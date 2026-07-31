<?php

if (!defined('PENDENCIAS_LINKS_GOOGLE_EARTH_RESPONSAVEL_ID')) {
    // André é o responsável padrão pela pendência do Google Earth.
    define('PENDENCIAS_LINKS_GOOGLE_EARTH_RESPONSAVEL_ID', 9);
}

function pendencias_links_obra_google_earth_responsavel_id(): int
{
    return (int) PENDENCIAS_LINKS_GOOGLE_EARTH_RESPONSAVEL_ID;
}

function pendencias_links_obra_ensure_schema(mysqli $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $sql = "CREATE TABLE IF NOT EXISTS pendencias_links_obra (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        obra_id INT NOT NULL,
        tipo_link VARCHAR(30) NOT NULL,
        origem VARCHAR(50) NOT NULL,
        status_id INT NULL,
        entrega_id INT NULL,
        responsavel_id INT NULL,
        status ENUM('aberta','concluida') NOT NULL DEFAULT 'aberta',
        criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        concluida_em DATETIME NULL,
        concluida_por INT NULL,
        atualizada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY ux_pendencias_links_obra_tipo (obra_id, tipo_link),
        KEY idx_pendencias_links_status (status),
        KEY idx_pendencias_links_entrega (entrega_id),
        KEY idx_pendencias_links_responsavel (responsavel_id),
        KEY idx_pendencias_links_status_etapa (status_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        throw new RuntimeException('Falha ao garantir pendências de Links: ' . $conn->error);
    }

    // Bancos criados antes da coluna de responsável precisam ser atualizados
    // automaticamente, pois a tabela já existe nesses ambientes.
    $columnResult = $conn->query("SHOW COLUMNS FROM pendencias_links_obra LIKE 'responsavel_id'");
    if ($columnResult && $columnResult->num_rows === 0) {
        if (!$conn->query('ALTER TABLE pendencias_links_obra ADD COLUMN responsavel_id INT NULL AFTER entrega_id')) {
            throw new RuntimeException('Falha ao adicionar responsável às pendências de Links: ' . $conn->error);
        }
        $conn->query('ALTER TABLE pendencias_links_obra ADD KEY idx_pendencias_links_responsavel (responsavel_id)');
    }
    if ($columnResult instanceof mysqli_result) {
        $columnResult->close();
    }

    // Pendências antigas de Google Earth também passam a pertencer ao André.
    $responsavelId = pendencias_links_obra_google_earth_responsavel_id();
    $backfill = $conn->prepare(
        "UPDATE pendencias_links_obra
            SET responsavel_id = ?
          WHERE tipo_link = 'google_earth'
            AND (responsavel_id IS NULL OR responsavel_id = 0)"
    );
    if ($backfill) {
        $backfill->bind_param('i', $responsavelId);
        $backfill->execute();
        $backfill->close();
    }

    $ensured = true;
}

function pendencias_links_obra_config(string $tipoLink): ?array
{
    $links = [
        'drive' => ['campo' => 'link_drive', 'label' => 'Link do Drive'],
        'review' => ['campo' => 'link_review', 'label' => 'Link do Review Studio'],
        'google_earth' => ['campo' => 'google_earth', 'label' => 'Link do Google Earth'],
    ];

    return $links[$tipoLink] ?? null;
}

function pendencias_links_obra_tipo_por_status(mysqli $conn, int $statusId): ?string
{
    if ($statusId <= 0) {
        return null;
    }

    $stmt = $conn->prepare('SELECT nome_status FROM status_imagem WHERE idstatus = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Falha ao consultar a etapa da entrega: ' . $conn->error);
    }
    $stmt->bind_param('i', $statusId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $etapa = strtoupper(trim((string) ($row['nome_status'] ?? '')));
    return match ($etapa) {
        'EF' => 'drive',
        'R00' => 'review',
        default => null,
    };
}

function pendencias_links_obra_campo_vazio(mysqli $conn, int $obraId, string $campo): bool
{
    $camposPermitidos = ['link_drive', 'link_review', 'google_earth'];
    if (!in_array($campo, $camposPermitidos, true) || $obraId <= 0) {
        return false;
    }

    $stmt = $conn->prepare("SELECT NULLIF(TRIM(COALESCE($campo, '')), '') AS valor FROM obra WHERE idobra = ? LIMIT 1");
    if (!$stmt) {
        throw new RuntimeException('Falha ao consultar o link da obra: ' . $conn->error);
    }
    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return isset($row) && empty($row['valor']);
}

function pendencias_links_obra_abrir(
    mysqli $conn,
    int $obraId,
    string $tipoLink,
    string $origem,
    ?int $statusId = null,
    ?int $entregaId = null,
    ?int $responsavelId = null
): ?int {
    $config = pendencias_links_obra_config($tipoLink);
    if (!$config || $obraId <= 0 || !pendencias_links_obra_campo_vazio($conn, $obraId, $config['campo'])) {
        return null;
    }

    pendencias_links_obra_ensure_schema($conn);
    $stmt = $conn->prepare(
        "INSERT INTO pendencias_links_obra
            (obra_id, tipo_link, origem, status_id, entrega_id, responsavel_id, status, concluida_em, concluida_por)
         VALUES (?, ?, ?, ?, ?, ?, 'aberta', NULL, NULL)
         ON DUPLICATE KEY UPDATE
            origem = VALUES(origem),
            status_id = VALUES(status_id),
            entrega_id = VALUES(entrega_id),
            responsavel_id = COALESCE(VALUES(responsavel_id), responsavel_id),
            status = 'aberta',
            concluida_em = NULL,
            concluida_por = NULL"
    );
    if (!$stmt) {
        throw new RuntimeException('Falha ao abrir pendência de Link: ' . $conn->error);
    }

    $stmt->bind_param('issiii', $obraId, $tipoLink, $origem, $statusId, $entregaId, $responsavelId);
    $stmt->execute();
    $id = (int) $conn->insert_id;
    $stmt->close();

    return $id > 0 ? $id : null;
}

function pendencias_links_obra_abrir_por_entrega(mysqli $conn, int $obraId, int $statusId, int $entregaId): ?int
{
    $tipoLink = pendencias_links_obra_tipo_por_status($conn, $statusId);
    if (!$tipoLink) {
        return null;
    }

    return pendencias_links_obra_abrir($conn, $obraId, $tipoLink, 'entrega', $statusId, $entregaId);
}

function pendencias_links_obra_abrir_google_earth(mysqli $conn, int $obraId): ?int
{
    return pendencias_links_obra_abrir(
        $conn,
        $obraId,
        'google_earth',
        'onboarding',
        null,
        null,
        pendencias_links_obra_google_earth_responsavel_id()
    );
}

function pendencias_links_obra_concluir_por_campo(
    mysqli $conn,
    int $obraId,
    string $campo,
    string $valor,
    ?int $colaboradorId = null
): int {
    if ($obraId <= 0 || trim($valor) === '') {
        return 0;
    }

    $tipoLink = array_search($campo, [
        'drive' => 'link_drive',
        'review' => 'link_review',
        'google_earth' => 'google_earth',
    ], true);
    if (!$tipoLink) {
        return 0;
    }

    pendencias_links_obra_ensure_schema($conn);
    $stmt = $conn->prepare(
        "UPDATE pendencias_links_obra
            SET status = 'concluida',
                concluida_em = NOW(),
                concluida_por = ?
          WHERE obra_id = ?
            AND tipo_link = ?
            AND status = 'aberta'"
    );
    if (!$stmt) {
        throw new RuntimeException('Falha ao concluir pendência de Link: ' . $conn->error);
    }
    $stmt->bind_param('iis', $colaboradorId, $obraId, $tipoLink);
    $stmt->execute();
    $affected = (int) $stmt->affected_rows;
    $stmt->close();

    return $affected;
}

function pendencias_links_obra_listar_abertas(mysqli $conn): array
{
    pendencias_links_obra_ensure_schema($conn);
    $sql = "SELECT
            p.id,
            p.obra_id,
            p.tipo_link,
            p.origem,
            p.status_id,
            p.entrega_id,
            p.responsavel_id,
            c.nome_colaborador AS responsavel_nome,
            p.criada_em,
            COALESCE(NULLIF(o.nomenclatura, ''), NULLIF(o.nome_obra, ''), CONCAT('Obra ', o.idobra)) AS obra_nome
        FROM pendencias_links_obra p
        JOIN obra o ON o.idobra = p.obra_id
        LEFT JOIN colaborador c ON c.idcolaborador = p.responsavel_id
        WHERE p.status = 'aberta'
          AND o.status_obra = 0
        ORDER BY p.criada_em ASC, p.id ASC";

    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException('Falha ao listar pendências de Links: ' . $conn->error);
    }

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $config = pendencias_links_obra_config((string) $row['tipo_link']);
        if (!$config) {
            continue;
        }
        $items[] = [
            'id' => (int) $row['id'],
            'obra_id' => (int) $row['obra_id'],
            'obra_nome' => (string) $row['obra_nome'],
            'tipo_link' => (string) $row['tipo_link'],
            'link_label' => $config['label'],
            'origem' => (string) $row['origem'],
            'status_id' => isset($row['status_id']) ? (int) $row['status_id'] : null,
            'entrega_id' => isset($row['entrega_id']) ? (int) $row['entrega_id'] : null,
            'responsavel_id' => isset($row['responsavel_id']) ? (int) $row['responsavel_id'] : null,
            'responsavel_nome' => (string) ($row['responsavel_nome'] ?? ''),
            'criada_em' => $row['criada_em'],
        ];
    }
    $result->close();

    return $items;
}
