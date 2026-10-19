<?php

require_once __DIR__ . '/tarefa_planejamento_contexto_helper.php';

/**
 * Presentation of a single image's real flow. Planned declarations are not tasks:
 * they never receive a fabricated funcao_imagem_id or execution history.
 */
function flow_imagem_fluxo_operacional_montar(array $contexto, array $previstas = [], ?array $plano = null): array
{
    $imagem = $contexto['imagem'];
    $definicoes = flow_planejamento_definicoes_etapas();
    $janelas = [];
    foreach ((array) ($plano['snapshot']['etapas'] ?? []) as $index => $janela) {
        $janelas[$janela['codigo']] = $janela + ['ordem_fluxo' => ($index + 1) * 100];
    }
    $etapas = [];
    foreach ($contexto['etapas'] as $etapa) {
        $etapa['id_etapa'] = 'tarefa:' . $etapa['funcao_imagem_id'];
        $etapa['origem'] = 'TAREFA';
        $etapas[] = $etapa;
    }
    $declaracoes = [];
    foreach ($previstas as $prevista) {
        if ((int) $prevista['imagem_id'] !== (int) $imagem['id']) {
            continue;
        }
        $funcaoId = (int) $prevista['funcao_id'];
        if (isset($declaracoes[$funcaoId])) {
            continue;
        }
        $declaracoes[$funcaoId] = true;
        // A materialized task supersedes the declaration, including unassigned tasks.
        if (array_filter($contexto['etapas'], static fn ($e) => $e['funcao_id'] === $funcaoId)) {
            continue;
        }
        $codigo = flow_planejamento_codigo_etapa($prevista + ['tipo_imagem' => $imagem['tipo_imagem'] ?? '']);
        $janela = $janelas[$codigo ?? ''] ?? [];
        $inicio = flow_tarefa_planejamento_data_valida($janela['inicio'] ?? $janela['data_inicio'] ?? null);
        $fim = flow_tarefa_planejamento_data_valida($janela['limite'] ?? $janela['data_limite'] ?? null);
        $cancelada = flow_execucao_status_cancelado((string) ($prevista['status'] ?? ''));
        $etapas[] = [
            'id_etapa' => 'planejada:' . $imagem['id'] . ':' . $funcaoId,
            'funcao_imagem_id' => null, 'imagem_id' => (int) $imagem['id'], 'funcao_id' => $funcaoId,
            'funcao_nome' => $prevista['funcao_nome'] ?? ($definicoes[$codigo]['nome'] ?? 'Função prevista'),
            'origem' => 'PLANEJAMENTO', 'somente_planejada' => true, 'tarefas' => [],
            'status' => $prevista['status'] ?? null,
            'estado_visual' => $cancelada ? null : 'PENDENTE', 'participa_operacao' => !$cancelada,
            'responsavel_id' => null, 'responsavel_nome' => null, 'prazo' => null,
            'execucao_individual' => ['inicio_real' => null, 'conclusao_real' => null, 'hold' => false],
            'requisitos_inicio' => null, 'avisos' => [],
            'contexto_planejamento' => [
                'disponivel' => (bool) $janela, 'etapa_codigo' => $codigo,
                'inicio_planejado' => $inicio, 'prazo_planejado' => $fim,
                'versao_id' => $plano['versao_id'] ?? null, 'plano_versao' => $plano['plano_versao'] ?? null,
                'status_temporal_individual' => $fim && !$cancelada ? flow_tarefa_planejamento_status_temporal($fim, 'Não iniciado', null) : null,
            ],
        ];
    }
    $combo = [];
    foreach ($etapas as $index => &$etapa) {
        $codigo = flow_planejamento_codigo_etapa($etapa + ['tipo_imagem' => $imagem['tipo_imagem'] ?? '']);
        $etapa['codigo_etapa'] = $codigo;
        $etapa['ordem'] = $janelas[$codigo ?? '']['ordem_fluxo']
            ?? (($codigo && isset($definicoes[$codigo])) ? (array_search($codigo, array_keys($definicoes), true) + 1) * 100 : 100000 + ($etapa['ordem'] ?? $index));
        if ($codigo === 'CADERNO_FILTRO') {
            $combo[] = $etapa;
        }
    }
    unset($etapa);
    if ($combo) {
        // Same planning representative; operational work remains visible until
        // all participating tasks finish (Filtro may still await approval).
        $unida = flow_planejamento_itens_da_etapa('CADERNO_FILTRO', $combo)[0];
        $operacionais = array_values(array_filter($combo, static fn ($e) => !empty($e['estado_visual']) && !empty($e['participa_operacao'])));
        $ativas = array_values(array_filter($operacionais, static fn ($e) => $e['estado_visual'] === 'ATIVA'));
        $abertas = array_values(array_filter($operacionais, static fn ($e) => $e['estado_visual'] !== 'CONCLUIDA'));
        $bloqueadas = array_values(array_filter($abertas, static fn ($e) => $e['estado_visual'] === 'BLOQUEADA'));
        $fonte = $ativas[0] ?? $bloqueadas[0] ?? $abertas[0] ?? $unida;
        $unida['estado_visual'] = $ativas ? 'ATIVA' : ($bloqueadas ? 'BLOQUEADA' : ($abertas ? 'PENDENTE' : ($operacionais ? 'CONCLUIDA' : null)));
        $unida['participa_operacao'] = (bool) $operacionais;
        $unida['status'] = $fonte['status'];
        $unida['requisitos_inicio'] = $fonte['requisitos_inicio'];
        $unida['contexto_planejamento']['status_temporal_individual'] = $fonte['contexto_planejamento']['status_temporal_individual'] ?? null;
        $inicios = array_values(array_filter(array_map(static fn ($e) => $e['execucao_individual']['inicio_real'] ?? null, $operacionais)));
        $fins = array_values(array_filter(array_map(static fn ($e) => $e['execucao_individual']['conclusao_real'] ?? null, $operacionais)));
        $unida['execucao_individual']['inicio_real'] = $inicios ? min($inicios) : null;
        $unida['execucao_individual']['conclusao_real'] = $unida['estado_visual'] === 'CONCLUIDA' && count($fins) === count($operacionais) && $fins ? max($fins) : null;
        $unida['execucao_individual']['concluida'] = $unida['estado_visual'] === 'CONCLUIDA';
        $unida['execucao_individual']['hold'] = (bool) array_filter($operacionais, static fn ($e) => !empty($e['execucao_individual']['hold']));
        $unida['execucao_individual']['iniciada'] = (bool) array_filter($operacionais, static fn ($e) => !empty($e['execucao_individual']['iniciada']));
        $unida['ultimo_ciclo'] = null;
        $unida['contexto_planejamento']['desvio_individual_dias_uteis'] = null;
        $unida['avisos'] = array_values(array_unique(array_merge(...array_map(static fn ($e) => $e['avisos'] ?? [], $combo))));
        $unida['id_etapa'] = 'etapa:CADERNO_FILTRO';
        $unida['funcao_nome'] = $definicoes['CADERNO_FILTRO']['nome'];
        $unida['tarefas'] = array_map(static fn ($e) => array_intersect_key($e, array_flip(['funcao_imagem_id','imagem_id','funcao_nome','responsavel_id'])), array_values(array_filter($combo, static fn ($e) => !empty($e['funcao_imagem_id']))));
        $unida['somente_planejada'] = !$unida['tarefas'];
        $unida['componentes'] = $combo;
        $nomes = array_values(array_unique(array_filter(array_column($operacionais, 'responsavel_nome'))));
        $unida['responsavel_nome'] = $nomes ? implode(', ', $nomes) : null;
        $etapas = array_values(array_filter($etapas, static fn ($e) => $e['codigo_etapa'] !== 'CADERNO_FILTRO'));
        $etapas[] = $unida;
    }
    usort($etapas, static fn ($a, $b) => ($a['ordem'] <=> $b['ordem']) ?: strcmp($a['id_etapa'], $b['id_etapa']));
    $fluxo = ['imagem' => $imagem, 'hoje' => $contexto['hoje'], 'etapas' => $etapas, 'avisos' => $contexto['avisos']];
    $grupos = ['ATIVA' => 'etapas_ativas', 'CONCLUIDA' => 'etapas_concluidas', 'PENDENTE' => 'etapas_pendentes', 'BLOQUEADA' => 'etapas_bloqueadas'];
    foreach (array_merge(array_values($grupos), ['etapas_nao_operacionais']) as $grupo) {
        $fluxo[$grupo] = [];
    }
    foreach ($etapas as $etapa) {
        $fluxo[$grupos[$etapa['estado_visual']] ?? 'etapas_nao_operacionais'][] = $etapa['id_etapa'];
    }
    return $fluxo;
}

/** Reuses the same real/planned item source used by production planning. */
function flow_imagem_fluxo_fontes_lote(mysqli $conn, array $imagens): array
{
    $porObra = [];
    $previstas = [];
    $planos = [];
    foreach ($imagens as $imagem) {
        $porObra[(int) $imagem['obra_id']][] = (int) $imagem['id'];
    }
    $nomes = [];
    $result = $conn->query('SELECT idfuncao, nome_funcao FROM funcao');
    while ($row = $result->fetch_assoc()) {
        $nomes[(int) $row['idfuncao']] = $row['nome_funcao'];
    }
    foreach ($porObra as $obraId => $ids) {
        foreach (flow_planejamento_carregar_itens_obra($conn, $obraId, $ids) as $item) {
            if ($item['origem'] !== 'PLANEJAMENTO') {
                continue;
            }
            $item['funcao_nome'] = $nomes[(int) $item['funcao_id']] ?? null;
            $previstas[(int) $item['imagem_id']][] = $item;
        }
    }
    if ($imagens && flow_planejamento_tabelas_persistencia_disponiveis($conn)) {
        $ids = array_map(static fn ($i) => (int) $i['id'], $imagens);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("SELECT ei.imagem_id, v.id AS versao_id, v.numero AS plano_versao, v.snapshot_json
            FROM entregas_itens ei JOIN entregas e ON e.id=ei.entrega_id AND e.status_id=2
            JOIN entrega_planejamento_producao p ON p.entrega_id=e.id AND p.estado IN ('CONFIRMADO','DESATUALIZADO','REPLANEJAMENTO')
            JOIN entrega_planejamento_versao v ON v.id=p.versao_atual_id AND v.vigente=1
            WHERE ei.imagem_id IN ($marks) ORDER BY e.id DESC");
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $id = (int) $row['imagem_id'];
            if (isset($planos[$id])) {
                continue;
            }
            $snapshot = json_decode($row['snapshot_json'], true);
            if (is_array($snapshot)) {
                $planos[$id] = ['snapshot' => $snapshot, 'versao_id' => (int) $row['versao_id'], 'plano_versao' => (int) $row['plano_versao']];
            }
        }
        $stmt->close();
    }
    return ['previstas' => $previstas, 'planos' => $planos];
}
