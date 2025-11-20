<?php
include '../conexao.php';
$data = json_decode(file_get_contents("php://input"), true);
session_start();

$comentario_id = $data['comentario_id'];
$texto = $data['texto'];
$usuario_id = isset($_SESSION['idusuario']) ? intval($_SESSION['idusuario']) : null;


$stmt = $conn->prepare("INSERT INTO respostas_reeview (comentario_id, texto, usuario_id) VALUES (?, ?, ?)");
$stmt->bind_param('isi', $comentario_id, $texto, $usuario_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode([
        "id" => $stmt->insert_id,
        "texto" => $texto,
        "data" => date("Y-m-d H:i:s"),
        "nome_usuario" => $_SESSION['nome_usuario'],
    ]);
} else {
    echo json_encode(["erro" => "Erro ao salvar resposta"]);
}
