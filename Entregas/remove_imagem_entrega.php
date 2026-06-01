<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/p00_delivery_helpers.php';

improov_p00_ensure_schema($conn);

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$entregaId = isset($data['entrega_id']) ? (int)$data['entrega_id'] : 0;
$itemId    = isset($data['item_id']) ? (int)$data['item_id'] : 0; // id da tabela entregas_itens
$imagemId  = isset($data['imagem_id']) ? (int)$data['imagem_id'] : 0; // id da imagem (coluna imagem_id)

if ($entregaId <= 0) {
    echo json_encode(['success' => false, 'error' => 'entrega_id inválido']);
    exit;
}

$tipoEntrega = 'PADRAO';
$stmtEntrega = $conn->prepare("SELECT COALESCE(tipo_entrega, 'PADRAO') AS tipo_entrega FROM entregas WHERE id = ? LIMIT 1");
if ($stmtEntrega) {
    $stmtEntrega->bind_param('i', $entregaId);
    $stmtEntrega->execute();
    $resEntrega = $stmtEntrega->get_result()->fetch_assoc();
    if ($resEntrega && isset($resEntrega['tipo_entrega'])) {
        $tipoEntrega = (string) $resEntrega['tipo_entrega'];
    }
    $stmtEntrega->close();
}

// Preferir remoção pelo item_id (chave primária), senão usar entrega_id+imagem_id
if ($tipoEntrega === 'P00' && $itemId > 0) {
    $stmt = $conn->prepare('DELETE FROM entregas_p00_versoes WHERE id = ? AND entrega_id = ? LIMIT 1');
    $stmt->bind_param('ii', $itemId, $entregaId);
} elseif ($itemId > 0) {
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
    'mode' => $tipoEntrega === 'P00' ? 'by_version_id' : ($itemId > 0 ? 'by_item_id' : 'by_imagem_id')
]);
