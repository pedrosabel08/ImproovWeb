<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/conexaoMain.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$filters = ['event-type' => null, 'correlation-id' => null, 'entity-id' => null, 'limit' => 20];
foreach ($argv as $arg) {
    foreach (array_keys($filters) as $key) {
        if (str_starts_with($arg, "--{$key}=")) $filters[$key] = substr($arg, strlen($key) + 3);
    }
}
$limit = max(1, min(200, (int) $filters['limit']));
$conn = conectarBanco();
$where = ["JSON_UNQUOTE(JSON_EXTRACT(e.metadata_json, '$.flow_connect_mode'))='shadow'"];
if ($filters['event-type']) $where[] = "e.event_type='" . $conn->real_escape_string((string) $filters['event-type']) . "'";
if ($filters['correlation-id']) $where[] = "e.correlation_id='" . $conn->real_escape_string((string) $filters['correlation-id']) . "'";
if ($filters['entity-id']) $where[] = "e.entity_id='" . $conn->real_escape_string((string) $filters['entity-id']) . "'";

$sql = "SELECT e.id event_id, e.event_uuid, e.event_type, e.correlation_id, e.entity_type, e.entity_id, e.payload_json,
               n.id notification_id, n.severity, n.category, n.delivery_mode, n.template_code, n.recipient_strategy,
               d.id delivery_id, d.destination_kind, d.collaborator_id, d.slack_user_id, d.status delivery_status,
               d.rendered_text, d.last_error_code, d.last_error_safe
        FROM flow_connect_events e
        LEFT JOIN flow_connect_notifications n ON n.event_id=e.id
        LEFT JOIN flow_connect_deliveries d ON d.notification_id=n.id
        WHERE " . implode(' AND ', $where) . " ORDER BY e.id DESC, d.id ASC LIMIT {$limit}";
$result = $conn->query($sql);
if (!$result) {
    fwrite(STDERR, 'Falha ao consultar shadow. A migration foi aplicada? Erro seguro: query_failed' . PHP_EOL);
    exit(1);
}
while ($row = $result->fetch_assoc()) {
    $payload = json_decode((string) $row['payload_json'], true) ?: [];
    $safeSummary = array_intersect_key($payload, array_flip([
        'comentario_id', 'resposta_id', 'mencao_id', 'funcao_imagem_id', 'funcao_animacao_id',
        'imagem_id', 'obra_id', 'funcao_id', 'mencionado_id', 'status_anterior', 'status_novo',
        'decisao', 'tipo_fluxo', 'tempo_em_aprovacao', 'limite_sla',
    ]));
    $hasDelivery = $row['delivery_id'] !== null;
    $identityUnresolved = $hasDelivery
        && (string) ($row['destination_kind'] ?? '') !== 'CHANNEL'
        && trim((string) ($row['slack_user_id'] ?? '')) === '';
    echo json_encode([
        'event' => array_intersect_key($row, array_flip(['event_id', 'event_uuid', 'event_type', 'correlation_id', 'entity_type', 'entity_id'])),
        'payload_summary' => $safeSummary,
        'plan' => array_intersect_key($row, array_flip(['notification_id', 'severity', 'category', 'delivery_mode', 'template_code', 'recipient_strategy'])),
        'recipient' => array_intersect_key($row, array_flip(['delivery_id', 'destination_kind', 'collaborator_id', 'slack_user_id', 'delivery_status'])),
        'message_preview' => $row['rendered_text'],
        'preview_available' => trim((string) ($row['rendered_text'] ?? '')) !== '',
        'divergence' => $row['last_error_code'] ?: ($identityUnresolved ? 'slack_identity_unresolved' : ($hasDelivery ? null : 'delivery_not_planned')),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
}
$conn->close();
