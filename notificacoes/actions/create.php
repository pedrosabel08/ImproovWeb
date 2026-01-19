<?php

require_once __DIR__ . '/../_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

$titulo = trim((string)($_POST['titulo'] ?? ''));
$mensagem = trim((string)($_POST['mensagem'] ?? ''));
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

list($arquivo_path, $arquivo_nome) = saveUploadedPdf('arquivo_pdf');

if ($titulo === '' || $mensagem === '') {
    header('Location: ../index.php?err=' . urlencode('Título e mensagem são obrigatórios.'));
    exit();
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

$alvoIds = [];
if ($segmentacao_tipo === 'funcao') {
    $alvoIds = $_POST['funcao_ids'] ?? [];
} elseif ($segmentacao_tipo === 'pessoa') {
    $alvoIds = $_POST['usuario_ids'] ?? [];
} elseif ($segmentacao_tipo === 'projeto') {
    $alvoIds = $_POST['obra_ids'] ?? [];
}

$sql = "INSERT INTO notificacoes (titulo, mensagem, tipo, canal, segmentacao_tipo, prioridade, ativa, inicio_em, fim_em, fixa, fechavel, exige_confirmacao, cta_label, cta_url, arquivo_nome, arquivo_path, payload_json, criado_por)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    header('Location: ../index.php?err=' . urlencode('Erro ao preparar INSERT. Verifique se o SQL foi executado.'));
    exit();
}

$criado_por = (int)($_SESSION['idusuario'] ?? 0);
$stmt->bind_param(
    'sssssiissiiisssssi',
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
    $arquivo_nome,
    $arquivo_path,
    $payload_json,
    $criado_por
);

if (!$stmt->execute()) {
    $stmt->close();
    header('Location: ../index.php?err=' . urlencode('Erro ao salvar notificação.'));
    exit();
}

$notificacaoId = (int)$conn->insert_id;
$stmt->close();

replaceTargetsAndRecipients($conn, $notificacaoId, $segmentacao_tipo, $alvoIds);

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

$redirect = '../index.php?ok=' . urlencode($okMsg);
if ($errMsg !== '') {
    $redirect .= '&err=' . urlencode($errMsg);
}

header('Location: ' . $redirect);
exit();
