<?php
header('Content-Type: application/json');
include '../conexao.php'; // Certifique-se de incluir a conexão com o banco

$data = json_decode(file_get_contents("php://input"), true);
$id_alteracao = $data['id_alteracao'];
$campo = $data['campo'];
$valor = $data['valor'];

// Proteção para evitar SQL Injection (usando prepared statements)
$sql = "UPDATE alteracoes SET $campo = ? WHERE id_alteracao = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $valor, $id_alteracao);

if ($stmt->execute()) {
    echo json_encode(["status" => "sucesso"]);
} else {
    echo json_encode(["status" => "erro", "mensagem" => $stmt->error]);
}
