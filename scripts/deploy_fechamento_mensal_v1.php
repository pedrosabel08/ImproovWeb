<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/../conexao.php';
$has=(int)$conn->query("SELECT COUNT(*) n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='colaborador' AND COLUMN_NAME='tipo_remuneracao'")->fetch_assoc()['n'];
if (!$has) $conn->query(file_get_contents(__DIR__.'/../sql/2026-10-06_fechamento_mensal_v1.sql'));
$hasParticipacao=(int)$conn->query("SELECT COUNT(*) n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='colaborador' AND COLUMN_NAME='participa_fechamento_mensal'")->fetch_assoc()['n'];
if (!$hasParticipacao) $conn->query(file_get_contents(__DIR__.'/../sql/2026-10-06_fechamento_mensal_participacao.sql'));
echo json_encode(['migration'=>$has?'ja_aplicada':'aplicada','participacao'=>$hasParticipacao?'ja_aplicada':'aplicada'])."\n";
$conn->close();
require __DIR__.'/list_fechamento_mensal_colaboradores.php';
