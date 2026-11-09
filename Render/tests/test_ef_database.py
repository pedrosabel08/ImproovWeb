"""Opt-in integration tests. Only a newly created flow_ef_test_* schema is mutated."""
import concurrent.futures
import json
import os
from pathlib import Path
import subprocess
import threading
import unittest
import uuid


@unittest.skipUnless(os.getenv('FLOW_EF_DATABASE_TESTS') == '1', 'set FLOW_EF_DATABASE_TESTS=1 for isolated MySQL tests')
class DatabaseTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        import pymysql
        from dotenv import dotenv_values
        cls.root=Path(__file__).resolve().parents[2]
        config=dotenv_values(cls.root/'.env')
        cls.config=dict(host=config['DB_HOST'],port=int(config.get('DB_PORT',3306)),user=config['DB_USERNAME'],password=config['DB_PASSWORD'],charset='utf8mb4',autocommit=True)
        if os.getenv('FLOW_EF_TEST_HOST'):
            cls.config.update(host=os.environ['FLOW_EF_TEST_HOST'],port=int(os.getenv('FLOW_EF_TEST_PORT','3306')),user=os.environ['FLOW_EF_TEST_USER'],password=os.getenv('FLOW_EF_TEST_PASSWORD',''))
        cls.schema='flow_ef_test_'+uuid.uuid4().hex[:16]
        cls.admin=pymysql.connect(**cls.config)
        with cls.admin.cursor() as c:c.execute('CREATE DATABASE `'+cls.schema+'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
        cls.config['database']=cls.schema
        cls.db=pymysql.connect(**cls.config)
        with cls.db.cursor() as c:
            c.execute('CREATE TABLE imagens_cliente_obra (idimagens_cliente_obra INT PRIMARY KEY, obra_id INT, status_id INT,imagem_nome VARCHAR(255)) ENGINE=InnoDB')
            c.execute("CREATE TABLE render_alta (idrender_alta INT PRIMARY KEY,imagem_id INT,status_id INT,responsavel_id INT,status VARCHAR(50),excluido_em DATETIME NULL,deadline_job_id CHAR(24),has_error INT DEFAULT 0,errors TEXT) ENGINE=InnoDB")
            c.execute("INSERT INTO imagens_cliente_obra VALUES (1,1,9,'Image 1'),(2,2,9,'Image 2'),(10,10,9,'Image 10')")
            c.execute("INSERT INTO render_alta (idrender_alta,imagem_id,status_id,responsavel_id,status) VALUES (1,1,3,7,'Aprovado'),(2,2,3,9,'Aprovado'),(10,10,3,7,'Aprovado')")
            c.execute((cls.root/'sql/2026-10-20_render_ef.sql').read_text(encoding='utf-8'))
            c.execute("INSERT INTO render_ef_solicitacoes (id,render_origem_id,imagem_id,criado_por,origem_job_id,status,mensagem,novo_job_id) VALUES (1,1,1,7,%s,'CONCLUIDO','historical',%s),(2,2,2,7,%s,'ERRO','historical failure',NULL)",('a'*24,'b'*24,'c'*24))
            for sql in (cls.root/'sql/2026-11-06_render_ef_nas.sql').read_text(encoding='utf-8').split(';'):
                if sql.strip():c.execute(sql)
            c.execute('CREATE TABLE obra (idobra INT PRIMARY KEY,nomenclatura VARCHAR(50),nome_obra VARCHAR(100))')
            c.execute("INSERT INTO obra VALUES (1,'QA1','QA1'),(2,'QA2','QA2'),(10,'QA10','QA10')")
            c.execute('CREATE TABLE status_imagem (idstatus INT PRIMARY KEY,nome_status VARCHAR(50))')
            c.execute("INSERT INTO status_imagem VALUES (9,'EF'),(3,'R00')")
            c.execute('CREATE TABLE usuario_cargo (usuario_id INT,cargo_id INT)')
            c.execute('CREATE TABLE cargo (id INT,nome VARCHAR(100))')
            c.execute('CREATE TABLE funcao_imagem (idfuncao_imagem INT,imagem_id INT,colaborador_id INT,funcao_id INT)')
            c.execute('CREATE TABLE funcao (idfuncao INT,nome_funcao VARCHAR(100))')
            c.execute("INSERT INTO funcao VALUES (4,'Finalização')")
            c.execute('CREATE TABLE arquivo_log (id INT,funcao_imagem_id INT,nome_arquivo VARCHAR(255),caminho VARCHAR(1024),status VARCHAR(50),tipo VARCHAR(20),criado_em DATETIME,tamanho BIGINT,colaborador_id INT)')
            c.execute("INSERT INTO arquivo_log VALUES (101,10,'scene.max','Z:\\\\2026\\\\QA10\\\\scene.max','concluido','IMG',NOW(),100,7),(102,20,'other.max','Z:\\\\2026\\\\QA2\\\\other.max','concluido','IMG',NOW(),100,9)")
            c.execute('CREATE TABLE render_tentativas (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,motivo_encerramento VARCHAR(255),render_id INT,imagem_id INT,status_id INT,numero_tentativa INT,deadline_job_id CHAR(24),ativa INT,status VARCHAR(50))')
            for sql in (cls.root/'sql/2026-11-06_deadline_job_snapshots.sql').read_text(encoding='utf-8').split(';'):
                if sql.strip():c.execute(sql)
            c.execute('CREATE TABLE deadline_workers (versao VARCHAR(10),status VARCHAR(20),ultimo_heartbeat DATETIME,detalhes TEXT)')

    @classmethod
    def tearDownClass(cls):
        cls.db.close()
        if cls.schema.startswith('flow_ef_test_') and len(cls.schema)==29:
            with cls.admin.cursor() as c:c.execute('DROP DATABASE `'+cls.schema+'`')
        cls.admin.close()

    def setUp(self):
        with self.db.cursor() as c:
            c.execute('UPDATE render_ef_solicitacoes SET snapshot_id=NULL'); c.execute('DELETE FROM render_job_snapshot_capturas'); c.execute('DELETE FROM render_job_snapshots'); c.execute('DELETE FROM render_job_snapshot_eventos'); c.execute('DELETE FROM render_tentativas')
            c.execute('DELETE FROM deadline_workers')
            c.execute("INSERT INTO deadline_workers VALUES ('2.4','ATIVO',NOW(),'{\"snapshot_flow_enabled\":true,\"ef_submit_enabled\":false}')")
            c.execute('DELETE e FROM render_ef_eventos e JOIN render_ef_solicitacoes r ON r.id=e.solicitacao_id WHERE r.imagem_id=10')
            c.execute('DELETE FROM render_ef_solicitacoes WHERE imagem_id=10')
            c.execute('DELETE FROM funcao_imagem')
            c.execute('INSERT INTO funcao_imagem VALUES (10,10,7,4),(20,2,9,4)')
            c.execute("INSERT INTO render_tentativas (render_id,imagem_id,status_id,numero_tentativa,deadline_job_id,ativa,status,motivo_encerramento) VALUES (10,10,3,1,%s,0,'ENCERRADA','APROVADA_NO_FLOW')",('d'*24,))
            c.execute('UPDATE render_alta SET deadline_job_id=NULL WHERE idrender_alta=10')

    def inspection_db(self):
        from contextlib import contextmanager
        import pymysql
        from pymysql.cursors import DictCursor
        config={**self.config,'autocommit':False,'cursorclass':DictCursor}
        class IsolatedDB:
            @contextmanager
            def transaction(self):
                conn=pymysql.connect(**config)
                try:
                    with conn.cursor() as cursor:yield cursor
                    conn.commit()
                except Exception:
                    conn.rollback();raise
                finally:conn.close()
        return IsolatedDB()

    def test_snapshot_immutable_idempotent_multiple_jobs_and_approved_selection(self):
        from deadline_snapshots import SnapshotStore,approved_attempt,decode_snapshot,digest,normalize
        from test_snapshots import fixture
        store=SnapshotStore(self.inspection_db())
        self.assertTrue(store.ready())
        with self.db.cursor() as c:
            c.execute('SELECT id FROM render_tentativas WHERE render_id=10')
            first=c.fetchone()[0]
        def result(attempt,job):
            content=fixture();content.update(attempt_id=attempt,job_id=job)
            return {'content':content,'sha256':digest(content),'normalized':normalize(content['job_info'],content['plugin_info'],content['metadata']),'origin':'DEADLINE_ATIVO','state':'CAPTURADO'}
        attempt={'id':first,'imagem_id':10,'deadline_job_id':'d'*24}
        store.queue(attempt);reserved=store.reserve();self.assertEqual(reserved,attempt)
        saved=result(first,'d'*24);store.commit(attempt,saved)
        original=store.get('d'*24)
        saved['content']['plugin_info']['Camera']='CHANGED';saved['sha256']=digest(saved['content'])
        store.commit(attempt,saved)
        self.assertEqual(store.get('d'*24)['conteudo_sha256'],original['conteudo_sha256'])
        self.assertEqual(decode_snapshot(store.get('d'*24))['plugin_info']['Camera'],'Camera_aprovada')
        from deadline_nas_submission import snapshot_plan
        historical=store.get('d'*24)
        # Acceptance: original Deadline job gone; DB snapshot alone reconstructs.
        built=snapshot_plan(decode_snapshot(historical),historical,
            {'id':100,'imagem_id':10,'submissao_uuid':str(uuid.uuid4()),'arquivo_confirmado_por':7,'arquivo_confirmado_em':'2026-11-06'},
            {'imagem_id':10,'imagem_nome':'QA','nomenclatura':'QA10'},r'\\192.168.0.250\clientes\2026\QA10\final.max',
            {'size':10,'mtime_ns':1,'sha256':'a'*64},{'edge':5000})
        self.assertEqual((built['width'],built['height']),(5000,2813))
        self.assertEqual(built['source_info']['Camera'],'Camera_aprovada')
        with self.db.cursor() as c:
            c.execute("INSERT INTO render_tentativas (render_id,imagem_id,status_id,numero_tentativa,deadline_job_id,ativa,status,motivo_encerramento) VALUES (10,10,3,2,%s,0,'ENCERRADA','APROVADA_NO_FLOW')",('e'*24,))
            second=c.lastrowid
            c.execute('UPDATE render_alta SET deadline_job_id=%s WHERE idrender_alta=10',('e'*24,))
        other={'id':second,'imagem_id':10,'deadline_job_id':'e'*24};store.queue(other);store.commit(other,result(second,'e'*24))
        selected=approved_attempt(self.inspection_db(),{'render_origem_id':10,'imagem_id':10})
        self.assertEqual(selected['id'],second)
        # No Deadline calls: both snapshots remain available after source purge.
        self.assertEqual(decode_snapshot(store.get('d'*24))['attempt_id'],first)
        self.assertEqual(decode_snapshot(store.get('e'*24))['attempt_id'],second)
        with self.db.cursor() as c:c.execute('UPDATE render_alta SET deadline_job_id=NULL WHERE idrender_alta=10')
        with self.assertRaisesRegex(RuntimeError,'única'):approved_attempt(self.inspection_db(),{'render_origem_id':10,'imagem_id':10})

    def test_snapshot_capture_recovers_stale_reservation_after_restart(self):
        from deadline_snapshots import SnapshotStore
        with self.db.cursor() as c:
            c.execute('SELECT id FROM render_tentativas WHERE render_id=10')
            attempt={'id':c.fetchone()[0],'imagem_id':10,'deadline_job_id':'d'*24}
        store=SnapshotStore(self.inspection_db());store.queue(attempt)
        self.assertEqual(store.reserve(),attempt)
        with self.db.cursor() as c:c.execute("UPDATE render_job_snapshot_capturas SET iniciado_em=NOW()-INTERVAL 16 MINUTE WHERE deadline_job_id=%s",('d'*24,))
        restarted=SnapshotStore(self.inspection_db())
        self.assertEqual(restarted.reserve(),attempt)
        restarted.fail(attempt,'interrupção de rede')
        self.assertIsNone(store.get('d'*24))

    def test_api_file_confirmation_cannot_be_omitted(self):
        denied=self.api({'action':'prepare','render_id':10,'origin':'NAS','file_id':101,'client_key':str(uuid.uuid4())})
        self.assertEqual(denied['status'],'erro');self.assertIn('Confirme',denied['message'])

    def test_K_migration_preserves_all_legacy_rows_and_job_ids(self):
        with self.db.cursor() as c:
            c.execute('SELECT id,status,origem_job_id,novo_job_id,submissao_uuid FROM render_ef_solicitacoes WHERE id IN (1,2) ORDER BY id')
            rows=c.fetchall()
        self.assertEqual(len(rows),2)
        self.assertEqual(rows[0][2:4],('a'*24,'b'*24))
        self.assertEqual(rows[1][1:4],('ERRO','c'*24,None))
        self.assertTrue(all(row[4] for row in rows))

    def test_E_concurrent_requests_resolve_to_single_operation(self):
        import pymysql
        barrier=threading.Barrier(2)
        def create():
            db=pymysql.connect(**{**self.config,'autocommit':False})
            try:
                barrier.wait(timeout=10)
                with db.cursor() as c:
                    c.execute('SELECT status_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra=10 FOR UPDATE')
                    c.execute('SELECT id FROM render_ef_solicitacoes WHERE imagem_id=10 AND operacao_ativa_imagem IS NOT NULL')
                    active=c.fetchone()
                    if active:result=active[0]
                    else:
                        c.execute("INSERT INTO render_ef_solicitacoes (render_origem_id,imagem_id,criado_por,origem,submissao_uuid,mensagem) VALUES (10,10,7,'NAS',%s,'test')",(str(uuid.uuid4()),))
                        result=c.lastrowid
                db.commit();return result
            finally:db.close()
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            results=list(pool.map(lambda _:create(),range(2)))
        self.assertEqual(results[0],results[1])
        with self.db.cursor() as c:
            c.execute('SELECT COUNT(*) FROM render_ef_solicitacoes WHERE imagem_id=10')
            self.assertEqual(c.fetchone()[0],1)

    def test_E_uncertain_submission_keeps_lock_and_history(self):
        import pymysql
        with self.db.cursor() as c:
            c.execute("INSERT INTO render_ef_solicitacoes (render_origem_id,imagem_id,criado_por,origem,mensagem,status,submissao_iniciada_em) VALUES (10,10,7,'NAS','uncertain','ERRO',NOW())")
            with self.assertRaises(pymysql.IntegrityError):c.execute("INSERT INTO render_ef_solicitacoes (render_origem_id,imagem_id,criado_por,origem,mensagem) VALUES (10,10,7,'NAS','duplicate')")
            c.execute("UPDATE render_ef_solicitacoes SET status='CONCLUIDO' WHERE imagem_id=10")
            c.execute("INSERT INTO render_ef_solicitacoes (render_origem_id,imagem_id,criado_por,origem,mensagem) VALUES (10,10,7,'NAS','next attempt')")
            c.execute('SELECT COUNT(*) FROM render_ef_solicitacoes WHERE imagem_id=10')
            self.assertEqual(c.fetchone()[0],2)

    def test_L_backend_authorization_scopes_image_and_work(self):
        with self.db.cursor() as c:
            c.execute('CREATE TABLE IF NOT EXISTS usuario_cargo (usuario_id INT,cargo_id INT)')
            c.execute('CREATE TABLE IF NOT EXISTS cargo (id INT,nome VARCHAR(100))')
            c.execute('CREATE TABLE IF NOT EXISTS funcao_imagem (idfuncao_imagem INT,imagem_id INT,colaborador_id INT,funcao_id INT)')
            c.execute('DELETE FROM funcao_imagem')
            c.execute('INSERT INTO funcao_imagem VALUES (1,1,8,4),(2,2,9,4)')
        env=dict(os.environ,DB_HOST=self.config['host'],DB_PORT=str(self.config['port']),DB_USERNAME=self.config['user'],DB_PASSWORD=self.config['password'],DB_DATABASE=self.schema)
        result=subprocess.run([r'C:\xampp\php\php.exe',str(Path(__file__).with_name('ef_permissions.php'))],env=env,capture_output=True,check=True)
        self.assertEqual(result.stdout.strip(),b'permissions: OK')

    def api(self, data):
        env=dict(os.environ,DB_HOST=self.config['host'],DB_PORT=str(self.config['port']),DB_USERNAME=self.config['user'],DB_PASSWORD=self.config['password'],DB_DATABASE=self.schema)
        response=subprocess.run([r'C:\xampp\php\php.exe',str(Path(__file__).with_name('ef_api.php'))],input=json.dumps(data).encode(),env=env,capture_output=True,check=True)
        return json.loads(response.stdout)

    def test_B_api_NAS_without_job_and_IMG_classification(self):
        key=str(uuid.uuid4())
        body={'action':'prepare','render_id':10,'origin':'NAS','file_id':101,'client_key':key,'file_confirmed':'1'}
        first=self.api(body);second=self.api(body)
        self.assertEqual(first['status'],'sucesso',first)
        self.assertEqual(first['request']['status'],'SNAPSHOT_PREPARAR')
        self.assertEqual(first['request']['origem_job_id'],'d'*24)
        self.assertEqual(first['request']['id'],second['request']['id'])
        self.assertEqual(first['files'][0]['id'],101)

    def test_L_M_API_rejects_foreign_image_and_arbitrary_paths(self):
        for extra in [{'actor':9,'file_id':101},{'actor':7,'file_id':102},{'actor':7,'file_id':0,'path':'C:\\attack.max'}]:
            result=self.api({'action':'prepare','render_id':10,'origin':'NAS','client_key':str(uuid.uuid4()),**extra})
            self.assertEqual(result['status'],'erro',result)

    def test_API_confirmation_gate_rejects_disabled_worker(self):
        result=self.api({'action':'prepare','render_id':10,'origin':'NAS','file_id':101,'client_key':str(uuid.uuid4()),'file_confirmed':'1'})
        request=result['request']
        with self.db.cursor() as c:c.execute("UPDATE render_ef_solicitacoes SET status='PRONTO',plano_json='{}' WHERE id=%s",(request['id'],))
        import hashlib
        response=self.api({'action':'confirm','render_id':10,'request_id':request['id'],'plan_hash':hashlib.sha256(b'{}').hexdigest()})
        self.assertEqual(response['status'],'erro')
        with self.db.cursor() as c:
            c.execute('SELECT status FROM render_ef_solicitacoes WHERE id=%s',(request['id'],))
            self.assertEqual(c.fetchone()[0],'PRONTO')

    def test_API_render_gate_independent_of_delete_dry_run(self):
        result=self.api({'action':'prepare','render_id':10,'origin':'NAS','file_id':101,'client_key':str(uuid.uuid4()),'file_confirmed':'1'})
        request=result['request']
        with self.db.cursor() as c:
            c.execute("UPDATE render_ef_solicitacoes SET status='PRONTO',plano_json='{}' WHERE id=%s",(request['id'],))
            c.execute("UPDATE deadline_workers SET status='DRY_RUN',detalhes='{\"snapshot_flow_enabled\":true,\"ef_submit_enabled\":true}'")
        import hashlib
        response=self.api({'action':'confirm','render_id':10,'request_id':request['id'],'plan_hash':hashlib.sha256(b'{}').hexdigest()})
        self.assertEqual(response['status'],'sucesso',response)
        self.assertEqual(response['request']['status'],'EXECUTAR_V2')
        # No worker is run; this only enqueues an isolated database fixture.

    def test_E_actual_endpoint_simultaneous_preparations_reuse_request(self):
        def prepare(_):return self.api({'action':'prepare','render_id':10,'origin':'NAS','file_id':101,'client_key':str(uuid.uuid4()),'file_confirmed':'1'})
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:responses=list(pool.map(prepare,range(2)))
        self.assertTrue(all(r['status']=='sucesso' for r in responses),responses)
        self.assertEqual(responses[0]['request']['id'],responses[1]['request']['id'])


if __name__=='__main__':unittest.main()
