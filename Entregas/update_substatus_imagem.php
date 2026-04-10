<?php
// Entregas/update_substatus_imagem.php
// Atualiza o substatus_id de uma ou mais imagens em imagens_cliente_obra
header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';
require_once '../config/session_bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (
    !isset($data['imagem_ids'], $data['substatus_id']) ||
    !is_array($data['imagem_ids']) ||
    empty($data['imagem_ids']) ||
    !is_numeric($data['substatus_id'])
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos.']);
    exit;
}

$substatus_id = intval($data['substatus_id']);
$imagem_ids   = array_map('intval', $data['imagem_ids']);

// Valida que o substatus existe
$stmtCheck = $conn->prepare('SELECT id FROM substatus_imagem WHERE id = ?');
$stmtCheck->bind_param('i', $substatus_id);
$stmtCheck->execute();
if ($stmtCheck->get_result()->num_rows === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Substatus inválido.']);
    exit;
}
$stmtCheck->close();

// Monta IN (?, ?, ...) para prepared statement
$placeholders = implode(',', array_fill(0, count($imagem_ids), '?'));
$types        = str_repeat('i', count($imagem_ids) + 1); // +1 para substatus_id no início
$sql = "UPDATE imagens_cliente_obra SET substatus_id = ? WHERE idimagens_cliente_obra IN ($placeholders)";
$stmt = $conn->prepare($sql);

$params = array_merge([$substatus_id], $imagem_ids);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'affected' => $stmt->affected_rows]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $conn->error]);
}
$stmt->close();
