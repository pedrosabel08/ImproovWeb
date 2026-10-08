<?php
/** Restaura somente manifesto válido, em MySQL 8 com datadir privado e porta exclusiva. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__.'/../Pagamento/services/FechamentoComposicaoService.php';
$c=null; $db=null; $status=0;
$stage='manifest';
try {
    $root='C:/ProgramData/ImproovWeb/private/deployment-backups';
    $manifestPath=$argv[1]??'';
    if(!preg_match('~^C:/ProgramData/ImproovWeb/private/deployment-backups/flowdb_pre_fechamento_[0-9_-]+_[a-f0-9]{6}\.sql\.manifest\.json$~D',$manifestPath)) throw new RuntimeException('Manifesto não autorizado');
    $manifest=json_decode(file_get_contents($manifestPath),true,512,JSON_THROW_ON_ERROR);
    $file=$manifest['file'];
    if($manifest['result']!=='OK'||$manifest['exit_code']!==0||$file.'.manifest.json'!==$manifestPath||!hash_equals($manifest['sha256'],hash_file('sha256',$file))) throw new RuntimeException('Backup inválido');
    $stage='isolation';
    $c=new mysqli('127.0.0.1','root','',null,3321); $c->set_charset('utf8mb4');
    $id=$c->query('SELECT VERSION() version,@@port port,@@datadir datadir,@@event_scheduler event_scheduler,@@server_uuid server_uuid,@@log_bin log_bin')->fetch_assoc();
    $datadir=strtolower(str_replace('\\','/',$id['datadir']));
    if(!str_starts_with($id['version'],'8.0.')||(int)$id['port']!==3321||!str_starts_with($datadir,strtolower($root.'/mysql8_restore_20261005/data/'))||$id['event_scheduler']!=='OFF'||$id['server_uuid']===$manifest['source']['server_uuid']) throw new RuntimeException('Instância não isolada');
    $stage='import';
    $db='pagamento_restore_'.date('Ymd_His').'_'.bin2hex(random_bytes(4));
    $c->query("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $mysql=getenv('TEMP').'/pagamento_1cb_runtime/mysql-8.0.42-winx64/bin/mysql.exe';
    $errorFile=$root.'/'.$db.'.import.stderr';
    $p=proc_open([$mysql,'--no-defaults','--host=127.0.0.1','--port=3321','--user=root','--binary-mode','--database='.$db],[0=>['file',$file,'r'],1=>['file',$root.'/'.$db.'.import.stdout','w'],2=>['file',$errorFile,'w']],$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($p)) throw new RuntimeException('Importação não iniciou');
    $exit=proc_close($p);
    if($exit!==0) throw new RuntimeException('Importação incompleta',$exit);
    $stage='inventory';
    $c->select_db($db);
    $read=static fn(string $sql):array=>$c->query($sql)->fetch_all(MYSQLI_ASSOC);
    $tables=$read("SELECT TABLE_NAME,ENGINE,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME");
    $triggers=$read('SELECT TRIGGER_NAME,DEFINER FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() ORDER BY TRIGGER_NAME');
    $events=$read('SELECT EVENT_NAME,STATUS,DEFINER FROM information_schema.EVENTS WHERE EVENT_SCHEMA=DATABASE() ORDER BY EVENT_NAME');
    $routines=$read('SELECT ROUTINE_NAME,ROUTINE_TYPE,DEFINER FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() ORDER BY ROUTINE_NAME');
    $sql=file_get_contents($file);
    preg_match_all('/^CREATE TABLE `([^`]+)`/m',$sql,$expectedTables);
    preg_match_all('/\bTRIGGER `([^`]+)`/',$sql,$expectedTriggers);
    preg_match_all('/\bEVENT `([^`]+)`/',$sql,$expectedEvents);
    preg_match_all('/\bCREATE DEFINER=[^\n]+?\b(?:FUNCTION|PROCEDURE) `([^`]+)`/',$sql,$expectedRoutines);
    $same=static function(array $a,array $b):bool{sort($a);sort($b);return $a===$b;};
    $source=$manifest['source_metadata']??[];
    $expected=static fn(string $kind):array=>array_values(array_filter($source,static fn($row)=>$row[0]===$kind));
    $baseTables=array_values(array_filter($tables,static fn($t)=>$t['TABLE_TYPE']==='BASE TABLE'));
    if(!$same($expectedTables[1],array_column($baseTables,'TABLE_NAME'))||!$same($expectedTriggers[1],array_column($triggers,'TRIGGER_NAME'))||!$same($expectedEvents[1],array_column($events,'EVENT_NAME'))||!$same($expectedRoutines[1],array_column($routines,'ROUTINE_NAME'))) throw new RuntimeException('Inventário difere do dump');
    foreach($baseTables as $t) if($t['ENGINE']!=='InnoDB') throw new RuntimeException('Engine não InnoDB');
    if(!$same(array_column($expected('TABLE'),1),array_column($tables,'TABLE_NAME'))||!$same(array_column($expected('TRIGGER'),1),array_column($triggers,'TRIGGER_NAME'))||!$same(array_column($expected('EVENT'),1),array_column($events,'EVENT_NAME'))||!$same(array_column($expected('ROUTINE'),1),array_column($routines,'ROUTINE_NAME'))) throw new RuntimeException('Inventário difere dos metadados de origem');
    unset($sql);
    $stage='counts';
    $counts=[];
    foreach($baseTables as $t){$table=str_replace('`','``',$t['TABLE_NAME']);$counts[$t['TABLE_NAME']]=(int)$c->query("SELECT COUNT(*) n FROM `$table`")->fetch_assoc()['n'];}
    $origins=['colaborador','usuario','funcao_imagem','imagens_cliente_obra','funcao','log_alteracoes','funcao_animacao','animacao','acompanhamento','pagamento_itens','pagamentos','adendos','informacoes_usuario','endereco','endereco_cnpj'];
    if(array_diff($origins,array_keys($counts))) throw new RuntimeException('Origens financeiras ausentes');
    $foreignKeys=$read('SELECT CONSTRAINT_NAME,TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION');
    $groups=[];
    foreach($foreignKeys as $fk) $groups[$fk['TABLE_NAME'].'.'.$fk['CONSTRAINT_NAME']][]=$fk;
    $restoredFkMetadata=array_map(static fn($fk)=>[$fk['TABLE_NAME'].'.'.$fk['CONSTRAINT_NAME'],$fk['COLUMN_NAME'].'=>'.$fk['REFERENCED_TABLE_NAME'].'.'.$fk['REFERENCED_COLUMN_NAME'],(string)$fk['ORDINAL_POSITION']],$foreignKeys);
    $sourceFkMetadata=array_map(static fn($fk)=>array_slice($fk,1),$expected('FK'));
    if(!$same($sourceFkMetadata,$restoredFkMetadata)) throw new RuntimeException('FKs diferem da origem');
    $stage='foreign_keys';
    $fkResults=[];
    $ident=static fn(string $s):string=>'`'.str_replace('`','``',$s).'`';
    foreach($groups as $name=>$columns){
        $fk=$columns[0]; if($fk['REFERENCED_TABLE_SCHEMA']!==$db) throw new RuntimeException('FK fora do schema restaurado');
        $join=[];$nonnull=[];
        foreach($columns as $column){$join[]='p.'.$ident($column['REFERENCED_COLUMN_NAME']).'=c.'.$ident($column['COLUMN_NAME']);$nonnull[]='c.'.$ident($column['COLUMN_NAME']).' IS NOT NULL';}
        $query='SELECT COUNT(*) n FROM '.$ident($fk['TABLE_NAME']).' c WHERE '.implode(' AND ',$nonnull).' AND NOT EXISTS (SELECT 1 FROM '.$ident($fk['REFERENCED_TABLE_NAME']).' p WHERE '.implode(' AND ',$join).')';
        $fkResults[$name]=(int)$c->query($query)->fetch_assoc()['n'];
    }
    $stage='checks';
    $checks=$read("SELECT tc.TABLE_NAME,tc.CONSTRAINT_NAME,cc.CHECK_CLAUSE,tc.ENFORCED FROM information_schema.TABLE_CONSTRAINTS tc JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.CONSTRAINT_SCHEMA=DATABASE() AND tc.CONSTRAINT_TYPE='CHECK' ORDER BY tc.TABLE_NAME,tc.CONSTRAINT_NAME");
    $restoredChecks=array_map(static fn($check)=>[$check['TABLE_NAME'],$check['CONSTRAINT_NAME'],$check['CHECK_CLAUSE']],$checks);
    if(!$same(array_map(static fn($check)=>array_slice($check,1),$expected('CHECK')),$restoredChecks)) throw new RuntimeException('CHECKs diferem da origem');
    $checkResults=[];
    foreach($checks as $check) {
        // CHECK_CLAUSE inclui escaping de apresentação; SHOW CREATE fornece SQL executável.
        $ddl=$c->query('SHOW CREATE TABLE '.$ident($check['TABLE_NAME']))->fetch_assoc()['Create Table'];
        if(!preg_match('/CONSTRAINT `'.preg_quote($check['CONSTRAINT_NAME'],'/').'` CHECK \((.*)\)(?: NOT ENFORCED)?[,]?$/m',$ddl,$match)) throw new RuntimeException('Expressão CHECK não localizada');
        $violations=(int)$c->query('SELECT COUNT(*) n FROM '.$ident($check['TABLE_NAME']).' WHERE ('.$match[1].') = 0')->fetch_assoc()['n'];
        $checkResults[$check['TABLE_NAME'].'.'.$check['CONSTRAINT_NAME']]=['enforced'=>$check['ENFORCED'],'violations'=>$violations];
        if($violations!==0) throw new RuntimeException('CHECK com violação');
    }
    $views=array_values(array_filter($tables,static fn($t)=>$t['TABLE_TYPE']==='VIEW'));
    foreach($views as $view) $c->query('SELECT * FROM '.$ident($view['TABLE_NAME']).' LIMIT 0');
    $stage='readers';
    $beneficiarios=$read('SELECT c.idcolaborador FROM colaborador c JOIN funcao_imagem fi ON fi.colaborador_id=c.idcolaborador GROUP BY c.idcolaborador ORDER BY COUNT(*) DESC,c.idcolaborador LIMIT 3');
    $s=new FechamentoFinanceiroService(new FechamentoFinanceiroRepository($c));
    $composicao=new FechamentoComposicaoService($s,new FechamentoComposicaoRepository());
    $serviceReads=0;
    foreach($beneficiarios as $b){$s->calcular((int)$b['idcolaborador'],'2026-09');$composicao->calcular((int)$b['idcolaborador'],'2026-09');$serviceReads+=2;}
    if(!$serviceReads) throw new RuntimeException('Sem amostra de leitura');
    if($c->query('SELECT @@event_scheduler v')->fetch_assoc()['v']!=='OFF') throw new RuntimeException('Scheduler alterado');
    $evidence=['result'=>'OK','at'=>date(DATE_ATOM),'backup'=>$file,'sha256'=>$manifest['sha256'],'instance'=>$id,'database'=>$db,'import_exit_code'=>$exit,'source_inventory_match'=>true,'dump_inventory_match'=>true,'tables_count'=>count($baseTables),'innodb_count'=>count($baseTables),'views_count'=>count($views),'triggers'=>$triggers,'events'=>$events,'routines'=>$routines,'foreign_keys_count'=>count($groups),'foreign_key_orphan_counts'=>$fkResults,'checks'=>$checkResults,'table_counts'=>$counts,'financial_origin_counts'=>array_intersect_key($counts,array_flip($origins)),'service_reads'=>['1A'=>'OK','1B'=>'OK','read_calls'=>$serviceReads,'sample'=>'3 colaboradores com maior quantidade de funcao_imagem; competencia 2026-09; sem snapshots ou valores publicados'],'event_execution'=>'DISABLED','cleanup'=>'PENDING'];
    $c->select_db('mysql'); $c->query("DROP DATABASE `$db`"); $evidence['cleanup']='DATABASE_DROPPED';
    file_put_contents(__DIR__.'/../docs/evidence/pagamento-unblock-restore-2026-10-05.json',json_encode($evidence,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
    echo json_encode(array_diff_key($evidence,array_flip(['table_counts','triggers','events','routines','foreign_key_orphan_counts'])),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){
    if($db!==null) file_put_contents(__DIR__.'/../docs/evidence/pagamento-unblock-restore-attempt-2026-10-05.json',json_encode(['result'=>'FAILED','stage'=>$stage,'database'=>$db,'error_code'=>$e->getCode(),'import_exit_code'=>$exit??null,'foreign_key_orphan_counts'=>$fkResults??[]],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
    fwrite(STDERR,'Restore falhou: etapa '.$stage.'; '.get_class($e).' code '.$e->getCode().'; database '.($db??'não criado')."\n");$status=2;
}
finally{if($c instanceof mysqli)$c->close();}
exit($status);
