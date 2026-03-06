<?php
include '../conexao.php';

header('Content-Type: application/json');

// ── Helpers ──────────────────────────────────────────────────────
function esc($conn, $v)
{
    return $conn->real_escape_string($v);
}

// ── Filtros comuns ───────────────────────────────────────────────
$obra_id          = isset($_GET['obra_id'])      ? intval($_GET['obra_id'])           : null;
$filtro_tipo      = isset($_GET['tipo'])         ? esc($conn, $_GET['tipo'])          : null;
$filtro_tipo_arq  = isset($_GET['tipo_arquivo']) ? esc($conn, $_GET['tipo_arquivo'])  : null;
$filtro_status    = isset($_GET['status'])       ? esc($conn, $_GET['status'])        : null;
$filtro_cat       = isset($_GET['categoria_id']) ? intval($_GET['categoria_id'])      : null;
$limit_only       = isset($_GET['limit'])        ? intval($_GET['limit'])             : null;

// ── Modo server-side DataTables (quando o parâmetro 'draw' existe) ─
$is_dt = isset($_GET['draw']);

// ── Base WHERE ───────────────────────────────────────────────────
$where = "WHERE 1";
if ($obra_id)       $where .= " AND a.obra_id = $obra_id";
if ($filtro_tipo)   $where .= " AND ti.nome = '$filtro_tipo'";
if ($filtro_tipo_arq) $where .= " AND a.tipo = '$filtro_tipo_arq'";
if ($filtro_status) $where .= " AND a.status = '$filtro_status'";
if ($filtro_cat)    $where .= " AND a.categoria_id = $filtro_cat";

$base_from = "FROM arquivos a
    LEFT JOIN obra o ON a.obra_id = o.idobra
    LEFT JOIN tipo_imagem ti ON a.tipo_imagem_id = ti.id_tipo_imagem
    LEFT JOIN colaborador c ON c.idcolaborador = a.colaborador_id";

$select_cols = "a.idarquivo, a.obra_id, a.categoria_id, a.tipo_imagem_id, a.imagem_id,
    a.nome_original, a.nome_interno, a.caminho, a.tipo, a.versao,
    a.status, a.origem, a.recebido_por, a.recebido_em, a.sufixo,
    a.descricao, a.tamanho, a.colaborador_id,
    o.nomenclatura AS projeto,
    ti.nome AS tipo_imagem,
    c.nome_colaborador AS colaborador_nome";

if ($is_dt) {
    // ── DataTables server-side ────────────────────────────────────
    $draw   = intval($_GET['draw']);
    $start  = isset($_GET['start'])  ? intval($_GET['start'])  : 0;
    $length = isset($_GET['length']) ? intval($_GET['length']) : 25;
    if ($length < 1 || $length > 500) $length = 25;

    // Search
    $search = isset($_GET['search']['value']) ? esc($conn, $_GET['search']['value']) : '';
    $search_where = '';
    if ($search !== '') {
        $search_where = " AND (a.nome_interno LIKE '%$search%'
            OR a.nome_original LIKE '%$search%'
            OR o.nomenclatura  LIKE '%$search%'
            OR ti.nome         LIKE '%$search%'
            OR a.tipo          LIKE '%$search%'
            OR a.descricao     LIKE '%$search%')";
    }

    // Ordering — map DataTables column index → SQL column
    $col_map = [
        0 => 'a.tipo',
        1 => 'a.nome_interno',
        2 => 'o.nomenclatura',
        3 => 'a.categoria_id',
        4 => 'a.tipo',
        5 => 'a.tamanho',
        6 => 'a.recebido_em',
        7 => 'a.status',
    ];
    $order_sql = 'a.recebido_em DESC';
    if (isset($_GET['order'][0])) {
        $ord_col = intval($_GET['order'][0]['column']);
        $ord_dir = strtoupper($_GET['order'][0]['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        if (isset($col_map[$ord_col])) {
            $order_sql = $col_map[$ord_col] . ' ' . $ord_dir;
        }
    }

    // Counts
    $cnt_total    = $conn->query("SELECT COUNT(*) FROM arquivos a LEFT JOIN obra o ON a.obra_id=o.idobra LEFT JOIN tipo_imagem ti ON a.tipo_imagem_id=ti.id_tipo_imagem $where");
    $records_total = (int)$cnt_total->fetch_row()[0];

    $cnt_filtered = $conn->query("SELECT COUNT(*) FROM arquivos a LEFT JOIN obra o ON a.obra_id=o.idobra LEFT JOIN tipo_imagem ti ON a.tipo_imagem_id=ti.id_tipo_imagem $where $search_where");
    $records_filtered = (int)$cnt_filtered->fetch_row()[0];

    // Data
    $sql = "SELECT $select_cols $base_from $where $search_where ORDER BY $order_sql LIMIT $start, $length";
    $result = $conn->query($sql);
    $data = [];
    while ($row = $result->fetch_assoc()) $data[] = $row;

    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => $records_total,
        'recordsFiltered' => $records_filtered,
        'data'            => $data,
    ]);
} else {
    // ── Modo legado / recentes (sem draw) ────────────────────────
    $sql = "SELECT $select_cols $base_from $where ORDER BY a.recebido_em DESC";
    if ($limit_only) $sql .= " LIMIT $limit_only";
    $result = $conn->query($sql);
    $arquivos = [];
    while ($row = $result->fetch_assoc()) $arquivos[] = $row;
    echo json_encode($arquivos);
}

$conn->close();
