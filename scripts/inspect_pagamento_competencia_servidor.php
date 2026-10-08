<?php
if(PHP_SAPI!=='cli') exit(2);
require_once __DIR__.'/../vendor/autoload.php';
$cfg=json_decode(file_get_contents(__DIR__.'/../.vscode/sftp.json'),true,512,JSON_THROW_ON_ERROR);
if($cfg['host']!=='72.60.137.192'||$cfg['username']!=='root'||$cfg['remotePath']!=='/home/improov/web/improov.com.br/public_html/flow/ImproovWeb') throw new RuntimeException('Destino inesperado.');
$ssh=new phpseclib3\Net\SSH2($cfg['host'],22,30);
if(!hash_equals('4fb4d6a8d5f2d3d1837c9284da43f2a6fd51f16786c3efc1392ad72c46da3170',hash('sha256',$ssh->getServerPublicHostKey())) || !$ssh->login($cfg['username'],$cfg['password'])) throw new RuntimeException('SSH não autenticado.');
if(in_array('--executar-job',$argv,true)) {
 $run="import os,subprocess; env=os.environ.copy(); env['PAGAMENTO_JOB_ENV']='/root/improov-pagamento-competencia/job.env'; p=subprocess.run(['/usr/bin/flock','-n','/root/improov-pagamento-competencia/runner.lock','/usr/bin/php','/root/improov-pagamento-competencia/current/scripts/pagamento_competencia_diario.php','--entregar'],env=env,capture_output=True,text=True,timeout=120); print(p.stdout); print(p.stderr); exit(p.returncode)";
 $ssh->setTimeout(150); echo $ssh->exec("python3 -c '".str_replace("'","'\\''",$run)."'");
 if($ssh->getExitStatus()!==0) exit(1); exit;
}
$python=<<<'PY'
import os,json,subprocess
site='/home/improov/web/improov.com.br/public_html/flow/ImproovWeb'
files=['.env','Pagamento/services/FechamentoMensalRules.php','FlowConnect/bootstrap.php','scripts/pagamento_competencia_diario.php']
p=subprocess.run(['php','-v'],capture_output=True,text=True)
cron_service=subprocess.run(['systemctl','is-active','cron'],capture_output=True,text=True)
financial_cron='/etc/cron.d/improov-pagamento-competencia'
log='/root/improov-pagamento-competencia/runner.log'
c=subprocess.run(['crontab','-l'],capture_output=True,text=True)
print(json.dumps({'site_exists':os.path.isdir(site),'files':{f:os.path.isfile(site+'/'+f) for f in files},'php':p.stdout.splitlines()[0] if p.stdout else 'ausente','cron_exists':c.returncode==0,'financial_job_exists':os.path.isfile(financial_cron),'cron_service':cron_service.stdout.strip(),'last_runs':open(log).read().splitlines()[-3:] if os.path.isfile(log) else [],'flow_connect_mentions':c.stdout.count('FlowConnect')}))
PY;
echo $ssh->exec("python3 -c '".str_replace("'","'\\''",$python)."'");
if($ssh->getExitStatus()!==0) exit(1);
