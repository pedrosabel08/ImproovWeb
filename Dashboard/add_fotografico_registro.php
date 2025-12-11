<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

$obra_id = isset($data['obra_id']) && is_numeric($data['obra_id']) ? intval($data['obra_id']) : null;
$registro_data = isset($data['registro_data']) ? $data['registro_data'] : null;
$observacoes = isset($data['observacoes']) ? $data['observacoes'] : null;
 $criado_por = isset($_SESSION['idcolaborador']) ? intval($_SESSION['idcolaborador']) : null;

if (!$obra_id || !$registro_data) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados incompletos']);
    exit;
}

try {
    $sql = "INSERT INTO fotografico_registro (obra_id, registro_data, observacoes, criado_por) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('issi', $obra_id, $registro_data, $observacoes, $criado_por);
    $ok = $stmt->execute();
    if (!$ok) throw new Exception($stmt->error);
    echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
