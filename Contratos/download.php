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

$arquivo = isset($_GET['arquivo']) ? basename((string)$_GET['arquivo']) : '';
if ($arquivo === '' || strpos($arquivo, '..') !== false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Arquivo inválido.']);
    exit;
}

$dir = __DIR__ . '/gerados';
$path = $dir . DIRECTORY_SEPARATOR . $arquivo;
if (!file_exists($path)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Arquivo não encontrado.']);
    exit;
}

header_remove('Content-Type');
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $arquivo . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
