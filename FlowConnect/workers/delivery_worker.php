<?php

declare(strict_types=1);

require_once __DIR__ . '/_cli.php';

use FlowConnect\Application\RetryPolicy;
use FlowConnect\Application\WorkerLoop;
use FlowConnect\Channels\SlackApiAdapter;
use FlowConnect\Infrastructure\DeadLetterRepository;
use FlowConnect\Infrastructure\DeliveryRepository;

$options = flow_connect_cli_options($argv);
$conn = conectarBanco();
$config = flow_connect_config();
$deliveries = new DeliveryRepository($conn, (int) $config['claim_ttl_seconds']);
$deadLetters = new DeadLetterRepository($conn);
$adapter = new SlackApiAdapter($config['slack']);
$retry = new RetryPolicy();
$workerId = flow_connect_worker_id('delivery');

try {
    $keepRunning = flow_connect_daemon_keep_running();
    (new WorkerLoop())->run((bool) $options['daemon'], function () use ($deliveries, $options, $workerId, $adapter, $retry, $deadLetters): int {
    $batch = $deliveries->claimEligible((int) $options['limit'], $workerId);
    flow_connect_cli_log('delivery_worker claimed=' . count($batch), (bool) $options['verbose']);
    foreach ($batch as $delivery) {
        // A chamada externa acontece fora da transação de claim.
        $result = $adapter->send($delivery);
        $decision = $retry->decide($result, (int) $delivery['attempt_count']);
        $deliveries->completeAttempt($delivery, $result, $decision);
        if ($decision['status'] === 'DEAD') {
            $deadLetters->record(null, (int) $delivery['notification_id'], (int) $delivery['id'], 'delivery_exhausted', [
                'error_code' => $result['error_code'] ?? 'unknown',
                'safe_error' => $result['safe_error'] ?? 'delivery_failed',
            ]);
        }
        flow_connect_cli_log("delivery={$delivery['id']} status={$decision['status']} error=" . ($result['error_code'] ?? '-'), (bool) $options['verbose']);
    }
    return count($batch);
    }, $keepRunning, 'flow_connect_daemon_idle_wait');
} catch (Throwable $e) {
    flow_connect_cli_log('delivery_worker fatal=' . flow_connect_safe_error($e->getMessage()), true);
    $conn->close();
    exit(1);
}

$conn->close();
