<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../conexao.php';
$queries = [
 'schema' => "SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('colaborador','funcao_imagem','log_alteracoes','historico_imagens','status_imagem','entregas') ORDER BY TABLE_NAME,ORDINAL_POSITION",
 'status' => 'SELECT * FROM status_imagem',
 'funcoes' => 'SELECT idfuncao,nome_funcao FROM funcao',
 'eventos' => "SELECT la.status_novo,COUNT(*) quantidade FROM log_alteracoes la JOIN funcao_imagem fi ON fi.idfuncao_imagem=la.funcao_imagem_id WHERE fi.funcao_id=4 GROUP BY la.status_novo",
 'r0_amostra' => "SELECT la.idlog,la.funcao_imagem_id,la.status_anterior,la.status_novo,la.data,fi.imagem_id,(SELECT h.status_id FROM historico_imagens h WHERE h.imagem_id=fi.imagem_id AND h.data_movimento<=la.data ORDER BY h.data_movimento DESC,h.idhistorico DESC LIMIT 1) ciclo FROM log_alteracoes la JOIN funcao_imagem fi ON fi.idfuncao_imagem=la.funcao_imagem_id WHERE fi.funcao_id=4 AND la.data>='2026-09-01' AND la.data<'2026-10-01' ORDER BY la.idlog DESC LIMIT 16",
];
foreach ($queries as $name=>$sql) {
 try { echo $name.': '.json_encode($conn->query($sql)->fetch_all(MYSQLI_ASSOC),JSON_UNESCAPED_UNICODE)."\n"; }
 catch(Throwable $e) { echo $name.': '.$e->getMessage()."\n"; }
}
