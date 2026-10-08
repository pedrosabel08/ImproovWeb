<?php
/** Executor das consultas de integridade, sem mutações financeiras. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/secure_env.php'; improov_load_env_once();
require_once __DIR__.'/../config/pagamento_fechamento.php';
require_once __DIR__.'/../helpers/pagamento_competencia_helper.php';
$ref = $argv[1] ?? '2026-09'; pagamento_competencia_nova($ref);
$c = pagamento_fechamento_connection();
$sql = file_get_contents(__DIR__.'/../sql/validacao_pagamento_competencia.sql');
$sql = str_replace("SET @competencia = '2026-09'", "SET @competencia = '".$c->real_escape_string($ref)."'", $sql);
$out = [];
foreach (explode(';', $sql) as $statement) {
    $statement = trim(preg_replace('/^--.*$/m', '', $statement));
    if ($statement === '') continue;
    $result = $c->query($statement);
    if ($result instanceof mysqli_result) $out[] = ['consulta'=>count($out)+1,'registros'=>$result->fetch_all(MYSQLI_ASSOC)];
}
echo json_encode(['competencia'=>$ref,'consultas'=>$out], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
