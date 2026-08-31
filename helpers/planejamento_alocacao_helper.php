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
const FLOW_ALOCACAO_STATUS_NORMAL = 'NORMAL';
const FLOW_ALOCACAO_STATUS_SOBRECARGA_NAO_VALIDADA = 'SOBRECARGA_NAO_VALIDADA';
const FLOW_ALOCACAO_STATUS_SOBRECARGA_VALIDADA = 'SOBRECARGA_VALIDADA';
const FLOW_ALOCACAO_STATUS_VALIDACAO_DESATUALIZADA = 'VALIDACAO_DESATUALIZADA';
const FLOW_ALOCACAO_STATUS_CAPACIDADE_PENDENTE_VALIDACAO = 'CAPACIDADE_PENDENTE_VALIDACAO';
const FLOW_ALOCACAO_STATUS_ALOCACAO_VALIDADA_EXCECAO = 'ALOCACAO_VALIDADA_COM_EXCECAO';

function flow_alocacao_validacoes_disponiveis(mysqli $conn): bool
{
    static $cache = [];
    $key = spl_object_id($conn);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'entrega_planejamento_capacidade_validacao' LIMIT 1");
    if (!$stmt) {
        return $cache[$key] = false;
    }
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $cache[$key] = $exists;
}

function flow_alocacao_json_canonico(array $valor): string
{
    $normalizar = static function ($item) use (&$normalizar) {
        if (!is_array($item)) {
            return $item;
        }
        if (array_is_list($item)) {
            return array_map($normalizar, $item);
        }
        ksort($item);
        foreach ($item as $chave => $parte) {
            $item[$chave] = $normalizar($parte);
        }
        return $item;
    };
    return json_encode($normalizar($valor), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
}

function flow_alocacao_status_carga(float $pico, bool $validacaoVigente = false, bool $validacaoAnterior = false): string
{
    if ($pico <= 1.0001) {
        return FLOW_ALOCACAO_STATUS_NORMAL;
    }
    if ($validacaoVigente) {
        return FLOW_ALOCACAO_STATUS_SOBRECARGA_VALIDADA;
    }
    return $validacaoAnterior
        ? FLOW_ALOCACAO_STATUS_VALIDACAO_DESATUALIZADA
        : FLOW_ALOCACAO_STATUS_SOBRECARGA_NAO_VALIDADA;
}

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

function flow_alocacao_status_etapa(array $resumo, int $pessoasPlanejadas, int $pendentesMaterializacao, bool $foraDoPlano, bool $temConflito, bool $temSobrecargaNaoValidada = false, bool $temSobrecargaValidada = false): string
{
    $materializadas = (int) ($resumo['materializado'] ?? $resumo['materializadas'] ?? 0);
    $alocadas = (int) ($resumo['alocado'] ?? $resumo['alocadas'] ?? 0);
    $semResponsavel = (int) ($resumo['sem_responsavel'] ?? 0);
    if ($foraDoPlano) {
        return 'FORA_DO_PLANO';
    }
    if ($temSobrecargaNaoValidada) {
        return FLOW_ALOCACAO_STATUS_CAPACIDADE_PENDENTE_VALIDACAO;
    }
    if ($temSobrecargaValidada) {
        return FLOW_ALOCACAO_STATUS_ALOCACAO_VALIDADA_EXCECAO;
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
        'dias' => $dias,
        'referencias' => $referencias,
        'conflitos' => $conflitos,
    ];
}

function flow_alocacao_carregar_validacoes(mysqli $conn, array $planos): array
{
    if (!$planos || !flow_alocacao_validacoes_disponiveis($conn)) {
        return [];
    }
    $versoes = array_values(array_unique(array_filter(array_map(static fn (array $plano): int => (int) ($plano['versao_id'] ?? 0), $planos))));
    if (!$versoes) {
        return [];
    }
    $sql = 'SELECT * FROM entrega_planejamento_capacidade_validacao WHERE versao_id IN (' . implode(',', $versoes) . ') AND status = \'VALIDA\' ORDER BY id DESC';
    $resultado = $conn->query($sql);
    if (!$resultado) {
        throw new RuntimeException('Não foi possível carregar validações de capacidade: ' . $conn->error);
    }
    $validacoes = [];
    while ($linha = $resultado->fetch_assoc()) {
        $chave = (int) $linha['versao_id'] . ':' . (int) $linha['colaborador_id'] . ':' . (string) $linha['codigo_etapa'];
        $validacoes[$chave][] = $linha;
    }
    $resultado->free();
    return $validacoes;
}

function flow_alocacao_validacao_contextual(array $validacoes, array $etapa, array $pessoa, string $fingerprint): array
{
    $chave = (int) ($etapa['versao_id'] ?? 0) . ':' . (int) ($pessoa['id'] ?? 0) . ':' . (string) ($etapa['codigo_etapa'] ?? '');
    $historico = $validacoes[$chave] ?? [];
    foreach ($historico as $validacao) {
        if ((string) ($validacao['fingerprint'] ?? '') === $fingerprint) {
            return [
                'status' => FLOW_ALOCACAO_STATUS_SOBRECARGA_VALIDADA,
                'registro' => $validacao,
            ];
        }
    }
    return [
        'status' => $historico ? FLOW_ALOCACAO_STATUS_VALIDACAO_DESATUALIZADA : FLOW_ALOCACAO_STATUS_SOBRECARGA_NAO_VALIDADA,
        'registro' => $historico[0] ?? null,
    ];
}

function flow_alocacao_fingerprint_pessoa(array $etapa, array $pessoa, array $dias, array $tarefas): string
{
    $tarefasContexto = [];
    foreach ($tarefas as $unidade) {
        foreach (($unidade['tarefas'] ?? []) as $tarefa) {
            $tarefasContexto[] = [
                'id' => (int) ($tarefa['tarefa_id'] ?? 0),
                'responsavel' => (int) ($tarefa['colaborador_id'] ?? 0),
                'status' => (string) ($tarefa['status'] ?? ''),
            ];
        }
    }
    usort($tarefasContexto, static fn (array $a, array $b): int => ($a['id'] <=> $b['id']) ?: ($a['responsavel'] <=> $b['responsavel']));
    return hash('sha256', flow_alocacao_json_canonico([
        'planejamento_id' => (int) ($etapa['planejamento_id'] ?? 0),
        'versao_id' => (int) ($etapa['versao_id'] ?? 0),
        'entrega_id' => (int) ($etapa['entrega_id'] ?? 0),
        'obra_id' => (int) ($etapa['obra_id'] ?? 0),
        'codigo_etapa' => (string) ($etapa['codigo_etapa'] ?? ''),
        'colaborador_id' => (int) ($pessoa['id'] ?? 0),
        'inicio' => (string) ($etapa['inicio'] ?? ''),
        'limite' => (string) ($etapa['limite'] ?? ''),
        'duracao' => (int) ($etapa['duracao_dias_uteis'] ?? 0),
        'pessoas_planejadas' => (int) ($etapa['pessoas_planejadas'] ?? 0),
        'planejado' => (int) ($etapa['planejado'] ?? 0),
        'dias' => $dias,
        'tarefas' => $tarefasContexto,
    ]));
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
    $validacoes = flow_alocacao_carregar_validacoes($conn, $planos);
    $responsaveisOverride = [];
    foreach ((array) ($opcoes['responsaveis_override'] ?? []) as $tarefaId => $colaboradorId) {
        $tarefaId = (int) $tarefaId;
        $colaboradorId = (int) $colaboradorId;
        if ($tarefaId > 0 && $colaboradorId > 0) {
            $responsaveisOverride[$tarefaId] = $colaboradorId;
        }
    }
    $colaboradoresPorId = [];
    foreach ($configuracoes as $configuracao) {
        foreach (($configuracao['colaboradores'] ?? []) as $colaborador) {
            $colaboradoresPorId[(int) $colaborador['id']] = $colaborador;
        }
    }
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
        $tarefaId = (int) ($tarefa['tarefa_id'] ?? 0);
        if (isset($responsaveisOverride[$tarefaId])) {
            $novoResponsavelId = $responsaveisOverride[$tarefaId];
            $tarefa['colaborador_id'] = $novoResponsavelId;
            if (isset($colaboradoresPorId[$novoResponsavelId])) {
                $tarefa['responsavel_nome'] = $colaboradoresPorId[$novoResponsavelId]['nome'];
                $tarefa['responsavel_ativo'] = 1;
                $tarefa['responsavel_elegivel_capacidade'] = 1;
            }
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
        $temSobrecargaNaoValidada = false;
        $temSobrecargaValidada = false;
        $cargasDaEtapa = [];
        foreach ($cargasPorPessoa as $id => $cargaGlobal) {
            $cargasDaEtapa[(int) $id] = flow_alocacao_carga_na_janela($cargaGlobal, $etapa['dias_carga']);
        }
        foreach ($etapa['pessoas'] as &$pessoa) {
            $carga = $cargasDaEtapa[(int) $pessoa['id']] ?? ['pessoa_dias' => 0.0, 'pico_carga' => 0.0, 'dias' => [], 'referencias' => [], 'conflitos' => []];
            $pessoa['carga_periodo'] = round((float) $carga['pico_carga'], 4);
            $pessoa['carga_periodo_percentual'] = round(100 * (float) $carga['pico_carga'], 1);
            $pessoa['conflitos'] = array_values($carga['conflitos']);
            $pessoa['carga_dias'] = array_map(
                static fn (float $ocupacao, string $dia): array => [
                    'data' => $dia,
                    'carga' => round($ocupacao, 4),
                    'percentual' => round($ocupacao * 100, 1),
                    'referencias' => array_values($carga['referencias'][$dia] ?? []),
                ],
                $carga['dias'],
                array_keys($carga['dias'])
            );
            $pessoa['fingerprint_carga'] = flow_alocacao_fingerprint_pessoa($etapa, $pessoa, $pessoa['carga_dias'], $etapa['tarefas_operacionais']);
            $validacao = flow_alocacao_validacao_contextual($validacoes, $etapa, $pessoa, $pessoa['fingerprint_carga']);
            $pessoa['status_carga'] = flow_alocacao_status_carga((float) $carga['pico_carga'], $validacao['status'] === FLOW_ALOCACAO_STATUS_SOBRECARGA_VALIDADA, $validacao['status'] === FLOW_ALOCACAO_STATUS_VALIDACAO_DESATUALIZADA);
            $pessoa['validacao_excepcional'] = $validacao['registro'];
            $temConflito = $temConflito || !empty($pessoa['conflitos']);
            $temSobrecargaNaoValidada = $temSobrecargaNaoValidada || in_array($pessoa['status_carga'], [FLOW_ALOCACAO_STATUS_SOBRECARGA_NAO_VALIDADA, FLOW_ALOCACAO_STATUS_VALIDACAO_DESATUALIZADA], true);
            $temSobrecargaValidada = $temSobrecargaValidada || $pessoa['status_carga'] === FLOW_ALOCACAO_STATUS_SOBRECARGA_VALIDADA;
        }
        unset($pessoa);
        $foraDoPlano = (bool) array_filter($etapa['pessoas'], static fn (array $pessoa): bool => empty($pessoa['elegivel']));
        $etapa['candidatos'] = flow_alocacao_candidatos_da_etapa($etapa['elegiveis'], array_column($etapa['pessoas'], null, 'id'), $cargasDaEtapa);
        unset($etapa['elegiveis']);
        unset($etapa['dias_carga']);
        $etapa['status_alocacao'] = flow_alocacao_status_etapa($etapa, (int) $etapa['pessoas_planejadas'], (int) $etapa['pendente_materializacao'], $foraDoPlano, $temConflito, $temSobrecargaNaoValidada, $temSobrecargaValidada);
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

    $resumo = [
        'planejado' => 0,
        'materializado' => 0,
        'alocado' => 0,
        'sem_responsavel' => 0,
        'pendente_materializacao' => 0,
        'conflitos' => 0,
        'etapas_ok' => 0,
        'sobrecargas' => 0,
        'sobrecargas_validadas' => 0,
        'aguardando_validacao' => 0,
    ];
    $contextoEtapas = [];
    $contextoTarefas = [];
    foreach ($resultadoEtapas as $etapa) {
        foreach (['planejado', 'materializado', 'alocado', 'sem_responsavel', 'pendente_materializacao'] as $campo) {
            $resumo[$campo] += (int) $etapa[$campo];
        }
        $resumo['conflitos'] += count(array_filter($etapa['pessoas'], static fn (array $pessoa): bool => !empty($pessoa['conflitos'])));
        if ($etapa['status_alocacao'] === FLOW_ALOCACAO_STATUS_CAPACIDADE_PENDENTE_VALIDACAO) {
            $resumo['aguardando_validacao']++;
        } elseif ($etapa['status_alocacao'] === FLOW_ALOCACAO_STATUS_ALOCACAO_VALIDADA_EXCECAO) {
            $resumo['sobrecargas_validadas']++;
        } elseif ($etapa['status_alocacao'] === 'ALOCADO') {
            $resumo['etapas_ok']++;
        }
        foreach ($etapa['pessoas'] as $pessoa) {
            if ((float) ($pessoa['carga_periodo'] ?? 0) > 1.0001) {
                $resumo['sobrecargas']++;
            }
        }
        $contextoEtapas[] = [
            'planejamento_id' => (int) $etapa['planejamento_id'],
            'versao_id' => (int) $etapa['versao_id'],
            'entrega_id' => (int) $etapa['entrega_id'],
            'codigo_etapa' => (string) $etapa['codigo_etapa'],
            'volume' => (int) $etapa['planejado'],
            'pessoas' => (int) $etapa['pessoas_planejadas'],
            'duracao' => (int) $etapa['duracao_dias_uteis'],
            'inicio' => (string) $etapa['inicio'],
            'limite' => (string) $etapa['limite'],
        ];
        foreach ($etapa['tarefas_operacionais'] as $unidade) {
            foreach (($unidade['tarefas'] ?? []) as $tarefa) {
                $contextoTarefas[] = [
                    'tarefa_id' => (int) ($tarefa['tarefa_id'] ?? 0),
                    'entrega_id' => (int) ($tarefa['entrega_id'] ?? $etapa['entrega_id']),
                    'imagem_id' => (int) ($tarefa['imagem_id'] ?? 0),
                    'funcao_id' => (int) ($tarefa['funcao_id'] ?? 0),
                    'colaborador_id' => (int) ($tarefa['colaborador_id'] ?? 0),
                    'status' => (string) ($tarefa['status'] ?? ''),
                ];
            }
        }
    }
    usort($contextoEtapas, static fn (array $a, array $b): int => strcmp(flow_alocacao_json_canonico($a), flow_alocacao_json_canonico($b)));
    usort($contextoTarefas, static fn (array $a, array $b): int => ($a['tarefa_id'] <=> $b['tarefa_id']) ?: strcmp(flow_alocacao_json_canonico($a), flow_alocacao_json_canonico($b)));
    $contexto = [
        'periodo' => ['inicio' => $inicio, 'fim' => $fim],
        'etapas' => $contextoEtapas,
        'tarefas' => $contextoTarefas,
    ];
    $fingerprint = hash('sha256', flow_alocacao_json_canonico($contexto));

    return [
        'periodo' => ['inicio' => $inicio, 'fim' => $fim],
        'modo' => 'READ_ONLY_SANDBOX',
        'fingerprint' => $fingerprint,
        'resumo' => $resumo + ['planos' => count($planos), 'etapas' => count($resultadoEtapas)],
        'grupos' => $resultadoGrupos,
        'excecoes' => array_values($excecoes),
        'formula_carga' => 'carga_total_planejada_pessoa_dia = duracao_dias_uteis × pessoas_planejadas; a carga nominal só é atribuída a tarefas operacionais reais com um único responsável.',
    ];
}

function flow_alocacao_indexar_tarefas(array $alocacao): array
{
    $indice = [];
    foreach (($alocacao['grupos'] ?? []) as $grupo) {
        foreach (($grupo['projetos'] ?? []) as $projeto) {
            foreach (($projeto['tarefas_operacionais'] ?? []) as $unidade) {
                foreach (($unidade['tarefas'] ?? []) as $tarefa) {
                    $id = (int) ($tarefa['tarefa_id'] ?? 0);
                    if ($id <= 0) {
                        continue;
                    }
                    $indice[$id] = [
                        'tarefa' => $tarefa,
                        'unidade' => $unidade,
                        'projeto' => $projeto,
                        'grupo' => $grupo,
                    ];
                }
            }
        }
    }
    return $indice;
}

/**
 * Referência persistida da fila para uma etapa que pertence a uma entrega.
 * Mantê-la em um único lugar evita que a realocação e a fila usem chaves
 * diferentes para o mesmo bloco de trabalho.
 */
function flow_alocacao_referencia_fila(int $entregaId, int $obraId, string $codigoEtapa): ?string
{
    $codigoEtapa = strtoupper(trim($codigoEtapa));
    if ($codigoEtapa === '') {
        return null;
    }
    if ($entregaId > 0) {
        return 'ENTREGA:' . $entregaId . ':' . $codigoEtapa;
    }
    if ($obraId > 0) {
        return 'OBRA:' . $obraId . ':' . $codigoEtapa;
    }
    return null;
}

/**
 * A confirmação de fila pertence ao responsável, não à tarefa. Quando a
 * Central transfere toda a frente aberta de uma entrega/etapa para outra
 * pessoa, a decisão já confirmada deve acompanhá-la. Sem isso a ordem fica
 * órfã na pessoa anterior e o Kanban volta a desempatar por prazo.
 *
 * Em realocações parciais, a prioridade é copiada para a nova responsável e
 * permanece com a origem. Quando a frente inteira sai da origem, a cópia é
 * criada e a decisão antiga é desativada. Assim, cada pessoa preserva a
 * ordem que já foi decidida para o trabalho que efetivamente recebeu.
 */
function flow_alocacao_migrar_fila_confirmada(
    mysqli $conn,
    array $movimentos,
    array $indiceTarefas
): array {
    $tabela = $conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'entrega_planejamento_fila_operacional' LIMIT 1");
    if (!$tabela || $tabela->num_rows === 0) {
        return [];
    }
    $tabela->free();

    $candidatos = [];
    foreach ($movimentos as $movimento) {
        $tarefaId = (int) ($movimento['tarefa_id'] ?? 0);
        $indice = $indiceTarefas[$tarefaId] ?? null;
        $projeto = $indice['projeto'] ?? [];
        $origem = (int) ($movimento['de_colaborador_id'] ?? 0);
        $destino = (int) ($movimento['para_colaborador_id'] ?? 0);
        $entregaId = (int) ($projeto['entrega_id'] ?? 0);
        $obraId = (int) ($projeto['obra_id'] ?? 0);
        $etapa = strtoupper(trim((string) ($projeto['codigo_etapa'] ?? '')));
        $referencia = flow_alocacao_referencia_fila($entregaId, $obraId, $etapa);
        if ($origem <= 0 || $destino <= 0 || $origem === $destino || !$referencia) {
            continue;
        }
        $chave = implode(':', [$origem, $destino, $entregaId, $obraId, $etapa]);
        $candidatos[$chave] = compact('origem', 'destino', 'entregaId', 'obraId', 'etapa', 'referencia');
    }

    if (!$candidatos) {
        return [];
    }

    $buscarTarefasOrigem = $conn->prepare(
        'SELECT fi.funcao_id, ico.tipo_imagem
           FROM entregas_itens ei
           JOIN funcao_imagem fi ON fi.imagem_id = ei.imagem_id
           JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
          WHERE ei.entrega_id = ?
            AND fi.colaborador_id = ?
            AND fi.status NOT IN (\'Finalizado\', \'Aprovado\', \'Aprovado com ajustes\', \'Cancelado\')'
    );
    $copiar = $conn->prepare(
        'INSERT INTO entrega_planejamento_fila_operacional
            (planejamento_id, versao_id, entrega_id, obra_id, codigo_etapa, referencia_fila, colaborador_id, posicao, fingerprint, motivo, confirmado_por_colaborador_id)
         SELECT origem.planejamento_id, origem.versao_id, origem.entrega_id, origem.obra_id, origem.codigo_etapa, origem.referencia_fila, ?, origem.posicao, origem.fingerprint, origem.motivo, origem.confirmado_por_colaborador_id
           FROM entrega_planejamento_fila_operacional origem
          WHERE origem.colaborador_id = ?
            AND origem.codigo_etapa = ?
            AND origem.referencia_fila = ?
            AND origem.ativo = 1
            AND NOT EXISTS (
                SELECT 1
                  FROM entrega_planejamento_fila_operacional destino
                 WHERE destino.colaborador_id = ?
                   AND destino.codigo_etapa = ?
                   AND destino.referencia_fila = ?
                   AND destino.ativo = 1
            )'
    );
    $desativarOrigem = $conn->prepare(
        'UPDATE entrega_planejamento_fila_operacional
            SET ativo = 0
          WHERE colaborador_id = ?
            AND codigo_etapa = ?
            AND referencia_fila = ?
            AND ativo = 1'
    );
    if (!$buscarTarefasOrigem || !$copiar || !$desativarOrigem) {
        throw new RuntimeException('Não foi possível preparar a atualização da fila após a realocação.');
    }

    $migradas = [];
    foreach ($candidatos as $candidato) {
        // Filas legadas sem entrega continuam identificadas pela obra. Para as
        // filas atuais, a referência de entrega é a chave canônica.
        if ($candidato['entregaId'] <= 0) {
            continue;
        }
        $buscarTarefasOrigem->bind_param('ii', $candidato['entregaId'], $candidato['origem']);
        $buscarTarefasOrigem->execute();
        $aindaNaOrigem = false;
        $resultado = $buscarTarefasOrigem->get_result();
        while ($tarefa = $resultado->fetch_assoc()) {
            if (flow_planejamento_codigo_etapa($tarefa) === $candidato['etapa']) {
                $aindaNaOrigem = true;
                break;
            }
        }
        $resultado->free();
        $copiar->bind_param(
            'iississ',
            $candidato['destino'],
            $candidato['origem'],
            $candidato['etapa'],
            $candidato['referencia'],
            $candidato['destino'],
            $candidato['etapa'],
            $candidato['referencia']
        );
        if (!$copiar->execute()) {
            throw new RuntimeException('Não foi possível copiar a prioridade confirmada: ' . $copiar->error);
        }
        $linhasCopiadas = $copiar->affected_rows;
        if (!$aindaNaOrigem) {
            $desativarOrigem->bind_param('iss', $candidato['origem'], $candidato['etapa'], $candidato['referencia']);
            if (!$desativarOrigem->execute()) {
                throw new RuntimeException('Não foi possível encerrar a prioridade anterior: ' . $desativarOrigem->error);
            }
        }
        if ($linhasCopiadas > 0 || !$aindaNaOrigem) {
            $migradas[] = $candidato + [
                'linhas' => $linhasCopiadas,
                'tipo' => $aindaNaOrigem ? 'COPIADA' : 'MIGRADA',
            ];
        }
    }
    $buscarTarefasOrigem->close();
    $copiar->close();
    $desativarOrigem->close();

    return $migradas;
}

function flow_alocacao_indexar_pessoas(array $alocacao): array
{
    $indice = [];
    foreach (($alocacao['grupos'] ?? []) as $grupo) {
        foreach (($grupo['projetos'] ?? []) as $projeto) {
            foreach (($projeto['pessoas'] ?? []) as $pessoa) {
                $id = (int) ($pessoa['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $indice[$id][] = [
                    'pessoa' => $pessoa,
                    'projeto' => $projeto,
                    'grupo' => $grupo,
                ];
            }
        }
    }
    return $indice;
}

function flow_alocacao_normalizar_movimentos(array $movimentos): array
{
    $resultado = [];
    $vistos = [];
    foreach ($movimentos as $movimento) {
        if (!is_array($movimento)) {
            throw new InvalidArgumentException('Cada movimentação deve ser um objeto.');
        }
        $tarefaId = (int) ($movimento['tarefa_id'] ?? $movimento['idfuncao_imagem'] ?? 0);
        $para = (int) ($movimento['para_colaborador_id'] ?? $movimento['colaborador_id'] ?? 0);
        if ($tarefaId <= 0 || $para <= 0) {
            throw new InvalidArgumentException('Toda movimentação precisa informar tarefa e colaborador destino.');
        }
        if (isset($vistos[$tarefaId])) {
            throw new InvalidArgumentException('A mesma tarefa não pode aparecer duas vezes na simulação.');
        }
        $vistos[$tarefaId] = true;
        $resultado[] = [
            'tarefa_id' => $tarefaId,
            'para_colaborador_id' => $para,
            'de_colaborador_id' => (int) ($movimento['de_colaborador_id'] ?? 0),
        ];
    }
    if (!$resultado) {
        throw new InvalidArgumentException('Selecione pelo menos uma tarefa para simular.');
    }
    return $resultado;
}

function flow_alocacao_carga_pessoas_resumo(array $alocacao, array $ids): array
{
    $ids = array_fill_keys(array_map('intval', $ids), true);
    $resumo = [];
    foreach (($alocacao['grupos'] ?? []) as $grupo) {
        foreach (($grupo['projetos'] ?? []) as $projeto) {
            foreach (($projeto['pessoas'] ?? []) as $pessoa) {
                $id = (int) ($pessoa['id'] ?? 0);
                if (!isset($ids[$id])) {
                    continue;
                }
                $resumo[$id] = [
                    'id' => $id,
                    'nome' => (string) ($pessoa['nome'] ?? ''),
                    'carga_percentual' => (float) ($pessoa['carga_periodo_percentual'] ?? 0),
                    'carga_periodo' => (float) ($pessoa['carga_periodo'] ?? 0),
                    'status_carga' => (string) ($pessoa['status_carga'] ?? FLOW_ALOCACAO_STATUS_NORMAL),
                    'conflitos' => $pessoa['conflitos'] ?? [],
                ];
            }
        }
    }
    foreach (array_keys($ids) as $id) {
        if (!isset($resumo[$id])) {
            $resumo[$id] = ['id' => (int) $id, 'nome' => '', 'carga_percentual' => 0.0, 'carga_periodo' => 0.0, 'status_carga' => FLOW_ALOCACAO_STATUS_NORMAL, 'conflitos' => []];
        }
    }
    return $resumo;
}

function flow_alocacao_simular_movimentos(mysqli $conn, string $inicio, string $fim, array $movimentos, array $opcoes = []): array
{
    $movimentos = flow_alocacao_normalizar_movimentos($movimentos);
    $atual = flow_alocacao_consultar($conn, $inicio, $fim, $opcoes);
    $tarefas = flow_alocacao_indexar_tarefas($atual);
    $idsPessoas = [];
    $override = [];
    $mudancas = [];
    foreach ($movimentos as $movimento) {
        $id = (int) $movimento['tarefa_id'];
        if (!isset($tarefas[$id])) {
            throw new InvalidArgumentException('A tarefa ' . $id . ' não pertence a um plano vigente no período consultado.');
        }
        $item = $tarefas[$id];
        $tarefa = $item['tarefa'];
        $projeto = $item['projeto'];
        $codigo = (string) ($projeto['codigo_etapa'] ?? '');
        $de = (int) ($tarefa['colaborador_id'] ?? 0);
        $deInformado = (int) $movimento['de_colaborador_id'];
        if ($deInformado > 0 && $deInformado !== $de) {
            throw new RuntimeException('A tarefa ' . $id . ' foi alterada por outra pessoa. Recarregue a Central.');
        }
        $para = (int) $movimento['para_colaborador_id'];
        $elegivel = false;
        foreach (($projeto['candidatos'] ?? []) as $candidato) {
            if ((int) ($candidato['colaborador_id'] ?? 0) === $para && !empty($candidato['elegivel'])) {
                $elegivel = true;
                $idsPessoas[$para] = true;
                break;
            }
        }
        if (!$elegivel) {
            throw new InvalidArgumentException('O colaborador escolhido não é elegível para ' . $codigo . '.');
        }
        if ($de > 0) {
            $idsPessoas[$de] = true;
        }
        $override[$id] = $para;
        $mudancas[] = [
            'tarefa_id' => $id,
            'imagem_id' => (int) ($tarefa['imagem_id'] ?? 0),
            'imagem_nome' => (string) ($tarefa['imagem_nome'] ?? ''),
            'obra' => (string) ($projeto['obra'] ?? ''),
            'entrega_id' => (int) ($projeto['entrega_id'] ?? 0),
            'codigo_etapa' => $codigo,
            'etapa' => (string) ($projeto['etapa'] ?? $codigo),
            'de_colaborador_id' => $de,
            'de_nome' => (string) ($tarefa['responsavel_nome'] ?? ''),
            'para_colaborador_id' => $para,
            'para_nome' => (string) (($projeto['candidatos'][array_search($para, array_column($projeto['candidatos'], 'colaborador_id'))]['nome'] ?? '')),
        ];
    }
    $simulado = flow_alocacao_consultar($conn, $inicio, $fim, array_merge($opcoes, ['responsaveis_override' => $override]));
    $idsPessoas = array_keys($idsPessoas);
    $antes = flow_alocacao_carga_pessoas_resumo($atual, $idsPessoas);
    $depois = flow_alocacao_carga_pessoas_resumo($simulado, $idsPessoas);
    $impactos = [];
    foreach ($idsPessoas as $id) {
        $impactos[] = [
            'colaborador_id' => (int) $id,
            'nome' => $depois[$id]['nome'] ?: $antes[$id]['nome'],
            'antes' => $antes[$id],
            'depois' => $depois[$id],
            'delta_percentual' => round($depois[$id]['carga_percentual'] - $antes[$id]['carga_percentual'], 1),
        ];
    }
    $sobrecargasDepois = [];
    $requerValidacao = false;
    foreach ($depois as $pessoa) {
        if ((float) $pessoa['carga_periodo'] > 1.0001) {
            $sobrecargasDepois[] = $pessoa;
            $requerValidacao = $requerValidacao || ($pessoa['status_carga'] ?? '') !== FLOW_ALOCACAO_STATUS_SOBRECARGA_VALIDADA;
        }
    }
    return [
        'periodo' => ['inicio' => $inicio, 'fim' => $fim],
        'fingerprint_atual' => (string) ($atual['fingerprint'] ?? ''),
        'fingerprint_simulado' => (string) ($simulado['fingerprint'] ?? ''),
        'movimentos' => $mudancas,
        'atual' => $atual,
        'simulado' => $simulado,
        'impactos' => $impactos,
        'sobrecargas_depois' => $sobrecargasDepois,
        'requer_validacao' => $requerValidacao,
        'resultado' => [
            'sobrecargas_antes' => (int) ($atual['resumo']['sobrecargas'] ?? 0),
            'sobrecargas_depois' => (int) ($simulado['resumo']['sobrecargas'] ?? 0),
            'aguardando_validacao_depois' => (int) ($simulado['resumo']['aguardando_validacao'] ?? 0),
            'conflitos_antes' => (int) ($atual['resumo']['conflitos'] ?? 0),
            'conflitos_depois' => (int) ($simulado['resumo']['conflitos'] ?? 0),
        ],
    ];
}

/**
 * Procura uma redistribuição determinística para uma etapa, sem persistir.
 * O algoritmo só aceita uma troca quando ela reduz lexicograficamente:
 * quantidade de sobrecargas, excesso total, pico e uso de secundários.
 */
function flow_alocacao_sugerir_distribuicao(mysqli $conn, string $inicio, string $fim, int $entregaId, string $codigoEtapa): array
{
    if ($entregaId <= 0 || $codigoEtapa === '') {
        throw new InvalidArgumentException('Informe entrega e etapa para buscar uma distribuição.');
    }
    $atual = flow_alocacao_consultar($conn, $inicio, $fim);
    $projeto = null;
    foreach (($atual['grupos'] ?? []) as $grupo) {
        foreach (($grupo['projetos'] ?? []) as $item) {
            if ((int) ($item['entrega_id'] ?? 0) === $entregaId && (string) ($item['codigo_etapa'] ?? '') === $codigoEtapa) {
                $projeto = $item;
                break 2;
            }
        }
    }
    if (!$projeto) {
        throw new InvalidArgumentException('A etapa não está presente no período atual.');
    }
    if ((int) ($projeto['pendente_materializacao'] ?? 0) > 0 && empty($projeto['tarefas_operacionais'])) {
        return ['movimentos' => [], 'motivo' => 'PENDENTE_MATERIALIZACAO', 'mensagem' => 'Não há tarefas operacionais materializadas para distribuir.'];
    }
    $candidatos = [];
    foreach (($projeto['candidatos'] ?? []) as $candidato) {
        $id = (int) ($candidato['colaborador_id'] ?? 0);
        if ($id > 0) {
            $candidatos[$id] = $candidato;
        }
    }
    foreach (($projeto['pessoas'] ?? []) as $pessoa) {
        $id = (int) ($pessoa['id'] ?? 0);
        if ($id > 0 && !isset($candidatos[$id]) && !empty($pessoa['elegivel'])) {
            $candidatos[$id] = ['colaborador_id' => $id, 'tipo_atuacao' => $pessoa['tipo_atuacao'] ?? null];
        }
    }
    if (!$candidatos) {
        return ['movimentos' => [], 'motivo' => 'SEM_ELEGIVEIS', 'mensagem' => 'Não há colaboradores elegíveis para testar.'];
    }
    $unidades = array_values(array_filter($projeto['tarefas_operacionais'] ?? [], static fn (array $unidade): bool => !empty($unidade['tarefas'])));
    $score = static function (array $alocacao) use ($candidatos): array {
        $sobrecargas = 0;
        $excesso = 0.0;
        $pico = 0.0;
        $secundarios = 0;
        foreach (($alocacao['grupos'] ?? []) as $grupo) {
            foreach (($grupo['projetos'] ?? []) as $item) {
                foreach (($item['pessoas'] ?? []) as $pessoa) {
                    $carga = (float) ($pessoa['carga_periodo'] ?? 0);
                    $pico = max($pico, $carga);
                    if ($carga > 1.0001) {
                        $sobrecargas++;
                        $excesso += $carga - 1.0;
                    }
                    if (($candidatos[(int) ($pessoa['id'] ?? 0)]['tipo_atuacao'] ?? null) === FLOW_TIPO_ATUACAO_SECUNDARIA) {
                        $secundarios++;
                    }
                }
            }
        }
        return [$sobrecargas, round($excesso, 6), round($pico, 6), $secundarios];
    };
    $melhorScore = $score($atual);
    $override = [];
    $movimentos = [];
    foreach ($unidades as $unidade) {
        $idsTarefas = array_values(array_filter(array_map(static fn (array $tarefa): int => (int) ($tarefa['tarefa_id'] ?? 0), $unidade['tarefas'] ?? [])));
        if (!$idsTarefas) {
            continue;
        }
        $responsaveis = array_values(array_unique(array_filter(array_map(static fn (array $tarefa): int => (int) ($tarefa['colaborador_id'] ?? 0), $unidade['tarefas'] ?? []))));
        $origem = $responsaveis[0] ?? 0;
        $melhorCandidato = null;
        $melhorCandidatoScore = null;
        foreach ($candidatos as $candidatoId => $candidato) {
            if ($candidatoId === $origem || in_array($candidatoId, $responsaveis, true)) {
                continue;
            }
            $trialOverride = $override;
            foreach ($idsTarefas as $tarefaId) {
                $trialOverride[$tarefaId] = $candidatoId;
            }
            $trial = flow_alocacao_consultar($conn, $inicio, $fim, ['responsaveis_override' => $trialOverride]);
            $trialScore = $score($trial);
            if ($melhorCandidatoScore === null || $trialScore < $melhorCandidatoScore) {
                $melhorCandidato = $candidatoId;
                $melhorCandidatoScore = $trialScore;
            }
        }
        if ($melhorCandidato === null || $melhorCandidatoScore === null || !($melhorCandidatoScore < $melhorScore)) {
            continue;
        }
        $override = array_merge($override, array_fill_keys($idsTarefas, $melhorCandidato));
        foreach ($idsTarefas as $tarefaId) {
            $tarefa = null;
            foreach (($unidade['tarefas'] ?? []) as $item) {
                if ((int) ($item['tarefa_id'] ?? 0) === $tarefaId) {
                    $tarefa = $item;
                    break;
                }
            }
            $movimentos[] = [
                'tarefa_id' => $tarefaId,
                'de_colaborador_id' => (int) ($tarefa['colaborador_id'] ?? 0),
                'para_colaborador_id' => $melhorCandidato,
            ];
        }
        $melhorScore = $melhorCandidatoScore;
    }
    if (!$movimentos) {
        return ['movimentos' => [], 'motivo' => 'NENHUMA_MELHORIA', 'mensagem' => 'Nenhuma distribuição elegível reduz a sobrecarga sem piorar o contexto global.', 'score_atual' => $melhorScore];
    }
    return flow_alocacao_simular_movimentos($conn, $inicio, $fim, $movimentos);
}

function flow_alocacao_aplicar_movimentos(mysqli $conn, string $inicio, string $fim, array $movimentos, string $fingerprintEsperado, ?int $atorId, string $observacao = ''): array
{
    if ($fingerprintEsperado === '') {
        throw new InvalidArgumentException('A simulação precisa informar o fingerprint atual da Central.');
    }
    $movimentos = flow_alocacao_normalizar_movimentos($movimentos);
    $observacao = trim($observacao);
    if (mb_strlen($observacao) > 500) {
        throw new InvalidArgumentException('A observação aceita até 500 caracteres.');
    }

    $ids = array_values(array_unique(array_map(static fn (array $movimento): int => (int) $movimento['tarefa_id'], $movimentos)));
    $conn->begin_transaction();
    try {
        $lista = implode(',', array_map('intval', $ids));
        $resultado = $conn->query("SELECT idfuncao_imagem, colaborador_id, status FROM funcao_imagem WHERE idfuncao_imagem IN ({$lista}) FOR UPDATE");
        if (!$resultado || $resultado->num_rows !== count($ids)) {
            throw new RuntimeException('Uma ou mais tarefas não existem mais. Recarregue a Central.');
        }
        $atuais = [];
        while ($linha = $resultado->fetch_assoc()) {
            $atuais[(int) $linha['idfuncao_imagem']] = $linha;
        }
        $resultado->free();
        foreach ($movimentos as $movimento) {
            $id = (int) $movimento['tarefa_id'];
            $atual = $atuais[$id];
            if (flow_planejamento_normalizar((string) ($atual['status'] ?? '')) === 'cancelado') {
                throw new InvalidArgumentException('A tarefa ' . $id . ' está cancelada e não pode ser alocada.');
            }
            $de = (int) ($atual['colaborador_id'] ?? 0);
            $deEsperado = (int) ($movimento['de_colaborador_id'] ?? 0);
            if ($deEsperado > 0 && $de !== $deEsperado) {
                throw new RuntimeException('A tarefa ' . $id . ' mudou de responsável. Recalcule a simulação.');
            }
            if ($de === (int) $movimento['para_colaborador_id']) {
                throw new InvalidArgumentException('A tarefa ' . $id . ' já está com o colaborador escolhido.');
            }
        }

        $atual = flow_alocacao_consultar($conn, $inicio, $fim);
        if (!hash_equals($fingerprintEsperado, (string) ($atual['fingerprint'] ?? ''))) {
            throw new RuntimeException('O contexto da alocação mudou. Recarregue a Central e simule novamente.');
        }
        $simulacao = flow_alocacao_simular_movimentos($conn, $inicio, $fim, $movimentos);
        foreach (($simulacao['sobrecargas_depois'] ?? []) as $sobrecarga) {
            if (($sobrecarga['status_carga'] ?? '') !== FLOW_ALOCACAO_STATUS_SOBRECARGA_VALIDADA) {
                throw new DomainException('A distribuição mantém sobrecarga sem validação excepcional vigente.');
            }
        }

        $update = $conn->prepare('UPDATE funcao_imagem SET colaborador_id = ? WHERE idfuncao_imagem = ? AND colaborador_id = ?');
        if (!$update) {
            throw new RuntimeException('Não foi possível preparar a atualização das tarefas: ' . $conn->error);
        }
        $mudancasPorPlano = [];
        $indiceTarefas = flow_alocacao_indexar_tarefas($atual);
        foreach ($movimentos as $movimento) {
            $id = (int) $movimento['tarefa_id'];
            $de = (int) ($atuais[$id]['colaborador_id'] ?? 0);
            $para = (int) $movimento['para_colaborador_id'];
            $update->bind_param('iii', $para, $id, $de);
            $update->execute();
            if ($update->affected_rows !== 1) {
                throw new RuntimeException('A tarefa ' . $id . ' mudou durante a aplicação. Recalcule a simulação.');
            }
            $projeto = $indiceTarefas[$id]['projeto'] ?? [];
            $chavePlano = (int) ($projeto['planejamento_id'] ?? 0) . ':' . (int) ($projeto['entrega_id'] ?? 0);
            $mudancasPorPlano[$chavePlano]['planejamento_id'] = (int) ($projeto['planejamento_id'] ?? 0);
            $mudancasPorPlano[$chavePlano]['entrega_id'] = (int) ($projeto['entrega_id'] ?? 0);
            $mudancasPorPlano[$chavePlano]['versao_id'] = (int) ($projeto['versao_id'] ?? 0);
            $mudancasPorPlano[$chavePlano]['mudancas'][] = [
                'tarefa_id' => $id,
                'imagem_id' => (int) ($indiceTarefas[$id]['tarefa']['imagem_id'] ?? 0),
                'de_colaborador_id' => $de,
                'para_colaborador_id' => $para,
                'codigo_etapa' => (string) ($projeto['codigo_etapa'] ?? ''),
                'obra' => (string) ($projeto['obra'] ?? ''),
            ];
        }
        $update->close();

        // A prioridade confirmada acompanha uma frente que foi integralmente
        // transferida. Isso mantém a ordem do Kanban alinhada à Central de
        // Alocação também depois de trocar o responsável.
        $filasMigradas = flow_alocacao_migrar_fila_confirmada($conn, $movimentos, $indiceTarefas);

        foreach ($mudancasPorPlano as $plano) {
            flow_planejamento_registrar_evento(
                $conn,
                $plano['planejamento_id'],
                $plano['entrega_id'],
                'ALOCACAO_CENTRAL_CONFIRMADA',
                $plano['versao_id'],
                $atorId,
                'REALOCACAO_EM_MASSA',
                $observacao !== '' ? $observacao : null,
                [
                    'origem' => 'CENTRAL_ALOCACAO',
                    'fingerprint_antes' => $fingerprintEsperado,
                    'fingerprint_depois' => $simulacao['fingerprint_simulado'],
                    'movimentos' => $plano['mudancas'],
                    'resultado' => $simulacao['resultado'],
                ]
            );
        }
        $conn->commit();
        return $simulacao + ['aplicado' => true, 'filas_migradas' => $filasMigradas];
    } catch (Throwable $erro) {
        $conn->rollback();
        throw $erro;
    }
}

function flow_alocacao_validar_capacidade_excepcional(mysqli $conn, string $inicio, string $fim, array $dados, ?int $atorId): array
{
    if (!flow_alocacao_validacoes_disponiveis($conn)) {
        throw new RuntimeException('A migration de validação excepcional ainda não foi aplicada.');
    }
    if (!$atorId || empty($dados['confirmado'])) {
        throw new InvalidArgumentException('Confirme explicitamente a capacidade excepcional.');
    }
    $observacao = trim((string) ($dados['observacao'] ?? ''));
    if (mb_strlen($observacao) < 5 || mb_strlen($observacao) > 500) {
        throw new InvalidArgumentException('Informe uma observação entre 5 e 500 caracteres.');
    }
    $entregaId = (int) ($dados['entrega_id'] ?? 0);
    $codigo = strtoupper(trim((string) ($dados['codigo_etapa'] ?? '')));
    $colaboradorId = (int) ($dados['colaborador_id'] ?? 0);
    $fingerprint = trim((string) ($dados['fingerprint_carga'] ?? ''));
    if ($entregaId <= 0 || $codigo === '' || $colaboradorId <= 0 || $fingerprint === '') {
        throw new InvalidArgumentException('O contexto da validação está incompleto. Reabra a Central.');
    }
    $alocacao = flow_alocacao_consultar($conn, $inicio, $fim);
    foreach (($alocacao['grupos'] ?? []) as $grupo) {
        foreach (($grupo['projetos'] ?? []) as $projeto) {
            if ((int) $projeto['entrega_id'] !== $entregaId || (string) $projeto['codigo_etapa'] !== $codigo) {
                continue;
            }
            foreach (($projeto['pessoas'] ?? []) as $pessoa) {
                if ((int) ($pessoa['id'] ?? 0) !== $colaboradorId) {
                    continue;
                }
                if ((float) ($pessoa['carga_periodo'] ?? 0) <= 1.0001) {
                    throw new InvalidArgumentException('A pessoa não está acima de 100% neste contexto.');
                }
                if (!hash_equals($fingerprint, (string) ($pessoa['fingerprint_carga'] ?? ''))) {
                    throw new RuntimeException('A carga mudou desde a abertura da validação. Recalcule a Central.');
                }
                $stmt = $conn->prepare(
                    'INSERT INTO entrega_planejamento_capacidade_validacao
                     (planejamento_id, versao_id, entrega_id, obra_id, colaborador_id, codigo_etapa, data_inicio, data_fim, carga_percentual, pico_carga, fingerprint, status, observacao, validado_por_colaborador_id, validado_em)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'VALIDA\', ?, ?, NOW())'
                );
                if (!$stmt) {
                    throw new RuntimeException('Não foi possível preparar a validação: ' . $conn->error);
                }
                $percentual = (float) ($pessoa['carga_periodo_percentual'] ?? 0);
                $pico = (float) ($pessoa['carga_periodo'] ?? 0);
                $planejamentoId = (int) ($projeto['planejamento_id'] ?? 0);
                $versaoId = (int) ($projeto['versao_id'] ?? 0);
                $obraId = (int) ($projeto['obra_id'] ?? 0);
                $dataInicio = (string) ($projeto['inicio'] ?? '');
                $dataFim = (string) ($projeto['limite'] ?? '');
                $stmt->bind_param('iiiiisssddssi', $planejamentoId, $versaoId, $entregaId, $obraId, $colaboradorId, $codigo, $dataInicio, $dataFim, $percentual, $pico, $fingerprint, $observacao, $atorId);
                $stmt->execute();
                $id = (int) $stmt->insert_id;
                $stmt->close();
                flow_planejamento_registrar_evento($conn, (int) $projeto['planejamento_id'], $entregaId, 'CAPACIDADE_EXCEPCIONAL_VALIDADA', (int) $projeto['versao_id'], $atorId, 'SOBRECARGA_EXCEPCIONAL', $observacao, [
                    'origem' => 'CENTRAL_ALOCACAO',
                    'colaborador_id' => $colaboradorId,
                    'codigo_etapa' => $codigo,
                    'carga_percentual' => $percentual,
                    'fingerprint' => $fingerprint,
                ]);
                return ['id' => $id, 'status' => FLOW_ALOCACAO_STATUS_SOBRECARGA_VALIDADA, 'pessoa' => $pessoa, 'observacao' => $observacao];
            }
        }
    }
    throw new InvalidArgumentException('Colaborador ou etapa não encontrados no contexto atual.');
}
