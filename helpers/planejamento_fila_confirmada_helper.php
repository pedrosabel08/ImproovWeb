<?php

/** V1.5B/C/D: overrides confirmados, simulação, histórico e auditoria. */
require_once __DIR__ . '/planejamento_fila_operacional_helper.php';

function flow_fila_confirmada_tabelas_disponiveis(mysqli $conn): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    foreach (['entrega_planejamento_fila_operacional', 'entrega_planejamento_projecao_operacional', 'entrega_planejamento_projecao_etapa'] as $tabela) {
        $stmt = $conn->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
        $stmt->bind_param('s', $tabela);
        $stmt->execute();
        $existe = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if (!$existe) {
            return $ok = false;
        }
    }
    $coluna = $conn->query("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'entrega_planejamento_fila_operacional' AND column_name = 'referencia_fila' LIMIT 1");
    if (!$coluna || $coluna->num_rows === 0) {
        return $ok = false;
    }
    $coluna->free();
    return $ok = true;
}

/**
 * A validade da ORDEM não depende de uma tarefa ter avançado: conclusão/HOLD
 * tornam a projeção histórica desatualizada, mas não apagam a decisão humana.
 * Mudanças de responsável, prioridade, plano/versão ou entrada/saída de bloco
 * invalidam a fila e exigem nova simulação.
 */
function flow_fila_confirmada_fingerprint_fila(array $projecoes): string
{
    $contexto = [];
    foreach ($projecoes as $p) {
        $contexto[] = [
            'entrega' => (int) $p['entrega_id'], 'versao' => (int) $p['versao_vigente_id'],
            'fila' => array_map(static fn (array $f) => [
                'colaborador' => (int) $f['colaborador_id'],
                'blocos' => array_map(static fn (array $b) => [
                    'chave' => $b['fila_chave'] ?? '', 'prioridade' => (int) ($b['prioridade'] ?? 0),
                    // status propositalmente não faz parte da fila confirmada.
                    'tarefas' => array_map(static fn (array $t) => [(int) ($t['id'] ?? 0), (int) ($t['funcao_id'] ?? 0), (int) ($t['colaborador_id'] ?? 0)], $b['tarefas_contexto'] ?? []),
                ], $f['fila_completa'] ?? []),
            ], $p['filas_responsaveis'] ?? []),
        ];
    }
    usort($contexto, static fn ($a, $b) => $a['entrega'] <=> $b['entrega']);
    return hash('sha256', flow_planejamento_json($contexto));
}

function flow_fila_confirmada_fingerprint_projecao(array $projecoes): string
{
    $contexto = [];
    foreach ($projecoes as $p) {
        $contexto[] = [
            'entrega' => (int) $p['entrega_id'], 'fim' => $p['fim_operacional_projetado'] ?? null,
            'etapas' => array_map(static fn (array $e) => [
                $e['codigo'] ?? '', $e['inicio_operacional_projetado'] ?? null, $e['fim_operacional_projetado'] ?? null,
                $e['status_operacional'] ?? '', $e['confianca'] ?? '',
            ], $p['etapas'] ?? []),
        ];
    }
    usort($contexto, static fn ($a, $b) => $a['entrega'] <=> $b['entrega']);
    return hash('sha256', flow_planejamento_json($contexto));
}

/** Compatibilidade para consumidores que já esperavam a chave fingerprint. */
function flow_fila_confirmada_fingerprint(array $projecoes): string
{
    return flow_fila_confirmada_fingerprint_fila($projecoes);
}

function flow_fila_confirmada_carregar_overrides(mysqli $conn, array $entregaIds = []): array
{
    if (!flow_fila_confirmada_tabelas_disponiveis($conn)) {
        return [];
    }
    // Overrides são poucos e a posição de outra entrega também influencia a
    // disponibilidade do colaborador. Carregar todos evita desconsiderar uma
    // decisão já confirmada fora do filtro atualmente aberto.
    $result = $conn->query('SELECT f.*, c.nome_colaborador AS confirmado_por_nome FROM entrega_planejamento_fila_operacional f LEFT JOIN colaborador c ON c.idcolaborador = f.confirmado_por_colaborador_id WHERE f.ativo = 1 ORDER BY f.colaborador_id, f.codigo_etapa, f.posicao, f.id');
    if (!$result) {
        throw new RuntimeException($conn->error);
    }
    $overrides = [];
    while ($row = $result->fetch_assoc()) {
        $chave = (string) ($row['referencia_fila'] ?? ('ENTREGA:' . (int) $row['entrega_id'] . ':' . $row['codigo_etapa']));
        $overrides[(int) $row['colaborador_id']][$chave] = (int) $row['posicao'];
    }
    $result->free();
    return $overrides;
}

function flow_fila_confirmada_carregar_decisoes(mysqli $conn): array
{
    if (!flow_fila_confirmada_tabelas_disponiveis($conn)) {
        return [];
    }
    $sql = 'SELECT f.*, c.nome_colaborador AS confirmado_por_nome
              FROM entrega_planejamento_fila_operacional f
         LEFT JOIN colaborador c ON c.idcolaborador = f.confirmado_por_colaborador_id
             WHERE f.ativo = 1
          ORDER BY f.colaborador_id, f.codigo_etapa, f.posicao, f.id';
    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException($conn->error);
    }
    $decisoes = [];
    while ($row = $result->fetch_assoc()) {
        $chave = (string) ($row['referencia_fila'] ?? ('ENTREGA:' . (int) $row['entrega_id'] . ':' . $row['codigo_etapa']));
        $decisoes[(int) $row['colaborador_id']][$chave] = $row;
    }
    $result->free();
    return $decisoes;
}

function flow_fila_confirmada_ultimo_snapshot(mysqli $conn, int $entregaId): ?array
{
    if (!flow_fila_confirmada_tabelas_disponiveis($conn)) {
        return null;
    }
    $stmt = $conn->prepare('SELECT id, fingerprint, confirmado_em, confirmado_por_colaborador_id FROM entrega_planejamento_projecao_operacional WHERE entrega_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $entregaId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function flow_fila_confirmada_ultimos_snapshots(mysqli $conn, array $entregaIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $entregaIds))));
    if (!$ids || !flow_fila_confirmada_tabelas_disponiveis($conn)) {
        return [];
    }
    $lista = implode(',', $ids);
    $sql = "SELECT p.id, p.entrega_id, p.fingerprint, p.confirmado_em, p.confirmado_por_colaborador_id
              FROM entrega_planejamento_projecao_operacional p
              JOIN (SELECT entrega_id, MAX(id) AS id FROM entrega_planejamento_projecao_operacional WHERE entrega_id IN ({$lista}) GROUP BY entrega_id) ultimo ON ultimo.id = p.id";
    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException($conn->error);
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[(int) $row['entrega_id']] = $row;
    }
    $result->free();
    return $rows;
}

function flow_fila_confirmada_projetar(mysqli $conn, array $filtros = [], array $opcoes = []): array
{
    $planos = flow_fila_carregar_planos_confirmados($conn, $filtros);
    $ids = array_keys($planos);
    $overrides = $opcoes['overrides'] ?? flow_fila_confirmada_carregar_overrides($conn, $ids);
    $projecoes = [];
    foreach ($planos as $id => $plano) {
        $projecoes[$id] = flow_fila_projetar_entrega($conn, (int) $id, $planos, $opcoes + ['overrides' => $overrides]);
    }
    $valores = array_values($projecoes);
    $decisoes = flow_fila_confirmada_carregar_decisoes($conn);
    $snapshots = flow_fila_confirmada_ultimos_snapshots($conn, array_keys($projecoes));
    foreach ($valores as &$projecao) {
        $confirmacoes = [];
        foreach ($projecao['filas_responsaveis'] as &$fila) {
            $confirmada = false;
            $porEtapa = [];
            foreach ((array) ($fila['fila_completa'] ?? []) as $bloco) {
                $porEtapa[(string) ($bloco['codigo_etapa'] ?? '')][] = $bloco;
                $decisao = $decisoes[(int) ($fila['colaborador_id'] ?? 0)][$bloco['fila_chave'] ?? ''] ?? null;
                if (!$decisao) {
                    continue;
                }
                $confirmada = true;
                $confirmacoes[] = [
                    'colaborador_id' => (int) $fila['colaborador_id'],
                    'codigo_etapa' => $bloco['codigo_etapa'] ?? '',
                    'confirmado_em' => $decisao['confirmado_em'],
                    'confirmado_por' => $decisao['confirmado_por_nome'] ?? null,
                ];
            }
            $fila['fingerprints_etapas'] = [];
            foreach ($porEtapa as $codigo => $blocos) {
                $fila['fingerprints_etapas'][$codigo] = flow_fila_confirmada_fingerprint_linha($blocos);
            }
            if ($confirmada) {
                $fila['tipo_fila'] = 'CONFIRMADA';
            }
        }
        unset($fila);
        $projecao['confirmacoes_fila'] = $confirmacoes;
        $snapshot = $snapshots[(int) $projecao['entrega_id']] ?? null;
        $atual = flow_fila_confirmada_fingerprint_projecao([$projecao]);
        $projecao['projecao_confirmada'] = $snapshot ? [
            'id' => (int) $snapshot['id'],
            'confirmado_em' => $snapshot['confirmado_em'],
            'desatualizada' => !hash_equals((string) $snapshot['fingerprint'], $atual),
            'status' => hash_equals((string) $snapshot['fingerprint'], $atual) ? 'ATUAL' : 'PROJECAO_DESATUALIZADA',
        ] : null;
    }
    unset($projecao);
    return [
        'projecoes' => $valores, 'overrides' => $overrides,
        'fingerprint' => flow_fila_confirmada_fingerprint_fila($valores),
        'fingerprint_fila' => flow_fila_confirmada_fingerprint_fila($valores),
        'fingerprint_projecao' => flow_fila_confirmada_fingerprint_projecao($valores),
    ];
}

function flow_fila_confirmada_normalizar_ordem(array $ordem): array
{
    $itens = [];
    foreach ($ordem as $item) {
        if (!is_array($item)) {
            continue;
        }
        $entrega = (int) ($item['entrega_id'] ?? 0);
        $etapa = strtoupper(trim((string) ($item['codigo_etapa'] ?? '')));
        $referencia = trim((string) ($item['referencia_fila'] ?? ''));
        // Entrega 0 representa trabalho operacional real ainda não vinculado a
        // uma entrega planejada. Ele ocupa a fila, mas não gera snapshot/evento.
        if ($entrega >= 0 && preg_match('/^[A-Z0-9_]+$/', $etapa) && ($entrega > 0 || $referencia !== '')) {
            $itens[] = ['entrega_id' => $entrega, 'codigo_etapa' => $etapa, 'referencia_fila' => $referencia ?: ('ENTREGA:' . $entrega . ':' . $etapa)];
        }
    }
    $vistos = [];
    $resultado = [];
    foreach ($itens as $item) {
        $chave = $item['referencia_fila'];
        if (!isset($vistos[$chave])) {
            $vistos[$chave] = true;
            $resultado[] = $item;
        }
    }
    return $resultado;
}

function flow_fila_confirmada_classificar(array $antes, array $depois, int $entregaFoco): string
{
    $mapaAntes = [];
    foreach ($antes as $p) {
        $mapaAntes[(int) $p['entrega_id']] = $p;
    }
    $focoAntes = $mapaAntes[$entregaFoco] ?? [];
    $focoDepois = null;
    $piorouOutro = false;
    foreach ($depois as $p) {
        if ((int) $p['entrega_id'] === $entregaFoco) {
            $focoDepois = $p;
        }
        $anterior = $mapaAntes[(int) $p['entrega_id']] ?? [];
        if ((int) ($p['margem_operacional_dias_uteis'] ?? 0) < 0 && (int) ($anterior['margem_operacional_dias_uteis'] ?? 0) >= 0 && (int) $p['entrega_id'] !== $entregaFoco) {
            $piorouOutro = true;
        }
    }
    if (!$focoDepois || ($focoAntes['fim_operacional_projetado'] ?? null) === ($focoDepois['fim_operacional_projetado'] ?? null)) {
        return 'SEM_GANHO';
    }
    if ($piorouOutro) {
        return 'TRANSFERE_PROBLEMA';
    }
    return ((int) ($focoAntes['margem_operacional_dias_uteis'] ?? -1) < 0 && (int) ($focoDepois['margem_operacional_dias_uteis'] ?? -1) >= 0)
        ? 'RESOLVE' : 'RESOLVE_PARCIALMENTE';
}

function flow_fila_confirmada_linha_colaborador(array $projecoes, int $colaboradorId, string $codigoEtapa): array
{
    foreach ($projecoes as $projecao) {
        foreach ((array) ($projecao['filas_responsaveis'] ?? []) as $fila) {
            if ((int) ($fila['colaborador_id'] ?? 0) !== $colaboradorId) {
                continue;
            }
            $itens = array_values(array_filter((array) ($fila['fila_completa'] ?? []), static fn (array $b): bool => ($b['codigo_etapa'] ?? '') === $codigoEtapa));
            if ($itens) {
                return $itens;
            }
        }
    }
    return [];
}

function flow_fila_confirmada_fingerprint_linha(array $blocos): string
{
    $contexto = array_map(static fn (array $b): array => [
        'chave' => $b['fila_chave'] ?? '',
        'prioridade' => (int) ($b['prioridade'] ?? 0),
        'tarefas' => array_map(static fn (array $t): array => [(int) ($t['id'] ?? 0), (int) ($t['funcao_id'] ?? 0), (int) ($t['colaborador_id'] ?? 0)], $b['tarefas_contexto'] ?? []),
    ], $blocos);
    return hash('sha256', flow_planejamento_json($contexto));
}

function flow_fila_confirmada_ids_afetados(mysqli $conn, int $colaboradorId, array $ordem, array $opcoes): array
{
    $foco = (int) ($opcoes['entrega_id'] ?? $ordem[0]['entrega_id'] ?? 0);
    $codigo = (string) ($opcoes['codigo_etapa'] ?? $ordem[0]['codigo_etapa'] ?? '');
    $semente = flow_fila_confirmada_projetar($conn, ['entrega_ids' => [$foco]], $opcoes);
    $ids = [$foco];
    foreach (flow_fila_confirmada_linha_colaborador($semente['projecoes'], $colaboradorId, $codigo) as $bloco) {
        if (!empty($bloco['entrega_id'])) {
            $ids[] = (int) $bloco['entrega_id'];
        }
    }
    foreach ($ordem as $item) {
        $ids[] = (int) $item['entrega_id'];
    }
    return array_values(array_unique(array_filter($ids)));
}

function flow_fila_confirmada_simular(mysqli $conn, int $colaboradorId, array $ordem, string $fingerprintEsperado, array $opcoes = []): array
{
    $ordem = flow_fila_confirmada_normalizar_ordem($ordem);
    if ($colaboradorId <= 0 || !$ordem) {
        throw new InvalidArgumentException('Informe colaborador e uma ordem operacional válida.');
    }
    $codigoEtapa = (string) ($opcoes['codigo_etapa'] ?? $ordem[0]['codigo_etapa']);
    if (array_filter($ordem, static fn (array $item): bool => $item['codigo_etapa'] !== $codigoEtapa)) {
        throw new InvalidArgumentException('Organize uma etapa por vez para preservar a granularidade da fila.');
    }
    $entregaFoco = (int) ($opcoes['entrega_id'] ?? $ordem[0]['entrega_id']);
    // A interface envia a linha inteira da etapa. Assim a simulação precisa
    // apenas de antes/depois (duas projeções), sem uma consulta preparatória
    // por obra, e ainda consegue demonstrar todos os blocos afetados.
    $ids = array_values(array_unique(array_filter(array_merge([$entregaFoco], array_column($ordem, 'entrega_id')))));
    $antes = flow_fila_confirmada_projetar($conn, ['entrega_ids' => $ids], $opcoes);
    $linhaAtual = flow_fila_confirmada_linha_colaborador($antes['projecoes'], $colaboradorId, $codigoEtapa);
    $chavesAtuais = array_column($linhaAtual, 'fila_chave');
    $chavesPropostas = array_column($ordem, 'referencia_fila');
    sort($chavesAtuais);
    sort($chavesPropostas);
    if ($chavesAtuais !== $chavesPropostas) {
        throw new RuntimeException('FILA_DESATUALIZADA: envie a fila completa da etapa antes de simular.');
    }
    $fingerprintLinha = flow_fila_confirmada_fingerprint_linha($linhaAtual);
    if ($fingerprintEsperado !== '' && !hash_equals($fingerprintLinha, $fingerprintEsperado)) {
        throw new RuntimeException('FILA_DESATUALIZADA: a fila mudou antes da simulação.');
    }
    $overrides = $antes['overrides'];
    foreach ($ordem as $posicao => $item) {
        $overrides[$colaboradorId][$item['referencia_fila']] = $posicao + 1;
    }
    $depois = flow_fila_confirmada_projetar($conn, ['entrega_ids' => $ids], $opcoes + ['overrides' => $overrides]);
    $antesPorId = [];
    foreach ($antes['projecoes'] as $p) {
        $antesPorId[(int) $p['entrega_id']] = $p;
    }
    $impactos = [];
    foreach ($depois['projecoes'] as $p) {
        $a = $antesPorId[(int) $p['entrega_id']] ?? [];
        $impactos[] = ['entrega_id' => (int) $p['entrega_id'], 'obra' => $p['obra'], 'antes' => $a, 'depois' => $p,
            'variacao_fim_dias_uteis' => (!empty($a['fim_operacional_projetado']) && !empty($p['fim_operacional_projetado'])) ? flow_execucao_desvio($a['fim_operacional_projetado'], $p['fim_operacional_projetado']) : null];
    }
    return ['fila_antes' => $antes, 'fila_depois' => $depois, 'ordem_proposta' => $ordem,
        'fila_atual' => $linhaAtual,
        'classificacao' => flow_fila_confirmada_classificar($antes['projecoes'], $depois['projecoes'], $entregaFoco), 'impactos' => $impactos,
        'fingerprint_atual' => $fingerprintLinha, 'fingerprint_simulado' => $fingerprintLinha,
        'fingerprint_projecao_atual' => $antes['fingerprint_projecao'], 'fingerprint_projecao_simulada' => $depois['fingerprint_projecao'],
        'codigo_etapa' => $codigoEtapa, 'entrega_ids_afetadas' => $ids];
}

/** Busca limitada: testa apenas a posição do bloco foco na mesma fila/etapa. */
function flow_fila_confirmada_encontrar_melhor_ordem(mysqli $conn, int $colaboradorId, int $entregaId, string $codigoEtapa, string $fingerprintEsperado): array
{
    $semente = flow_fila_confirmada_projetar($conn, ['entrega_ids' => [$entregaId]]);
    $atual = flow_fila_confirmada_linha_colaborador($semente['projecoes'], $colaboradorId, $codigoEtapa);
    if ($fingerprintEsperado !== '' && !hash_equals($fingerprintEsperado, flow_fila_confirmada_fingerprint_linha($atual))) {
        throw new RuntimeException('FILA_DESATUALIZADA: a fila mudou antes da sugestão.');
    }
    $chaveFoco = 'ENTREGA:' . $entregaId . ':' . $codigoEtapa;
    $original = array_map(static fn (array $b): array => ['entrega_id' => (int) $b['entrega_id'], 'codigo_etapa' => (string) $b['codigo_etapa'], 'referencia_fila' => (string) $b['fila_chave']], $atual);
    if (count($atual) < 2) {
        return ['fila_atual' => $atual, 'ordem_proposta' => $original, 'mensagem' => 'Não há outra obra nesta fila para reorganizar.'];
    }
    $melhor = null;
    foreach (array_keys($original) as $posicao) {
        $tentativa = $original;
        $indiceFoco = array_search($chaveFoco, array_column($tentativa, 'referencia_fila'), true);
        if ($indiceFoco === false) {
            continue;
        }
        $item = array_splice($tentativa, $indiceFoco, 1)[0];
        array_splice($tentativa, $posicao, 0, [$item]);
        $sim = flow_fila_confirmada_simular($conn, $colaboradorId, $tentativa, $fingerprintEsperado, ['entrega_id' => $entregaId, 'codigo_etapa' => $codigoEtapa]);
        $peso = ['RESOLVE' => 0, 'RESOLVE_PARCIALMENTE' => 1, 'SEM_GANHO' => 2, 'TRANSFERE_PROBLEMA' => 3][$sim['classificacao']] ?? 4;
        $foco = array_values(array_filter((array) ($sim['fila_depois']['projecoes'] ?? []), static fn (array $p): bool => (int) ($p['entrega_id'] ?? 0) === $entregaId));
        $margem = (int) (($foco[0]['margem_operacional_dias_uteis'] ?? -999));
        $chave = [$peso, -$margem, $posicao];
        if ($melhor === null || $chave < $melhor['chave']) {
            $melhor = ['chave' => $chave, 'simulacao' => $sim];
        }
    }
    return ($melhor['simulacao'] ?? $base) + ['heuristica' => 'Testadas somente as posições possíveis do bloco foco nesta fila/etapa; nenhuma permutação irrestrita foi executada.'];
}

function flow_fila_confirmada_persistir_snapshot(mysqli $conn, array $projecao, string $fingerprint, ?int $atorId): void
{
    $json = flow_planejamento_json($projecao);
    $stmt = $conn->prepare('INSERT INTO entrega_planejamento_projecao_operacional (planejamento_id, versao_id, entrega_id, obra_id, status_operacional, data_referencia, fim_operacional_projetado, margem_operacional_dias_uteis, fingerprint, confirmado_por_colaborador_id, snapshot_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('iiiisssisis', $projecao['planejamento_id'], $projecao['versao_vigente_id'], $projecao['entrega_id'], $projecao['obra_id'], $projecao['status_operacional'], $projecao['data_referencia'], $projecao['fim_operacional_projetado'], $projecao['margem_operacional_dias_uteis'], $fingerprint, $atorId, $json);
    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error);
    }
    $id = (int) $conn->insert_id;
    $stmt->close();
    $stmtEtapa = $conn->prepare('INSERT INTO entrega_planejamento_projecao_etapa (projecao_id, codigo_etapa, inicio_operacional_projetado, fim_operacional_projetado, desvio_baseline_dias_uteis, desvio_plano_vigente_dias_uteis, margem_operacional_dias_uteis, status_operacional, confianca, explicacao_json) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?)');
    foreach ($projecao['etapas'] as $e) {
        $exp = flow_planejamento_json(['frentes' => $e['frentes'] ?? [], 'dependencias' => $e['dependencias'] ?? []]);
        $stmtEtapa->bind_param('isssiisss', $id, $e['codigo'], $e['inicio_operacional_projetado'], $e['fim_operacional_projetado'], $e['desvio_baseline_dias_uteis'], $e['desvio_plano_vigente_dias_uteis'], $e['status_operacional'], $e['confianca'], $exp);
        if (!$stmtEtapa->execute()) {
            throw new RuntimeException($stmtEtapa->error);
        }
    }
    $stmtEtapa->close();
}

function flow_fila_confirmada_confirmar(mysqli $conn, int $colaboradorId, array $ordem, string $fingerprintEsperado, ?int $atorId, string $motivo = '', array $opcoes = []): array
{
    if (!improov_usuario_eh_gestor_sidebar($conn)) {
        throw new RuntimeException('Sem permissão para confirmar fila operacional.');
    }
    if (!flow_fila_confirmada_tabelas_disponiveis($conn)) {
        throw new RuntimeException('A migration da fila operacional ainda não foi aplicada.');
    }
    $simulacao = flow_fila_confirmada_simular($conn, $colaboradorId, $ordem, $fingerprintEsperado, $opcoes);
    if (in_array($simulacao['classificacao'], ['TRANSFERE_PROBLEMA'], true) && trim($motivo) === '') {
        throw new InvalidArgumentException('Informe o motivo para confirmar um cenário que transfere problema.');
    }
    $conn->begin_transaction();
    try {
        $atuais = flow_fila_confirmada_projetar($conn, ['entrega_ids' => $simulacao['entrega_ids_afetadas']], $opcoes);
        $linhaAtual = flow_fila_confirmada_linha_colaborador($atuais['projecoes'], $colaboradorId, $simulacao['codigo_etapa']);
        if (!hash_equals($simulacao['fingerprint_atual'], flow_fila_confirmada_fingerprint_linha($linhaAtual))) {
            throw new RuntimeException('FILA_DESATUALIZADA: recalcule antes de confirmar.');
        }
        $stmtOff = $conn->prepare('UPDATE entrega_planejamento_fila_operacional SET ativo = 0 WHERE colaborador_id = ? AND codigo_etapa = ? AND ativo = 1');
        $stmtIn = $conn->prepare('INSERT INTO entrega_planejamento_fila_operacional (planejamento_id, versao_id, entrega_id, obra_id, codigo_etapa, referencia_fila, colaborador_id, posicao, fingerprint, motivo, confirmado_por_colaborador_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $etapas = array_values(array_unique(array_column($simulacao['ordem_proposta'], 'codigo_etapa')));
        foreach ($etapas as $codigoEtapa) {
            $stmtOff->bind_param('is', $colaboradorId, $codigoEtapa);
            if (!$stmtOff->execute()) {
                throw new RuntimeException($stmtOff->error);
            }
        }
        foreach ($simulacao['ordem_proposta'] as $pos => $item) {
            $p = null;
            foreach ($simulacao['fila_depois']['projecoes'] as $projecao) {
                if ((int) $projecao['entrega_id'] === (int) $item['entrega_id']) {
                    $p = $projecao;
                }
            }
            if (!$p) {
                $bloco = null;
                foreach ((array) ($simulacao['fila_atual'] ?? []) as $atual) {
                    if (($atual['fila_chave'] ?? '') === $item['referencia_fila']) {
                        $bloco = $atual;
                        break;
                    }
                }
                $p = ['planejamento_id' => 0, 'versao_vigente_id' => 0, 'entrega_id' => (int) $item['entrega_id'], 'obra_id' => (int) ($bloco['obra_id'] ?? 0)];
            }
            $posicao = $pos + 1;
            $fp = $simulacao['fingerprint_simulado'];
            $referencia = $item['referencia_fila'];
            $stmtIn->bind_param('iiiissiissi', $p['planejamento_id'], $p['versao_vigente_id'], $p['entrega_id'], $p['obra_id'], $item['codigo_etapa'], $referencia, $colaboradorId, $posicao, $fp, $motivo, $atorId);
            if (!$stmtIn->execute()) {
                throw new RuntimeException($stmtIn->error);
            }
        }
        $stmtOff->close();
        $stmtIn->close();
        foreach ($simulacao['fila_depois']['projecoes'] as $p) {
            flow_fila_confirmada_persistir_snapshot($conn, $p, flow_fila_confirmada_fingerprint_projecao([$p]), $atorId);
            $metadados = ['colaborador_id' => $colaboradorId, 'ordem' => $simulacao['ordem_proposta'], 'classificacao' => $simulacao['classificacao'], 'fingerprint_fila' => $simulacao['fingerprint_simulado'], 'fingerprint_projecao' => $simulacao['fingerprint_projecao_simulada']];
            flow_planejamento_registrar_evento($conn, (int) $p['planejamento_id'], (int) $p['entrega_id'], 'FILA_OPERACIONAL_REORDENADA', (int) $p['versao_vigente_id'], $atorId, null, $motivo ?: null, $metadados);
            flow_planejamento_registrar_evento($conn, (int) $p['planejamento_id'], (int) $p['entrega_id'], 'PROJECAO_OPERACIONAL_CONFIRMADA', (int) $p['versao_vigente_id'], $atorId, null, $motivo ?: null, $metadados);
        }
        $conn->commit();
        return $simulacao + ['confirmada' => true];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
