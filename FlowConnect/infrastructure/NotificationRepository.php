<?php

declare(strict_types=1);

namespace FlowConnect\Infrastructure;

use mysqli;
use RuntimeException;

final class NotificationRepository
{
    public function __construct(private mysqli $conn)
    {
    }

    public function create(array $plan): int
    {
        $payload = json_encode($plan['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $stmt = $this->conn->prepare("INSERT INTO flow_connect_notifications
            (event_id, notification_key, severity, category, delivery_mode, template_code, template_version, recipient_strategy, payload_json, status)
            VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id=LAST_INSERT_ID(id),
                status=IF(VALUES(delivery_mode)='SHADOW', 'READY', status)");
        if (!$stmt) {
            throw new RuntimeException('flow_connect_notification_prepare_failed');
        }
        // SHADOW segue o mesmo planejamento de uma entrega ativa. O worker é
        // que deve ignorar a notification marcada como SHADOW antes do Slack.
        $status = in_array($plan['delivery_mode'], ['HISTORY_ONLY', 'SUPPRESSED'], true) ? 'COMPLETED' : 'READY';
        $stmt->bind_param('issssssss', $plan['event_id'], $plan['notification_key'], $plan['severity'], $plan['category'], $plan['delivery_mode'], $plan['template_code'], $plan['recipient_strategy'], $payload, $status);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('flow_connect_notification_insert_failed');
        }
        $id = (int) $this->conn->insert_id;
        $stmt->close();
        return $id;
    }
}
