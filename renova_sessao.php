<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Tue, 01 Jan 2000 00:00:00 GMT');
header('Vary: Cookie');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/config/session_bootstrap.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Sessão inexistente ou expirada.']);
    exit;
}

// Use an explicit timezone to avoid server timezone mismatches.
$tz = new DateTimeZone('America/Sao_Paulo');
$dt = new DateTime('now', $tz);
$_SESSION['ultimo_renovado'] = $dt->format('Y-m-d H:i:s P');
echo json_encode([
    'ok' => true,
    'message' => 'Sessão renovada',
    'renovado_em' => $_SESSION['ultimo_renovado'],
]);
