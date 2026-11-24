<?php

/** Planejamento manual por rodada. O motor de funções R00 permanece separado. */
require_once __DIR__ . '/planejamento_producao_helper.php';
require_once __DIR__ . '/planejamento_alteracao_capacidade_helper.php';
require_once __DIR__ . '/planejamento_alteracao_realizado_helper.php';
require_once __DIR__ . '/alteracoes_helper.php';

final class FlowPaClassificacaoPendente extends DomainException
{
    public function __construct(public array $itens)
    {
        parent::__construct('Classifique as imagens adicionadas à entrega para continuar o planejamento.', 409);
    }
}

function flow_pa_query(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error);
    }
    return $stmt;
}

function flow_pa_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = flow_pa_query($conn, $sql, $types, $params);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function flow_planejamento_modelo(mysqli $conn, int $entregaId): string
{
    // Instalações ainda sem migration continuam oferecendo exclusivamente R00.
    if (!flow_planejamento_tabelas_persistencia_disponiveis($conn)) {
        return 'FUNCOES_R00';
    }
    $raiz = flow_planejamento_carregar_raiz($conn, $entregaId);
    if ($raiz) {
        return (string)$raiz['modelo'];
    }
    $entrega = flow_pa_rows($conn, 'SELECT status_id,arquivada FROM entregas WHERE id=?
        AND EXISTS (SELECT 1 FROM entregas_itens WHERE entrega_id=entregas.id AND data_entregue IS NULL)', 'i', [$entregaId])[0] ?? [];
    return flow_pa_manual_elegivel($conn, $entrega) ? 'IMAGENS_ALTERACAO' : 'FUNCOES_R00';
}

function flow_pa_manual_elegivel(mysqli $conn, array $entrega): bool
{
    return empty($entrega['arquivada'])
        && in_array((int)($entrega['status_id'] ?? 0), [3, 4, 5, 14, 15], true)
        && improov_usuario_eh_gestor_sidebar($conn);
}

function flow_pa_entrega(mysqli $conn, int $id, bool $lock = false): array
{
    $e = flow_pa_rows($conn, 'SELECT e.*, o.nomenclatura, s.nome_status AS etapa FROM entregas e JOIN obra o ON o.idobra=e.obra_id JOIN status_imagem s ON s.idstatus=e.status_id WHERE e.id=?' . ($lock ? ' FOR UPDATE' : ''), 'i', [$id])[0] ?? null;
    if (!$e) {
        throw new InvalidArgumentException('Entrega não encontrada.');
    }
    return $e;
}

function flow_pa_escopo(mysqli $conn, int $id, bool $lock = false): array
{
    // Consultas em lote; número de chamadas independente do número de imagens.
    $sql = "SELECT ei.*,e.obra_id AS entrega_obra_id,e.status_id AS status_id_entrega,im.imagem_nome,im.tipo_imagem,im.status_id AS etapa_atual_id,im.obra_id AS imagem_obra_id
        FROM entregas_itens ei JOIN entregas e ON e.id=ei.entrega_id
        JOIN imagens_cliente_obra im ON im.idimagens_cliente_obra=ei.imagem_id
        WHERE ei.entrega_id=? ORDER BY ei.id".($lock ? ' FOR UPDATE' : '');
    $dados = flow_pa_rows($conn, $sql, 'i', [$id]);
    $saida = [];
    $pendentes = [];
    $vistos = [];
    if (!$dados) {
        return [];
    }
    $ids = implode(',', array_map('intval', array_column($dados, 'imagem_id')));
    $tarefas = [];
    foreach (flow_pa_rows($conn, "SELECT imagem_id,idfuncao_imagem,status,colaborador_id FROM funcao_imagem WHERE funcao_id=6 AND imagem_id IN ($ids) ORDER BY idfuncao_imagem".($lock ? ' FOR UPDATE' : '')) as $t) {
        $tarefas[$t['imagem_id']][] = $t;
    }
    $itemIds = implode(',', array_map('intval', array_column($dados, 'id')));
    $ciclos = array_column(flow_pa_rows($conn, "SELECT entrega_item_id,situacao,motivo_encerramento FROM janela_operacional_ciclo WHERE entrega_item_id IN ($itemIds) ORDER BY id".($lock ? ' FOR UPDATE' : '')), null, 'entrega_item_id');
    $tarefaIds = implode(',', array_map('intval', array_column(array_merge(...array_values($tarefas ?: [[]])), 'idfuncao_imagem')));
    $ativos = [];
    if ($tarefaIds) {
        $sqlAtivos = "SELECT ci.funcao_imagem_id,c.id,c.situacao,c.entrega_item_id,c.planejamento_versao_id_snapshot,c.unidade_trabalho_id,c.responsavel_atual_id,c.previsao_atual
            FROM janela_operacional_ciclo c JOIN janela_operacional_ciclo_item ci ON ci.ciclo_id=c.id
            WHERE ci.funcao_imagem_id IN ($tarefaIds) AND c.ativo_token='ATIVO'
            ORDER BY ci.funcao_imagem_id,c.id".($lock ? ' FOR UPDATE' : '');
        $linhasAtivas = flow_pa_rows($conn, $sqlAtivos);
        $semPlano = array_filter($linhasAtivas, static fn (array $ciclo) => $ciclo['entrega_item_id'] === null
            && $ciclo['planejamento_versao_id_snapshot'] === null && $ciclo['unidade_trabalho_id'] === null);
        $membrosPorCiclo = [];
        if ($semPlano) {
            $idsSemPlano = implode(',', array_map('intval', array_column($semPlano, 'id')));
            $membros = flow_pa_rows($conn, "SELECT ciclo_id FROM janela_operacional_ciclo_item WHERE ciclo_id IN ($idsSemPlano) ORDER BY ciclo_id".($lock ? ' FOR UPDATE' : ''));
            $membrosPorCiclo = array_count_values(array_column($membros, 'ciclo_id'));
        }
        foreach ($linhasAtivas as $ativo) {
            $ativo['individual'] = ($membrosPorCiclo[$ativo['id']] ?? 0) === 1;
            $ativos[$ativo['funcao_imagem_id']][] = $ativo;
        }
    }
    $origens = [];
    $rodadasAtuais = [];
    $rodadas = flow_pa_rows($conn, "SELECT p.imagem_id,p.id,p.nivel_complexidade,p.resultado,l.entrega_destino_id
        FROM pre_alt_liberacao_itens l JOIN pre_alt_itens p ON p.id=l.pre_alt_item_id
        WHERE p.imagem_id IN ($ids) AND (l.entrega_destino_id=? OR l.status_destino_id=?)
        ORDER BY l.id".($lock ? ' FOR UPDATE' : ''), 'ii', [$id, (int)$dados[0]['status_id_entrega']]);
    foreach ($rodadas as $p) {
        $rodadasAtuais[$p['imagem_id']] = (int)$p['entrega_destino_id'];
        if ((int)$p['entrega_destino_id'] === $id) {
            $origens[$p['imagem_id']][] = $p;
        }
    }
    foreach ($dados as $r) {
        if (isset($vistos[$r['imagem_id']])) {
            throw new DomainException('A mesma imagem está duplicada na entrega.');
        }
        $vistos[$r['imagem_id']] = true;
        $ts = $tarefas[$r['imagem_id']] ?? [];
        $ps = $origens[$r['imagem_id']] ?? [];
        if ((int)$r['imagem_obra_id'] !== (int)$r['entrega_obra_id']) {
            throw new DomainException('A imagem não pertence à obra da entrega.');
        }
        if (!$ps && count($ts) <= 1 && empty($r['data_entregue']) && !isset($ciclos[$r['id']])
            && (int)$r['etapa_atual_id'] === (int)$r['status_id_entrega']
            && ($rodadasAtuais[$r['imagem_id']] ?? 0) < $id) {
            $pendentes[] = ['entrega_item_id' => (int)$r['id'], 'imagem_id' => (int)$r['imagem_id'], 'imagem_nome' => $r['imagem_nome'],
                'tipo_imagem' => (string)($r['tipo_imagem'] ?? ''),
                'funcao_imagem_id' => isset($ts[0]) ? (int)$ts[0]['idfuncao_imagem'] : null,
                'rodada_anterior_id' => $rodadasAtuais[$r['imagem_id']] ?? null];
            continue;
        }
        if (count($ps) !== 1 || $ps[0]['resultado'] !== 'ALTERACAO') {
            throw new DomainException('A origem da Pré-alteração não é compatível com a imagem da entrega.');
        }
        if (count($ts) !== 1) {
            throw new DomainException('Cada imagem precisa de uma única tarefa de Alteração.');
        }
        $tarefa = $ts[0];
        $r['rodada_substituida'] = $rodadasAtuais[$r['imagem_id']] !== $id;
        $ativosDaTarefa = $ativos[$tarefa['idfuncao_imagem']] ?? [];
        $cicloResidual = flow_pa_ciclo_residual((string)$tarefa['status'], $ativosDaTarefa);
        $estado = flow_pa_estado_item($r, (string)$tarefa['status'], $ciclos[$r['id']] ?? null, $ativosDaTarefa && $cicloResidual === null);
        $item = ['entrega_item_id' => (int)$r['id'],'imagem_id' => (int)$r['imagem_id'],'imagem_nome' => $r['imagem_nome'],'tipo_imagem' => (string)($r['tipo_imagem'] ?? ''),
            'funcao_imagem_id' => (int)$ts[0]['idfuncao_imagem'],'pre_alt_item_id' => (int)$ps[0]['id'],'nivel_complexidade_snapshot' => $ps[0]['nivel_complexidade'] === null ? null : (int)$ps[0]['nivel_complexidade'],
            'etapa_atual_id' => (int)$r['etapa_atual_id'],'data_entregue' => $r['data_entregue'],'tarefa_cancelada' => flow_planejamento_normalizar((string)$ts[0]['status']) === 'cancelado',
            'responsavel_planejado_id' => null,'inicio_planejado' => null,'fim_planejado' => null] + $estado + [
            'responsavel_operacional_id' => (int)$tarefa['colaborador_id']];
        $ativo = count($ativosDaTarefa) === 1 ? $ativosDaTarefa[0] : null;
        $status = (string)$tarefa['status'];
        if ($estado['pode_editar_datas'] && $ativo && (int)$ativo['entrega_item_id'] === $item['entrega_item_id']
            && $ativo['unidade_trabalho_id'] === null && $item['responsavel_operacional_id'] > 0
            && (int)$ativo['responsavel_atual_id'] === $item['responsavel_operacional_id']
            && in_array($status, ['Em andamento', 'Ajuste', 'HOLD'], true)
            && ($ativo['situacao'] !== 'AGUARDANDO_INICIO' || $status === 'Ajuste')) {
            $item['execucao'] = ['ciclo_id' => (int)$ativo['id'], 'situacao' => $ativo['situacao'],
                'status' => $status, 'previsao' => $ativo['previsao_atual']];
        }
        if ($lock && $cicloResidual !== null) {
            $item['ciclo_residual_id'] = $cicloResidual;
        }
        $saida[] = $item;
    }
    if ($pendentes) {
        throw new FlowPaClassificacaoPendente($pendentes);
    }
    return $saida;
}

/** Uma tarefa reiniciada pode ter deixado aberto um ciclo individual sem vínculo com um plano. */
function flow_pa_ciclo_residual(string $status, array $ativos): ?int
{
    if (count($ativos) !== 1 || flow_planejamento_normalizar($status) !== 'nao iniciado') {
        return null;
    }
    $ciclo = $ativos[0];
    return $ciclo['situacao'] === 'ATIVO' && !empty($ciclo['individual'])
        && $ciclo['entrega_item_id'] === null && $ciclo['planejamento_versao_id_snapshot'] === null
        && $ciclo['unidade_trabalho_id'] === null ? (int)$ciclo['id'] : null;
}

function flow_pa_estado_item(array $item, string $status, ?array $ciclo, bool $ativo): array
{
    $mesmaEtapa = empty($item['rodada_substituida']) && (int)$item['etapa_atual_id'] === (int)$item['status_id_entrega'];
    $status = flow_planejamento_normalizar($status);
    $concluido = flow_pac_ciclo_concluido($ciclo) || (!$ciclo && $mesmaEtapa && !$ativo && in_array($status, ['finalizado', 'aprovado'], true));
    $motivo = match (true) {
        !empty($item['data_entregue']) => 'Entregue ao cliente',
        !$mesmaEtapa => 'Imagem em outra rodada',
        $status === 'cancelado' => 'Tarefa cancelada',
        $concluido => 'Alteração concluída',
        default => null,
    };
    $trocaBloqueada = $ativo || in_array($status, ['em andamento', 'hold', 'ajuste'], true);
    return [
        'pode_editar_datas' => $motivo === null,
        'pode_editar_responsavel' => $motivo === null && !$trocaBloqueada,
        'motivo_bloqueio' => $motivo,
        'motivo_bloqueio_responsavel' => $motivo ?? ($trocaBloqueada ? 'Responsável protegido durante a execução' : null),
    ];
}

function flow_pa_liberacao_classificacao(mysqli $conn, array $entrega, bool $lock = false): ?array
{
    $liberacoes = flow_pa_rows($conn, "SELECT l.id, l.pre_alt_lote_id
        FROM pre_alt_liberacoes l JOIN pre_alt_lote p ON p.id=l.pre_alt_lote_id
        WHERE l.entrega_alteracao_id=? AND p.obra_id=? AND p.status<>'CANCELADO'"
        . ($lock ? ' FOR UPDATE' : ''), 'ii', [$entrega['id'], $entrega['obra_id']]);
    if (count($liberacoes) > 1) {
        throw new DomainException('A entrega precisa estar vinculada a uma única liberação de Pré-alteração.');
    }
    return $liberacoes[0] ?? null;
}

function flow_pa_niveis_manuais(mysqli $conn, array $entrega, array $itens, bool $lock = false): array
{
    $tarefas = array_filter(array_column($itens, 'funcao_imagem_id'));
    if (!$tarefas || !alteracoes_column_exists($conn, 'alteracoes', 'nivel_complexidade')) {
        return [];
    }
    $ids = implode(',', array_map('intval', $tarefas));
    $niveis = array_column(flow_pa_rows($conn, "SELECT funcao_id,nivel_complexidade FROM alteracoes
        WHERE status_id=? AND funcao_id IN ($ids) AND nivel_complexidade BETWEEN 1 AND 5"
        . ($lock ? ' FOR UPDATE' : ''), 'i', [(int)$entrega['status_id']]), 'nivel_complexidade', 'funcao_id');
    $resultado = [];
    foreach ($itens as $item) {
        if (!$item['rodada_anterior_id'] && isset($niveis[$item['funcao_imagem_id']])) {
            $resultado[$item['entrega_item_id']] = (int)$niveis[$item['funcao_imagem_id']];
        }
    }
    return $resultado;
}

function flow_pa_criar_liberacao_manual(mysqli $conn, array $entrega, ?int $ator): array
{
    // A origem mantém o nível por imagem e rodada no mesmo formato do planejamento e da execução.
    $data = $entrega['data_recebimento'] ?: date('Y-m-d');
    $stmt = flow_pa_query(
        $conn,
        "INSERT INTO pre_alt_lote
        (obra_id, status_id, data_finalizacao_cliente, status, prazo, created_by)
        VALUES (?, ?, ?, 'PLANEJADO', ?, ?)",
        'iissi',
        [$entrega['obra_id'], $entrega['status_id'], $data, $entrega['data_prevista'], $ator]
    );
    $loteId = (int)$stmt->insert_id;
    $stmt->close();
    $stmt = flow_pa_query(
        $conn,
        "INSERT INTO pre_alt_liberacoes
        (pre_alt_lote_id, data_triagem, entrega_alteracao_id, observacao, created_by)
        VALUES (?, CURRENT_DATE, ?, 'Classificação no planejamento de entrega manual.', ?)",
        'iii',
        [$loteId, $entrega['id'], $ator]
    );
    $liberacao = ['id' => (int)$stmt->insert_id, 'pre_alt_lote_id' => $loteId];
    $stmt->close();
    return $liberacao;
}

function flow_pa_classificar_imagens(mysqli $conn, int $id, array $dados): void
{
    if (!improov_usuario_eh_gestor_sidebar($conn)) {
        throw new RuntimeException('Somente a gestão pode classificar imagens da entrega.', 403);
    }
    require_once __DIR__ . '/../PreAlteracao/conclusao_helpers.php';
    $coluna = flow_pa_rows($conn, "SELECT IS_NULLABLE FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pre_alt_itens' AND COLUMN_NAME='review_batch_item_id'");
    if (($coluna[0]['IS_NULLABLE'] ?? '') !== 'YES') {
        throw new RuntimeException('Aplique a migration 2026-11-10_pre_alt_imagens_avulsas.sql antes de classificar imagens avulsas.');
    }
    if (!alteracoes_column_exists($conn, 'alteracoes', 'nivel_complexidade')) {
        throw new RuntimeException('Atualize o esquema de Alterações antes de classificar imagens avulsas.');
    }

    $conn->begin_transaction();
    try {
        $entrega = flow_pa_entrega($conn, $id, true);
        if (!improov_usuario_pode_acessar_obra($conn, (int)$entrega['obra_id'])) {
            throw new RuntimeException('Sem permissão para acessar esta obra.', 403);
        }
        $plano = flow_planejamento_carregar_raiz($conn, $id, true);
        if (!empty($entrega['arquivada']) || ($plano
            ? $plano['modelo'] !== 'IMAGENS_ALTERACAO'
            : !flow_pa_manual_elegivel($conn, $entrega))) {
            throw new DomainException('A entrega não está disponível para classificação.');
        }
        $liberacao = flow_pa_liberacao_classificacao($conn, $entrega, true);
        $pendentes = [];
        try {
            flow_pa_escopo($conn, $id, true);
        } catch (FlowPaClassificacaoPendente $erro) {
            $pendentes = array_column($erro->itens, null, 'entrega_item_id');
        }
        $manual = $liberacao === null;
        $existentes = $manual ? flow_pa_niveis_manuais($conn, $entrega, $pendentes, true) : [];
        $aClassificar = array_diff_key($pendentes, $existentes);
        $niveis = $dados['niveis'] ?? null;
        if (!$pendentes || (int)($dados['liberacao_id'] ?? 0) !== (int)($liberacao['id'] ?? 0)
            || !is_array($niveis) || array_diff_key($niveis, $aClassificar) || array_diff_key($aClassificar, $niveis)) {
            throw new RuntimeException('As imagens pendentes mudaram. Recarregue o planejamento.', 409);
        }

        $ator = !empty($_SESSION['idcolaborador']) ? (int)$_SESSION['idcolaborador'] : null;
        $liberacao ??= flow_pa_criar_liberacao_manual($conn, $entrega, $ator);
        $niveis += $existentes;
        $loteId = (int)$liberacao['pre_alt_lote_id'];
        $prazo = $entrega['data_prevista'];
        $statusId = (int)$entrega['status_id'];
        foreach ($pendentes as $itemId => $item) {
            $valor = $niveis[$itemId];
            if ((!is_string($valor) && !is_int($valor)) || !preg_match('/^[1-5]$/D', (string)$valor)) {
                throw new InvalidArgumentException('Escolha um nível de complexidade de 1 a 5 para cada imagem.');
            }
            $nivel = (int)$valor;
            $imagemId = $item['imagem_id'];
            if (flow_pa_rows($conn, 'SELECT id FROM pre_alt_itens WHERE pre_alt_lote_id=? AND imagem_id=? FOR UPDATE', 'ii', [$loteId, $imagemId])) {
                throw new DomainException('Uma das imagens já consta neste lote da Pré-alteração. Revise a liberação existente.');
            }
            $tarefaId = $manual && !$item['rodada_anterior_id'] ? $item['funcao_imagem_id'] : null;
            $tarefaId ??= pre_alt_upsert_funcao_alteracao($conn, $imagemId, $prazo, true);
            alteracoes_upsert_registro($conn, $tarefaId, $statusId, $entrega['data_recebimento'], $nivel, true);
            $stmt = flow_pa_query($conn, "INSERT INTO pre_alt_itens
                (pre_alt_lote_id, entrega_id, entrega_item_id, imagem_id, resultado, nivel_complexidade)
                VALUES (?, ?, ?, ?, 'ALTERACAO', ?)", 'iiiii', [$loteId, $id, $itemId, $imagemId, $nivel]);
            $preAltItemId = (int)$stmt->insert_id;
            $stmt->close();
            flow_pa_query($conn, 'INSERT INTO pre_alt_liberacao_itens
                (liberacao_id, pre_alt_item_id, entrega_destino_id, status_destino_id, prazo)
                VALUES (?, ?, ?, ?, ?)', 'iiiis', [$liberacao['id'], $preAltItemId, $id, $statusId, $prazo])->close();
            pre_alt_registrar_historico(
                $conn,
                $loteId,
                'INCLUSAO_MANUAL',
                'nivel_complexidade',
                null,
                $nivel,
                'Imagem classificada no planejamento da entrega.',
                $preAltItemId,
                null,
                ['entrega_id' => $id]
            );
        }
        if ($plano) {
            flow_pa_escopo($conn, $id, true);
        } else {
            flow_planejamento_alteracao_criar($conn, $id, $ator);
        }
        $conn->commit();
    } catch (Throwable $erro) {
        $conn->rollback();
        throw $erro;
    }
}

function flow_pa_itens_editaveis(array $itens): array
{
    return array_values(array_filter($itens, fn ($item) => $item['pode_editar_datas']));
}

function flow_pa_preservar_responsavel(array $item, array $confirmado): array
{
    if ($item['pode_editar_datas'] && !$item['pode_editar_responsavel']) {
        $item['responsavel_preservado_id'] = (int)($confirmado['responsavel_planejado_id'] ?? $item['responsavel_operacional_id']);
    }
    return $item;
}

/** Usa a mesma atribuição confirmada que as operações da tarefa consultam. */
function flow_pa_snapshot_versao(mysqli $conn, array $versao): array
{
    $snapshot = json_decode((string)$versao['snapshot_json'], true) ?? [];
    if (empty($snapshot['itens'])) {
        return $snapshot;
    }
    $atribuicoes = array_column(flow_pa_rows($conn, 'SELECT i.entrega_item_id,i.responsavel_planejado_id,c.nome_colaborador
        FROM entrega_planejamento_item i
        LEFT JOIN colaborador c ON c.idcolaborador=i.responsavel_planejado_id
        WHERE i.versao_id=?', 'i', [(int)$versao['id']]), null, 'entrega_item_id');
    foreach ($snapshot['itens'] as &$item) {
        $atribuicao = $atribuicoes[$item['entrega_item_id']] ?? null;
        if ($atribuicao && (int)($item['responsavel_planejado_id'] ?? 0) !== (int)$atribuicao['responsavel_planejado_id']) {
            $item['responsavel_planejado_id'] = (int)$atribuicao['responsavel_planejado_id'];
            $item['responsavel_planejado_nome'] = $atribuicao['nome_colaborador'];
        }
    }
    unset($item);
    return $snapshot;
}

function flow_pa_fingerprint(array $entrega, array $itens): array
{
    $campos = ['entrega_item_id','imagem_id','funcao_imagem_id','pre_alt_item_id','nivel_complexidade_snapshot','etapa_atual_id','data_entregue','tarefa_cancelada','pode_editar_datas','pode_editar_responsavel','motivo_bloqueio'];
    $contexto = ['modelo' => 'IMAGENS_ALTERACAO','entrega_id' => (int)$entrega['id'],'obra_id' => (int)$entrega['obra_id'],'status_id' => (int)$entrega['status_id'],'data_recebimento' => $entrega['data_recebimento'],'data_prevista' => $entrega['data_prevista'],'arquivada' => (int)($entrega['arquivada'] ?? 0),'itens' => []];
    foreach ($itens as $i) {
        $contexto['itens'][] = array_intersect_key($i, array_flip($campos));
    }
    return ['fingerprint' => hash('sha256', flow_planejamento_json($contexto)),'contexto' => $contexto];
}

function flow_planejamento_alteracao_criar(mysqli $conn, int $entregaId, ?int $atorId = null): array
{
    try {
        foreach ([
            'SELECT modelo,rascunho_json FROM entrega_planejamento_producao LIMIT 0',
            'SELECT entrega_item_id FROM janela_operacional_ciclo LIMIT 0',
            'SELECT versao_id FROM entrega_planejamento_item LIMIT 0',
        ] as $sql) {
            $conn->query($sql)->free();
        }
    } catch (mysqli_sql_exception $erro) {
        if (in_array((int)$erro->getCode(), [1054,1146], true)) {
            throw new RuntimeException('Aplique a migration 2026-10-28_planejamento_alteracao_manual.sql antes de liberar uma nova entrega de alteração.', 0, $erro);
        }
        throw $erro;
    }
    $e = flow_pa_entrega($conn, $entregaId, true);
    if (!in_array((int)$e['status_id'], [3,4,5,14,15], true)) {
        throw new DomainException('O planejamento por imagens atende somente R01–R05.');
    }
    $itens = flow_pa_escopo($conn, $entregaId, true);
    if (!$itens) {
        throw new DomainException('Uma entrega de alteração precisa de imagens.');
    }
    $fp = flow_pa_fingerprint($e, $itens);
    // Não converter um plano legado nem continuar silenciosamente sem migration.
    flow_pa_query($conn, "INSERT INTO entrega_planejamento_producao (entrega_id,modelo,estado,ultimo_fingerprint) VALUES (?,'IMAGENS_ALTERACAO','RASCUNHO',?)", 'is', [$entregaId,$fp['fingerprint']])->close();
    $raiz = flow_planejamento_carregar_raiz($conn, $entregaId);
    flow_planejamento_registrar_evento($conn, (int)$raiz['id'], $entregaId, 'RASCUNHO_CRIADO', null, $atorId, null, null, ['modelo' => 'IMAGENS_ALTERACAO','imagens' => count($itens)]);
    return $raiz;
}

function flow_pa_colaboradores(mysqli $conn, bool $lock = false): array
{
    // Agrega vínculos repetidos, preservando principal como qualificação mais forte.
    return flow_pa_rows($conn, "SELECT c.idcolaborador AS id,c.nome_colaborador AS nome,
        CASE WHEN MAX(fc.tipo_atuacao='PRINCIPAL')=1 THEN 'PRINCIPAL' ELSE 'SECUNDARIA' END AS tipo_atuacao
        FROM colaborador c JOIN funcao_colaborador fc ON fc.colaborador_id=c.idcolaborador AND fc.funcao_id=6
        WHERE c.ativo=1 AND COALESCE(c.elegivel_capacidade,1)=1 GROUP BY c.idcolaborador,c.nome_colaborador
        ORDER BY c.idcolaborador" . ($lock ? ' FOR UPDATE' : ''));
}

/** O cliente envia apenas decisões; identidade e classificação vêm do escopo. */
function flow_pa_validar_itens(array $escopo, array $decisoes, array $colaboradores, bool $confirmar): array
{
    $porId = [];
    $validos = array_map('intval', array_column($colaboradores, 'id'));
    foreach ($decisoes as $d) {
        if (!is_array($d)) {
            throw new InvalidArgumentException('Item de planejamento inválido.');
        }
        $id = filter_var($d['entrega_item_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || isset($porId[$id])) {
            throw new InvalidArgumentException('Imagem duplicada ou inválida.');
        }
        $porId[$id] = $d;
    }
    $ids = array_column($escopo, 'entrega_item_id');
    if (array_diff(array_keys($porId), $ids)) {
        throw new DomainException('O escopo da entrega mudou ou contém imagem de outra entrega.');
    }
    $saida = [];
    foreach ($escopo as $item) {
        $d = $porId[$item['entrega_item_id']] ?? [];
        $preservado = (int)($item['responsavel_preservado_id'] ?? 0);
        $r = array_key_exists('responsavel_planejado_id', $d) ? $d['responsavel_planejado_id'] : ($preservado ?: null);
        if ($r !== null && $r !== '') {
            $r = filter_var($r, FILTER_VALIDATE_INT);
            if (!$r || ($r !== $preservado && !in_array($r, $validos, true))) {
                throw new InvalidArgumentException('Responsável inválido, inativo ou sem vínculo elegível de Alteração.');
            }
        } else {
            $r = null;
        }
        if (array_key_exists('responsavel_preservado_id', $item) && (int)$r !== $preservado) {
            throw new RuntimeException('Encerre o ciclo operacional ativo antes de reatribuir esta imagem.', 409);
        }
        $datas = [];
        foreach (['inicio_planejado','fim_planejado'] as $campo) {
            $v = $d[$campo] ?? null;
            if ($v === '') {
                $v = null;
            }
            if ($v !== null && (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $v) || !entregas_valid_date($v))) {
                throw new InvalidArgumentException('Data planejada inválida.');
            }
            $datas[$campo] = $v;
        }
        if ($datas['inicio_planejado'] && $datas['fim_planejado'] && $datas['inicio_planejado'] > $datas['fim_planejado']) {
            throw new InvalidArgumentException('O início não pode ser posterior à conclusão.');
        }
        if ($confirmar && (!$r || !$datas['fim_planejado'])) {
            throw new InvalidArgumentException('Todas as imagens precisam de responsável válido e fim planejado.');
        }
        $nomes = array_column($colaboradores, 'nome', 'id');
        $saida[] = array_merge($item, ['responsavel_planejado_id' => $r,'responsavel_planejado_nome' => $r ? ($nomes[$r] ?? $item['responsavel_planejado_nome'] ?? null) : null,'fixado' => !empty($d['fixado']),'excecao_capacidade' => is_array($d['excecao_capacidade'] ?? null) ? $d['excecao_capacidade'] : null], $datas);
    }
    return $saida;
}

function flow_pa_rascunho_atual(?array $rascunho, array $raiz, string $fingerprint, string $capacidade): bool
{
    $contexto = $rascunho['contexto'] ?? [];
    return isset($rascunho['itens'])
        && ($contexto['versao_id'] ?? null) === (int)$raiz['versao_atual_id']
        && ($contexto['lock_version'] ?? null) === (int)$raiz['lock_version']
        && ($contexto['fingerprint'] ?? null) === $fingerprint
        && ($contexto['capacidade_fingerprint'] ?? null) === $capacidade;
}

function flow_planejamento_alteracao_carregar(mysqli $conn, int $id): array
{
    $e = flow_pa_entrega($conn, $id);
    if (!improov_usuario_pode_acessar_obra($conn, (int)$e['obra_id'])) {
        throw new RuntimeException('Sem permissão para acessar esta obra.', 403);
    }
    $p = flow_planejamento_carregar_raiz($conn, $id);
    if (!$p && flow_pa_manual_elegivel($conn, $e)) {
        flow_pa_escopo($conn, $id);
        throw new DomainException('Esta entrega não tem imagens pendentes de classificação para iniciar o planejamento.');
    }
    if (($p['modelo'] ?? '') !== 'IMAGENS_ALTERACAO') {
        throw new DomainException('Esta entrega utiliza o fluxo legado.');
    }
    $itens = flow_pa_escopo($conn, $id);
    $gestor = improov_usuario_eh_gestor_sidebar($conn);
    $fp = flow_pa_fingerprint($e, $itens);
    $config = flow_pac_config($conn);
    $v = !empty($p['versao_atual_id']) ? flow_planejamento_carregar_versao($conn, (int)$p['versao_atual_id']) : null;
    $confirmados = $v ? (flow_pa_snapshot_versao($conn, $v)['itens'] ?? []) : [];
    $confirmadosPorId = array_column($confirmados, null, 'entrega_item_id');
    $rascunho = $p['rascunho_json'] ? json_decode($p['rascunho_json'], true) : null;
    $itens = flow_pac_pontuar($itens, $config, $confirmados);
    $pessoas = flow_pa_colaboradores($conn);
    $rascunhoPorId = array_column($rascunho['itens'] ?? [], null, 'entrega_item_id');
    $baseAgenda = flow_pa_itens_editaveis($itens);
    foreach ($baseAgenda as &$i) {
        $i['fim_planejado'] = ($rascunhoPorId[$i['entrega_item_id']] ?? $confirmadosPorId[$i['entrega_item_id']] ?? [])['fim_planejado'] ?? null;
    }
    unset($i);
    $tarefasExternas = [];
    $capacidade = flow_pac_contexto($conn, $e, $baseAgenda, $pessoas, $config, $tarefasExternas);
    $retomar = flow_pa_rascunho_atual($rascunho, $p, $fp['fingerprint'], $capacidade['fingerprint']);
    if ($rascunho !== null && !$retomar) {
        foreach ($baseAgenda as &$i) {
            $i['fim_planejado'] = $confirmadosPorId[$i['entrega_item_id']]['fim_planejado'] ?? null;
        }
        unset($i);
        $capacidade = flow_pac_contexto($conn, $e, $baseAgenda, $pessoas, $config, $tarefasExternas);
    }
    foreach ($itens as &$i) {
        $i['nivel_atual'] = $i['nivel_complexidade_snapshot'];
        $i['pontos_replanejamento'] = $i['pontos_snapshot'];
    }
    unset($i);
    // Ao ler a versão vigente sem rascunho, os níveis/pontos vêm do seu snapshot.
    if ($v) {
        foreach ($itens as &$i) {
            $anterior = $confirmadosPorId[$i['entrega_item_id']] ?? null;
            if ($anterior && (!$retomar || !$i['pode_editar_datas'])) {
                $i['nivel_complexidade_snapshot'] = $anterior['nivel_complexidade_snapshot'];
                $i['pontos_snapshot'] = $anterior['pontos_snapshot'] ?? null;
            }
        }
    }
    unset($i);
    foreach ($itens as &$i) {
        $confirmado = $confirmadosPorId[$i['entrega_item_id']] ?? [];
        $decisao = $retomar && $i['pode_editar_datas'] ? ($rascunhoPorId[$i['entrega_item_id']] ?? $confirmado) : $confirmado;
        foreach (['responsavel_planejado_id','responsavel_planejado_nome','inicio_planejado','fim_planejado','fixado','excecao_capacidade'] as $campo) {
            $i[$campo] = $decisao[$campo] ?? null;
        }
        $i['responsavel_confirmado_id'] = $confirmado['responsavel_planejado_id'] ?? null;
        $i = flow_pa_preservar_responsavel($i, $confirmado);
        $i['atribuicao_divergente'] = $i['pode_editar_datas'] && $confirmado
            && (int)$confirmado['responsavel_planejado_id'] !== $i['responsavel_operacional_id'];
        $i['pode_transferir_execucao'] = $gestor && empty($e['arquivada']) && isset($i['execucao'])
            && !empty($confirmado['fim_planejado']) && !$i['atribuicao_divergente'];
    }
    unset($i);
    $elegiveis = count(flow_pa_itens_editaveis($itens));
    $editavel = $elegiveis > 0 && empty($e['arquivada']) && ($v || $elegiveis === count($itens));
    $desatualizado = $editavel && $v && !hash_equals($v['fingerprint'], $fp['fingerprint']);
    $p['rascunho_pendente'] = $retomar;
    $p['rascunho_requer_revisao'] = $p['rascunho_json'] !== null && !$retomar;
    $p['historico'] = flow_planejamento_historico($conn, $id);
    unset($p['rascunho_json']);
    if ($desatualizado) {
        $p['estado'] = 'DESATUALIZADO';
    }
    $b = !empty($p['baseline_versao_id']) ? flow_planejamento_carregar_versao($conn, (int)$p['baseline_versao_id']) : null;
    $realizado = flow_pac_realizado($conn, $id, $itens, $b ? (flow_pa_snapshot_versao($conn, $b)['itens'] ?? []) : [], $confirmados);
    $miniaturas = [];
    if ($itens) {
        $imagemIds = implode(',', array_map('intval', array_column($itens, 'imagem_id')));
        $miniaturas = array_flip(array_map('intval', array_column(flow_pa_rows($conn, "SELECT DISTINCT fi.imagem_id FROM historico_aprovacoes_imagens hi JOIN funcao_imagem fi ON fi.idfuncao_imagem=hi.funcao_imagem_id WHERE fi.imagem_id IN ($imagemIds) AND hi.imagem IS NOT NULL AND hi.imagem<>'' AND hi.imagem NOT LIKE '%imagem_%'"), 'imagem_id')));
    }
    foreach ($itens as &$i) {
        $i['thumbnail_url'] = isset($miniaturas[$i['imagem_id']]) ? 'thumbnail.php?imagem_id='.$i['imagem_id'] : null;
    }
    unset($i);
    return ['modelo' => 'IMAGENS_ALTERACAO','entrega' => $e,'itens' => $itens,'vigente_itens' => $confirmados,'realizado' => $realizado,'colaboradores' => $pessoas,'configuracao' => $config,'capacidade' => $capacidade,'tarefas_externas' => $tarefasExternas,'planejamento' => $p,'fingerprint' => $fp['fingerprint'],'desatualizado' => (bool)$desatualizado,'pode_editar' => $editavel && $gestor,'pode_configurar_capacidade' => $gestor];
}

/** Persiste uma versão imutável dentro da transação do chamador. */
function flow_pa_gravar_versao(mysqli $conn, array $entrega, array $plano, array $fp, array $snapshot, ?int $ator, string $motivo, string $nota): array
{
    $pid = (int)$plano['id'];
    $itens = $snapshot['itens'];
    $numero = 1 + (int)flow_pa_rows($conn, 'SELECT COALESCE(MAX(numero),0) AS n FROM entrega_planejamento_versao WHERE planejamento_id=?', 'i', [$pid])[0]['n'];
    flow_pa_query($conn, 'UPDATE entrega_planejamento_versao SET vigente=0,vigente_token=NULL WHERE planejamento_id=? AND vigente=1', 'i', [$pid])->close();
    $inicios = array_filter(array_column($itens, 'inicio_planejado'));
    $inicio = $inicios ? min($inicios) : ($entrega['data_recebimento'] ?: date('Y-m-d'));
    $fim = max(array_column($itens, 'fim_planejado'));
    $tipo = empty($plano['versao_atual_id']) ? 'BASELINE' : 'REPLANEJAMENTO';
    $stmt = flow_pa_query(
        $conn,
        "INSERT INTO entrega_planejamento_versao
        (planejamento_id,numero,tipo,vigente,vigente_token,fingerprint,data_inicio,prazo_r00,fim_previsto,status_plano,motivo_codigo,motivo_observacao,confirmado_por_colaborador_id,snapshot_json,contexto_fingerprint_json)
        VALUES (?,?,?,1,'VIGENTE',?,?,?,?,'PLANEJADO',?,?,?,?,?)",
        'iisssssssiss',
        [$pid, $numero, $tipo, $fp['fingerprint'], $inicio, $entrega['data_prevista'], $fim, $motivo ?: null, $nota ?: null, $ator,
            flow_planejamento_json($snapshot), flow_planejamento_json($fp['contexto'])]
    );
    $vid = (int)$stmt->insert_id;
    $stmt->close();
    $paramItens = [];
    foreach ($itens as $i) {
        array_push($paramItens, $vid, $i['entrega_item_id'], $i['funcao_imagem_id'], $i['pre_alt_item_id'], $i['responsavel_planejado_id'], $i['inicio_planejado'], $i['fim_planejado'], $i['nivel_complexidade_snapshot']);
    }
    $linhas = array_fill(0, count($itens), '(?,?,?,?,?,?,?,?)');
    flow_pa_query($conn, 'INSERT INTO entrega_planejamento_item (versao_id,entrega_item_id,funcao_imagem_id,pre_alt_item_id,responsavel_planejado_id,inicio_planejado,fim_planejado,nivel_complexidade_snapshot) VALUES '.implode(',', $linhas), str_repeat('iiiiissi', count($itens)), $paramItens)->close();
    return [$vid, $numero];
}

function flow_planejamento_alteracao_transferir(mysqli $conn, int $id, array $dados, ?int $ator, ?int $atorUsuario): array
{
    if (!improov_usuario_eh_gestor_sidebar($conn)) {
        throw new RuntimeException('Somente a gestão pode transferir a execução.', 403);
    }
    $itemId = filter_var($dados['entrega_item_id'] ?? null, FILTER_VALIDATE_INT);
    $novoId = filter_var($dados['novo_responsavel_id'] ?? null, FILTER_VALIDATE_INT);
    $dia = $dados['fim_planejado'] ?? null;
    $motivo = is_string($dados['motivo'] ?? null) ? trim($dados['motivo']) : '';
    if ($itemId < 1 || $novoId < 1 || $motivo === '' || mb_strlen($motivo) > 500) {
        throw new InvalidArgumentException('Selecione o novo responsável e descreva o motivo em até 500 caracteres.');
    }
    if (!is_string($dia) || !entregas_valid_date($dia) || !flow_pac_dia_util($dia) || $dia < date('Y-m-d')) {
        throw new InvalidArgumentException('Escolha um dia útil a partir de hoje para a conclusão.');
    }

    require_once __DIR__ . '/inicio_operacional_helper.php';
    $conn->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $conn->begin_transaction();
    try {
        $config = flow_pac_config($conn, true);
        $entrega = flow_pa_entrega($conn, $id, true);
        if (!improov_usuario_pode_acessar_obra($conn, (int)$entrega['obra_id'])) {
            throw new RuntimeException('Sem permissão para acessar esta obra.', 403);
        }
        $plano = flow_planejamento_carregar_raiz($conn, $id, true);
        if (($plano['modelo'] ?? '') !== 'IMAGENS_ALTERACAO' || empty($plano['versao_atual_id']) || !empty($entrega['arquivada'])) {
            throw new DomainException('A transferência exige uma entrega de alteração ativa e com planejamento confirmado.');
        }
        $escopo = flow_pa_escopo($conn, $id, true);
        $fp = flow_pa_fingerprint($entrega, $escopo);
        if ((int)($dados['lock_version'] ?? -1) !== (int)$plano['lock_version']
            || !hash_equals($fp['fingerprint'], (string)($dados['fingerprint'] ?? ''))) {
            throw new RuntimeException('O planejamento mudou. Recarregue os dados antes de transferir.', 409);
        }
        $porItem = array_column($escopo, null, 'entrega_item_id');
        $item = $porItem[$itemId] ?? null;
        $execucao = $item['execucao'] ?? null;
        if (!$execucao || $execucao['ciclo_id'] !== (int)($dados['ciclo_id'] ?? 0)
            || $execucao['situacao'] !== ($dados['situacao_ciclo'] ?? null) || $execucao['status'] !== ($dados['status_tarefa'] ?? null)) {
            throw new RuntimeException('A execução da imagem mudou. Recarregue os dados antes de transferir.', 409);
        }

        $versao = flow_planejamento_carregar_versao($conn, (int)$plano['versao_atual_id']);
        $snapshot = flow_pa_snapshot_versao($conn, $versao);
        $itens = array_column($snapshot['itens'] ?? [], null, 'entrega_item_id');
        $anterior = $itens[$itemId] ?? [];
        $anteriorId = $item['responsavel_operacional_id'];
        if (empty($anterior['fim_planejado']) || (int)$anterior['funcao_imagem_id'] !== $item['funcao_imagem_id']
            || (int)$anterior['responsavel_planejado_id'] !== $anteriorId) {
            throw new RuntimeException('A atribuição atual diverge do planejamento confirmado.', 409);
        }
        $pessoas = flow_pa_colaboradores($conn, true);
        $nomes = array_column($pessoas, 'nome', 'id');
        if ($novoId === $anteriorId || !isset($nomes[$novoId])) {
            throw new InvalidArgumentException('Escolha outro colaborador elegível para Alteração.');
        }
        $recebimento = substr($entrega['data_recebimento'] ?: date('Y-m-d'), 0, 10);
        if ($dia < max($recebimento, $anterior['inicio_planejado'] ?? '') || (strtotime($dia) - strtotime($recebimento)) / 86400 > 730) {
            throw new InvalidArgumentException('Escolha uma data dentro do período do planejamento e após o início previsto.');
        }

        $pontos = $anterior['pontos_snapshot'] ?? $config['niveis'][$anterior['nivel_complexidade_snapshot']] ?? null;
        if (!$pontos) {
            throw new DomainException('A imagem precisa de nível e pontos para validar a capacidade da transferência.');
        }
        $itens[$itemId] = array_replace($anterior, ['responsavel_planejado_id' => $novoId,
            'responsavel_planejado_nome' => $nomes[$novoId], 'responsavel_operacional_id' => $novoId,
            'fim_planejado' => $dia, 'pontos_snapshot' => $pontos, 'excecao_capacidade' => null]);
        unset($itens[$itemId]['responsavel_preservado_id']);
        $agenda = flow_pac_agenda($conn, $id, $config, $pessoas, $dia, $dia);
        $destino = array_filter($itens, static fn ($i) => ($porItem[$i['entrega_item_id']]['pode_editar_datas'] ?? false)
            && (int)$i['responsavel_planejado_id'] === $novoId && $i['fim_planejado'] === $dia);
        if (flow_pac_sobrecargas($destino, $agenda)) {
            throw new DomainException('O novo responsável não tem capacidade suficiente nesse dia. Escolha outra data ou outro colaborador.');
        }

        // A transferência cobre somente a imagem selecionada. Mudanças de escopo
        // que já exigiam replanejamento continuam pendentes depois da troca.
        $contextoAnterior = json_decode((string)$versao['contexto_fingerprint_json'], true);
        $contextos = [$contextoAnterior, $fp['contexto']];
        foreach ($contextos as &$contexto) {
            foreach ($contexto['itens'] ?? [] as $pos => $i) {
                unset($contexto['itens'][$pos]['pode_editar_responsavel']);
            }
        }
        unset($contexto);
        $fpVersao = $contextos[0] === $contextos[1] ? $fp : ['fingerprint' => $versao['fingerprint'], 'contexto' => $contextoAnterior];
        $movimento = ['entrega_item_id' => $itemId, 'imagem_nome' => $item['imagem_nome'],
            'responsavel_anterior_id' => $anteriorId, 'responsavel_anterior_nome' => $anterior['responsavel_planejado_nome'] ?? $nomes[$anteriorId] ?? null,
            'novo_responsavel_id' => $novoId, 'novo_responsavel_nome' => $nomes[$novoId],
            'fim_planejado_anterior' => $anterior['fim_planejado'], 'fim_planejado' => $dia, 'status' => $execucao['status']];
        $snapshot['itens'] = array_values($itens);
        $snapshot['transferencia_execucao'] = $movimento;
        $codigoMotivo = 'TRANSFERENCIA_EXECUCAO';
        $entregaPlanejada = array_replace($entrega, ['data_prevista' => $versao['prazo_r00']]);
        [$vid, $numero] = flow_pa_gravar_versao($conn, $entregaPlanejada, $plano, $fpVersao, $snapshot, $ator, $codigoMotivo, $motivo);
        $pid = (int)$plano['id'];
        flow_pa_query(
            $conn,
            "UPDATE entrega_planejamento_producao SET estado='CONFIRMADO',versao_atual_id=?,ultimo_fingerprint=?,lock_version=lock_version+1 WHERE id=?",
            'isi',
            [$vid, $fp['fingerprint'], $pid]
        )->close();

        $resultado = flow_inicio_operacional_transferir($conn, [
            'funcao_imagem_id' => $item['funcao_imagem_id'], 'novo_responsavel_id' => $novoId,
            'nova_previsao' => $dia, 'motivo_transferencia' => $motivo,
            'ator_colaborador_id' => $ator, 'ator_usuario_id' => $atorUsuario,
        ]);
        if (empty($resultado['ciclo_novo_id']) || $resultado['ciclo_anterior_id'] !== $execucao['ciclo_id']
            || array_map('intval', $resultado['membros']) !== [$item['funcao_imagem_id']]) {
            throw new RuntimeException('O ciclo operacional mudou. Recarregue os dados antes de transferir.', 409);
        }
        flow_planejamento_registrar_evento(
            $conn,
            $pid,
            $id,
            'REPLANEJAMENTO_CONFIRMADO',
            $vid,
            $ator,
            $codigoMotivo,
            $motivo,
            $movimento + ['numero' => $numero, 'ciclo_anterior_id' => $resultado['ciclo_anterior_id'], 'ciclo_novo_id' => $resultado['ciclo_novo_id']]
        );
        $conn->commit();
    } catch (Throwable $erro) {
        $conn->rollback();
        throw $erro;
    }
    return flow_planejamento_alteracao_carregar($conn, $id);
}

function flow_planejamento_alteracao_salvar(mysqli $conn, int $id, array $dados, bool $confirmar, ?int $ator): array
{
    if (!improov_usuario_eh_gestor_sidebar($conn)) {
        throw new RuntimeException('Somente a gestão pode planejar entregas.', 403);
    }
    $conn->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $conn->begin_transaction();
    try {
        // Serializa confirmações R01+ de entregas diferentes e mudanças de parâmetros.
        $config = flow_pac_config($conn, true);
        $e = flow_pa_entrega($conn, $id, true);
        if (!improov_usuario_pode_acessar_obra($conn, (int)$e['obra_id'])) {
            throw new RuntimeException('Sem permissão para acessar esta obra.', 403);
        }
        $p = flow_planejamento_carregar_raiz($conn, $id, true);
        if (($p['modelo'] ?? '') !== 'IMAGENS_ALTERACAO') {
            throw new DomainException('Esta entrega utiliza o fluxo legado.');
        }
        $escopo = flow_pa_escopo($conn, $id, true);
        $fp = flow_pa_fingerprint($e, $escopo);
        if ((int)($dados['lock_version'] ?? -1) !== (int)$p['lock_version']) {
            throw new RuntimeException('O plano foi alterado por outro gestor. Recarregue a página.', 409);
        }
        if (!hash_equals($fp['fingerprint'], (string)($dados['fingerprint'] ?? ''))) {
            throw new RuntimeException('O escopo da entrega mudou. Recarregue e revise o planejamento.', 409);
        }
        if (!$escopo || !empty($e['arquivada'])) {
            throw new DomainException('A entrega não está disponível para planejamento.');
        }
        $temVersao = !empty($p['versao_atual_id']);
        if ($temVersao && empty($dados['replanejar'])) {
            throw new DomainException('Use Replanejar para preservar a versão confirmada.');
        }
        $pessoas = flow_pa_colaboradores($conn, true);
        $anterior = $temVersao ? flow_planejamento_carregar_versao($conn, (int)$p['versao_atual_id']) : null;
        $confirmados = $anterior ? (flow_pa_snapshot_versao($conn, $anterior)['itens'] ?? []) : [];
        $porId = array_column($confirmados, null, 'entrega_item_id');
        $ciclosResiduais = array_column($escopo, 'ciclo_residual_id', 'funcao_imagem_id');
        $editaveis = array_map(static function ($item) use ($porId) {
            $anterior = $porId[$item['entrega_item_id']] ?? [];
            unset($item['ciclo_residual_id'], $item['execucao']);
            return flow_pa_preservar_responsavel($item, $anterior) + [
                'responsavel_planejado_nome' => $anterior['responsavel_planejado_nome'] ?? null,
            ];
        }, flow_pa_itens_editaveis($escopo));
        if (!$editaveis || (!$temVersao && count($editaveis) !== count($escopo))) {
            throw new DomainException('Não há escopo disponível para planejar.');
        }
        $congelados = [];
        foreach ($escopo as $i) {
            if (!$i['pode_editar_datas']) {
                $original = $porId[$i['entrega_item_id']] ?? null;
                if (!$original || (int)$original['funcao_imagem_id'] !== $i['funcao_imagem_id']) {
                    throw new RuntimeException('Imagem bloqueada sem item correspondente na versão vigente. Revise o escopo.', 409);
                }
                $congelados[] = $original;
            }
        }
        $decisoes = $dados['itens'] ?? [];
        if (array_intersect(array_column($decisoes, 'entrega_item_id'), array_column($congelados, 'entrega_item_id'))) {
            throw new RuntimeException('Uma das imagens informadas está bloqueada. Recarregue e revise o planejamento.', 409);
        }
        $pendentes = flow_pa_validar_itens(flow_pac_pontuar($editaveis, $config, $confirmados), $decisoes, $pessoas, $confirmar);
        $atribuicoes = [];
        $ciclosParaEncerrar = [];
        foreach ($pendentes as $i) {
            $responsavelAnterior = (int)($porId[$i['entrega_item_id']]['responsavel_planejado_id'] ?? $i['responsavel_operacional_id']);
            $responsavel = (int)$i['responsavel_planejado_id'];
            $tarefaId = $i['funcao_imagem_id'];
            if ($confirmar && $responsavel !== $responsavelAnterior) {
                if ($responsavel !== $i['responsavel_operacional_id']) {
                    $atribuicoes[$tarefaId] = $responsavel;
                }
                if (isset($ciclosResiduais[$tarefaId])) {
                    $ciclosParaEncerrar[$tarefaId] = $ciclosResiduais[$tarefaId];
                }
            }
            if ($i['fim_planejado'] && (!flow_pac_dia_util($i['fim_planejado']) || $i['fim_planejado'] < substr($e['data_recebimento'], 0, 10))) {
                throw new InvalidArgumentException('Escolha um dia útil a partir do recebimento.');
            }
            if ($confirmar && empty($i['pontos_snapshot'])) {
                throw new InvalidArgumentException('Todas as imagens precisam de nível e pontos válidos.');
            }
        }
        $capacidade = flow_pac_contexto($conn, $e, $pendentes, $pessoas, $config);
        if (!hash_equals($capacidade['fingerprint'], (string)($dados['capacidade_fingerprint'] ?? ''))) {
            throw new RuntimeException('A capacidade ou configuração mudou. Recarregue e revise o plano.', 409);
        }
        $rascunho = $p['rascunho_json'] ? json_decode($p['rascunho_json'], true) : null;
        if ($rascunho !== null && !flow_pa_rascunho_atual($rascunho, $p, $fp['fingerprint'], $capacidade['fingerprint']) && ($dados['revisar_rascunho'] ?? false) !== true) {
            throw new RuntimeException('O contexto do rascunho mudou. Revise a versão vigente antes de continuar.', 409);
        }
        $sobrecargas = flow_pac_sobrecargas($pendentes, $capacidade['agenda']);
        foreach ($sobrecargas as $s) {
            $aceita = false;
            foreach ($pendentes as $i) {
                if ($i['responsavel_planejado_id'] === $s['colaborador_id'] && $i['fim_planejado'] === $s['dia']) {
                    $exc = $i['excecao_capacidade'];
                    if (($exc['fingerprint'] ?? '') === $capacidade['fingerprint'] && ($exc['dia'] ?? '') === $s['dia'] && (int)($exc['colaborador_id'] ?? 0) === $s['colaborador_id'] && (float)($exc['excesso'] ?? 0) >= $s['excesso']) {
                        $aceita = true;
                    }
                }
            }
            if (!$aceita && $confirmar) {
                throw new DomainException('Capacidade excedida. Confirme explicitamente a exceção antes de confirmar o plano.');
            }
        }
        $pid = (int)$p['id'];
        if (!$confirmar) {
            $json = flow_planejamento_json(['contexto' => [
                'versao_id' => (int)$p['versao_atual_id'],
                'lock_version' => (int)$p['lock_version'] + 1,
                'fingerprint' => $fp['fingerprint'],
                'capacidade_fingerprint' => $capacidade['fingerprint'],
            ], 'itens' => $pendentes]);
            $estado = $temVersao ? 'REPLANEJAMENTO' : 'RASCUNHO';
            flow_pa_query($conn, 'UPDATE entrega_planejamento_producao SET rascunho_json=?,estado=?,ultimo_fingerprint=?,lock_version=lock_version+1 WHERE id=?', 'sssi', [$json,$estado,$fp['fingerprint'],$pid])->close();
            flow_planejamento_registrar_evento($conn, $pid, $id, 'RASCUNHO_SALVO', null, $ator);
        } else {
            $motivo = trim((string)($dados['motivo_codigo'] ?? ''));
            $nota = trim((string)($dados['motivo_observacao'] ?? ''));
            if (mb_strlen($motivo) > 40 || mb_strlen($nota) > 500 || ($temVersao && (!$motivo || ($motivo === 'OUTRO' && !$nota)))) {
                throw new InvalidArgumentException('Informe um motivo válido para o replanejamento (observação até 500 caracteres).');
            }
            $itens = array_merge($pendentes, $congelados);
            usort($itens, fn ($a, $b) => $a['entrega_item_id'] <=> $b['entrega_item_id']);
            $snapshot = ['modelo' => 'IMAGENS_ALTERACAO','entrega' => $e,'itens' => $itens,'configuracao' => $config,'capacidade' => $capacidade,'excecoes_capacidade' => $sobrecargas];
            [$vid, $numero] = flow_pa_gravar_versao($conn, $e, $p, $fp, $snapshot, $ator, $motivo, $nota);
            foreach ($sobrecargas as $s) {
                $percentual = $s['capacidade'] > 0 ? 100 * ($s['pontos'] + $s['comprometidos']) / $s['capacidade'] : 9999;
                $hash = hash('sha256', $capacidade['fingerprint'].':'.$s['colaborador_id'].':'.$s['dia']);
                flow_pa_query($conn, "INSERT INTO entrega_planejamento_capacidade_validacao (planejamento_id,versao_id,entrega_id,obra_id,colaborador_id,codigo_etapa,data_inicio,data_fim,carga_percentual,pico_carga,fingerprint,status,observacao,validado_por_colaborador_id,validado_em) VALUES (?,?,?,?,?,'ALTERACAO_PONTOS',?,?,?,?,?,'VALIDA','Exceção explícita no planner diário',?,NOW())", 'iiiiissddsi', [$pid,$vid,$id,(int)$e['obra_id'],$s['colaborador_id'],$s['dia'],$s['dia'],$percentual,$percentual / 100,$hash,$ator])->close();
            }
            if ($ciclosParaEncerrar) {
                require_once __DIR__ . '/janela_operacional_helper.php';
                $atorUsuario = isset($_SESSION['idusuario']) ? (int)$_SESSION['idusuario'] : null;
                foreach ($ciclosParaEncerrar as $tarefaId => $cicloId) {
                    $encerrado = flow_janela_encerrar_ciclo($conn, $tarefaId, 'REGULARIZACAO_REPLANEJAMENTO', $ator, $atorUsuario);
                    if (($encerrado['ciclo_id'] ?? null) !== $cicloId) {
                        throw new RuntimeException('O ciclo operacional mudou. Recarregue e revise o planejamento.', 409);
                    }
                }
            }
            foreach ($atribuicoes as $tarefaId => $responsavel) {
                flow_pa_query($conn, 'UPDATE funcao_imagem SET colaborador_id=? WHERE idfuncao_imagem=?', 'ii', [$responsavel, $tarefaId])->close();
            }
            flow_pa_query($conn, "UPDATE entrega_planejamento_producao SET estado='CONFIRMADO',versao_atual_id=?,baseline_versao_id=COALESCE(baseline_versao_id,?),rascunho_json=NULL,ultimo_fingerprint=?,lock_version=lock_version+1 WHERE id=?", 'iisi', [$vid,$vid,$fp['fingerprint'],$pid])->close();
            flow_planejamento_registrar_evento($conn, $pid, $id, $temVersao ? 'REPLANEJAMENTO_CONFIRMADO' : 'PLANO_CONFIRMADO', $vid, $ator, $motivo ?: null, $nota ?: null, ['modelo' => 'IMAGENS_ALTERACAO','numero' => $numero,'fingerprint' => $fp['fingerprint'],'itens' => count($itens)]);
        }
        $conn->commit();
    } catch (Throwable $erro) {
        $conn->rollback();
        throw $erro;
    }
    return flow_planejamento_alteracao_carregar($conn,$id);
}
