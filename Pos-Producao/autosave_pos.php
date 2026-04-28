<?php
include_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido']);
    exit;
}

$idPos = intval($input['id_pos'] ?? 0);
if (!$idPos) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

$caminhoPasta  = $input['caminho_pasta'] ?? '';
$numeroBG      = $input['numero_bg'] ?? '';
$refs          = $input['refs'] ?? '';
$obs           = $input['obs'] ?? '';
$statusId      = intval($input['status_id'] ?? 0);
$colaboradorId = intval($input['colaborador_id'] ?? 0);
$responsavelId = intval($input['responsavel_id'] ?? 0);

$sql = "UPDATE pos_producao
        SET caminho_pasta = ?,
            numero_bg     = ?,
            refs          = ?,
            obs           = ?,
            status_id     = ?,
            colaborador_id = ?,
            responsavel_id = ?
        WHERE idpos_producao = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}

$stmt->bind_param('ssssiiii', $caminhoPasta, $numeroBG, $refs, $obs, $statusId, $colaboradorId, $responsavelId, $idPos);
$success = $stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['success' => $success]);
