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

$entrega_id    = intval($input['entrega_id']);
$data_prevista = isset($input['data_prevista']) ? trim($input['data_prevista']) : null;
$status_id     = isset($input['status_id'])     ? intval($input['status_id'])   : null;

if ($entrega_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'entrega_id inválido']);
    exit;
}

// Validate date format
if ($data_prevista !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_prevista)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Formato de data inválido']);
    exit;
}

if ($data_prevista === null && $status_id === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Nenhum campo a atualizar']);
    exit;
}

$conn->begin_transaction();

try {
    if ($data_prevista !== null && $status_id !== null) {
        $stmt = $conn->prepare("UPDATE entregas SET data_prevista = ?, status_id = ? WHERE id = ?");
        $stmt->bind_param('sii', $data_prevista, $status_id, $entrega_id);
    } elseif ($data_prevista !== null) {
        $stmt = $conn->prepare("UPDATE entregas SET data_prevista = ? WHERE id = ?");
        $stmt->bind_param('si', $data_prevista, $entrega_id);
    } else {
        $stmt = $conn->prepare("UPDATE entregas SET status_id = ? WHERE id = ?");
        $stmt->bind_param('ii', $status_id, $entrega_id);
    }
    $stmt->execute();
    $stmt->close();

    // When data_prevista changes, also update entregas_itens.data_prevista
    if ($data_prevista !== null) {
        $stmtItems = $conn->prepare("UPDATE entregas_itens SET data_prevista = ? WHERE entrega_id = ?");
        if ($stmtItems) {
            $stmtItems->bind_param('si', $data_prevista, $entrega_id);
            $stmtItems->execute();
            $stmtItems->close();
        }
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
