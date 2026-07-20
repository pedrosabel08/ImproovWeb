<?php

require_once __DIR__ . '/pre_alt_helpers.php';

function pre_alt_planejamento_ensure_schema(mysqli $conn): void
{
    pre_alt_ensure_schema($conn);

    $conn->query("CREATE TABLE IF NOT EXISTS pre_alt_diagramas (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        pre_alt_lote_id INT UNSIGNED NOT NULL,
        nome VARCHAR(160) NOT NULL,
        status ENUM('RASCUNHO','VALIDADO','PUBLICADO','ARQUIVADO') NOT NULL DEFAULT 'RASCUNHO',
        created_by INT UNSIGNED NULL,
        updated_by INT UNSIGNED NULL,
        published_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY ux_pre_alt_diagramas_lote (pre_alt_lote_id),
        KEY idx_pre_alt_diagramas_status (status, updated_at),
        CONSTRAINT fk_pre_alt_diagramas_lote FOREIGN KEY (pre_alt_lote_id) REFERENCES pre_alt_lote (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS pre_alt_diagrama_grupos (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        diagrama_id INT UNSIGNED NOT NULL,
        nome VARCHAR(120) NOT NULL,
        responsavel_id INT UNSIGNED NULL,
        ordem INT UNSIGNED NOT NULL DEFAULT 0,
        pos_x DECIMAL(10,2) NULL,
        pos_y DECIMAL(10,2) NULL,
        width DECIMAL(10,2) NULL,
        height DECIMAL(10,2) NULL,
        visual_json LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_pre_alt_diag_grupos_diagrama (diagrama_id, ordem),
        CONSTRAINT fk_pre_alt_diag_grupos_diagrama FOREIGN KEY (diagrama_id) REFERENCES pre_alt_diagramas (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS pre_alt_diagrama_itens (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        diagrama_id INT UNSIGNED NOT NULL,
        pre_alt_item_id INT UNSIGNED NOT NULL,
        grupo_id INT UNSIGNED NULL,
        responsavel_id INT UNSIGNED NULL,
        ordem INT UNSIGNED NOT NULL DEFAULT 0,
        pos_x DECIMAL(10,2) NULL,
        pos_y DECIMAL(10,2) NULL,
        width DECIMAL(10,2) NULL,
        height DECIMAL(10,2) NULL,
        visual_json LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY ux_pre_alt_diag_item (diagrama_id, pre_alt_item_id),
        KEY idx_pre_alt_diag_itens_grupo (grupo_id),
        CONSTRAINT fk_pre_alt_diag_itens_diagrama FOREIGN KEY (diagrama_id) REFERENCES pre_alt_diagramas (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_pre_alt_diag_itens_pre_alt FOREIGN KEY (pre_alt_item_id) REFERENCES pre_alt_itens (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_pre_alt_diag_itens_grupo FOREIGN KEY (grupo_id) REFERENCES pre_alt_diagrama_grupos (id) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS pre_alt_diagrama_gates (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        diagrama_id INT UNSIGNED NOT NULL,
        titulo VARCHAR(160) NOT NULL,
        gate_tipo ENUM('APROVACAO','FINALIZACAO','MANUAL') NOT NULL DEFAULT 'APROVACAO',
        pos_x DECIMAL(10,2) NULL,
        pos_y DECIMAL(10,2) NULL,
        width DECIMAL(10,2) NULL,
        height DECIMAL(10,2) NULL,
        visual_json LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_pre_alt_diag_gates_diagrama (diagrama_id),
        CONSTRAINT fk_pre_alt_diag_gates_diagrama FOREIGN KEY (diagrama_id) REFERENCES pre_alt_diagramas (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS pre_alt_diagrama_dependencias (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        diagrama_id INT UNSIGNED NOT NULL,
        origem_tipo ENUM('GRUPO','ITEM','GATE') NOT NULL,
        origem_id INT UNSIGNED NOT NULL,
        destino_tipo ENUM('GRUPO','ITEM','GATE') NOT NULL,
        destino_id INT UNSIGNED NOT NULL,
        condicao ENUM('APROVADA','FINALIZADA') NOT NULL DEFAULT 'APROVADA',
        agregacao ENUM('ALL') NOT NULL DEFAULT 'ALL',
        observacao TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY ux_pre_alt_diag_dep_logica (diagrama_id, origem_tipo, origem_id, destino_tipo, destino_id, condicao, agregacao),
        KEY idx_pre_alt_diag_dep_origem (origem_tipo, origem_id),
        KEY idx_pre_alt_diag_dep_destino (destino_tipo, destino_id),
        CONSTRAINT fk_pre_alt_diag_deps_diagrama FOREIGN KEY (diagrama_id) REFERENCES pre_alt_diagramas (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function pre_alt_planejamento_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function pre_alt_planejamento_require_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        pre_alt_planejamento_json_response(['success' => false, 'error' => 'Nao autenticado.'], 401);
    }
}

function pre_alt_planejamento_actor_id(): ?int
{
    return isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null;
}

function pre_alt_planejamento_decode_json(?string $value): array
{
    if ($value === null || trim($value) === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function pre_alt_planejamento_encode_json($value): ?string
{
    if ($value === null || $value === [] || $value === '') {
        return null;
    }
    return json_encode($value, JSON_UNESCAPED_UNICODE);
}

function pre_alt_planejamento_ref(string $type, $id): string
{
    return strtolower($type) . '-' . (string) $id;
}

function pre_alt_planejamento_float_or_null($value): ?float
{
    return is_numeric($value) ? (float) $value : null;
}

function pre_alt_planejamento_int_or_null($value): ?int
{
    return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
}

function pre_alt_planejamento_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
    $value = trim((string) $value, '-');
    return $value !== '' ? $value : 'grupo';
}

function pre_alt_planejamento_grupo_sugerido(array $item): string
{
    $tipo = trim((string) ($item['tipo_imagem'] ?? ''));
    if ($tipo !== '') {
        return $tipo;
    }
    $nome = trim((string) ($item['nome'] ?? ''));
    if (preg_match('/^[0-9]+\\.[^_]+_([^_\\s]+)/', $nome, $m)) {
        return strtoupper($m[1]);
    }
    return 'Sem grupo';
}

function pre_alt_planejamento_fetch_lote(mysqli $conn, int $loteId): ?array
{
    $stmt = $conn->prepare(
        "SELECT
            l.id AS lote_id,
            l.obra_id,
            l.status_id,
            l.status AS lote_status,
            l.prioridade,
            l.prazo,
            COALESCE(l.responsavel_id, l.created_by) AS responsavel_id,
            l.created_by,
            l.created_at,
            l.updated_at,
            o.nomenclatura,
            cli.nome_cliente,
            si.nome_status,
            c.nome_colaborador AS responsavel_nome
         FROM pre_alt_lote l
         JOIN obra o ON o.idobra = l.obra_id
         LEFT JOIN cliente cli ON cli.idcliente = o.cliente
         LEFT JOIN status_imagem si ON si.idstatus = l.status_id
         LEFT JOIN colaborador c ON c.idcolaborador = COALESCE(l.responsavel_id, l.created_by)
         WHERE l.id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $loteId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    foreach (['lote_id', 'obra_id', 'status_id', 'responsavel_id', 'created_by'] as $key) {
        $row[$key] = isset($row[$key]) ? (int) $row[$key] : null;
    }
    return $row;
}

function pre_alt_planejamento_fetch_itens(mysqli $conn, int $loteId): array
{
    $stmt = $conn->prepare(
        "SELECT
            pai.id AS pre_alt_item_id,
            pai.imagem_id,
            pai.resultado,
            pai.nivel_complexidade,
            pai.tipo_alteracao,
            pai.acao,
            pai.quantidade_comentarios,
            pai.responsavel_id AS item_responsavel_id,
            resp.nome_colaborador AS item_responsavel_nome,
            ico.imagem_nome AS nome,
            ico.tipo_imagem,
            ico.subtipo_imagem,
            ico.status_id AS imagem_status_id,
            ss.nome_substatus,
                (
                    SELECT hai.imagem
                    FROM historico_aprovacoes_imagens hai
                    JOIN funcao_imagem fi2 ON fi2.idfuncao_imagem = hai.funcao_imagem_id
                    WHERE fi2.imagem_id = pai.imagem_id
                      AND hai.imagem IS NOT NULL
                      AND hai.imagem <> ''
                    ORDER BY hai.data_envio DESC, hai.id DESC
                    LIMIT 1
                
            ) AS preview_path
         FROM pre_alt_itens pai
         JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = pai.imagem_id
         LEFT JOIN substatus_imagem ss ON ss.id = ico.substatus_id
         LEFT JOIN colaborador resp ON resp.idcolaborador = pai.responsavel_id
         WHERE pai.pre_alt_lote_id = ?
           AND pai.resultado = 'ALTERACAO'
           AND NOT EXISTS (
                SELECT 1
                FROM pre_alt_liberacao_itens pli
                WHERE pli.pre_alt_item_id = pai.id
           )
         ORDER BY ico.imagem_nome ASC, pai.id ASC"
    );
    if (!$stmt) {
        throw new RuntimeException('Nao foi possivel consultar as imagens do lote.');
    }
    $stmt->bind_param('i', $loteId);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $row['pre_alt_item_id'] = (int) $row['pre_alt_item_id'];
        $row['imagem_id'] = (int) $row['imagem_id'];
        $row['nivel_complexidade'] = isset($row['nivel_complexidade']) ? (int) $row['nivel_complexidade'] : null;
        $row['quantidade_comentarios'] = isset($row['quantidade_comentarios']) ? (int) $row['quantidade_comentarios'] : null;
        $row['item_responsavel_id'] = isset($row['item_responsavel_id']) ? (int) $row['item_responsavel_id'] : null;
        $row['imagem_status_id'] = isset($row['imagem_status_id']) ? (int) $row['imagem_status_id'] : null;
        $row['thumb_url'] = $row['preview_path']
            ? '../thumb.php?path=' . rawurlencode((string) $row['preview_path']) . '&w=160&q=70'
            : null;
        $items[] = $row;
    }
    $stmt->close();
    return $items;
}

function pre_alt_planejamento_fetch_colaboradores(mysqli $conn): array
{
    $res = $conn->query("SELECT idcolaborador, nome_colaborador FROM colaborador ORDER BY nome_colaborador ASC");
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = [
                'idcolaborador' => (int) $row['idcolaborador'],
                'nome_colaborador' => (string) $row['nome_colaborador'],
            ];
        }
    }
    return $rows;
}

function pre_alt_planejamento_fetch_diagrama_row(mysqli $conn, int $loteId): ?array
{
    $stmt = $conn->prepare('SELECT * FROM pre_alt_diagramas WHERE pre_alt_lote_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $loteId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function pre_alt_planejamento_build_default(array $lote, array $items): array
{
    $groups = [];
    $groupRefs = [];
    foreach ($items as $idx => $item) {
        $groupName = pre_alt_planejamento_grupo_sugerido($item);
        $slug = pre_alt_planejamento_slug($groupName);
        if (!isset($groupRefs[$slug])) {
            $ref = 'group-new-' . $slug;
            $groupRefs[$slug] = $ref;
            $groups[] = [
                'id' => null,
                'ref' => $ref,
                'name' => $groupName,
                'responsavel_id' => $lote['responsavel_id'] ?? null,
                'responsavel_nome' => $lote['responsavel_nome'] ?? null,
                'order' => count($groups) + 1,
                'x' => 80 + (count($groups) * 280),
                'y' => 80,
                'width' => 240,
                'height' => 180,
                'visual' => [],
            ];
        }
    }

    $defaultItems = [];
    foreach ($items as $idx => $item) {
        $groupName = pre_alt_planejamento_grupo_sugerido($item);
        $groupRef = $groupRefs[pre_alt_planejamento_slug($groupName)] ?? null;
        $defaultItems[] = [
            'id' => null,
            'ref' => pre_alt_planejamento_ref('item', $item['pre_alt_item_id']),
            'pre_alt_item_id' => $item['pre_alt_item_id'],
            'group_ref' => $groupRef,
            'group_id' => null,
            'responsavel_id' => $item['item_responsavel_id'] ?? null,
            'responsavel_nome' => $item['item_responsavel_nome'] ?? null,
            'order' => $idx + 1,
            'x' => 120 + (($idx % 4) * 220),
            'y' => 180 + (floor($idx / 4) * 110),
            'width' => 180,
            'height' => 76,
            'visual' => [],
        ];
    }

    return [
        'diagram' => [
            'id' => null,
            'lote_id' => $lote['lote_id'],
            'name' => 'Planejamento - ' . ($lote['nomenclatura'] ?? ('Lote ' . $lote['lote_id'])),
            'status' => 'RASCUNHO',
            'published_at' => null,
            'created_at' => null,
            'updated_at' => null,
        ],
        'groups' => $groups,
        'items' => $defaultItems,
        'gates' => [],
        'dependencies' => [],
    ];
}

function pre_alt_planejamento_fetch_graph(mysqli $conn, int $loteId): array
{
    pre_alt_planejamento_ensure_schema($conn);
    $lote = pre_alt_planejamento_fetch_lote($conn, $loteId);
    if (!$lote) {
        throw new RuntimeException('Lote nao encontrado.');
    }

    $sourceItems = pre_alt_planejamento_fetch_itens($conn, $loteId);
    $sourceByPreAltId = [];
    foreach ($sourceItems as $item) {
        $sourceByPreAltId[(int) $item['pre_alt_item_id']] = $item;
    }

    $diagramRow = pre_alt_planejamento_fetch_diagrama_row($conn, $loteId);
    if (!$diagramRow) {
        $graph = pre_alt_planejamento_build_default($lote, $sourceItems);
        return [
            'lote' => $lote,
            'items_source' => $sourceItems,
            'colaboradores' => pre_alt_planejamento_fetch_colaboradores($conn),
        ] + $graph;
    }

    $diagramaId = (int) $diagramRow['id'];
    $diagram = [
        'id' => $diagramaId,
        'lote_id' => (int) $diagramRow['pre_alt_lote_id'],
        'name' => (string) $diagramRow['nome'],
        'status' => (string) $diagramRow['status'],
        'published_at' => $diagramRow['published_at'],
        'created_at' => $diagramRow['created_at'],
        'updated_at' => $diagramRow['updated_at'],
    ];

    $groups = [];
    $groupById = [];
    $stmt = $conn->prepare('SELECT * FROM pre_alt_diagrama_grupos WHERE diagrama_id = ? ORDER BY ordem ASC, id ASC');
    $stmt->bind_param('i', $diagramaId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $id = (int) $row['id'];
        $group = [
            'id' => $id,
            'ref' => pre_alt_planejamento_ref('group', $id),
            'name' => (string) $row['nome'],
            'responsavel_id' => isset($row['responsavel_id']) ? (int) $row['responsavel_id'] : null,
            'order' => (int) $row['ordem'],
            'x' => pre_alt_planejamento_float_or_null($row['pos_x']),
            'y' => pre_alt_planejamento_float_or_null($row['pos_y']),
            'width' => pre_alt_planejamento_float_or_null($row['width']),
            'height' => pre_alt_planejamento_float_or_null($row['height']),
            'visual' => pre_alt_planejamento_decode_json($row['visual_json'] ?? null),
        ];
        $groups[] = $group;
        $groupById[$id] = $group;
    }
    $stmt->close();

    $diagramItems = [];
    $itemById = [];
    $seenPreAlt = [];
    $stmt = $conn->prepare(
        'SELECT di.* FROM pre_alt_diagrama_itens di WHERE di.diagrama_id = ? ORDER BY di.ordem ASC, di.id ASC'
    );
    $stmt->bind_param('i', $diagramaId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $preAltItemId = (int) $row['pre_alt_item_id'];
        if (!isset($sourceByPreAltId[$preAltItemId])) {
            continue;
        }
        $id = (int) $row['id'];
        $groupId = isset($row['grupo_id']) ? (int) $row['grupo_id'] : null;
        $item = [
            'id' => $id,
            'ref' => pre_alt_planejamento_ref('item', $preAltItemId),
            'pre_alt_item_id' => $preAltItemId,
            'group_id' => $groupId,
            'group_ref' => $groupId ? pre_alt_planejamento_ref('group', $groupId) : null,
            'responsavel_id' => isset($row['responsavel_id']) ? (int) $row['responsavel_id'] : null,
            'order' => (int) $row['ordem'],
            'x' => pre_alt_planejamento_float_or_null($row['pos_x']),
            'y' => pre_alt_planejamento_float_or_null($row['pos_y']),
            'width' => pre_alt_planejamento_float_or_null($row['width']),
            'height' => pre_alt_planejamento_float_or_null($row['height']),
            'visual' => pre_alt_planejamento_decode_json($row['visual_json'] ?? null),
        ];
        $diagramItems[] = $item;
        $itemById[$id] = $item;
        $seenPreAlt[$preAltItemId] = true;
    }
    $stmt->close();

    foreach ($sourceItems as $idx => $sourceItem) {
        $preAltItemId = (int) $sourceItem['pre_alt_item_id'];
        if (isset($seenPreAlt[$preAltItemId])) {
            continue;
        }
        $diagramItems[] = [
            'id' => null,
            'ref' => pre_alt_planejamento_ref('item', $preAltItemId),
            'pre_alt_item_id' => $preAltItemId,
            'group_id' => null,
            'group_ref' => null,
            'responsavel_id' => $sourceItem['item_responsavel_id'] ?? null,
            'order' => count($diagramItems) + 1,
            'x' => 120 + (($idx % 4) * 220),
            'y' => 180 + (floor($idx / 4) * 110),
            'width' => 180,
            'height' => 76,
            'visual' => [],
        ];
    }

    $gates = [];
    $gateById = [];
    $stmt = $conn->prepare('SELECT * FROM pre_alt_diagrama_gates WHERE diagrama_id = ? ORDER BY id ASC');
    $stmt->bind_param('i', $diagramaId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $id = (int) $row['id'];
        $gate = [
            'id' => $id,
            'ref' => pre_alt_planejamento_ref('gate', $id),
            'title' => (string) $row['titulo'],
            'gate_type' => (string) $row['gate_tipo'],
            'x' => pre_alt_planejamento_float_or_null($row['pos_x']),
            'y' => pre_alt_planejamento_float_or_null($row['pos_y']),
            'width' => pre_alt_planejamento_float_or_null($row['width']),
            'height' => pre_alt_planejamento_float_or_null($row['height']),
            'visual' => pre_alt_planejamento_decode_json($row['visual_json'] ?? null),
        ];
        $gates[] = $gate;
        $gateById[$id] = $gate;
    }
    $stmt->close();

    $refByTypeAndId = ['GRUPO' => [], 'ITEM' => [], 'GATE' => []];
    foreach ($groupById as $id => $row) {
        $refByTypeAndId['GRUPO'][$id] = $row['ref'];
    }
    foreach ($itemById as $id => $row) {
        $refByTypeAndId['ITEM'][$id] = $row['ref'];
    }
    foreach ($gateById as $id => $row) {
        $refByTypeAndId['GATE'][$id] = $row['ref'];
    }

    $dependencies = [];
    $stmt = $conn->prepare('SELECT * FROM pre_alt_diagrama_dependencias WHERE diagrama_id = ? ORDER BY id ASC');
    $stmt->bind_param('i', $diagramaId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $originType = (string) $row['origem_tipo'];
        $targetType = (string) $row['destino_tipo'];
        $originId = (int) $row['origem_id'];
        $targetId = (int) $row['destino_id'];
        if (empty($refByTypeAndId[$originType][$originId]) || empty($refByTypeAndId[$targetType][$targetId])) {
            continue;
        }
        $dependencies[] = [
            'id' => (int) $row['id'],
            'ref' => pre_alt_planejamento_ref('dep', $row['id']),
            'origin_type' => $originType,
            'origin_ref' => $refByTypeAndId[$originType][$originId],
            'target_type' => $targetType,
            'target_ref' => $refByTypeAndId[$targetType][$targetId],
            'condition' => (string) $row['condicao'],
            'aggregation' => (string) $row['agregacao'],
            'note' => (string) ($row['observacao'] ?? ''),
        ];
    }
    $stmt->close();

    return [
        'lote' => $lote,
        'diagram' => $diagram,
        'items_source' => $sourceItems,
        'groups' => $groups,
        'items' => $diagramItems,
        'gates' => $gates,
        'dependencies' => $dependencies,
        'colaboradores' => pre_alt_planejamento_fetch_colaboradores($conn),
    ];
}

function pre_alt_planejamento_source_by_ref(array $graph): array
{
    $map = [];
    foreach (($graph['items_source'] ?? []) as $source) {
        $map[pre_alt_planejamento_ref('item', $source['pre_alt_item_id'])] = $source;
    }
    return $map;
}

function pre_alt_planejamento_validate_graph(array $graph): array
{
    $errors = [];
    $warnings = [];
    $infos = [];

    $nodeRefs = [];
    $groupRefs = [];
    foreach (($graph['groups'] ?? []) as $group) {
        $ref = (string) ($group['ref'] ?? '');
        if ($ref === '') {
            $errors[] = 'Grupo sem referencia interna.';
            continue;
        }
        $nodeRefs[$ref] = ['type' => 'GRUPO', 'label' => $group['name'] ?? $ref, 'responsavel_id' => $group['responsavel_id'] ?? null];
        $groupRefs[$ref] = $group;
    }

    $sourceByRef = pre_alt_planejamento_source_by_ref($graph);
    $groupItemCount = [];
    foreach (($graph['items'] ?? []) as $item) {
        $ref = (string) ($item['ref'] ?? '');
        if ($ref === '') {
            $errors[] = 'Imagem sem referencia interna.';
            continue;
        }
        if (empty($sourceByRef[$ref])) {
            $errors[] = 'Imagem removida ou fora do lote: ' . $ref;
            continue;
        }
        $groupRef = (string) ($item['group_ref'] ?? '');
        if ($groupRef !== '') {
            if (!isset($groupRefs[$groupRef])) {
                $errors[] = 'Imagem vinculada a grupo inexistente: ' . ($sourceByRef[$ref]['nome'] ?? $ref);
            } else {
                $groupItemCount[$groupRef] = ($groupItemCount[$groupRef] ?? 0) + 1;
            }
        }
        $effectiveResponsible = pre_alt_planejamento_int_or_null($item['responsavel_id'] ?? null);
        $groupResponsible = $groupRef && isset($groupRefs[$groupRef])
            ? pre_alt_planejamento_int_or_null($groupRefs[$groupRef]['responsavel_id'] ?? null)
            : null;
        if (!$effectiveResponsible && !$groupResponsible && empty($sourceByRef[$ref]['item_responsavel_id'])) {
            $warnings[] = 'Imagem sem responsavel: ' . ($sourceByRef[$ref]['nome'] ?? $ref);
        }
        if ($effectiveResponsible && $groupResponsible && $effectiveResponsible !== $groupResponsible) {
            $warnings[] = 'Responsavel da imagem diverge do grupo: ' . ($sourceByRef[$ref]['nome'] ?? $ref);
        }
        $nodeRefs[$ref] = ['type' => 'ITEM', 'label' => $sourceByRef[$ref]['nome'] ?? $ref, 'responsavel_id' => $effectiveResponsible];
    }

    foreach (($graph['gates'] ?? []) as $gate) {
        $ref = (string) ($gate['ref'] ?? '');
        if ($ref === '') {
            $errors[] = 'Gate sem referencia interna.';
            continue;
        }
        $nodeRefs[$ref] = ['type' => 'GATE', 'label' => $gate['title'] ?? $ref, 'responsavel_id' => null];
    }

    foreach ($groupRefs as $ref => $group) {
        if (($groupItemCount[$ref] ?? 0) === 0) {
            $warnings[] = 'Grupo vazio: ' . ($group['name'] ?? $ref);
        }
    }

    $seen = [];
    $adj = [];
    $incoming = [];
    foreach (($graph['dependencies'] ?? []) as $dep) {
        $origin = (string) ($dep['origin_ref'] ?? '');
        $target = (string) ($dep['target_ref'] ?? '');
        $condition = strtoupper((string) ($dep['condition'] ?? 'APROVADA'));
        $aggregation = strtoupper((string) ($dep['aggregation'] ?? 'ALL'));
        if ($origin === '' || $target === '' || !isset($nodeRefs[$origin]) || !isset($nodeRefs[$target])) {
            $errors[] = 'Dependencia com origem ou destino inexistente.';
            continue;
        }
        if ($origin === $target) {
            $errors[] = 'Dependencia de si mesmo: ' . ($nodeRefs[$origin]['label'] ?? $origin);
            continue;
        }
        $key = $origin . '>' . $target . '|' . $condition . '|' . $aggregation;
        if (isset($seen[$key])) {
            $errors[] = 'Dependencia duplicada entre ' . ($nodeRefs[$origin]['label'] ?? $origin) . ' e ' . ($nodeRefs[$target]['label'] ?? $target) . '.';
            continue;
        }
        $seen[$key] = true;
        $adj[$origin][] = $target;
        $incoming[$target] = true;
    }

    $visiting = [];
    $visited = [];
    $cycleFound = false;
    $visit = function (string $node) use (&$visit, &$visiting, &$visited, &$adj, &$cycleFound): void {
        if ($cycleFound || isset($visited[$node])) {
            return;
        }
        if (isset($visiting[$node])) {
            $cycleFound = true;
            return;
        }
        $visiting[$node] = true;
        foreach (($adj[$node] ?? []) as $next) {
            $visit($next);
        }
        unset($visiting[$node]);
        $visited[$node] = true;
    };
    foreach (array_keys($nodeRefs) as $ref) {
        $visit($ref);
    }
    if ($cycleFound) {
        $errors[] = 'O diagrama possui dependencia circular.';
    }

    foreach ($nodeRefs as $ref => $node) {
        if ($node['type'] === 'ITEM' && empty($incoming[$ref])) {
            $infos[] = 'Imagem independente: ' . ($node['label'] ?? $ref);
        }
    }

    return [
        'valid' => empty($errors),
        'errors' => array_values(array_unique($errors)),
        'warnings' => array_values(array_unique($warnings)),
        'infos' => array_values(array_unique($infos)),
    ];
}

function pre_alt_planejamento_save_graph(mysqli $conn, int $loteId, array $payload): array
{
    pre_alt_planejamento_ensure_schema($conn);
    $lote = pre_alt_planejamento_fetch_lote($conn, $loteId);
    if (!$lote) {
        throw new RuntimeException('Lote nao encontrado.');
    }

    $sourceItems = pre_alt_planejamento_fetch_itens($conn, $loteId);
    $allowedPreAltItems = [];
    foreach ($sourceItems as $source) {
        $allowedPreAltItems[(int) $source['pre_alt_item_id']] = true;
    }

    $actorId = pre_alt_planejamento_actor_id();
    $diagramPayload = is_array($payload['diagram'] ?? null) ? $payload['diagram'] : [];
    $name = trim((string) ($diagramPayload['name'] ?? ''));
    if ($name === '') {
        $name = 'Planejamento - ' . ($lote['nomenclatura'] ?? ('Lote ' . $loteId));
    }

    $conn->begin_transaction();
    try {
        $diagramRow = pre_alt_planejamento_fetch_diagrama_row($conn, $loteId);
        if ($diagramRow) {
            $diagramaId = (int) $diagramRow['id'];
            $stmt = $conn->prepare("UPDATE pre_alt_diagramas SET nome = ?, status = 'RASCUNHO', updated_by = ?, published_at = NULL, updated_at = NOW() WHERE id = ?");
            if (!$stmt) {
                throw new RuntimeException('Nao foi possivel atualizar o diagrama.');
            }
            $stmt->bind_param('sii', $name, $actorId, $diagramaId);
            $stmt->execute();
            $stmt->close();

            foreach ([
                'DELETE FROM pre_alt_diagrama_dependencias WHERE diagrama_id = ?',
                'DELETE FROM pre_alt_diagrama_itens WHERE diagrama_id = ?',
                'DELETE FROM pre_alt_diagrama_gates WHERE diagrama_id = ?',
                'DELETE FROM pre_alt_diagrama_grupos WHERE diagrama_id = ?',
            ] as $sql) {
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new RuntimeException('Nao foi possivel limpar o diagrama anterior.');
                }
                $stmt->bind_param('i', $diagramaId);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO pre_alt_diagramas (pre_alt_lote_id, nome, status, created_by, updated_by) VALUES (?, ?, 'RASCUNHO', ?, ?)");
            if (!$stmt) {
                throw new RuntimeException('Nao foi possivel criar o diagrama.');
            }
            $stmt->bind_param('isii', $loteId, $name, $actorId, $actorId);
            $stmt->execute();
            $diagramaId = (int) $stmt->insert_id;
            $stmt->close();
        }

        $groupMap = [];
        $stmtGroup = $conn->prepare(
            'INSERT INTO pre_alt_diagrama_grupos (diagrama_id, nome, responsavel_id, ordem, pos_x, pos_y, width, height, visual_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmtGroup) {
            throw new RuntimeException('Nao foi possivel preparar os grupos.');
        }
        foreach (($payload['groups'] ?? []) as $idx => $group) {
            if (!is_array($group)) {
                continue;
            }
            $ref = trim((string) ($group['ref'] ?? ''));
            $groupName = trim((string) ($group['name'] ?? ''));
            if ($ref === '' || $groupName === '') {
                continue;
            }
            $responsavelId = pre_alt_planejamento_int_or_null($group['responsavel_id'] ?? null);
            $order = isset($group['order']) && is_numeric($group['order']) ? (int) $group['order'] : ($idx + 1);
            $x = pre_alt_planejamento_float_or_null($group['x'] ?? null);
            $y = pre_alt_planejamento_float_or_null($group['y'] ?? null);
            $width = pre_alt_planejamento_float_or_null($group['width'] ?? null);
            $height = pre_alt_planejamento_float_or_null($group['height'] ?? null);
            $visual = pre_alt_planejamento_encode_json($group['visual'] ?? null);
            $stmtGroup->bind_param('isiidddds', $diagramaId, $groupName, $responsavelId, $order, $x, $y, $width, $height, $visual);
            $stmtGroup->execute();
            $groupMap[$ref] = (int) $stmtGroup->insert_id;
        }
        $stmtGroup->close();

        $itemMap = [];
        $stmtItem = $conn->prepare(
            'INSERT INTO pre_alt_diagrama_itens (diagrama_id, pre_alt_item_id, grupo_id, responsavel_id, ordem, pos_x, pos_y, width, height, visual_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmtItem) {
            throw new RuntimeException('Nao foi possivel preparar os itens.');
        }
        foreach (($payload['items'] ?? []) as $idx => $item) {
            if (!is_array($item)) {
                continue;
            }
            $ref = trim((string) ($item['ref'] ?? ''));
            $preAltItemId = isset($item['pre_alt_item_id']) ? (int) $item['pre_alt_item_id'] : 0;
            if ($ref === '' || $preAltItemId <= 0 || !isset($allowedPreAltItems[$preAltItemId])) {
                continue;
            }
            $groupRef = trim((string) ($item['group_ref'] ?? ''));
            $groupId = $groupRef !== '' && isset($groupMap[$groupRef]) ? $groupMap[$groupRef] : null;
            $responsavelId = pre_alt_planejamento_int_or_null($item['responsavel_id'] ?? null);
            $order = isset($item['order']) && is_numeric($item['order']) ? (int) $item['order'] : ($idx + 1);
            $x = pre_alt_planejamento_float_or_null($item['x'] ?? null);
            $y = pre_alt_planejamento_float_or_null($item['y'] ?? null);
            $width = pre_alt_planejamento_float_or_null($item['width'] ?? null);
            $height = pre_alt_planejamento_float_or_null($item['height'] ?? null);
            $visual = pre_alt_planejamento_encode_json($item['visual'] ?? null);
            $stmtItem->bind_param('iiiiidddds', $diagramaId, $preAltItemId, $groupId, $responsavelId, $order, $x, $y, $width, $height, $visual);
            $stmtItem->execute();
            $itemMap[$ref] = (int) $stmtItem->insert_id;
        }
        $stmtItem->close();

        $gateMap = [];
        $stmtGate = $conn->prepare(
            'INSERT INTO pre_alt_diagrama_gates (diagrama_id, titulo, gate_tipo, pos_x, pos_y, width, height, visual_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmtGate) {
            throw new RuntimeException('Nao foi possivel preparar os gates.');
        }
        $allowedGateTypes = ['APROVACAO', 'FINALIZACAO', 'MANUAL'];
        foreach (($payload['gates'] ?? []) as $idx => $gate) {
            if (!is_array($gate)) {
                continue;
            }
            $ref = trim((string) ($gate['ref'] ?? ''));
            $title = trim((string) ($gate['title'] ?? ''));
            if ($ref === '' || $title === '') {
                continue;
            }
            $gateType = strtoupper(trim((string) ($gate['gate_type'] ?? 'APROVACAO')));
            if (!in_array($gateType, $allowedGateTypes, true)) {
                $gateType = 'APROVACAO';
            }
            $x = pre_alt_planejamento_float_or_null($gate['x'] ?? null);
            $y = pre_alt_planejamento_float_or_null($gate['y'] ?? null);
            $width = pre_alt_planejamento_float_or_null($gate['width'] ?? null);
            $height = pre_alt_planejamento_float_or_null($gate['height'] ?? null);
            $visual = pre_alt_planejamento_encode_json($gate['visual'] ?? null);
            $stmtGate->bind_param('issdddds', $diagramaId, $title, $gateType, $x, $y, $width, $height, $visual);
            $stmtGate->execute();
            $gateMap[$ref] = (int) $stmtGate->insert_id;
        }
        $stmtGate->close();

        $mapsByType = [
            'GRUPO' => $groupMap,
            'ITEM' => $itemMap,
            'GATE' => $gateMap,
        ];
        $stmtDep = $conn->prepare(
            'INSERT IGNORE INTO pre_alt_diagrama_dependencias (diagrama_id, origem_tipo, origem_id, destino_tipo, destino_id, condicao, agregacao, observacao) VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, \'\'))'
        );
        if (!$stmtDep) {
            throw new RuntimeException('Nao foi possivel preparar as dependencias.');
        }
        foreach (($payload['dependencies'] ?? []) as $dep) {
            if (!is_array($dep)) {
                continue;
            }
            $originType = strtoupper(trim((string) ($dep['origin_type'] ?? '')));
            $targetType = strtoupper(trim((string) ($dep['target_type'] ?? '')));
            $originRef = trim((string) ($dep['origin_ref'] ?? ''));
            $targetRef = trim((string) ($dep['target_ref'] ?? ''));
            if (
                !isset($mapsByType[$originType][$originRef])
                || !isset($mapsByType[$targetType][$targetRef])
            ) {
                continue;
            }
            $condition = strtoupper(trim((string) ($dep['condition'] ?? 'APROVADA')));
            if (!in_array($condition, ['APROVADA', 'FINALIZADA'], true)) {
                $condition = 'APROVADA';
            }
            $aggregation = 'ALL';
            $note = trim((string) ($dep['note'] ?? ''));
            $originId = $mapsByType[$originType][$originRef];
            $targetId = $mapsByType[$targetType][$targetRef];
            $stmtDep->bind_param('isisisss', $diagramaId, $originType, $originId, $targetType, $targetId, $condition, $aggregation, $note);
            $stmtDep->execute();
        }
        $stmtDep->close();

        pre_alt_registrar_historico(
            $conn,
            $loteId,
            'PLANEJAMENTO_DIAGRAMA',
            'diagrama',
            null,
            'RASCUNHO',
            'Diagrama de planejamento salvo.',
            null,
            pre_alt_batch_id(),
            ['diagrama_id' => $diagramaId]
        );

        $conn->commit();
        return pre_alt_planejamento_fetch_graph($conn, $loteId);
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function pre_alt_planejamento_publish(mysqli $conn, int $loteId): array
{
    $graph = pre_alt_planejamento_fetch_graph($conn, $loteId);
    if (empty($graph['diagram']['id'])) {
        throw new RuntimeException('Salve o diagrama antes de publicar.');
    }
    $validation = pre_alt_planejamento_validate_graph($graph);
    if (!$validation['valid']) {
        return ['success' => true, 'published' => false, 'validation' => $validation];
    }

    $actorId = pre_alt_planejamento_actor_id();
    $diagramId = (int) $graph['diagram']['id'];
    $stmt = $conn->prepare("UPDATE pre_alt_diagramas SET status = 'PUBLICADO', updated_by = ?, published_at = NOW(), updated_at = NOW() WHERE id = ?");
    if (!$stmt) {
        throw new RuntimeException('Nao foi possivel publicar o diagrama.');
    }
    $stmt->bind_param('ii', $actorId, $diagramId);
    $stmt->execute();
    $stmt->close();

    pre_alt_registrar_historico(
        $conn,
        $loteId,
        'PUBLICACAO_DIAGRAMA',
        'status',
        $graph['diagram']['status'] ?? null,
        'PUBLICADO',
        'Diagrama de planejamento publicado.',
        null,
        pre_alt_batch_id(),
        ['diagrama_id' => $diagramId, 'warnings' => $validation['warnings']]
    );

    return [
        'success' => true,
        'published' => true,
        'validation' => $validation,
        'graph' => pre_alt_planejamento_fetch_graph($conn, $loteId),
    ];
}
