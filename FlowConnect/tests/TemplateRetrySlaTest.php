<?php

use FlowConnect\Application\RetryPolicy;
use FlowConnect\Application\SlaSchedulePolicy;
use FlowConnect\Application\TemplateRenderer;

function flow_connect_test_templates_retry_sla(): void
{
    $renderer = new TemplateRenderer();
    $templates = [
        'mention_created', 'angle_chosen', 'angle_chosen_with_adjustments', 'angle_adjustment_requested',
        'task_approved', 'task_adjustment_requested', 'task_approved_with_adjustments', 'task_rejected',
        'direction_validation_requested', 'sftp_failed', 'approval_sla_exceeded', 'legacy_immediate',
    ];
    $payload = [
        'autor_nome' => '<Autor & Cia>', 'obra_nome' => 'Obra <X>', 'imagem_nome' => 'Imagem & 1',
        'funcao_nome' => 'Finalização', 'observacao' => str_repeat('x', 700),
        'flow_review_url' => 'https://example.test/review', 'operacao' => 'upload', 'tentativa' => 1,
        'erro_tecnico_seguro' => 'timeout', 'tempo_em_aprovacao' => 30, 'limite_sla' => 24,
        'message' => 'Mensagem imediata <teste>',
    ];
    foreach ($templates as $template) {
        $result = $renderer->render($template, ['payload' => $payload]);
        fc_assert(trim($result['text']) !== '', "template {$template} rendered");
        fc_assert(strpos($result['text'], '<Autor & Cia>') === false, "template {$template} escapes user content");
    }

    $retry = new RetryPolicy();
    fc_assert_same('SENT', $retry->decide(['ok' => true], 0)['status'], 'success sent');
    fc_assert_same('RETRY_WAIT', $retry->decide(['ok' => false, 'retry_after' => 60, 'permanent' => false], 0)['status'], '429 retry');
    fc_assert_same('DEAD', $retry->decide(['ok' => false, 'permanent' => true], 0)['status'], 'permanent dead');
    fc_assert_same('DEAD', $retry->decide(['ok' => false, 'permanent' => false], 5)['status'], 'retry exhausted dead');

    $sla = new SlaSchedulePolicy();
    fc_assert(!$sla->isDue('Em aprovação', 23, 24), 'below SLA');
    fc_assert($sla->isDue('Em aprovação', 24, 24), 'at SLA');
    fc_assert(!$sla->isDue('Aprovado', 30, 24), 'resolved task');
    fc_assert(!$sla->isDue('Em aprovação', 30, 24, false, false, true), 'cancelled schedule');
    fc_assert(!$sla->isDue('Em aprovação', 30, 24, true), 'silenced schedule');
}
