<?php
require_once __DIR__ . '/config/version.php';

header('Content-Type: application/json; charset=utf-8');

// Garantir que essa resposta nunca fique em cache (proxy/CDN/navegador)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Vary: Cookie');

echo json_encode([
    'ok' => true,
    'version' => APP_VERSION,
    'ts' => time(),
]);