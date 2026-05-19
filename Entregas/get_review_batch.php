<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/review_cobranca_lib.php';

$batchId = isset($_GET['review_batch_id']) && is_numeric($_GET['review_batch_id'])
    ? (int) $_GET['review_batch_id']
    : 0;

if ($batchId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'review_batch_id inválido.']);
    exit;
}

if (!entregas_review_schema_ready($conn)) {
    echo json_encode([
        'success' => true,
        'enabled' => false,
        'data' => null,
    ]);
    exit;
}

$data = entregas_review_fetch_batch($conn, $batchId);
if ($data === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Batch não encontrado.']);
    exit;
}

echo json_encode([
    'success' => true,
    'enabled' => true,
    'data' => $data,
]);
