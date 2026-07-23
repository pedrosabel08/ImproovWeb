<?php
require_once __DIR__ . '/pendencias_links_obra_helper.php';

if (!defined('PENDENCIAS_PROJETO_OK_SLA_HORAS')) {
    define('PENDENCIAS_PROJETO_OK_SLA_HORAS', 24);
}

if (!defined('PENDENCIAS_IMAGEM_TODO_SLA_HORAS')) {
    define('PENDENCIAS_IMAGEM_TODO_SLA_HORAS', 4);
}

if (!defined('PENDENCIAS_IMAGEM_RESPONSAVEL_ID')) {
    define('PENDENCIAS_IMAGEM_RESPONSAVEL_ID', 1);
}

if (!defined('PENDENCIAS_PEDRO_ID')) {
    define('PENDENCIAS_PEDRO_ID', 21);
}

if (!defined('PENDENCIAS_ANDRE_ID')) {
    define('PENDENCIAS_ANDRE_ID', 9);
}

function pendencias_operacionais_user_in(int $colaboradorId, array $allowedIds): bool
{
    return in_array($colaboradorId, array_map('intval', $allowedIds), true);
}

function pendencias_operacionais_int_list_sql(array $ids): string
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function (int $id): bool {
        return $id > 0;
    })));

    return implode(',', $ids);
}

function pendencias_operacionais_user_has_operational_access(mysqli $conn, int $colaboradorId): bool
{
    if (pendencias_operacionais_user_in($colaboradorId, [
        PENDENCIAS_PEDRO_ID,
        PENDENCIAS_IMAGEM_RESPONSAVEL_ID,
        PENDENCIAS_ANDRE_ID,
    ])) {
        return true;
    }

    if (!pendencias_operacionais_table_exists($conn, 'render_alta')) {
        return false;
    }

    $stmt = $conn->prepare(
        "SELECT 1
           FROM render_alta
          WHERE status = 'Em aprovação'
            AND responsavel_id = ?
          LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $colaboradorId);
    $stmt->execute();
    $stmt->store_result();
    $hasAccess = $stmt->num_rows > 0;
    $stmt->close();

    return $hasAccess;
}

function pendencias_operacionais_table_exists(mysqli $conn, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $conn->prepare(
        "SELECT 1
           FROM information_schema.tables
          WHERE table_schema = DATABASE()
            AND table_name = ?
          LIMIT 1"
    );
    if (!$stmt) {
        $cache[$table] = false;
        return false;
    }

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $stmt->store_result();
    $cache[$table] = $stmt->num_rows > 0;
    $stmt->close();

    return $cache[$table];
}

function pendencias_operacionais_column_exists(mysqli $conn, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . ':' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $conn->prepare(
        "SELECT 1
           FROM information_schema.columns
          WHERE table_schema = DATABASE()
            AND table_name = ?
            AND column_name = ?
          LIMIT 1"
    );
    if (!$stmt) {
        $cache[$key] = false;
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $stmt->store_result();
    $cache[$key] = $stmt->num_rows > 0;
    $stmt->close();

    return $cache[$key];
}

function pendencias_operacionais_ensure_schema(mysqli $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS checklist_operacional (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            module_key VARCHAR(40) NOT NULL,
            entity_type VARCHAR(40) NOT NULL,
            entity_id INT NOT NULL,
            obra_id INT NULL,
            responsavel_id INT NULL,
            sla_start_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            due_at DATETIME NOT NULL,
            status ENUM('aberto','concluido','cancelado') NOT NULL DEFAULT 'aberto',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ux_checklist_entity (module_key, entity_type, entity_id),
            KEY idx_checklist_module_status (module_key, status),
            KEY idx_checklist_responsavel (responsavel_id),
            KEY idx_checklist_obra (obra_id),
            KEY idx_checklist_due (due_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS checklist_operacional_item (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            checklist_id INT UNSIGNED NOT NULL,
            item_key VARCHAR(60) NOT NULL,
            label VARCHAR(120) NOT NULL,
            required TINYINT(1) NOT NULL DEFAULT 1,
            done TINYINT(1) NOT NULL DEFAULT 0,
            done_by INT NULL,
            done_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ux_checklist_item (checklist_id, item_key),
            KEY idx_checklist_item_done (checklist_id, done),
            CONSTRAINT fk_checklist_item_checklist
                FOREIGN KEY (checklist_id) REFERENCES checklist_operacional (id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $ensured = true;
}

function pendencias_operacionais_project_items(): array
{
    return [
        'briefing' => 'Briefing',
        'kickoff' => 'Kickoff',
        'referencias_mood' => 'Referencias / Mood',
    ];
}

function pendencias_operacionais_image_items(bool $subtipoObrigatorio = true): array
{
    return [
        'subtipo_definido' => [
            'label' => 'Subtipo definido',
            'required' => $subtipoObrigatorio ? 1 : 0,
        ],
        'fila_operacional_validada' => [
            'label' => 'Fila operacional validada',
            'required' => 1,
        ],
    ];
}

function pendencias_operacionais_find_checklist(mysqli $conn, string $moduleKey, string $entityType, int $entityId): ?array
{
    pendencias_operacionais_ensure_schema($conn);

    $stmt = $conn->prepare(
        "SELECT *
           FROM checklist_operacional
          WHERE module_key = ?
            AND entity_type = ?
            AND entity_id = ?
          LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ssi', $moduleKey, $entityType, $entityId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function pendencias_operacionais_sync_items(mysqli $conn, int $checklistId, array $items): void
{
    $stmt = $conn->prepare(
        "INSERT INTO checklist_operacional_item (checklist_id, item_key, label, required)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            label = VALUES(label),
            required = VALUES(required)"
    );
    if (!$stmt) {
        return;
    }

    foreach ($items as $key => $config) {
        $label = is_array($config) ? (string) ($config['label'] ?? $key) : (string) $config;
        $required = is_array($config) ? (int) ($config['required'] ?? 1) : 1;
        $stmt->bind_param('issi', $checklistId, $key, $label, $required);
        $stmt->execute();
    }

    $stmt->close();
}

function pendencias_operacionais_update_checklist_status(mysqli $conn, int $checklistId): void
{
    $stmtCount = $conn->prepare(
        "SELECT
            SUM(CASE WHEN required = 1 AND done = 0 THEN 1 ELSE 0 END) AS pendentes
           FROM checklist_operacional_item
          WHERE checklist_id = ?"
    );
    if (!$stmtCount) {
        return;
    }

    $stmtCount->bind_param('i', $checklistId);
    $stmtCount->execute();
    $row = $stmtCount->get_result()->fetch_assoc();
    $stmtCount->close();

    $status = ((int) ($row['pendentes'] ?? 0) <= 0) ? 'concluido' : 'aberto';
    $stmtUpdate = $conn->prepare('UPDATE checklist_operacional SET status = ?, updated_at = NOW() WHERE id = ?');
    if ($stmtUpdate) {
        $stmtUpdate->bind_param('si', $status, $checklistId);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }
}

function pendencias_operacionais_ensure_project_checklist(mysqli $conn, int $obraId, ?int $responsavelId = null): ?int
{
    if ($obraId <= 0) {
        return null;
    }

    pendencias_operacionais_ensure_schema($conn);
    $existing = pendencias_operacionais_find_checklist($conn, 'projeto', 'obra', $obraId);
    if ($existing) {
        $checklistId = (int) $existing['id'];
        pendencias_operacionais_sync_items($conn, $checklistId, pendencias_operacionais_project_items());
        pendencias_operacionais_update_checklist_status($conn, $checklistId);
        return $checklistId;
    }

    $responsavelId = $responsavelId ?: null;
    $slaHours = (int) PENDENCIAS_PROJETO_OK_SLA_HORAS;
    $stmt = $conn->prepare(
        "INSERT INTO checklist_operacional
            (module_key, entity_type, entity_id, obra_id, responsavel_id, sla_start_at, due_at)
         VALUES ('projeto', 'obra', ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? HOUR))"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('iiii', $obraId, $obraId, $responsavelId, $slaHours);
    $stmt->execute();
    $checklistId = (int) $stmt->insert_id;
    $stmt->close();

    pendencias_operacionais_sync_items($conn, $checklistId, pendencias_operacionais_project_items());
    return $checklistId;
}

function pendencias_operacionais_image_requires_subtipo(?string $tipoImagem): bool
{
    return strtolower(trim((string) $tipoImagem)) !== 'fachada';
}

function pendencias_operacionais_fetch_image_context(mysqli $conn, int $imagemId): ?array
{
    if ($imagemId <= 0) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT
            ico.idimagens_cliente_obra AS imagem_id,
            ico.obra_id,
            ico.imagem_nome,
            ico.tipo_imagem,
            ico.subtipo_id,
            ico.substatus_id,
            o.status_obra
         FROM imagens_cliente_obra ico
         LEFT JOIN obra o ON o.idobra = ico.obra_id
         WHERE ico.idimagens_cliente_obra = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $imagemId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function pendencias_operacionais_set_item_done(mysqli $conn, int $checklistId, string $itemKey, bool $done): void
{
    $stmt = $conn->prepare(
        "UPDATE checklist_operacional_item
            SET done = ?,
                done_by = CASE WHEN ? = 1 THEN COALESCE(done_by, ?) ELSE NULL END,
                done_at = CASE WHEN ? = 1 THEN COALESCE(done_at, NOW()) ELSE NULL END
          WHERE checklist_id = ?
            AND item_key = ?"
    );
    if (!$stmt) {
        return;
    }

    $doneInt = $done ? 1 : 0;
    $systemUser = (int) PENDENCIAS_IMAGEM_RESPONSAVEL_ID;
    $stmt->bind_param('iiiiis', $doneInt, $doneInt, $systemUser, $doneInt, $checklistId, $itemKey);
    $stmt->execute();
    $stmt->close();
}

function pendencias_operacionais_sync_image_checklist(mysqli $conn, int $imagemId): ?int
{
    if ($imagemId <= 0) {
        return null;
    }

    pendencias_operacionais_ensure_schema($conn);
    $context = pendencias_operacionais_fetch_image_context($conn, $imagemId);
    if (!$context) {
        return null;
    }

    $obraId = (int) ($context['obra_id'] ?? 0);
    $isActiveObra = !isset($context['status_obra']) || (int) $context['status_obra'] === 0;
    $isTodoSubstatus = (int) ($context['substatus_id'] ?? 0) === 2;
    $shouldHaveChecklist = $obraId > 0 && $isActiveObra && $isTodoSubstatus;

    $existing = pendencias_operacionais_find_checklist($conn, 'imagem', 'imagem', $imagemId);

    if (!$shouldHaveChecklist) {
        if ($existing && ($existing['status'] ?? '') === 'aberto') {
            $stmtCancel = $conn->prepare(
                "UPDATE checklist_operacional
                    SET status = 'cancelado',
                        updated_at = NOW()
                  WHERE id = ?"
            );
            if ($stmtCancel) {
                $checklistId = (int) $existing['id'];
                $stmtCancel->bind_param('i', $checklistId);
                $stmtCancel->execute();
                $stmtCancel->close();
            }
        }
        return null;
    }

    $responsavelId = (int) PENDENCIAS_IMAGEM_RESPONSAVEL_ID;
    $subtipoObrigatorio = pendencias_operacionais_image_requires_subtipo($context['tipo_imagem'] ?? '');
    $subtipoDefinido = !$subtipoObrigatorio || !empty($context['subtipo_id']);

    if ($existing) {
        $checklistId = (int) $existing['id'];
        $wasCanceled = ($existing['status'] ?? '') === 'cancelado';
        $stmtChecklist = $conn->prepare(
            "UPDATE checklist_operacional
                SET obra_id = ?,
                    responsavel_id = ?,
                    status = CASE WHEN status = 'cancelado' THEN 'aberto' ELSE status END,
                    sla_start_at = CASE WHEN status = 'cancelado' THEN NOW() ELSE sla_start_at END,
                    due_at = CASE WHEN status = 'cancelado' THEN DATE_ADD(NOW(), INTERVAL ? HOUR) ELSE due_at END,
                    updated_at = NOW()
              WHERE id = ?"
        );
        if ($stmtChecklist) {
            $slaHours = (int) PENDENCIAS_IMAGEM_TODO_SLA_HORAS;
            $stmtChecklist->bind_param('iiii', $obraId, $responsavelId, $slaHours, $checklistId);
            $stmtChecklist->execute();
            $stmtChecklist->close();
        }
        pendencias_operacionais_sync_items($conn, $checklistId, pendencias_operacionais_image_items($subtipoObrigatorio));
        if ($wasCanceled) {
            pendencias_operacionais_set_item_done($conn, $checklistId, 'fila_operacional_validada', false);
        }
    } else {
        $slaHours = (int) PENDENCIAS_IMAGEM_TODO_SLA_HORAS;
        $stmt = $conn->prepare(
            "INSERT INTO checklist_operacional
                (module_key, entity_type, entity_id, obra_id, responsavel_id, sla_start_at, due_at)
             VALUES ('imagem', 'imagem', ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? HOUR))"
        );
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('iiii', $imagemId, $obraId, $responsavelId, $slaHours);
        $stmt->execute();
        $checklistId = (int) $stmt->insert_id;
        $stmt->close();
        pendencias_operacionais_sync_items($conn, $checklistId, pendencias_operacionais_image_items($subtipoObrigatorio));
    }

    pendencias_operacionais_set_item_done($conn, $checklistId, 'subtipo_definido', $subtipoDefinido);

    pendencias_operacionais_update_checklist_status($conn, $checklistId);
    return $checklistId;
}

function pendencias_operacionais_ensure_image_checklist(
    mysqli $conn,
    int $imagemId,
    int $obraId,
    ?int $responsavelId = null,
    bool $subtipoDefinido = false
): ?int {
    return pendencias_operacionais_sync_image_checklist($conn, $imagemId);
}

function pendencias_operacionais_fetch_checklist_items(mysqli $conn, int $checklistId): array
{
    $stmt = $conn->prepare(
        "SELECT item_key, label, required, done, done_by, done_at
           FROM checklist_operacional_item
          WHERE checklist_id = ?
          ORDER BY id ASC"
    );
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $checklistId);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $row['required'] = (int) ($row['required'] ?? 0);
        $row['done'] = (int) ($row['done'] ?? 0);
        $items[] = $row;
    }
    $stmt->close();

    return $items;
}

function pendencias_operacionais_fetch_checklist_items_map(mysqli $conn, array $checklistIds): array
{
    $idsList = pendencias_operacionais_int_list_sql($checklistIds);
    if ($idsList === '') {
        return [];
    }

    $sql = "SELECT checklist_id, item_key, label, required, done, done_by, done_at
              FROM checklist_operacional_item
             WHERE checklist_id IN ({$idsList})
             ORDER BY checklist_id ASC, id ASC";
    $res = $conn->query($sql);
    if (!$res) {
        return [];
    }

    $itemsByChecklist = [];
    while ($row = $res->fetch_assoc()) {
        $checklistId = (int) ($row['checklist_id'] ?? 0);
        if ($checklistId <= 0) {
            continue;
        }
        $row['required'] = (int) ($row['required'] ?? 0);
        $row['done'] = (int) ($row['done'] ?? 0);
        unset($row['checklist_id']);
        $itemsByChecklist[$checklistId][] = $row;
    }
    $res->close();

    return $itemsByChecklist;
}

function pendencias_operacionais_sla_status(?string $startAt, ?string $dueAt): array
{
    if (!$startAt || !$dueAt) {
        return [
            'nivel' => 'dentro',
            'label' => 'Dentro do SLA',
            'tempo_decorrido_minutos' => null,
            'sla_minutos' => null,
        ];
    }

    try {
        $start = new DateTime($startAt);
        $due = new DateTime($dueAt);
        $now = new DateTime();
    } catch (Throwable $e) {
        return [
            'nivel' => 'dentro',
            'label' => 'Dentro do SLA',
            'tempo_decorrido_minutos' => null,
            'sla_minutos' => null,
        ];
    }

    $slaMinutes = max(1, (int) floor(($due->getTimestamp() - $start->getTimestamp()) / 60));
    $elapsed = max(0, (int) floor(($now->getTimestamp() - $start->getTimestamp()) / 60));

    if ($now <= $due) {
        return [
            'nivel' => 'dentro',
            'label' => 'Dentro do SLA',
            'tempo_decorrido_minutos' => $elapsed,
            'sla_minutos' => $slaMinutes,
        ];
    }

    if ($elapsed > ($slaMinutes * 2)) {
        return [
            'nivel' => 'critico',
            'label' => 'SLA Critico',
            'tempo_decorrido_minutos' => $elapsed,
            'sla_minutos' => $slaMinutes,
        ];
    }

    return [
        'nivel' => 'estourado',
        'label' => 'SLA Estourado',
        'tempo_decorrido_minutos' => $elapsed,
        'sla_minutos' => $slaMinutes,
    ];
}

function pendencias_operacionais_empty_module(string $key, string $name, string $description, string $icon, string $color): array
{
    return [
        'key' => $key,
        'name' => $name,
        'description' => $description,
        'icon' => $icon,
        'color' => $color,
        'total' => 0,
        'critical_count' => 0,
        'overdue_count' => 0,
        'within_sla_count' => 0,
        'items' => [],
    ];
}

function pendencias_operacionais_add_item(array &$module, array $item): void
{
    $module['items'][] = $item;
    $module['total']++;
    $sla = (string) ($item['sla_status'] ?? 'dentro');
    if ($sla === 'critico') {
        $module['critical_count']++;
    } elseif ($sla === 'estourado') {
        $module['overdue_count']++;
    } else {
        $module['within_sla_count']++;
    }
}

function pendencias_operacionais_item_from_checklist(array $row, string $moduleKey, string $sourceLabel, string $actionUrl): array
{
    $sla = pendencias_operacionais_sla_status($row['sla_start_at'] ?? null, $row['due_at'] ?? null);
    $items = $row['items'] ?? [];
    $pendingLabels = [];
    foreach ($items as $item) {
        if ((int) ($item['required'] ?? 0) === 1 && (int) ($item['done'] ?? 0) === 0) {
            $pendingLabels[] = $item['label'];
        }
    }

    return [
        'id' => $moduleKey . '-' . (int) $row['id'],
        'source_type' => $moduleKey,
        'source_id' => (int) $row['id'],
        'title' => (string) ($row['titulo'] ?? $sourceLabel),
        'subtitle' => implode(', ', $pendingLabels),
        'obra_id' => isset($row['obra_id']) ? (int) $row['obra_id'] : null,
        'obra_nome' => (string) ($row['obra_nome'] ?? ''),
        'responsavel_id' => isset($row['responsavel_id']) ? (int) $row['responsavel_id'] : null,
        'responsavel_nome' => (string) ($row['responsavel_nome'] ?? ''),
        'created_at' => $row['created_at'] ?? null,
        'sla_start_at' => $row['sla_start_at'] ?? null,
        'due_at' => $row['due_at'] ?? null,
        'sla_status' => $sla['nivel'],
        'sla_label' => $sla['label'],
        'tempo_decorrido_minutos' => $sla['tempo_decorrido_minutos'],
        'sla_minutos' => $sla['sla_minutos'],
        'action_url' => $actionUrl,
        'checklist_id' => (int) $row['id'],
        'checklist_items' => $items,
    ];
}

/**
 * Fonte única dos cards fotográficos: pendências persistidas no módulo.
 * A filtragem de obras replica o contexto do sidebar sem consultas por obra.
 */
function pendencias_operacionais_append_fotografico(
    mysqli $conn,
    array &$module,
    int $colaboradorId,
    ?int $obraId = null,
    bool $bypassPackageScope = false
): void
{
    if (
        !pendencias_operacionais_table_exists($conn, 'fotografico_pendencia')
        || !pendencias_operacionais_column_exists($conn, 'fotografico_pendencia', 'responsavel_cobranca_id')
        || !pendencias_operacionais_column_exists($conn, 'fotografico_pendencia', 'proxima_cobranca_em')
    ) {
        return;
    }

    // O painel da obra já validou o acesso à obra antes de chegar aqui. Nas
    // outras telas, preservamos a lista pessoal para não transformar o helper
    // em uma consulta global de planos fotográficos.
    $assigneeSql = $bypassPackageScope
        ? '1 = 1'
        : '(pe.responsavel_id = ? OR pe.responsavel_cobranca_id = ?)';

    $obraFilter = $obraId !== null && $obraId > 0 ? ' AND o.idobra = ' . (int) $obraId : '';
    $sql = "SELECT pe.id, pe.plano_id, pe.codigo, pe.titulo, pe.detalhes, pe.status,
                   pe.responsavel_id, pe.responsavel_cobranca_id, pe.criado_em,
                   pe.proxima_cobranca_em, p.status AS plano_status, p.campanha_numero,
                   o.idobra AS obra_id, COALESCE(o.nomenclatura, o.nome_obra) AS obra_nome,
                   r.nome_colaborador AS responsavel_nome,
                   c.nome_colaborador AS responsavel_cobranca_nome
              FROM fotografico_pendencia pe
              JOIN fotografico_plano p ON p.id = pe.plano_id
              JOIN obra o ON o.idobra = p.obra_id
         LEFT JOIN colaborador r ON r.idcolaborador = pe.responsavel_id
         LEFT JOIN colaborador c ON c.idcolaborador = pe.responsavel_cobranca_id
             WHERE pe.status = 'ABERTA'
               AND (o.status_obra = 0 OR o.status_obra IS NULL)
               AND {$assigneeSql}
               {$obraFilter}
             ORDER BY (pe.proxima_cobranca_em IS NULL), pe.proxima_cobranca_em, pe.criado_em
             LIMIT 120";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('[Pendencias/Fotografico] Falha ao preparar consolidação: ' . $conn->error);
        return;
    }
    if (!$bypassPackageScope) {
        $stmt->bind_param('ii', $colaboradorId, $colaboradorId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $due = $row['proxima_cobranca_em'] ?: null;
        $sla = pendencias_operacionais_sla_status($row['criado_em'] ?? null, $due);
        $stage = in_array((string) $row['codigo'], ['EXECUCAO_FOTOGRAFICA', 'CONFERENCIA_FOTOGRAFICO', 'COMPLEMENTO_MATERIAL'], true)
            ? 'execution'
            : 'plan';
        $actionUrl = 'Fotografico/index.php?plano_id=' . (int) $row['plano_id']
            . '&tab=' . $stage . '&pendencia_id=' . (int) $row['id'];
        pendencias_operacionais_add_item($module, [
            'id' => 'fotografico-' . (int) $row['id'],
            'source_type' => 'fotografico',
            'source_id' => (int) $row['id'],
            'plano_id' => (int) $row['plano_id'],
            'tipo' => (string) $row['codigo'],
            'title' => (string) ($row['titulo'] ?: 'Pendência fotográfica'),
            'subtitle' => (string) ($row['detalhes'] ?: ('Plano fotográfico #' . (int) $row['plano_id'])),
            'obra_id' => (int) $row['obra_id'],
            'obra_nome' => (string) $row['obra_nome'],
            'responsavel_id' => $row['responsavel_id'] !== null ? (int) $row['responsavel_id'] : null,
            'responsavel_nome' => (string) ($row['responsavel_nome'] ?? 'Não definido'),
            'responsavel_cobranca_id' => $row['responsavel_cobranca_id'] !== null ? (int) $row['responsavel_cobranca_id'] : null,
            'responsavel_cobranca_nome' => (string) ($row['responsavel_cobranca_nome'] ?? 'Não definido'),
            'created_at' => $row['criado_em'] ?? null,
            'proxima_cobranca_em' => $due,
            'sla_start_at' => $row['criado_em'] ?? null,
            'due_at' => $due,
            'sla_status' => $sla['nivel'],
            'sla_label' => $sla['label'],
            'tempo_decorrido_minutos' => $sla['tempo_decorrido_minutos'],
            'sla_minutos' => $sla['sla_minutos'],
            'status' => (string) $row['status'],
            'action_url' => $actionUrl,
            'url_destino' => $actionUrl,
            'metadata' => [
                'plano_status' => (string) $row['plano_status'],
                'campanha_numero' => (int) $row['campanha_numero'],
                'etapa' => $stage,
            ],
        ]);
    }
    $stmt->close();
}

/** Pendências de aprovação que a Página Principal antes preparava fora do helper. */
function pendencias_operacionais_flow_review_por_obra(mysqli $conn, int $obraId): array
{
    if ($obraId <= 0 || !pendencias_operacionais_table_exists($conn, 'historico_aprovacoes_imagens')) {
        return [];
    }
    $hasSla = pendencias_operacionais_table_exists($conn, 'sla_funcao');
    $slaSelect = $hasSla ? 'sf.limite_horas' : 'NULL';
    $slaJoin = $hasSla ? 'LEFT JOIN sla_funcao sf ON sf.funcao_id = fi.funcao_id' : '';
    $sql = "SELECT fi.idfuncao_imagem, fi.imagem_id, fi.funcao_id, fi.status,
                   f.nome_funcao, ico.imagem_nome, ico.obra_id, o.nomenclatura, o.nome_obra,
                   c.idcolaborador AS responsavel_id, c.nome_colaborador AS responsavel_nome,
                   COALESCE(
                     (SELECT h.data_envio FROM historico_aprovacoes_imagens h WHERE h.funcao_imagem_id = fi.idfuncao_imagem ORDER BY h.data_envio DESC, h.id DESC LIMIT 1),
                     (SELECT a.criado_em FROM arquivo_log a WHERE a.funcao_imagem_id = fi.idfuncao_imagem AND UPPER(a.tipo) = 'PDF' ORDER BY a.criado_em DESC, a.id DESC LIMIT 1)
                   ) AS data_postagem_flowreview,
                   {$slaSelect} AS sla_limite_horas,
                   CASE WHEN fi.funcao_id = 5 THEN 'Pós-produção'
                        WHEN fi.status = 'Aguardando Direção' THEN 'Direção'
                        WHEN fi.funcao_id = 6 THEN 'Revisão'
                        ELSE 'Aprovação' END AS tipo_pendencia
              FROM funcao_imagem fi
              JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
              JOIN obra o ON o.idobra = ico.obra_id
              JOIN funcao f ON f.idfuncao = fi.funcao_id
         LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
              {$slaJoin}
             WHERE ico.obra_id = ?
               AND fi.status IN ('Em aprovação', 'Aguardando Direção')
               AND (
                    EXISTS (SELECT 1 FROM historico_aprovacoes_imagens h WHERE h.funcao_imagem_id = fi.idfuncao_imagem)
                    OR EXISTS (SELECT 1 FROM arquivo_log a WHERE a.funcao_imagem_id = fi.idfuncao_imagem AND UPPER(a.tipo) = 'PDF')
               )
             ORDER BY data_postagem_flowreview ASC, fi.idfuncao_imagem ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('[Pendencias/FlowReview] Falha ao preparar pendências da obra: ' . $conn->error);
        return [];
    }
    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as &$row) {
        $hours = is_numeric($row['sla_limite_horas'] ?? null) ? (float) $row['sla_limite_horas'] : 0.0;
        $postedAt = $row['data_postagem_flowreview'] ?? null;
        $row['prazo_sla'] = $hours > 0 && $postedAt
            ? date('Y-m-d H:i:s', strtotime((string) $postedAt . ' +' . $hours . ' hours'))
            : null;
        $row['tempo_decorrido_minutos'] = $postedAt ? max(0, (int) floor((time() - strtotime((string) $postedAt)) / 60)) : null;
    }
    unset($row);
    return $rows;
}

function pendencias_operacionais_fetch(
    mysqli $conn,
    int $colaboradorId,
    int $nivelAcesso,
    array $pendenciasFlowReview = [],
    array $options = []
): array {
    $obraScopeId = isset($options['obra_id']) ? (int) $options['obra_id'] : 0;
    $showAllForObra = $obraScopeId > 0 && !empty($options['include_all_for_obra']);
    // O endpoint da obra já autorizou o acesso. Neste escopo, cada consulta é
    // limitada à obra solicitada, sem aplicar as filas pessoais da Página Principal.
    $obraFilter = $obraScopeId > 0 ? ' AND o.idobra = ' . $obraScopeId : '';
    $modules = [
        'flow_review'      => pendencias_operacionais_empty_module('flow_review', 'Flow Review', 'Aprovações e revisões de imagens', 'ri-external-link-line', '#7c3aed'),
        'pre_alteracao'    => pendencias_operacionais_empty_module('pre_alteracao', 'Pré-Alteração', 'Triagens e planejamentos', 'ri-survey-line', '#2563eb'),
        'render'           => pendencias_operacionais_empty_module('render', 'Render', 'Aprovações internas de render', 'ri-box-3-line', '#db2777'),
        'projeto'          => pendencias_operacionais_empty_module('projeto', 'Projeto', 'Checklist operacional da obra', 'ri-folder-3-line', '#f59e0b'),
        'imagem'           => pendencias_operacionais_empty_module('imagem', 'Imagem', 'Validações antes da produção', 'ri-image-edit-line', '#16a34a'),
        'flow_block'       => pendencias_operacionais_empty_module('flow_block', 'Flow Block', 'Cobranças de retorno de impedimentos operacionais', 'ri-forbid-2-line', '#dc2626'),
        'links'            => pendencias_operacionais_empty_module('links', 'Links', 'Links pendentes para a obra', 'ri-links-line', '#0f766e'),
        'cobranca_cliente' => pendencias_operacionais_empty_module('cobranca_cliente', 'Cobrança de Cliente', 'Retornos e cobranças de lotes', 'ri-time-line', '#0891b2'),
        'fotografico'      => pendencias_operacionais_empty_module('fotografico', 'Fotográfico', 'Planejamento, execução e conferência fotográfica', 'ri-camera-line', '#2563eb'),
    ];

    pendencias_operacionais_append_fotografico(
        $conn,
        $modules['fotografico'],
        $colaboradorId,
        $obraScopeId ?: null,
        $showAllForObra
    );

    if ($obraScopeId > 0 && empty($pendenciasFlowReview)) {
        $pendenciasFlowReview = pendencias_operacionais_flow_review_por_obra($conn, $obraScopeId);
    }

    foreach ($pendenciasFlowReview as $row) {
        $start = $row['data_postagem_flowreview'] ?? null;
        $due = $row['prazo_sla'] ?? null;
        $sla = pendencias_operacionais_sla_status($start, $due);
        if (!empty($row['sla_limite_horas']) && !empty($row['tempo_decorrido_minutos'])) {
            $elapsed = (int) $row['tempo_decorrido_minutos'];
            $limit = max(1, (int) round(((float) $row['sla_limite_horas']) * 60));
            $sla['tempo_decorrido_minutos'] = $elapsed;
            $sla['sla_minutos'] = $limit;
            if ($elapsed > $limit * 2) {
                $sla['nivel'] = 'critico';
                $sla['label'] = 'SLA Critico';
            } elseif ($elapsed > $limit) {
                $sla['nivel'] = 'estourado';
                $sla['label'] = 'SLA Estourado';
            } else {
                $sla['nivel'] = 'dentro';
                $sla['label'] = 'Dentro do SLA';
            }
        }

        pendencias_operacionais_add_item($modules['flow_review'], [
            'id' => 'flow-review-' . (int) ($row['idfuncao_imagem'] ?? 0),
            'source_type' => 'flow_review',
            'source_id' => (int) ($row['idfuncao_imagem'] ?? 0),
            'title' => (string) ($row['imagem_nome'] ?? 'Imagem'),
            'subtitle' => (string) ($row['tipo_pendencia'] ?? $row['nome_funcao'] ?? 'Aprovacao'),
            'obra_id' => (int) ($row['obra_id'] ?? 0),
            'obra_nome' => (string) ($row['nomenclatura'] ?? $row['nome_obra'] ?? ''),
            'responsavel_id' => (int) ($row['responsavel_id'] ?? 0),
            'responsavel_nome' => (string) ($row['responsavel_nome'] ?? ''),
            'created_at' => $start,
            'sla_start_at' => $start,
            'due_at' => $due,
            'sla_status' => $sla['nivel'],
            'sla_label' => $sla['label'],
            'tempo_decorrido_minutos' => $sla['tempo_decorrido_minutos'],
            'sla_minutos' => $sla['sla_minutos'],
            'comments_count' => (int) ($row['comentarios_ultima_versao'] ?? 0),
            'critical_count' => 0,
            'action_url' => 'FlowReview/index.php',
            'metadata' => $row,
        ]);
    }

    // Uma Issue ativa é uma pendência operacional do responsável. Para Issues
    // abertas a cobrança vem do SLA de primeira tratativa (2h); para pausadas,
    // ela vem do prazo de retorno informado ao pausar.
    $flowBlockAssigneeFilter = $showAllForObra
        ? '1 = 1'
        : "(
                    (i.status = 'RESOLVIDA' AND i.confirmada_em IS NULL AND fi.colaborador_id = ?)
                    OR (
                        i.status <> 'RESOLVIDA'
                        AND (i.responsavel_colaborador_id = ? OR (i.responsavel_colaborador_id IS NULL AND fi.colaborador_id = ?))
                    )
              )";
    if (
        pendencias_operacionais_table_exists($conn, 'flow_issue')
        && pendencias_operacionais_table_exists($conn, 'flow_issue_tipo')
        && pendencias_operacionais_column_exists($conn, 'flow_issue', 'proxima_cobranca_em')
    ) {
        $sqlFlowBlock = "SELECT
                i.id,
                i.codigo,
                i.status,
                i.descricao,
                i.urgencia,
                i.criado_em,
                i.pausada_em,
                i.pausa_motivo,
                i.proxima_cobranca_em,
                i.responsavel_colaborador_id AS responsavel_id,
                t.nome AS tipo_nome,
                fi.idfuncao_imagem,
                fi.colaborador_id AS tarefa_responsavel_id,
                f.nome_funcao,
                ico.imagem_nome,
                o.idobra AS obra_id,
                o.nomenclatura AS obra_nome,
                c.nome_colaborador AS responsavel_nome,
                ct.nome_colaborador AS tarefa_responsavel_nome,
                CASE
                    WHEN i.status = 'PAUSADA' AND i.pausada_em IS NOT NULL THEN i.pausada_em
                    ELSE DATE_SUB(i.proxima_cobranca_em, INTERVAL 2 HOUR)
                END AS sla_inicio
            FROM flow_issue i
            JOIN flow_issue_tipo t ON t.id = i.tipo_id
            JOIN funcao_imagem fi ON fi.idfuncao_imagem = i.funcao_imagem_id
            JOIN funcao f ON f.idfuncao = fi.funcao_id
            JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
            JOIN obra o ON o.idobra = ico.obra_id
            LEFT JOIN colaborador c ON c.idcolaborador = i.responsavel_colaborador_id
            LEFT JOIN colaborador ct ON ct.idcolaborador = fi.colaborador_id
            WHERE i.bloqueante = 1
              AND (
                    i.status IN ('ABERTA', 'AGUARDANDO_ACAO', 'PAUSADA')
                    OR (i.status = 'RESOLVIDA' AND i.confirmada_em IS NULL)
              )
              AND (i.proxima_cobranca_em IS NOT NULL OR (i.status = 'RESOLVIDA' AND i.confirmada_em IS NULL))
              AND {$flowBlockAssigneeFilter}
              AND (o.status_obra = 0 OR o.status_obra IS NULL)
              {$obraFilter}
            ORDER BY i.proxima_cobranca_em ASC, i.id ASC
            LIMIT 80";
        $stmtFlowBlock = $conn->prepare($sqlFlowBlock);
        if ($stmtFlowBlock) {
            if (!$showAllForObra) {
                $stmtFlowBlock->bind_param('iii', $colaboradorId, $colaboradorId, $colaboradorId);
            }
            $stmtFlowBlock->execute();
            $resultFlowBlock = $stmtFlowBlock->get_result();
            while ($row = $resultFlowBlock->fetch_assoc()) {
                $awaitingConfirmation = ($row['status'] ?? '') === 'RESOLVIDA';
                $start = $awaitingConfirmation ? ($row['criado_em'] ?? null) : ($row['sla_inicio'] ?? $row['criado_em'] ?? null);
                $due = $awaitingConfirmation ? null : ($row['proxima_cobranca_em'] ?? null);
                $sla = pendencias_operacionais_sla_status($start, $due);
                $isPaused = ($row['status'] ?? '') === 'PAUSADA';
                $subtitle = trim(
                    ($awaitingConfirmation ? 'Issue resolvida · Confirmar resposta' : (string) ($row['tipo_nome'] ?? 'Impedimento'))
                        . ' · '
                        . (string) ($row['nome_funcao'] ?? '')
                        . ($isPaused && !empty($row['pausa_motivo']) ? ' · Pausada: ' . $row['pausa_motivo'] : '')
                );
                pendencias_operacionais_add_item($modules['flow_block'], [
                    'id' => 'flow-block-' . (int) $row['id'],
                    'source_type' => 'flow_block',
                    'source_id' => (int) $row['id'],
                    'title' => trim((string) ($row['codigo'] ?? 'Issue') . ' · ' . (string) ($row['imagem_nome'] ?? 'Tarefa')),
                    'subtitle' => $subtitle,
                    'obra_id' => (int) ($row['obra_id'] ?? 0),
                    'obra_nome' => (string) ($row['obra_nome'] ?? ''),
                    'responsavel_id' => (int) ($awaitingConfirmation ? ($row['tarefa_responsavel_id'] ?? 0) : ($row['responsavel_id'] ?? 0)),
                    'responsavel_nome' => (string) ($awaitingConfirmation ? ($row['tarefa_responsavel_nome'] ?? 'Não definido') : ($row['responsavel_nome'] ?? 'Não definido')),
                    'created_at' => $row['criado_em'] ?? null,
                    'sla_start_at' => $start,
                    'due_at' => $due,
                    'sla_status' => $sla['nivel'],
                    'sla_label' => $sla['label'],
                    'tempo_decorrido_minutos' => $sla['tempo_decorrido_minutos'],
                    'sla_minutos' => $sla['sla_minutos'],
                    'comments_count' => 0,
                    'critical_count' => 0,
                    'action_url' => 'FlowBlock/issue.php?id=' . (int) $row['id'],
                    'metadata' => [
                        'issue_id' => (int) $row['id'],
                        'codigo' => (string) ($row['codigo'] ?? ''),
                        'tipo' => (string) ($row['tipo_nome'] ?? ''),
                        'status' => (string) ($row['status'] ?? ''),
                        'urgencia' => (string) ($row['urgencia'] ?? ''),
                        'pausada' => $isPaused,
                    ],
                ]);
            }
            $stmtFlowBlock->close();
        }
    }

    if (
        ($showAllForObra || pendencias_operacionais_user_in($colaboradorId, [PENDENCIAS_PEDRO_ID, PENDENCIAS_ANDRE_ID, PENDENCIAS_IMAGEM_RESPONSAVEL_ID, PENDENCIAS_ANDRE_ID]))
        && pendencias_operacionais_table_exists($conn, 'pre_alt_lote')
    ) {
        $sql = "SELECT
                    l.id,
                    l.obra_id,
                    l.status,
                    l.prazo,
                    l.data_finalizacao_cliente,
                    l.created_at,
                    l.updated_at,
                    COALESCE(l.responsavel_id, l.created_by) AS responsavel_id,
                    o.nomenclatura AS obra_nome,
                    c.nome_colaborador AS responsavel_nome,
                    si.nome_status,
                    COUNT(i.id) AS total_itens,
                    SUM(CASE WHEN i.resultado IS NULL OR (i.resultado = 'ALTERACAO' AND i.nivel_complexidade IS NULL) THEN 1 ELSE 0 END) AS pendentes
                FROM pre_alt_lote l
                JOIN obra o ON o.idobra = l.obra_id
                LEFT JOIN status_imagem si ON si.idstatus = l.status_id
                LEFT JOIN pre_alt_itens i ON i.pre_alt_lote_id = l.id
                LEFT JOIN colaborador c ON c.idcolaborador = COALESCE(l.responsavel_id, l.created_by)
                WHERE o.status_obra = 0
                  AND l.status NOT IN ('PLANEJADO', 'CANCELADO')
                  AND COALESCE(l.responsavel_id, l.created_by) IS NOT NULL
                  {$obraFilter}
                GROUP BY l.id, l.obra_id, l.status, l.prazo, l.data_finalizacao_cliente, l.created_at, l.updated_at, l.responsavel_id, l.created_by, o.nomenclatura, c.nome_colaborador, si.nome_status
                ORDER BY l.prazo IS NULL ASC, l.prazo ASC, l.created_at ASC
                LIMIT 80";

        if ($res = $conn->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $start = $row['created_at'] ?? null;
                $due = !empty($row['prazo'])
                    ? $row['prazo'] . ' 18:00:00'
                    : (!empty($row['data_finalizacao_cliente'])
                        ? date('Y-m-d 18:00:00', strtotime($row['data_finalizacao_cliente'] . ' +1 day'))
                        : ($start ? date('Y-m-d H:i:s', strtotime($start . ' +24 hours')) : null));
                $sla = pendencias_operacionais_sla_status($start, $due);
                pendencias_operacionais_add_item($modules['pre_alteracao'], [
                    'id' => 'pre-alt-' . (int) $row['id'],
                    'source_type' => 'pre_alteracao',
                    'source_id' => (int) $row['id'],
                    'title' => (string) ($row['obra_nome'] ?? 'Lote de triagem'),
                    'subtitle' => trim(($row['nome_status'] ?? '') . ' - ' . ($row['status'] ?? ''), ' -'),
                    'obra_id' => (int) $row['obra_id'],
                    'obra_nome' => (string) ($row['obra_nome'] ?? ''),
                    'responsavel_id' => (int) ($row['responsavel_id'] ?? 0),
                    'responsavel_nome' => (string) ($row['responsavel_nome'] ?? ''),
                    'created_at' => $start,
                    'sla_start_at' => $start,
                    'due_at' => $due,
                    'sla_status' => $sla['nivel'],
                    'sla_label' => $sla['label'],
                    'tempo_decorrido_minutos' => $sla['tempo_decorrido_minutos'],
                    'sla_minutos' => $sla['sla_minutos'],
                    'comments_count' => 0,
                    'critical_count' => 0,
                    'action_url' => 'PreAlteracao/index.php',
                    'metadata' => [
                        'total_itens' => (int) ($row['total_itens'] ?? 0),
                        'pendentes' => (int) ($row['pendentes'] ?? 0),
                    ],
                ]);
            }
        }
    }

    if (pendencias_operacionais_table_exists($conn, 'render_alta')) {
        $whereUser = $showAllForObra || in_array((int) $colaboradorId, [
            (int) PENDENCIAS_PEDRO_ID,
            (int) PENDENCIAS_ANDRE_ID
        ], true)
            ? '1=1'
            : 'r.responsavel_id = ' . (int) $colaboradorId;
        $sql = "SELECT
                    r.idrender_alta,
                    r.imagem_id,
                    r.status_id,
                    r.data,
                    r.responsavel_id,
                    i.imagem_nome,
                    o.idobra AS obra_id,
                    o.nomenclatura AS obra_nome,
                    c.nome_colaborador AS responsavel_nome,
                    s.nome_status
                FROM render_alta r
                LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = r.imagem_id
                LEFT JOIN obra o ON o.idobra = i.obra_id
                LEFT JOIN colaborador c ON c.idcolaborador = r.responsavel_id
                LEFT JOIN status_imagem s ON s.idstatus = r.status_id
                WHERE r.status = 'Em aprovação'
                  AND r.responsavel_id IS NOT NULL
                  AND (o.status_obra = 0 OR o.status_obra IS NULL)
                  AND {$whereUser}
                  {$obraFilter}
                ORDER BY r.data ASC
                LIMIT 80";
        if ($res = $conn->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $start = $row['data'] ?? null;
                $due = $start ? date('Y-m-d H:i:s', strtotime($start . ' +1 hour')) : null;
                $sla = pendencias_operacionais_sla_status($start, $due);
                pendencias_operacionais_add_item($modules['render'], [
                    'id' => 'render-' . (int) $row['idrender_alta'],
                    'source_type' => 'render',
                    'source_id' => (int) $row['idrender_alta'],
                    'title' => (string) ($row['imagem_nome'] ?? 'Render em aprovacao'),
                    'subtitle' => (string) ($row['nome_status'] ?? 'Aprovacao interna'),
                    'obra_id' => (int) ($row['obra_id'] ?? 0),
                    'obra_nome' => (string) ($row['obra_nome'] ?? ''),
                    'responsavel_id' => (int) ($row['responsavel_id'] ?? 0),
                    'responsavel_nome' => (string) ($row['responsavel_nome'] ?? ''),
                    'created_at' => $start,
                    'sla_start_at' => $start,
                    'due_at' => $due,
                    'sla_status' => $sla['nivel'],
                    'sla_label' => $sla['label'],
                    'tempo_decorrido_minutos' => $sla['tempo_decorrido_minutos'],
                    'sla_minutos' => $sla['sla_minutos'],
                    'comments_count' => 0,
                    'critical_count' => 0,
                    'action_url' => 'Render/index.php',
                ]);
            }
        }
    }

    pendencias_operacionais_ensure_schema($conn);

    if ($showAllForObra || pendencias_operacionais_user_in($colaboradorId, [
        PENDENCIAS_PEDRO_ID,
        PENDENCIAS_ANDRE_ID,
        PENDENCIAS_IMAGEM_RESPONSAVEL_ID,
    ])) {
        foreach (pendencias_links_obra_listar_abertas($conn) as $link) {
            if ($obraScopeId > 0 && (int) ($link['obra_id'] ?? 0) !== $obraScopeId) {
                continue;
            }
            pendencias_operacionais_add_item($modules['links'], [
                'id' => 'link-' . (int) $link['id'],
                'source_type' => 'links',
                'source_id' => (int) $link['id'],
                'title' => (string) $link['obra_nome'],
                'subtitle' => 'Cadastrar ' . $link['link_label'] . ' na tela da obra.',
                'obra_id' => (int) $link['obra_id'],
                'obra_nome' => (string) $link['obra_nome'],
                'responsavel_id' => null,
                'responsavel_nome' => 'Operacional',
                'created_at' => $link['criada_em'],
                'sla_start_at' => null,
                'due_at' => null,
                'sla_status' => 'dentro',
                'sla_label' => 'Pendente',
                'tempo_decorrido_minutos' => null,
                'sla_minutos' => null,
                'comments_count' => 0,
                'critical_count' => 0,
                'action_url' => '',
                'metadata' => [
                    'link_key' => $link['tipo_link'],
                    'link_label' => $link['link_label'],
                    'origem' => $link['origem'],
                ],
            ]);
        }
    }

    if ($showAllForObra || pendencias_operacionais_user_in($colaboradorId, [PENDENCIAS_PEDRO_ID, PENDENCIAS_ANDRE_ID, PENDENCIAS_IMAGEM_RESPONSAVEL_ID])) {
        $sqlCompletedProjects = "SELECT
                    o.idobra,
                    MAX(ae.colaborador_id) AS responsavel_id
                FROM obra o
                JOIN acompanhamento_email ae ON ae.obra_id = o.idobra
                LEFT JOIN checklist_operacional co
                  ON co.module_key = 'projeto'
                 AND co.entity_type = 'obra'
                 AND co.entity_id = o.idobra
                WHERE o.status_obra = 0
                  AND ae.tipo = 'ONBOARDING_COMPLETED'
                  AND co.id IS NULL
                  {$obraFilter}
                GROUP BY o.idobra
                LIMIT 50";
        if ($resCompleted = $conn->query($sqlCompletedProjects)) {
            while ($rowCompleted = $resCompleted->fetch_assoc()) {
                pendencias_operacionais_ensure_project_checklist(
                    $conn,
                    (int) $rowCompleted['idobra'],
                    isset($rowCompleted['responsavel_id']) ? (int) $rowCompleted['responsavel_id'] : null
                );
            }
        }

        $sql = "SELECT
                    co.*,
                    o.nomenclatura AS obra_nome,
                    c.nome_colaborador AS responsavel_nome
                FROM checklist_operacional co
                JOIN obra o ON o.idobra = co.obra_id
                LEFT JOIN colaborador c ON c.idcolaborador = co.responsavel_id
                WHERE co.module_key = 'projeto'
                  AND co.status = 'aberto'
                  AND co.responsavel_id IS NOT NULL
                  {$obraFilter}
                ORDER BY co.due_at ASC
                LIMIT 80";
        if ($res = $conn->query($sql)) {
            $rows = [];
            $checklistIds = [];
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
                $checklistIds[] = (int) $row['id'];
            }
            $itemsByChecklist = pendencias_operacionais_fetch_checklist_items_map($conn, $checklistIds);
            foreach ($rows as $row) {
                $row['titulo'] = 'Projeto OK - ' . ($row['obra_nome'] ?? 'Obra');
                $row['items'] = $itemsByChecklist[(int) $row['id']] ?? [];
                pendencias_operacionais_add_item(
                    $modules['projeto'],
                    pendencias_operacionais_item_from_checklist($row, 'projeto', 'Checklist do projeto', 'Dashboard/obra.php')
                );
            }
        }
    }

    if ($showAllForObra || pendencias_operacionais_user_in($colaboradorId, [PENDENCIAS_PEDRO_ID, PENDENCIAS_ANDRE_ID, PENDENCIAS_IMAGEM_RESPONSAVEL_ID])) {
        $sqlQueue = "SELECT
                        co.*,
                        ico.imagem_nome,
                        o.nomenclatura AS obra_nome,
                        c.nome_colaborador AS responsavel_nome
                    FROM checklist_operacional co
                    JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = co.entity_id
                    JOIN obra o ON o.idobra = ico.obra_id
                    LEFT JOIN colaborador c ON c.idcolaborador = co.responsavel_id
                    WHERE co.module_key = 'imagem'
                      AND co.entity_type = 'imagem'
                      AND co.status = 'aberto'
                      AND o.status_obra = 0
                      AND ico.substatus_id = 2
                      AND ico.status_id IN (1, 2)
                      {$obraFilter}
                      AND EXISTS (
                          SELECT 1
                            FROM checklist_operacional_item coi
                           WHERE coi.checklist_id = co.id
                             AND coi.required = 1
                             AND coi.done = 0
                           LIMIT 1
                      )
                    ORDER BY co.due_at ASC, co.id ASC
                    LIMIT 80";
        if ($res = $conn->query($sqlQueue)) {
            $rows = [];
            $checklistIds = [];
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
                $checklistIds[] = (int) $row['id'];
            }
            $itemsByChecklist = pendencias_operacionais_fetch_checklist_items_map($conn, $checklistIds);
            foreach ($rows as $checklist) {
                $checklist['titulo'] = (string) ($checklist['imagem_nome'] ?? 'Imagem em TO-DO');
                $checklist['obra_nome'] = (string) ($checklist['obra_nome'] ?? '');
                $checklist['responsavel_nome'] = (string) ($checklist['responsavel_nome'] ?? 'Nicolle');
                $checklist['items'] = $itemsByChecklist[(int) $checklist['id']] ?? [];
                pendencias_operacionais_add_item(
                    $modules['imagem'],
                    pendencias_operacionais_item_from_checklist($checklist, 'imagem', 'Checklist da imagem', 'PaginaPrincipal')
                );
            }
        }
    }

    if (
        ($showAllForObra || in_array((int) $colaboradorId, [
            (int) PENDENCIAS_PEDRO_ID,
            (int) PENDENCIAS_ANDRE_ID
        ], true))
        && pendencias_operacionais_table_exists($conn, 'cobranca_review')
    ) {
        $sql = "SELECT
                    cr.id,
                    cr.review_batch_id,
                    cr.due_at,
                    cr.status,
                    cr.overdue_days,
                    cr.notification_count,
                    cr.last_notification_at,
                    cr.snooze_until,
                    cr.created_at,
                    rb.entrega_id,
                    e.obra_id,
                    o.nomenclatura AS obra_nome,
                    s.nome_status,
                    COUNT(rbi.id) AS total_items
                FROM cobranca_review cr
                JOIN review_batch rb ON rb.id = cr.review_batch_id
                JOIN entregas e ON e.id = rb.entrega_id
                JOIN obra o ON o.idobra = e.obra_id
                LEFT JOIN status_imagem s ON s.idstatus = e.status_id
                LEFT JOIN review_batch_items rbi ON rbi.review_batch_id = rb.id
                WHERE cr.status = 'OVERDUE'
                  AND cr.resolved_at IS NULL
                  AND rb.status NOT IN ('RESOLVED', 'IGNORED')
                  AND o.status_obra = 0
                  {$obraFilter}
                GROUP BY cr.id, cr.review_batch_id, cr.due_at, cr.status, cr.overdue_days, cr.notification_count, cr.last_notification_at, cr.snooze_until, cr.created_at, rb.entrega_id, e.obra_id, o.nomenclatura, s.nome_status
                ORDER BY cr.overdue_days DESC, cr.due_at ASC
                LIMIT 80";
        if ($res = $conn->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $start = $row['created_at'] ?? null;
                $due = $row['due_at'] ?? null;
                $sla = pendencias_operacionais_sla_status($start, $due);
                if (($row['status'] ?? '') === 'OVERDUE' && $sla['nivel'] === 'dentro') {
                    $sla['nivel'] = 'estourado';
                    $sla['label'] = 'SLA Estourado';
                }
                if ((int) ($row['overdue_days'] ?? 0) >= 2) {
                    $sla['nivel'] = 'critico';
                    $sla['label'] = 'SLA Critico';
                }
                pendencias_operacionais_add_item($modules['cobranca_cliente'], [
                    'id' => 'cobranca-' . (int) $row['id'],
                    'source_type' => 'cobranca_cliente',
                    'source_id' => (int) $row['review_batch_id'],
                    'title' => (string) ($row['obra_nome'] ?? 'Lote sem retorno'),
                    'subtitle' => 'Lote ' . (int) $row['review_batch_id'] . ' - ' . (string) ($row['nome_status'] ?? 'Entrega'),
                    'obra_id' => (int) ($row['obra_id'] ?? 0),
                    'obra_nome' => (string) ($row['obra_nome'] ?? ''),
                    'responsavel_id' => $colaboradorId,
                    'responsavel_nome' => 'Operacional',
                    'created_at' => $start,
                    'sla_start_at' => $start,
                    'due_at' => $due,
                    'sla_status' => $sla['nivel'],
                    'sla_label' => $sla['label'],
                    'tempo_decorrido_minutos' => $sla['tempo_decorrido_minutos'],
                    'sla_minutos' => $sla['sla_minutos'],
                    'comments_count' => (int) ($row['notification_count'] ?? 0),
                    'critical_count' => 0,
                    'action_url' => 'Entregas/index.php',
                    'metadata' => [
                        'status' => $row['status'],
                        'overdue_days' => (int) ($row['overdue_days'] ?? 0),
                        'total_items' => (int) ($row['total_items'] ?? 0),
                    ],
                ]);
            }
        }
    }

    $result = array_values(array_filter($modules, static function (array $module): bool {
        return (int) ($module['total'] ?? 0) > 0;
    }));

    usort($result, static function (array $a, array $b): int {
        if ((int) $b['critical_count'] !== (int) $a['critical_count']) {
            return (int) $b['critical_count'] <=> (int) $a['critical_count'];
        }
        return (int) $b['total'] <=> (int) $a['total'];
    });

    return $result;
}

function pendencias_operacionais_image_checklist_for_card(mysqli $conn, int $imagemId): ?array
{
    if ($imagemId <= 0 || !pendencias_operacionais_table_exists($conn, 'checklist_operacional')) {
        return null;
    }

    $row = pendencias_operacionais_find_checklist($conn, 'imagem', 'imagem', $imagemId);
    if (!$row || ($row['status'] ?? '') !== 'aberto') {
        return null;
    }

    $items = pendencias_operacionais_fetch_checklist_items($conn, (int) $row['id']);
    $pending = array_values(array_filter($items, static function (array $item): bool {
        return (int) ($item['required'] ?? 0) === 1 && (int) ($item['done'] ?? 0) === 0;
    }));

    if (empty($pending)) {
        pendencias_operacionais_update_checklist_status($conn, (int) $row['id']);
        return null;
    }

    return [
        'checklist_id' => (int) $row['id'],
        'items' => $items,
    ];
}
