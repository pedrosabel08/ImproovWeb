<?php
require '../conexao.php';
$data = json_decode(file_get_contents('php://input'), true);

$id = intval($data['id']);
$status = $conn->real_escape_string($data['status']);

$sql = "UPDATE entregas_itens SET status='$status', updated_at=NOW() WHERE id=$id";
if ($conn->query($sql)) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $conn->error]);
}
