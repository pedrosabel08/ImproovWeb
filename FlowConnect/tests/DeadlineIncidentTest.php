<?php

declare(strict_types=1);

use FlowConnect\Application\RecipientResolver;
use FlowConnect\Application\RetryPolicy;
use FlowConnect\Application\TemplateRenderer;
use FlowConnect\Contracts\EventEnvelope;
use FlowConnect\Contracts\EventValidator;

function flow_connect_test_deadline_incident(): void
{
    $definitions = require dirname(__DIR__) . '/config/events/deadline.php';
    $templates = new TemplateRenderer();
    $recipients = new RecipientResolver([]);
    foreach ($definitions as $eventType => $definition) {
        $event = EventEnvelope::normalize([
            'event_type' => $eventType, 'source_module' => 'deadline', 'entity_type' => 'deadline_incident',
            'entity_id' => '101', 'idempotency_key' => 'deadline:integration:101:1:active:1',
            'payload' => [
                'worker' => '<!channel>', 'job_id' => str_repeat('a', 24), 'job_name' => 'Cena & teste',
                'category' => 'Memória RAM insuficiente', 'diagnosis' => 'Provável insuficiência de memória.',
                'confidence' => 'provável', 'severity' => $definition['severity'], 'state' => 'open',
                'evidence_summary' => 'Aviso do Corona e encerramento do 3ds Max.',
                'recommendation' => 'Verificar o consumo da cena.', 'occurrences' => 3,
                'window_count' => 3, 'window_minutes' => 15, 'ram_percent' => 'RAM reportada em 99%.',
            ],
            'metadata' => ['flow_connect_mode' => 'active'],
        ]);
        EventValidator::validate($event);
        $destination = $recipients->resolveForEvent($definition['recipient_strategy'], $event);
        fc_assert_same('FLOW_CONNECT_DEADLINE_WEBHOOK_URL', $destination[0]['external_id'], 'destino exclusivo do Deadline');
        $message = $templates->render($definition['template'], $event)['text'];
        fc_assert(str_contains($message, '&lt;!channel&gt;') && !str_contains($message, '<!channel>'), 'menções do log são escapadas');
        fc_assert(str_contains($message, '3 em uma janela de 15 min.'), 'janela e contagem no Slack');
        fc_assert(str_contains($message, 'RAM reportada em 99%.'), 'evidência exibida no Slack');
    }
    $retry = new RetryPolicy();
    $decision = $retry->decide(['ok' => false, 'http_status' => 503, 'permanent' => false], 0);
    fc_assert_same('RETRY_WAIT', $decision['status'], 'falha temporária do Slack é reagendada');
    fc_assert_same('SENT', $retry->decide(['ok' => true], 1)['status'], 'reenvio bem-sucedido conclui a entrega');
}
