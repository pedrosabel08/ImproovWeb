<?php
// PreAlteracao/get_pre_alt_lote.php
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

$loteId = isset($_GET['lote_id']) && is_numeric($_GET['lote_id']) ? (int) $_GET['lote_id'] : 0;
if ($loteId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'lote_id invalido.']);
    exit;
}

pre_alt_ensure_schema($conn);

$stmtLote = $conn->prepare(
    "SELECT
        l.id AS lote_id,
        l.obra_id,
        l.status_id,
        l.data_finalizacao_cliente,
        l.status AS lote_status,
        o.nomenclatura,
        si.nome_status AS nome_etapa
     FROM pre_alt_lote l
     JOIN obra o ON o.idobra = l.obra_id
     JOIN status_imagem si ON si.idstatus = l.status_id
     WHERE l.id = ?
     LIMIT 1"
);
if (!$stmtLote) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}
$stmtLote->bind_param('i', $loteId);
$stmtLote->execute();
$lote = $stmtLote->get_result()->fetch_assoc();
$stmtLote->close();

if (!$lote) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Lote nao encontrado.']);
    exit;
}

$stmtItens = $conn->prepare(
    "SELECT
        pai.id AS item_id,
        pai.pre_alt_lote_id AS lote_id,
        pai.review_batch_item_id,
        pai.entrega_id,
        pai.entrega_item_id,
        pai.imagem_id,
        ico.imagem_nome AS nome,
        pai.resultado,
        pai.nivel_complexidade,
        pai.tipo_alteracao,
        pai.acao,
        pai.necessita_retorno,
        pai.responsavel_id,
        c.nome_colaborador AS responsavel_nome,
        rb.data_entrega_lote,
        rb.review_round,
        ss.nome_substatus,
        ico.substatus_id
     FROM pre_alt_itens pai
     JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = pai.imagem_id
     JOIN review_batch_items rbi ON rbi.id = pai.review_batch_item_id
     JOIN review_batch rb ON rb.id = rbi.review_batch_id
     LEFT JOIN substatus_imagem ss ON ss.id = ico.substatus_id
     LEFT JOIN colaborador c ON c.idcolaborador = pai.responsavel_id
     WHERE pai.pre_alt_lote_id = ?
     ORDER BY
        pai.entrega_id ASC,
        CAST(SUBSTRING_INDEX(ico.imagem_nome, '.', 1) AS UNSIGNED) ASC,
        ico.imagem_nome ASC,
        pai.id ASC"
);
if (!$stmtItens) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}
$stmtItens->bind_param('i', $loteId);
$stmtItens->execute();
$resItens = $stmtItens->get_result();

$itens = [];
while ($row = $resItens->fetch_assoc()) {
    $row['item_id'] = (int) $row['item_id'];
    $row['lote_id'] = (int) $row['lote_id'];
    $row['review_batch_item_id'] = (int) $row['review_batch_item_id'];
    $row['entrega_id'] = (int) $row['entrega_id'];
    $row['entrega_item_id'] = isset($row['entrega_item_id']) ? (int) $row['entrega_item_id'] : null;
    $row['imagem_id'] = (int) $row['imagem_id'];
    $row['nivel_complexidade'] = isset($row['nivel_complexidade']) ? (int) $row['nivel_complexidade'] : null;
    $row['necessita_retorno'] = (int) ($row['necessita_retorno'] ?? 0);
    $row['responsavel_id'] = isset($row['responsavel_id']) ? (int) $row['responsavel_id'] : null;
    $row['substatus_id'] = isset($row['substatus_id']) ? (int) $row['substatus_id'] : null;
    $row['review_round'] = (int) ($row['review_round'] ?? 1);
    $itens[] = $row;
}
$stmtItens->close();

$lote['lote_id'] = (int) $lote['lote_id'];
$lote['obra_id'] = (int) $lote['obra_id'];
$lote['status_id'] = (int) $lote['status_id'];

echo json_encode(['success' => true, 'lote' => $lote, 'itens' => $itens]);
