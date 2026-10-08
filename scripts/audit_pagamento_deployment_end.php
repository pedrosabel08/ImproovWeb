<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../config/pagamento_fechamento.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
date_default_timezone_set('America/Sao_Paulo');
$root='C:/ProgramData/ImproovWeb/private/deployment-backups';
$context=json_decode(file_get_contents($root.'/deployment_pagamento_20261005.context.json'),true,512,JSON_THROW_ON_ERROR);
$db=$context['reference_database'];
if($db!=='pagamento_deploy_reference_20261005_d5807b8a')throw new RuntimeException('Referencia inesperada');
$runtime=pagamento_fechamento_connection();
$counts=[];
$runtime->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
foreach(['revisao','documento'] as $kind){
    preg_match_all('/CREATE TABLE\s+(\w+)/i',file_get_contents(__DIR__.'/../sql/2026-10-02_pagamento_fechamento_'.$kind.'.sql'),$m);
    foreach($m[1] as $table)$counts[$table]=(int)$runtime->query("SELECT COUNT(*) n FROM `$table`")->fetch_assoc()['n'];
}
$runtime->rollback();$runtime->close();
if(array_sum($counts)!==0)throw new RuntimeException('Novas tabelas possuem registros: parar');
$isolated=new mysqli('127.0.0.1','root','',null,3321);
$id=$isolated->query('SELECT @@server_uuid u,@@event_scheduler e,@@datadir d')->fetch_assoc();
if($id['u']!=='393a087f-c110-11f1-a84e-047c16cb9e88'||$id['e']!=='OFF'||!str_starts_with(strtolower(str_replace('\\','/',$id['d'])),strtolower($root.'/mysql8_restore_20261005/data/')))throw new RuntimeException('Instancia nao isolada');
$isolated->query("DROP DATABASE `$db`");
$isolated->query('SHUTDOWN');$isolated->close();
$proof=['at'=>date(DATE_ATOM),'new_table_rows'=>$counts,'financial_acts'=>'NONE','reference_database'=>$db,'reference_cleanup'=>'DROPPED','isolated_instance'=>'SHUTDOWN','backup_preserved'=>file_exists($root.'/flowdb_pre_fechamento_2026-10-05_20-15-50_2c42b1.sql'),'backup_sha256'=>hash_file('sha256',$root.'/flowdb_pre_fechamento_2026-10-05_20-15-50_2c42b1.sql')];
file_put_contents(__DIR__.'/../docs/evidence/pagamento-deployment-end-2026-10-05.json',json_encode($proof,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
echo json_encode($proof,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
