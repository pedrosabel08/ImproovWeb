<?php
require_once dirname(__DIR__) . '/config/session_bootstrap.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/heatmap_helpers.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado'], JSON_UNESCAPED_UNICODE);
    exit();
}

$response = heatmap_fetch_response($conn, $_GET);
$conn->close();

echo json_encode($response, JSON_UNESCAPED_UNICODE);
