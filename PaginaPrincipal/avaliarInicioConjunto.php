<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/inicio_conjunto_helper.php';

header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'code' => 'UNAUTHENTICATED', 'message' => 'Sessão inválida.']);
    exit;
}
$modelagemId = (int) ($_GET['modelagem_id'] ?? $_POST['modelagem_id'] ?? 0);
try {
    $evaluation = flow_inicio_conjunto_avaliar_modelagem_composicao($conn, $modelagemId);
    echo json_encode(['success' => true] + $evaluation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'JOINT_START_EVALUATION_FAILED', 'message' => 'Não foi possível avaliar o início conjunto.'], JSON_UNESCAPED_UNICODE);
}

