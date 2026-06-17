<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/conclusao_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['idcolaborador'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessao expirada. Faca login novamente.']);
    exit;
}

$loteId = isset($_GET['lote_id']) ? (int) $_GET['lote_id'] : 0;
$dataTriagem = isset($_GET['data_triagem']) ? trim((string) $_GET['data_triagem']) : null;

if ($loteId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Lote invalido.']);
    exit;
}

try {
    echo json_encode(pre_alt_fetch_conclusao_summary($conn, $loteId, $dataTriagem), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

