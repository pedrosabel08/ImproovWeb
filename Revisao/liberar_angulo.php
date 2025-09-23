<?php
header('Content-Type: application/json');
include '../conexao.php';

$data = json_decode(file_get_contents('php://input'), true);

if (
    !isset($data['imagem_id']) ||
    !isset($data['liberada']) ||
    !isset($data['sugerida']) ||
    !isset($data['motivo_sugerida'])
) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

$imagem_id = intval($data['imagem_id']);
$historico_id = intval($data['historico_id']);
$liberada = $data['liberada'] ? 1 : 0;
$sugerida = $data['sugerida'] ? 1 : 0;
$motivo_sugerida = trim($data['motivo_sugerida']);

$stmt = $conn->prepare("INSERT INTO angulos_imagens (liberada, sugerida, motivo_sugerida, imagem_id, historico_id) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iisii", $liberada, $sugerida, $motivo_sugerida, $imagem_id, $historico_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao inserir: ' . $conn->error]);
}

$stmt->close();
$conn->close();
