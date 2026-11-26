"""Snapshot replay tests. No real Deadline/Max processes or farm submissions."""
import copy
import json
import os
from pathlib import Path
import sys
import tempfile
import unittest
from concurrent.futures import Future
from types import SimpleNamespace
from unittest.mock import Mock,patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from deadline_client import CommandResult
from deadline_snapshots import digest,normalize,export_snapshot,decode_snapshot,approved_attempt,SnapshotCapture
from deadline_nas_submission import snapshot_plan,dimensions
from deadline_render_ef import RenderEF,read_info,asset_base_script
from deadline_scene import fingerprint,inspect_scene
from deadline_ef_script_recovery import prepare
from deadline_inspection import is_inspection

TOKEN='a1212121-1234-4123-8123-123456789abc'


def fixture():return json.loads((Path(__file__).parent/'fixtures/deadline_snapshot_v1.json').read_text())


def record(content=None):
    content=content or fixture()
    return {'id':1,'tentativa_id':content['attempt_id'],'imagem_id':content['image_id'],'deadline_job_id':content['job_id'],
            'formato':1,'conteudo_json':json.dumps(content),'conteudo_sha256':digest(content),'estado':'CAPTURADO'}


def request():return {'id':7,'imagem_id':10,'render_origem_id':10,'submissao_uuid':TOKEN,'arquivo_log_id':101,'origem':'NAS','origem_job_id':'a'*24,'arquivo_confirmado_por':7,'arquivo_confirmado_em':'2026-11-06 14:00:00'}


def plan(content=None,selection=None,path=r'\\192.168.0.250\clientes\2026\QA_TEST\final.max',fp=None):
    content=content or fixture()
    return snapshot_plan(content,record(content),request(),{'imagem_id':10,'imagem_nome':'Imagem QA','nomenclatura':'QA_TEST'},path,fp or {'sha256':'b'*64,'size':20,'mtime_ns':1},selection or {'edge':5000})


class SnapshotTests(unittest.TestCase):
    def test_ef_uses_approved_output_name_and_requires_all_elements(self):
        content = fixture()
        base = '12.RAY_DOM Living Apto tipo 4 com vista real fotografica'
        content['plugin_info']['RenderOutput'] = 'M:\\RAY_DOM\\R00\\' + base + '_R00..exr'
        content['plugin_info']['RenderElementOutputFilename0'] = 'M:\\RAY_DOM\\R00\\' + base + '_R00_CESSENTIAL_Direct..exr'
        built = plan(content)
        self.assertTrue(built['destination'].endswith('\\EF\\' + base + '_EF'))
        self.assertTrue(built['outputs']['RenderOutput'].endswith('\\' + base + '_EF..exr'))
        self.assertTrue(built['outputs']['RenderElementOutputFilename0'].endswith('\\' + base + '_EF_CESSENTIAL_Direct..exr'))
        self.assertEqual(set(built['required_outputs']), set(built['outputs']))

    def test_high_resolution_snapshot_uses_missing_asset_tolerances(self):
        content = fixture()
        content['plugin_info'].update(IgnoreMissingDLLs='0', IgnoreMissingExternalFiles='0',
                                      IgnoreMissingUVWs='0', IgnoreMissingXREFs='0',
                                      IgnoreRenderElements='0', IncludeLocalPaths='0')
        plugin = plan(content)['source_info']
        for key in ('IgnoreMissingDLLs', 'IgnoreMissingExternalFiles', 'IgnoreMissingUVWs', 'IgnoreMissingXREFs'):
            self.assertEqual(plugin[key], '1')
        self.assertEqual(plugin['IgnoreRenderElements'], '0')
        self.assertEqual(plugin['IncludeLocalPaths'], '0')

    def test_dependency_resume_options_preserved_without_dependencies(self):
        content=fixture()
        for key in ('ResumeOnCompleteDependencies','ResumeOnDeletedDependencies','ResumeOnFailedDependencies'):
            content['job_info'][key]='True'
        built=plan(content)
        self.assertEqual(built['job_info']['ResumeOnCompleteDependencies'],'True')
        for key in ('JobDependencies','ScriptDependencies','PreJobScript'):
            blocked=copy.deepcopy(content);blocked['job_info'][key]='required-resource'
            with self.subTest(key=key),self.assertRaisesRegex(ValueError,'revisão controlada'):plan(blocked)
        content['job_info']['ResumeOnCompleteDependencies']='unexpected'
        with self.assertRaisesRegex(ValueError,'inválida'):plan(content)

    def export_client(self,temp,content=None,extra=None):
        content=content or fixture();client=Mock()
        def run(args):
            from deadline_render_ef import write_info
            job=copy.deepcopy(content['job_info']);job.pop('ChunkSize',None)
            write_info(Path(args[3]),job);write_info(Path(args[4]),content['plugin_info'])
            meta=copy.deepcopy(content['metadata']);meta['auxiliary_path']=temp
            if extra:meta['auxiliary_names'].append(extra)
            Path(args[5]).write_text(json.dumps(meta),encoding='utf-8')
            return CommandResult(True,'read-only export')
        client.run.side_effect=run
        client.get_job.return_value=(CommandResult(True,'PluginDataFileName=original.max\nChunkSize=1'),{'PluginDataFileName':'original.max','ChunkSize':'1'})
        return client

    def test_capture_complete_job_plugin_and_raw_data(self):
        with tempfile.TemporaryDirectory() as temp:
            client=self.export_client(temp)
            result=export_snapshot(client,{'id':1,'imagem_id':10,'deadline_job_id':'a'*24})
            self.assertEqual(result['state'],'CAPTURADO')
            self.assertEqual(result['content']['job_info']['ChunkSize'],'1')
            self.assertEqual(result['content']['recovered_fields']['ChunkSize'],'GetJob/GetDeletedJob')
            self.assertEqual(result['content']['plugin_info']['Camera'],'Camera_aprovada')
            self.assertTrue(result['content']['job_file_base64'])
            self.assertEqual(client.run.call_args.args[0][0],'-ExecuteScriptNoGui')

    def test_preserve_auxiliaries_with_hash_and_reject_missing(self):
        with tempfile.TemporaryDirectory() as temp:
            source=Path(temp)/'source';root=Path(temp)/'archive';source.mkdir();root.mkdir()
            client=self.export_client(str(source),extra='required.ms')
            with self.assertRaises(FileNotFoundError):export_snapshot(client,{'id':1,'imagem_id':10,'deadline_job_id':'a'*24},root=root)
            (source/'required.ms').write_text('-- safe fixture')
            result=export_snapshot(client,{'id':1,'imagem_id':10,'deadline_job_id':'a'*24},root=root)
            a=result['content']['auxiliaries'][0]
            self.assertEqual(fingerprint(a['path'])['sha256'],a['sha256'])

    def test_snapshot_tampering_and_unsupported_version(self):
        row=record();self.assertEqual(decode_snapshot(row)['job_id'],'a'*24)
        bad=copy.deepcopy(row);bad['conteudo_json']=bad['conteudo_json'].replace('1920','1600')
        with self.assertRaisesRegex(RuntimeError,'Integridade'):decode_snapshot(bad)
        row['formato']=2
        with self.assertRaises(RuntimeError):decode_snapshot(row)

    def test_capture_deleted_job_uses_same_sdk_export_without_restore(self):
        with tempfile.TemporaryDirectory() as temp:
            content=fixture();content['metadata']['origin']='DEADLINE_EXCLUIDO'
            client=self.export_client(temp,content)
            client.get_job.return_value=(CommandResult(False,'absent'),{})
            exported=client.run.side_effect
            def run(args):
                if args[0]=='-GetDeletedJob':return CommandResult(True,'PluginDataFileName=original.max\nChunkSize=1')
                return exported(args)
            client.run.side_effect=run
            result=export_snapshot(client,{'id':1,'imagem_id':10,'deadline_job_id':'a'*24})
            self.assertEqual(result['origin'],'DEADLINE_EXCLUIDO')
            self.assertFalse(any('Restore' in str(call) or 'Resume' in str(call) for call in client.run.call_args_list))

    def test_pending_capture_does_not_block_worker_loop(self):
        worker=SimpleNamespace(database=Mock(),client=Mock(),logger=Mock())
        capture=SnapshotCapture(worker);future=Future();capture.pending=({'id':1},future)
        capture.tick()
        worker.database.transaction.assert_not_called();worker.client.run.assert_not_called()

    def test_reconstruct_after_original_job_is_gone_without_contacting_deadline(self):
        original=fixture();built=plan(original)
        self.assertEqual((built['width'],built['height']),(5000,2813))
        for key in ['Camera','SubmittedRendererName','Version','Padding','RemovePadding']:
            self.assertEqual(built['source_info'][key],original['plugin_info'][key])
        for key in ['Pool','Group','Priority','Frames','ChunkSize']:
            self.assertEqual(built['job_info'][key],original['job_info'][key])
        self.assertEqual(original,fixture())
        self.assertNotIn('SceneFile',built['source_info'])
        self.assertTrue(built['destination'].endswith(r'\EF\Imagem_EF'))
        self.assertTrue(built['outputs']['RenderOutput'].endswith(r'\Imagem_EF..exr'))
        self.assertTrue(built['outputs']['RenderElementOutputFilename0'].endswith(r'\Imagem_EF_Beauty..exr'))
        self.assertEqual(set(built['required_outputs']), set(built['outputs']))
        self.assertEqual(built['job_info']['InitialStatus'],'Suspended')

    def test_unknown_dimensions_require_explicit_confirmed_size(self):
        content=fixture();del content['plugin_info']['RenderWidth'];del content['plugin_info']['RenderHeight']
        with self.assertRaisesRegex(ValueError,'Resolução original'):plan(content)
        built=plan(content,{'edge':5000,'source_width':1200,'source_height':1600,'dimensions_confirmed':True})
        self.assertEqual((built['width'],built['height']),(3750,5000))
        self.assertIn('colaborador',built['parameter_sources']['resolution'])

    def test_missing_camera_renderer_not_invented(self):
        content=fixture();del content['plugin_info']['Camera'];del content['plugin_info']['SubmittedRendererName']
        built=plan(content)
        self.assertIsNone(built['camera']);self.assertIsNone(built['renderer'])
        self.assertNotIn('Camera',built['source_info'])

    def test_incomplete_snapshot_blocks_instead_of_generic_defaults(self):
        for key in ['Pool','Frames','ChunkSize','Priority','Group']:
            content=fixture();del content['job_info'][key]
            with self.subTest(key=key),self.assertRaisesRegex(ValueError,key):plan(content)

    def test_file_confirmation_is_required(self):
        req=request();req['arquivo_confirmado_por']=None
        with self.assertRaisesRegex(ValueError,'Confirme explicitamente'):
            snapshot_plan(fixture(),record(),req,{},'file.max',{}, {})

    def test_no_inspection_fallback_and_legacy_job_filter(self):
        with self.assertRaisesRegex(RuntimeError,'Inspeção de cenas desativada'):inspect_scene('missing.max',Mock())
        self.assertTrue(is_inspection({'ExtraInfoDictionary':'FlowJobType=SceneInspection'}))
        self.assertTrue(is_inspection({'ExtraInfoDictionary':'FlowInspectionUUID='+TOKEN}))
        source=(Path(__file__).parents[1]/'deadline_render_ef.py').read_text(encoding='utf-8')
        self.assertNotIn('remote_inspection',source)
        self.assertNotIn('inspect_scene(',source)

    def test_approval_selection_rejects_multiple_or_absent_candidates(self):
        from contextlib import contextmanager
        for values in [[],[{'id':1},{'id':2}],[{'id':5}]]:
            cursor=Mock();cursor.fetchall.return_value=values
            @contextmanager
            def transaction():yield cursor
            db=SimpleNamespace(transaction=transaction)
            if len(values)==1:self.assertEqual(approved_attempt(db,request()),{'id':5})
            else:
                with self.assertRaises(RuntimeError):approved_attempt(db,request())
            query=cursor.execute.call_args.args[0]
            self.assertIn('APROVADA_NO_FLOW',query);self.assertNotIn('ORDER BY',query)

    def test_snapshot_capture_restart_and_lost_database_commit_is_idempotent(self):
        worker=SimpleNamespace(database=Mock(),client=Mock(),logger=Mock())
        capture=SnapshotCapture(worker);capture.store=Mock();capture.store.ready.return_value=False
        future=Future();future.set_result({'result':'immutable'});attempt={'id':1}
        capture.pending=(attempt,future)
        capture.tick();capture.store.commit.assert_called_once_with(attempt,{'result':'immutable'})
        self.assertIsNone(capture.pending)
        restarted=SnapshotCapture(worker);self.assertIsNone(restarted.pending)

    def test_capture_flag_off_never_touches_deadline_or_db(self):
        worker=SimpleNamespace(database=Mock(),client=Mock(),logger=Mock());capture=SnapshotCapture(worker)
        with patch.dict(os.environ,{'DEADLINE_SNAPSHOT_CAPTURE_ENABLED':'0'}):capture.tick()
        worker.client.run.assert_not_called();worker.database.transaction.assert_not_called()

    def test_file_missing_and_file_changed_block_revalidation(self):
        service=RenderEF(SimpleNamespace(database=Mock(),client=Mock()))
        service.selected_scene=Mock(return_value='missing.max')
        with patch.dict(os.environ,{'DEADLINE_EF_SNAPSHOT_ENABLED':'1'}),patch('deadline_snapshots.approved_attempt',return_value={'id':1,'deadline_job_id':'a'*24}),patch('deadline_snapshots.SnapshotStore') as store:
            store.return_value.get.return_value=record()
            with self.assertRaisesRegex(RuntimeError,'mudou'):service.revalidate_nas(request(),plan())

    def test_submission_uses_final_copy_preserves_aux_and_suspended_gate(self):
        with tempfile.TemporaryDirectory() as temp:
            source=Path(temp)/'final.max';source.write_bytes(b'original scene never opened')
            fp=fingerprint(str(source));built=plan(path=str(source),fp=fp)
            service=RenderEF(SimpleNamespace(database=Mock(),client=Mock(),logger=Mock()))
            service.update=lambda r,s,m,**fields:r.update(**fields)
            service.source=Mock(return_value=({'_job_info':built['job_info']},built['source_info'],[str(source)]))
            service.context=Mock(return_value={})
            def submit(args):
                job=read_info(Path(args[0]));plugin=read_info(Path(args[1]))
                self.assertEqual(job['InitialStatus'],'Suspended');self.assertEqual(plugin['RenderWidth'],'5000')
                self.assertNotIn('SceneFile', plugin)
                self.assertNotEqual(args[2],str(source));self.assertEqual(Path(args[2]).read_bytes(),source.read_bytes())
                self.assertEqual(len(args),5)
                return CommandResult(True,'JobID='+'b'*24)
            service.client.run.side_effect=submit
            with patch.dict(os.environ,{'DEADLINE_EF_SUBMIT_ENABLED':'1'}),patch('deadline_render_ef.ensure_smb_destination_ready'):
                self.assertEqual(service.submit(request(),built,{}),'b'*24)
            self.assertEqual(fingerprint(str(source)),fp)
            service.client.run.reset_mock()
            with patch.dict(os.environ,{'DEADLINE_EF_SUBMIT_ENABLED':'0'}):
                with self.assertRaisesRegex(RuntimeError,'desabilitada'):service.submit(request(),built,{})
            service.client.run.assert_not_called()

    def test_existing_destination_blocks_submission_without_overwriting(self):
        with tempfile.TemporaryDirectory() as temp:
            existing = Path(temp) / 'existing.exr'
            existing.write_bytes(b'previous render')
            built = plan()
            built['destination'] = temp
            service = RenderEF(SimpleNamespace(database=Mock(), client=Mock(), logger=Mock()))
            service.update = Mock()
            service.source = Mock(return_value=({'_job_info': built['job_info']}, built['source_info'], built['files']))
            with patch.dict(os.environ, {'DEADLINE_EF_SUBMIT_ENABLED': '1'}):
                with self.assertRaisesRegex(RuntimeError, 'Nenhuma saída será sobrescrita'):
                    service.submit(request(), built, {})
            self.assertEqual(existing.read_bytes(), b'previous render')
            service.client.run.assert_not_called()

    def test_validate_scene_when_deadline_reports_combined_auxiliary_size(self):
        with tempfile.TemporaryDirectory() as temp:
            scene = Path(temp) / 'final.max'
            scene.write_bytes(b'confirmed scene')
            built = plan(path=str(scene), fp=fingerprint(str(scene)))
            for phase, name in [('PreLoadScript', 'flow_asset_base_pre.ms'), ('PostLoadScript', 'flow_asset_base_post.ms')]:
                (Path(temp) / name).write_text(asset_base_script(built, phase), encoding='utf-8-sig')
            data = {**built['generic'], 'PluginDataFileName': built['scene'],
                    'PluginDataFileSize': str(sum(p.stat().st_size for p in Path(temp).iterdir()))}
            actual = {**built['source_info'], **built['outputs'], **built['plugin_overrides'],
                      built['width_key']: str(built['width']), built['height_key']: str(built['height'])}
            service = RenderEF(SimpleNamespace(database=Mock(), client=Mock(), logger=Mock()))
            service.client.get_job.return_value = (CommandResult(True, ''), data)
            service.command = Mock(return_value=temp)
            req = {**request(), 'novo_job_id': 'b' * 24}
            with patch('deadline_render_ef.plugin_info', return_value=actual), patch('deadline_snapshots.SnapshotStore') as store:
                store.return_value.get.return_value = record()
                service.validate(req, built)
                with patch('deadline_render_ef.plugin_info', return_value={**actual, 'SceneFile': ''}):
                    with self.assertRaisesRegex(RuntimeError, 'SceneFile presente'):
                        service.validate(req, built)
                scene.write_bytes(b'altered scene!!')  # same size, different contents
                with self.assertRaisesRegex(RuntimeError, 'difere do .max confirmado'):
                    service.validate(req, built)
                scene.write_bytes(b'short')
                with self.assertRaisesRegex(RuntimeError, 'não foi encontrado'):
                    service.validate(req, built)
                data['PluginDataFileName'] = 'wrong.max'
                with self.assertRaisesRegex(RuntimeError, 'não corresponde'):
                    service.validate(req, built)

    def test_asset_scripts_have_explicit_boolean_contract_for_both_phases(self):
        for phase in ('PreLoadScript', 'PostLoadScript'):
            with self.subTest(phase=phase):
                built = plan()
                script = asset_base_script(built, phase)
                self.assertIn('sessionPaths.add #map ', script)
                self.assertIn('sessionPaths.add #xref ', script)
                self.assertIn('sysInfo.currentdir = ', script)
                self.assertTrue(script.endswith('\ntrue\n)\n'))
                built['source_info'][phase] = 'reviewed.ms'
                script = asset_base_script(built, phase)
                self.assertIn('local flowOriginalResult = fileIn ', script)
                self.assertTrue(script.endswith('if flowOriginalResult == true then true else false\n)\n'))
                self.assertNotIn('catch', script)

    def test_legacy_scripts_are_identified_and_blocked_for_both_phases(self):
        with tempfile.TemporaryDirectory() as temp:
            scene = Path(temp) / 'final.max'; scene.write_bytes(b'confirmed scene')
            built = plan(path=str(scene), fp=fingerprint(str(scene)))
            phases = [('PreLoadScript', 'flow_asset_base_pre.ms'), ('PostLoadScript', 'flow_asset_base_post.ms')]
            data = {**built['generic'], 'PluginDataFileName': built['scene'], 'PluginDataFileSize': '999'}
            actual = {**built['source_info'], **built['outputs'], **built['plugin_overrides'],
                      built['width_key']: str(built['width']), built['height_key']: str(built['height'])}
            service = RenderEF(SimpleNamespace(database=Mock(), client=Mock(), logger=Mock()))
            service.client.get_job.return_value = (CommandResult(True, ''), data)
            service.command = Mock(return_value=temp)
            req = {**request(), 'novo_job_id': 'b' * 24}
            with patch('deadline_render_ef.plugin_info', return_value=actual), patch('deadline_snapshots.SnapshotStore') as store:
                store.return_value.get.return_value = record()
                for old_phase, old_name in phases:
                    for phase, name in phases:
                        (Path(temp) / name).write_text(asset_base_script(built, phase, legacy=phase == old_phase), encoding='utf-8-sig')
                    with self.subTest(phase=old_phase), self.assertRaisesRegex(RuntimeError, 'Recuperação controlada'):
                        service.validate(req, built)
                    self.assertEqual(json.loads(service.worker.logger.error.call_args.kwargs['extra']['reason'])['script'], old_name)
                (Path(temp) / phases[0][1]).write_text('tampered', encoding='utf-8-sig')
                with self.assertRaisesRegex(RuntimeError, 'divergente'):
                    service.validate(req, built)

    def test_recovery_prepares_auditable_files_without_mutating_job(self):
        with tempfile.TemporaryDirectory() as temp:
            source = Path(temp) / ('b' * 24); source.mkdir()
            built = plan()
            for phase, name in [('PreLoadScript', 'flow_asset_base_pre.ms'), ('PostLoadScript', 'flow_asset_base_post.ms')]:
                (source / name).write_text(asset_base_script(built, phase, legacy=True), encoding='utf-8-sig')
            before = {p.name: p.read_bytes() for p in source.iterdir()}
            target = Path(temp) / 'staging'
            manifest = prepare(built, 'b' * 24, source, target)
            self.assertEqual(before, {p.name: p.read_bytes() for p in source.iterdir()})
            self.assertEqual(len(manifest['scripts']), 2)
            for item in manifest['scripts']:
                self.assertEqual((target / (item['name'] + '.original')).read_bytes(), before[item['name']])
                self.assertNotEqual(item['before_sha256'], item['after_sha256'])
            with self.assertRaises(FileExistsError): prepare(built, 'b' * 24, source, target)
            with self.assertRaises(ValueError): prepare(built, 'a' * 24, source, Path(temp) / 'other')
            (source / 'flow_asset_base_pre.ms').write_text('modified', encoding='utf-8')
            with self.assertRaisesRegex(ValueError, 'legado'): prepare(built, 'b' * 24, source, Path(temp) / 'other')
            self.assertFalse((Path(temp) / 'other').exists())

    def test_failed_binding_never_resumes_or_duplicates_job(self):
        service=RenderEF(SimpleNamespace(database=Mock(),client=Mock(),logger=Mock(),repository=Mock()))
        service.context=Mock(return_value={'status_id':9});service.validate=Mock(return_value=(CommandResult(True,''),{'Status':'Suspended'}))
        service.update=Mock();service.worker.repository.bind_ef_submission.side_effect=RuntimeError('database failed')
        req={**request(),'novo_job_id':'b'*24,'plano_json':json.dumps(plan())}
        with self.assertRaisesRegex(RuntimeError,'database failed'):service.execute(req)
        service.client.run.assert_not_called()

    def test_reconcile_uses_existing_render_uuid_without_new_submission(self):
        service=RenderEF(SimpleNamespace(database=Mock(),client=Mock()))
        service.client.find_submission.return_value=['b'*24];service.update=lambda r,s,m,**fields:r.update(**fields)
        req=request();self.assertTrue(service.reconcile_submission(req));self.assertEqual(req['novo_job_id'],'b'*24)
        service.client.run.assert_not_called()

    def test_lost_submission_response_reconciles_without_resubmission(self):
        service=RenderEF(SimpleNamespace(database=Mock(),client=Mock()))
        service.client.find_submission.return_value=['b'*24]
        service.update=lambda r,s,m,**fields:r.update(**fields)
        req={**request(),'submissao_iniciada_em':True}
        self.assertTrue(service.reconcile_submission(req))
        self.assertEqual(req['novo_job_id'],'b'*24)
        service.client.run.assert_not_called()
        for matches in ([], ['b'*24, 'c'*24]):
            service.client.find_submission.return_value=matches
            ambiguous={**request(),'submissao_iniciada_em':True}
            self.assertFalse(service.reconcile_submission(ambiguous))
            self.assertNotIn('novo_job_id', ambiguous)
            self.assertEqual(ambiguous['status'], 'ATENCAO')


if __name__=='__main__':unittest.main()
