<?php

use FlowConnect\Application\FlowReviewEventFactory;
use FlowConnect\Application\LegacyImmediateEventFactory;
use FlowConnect\Contracts\EventValidator;

function flow_connect_test_event_contracts(): void
{
    $baseMention = [
        'comentario_id' => 10, 'resposta_id' => null, 'mencao_id' => 100,
        'mencionado_id' => 7, 'autor_id' => 3, 'flow_review_url' => 'https://example.test/review',
    ];
    $comment = FlowReviewEventFactory::mention($baseMention);
    EventValidator::validate(\FlowConnect\Contracts\EventEnvelope::normalize($comment));
    fc_assert_same('review:mencao:comentario:10:colaborador:7:v1', $comment['idempotency_key'], 'mention comment key');
    $reply = FlowReviewEventFactory::mention(array_merge($baseMention, ['comentario_id' => 10, 'resposta_id' => 44]));
    fc_assert_same('review:mencao:resposta:44:colaborador:7:v1', $reply['idempotency_key'], 'mention reply key');
    $duplicate = FlowReviewEventFactory::mention($baseMention);
    fc_assert_same($comment['idempotency_key'], $duplicate['idempotency_key'], 'duplicate mention deterministic');
    $secondPerson = FlowReviewEventFactory::mention(array_merge($baseMention, ['mencionado_id' => 8]));
    fc_assert($secondPerson['idempotency_key'] !== $comment['idempotency_key'], 'two mentions must differ');

    foreach (['escolhido', 'escolhido_com_ajustes', 'ajustes'] as $decision) {
        $event = FlowReviewEventFactory::angle([
            'historico_id' => 22, 'funcao_imagem_id' => 5, 'imagem_id' => 6,
            'colaborador_responsavel_id' => 7, 'revisor_id' => 3, 'decisao' => $decision,
            'observacao' => $decision === 'escolhido' ? '' : 'Ajustar enquadramento',
        ]);
        EventValidator::validate(\FlowConnect\Contracts\EventEnvelope::normalize($event));
    }

    $statuses = [
        'Aprovado' => 'review.tarefa.aprovada',
        'Ajuste' => 'review.tarefa.ajuste_solicitado',
        'Aprovado com ajustes' => 'review.tarefa.aprovada_com_ajustes',
    ];
    foreach ($statuses as $status => $type) {
        $event = FlowReviewEventFactory::task([
            'funcao_imagem_id' => 9, 'historico_aprovacao_id' => 30, 'status_novo' => $status,
            'tipo_fluxo' => 'imagem', 'colaborador_responsavel_id' => 7,
        ]);
        fc_assert_same($type, $event['event_type'], 'task status mapping');
        EventValidator::validate(\FlowConnect\Contracts\EventEnvelope::normalize($event));
    }
    $animation = FlowReviewEventFactory::task([
        'funcao_animacao_id' => 12, 'historico_aprovacao_id' => 31, 'status_novo' => 'Aprovado',
        'tipo_fluxo' => 'animacao', 'colaborador_responsavel_id' => 7,
    ]);
    fc_assert_same('funcao_animacao', $animation['entity_type'], 'animation entity type');

    $sentDirection = FlowReviewEventFactory::taskSentToDirection([
        'funcao_imagem_id' => 9, 'historico_direcao_id' => 33,
    ]);
    fc_assert_same('review.tarefa.enviada_direcao', $sentDirection['event_type'], 'task sent to direction fact');
    EventValidator::validate(\FlowConnect\Contracts\EventEnvelope::normalize($sentDirection));

    $sftp = FlowReviewEventFactory::sftpFailure([
        'funcao_imagem_id' => 9, 'operacao' => 'upload', 'operation_id' => 'op-9',
        'tentativa' => 1, 'erro_tecnico_seguro' => 'timeout',
    ]);
    EventValidator::validate(\FlowConnect\Contracts\EventEnvelope::normalize($sftp));
    fc_assert(!isset($sftp['payload']['operation_id']), 'operation id is key material, not payload');

    $sla = FlowReviewEventFactory::slaExceeded([
        'funcao_imagem_id' => 9, 'tempo_em_aprovacao' => 30, 'limite_sla' => 24,
        'nivel' => 1, 'janela_referencia' => '2026-07-31',
    ]);
    EventValidator::validate(\FlowConnect\Contracts\EventEnvelope::normalize($sla));
    fc_assert_same('review:sla:9:nivel:1:janela:2026-07-31:v1', $sla['idempotency_key'], 'sla key');

    $legacy = LegacyImmediateEventFactory::make('arquivo.upload.status', 'funcao_imagem', 9, ['message' => 'Upload concluído'], 7, null, 'upload:9:ok:v1', 'flowdrive');
    EventValidator::validate($legacy);
    fc_assert_same('upload:9:ok:v1', $legacy['idempotency_key'], 'legacy immediate key remains deterministic');

    $invalid = \FlowConnect\Contracts\EventEnvelope::normalize($comment);
    $invalid['payload']['token'] = 'forbidden';
    try {
        EventValidator::validate($invalid);
        fc_assert(false, 'producer token must be rejected');
    } catch (InvalidArgumentException $e) {
        fc_assert(true, 'producer token rejected');
    }
}
