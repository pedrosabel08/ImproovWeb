<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nao autenticado.']);
    exit;
}

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/pendencias_operacionais_helper.php';

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON invalido.']);
    exit;
}

$checklistId = isset($payload['checklist_id']) ? (int) $payload['checklist_id'] : 0;
$items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [];
$actor = isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null;
$nivelAcesso = isset($_SESSION['nivel_acesso']) ? (int) $_SESSION['nivel_acesso'] : 0;

if ($checklistId <= 0 || empty($items)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Checklist invalido.']);
    exit;
}

pendencias_operacionais_ensure_schema($conn);

$stmtChecklist = $conn->prepare('SELECT id, module_key, responsavel_id, status FROM checklist_operacional WHERE id = ? LIMIT 1');
if (!$stmtChecklist) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Nao foi possivel consultar checklist.']);
    exit;
}
$stmtChecklist->bind_param('i', $checklistId);
$stmtChecklist->execute();
$checklist = $stmtChecklist->get_result()->fetch_assoc();
$stmtChecklist->close();

if (!$checklist || ($checklist['status'] ?? '') === 'cancelado') {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Checklist nao encontrado.']);
    exit;
}

$moduleKey = (string) ($checklist['module_key'] ?? '');
$responsavelId = isset($checklist['responsavel_id']) ? (int) $checklist['responsavel_id'] : 0;
if ($moduleKey === 'projeto' && !in_array($nivelAcesso, [1, 2, 3], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissao para atualizar este checklist.']);
    exit;
}
if ($moduleKey === 'imagem' && !in_array($nivelAcesso, [1, 2, 3, 5], true) && $responsavelId !== (int) $actor) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissao para atualizar este checklist.']);
    exit;
}

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare(
        "UPDATE checklist_operacional_item
            SET done = ?,
                done_by = CASE WHEN ? = 1 THEN ? ELSE NULL END,
                done_at = CASE WHEN ? = 1 THEN COALESCE(done_at, NOW()) ELSE NULL END
          WHERE checklist_id = ?
            AND item_key = ?"
    );
    if (!$stmt) {
        throw new RuntimeException('Nao foi possivel atualizar itens.');
    }

    foreach ($items as $itemKey => $doneValue) {
        $itemKey = trim((string) $itemKey);
        if ($itemKey === '') {
            continue;
        }
        $done = !empty($doneValue) ? 1 : 0;
        $stmt->bind_param('iiiiis', $done, $done, $actor, $done, $checklistId, $itemKey);
        $stmt->execute();
    }
    $stmt->close();

    pendencias_operacionais_update_checklist_status($conn, $checklistId);
    $conn->commit();

    $updatedItems = pendencias_operacionais_fetch_checklist_items($conn, $checklistId);
    $updatedChecklist = null;
    $stmtUpdated = $conn->prepare('SELECT status FROM checklist_operacional WHERE id = ? LIMIT 1');
    if ($stmtUpdated) {
        $stmtUpdated->bind_param('i', $checklistId);
        $stmtUpdated->execute();
        $updatedChecklist = $stmtUpdated->get_result()->fetch_assoc();
        $stmtUpdated->close();
    }

    echo json_encode([
        'success' => true,
        'status' => $updatedChecklist['status'] ?? 'aberto',
        'items' => $updatedItems,
    ]);
} catch (Throwable $throwable) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $throwable->getMessage()]);
}
