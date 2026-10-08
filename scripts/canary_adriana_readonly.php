<?php
/** Canary historico: somente SELECT/SHOW. Nenhuma preparacao/decisao/documento. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../config/pagamento_fechamento.php';
$c=pagamento_fechamento_connection();
$c->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
try {
    $people=$c->query("SELECT idcolaborador,nome_colaborador FROM colaborador WHERE LOWER(nome_colaborador) LIKE 'adriana%' ORDER BY idcolaborador")->fetch_all(MYSQLI_ASSOC);
    if(count($people)!==1||(int)$people[0]['idcolaborador']!==14)throw new RuntimeException('Adriana nao identificada inequivocamente');
    $stmt=$c->prepare('SELECT id,competencia,status,payload_enviado,arquivo_nome,arquivo_path FROM adendos WHERE colaborador_id=? ORDER BY competencia DESC,id DESC');
    $id=14;$stmt->bind_param('i',$id);$stmt->execute();$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
    $hist=[];
    foreach($rows as $row){$p=json_decode($row['payload_enviado']??'',true);$hist[]=['id'=>(int)$row['id'],'competencia'=>$row['competencia'],'estado'=>$row['status'],'financeiro'=>is_array($p)?array_intersect_key($p,array_flip(['COMPETENCIA','VALOR_FIXO','VALOR_TOTAL','VALOR_EXTRAS','EXTRAS','SERVICOS','ITENS'])):null,'arquivo_nome'=>$row['arquivo_nome'],'arquivo_path'=>$row['arquivo_path'],'arquivo_disponivel'=>is_file($row['arquivo_path']??''),'payload_sha256'=>hash('sha256',$row['payload_enviado']??'')];}
    $tables=[];
    foreach(['revisao','documento'] as $kind){preg_match_all('/CREATE TABLE\s+(\w+)/i',file_get_contents(__DIR__.'/../sql/2026-10-02_pagamento_fechamento_'.$kind.'.sql'),$m);foreach($m[1] as $table)$tables[$table]=(int)$c->query("SELECT COUNT(*) n FROM `$table`")->fetch_assoc()['n'];}
    $selected=array_values(array_filter($hist,fn($r)=>$r['competencia']==='2026-09'));
    $out=['at'=>(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format(DATE_ATOM),'colaboradora'=>$people[0],'setembro'=>$selected,'competencias_recentes'=>array_slice(array_map(fn($r)=>['id'=>$r['id'],'competencia'=>$r['competencia'],'estado'=>$r['estado']],$hist),0,8),'new_table_rows'=>$tables];
    $root='C:/ProgramData/ImproovWeb/private/deployment-backups';
    $manifest=$root.'/canary_adriana_202609_'.($argv[1]??'before').'.json';
    file_put_contents($manifest,json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");
    echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
}finally{$c->rollback();$c->close();}
