<?php

/**
 * Contexto operacional que conecta uma funcao_imagem ao plano de producao
 * confirmado. Esta camada e deliberadamente somente leitora: o prazo
 * necessario nunca e copiado para funcao_imagem.prazo.
 */

require_once __DIR__ . '/planejamento_producao_helper.php';
require_once __DIR__ . '/planejamento_execucao_helper.php';

function flow_tarefa_planejamento_tabela_existe(mysqli $conn, string $tabela): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $tabela);
    $stmt->execute();
    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $existe;
}

function flow_tarefa_planejamento_persistencia_disponivel(mysqli $conn): bool
{
    return flow_tarefa_planejamento_tabela_existe($conn, 'funcao_imagem_previsao_conclusao')
        && flow_tarefa_planejamento_tabela_existe($conn, 'funcao_imagem_previsao_historico');
}

/**
 * Retorna a posição confirmada da fila operacional para as tarefas de uma
 * pessoa. A fila é gerenciada por blocos (entrega + etapa), então todas as
 * tarefas da mesma obra/etapa recebem a mesma posição e continuam usando os
 * critérios atuais como desempate.
 *
 * Obras legadas sem entrega planejada podem ser encontradas pelo par obra +
 * etapa, pois a fila confirmada usa a referência operacional OBRA:{id}:{etapa}.
 */
function flow_tarefa_fila_operacional_posicoes_lote(mysqli $conn, array $tarefas, int $colaboradorId): array
{
    if ($colaboradorId <= 0 || !$tarefas || !flow_tarefa_planejamento_tabela_existe($conn, 'entrega_planejamento_fila_operacional')) {
        return [];
    }

    $imagemIds = [];
    foreach ($tarefas as $tarefa) {
        if (!empty($tarefa['is_animacao'])) {
            continue;
        }
        $imagemId = (int) ($tarefa['imagem_id'] ?? 0);
        if ($imagemId > 0) {
            $imagemIds[$imagemId] = true;
        }
    }
    if (!$imagemIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($imagemIds), '?'));
    $tipos = str_repeat('i', count($imagemIds));
    $valores = array_map('intval', array_keys($imagemIds));

    // Mantém a mesma regra do contexto de planejamento: quando uma imagem
    // está em mais de uma entrega ativa, considera a entrega mais recente.
    $stmtEntregas = $conn->prepare(
        "SELECT ei.imagem_id, e.id AS entrega_id, e.obra_id
           FROM entregas_itens ei
           JOIN entregas e ON e.id = ei.entrega_id AND e.status_id = 2
          WHERE ei.imagem_id IN ($placeholders)
          ORDER BY e.id DESC, ei.id ASC"
    );
    if (!$stmtEntregas) {
        return [];
    }
    $stmtEntregas->bind_param($tipos, ...$valores);
    $stmtEntregas->execute();
    $entregaPorImagem = [];
    $resultEntregas = $stmtEntregas->get_result();
    while ($row = $resultEntregas->fetch_assoc()) {
        $imagemId = (int) ($row['imagem_id'] ?? 0);
        if (!isset($entregaPorImagem[$imagemId])) {
            $entregaPorImagem[$imagemId] = [
                'entrega_id' => (int) ($row['entrega_id'] ?? 0),
                'obra_id' => (int) ($row['obra_id'] ?? 0),
            ];
        }
    }
    $stmtEntregas->close();

    $stmtFila = $conn->prepare(
        'SELECT entrega_id, obra_id, codigo_etapa, posicao
           FROM entrega_planejamento_fila_operacional
          WHERE colaborador_id = ? AND ativo = 1
          ORDER BY codigo_etapa, posicao, id'
    );
    if (!$stmtFila) {
        return [];
    }
    $stmtFila->bind_param('i', $colaboradorId);
    $stmtFila->execute();
    $filaPorEntregaEtapa = [];
    $filaPorObraEtapa = [];
    $resultFila = $stmtFila->get_result();
    while ($row = $resultFila->fetch_assoc()) {
        $etapa = strtoupper(trim((string) ($row['codigo_etapa'] ?? '')));
        $posicao = (int) ($row['posicao'] ?? 0);
        if ($etapa === '' || $posicao <= 0) {
            continue;
        }

        $entregaId = (int) ($row['entrega_id'] ?? 0);
        $obraId = (int) ($row['obra_id'] ?? 0);
        if ($entregaId > 0) {
            $chave = $entregaId . ':' . $etapa;
            $filaPorEntregaEtapa[$chave] = isset($filaPorEntregaEtapa[$chave])
                ? min($filaPorEntregaEtapa[$chave], $posicao)
                : $posicao;
        } elseif ($obraId > 0) {
            $chave = $obraId . ':' . $etapa;
            $filaPorObraEtapa[$chave] = isset($filaPorObraEtapa[$chave])
                ? min($filaPorObraEtapa[$chave], $posicao)
                : $posicao;
        }
    }
    $stmtFila->close();

    if (!$filaPorEntregaEtapa && !$filaPorObraEtapa) {
        return [];
    }

    $posicoes = [];
    foreach ($tarefas as $tarefa) {
        if (!empty($tarefa['is_animacao'])) {
            continue;
        }
        $tarefaId = (int) ($tarefa['idfuncao_imagem'] ?? 0);
        $imagemId = (int) ($tarefa['imagem_id'] ?? 0);
        $etapa = flow_planejamento_codigo_etapa($tarefa);
        if ($tarefaId <= 0 || $imagemId <= 0 || !$etapa) {
            continue;
        }

        $posicao = null;
        $entrega = $entregaPorImagem[$imagemId] ?? null;
        if ($entrega) {
            $posicao = $filaPorEntregaEtapa[(int) $entrega['entrega_id'] . ':' . $etapa] ?? null;
        }
        if ($posicao === null) {
            $obraId = (int) ($tarefa['obra_id'] ?? ($entrega['obra_id'] ?? 0));
            if ($obraId > 0) {
                $posicao = $filaPorObraEtapa[$obraId . ':' . $etapa] ?? null;
            }
        }
        if ($posicao !== null) {
            $posicoes[$tarefaId] = [
                'posicao' => (int) $posicao,
                'etapa' => $etapa,
            ];
        }
    }

    return $posicoes;
}

function flow_tarefa_planejamento_data_valida(?string $data): ?string
{
    $data = $data ? substr($data, 0, 10) : null;
    return $data && entregas_valid_date($data) && $data !== '0000-00-00' ? $data : null;
}

/** Convenção: positivo = depois do prazo; negativo = antes do prazo. */
function flow_tarefa_planejamento_desvio(?string $referencia, ?string $data): ?int
{
    if (!$referencia || !$data) {
        return null;
    }
    if ($referencia === $data) {
        return 0;
    }
    return $data > $referencia
        ? flow_planejamento_dias_uteis_entre($referencia, $data)
        : -flow_planejamento_dias_uteis_entre($data, $referencia);
}

function flow_tarefa_planejamento_status_temporal(?string $prazo, string $status, ?string $conclusao, bool $bloqueada = false, ?string $hoje = null): array
{
    $prazo = flow_tarefa_planejamento_data_valida($prazo);
    $conclusao = flow_tarefa_planejamento_data_valida($conclusao);
    $hoje = flow_tarefa_planejamento_data_valida($hoje ?: date('Y-m-d')) ?: date('Y-m-d');
    if (!$prazo) {
        return ['codigo' => 'SEM_PRAZO', 'rotulo' => 'Prazo não definido pelo planejamento', 'dias' => null];
    }

    if (flow_planejamento_status_finalizado($status)) {
        $desvio = flow_tarefa_planejamento_desvio($prazo, $conclusao);
        if ($desvio === null || $desvio <= 0) {
            return ['codigo' => 'CONCLUIDO_NO_PRAZO', 'rotulo' => 'Concluído no prazo', 'dias' => max(0, (int) ($desvio ?? 0))];
        }
        return ['codigo' => 'CONCLUIDO_COM_ATRASO', 'rotulo' => 'Concluído +' . $desvio . ' ' . ($desvio === 1 ? 'dia útil' : 'dias úteis'), 'dias' => $desvio];
    }

    $desvio = flow_tarefa_planejamento_desvio($prazo, $hoje);
    if ($bloqueada) {
        return [
            'codigo' => 'BLOQUEADO',
            'rotulo' => $desvio !== null && $desvio > 0 ? 'Aguardando dependências · prazo vencido' : 'Aguardando dependências',
            'dias' => max(0, (int) ($desvio ?? 0)),
        ];
    }
    if ($desvio !== null && $desvio > 0) {
        return ['codigo' => 'ATRASADO', 'rotulo' => '+' . $desvio . ' ' . ($desvio === 1 ? 'dia útil' : 'dias úteis'), 'dias' => $desvio];
    }
    if ($prazo === $hoje) {
        return ['codigo' => 'PRAZO_HOJE', 'rotulo' => 'Prazo hoje', 'dias' => 0];
    }

    $restantes = flow_planejamento_dias_uteis_entre($hoje, $prazo);
    if ($restantes <= 1) {
        return ['codigo' => 'PRAZO_PROXIMO', 'rotulo' => 'Prazo amanhã', 'dias' => $restantes];
    }
    return ['codigo' => 'NO_PRAZO', 'rotulo' => 'No prazo', 'dias' => $restantes];
}

function flow_tarefa_planejamento_contexto_vazio(array $tarefa): array
{
    return [
        'planejamento_disponivel' => false,
        'etapa_codigo' => null,
        'etapa_nome' => null,
        'janela_inicio' => null,
        'janela_fim' => null,
        'prazo_necessario' => null,
        'prazo_necessario_historico' => false,
        'previsao_colaborador' => null,
        'justificativa_previsao' => null,
        'diferenca_previsao_dias_uteis' => null,
        'status_temporal' => ['codigo' => 'SEM_PRAZO', 'rotulo' => 'Prazo não definido pelo planejamento', 'dias' => null],
        'desvio_acumulado' => null,
        'mensagem_contexto' => 'Esta tarefa não possui uma etapa correspondente no planejamento vigente.',
        'timeline' => [],
        'origem_timeline' => null,
        'plano_versao' => null,
        'versao_id' => null,
        'tarefa_id' => (int) ($tarefa['idfuncao_imagem'] ?? $tarefa['tarefa_id'] ?? 0),
    ];
}

/**
 * Linha do tempo para obras legadas/sem plano confirmado. Ela preserva a
 * sequência realmente cadastrada em funcao_imagem e nunca sintetiza prazo.
 */
function flow_tarefa_timeline_operacional_fallback_lote(mysqli $conn, array $porTarefa, array $contextos): array
{
    $imagemIds = array_values(array_unique(array_filter(array_map(static fn (array $t): int => (int) ($t['imagem_id'] ?? 0), $porTarefa))));
    if (!$imagemIds) {
        return $contextos;
    }
    $inImagens = implode(',', array_fill(0, count($imagemIds), '?'));
    $stmt = $conn->prepare("SELECT fi.idfuncao_imagem AS tarefa_id, fi.imagem_id, fi.funcao_id, fi.status, fi.prazo, f.nome_funcao
                              FROM funcao_imagem fi
                              LEFT JOIN funcao f ON f.idfuncao = fi.funcao_id
                             WHERE fi.imagem_id IN ($inImagens)");
    if (!$stmt) {
        return $contextos;
    }
    $tipos = str_repeat('i', count($imagemIds));
    $stmt->bind_param($tipos, ...$imagemIds);
    $stmt->execute();
    $porImagem = [];
    $todos = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['tarefa_id'] = (int) $row['tarefa_id'];
        $porImagem[(int) $row['imagem_id']][] = $row;
        $todos[] = $row;
    }
    $stmt->close();
    $logs = $todos ? flow_execucao_carregar_logs($conn, $todos) : [];
    $ordem = [1 => 10, 8 => 11, 2 => 20, 3 => 30, 9 => 35, 4 => 40, 5 => 50, 6 => 60, 7 => 70];

    foreach ($porTarefa as $id => $tarefa) {
        $itens = $porImagem[(int) ($tarefa['imagem_id'] ?? 0)] ?? [];
        if (!$itens) {
            continue;
        }
        usort($itens, static function (array $a, array $b) use ($ordem): int {
            $ordemA = $ordem[(int) ($a['funcao_id'] ?? 0)] ?? 999;
            $ordemB = $ordem[(int) ($b['funcao_id'] ?? 0)] ?? 999;
            return $ordemA === $ordemB ? ((int) $a['tarefa_id'] <=> (int) $b['tarefa_id']) : ($ordemA <=> $ordemB);
        });
        $timeline = [];
        foreach ($itens as $item) {
            $resumo = flow_execucao_resumir_tarefa($item, $logs[(int) $item['tarefa_id']] ?? []);
            $concluida = (bool) ($resumo['concluida'] ?? false);
            $estaAtual = (int) $item['tarefa_id'] === (int) $id;
            $estado = $estaAtual ? 'ATUAL' : ($concluida ? 'CONCLUIDA' : (flow_planejamento_status_hold((string) $item['status']) ? 'BLOQUEADA' : 'PROXIMA'));
            $prazo = flow_tarefa_planejamento_data_valida($item['prazo'] ?? null);
            $timeline[] = [
                'codigo' => 'FUNCAO_' . (int) $item['funcao_id'],
                'nome' => (string) ($item['nome_funcao'] ?: 'Etapa operacional'),
                'estado' => $estado,
                'prazo' => $prazo,
                'data_conclusao' => $resumo['conclusao_real'] ?? null,
                'desvio' => $concluida ? flow_tarefa_planejamento_desvio($prazo, $resumo['conclusao_real'] ?? null) : null,
            ];
        }
        $contextos[$id]['timeline'] = $timeline;
        $contextos[$id]['origem_timeline'] = 'OPERACIONAL_FALLBACK';
        $contextos[$id]['mensagem_contexto'] = 'Esta obra ainda não possui planejamento confirmado. A sequência abaixo reflete as etapas operacionais cadastradas.';
    }
    return $contextos;
}

function flow_tarefa_planejamento_conclusao_por_tarefa(array $item, array $logs): ?string
{
    return flow_execucao_resumir_tarefa($item, $logs)['conclusao_real'] ?? null;
}

/**
 * Resolve todos os contextos de uma lista de tarefas sem consulta por card.
 * A lista deve conter idfuncao_imagem, imagem_id, funcao_id, tipo_imagem e status.
 */
function flow_tarefa_contextos_planejamento_lote(mysqli $conn, array $tarefas): array
{
    $resultado = [];
    $porTarefa = [];
    foreach ($tarefas as $tarefa) {
        $id = (int) ($tarefa['idfuncao_imagem'] ?? $tarefa['tarefa_id'] ?? 0);
        if (!$id || !empty($tarefa['is_animacao'])) {
            continue;
        }
        $tarefa['idfuncao_imagem'] = $id;
        $porTarefa[$id] = $tarefa;
        $resultado[$id] = flow_tarefa_planejamento_contexto_vazio($tarefa);
    }
    if (!$porTarefa) {
        return $resultado;
    }
    $resultado = flow_tarefa_timeline_operacional_fallback_lote($conn, $porTarefa, $resultado);
    if (!flow_planejamento_tabelas_persistencia_disponiveis($conn)) {
        return $resultado;
    }

    $imagemIds = array_values(array_unique(array_filter(array_map(static fn (array $t): int => (int) ($t['imagem_id'] ?? 0), $porTarefa))));
    if (!$imagemIds) {
        return $resultado;
    }
    $inImagens = implode(',', array_fill(0, count($imagemIds), '?'));
    $sqlPlanos = "SELECT ei.imagem_id, e.id AS entrega_id, v.id AS versao_id, v.numero AS plano_versao, v.snapshot_json
                    FROM entregas_itens ei
                    JOIN entregas e ON e.id = ei.entrega_id AND e.status_id = 2
                    JOIN entrega_planejamento_producao p ON p.entrega_id = e.id AND p.estado = 'CONFIRMADO'
                    JOIN entrega_planejamento_versao v ON v.id = p.versao_atual_id AND v.vigente = 1
                   WHERE ei.imagem_id IN ($inImagens)
                   ORDER BY e.id DESC";
    $stmt = $conn->prepare($sqlPlanos);
    if (!$stmt) {
        return $resultado;
    }
    $tipos = str_repeat('i', count($imagemIds));
    $stmt->bind_param($tipos, ...$imagemIds);
    $stmt->execute();
    $planosPorImagem = [];
    $resPlanos = $stmt->get_result();
    while ($row = $resPlanos->fetch_assoc()) {
        $imagemId = (int) $row['imagem_id'];
        if (!isset($planosPorImagem[$imagemId])) {
            $planosPorImagem[$imagemId] = $row;
        }
    }
    $stmt->close();
    if (!$planosPorImagem) {
        return $resultado;
    }

    $entregaPorTarefa = [];
    $entregas = [];
    foreach ($porTarefa as $id => $tarefa) {
        $plano = $planosPorImagem[(int) ($tarefa['imagem_id'] ?? 0)] ?? null;
        $codigo = flow_planejamento_codigo_etapa($tarefa);
        if (!$plano || !$codigo) {
            continue;
        }
        $snapshot = json_decode((string) $plano['snapshot_json'], true);
        if (!is_array($snapshot)) {
            continue;
        }
        $etapas = [];
        foreach ((array) ($snapshot['etapas'] ?? []) as $etapa) {
            $etapas[(string) ($etapa['codigo'] ?? '')] = $etapa;
        }
        if (empty($etapas[$codigo]['limite'])) {
            continue;
        }
        $entregaId = (int) $plano['entrega_id'];
        $entregas[$entregaId] = ['plano' => $plano, 'snapshot' => $snapshot, 'etapas' => $etapas];
        $entregaPorTarefa[$id] = ['entrega_id' => $entregaId, 'codigo' => $codigo];
    }
    if (!$entregaPorTarefa) {
        return $resultado;
    }

    $entregaIds = array_keys($entregas);
    $inEntregas = implode(',', array_fill(0, count($entregaIds), '?'));
    $sqlItens = "SELECT ei.entrega_id, fi.idfuncao_imagem AS tarefa_id, fi.imagem_id, fi.funcao_id, fi.status, ico.tipo_imagem
                    FROM entregas_itens ei
                    JOIN funcao_imagem fi ON fi.imagem_id = ei.imagem_id
                    JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
                   WHERE ei.entrega_id IN ($inEntregas)";
    $stmt = $conn->prepare($sqlItens);
    if (!$stmt) {
        return $resultado;
    }
    $tipos = str_repeat('i', count($entregaIds));
    $stmt->bind_param($tipos, ...$entregaIds);
    $stmt->execute();
    $itensPorEntrega = [];
    $todosItens = [];
    $resItens = $stmt->get_result();
    while ($row = $resItens->fetch_assoc()) {
        $row['tarefa_id'] = (int) $row['tarefa_id'];
        $itensPorEntrega[(int) $row['entrega_id']][] = $row;
        $todosItens[] = $row;
    }
    $stmt->close();
    $logsPorTarefa = $todosItens ? flow_execucao_carregar_logs($conn, $todosItens) : [];

    $execucaoPorEntrega = [];
    foreach ($entregas as $entregaId => $entrega) {
        $grupos = flow_execucao_itens_por_etapa($itensPorEntrega[$entregaId] ?? [])['grupos'];
        $estagios = [];
        foreach ((array) ($entrega['snapshot']['etapas'] ?? []) as $planejada) {
            $codigo = (string) ($planejada['codigo'] ?? '');
            if (!$codigo || $codigo === 'FINALIZACAO_GLOBAL') {
                continue;
            }
            $grupo = $grupos[$codigo] ?? [];
            $resumos = [];
            foreach ($grupo as $item) {
                $resumos[] = flow_execucao_resumir_tarefa($item, $logsPorTarefa[(int) ($item['tarefa_id'] ?? 0)] ?? []);
            }
            $concluidas = $resumos && !array_filter($resumos, static fn (array $r): bool => empty($r['concluida']));
            $inicios = array_values(array_filter(array_column($resumos, 'inicio_real')));
            $conclusoes = array_values(array_filter(array_column($resumos, 'conclusao_real')));
            $estagios[$codigo] = [
                'codigo' => $codigo,
                'nome' => (string) ($planejada['nome'] ?? $planejada['nome_etapa'] ?? $codigo),
                'inicio_planejado' => flow_tarefa_planejamento_data_valida($planejada['inicio'] ?? $planejada['data_inicio'] ?? null),
                'limite_planejado' => flow_tarefa_planejamento_data_valida($planejada['limite'] ?? $planejada['data_limite'] ?? null),
                'concluida' => (bool) $concluidas,
                'inicio_real' => $inicios ? min($inicios) : null,
                'conclusao_real' => $conclusoes && $concluidas ? max($conclusoes) : null,
                'dependencias' => (array) ($planejada['dependencias'] ?? []),
            ];
        }
        $execucaoPorEntrega[$entregaId] = $estagios;
    }

    $previsoes = [];
    $conclusoesHistoricas = [];
    if (flow_tarefa_planejamento_persistencia_disponivel($conn)) {
        $ids = array_keys($porTarefa);
        $inIds = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("SELECT funcao_imagem_id, previsao_conclusao, justificativa FROM funcao_imagem_previsao_conclusao WHERE funcao_imagem_id IN ($inIds)");
        if ($stmt) {
            $tipos = str_repeat('i', count($ids));
            $stmt->bind_param($tipos, ...$ids);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $previsoes[(int) $row['funcao_imagem_id']] = $row;
            }
            $stmt->close();
        }
        $stmt = $conn->prepare("SELECT h.funcao_imagem_id, h.prazo_necessario
                                  FROM funcao_imagem_previsao_historico h
                                  JOIN (SELECT funcao_imagem_id, MAX(id) id FROM funcao_imagem_previsao_historico WHERE evento = 'CONCLUSAO_REGISTRADA' AND funcao_imagem_id IN ($inIds) GROUP BY funcao_imagem_id) u ON u.id = h.id");
        if ($stmt) {
            $tipos = str_repeat('i', count($ids));
            $stmt->bind_param($tipos, ...$ids);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $conclusoesHistoricas[(int) $row['funcao_imagem_id']] = flow_tarefa_planejamento_data_valida($row['prazo_necessario']);
            }
            $stmt->close();
        }
    }

    foreach ($entregaPorTarefa as $id => $referencia) {
        $tarefa = $porTarefa[$id];
        $entrega = $entregas[$referencia['entrega_id']];
        $estagios = $execucaoPorEntrega[$referencia['entrega_id']] ?? [];
        $atual = $estagios[$referencia['codigo']] ?? null;
        if (!$atual) {
            continue;
        }
        $prazoAtual = $atual['limite_planejado'];
        $prazoHistorico = $conclusoesHistoricas[$id] ?? null;
        $prazo = flow_planejamento_status_finalizado((string) ($tarefa['status'] ?? '')) && $prazoHistorico ? $prazoHistorico : $prazoAtual;
        $previsao = flow_tarefa_planejamento_data_valida($previsoes[$id]['previsao_conclusao'] ?? null);
        $desvioChegada = null;
        $chegada = $atual['inicio_real'];
        if (!$chegada && !empty($atual['dependencias'])) {
            $terminos = [];
            foreach ($atual['dependencias'] as $dep) {
                if (!empty($estagios[$dep]['conclusao_real'])) {
                    $terminos[] = $estagios[$dep]['conclusao_real'];
                }
            }
            if ($terminos) {
                $chegada = max($terminos);
            }
        }
        if ($chegada) {
            $desvioChegada = flow_tarefa_planejamento_desvio($atual['inicio_planejado'], $chegada);
        }

        $timeline = [];
        foreach ((array) ($entrega['snapshot']['etapas'] ?? []) as $planejada) {
            $codigo = (string) ($planejada['codigo'] ?? '');
            if (!$codigo || $codigo === 'FINALIZACAO_GLOBAL' || empty($estagios[$codigo])) {
                continue;
            }
            $etapa = $estagios[$codigo];
            $desvio = $etapa['concluida']
                ? flow_tarefa_planejamento_desvio($etapa['limite_planejado'], $etapa['conclusao_real'])
                : ($codigo === $referencia['codigo'] ? $desvioChegada : null);
            $timeline[] = [
                'codigo' => $codigo,
                'nome' => $etapa['nome'],
                'estado' => $codigo === $referencia['codigo'] ? 'ATUAL' : ($etapa['concluida'] ? 'CONCLUIDA' : 'PROXIMA'),
                'desvio' => $desvio,
                'prazo' => $etapa['limite_planejado'],
            ];
        }
        $mensagem = $desvioChegada === null || $desvioChegada === 0
            ? 'O processo chegou a esta etapa dentro do planejamento.'
            : ($desvioChegada < 0
                ? 'O processo recuperou o desvio das etapas anteriores e chegou dentro do prazo.'
                : 'O processo chegou a esta etapa com +' . $desvioChegada . ' ' . ($desvioChegada === 1 ? 'dia útil' : 'dias úteis') . ' de desvio.');
        $conclusao = flow_tarefa_planejamento_conclusao_por_tarefa($tarefa, $logsPorTarefa[$id] ?? []);
        $resultado[$id] = [
            'planejamento_disponivel' => true,
            'etapa_codigo' => $referencia['codigo'],
            'etapa_nome' => $atual['nome'],
            'janela_inicio' => $atual['inicio_planejado'],
            'janela_fim' => $prazoAtual,
            'prazo_necessario' => $prazo,
            'prazo_necessario_historico' => (bool) $prazoHistorico,
            'previsao_colaborador' => $previsao,
            'justificativa_previsao' => $previsoes[$id]['justificativa'] ?? null,
            'diferenca_previsao_dias_uteis' => $previsao && $prazo ? flow_tarefa_planejamento_desvio($prazo, $previsao) : null,
            'status_temporal' => flow_tarefa_planejamento_status_temporal($prazo, (string) ($tarefa['status'] ?? ''), $conclusao),
            'desvio_acumulado' => $desvioChegada,
            'mensagem_contexto' => $mensagem,
            'timeline' => $timeline,
            'origem_timeline' => 'PLANEJAMENTO',
            'plano_versao' => (int) $entrega['plano']['plano_versao'],
            'versao_id' => (int) $entrega['plano']['versao_id'],
            'tarefa_id' => $id,
        ];
    }
    return $resultado;
}

function flow_tarefa_contexto_planejamento(mysqli $conn, array $tarefa): array
{
    $id = (int) ($tarefa['idfuncao_imagem'] ?? $tarefa['tarefa_id'] ?? 0);
    return flow_tarefa_contextos_planejamento_lote($conn, [$tarefa])[$id] ?? flow_tarefa_planejamento_contexto_vazio($tarefa);
}

function flow_tarefa_planejamento_registrar_conclusao(mysqli $conn, int $funcaoImagemId, ?int $atorColaboradorId, ?int $atorUsuarioId): void
{
    if (!flow_tarefa_planejamento_persistencia_disponivel($conn)) {
        return;
    }
    $stmt = $conn->prepare('SELECT fi.idfuncao_imagem, fi.imagem_id, fi.funcao_id, fi.status, ico.tipo_imagem FROM funcao_imagem fi JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id WHERE fi.idfuncao_imagem = ? LIMIT 1');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $funcaoImagemId);
    $stmt->execute();
    $tarefa = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$tarefa) {
        return;
    }
    $contexto = flow_tarefa_contexto_planejamento($conn, $tarefa);
    if (empty($contexto['prazo_necessario'])) {
        return;
    }
    $previsao = $contexto['previsao_colaborador'];
    $justificativa = $contexto['justificativa_previsao'];
    $diferenca = $contexto['diferenca_previsao_dias_uteis'];
    $versao = $contexto['versao_id'];
    $prazo = $contexto['prazo_necessario'];
    $stmt = $conn->prepare('INSERT INTO funcao_imagem_previsao_historico (funcao_imagem_id, evento, prazo_necessario, previsao_conclusao, diferenca_dias_uteis, justificativa, versao_planejamento_id, ator_colaborador_id, ator_usuario_id) VALUES (?, \'CONCLUSAO_REGISTRADA\', ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('issisiii', $funcaoImagemId, $prazo, $previsao, $diferenca, $justificativa, $versao, $atorColaboradorId, $atorUsuarioId);
    $stmt->execute();
    $stmt->close();
}
