<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nao autenticado.']);
    exit;
}

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../contact_architecture.php';

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON invalido.']);
    exit;
}

$obraId = isset($payload['obra_id']) ? (int) $payload['obra_id'] : 0;
$contactIds = is_array($payload['contact_ids'] ?? null) ? $payload['contact_ids'] : [];

if ($obraId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Obra invalida.']);
    exit;
}

try {
    $conn->begin_transaction();
    $sync = contact_arch_sync_obra_contacts($conn, $obraId, $contactIds);
    $conn->commit();

    echo json_encode([
        'success' => true,
        'obra_id' => $obraId,
        'linked_count' => (int) ($sync['linked_count'] ?? 0),
        'contact_ids' => $sync['selected_ids'] ?? [],
    ]);
} catch (Throwable $throwable) {
    if ($conn) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $throwable->getMessage()]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
