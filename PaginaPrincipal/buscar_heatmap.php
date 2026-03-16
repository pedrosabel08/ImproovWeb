<?php
require_once dirname(__DIR__) . '/config/session_bootstrap.php';
header('Content-Type: application/json');

include '../conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit();
}

$mes       = isset($_GET['mes'])         ? intval($_GET['mes'])        : (int) date('m');
$ano       = isset($_GET['ano'])         ? intval($_GET['ano'])        : (int) date('Y');
$funcao_id = isset($_GET['funcao_id'])   ? intval($_GET['funcao_id'])  : 0;
$tipo_img  = isset($_GET['tipo_imagem']) ? trim($_GET['tipo_imagem'])  : '';

if ($mes < 1 || $mes > 12 || $ano < 2020) {
    $mes = (int) date('m');
    $ano = (int) date('Y');
}

// JOIN base (reutilizado em todas as queries relevantes)
$joinBase = "
    JOIN funcao_imagem fi  ON fi.idfuncao_imagem = la.funcao_imagem_id
    JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
";

// WHERE base: mesmos filtros de exclusão usados em buscar_producao_funcao.php
$whereBase = "
    LOWER(TRIM(la.status_novo)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
    AND fi.colaborador_id NOT IN (21, 15)
    AND ico.obra_id != 74
    AND NOT (fi.funcao_id = 4 AND fi.colaborador_id IN (7, 34))
";

// Filtros opcionais
$extraWhere = '';
$extraTypes = '';
$extraVals  = [];

if ($funcao_id > 0) {
    $extraWhere .= ' AND fi.funcao_id = ?';
    $extraTypes .= 'i';
    $extraVals[] = $funcao_id;
}
if ($tipo_img !== '') {
    $extraWhere .= ' AND ico.tipo_imagem = ?';
    $extraTypes .= 's';
    $extraVals[] = $tipo_img;
}

// ── Query 1: tarefas por dia do mês selecionado ──────────────────────────────
$sqlDias = "
    SELECT DATE(la.data) AS dia,
           COUNT(DISTINCT fi.idfuncao_imagem) AS total
    FROM log_alteracoes la
    {$joinBase}
    WHERE YEAR(la.data) = ? AND MONTH(la.data) = ?
      AND {$whereBase}
      {$extraWhere}
    GROUP BY DATE(la.data)
    ORDER BY dia ASC
";

$typesQ1  = 'ii' . $extraTypes;
$paramsQ1 = array_merge([$ano, $mes], $extraVals);

$stmtD = $conn->prepare($sqlDias);
$stmtD->bind_param($typesQ1, ...$paramsQ1);
$stmtD->execute();
$rowsDias = $stmtD->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtD->close();

$porDia = [];
foreach ($rowsDias as $r) {
    $porDia[$r['dia']] = (int) $r['total'];
}

// ── Query 2: média histórica dos 6 meses anteriores ao mês selecionado ───────
// Período: do 1º dia, há 6 meses, até o último dia do mês anterior ao selecionado
$inicioHistorico = date('Y-m-d', mktime(0, 0, 0, $mes - 6, 1, $ano));
$fimHistorico    = date('Y-m-d', mktime(0, 0, 0, $mes, 0, $ano)); // dia 0 = último dia do mês anterior
$diasPeriodo     = max(1, (int) round((strtotime($fimHistorico) - strtotime($inicioHistorico)) / 86400) + 1);

$sqlMedia = "
    SELECT COUNT(DISTINCT fi.idfuncao_imagem) AS total_hist
    FROM log_alteracoes la
    {$joinBase}
    WHERE la.data >= ? AND la.data <= ?
      AND {$whereBase}
      {$extraWhere}
";

$typesQ2  = 'ss' . $extraTypes;
$paramsQ2 = array_merge([$inicioHistorico, $fimHistorico], $extraVals);

$stmtM = $conn->prepare($sqlMedia);
$stmtM->bind_param($typesQ2, ...$paramsQ2);
$stmtM->execute();
$totalHist = (int) ($stmtM->get_result()->fetch_assoc()['total_hist'] ?? 0);
$stmtM->close();

$mediaDiaria = round($totalHist / $diasPeriodo, 2);

// Thresholds para os 4 níveis de cor:
// level 0 = 0 tarefas (cinza)
// level 1 = 1..t1 (verde claro — abaixo/igual à média)
// level 2 = t1+1..t2 (verde médio — acima da média, até 2×)
// level 3 = >t2 (verde escuro — 2× acima da média)
$t1 = max(1, (int) floor($mediaDiaria));
$t2 = max($t1 + 1, (int) floor($mediaDiaria * 2));

// ── Query 3: funções disponíveis (para dropdown de filtro) ────────────────────
$sqlFuncoes = "
    SELECT DISTINCT f.idfuncao AS id, f.nome_funcao AS nome
    FROM funcao f
    JOIN funcao_imagem fi ON fi.funcao_id = f.idfuncao
    WHERE fi.colaborador_id NOT IN (21, 15)
    ORDER BY f.nome_funcao
";
$resFuncoes = $conn->query($sqlFuncoes);
$funcoes    = $resFuncoes ? $resFuncoes->fetch_all(MYSQLI_ASSOC) : [];

// ── Query 4: tipos de imagem disponíveis (para dropdown de filtro) ────────────
$sqlTipos = "
    SELECT DISTINCT tipo_imagem
    FROM imagens_cliente_obra
    WHERE tipo_imagem IS NOT NULL AND tipo_imagem <> ''
    ORDER BY tipo_imagem
";
$resTipos    = $conn->query($sqlTipos);
$tiposImagem = [];
if ($resTipos) {
    while ($row = $resTipos->fetch_assoc()) {
        $tiposImagem[] = $row['tipo_imagem'];
    }
}

$conn->close();

echo json_encode([
    'por_dia'      => $porDia,
    'media_diaria' => $mediaDiaria,
    't1'           => $t1,
    't2'           => $t2,
    'funcoes'      => $funcoes,
    'tipos_imagem' => $tiposImagem,
    'mes'          => $mes,
    'ano'          => $ano,
], JSON_UNESCAPED_UNICODE);
