<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../conexao.php';

$funcao_id = isset($_GET['funcao_id']) ? (int) $_GET['funcao_id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'all'; // 'all' | 'ph' | 'exclude_ph'
$source = isset($_GET['source']) ? $_GET['source'] : 'pagamento'; // 'pagamento' | 'logs'
$done_status = isset($_GET['done_status']) ? $_GET['done_status'] : '%finaliz%'; // used when source=logs (LIKE pattern)

if ($funcao_id <= 0) {
    echo json_encode(['error' => 'Parâmetro funcao_id é obrigatório']);
    exit;
}

$whereTipo = '';
if ($type === 'ph') {
    $whereTipo = "AND i.tipo_imagem LIKE 'Planta Humanizada%'";
} elseif ($type === 'exclude_ph') {
    $whereTipo = "AND i.tipo_imagem NOT LIKE 'Planta Humanizada%'";
}

$sql = '';
$stmt = null;
if ($source === 'logs') {
        // Contagem baseada no log de alterações (entradas onde status foi alterado para 'concluído' / 'finalizado')
        $sql = "SELECT YEAR(la.data) AS y, MONTH(la.data) AS m, COUNT(*) AS total_mes, DAY(LAST_DAY(MAX(la.data))) AS dias_mes
        FROM log_alteracoes la
        JOIN funcao_imagem fi ON la.funcao_imagem_id = fi.idfuncao_imagem
        JOIN imagens_cliente_obra i ON fi.imagem_id = i.idimagens_cliente_obra
        WHERE fi.funcao_id = ?
            AND la.data >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
            AND la.status_novo LIKE ?
            $whereTipo
        GROUP BY y, m
        ORDER BY y, m";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
                echo json_encode(['error' => 'Erro ao preparar query (logs)', 'debug' => $conn->error]);
                exit;
        }
        $stmt->bind_param('is', $funcao_id, $done_status);
        $stmt->execute();
        $res = $stmt->get_result();
} else {
        // Contagem baseada em data_pagamento (comportamento anterior)
        $sql = "SELECT YEAR(fi.data_pagamento) AS y, MONTH(fi.data_pagamento) AS m, COUNT(*) AS total_mes, DAY(LAST_DAY(MAX(fi.data_pagamento))) AS dias_mes
        FROM funcao_imagem fi
        JOIN imagens_cliente_obra i ON fi.imagem_id = i.idimagens_cliente_obra
        WHERE fi.funcao_id = ?
            AND fi.data_pagamento >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
            $whereTipo
        GROUP BY y, m
        ORDER BY y, m";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
                echo json_encode(['error' => 'Erro ao preparar query (pagamento)', 'debug' => $conn->error]);
                exit;
        }
        $stmt->bind_param('i', $funcao_id);
        $stmt->execute();
        $res = $stmt->get_result();
}

$months = [];
while ($row = $res->fetch_assoc()) {
    $total_mes = (int) $row['total_mes'];
    $dias_mes = (int) $row['dias_mes'] ?: 30;
    $daily = $dias_mes > 0 ? ($total_mes / $dias_mes) : 0;
    $months[] = [
        'year' => (int) $row['y'],
        'month' => (int) $row['m'],
        'total_mes' => $total_mes,
        'dias_mes' => $dias_mes,
        'daily_avg' => round($daily, 4)
    ];
}

$countMonths = count($months);
$avg_daily = 0.0;
if ($countMonths > 0) {
    $sum = 0.0;
    foreach ($months as $m)
        $sum += $m['daily_avg'];
    $avg_daily = $sum / $countMonths;
}

$weekly = $avg_daily * 5; // semana comercial de 5 dias
$projected_daily = $avg_daily * 1.2; // capacidade alvo +20%
$projected_weekly = $projected_daily * 5;
// Métricas alternativas para comparação e maior fidelidade
$sum_total_mes = 0;
$sum_dias_mes = 0;
foreach ($months as $m) {
    $sum_total_mes += $m['total_mes'];
    $sum_dias_mes += $m['dias_mes'];
}

$avg_month_total = $countMonths > 0 ? ($sum_total_mes / $countMonths) : 0.0; // média dos totais mensais
$avg_days_per_month = $countMonths > 0 ? ($sum_dias_mes / $countMonths) : 30; // dias médios por mês no conjunto

// Alternativa A: média diária calculada como AVG(total_mes) / avg_days_per_month
$alt_daily_via_avg_month = $avg_days_per_month > 0 ? ($avg_month_total / $avg_days_per_month) : 0.0;

// Alternativa B: total dos 3 meses dividido pelo total de dias reais desses meses
$alt_daily_via_total_over_period = $sum_dias_mes > 0 ? ($sum_total_mes / $sum_dias_mes) : 0.0;

// Mantemos também a média diária via meses (o que já tínhamos): $avg_daily

// Projeções (cada método)
$proj_via_avg_daily = $avg_daily * 1.2;
$proj_via_avg_month = $alt_daily_via_avg_month * 1.2;
$proj_via_total_period = $alt_daily_via_total_over_period * 1.2;

// Arredondamentos para exibição inteira (conforme solicitado)
$avg_daily_rounded = (int) round($avg_daily);
$weekly_rounded = (int) round($avg_daily_rounded * 5);
$projected_daily_rounded = (int) round($proj_via_avg_daily);
$projected_weekly_rounded = (int) round($projected_daily_rounded * 5);

$avg_month_total_rounded = (int) round($avg_month_total);
$alt_daily_via_avg_month_rounded = (int) round($alt_daily_via_avg_month);
$alt_daily_via_total_over_period_rounded = (int) round($alt_daily_via_total_over_period);

echo json_encode([
    'funcao_id' => $funcao_id,
    'type' => $type,
    'months' => $months,

    // Métricas originais detalhadas
    'avg_daily' => round($avg_daily, 4),
    'weekly' => round($weekly, 2),
    'projected_daily' => round($proj_via_avg_daily, 4),
    'projected_weekly' => round($projected_weekly, 2),

    // Métricas alternativas para comparação
    'count_months' => $countMonths,
    'sum_total_mes' => $sum_total_mes,
    'sum_dias_mes' => $sum_dias_mes,
    'avg_month_total' => round($avg_month_total, 4),
    'avg_days_per_month' => round($avg_days_per_month, 2),
    'alt_daily_via_avg_month' => round($alt_daily_via_avg_month, 4),
    'alt_daily_via_total_over_period' => round($alt_daily_via_total_over_period, 4),

    // Campos arredondados para exibição (inteiros)
    'avg_daily_rounded' => $avg_daily_rounded,
    'weekly_rounded' => $weekly_rounded,
    'projected_daily_rounded' => $projected_daily_rounded,
    'projected_weekly_rounded' => $projected_weekly_rounded,
    'avg_month_total_rounded' => $avg_month_total_rounded,
    'alt_daily_via_avg_month_rounded' => $alt_daily_via_avg_month_rounded,
    'alt_daily_via_total_over_period_rounded' => $alt_daily_via_total_over_period_rounded,
]);
