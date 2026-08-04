<?php

use FlowConnect\Application\OperationalMilestonePolicy;
use FlowConnect\Application\OperationalPendingEventFactory;
use FlowConnect\Application\RecipientResolver;
use FlowConnect\Application\TemplateRenderer;

function flow_connect_test_operational_pending(): void
{
    $policy = new OperationalMilestonePolicy();
    $tz = new DateTimeZone('America/Sao_Paulo');
    $start = new DateTimeImmutable('2026-08-03 08:00:00', $tz);
    $due = new DateTimeImmutable('2026-08-03 10:00:00', $tz);
    fc_assert_same(['WARNING_90'], $policy->dueMilestones($start, $due, new DateTimeImmutable('2026-08-03 09:48:00', $tz)), '90 percent warning');
    fc_assert_same(['WARNING_90', 'EXPIRED'], $policy->dueMilestones($start, $due, $due), 'expiry preserves earlier milestone');
    fc_assert_same(4, count($policy->dueMilestones($start, $due, new DateTimeImmutable('2026-08-03 14:00:00', $tz))), 'all overdue milestones');

    $event = OperationalPendingEventFactory::milestone(['module_key' => 'projeto', 'policy_key' => 'projeto.checklist.v1', 'entity_type' => 'checklist_operacional', 'entity_id' => '44', 'cycle_id' => '2026-08-03'], 'EXPIRED', ['titulo' => 'Checklist']);
    fc_assert_same('operacional:pendencia:projeto:checklist_operacional:44:policy:projeto.checklist.v1:cycle:2026-08-03:milestone:EXPIRED', $event['idempotency_key'], 'milestone idempotency key');

    $resolver = new RecipientResolver(['flow_review' => ['roles' => ['flow_review_managers' => []]], 'operational' => ['manager_roles' => ['projeto' => [9, 21]], 'overdue_webhook_env' => 'FLOW_CONNECT_SLA_OVERDUE_WEBHOOK_URL']]);
    $audience = $resolver->resolveForEvent('operational_pending_audience', ['event_type' => 'projeto.pendencia.criada', 'payload' => ['module_key' => 'projeto', 'responsavel_id' => 21, 'responsavel_cobranca_id' => 9]]);
    fc_assert_same([21, 9], array_column($audience, 'collaborator_id'), 'operational recipients are deduplicated before delivery');
    fc_assert_same('WEBHOOK', $resolver->resolve('sla_overdue_webhook', ['payload' => []])[0]['destination_kind'], 'overdue channel is an environment-key webhook');

    $renderer = new TemplateRenderer();
    fc_assert(strpos($renderer->render('operational_pending_milestone', ['payload' => ['titulo' => 'Teste', 'milestone' => 'EXPIRED']])['text'], 'vencida') !== false, 'milestone template renders');
    $fileSummary = $renderer->render('file_upload_pending_summary', ['payload' => ['total' => 2, 'itens' => [['titulo' => 'Imagem · Função · arquivo pendente']], 'origin_url' => 'https://improov.com.br/flow/ImproovWeb/inicio.php']])['text'];
    fc_assert(strpos($fileSummary, 'Imagem · Função · arquivo pendente') !== false, 'upload summary template renders details');
    fc_assert(strpos($fileSummary, 'Abrir pendências') !== false, 'upload summary includes pending link');
    $compactSummary = $renderer->render('file_upload_pending_summary', [
        'payload' => [
            'total' => 6,
            'resumo_compacto' => true,
            'itens' => [['titulo' => 'Item']],
        ],
    ])['text'];
    fc_assert(strpos($compactSummary, 'Verifique agora') !== false && strpos($compactSummary, 'Item') === false, 'upload summary compacts larger queues');
}
