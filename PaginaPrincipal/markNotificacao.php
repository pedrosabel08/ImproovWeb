<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
header('Content-Type: application/json');
session_start();
include '../conexao.php';

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$colaborador = isset($_SESSION['idcolaborador']) ? intval($_SESSION['idcolaborador']) : 0;

if ($id <= 0 || $colaborador <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos ou sessão expirada']);
    exit;
}

$stmt = $conn->prepare("UPDATE notificacoes_gerais SET lida = 1 WHERE id = ? AND colaborador_id = ? AND lida = 0");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Erro ao preparar consulta: ' . $conn->error]);
    exit;
}
$stmt->bind_param('ii', $id, $colaborador);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

echo json_encode(['success' => true, 'updated' => $affected]);
$conn->close();
