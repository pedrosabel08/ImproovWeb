<?php

declare(strict_types=1);

namespace FlowConnect\Contracts;

final class EventEnvelope
{
    public static function normalize(array $event): array
    {
        $event['event_version'] = (int) ($event['event_version'] ?? 1);
        $event['source_module'] = $event['source_module'] ?? 'flow_review';
        $event['event_uuid'] = self::validUuid($event['event_uuid'] ?? null) ? $event['event_uuid'] : self::uuidV4();
        $event['correlation_id'] = self::validUuid($event['correlation_id'] ?? null) ? $event['correlation_id'] : self::uuidV4();
        $event['causation_event_uuid'] = self::validUuid($event['causation_event_uuid'] ?? null)
            ? $event['causation_event_uuid']
            : null;
        $event['occurred_at'] = $event['occurred_at'] ?? gmdate('Y-m-d H:i:s');
        $event['actor_id'] = isset($event['actor_id']) && (int) $event['actor_id'] > 0 ? (int) $event['actor_id'] : null;
        $event['entity_id'] = (string) ($event['entity_id'] ?? '');
        $event['payload'] = is_array($event['payload'] ?? null) ? $event['payload'] : [];
        $event['metadata'] = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];
        return $event;
    }

    public static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }

    public static function validUuid($value): bool
    {
        return is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
