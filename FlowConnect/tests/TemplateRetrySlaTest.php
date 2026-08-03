<?php

use FlowConnect\Application\RetryPolicy;
use FlowConnect\Application\SlaSchedulePolicy;
use FlowConnect\Application\TemplateRenderer;
use FlowConnect\Channels\SlackApiAdapter;

function flow_connect_test_templates_retry_sla(): void
{
    $renderer = new TemplateRenderer();
    $templates = [
        'mention_created',
        'angle_chosen',
        'angle_chosen_with_adjustments',
        'angle_adjustment_requested',
        'task_approved',
        'task_adjustment_requested',
        'task_approved_with_adjustments',
        'task_rejected',
        'direction_validation_requested',
        'sftp_failed',
        'approval_sla_exceeded',
        'legacy_immediate',
    ];
    $payload = [
        'autor_nome' => '<Autor & Cia>',
        'obra_nome' => 'Obra <X>',
        'imagem_nome' => 'Imagem & 1',
        'funcao_nome' => 'Finalização',
        'observacao' => str_repeat('x', 700),
        'flow_review_url' => 'https://example.test/review',
        'operacao' => 'upload',
        'tentativa' => 1,
        'erro_tecnico_seguro' => 'timeout',
        'tempo_em_aprovacao' => 30,
        'limite_sla' => 24,
        'message' => 'Mensagem imediata <teste>',
        'revisor_nome' => 'Rafael <Teste>',
    ];
    foreach ($templates as $template) {
        $result = $renderer->render($template, ['payload' => $payload]);
        fc_assert(trim($result['text']) !== '', "template {$template} rendered");
        fc_assert(strpos($result['text'], '<Autor & Cia>') === false, "template {$template} escapes user content");
    }

    $actorTemplates = [
        'angle_chosen',
        'angle_chosen_with_adjustments',
        'angle_adjustment_requested',
        'task_approved',
        'task_adjustment_requested',
        'task_approved_with_adjustments',
        'task_rejected',
        'direction_validation_requested',
        'sftp_failed',
    ];
    foreach ($actorTemplates as $template) {
        $result = $renderer->render($template, ['payload' => $payload]);
        fc_assert(
            strpos($result['text'], '*Rafael &lt;Teste&gt;*') !== false,
            "template {$template} identifies the reviewer"
        );
    }

    $taskAdjustmentText = $renderer->render('task_adjustment_requested', ['payload' => $payload])['text'];
    fc_assert(strpos($taskAdjustmentText, '🛠️ Ajuste solicitado na tarefa por *Rafael &lt;Teste&gt;*') !== false, 'task adjustment identifies who requested it in its title');
    fc_assert(strpos($taskAdjustmentText, 'Ação registrada por') === false, 'task adjustment has no separate actor line');
    fc_assert(
        strpos($renderer->render('task_approved', ['payload' => $payload])['text'], '✅ Tarefa aprovada por *Rafael &lt;Teste&gt;*') !== false,
        'task approval identifies its reviewer in its title'
    );
    fc_assert(
        strpos($renderer->render('direction_validation_requested', ['payload' => $payload])['text'], '⏳ Validação da direção solicitada por *Rafael &lt;Teste&gt;*') !== false,
        'direction validation identifies its reviewer in its title'
    );
    fc_assert_same(
        1,
        substr_count($renderer->render('mention_created', ['payload' => $payload])['text'], 'Autor &amp; Cia'),
        'mention identifies its author once'
    );

    $slaResult = $renderer->render('approval_sla_exceeded', ['payload' => array_diff_key($payload, ['revisor_nome' => true, 'autor_nome' => true])]);
    fc_assert(strpos($slaResult['text'], 'Ação registrada por') === false, 'SLA event does not invent an actor');

    $retry = new RetryPolicy();
    fc_assert_same('SENT', $retry->decide(['ok' => true], 0)['status'], 'success sent');
    fc_assert_same('RETRY_WAIT', $retry->decide(['ok' => false, 'retry_after' => 60, 'permanent' => false], 0)['status'], '429 retry');
    fc_assert_same('DEAD', $retry->decide(['ok' => false, 'permanent' => true], 0)['status'], 'permanent dead');
    fc_assert_same('DEAD', $retry->decide(['ok' => false, 'permanent' => false], 5)['status'], 'retry exhausted dead');

    fc_assert_same(
        'SLACK_WEBHOOK_CONTRATOS_URL',
        SlackApiAdapter::webhookEnvKey(['destination_key' => 'SLACK_WEBHOOK_CONTRATOS_URL', 'slack_user_id' => null]),
        'new webhook delivery resolves environment key from destination key'
    );
    fc_assert_same(
        'SLACK_WEBHOOK_POS_URL',
        SlackApiAdapter::webhookEnvKey(['destination_key' => 'slack:channel:SLACK_WEBHOOK_POS_URL', 'slack_user_id' => 'SLACK_WEBHOOK_POS_URL']),
        'legacy webhook delivery keeps its environment key'
    );

    $sla = new SlaSchedulePolicy();
    fc_assert(!$sla->isDue('Em aprovação', 23, 24), 'below SLA');
    fc_assert($sla->isDue('Em aprovação', 24, 24), 'at SLA');
    fc_assert(!$sla->isDue('Aprovado', 30, 24), 'resolved task');
    fc_assert(!$sla->isDue('Em aprovação', 30, 24, false, false, true), 'cancelled schedule');
    fc_assert(!$sla->isDue('Em aprovação', 30, 24, true), 'silenced schedule');
}
