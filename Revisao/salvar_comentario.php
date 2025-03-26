<?php
include '../conexao.php'; // Inclui o arquivo de conexão
session_start(); // Inicia a sessão

$data = json_decode(file_get_contents('php://input'), true);

$ap_imagem_id = $data['ap_imagem_id'];
$x = $data['x'];
$y = $data['y'];
$texto = $data['texto'];
$responsavel = $_SESSION['idcolaborador'];

// Busca o último número de comentário para a imagem
$stmt = $conn->prepare('SELECT IFNULL(MAX(numero_comentario), 0) + 1 AS proximo_numero FROM comentarios_imagem WHERE ap_imagem_id = ?');
$stmt->bind_param('i', $ap_imagem_id);
$stmt->execute();
$result = $stmt->get_result();
$numero_comentario = $result->fetch_assoc()['proximo_numero'];

// Insere o novo comentário com o número gerado
$stmt = $conn->prepare('INSERT INTO comentarios_imagem (ap_imagem_id, numero_comentario, x, y, texto, responsavel_id, data) VALUES (?, ?, ?, ?, ?, ?, NOW())');
$stmt->bind_param('iiddsi', $ap_imagem_id, $numero_comentario, $x, $y, $texto, $responsavel);
$stmt->execute();

$response = [
    'sucesso' => true
];

header('Content-Type: application/json');
echo json_encode($response);
