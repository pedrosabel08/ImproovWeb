<?php
// Arquivo temporário para diagnóstico - REMOVER APÓS USO
$secret = $_GET['s'] ?? '';
if ($secret !== 'diag2026') { http_response_code(403); exit; }
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
