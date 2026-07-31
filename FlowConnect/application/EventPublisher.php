<?php

declare(strict_types=1);

namespace FlowConnect\Application;

use FlowConnect\Contracts\EventEnvelope;
use FlowConnect\Contracts\EventValidator;
use mysqli;
use RuntimeException;

final class EventPublisher
{
    public function publishInTransaction(mysqli $conn, array $event): int
    {
        $event = EventEnvelope::normalize($event);
        EventValidator::validate($event);

        $payloadJson = json_encode($event['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $metadataJson = json_encode($event['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $actorId = $event['actor_id'];
        $causation = $event['causation_event_uuid'];

        $sql = "INSERT INTO flow_connect_events
            (event_uuid, event_type, event_version, source_module, entity_type, entity_id, actor_id,
             occurred_at, correlation_id, causation_event_uuid, idempotency_key, payload_json, metadata_json, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('flow_connect_event_prepare_failed');
        }
        $stmt->bind_param(
            'ssisssissssss',
            $event['event_uuid'],
            $event['event_type'],
            $event['event_version'],
            $event['source_module'],
            $event['entity_type'],
            $event['entity_id'],
            $actorId,
            $event['occurred_at'],
            $event['correlation_id'],
            $causation,
            $event['idempotency_key'],
            $payloadJson,
            $metadataJson
        );
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('flow_connect_event_insert_failed');
        }
        $eventId = (int) $conn->insert_id;
        $stmt->close();

        if ($eventId <= 0) {
            throw new RuntimeException('flow_connect_event_id_missing');
        }
        return $eventId;
    }
}
