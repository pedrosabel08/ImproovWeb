-- Executar somente em banco de teste após aprovação e aplicação da migration.
-- 1) Evento repetido: a segunda publicação deve retornar o mesmo id.
SELECT idempotency_key, COUNT(*) quantidade
FROM flow_connect_events
GROUP BY
    idempotency_key
HAVING
    COUNT(*) > 1;

-- 2) Delivery repetida: não pode haver mais de uma por notificação/destino.
SELECT
    notification_id,
    destination_key,
    COUNT(*) quantidade
FROM flow_connect_deliveries
GROUP BY
    notification_id,
    destination_key
HAVING
    COUNT(*) > 1;

-- 3) Claim expirado deve voltar à fila no próximo worker.
SELECT
    id,
    status,
    claimed_by,
    claim_expires_at
FROM flow_connect_events
WHERE
    status = 'PROCESSING'
    AND claim_expires_at < UTC_TIMESTAMP(6);

-- 4) Shadow jamais deve chegar a SENT.
SELECT d.id, d.status, e.event_type
FROM
    flow_connect_deliveries d
    JOIN flow_connect_notifications n ON n.id = d.notification_id
    JOIN flow_connect_events e ON e.id = n.event_id
WHERE
    JSON_UNQUOTE(
        JSON_EXTRACT(
            e.metadata_json,
            '$.flow_connect_mode'
        )
    ) = 'shadow'
    AND d.status = 'SENT';

-- 5) Cobrança SLA depois de resolução/cancelamento deve ser zero.
SELECT
    id,
    entity_id,
    next_due_at,
    resolved_at,
    cancelled_at
FROM flow_connect_schedules
WHERE
    event_type = 'review.aprovacao.sla_excedido'
    AND (
        resolved_at IS NOT NULL
        OR cancelled_at IS NOT NULL
    )
    AND status = 'ACTIVE';