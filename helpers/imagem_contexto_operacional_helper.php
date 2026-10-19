<?php

/** Read-only image context. One stage per real task; no selected card or collective completion. */
require_once __DIR__ . '/tarefa_planejamento_contexto_helper.php';
require_once __DIR__ . '/motor_requisitos_helper.php';
require_once __DIR__ . '/janela_operacional_helper.php';
require_once __DIR__ . '/imagem_fluxo_operacional_helper.php';

/** Pure composition boundary, also used by the contract tests. */
function flow_imagem_contexto_operacional_montar(array $imagem, array $tarefas, array $logs, array $planejamentos, array $requisitos, array $ciclos = []): array
{
    $result = [
        'contrato_versao' => 1,
        'hoje' => (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d'),
        'imagem' => $imagem,
        'etapas' => [],
        'etapas_ativas' => [], 'etapas_concluidas' => [],
        'etapas_pendentes' => [], 'etapas_bloqueadas' => [],
        'etapas_nao_operacionais' => [],
        'avisos' => [],
    ];
    $result['imagem']['atualizado_em'] = $imagem['atualizado_em'] ?? null;
    $ordemOperacional = flow_tarefa_ordem_funcoes_operacionais();
    $temPlano = (bool) array_filter($tarefas, static fn (array $t): bool => !empty($planejamentos[(int) $t['idfuncao_imagem']]['planejamento_disponivel']));
    $porFuncao = [];
    foreach ($tarefas as $tarefa) {
        if ((int) ($tarefa['imagem_id'] ?? 0) !== (int) $imagem['id']) {
            throw new InvalidArgumentException('Uma etapa não pode pertencer a outra imagem.');
        }
        $id = (int) $tarefa['idfuncao_imagem'];
        foreach ($logs[$id] ?? [] as $log) {
            if (!empty($log['data']) && $log['data'] > ($result['imagem']['atualizado_em'] ?? '')) {
                $result['imagem']['atualizado_em'] = $log['data'];
            }
        }
        $funcao = (int) $tarefa['funcao_id'];
        $status = (string) ($tarefa['status'] ?? '');
        $resumo = flow_execucao_resumir_tarefa($tarefa + ['tarefa_id' => $id], $logs[$id] ?? []);
        $avaliacao = $requisitos[$id] ?? null;
        $bloqueioInicio = $avaliacao !== null && empty($avaliacao['elegivel']);
        $cancelada = flow_execucao_status_cancelado($status);
        $dispensada = motor_requisitos_estado_predecessora([
            'colaborador_id' => $tarefa['responsavel_id'] ?? null,
            'nome_colaborador' => $tarefa['responsavel_nome'] ?? '',
            'status' => $status,
        ]) === 'DISPENSADO' || flow_planejamento_normalizar($status) === 'nao se aplica';
        // Eligibility describes permission to START, not whether work is already active.
        $estado = $cancelada || $dispensada ? null : (
            $resumo['concluida'] ? 'CONCLUIDA' : (
                $resumo['hold'] ? 'BLOQUEADA' : (
                    flow_execucao_status_trabalho_efetivo($status) ? 'ATIVA' : (
                        $bloqueioInicio ? 'BLOQUEADA' : 'PENDENTE'
                    )
                )
            )
        );
        $plan = $planejamentos[$id] ?? [];
        $planejado = !empty($plan['planejamento_disponivel']);
        $ordemPlanejada = null;
        if ($planejado) {
            foreach ((array) ($plan['timeline'] ?? []) as $index => $etapa) {
                if (($etapa['codigo'] ?? '') === ($plan['etapa_codigo'] ?? null)) {
                    $ordemPlanejada = $index + 1;
                    break;
                }
            }
        }
        $prazo = flow_tarefa_planejamento_data_valida($tarefa['prazo'] ?? null);
        $compromisso = $planejado ? ($plan['prazo_necessario'] ?? null) : null;
        $stage = [
            'funcao_id' => $funcao,
            'funcao_nome' => $tarefa['funcao_nome'] ?? null,
            'funcao_imagem_id' => $id,
            'imagem_id' => (int) $imagem['id'],
            'status' => $status,
            'responsavel_id' => isset($tarefa['responsavel_id']) ? (int) $tarefa['responsavel_id'] : null,
            'responsavel_nome' => $tarefa['responsavel_nome'] ?? null,
            'prazo' => $prazo,
            'estado_visual' => $estado,
            'participa_operacao' => !$cancelada && !$dispensada,
            'motivo_nao_operacional' => $cancelada ? 'CANCELADA' : ($dispensada ? 'DISPENSADA' : null),
            'ordem' => $ordemPlanejada !== null ? $ordemPlanejada * 100 : ($temPlano ? 100000 : 0) + ($ordemOperacional[$funcao] ?? 999),
            'origem_ordem' => $ordemPlanejada !== null ? 'PLANEJAMENTO' : 'OPERACIONAL',
            'execucao_individual' => $resumo,
            'requisitos_inicio' => $avaliacao,
            'contexto_planejamento' => [
                'escopo' => 'ENTREGA', 'disponivel' => $planejado,
                'etapa_codigo' => $planejado ? ($plan['etapa_codigo'] ?? null) : null,
                'etapa_nome' => $planejado ? ($plan['etapa_nome'] ?? null) : null,
                'versao_id' => $planejado ? ($plan['versao_id'] ?? null) : null,
                'plano_versao' => $planejado ? ($plan['plano_versao'] ?? null) : null,
                'ordem_planejada' => $ordemPlanejada,
                'inicio_planejado' => $planejado ? ($plan['janela_inicio'] ?? null) : null,
                'prazo_planejado' => $planejado ? ($plan['janela_fim'] ?? null) : null,
                'compromisso' => $compromisso,
                'compromisso_historico' => $planejado && !empty($plan['prazo_necessario_historico']),
                'previsao_colaborador' => $planejado ? ($plan['previsao_colaborador'] ?? null) : null,
                'desvio_individual_dias_uteis' => flow_tarefa_planejamento_desvio($compromisso, $resumo['conclusao_real']),
                'status_temporal_individual' => $estado === null ? null : flow_tarefa_planejamento_status_temporal($compromisso, $status, $resumo['conclusao_real'], $estado === 'BLOQUEADA'),
            ],
            'ultimo_ciclo' => $ciclos[$id] ?? null,
            'avisos' => [],
        ];
        if ($avaliacao === null) {
            $stage['avisos'][] = 'REQUISITOS_NAO_AVALIADOS';
        }
        if ($estado === 'ATIVA' && $bloqueioInicio) {
            $stage['avisos'][] = 'ATIVA_COM_RESTRICAO_DE_INICIO';
        }
        if ($resumo['sem_evidencia_conclusao']) {
            $stage['avisos'][] = 'CONCLUIDA_SEM_DATA_NO_HISTORICO';
        }
        $porFuncao[$funcao][] = $id;
        $result['etapas'][] = $stage;
    }
    usort($result['etapas'], static fn (array $a, array $b): int => ($a['ordem'] <=> $b['ordem']) ?: ($a['funcao_imagem_id'] <=> $b['funcao_imagem_id']));
    foreach ($porFuncao as $funcao => $ids) {
        if (count($ids) > 1) {
            $result['avisos'][] = ['codigo' => 'FUNCAO_COM_MULTIPLOS_REGISTROS', 'funcao_id' => $funcao, 'funcao_imagem_ids' => $ids];
        }
    }
    $groups = ['ATIVA' => 'etapas_ativas', 'CONCLUIDA' => 'etapas_concluidas', 'PENDENTE' => 'etapas_pendentes', 'BLOQUEADA' => 'etapas_bloqueadas'];
    foreach ($result['etapas'] as $stage) {
        // Group members are task IDs referencing etapas, never indexes or function IDs.
        $result[$groups[$stage['estado_visual']] ?? 'etapas_nao_operacionais'][] = $stage['funcao_imagem_id'];
    }
    $result['resumo'] = array_map('count', array_intersect_key($result, array_flip(array_merge(array_values($groups), ['etapas_nao_operacionais']))));
    return $result;
}

/** Caller must authorize obra/imagem before exposing these data through an HTTP endpoint. */
function flow_imagens_contextos_operacionais_lote(mysqli $conn, array $imagemIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $imagemIds), static fn (int $id): bool => $id > 0)));
    if (!$ids) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $conn->prepare("SELECT i.idimagens_cliente_obra AS id, i.imagem_nome, i.obra_id, o.nomenclatura AS obra_nome, i.tipo_imagem, i.subtipo_id, i.status_id, i.substatus_id, s.nome_status AS status_nome FROM imagens_cliente_obra i LEFT JOIN obra o ON o.idobra=i.obra_id LEFT JOIN status_imagem s ON s.idstatus=i.status_id WHERE i.idimagens_cliente_obra IN ($marks)");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $imagens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $stmt = $conn->prepare("SELECT fi.idfuncao_imagem, fi.imagem_id, fi.funcao_id, fi.status, fi.prazo,
        fi.colaborador_id AS responsavel_id, c.nome_colaborador AS responsavel_nome, f.nome_funcao AS funcao_nome, i.tipo_imagem
        FROM funcao_imagem fi JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=fi.imagem_id
        LEFT JOIN funcao f ON f.idfuncao=fi.funcao_id LEFT JOIN colaborador c ON c.idcolaborador=fi.colaborador_id
        WHERE fi.imagem_id IN ($marks) ORDER BY fi.imagem_id, fi.idfuncao_imagem");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $tarefas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $porImagem = [];
    foreach ($tarefas as &$tarefa) {
        $tarefa['idfuncao_imagem'] = (int) $tarefa['idfuncao_imagem'];
        $tarefa['tarefa_id'] = $tarefa['idfuncao_imagem'];
        $porImagem[(int) $tarefa['imagem_id']][] = $tarefa;
    }
    unset($tarefa);
    $logs = $tarefas ? flow_execucao_carregar_logs($conn, $tarefas) : [];
    $plans = flow_tarefa_contextos_planejamento_lote($conn, $tarefas);
    $taskIds = array_column($tarefas, 'idfuncao_imagem');
    motor_requisitos_preparar_lote($conn, $taskIds);
    $requirements = [];
    foreach ($taskIds as $id) {
        $requirements[$id] = motor_requisitos_avaliar_funcao_imagem($conn, $id);
    }
    $cycles = flow_janela_ultimos_ciclos_lote($conn, $taskIds);
    $fontesFluxo = flow_imagem_fluxo_fontes_lote($conn, $imagens);
    $result = [];
    foreach ($imagens as $imagem) {
        foreach (['id', 'obra_id', 'subtipo_id', 'status_id', 'substatus_id'] as $key) {
            $imagem[$key] = isset($imagem[$key]) ? (int) $imagem[$key] : null;
        }
        $id = $imagem['id'];
        $result[$id] = flow_imagem_contexto_operacional_montar($imagem, $porImagem[$id] ?? [], $logs, $plans, $requirements, $cycles);
        $result[$id]['fluxo'] = flow_imagem_fluxo_operacional_montar($result[$id], $fontesFluxo['previstas'][$id] ?? [], $fontesFluxo['planos'][$id] ?? null);
    }
    return $result;
}

function flow_imagem_contexto_operacional(mysqli $conn, int $imagemId): ?array
{
    return flow_imagens_contextos_operacionais_lote($conn, [$imagemId])[$imagemId] ?? null;
}
