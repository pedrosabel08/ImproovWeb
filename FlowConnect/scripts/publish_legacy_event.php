<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/conexaoMain.php';

$input = json_decode((string) base64_decode((string) ($argv[1] ?? ''), true), true);
if (!is_array($input)) exit(2);
$conn = conectarBanco();
$logs = [];
$eventId = flow_connect_publish_legacy_immediate(
    $conn,
    (string) ($input['module'] ?? ''),
    (string) ($input['event_type'] ?? ''),
    (string) ($input['entity_type'] ?? ''),
    (string) ($input['entity_id'] ?? ''),
    (string) ($input['message'] ?? ''),
    isset($input['recipient_collaborator_id']) ? (int) $input['recipient_collaborator_id'] : null,
    isset($input['webhook_env']) ? (string) $input['webhook_env'] : null,
    (string) ($input['idempotency_key'] ?? ''),
    $logs
);
$conn->close();
echo json_encode(['event_id' => $eventId, 'bypass_legacy' => flow_connect_legacy_should_bypass((string) ($input['module'] ?? ''), $eventId), 'logs' => $logs], JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($eventId > 0 || flow_connect_normalize_mode(getenv('FLOW_CONNECT_' . strtoupper(str_replace('-', '_', (string) ($input['module'] ?? ''))) . '_MODE') ?: 'off') === 'off' ? 0 : 1);
