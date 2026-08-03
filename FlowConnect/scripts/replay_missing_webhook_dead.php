<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/conexaoMain.php';

use FlowConnect\Application\RetryPolicy;
use FlowConnect\Channels\SlackApiAdapter;
use FlowConnect\Infrastructure\DeadLetterRepository;
use FlowConnect\Infrastructure\DeliveryRepository;

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$apply = in_array('--apply', $argv, true);
$expected = 34;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--expect=')) {
        $expected = max(1, min(500, (int) substr($arg, strlen('--expect='))));
    }
}

$conn = conectarBanco();
$where = "d.status='DEAD'
    AND d.sent_at IS NULL
    AND d.destination_kind='WEBHOOK'
    AND d.last_error_code IN ('missing_webhook', 'missing_webhook_destination')
    AND ((e.event_type='contratos.documento.status_atualizado' AND d.destination_key='SLACK_WEBHOOK_CONTRATOS_URL')
      OR (e.event_type='pos.imagem.finalizada' AND d.destination_key='SLACK_WEBHOOK_POS_URL'))";

$conn->begin_transaction();
try {
    $result = $conn->query("SELECT d.*, n.delivery_mode, e.event_type
        FROM flow_connect_deliveries d
        INNER JOIN flow_connect_notifications n ON n.id=d.notification_id
        INNER JOIN flow_connect_events e ON e.id=n.event_id
        WHERE {$where}
        ORDER BY d.id ASC FOR UPDATE");
    if (!$result) throw new RuntimeException('replay_select_failed');

    $deliveries = [];
    while ($delivery = $result->fetch_assoc()) {
        $delivery['id'] = (int) $delivery['id'];
        $delivery['notification_id'] = (int) $delivery['notification_id'];
        $delivery['attempt_count'] = (int) $delivery['attempt_count'];
        $delivery['rendered_blocks'] = json_decode((string) ($delivery['rendered_blocks_json'] ?? ''), true);
        $deliveries[] = $delivery;
    }

    $ids = array_column($deliveries, 'id');
    $summary = [
        'apply' => $apply,
        'expected' => $expected,
        'selected' => count($deliveries),
        'ids' => $ids,
        'by_event' => array_count_values(array_map(static fn(array $delivery): string => (string) $delivery['event_type'], $deliveries)),
    ];

    if (!$apply) {
        $conn->rollback();
        echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        $conn->close();
        exit(0);
    }
    if (count($deliveries) !== $expected) {
        $conn->rollback();
        fwrite(STDERR, json_encode($summary + ['error' => 'unexpected_delivery_count'], JSON_UNESCAPED_UNICODE) . PHP_EOL);
        $conn->close();
        exit(2);
    }

    $idList = implode(',', array_map('intval', $ids));
    $workerId = 'replay-missing-webhook:' . gethostname() . ':' . getmypid();
    $safeWorkerId = $conn->real_escape_string(substr($workerId, 0, 120));
    if (!$conn->query("UPDATE flow_connect_deliveries
        SET status='SENDING', claimed_by='{$safeWorkerId}', claimed_at=UTC_TIMESTAMP(6),
            claim_expires_at=DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 15 MINUTE),
            last_error_code=NULL, last_error_safe=NULL, next_attempt_at=NULL
        WHERE id IN ({$idList}) AND status='DEAD' AND sent_at IS NULL")) {
        throw new RuntimeException('replay_claim_failed');
    }
    if ($conn->affected_rows !== count($deliveries)) throw new RuntimeException('replay_claim_count_mismatch');

    $conn->query("UPDATE flow_connect_notifications n
        INNER JOIN flow_connect_deliveries d ON d.notification_id=n.id
        SET n.status='READY', n.completed_at=NULL
        WHERE d.id IN ({$idList})");
    $conn->query("UPDATE flow_connect_dead_letters
        SET reprocessed_at=UTC_TIMESTAMP(6)
        WHERE delivery_id IN ({$idList}) AND reprocessed_at IS NULL");
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    $conn->close();
    fwrite(STDERR, 'replay_prepare_failed:' . flow_connect_safe_error($e->getMessage()) . PHP_EOL);
    exit(1);
}

$config = flow_connect_config();
$adapter = new SlackApiAdapter($config['slack']);
$retry = new RetryPolicy();
$repository = new DeliveryRepository($conn, (int) $config['claim_ttl_seconds']);
$deadLetters = new DeadLetterRepository($conn);
$outcomes = [];
foreach ($deliveries as $delivery) {
    $result = $adapter->send($delivery);
    $decision = $retry->decide($result, (int) $delivery['attempt_count']);
    $repository->completeAttempt($delivery, $result, $decision);
    if ($decision['status'] === 'SENT') {
        $resolve = $conn->prepare("UPDATE flow_connect_dead_letters
            SET resolved_at=UTC_TIMESTAMP(6)
            WHERE delivery_id=? AND reprocessed_at IS NOT NULL AND resolved_at IS NULL");
        $deliveryId = (int) $delivery['id'];
        $resolve->bind_param('i', $deliveryId);
        $resolve->execute();
        $resolve->close();
    } elseif ($decision['status'] === 'DEAD') {
        $deadLetters->record(null, (int) $delivery['notification_id'], (int) $delivery['id'], 'delivery_exhausted', [
            'error_code' => $result['error_code'] ?? 'unknown',
            'safe_error' => $result['safe_error'] ?? 'delivery_failed',
        ]);
    }
    $outcomes[] = ['delivery_id' => (int) $delivery['id'], 'status' => $decision['status'], 'error_code' => $result['error_code'] ?? null];
}

$conn->close();
echo json_encode($summary + ['outcomes' => $outcomes], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
