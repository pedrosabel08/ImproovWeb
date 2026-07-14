<?php

require_once __DIR__ . '/../_common.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    notificacaoJsonResponse(false, 'Método não permitido.', 405);
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

$cta_label = trim((string)($_POST['cta_label'] ?? ''));
$cta_url = trim((string)($_POST['cta_url'] ?? ''));
$payload_json = trim((string)($_POST['payload_json'] ?? ''));

$version_bump = isset($_POST['version_bump']);
$version_type = trim((string)($_POST['version_type'] ?? 'patch'));
$version_manual = trim((string)($_POST['version_manual'] ?? ''));
$version_desc = trim((string)($_POST['version_desc'] ?? ''));

if ($titulo === '' || $mensagem === '') {
    notificacaoJsonResponse(false, [
        'erro' => 'Título e mensagem são obrigatórios.',
        'titulo_recebido' => $titulo,
        'mensagem_original' => $_POST['mensagem'] ?? null,
        'mensagem_sanitizada' => $mensagem,
    ], 422);
}

$allowedTipos = ['info', 'warning', 'danger', 'success'];
if (!in_array($tipo, $allowedTipos, true)) {
    $tipo = 'info';
}

$allowedCanais = ['banner', 'toast', 'modal', 'card'];
if (!in_array($canal, $allowedCanais, true)) {
    $canal = 'banner';
}

$allowedSeg = ['geral', 'funcao', 'pessoa', 'projeto'];
if (!in_array($segmentacao_tipo, $allowedSeg, true)) {
    $segmentacao_tipo = 'geral';
}

$cta_label = $cta_label === '' ? null : $cta_label;
$cta_url = $cta_url === '' ? null : $cta_url;
$payload_json = $payload_json === '' ? null : $payload_json;

try {
    if (notificacaoNormalizeUploads('arquivos') && !notificacaoAnexosTableExists($conn)) {
        throw new RuntimeException('A migration de anexos de notificações precisa ser aplicada antes de enviar arquivos.');
    }
    $attachments = notificacaoSaveUploadedFiles('arquivos');
} catch (Throwable $e) {
    notificacaoJsonResponse(false, $e->getMessage(), 422);
}

$alvoIds = [];
if ($segmentacao_tipo === 'funcao') {
    $alvoIds = $_POST['funcao_ids'] ?? [];
} elseif ($segmentacao_tipo === 'pessoa') {
    $alvoIds = $_POST['usuario_ids'] ?? [];
} elseif ($segmentacao_tipo === 'projeto') {
    $alvoIds = $_POST['obra_ids'] ?? [];
}

$sql = "INSERT INTO notificacoes (titulo, mensagem, tipo, canal, segmentacao_tipo, prioridade, ativa, inicio_em, fim_em, fixa, fechavel, exige_confirmacao, cta_label, cta_url, payload_json, criado_por)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    notificacaoRemoveFiles($attachments);
    notificacaoJsonResponse(false, 'Erro ao preparar INSERT. Verifique se o SQL foi executado.', 500);
}

$criado_por = (int)($_SESSION['idusuario'] ?? 0);
$stmt->bind_param(
    'sssssiissiiisssi',
    $titulo,
    $mensagem,
    $tipo,
    $canal,
    $segmentacao_tipo,
    $prioridade,
    $ativa,
    $inicio_em,
    $fim_em,
    $fixa,
    $fechavel,
    $exige_confirmacao,
    $cta_label,
    $cta_url,
    $payload_json,
    $criado_por
);

$conn->begin_transaction();
try {
    if (!$stmt->execute()) {
        throw new RuntimeException('Erro ao salvar a notificação.');
    }

    $notificacaoId = (int)$conn->insert_id;
    $stmt->close();
    $stmt = null;

    notificacaoInsertAttachments($conn, $notificacaoId, $attachments);
    replaceTargetsAndRecipients($conn, $notificacaoId, $segmentacao_tipo, $alvoIds);
    $conn->commit();
} catch (Throwable $e) {
    if ($stmt) $stmt->close();
    $conn->rollback();
    notificacaoRemoveFiles($attachments);
    notificacaoJsonResponse(false, $e->getMessage() ?: 'Erro ao salvar a notificação.', 500);
}

$okMsg = 'Notificação criada!';
$errMsg = '';

if ($version_bump) {
    require_once realpath(__DIR__ . '/../../config/version_manager.php') ?: __DIR__ . '/../../config/version_manager.php';
    $root = realpath(__DIR__ . '/../../');
    $explicit = ($version_type === 'manual') ? $version_manual : null;
    $result = improov_bump_versions($root ?: (__DIR__ . '/../../'), $version_type, $explicit);

    if (!$result['ok']) {
        $errMsg = $result['message'] ?? 'Falha ao atualizar a versão.';
    } else {
        $version_final = (string)($result['app_version'] ?? '');
        $desc_final = $version_desc === '' ? null : $version_desc;
        $tipo_final = $version_type === 'manual' ? 'manual' : $version_type;
        $criado_por = (int)($_SESSION['idusuario'] ?? 0);

        $stmtV = $conn->prepare('INSERT INTO versionamentos (versao, descricao, tipo, criado_por) VALUES (?, ?, ?, ?)');
        if ($stmtV) {
            $stmtV->bind_param('sssi', $version_final, $desc_final, $tipo_final, $criado_por);
            if (!$stmtV->execute()) {
                $errMsg = 'Versão atualizada, mas falhou ao registrar no banco.';
            }
            $stmtV->close();
        } else {
            $errMsg = 'Versão atualizada, mas falhou ao preparar registro no banco.';
        }
    }
}

notificacaoJsonResponse(true, $okMsg, 200, ['warning' => $errMsg ?: null, 'redirect' => 'index.php']);
