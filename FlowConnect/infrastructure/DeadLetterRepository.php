<?php

declare(strict_types=1);

namespace FlowConnect\Infrastructure;

use mysqli;

final class DeadLetterRepository
{
    public function __construct(private mysqli $conn)
    {
    }

    public function record(?int $eventId, ?int $notificationId, ?int $deliveryId, string $reasonCode, array $safePayload): void
    {
        $json = json_encode($safePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $dedupeKey = hash('sha256', implode(':', [$eventId ?: 0, $notificationId ?: 0, $deliveryId ?: 0, $reasonCode]));
        $stmt = $this->conn->prepare("INSERT INTO flow_connect_dead_letters
            (event_id, notification_id, delivery_id, reason_code, dedupe_key, payload_safe_json, first_failed_at, last_failed_at)
            VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
            ON DUPLICATE KEY UPDATE last_failed_at=VALUES(last_failed_at), payload_safe_json=VALUES(payload_safe_json)");
        $stmt->bind_param('iiisss', $eventId, $notificationId, $deliveryId, $reasonCode, $dedupeKey, $json);
        $stmt->execute();
        $stmt->close();
    }
}
