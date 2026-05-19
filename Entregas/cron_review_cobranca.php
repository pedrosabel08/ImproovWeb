<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/review_cobranca_lib.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    require_once __DIR__ . '/../config/session_bootstrap.php';
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado.']);
        exit;
    }
}

if (!entregas_review_schema_ready($conn)) {
    http_response_code(412);
    echo json_encode([
        'success' => false,
        'error' => 'Estrutura de review/cobrança ainda não instalada.',
    ]);
    exit;
}

$stats = [
    'billing_rows_created' => 0,
    'pending_to_overdue' => 0,
    'snoozed_to_overdue' => 0,
    'overdue_recalculated' => 0,
    'pending_reset' => 0,
];

try {
    $conn->begin_transaction();

    $sqlCreateMissing = "INSERT INTO cobranca_review (
            review_batch_id,
            due_at,
            overdue_days,
            status,
            status_changed_at,
            created_at,
            updated_at
        )
        SELECT
            rb.id,
            DATE_ADD(CONCAT(rb.data_entrega_lote, ' 23:59:59'), INTERVAL 3 DAY),
            CASE
                WHEN DATE_ADD(CONCAT(rb.data_entrega_lote, ' 23:59:59'), INTERVAL 3 DAY) < NOW()
                    THEN GREATEST(DATEDIFF(CURDATE(), DATE(DATE_ADD(CONCAT(rb.data_entrega_lote, ' 23:59:59'), INTERVAL 3 DAY))), 0)
                ELSE 0
            END,
            CASE
                WHEN DATE_ADD(CONCAT(rb.data_entrega_lote, ' 23:59:59'), INTERVAL 3 DAY) < NOW()
                    THEN 'OVERDUE'
                ELSE 'PENDING'
            END,
            NOW(),
            NOW(),
            NOW()
        FROM review_batch rb
        LEFT JOIN cobranca_review cr ON cr.review_batch_id = rb.id
        WHERE cr.id IS NULL";
    $conn->query($sqlCreateMissing);
    $stats['billing_rows_created'] = (int) $conn->affected_rows;

    $sqlSnoozed = "UPDATE cobranca_review cr
        INNER JOIN review_batch rb ON rb.id = cr.review_batch_id
        SET cr.status = 'OVERDUE',
            cr.overdue_days = GREATEST(DATEDIFF(CURDATE(), DATE(cr.due_at)), 0),
            cr.status_changed_at = NOW(),
            cr.updated_at = NOW(),
            rb.status = 'OVERDUE',
            rb.batch_active_slot = 1,
            rb.updated_at = NOW()
        WHERE cr.status = 'SNOOZED'
          AND cr.snooze_until IS NOT NULL
          AND cr.snooze_until < NOW()
          AND cr.resolved_at IS NULL
          AND rb.status <> 'IGNORED'";
    $conn->query($sqlSnoozed);
    $stats['snoozed_to_overdue'] = (int) $conn->affected_rows;

    $sqlPending = "UPDATE cobranca_review cr
        INNER JOIN review_batch rb ON rb.id = cr.review_batch_id
        SET cr.status = 'OVERDUE',
            cr.overdue_days = GREATEST(DATEDIFF(CURDATE(), DATE(cr.due_at)), 0),
            cr.status_changed_at = NOW(),
            cr.updated_at = NOW(),
            rb.status = 'OVERDUE',
            rb.batch_active_slot = 1,
            rb.updated_at = NOW()
        WHERE cr.status = 'PENDING'
          AND cr.due_at < NOW()
          AND (cr.snooze_until IS NULL OR cr.snooze_until < NOW())
          AND cr.resolved_at IS NULL
          AND rb.status NOT IN ('IGNORED', 'RESOLVED')";
    $conn->query($sqlPending);
    $stats['pending_to_overdue'] = (int) $conn->affected_rows;

    $sqlOverdue = "UPDATE cobranca_review cr
        INNER JOIN review_batch rb ON rb.id = cr.review_batch_id
        SET cr.overdue_days = GREATEST(DATEDIFF(CURDATE(), DATE(cr.due_at)), 0),
            cr.updated_at = NOW(),
            rb.updated_at = NOW()
        WHERE cr.status IN ('OVERDUE', 'NOTIFIED')
          AND cr.resolved_at IS NULL
          AND rb.status NOT IN ('IGNORED', 'RESOLVED')";
    $conn->query($sqlOverdue);
    $stats['overdue_recalculated'] = (int) $conn->affected_rows;

    $sqlResetPending = "UPDATE cobranca_review cr
        INNER JOIN review_batch rb ON rb.id = cr.review_batch_id
        SET cr.overdue_days = 0,
            cr.updated_at = NOW(),
            rb.status = 'OPEN',
            rb.batch_active_slot = 1,
            rb.updated_at = NOW()
        WHERE cr.status = 'PENDING'
          AND cr.due_at >= NOW()
          AND rb.status = 'OPEN'";
    $conn->query($sqlResetPending);
    $stats['pending_reset'] = (int) $conn->affected_rows;

    $conn->commit();

    echo json_encode([
        'success' => true,
        'mode' => $isCli ? 'cli' : 'http',
        'stats' => $stats,
        'executed_at' => date('Y-m-d H:i:s'),
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
