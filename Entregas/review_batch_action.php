<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/review_cobranca_lib.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado.']);
    exit;
}

if (!entregas_review_schema_ready($conn)) {
    http_response_code(412);
    echo json_encode(['success' => false, 'error' => 'Estrutura de review/cobrança ainda não instalada.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']);
    exit;
}

$reviewBatchId = isset($input['review_batch_id']) && is_numeric($input['review_batch_id'])
    ? (int) $input['review_batch_id']
    : 0;
$action = isset($input['action']) ? strtolower(trim((string) $input['action'])) : '';
$reason = isset($input['reason']) ? trim((string) $input['reason']) : '';
$note = isset($input['note']) ? trim((string) $input['note']) : '';
$snoozeUntil = isset($input['snooze_until']) ? trim((string) $input['snooze_until']) : '';
$actorUserId = (int) ($_SESSION['idusuario'] ?? 0);

if ($reviewBatchId <= 0 || !in_array($action, ['notify', 'snooze', 'resolve', 'ignore'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos.']);
    exit;
}

$current = entregas_review_fetch_batch($conn, $reviewBatchId);
if ($current === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Batch não encontrado.']);
    exit;
}

$currentStatus = strtoupper((string) ($current['billing_status'] ?? $current['batch_status'] ?? 'PENDING'));
if (in_array($currentStatus, ['RESOLVED', 'IGNORED'], true)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'Batch já encerrado.']);
    exit;
}

$billingId = isset($current['cobranca_id']) ? (int) $current['cobranca_id'] : 0;
if ($billingId <= 0) {
    $sqlCreate = "INSERT INTO cobranca_review (
            review_batch_id,
            due_at,
            overdue_days,
            status,
            status_changed_at,
            created_at,
            updated_at
        ) VALUES (
            ?,
            DATE_ADD(CONCAT(?, ' 23:59:59'), INTERVAL 3 DAY),
            0,
            'PENDING',
            NOW(),
            NOW(),
            NOW()
        )";
    $stmtCreate = $conn->prepare($sqlCreate);
    if (!$stmtCreate) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Não foi possível criar cobrança do batch.']);
        exit;
    }
    $stmtCreate->bind_param('is', $reviewBatchId, $current['data_entrega_lote']);
    $stmtCreate->execute();
    $stmtCreate->close();
}

try {
    $conn->begin_transaction();

    if ($action === 'notify') {
        $sql = "UPDATE cobranca_review
                SET status = 'NOTIFIED',
                    notification_count = notification_count + 1,
                    last_notification_at = NOW(),
                    overdue_days = CASE WHEN due_at < NOW() THEN GREATEST(DATEDIFF(CURDATE(), DATE(due_at)), 0) ELSE 0 END,
                    snooze_until = NULL,
                    status_changed_at = NOW(),
                    status_changed_by = ?,
                    last_action_note = ?
                WHERE review_batch_id = ?
                  AND status NOT IN ('RESOLVED', 'IGNORED')";
        $stmt = $conn->prepare($sql);
                $noteValue = $note !== '' ? substr($note, 0, 255) : 'Cobrança manual registrada.';
        $stmt->bind_param('isi', $actorUserId, $noteValue, $reviewBatchId);
        $stmt->execute();
        $stmt->close();

        $stmtBatch = $conn->prepare("UPDATE review_batch SET status = 'NOTIFIED', batch_active_slot = 1, updated_at = NOW() WHERE id = ? AND status NOT IN ('RESOLVED', 'IGNORED')");
        $stmtBatch->bind_param('i', $reviewBatchId);
        $stmtBatch->execute();
        $stmtBatch->close();
    }

    if ($action === 'snooze') {
        $parsedSnooze = strtotime($snoozeUntil);
        if ($parsedSnooze === false) {
            throw new RuntimeException('snooze_until inválido.');
        }
        $snoozeAt = date('Y-m-d H:i:s', $parsedSnooze);
        if ($snoozeAt <= date('Y-m-d H:i:s')) {
            throw new RuntimeException('snooze_until precisa estar no futuro.');
        }

        $sql = "UPDATE cobranca_review
                SET status = 'SNOOZED',
                    snooze_until = ?,
                    status_changed_at = NOW(),
                    status_changed_by = ?,
                    last_action_note = ?
                WHERE review_batch_id = ?
                  AND status NOT IN ('RESOLVED', 'IGNORED')";
        $stmt = $conn->prepare($sql);
                $noteValue = $note !== '' ? substr($note, 0, 255) : 'Cobrança pausada manualmente.';
        $stmt->bind_param('sisi', $snoozeAt, $actorUserId, $noteValue, $reviewBatchId);
        $stmt->execute();
        $stmt->close();

        $stmtBatch = $conn->prepare("UPDATE review_batch SET status = 'SNOOZED', batch_active_slot = 1, updated_at = NOW() WHERE id = ? AND status NOT IN ('RESOLVED', 'IGNORED')");
        $stmtBatch->bind_param('i', $reviewBatchId);
        $stmtBatch->execute();
        $stmtBatch->close();
    }

    if ($action === 'resolve') {
        $resolvedReason = $reason !== '' ? substr($reason, 0, 255) : 'MANUAL_RESOLVED';
        $noteValue = $note !== '' ? substr($note, 0, 255) : 'Batch resolvido manualmente.';

        $sql = "UPDATE cobranca_review
                SET status = 'RESOLVED',
                    resolved_at = NOW(),
                    resolved_reason = ?,
                    snooze_until = NULL,
                    status_changed_at = NOW(),
                    status_changed_by = ?,
                    last_action_note = ?
                WHERE review_batch_id = ?
                  AND status <> 'IGNORED'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sisi', $resolvedReason, $actorUserId, $noteValue, $reviewBatchId);
        $stmt->execute();
        $stmt->close();

        $stmtBatch = $conn->prepare("UPDATE review_batch SET status = 'RESOLVED', batch_active_slot = NULL, updated_at = NOW() WHERE id = ? AND status <> 'IGNORED'");
        $stmtBatch->bind_param('i', $reviewBatchId);
        $stmtBatch->execute();
        $stmtBatch->close();
    }

    if ($action === 'ignore') {
        $resolvedReason = $reason !== '' ? substr($reason, 0, 255) : 'IGNORED_MANUALLY';
        $noteValue = $note !== '' ? substr($note, 0, 255) : 'Batch ignorado manualmente.';

        $sql = "UPDATE cobranca_review
                SET status = 'IGNORED',
                    resolved_at = NOW(),
                    resolved_reason = ?,
                    snooze_until = NULL,
                    status_changed_at = NOW(),
                    status_changed_by = ?,
                    last_action_note = ?
                WHERE review_batch_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sisi', $resolvedReason, $actorUserId, $noteValue, $reviewBatchId);
        $stmt->execute();
        $stmt->close();

        $stmtBatch = $conn->prepare("UPDATE review_batch SET status = 'IGNORED', batch_active_slot = NULL, updated_at = NOW() WHERE id = ?");
        $stmtBatch->bind_param('i', $reviewBatchId);
        $stmtBatch->execute();
        $stmtBatch->close();
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'data' => entregas_review_fetch_batch($conn, $reviewBatchId),
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
