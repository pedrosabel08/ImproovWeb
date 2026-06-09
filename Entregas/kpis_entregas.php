<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';

function entregas_kpi_valid_date($value)
{
    if (!is_string($value) || $value === '') {
        return false;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt && $dt->format('Y-m-d') === $value;
}

function entregas_kpi_period()
{
    $today = new DateTimeImmutable('today');
    $from = isset($_GET['from']) ? trim((string) $_GET['from']) : '';
    $to = isset($_GET['to']) ? trim((string) $_GET['to']) : '';

    if (entregas_kpi_valid_date($from) && entregas_kpi_valid_date($to)) {
        $start = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($to);
        if ($start > $end) {
            $tmp = $start;
            $start = $end;
            $end = $tmp;
        }
    } else {
        $days = isset($_GET['days']) ? (int) $_GET['days'] : 7;
        if (!in_array($days, [7, 15, 30], true)) {
            $days = 7;
        }
        $end = $today;
        $start = $today->modify('-' . ($days - 1) . ' days');
    }

    $daysCount = $start->diff($end)->days + 1;
    $previousEnd = $start->modify('-1 day');
    $previousStart = $previousEnd->modify('-' . ($daysCount - 1) . ' days');

    return [
        'current' => [
            'from' => $start->format('Y-m-d'),
            'to' => $end->format('Y-m-d'),
            'start_at' => $start->format('Y-m-d') . ' 00:00:00',
            'end_at' => $end->format('Y-m-d') . ' 23:59:59',
        ],
        'previous' => [
            'from' => $previousStart->format('Y-m-d'),
            'to' => $previousEnd->format('Y-m-d'),
            'start_at' => $previousStart->format('Y-m-d') . ' 00:00:00',
            'end_at' => $previousEnd->format('Y-m-d') . ' 23:59:59',
        ],
        'days' => $daysCount,
    ];
}

function entregas_kpi_zero_summary()
{
    return [
        'total' => 0,
        'no_prazo' => 0,
        'com_atraso' => 0,
        'antecipadas' => 0,
    ];
}

function entregas_kpi_fetch_daily($conn, $startAt, $endAt)
{
    $sql = "
        SELECT
            DATE(e.data_conclusao) AS dia,
            COUNT(*) AS total,
            SUM(CASE WHEN e.status = 'Entregue no prazo' THEN 1 ELSE 0 END) AS no_prazo,
            SUM(CASE WHEN e.status = 'Entregue com atraso' THEN 1 ELSE 0 END) AS com_atraso,
            SUM(CASE WHEN e.status = 'Entrega antecipada' THEN 1 ELSE 0 END) AS antecipadas
        FROM entregas e
        WHERE e.data_conclusao BETWEEN ? AND ?
          AND e.status IN ('Entregue no prazo', 'Entregue com atraso', 'Entrega antecipada')
        GROUP BY DATE(e.data_conclusao)
        ORDER BY dia ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar consulta de KPIs.');
    }

    $stmt->bind_param('ss', $startAt, $endAt);
    $stmt->execute();
    $res = $stmt->get_result();
    $daily = [];

    while ($row = $res->fetch_assoc()) {
        $daily[(string) $row['dia']] = [
            'total' => (int) ($row['total'] ?? 0),
            'no_prazo' => (int) ($row['no_prazo'] ?? 0),
            'com_atraso' => (int) ($row['com_atraso'] ?? 0),
            'antecipadas' => (int) ($row['antecipadas'] ?? 0),
        ];
    }
    $stmt->close();

    return $daily;
}

function entregas_kpi_build_summary($daily)
{
    $summary = entregas_kpi_zero_summary();
    foreach ($daily as $row) {
        foreach ($summary as $key => $value) {
            $summary[$key] += (int) ($row[$key] ?? 0);
        }
    }
    return $summary;
}

function entregas_kpi_date_series($from, $days, $daily, $key)
{
    $series = [];
    $start = new DateTimeImmutable($from);
    for ($i = 0; $i < $days; $i++) {
        $date = $start->modify('+' . $i . ' days')->format('Y-m-d');
        $series[] = (float) ($daily[$date][$key] ?? 0);
    }
    return $series;
}

function entregas_kpi_sla_series($from, $days, $daily)
{
    $series = [];
    $start = new DateTimeImmutable($from);
    $total = 0;
    $positive = 0;

    for ($i = 0; $i < $days; $i++) {
        $date = $start->modify('+' . $i . ' days')->format('Y-m-d');
        $row = $daily[$date] ?? entregas_kpi_zero_summary();
        $total += (int) ($row['total'] ?? 0);
        $positive += (int) ($row['no_prazo'] ?? 0) + (int) ($row['antecipadas'] ?? 0);
        $series[] = $total > 0 ? round(($positive / $total) * 100, 1) : 0;
    }

    return $series;
}

function entregas_kpi_rate($summary)
{
    $total = (int) ($summary['total'] ?? 0);
    if ($total <= 0) {
        return 0.0;
    }
    $positive = (int) ($summary['no_prazo'] ?? 0) + (int) ($summary['antecipadas'] ?? 0);
    return round(($positive / $total) * 100, 1);
}

function entregas_kpi_percent_change($current, $previous)
{
    if ((float) $previous === 0.0) {
        return (float) $current === 0.0 ? 0.0 : 100.0;
    }
    return round((($current - $previous) / abs($previous)) * 100, 1);
}

function entregas_kpi_metric($current, $previous, $series, $inverse = false, $unit = 'count')
{
    $diff = round($current - $previous, 1);
    $change = $unit === 'percent'
        ? round($diff, 1)
        : entregas_kpi_percent_change($current, $previous);

    if ($diff == 0) {
        $trend = 'flat';
        $sentiment = 'neutral';
    } else {
        $trend = $diff > 0 ? 'up' : 'down';
        $isBetter = $inverse ? $diff < 0 : $diff > 0;
        $sentiment = $isBetter ? 'positive' : 'negative';
    }

    return [
        'current' => $current,
        'previous' => $previous,
        'diff' => $diff,
        'change' => $change,
        'unit' => $unit,
        'trend' => $trend,
        'sentiment' => $sentiment,
        'series' => $series,
    ];
}

try {
    $period = entregas_kpi_period();
    $currentDaily = entregas_kpi_fetch_daily($conn, $period['current']['start_at'], $period['current']['end_at']);
    $previousDaily = entregas_kpi_fetch_daily($conn, $period['previous']['start_at'], $period['previous']['end_at']);
    $current = entregas_kpi_build_summary($currentDaily);
    $previous = entregas_kpi_build_summary($previousDaily);
    $days = (int) $period['days'];

    echo json_encode([
        'success' => true,
        'period' => [
            'current' => [
                'from' => $period['current']['from'],
                'to' => $period['current']['to'],
            ],
            'previous' => [
                'from' => $period['previous']['from'],
                'to' => $period['previous']['to'],
            ],
        ],
        'metrics' => [
            'total' => entregas_kpi_metric(
                $current['total'],
                $previous['total'],
                entregas_kpi_date_series($period['current']['from'], $days, $currentDaily, 'total')
            ),
            'no_prazo' => entregas_kpi_metric(
                $current['no_prazo'],
                $previous['no_prazo'],
                entregas_kpi_date_series($period['current']['from'], $days, $currentDaily, 'no_prazo')
            ),
            'com_atraso' => entregas_kpi_metric(
                $current['com_atraso'],
                $previous['com_atraso'],
                entregas_kpi_date_series($period['current']['from'], $days, $currentDaily, 'com_atraso'),
                true
            ),
            'antecipadas' => entregas_kpi_metric(
                $current['antecipadas'],
                $previous['antecipadas'],
                entregas_kpi_date_series($period['current']['from'], $days, $currentDaily, 'antecipadas')
            ),
            'pontualidade' => entregas_kpi_metric(
                entregas_kpi_rate($current),
                entregas_kpi_rate($previous),
                entregas_kpi_sla_series($period['current']['from'], $days, $currentDaily),
                false,
                'percent'
            ),
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
