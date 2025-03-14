<?php
$data = json_decode(file_get_contents("php://input"));

include '../conexao.php';

$id = $data->id;
$texto = $data->texto;


$sql = "UPDATE comentarios_imagem SET texto = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('si', $texto, $id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['sucesso' => true]);
} else {
    echo json_encode(['sucesso' => false]);
}

$stmt->close();
$conn->close();
