<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/conexaoMain.php';

$conn = conectarBanco();
$window = trim((string) ($argv[1] ?? ''));
if ($window === '') {
    fwrite(STDERR, "Uso: php inspect_pending_summary_shadow.php <YYYY-MM-DDTHH:MM>\n");
    exit(2);
}
$stmt = $conn->prepare("SELECT e.id,e.event_uuid,e.status,e.payload_json,n.id notification_id,n.delivery_mode,d.id delivery_id,d.collaborator_id,d.status delivery_status FROM flow_connect_events e LEFT JOIN flow_connect_notifications n ON n.event_id=e.id LEFT JOIN flow_connect_deliveries d ON d.notification_id=n.id WHERE e.event_type='pending.summary.ready' AND JSON_UNQUOTE(JSON_EXTRACT(e.payload_json,'$.window_key'))=? ORDER BY e.id,d.id");
$stmt->bind_param('s', $window);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$summaries = [];
foreach ($rows as $row) {
    $payload = json_decode((string) $row['payload_json'], true) ?: [];
    $id = (int) ($payload['collaborator_id'] ?? 0);
    $summaries[$id] ??= ['collaborator_id' => $id, 'total_pending' => (int) ($payload['total_pending'] ?? 0), 'upload_total' => 0, 'recipients' => [], 'event_uuid' => $row['event_uuid'], 'delivery_mode' => $row['delivery_mode']];
    foreach ($payload['modules'] ?? [] as $module) if (($module['module_key'] ?? '') === 'arquivo') $summaries[$id]['upload_total'] = (int) ($module['total'] ?? 0);
    if ($row['collaborator_id'] !== null) $summaries[$id]['recipients'][(int) $row['collaborator_id']] = true;
}
$legacy = [];
$query = "SELECT fi.colaborador_id,COUNT(DISTINCT fi.idfuncao_imagem) total FROM funcao_imagem fi JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=fi.imagem_id JOIN obra o ON o.idobra=i.obra_id WHERE fi.requires_file_upload=1 AND fi.file_uploaded_at IS NULL AND fi.colaborador_id IS NOT NULL AND o.status_obra=0 GROUP BY fi.colaborador_id";
if ($result = $conn->query($query)) while ($row = $result->fetch_assoc()) $legacy[(int) $row['colaborador_id']] = (int) $row['total'];
foreach ($summaries as &$summary) { $summary['recipients'] = array_keys($summary['recipients']); $summary['legacy_upload_total'] = $legacy[$summary['collaborator_id']] ?? 0; $summary['upload_matches_legacy'] = $summary['upload_total'] === $summary['legacy_upload_total']; } unset($summary);
echo json_encode(['window_key' => $window, 'summaries' => array_values($summaries), 'legacy_only_collaborators' => array_values(array_diff(array_keys($legacy), array_keys($summaries)))], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
$conn->close();
