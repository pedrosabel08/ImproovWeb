<?php

require_once __DIR__ . '/config/database.php';

try {
    $conn = improov_db_connect();
} catch (Throwable $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Nao foi possivel conectar ao banco de dados.']));
}
