<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
// session_start();
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

$sql = "SELECT 
            hc.*,
            u1.nome_usuario AS created_by_name,
            u2.nome_usuario AS updated_by_name
        FROM handoff_comercial hc
        LEFT JOIN usuario u1 ON u1.idusuario = hc.created_by
        LEFT JOIN usuario u2 ON u2.idusuario = hc.updated_by
        WHERE hc.obra_id = ?
        LIMIT 1";
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
