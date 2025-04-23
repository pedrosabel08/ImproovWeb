<?php
include '../conexao.php';
$data = json_decode(file_get_contents("php://input"), true);

$comentario_id = $data['comentario_id'];
$texto = $data['texto'];

$stmt = $conn->prepare("INSERT INTO respostas_comentario (comentario_id, texto) VALUES (?, ?)");
$stmt->bind_param('is', $comentario_id, $texto);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode([
        "id" => $stmt->insert_id,
        "texto" => $texto,
        "data" => date("Y-m-d H:i:s")
    ]);
} else {
    echo json_encode(["erro" => "Erro ao salvar resposta"]);
}
