<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../config/kpi_access.php';

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexaoMain.php';

$currentUserId = (int) ($_SESSION['idusuario'] ?? 0);
$kpiPermissions = improov_kpi_permissions_for_user($currentUserId);

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode([
        'kpis' => [],
        'permissions' => $kpiPermissions,
    ]);
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if (!improov_can_view_kpi_scope('management', $currentUserId)) {
    echo json_encode([
        'kpis' => [],
        'permissions' => $kpiPermissions,
    ]);
    exit;
}

// ── Input validation ──────────────────────────────────────────────────────────
$obra_id = isset($_GET['obra_id']) ? (int) $_GET['obra_id'] : null;

$conn = conectarBanco();

// Date windows
$now_6m  = date('Y-m-d H:i:s', strtotime('-3 months'));
$now_12m = date('Y-m-d H:i:s', strtotime('-6 months'));
$now_30d = date('Y-m-d H:i:s', strtotime('-30 days'));
$now_60d = date('Y-m-d H:i:s', strtotime('-60 days'));

// View mode: obra-specific → full-period main KPI + 30d trend
//            general      → last 6m main KPI + 6m trend
$is_obra_view    = $obra_id !== null;
$trend_cur_from  = $is_obra_view ? $now_30d  : $now_6m;
$trend_prev_from = $is_obra_view ? $now_60d  : $now_12m;
$trend_prev_to   = $is_obra_view ? $now_30d  : $now_6m;
$trend_label_sfx = $is_obra_view ? 'vs. 30d ant.' : 'vs. 3m ant.';

// Main KPI date clause: obra view = all time; general = last 6m
$main_date_clause     = $is_obra_view ? '' : 'AND h.data_aprovacao >= ?';
$main_date_clause_end = $is_obra_view ? '' : 'AND h_end.data_aprovacao >= ?';

// Optional obra filter clause and bind helpers
$obra_clause = $obra_id ? 'AND ico.obra_id = ?' : '';

// ── Helpers ───────────────────────────────────────────────────────────────────

function fr_kpi_fetch(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }
    $res  = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function fr_kpi_median(array $values): float
{
    if (empty($values)) {
        return 0.0;
    }
    sort($values, SORT_NUMERIC);
    $n   = count($values);
    $mid = (int) floor($n / 2);
    return $n % 2 === 1
        ? (float) $values[$mid]
        : ($values[$mid - 1] + $values[$mid]) / 2.0;
}

function fr_kpi_format_horas(float $h): string
{
    if ($h < 1) {
        return '<1h';
    }
    if ($h < 24) {
        return round($h) . 'h';
    }
    $d   = (int) floor($h / 24);
    $rem = (int) round($h - $d * 24);
    return $d . 'd' . ($rem > 0 ? ' ' . $rem . 'h' : '');
}

// ── KPI 1 · Em aprovação (real-time snapshot + historical throughput trend) ───

// Current: items currently in 'Em aprovação' (active obras)
$rows_cur = fr_kpi_fetch(
    $conn,
    "SELECT fi.funcao_id, f.nome_funcao, COUNT(*) AS cnt
     FROM funcao_imagem fi
     JOIN funcao f ON f.idfuncao = fi.funcao_id
     JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
     JOIN obra o ON o.idobra = ico.obra_id
     WHERE fi.status = 'Em aprovação'
       AND o.status_obra = 0
       AND fi.funcao_id NOT IN (7, 9)
       $obra_clause
     GROUP BY fi.funcao_id",
    $obra_id ? 'i' : '',
    $obra_id ? [$obra_id] : []
);

$ea_total        = 0;
$ea_by_funcao    = [];
foreach ($rows_cur as $r) {
    $ea_total += (int) $r['cnt'];
    $ea_by_funcao[(int) $r['funcao_id']] = [
        'nome_funcao' => $r['nome_funcao'],
        'cnt'         => (int) $r['cnt'],
    ];
}

// Trend: entries into 'Em aprovação' – current 6m vs. previous 6m
$base_trend_joins = "
    JOIN funcao_imagem fi ON fi.idfuncao_imagem = h.funcao_imagem_id
    JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
    JOIN obra o ON o.idobra = ico.obra_id
    WHERE h.status_novo = 'Em aprovação'
      AND o.status_obra = 0
      AND fi.funcao_id NOT IN (7, 9)";

$ea_cur_rows = fr_kpi_fetch(
    $conn,
    "SELECT COUNT(DISTINCT h.funcao_imagem_id) AS cnt
     FROM historico_aprovacoes h
     $base_trend_joins
       AND h.data_aprovacao >= ?
       $obra_clause",
    $obra_id ? 'si' : 's',
    $obra_id ? [$trend_cur_from, $obra_id] : [$trend_cur_from]
);
$ea_trend_cur = (int) ($ea_cur_rows[0]['cnt'] ?? 0);

$ea_prev_rows = fr_kpi_fetch(
    $conn,
    "SELECT COUNT(DISTINCT h.funcao_imagem_id) AS cnt
     FROM historico_aprovacoes h
     $base_trend_joins
       AND h.data_aprovacao >= ?
       AND h.data_aprovacao < ?
       $obra_clause",
    $obra_id ? 'ssi' : 'ss',
    $obra_id ? [$trend_prev_from, $trend_prev_to, $obra_id] : [$trend_prev_from, $trend_prev_to]
);
$ea_trend_prev = (int) ($ea_prev_rows[0]['cnt'] ?? 0);

$ea_diff      = $ea_trend_cur - $ea_trend_prev;
$ea_trend_lbl = ($ea_diff >= 0 ? '+' : '') . $ea_diff . ' ' . $trend_label_sfx;
$ea_trend_dir = $ea_diff >= 0 ? 'up' : 'down';

// ── KPI 2 · % de Ajustes (6-month window) ────────────────────────────────────

$aj_rows = fr_kpi_fetch(
    $conn,
    "SELECT
         fi.funcao_id,
         f.nome_funcao,
         COUNT(DISTINCT fi.idfuncao_imagem) AS total_itens,
         COUNT(DISTINCT CASE
             WHEN h.status_novo IN ('Ajuste', 'Aprovado com ajustes')
             THEN fi.idfuncao_imagem
         END) AS itens_com_ajuste
     FROM historico_aprovacoes h
     JOIN funcao_imagem fi ON fi.idfuncao_imagem = h.funcao_imagem_id
     JOIN funcao f ON f.idfuncao = fi.funcao_id
     JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
     JOIN obra o ON o.idobra = ico.obra_id
     WHERE o.status_obra = 0
       AND fi.funcao_id NOT IN (7, 9)
       $main_date_clause
       $obra_clause
     GROUP BY fi.funcao_id",
    $obra_id ? 'i' : 's',
    $obra_id ? [$obra_id] : [$now_6m]
);

$aj_total        = 0;
$aj_com_ajuste   = 0;
$aj_by_funcao    = [];
foreach ($aj_rows as $r) {
    $aj_total      += (int) $r['total_itens'];
    $aj_com_ajuste += (int) $r['itens_com_ajuste'];
    $pct = $r['total_itens'] > 0
        ? round((int) $r['itens_com_ajuste'] / (int) $r['total_itens'] * 100, 1)
        : 0.0;
    $aj_by_funcao[(int) $r['funcao_id']] = [
        'nome_funcao'      => $r['nome_funcao'],
        'total_itens'      => (int) $r['total_itens'],
        'itens_com_ajuste' => (int) $r['itens_com_ajuste'],
        'pct'              => $pct,
    ];
}
$pct_ajustes_cur = $aj_total > 0 ? round($aj_com_ajuste / $aj_total * 100, 1) : 0.0;

// Trend: same for previous 6m
$aj_prev_rows = fr_kpi_fetch(
    $conn,
    "SELECT
         COUNT(DISTINCT fi.idfuncao_imagem) AS total_itens,
         COUNT(DISTINCT CASE
             WHEN h.status_novo IN ('Ajuste', 'Aprovado com ajustes')
             THEN fi.idfuncao_imagem
         END) AS itens_com_ajuste
     FROM historico_aprovacoes h
     JOIN funcao_imagem fi ON fi.idfuncao_imagem = h.funcao_imagem_id
     JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
     JOIN obra o ON o.idobra = ico.obra_id
     WHERE h.data_aprovacao >= ?
       AND h.data_aprovacao < ?
       AND o.status_obra = 0
       AND fi.funcao_id NOT IN (7, 9)
       $obra_clause",
    $obra_id ? 'ssi' : 'ss',
    $obra_id ? [$trend_prev_from, $trend_prev_to, $obra_id] : [$trend_prev_from, $trend_prev_to]
);
$ajp_total      = (int) ($aj_prev_rows[0]['total_itens'] ?? 0);
$ajp_com_ajuste = (int) ($aj_prev_rows[0]['itens_com_ajuste'] ?? 0);
$pct_ajustes_prev = $ajp_total > 0 ? round($ajp_com_ajuste / $ajp_total * 100, 1) : 0.0;

$aj_diff      = round($pct_ajustes_cur - $pct_ajustes_prev, 1);
$aj_trend_lbl = ($aj_diff >= 0 ? '+' : '') . $aj_diff . '% ' . $trend_label_sfx;
$aj_trend_dir = $aj_diff <= 0 ? 'down' : 'up'; // lower % is better

// ── KPI 3 · Mediana de tempo para aprovação (6-month window) ─────────────────

// For each approval resolution in the current window, find the preceding
// 'Em aprovação' entry and compute the elapsed hours.
$med_sql = "
    SELECT
        fi.funcao_id,
        f.nome_funcao,
        TIMESTAMPDIFF(HOUR, h_start.data_aprovacao, h_end.data_aprovacao) AS horas
    FROM historico_aprovacoes h_end
    JOIN funcao_imagem fi ON fi.idfuncao_imagem = h_end.funcao_imagem_id
    JOIN funcao f ON f.idfuncao = fi.funcao_id
    JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
    JOIN obra o ON o.idobra = ico.obra_id
    JOIN historico_aprovacoes h_start ON h_start.funcao_imagem_id = h_end.funcao_imagem_id
        AND h_start.status_novo = 'Em aprovação'
        AND h_start.data_aprovacao = (
            SELECT MAX(hs2.data_aprovacao)
            FROM historico_aprovacoes hs2
            WHERE hs2.funcao_imagem_id = h_end.funcao_imagem_id
              AND hs2.status_novo = 'Em aprovação'
              AND hs2.data_aprovacao < h_end.data_aprovacao
        )
    WHERE h_end.status_anterior = 'Em aprovação'
      AND h_end.status_novo IN ('Aprovado', 'Aprovado com ajustes')
      AND o.status_obra = 0
      AND fi.funcao_id NOT IN (7, 9)
      $main_date_clause_end
      $obra_clause
    HAVING horas >= 0";

$med_rows = fr_kpi_fetch(
    $conn,
    $med_sql,
    $obra_id ? 'i' : 's',
    $obra_id ? [$obra_id] : [$now_6m]
);

$med_all_horas     = [];
$med_by_funcao     = [];
foreach ($med_rows as $r) {
    $h   = (int) $r['horas'];
    $fid = (int) $r['funcao_id'];
    $med_all_horas[] = $h;
    if (!isset($med_by_funcao[$fid])) {
        $med_by_funcao[$fid] = ['nome_funcao' => $r['nome_funcao'], 'horas' => []];
    }
    $med_by_funcao[$fid]['horas'][] = $h;
}
$mediana_cur = fr_kpi_median($med_all_horas);

// Trend: same for previous 6m
$med_prev_sql = "
    SELECT
        TIMESTAMPDIFF(HOUR, h_start.data_aprovacao, h_end.data_aprovacao) AS horas
    FROM historico_aprovacoes h_end
    JOIN funcao_imagem fi ON fi.idfuncao_imagem = h_end.funcao_imagem_id
    JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
    JOIN obra o ON o.idobra = ico.obra_id
    JOIN historico_aprovacoes h_start ON h_start.funcao_imagem_id = h_end.funcao_imagem_id
        AND h_start.status_novo = 'Em aprovação'
        AND h_start.data_aprovacao = (
            SELECT MAX(hs2.data_aprovacao)
            FROM historico_aprovacoes hs2
            WHERE hs2.funcao_imagem_id = h_end.funcao_imagem_id
              AND hs2.status_novo = 'Em aprovação'
              AND hs2.data_aprovacao < h_end.data_aprovacao
        )
    WHERE h_end.status_anterior = 'Em aprovação'
      AND h_end.status_novo IN ('Aprovado', 'Aprovado com ajustes')
      AND h_end.data_aprovacao >= ?
      AND h_end.data_aprovacao < ?
      AND o.status_obra = 0
      AND fi.funcao_id NOT IN (7, 9)
      $obra_clause
    HAVING horas >= 0";

$med_prev_rows = fr_kpi_fetch(
    $conn,
    $med_prev_sql,
    $obra_id ? 'ssi' : 'ss',
    $obra_id ? [$trend_prev_from, $trend_prev_to, $obra_id] : [$trend_prev_from, $trend_prev_to]
);
$med_prev_horas = array_map(static fn($r) => (int) $r['horas'], $med_prev_rows);
$mediana_prev   = fr_kpi_median($med_prev_horas);

$med_diff      = round($mediana_cur - $mediana_prev, 0);
$med_trend_lbl = ($med_diff >= 0 ? '+' : '') . $med_diff . 'h ' . $trend_label_sfx;
$med_trend_dir = $med_diff <= 0 ? 'down' : 'up'; // lower time is better

$conn->close();

// ── Build detail arrays ───────────────────────────────────────────────────────

// KPI 1 detail: sorted by count desc
$kpi1_detail = [];
foreach ($ea_by_funcao as $data) {
    $kpi1_detail[] = [
        'function' => $data['nome_funcao'],
        'value'    => $data['cnt'],
    ];
}
usort($kpi1_detail, static fn($a, $b) => $b['value'] - $a['value']);

// KPI 2 detail: sorted by % desc
$kpi2_detail = [];
foreach ($aj_by_funcao as $data) {
    $kpi2_detail[] = [
        'function'  => $data['nome_funcao'],
        'value'     => $data['pct'],
        'raw_label' => $data['itens_com_ajuste'] . '/' . $data['total_itens'],
    ];
}
usort($kpi2_detail, static fn($a, $b) => $b['value'] <=> $a['value']);

// KPI 3 detail: sorted by median asc (faster functions first)
$kpi3_detail = [];
foreach ($med_by_funcao as $data) {
    $med = fr_kpi_median($data['horas']);
    $kpi3_detail[] = [
        'function' => $data['nome_funcao'],
        'value'    => $med,
    ];
}
usort($kpi3_detail, static fn($a, $b) => $a['value'] <=> $b['value']);

// ── Response ──────────────────────────────────────────────────────────────────

echo json_encode([
    'permissions' => $kpiPermissions,
    'kpis' => [
        [
            'key'       => 'em_aprovacao',
            'label'     => 'Em aprovação',
            'icon'      => 'fa-solid fa-clock',
            'tone'      => 'accent',
            'value'     => $ea_total,
            'value_fmt' => (string) $ea_total,
            'trend'     => $ea_trend_lbl,
            'trend_dir' => $ea_trend_dir,
            'detail'    => $kpi1_detail,
        ],
        [
            'key'       => 'pct_ajustes',
            'label'     => '% de Ajustes',
            'icon'      => 'fa-solid fa-rotate-left',
            'tone'      => 'warning',
            'value'     => $pct_ajustes_cur,
            'value_fmt' => $pct_ajustes_cur . '%',
            'trend'     => $aj_trend_lbl,
            'trend_dir' => $aj_trend_dir,
            'detail'    => $kpi2_detail,
        ],
        [
            'key'       => 'mediana_aprovacao',
            'label'     => 'Mediana p/ Aprovação',
            'icon'      => 'fa-solid fa-hourglass-half',
            'tone'      => 'info',
            'value'     => $mediana_cur,
            'value_fmt' => fr_kpi_format_horas($mediana_cur),
            'trend'     => $med_trend_lbl,
            'trend_dir' => $med_trend_dir,
            'detail'    => $kpi3_detail,
        ],
    ],
], JSON_UNESCAPED_UNICODE);
