<?php
require_once __DIR__ . '/../config/session_bootstrap.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit();
}

include '../conexao.php';

$input = json_decode(file_get_contents('php://input'), true);
$funcao_imagem_id = isset($input['funcao_imagem_id']) ? intval($input['funcao_imagem_id']) : 0;

if (!$funcao_imagem_id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit();
}

// Toggle: se já é 1 vira 0, se é 0 vira 1
$stmt = $conn->prepare(
    "UPDATE funcao_imagem SET prioridade_aprovacao = 1 - COALESCE(prioridade_aprovacao, 0) WHERE idfuncao_imagem = ?"
);
$stmt->bind_param("i", $funcao_imagem_id);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit();
}
$stmt->close();

// Retorna o novo valor
$stmtSel = $conn->prepare("SELECT prioridade_aprovacao FROM funcao_imagem WHERE idfuncao_imagem = ?");
$stmtSel->bind_param("i", $funcao_imagem_id);
$stmtSel->execute();
$res = $stmtSel->get_result()->fetch_assoc();
$stmtSel->close();
$conn->close();

echo json_encode([
    'success'    => true,
    'prioridade' => (int)($res['prioridade_aprovacao'] ?? 0),
]);
