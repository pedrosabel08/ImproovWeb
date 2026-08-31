<?php

/**
 * V1.5A — projeção operacional somente leitura.
 *
 * Plano (baseline/vigente), execução e fila são deliberadamente distintos:
 * este arquivo nunca cria versão de plano, não muda prioridade e não altera
 * funcao_imagem. A ordem retornada é DERIVADA até existir uma V1.5B.
 */

require_once __DIR__ . '/planejamento_execucao_helper.php';
require_once __DIR__ . '/planejamento_alocacao_helper.php';

const FLOW_FILA_TIPO_DERIVADA = 'DERIVADA';

function flow_fila_confianca_minima(string $a, string $b): string
{
    $peso = ['ALTA' => 4, 'MEDIA' => 3, 'BAIXA' => 2, 'INSUFICIENTE' => 1, 'NAO_APLICAVEL' => 3];
    return ($peso[$a] ?? 1) <= ($peso[$b] ?? 1) ? $a : $b;
}

function flow_fila_proximo_dia_util(string $data): string
{
    return flow_planejamento_adicionar_dias_uteis($data, 1);
}

function flow_fila_adicionar_esforco(string $inicio, float $pessoaDias): string
{
    return flow_planejamento_adicionar_dias_uteis($inicio, (int) ceil(max(0.0, $pessoaDias)));
}

function flow_fila_status_aberto(string $status): bool
{
    return !flow_planejamento_status_finalizado($status)
        && !flow_execucao_status_cancelado($status);
}

/** Carrega snapshots sem chamar a rotina que pode marcar plano desatualizado. */
function flow_fila_carregar_planos_confirmados(mysqli $conn, array $filtros = []): array
{
    $sql = "SELECT p.id AS planejamento_id, p.entrega_id, p.versao_atual_id, p.baseline_versao_id,
                   e.obra_id, e.data_prevista, o.nome_obra
              FROM entrega_planejamento_producao p
              JOIN entregas e ON e.id = p.entrega_id
              JOIN obra o ON o.idobra = e.obra_id
             WHERE p.estado IN ('CONFIRMADO', 'DESATUALIZADO', 'REPLANEJAMENTO')
               AND p.versao_atual_id IS NOT NULL";
    $tipos = '';
    $valores = [];
    if (!empty($filtros['obra_id'])) {
        $sql .= ' AND e.obra_id = ?';
        $tipos .= 'i';
        $valores[] = (int) $filtros['obra_id'];
    }
    if (!empty($filtros['entrega_id'])) {
        $sql .= ' AND e.id = ?';
        $tipos .= 'i';
        $valores[] = (int) $filtros['entrega_id'];
    }
    if (!empty($filtros['entrega_ids']) && is_array($filtros['entrega_ids'])) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $filtros['entrega_ids']))));
        if ($ids) {
            $sql .= ' AND e.id IN (' . implode(',', $ids) . ')';
        }
    }
    $stmt = $conn->prepare($sql . ' ORDER BY e.obra_id, e.id');
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }
    if ($tipos !== '') {
        $stmt->bind_param($tipos, ...$valores);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $planos = [];
    foreach ($rows as $row) {
        $vigente = flow_planejamento_carregar_versao($conn, (int) $row['versao_atual_id']);
        $baseline = !empty($row['baseline_versao_id'])
            ? flow_planejamento_carregar_versao($conn, (int) $row['baseline_versao_id'])
            : null;
        $snapshot = $vigente ? json_decode((string) $vigente['snapshot_json'], true) : null;
        $baselineSnapshot = $baseline ? json_decode((string) $baseline['snapshot_json'], true) : null;
        if (!is_array($snapshot)) {
            continue;
        }
        $snapshot['fonte'] = 'VERSAO_CONFIRMADA';
        $snapshot['planejamento'] = [
            'id' => (int) $row['planejamento_id'],
            'versao_atual_id' => (int) $row['versao_atual_id'],
            'baseline_versao_id' => (int) ($row['baseline_versao_id'] ?? 0),
        ];
        $planos[(int) $row['entrega_id']] = [
            'entrega_id' => (int) $row['entrega_id'],
            'obra_id' => (int) $row['obra_id'],
            'obra' => (string) ($row['nome_obra'] ?? ''),
            'planejamento_id' => (int) $row['planejamento_id'],
            'versao_id' => (int) $row['versao_atual_id'],
            'baseline_versao_id' => (int) ($row['baseline_versao_id'] ?? 0),
            'vigente' => $snapshot,
            'baseline' => $baselineSnapshot,
        ];
    }
    return $planos;
}

function flow_fila_mapa_etapas_plano(array $plano): array
{
    $mapa = [];
    foreach ((array) ($plano['etapas'] ?? []) as $etapa) {
        $codigo = (string) ($etapa['codigo'] ?? $etapa['codigo_etapa'] ?? '');
        if ($codigo !== '') {
            $mapa[$codigo] = $etapa;
        }
    }
    return $mapa;
}

/** Uma query por lote para a fila de todos os responsáveis relevantes. */
function flow_fila_carregar_tarefas_responsaveis(mysqli $conn, array $colaboradorIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $colaboradorIds))));
    if (!$ids) {
        return [];
    }
    $lista = implode(',', $ids);
    $sql = "SELECT fi.idfuncao_imagem AS tarefa_id, fi.imagem_id, fi.funcao_id, fi.colaborador_id, fi.status, fi.prazo,
                   ico.tipo_imagem, ico.imagem_nome, ico.obra_id, o.nome_obra, c.nome_colaborador AS responsavel_nome,
                   COALESCE(pf.prioridade, 3) AS prioridade,
                   (SELECT ei.entrega_id FROM entregas_itens ei JOIN entregas e ON e.id = ei.entrega_id
                     WHERE ei.imagem_id = fi.imagem_id AND e.status_id = 2
                     ORDER BY e.data_prevista ASC, ei.id ASC LIMIT 1) AS entrega_id,
                   (SELECT e.data_prevista FROM entregas_itens ei JOIN entregas e ON e.id = ei.entrega_id
                     WHERE ei.imagem_id = fi.imagem_id AND e.status_id = 2
                     ORDER BY e.data_prevista ASC, ei.id ASC LIMIT 1) AS prazo_entrega
              FROM funcao_imagem fi
              JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
              JOIN obra o ON o.idobra = ico.obra_id
              LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
              LEFT JOIN prioridade_funcao pf ON pf.funcao_imagem_id = fi.idfuncao_imagem
             WHERE fi.colaborador_id IN ({$lista})
               AND o.status_obra = 0
               AND fi.status NOT IN ('Finalizado', 'Aprovado', 'Aprovado com ajustes', 'Cancelado')
             ORDER BY fi.colaborador_id, COALESCE(pf.prioridade, 3), fi.idfuncao_imagem";
    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException('Não foi possível carregar a fila operacional: ' . $conn->error);
    }
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    return $rows;
}

function flow_fila_logs_por_tarefa(mysqli $conn, array $tarefas): array
{
    return flow_execucao_carregar_logs($conn, $tarefas);
}

/**
 * Agrupa Caderno/Filtro por imagem. Quando os responsáveis divergem, reparte
 * apenas como aproximação explícita, nunca como regra de produtividade.
 */
function flow_fila_unidades_logicas(array $tarefas, bool $incluirFechadas = false): array
{
    $grupos = [];
    foreach ($tarefas as $tarefa) {
        $codigo = flow_planejamento_codigo_etapa($tarefa);
        if ($codigo === null || (!$incluirFechadas && !flow_fila_status_aberto((string) ($tarefa['status'] ?? '')))) {
            continue;
        }
        $chave = $codigo === 'CADERNO_FILTRO'
            ? $codigo . ':I' . (int) $tarefa['imagem_id']
            : $codigo . ':T' . (int) $tarefa['tarefa_id'];
        if (!isset($grupos[$chave])) {
            $tarefa['codigo_etapa'] = $codigo;
            $grupos[$chave] = ['chave' => $chave, 'codigo_etapa' => $codigo, 'imagem_id' => (int) $tarefa['imagem_id'], 'tarefas' => [], 'representante' => $tarefa];
        }
        $grupos[$chave]['tarefas'][] = $tarefa;
        if ((int) $tarefa['funcao_id'] === 1) {
            $grupos[$chave]['representante'] = $tarefa;
        }
    }
    foreach ($grupos as &$unidade) {
        $responsaveis = array_values(array_unique(array_filter(array_map(static fn (array $t): int => (int) ($t['colaborador_id'] ?? 0), $unidade['tarefas']))));
        sort($responsaveis);
        $unidade['responsaveis'] = $responsaveis;
        $unidade['responsabilidade_divergente'] = $unidade['codigo_etapa'] === 'CADERNO_FILTRO' && count($responsaveis) > 1;
        $unidade['prioridade'] = min(array_map(static fn (array $t): int => (int) ($t['prioridade'] ?? 3), $unidade['tarefas']));
        $prazos = array_values(array_filter(array_map(static fn (array $t): ?string => ($t['prazo'] ?? null) ?: null, $unidade['tarefas'])));
        $unidade['prazo_operacional'] = $prazos ? min($prazos) : null;
        $entregas = array_values(array_filter(array_map(static fn (array $t): int => (int) ($t['entrega_id'] ?? 0), $unidade['tarefas'])));
        $unidade['entrega_id'] = $entregas ? min($entregas) : null;
        $prazoEntregas = array_values(array_filter(array_map(static fn (array $t): ?string => ($t['prazo_entrega'] ?? null) ?: null, $unidade['tarefas'])));
        $unidade['prazo_entrega'] = $prazoEntregas ? min($prazoEntregas) : null;
    }
    unset($unidade);
    return array_values($grupos);
}

function flow_fila_estimativa_unidade(mysqli $conn, array $unidade, array $planoEtapa, array $logsPorTarefa, array &$cache, ?string $hoje = null): array
{
    $representante = $unidade['representante'];
    $codigo = (string) $unidade['codigo_etapa'];
    $definicao = flow_planejamento_definicoes_etapas()[$codigo] ?? [];
    $resumos = [];
    foreach ($unidade['tarefas'] as $tarefa) {
        $resumos[] = flow_execucao_resumir_tarefa($tarefa, $logsPorTarefa[(int) $tarefa['tarefa_id']] ?? []);
    }
    if ($resumos && !array_filter($resumos, static fn (array $r): bool => empty($r['concluida']))) {
        return ['pessoa_dias' => 0.0, 'origem' => 'CONCLUSAO_REAL', 'confianca' => 'ALTA', 'bloqueada' => false, 'resumos' => $resumos];
    }
    if (array_filter($resumos, static fn (array $r): bool => !empty($r['hold']))) {
        return ['pessoa_dias' => null, 'origem' => 'HOLD', 'confianca' => 'INSUFICIENTE', 'bloqueada' => true, 'resumos' => $resumos];
    }

    $estrategia = (string) ($planoEtapa['estrategia_duracao'] ?? $definicao['estrategia'] ?? '');
    $volume = max(1, (int) ($planoEtapa['volume'] ?? 1));
    $pessoas = max(1, (int) ($planoEtapa['pessoas_alocadas'] ?? 1));
    $fallback = max(0.1, ((float) ($planoEtapa['duracao_dias_uteis'] ?? 0) * $pessoas) / $volume);
    $estimativa = null;
    $origem = 'PLANEJAMENTO';
    $confianca = 'BAIXA';
    if (in_array($estrategia, ['OPERACIONAL_POR_TAREFA', 'OPERACIONAL_POR_TAXA', 'JANELA_FIXA'], true)) {
        $taxa = (float) (($planoEtapa['metrica']['tarefas_por_dia_util_pessoa'] ?? $definicao['tarefas_por_dia_pessoa'] ?? 0));
        $estimativa = $estrategia === 'JANELA_FIXA' ? $fallback : ($taxa > 0 ? 1 / $taxa : $fallback);
        $origem = 'REGRA_OPERACIONAL_CONFIRMADA';
        $confianca = 'MEDIA';
    } else {
        $tipos = flow_planejamento_tipos_da_etapa($codigo);
        $metrica = flow_planejamento_estimar_produtividade($conn, (int) ($definicao['funcao_id'] ?? $representante['funcao_id']), $tipos, $cache);
        if (($metrica['confianca'] ?? 'INSUFICIENTE') !== 'INSUFICIENTE' && !empty($metrica['duracao_mediana_dias_uteis'])) {
            $estimativa = (float) $metrica['duracao_mediana_dias_uteis'];
            $origem = 'HISTORICO_MEDIANA';
            $confianca = (string) $metrica['confianca'];
        } elseif ($fallback > 0) {
            $estimativa = $fallback;
        }
    }
    if ($estimativa === null) {
        return ['pessoa_dias' => null, 'origem' => 'SEM_ESTIMATIVA', 'confianca' => 'INSUFICIENTE', 'bloqueada' => false, 'resumos' => $resumos];
    }

    // Para uma unidade já iniciada, desconta somente dias úteis observados.
    $inicios = array_values(array_filter(array_column($resumos, 'inicio_real')));
    if ($inicios) {
        $observado = flow_planejamento_dias_uteis_entre(min($inicios), $hoje ?: date('Y-m-d'));
        $estimativa = max(0.1, $estimativa - $observado);
        $origem .= '_RESIDUAL';
        $confianca = flow_fila_confianca_minima($confianca, 'BAIXA');
    }
    return ['pessoa_dias' => round($estimativa, 4), 'origem' => $origem, 'confianca' => $confianca, 'bloqueada' => false, 'resumos' => $resumos];
}

function flow_fila_ordenar_unidades(array &$unidades, array $etapasPorEntrega): void
{
    foreach ($unidades as &$unidade) {
        $etapa = $etapasPorEntrega[(int) ($unidade['entrega_id'] ?? 0)][$unidade['codigo_etapa']] ?? [];
        $unidade['inicio_planejado'] = $etapa['inicio'] ?? $etapa['data_inicio'] ?? null;
        $unidade['ordem_etapa'] = (int) ($etapa['ordem_apresentacao'] ?? 999);
    }
    unset($unidade);
    usort($unidades, static function (array $a, array $b): int {
        $nuloDepois = static fn (?string $data): string => $data ?: '9999-12-31';
        return ((int) $a['prioridade'] <=> (int) $b['prioridade'])
            ?: strcmp($nuloDepois($a['prazo_operacional']), $nuloDepois($b['prazo_operacional']))
            ?: strcmp($nuloDepois($a['prazo_entrega']), $nuloDepois($b['prazo_entrega']))
            ?: strcmp($nuloDepois($a['inicio_planejado']), $nuloDepois($b['inicio_planejado']))
            ?: ((int) $a['ordem_etapa'] <=> (int) $b['ordem_etapa'])
            ?: ((int) $a['imagem_id'] <=> (int) $b['imagem_id'])
            ?: strcmp((string) $a['chave'], (string) $b['chave']);
    });
}

function flow_fila_resumo_unidade(array $unidade): array
{
    $r = $unidade['representante'] ?? [];
    return [
        'chave' => (string) ($unidade['chave'] ?? ''),
        'fila_chave' => flow_fila_chave_operacional($unidade),
        'entrega_id' => $unidade['entrega_id'] ?? null,
        'obra_id' => isset($r['obra_id']) ? (int) $r['obra_id'] : null,
        'obra' => (string) ($r['nome_obra'] ?? ''),
        'etapa' => (string) ($unidade['codigo_etapa'] ?? ''),
        'imagem' => (string) ($r['imagem_nome'] ?? ''),
        'prioridade' => (int) ($unidade['prioridade'] ?? 3),
    ];
}

/**
 * Entregas sem plano ainda ocupam capacidade. Não podem compartilhar a chave
 * "0:ETAPA", pois isso misturaria obras independentes na fila confirmada.
 */
function flow_fila_chave_operacional(array $unidade): string
{
    $entregaId = (int) ($unidade['entrega_id'] ?? 0);
    $codigo = (string) ($unidade['codigo_etapa'] ?? '');
    if ($entregaId > 0) {
        return 'ENTREGA:' . $entregaId . ':' . $codigo;
    }
    $representante = (array) ($unidade['representante'] ?? []);
    $obraId = (int) ($unidade['obra_id'] ?? $representante['obra_id'] ?? 0);
    return 'OBRA:' . $obraId . ':' . $codigo;
}

/**
 * A UI manipula blocos gerenciais (entrega + etapa), nunca imagens isoladas.
 * A estimativa continua por unidade lógica para preservar o cálculo V1.5A;
 * este resumo somente agrupa a visualização e o override da ordem.
 */
function flow_fila_resumir_blocos_responsavel(array $fila, array $estimativas, int $colaboradorId): array
{
    $blocos = [];
    foreach ($fila as $indice => $unidade) {
        $chave = flow_fila_chave_operacional($unidade);
        if (!isset($blocos[$chave])) {
            $resumo = flow_fila_resumo_unidade($unidade);
            $blocos[$chave] = [
                'chave' => $chave,
                'fila_chave' => $chave,
                'posicao_derivada' => $indice + 1,
                'entrega_id' => (int) ($unidade['entrega_id'] ?? 0),
                'codigo_etapa' => (string) ($unidade['codigo_etapa'] ?? ''),
                'obra' => $resumo['obra'],
                'prioridade' => $resumo['prioridade'],
                'unidades' => 0,
                'esforco_pessoa_dia' => 0.0,
                // Não inclui prazo individual: a fila não é microplanejamento.
                'tarefas_contexto' => [],
            ];
        }
        $estimativa = $estimativas[$unidade['chave']] ?? [];
        $divisor = max(1, count((array) ($unidade['responsaveis'] ?? [])));
        $blocos[$chave]['unidades']++;
        $blocos[$chave]['esforco_pessoa_dia'] += (float) ($estimativa['pessoa_dias'] ?? 0) / $divisor;
        foreach ((array) ($unidade['tarefas'] ?? []) as $tarefa) {
            if ((int) ($tarefa['colaborador_id'] ?? 0) !== $colaboradorId) {
                continue;
            }
            $blocos[$chave]['tarefas_contexto'][] = [
                'id' => (int) ($tarefa['tarefa_id'] ?? 0),
                'funcao_id' => (int) ($tarefa['funcao_id'] ?? 0),
                'status' => (string) ($tarefa['status'] ?? ''),
                'colaborador_id' => (int) ($tarefa['colaborador_id'] ?? 0),
            ];
        }
    }
    foreach ($blocos as &$bloco) {
        usort($bloco['tarefas_contexto'], static fn (array $a, array $b): int => $a['id'] <=> $b['id']);
        $bloco['esforco_pessoa_dia'] = round($bloco['esforco_pessoa_dia'], 2);
    }
    unset($bloco);
    return array_values($blocos);
}

function flow_fila_disponibilidade_por_responsavel(array $unidades, array $estimativas, int $entregaAlvo, string $hoje, array $overrides = []): array
{
    $filas = [];
    foreach ($unidades as $unidade) {
        $estimativa = $estimativas[$unidade['chave']] ?? [];
        foreach ($unidade['responsaveis'] as $id) {
            $filas[$id][] = $unidade;
        }
    }
    $resultado = [];
    foreach ($filas as $id => $fila) {
        $ordemConfirmada = $overrides[(int) $id] ?? [];
        if ($ordemConfirmada) {
            usort($fila, static function (array $a, array $b) use ($ordemConfirmada): int {
                $chaveA = flow_fila_chave_operacional($a);
                $chaveB = flow_fila_chave_operacional($b);
                $posA = $ordemConfirmada[$chaveA] ?? PHP_INT_MAX;
                $posB = $ordemConfirmada[$chaveB] ?? PHP_INT_MAX;
                return $posA <=> $posB
                    ?: ((int) $a['prioridade'] <=> (int) $b['prioridade'])
                    ?: ((int) $a['imagem_id'] <=> (int) $b['imagem_id']);
            });
        }
        $cursor = $hoje;
        $antes = [];
        $indisponivel = false;
        $nome = '';
        foreach ($fila as $unidade) {
            foreach ((array) ($unidade['tarefas'] ?? []) as $tarefa) {
                if ((int) ($tarefa['colaborador_id'] ?? 0) === (int) $id && !empty($tarefa['responsavel_nome'])) {
                    $nome = (string) $tarefa['responsavel_nome'];
                    break 2;
                }
            }
        }
        foreach ($fila as $unidade) {
            if ((int) ($unidade['entrega_id'] ?? 0) === $entregaAlvo) {
                break;
            }
            $estimativa = $estimativas[$unidade['chave']] ?? [];
            if (!empty($estimativa['bloqueada']) || ($estimativa['confianca'] ?? '') === 'INSUFICIENTE') {
                $indisponivel = true;
                $antes[] = ['unidade' => flow_fila_resumo_unidade($unidade), 'esforco_pessoa_dia' => null, 'origem' => $estimativa['origem'] ?? 'SEM_ESTIMATIVA'];
                continue;
            }
            $divisor = max(1, count($unidade['responsaveis']));
            $esforco = (float) ($estimativa['pessoa_dias'] ?? 0) / $divisor;
            $inicio = $cursor;
            $cursor = flow_fila_adicionar_esforco($cursor, $esforco);
            $antes[] = ['unidade' => flow_fila_resumo_unidade($unidade), 'inicio' => $inicio, 'fim' => $cursor, 'esforco_pessoa_dia' => round($esforco, 2), 'origem' => $estimativa['origem'] ?? ''];
        }
        $resultado[(int) $id] = [
            'colaborador_id' => (int) $id,
            'nome' => $nome !== '' ? $nome : ('Colaborador #' . (int) $id),
            'tipo_fila' => $ordemConfirmada ? 'CONFIRMADA' : FLOW_FILA_TIPO_DERIVADA,
            'confianca_fila' => 'BAIXA',
            'disponivel_em' => $indisponivel ? null : $cursor,
            'bloqueada_por_fila' => $indisponivel,
            'anteriores' => $antes,
            'fila_completa' => flow_fila_resumir_blocos_responsavel($fila, $estimativas, (int) $id),
        ];
    }
    return $resultado;
}

function flow_fila_status_etapa(?string $fim, ?string $limite, ?string $entrega, bool $bloqueada, bool $insuficiente): string
{
    if ($bloqueada) {
        return 'BLOQUEADO';
    }
    if ($insuficiente || !$fim) {
        return 'FILA_NAO_CALCULAVEL';
    }
    if (!$limite || $fim <= $limite) {
        return 'NO_PLANO';
    }
    return $entrega && $fim > $entrega ? 'ATRASO_PROJETADO' : 'MARGEM_CONSUMIDA';
}

function flow_fila_margem_operacional(?string $fim, ?string $entrega): ?int
{
    if (!$fim || !$entrega) {
        return null;
    }
    return $fim <= $entrega
        ? flow_planejamento_dias_uteis_entre($fim, $entrega)
        : -flow_planejamento_dias_uteis_entre($entrega, $fim);
}

/** Parte pura: útil nos testes e mantém o cálculo fora de endpoints/JS. */
function flow_fila_projetar_etapas(array $planoVigente, array $baseline, array $unidadesEtapa, array $estimativas, array $disponibilidades, string $hoje): array
{
    $etapasPlanejadas = flow_fila_mapa_etapas_plano($planoVigente);
    $etapasBaseline = flow_fila_mapa_etapas_plano($baseline);
    $resultado = [];
    $confiancaGeral = 'ALTA';
    foreach ((array) ($planoVigente['etapas'] ?? []) as $planejada) {
        $codigo = (string) ($planejada['codigo'] ?? $planejada['codigo_etapa'] ?? '');
        if ($codigo === '') {
            continue;
        }
        $dependencias = (array) ($planejada['dependencias'] ?? []);
        if ($codigo === 'FINALIZACAO_GLOBAL') {
            $fins = array_values(array_filter(array_map(static fn (string $d): ?string => $resultado[$d]['fim_operacional_projetado'] ?? null, $dependencias)));
            $resultado[$codigo] = [
                'codigo' => $codigo, 'nome' => (string) ($planejada['nome'] ?? 'Finalização (marco global)'),
                'baseline_limite' => $etapasBaseline[$codigo]['limite'] ?? null,
                'plano_vigente_inicio' => $planejada['inicio'] ?? null, 'plano_vigente_limite' => $planejada['limite'] ?? null,
                'inicio_operacional_projetado' => $fins ? min($fins) : null,
                'fim_operacional_projetado' => $fins ? max($fins) : null,
                'status_operacional' => $fins ? 'NO_PLANO' : 'FILA_NAO_CALCULAVEL',
                'confianca' => 'MEDIA', 'dependencias' => $dependencias, 'frentes' => [], 'tipo' => 'MARCO_VIRTUAL',
            ];
            continue;
        }
        $unidades = $unidadesEtapa[$codigo] ?? [];
        $dependenciaFim = $hoje;
        foreach ($dependencias as $dependencia) {
            $fim = $resultado[$dependencia]['fim_operacional_projetado'] ?? null;
            if ($fim === null) {
                $dependenciaFim = null;
            } elseif ($dependenciaFim !== null && $fim > $dependenciaFim) {
                $dependenciaFim = $fim;
            }
        }
        $frentes = [];
        $cargasFrente = [];
        $bloqueada = $dependenciaFim === null;
        $insuficiente = false;
        foreach ($unidades as $unidade) {
            $estimativa = $estimativas[$unidade['chave']] ?? [];
            if (!empty($estimativa['bloqueada'])) {
                $bloqueada = true;
            }
            if (($estimativa['confianca'] ?? '') === 'INSUFICIENTE') {
                $insuficiente = true;
            }
            $pessoas = $unidade['responsaveis'];
            if (!$pessoas) {
                $bloqueada = true;
            }
            foreach ($pessoas as $pessoaId) {
                $cargasFrente[$pessoaId]['esforco'] = ($cargasFrente[$pessoaId]['esforco'] ?? 0) + ((float) ($estimativa['pessoa_dias'] ?? 0) / max(1, count($pessoas)));
                $cargasFrente[$pessoaId]['unidades'][] = $unidade['chave'];
                $confiancaGeral = flow_fila_confianca_minima($confiancaGeral, (string) ($estimativa['confianca'] ?? 'INSUFICIENTE'));
            }
        }
        // Unidades da mesma frente podem ser executadas dentro da janela da
        // etapa; soma-se o esforço antes de converter para dias úteis. Isso
        // preserva, por exemplo, Pós-produção a 5 tarefas/dia.
        foreach ($cargasFrente as $pessoaId => $carga) {
            $disponibilidade = $disponibilidades[$pessoaId] ?? [];
            if (empty($disponibilidade['disponivel_em'])) {
                $bloqueada = true;
            }
            $inicio = $dependenciaFim;
            if ($inicio && !empty($disponibilidade['disponivel_em']) && $disponibilidade['disponivel_em'] > $inicio) {
                $inicio = $disponibilidade['disponivel_em'];
            }
            $fim = ($inicio && !$bloqueada && !$insuficiente) ? flow_fila_adicionar_esforco($inicio, (float) $carga['esforco']) : null;
            $frentes[$pessoaId] = [
                'colaborador_id' => (int) $pessoaId,
                'inicio' => $inicio,
                'fim' => $fim,
                'esforco_pessoa_dia' => round((float) $carga['esforco'], 2),
                'unidades' => $carga['unidades'],
            ];
        }
        $inicios = array_values(array_filter(array_column($frentes, 'inicio')));
        $fins = array_values(array_filter(array_column($frentes, 'fim')));
        $fim = $fins ? max($fins) : null;
        $limite = $planejada['limite'] ?? $planejada['data_limite'] ?? null;
        $etapa = [
            'codigo' => $codigo, 'nome' => (string) ($planejada['nome'] ?? $codigo),
            'volume_planejado' => (int) ($planejada['volume'] ?? 0), 'volume_materializado' => count($unidades),
            'baseline_limite' => $etapasBaseline[$codigo]['limite'] ?? null,
            'plano_vigente_inicio' => $planejada['inicio'] ?? $planejada['data_inicio'] ?? null,
            'plano_vigente_limite' => $limite,
            'inicio_operacional_projetado' => $inicios ? min($inicios) : null,
            'fim_operacional_projetado' => $fim,
            'desvio_baseline_dias_uteis' => $fim && !empty($etapasBaseline[$codigo]['limite']) ? flow_execucao_desvio($etapasBaseline[$codigo]['limite'], $fim) : null,
            'desvio_plano_vigente_dias_uteis' => $fim && $limite ? flow_execucao_desvio($limite, $fim) : null,
            'status_operacional' => flow_fila_status_etapa($fim, $limite, $planoVigente['data_entrega'] ?? null, $bloqueada, $insuficiente),
            'confianca' => $insuficiente ? 'INSUFICIENTE' : ($bloqueada ? 'BAIXA' : $confiancaGeral),
            'dependencias' => $dependencias, 'frentes' => array_values($frentes),
            'atrasada_contra_plano' => $limite && $limite < $hoje && !$fim,
        ];
        if ((int) $etapa['volume_materializado'] < (int) $etapa['volume_planejado']) {
            $etapa['status_operacional'] = 'BLOQUEADO';
            $etapa['motivo_bloqueio'] = 'PENDENTE_MATERIALIZACAO';
        }
        $resultado[$codigo] = $etapa;
    }
    return $resultado;
}

function flow_fila_projetar_entrega(mysqli $conn, int $entregaId, array $planosGlobais = [], array $opcoes = []): array
{
    $hoje = (string) ($opcoes['data_hoje'] ?? date('Y-m-d'));
    if (!entregas_valid_date($hoje)) {
        $hoje = date('Y-m-d');
    }
    $planosGlobais = $planosGlobais ?: flow_fila_carregar_planos_confirmados($conn);
    $plano = $planosGlobais[$entregaId] ?? null;
    if (!$plano) {
        throw new RuntimeException('Não há plano confirmado para esta entrega.');
    }
    $etapasPorEntrega = [];
    foreach ($planosGlobais as $id => $item) {
        $etapasPorEntrega[$id] = flow_fila_mapa_etapas_plano($item['vigente']);
    }

    $tarefasEntrega = flow_alocacao_carregar_tarefas_reais($conn, [$entregaId]);
    foreach ($tarefasEntrega as &$tarefaEntrega) {
        $tarefaEntrega['entrega_id'] = $entregaId;
        $tarefaEntrega['prazo_entrega'] = (string) ($plano['vigente']['data_entrega'] ?? $plano['vigente']['prazo_r00'] ?? '');
        $tarefaEntrega['prioridade'] = 3;
    }
    unset($tarefaEntrega);
    $mapeadasIniciais = flow_fila_unidades_logicas($tarefasEntrega, true);
    $responsaveis = [];
    foreach ($mapeadasIniciais as $unidade) {
        foreach ($unidade['responsaveis'] as $id) {
            $responsaveis[] = $id;
        }
    }
    $tarefasFila = flow_fila_carregar_tarefas_responsaveis($conn, $responsaveis);
    $unidadesFila = flow_fila_unidades_logicas($tarefasFila);
    $prioridadesFila = [];
    foreach ($tarefasFila as $tarefaFila) {
        $prioridadesFila[(int) $tarefaFila['tarefa_id']] = (int) ($tarefaFila['prioridade'] ?? 3);
    }
    foreach ($tarefasEntrega as &$tarefaEntrega) {
        $tarefaEntrega['prioridade'] = $prioridadesFila[(int) $tarefaEntrega['tarefa_id']] ?? 3;
    }
    unset($tarefaEntrega);
    $mapeadas = flow_fila_unidades_logicas($tarefasEntrega, true);
    $todasTarefas = array_merge($tarefasEntrega, $tarefasFila);
    $logs = flow_fila_logs_por_tarefa($conn, $todasTarefas);
    flow_fila_ordenar_unidades($unidadesFila, $etapasPorEntrega);

    $cache = [];
    $estimativas = [];
    foreach ($unidadesFila as $unidade) {
        $etapa = $etapasPorEntrega[(int) ($unidade['entrega_id'] ?? 0)][$unidade['codigo_etapa']] ?? [];
        $estimativas[$unidade['chave']] = flow_fila_estimativa_unidade($conn, $unidade, $etapa, $logs, $cache, $hoje);
    }
    $disponibilidades = flow_fila_disponibilidade_por_responsavel($unidadesFila, $estimativas, $entregaId, $hoje, (array) ($opcoes['overrides'] ?? []));
    $unidadesEtapa = [];
    foreach ($mapeadas as $unidade) {
        $unidadesEtapa[$unidade['codigo_etapa']][] = $unidade;
    }
    foreach ($mapeadas as $unidade) {
        if (!isset($estimativas[$unidade['chave']])) {
            $etapa = $etapasPorEntrega[$entregaId][$unidade['codigo_etapa']] ?? [];
            $estimativas[$unidade['chave']] = flow_fila_estimativa_unidade($conn, $unidade, $etapa, $logs, $cache, $hoje);
        }
    }
    $etapas = flow_fila_projetar_etapas($plano['vigente'], (array) $plano['baseline'], $unidadesEtapa, $estimativas, $disponibilidades, $hoje);
    $fim = null;
    foreach ($etapas as $etapa) {
        if (!empty($etapa['fim_operacional_projetado']) && ($fim === null || $etapa['fim_operacional_projetado'] > $fim)) {
            $fim = $etapa['fim_operacional_projetado'];
        }
    }
    $entrega = (string) ($plano['vigente']['data_entrega'] ?? $plano['vigente']['prazo_r00'] ?? '');
    $margem = flow_fila_margem_operacional($fim, $entrega);
    $status = $fim === null ? 'FILA_NAO_CALCULAVEL' : ($entrega && $fim > $entrega ? 'ATRASO_PROJETADO' : (($plano['vigente']['fim_previsto'] ?? null) && $fim > $plano['vigente']['fim_previsto'] ? 'MARGEM_CONSUMIDA' : 'NO_PLANO'));
    return [
        'tipo_fila' => !empty($opcoes['overrides']) ? 'CONFIRMADA' : FLOW_FILA_TIPO_DERIVADA, 'confianca_fila' => 'BAIXA', 'data_referencia' => $hoje,
        'entrega_id' => $entregaId, 'obra_id' => $plano['obra_id'], 'obra' => $plano['obra'],
        'planejamento_id' => $plano['planejamento_id'], 'versao_vigente_id' => $plano['versao_id'], 'baseline_versao_id' => $plano['baseline_versao_id'],
        'data_entrega' => $entrega, 'fim_planejado_vigente' => $plano['vigente']['fim_previsto'] ?? null,
        'margem_planejada_dias_uteis' => $plano['vigente']['margem_dias_uteis'] ?? null,
        'fim_operacional_projetado' => $fim, 'margem_operacional_dias_uteis' => $margem,
        'margem_consumida_dias_uteis' => $margem === null ? null : max(0, (int) ($plano['vigente']['margem_dias_uteis'] ?? 0) - $margem),
        'status_operacional' => $status, 'etapas' => array_values($etapas),
        'filas_responsaveis' => array_values($disponibilidades),
        'estimativas_etapas' => array_intersect_key($estimativas, array_flip(array_map(static fn (array $u): string => $u['chave'], $mapeadas))),
        'explicacao' => 'Fila derivada por prioridade, prazos, início planejado e imagem; nenhuma ordem ou tarefa foi alterada.',
    ];
}

function flow_fila_operacional_consultar(mysqli $conn, array $filtros = [], array $opcoes = []): array
{
    $planos = flow_fila_carregar_planos_confirmados($conn, $filtros);
    $projecoes = [];
    foreach ($planos as $entregaId => $plano) {
        $projecoes[$entregaId] = flow_fila_projetar_entrega($conn, (int) $entregaId, $planos, $opcoes);
    }
    return ['tipo' => 'PROJECAO_OPERACIONAL_READ_ONLY', 'projecoes' => array_values($projecoes)];
}
