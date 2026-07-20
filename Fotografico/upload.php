<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/fotografico_service.php';
require_once __DIR__ . '/ws_notify.php';

function foto_upload_response(bool $success, array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($success ? ['success' => true, 'data' => $payload] : ['success' => false, 'error' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    foto_upload_response(false, ['code' => 'NAO_AUTENTICADO', 'message' => 'Sessao expirada.'], 401);
}
if (!fotografico_schema_ready($conn)) {
    foto_upload_response(false, ['code' => 'MIGRATION_PENDENTE', 'message' => 'A migration de refatoracao do Fotografico ainda nao foi aplicada.'], 503);
}
$csrf = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
if ($csrf === '' || empty($_SESSION['fotografico_csrf']) || !hash_equals((string) $_SESSION['fotografico_csrf'], $csrf)) {
    foto_upload_response(false, ['code' => 'CSRF_INVALIDO', 'message' => 'Atualize a pagina e tente novamente.'], 419);
}

$planoId = (int) ($_POST['plano_id'] ?? 0);
$kind = strtolower(trim((string) ($_POST['tipo'] ?? 'evidencia_execucao')));
$targetId = (int) ($_POST['entidade_id'] ?? 0);
if ($planoId <= 0 || $targetId <= 0 || !in_array($kind, ['mapa', 'evidencia_execucao'], true)) {
    foto_upload_response(false, ['code' => 'PARAMETROS_INVALIDOS', 'message' => 'Destino do anexo invalido.'], 422);
}

$expectedVersion = (int) ($_POST['version'] ?? 0);
if ($expectedVersion <= 0) {
    foto_upload_response(false, ['code' => 'VERSAO_OBRIGATORIA', 'message' => 'Atualize a pagina e tente novamente.'], 422);
}
$stmt = $conn->prepare('SELECT obra_id, responsavel_plano_id, responsavel_execucao_id, lock_version FROM fotografico_plano WHERE id = ?');
$stmt->bind_param('i', $planoId);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();
$stmt->close();
$actorId = fotografico_actor_id();
$isManager = improov_usuario_eh_gestor_sidebar($conn);
if (!$plan || !improov_usuario_pode_acessar_obra($conn, (int) $plan['obra_id'])) {
    foto_upload_response(false, ['code' => 'SEM_ACESSO', 'message' => 'Sem acesso a este plano.'], 403);
}
$canEdit = $isManager || ($actorId !== null && (int) ($plan['responsavel_plano_id'] ?? 0) === $actorId);
$canExecute = $isManager || ($actorId !== null && (int) ($plan['responsavel_execucao_id'] ?? 0) === $actorId);
if (($kind === 'mapa' && !$canEdit) || ($kind === 'evidencia_execucao' && !$canExecute && !$canEdit)) {
    foto_upload_response(false, ['code' => 'SEM_PERMISSAO', 'message' => 'Sem permissao para anexar neste plano.'], 403);
}

$entityType = $kind === 'mapa' ? 'VERSAO' : 'EXECUCAO';
if ($kind === 'mapa') {
    $stmt = $conn->prepare("SELECT id FROM fotografico_plano_versao WHERE id = ? AND plano_id = ? AND status = 'RASCUNHO'");
    $stmt->bind_param('ii', $targetId, $planoId);
} else {
    $stmt = $conn->prepare('SELECT id FROM fotografico_execucao WHERE id = ? AND plano_id = ?');
    $stmt->bind_param('ii', $targetId, $planoId);
}
$stmt->execute();
$belongs = (bool) $stmt->get_result()->fetch_row();
$stmt->close();
if (!$belongs) {
    foto_upload_response(false, ['code' => 'DESTINO_INVALIDO', 'message' => 'O destino nao pertence ao plano ou nao pode mais ser alterado.'], 422);
}

$file = $_FILES['arquivo'] ?? null;
if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    foto_upload_response(false, ['code' => 'ARQUIVO_INVALIDO', 'message' => 'Selecione um arquivo valido.'], 422);
}
$size = (int) ($file['size'] ?? 0);
if ($size <= 0 || $size > 10 * 1024 * 1024) {
    foto_upload_response(false, ['code' => 'TAMANHO_INVALIDO', 'message' => 'O arquivo deve ter no maximo 10 MB.'], 422);
}
$original = basename((string) ($file['name'] ?? 'arquivo'));
$extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
$tmp = (string) ($file['tmp_name'] ?? '');
if (!is_uploaded_file($tmp)) {
    foto_upload_response(false, ['code' => 'UPLOAD_INVALIDO', 'message' => 'Arquivo temporario invalido.'], 422);
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
$mapMimes = ['image/jpeg', 'image/png', 'image/webp'];
$evidenceMimes = [...$mapMimes, 'application/pdf'];
$allowedMimes = $kind === 'mapa' ? $mapMimes : $evidenceMimes;
$allowedExtensions = $kind === 'mapa' ? ['jpg', 'jpeg', 'png', 'webp'] : ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
if (!in_array($extension, $allowedExtensions, true) || !in_array($mime, $allowedMimes, true)) {
    foto_upload_response(false, ['code' => 'TIPO_INVALIDO', 'message' => $kind === 'mapa' ? 'O mapa deve ser JPG, JPEG, PNG ou WEBP.' : 'Use JPG, JPEG, PNG, WEBP ou PDF.'], 422);
}

$directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'fotografico';
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    foto_upload_response(false, ['code' => 'PASTA_INDISPONIVEL', 'message' => 'Nao foi possivel preparar a pasta de anexos.'], 500);
}
$stored = 'obra_' . (int) $plan['obra_id'] . '_plano_' . $planoId . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
$target = $directory . DIRECTORY_SEPARATOR . $stored;
if (!move_uploaded_file($tmp, $target)) {
    foto_upload_response(false, ['code' => 'FALHA_AO_SALVAR', 'message' => 'Nao foi possivel salvar o anexo.'], 500);
}

try {
    $conn->begin_transaction();
    $stmt = $conn->prepare('SELECT lock_version FROM fotografico_plano WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $planoId);
    $stmt->execute();
    $currentVersion = (int) ($stmt->get_result()->fetch_assoc()['lock_version'] ?? 0);
    $stmt->close();
    if ($currentVersion !== $expectedVersion) {
        $conn->rollback();
        @unlink($target);
        foto_upload_response(false, ['code' => 'VERSAO_DESATUALIZADA', 'message' => 'O plano foi alterado por outro usuario. Recarregue os dados.'], 409);
    }
    if ($kind === 'mapa') {
        $stmt = $conn->prepare("UPDATE fotografico_anexo SET arquivado_em = NOW() WHERE entidade_tipo = 'VERSAO' AND entidade_id = ? AND categoria = 'MAPA' AND arquivado_em IS NULL");
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $stmt->close();
    }
    $path = 'uploads/fotografico/' . $stored;
    $hash = sha1_file($target) ?: null;
    $category = $kind === 'mapa' ? 'MAPA' : 'EVIDENCIA';
    $stmt = $conn->prepare("INSERT INTO fotografico_anexo (plano_id, entidade_tipo, entidade_id, tipo, categoria, nome_original, caminho, mime, tamanho_bytes, hash_sha1, criado_por) VALUES (?, ?, ?, 'UPLOAD', ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isissssisi', $planoId, $entityType, $targetId, $category, $original, $path, $mime, $size, $hash, $actorId);
    $stmt->execute();
    $attachmentId = (int) $conn->insert_id;
    $stmt->close();
    if ($kind === 'mapa') {
        $stmt = $conn->prepare('UPDATE fotografico_plano_versao SET mapa_anexo_id = ? WHERE id = ?');
        $stmt->bind_param('ii', $attachmentId, $targetId);
        $stmt->execute();
        $stmt->close();
        fotografico_evento($conn, $planoId, 'MAPA_ENVIADO', null, null, $actorId, 'Fotografico/upload.php', ['versao_id' => $targetId, 'anexo_id' => $attachmentId]);
    } else {
        fotografico_evento($conn, $planoId, 'ANEXO_EXECUCAO_ADICIONADO', null, null, $actorId, 'Fotografico/upload.php', ['execucao_id' => $targetId, 'anexo_id' => $attachmentId]);
    }
    $stmt = $conn->prepare('UPDATE fotografico_plano SET lock_version = lock_version + 1 WHERE id = ?');
    $stmt->bind_param('i', $planoId);
    $stmt->execute();
    $stmt->close();
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    @unlink($target);
    foto_upload_response(false, ['code' => 'FALHA_AO_REGISTRAR', 'message' => $e->getMessage()], 500);
}

fotografico_notify_update($kind === 'mapa' ? 'map.updated' : 'execution.evidence.added', [
    'plan_id' => $planoId,
    'entity_id' => $targetId,
    'attachment_id' => $attachmentId,
    'client_event_id' => (string) ($_POST['client_event_id'] ?? ''),
]);
foto_upload_response(true, ['id' => $attachmentId, 'caminho' => $path, 'nome_original' => $original, 'mime' => $mime]);
