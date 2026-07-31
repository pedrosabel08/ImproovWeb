<?php

declare(strict_types=1);

use FlowConnect\Application\FlowReviewEventFactory;

function fc_it_direction(FlowConnectIntegrationContext $ctx): void
{
    $context = ['funcao_imagem_id' => 993010, 'historico_direcao_id' => 993011, 'funcao_id' => 3, 'imagem_id' => 993012, 'obra_id' => 993013, 'colaborador_responsavel_id' => $ctx->activeCollaboratorId(), 'revisor_id' => 1, 'status_anterior' => 'Em aprovação', 'status_novo' => 'Aguardando Direção', 'tipo_fluxo' => 'imagem', 'flow_review_url' => 'https://improov/ImproovWeb/FlowReview/'];
    [$sent, $sentPlan] = $ctx->publishAndPlan(FlowReviewEventFactory::taskSentToDirection($context), 'direction-fact');
    $request = FlowReviewEventFactory::direction($context);
    $request['causation_event_uuid'] = $sent['event_uuid'];
    [$stored, $requestPlan] = $ctx->publishAndPlan($request, 'direction-request');
    $ctx->assert($stored['causation_event_uuid'] === $sent['event_uuid'], 'Causation da direção não preservada.');
    $ctx->assert($sentPlan['delivery_ids'] === [], 'Fato enviado à direção deve ficar somente em histórico.');
    $ctx->assert($requestPlan['delivery_ids'] !== [], 'Solicitação de direção deve criar deliveries lógicas.');
    foreach ($requestPlan['delivery_ids'] as $deliveryId) {
        $delivery = $ctx->delivery((int) $deliveryId);
        $ctx->assert($delivery['destination_kind'] === 'GROUP', 'Direção deve usar destino GROUP.');
        $ctx->assert((int) $delivery['attempt_count'] === 0, 'Direção shadow não pode tentar Slack.');
    }
}
