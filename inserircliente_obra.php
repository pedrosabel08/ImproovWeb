<?php
header('Content-Type: application/json');

include 'conexao.php';

// Receber os dados JSON enviados
$data = json_decode(file_get_contents('php://input'), true);

if ($data['opcao'] === 'obra') {
    $stmt = $conn->prepare("INSERT INTO obra (nome_obra) VALUES (?)");
    $stmt->bind_param('s', $data['nome']);
} else if ($data['opcao'] === 'cliente') {
    $stmt = $conn->prepare("INSERT INTO cliente (nome_cliente) VALUES (?)");
    $stmt->bind_param('s', $data['nome']);
}

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => ucfirst($data['opcao']) . ' inserido(a) com sucesso']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao inserir ' . $data['opcao'] . ': ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>