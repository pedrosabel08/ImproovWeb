"""Immutable job snapshots. No scene opening, no Max process, no job submission."""
from __future__ import annotations
import hashlib
import base64
import json
import ntpath
import os
import re
import shutil
import tempfile
import uuid
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

from deadline_domain import valid_job_id
from deadline_scene import fingerprint


def digest(value):
    return hashlib.sha256(json.dumps(value,ensure_ascii=False,sort_keys=True,separators=(',',':'),allow_nan=False).encode('utf-8')).hexdigest()


def capture_enabled():
    return os.getenv('DEADLINE_SNAPSHOT_CAPTURE_ENABLED','0') == '1'


def flow_enabled():
    return os.getenv('DEADLINE_EF_SNAPSHOT_ENABLED','0') == '1'


def private_root():
    root=os.getenv('DEADLINE_SNAPSHOT_ROOT','')
    if not root.startswith('\\\\') or ':' in root or any(p in {'.','..'} for p in root.split('\\')):
        raise RuntimeError('Configure DEADLINE_SNAPSHOT_ROOT em compartilhamento UNC privado.')
    public=os.path.normcase(os.path.realpath(Path(__file__).parent.parent))
    resolved=os.path.normcase(os.path.realpath(root))
    if resolved == public or resolved.startswith(public+os.sep):
        raise RuntimeError('Snapshots auxiliares não podem ficar na árvore pública.')
    return Path(root)


def normalize(job,plugin,meta):
    keys={k.lower():k for k in plugin}
    result={key:job.get(key) for key in ('Name','Plugin','Pool','Group','Priority','Frames','ChunkSize')}
    result.update(camera=plugin.get('Camera'),renderer=plugin.get('SubmittedRendererName',plugin.get('Renderer')),max_version=plugin.get('Version'),
                  scene=plugin.get('SceneFile') or meta.get('primary_scene'),missing=[])
    for name in ('RenderWidth','RenderHeight'):
        value=plugin.get(keys.get(name.lower(),''))
        try:result[name]=int(value) if value is not None and int(value)>0 else None
        except (ValueError,TypeError):result[name]=None
        if result[name] is None:result['missing'].append(name)
    return result


def export_snapshot(client,attempt,root=None):
    """Runs in an IO thread. Client commands export configuration only."""
    from deadline_render_ef import read_info
    from deadline_inspection import is_inspection
    jobid=attempt['deadline_job_id']
    if not valid_job_id(jobid):raise ValueError('Job ID inválido para captura.')
    with tempfile.TemporaryDirectory(prefix='flow_snapshot_export_') as temp:
        paths=[Path(temp)/n for n in ('job.job','plugin.job','meta.json')]
        command=client.run(['-ExecuteScriptNoGui',str(Path(__file__).with_name('deadline_snapshot_export.py')),jobid,*map(str,paths)])
        if not command.success or not all(p.is_file() for p in paths):
            raise RuntimeError('Exportação original indisponível ou incompleta; nenhuma inspeção será executada.')
        job,plugin=read_info(paths[0]),read_info(paths[1])
        meta=json.loads(paths[2].read_text(encoding='utf-8'))
        if meta.get('job_id')!=jobid or job.get('Plugin','').lower()!='3dsmax' or is_inspection({'ExtraInfoDictionary':','.join(v for k,v in job.items() if k.startswith('ExtraInfoKeyValue'))}):
            raise RuntimeError('O job exportado não é um render 3ds Max elegível.')
        # Recoverable data is preserved verbatim too, not just normalized fields.
        response,raw=client.get_job(jobid)
        if not response.success:
            response=client.run(['-GetDeletedJob',jobid,'True'])
            from deadline_client import parse_output
            raw=parse_output(response.output) if response.success else {}
        # This installed SDK export omits ChunkSize for some jobs. GetJob exposes
        # it explicitly; record that provenance instead of inventing a default.
        recovered={}
        for name in ('Pool','Group','Priority','Frames','ChunkSize'):
            value=raw.get(name,raw.get('Job'+name))
            if name not in job and isinstance(value,(str,int)) and str(value)!='':
                job[name]=str(value);recovered[name]='GetJob/GetDeletedJob'
        primary=str(raw.get('PluginDataFileName',''))
        external=plugin.get('SceneFile','').strip()
        if external and not external.lower().endswith('.max'):
            raise RuntimeError('Cena externa do job não é um arquivo .max identificado.')
        if not external and (not primary.lower().endswith('.max') or ntpath.basename(primary)!=primary):
            raise RuntimeError('Não foi possível identificar o .max auxiliar principal do job.')
        meta['primary_scene']=external or primary
        archive=[]
        names=meta.get('auxiliary_names',[])
        if not external and primary not in names:raise RuntimeError('Cena principal não consta da lista de auxiliares.')
        needed=[n for n in names if n!=primary]
        if any(not isinstance(n,str) or not n or n in {'.','..'} or ntpath.basename(n)!=n or re.search(r'[<>:"/\\|?*\x00-\x1f]',n) for n in names):
            raise ValueError('Nome de auxiliar inválido no Deadline.')
        if len({n.casefold() for n in names})!=len(names):raise ValueError('Auxiliares duplicados no Deadline.')
        if needed:
            root=root or private_root()
            stage=Path(root)/(jobid+'_'+uuid.uuid4().hex)
            stage.mkdir(parents=False,exist_ok=False)
            for name in needed:
                source=Path(meta['auxiliary_path'])/name
                before=fingerprint(str(source));destination=stage/name
                shutil.copyfile(source,destination)
                copied=fingerprint(str(destination))
                if before!=fingerprint(str(source)) or copied['sha256']!=before['sha256']:
                    raise RuntimeError('Auxiliar mudou durante a preservação: '+name)
                archive.append({'name':name,'path':str(destination),'sha256':copied['sha256'],'size':copied['size'],'original_path':str(source)})
        normalized=normalize(job,plugin,meta)
        content={'format':1,'job_id':jobid,'attempt_id':attempt['id'],'image_id':attempt['imagem_id'],
                 'job_info':job,'plugin_info':plugin,'raw_job_info':paths[0].read_text(encoding='utf-8-sig',errors='replace'),
                 'raw_plugin_info':paths[1].read_text(encoding='utf-8-sig',errors='replace'),
                 'job_file_base64':base64.b64encode(paths[0].read_bytes()).decode('ascii'),
                 'plugin_file_base64':base64.b64encode(paths[1].read_bytes()).decode('ascii'),
                 'deadline_data':raw,'deadline_raw':response.output,'metadata':meta,'recovered_fields':recovered,'auxiliaries':archive}
        return {'content':content,'sha256':digest(content),'normalized':normalized,'origin':meta['origin'],
                'state':'CAPTURADO' if all(job.get(k) not in (None,'') for k in ('Plugin','Pool','Group','Priority','Frames','ChunkSize')) else 'INCOMPLETO'}


def decode_snapshot(row):
    content=json.loads(row['conteudo_json'])
    if row['formato']!=1 or digest(content)!=row['conteudo_sha256']:
        raise RuntimeError('Integridade ou versão do snapshot inválida.')
    if content['job_id']!=row['deadline_job_id'] or content['attempt_id']!=row['tentativa_id'] or content['image_id']!=row['imagem_id']:
        raise RuntimeError('Vínculo do snapshot divergente.')
    return content


def approved_attempt(db,request):
    with db.transaction() as c:
        c.execute("""SELECT rt.* FROM render_tentativas rt JOIN render_alta r ON r.idrender_alta=rt.render_id
            WHERE rt.render_id=%s AND rt.imagem_id=%s AND r.status='Aprovado' AND r.excluido_em IS NULL
            AND rt.deadline_job_id IS NOT NULL AND (rt.status='APROVADA' OR rt.motivo_encerramento='APROVADA_NO_FLOW')
            AND (r.deadline_job_id IS NULL OR r.deadline_job_id='' OR r.deadline_job_id=rt.deadline_job_id)""",
                  (request['render_origem_id'],request['imagem_id']))
        rows=list(c.fetchall())
    if len(rows)!=1:
        raise RuntimeError('Não há uma única tentativa com Job ID comprovadamente associada a este render aprovado.')
    return rows[0]


class SnapshotStore:
    def __init__(self,db):self.db=db

    def ready(self):
        with self.db.transaction() as c:
            c.execute("SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('render_job_snapshots','render_job_snapshot_capturas','render_job_snapshot_eventos')")
            return c.fetchone()['total']==3

    def queue(self,attempt,priority=0):
        with self.db.transaction() as c:
            c.execute('INSERT INTO render_job_snapshot_capturas (deadline_job_id,tentativa_id,imagem_id,prioridade) VALUES (%s,%s,%s,%s) ON DUPLICATE KEY UPDATE prioridade=GREATEST(prioridade,VALUES(prioridade))',
                      (attempt['deadline_job_id'],attempt['id'],attempt['imagem_id'],priority))

    def get(self,jobid):
        with self.db.transaction() as c:
            c.execute('SELECT * FROM render_job_snapshots WHERE deadline_job_id=%s',(jobid,));return c.fetchone()

    def discover(self):
        with self.db.transaction() as c:
            # Established bindings only. Never infer a snapshot/image from a name.
            c.execute("""INSERT IGNORE INTO render_job_snapshot_capturas (deadline_job_id,tentativa_id,imagem_id)
                SELECT rt.deadline_job_id,rt.id,rt.imagem_id FROM render_tentativas rt
                LEFT JOIN render_job_snapshots s ON s.deadline_job_id=rt.deadline_job_id
                WHERE rt.deadline_job_id REGEXP '^[a-fA-F0-9]{24}$' AND s.id IS NULL
                ORDER BY rt.ativa DESC,rt.id DESC""")

    def reserve(self):
        with self.db.transaction() as c:
            c.execute("UPDATE render_job_snapshot_capturas SET estado='PENDENTE',disponivel_em=NOW() WHERE estado='CAPTURANDO' AND iniciado_em<NOW()-INTERVAL 15 MINUTE")
            c.execute("SELECT * FROM render_job_snapshot_capturas WHERE estado IN ('PENDENTE','ERRO') AND tentativas<5 AND disponivel_em<=NOW() ORDER BY prioridade DESC,atualizado_em DESC LIMIT 1 FOR UPDATE")
            row=c.fetchone()
            if row:
                c.execute("UPDATE render_job_snapshot_capturas SET estado='CAPTURANDO',iniciado_em=NOW(),tentativas=tentativas+1 WHERE deadline_job_id=%s",(row['deadline_job_id'],))
                return {'id':row['tentativa_id'],'imagem_id':row['imagem_id'],'deadline_job_id':row['deadline_job_id']}

    def commit(self,attempt,result):
        with self.db.transaction() as c:
            c.execute('SELECT deadline_job_id,imagem_id FROM render_tentativas WHERE id=%s FOR UPDATE',(attempt['id'],))
            current=c.fetchone()
            if not current or current['deadline_job_id']!=attempt['deadline_job_id'] or current['imagem_id']!=attempt['imagem_id']:
                raise RuntimeError('Vínculo da tentativa mudou durante a captura.')
            c.execute("""INSERT IGNORE INTO render_job_snapshots (deadline_job_id,tentativa_id,imagem_id,origem,estado,conteudo_json,conteudo_sha256,normalizado_json)
                VALUES (%s,%s,%s,%s,%s,%s,%s,%s)""",(attempt['deadline_job_id'],attempt['id'],attempt['imagem_id'],result['origin'],result['state'],json.dumps(result['content'],ensure_ascii=False),result['sha256'],json.dumps(result['normalized'],ensure_ascii=False)))
            c.execute("UPDATE render_job_snapshot_capturas SET estado='CONCLUIDO',ultimo_erro=NULL WHERE deadline_job_id=%s",(attempt['deadline_job_id'],))
            c.execute('SELECT tentativa_id,imagem_id FROM render_job_snapshots WHERE deadline_job_id=%s',(attempt['deadline_job_id'],))
            saved=c.fetchone()
            if not saved or saved['tentativa_id']!=attempt['id'] or saved['imagem_id']!=attempt['imagem_id']:
                raise RuntimeError('Snapshot já pertence a outro vínculo. Nenhum histórico foi sobrescrito.')
            c.execute("INSERT INTO render_job_snapshot_eventos (deadline_job_id,tentativa_id,estado,mensagem) VALUES (%s,%s,'CAPTURADO','Configuração histórica preservada; sem executar Max.')",(attempt['deadline_job_id'],attempt['id']))

    def fail(self,attempt,error):
        with self.db.transaction() as c:
            c.execute("UPDATE render_job_snapshot_capturas SET estado='ERRO',ultimo_erro=%s,disponivel_em=NOW()+INTERVAL 60 SECOND WHERE deadline_job_id=%s",(str(error)[:2000],attempt['deadline_job_id']))
            c.execute("INSERT INTO render_job_snapshot_eventos (deadline_job_id,tentativa_id,estado,mensagem) VALUES (%s,%s,'ERRO',%s)",(attempt['deadline_job_id'],attempt['id'],str(error)[:2000]))


class SnapshotCapture:
    def __init__(self,worker):
        self.worker=worker;self.store=SnapshotStore(worker.database);self.pool=None;self.pending=None

    def tick(self):
        if self.pending:
            attempt,future=self.pending
            if not future.done():return
            try:self.store.commit(attempt,future.result())
            except Exception as exc:
                self.worker.logger.exception('Snapshot capture failed')
                self.store.fail(attempt,exc)
            finally:self.pending=None
        if not capture_enabled() or not self.store.ready():return
        self.store.discover()
        attempt=self.store.reserve()
        if not attempt:return
        if self.pool is None:self.pool=ThreadPoolExecutor(max_workers=1,thread_name_prefix='flow-snapshot-io')
        self.pending=(attempt,self.pool.submit(export_snapshot,self.worker.client,attempt))
