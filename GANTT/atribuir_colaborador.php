<?php
include '../conexao.php'; // Sua conexão com o banco

$input = json_decode(file_get_contents('php://input'), true);
$gantt_id = intval($input['gantt_id']);
$colaborador_id = intval($input['colaborador_id']);

$stmt = $conn->prepare("INSERT INTO etapa_colaborador (gantt_id, colaborador_id) VALUES (?, ?)");
$stmt->bind_param("ii", $gantt_id, $colaborador_id);

if ($stmt->execute()) {
    echo json_encode(['message' => 'Colaborador atribuído com sucesso.']);
} else {
    echo json_encode(['message' => 'Erro ao atribuir colaborador.']);
}
