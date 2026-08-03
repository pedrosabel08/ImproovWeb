<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/conexaoMain.php';

$conn = conectarBanco();
$tables = ['flow_connect_pending_cycles', 'flow_connect_pending_milestones', 'flow_connect_dead_letters'];
foreach ($tables as $table) {
    $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    if (!$r || $r->num_rows === 0) { echo json_encode(['status' => 'migration_not_applied', 'missing_table' => $table]) . PHP_EOL; $conn->close(); exit(0); }
}
$queries = [
    'schedules_vencidos' => "SELECT id,module_key,policy_key,entity_type,entity_id,due_at FROM flow_connect_pending_cycles WHERE status='ACTIVE' AND due_at < UTC_TIMESTAMP() ORDER BY due_at LIMIT 100",
    'dead_letters_abertas' => "SELECT id,reason_code,event_id,notification_id,delivery_id,last_failed_at FROM flow_connect_dead_letters WHERE resolved_at IS NULL ORDER BY last_failed_at DESC LIMIT 100",
    'deliveries_duplicadas' => "SELECT notification_id,destination_key,COUNT(*) total FROM flow_connect_deliveries GROUP BY notification_id,destination_key HAVING COUNT(*)>1 LIMIT 100",
    'marcos_faltantes' => "SELECT c.id,c.module_key,c.entity_type,c.entity_id,c.due_at FROM flow_connect_pending_cycles c LEFT JOIN flow_connect_pending_milestones m ON m.cycle_id=c.id AND m.milestone='EXPIRED' WHERE c.status='ACTIVE' AND c.due_at < UTC_TIMESTAMP() AND m.id IS NULL LIMIT 100",
];
$out = [];
foreach ($queries as $key => $sql) { $r = $conn->query($sql); $out[$key] = $r ? $r->fetch_all(MYSQLI_ASSOC) : ['error' => 'query_failed']; }
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
$conn->close();
