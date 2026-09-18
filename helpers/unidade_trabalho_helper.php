<?php

if (!defined('FLOW_UNIDADE_TIPO_MODELAGEM_COMPOSICAO')) {
    define('FLOW_UNIDADE_TIPO_MODELAGEM_COMPOSICAO', 'MODELAGEM_COMPOSICAO');
    define('FLOW_FUNCAO_CADERNO', 1);
    define('FLOW_FUNCAO_MODELAGEM', 2);
    define('FLOW_FUNCAO_COMPOSICAO', 3);
    define('FLOW_FUNCAO_FILTRO_ASSETS', 8);
    define('FLOW_WIP_LIMIT', 1);
    define('FLOW_WIP_ERROR_CODE', 'WIP_LIMIT_REACHED');
}

class FlowWipException extends DomainException
{
    private array $contexto;

    public function __construct(array $contexto = [])
    {
        parent::__construct('Você já possui trabalho iniciado aguardando sua ação. Conclua ou avance essas tarefas antes de iniciar uma nova.');
        $this->contexto = $contexto;
    }

    public function contexto(): array
    {
        return $this->contexto;
    }
}

function flow_wip_status_ativo(?string $status): bool
{
    return in_array(trim((string) $status), ['Em andamento', 'Ajuste'], true);
}

function flow_unidade_consumo_wip(array $members): int
{
    foreach ($members as $member) {
        $status = is_array($member) ? ($member['status'] ?? null) : $member;
        if (flow_wip_status_ativo($status)) {
            return 1;
        }
    }
    return 0;
}

function flow_wip_unidades_bloqueantes(array $units, ?string $candidateKey = null): array
{
    return array_values(array_filter(
        $units,
        static fn(array $unit): bool => $candidateKey === null || ($unit['key'] ?? null) !== $candidateKey
    ));
}

function flow_unidade_schema_disponivel(mysqli $conn): bool
{
    static $cache = [];
    $key = spl_object_id($conn);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $result = $conn->query("SHOW TABLES LIKE 'unidade_trabalho'");
    $cache[$key] = $result && $result->num_rows > 0;
    return $cache[$key];
}

/** Serializa todo novo início de um colaborador dentro da transação corrente. */
function flow_wip_bloquear_colaborador(mysqli $conn, int $colaboradorId): void
{
    if ($colaboradorId <= 0) {
        throw new DomainException('A tarefa precisa possuir um colaborador responsável para ser iniciada.');
    }
    $stmt = $conn->prepare('SELECT idcolaborador FROM colaborador WHERE idcolaborador = ? FOR UPDATE');
    $stmt->bind_param('i', $colaboradorId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        throw new DomainException('Colaborador responsável não encontrado.');
    }
}

function flow_unidade_mapa_explicito(mysqli $conn, array $funcaoImagemIds): array
{
    if (!$funcaoImagemIds || !flow_unidade_schema_disponivel($conn)) {
        return [];
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $funcaoImagemIds))));
    if (!$ids) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare(
        "SELECT ut.id unidade_id, ut.tipo, ut.imagem_id, ut.colaborador_id, uti.funcao_imagem_id, uti.ordem,
                fi.funcao_id, fi.status, fi.prazo, f.nome_funcao
           FROM unidade_trabalho ut
           JOIN unidade_trabalho_item uti ON uti.unidade_trabalho_id = ut.id
           JOIN funcao_imagem fi ON fi.idfuncao_imagem = uti.funcao_imagem_id
           JOIN funcao f ON f.idfuncao = fi.funcao_id
          WHERE ut.estado = 'ATIVA'
            AND ut.id IN (
                SELECT DISTINCT unidade_trabalho_id
                FROM unidade_trabalho_item
                WHERE funcao_imagem_id IN ($marks)
            )
          ORDER BY ut.id, uti.ordem, uti.funcao_imagem_id"
    );
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();
    $units = [];
    while ($row = $result->fetch_assoc()) {
        $unitId = (int) $row['unidade_id'];
        if (!isset($units[$unitId])) {
            $units[$unitId] = [
                'id' => $unitId,
                'tipo' => (string) $row['tipo'],
                'imagem_id' => (int) $row['imagem_id'],
                'colaborador_id' => (int) $row['colaborador_id'],
                'membros' => [],
            ];
        }
        $units[$unitId]['membros'][] = [
            'idfuncao_imagem' => (int) $row['funcao_imagem_id'],
            'funcao_id' => (int) $row['funcao_id'],
            'nome_funcao' => (string) $row['nome_funcao'],
            'status' => (string) $row['status'],
            'prazo' => $row['prazo'],
            'ordem' => (int) $row['ordem'],
        ];
    }
    $stmt->close();
    return $units;
}

function flow_unidade_chave_candidata_funcao(mysqli $conn, array $task): string
{
    $id = (int) ($task['idfuncao_imagem'] ?? 0);
    if ($id <= 0) {
        return '';
    }
    $units = flow_unidade_mapa_explicito($conn, [$id]);
    if ($units) {
        return 'UT:' . (int) array_key_first($units);
    }

    // Compatibilidade estritamente limitada ao par operacional legado já
    // apresentado como um card. Não cria associação positiva para esse par.
    $funcaoId = (int) ($task['funcao_id'] ?? 0);
    if (in_array($funcaoId, [FLOW_FUNCAO_CADERNO, FLOW_FUNCAO_FILTRO_ASSETS], true)) {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) total
               FROM funcao_imagem fi
              WHERE fi.imagem_id = ?
                AND fi.funcao_id IN (1, 8)
                AND fi.colaborador_id = ?
                AND NOT EXISTS (
                    SELECT 1 FROM funcao_par_separado fps
                    WHERE fps.imagem_id = fi.imagem_id AND fps.par_tipo = 'caderno_filtro'
                )"
        );
        $imagemId = (int) ($task['imagem_id'] ?? 0);
        $colaboradorId = (int) ($task['colaborador_id'] ?? 0);
        $stmt->bind_param('ii', $imagemId, $colaboradorId);
        $stmt->execute();
        $total = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $stmt->close();
        if ($total === 2) {
            return 'LEGACY_CADERNO_FILTRO:' . $imagemId . ':' . $colaboradorId;
        }
    }
    return 'FUNCAO_IMAGEM:' . $id;
}

/** Retorna unidades, não quantidade bruta de registros ativos. */
function flow_wip_unidades_ativas(mysqli $conn, int $colaboradorId): array
{
    $stmt = $conn->prepare(
        "SELECT fi.idfuncao_imagem, fi.imagem_id, fi.funcao_id, fi.colaborador_id, fi.status,
                f.nome_funcao, ico.imagem_nome, o.nomenclatura
           FROM funcao_imagem fi
          JOIN funcao f ON f.idfuncao = fi.funcao_id
          JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
          JOIN obra o ON o.idobra = ico.obra_id
          WHERE fi.colaborador_id = ? AND fi.status IN ('Em andamento', 'Ajuste')
            -- Finalização/Alteração deixam de consumir WIP enquanto o
            -- render correspondente está sendo processado no Deadline.
            AND NOT (
                fi.funcao_id IN (4, 6)
                AND EXISTS (
                    SELECT 1
                    FROM render_alta ra
                    WHERE ra.imagem_id = fi.imagem_id
                      AND ra.status = 'Em andamento'
                )
            )"
    );
    $stmt->bind_param('i', $colaboradorId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $units = [];
    $explicit = flow_unidade_mapa_explicito($conn, array_column($rows, 'idfuncao_imagem'));
    $byTask = [];
    foreach ($explicit as $unit) {
        foreach ($unit['membros'] as $member) {
            $byTask[(int) $member['idfuncao_imagem']] = $unit;
        }
    }
    foreach ($rows as $row) {
        $taskId = (int) $row['idfuncao_imagem'];
        if (isset($byTask[$taskId])) {
            $unit = $byTask[$taskId];
            $key = 'UT:' . (int) $unit['id'];
            if (!isset($units[$key])) {
                $units[$key] = [
                    'key' => $key,
                    'type' => $unit['tipo'],
                    'unit_id' => (int) $unit['id'],
                    'image_id' => (int) $unit['imagem_id'],
                    'label' => 'Modelagem + Composição',
                    'task_ids' => array_values(array_map(static fn(array $m): int => (int) $m['idfuncao_imagem'], $unit['membros'])),
                ];
            }
            continue;
        }
        $key = flow_unidade_chave_candidata_funcao($conn, $row);
        if (!isset($units[$key])) {
            $units[$key] = [
                'key' => $key,
                'type' => str_starts_with($key, 'LEGACY_CADERNO_FILTRO:') ? 'CADERNO_FILTRO_LEGADO' : 'FUNCAO_IMAGEM',
                'unit_id' => null,
                'image_id' => (int) $row['imagem_id'],
                'label' => str_starts_with($key, 'LEGACY_CADERNO_FILTRO:') ? 'Caderno + Filtro de Assets' : (string) $row['nome_funcao'],
                'task_ids' => [],
            ];
        }
        $units[$key]['task_ids'][] = $taskId;
    }

    // Animação e cards manuais também são trabalho puxado no mesmo Kanban.
    foreach ([
        ['table' => 'funcao_animacao', 'id' => 'id', 'prefix' => 'ANIMACAO'],
        ['table' => 'tarefas', 'id' => 'id', 'prefix' => 'TAREFA'],
    ] as $source) {
        try {
            $sql = "SELECT {$source['id']} id FROM {$source['table']} WHERE colaborador_id = ? AND status IN ('Em andamento', 'Ajuste')";
            $other = $conn->prepare($sql);
            $other->bind_param('i', $colaboradorId);
            $other->execute();
            $result = $other->get_result();
            while ($row = $result->fetch_assoc()) {
                $key = $source['prefix'] . ':' . (int) $row['id'];
                $units[$key] = ['key' => $key, 'type' => $source['prefix'], 'unit_id' => null, 'image_id' => null, 'label' => $source['prefix'] === 'ANIMACAO' ? 'Animação' : 'Tarefa', 'task_ids' => [(int) $row['id']]];
            }
            $other->close();
        } catch (Throwable $ignored) {
            // Instalações antigas podem não ter uma das fontes auxiliares.
        }
    }
    return array_values($units);
}

function flow_wip_resumo(mysqli $conn, int $colaboradorId, ?string $candidateKey = null): array
{
    $units = flow_wip_unidades_ativas($conn, $colaboradorId);
    $blocking = flow_wip_unidades_bloqueantes($units, $candidateKey);
    return [
        'limit' => FLOW_WIP_LIMIT,
        'active_count' => count($units),
        'blocking_count' => count($blocking),
        'can_start_new' => count($blocking) < FLOW_WIP_LIMIT,
        'active_units' => $units,
        'blocking_units' => $blocking,
    ];
}

/** Contagem em lote para superfícies gerenciais que exibem várias pessoas. */
function flow_wip_contagens_colaboradores(mysqli $conn, array $colaboradorIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $colaboradorIds))));
    $counts = array_fill_keys($ids, 0);
    if (!$ids) {
        return $counts;
    }

    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare(
        "SELECT idfuncao_imagem, imagem_id, funcao_id, colaborador_id, status
           FROM funcao_imagem
          WHERE colaborador_id IN ($marks)
            AND status IN ('Em andamento', 'Ajuste')
            AND NOT (
                funcao_id IN (4, 6)
                AND EXISTS (
                    SELECT 1
                    FROM render_alta ra
                    WHERE ra.imagem_id = funcao_imagem.imagem_id
                      AND ra.status = 'Em andamento'
                )
            )"
    );
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $explicit = flow_unidade_mapa_explicito($conn, array_column($rows, 'idfuncao_imagem'));
    $byTask = [];
    foreach ($explicit as $unit) {
        foreach ($unit['membros'] as $member) {
            $byTask[(int) $member['idfuncao_imagem']] = (int) $unit['id'];
        }
    }

    $unitKeys = array_fill_keys($ids, []);
    foreach ($rows as $row) {
        $collaboratorId = (int) $row['colaborador_id'];
        $taskId = (int) $row['idfuncao_imagem'];
        $key = isset($byTask[$taskId])
            ? 'UT:' . $byTask[$taskId]
            : flow_unidade_chave_candidata_funcao($conn, $row);
        $unitKeys[$collaboratorId][$key] = true;
    }

    foreach ([
        ['table' => 'funcao_animacao', 'prefix' => 'ANIMACAO'],
        ['table' => 'tarefas', 'prefix' => 'TAREFA'],
    ] as $source) {
        try {
            $other = $conn->prepare(
                "SELECT id, colaborador_id FROM {$source['table']}
                  WHERE colaborador_id IN ($marks)
                    AND status IN ('Em andamento', 'Ajuste')"
            );
            $other->bind_param($types, ...$ids);
            $other->execute();
            $result = $other->get_result();
            while ($row = $result->fetch_assoc()) {
                $collaboratorId = (int) $row['colaborador_id'];
                $unitKeys[$collaboratorId][$source['prefix'] . ':' . (int) $row['id']] = true;
            }
            $other->close();
        } catch (Throwable $ignored) {
            // Instalações antigas podem não possuir uma das fontes auxiliares.
        }
    }

    foreach ($unitKeys as $collaboratorId => $keys) {
        $counts[$collaboratorId] = count($keys);
    }
    return $counts;
}

function flow_wip_assert_novo_inicio(mysqli $conn, int $colaboradorId, ?array $candidateTask = null): array
{
    flow_wip_bloquear_colaborador($conn, $colaboradorId);
    $candidateKey = $candidateTask ? flow_unidade_chave_candidata_funcao($conn, $candidateTask) : null;
    $summary = flow_wip_resumo($conn, $colaboradorId, $candidateKey);
    if (!$summary['can_start_new']) {
        throw new FlowWipException($summary);
    }
    return $summary;
}

function flow_wip_exception_payload(FlowWipException $error): array
{
    return [
        'success' => false,
        'code' => FLOW_WIP_ERROR_CODE,
        'message' => $error->getMessage(),
        'wip' => $error->contexto(),
    ];
}

function flow_unidade_status_operacional(array $members): string
{
    $priority = ['Ajuste', 'Em andamento', 'Em aprovação', 'Aguardando Direção', 'Não iniciado', 'HOLD', 'Aprovado com ajustes', 'Aprovado', 'Finalizado'];
    foreach ($priority as $status) {
        foreach ($members as $member) {
            if (($member['status'] ?? '') === $status) {
                return $status;
            }
        }
    }
    return (string) ($members[0]['status'] ?? 'Não iniciado');
}

/**
 * A Composição é a representante operacional da unidade Modelagem +
 * Composição sempre que ela estiver no mesmo estado operacional. Isso evita
 * que uma prévia, aprovação ou HOLD volte a apontar para a Modelagem.
 */
function flow_unidade_membro_acionavel(array $unit, array $members, string $operationalStatus): ?array
{
    $sameStatus = array_values(array_filter(
        $members,
        static fn(array $member): bool => (string) ($member['status'] ?? '') === $operationalStatus
    ));
    if (($unit['tipo'] ?? '') === FLOW_UNIDADE_TIPO_MODELAGEM_COMPOSICAO) {
        foreach ($sameStatus as $member) {
            if ((int) ($member['funcao_id'] ?? 0) === FLOW_FUNCAO_COMPOSICAO) {
                return $member;
            }
        }
    }
    return $sameStatus[0] ?? $members[0] ?? null;
}

/**
 * Obtém e bloqueia, quando solicitado, a unidade explícita de uma tarefa.
 * Somente Modelagem + Composição possui a regra de representante principal.
 */
function flow_unidade_modelagem_composicao_da_tarefa(mysqli $conn, int $funcaoImagemId, bool $forUpdate = false): ?array
{
    if ($funcaoImagemId <= 0 || !flow_unidade_schema_disponivel($conn)) {
        return null;
    }
    $tipo = FLOW_UNIDADE_TIPO_MODELAGEM_COMPOSICAO;
    $sql = "SELECT ut.id unidade_id, ut.tipo, ut.imagem_id, ut.colaborador_id,
                   uti.funcao_imagem_id, uti.ordem, fi.funcao_id, fi.status, fi.prazo, f.nome_funcao
              FROM unidade_trabalho ut
              JOIN unidade_trabalho_item uti ON uti.unidade_trabalho_id = ut.id
              JOIN funcao_imagem fi ON fi.idfuncao_imagem = uti.funcao_imagem_id
              JOIN funcao f ON f.idfuncao = fi.funcao_id
             WHERE ut.estado = 'ATIVA'
               AND ut.tipo = ?
               AND ut.id IN (
                   SELECT unidade_trabalho_id
                     FROM unidade_trabalho_item
                    WHERE funcao_imagem_id = ?
               )
             ORDER BY uti.ordem, uti.funcao_imagem_id" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $tipo, $funcaoImagemId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if (count($rows) !== 2) {
        return null;
    }
    $unit = [
        'id' => (int) $rows[0]['unidade_id'],
        'tipo' => (string) $rows[0]['tipo'],
        'imagem_id' => (int) $rows[0]['imagem_id'],
        'colaborador_id' => (int) $rows[0]['colaborador_id'],
        'membros' => [],
    ];
    foreach ($rows as $row) {
        $unit['membros'][] = [
            'idfuncao_imagem' => (int) $row['funcao_imagem_id'],
            'funcao_id' => (int) $row['funcao_id'],
            'nome_funcao' => (string) $row['nome_funcao'],
            'status' => (string) $row['status'],
            'prazo' => $row['prazo'],
            'ordem' => (int) $row['ordem'],
        ];
    }
    $modelagem = array_values(array_filter($unit['membros'], static fn(array $m): bool => (int) $m['funcao_id'] === FLOW_FUNCAO_MODELAGEM));
    $composicao = array_values(array_filter($unit['membros'], static fn(array $m): bool => (int) $m['funcao_id'] === FLOW_FUNCAO_COMPOSICAO));
    if (count($modelagem) !== 1 || count($composicao) !== 1) {
        return null;
    }
    $unit['modelagem'] = $modelagem[0];
    $unit['composicao'] = $composicao[0];
    return $unit;
}

/**
 * Promove a Composição a tarefa principal nos marcos de entrega. A Modelagem
 * fica finalizada, e as duas tarefas continuam com seus registros próprios.
 * Esta função deve ser chamada dentro de uma transação já aberta.
 */
function flow_unidade_promover_composicao_principal(
    mysqli $conn,
    int $funcaoImagemId,
    string $statusDestino,
    ?int $actorColaboradorId = null,
    ?int $actorUsuarioId = null,
    string $origem = 'operacao_unidade'
): array {
    if (!in_array($statusDestino, ['Em aprovação', 'HOLD'], true)) {
        throw new InvalidArgumentException('Status de promoção da unidade inválido.');
    }
    $unit = flow_unidade_modelagem_composicao_da_tarefa($conn, $funcaoImagemId, true);
    if (!$unit) {
        return ['aplicada' => false, 'tarefa_principal_id' => $funcaoImagemId, 'unit' => null];
    }

    $modelagem = $unit['modelagem'];
    $composicao = $unit['composicao'];
    $modelagemId = (int) $modelagem['idfuncao_imagem'];
    $composicaoId = (int) $composicao['idfuncao_imagem'];
    if ($statusDestino === 'Em aprovação' && (string) $composicao['status'] === 'HOLD') {
        throw new DomainException('A Composição está em HOLD e não pode receber uma prévia até ser retomada.');
    }

    if (in_array((string) $modelagem['status'], ['Em andamento', 'Em aprovação', 'Ajuste'], true)) {
        $finalizar = $conn->prepare("UPDATE funcao_imagem SET status = 'Finalizado' WHERE idfuncao_imagem = ?");
        $finalizar->bind_param('i', $modelagemId);
        $finalizar->execute();
        $finalizar->close();
    }
    if ((string) $composicao['status'] !== $statusDestino) {
        $promover = $conn->prepare('UPDATE funcao_imagem SET status = ? WHERE idfuncao_imagem = ?');
        $promover->bind_param('si', $statusDestino, $composicaoId);
        $promover->execute();
        $promover->close();
    }

    // Prévia é um artefato da entrega da Composição. Este UPDATE também
    // corrige, sem apagar histórico, uploads antigos feitos pela Modelagem
    // antes da regra de representante principal existir.
    if ($statusDestino === 'Em aprovação' && $modelagemId !== $composicaoId) {
        $migrarMidias = $conn->prepare(
            'UPDATE historico_aprovacoes_imagens SET funcao_imagem_id = ? WHERE funcao_imagem_id = ?'
        );
        $migrarMidias->bind_param('ii', $composicaoId, $modelagemId);
        $migrarMidias->execute();
        $migrarMidias->close();
        $migrarAprovacoes = $conn->prepare(
            "UPDATE historico_aprovacoes
                SET funcao_imagem_id = ?
              WHERE funcao_imagem_id = ?
                AND status_novo = 'Em aprovação'"
        );
        $migrarAprovacoes->bind_param('ii', $composicaoId, $modelagemId);
        $migrarAprovacoes->execute();
        $migrarAprovacoes->close();
    }

    $detalhes = json_encode([
        'origem' => $origem,
        'tarefa_solicitada_id' => $funcaoImagemId,
        'modelagem_id' => $modelagemId,
        'composicao_id' => $composicaoId,
        'status_destino' => $statusDestino,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $evento = $statusDestino === 'HOLD' ? 'HOLD_COMPOSICAO_PRINCIPAL' : 'PREVIA_COMPOSICAO_PRINCIPAL';
    $event = $conn->prepare('INSERT INTO unidade_trabalho_evento (unidade_trabalho_id, evento, ator_colaborador_id, ator_usuario_id, detalhes) VALUES (?, ?, ?, ?, ?)');
    $unitId = (int) $unit['id'];
    $event->bind_param('isiis', $unitId, $evento, $actorColaboradorId, $actorUsuarioId, $detalhes);
    $event->execute();
    $event->close();

    return ['aplicada' => true, 'tarefa_principal_id' => $composicaoId, 'unit' => $unit];
}

/** Aplica uma projeção explícita de unidade aos payloads de tarefa do Kanban. */
function flow_unidade_agrupar_funcoes_payload(mysqli $conn, array $items): array
{
    $ids = array_values(array_filter(array_map(static fn(array $item): int => (int) ($item['idfuncao_imagem'] ?? 0), $items)));
    $units = flow_unidade_mapa_explicito($conn, $ids);
    if (!$units) {
        return $items;
    }
    $indexById = [];
    foreach ($items as $index => $item) {
        $indexById[(int) ($item['idfuncao_imagem'] ?? 0)] = $index;
    }
    $suppressed = [];
    foreach ($units as $unit) {
        $availableMembers = array_values(array_filter($unit['membros'], static fn(array $member): bool => isset($indexById[(int) $member['idfuncao_imagem']])));
        if (count($availableMembers) < 2) {
            continue;
        }
        $operationalStatus = flow_unidade_status_operacional($availableMembers);
        $representative = flow_unidade_membro_acionavel($unit, $availableMembers, $operationalStatus);
        $repIndex = $indexById[(int) $representative['idfuncao_imagem']];
        $items[$repIndex]['status'] = $operationalStatus;
        $items[$repIndex]['nome_funcao'] = 'Modelagem + Composição';
        $items[$repIndex]['work_unit'] = array_merge($unit, [
            'label' => 'Modelagem + Composição',
            'status_operacional' => $operationalStatus,
            'membro_acionavel_id' => (int) $representative['idfuncao_imagem'],
        ]);
        foreach ($availableMembers as $member) {
            $idx = $indexById[(int) $member['idfuncao_imagem']];
            if ($idx !== $repIndex) {
                $suppressed[$idx] = true;
            }
        }
    }
    foreach (array_keys($suppressed) as $idx) {
        unset($items[$idx]);
    }
    return array_values($items);
}

function flow_unidade_por_imagens(mysqli $conn, array $imagemIds): array
{
    if (!$imagemIds || !flow_unidade_schema_disponivel($conn)) {
        return [];
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $imagemIds))));
    if (!$ids) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare(
        "SELECT uti.funcao_imagem_id
           FROM unidade_trabalho ut
           JOIN unidade_trabalho_item uti ON uti.unidade_trabalho_id = ut.id
          WHERE ut.estado = 'ATIVA' AND ut.imagem_id IN ($marks)"
    );
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $taskIds = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'funcao_imagem_id'));
    $stmt->close();
    $byImage = [];
    foreach (flow_unidade_mapa_explicito($conn, $taskIds) as $unit) {
        $unit['label'] = $unit['tipo'] === FLOW_UNIDADE_TIPO_MODELAGEM_COMPOSICAO
            ? 'Modelagem + Composição'
            : $unit['tipo'];
        $unit['status_operacional'] = flow_unidade_status_operacional($unit['membros']);
        $byImage[(int) $unit['imagem_id']][] = $unit;
    }
    return $byImage;
}
