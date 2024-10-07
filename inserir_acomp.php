<?php
header('Content-Type: application/json');

// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

// Verificar a conexão
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Falha na conexão com o banco de dados']);
    exit();
}
$conn->set_charset('utf8mb4');

// Receber os dados JSON enviados
$data = json_decode(file_get_contents('php://input'), true);

// Verificar se os dados obrigatórios foram enviados
if (!isset($data['obraAcomp']) || !isset($data['colab_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Dados insuficientes']);
    exit();
}

// Preparar o statement SQL para inserir na tabela acompanhamento
$stmt = $conn->prepare("INSERT INTO acompanhamento (obra_id, colaborador_id) VALUES (?, ?)");

// Vincular os parâmetros da consulta
$stmt->bind_param('ii', $data['obraAcomp'], $data['colab_id']);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Acompanhamento inserido com sucesso']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao inserir acompanhamento: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
