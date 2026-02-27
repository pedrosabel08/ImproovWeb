<?php
$data = json_decode(file_get_contents("php://input"));
$id = $data->id;

include '../conexao.php';

$sql = "DELETE FROM comentarios_review WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['sucesso' => true]);
} else {
    echo json_encode(['sucesso' => false]);
}

$stmt->close();
$conn->close();
