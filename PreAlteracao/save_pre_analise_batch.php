<?php
// PreAlteracao/save_pre_analise_batch.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/pre_alt_helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nao autenticado.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data) || !isset($data['itens']) || !is_array($data['itens'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Payload invalido.']);
    exit;
}

pre_alt_ensure_schema($conn);

$responsavelId = isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null;
$resultadosValidos = ['ALTERACAO', 'SEM_ALTERACAO', 'AGUARDANDO_CLIENTE'];
$batchId = pre_alt_batch_id();
$loteIds = [];
$updatedItems = 0;

$stmtFetch = $conn->prepare(
    'SELECT
        id,
        pre_alt_lote_id,
        resultado,
        nivel_complexidade,
        tipo_alteracao,
        acao,
        necessita_retorno,
        quantidade_comentarios,
        reanalise_pos_retorno,
        responsavel_id,
        (SELECT pli.id FROM pre_alt_liberacao_itens pli WHERE pli.pre_alt_item_id = pre_alt_itens.id LIMIT 1) AS liberacao_item_id
     FROM pre_alt_itens
     WHERE id = ?
     LIMIT 1'
);
$stmtUpdate = $conn->prepare(
    "UPDATE pre_alt_itens
     SET resultado = ?,
         nivel_complexidade = ?,
         tipo_alteracao = NULLIF(?, ''),
         acao = NULLIF(?, ''),
         necessita_retorno = ?,
         quantidade_comentarios = ?,
         reanalise_pos_retorno = ?,
         responsavel_id = ?,
         updated_at = NOW()
     WHERE id = ?"
);

if (!$stmtFetch || !$stmtUpdate) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}

foreach ($data['itens'] as $item) {
    if (!is_array($item)) {
        continue;
    }

    $itemId = isset($item['item_id']) && is_numeric($item['item_id']) ? (int) $item['item_id'] : 0;
    $resultado = strtoupper(trim((string) ($item['resultado'] ?? '')));
    $nivel = isset($item['nivel_complexidade']) && is_numeric($item['nivel_complexidade'])
        ? (int) $item['nivel_complexidade']
        : null;
    $tipoAlteracao = trim((string) ($item['tipo_alteracao'] ?? ''));
    $acao = trim((string) ($item['acao'] ?? ''));
    $necessitaRetorno = !empty($item['necessita_retorno']) ? 1 : 0;
    $quantidadeComentarios = isset($item['quantidade_comentarios']) && is_numeric($item['quantidade_comentarios'])
        ? max(0, (int) $item['quantidade_comentarios'])
        : 0;

    if ($itemId <= 0 || !in_array($resultado, $resultadosValidos, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Item com parametros invalidos.']);
        exit;
    }

    if ($resultado === 'ALTERACAO' && ($nivel === null || $nivel < 1 || $nivel > 5)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Selecione o nivel de complexidade das imagens com alteracao.']);
        exit;
    }

    if ($resultado !== 'ALTERACAO') {
        $nivel = null;
        $tipoAlteracao = '';
    }
    if ($resultado === 'AGUARDANDO_CLIENTE') {
        $necessitaRetorno = 1;
    }

    $stmtFetch->bind_param('i', $itemId);
    $stmtFetch->execute();
    $current = $stmtFetch->get_result()->fetch_assoc();
    if (!$current) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Item nao encontrado.']);
        exit;
    }
    if (!empty($current['liberacao_item_id'])) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Imagens ja liberadas nao podem ser alteradas.']);
        exit;
    }

    $loteId = (int) $current['pre_alt_lote_id'];
    $loteIds[$loteId] = true;
    $reanalisePosRetorno = (int) ($current['reanalise_pos_retorno'] ?? 0);
    if ($reanalisePosRetorno === 1 && ($resultado === 'SEM_ALTERACAO' || ($resultado === 'ALTERACAO' && $nivel !== null))) {
        $reanalisePosRetorno = 0;
    }

    $changes = [
        'resultado' => [$current['resultado'] ?? null, $resultado],
        'nivel_complexidade' => [$current['nivel_complexidade'] ?? null, $nivel],
        'tipo_alteracao' => [$current['tipo_alteracao'] ?? null, $tipoAlteracao !== '' ? $tipoAlteracao : null],
        'acao' => [$current['acao'] ?? null, $acao !== '' ? $acao : null],
        'necessita_retorno' => [(int) ($current['necessita_retorno'] ?? 0), $necessitaRetorno],
        'quantidade_comentarios' => [$current['quantidade_comentarios'] ?? null, $quantidadeComentarios],
        'reanalise_pos_retorno' => [(int) ($current['reanalise_pos_retorno'] ?? 0), $reanalisePosRetorno],
        'responsavel_id' => [$current['responsavel_id'] ?? null, $responsavelId],
    ];

    $hasChanges = false;
    foreach ($changes as [$oldValue, $newValue]) {
        if ((string) ($oldValue ?? '') !== (string) ($newValue ?? '')) {
            $hasChanges = true;
            break;
        }
    }
    if (!$hasChanges) {
        continue;
    }

    $stmtUpdate->bind_param(
        'sissiiiii',
        $resultado,
        $nivel,
        $tipoAlteracao,
        $acao,
        $necessitaRetorno,
        $quantidadeComentarios,
        $reanalisePosRetorno,
        $responsavelId,
        $itemId
    );
    if (!$stmtUpdate->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $stmtUpdate->error]);
        exit;
    }

    foreach ($changes as $campo => [$oldValue, $newValue]) {
        if ((string) ($oldValue ?? '') === (string) ($newValue ?? '')) {
            continue;
        }
        pre_alt_registrar_historico(
            $conn,
            $loteId,
            'ALTERACAO_ITEM',
            $campo,
            $oldValue,
            $newValue,
            'Triagem da imagem atualizada em lote.',
            $itemId,
            $batchId
        );
    }

    $updatedItems += 1;
}

$stmtFetch->close();
$stmtUpdate->close();

$statuses = [];
foreach (array_keys($loteIds) as $loteId) {
    $statuses[$loteId] = pre_alt_recalcular_status_lote(
        $conn,
        (int) $loteId,
        $batchId,
        'Status recalculado apos salvamento em lote da triagem.'
    );
}

echo json_encode([
    'success' => true,
    'updated_items' => $updatedItems,
    'lote_statuses' => $statuses,
    'ready_for_planning' => in_array('PRONTO_PLANEJAMENTO', $statuses, true),
]);
