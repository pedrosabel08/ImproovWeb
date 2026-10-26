<?php
// Arquivo temporário para diagnóstico - REMOVER APÓS USO
require_once dirname(__DIR__) . '/config/secure_env.php';
improov_load_env_once();

$secret = $_GET["s"] ?? '';
$expectedSecret = getenv('SLACK_LOG_ACCESS_SECRET');
if (!is_string($secret) || !is_string($expectedSecret) || $expectedSecret === '' || !hash_equals($expectedSecret, $secret)) {
    http_response_code(403);
    exit;
}
$candidates = [
    __DIR__ . '/../logs/slack_debug.log',
    __DIR__ . '/slack_debug.log',
];
foreach ($candidates as $f) {
    if (file_exists($f)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "=== $f ===\n";
        echo file_get_contents($f) ?: '(vazio)';
        exit;
    }
}
echo "Log não encontrado. Testados:\n" . implode("\n", $candidates);
