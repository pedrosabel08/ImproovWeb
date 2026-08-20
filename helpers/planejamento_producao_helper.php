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
    if ($funcaoId === 2 && in_array($tipo, ['FACHADA', 'EXTERNA'], true)) {
        return 'MODELAGEM_FACHADA';
    }
    if ($funcaoId === 2 && in_array($tipo, ['INTERNA', 'UNIDADE'], true)) {
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
        return $tipo === 'FACHADA'
            ? 'FUNCAO_MODELAGEM_EM_TIPO_FACHADA'
            : 'FUNCAO_MODELAGEM_EM_TIPO_IMAGEM_EXTERNA_AGREGADA_NA_FRENTE_EXTERNA';
    }
    if ($codigo === 'MODELAGEM_INTERNA') {
        return 'FUNCAO_MODELAGEM_EM_TIPO_INTERNO_OU_UNIDADE';
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
        'MODELAGEM_FACHADA', 'FINALIZACAO_EXTERNA' => ['FACHADA', 'EXTERNA'],
        'MODELAGEM_INTERNA', 'FINALIZACAO_INTERNA' => ['INTERNA', 'UNIDADE'],
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
        $etapa =& $etapas[$codigo];
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
