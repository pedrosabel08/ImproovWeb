<?php

declare(strict_types=1);

use FlowConnect\Application\FlowReviewEventFactory;
use FlowConnect\Application\SlaSchedulePolicy;

function fc_it_sla(FlowConnectIntegrationContext $ctx): void
{
    $policy = new SlaSchedulePolicy();
    $ctx->assert(!$policy->isDue('Em aprovação', 23, 24), 'SLA abaixo do limite não deveria vencer.');
    $ctx->assert($policy->isDue('Em aprovação', 24, 24), 'SLA no limite deveria vencer.');
    $ctx->assert(!$policy->isDue('Aprovado', 30, 24), 'Tarefa resolvida não pode cobrar SLA.');
    $event = FlowReviewEventFactory::slaExceeded(['funcao_imagem_id' => 995010, 'funcao_id' => 3, 'imagem_id' => 995011, 'obra_id' => 995012, 'tempo_em_aprovacao' => 24, 'limite_sla' => 24, 'nivel' => 1, 'janela_referencia' => '2026-07-31', 'flow_review_url' => 'https://improov/ImproovWeb/FlowReview/']);
    [$stored, $plan] = $ctx->publishAndPlan($event, 'sla-window');
    $ctx->assert($stored['event_type'] === 'review.aprovacao.sla_excedido', 'Tipo de SLA incorreto.');
    foreach ($plan['delivery_ids'] as $deliveryId) {
        $ctx->assert((int) $ctx->delivery((int) $deliveryId)['attempt_count'] === 0, 'SLA shadow não pode chamar Slack.');
    }

    $insert = $ctx->conn->prepare("INSERT INTO flow_connect_schedules (event_type,entity_type,entity_id,schedule_kind,status,next_due_at,context_json) VALUES ('review.aprovacao.sla_excedido','funcao_imagem',?,'COBRANCA','ACTIVE',DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 1 MINUTE),?)");
    $entity = 'it-schedule-' . getmypid();
    $context = json_encode(['integration_prefix' => $ctx->prefix], JSON_UNESCAPED_UNICODE);
    $insert->bind_param('ss', $entity, $context);
    $insert->execute();
    $scheduleId = (int) $ctx->conn->insert_id;
    $insert->close();
    $scheduler = 'php ' . escapeshellarg(dirname(__DIR__, 2) . '/workers/scheduler_worker.php') . ' --once --limit=20';
    exec($scheduler, $schedulerOutput, $schedulerExit);
    $ctx->assert($schedulerExit === 0, 'Scheduler worker terminou com erro.');
    $row = $ctx->conn->query('SELECT status,resolved_at FROM flow_connect_schedules WHERE id=' . $scheduleId)->fetch_assoc();
    $ctx->assert($row !== null && $row['status'] === 'RESOLVED' && $row['resolved_at'] !== null, 'Scheduler não resolveu schedule sem tarefa vigente.');
    exec($scheduler, $schedulerRepeatOutput, $schedulerRepeatExit);
    $repeat = $ctx->conn->query('SELECT status,last_fired_at FROM flow_connect_schedules WHERE id=' . $scheduleId)->fetch_assoc();
    $ctx->assert($schedulerRepeatExit === 0 && $repeat['status'] === 'RESOLVED' && $repeat['last_fired_at'] === null, 'Scheduler repetido reprocessou schedule já resolvido.');

    $insert = $ctx->conn->prepare("INSERT INTO flow_connect_schedules (event_type,entity_type,entity_id,schedule_kind,status,next_due_at,silence_until,cancelled_at,context_json) VALUES ('review.aprovacao.sla_excedido','funcao_imagem',?,'COBRANCA','ACTIVE',DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 1 MINUTE),DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 1 HOUR),UTC_TIMESTAMP(6),?)");
    $entity = 'it-schedule-silenced-' . getmypid();
    $insert->bind_param('ss', $entity, $context);
    $insert->execute();
    $blockedId = (int) $ctx->conn->insert_id;
    $insert->close();
    exec($scheduler, $blockedOutput, $blockedExit);
    $blocked = $ctx->conn->query('SELECT claimed_by,last_fired_at FROM flow_connect_schedules WHERE id=' . $blockedId)->fetch_assoc();
    $ctx->assert($blockedExit === 0 && $blocked !== null && $blocked['claimed_by'] === null && $blocked['last_fired_at'] === null, 'Schedule cancelado/silenciado foi processado indevidamente.');

    $expiredInsert = $ctx->conn->prepare("INSERT INTO flow_connect_schedules (event_type,entity_type,entity_id,schedule_kind,status,next_due_at,claimed_by,claimed_at,claim_expires_at,context_json) VALUES ('review.aprovacao.sla_excedido','funcao_imagem',?,'COBRANCA','ACTIVE',DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 1 MINUTE),'expired-integration',DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 10 MINUTE),DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 5 MINUTE),?)");
    $entity = 'it-schedule-expired-' . getmypid();
    $expiredInsert->bind_param('ss', $entity, $context);
    $expiredInsert->execute();
    $expiredId = (int) $ctx->conn->insert_id;
    $expiredInsert->close();
    exec($scheduler, $expiredOutput, $expiredExit);
    $expired = $ctx->conn->query('SELECT status,claimed_by,resolved_at FROM flow_connect_schedules WHERE id=' . $expiredId)->fetch_assoc();
    $ctx->assert($expiredExit === 0 && $expired['status'] === 'RESOLVED' && $expired['claimed_by'] === null && $expired['resolved_at'] !== null, 'Scheduler não recuperou claim expirado.');
}
