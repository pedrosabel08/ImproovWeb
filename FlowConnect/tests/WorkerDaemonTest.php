<?php

declare(strict_types=1);

use FlowConnect\Application\WorkerLoop;

require_once dirname(__DIR__) . '/workers/_cli.php';

function flow_connect_test_worker_daemon(): void
{
    $daemonOptions = flow_connect_cli_options(['worker.php', '--daemon', '--limit=55']);
    fc_assert_same(true, $daemonOptions['daemon'], 'daemon flag is parsed');
    fc_assert_same(55, $daemonOptions['limit'], 'daemon keeps configured batch limit');
    fc_assert_same(237, flow_connect_cli_options(['worker.php', '--once', '--event-id=237'])['event_id'], 'explicit event id is parsed for isolated shadow validation');
    fc_assert_same('shadow-render:356946:v1', flow_connect_cli_options(['worker.php', '--once', '--cycle-id=shadow-render:356946:v1'])['cycle_id'], 'explicit cycle id is parsed for isolated scheduler validation');
    fc_assert_same(21, flow_connect_cli_options(['worker.php', '--once', '--collaborator-id=21'])['collaborator_id'], 'collaborator filter is parsed for isolated pending-summary validation');
    try {
        flow_connect_cli_options(['worker.php', '--once', '--daemon']);
        fc_assert(false, 'once and daemon cannot be used together');
    } catch (InvalidArgumentException $e) {
        fc_assert(true, 'once and daemon conflict is rejected');
    }

    $loop = new WorkerLoop();
    $cycles = 0;
    $sleeps = 0;
    $running = true;
    $loop->run(true, function () use (&$cycles, &$running): int {
        $cycles++;
        if ($cycles === 3) $running = false;
        return $cycles === 1 ? 2 : 0;
    }, static function () use (&$running): bool {
        return $running;
    }, function () use (&$sleeps): void {
        $sleeps++;
    });
    fc_assert_same(3, $cycles, 'daemon repeats cycles until graceful stop is requested');
    fc_assert_same(1, $sleeps, 'daemon sleeps only after an empty claim while still running');

    $onceCycles = 0;
    $onceSleeps = 0;
    $loop->run(false, function () use (&$onceCycles): int {
        $onceCycles++;
        return 0;
    }, static fn(): bool => true, function () use (&$onceSleeps): void {
        $onceSleeps++;
    });
    fc_assert_same(1, $onceCycles, 'once mode keeps one processing cycle');
    fc_assert_same(0, $onceSleeps, 'once mode never sleeps');
}
