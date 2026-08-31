<?php

/**
 * Leitura da execução do Plano de Produção R00.
 *
 * Não persiste progresso. O plano vigente vem do snapshot confirmado e os
 * fatos vêm exclusivamente de funcao_imagem + log_alteracoes.
 */

require_once __DIR__ . '/planejamento_producao_helper.php';

const FLOW_EXECUCAO_MIN_CONCLUIDAS_RITMO = 3;
const FLOW_EXECUCAO_MIN_DIAS_UTEIS_RITMO = 3;

function flow_execucao_status_cancelado(string $status): bool
{
    return flow_planejamento_normalizar($status) === 'cancelado';
}

function flow_execucao_status_nao_iniciado(string $status): bool
{
    return in_array(flow_planejamento_normalizar($status), ['', 'nao iniciado'], true);
}

/** Estados que comprovam uma tentativa operacional, inclusive fluxos legados. */
function flow_execucao_status_trabalho_efetivo(string $status): bool
{
    $status = flow_planejamento_normalizar($status);
    if (flow_execucao_status_nao_iniciado($status) || flow_planejamento_status_hold($status) || flow_execucao_status_cancelado($status)) {
        return false;
    }
    return true;
}

/**
 * Uma única camada de classificação para cálculo e execução.
 * Caderno/Filtro preserva a regra já validada: uma unidade por imagem e a
 * tarefa Caderno (função 1) é a representante quando ambas existem.
 */
function flow_execucao_itens_por_etapa(array $itens): array
{
    $grupos = array_fill_keys(array_keys(flow_planejamento_definicoes_etapas()), []);
    $canceladas = array_fill_keys(array_keys($grupos), 0);
    foreach ($itens as $item) {
        $codigo = flow_planejamento_codigo_etapa($item);
        if ($codigo === null || $codigo === 'FINALIZACAO_GLOBAL') {
            continue;
        }
        if (flow_execucao_status_cancelado((string) ($item['status'] ?? ''))) {
            $canceladas[$codigo]++;
            continue;
        }
        $item['etapa'] = $codigo;
        $item['regra_classificacao'] = flow_planejamento_regra_classificacao($item, $codigo);
        $grupos[$codigo][] = $item;
    }
    foreach ($grupos as $codigo => $grupo) {
        $grupos[$codigo] = flow_planejamento_itens_da_etapa($codigo, $grupo);
    }
    return ['grupos' => $grupos, 'canceladas' => $canceladas];
}

/** Consulta única para os históricos das tarefas reais de uma R00. */
function flow_execucao_carregar_logs(mysqli $conn, array $itens): array
{
    $ids = array_values(array_unique(array_filter(array_map(static fn (array $item): int => (int) ($item['tarefa_id'] ?? 0), $itens))));
    if (!$ids) {
        return [];
    }
    $lista = implode(',', $ids);
    $sql = "SELECT idlog, funcao_imagem_id, status_anterior, status_novo, data
              FROM log_alteracoes
             WHERE funcao_imagem_id IN ({$lista})
             ORDER BY funcao_imagem_id, data, idlog";
    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException('Não foi possível carregar o histórico da execução: ' . $conn->error);
    }
    $porTarefa = [];
    while ($row = $result->fetch_assoc()) {
        $porTarefa[(int) $row['funcao_imagem_id']][] = $row;
    }
    $result->free();
    return $porTarefa;
}

function flow_execucao_data_log(array $log): ?string
{
    $data = substr((string) ($log['data'] ?? ''), 0, 10);
    return entregas_valid_date($data) && $data !== '0000-00-00' ? $data : null;
}

/** Resume uma tarefa sem inferir data quando a trilha operacional não existe. */
function flow_execucao_resumir_tarefa(array $item, array $logs): array
{
    $statusAtual = (string) ($item['status'] ?? 'Não iniciado');
    $inicio = null;
    $conclusaoNoHistorico = null;
    foreach ($logs as $log) {
        $data = flow_execucao_data_log($log);
        if ($data === null) {
            continue;
        }
        $novo = (string) ($log['status_novo'] ?? '');
        if ($inicio === null && flow_execucao_status_trabalho_efetivo($novo)) {
            $inicio = $data;
        }
        if (flow_planejamento_status_finalizado($novo)) {
            $conclusaoNoHistorico = $data;
        }
    }

    $concluida = flow_planejamento_status_finalizado($statusAtual);
    return [
        'tarefa_id' => (int) ($item['tarefa_id'] ?? 0),
        'concluida' => $concluida,
        'hold' => flow_planejamento_status_hold($statusAtual),
        'iniciada' => $inicio !== null,
        'inicio_real' => $inicio,
        // A data somente é exposta se a tarefa ainda está atualmente fechada.
        // Assim, uma reabertura não deixa um falso "100% concluído".
        'conclusao_real' => $concluida ? $conclusaoNoHistorico : null,
        'sem_evidencia_conclusao' => $concluida && $conclusaoNoHistorico === null,
    ];
}

function flow_execucao_condicao_prazo(string $estado, ?string $limite, ?string $conclusao, string $hoje): string
{
    if (!$limite) {
        return 'SEM_LIMITE';
    }
    if ($estado === 'CONCLUIDA' && $conclusao) {
        if ($conclusao < $limite) {
            return 'ADIANTADA';
        }
        if ($conclusao > $limite) {
            return 'ATRASADA';
        }
        return 'NO_PRAZO';
    }
    if ($limite < $hoje) {
        return 'ATRASADA';
    }
    if ($limite === $hoje) {
        return 'LIMITE_HOJE';
    }
    return 'NO_PRAZO';
}

function flow_execucao_margem(?string $fim, ?string $entrega): ?int
{
    if (!$fim || !$entrega) {
        return null;
    }
    $dias = flow_planejamento_dias_uteis_entre($fim, $entrega);
    return $entrega < $fim ? -$dias : $dias;
}

/** Convenção: positivo = termina depois do marco; negativo = antecipação. */
function flow_execucao_desvio(?string $marcoPlanejado, ?string $fimProjetado): ?int
{
    if (!$marcoPlanejado || !$fimProjetado) {
        return null;
    }
    if ($fimProjetado === $marcoPlanejado) {
        return 0;
    }
    return $fimProjetado > $marcoPlanejado
        ? flow_planejamento_dias_uteis_entre($marcoPlanejado, $fimProjetado)
        : -flow_planejamento_dias_uteis_entre($fimProjetado, $marcoPlanejado);
}

function flow_execucao_duracao_restante(array $planejada, array $realizada): array
{
    $pendentes = max(0, (int) $realizada['pendentes']);
    if ($pendentes === 0) {
        return ['dias' => 0, 'metodo' => 'SEM_RESTANTE', 'taxa' => null];
    }
    $volume = max(1, (int) ($planejada['volume'] ?? $realizada['volume_atual'] ?? 1));
    $pessoas = max(1, (int) ($planejada['pessoas_alocadas'] ?? 1));
    $duracaoPlanejada = max(1, (int) ($planejada['duracao_dias_uteis'] ?? 1));
    $metrica = is_array($planejada['metrica'] ?? null) ? $planejada['metrica'] : [];
    $taxaPorPessoa = (float) ($metrica['tarefas_por_dia_util_pessoa'] ?? 0);

    // Fachada é uma janela deliberadamente fixa. A execução não altera a
    // regra do plano, mas a parte ainda não entregue reduz proporcionalmente.
    if (($planejada['estrategia_duracao'] ?? '') === 'JANELA_FIXA') {
        return [
            'dias' => max(1, (int) ceil($duracaoPlanejada * $pendentes / $volume)),
            'metodo' => 'JANELA_FIXA_PROPORCIONAL_AO_RESTANTE',
            'taxa' => null,
        ];
    }
    if ($taxaPorPessoa <= 0) {
        $taxaPorPessoa = $volume / $duracaoPlanejada / $pessoas;
    }
    $taxaPlanejada = max(0.0001, $taxaPorPessoa * $pessoas);
    $taxaUsada = $taxaPlanejada;
    $metodo = 'CAPACIDADE_PLANEJADA';

    $inicio = $realizada['inicio_real'] ?? null;
    $hoje = $realizada['data_hoje'] ?? null;
    if ($inicio && $hoje) {
        $diasObservados = flow_planejamento_dias_uteis_entre($inicio, $hoje);
        if ((int) $realizada['concluidas'] >= FLOW_EXECUCAO_MIN_CONCLUIDAS_RITMO && $diasObservados >= FLOW_EXECUCAO_MIN_DIAS_UTEIS_RITMO) {
            $taxaObservada = (int) $realizada['concluidas'] / $diasObservados;
            // Ritmo pontual somente pode piorar a projeção; ganhos de curto
            // prazo não consomem nem criam margem antes de se estabilizarem.
            if ($taxaObservada > 0 && $taxaObservada < $taxaPlanejada) {
                $taxaUsada = $taxaObservada;
                $metodo = 'RITMO_OBSERVADO_CONSERVADOR';
            }
        }
    }
    return [
        'dias' => max(1, (int) ceil($pendentes / $taxaUsada)),
        'metodo' => $metodo,
        'taxa' => round($taxaUsada, 4),
        'taxa_planejada' => round($taxaPlanejada, 4),
    ];
}

function flow_execucao_caminho_critico(array $etapas, string $fim): array
{
    $mapa = [];
    foreach ($etapas as $etapa) {
        $mapa[$etapa['codigo']] = $etapa;
    }
    $atual = null;
    foreach ($etapas as $etapa) {
        if (($etapa['fim_projetado'] ?? null) === $fim && !empty($etapa['codigo'])) {
            $atual = $etapa['codigo'];
        }
    }
    $criticas = [];
    while ($atual && empty($criticas[$atual])) {
        $criticas[$atual] = true;
        $dependencias = $mapa[$atual]['dependencias'] ?? [];
        usort($dependencias, static fn (string $a, string $b): int => strcmp((string) ($mapa[$b]['fim_projetado'] ?? ''), (string) ($mapa[$a]['fim_projetado'] ?? '')));
        $atual = $dependencias[0] ?? null;
    }
    return $criticas;
}

/**
 * Aplica execução e projeção sobre um snapshot confirmado, sem tocar nele.
 */
function flow_planejamento_monitorar_execucao(mysqli $conn, int $entregaId, array $planoVigente, array $opcoes = []): array
{
    if (($planoVigente['fonte'] ?? '') !== 'VERSAO_CONFIRMADA') {
        return ['disponivel' => false, 'motivo' => 'PLANO_AINDA_NAO_CONFIRMADO'];
    }
    $entrega = flow_planejamento_contexto_entrega($conn, $entregaId);
    $itens = flow_planejamento_carregar_itens_obra($conn, (int) $entrega['obra_id'], flow_planejamento_imagens_entrega($conn, $entregaId));
    $mapeamento = flow_execucao_itens_por_etapa($itens);
    $todos = array_merge(...array_values($mapeamento['grupos']));
    $logsPorTarefa = flow_execucao_carregar_logs($conn, $todos);
    return flow_planejamento_monitorar_execucao_com_dados($planoVigente, $itens, $logsPorTarefa, $opcoes);
}

/** Parte pura do monitor: permite testes determinísticos sem banco. */
function flow_planejamento_monitorar_execucao_com_dados(array $planoVigente, array $itens, array $logsPorTarefa, array $opcoes = []): array
{
    if (($planoVigente['fonte'] ?? '') !== 'VERSAO_CONFIRMADA') {
        return ['disponivel' => false, 'motivo' => 'PLANO_AINDA_NAO_CONFIRMADO'];
    }
    $hoje = (string) ($opcoes['data_hoje'] ?? $planoVigente['data_hoje'] ?? date('Y-m-d'));
    if (!entregas_valid_date($hoje)) {
        $hoje = date('Y-m-d');
    }
    $mapeamento = flow_execucao_itens_por_etapa($itens);
    $planejadas = [];
    foreach ((array) ($planoVigente['etapas'] ?? []) as $etapa) {
        $planejadas[(string) $etapa['codigo']] = $etapa;
    }
    $ordem = array_map(static fn (array $etapa): string => (string) $etapa['codigo'], (array) ($planoVigente['etapas'] ?? []));
    $etapas = [];
    $excecoes = [];

    foreach ($ordem as $codigo) {
        $planejada = $planejadas[$codigo];
        if ($codigo === 'FINALIZACAO_GLOBAL') {
            continue;
        }
        $grupo = $mapeamento['grupos'][$codigo] ?? [];
        $resumos = array_map(static fn (array $item): array => flow_execucao_resumir_tarefa($item, $logsPorTarefa[(int) ($item['tarefa_id'] ?? 0)] ?? []), $grupo);
        $volumeAtual = count($resumos);
        $concluidas = count(array_filter($resumos, static fn (array $resumo): bool => $resumo['concluida']));
        $inicios = array_values(array_filter(array_column($resumos, 'inicio_real')));
        $conclusoes = array_values(array_filter(array_column($resumos, 'conclusao_real')));
        $temEvidenciaTrabalho = !empty($inicios) || $concluidas > 0 || count(array_filter($resumos, static fn (array $resumo): bool => $resumo['hold'])) > 0;
        $estado = $volumeAtual === 0 ? 'NAO_APLICAVEL' : ($concluidas === $volumeAtual ? 'CONCLUIDA' : ($temEvidenciaTrabalho ? 'EM_ANDAMENTO' : 'NAO_INICIADA'));
        $inicioReal = $inicios ? min($inicios) : null;
        $conclusaoReal = $estado === 'CONCLUIDA' && $conclusoes ? max($conclusoes) : null;
        $hold = count(array_filter($resumos, static fn (array $resumo): bool => $resumo['hold']));
        $canceladas = (int) ($mapeamento['canceladas'][$codigo] ?? 0);
        $etapa = [
            'codigo' => $codigo,
            'volume_planejado' => (int) ($planejada['volume'] ?? 0),
            'volume_atual' => $volumeAtual,
            'concluidas' => $concluidas,
            'pendentes' => max(0, $volumeAtual - $concluidas),
            'percentual_concluido' => $volumeAtual ? round(100 * $concluidas / $volumeAtual, 1) : 0.0,
            'execucao' => $estado,
            'inicio_real' => $inicioReal,
            'conclusao_real' => $conclusaoReal,
            'limite_planejado' => $planejada['limite'] ?? null,
            'dependencias' => $planejada['dependencias'] ?? [],
            'hold' => $hold,
            'canceladas' => $canceladas,
            'data_hoje' => $hoje,
        ];
        $etapa['condicao_prazo'] = flow_execucao_condicao_prazo($estado, $etapa['limite_planejado'], $conclusaoReal, $hoje);
        if ($volumeAtual !== (int) ($planejada['volume'] ?? 0)) {
            $excecoes[] = ['codigo' => 'ESCOPO_OPERACIONAL_DIVERGENTE', 'etapa' => $codigo, 'volume_planejado' => (int) ($planejada['volume'] ?? 0), 'volume_atual' => $volumeAtual, 'canceladas' => $canceladas];
        }
        if ($hold > 0) {
            $excecoes[] = ['codigo' => 'TAREFAS_EM_HOLD', 'etapa' => $codigo, 'quantidade' => $hold];
        }
        $etapas[$codigo] = $etapa;
    }

    // Marco virtual: não tem tarefa nem percentual artificial; ele só fecha
    // quando todos os pools realmente aplicáveis fecham.
    if (isset($planejadas['FINALIZACAO_GLOBAL'])) {
        $pools = array_values(array_filter(['FINALIZACAO_EXTERNA', 'FINALIZACAO_INTERNA', 'FINALIZACAO_PLANTA'], static fn (string $codigo): bool => !empty($etapas[$codigo]) && $etapas[$codigo]['execucao'] !== 'NAO_APLICAVEL'));
        $concluida = $pools && !array_filter($pools, static fn (string $codigo): bool => $etapas[$codigo]['execucao'] !== 'CONCLUIDA');
        $iniciosPools = array_values(array_filter(array_map(static fn (string $codigo): ?string => $etapas[$codigo]['inicio_real'], $pools)));
        $conclusoesPools = array_values(array_filter(array_map(static fn (string $codigo): ?string => $etapas[$codigo]['conclusao_real'], $pools)));
        $etapas['FINALIZACAO_GLOBAL'] = [
            'codigo' => 'FINALIZACAO_GLOBAL', 'volume_planejado' => (int) ($planejadas['FINALIZACAO_GLOBAL']['volume'] ?? 0),
            'volume_atual' => array_sum(array_map(static fn (string $codigo): int => $etapas[$codigo]['volume_atual'], $pools)),
            'concluidas' => array_sum(array_map(static fn (string $codigo): int => $etapas[$codigo]['concluidas'], $pools)),
            'pendentes' => array_sum(array_map(static fn (string $codigo): int => $etapas[$codigo]['pendentes'], $pools)),
            'percentual_concluido' => null, 'execucao' => !$pools ? 'NAO_APLICAVEL' : ($concluida ? 'CONCLUIDA' : (array_filter($pools, static fn (string $codigo): bool => $etapas[$codigo]['execucao'] !== 'NAO_INICIADA') ? 'EM_ANDAMENTO' : 'NAO_INICIADA')),
            'inicio_real' => $iniciosPools ? min($iniciosPools) : null,
            'conclusao_real' => $concluida && $conclusoesPools ? max($conclusoesPools) : null,
            'limite_planejado' => $planejadas['FINALIZACAO_GLOBAL']['limite'] ?? null, 'dependencias' => $pools, 'hold' => array_sum(array_map(static fn (string $codigo): int => $etapas[$codigo]['hold'], $pools)), 'canceladas' => 0, 'data_hoje' => $hoje,
        ];
        $etapas['FINALIZACAO_GLOBAL']['condicao_prazo'] = flow_execucao_condicao_prazo($etapas['FINALIZACAO_GLOBAL']['execucao'], $etapas['FINALIZACAO_GLOBAL']['limite_planejado'], $etapas['FINALIZACAO_GLOBAL']['conclusao_real'], $hoje);
    }

    foreach ($ordem as $codigo) {
        if (!isset($etapas[$codigo])) {
            continue;
        }
        $realizada = &$etapas[$codigo];
        $planejada = $planejadas[$codigo];
        $terminosDependencias = array_values(array_filter(array_map(static fn (string $dependencia): ?string => $etapas[$dependencia]['fim_projetado'] ?? null, $realizada['dependencias'])));
        if ($codigo === 'FINALIZACAO_GLOBAL') {
            $realizada['inicio_projetado'] = $terminosDependencias ? min($terminosDependencias) : null;
            $realizada['fim_projetado'] = $terminosDependencias ? max($terminosDependencias) : null;
            $realizada['duracao_restante_dias_uteis'] = 0;
            $realizada['metodo_projecao'] = 'MAXIMO_DAS_FRENTES_APLICAVEIS';
        } elseif ($realizada['execucao'] === 'CONCLUIDA') {
            $realizada['inicio_projetado'] = $realizada['inicio_real'];
            $realizada['fim_projetado'] = $realizada['conclusao_real'];
            $realizada['duracao_restante_dias_uteis'] = 0;
            $realizada['metodo_projecao'] = 'CONCLUSAO_REAL';
        } else {
            $inicio = max(array_merge([$hoje], $terminosDependencias));
            $restante = flow_execucao_duracao_restante($planejada, $realizada);
            $realizada['inicio_projetado'] = $inicio;
            $realizada['fim_projetado'] = flow_planejamento_adicionar_dias_uteis($inicio, (int) $restante['dias']);
            $realizada['duracao_restante_dias_uteis'] = $restante['dias'];
            $realizada['metodo_projecao'] = $restante['metodo'];
            $realizada['taxa_projecao_tarefas_dia'] = $restante['taxa'];
            $realizada['taxa_planejada_tarefas_dia'] = $restante['taxa_planejada'] ?? null;
        }
        $realizada['desvio_projetado_dias_uteis'] = flow_execucao_desvio($realizada['limite_planejado'], $realizada['fim_projetado']);
        unset($realizada);
    }

    $lista = array_values($etapas);
    $fimProjetado = null;
    foreach ($lista as $etapa) {
        if (($etapa['fim_projetado'] ?? null) && ($fimProjetado === null || $etapa['fim_projetado'] > $fimProjetado)) {
            $fimProjetado = $etapa['fim_projetado'];
        }
    }
    $criticas = $fimProjetado ? flow_execucao_caminho_critico($lista, $fimProjetado) : [];
    $atrasosCriticos = [];
    foreach ($lista as &$etapa) {
        $etapa['caminho_critico_projetado'] = !empty($criticas[$etapa['codigo']]);
        if ($etapa['caminho_critico_projetado'] && $etapa['execucao'] !== 'CONCLUIDA' && ($etapa['fim_projetado'] ?? '') > ($etapa['limite_planejado'] ?? '9999-12-31')) {
            $atrasosCriticos[] = $etapa;
        }
    }
    unset($etapa);
    // Uma cadeia propagada representa uma única decisão gerencial. Exibir
    // cada nó atrasado criaria ruído e duplicaria o mesmo impacto temporal.
    if ($atrasosCriticos) {
        usort($atrasosCriticos, static fn (array $a, array $b): int => strcmp((string) $a['limite_planejado'], (string) $b['limite_planejado']));
        $atraso = $atrasosCriticos[0];
        $excecoes[] = ['codigo' => 'CADEIA_CRITICA_PROJETADA_ATRASADA', 'etapa' => $atraso['codigo'], 'limite' => $atraso['limite_planejado'], 'fim_projetado' => $atraso['fim_projetado']];
    }
    $margemPlanejada = $planoVigente['margem_dias_uteis'] ?? null;
    $margemProjetada = flow_execucao_margem($fimProjetado, $planoVigente['data_entrega'] ?? null);
    if ($margemProjetada !== null && $margemProjetada < 0) {
        $excecoes[] = ['codigo' => 'ENTREGA_R00_EM_RISCO', 'fim_projetado' => $fimProjetado, 'data_entrega' => $planoVigente['data_entrega']];
    }
    $naoConcluidas = array_values(array_filter($lista, static fn (array $etapa): bool => $etapa['execucao'] !== 'CONCLUIDA' && $etapa['execucao'] !== 'NAO_APLICAVEL'));
    if ($naoConcluidas) {
        usort($naoConcluidas, static fn (array $a, array $b): int => strcmp((string) ($a['limite_planejado'] ?? '9999-12-31'), (string) ($b['limite_planejado'] ?? '9999-12-31')));
    }
    $proximo = $naoConcluidas[0] ?? null;
    $gargalos = array_values(array_filter($lista, static fn (array $etapa): bool => !empty($etapa['caminho_critico_projetado']) && $etapa['execucao'] !== 'CONCLUIDA' && $etapa['codigo'] !== 'FINALIZACAO_GLOBAL'));
    usort($gargalos, static function (array $a, array $b): int {
        $desvioA = (int) ($a['desvio_projetado_dias_uteis'] ?? PHP_INT_MIN);
        $desvioB = (int) ($b['desvio_projetado_dias_uteis'] ?? PHP_INT_MIN);
        if ($desvioA !== $desvioB) {
            return $desvioB <=> $desvioA;
        }
        return strcmp((string) ($a['limite_planejado'] ?? ''), (string) ($b['limite_planejado'] ?? ''));
    });
    $gargalo = $gargalos[0] ?? null;
    $saude = !$naoConcluidas ? 'CONCLUIDA' : ($margemProjetada !== null && $margemProjetada < 0 ? 'EM_RISCO' : (($margemProjetada !== null && $margemPlanejada !== null && ($margemProjetada <= 2 || $margemProjetada < $margemPlanejada)) || array_filter($excecoes, static fn (array $excecao): bool => in_array($excecao['codigo'], ['TAREFAS_EM_HOLD', 'ESCOPO_OPERACIONAL_DIVERGENTE'], true)) ? 'ATENCAO' : 'NO_PRAZO'));
    return [
        'disponivel' => true, 'data_referencia' => $hoje, 'etapas' => $lista,
        'fim_planejado' => $planoVigente['fim_previsto'] ?? null, 'fim_projetado' => $fimProjetado,
        'margem_planejada_dias_uteis' => $margemPlanejada, 'margem_projetada_dias_uteis' => $margemProjetada,
        'impacto_margem_dias_uteis' => $margemPlanejada !== null && $margemProjetada !== null ? $margemProjetada - $margemPlanejada : null,
        'saude' => $saude, 'proximo_marco' => $proximo ? $proximo['codigo'] : null, 'gargalo' => $gargalo ? $gargalo['codigo'] : null,
        'excecoes' => $excecoes,
    ];
}
