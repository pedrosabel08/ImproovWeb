<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/FlowReviewMentionIntegrationTest.php';
require_once __DIR__ . '/FlowReviewAngleIntegrationTest.php';
require_once __DIR__ . '/FlowReviewTaskIntegrationTest.php';
require_once __DIR__ . '/FlowReviewDirectionIntegrationTest.php';
require_once __DIR__ . '/FlowReviewSftpIntegrationTest.php';
require_once __DIR__ . '/FlowReviewSlaIntegrationTest.php';
require_once __DIR__ . '/WorkerIntegrationTest.php';
require_once __DIR__ . '/ShadowModeIntegrationTest.php';

$ctx = new FlowConnectIntegrationContext();
$tests = [
    'shadow_mode' => 'fc_it_shadow_mode',
    'mentions' => 'fc_it_mentions',
    'angles' => 'fc_it_angles',
    'tasks' => 'fc_it_tasks',
    'direction' => 'fc_it_direction',
    'sftp' => 'fc_it_sftp',
    'sla' => 'fc_it_sla',
    'workers' => 'fc_it_workers',
];
$result = ['started_at_utc' => gmdate('c'), 'prefix' => $ctx->prefix, 'tests' => [], 'evidence' => []];
$failed = false;
try {
    foreach ($tests as $name => $test) {
        try {
            $test($ctx);
            $result['tests'][$name] = 'PASS';
        } catch (Throwable $e) {
            $result['tests'][$name] = 'FAIL: ' . preg_replace('/[^a-zA-Z0-9_ .:-]/', '_', $e->getMessage());
            $failed = true;
            break;
        }
    }
    $result['evidence'] = $ctx->evidence;
} finally {
    $ctx->cleanup();
    $ctx->conn->close();
}
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($failed ? 1 : 0);
