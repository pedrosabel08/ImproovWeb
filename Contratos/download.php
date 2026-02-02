<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

// Permitir caminhos relativos dentro de gerados/, mas proteger contra path traversal
$rawArquivo = isset($_GET['arquivo']) ? (string)$_GET['arquivo'] : '';
$rawArquivo = trim($rawArquivo, "\x00 \t\n\r\0\x0B/");
if ($rawArquivo === '' || strpos($rawArquivo, '..') !== false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Arquivo inválido.']);
    exit;
}

$baseDir = realpath(__DIR__ . '/gerados');
if ($baseDir === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Diretório de gerados não existe.']);
    exit;
}

$requested = $baseDir . DIRECTORY_SEPARATOR . str_replace(['\\','/'], DIRECTORY_SEPARATOR, $rawArquivo);
$realRequested = realpath($requested);
if ($realRequested === false || strpos($realRequested, $baseDir) !== 0 || !is_file($realRequested)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Arquivo não encontrado.']);
    exit;
}

$path = $realRequested;

$arquivo = basename($path);
$arquivoEncoded = rawurlencode($arquivo);

$raw = isset($_GET['raw']) ? (int)$_GET['raw'] : 0;
if ($raw !== 1) {
    $safeTitle = htmlspecialchars(pathinfo($arquivo, PATHINFO_FILENAME), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $self = basename($_SERVER['PHP_SELF']);
    $iframeSrc = $self . '?arquivo=' . rawurlencode($rawArquivo) . '&raw=1';
    header_remove('Content-Type');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-br"><head>';
    echo '<meta charset="utf-8" />';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1" />';
    echo '<title>' . $safeTitle . '</title>';
    echo '<style>html,body{height:100%;margin:0}iframe{border:0;width:100%;height:100%}</style>';
    echo '</head><body>';
    echo '<iframe src="' . $iframeSrc . '" title="' . $safeTitle . '"></iframe>';
    echo '</body></html>';
    exit;
}

header_remove('Content-Type');
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $arquivo . '"; filename*=UTF-8\'\'' . $arquivoEncoded);
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
