<?php

// Exercise configuration only: never opens a connection or reads production data.
require_once dirname(__DIR__) . '/config/database.php';

$fixture = [
    'DB_HOST' => 'database.example.invalid',
    'DB_PORT' => '3307',
    'DB_USERNAME' => 'fixture_user',
    'DB_PASSWORD' => 'fixture$#=password',
    'DB_DATABASE' => 'fixture_database',
];
foreach ($fixture as $key => $value) {
    putenv($key . '=' . $value);
}

function check_config(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$config = improov_db_config();
check_config($config['host'] === $fixture['DB_HOST'], 'Host must come from DB_HOST');
check_config($config['user'] === $fixture['DB_USERNAME'], 'User must come from DB_USERNAME');
check_config($config['password'] === $fixture['DB_PASSWORD'], 'Password characters must be preserved');
check_config($config['database'] === $fixture['DB_DATABASE'], 'Database must come from DB_DATABASE');
check_config($config['port'] === 3307, 'Configured port must be used');

putenv('DB_PORT=');
check_config(improov_db_config()['port'] === 3306, 'Empty port uses default');
foreach (['0', '65536', 'invalid'] as $port) {
    putenv('DB_PORT=' . $port);
    try {
        improov_db_config();
        throw new LogicException('Invalid port must be rejected');
    } catch (RuntimeException $e) {
        check_config(strpos($e->getMessage(), 'DB_PORT') !== false, 'Error must identify only the variable');
    }
}
putenv('DB_PORT=3306');

foreach (['DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE'] as $key) {
    putenv($key . '=');
    try {
        improov_db_config();
        throw new LogicException('Missing credentials must not use a fallback');
    } catch (RuntimeException $e) {
        check_config(strpos($e->getMessage(), $key) !== false, 'Missing variable must be identified');
        check_config(strpos($e->getMessage(), $fixture['DB_PASSWORD']) === false, 'Errors must not expose the password');
    }
    putenv($key . '=' . $fixture[$key]);
}

echo "Database configuration checks passed.\n";
