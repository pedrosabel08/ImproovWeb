<?php

header('Content-Type: application/json');
include '../conexao.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data['usuario'] || !$data['imagem_id'] || !$data['aprovacao']) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados incompletos']);
    exit;
}

$usuario = intval($data['usuario']);
$imagem_id = intval($data['imagem_id']);
$aprovacao = $data['aprovacao'];
$data_aprovacao = date('Y-m-d H:i:s');

// Exemplo: evita duplicidade, atualiza se já existe aprovação desse usuário para essa imagem
$sql = "INSERT INTO aprovacoes_imagem (imagem_id, usuario_id, aprovacao, data_aprovacao)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE aprovacao = VALUES(aprovacao), data_aprovacao = VALUES(data_aprovacao)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiss", $imagem_id, $usuario, $aprovacao, $data_aprovacao);

if ($stmt->execute()) {
    echo json_encode(['sucesso' => true]);
} else {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao salvar no banco.']);
}

$stmt->close();
$conn->close();