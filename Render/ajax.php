<?php
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/aprovacao_interna_helper.php';
require_once __DIR__ . '/deadline_flow.php';
require_once __DIR__ . '/pos_referencias_helper.php';
require_once __DIR__ . '/render_ws_notify.php';
require_once __DIR__ . '/../Pos-Producao/ws_notify.php';
require_once __DIR__ . '/../FlowReview/ws_notify.php';
require_once __DIR__ . '/../helpers/funcao_imagem_prazo_helper.php';
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

function render_manual_completion_schema_ready(mysqli $conn): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'render_conclusoes_manuais'
           AND COLUMN_NAME IN ('render_id', 'tentativa_id', 'colaborador_id', 'status_anterior', 'justificativa', 'criado_em')"
    );
    if (!$stmt || !$stmt->execute()) {
        if ($stmt) $stmt->close();
        return $ready = false;
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ready = ((int) ($row['total'] ?? 0) === 6);
}

function render_manual_completion_require_schema(mysqli $conn): void
{
    if (!render_manual_completion_schema_ready($conn)) {
        throw new RuntimeException('A migration sql/2026-08-17_render_conclusao_manual.sql ainda nao foi aplicada.');
    }
}

function render_current_user_is_manager(mysqli $conn): bool
{
    $nivel = (int) ($_SESSION['nivel_acesso'] ?? 0);
    if (in_array($nivel, [1, 5], true)) {
        return true;
    }
    $cargoSessao = mb_strtolower(trim((string) ($_SESSION['cargo_colaborador'] ?? '')), 'UTF-8');
    foreach (['gestor', 'diretor', 'gerente'] as $term) {
        if ($cargoSessao !== '' && mb_strpos($cargoSessao, $term, 0, 'UTF-8') !== false) {
            return true;
        }
    }
    $usuarioId = (int) ($_SESSION['idusuario'] ?? 0);
    if ($usuarioId <= 0) {
        return false;
    }
    $stmt = $conn->prepare(
        "SELECT LOWER(c.nome) AS cargo
         FROM usuario_cargo uc
         JOIN cargo c ON c.id = uc.cargo_id
         WHERE uc.usuario_id = ?"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        foreach (['gestor', 'diretor', 'gerente'] as $term) {
            if (mb_strpos((string) $row['cargo'], $term, 0, 'UTF-8') !== false) {
                $stmt->close();
                return true;
            }
        }
    }
    $stmt->close();
    return false;
}

function render_can_complete_manually(mysqli $conn, array $render): bool
{
    if (!deadline_flow_manual_eligible_status((string) ($render['status'] ?? ''))) {
        return false;
    }
    $actorId = render_current_colaborador_id();
    if ($actorId <= 0) {
        return false;
    }
    return $actorId === (int) ($render['responsavel_id'] ?? 0)
        || render_current_user_is_manager($conn);
}

/**
 * Reads cached Deadline progress once for each unique job shown in a listing.
 *
 * The local worker owns command execution and parsing. A direct Python bridge is
 * retained only as a development fallback when the shared cache is not installed.
 */
function render_deadline_progress_for_jobs($conn, array $rawJobIds): array
{
    $progressByJob = [];
    foreach ($rawJobIds as $rawJobId) {
        $jobId = deadline_flow_valid_job_id((string) $rawJobId);
        if ($jobId !== null) {
            $progressByJob[$jobId] = [
                'deadline_job_progress' => null,
                'deadline_task_progress' => null,
                'deadline_task_render_status' => null,
                'deadline_task_render_summary' => null,
                'deadline_task_elapsed' => null,
                'deadline_task_time_remaining' => null,
                'deadline_estimated_time_remaining' => null,
            ];
        }
    }

    if (!$progressByJob) {
        return $progressByJob;
    }

    // O worker local, na rede do Deadline, preenche este cache no banco.
    // O VPS apenas lê os dados compartilhados e não acessa o repositório.
    $jobIds = array_keys($progressByJob);
    $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
    $cacheStmt = $conn->prepare(
        "SELECT deadline_job_id, deadline_job_progress, deadline_task_progress,
                deadline_task_render_status, deadline_task_render_summary,
                deadline_task_elapsed, deadline_task_time_remaining,
                deadline_estimated_time_remaining
         FROM deadline_job_progress
         WHERE deadline_job_id IN ($placeholders)"
    );
    if ($cacheStmt) {
        $types = str_repeat('s', count($jobIds));
        $bind = [$types];
        foreach ($jobIds as $index => $jobId) {
            $bind[] = &$jobIds[$index];
        }
        call_user_func_array([$cacheStmt, 'bind_param'], $bind);
        if ($cacheStmt->execute()) {
            $cacheResult = $cacheStmt->get_result();
            while ($item = $cacheResult->fetch_assoc()) {
                $jobId = (string) $item['deadline_job_id'];
                if (isset($progressByJob[$jobId])) {
                    $progressByJob[$jobId] = [
                        'deadline_job_progress' => is_numeric($item['deadline_job_progress'])
                            ? (float) $item['deadline_job_progress'] : null,
                        'deadline_task_progress' => is_numeric($item['deadline_task_progress'])
                            ? (float) $item['deadline_task_progress'] : null,
                        'deadline_task_render_status' => $item['deadline_task_render_status'] ?: null,
                        'deadline_task_render_summary' => $item['deadline_task_render_summary'] ?: null,
                        'deadline_task_elapsed' => $item['deadline_task_elapsed'] ?: null,
                        'deadline_task_time_remaining' => $item['deadline_task_time_remaining'] ?: null,
                        'deadline_estimated_time_remaining' => $item['deadline_estimated_time_remaining'] ?: null,
                    ];
                }
            }
            $cacheStmt->close();
            return $progressByJob;
        }
        $cacheStmt->close();
    } else {
        error_log('[Render] Deadline progress cache is not available; using direct bridge fallback.');
    }

    if (!function_exists('proc_open')) {
        error_log('[Render] Deadline progress unavailable: proc_open is disabled in PHP.');
        return $progressByJob;
    }

    $python = getenv('DEADLINE_PYTHON') ?: 'python';
    $bridge = __DIR__ . DIRECTORY_SEPARATOR . 'deadline_progress.py';
    if (!is_file($bridge)) {
        error_log('[Render] Deadline progress bridge was not found.');
        return $progressByJob;
    }

    $pipes = [];
    $process = @proc_open(
        [$python, $bridge],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        __DIR__
    );
    if (!is_resource($process)) {
        error_log('[Render] Deadline progress bridge could not be started.');
        return $progressByJob;
    }

    fwrite($pipes[0], json_encode(['job_ids' => array_keys($progressByJob)]));
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    $payload = json_decode($stdout, true);
    if ($exitCode !== 0 || !is_array($payload)) {
        $details = trim((string) $stderr);
        if ($details !== '') {
            $details = preg_replace('/\s+/', ' ', $details);
            $details = substr($details, 0, 500);
        }
        error_log(
            '[Render] Deadline progress query failed. exit=' . (string) $exitCode
            . ($details !== '' ? ' stderr=' . $details : '')
        );
        return $progressByJob;
    }

    $results = is_array($payload['results'] ?? null) ? $payload['results'] : [];
    foreach ($progressByJob as $jobId => $fallback) {
        $item = $results[$jobId] ?? null;
        if (!is_array($item)) {
            continue;
        }
        $progress = $item['job_progress'] ?? null;
        $progressByJob[$jobId] = [
            'deadline_job_progress' => is_numeric($progress) ? (float) $progress : null,
            'deadline_task_progress' => is_numeric($item['task_progress'] ?? null)
                ? (float) $item['task_progress']
                : null,
            'deadline_task_render_status' => !empty($item['task_render_status'])
                ? (string) $item['task_render_status']
                : null,
            'deadline_task_render_summary' => !empty($item['task_render_summary'])
                ? (string) $item['task_render_summary']
                : null,
            'deadline_task_elapsed' => !empty($item['task_elapsed'])
                ? (string) $item['task_elapsed']
                : null,
            'deadline_task_time_remaining' => !empty($item['task_time_remaining'])
                ? (string) $item['task_time_remaining']
                : null,
            'deadline_estimated_time_remaining' => !empty($item['estimated_time_remaining'])
                ? (string) $item['estimated_time_remaining']
                : null,
        ];
    }

    if (!empty($payload['errors']) || trim($stderr) !== '') {
        $details = trim((string) $stderr);
        if ($details !== '') {
            $details = preg_replace('/\s+/', ' ', $details);
            $details = substr($details, 0, 500);
        }
        error_log(
            '[Render] One or more Deadline progress queries are unavailable.'
            . ($details !== '' ? ' stderr=' . $details : '')
        );
    }

    return $progressByJob;
}

/**
 * Finaliza a etapa de Finalizacao da imagem quando o render e enviado para a Pos.
 * Esta operacao deve ocorrer dentro da mesma transacao da aprovacao do render.
 */
function render_mark_finalizacao_file_pending($conn, int $imagemId, int $colaboradorId): int
{
    $funcaoImagemId = 0;

    // Mantem a mesma prioridade usada pelo fluxo legado de aprovacao de Render.
    foreach ([6, 4] as $funcaoId) {
        $stmt = $conn->prepare(
            'SELECT idfuncao_imagem
             FROM funcao_imagem
             WHERE imagem_id = ? AND funcao_id = ?
             LIMIT 1 FOR UPDATE'
        );
        if (!$stmt) {
            throw new RuntimeException('Nao foi possivel localizar a funcao de Finalizacao.');
        }
        $stmt->bind_param('ii', $imagemId, $funcaoId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $funcaoImagemId = (int) $row['idfuncao_imagem'];
            break;
        }
    }

    // Compatibilidade com obras cuja funcao de Finalizacao nao usa os IDs acima.
    if ($funcaoImagemId <= 0) {
        $stmt = $conn->prepare(
            "SELECT fi.idfuncao_imagem
             FROM funcao_imagem fi
             INNER JOIN funcao f ON f.idfuncao = fi.funcao_id
             WHERE fi.imagem_id = ? AND LOWER(f.nome_funcao) LIKE 'finaliza%'
             LIMIT 1 FOR UPDATE"
        );
        if (!$stmt) {
            throw new RuntimeException('Nao foi possivel localizar a funcao de Finalizacao.');
        }
        $stmt->bind_param('i', $imagemId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $funcaoImagemId = $row ? (int) $row['idfuncao_imagem'] : 0;
    }

    if ($funcaoImagemId <= 0) {
        throw new RuntimeException('Funcao de Finalizacao nao encontrada para esta imagem.');
    }

    $stmt = $conn->prepare(
        'SELECT prazo, status FROM funcao_imagem WHERE idfuncao_imagem = ? LIMIT 1 FOR UPDATE'
    );
    if (!$stmt) {
        throw new RuntimeException('Nao foi possivel ler o status atual da Finalizacao.');
    }
    $stmt->bind_param('i', $funcaoImagemId);
    $stmt->execute();
    $before = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$before) {
        throw new RuntimeException('Funcao de Finalizacao nao encontrada para esta imagem.');
    }

    $prazoResult = funcao_imagem_prazo_atualizar(
        $conn,
        $funcaoImagemId,
        date('Y-m-d'),
        [
            'origem' => 'render_finalizado',
            'alterado_por_colaborador_id' => $colaboradorId,
            'alterado_por_usuario_id' => isset($_SESSION['idusuario']) ? (int) $_SESSION['idusuario'] : null,
            'status_novo' => 'Finalizado',
        ]
    );
    $sqlFinalizacaoFlags = $prazoResult['alterado']
        ? 'UPDATE funcao_imagem SET requires_file_upload = 1, file_uploaded_at = NULL WHERE idfuncao_imagem = ?'
        : "UPDATE funcao_imagem SET status = 'Finalizado', requires_file_upload = 1, file_uploaded_at = NULL WHERE idfuncao_imagem = ?";
    $stmt = $conn->prepare($sqlFinalizacaoFlags);
    if (!$stmt) {
        throw new RuntimeException('Nao foi possivel finalizar a funcao da imagem.');
    }
    $stmt->bind_param('i', $funcaoImagemId);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Nao foi possivel finalizar a funcao da imagem: ' . $error);
    }
    $stmt->close();

    return $funcaoImagemId;
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
            ]);
            break;

        case 'getRenders':
            // Os filtros são aplicados antes da paginação para que "Carregar mais"
            // percorra somente os resultados do contexto selecionado.
            $page  = max(1, (int)($_GET['page']  ?? 1));
            $limit = max(1, min(200, (int)($_GET['limit'] ?? 100)));
            $offset = ($page - 1) * $limit;

            $where = ["r.status != 'Arquivado'"];
            $params = [];
            $types = '';
            $addFilter = static function ($column, $value) use (&$where, &$params, &$types) {
                if ($value === '') {
                    return;
                }
                $where[] = "$column = ?";
                $params[] = $value;
                $types .= 's';
            };

            $addFilter('r.status', trim((string)($_GET['status'] ?? '')));
            $addFilter('s.nome_status', trim((string)($_GET['statusImagem'] ?? '')));
            $addFilter('c.nome_colaborador', trim((string)($_GET['colaborador'] ?? '')));
            $addFilter('o.nomenclatura', trim((string)($_GET['obra'] ?? '')));

            $search = trim((string)($_GET['search'] ?? ''));
            if ($search !== '') {
                $where[] = '(i.imagem_nome LIKE ? OR o.nomenclatura LIKE ? OR c.nome_colaborador LIKE ? OR r.status LIKE ?)';
                $like = '%' . $search . '%';
                array_push($params, $like, $like, $like, $like);
                $types .= 'ssss';
            }

            $dateFrom = trim((string)($_GET['dateFrom'] ?? ''));
            if ($dateFrom !== '') {
                $where[] = 'r.data >= ?';
                $params[] = $dateFrom . ' 00:00:00';
                $types .= 's';
            }

            $dateTo = trim((string)($_GET['dateTo'] ?? ''));
            if ($dateTo !== '') {
                $where[] = 'r.data <= ?';
                $params[] = $dateTo . ' 23:59:59';
                $types .= 's';
            }

            $manualCompletionAvailable = render_manual_completion_schema_ready($conn);
            $manualJoin = $manualCompletionAvailable
                ? "\nLEFT JOIN render_conclusoes_manuais rcm ON rcm.id = (
                    SELECT cm.id FROM render_conclusoes_manuais cm
                    WHERE cm.render_id = r.idrender_alta
                    ORDER BY cm.criado_em DESC, cm.id DESC
                    LIMIT 1
                )"
                : '';
            $manualSelect = $manualCompletionAvailable
                ? ", CASE WHEN BINARY r.status = 0x456D206170726F7661C3A7C3A36F
                           AND rcm.tentativa_id = (
                               SELECT rt.id FROM render_tentativas rt
                               WHERE rt.render_id = r.idrender_alta
                               ORDER BY rt.numero_tentativa DESC LIMIT 1
                           ) THEN 1 ELSE 0 END AS concluido_manualmente"
                : ', 0 AS concluido_manualmente';
            $from = "
FROM render_alta r
LEFT JOIN imagens_cliente_obra i ON r.imagem_id = i.idimagens_cliente_obra
LEFT JOIN colaborador c ON r.responsavel_id = c.idcolaborador
LEFT JOIN status_imagem s ON r.status_id = s.idstatus
LEFT JOIN obra o ON i.obra_id = o.idobra" . $manualJoin;
            $whereSql = ' WHERE ' . implode(' AND ', $where);

            $bind = static function ($stmt, $bindTypes, $bindParams) {
                if ($bindTypes !== '') {
                    $stmt->bind_param($bindTypes, ...$bindParams);
                }
                $stmt->execute();
                return $stmt->get_result();
            };

            $stmtCount = $conn->prepare("SELECT COUNT(*) AS total $from $whereSql");
            $resCount = $bind($stmtCount, $types, $params);
            $total = (int)$resCount->fetch_assoc()['total'];
            $stmtCount->close();

            $sql = "SELECT 
    c.nome_colaborador, 
    s.nome_status, 
    i.imagem_nome,
    i.tipo_imagem,
    o.nome_obra,
    o.nomenclatura AS obra_nomenclatura,
    r.* $manualSelect
 $from
 $whereSql
ORDER BY 
    FIELD(r.status, 'Em aprovação', 'Em andamento', 'Refazendo', 'Reprovado', 'Erro', 'Aprovado'), data DESC
LIMIT $limit OFFSET $offset";
            $stmt = $conn->prepare($sql);
            $result = $bind($stmt, $types, $params);
            $renders = [];

            while ($row = $result->fetch_assoc()) {
                $row['concluido_manualmente'] = (bool) ($row['concluido_manualmente'] ?? false);
                $renders[] = $row;
            }
            $stmt->close();

            $progressByJob = render_deadline_progress_for_jobs(
                $conn,
                array_column($renders, 'deadline_job_id')
            );
            foreach ($renders as &$render) {
                $jobId = deadline_flow_valid_job_id((string) ($render['deadline_job_id'] ?? ''));
                $progress = $jobId !== null ? ($progressByJob[$jobId] ?? null) : null;
                $render['deadline_job_progress'] = $progress['deadline_job_progress'] ?? null;
                $render['deadline_task_progress'] = $progress['deadline_task_progress'] ?? null;
                $render['deadline_task_render_status'] = $progress['deadline_task_render_status'] ?? null;
                $render['deadline_task_render_summary'] = $progress['deadline_task_render_summary'] ?? null;
                $render['deadline_task_elapsed'] = $progress['deadline_task_elapsed'] ?? null;
                $render['deadline_task_time_remaining'] = $progress['deadline_task_time_remaining'] ?? null;
                $render['deadline_estimated_time_remaining'] = $progress['deadline_estimated_time_remaining'] ?? null;
            }
            unset($render);

            $filterOptions = null;
            if ($page === 1) {
                // As opções não dependem da página atual: uma obra/responsável que não
                // esteja entre os primeiros 200 ainda precisa poder ser selecionado.
                $filterOptions = [
                    'obras' => [],
                    'colaboradores' => [],
                    'status' => [],
                    'statusImagem' => [],
                ];
                $optionQueries = [
                    'obras' => "SELECT DISTINCT o.nomenclatura AS value $from WHERE r.status != 'Arquivado' AND o.nomenclatura IS NOT NULL ORDER BY value",
                    'colaboradores' => "SELECT DISTINCT c.nome_colaborador AS value $from WHERE r.status != 'Arquivado' AND c.nome_colaborador IS NOT NULL ORDER BY value",
                    'status' => "SELECT DISTINCT r.status AS value $from WHERE r.status != 'Arquivado' AND r.status IS NOT NULL ORDER BY value",
                    'statusImagem' => "SELECT DISTINCT s.nome_status AS value $from WHERE r.status != 'Arquivado' AND s.nome_status IS NOT NULL ORDER BY value",
                ];
                foreach ($optionQueries as $key => $optionQuery) {
                    $optionResult = $conn->query($optionQuery);
                    while ($optionRow = $optionResult->fetch_assoc()) {
                        $filterOptions[$key][] = $optionRow['value'];
                    }
                }
            }

            echo json_encode(['status' => 'sucesso', 'renders' => $renders, 'total' => $total, 'page' => $page, 'limit' => $limit, 'filterOptions' => $filterOptions]);
            break;

        case 'getRender':
            // Buscar um render específico
            if (isset($_GET['idrender_alta'])) {
                $idrender_alta = (int) $_GET['idrender_alta'];
                $manualCompletionAvailable = render_manual_completion_schema_ready($conn);
                $manualJoin = $manualCompletionAvailable
                    ? " LEFT JOIN render_conclusoes_manuais rcm ON rcm.id = (
                        SELECT cm.id FROM render_conclusoes_manuais cm
                        WHERE cm.render_id = r.idrender_alta
                        ORDER BY cm.criado_em DESC, cm.id DESC LIMIT 1
                    ) LEFT JOIN colaborador manual_colaborador ON manual_colaborador.idcolaborador = rcm.colaborador_id"
                    : '';
                $manualSelect = $manualCompletionAvailable
                    ? ", CASE WHEN BINARY r.status = 0x456D206170726F7661C3A7C3A36F
                               AND rcm.tentativa_id = (
                                   SELECT rt.id FROM render_tentativas rt
                                   WHERE rt.render_id = r.idrender_alta
                                   ORDER BY rt.numero_tentativa DESC LIMIT 1
                               ) THEN 1 ELSE 0 END AS concluido_manualmente,
                         rcm.justificativa AS justificativa_conclusao_manual,
                         rcm.criado_em AS concluido_manualmente_em,
                         manual_colaborador.nome_colaborador AS concluido_manualmente_por"
                    : ', 0 AS concluido_manualmente, NULL AS justificativa_conclusao_manual, NULL AS concluido_manualmente_em, NULL AS concluido_manualmente_por';
                $sql = "SELECT r.*, i.imagem_nome, i.tipo_imagem, c.nome_colaborador, s.nome_status,
                               o.nomenclatura AS obra_nomenclatura $manualSelect
                        FROM render_alta r
                        JOIN imagens_cliente_obra i ON r.imagem_id = i.idimagens_cliente_obra
                        LEFT JOIN colaborador c ON r.responsavel_id = c.idcolaborador
                        JOIN status_imagem s ON r.status_id = s.idstatus
                        LEFT JOIN obra o ON o.idobra = i.obra_id
                        $manualJoin
                        WHERE r.idrender_alta = ?";
                $stmtRender = $conn->prepare($sql);
                $stmtRender->bind_param('i', $idrender_alta);
                $stmtRender->execute();
                $render = $stmtRender->get_result()->fetch_assoc();
                $stmtRender->close();
                if (!$render) {
                    echo json_encode(['status' => 'erro', 'message' => 'Render nao encontrado.']);
                    break;
                }
                $render['concluido_manualmente'] = (bool) ($render['concluido_manualmente'] ?? false);
                $render['can_complete_manually'] = render_can_complete_manually($conn, $render);

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

                $manualEvents = [];
                if (render_manual_completion_schema_ready($conn)) {
                    $stmtManual = $conn->prepare(
                        "SELECT cm.status_anterior, cm.justificativa, cm.criado_em,
                                c.nome_colaborador AS autor
                         FROM render_conclusoes_manuais cm
                         JOIN colaborador c ON c.idcolaborador = cm.colaborador_id
                         WHERE cm.render_id = ?
                         ORDER BY cm.criado_em ASC, cm.id ASC"
                    );
                    if ($stmtManual) {
                        $stmtManual->bind_param('i', $render_id);
                        $stmtManual->execute();
                        $resultManual = $stmtManual->get_result();
                        while ($manual = $resultManual->fetch_assoc()) {
                            $manualEvents[] = $manual;
                        }
                        $stmtManual->close();
                    }
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

                foreach ($manualEvents as $manual) {
                    $timeline[] = [
                        'status_anterior' => $manual['status_anterior'],
                        'status_novo' => deadline_flow_approval_status(),
                        'data' => $manual['criado_em'],
                        'source' => 'manual',
                        'type' => 'manual_completion',
                        'autor' => $manual['autor'],
                        'justificativa' => $manual['justificativa'],
                        'is_start' => false,
                    ];
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
                    i.obra_id, i.tipo_imagem, s.nome_status, p.idpos_producao
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

                $isPlantaHumanizada = mb_strtolower(trim((string) ($render['tipo_imagem'] ?? '')), 'UTF-8') === 'planta humanizada';
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

                $posId = 0;
                $references = [];
                if (!$isPlantaHumanizada) {
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
                    $references = pos_referencias_list($conn, $posId);
                }

                // A aprovacao so e concluida quando a Finalizacao estiver marcada
                // como concluida e aguardando o upload do arquivo final.
                render_mark_finalizacao_file_pending($conn, (int) $render['imagem_id'], $colaboradorId);

                $updateRender = $conn->prepare("UPDATE render_alta SET status = 'Aprovado', data = NOW() WHERE idrender_alta = ?");
                $updateRender->bind_param('i', $renderId);
                if (!$updateRender->execute()) throw new RuntimeException('Não foi possível aprovar o render.');
                $updateRender->close();
                $deadlineFlowResult = deadline_flow_approve_locked($conn, $renderId);
                $conn->commit();

                notifyRenderUpdate($isPlantaHumanizada ? 'render.approved_without_pos' : 'render.approved_to_pos', ['render_id' => $renderId, 'imagem_id' => (int) $render['imagem_id'], 'pos_producao_id' => $posId, 'tipo_imagem' => $render['tipo_imagem'] ?? null]);
                if (!$isPlantaHumanizada && function_exists('notifyPosProducaoUpdate')) notifyPosProducaoUpdate('references_changed', ['render_id' => $renderId, 'pos_producao_id' => $posId]);
                echo json_encode(['status' => 'sucesso', 'render_id' => $renderId, 'pos_producao_id' => $posId, 'skip_pos_producao' => $isPlantaHumanizada, 'tipo_imagem' => $render['tipo_imagem'] ?? null, 'references' => $references, 'deadline_command_created' => $deadlineFlowResult['command']['created'] ?? false]);
            } catch (Throwable $e) {
                $conn->rollback();
                pos_referencias_cleanup_uploaded_files($savedFiles);
                echo json_encode(['status' => 'erro', 'message' => $e->getMessage()]);
            }
            break;

        case 'getPosReferences':
            $renderId = isset($_GET['render_id']) ? (int) $_GET['render_id'] : 0;
            pos_referencias_ensure_schema($conn);
            $stmt = $conn->prepare('SELECT p.idpos_producao, i.tipo_imagem
                FROM pos_producao p
                JOIN render_alta r ON r.idrender_alta = p.render_id
                JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = r.imagem_id
                WHERE p.render_id = ? LIMIT 1');
            $stmt->bind_param('i', $renderId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row && mb_strtolower(trim((string) ($row['tipo_imagem'] ?? '')), 'UTF-8') === 'planta humanizada') {
                echo json_encode(['status' => 'sucesso', 'skip_pos_producao' => true, 'references' => []]);
                break;
            }
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
            $stmt = $conn->prepare("SELECT r.idrender_alta, r.previa_jpg, i.tipo_imagem, p.idpos_producao
                FROM render_alta r
                JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = r.imagem_id
                LEFT JOIN pos_producao p ON p.render_id = r.idrender_alta
                WHERE r.idrender_alta = ? LIMIT 1");
            $stmt->bind_param('i', $renderId);
            $stmt->execute();
            $review = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $isPlantaHumanizada = $review && mb_strtolower(trim((string) ($review['tipo_imagem'] ?? '')), 'UTF-8') === 'planta humanizada';
            if ($isPlantaHumanizada) {
                echo json_encode([
                    'status' => 'sucesso',
                    'render_id' => $renderId,
                    'pos_producao_id' => 0,
                    'skip_pos_producao' => true,
                    'tipo_imagem' => $review['tipo_imagem'],
                    'references' => [],
                ]);
                break;
            }
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
            $stmt = $conn->prepare('SELECT p.idpos_producao, i.tipo_imagem
                FROM pos_producao p
                JOIN render_alta r ON r.idrender_alta = p.render_id
                JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = r.imagem_id
                WHERE p.render_id = ? LIMIT 1');
            $stmt->bind_param('i', $renderId);
            $stmt->execute();
            $pos = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($pos && mb_strtolower(trim((string) ($pos['tipo_imagem'] ?? '')), 'UTF-8') === 'planta humanizada') {
                echo json_encode(['status' => 'erro', 'message' => 'Planta Humanizada nao utiliza a Pos-Producao.']);
                break;
            }
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

        case 'completeRenderManually':
            $renderId = isset($_POST['idrender_alta']) ? (int) $_POST['idrender_alta'] : 0;
            $reason = trim((string) ($_POST['justificativa'] ?? ''));
            $actorId = render_current_colaborador_id();
            if ($renderId <= 0 || $actorId <= 0) {
                http_response_code(422);
                echo json_encode(['status' => 'erro', 'message' => 'Render ou colaborador invalido.']);
                break;
            }
            if ($reason === '') {
                http_response_code(422);
                echo json_encode(['status' => 'erro', 'message' => 'Informe a justificativa da conclusao manual.']);
                break;
            }
            if (mb_strlen($reason, 'UTF-8') > 2000) {
                http_response_code(422);
                echo json_encode(['status' => 'erro', 'message' => 'A justificativa deve ter no maximo 2.000 caracteres.']);
                break;
            }
            try {
                render_manual_completion_require_schema($conn);
                $conn->begin_transaction();
                $completion = deadline_flow_complete_manually_locked(
                    $conn,
                    $renderId,
                    $actorId,
                    render_current_user_is_manager($conn),
                    $reason
                );
                $attemptId = (int) $completion['tentativa_id'];
                $stmtAudit = $conn->prepare(
                    "INSERT INTO render_conclusoes_manuais
                        (render_id, tentativa_id, colaborador_id, status_anterior, justificativa)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $previousStatus = (string) $completion['status_anterior'];
                $stmtAudit->bind_param('iiiss', $renderId, $attemptId, $actorId, $previousStatus, $reason);
                if (!$stmtAudit->execute()) {
                    $error = $stmtAudit->error;
                    $stmtAudit->close();
                    throw new RuntimeException('Nao foi possivel registrar a auditoria: ' . $error);
                }
                $stmtAudit->close();
                $conn->commit();
                notifyRenderUpdate('render.manually_completed', [
                    'render_id' => $renderId,
                    'tentativa_id' => $attemptId,
                    'status' => deadline_flow_approval_status(),
                ]);
                echo json_encode([
                    'status' => 'sucesso',
                    'render_id' => $renderId,
                    'tentativa_id' => $attemptId,
                    'deadline_command_created' => $completion['command']['created'] ?? false,
                    'deadline_command_status' => $completion['command']['status'] ?? null,
                    'message' => 'Render marcado como feito manualmente e enviado para aprovacao.',
                ]);
            } catch (Throwable $e) {
                $conn->rollback();
                http_response_code(422);
                echo json_encode(['status' => 'erro', 'message' => $e->getMessage()]);
            }
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
                $flowReviewMediaCreated = [];
                $flowReviewMediaContext = null;
                $isPlantaHumanizadaRender = false;

                if (strtolower($status) === 'aprovado') {
                    $stmtTipoImagem = $conn->prepare('SELECT i.tipo_imagem
                        FROM render_alta r
                        JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = r.imagem_id
                        WHERE r.idrender_alta = ? LIMIT 1');
                    if ($stmtTipoImagem) {
                        $stmtTipoImagem->bind_param('i', $idrender_alta);
                        $stmtTipoImagem->execute();
                        $tipoImagemRow = $stmtTipoImagem->get_result()->fetch_assoc();
                        $stmtTipoImagem->close();
                        $isPlantaHumanizadaRender = mb_strtolower(trim((string) ($tipoImagemRow['tipo_imagem'] ?? '')), 'UTF-8') === 'planta humanizada';
                    }
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

                        if (!$isPlantaHumanizadaRender) {
                            $stmtPos = $conn->prepare("UPDATE pos_producao SET data_pos = NOW() WHERE render_id = ?");
                            if ($stmtPos) {
                                $stmtPos->bind_param('i', $idrender_alta);
                                $stmtPos->execute();
                                $logs[] = 'pos_producao.data_pos resetado para NOW()';
                                $stmtPos->close();
                            } else {
                                $logs[] = 'Erro prepare pos_producao reset: ' . $conn->error;
                            }
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
                                        $prazoResultP00 = funcao_imagem_prazo_atualizar(
                                            $conn,
                                            (int) $funcaoImagemId,
                                            date('Y-m-d'),
                                            [
                                                'origem' => 'render_p00_aprovado',
                                                'alterado_por_colaborador_id' => isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null,
                                                'alterado_por_usuario_id' => isset($_SESSION['idusuario']) ? (int) $_SESSION['idusuario'] : null,
                                                'status_novo' => 'Em aprovação',
                                            ]
                                        );
                                        $sqlP00Flags = $prazoResultP00['alterado']
                                            ? 'UPDATE funcao_imagem SET requires_file_upload = 1, file_uploaded_at = NULL WHERE idfuncao_imagem = ?'
                                            : "UPDATE funcao_imagem SET status = 'Em aprovação', requires_file_upload = 1, file_uploaded_at = NULL WHERE idfuncao_imagem = ?";
                                        $stUpFi = $conn->prepare($sqlP00Flags);
                                        if (!$stUpFi) {
                                            throw new RuntimeException('Nao foi possivel preparar a atualizacao da Finalizacao P00.');
                                        }
                                        $stUpFi->bind_param('i', $funcaoImagemId);
                                        if (!$stUpFi->execute()) {
                                            $error = $stUpFi->error;
                                            $stUpFi->close();
                                            throw new RuntimeException('Nao foi possivel atualizar a Finalizacao P00: ' . $error);
                                        }
                                        $stUpFi->close();

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
                                                        $flowReviewMediaCreated[] = (int)$histId;
                                                        $flowReviewMediaContext = [
                                                            'imagem_id' => (int)$imagem_id,
                                                            'funcao_imagem_id' => (int)$funcaoImagemId,
                                                            'historico_id' => (int)$histId,
                                                            'indice_envio' => (int)$nextIndice,
                                                            'versao' => (int)$nextIndice,
                                                            'render_id' => (int)$idrender_alta,
                                                        ];
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
                                        $prazoResultFin = funcao_imagem_prazo_atualizar(
                                            $conn,
                                            (int) $funcaoImagemId,
                                            date('Y-m-d'),
                                            [
                                                'origem' => 'render_finalizado',
                                                'alterado_por_colaborador_id' => isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null,
                                                'alterado_por_usuario_id' => isset($_SESSION['idusuario']) ? (int) $_SESSION['idusuario'] : null,
                                                'status_novo' => 'Finalizado',
                                            ]
                                        );
                                        $sqlFinFlags = $prazoResultFin['alterado']
                                            ? 'UPDATE funcao_imagem SET requires_file_upload = 1, file_uploaded_at = NULL WHERE idfuncao_imagem = ?'
                                            : "UPDATE funcao_imagem SET status = 'Finalizado', requires_file_upload = 1, file_uploaded_at = NULL WHERE idfuncao_imagem = ?";
                                        $stUpd = $conn->prepare($sqlFinFlags);
                                        if (!$stUpd) {
                                            throw new RuntimeException('Nao foi possivel preparar a atualizacao da Finalizacao.');
                                        }
                                        $stUpd->bind_param('i', $funcaoImagemId);
                                        if (!$stUpd->execute()) {
                                            $error = $stUpd->error;
                                            $stUpd->close();
                                            throw new RuntimeException('Nao foi possivel atualizar a Finalizacao: ' . $error);
                                        }
                                        $stUpd->close();
                                        $logs[] = 'finalizacao.marked_finalizado.idfuncao_imagem=' . $funcaoImagemId . '.funcao_id=' . $chosenFuncaoId;
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
                    if ($flowReviewMediaCreated && $flowReviewMediaContext) {
                        notifyFlowReviewUpdate($conn, 'media.created', array_merge($flowReviewMediaContext, [
                            'historico_ids' => $flowReviewMediaCreated,
                            'media_count' => count($flowReviewMediaCreated),
                        ]));
                    }
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
                $stmtType = $conn->prepare('SELECT i.tipo_imagem
                    FROM render_alta r
                    JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = r.imagem_id
                    WHERE r.idrender_alta = ? LIMIT 1');
                $stmtType->bind_param('i', $render_id);
                $stmtType->execute();
                $typeRow = $stmtType->get_result()->fetch_assoc();
                $stmtType->close();
                if ($typeRow && mb_strtolower(trim((string) ($typeRow['tipo_imagem'] ?? '')), 'UTF-8') === 'planta humanizada') {
                    echo json_encode(['status' => 'erro', 'message' => 'Planta Humanizada nao utiliza a Pos-Producao.']);
                    break;
                }
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
