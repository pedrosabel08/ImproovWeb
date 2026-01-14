<?php

require_once __DIR__ . '/../_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: ../index.php?err=' . urlencode('ID inválido.'));
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

$sql = "UPDATE notificacoes
        SET titulo = ?, mensagem = ?, tipo = ?, canal = ?, segmentacao_tipo = ?, prioridade = ?, ativa = ?, inicio_em = ?, fim_em = ?, fixa = ?, fechavel = ?, exige_confirmacao = ?, cta_label = ?, cta_url = ?, payload_json = ?
        WHERE id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    header('Location: ../index.php?err=' . urlencode('Erro ao preparar UPDATE.'));
    exit();
}

$stmt->bind_param(
    'sssssisssiiisssi',
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
    $id
);

if (!$stmt->execute()) {
    $stmt->close();
    header('Location: ../index.php?err=' . urlencode('Erro ao atualizar notificação.'));
    exit();
}

$stmt->close();

replaceTargetsAndRecipients($conn, $id, $segmentacao_tipo, $alvoIds);

header('Location: ../index.php?ok=' . urlencode('Notificação atualizada!'));
exit();
