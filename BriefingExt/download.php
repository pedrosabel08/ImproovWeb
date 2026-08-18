<?php

declare(strict_types=1);
require_once __DIR__ . '/../Briefing/lib.php';

try {
    $conn = briefing_conn();
    $access = briefing_external_access($conn, (string) ($_GET['t'] ?? ''), false);
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = briefing_stmt($conn, 'SELECT * FROM briefing_attachment WHERE id=? AND briefing_id=?', 'ii', [$id, (int) $access['link']['briefing_id']]);
    $file = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$file) {
        throw new RuntimeException('Arquivo indisponível.');
    }
    $path = __DIR__ . '/storage/' . $file['caminho'];
    if (!is_file($path)) {
        throw new RuntimeException('Arquivo indisponível.');
    }
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="' . rawurlencode($file['nome_original']) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
} catch (Throwable) {
    http_response_code(404);
    echo 'Arquivo indisponível.';
}
