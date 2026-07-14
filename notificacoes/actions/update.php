<?php

require_once __DIR__ . '/../_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    notificacaoJsonResponse(false, 'Método não permitido.', 405);
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    notificacaoJsonResponse(false, 'ID inválido.', 422);
}

$titulo = trim((string)($_POST['titulo'] ?? ''));
$mensagem = notificacaoSanitizeHtml($_POST['mensagem'] ?? '');
$tipo = trim((string)($_POST['tipo'] ?? 'info'));
$canal = trim((string)($_POST['canal'] ?? 'banner'));
$segmentacao_tipo = trim((string)($_POST['segmentacao_tipo'] ?? 'geral'));
$prioridade = (int)($_POST['prioridade'] ?? 0);
$ativa = isset($_POST['ativa']) ? 1 : 0;
$fixa = isset($_POST['fixa']) ? 1 : 0;
$fechavel = isset($_POST['fechavel']) ? 1 : 0;
$exige_confirmacao = isset($_POST['exige_confirmacao']) ? 1 : 0;
$inicio_em = toMysqlDateTimeOrNull($_POST['inicio_em'] ?? '');
$fim_em = toMysqlDateTimeOrNull($_POST['fim_em'] ?? '');
$cta_label = trim((string)($_POST['cta_label'] ?? '')) ?: null;
$cta_url = trim((string)($_POST['cta_url'] ?? '')) ?: null;
$payload_json = trim((string)($_POST['payload_json'] ?? '')) ?: null;
$version_bump = isset($_POST['version_bump']);
$version_type = trim((string)($_POST['version_type'] ?? 'patch'));
$version_manual = trim((string)($_POST['version_manual'] ?? ''));
$version_desc = trim((string)($_POST['version_desc'] ?? ''));

if ($titulo === '' || $mensagem === '') {
    notificacaoJsonResponse(false, 'Título e mensagem são obrigatórios.', 422);
}

if (!in_array($tipo, ['info', 'warning', 'danger', 'success'], true)) $tipo = 'info';
if (!in_array($canal, ['banner', 'toast', 'modal', 'card'], true)) $canal = 'banner';
if (!in_array($segmentacao_tipo, ['geral', 'funcao', 'pessoa', 'projeto'], true)) $segmentacao_tipo = 'geral';

$alvoIds = $segmentacao_tipo === 'funcao' ? ($_POST['funcao_ids'] ?? []) : ($segmentacao_tipo === 'pessoa' ? ($_POST['usuario_ids'] ?? []) : ($segmentacao_tipo === 'projeto' ? ($_POST['obra_ids'] ?? []) : []));

try {
    if (notificacaoNormalizeUploads('arquivos') && !notificacaoAnexosTableExists($conn)) {
        throw new RuntimeException('A migration de anexos de notificações precisa ser aplicada antes de enviar arquivos.');
    }
    $attachments = notificacaoSaveUploadedFiles('arquivos');
    $conn->begin_transaction();
    $stmt = $conn->prepare('UPDATE notificacoes SET titulo = ?, mensagem = ?, tipo = ?, canal = ?, segmentacao_tipo = ?, prioridade = ?, ativa = ?, inicio_em = ?, fim_em = ?, fixa = ?, fechavel = ?, exige_confirmacao = ?, cta_label = ?, cta_url = ?, payload_json = ? WHERE id = ?');
    if (!$stmt) throw new RuntimeException('Erro ao preparar atualização.');
    $stmt->bind_param('sssssiissiiisssi', $titulo, $mensagem, $tipo, $canal, $segmentacao_tipo, $prioridade, $ativa, $inicio_em, $fim_em, $fixa, $fechavel, $exige_confirmacao, $cta_label, $cta_url, $payload_json, $id);
    if (!$stmt->execute()) throw new RuntimeException('Erro ao atualizar notificação.');
    $stmt->close();
    $stmt = null;
    notificacaoInsertAttachments($conn, $id, $attachments);
    replaceTargetsAndRecipients($conn, $id, $segmentacao_tipo, $alvoIds);
    $conn->commit();
} catch (Throwable $e) {
    if (isset($stmt) && $stmt) $stmt->close();
    $conn->rollback();
    notificacaoRemoveFiles($attachments ?? []);
    notificacaoJsonResponse(false, $e->getMessage() ?: 'Erro ao atualizar notificação.', 500);
}

$okMsg = 'Notificação atualizada!';
$errMsg = '';
if ($version_bump) {
    require_once realpath(__DIR__ . '/../../config/version_manager.php') ?: __DIR__ . '/../../config/version_manager.php';
    $root = realpath(__DIR__ . '/../../');
    $result = improov_bump_versions($root ?: (__DIR__ . '/../../'), $version_type, $version_type === 'manual' ? $version_manual : null);
    if (!$result['ok']) {
        $errMsg = $result['message'] ?? 'Falha ao atualizar a versão.';
    } else {
        $stmtV = $conn->prepare('INSERT INTO versionamentos (versao, descricao, tipo, criado_por) VALUES (?, ?, ?, ?)');
        if ($stmtV) {
            $version_final = (string)($result['app_version'] ?? '');
            $desc_final = $version_desc === '' ? null : $version_desc;
            $tipo_final = $version_type === 'manual' ? 'manual' : $version_type;
            $criado_por = (int)($_SESSION['idusuario'] ?? 0);
            $stmtV->bind_param('sssi', $version_final, $desc_final, $tipo_final, $criado_por);
            if (!$stmtV->execute()) $errMsg = 'Versão atualizada, mas falhou ao registrar no banco.';
            $stmtV->close();
        }
    }
}

notificacaoJsonResponse(true, $okMsg, 200, ['warning' => $errMsg ?: null, 'redirect' => 'index.php']);
