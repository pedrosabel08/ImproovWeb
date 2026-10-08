<?php
/** Backup administrativo autorizado: dump remoto somente leitura, sem rotação. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../vendor/autoload.php';
date_default_timezone_set('America/Sao_Paulo');
try {
    $cfg=json_decode(file_get_contents(__DIR__.'/../.vscode/sftp.json'),true,512,JSON_THROW_ON_ERROR);
    if($cfg['host']!=='72.60.137.192'||$cfg['username']!=='root'||(int)$cfg['port']!==22) throw new RuntimeException('Configuração SSH inesperada');
    $ssh=new phpseclib3\Net\SSH2($cfg['host'],22,30);
    $key=$ssh->getServerPublicHostKey();
    if(!hash_equals('4fb4d6a8d5f2d3d1837c9284da43f2a6fd51f16786c3efc1392ad72c46da3170',hash('sha256',$key))) throw new RuntimeException('Host key mudou');
    if(!$ssh->login($cfg['username'],$cfg['password'])) throw new RuntimeException('SSH não autenticou');
    $python=<<<'PY'
import os,json,stat,subprocess,hashlib,datetime,secrets,re,zoneinfo
root='/root/ImproovWeb-deployment-backups'
if os.path.lexists(root):
    st=os.lstat(root)
    if not stat.S_ISDIR(st.st_mode) or st.st_uid!=0 or stat.S_IMODE(st.st_mode)&0o077: raise RuntimeError('Diretorio privado inseguro')
else: os.mkdir(root,0o700)
os.umask(0o077)
tz=zoneinfo.ZoneInfo('America/Sao_Paulo')
stamp=datetime.datetime.now(tz).strftime('%Y-%m-%d_%H-%M-%S')+'_'+secrets.token_hex(3)
file=root+'/flowdb_pre_fechamento_'+stamp+'.sql'
partial=file+'.part'
def query(sql):
    p=subprocess.run(['mysql','--defaults-file=/etc/mysql/debian.cnf','--batch','--raw','--skip-column-names','--execute='+sql],capture_output=True,text=True,timeout=60)
    if p.returncode: raise RuntimeError('Consulta de metadados falhou: exit '+str(p.returncode))
    return [row.split('\t') for row in p.stdout.rstrip('\n').split('\n')] if p.stdout.strip() else []
identity=query('SELECT VERSION(),CURRENT_USER(),@@server_uuid,@@global.log_bin,@@global.log_bin_trust_function_creators')[0]
if identity[1]!='debian-sys-maint@localhost' or identity[2]!='615e3ba3-b18b-11f0-9193-bc2411f08407' or not identity[0].startswith('8.0.'): raise RuntimeError('Destino inesperado')
client=subprocess.check_output(['mysqldump','--version'],text=True).strip()
if not re.search(r'\b8\.0\.\d+',client) or 'MariaDB' in client: raise RuntimeError('Cliente exige MySQL 8')
metadata_sql="SELECT 'TABLE',TABLE_NAME,TABLE_TYPE,COALESCE(ENGINE,'') FROM information_schema.TABLES WHERE TABLE_SCHEMA='flowdb' UNION ALL SELECT 'TRIGGER',TRIGGER_NAME,EVENT_OBJECT_TABLE,DEFINER FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='flowdb' UNION ALL SELECT 'ROUTINE',ROUTINE_NAME,ROUTINE_TYPE,DEFINER FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='flowdb' UNION ALL SELECT 'EVENT',EVENT_NAME,STATUS,DEFINER FROM information_schema.EVENTS WHERE EVENT_SCHEMA='flowdb' UNION ALL SELECT 'FK',CONCAT(TABLE_NAME,'.',CONSTRAINT_NAME),CONCAT(COLUMN_NAME,'=>',REFERENCED_TABLE_NAME,'.',REFERENCED_COLUMN_NAME),CAST(ORDINAL_POSITION AS CHAR) FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA='flowdb' AND REFERENCED_TABLE_NAME IS NOT NULL UNION ALL SELECT 'CHECK',tc.TABLE_NAME,tc.CONSTRAINT_NAME,cc.CHECK_CLAUSE FROM information_schema.TABLE_CONSTRAINTS tc JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.CONSTRAINT_SCHEMA='flowdb' AND tc.CONSTRAINT_TYPE='CHECK' ORDER BY 1,2,3,4"
before=query(metadata_sql)
args=['mysqldump','--defaults-file=/etc/mysql/debian.cnf','--single-transaction','--quick','--routines','--events','--triggers','--no-tablespaces','--column-statistics=0','--set-gtid-purged=OFF','--result-file='+partial,'flowdb']
with open(file+'.stderr','xb') as err:
    p=subprocess.run(args,stdout=subprocess.DEVNULL,stderr=err,timeout=600)
if p.returncode: raise RuntimeError('mysqldump falhou: exit '+str(p.returncode)+'; erro privado em '+file+'.stderr')
size=os.path.getsize(partial)
if size<=0: raise RuntimeError('Dump vazio')
with open(partial,'rb') as stream:
    stream.seek(max(0,size-4096)); tail=stream.read().decode('utf-8','replace')
footer=re.search(r'-- Dump completed on ([^\r\n]+)',tail)
if not footer: raise RuntimeError('Dump sem footer')
after=query(metadata_sql)
if before!=after: raise RuntimeError('Metadados mudaram durante o dump: auditar DDL concorrente')
digest=hashlib.sha256()
with open(partial,'rb') as stream:
    for chunk in iter(lambda:stream.read(1024*1024),b''): digest.update(chunk)
os.rename(partial,file)
manifest={'result':'OK','file':file,'exit_code':p.returncode,'size_bytes':size,'sha256':digest.hexdigest(),'completed':footer.group(1),'recorded_at':datetime.datetime.now(tz).isoformat(),'client':client,'source':{'host':'72.60.137.192','port':3306,'database':'flowdb','account':identity[1],'server_version':identity[0],'server_uuid':identity[2]},'transport':'SSH/SFTP','metadata_stable_during_dump':True,'source_metadata':before,'remote_mode':oct(stat.S_IMODE(os.stat(file).st_mode))}
with open(file+'.manifest.json','x') as stream: json.dump(manifest,stream,ensure_ascii=False,indent=2)
print(json.dumps(manifest,ensure_ascii=False))
PY;
    $runner="import base64;exec(base64.b64decode('".base64_encode($python)."'))";
    $ssh->setTimeout(660);
    $response=$ssh->exec("python3 -c '".str_replace("'","'\\''",$runner)."' 2>/dev/null");
    if($ssh->getExitStatus()!==0) throw new RuntimeException('Dump remoto falhou; consultar erro privado');
    $manifest=json_decode($response,true,512,JSON_THROW_ON_ERROR);
    $remote=$manifest['file'];
    if(!preg_match('~^/root/ImproovWeb-deployment-backups/flowdb_pre_fechamento_[0-9_-]+_[a-f0-9]{6}\.sql$~D',$remote)) throw new RuntimeException('Caminho remoto inesperado');
    $localRoot='C:/ProgramData/ImproovWeb/private/deployment-backups';
    if(!is_dir($localRoot)||!is_writable($localRoot)) throw new RuntimeException('Área privada local indisponível');
    $local=$localRoot.'/'.basename($remote);
    if(file_exists($local)||file_exists($local.'.part')) throw new RuntimeException('Arquivo local já existe');
    $sftp=new phpseclib3\Net\SFTP($cfg['host'],22,30);
    if(!hash_equals(hash('sha256',$key),hash('sha256',$sftp->getServerPublicHostKey()))||!$sftp->login($cfg['username'],$cfg['password'])) throw new RuntimeException('SFTP não autenticou');
    if(!$sftp->get($remote,$local.'.part')) throw new RuntimeException('Transferência falhou');
    if(filesize($local.'.part')!==$manifest['size_bytes']||!hash_equals($manifest['sha256'],hash_file('sha256',$local.'.part'))) throw new RuntimeException('Transferência diverge do SHA remoto');
    if(!rename($local.'.part',$local)) throw new RuntimeException('Publicação local falhou');
    $manifest['remote_file']=$remote; $manifest['file']=$local; $manifest['transfer_sha_verified']=true;
    $json=json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
    file_put_contents($local.'.manifest.json',$json);
    file_put_contents(__DIR__.'/../docs/evidence/pagamento-unblock-backup-2026-10-05.json',$json);
    unset($manifest['source_metadata']);
    echo json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
}catch(Throwable $e){fwrite(STDERR,'Backup remoto falhou: '.get_class($e).' code '.$e->getCode().' — '.$e->getMessage()."\n");exit(2);}
