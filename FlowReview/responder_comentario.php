<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
include '../conexao.php';
$data = json_decode(file_get_contents("php://input"), true);

$comentario_id = $data['comentario_id'];
$texto = $data['texto'];
$responsavel = $_SESSION['idcolaborador'];


$stmt = $conn->prepare("INSERT INTO respostas_comentario (comentario_id, texto, responsavel) VALUES (?, ?, ?)");
$stmt->bind_param('isi', $comentario_id, $texto, $responsavel);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode([
        "id" => $stmt->insert_id,
        "texto" => $texto,
        "data" => date("Y-m-d H:i:s"),
        "nome_responsavel" => $_SESSION['nome_colaborador'],
    ]);
} else {
    echo json_encode(["erro" => "Erro ao salvar resposta"]);
}
