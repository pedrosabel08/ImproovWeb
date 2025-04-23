<?php
// Conexão com o banco de dados

include '../conexao.php';

// Pega o ID da imagem
$id = $_GET['id'];

// Busca os comentários no banco de dados
$query = "SELECT ci.*, c.nome_colaborador as nome_responsavel FROM comentarios_imagem ci 
          JOIN colaborador c ON ci.responsavel_id = c.idcolaborador 
          WHERE ap_imagem_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$comentarios = [];

while ($comentario = $result->fetch_assoc()) {
    $comentario_id = $comentario['id'];
    $resQuery = $conn->prepare("SELECT id, texto, data FROM respostas_comentario WHERE comentario_id = ?");
    $resQuery->bind_param('i', $comentario_id);
    $resQuery->execute();
    $resResult = $resQuery->get_result();
    $comentario['respostas'] = $resResult->fetch_all(MYSQLI_ASSOC);

    $comentarios[] = $comentario;
}

echo json_encode($comentarios);
