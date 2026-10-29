<?php
/** Read-only operational view: never creates plans, assigns people or updates schema. */
require_once __DIR__ . '/../Entregas/prazo_entrega_helper.php';

function flow_alt_semana(?string $data = null, ?string $hoje = null): array
{
    $tz = new DateTimeZone('America/Sao_Paulo');
    $hoje = $hoje ?? (new DateTimeImmutable('now', $tz))->format('Y-m-d');
    if ($data !== null && !entregas_valid_date($data)) throw new InvalidArgumentException('Semana inválida. Use AAAA-MM-DD.');
    $ref = new DateTimeImmutable($data ?? $hoje, $tz);
    $segunda = $ref->modify('-' . ((int)$ref->format('N') - 1) . ' days');
    $dias = [];
    $nomes = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira'];
    for ($n = 0; $n < 5; $n++) {
        $dia = $segunda->modify("+$n days")->format('Y-m-d');
        $util = entregas_adicionar_dias_uteis(date('Y-m-d', strtotime($dia . ' -1 day')), 1) === $dia;
        $dias[] = ['data' => $dia, 'nome' => $nomes[$n], 'util' => $util, 'hoje' => $dia === $hoje];
    }
    return ['inicio' => $dias[0]['data'], 'fim' => $dias[4]['data'], 'hoje' => $hoje,
        'anterior' => $segunda->modify('-7 days')->format('Y-m-d'),
        'proxima' => $segunda->modify('+7 days')->format('Y-m-d'), 'dias' => $dias];
}

function flow_alt_concluida(string $status): bool
{
    return in_array(mb_strtolower(trim($status), 'UTF-8'), ['finalizado', 'aprovado'], true);
}

function flow_alt_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function flow_alt_item(array $r, array $semana): array
{
    $confirmado = ($r['modelo'] ?? '') === 'IMAGENS_ALTERACAO' && !empty($r['versao_atual_id']) && !empty($r['fim_planejado']);
    $dia = $confirmado ? $r['fim_planejado'] : null;
    $resp = $confirmado ? $r['responsavel_planejado_id'] : $r['colaborador_id'];
    $status = trim((string)($r['status_funcao'] ?? '')) ?: 'Não iniciado';
    $concluida = flow_alt_concluida($status);
    $ef = (int)$r['imagem_status_id'] === 6;
    return [
        'funcao_id' => (int)$r['idfuncao_imagem'], 'imagem_id' => (int)$r['imagem_id'], 'imagem_nome' => $r['imagem_nome'],
        'obra_id' => (int)$r['obra_id'], 'obra_nome' => $r['nomenclatura'], 'obra_nome_comercial' => $r['nome_obra'],
        'colaborador_id' => $resp ? (int)$resp : null,
        'colaborador_nome' => $confirmado ? $r['responsavel_planejado_nome'] : $r['nome_colaborador'],
        'status_funcao' => $status, 'status_nome' => $r['nome_status'], 'idstatus' => (int)$r['imagem_status_id'],
        'nivel_complexidade' => $confirmado ? (isset($r['nivel_snapshot']) ? (int)$r['nivel_snapshot'] : null) : (isset($r['nivel_complexidade']) ? (int)$r['nivel_complexidade'] : null),
        'is_ef' => $ef, 'concluida' => $concluida, 'dia_planejado' => $ef ? null : $dia, 'planejada' => !$ef && $confirmado,
        'atrasada' => !$ef && $dia && $dia < $semana['hoje'] && !$concluida,
        'prazo' => !empty($r['prazo']) ? date('d/m/Y', strtotime($r['prazo'])) : null,
        'entrega_id' => !empty($r['entrega_id']) ? (int)$r['entrega_id'] : null,
        'entrega_item_id' => !empty($r['entrega_item_id']) ? (int)$r['entrega_item_id'] : null,
        'planejamento_versao_id' => $confirmado ? (int)$r['versao_atual_id'] : null,
        'planejamento_url' => !$ef && ($r['modelo'] ?? '') === 'IMAGENS_ALTERACAO' ? '../PlanejamentoProducao/index.php?entrega_id=' . (int)$r['entrega_id'] : null,
        'sem_planejamento_motivo' => $confirmado ? null : (($r['modelo'] ?? '') === 'IMAGENS_ALTERACAO' ? 'Aguardando confirmação' : 'Fluxo legado'),
        'thumbnail_url' => null,
    ];
}

function flow_alt_planner_carregar(mysqli $conn, array $semana, array $acesso): array
{
    // Same identity as contexto_operacional: latest release for image + current stage.
    // Ranked release prevents duplicate cards and stale allocations for repeated R05.
    $joins = "LEFT JOIN (
        SELECT pai.imagem_id, e.status_id, pli.entrega_destino_id,
            ROW_NUMBER() OVER (PARTITION BY pai.imagem_id,e.status_id ORDER BY pli.id DESC) AS posicao
        FROM pre_alt_itens pai
        JOIN pre_alt_liberacao_itens pli ON pli.pre_alt_item_id=pai.id
        JOIN entregas e ON e.id=pli.entrega_destino_id
        ) rodada ON rodada.imagem_id=f.imagem_id AND rodada.status_id=i.status_id AND rodada.posicao=1
        LEFT JOIN entregas_itens ei ON ei.entrega_id=rodada.entrega_destino_id AND ei.imagem_id=f.imagem_id
        LEFT JOIN entrega_planejamento_producao p ON p.entrega_id=rodada.entrega_destino_id
        LEFT JOIN entrega_planejamento_item pi ON pi.versao_id=p.versao_atual_id AND pi.entrega_item_id=ei.id
             AND pi.funcao_imagem_id=f.idfuncao_imagem AND p.modelo='IMAGENS_ALTERACAO'
        LEFT JOIN colaborador c ON c.idcolaborador=CASE WHEN p.modelo='IMAGENS_ALTERACAO'
            AND p.versao_atual_id IS NOT NULL AND pi.fim_planejado IS NOT NULL
            THEN pi.responsavel_planejado_id ELSE f.colaborador_id END";
    $planFields = 'rodada.entrega_destino_id AS entrega_id, ei.id AS entrega_item_id, p.modelo, p.versao_atual_id,
        pi.fim_planejado, pi.responsavel_planejado_id, c.nome_colaborador AS responsavel_planejado_nome,
        pi.nivel_complexidade_snapshot AS nivel_snapshot';
    $levelField = entregas_table_has_column($conn, 'alteracoes', 'nivel_complexidade') ? 'a.nivel_complexidade' : 'NULL AS nivel_complexidade';
    $fields = "SELECT f.idfuncao_imagem, f.imagem_id, f.colaborador_id,
        COALESCE(NULLIF(TRIM(f.status), ''), 'Não iniciado') AS status_funcao,
        i.prazo, i.imagem_nome, i.obra_id, o.nomenclatura, o.nome_obra, c.nome_colaborador,
        s.nome_status, a.status_id AS imagem_status_id, $levelField, ";
    $base = ' FROM alteracoes a JOIN funcao_imagem f ON f.idfuncao_imagem=a.funcao_id
        JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=f.imagem_id
        JOIN obra o ON o.idobra=i.obra_id JOIN status_imagem s ON s.idstatus=a.status_id ';
    $where = " WHERE f.funcao_id=6 AND o.status_obra=0 AND a.status_id=i.status_id AND COALESCE(f.status,'') <> 'Cancelado'";
    $params = []; $types = '';
    if (($acesso['modo'] ?? '') !== 'gestao') {
        $where .= " AND EXISTS (SELECT 1 FROM obra_pacote op WHERE op.obra_id=i.obra_id AND op.tipo=? AND op.status='ATIVO')";
        $params[] = $acesso['pacote_tipo'] ?? ''; $types = 's';
    }
    $disponivel = true;
    try {
        $rows = flow_alt_rows($conn, $fields . $planFields . $base . $joins . $where, $types, $params);
    } catch (mysqli_sql_exception $error) {
        if (!in_array((int)$error->getCode(), [1054, 1146], true)) throw $error;
        // No migration/backfill on GET. Old installations keep their queue.
        $disponivel = false;
        $rows = flow_alt_rows($conn, $fields . 'NULL AS entrega_id, NULL AS entrega_item_id, NULL AS modelo,
            NULL AS versao_atual_id, NULL AS fim_planejado, NULL AS responsavel_planejado_id,
            NULL AS responsavel_planejado_nome, NULL AS nivel_snapshot' . $base . ' LEFT JOIN colaborador c ON c.idcolaborador=f.colaborador_id ' . $where, $types, $params);
    }
    // Completed rounds must survive a task advancing to another image stage or release.
    // Do not read the reused task's status as the status of an older round.
    if ($disponivel) {
        $historicoWhere = " WHERE p.modelo='IMAGENS_ALTERACAO' AND o.status_obra=0
            AND pi.fim_planejado BETWEEN ? AND ?
            AND (ei.data_entregue IS NOT NULL OR (jc.situacao='ENCERRADO' AND jc.motivo_encerramento='CONCLUSAO'))";
        $historyParams = [$semana['inicio'], $semana['fim']]; $historyTypes = 'ss';
        if (($acesso['modo'] ?? '') !== 'gestao') {
            $historicoWhere .= " AND EXISTS (SELECT 1 FROM obra_pacote op WHERE op.obra_id=i.obra_id AND op.tipo=? AND op.status='ATIVO')";
            $historyParams[] = $acesso['pacote_tipo'] ?? ''; $historyTypes .= 's';
        }
        $historico = flow_alt_rows($conn, "SELECT f.idfuncao_imagem,f.imagem_id,f.colaborador_id,'Finalizado' AS status_funcao,
            i.prazo,i.imagem_nome,i.obra_id,o.nomenclatura,o.nome_obra,NULL AS nome_colaborador,s.nome_status,e.status_id AS imagem_status_id,
            NULL AS nivel_complexidade,e.id AS entrega_id,ei.id AS entrega_item_id,p.modelo,p.versao_atual_id,pi.fim_planejado,
            pi.responsavel_planejado_id,cp.nome_colaborador AS responsavel_planejado_nome,pi.nivel_complexidade_snapshot AS nivel_snapshot
            FROM entrega_planejamento_producao p JOIN entrega_planejamento_item pi ON pi.versao_id=p.versao_atual_id
            JOIN entregas e ON e.id=p.entrega_id JOIN entregas_itens ei ON ei.id=pi.entrega_item_id AND ei.entrega_id=e.id
            JOIN funcao_imagem f ON f.idfuncao_imagem=pi.funcao_imagem_id AND f.imagem_id=ei.imagem_id
            JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=ei.imagem_id JOIN obra o ON o.idobra=i.obra_id
            JOIN status_imagem s ON s.idstatus=e.status_id LEFT JOIN colaborador cp ON cp.idcolaborador=pi.responsavel_planejado_id
            LEFT JOIN (SELECT entrega_item_id,situacao,motivo_encerramento,
                ROW_NUMBER() OVER (PARTITION BY entrega_item_id ORDER BY id DESC) AS posicao
                FROM janela_operacional_ciclo WHERE entrega_item_id IS NOT NULL) jc ON jc.entrega_item_id=ei.id AND jc.posicao=1" . $historicoWhere, $historyTypes, $historyParams);
        $atuaisPorItem = array_flip(array_filter(array_column($rows, 'entrega_item_id')));
        foreach ($historico as $r) {
            if (!isset($atuaisPorItem[$r['entrega_item_id']])) { $r['historica'] = true; $rows[] = $r; }
        }
    }
    $items = []; $mapas = ['obras' => [], 'colaboradores' => [], 'status_imagens' => []];
    foreach ($rows as $r) {
        $item = flow_alt_item($r, $semana);
        $item['historica'] = !empty($r['historica']);
        if ($item['historica']) $item['detalhe_url'] = $item['planejamento_url'];
        $naSemana = $item['dia_planejado'] && $item['dia_planejado'] >= $semana['inicio'] && $item['dia_planejado'] <= $semana['fim'];
        if ($item['concluida'] && !$naSemana && !(!$item['dia_planejado'] && substr((string)$r['prazo'], 0, 10) === $semana['hoje'])) continue;
        $mapas['obras'][$item['obra_id']] = $item['obra_nome'];
        if ($item['colaborador_id']) $mapas['colaboradores'][$item['colaborador_id']] = $item['colaborador_nome'];
        $mapas['status_imagens'][$item['idstatus']] = $item['status_nome'];
        if ($item['is_ef'] || !$item['dia_planejado'] || $naSemana) $items[$item['entrega_item_id'] ? 'entrega:' . $item['entrega_item_id'] : 'tarefa:' . $item['funcao_id']] = $item;
    }
    // Thumbnail availability is aggregated. Only image bytes are requested individually/lazily.
    if ($items) {
        $ids = implode(',', array_unique(array_column($items, 'imagem_id')));
        $thumbs = array_flip(array_column(flow_alt_rows($conn, "SELECT DISTINCT fi.imagem_id FROM historico_aprovacoes_imagens hi
            JOIN funcao_imagem fi ON fi.idfuncao_imagem=hi.funcao_imagem_id WHERE fi.imagem_id IN ($ids)
            AND hi.imagem IS NOT NULL AND hi.imagem<>'' AND hi.imagem NOT LIKE '%imagem_%'"), 'imagem_id'));
        foreach ($items as &$item) {
            if (isset($thumbs[$item['imagem_id']])) $item['thumbnail_url'] = '../PlanejamentoProducao/thumbnail.php?imagem_id=' . $item['imagem_id'];
        }
        unset($item);
    }
    $filtros = [];
    foreach ($mapas as $key => $mapa) {
        asort($mapa, SORT_NATURAL | SORT_FLAG_CASE); $filtros[$key] = [];
        foreach ($mapa as $id => $nome) $filtros[$key][] = ['id' => (int)$id, 'nome' => $nome];
    }
    usort($items, static fn($a, $b) => [$a['concluida'], $a['imagem_nome'], $a['funcao_id']] <=> [$b['concluida'], $b['imagem_nome'], $b['funcao_id']]);
    return ['success' => true, 'semana' => $semana, 'items' => array_values($items), 'filtros' => $filtros, 'planejamento_disponivel' => $disponivel];
}
