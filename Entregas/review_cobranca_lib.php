<?php

require_once __DIR__ . '/p00_delivery_helpers.php';

function entregas_review_schema_ready(mysqli $conn): bool
{
    static $ready = null;

    if ($ready !== null) {
        return $ready;
    }

    improov_p00_ensure_schema($conn);

    $sql = "SELECT COUNT(*) AS cnt
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name IN ('review_batch', 'review_batch_items', 'cobranca_review')";

    $res = $conn->query($sql);
    $row = $res ? $res->fetch_assoc() : null;
    if (((int) ($row['cnt'] ?? 0)) !== 3) {
        $ready = false;
        return $ready;
    }

    entregas_review_schema_ensure_p00_support($conn);

    $ready = entregas_review_table_column_exists($conn, 'review_batch_items', 'p00_versao_id')
        && entregas_review_index_exists($conn, 'review_batch_items', 'ux_review_batch_p00_item_active')
        && entregas_review_fk_exists($conn, 'review_batch_items', 'fk_review_batch_items_p00_versao');

    return $ready;
}

function entregas_review_table_column_exists(mysqli $conn, string $tableName, string $columnName): bool
{
    $sql = "SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function entregas_review_column_is_nullable(mysqli $conn, string $tableName, string $columnName): bool
{
    $sql = "SELECT is_nullable
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return strtoupper((string) ($result['is_nullable'] ?? 'NO')) === 'YES';
}

function entregas_review_index_exists(mysqli $conn, string $tableName, string $indexName): bool
{
    $sql = "SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND index_name = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $tableName, $indexName);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function entregas_review_fk_exists(mysqli $conn, string $tableName, string $constraintName): bool
{
    $sql = "SELECT 1
            FROM information_schema.table_constraints
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND constraint_name = ?
              AND constraint_type = 'FOREIGN KEY'
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $tableName, $constraintName);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function entregas_review_procedure_exists(mysqli $conn, string $procedureName): bool
{
    $sql = "SELECT 1
            FROM information_schema.routines
            WHERE routine_schema = DATABASE()
              AND routine_name = ?
              AND routine_type = 'PROCEDURE'
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $procedureName);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function entregas_review_trigger_contains(mysqli $conn, string $triggerName, string $needle): bool
{
    $sql = "SELECT action_statement
            FROM information_schema.triggers
            WHERE trigger_schema = DATABASE()
              AND trigger_name = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $triggerName);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return stripos((string) ($result['action_statement'] ?? ''), $needle) !== false;
}

function entregas_review_schema_ensure_p00_support(mysqli $conn): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }
    $ensured = true;

    if (!entregas_review_column_is_nullable($conn, 'review_batch_items', 'entrega_item_id')) {
        $conn->query("ALTER TABLE review_batch_items MODIFY entrega_item_id INT NULL");
    }

    if (!entregas_review_table_column_exists($conn, 'review_batch_items', 'p00_versao_id')) {
        $conn->query("ALTER TABLE review_batch_items ADD COLUMN p00_versao_id INT NULL AFTER entrega_item_id");
    }

    if (!entregas_review_index_exists($conn, 'review_batch_items', 'idx_review_batch_items_p00_history')) {
        $conn->query("ALTER TABLE review_batch_items ADD KEY idx_review_batch_items_p00_history (p00_versao_id, entered_rvw_at)");
    }

    if (!entregas_review_index_exists($conn, 'review_batch_items', 'ux_review_batch_p00_item_active')) {
        $conn->query("ALTER TABLE review_batch_items ADD UNIQUE KEY ux_review_batch_p00_item_active (review_batch_id, p00_versao_id, imagem_id, item_active_slot)");
    }

    if (!entregas_review_fk_exists($conn, 'review_batch_items', 'fk_review_batch_items_p00_versao')) {
        $conn->query("ALTER TABLE review_batch_items ADD CONSTRAINT fk_review_batch_items_p00_versao FOREIGN KEY (p00_versao_id) REFERENCES entregas_p00_versoes (id) ON DELETE CASCADE ON UPDATE CASCADE");
    }

}

function entregas_review_schema_refresh_p00_routines(mysqli $conn): void
{
    $conn->query("DROP TRIGGER IF EXISTS trg_review_batch_on_rvw");
    $conn->query("DROP PROCEDURE IF EXISTS sp_review_batch_enter_drv_p00");
    $conn->query("DROP PROCEDURE IF EXISTS sp_review_batch_leave_drv_p00");

    $conn->query(<<<'SQL'
CREATE PROCEDURE sp_review_batch_enter_drv_p00(IN p_imagem_id INT, IN p_changed_at DATETIME)
proc: BEGIN
    DECLARE v_p00_versao_id INT DEFAULT NULL;
    DECLARE v_entrega_id INT DEFAULT NULL;
    DECLARE v_data_entregue DATETIME DEFAULT NULL;
    DECLARE v_data_lote DATE DEFAULT NULL;
    DECLARE v_review_batch_id INT DEFAULT NULL;
    DECLARE v_review_round SMALLINT UNSIGNED DEFAULT 1;
    DECLARE v_due_at DATETIME DEFAULT NULL;
    DECLARE v_active_item_id INT DEFAULT NULL;

    SELECT
        v.id,
        v.entrega_id,
        v.data_entregue,
        DATE(v.data_entregue)
    INTO
        v_p00_versao_id,
        v_entrega_id,
        v_data_entregue,
        v_data_lote
    FROM entregas_p00_versoes v
    INNER JOIN entregas e ON e.id = v.entrega_id
    WHERE v.imagem_id = p_imagem_id
      AND v.data_entregue IS NOT NULL
      AND COALESCE(e.tipo_entrega, 'PADRAO') = 'P00'
    ORDER BY v.data_entregue DESC, v.id DESC
    LIMIT 1;

    IF v_p00_versao_id IS NULL OR v_entrega_id IS NULL OR v_data_lote IS NULL THEN
        LEAVE proc;
    END IF;

    SELECT rb.id
    INTO v_review_batch_id
    FROM review_batch rb
    WHERE rb.entrega_id = v_entrega_id
      AND rb.data_entrega_lote = v_data_lote
      AND rb.status IN ('OPEN', 'OVERDUE', 'NOTIFIED', 'SNOOZED')
    ORDER BY rb.review_round DESC, rb.id DESC
    LIMIT 1
    FOR UPDATE;

    IF v_review_batch_id IS NULL THEN
        SELECT COALESCE(MAX(rb.review_round), 0) + 1
        INTO v_review_round
        FROM review_batch rb
        WHERE rb.entrega_id = v_entrega_id
          AND rb.data_entrega_lote = v_data_lote;

        INSERT INTO review_batch (
            entrega_id,
            data_entrega_lote,
            review_round,
            status
        ) VALUES (
            v_entrega_id,
            v_data_lote,
            v_review_round,
            'OPEN'
        );

        SET v_review_batch_id = LAST_INSERT_ID();
    END IF;

    SELECT rbi.id
    INTO v_active_item_id
    FROM review_batch_items rbi
    WHERE rbi.review_batch_id = v_review_batch_id
      AND rbi.p00_versao_id = v_p00_versao_id
      AND rbi.imagem_id = p_imagem_id
      AND rbi.left_rvw_at IS NULL
    LIMIT 1
    FOR UPDATE;

    IF v_active_item_id IS NULL THEN
        INSERT INTO review_batch_items (
            review_batch_id,
            entrega_item_id,
            p00_versao_id,
            imagem_id,
            entered_rvw_at
        ) VALUES (
            v_review_batch_id,
            NULL,
            v_p00_versao_id,
            p_imagem_id,
            COALESCE(v_data_entregue, p_changed_at)
        );
    END IF;

    SET v_due_at = DATE_ADD(CONCAT(v_data_lote, ' 23:59:59'), INTERVAL 3 DAY);

    INSERT INTO cobranca_review (
        review_batch_id,
        due_at,
        overdue_days,
        status,
        status_changed_at,
        created_at,
        updated_at
    )
    SELECT
        v_review_batch_id,
        v_due_at,
        CASE WHEN v_due_at < COALESCE(p_changed_at, NOW()) THEN GREATEST(DATEDIFF(CURDATE(), DATE(v_due_at)), 0) ELSE 0 END,
        CASE WHEN v_due_at < COALESCE(p_changed_at, NOW()) THEN 'OVERDUE' ELSE 'PENDING' END,
        COALESCE(p_changed_at, NOW()),
        COALESCE(p_changed_at, NOW()),
        COALESCE(p_changed_at, NOW())
    FROM dual
    WHERE NOT EXISTS (
        SELECT 1
        FROM cobranca_review cr
        WHERE cr.review_batch_id = v_review_batch_id
    );

    UPDATE review_batch rb
    INNER JOIN cobranca_review cr ON cr.review_batch_id = rb.id
    SET rb.status = CASE
            WHEN cr.status = 'OVERDUE' THEN 'OVERDUE'
            WHEN cr.status = 'NOTIFIED' THEN 'NOTIFIED'
            WHEN cr.status = 'SNOOZED' THEN 'SNOOZED'
            WHEN cr.status = 'PENDING' THEN 'OPEN'
            WHEN cr.status = 'IGNORED' THEN 'IGNORED'
            WHEN cr.status = 'RESOLVED' THEN 'RESOLVED'
            ELSE rb.status
        END,
        rb.batch_active_slot = CASE
            WHEN cr.status IN ('PENDING', 'OVERDUE', 'NOTIFIED', 'SNOOZED') THEN 1
            ELSE NULL
        END,
        rb.updated_at = COALESCE(p_changed_at, NOW())
    WHERE rb.id = v_review_batch_id;
END
SQL
);

    $conn->query(<<<'SQL'
CREATE PROCEDURE sp_review_batch_leave_drv_p00(IN p_imagem_id INT, IN p_changed_at DATETIME)
proc: BEGIN
    DECLARE v_review_batch_id INT DEFAULT NULL;
    DECLARE v_active_items INT DEFAULT 0;

    SELECT rbi.review_batch_id
    INTO v_review_batch_id
    FROM review_batch_items rbi
    INNER JOIN review_batch rb ON rb.id = rbi.review_batch_id
    WHERE rbi.imagem_id = p_imagem_id
      AND rbi.p00_versao_id IS NOT NULL
      AND rbi.left_rvw_at IS NULL
      AND rb.status IN ('OPEN', 'OVERDUE', 'NOTIFIED', 'SNOOZED')
    ORDER BY rbi.id DESC
    LIMIT 1
    FOR UPDATE;

    IF v_review_batch_id IS NULL THEN
        LEAVE proc;
    END IF;

    UPDATE review_batch_items
    SET left_rvw_at = COALESCE(left_rvw_at, COALESCE(p_changed_at, NOW())),
        item_active_slot = NULL
    WHERE review_batch_id = v_review_batch_id
      AND imagem_id = p_imagem_id
      AND p00_versao_id IS NOT NULL
      AND left_rvw_at IS NULL;

    SELECT COUNT(*)
    INTO v_active_items
    FROM review_batch_items
    WHERE review_batch_id = v_review_batch_id
      AND left_rvw_at IS NULL;

    IF v_active_items = 0 THEN
        UPDATE cobranca_review
        SET status = CASE WHEN status = 'IGNORED' THEN status ELSE 'RESOLVED' END,
            resolved_at = COALESCE(resolved_at, COALESCE(p_changed_at, NOW())),
            resolved_reason = COALESCE(NULLIF(resolved_reason, ''), 'AUTO_EMPTY_BATCH'),
            snooze_until = NULL,
            status_changed_at = COALESCE(p_changed_at, NOW()),
            last_action_note = COALESCE(NULLIF(last_action_note, ''), 'Batch resolvido automaticamente após saída do último item do DRV P00.')
        WHERE review_batch_id = v_review_batch_id
          AND status <> 'IGNORED';

        UPDATE review_batch
        SET status = CASE WHEN status = 'IGNORED' THEN status ELSE 'RESOLVED' END,
            batch_active_slot = NULL,
            updated_at = COALESCE(p_changed_at, NOW())
        WHERE id = v_review_batch_id
          AND status <> 'IGNORED';
    END IF;
END
SQL
);

    $conn->query(<<<'SQL'
CREATE TRIGGER trg_review_batch_on_rvw
AFTER UPDATE ON imagens_cliente_obra
FOR EACH ROW
BEGIN
    IF NEW.substatus_id = 6 AND (OLD.substatus_id IS NULL OR OLD.substatus_id <> 6) THEN
        CALL sp_review_batch_enter_rvw(NEW.idimagens_cliente_obra, NOW());
    END IF;

    IF OLD.substatus_id = 6 AND (NEW.substatus_id IS NULL OR NEW.substatus_id <> 6) THEN
        CALL sp_review_batch_leave_rvw(NEW.idimagens_cliente_obra, NOW());
    END IF;

    IF NEW.status_id = 1
        AND NEW.substatus_id = 9
        AND (
            OLD.status_id IS NULL
            OR OLD.status_id <> 1
            OR OLD.substatus_id IS NULL
            OR OLD.substatus_id <> 9
        ) THEN
        CALL sp_review_batch_enter_drv_p00(NEW.idimagens_cliente_obra, NOW());
    END IF;

    IF OLD.status_id = 1
        AND OLD.substatus_id = 9
        AND (
            NEW.status_id IS NULL
            OR NEW.status_id <> 1
            OR NEW.substatus_id IS NULL
            OR NEW.substatus_id <> 9
        ) THEN
        CALL sp_review_batch_leave_drv_p00(NEW.idimagens_cliente_obra, NOW());
    END IF;
END
SQL
);
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
                SUM(CASE WHEN rbi.p00_versao_id IS NOT NULL THEN 1 ELSE 0 END) AS p00_item_count,
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
        $row['p00_item_count'] = (int) ($row['p00_item_count'] ?? 0);
        $row['total_items'] = (int) ($row['total_items'] ?? 0);
        $row['active_items'] = (int) ($row['active_items'] ?? 0);
        $row['batch_kind'] = $row['p00_item_count'] > 0 ? 'P00' : 'ENTREGA';
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
                    rbi.p00_versao_id,
                    rbi.imagem_id,
                    rbi.entered_rvw_at,
                    rbi.left_rvw_at,
                    COALESCE(ei.status, pv.status) AS entrega_item_status,
                    COALESCE(ei.data_entregue, pv.data_entregue) AS data_entregue,
                    ico.imagem_nome,
                    ico.substatus_id,
                    ss.nome_substatus,
                    CASE
                        WHEN pv.id IS NOT NULL AND pv.arquivo_principal IS NOT NULL AND TRIM(pv.arquivo_principal) <> ''
                            THEN CONCAT(pv.versao_label, ' - ', pv.arquivo_principal)
                        WHEN pv.id IS NOT NULL
                            THEN CONCAT('Versão ', pv.versao_label)
                        ELSE ico.imagem_nome
                    END AS item_nome
                 FROM review_batch_items rbi
                 LEFT JOIN entregas_itens ei ON ei.id = rbi.entrega_item_id
                 LEFT JOIN entregas_p00_versoes pv ON pv.id = rbi.p00_versao_id
                 INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = rbi.imagem_id
                 LEFT JOIN substatus_imagem ss ON ss.id = ico.substatus_id
                 WHERE rbi.review_batch_id = ?
                 ORDER BY
                    CASE WHEN rbi.left_rvw_at IS NULL THEN 0 ELSE 1 END ASC,
                    item_nome ASC,
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
        $row['entrega_item_id'] = isset($row['entrega_item_id']) ? (int) $row['entrega_item_id'] : null;
        $row['p00_versao_id'] = isset($row['p00_versao_id']) ? (int) $row['p00_versao_id'] : null;
        $row['imagem_id'] = (int) $row['imagem_id'];
        $row['substatus_id'] = isset($row['substatus_id']) ? (int) $row['substatus_id'] : null;
        $row['item_kind'] = $row['p00_versao_id'] ? 'P00_VERSAO' : 'ENTREGA_ITEM';
        $row['nome'] = $row['item_nome'] ?? $row['imagem_nome'];
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

function entregas_review_sync_p00_batch_state(mysqli $conn, int $imagemId, ?int $statusId = null, ?int $substatusId = null, ?string $changedAt = null): void
{
    if ($imagemId <= 0 || !entregas_review_schema_ready($conn)) {
        return;
    }

    if ($statusId === null || $substatusId === null) {
        $stmt = $conn->prepare('SELECT status_id, substatus_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ? LIMIT 1');
        if (!$stmt) {
            return;
        }

        $stmt->bind_param('i', $imagemId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return;
        }

        $statusId = isset($row['status_id']) ? (int) $row['status_id'] : 0;
        $substatusId = isset($row['substatus_id']) ? (int) $row['substatus_id'] : 0;
    }

    if ($statusId === 1 && $substatusId === 9) {
        entregas_review_open_p00_batch_for_image($conn, $imagemId, $changedAt);
        return;
    }

    entregas_review_close_p00_batch_for_image($conn, $imagemId, $changedAt);
}

function entregas_review_sync_standard_batch_state(mysqli $conn, int $imagemId, ?int $substatusId = null, ?string $changedAt = null): void
{
    if ($imagemId <= 0 || !entregas_review_schema_ready($conn)) {
        return;
    }

    if ($substatusId === null) {
        $stmt = $conn->prepare('SELECT substatus_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ? LIMIT 1');
        if (!$stmt) {
            return;
        }

        $stmt->bind_param('i', $imagemId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return;
        }

        $substatusId = isset($row['substatus_id']) ? (int) $row['substatus_id'] : 0;
    }

    if ($substatusId === 6) {
        entregas_review_open_standard_batch_for_image($conn, $imagemId, $changedAt);
        return;
    }

    entregas_review_close_standard_batch_for_image($conn, $imagemId, $changedAt);
}

function entregas_review_open_standard_batch_for_image(mysqli $conn, int $imagemId, ?string $changedAt = null): void
{
    $imagemId = (int) $imagemId;
    if ($imagemId <= 0) {
        return;
    }

    $changedAt = $changedAt ?: date('Y-m-d H:i:s');

    $stmtItem = $conn->prepare("SELECT
            ei.id,
            ei.entrega_id,
            ei.data_entregue,
            DATE(ei.data_entregue) AS data_lote
        FROM entregas_itens ei
        INNER JOIN entregas e ON e.id = ei.entrega_id
        WHERE ei.imagem_id = ?
          AND ei.data_entregue IS NOT NULL
          AND COALESCE(e.tipo_entrega, 'PADRAO') <> 'P00'
        ORDER BY ei.data_entregue DESC, ei.id DESC
        LIMIT 1");
    if (!$stmtItem) {
        return;
    }

    $stmtItem->bind_param('i', $imagemId);
    $stmtItem->execute();
    $item = $stmtItem->get_result()->fetch_assoc();
    $stmtItem->close();

    if (!$item) {
        return;
    }

    $entregaId = (int) ($item['entrega_id'] ?? 0);
    $entregaItemId = (int) ($item['id'] ?? 0);
    $dataLote = (string) ($item['data_lote'] ?? '');
    $dataEntregue = (string) ($item['data_entregue'] ?? $changedAt);

    if ($entregaId <= 0 || $entregaItemId <= 0 || $dataLote === '') {
        return;
    }

    $batchId = 0;
    $stmtBatch = $conn->prepare("SELECT id
        FROM review_batch
        WHERE entrega_id = ?
          AND data_entrega_lote = ?
          AND status IN ('OPEN', 'OVERDUE', 'NOTIFIED', 'SNOOZED')
        ORDER BY review_round DESC, id DESC
        LIMIT 1");
    if ($stmtBatch) {
        $stmtBatch->bind_param('is', $entregaId, $dataLote);
        $stmtBatch->execute();
        $found = $stmtBatch->get_result()->fetch_assoc();
        $stmtBatch->close();
        if ($found) {
            $batchId = (int) ($found['id'] ?? 0);
        }
    }

    if ($batchId <= 0) {
        $reviewRound = 1;
        $stmtRound = $conn->prepare('SELECT COALESCE(MAX(review_round), 0) + 1 AS next_round FROM review_batch WHERE entrega_id = ? AND data_entrega_lote = ?');
        if ($stmtRound) {
            $stmtRound->bind_param('is', $entregaId, $dataLote);
            $stmtRound->execute();
            $roundRow = $stmtRound->get_result()->fetch_assoc();
            $stmtRound->close();
            $reviewRound = (int) ($roundRow['next_round'] ?? 1);
        }

        $stmtInsertBatch = $conn->prepare("INSERT INTO review_batch (entrega_id, data_entrega_lote, review_round, status, batch_active_slot, created_at, updated_at)
            VALUES (?, ?, ?, 'OPEN', 1, ?, ?)");
        if (!$stmtInsertBatch) {
            return;
        }

        $stmtInsertBatch->bind_param('isiss', $entregaId, $dataLote, $reviewRound, $changedAt, $changedAt);
        if (!$stmtInsertBatch->execute()) {
            $stmtInsertBatch->close();
            return;
        }
        $batchId = (int) $conn->insert_id;
        $stmtInsertBatch->close();
    }

    $stmtActive = $conn->prepare('SELECT id FROM review_batch_items WHERE review_batch_id = ? AND entrega_item_id = ? AND imagem_id = ? AND left_rvw_at IS NULL LIMIT 1');
    if ($stmtActive) {
        $stmtActive->bind_param('iii', $batchId, $entregaItemId, $imagemId);
        $stmtActive->execute();
        $activeRow = $stmtActive->get_result()->fetch_assoc();
        $stmtActive->close();
        if ($activeRow) {
            entregas_review_sync_batch_billing($conn, $batchId, $dataLote, $changedAt);
            return;
        }
    }

    $enteredAt = $dataEntregue !== '' ? $dataEntregue : $changedAt;
    $stmtInsertItem = $conn->prepare('INSERT INTO review_batch_items (review_batch_id, entrega_item_id, imagem_id, entered_rvw_at) VALUES (?, ?, ?, ?)');
    if (!$stmtInsertItem) {
        return;
    }

    $stmtInsertItem->bind_param('iiis', $batchId, $entregaItemId, $imagemId, $enteredAt);
    $stmtInsertItem->execute();
    $stmtInsertItem->close();

    entregas_review_sync_batch_billing($conn, $batchId, $dataLote, $changedAt);
}

function entregas_review_close_standard_batch_for_image(mysqli $conn, int $imagemId, ?string $changedAt = null): void
{
    $imagemId = (int) $imagemId;
    if ($imagemId <= 0) {
        return;
    }

    $changedAt = $changedAt ?: date('Y-m-d H:i:s');

    $stmtBatch = $conn->prepare("SELECT rbi.review_batch_id
        FROM review_batch_items rbi
        INNER JOIN review_batch rb ON rb.id = rbi.review_batch_id
        WHERE rbi.imagem_id = ?
          AND rbi.p00_versao_id IS NULL
          AND rbi.left_rvw_at IS NULL
          AND rb.status IN ('OPEN', 'OVERDUE', 'NOTIFIED', 'SNOOZED')
        ORDER BY rbi.id DESC
        LIMIT 1");
    if (!$stmtBatch) {
        return;
    }

    $stmtBatch->bind_param('i', $imagemId);
    $stmtBatch->execute();
    $batchRow = $stmtBatch->get_result()->fetch_assoc();
    $stmtBatch->close();

    $batchId = isset($batchRow['review_batch_id']) ? (int) $batchRow['review_batch_id'] : 0;
    if ($batchId <= 0) {
        return;
    }

    $stmtUpdate = $conn->prepare("UPDATE review_batch_items
        SET left_rvw_at = COALESCE(left_rvw_at, ?),
            item_active_slot = NULL
        WHERE review_batch_id = ?
          AND imagem_id = ?
          AND p00_versao_id IS NULL
          AND left_rvw_at IS NULL");
    if (!$stmtUpdate) {
        return;
    }

    $stmtUpdate->bind_param('sii', $changedAt, $batchId, $imagemId);
    $stmtUpdate->execute();
    $stmtUpdate->close();

    $stmtCount = $conn->prepare('SELECT COUNT(*) AS active_items FROM review_batch_items WHERE review_batch_id = ? AND left_rvw_at IS NULL');
    if (!$stmtCount) {
        return;
    }

    $stmtCount->bind_param('i', $batchId);
    $stmtCount->execute();
    $countRow = $stmtCount->get_result()->fetch_assoc();
    $stmtCount->close();

    if ((int) ($countRow['active_items'] ?? 0) !== 0) {
        return;
    }

    $stmtBilling = $conn->prepare("UPDATE cobranca_review
        SET status = CASE WHEN status = 'IGNORED' THEN status ELSE 'RESOLVED' END,
            resolved_at = COALESCE(resolved_at, ?),
            resolved_reason = COALESCE(NULLIF(resolved_reason, ''), 'AUTO_EMPTY_BATCH'),
            snooze_until = NULL,
            status_changed_at = ?,
            last_action_note = COALESCE(NULLIF(last_action_note, ''), 'Batch resolvido automaticamente apos saida do ultimo item do RVW.')
        WHERE review_batch_id = ?
          AND status <> 'IGNORED'");
    if ($stmtBilling) {
        $stmtBilling->bind_param('ssi', $changedAt, $changedAt, $batchId);
        $stmtBilling->execute();
        $stmtBilling->close();
    }

    $stmtBatchResolve = $conn->prepare("UPDATE review_batch
        SET status = CASE WHEN status = 'IGNORED' THEN status ELSE 'RESOLVED' END,
            batch_active_slot = NULL,
            updated_at = ?
        WHERE id = ?
          AND status <> 'IGNORED'");
    if ($stmtBatchResolve) {
        $stmtBatchResolve->bind_param('si', $changedAt, $batchId);
        $stmtBatchResolve->execute();
        $stmtBatchResolve->close();
    }
}

function entregas_review_open_p00_batch_for_image(mysqli $conn, int $imagemId, ?string $changedAt = null): void
{
    $imagemId = (int) $imagemId;
    if ($imagemId <= 0) {
        return;
    }

    $changedAt = $changedAt ?: date('Y-m-d H:i:s');
    $changedAtSql = $conn->real_escape_string($changedAt);

    $sqlVersion = "SELECT
            v.id,
            v.entrega_id,
            v.data_entregue,
            DATE(v.data_entregue) AS data_lote
        FROM entregas_p00_versoes v
        INNER JOIN entregas e ON e.id = v.entrega_id
        WHERE v.imagem_id = $imagemId
          AND v.data_entregue IS NOT NULL
                    AND v.status IN ('Entregue no prazo', 'Entregue com atraso', 'Entrega antecipada')
          AND COALESCE(e.tipo_entrega, 'PADRAO') = 'P00'
        ORDER BY v.data_entregue DESC, v.id DESC
        LIMIT 1";
    $versionRow = $conn->query($sqlVersion);
    $version = $versionRow ? $versionRow->fetch_assoc() : null;
    if (!$version) {
        return;
    }

    $entregaId = (int) ($version['entrega_id'] ?? 0);
    $versaoId = (int) ($version['id'] ?? 0);
    $dataLote = (string) ($version['data_lote'] ?? '');
    $dataEntregue = (string) ($version['data_entregue'] ?? $changedAt);

    if ($entregaId <= 0 || $versaoId <= 0 || $dataLote === '') {
        return;
    }

    $batchId = 0;
    $stmtBatch = $conn->prepare("SELECT id
        FROM review_batch
        WHERE entrega_id = ?
          AND data_entrega_lote = ?
          AND status IN ('OPEN', 'OVERDUE', 'NOTIFIED', 'SNOOZED')
        ORDER BY review_round DESC, id DESC
        LIMIT 1");
    if ($stmtBatch) {
        $stmtBatch->bind_param('is', $entregaId, $dataLote);
        $stmtBatch->execute();
        $found = $stmtBatch->get_result()->fetch_assoc();
        $stmtBatch->close();
        if ($found) {
            $batchId = (int) ($found['id'] ?? 0);
        }
    }

    if ($batchId <= 0) {
        $reviewRound = 1;
        $stmtRound = $conn->prepare('SELECT COALESCE(MAX(review_round), 0) + 1 AS next_round FROM review_batch WHERE entrega_id = ? AND data_entrega_lote = ?');
        if ($stmtRound) {
            $stmtRound->bind_param('is', $entregaId, $dataLote);
            $stmtRound->execute();
            $roundRow = $stmtRound->get_result()->fetch_assoc();
            $stmtRound->close();
            $reviewRound = (int) ($roundRow['next_round'] ?? 1);
        }

        $stmtInsertBatch = $conn->prepare("INSERT INTO review_batch (entrega_id, data_entrega_lote, review_round, status, batch_active_slot, created_at, updated_at)
            VALUES (?, ?, ?, 'OPEN', 1, ?, ?)");
        if (!$stmtInsertBatch) {
            return;
        }

        $stmtInsertBatch->bind_param('isiss', $entregaId, $dataLote, $reviewRound, $changedAtSql, $changedAtSql);
        if (!$stmtInsertBatch->execute()) {
            $stmtInsertBatch->close();
            return;
        }
        $batchId = (int) $conn->insert_id;
        $stmtInsertBatch->close();
    }

    $stmtActive = $conn->prepare('SELECT id FROM review_batch_items WHERE review_batch_id = ? AND p00_versao_id = ? AND imagem_id = ? AND left_rvw_at IS NULL LIMIT 1');
    if ($stmtActive) {
        $stmtActive->bind_param('iii', $batchId, $versaoId, $imagemId);
        $stmtActive->execute();
        $activeRow = $stmtActive->get_result()->fetch_assoc();
        $stmtActive->close();
        if ($activeRow) {
            entregas_review_sync_batch_billing($conn, $batchId, $dataLote, $changedAt);
            return;
        }
    }

    $enteredAt = $dataEntregue !== '' ? $dataEntregue : $changedAt;
    $stmtInsertItem = $conn->prepare('INSERT INTO review_batch_items (review_batch_id, entrega_item_id, p00_versao_id, imagem_id, entered_rvw_at) VALUES (?, NULL, ?, ?, ?)');
    if (!$stmtInsertItem) {
        return;
    }

    $stmtInsertItem->bind_param('iiis', $batchId, $versaoId, $imagemId, $enteredAt);
    $stmtInsertItem->execute();
    $stmtInsertItem->close();

    entregas_review_sync_batch_billing($conn, $batchId, $dataLote, $changedAt);
}

function entregas_review_close_p00_batch_for_image(mysqli $conn, int $imagemId, ?string $changedAt = null): void
{
    $imagemId = (int) $imagemId;
    if ($imagemId <= 0) {
        return;
    }

    $changedAt = $changedAt ?: date('Y-m-d H:i:s');

    $stmtBatch = $conn->prepare("SELECT rbi.review_batch_id
        FROM review_batch_items rbi
        INNER JOIN review_batch rb ON rb.id = rbi.review_batch_id
        WHERE rbi.imagem_id = ?
          AND rbi.p00_versao_id IS NOT NULL
          AND rbi.left_rvw_at IS NULL
          AND rb.status IN ('OPEN', 'OVERDUE', 'NOTIFIED', 'SNOOZED')
        ORDER BY rbi.id DESC
        LIMIT 1");
    if (!$stmtBatch) {
        return;
    }

    $stmtBatch->bind_param('i', $imagemId);
    $stmtBatch->execute();
    $batchRow = $stmtBatch->get_result()->fetch_assoc();
    $stmtBatch->close();

    $batchId = isset($batchRow['review_batch_id']) ? (int) $batchRow['review_batch_id'] : 0;
    if ($batchId <= 0) {
        return;
    }

    $stmtUpdate = $conn->prepare("UPDATE review_batch_items
        SET left_rvw_at = COALESCE(left_rvw_at, ?),
            item_active_slot = NULL
        WHERE review_batch_id = ?
          AND imagem_id = ?
          AND p00_versao_id IS NOT NULL
          AND left_rvw_at IS NULL");
    if (!$stmtUpdate) {
        return;
    }

    $stmtUpdate->bind_param('sii', $changedAt, $batchId, $imagemId);
    $stmtUpdate->execute();
    $stmtUpdate->close();

    $stmtCount = $conn->prepare('SELECT COUNT(*) AS active_items FROM review_batch_items WHERE review_batch_id = ? AND left_rvw_at IS NULL');
    if (!$stmtCount) {
        return;
    }

    $stmtCount->bind_param('i', $batchId);
    $stmtCount->execute();
    $countRow = $stmtCount->get_result()->fetch_assoc();
    $stmtCount->close();

    if ((int) ($countRow['active_items'] ?? 0) !== 0) {
        return;
    }

    $stmtBilling = $conn->prepare("UPDATE cobranca_review
        SET status = CASE WHEN status = 'IGNORED' THEN status ELSE 'RESOLVED' END,
            resolved_at = COALESCE(resolved_at, ?),
            resolved_reason = COALESCE(NULLIF(resolved_reason, ''), 'AUTO_EMPTY_BATCH'),
            snooze_until = NULL,
            status_changed_at = ?,
            last_action_note = COALESCE(NULLIF(last_action_note, ''), 'Batch resolvido automaticamente após saída do último item do DRV P00.')
        WHERE review_batch_id = ?
          AND status <> 'IGNORED'");
    if ($stmtBilling) {
        $stmtBilling->bind_param('ssi', $changedAt, $changedAt, $batchId);
        $stmtBilling->execute();
        $stmtBilling->close();
    }

    $stmtBatchResolve = $conn->prepare("UPDATE review_batch
        SET status = CASE WHEN status = 'IGNORED' THEN status ELSE 'RESOLVED' END,
            batch_active_slot = NULL,
            updated_at = ?
        WHERE id = ?
          AND status <> 'IGNORED'");
    if ($stmtBatchResolve) {
        $stmtBatchResolve->bind_param('si', $changedAt, $batchId);
        $stmtBatchResolve->execute();
        $stmtBatchResolve->close();
    }
}

function entregas_review_sync_batch_billing(mysqli $conn, int $batchId, string $dataLote, ?string $changedAt = null): void
{
    if ($batchId <= 0 || $dataLote === '') {
        return;
    }

    $changedAt = $changedAt ?: date('Y-m-d H:i:s');
    $dueAt = date('Y-m-d H:i:s', strtotime($dataLote . ' 23:59:59 +3 days'));
    $status = (strtotime($dueAt) < strtotime($changedAt)) ? 'OVERDUE' : 'PENDING';
    $overdueDays = $status === 'OVERDUE'
        ? max((int) floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', strtotime($dueAt)))) / 86400), 0)
        : 0;

    $stmtExisting = $conn->prepare('SELECT id, status FROM cobranca_review WHERE review_batch_id = ? LIMIT 1');
    if (!$stmtExisting) {
        return;
    }

    $stmtExisting->bind_param('i', $batchId);
    $stmtExisting->execute();
    $existing = $stmtExisting->get_result()->fetch_assoc();
    $stmtExisting->close();

    if (!$existing) {
        $stmtInsert = $conn->prepare("INSERT INTO cobranca_review (
                review_batch_id,
                due_at,
                overdue_days,
                status,
                status_changed_at,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmtInsert) {
            $stmtInsert->bind_param('isissss', $batchId, $dueAt, $overdueDays, $status, $changedAt, $changedAt, $changedAt);
            $stmtInsert->execute();
            $stmtInsert->close();
        }
    }

    $stmtBatch = $conn->prepare("UPDATE review_batch rb
        LEFT JOIN cobranca_review cr ON cr.review_batch_id = rb.id
        SET rb.status = CASE
                WHEN cr.status = 'OVERDUE' THEN 'OVERDUE'
                WHEN cr.status = 'NOTIFIED' THEN 'NOTIFIED'
                WHEN cr.status = 'SNOOZED' THEN 'SNOOZED'
                WHEN cr.status = 'PENDING' THEN 'OPEN'
                WHEN cr.status = 'IGNORED' THEN 'IGNORED'
                WHEN cr.status = 'RESOLVED' THEN 'RESOLVED'
                ELSE rb.status
            END,
            rb.batch_active_slot = CASE
                WHEN cr.status IN ('PENDING', 'OVERDUE', 'NOTIFIED', 'SNOOZED') THEN 1
                ELSE NULL
            END,
            rb.updated_at = ?
        WHERE rb.id = ?");
    if ($stmtBatch) {
        $stmtBatch->bind_param('si', $changedAt, $batchId);
        $stmtBatch->execute();
        $stmtBatch->close();
    }
}
