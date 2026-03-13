<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/session_bootstrap.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit();
}

include __DIR__ . '/../conexao.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $obraId = intval($_GET['obra_id'] ?? 0);
    if ($obraId <= 0) {
        echo json_encode(['success' => false, 'error' => 'obra_id inválido']);
        exit();
    }
    $stmt = $conn->prepare('SELECT liberar_modelagem FROM obra WHERE idobra = ?');
    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo json_encode(['success' => true, 'liberar_modelagem' => intval($result['liberar_modelagem'] ?? 0)]);
    exit();
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $obraId = intval($data['obra_id'] ?? 0);
    $valor  = isset($data['valor']) ? intval((bool)$data['valor']) : null;

    if ($obraId <= 0) {
        echo json_encode(['success' => false, 'error' => 'obra_id inválido']);
        exit();
    }

    if ($valor === null) {
        // Toggle
        $stmt = $conn->prepare('SELECT liberar_modelagem FROM obra WHERE idobra = ?');
        $stmt->bind_param('i', $obraId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $valor = intval($row['liberar_modelagem'] ?? 0) === 1 ? 0 : 1;
    }

    $stmt = $conn->prepare('UPDATE obra SET liberar_modelagem = ? WHERE idobra = ?');
    $stmt->bind_param('ii', $valor, $obraId);
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();

    echo json_encode(['success' => $ok, 'liberar_modelagem' => $valor]);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método não permitido']);
