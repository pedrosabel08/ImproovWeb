"""Opt-in isolated MariaDB tests; never connect to the Flow production schema."""
import concurrent.futures
from contextlib import contextmanager
import json
import os
from pathlib import Path
import sys
from types import SimpleNamespace
import unittest
from unittest.mock import Mock, patch
import uuid

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from deadline_client import CommandResult
from deadline_ef_recovery import reserve
from test_snapshots import plan


@unittest.skipUnless(os.getenv('FLOW_RECOVERY_TEST_PORT'), 'isolated MariaDB not configured')
class RecoveryTransactions(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        import pymysql
        cls.pymysql = pymysql
        cls.config = dict(host='127.0.0.1', port=int(os.environ['FLOW_RECOVERY_TEST_PORT']), user='root', password='', charset='utf8mb4', autocommit=True)
        cls.schema = 'flow_recovery_test_' + uuid.uuid4().hex[:12]
        cls.admin = pymysql.connect(**cls.config)
        with cls.admin.cursor() as c:
            c.execute('CREATE DATABASE `' + cls.schema + '`')
        cls.config['database'] = cls.schema
        cls.connection = pymysql.connect(**cls.config)
        with cls.connection.cursor() as c:
            c.execute('CREATE TABLE imagens_cliente_obra (idimagens_cliente_obra INT PRIMARY KEY,status_id INT) ENGINE=InnoDB')
            c.execute('CREATE TABLE render_tentativas (id INT AUTO_INCREMENT PRIMARY KEY,render_id INT,imagem_id INT,status_id INT,numero_tentativa INT,deadline_job_id VARCHAR(64),deadline_job_name VARCHAR(255),ativa INT,status VARCHAR(30),encerrado_em DATETIME,motivo_encerramento VARCHAR(255),vinculado_em DATETIME) ENGINE=InnoDB')
            c.execute('CREATE TABLE render_alta (idrender_alta INT PRIMARY KEY,imagem_id INT,status_id INT,responsavel_id INT,status VARCHAR(50),deadline_job_id VARCHAR(64),job_folder TEXT,excluido_em DATETIME,previa_jpg TEXT,has_error INT,errors TEXT) ENGINE=InnoDB')
            c.execute('CREATE TABLE render_tentativa_eventos (id INT AUTO_INCREMENT PRIMARY KEY,tentativa_id INT,tipo VARCHAR(80),chave VARCHAR(120),dados_json LONGTEXT) ENGINE=InnoDB')
            c.execute("""CREATE TABLE render_ef_solicitacoes (id INT AUTO_INCREMENT PRIMARY KEY,render_origem_id INT,imagem_id INT,criado_por INT,origem_job_id VARCHAR(64),origem VARCHAR(16),arquivo_log_id INT,submissao_uuid CHAR(36) UNIQUE,status VARCHAR(30),etapa VARCHAR(50),mensagem TEXT,plano_json LONGTEXT,configuracao_json LONGTEXT,snapshot_id INT,origem_tentativa_id INT,arquivo_confirmado_por INT,arquivo_confirmado_em DATETIME,confirmado_por INT,confirmado_em DATETIME,novo_job_id VARCHAR(64),render_ef_id INT,worker_id VARCHAR(100),submissao_iniciada_em DATETIME, operacao_ativa_imagem INT GENERATED ALWAYS AS (CASE WHEN status NOT IN ('CONCLUIDO','CANCELADO','ERRO') OR (status='ERRO' AND (novo_job_id IS NOT NULL OR submissao_iniciada_em IS NOT NULL)) THEN imagem_id ELSE NULL END) STORED UNIQUE) ENGINE=InnoDB""")
            c.execute('CREATE TABLE render_ef_eventos (id INT AUTO_INCREMENT PRIMARY KEY,solicitacao_id INT,etapa VARCHAR(50),status VARCHAR(30),mensagem TEXT,origem VARCHAR(16)) ENGINE=InnoDB')

    @classmethod
    def tearDownClass(cls):
        cls.connection.close()
        with cls.admin.cursor() as c:
            c.execute('DROP DATABASE `' + cls.schema + '`')
        cls.admin.close()

    def setUp(self):
        self.old = plan()
        self.job_id = 'b' * 24
        with self.connection.cursor() as c:
            c.execute('DELETE FROM render_ef_eventos'); c.execute('DELETE FROM render_tentativa_eventos'); c.execute('DELETE FROM render_ef_solicitacoes'); c.execute('DELETE FROM render_tentativas'); c.execute('DELETE FROM render_alta'); c.execute('DELETE FROM imagens_cliente_obra')
            c.execute('INSERT INTO imagens_cliente_obra VALUES (10,9)')
            c.execute("INSERT INTO render_alta (idrender_alta,imagem_id,status_id,status,deadline_job_id) VALUES (99,10,9,'Erro',%s)", (self.job_id,))
            c.execute("INSERT INTO render_tentativas (id,render_id,imagem_id,status_id,numero_tentativa,deadline_job_id,ativa,status) VALUES (77,99,10,9,1,%s,1,'ERRO')", (self.job_id,))
            c.execute("""INSERT INTO render_ef_solicitacoes (id,render_origem_id,imagem_id,criado_por,origem_job_id,origem,arquivo_log_id,submissao_uuid,status,etapa,mensagem,plano_json,configuracao_json,snapshot_id,origem_tentativa_id,arquivo_confirmado_por,arquivo_confirmado_em,confirmado_por,confirmado_em,novo_job_id,render_ef_id)
                         VALUES (8,10,10,7,%s,'NAS',101,%s,'CONCLUIDO','CONCLUIDO','old',%s,'{}',1,5,7,NOW(),7,NOW(),%s,99)""", ('a'*24, str(uuid.uuid4()), json.dumps(self.old), self.job_id))
        config = self.config
        class DB:
            @contextmanager
            def transaction(self):
                conn = self.outer.pymysql.connect(**{**config, 'autocommit': False, 'cursorclass': self.outer.pymysql.cursors.DictCursor})
                try:
                    with conn.cursor() as c: yield c
                    conn.commit()
                except Exception:
                    conn.rollback(); raise
                finally: conn.close()
        db = DB(); db.outer = self
        client = Mock(); client.get_job.return_value = (CommandResult(True, ''), {'Status':'Suspended','RenderingChunks':'0'})
        self.worker = SimpleNamespace(database=db, client=client)
        self.snapshot = {'id':1,'tentativa_id':5,'conteudo_sha256':self.old['snapshot_sha256']}

    def call(self):
        return reserve(self.worker, 8, 77, self.job_id)

    def run_with_snapshot(self, action):
        with patch('deadline_ef_recovery.approved_attempt', return_value={'id':5}), patch('deadline_ef_recovery.SnapshotStore') as store, patch('deadline_ef_recovery.decode_snapshot'), patch('deadline_ef_recovery.assert_scene_access', return_value=self.old['scene_fingerprint']):
            store.return_value.get.return_value = self.snapshot
            return action()

    def test_concurrent_and_repeated_reservation(self):
        def run():
            with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:
                return list(pool.map(lambda _: self.call(), range(6)))
        results = self.run_with_snapshot(run)
        self.assertEqual(len(set(results)), 1)
        self.assertEqual(self.run_with_snapshot(self.call), results[0])
        with self.connection.cursor() as c:
            c.execute('SELECT COUNT(*) FROM render_ef_solicitacoes WHERE imagem_id=10 AND operacao_ativa_imagem IS NOT NULL')
            self.assertEqual(c.fetchone()[0], 1)
            c.execute('SELECT status,ativa FROM render_tentativas WHERE id=77')
            self.assertEqual(c.fetchone(), ('ERRO', 1))

    def test_rendering_job_is_rejected(self):
        self.worker.client.get_job.return_value = (CommandResult(True,''), {'Status':'Rendering','RenderingChunks':'1'})
        with self.assertRaisesRegex(RuntimeError, 'suspenso'):
            self.run_with_snapshot(self.call)

    def test_pre_submit_failure_reuses_request_and_uuid(self):
        first = self.run_with_snapshot(self.call)
        with self.connection.cursor() as c:
            c.execute("UPDATE render_ef_solicitacoes SET status='ERRO' WHERE id=%s", (first[0],))
        self.assertEqual(self.run_with_snapshot(self.call), first)
        with self.connection.cursor() as c:
            c.execute('SELECT status FROM render_ef_solicitacoes WHERE id=%s', (first[0],))
            self.assertEqual(c.fetchone()[0], 'EXECUTAR_V2')

    def test_preserves_snapshot_outputs_and_history(self):
        request_id, token = self.run_with_snapshot(self.call)
        with self.connection.cursor() as c:
            c.execute('SELECT plano_json,snapshot_id FROM render_ef_solicitacoes WHERE id=%s', (request_id,))
            content, snapshot_id = c.fetchone()
        new = json.loads(content)
        self.assertEqual(snapshot_id, 1)
        self.assertEqual(new['scene_fingerprint'], self.old['scene_fingerprint'])
        self.assertEqual(new['recovery']['previous_attempt_id'], 77)
        self.assertNotIn('SceneFile', new['source_info'])
        import ntpath
        for k in new['outputs']:
            self.assertEqual(ntpath.basename(new['outputs'][k]), ntpath.basename(self.old['outputs'][k]))
            self.assertIn(token, ntpath.dirname(new['outputs'][k]))

    def test_binding_replaces_active_attempt_atomically_and_is_idempotent(self):
        from deadline_repository import DeadlineRepository
        request_id, token = self.run_with_snapshot(self.call)
        with self.connection.cursor() as c:
            c.execute("UPDATE render_ef_solicitacoes SET status='PROCESSANDO',worker_id='test',novo_job_id=%s WHERE id=%s", ('c'*24, request_id))
            c.execute('SELECT plano_json FROM render_ef_solicitacoes WHERE id=%s', (request_id,))
            built = json.loads(c.fetchone()[0])
        req = {'id':request_id,'imagem_id':10,'render_origem_id':10,'novo_job_id':'c'*24,'worker_id':'test','origem':'NAS','submissao_uuid':token}
        repo = DeadlineRepository(self.worker.database, Mock())
        attempt = repo.bind_ef_submission(req, {'status_id':9}, built)
        self.assertEqual(repo.bind_ef_submission(req, {'status_id':9}, built)['id'], attempt['id'])
        with self.connection.cursor() as c:
            c.execute('SELECT id,status,ativa,deadline_job_id FROM render_tentativas ORDER BY id')
            rows = c.fetchall()
        self.assertEqual(rows[0], (77, 'ENCERRADA', 0, 'b'*24))
        self.assertEqual(rows[1][1:], ('VINCULADA', 1, 'c'*24))

    def test_binding_failure_rolls_back_previous_active_attempt(self):
        from deadline_repository import DeadlineRepository
        request_id, token = self.run_with_snapshot(self.call)
        with self.connection.cursor() as c:
            c.execute("UPDATE render_ef_solicitacoes SET status='PROCESSANDO',worker_id='test',novo_job_id=%s WHERE id=%s", ('c'*24, request_id))
            c.execute('SELECT plano_json FROM render_ef_solicitacoes WHERE id=%s', (request_id,))
            built = json.loads(c.fetchone()[0])
        req = {'id':request_id,'imagem_id':10,'render_origem_id':10,'novo_job_id':'c'*24,'worker_id':'test','origem':'NAS','submissao_uuid':token}
        repo = DeadlineRepository(self.worker.database, Mock())
        with patch.object(repo, '_insert_event_dict', side_effect=RuntimeError('simulated failure')):
            with self.assertRaisesRegex(RuntimeError, 'simulated failure'):
                repo.bind_ef_submission(req, {'status_id':9}, built)
        with self.connection.cursor() as c:
            c.execute('SELECT id,status,ativa,deadline_job_id FROM render_tentativas')
            self.assertEqual(c.fetchall(), ((77,'ERRO',1,'b'*24),))
