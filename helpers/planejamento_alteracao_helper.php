<?php

/** Planejamento manual por rodada. O motor de funções R00 permanece separado. */
require_once __DIR__ . '/planejamento_producao_helper.php';
require_once __DIR__ . '/planejamento_alteracao_capacidade_helper.php';
require_once __DIR__ . '/planejamento_alteracao_realizado_helper.php';

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
    return (string) ($raiz['modelo'] ?? 'FUNCOES_R00');
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
    $ativos = $tarefaIds ? array_flip(array_column(flow_pa_rows($conn, "SELECT ci.funcao_imagem_id FROM janela_operacional_ciclo c JOIN janela_operacional_ciclo_item ci ON ci.ciclo_id=c.id WHERE ci.funcao_imagem_id IN ($tarefaIds) AND c.ativo_token='ATIVO' ORDER BY ci.funcao_imagem_id".($lock ? ' FOR UPDATE' : '')), 'funcao_imagem_id')) : [];
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
        if (count($ts) !== 1) {
            throw new DomainException('Cada imagem precisa de uma única tarefa de Alteração.');
        }
        if (count($ps) !== 1 || $ps[0]['resultado'] !== 'ALTERACAO') {
            throw new DomainException('A origem da Pré-alteração não é compatível com a imagem da entrega.');
        }
        if ((int)$r['imagem_obra_id'] !== (int)$r['entrega_obra_id']) {
            throw new DomainException('A imagem não pertence à obra da entrega.');
        }
        $tarefa = $ts[0];
        $r['rodada_substituida'] = $rodadasAtuais[$r['imagem_id']] !== $id;
        $estado = flow_pa_estado_item($r, (string)$tarefa['status'], $ciclos[$r['id']] ?? null, isset($ativos[$tarefa['idfuncao_imagem']]));
        $saida[] = ['entrega_item_id' => (int)$r['id'],'imagem_id' => (int)$r['imagem_id'],'imagem_nome' => $r['imagem_nome'],'tipo_imagem' => (string)($r['tipo_imagem'] ?? ''),
            'funcao_imagem_id' => (int)$ts[0]['idfuncao_imagem'],'pre_alt_item_id' => (int)$ps[0]['id'],'nivel_complexidade_snapshot' => $ps[0]['nivel_complexidade'] === null ? null : (int)$ps[0]['nivel_complexidade'],
            'etapa_atual_id' => (int)$r['etapa_atual_id'],'data_entregue' => $r['data_entregue'],'tarefa_cancelada' => flow_planejamento_normalizar((string)$ts[0]['status']) === 'cancelado',
            'responsavel_planejado_id' => null,'inicio_planejado' => null,'fim_planejado' => null] + $estado + [
            'responsavel_operacional_id' => (int)$tarefa['colaborador_id']];
    }
    return $saida;
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
    if (($p['modelo'] ?? '') !== 'IMAGENS_ALTERACAO') {
        throw new DomainException('Esta entrega utiliza o fluxo legado.');
    }
    $itens = flow_pa_escopo($conn, $id);
    $fp = flow_pa_fingerprint($e, $itens);
    $config = flow_pac_config($conn);
    $v = !empty($p['versao_atual_id']) ? flow_planejamento_carregar_versao($conn, (int)$p['versao_atual_id']) : null;
    $confirmados = $v ? (json_decode($v['snapshot_json'], true)['itens'] ?? []) : [];
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
    $capacidade = flow_pac_contexto($conn, $e, $baseAgenda, $pessoas, $config);
    $retomar = flow_pa_rascunho_atual($rascunho, $p, $fp['fingerprint'], $capacidade['fingerprint']);
    if ($rascunho !== null && !$retomar) {
        foreach ($baseAgenda as &$i) {
            $i['fim_planejado'] = $confirmadosPorId[$i['entrega_item_id']]['fim_planejado'] ?? null;
        }
        unset($i);
        $capacidade = flow_pac_contexto($conn, $e, $baseAgenda, $pessoas, $config);
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
    $realizado = flow_pac_realizado($conn, $id, $itens, $b ? (json_decode($b['snapshot_json'], true)['itens'] ?? []) : [], $confirmados);
    $miniaturas = [];
    if ($itens) {
        $imagemIds = implode(',', array_map('intval', array_column($itens, 'imagem_id')));
        $miniaturas = array_flip(array_map('intval', array_column(flow_pa_rows($conn, "SELECT DISTINCT fi.imagem_id FROM historico_aprovacoes_imagens hi JOIN funcao_imagem fi ON fi.idfuncao_imagem=hi.funcao_imagem_id WHERE fi.imagem_id IN ($imagemIds) AND hi.imagem IS NOT NULL AND hi.imagem<>'' AND hi.imagem NOT LIKE '%imagem_%'"), 'imagem_id')));
    }
    foreach ($itens as &$i) {
        $i['thumbnail_url'] = isset($miniaturas[$i['imagem_id']]) ? 'thumbnail.php?imagem_id='.$i['imagem_id'] : null;
    }
    unset($i);
    return ['modelo' => 'IMAGENS_ALTERACAO','entrega' => $e,'itens' => $itens,'vigente_itens' => $confirmados,'realizado' => $realizado,'colaboradores' => $pessoas,'configuracao' => $config,'capacidade' => $capacidade,'planejamento' => $p,'fingerprint' => $fp['fingerprint'],'desatualizado' => (bool)$desatualizado,'pode_editar' => $editavel && improov_usuario_eh_gestor_sidebar($conn)];
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
        $confirmados = $anterior ? (json_decode($anterior['snapshot_json'], true)['itens'] ?? []) : [];
        $porId = array_column($confirmados, null, 'entrega_item_id');
        $editaveis = array_map(static function ($item) use ($porId) {
            $anterior = $porId[$item['entrega_item_id']] ?? [];
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
        foreach ($pendentes as $i) {
            $responsavelAnterior = (int)($porId[$i['entrega_item_id']]['responsavel_planejado_id'] ?? $i['responsavel_operacional_id']);
            $responsavel = (int)$i['responsavel_planejado_id'];
            if ($confirmar && $responsavel !== $responsavelAnterior && $responsavel !== $i['responsavel_operacional_id']) {
                $atribuicoes[$i['funcao_imagem_id']] = $responsavel;
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
            $numero = 1 + (int)(flow_pa_rows($conn, 'SELECT COALESCE(MAX(numero),0) AS n FROM entrega_planejamento_versao WHERE planejamento_id=?', 'i', [$pid])[0]['n']);
            flow_pa_query($conn, 'UPDATE entrega_planejamento_versao SET vigente=0,vigente_token=NULL WHERE planejamento_id=? AND vigente=1', 'i', [$pid])->close();
            $fins = array_column($itens, 'fim_planejado');
            $inicios = array_values(array_filter(array_column($itens, 'inicio_planejado')));
            $inicio = $inicios ? min($inicios) : ($e['data_recebimento'] ?: date('Y-m-d'));
            $fim = max($fins);
            $tipo = $temVersao ? 'REPLANEJAMENTO' : 'BASELINE';
            $snapshot = flow_planejamento_json(['modelo' => 'IMAGENS_ALTERACAO','entrega' => $e,'itens' => $itens,'configuracao' => $config,'capacidade' => $capacidade,'excecoes_capacidade' => $sobrecargas]);
            $contexto = flow_planejamento_json($fp['contexto']);
            $stmt = flow_pa_query($conn, "INSERT INTO entrega_planejamento_versao (planejamento_id,numero,tipo,vigente,vigente_token,fingerprint,data_inicio,prazo_r00,fim_previsto,status_plano,motivo_codigo,motivo_observacao,confirmado_por_colaborador_id,snapshot_json,contexto_fingerprint_json) VALUES (?,?,?,1,'VIGENTE',?,?,?,?,'PLANEJADO',?,?,?,?,?)", 'iisssssssiss', [$pid,$numero,$tipo,$fp['fingerprint'],$inicio,$e['data_prevista'],$fim,$motivo ?: null,$nota ?: null,$ator,$snapshot,$contexto]);
            $vid = (int)$stmt->insert_id;
            $stmt->close();
            foreach ($sobrecargas as $s) {
                $percentual = $s['capacidade'] > 0 ? 100 * ($s['pontos'] + $s['comprometidos']) / $s['capacidade'] : 9999;
                $hash = hash('sha256', $capacidade['fingerprint'].':'.$s['colaborador_id'].':'.$s['dia']);
                flow_pa_query($conn, "INSERT INTO entrega_planejamento_capacidade_validacao (planejamento_id,versao_id,entrega_id,obra_id,colaborador_id,codigo_etapa,data_inicio,data_fim,carga_percentual,pico_carga,fingerprint,status,observacao,validado_por_colaborador_id,validado_em) VALUES (?,?,?,?,?,'ALTERACAO_PONTOS',?,?,?,?,?,'VALIDA','Exceção explícita no planner diário',?,NOW())", 'iiiiissddsi', [$pid,$vid,$id,(int)$e['obra_id'],$s['colaborador_id'],$s['dia'],$s['dia'],$percentual,$percentual / 100,$hash,$ator])->close();
            }
            $linhas = [];
            $paramItens = [];
            foreach ($itens as $i) {
                $linhas[] = '(?,?,?,?,?,?,?,?)';
                array_push($paramItens, $vid, $i['entrega_item_id'], $i['funcao_imagem_id'], $i['pre_alt_item_id'], $i['responsavel_planejado_id'], $i['inicio_planejado'], $i['fim_planejado'], $i['nivel_complexidade_snapshot']);
            }
            flow_pa_query($conn, 'INSERT INTO entrega_planejamento_item (versao_id,entrega_item_id,funcao_imagem_id,pre_alt_item_id,responsavel_planejado_id,inicio_planejado,fim_planejado,nivel_complexidade_snapshot) VALUES '.implode(',', $linhas), str_repeat('iiiiissi', count($itens)), $paramItens)->close();
            foreach ($atribuicoes as $tarefaId => $responsavel) {
                flow_pa_query($conn, 'UPDATE funcao_imagem SET colaborador_id=? WHERE idfuncao_imagem=?', 'ii', [$responsavel, $tarefaId])->close();
            }
            flow_pa_query($conn,"UPDATE entrega_planejamento_producao SET estado='CONFIRMADO',versao_atual_id=?,baseline_versao_id=COALESCE(baseline_versao_id,?),rascunho_json=NULL,ultimo_fingerprint=?,lock_version=lock_version+1 WHERE id=?",'iisi',[$vid,$vid,$fp['fingerprint'],$pid])->close();
            flow_planejamento_registrar_evento($conn,$pid,$id,$temVersao ? 'REPLANEJAMENTO_CONFIRMADO' : 'PLANO_CONFIRMADO',$vid,$ator,$motivo ?: null,$nota ?: null,['modelo' => 'IMAGENS_ALTERACAO','numero' => $numero,'fingerprint' => $fp['fingerprint'],'itens' => count($itens)]);
        }
        $conn->commit();
    } catch (Throwable $erro) {
        $conn->rollback();
        throw $erro;
    }
    return flow_planejamento_alteracao_carregar($conn,$id);
}
