<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/pagamento_auth.php';
pagamento_require_gestor(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int)$data['id'] : 0;

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

require_once __DIR__ . '/../conexao.php';

$check = $conn->prepare('SELECT idfuncao_imagem FROM funcao_imagem WHERE idfuncao_imagem = ? LIMIT 1');
if (!$check) {
    error_log('Payment value approval existence check failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível aprovar o valor.']);
    exit;
}
$check->bind_param('i', $id);
$check->execute();
$exists = $check->get_result()->num_rows > 0;
$check->close();
if (!$exists) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Item não encontrado.']);
    exit;
}

$stmt = $conn->prepare('UPDATE funcao_imagem SET valor_aprovado = 1 WHERE idfuncao_imagem = ?');
if (!$stmt) {
    error_log('Payment value approval prepare failed: ' . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Não foi possível aprovar o valor.']);
    exit;
}

$stmt->bind_param('i', $id);
if (!$stmt->execute()) {
    error_log('Payment value approval update failed: ' . $stmt->error);
    $stmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível aprovar o valor.']);
    exit;
}
$ok = $stmt->affected_rows >= 0; // 0 = already 1, still success
$stmt->close();
$conn->close();

echo json_encode(['success' => $ok]);
