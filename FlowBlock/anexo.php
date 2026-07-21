<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/flow_block_helper.php';

flow_block_ensure_authenticated();

$attachmentId = (int) ($_GET['id'] ?? 0);
if ($attachmentId <= 0) {
    http_response_code(404);
    exit;
}

$stmt = $conn->prepare(
    'SELECT a.nome_original, a.caminho, a.mime_type
     FROM flow_issue_anexo a
     WHERE a.id = ? LIMIT 1'
);
$stmt->bind_param('i', $attachmentId);
$stmt->execute();
$attachment = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

// Anexos de Issues são visíveis para qualquer usuário autenticado do Flow.
// As permissões de criação, edição e exclusão continuam sendo validadas na API.
if (!$attachment) {
    http_response_code(404);
    exit;
}

$uploadsRoot = realpath(__DIR__ . '/../uploads/flow_block');
$filePath = realpath(__DIR__ . '/../' . ltrim((string) $attachment['caminho'], '/\\'));
if (!$uploadsRoot || !$filePath || !is_file($filePath) || !str_starts_with($filePath, $uploadsRoot . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit;
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($filePath) ?: ($attachment['mime_type'] ?: 'application/octet-stream');
$filename = str_replace(["\r", "\n", '"'], '', (string) $attachment['nome_original']);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode($filename));
header('X-Content-Type-Options: nosniff');
readfile($filePath);
