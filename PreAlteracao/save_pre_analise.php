<?php
// PreAlteracao/save_pre_analise.php
// Salva a triagem de um item do lote de pre-alteracao.
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
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Payload invalido.']);
    exit;
}

$itemId = isset($data['item_id']) && is_numeric($data['item_id']) ? (int) $data['item_id'] : 0;
$resultado = strtoupper(trim((string) ($data['resultado'] ?? '')));
$nivel = isset($data['nivel_complexidade']) && is_numeric($data['nivel_complexidade'])
    ? (int) $data['nivel_complexidade']
    : null;
$tipoAlteracao = trim((string) ($data['tipo_alteracao'] ?? ''));
$acao = trim((string) ($data['acao'] ?? ''));
$necessitaRetorno = !empty($data['necessita_retorno']) ? 1 : 0;
$quantidadeComentarios = isset($data['quantidade_comentarios']) && is_numeric($data['quantidade_comentarios'])
    ? max(0, (int) $data['quantidade_comentarios'])
    : 0;
$responsavelId = isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null;

$resultadosValidos = ['ALTERACAO', 'SEM_ALTERACAO', 'AGUARDANDO_CLIENTE'];
if ($itemId <= 0 || !in_array($resultado, $resultadosValidos, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parametros invalidos.']);
    exit;
}

if ($resultado === 'ALTERACAO' && ($nivel === null || $nivel < 1 || $nivel > 5)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Selecione um nivel de complexidade de 1 a 5.']);
    exit;
}

if ($resultado !== 'ALTERACAO') {
    $nivel = null;
    $tipoAlteracao = '';
}

if ($resultado === 'AGUARDANDO_CLIENTE') {
    $necessitaRetorno = 1;
}

pre_alt_ensure_schema($conn);

$stmtLote = $conn->prepare(
    'SELECT
        pre_alt_lote_id,
        resultado,
        nivel_complexidade,
        tipo_alteracao,
        acao,
        necessita_retorno,
        quantidade_comentarios,
        responsavel_id
     FROM pre_alt_itens
     WHERE id = ?
     LIMIT 1'
);
if (!$stmtLote) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}
$stmtLote->bind_param('i', $itemId);
$stmtLote->execute();
$rowLote = $stmtLote->get_result()->fetch_assoc();
$stmtLote->close();

if (!$rowLote) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Item nao encontrado.']);
    exit;
}

$loteId = (int) $rowLote['pre_alt_lote_id'];

$stmt = $conn->prepare(
    "UPDATE pre_alt_itens
     SET resultado = ?,
         nivel_complexidade = ?,
         tipo_alteracao = NULLIF(?, ''),
         acao = NULLIF(?, ''),
         necessita_retorno = ?,
         quantidade_comentarios = ?,
         responsavel_id = ?,
         updated_at = NOW()
     WHERE id = ?"
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}

$stmt->bind_param('sissiiii', $resultado, $nivel, $tipoAlteracao, $acao, $necessitaRetorno, $quantidadeComentarios, $responsavelId, $itemId);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $stmt->error]);
    $stmt->close();
    exit;
}
$stmt->close();

$changes = [
    'resultado' => [$rowLote['resultado'] ?? null, $resultado],
    'nivel_complexidade' => [$rowLote['nivel_complexidade'] ?? null, $nivel],
    'tipo_alteracao' => [$rowLote['tipo_alteracao'] ?? null, $tipoAlteracao !== '' ? $tipoAlteracao : null],
    'acao' => [$rowLote['acao'] ?? null, $acao !== '' ? $acao : null],
    'necessita_retorno' => [(int) ($rowLote['necessita_retorno'] ?? 0), $necessitaRetorno],
    'quantidade_comentarios' => [$rowLote['quantidade_comentarios'] ?? null, $quantidadeComentarios],
    'responsavel_id' => [$rowLote['responsavel_id'] ?? null, $responsavelId],
];

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
        'Triagem da imagem atualizada.',
        $itemId
    );
}

$loteStatus = pre_alt_recalcular_status_lote($conn, $loteId, null, 'Status recalculado apos triagem de imagem.');

echo json_encode([
    'success' => true,
    'lote_id' => $loteId,
    'lote_status' => $loteStatus,
    'ready_for_planning' => $loteStatus === 'PRONTO_PLANEJAMENTO',
]);
