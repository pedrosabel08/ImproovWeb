<?php

declare(strict_types=1);

use FlowConnect\Application\FlowReviewEventFactory;

function fc_it_sftp(FlowConnectIntegrationContext $ctx): void
{
    $event = FlowReviewEventFactory::sftpFailure(['funcao_imagem_id' => 994010, 'funcao_id' => 3, 'imagem_id' => 994011, 'obra_id' => 994012, 'actor_id' => 1, 'operacao' => 'envio_arquivo_revisado', 'operation_id' => 'integration-sftp-994010', 'tentativa' => 1, 'erro_tecnico_seguro' => flow_connect_safe_error('timeout password=secret C:\\private\\file.jpg', 'sftp_failed'), 'flow_review_url' => 'https://improov/ImproovWeb/FlowReview/']);
    [$stored, $plan] = $ctx->publishAndPlan($event, 'sftp-safe-error');
    $definition = require dirname(__DIR__, 2) . '/config/events/flow_review.php';
    $ctx->assert($definition[$stored['event_type']]['category'] === 'TECHNICAL', 'Falha SFTP precisa ser técnica.');
    $ctx->assert($definition[$stored['event_type']]['severity'] === 'CRITICO', 'Falha SFTP precisa ser crítica.');
    $payload = json_encode($stored['payload'], JSON_UNESCAPED_UNICODE);
    $ctx->assert(!str_contains((string) $payload, 'secret') && !str_contains((string) $payload, 'C:\\private'), 'Payload técnico expôs dado sensível.');
    foreach ($plan['delivery_ids'] as $deliveryId) {
        $delivery = $ctx->delivery((int) $deliveryId);
        $ctx->assert($delivery['destination_kind'] === 'ADMIN', 'Falha SFTP deve ir para administradores técnicos.');
        $ctx->assert((int) $delivery['attempt_count'] === 0, 'Falha SFTP shadow não pode chamar Slack.');
    }
}
