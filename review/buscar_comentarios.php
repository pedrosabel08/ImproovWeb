<?php
// Conexão com o banco de dados

include '../conexao.php';

// Pega o ID da imagem
$id = $_GET['id'];

// Busca os comentários no banco de dados
$query = "SELECT cr.*, u.nome_usuario as nome_responsavel FROM comentarios_review cr 
          JOIN usuario_externo u ON cr.usuario_id = u.idusuario 
          WHERE review_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$comentarios = [];

while ($comentario = $result->fetch_assoc()) {
    $comentario_id = $comentario['id'];
    $resQuery = $conn->prepare("SELECT id, texto, data, u.nome_usuario as nome_responsavel FROM respostas_review r 
    JOIN usuario_externo u on r.usuario_id = u.idusuario WHERE comentario_id = ?");
    $resQuery->bind_param('i', $comentario_id);
    $resQuery->execute();
    $resResult = $resQuery->get_result();
    $comentario['respostas'] = $resResult->fetch_all(MYSQLI_ASSOC);

    $comentarios[] = $comentario;
}

echo json_encode($comentarios);
