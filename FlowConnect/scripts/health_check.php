<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/conexaoMain.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$requiredTables = [
    'flow_connect_events', 'flow_connect_notifications', 'flow_connect_deliveries',
    'flow_connect_delivery_attempts', 'flow_connect_schedules',
    'flow_connect_slack_identities', 'flow_connect_dead_letters',
];
$report = ['ok' => true, 'tables' => [], 'modes' => [], 'warnings' => []];
try {
    $conn = conectarBanco();
    foreach ($requiredTables as $table) {
        $safe = $conn->real_escape_string($table);
        $result = $conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$safe}' LIMIT 1");
        $exists = $result && $result->num_rows === 1;
        $report['tables'][$table] = $exists;
        if (!$exists) $report['ok'] = false;
    }
    if ($report['tables']['flow_connect_slack_identities']) {
        $row = $conn->query("SELECT COUNT(*) total, SUM(status='ACTIVE') active_count, SUM(status='UNRESOLVED') unresolved_count FROM flow_connect_slack_identities")->fetch_assoc();
        $report['identities'] = array_map('intval', $row);
    }
    $conn->close();
} catch (Throwable $e) {
    $report['ok'] = false;
    $report['database'] = 'connection_failed';
}

foreach (['mention', 'angle', 'task', 'direction', 'sftp', 'sla'] as $family) {
    $report['modes'][$family] = flow_connect_review_mode($family);
}
if (in_array('active', $report['modes'], true)) {
    $report['warnings'][] = 'Há família em active. Esta etapa recomenda manter todas em off até migration, sync e shadow aprovados.';
}
if ((getenv('SLACK_TOKEN') ?: '') === '') $report['warnings'][] = 'SLACK_TOKEN ausente; delivery e sync não funcionarão.';
$config = flow_connect_config();
foreach (['direction_group', 'flow_review_managers', 'technical_admins'] as $role) {
    if (empty($config['flow_review']['roles'][$role])) $report['warnings'][] = "Papel {$role} sem colaboradores configurados.";
}

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($report['ok'] ? 0 : 2);
