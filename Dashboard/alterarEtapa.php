<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';

if (!isset($_POST['imagem_id'], $_POST['status_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'imagem_id e status_id são obrigatórios.']);
    exit;
}

$imagem_id = intval($_POST['imagem_id']);
$status_id = intval($_POST['status_id']);

if ($imagem_id <= 0 || $status_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valores inválidos.']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE imagens_cliente_obra SET status_id = ? WHERE idimagens_cliente_obra = ?");
    $stmt->bind_param('ii', $status_id, $imagem_id);
    $stmt->execute();

    if ($stmt->affected_rows >= 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Nenhuma linha atualizada.']);
    }
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
