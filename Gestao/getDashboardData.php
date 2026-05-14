<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexaoMain.php';

const GESTAO_EXCLUDED_COLLABORATORS = [15, 21, 30];
const GESTAO_CLOSED_STATUSES = ['finalizado', 'aprovado'];

function gestao_json_exit(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function gestao_normalize(?string $value): string
{
    $trimmed = trim((string) $value);

    if ($trimmed === '') {
        return '';
    }

    return function_exists('mb_strtolower')
        ? mb_strtolower($trimmed, 'UTF-8')
        : strtolower($trimmed);
}

function gestao_contains(string $haystack, string $needle): bool
{
    return $needle !== '' && strpos($haystack, $needle) !== false;
}

function gestao_fetch_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException('Falha ao preparar consulta: ' . $conn->error);
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Falha ao executar consulta: ' . $error);
    }

    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function gestao_fetch_one(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $rows = gestao_fetch_all($conn, $sql, $types, $params);

    return $rows[0] ?? [];
}

function gestao_is_open_task(string $status): bool
{
    return !in_array($status, GESTAO_CLOSED_STATUSES, true);
}

function gestao_map_stage(?string $status, ?string $functionName): string
{
    $normalizedStatus = gestao_normalize($status);
    $normalizedFunction = gestao_normalize($functionName);

    if ($normalizedStatus === 'em aprovação') {
        return 'Aprovação';
    }

    if (in_array($normalizedStatus, ['ajuste', 'aprovado com ajustes'], true)) {
        return 'Revisão';
    }

    if (gestao_contains($normalizedFunction, 'model')) {
        return 'Modelagem';
    }

    if (gestao_contains($normalizedFunction, 'pós') || gestao_contains($normalizedFunction, 'pos')) {
        return 'Pós-produção';
    }

    return 'Render';
}

function gestao_map_to_canonical_function(?string $functionName): string
{
    $n = gestao_normalize($functionName);

    if ($n === '') {
        return 'Modelagem';
    }

    // Exact matches first
    $exactMap = [
        'caderno'          => 'Caderno',
        'filtro de assets' => 'Filtro de assets',
        'modelagem'        => 'Modelagem',
        'composição'       => 'Composição',
        'composicao'       => 'Composição',
        'finalização'      => 'Finalização',
        'finalizacao'      => 'Finalização',
        'pós-produção'     => 'Pós-produção',
        'pos-producao'     => 'Pós-produção',
        'alteração'        => 'Alteração',
        'alteracao'        => 'Alteração',
    ];

    if (isset($exactMap[$n])) {
        return $exactMap[$n];
    }

    // Fuzzy matching — order matters (most specific first)
    if (gestao_contains($n, 'caderno')) {
        return 'Caderno';
    }

    if (gestao_contains($n, 'filtro') || gestao_contains($n, 'asset')) {
        return 'Filtro de assets';
    }

    if (gestao_contains($n, 'composi')) {
        return 'Composição';
    }

    if (gestao_contains($n, 'model')) {
        return 'Modelagem';
    }

    if (gestao_contains($n, 'altera') || gestao_contains($n, 'revis') || gestao_contains($n, 'ajust')) {
        return 'Alteração';
    }

    if (gestao_contains($n, 'finaliz') || gestao_contains($n, 'finish') || gestao_contains($n, 'render')) {
        return 'Finalização';
    }

    if (gestao_contains($n, 'pós') || gestao_contains($n, 'pós-') || gestao_contains($n, 'pos-')) {
        return 'Pós-produção';
    }

    return 'Modelagem';
}

function gestao_map_primary_role(?string $functionName): string
{
    return gestao_map_to_canonical_function($functionName);
}

function gestao_classify_delivery(array $delivery, string $todayYmd): string
{
    $total = (int) ($delivery['total_itens'] ?? 0);
    $delivered = (int) ($delivery['entregues_count'] ?? 0);
    $status = 'pendente';

    if ($total > 0 && $delivered > 0 && $delivered < $total) {
        $status = 'parcial';
    } elseif ($total > 0 && $delivered >= $total) {
        $status = 'concluida';
    }

    if ((int) ($delivery['em_hold'] ?? 0) === 1) {
        return 'hold';
    }

    $dueDate = (string) ($delivery['data_prevista'] ?? '');
    if ($dueDate !== '' && $dueDate < $todayYmd && in_array($status, ['pendente', 'parcial'], true)) {
        return 'atrasada';
    }

    return $status;
}

function gestao_relative_time(?string $dateTime): string
{
    if (!$dateTime) {
        return 'agora';
    }

    $timestamp = strtotime($dateTime);
    if (!$timestamp) {
        return 'agora';
    }

    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'agora';
    }

    if ($diff < 3600) {
        $minutes = (int) floor($diff / 60);
        return 'Há ' . $minutes . ' min';
    }

    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return 'Há ' . $hours . ' hora' . ($hours > 1 ? 's' : '');
    }

    $days = (int) floor($diff / 86400);
    return 'Há ' . $days . ' dia' . ($days > 1 ? 's' : '');
}

function gestao_avatar_url(?string $avatarPath): ?string
{
    $path = trim((string) $avatarPath);

    if ($path === '') {
        return null;
    }

    if (preg_match('/^https?:\/\//i', $path) === 1) {
        return $path;
    }

    return null;
}

function gestao_short_date(?string $ymd): string
{
    if (!$ymd) {
        return '';
    }

    $timestamp = strtotime($ymd);
    if (!$timestamp) {
        return '';
    }

    return date('d/m', $timestamp);
}

function gestao_weekday_label(DateTimeImmutable $day, int $offset): string
{
    if ($offset === 0) {
        return 'Hoje';
    }

    if ($offset === 1) {
        return 'Amanhã';
    }

    $weekdayMap = [
        0 => 'Domingo',
        1 => 'Segunda',
        2 => 'Terça',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sábado',
    ];

    return $weekdayMap[(int) $day->format('w')] ?? 'Dia';
}

function gestao_count_period_keys(int $months): array
{
    $keys = [];
    $cursor = new DateTimeImmutable('first day of this month');

    for ($index = 0; $index < $months; $index++) {
        $keys[] = $cursor->modify('-' . $index . ' month')->format('Y-m');
    }

    return array_reverse($keys);
}

function gestao_median(array $values): float
{
    if (empty($values)) {
        return 0.0;
    }

    sort($values, SORT_NUMERIC);
    $count = count($values);
    $mid = (int) floor($count / 2);

    if ($count % 2 === 1) {
        return (float) $values[$mid];
    }

    return ($values[$mid - 1] + $values[$mid]) / 2.0;
}

try {
    $allowedPeriods = [7, 14, 30, 90];
    $period = (int) ($_GET['period'] ?? 7);
    if (!in_array($period, $allowedPeriods, true)) {
        $period = 7;
    }

    $today = new DateTimeImmutable('today');
    $todayYmd = $today->format('Y-m-d');
    $tomorrowYmd = $today->modify('+1 day')->format('Y-m-d');
    $windowEnd = $today->modify('+' . max($period - 1, 0) . ' day')->format('Y-m-d');
    $sevenDaysEnd = $today->modify('+6 day')->format('Y-m-d');
    $monthlyKeys = gestao_count_period_keys(6);
    $last24HoursCutoff = time() - 86400;

    $conn = conectarBanco();

    $projects = gestao_fetch_all(
        $conn,
        "SELECT idobra, nomenclatura
         FROM obra
         WHERE status_obra = 0
         ORDER BY nomenclatura ASC"
    );

    $images = gestao_fetch_all(
        $conn,
        "SELECT ico.idimagens_cliente_obra,
                ico.obra_id,
                ico.imagem_nome,
                ico.substatus_id,
                COALESCE(ss.nome_substatus, '') AS nome_substatus,
                o.nomenclatura
         FROM imagens_cliente_obra ico
         JOIN obra o ON o.idobra = ico.obra_id
         LEFT JOIN substatus_imagem ss ON ss.id = ico.substatus_id
         WHERE o.status_obra = 0"
    );

    $tasks = gestao_fetch_all(
        $conn,
        "SELECT fi.idfuncao_imagem,
                fi.imagem_id,
                fi.funcao_id,
                fi.colaborador_id,
                fi.status,
                fi.prazo,
                f.nome_funcao,
                ico.imagem_nome,
                ico.substatus_id,
                COALESCE(ss.nome_substatus, '') AS nome_substatus,
                ico.obra_id,
                o.nomenclatura,
                c.nome_colaborador,
                COALESCE(c.imagem, iu.thumb) AS avatar
         FROM funcao_imagem fi
         JOIN funcao f ON f.idfuncao = fi.funcao_id
         JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
         JOIN obra o ON o.idobra = ico.obra_id
         LEFT JOIN substatus_imagem ss ON ss.id = ico.substatus_id
         LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
         LEFT JOIN usuario u ON u.idcolaborador = c.idcolaborador
         LEFT JOIN informacoes_usuario iu ON iu.usuario_id = u.idusuario
         WHERE o.status_obra = 0
           AND (fi.colaborador_id IS NULL OR fi.colaborador_id NOT IN (15, 21, 30))"
    );

    $taskDurationRows = gestao_fetch_all(
        $conn,
        "SELECT fi.idfuncao_imagem,
                f.nome_funcao,
                MIN(la.data) AS data_entrada,
                MIN(CASE WHEN LOWER(TRIM(la.status_novo)) IN ('em aprovação', 'finalizado', 'aprovado') THEN la.data END) AS data_saida
         FROM funcao_imagem fi
         JOIN funcao f ON f.idfuncao = fi.funcao_id
         JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
         JOIN obra o ON o.idobra = ico.obra_id
         JOIN log_alteracoes la ON la.funcao_imagem_id = fi.idfuncao_imagem
         WHERE o.status_obra = 0
           AND LOWER(TRIM(fi.status)) IN ('finalizado', 'aprovado')
           AND la.data >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
           AND (fi.colaborador_id IS NULL OR fi.colaborador_id NOT IN (15, 21, 30))
         GROUP BY fi.idfuncao_imagem, f.nome_funcao
         HAVING data_saida IS NOT NULL
            AND data_entrada < data_saida"
    );

    $deliveriesRaw = gestao_fetch_all(
        $conn,
        "SELECT e.id,
                e.obra_id,
                e.status_id,
                e.data_prevista,
                e.data_conclusao,
                e.status,
                e.observacoes,
                e.em_hold,
                e.motivo_hold,
                s.nome_status AS nome_etapa,
                o.nomenclatura,
                COUNT(DISTINCT ei.id) AS total_itens,
                COUNT(DISTINCT CASE WHEN ei.status NOT IN ('Pendente', 'Entrega pendente') THEN ei.id END) AS entregues_count,
                ROUND((COUNT(DISTINCT CASE WHEN ei.status NOT IN ('Pendente', 'Entrega pendente') THEN ei.id END) / GREATEST(COUNT(DISTINCT ei.id), 1)) * 100, 1) AS pct_entregue,
                COUNT(DISTINCT CASE
                    WHEN (ei.status = 'Entrega pendente' OR ss.nome_substatus IN ('RVW', 'DRV'))
                         AND ei.status NOT IN ('Entregue no prazo', 'Entregue com atraso', 'Entrega antecipada')
                    THEN ei.id
                END) AS ready_count,
                COUNT(DISTINCT CASE WHEN LOWER(TRIM(fi.status)) = 'em aprovação' THEN fi.imagem_id END) AS approval_count,
                COUNT(DISTINCT CASE WHEN LOWER(TRIM(fi.status)) IN ('ajuste', 'aprovado com ajustes') THEN fi.imagem_id END) AS revision_count,
                COUNT(DISTINCT CASE
                    WHEN fi.prazo IS NOT NULL
                         AND DATE(fi.prazo) < CURDATE()
                         AND LOWER(TRIM(fi.status)) NOT IN ('finalizado', 'aprovado')
                    THEN fi.idfuncao_imagem
                END) AS overdue_task_count
         FROM entregas e
         JOIN obra o ON o.idobra = e.obra_id
         JOIN status_imagem s ON s.idstatus = e.status_id
         LEFT JOIN entregas_itens ei ON ei.entrega_id = e.id
         LEFT JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = ei.imagem_id
         LEFT JOIN substatus_imagem ss ON ss.id = ico.substatus_id
         LEFT JOIN funcao_imagem fi ON fi.imagem_id = ico.idimagens_cliente_obra
         WHERE (e.arquivada IS NULL OR e.arquivada = 0)
           AND o.status_obra = 0
         GROUP BY e.id
         HAVING total_itens > 0
         ORDER BY e.data_prevista IS NULL, e.data_prevista ASC"
    );

    $recentTransitions = gestao_fetch_all(
        $conn,
        "SELECT la.funcao_imagem_id,
                la.status_novo,
                la.data AS event_time,
                fi.imagem_id,
                ico.imagem_nome,
                o.nomenclatura,
                f.nome_funcao
         FROM log_alteracoes la
         JOIN funcao_imagem fi ON fi.idfuncao_imagem = la.funcao_imagem_id
         JOIN funcao f ON f.idfuncao = fi.funcao_id
         JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
         JOIN obra o ON o.idobra = ico.obra_id
         WHERE o.status_obra = 0
           AND la.data >= DATE_SUB(NOW(), INTERVAL 30 DAY)
           AND LOWER(TRIM(la.status_novo)) IN ('não iniciado', 'em andamento', 'em aprovação')
         ORDER BY la.data DESC
         LIMIT 40"
    );

    $recentApprovals = gestao_fetch_all(
        $conn,
        "SELECT h.funcao_imagem_id,
                h.status_novo,
                h.data_aprovacao AS event_time,
                fi.imagem_id,
                ico.imagem_nome,
                o.nomenclatura,
                f.nome_funcao
         FROM historico_aprovacoes h
         JOIN funcao_imagem fi ON fi.idfuncao_imagem = h.funcao_imagem_id
         JOIN funcao f ON f.idfuncao = fi.funcao_id
         JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
         JOIN obra o ON o.idobra = ico.obra_id
         WHERE o.status_obra = 0
           AND h.data_aprovacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)
           AND LOWER(TRIM(h.status_novo)) IN ('aprovado', 'finalizado', 'aprovado com ajustes', 'ajuste')
         ORDER BY h.data_aprovacao DESC
         LIMIT 40"
    );

    $recentRenders = gestao_fetch_all(
        $conn,
        "SELECT lr.id,
                lr.status_novo,
                lr.data AS event_time,
                ra.imagem_id,
                ico.imagem_nome,
                o.nomenclatura
         FROM log_render lr
         JOIN render_alta ra ON ra.idrender_alta = lr.render_id
         JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = ra.imagem_id
         JOIN obra o ON o.idobra = ico.obra_id
         WHERE o.status_obra = 0
           AND lr.data >= DATE_SUB(NOW(), INTERVAL 30 DAY)
           AND LOWER(TRIM(lr.status_novo)) IN ('em andamento', 'aprovado')
         ORDER BY lr.data DESC
         LIMIT 30"
    );

    $monthlyCompletions = gestao_fetch_all(
        $conn,
        "SELECT fi.colaborador_id,
                YEAR(h.data_aprovacao) AS yr,
                MONTH(h.data_aprovacao) AS mo,
                COUNT(DISTINCT h.funcao_imagem_id) AS qtd
         FROM historico_aprovacoes h
         JOIN funcao_imagem fi ON fi.idfuncao_imagem = h.funcao_imagem_id
         JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
         JOIN obra o ON o.idobra = ico.obra_id
         WHERE o.status_obra = 0
           AND fi.colaborador_id IS NOT NULL
           AND fi.colaborador_id NOT IN (15, 21, 30)
           AND h.data_aprovacao >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
           AND LOWER(TRIM(h.status_novo)) IN ('aprovado', 'finalizado', 'aprovado com ajustes')
         GROUP BY fi.colaborador_id, YEAR(h.data_aprovacao), MONTH(h.data_aprovacao)"
    );

    $roles = gestao_fetch_all(
        $conn,
        "SELECT DISTINCT c.idcolaborador,
                c.nome_colaborador,
                COALESCE(c.imagem, iu.thumb) AS avatar,
                f.nome_funcao
         FROM funcao_colaborador fc
         JOIN colaborador c ON c.idcolaborador = fc.colaborador_id
         JOIN funcao f ON f.idfuncao = fc.funcao_id
         LEFT JOIN usuario u ON u.idcolaborador = c.idcolaborador
         LEFT JOIN informacoes_usuario iu ON iu.usuario_id = u.idusuario
         WHERE c.ativo = 1
           AND c.idcolaborador NOT IN (15, 21, 30)
         ORDER BY c.nome_colaborador ASC"
    );

    $conn->close();

    $activityProjectsToday = [];
    $startedImagesToday = [];
    $sentToApprovalToday = [];
    $feedbackToday = [];

    $imageStates = [];
    foreach ($images as $image) {
        $imageId = (int) $image['idimagens_cliente_obra'];
        $imageStates[$imageId] = [
            'obra_id'    => (int) $image['obra_id'],
            'project'    => $image['nomenclatura'],
            'image_name' => (string) ($image['imagem_nome'] ?? ''),
            'hold' => (int) ($image['substatus_id'] ?? 0) === 7 || gestao_normalize($image['nome_substatus']) === 'hold',
            'approval' => false,
            'review' => false,
            'production' => false,
            'overdue_tasks' => 0,
        ];
    }

    $canonicalFunctions = [
        'Caderno',
        'Filtro de assets',
        'Modelagem',
        'Composição',
        'Finalização',
        'Pós-produção',
        'Alteração',
    ];

    $stageStats = [];
    foreach ($canonicalFunctions as $fn) {
        $stageStats[$fn] = ['label' => $fn, 'to_start' => 0, 'in_progress' => 0, 'approval' => 0, 'overdue' => 0, 'total' => 0];
    }

    $stageTimeSamples = [];
    foreach ($canonicalFunctions as $fn) {
        $stageTimeSamples[$fn] = [];
    }
    foreach ($taskDurationRows as $durRow) {
        $funcLabel = gestao_map_to_canonical_function($durRow['nome_funcao']);
        if (!isset($stageTimeSamples[$funcLabel])) {
            continue;
        }
        $deltaDays = max(0.0, (strtotime((string) $durRow['data_saida']) - strtotime((string) $durRow['data_entrada'])) / 86400);
        $stageTimeSamples[$funcLabel][] = $deltaDays;
    }

    $overdueTasks = 0;
    $staleTasks = 0;
    $overdueTasksDetail  = [];
    $staleTasksDetail    = [];
    $approvalTasksDetail = [];
    $collaboratorsMap = [];
    foreach ($roles as $role) {
        $collaboratorId = (int) $role['idcolaborador'];
        if (!isset($collaboratorsMap[$collaboratorId])) {
            $collaboratorsMap[$collaboratorId] = [
                'id' => $collaboratorId,
                'name' => $role['nome_colaborador'],
                'avatar' => gestao_avatar_url($role['avatar']),
                'roles' => [],
                'stage_loads' => [],
                'weighted_load' => 0.0,
                'task_count' => 0,
                'overdue' => 0,
            ];
        }

        $collaboratorsMap[$collaboratorId]['roles'][] = $role['nome_funcao'];
    }

    foreach ($tasks as $task) {
        $taskId = (int) $task['idfuncao_imagem'];
        $imageId = (int) $task['imagem_id'];
        $status = gestao_normalize($task['status']);
        $stage = gestao_map_stage($task['status'], $task['nome_funcao']);
        $funcLabel = gestao_map_to_canonical_function($task['nome_funcao']);
        $isOpen = gestao_is_open_task($status);
        $dueDate = (string) ($task['prazo'] ?? '');
        $isOverdue = $isOpen && $dueDate !== '' && $dueDate < $todayYmd && $status == 'em andamento';

        if (!isset($imageStates[$imageId])) {
            $imageStates[$imageId] = [
                'obra_id'    => (int) ($task['obra_id'] ?? 0),
                'project'    => (string) ($task['nomenclatura'] ?? ''),
                'image_name' => (string) ($task['imagem_nome'] ?? ''),
                'hold' => false,
                'approval' => false,
                'review' => false,
                'production' => false,
                'overdue_tasks' => 0,
            ];
        }

        if ($isOpen) {
            if ($status === 'hold') {
                $imageStates[$imageId]['hold'] = true;
            } elseif ($status === 'em aprovação') {
                $imageStates[$imageId]['approval'] = true;
            } elseif (in_array($status, ['ajuste', 'aprovado com ajustes'], true)) {
                $imageStates[$imageId]['review'] = true;
            } else {
                $imageStates[$imageId]['production'] = true;
            }

            if ($isOverdue) {
                $imageStates[$imageId]['overdue_tasks']++;
                $overdueTasks++;
                $overdueTasksDetail[$funcLabel][] = [
                    'image'  => (string) ($task['imagem_nome'] ?? ''),
                    'person' => (string) ($task['nome_colaborador'] ?? ''),
                ];
            }

            if ($status === 'não iniciado' && $dueDate !== '' && $dueDate < $today->modify('-7 day')->format('Y-m-d')) {
                $staleTasks++;
                $staleTasksDetail[$funcLabel][] = [
                    'image'  => (string) ($task['imagem_nome'] ?? ''),
                    'person' => (string) ($task['nome_colaborador'] ?? ''),
                ];
            }

            $stageStats[$funcLabel]['total']++;

            if ($status === 'não iniciado') {
                $stageStats[$funcLabel]['to_start']++;
            } elseif ($status === 'em aprovação') {
                $stageStats[$funcLabel]['approval']++;
                $approvalTasksDetail[$funcLabel][] = [
                    'image'  => (string) ($task['imagem_nome'] ?? ''),
                    'person' => (string) ($task['nome_colaborador'] ?? ''),
                ];
            } else {
                $stageStats[$funcLabel]['in_progress']++;
            }

            if ($isOverdue) {
                $stageStats[$funcLabel]['overdue']++;
            }

            $collaboratorId = (int) ($task['colaborador_id'] ?? 0);
            if ($collaboratorId > 0 && !in_array($collaboratorId, GESTAO_EXCLUDED_COLLABORATORS, true)) {
                if (!isset($collaboratorsMap[$collaboratorId])) {
                    $collaboratorsMap[$collaboratorId] = [
                        'id' => $collaboratorId,
                        'name' => (string) ($task['nome_colaborador'] ?? 'Sem nome'),
                        'avatar' => gestao_avatar_url($task['avatar'] ?? null),
                        'roles' => [],
                        'stage_loads' => [],
                        'weighted_load' => 0.0,
                        'task_count' => 0,
                        'overdue' => 0,
                    ];
                }

                if (!isset($collaboratorsMap[$collaboratorId]['stage_loads'][$funcLabel])) {
                    $collaboratorsMap[$collaboratorId]['stage_loads'][$funcLabel] = 0;
                }

                $collaboratorsMap[$collaboratorId]['stage_loads'][$funcLabel]++;
                $collaboratorsMap[$collaboratorId]['task_count']++;

                $weight = 1.0;
                if ($status === 'não iniciado') {
                    $weight = 0.8;
                } elseif ($status === 'em aprovação') {
                    $weight = 0.6;
                } elseif (in_array($status, ['ajuste', 'aprovado com ajustes'], true)) {
                    $weight = 1.15;
                } elseif ($status === 'hold') {
                    $weight = 0.2;
                }

                $collaboratorsMap[$collaboratorId]['weighted_load'] += $weight;

                if ($isOverdue) {
                    $collaboratorsMap[$collaboratorId]['overdue']++;
                }
            }
        }
    }

    $holdImages = 0;
    $approvalImages = 0;
    $productionImages = 0;
    $holdProjects = [];
    $holdImagesDetail       = [];
    $productionImagesDetail = [];

    foreach ($imageStates as $imageState) {
        if ($imageState['hold']) {
            $holdImages++;
            $holdProjects[$imageState['obra_id']] = true;
            $holdImagesDetail[$imageState['project']][] = $imageState['image_name'];
            continue;
        }

        if ($imageState['approval']) {
            $approvalImages++;
            continue;
        }

        if ($imageState['review']) {
            continue;
        }

        if ($imageState['production']) {
            $productionImages++;
            $productionImagesDetail[$imageState['project']][] = $imageState['image_name'];
        }
    }

    $deliveries = [];
    $upcomingDeliveries = 0;
    $upcomingCritical = 0;
    $overdueDeliveries = 0;
    $overdueWithReadyItems = 0;
    $upcomingDeliveriesDetail = [];
    $overdueDeliveriesDetail  = [];
    $weekCards = [];

    for ($offset = 0; $offset < 7; $offset++) {
        $day = $today->modify('+' . $offset . ' day');
        $key = $day->format('Y-m-d');
        $weekCards[$key] = [
            'date' => $key,
            'label' => gestao_weekday_label($day, $offset),
            'date_label' => $day->format('d/m'),
            'deliveries' => 0,
            'deliveries_detail' => [],
            'tasks' => [],
            'tasks_detail' => [],
        ];
    }

    foreach ($deliveriesRaw as $delivery) {
        $delivery['kanban_status'] = gestao_classify_delivery($delivery, $todayYmd);
        $deliveries[] = $delivery;

        $dueDate = (string) ($delivery['data_prevista'] ?? '');
        $deliveryStatus = $delivery['kanban_status'];

        if ($deliveryStatus === 'atrasada') {
            $overdueDeliveries++;
            if ((int) ($delivery['ready_count'] ?? 0) > 0) {
                $overdueWithReadyItems++;
            }
            $overdueDeliveriesDetail[] = [
                'project' => (string) ($delivery['nomenclatura'] ?? ''),
                'status'  => (string) ($delivery['nome_etapa'] ?? ''),
                'pending' => max(0, (int) ($delivery['total_itens'] ?? 0) - (int) ($delivery['entregues_count'] ?? 0)),
            ];
        }

        if ($dueDate !== '' && $dueDate >= $todayYmd && $dueDate <= $windowEnd && !in_array($deliveryStatus, ['concluida', 'hold'], true)) {
            $upcomingDeliveries++;

            $daysUntilDue = (int) floor((strtotime($dueDate) - strtotime($todayYmd)) / 86400);
            if ($daysUntilDue <= 2 || (int) ($delivery['ready_count'] ?? 0) < max(1, (int) ceil(((int) ($delivery['total_itens'] ?? 0)) * 0.4))) {
                $upcomingCritical++;
            }
            $upcomingDeliveriesDetail[] = [
                'project' => (string) ($delivery['nomenclatura'] ?? ''),
                'status'  => (string) ($delivery['nome_etapa'] ?? ''),
                'pending' => max(0, (int) ($delivery['total_itens'] ?? 0) - (int) ($delivery['entregues_count'] ?? 0)),
            ];
        }

        if ($dueDate !== '' && isset($weekCards[$dueDate]) && !in_array($deliveryStatus, ['concluida', 'hold'], true)) {
            $weekCards[$dueDate]['deliveries']++;
            $weekCards[$dueDate]['deliveries_detail'][] = [
                'name'  => (string) ($delivery['nomenclatura'] ?? ''),
                'label' => (string) ($delivery['nome_etapa'] ?? ''),
            ];
        }
    }

    foreach ($tasks as $task) {
        $status = gestao_normalize($task['status']);
        if (!gestao_is_open_task($status)) {
            continue;
        }

        $dueDate = (string) ($task['prazo'] ?? '');
        if ($dueDate === '' || !isset($weekCards[$dueDate])) {
            continue;
        }

        $funcLabel = gestao_map_to_canonical_function((string) ($task['nome_funcao'] ?? ''));
        if (!isset($weekCards[$dueDate]['tasks'][$funcLabel])) {
            $weekCards[$dueDate]['tasks'][$funcLabel] = 0;
            $weekCards[$dueDate]['tasks_detail'][$funcLabel] = [];
        }
        $weekCards[$dueDate]['tasks'][$funcLabel]++;
        $weekCards[$dueDate]['tasks_detail'][$funcLabel][] = [
            'imagem'      => (string) ($task['imagem_nome'] ?? ''),
            'responsavel' => (string) ($task['nome_colaborador'] ?? ''),
        ];
    }

    $riskRadar = [];
    foreach ($deliveries as $delivery) {
        $deliveryStatus = $delivery['kanban_status'];
        $dueDate = (string) ($delivery['data_prevista'] ?? '');

        if ($deliveryStatus === 'concluida') {
            continue;
        }

        $daysUntilDue = $dueDate !== '' ? (int) floor((strtotime($dueDate) - strtotime($todayYmd)) / 86400) : 999;
        $score = 0;

        if ($deliveryStatus === 'hold') {
            $score += 55;
        }

        if ($deliveryStatus === 'atrasada') {
            $score += 45;
        }

        if ($daysUntilDue <= 2) {
            $score += 18;
        }

        $score += min(18, ((int) ($delivery['overdue_task_count'] ?? 0)) * 3);
        $score += min(14, ((int) ($delivery['revision_count'] ?? 0)) * 4);
        $score += min(10, ((int) ($delivery['approval_count'] ?? 0)) * 3);

        $totalItems = (int) ($delivery['total_itens'] ?? 0);
        $readyCount = (int) ($delivery['ready_count'] ?? 0);
        if ($totalItems > 0 && $readyCount < max(1, (int) ceil($totalItems * 0.35)) && $daysUntilDue <= 3) {
            $score += 12;
        }

        if ($score < 20) {
            continue;
        }

        $motivo = 'Baixa prontidão para o prazo';
        $holdReason = trim((string) ($delivery['motivo_hold'] ?? ''));
        $normalizedHoldReason = gestao_normalize($holdReason);

        if ($deliveryStatus === 'hold') {
            if (gestao_contains($normalizedHoldReason, 'arquitet')) {
                $motivo = 'Aguardando arquitetura';
            } elseif (gestao_contains($normalizedHoldReason, 'depend')) {
                $motivo = 'Dependência externa';
            } elseif ($holdReason !== '') {
                $motivo = 'HOLD: ' . $holdReason;
            } else {
                $motivo = 'Dependência externa';
            }
        } elseif ($deliveryStatus === 'atrasada') {
            $motivo = 'Entrega atrasada com ' . max(1, $totalItems - (int) ($delivery['entregues_count'] ?? 0)) . ' itens pendentes';
        } elseif ((int) ($delivery['revision_count'] ?? 0) >= 3) {
            $motivo = 'Muitas revisões em aberto';
        } elseif ((int) ($delivery['approval_count'] ?? 0) >= 2) {
            $motivo = 'Aprovação pendente';
        }

        $riskLevel = 'Baixo';
        $riskTone = 'info';
        if ($score >= 60) {
            $riskLevel = 'Alto';
            $riskTone = 'critical';
        } elseif ($score >= 35) {
            $riskLevel = 'Médio';
            $riskTone = 'warning';
        }

        $riskRadar[] = [
            'project' => $delivery['nomenclatura'],
            'delivery' => $delivery['nome_etapa'],
            'due_date' => $dueDate,
            'due_label' => $dueDate ? 'Prazo: ' . gestao_short_date($dueDate) : 'Sem prazo',
            'risk' => $riskLevel,
            'risk_tone' => $riskTone,
            'reason' => $motivo,
            'score' => $score,
        ];
    }

    usort($riskRadar, static function (array $left, array $right): int {
        return $right['score'] <=> $left['score'];
    });
    $riskRadar = array_slice($riskRadar, 0, 5);
    $criticalRiskCount = count(array_filter($riskRadar, static fn(array $row): bool => $row['risk'] === 'Alto'));

    $bottlenecks = [];
    foreach ($stageStats as $stage => $stats) {
        $total = max(1, $stats['total']);
        $avgDays = round(gestao_median($stageTimeSamples[$stage] ?? []), 1);
        $severity = 'healthy';
        if ($stats['overdue'] >= 4 || $avgDays >= 3) {
            $severity = 'critical';
        } elseif ($stats['overdue'] >= 2 || $avgDays >= 1.5) {
            $severity = 'warning';
        }

        $bottlenecks[] = [
            'function' => $stage,
            'to_start' => $stats['to_start'],
            'in_progress' => $stats['in_progress'],
            'approval' => $stats['approval'],
            'overdue' => $stats['overdue'],
            'avg_days' => number_format($avgDays, 1, ',', '.'),
            'load_percent' => min(100, (int) round((($stats['in_progress'] + $stats['approval']) / $total) * 100)),
            'severity' => $severity,
        ];
    }

    $monthlyByCollaborator = [];
    foreach ($monthlyCompletions as $row) {
        $key = sprintf('%04d-%02d', (int) $row['yr'], (int) $row['mo']);
        $monthlyByCollaborator[(int) $row['colaborador_id']][$key] = (int) $row['qtd'];
    }

    $capacityRows = [];
    foreach ($collaboratorsMap as $collaboratorId => $collaborator) {
        $stageLoads = $collaborator['stage_loads'];
        arsort($stageLoads);
        $dominantStage = key($stageLoads) ?: null;

        if (!$dominantStage) {
            $firstRole = $collaborator['roles'][0] ?? null;
            $dominantStage = gestao_map_primary_role($firstRole);
        }

        $history = [];
        foreach ($monthlyKeys as $monthKey) {
            $history[] = $monthlyByCollaborator[$collaboratorId][$monthKey] ?? 0;
        }

        $averageMonthly = count($history) > 0 ? array_sum($history) / count($history) : 0.0;
        $utilization = $averageMonthly > 0
            ? (int) round(($collaborator['weighted_load'] / $averageMonthly) * 100)
            : ($collaborator['task_count'] > 0 ? 120 : 0);

        $utilization = max(0, min($utilization, 160));

        $status = 'Equilibrado';
        $tone = 'balanced';
        if ($utilization < 70) {
            $status = 'Ocioso';
            $tone = 'idle';
        } elseif ($utilization > 105) {
            $status = 'Sobrecarregado';
            $tone = 'overloaded';
        }

        $capacityRows[] = [
            'name' => $collaborator['name'],
            'function' => $dominantStage,
            'capacity' => $utilization,
            'status' => $status,
            'status_tone' => $tone,
            'avatar' => $collaborator['avatar'],
            'avg_monthly' => number_format($averageMonthly, 1, ',', '.'),
            'task_count' => $collaborator['task_count'],
            'overdue' => $collaborator['overdue'],
        ];
    }

    $capacityByStatus = [
        'Sobrecarregado' => [],
        'Equilibrado' => [],
        'Ocioso' => [],
    ];

    foreach ($capacityRows as $row) {
        $capacityByStatus[$row['status']][] = $row;
    }

    foreach ($capacityByStatus as &$group) {
        usort($group, static function (array $left, array $right): int {
            return $right['capacity'] <=> $left['capacity'];
        });
    }
    unset($group);

    $capacity = array_merge(
        array_slice($capacityByStatus['Sobrecarregado'], 0, 2),
        array_slice($capacityByStatus['Equilibrado'], 0, 2),
        array_slice($capacityByStatus['Ocioso'], 0, 1)
    );

    if (count($capacity) < 5) {
        $alreadySelected = [];
        foreach ($capacity as $row) {
            $alreadySelected[$row['name']] = true;
        }

        usort($capacityRows, static function (array $left, array $right): int {
            return $right['capacity'] <=> $left['capacity'];
        });

        foreach ($capacityRows as $row) {
            if (isset($alreadySelected[$row['name']])) {
                continue;
            }

            $capacity[] = $row;
            $alreadySelected[$row['name']] = true;

            if (count($capacity) >= 5) {
                break;
            }
        }
    }

    usort($capacity, static function (array $left, array $right): int {
        return $right['capacity'] <=> $left['capacity'];
    });

    $recentEvents = [];
    foreach ($recentTransitions as $row) {
        $status = gestao_normalize($row['status_novo']);
        $stage = gestao_map_stage($row['status_novo'], $row['nome_funcao']);
        $eventType = '';
        $label = '';
        $description = '';
        $icon = 'fa-regular fa-circle';
        $tone = 'info';

        if ($status === 'não iniciado') {
            $eventType = 'taskCreated';
            $label = 'Tarefa criada';
            $description = 'Nova atividade planejada para ' . gestao_normalize($stage);
            $icon = 'fa-regular fa-square-plus';
            $tone = 'warning';
        } elseif ($status === 'em andamento') {
            $eventType = 'taskStarted';
            $label = 'Tarefa iniciada';
            $description = 'Etapa em andamento na operação';
            $icon = 'fa-regular fa-clock';
            $tone = 'success';
        } elseif ($status === 'em aprovação') {
            $eventType = 'imageSent';
            $label = 'Imagem enviada';
            $description = 'Arquivo enviado para aprovação do cliente';
            $icon = 'fa-regular fa-paper-plane';
            $tone = 'accent';
        }

        if ($eventType === '') {
            continue;
        }

        $timestamp = strtotime((string) $row['event_time']);
        if ($timestamp >= $last24HoursCutoff) {
            $activityProjectsToday[(string) $row['nomenclatura']] = true;
            if ($eventType === 'imageSent') {
                $sentToApprovalToday[(int) $row['imagem_id']] = true;
            }
        }

        $recentEvents[] = [
            'type' => $eventType,
            'label' => $label,
            'project' => $row['nomenclatura'],
            'description' => $description,
            'time' => $row['event_time'],
            'relative_time' => gestao_relative_time($row['event_time']),
            'icon' => $icon,
            'tone' => $tone,
        ];
    }

    foreach ($recentApprovals as $row) {
        $status = gestao_normalize($row['status_novo']);
        $stage = gestao_map_stage($row['status_novo'], $row['nome_funcao']);
        $eventType = '';
        $label = '';
        $description = '';
        $icon = 'fa-regular fa-circle-check';
        $tone = 'success';

        if (in_array($status, ['ajuste', 'aprovado com ajustes'], true)) {
            $eventType = 'feedbackReceived';
            $label = 'Feedback Interno';
            $description = 'Solicitação de revisão recebida';
            $icon = 'fa-regular fa-message';
            $tone = 'warning';
        } else {
            $eventType = 'taskCompleted';
            $label = 'Tarefa concluída';
            $description = 'Etapa finalizada na operação';
            $icon = 'fa-regular fa-circle-check';
            $tone = 'success';
        }

        $timestamp = strtotime((string) $row['event_time']);
        if ($timestamp >= $last24HoursCutoff && $eventType === 'feedbackReceived') {
            $feedbackToday[(int) $row['imagem_id']] = true;
        }

        $recentEvents[] = [
            'type' => $eventType,
            'label' => $label,
            'project' => $row['nomenclatura'],
            'description' => $description,
            'time' => $row['event_time'],
            'relative_time' => gestao_relative_time($row['event_time']),
            'icon' => $icon,
            'tone' => $tone,
        ];
    }

    foreach ($recentRenders as $row) {
        $statusNovo = gestao_normalize($row['status_novo']);
        if ($statusNovo === 'em andamento') {
            $eventType = 'renderStarted';
            $label = 'Render iniciado';
            $description = 'Arquivo entrou na fila principal de produção';
            $icon = 'fa-regular fa-circle-play';
            $tone = 'accent';
        } elseif ($statusNovo === 'aprovado') {
            $eventType = 'renderApproved';
            $label = 'Render aprovado';
            $description = 'Aprovação concluída sem ajustes';
            $icon = 'fa-regular fa-circle-check';
            $tone = 'success';
        } else {
            continue;
        }

        $timestamp = strtotime((string) $row['event_time']);
        if ($timestamp >= $last24HoursCutoff) {
            $activityProjectsToday[(string) $row['nomenclatura']] = true;
            if ($eventType === 'renderStarted') {
                $startedImagesToday[(int) $row['imagem_id']] = true;
            }
        }

        $recentEvents[] = [
            'type' => $eventType,
            'label' => $label,
            'project' => $row['nomenclatura'],
            'description' => $description,
            'time' => $row['event_time'],
            'relative_time' => gestao_relative_time($row['event_time']),
            'icon' => $icon,
            'tone' => $tone,
        ];
    }

    foreach ($deliveries as $delivery) {
        if (!$delivery['data_conclusao']) {
            continue;
        }

        $timestamp = strtotime((string) $delivery['data_conclusao']);
        if (!$timestamp || $timestamp < strtotime('-30 days')) {
            continue;
        }

        $recentEvents[] = [
            'type' => 'deliveryCompleted',
            'label' => 'Entrega realizada',
            'project' => $delivery['nomenclatura'],
            'description' => 'Entrega concluída para ' . $delivery['nome_etapa'],
            'time' => $delivery['data_conclusao'],
            'relative_time' => gestao_relative_time($delivery['data_conclusao']),
            'icon' => 'fa-regular fa-box',
            'tone' => 'warning',
        ];
    }

    usort($recentEvents, static function (array $left, array $right): int {
        return strtotime((string) $right['time']) <=> strtotime((string) $left['time']);
    });
    $recentEvents = array_slice($recentEvents, 0, 7);

    $footerTypeMap = [
        'renderStarted' => ['label' => 'Render iniciado', 'icon' => 'fa-regular fa-circle-play', 'tone' => 'accent'],
        'renderApproved' => ['label' => 'Render aprovado', 'icon' => 'fa-regular fa-circle-check', 'tone' => 'success'],
        'imageSent' => ['label' => 'Imagem enviada', 'icon' => 'fa-regular fa-paper-plane', 'tone' => 'accent'],
        'deliveryCompleted' => ['label' => 'Entrega realizada', 'icon' => 'fa-regular fa-box', 'tone' => 'warning'],
        'feedbackReceived' => ['label' => 'Feedback interno', 'icon' => 'fa-regular fa-message', 'tone' => 'warning'],
        'taskCreated' => ['label' => 'Tarefa criada', 'icon' => 'fa-regular fa-square-plus', 'tone' => 'warning'],
        'taskCompleted' => ['label' => 'Tarefa concluída', 'icon' => 'fa-regular fa-square-check', 'tone' => 'info'],
    ];

    $footerCounts = array_fill_keys(array_keys($footerTypeMap), 0);
    foreach ($recentEvents as $event) {
        $timestamp = strtotime((string) $event['time']);
        if ($timestamp >= $last24HoursCutoff && isset($footerCounts[$event['type']])) {
            $footerCounts[$event['type']]++;
        }
    }

    $footerStatuses = [];
    foreach ($footerTypeMap as $type => $meta) {
        $count = $footerCounts[$type];
        $footerStatuses[] = [
            'label' => $meta['label'],
            'icon' => $meta['icon'],
            'tone' => $meta['tone'],
            'count' => $count,
            'description' => $count > 0
                ? $count . ' nas últimas 24h'
                : 'Sem eventos nas últimas 24h',
        ];
    }

    $criticalAlerts = $criticalRiskCount + count(array_filter($capacity, static fn(array $row): bool => $row['status'] === 'Sobrecarregado'));

    $weekSchedule = [];
    foreach ($weekCards as $day) {
        $totalTasks = array_sum($day['tasks']);
        $workloadScore = ($day['deliveries'] * 2) + ($totalTasks * 1.2);
        $priority = 'Leve';
        $priorityTone = 'success';

        if ($workloadScore >= 10) {
            $priority = 'Crítico';
            $priorityTone = 'critical';
        } elseif ($workloadScore >= 7) {
            $priority = 'Alto';
            $priorityTone = 'warning';
        } elseif ($workloadScore >= 4) {
            $priority = 'Médio';
            $priorityTone = 'info';
        }

        $tasksSorted = $day['tasks'];
        arsort($tasksSorted);
        $tasksList = [];
        $tasksDetailList = [];
        foreach ($tasksSorted as $fn => $cnt) {
            $tasksList[]       = ['function' => $fn, 'count' => $cnt];
            $tasksDetailList[] = ['function' => $fn, 'items' => $day['tasks_detail'][$fn] ?? []];
        }

        $weekSchedule[] = [
            'label'             => $day['label'],
            'date'              => $day['date'],
            'date_label'        => $day['date_label'],
            'deliveries'        => $day['deliveries'],
            'deliveries_detail' => $day['deliveries_detail'],
            'tasks'             => $tasksList,
            'tasks_detail'      => $tasksDetailList,
            'priority'          => $priority,
            'priority_tone'     => $priorityTone,
        ];
    }

    // ── KPI detail payloads ──────────────────────────────────────────────
    $projectsDetail = array_values(array_map(
        static fn(array $p): array => ['name' => (string) $p['nomenclatura']],
        $projects
    ));

    $productionDetailList = [];
    foreach ($productionImagesDetail as $proj => $imgs) {
        $productionDetailList[] = ['project' => $proj, 'images' => array_values(array_unique($imgs))];
    }

    $holdDetailList = [];
    foreach ($holdImagesDetail as $proj => $imgs) {
        $holdDetailList[] = ['project' => $proj, 'images' => array_values(array_unique($imgs))];
    }

    $overdueTasksDetailList = [];
    foreach ($overdueTasksDetail as $fn => $items) {
        $overdueTasksDetailList[] = ['function' => $fn, 'items' => $items];
    }

    $staleTasksDetailList = [];
    foreach ($staleTasksDetail as $fn => $items) {
        $staleTasksDetailList[] = ['function' => $fn, 'items' => $items];
    }

    $approvalTasksDetailList = [];
    foreach ($approvalTasksDetail as $fn => $items) {
        $approvalTasksDetailList[] = ['function' => $fn, 'items' => $items];
    }
    // ────────────────────────────────────────────────────────────────────

    $kpis = [
        [
            'key'    => 'projetos_ativos',
            'label'  => 'Projetos ativos',
            'icon'   => 'fa-regular fa-folder-open',
            'value'  => count($projects),
            'tone'   => 'info',
            'trend'  => '+' . count($activityProjectsToday),
            'trend_label' => 'com movimentação hoje',
            'micro'  => 'Carteira ativa',
            'detail' => $projectsDetail,
        ],
        [
            'key'    => 'imagens_producao',
            'label'  => 'Imagens em produção',
            'icon'   => 'fa-regular fa-image',
            'value'  => $productionImages,
            'tone'   => 'success',
            'trend'  => '+' . count($startedImagesToday),
            'trend_label' => 'iniciadas hoje',
            'micro'  => 'Produção em andamento',
            'detail' => $productionDetailList,
        ],
        [
            'key'    => 'imagens_hold',
            'label'  => 'Imagens em HOLD',
            'icon'   => 'fa-regular fa-circle-pause',
            'value'  => $holdImages,
            'tone'   => 'warning',
            'trend'  => count($holdProjects),
            'trend_label' => 'projetos impactados',
            'micro'  => 'Itens fora de fluxo',
            'detail' => $holdDetailList,
        ],
        [
            'key'    => 'entregas_proximas',
            'label'  => 'Entregas próximas',
            'icon'   => 'fa-regular fa-calendar',
            'value'  => $upcomingDeliveries,
            'tone'   => 'info',
            'trend'  => $upcomingCritical,
            'trend_label' => 'críticas na janela',
            'micro'  => 'Janela de ' . $period . ' dias',
            'detail' => $upcomingDeliveriesDetail,
        ],
        [
            'key'    => 'entregas_atrasadas',
            'label'  => 'Entregas atrasadas',
            'icon'   => 'fa-regular fa-calendar-xmark',
            'value'  => $overdueDeliveries,
            'tone'   => 'critical',
            'trend'  => $overdueWithReadyItems,
            'trend_label' => 'parcialmente prontas',
            'micro'  => 'Demandam recuperação',
            'detail' => $overdueDeliveriesDetail,
        ],
        // [
        //     'key'    => 'alertas_criticos',
        //     'label'  => 'Alertas críticos',
        //     'icon'   => 'fa-regular fa-triangle-exclamation',
        //     'value'  => $criticalAlerts,
        //     'tone'   => 'critical',
        //     'trend'  => $criticalRiskCount,
        //     'trend_label' => 'projetos em alto risco',
        //     'micro'  => 'Prioridade executiva',
        //     'detail' => [],
        // ],
        [
            'key'    => 'tarefas_atrasadas',
            'label'  => 'Tarefas atrasadas',
            'icon'   => 'fa-regular fa-list-check',
            'value'  => $overdueTasks,
            'tone'   => 'critical',
            'trend'  => count($feedbackToday),
            'trend_label' => 'com revisão recente',
            'micro'  => 'Backlog vencido',
            'detail' => $overdueTasksDetailList,
        ],
        [
            'key'    => 'sem_iniciar',
            'label'  => 'Sem iniciar há muito tempo',
            'icon'   => 'fa-regular fa-hourglass-half',
            'value'  => $staleTasks,
            'tone'   => 'warning',
            'trend'  => $staleTasks,
            'trend_label' => 'acima de 7 dias',
            'micro'  => 'Acúmulo sem avanço',
            'detail' => $staleTasksDetailList,
        ],
        [
            'key'    => 'imagens_aprovacao',
            'label'  => 'Imagens para aprovação',
            'icon'   => 'fa-regular fa-circle-check',
            'value'  => $approvalImages,
            'tone'   => 'accent',
            'trend'  => '+' . count($sentToApprovalToday),
            'trend_label' => 'novos envios hoje',
            'micro'  => 'Fila do cliente',
            'detail' => $approvalTasksDetailList,
        ],
    ];

    gestao_json_exit([
        'period' => $period,
        'date_range' => [
            'start' => $todayYmd,
            'end' => $windowEnd,
            'week_end' => $sevenDaysEnd,
        ],
        'kpis' => $kpis,
        'risk_radar' => $riskRadar,
        'bottlenecks' => $bottlenecks,
        'capacity' => $capacity,
        'week_schedule' => $weekSchedule,
        'activities' => $recentEvents,
        'footer_statuses' => $footerStatuses,
        'updated_at' => date('c'),
    ]);
} catch (Throwable $exception) {
    gestao_json_exit([
        'error' => true,
        'message' => 'Falha ao montar dashboard de gestão.',
        'details' => $exception->getMessage(),
    ], 500);
}
