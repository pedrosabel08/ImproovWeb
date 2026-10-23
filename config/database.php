<?php

require_once __DIR__ . '/secure_env.php';

function improov_db_config(): array
{
    $port = filter_var(improov_env('DB_PORT', '3306'), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    if ($port === false) {
        throw new RuntimeException('Variavel de ambiente invalida: DB_PORT');
    }

    return [
        'host' => improov_env('DB_HOST'),
        'port' => $port,
        'user' => improov_env('DB_USERNAME'),
        'password' => improov_env('DB_PASSWORD'),
        'database' => improov_env('DB_DATABASE'),
    ];
}

function improov_db_connect(): mysqli
{
    $config = improov_db_config();
    $conn = new mysqli(
        $config['host'],
        $config['user'],
        $config['password'],
        $config['database'],
        $config['port']
    );
    if ($conn->connect_error) {
        throw new RuntimeException('Nao foi possivel conectar ao banco de dados.');
    }
    $conn->set_charset('utf8mb4');

    return $conn;
}

function improov_db_pdo(): PDO
{
    $config = improov_db_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['database']
    );

    return new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}
