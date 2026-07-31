<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

$GLOBALS['flow_connect_test_count'] = 0;

function fc_assert(bool $condition, string $message): void
{
    $GLOBALS['flow_connect_test_count']++;
    if (!$condition) throw new RuntimeException($message);
}

function fc_assert_same($expected, $actual, string $message): void
{
    fc_assert($expected === $actual, $message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
}
