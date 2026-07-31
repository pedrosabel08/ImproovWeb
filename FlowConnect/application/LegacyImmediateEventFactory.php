<?php

declare(strict_types=1);

namespace FlowConnect\Application;

use FlowConnect\Contracts\EventEnvelope;

final class LegacyImmediateEventFactory
{
    public static function make(string $eventType, string $entityType, string|int $entityId, array $payload, ?int $recipientId, ?string $webhookEnv, string $idempotencyKey, string $module): array
    {
        $payload['message'] = trim((string) ($payload['message'] ?? ''));
        $payload['recipient_collaborator_id'] = $recipientId;
        $payload['webhook_env'] = $webhookEnv;
        return EventEnvelope::normalize([
            'event_type' => $eventType,
            'event_version' => 1,
            'source_module' => $module,
            'entity_type' => $entityType,
            'entity_id' => (string) $entityId,
            'actor_id' => null,
            'occurred_at' => gmdate('Y-m-d H:i:s'),
            'event_uuid' => null,
            'correlation_id' => function_exists('flow_connect_request_correlation_id') ? flow_connect_request_correlation_id() : EventEnvelope::uuidV4(),
            'causation_event_uuid' => null,
            'idempotency_key' => substr($idempotencyKey, 0, 255),
            'payload' => $payload,
            'metadata' => ['producer' => $module, 'environment' => getenv('APP_ENV') ?: 'local'],
        ]);
    }
}
