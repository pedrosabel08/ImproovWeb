<?php
include_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$idPos = intval($_POST['id_pos'] ?? 0);
if (!$idPos) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

$stmt = $conn->prepare("SELECT status_pos FROM pos_producao WHERE idpos_producao = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}
$stmt->bind_param('i', $idPos);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Registro não encontrado']);
    exit;
}

$newStatus = intval($row['status_pos']) === 0 ? 1 : 0;

$stmt = $conn->prepare("UPDATE pos_producao SET status_pos = ? WHERE idpos_producao = ?");
$stmt->bind_param('ii', $newStatus, $idPos);
$success = $stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['success' => $success, 'new_status' => $newStatus]);
