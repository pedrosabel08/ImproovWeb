<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
include '../conexao.php';

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? intval($data['id']) : 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'ID inválido.']);
    exit;
}

$responsavel = $_SESSION['idcolaborador'];

// Exclui menções vinculadas a esta resposta antes de deletar
$stmtMencoes = $conn->prepare('DELETE FROM mencoes WHERE resposta_id = ?');
$stmtMencoes->bind_param('i', $id);
$stmtMencoes->execute();

$stmt = $conn->prepare('DELETE FROM respostas_comentario WHERE id = ? AND responsavel = ?');
$stmt->bind_param('ii', $id, $responsavel);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['sucesso' => true]);
} else {
    echo json_encode(['sucesso' => false, 'erro' => 'Resposta não encontrada ou sem permissão.']);
}
