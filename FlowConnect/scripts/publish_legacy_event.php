<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/conexaoMain.php';

$input = json_decode((string) base64_decode((string) ($argv[1] ?? ''), true), true);
if (!is_array($input)) exit(2);
$conn = conectarBanco();
$logs = [];
$operational = is_array($input['operational'] ?? null) ? $input['operational'] : null;
if ($operational !== null) {
    $eventId = flow_connect_publish_operational_pending(
        $conn,
        (string) ($operational['module_key'] ?? ''),
        (string) ($operational['policy_key'] ?? ''),
        (string) ($operational['action'] ?? 'criada'),
        (string) ($input['entity_type'] ?? ''),
        (string) ($input['entity_id'] ?? ''),
        is_array($operational['payload'] ?? null) ? $operational['payload'] : [],
        isset($operational['actor_id']) ? (int) $operational['actor_id'] : null,
        $logs
    );
    $bypass = flow_connect_operational_should_bypass((string) ($operational['module_key'] ?? ''), (string) ($operational['policy_key'] ?? ''), $eventId);
} else {
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
    $bypass = flow_connect_legacy_should_bypass((string) ($input['module'] ?? ''), $eventId);
}
$conn->close();
echo json_encode(['event_id' => $eventId, 'bypass_legacy' => $bypass, 'logs' => $logs], JSON_UNESCAPED_UNICODE) . PHP_EOL;
$mode = $operational !== null ? flow_connect_operational_mode((string) ($operational['module_key'] ?? ''), (string) ($operational['policy_key'] ?? '')) : flow_connect_normalize_mode(getenv('FLOW_CONNECT_' . strtoupper(str_replace('-', '_', (string) ($input['module'] ?? ''))) . '_MODE') ?: 'off');
exit($eventId > 0 || $mode === 'off' ? 0 : 1);
