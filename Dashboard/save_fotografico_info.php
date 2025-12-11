<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

$obra_id = isset($data['obra_id']) && is_numeric($data['obra_id']) ? intval($data['obra_id']) : null;
$endereco = isset($data['endereco']) ? trim($data['endereco']) : null;
$altura1 = isset($data['altura1']) ? trim($data['altura1']) : null;
$altura2 = isset($data['altura2']) ? trim($data['altura2']) : null;
$altura3 = isset($data['altura3']) ? trim($data['altura3']) : null;

if (!$obra_id) {
    http_response_code(400);
    echo json_encode(['error' => 'obra_id inválido']);
    exit;
}

try {
    // upsert
     $sql = "INSERT INTO fotografico_info (obra_id, endereco)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE endereco = VALUES(endereco)";
     $stmt = $conn->prepare($sql);
     $stmt->bind_param('is', $obra_id, $endereco);
    $ok = $stmt->execute();
    if (!$ok) throw new Exception($stmt->error);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
