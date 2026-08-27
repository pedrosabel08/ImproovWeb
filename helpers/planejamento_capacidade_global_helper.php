<?php

/**
 * Capacidade global de produção.
 *
 * Esta camada é deliberadamente somente leitora do planejamento individual:
 * consome a versão confirmada vigente e transforma suas etapas em demanda
 * planejada por dia útil. Ela não recalcula volume, produtividade ou datas.
 */

require_once __DIR__ . '/planejamento_producao_helper.php';
require_once __DIR__ . '/capacidade_colaborador_helper.php';

const FLOW_CAPACIDADE_ESTRATEGIA_JANELA_COMPLETA = 'FULL_WINDOW';
const FLOW_CAPACIDADE_ESTRATEGIA_NAO_CONSUME = 'NON_CAPACITY';

/**
 * Estratégias declarativas de consumo. A Modelagem da Fachada é uma janela
 * gerencial fixa no motor individual; como não há medida de esforço que prove
 * ocupação integral, ela permanece rastreável, mas não vira demanda nesta V1.1.
 */
function flow_capacidade_definicoes_etapas(): array
{
    return [
        'CADERNO_FILTRO' => [
            'nome' => 'Caderno + Filtro de Assets',
            'nome_painel' => 'Caderno / Filtro',
            'ordem_painel' => 10,
            'visivel_painel' => true,
            'estrategia' => FLOW_CAPACIDADE_ESTRATEGIA_JANELA_COMPLETA,
        ],
        'MODELAGEM_FACHADA' => [
            'nome' => 'Modelagem da Fachada',
            'ordem_painel' => 15,
            'estrategia' => FLOW_CAPACIDADE_ESTRATEGIA_NAO_CONSUME,
            'motivo_nao_consume' => 'JANELA_GERENCIAL_FIXA_SEM_EVIDENCIA_DE_OCUPACAO_INTEGRAL',
        ],
        'MODELAGEM_INTERNA' => [
            'nome' => 'Modelagem Interna',
            'nome_painel' => 'Modelagem',
            'ordem_painel' => 20,
            'visivel_painel' => true,
            'estrategia' => FLOW_CAPACIDADE_ESTRATEGIA_JANELA_COMPLETA,
        ],
        'COMPOSICAO' => [
            'nome' => 'Composição',
            'nome_painel' => 'Composição',
            'ordem_painel' => 30,
            'visivel_painel' => true,
            'estrategia' => FLOW_CAPACIDADE_ESTRATEGIA_JANELA_COMPLETA,
        ],
        'FINALIZACAO_EXTERNA' => [
            'nome' => 'Finalização Externa',
            'nome_painel' => 'Finalização Externa',
            'ordem_painel' => 40,
            'visivel_painel' => true,
            'estrategia' => FLOW_CAPACIDADE_ESTRATEGIA_JANELA_COMPLETA,
        ],
        'FINALIZACAO_INTERNA' => [
            'nome' => 'Finalização Interna',
            'nome_painel' => 'Finalização Interna',
            'ordem_painel' => 50,
            'visivel_painel' => true,
            'estrategia' => FLOW_CAPACIDADE_ESTRATEGIA_JANELA_COMPLETA,
        ],
        'FINALIZACAO_PLANTA' => [
            'nome' => 'Finalização Planta',
            'nome_painel' => 'Finalização Planta',
            'ordem_painel' => 60,
            'visivel_painel' => true,
            'estrategia' => FLOW_CAPACIDADE_ESTRATEGIA_JANELA_COMPLETA,
        ],
        'FINALIZACAO_GLOBAL' => [
            'nome' => 'Finalização (marco global)',
            'estrategia' => FLOW_CAPACIDADE_ESTRATEGIA_NAO_CONSUME,
            'motivo_nao_consume' => 'MARCO_VIRTUAL_AGREGADOR_DE_POOLS',
        ],
        'POS_PRODUCAO' => [
            'nome' => 'Pós-Produção',
            'nome_painel' => 'Pós-Produção',
            'ordem_painel' => 70,
            'visivel_painel' => true,
            'estrategia' => FLOW_CAPACIDADE_ESTRATEGIA_JANELA_COMPLETA,
        ],
    ];
}

function flow_capacidade_data_valida(?string $data): bool
{
    return entregas_valid_date($data);
}

/** Lista dias úteis usando exclusivamente o calendário canônico de Entregas. */
function flow_capacidade_dias_uteis_no_intervalo(string $inicio, string $fim): array
{
    if (!flow_capacidade_data_valida($inicio) || !flow_capacidade_data_valida($fim) || $fim < $inicio) {
        return [];
    }

    $cursor = date('Y-m-d', strtotime($inicio . ' -1 day'));
    $dias = [];
    while (true) {
        $proximo = flow_planejamento_adicionar_dias_uteis($cursor, 1);
        if ($proximo > $fim) {
            break;
        }
        if ($proximo >= $inicio) {
            $dias[] = $proximo;
        }
        $cursor = $proximo;
    }
    return $dias;
}

function flow_capacidade_inicio_semana(string $data): string
{
    $timestamp = strtotime($data . ' 12:00:00');
    $diaSemana = (int) date('N', $timestamp);
    return date('Y-m-d', strtotime('-' . ($diaSemana - 1) . ' days', $timestamp));
}

function flow_capacidade_numero(float $valor): float
{
    return round(max(0.0, $valor), 4);
}

function flow_capacidade_tabelas_disponiveis(mysqli $conn): bool
{
    static $disponivel = null;
    if ($disponivel !== null) {
        return $disponivel;
    }
    foreach (['planejamento_capacidade_etapa', 'planejamento_capacidade_etapa_periodo'] as $tabela) {
        $stmt = $conn->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
        );
        if (!$stmt) {
            return $disponivel = false;
        }
        $stmt->bind_param('s', $tabela);
        $stmt->execute();
        $existe = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if (!$existe) {
            return $disponivel = false;
        }
    }
    return $disponivel = true;
}

function flow_capacidade_normalizar_configuracoes(array $configuracoes): array
{
    $resultado = [];
    foreach ($configuracoes as $codigo => $configuracao) {
        if (is_numeric($configuracao)) {
            $configuracao = ['capacidade_padrao' => $configuracao, 'ativo' => true];
        }
        if (!is_array($configuracao)) {
            continue;
        }
        $resultado[(string) $codigo] = [
            'codigo_etapa' => (string) $codigo,
            // capacidade_padrao permanece como alias compatível da capacidade-base.
            'capacidade_padrao' => flow_capacidade_numero((float) ($configuracao['capacidade_principal'] ?? $configuracao['capacidade_padrao'] ?? $configuracao['capacidade'] ?? 0)),
            'capacidade_principal' => flow_capacidade_numero((float) ($configuracao['capacidade_principal'] ?? $configuracao['capacidade_padrao'] ?? $configuracao['capacidade'] ?? 0)),
            'capacidade_secundaria' => flow_capacidade_numero((float) ($configuracao['capacidade_secundaria'] ?? 0)),
            'ativo' => array_key_exists('ativo', $configuracao) ? !empty($configuracao['ativo']) : true,
            'origem' => (string) ($configuracao['origem'] ?? 'CONFIGURACAO'),
            'overrides' => array_values($configuracao['overrides'] ?? []),
            'colaboradores' => array_values($configuracao['colaboradores'] ?? []),
            'colaboradores_principais' => array_values($configuracao['colaboradores_principais'] ?? []),
            'colaboradores_secundarios' => array_values($configuracao['colaboradores_secundarios'] ?? []),
            'evidencia' => (string) ($configuracao['evidencia'] ?? ''),
            'pool_fisico' => (string) ($configuracao['pool_fisico'] ?? ''),
        ];
    }
    return $resultado;
}

/**
 * Em uma etapa que combina funções físicas (Caderno + Filtro), o mesmo
 * colaborador entra uma vez. Se for principal em pelo menos uma delas, sua
 * capacidade-base prevalece para o pool combinado.
 */
function flow_capacidade_configuracao_por_colaboradores(array $colaboradores, string $origem, string $evidencia, string $poolFisico): array
{
    $unicos = [];
    foreach ($colaboradores as $id => $colaborador) {
        $id = (int) ($colaborador['id'] ?? $id);
        if ($id <= 0) {
            continue;
        }
        if (!isset($unicos[$id]) || ($colaborador['tipo_atuacao'] ?? '') === FLOW_TIPO_ATUACAO_PRINCIPAL) {
            $unicos[$id] = $colaborador;
        }
    }

    $principais = [];
    $secundarios = [];
    foreach ($unicos as $id => $colaborador) {
        if (($colaborador['tipo_atuacao'] ?? '') === FLOW_TIPO_ATUACAO_PRINCIPAL) {
            $principais[$id] = $colaborador;
        } else {
            $secundarios[$id] = $colaborador;
        }
    }

    return [
        'capacidade_principal' => count($principais),
        'capacidade_secundaria' => count($secundarios),
        'ativo' => count($unicos) > 0,
        'origem' => $origem,
        'colaboradores' => array_values($unicos),
        'colaboradores_principais' => array_values($principais),
        'colaboradores_secundarios' => array_values($secundarios),
        'evidencia' => $evidencia,
        'pool_fisico' => $poolFisico,
        'overrides' => [],
    ];
}

/**
 * Retorna a capacidade operacional observável no cadastro atual do Flow.
 *
 * A fonte de verdade é o vínculo funcao_colaborador. A elegibilidade vem do
 * cadastro explícito do colaborador, e não de nomes ou IDs especiais. O mesmo
 * colaborador é deduplicado por ID em cada pool físico.
 */
function flow_capacidade_carregar_configuracoes_colaboradores(mysqli $conn): array
{
    $resultado = $conn->query(
        "SELECT fc.funcao_id, fc.colaborador_id, fc.tipo_atuacao,
                c.nome_colaborador, c.ativo, c.elegivel_capacidade
           FROM funcao_colaborador fc
           JOIN colaborador c ON c.idcolaborador = fc.colaborador_id
          WHERE " . flow_colaborador_elegivel_capacidade_sql('c') . "
            AND fc.funcao_id IN (1, 2, 3, 4, 5, 8)
          ORDER BY fc.funcao_id, c.nome_colaborador, fc.colaborador_id"
    );
    if (!$resultado) {
        throw new RuntimeException($conn->error);
    }

    $porFuncao = [];
    $porNomeFinalizacao = [];
    while ($linha = $resultado->fetch_assoc()) {
        $id = (int) $linha['colaborador_id'];
        $nome = trim((string) $linha['nome_colaborador']);
        if ($id <= 0 || $nome === '' || !flow_colaborador_elegivel_capacidade($linha)) {
            continue;
        }
        $funcaoId = (int) $linha['funcao_id'];
        $registro = [
            'id' => $id,
            'nome' => $nome,
            'tipo_atuacao' => flow_capacidade_normalizar_tipo_atuacao($linha['tipo_atuacao'] ?? null),
            'capacidade_secundaria_potencial' => true,
        ];
        $porFuncao[$funcaoId][$id] = $registro;
        if ($funcaoId === 4) {
            $porNomeFinalizacao[flow_planejamento_normalizar($nome)][$id] = $registro;
        }
    }
    $resultado->free();

    $configuracoes = [];
    $mapaFuncoes = [
        'CADERNO_FILTRO' => [1, 8],
        // Embora a demanda da Fachada não seja inferida no heatmap global,
        // ela continua sendo uma frente real de Modelagem e precisa expor os
        // mesmos colaboradores elegíveis na Central de Alocação.
        'MODELAGEM_FACHADA' => [2],
        'MODELAGEM_INTERNA' => [2],
        'COMPOSICAO' => [3],
        'POS_PRODUCAO' => [5],
    ];
    foreach ($mapaFuncoes as $codigo => $funcoes) {
        $colaboradores = [];
        foreach ($funcoes as $funcaoId) {
            foreach (($porFuncao[$funcaoId] ?? []) as $id => $colaborador) {
                $colaboradores[$id] = $colaborador;
            }
        }
        $configuracoes[$codigo] = flow_capacidade_configuracao_por_colaboradores(
            $colaboradores,
            'FUNCAO_COLABORADOR_ELEGIVEL',
            'funcao_colaborador + colaborador ativo e elegível; IDs distintos',
            $codigo === 'CADERNO_FILTRO' ? 'CADERNO_FILTRO' : str_replace('_INTERNA', '', $codigo)
        );
    }

    // Os pools de finalização são uma regra operacional explícita do Flow.
    // Não usamos nivel_finalizacao para inferi-los silenciosamente: o cadastro
    // atual possui níveis históricos que não correspondem aos pools por tipo.
    $poolsFinalizacao = [
        'FINALIZACAO_EXTERNA' => ['marcio', 'heverton'],
        'FINALIZACAO_INTERNA' => ['bruna', 'jose robson', 'jose'],
        'FINALIZACAO_PLANTA' => ['jiulia'],
    ];
    foreach ($poolsFinalizacao as $codigo => $nomes) {
        $colaboradores = [];
        foreach ($nomes as $nomeNormalizado) {
            foreach (($porNomeFinalizacao[$nomeNormalizado] ?? []) as $id => $colaborador) {
                $colaboradores[$id] = $colaborador;
            }
        }
        $configuracoes[$codigo] = flow_capacidade_configuracao_por_colaboradores(
            $colaboradores,
            'FUNCAO_COLABORADOR_ELEGIVEL_POOL_FINALIZACAO',
            'pool operacional explícito; vínculo funcao_id=4 e colaborador elegível',
            $codigo
        );
    }

    return flow_capacidade_normalizar_configuracoes($configuracoes);
}

function flow_capacidade_carregar_configuracoes(mysqli $conn): array
{
    if (!flow_capacidade_tabelas_disponiveis($conn)) {
        return [];
    }
    $configuracoes = [];
    $resultado = $conn->query(
        'SELECT codigo_etapa, capacidade_padrao, ativo
           FROM planejamento_capacidade_etapa'
    );
    if (!$resultado) {
        throw new RuntimeException($conn->error);
    }
    while ($linha = $resultado->fetch_assoc()) {
        $configuracoes[(string) $linha['codigo_etapa']] = [
            'capacidade_padrao' => (float) $linha['capacidade_padrao'],
            'ativo' => !empty($linha['ativo']),
            'origem' => 'CONFIGURACAO_PERSISTIDA',
            'overrides' => [],
        ];
    }
    $resultado->free();

    $resultado = $conn->query(
        'SELECT id, codigo_etapa, data_inicio, data_fim, capacidade_disponivel, ativo
           FROM planejamento_capacidade_etapa_periodo
          WHERE ativo = 1
          ORDER BY codigo_etapa, data_inicio, id'
    );
    if (!$resultado) {
        throw new RuntimeException($conn->error);
    }
    while ($linha = $resultado->fetch_assoc()) {
        $codigo = (string) $linha['codigo_etapa'];
        if (!isset($configuracoes[$codigo])) {
            $configuracoes[$codigo] = [
                'capacidade_padrao' => 0.0,
                'ativo' => false,
                'origem' => 'CONFIGURACAO_PERSISTIDA',
                'overrides' => [],
            ];
        }
        $configuracoes[$codigo]['overrides'][] = [
            'id' => (int) $linha['id'],
            'data_inicio' => (string) $linha['data_inicio'],
            'data_fim' => (string) $linha['data_fim'],
            'capacidade_disponivel' => (float) $linha['capacidade_disponivel'],
        ];
    }
    $resultado->free();
    return flow_capacidade_normalizar_configuracoes($configuracoes);
}

function flow_capacidade_disponivel_em(string $codigo, string $data, array $configuracoes): array
{
    $configuracao = $configuracoes[$codigo] ?? null;
    if (!$configuracao || empty($configuracao['ativo'])) {
        return [
            'configurada' => false,
            'capacidade' => null,
            'capacidade_principal' => null,
            'capacidade_secundaria' => null,
            'capacidade_total' => null,
            'origem' => null,
        ];
    }
    $principal = (float) ($configuracao['capacidade_principal'] ?? $configuracao['capacidade_padrao']);
    $secundaria = (float) ($configuracao['capacidade_secundaria'] ?? 0);
    $origem = (string) ($configuracao['origem'] ?? 'CONFIGURACAO');
    foreach (($configuracao['overrides'] ?? []) as $override) {
        if ($data >= (string) $override['data_inicio'] && $data <= (string) $override['data_fim']) {
            // A estrutura anterior de override só possuía uma capacidade. Para
            // compatibilidade, ela ajusta a capacidade-base do período e não
            // transforma apoio potencial em disponibilidade garantida.
            $principal = (float) $override['capacidade_disponivel'];
            $origem = 'OVERRIDE_PERIODO';
        }
    }
    $principal = flow_capacidade_numero($principal);
    $secundaria = flow_capacidade_numero($secundaria);
    return [
        'configurada' => true,
        // Alias legado: capacidade normal é a capacidade principal.
        'capacidade' => $principal,
        'capacidade_principal' => $principal,
        'capacidade_secundaria' => $secundaria,
        'capacidade_total' => flow_capacidade_numero($principal + $secundaria),
        'origem' => $origem,
    ];
}

/** Regra canônica para a versão que representa demanda contratada. */
function flow_capacidade_plano_elegivel(array $plano): bool
{
    if (!in_array((string) ($plano['estado'] ?? ''), ['CONFIRMADO', 'DESATUALIZADO', 'REPLANEJAMENTO'], true)) {
        return false;
    }
    if (empty($plano['versao_atual_id']) || empty($plano['versao_vigente'])) {
        return false;
    }
    if ((int) ($plano['status_id'] ?? 0) !== 2 || !empty($plano['arquivada'])) {
        return false;
    }
    if ((int) ($plano['status_obra'] ?? -1) !== 0) {
        return false;
    }
    return !in_array((string) ($plano['entrega_status'] ?? ''), ['Entregue no prazo', 'Entregue com atraso', 'Entrega antecipada'], true);
}

function flow_capacidade_carregar_planos_vigentes(mysqli $conn, string $inicio, string $fim, array $filtros = []): array
{
    $sql = "SELECT p.id AS planejamento_id, p.entrega_id, p.estado, p.versao_atual_id,
                   v.id AS versao_id, v.numero AS versao_numero, v.vigente AS versao_vigente,
                   v.margem_dias_uteis, v.status_plano, v.prazo_r00,
                   e.obra_id, e.status_id, e.status AS entrega_status, e.arquivada,
                   o.nome_obra, o.nomenclatura, o.cliente, o.status_obra
              FROM entrega_planejamento_producao p
              JOIN entrega_planejamento_versao v
                ON v.id = p.versao_atual_id AND v.vigente = 1
              JOIN entregas e ON e.id = p.entrega_id
              JOIN obra o ON o.idobra = e.obra_id
             WHERE p.estado IN ('CONFIRMADO', 'DESATUALIZADO', 'REPLANEJAMENTO')
               AND e.status_id = 2
               AND COALESCE(e.arquivada, 0) = 0
               AND o.status_obra = 0
               AND COALESCE(e.status, 'Pendente') NOT IN ('Entregue no prazo', 'Entregue com atraso', 'Entrega antecipada')";
    $tipos = '';
    $valores = [];
    if (!empty($filtros['obra_id'])) {
        $sql .= ' AND e.obra_id = ?';
        $tipos .= 'i';
        $valores[] = (int) $filtros['obra_id'];
    }
    if (!empty($filtros['cliente_id'])) {
        $sql .= ' AND o.cliente = ?';
        $tipos .= 'i';
        $valores[] = (int) $filtros['cliente_id'];
    }
    $sql .= ' ORDER BY e.obra_id, p.id';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }
    if ($tipos !== '') {
        $stmt->bind_param($tipos, ...$valores);
    }
    $stmt->execute();
    $resultado = $stmt->get_result();
    $planos = [];
    while ($linha = $resultado->fetch_assoc()) {
        if (flow_capacidade_plano_elegivel($linha)) {
            $linha['confiabilidade'] = (string) $linha['estado'] === 'DESATUALIZADO' ? 'REDUZIDA' : 'NORMAL';
            $planos[(int) $linha['versao_id']] = $linha;
        }
    }
    $stmt->close();
    if (!$planos) {
        return [];
    }

    $versoes = implode(',', array_map('intval', array_keys($planos)));
    $etapasSql = "SELECT f.*
                     FROM entrega_planejamento_funcao f
                    WHERE f.versao_id IN ({$versoes})
                      AND f.data_inicio IS NOT NULL
                      AND f.data_limite IS NOT NULL
                      AND f.data_inicio <= ?
                      AND f.data_limite >= ?
                    ORDER BY f.versao_id, f.ordem_apresentacao";
    $stmtEtapas = $conn->prepare($etapasSql);
    if (!$stmtEtapas) {
        throw new RuntimeException($conn->error);
    }
    $stmtEtapas->bind_param('ss', $fim, $inicio);
    $stmtEtapas->execute();
    $resultadoEtapas = $stmtEtapas->get_result();
    while ($etapa = $resultadoEtapas->fetch_assoc()) {
        $versaoId = (int) $etapa['versao_id'];
        $planos[$versaoId]['etapas'][] = $etapa;
    }
    $stmtEtapas->close();
    return array_values($planos);
}

function flow_capacidade_classificar(?float $principal, ?float $secundaria, float $demanda, bool $configurada): array
{
    if (!$configurada) {
        return [
            'classificacao' => 'SEM_CAPACIDADE_CONFIGURADA',
            'ocupacao' => null,
            'ocupacao_total' => null,
            'necessidade_apoio' => null,
            'deficit' => null,
            'principal_no_limite' => false,
            'capacidade_indisponivel' => false,
        ];
    }
    $principal = flow_capacidade_numero((float) $principal);
    $secundaria = flow_capacidade_numero((float) $secundaria);
    $total = flow_capacidade_numero($principal + $secundaria);
    if ($demanda <= 0.0) {
        return [
            'classificacao' => 'SEM_DEMANDA',
            'ocupacao' => $principal > 0.0 ? 0.0 : null,
            'ocupacao_total' => $total > 0.0 ? 0.0 : null,
            'necessidade_apoio' => 0.0,
            'deficit' => 0.0,
            'principal_no_limite' => false,
            'capacidade_indisponivel' => false,
        ];
    }
    if ($total <= 0.0) {
        return [
            'classificacao' => 'CONFLITO',
            'ocupacao' => null,
            'ocupacao_total' => null,
            'necessidade_apoio' => 0.0,
            'deficit' => flow_capacidade_numero($demanda),
            'principal_no_limite' => false,
            'capacidade_indisponivel' => true,
        ];
    }
    $ocupacaoPrincipal = $principal > 0.0 ? flow_capacidade_numero($demanda / $principal) : null;
    $ocupacaoTotal = flow_capacidade_numero($demanda / $total);
    $apoio = flow_capacidade_numero(max(0.0, $demanda - $principal));
    if ($principal <= 0.0 && $demanda <= $total) {
        return [
            'classificacao' => 'SEM_PRINCIPAIS_CONFIGURADOS',
            'ocupacao' => null,
            'ocupacao_total' => $ocupacaoTotal,
            'necessidade_apoio' => $apoio,
            'deficit' => 0.0,
            'principal_no_limite' => false,
            'capacidade_indisponivel' => false,
        ];
    }
    if ($demanda <= $principal) {
        return [
            'classificacao' => 'SAUDAVEL',
            'ocupacao' => $ocupacaoPrincipal,
            'ocupacao_total' => $ocupacaoTotal,
            'necessidade_apoio' => 0.0,
            'deficit' => 0.0,
            'principal_no_limite' => abs($demanda - $principal) < 0.0001,
            'capacidade_indisponivel' => false,
        ];
    }
    if ($demanda <= $total) {
        return [
            'classificacao' => 'NECESSITA_APOIO',
            'ocupacao' => $ocupacaoPrincipal,
            'ocupacao_total' => $ocupacaoTotal,
            'necessidade_apoio' => $apoio,
            'deficit' => 0.0,
            'principal_no_limite' => false,
            'capacidade_indisponivel' => false,
        ];
    }
    return [
        'classificacao' => 'CONFLITO',
        'ocupacao' => $ocupacaoPrincipal,
        'ocupacao_total' => $ocupacaoTotal,
        'necessidade_apoio' => flow_capacidade_numero(min($secundaria, $apoio)),
        'deficit' => flow_capacidade_numero($demanda - $total),
        'principal_no_limite' => false,
        'capacidade_indisponivel' => false,
    ];
}

function flow_capacidade_agrupar_conflitos(array $dias): array
{
    $periodos = [];
    $atual = null;
    foreach ($dias as $dia) {
        if (($dia['classificacao'] ?? '') !== 'CONFLITO') {
            if ($atual !== null) {
                $periodos[] = $atual;
                $atual = null;
            }
            continue;
        }
        $continua = $atual !== null
            && flow_planejamento_adicionar_dias_uteis((string) $atual['data_fim'], 1) === $dia['data'];
        if (!$continua) {
            if ($atual !== null) {
                $periodos[] = $atual;
            }
            $atual = [
                'data_inicio' => $dia['data'],
                'data_fim' => $dia['data'],
                'dias_conflito' => 0,
                'capacidade_minima' => $dia['capacidade'],
                'pico_demanda' => 0.0,
                'pico_ocupacao' => null,
                'deficit_maximo' => 0.0,
                'capacidade_indisponivel' => false,
                'dias' => [],
                'projetos' => [],
            ];
        }
        $atual['data_fim'] = $dia['data'];
        $atual['dias_conflito']++;
        $atual['capacidade_minima'] = $atual['capacidade_minima'] === null || $dia['capacidade'] === null
            ? null
            : min((float) $atual['capacidade_minima'], (float) $dia['capacidade']);
        $atual['pico_demanda'] = max((float) $atual['pico_demanda'], (float) $dia['demanda_planejada']);
        if ($dia['ocupacao'] !== null) {
            $atual['pico_ocupacao'] = $atual['pico_ocupacao'] === null ? $dia['ocupacao'] : max((float) $atual['pico_ocupacao'], (float) $dia['ocupacao']);
        }
        $atual['deficit_maximo'] = max((float) $atual['deficit_maximo'], (float) ($dia['deficit'] ?? 0));
        $atual['capacidade_indisponivel'] = $atual['capacidade_indisponivel'] || !empty($dia['capacidade_indisponivel']);
        $atual['dias'][] = $dia;
        foreach ($dia['projetos'] as $projeto) {
            $chave = (string) $projeto['versao_id'];
            if (!isset($atual['projetos'][$chave])) {
                $atual['projetos'][$chave] = $projeto + ['dias_em_conflito' => 0];
            }
            $atual['projetos'][$chave]['dias_em_conflito']++;
        }
    }
    if ($atual !== null) {
        $periodos[] = $atual;
    }
    foreach ($periodos as &$periodo) {
        $periodo['projetos'] = array_values($periodo['projetos']);
    }
    unset($periodo);
    return $periodos;
}

function flow_capacidade_prioridade_classificacao(string $classificacao): int
{
    return [
        'SEM_CAPACIDADE_CONFIGURADA' => 5,
        'CONFLITO' => 4,
        'SEM_PRINCIPAIS_CONFIGURADOS' => 3,
        'NECESSITA_APOIO' => 2,
        'SAUDAVEL' => 1,
        'SEM_DEMANDA' => 0,
    ][$classificacao] ?? 0;
}

/**
 * A UI usa semanas, mas a decisão é sempre ancorada no pior dia útil: média
 * semanal nunca pode esconder um conflito pontual.
 */
function flow_capacidade_resumo_semanal(array $dias): array
{
    $semanas = [];
    foreach ($dias as $dia) {
        $semana = flow_capacidade_inicio_semana((string) $dia['data']);
        if (!isset($semanas[$semana])) {
            $semanas[$semana] = [
                'semana' => $semana,
                'capacidade_minima' => $dia['capacidade'],
                'pico_demanda' => 0.0,
                'pico_ocupacao' => null,
                'dias_conflito' => 0,
                'dias_necessita_apoio' => 0,
                'dias_sem_principais' => 0,
                'deficit_maximo' => 0.0,
                'apoio_maximo' => 0.0,
                'dias_analisados' => 0,
                'sem_configuracao' => false,
                'principal_no_limite' => false,
                'dia_referencia' => null,
                'classificacao_referencia' => 'SEM_DEMANDA',
                'capacidade_principal_referencia' => null,
                'capacidade_secundaria_referencia' => null,
                'capacidade_total_referencia' => null,
                'projetos' => [],
            ];
        }
        $item = &$semanas[$semana];
        $item['dias_analisados']++;
        $item['capacidade_minima'] = $item['capacidade_minima'] === null || $dia['capacidade'] === null
            ? null
            : min((float) $item['capacidade_minima'], (float) $dia['capacidade']);
        $item['pico_demanda'] = max((float) $item['pico_demanda'], (float) $dia['demanda_planejada']);
        if ($dia['ocupacao'] !== null) {
            $item['pico_ocupacao'] = $item['pico_ocupacao'] === null ? $dia['ocupacao'] : max((float) $item['pico_ocupacao'], (float) $dia['ocupacao']);
        }
        $item['dias_conflito'] += ($dia['classificacao'] === 'CONFLITO' ? 1 : 0);
        $item['dias_necessita_apoio'] += ($dia['classificacao'] === 'NECESSITA_APOIO' ? 1 : 0);
        $item['dias_sem_principais'] += ($dia['classificacao'] === 'SEM_PRINCIPAIS_CONFIGURADOS' ? 1 : 0);
        $item['deficit_maximo'] = max((float) $item['deficit_maximo'], (float) ($dia['deficit'] ?? 0));
        $item['apoio_maximo'] = max((float) $item['apoio_maximo'], (float) ($dia['necessidade_apoio'] ?? 0));
        $item['sem_configuracao'] = $item['sem_configuracao'] || $dia['classificacao'] === 'SEM_CAPACIDADE_CONFIGURADA';
        $item['principal_no_limite'] = $item['principal_no_limite'] || !empty($dia['principal_no_limite']);

        $prioridadeAtual = flow_capacidade_prioridade_classificacao((string) ($item['classificacao_referencia'] ?? 'SEM_DEMANDA'));
        $prioridadeDia = flow_capacidade_prioridade_classificacao((string) ($dia['classificacao'] ?? 'SEM_DEMANDA'));
        $mesmaPrioridadeComPiorImpacto = $prioridadeDia === $prioridadeAtual
            && (
                (float) ($dia['deficit'] ?? 0) > (float) ($item['deficit_referencia'] ?? 0)
                || (float) ($dia['necessidade_apoio'] ?? 0) > (float) ($item['apoio_referencia'] ?? 0)
                || (float) ($dia['demanda_planejada'] ?? 0) > (float) ($item['demanda_referencia'] ?? 0)
            );
        $deveSubstituirReferencia = $item['dia_referencia'] === null
            || $prioridadeDia > $prioridadeAtual
            || $mesmaPrioridadeComPiorImpacto;
        if ($deveSubstituirReferencia) {
            $item['dia_referencia'] = $dia['data'];
            $item['classificacao_referencia'] = $dia['classificacao'];
            $item['capacidade_principal_referencia'] = $dia['capacidade_principal'];
            $item['capacidade_secundaria_referencia'] = $dia['capacidade_secundaria'];
            $item['capacidade_total_referencia'] = $dia['capacidade_total'];
            $item['demanda_referencia'] = $dia['demanda_planejada'];
            $item['apoio_referencia'] = $dia['necessidade_apoio'];
            $item['deficit_referencia'] = $dia['deficit'];
        }
        foreach (($dia['projetos'] ?? []) as $projeto) {
            $chave = (string) ($projeto['versao_id'] ?? $projeto['planejamento_id'] ?? '');
            if ($chave === '') {
                continue;
            }
            if (!isset($item['projetos'][$chave])) {
                $item['projetos'][$chave] = $projeto + [
                    'dias_consumindo_semana' => 0,
                    'primeiro_dia_semana' => $dia['data'],
                    'ultimo_dia_semana' => $dia['data'],
                ];
            }
            $item['projetos'][$chave]['dias_consumindo_semana']++;
            $item['projetos'][$chave]['primeiro_dia_semana'] = min((string) $item['projetos'][$chave]['primeiro_dia_semana'], (string) $dia['data']);
            $item['projetos'][$chave]['ultimo_dia_semana'] = max((string) $item['projetos'][$chave]['ultimo_dia_semana'], (string) $dia['data']);
        }
        unset($item);
    }
    foreach ($semanas as &$semana) {
        $semana['classificacao'] = $semana['sem_configuracao']
            ? 'SEM_CAPACIDADE_CONFIGURADA'
            : ($semana['dias_conflito'] > 0
                ? 'CONFLITO'
                : ($semana['dias_sem_principais'] > 0
                    ? 'SEM_PRINCIPAIS_CONFIGURADOS'
                    : ($semana['dias_necessita_apoio'] > 0 ? 'NECESSITA_APOIO' : 'SAUDAVEL')));
        unset($semana['sem_configuracao']);
        $semana['projetos'] = array_values($semana['projetos']);
        usort($semana['projetos'], static function (array $a, array $b): int {
            $margemA = $a['margem_dias_uteis'] ?? PHP_INT_MAX;
            $margemB = $b['margem_dias_uteis'] ?? PHP_INT_MAX;
            return $margemA <=> $margemB
                ?: ((float) ($b['capacidade_planejada'] ?? 0) <=> (float) ($a['capacidade_planejada'] ?? 0))
                ?: strcmp((string) ($a['obra'] ?? ''), (string) ($b['obra'] ?? ''));
        });
    }
    unset($semana);
    return array_values($semanas);
}

/**
 * Função pura para testes e para o serviço: recebe as etapas já persistidas,
 * expande apenas as de janela completa e agrega demanda planejada por dia.
 */
function flow_capacidade_calcular_demanda_planejada(array $planos, string $inicio, string $fim, array $configuracoes = [], array $filtros = []): array
{
    if (!flow_capacidade_data_valida($inicio) || !flow_capacidade_data_valida($fim) || $fim < $inicio) {
        throw new InvalidArgumentException('O intervalo de capacidade deve usar datas válidas em formato Y-m-d.');
    }
    $configuracoes = flow_capacidade_normalizar_configuracoes($configuracoes);
    $definicoes = flow_capacidade_definicoes_etapas();
    $diasUteis = flow_capacidade_dias_uteis_no_intervalo($inicio, $fim);
    $diasPorEtapa = [];
    $exclusoes = [];
    $planosConsiderados = [];

    foreach ($planos as $plano) {
        if (!flow_capacidade_plano_elegivel($plano)) {
            continue;
        }
        if (!empty($filtros['obra_id']) && (int) $plano['obra_id'] !== (int) $filtros['obra_id']) {
            continue;
        }
        foreach (($plano['etapas'] ?? []) as $etapa) {
            $codigo = (string) ($etapa['codigo_etapa'] ?? $etapa['codigo'] ?? '');
            if ($codigo === '' || (!empty($filtros['etapa']) && $codigo !== (string) $filtros['etapa'])) {
                continue;
            }
            $definicao = $definicoes[$codigo] ?? null;
            $metadados = is_array($etapa['metadados_json'] ?? null)
                ? $etapa['metadados_json']
                : (json_decode((string) ($etapa['metadados_json'] ?? ''), true) ?: []);
            if (!$definicao || !empty($metadados['nao_aplicavel'])) {
                continue;
            }
            $estrategia = (string) ($definicao['estrategia'] ?? FLOW_CAPACIDADE_ESTRATEGIA_NAO_CONSUME);
            if ($estrategia !== FLOW_CAPACIDADE_ESTRATEGIA_JANELA_COMPLETA) {
                $exclusoes[] = [
                    'codigo_etapa' => $codigo,
                    'etapa' => $definicao['nome'],
                    'planejamento_id' => (int) $plano['planejamento_id'],
                    'versao_id' => (int) $plano['versao_id'],
                    'entrega_id' => (int) $plano['entrega_id'],
                    'obra_id' => (int) $plano['obra_id'],
                    'obra' => (string) ($plano['nomenclatura'] ?: $plano['nome_obra']),
                    'estrategia' => $estrategia,
                    'motivo' => $definicao['motivo_nao_consume'] ?? 'ESTRATEGIA_SEM_CONSUMO_DE_CAPACIDADE',
                ];
                continue;
            }
            $inicioEtapa = (string) ($etapa['data_inicio'] ?? $etapa['inicio'] ?? '');
            $fimEtapa = (string) ($etapa['data_limite'] ?? $etapa['limite'] ?? '');
            if (!flow_capacidade_data_valida($inicioEtapa) || !flow_capacidade_data_valida($fimEtapa) || $fimEtapa < $inicioEtapa) {
                continue;
            }
            $capacidadePlanejada = flow_capacidade_numero((float) ($etapa['pessoas_alocadas'] ?? 0));
            if ($capacidadePlanejada <= 0.0) {
                continue;
            }
            $faixaInicio = max($inicio, $inicioEtapa);
            $faixaFim = min($fim, $fimEtapa);
            $diasDaFaixa = flow_capacidade_dias_uteis_no_intervalo($faixaInicio, $faixaFim);
            if (!$diasDaFaixa) {
                continue;
            }
            // O plano só é considerado no horizonte global quando uma etapa
            // de fato consome capacidade dentro do intervalo consultado.
            $planosConsiderados[(int) $plano['versao_id']] = $plano;
            foreach ($diasDaFaixa as $dia) {
                $diasPorEtapa[$codigo][$dia][] = [
                    'planejamento_id' => (int) $plano['planejamento_id'],
                    'versao_id' => (int) $plano['versao_id'],
                    'versao_numero' => (int) ($plano['versao_numero'] ?? 0),
                    'entrega_id' => (int) $plano['entrega_id'],
                    'obra_id' => (int) $plano['obra_id'],
                    'obra' => (string) ($plano['nomenclatura'] ?: $plano['nome_obra']),
                    'capacidade_planejada' => $capacidadePlanejada,
                    'margem_dias_uteis' => isset($plano['margem_dias_uteis']) ? (int) $plano['margem_dias_uteis'] : null,
                    'status_plano' => (string) ($plano['status_plano'] ?? ''),
                    'estado_planejamento' => (string) ($plano['estado'] ?? ''),
                    'confiabilidade' => (string) ($plano['confiabilidade'] ?? ((string) ($plano['estado'] ?? '') === 'DESATUALIZADO' ? 'REDUZIDA' : 'NORMAL')),
                    'etapa_inicio' => $inicioEtapa,
                    'etapa_limite' => $fimEtapa,
                ];
            }
        }
    }

    $etapas = [];
    foreach ($definicoes as $codigo => $definicao) {
        if (!empty($filtros['etapa']) && $codigo !== (string) $filtros['etapa']) {
            continue;
        }
        if (($definicao['estrategia'] ?? null) !== FLOW_CAPACIDADE_ESTRATEGIA_JANELA_COMPLETA && empty($diasPorEtapa[$codigo])) {
            continue;
        }
        $dias = [];
        foreach ($diasUteis as $dia) {
            $projetos = $diasPorEtapa[$codigo][$dia] ?? [];
            $demanda = flow_capacidade_numero(array_sum(array_column($projetos, 'capacidade_planejada')));
            $disponibilidade = flow_capacidade_disponivel_em($codigo, $dia, $configuracoes);
            $estado = flow_capacidade_classificar(
                $disponibilidade['capacidade_principal'],
                $disponibilidade['capacidade_secundaria'],
                $demanda,
                $disponibilidade['configurada']
            );
            $dias[] = array_merge([
                'data' => $dia,
                'demanda_planejada' => $demanda,
                // Alias legado para quem já consome a capacidade normal.
                'capacidade' => $disponibilidade['capacidade'],
                'capacidade_principal' => $disponibilidade['capacidade_principal'],
                'capacidade_secundaria' => $disponibilidade['capacidade_secundaria'],
                'capacidade_total' => $disponibilidade['capacidade_total'],
                'capacidade_secundaria_potencial' => true,
                'capacidade_configurada' => $disponibilidade['configurada'],
                'origem_capacidade' => $disponibilidade['origem'],
                'projetos' => $projetos,
            ], $estado);
        }
        $temDemanda = array_filter($dias, static fn (array $dia): bool => $dia['demanda_planejada'] > 0.0);
        if (!$temDemanda) {
            continue;
        }
        $conflitos = flow_capacidade_agrupar_conflitos($dias);
        foreach ($conflitos as &$conflito) {
            $conflito['codigo_etapa'] = $codigo;
            $conflito['etapa'] = $definicao['nome'];
        }
        unset($conflito);
        $etapas[] = [
            'codigo_etapa' => $codigo,
            'etapa' => $definicao['nome'],
            'nome_painel' => $definicao['nome_painel'] ?? $definicao['nome'],
            'ordem_painel' => (int) ($definicao['ordem_painel'] ?? 999),
            'visivel_painel' => !empty($definicao['visivel_painel']),
            'estrategia_consumo' => $definicao['estrategia'],
            'dias' => $dias,
            'semanas' => flow_capacidade_resumo_semanal($dias),
            'conflitos' => $conflitos,
        ];
    }

    $conflitos = [];
    $diasNecessitaApoio = 0;
    $diasSemPrincipais = 0;
    $resumoEtapas = [];
    $prioridade = null;
    foreach ($etapas as $etapa) {
        foreach ($etapa['conflitos'] as $conflito) {
            $conflitos[] = $conflito;
        }
        foreach ($etapa['dias'] as $dia) {
            $diasNecessitaApoio += ($dia['classificacao'] ?? '') === 'NECESSITA_APOIO' ? 1 : 0;
            $diasSemPrincipais += ($dia['classificacao'] ?? '') === 'SEM_PRINCIPAIS_CONFIGURADOS' ? 1 : 0;
        }
        $classificacaoEtapa = 'SAUDAVEL';
        $melhorSemana = null;
        foreach ($etapa['semanas'] as $semana) {
            if (flow_capacidade_prioridade_classificacao((string) $semana['classificacao']) > flow_capacidade_prioridade_classificacao($classificacaoEtapa)) {
                $classificacaoEtapa = (string) $semana['classificacao'];
            }
            if ($melhorSemana === null
                || flow_capacidade_prioridade_classificacao((string) $semana['classificacao']) > flow_capacidade_prioridade_classificacao((string) $melhorSemana['classificacao'])
                || (flow_capacidade_prioridade_classificacao((string) $semana['classificacao']) === flow_capacidade_prioridade_classificacao((string) $melhorSemana['classificacao'])
                    && ((float) ($semana['deficit_maximo'] ?? 0) > (float) ($melhorSemana['deficit_maximo'] ?? 0)
                        || (float) ($semana['apoio_maximo'] ?? 0) > (float) ($melhorSemana['apoio_maximo'] ?? 0)))) {
                $melhorSemana = $semana;
            }
        }
        $resumoEtapas[] = [
            'codigo_etapa' => $etapa['codigo_etapa'],
            'etapa' => $etapa['etapa'],
            'nome_painel' => $etapa['nome_painel'],
            'ordem_painel' => $etapa['ordem_painel'],
            'classificacao' => $classificacaoEtapa,
            'semana_critica' => $melhorSemana,
        ];
        if ($melhorSemana !== null) {
            $candidatoPrioridade = [
                'codigo_etapa' => $etapa['codigo_etapa'],
                'etapa' => $etapa['etapa'],
                'nome_painel' => $etapa['nome_painel'],
                'semana' => $melhorSemana,
            ];
            if ($prioridade === null
                || flow_capacidade_prioridade_classificacao((string) $candidatoPrioridade['semana']['classificacao']) > flow_capacidade_prioridade_classificacao((string) $prioridade['semana']['classificacao'])
                || (flow_capacidade_prioridade_classificacao((string) $candidatoPrioridade['semana']['classificacao']) === flow_capacidade_prioridade_classificacao((string) $prioridade['semana']['classificacao'])
                    && ((float) ($candidatoPrioridade['semana']['deficit_maximo'] ?? 0) > (float) ($prioridade['semana']['deficit_maximo'] ?? 0)
                        || (float) ($candidatoPrioridade['semana']['apoio_maximo'] ?? 0) > (float) ($prioridade['semana']['apoio_maximo'] ?? 0)))) {
                $prioridade = $candidatoPrioridade;
            }
        }
    }
    $obras = array_unique(array_map(static fn (array $plano): int => (int) $plano['obra_id'], $planosConsiderados));
    $capacidades = [];
    foreach ($configuracoes as $codigo => $configuracao) {
        $capacidades[$codigo] = [
            'capacidade_padrao' => $configuracao['capacidade_padrao'],
            'capacidade_principal' => $configuracao['capacidade_principal'],
            'capacidade_secundaria' => $configuracao['capacidade_secundaria'],
            'capacidade_total' => flow_capacidade_numero($configuracao['capacidade_principal'] + $configuracao['capacidade_secundaria']),
            'ativo' => $configuracao['ativo'],
            'origem' => $configuracao['origem'],
            'colaboradores' => $configuracao['colaboradores'] ?? [],
            'colaboradores_principais' => $configuracao['colaboradores_principais'] ?? [],
            'colaboradores_secundarios' => $configuracao['colaboradores_secundarios'] ?? [],
            'evidencia' => $configuracao['evidencia'] ?? '',
            'pool_fisico' => $configuracao['pool_fisico'] ?? '',
            'overrides' => $configuracao['overrides'] ?? [],
        ];
    }
    usort($etapas, static fn (array $a, array $b): int => ($a['ordem_painel'] ?? 999) <=> ($b['ordem_painel'] ?? 999));
    usort($resumoEtapas, static fn (array $a, array $b): int => ($a['ordem_painel'] ?? 999) <=> ($b['ordem_painel'] ?? 999));
    $catalogoEtapas = [];
    foreach ($definicoes as $codigo => $definicao) {
        if (empty($definicao['visivel_painel'])) {
            continue;
        }
        $catalogoEtapas[] = [
            'codigo_etapa' => $codigo,
            'etapa' => $definicao['nome'],
            'nome_painel' => $definicao['nome_painel'] ?? $definicao['nome'],
            'ordem_painel' => (int) ($definicao['ordem_painel'] ?? 999),
        ];
    }
    usort($catalogoEtapas, static fn (array $a, array $b): int => $a['ordem_painel'] <=> $b['ordem_painel']);
    $resumoClassificacoes = [
        'SAUDAVEL' => 0,
        'NECESSITA_APOIO' => 0,
        'CONFLITO' => 0,
        'SEM_PRINCIPAIS_CONFIGURADOS' => 0,
        'SEM_CAPACIDADE_CONFIGURADA' => 0,
    ];
    foreach ($resumoEtapas as $resumoEtapa) {
        $codigoClassificacao = (string) $resumoEtapa['classificacao'];
        if (array_key_exists($codigoClassificacao, $resumoClassificacoes)) {
            $resumoClassificacoes[$codigoClassificacao]++;
        }
    }
    return [
        'periodo' => ['inicio' => $inicio, 'fim' => $fim],
        'tipo_demanda' => 'PLANEJADA',
        'resumo' => [
            'planos_considerados' => count($planosConsiderados),
            'obras_consideradas' => count($obras),
            'dias_uteis_analisados' => count($diasUteis),
            'conflitos' => count($conflitos),
            'dias_necessita_apoio' => $diasNecessitaApoio,
            'dias_sem_principais_configurados' => $diasSemPrincipais,
            'planos_desatualizados' => count(array_filter($planosConsiderados, static fn (array $plano): bool => ($plano['estado'] ?? '') === 'DESATUALIZADO')),
            'funcoes_por_classificacao' => $resumoClassificacoes,
        ],
        'planos_considerados' => array_values(array_map(static function (array $plano): array {
            unset($plano['etapas']);
            return $plano;
        }, $planosConsiderados)),
        'etapas' => $etapas,
        'catalogo_etapas' => $catalogoEtapas,
        'resumo_etapas' => $resumoEtapas,
        'prioridade' => $prioridade,
        'conflitos' => $conflitos,
        'etapas_sem_demanda_inferida' => $exclusoes,
        'capacidades' => $capacidades,
    ];
}

function flow_capacidade_consultar(mysqli $conn, string $inicio, string $fim, array $opcoes = []): array
{
    if (!flow_capacidade_tabelas_disponiveis($conn)) {
        throw new RuntimeException('A migration de capacidade global ainda não foi aplicada.');
    }
    $planos = flow_capacidade_carregar_planos_vigentes($conn, $inicio, $fim, $opcoes);
    if (isset($opcoes['capacidades_fixture'])) {
        $configuracoes = flow_capacidade_normalizar_configuracoes((array) $opcoes['capacidades_fixture']);
        $origemCapacidade = 'FIXTURE_TESTE';
    } else {
        $configuracoes = flow_capacidade_carregar_configuracoes_colaboradores($conn);

        // A tabela de capacidade permanece disponível apenas para exceções
        // temporárias por período. Ela não substitui a capacidade observada
        // nos colaboradores ativos.
        $persistidas = flow_capacidade_carregar_configuracoes($conn);
        foreach ($persistidas as $codigo => $persistida) {
            if (!empty($persistida['overrides']) && isset($configuracoes[$codigo])) {
                $configuracoes[$codigo]['overrides'] = $persistida['overrides'];
                $configuracoes[$codigo]['origem'] .= '_COM_OVERRIDES';
            }
        }
        $configuracoes = flow_capacidade_normalizar_configuracoes($configuracoes);
        $origemCapacidade = 'FUNCAO_COLABORADOR_ELEGIVEL';
    }
    $resultado = flow_capacidade_calcular_demanda_planejada($planos, $inicio, $fim, $configuracoes, $opcoes);
    $resultado['origem_capacidade'] = $origemCapacidade;
    return $resultado;
}
