<?php

declare(strict_types=1);

require_once __DIR__ . '/_cli.php';

use FlowConnect\Application\PendingSummary\PendingSummaryCollector;
use FlowConnect\Application\PendingSummary\PendingSummaryFactory;
use FlowConnect\Application\PendingSummary\PendingSummaryProviderRegistry;

$options = flow_connect_cli_options($argv);
$config = flow_connect_config();
$summaryConfig = $config['operational']['pending_summary'] ?? [];
if (($summaryConfig['mode'] ?? 'shadow') === 'off') {
    flow_connect_cli_log('pending_summary_worker skipped: policy off', true);
    exit(0);
}
$conn = conectarBanco();
try {
    $table = $conn->query("SHOW TABLES LIKE 'flow_connect_pending_summary_windows'");
    if (!$table || $table->num_rows === 0) throw new RuntimeException('pending_summary_migration_missing');
    $tz = new DateTimeZone((string) ($config['operational']['business_timezone'] ?? 'America/Sao_Paulo'));
    $now = new DateTimeImmutable('now', $tz);
    $time = $now->format('H:i');
    if (!in_array($time, $summaryConfig['times'] ?? [], true)) {
        flow_connect_cli_log('pending_summary_worker skipped: outside configured window', (bool) $options['verbose']);
        exit(0);
    }
    $windowKey = $now->format('Y-m-d') . 'T' . $time;
    $result = (new PendingSummaryCollector(PendingSummaryProviderRegistry::registered($config)))->collect($conn);
    if ($options['collaborator_id'] !== null) {
        $target = (int) $options['collaborator_id'];
        $result['summaries'] = isset($result['summaries'][$target]) ? [$target => $result['summaries'][$target]] : [];
        flow_connect_cli_log("pending_summary filter collaborator={$target}", true);
    }
    foreach ($result['summaries'] as $collaboratorId => $modules) {
        $total = array_sum(array_map(static fn(array $module): int => (int) $module['total'], $modules));
        if ($total <= 0) continue;
        $event = PendingSummaryFactory::event((int) $collaboratorId, $windowKey, $modules, $config, $result['providers_success'], $result['providers_failed']);
        $conn->begin_transaction();
        try {
            $check = $conn->prepare('SELECT id FROM flow_connect_pending_summary_windows WHERE policy_key=? AND window_key=? AND collaborator_id=? FOR UPDATE');
            $policyKey = 'pending.summary.v1';
            $check->bind_param('ssi', $policyKey, $windowKey, $collaboratorId);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();
            if ($exists) { $conn->commit(); continue; }
            flow_connect_publish_in_transaction($conn, $event);
            $eventUuid = $event['event_uuid'];
            $insert = $conn->prepare('INSERT INTO flow_connect_pending_summary_windows (policy_key,window_key,collaborator_id,event_uuid) VALUES (?,?,?,?)');
            $insert->bind_param('ssis', $policyKey, $windowKey, $collaboratorId, $eventUuid);
            $insert->execute();
            $insert->close();
            $conn->commit();
            flow_connect_cli_log("pending_summary window={$windowKey} collaborator={$collaboratorId} total={$total} modules=" . count($modules) . ' providers_success=' . count($result['providers_success']) . ' providers_failed=' . count($result['providers_failed']) . " event_uuid={$eventUuid} status=PUBLISHED", true);
        } catch (Throwable $e) {
            $conn->rollback();
            flow_connect_cli_log("pending_summary window={$windowKey} collaborator={$collaboratorId} status=FAILED error=" . flow_connect_safe_error($e->getMessage()), true);
        }
    }
} catch (Throwable $e) {
    flow_connect_cli_log('pending_summary_worker failed=' . flow_connect_safe_error($e->getMessage()), true);
    $conn->close();
    exit(1);
}
$conn->close();
