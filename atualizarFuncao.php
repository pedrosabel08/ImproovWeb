<?php
require_once __DIR__ . '/config/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

// HOLD de tarefa não é mais uma alteração direta: ele só pode ser derivado de
// uma Issue do Flow Block.  Mantemos o endpoint para não deixar consumidores
// legados mudarem o estado sem histórico/auditoria.
http_response_code(410);
echo json_encode([
    'success' => false,
    'message' => 'Crie uma Issue no Flow Block para bloquear a tarefa.',
    'flow_block_url' => 'FlowBlock/index.php?new_task=' . (int) ($_POST['idfuncao_imagem'] ?? 0),
], JSON_UNESCAPED_UNICODE);
