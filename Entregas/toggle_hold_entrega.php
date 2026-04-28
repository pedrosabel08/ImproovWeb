<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$id     = isset($input['entrega_id']) && is_numeric($input['entrega_id']) ? intval($input['entrega_id']) : 0;
$acao   = isset($input['acao']) ? trim($input['acao']) : '';
$motivo = isset($input['motivo']) ? trim($input['motivo']) : '';

if (!$id || !in_array($acao, ['colocar', 'remover'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Parâmetros inválidos.']);
    exit;
}

if ($acao === 'colocar' && $motivo === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'O motivo é obrigatório para colocar em HOLD.']);
    exit;
}

if ($acao === 'colocar') {
    $stmt = $conn->prepare("UPDATE entregas SET em_hold = 1, motivo_hold = ? WHERE id = ?");
    $stmt->bind_param('si', $motivo, $id);
} else {
    $stmt = $conn->prepare("UPDATE entregas SET em_hold = 0, motivo_hold = NULL WHERE id = ?");
    $stmt->bind_param('i', $id);
}

$stmt->execute();

if ($stmt->affected_rows === 0) {
    // Entrega não encontrada ou sem mudança real (já estava no estado solicitado)
    echo json_encode(['ok' => true, 'msg' => 'Nenhuma alteração necessária.']);
    exit;
}

echo json_encode(['ok' => true]);
