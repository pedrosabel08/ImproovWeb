<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/review_cobranca_lib.php';

if (!entregas_review_schema_ready($conn)) {
    echo json_encode([
        'success' => true,
        'enabled' => false,
        'data' => [],
    ]);
    exit;
}

$obraId = isset($_GET['obra_id']) && is_numeric($_GET['obra_id']) ? (int) $_GET['obra_id'] : null;
$entregaId = isset($_GET['entrega_id']) && is_numeric($_GET['entrega_id']) ? (int) $_GET['entrega_id'] : null;
$status = isset($_GET['status']) ? strtoupper(trim((string) $_GET['status'])) : null;
$onlyOverdue = isset($_GET['only_overdue']) && (string) $_GET['only_overdue'] === '1';

$allowedStatuses = ['PENDING', 'OVERDUE', 'NOTIFIED', 'SNOOZED', 'RESOLVED', 'IGNORED'];
$conditions = [];

if ($obraId !== null && $obraId > 0) {
    $conditions[] = 'e.obra_id = ' . $obraId;
}
if ($entregaId !== null && $entregaId > 0) {
    $conditions[] = 'rb.entrega_id = ' . $entregaId;
}
if ($status !== null && in_array($status, $allowedStatuses, true)) {
    $conditions[] = "cr.status = '" . $conn->real_escape_string($status) . "'";
}
if ($onlyOverdue) {
    $conditions[] = "cr.status IN ('OVERDUE', 'NOTIFIED')";
}

$where = '';
if (!empty($conditions)) {
    $where = 'WHERE ' . implode(' AND ', $conditions);
}

$sql = "SELECT
            rb.id,
            rb.entrega_id,
            e.obra_id,
            o.nomenclatura,
            s.nome_status AS nome_etapa,
            rb.data_entrega_lote,
            rb.review_round,
            rb.status AS batch_status,
            cr.id AS cobranca_id,
            cr.due_at,
            cr.overdue_days,
            cr.status AS billing_status,
            cr.notification_count,
            cr.last_notification_at,
            cr.snooze_until,
            cr.resolved_at,
            cr.resolved_reason,
            cr.last_action_note,
            COUNT(rbi.id) AS total_items,
            SUM(CASE WHEN rbi.left_rvw_at IS NULL THEN 1 ELSE 0 END) AS active_items
        FROM review_batch rb
        INNER JOIN entregas e ON e.id = rb.entrega_id
        INNER JOIN obra o ON o.idobra = e.obra_id
        INNER JOIN status_imagem s ON s.idstatus = e.status_id
        INNER JOIN cobranca_review cr ON cr.review_batch_id = rb.id
        LEFT JOIN review_batch_items rbi ON rbi.review_batch_id = rb.id
        $where
        GROUP BY
            rb.id,
            rb.entrega_id,
            e.obra_id,
            o.nomenclatura,
            s.nome_status,
            rb.data_entrega_lote,
            rb.review_round,
            rb.status,
            cr.id,
            cr.due_at,
            cr.overdue_days,
            cr.status,
            cr.notification_count,
            cr.last_notification_at,
            cr.snooze_until,
            cr.resolved_at,
            cr.resolved_reason,
            cr.last_action_note
        ORDER BY
            CASE WHEN cr.status IN ('OVERDUE', 'NOTIFIED') THEN 0 ELSE 1 END ASC,
            cr.overdue_days DESC,
            rb.data_entrega_lote DESC,
            rb.review_round DESC,
            rb.id DESC";

$res = $conn->query($sql);
if (!$res) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}

$data = [];
while ($row = $res->fetch_assoc()) {
    $billingStatus = (string) ($row['billing_status'] ?? $row['batch_status'] ?? 'PENDING');
    $row['id'] = (int) $row['id'];
    $row['entrega_id'] = (int) $row['entrega_id'];
    $row['obra_id'] = (int) $row['obra_id'];
    $row['review_round'] = (int) $row['review_round'];
    $row['cobranca_id'] = isset($row['cobranca_id']) ? (int) $row['cobranca_id'] : null;
    $row['overdue_days'] = (int) ($row['overdue_days'] ?? 0);
    $row['notification_count'] = (int) ($row['notification_count'] ?? 0);
    $row['total_items'] = (int) ($row['total_items'] ?? 0);
    $row['active_items'] = (int) ($row['active_items'] ?? 0);
    $row['items'] = entregas_review_fetch_batch_items($conn, $row['id']);
    $row['total_items'] = count($row['items']);
    $row['active_items'] = count(array_filter($row['items'], static function ($item) {
        return !empty($item['is_active']);
    }));
    $row['allowed_actions'] = entregas_review_allowed_actions($billingStatus);
    $row['severity'] = entregas_review_severity(
        in_array($billingStatus, ['OVERDUE', 'NOTIFIED'], true) ? 1 : 0,
        $row['overdue_days'],
        1
    );
    $data[] = $row;
}

echo json_encode([
    'success' => true,
    'enabled' => true,
    'data' => $data,
]);
