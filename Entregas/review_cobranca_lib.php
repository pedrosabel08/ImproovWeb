<?php

function entregas_review_schema_ready(mysqli $conn): bool
{
    static $ready = null;

    if ($ready !== null) {
        return $ready;
    }

    $sql = "SELECT COUNT(*) AS cnt
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name IN ('review_batch', 'review_batch_items', 'cobranca_review')";

    $res = $conn->query($sql);
    $row = $res ? $res->fetch_assoc() : null;
    $ready = ((int) ($row['cnt'] ?? 0)) === 3;

    return $ready;
}

function entregas_review_allowed_actions(string $status): array
{
    $status = strtoupper(trim($status));

    if ($status === 'RESOLVED' || $status === 'IGNORED') {
        return [];
    }

    if ($status === 'SNOOZED') {
        return ['notify', 'resolve', 'ignore'];
    }

    return ['notify', 'snooze', 'resolve', 'ignore'];
}

function entregas_review_severity(int $overdueCount, int $maxOverdueDays, int $totalBatches): string
{
    if ($overdueCount > 0 && $maxOverdueDays >= 7) {
        return 'critical';
    }

    if ($overdueCount > 0) {
        return 'warning';
    }

    if ($totalBatches > 0) {
        return 'pending';
    }

    return 'none';
}

function entregas_review_fetch_entrega_summary(mysqli $conn, array $entregaIds): array
{
    if (!entregas_review_schema_ready($conn)) {
        return [];
    }

    $entregaIds = array_values(array_unique(array_map('intval', $entregaIds)));
    $entregaIds = array_filter($entregaIds, static function ($id) {
        return $id > 0;
    });

    if (empty($entregaIds)) {
        return [];
    }

    $idList = implode(',', $entregaIds);

    $sql = "SELECT
                rb.entrega_id,
                COUNT(*) AS total_batches,
                SUM(CASE WHEN cr.status IN ('OVERDUE', 'NOTIFIED') THEN 1 ELSE 0 END) AS overdue_batches,
                SUM(CASE WHEN cr.status = 'PENDING' THEN 1 ELSE 0 END) AS pending_batches,
                SUM(CASE WHEN cr.status = 'SNOOZED' THEN 1 ELSE 0 END) AS snoozed_batches,
                MAX(CASE WHEN cr.status IN ('OVERDUE', 'NOTIFIED') THEN cr.overdue_days ELSE 0 END) AS max_overdue_days
            FROM review_batch rb
            INNER JOIN cobranca_review cr ON cr.review_batch_id = rb.id
            WHERE rb.entrega_id IN ($idList)
              AND rb.status IN ('OPEN', 'OVERDUE', 'NOTIFIED', 'SNOOZED')
            GROUP BY rb.entrega_id";

    $res = $conn->query($sql);
    if (!$res) {
        return [];
    }

    $summary = [];
    while ($row = $res->fetch_assoc()) {
        $entregaId = (int) $row['entrega_id'];
        $totalBatches = (int) ($row['total_batches'] ?? 0);
        $overdueBatches = (int) ($row['overdue_batches'] ?? 0);
        $maxOverdueDays = (int) ($row['max_overdue_days'] ?? 0);

        $summary[$entregaId] = [
            'review_batches_total' => $totalBatches,
            'review_batches_overdue' => $overdueBatches,
            'review_batches_pending' => (int) ($row['pending_batches'] ?? 0),
            'review_batches_snoozed' => (int) ($row['snoozed_batches'] ?? 0),
            'review_batches_max_overdue_days' => $maxOverdueDays,
            'review_badge_severity' => entregas_review_severity($overdueBatches, $maxOverdueDays, $totalBatches),
        ];
    }

    return $summary;
}

function entregas_review_fetch_batches_for_entrega(mysqli $conn, int $entregaId): array
{
    if ($entregaId <= 0 || !entregas_review_schema_ready($conn)) {
        return [];
    }

    $sql = "SELECT
                rb.id,
                rb.entrega_id,
                rb.data_entrega_lote,
                rb.review_round,
                rb.status AS batch_status,
                rb.created_at,
                rb.updated_at,
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
                cr.status_changed_at,
                cr.status_changed_by,
                COUNT(rbi.id) AS total_items,
                SUM(CASE WHEN rbi.left_rvw_at IS NULL THEN 1 ELSE 0 END) AS active_items
            FROM review_batch rb
            LEFT JOIN review_batch_items rbi ON rbi.review_batch_id = rb.id
            LEFT JOIN cobranca_review cr ON cr.review_batch_id = rb.id
            WHERE rb.entrega_id = ?
            GROUP BY
                rb.id,
                rb.entrega_id,
                rb.data_entrega_lote,
                rb.review_round,
                rb.status,
                rb.created_at,
                rb.updated_at,
                cr.id,
                cr.due_at,
                cr.overdue_days,
                cr.status,
                cr.notification_count,
                cr.last_notification_at,
                cr.snooze_until,
                cr.resolved_at,
                cr.resolved_reason,
                cr.last_action_note,
                cr.status_changed_at,
                cr.status_changed_by
            ORDER BY rb.data_entrega_lote DESC, rb.review_round DESC, rb.id DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $entregaId);
    $stmt->execute();
    $res = $stmt->get_result();

    $batches = [];
    while ($row = $res->fetch_assoc()) {
        $billingStatus = (string) ($row['billing_status'] ?? $row['batch_status'] ?? 'PENDING');
        $row['id'] = (int) $row['id'];
        $row['entrega_id'] = (int) $row['entrega_id'];
        $row['review_round'] = (int) $row['review_round'];
        $row['cobranca_id'] = isset($row['cobranca_id']) ? (int) $row['cobranca_id'] : null;
        $row['overdue_days'] = (int) ($row['overdue_days'] ?? 0);
        $row['notification_count'] = (int) ($row['notification_count'] ?? 0);
        $row['total_items'] = (int) ($row['total_items'] ?? 0);
        $row['active_items'] = (int) ($row['active_items'] ?? 0);
        $row['allowed_actions'] = entregas_review_allowed_actions($billingStatus);
        $row['severity'] = entregas_review_severity(
            in_array($billingStatus, ['OVERDUE', 'NOTIFIED'], true) ? 1 : 0,
            (int) ($row['overdue_days'] ?? 0),
            1
        );
        $batches[] = $row;
    }

    $stmt->close();

    return $batches;
}

function entregas_review_fetch_batch(mysqli $conn, int $batchId): ?array
{
    if ($batchId <= 0 || !entregas_review_schema_ready($conn)) {
        return null;
    }

    $sqlBatch = "SELECT
                    rb.id,
                    rb.entrega_id,
                    rb.data_entrega_lote,
                    rb.review_round,
                    rb.status AS batch_status,
                    rb.created_at,
                    rb.updated_at,
                    e.obra_id,
                    o.nomenclatura,
                    s.nome_status AS nome_etapa,
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
                    cr.status_changed_at,
                    cr.status_changed_by
                 FROM review_batch rb
                 INNER JOIN entregas e ON e.id = rb.entrega_id
                 INNER JOIN obra o ON o.idobra = e.obra_id
                 INNER JOIN status_imagem s ON s.idstatus = e.status_id
                 LEFT JOIN cobranca_review cr ON cr.review_batch_id = rb.id
                 WHERE rb.id = ?
                 LIMIT 1";

    $stmtBatch = $conn->prepare($sqlBatch);
    if (!$stmtBatch) {
        return null;
    }

    $stmtBatch->bind_param('i', $batchId);
    $stmtBatch->execute();
    $batch = $stmtBatch->get_result()->fetch_assoc();
    $stmtBatch->close();

    if (!$batch) {
        return null;
    }

    $batch['id'] = (int) $batch['id'];
    $batch['entrega_id'] = (int) $batch['entrega_id'];
    $batch['obra_id'] = (int) $batch['obra_id'];
    $batch['review_round'] = (int) $batch['review_round'];
    $batch['cobranca_id'] = isset($batch['cobranca_id']) ? (int) $batch['cobranca_id'] : null;
    $batch['overdue_days'] = (int) ($batch['overdue_days'] ?? 0);
    $batch['notification_count'] = (int) ($batch['notification_count'] ?? 0);

    $sqlItems = "SELECT
                    rbi.id,
                    rbi.review_batch_id,
                    rbi.entrega_item_id,
                    rbi.imagem_id,
                    rbi.entered_rvw_at,
                    rbi.left_rvw_at,
                    ei.status AS entrega_item_status,
                    ei.data_entregue,
                    ico.imagem_nome,
                    ico.substatus_id,
                    ss.nome_substatus
                 FROM review_batch_items rbi
                 INNER JOIN entregas_itens ei ON ei.id = rbi.entrega_item_id
                 INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = rbi.imagem_id
                 LEFT JOIN substatus_imagem ss ON ss.id = ico.substatus_id
                 WHERE rbi.review_batch_id = ?
                 ORDER BY
                    CASE WHEN rbi.left_rvw_at IS NULL THEN 0 ELSE 1 END ASC,
                    ico.imagem_nome ASC,
                    rbi.id ASC";

    $stmtItems = $conn->prepare($sqlItems);
    if (!$stmtItems) {
        $batch['items'] = [];
        $batch['allowed_actions'] = entregas_review_allowed_actions((string) ($batch['billing_status'] ?? $batch['batch_status'] ?? 'PENDING'));
        return $batch;
    }

    $stmtItems->bind_param('i', $batchId);
    $stmtItems->execute();
    $resItems = $stmtItems->get_result();

    $items = [];
    while ($row = $resItems->fetch_assoc()) {
        $row['id'] = (int) $row['id'];
        $row['review_batch_id'] = (int) $row['review_batch_id'];
        $row['entrega_item_id'] = (int) $row['entrega_item_id'];
        $row['imagem_id'] = (int) $row['imagem_id'];
        $row['substatus_id'] = isset($row['substatus_id']) ? (int) $row['substatus_id'] : null;
        $row['is_active'] = $row['left_rvw_at'] === null;
        $items[] = $row;
    }

    $stmtItems->close();

    $batch['items'] = $items;
    $batch['total_items'] = count($items);
    $batch['active_items'] = count(array_filter($items, static function ($item) {
        return !empty($item['is_active']);
    }));
    $batch['allowed_actions'] = entregas_review_allowed_actions((string) ($batch['billing_status'] ?? $batch['batch_status'] ?? 'PENDING'));

    return $batch;
}
