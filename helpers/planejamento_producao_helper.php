<?php

/**
 * Motor experimental de planejamento de produção R00.
 *
 * Este arquivo deliberadamente não grava nada. Ele transforma o estado atual
 * das tarefas (inclusive as funções ainda somente planejadas) em marcos
 * gerenciais por função. A persistência de baseline e a integração com a UI
 * serão tratadas em etapas posteriores.
 */

require_once dirname(__DIR__) . '/Entregas/prazo_entrega_helper.php';

const FLOW_PLANEJAMENTO_JANELA_HISTORICO_DIAS = 540;
const FLOW_PLANEJAMENTO_MIN_CICLOS_CONFIAVEIS = 8;
const FLOW_PLANEJAMENTO_MAX_DURACAO_CICLO_DIAS_UTEIS = 45;

function flow_planejamento_normalizar(string $valor): string
{
    $valor = trim($valor);
    if (function_exists('mb_strtolower')) {
        $valor = mb_strtolower($valor, 'UTF-8');
    } else {
        $valor = strtolower($valor);
    }

    return strtr($valor, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
}

function flow_planejamento_tipo_imagem(string $tipoImagem): ?string
{
    $tipo = flow_planejamento_normalizar($tipoImagem);
    $mapa = [
        'fachada' => 'FACHADA',
        'imagem externa' => 'EXTERNA',
        'imagem interna' => 'INTERNA',
        'unidade' => 'UNIDADE',
        'planta humanizada' => 'PLANTA',
    ];

    return $mapa[$tipo] ?? null;
}

function flow_planejamento_status_finalizado(string $status): bool
{
    return in_array(flow_planejamento_normalizar($status), [
        'finalizado',
        'aprovado',
        'aprovado com ajustes',
    ], true);
}

function flow_planejamento_status_hold(string $status): bool
{
    return strpos(flow_planejamento_normalizar($status), 'hold') !== false;
}

/** Usa a única regra de calendário de entregas; não replica feriados. */
function flow_planejamento_adicionar_dias_uteis(string $data, int $dias): string
{
    return entregas_adicionar_dias_uteis($data, max(0, $dias));
}

/**
 * Dias úteis decorridos sem duplicar a regra de feriados de Entregas.
 * O mesmo dia representa zero dias transcorridos; um ciclo concluído no mesmo
 * dia é normalizado para um dia ao calcular produtividade.
 */
function flow_planejamento_dias_uteis_entre(string $inicio, string $fim): int
{
    if ($fim <= $inicio) {
        return 0;
    }

    $dias = 0;
    $cursor = $inicio;
    while ($cursor < $fim) {
        $proximo = flow_planejamento_adicionar_dias_uteis($cursor, 1);
        if ($proximo > $fim) {
            break;
        }
        $dias++;
        $cursor = $proximo;
    }

    return $dias;
}

function flow_planejamento_mediana(array $valores): ?float
{
    $valores = array_values(array_filter($valores, static fn ($valor) => is_numeric($valor)));
    if (!$valores) {
        return null;
    }
    sort($valores, SORT_NUMERIC);
    $n = count($valores);
    $meio = intdiv($n, 2);
    return $n % 2 ? (float) $valores[$meio] : ((float) $valores[$meio - 1] + (float) $valores[$meio]) / 2;
}

function flow_planejamento_remover_outliers(array $duracoes): array
{
    if (count($duracoes) < 5) {
        return $duracoes;
    }

    $mediana = flow_planejamento_mediana($duracoes);
    $desvios = array_map(static fn ($valor) => abs((float) $valor - $mediana), $duracoes);
    $mad = flow_planejamento_mediana($desvios);
    if ($mad === null || $mad == 0.0) {
        return array_values(array_filter($duracoes, static fn ($valor) => $valor <= max(1, $mediana * 3)));
    }

    $limite = 3.5 * $mad;
    return array_values(array_filter($duracoes, static fn ($valor) => abs((float) $valor - $mediana) <= $limite));
}

/**
 * Classificação explícita por IDs de função e por valores canônicos do domínio
 * imagens_cliente_obra.tipo_imagem. Não depende de ordem/nome da imagem.
 */
function flow_planejamento_codigo_etapa(array $item): ?string
{
    $funcaoId = (int) ($item['funcao_id'] ?? 0);
    $tipo = flow_planejamento_tipo_imagem((string) ($item['tipo_imagem'] ?? ''));

    if ($tipo === null) {
        return null;
    }
    if (in_array($funcaoId, [1, 8], true) && $tipo !== 'PLANTA') {
        return 'CADERNO_FILTRO';
    }
    // A imagem marcada como Fachada é a única frente de modelagem paralela.
    // As Imagens Externas possuem modelagem operacional na mesma frente da
    // modelagem interna (regra validada na obra 116: 12 internas + 2 externas).
    if ($funcaoId === 2 && $tipo === 'FACHADA') {
        return 'MODELAGEM_FACHADA';
    }
    if ($funcaoId === 2 && in_array($tipo, ['EXTERNA', 'INTERNA', 'UNIDADE'], true)) {
        return 'MODELAGEM_INTERNA';
    }
    if ($funcaoId === 3 && $tipo !== 'PLANTA') {
        return 'COMPOSICAO';
    }
    if ($funcaoId === 4 && in_array($tipo, ['FACHADA', 'EXTERNA'], true)) {
        return 'FINALIZACAO_EXTERNA';
    }
    if ($funcaoId === 4 && in_array($tipo, ['INTERNA', 'UNIDADE'], true)) {
        return 'FINALIZACAO_INTERNA';
    }
    if ($funcaoId === 4 && $tipo === 'PLANTA') {
        return 'FINALIZACAO_PLANTA';
    }
    if ($funcaoId === 5) {
        return 'POS_PRODUCAO';
    }

    return null;
}

function flow_planejamento_regra_classificacao(array $item, ?string $codigo = null): string
{
    $codigo = $codigo ?? flow_planejamento_codigo_etapa($item);
    $tipo = flow_planejamento_tipo_imagem((string) ($item['tipo_imagem'] ?? ''));
    if ($codigo === 'MODELAGEM_FACHADA') {
        return 'FUNCAO_MODELAGEM_EM_TIPO_FACHADA';
    }
    if ($codigo === 'MODELAGEM_INTERNA') {
        return 'FUNCAO_MODELAGEM_EM_TIPO_INTERNO_EXTERNO_OU_UNIDADE';
    }
    if (str_starts_with((string) $codigo, 'FINALIZACAO_')) {
        return 'FUNCAO_FINALIZACAO_CLASSIFICADA_POR_TIPO_IMAGEM';
    }
    return 'FUNCAO_OPERACIONAL_CLASSIFICADA_POR_FUNCAO_E_TIPO_IMAGEM';
}

function flow_planejamento_definicoes_etapas(): array
{
    return [
        // Caderno é a fonte histórica: apresenta menor taxa de descarte que
        // Filtro e ambos são executados pela mesma pessoa na mesma janela.
        'CADERNO_FILTRO' => ['nome' => 'Caderno + Filtro de Assets', 'funcao_id' => 1, 'funcoes_origem' => [1, 8], 'estrategia' => 'HISTORICO_POR_PESSOA', 'paralela' => true],
        'MODELAGEM_FACHADA' => ['nome' => 'Modelagem da Fachada', 'funcao_id' => 2, 'estrategia' => 'JANELA_FIXA', 'duracao_fixa_dias_uteis' => 7, 'paralela' => true],
        'MODELAGEM_INTERNA' => ['nome' => 'Modelagem Interna', 'funcao_id' => 2, 'estrategia' => 'HISTORICO_POR_PESSOA', 'paralela' => false],
        'COMPOSICAO' => ['nome' => 'Composição', 'funcao_id' => 3, 'estrategia' => 'HISTORICO_POR_PESSOA', 'paralela' => false],
        'FINALIZACAO_EXTERNA' => ['nome' => 'Finalização Externa', 'funcao_id' => 4, 'estrategia' => 'OPERACIONAL_POR_TAREFA', 'tarefas_por_dia_pessoa' => 1, 'paralela' => true],
        'FINALIZACAO_INTERNA' => ['nome' => 'Finalização Interna', 'funcao_id' => 4, 'estrategia' => 'OPERACIONAL_POR_TAREFA', 'tarefas_por_dia_pessoa' => 1, 'paralela' => true],
        'FINALIZACAO_PLANTA' => ['nome' => 'Finalização Planta', 'funcao_id' => 4, 'estrategia' => 'OPERACIONAL_POR_TAREFA', 'tarefas_por_dia_pessoa' => 1, 'paralela' => true],
        'FINALIZACAO_GLOBAL' => ['nome' => 'Finalização (marco global)', 'virtual' => true, 'paralela' => false],
        'POS_PRODUCAO' => ['nome' => 'Pós-Produção', 'funcao_id' => 5, 'estrategia' => 'OPERACIONAL_POR_TAXA', 'tarefas_por_dia_pessoa' => 5, 'paralela' => false],
    ];
}

function flow_planejamento_dependencias(string $codigo, array $ativos): array
{
    $tem = static fn (string $etapa): bool => !empty($ativos[$etapa]);
    $finalizacoes = array_values(array_filter(['FINALIZACAO_EXTERNA', 'FINALIZACAO_INTERNA', 'FINALIZACAO_PLANTA'], $tem));

    switch ($codigo) {
        case 'CADERNO_FILTRO':
        case 'MODELAGEM_FACHADA':
            return [];
        case 'MODELAGEM_INTERNA':
            return $tem('CADERNO_FILTRO') ? ['CADERNO_FILTRO'] : [];
        case 'COMPOSICAO':
            return $tem('MODELAGEM_INTERNA') ? ['MODELAGEM_INTERNA'] : [];
        case 'FINALIZACAO_EXTERNA':
        case 'FINALIZACAO_INTERNA':
        case 'FINALIZACAO_PLANTA':
            return $tem('COMPOSICAO') ? ['COMPOSICAO'] : [];
        case 'FINALIZACAO_GLOBAL':
            return $finalizacoes;
        case 'POS_PRODUCAO':
            return $tem('FINALIZACAO_GLOBAL') ? ['FINALIZACAO_GLOBAL'] : [];
    }
    return [];
}

/**
 * Reconstrói ciclos fechados de uma sequência de log_alteracoes.
 * HOLD, cancelamento, data inválida e duração extrema não entram na amostra.
 */
function flow_planejamento_ciclos_validos(array $logs, ?string $corte = null): array
{
    usort($logs, static fn (array $a, array $b) => strcmp((string) $a['data'], (string) $b['data']));
    $resultado = ['duracoes' => [], 'descartados_hold' => 0, 'descartados_duracao' => 0, 'reaberturas' => 0];
    $inicio = null;
    $contaminado = false;
    $teveTerminal = false;

    foreach ($logs as $log) {
        $data = substr((string) ($log['data'] ?? ''), 0, 10);
        if (!entregas_valid_date($data) || $data === '0000-00-00') {
            continue;
        }
        $status = (string) ($log['status_novo'] ?? '');
        $normalizado = flow_planejamento_normalizar($status);

        if ($normalizado === 'em andamento') {
            if ($teveTerminal) {
                $resultado['reaberturas']++;
                $teveTerminal = false;
            }
            $inicio = $data;
            $contaminado = false;
            continue;
        }
        if ($inicio === null) {
            continue;
        }
        if (flow_planejamento_status_hold($status)) {
            $contaminado = true;
            continue;
        }
        if ($normalizado === 'cancelado') {
            $inicio = null;
            $contaminado = false;
            continue;
        }
        if (!flow_planejamento_status_finalizado($status)) {
            continue;
        }

        $duracao = max(1, flow_planejamento_dias_uteis_entre($inicio, $data));
        if ($contaminado) {
            $resultado['descartados_hold']++;
        } elseif ($duracao > FLOW_PLANEJAMENTO_MAX_DURACAO_CICLO_DIAS_UTEIS) {
            $resultado['descartados_duracao']++;
        } elseif ($corte === null || $data >= $corte) {
            $resultado['duracoes'][] = $duracao;
        }
        $inicio = null;
        $contaminado = false;
        $teveTerminal = true;
    }

    return $resultado;
}

function flow_planejamento_estimar_produtividade(mysqli $conn, int $funcaoId, array $tiposPermitidos, array &$cache): array
{
    sort($tiposPermitidos);
    $chave = $funcaoId . ':' . implode(',', $tiposPermitidos);
    if (isset($cache[$chave])) {
        return $cache[$chave];
    }

    $corte = date('Y-m-d', strtotime('-' . FLOW_PLANEJAMENTO_JANELA_HISTORICO_DIAS . ' days'));
    $stmt = $conn->prepare(
        "SELECT fi.idfuncao_imagem, ico.tipo_imagem, l.status_novo, l.data
          FROM funcao_imagem fi
           JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
           JOIN log_alteracoes l ON l.funcao_imagem_id = fi.idfuncao_imagem
          WHERE fi.funcao_id = ?
            AND l.data IS NOT NULL
          ORDER BY fi.idfuncao_imagem, l.data, l.idlog"
    );
    if (!$stmt) {
        throw new RuntimeException('Não foi possível preparar histórico de produtividade: ' . $conn->error);
    }
    $stmt->bind_param('i', $funcaoId);
    $stmt->execute();
    $result = $stmt->get_result();
    $porTarefa = [];
    while ($row = $result->fetch_assoc()) {
        $tipo = flow_planejamento_tipo_imagem((string) $row['tipo_imagem']);
        if ($tipo !== null && in_array($tipo, $tiposPermitidos, true)) {
            $porTarefa[(int) $row['idfuncao_imagem']][] = $row;
        }
    }
    $stmt->close();

    $duracoes = [];
    $hold = 0;
    $duracaoDescartada = 0;
    $reaberturas = 0;
    foreach ($porTarefa as $logs) {
        $ciclos = flow_planejamento_ciclos_validos($logs, $corte);
        $duracoes = array_merge($duracoes, $ciclos['duracoes']);
        $hold += $ciclos['descartados_hold'];
        $duracaoDescartada += $ciclos['descartados_duracao'];
        $reaberturas += $ciclos['reaberturas'];
    }

    $antesOutliers = count($duracoes);
    $duracoes = flow_planejamento_remover_outliers($duracoes);
    $mediana = flow_planejamento_mediana($duracoes);
    $amostra = count($duracoes);
    $outliersRemovidos = $antesOutliers - $amostra;
    $descartes = $hold + $duracaoDescartada + $outliersRemovidos;
    $taxaDescartes = ($amostra + $descartes) > 0 ? $descartes / ($amostra + $descartes) : 1;
    if ($amostra < FLOW_PLANEJAMENTO_MIN_CICLOS_CONFIAVEIS || $taxaDescartes > 0.45) {
        $confianca = 'INSUFICIENTE';
    } elseif ($amostra >= 20 && $taxaDescartes <= 0.20) {
        $confianca = 'ALTA';
    } else {
        $confianca = 'MEDIA';
    }

    return $cache[$chave] = [
        'metodo' => 'MEDIANA_DURACAO_CICLO_POR_TAREFA',
        'janela_dias' => FLOW_PLANEJAMENTO_JANELA_HISTORICO_DIAS,
        'corte' => $corte,
        'amostra_ciclos_validos' => $amostra,
        'amostra_antes_outliers' => $antesOutliers,
        'duracao_mediana_dias_uteis' => $mediana,
        'tarefas_por_dia_util_pessoa' => $mediana ? round(1 / $mediana, 4) : null,
        'confianca' => $confianca,
        'taxa_descartes' => round($taxaDescartes, 4),
        'descartados_hold' => $hold,
        'descartados_duracao_extrema' => $duracaoDescartada,
        'outliers_removidos' => $outliersRemovidos,
        'reaberturas_observadas' => $reaberturas,
    ];
}

function flow_planejamento_tipos_da_etapa(string $codigo): array
{
    return match ($codigo) {
        'CADERNO_FILTRO', 'COMPOSICAO', 'POS_PRODUCAO' => ['FACHADA', 'EXTERNA', 'INTERNA', 'UNIDADE'],
        'MODELAGEM_FACHADA' => ['FACHADA'],
        'MODELAGEM_INTERNA' => ['EXTERNA', 'INTERNA', 'UNIDADE'],
        'FINALIZACAO_EXTERNA' => ['FACHADA', 'EXTERNA'],
        'FINALIZACAO_INTERNA' => ['INTERNA', 'UNIDADE'],
        'FINALIZACAO_PLANTA' => ['PLANTA'],
        default => [],
    };
}

/** Caderno + Filtro conta uma vez por imagem; Caderno prevalece como fonte. */
function flow_planejamento_itens_da_etapa(string $codigo, array $grupo): array
{
    if ($codigo !== 'CADERNO_FILTRO') {
        return $grupo;
    }

    usort($grupo, static function (array $a, array $b): int {
        $imagem = ((int) $a['imagem_id']) <=> ((int) $b['imagem_id']);
        if ($imagem !== 0) {
            return $imagem;
        }
        return ((int) $a['funcao_id']) <=> ((int) $b['funcao_id']);
    });
    $unicos = [];
    foreach ($grupo as $item) {
        $imagemId = (int) $item['imagem_id'];
        if (!isset($unicos[$imagemId]) || (int) $item['funcao_id'] === 1) {
            $unicos[$imagemId] = $item;
        }
    }
    return array_values($unicos);
}

function flow_planejamento_pessoas_alocadas(array $opcoes, string $codigo, bool $editavel = true): int
{
    if (!$editavel) {
        return 1;
    }
    $pessoas = (int) (($opcoes['pessoas_alocadas'][$codigo] ?? 1));
    return max(1, min(20, $pessoas));
}

function flow_planejamento_duracao_da_etapa(array $definicao, int $volume, int $pessoas, ?array $metrica): array
{
    $estrategia = $definicao['estrategia'] ?? '';
    if ($estrategia === 'JANELA_FIXA') {
        return [
            'duracao' => (int) $definicao['duracao_fixa_dias_uteis'],
            'metrica' => ['metodo' => 'REGRA_OPERACIONAL_FIXA', 'origem' => 'Regra operacional V1', 'confianca' => 'NAO_APLICAVEL'],
            'formula' => 'Janela fixa de ' . (int) $definicao['duracao_fixa_dias_uteis'] . ' dias úteis; não depende do volume nesta V1.',
        ];
    }
    if ($estrategia === 'OPERACIONAL_POR_TAREFA' || $estrategia === 'OPERACIONAL_POR_TAXA') {
        $taxa = (float) $definicao['tarefas_por_dia_pessoa'];
        $capacidade = $taxa * $pessoas;
        return [
            'duracao' => (int) ceil($volume / $capacidade),
            'metrica' => [
                'metodo' => 'REGRA_OPERACIONAL_POR_CAPACIDADE',
                'origem' => 'Regra operacional V1',
                'tarefas_por_dia_util_pessoa' => $taxa,
                'confianca' => 'NAO_APLICAVEL',
            ],
            'formula' => sprintf('%d ÷ (%.2f tarefas/dia/pessoa × %d pessoa%s) = %d dias úteis', $volume, $taxa, $pessoas, $pessoas === 1 ? '' : 's', (int) ceil($volume / $capacidade)),
        ];
    }
    if (($metrica['confianca'] ?? 'INSUFICIENTE') === 'INSUFICIENTE' || empty($metrica['tarefas_por_dia_util_pessoa'])) {
        return ['duracao' => null, 'metrica' => $metrica, 'formula' => 'Histórico insuficiente para estimar duração.'];
    }
    $taxa = (float) $metrica['tarefas_por_dia_util_pessoa'];
    $duracao = (int) ceil($volume / ($taxa * $pessoas));
    return [
        'duracao' => $duracao,
        'metrica' => $metrica,
        'formula' => sprintf('%d ÷ (%.4f tarefas/dia/pessoa × %d pessoa%s) = %d dias úteis', $volume, $taxa, $pessoas, $pessoas === 1 ? '' : 's', $duracao),
    ];
}

/** Dados reais de tarefa + funções planejadas ainda não materializadas. */
function flow_planejamento_carregar_itens_obra(mysqli $conn, int $obraId, ?array $imagemIds = null): array
{
    $filtroImagens = '';
    if ($imagemIds !== null) {
        $imagemIds = array_values(array_unique(array_filter(array_map('intval', $imagemIds))));
        if (!$imagemIds) {
            return [];
        }
        $filtroImagens = ' AND ico.idimagens_cliente_obra IN (' . implode(',', $imagemIds) . ')';
    }

    $sql = "SELECT fi.idfuncao_imagem AS tarefa_id, fi.imagem_id, fi.funcao_id, fi.status,
                   ico.tipo_imagem, ico.imagem_nome, 'TAREFA' AS origem
              FROM funcao_imagem fi
              JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
             WHERE ico.obra_id = ? {$filtroImagens}
            UNION ALL
            SELECT NULL AS tarefa_id, ifp.imagem_id, ifp.funcao_id, ifp.status,
                   ico.tipo_imagem, ico.imagem_nome, 'PLANEJAMENTO' AS origem
              FROM imagem_funcao_planejada ifp
              JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = ifp.imagem_id
             WHERE ico.obra_id = ?
               AND ifp.funcao_imagem_id IS NULL
               AND NOT EXISTS (
                    SELECT 1
                      FROM funcao_imagem fi_existente
                     WHERE fi_existente.imagem_id = ifp.imagem_id
                       AND fi_existente.funcao_id = ifp.funcao_id
               ) {$filtroImagens}";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Não foi possível carregar funções da obra: ' . $conn->error);
    }
    $stmt->bind_param('ii', $obraId, $obraId);
    $stmt->execute();
    $result = $stmt->get_result();
    $itens = [];
    while ($row = $result->fetch_assoc()) {
        $itens[] = $row;
    }
    $stmt->close();
    return $itens;
}

function flow_planejamento_contexto_obra(mysqli $conn, int $obraId): array
{
    $stmt = $conn->prepare('SELECT idobra, nomenclatura, liberar_modelagem FROM obra WHERE idobra = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Não foi possível carregar obra: ' . $conn->error);
    }
    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $obra = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$obra) {
        throw new InvalidArgumentException('Obra não encontrada: ' . $obraId);
    }
    return $obra;
}

function flow_planejamento_contexto_entrega(mysqli $conn, int $entregaId): array
{
    $stmt = $conn->prepare('SELECT id, obra_id, status_id, data_recebimento, data_prevista FROM entregas WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Não foi possível carregar entrega: ' . $conn->error);
    }
    $stmt->bind_param('i', $entregaId);
    $stmt->execute();
    $entrega = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$entrega) {
        throw new InvalidArgumentException('Entrega não encontrada: ' . $entregaId);
    }
    if ((int) $entrega['status_id'] !== 2) {
        throw new InvalidArgumentException('O motor desta etapa aceita apenas entrega R00 (status_id 2).');
    }
    return $entrega;
}

function flow_planejamento_imagens_entrega(mysqli $conn, int $entregaId): array
{
    $stmt = $conn->prepare('SELECT imagem_id FROM entregas_itens WHERE entrega_id = ?');
    $stmt->bind_param('i', $entregaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int) $row['imagem_id'];
    }
    $stmt->close();
    return $ids;
}

function flow_planejamento_calcular(array $itens, array $opcoes, callable $resolverProdutividade): array
{
    $dataInicio = (string) ($opcoes['data_inicio'] ?? '');
    if (!entregas_valid_date($dataInicio)) {
        throw new InvalidArgumentException('data_inicio deve estar no formato Y-m-d.');
    }
    $definicoes = flow_planejamento_definicoes_etapas();
    $deslocamentosEtapas = [];
    foreach ((array) ($opcoes['deslocamentos_etapas'] ?? []) as $codigo => $dias) {
        if (is_string($codigo) && isset($definicoes[$codigo]) && is_numeric($dias)) {
            $deslocamentosEtapas[$codigo] = max(0, min(60, (int) $dias));
        }
    }
    $porEtapa = array_fill_keys(array_keys($definicoes), []);
    $excecoes = [];
    $canceladasPorImagem = [];
    foreach ($itens as $item) {
        if (flow_planejamento_normalizar((string) ($item['status'] ?? '')) === 'cancelado') {
            $canceladasPorImagem[(int) ($item['imagem_id'] ?? 0)][(int) ($item['funcao_id'] ?? 0)] = true;
        }
    }

    foreach ($itens as $item) {
        $codigo = flow_planejamento_codigo_etapa($item);
        if (flow_planejamento_normalizar((string) ($item['status'] ?? '')) === 'cancelado') {
            continue;
        }
        if ($codigo === null) {
            $funcaoId = (int) ($item['funcao_id'] ?? 0);
            if ($funcaoId === 7) {
                continue; // Planta Humanizada operacional não é etapa gerencial.
            }
            $excecoes[] = ['codigo' => 'FUNCAO_OU_TIPO_NAO_CLASSIFICADO', 'imagem_id' => $item['imagem_id'] ?? null, 'funcao_id' => $funcaoId, 'tipo_imagem' => $item['tipo_imagem'] ?? null];
            continue;
        }
        $item['etapa'] = $codigo;
        $item['regra_classificacao'] = flow_planejamento_regra_classificacao($item, $codigo);
        $porEtapa[$codigo][] = $item;
        if (in_array($codigo, ['FINALIZACAO_EXTERNA', 'FINALIZACAO_INTERNA', 'FINALIZACAO_PLANTA', 'POS_PRODUCAO'], true)) {
            $imagemId = (int) ($item['imagem_id'] ?? 0);
            $canceladas = array_keys($canceladasPorImagem[$imagemId] ?? []);
            if (array_intersect($canceladas, [1, 8, 2, 3])) {
                $chave = 'ETAPA_POSTERIOR_ATIVA_APOS_CANCELAMENTO:' . $imagemId;
                if (!isset($excecoes[$chave])) {
                    $excecoes[$chave] = ['codigo' => 'ETAPA_POSTERIOR_ATIVA_APOS_CANCELAMENTO', 'imagem_id' => $imagemId, 'etapa' => $codigo, 'funcoes_canceladas' => $canceladas];
                }
            }
        }
    }

    $ativos = [];
    foreach ($porEtapa as $codigo => $grupo) {
        $ativos[$codigo] = !empty($grupo) && $codigo !== 'FINALIZACAO_GLOBAL';
    }
    $ativos['FINALIZACAO_GLOBAL'] = $ativos['FINALIZACAO_EXTERNA'] || $ativos['FINALIZACAO_INTERNA'] || $ativos['FINALIZACAO_PLANTA'];

    $etapas = [];
    foreach ($definicoes as $codigo => $definicao) {
        $grupo = flow_planejamento_itens_da_etapa($codigo, $porEtapa[$codigo] ?? []);
        if (!empty($definicao['virtual'])) {
            $etapas[$codigo] = array_merge($definicao, ['codigo' => $codigo, 'volume' => 0, 'concluidas' => 0, 'nao_aplicavel' => !$ativos[$codigo]]);
            continue;
        }
        $volume = count($grupo);
        $concluidas = count(array_filter($grupo, static fn (array $item) => flow_planejamento_status_finalizado((string) ($item['status'] ?? ''))));
        $editavel = ($definicao['estrategia'] ?? '') !== 'JANELA_FIXA';
        $pessoas = flow_planejamento_pessoas_alocadas($opcoes, $codigo, $editavel);
        $metrica = $volume && ($definicao['estrategia'] ?? '') === 'HISTORICO_POR_PESSOA' ? $resolverProdutividade($codigo, $definicao, $grupo) : null;
        $calculo = $volume ? flow_planejamento_duracao_da_etapa($definicao, $volume, $pessoas, $metrica) : ['duracao' => null, 'metrica' => $metrica, 'formula' => null];
        $duracao = $calculo['duracao'];
        if ($volume && $duracao === null) {
            $excecoes[] = ['codigo' => 'HISTORICO_INSUFICIENTE', 'etapa' => $codigo, 'volume' => $volume];
        }
        $etapas[$codigo] = array_merge($definicao, [
            'codigo' => $codigo,
            'volume' => $volume,
            'concluidas' => $concluidas,
            'percentual_concluido' => $volume ? round(100 * $concluidas / $volume, 1) : 0.0,
            'itens' => $grupo,
            'metrica' => $calculo['metrica'],
            'pessoas_alocadas' => $pessoas,
            'capacidade_editavel' => $editavel,
            'estrategia_duracao' => $definicao['estrategia'] ?? null,
            'formula' => $calculo['formula'],
            'duracao_dias_uteis' => $duracao,
            'nao_aplicavel' => $volume === 0,
        ]);
    }

    // As dependências são calculadas após saber quais funções existem de fato.
    $ordem = ['CADERNO_FILTRO', 'MODELAGEM_FACHADA', 'MODELAGEM_INTERNA', 'COMPOSICAO', 'FINALIZACAO_EXTERNA', 'FINALIZACAO_INTERNA', 'FINALIZACAO_PLANTA', 'FINALIZACAO_GLOBAL', 'POS_PRODUCAO'];
    foreach ($ordem as $codigo) {
        $etapa = &$etapas[$codigo];
        if ($etapa['nao_aplicavel']) {
            $etapa['dependencias'] = [];
            unset($etapa);
            continue;
        }
        $dependencias = flow_planejamento_dependencias($codigo, $ativos);
        $etapa['dependencias'] = $dependencias;
        $limites = [];
        foreach ($dependencias as $dependencia) {
            if (empty($etapas[$dependencia]['limite'])) {
                $excecoes[] = ['codigo' => 'DEPENDENCIA_SEM_PREVISAO', 'etapa' => $codigo, 'dependencia' => $dependencia];
                continue;
            }
            $limites[] = $etapas[$dependencia]['limite'];
        }
        if (count($limites) !== count($dependencias)) {
            $etapa['inicio'] = null;
            $etapa['limite'] = null;
            unset($etapa);
            continue;
        }
        if (!empty($etapa['virtual'])) {
            $etapa['inicio'] = min(array_map(static fn (string $dep) => $etapas[$dep]['inicio'], $dependencias));
            $etapa['limite'] = max($limites);
            $etapa['duracao_dias_uteis'] = max(array_map(static fn (string $dep) => $etapas[$dep]['duracao_dias_uteis'], $dependencias));
            $etapa['volume'] = array_sum(array_map(static fn (string $dep) => $etapas[$dep]['volume'], $dependencias));
            unset($etapa);
            continue;
        }
        if ($etapa['duracao_dias_uteis'] === null) {
            $etapa['inicio'] = null;
            $etapa['limite'] = null;
            unset($etapa);
            continue;
        }
        $etapa['inicio'] = $limites ? max($limites) : $dataInicio;
        $deslocamento = (int) ($deslocamentosEtapas[$codigo] ?? 0);
        if ($deslocamento > 0) {
            // Intervenção hipotética: a propagação seguinte permanece no
            // mesmo grafo canônico, pois os dependentes usam este limite.
            $etapa['inicio'] = flow_planejamento_adicionar_dias_uteis($etapa['inicio'], $deslocamento);
        }
        $etapa['limite'] = flow_planejamento_adicionar_dias_uteis($etapa['inicio'], $etapa['duracao_dias_uteis']);
        unset($etapa);
    }

    $fim = null;
    foreach ($etapas as $etapa) {
        if (!empty($etapa['limite']) && ($fim === null || $etapa['limite'] > $fim)) {
            $fim = $etapa['limite'];
        }
    }
    $dataEntrega = isset($opcoes['data_entrega']) && entregas_valid_date((string) $opcoes['data_entrega']) ? (string) $opcoes['data_entrega'] : null;
    $margem = ($fim && $dataEntrega) ? flow_planejamento_dias_uteis_entre($fim, $dataEntrega) : null;
    if ($margem !== null && $dataEntrega < $fim) {
        $margem = -flow_planejamento_dias_uteis_entre($dataEntrega, $fim);
    }
    $status = !empty($opcoes['desatualizado']) ? 'DESATUALIZADO' : ($fim === null ? 'SEM_PREVISAO_CONFIAVEL' : ($margem === null ? 'SEM_PRAZO_R00' : ($margem < 0 ? 'INVIAVEL' : ($margem <= 2 ? 'ATENCAO' : 'VIAVEL'))));

    $criticas = [];
    $atual = !empty($etapas['POS_PRODUCAO']['limite']) ? 'POS_PRODUCAO' : null;
    while ($atual !== null && !isset($criticas[$atual])) {
        $criticas[$atual] = true;
        $dependencias = $etapas[$atual]['dependencias'] ?? [];
        if (!$dependencias) {
            break;
        }
        usort($dependencias, static fn (string $a, string $b) => strcmp((string) ($etapas[$b]['limite'] ?? ''), (string) ($etapas[$a]['limite'] ?? '')));
        $atual = $dependencias[0] ?? null;
    }
    foreach ($etapas as $codigo => &$etapa) {
        $etapa['caminho_critico'] = !empty($criticas[$codigo]);
    }
    unset($etapa);

    return [
        'obra_id' => $opcoes['obra_id'] ?? null,
        'entrega_id' => $opcoes['entrega_id'] ?? null,
        'data_inicio' => $dataInicio,
        'data_hoje' => entregas_valid_date((string) ($opcoes['data_hoje'] ?? '')) ? (string) $opcoes['data_hoje'] : date('Y-m-d'),
        'data_entrega' => $dataEntrega,
        'pessoas_alocadas' => $opcoes['pessoas_alocadas'] ?? [],
        'etapas' => array_values($etapas),
        'fim_previsto' => $fim,
        'margem_dias_uteis' => $margem,
        'status_plano' => $status,
        'excecoes' => array_values($excecoes),
    ];
}

function flow_planejamento_planejar_obra(mysqli $conn, int $obraId, array $opcoes = []): array
{
    $obra = flow_planejamento_contexto_obra($conn, $obraId);
    $itens = flow_planejamento_carregar_itens_obra($conn, $obraId, $opcoes['imagem_ids'] ?? null);
    $cache = [];
    $opcoes['obra_id'] = $obraId;
    $opcoes['liberar_modelagem'] = !empty($obra['liberar_modelagem']);
    $plano = flow_planejamento_calcular($itens, $opcoes, static function (string $codigo, array $definicao) use ($conn, &$cache): array {
        return flow_planejamento_estimar_produtividade($conn, (int) $definicao['funcao_id'], flow_planejamento_tipos_da_etapa($codigo), $cache);
    });
    $plano['obra'] = ['id' => (int) $obra['idobra'], 'nomenclatura' => $obra['nomenclatura']];
    return $plano;
}

function flow_planejamento_planejar_entrega(mysqli $conn, int $entregaId, array $opcoes = []): array
{
    $entrega = flow_planejamento_contexto_entrega($conn, $entregaId);
    $opcoes['imagem_ids'] = flow_planejamento_imagens_entrega($conn, $entregaId);
    $opcoes['data_inicio'] = $opcoes['data_inicio'] ?? $entrega['data_recebimento'];
    $opcoes['data_entrega'] = $opcoes['data_entrega'] ?? $entrega['data_prevista'];
    $opcoes['entrega_id'] = $entregaId;
    return flow_planejamento_planejar_obra($conn, (int) $entrega['obra_id'], $opcoes);
}

/*
 * Persistência do planejamento R00
 *
 * O motor acima continua puro: ele calcula uma proposta a partir dos dados
 * atuais. As funções abaixo separam essa proposta do plano oficial: cada
 * confirmação cria uma versão imutável e a primeira é o baseline.
 */
function flow_planejamento_tabelas_persistencia_disponiveis(mysqli $conn): bool
{
    static $disponivel = null;
    if ($disponivel !== null) {
        return $disponivel;
    }
    $necessarias = [
        'entrega_planejamento_producao',
        'entrega_planejamento_versao',
        'entrega_planejamento_funcao',
        'entrega_planejamento_evento',
    ];
    foreach ($necessarias as $tabela) {
        $stmt = $conn->prepare(
            'SELECT 1
               FROM information_schema.tables
              WHERE table_schema = DATABASE()
                AND table_name = ?
              LIMIT 1'
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

function flow_planejamento_json(array $valor): string
{
    $json = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Não foi possível serializar o planejamento.');
    }
    return $json;
}

function flow_planejamento_estrutura_entrega(mysqli $conn, int $entregaId): array
{
    $entrega = flow_planejamento_contexto_entrega($conn, $entregaId);
    $stmt = $conn->prepare(
        'SELECT ei.imagem_id, ico.tipo_imagem
           FROM entregas_itens ei
           JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = ei.imagem_id
          WHERE ei.entrega_id = ?
          ORDER BY ei.imagem_id'
    );
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }
    $stmt->bind_param('i', $entregaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $imagens = [];
    while ($row = $result->fetch_assoc()) {
        $imagens[(int) $row['imagem_id']] = (string) ($row['tipo_imagem'] ?? '');
    }
    $result->free();
    $stmt->close();

    $itens = flow_planejamento_carregar_itens_obra($conn, (int) $entrega['obra_id'], array_keys($imagens));
    $linhas = [];
    foreach ($itens as $item) {
        $status = flow_planejamento_normalizar((string) ($item['status'] ?? ''));
        $linhas[] = [
            'imagem_id' => (int) ($item['imagem_id'] ?? 0),
            'funcao_id' => (int) ($item['funcao_id'] ?? 0),
            'tipo_imagem' => flow_planejamento_tipo_imagem((string) ($item['tipo_imagem'] ?? '')),
            // O motor distingue apenas cancelamento; andamento operacional
            // não é mudança estrutural e não pode invalidar o baseline.
            'status' => $status === 'cancelado' ? 'CANCELADO' : 'ATIVO',
        ];
    }
    usort($linhas, static fn (array $a, array $b): int => strcmp(flow_planejamento_json($a), flow_planejamento_json($b)));

    $volumes = [];
    $cadernoPorImagem = [];
    foreach ($itens as $item) {
        if (flow_planejamento_normalizar((string) ($item['status'] ?? '')) === 'cancelado') {
            continue;
        }
        $codigo = flow_planejamento_codigo_etapa($item);
        if ($codigo === null) {
            continue;
        }
        if ($codigo === 'CADERNO_FILTRO') {
            $cadernoPorImagem[(int) $item['imagem_id']] = true;
            continue;
        }
        $volumes[$codigo] = ($volumes[$codigo] ?? 0) + 1;
    }
    if ($cadernoPorImagem) {
        $volumes['CADERNO_FILTRO'] = count($cadernoPorImagem);
    }
    ksort($volumes);

    return [
        'entrega_id' => (int) $entrega['id'],
        'obra_id' => (int) $entrega['obra_id'],
        'data_inicio' => (string) $entrega['data_recebimento'],
        'prazo_r00' => (string) $entrega['data_prevista'],
        'imagens' => array_map(static fn (int $id, string $tipo): array => ['imagem_id' => $id, 'tipo_imagem' => flow_planejamento_tipo_imagem($tipo)], array_keys($imagens), array_values($imagens)),
        'funcoes' => $linhas,
        'volumes_por_etapa' => $volumes,
    ];
}

function flow_planejamento_fingerprint_entrega(mysqli $conn, int $entregaId): array
{
    $contexto = flow_planejamento_estrutura_entrega($conn, $entregaId);
    return ['fingerprint' => hash('sha256', flow_planejamento_json($contexto)), 'contexto' => $contexto];
}

function flow_planejamento_diferencas_estrutura(array $anterior, array $atual): array
{
    $mudancas = [];
    foreach (['data_inicio' => 'Início da produção', 'prazo_r00' => 'Prazo da R00'] as $campo => $rotulo) {
        if (($anterior[$campo] ?? null) !== ($atual[$campo] ?? null)) {
            $mudancas[] = ['tipo' => $campo, 'descricao' => $rotulo . ' alterado de ' . ($anterior[$campo] ?? '—') . ' para ' . ($atual[$campo] ?? '—') . '.'];
        }
    }
    $etapas = array_unique(array_merge(array_keys($anterior['volumes_por_etapa'] ?? []), array_keys($atual['volumes_por_etapa'] ?? [])));
    sort($etapas);
    foreach ($etapas as $etapa) {
        $antes = (int) (($anterior['volumes_por_etapa'][$etapa] ?? 0));
        $depois = (int) (($atual['volumes_por_etapa'][$etapa] ?? 0));
        if ($antes !== $depois) {
            $mudancas[] = ['tipo' => 'volume', 'etapa' => $etapa, 'antes' => $antes, 'depois' => $depois, 'descricao' => $etapa . ': ' . ($depois - $antes >= 0 ? '+' : '') . ($depois - $antes) . ' tarefa(s).'];
        }
    }
    if (($anterior['imagens'] ?? []) !== ($atual['imagens'] ?? [])) {
        $mudancas[] = ['tipo' => 'imagens', 'descricao' => 'A composição de imagens da R00 foi alterada.'];
    }
    if (($anterior['funcoes'] ?? []) !== ($atual['funcoes'] ?? [])) {
        $mudancas[] = ['tipo' => 'funcoes', 'descricao' => 'As funções ou tipos de imagem considerados pelo plano foram alterados.'];
    }
    return $mudancas;
}

function flow_planejamento_registrar_evento(mysqli $conn, int $planejamentoId, int $entregaId, string $tipo, ?int $versaoId = null, ?int $atorId = null, ?string $motivoCodigo = null, ?string $motivoObservacao = null, array $metadados = []): void
{
    $json = $metadados ? flow_planejamento_json($metadados) : null;
    $stmt = $conn->prepare(
        'INSERT INTO entrega_planejamento_evento
         (planejamento_id, versao_id, entrega_id, tipo, motivo_codigo, motivo_observacao, ator_colaborador_id, metadados_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }
    $stmt->bind_param('iiisssis', $planejamentoId, $versaoId, $entregaId, $tipo, $motivoCodigo, $motivoObservacao, $atorId, $json);
    if (!$stmt->execute()) {
        $erro = $stmt->error;
        $stmt->close();
        throw new RuntimeException($erro);
    }
    $stmt->close();
}

function flow_planejamento_garantir_rascunho(mysqli $conn, int $entregaId, ?int $atorId = null): ?array
{
    if (!flow_planejamento_tabelas_persistencia_disponiveis($conn)) {
        return null;
    }
    $fingerprint = flow_planejamento_fingerprint_entrega($conn, $entregaId);
    $stmt = $conn->prepare(
        "INSERT INTO entrega_planejamento_producao (entrega_id, estado, ultimo_fingerprint)
         VALUES (?, 'RASCUNHO', ?)
         ON DUPLICATE KEY UPDATE atualizado_em = atualizado_em"
    );
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }
    $hash = $fingerprint['fingerprint'];
    $stmt->bind_param('is', $entregaId, $hash);
    if (!$stmt->execute()) {
        $erro = $stmt->error;
        $stmt->close();
        throw new RuntimeException($erro);
    }
    $criado = $stmt->affected_rows === 1;
    $stmt->close();
    $stmtPlano = $conn->prepare('SELECT id, estado, lock_version FROM entrega_planejamento_producao WHERE entrega_id = ? LIMIT 1');
    $stmtPlano->bind_param('i', $entregaId);
    $stmtPlano->execute();
    $plano = $stmtPlano->get_result()->fetch_assoc();
    $stmtPlano->close();
    if ($criado && $plano) {
        flow_planejamento_registrar_evento($conn, (int) $plano['id'], $entregaId, 'RASCUNHO_CRIADO', null, $atorId, null, null, ['fingerprint' => $hash]);
    }
    return $plano ?: null;
}

function flow_planejamento_snapshot(array $plano): array
{
    $snapshot = $plano;
    if (isset($snapshot['etapas']) && is_array($snapshot['etapas'])) {
        foreach ($snapshot['etapas'] as &$etapa) {
            unset($etapa['itens']);
        }
        unset($etapa);
    }
    return $snapshot;
}

function flow_planejamento_carregar_raiz(mysqli $conn, int $entregaId, bool $forUpdate = false): ?array
{
    $sql = 'SELECT * FROM entrega_planejamento_producao WHERE entrega_id = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }
    $stmt->bind_param('i', $entregaId);
    $stmt->execute();
    $raiz = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $raiz;
}

function flow_planejamento_carregar_versao(mysqli $conn, int $versaoId): ?array
{
    $stmt = $conn->prepare('SELECT * FROM entrega_planejamento_versao WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }
    $stmt->bind_param('i', $versaoId);
    $stmt->execute();
    $versao = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $versao;
}

function flow_planejamento_marcar_desatualizado(mysqli $conn, int $entregaId, ?int $atorId = null): array
{
    if (!flow_planejamento_tabelas_persistencia_disponiveis($conn)) {
        return ['alterado' => false, 'motivos' => []];
    }
    $raiz = flow_planejamento_carregar_raiz($conn, $entregaId);
    if (!$raiz || empty($raiz['versao_atual_id']) || !in_array($raiz['estado'], ['CONFIRMADO', 'REPLANEJAMENTO', 'DESATUALIZADO'], true)) {
        return ['alterado' => false, 'motivos' => []];
    }
    $versao = flow_planejamento_carregar_versao($conn, (int) $raiz['versao_atual_id']);
    $atual = flow_planejamento_fingerprint_entrega($conn, $entregaId);
    if (!$versao || hash_equals((string) $versao['fingerprint'], $atual['fingerprint'])) {
        return ['alterado' => false, 'motivos' => []];
    }
    $anterior = json_decode((string) $versao['contexto_fingerprint_json'], true) ?: [];
    $motivos = flow_planejamento_diferencas_estrutura($anterior, $atual['contexto']);
    $stmt = $conn->prepare("UPDATE entrega_planejamento_producao SET estado = 'DESATUALIZADO', atualizado_em = NOW() WHERE id = ? AND estado <> 'DESATUALIZADO'");
    $planejamentoId = (int) $raiz['id'];
    $stmt->bind_param('i', $planejamentoId);
    $stmt->execute();
    $alterado = $stmt->affected_rows > 0;
    $stmt->close();
    if ($alterado) {
        flow_planejamento_registrar_evento($conn, $planejamentoId, $entregaId, 'PLANO_DESATUALIZADO', (int) $raiz['versao_atual_id'], $atorId, null, null, ['motivos' => $motivos, 'fingerprint_atual' => $atual['fingerprint']]);
    }
    return ['alterado' => $alterado, 'motivos' => $motivos];
}

function flow_planejamento_marcar_desatualizado_por_imagens(mysqli $conn, array $imagemIds, ?int $atorId = null): void
{
    if (!flow_planejamento_tabelas_persistencia_disponiveis($conn)) {
        return;
    }
    $imagemIds = array_values(array_unique(array_filter(array_map('intval', $imagemIds))));
    if (!$imagemIds) {
        return;
    }
    $lista = implode(',', $imagemIds);
    $resultado = $conn->query(
        "SELECT DISTINCT e.id
           FROM entregas e
           JOIN entregas_itens ei ON ei.entrega_id = e.id
          WHERE e.status_id = 2 AND ei.imagem_id IN ({$lista})"
    );
    if (!$resultado) {
        throw new RuntimeException($conn->error);
    }
    while ($row = $resultado->fetch_assoc()) {
        flow_planejamento_marcar_desatualizado($conn, (int) $row['id'], $atorId);
    }
    $resultado->free();
}

function flow_planejamento_historico(mysqli $conn, int $entregaId): array
{
    if (!flow_planejamento_tabelas_persistencia_disponiveis($conn)) {
        return [];
    }
    $stmt = $conn->prepare(
        'SELECT v.id, v.numero, v.tipo, v.vigente, v.fim_previsto, v.margem_dias_uteis, v.status_plano,
                v.confirmado_em, c.nome_colaborador AS confirmado_por
           FROM entrega_planejamento_producao p
           JOIN entrega_planejamento_versao v ON v.planejamento_id = p.id
      LEFT JOIN colaborador c ON c.idcolaborador = v.confirmado_por_colaborador_id
          WHERE p.entrega_id = ?
          ORDER BY v.numero DESC'
    );
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }
    $stmt->bind_param('i', $entregaId);
    $stmt->execute();
    $historico = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $historico;
}

function flow_planejamento_carregar_para_interface(mysqli $conn, int $entregaId, array $opcoes = []): array
{
    $fingerprint = flow_planejamento_fingerprint_entrega($conn, $entregaId);
    $persistenciaDisponivel = flow_planejamento_tabelas_persistencia_disponiveis($conn);
    $meta = ['estado' => 'RASCUNHO', 'lock_version' => 0, 'historico' => []];
    if ($persistenciaDisponivel) {
        $raiz = flow_planejamento_carregar_raiz($conn, $entregaId);
        if ($raiz) {
            $estado = (string) $raiz['estado'];
            $desatualizacao = flow_planejamento_marcar_desatualizado($conn, $entregaId);
            if ($desatualizacao['alterado']) {
                $raiz = flow_planejamento_carregar_raiz($conn, $entregaId) ?: $raiz;
                $estado = 'DESATUALIZADO';
            }
            $meta = [
                'id' => (int) $raiz['id'],
                'estado' => $estado,
                'lock_version' => (int) $raiz['lock_version'],
                'versao_atual_id' => $raiz['versao_atual_id'] ? (int) $raiz['versao_atual_id'] : null,
                'baseline_versao_id' => $raiz['baseline_versao_id'] ? (int) $raiz['baseline_versao_id'] : null,
                'historico' => flow_planejamento_historico($conn, $entregaId),
                'motivos_desatualizacao' => $desatualizacao['motivos'],
            ];
            // Consulta do plano confirmado lê somente o snapshot persistido;
            // o motor/histórico fica reservado para rascunho e replanejamento.
            if (!empty($raiz['versao_atual_id']) && empty($opcoes['replanejar'])) {
                $versao = flow_planejamento_carregar_versao($conn, (int) $raiz['versao_atual_id']);
                $oficial = $versao ? json_decode((string) $versao['snapshot_json'], true) : null;
                if (is_array($oficial)) {
                    $oficial['fingerprint'] = (string) ($versao['fingerprint'] ?? '');
                    $oficial['fonte'] = 'VERSAO_CONFIRMADA';
                    $oficial['persistencia_disponivel'] = true;
                    $oficial['planejamento'] = $meta;
                    if ($estado === 'DESATUALIZADO') {
                        $oficial['status_plano'] = 'DESATUALIZADO';
                    }
                    return $oficial;
                }
            }
        }
    }
    $simulacao = flow_planejamento_planejar_entrega($conn, $entregaId, $opcoes);
    $simulacao['fingerprint'] = $fingerprint['fingerprint'];
    $simulacao['contexto_fingerprint'] = $fingerprint['contexto'];
    $simulacao['fonte'] = 'SIMULACAO';
    $simulacao['persistencia_disponivel'] = $persistenciaDisponivel;
    $simulacao['planejamento'] = $meta;
    return $simulacao;
}

function flow_planejamento_persistir_confirmacao(mysqli $conn, int $entregaId, array $pessoas, string $fingerprintEsperado, int $lockVersionEsperado, ?int $atorId, bool $replanejar, ?string $motivoCodigo, ?string $motivoObservacao, array $deslocamentosEtapas = [], array $metadadosEvento = [], bool $gerenciarTransacao = true): array
{
    if (!flow_planejamento_tabelas_persistencia_disponiveis($conn)) {
        throw new RuntimeException('A migration do planejamento de produção ainda não foi aplicada.');
    }
    if (!improov_usuario_eh_gestor_sidebar($conn)) {
        throw new RuntimeException('Você não tem permissão para confirmar ou replanejar este plano.');
    }
    $entrega = flow_planejamento_contexto_entrega($conn, $entregaId);
    if (!improov_usuario_pode_acessar_obra($conn, (int) $entrega['obra_id'])) {
        throw new RuntimeException('Sem permissão para acessar esta R00.');
    }
    $motivoCodigo = $motivoCodigo !== null ? strtoupper(trim($motivoCodigo)) : null;
    $motivoObservacao = $motivoObservacao !== null ? trim($motivoObservacao) : null;
    if ($motivoObservacao !== null && mb_strlen($motivoObservacao) > 500) {
        throw new InvalidArgumentException('A observação do motivo aceita até 500 caracteres.');
    }

    if ($gerenciarTransacao) {
        $conn->begin_transaction();
    }
    try {
        $raiz = flow_planejamento_carregar_raiz($conn, $entregaId, true);
        $raizCriadaAgora = false;
        if (!$raiz) {
            $hashInicial = flow_planejamento_fingerprint_entrega($conn, $entregaId)['fingerprint'];
            $stmtCriar = $conn->prepare("INSERT INTO entrega_planejamento_producao (entrega_id, estado, ultimo_fingerprint) VALUES (?, 'RASCUNHO', ?)");
            $stmtCriar->bind_param('is', $entregaId, $hashInicial);
            $stmtCriar->execute();
            $stmtCriar->close();
            $raiz = flow_planejamento_carregar_raiz($conn, $entregaId, true);
            $raizCriadaAgora = true;
            flow_planejamento_registrar_evento($conn, (int) $raiz['id'], $entregaId, 'RASCUNHO_CRIADO', null, $atorId, null, null, ['fingerprint' => $hashInicial]);
        }
        if ($lockVersionEsperado !== (int) $raiz['lock_version'] && !($raizCriadaAgora && $lockVersionEsperado === 0)) {
            throw new RuntimeException('O plano foi alterado por outro gestor. Recarregue a revisão antes de confirmar.');
        }
        $atual = flow_planejamento_fingerprint_entrega($conn, $entregaId);
        if (!hash_equals($fingerprintEsperado, $atual['fingerprint'])) {
            throw new RuntimeException('A R00 mudou desde que este cálculo foi aberto. Recalcule e revise o plano antes de confirmar.');
        }
        $temVersao = !empty($raiz['versao_atual_id']);
        if ($temVersao && !$replanejar) {
            throw new RuntimeException('Já existe um plano confirmado. Use Replanejar para criar uma nova versão sem alterar o baseline.');
        }
        if ($temVersao && (!$motivoCodigo || ($motivoCodigo === 'OUTRO' && !$motivoObservacao))) {
            throw new InvalidArgumentException('Informe o motivo do replanejamento.');
        }
        $plano = flow_planejamento_planejar_entrega($conn, $entregaId, [
            'pessoas_alocadas' => $pessoas,
            'deslocamentos_etapas' => $deslocamentosEtapas,
        ]);
        $numero = 1;
        if ($temVersao) {
            $resNumero = $conn->query('SELECT MAX(numero) AS numero FROM entrega_planejamento_versao WHERE planejamento_id = ' . (int) $raiz['id']);
            $numero = ((int) ($resNumero->fetch_assoc()['numero'] ?? 0)) + 1;
            $resNumero->free();
            $stmtDesativar = $conn->prepare('UPDATE entrega_planejamento_versao SET vigente = 0, vigente_token = NULL WHERE planejamento_id = ? AND vigente = 1');
            $planejamentoId = (int) $raiz['id'];
            $stmtDesativar->bind_param('i', $planejamentoId);
            $stmtDesativar->execute();
            $stmtDesativar->close();
        }
        $tipo = $temVersao ? 'REPLANEJAMENTO' : 'BASELINE';
        $snapshot = flow_planejamento_json(flow_planejamento_snapshot($plano));
        $contexto = flow_planejamento_json($atual['contexto']);
        $statusPlano = (string) $plano['status_plano'];
        $fingerprintAtual = (string) $atual['fingerprint'];
        $fim = $plano['fim_previsto'] ?: null;
        $margem = $plano['margem_dias_uteis'];
        $prazo = $plano['data_entrega'] ?: null;
        $inicio = $plano['data_inicio'];
        $vigenteToken = 'VIGENTE';
        $planejamentoId = (int) $raiz['id'];
        $stmtVersao = $conn->prepare(
            'INSERT INTO entrega_planejamento_versao
             (planejamento_id, numero, tipo, vigente, vigente_token, fingerprint, data_inicio, prazo_r00, fim_previsto, margem_dias_uteis, status_plano, motivo_codigo, motivo_observacao, confirmado_por_colaborador_id, snapshot_json, contexto_fingerprint_json)
             VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmtVersao) {
            throw new RuntimeException($conn->error);
        }
        $stmtVersao->bind_param('iissssssisssiss', $planejamentoId, $numero, $tipo, $vigenteToken, $fingerprintAtual, $inicio, $prazo, $fim, $margem, $statusPlano, $motivoCodigo, $motivoObservacao, $atorId, $snapshot, $contexto);
        if (!$stmtVersao->execute()) {
            throw new RuntimeException($stmtVersao->error);
        }
        $versaoId = (int) $stmtVersao->insert_id;
        $stmtVersao->close();

        $stmtEtapa = $conn->prepare(
            'INSERT INTO entrega_planejamento_funcao
             (versao_id, codigo_etapa, ordem_apresentacao, nome_etapa, volume, pessoas_alocadas, capacidade_editavel, estrategia_duracao, produtividade_json, formula_calculo, duracao_dias_uteis, data_inicio, data_limite, dependencias_json, confianca, origem_calculo, caminho_critico, metadados_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmtEtapa) {
            throw new RuntimeException($conn->error);
        }
        foreach ($plano['etapas'] as $ordem => $etapa) {
            $codigo = (string) $etapa['codigo'];
            $nome = (string) $etapa['nome'];
            $volume = (int) $etapa['volume'];
            // Marcos virtuais, como FINALIZACAO_GLOBAL, não recebem pessoas
            // nem métrica própria; persistimos o valor neutro 1.
            $pessoasEtapa = (int) ($etapa['pessoas_alocadas'] ?? 1);
            $editavel = !empty($etapa['capacidade_editavel']) ? 1 : 0;
            $estrategia = $etapa['estrategia_duracao'] ?? null;
            $metrica = is_array($etapa['metrica'] ?? null) ? $etapa['metrica'] : [];
            $produtividade = $metrica ? flow_planejamento_json($metrica) : null;
            $formula = $etapa['formula'] ?? null;
            $duracao = $etapa['duracao_dias_uteis'] ?? null;
            $inicioEtapa = $etapa['inicio'] ?? null;
            $limite = $etapa['limite'] ?? null;
            $dependencias = flow_planejamento_json($etapa['dependencias'] ?? []);
            $confianca = $metrica['confianca'] ?? null;
            $origem = $metrica['origem'] ?? 'Motor de planejamento V1';
            $critico = !empty($etapa['caminho_critico']) ? 1 : 0;
            $metadados = flow_planejamento_json(['nao_aplicavel' => !empty($etapa['nao_aplicavel']), 'concluidas' => (int) ($etapa['concluidas'] ?? 0), 'regra' => $etapa['regra_classificacao'] ?? null]);
            $ordemBanco = $ordem + 1;
            $stmtEtapa->bind_param('isisiiisssisssssis', $versaoId, $codigo, $ordemBanco, $nome, $volume, $pessoasEtapa, $editavel, $estrategia, $produtividade, $formula, $duracao, $inicioEtapa, $limite, $dependencias, $confianca, $origem, $critico, $metadados);
            if (!$stmtEtapa->execute()) {
                throw new RuntimeException($stmtEtapa->error);
            }
        }
        $stmtEtapa->close();
        $novoLock = (int) $raiz['lock_version'] + 1;
        if ($temVersao) {
            $stmtRaiz = $conn->prepare("UPDATE entrega_planejamento_producao SET estado = 'CONFIRMADO', versao_atual_id = ?, ultimo_fingerprint = ?, lock_version = ? WHERE id = ?");
            $stmtRaiz->bind_param('isii', $versaoId, $fingerprintAtual, $novoLock, $planejamentoId);
        } else {
            $stmtRaiz = $conn->prepare("UPDATE entrega_planejamento_producao SET estado = 'CONFIRMADO', versao_atual_id = ?, baseline_versao_id = ?, ultimo_fingerprint = ?, lock_version = ? WHERE id = ?");
            $stmtRaiz->bind_param('iisii', $versaoId, $versaoId, $fingerprintAtual, $novoLock, $planejamentoId);
        }
        if (!$stmtRaiz->execute()) {
            throw new RuntimeException($stmtRaiz->error);
        }
        $stmtRaiz->close();
        flow_planejamento_registrar_evento($conn, $planejamentoId, $entregaId, $temVersao ? 'REPLANEJAMENTO_CONFIRMADO' : 'PLANO_CONFIRMADO', $versaoId, $atorId, $motivoCodigo, $motivoObservacao, array_merge(['fingerprint' => $atual['fingerprint'], 'numero' => $numero], $metadadosEvento));
        if ($gerenciarTransacao) {
            $conn->commit();
        }
        $plano['fingerprint'] = $atual['fingerprint'];
        $plano['fonte'] = 'VERSAO_CONFIRMADA';
        $plano['persistencia_disponivel'] = true;
        $plano['planejamento'] = ['id' => $planejamentoId, 'estado' => 'CONFIRMADO', 'lock_version' => $novoLock, 'versao_atual_id' => $versaoId, 'baseline_versao_id' => $temVersao ? (int) $raiz['baseline_versao_id'] : $versaoId, 'historico' => flow_planejamento_historico($conn, $entregaId)];
        return $plano;
    } catch (Throwable $erro) {
        if ($gerenciarTransacao) {
            $conn->rollback();
        }
        throw $erro;
    }
}
