<?php
include '../conexao.php';
$data = json_decode(file_get_contents("php://input"), true);
require_once __DIR__ . '/auth_cookie.php';

$comentario_id = isset($data['comentario_id']) ? intval($data['comentario_id']) : 0;
$texto = isset($data['texto']) ? $data['texto'] : '';
$usuario_id = $flow_user_id;
$nome_usuario = $flow_user_name;

if ($comentario_id <= 0 || $texto === '' || empty($usuario_id)) {
    echo json_encode(["erro" => "Parâmetros inválidos ou não autorizado"]);
    exit();
}

$stmt = $conn->prepare("INSERT INTO respostas_reeview (comentario_id, texto, usuario_id) VALUES (?, ?, ?)");
$stmt->bind_param('isi', $comentario_id, $texto, $usuario_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode([
        "id" => $stmt->insert_id,
        "texto" => $texto,
        "data" => date("Y-m-d H:i:s"),
        "nome_usuario" => $nome_usuario,
    ]);
} else {
    echo json_encode(["erro" => "Erro ao salvar resposta"]);
}
