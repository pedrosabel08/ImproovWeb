<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/test_mode.php';

function flow_connect_test_test_mode_config(): void
{
    $keys = [
        'APP_ENV',
        'FLOW_CONNECT_TEST_MODE',
        'FLOW_CONNECT_TEST_COLLABORATOR_ID',
        'FLOW_CONNECT_TEST_ALLOWED_PROJECT',
    ];
    $previous = [];
    foreach ($keys as $key) {
        $previous[$key] = getenv($key);
    }

    try {
        putenv('APP_ENV=local');
        putenv('FLOW_CONNECT_TEST_MODE=true');
        putenv('FLOW_CONNECT_TEST_COLLABORATOR_ID=77');
        putenv('FLOW_CONNECT_TEST_ALLOWED_PROJECT=TES_TES');

        $local = flow_connect_test_config();
        fc_assert_same(true, $local['enabled'], 'Test mode must be enabled in local when explicitly requested');
        fc_assert_same(77, $local['collaborator_id'], 'Test collaborator must come from the environment');
        fc_assert_same('TES_TES', $local['allowed_project'], 'Test project must come from the environment');

        putenv('APP_ENV=production');
        $production = flow_connect_test_config();
        fc_assert_same(false, $production['enabled'], 'Test mode must be ignored in production');

        putenv('APP_ENV=test');
        $test = flow_connect_test_config();
        fc_assert_same(true, $test['enabled'], 'Test mode must be allowed in the test environment');
    } finally {
        foreach ($previous as $key => $value) {
            putenv($value === false ? $key : $key . '=' . $value);
        }
    }
}
