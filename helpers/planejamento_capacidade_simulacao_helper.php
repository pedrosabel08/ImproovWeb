<?php

/**
 * Sandbox de decisão da capacidade global.
 *
 * Não persiste datas, capacidade ou versões. Ele reaproveita o motor de
 * planejamento individual para os planos alterados e o motor global para
 * comparar a demanda resultante em todo o horizonte analisado.
 */

require_once __DIR__ . '/planejamento_capacidade_global_helper.php';

function flow_capacidade_simulacao_configuracoes(mysqli $conn): array
{
    $configuracoes = flow_capacidade_carregar_configuracoes_colaboradores($conn);
    foreach (flow_capacidade_carregar_configuracoes($conn) as $codigo => $persistida) {
        if (!empty($persistida['overrides']) && isset($configuracoes[$codigo])) {
            $configuracoes[$codigo]['overrides'] = $persistida['overrides'];
            $configuracoes[$codigo]['origem'] .= '_COM_OVERRIDES';
        }
    }
    return flow_capacidade_normalizar_configuracoes($configuracoes);
}

function flow_capacidade_simulacao_normalizar_acoes(array $acoes): array
{
    $resultado = [];
    foreach ($acoes as $acao) {
        if (!is_array($acao)) {
            continue;
        }
        $tipo = strtoupper(trim((string) ($acao['tipo'] ?? '')));
        $entregaId = (int) ($acao['entrega_id'] ?? 0);
        $etapa = strtoupper(trim((string) ($acao['codigo_etapa'] ?? '')));
        if (!in_array($tipo, ['DESLOCAR_ETAPA', 'ALTERAR_CAPACIDADE', 'APOIO_SECUNDARIO', 'CAPACIDADE_EXTRAORDINARIA', 'CAPACIDADE_EXTERNA'], true)
            || !isset(flow_planejamento_definicoes_etapas()[$etapa])) {
            continue;
        }
        if ($tipo === 'APOIO_SECUNDARIO') {
            $quantidade = max(1, min(20, (int) ($acao['quantidade'] ?? 0)));
            $resultado[] = ['tipo' => $tipo, 'codigo_etapa' => $etapa, 'quantidade' => $quantidade];
            continue;
        }
        if ($tipo === 'CAPACIDADE_EXTRAORDINARIA') {
            $data = (string) ($acao['data'] ?? '');
            $pessoas = max(1, min(20, (int) ($acao['pessoas'] ?? 0)));
            if (flow_capacidade_simulacao_data_calendario_valida($data)) {
                $resultado[] = [
                    'tipo' => $tipo,
                    'codigo_etapa' => $etapa,
                    'data' => $data,
                    'pessoas' => $pessoas,
                    'origem' => strtoupper(trim((string) ($acao['origem'] ?? 'TRABALHO_EXTRAORDINARIO'))),
                ];
            }
            continue;
        }
        if ($tipo === 'CAPACIDADE_EXTERNA') {
            $dataInicio = (string) ($acao['data_inicio'] ?? '');
            $dataFim = (string) ($acao['data_fim'] ?? '');
            $pessoas = max(1, min(20, (int) ($acao['pessoas'] ?? 0)));
            if (!flow_capacidade_simulacao_data_calendario_valida($dataInicio)
                || !flow_capacidade_simulacao_data_calendario_valida($dataFim) || $dataFim < $dataInicio) {
                continue;
            }
            $distribuicao = [];
            foreach ((array) ($acao['distribuicao'] ?? []) as $dia) {
                $data = (string) ($dia['data'] ?? '');
                $quantidade = max(0, min(20, (int) ($dia['pessoas'] ?? 0)));
                if (flow_capacidade_simulacao_data_calendario_valida($data) && $data >= $dataInicio && $data <= $dataFim && $quantidade > 0) {
                    $distribuicao[$data] = $quantidade;
                }
            }
            $resultado[] = [
                'tipo' => $tipo,
                'codigo_etapa' => $etapa,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'pessoas' => $pessoas,
                'distribuicao' => array_map(static fn (string $data, int $quantidade): array => ['data' => $data, 'pessoas' => $quantidade], array_keys($distribuicao), array_values($distribuicao)),
            ];
            continue;
        }
        if ($entregaId <= 0) {
            continue;
        }
        if ($tipo === 'DESLOCAR_ETAPA') {
            $dias = max(0, min(60, (int) ($acao['dias_uteis'] ?? 0)));
            if ($dias > 0) {
                $resultado[] = ['tipo' => $tipo, 'entrega_id' => $entregaId, 'codigo_etapa' => $etapa, 'dias_uteis' => $dias];
            }
            continue;
        }
        $pessoas = max(1, min(20, (int) ($acao['pessoas'] ?? 0)));
        $resultado[] = ['tipo' => $tipo, 'entrega_id' => $entregaId, 'codigo_etapa' => $etapa, 'pessoas' => $pessoas];
    }
    return $resultado;
}

/** Datas de cenário aceitam fins de semana e feriados; não mudam o calendário. */
function flow_capacidade_simulacao_data_calendario_valida(string $data): bool
{
    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
    return $objeto instanceof DateTimeImmutable && $objeto->format('Y-m-d') === $data;
}

function flow_capacidade_simulacao_eh_dia_util(string $data): bool
{
    if (!flow_capacidade_simulacao_data_calendario_valida($data)) {
        return false;
    }
    return flow_planejamento_adicionar_dias_uteis(date('Y-m-d', strtotime($data . ' -1 day')), 1) === $data;
}

function flow_capacidade_simulacao_semana(array $resultado, string $codigo, string $semana): ?array
{
    foreach (($resultado['etapas'] ?? []) as $etapa) {
        if (($etapa['codigo_etapa'] ?? '') !== $codigo) {
            continue;
        }
        foreach (($etapa['semanas'] ?? []) as $item) {
            if (($item['semana'] ?? '') === $semana) {
                return $item;
            }
        }
    }
    return null;
}

function flow_capacidade_simulacao_chave_conflito(array $conflito): string
{
    return implode('|', [
        (string) ($conflito['codigo_etapa'] ?? ''),
        (string) ($conflito['data_inicio'] ?? ''),
        (string) ($conflito['data_fim'] ?? ''),
    ]);
}

function flow_capacidade_simulacao_conflitos(array $resultado): array
{
    $conflitos = [];
    foreach (($resultado['conflitos'] ?? []) as $conflito) {
        $conflitos[flow_capacidade_simulacao_chave_conflito($conflito)] = $conflito;
    }
    return $conflitos;
}

/** Pesos operacionais centralizados; ainda não representam custo financeiro. */
function flow_capacidade_simulacao_pesos(): array
{
    return [
        'APOIO_SECUNDARIO_PESSOA' => 10,
        'DESLOCAMENTO_DIA_UTIL' => 15,
        'ALTERACAO_CAPACIDADE_PESSOA' => 25,
        'EXTRA_SABADO_PESSOA_DIA' => 35,
        'EXTRA_DOMINGO_OU_FERIADO_PESSOA_DIA' => 55,
        'CAPACIDADE_EXTERNA_PESSOA_DIA' => 80,
        'NOVO_CONFLITO' => 10000,
        'INVIAVEL' => 100000,
    ];
}

function flow_capacidade_simulacao_capacidade_externa_por_dia(array $acoes): array
{
    $resultado = [];
    foreach ($acoes as $acao) {
        if (($acao['tipo'] ?? '') !== 'CAPACIDADE_EXTERNA') {
            continue;
        }
        $codigo = (string) $acao['codigo_etapa'];
        $distribuicao = (array) ($acao['distribuicao'] ?? []);
        if (!$distribuicao) {
            $cursor = (string) $acao['data_inicio'];
            while ($cursor <= (string) $acao['data_fim']) {
                if (flow_capacidade_simulacao_eh_dia_util($cursor)) {
                    $distribuicao[] = ['data' => $cursor, 'pessoas' => (int) $acao['pessoas']];
                }
                $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
            }
        }
        foreach ($distribuicao as $dia) {
            $data = (string) ($dia['data'] ?? '');
            if (!flow_capacidade_simulacao_eh_dia_util($data)) {
                continue;
            }
            $resultado[$codigo][$data] = ($resultado[$codigo][$data] ?? 0.0) + (float) ($dia['pessoas'] ?? 0);
        }
    }
    return $resultado;
}

/**
 * O motor global trabalha por janela integral. Para não fingir que sábado é
 * útil, o trabalho extraordinário vira crédito explícito de pessoa-dia e é
 * alocado somente contra o déficit já existente da mesma etapa.
 */
function flow_capacidade_simulacao_alivio_extraordinario(array $resultado, array $acoes): array
{
    $creditos = [];
    foreach ($acoes as $acao) {
        if (($acao['tipo'] ?? '') !== 'CAPACIDADE_EXTRAORDINARIA') {
            continue;
        }
        $codigo = (string) $acao['codigo_etapa'];
        $creditos[$codigo] = ($creditos[$codigo] ?? 0.0) + (float) ($acao['pessoas'] ?? 0);
    }
    $alivios = [];
    foreach (($resultado['etapas'] ?? []) as $etapa) {
        $codigo = (string) ($etapa['codigo_etapa'] ?? '');
        $credito = (float) ($creditos[$codigo] ?? 0.0);
        if ($credito <= 0.0) {
            continue;
        }
        $dias = array_values(array_filter((array) ($etapa['dias'] ?? []), static fn (array $dia): bool => (float) ($dia['deficit'] ?? 0) > 0.0));
        usort($dias, static fn (array $a, array $b): int => ((float) $b['deficit'] <=> (float) $a['deficit']) ?: strcmp((string) $a['data'], (string) $b['data']));
        foreach ($dias as $dia) {
            if ($credito <= 0.0) {
                break;
            }
            $uso = min($credito, (float) $dia['deficit']);
            $alivios[$codigo][(string) $dia['data']] = $uso;
            $credito -= $uso;
        }
    }
    return $alivios;
}

function flow_capacidade_simulacao_recalcular_global(array $global, array $configuracoes, array $acoes): array
{
    $externa = flow_capacidade_simulacao_capacidade_externa_por_dia($acoes);
    $alivios = flow_capacidade_simulacao_alivio_extraordinario($global, $acoes);
    foreach ($global['etapas'] as &$etapa) {
        $codigo = (string) ($etapa['codigo_etapa'] ?? '');
        foreach ($etapa['dias'] as &$dia) {
            $data = (string) $dia['data'];
            $adicionalExterno = (float) ($externa[$codigo][$data] ?? 0.0);
            $alivio = (float) ($alivios[$codigo][$data] ?? 0.0);
            if ($adicionalExterno <= 0.0 && $alivio <= 0.0) {
                continue;
            }
            $dia['demanda_planejada'] = flow_capacidade_numero(max(0.0, (float) $dia['demanda_planejada'] - $alivio));
            $dia['capacidade_externa'] = flow_capacidade_numero($adicionalExterno);
            $dia['capacidade_extraordinaria_compensada'] = flow_capacidade_numero($alivio);
            $disponibilidade = flow_capacidade_disponivel_em($codigo, $data, $configuracoes);
            $principal = (float) ($disponibilidade['capacidade_principal'] ?? 0) + $adicionalExterno;
            $secundaria = (float) ($disponibilidade['capacidade_secundaria'] ?? 0);
            $estado = flow_capacidade_classificar($principal, $secundaria, (float) $dia['demanda_planejada'], !empty($disponibilidade['configurada']));
            $dia = array_merge($dia, $estado, [
                'capacidade' => flow_capacidade_numero($principal),
                'capacidade_principal' => flow_capacidade_numero((float) ($disponibilidade['capacidade_principal'] ?? 0)),
                'capacidade_externa' => flow_capacidade_numero($adicionalExterno),
                'capacidade_total' => flow_capacidade_numero($principal + $secundaria),
            ]);
        }
        unset($dia);
        $etapa['conflitos'] = flow_capacidade_agrupar_conflitos($etapa['dias']);
        foreach ($etapa['conflitos'] as &$conflito) {
            $conflito['codigo_etapa'] = $codigo;
            $conflito['etapa'] = $etapa['etapa'];
        }
        unset($conflito);
        $etapa['semanas'] = flow_capacidade_resumo_semanal($etapa['dias']);
    }
    unset($etapa);
    $global['conflitos'] = [];
    foreach ($global['etapas'] as $etapa) {
        foreach ($etapa['conflitos'] as $conflito) {
            $global['conflitos'][] = $conflito;
        }
    }
    $global['resumo']['conflitos'] = count($global['conflitos']);
    return $global;
}

function flow_capacidade_simulacao_resumo_intervencoes(array $acoes): array
{
    $extra = [];
    $externa = [];
    foreach ($acoes as $acao) {
        if (($acao['tipo'] ?? '') === 'CAPACIDADE_EXTRAORDINARIA') {
            $extra[] = $acao + ['pessoa_dias' => (int) ($acao['pessoas'] ?? 0), 'depende_validacao_operacional' => true];
        }
        if (($acao['tipo'] ?? '') === 'CAPACIDADE_EXTERNA') {
            $pessoaDias = array_sum(array_map(static fn (array $dia): int => (int) ($dia['pessoas'] ?? 0), (array) ($acao['distribuicao'] ?? [])));
            if ($pessoaDias === 0) {
                foreach (flow_capacidade_simulacao_capacidade_externa_por_dia([$acao])[(string) $acao['codigo_etapa']] ?? [] as $quantidade) {
                    $pessoaDias += (int) $quantidade;
                }
            }
            $externa[] = $acao + ['pessoa_dias' => $pessoaDias, 'pico_pessoas' => (int) ($acao['pessoas'] ?? 0), 'depende_validacao_operacional' => true];
        }
    }
    return ['extraordinaria' => $extra, 'externa' => $externa];
}

function flow_capacidade_simulacao_score(array $acoes, array $resultado): int
{
    $pesos = flow_capacidade_simulacao_pesos();
    $score = 0;
    foreach ($acoes as $acao) {
        $tipo = (string) ($acao['tipo'] ?? '');
        if ($tipo === 'APOIO_SECUNDARIO') {
            $score += $pesos['APOIO_SECUNDARIO_PESSOA'] * (int) $acao['quantidade'];
        }
        if ($tipo === 'DESLOCAR_ETAPA') {
            $score += $pesos['DESLOCAMENTO_DIA_UTIL'] * (int) $acao['dias_uteis'];
        }
        if ($tipo === 'CAPACIDADE_EXTRAORDINARIA') {
            $score += (date('N', strtotime((string) $acao['data'])) === '6' ? $pesos['EXTRA_SABADO_PESSOA_DIA'] : $pesos['EXTRA_DOMINGO_OU_FERIADO_PESSOA_DIA']) * (int) $acao['pessoas'];
        }
        if ($tipo === 'CAPACIDADE_EXTERNA') {
            $pessoaDias = array_sum(array_map(static fn (array $d): int => (int) ($d['pessoas'] ?? 0), (array) ($acao['distribuicao'] ?? [])));
            if ($pessoaDias === 0) {
                foreach (flow_capacidade_simulacao_capacidade_externa_por_dia([$acao])[(string) $acao['codigo_etapa']] ?? [] as $quantidade) {
                    $pessoaDias += (int) $quantidade;
                }
            }
            $score += $pesos['CAPACIDADE_EXTERNA_PESSOA_DIA'] * $pessoaDias;
        }
        if ($tipo === 'ALTERAR_CAPACIDADE') {
            $score += $pesos['ALTERACAO_CAPACIDADE_PESSOA'] * (int) ($acao['pessoas'] ?? 1);
        }
    }
    $score += $pesos['NOVO_CONFLITO'] * count($resultado['novos_conflitos'] ?? []);
    if (($resultado['classificacao'] ?? '') === 'INVIAVEL') {
        $score += $pesos['INVIAVEL'];
    }
    return $score;
}

function flow_capacidade_simulacao_pessoas_do_plano(array $plano): array
{
    $pessoas = [];
    foreach (($plano['etapas'] ?? []) as $etapa) {
        $codigo = (string) ($etapa['codigo_etapa'] ?? $etapa['codigo'] ?? '');
        if ($codigo !== '' && !empty($etapa['capacidade_editavel'])) {
            $pessoas[$codigo] = max(1, (int) ($etapa['pessoas_alocadas'] ?? 1));
        }
    }
    return $pessoas;
}

function flow_capacidade_simulacao_plano_para_global(array $planoBase, array $planoCalculado): array
{
    $plano = $planoBase;
    $plano['margem_dias_uteis'] = $planoCalculado['margem_dias_uteis'];
    $plano['status_plano'] = $planoCalculado['status_plano'];
    $plano['etapas'] = [];
    foreach (($planoCalculado['etapas'] ?? []) as $etapa) {
        $plano['etapas'][] = [
            'codigo_etapa' => $etapa['codigo'],
            'data_inicio' => $etapa['inicio'] ?? null,
            'data_limite' => $etapa['limite'] ?? null,
            'pessoas_alocadas' => $etapa['pessoas_alocadas'] ?? 1,
            'capacidade_editavel' => !empty($etapa['capacidade_editavel']),
            'metadados_json' => ['nao_aplicavel' => !empty($etapa['nao_aplicavel'])],
        ];
    }
    return $plano;
}

function flow_capacidade_simulacao_classificar(array $original, array $simulado, ?array $semanaOriginal, ?array $semanaSimulada, bool $dependeValidacao): string
{
    $margensInvalidas = array_filter($simulado['planos_afetados'] ?? [], static fn (array $plano): bool => (int) ($plano['depois']['margem_dias_uteis'] ?? 0) < 0);
    if ($margensInvalidas) {
        return 'INVIAVEL';
    }
    $deficitAntes = (float) ($semanaOriginal['deficit_maximo'] ?? 0);
    $deficitDepois = (float) ($semanaSimulada['deficit_maximo'] ?? 0);
    $novos = count($simulado['novos_conflitos'] ?? []);
    if ($deficitDepois <= 0 && $deficitAntes > 0) {
        return $novos > 0 ? 'TRANSFERE_PROBLEMA' : ($dependeValidacao ? 'RESOLVE_COM_VALIDACAO' : 'RESOLVE');
    }
    if ($deficitDepois < $deficitAntes) {
        return 'RESOLVE_PARCIALMENTE';
    }
    if ($novos > 0) {
        return 'TRANSFERE_PROBLEMA';
    }
    return $dependeValidacao ? 'DEPENDENTE_DE_APOIO' : 'SEM_GANHO';
}

/**
 * Função pura: recebe planos já carregados e um callback para recalcular
 * apenas as R00s alteradas. É a base dos testes sem banco.
 */
function flow_capacidade_simular_com_planos(array $planos, array $configuracoes, string $inicio, string $fim, string $codigoEtapa, string $semana, array $acoes, callable $recalcularPlano): array
{
    $acoes = flow_capacidade_simulacao_normalizar_acoes($acoes);
    $original = flow_capacidade_calcular_demanda_planejada($planos, $inicio, $fim, $configuracoes);
    $semanaOriginal = flow_capacidade_simulacao_semana($original, $codigoEtapa, $semana);
    if (!$semanaOriginal) {
        throw new InvalidArgumentException('O conflito ou período selecionado não existe mais. Recarregue o painel.');
    }

    $porEntrega = [];
    $apoios = [];
    foreach ($acoes as $acao) {
        if (in_array($acao['tipo'], ['APOIO_SECUNDARIO', 'CAPACIDADE_EXTRAORDINARIA', 'CAPACIDADE_EXTERNA'], true)) {
            if ($acao['tipo'] !== 'APOIO_SECUNDARIO') {
                continue;
            }
            $apoios[$acao['codigo_etapa']] = ($apoios[$acao['codigo_etapa']] ?? 0) + $acao['quantidade'];
            continue;
        }
        $porEntrega[(int) $acao['entrega_id']][] = $acao;
    }

    $substitutos = [];
    $planosAfetados = [];
    foreach ($planos as $indice => $plano) {
        $entregaId = (int) ($plano['entrega_id'] ?? 0);
        if (!isset($porEntrega[$entregaId])) {
            continue;
        }
        $pessoas = flow_capacidade_simulacao_pessoas_do_plano($plano);
        $deslocamentos = [];
        foreach ($porEntrega[$entregaId] as $acao) {
            if ($acao['tipo'] === 'ALTERAR_CAPACIDADE') {
                $pessoas[$acao['codigo_etapa']] = $acao['pessoas'];
            }
            if ($acao['tipo'] === 'DESLOCAR_ETAPA') {
                $deslocamentos[$acao['codigo_etapa']] = ($deslocamentos[$acao['codigo_etapa']] ?? 0) + $acao['dias_uteis'];
            }
        }
        $recalculado = $recalcularPlano($plano, $pessoas, $deslocamentos);
        $substitutos[$indice] = flow_capacidade_simulacao_plano_para_global($plano, $recalculado);
        $etapasAntes = [];
        foreach (($plano['etapas'] ?? []) as $etapaAntes) {
            $etapasAntes[(string) ($etapaAntes['codigo_etapa'] ?? '')] = [
                'inicio' => $etapaAntes['data_inicio'] ?? null,
                'limite' => $etapaAntes['data_limite'] ?? null,
            ];
        }
        $etapasDepois = [];
        foreach (($recalculado['etapas'] ?? []) as $etapaDepois) {
            $codigoDepois = (string) ($etapaDepois['codigo'] ?? '');
            if ($codigoDepois !== '') {
                $etapasDepois[$codigoDepois] = ['inicio' => $etapaDepois['inicio'] ?? null, 'limite' => $etapaDepois['limite'] ?? null];
            }
        }
        $planosAfetados[] = [
            'entrega_id' => $entregaId,
            'obra_id' => (int) ($plano['obra_id'] ?? 0),
            'obra' => (string) ($plano['nomenclatura'] ?? $plano['nome_obra'] ?? ''),
            'versao_id' => (int) ($plano['versao_id'] ?? 0),
            'estado' => (string) ($plano['estado'] ?? ''),
            'antes' => ['margem_dias_uteis' => $plano['margem_dias_uteis'] ?? null, 'status_plano' => $plano['status_plano'] ?? null],
            'depois' => ['margem_dias_uteis' => $recalculado['margem_dias_uteis'] ?? null, 'status_plano' => $recalculado['status_plano'] ?? null, 'fim_previsto' => $recalculado['fim_previsto'] ?? null],
            'pessoas' => $pessoas,
            'deslocamentos_etapas' => $deslocamentos,
            'etapas_antes' => $etapasAntes,
            'etapas_depois' => $etapasDepois,
        ];
    }
    foreach ($porEntrega as $entregaId => $_) {
        if (!array_filter($planosAfetados, static fn (array $plano): bool => (int) $plano['entrega_id'] === (int) $entregaId)) {
            throw new InvalidArgumentException('A R00 selecionada não está disponível no horizonte atual. Reabra o conflito.');
        }
    }
    foreach ($substitutos as $indice => $plano) {
        $planos[$indice] = $plano;
    }

    $simuladoGlobal = flow_capacidade_calcular_demanda_planejada($planos, $inicio, $fim, $configuracoes);
    $simuladoGlobal = flow_capacidade_simulacao_recalcular_global($simuladoGlobal, $configuracoes, $acoes);
    $semanaSimulada = flow_capacidade_simulacao_semana($simuladoGlobal, $codigoEtapa, $semana);
    $conflitosAntes = flow_capacidade_simulacao_conflitos($original);
    $conflitosDepois = flow_capacidade_simulacao_conflitos($simuladoGlobal);
    $resolvidos = array_values(array_diff_key($conflitosAntes, $conflitosDepois));
    $novos = array_values(array_diff_key($conflitosDepois, $conflitosAntes));
    $capacidadeApoio = (float) (($configuracoes[$codigoEtapa]['capacidade_secundaria'] ?? 0));
    $apoioSolicitado = (int) ($apoios[$codigoEtapa] ?? 0);
    $intervencoes = flow_capacidade_simulacao_resumo_intervencoes($acoes);
    $dependeApoio = $apoioSolicitado > 0;
    $dependeValidacao = $dependeApoio || !empty($intervencoes['extraordinaria']) || !empty($intervencoes['externa']);
    $excecoes = [];
    if ($apoioSolicitado > $capacidadeApoio) {
        $excecoes[] = ['codigo' => 'APOIO_SECUNDARIO_INSUFICIENTE', 'solicitado' => $apoioSolicitado, 'elegivel' => $capacidadeApoio];
    }
    foreach ($planosAfetados as $planoAfetado) {
        if ($planoAfetado['estado'] === 'DESATUALIZADO') {
            $excecoes[] = ['codigo' => 'PLANO_DESATUALIZADO', 'entrega_id' => $planoAfetado['entrega_id']];
        }
    }
    $comparacao = [
        'conflitos_antes' => count($conflitosAntes),
        'conflitos_depois' => count($conflitosDepois),
        'conflitos_resolvidos' => $resolvidos,
        'novos_conflitos' => $novos,
        'deficit_antes' => (float) ($semanaOriginal['deficit_maximo'] ?? 0),
        'deficit_depois' => (float) ($semanaSimulada['deficit_maximo'] ?? 0),
        'pico_antes' => (float) ($semanaOriginal['pico_demanda'] ?? 0),
        'pico_depois' => (float) ($semanaSimulada['pico_demanda'] ?? 0),
    ];
    $resultado = [
        'original' => ['global' => $original, 'semana' => $semanaOriginal],
        'simulado' => ['global' => $simuladoGlobal, 'semana' => $semanaSimulada],
        'acoes' => $acoes,
        'planos_afetados' => $planosAfetados,
        'conflitos_resolvidos' => $resolvidos,
        'novos_conflitos' => $novos,
        'comparacao' => $comparacao,
        'apoio_secundario' => ['solicitado' => $apoioSolicitado, 'elegivel' => $capacidadeApoio, 'depende_validacao_operacional' => $dependeApoio],
        'intervencoes_capacidade' => $intervencoes,
        'depende_validacao_operacional' => $dependeValidacao,
        'excecoes' => $excecoes,
    ];
    $resultado['classificacao'] = flow_capacidade_simulacao_classificar($original, $resultado, $semanaOriginal, $semanaSimulada, $dependeValidacao);
    $resultado['score_operacional'] = flow_capacidade_simulacao_score($acoes, $resultado);
    return $resultado;
}

function flow_capacidade_simular(mysqli $conn, string $inicio, string $fim, string $codigoEtapa, string $semana, array $acoes): array
{
    if (!flow_capacidade_tabelas_disponiveis($conn)) {
        throw new RuntimeException('A migration de capacidade global ainda não foi aplicada.');
    }
    $fimHorizonte = flow_planejamento_adicionar_dias_uteis($fim, 60);
    $planos = flow_capacidade_carregar_planos_vigentes($conn, $inicio, $fimHorizonte);
    $configuracoes = flow_capacidade_simulacao_configuracoes($conn);
    return flow_capacidade_simular_com_planos(
        $planos,
        $configuracoes,
        $inicio,
        $fimHorizonte,
        $codigoEtapa,
        $semana,
        $acoes,
        static function (array $plano, array $pessoas, array $deslocamentos) use ($conn): array {
            return flow_planejamento_planejar_entrega($conn, (int) $plano['entrega_id'], [
                'pessoas_alocadas' => $pessoas,
                'deslocamentos_etapas' => $deslocamentos,
                'data_hoje' => date('Y-m-d'),
            ]);
        }
    );
}

function flow_capacidade_sugerir(mysqli $conn, string $inicio, string $fim, string $codigoEtapa, string $semana): array
{
    $base = flow_capacidade_simular($conn, $inicio, $fim, $codigoEtapa, $semana, []);
    $candidatos = $base['original']['semana']['projetos'] ?? [];
    usort($candidatos, static fn (array $a, array $b): int => ((int) ($b['margem_dias_uteis'] ?? -999) <=> (int) ($a['margem_dias_uteis'] ?? -999)) ?: ((float) ($a['capacidade_planejada'] ?? 0) <=> (float) ($b['capacidade_planejada'] ?? 0)));
    $acoes = [];
    foreach (array_slice($candidatos, 0, 3) as $projeto) {
        $entregaId = (int) ($projeto['entrega_id'] ?? 0);
        if ($entregaId <= 0) {
            continue;
        }
        // Procura o menor deslocamento que realmente elimina o conflito.
        $limite = max(0, min(30, (int) ($projeto['margem_dias_uteis'] ?? 0)));
        for ($dias = 1; $dias <= $limite; $dias++) {
            $tentativa = [['tipo' => 'DESLOCAR_ETAPA', 'entrega_id' => $entregaId, 'codigo_etapa' => $codigoEtapa, 'dias_uteis' => $dias]];
            try {
                $resultado = flow_capacidade_simular($conn, $inicio, $fim, $codigoEtapa, $semana, $tentativa);
                if (in_array($resultado['classificacao'], ['RESOLVE', 'RESOLVE_COM_VALIDACAO'], true)) {
                    $acoes[] = $tentativa;
                    break;
                }
            } catch (Throwable $erro) {
                break;
            }
        }
        $acoes[] = [['tipo' => 'ALTERAR_CAPACIDADE', 'entrega_id' => $entregaId, 'codigo_etapa' => $codigoEtapa, 'pessoas' => (int) ($projeto['capacidade_planejada'] ?? 1) + 1]];
    }
    if ((float) ($base['original']['semana']['apoio_maximo'] ?? 0) > 0) {
        $acoes[] = [['tipo' => 'APOIO_SECUNDARIO', 'codigo_etapa' => $codigoEtapa, 'quantidade' => (int) ceil((float) $base['original']['semana']['apoio_maximo'])]];
    }
    $diasDeficit = [];
    foreach (($base['original']['global']['etapas'] ?? []) as $etapa) {
        if (($etapa['codigo_etapa'] ?? '') !== $codigoEtapa) {
            continue;
        }
        foreach (($etapa['dias'] ?? []) as $dia) {
            if (flow_capacidade_inicio_semana((string) $dia['data']) === $semana && (float) ($dia['deficit'] ?? 0) > 0) {
                $diasDeficit[] = $dia;
            }
        }
    }
    if ($diasDeficit) {
        $distribuicao = array_map(static fn (array $dia): array => ['data' => $dia['data'], 'pessoas' => (int) ceil((float) $dia['deficit'])], $diasDeficit);
        $acoes[] = [[
            'tipo' => 'CAPACIDADE_EXTERNA', 'codigo_etapa' => $codigoEtapa,
            'data_inicio' => min(array_column($distribuicao, 'data')), 'data_fim' => max(array_column($distribuicao, 'data')),
            'pessoas' => max(array_column($distribuicao, 'pessoas')), 'distribuicao' => $distribuicao,
        ]];
        $acoes[] = [[
            'tipo' => 'CAPACIDADE_EXTRAORDINARIA', 'codigo_etapa' => $codigoEtapa,
            'data' => date('Y-m-d', strtotime($semana . ' +5 days')),
            'pessoas' => (int) ceil(array_sum(array_column($diasDeficit, 'deficit'))), 'origem' => 'TRABALHO_EXTRAORDINARIO',
        ]];
    }
    $combinacoes = [];
    $limiteCombinacao = min(5, count($acoes));
    for ($i = 0; $i < $limiteCombinacao; $i++) {
        for ($j = $i + 1; $j < $limiteCombinacao; $j++) {
            $combinacoes[] = array_merge($acoes[$i], $acoes[$j]);
        }
    }
    $sugestoes = [];
    $vistos = [];
    foreach (array_merge($acoes, $combinacoes) as $acao) {
        $chave = md5(json_encode($acao));
        if (isset($vistos[$chave])) {
            continue;
        }
        $vistos[$chave] = true;
        try {
            $cenario = flow_capacidade_simular($conn, $inicio, $fim, $codigoEtapa, $semana, $acao);
            $cenario['titulo'] = count($acao) > 1 ? 'Solução combinada' : match ($acao[0]['tipo']) {
                'CAPACIDADE_EXTERNA' => 'Adicionar capacidade externa mínima',
                'CAPACIDADE_EXTRAORDINARIA' => 'Usar produção extraordinária no sábado',
                'DESLOCAR_ETAPA' => 'Deslocar etapa dentro da margem',
                'ALTERAR_CAPACIDADE' => 'Aumentar pessoas planejadas na etapa',
                default => 'Usar o apoio secundário mínimo',
            };
            $sugestoes[] = $cenario;
        } catch (Throwable $erro) {
            // Uma sugestão inviável não impede as demais alternativas.
        }
    }
    // Duas R00/versionamentos da mesma obra podem gerar a mesma decisão
    // gerencial. O ID técnico da entrega não deve criar cards duplicados.
    $sugestoesUnicas = [];
    foreach ($sugestoes as $cenario) {
        $chavesAcoes = [];
        foreach (($cenario['acoes'] ?? []) as $acao) {
            $obraChave = (string) ($acao['entrega_id'] ?? '');
            foreach (($cenario['planos_afetados'] ?? []) as $planoAfetado) {
                if ((int) ($planoAfetado['entrega_id'] ?? 0) === (int) ($acao['entrega_id'] ?? -1)) {
                    $obraChave = (string) ($planoAfetado['obra_id'] ?? $planoAfetado['obra'] ?? $obraChave);
                    break;
                }
            }
            $chavesAcoes[] = implode(':', [
                (string) ($acao['tipo'] ?? ''),
                $obraChave,
                (string) ($acao['codigo_etapa'] ?? ''),
                (string) ($acao['dias_uteis'] ?? $acao['pessoas'] ?? $acao['quantidade'] ?? ''),
                (string) ($acao['data'] ?? $acao['data_inicio'] ?? ''),
                (string) ($acao['data_fim'] ?? ''),
            ]);
        }
        sort($chavesAcoes);
        $chaveDecisao = implode('|', $chavesAcoes);
        $existente = $sugestoesUnicas[$chaveDecisao] ?? null;
        if ($existente === null || (int) ($cenario['score_operacional'] ?? PHP_INT_MAX) < (int) ($existente['score_operacional'] ?? PHP_INT_MAX)) {
            $sugestoesUnicas[$chaveDecisao] = $cenario;
        }
    }
    $sugestoes = array_values($sugestoesUnicas);
    // Combinações só devem aparecer quando agregam resultado. Se uma ação
    // isolada já resolve o conflito e a combinação apenas repete essa ação
    // com impacto igual ou maior, ela é dominada e sai da resposta.
    $chaveAcao = static function (array $acao): string {
        return implode(':', [
            (string) ($acao['tipo'] ?? ''),
            (string) ($acao['entrega_id'] ?? ''),
            (string) ($acao['codigo_etapa'] ?? ''),
            (string) ($acao['dias_uteis'] ?? $acao['pessoas'] ?? $acao['quantidade'] ?? ''),
            (string) ($acao['data'] ?? $acao['data_inicio'] ?? ''),
            (string) ($acao['data_fim'] ?? ''),
        ]);
    };
    $isoladasResolvidas = [];
    foreach ($sugestoes as $cenario) {
        if (count($cenario['acoes'] ?? []) !== 1
            || !in_array((string) ($cenario['classificacao'] ?? ''), ['RESOLVE', 'RESOLVE_COM_VALIDACAO', 'RESOLVE_COM_APOIO'], true)
            || (float) ($cenario['comparacao']['deficit_depois'] ?? 0) > 0
            || (int) ($cenario['comparacao']['conflitos_depois'] ?? 0) > 0
            || !empty($cenario['novos_conflitos'])) {
            continue;
        }
        $isoladasResolvidas[$chaveAcao($cenario['acoes'][0])] = $cenario;
    }
    $sugestoes = array_values(array_filter($sugestoes, static function (array $cenario) use ($chaveAcao, $isoladasResolvidas): bool {
        if (count($cenario['acoes'] ?? []) <= 1) {
            return true;
        }
        $score = (int) ($cenario['score_operacional'] ?? PHP_INT_MAX);
        foreach ($isoladasResolvidas as $chave => $isolada) {
            $contém = false;
            foreach ($cenario['acoes'] as $acao) {
                if ($chaveAcao($acao) === $chave) {
                    $contém = true;
                    break;
                }
            }
            if ($contém && $score >= (int) ($isolada['score_operacional'] ?? PHP_INT_MAX)) {
                return false;
            }
        }
        return true;
    }));
    $ordem = ['RESOLVE' => 0, 'RESOLVE_COM_VALIDACAO' => 1, 'RESOLVE_PARCIALMENTE' => 2, 'DEPENDENTE_DE_APOIO' => 3, 'TRANSFERE_PROBLEMA' => 4, 'INVIAVEL' => 5, 'SEM_GANHO' => 6];
    usort($sugestoes, static function (array $a, array $b) use ($ordem): int {
        return ($ordem[$a['classificacao']] ?? 99) <=> ($ordem[$b['classificacao']] ?? 99)
            ?: count($a['novos_conflitos']) <=> count($b['novos_conflitos'])
            ?: (int) ($a['score_operacional'] ?? PHP_INT_MAX) <=> (int) ($b['score_operacional'] ?? PHP_INT_MAX);
    });
    return array_slice($sugestoes, 0, 5);
}

/** Resposta compacta para a interface; o sandbox completo nunca é persistido. */
function flow_capacidade_simulacao_para_interface(array $cenario): array
{
    return [
        'classificacao' => $cenario['classificacao'],
        'score_operacional' => $cenario['score_operacional'] ?? null,
        'acoes' => $cenario['acoes'],
        'comparacao' => $cenario['comparacao'],
        'planos_afetados' => $cenario['planos_afetados'],
        'conflitos_resolvidos' => $cenario['conflitos_resolvidos'],
        'novos_conflitos' => $cenario['novos_conflitos'],
        'apoio_secundario' => $cenario['apoio_secundario'],
        'intervencoes_capacidade' => $cenario['intervencoes_capacidade'] ?? ['extraordinaria' => [], 'externa' => []],
        'depende_validacao_operacional' => !empty($cenario['depende_validacao_operacional']),
        'excecoes' => $cenario['excecoes'],
        'original' => ['semana' => $cenario['original']['semana']],
        'simulado' => ['semana' => $cenario['simulado']['semana']],
        'titulo' => $cenario['titulo'] ?? null,
    ];
}

/**
 * Converte um cenário confirmado em novas versões dos planos individuais.
 * O motor global nunca escreve diretamente nas suas próprias estruturas.
 */
function flow_capacidade_aplicar_cenario(mysqli $conn, string $inicio, string $fim, string $codigoEtapa, string $semana, array $acoes, ?int $atorId, ?string $observacao = null): array
{
    $cenario = flow_capacidade_simular($conn, $inicio, $fim, $codigoEtapa, $semana, $acoes);
    if (!$cenario['planos_afetados']) {
        throw new InvalidArgumentException('Este cenário não altera nenhum planejamento individual para aplicar.');
    }
    if (!empty($cenario['depende_validacao_operacional'])) {
        throw new InvalidArgumentException('Cenários com apoio, capacidade extraordinária ou externa exigem validação operacional e não podem ser aplicados automaticamente.');
    }
    $observacao = trim((string) $observacao);
    if (mb_strlen($observacao) > 500) {
        throw new InvalidArgumentException('A observação aceita até 500 caracteres.');
    }

    $conn->begin_transaction();
    try {
        $aplicados = [];
        foreach ($cenario['planos_afetados'] as $afetado) {
            $entregaId = (int) $afetado['entrega_id'];
            $atual = flow_planejamento_carregar_para_interface($conn, $entregaId, ['replanejar' => true]);
            $planejamento = (array) ($atual['planejamento'] ?? []);
            if (empty($atual['persistencia_disponivel']) || empty($planejamento['id'])) {
                throw new RuntimeException('O planejamento individual da R00 ' . $entregaId . ' não está pronto para replanejamento.');
            }
            $aplicados[] = flow_planejamento_persistir_confirmacao(
                $conn,
                $entregaId,
                (array) $afetado['pessoas'],
                (string) ($atual['fingerprint'] ?? ''),
                (int) ($planejamento['lock_version'] ?? -1),
                $atorId,
                true,
                'RESOLUCAO_CAPACIDADE',
                $observacao !== '' ? $observacao : 'Cenário confirmado no Planejamento Global de Capacidade.',
                (array) $afetado['deslocamentos_etapas'],
                [
                    'origem' => 'PLANEJAMENTO_GLOBAL_CAPACIDADE',
                    'conflito_origem' => ['codigo_etapa' => $codigoEtapa, 'semana' => $semana],
                    'acoes' => $cenario['acoes'],
                    'comparacao_esperada' => $cenario['comparacao'],
                ],
                false
            );
        }
        $conn->commit();
        return ['cenario' => flow_capacidade_simulacao_para_interface($cenario), 'planos' => $aplicados];
    } catch (Throwable $erro) {
        $conn->rollback();
        throw $erro;
    }
}
