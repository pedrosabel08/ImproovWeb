<?php
// Conexão com o banco de dados

include '../conexao.php';

// Pega o ID da imagem
$id = $_GET['id'];

// Busca os comentários no banco de dados
$query = "SELECT * FROM comentarios_imagem WHERE ap_imagem_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

// Retorna os comentários como JSON
$comentarios = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode($comentarios);
