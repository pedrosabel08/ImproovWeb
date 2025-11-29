<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$entregaId = isset($data['entrega_id']) ? (int)$data['entrega_id'] : 0;
$itemId    = isset($data['item_id']) ? (int)$data['item_id'] : 0; // id da tabela entregas_itens
$imagemId  = isset($data['imagem_id']) ? (int)$data['imagem_id'] : 0; // id da imagem (coluna imagem_id)

if ($entregaId <= 0) {
    echo json_encode(['success' => false, 'error' => 'entrega_id inválido']);
    exit;
}

// Preferir remoção pelo item_id (chave primária), senão usar entrega_id+imagem_id
if ($itemId > 0) {
    $stmt = $conn->prepare('DELETE FROM entregas_itens WHERE id = ? AND entrega_id = ? LIMIT 1');
    $stmt->bind_param('ii', $itemId, $entregaId);
} elseif ($imagemId > 0) {
    $stmt = $conn->prepare('DELETE FROM entregas_itens WHERE entrega_id = ? AND imagem_id = ? LIMIT 1');
    $stmt->bind_param('ii', $entregaId, $imagemId);
} else {
    echo json_encode(['success' => false, 'error' => 'Forneça item_id ou imagem_id']);
    exit;
}

$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

echo json_encode([
    'success' => $affected > 0,
    'removed' => $affected,
    'mode' => $itemId > 0 ? 'by_item_id' : 'by_imagem_id'
]);
