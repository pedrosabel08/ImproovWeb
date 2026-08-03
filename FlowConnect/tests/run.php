<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/EventContractTest.php';
require_once __DIR__ . '/ModeAndRecipientTest.php';
require_once __DIR__ . '/TemplateRetrySlaTest.php';
require_once __DIR__ . '/StaticArchitectureTest.php';
require_once __DIR__ . '/TestModeConfigTest.php';
require_once __DIR__ . '/WorkerDaemonTest.php';
require_once __DIR__ . '/OperationalPendingTest.php';

$tests = [
    'event_contracts' => 'flow_connect_test_event_contracts',
    'modes_and_recipients' => 'flow_connect_test_modes_and_recipients',
    'templates_retry_sla' => 'flow_connect_test_templates_retry_sla',
    'static_architecture' => 'flow_connect_test_static_architecture',
    'test_mode_config' => 'flow_connect_test_test_mode_config',
    'worker_daemon' => 'flow_connect_test_worker_daemon',
    'operational_pending' => 'flow_connect_test_operational_pending',
];
$failed = [];
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "PASS {$name}\n";
    } catch (Throwable $e) {
        $failed[$name] = $e->getMessage();
        echo "FAIL {$name}: {$e->getMessage()}\n";
    }
}
echo 'Assertions: ' . (int)$GLOBALS['flow_connect_test_count'] . PHP_EOL;
if ($failed !== []) {
    echo json_encode(['failed' => $failed], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
}
echo "All Flow Connect unit/contract tests passed.\n";
