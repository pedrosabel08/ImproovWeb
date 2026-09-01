<?php

/**
 * Contratos enxutos da Visão Geral V1.
 *
 * Esta camada não substitui Kanban, planejamento ou capacidade. Ela apenas
 * transforma os dados canônicos desses módulos em itens de decisão.
 */

require_once __DIR__ . '/dashboard_colaborador_helper.php';
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

function flow_overview_v1_atencao_colaborador(array $tarefas, array $originais, array $pendencias, int $colaboradorId): array
{
    $porId = [];
    foreach ($originais as $item) {
        $porId[(int) ($item['idfuncao_imagem'] ?? 0)] = $item;
    }
    $atencao = [];
    $adicionar = static function (string $chave, array $item) use (&$atencao): void {
        $peso = ['critical' => 0, 'high' => 1, 'warning' => 2];
        if (!isset($atencao[$chave]) || ($peso[$item['severity']] ?? 9) < ($peso[$atencao[$chave]['severity']] ?? 9)) {
            $atencao[$chave] = $item;
        }
    };
    foreach ($tarefas as $tarefa) {
        if (!empty($tarefa['concluida'])) {
            continue;
        }
        $id = (int) $tarefa['id'];
        $excecao = flow_overview_v1_excecao_tarefa($tarefa, $porId[$id] ?? []);
        if (!$excecao) {
            continue;
        }
        $acionavel = in_array($excecao['state'], ['flow_block', 'hold', 'overdue', 'due_today'], true)
            || (($tarefa['bloqueio']['responsavel_id'] ?? $colaboradorId) === $colaboradorId);
        if (!$acionavel) {
            continue;
        }
        $adicionar('task:' . $id, [
            'entity_type' => 'task', 'entity_id' => $id,
            'severity' => $excecao['severity'], 'type' => $excecao['state'],
            'title' => trim((string) $tarefa['obra'] . ' · ' . (string) $tarefa['imagem']),
            'detail' => $excecao['label'], 'action' => ['type' => 'open_task'],
        ]);
    }
    foreach ($pendencias as $pendencia) {
        if ((int) ($pendencia['responsavel_id'] ?? 0) !== $colaboradorId) {
            continue;
        }
        $chave = 'pending:' . (string) ($pendencia['source_type'] ?? '') . ':' . (int) ($pendencia['source_id'] ?? 0);
        $adicionar($chave, [
            'entity_type' => 'pending', 'entity_id' => (int) ($pendencia['source_id'] ?? 0),
            'severity' => flow_overview_v1_severidade_pendencia($pendencia),
            'type' => (string) ($pendencia['source_type'] ?? 'pending'),
            'title' => (string) ($pendencia['title'] ?? 'Pendência operacional'),
            'detail' => (string) ($pendencia['subtitle'] ?? 'Ação necessária.'),
            'action' => ['type' => 'open_pending', 'url' => (string) ($pendencia['action_url'] ?? '')],
        ]);
    }
    $resultado = array_values($atencao);
    $peso = ['critical' => 0, 'high' => 1, 'warning' => 2];
    usort($resultado, static fn (array $a, array $b): int => ($peso[$a['severity']] ?? 9) <=> ($peso[$b['severity']] ?? 9) ?: strcmp($a['title'], $b['title']));
    return array_slice($resultado, 0, 4);
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
    $sql = "SELECT fi.idfuncao_imagem, fi.status, fi.prazo, ico.idimagens_cliente_obra AS imagem_id,
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

function flow_overview_v1_gestor(mysqli $conn, string $section = 'all'): array
{
    $inicio = flow_overview_v1_inicio_semana();
    $fim = date('Y-m-d', strtotime($inicio . ' +25 days'));
    $alocacao = flow_alocacao_consultar($conn, $inicio, $fim);
    $capacidade = flow_capacidade_consultar($conn, $inicio, $fim);
    $projecao = flow_fila_confirmada_projetar($conn);
    $atencao = [];
    $adicionar = static function (array $item) use (&$atencao): void {
        $atencao[$item['key']] = $item;
    };
    foreach (flow_overview_v1_equipes($conn, $alocacao) as $pessoa) {
        if ($pessoa['peak_percent'] <= 100) {
            continue;
        }
        $adicionar(['key' => 'person:' . $pessoa['id'], 'entity_type' => 'person', 'entity_id' => $pessoa['id'], 'severity' => 'critical', 'title' => $pessoa['name'] . ' está com ' . round($pessoa['peak_percent']) . '% de carga planejada.', 'detail' => 'Pico em ' . date('d/m', strtotime((string) $pessoa['peak_date'])), 'causes' => ['overload'], 'action' => ['type' => 'open_capacity']]);
    }
    foreach (flow_overview_v1_excecoes_tarefas_gestor($conn) as $tarefa) {
        $taskId = (int) $tarefa['idfuncao_imagem'];
        $titulo = trim((string) $tarefa['nomenclatura'] . ' · ' . (string) $tarefa['imagem_nome']);
        $temIssue = (int) ($tarefa['issue_id'] ?? 0) > 0;
        if ($temIssue) {
            $critico = strtoupper((string) ($tarefa['issue_urgency'] ?? '')) === 'CRITICA';
            $detalhe = trim((string) ($tarefa['issue_reason'] ?? 'Bloqueio operacional ativo.'));
            $adicionar(['key' => 'task:' . $taskId, 'entity_type' => 'task', 'entity_id' => $taskId, 'severity' => $critico ? 'critical' : 'high', 'title' => $titulo . ' está bloqueada.', 'detail' => $detalhe, 'causes' => ['flow_block'], 'action' => ['type' => 'open_task']]);
        } elseif ((string) $tarefa['status'] === 'HOLD') {
            $adicionar(['key' => 'task:' . $taskId, 'entity_type' => 'task', 'entity_id' => $taskId, 'severity' => 'high', 'title' => $titulo . ' está em HOLD.', 'detail' => 'Verifique o bloqueio e o impacto no planejamento.', 'causes' => ['hold'], 'action' => ['type' => 'open_task']]);
        } else {
            $adicionar(['key' => 'task:' . $taskId, 'entity_type' => 'task', 'entity_id' => $taskId, 'severity' => 'high', 'title' => $titulo . ' ultrapassou o prazo.', 'detail' => (string) $tarefa['nome_funcao'], 'causes' => ['overdue'], 'action' => ['type' => 'open_task']]);
        }
    }
    foreach (($capacidade['resumo_etapas'] ?? []) as $etapa) {
        $classificacao = (string) ($etapa['classificacao'] ?? '');
        if (!in_array($classificacao, ['CONFLITO', 'NECESSITA_APOIO'], true)) {
            continue;
        }
        $semana = (array) ($etapa['semana_critica'] ?? []);
        $adicionar(['key' => 'function:' . (string) ($etapa['codigo_etapa'] ?? '') . ':' . (string) ($semana['semana'] ?? ''), 'entity_type' => 'capacity', 'entity_id' => (string) ($etapa['codigo_etapa'] ?? ''), 'severity' => $classificacao === 'CONFLITO' ? 'critical' : 'high', 'title' => (string) ($etapa['etapa'] ?? 'Função') . ' ' . ($classificacao === 'CONFLITO' ? 'está sem capacidade suficiente.' : 'precisa de apoio.'), 'detail' => 'Semana de ' . date('d/m', strtotime((string) ($semana['semana'] ?? $inicio))), 'causes' => ['capacity'], 'action' => ['type' => 'open_capacity']]);
    }
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
        $adicionar(['key' => 'delivery:' . $risco['delivery_id'], 'entity_type' => 'project', 'entity_id' => $risco['entity_id'], 'severity' => $risco['severity'], 'title' => $risco['project'] . ': ' . $risco['title'], 'detail' => $risco['detail'], 'causes' => ['projection'], 'action' => $risco['action']]);
    }
    $lista = array_values($atencao);
    $peso = ['critical' => 0, 'high' => 1, 'warning' => 2];
    usort($lista, static fn (array $a, array $b): int => ($peso[$a['severity']] ?? 9) <=> ($peso[$b['severity']] ?? 9) ?: strcmp($a['title'], $b['title']));
    $resultado = ['mode' => 'manager', 'summary' => ['critical_count' => count(array_filter($lista, static fn (array $item): bool => $item['severity'] === 'critical')), 'attention_count' => count($lista)], 'attention' => array_slice($lista, 0, 6)];
    if ($section !== 'critical') {
        $resultado['team'] = flow_overview_v1_equipes($conn, $alocacao);
        $resultado['capacity'] = array_map(static fn (array $etapa): array => ['code' => (string) ($etapa['codigo_etapa'] ?? ''), 'name' => (string) ($etapa['etapa'] ?? ''), 'classification' => (string) ($etapa['classificacao'] ?? ''), 'weeks' => array_slice((array) ($etapa['semanas'] ?? []), 0, 4)], array_values(array_filter((array) ($capacidade['resumo_etapas'] ?? []), static fn (array $etapa): bool => in_array((string) ($etapa['classificacao'] ?? ''), ['CONFLITO', 'NECESSITA_APOIO'], true))));
        $resultado['risks'] = array_slice($riscos, 0, 5);
        $resultado['production'] = flow_overview_v1_metricas_conclusao($conn);
    }
    return $resultado;
}
