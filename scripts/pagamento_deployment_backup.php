<?php
/** Backup manual completo; CLI, sem rotação, senha somente em option file privado temporário. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('America/Sao_Paulo');
$option = null;
try {
    $root = 'C:/ProgramData/ImproovWeb/private/deployment-backups';
    if (!is_dir($root) || !is_writable($root)) throw new RuntimeException('Diretório privado indisponível');
    require __DIR__.'/../conexao.php';
    if ($dbname !== 'flowdb' || $servername !== '72.60.137.192') throw new RuntimeException('Destino inesperado');
    $adminTunnel=in_array('--admin-tunnel',$argv,true);
    $stamp = date('Y-m-d_H-i-s').'_'.bin2hex(random_bytes(3));
    $option = $adminTunnel?$root.'/dba_backup_tunnel.cnf':$root.'/client_'.$stamp.'.cnf';
    $quote = static fn(string $v): string => '"'.str_replace(["\\",'"',"\n","\r"],["\\\\",'\\"','\\n','\\r'],$v).'"';
    if($adminTunnel) {
        if(!is_file($option)) throw new RuntimeException('Option file administrativo ausente');
        $settings=parse_ini_file($option,true,INI_SCANNER_RAW)['client'];
        $password=$settings['password'];
        $verify=new mysqli('127.0.0.1','debian-sys-maint',$password,'flowdb',3330);
        $id=$verify->query('SELECT @@server_uuid u,CURRENT_USER() account')->fetch_assoc();
        if($id['u']!==$conn->query('SELECT @@server_uuid u')->fetch_assoc()['u']||$id['account']!=='debian-sys-maint@localhost') throw new RuntimeException('Túnel aponta a destino inesperado');
        $verify->close();
    } else {
    $h = fopen($option,'x');
    if (!$h) throw new RuntimeException('Option file indisponível');
    fwrite($h,"[client]\nhost=".$quote($servername)."\nport=3306\nuser=".$quote($username)."\npassword=".$quote($password)."\n");
    fclose($h);
    }
    $legacy=in_array('--legacy-metadata-probe',$argv,true);
    $dump = $legacy?'C:/xampp/mysql/bin/mysqldump.exe':getenv('TEMP').'/pagamento_1cb_runtime/mysql-8.0.42-winx64/bin/mysqldump.exe';
    if (!is_file($dump)) throw new RuntimeException('Cliente homologado ausente');
    $file = $root.'/'.($legacy?'legacy_metadata_probe_':'flowdb_pre_fechamento_').$stamp.'.sql';
    $args=$legacy?[$dump,'--defaults-file='.$option,'--no-data','--skip-lock-tables','--result-file='.$file,'flowdb']:[$dump,'--defaults-file='.$option,'--single-transaction','--quick','--routines','--events','--triggers','--no-tablespaces','--column-statistics=0','--set-gtid-purged=OFF','--result-file='.$file,'flowdb'];
    $p = proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
    if (!is_resource($p)) throw new RuntimeException('Cliente indisponível');
    fclose($pipes[0]); stream_get_contents($pipes[1]); fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]); fclose($pipes[2]); $exit = proc_close($p);
    // Nunca ecoar stderr arbitrário do cliente (pode incluir dados de rotina/objeto).
    if ($exit !== 0) {
        file_put_contents($file.'.error.txt',str_replace($password,'[REDACTED]',$error));
        preg_match('/(?:Error|Got error):?\s*(\d+)/i',$error,$m);
        $permission = [];
        foreach (['SELECT','SHOW VIEW','TRIGGER','EVENT','SHOW_ROUTINE','PROCESS','RELOAD','LOCK TABLES','SUPER'] as $priv) if (stripos($error,$priv)!==false) $permission[]=$priv;
        if (preg_match('/insufficient privileges to SHOW CREATE (FUNCTION|PROCEDURE)/i',$error)) $permission[]='SHOW_ROUTINE';
        echo json_encode(['result'=>'FAILED','exit_code'=>$exit,'mysql_error_code'=>$m[1]??null,'permissions_mentioned'=>$permission,'partial_file'=>$file,'private_error_file'=>$file.'.error.txt'])."\n";
        throw new RuntimeException('Cliente retornou erro');
    }
    if($legacy){ echo json_encode(['result'=>'METADATA_PROBE_OK','exit_code'=>$exit,'file'=>$file])."\n"; }
    else {
    clearstatcache(true,$file); $size = filesize($file);
    $h=fopen($file,'rb'); fseek($h,max(0,$size-4096)); $tail=stream_get_contents($h); fclose($h);
    if ($size<=0 || !preg_match('/-- Dump completed on ([^\r\n]+)/',$tail,$m)) throw new RuntimeException('Dump sem finalização');
    $manifest=['result'=>'OK','file'=>$file,'exit_code'=>$exit,'size_bytes'=>$size,'completed'=>$m[1],'recorded_at'=>date(DATE_ATOM),'sha256'=>hash_file('sha256',$file),'source'=>['host'=>$servername,'port'=>3306,'database'=>$dbname,'account'=>$adminTunnel?$id['account']:$conn->query('SELECT CURRENT_USER() u')->fetch_assoc()['u'],'server_uuid'=>$conn->query('SELECT @@server_uuid u')->fetch_assoc()['u']],'client'=>'MySQL 8.0.42','transport'=>$adminTunnel?'SSH tunnel to server localhost':'TCP'];
    file_put_contents($file.'.manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
    echo json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
    }
} catch (Throwable $e) { fwrite(STDERR,'Backup falhou: '.get_class($e).' code '.$e->getCode()."\n"); $status=2; }
finally { if ($option !== null && is_file($option)) unlink($option); }
exit($status??0);
