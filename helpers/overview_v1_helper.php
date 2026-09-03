<?php

/**
 * Contratos enxutos da Visão Geral V1.
 *
 * Esta camada não substitui Kanban, planejamento ou capacidade. Ela apenas
 * transforma os dados canônicos desses módulos em itens de decisão.
 */

require_once __DIR__ . '/dashboard_colaborador_helper.php';
require_once __DIR__ . '/pendencias_operacionais_helper.php';
require_once __DIR__ . '/planejamento_alocacao_helper.php';
require_once __DIR__ . '/planejamento_capacidade_global_helper.php';
require_once __DIR__ . '/planejamento_fila_confirmada_helper.php';

function flow_overview_v1_inicio_semana(?string $data = null): string
{
    $timestamp = strtotime(($data ?: date('Y-m-d')) . ' 12:00:00');
    return date('Y-m-d', strtotime('-' . ((int) date('N', $timestamp) - 1) . ' days', $timestamp));
}

function flow_overview_v1_fim_semana(?string $data = null): string
{
    return date('Y-m-d', strtotime(flow_overview_v1_inicio_semana($data) . ' +4 days'));
}

function flow_overview_v1_rotulo_prazo(?string $data, string $statusTemporal = ''): string
{
    if (!$data) {
        return 'Sem prazo definido';
    }
    if ($statusTemporal === 'PRAZO_HOJE') {
        return 'Prazo hoje';
    }
    if ($statusTemporal === 'PRAZO_PROXIMO') {
        return 'Prazo amanhã';
    }
    if ($statusTemporal === 'ATRASADO') {
        return 'Prazo ultrapassado';
    }
    return 'Prazo ' . date('d/m', strtotime($data . ' 12:00:00'));
}

function flow_overview_v1_thumbnail(array $original): ?string
{
    $arquivo = trim((string) ($original['ultima_imagem'] ?? ''));
    if ($arquivo === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $arquivo)) {
        return $arquivo;
    }
    return 'thumb.php?path=' . rawurlencode($arquivo) . '&w=360&q=70';
}

function flow_overview_v1_excecao_tarefa(array $tarefa, array $original = []): ?array
{
    $flowBlock = (array) ($tarefa['flow_block'] ?? []);
    if (!empty($flowBlock)) {
        $critico = !empty($flowBlock['cobranca_atrasada'])
            || strtoupper((string) ($original['flow_block_urgencia_principal'] ?? '')) === 'CRITICA';
        return [
            'state' => 'flow_block',
            'severity' => $critico ? 'critical' : 'high',
            'label' => $critico ? 'Flow Block exige ação' : 'Bloqueada por Flow Block',
        ];
    }
    if (($tarefa['bloqueio']['tipo'] ?? '') === 'hold') {
        return ['state' => 'hold', 'severity' => 'high', 'label' => 'Em HOLD'];
    }
    if (($tarefa['status_temporal'] ?? '') === 'ATRASADO') {
        return ['state' => 'overdue', 'severity' => 'high', 'label' => 'Prazo ultrapassado'];
    }
    if (($tarefa['bloqueio']['tipo'] ?? '') === 'requisito') {
        return ['state' => 'requirement', 'severity' => 'warning', 'label' => 'Aguardando requisito'];
    }
    if (($tarefa['status_temporal'] ?? '') === 'PRAZO_HOJE') {
        return ['state' => 'due_today', 'severity' => 'high', 'label' => 'Prazo hoje'];
    }
    if (($tarefa['status_temporal'] ?? '') === 'PRAZO_PROXIMO') {
        return ['state' => 'due_soon', 'severity' => 'warning', 'label' => 'Prazo amanhã'];
    }
    return null;
}

/** A timeline já é calculada pelo planejamento; aqui apenas a reduzimos para o card. */
function flow_overview_v1_timeline(array $original): array
{
    $timeline = array_values(array_filter((array) (($original['planejamento']['timeline'] ?? [])), static fn ($item): bool => is_array($item)));
    if (count($timeline) > 5) {
        $atual = array_values(array_filter($timeline, static fn (array $item): bool => ($item['estado'] ?? '') === 'ATUAL'));
        $timeline = array_values(array_unique(array_merge(array_slice($timeline, 0, 2), array_slice($atual, 0, 1), array_slice($timeline, -2)), SORT_REGULAR));
    }
    return array_map(static fn (array $item): array => [
        'label' => (string) ($item['nome'] ?? $item['codigo'] ?? 'Etapa'),
        'state' => strtolower((string) ($item['estado'] ?? 'FUTURA')),
    ], $timeline);
}

function flow_overview_v1_item_tarefa(array $tarefa, array $original = []): array
{
    $excecao = flow_overview_v1_excecao_tarefa($tarefa, $original);
    return [
        'task_id' => (int) ($tarefa['id'] ?? 0),
        'image_id' => (int) ($tarefa['imagem_id'] ?? 0),
        'project' => (string) ($tarefa['obra'] ?? ''),
        'image_name' => (string) ($tarefa['imagem'] ?? ''),
        'function_name' => (string) ($tarefa['funcao'] ?? ''),
        'substatus' => (string) ($tarefa['substatus'] ?? ''),
        'status' => (string) ($tarefa['status'] ?? ''),
        'thumbnail_url' => flow_overview_v1_thumbnail($original),
        'deadline' => [
            'date' => $tarefa['prazo'] ?? null,
            'label' => flow_overview_v1_rotulo_prazo($tarefa['prazo'] ?? null, (string) ($tarefa['status_temporal'] ?? '')),
            'state' => strtolower((string) ($tarefa['status_temporal'] ?? 'SEM_PRAZO')),
            'source' => (string) ($tarefa['prazo_origem'] ?? 'indisponivel'),
        ],
        'exception' => $excecao,
        'timeline' => flow_overview_v1_timeline($original),
        'action' => ['type' => 'open_task'],
    ];
}

function flow_overview_v1_ordenar_proximas(array &$tarefas, string $hoje): void
{
    usort($tarefas, static function (array $a, array $b) use ($hoje): int {
        $janelaA = !empty($a['janela_inicio']) && $a['janela_inicio'] <= $hoje ? 0 : 1;
        $janelaB = !empty($b['janela_inicio']) && $b['janela_inicio'] <= $hoje ? 0 : 1;
        return $janelaA <=> $janelaB
            ?: (($a['fila_posicao'] ?? PHP_INT_MAX) <=> ($b['fila_posicao'] ?? PHP_INT_MAX))
            ?: ((int) ($a['prioridade_manual'] ?? 3) <=> (int) ($b['prioridade_manual'] ?? 3))
            ?: strcmp((string) ($a['prazo'] ?? '9999-12-31'), (string) ($b['prazo'] ?? '9999-12-31'))
            ?: ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
    });
}

function flow_overview_v1_severidade_pendencia(array $item): string
{
    return dashboard_colaborador_severidade_pendencia($item) === 'critical' ? 'critical' : 'warning';
}

/**
 * Converte exclusivamente a fonte canônica de pendências operacionais em uma
 * fila curta de decisão. Nenhuma consulta paralela de alertas é feita aqui.
 */
function flow_overview_v1_atencao_pendencias(array $fonte, ?int $colaboradorId = null, int $limite = 6): array
{
    $itens = [];
    foreach ($fonte as $entrada) {
        if (isset($entrada['items']) && is_array($entrada['items'])) {
            foreach ($entrada['items'] as $item) {
                $item['module_name'] = (string) ($entrada['name'] ?? $entrada['key'] ?? 'Operação');
                $itens[] = $item;
            }
        } else {
            $itens[] = $entrada;
        }
    }

    $resultado = [];
    foreach ($itens as $item) {
        $responsavelId = (int) ($item['responsavel_id'] ?? 0);
        if ($colaboradorId !== null && $responsavelId !== $colaboradorId) {
            continue;
        }
        $sourceType = (string) ($item['source_type'] ?? 'pending');
        $sourceId = (int) ($item['source_id'] ?? 0);
        $obraId = (int) ($item['obra_id'] ?? 0);
        $obra = trim((string) ($item['obra_nome'] ?? ''));
        $responsavel = trim((string) ($item['responsavel_nome'] ?? ''));
        $tituloBase = trim((string) ($item['title'] ?? 'Pendência operacional'));
        $contexto = $colaboradorId === null && $responsavel !== '' ? $responsavel : $obra;
        $titulo = $contexto !== '' && stripos($tituloBase, $contexto) === false
            ? $contexto . ' · ' . $tituloBase
            : $tituloBase;
        $detalhe = trim((string) ($item['module_name'] ?? '') . (($item['subtitle'] ?? '') !== '' ? ' · ' . (string) $item['subtitle'] : ''));
        $actionUrl = trim((string) ($item['action_url'] ?? ''));
        if ($actionUrl === 'Dashboard/obra.php' && $obraId > 0) {
            $actionUrl .= '?obra_id=' . $obraId;
        }
        $actionType = $sourceType === 'flow_review' && $sourceId > 0
            ? 'open_task'
            : ($actionUrl !== '' ? 'open_pending' : ($obraId > 0 ? 'open_project' : 'open_kanban'));
        $resultado[] = [
            'entity_type' => 'pending',
            'entity_id' => $sourceId,
            'assignee_id' => $responsavelId,
            'project_id' => $obraId,
            'severity' => flow_overview_v1_severidade_pendencia($item),
            'type' => $sourceType,
            'title' => $titulo,
            'detail' => $detalhe !== '' ? $detalhe : (string) ($item['sla_label'] ?? 'Ação necessária.'),
            'action' => [
                'type' => $actionType,
                'url' => $actionUrl,
            ],
            'sla_status' => (string) ($item['sla_status'] ?? 'dentro'),
            'operational_hold' => !empty($item['operational_hold']),
        ];
    }

    $peso = ['critical' => 0, 'high' => 1, 'warning' => 2];
    usort($resultado, static function (array $a, array $b) use ($peso): int {
        return ($peso[$a['severity']] ?? 9) <=> ($peso[$b['severity']] ?? 9)
            ?: ((int) empty($a['operational_hold']) <=> (int) empty($b['operational_hold']))
            ?: strcmp($a['title'], $b['title']);
    });
    return array_slice($resultado, 0, $limite);
}

function flow_overview_v1_atencao_colaborador(array $tarefas, array $originais, array $pendencias, int $colaboradorId): array
{
    return flow_overview_v1_atencao_pendencias($pendencias, $colaboradorId, 50);
}

function flow_overview_v1_carga_colaborador(mysqli $conn, int $colaboradorId, string $inicio, string $fim): array
{
    $dias = [];
    foreach (flow_capacidade_dias_uteis_no_intervalo($inicio, $fim) as $data) {
        $dias[$data] = 0.0;
    }
    try {
        $alocacao = flow_alocacao_consultar($conn, $inicio, $fim);
        foreach (($alocacao['grupos'] ?? []) as $grupo) {
            foreach (($grupo['projetos'] ?? []) as $projeto) {
                foreach (($projeto['pessoas'] ?? []) as $pessoa) {
                    if ((int) ($pessoa['id'] ?? 0) !== $colaboradorId) {
                        continue;
                    }
                    foreach (($pessoa['carga_dias'] ?? []) as $dia) {
                        $data = (string) ($dia['data'] ?? '');
                        if (isset($dias[$data])) {
                            $dias[$data] = max($dias[$data], (float) ($dia['percentual'] ?? 0));
                        }
                    }
                }
            }
        }
        return ['available' => true, 'days' => array_map(static fn (float $percentual, string $data): array => [
            'date' => $data, 'weekday' => ['SEG', 'TER', 'QUA', 'QUI', 'SEX'][(int) date('N', strtotime($data . ' 12:00:00')) - 1] ?? '',
            'percent' => round($percentual, 1),
            'state' => $percentual > 100 ? 'overload' : ($percentual > 80 ? 'attention' : 'normal'),
        ], $dias, array_keys($dias))];
    } catch (Throwable $erro) {
        return ['available' => false, 'days' => []];
    }
}

function flow_overview_v1_metricas_conclusao(mysqli $conn, ?int $colaboradorId = null): array
{
    $inicio = date('Y-m-01 00:00:00');
    $fim = date('Y-m-01 00:00:00', strtotime('+1 month'));
    $inicioAnterior = date('Y-m-01 00:00:00', strtotime('-1 month'));
    $where = $colaboradorId ? ' AND fi.colaborador_id = ?' : '';
    $sql = "SELECT la.funcao_imagem_id, MIN(la.data) AS concluida_em, fi.prazo,
                   h.prazo_necessario, ico.imagem_nome, o.nomenclatura, f.nome_funcao
              FROM log_alteracoes la
              JOIN funcao_imagem fi ON fi.idfuncao_imagem = la.funcao_imagem_id
              JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
              JOIN obra o ON o.idobra = ico.obra_id
              JOIN funcao f ON f.idfuncao = fi.funcao_id
              LEFT JOIN (
                  SELECT x.funcao_imagem_id, x.prazo_necessario
                    FROM funcao_imagem_previsao_historico x
                    JOIN (SELECT funcao_imagem_id, MAX(id) AS id
                            FROM funcao_imagem_previsao_historico
                           WHERE evento = 'CONCLUSAO_REGISTRADA'
                           GROUP BY funcao_imagem_id) ult ON ult.id = x.id
              ) h ON h.funcao_imagem_id = fi.idfuncao_imagem
             WHERE la.data >= ? AND la.data < ? {$where}
               AND LOWER(TRIM(la.status_novo)) IN ('finalizado','aprovado','aprovado com ajustes')
             GROUP BY la.funcao_imagem_id, fi.prazo, h.prazo_necessario, ico.imagem_nome, o.nomenclatura, f.nome_funcao
             ORDER BY concluida_em DESC";
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException($conn->error);
        }
        if ($colaboradorId) {
            $stmt->bind_param('ssi', $inicio, $fim, $colaboradorId);
        } else {
            $stmt->bind_param('ss', $inicio, $fim);
        }
        $stmt->execute();
        $atuais = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $sqlAnterior = "SELECT COUNT(DISTINCT la.funcao_imagem_id) total FROM log_alteracoes la JOIN funcao_imagem fi ON fi.idfuncao_imagem = la.funcao_imagem_id WHERE la.data >= ? AND la.data < ? {$where} AND LOWER(TRIM(la.status_novo)) IN ('finalizado','aprovado','aprovado com ajustes')";
        $stmt = $conn->prepare($sqlAnterior);
        if ($colaboradorId) {
            $stmt->bind_param('ssi', $inicioAnterior, $inicio, $colaboradorId);
        } else {
            $stmt->bind_param('ss', $inicioAnterior, $inicio);
        }
        $stmt->execute();
        $anterior = (int) (($stmt->get_result()->fetch_assoc()['total'] ?? 0));
        $stmt->close();
        $elegiveis = array_values(array_filter($atuais, static fn (array $item): bool => !empty($item['prazo_necessario'])));
        $pontuais = count(array_filter($elegiveis, static fn (array $item): bool => substr((string) $item['concluida_em'], 0, 10) <= (string) $item['prazo_necessario']));
        return [
            'available' => true, 'month_label' => strftime('%B %Y'), 'count' => count($atuais),
            'trend_percent' => $anterior > 0 ? round(((count($atuais) - $anterior) / $anterior) * 100, 1) : null,
            'punctuality_percent' => $elegiveis ? round(($pontuais / count($elegiveis)) * 100, 1) : null,
            'recent' => array_map(static fn (array $item): array => ['task_id' => (int) $item['funcao_imagem_id'], 'project' => (string) $item['nomenclatura'], 'image_name' => (string) $item['imagem_nome'], 'function_name' => (string) $item['nome_funcao']], array_slice($atuais, 0, 3)),
        ];
    } catch (Throwable $erro) {
        return ['available' => false, 'month_label' => '', 'count' => null, 'trend_percent' => null, 'punctuality_percent' => null, 'recent' => []];
    }
}

function flow_overview_v1_colaborador(mysqli $conn, array $payloadKanban, int $colaboradorId, string $section = 'all'): array
{
    $hoje = date('Y-m-d');
    $originais = (array) ($payloadKanban['funcoes'] ?? []);
    $porId = [];
    $tarefas = [];
    foreach ($originais as $original) {
        $normalizada = dashboard_colaborador_normalizar_tarefa($original);
        if ((int) $normalizada['id'] <= 0) {
            continue;
        }
        $porId[(int) $normalizada['id']] = $original;
        $tarefas[] = $normalizada;
    }
    $ativas = array_values(array_filter($tarefas, static fn (array $t): bool => empty($t['concluida'])));
    $emAndamento = array_values(array_filter($ativas, static fn (array $t): bool => dashboard_colaborador_status_execucao((string) ($t['status'] ?? ''))));
    usort($emAndamento, static function (array $a, array $b) use ($porId): int {
        $peso = ['flow_block' => 0, 'hold' => 1, 'overdue' => 2, 'requirement' => 3, 'due_today' => 4, 'due_soon' => 5];
        $ea = flow_overview_v1_excecao_tarefa($a, $porId[(int) $a['id']] ?? [])['state'] ?? '';
        $eb = flow_overview_v1_excecao_tarefa($b, $porId[(int) $b['id']] ?? [])['state'] ?? '';
        return ($peso[$ea] ?? 9) <=> ($peso[$eb] ?? 9) ?: dashboard_colaborador_rank_tarefa($a) <=> dashboard_colaborador_rank_tarefa($b);
    });
    $proximas = array_values(array_filter($ativas, static fn (array $t): bool => dashboard_colaborador_status_nao_iniciado((string) ($t['status'] ?? '')) && !empty($t['pode_iniciar'])));
    flow_overview_v1_ordenar_proximas($proximas, $hoje);
    $atencao = flow_overview_v1_atencao_colaborador($ativas, $originais, dashboard_colaborador_pendencias_acionaveis((array) ($payloadKanban['pendencias_operacionais'] ?? []), $colaboradorId), $colaboradorId);
    $resultado = [
        'mode' => 'collaborator',
        'summary' => ['in_progress_count' => count($emAndamento), 'attention_count' => count($atencao)],
        'in_progress' => array_map(static fn (array $t): array => flow_overview_v1_item_tarefa($t, $porId[(int) $t['id']] ?? []), array_slice($emAndamento, 0, 3)),
        'next' => array_map(static fn (array $t): array => flow_overview_v1_item_tarefa($t, $porId[(int) $t['id']] ?? []), array_slice($proximas, 0, 3)),
        'attention' => $atencao,
    ];
    if ($section !== 'critical') {
        $resultado['week_load'] = array_merge(['label' => 'Carga planejada da semana'], flow_overview_v1_carga_colaborador($conn, $colaboradorId, flow_overview_v1_inicio_semana($hoje), flow_overview_v1_fim_semana($hoje)));
        $resultado['completed'] = flow_overview_v1_metricas_conclusao($conn, $colaboradorId);
    }
    return $resultado;
}

function flow_overview_v1_equipes(mysqli $conn, array $alocacao): array
{
    $porPessoa = [];
    foreach (($alocacao['grupos'] ?? []) as $grupo) {
        foreach (($grupo['projetos'] ?? []) as $projeto) {
            foreach (($projeto['pessoas'] ?? []) as $pessoa) {
                $id = (int) ($pessoa['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                if (!isset($porPessoa[$id])) {
                    $porPessoa[$id] = ['id' => $id, 'name' => (string) ($pessoa['nome'] ?? ''), 'function_name' => (string) ($grupo['etapa'] ?? ''), 'peak_percent' => 0.0, 'peak_date' => null];
                }
                foreach (($pessoa['carga_dias'] ?? []) as $dia) {
                    if ((float) ($dia['percentual'] ?? 0) > $porPessoa[$id]['peak_percent']) {
                        $porPessoa[$id]['peak_percent'] = (float) $dia['percentual'];
                        $porPessoa[$id]['peak_date'] = $dia['data'] ?? null;
                    }
                }
            }
        }
    }
    $sql = "SELECT fi.colaborador_id, COUNT(CASE WHEN fi.status IN ('Em andamento','Ajuste') THEN 1 END) AS wip, COUNT(CASE WHEN fi.status IN ('Em andamento','Ajuste') AND fi.prazo < CURDATE() THEN 1 END) AS overdue, COUNT(CASE WHEN fi.status = 'HOLD' THEN 1 END) AS holds FROM funcao_imagem fi WHERE fi.colaborador_id IS NOT NULL GROUP BY fi.colaborador_id";
    $result = $conn->query($sql);
    $operacao = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $operacao[(int) $row['colaborador_id']] = $row;
        }
    }
    foreach ($porPessoa as $id => &$pessoa) {
        $op = $operacao[$id] ?? [];
        $pessoa['wip'] = (int) ($op['wip'] ?? 0);
        $pessoa['overdue_count'] = (int) ($op['overdue'] ?? 0);
        $pessoa['hold_count'] = (int) ($op['holds'] ?? 0);
        $pessoa['state'] = $pessoa['peak_percent'] > 100 ? 'overload' : ($pessoa['overdue_count'] || $pessoa['hold_count'] ? 'attention' : 'normal');
    }
    unset($pessoa);
    $lista = array_values($porPessoa);
    usort($lista, static fn (array $a, array $b): int => ['overload' => 0, 'attention' => 1, 'normal' => 2][$a['state']] <=> ['overload' => 0, 'attention' => 1, 'normal' => 2][$b['state']] ?: $b['peak_percent'] <=> $a['peak_percent'] ?: strcmp($a['name'], $b['name']));
    return array_slice($lista, 0, 8);
}

/** Exceções de tarefa para a fila gerencial, sem carregar o payload inteiro do Kanban por pessoa. */
function flow_overview_v1_excecoes_tarefas_gestor(mysqli $conn): array
{
    $sql = "SELECT fi.idfuncao_imagem, fi.colaborador_id, fi.status, fi.prazo, ico.idimagens_cliente_obra AS imagem_id,
                   ico.imagem_nome, o.nomenclatura, f.nome_funcao,
                   (SELECT fbi.id FROM flow_issue fbi WHERE fbi.funcao_imagem_id = fi.idfuncao_imagem
                      AND fbi.bloqueante = 1 AND (fbi.status IN ('ABERTA','AGUARDANDO_ACAO','PAUSADA') OR (fbi.status = 'RESOLVIDA' AND fbi.confirmada_em IS NULL))
                    ORDER BY CASE WHEN fbi.proxima_cobranca_em IS NOT NULL AND fbi.proxima_cobranca_em < NOW() THEN 0 ELSE 1 END,
                      FIELD(fbi.urgencia,'CRITICA','ALTA','NORMAL','BAIXA'), fbi.id ASC LIMIT 1) AS issue_id,
                   (SELECT fbi.urgencia FROM flow_issue fbi WHERE fbi.funcao_imagem_id = fi.idfuncao_imagem
                      AND fbi.bloqueante = 1 AND (fbi.status IN ('ABERTA','AGUARDANDO_ACAO','PAUSADA') OR (fbi.status = 'RESOLVIDA' AND fbi.confirmada_em IS NULL))
                    ORDER BY CASE WHEN fbi.proxima_cobranca_em IS NOT NULL AND fbi.proxima_cobranca_em < NOW() THEN 0 ELSE 1 END,
                      FIELD(fbi.urgencia,'CRITICA','ALTA','NORMAL','BAIXA'), fbi.id ASC LIMIT 1) AS issue_urgency,
                   (SELECT COALESCE(ft.nome, 'Bloqueio operacional ativo.') FROM flow_issue fbi
                    LEFT JOIN flow_issue_tipo ft ON ft.id = fbi.tipo_id WHERE fbi.funcao_imagem_id = fi.idfuncao_imagem
                      AND fbi.bloqueante = 1 AND (fbi.status IN ('ABERTA','AGUARDANDO_ACAO','PAUSADA') OR (fbi.status = 'RESOLVIDA' AND fbi.confirmada_em IS NULL))
                    ORDER BY fbi.id ASC LIMIT 1) AS issue_reason,
                   (SELECT fbi.criado_em FROM flow_issue fbi WHERE fbi.funcao_imagem_id = fi.idfuncao_imagem
                      AND fbi.bloqueante = 1 AND (fbi.status IN ('ABERTA','AGUARDANDO_ACAO','PAUSADA') OR (fbi.status = 'RESOLVIDA' AND fbi.confirmada_em IS NULL))
                    ORDER BY fbi.id ASC LIMIT 1) AS issue_since
              FROM funcao_imagem fi
              JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
              JOIN obra o ON o.idobra = ico.obra_id
              JOIN funcao f ON f.idfuncao = fi.funcao_id
             WHERE fi.status IN ('Em andamento','Ajuste','HOLD')
               AND (fi.status = 'HOLD' OR fi.prazo < CURDATE() OR EXISTS (
                    SELECT 1 FROM flow_issue fbi WHERE fbi.funcao_imagem_id = fi.idfuncao_imagem AND fbi.bloqueante = 1
                      AND (fbi.status IN ('ABERTA','AGUARDANDO_ACAO','PAUSADA') OR (fbi.status = 'RESOLVIDA' AND fbi.confirmada_em IS NULL))
               ))
             ORDER BY CASE WHEN fi.status = 'HOLD' THEN 0 ELSE 1 END, fi.prazo ASC, fi.idfuncao_imagem ASC
             LIMIT 30";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function flow_overview_v1_gestor(mysqli $conn, array $pendenciasOperacionais, string $section = 'all'): array
{
    $inicio = flow_overview_v1_inicio_semana();
    // A grade resumida reproduz o horizonte de oito semanas do Planejamento
    // de Capacidade, sem expor a composição de colaboradores.
    $fim = date('Y-m-d', strtotime($inicio . ' +55 days'));
    $alocacao = flow_alocacao_consultar($conn, $inicio, $fim);
    $capacidade = flow_capacidade_consultar($conn, $inicio, $fim);
    $projecao = flow_fila_confirmada_projetar($conn);
    $atencao = flow_overview_v1_atencao_pendencias($pendenciasOperacionais, null, 50);
    $riscos = [];
    foreach (($projecao['projecoes'] ?? []) as $item) {
        $margem = (int) ($item['margem_operacional_dias_uteis'] ?? 0);
        $status = (string) ($item['status_operacional'] ?? '');
        $critico = $margem < 0 || stripos($status, 'ATRAS') !== false;
        $atencaoRisco = !$critico && ($margem <= 2 || in_array(strtoupper((string) ($item['confianca'] ?? '')), ['BAIXA', 'INSUFICIENTE'], true));
        if (!$critico && !$atencaoRisco) {
            continue;
        }
        $risco = ['entity_type' => 'project', 'entity_id' => (int) ($item['obra_id'] ?? 0), 'delivery_id' => (int) ($item['entrega_id'] ?? 0), 'severity' => $critico ? 'critical' : 'warning', 'project' => (string) ($item['nomenclatura'] ?? $item['nome_obra'] ?? 'Projeto'), 'title' => $critico ? 'Projeção ultrapassa a entrega.' : 'Margem operacional curta.', 'detail' => $margem < 0 ? abs($margem) . ' dia(s) útil(eis) além da margem.' : 'Margem de ' . $margem . ' dia(s) útil(eis).', 'action' => ['type' => 'open_planning']];
        $riscos[] = $risco;
    }
    $lista = array_values($atencao);
    $peso = ['critical' => 0, 'high' => 1, 'warning' => 2];
    usort($lista, static fn (array $a, array $b): int => ($peso[$a['severity']] ?? 9) <=> ($peso[$b['severity']] ?? 9) ?: strcmp($a['title'], $b['title']));
    $resultado = ['mode' => 'manager', 'summary' => ['critical_count' => count(array_filter($lista, static fn (array $item): bool => $item['severity'] === 'critical')), 'attention_count' => count($lista)], 'attention' => array_slice($lista, 0, 6)];
    if ($section !== 'critical') {
        $resultado['team'] = flow_overview_v1_equipes($conn, $alocacao);
        $etapasPorCodigo = [];
        foreach ((array) ($capacidade['etapas'] ?? []) as $etapa) {
            $etapasPorCodigo[(string) ($etapa['codigo_etapa'] ?? '')] = $etapa;
        }
        $semanas = [];
        for ($indice = 0; $indice < 8; $indice++) {
            $semanas[] = date('Y-m-d', strtotime($inicio . ' +' . ($indice * 7) . ' days'));
        }
        $resultado['capacity'] = array_map(static function (array $catalogo) use ($etapasPorCodigo, $semanas, $capacidade): array {
            $codigo = (string) ($catalogo['codigo_etapa'] ?? '');
            $etapa = (array) ($etapasPorCodigo[$codigo] ?? []);
            $capacidadePrincipal = (float) (($capacidade['capacidades'][$codigo]['capacidade_principal'] ?? 0));
            $porSemana = [];
            foreach ((array) ($etapa['semanas'] ?? []) as $semana) {
                $porSemana[(string) ($semana['semana'] ?? '')] = $semana;
            }
            $weeks = array_map(static function (string $semana) use ($porSemana, $capacidadePrincipal): array {
                $item = (array) ($porSemana[$semana] ?? []);
                return [
                    'semana' => $semana,
                    'pico_demanda' => (float) ($item['pico_demanda'] ?? 0),
                    'capacidade_principal_referencia' => $item['capacidade_principal_referencia'] ?? $capacidadePrincipal,
                    'classificacao' => (string) ($item['classificacao'] ?? 'SEM_DEMANDA'),
                ];
            }, $semanas);
            return [
                'code' => $codigo,
                'name' => (string) ($catalogo['nome_painel'] ?? $catalogo['etapa'] ?? 'Função'),
                'classification' => (string) ($etapa['classificacao'] ?? 'SEM_DEMANDA'),
                'weeks' => $weeks,
            ];
        }, array_slice((array) ($capacidade['catalogo_etapas'] ?? []), 0, 8));
        $resultado['risks'] = array_slice($riscos, 0, 5);
        $resultado['production'] = flow_overview_v1_metricas_conclusao($conn);
    }
    return $resultado;
}
