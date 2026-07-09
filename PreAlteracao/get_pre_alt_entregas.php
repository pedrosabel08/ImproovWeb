<?php
// PreAlteracao/get_pre_alt_entregas.php
// Retorna lotes de triagem de alteracao agrupados por obra/etapa/data.
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

pre_alt_planejamento_ensure_schema($conn);

$sql = "
    SELECT
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
        si.nome_status AS nome_etapa,
        c.nome_colaborador AS responsavel_nome,
        COUNT(DISTINCT rbi.review_batch_id) AS batch_count,
        COUNT(DISTINCT i.id) AS total_itens,
        SUM(CASE WHEN i.resultado = 'ALTERACAO' THEN 1 ELSE 0 END) AS count_alteracao,
        SUM(CASE WHEN i.resultado = 'SEM_ALTERACAO' THEN 1 ELSE 0 END) AS count_sem_alteracao,
        SUM(CASE WHEN i.resultado = 'AGUARDANDO_CLIENTE' OR i.necessita_retorno = 1 THEN 1 ELSE 0 END) AS count_aguardando,
        SUM(CASE WHEN i.resultado IS NULL OR (i.resultado = 'ALTERACAO' AND i.nivel_complexidade IS NULL) THEN 1 ELSE 0 END) AS count_incompleto,
        SUM(COALESCE(i.quantidade_comentarios, cm.comment_count, 0)) AS total_comentarios,
        SUM(COALESCE(cm.critical_count, 0)) AS comentarios_criticos,
        MAX(cm.last_comment_at) AS ultimo_comentario_em,
        MAX(h.created_at) AS ultima_movimentacao,
        MAX(COALESCE(cr.resolved_at, CASE WHEN rb.status = 'RESOLVED' THEN rb.updated_at ELSE NULL END)) AS lote_resolvido_em,
        GROUP_CONCAT(DISTINCT rb.review_round ORDER BY rb.review_round SEPARATOR ',') AS review_rounds,
        SUM(CASE WHEN i.nivel_complexidade = 1 THEN 1 ELSE 0 END) AS nivel_1,
        SUM(CASE WHEN i.nivel_complexidade = 2 THEN 1 ELSE 0 END) AS nivel_2,
        SUM(CASE WHEN i.nivel_complexidade = 3 THEN 1 ELSE 0 END) AS nivel_3,
        SUM(CASE WHEN i.nivel_complexidade = 4 THEN 1 ELSE 0 END) AS nivel_4,
        SUM(CASE WHEN i.nivel_complexidade = 5 THEN 1 ELSE 0 END) AS nivel_5,
        GROUP_CONCAT(DISTINCT i.entrega_id ORDER BY i.entrega_id SEPARATOR ',') AS entrega_ids
    FROM pre_alt_lote l
    JOIN obra o ON o.idobra = l.obra_id
    LEFT JOIN cliente cli ON cli.idcliente = o.cliente
    JOIN status_imagem si ON si.idstatus = l.status_id
    LEFT JOIN pre_alt_itens i ON i.pre_alt_lote_id = l.id
    LEFT JOIN pre_alt_diagramas d ON d.pre_alt_lote_id = l.id
    LEFT JOIN review_batch_items rbi ON rbi.id = i.review_batch_item_id
    LEFT JOIN review_batch rb ON rb.id = rbi.review_batch_id
    LEFT JOIN cobranca_review cr ON cr.review_batch_id = rb.id
    LEFT JOIN colaborador c ON c.idcolaborador = COALESCE(l.responsavel_id, l.created_by)
    LEFT JOIN (
        SELECT
            rbi2.id AS review_batch_item_id,
            COUNT(ci.id) AS comment_count,
            SUM(CASE WHEN LOWER(COALESCE(ci.cor, '')) IN ('#ff0000', '#ef4444', 'red') THEN 1 ELSE 0 END) AS critical_count,
            MAX(ci.data) AS last_comment_at
        FROM review_batch_items rbi2
        LEFT JOIN entregas_p00_versoes pv ON pv.id = rbi2.p00_versao_id
        LEFT JOIN comentarios_imagem ci ON ci.ap_imagem_id = pv.historico_id
        GROUP BY rbi2.id
    ) cm ON cm.review_batch_item_id = i.review_batch_item_id
    LEFT JOIN (
        SELECT pre_alt_lote_id, MAX(created_at) AS created_at
        FROM pre_alt_lote_historico
        GROUP BY pre_alt_lote_id
    ) h ON h.pre_alt_lote_id = l.id
    WHERE o.status_obra = 0
      AND l.status <> 'CANCELADO'
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
    ORDER BY
        FIELD(l.status, 'EM_TRIAGEM', 'AGUARDANDO_CLIENTE', 'PRONTO_PLANEJAMENTO', 'PLANEJADO'),
        l.data_finalizacao_cliente DESC,
        o.nomenclatura ASC,
        si.nome_status ASC
";

$res = $conn->query($sql);
if (!$res) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}

$lotes = [];
$obrasMap = [];

while ($row = $res->fetch_assoc()) {
    $row['lote_id'] = (int) $row['lote_id'];
    $row['obra_id'] = (int) $row['obra_id'];
    $row['status_id'] = (int) $row['status_id'];
    $row['cliente_id'] = isset($row['cliente_id']) ? (int) $row['cliente_id'] : null;
    $row['responsavel_id'] = isset($row['responsavel_id']) ? (int) $row['responsavel_id'] : null;
    $row['planejamento_id'] = isset($row['planejamento_id']) ? (int) $row['planejamento_id'] : null;
    $row['batch_count'] = (int) ($row['batch_count'] ?? 0);
    $row['total_itens'] = (int) ($row['total_itens'] ?? 0);
    $row['count_alteracao'] = (int) ($row['count_alteracao'] ?? 0);
    $row['count_sem_alteracao'] = (int) ($row['count_sem_alteracao'] ?? 0);
    $row['count_aguardando'] = (int) ($row['count_aguardando'] ?? 0);
    $row['count_incompleto'] = (int) ($row['count_incompleto'] ?? 0);
    $row['total_comentarios'] = (int) ($row['total_comentarios'] ?? 0);
    $row['comentarios_criticos'] = (int) ($row['comentarios_criticos'] ?? 0);
    $row['classificados'] = max(0, $row['total_itens'] - $row['count_incompleto']);
    $row['progresso'] = $row['total_itens'] > 0 ? (int) round(($row['classificados'] / $row['total_itens']) * 100) : 0;
    $resolvidoData = substr((string) ($row['lote_resolvido_em'] ?? ''), 0, 10);
    $row['prazo_operacional'] = entregas_valid_date($resolvidoData)
        ? entregas_adicionar_dias_uteis($resolvidoData, 1)
        : null;
    $row['ultima_atualizacao'] = $row['ultima_movimentacao'] ?: ($row['ultimo_comentario_em'] ?: $row['updated_at']);
    for ($nivel = 1; $nivel <= 5; $nivel++) {
        $row['nivel_' . $nivel] = (int) ($row['nivel_' . $nivel] ?? 0);
    }
    $lotes[] = $row;
    $obrasMap[$row['obra_id']] = $row['nomenclatura'];
}

$obras = [];
foreach ($obrasMap as $id => $nome) {
    $obras[] = ['idobra' => (int) $id, 'nomenclatura' => $nome];
}
usort($obras, static fn($a, $b) => strcmp($a['nomenclatura'], $b['nomenclatura']));

$comentariosPorLote = [];
$sqlComentarios = "
    SELECT
        pai.pre_alt_lote_id,
        ico.imagem_nome AS nome,
        COALESCE(pai.quantidade_comentarios, cm.comment_count, 0) AS comment_count,
        COALESCE(cm.critical_count, 0) AS critical_count
    FROM pre_alt_itens pai
    JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = pai.imagem_id
    LEFT JOIN review_batch_items rbi ON rbi.id = pai.review_batch_item_id
    LEFT JOIN entregas_p00_versoes pv ON pv.id = rbi.p00_versao_id
    LEFT JOIN (
        SELECT
            ap_imagem_id,
            COUNT(id) AS comment_count,
            SUM(CASE WHEN LOWER(COALESCE(cor, '')) IN ('#ff0000', '#ef4444', 'red') THEN 1 ELSE 0 END) AS critical_count
        FROM comentarios_imagem
        GROUP BY ap_imagem_id
    ) cm ON cm.ap_imagem_id = pv.historico_id
    ORDER BY pai.pre_alt_lote_id ASC, comment_count DESC, ico.imagem_nome ASC
";
$resComentarios = $conn->query($sqlComentarios);
if ($resComentarios) {
    while ($row = $resComentarios->fetch_assoc()) {
        $loteId = (int) $row['pre_alt_lote_id'];
        if (!isset($comentariosPorLote[$loteId])) {
            $comentariosPorLote[$loteId] = [];
        }
        $comentariosPorLote[$loteId][] = [
            'nome' => $row['nome'],
            'comment_count' => (int) ($row['comment_count'] ?? 0),
            'critical_count' => (int) ($row['critical_count'] ?? 0),
        ];
    }
}

foreach ($lotes as &$lote) {
    $comentarios = $comentariosPorLote[$lote['lote_id']] ?? [];
    $lote['comentarios_por_imagem'] = array_slice($comentarios, 0, 4);
    $lote['comentarios_por_imagem_extra'] = max(0, count($comentarios) - 4);
}
unset($lote);

$clientesMap = [];
$responsaveisMap = [];
$kpis = [
    'total_imagens' => 0,
    'total_lotes' => count($lotes),
    'aguardando_cliente' => 0,
    'comentarios' => 0,
    'comentarios_criticos' => 0,
    'em_planejamento' => 0,
    'classificados' => 0,
];

foreach ($lotes as $lote) {
    $kpis['total_imagens'] += $lote['total_itens'];
    $kpis['aguardando_cliente'] += $lote['count_aguardando'];
    $kpis['comentarios'] += $lote['total_comentarios'];
    $kpis['comentarios_criticos'] += $lote['comentarios_criticos'];
    $kpis['classificados'] += $lote['classificados'];
    if ($lote['lote_status'] === 'PRONTO_PLANEJAMENTO' || $lote['lote_status'] === 'PLANEJADO') {
        $kpis['em_planejamento'] += 1;
    }
    if (!empty($lote['cliente_id'])) {
        $clientesMap[$lote['cliente_id']] = $lote['nome_cliente'];
    }
    if (!empty($lote['responsavel_id'])) {
        $responsaveisMap[$lote['responsavel_id']] = $lote['responsavel_nome'] ?: ('Colaborador #' . $lote['responsavel_id']);
    }
}
$kpis['progresso_geral'] = $kpis['total_imagens'] > 0 ? (int) round(($kpis['classificados'] / $kpis['total_imagens']) * 100) : 0;

$clientes = [];
foreach ($clientesMap as $id => $nome) {
    $clientes[] = ['idcliente' => (int) $id, 'nome_cliente' => $nome];
}
usort($clientes, static fn($a, $b) => strcmp($a['nome_cliente'], $b['nome_cliente']));

$responsaveis = [];
foreach ($responsaveisMap as $id => $nome) {
    $responsaveis[] = ['idcolaborador' => (int) $id, 'nome_colaborador' => $nome];
}
usort($responsaveis, static fn($a, $b) => strcmp($a['nome_colaborador'], $b['nome_colaborador']));

echo json_encode([
    'success' => true,
    'lotes' => $lotes,
    'obras' => $obras,
    'clientes' => $clientes,
    'responsaveis' => $responsaveis,
    'kpis' => $kpis,
]);
