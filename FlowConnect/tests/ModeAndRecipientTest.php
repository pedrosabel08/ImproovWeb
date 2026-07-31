<?php

use FlowConnect\Application\RecipientResolver;

function flow_connect_test_modes_and_recipients(): void
{
    $keys = [
        'FLOW_CONNECT_FLOW_REVIEW_MODE', 'FLOW_CONNECT_REVIEW_MENTION_MODE',
        'FLOW_CONNECT_REVIEW_ANGLE_MODE', 'FLOW_CONNECT_REVIEW_TASK_MODE',
        'FLOW_CONNECT_REVIEW_DIRECTION_MODE', 'FLOW_CONNECT_REVIEW_SFTP_MODE',
        'FLOW_CONNECT_REVIEW_SLA_MODE',
    ];
    $old = [];
    foreach ($keys as $key) $old[$key] = getenv($key);
    try {
        foreach ($keys as $key) putenv($key . '=');
        putenv('FLOW_CONNECT_FLOW_REVIEW_MODE=off');
        fc_assert_same('off', flow_connect_review_mode('mention'), 'general off');
        fc_assert(!flow_connect_should_bypass_legacy('mention', 99), 'off keeps legacy');

        putenv('FLOW_CONNECT_FLOW_REVIEW_MODE=shadow');
        fc_assert_same('shadow', flow_connect_review_mode('angle'), 'general shadow');
        fc_assert(!flow_connect_should_bypass_legacy('angle', 99), 'shadow keeps legacy');

        putenv('FLOW_CONNECT_REVIEW_MENTION_MODE=active');
        fc_assert_same('active', flow_connect_review_mode('mention'), 'specific override');
        fc_assert(flow_connect_should_bypass_legacy('mention', 99), 'active bypasses persisted event');
        fc_assert(!flow_connect_should_bypass_legacy('mention', 0), 'active falls back when publish fails');
    } finally {
        foreach ($old as $key => $value) putenv($value === false ? $key . '=' : $key . '=' . $value);
    }

    $resolver = new RecipientResolver([
        'flow_review' => [
            'roles' => ['direction_group' => [21, 2], 'flow_review_managers' => [21, 9], 'technical_admins' => [21, 9]],
            'review_channel_id' => '',
        ],
    ]);
    $mentioned = $resolver->resolve('mentioned_user', ['actor_id' => 3, 'payload' => ['mencionado_id' => 7]]);
    fc_assert_same(7, $mentioned[0]['collaborator_id'], 'mentioned user');
    $self = $resolver->resolve('mentioned_user', ['actor_id' => 7, 'payload' => ['mencionado_id' => 7]]);
    fc_assert_same(7, $self[0]['collaborator_id'], 'self mention remains addressed by payload');
    fc_assert_same(2, count($resolver->resolve('direction_group', ['payload' => []])), 'direction group');
    $technical = $resolver->resolve('technical_admins', ['payload' => []]);
    fc_assert_same('ADMIN', $technical[0]['destination_kind'], 'technical destination isolated');

    $taskAudience = $resolver->resolveForEvent('task_responsible', ['event_type' => 'review.tarefa.ajuste_solicitado', 'payload' => ['colaborador_responsavel_id' => 21]]);
    fc_assert_same([21, 9], array_column($taskAudience, 'collaborator_id'), 'manager responsible must receive one delivery each');
    $directionAudience = $resolver->resolveForEvent('direction_group', ['event_type' => 'review.direcao.validacao_solicitada', 'payload' => []]);
    fc_assert_same([21, 2, 9], array_column($directionAudience, 'collaborator_id'), 'direction and managers must be deduplicated before deliveries');
    $technicalAudience = $resolver->resolveForEvent('technical_admins', ['event_type' => 'review.sftp.envio_falhou', 'payload' => []]);
    fc_assert_same([21, 9], array_column($technicalAudience, 'collaborator_id'), 'technical administrators repeated as managers must remain unique');
    $mentionAudience = $resolver->resolveForEvent('mentioned_user', ['event_type' => 'review.mencao.criada', 'payload' => ['mencionado_id' => 7]]);
    fc_assert_same([7], array_column($mentionAudience, 'collaborator_id'), 'mentions must not add flow review managers');
    $legacyDm = $resolver->resolve('legacy_payload_destination', ['payload' => ['recipient_collaborator_id' => 7]]);
    fc_assert_same(7, $legacyDm[0]['collaborator_id'], 'legacy immediate DM keeps collaborator destination');
    $legacyWebhook = $resolver->resolve('legacy_payload_destination', ['payload' => ['webhook_env' => 'SLACK_WEBHOOK_POS_URL']]);
    fc_assert_same('WEBHOOK', $legacyWebhook[0]['destination_kind'], 'legacy immediate webhook keeps environment key only');
}
