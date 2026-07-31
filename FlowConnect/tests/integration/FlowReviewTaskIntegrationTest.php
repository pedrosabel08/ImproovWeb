<?php

declare(strict_types=1);

use FlowConnect\Application\FlowReviewEventFactory;

function fc_it_tasks(FlowConnectIntegrationContext $ctx): void
{
    $active = $ctx->activeCollaboratorId();
    $cases = ['Aprovado' => 'review.tarefa.aprovada', 'Ajuste' => 'review.tarefa.ajuste_solicitado', 'Aprovado com ajustes' => 'review.tarefa.aprovada_com_ajustes'];
    foreach ($cases as $status => $type) {
        $event = FlowReviewEventFactory::task(['funcao_imagem_id' => 992010 + strlen($status), 'funcao_id' => 3, 'imagem_id' => 992020, 'obra_id' => 992030, 'colaborador_responsavel_id' => $active, 'revisor_id' => 1, 'historico_aprovacao_id' => 992040 + strlen($status), 'status_anterior' => 'Em aprovação', 'status_novo' => $status, 'tipo_fluxo' => 'imagem', 'obra_nome' => 'Obra teste', 'imagem_nome' => 'Imagem teste', 'funcao_nome' => 'Composição', 'flow_review_url' => 'https://improov/ImproovWeb/FlowReview/']);
        [$stored, $plan] = $ctx->publishAndPlan($event, 'task-' . strtolower(str_replace(' ', '-', $status)));
        $ctx->assert($stored['event_type'] === $type, "Status {$status} gerou evento incorreto.");
        if ($status === 'Aprovado') {
            $definition = require dirname(__DIR__, 2) . '/config/events/flow_review.php';
            $ctx->assert($definition[$type]['delivery_mode'] === 'HISTORY_ONLY', 'Aprovação simples deve ser HISTORY_ONLY fora de shadow.');
            $ctx->assert($plan['delivery_mode'] === 'HISTORY_ONLY' && $plan['delivery_ids'] === [], 'Aprovação simples não pode criar delivery em shadow.');
            continue;
        }
        $ctx->assertShadowDelivery((int) $plan['delivery_ids'][0], $active, true);
    }

    $animation = FlowReviewEventFactory::task(['funcao_animacao_id' => 992099, 'funcao_id' => 3, 'imagem_id' => 992020, 'obra_id' => 992030, 'colaborador_responsavel_id' => $active, 'revisor_id' => 1, 'historico_aprovacao_id' => 992098, 'status_novo' => 'Ajuste', 'tipo_fluxo' => 'animacao', 'flow_review_url' => 'https://improov/ImproovWeb/FlowReview/']);
    [$stored, $plan] = $ctx->publishAndPlan($animation, 'task-animation');
    $ctx->assert($stored['entity_type'] === 'funcao_animacao', 'Animação precisa preservar entity_type próprio.');
    $ctx->assert($plan['delivery_ids'] !== [], 'Animação deve resolver responsável.');
    $ctx->assertShadowDelivery((int) $plan['delivery_ids'][0], $active, true);
}
