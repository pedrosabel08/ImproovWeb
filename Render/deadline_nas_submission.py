"""NAS preparation mixed into the existing RenderEF queue consumer."""
from __future__ import annotations

import json
import ntpath
import os
import re

from deadline_scene import nas_scene_path, assert_scene_access, fingerprint


def frames(value: str) -> str:
    value = str(value or "").strip()
    if len(value) > 500 or not re.fullmatch(r"-?\d+(?:--?\d+(?:x[1-9]\d*)?)?(?:,-?\d+(?:--?\d+(?:x[1-9]\d*)?)?)*", value):
        raise ValueError("Informe frames válidos, por exemplo 0 ou 0-10x2.")
    return value


def dimensions(width, height, edge):
    width, height, edge = int(width), int(height), int(edge)
    if min(width, height, edge) < 1 or max(width, height, edge) > 65536:
        raise ValueError("A resolução deve estar entre 1 e 65536 pixels.")
    larger = max(width, height)
    result = ((width * edge + larger // 2) // larger, (height * edge + larger // 2) // larger)
    if min(result) < 1:
        raise ValueError("A proporção produziria uma dimensão inválida.")
    return result


def destination(context: dict, token: str) -> str:
    if not re.fullmatch(r"[a-f0-9-]{36}", token):
        raise ValueError("Identificador de submissão inválido.")
    # Work code comes exclusively from the image's obra, never from HTTP paths.
    work = str(context["nomenclatura"])
    if not work or re.search(r'[<>:"/\\|?*\x00-\x1f]', work) or work in {".", ".."} or work.endswith((".", " ")):
        raise ValueError("Código da obra inválido para o armazenamento dos renders.")
    return ntpath.join(r"\\192.168.0.250\renders2", work, "EF", f"flow_{context['imagem_id']}_{token}")


def snapshot_plan(content, record, request, context, path, scene_fingerprint, selection):
    from deadline_snapshots import digest, normalize
    from deadline_render_ef import snapshot
    if not request.get('arquivo_confirmado_por') or not request.get('arquivo_confirmado_em'):
        raise ValueError('Confirme explicitamente o arquivo .max final antes de preparar a alta.')
    job, plugin = dict(content['job_info']), dict(content['plugin_info'])
    if job.get('Plugin','').lower()!='3dsmax' or plugin.get('Version')!='2024':
        raise ValueError('Snapshot não contém um job 3ds Max 2024 compatível.')
    if str(plugin.get('MAXScriptJob','false')).lower() not in {'false','0',''}:
        raise ValueError('Jobs MAXScript exigem revisão e não podem ser reproduzidos como render comum.')
    for key in ('Pool','Group','Priority','Frames','ChunkSize'):
        if job.get(key) in (None,''):raise ValueError('Snapshot incompleto: '+key+' indisponível.')
    dependency_options = {'ResumeOnCompleteDependencies','ResumeOnDeletedDependencies','ResumeOnFailedDependencies'}
    for key,value in job.items():
        if key in dependency_options:
            if str(value).lower() not in {'true','false','0','1',''}:
                raise ValueError('Opção de dependência inválida: '+key)
            continue
        if value and ('Dependencies' in key and key!='IsFrameDependent' or key in {'PreJobScript','PostJobScript','PreTaskScript','PostTaskScript'}):
            if str(value).lower() not in {'false','0'}:raise ValueError('Configuração exige revisão controlada: '+key)
    # Preserve scripts only when archived AND explicitly reviewed by infrastructure.
    approved_scripts=json.loads(os.getenv('DEADLINE_SNAPSHOT_REVIEWED_SCRIPTS','{}'))
    auxiliaries=content.get('auxiliaries',[])
    for key in ('PreLoadScript','PostLoadScript','PreFrameScript','PostFrameScript'):
        value=plugin.get(key,'').strip()
        if value:
            match=next((a for a in auxiliaries if a['name']==ntpath.basename(value)),None)
            if not match or approved_scripts.get(match['sha256']) is not True:
                raise ValueError('Script auxiliar ainda não revisado: '+key)
            plugin[key]=match['name']
    normalized=normalize(job,plugin,content['metadata'])
    width,height=normalized['RenderWidth'],normalized['RenderHeight']
    resolution_source='Snapshot do render aprovado'
    if not width or not height:
        if selection.get('dimensions_confirmed') is not True:
            raise ValueError('Resolução original indisponível no snapshot. Informe e confirme as duas dimensões do render aprovado.')
        width,height=int(selection.get('source_width',0)),int(selection.get('source_height',0))
        resolution_source='Dimensões confirmadas pelo colaborador'
    edge=int(selection.get('edge',5000))
    new_w,new_h=dimensions(width,height,edge)
    if edge<max(width,height):raise ValueError('O tamanho de alta não pode reduzir a resolução original confirmada.')
    dest=destination(context,request['submissao_uuid'])
    stem=f"flow_{request['imagem_id']}_{request['submissao_uuid']}"
    outputs={}
    for key,value in plugin.items():
        if value and (key=='RenderOutput' or re.fullmatch(r'RenderElementOutputFilename\d+',key)):
            name=ntpath.basename(value)
            if re.search(r'[<>:"/\\|?*\x00-\x1f]',name):raise ValueError('Nome de saída inválido no snapshot: '+key)
            if not ntpath.splitext(name)[1]:raise ValueError('Saída sem formato no snapshot: '+key)
            if ntpath.splitext(name)[1].lower() not in {'.exr','.cxr','.jpg','.jpeg','.png','.tif','.tiff','.tga','.bmp'}:
                raise ValueError('Formato de saída sem validador disponível: '+key)
            outputs[key]=ntpath.join(dest,stem+'_'+name)
    if 'RenderOutput' not in outputs:raise ValueError('Saída principal indisponível no snapshot aprovado.')
    # Source is selected/confirmed, never guessed from the newest NAS timestamp.
    plugin.update(SceneFile='',IgnoreMissingExternalFiles='0',IgnoreMissingDLLs='0',IgnoreMissingXREFs='0')
    keys={k.lower():k for k in plugin}
    wkey,hkey=keys.get('renderwidth','RenderWidth'),keys.get('renderheight','RenderHeight')
    for key in list(job):
        if key in {'JobID','JobId','ID','Id'} or (key.startswith('ExtraInfoKeyValue') and str(job[key]).startswith('Flow')):del job[key]
        elif re.fullmatch(r'OutputDirectory\d+',key):job[key]=dest
        elif re.fullmatch(r'OutputFilename\d+',key) and job[key]:job[key]=stem+'_'+ntpath.basename(job[key])
    job.update(Name=context['imagem_nome']+'_EF',InitialStatus='Suspended',OutputDirectory0=dest,OnJobComplete='Nothing')
    job['OutputFilename0']=ntpath.basename(outputs['RenderOutput'])
    files=[path]+[a['path'] for a in auxiliaries]
    plan={'origin':'NAS','snapshot_id':record['id'],'approved_attempt_id':record['tentativa_id'],
          'snapshot_sha256':record['conteudo_sha256'],'scene':ntpath.basename(path),'scene_path':path,
          'scene_size':scene_fingerprint['size'],'scene_fingerprint':scene_fingerprint,
          'old_width':width,'old_height':height,'width':new_w,'height':new_h,'width_key':wkey,'height_key':hkey,
          'source_info':plugin,'job_info':job,'outputs':outputs,'required_outputs':['RenderOutput'],
          'destination':dest,'job_name':job['Name'],'files':files,'auxiliaries':auxiliaries,
          'camera':normalized['camera'],'renderer':normalized['renderer'],'max_version':normalized['max_version'],
          'frames':job['Frames'],'generic':{k:str(job[k]) for k in ('Pool','Group','Priority','ChunkSize','Frames')},
          'parameter_sources':{k:'Snapshot do render aprovado' for k in ('camera','renderer','frames','Pool','Group','Priority','ChunkSize','max_version')},
          'plugin_overrides':{'PreLoadScript':'flow_asset_base_pre.ms','PostLoadScript':'flow_asset_base_post.ms','SaveFile':'true'},
          'blockers':[],'rounding':True,'selection':selection,'file_confirmation':{'by':request['arquivo_confirmado_por'],'at':str(request['arquivo_confirmado_em'])}}
    plan['parameter_sources']['resolution']=resolution_source
    plan['snapshot']=snapshot(plugin);plan['job_snapshot']=snapshot(job)
    return plan


class NasSubmission:
    def selected_scene(self,request):
        with self.db.transaction() as cursor:
            cursor.execute("""SELECT al.* FROM arquivo_log al JOIN funcao_imagem fi ON fi.idfuncao_imagem=al.funcao_imagem_id
                WHERE al.id=%s AND fi.imagem_id=%s AND LOWER(al.nome_arquivo) LIKE '%%.max'
                AND al.status IN ('concluido','atualizado','finalizado','historico')""",(request['arquivo_log_id'],request['imagem_id']))
            record=cursor.fetchone()
        if not record:raise RuntimeError('O .max selecionado não pertence à imagem ou seu upload não foi concluído.')
        path=nas_scene_path(record['caminho'])
        if self.context(request)['nomenclatura'].casefold() not in [p.casefold() for p in path.split('\\')]:
            raise RuntimeError('A cena não pertence à pasta da obra autorizada.')
        return path

    def inspect_nas(self,request,**_unused):
        """Historical method name; now prepares configuration from DB only."""
        from deadline_snapshots import SnapshotStore,approved_attempt,decode_snapshot,flow_enabled,capture_enabled,normalize
        if not flow_enabled():raise RuntimeError('Preparação por snapshots ainda não habilitada.')
        self.context(request)
        store=SnapshotStore(self.db)
        if not store.ready():raise RuntimeError('Migration de snapshots pendente.')
        approved=approved_attempt(self.db,request)
        row=store.get(approved['deadline_job_id'])
        if not row:
            if not capture_enabled():raise RuntimeError('Snapshot ausente e captura desabilitada. Não haverá inspeção como fallback.')
            store.queue(approved,priority=100)
            with self.db.transaction() as c:
                c.execute('SELECT ultimo_erro,tentativas FROM render_job_snapshot_capturas WHERE deadline_job_id=%s',(approved['deadline_job_id'],))
                capture=c.fetchone()
            if capture and capture['tentativas']>=3 and capture['ultimo_erro']:
                raise RuntimeError('Configuração original ainda indisponível: '+capture['ultimo_erro'])
            self.update(request,'SNAPSHOT','Aguardando preservação da configuração original no Deadline. Se ela não estiver disponível, o envio permanecerá bloqueado.',status='SNAPSHOT_AGUARDAR')
            return
        if row['tentativa_id']!=approved['id'] or row['imagem_id']!=request['imagem_id']:
            raise RuntimeError('Snapshot não corresponde à tentativa aprovada.')
        content=decode_snapshot(row)
        path=self.selected_scene(request)
        from concurrent.futures import ThreadPoolExecutor
        check=self.file_checks.get(request['id'])
        if check is None:
            if self.file_pool is None:self.file_pool=ThreadPoolExecutor(max_workers=1,thread_name_prefix='flow-nas-integrity')
            self.file_checks[request['id']]=(path,self.file_pool.submit(assert_scene_access,path))
            self.update(request,'ARQUIVO','Conferindo integridade do arquivo final, sem abrir o Max.',status='SNAPSHOT_ARQUIVO')
            return
        if check[0]!=path:raise RuntimeError('O vínculo do arquivo mudou durante a preparação.')
        if not check[1].done():
            self.update(request,'ARQUIVO','Conferindo integridade do arquivo final, sem abrir o Max.',status='SNAPSHOT_ARQUIVO')
            return
        del self.file_checks[request['id']]
        before=check[1].result()
        selection=json.loads(request.get('configuracao_json') or '{}')
        normalized=normalize(content['job_info'],content['plugin_info'],content['metadata'])
        if not normalized['RenderWidth'] or not normalized['RenderHeight']:
            if selection.get('dimensions_confirmed') is not True:
                self.update(request,'DIMENSOES','A resolução original não foi exposta pelo Deadline. Informe as dimensões comprovadas do render aprovado.',status='CONFIGURAR',
                            snapshot_id=row['id'],origem_tentativa_id=approved['id'],origem_job_id=approved['deadline_job_id'],plano_json=json.dumps({'snapshot_id':row['id'],'scene_path':path,'scene':path,'camera':normalized['camera'],'renderer':normalized['renderer'],'max_version':normalized['max_version'],'generic':{k:normalized[k] for k in ('Pool','Group','Priority','Frames','ChunkSize')},'blockers':[]}))
                return
        plan=snapshot_plan(content,row,request,self.context(request),path,before,selection)
        self.update(request,'REVISAO','Configuração do render aprovado recuperada. Confira o arquivo final, a resolução e o destino.',status='PRONTO',
                    snapshot_id=row['id'],origem_tentativa_id=approved['id'],origem_job_id=approved['deadline_job_id'],plano_json=json.dumps(plan,ensure_ascii=False))

    def configure_nas(self,request):return self.inspect_nas(request)

    def revalidate_nas(self,request,plan):
        from deadline_snapshots import SnapshotStore,approved_attempt,decode_snapshot,flow_enabled
        if not flow_enabled():raise RuntimeError('Preparação por snapshots desabilitada para novos envios.')
        approved=approved_attempt(self.db,request)
        row=SnapshotStore(self.db).get(approved['deadline_job_id'])
        if not row or row['id']!=plan['snapshot_id'] or row['conteudo_sha256']!=plan['snapshot_sha256']:
            raise RuntimeError('O snapshot aprovado mudou. Faça uma nova conferência.')
        decode_snapshot(row)
        path=self.selected_scene(request)
        if path!=plan['scene_path'] or assert_scene_access(path)!=plan['scene_fingerprint']:
            raise RuntimeError('O .max confirmado mudou. Prepare novamente a alta.')
        if not request.get('arquivo_confirmado_por') or not request.get('arquivo_confirmado_em'):
            raise RuntimeError('Arquivo final não confirmado.')
        for asset in plan.get('auxiliaries',[]):
            fp=fingerprint(asset['path'])
            if fp['sha256']!=asset['sha256'] or fp['size']!=asset['size']:
                raise RuntimeError('Auxiliar indisponível ou alterado: '+asset['name'])
        return {'_job_info':plan['job_info']},dict(plan['source_info']),list(plan['files'])


