<?php
// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

// Verifica a conexão
if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

// Definir o charset
$conn->set_charset('utf8mb4');

// Verifica se o ID da obra foi passado
$obra_id = isset($_GET['obra_id']) ? intval($_GET['obra_id']) : null;

// Se o ID da obra for 0, busca todas as imagens
if ($obra_id === 0) {
    $sql = "SELECT idimagem_animacao, imagem_nome FROM imagem_animacao";
} elseif ($obra_id) {
    // Se o ID da obra foi fornecido, busca todas as imagens da obra
    $sql = "SELECT idimagem_animacao, imagem_nome 
            FROM imagem_animacao 
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
            'idimagem_animacao' => $row['idimagem_animacao'],
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
