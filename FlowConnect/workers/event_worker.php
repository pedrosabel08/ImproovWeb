<?php

declare(strict_types=1);

require_once __DIR__ . '/_cli.php';

use FlowConnect\Application\EventPlanner;
use FlowConnect\Application\WorkerLoop;
use FlowConnect\Contracts\EventValidator;
use FlowConnect\Infrastructure\DeadLetterRepository;
use FlowConnect\Infrastructure\EventRepository;

$options = flow_connect_cli_options($argv);
$conn = conectarBanco();
$config = flow_connect_config();
$events = new EventRepository($conn, (int) $config['claim_ttl_seconds']);
$deadLetters = new DeadLetterRepository($conn);
$planner = new EventPlanner($conn, $config);
$workerId = flow_connect_worker_id('event');

try {
    $keepRunning = flow_connect_daemon_keep_running();
    (new WorkerLoop())->run((bool) $options['daemon'], function () use ($events, $options, $workerId, $planner, $deadLetters): int {
    $batch = $events->claimPending((int) $options['limit'], $workerId);
    flow_connect_cli_log('event_worker claimed=' . count($batch), (bool) $options['verbose']);
    foreach ($batch as $event) {
        try {
            EventValidator::validate($event);
            $plan = $planner->plan($event);
            $events->markProcessed((int) $event['id']);
            flow_connect_cli_log("event={$event['id']} processed mode={$plan['delivery_mode']} notification={$plan['notification_id']}", (bool) $options['verbose']);
        } catch (Throwable $e) {
            $safe = flow_connect_safe_error($e->getMessage(), 'event_planning_failed');
            $dead = ((int) ($event['failure_count'] ?? 0) + 1) >= 3;
            $events->markFailed((int) $event['id'], 'planning_failed', $safe, $dead);
            if ($dead) $deadLetters->record((int) $event['id'], null, null, 'event_planning_failed', ['error' => $safe]);
            flow_connect_cli_log("event={$event['id']} failed dead=" . ($dead ? 'yes' : 'no') . " error={$safe}", true);
        }
    }
    return count($batch);
    }, $keepRunning, 'flow_connect_daemon_idle_wait');
} catch (Throwable $e) {
    flow_connect_cli_log('event_worker fatal=' . flow_connect_safe_error($e->getMessage()), true);
    $conn->close();
    exit(1);
}

$conn->close();
