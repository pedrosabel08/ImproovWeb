<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int)$data['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

include '../conexao.php';

$stmt = $conn->prepare('UPDATE funcao_imagem SET valor_aprovado = 1 WHERE idfuncao_imagem = ?');
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Erro no prepare: ' . $conn->error]);
    exit;
}

$stmt->bind_param('i', $id);
$stmt->execute();
$ok = $stmt->affected_rows >= 0; // 0 = already 1, still success
$stmt->close();
$conn->close();

echo json_encode(['success' => $ok]);
