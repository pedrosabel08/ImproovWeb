-- Badge do Kanban por entrega.
SELECT
    rb.entrega_id,
    COUNT(*) AS total_batches_ativos,
    SUM(
        CASE
            WHEN cr.status IN ('OVERDUE', 'NOTIFIED') THEN 1
            ELSE 0
        END
    ) AS batches_vencidos,
    SUM(
        CASE
            WHEN cr.status = 'PENDING' THEN 1
            ELSE 0
        END
    ) AS batches_pendentes,
    MAX(
        CASE
            WHEN cr.status IN ('OVERDUE', 'NOTIFIED') THEN cr.overdue_days
            ELSE 0
        END
    ) AS max_overdue_days
FROM
    review_batch rb
    INNER JOIN cobranca_review cr ON cr.review_batch_id = rb.id
WHERE
    rb.status IN (
        'OPEN',
        'OVERDUE',
        'NOTIFIED',
        'SNOOZED'
    )
GROUP BY
    rb.entrega_id;

-- Listagem de lotes para modal ou dashboard por obra/entrega.
SELECT
    rb.id,
    rb.entrega_id,
    e.obra_id,
    o.nomenclatura,
    s.nome_status AS nome_etapa,
    rb.data_entrega_lote,
    rb.review_round,
    rb.status AS batch_status,
    cr.status AS billing_status,
    cr.due_at,
    cr.overdue_days,
    cr.notification_count,
    cr.last_notification_at,
    cr.snooze_until,
    cr.resolved_at,
    cr.resolved_reason,
    COUNT(rbi.id) AS total_items,
    SUM(CASE WHEN rbi.left_rvw_at IS NULL THEN 1 ELSE 0 END) AS active_items
FROM review_batch rb
INNER JOIN entregas e ON e.id = rb.entrega_id
INNER JOIN obra o ON o.idobra = e.obra_id
INNER JOIN status_imagem s ON s.idstatus = e.status_id
INNER JOIN cobranca_review cr ON cr.review_batch_id = rb.id
LEFT JOIN review_batch_items rbi ON rbi.review_batch_id = rb.id
WHERE e.obra_id = :obra_id
GROUP BY
    rb.id,
    rb.entrega_id,
    e.obra_id,
    o.nomenclatura,
    s.nome_status,
    rb.data_entrega_lote,
    rb.review_round,
    rb.status,
    cr.status,
    cr.due_at,
    cr.overdue_days,
    cr.notification_count,
    cr.last_notification_at,
    cr.snooze_until,
    cr.resolved_at,
    cr.resolved_reason
ORDER BY rb.data_entrega_lote DESC, rb.review_round DESC, rb.id DESC;

-- Detalhe de um batch com imagens e histórico de permanência.
SELECT
    rbi.id,
    rbi.review_batch_id,
    rbi.entrega_item_id,
    rbi.imagem_id,
    ico.imagem_nome,
    ei.status AS entrega_item_status,
    ei.data_entregue,
    rbi.entered_rvw_at,
    rbi.left_rvw_at,
    ss.nome_substatus
FROM review_batch_items rbi
INNER JOIN entregas_itens ei ON ei.id = rbi.entrega_item_id
INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = rbi.imagem_id
LEFT JOIN substatus_imagem ss ON ss.id = ico.substatus_id
WHERE rbi.review_batch_id = :review_batch_id
ORDER BY
    CASE WHEN rbi.left_rvw_at IS NULL THEN 0 ELSE 1 END,
    ico.imagem_nome,
    rbi.id;

-- Aging para métricas futuras.
SELECT
    cr.status,
    COUNT(*) AS total_batches,
    AVG(cr.overdue_days) AS avg_overdue_days,
    MAX(cr.overdue_days) AS max_overdue_days
FROM cobranca_review cr
GROUP BY
    cr.status;

-- Reconciliação para batches sem cobrança, útil em manutenção ou no cron.
INSERT INTO
    cobranca_review (
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
    DATE_ADD(
        CONCAT(
            rb.data_entrega_lote,
            ' 23:59:59'
        ),
        INTERVAL 3 DAY
    ),
    CASE
        WHEN DATE_ADD(
            CONCAT(
                rb.data_entrega_lote,
                ' 23:59:59'
            ),
            INTERVAL 3 DAY
        ) < NOW() THEN GREATEST(
            DATEDIFF(
                CURDATE(),
                DATE(
                    DATE_ADD(
                        CONCAT(
                            rb.data_entrega_lote,
                            ' 23:59:59'
                        ),
                        INTERVAL 3 DAY
                    )
                )
            ),
            0
        )
        ELSE 0
    END,
    CASE
        WHEN DATE_ADD(
            CONCAT(
                rb.data_entrega_lote,
                ' 23:59:59'
            ),
            INTERVAL 3 DAY
        ) < NOW() THEN 'OVERDUE'
        ELSE 'PENDING'
    END,
    NOW(),
    NOW(),
    NOW()
FROM
    review_batch rb
    LEFT JOIN cobranca_review cr ON cr.review_batch_id = rb.id
WHERE
    cr.id IS NULL;