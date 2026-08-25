<?php

/**
 * Central de Alocação (V1.4) — leitura consolidada de planejamento,
 * materialização operacional e responsáveis reais.
 *
 * Esta camada não cria nem altera funcao_imagem. Ela existe para separar:
 *   PLANEJADO (entrega_planejamento_funcao)
 *   MATERIALIZADO (funcao_imagem)
 *   ALOCADO (funcao_imagem.colaborador_id)
 *   CAPACIDADE POTENCIAL (funcao_colaborador)
 */

require_once __DIR__ . '/planejamento_capacidade_global_helper.php';

const FLOW_ALOCACAO_STATUS_PENDENTE_MATERIALIZACAO = 'PENDENTE_MATERIALIZACAO';
const FLOW_ALOCACAO_ALERTA_POOL_FINALIZACAO_HARDCODED = 'POOL_FINALIZACAO_HARDCODED';

function flow_alocacao_chave_etapa(int $entregaId, string $codigoEtapa): string
{
    return $entregaId . ':' . $codigoEtapa;
}

function flow_alocacao_etapa_virtual(string $codigoEtapa): bool
{
    return $codigoEtapa === 'FINALIZACAO_GLOBAL';
}

/**
 * O limite da etapa é um marco de conclusão no motor atual. A carga é
 * distribuída nos N dias úteis anteriores a esse marco, onde N é a duração
 * persistida — assim duração × pessoas permanece exatamente em pessoa-dias.
 */
function flow_alocacao_dias_planejados_etapa(string $inicio, string $limite, int $duracao): array
{
    if (!flow_capacidade_data_valida($inicio) || !flow_capacidade_data_valida($limite) || $duracao <= 0) {
        return [];
    }

    $cursor = date('Y-m-d', strtotime($inicio . ' -1 day'));
    $dias = [];
    while (count($dias) < $duracao) {
        $proximo = flow_planejamento_adicionar_dias_uteis($cursor, 1);
        if ($proximo >= $limite) {
            break;
        }
        $dias[] = $proximo;
        $cursor = $proximo;
    }

    // Planos históricos com duração/marco inconsistente continuam visíveis,
    // mas são marcados pelo chamador; não inventamos dias fora da janela.
    return $dias;
}

function flow_alocacao_responsavel_da_tarefa(array $tarefa): ?array
{
    $id = (int) ($tarefa['colaborador_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }
    return [
        'id' => $id,
        'nome' => trim((string) ($tarefa['responsavel_nome'] ?? 'Colaborador #' . $id)),
        'ativo' => (int) ($tarefa['responsavel_ativo'] ?? 0),
        'elegivel_capacidade' => (int) ($tarefa['responsavel_elegivel_capacidade'] ?? 1),
    ];
}

/**
 * Converte tarefas físicas em unidades de alocação. Caderno e Filtro mantêm
 * suas duas tarefas para acompanhamento, mas uma imagem equivale a uma única
 * unidade de capacidade planejada.
 */
function flow_alocacao_unidades_reais(string $codigoEtapa, array $tarefas): array
{
    $unidades = [];
    foreach ($tarefas as $tarefa) {
        $tarefaId = (int) ($tarefa['tarefa_id'] ?? 0);
        $imagemId = (int) ($tarefa['imagem_id'] ?? 0);
        $chave = $codigoEtapa === 'CADERNO_FILTRO'
            ? 'imagem:' . $imagemId
            : ($tarefaId > 0
                ? 'tarefa:' . $tarefaId
                : 'planejada:' . (int) ($tarefa['planejamento_item_id'] ?? $imagemId));
        if (!isset($unidades[$chave])) {
            $unidades[$chave] = [
                'unidade_id' => $chave,
                'imagem_id' => $imagemId,
                'imagem_nome' => (string) ($tarefa['imagem_nome'] ?? ''),
                'tipo_imagem' => (string) ($tarefa['tipo_imagem'] ?? ''),
                'tarefas' => [],
                'responsaveis' => [],
            ];
        }
        $unidades[$chave]['tarefas'][] = $tarefa;
        $responsavel = flow_alocacao_responsavel_da_tarefa($tarefa);
        if ($responsavel !== null) {
            $unidades[$chave]['responsaveis'][$responsavel['id']] = $responsavel;
        }
    }

    foreach ($unidades as &$unidade) {
        $unidade['responsaveis'] = array_values($unidade['responsaveis']);
        $unidade['tem_responsavel'] = count($unidade['responsaveis']) > 0;
        $unidade['responsabilidade_divergente'] = $codigoEtapa === 'CADERNO_FILTRO'
            && count($unidade['responsaveis']) > 1;
    }
    unset($unidade);
    return array_values($unidades);
}

function flow_alocacao_resumo_unidades(string $codigoEtapa, array $tarefas): array
{
    $unidades = flow_alocacao_unidades_reais($codigoEtapa, $tarefas);
    $pessoas = [];
    $alocadas = 0;
    $semResponsavel = 0;
    $divergentes = 0;
    $tarefasSemResponsavel = 0;

    foreach ($tarefas as $tarefa) {
        if (flow_alocacao_responsavel_da_tarefa($tarefa) === null) {
            $tarefasSemResponsavel++;
        }
    }

    foreach ($unidades as $unidade) {
        $responsaveis = $unidade['responsaveis'];
        if (!$responsaveis) {
            $semResponsavel++;
            continue;
        }
        $alocadas++;
        if (!empty($unidade['responsabilidade_divergente'])) {
            $divergentes++;
        }
        foreach ($responsaveis as $responsavel) {
            $id = (int) $responsavel['id'];
            if (!isset($pessoas[$id])) {
                $pessoas[$id] = $responsavel + [
                    'unidades_atribuidas' => 0,
                    'unidades_compartilhadas' => 0,
                    'tarefas_operacionais_atribuidas' => 0,
                ];
            }
            if (!empty($unidade['responsabilidade_divergente'])) {
                $pessoas[$id]['unidades_compartilhadas']++;
            } else {
                $pessoas[$id]['unidades_atribuidas']++;
            }
        }
        foreach ($unidade['tarefas'] as $tarefa) {
            $responsavel = flow_alocacao_responsavel_da_tarefa($tarefa);
            if ($responsavel !== null && isset($pessoas[(int) $responsavel['id']])) {
                $pessoas[(int) $responsavel['id']]['tarefas_operacionais_atribuidas']++;
            }
        }
    }

    return [
        'unidades' => $unidades,
        'tarefas_reais' => count($tarefas),
        'materializadas' => count($unidades),
        'alocadas' => $alocadas,
        'sem_responsavel' => $semResponsavel,
        'tarefas_reais_sem_responsavel' => $tarefasSemResponsavel,
        'responsabilidades_divergentes' => $divergentes,
        'pessoas' => $pessoas,
    ];
}

function flow_alocacao_tipo_atuacao_candidato(array $elegiveis, int $colaboradorId): ?string
{
    return isset($elegiveis[$colaboradorId])
        ? flow_capacidade_normalizar_tipo_atuacao($elegiveis[$colaboradorId]['tipo_atuacao'] ?? null)
        : null;
}

function flow_alocacao_status_etapa(array $resumo, int $pessoasPlanejadas, int $pendentesMaterializacao, bool $foraDoPlano, bool $temConflito): string
{
    $materializadas = (int) ($resumo['materializado'] ?? $resumo['materializadas'] ?? 0);
    $alocadas = (int) ($resumo['alocado'] ?? $resumo['alocadas'] ?? 0);
    $semResponsavel = (int) ($resumo['sem_responsavel'] ?? 0);
    if ($foraDoPlano) {
        return 'FORA_DO_PLANO';
    }
    if ($temConflito) {
        return 'CONFLITO';
    }
    if ($pendentesMaterializacao > 0 && $materializadas === 0) {
        return FLOW_ALOCACAO_STATUS_PENDENTE_MATERIALIZACAO;
    }
    if ($alocadas === 0) {
        return 'NAO_ALOCADO';
    }
    if ($semResponsavel > 0 || $pendentesMaterializacao > 0) {
        return 'PARCIALMENTE_ALOCADO';
    }
    $pessoasAtuais = count($resumo['pessoas']);
    if ($pessoasAtuais < $pessoasPlanejadas) {
        return 'SUBALOCADO';
    }
    if ($pessoasAtuais > $pessoasPlanejadas) {
        return 'SOBREALOCADO';
    }
    return 'ALOCADO';
}

function flow_alocacao_distribuicao_desequilibrada(array $pessoas): bool
{
    $volumes = array_values(array_filter(array_map(
        static fn (array $pessoa): int => (int) ($pessoa['unidades_atribuidas'] ?? 0),
        $pessoas
    ), static fn (int $volume): bool => $volume > 0));
    $total = array_sum($volumes);
    if (count($volumes) < 2 || $total < 4) {
        return false;
    }
    return max($volumes) / $total > 0.75;
}

function flow_alocacao_carregar_tarefas_reais(mysqli $conn, array $entregaIds): array
{
    if (!$entregaIds) {
        return [];
    }
    $ids = implode(',', array_map('intval', array_values(array_unique($entregaIds))));
    $sql = "SELECT ei.entrega_id,
                   fi.idfuncao_imagem AS tarefa_id, fi.imagem_id, fi.funcao_id,
                   fi.colaborador_id, fi.status, fi.prazo,
                   ico.imagem_nome, ico.tipo_imagem,
                   c.nome_colaborador AS responsavel_nome,
                   c.ativo AS responsavel_ativo,
                   c.elegivel_capacidade AS responsavel_elegivel_capacidade
              FROM entregas_itens ei
              JOIN funcao_imagem fi ON fi.imagem_id = ei.imagem_id
              JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
              LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
             WHERE ei.entrega_id IN ({$ids})
             ORDER BY ei.entrega_id, fi.imagem_id, fi.funcao_id, fi.idfuncao_imagem";
    $resultado = $conn->query($sql);
    if (!$resultado) {
        throw new RuntimeException('Não foi possível carregar tarefas reais: ' . $conn->error);
    }
    $linhas = $resultado->fetch_all(MYSQLI_ASSOC);
    $resultado->free();
    return $linhas;
}

function flow_alocacao_carregar_pendentes_materializacao(mysqli $conn, array $entregaIds): array
{
    if (!$entregaIds) {
        return [];
    }
    $ids = implode(',', array_map('intval', array_values(array_unique($entregaIds))));
    $sql = "SELECT ei.entrega_id,
                   ifp.idimagem_funcao_planejada AS planejamento_item_id,
                   ifp.imagem_id, ifp.funcao_id, ifp.status,
                   ico.imagem_nome, ico.tipo_imagem
              FROM entregas_itens ei
              JOIN imagem_funcao_planejada ifp ON ifp.imagem_id = ei.imagem_id
              JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = ifp.imagem_id
              LEFT JOIN funcao_imagem fi
                ON fi.imagem_id = ifp.imagem_id
               AND fi.funcao_id = ifp.funcao_id
             WHERE ei.entrega_id IN ({$ids})
               AND ifp.status <> 'CANCELADO'
               AND ifp.funcao_imagem_id IS NULL
               AND fi.idfuncao_imagem IS NULL
             ORDER BY ei.entrega_id, ifp.imagem_id, ifp.funcao_id, ifp.idimagem_funcao_planejada";
    $resultado = $conn->query($sql);
    if (!$resultado) {
        throw new RuntimeException('Não foi possível carregar pendências de materialização: ' . $conn->error);
    }
    $linhas = $resultado->fetch_all(MYSQLI_ASSOC);
    $resultado->free();
    return $linhas;
}

function flow_alocacao_candidatos_da_etapa(array $elegiveis, array $pessoasAtuais, array $cargas): array
{
    $candidatos = [];
    foreach ($elegiveis as $id => $elegivel) {
        $id = (int) ($elegivel['id'] ?? $id);
        $atual = $pessoasAtuais[$id] ?? null;
        $carga = $cargas[$id] ?? [];
        $temConflito = !empty($carga['conflitos']);
        $tipo = flow_capacidade_normalizar_tipo_atuacao($elegivel['tipo_atuacao'] ?? null);
        $candidatos[] = [
            'colaborador_id' => $id,
            'nome' => (string) ($elegivel['nome'] ?? ('Colaborador #' . $id)),
            'tipo_atuacao' => $tipo,
            'elegivel' => true,
            'responsavel_atual' => $atual !== null,
            'tarefas_atribuidas' => (int) ($atual['unidades_atribuidas'] ?? 0),
            'tarefas_compartilhadas_sem_peso' => (int) ($atual['unidades_compartilhadas'] ?? 0),
            'carga_planejada_pessoa_dia' => round((float) ($carga['pessoa_dias'] ?? 0), 2),
            'carga_periodo' => round((float) ($carga['pico_carga'] ?? 0), 4),
            'carga_periodo_percentual' => round(100 * (float) ($carga['pico_carga'] ?? 0), 1),
            'conflitos' => array_values($carga['conflitos'] ?? []),
            'classificacao' => $atual !== null
                ? ($temConflito ? 'RESPONSAVEL_ATUAL_COM_CONFLITO' : 'RESPONSAVEL_ATUAL')
                : ($temConflito ? 'CONFLITO' : ($tipo === FLOW_TIPO_ATUACAO_PRINCIPAL ? 'RECOMENDADO' : 'APOIO_DISPONIVEL')),
        ];
    }

    usort($candidatos, static function (array $a, array $b): int {
        $peso = static function (array $item): int {
            $conflito = !empty($item['conflitos']);
            if (!empty($item['responsavel_atual']) && !$conflito) {
                return 10;
            }
            if ($item['tipo_atuacao'] === FLOW_TIPO_ATUACAO_PRINCIPAL && !$conflito) {
                return 20;
            }
            if ($item['tipo_atuacao'] === FLOW_TIPO_ATUACAO_SECUNDARIA && !$conflito) {
                return 30;
            }
            if ($item['tipo_atuacao'] === FLOW_TIPO_ATUACAO_PRINCIPAL) {
                return 40;
            }
            return 50;
        };
        return $peso($a) <=> $peso($b)
            ?: ((float) $a['carga_periodo'] <=> (float) $b['carga_periodo'])
            ?: strcmp((string) $a['nome'], (string) $b['nome']);
    });
    return $candidatos;
}

/** Recorta a carga global do colaborador à janela da etapa consultada. */
function flow_alocacao_carga_na_janela(array $carga, array $diasDaEtapa): array
{
    $diasPermitidos = array_flip($diasDaEtapa);
    $dias = [];
    $referencias = [];
    foreach ((array) ($carga['dias'] ?? []) as $data => $ocupacao) {
        if (!isset($diasPermitidos[$data])) {
            continue;
        }
        $dias[$data] = (float) $ocupacao;
        $referencias[$data] = array_values($carga['referencias'][$data] ?? []);
    }
    $conflitos = [];
    foreach ($dias as $data => $ocupacao) {
        if ($ocupacao > 1.0001) {
            $conflitos[] = [
                'data' => $data,
                'carga_periodo' => round($ocupacao, 4),
                'percentual' => round($ocupacao * 100, 1),
                'referencias' => $referencias[$data],
            ];
        }
    }
    return [
        'pessoa_dias' => array_sum($dias),
        'pico_carga' => $dias ? max($dias) : 0.0,
        'conflitos' => $conflitos,
    ];
}

function flow_alocacao_consultar(mysqli $conn, string $inicio, string $fim, array $opcoes = []): array
{
    if (!flow_capacidade_data_valida($inicio) || !flow_capacidade_data_valida($fim) || $fim < $inicio) {
        throw new InvalidArgumentException('Informe um período válido para consultar a alocação.');
    }
    if (!flow_capacidade_tabelas_disponiveis($conn)) {
        throw new RuntimeException('A migration de capacidade global ainda não foi aplicada.');
    }

    $planos = flow_capacidade_carregar_planos_vigentes($conn, $inicio, $fim, $opcoes);
    $definicoes = flow_capacidade_definicoes_etapas();
    $configuracoes = flow_capacidade_carregar_configuracoes_colaboradores($conn);
    $indices = [];
    $entradas = [];

    foreach ($planos as $plano) {
        foreach (($plano['etapas'] ?? []) as $etapa) {
            $codigo = (string) ($etapa['codigo_etapa'] ?? '');
            $metadados = json_decode((string) ($etapa['metadados_json'] ?? ''), true) ?: [];
            if ($codigo === '' || flow_alocacao_etapa_virtual($codigo) || !isset($definicoes[$codigo]) || !empty($metadados['nao_aplicavel'])) {
                continue;
            }
            $chave = flow_alocacao_chave_etapa((int) $plano['entrega_id'], $codigo);
            $indices[$chave] = true;
            $entradas[$chave] = [
                'chave' => $chave,
                'plano' => $plano,
                'etapa' => $etapa,
                'codigo_etapa' => $codigo,
                'tarefas_reais' => [],
                'pendencias_registradas' => [],
            ];
        }
    }

    $entregaIds = array_values(array_unique(array_map(static fn (array $plano): int => (int) $plano['entrega_id'], $planos)));
    foreach (flow_alocacao_carregar_tarefas_reais($conn, $entregaIds) as $tarefa) {
        if (flow_planejamento_normalizar((string) ($tarefa['status'] ?? '')) === 'cancelado') {
            continue;
        }
        $codigo = flow_planejamento_codigo_etapa($tarefa);
        $chave = flow_alocacao_chave_etapa((int) $tarefa['entrega_id'], (string) $codigo);
        if ($codigo !== null && isset($entradas[$chave])) {
            $entradas[$chave]['tarefas_reais'][] = $tarefa;
        }
    }
    foreach (flow_alocacao_carregar_pendentes_materializacao($conn, $entregaIds) as $pendencia) {
        $codigo = flow_planejamento_codigo_etapa($pendencia);
        $chave = flow_alocacao_chave_etapa((int) $pendencia['entrega_id'], (string) $codigo);
        if ($codigo !== null && isset($entradas[$chave])) {
            $entradas[$chave]['pendencias_registradas'][] = $pendencia;
        }
    }

    $cargasPorPessoa = [];
    $resultadoEtapas = [];
    $excecoes = [];
    foreach ($entradas as $chave => $entrada) {
        $codigo = $entrada['codigo_etapa'];
        $etapa = $entrada['etapa'];
        $plano = $entrada['plano'];
        $resumo = flow_alocacao_resumo_unidades($codigo, $entrada['tarefas_reais']);
        $planejado = max(0, (int) ($etapa['volume'] ?? 0));
        $pessoasPlanejadas = max(0, (int) ($etapa['pessoas_alocadas'] ?? 0));
        $duracao = max(0, (int) ($etapa['duracao_dias_uteis'] ?? 0));
        $cargaTotal = $duracao * $pessoasPlanejadas;
        $cargaPorUnidade = $planejado > 0 ? $cargaTotal / $planejado : 0.0;
        $materializadas = (int) $resumo['materializadas'];
        $pendentes = max(0, $planejado - $materializadas);
        $pendenciasRegistradas = flow_alocacao_unidades_reais($codigo, $entrada['pendencias_registradas']);
        $dias = flow_alocacao_dias_planejados_etapa((string) ($etapa['data_inicio'] ?? ''), (string) ($etapa['data_limite'] ?? ''), $duracao);
        $elegiveis = [];
        foreach (($configuracoes[$codigo]['colaboradores'] ?? []) as $candidato) {
            $elegiveis[(int) $candidato['id']] = $candidato;
        }

        $pessoasAtuais = $resumo['pessoas'];
        $foraDoPlano = false;
        foreach ($pessoasAtuais as $id => &$pessoa) {
            $pessoa['tipo_atuacao'] = flow_alocacao_tipo_atuacao_candidato($elegiveis, (int) $id);
            $pessoa['elegivel'] = isset($elegiveis[(int) $id]);
            $pessoa['carga_planejada_pessoa_dia'] = 0.0;
            $pessoa['carga_compartilhada_sem_peso_pessoa_dia'] = 0.0;
            if (!$pessoa['elegivel']) {
                $foraDoPlano = true;
            }
        }
        unset($pessoa);

        $cargaAtribuida = 0.0;
        $cargaCompartilhada = 0.0;
        $cargaNaoAtribuida = 0.0;
        foreach ($resumo['unidades'] as $indice => $unidade) {
            $cargaUnidade = $indice < $planejado ? $cargaPorUnidade : 0.0;
            $responsaveis = $unidade['responsaveis'];
            if (!$responsaveis) {
                $cargaNaoAtribuida += $cargaUnidade;
                continue;
            }
            if (!empty($unidade['responsabilidade_divergente'])) {
                $cargaCompartilhada += $cargaUnidade;
                foreach ($responsaveis as $responsavel) {
                    $id = (int) $responsavel['id'];
                    if (isset($pessoasAtuais[$id])) {
                        $pessoasAtuais[$id]['carga_compartilhada_sem_peso_pessoa_dia'] += $cargaUnidade;
                    }
                }
                continue;
            }
            $responsavel = $responsaveis[0];
            $id = (int) $responsavel['id'];
            $cargaAtribuida += $cargaUnidade;
            if (isset($pessoasAtuais[$id])) {
                $pessoasAtuais[$id]['carga_planejada_pessoa_dia'] += $cargaUnidade;
            }
            if ($dias) {
                $cargaPorDia = $cargaUnidade / count($dias);
                foreach ($dias as $dia) {
                    if ($dia < $inicio || $dia > $fim) {
                        continue;
                    }
                    if (!isset($cargasPorPessoa[$id])) {
                        $cargasPorPessoa[$id] = ['pessoa_dias' => 0.0, 'dias' => [], 'referencias' => []];
                    }
                    $cargasPorPessoa[$id]['pessoa_dias'] += $cargaPorDia;
                    $cargasPorPessoa[$id]['dias'][$dia] = ($cargasPorPessoa[$id]['dias'][$dia] ?? 0.0) + $cargaPorDia;
                    $cargasPorPessoa[$id]['referencias'][$dia][] = [
                        'obra' => (string) ($plano['nomenclatura'] ?: $plano['nome_obra']),
                        'entrega_id' => (int) $plano['entrega_id'],
                        'codigo_etapa' => $codigo,
                    ];
                }
            }
        }
        foreach ($pessoasAtuais as &$pessoa) {
            $pessoa['carga_planejada_pessoa_dia'] = round((float) $pessoa['carga_planejada_pessoa_dia'], 2);
            $pessoa['carga_compartilhada_sem_peso_pessoa_dia'] = round((float) $pessoa['carga_compartilhada_sem_peso_pessoa_dia'], 2);
        }
        unset($pessoa);

        if ($materializadas > $planejado) {
            $excecoes[] = ['codigo' => 'VOLUME_MATERIALIZADO_ACIMA_PLANEJADO', 'entrega_id' => (int) $plano['entrega_id'], 'codigo_etapa' => $codigo];
        }
        if ($duracao > 0 && count($dias) !== $duracao) {
            $excecoes[] = ['codigo' => 'JANELA_DE_CARGA_INCONSISTENTE', 'entrega_id' => (int) $plano['entrega_id'], 'codigo_etapa' => $codigo];
        }
        if (in_array($codigo, ['FINALIZACAO_EXTERNA', 'FINALIZACAO_INTERNA', 'FINALIZACAO_PLANTA'], true)) {
            $excecoes[FLOW_ALOCACAO_ALERTA_POOL_FINALIZACAO_HARDCODED] = [
                'codigo' => FLOW_ALOCACAO_ALERTA_POOL_FINALIZACAO_HARDCODED,
                'mensagem' => 'Pools de Finalização reutilizam a configuração central atual, que ainda identifica os pools por nome.',
            ];
        }

        $resultadoEtapas[$chave] = [
            'entrega_id' => (int) $plano['entrega_id'],
            'planejamento_id' => (int) $plano['planejamento_id'],
            'versao_id' => (int) $plano['versao_id'],
            'obra_id' => (int) $plano['obra_id'],
            'obra' => (string) ($plano['nomenclatura'] ?: $plano['nome_obra']),
            'codigo_etapa' => $codigo,
            'etapa' => (string) ($etapa['nome_etapa'] ?? $definicoes[$codigo]['nome']),
            'inicio' => (string) ($etapa['data_inicio'] ?? ''),
            'limite' => (string) ($etapa['data_limite'] ?? ''),
            'duracao_dias_uteis' => $duracao,
            'pessoas_planejadas' => $pessoasPlanejadas,
            'planejado' => $planejado,
            'materializado' => $materializadas,
            'alocado' => (int) $resumo['alocadas'],
            'sem_responsavel' => (int) $resumo['sem_responsavel'],
            'tarefas_reais' => (int) $resumo['tarefas_reais'],
            'tarefas_reais_sem_responsavel' => (int) $resumo['tarefas_reais_sem_responsavel'],
            'pendente_materializacao' => $pendentes,
            'pendente_materializacao_registrado' => count($pendenciasRegistradas),
            'responsabilidades_divergentes' => (int) $resumo['responsabilidades_divergentes'],
            'pessoas_atualmente_alocadas' => count($pessoasAtuais),
            'pessoas' => array_values($pessoasAtuais),
            'elegiveis' => $elegiveis,
            'carga_total_planejada_pessoa_dia' => round($cargaTotal, 2),
            'carga_nominal_atribuida_pessoa_dia' => round($cargaAtribuida, 2),
            'carga_compartilhada_sem_peso_pessoa_dia' => round($cargaCompartilhada, 2),
            'carga_nao_atribuida_pessoa_dia' => round($cargaNaoAtribuida, 2),
            'carga_nao_materializada_pessoa_dia' => round(max(0.0, $cargaTotal - ($cargaPorUnidade * min($planejado, $materializadas))), 2),
            'distribuicao_desequilibrada' => flow_alocacao_distribuicao_desequilibrada($pessoasAtuais),
            'status_alocacao' => '',
            'dias_carga' => $dias,
            'tarefas_operacionais' => $resumo['unidades'],
        ];
    }

    foreach ($cargasPorPessoa as $id => &$carga) {
        $conflitos = [];
        foreach ($carga['dias'] as $dia => $ocupacao) {
            if ($ocupacao > 1.0001) {
                $conflitos[] = [
                    'data' => $dia,
                    'carga_periodo' => round($ocupacao, 4),
                    'percentual' => round($ocupacao * 100, 1),
                    'referencias' => array_values($carga['referencias'][$dia] ?? []),
                ];
            }
        }
        $carga['pico_carga'] = $carga['dias'] ? max($carga['dias']) : 0.0;
        $carga['conflitos'] = $conflitos;
    }
    unset($carga);

    $grupos = [];
    foreach ($resultadoEtapas as &$etapa) {
        $temConflito = false;
        $cargasDaEtapa = [];
        foreach ($cargasPorPessoa as $id => $cargaGlobal) {
            $cargasDaEtapa[(int) $id] = flow_alocacao_carga_na_janela($cargaGlobal, $etapa['dias_carga']);
        }
        foreach ($etapa['pessoas'] as &$pessoa) {
            $carga = $cargasDaEtapa[(int) $pessoa['id']] ?? ['pessoa_dias' => 0.0, 'pico_carga' => 0.0, 'conflitos' => []];
            $pessoa['carga_periodo'] = round((float) $carga['pico_carga'], 4);
            $pessoa['carga_periodo_percentual'] = round(100 * (float) $carga['pico_carga'], 1);
            $pessoa['conflitos'] = array_values($carga['conflitos']);
            $temConflito = $temConflito || !empty($pessoa['conflitos']);
        }
        unset($pessoa);
        $foraDoPlano = (bool) array_filter($etapa['pessoas'], static fn (array $pessoa): bool => empty($pessoa['elegivel']));
        $etapa['candidatos'] = flow_alocacao_candidatos_da_etapa($etapa['elegiveis'], array_column($etapa['pessoas'], null, 'id'), $cargasDaEtapa);
        unset($etapa['elegiveis']);
        unset($etapa['dias_carga']);
        $etapa['status_alocacao'] = flow_alocacao_status_etapa($etapa, (int) $etapa['pessoas_planejadas'], (int) $etapa['pendente_materializacao'], $foraDoPlano, $temConflito);
        $grupos[$etapa['codigo_etapa']][] = $etapa;
    }
    unset($etapa);

    $resultadoGrupos = [];
    foreach ($grupos as $codigo => $etapas) {
        $primeira = $etapas[0];
        $resultadoGrupos[] = [
            'codigo_etapa' => $codigo,
            'etapa' => $primeira['etapa'],
            'ordem_painel' => (int) ($definicoes[$codigo]['ordem_painel'] ?? 999),
            'planejado' => array_sum(array_column($etapas, 'planejado')),
            'materializado' => array_sum(array_column($etapas, 'materializado')),
            'alocado' => array_sum(array_column($etapas, 'alocado')),
            'sem_responsavel' => array_sum(array_column($etapas, 'sem_responsavel')),
            'pendente_materializacao' => array_sum(array_column($etapas, 'pendente_materializacao')),
            'projetos' => array_values($etapas),
        ];
    }
    usort($resultadoGrupos, static fn (array $a, array $b): int => $a['ordem_painel'] <=> $b['ordem_painel']);

    $resumo = ['planejado' => 0, 'materializado' => 0, 'alocado' => 0, 'sem_responsavel' => 0, 'pendente_materializacao' => 0, 'conflitos' => 0];
    foreach ($resultadoEtapas as $etapa) {
        foreach (['planejado', 'materializado', 'alocado', 'sem_responsavel', 'pendente_materializacao'] as $campo) {
            $resumo[$campo] += (int) $etapa[$campo];
        }
        $resumo['conflitos'] += count(array_filter($etapa['pessoas'], static fn (array $pessoa): bool => !empty($pessoa['conflitos'])));
    }

    return [
        'periodo' => ['inicio' => $inicio, 'fim' => $fim],
        'modo' => 'READ_ONLY_SANDBOX',
        'resumo' => $resumo + ['planos' => count($planos), 'etapas' => count($resultadoEtapas)],
        'grupos' => $resultadoGrupos,
        'excecoes' => array_values($excecoes),
        'formula_carga' => 'carga_total_planejada_pessoa_dia = duracao_dias_uteis × pessoas_planejadas; a carga nominal só é atribuída a tarefas operacionais reais com um único responsável.',
    ];
}
