<?php

declare(strict_types=1);

require_once __DIR__ . '/_cli.php';

use FlowConnect\Application\FlowReviewEventFactory;
use FlowConnect\Application\SlaSchedulePolicy;

$options = flow_connect_cli_options($argv);
$mode = flow_connect_review_mode('sla');
if ($mode === 'off') {
    flow_connect_cli_log('scheduler_worker skipped: FLOW_CONNECT_REVIEW_SLA_MODE=off', true);
    exit(0);
}

$conn = conectarBanco();
$config = flow_connect_config();
$limit = (int) $options['limit'];
$workerId = flow_connect_worker_id('scheduler');
$ttl = max(30, (int) $config['claim_ttl_seconds']);
$slaPolicy = new SlaSchedulePolicy();

try {
    // Reconcilia schedules sem alterar as tabelas de negócio.
    $sql = "SELECT fi.idfuncao_imagem, fi.funcao_id, fun.nome_funcao, i.idimagens_cliente_obra AS imagem_id,
                   i.imagem_nome, o.idobra AS obra_id, COALESCE(NULLIF(o.nomenclatura,''), o.nome_obra) AS obra_nome,
                   sf.limite_horas, ha.data_aprovacao,
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
    $upsert = $conn->prepare("INSERT INTO flow_connect_schedules
        (event_type, entity_type, entity_id, schedule_kind, status, next_due_at, recurrence_json, context_json)
        VALUES ('review.aprovacao.sla_excedido', 'funcao_imagem', ?, 'COBRANCA', 'ACTIVE', ?, ?, ?)
        ON DUPLICATE KEY UPDATE context_json=VALUES(context_json), recurrence_json=VALUES(recurrence_json),
            next_due_at=IF(last_fired_at IS NULL, VALUES(next_due_at), next_due_at),
            status=IF(resolved_at IS NULL AND cancelled_at IS NULL, 'ACTIVE', status)");
    $reconciled = 0;
    while ($result && ($row = $result->fetch_assoc())) {
        $entityId = (string) $row['idfuncao_imagem'];
        $nextDue = (string) $row['next_due_at'];
        $recurrence = json_encode(['repeat_hours' => (int) $config['flow_review']['sla_repeat_hours']], JSON_UNESCAPED_UNICODE);
        $context = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $upsert->bind_param('ssss', $entityId, $nextDue, $recurrence, $context);
        $upsert->execute();
        $reconciled++;
    }
    $upsert->close();

    $conn->begin_transaction();
    $conn->query("UPDATE flow_connect_schedules SET claimed_by=NULL, claimed_at=NULL, claim_expires_at=NULL WHERE status='ACTIVE' AND claim_expires_at < UTC_TIMESTAMP(6)");
    $due = $conn->query("SELECT * FROM flow_connect_schedules WHERE event_type='review.aprovacao.sla_excedido' AND status='ACTIVE' AND resolved_at IS NULL AND cancelled_at IS NULL AND next_due_at<=UTC_TIMESTAMP(6) AND (silence_until IS NULL OR silence_until<=UTC_TIMESTAMP(6)) AND claimed_by IS NULL ORDER BY next_due_at ASC LIMIT {$limit} FOR UPDATE SKIP LOCKED");
    $schedules = [];
    $ids = [];
    while ($due && ($row = $due->fetch_assoc())) {
        $schedules[] = $row;
        $ids[] = (int) $row['id'];
    }
    if ($ids !== []) {
        $safeWorker = $conn->real_escape_string($workerId);
        $conn->query("UPDATE flow_connect_schedules SET claimed_by='{$safeWorker}', claimed_at=UTC_TIMESTAMP(6), claim_expires_at=DATE_ADD(UTC_TIMESTAMP(6), INTERVAL {$ttl} SECOND) WHERE id IN (" . implode(',', $ids) . ')');
    }
    $conn->commit();
    flow_connect_cli_log("scheduler_worker reconciled={$reconciled} claimed=" . count($schedules), (bool) $options['verbose']);

    foreach ($schedules as $schedule) {
        $taskId = (int) $schedule['entity_id'];
        $stmt = $conn->prepare("SELECT fi.status, fi.funcao_id, fun.nome_funcao, i.idimagens_cliente_obra AS imagem_id, i.imagem_nome,
                                      o.idobra AS obra_id, COALESCE(NULLIF(o.nomenclatura,''),o.nome_obra) AS obra_nome,
                                      sf.limite_horas, TIMESTAMPDIFF(HOUR, ha.data_aprovacao, UTC_TIMESTAMP()) AS horas
                               FROM funcao_imagem fi
                               JOIN sla_funcao sf ON sf.funcao_id=fi.funcao_id
                               LEFT JOIN funcao fun ON fun.idfuncao=fi.funcao_id
                               JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=fi.imagem_id
                               JOIN obra o ON o.idobra=i.obra_id
                               JOIN historico_aprovacoes ha ON ha.id=(SELECT MAX(h2.id) FROM historico_aprovacoes h2 WHERE h2.funcao_imagem_id=fi.idfuncao_imagem AND h2.status_novo='Em aprovação')
                               WHERE fi.idfuncao_imagem=? LIMIT 1");
        $stmt->bind_param('i', $taskId);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$task || !$slaPolicy->isDue((string)$task['status'], (int)($task['horas'] ?? 0), (int)($task['limite_horas'] ?? PHP_INT_MAX))) {
            $done = $conn->prepare("UPDATE flow_connect_schedules SET status='RESOLVED', resolved_at=UTC_TIMESTAMP(6), claimed_by=NULL, claimed_at=NULL, claim_expires_at=NULL WHERE id=?");
            $done->bind_param('i', $schedule['id']);
            $done->execute();
            $done->close();
            continue;
        }

        $reference = gmdate('Y-m-d');
        $event = FlowReviewEventFactory::slaExceeded([
            'funcao_imagem_id' => $taskId,
            'funcao_id' => (int) $task['funcao_id'],
            'imagem_id' => (int) $task['imagem_id'],
            'obra_id' => (int) $task['obra_id'],
            'funcao_nome' => $task['nome_funcao'],
            'imagem_nome' => $task['imagem_nome'],
            'obra_nome' => $task['obra_nome'],
            'tempo_em_aprovacao' => (int) $task['horas'],
            'limite_sla' => (int) $task['limite_horas'],
            'nivel' => 1,
            'janela_referencia' => $reference,
            'flow_review_url' => $config['flow_review']['url'],
            'producer' => 'FlowConnect/workers/scheduler_worker.php',
        ]);
        $event['metadata']['flow_connect_mode'] = $mode;
        $conn->begin_transaction();
        try {
            $eventId = flow_connect_publish_in_transaction($conn, $event);
            $eventUuid = null;
            $getUuid = $conn->prepare('SELECT event_uuid FROM flow_connect_events WHERE id=?');
            $getUuid->bind_param('i', $eventId);
            $getUuid->execute();
            $getUuid->bind_result($eventUuid);
            $getUuid->fetch();
            $getUuid->close();
            $nextAt = gmdate('Y-m-d H:i:s', time() + ((int) $config['flow_review']['sla_repeat_hours'] * 3600));
            $up = $conn->prepare("UPDATE flow_connect_schedules SET last_event_uuid=?, last_fired_at=UTC_TIMESTAMP(6), next_due_at=?, claimed_by=NULL, claimed_at=NULL, claim_expires_at=NULL, last_error_code=NULL, last_error_safe=NULL WHERE id=?");
            $up->bind_param('ssi', $eventUuid, $nextAt, $schedule['id']);
            $up->execute();
            $up->close();
            $conn->commit();
            flow_connect_cli_log("schedule={$schedule['id']} event={$eventId}", (bool) $options['verbose']);
        } catch (Throwable $e) {
            $conn->rollback();
            $safe = flow_connect_safe_error($e->getMessage(), 'scheduler_publish_failed');
            $fail = $conn->prepare("UPDATE flow_connect_schedules SET claimed_by=NULL, claimed_at=NULL, claim_expires_at=NULL, last_error_code='publish_failed', last_error_safe=? WHERE id=?");
            $fail->bind_param('si', $safe, $schedule['id']);
            $fail->execute();
            $fail->close();
            flow_connect_cli_log("schedule={$schedule['id']} failed={$safe}", true);
        }
    }
} catch (Throwable $e) {
    if ($conn->errno === 0) {
        // conexão utilizável; nenhuma ação adicional.
    }
    flow_connect_cli_log('scheduler_worker fatal=' . flow_connect_safe_error($e->getMessage()), true);
    $conn->close();
    exit(1);
}

$conn->close();
