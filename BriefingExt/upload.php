<?php

declare(strict_types=1);
require_once __DIR__ . '/../Briefing/lib.php';

$conn = briefing_conn();
try {
    $access = briefing_external_access($conn, (string) ($_POST['token'] ?? ''), true);
    $briefingId = (int) $access['link']['briefing_id'];
    $participantId = (int) $access['participant']['id'];
    $questionId = (int) ($_POST['question_id'] ?? 0);
    if (!briefing_external_question_editable($conn, $briefingId, (string) $access['link']['status'], $questionId) || !ext_question_upload($conn, $briefingId, $questionId)) {
        throw new InvalidArgumentException('Pergunta indisponível para anexo.');
    }
    $file = $_FILES['file'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('Arquivo inválido.');
    if ((int) $file['size'] > 10 * 1024 * 1024) throw new InvalidArgumentException('O anexo deve ter no máximo 10 MiB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    if (!isset($allowed[$mime])) throw new InvalidArgumentException('Envie somente JPG, PNG, WebP ou PDF.');
    $dir = __DIR__ . '/storage/' . gmdate('Y/m');
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('Não foi possível preparar o armazenamento.');
    $name = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
    $path = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $path)) throw new RuntimeException('Não foi possível armazenar o anexo.');
    $relative = gmdate('Y/m') . '/' . $name;
    $original = briefing_clean_text($file['name'], 255);
    $checksum = hash_file('sha256', $path);
    $stmt = briefing_stmt($conn, 'INSERT INTO briefing_attachment (briefing_id,briefing_question_id,caminho,nome_original,mime_type,tamanho_bytes,checksum_sha256,autor_participant_id) VALUES (?,?,?,?,?,?,?,?)', 'iisssisi', [$briefingId, $questionId, $relative, $original, $mime, (int) $file['size'], $checksum, $participantId]);
    $id = (int) $conn->insert_id; $stmt->close();
    briefing_event($conn, $briefingId, 'attachment.uploaded', 'PARTICIPANTE', $participantId, ['attachment_id' => $id, 'question_id' => $questionId]);
    briefing_json(['ok' => true, 'attachment' => ['id' => $id, 'name' => $original, 'url' => 'download.php?id=' . $id . '&t=' . rawurlencode((string) $_POST['token'])]]);
} catch (InvalidArgumentException $e) {
    briefing_json(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('[Briefing upload] ' . $e->getMessage()); briefing_json(['ok' => false, 'message' => 'Não foi possível enviar o anexo.'], 500);
}
function ext_question_upload(mysqli $conn, int $briefingId, int $questionId): bool
{
    return (bool) briefing_scalar($conn, 'SELECT q.id FROM briefing_question q JOIN briefing_section s ON s.id=q.briefing_section_id WHERE q.id=? AND s.briefing_id=? AND q.tipo=?', 'iis', [$questionId, $briefingId, 'REFERENCE']);
}
