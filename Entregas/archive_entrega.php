<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!$input || !isset($input['entrega_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
    exit;
}

$entrega_id = intval($input['entrega_id']);
$arquivada  = isset($input['arquivada']) ? (intval($input['arquivada']) ? 1 : 0) : 1;

if ($entrega_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'entrega_id inválido']);
    exit;
}

$stmt = $conn->prepare("UPDATE entregas SET arquivada = ? WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}
$stmt->bind_param('ii', $arquivada, $entrega_id);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if ($affected === 0) {
    // Check if row exists at all
    $chk = $conn->prepare("SELECT id FROM entregas WHERE id = ?");
    $chk->bind_param('i', $entrega_id);
    $chk->execute();
    $exists = $chk->get_result()->num_rows > 0;
    $chk->close();
    if (!$exists) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Entrega não encontrada']);
        exit;
    }
}

echo json_encode(['success' => true, 'arquivada' => $arquivada]);
