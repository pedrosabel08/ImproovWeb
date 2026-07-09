<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodo invalido.']);
    exit;
}

$funcaoAnimacaoId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$animacaoId = isset($_POST['animacao_id']) ? (int) $_POST['animacao_id'] : 0;

if ($funcaoAnimacaoId <= 0 || $animacaoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parametros invalidos.']);
    exit;
}

$stmt = $conn->prepare(
    'DELETE FROM funcao_animacao WHERE id = ? AND animacao_id = ? LIMIT 1'
);

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Erro ao preparar exclusao.']);
    $conn->close();
    exit;
}

$stmt->bind_param('ii', $funcaoAnimacaoId, $animacaoId);
$ok = $stmt->execute();
$affected = $stmt->affected_rows;
$error = $stmt->error;
$stmt->close();
$conn->close();

if (!$ok) {
    echo json_encode(['success' => false, 'error' => $error ?: 'Erro ao excluir funcao.']);
    exit;
}

if ($affected < 1) {
    echo json_encode(['success' => false, 'error' => 'Funcao da animacao nao encontrada.']);
    exit;
}

echo json_encode(['success' => true]);
