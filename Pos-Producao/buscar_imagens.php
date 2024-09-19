<?php
// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

// Verifica se o ID da obra foi passado
$obra_id = isset($_GET['obra_id']) ? intval($_GET['obra_id']) : null;

// Se o ID da obra foi fornecido, busca todas as imagens da obra
if ($obra_id) {
    $sql = "SELECT idimagens_cliente_obra, imagem_nome 
            FROM imagens_cliente_obra 
            WHERE obra_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Erro na preparação da consulta: " . $conn->error);
    }
    $stmt->bind_param('i', $obra_id);
} else {
    // Se nenhum ID de obra for fornecido, retorna uma resposta vazia
    echo json_encode([]);
    exit;
}

// Execute a consulta
$stmt->execute();
$result = $stmt->get_result();

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
