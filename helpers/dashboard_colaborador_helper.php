<?php

/**
 * Camada de leitura da home operacional. Ela recebe o payload já canônico do
 * Kanban e apenas normaliza/agrupa seus fatos; não consulta nem persiste dados.
 */

require_once __DIR__ . '/tarefa_planejamento_contexto_helper.php';
require_once __DIR__ . '/planejamento_execucao_helper.php';

function dashboard_colaborador_data_valida(?string $data): ?string
{
    return flow_tarefa_planejamento_data_valida($data);
}

function dashboard_colaborador_status(string $status): string
{
    return flow_planejamento_normalizar($status);
}

function dashboard_colaborador_status_nao_iniciado(string $status): bool
{
    return dashboard_colaborador_status($status) === 'nao iniciado';
}

function dashboard_colaborador_status_execucao(string $status): bool
{
    return in_array(dashboard_colaborador_status($status), ['em andamento', 'ajuste'], true);
}

function dashboard_colaborador_prazo_efetivo(array $tarefa): array
{
    $planejamento = (array) ($tarefa['planejamento'] ?? []);
    $prazoPlanejamento = dashboard_colaborador_data_valida($planejamento['prazo_necessario'] ?? null);
    if (!empty($planejamento['planejamento_disponivel']) && $prazoPlanejamento) {
        return [
            'prazo' => $prazoPlanejamento,
            'prazo_origem' => 'planejamento',
            'planejamento_confirmado' => true,
        ];
    }

    $prazoFuncao = dashboard_colaborador_data_valida($tarefa['prazo'] ?? null);
    return [
        'prazo' => $prazoFuncao,
        'prazo_origem' => $prazoFuncao ? 'funcao_imagem' : 'indisponivel',
        'planejamento_confirmado' => false,
    ];
}

function dashboard_colaborador_bloqueio(array $tarefa, bool $hold, bool $flowBlock, bool $impedidaInicio): ?array
{
    if ($flowBlock) {
        return [
            'tipo' => 'flow_block',
            'issue_id' => (int) ($tarefa['flow_block_issue_principal_id'] ?? 0) ?: null,
            'status' => (string) ($tarefa['flow_block_issue_principal_status'] ?? ''),
            'mensagem' => (string) ($tarefa['flow_block_motivo_principal'] ?? 'Impedimento operacional ativo.'),
        ];
    }
    if ($hold) {
        return [
            'tipo' => 'hold',
            'issue_id' => null,
            'status' => 'HOLD',
            'mensagem' => (string) ($tarefa['hold_justificativa_recente'] ?? $tarefa['descricao'] ?? 'Tarefa em HOLD.'),
        ];
    }
    if ($impedidaInicio) {
        $requisito = (array) (($tarefa['requisitos']['bloqueios'] ?? [])[0] ?? []);
        return [
            'tipo' => 'requisito',
            'issue_id' => null,
            'status' => (string) ($requisito['estado'] ?? 'NAO_ATENDIDO'),
            'mensagem' => (string) ($requisito['label'] ?? 'Aguardando requisito para iniciar.'),
            'responsavel_id' => isset($requisito['responsavel_id']) ? (int) $requisito['responsavel_id'] : null,
            'responsavel_nome' => (string) ($requisito['responsavel_nome'] ?? ''),
        ];
    }
    return null;
}

function dashboard_colaborador_normalizar_tarefa(array $tarefa): array
{
    $status = (string) ($tarefa['status'] ?? 'Não iniciado');
    $finalizada = flow_planejamento_status_finalizado($status);
    $naoIniciada = dashboard_colaborador_status_nao_iniciado($status);
    $hold = flow_planejamento_status_hold($status);
    $flowBlockAtivo = (int) ($tarefa['flow_block_issues_abertas'] ?? 0) > 0;
    $liberada = (bool) ($tarefa['liberada'] ?? false);
    $impedidaInicio = !$finalizada && $naoIniciada && !$liberada;
    $bloqueada = !$finalizada && ($hold || $flowBlockAtivo || $impedidaInicio);
    $prazo = dashboard_colaborador_prazo_efetivo($tarefa);
    $planejamento = (array) ($tarefa['planejamento'] ?? []);
    $statusTemporal = flow_tarefa_planejamento_status_temporal(
        $prazo['prazo'],
        $status,
        null,
        $bloqueada
    );

    return [
        'id' => (int) ($tarefa['idfuncao_imagem'] ?? 0),
        'imagem_id' => (int) ($tarefa['imagem_id'] ?? 0),
        'obra_id' => (int) ($tarefa['obra_id'] ?? 0),
        'obra' => (string) ($tarefa['nomenclatura'] ?? $tarefa['nome_obra'] ?? ''),
        'imagem' => (string) ($tarefa['imagem_nome'] ?? ''),
        'funcao_id' => (int) ($tarefa['funcao_id'] ?? 0),
        'funcao' => (string) ($tarefa['nome_funcao'] ?? ''),
        'status' => $status,
        'substatus' => (string) ($tarefa['nome_status'] ?? ''),
        'prazo' => $prazo['prazo'],
        'prazo_origem' => $prazo['prazo_origem'],
        'planejamento_confirmado' => $prazo['planejamento_confirmado'],
        'planejamento_tipo' => $prazo['planejamento_confirmado'] ? 'confirmado' : ($prazo['prazo'] ? 'fallback_funcao' : 'indisponivel'),
        'janela_inicio' => dashboard_colaborador_data_valida($planejamento['janela_inicio'] ?? null),
        'status_temporal' => (string) ($statusTemporal['codigo'] ?? 'SEM_PRAZO'),
        'status_temporal_label' => (string) ($statusTemporal['rotulo'] ?? ''),
        'dias_prazo' => $statusTemporal['dias'] ?? null,
        'concluida' => $finalizada,
        'pode_iniciar' => !$finalizada && !$hold && !$flowBlockAtivo && $naoIniciada && $liberada,
        'bloqueada' => $bloqueada,
        'bloqueio' => dashboard_colaborador_bloqueio($tarefa, $hold, $flowBlockAtivo, $impedidaInicio),
        'flow_block' => $flowBlockAtivo ? [
            'issue_id' => (int) ($tarefa['flow_block_issue_principal_id'] ?? 0) ?: null,
            'status' => (string) ($tarefa['flow_block_issue_principal_status'] ?? ''),
            'motivo' => (string) ($tarefa['flow_block_motivo_principal'] ?? ''),
            'proxima_cobranca_em' => $tarefa['flow_block_proxima_cobranca_em'] ?? null,
            'cobranca_atrasada' => (bool) ($tarefa['flow_block_cobranca_atrasada'] ?? false),
        ] : null,
        'prioridade_manual' => (int) ($tarefa['prioridade'] ?? 3),
        'fila_posicao' => isset($tarefa['fila_operacional_posicao']) ? (int) $tarefa['fila_operacional_posicao'] : null,
        'fila_etapa' => $tarefa['fila_operacional_etapa'] ?? null,
        'em_execucao' => dashboard_colaborador_status_execucao($status) && !$bloqueada,
        'trabalho_efetivo' => flow_execucao_status_trabalho_efetivo($status) && !$finalizada && !$bloqueada,
        'elegivel_agora' => !$finalizada && !$bloqueada
            && (dashboard_colaborador_status_execucao($status) || ($naoIniciada && $liberada)),
    ];
}

function dashboard_colaborador_rank_tarefa(array $tarefa): array
{
    $grupo = 90;
    $statusTemporal = (string) ($tarefa['status_temporal'] ?? 'SEM_PRAZO');
    $status = dashboard_colaborador_status((string) ($tarefa['status'] ?? ''));
    if ($status === 'em andamento') {
        $grupo = $statusTemporal === 'ATRASADO' ? 10 : ($statusTemporal === 'PRAZO_HOJE' ? 20 : 30);
    } elseif ($status === 'ajuste') {
        $grupo = $statusTemporal === 'ATRASADO' ? 40 : ($statusTemporal === 'PRAZO_HOJE' ? 50 : 60);
    } elseif ($status === 'nao iniciado') {
        $grupo = $statusTemporal === 'ATRASADO' ? 70 : ($statusTemporal === 'PRAZO_HOJE' ? 80 : 90);
    }
    return [
        $grupo,
        (int) ($tarefa['prioridade_manual'] ?? 3),
        $tarefa['fila_posicao'] === null ? PHP_INT_MAX : (int) $tarefa['fila_posicao'],
        (string) ($tarefa['prazo'] ?? '9999-12-31'),
        (int) ($tarefa['id'] ?? 0),
    ];
}

function dashboard_colaborador_ordenar_tarefas(array &$tarefas): void
{
    usort($tarefas, static function (array $a, array $b): int {
        return dashboard_colaborador_rank_tarefa($a) <=> dashboard_colaborador_rank_tarefa($b);
    });
}

function dashboard_colaborador_pendencias_acionaveis(array $modulos, int $colaboradorId): array
{
    $resultado = [];
    foreach ($modulos as $modulo) {
        foreach ((array) ($modulo['items'] ?? []) as $item) {
            if ((int) ($item['responsavel_id'] ?? 0) !== $colaboradorId) {
                continue;
            }
            $chave = (string) ($item['source_type'] ?? '') . ':' . (int) ($item['source_id'] ?? 0);
            $resultado[$chave] = $item;
        }
    }
    return array_values($resultado);
}

function dashboard_colaborador_severidade_pendencia(array $item): string
{
    $urgencia = strtoupper((string) (($item['metadata']['urgencia'] ?? '')));
    $sla = (string) ($item['sla_status'] ?? 'dentro');
    return $urgencia === 'CRITICA' || in_array($sla, ['critico', 'estourado'], true) ? 'critical' : 'warning';
}

function dashboard_colaborador_titulo(array $tarefa): string
{
    return trim((string) ($tarefa['obra'] ?? '') . ' · ' . (string) ($tarefa['imagem'] ?? ''));
}

function dashboard_colaborador_montar(array $payloadKanban, int $colaboradorId, ?string $hoje = null): array
{
    $hoje = dashboard_colaborador_data_valida($hoje ?: date('Y-m-d')) ?: date('Y-m-d');
    $limiteSemana = flow_planejamento_adicionar_dias_uteis($hoje, 7);
    $tarefas = array_values(array_filter(array_map(
        'dashboard_colaborador_normalizar_tarefa',
        (array) ($payloadKanban['funcoes'] ?? [])
    ), static fn (array $tarefa): bool => (int) $tarefa['id'] > 0));

    $ativas = array_values(array_filter($tarefas, static fn (array $tarefa): bool => empty($tarefa['concluida'])));
    $hojeTarefas = array_values(array_filter($ativas, static fn (array $tarefa): bool => ($tarefa['prazo'] ?? null) === $hoje));
    $atrasadas = array_values(array_filter($ativas, static fn (array $tarefa): bool => ($tarefa['status_temporal'] ?? '') === 'ATRASADO'));
    $emAndamento = array_values(array_filter($ativas, static fn (array $tarefa): bool => !empty($tarefa['em_execucao'])));
    $emAndamentoEstrito = array_values(array_filter($emAndamento, static fn (array $tarefa): bool => dashboard_colaborador_status((string) $tarefa['status']) === 'em andamento'));
    $emAjuste = array_values(array_filter($emAndamento, static fn (array $tarefa): bool => dashboard_colaborador_status((string) $tarefa['status']) === 'ajuste'));
    $proximas = array_values(array_filter($ativas, static function (array $tarefa) use ($hoje, $limiteSemana): bool {
        return !empty($tarefa['pode_iniciar'])
            && !empty($tarefa['prazo'])
            && $tarefa['prazo'] > $hoje
            && $tarefa['prazo'] <= $limiteSemana;
    }));

    $candidatasAgora = array_values(array_filter($ativas, static fn (array $tarefa): bool => !empty($tarefa['elegivel_agora'])));
    dashboard_colaborador_ordenar_tarefas($candidatasAgora);
    $agora = $candidatasAgora[0] ?? null;
    if ($agora) {
        $candidatasAgora = array_values(array_filter($candidatasAgora, static fn (array $tarefa): bool => (int) $tarefa['id'] !== (int) $agora['id']));
    }

    $pendencias = dashboard_colaborador_pendencias_acionaveis((array) ($payloadKanban['pendencias_operacionais'] ?? []), $colaboradorId);
    $porIssue = [];
    foreach ($tarefas as $tarefa) {
        $issueId = (int) ($tarefa['flow_block']['issue_id'] ?? 0);
        if ($issueId > 0) $porIssue[$issueId] = $tarefa;
    }
    $atencaoPorChave = [];
    foreach ($pendencias as $pendencia) {
        $issueId = (int) (($pendencia['metadata']['issue_id'] ?? 0));
        $tarefa = $porIssue[$issueId] ?? null;
        $chave = $tarefa ? 'task:' . (int) $tarefa['id'] : 'pending:' . (string) ($pendencia['id'] ?? '');
        $atencaoPorChave[$chave] = [
            'type' => (string) ($pendencia['source_type'] ?? 'pending'),
            'severity' => dashboard_colaborador_severidade_pendencia($pendencia),
            'task_id' => $tarefa['id'] ?? null,
            'title' => $tarefa ? dashboard_colaborador_titulo($tarefa) : (string) ($pendencia['title'] ?? 'Pendência operacional'),
            'message' => (string) ($pendencia['subtitle'] ?? 'Ação necessária.'),
            'action' => $tarefa ? 'open_task' : 'open_pending',
            'action_url' => (string) ($pendencia['action_url'] ?? ''),
        ];
    }
    foreach ($ativas as $tarefa) {
        $chave = 'task:' . (int) $tarefa['id'];
        if (isset($atencaoPorChave[$chave])) continue;
        if (($tarefa['status_temporal'] ?? '') === 'ATRASADO' && !empty($tarefa['pode_iniciar'])) {
            $atencaoPorChave[$chave] = [
                'type' => 'late_task', 'severity' => 'critical', 'task_id' => $tarefa['id'],
                'title' => dashboard_colaborador_titulo($tarefa),
                'message' => 'Prazo ultrapassado' . (($tarefa['dias_prazo'] ?? 0) > 0 ? ' há ' . (int) $tarefa['dias_prazo'] . ' dia(s) útil(eis)' : '.'),
                'action' => 'open_task',
            ];
        } elseif (($tarefa['status_temporal'] ?? '') === 'PRAZO_HOJE' && !empty($tarefa['pode_iniciar'])) {
            $atencaoPorChave[$chave] = [
                'type' => 'due_today', 'severity' => 'warning', 'task_id' => $tarefa['id'],
                'title' => dashboard_colaborador_titulo($tarefa), 'message' => 'Tarefa liberada com prazo hoje.',
                'action' => 'open_task',
            ];
        }
    }
    $atencao = array_values($atencaoPorChave);
    usort($atencao, static fn (array $a, array $b): int => (($a['severity'] === 'critical') ? 0 : 1) <=> (($b['severity'] === 'critical') ? 0 : 1));

    $semanaPorData = [];
    foreach ($ativas as $tarefa) {
        $data = $tarefa['prazo'] ?? null;
        if (!$data || $data < $hoje || $data > $limiteSemana) continue;
        $semanaPorData[$data]['date'] = $data;
        $semanaPorData[$data]['is_today'] = $data === $hoje;
        $semanaPorData[$data]['tasks'][] = $tarefa;
    }
    ksort($semanaPorData);
    foreach ($semanaPorData as &$dia) dashboard_colaborador_ordenar_tarefas($dia['tasks']);
    unset($dia);

    return [
        'summary' => [
            'today' => ['total' => count($hojeTarefas), 'late' => count($atrasadas)],
            'in_progress' => ['total' => count($emAndamento), 'em_andamento' => count($emAndamentoEstrito), 'ajuste' => count($emAjuste)],
            'pending' => ['total' => count($pendencias), 'critical' => count(array_filter($pendencias, static fn (array $item): bool => dashboard_colaborador_severidade_pendencia($item) === 'critical'))],
            'upcoming' => ['total' => count($proximas)],
        ],
        'day' => ['current' => $agora, 'next' => array_slice($candidatasAgora, 0, 5)],
        'attention' => $atencao,
        'week' => array_values($semanaPorData),
        'meta' => ['today' => $hoje, 'week_until' => $limiteSemana, 'eligible_tasks' => count($tarefas)],
    ];
}
