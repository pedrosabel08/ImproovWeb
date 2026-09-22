<?php

require_once __DIR__ . '/planejamento_producao_helper.php';
require_once __DIR__ . '/tarefa_planejamento_contexto_helper.php';
require_once __DIR__ . '/unidade_trabalho_helper.php';

if (!defined('FLOW_JANELA_ESTADO_NORMAL')) {
    define('FLOW_JANELA_ESTADO_NORMAL', 'NORMAL');
    define('FLOW_JANELA_ESTADO_EXCECAO', 'EXCECAO_OPERACIONAL');
    define('FLOW_JANELA_ESTADO_CONFLITO', 'CONFLITO_PLANEJAMENTO');
}

function flow_janela_tabela_existe(mysqli $conn, string $tabela): bool
{
    static $cache = [];
    $key = spl_object_id($conn) . ':' . $tabela;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    if (!$stmt) {
        return $cache[$key] = false;
    }
    $stmt->bind_param('s', $tabela);
    $stmt->execute();
    $cache[$key] = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $cache[$key];
}

function flow_janela_schema_disponivel(mysqli $conn): bool
{
    foreach (['janela_operacional_perfil', 'janela_operacional_motivo', 'janela_operacional_ciclo', 'janela_operacional_ciclo_item', 'janela_operacional_evento', 'janela_operacional_pausa'] as $tabela) {
        if (!flow_janela_tabela_existe($conn, $tabela)) {
            return false;
        }
    }
    return true;
}

function flow_janela_data_valida(?string $data): ?string
{
    $data = trim((string) $data);
    if ($data === '') {
        return null;
    }
    $data = substr($data, 0, 10);
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
    return $parsed && $parsed->format('Y-m-d') === $data ? $data : null;
}

function flow_janela_dias_hold_integrais(string $inicioData, string $retomadaData): int
{
    $inicioData = flow_janela_data_valida($inicioData);
    $retomadaData = flow_janela_data_valida($retomadaData);
    if (!$inicioData || !$retomadaData || $retomadaData <= $inicioData) {
        return 0;
    }
    $ultimoDiaIntegral = (new DateTimeImmutable($retomadaData))->modify('-1 day')->format('Y-m-d');
    return $ultimoDiaIntegral > $inicioData
        ? flow_planejamento_dias_uteis_entre($inicioData, $ultimoDiaIntegral)
        : 0;
}

/** Pura e testavel: conflito de planejamento sempre possui precedencia. */
function flow_janela_classificar(?string $previsao, ?string $limite, ?string $prazoNecessario): string
{
    $previsao = flow_janela_data_valida($previsao);
    $limite = flow_janela_data_valida($limite);
    $prazoNecessario = flow_janela_data_valida($prazoNecessario);
    if (!$previsao) {
        throw new InvalidArgumentException('Previsao de conclusao invalida.');
    }
    if ($prazoNecessario && $previsao > $prazoNecessario) {
        return FLOW_JANELA_ESTADO_CONFLITO;
    }
    if ($limite && $previsao > $limite) {
        return FLOW_JANELA_ESTADO_EXCECAO;
    }
    return FLOW_JANELA_ESTADO_NORMAL;
}

/**
 * Traduz a classificacao canonica existente para a matriz operacional.
 * Funcoes desconhecidas retornam null (default seguro: sem regra).
 */
function flow_janela_codigo_perfil(array $tarefa, ?string $tipoUnidade = null, bool $parSeparado = false): ?string
{
    if ($tipoUnidade === FLOW_UNIDADE_TIPO_MODELAGEM_COMPOSICAO) {
        return 'MODELAGEM_COMPOSICAO';
    }
    if ($tipoUnidade === 'CADERNO_FILTRO_LEGADO') {
        return 'CADERNO_FILTRO';
    }
    $funcaoId = (int) ($tarefa['funcao_id'] ?? 0);
    if ($funcaoId === 6) {
        return 'ALTERACAO';
    }
    if ($funcaoId === FLOW_FUNCAO_CADERNO && $parSeparado) {
        return 'CADERNO';
    }
    if ($funcaoId === FLOW_FUNCAO_FILTRO_ASSETS && $parSeparado) {
        return 'FILTRO_ASSETS';
    }
    $codigoPlanejamento = flow_planejamento_codigo_etapa($tarefa);
    $mapa = [
        'CADERNO_FILTRO' => 'CADERNO_FILTRO',
        'MODELAGEM_INTERNA' => 'MODELAGEM_COMUM',
        'MODELAGEM_FACHADA' => 'MODELAGEM_FACHADA',
        'COMPOSICAO' => 'COMPOSICAO',
        'FINALIZACAO_INTERNA' => 'FINALIZACAO_INTERNA',
        'FINALIZACAO_EXTERNA' => 'FINALIZACAO_EXTERNA',
        'FINALIZACAO_PLANTA' => 'FINALIZACAO_PLANTA',
        'POS_PRODUCAO' => 'POS_PRODUCAO',
    ];
    return $mapa[$codigoPlanejamento] ?? null;
}

function flow_janela_carregar_tarefa(mysqli $conn, int $funcaoImagemId, bool $forUpdate = false): ?array
{
    $sql = "SELECT fi.idfuncao_imagem, fi.imagem_id, fi.funcao_id, fi.colaborador_id, fi.status,
                   f.nome_funcao, ico.tipo_imagem, ico.subtipo_id, ico.imagem_nome, ico.obra_id
              FROM funcao_imagem fi
              JOIN funcao f ON f.idfuncao = fi.funcao_id
              JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
             WHERE fi.idfuncao_imagem = ? LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Nao foi possivel consultar a tarefa.');
    }
    $stmt->bind_param('i', $funcaoImagemId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function flow_janela_par_caderno_filtro(mysqli $conn, array $tarefa, bool $forUpdate = false): ?array
{
    if (!in_array((int) ($tarefa['funcao_id'] ?? 0), [FLOW_FUNCAO_CADERNO, FLOW_FUNCAO_FILTRO_ASSETS], true)) {
        return null;
    }
    $stmtSeparated = $conn->prepare("SELECT 1 FROM funcao_par_separado WHERE imagem_id = ? AND par_tipo = 'caderno_filtro' LIMIT 1");
    $imagemId = (int) $tarefa['imagem_id'];
    $stmtSeparated->bind_param('i', $imagemId);
    $stmtSeparated->execute();
    $separado = $stmtSeparated->get_result()->num_rows > 0;
    $stmtSeparated->close();
    if ($separado) {
        return ['separado' => true, 'membros' => [$tarefa]];
    }
    $sql = "SELECT fi.idfuncao_imagem, fi.imagem_id, fi.funcao_id, fi.colaborador_id, fi.status,
                   f.nome_funcao, ico.tipo_imagem, ico.subtipo_id, ico.imagem_nome, ico.obra_id
              FROM funcao_imagem fi
              JOIN funcao f ON f.idfuncao = fi.funcao_id
              JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
             WHERE fi.imagem_id = ? AND fi.funcao_id IN (1, 8) AND fi.colaborador_id = ?
             ORDER BY FIELD(fi.funcao_id, 1, 8)" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $conn->prepare($sql);
    $colaboradorId = (int) $tarefa['colaborador_id'];
    $stmt->bind_param('ii', $imagemId, $colaboradorId);
    $stmt->execute();
    $membros = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return count($membros) === 2 ? ['separado' => false, 'membros' => $membros] : ['separado' => true, 'membros' => [$tarefa]];
}

/** Resolve uma unidade e sua politica sem depender de funcao_imagem.prazo. */
function flow_janela_resolver_unidade(mysqli $conn, int $funcaoImagemId, bool $forUpdate = false, bool $agruparModelagemComposicao = false): array
{
    $tarefa = flow_janela_carregar_tarefa($conn, $funcaoImagemId, $forUpdate);
    if (!$tarefa) {
        throw new DomainException('Tarefa nao encontrada.');
    }

    $unidade = flow_unidade_modelagem_composicao_da_tarefa($conn, $funcaoImagemId, $forUpdate);
    if (!$unidade && $agruparModelagemComposicao && (int) $tarefa['funcao_id'] === FLOW_FUNCAO_MODELAGEM) {
        // A avaliacao transacional completa continua no servico de inicio.
        $stmt = $conn->prepare("SELECT fi.idfuncao_imagem, fi.imagem_id, fi.funcao_id, fi.colaborador_id, fi.status,
                                      f.nome_funcao, ico.tipo_imagem, ico.subtipo_id, ico.imagem_nome, ico.obra_id
                                 FROM funcao_imagem fi
                                 JOIN funcao f ON f.idfuncao = fi.funcao_id
                                 JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
                                WHERE fi.imagem_id = ? AND fi.funcao_id IN (2, 3)
                                ORDER BY FIELD(fi.funcao_id, 2, 3)" . ($forUpdate ? ' FOR UPDATE' : ''));
        $imagemId = (int) $tarefa['imagem_id'];
        $stmt->bind_param('i', $imagemId);
        $stmt->execute();
        $membros = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (count($membros) === 2) {
            $unidade = ['id' => null, 'tipo' => FLOW_UNIDADE_TIPO_MODELAGEM_COMPOSICAO, 'membros' => $membros];
        }
    }

    $tipo = null;
    $membros = [$tarefa];
    $unidadeId = null;
    $parSeparado = false;
    if ($unidade) {
        $tipo = FLOW_UNIDADE_TIPO_MODELAGEM_COMPOSICAO;
        $membros = [];
        foreach ($unidade['membros'] as $membro) {
            $completo = flow_janela_carregar_tarefa($conn, (int) $membro['idfuncao_imagem'], $forUpdate);
            if ($completo) {
                $membros[] = $completo;
            }
        }
        $unidadeId = isset($unidade['id']) ? (int) $unidade['id'] : null;
    } else {
        $par = flow_janela_par_caderno_filtro($conn, $tarefa, $forUpdate);
        if ($par) {
            $parSeparado = (bool) $par['separado'];
            $membros = $par['membros'];
            if (!$parSeparado) {
                $tipo = 'CADERNO_FILTRO_LEGADO';
            }
        }
    }

    $codigo = flow_janela_codigo_perfil($tarefa, $tipo, $parSeparado);
    $chave = $tipo === FLOW_UNIDADE_TIPO_MODELAGEM_COMPOSICAO && $unidadeId
        ? 'UT:' . $unidadeId
        : ($tipo === FLOW_UNIDADE_TIPO_MODELAGEM_COMPOSICAO
            ? 'MODELAGEM_COMPOSICAO:' . (int) $tarefa['imagem_id']
            : ($tipo === 'CADERNO_FILTRO_LEGADO'
                ? 'LEGACY_CADERNO_FILTRO:' . (int) $tarefa['imagem_id'] . ':' . (int) $tarefa['colaborador_id']
                : 'FUNCAO_IMAGEM:' . (int) $tarefa['idfuncao_imagem']));
    return [
        'chave_referencia' => $chave,
        'tipo_unidade' => $tipo ?: 'FUNCAO_IMAGEM',
        'unidade_trabalho_id' => $unidadeId,
        'tarefa_principal' => $tarefa,
        'membros' => array_values($membros),
        'perfil_codigo' => $codigo,
        'par_separado' => $parSeparado,
    ];
}

function flow_janela_perfil_vigente(mysqli $conn, ?string $codigo): ?array
{
    if (!$codigo || !flow_janela_schema_disponivel($conn)) {
        return null;
    }
    $stmt = $conn->prepare("SELECT id, codigo, versao, nome, aplica_regra, limite_dias_uteis
                              FROM janela_operacional_perfil
                             WHERE codigo = ? AND vigente = 1 AND vigente_token = 'VIGENTE'
                             LIMIT 1");
    $stmt->bind_param('s', $codigo);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    $row['id'] = (int) $row['id'];
    $row['versao'] = (int) $row['versao'];
    $row['aplica_regra'] = (bool) $row['aplica_regra'];
    $row['limite_dias_uteis'] = $row['limite_dias_uteis'] === null ? null : (int) $row['limite_dias_uteis'];
    return $row;
}

function flow_janela_prazo_necessario(mysqli $conn, array $unidade): array
{
    $contextos = flow_tarefa_contextos_planejamento_lote($conn, $unidade['membros']);
    $preferidos = $unidade['tipo_unidade'] === FLOW_UNIDADE_TIPO_MODELAGEM_COMPOSICAO ? [3, 2] : [8, 1];
    foreach ($preferidos as $funcaoId) {
        foreach ($unidade['membros'] as $membro) {
            if ((int) $membro['funcao_id'] !== $funcaoId) {
                continue;
            }
            $ctx = $contextos[(int) $membro['idfuncao_imagem']] ?? [];
            if (!empty($ctx['prazo_necessario'])) {
                return ['data' => $ctx['prazo_necessario'], 'versao_id' => $ctx['versao_id'] ?? null, 'contexto' => $ctx];
            }
        }
    }
    foreach ($contextos as $ctx) {
        if (!empty($ctx['prazo_necessario'])) {
            return ['data' => $ctx['prazo_necessario'], 'versao_id' => $ctx['versao_id'] ?? null, 'contexto' => $ctx];
        }
    }
    return ['data' => null, 'versao_id' => null, 'contexto' => reset($contextos) ?: null];
}

function flow_janela_avaliar(mysqli $conn, array $unidade, string $previsao, ?string $inicioData = null): array
{
    $previsao = flow_janela_data_valida($previsao);
    if (!$previsao) {
        throw new DomainException('Informe uma previsao de conclusao valida.');
    }
    $perfil = flow_janela_perfil_vigente($conn, $unidade['perfil_codigo']);
    $prazo = flow_janela_prazo_necessario($conn, $unidade);
    $inicioData = flow_janela_data_valida($inicioData ?: date('Y-m-d')) ?: date('Y-m-d');
    $aplica = $perfil && !empty($perfil['aplica_regra']);
    $limite = $aplica ? flow_planejamento_adicionar_dias_uteis($inicioData, (int) $perfil['limite_dias_uteis']) : null;
    // Sem janela, a avaliacao operacional permanece inerte; o conflito com o
    // prazo necessario continua independente e preserva a regra de planejamento.
    $estado = $aplica
        ? flow_janela_classificar($previsao, $limite, $prazo['data'])
        : flow_janela_classificar($previsao, null, $prazo['data']);
    return [
        'aplica_regra' => $aplica,
        'perfil' => $perfil,
        'perfil_codigo' => $unidade['perfil_codigo'],
        'limite_dias_uteis' => $aplica ? (int) $perfil['limite_dias_uteis'] : null,
        'inicio_data' => $inicioData,
        'limite_data' => $limite,
        'prazo_necessario' => $prazo['data'],
        'planejamento_versao_id' => $prazo['versao_id'],
        'previsao' => $previsao,
        'estado' => $estado,
        'exige_justificativa' => $estado !== FLOW_JANELA_ESTADO_NORMAL,
        'unidade' => [
            'chave' => $unidade['chave_referencia'],
            'tipo' => $unidade['tipo_unidade'],
            'membros' => array_map(static fn(array $m): int => (int) $m['idfuncao_imagem'], $unidade['membros']),
        ],
    ];
}

function flow_janela_motivos_ativos(mysqli $conn): array
{
    if (!flow_janela_tabela_existe($conn, 'janela_operacional_motivo')) {
        return [];
    }
    $result = $conn->query('SELECT codigo, label, exige_texto FROM janela_operacional_motivo WHERE ativo = 1 ORDER BY ordem, id');
    $rows = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = ['codigo' => $row['codigo'], 'label' => $row['label'], 'exige_texto' => (bool) $row['exige_texto']];
    }
    return $rows;
}

function flow_janela_validar_justificativa(mysqli $conn, string $estado, ?string $motivoCodigo, ?string $motivoTexto): ?array
{
    if ($estado === FLOW_JANELA_ESTADO_NORMAL) {
        return null;
    }
    $motivoCodigo = strtoupper(trim((string) $motivoCodigo));
    $stmt = $conn->prepare('SELECT id, codigo, label, exige_texto FROM janela_operacional_motivo WHERE codigo = ? AND ativo = 1 LIMIT 1');
    $stmt->bind_param('s', $motivoCodigo);
    $stmt->execute();
    $motivo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$motivo) {
        throw new DomainException('Selecione um motivo para a previsao informada.');
    }
    $texto = trim((string) $motivoTexto);
    if (!empty($motivo['exige_texto']) && $texto === '') {
        throw new DomainException('Detalhe a justificativa para o motivo selecionado.');
    }
    if (mb_strlen($texto) > 500) {
        throw new DomainException('A justificativa deve ter no maximo 500 caracteres.');
    }
    return [
        'id' => (int) $motivo['id'],
        'codigo' => $motivo['codigo'],
        'label' => $motivo['label'],
        'texto' => $texto !== '' ? $texto : null,
    ];
}

function flow_janela_ciclo_ativo_por_tarefa(mysqli $conn, int $funcaoImagemId, bool $forUpdate = false): ?array
{
    if (!flow_janela_schema_disponivel($conn)) {
        return null;
    }
    $sql = "SELECT c.* FROM janela_operacional_ciclo c
              JOIN janela_operacional_ciclo_item i ON i.ciclo_id = c.id
             WHERE i.funcao_imagem_id = ? AND c.ativo_token = 'ATIVO'
             ORDER BY c.id DESC LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $funcaoImagemId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function flow_janela_ultimo_ciclo_por_tarefa(mysqli $conn, int $funcaoImagemId, bool $forUpdate = false): ?array
{
    if (!flow_janela_schema_disponivel($conn)) {
        return null;
    }
    $sql = "SELECT c.* FROM janela_operacional_ciclo c
              JOIN janela_operacional_ciclo_item i ON i.ciclo_id = c.id
             WHERE i.funcao_imagem_id = ?
             ORDER BY c.id DESC LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $funcaoImagemId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function flow_janela_registrar_evento(mysqli $conn, int $cicloId, string $evento, array $dados = []): int
{
    $motivo = $dados['motivo'] ?? null;
    $detalhes = isset($dados['detalhes']) ? json_encode($dados['detalhes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $sql = 'INSERT INTO janela_operacional_evento
        (ciclo_id, evento, status_tarefa_anterior, status_tarefa_novo,
         estado_anterior, estado_novo, previsao_anterior, previsao_nova,
         prazo_necessario_snapshot, limite_data_snapshot, motivo_id, motivo_codigo_snapshot,
         motivo_label_snapshot, motivo_texto, responsavel_anterior_id, responsavel_novo_id,
         dias_uteis_consumidos, ator_colaborador_id, ator_usuario_id, detalhes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = $conn->prepare($sql);
    $estadoAnterior = $dados['estado_anterior'] ?? null;
    $estadoNovo = $dados['estado_novo'] ?? null;
    $statusAnterior = $dados['status_tarefa_anterior'] ?? null;
    $statusNovo = $dados['status_tarefa_novo'] ?? null;
    $previsaoAnterior = $dados['previsao_anterior'] ?? null;
    $previsaoNova = $dados['previsao_nova'] ?? null;
    $prazo = $dados['prazo_necessario'] ?? null;
    $limite = $dados['limite_data'] ?? null;
    $motivoId = $motivo['id'] ?? null;
    $motivoCodigo = $motivo['codigo'] ?? null;
    $motivoLabel = $motivo['label'] ?? null;
    $motivoTexto = $motivo['texto'] ?? null;
    $respAnterior = $dados['responsavel_anterior_id'] ?? null;
    $respNovo = $dados['responsavel_novo_id'] ?? null;
    $consumidos = $dados['dias_uteis_consumidos'] ?? null;
    $atorColaborador = $dados['ator_colaborador_id'] ?? null;
    $atorUsuario = $dados['ator_usuario_id'] ?? null;
    $stmt->bind_param(
        'isssssssssisssiiiiis',
        $cicloId,
        $evento,
        $statusAnterior,
        $statusNovo,
        $estadoAnterior,
        $estadoNovo,
        $previsaoAnterior,
        $previsaoNova,
        $prazo,
        $limite,
        $motivoId,
        $motivoCodigo,
        $motivoLabel,
        $motivoTexto,
        $respAnterior,
        $respNovo,
        $consumidos,
        $atorColaborador,
        $atorUsuario,
        $detalhes
    );
    $stmt->execute();
    $id = (int) $conn->insert_id;
    $stmt->close();
    return $id;
}

function flow_janela_proximo_numero_ciclo(mysqli $conn, string $chaveReferencia): int
{
    $stmt = $conn->prepare('SELECT COALESCE(MAX(numero_ciclo), 0) + 1 AS proximo FROM janela_operacional_ciclo WHERE chave_referencia = ? FOR UPDATE');
    $stmt->bind_param('s', $chaveReferencia);
    $stmt->execute();
    $numero = (int) ($stmt->get_result()->fetch_assoc()['proximo'] ?? 1);
    $stmt->close();
    return max(1, $numero);
}

function flow_janela_origem_por_evento(string $evento): string
{
    return match ($evento) {
        'TRANSFERENCIA_ENTRADA' => 'TRANSFERENCIA',
        'REABERTURA' => 'REABERTURA',
        default => 'PRIMEIRA_EXECUCAO',
    };
}

function flow_janela_criar_ciclo(
    mysqli $conn,
    array $unidade,
    array $avaliacao,
    ?array $motivo,
    ?int $atorColaboradorId,
    ?int $atorUsuarioId,
    ?int $cicloAnteriorId = null,
    string $eventoAbertura = 'PRIMEIRO_INICIO',
    array $detalhesEvento = []
): array
{
    if (empty($avaliacao['perfil'])) {
        return ['ciclo_id' => null, 'criado' => false];
    }
    $perfil = $avaliacao['perfil'];
    $inicioEm = date('Y-m-d H:i:s');
    $responsavel = (int) $unidade['tarefa_principal']['colaborador_id'];
    $numeroCiclo = flow_janela_proximo_numero_ciclo($conn, $unidade['chave_referencia']);
    $origemAbertura = $detalhesEvento['origem_abertura'] ?? flow_janela_origem_por_evento($eventoAbertura);
    $qualidadeDados = $detalhesEvento['qualidade_dados'] ?? 'CANONICO';
    $sql = "INSERT INTO janela_operacional_ciclo
        (chave_referencia, numero_ciclo, origem_abertura, qualidade_dados, ciclo_anterior_id, unidade_trabalho_id, perfil_id, perfil_codigo_snapshot,
         perfil_versao_snapshot, perfil_nome_snapshot, aplica_regra_snapshot, limite_dias_uteis_snapshot,
         inicio_em, limite_data_original, limite_data_atual, prazo_necessario_snapshot,
         planejamento_versao_id_snapshot, previsao_original, previsao_atual, estado_original, estado_atual,
         motivo_original_id, motivo_original_codigo, motivo_original_label, motivo_original_texto,
         responsavel_original_id, responsavel_atual_id, criado_por_colaborador_id, criado_por_usuario_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $chave = $unidade['chave_referencia'];
    $unidadeId = $unidade['unidade_trabalho_id'];
    $perfilId = (int) $perfil['id'];
    $perfilCodigo = $perfil['codigo'];
    $perfilVersao = (int) $perfil['versao'];
    $perfilNome = $perfil['nome'];
    $aplicaRegra = !empty($perfil['aplica_regra']) ? 1 : 0;
    $dias = $perfil['limite_dias_uteis'] === null ? null : (int) $perfil['limite_dias_uteis'];
    $limite = $avaliacao['limite_data'];
    $prazo = $avaliacao['prazo_necessario'];
    $versaoPlanejamento = $avaliacao['planejamento_versao_id'];
    $previsao = $avaliacao['previsao'];
    $estado = $avaliacao['estado'];
    $motivoId = $motivo['id'] ?? null;
    $motivoCodigo = $motivo['codigo'] ?? null;
    $motivoLabel = $motivo['label'] ?? null;
    $motivoTexto = $motivo['texto'] ?? null;
    $stmt->bind_param(
        'sissiiisisiissssissssisssiiii',
        $chave,
        $numeroCiclo,
        $origemAbertura,
        $qualidadeDados,
        $cicloAnteriorId,
        $unidadeId,
        $perfilId,
        $perfilCodigo,
        $perfilVersao,
        $perfilNome,
        $aplicaRegra,
        $dias,
        $inicioEm,
        $limite,
        $limite,
        $prazo,
        $versaoPlanejamento,
        $previsao,
        $previsao,
        $estado,
        $estado,
        $motivoId,
        $motivoCodigo,
        $motivoLabel,
        $motivoTexto,
        $responsavel,
        $responsavel,
        $atorColaboradorId,
        $atorUsuarioId
    );
    $stmt->execute();
    $cicloId = (int) $conn->insert_id;
    $stmt->close();

    $stmtItem = $conn->prepare('INSERT INTO janela_operacional_ciclo_item (ciclo_id, funcao_imagem_id, ordem) VALUES (?, ?, ?)');
    foreach (array_values($unidade['membros']) as $ordem => $membro) {
        $taskId = (int) $membro['idfuncao_imagem'];
        $posicao = $ordem + 1;
        $stmtItem->bind_param('iii', $cicloId, $taskId, $posicao);
        $stmtItem->execute();
    }
    $stmtItem->close();
    flow_janela_registrar_evento($conn, $cicloId, 'CICLO_CRIADO', [
        'status_tarefa_novo' => 'Em andamento',
        'estado_novo' => $estado,
        'previsao_nova' => $previsao,
        'prazo_necessario' => $prazo,
        'limite_data' => $limite,
        'motivo' => $motivo,
        'responsavel_novo_id' => $responsavel,
        'ator_colaborador_id' => $atorColaboradorId,
        'ator_usuario_id' => $atorUsuarioId,
        'detalhes' => array_merge(
            ['evento_origem' => $eventoAbertura, 'numero_ciclo' => $numeroCiclo, 'origem_abertura' => $origemAbertura, 'perfil_codigo' => $perfilCodigo, 'perfil_versao' => $perfilVersao, 'limite_dias_uteis' => $dias],
            $detalhesEvento
        ),
    ]);
    flow_janela_registrar_evento($conn, $cicloId, 'EXECUCAO_INICIADA', [
        'status_tarefa_novo' => 'Em andamento',
        'estado_novo' => $estado,
        'previsao_nova' => $previsao,
        'prazo_necessario' => $prazo,
        'limite_data' => $limite,
        'ator_colaborador_id' => $atorColaboradorId,
        'ator_usuario_id' => $atorUsuarioId,
        'detalhes' => ['origem_acionamento' => $detalhesEvento['origem_acionamento'] ?? null],
    ]);
    return ['ciclo_id' => $cicloId, 'criado' => true];
}

/** Cria o ciclo de ajuste assim que a revisão devolve a tarefa, ainda sem previsão. */
function flow_janela_criar_ciclo_aguardando_inicio(mysqli $conn, int $funcaoImagemId, ?int $atorColaboradorId, ?int $atorUsuarioId): array
{
    $existente = flow_janela_ciclo_ativo_por_tarefa($conn, $funcaoImagemId, true);
    if ($existente) {
        if ($existente['situacao'] === 'AGUARDANDO_INICIO') {
            return ['ciclo_id' => (int) $existente['id'], 'criado' => false];
        }
        throw new RuntimeException('Existe um ciclo operacional ativo para esta tarefa.');
    }
    $unidade = flow_janela_resolver_unidade($conn, $funcaoImagemId, true);
    $anterior = flow_janela_ultimo_ciclo_por_tarefa($conn, $funcaoImagemId, true);
    $perfil = flow_janela_perfil_vigente($conn, $unidade['perfil_codigo']);
    $numero = flow_janela_proximo_numero_ciclo($conn, $unidade['chave_referencia']);
    $responsavel = (int) $unidade['tarefa_principal']['colaborador_id'];
    $perfilId = $perfil['id'] ?? null;
    $perfilCodigo = $perfil['codigo'] ?? null;
    $perfilVersao = $perfil['versao'] ?? null;
    $perfilNome = $perfil['nome'] ?? null;
    $aplicaRegra = $perfil ? (!empty($perfil['aplica_regra']) ? 1 : 0) : null;
    $dias = $perfil['limite_dias_uteis'] ?? null;
    $anteriorId = $anterior ? (int) $anterior['id'] : null;
    $unidadeId = $unidade['unidade_trabalho_id'] ?: null;
    $stmt = $conn->prepare("INSERT INTO janela_operacional_ciclo
        (chave_referencia, numero_ciclo, origem_abertura, qualidade_dados, ciclo_anterior_id, unidade_trabalho_id,
         perfil_id, perfil_codigo_snapshot, perfil_versao_snapshot, perfil_nome_snapshot, aplica_regra_snapshot,
         limite_dias_uteis_snapshot, responsavel_original_id, responsavel_atual_id, situacao, ativo_token,
         criado_por_colaborador_id, criado_por_usuario_id)
         VALUES (?, ?, 'AJUSTE', 'CANONICO', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'AGUARDANDO_INICIO', 'ATIVO', ?, ?)");
    $stmt->bind_param('siiiisisiiiiii', $unidade['chave_referencia'], $numero, $anteriorId, $unidadeId, $perfilId, $perfilCodigo, $perfilVersao, $perfilNome, $aplicaRegra, $dias, $responsavel, $responsavel, $atorColaboradorId, $atorUsuarioId);
    $stmt->execute();
    $cicloId = (int) $conn->insert_id;
    $stmt->close();
    $item = $conn->prepare('INSERT INTO janela_operacional_ciclo_item (ciclo_id, funcao_imagem_id, ordem) VALUES (?, ?, ?)');
    foreach (array_values($unidade['membros']) as $ordem => $membro) {
        $tarefaId = (int) $membro['idfuncao_imagem'];
        $posicao = $ordem + 1;
        $item->bind_param('iii', $cicloId, $tarefaId, $posicao);
        $item->execute();
    }
    $item->close();
    flow_janela_registrar_evento($conn, $cicloId, 'CICLO_CRIADO', [
        'status_tarefa_anterior' => 'Em aprovação',
        'status_tarefa_novo' => 'Ajuste',
        'responsavel_novo_id' => $responsavel,
        'ator_colaborador_id' => $atorColaboradorId,
        'ator_usuario_id' => $atorUsuarioId,
        'detalhes' => ['numero_ciclo' => $numero, 'origem_abertura' => 'AJUSTE', 'situacao' => 'AGUARDANDO_INICIO'],
    ]);
    flow_janela_registrar_evento($conn, $cicloId, 'AJUSTE_RECEBIDO', [
        'status_tarefa_anterior' => 'Em aprovação',
        'status_tarefa_novo' => 'Ajuste',
        'ator_colaborador_id' => $atorColaboradorId,
        'ator_usuario_id' => $atorUsuarioId,
    ]);
    return ['ciclo_id' => $cicloId, 'criado' => true];
}

/**
 * Garante que um retorno para Ajuste deixe a tarefa em um ciclo pendente.
 *
 * Em fluxos canônicos, a execução anterior já foi encerrada ao entrar em
 * aprovação. A regularização abaixo protege rotas legadas que tenham gravado
 * o status sem encerrar o ciclo: esse ciclo não pode continuar aberto depois
 * de a tarefa retornar para Ajuste, pois bloquearia o próximo início.
 */
function flow_janela_garantir_ciclo_ajuste_aguardando_inicio(mysqli $conn, int $funcaoImagemId, ?int $atorColaboradorId, ?int $atorUsuarioId): array
{
    $existente = flow_janela_ciclo_ativo_por_tarefa($conn, $funcaoImagemId, true);
    if ($existente && $existente['situacao'] === 'AGUARDANDO_INICIO') {
        return ['ciclo_id' => (int) $existente['id'], 'criado' => false, 'regularizado' => false];
    }

    if ($existente) {
        flow_janela_encerrar_ciclo(
            $conn,
            $funcaoImagemId,
            'REGULARIZACAO_RETORNO_AJUSTE',
            $atorColaboradorId,
            $atorUsuarioId,
            'Em aprovação',
            'EXECUCAO_ENVIADA_APROVACAO'
        );
    }

    $criado = flow_janela_criar_ciclo_aguardando_inicio($conn, $funcaoImagemId, $atorColaboradorId, $atorUsuarioId);
    $criado['regularizado'] = $existente !== null;
    return $criado;
}

/** Ativa um ajuste sem reutilizar previsão nem horário do ciclo anterior. */
function flow_janela_ativar_ciclo_aguardando_inicio(mysqli $conn, int $funcaoImagemId, string $previsao, ?array $motivo, ?int $atorColaboradorId, ?int $atorUsuarioId, string $origemAcionamento = 'KANBAN'): array
{
    $ciclo = flow_janela_ciclo_ativo_por_tarefa($conn, $funcaoImagemId, true);
    if (!$ciclo || $ciclo['situacao'] !== 'AGUARDANDO_INICIO') {
        throw new DomainException('Não existe ciclo de ajuste aguardando início para esta tarefa.');
    }
    $unidade = flow_janela_resolver_unidade($conn, $funcaoImagemId, true);
    $avaliacao = flow_janela_avaliar($conn, $unidade, $previsao);
    $perfil = $avaliacao['perfil'];
    $inicio = date('Y-m-d H:i:s');
    $cicloId = (int) $ciclo['id'];
    $stmt = $conn->prepare('UPDATE janela_operacional_ciclo SET perfil_id=?, perfil_codigo_snapshot=?, perfil_versao_snapshot=?, perfil_nome_snapshot=?, aplica_regra_snapshot=?, limite_dias_uteis_snapshot=?, inicio_em=?, limite_data_original=?, limite_data_atual=?, prazo_necessario_snapshot=?, planejamento_versao_id_snapshot=?, previsao_original=?, previsao_atual=?, estado_original=?, estado_atual=?, motivo_original_id=?, motivo_original_codigo=?, motivo_original_label=?, motivo_original_texto=?, situacao=\'ATIVO\', qualidade_dados=? WHERE id=?');
    $perfilId = $perfil['id'] ?? null; $codigo = $perfil['codigo'] ?? null; $versao = $perfil['versao'] ?? null; $nome = $perfil['nome'] ?? null;
    $aplica = !empty($perfil['aplica_regra']) ? 1 : 0; $dias = $perfil['limite_dias_uteis'] ?? null;
    $limite = $avaliacao['limite_data']; $prazoNecessario = $avaliacao['prazo_necessario']; $versaoPlanejamento = $avaliacao['planejamento_versao_id']; $estado = $avaliacao['estado'];
    $motivoId = $motivo['id'] ?? null; $motivoCodigo = $motivo['codigo'] ?? null; $motivoLabel = $motivo['label'] ?? null; $motivoTexto = $motivo['texto'] ?? null; $qualidade = $origemAcionamento === 'FALLBACK_ENVIO_APROVACAO' ? 'REGULARIZADO_FALLBACK' : 'CANONICO';
    $stmt->bind_param('isisiissssissssissssi', $perfilId, $codigo, $versao, $nome, $aplica, $dias, $inicio, $limite, $limite, $prazoNecessario, $versaoPlanejamento, $previsao, $previsao, $estado, $estado, $motivoId, $motivoCodigo, $motivoLabel, $motivoTexto, $qualidade, $cicloId);
    $stmt->execute(); $stmt->close();
    flow_janela_registrar_evento($conn, $cicloId, 'EXECUCAO_INICIADA', [
        'status_tarefa_anterior' => 'Ajuste', 'status_tarefa_novo' => 'Em andamento', 'estado_novo' => $estado,
        'previsao_nova' => $previsao, 'prazo_necessario' => $prazoNecessario, 'limite_data' => $limite,
        'motivo' => $motivo, 'ator_colaborador_id' => $atorColaboradorId, 'ator_usuario_id' => $atorUsuarioId,
        'detalhes' => ['origem_acionamento' => $origemAcionamento],
    ]);
    return ['ciclo_id' => $cicloId, 'avaliacao' => $avaliacao];
}

function flow_janela_atualizar_previsao(mysqli $conn, int $funcaoImagemId, string $previsao, ?string $motivoCodigo, ?string $motivoTexto, ?int $atorColaboradorId, ?int $atorUsuarioId): array
{
    $ciclo = flow_janela_ciclo_ativo_por_tarefa($conn, $funcaoImagemId, true);
    if (!$ciclo) {
        throw new DomainException('Esta tarefa nao possui ciclo operacional ativo.');
    }
    $previsao = flow_janela_data_valida($previsao);
    if (!$previsao) {
        throw new DomainException('Informe uma previsao de conclusao valida.');
    }
    $unidade = flow_janela_resolver_unidade($conn, $funcaoImagemId, true);
    $prazo = flow_janela_prazo_necessario($conn, $unidade);
    $estadoNovo = flow_janela_classificar($previsao, $ciclo['limite_data_atual'], $prazo['data']);
    $motivo = flow_janela_validar_justificativa($conn, $estadoNovo, $motivoCodigo, $motivoTexto);
    $stmt = $conn->prepare('UPDATE janela_operacional_ciclo SET previsao_atual = ?, estado_atual = ?, prazo_necessario_snapshot = COALESCE(prazo_necessario_snapshot, ?), planejamento_versao_id_snapshot = COALESCE(planejamento_versao_id_snapshot, ?) WHERE id = ?');
    $versao = $prazo['versao_id'];
    $cicloId = (int) $ciclo['id'];
    $stmt->bind_param('sssii', $previsao, $estadoNovo, $prazo['data'], $versao, $cicloId);
    $stmt->execute();
    $stmt->close();
    flow_janela_registrar_evento($conn, $cicloId, 'PREVISAO_ATUALIZADA', [
        'estado_anterior' => $ciclo['estado_atual'],
        'estado_novo' => $estadoNovo,
        'previsao_anterior' => $ciclo['previsao_atual'],
        'previsao_nova' => $previsao,
        'prazo_necessario' => $prazo['data'],
        'limite_data' => $ciclo['limite_data_atual'],
        'motivo' => $motivo,
        'ator_colaborador_id' => $atorColaboradorId,
        'ator_usuario_id' => $atorUsuarioId,
    ]);
    return ['ciclo_id' => $cicloId, 'previsao' => $previsao, 'estado' => $estadoNovo, 'prazo_necessario' => $prazo['data'], 'limite_data' => $ciclo['limite_data_atual']];
}

function flow_janela_pausar(mysqli $conn, int $funcaoImagemId, ?int $atorColaboradorId, ?int $atorUsuarioId, ?int $flowIssueId = null): ?array
{
    $ciclo = flow_janela_ciclo_ativo_por_tarefa($conn, $funcaoImagemId, true);
    if (!$ciclo || $ciclo['situacao'] === 'ENCERRADO') {
        return null;
    }
    if ($ciclo['situacao'] === 'PAUSADO') {
        return ['ciclo_id' => (int) $ciclo['id'], 'ja_pausado' => true];
    }
    $cicloId = (int) $ciclo['id'];
    $agora = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO janela_operacional_pausa (ciclo_id, inicio_em, flow_issue_id, criado_por_colaborador_id, criado_por_usuario_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('isiii', $cicloId, $agora, $flowIssueId, $atorColaboradorId, $atorUsuarioId);
    $stmt->execute();
    $pausaId = (int) $conn->insert_id;
    $stmt->close();
    $conn->query("UPDATE janela_operacional_ciclo SET situacao = 'PAUSADO' WHERE id = " . $cicloId);
    flow_janela_registrar_evento($conn, $cicloId, 'HOLD_INICIADO', ['limite_data' => $ciclo['limite_data_atual'], 'ator_colaborador_id' => $atorColaboradorId, 'ator_usuario_id' => $atorUsuarioId, 'detalhes' => ['pausa_id' => $pausaId, 'flow_issue_id' => $flowIssueId]]);
    return ['ciclo_id' => $cicloId, 'pausa_id' => $pausaId, 'limite_data' => $ciclo['limite_data_atual']];
}

function flow_janela_retomar(mysqli $conn, int $funcaoImagemId, ?int $atorColaboradorId, ?int $atorUsuarioId): ?array
{
    $ciclo = flow_janela_ciclo_ativo_por_tarefa($conn, $funcaoImagemId, true);
    if (!$ciclo || $ciclo['situacao'] !== 'PAUSADO') {
        return null;
    }
    $cicloId = (int) $ciclo['id'];
    $stmt = $conn->prepare("SELECT * FROM janela_operacional_pausa WHERE ciclo_id = ? AND ativa_token = 'ATIVA' LIMIT 1 FOR UPDATE");
    $stmt->bind_param('i', $cicloId);
    $stmt->execute();
    $pausa = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$pausa) {
        throw new RuntimeException('Ciclo pausado sem evento de pausa ativo.');
    }
    $hoje = date('Y-m-d');
    $inicio = substr($pausa['inicio_em'], 0, 10);
    // Com granularidade de data, somente dias integralmente indisponiveis
    // ampliam a janela: nao contamos o dia em que o trabalho e retomado.
    $dias = flow_janela_dias_hold_integrais($inicio, $hoje);
    $aplicaRegra = !empty($ciclo['aplica_regra_snapshot']);
    $limiteNovo = $aplicaRegra
        ? flow_planejamento_adicionar_dias_uteis($ciclo['limite_data_atual'], $dias)
        : null;
    $agora = date('Y-m-d H:i:s');
    $pausaId = (int) $pausa['id'];
    $stmt = $conn->prepare("UPDATE janela_operacional_pausa SET fim_em = ?, dias_uteis_suspensos = ?, ativa_token = NULL, encerrado_por_colaborador_id = ?, encerrado_por_usuario_id = ? WHERE id = ?");
    $stmt->bind_param('siiii', $agora, $dias, $atorColaboradorId, $atorUsuarioId, $pausaId);
    $stmt->execute();
    $stmt->close();
    $unidade = flow_janela_resolver_unidade($conn, $funcaoImagemId, true);
    $prazoAtual = flow_janela_prazo_necessario($conn, $unidade);
    $estadoNovo = flow_janela_classificar($ciclo['previsao_atual'], $aplicaRegra ? $limiteNovo : null, $prazoAtual['data']);
    $stmt = $conn->prepare("UPDATE janela_operacional_ciclo SET situacao = 'ATIVO', limite_data_atual = ?, estado_atual = ? WHERE id = ?");
    $stmt->bind_param('ssi', $limiteNovo, $estadoNovo, $cicloId);
    $stmt->execute();
    $stmt->close();
    flow_janela_registrar_evento($conn, $cicloId, 'HOLD_ENCERRADO', ['estado_anterior' => $ciclo['estado_atual'], 'estado_novo' => $estadoNovo, 'prazo_necessario' => $prazoAtual['data'], 'limite_data' => $limiteNovo, 'dias_uteis_consumidos' => $dias, 'ator_colaborador_id' => $atorColaboradorId, 'ator_usuario_id' => $atorUsuarioId, 'detalhes' => ['pausa_id' => $pausaId, 'limite_anterior' => $ciclo['limite_data_atual']]]);
    return ['ciclo_id' => $cicloId, 'dias_uteis_suspensos' => $dias, 'limite_data_original' => $ciclo['limite_data_original'], 'limite_data_atual' => $limiteNovo, 'estado' => $estadoNovo];
}

function flow_janela_encerrar_ciclo(mysqli $conn, int $funcaoImagemId, string $motivo, ?int $atorColaboradorId, ?int $atorUsuarioId, ?string $statusSaida = null, ?string $eventoSaida = null): ?array
{
    $ciclo = flow_janela_ciclo_ativo_por_tarefa($conn, $funcaoImagemId, true);
    if (!$ciclo) {
        return null;
    }
    $cicloId = (int) $ciclo['id'];
    $agora = date('Y-m-d H:i:s');
    if ($ciclo['situacao'] === 'PAUSADO') {
        $stmtPausa = $conn->prepare("SELECT id, inicio_em FROM janela_operacional_pausa WHERE ciclo_id = ? AND ativa_token = 'ATIVA' LIMIT 1 FOR UPDATE");
        $stmtPausa->bind_param('i', $cicloId);
        $stmtPausa->execute();
        $pausa = $stmtPausa->get_result()->fetch_assoc();
        $stmtPausa->close();
        if ($pausa) {
            $diasPausa = flow_janela_dias_hold_integrais(substr($pausa['inicio_em'], 0, 10), date('Y-m-d'));
            $pausaId = (int) $pausa['id'];
            $stmtPausa = $conn->prepare('UPDATE janela_operacional_pausa SET fim_em = ?, dias_uteis_suspensos = ?, ativa_token = NULL, encerrado_por_colaborador_id = ?, encerrado_por_usuario_id = ? WHERE id = ?');
            $stmtPausa->bind_param('siiii', $agora, $diasPausa, $atorColaboradorId, $atorUsuarioId, $pausaId);
            $stmtPausa->execute();
            $stmtPausa->close();
        }
    }
    $stmt = $conn->prepare("UPDATE janela_operacional_ciclo SET situacao = 'ENCERRADO', ativo_token = NULL, encerrado_em = ?, motivo_encerramento = ?, status_saida = ? WHERE id = ?");
    $stmt->bind_param('sssi', $agora, $motivo, $statusSaida, $cicloId);
    $stmt->execute();
    $stmt->close();
    $consumidos = flow_janela_dias_consumidos($conn, $ciclo);
    if ($eventoSaida) {
        flow_janela_registrar_evento($conn, $cicloId, $eventoSaida, ['status_tarefa_anterior' => 'Em andamento', 'status_tarefa_novo' => $statusSaida, 'estado_anterior' => $ciclo['estado_atual'], 'previsao_anterior' => $ciclo['previsao_atual'], 'limite_data' => $ciclo['limite_data_atual'], 'dias_uteis_consumidos' => $consumidos, 'ator_colaborador_id' => $atorColaboradorId, 'ator_usuario_id' => $atorUsuarioId, 'detalhes' => ['motivo' => $motivo]]);
    }
    flow_janela_registrar_evento($conn, $cicloId, 'CICLO_ENCERRADO', ['status_tarefa_anterior' => $statusSaida ? 'Em andamento' : null, 'status_tarefa_novo' => $statusSaida, 'estado_anterior' => $ciclo['estado_atual'], 'previsao_anterior' => $ciclo['previsao_atual'], 'limite_data' => $ciclo['limite_data_atual'], 'dias_uteis_consumidos' => $consumidos, 'ator_colaborador_id' => $atorColaboradorId, 'ator_usuario_id' => $atorUsuarioId, 'detalhes' => ['motivo' => $motivo]]);
    return ['ciclo_id' => $cicloId, 'motivo' => $motivo, 'status_saida' => $statusSaida, 'dias_uteis_consumidos' => $consumidos];
}

/**
 * Protege rotas legadas de entrega que já gravaram Em aprovação diretamente.
 * A sincronização é idempotente: o fluxo canônico já terá encerrado o ciclo
 * e, nesse caso, não haverá ação adicional.
 */
function flow_janela_sincronizar_envio_aprovacao(mysqli $conn, int $funcaoImagemId, ?int $atorColaboradorId, ?int $atorUsuarioId): ?array
{
    if (!flow_janela_schema_disponivel($conn)) {
        return null;
    }
    $tarefa = flow_janela_carregar_tarefa($conn, $funcaoImagemId, true);
    if (!$tarefa || (string) ($tarefa['status'] ?? '') !== 'Em aprovação') {
        return null;
    }
    $ciclo = flow_janela_ciclo_ativo_por_tarefa($conn, $funcaoImagemId, true);
    if (!$ciclo || !in_array((string) $ciclo['situacao'], ['ATIVO', 'PAUSADO'], true)) {
        return null;
    }
    return flow_janela_encerrar_ciclo(
        $conn,
        $funcaoImagemId,
        'SINCRONIZACAO_ENVIO_APROVACAO',
        $atorColaboradorId,
        $atorUsuarioId,
        'Em aprovação',
        'EXECUCAO_ENVIADA_APROVACAO'
    );
}

function flow_janela_encerrar_se_unidade_finalizada(mysqli $conn, int $funcaoImagemId, ?int $atorColaboradorId, ?int $atorUsuarioId): ?array
{
    $ciclo = flow_janela_ciclo_ativo_por_tarefa($conn, $funcaoImagemId, true);
    if (!$ciclo) {
        return null;
    }
    $cicloId = (int) $ciclo['id'];
    $stmt = $conn->prepare("SELECT COUNT(*) total
                              FROM janela_operacional_ciclo_item i
                              JOIN funcao_imagem fi ON fi.idfuncao_imagem = i.funcao_imagem_id
                             WHERE i.ciclo_id = ?
                               AND fi.status NOT IN ('Finalizado', 'Aprovado', 'Aprovado com ajustes')");
    $stmt->bind_param('i', $cicloId);
    $stmt->execute();
    $pendentes = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $pendentes === 0
        ? flow_janela_encerrar_ciclo($conn, $funcaoImagemId, 'CONCLUSAO', $atorColaboradorId, $atorUsuarioId)
        : null;
}

function flow_janela_dias_consumidos(mysqli $conn, array $ciclo, ?string $ateData = null): int
{
    $inicio = substr((string) $ciclo['inicio_em'], 0, 10);
    $ateData = flow_janela_data_valida($ateData ?: date('Y-m-d')) ?: date('Y-m-d');
    $total = flow_planejamento_dias_uteis_entre($inicio, $ateData);
    $stmt = $conn->prepare('SELECT inicio_em, fim_em, dias_uteis_suspensos FROM janela_operacional_pausa WHERE ciclo_id = ?');
    $cicloId = (int) $ciclo['id'];
    $stmt->bind_param('i', $cicloId);
    $stmt->execute();
    $pausados = 0;
    $result = $stmt->get_result();
    while ($pausa = $result->fetch_assoc()) {
        $pausados += $pausa['fim_em'] !== null
            ? (int) ($pausa['dias_uteis_suspensos'] ?? 0)
            : flow_janela_dias_hold_integrais(substr($pausa['inicio_em'], 0, 10), $ateData);
    }
    $stmt->close();
    return max(0, $total - $pausados);
}

/**
 * Transfere a unidade inteira. O ciclo anterior e encerrado e a politica
 * vigente e fotografada em um novo ciclo ligado por ciclo_anterior_id.
 */
function flow_janela_transferir_responsavel(
    mysqli $conn,
    int $funcaoImagemId,
    int $novoResponsavelId,
    ?string $novaPrevisao,
    string $motivoTransferencia,
    ?int $atorColaboradorId,
    ?int $atorUsuarioId,
    ?string $motivoOperacionalCodigo = null,
    ?string $motivoOperacionalTexto = null
): array {
    $ciclo = flow_janela_ciclo_ativo_por_tarefa($conn, $funcaoImagemId, true);
    if (!$ciclo) {
        return ['aplicada' => false, 'motivo' => 'SEM_CICLO_ATIVO'];
    }
    $responsavelAnterior = (int) $ciclo['responsavel_atual_id'];
    if ($novoResponsavelId <= 0 || $novoResponsavelId === $responsavelAnterior) {
        throw new DomainException('Novo responsavel invalido para transferencia.');
    }
    $unidade = flow_janela_resolver_unidade($conn, $funcaoImagemId, true);
    flow_wip_assert_novo_inicio($conn, $novoResponsavelId);
    $previsao = flow_janela_data_valida($novaPrevisao) ?: flow_janela_data_valida($ciclo['previsao_atual']);
    if (!$previsao) {
        throw new DomainException('Informe a previsao do novo responsavel.');
    }
    $motivoTransferencia = trim($motivoTransferencia);
    if ($motivoTransferencia === '') {
        throw new DomainException('Informe o motivo da transferencia.');
    }
    $consumidos = flow_janela_dias_consumidos($conn, $ciclo);
    flow_janela_registrar_evento($conn, (int) $ciclo['id'], 'TRANSFERENCIA_SAIDA', [
        'estado_anterior' => $ciclo['estado_atual'],
        'previsao_anterior' => $ciclo['previsao_atual'],
        'prazo_necessario' => $ciclo['prazo_necessario_snapshot'],
        'limite_data' => $ciclo['limite_data_atual'],
        'responsavel_anterior_id' => $responsavelAnterior,
        'responsavel_novo_id' => $novoResponsavelId,
        'dias_uteis_consumidos' => $consumidos,
        'ator_colaborador_id' => $atorColaboradorId,
        'ator_usuario_id' => $atorUsuarioId,
        'detalhes' => ['motivo_transferencia' => $motivoTransferencia],
    ]);
    flow_janela_encerrar_ciclo($conn, $funcaoImagemId, 'TRANSFERENCIA', $atorColaboradorId, $atorUsuarioId);

    $stmt = $conn->prepare('UPDATE funcao_imagem SET colaborador_id = ? WHERE idfuncao_imagem = ? AND colaborador_id = ?');
    foreach ($unidade['membros'] as &$membro) {
        $taskId = (int) $membro['idfuncao_imagem'];
        $stmt->bind_param('iii', $novoResponsavelId, $taskId, $responsavelAnterior);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            throw new RuntimeException('A unidade mudou durante a transferencia. Recarregue e tente novamente.');
        }
        $membro['colaborador_id'] = $novoResponsavelId;
    }
    unset($membro);
    $stmt->close();
    if (!empty($unidade['unidade_trabalho_id'])) {
        $unitId = (int) $unidade['unidade_trabalho_id'];
        $stmt = $conn->prepare('UPDATE unidade_trabalho SET colaborador_id = ? WHERE id = ?');
        $stmt->bind_param('ii', $novoResponsavelId, $unitId);
        $stmt->execute();
        $stmt->close();
    }
    $unidade['tarefa_principal']['colaborador_id'] = $novoResponsavelId;
    if ($unidade['tipo_unidade'] === 'CADERNO_FILTRO_LEGADO') {
        $unidade['chave_referencia'] = 'LEGACY_CADERNO_FILTRO:' . (int) $unidade['tarefa_principal']['imagem_id'] . ':' . $novoResponsavelId;
    }
    $avaliacao = flow_janela_avaliar($conn, $unidade, $previsao);
    if ($avaliacao['exige_justificativa'] && !$motivoOperacionalCodigo) {
        // Fluxos legados de transferencia ja exigem um motivo textual. Ele e
        // preservado como OUTRO, sem atribuir silenciosamente uma causa falsa.
        $motivoOperacionalCodigo = 'OUTRO';
        $motivoOperacionalTexto = $motivoTransferencia;
    }
    $motivo = flow_janela_validar_justificativa($conn, $avaliacao['estado'], $motivoOperacionalCodigo, $motivoOperacionalTexto);
    $novo = flow_janela_criar_ciclo(
        $conn,
        $unidade,
        $avaliacao,
        $motivo,
        $atorColaboradorId,
        $atorUsuarioId,
        (int) $ciclo['id'],
        'TRANSFERENCIA_ENTRADA',
        [
            'motivo_transferencia' => $motivoTransferencia,
            'previsao_reaproveitada' => empty($novaPrevisao),
            'responsavel_anterior_id' => $responsavelAnterior,
            'responsavel_novo_id' => $novoResponsavelId,
        ]
    );
    if ($ciclo['situacao'] === 'PAUSADO') {
        // A troca de responsavel nao retira a tarefa do HOLD. O novo ciclo
        // nasce com janela completa, mas permanece suspenso ate a retomada.
        flow_janela_pausar($conn, $funcaoImagemId, $atorColaboradorId, $atorUsuarioId);
    }
    return ['aplicada' => true, 'ciclo_anterior_id' => (int) $ciclo['id'], 'ciclo_novo_id' => (int) $novo['ciclo_id'], 'avaliacao' => $avaliacao, 'membros' => array_column($unidade['membros'], 'idfuncao_imagem')];
}

function flow_janela_contextos_lote(mysqli $conn, array $funcaoImagemIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $funcaoImagemIds))));
    if (!$ids || !flow_janela_schema_disponivel($conn)) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("SELECT i.funcao_imagem_id, c.id ciclo_id, c.perfil_codigo_snapshot, c.perfil_nome_snapshot,
                                  c.limite_dias_uteis_snapshot, c.limite_data_original, c.limite_data_atual,
                                  c.previsao_atual, c.prazo_necessario_snapshot, c.estado_atual, c.situacao
                             FROM janela_operacional_ciclo_item i
                             JOIN janela_operacional_ciclo c ON c.id = i.ciclo_id AND c.ativo_token = 'ATIVO'
                            WHERE i.funcao_imagem_id IN ($marks)");
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();
    $out = [];
    while ($row = $result->fetch_assoc()) {
        $out[(int) $row['funcao_imagem_id']] = $row;
    }
    $stmt->close();
    return $out;
}
