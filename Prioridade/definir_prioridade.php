<?php
include '../conexao.php';

$data = json_decode(file_get_contents('php://input'), true);
$ids = $data['ids'] ?? [];
$novaPrioridade = $data['novaPrioridade'] ?? null;

if (empty($ids) || !$novaPrioridade) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "UPDATE prioridade_funcao SET prioridade = ? WHERE funcao_imagem_id IN ($placeholders)";

$stmt = $conn->prepare($sql);
$params = array_merge([$novaPrioridade], $ids);
$stmt->bind_param(str_repeat('i', count($params)), ...$params);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar prioridades.']);
}

$stmt->close();
$conn->close();
