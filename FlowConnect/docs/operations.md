# Operação local e futura VPS

## Ordem segura de ativação

1. Revisar `FlowConnect/migrations/001_flow_connect_core.sql`.
2. Após aprovação humana, aplicar manualmente a migration em banco local/teste.
3. Copiar somente as variáveis necessárias de `.env.example` para o `.env` protegido.
4. Manter tudo em `off` e executar `health_check.php`.
5. Sincronizar identidades Slack uma única vez e revisar conflitos.
6. Ativar uma família em `shadow`.
7. Executar a ação correspondente no FlowReview e rodar workers manualmente.
8. Comparar evento, destinatário e mensagem com o legado em `inspect_shadow.php`.
9. Somente após aprovação, ativar uma família por vez em `active` e confirmar uma única entrega.

Esta entrega para antes dos passos 2 e 9.

## Comandos

```text
php FlowConnect/tests/run.php
php FlowConnect/scripts/health_check.php
php FlowConnect/scripts/sync_slack_identities.php
php FlowConnect/workers/event_worker.php --once --limit=20 --verbose
php FlowConnect/workers/delivery_worker.php --once --limit=20 --verbose
php FlowConnect/workers/event_worker.php --daemon --limit=100 --verbose
php FlowConnect/workers/delivery_worker.php --daemon --limit=100 --verbose
php FlowConnect/workers/scheduler_worker.php --once --limit=20 --verbose
php FlowConnect/scripts/inspect_shadow.php --limit=20
php FlowConnect/scripts/inspect_shadow.php --event-type=review.mencao.criada --limit=50
php FlowConnect/scripts/inspect_shadow.php --correlation-id=UUID --limit=50
php FlowConnect/scripts/inspect_shadow.php --entity-id=123 --limit=50
```

Sem `--daemon`, os workers fazem um único ciclo, inclusive sem `--once`. Em `--daemon`, apenas os workers imediatos (`event_worker.php` e `delivery_worker.php`) repetem o ciclo: processam a fila, aguardam um segundo quando o claim vem vazio e consultam novamente. `SIGTERM` e `SIGINT` solicitam parada cooperativa: o lote em curso termina, nenhum novo claim é iniciado e a conexão é fechada. O sleep ocorre após o claim já ter encerrado sua transação, sem lock mantido.

## Consultas de inspeção

```sql
SELECT id, event_uuid, event_type, entity_type, entity_id, correlation_id,
       idempotency_key, status, received_at, processed_at, last_error_code
FROM flow_connect_events
ORDER BY id DESC LIMIT 100;

SELECT n.id, e.event_type, n.severity, n.category, n.delivery_mode,
       n.template_code, n.recipient_strategy, n.status
FROM flow_connect_notifications n
JOIN flow_connect_events e ON e.id=n.event_id
ORDER BY n.id DESC LIMIT 100;

SELECT d.id, e.event_type, d.destination_kind, d.collaborator_id,
       d.slack_user_id, d.status, d.attempt_count, d.next_attempt_at,
       d.last_error_code, d.last_error_safe
FROM flow_connect_deliveries d
JOIN flow_connect_notifications n ON n.id=d.notification_id
JOIN flow_connect_events e ON e.id=n.event_id
ORDER BY d.id DESC LIMIT 100;

SELECT a.delivery_id, a.attempt_no, a.http_status, a.provider_error_code,
       a.error_safe, a.started_at, a.finished_at
FROM flow_connect_delivery_attempts a
ORDER BY a.id DESC LIMIT 100;

SELECT id, entity_id, status, next_due_at, silence_until,
       last_fired_at, resolved_at, cancelled_at
FROM flow_connect_schedules
WHERE event_type='review.aprovacao.sla_excedido'
ORDER BY id DESC;

SELECT colaborador_id, slack_user_id, status, last_synced_at
FROM flow_connect_slack_identities
ORDER BY status, colaborador_id;

SELECT reason_code, payload_safe_json, first_failed_at, last_failed_at,
       reprocessed_at, resolved_at
FROM flow_connect_dead_letters
WHERE resolved_at IS NULL
ORDER BY last_failed_at DESC;
```

## Cron sugerido para VPS — não instalar nesta etapa

```text
* * * * * flock -n /tmp/flow-connect-event.lock php /caminho/FlowConnect/workers/event_worker.php --once --limit=100
* * * * * flock -n /tmp/flow-connect-delivery.lock php /caminho/FlowConnect/workers/delivery_worker.php --once --limit=100
* * * * * flock -n /tmp/flow-connect-scheduler.lock php /caminho/FlowConnect/workers/scheduler_worker.php --once --limit=100
```

O caminho real da VPS não está no código. O cron legado de SLA não deve ser removido antes da comparação em shadow.

## Retry e dead-letter

- `429`: respeita `Retry-After`.
- Timeout/transporte: backoff exponencial de 30 segundos até 1 hora.
- Erro permanente ou sexta tentativa: delivery em `DEAD` e dead-letter idempotente.
- Retry cria nova tentativa, nunca novo evento.
- Claims vencidos retornam à fila no ciclo seguinte.
