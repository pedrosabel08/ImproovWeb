-- Active: 1774568939571@@127.0.0.1@3306@flowdb
DROP TABLE IF EXISTS review_batch;

CREATE TABLE IF NOT EXISTS review_batch (
    id INT NOT NULL AUTO_INCREMENT,
    entrega_id INT NOT NULL,
    data_entrega_lote DATE NOT NULL,
    review_round SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM(
        'OPEN',
        'OVERDUE',
        'NOTIFIED',
        'SNOOZED',
        'RESOLVED',
        'IGNORED'
    ) NOT NULL DEFAULT 'OPEN',
    batch_active_slot TINYINT UNSIGNED DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY ux_review_batch_active (
        entrega_id,
        data_entrega_lote,
        review_round,
        batch_active_slot
    ),
    KEY idx_review_batch_entrega_status (
        entrega_id,
        status,
        data_entrega_lote
    ),
    KEY idx_review_batch_status_date (status, data_entrega_lote),
    CONSTRAINT fk_review_batch_entrega FOREIGN KEY (entrega_id) REFERENCES entregas (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_batch_items (
    id INT NOT NULL AUTO_INCREMENT,
    review_batch_id INT NOT NULL,
    entrega_item_id INT NOT NULL,
    imagem_id INT NOT NULL,
    entered_rvw_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    left_rvw_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    item_active_slot TINYINT UNSIGNED DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY ux_review_batch_item_active (
        review_batch_id,
        entrega_item_id,
        imagem_id,
        item_active_slot
    ),
    KEY idx_review_batch_items_batch_active (review_batch_id, left_rvw_at),
    KEY idx_review_batch_items_imagem_history (imagem_id, entered_rvw_at),
    KEY idx_review_batch_items_entrega_history (
        entrega_item_id,
        entered_rvw_at
    ),
    CONSTRAINT fk_review_batch_items_batch FOREIGN KEY (review_batch_id) REFERENCES review_batch (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_review_batch_items_entrega_item FOREIGN KEY (entrega_item_id) REFERENCES entregas_itens (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_review_batch_items_imagem FOREIGN KEY (imagem_id) REFERENCES imagens_cliente_obra (idimagens_cliente_obra) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cobranca_review (
    id INT NOT NULL AUTO_INCREMENT,
    review_batch_id INT NOT NULL,
    due_at DATETIME NOT NULL,
    overdue_days INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM(
        'PENDING',
        'OVERDUE',
        'NOTIFIED',
        'SNOOZED',
        'RESOLVED',
        'IGNORED'
    ) NOT NULL DEFAULT 'PENDING',
    notification_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_notification_at DATETIME DEFAULT NULL,
    snooze_until DATETIME DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    resolved_reason VARCHAR(255) DEFAULT NULL,
    status_changed_at DATETIME DEFAULT NULL,
    status_changed_by INT DEFAULT NULL,
    last_action_note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY ux_cobranca_review_batch (review_batch_id),
    KEY idx_cobranca_review_status_due (status, due_at),
    KEY idx_cobranca_review_status_snooze (status, snooze_until),
    KEY idx_cobranca_review_status_overdue (status, overdue_days),
    CONSTRAINT fk_cobranca_review_batch FOREIGN KEY (review_batch_id) REFERENCES review_batch (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_review_batch_enter_rvw $$

CREATE PROCEDURE sp_review_batch_enter_rvw(IN p_imagem_id INT, IN p_changed_at DATETIME)
proc: BEGIN
    DECLARE v_entrega_item_id INT DEFAULT NULL;
    DECLARE v_entrega_id INT DEFAULT NULL;
    DECLARE v_data_entregue DATETIME DEFAULT NULL;
    DECLARE v_data_lote DATE DEFAULT NULL;
    DECLARE v_review_batch_id INT DEFAULT NULL;
    DECLARE v_review_round SMALLINT UNSIGNED DEFAULT 1;
    DECLARE v_due_at DATETIME DEFAULT NULL;
    DECLARE v_active_item_id INT DEFAULT NULL;

    SELECT
        ei.id,
        ei.entrega_id,
        ei.data_entregue,
        DATE(ei.data_entregue)
    INTO
        v_entrega_item_id,
        v_entrega_id,
        v_data_entregue,
        v_data_lote
    FROM entregas_itens ei
    WHERE ei.imagem_id = p_imagem_id
      AND ei.data_entregue IS NOT NULL
    ORDER BY ei.data_entregue DESC, ei.id DESC
    LIMIT 1;

    IF v_entrega_item_id IS NULL OR v_entrega_id IS NULL OR v_data_lote IS NULL THEN
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
      AND rbi.entrega_item_id = v_entrega_item_id
      AND rbi.imagem_id = p_imagem_id
      AND rbi.left_rvw_at IS NULL
    LIMIT 1
    FOR UPDATE;

    IF v_active_item_id IS NULL THEN
        INSERT INTO review_batch_items (
            review_batch_id,
            entrega_item_id,
            imagem_id,
            entered_rvw_at
        ) VALUES (
            v_review_batch_id,
            v_entrega_item_id,
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
END $$

DROP PROCEDURE IF EXISTS sp_review_batch_leave_rvw $$

CREATE PROCEDURE sp_review_batch_leave_rvw(IN p_imagem_id INT, IN p_changed_at DATETIME)
proc: BEGIN
    DECLARE v_review_batch_id INT DEFAULT NULL;
    DECLARE v_active_items INT DEFAULT 0;

    SELECT rbi.review_batch_id
    INTO v_review_batch_id
    FROM review_batch_items rbi
    INNER JOIN review_batch rb ON rb.id = rbi.review_batch_id
    WHERE rbi.imagem_id = p_imagem_id
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
            last_action_note = COALESCE(NULLIF(last_action_note, ''), 'Batch resolvido automaticamente após saída do último item do RVW.')
        WHERE review_batch_id = v_review_batch_id
          AND status <> 'IGNORED';

        UPDATE review_batch
        SET status = CASE WHEN status = 'IGNORED' THEN status ELSE 'RESOLVED' END,
            batch_active_slot = NULL,
            updated_at = COALESCE(p_changed_at, NOW())
        WHERE id = v_review_batch_id
          AND status <> 'IGNORED';
    END IF;
END $$

DROP TRIGGER IF EXISTS trg_review_batch_on_rvw $$

-- DELIMITER is client-side. In the VS Code DB client, run each CREATE PROCEDURE
-- and CREATE TRIGGER block as a full statement if delimiter handling is inconsistent.

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
END $$

DELIMITER;