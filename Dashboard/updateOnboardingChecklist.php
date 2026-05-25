<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

// session_start();

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/onboarding_helpers.php';

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido.']);
    exit;
}

$obraId = isset($payload['obra_id']) ? (int) $payload['obra_id'] : 0;
$item = trim((string) ($payload['item'] ?? ''));
$allowedItems = ['grupo_cliente', 'grupo_interno'];

if ($obraId <= 0 || !in_array($item, $allowedItems, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Checklist inválido.']);
    exit;
}

$progress = dashboard_get_onboarding_progress_for_obra($conn, $obraId);
if (!$progress) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Onboarding não encontrado para esta obra.']);
    exit;
}

$groupMetadata = [
    'grupo_cliente' => !empty($progress['checklist']['grupo_cliente']),
    'grupo_interno' => !empty($progress['checklist']['grupo_interno']),
];
$groupMetadata[$item] = true;

$colaboradorId = isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null;

try {
    $conn->begin_transaction();
    dashboard_insert_onboarding_event(
        $conn,
        $obraId,
        $colaboradorId,
        'GROUPS_CREATED',
        'Checklist operacional de grupos atualizado.',
        $groupMetadata
    );

    $completion = dashboard_finalize_onboarding_if_ready($conn, $obraId, $colaboradorId);
    $conn->commit();

    $updatedProgress = dashboard_get_onboarding_progress_for_obra($conn, $obraId);
    echo json_encode([
        'success' => true,
        'completed' => (bool) ($completion['completed'] ?? false),
        'pending_items' => $updatedProgress['pending_items'] ?? 0,
        'checklist' => $updatedProgress['checklist'] ?? [],
        'status_obra' => $updatedProgress['status_obra'] ?? null,
    ]);
} catch (Throwable $throwable) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $throwable->getMessage()]);
}
