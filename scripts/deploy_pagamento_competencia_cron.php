<?php
/** Publica exclusivamente o job financeiro em diretório privado e cron próprio. */
if(PHP_SAPI!=='cli') exit(2);
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../config/secure_env.php'; improov_load_env_once();
$cfg=json_decode(file_get_contents(__DIR__.'/../.vscode/sftp.json'),true,512,JSON_THROW_ON_ERROR);
if($cfg['host']!=='72.60.137.192'||$cfg['username']!=='root'||(int)$cfg['port']!==22) throw new RuntimeException('Destino inesperado.');
$ssh=new phpseclib3\Net\SSH2($cfg['host'],22,30);
$key=$ssh->getServerPublicHostKey();
if(!hash_equals('4fb4d6a8d5f2d3d1837c9284da43f2a6fd51f16786c3efc1392ad72c46da3170',hash('sha256',$key))) throw new RuntimeException('Host key inesperada.');
if(!$ssh->login($cfg['username'],$cfg['password'])) throw new RuntimeException('SSH não autenticado.');
$files=['scripts/pagamento_competencia_diario.php','config/secure_env.php','config/pagamento_fechamento.php','conexao.php','helpers/custos_helper.php','helpers/pagamento_competencia_helper.php','Entregas/prazo_entrega_helper.php','Pagamento/PagamentoService.php'];
foreach(['FechamentoCompetenciaService','FechamentoCompetenciaAutomacao','FechamentoCompetenciaNotificacoes','FechamentoRevisaoRepository','FechamentoSnapshot','FechamentoFinanceiroRules'] as $class) $files[]='Pagamento/services/'.$class.'.php';
$base=dirname(__DIR__);
foreach(glob($base.'/Pagamento/services/*.php') as $file) $files[]='Pagamento/services/'.basename($file);
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base.'/FlowConnect',FilesystemIterator::SKIP_DOTS));
foreach($it as $file) {
 $path=str_replace('\\','/',substr($file->getPathname(),strlen($base)+1));
 if($file->isFile() && str_ends_with($path,'.php') && !preg_match('~^FlowConnect/(tests|docs|workers|api|admin)/~',$path)) $files[]=$path;
}
$files=array_unique($files); sort($files);
$hashes=[]; foreach($files as $file) $hashes[$file]=hash_file('sha256',$base.'/'.$file);
$version='v20261007_'.substr(hash('sha256',json_encode($hashes)),0,12);
$local=sys_get_temp_dir().'/pagamento_competencia_'.$version.'.zip';
$zip=new ZipArchive(); if($zip->open($local,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) throw new RuntimeException('Pacote não criado.');
foreach($files as $file) $zip->addFile($base.'/'.$file,$file);
$zip->close();
$remote='/root/improov-pagamento-competencia';
$command="python3 -c 'import os; os.makedirs(\"".$remote."\",mode=0o700,exist_ok=True); os.chmod(\"".$remote."\",0o700)'";
$ssh->exec($command); if($ssh->getExitStatus()!==0) throw new RuntimeException('Diretório privado não criado.');
$sftp=new phpseclib3\Net\SFTP($cfg['host'],22,30);
if(!hash_equals(hash('sha256',$key),hash('sha256',$sftp->getServerPublicHostKey())) || !$sftp->login($cfg['username'],$cfg['password'])) throw new RuntimeException('SFTP não autenticado.');
if(!$sftp->put($remote.'/package.zip',$local,phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE)) throw new RuntimeException('Transferência falhou.');
$webhook=getenv('SLACK_WEBHOOK_CONTRATOS_URL');
if(!$webhook || !str_starts_with($webhook,'https://hooks.slack.com/')) throw new RuntimeException('Webhook do canal ausente.');
$env="SLACK_WEBHOOK_CONTRATOS_URL=".$webhook."\nPAGAMENTO_COMPETENCIA_INICIO=2026-09\nPAGAMENTO_AUTOMACAO_USUARIO_ID=1\nPAGAMENTO_RESPONSAVEL_IDS=21,9,43\nFLOW_CONNECT_PAGAMENTO_MODE=active\nPAGAMENTO_APP_URL=https://improov/ImproovWeb/\n";
if(!$sftp->put($remote.'/job.env',$env)) throw new RuntimeException('Configuração privada não transferida.');
$sftp->chmod(0600,$remote.'/job.env');
$payload=base64_encode(json_encode(['version'=>$version,'hashes'=>$hashes,'zip_sha'=>hash_file('sha256',$local)],JSON_THROW_ON_ERROR));
$python=<<<'PY'
import os,json,base64,hashlib,zipfile,subprocess,stat
p=json.loads(base64.b64decode('PAYLOAD'))
root='/root/improov-pagamento-competencia'
st=os.lstat(root)
if not stat.S_ISDIR(st.st_mode) or st.st_uid!=0 or stat.S_IMODE(st.st_mode)&0o077: raise RuntimeError('Diretório privado inseguro')
archive=root+'/package.zip'
if hashlib.sha256(open(archive,'rb').read()).hexdigest()!=p['zip_sha']: raise RuntimeError('Pacote divergente')
target=root+'/'+p['version']
os.umask(0o077)
os.makedirs(target,mode=0o700,exist_ok=True)
with zipfile.ZipFile(archive) as z:
    if set(z.namelist())!=set(p['hashes']): raise RuntimeError('Inventário divergente')
    for name in z.namelist():
        if name.startswith('/') or '..' in name.split('/'): raise RuntimeError('Caminho inválido')
    z.extractall(target)
for name,sha in p['hashes'].items():
    if hashlib.sha256(open(target+'/'+name,'rb').read()).hexdigest()!=sha: raise RuntimeError('Arquivo divergente')
env=os.environ.copy(); env['PAGAMENTO_JOB_ENV']=root+'/job.env'
test=subprocess.run(['/usr/bin/php',target+'/scripts/pagamento_competencia_diario.php','--sem-alertas'],env=env,capture_output=True,text=True,timeout=120)
if test.returncode: raise RuntimeError('Preflight financeiro falhou: '+test.stderr[:500])
cycle=json.loads(test.stdout)
link=root+'/current.next'
if os.path.lexists(link): os.unlink(link)
os.symlink(target,link); os.replace(link,root+'/current')
cron='# Novo job financeiro; nenhum cron existente é substituído.\nSHELL=/bin/sh\nPAGAMENTO_JOB_ENV='+root+'/job.env\n*/15 * * * * root /usr/bin/flock -n '+root+'/runner.lock /usr/bin/php '+root+'/current/scripts/pagamento_competencia_diario.php --entregar >> '+root+'/runner.log 2>&1\n'
path='/etc/cron.d/improov-pagamento-competencia'
if os.path.exists(path) and open(path).read()!=cron:
    open(root+'/cron.previous','w').write(open(path).read())
with open(path+'.new','w') as f: f.write(cron)
os.chmod(path+'.new',0o644); os.replace(path+'.new',path)
print(json.dumps({'job':path,'version':p['version'],'files':len(p['hashes']),'preflight':cycle,'frequency':'15 minutes','slack_sent_in_preflight':False}))
PY;
$python=str_replace('PAYLOAD',$payload,$python);
$ssh->setTimeout(150);
$out=$ssh->exec("python3 -c '".str_replace("'","'\\''",$python)."' 2>&1");
if($ssh->getExitStatus()!==0) throw new RuntimeException('Implantação do job falhou: '.$out);
echo $out;
