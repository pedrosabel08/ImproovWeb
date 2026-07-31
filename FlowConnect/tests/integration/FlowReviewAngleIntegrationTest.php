<?php

declare(strict_types=1);

use FlowConnect\Application\FlowReviewEventFactory;

function fc_it_angles(FlowConnectIntegrationContext $ctx): void
{
    $active = $ctx->activeCollaboratorId();
    $cases = ['escolhido' => 'review.angulo.escolhido', 'escolhido_com_ajustes' => 'review.angulo.escolhido_com_ajustes', 'ajustes' => 'review.angulo.ajuste_solicitado'];
    foreach ($cases as $decision => $expectedType) {
        $event = FlowReviewEventFactory::angle(['historico_id' => 991000 + strlen($decision), 'funcao_imagem_id' => 991010, 'imagem_id' => 991011, 'obra_id' => 991012, 'funcao_id' => 3, 'colaborador_responsavel_id' => $active, 'revisor_id' => 1, 'decisao' => $decision, 'observacao' => '<ajuste & seguro>', 'obra_nome' => 'Obra <Teste>', 'imagem_nome' => 'Imagem & Teste', 'funcao_nome' => 'Composição', 'flow_review_url' => 'https://improov/ImproovWeb/FlowReview/']);
        [$stored, $plan] = $ctx->publishAndPlan($event, 'angle-' . $decision);
        $ctx->assert($stored['event_type'] === $expectedType, "Decisão {$decision} gerou tipo incorreto.");
        $ctx->assert($plan['delivery_mode'] === 'SHADOW', 'Ângulo em shadow precisa manter delivery lógica.');
        $ctx->assertShadowDelivery((int) $plan['delivery_ids'][0], $active, true);
    }
}
