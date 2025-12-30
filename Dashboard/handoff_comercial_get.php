<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

require '../conexao.php';

$obraId = null;
if (isset($_GET['obra_id'])) $obraId = intval($_GET['obra_id']);
if ($obraId === null && isset($_GET['obraId'])) $obraId = intval($_GET['obraId']);

if (!$obraId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'obra_id inválido']);
    exit;
}

$sql = "SELECT * FROM handoff_comercial WHERE obra_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao preparar query']);
    exit;
}

$stmt->bind_param('i', $obraId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'data' => $row]);
