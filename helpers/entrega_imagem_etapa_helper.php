<?php

require_once __DIR__ . '/planejamento_producao_helper.php';
require_once __DIR__ . '/unidade_trabalho_helper.php';

/** Read model only: never advances a task, a render, or an item of a delivery. */
function flow_entrega_situacao(string $status): string
{
    $s = flow_planejamento_normalizar($status);
    if (flow_planejamento_status_finalizado($status)) {
        return 'CONCLUIDA';
    }
    if (strpos($s, 'hold') !== false) {
        return 'BLOQUEADA';
    }
    return match ($s) {
        'em andamento' => 'EM_EXECUCAO',
        'ajuste', 'refazendo' => 'EM_AJUSTE',
        'em aprovacao', 'aguardando direcao' => 'AGUARDANDO_APROVACAO',
        'erro', 'inconsistente' => 'ERRO',
        'reprovado' => 'REPROVADO',
        default => 'AGUARDANDO_INICIO',
    };
}

function flow_entrega_etapa(string $codigo, string $situacao, array $fonte = []): array
{
    $nomes = ['CADERNO_FILTRO' => 'Caderno/Filtro', 'MODELAGEM' => 'Modelagem',
        'COMPOSICAO' => 'Composição', 'FINALIZACAO' => 'Finalização', 'RENDER' => 'Render',
        'POS_PRODUCAO' => 'Pós-produção', 'INDEFINIDA' => 'Etapa a confirmar'];
    $situacoes = ['AGUARDANDO_INICIO' => 'Aguardando início', 'EM_EXECUCAO' => 'Em execução',
        'EM_AJUSTE' => 'Em ajuste', 'AGUARDANDO_APROVACAO' => 'Aguardando aprovação',
        'BLOQUEADA' => 'Bloqueada', 'CONCLUIDA' => 'Concluída', 'NA_FILA' => 'Na fila',
        'AGUARDANDO_ENVIO' => 'Aguardando envio', 'REFAZENDO' => 'Refazendo',
        'REPROVADO' => 'Reprovado', 'ERRO' => 'Erro', 'INDEFINIDA' => 'A confirmar'];
    return ['codigo' => $codigo, 'nome' => $nomes[$codigo], 'situacao' => $situacao,
        'situacao_nome' => $situacoes[$situacao], 'origem' => $fonte['origem'] ?? 'FUNCAO',
        'fonte_id' => $fonte['tarefa_id'] ?? $fonte['id'] ?? null,
        'funcao_id' => isset($fonte['funcao_id']) ? (int) $fonte['funcao_id'] : null,
        'responsavel_id' => !empty($fonte['colaborador_id']) ? (int) $fonte['colaborador_id'] : null,
        'responsavel_nome' => $fonte['nome_colaborador'] ?? null,
        'prazo' => $fonte['prazo'] ?? null, 'status_original' => $fonte['status'] ?? null,
        'atualizado_em' => $fonte['alterado_em'] ?? null];
}

/** Pure resolver; all sources must already be scoped to this delivery's cycle. */
function flow_entrega_resolver_etapa(array $imagem, array $tarefas, ?array $render = null, ?array $pos = null): array
{
    $mapa = [1 => 'CADERNO_FILTRO', 8 => 'CADERNO_FILTRO', 2 => 'MODELAGEM',
        3 => 'COMPOSICAO', 4 => 'FINALIZACAO', 6 => 'FINALIZACAO', 5 => 'POS_PRODUCAO'];
    $grupos = [];
    foreach ($tarefas as $t) {
        $f = (int) $t['funcao_id'];
        if (!isset($mapa[$f]) || in_array(flow_planejamento_normalizar((string) ($t['status'] ?? '')), ['cancelado', 'nao se aplica'], true)
            || in_array((int) ($t['colaborador_id'] ?? 0), [15, 30], true)) {
            continue;
        }
        // A planned Post task does not prove that the image has been sent to Post.
        if ($f === 5 && !$pos) {
            continue;
        }
        $grupos[$mapa[$f]][] = $t;
    }
    $etapas = [];
    foreach ($grupos as $codigo => $membros) {
        // An incomplete member keeps the Caderno/Filtro stage open.
        usort($membros, static function ($a, $b) {
            $prioridades = ['EM_EXECUCAO' => 0, 'EM_AJUSTE' => 1, 'AGUARDANDO_APROVACAO' => 2,
                'BLOQUEADA' => 3, 'AGUARDANDO_INICIO' => 4, 'CONCLUIDA' => 5];
            return ($prioridades[flow_entrega_situacao($a['status'] ?? '')] ?? 3)
                <=> ($prioridades[flow_entrega_situacao($b['status'] ?? '')] ?? 3);
        });
        $t = $membros[0];
        $etapas[$codigo] = flow_entrega_etapa($codigo, flow_entrega_situacao($t['status'] ?? ''), $t);
        $abertos = array_values(array_filter($membros, static fn ($m) => flow_entrega_situacao($m['status'] ?? '') !== 'CONCLUIDA'));
        $etapas[$codigo]['responsaveis'] = array_values(array_map(static fn ($m) => [
            'id' => !empty($m['colaborador_id']) ? (int) $m['colaborador_id'] : null,
            'nome' => $m['nome_colaborador'] ?? null], $abertos ?: $membros));
    }
    // Preserve the operational representative of an explicit Modelagem/Composição unit.
    foreach ($tarefas as $modelagem) {
        if ((int) $modelagem['funcao_id'] !== 2 || empty($modelagem['unidade_id'])) {
            continue;
        }
        foreach ($tarefas as $composicao) {
            if ((int) $composicao['funcao_id'] === 3 && ($composicao['unidade_id'] ?? null) === $modelagem['unidade_id']
                && $modelagem['status'] === $composicao['status']) {
                unset($etapas['MODELAGEM']);
            }
        }
    }
    $reaberta = false;
    if ($render) {
        $referencia = $render['referencia_em'] ?? '';
        foreach ($tarefas as $t) {
            if (in_array((int) $t['funcao_id'], [4, 6], true)
                && in_array(flow_entrega_situacao($t['status'] ?? ''), ['EM_EXECUCAO', 'EM_AJUSTE', 'BLOQUEADA'], true)
                && !empty($t['alterado_em']) && $t['alterado_em'] > $referencia) {
                $reaberta = true;
            }
        }
        if (!$reaberta) {
            $s = flow_planejamento_normalizar($render['status'] ?? '');
            $tentativa = $render['tentativa_status'] ?? '';
            if ($s === 'aprovado') {
                $semPos = flow_planejamento_normalizar($imagem['tipo_imagem'] ?? '') === 'planta humanizada'
                    || ($imagem['ciclo_nome'] ?? '') === 'P00';
                if ($semPos) {
                    return flow_entrega_resultado([flow_entrega_etapa('RENDER', 'CONCLUIDA', $render)]);
                }
                if ($pos) {
                    $situacao = (int) $pos['status_pos'] === 0 ? 'CONCLUIDA'
                        : ($etapas['POS_PRODUCAO']['situacao'] ?? 'AGUARDANDO_INICIO');
                    if ($situacao === 'CONCLUIDA' && (int) $pos['status_pos'] !== 0) {
                        $situacao = 'AGUARDANDO_INICIO';
                    }
                    $e = flow_entrega_etapa('POS_PRODUCAO', $situacao, $pos);
                    // Current task supplies execution details; the matching Post row supplies handoff/completion.
                    if ($situacao !== 'CONCLUIDA' && isset($etapas['POS_PRODUCAO']) && $etapas['POS_PRODUCAO']['situacao'] !== 'CONCLUIDA') {
                        $e['responsaveis'] = $etapas['POS_PRODUCAO']['responsaveis'];
                        $e['responsavel_id'] = $etapas['POS_PRODUCAO']['responsavel_id'];
                        $e['responsavel_nome'] = $etapas['POS_PRODUCAO']['responsavel_nome'];
                    }
                    return flow_entrega_resultado([$e]);
                }
                return flow_entrega_resultado([flow_entrega_etapa('POS_PRODUCAO', 'AGUARDANDO_ENVIO', $render)]);
            }
            $situacao = match (true) {
                $s === 'reprovado' => 'REPROVADO',
                $s === 'refazendo' => 'REFAZENDO',
                $s === 'erro', in_array($tentativa, ['ERRO', 'INCONSISTENTE'], true) => 'ERRO',
                $s === 'em aprovacao', in_array($tentativa, ['EM_APROVACAO', 'CONCLUIDA_MANUALMENTE'], true) => 'AGUARDANDO_APROVACAO',
                preg_match('/queued|pending|waiting|suspended/i', (string) ($render['processamento_status'] ?? '')) === 1 => 'NA_FILA',
                $s === 'em andamento', $tentativa === 'EM_ANDAMENTO' => 'EM_EXECUCAO',
                default => 'NA_FILA',
            };
            return flow_entrega_resultado([flow_entrega_etapa('RENDER', $situacao, $render)]);
        }
    }
    $ordem = ['CADERNO_FILTRO', 'MODELAGEM', 'COMPOSICAO', 'FINALIZACAO', 'POS_PRODUCAO'];
    $ordenadas = array_values(array_replace(array_fill_keys($ordem, null), $etapas));
    $ordenadas = array_values(array_filter($ordenadas));
    if ($reaberta) {
        $ordenadas = array_values(array_filter($ordenadas, static fn ($e) => $e['codigo'] === 'FINALIZACAO'));
    }
    $ativas = array_values(array_filter($ordenadas, static fn ($e) => in_array($e['situacao'], ['EM_EXECUCAO', 'EM_AJUSTE', 'AGUARDANDO_APROVACAO', 'BLOQUEADA'], true)));
    if ($ativas) {
        return flow_entrega_resultado($ativas);
    }
    $abertas = array_values(array_filter($ordenadas, static fn ($e) => $e['situacao'] !== 'CONCLUIDA'));
    if ($abertas) {
        return flow_entrega_resultado([$abertas[0]]);
    }
    if (($imagem['ciclo_nome'] ?? '') === 'P00' && $ordenadas) {
        return flow_entrega_resultado([end($ordenadas)]);
    }
    if (isset($etapas['FINALIZACAO'])) {
        return flow_entrega_resultado([flow_entrega_etapa('RENDER', 'AGUARDANDO_ENVIO', ['origem' => 'ENCAMINHAMENTO'])]);
    }
    return flow_entrega_resultado([flow_entrega_etapa('INDEFINIDA', 'INDEFINIDA', ['origem' => 'SEM_EVIDENCIA'])]);
}

function flow_entrega_resultado(array $etapas): array
{
    return ['atual' => $etapas[0], 'simultaneas' => $etapas,
        'motivo' => count($etapas) > 1 ? 'Atividades simultâneas; a primeira etapa aberta é destacada.'
            : ($etapas[0]['codigo'] === 'INDEFINIDA' ? 'Não há registros suficientes para identificar a etapa desta imagem neste ciclo.'
                : 'Identificada pelas funções e pelo encaminhamento do ciclo da entrega.')];
}

/** Resolve a real function, never a render/Post record or a planned allocation ID. */
function flow_entrega_tarefa_atalho(array $producao, array $tarefas, string $ciclo): ?array
{
    $etapa = $producao['atual'];
    $funcoes = match ($etapa['codigo']) {
        'CADERNO_FILTRO' => [1, 8], 'MODELAGEM' => [2], 'COMPOSICAO' => [3],
        'FINALIZACAO' => [6, 4], 'POS_PRODUCAO' => [5],
        'RENDER' => $ciclo === 'P00' ? [2] : [6, 4],
        default => [],
    };
    $candidatas = array_values(array_filter($tarefas, static fn ($t) =>
        !empty($t['tarefa_id']) && ($t['origem'] ?? 'FUNCAO') === 'FUNCAO'
        && !in_array(flow_planejamento_normalizar((string) ($t['status'] ?? '')), ['cancelado', 'nao se aplica'], true)
        && !in_array((int) ($t['colaborador_id'] ?? 0), [15, 30], true)
        && in_array((int) $t['funcao_id'], $funcoes, true)));
    usort($candidatas, static function ($a, $b) use ($etapa, $funcoes) {
        $idAtual = ($etapa['origem'] === 'FUNCAO') ? (int) $etapa['fonte_id'] : 0;
        return ((int) $b['tarefa_id'] === $idAtual) <=> ((int) $a['tarefa_id'] === $idAtual)
            ?: array_search((int) $a['funcao_id'], $funcoes, true) <=> array_search((int) $b['funcao_id'], $funcoes, true)
            ?: (int) $b['tarefa_id'] <=> (int) $a['tarefa_id'];
    });
    if (!$candidatas) {
        return null;
    }
    $t = $candidatas[0];
    $nomes = [1 => 'Caderno', 8 => 'Filtro', 2 => 'Modelagem', 3 => 'Composição',
        4 => 'Finalização', 6 => 'Alteração', 5 => 'Pós-produção'];
    return ['funcao_imagem_id' => (int) $t['tarefa_id'],
        'imagem_id' => (int) ($t['imagem_id'] ?? 0),
        'funcao_nome' => $nomes[(int) $t['funcao_id']] ?? 'Função',
        'responsavel_id' => (int) ($t['colaborador_id'] ?? 0)];
}

/** Batch queries avoid one network/database request per image. */
function flow_entrega_etapas_lote(mysqli $conn, array $entrega, array $itens): array
{
    $ids = array_values(array_unique(array_map('intval', array_column($itens, 'imagem_id'))));
    if (!$ids) {
        return [];
    }
    $lista = implode(',', $ids);
    $ciclo = (int) $entrega['status_id'];
    $entregaId = (int) $entrega['id'];
    // A later delivery for the same image/cycle makes the live sources ambiguous for this older card.
    $novas = $conn->query("SELECT DISTINCT ei.imagem_id FROM entregas_itens ei JOIN entregas e ON e.id=ei.entrega_id
        WHERE ei.imagem_id IN ($lista) AND e.status_id=$ciclo AND e.id>$entregaId")->fetch_all(MYSQLI_ASSOC);
    $historicas = array_fill_keys(array_column($novas, 'imagem_id'), true);
    $result = $conn->query("SELECT fi.idfuncao_imagem AS tarefa_id, fi.imagem_id, fi.funcao_id, fi.status, fi.prazo,
        fi.colaborador_id, c.nome_colaborador, i.status_id AS ciclo_atual,
        (SELECT MAX(l.data) FROM log_alteracoes l WHERE l.funcao_imagem_id=fi.idfuncao_imagem
            AND l.status_novo=fi.status AND NOT (l.status_anterior <=> l.status_novo)) AS alterado_em,
        EXISTS(SELECT 1 FROM alteracoes a WHERE a.funcao_id=fi.idfuncao_imagem AND a.status_id=$ciclo) AS alteracao_do_ciclo
        FROM funcao_imagem fi JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=fi.imagem_id
        LEFT JOIN colaborador c ON c.idcolaborador=fi.colaborador_id WHERE fi.imagem_id IN ($lista)");
    $tarefas = $result->fetch_all(MYSQLI_ASSOC);
    $unidades = flow_unidade_mapa_explicito($conn, array_column($tarefas, 'tarefa_id'));
    $porTarefa = [];
    foreach ($unidades as $u) {
        foreach ($u['membros'] as $m) {
            if ($u['tipo'] === FLOW_UNIDADE_TIPO_MODELAGEM_COMPOSICAO) {
                $porTarefa[$m['idfuncao_imagem']] = $u['id'];
            }
        }
    }
    $porImagem = [];
    foreach ($tarefas as $t) {
        if ((int) $t['ciclo_atual'] !== $ciclo && !($ciclo === 2 && (int) $t['ciclo_atual'] === 1)) {
            continue;
        } // R00 also includes images still in preparation/P00.
        if (($entrega['nome_etapa'] ?? '') === 'P00' && (int) $t['funcao_id'] !== 2) {
            continue;
        }
        if ($ciclo !== 2 && ($entrega['nome_etapa'] ?? '') !== 'P00'
            && !in_array((int) $t['funcao_id'], [5, 6], true)) {
            continue;
        }
        if ((int) $t['funcao_id'] === 6 && !$t['alteracao_do_ciclo']) {
            continue;
        }
        $t['unidade_id'] = $porTarefa[$t['tarefa_id']] ?? null;
        $porImagem[$t['imagem_id']][] = $t;
    }
    if ($ciclo === 2) {
        $previstas = flow_planejamento_carregar_itens_obra($conn, (int) $entrega['obra_id'], $ids);
        foreach ($previstas as $p) {
            if ($p['origem'] === 'PLANEJAMENTO') {
                // Same cycle check also applies to planned declarations.
                $item = array_values(array_filter($itens, static fn ($i) => (int) $i['imagem_id'] === (int) $p['imagem_id']))[0] ?? [];
                if (!in_array((int) ($item['ciclo_atual'] ?? 0), [1, 2], true)) {
                    continue;
                }
                $porImagem[$p['imagem_id']][] = $p;
            }
        }
    }
    $temProgresso = $conn->query("SHOW TABLES LIKE 'deadline_job_progress'")->num_rows > 0;
    $campoProgresso = $temProgresso ? 'dp.deadline_task_render_status' : 'NULL';
    $joinProgresso = $temProgresso ? 'LEFT JOIN deadline_job_progress dp ON dp.deadline_job_id COLLATE utf8mb4_unicode_ci=t.deadline_job_id COLLATE utf8mb4_unicode_ci' : '';
    $renders = $conn->query("SELECT r.idrender_alta AS id, r.imagem_id, r.status, r.responsavel_id AS colaborador_id,
        c.nome_colaborador, 'RENDER' AS origem, t.status AS tentativa_status,
        $campoProgresso AS processamento_status,
        COALESCE(t.criado_em, r.submitted, r.data) AS referencia_em,
        COALESCE(t.atualizado_em, r.last_updated, r.data) AS alterado_em
        FROM render_alta r LEFT JOIN colaborador c ON c.idcolaborador=r.responsavel_id
        LEFT JOIN render_tentativas t ON t.id=(SELECT rt.id FROM render_tentativas rt WHERE rt.render_id=r.idrender_alta
            ORDER BY rt.ativa DESC, rt.numero_tentativa DESC LIMIT 1)
        $joinProgresso
        WHERE r.imagem_id IN ($lista) AND r.status_id=$ciclo AND r.status <> 'Arquivado' AND r.excluido_em IS NULL
        ORDER BY r.idrender_alta DESC")->fetch_all(MYSQLI_ASSOC);
    $renderPorImagem = [];
    foreach ($renders as $r) {
        if (!isset($renderPorImagem[$r['imagem_id']])) {
            $renderPorImagem[$r['imagem_id']] = $r;
        }
    }
    $posRows = $conn->query("SELECT p.idpos_producao AS id, p.imagem_id, p.render_id, p.status_pos,
        p.responsavel_id AS colaborador_id, c.nome_colaborador, p.data_pos AS alterado_em, 'POS_PRODUCAO' AS origem
        FROM pos_producao p LEFT JOIN colaborador c ON c.idcolaborador=p.responsavel_id
        WHERE p.imagem_id IN ($lista) AND p.status_id=$ciclo ORDER BY p.idpos_producao DESC")->fetch_all(MYSQLI_ASSOC);
    $posPorRender = [];
    foreach ($posRows as $p) {
        if (!isset($posPorRender[$p['render_id']])) {
            $posPorRender[$p['render_id']] = $p;
        }
    }
    $saida = [];
    foreach ($itens as $i) {
        $id = (int) $i['imagem_id'];
        if (($entrega['tipo_entrega'] ?? '') !== 'P00' && isset($historicas[$id])) {
            $saida[$id] = flow_entrega_resultado([flow_entrega_etapa('INDEFINIDA', 'INDEFINIDA', ['origem' => 'CICLO_HISTORICO'])]);
            $saida[$id]['motivo'] = 'Existe uma entrega mais recente desta imagem no mesmo ciclo. A etapa atual não substitui o histórico desta entrega.';
            continue;
        }
        $render = $renderPorImagem[$id] ?? null;
        $saida[$id] = flow_entrega_resolver_etapa(
            $i + ['ciclo_nome' => $entrega['nome_etapa']],
            $porImagem[$id] ?? [],
            $render,
            $render ? ($posPorRender[$render['id']] ?? null) : null
        );
        $saida[$id]['tarefa_atalho'] = flow_entrega_tarefa_atalho($saida[$id], $porImagem[$id] ?? [], (string) $entrega['nome_etapa']);
    }
    return $saida;
}
