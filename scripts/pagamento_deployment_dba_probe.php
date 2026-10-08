<?php
/** Consulta administrativa somente leitura usando configuração SSH já existente. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../vendor/autoload.php';
if(($argv[1]??'')==='--ssh-askpass') {
    // Somente subprocesso askpass do túnel; nunca executar este modo em terminal/log.
    if(getenv('PAGAMENTO_DBA_ASKPASS')!=='1'||getenv('SSH_ASKPASS_REQUIRE')!=='force') exit(2);
    $cfg=json_decode(file_get_contents(__DIR__.'/../.vscode/sftp.json'),true,512,JSON_THROW_ON_ERROR);
    echo $cfg['password']; exit;
}
try {
    $cfg=json_decode(file_get_contents(__DIR__.'/../.vscode/sftp.json'),true,512,JSON_THROW_ON_ERROR);
    if($cfg['host']!=='72.60.137.192'||$cfg['username']!=='root'||(int)$cfg['port']!==22) throw new RuntimeException('Configuração administrativa inesperada');
    $ssh=new phpseclib3\Net\SSH2($cfg['host'],22,15);
    if(!$ssh->login($cfg['username'],$cfg['password'])) throw new RuntimeException('Autenticação administrativa falhou');
    if(($argv[1]??'')==='--prepare-backup-tunnel') {
        $root='C:/ProgramData/ImproovWeb/private/deployment-backups';
        if(!is_dir($root)||!is_writable($root)) throw new RuntimeException('Área privada ausente');
        $settings=parse_ini_string($ssh->exec('cat /etc/mysql/debian.cnf'),true,INI_SCANNER_RAW);
        $client=$settings['client']??[];
        if(($client['user']??'')!=='debian-sys-maint'||empty($client['password'])) throw new RuntimeException('Conta administrativa inesperada');
        $q=static fn(string $v):string=>'"'.str_replace(["\\",'"',"\n","\r"],["\\\\",'\\"','\\n','\\r'],$v).'"';
        $file=$root.'/dba_backup_tunnel.cnf';
        $h=fopen($file,'x'); if(!$h) throw new RuntimeException('Arquivo já existe');
        fwrite($h,"[client]\nhost=127.0.0.1\nport=3330\nuser=".$q($client['user'])."\npassword=".$q($client['password'])."\n"); fclose($h);
        file_put_contents($root.'/dba_known_hosts','72.60.137.192 '.$ssh->getServerPublicHostKey()."\n");
        $helper="@echo off\r\n\"C:\\xampp\\php\\php.exe\" \"C:\\xampp\\htdocs\\ImproovWeb\\scripts\\pagamento_deployment_dba_probe.php\" --ssh-askpass\r\n";
        file_put_contents($root.'/dba_askpass.cmd',$helper);
        echo json_encode(['result'=>'PREPARED','private_option_file'=>$file,'secret_printed'=>false])."\n";
        exit;
    }
    $out=['ssh'=>'OK','host'=>$cfg['host'],'ssh_account'=>'root','host_key_sha256'=>hash('sha256',$ssh->getServerPublicHostKey())];
    $sql='SELECT VERSION(),CURRENT_USER(),@@server_uuid,@@global.log_bin,@@global.log_bin_trust_function_creators; SHOW GRANTS';
    $response=$ssh->exec('mysql --batch --skip-column-names --execute='.escapeshellarg($sql).' 2>/dev/null');
    $out['mysql_exit_code']=$ssh->getExitStatus();
    if($out['mysql_exit_code']!==0) {
        $python=<<<'PY'
import os,json,stat,shlex,subprocess
paths=['/root/.my.cnf','/etc/mysql/debian.cnf','/usr/local/hestia/conf/mysql.conf']
result={'files':[]}
for path in paths:
    if not os.path.isfile(path):
        result['files'].append({'path':path,'exists':False}); continue
    st=os.stat(path)
    result['files'].append({'path':path,'exists':True,'uid':st.st_uid,'mode':oct(stat.S_IMODE(st.st_mode))})
    if st.st_uid!=0 or stat.S_IMODE(st.st_mode)&0o007: continue
    env=os.environ.copy()
    if path.endswith('/mysql.conf'):
        values={}
        for line in open(path):
            if '=' not in line or line.lstrip().startswith('#'): continue
            key,value=line.strip().split('=',1)
            try: parts=shlex.split(value)
            except ValueError: continue
            if len(parts)==1: values[key]=parts[0]
        if values.get('USER','root')!='root' or values.get('HOST','localhost') not in ['localhost','127.0.0.1']: continue
        if not values.get('PASSWORD'): continue
        env['MYSQL_PWD']=values['PASSWORD']
        args=['mysql','--no-defaults','--protocol=SOCKET','--user=root']
    else:
        args=['mysql','--defaults-file='+path,'--protocol=SOCKET']
    args+=['--batch','--skip-column-names','--execute=SELECT VERSION(),CURRENT_USER(),@@server_uuid,@@global.log_bin,@@global.log_bin_trust_function_creators; SHOW GRANTS']
    p=subprocess.run(args,capture_output=True,text=True,env=env,timeout=15)
    result['files'][-1]['mysql_exit_code']=p.returncode
    if p.returncode==0:
        result['admin_config']=path
        result['response']=p.stdout
        break
print(json.dumps(result))
PY;
        // O shell remoto é Linux; não usar escapeshellarg() do PHP Windows para código remoto.
        $runner="import base64;exec(base64.b64decode('".base64_encode($python)."'))";
        $remote=$ssh->exec("python3 -c '".str_replace("'","'\\''",$runner)."' 2>/dev/null");
        if($ssh->getExitStatus()!==0) throw new RuntimeException('Consulta administrativa remota falhou');
        $probe=json_decode($remote,true,512,JSON_THROW_ON_ERROR);
        $out['local_admin_configs']=$probe['files'];
        if(isset($probe['response'])) { $response=$probe['response']; $out['mysql_exit_code']=0; $out['admin_config']=$probe['admin_config']; }
    }
    if($out['mysql_exit_code']===0){
        $lines=explode("\n",trim($response)); $out['mysql_identity']=explode("\t",array_shift($lines));
        $out['grants']=[]; foreach($lines as $line) if(preg_match('/^GRANT (.+?) ON (.+?) TO /',$line,$m)) $out['grants'][]=['privileges'=>explode(', ',$m[1]),'scope'=>$m[2]];
    }
    echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){fwrite(STDERR,'Probe DBA falhou: '.get_class($e).' code '.$e->getCode()."\n");exit(2);}
