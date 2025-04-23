<?php
include '../conexao.php';

$data = json_decode(file_get_contents("php://input"), true);
$comentario_id = $data['comentario_id'];
$mencionado_id = $data['mencionado_id'];

$stmt = $conn->prepare("INSERT INTO mencoes (comentario_id, mencionado_id) VALUES (?, ?)");
$stmt->bind_param("ii", $comentario_id, $mencionado_id);
$stmt->execute();

echo json_encode(["status" => "ok"]);
