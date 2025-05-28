<?php
header('Content-Type: application/json');

include 'conexao.php'; // Inclua a conexão com o banco de dados.

// Verifica se foi passado um ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['erro' => 'ID inválido']);
    exit;
}

$id = intval($_GET['id']);

// Atualiza a notificação como lida
$sql = "UPDATE notificacoes SET lida = 1 WHERE id = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['sucesso' => true, 'id_lida' => $id]);
    } else {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao executar a query']);
    }
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao preparar a query']);
}

$conn->close();
