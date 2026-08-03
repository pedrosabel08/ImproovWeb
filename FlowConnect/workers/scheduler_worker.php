<?php

declare(strict_types=1);

require_once __DIR__ . '/_cli.php';

use FlowConnect\Application\FlowReviewEventFactory;
use FlowConnect\Application\SlaSchedulePolicy;

/**
 * Executa uma rodada do scheduler. Cada transacao e encerrada antes de a
 * rodada terminar; o WorkerLoop so dorme depois disso, sem manter locks.
 */
function flow_connect_scheduler_cycle(mysqli $conn, array $config, string $mode, int $limit, string $workerId, int $ttl, bool $verbose): int
{
    $businessTimezone = new DateTimeZone((string) ($config['operational']['business_timezone'] ?? 'America/Sao_Paulo'));
    $businessNow = (new DateTimeImmutable('now', $businessTimezone))->format('Y-m-d H:i:s');
    $businessNowSql = $conn->real_escape_string($businessNow);
    $sql = "SELECT fi.idfuncao_imagem, fi.funcao_id, fun.nome_funcao, i.idimagens_cliente_obra AS imagem_id,
                   i.imagem_nome, o.idobra AS obra_id, COALESCE(NULLIF(o.nomenclatura,''), o.nome_obra) AS obra_nome,
                   sf.limite_horas, ha.id AS approval_history_id, ha.data_aprovacao,
                   DATE_ADD(ha.data_aprovacao, INTERVAL sf.limite_horas HOUR) AS next_due_at
            FROM funcao_imagem fi
            JOIN sla_funcao sf ON sf.funcao_id=fi.funcao_id
            LEFT JOIN funcao fun ON fun.idfuncao=fi.funcao_id
            JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=fi.imagem_id
            JOIN obra o ON o.idobra=i.obra_id
            JOIN (SELECT funcao_imagem_id, MAX(id) max_id FROM historico_aprovacoes WHERE status_novo='Em aprovação' GROUP BY funcao_imagem_id) latest ON latest.funcao_imagem_id=fi.idfuncao_imagem
            JOIN historico_aprovacoes ha ON ha.id=latest.max_id
            WHERE fi.status='Em aprovação' AND o.status_obra=0";
    $result = $conn->query($sql);
    if (!$result) throw new RuntimeException('scheduler_reconcile_query_failed');

    $upsert = $conn->prepare("INSERT INTO flow_connect_schedules
        (event_type, entity_type, entity_id, schedule_kind, status, next_due_at, recurrence_json, context_json)
        VALUES ('review.aprovacao.sla_excedido', 'funcao_imagem', ?, 'COBRANCA', 'ACTIVE', ?, ?, ?)
        ON DUPLICATE KEY UPDATE context_json=VALUES(context_json), recurrence_json=VALUES(recurrence_json),
            next_due_at=IF(last_fired_at IS NULL, VALUES(next_due_at), next_due_at),
            status=IF(resolved_at IS NULL AND cancelled_at IS NULL, 'ACTIVE', status)");
    $existing = $conn->prepare("SELECT id, context_json FROM flow_connect_schedules WHERE event_type='review.aprovacao.sla_excedido' AND entity_type='funcao_imagem' AND entity_id=? AND schedule_kind='COBRANCA' LIMIT 1");
    if (!$upsert || !$existing) throw new RuntimeException('scheduler_reconcile_prepare_failed');
    $reconciled = 0;
    while ($row = $result->fetch_assoc()) {
        $entityId = (string) $row['idfuncao_imagem'];
        $existing->bind_param('s', $entityId); $existing->execute();
        $previous = $existing->get_result()->fetch_assoc();
        $previousContext = json_decode((string) ($previous['context_json'] ?? '{}'), true) ?: [];
        $reopened = $previous !== null
            && (string) ($previousContext['approval_history_id'] ?? '') !== ''
            && (string) ($previousContext['approval_history_id'] ?? '') !== (string) $row['approval_history_id'];
        $nextDue = (string) $row['next_due_at'];
        $recurrence = json_encode(['repeat_hours' => (int) $config['flow_review']['sla_repeat_hours']], JSON_UNESCAPED_UNICODE);
        $context = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $upsert->bind_param('ssss', $entityId, $nextDue, $recurrence, $context); $upsert->execute();
        if ($reopened) {
            $reset = $conn->prepare("UPDATE flow_connect_schedules SET status='ACTIVE', resolved_at=NULL, cancelled_at=NULL, last_event_uuid=NULL, last_fired_at=NULL, next_due_at=?, claimed_by=NULL, claimed_at=NULL, claim_expires_at=NULL WHERE id=?");
            $scheduleId = (int) $previous['id']; $reset->bind_param('si', $nextDue, $scheduleId); $reset->execute(); $reset->close();
        }
        $reconciled++;
    }
    $upsert->close(); $existing->close();

    $conn->begin_transaction();
    try {
        $conn->query("UPDATE flow_connect_schedules SET claimed_by=NULL, claimed_at=NULL, claim_expires_at=NULL WHERE status='ACTIVE' AND claim_expires_at < UTC_TIMESTAMP(6)");
        $due = $conn->query("SELECT * FROM flow_connect_schedules WHERE event_type='review.aprovacao.sla_excedido' AND status='ACTIVE' AND resolved_at IS NULL AND cancelled_at IS NULL AND next_due_at<='{$businessNowSql}' AND (silence_until IS NULL OR silence_until<=UTC_TIMESTAMP(6)) AND claimed_by IS NULL ORDER BY next_due_at ASC LIMIT {$limit} FOR UPDATE SKIP LOCKED");
        if (!$due) throw new RuntimeException('scheduler_claim_query_failed');
        $schedules = []; $ids = [];
        while ($row = $due->fetch_assoc()) { $schedules[] = $row; $ids[] = (int) $row['id']; }
        if ($ids !== []) {
            $safeWorker = $conn->real_escape_string($workerId);
            $conn->query("UPDATE flow_connect_schedules SET claimed_by='{$safeWorker}', claimed_at=UTC_TIMESTAMP(6), claim_expires_at=DATE_ADD(UTC_TIMESTAMP(6), INTERVAL {$ttl} SECOND) WHERE id IN (" . implode(',', $ids) . ')');
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback(); throw $e;
    }
    flow_connect_cli_log("scheduler_worker reconciled={$reconciled} claimed=" . count($schedules), $verbose);

    $policy = new SlaSchedulePolicy(); $processed = 0;
    foreach ($schedules as $schedule) {
        $taskId = (int) $schedule['entity_id'];
        $stmt = $conn->prepare("SELECT fi.status, fi.funcao_id, fun.nome_funcao, i.idimagens_cliente_obra AS imagem_id, i.imagem_nome,
                                      o.idobra AS obra_id, COALESCE(NULLIF(o.nomenclatura,''),o.nome_obra) AS obra_nome,
                                      sf.limite_horas, TIMESTAMPDIFF(HOUR, ha.data_aprovacao, '{$businessNowSql}') AS horas
                               FROM funcao_imagem fi JOIN sla_funcao sf ON sf.funcao_id=fi.funcao_id
                               LEFT JOIN funcao fun ON fun.idfuncao=fi.funcao_id JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=fi.imagem_id
                               JOIN obra o ON o.idobra=i.obra_id JOIN historico_aprovacoes ha ON ha.id=(SELECT MAX(h2.id) FROM historico_aprovacoes h2 WHERE h2.funcao_imagem_id=fi.idfuncao_imagem AND h2.status_novo='Em aprovação')
                               WHERE fi.idfuncao_imagem=? LIMIT 1");
        if (!$stmt) throw new RuntimeException('scheduler_task_prepare_failed');
        $stmt->bind_param('i', $taskId); $stmt->execute(); $task = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$task || !$policy->isDue((string) $task['status'], (int) ($task['horas'] ?? 0), (int) ($task['limite_horas'] ?? PHP_INT_MAX))) {
            $done = $conn->prepare("UPDATE flow_connect_schedules SET status='RESOLVED', resolved_at=UTC_TIMESTAMP(6), claimed_by=NULL, claimed_at=NULL, claim_expires_at=NULL WHERE id=?");
            $done->bind_param('i', $schedule['id']); $done->execute(); $done->close(); continue;
        }
        $event = FlowReviewEventFactory::slaExceeded([
            'funcao_imagem_id' => $taskId, 'funcao_id' => (int) $task['funcao_id'], 'imagem_id' => (int) $task['imagem_id'], 'obra_id' => (int) $task['obra_id'],
            'funcao_nome' => $task['nome_funcao'], 'imagem_nome' => $task['imagem_nome'], 'obra_nome' => $task['obra_nome'],
            'tempo_em_aprovacao' => (int) $task['horas'], 'limite_sla' => (int) $task['limite_horas'], 'nivel' => 1,
            'janela_referencia' => gmdate('Y-m-d'), 'flow_review_url' => $config['flow_review']['url'], 'producer' => 'FlowConnect/workers/scheduler_worker.php',
        ]);
        $event['metadata']['flow_connect_mode'] = $mode;
        $conn->begin_transaction();
        try {
            $eventId = flow_connect_publish_in_transaction($conn, $event); $uuid = $event['event_uuid'];
            $nextAt = gmdate('Y-m-d H:i:s', time() + ((int) $config['flow_review']['sla_repeat_hours'] * 3600));
            $up = $conn->prepare("UPDATE flow_connect_schedules SET last_event_uuid=?, last_fired_at=UTC_TIMESTAMP(6), next_due_at=?, claimed_by=NULL, claimed_at=NULL, claim_expires_at=NULL, last_error_code=NULL, last_error_safe=NULL WHERE id=?");
            $up->bind_param('ssi', $uuid, $nextAt, $schedule['id']); $up->execute(); $up->close(); $conn->commit(); $processed++;
            flow_connect_cli_log("schedule={$schedule['id']} event={$eventId}", $verbose);
        } catch (Throwable $e) {
            $conn->rollback(); $safe = flow_connect_safe_error($e->getMessage(), 'scheduler_publish_failed');
            $fail = $conn->prepare("UPDATE flow_connect_schedules SET claimed_by=NULL, claimed_at=NULL, claim_expires_at=NULL, last_error_code='publish_failed', last_error_safe=? WHERE id=?");
            $fail->bind_param('si', $safe, $schedule['id']); $fail->execute(); $fail->close(); flow_connect_cli_log("schedule={$schedule['id']} failed={$safe}", true);
        }
    }
    return $processed + count($schedules);
}

$options = flow_connect_cli_options($argv);
$mode = flow_connect_review_mode('sla');
if ($mode === 'off') { flow_connect_cli_log('scheduler_worker skipped: FLOW_CONNECT_REVIEW_SLA_MODE=off', true); exit(0); }
$conn = conectarBanco(); $config = flow_connect_config();
try {
    $keepRunning = flow_connect_daemon_keep_running();
    (new FlowConnect\Application\WorkerLoop())->run((bool) $options['daemon'], function () use ($conn, $config, $mode, $options): int {
        return flow_connect_scheduler_cycle($conn, $config, $mode, (int) $options['limit'], flow_connect_worker_id('scheduler'), max(30, (int) $config['claim_ttl_seconds']), (bool) $options['verbose']);
    }, $keepRunning, 'flow_connect_daemon_idle_wait');
} catch (Throwable $e) {
    flow_connect_cli_log('scheduler_worker fatal=' . flow_connect_safe_error($e->getMessage()), true); $conn->close(); exit(1);
}
$conn->close();
