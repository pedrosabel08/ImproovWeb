<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/conexaoMain.php';
$ids = array_values(array_filter(array_map('intval', array_slice($argv, 1)), static fn (int $id): bool => $id > 0));
if ($ids === []) { fwrite(STDERR, "Uso: php inspect_shadow_events.php <event_id> [event_id...]\n"); exit(2); }
$conn = conectarBanco();
$list = implode(',', $ids);
$sql = "SELECT e.id,e.event_type,e.status,e.failure_count,e.last_error_code,e.last_error_safe,
               n.id AS notification_id,n.delivery_mode,
               d.id AS delivery_id,d.collaborator_id,d.slack_user_id,d.status AS delivery_status,
               LEFT(d.rendered_text,160) AS rendered_text
          FROM flow_connect_events e
          LEFT JOIN flow_connect_notifications n ON n.event_id=e.id
          LEFT JOIN flow_connect_deliveries d ON d.notification_id=n.id
         WHERE e.id IN ({$list}) ORDER BY e.id,n.id,d.id";
$rows = [];
if ($result = $conn->query($sql)) while ($row = $result->fetch_assoc()) $rows[] = $row;
$attempts = 0;
$sql = "SELECT COUNT(*) AS total
          FROM flow_connect_delivery_attempts da
          JOIN flow_connect_deliveries d ON d.id=da.delivery_id
          JOIN flow_connect_notifications n ON n.id=d.notification_id
         WHERE n.delivery_mode='SHADOW' AND n.event_id IN ({$list})";
if ($result = $conn->query($sql)) $attempts = (int) ($result->fetch_assoc()['total'] ?? 0);
echo json_encode(['events' => $rows, 'shadow_attempts' => $attempts], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
$conn->close();
