<?php
// PreAlteracao/get_pre_alt_lote.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/planejamento_helpers.php';
require_once __DIR__ . '/../Entregas/prazo_entrega_helper.php';

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

pre_alt_planejamento_ensure_schema($conn);

$stmtLote = $conn->prepare(
    "SELECT
        l.id AS lote_id,
        l.obra_id,
        l.status_id,
        l.data_finalizacao_cliente,
        l.status AS lote_status,
        l.prioridade,
        l.prazo,
        COALESCE(l.responsavel_id, l.created_by) AS responsavel_id,
        l.created_by,
        l.created_at,
        l.updated_at,
        d.id AS planejamento_id,
        d.status AS planejamento_status,
        d.published_at AS planejamento_published_at,
        d.updated_at AS planejamento_updated_at,
        o.nomenclatura,
        o.link_review,
        cli.idcliente AS cliente_id,
        cli.nome_cliente,
        c.nome_colaborador AS responsavel_nome,
        MAX(h.created_at) AS ultima_movimentacao,
        MAX(COALESCE(cr.resolved_at, CASE WHEN rb.status = 'RESOLVED' THEN rb.updated_at ELSE NULL END)) AS lote_resolvido_em,
        si.nome_status AS nome_etapa
     FROM pre_alt_lote l
     JOIN obra o ON o.idobra = l.obra_id
     LEFT JOIN cliente cli ON cli.idcliente = o.cliente
     JOIN status_imagem si ON si.idstatus = l.status_id
     LEFT JOIN colaborador c ON c.idcolaborador = COALESCE(l.responsavel_id, l.created_by)
     LEFT JOIN pre_alt_lote_historico h ON h.pre_alt_lote_id = l.id
     LEFT JOIN pre_alt_lote_batches plb ON plb.pre_alt_lote_id = l.id
     LEFT JOIN pre_alt_diagramas d ON d.pre_alt_lote_id = l.id
     LEFT JOIN review_batch rb ON rb.id = plb.review_batch_id
     LEFT JOIN cobranca_review cr ON cr.review_batch_id = rb.id
     WHERE l.id = ?
     GROUP BY
        l.id,
        l.obra_id,
        l.status_id,
        l.data_finalizacao_cliente,
        l.status,
        l.prioridade,
        l.prazo,
        l.responsavel_id,
        l.created_by,
        l.created_at,
        l.updated_at,
        d.id,
        d.status,
        d.published_at,
        d.updated_at,
        o.nomenclatura,
        o.link_review,
        cli.idcliente,
        cli.nome_cliente,
        c.nome_colaborador,
        si.nome_status
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
        ico.tipo_imagem,
        pai.resultado,
        pai.nivel_complexidade,
        pai.tipo_alteracao,
        pai.acao,
        pai.necessita_retorno,
        pai.quantidade_comentarios,
        pai.reanalise_pos_retorno,
        pai.responsavel_id,
        c.nome_colaborador AS responsavel_nome,
        rb.data_entrega_lote,
        rb.review_round,
        rbi.p00_versao_id,
        pv.historico_id,
        COALESCE(pai.quantidade_comentarios, cm.comment_count, 0) AS comment_count,
        cm.comment_count AS review_comment_count,
        COALESCE(cm.critical_count, 0) AS critical_count,
        cm.last_comment_at,
        ss.nome_substatus,
        ico.substatus_id
     FROM pre_alt_itens pai
     JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = pai.imagem_id
     JOIN review_batch_items rbi ON rbi.id = pai.review_batch_item_id
     JOIN review_batch rb ON rb.id = rbi.review_batch_id
     LEFT JOIN entregas_p00_versoes pv ON pv.id = rbi.p00_versao_id
     LEFT JOIN (
        SELECT
            ap_imagem_id,
            COUNT(id) AS comment_count,
            SUM(CASE WHEN LOWER(COALESCE(cor, '')) IN ('#ff0000', '#ef4444', 'red') THEN 1 ELSE 0 END) AS critical_count,
            MAX(data) AS last_comment_at
        FROM comentarios_imagem
        GROUP BY ap_imagem_id
     ) cm ON cm.ap_imagem_id = pv.historico_id
     LEFT JOIN substatus_imagem ss ON ss.id = ico.substatus_id
     LEFT JOIN colaborador c ON c.idcolaborador = pai.responsavel_id
     WHERE pai.pre_alt_lote_id = ?
     ORDER BY
        ico.idimagens_cliente_obra ASC,
        pai.entrega_id ASC,
        CAST(SUBSTRING_INDEX(ico.imagem_nome, '.', 1) AS UNSIGNED) ASC,
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
    $row['tipo_imagem'] = (string) ($row['tipo_imagem'] ?? '');
    $row['nivel_complexidade'] = isset($row['nivel_complexidade']) ? (int) $row['nivel_complexidade'] : null;
    $row['necessita_retorno'] = (int) ($row['necessita_retorno'] ?? 0);
    $row['quantidade_comentarios'] = isset($row['quantidade_comentarios']) ? (int) $row['quantidade_comentarios'] : null;
    $row['reanalise_pos_retorno'] = (int) ($row['reanalise_pos_retorno'] ?? 0);
    $row['responsavel_id'] = isset($row['responsavel_id']) ? (int) $row['responsavel_id'] : null;
    $row['substatus_id'] = isset($row['substatus_id']) ? (int) $row['substatus_id'] : null;
    $row['review_round'] = (int) ($row['review_round'] ?? 1);
    $row['p00_versao_id'] = isset($row['p00_versao_id']) ? (int) $row['p00_versao_id'] : null;
    $row['historico_id'] = isset($row['historico_id']) ? (int) $row['historico_id'] : null;
    $row['comment_count'] = (int) ($row['comment_count'] ?? 0);
    $row['review_comment_count'] = isset($row['review_comment_count']) ? (int) $row['review_comment_count'] : null;
    $row['critical_count'] = (int) ($row['critical_count'] ?? 0);
    $itens[] = $row;
}
$stmtItens->close();

$lote['lote_id'] = (int) $lote['lote_id'];
$lote['obra_id'] = (int) $lote['obra_id'];
$lote['status_id'] = (int) $lote['status_id'];
$lote['cliente_id'] = isset($lote['cliente_id']) ? (int) $lote['cliente_id'] : null;
$lote['responsavel_id'] = isset($lote['responsavel_id']) ? (int) $lote['responsavel_id'] : null;
$lote['planejamento_id'] = isset($lote['planejamento_id']) ? (int) $lote['planejamento_id'] : null;
$resolvidoData = substr((string) ($lote['lote_resolvido_em'] ?? ''), 0, 10);
$lote['prazo_operacional'] = entregas_valid_date($resolvidoData)
    ? entregas_adicionar_dias_uteis($resolvidoData, 1)
    : null;
$lote['ultima_atualizacao'] = $lote['ultima_movimentacao'] ?: $lote['updated_at'];
$interacoesCliente = pre_alt_buscar_interacoes_cliente($conn, $loteId);

echo json_encode([
    'success' => true,
    'lote' => $lote,
    'itens' => $itens,
    'interacoes_cliente' => $interacoesCliente,
]);
