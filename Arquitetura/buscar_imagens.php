<?php
// Conectar ao banco de dados
include 'conexao.php';


// Verifica se o ID da obra foi passado
$obra_id = isset($_GET['obra_id']) ? intval($_GET['obra_id']) : null;

// Se o ID da obra for 0, busca todas as imagens
if ($obra_id === 0) {
    $sql = "SELECT idimagens_cliente_obra, imagem_nome FROM imagens_cliente_obra";
} elseif ($obra_id) {
    // Se o ID da obra foi fornecido, busca todas as imagens da obra
    $sql = "SELECT idimagens_cliente_obra, imagem_nome 
            FROM imagens_cliente_obra 
            WHERE obra_id = ?";
}

// Prepara a consulta
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die(json_encode(["error" => "Erro na preparação da consulta: " . $conn->error]));
}

// Se o ID da obra não for nulo e não for 0, vincula o parâmetro
if ($obra_id && $obra_id !== 0) {
    $stmt->bind_param('i', $obra_id);
}

// Executa a consulta
$stmt->execute();
$result = $stmt->get_result();

// Recupera as imagens
$imagens = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $imagens[] = [
            'idimagens_cliente_obra' => $row['idimagens_cliente_obra'],
            'imagem_nome' => $row['imagem_nome']
        ];
    }
}

// Retorna as imagens como JSON
header('Content-Type: application/json');
echo json_encode($imagens);

// Feche o statement e a conexão
$stmt->close();
$conn->close();