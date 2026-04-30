<?php

/**
 * SIRE — Golden Samples
 * Endpoint AJAX: golden_sample_ajax.php
 * Alterna (toggle) o valor de golden_sample de uma referência.
 */
require_once __DIR__ . '/../config/session_bootstrap.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit();
}

if (session_status() === PHP_SESSION_ACTIVE)
    session_write_close();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit();
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

$referencia_id = isset($data['referencia_id']) ? (int) $data['referencia_id'] : 0;

if ($referencia_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit();
}

include_once __DIR__ . '/../conexaoMain.php';

$conn = conectarBanco();

// Busca estado atual
$stmt = $conn->prepare("SELECT golden_sample FROM referencias_imagens WHERE id = ?");
if (!$stmt) {
    $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno.']);
    exit();
}
$stmt->bind_param('i', $referencia_id);
$stmt->execute();
$stmt->bind_result($current_value);

if (!$stmt->fetch()) {
    $stmt->close();
    $conn->close();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Referência não encontrada.']);
    exit();
}
$stmt->close();

// Inverte o valor
$novo_valor = ($current_value == 1) ? 0 : 1;

$upd = $conn->prepare("UPDATE referencias_imagens SET golden_sample = ? WHERE id = ?");
if (!$upd) {
    $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar.']);
    exit();
}
$upd->bind_param('ii', $novo_valor, $referencia_id);
$upd->execute();
$upd->close();
$conn->close();

echo json_encode([
    'success' => true,
    'golden_sample' => $novo_valor,
]);
