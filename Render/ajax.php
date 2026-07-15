<?php
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/aprovacao_interna_helper.php';
require_once __DIR__ . '/deadline_flow.php';
require_once __DIR__ . '/pos_referencias_helper.php';
require_once __DIR__ . '/render_ws_notify.php';
require_once __DIR__ . '/../Pos-Producao/ws_notify.php';
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['logado'])) {
    http_response_code(401);
    echo json_encode(['status' => 'erro', 'message' => 'Sessão expirada.']);
    exit;
}

function render_current_colaborador_id(): int
{
    return isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : 0;
}

function render_kpi_valid_date($value)
{
    if (!is_string($value) || $value === '') {
        return false;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt && $dt->format('Y-m-d') === $value;
}

function render_kpi_period()
{
    $today = new DateTimeImmutable('today');
    $from = isset($_GET['from']) ? trim((string) $_GET['from']) : '';
    $to = isset($_GET['to']) ? trim((string) $_GET['to']) : '';

    if (render_kpi_valid_date($from) && render_kpi_valid_date($to)) {
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

function render_kpi_scalar($conn, $sql, $types = '', ...$params)
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    if ($types !== '') {
        $bind = [$types];
        foreach ($params as $key => $value) {
            $bind[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return isset($row['total']) ? (int) $row['total'] : 0;
}

function render_kpi_fetch_daily($conn, $sql, $types, ...$params)
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar consulta de KPIs.');
    }

    $bind = [$types];
    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);

    $stmt->execute();
    $res = $stmt->get_result();
    $daily = [];
    while ($row = $res->fetch_assoc()) {
        $daily[(string) $row['dia']] = (int) ($row['total'] ?? 0);
    }
    $stmt->close();

    return $daily;
}

function render_kpi_date_series($from, $days, $daily)
{
    $series = [];
    $start = new DateTimeImmutable($from);
    for ($i = 0; $i < $days; $i++) {
        $date = $start->modify('+' . $i . ' days')->format('Y-m-d');
        $series[] = (float) ($daily[$date] ?? 0);
    }
    return $series;
}

function render_kpi_sum($daily)
{
    return array_sum(array_map('intval', $daily));
}

function render_kpi_daily_average($total, $days)
{
    $days = max(1, (int) $days);
    return round(((float) $total) / $days, 1);
}

function render_kpi_percent_change($current, $previous)
{
    if ((float) $previous === 0.0) {
        return (float) $current === 0.0 ? 0.0 : 100.0;
    }
    return round((($current - $previous) / abs($previous)) * 100, 1);
}

function render_kpi_metric($current, $previous, $series, $inverse = false, $unit = 'count')
{
    $diff = round($current - $previous, 1);
    $change = $unit === 'percent'
        ? round($diff, 1)
        : render_kpi_percent_change($current, $previous);

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

function render_kpi_approved_daily($conn, $startAt, $endAt)
{
    $sql = "
        SELECT DATE(event_date) AS dia, COUNT(*) AS total
        FROM (
            SELECT lr.render_id, lr.data AS event_date
            FROM log_render lr
            WHERE LOWER(TRIM(lr.status_novo)) = 'aprovado'
              AND lr.data BETWEEN ? AND ?
            UNION
            SELECT r.idrender_alta AS render_id, r.data AS event_date
            FROM render_alta r
            WHERE r.status = 'Aprovado'
              AND r.data BETWEEN ? AND ?
              AND NOT EXISTS (
                  SELECT 1
                  FROM log_render lr2
                  WHERE lr2.render_id = r.idrender_alta
                    AND LOWER(TRIM(lr2.status_novo)) = 'aprovado'
              )
        ) k
        GROUP BY DATE(event_date)
        ORDER BY dia ASC
    ";
    return render_kpi_fetch_daily($conn, $sql, 'ssss', $startAt, $endAt, $startAt, $endAt);
}

function render_kpi_status_daily($conn, $startAt, $endAt, $statuses)
{
    $lowerStatuses = array_map(static function ($status) {
        return strtolower($status);
    }, $statuses);
    $placeholders = implode(',', array_fill(0, count($lowerStatuses), '?'));
    $types = str_repeat('s', count($lowerStatuses)) . 'ss';
    $params = array_merge($lowerStatuses, [$startAt, $endAt]);

    $sql = "
        SELECT DATE(lr.data) AS dia, COUNT(DISTINCT lr.render_id) AS total
        FROM log_render lr
        WHERE LOWER(TRIM(lr.status_novo)) IN ($placeholders)
          AND lr.data BETWEEN ? AND ?
        GROUP BY DATE(lr.data)
        ORDER BY dia ASC
    ";
    return render_kpi_fetch_daily($conn, $sql, $types, ...$params);
}

function render_kpi_error_daily($conn, $startAt, $endAt)
{
    $sql = "
        SELECT DATE(event_date) AS dia, COUNT(*) AS total
        FROM (
            SELECT lr.render_id, lr.data AS event_date
            FROM log_render lr
            WHERE LOWER(TRIM(lr.status_novo)) = 'erro'
              AND lr.data BETWEEN ? AND ?
            UNION
            SELECT r.idrender_alta AS render_id, r.data AS event_date
            FROM render_alta r
            WHERE r.status = 'Erro'
              AND r.data BETWEEN ? AND ?
              AND NOT EXISTS (
                  SELECT 1
                  FROM log_render lr2
                  WHERE lr2.render_id = r.idrender_alta
                    AND LOWER(TRIM(lr2.status_novo)) = 'erro'
              )
        ) k
        GROUP BY DATE(event_date)
        ORDER BY dia ASC
    ";
    return render_kpi_fetch_daily($conn, $sql, 'ssss', $startAt, $endAt, $startAt, $endAt);
}

function render_kpi_sent_daily($conn, $startAt, $endAt)
{
    $sql = "
        SELECT DATE(r.submitted) AS dia, COUNT(*) AS total
        FROM render_alta r
        WHERE r.submitted BETWEEN ? AND ?
          AND r.status != 'Arquivado'
        GROUP BY DATE(r.submitted)
        ORDER BY dia ASC
    ";
    return render_kpi_fetch_daily($conn, $sql, 'ss', $startAt, $endAt);
}

function render_kpi_top_responsavel($conn, $startAt, $endAt, $previousStartAt, $previousEndAt)
{
    $sql = "
        SELECT
            r.responsavel_id,
            COALESCE(c.nome_colaborador, 'Sem responsavel') AS nome_colaborador,
            COUNT(*) AS total
        FROM (
            SELECT lr.render_id, lr.data AS event_date
            FROM log_render lr
            WHERE LOWER(TRIM(lr.status_novo)) = 'aprovado'
              AND lr.data BETWEEN ? AND ?
            UNION
            SELECT ra.idrender_alta AS render_id, ra.data AS event_date
            FROM render_alta ra
            WHERE ra.status = 'Aprovado'
              AND ra.data BETWEEN ? AND ?
              AND NOT EXISTS (
                  SELECT 1
                  FROM log_render lr2
                  WHERE lr2.render_id = ra.idrender_alta
                    AND LOWER(TRIM(lr2.status_novo)) = 'aprovado'
              )
        ) k
        INNER JOIN render_alta r ON r.idrender_alta = k.render_id
        LEFT JOIN colaborador c ON r.responsavel_id = c.idcolaborador
        GROUP BY r.responsavel_id, c.nome_colaborador
        ORDER BY total DESC, nome_colaborador ASC
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['nome_colaborador' => 'Sem dados', 'total' => 0, 'previous' => 0, 'change' => 0, 'sentiment' => 'neutral'];
    }
    $stmt->bind_param('ssss', $startAt, $endAt, $startAt, $endAt);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['nome_colaborador' => 'Sem dados', 'total' => 0, 'previous' => 0, 'change' => 0, 'sentiment' => 'neutral'];
    }

    $responsavelId = $row['responsavel_id'];
    $previousSql = "
        SELECT COUNT(*) AS total
        FROM (
            SELECT lr.render_id, lr.data AS event_date
            FROM log_render lr
            WHERE LOWER(TRIM(lr.status_novo)) = 'aprovado'
              AND lr.data BETWEEN ? AND ?
            UNION
            SELECT ra.idrender_alta AS render_id, ra.data AS event_date
            FROM render_alta ra
            WHERE ra.status = 'Aprovado'
              AND ra.data BETWEEN ? AND ?
              AND NOT EXISTS (
                  SELECT 1
                  FROM log_render lr2
                  WHERE lr2.render_id = ra.idrender_alta
                    AND LOWER(TRIM(lr2.status_novo)) = 'aprovado'
              )
        ) k
        INNER JOIN render_alta r ON r.idrender_alta = k.render_id
        WHERE " . ($responsavelId === null ? 'r.responsavel_id IS NULL' : 'r.responsavel_id = ?') . "
    ";

    if ($responsavelId === null) {
        $previous = render_kpi_scalar($conn, $previousSql, 'ssss', $previousStartAt, $previousEndAt, $previousStartAt, $previousEndAt);
    } else {
        $previous = render_kpi_scalar($conn, $previousSql, 'ssssi', $previousStartAt, $previousEndAt, $previousStartAt, $previousEndAt, (int) $responsavelId);
    }

    $total = (int) ($row['total'] ?? 0);
    $change = render_kpi_percent_change($total, $previous);
    $sentiment = $total === $previous ? 'neutral' : ($total > $previous ? 'positive' : 'negative');

    return [
        'nome_colaborador' => $row['nome_colaborador'],
        'total' => $total,
        'previous' => $previous,
        'change' => $change,
        'diff' => $total - $previous,
        'sentiment' => $sentiment,
    ];
}

// Lidar com as ações de AJAX
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'getKpis':
            $period = render_kpi_period();
            $current = $period['current'];
            $previous = $period['previous'];
            $days = (int) $period['days'];

            $approvedDaily = render_kpi_approved_daily($conn, $current['start_at'], $current['end_at']);
            $approvedPreviousDaily = render_kpi_approved_daily($conn, $previous['start_at'], $previous['end_at']);
            $reworkDaily = render_kpi_status_daily($conn, $current['start_at'], $current['end_at'], ['Reprovado', 'Refazendo']);
            $reworkPreviousDaily = render_kpi_status_daily($conn, $previous['start_at'], $previous['end_at'], ['Reprovado', 'Refazendo']);
            $errorDaily = render_kpi_error_daily($conn, $current['start_at'], $current['end_at']);
            $errorPreviousDaily = render_kpi_error_daily($conn, $previous['start_at'], $previous['end_at']);
            $sentDaily = render_kpi_sent_daily($conn, $current['start_at'], $current['end_at']);
            $sentPreviousDaily = render_kpi_sent_daily($conn, $previous['start_at'], $previous['end_at']);

            $approved = render_kpi_sum($approvedDaily);
            $approvedPrevious = render_kpi_sum($approvedPreviousDaily);
            $sent = render_kpi_sum($sentDaily);
            $sentPrevious = render_kpi_sum($sentPreviousDaily);

            echo json_encode([
                'status' => 'sucesso',
                'period' => [
                    'current' => [
                        'from' => $current['from'],
                        'to' => $current['to'],
                    ],
                    'previous' => [
                        'from' => $previous['from'],
                        'to' => $previous['to'],
                    ],
                ],
                'metrics' => [
                    'aprovados' => render_kpi_metric(
                        $approved,
                        $approvedPrevious,
                        render_kpi_date_series($current['from'], $days, $approvedDaily)
                    ),
                    'retrabalho' => render_kpi_metric(
                        render_kpi_sum($reworkDaily),
                        render_kpi_sum($reworkPreviousDaily),
                        render_kpi_date_series($current['from'], $days, $reworkDaily),
                        true
                    ),
                    'erros' => render_kpi_metric(
                        render_kpi_sum($errorDaily),
                        render_kpi_sum($errorPreviousDaily),
                        render_kpi_date_series($current['from'], $days, $errorDaily),
                        true
                    ),
                    'media_diaria' => render_kpi_metric(
                        render_kpi_daily_average($sent, $days),
                        render_kpi_daily_average($sentPrevious, $days),
                        render_kpi_date_series($current['from'], $days, $sentDaily)
                    ),
                ],
                'highlight' => [
                    'top_responsavel' => render_kpi_top_responsavel(
                        $conn,
                        $current['start_at'],
                        $current['end_at'],
                        $previous['start_at'],
                        $previous['end_at']
                    ),
                ],
            ]);
            break;

        case 'getRenders':
            // Buscar renders com paginação
            $page  = max(1, (int)($_GET['page']  ?? 1));
            $limit = max(1, min(200, (int)($_GET['limit'] ?? 100)));
            $offset = ($page - 1) * $limit;

            $sqlCount = "SELECT COUNT(*) AS total FROM render_alta r WHERE r.status != 'Arquivado'";
            $resCount = $conn->query($sqlCount);
            $total = $resCount ? (int)$resCount->fetch_assoc()['total'] : 0;

            $sql = "SELECT 
    c.nome_colaborador, 
    s.nome_status, 
    i.imagem_nome,
    o.nome_obra,
    o.nomenclatura AS obra_nomenclatura,
    r.*
FROM 
    render_alta r
LEFT JOIN 
    imagens_cliente_obra i ON r.imagem_id = i.idimagens_cliente_obra
LEFT JOIN 
    colaborador c ON r.responsavel_id = c.idcolaborador
LEFT JOIN 
    status_imagem s ON r.status_id = s.idstatus
LEFT JOIN
    obra o ON i.obra_id = o.idobra
WHERE 
    r.status != 'Arquivado'
ORDER BY 
    FIELD(r.status, 'Em aprovação', 'Em andamento', 'Refazendo', 'Reprovado', 'Erro', 'Aprovado'), data DESC
LIMIT $limit OFFSET $offset";
            $result = $conn->query($sql);
            $renders = [];

            while ($row = $result->fetch_assoc()) {
                $renders[] = $row;
            }

            echo json_encode(['status' => 'sucesso', 'renders' => $renders, 'total' => $total, 'page' => $page, 'limit' => $limit]);
            break;

        case 'getRender':
            // Buscar um render específico
            if (isset($_GET['idrender_alta'])) {
                $idrender_alta = $_GET['idrender_alta'];
                $sql = "SELECT r.*, i.imagem_nome, c.nome_colaborador, s.nome_status  FROM render_alta r
                 join imagens_cliente_obra i on r.imagem_id = i.idimagens_cliente_obra 
                 join colaborador c on r.responsavel_id = c.idcolaborador
                 join status_imagem s on r.status_id = s.idstatus
                 WHERE idrender_alta = $idrender_alta";
                $result = $conn->query($sql);
                $render = $result->fetch_assoc();

                // Buscar previews associados ao render (se houver) e incluí-los na resposta
                $previews = [];
                $stmtPre = $conn->prepare("SELECT filename, uploaded_at FROM render_previews WHERE render_id = ? ORDER BY uploaded_at ASC, id ASC");
                if ($stmtPre) {
                    $stmtPre->bind_param('i', $idrender_alta);
                    $stmtPre->execute();
                    $resPre = $stmtPre->get_result();
                    while ($rowPre = $resPre->fetch_assoc()) {
                        $previews[] = $rowPre;
                    }
                    $stmtPre->close();
                }

                echo json_encode(['status' => 'sucesso', 'render' => $render, 'previews' => $previews]);
            }
            break;

        case 'getColaboradores':
            $res = $conn->query("SELECT idcolaborador, nome_colaborador FROM colaborador WHERE ativo = 1 ORDER BY nome_colaborador");
            $colaboradores = [];
            while ($row = $res->fetch_assoc()) {
                $colaboradores[] = $row;
            }
            echo json_encode(['status' => 'sucesso', 'colaboradores' => $colaboradores]);
            break;

        case 'getRenderTimeline':
            if (isset($_GET['render_id'])) {
                $render_id = (int)$_GET['render_id'];

                // 1. Fetch logs from log_render ordered chronologically
                $logs = [];
                $stmtLogs = $conn->prepare(
                    "SELECT id, status_anterior, status_novo, data
                     FROM log_render
                     WHERE render_id = ?
                     ORDER BY data ASC, id ASC"
                );
                if ($stmtLogs) {
                    $stmtLogs->bind_param('i', $render_id);
                    $stmtLogs->execute();
                    $res = $stmtLogs->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $logs[] = $row;
                    }
                    $stmtLogs->close();
                }

                // 2. Fetch render metadata for anchor/fallback
                $render = null;
                $stmtR = $conn->prepare(
                    "SELECT submitted, last_updated, data, status
                     FROM render_alta
                     WHERE idrender_alta = ? LIMIT 1"
                );
                if ($stmtR) {
                    $stmtR->bind_param('i', $render_id);
                    $stmtR->execute();
                    $render = $stmtR->get_result()->fetch_assoc();
                    $stmtR->close();
                }

                // 3. Build ordered timeline
                $timeline = [];

                // Always start with "Enviado" anchor using render_alta.submitted
                $startDate = null;
                if ($render) {
                    $startDate = !empty($render['submitted']) ? $render['submitted']
                        : (!empty($render['data'])      ? $render['data'] : null);
                }

                if ($startDate) {
                    $timeline[] = [
                        'status_anterior' => null,
                        'status_novo'     => 'Enviado',
                        'data'            => $startDate,
                        'source'          => 'fallback',
                        'is_start'        => true,
                    ];
                }

                if (!empty($logs)) {
                    // Primary source: log_render entries
                    foreach ($logs as $log) {
                        $timeline[] = [
                            'status_anterior' => $log['status_anterior'],
                            'status_novo'     => $log['status_novo'],
                            'data'            => $log['data'],
                            'source'          => 'log',
                            'is_start'        => false,
                        ];
                    }
                } else {
                    // Fallback: show current status from render_alta when no logs exist
                    if ($render) {
                        $fallbackDate   = !empty($render['last_updated']) ? $render['last_updated']
                            : (!empty($render['data'])         ? $render['data'] : null);
                        $currentStatus  = $render['status'] ?? null;

                        // Only add if the date differs from the start anchor (avoid duplicate)
                        if ($currentStatus && $fallbackDate && $fallbackDate !== $startDate) {
                            $timeline[] = [
                                'status_anterior' => null,
                                'status_novo'     => $currentStatus,
                                'data'            => $fallbackDate,
                                'source'          => 'fallback',
                                'is_start'        => false,
                            ];
                        }
                    }
                }

                // 4. Sort chronologically (datetime strings are lexicographically sortable)
                usort($timeline, function ($a, $b) {
                    return strcmp($a['data'] ?? '', $b['data'] ?? '');
                });

                echo json_encode(['status' => 'sucesso', 'timeline' => $timeline]);
            }
            break;
    }
}

if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'approveToPos':
            $renderId = isset($_POST['idrender_alta']) ? (int) $_POST['idrender_alta'] : 0;
            $colaboradorId = render_current_colaborador_id();
            if ($renderId <= 0 || $colaboradorId <= 0) {
                echo json_encode(['status' => 'erro', 'message' => 'Dados de aprovação inválidos.']);
                break;
            }

            $refs = trim((string) ($_POST['refs'] ?? ''));
            $obs = trim((string) ($_POST['obs'] ?? ''));
            $savedFiles = [];
            try {
                pos_referencias_ensure_schema($conn);
                pos_referencias_ensure_annotations_schema($conn);
                aprovacao_interna_ensure_schema($conn);
                $conn->begin_transaction();
                $stmt = $conn->prepare("SELECT r.idrender_alta, r.imagem_id, r.status_id, r.responsavel_id, r.previa_jpg,
                    i.obra_id, s.nome_status, p.idpos_producao
                    FROM render_alta r
                    JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = r.imagem_id
                    JOIN status_imagem s ON s.idstatus = r.status_id
                    LEFT JOIN pos_producao p ON p.render_id = r.idrender_alta
                    WHERE r.idrender_alta = ? FOR UPDATE");
                $stmt->bind_param('i', $renderId);
                $stmt->execute();
                $render = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$render) throw new RuntimeException('Render não encontrado.');
                if (mb_strtolower(trim((string) $render['nome_status']), 'UTF-8') === 'p00') {
                    throw new RuntimeException('P00 mantém o fluxo de aprovação e Follow-up atual.');
                }

                $alteracao = aprovacao_interna_resolver_alteracao_por_render($conn, $renderId);
                if ($alteracao && !aprovacao_interna_tem_registro($conn, (int) $alteracao['funcao_imagem_id'], (int) $alteracao['status_id'])) {
                    $origin = strtolower(trim((string) ($_POST['approval_origin'] ?? '')));
                    if (!in_array($origin, ['presencial', 'whatsapp'], true)) {
                        $conn->rollback();
                        echo json_encode(['status' => 'aprovacao_interna_pendente', 'message' => 'A aprovação interna precisa ser registrada antes do envio à Pós.']);
                        break;
                    }
                    if (!aprovacao_interna_registrar($conn, (int) $alteracao['funcao_imagem_id'], (int) $alteracao['imagem_id'], (int) $alteracao['status_id'], $origin, $colaboradorId, $renderId, null, $obs ?: null)) {
                        throw new RuntimeException('Não foi possível registrar a aprovação interna.');
                    }
                }

                $posId = (int) ($render['idpos_producao'] ?? 0);
                if ($posId <= 0) {
                    $responsavel = (int) ($render['responsavel_id'] ?: $colaboradorId);
                    $insertPos = $conn->prepare("INSERT INTO pos_producao
                        (render_id, imagem_id, obra_id, colaborador_id, status_id, responsavel_id, refs, obs, data_pos)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $insertPos->bind_param('iiiiiiss', $renderId, $render['imagem_id'], $render['obra_id'], $responsavel, $render['status_id'], $responsavel, $refs, $obs);
                    if (!$insertPos->execute()) throw new RuntimeException('Não foi possível criar a Pós-Produção.');
                    $posId = (int) $conn->insert_id;
                    $insertPos->close();
                } else {
                    $updatePos = $conn->prepare('UPDATE pos_producao SET refs = ?, obs = ?, data_pos = NOW() WHERE idpos_producao = ?');
                    $updatePos->bind_param('ssi', $refs, $obs, $posId);
                    if (!$updatePos->execute()) throw new RuntimeException('Não foi possível atualizar a Pós-Produção.');
                    $updatePos->close();
                }

                $primaryReferenceId = pos_referencias_ensure_render_principal($conn, $posId, $renderId, (string)($render['previa_jpg'] ?? ''), $colaboradorId);
                $savedFiles = pos_referencias_insert_uploads($conn, $posId, $colaboradorId, $_FILES['references'] ?? []);
                $referenceMap = ['main' => $primaryReferenceId];
                foreach ($savedFiles as $savedFile) {
                    if (!empty($savedFile['reference_id'])) $referenceMap['upload_' . (int)$savedFile['input_index']] = (int)$savedFile['reference_id'];
                }
                $drafts = json_decode((string)($_POST['reference_review_drafts'] ?? '{}'), true);
                if (is_array($drafts)) {
                    foreach ($drafts as $draftKey => $annotations) {
                        $referenceId = $referenceMap[$draftKey] ?? 0;
                        if ($referenceId <= 0 || !is_array($annotations)) continue;
                        foreach ($annotations as $annotation) {
                            if (!is_array($annotation)) continue;
                            pos_referencias_annotation_create(
                                $conn,
                                $referenceId,
                                $colaboradorId,
                                trim((string)($annotation['texto'] ?? '')),
                                (string)($annotation['tipo'] ?? 'freehand'),
                                isset($annotation['x']) ? (float)$annotation['x'] : null,
                                isset($annotation['y']) ? (float)$annotation['y'] : null,
                                isset($annotation['path_data']) ? json_encode($annotation['path_data'], JSON_UNESCAPED_UNICODE) : null,
                                (string)($annotation['cor'] ?? '#f59e0b'),
                                (int)($annotation['espessura'] ?? 2),
                                array_key_exists('possui_desenho', $annotation) ? (bool)$annotation['possui_desenho'] : null
                            );
                        }
                    }
                }
                $updateRender = $conn->prepare("UPDATE render_alta SET status = 'Aprovado', data = NOW() WHERE idrender_alta = ?");
                $updateRender->bind_param('i', $renderId);
                if (!$updateRender->execute()) throw new RuntimeException('Não foi possível aprovar o render.');
                $updateRender->close();
                $deadlineFlowResult = deadline_flow_approve_locked($conn, $renderId);
                $conn->commit();

                notifyRenderUpdate('render.approved_to_pos', ['render_id' => $renderId, 'imagem_id' => (int) $render['imagem_id'], 'pos_producao_id' => $posId]);
                if (function_exists('notifyPosProducaoUpdate')) notifyPosProducaoUpdate('references_changed', ['render_id' => $renderId, 'pos_producao_id' => $posId]);
                echo json_encode(['status' => 'sucesso', 'render_id' => $renderId, 'pos_producao_id' => $posId, 'references' => pos_referencias_list($conn, $posId), 'deadline_command_created' => $deadlineFlowResult['command']['created'] ?? false]);
            } catch (Throwable $e) {
                $conn->rollback();
                pos_referencias_cleanup_uploaded_files($savedFiles);
                echo json_encode(['status' => 'erro', 'message' => $e->getMessage()]);
            }
            break;

        case 'getPosReferences':
            $renderId = isset($_GET['render_id']) ? (int) $_GET['render_id'] : 0;
            pos_referencias_ensure_schema($conn);
            $stmt = $conn->prepare('SELECT idpos_producao FROM pos_producao WHERE render_id = ? LIMIT 1');
            $stmt->bind_param('i', $renderId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            echo json_encode(['status' => 'sucesso', 'references' => $row ? pos_referencias_list($conn, (int) $row['idpos_producao']) : []]);
            break;

        case 'getReferenceReview':
            $renderId = isset($_POST['render_id']) ? (int)$_POST['render_id'] : (isset($_GET['render_id']) ? (int)$_GET['render_id'] : 0);
            $colaboradorId = render_current_colaborador_id();
            if ($renderId <= 0 || $colaboradorId <= 0) {
                echo json_encode(['status' => 'erro', 'message' => 'Render inválido.']);
                break;
            }
            pos_referencias_ensure_schema($conn);
            $stmt = $conn->prepare("SELECT r.idrender_alta, r.previa_jpg, p.idpos_producao
                FROM render_alta r LEFT JOIN pos_producao p ON p.render_id = r.idrender_alta
                WHERE r.idrender_alta = ? LIMIT 1");
            $stmt->bind_param('i', $renderId);
            $stmt->execute();
            $review = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$review) {
                echo json_encode(['status' => 'erro', 'message' => 'Render não encontrado.']);
                break;
            }
            $posId = (int)($review['idpos_producao'] ?? 0);
            $primaryId = null;
            $references = [];
            if ($posId > 0) {
                $primaryId = pos_referencias_ensure_render_principal($conn, $posId, $renderId, (string)($review['previa_jpg'] ?? ''), $colaboradorId);
                $references = pos_referencias_list($conn, $posId);
            }
            echo json_encode([
                'status' => 'sucesso',
                'render_id' => $renderId,
                'pos_producao_id' => $posId,
                'main_reference_id' => $primaryId,
                'main_preview' => (string)($review['previa_jpg'] ?? ''),
                'references' => $references,
            ]);
            break;

        case 'addReferenceFiles':
            $renderId = isset($_POST['render_id']) ? (int)$_POST['render_id'] : 0;
            $colaboradorId = render_current_colaborador_id();
            $stmt = $conn->prepare('SELECT idpos_producao FROM pos_producao WHERE render_id = ? LIMIT 1');
            $stmt->bind_param('i', $renderId);
            $stmt->execute();
            $pos = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$pos || $colaboradorId <= 0) {
                echo json_encode(['status' => 'erro', 'message' => 'A Pós-Produção ainda não está disponível para esta referência.']);
                break;
            }
            pos_referencias_ensure_schema($conn);
            pos_referencias_ensure_annotations_schema($conn);
            $saved = [];
            try {
                $conn->begin_transaction();
                $saved = pos_referencias_insert_uploads($conn, (int)$pos['idpos_producao'], $colaboradorId, $_FILES['references'] ?? []);
                $referenceMap = [];
                foreach ($saved as $savedFile) {
                    if (!empty($savedFile['reference_id'])) $referenceMap['upload_' . (int)$savedFile['input_index']] = (int)$savedFile['reference_id'];
                }
                $drafts = json_decode((string)($_POST['reference_review_drafts'] ?? '{}'), true);
                if (is_array($drafts)) {
                    foreach ($drafts as $draftKey => $annotations) {
                        $referenceId = $referenceMap[$draftKey] ?? 0;
                        if ($referenceId <= 0 || !is_array($annotations)) continue;
                        foreach ($annotations as $annotation) {
                            if (!is_array($annotation)) continue;
                            pos_referencias_annotation_create(
                                $conn,
                                $referenceId,
                                $colaboradorId,
                                trim((string)($annotation['texto'] ?? '')),
                                (string)($annotation['tipo'] ?? 'freehand'),
                                isset($annotation['x']) ? (float)$annotation['x'] : null,
                                isset($annotation['y']) ? (float)$annotation['y'] : null,
                                isset($annotation['path_data']) ? json_encode($annotation['path_data'], JSON_UNESCAPED_UNICODE) : null,
                                (string)($annotation['cor'] ?? '#f59e0b'),
                                (int)($annotation['espessura'] ?? 2),
                                array_key_exists('possui_desenho', $annotation) ? (bool)$annotation['possui_desenho'] : null
                            );
                        }
                    }
                }
                $conn->commit();
                notifyPosProducaoUpdate('references_changed', ['render_id' => $renderId, 'pos_producao_id' => (int)$pos['idpos_producao']]);
                echo json_encode(['status' => 'sucesso', 'saved_count' => count($saved), 'references' => pos_referencias_list($conn, (int)$pos['idpos_producao'])]);
            } catch (Throwable $e) {
                $conn->rollback();
                pos_referencias_cleanup_uploaded_files($saved);
                echo json_encode(['status' => 'erro', 'message' => $e->getMessage()]);
            }
            break;

        case 'removeReference':
            $referenceId = isset($_POST['reference_id']) ? (int)$_POST['reference_id'] : 0;
            $colaboradorId = render_current_colaborador_id();
            if ($referenceId <= 0 || $colaboradorId <= 0 || !pos_referencias_remove($conn, $referenceId, $colaboradorId)) {
                echo json_encode(['status' => 'erro', 'message' => 'A referência não pode ser removida.']);
                break;
            }
            echo json_encode(['status' => 'sucesso']);
            break;

        case 'updateRender':
            // Atualizar o render
            if (isset($_POST['idrender_alta']) && isset($_POST['status'])) {
                $idrender_alta = $_POST['idrender_alta'];
                $status = $_POST['status'];
                $logs = [];
                $debug = isset($_POST['debug']) && (string)$_POST['debug'] === '1';
                $logs[] = "updateRender: idrender_alta={$idrender_alta}, status={$status}";
                $manualApprovalData = null;
                $transactionStarted = false;
                $deadlineFlowResult = null;

                if (strtolower($status) === 'aprovado') {
                    aprovacao_interna_ensure_schema($conn);
                    $alteracaoAprovacao = aprovacao_interna_resolver_alteracao_por_render($conn, (int)$idrender_alta);

                    if ($alteracaoAprovacao) {
                        $logs[] = 'aprovacao_interna.alteracao_detectada=' . $alteracaoAprovacao['funcao_imagem_id'];
                        $temAprovacaoInterna = aprovacao_interna_tem_registro(
                            $conn,
                            (int)$alteracaoAprovacao['funcao_imagem_id'],
                            (int)$alteracaoAprovacao['status_id']
                        );

                        if (!$temAprovacaoInterna) {
                            $approvalOrigin = isset($_POST['approval_origin'])
                                ? strtolower(trim((string)$_POST['approval_origin']))
                                : '';

                            if (!in_array($approvalOrigin, ['presencial', 'whatsapp'], true)) {
                                $resp = [
                                    'status' => 'aprovacao_interna_pendente',
                                    'message' => 'A alteração desta imagem não possui aprovação interna registrada.',
                                    'question' => 'A alteração foi aprovada?',
                                ];
                                if ($debug) $resp['logs'] = $logs;
                                echo json_encode($resp);
                                break;
                            }

                            $registradoPor = isset($_SESSION['idcolaborador']) ? (int)$_SESSION['idcolaborador'] : 0;
                            if ($registradoPor <= 0) {
                                $resp = ['status' => 'erro', 'message' => 'Usuário sem colaborador vinculado para registrar a aprovação interna.'];
                                if ($debug) $resp['logs'] = $logs;
                                echo json_encode($resp);
                                break;
                            }

                            $manualApprovalData = [
                                'funcao_imagem_id' => (int)$alteracaoAprovacao['funcao_imagem_id'],
                                'imagem_id' => (int)$alteracaoAprovacao['imagem_id'],
                                'status_id' => (int)$alteracaoAprovacao['status_id'],
                                'origem' => $approvalOrigin,
                                'registrado_por' => $registradoPor,
                                'render_id' => (int)$idrender_alta,
                            ];
                        }
                    } else {
                        $logs[] = 'aprovacao_interna.sem_funcao_alteracao';
                    }
                }

                if (in_array(strtolower($status), ['reprovado', 'refazendo'], true)) {
                    try {
                        $conn->begin_transaction();
                        $deadlineFlowResult = deadline_flow_rework_locked(
                            $conn,
                            (int) $idrender_alta,
                            $status
                        );
                        $conn->commit();

                        $hasJob = !empty($deadlineFlowResult['deadline_job_id']);
                        $message = $hasJob
                            ? 'Render reprovado. A remocao do job no Deadline esta pendente.'
                            : 'Render reprovado. Nao havia job do Deadline vinculado.';
                        echo json_encode([
                            'status' => 'sucesso',
                            'success' => true,
                            'render_id' => (int) $idrender_alta,
                            'tentativa_encerrada_id' => $deadlineFlowResult['tentativa_encerrada_id'],
                            'nova_tentativa_id' => $deadlineFlowResult['nova_tentativa_id'],
                            'deadline_command_created' => $deadlineFlowResult['deadline_command_created'],
                            'deadline_command_status' => $deadlineFlowResult['deadline_command_status'],
                            'message' => $message,
                        ]);
                        notifyRenderUpdate('render.status_changed', ['render_id' => (int) $idrender_alta, 'status' => $status]);
                    } catch (Throwable $e) {
                        $conn->rollback();
                        $logs[] = 'deadline_flow.rework_error=' . $e->getMessage();
                        $resp = ['status' => 'erro', 'success' => false, 'message' => 'Erro ao registrar a reprovacao e a fila Deadline.'];
                        if ($debug) $resp['logs'] = $logs;
                        echo json_encode($resp);
                    }
                    break;
                }

                if (!$transactionStarted) {
                    $conn->begin_transaction();
                    $transactionStarted = true;
                }

                if ($manualApprovalData) {
                    $approvalOk = aprovacao_interna_registrar(
                        $conn,
                        $manualApprovalData['funcao_imagem_id'],
                        $manualApprovalData['imagem_id'],
                        $manualApprovalData['status_id'],
                        $manualApprovalData['origem'],
                        $manualApprovalData['registrado_por'],
                        $manualApprovalData['render_id'],
                        null,
                        null
                    );

                    if (!$approvalOk) {
                        $conn->rollback();
                        $transactionStarted = false;
                        $resp = ['status' => 'erro', 'message' => 'Erro ao registrar aprovação interna.'];
                        if ($debug) $resp['logs'] = $logs;
                        echo json_encode($resp);
                        break;
                    }

                    $logs[] = 'aprovacao_interna.manual_registrada=' . $manualApprovalData['origem'];
                }

                $stmtUpd = $conn->prepare("UPDATE render_alta SET status = ?, data = NOW() WHERE idrender_alta = ?");
                if (!$stmtUpd) {
                    if ($transactionStarted) {
                        $conn->rollback();
                    }
                    $logs[] = 'Erro prepare update: ' . $conn->error;
                    echo json_encode(['status' => 'erro', 'message' => 'Erro ao atualizar o render', 'logs' => $debug ? $logs : null]);
                    break;
                }
                $stmtUpd->bind_param('si', $status, $idrender_alta);
                $okUpd = $stmtUpd->execute();
                $stmtUpd->close();

                if ($okUpd === TRUE) {
                    if (strtolower($status) === 'aprovado') {
                        try {
                            $deadlineFlowResult = deadline_flow_approve_locked($conn, (int) $idrender_alta);
                        } catch (Throwable $e) {
                            if ($transactionStarted) {
                                $conn->rollback();
                                $transactionStarted = false;
                            }
                            $logs[] = 'deadline_flow.approval_error=' . $e->getMessage();
                            echo json_encode([
                                'status' => 'erro',
                                'success' => false,
                                'message' => 'Erro ao registrar a aprovacao e a fila Deadline.',
                                'logs' => $debug ? $logs : null,
                            ]);
                            break;
                        }
                    }
                    if ($transactionStarted) {
                        $conn->commit();
                        $transactionStarted = false;
                    }

                    // Ao reprovar/refazer, zerar status_pos em pos_producao
                    if (in_array(strtolower($status), ['reprovado', 'refazendo'])) {
                        $stmtPos = $conn->prepare("UPDATE pos_producao SET status_pos = 1 WHERE render_id = ?");
                        if ($stmtPos) {
                            $stmtPos->bind_param('i', $idrender_alta);
                            $stmtPos->execute();
                            $logs[] = 'pos_producao.status_pos resetado para 1';
                            $stmtPos->close();
                        } else {
                            $logs[] = 'Erro prepare pos_producao reset: ' . $conn->error;
                        }
                    }

                    // Se o novo status for 'Aprovado', preparar os ângulos para follow-up
                    if (strtolower($status) === 'aprovado') {

                        $stmtPos = $conn->prepare("UPDATE pos_producao SET data_pos = NOW() WHERE render_id = ?");
                        if ($stmtPos) {
                            $stmtPos->bind_param('i', $idrender_alta);
                            $stmtPos->execute();
                            $logs[] = 'pos_producao.data_pos resetado para NOW()';
                            $stmtPos->close();
                        } else {
                            $logs[] = 'Erro prepare pos_producao reset: ' . $conn->error;
                        }


                        // Criar tabela followup_angles se não existir
                        $createSql = "CREATE TABLE IF NOT EXISTS followup_angles (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            render_id INT NOT NULL,
                            imagem_id INT DEFAULT NULL,
                            filename VARCHAR(255) NOT NULL,
                            uploaded_at DATETIME DEFAULT NULL,
                            status ENUM('pendente','escolhido','em_producao') DEFAULT 'pendente',
                            UNIQUE KEY uniq_render_file (render_id, filename)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
                        if ($conn->query($createSql) === TRUE) {
                            $logs[] = 'followup_angles: ok (CREATE TABLE IF NOT EXISTS)';
                        } else {
                            $logs[] = 'followup_angles: erro ao criar/validar tabela: ' . $conn->error;
                        }

                        // Buscar previews associados ao render e inserir na tabela followup_angles
                        $stmtPre = $conn->prepare("SELECT filename, uploaded_at FROM render_previews WHERE render_id = ?");
                        if ($stmtPre) {
                            $stmtPre->bind_param('i', $idrender_alta);
                            $stmtPre->execute();
                            $resPre = $stmtPre->get_result();

                            // Obter imagem pai (imagem_id) do render_alta, se existir
                            $imagem_id = null;
                            $stmtImg = $conn->prepare("SELECT imagem_id FROM render_alta WHERE idrender_alta = ? LIMIT 1");
                            if ($stmtImg) {
                                $stmtImg->bind_param('i', $idrender_alta);
                                $stmtImg->execute();
                                $rImg = $stmtImg->get_result()->fetch_assoc();
                                if ($rImg && isset($rImg['imagem_id']))
                                    $imagem_id = $rImg['imagem_id'];
                                $stmtImg->close();
                            }

                            $logs[] = 'render_alta.imagem_id=' . ($imagem_id !== null ? $imagem_id : 'null');

                            $insertStmt = $conn->prepare("INSERT IGNORE INTO followup_angles (render_id, imagem_id, filename, uploaded_at, status) VALUES (?, ?, ?, ?, 'pendente')");
                            while ($row = $resPre->fetch_assoc()) {
                                $filename = $row['filename'];
                                $uploaded_at = $row['uploaded_at'] ?: null;
                                $insertStmt->bind_param('iiss', $idrender_alta, $imagem_id, $filename, $uploaded_at);
                                $insertStmt->execute();
                            }
                            if ($insertStmt)
                                $insertStmt->close();
                            $stmtPre->close();

                            // ---------- Flow Review (2ª etapa): importar ângulos quando imagem for P00 ----------
                            if ($imagem_id) {
                                $statusNome = null;
                                if ($stStatus = $conn->prepare("SELECT s.nome_status FROM imagens_cliente_obra i JOIN status_imagem s ON s.idstatus = i.status_id WHERE i.idimagens_cliente_obra = ? LIMIT 1")) {
                                    $stStatus->bind_param('i', $imagem_id);
                                    $stStatus->execute();
                                    $rowStatus = $stStatus->get_result()->fetch_assoc();
                                    $statusNome = $rowStatus['nome_status'] ?? null;
                                    $stStatus->close();
                                }
                                $logs[] = 'imagem.status_nome=' . ($statusNome ?? 'null');

                                $isP00 = mb_strtolower(trim((string)$statusNome), 'UTF-8') === 'p00';
                                if ($isP00) {
                                    $funcaoImagemId = null;

                                    // Preferencial: funcao_id=4 (Finalização)
                                    if ($stFi = $conn->prepare("SELECT idfuncao_imagem FROM funcao_imagem WHERE imagem_id = ? AND funcao_id = 4 LIMIT 1")) {
                                        $stFi->bind_param('i', $imagem_id);
                                        $stFi->execute();
                                        $rowFi = $stFi->get_result()->fetch_assoc();
                                        $funcaoImagemId = isset($rowFi['idfuncao_imagem']) ? intval($rowFi['idfuncao_imagem']) : null;
                                        $stFi->close();
                                    }

                                    // Fallback por nome da função
                                    if (!$funcaoImagemId) {
                                        if ($stFi2 = $conn->prepare("SELECT fi.idfuncao_imagem FROM funcao_imagem fi JOIN funcao f ON f.idfuncao = fi.funcao_id WHERE fi.imagem_id = ? AND LOWER(f.nome_funcao) LIKE 'finaliza%' LIMIT 1")) {
                                            $stFi2->bind_param('i', $imagem_id);
                                            $stFi2->execute();
                                            $rowFi2 = $stFi2->get_result()->fetch_assoc();
                                            $funcaoImagemId = isset($rowFi2['idfuncao_imagem']) ? intval($rowFi2['idfuncao_imagem']) : null;
                                            $stFi2->close();
                                        }
                                    }

                                    $logs[] = 'finalizacao.funcao_imagem_id=' . ($funcaoImagemId ? $funcaoImagemId : 'null');

                                    if ($funcaoImagemId) {
                                        // Lê prazo/status atuais antes de atualizar (SLA)
                                        $fiPrazoP00  = null;
                                        $fiStatusP00 = null;
                                        if ($stFiCurP00 = $conn->prepare("SELECT prazo, status FROM funcao_imagem WHERE idfuncao_imagem = ? LIMIT 1")) {
                                            $stFiCurP00->bind_param('i', $funcaoImagemId);
                                            $stFiCurP00->execute();
                                            $rowFiCurP00 = $stFiCurP00->get_result()->fetch_assoc();
                                            $stFiCurP00->close();
                                            $fiPrazoP00  = $rowFiCurP00['prazo']  ?? null;
                                            $fiStatusP00 = $rowFiCurP00['status'] ?? null;
                                        }
                                        // garantir que apareça na revisão
                                        if ($stUpFi = $conn->prepare("UPDATE funcao_imagem SET prazo = NOW(), status = 'Em aprovação', requires_file_upload = 1, file_uploaded_at = NULL WHERE idfuncao_imagem = ?")) {
                                            $stUpFi->bind_param('i', $funcaoImagemId);
                                            $stUpFi->execute();
                                            $stUpFi->close();
                                            // Registra evento de entrega no histórico SLA
                                            $stmtSlaP00 = $conn->prepare(
                                                "INSERT INTO funcao_imagem_prazo_historico
                                                    (funcao_imagem_id, prazo_anterior, prazo_novo,
                                                     alterado_por_colaborador_id, alterado_por_usuario_id,
                                                     origem, motivo, status_anterior, status_novo)
                                                 VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?)"
                                            );
                                            if ($stmtSlaP00) {
                                                $_ajaxColabIdP00   = isset($_SESSION['idcolaborador']) ? (int)$_SESSION['idcolaborador'] : null;
                                                $_ajaxUsuarioIdP00 = isset($_SESSION['idusuario'])     ? (int)$_SESSION['idusuario']     : null;
                                                $_ajaxOrigemP00    = 'render_p00_aprovado';
                                                $_ajaxTodayP00     = date('Y-m-d');
                                                $_ajaxStNovP00     = 'Em aprovação';
                                                $stmtSlaP00->bind_param(
                                                    'issiisss',
                                                    $funcaoImagemId,
                                                    $fiPrazoP00,
                                                    $_ajaxTodayP00,
                                                    $_ajaxColabIdP00,
                                                    $_ajaxUsuarioIdP00,
                                                    $_ajaxOrigemP00,
                                                    $fiStatusP00,
                                                    $_ajaxStNovP00
                                                );
                                                $stmtSlaP00->execute();
                                                $stmtSlaP00->close();
                                            }
                                        }

                                        // índice de envio (um lote por aprovação)
                                        $nextIndice = 1;
                                        if ($stMax = $conn->prepare("SELECT MAX(indice_envio) AS max_indice FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ?")) {
                                            $stMax->bind_param('i', $funcaoImagemId);
                                            $stMax->execute();
                                            $rowMax = $stMax->get_result()->fetch_assoc();
                                            $max = isset($rowMax['max_indice']) ? intval($rowMax['max_indice']) : 0;
                                            $nextIndice = $max + 1;
                                            $stMax->close();
                                        }
                                        $logs[] = 'historico_aprovacoes_imagens.next_indice_envio=' . $nextIndice;

                                        // Rebuscar previews para não depender do cursor já percorrido
                                        $previewsToImport = [];
                                        if ($stPrev2 = $conn->prepare("SELECT filename FROM render_previews WHERE render_id = ? ORDER BY uploaded_at ASC, id ASC")) {
                                            $stPrev2->bind_param('i', $idrender_alta);
                                            $stPrev2->execute();
                                            $resPrev2 = $stPrev2->get_result();
                                            while ($p = $resPrev2->fetch_assoc()) {
                                                if (!empty($p['filename'])) $previewsToImport[] = $p['filename'];
                                            }
                                            $stPrev2->close();
                                        }
                                        $logs[] = 'previews_to_import=' . count($previewsToImport);

                                        foreach ($previewsToImport as $fn) {
                                            $path = 'uploads/renders/' . $fn;
                                            $nomeArquivo = pathinfo($fn, PATHINFO_FILENAME);

                                            // idempotência: se já existir para este funcao_imagem_id+path, reaproveita
                                            $histId = null;
                                            if ($stEx = $conn->prepare("SELECT id FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ? AND imagem = ? ORDER BY id DESC LIMIT 1")) {
                                                $stEx->bind_param('is', $funcaoImagemId, $path);
                                                $stEx->execute();
                                                $rowEx = $stEx->get_result()->fetch_assoc();
                                                $histId = isset($rowEx['id']) ? intval($rowEx['id']) : null;
                                                $stEx->close();
                                            }

                                            if (!$histId) {
                                                if ($stIns = $conn->prepare("INSERT INTO historico_aprovacoes_imagens (funcao_imagem_id, imagem, indice_envio, nome_arquivo, caminho_imagem) VALUES (?, ?, ?, ?, ?)")) {
                                                    $stIns->bind_param('isiss', $funcaoImagemId, $path, $nextIndice, $nomeArquivo, $path);
                                                    if ($stIns->execute()) {
                                                        $histId = $conn->insert_id;
                                                        $logs[] = 'import_ok: ' . $fn . ' -> historico_id=' . $histId;
                                                    } else {
                                                        $logs[] = 'import_erro: ' . $fn . ' -> ' . $stIns->error;
                                                    }
                                                    $stIns->close();
                                                } else {
                                                    $logs[] = 'import_prepare_erro: ' . $conn->error;
                                                }
                                            } else {
                                                $logs[] = 'import_skip_exists: ' . $fn . ' -> historico_id=' . $histId;
                                            }

                                            if ($histId) {
                                                if ($stAi = $conn->prepare("INSERT IGNORE INTO angulos_imagens (imagem_id, historico_id, entrega_item_id, liberada, sugerida, motivo_sugerida) VALUES (?, ?, NULL, 0, 0, '')")) {
                                                    $stAi->bind_param('ii', $imagem_id, $histId);
                                                    $stAi->execute();
                                                    $stAi->close();
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    // Quando não for P00: marcar a função de finalização como Finalizado
                                    $funcaoImagemId = null;
                                    $chosenFuncaoId = null;

                                    // Preferências: tentar funcao_id = 6, depois funcao_id = 4 (prioridade alterada)
                                    $tryIds = [6, 4];
                                    foreach ($tryIds as $fid) {
                                        if ($stFi = $conn->prepare("SELECT idfuncao_imagem, funcao_id FROM funcao_imagem WHERE imagem_id = ? AND funcao_id = ? LIMIT 1")) {
                                            $stFi->bind_param('ii', $imagem_id, $fid);
                                            $stFi->execute();
                                            $rowFi = $stFi->get_result()->fetch_assoc();
                                            $stFi->close();
                                            if ($rowFi) {
                                                $funcaoImagemId = intval($rowFi['idfuncao_imagem']);
                                                $chosenFuncaoId = intval($rowFi['funcao_id']);
                                                break;
                                            }
                                        }
                                    }

                                    // Fallback por nome da função (começa com 'finaliza') se não encontrou por id
                                    if (!$funcaoImagemId) {
                                        if ($stFi2 = $conn->prepare("SELECT fi.idfuncao_imagem, fi.funcao_id FROM funcao_imagem fi JOIN funcao f ON f.idfuncao = fi.funcao_id WHERE fi.imagem_id = ? AND LOWER(f.nome_funcao) LIKE 'finaliza%' LIMIT 1")) {
                                            $stFi2->bind_param('i', $imagem_id);
                                            $stFi2->execute();
                                            $rowFi2 = $stFi2->get_result()->fetch_assoc();
                                            if ($rowFi2) {
                                                $funcaoImagemId = intval($rowFi2['idfuncao_imagem']);
                                                $chosenFuncaoId = intval($rowFi2['funcao_id']);
                                            }
                                            $stFi2->close();
                                        }
                                    }

                                    if ($funcaoImagemId) {
                                        // Lê prazo/status atuais antes de atualizar (SLA)
                                        $fiPrazoFin  = null;
                                        $fiStatusFin = null;
                                        if ($stFiCurFin = $conn->prepare("SELECT prazo, status FROM funcao_imagem WHERE idfuncao_imagem = ? LIMIT 1")) {
                                            $stFiCurFin->bind_param('i', $funcaoImagemId);
                                            $stFiCurFin->execute();
                                            $rowFiCurFin = $stFiCurFin->get_result()->fetch_assoc();
                                            $stFiCurFin->close();
                                            $fiPrazoFin  = $rowFiCurFin['prazo']  ?? null;
                                            $fiStatusFin = $rowFiCurFin['status'] ?? null;
                                        }
                                        if ($stUpd = $conn->prepare("UPDATE funcao_imagem SET prazo = NOW(), status = 'Finalizado', requires_file_upload = 1, file_uploaded_at = NULL WHERE idfuncao_imagem = ?")) {
                                            $stUpd->bind_param('i', $funcaoImagemId);
                                            $stUpd->execute();
                                            $stUpd->close();
                                            $logs[] = 'finalizacao.marked_finalizado.idfuncao_imagem=' . $funcaoImagemId . '.funcao_id=' . $chosenFuncaoId;
                                            // Registra evento de finalização no histórico SLA
                                            $stmtSlaFin = $conn->prepare(
                                                "INSERT INTO funcao_imagem_prazo_historico
                                                    (funcao_imagem_id, prazo_anterior, prazo_novo,
                                                     alterado_por_colaborador_id, alterado_por_usuario_id,
                                                     origem, motivo, status_anterior, status_novo)
                                                 VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?)"
                                            );
                                            if ($stmtSlaFin) {
                                                $_ajaxColabIdFin   = isset($_SESSION['idcolaborador']) ? (int)$_SESSION['idcolaborador'] : null;
                                                $_ajaxUsuarioIdFin = isset($_SESSION['idusuario'])     ? (int)$_SESSION['idusuario']     : null;
                                                $_ajaxOrigemFin    = 'render_finalizado';
                                                $_ajaxTodayFin     = date('Y-m-d');
                                                $_ajaxStNovFin     = 'Finalizado';
                                                $stmtSlaFin->bind_param(
                                                    'issiisss',
                                                    $funcaoImagemId,
                                                    $fiPrazoFin,
                                                    $_ajaxTodayFin,
                                                    $_ajaxColabIdFin,
                                                    $_ajaxUsuarioIdFin,
                                                    $_ajaxOrigemFin,
                                                    $fiStatusFin,
                                                    $_ajaxStNovFin
                                                );
                                                $stmtSlaFin->execute();
                                                $stmtSlaFin->close();
                                            }
                                        } else {
                                            $logs[] = 'finalizacao.update_prepare_error: ' . $conn->error;
                                        }
                                    } else {
                                        $logs[] = 'finalizacao.not_found_for_imagem_id=' . $imagem_id;
                                    }
                                }
                            }
                        }
                    }
                    $resp = ['status' => 'sucesso', 'success' => true, 'message' => 'Render atualizado com sucesso'];
                    if ($deadlineFlowResult) {
                        $resp['tentativa_id'] = $deadlineFlowResult['tentativa_id'] ?? null;
                        $resp['deadline_command_created'] = $deadlineFlowResult['command']['created'] ?? false;
                        $resp['deadline_command_status'] = $deadlineFlowResult['command']['status'] ?? null;
                    }
                    if ($debug) $resp['logs'] = $logs;
                    notifyRenderUpdate('render.status_changed', ['render_id' => (int) $idrender_alta, 'status' => $status]);
                    echo json_encode($resp);
                } else {
                    if ($transactionStarted) {
                        $conn->rollback();
                    }
                    $logs[] = 'Erro ao atualizar o render (execute=false): ' . $conn->error;
                    $resp = ['status' => 'erro', 'message' => 'Erro ao atualizar o render'];
                    if ($debug) $resp['logs'] = $logs;
                    echo json_encode($resp);
                }
            }
            break;

        case 'updatePOS':
            // Aprovar o render
            if (isset($_POST['render_id'])) {
                $render_id = (int) $_POST['render_id'];
                $refs = (string) ($_POST['refs'] ?? '');
                $obs = (string) ($_POST['obs'] ?? '');
                $stmt = $conn->prepare('UPDATE pos_producao SET refs = ?, obs = ?, data_pos = NOW() WHERE render_id = ?');
                $stmt->bind_param('ssi', $refs, $obs, $render_id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    notifyPosProducaoUpdate('updated', ['render_id' => (int) $render_id]);
                    echo json_encode(['status' => 'sucesso']);
                } else {
                    echo json_encode(['status' => 'erro', 'message' => 'Pós-Produção não encontrada para o render.']);
                }
                $stmt->close();
            }
            break;

        case 'deleteRender':
            if (isset($_POST['idrender_alta'])) {
                $idrender_alta = (int) $_POST['idrender_alta'];
                try {
                    $conn->begin_transaction();
                    $archive = deadline_flow_archive_locked($conn, $idrender_alta);
                    $conn->commit();
                    echo json_encode([
                        'status' => 'sucesso',
                        'success' => true,
                        'message' => 'Render arquivado. Jobs vinculados foram adicionados a fila de exclusao.',
                        'render_id' => $idrender_alta,
                        'deadline_commands_created' => $archive['deadline_commands_created'],
                    ]);
                    notifyRenderUpdate('render.archived', ['render_id' => $idrender_alta]);
                } catch (Throwable $e) {
                    $conn->rollback();
                    echo json_encode([
                        'status' => 'erro',
                        'success' => false,
                        'message' => 'Erro ao arquivar o render: ' . $e->getMessage(),
                    ]);
                }
            }
            break;

        case 'getColaboradores':
            // movido para o bloco GET acima
            break;

        case 'updateResponsavel':
            if (isset($_POST['idrender_alta'], $_POST['responsavel_id'])) {
                $id = (int)$_POST['idrender_alta'];
                $resp_id = (int)$_POST['responsavel_id'];
                $stmt = $conn->prepare("UPDATE render_alta SET responsavel_id = ? WHERE idrender_alta = ?");
                $stmt->bind_param('ii', $resp_id, $id);
                if ($stmt->execute()) {
                    notifyRenderUpdate('render.assignee_changed', ['render_id' => $id, 'responsavel_id' => $resp_id]);
                    echo json_encode(['status' => 'sucesso']);
                } else {
                    echo json_encode(['status' => 'erro', 'message' => $stmt->error]);
                }
                $stmt->close();
            }
            break;
    }
}

$conn->close();
