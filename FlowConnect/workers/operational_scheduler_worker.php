<?php

declare(strict_types=1);

require_once __DIR__ . '/_cli.php';

use FlowConnect\Application\OperationalCycleRepository;
use FlowConnect\Application\OperationalMilestonePolicy;
use FlowConnect\Application\OperationalPendingEventFactory;
use FlowConnect\Application\OperationalStateProvider;
use FlowConnect\Application\WorkerLoop;

$options = flow_connect_cli_options($argv);
$daemon = (bool) $options['daemon'];
$limit = max(1, min(200, (int) $options['limit']));
$verbose = (bool) $options['verbose'];
$config = flow_connect_config();
$timezone = new DateTimeZone((string) $config['operational']['business_timezone']);
$idleSeconds = (int) ($config['operational']['scheduler_idle_seconds'] ?? 1);
$policy = new OperationalMilestonePolicy();
$provider = new OperationalStateProvider();
$backoffSeconds = 1;

/** A scheduler only appends events/milestones; delivery remains a separate worker. */
$runBatch = static function () use ($limit, $policy, $provider, $timezone, $verbose, &$backoffSeconds): int {
    try {
        $conn = conectarBanco();
        $table = $conn->query("SHOW TABLES LIKE 'flow_connect_pending_cycles'");
        if (!$table || $table->num_rows === 0) {
            flow_connect_cli_log('operational_scheduler_worker skipped: migration 002 not applied', true);
            $conn->close();
            return 0;
        }
        $conn->begin_transaction();
        // Lock only the short read/append transaction.  No lock survives an
        // idle wait and the unique milestone key makes a repeated scan safe.
        $rows = $conn->query("SELECT * FROM flow_connect_pending_cycles WHERE status IN ('ACTIVE','PAUSED') AND due_at IS NOT NULL ORDER BY due_at ASC LIMIT {$limit} FOR UPDATE SKIP LOCKED");
        $fired = 0;
        while ($rows && ($cycle = $rows->fetch_assoc())) {
            $module = (string) $cycle['module_key'];
            $policyKey = (string) $cycle['policy_key'];
            if (flow_connect_operational_mode($module, $policyKey) === 'off') continue;

            $current = $provider->inspect($conn, $cycle);
            $state = (string) ($current['state'] ?? 'UNKNOWN');
            if ($state === 'RESOLVED' || $state === 'CANCELLED') {
                $context = json_decode((string) ($cycle['context_json'] ?? '{}'), true) ?: [];
                $context += [
                    'cycle_id' => (string) $cycle['cycle_id'],
                    'titulo' => 'Pendência',
                    'responsavel_id' => (int) ($cycle['responsavel_id'] ?? 0) ?: null,
                    'responsavel_cobranca_id' => (int) ($cycle['responsavel_cobranca_id'] ?? 0) ?: null,
                    'started_at' => (string) $cycle['started_at'],
                    'due_at' => (string) ($cycle['due_at'] ?? ''),
                    'business_timezone' => 'UTC',
                ];
                foreach (['responsavel_id', 'responsavel_cobranca_id', 'due_at', 'origin_url'] as $key) {
                    if (array_key_exists($key, $current) && $current[$key] !== null && $current[$key] !== '') $context[$key] = $current[$key];
                }
                $action = $state === 'RESOLVED' ? 'resolvida' : 'cancelada';
                $lifecycleLogs = [];
                flow_connect_publish_operational_pending($conn, $module, $policyKey, $action, (string) $cycle['entity_type'], (string) $cycle['entity_id'], $context, null, $lifecycleLogs);
                OperationalCycleRepository::closeFromProvider($conn, (int) $cycle['id'], $state);
                continue;
            }
            if ($state !== 'ACTIVE') continue; // PAUSED or unknown: never charge stale state.

            $context = json_decode((string) ($cycle['context_json'] ?? '{}'), true) ?: [];
            foreach (['responsavel_id', 'responsavel_cobranca_id', 'due_at', 'origin_url'] as $key) {
                if (array_key_exists($key, $current) && $current[$key] !== null && $current[$key] !== '') $context[$key] = $current[$key];
            }
            $context += ['titulo' => 'Pendência', 'module_key' => $module, 'cycle_id' => $cycle['cycle_id']];
            $start = new DateTimeImmutable((string) $cycle['started_at'], new DateTimeZone('UTC'));
            $due = new DateTimeImmutable((string) $cycle['due_at'], new DateTimeZone('UTC'));
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            foreach ($policy->dueMilestones($start, $due, $now) as $milestone) {
                $id = (int) $cycle['id'];
                $event = OperationalPendingEventFactory::milestone($cycle, $milestone, $context);
                $event['metadata']['flow_connect_mode'] = flow_connect_operational_mode($module, $policyKey);
                try {
                    $eventId = flow_connect_publish_in_transaction($conn, $event);
                    $uuid = (string) $event['event_uuid'];
                    $insert = $conn->prepare('INSERT IGNORE INTO flow_connect_pending_milestones (cycle_id,milestone,event_uuid) VALUES (?,?,?)');
                    $insert->bind_param('iss', $id, $milestone, $uuid);
                    $insert->execute();
                    if ($insert->affected_rows === 1) $fired++;
                    $insert->close();
                    // A duplicate event can be present from a crashed process;
                    // the unique milestone row still prevents a second delivery.
                    unset($eventId);
                } catch (Throwable $e) {
                    flow_connect_cli_log('operational milestone failed=' . flow_connect_safe_error($e->getMessage()), true);
                }
            }
        }
        $conn->commit();
        $conn->close();
        $backoffSeconds = 1;
        if ($fired > 0 || $verbose) flow_connect_cli_log("operational_scheduler_worker fired={$fired}", true);
        return $fired;
    } catch (Throwable $e) {
        // A connection failure is isolated to this iteration.  The next one
        // reconnects, with capped backoff rather than a busy retry loop.
        flow_connect_cli_log('operational scheduler error=' . flow_connect_safe_error($e->getMessage()), true);
        $backoffSeconds = min(30, max(2, $backoffSeconds * 2));
        return 0;
    }
};

$keepRunning = flow_connect_daemon_keep_running();
$idleWait = static function () use (&$backoffSeconds, $idleSeconds): void { usleep(max($idleSeconds, $backoffSeconds) * 1000000); };
(new WorkerLoop())->run($daemon, $runBatch, $keepRunning, $idleWait);
