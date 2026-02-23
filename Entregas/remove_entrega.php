<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexao.php';

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!$input || !isset($input['entrega_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
    exit;
}

$entrega_id = intval($input['entrega_id']);
if ($entrega_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'entrega_id inválido']);
    exit;
}

try {
    $conn->begin_transaction();

    // Optionally verify existence
    $stmt = $conn->prepare("SELECT id FROM entregas WHERE id = ?");
    $stmt->bind_param('i', $entrega_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $stmt->close();
        $conn->rollback();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Entrega não encontrada']);
        exit;
    }
    $stmt->close();

    // delete itens
    $stmt = $conn->prepare("DELETE FROM entregas_itens WHERE entrega_id = ?");
    $stmt->bind_param('i', $entrega_id);
    $stmt->execute();
    $stmt->close();

    // delete entrega
    $stmt = $conn->prepare("DELETE FROM entregas WHERE id = ?");
    $stmt->bind_param('i', $entrega_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    $conn->commit();

    if ($affected > 0) {
        echo json_encode(['success' => true, 'message' => 'Entrega removida com sucesso']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Falha ao remover entrega']);
    }
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>