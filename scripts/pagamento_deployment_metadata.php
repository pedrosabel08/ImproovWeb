<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
try {
    require __DIR__.'/../conexao.php';
    $out=[];
    foreach(['processes'=>"SELECT ID,COMMAND,TIME,STATE FROM information_schema.PROCESSLIST WHERE USER=SUBSTRING_INDEX(CURRENT_USER(),'@',1)",
        'global_privileges'=>"SELECT PRIVILEGE_TYPE,IS_GRANTABLE FROM information_schema.USER_PRIVILEGES WHERE GRANTEE=CONCAT(CHAR(39),SUBSTRING_INDEX(CURRENT_USER(),'@',1),CHAR(39),'@',CHAR(39),SUBSTRING_INDEX(CURRENT_USER(),'@',-1),CHAR(39))",
        'objects'=>"SELECT 'TRIGGER' kind,COUNT(*) count FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() UNION ALL SELECT 'EVENT',COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA=DATABASE() UNION ALL SELECT 'ROUTINE',COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() UNION ALL SELECT 'VIEW',COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE()",
        'server'=>"SELECT @@global.read_only read_only,@@global.super_read_only super_read_only,@@global.event_scheduler event_scheduler,@@server_uuid server_uuid"] as $key=>$sql) {
        try { $out[$key]=$conn->query($sql)->fetch_all(MYSQLI_ASSOC); }
        catch(Throwable $e) { $out[$key]=['error_code'=>$e->getCode()]; }
    }
    $out['configured_accounts']=[];
    foreach(['conexao.php','conexaoMain.php','ScriptArquivos/conexao.php','Calendario/conexao.php','Arquitetura/conexao.php','Metas/conexao.php','infoCliente/conexao.php','Pos-Producao/conexao.php'] as $file) {
        $text=file_get_contents(__DIR__.'/../'.$file); $values=[];
        foreach(['servername','username','dbname','host','user','database'] as $key) if(preg_match('/\$'.preg_quote($key,'/').'\s*=\s*([\'\"])(.*?)\1\s*;/',$text,$m)) $values[$key]=$m[2];
        $out['configured_accounts'][$file]=$values;
    }
    echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
} catch(Throwable $e){fwrite(STDERR,'Metadata indisponível: '.get_class($e).' code '.$e->getCode()."\n");exit(2);}
