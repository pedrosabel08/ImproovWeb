<?php

declare(strict_types=1);

use FlowConnect\Application\FlowReviewEventFactory;

function fc_it_mentions(FlowConnectIntegrationContext $ctx): void
{
    $active = $ctx->activeCollaboratorId();
    $base = ['comentario_id' => 990001, 'mencao_id' => 990101, 'autor_id' => $active, 'mencionado_id' => $active, 'autor_nome' => '<Autor & Cia>', 'comentario' => str_repeat('<teste &>', 120), 'flow_review_url' => 'https://improov/ImproovWeb/FlowReview/'];
    [$event, $plan] = $ctx->publishAndPlan(FlowReviewEventFactory::mention($base), 'mention-self-special');
    $ctx->assert($event['event_type'] === 'review.mencao.criada', 'Tipo de evento de menção incorreto.');
    $ctx->assert(count($plan['delivery_ids']) === 1, 'Auto-menção deve manter delivery lógica pelo mencionado_id.');
    $ctx->assertShadowDelivery((int) $plan['delivery_ids'][0], $active, true);

    $sameKey = FlowReviewEventFactory::mention($base);
    [, $secondPlan] = $ctx->publishAndPlan($sameKey, 'mention-self-special');
    $ctx->assert($plan['notification_id'] === $secondPlan['notification_id'], 'Menção repetida criou notification duplicada.');
    $ctx->assert($plan['delivery_ids'][0] === $secondPlan['delivery_ids'][0], 'Menção repetida criou delivery duplicada.');

    $reply = FlowReviewEventFactory::mention(array_merge($base, ['comentario_id' => 990002, 'resposta_id' => 990003, 'mencao_id' => 990102]));
    [$replyEvent, $replyPlan] = $ctx->publishAndPlan($reply, 'mention-reply');
    $ctx->assert(str_contains($replyEvent['idempotency_key'], 'mention-reply:'), 'Prefixo de isolamento ausente na menção resposta.');
    $ctx->assert(count($replyPlan['delivery_ids']) === 1, 'Menção em resposta não planejou delivery.');

    $unresolved = $ctx->unresolvedCollaboratorId();
    $unresolvedEvent = FlowReviewEventFactory::mention(array_merge($base, ['comentario_id' => 990004, 'mencao_id' => 990103, 'mencionado_id' => $unresolved]));
    [$stored, $unresolvedPlan] = $ctx->publishAndPlan($unresolvedEvent, 'mention-unresolved');
    $ctx->assertShadowDelivery((int) $unresolvedPlan['delivery_ids'][0], $unresolved, false);
    $ctx->assert($ctx->deadLettersForEvent((int) $stored['id']) === 1, 'Ausência de identidade deveria gerar um dead-letter seguro.');
}
