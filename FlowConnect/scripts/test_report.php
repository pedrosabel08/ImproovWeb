<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/conexaoMain.php';

if (php_sapi_name() !== 'cli') exit("CLI only\n");

$filters = ['started-at' => null, 'event-family' => null, 'correlation-id' => null];
foreach ($argv as $arg) foreach (array_keys($filters) as $key) if (str_starts_with($arg, "--{$key}=")) $filters[$key] = substr($arg, strlen($key) + 3);
$conn = conectarBanco();
$where = ['1=1'];
if ($filters['started-at']) {
    $rawStartedAt = (string) $filters['started-at'];
    // received_at usa o fuso do MySQL local. Aceita ISO 8601 em UTC para a
    // bateria e converte somente o filtro para America/Sao_Paulo.
    if (preg_match('/(?:Z|[+-]\\d{2}:?\\d{2})$/', $rawStartedAt)) {
        try {
            $rawStartedAt = (new DateTimeImmutable($rawStartedAt))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            fwrite(STDERR, "started-at inválido\\n");
            exit(2);
        }
    }
    $filters['started-at-normalized'] = $rawStartedAt;
    $where[] = "e.received_at >= '" . $conn->real_escape_string($rawStartedAt) . "'";
}
if ($filters['event-family']) $where[] = "e.event_type LIKE '" . $conn->real_escape_string((string) $filters['event-family']) . ".%'";
if ($filters['correlation-id']) $where[] = "e.correlation_id='" . $conn->real_escape_string((string) $filters['correlation-id']) . "'";
$sql = "SELECT e.id event_id, e.event_type, e.status event_status, e.idempotency_key, n.id notification_id, n.delivery_mode, n.status notification_status, d.id delivery_id, d.destination_kind, d.collaborator_id, d.status delivery_status, d.attempt_count, d.sent_at, CHAR_LENGTH(d.rendered_text) preview_length, COUNT(a.id) attempt_rows
        FROM flow_connect_events e
        LEFT JOIN flow_connect_notifications n ON n.event_id=e.id
        LEFT JOIN flow_connect_deliveries d ON d.notification_id=n.id
        LEFT JOIN flow_connect_delivery_attempts a ON a.delivery_id=d.id
        WHERE " . implode(' AND ', $where) . " GROUP BY e.id,n.id,d.id ORDER BY e.id,d.id";
$rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
$summary = ['events' => count(array_unique(array_column($rows, 'event_id'))), 'notifications' => count(array_unique(array_filter(array_column($rows, 'notification_id')))), 'deliveries' => count(array_unique(array_filter(array_column($rows, 'delivery_id')))), 'attempt_rows' => array_sum(array_map(static fn($row) => (int) $row['attempt_rows'], $rows))];
echo json_encode(['filters' => $filters, 'summary' => $summary, 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
$conn->close();
