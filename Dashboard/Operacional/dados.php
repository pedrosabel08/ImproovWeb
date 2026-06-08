<?php
require_once __DIR__ . '/../../config/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../PaginaPrincipal/heatmap_helpers.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado'], JSON_UNESCAPED_UNICODE);
    exit();
}

if ((int) ($_SESSION['nivel_acesso'] ?? 0) !== 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso restrito ao perfil gerencial'], JSON_UNESCAPED_UNICODE);
    exit();
}

function operacional_table_exists(mysqli $conn, string $table): bool
{
    $table = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$table}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function operacional_fetch_function_catalog(mysqli $conn): array
{
    $result = $conn->query('SELECT idfuncao AS id, nome_funcao AS nome FROM funcao WHERE idfuncao <> 9 ORDER BY nome_funcao');
    $items = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $items[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'nome' => (string) $row['nome'],
            ];
        }
    }
    return $items;
}

function operacional_effective_function_expr(string $functionAlias, string $imageAlias): string
{
    return "CASE WHEN {$functionAlias}.funcao_id = 4 AND LOWER(TRIM({$imageAlias}.tipo_imagem)) = 'planta humanizada' THEN 7 ELSE {$functionAlias}.funcao_id END";
}

function operacional_build_effective_filter_sql(int $funcaoId, string $tipoImagem, string $effectiveExpr, string $imageAlias = 'ico'): array
{
    $where = '';
    $types = '';
    $values = [];

    if ($funcaoId > 0) {
        $where .= " AND {$effectiveExpr} = ?";
        $types .= 'i';
        $values[] = $funcaoId;
    }

    if ($tipoImagem !== '') {
        $where .= " AND {$imageAlias}.tipo_imagem = ?";
        $types .= 's';
        $values[] = $tipoImagem;
    }

    return [$where, $types, $values];
}

function operacional_run_grouped_count(mysqli $conn, string $sql, string $types = '', array $values = []): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$values);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $counts = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $counts[(int) $row['funcao_id']] = (int) $row['total'];
    }
    $stmt->close();

    return $counts;
}

function operacional_fetch_queue_counts(mysqli $conn, int $funcaoId, string $tipoImagem): array
{
    $planned = [];
    if (operacional_table_exists($conn, 'imagem_funcao_planejada')) {
        $effectivePlan = operacional_effective_function_expr('ifp', 'ico');
        [$wherePlan, $typesPlan, $valuesPlan] = operacional_build_effective_filter_sql($funcaoId, $tipoImagem, $effectivePlan);
        $planned = operacional_run_grouped_count(
            $conn,
            "SELECT {$effectivePlan} AS funcao_id, COUNT(*) AS total
             FROM imagem_funcao_planejada ifp
             INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = ifp.imagem_id
             INNER JOIN obra o ON o.idobra = ico.obra_id
             WHERE ifp.status = 'TODO'
               AND ifp.funcao_imagem_id IS NULL
               AND ifp.funcao_id NOT IN (9, 6)
               AND ico.obra_id != 74
                AND o.status_obra = 0
               
               {$wherePlan}
             GROUP BY {$effectivePlan}",
            $typesPlan,
            $valuesPlan
        );
    }

    $effectiveExec = operacional_effective_function_expr('fi', 'ico');
    [$whereExec, $typesExec, $valuesExec] = operacional_build_effective_filter_sql($funcaoId, $tipoImagem, $effectiveExec);
    $execution = operacional_run_grouped_count(
        $conn,
        "SELECT {$effectiveExec} AS funcao_id, COUNT(*) AS total
         FROM funcao_imagem fi
         INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
         INNER JOIN obra o ON o.idobra = ico.obra_id
         WHERE LOWER(TRIM(fi.status)) IN ('não iniciado', 'nao iniciado')
           AND ico.obra_id != 74
          AND fi.funcao_id NOT IN (9, 6)
           AND (fi.colaborador_id IS NULL OR fi.colaborador_id NOT IN (21, 15))
           AND NOT (fi.funcao_id = 4 AND fi.colaborador_id IN (7, 34))
           AND o.status_obra = 0
           {$whereExec}
         GROUP BY {$effectiveExec}",
        $typesExec,
        $valuesExec
    );

    return [
        'planned' => $planned,
        'execution' => $execution,
    ];
}

function operacional_fetch_queue_details(mysqli $conn, int $funcaoId, string $tipoImagem): array
{
    $details = [];
    $effectiveExec = operacional_effective_function_expr('fi', 'ico');
    [$whereExec, $typesExec, $valuesExec] = operacional_build_effective_filter_sql($funcaoId, $tipoImagem, $effectiveExec);

    $sqlExec = "
        SELECT
            {$effectiveExec} AS funcao_id,
            'execution' AS origem,
            fi.idfuncao_imagem AS item_id,
            ico.idimagens_cliente_obra AS imagem_id,
            ico.imagem_nome,
            ico.tipo_imagem,
            o.idobra AS obra_id,
            COALESCE(NULLIF(TRIM(o.nomenclatura), ''), NULLIF(TRIM(o.nome_obra), ''), CONCAT('Obra ', o.idobra)) AS obra_nome,
            COALESCE(NULLIF(TRIM(c.nome_colaborador), ''), 'Sem colaborador') AS responsavel,
            (
                SELECT MIN(la.data)
                FROM log_alteracoes la
                WHERE la.funcao_imagem_id = fi.idfuncao_imagem
                  AND la.status_anterior IS NULL
                  AND LOWER(TRIM(la.status_novo)) IN ('não iniciado', 'nao iniciado')
            ) AS inicio_em
        FROM funcao_imagem fi
        INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
        INNER JOIN obra o ON o.idobra = ico.obra_id
        LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
        WHERE LOWER(TRIM(fi.status)) IN ('não iniciado', 'nao iniciado')
          AND ico.obra_id != 74
          AND fi.funcao_id NOT IN (9, 6)
          AND o.status_obra = 0
          AND (fi.colaborador_id IS NULL OR fi.colaborador_id NOT IN (21, 15))
          AND NOT (fi.funcao_id = 4 AND fi.colaborador_id IN (7, 34))
          {$whereExec}
        ORDER BY obra_nome ASC, ico.imagem_nome ASC
    ";

    $stmtExec = $conn->prepare($sqlExec);
    if ($stmtExec) {
        if ($typesExec !== '') {
            $stmtExec->bind_param($typesExec, ...$valuesExec);
        }
        $stmtExec->execute();
        $resultExec = $stmtExec->get_result();
        while ($resultExec && ($row = $resultExec->fetch_assoc())) {
            operacional_append_queue_detail($details, $row);
        }
        $stmtExec->close();
    }

    if (operacional_table_exists($conn, 'imagem_funcao_planejada')) {
        $effectivePlan = operacional_effective_function_expr('ifp', 'ico');
        [$wherePlan, $typesPlan, $valuesPlan] = operacional_build_effective_filter_sql($funcaoId, $tipoImagem, $effectivePlan);

        $sqlPlan = "
            SELECT
                {$effectivePlan} AS funcao_id,
                'planned' AS origem,
                ifp.idimagem_funcao_planejada AS item_id,
                ico.idimagens_cliente_obra AS imagem_id,
                ico.imagem_nome,
                ico.tipo_imagem,
                o.idobra AS obra_id,
                COALESCE(NULLIF(TRIM(o.nomenclatura), ''), NULLIF(TRIM(o.nome_obra), ''), CONCAT('Obra ', o.idobra)) AS obra_nome,
                'Sem colaborador' AS responsavel,
                COALESCE(
                    (
                        SELECT MIN(ifph.created_at)
                        FROM imagem_funcao_planejada_historico ifph
                        WHERE ifph.imagem_funcao_planejada_id = ifp.idimagem_funcao_planejada
                    ),
                    ifp.created_at
                ) AS inicio_em
            FROM imagem_funcao_planejada ifp
            INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = ifp.imagem_id
            INNER JOIN obra o ON o.idobra = ico.obra_id
            WHERE ifp.status = 'TODO'
              AND ifp.funcao_imagem_id IS NULL
              AND ifp.funcao_id NOT IN (9, 6)
              AND ico.obra_id != 74
              AND o.status_obra = 0
              {$wherePlan}
            ORDER BY obra_nome ASC, ico.imagem_nome ASC
        ";

        $stmtPlan = $conn->prepare($sqlPlan);
        if ($stmtPlan) {
            if ($typesPlan !== '') {
                $stmtPlan->bind_param($typesPlan, ...$valuesPlan);
            }
            $stmtPlan->execute();
            $resultPlan = $stmtPlan->get_result();
            while ($resultPlan && ($row = $resultPlan->fetch_assoc())) {
                operacional_append_queue_detail($details, $row);
            }
            $stmtPlan->close();
        }
    }

    foreach ($details as &$functionGroup) {
        ksort($functionGroup['obras'], SORT_NATURAL | SORT_FLAG_CASE);
        $functionGroup['obras'] = array_values($functionGroup['obras']);
    }
    unset($functionGroup);

    return $details;
}

function operacional_append_queue_detail(array &$details, array $row): void
{
    $functionId = (int) ($row['funcao_id'] ?? 0);
    $obraId = (int) ($row['obra_id'] ?? 0);
    $obraNome = (string) ($row['obra_nome'] ?? ('Obra ' . $obraId));
    $inicioEm = $row['inicio_em'] ?? null;
    $dias = $inicioEm ? max(0, (int) floor((time() - strtotime((string) $inicioEm)) / 86400)) : null;

    if (!isset($details[$functionId])) {
        $details[$functionId] = ['obras' => []];
    }
    if (!isset($details[$functionId]['obras'][$obraNome])) {
        $details[$functionId]['obras'][$obraNome] = [
            'obra_id' => $obraId,
            'obra_nome' => $obraNome,
            'itens' => [],
        ];
    }

    $details[$functionId]['obras'][$obraNome]['itens'][] = [
        'origem' => (string) ($row['origem'] ?? ''),
        'item_id' => (int) ($row['item_id'] ?? 0),
        'imagem_id' => (int) ($row['imagem_id'] ?? 0),
        'imagem_nome' => (string) ($row['imagem_nome'] ?? ''),
        'tipo_imagem' => (string) ($row['tipo_imagem'] ?? ''),
        'responsavel' => (string) ($row['responsavel'] ?? 'Sem colaborador'),
        'inicio_em' => $inicioEm,
        'dias_na_fila' => $dias,
        'tempo_label' => $dias === null ? '-' : ($dias . ' ' . ($dias === 1 ? 'dia' : 'dias')),
    ];
}

function operacional_fetch_consumption_dataset(mysqli $conn, array $filters, int $functionId): array
{
    $tipoImagem = (string) ($filters['tipo_imagem'] ?? '');
    $tipoNormalizado = strtolower(trim($tipoImagem));

    if ($functionId === 7) {
        if ($tipoNormalizado !== '' && $tipoNormalizado !== 'planta humanizada') {
            return [
                'por_dia' => [],
                'media_diaria' => 0,
                't1' => 1,
                't2' => 2,
                'mes' => $filters['mes'],
                'ano' => $filters['ano'],
            ];
        }

        return heatmap_fetch_dataset($conn, $filters['mes'], $filters['ano'], 4, 'Planta Humanizada');
    }

    if ($functionId === 4) {
        if ($tipoNormalizado === 'planta humanizada') {
            return [
                'por_dia' => [],
                'media_diaria' => 0,
                't1' => 1,
                't2' => 2,
                'mes' => $filters['mes'],
                'ano' => $filters['ano'],
            ];
        }

        if ($tipoNormalizado === '') {
            $allFinalization = heatmap_fetch_dataset($conn, $filters['mes'], $filters['ano'], 4, '');
            $plantFinalization = heatmap_fetch_dataset($conn, $filters['mes'], $filters['ano'], 4, 'Planta Humanizada');
            $porDia = $allFinalization['por_dia'];
            foreach ($plantFinalization['por_dia'] as $day => $total) {
                $porDia[$day] = max(0, (int) ($porDia[$day] ?? 0) - (int) $total);
                if ($porDia[$day] === 0) {
                    unset($porDia[$day]);
                }
            }

            $mediaDiaria = max(0, round((float) $allFinalization['media_diaria'] - (float) $plantFinalization['media_diaria'], 2));
            $t1 = max(1, (int) floor($mediaDiaria));
            $t2 = max($t1 + 1, (int) floor($mediaDiaria * 2));

            return [
                'por_dia' => $porDia,
                'media_diaria' => $mediaDiaria,
                't1' => $t1,
                't2' => $t2,
                'mes' => $filters['mes'],
                'ano' => $filters['ano'],
            ];
        }
    }

    return heatmap_fetch_dataset($conn, $filters['mes'], $filters['ano'], $functionId, $tipoImagem);
}

function operacional_fetch_monthly_goals(mysqli $conn, int $mes, int $ano, int $funcaoId): array
{
    if (!operacional_table_exists($conn, 'metas')) {
        return [];
    }

    $where = '';
    $types = 'ii';
    $values = [$mes, $ano];
    if ($funcaoId > 0) {
        $where = ' AND funcao_id = ?';
        $types .= 'i';
        $values[] = $funcaoId;
    }

    $stmt = $conn->prepare(
        "SELECT funcao_id, quantidade_meta
         FROM metas
         WHERE mes = ? AND ano = ?
         {$where}"
    );
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $result = $stmt->get_result();
    $goals = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $goals[(int) $row['funcao_id']] = (int) $row['quantidade_meta'];
    }
    $stmt->close();

    return $goals;
}

function operacional_status_from_supply(?float $supplyPercent): array
{
    if ($supplyPercent === null) {
        return ['key' => 'sem_meta', 'label' => 'Sem meta'];
    }
    if ($supplyPercent < 40) {
        return ['key' => 'critico', 'label' => 'Crítico'];
    }
    if ($supplyPercent < 80) {
        return ['key' => 'atencao', 'label' => 'Atenção'];
    }
    if ($supplyPercent <= 130) {
        return ['key' => 'saudavel', 'label' => 'Saudável'];
    }
    return ['key' => 'excesso', 'label' => 'Excesso'];
}

function operacional_build_alerts(array $rows): array
{
    $alerts = [];
    foreach ($rows as $row) {
        $status = $row['status'];
        if ($status === 'critico') {
            $alerts[] = [
                'tone' => 'critico',
                'title' => $row['nome'] . ' abaixo do mínimo ideal',
                'body' => 'Possui apenas ' . $row['abastecimento_label'] . ' do abastecimento esperado.',
            ];
        } elseif ($status === 'atencao') {
            $alerts[] = [
                'tone' => 'atencao',
                'title' => $row['nome'] . ' em nível de atenção',
                'body' => 'Está abaixo da faixa saudável. Monitore novas entradas.',
            ];
        } elseif ($status === 'saudavel') {
            $alerts[] = [
                'tone' => 'saudavel',
                'title' => $row['nome'] . ' possui abastecimento adequado',
                'body' => 'Demanda atual dentro da faixa operacional saudável.',
            ];
        } elseif ($status === 'excesso') {
            $alerts[] = [
                'tone' => 'excesso',
                'title' => $row['nome'] . ' com fila excessiva',
                'body' => 'Abastecimento de ' . $row['abastecimento_label'] . '. Avalie redistribuição de demanda.',
            ];
        } elseif ($status === 'sem_meta' && $row['fila_total'] > 0) {
            $alerts[] = [
                'tone' => 'sem_meta',
                'title' => $row['nome'] . ' sem meta mensal',
                'body' => 'Há fila atual, mas não existe meta nem histórico suficiente para calcular abastecimento.',
            ];
        }
    }

    if (!$alerts) {
        $alerts[] = [
            'tone' => 'saudavel',
            'title' => 'Abastecimento operacional estável',
            'body' => 'Nenhuma função exige ação imediata pelos limites de abastecimento.',
        ];
    }

    return array_slice($alerts, 0, 6);
}

$filters = heatmap_normalize_filters($_GET);
$functionCatalog = operacional_fetch_function_catalog($conn);
$filterOptions = heatmap_fetch_filter_options($conn);
$filterOptions['funcoes'] = array_values(array_filter($filterOptions['funcoes'], function (array $function): bool {
    return (int) ($function['id'] ?? 0) !== 9;
}));
$queueCounts = operacional_fetch_queue_counts($conn, $filters['funcao_id'], $filters['tipo_imagem']);
$queueDetails = operacional_fetch_queue_details($conn, $filters['funcao_id'], $filters['tipo_imagem']);
$monthlyGoals = operacional_fetch_monthly_goals($conn, $filters['mes'], $filters['ano'], $filters['funcao_id']);
$trendDataset = $filters['funcao_id'] > 0
    ? operacional_fetch_consumption_dataset($conn, $filters, $filters['funcao_id'])
    : heatmap_fetch_dataset($conn, $filters['mes'], $filters['ano'], 0, $filters['tipo_imagem']);

$functionIds = [];
foreach ($filterOptions['funcoes'] as $function) {
    $functionIds[(int) $function['id']] = true;
}
foreach (array_keys($queueCounts['planned']) as $functionId) {
    $functionIds[(int) $functionId] = true;
}
foreach (array_keys($queueCounts['execution']) as $functionId) {
    $functionIds[(int) $functionId] = true;
}
if ($filters['funcao_id'] > 0) {
    $functionIds = [$filters['funcao_id'] => true];
}

$rows = [];
$supplySum = 0.0;
$supplyCount = 0;
$totalQueue = 0;
$totalGoal = 0;

foreach (array_keys($functionIds) as $functionId) {
    if (!isset($functionCatalog[$functionId])) {
        continue;
    }

    $planned = $queueCounts['planned'][$functionId] ?? 0;
    $execution = $queueCounts['execution'][$functionId] ?? 0;
    $total = $planned + $execution;
    $consumptionData = operacional_fetch_consumption_dataset($conn, $filters, $functionId);
    $dailyConsumption = (float) $consumptionData['media_diaria'];
    $historicalGoal = $dailyConsumption > 0 ? max(1, (int) round($dailyConsumption * 20)) : 0;
    $monthlyGoal = $monthlyGoals[$functionId] ?? $historicalGoal;
    $supplyPercent = $monthlyGoal > 0 ? round(($total / $monthlyGoal) * 100, 1) : null;
    $coverage = $dailyConsumption > 0 ? round($total / $dailyConsumption, 2) : null;
    $status = operacional_status_from_supply($supplyPercent);

    if ($supplyPercent !== null) {
        $supplySum += $supplyPercent;
        $supplyCount++;
    }
    $totalQueue += $total;
    $totalGoal += $monthlyGoal;

    $rows[] = [
        'id' => $functionId,
        'nome' => $functionCatalog[$functionId]['nome'],
        'planejada' => $planned,
        'nao_iniciado' => $execution,
        'fila_total' => $total,
        'meta_mensal' => $monthlyGoal,
        'meta_origem' => isset($monthlyGoals[$functionId]) ? 'metas' : 'historico',
        'abastecimento' => $supplyPercent,
        'abastecimento_label' => $supplyPercent === null ? '-' : number_format($supplyPercent, 0, ',', '.') . '%',
        'consumo_diario' => $dailyConsumption,
        'cobertura' => $coverage,
        'cobertura_label' => $coverage === null ? '-' : number_format($coverage, 1, ',', '') . ' ' . ($coverage == 1.0 ? 'dia' : 'dias'),
        'status' => $status['key'],
        'status_label' => $status['label'],
        'detalhes' => $queueDetails[$functionId] ?? ['obras' => []],
    ];
}

usort($rows, function (array $a, array $b): int {
    if ($a['status'] === 'sem_meta' && $b['status'] !== 'sem_meta') {
        return 1;
    }
    if ($b['status'] === 'sem_meta' && $a['status'] !== 'sem_meta') {
        return -1;
    }
    return ($a['abastecimento'] ?? 9999) <=> ($b['abastecimento'] ?? 9999);
});

$rankingSupply = array_values(array_filter($rows, fn(array $row): bool => $row['abastecimento'] !== null));
usort($rankingSupply, fn(array $a, array $b): int => $a['abastecimento'] <=> $b['abastecimento']);

$rankingQueue = $rows;
usort($rankingQueue, fn(array $a, array $b): int => $b['fila_total'] <=> $a['fila_total']);

$kpis = [
    'criticas' => count(array_filter($rows, fn(array $row): bool => $row['status'] === 'critico')),
    'atencao' => count(array_filter($rows, fn(array $row): bool => $row['status'] === 'atencao')),
    'saudaveis' => count(array_filter($rows, fn(array $row): bool => $row['status'] === 'saudavel')),
    'excesso' => count(array_filter($rows, fn(array $row): bool => $row['status'] === 'excesso')),
    'total_fila' => $totalQueue,
    'meta_total' => $totalGoal,
    'abastecimento_medio' => $supplyCount > 0 ? round($supplySum / $supplyCount, 1) : null,
];

$response = [
    'filters' => $filters,
    'options' => $filterOptions,
    'kpis' => $kpis,
    'funcoes' => $rows,
    'rankings' => [
        'menor_abastecimento' => array_slice($rankingSupply, 0, 5),
        'maior_fila' => array_slice($rankingQueue, 0, 5),
    ],
    'alertas' => operacional_build_alerts($rows),
    'tendencia' => [
        'por_dia' => $trendDataset['por_dia'],
        'media_diaria' => $trendDataset['media_diaria'],
    ],
    'updated_at' => date('c'),
];

$conn->close();

echo json_encode($response, JSON_UNESCAPED_UNICODE);
