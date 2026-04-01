<?php
/**
 * Serves the pending temp adendo PDF inline (for preview modal).
 * Only works when an adendo_pendente entry exists in the session.
 */
require_once __DIR__ . '/../config/session_bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    exit;
}

$pendente = $_SESSION['adendo_pendente'] ?? null;
if (!$pendente || empty($pendente['temp_rel'])) {
    http_response_code(404);
    exit;
}

$baseGerados = realpath(__DIR__ . '/../Contratos/gerados');
if (!$baseGerados) {
    http_response_code(500);
    exit;
}

$filePath = realpath($baseGerados . '/' . $pendente['temp_rel']);

// Security: path must stay inside the gerados directory
if (!$filePath || strpos($filePath, $baseGerados) !== 0 || !is_file($filePath)) {
    http_response_code(404);
    exit;
}

$filename = basename($filePath);
$filesize = filesize($filePath);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . $filesize);
header('Cache-Control: no-store');

readfile($filePath);
