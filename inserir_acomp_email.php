<?php
header('Content-Type: application/json');

$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Falha na conexão com o banco de dados']);
    exit();
}
$conn->set_charset('utf8mb4');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['obraAcomp']) || !isset($data['colab_id']) || !isset($data['assunto'])) {
    echo json_encode(['status' => 'error', 'message' => 'Dados insuficientes']);
    exit();
}

$stmt = $conn->prepare("INSERT INTO acompanhamento_email (obra_id, colaborador_id, assunto) VALUES (?, ?, ?)");

$stmt->bind_param('iis', $data['obraAcomp'], $data['colab_id'], $data['assunto']);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Acompanhamento inserido com sucesso']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao inserir acompanhamento: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
