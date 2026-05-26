<?php

require_once __DIR__ . '/onboarding_helpers.php';

function dashboard_table_exists(mysqli $conn, string $table): bool
{
    static $cache = [];
    $cacheKey = $table;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $sql = "SELECT 1
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    $cache[$cacheKey] = $exists;

    return $exists;
}

function dashboard_planning_column_exists(mysqli $conn, string $table, string $column): bool
{
    static $cache = [];
    $cacheKey = $table . ':' . $column;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $cache[$cacheKey] = dashboard_table_has_column($conn, $table, $column);

    return $cache[$cacheKey];
}

function dashboard_planning_tables_ready(mysqli $conn): bool
{
    if (
        !dashboard_table_exists($conn, 'imagem_funcao_planejada')
        || !dashboard_table_exists($conn, 'imagem_funcao_template')
        || !dashboard_table_exists($conn, 'imagem_funcao_template_item')
    ) {
        return false;
    }

    $requiredColumns = [
        'imagem_funcao_planejada' => [
            'template_id',
            'template_item_id',
            'template_versao',
            'funcao_imagem_id',
            'ordem',
            'obrigatoria',
            'status',
            'origem',
            'responsavel_sugerido_id',
        ],
        'imagem_funcao_template' => [
            'ativo',
            'versao',
        ],
        'imagem_funcao_template_item' => [
            'ordem',
            'obrigatoria',
            'ativo',
        ],
    ];

    foreach ($requiredColumns as $table => $columns) {
        foreach ($columns as $column) {
            if (!dashboard_planning_column_exists($conn, $table, $column)) {
                return false;
            }
        }
    }

    return true;
}

function dashboard_planning_normalize_text(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    if ($value === '') {
        return '';
    }

    if (function_exists('transliterator_transliterate')) {
        $normalized = @transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $value);
        if (is_string($normalized) && $normalized !== '') {
            return $normalized;
        }
    }

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false && $converted !== '') {
            return mb_strtolower($converted, 'UTF-8');
        }
    }

    return $value;
}

function dashboard_planned_status_from_execution(?string $executionStatus): string
{
    $normalized = dashboard_planning_normalize_text((string) $executionStatus);
    if (in_array($normalized, ['cancelado', 'nao se aplica'], true)) {
        return 'CANCELADO';
    }

    return 'INICIADO';
}

function dashboard_planning_type_rank(?string $imageType): int
{
    static $order = [
        'Fachada' => 10,
        'Imagem Interna' => 20,
        'Unidade' => 30,
        'Imagem Externa' => 40,
        'Planta Humanizada' => 50,
    ];

    $type = trim((string) $imageType);
    return $order[$type] ?? 999;
}

function dashboard_fetch_image_snapshot(mysqli $conn, int $imageId): ?array
{
    $stmt = $conn->prepare(
        'SELECT idimagens_cliente_obra AS imagem_id, imagem_nome, tipo_imagem
         FROM imagens_cliente_obra
         WHERE idimagens_cliente_obra = ?
         LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $imageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function dashboard_find_active_function_template(mysqli $conn, string $imageType): ?array
{
    if (!dashboard_table_exists($conn, 'imagem_funcao_template')) {
        return null;
    }

    $normalizedType = trim($imageType);
    if ($normalizedType === '') {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT idimagem_funcao_template, versao
         FROM imagem_funcao_template
         WHERE LOWER(TRIM(tipo_imagem)) = LOWER(TRIM(?))
           AND ativo = 1
         ORDER BY versao DESC, idimagem_funcao_template DESC
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $normalizedType);
    $stmt->execute();
    $result = $stmt->get_result();
    $template = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$template) {
        return null;
    }

    return [
        'id' => (int) ($template['idimagem_funcao_template'] ?? 0),
        'versao' => (int) ($template['versao'] ?? 1),
    ];
}

function dashboard_find_template_item_for_image(mysqli $conn, int $imageId, int $functionId): ?array
{
    if ($imageId <= 0 || $functionId <= 0 || !dashboard_planning_tables_ready($conn)) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT
            template.idimagem_funcao_template AS template_id,
            template.versao,
            item.idimagem_funcao_template_item AS template_item_id,
            item.ordem,
            item.obrigatoria,
            item.responsavel_padrao_id,
            ico.tipo_imagem
         FROM imagens_cliente_obra ico
         INNER JOIN imagem_funcao_template template
            ON LOWER(TRIM(template.tipo_imagem)) = LOWER(TRIM(ico.tipo_imagem))
           AND template.ativo = 1
         INNER JOIN imagem_funcao_template_item item
            ON item.template_id = template.idimagem_funcao_template
           AND item.funcao_id = ?
           AND item.ativo = 1
         WHERE ico.idimagens_cliente_obra = ?
         ORDER BY template.versao DESC, template.idimagem_funcao_template DESC
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ii', $functionId, $imageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'template_id' => (int) ($row['template_id'] ?? 0),
        'template_item_id' => (int) ($row['template_item_id'] ?? 0),
        'versao' => (int) ($row['versao'] ?? 1),
        'ordem' => isset($row['ordem']) ? (int) $row['ordem'] : 1,
        'obrigatoria' => isset($row['obrigatoria']) ? (int) $row['obrigatoria'] : 1,
        'responsavel_padrao_id' => isset($row['responsavel_padrao_id']) ? (int) $row['responsavel_padrao_id'] : null,
        'tipo_imagem' => (string) ($row['tipo_imagem'] ?? ''),
    ];
}

function dashboard_log_planned_function_event(
    mysqli $conn,
    ?int $plannedId,
    int $imageId,
    int $functionId,
    string $action,
    array $payload = [],
    ?int $actorColaboradorId = null
): void {
    if (!dashboard_table_exists($conn, 'imagem_funcao_planejada_historico')) {
        return;
    }

    $payloadJson = !empty($payload)
        ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : null;

    $stmt = $conn->prepare(
        'INSERT INTO imagem_funcao_planejada_historico (
            imagem_funcao_planejada_id,
            imagem_id,
            funcao_id,
            acao,
            payload,
            responsavel_id
        ) VALUES (?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('iiissi', $plannedId, $imageId, $functionId, $action, $payloadJson, $actorColaboradorId);
    $stmt->execute();
    $stmt->close();
}

function dashboard_insert_planned_functions_for_image(mysqli $conn, int $imageId, string $imageType): array
{
    if ($imageId <= 0) {
        return ['success' => false, 'skipped' => true, 'inserted' => 0, 'reason' => 'invalid_image'];
    }

    $normalizedType = trim($imageType);
    if ($normalizedType === '' || strcasecmp($normalizedType, 'Desconhecido') === 0) {
        return ['success' => true, 'skipped' => true, 'inserted' => 0, 'reason' => 'unsupported_type'];
    }

    if (!dashboard_planning_tables_ready($conn)) {
        return ['success' => true, 'skipped' => true, 'inserted' => 0, 'reason' => 'planning_tables_missing'];
    }

    $template = dashboard_find_active_function_template($conn, $normalizedType);
    if (!$template || ($template['id'] ?? 0) <= 0) {
        return ['success' => true, 'skipped' => true, 'inserted' => 0, 'reason' => 'template_not_found'];
    }

    $columns = ['imagem_id', 'funcao_id', 'status', 'origem'];
    $selects = ['?', 'item.funcao_id', "'TODO'", "'PLANEJAMENTO'"];

    if (dashboard_planning_column_exists($conn, 'imagem_funcao_planejada', 'template_id')) {
        $columns[] = 'template_id';
        $selects[] = 'template.idimagem_funcao_template';
    }
    if (dashboard_planning_column_exists($conn, 'imagem_funcao_planejada', 'template_item_id')) {
        $columns[] = 'template_item_id';
        $selects[] = 'item.idimagem_funcao_template_item';
    }
    if (dashboard_planning_column_exists($conn, 'imagem_funcao_planejada', 'template_versao')) {
        $columns[] = 'template_versao';
        $selects[] = 'template.versao';
    }
    if (dashboard_planning_column_exists($conn, 'imagem_funcao_planejada', 'ordem')) {
        $columns[] = 'ordem';
        $selects[] = 'item.ordem';
    }
    if (dashboard_planning_column_exists($conn, 'imagem_funcao_planejada', 'obrigatoria')) {
        $columns[] = 'obrigatoria';
        $selects[] = 'item.obrigatoria';
    }
    if (
        dashboard_planning_column_exists($conn, 'imagem_funcao_planejada', 'responsavel_sugerido_id')
        && dashboard_planning_column_exists($conn, 'imagem_funcao_template_item', 'responsavel_padrao_id')
    ) {
        $columns[] = 'responsavel_sugerido_id';
        $selects[] = 'item.responsavel_padrao_id';
    }

    $sql = 'INSERT INTO imagem_funcao_planejada (' . implode(', ', $columns) . ') '
        . 'SELECT ' . implode(', ', $selects) . ' '
        . 'FROM imagem_funcao_template template '
        . 'INNER JOIN imagem_funcao_template_item item '
        . '    ON item.template_id = template.idimagem_funcao_template '
        . 'LEFT JOIN imagem_funcao_planejada existing '
        . '    ON existing.imagem_id = ? '
        . '   AND existing.funcao_id = item.funcao_id '
        . 'WHERE template.idimagem_funcao_template = ? '
        . '  AND item.ativo = 1 '
        . '  AND existing.idimagem_funcao_planejada IS NULL';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [
            'success' => false,
            'skipped' => false,
            'inserted' => 0,
            'reason' => 'prepare_failed',
            'message' => $conn->error,
        ];
    }

    $templateId = (int) $template['id'];
    $stmt->bind_param('iii', $imageId, $imageId, $templateId);
    if (!$stmt->execute()) {
        $message = $stmt->error;
        $stmt->close();
        return [
            'success' => false,
            'skipped' => false,
            'inserted' => 0,
            'reason' => 'execute_failed',
            'message' => $message,
        ];
    }

    $inserted = (int) $stmt->affected_rows;
    $stmt->close();

    return [
        'success' => true,
        'skipped' => false,
        'inserted' => $inserted,
        'reason' => 'applied',
        'template_id' => $templateId,
        'template_versao' => (int) ($template['versao'] ?? 1),
    ];
}

function dashboard_fetch_planned_function_row(mysqli $conn, int $imageId, int $functionId): ?array
{
    if (!dashboard_table_exists($conn, 'imagem_funcao_planejada')) {
        return null;
    }

    $stmt = $conn->prepare(
        'SELECT *
         FROM imagem_funcao_planejada
         WHERE imagem_id = ?
           AND funcao_id = ?
         LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ii', $imageId, $functionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function dashboard_upsert_planned_function(
    mysqli $conn,
    int $imageId,
    int $functionId,
    array $payload = [],
    ?int $actorColaboradorId = null
): array {
    if (!dashboard_planning_tables_ready($conn)) {
        return ['success' => false, 'message' => 'Tabelas de planejamento não estão disponíveis.'];
    }

    $image = dashboard_fetch_image_snapshot($conn, $imageId);
    if (!$image) {
        return ['success' => false, 'message' => 'Imagem não encontrada.'];
    }

    $existing = dashboard_fetch_planned_function_row($conn, $imageId, $functionId);
    $templateItem = dashboard_find_template_item_for_image($conn, $imageId, $functionId);

    $hasExecutionLink = !empty($existing['funcao_imagem_id']);
    $status = $hasExecutionLink
        ? (string) ($existing['status'] ?? 'INICIADO')
        : ((string) ($payload['status'] ?? (($existing && ($existing['status'] ?? '') !== 'CANCELADO') ? $existing['status'] : 'TODO')));
    if (!in_array($status, ['TODO', 'INICIADO', 'CANCELADO'], true)) {
        $status = $hasExecutionLink ? 'INICIADO' : 'TODO';
    }
    if (($existing['status'] ?? '') === 'CANCELADO' && !$hasExecutionLink && $status === 'CANCELADO') {
        $status = 'TODO';
    }

    $ordem = isset($payload['ordem']) && $payload['ordem'] !== ''
        ? max(1, (int) $payload['ordem'])
        : ($existing ? (int) ($existing['ordem'] ?? 1) : (int) ($templateItem['ordem'] ?? 1));
    $obrigatoria = array_key_exists('obrigatoria', $payload)
        ? ((int) !empty($payload['obrigatoria']))
        : ($existing ? (int) ($existing['obrigatoria'] ?? 1) : (int) ($templateItem['obrigatoria'] ?? 1));
    $responsavelSugeridoId = array_key_exists('responsavel_sugerido_id', $payload)
        ? (($payload['responsavel_sugerido_id'] === '' || $payload['responsavel_sugerido_id'] === null)
            ? null
            : (int) $payload['responsavel_sugerido_id'])
        : ($existing && !empty($existing['responsavel_sugerido_id'])
            ? (int) $existing['responsavel_sugerido_id']
            : (!empty($templateItem['responsavel_padrao_id']) ? (int) $templateItem['responsavel_padrao_id'] : null));

    $origem = $existing
        ? (string) ($existing['origem'] ?? 'MANUAL')
        : (!empty($templateItem) ? 'PLANEJAMENTO' : 'MANUAL');

    $data = [
        'ordem' => $ordem,
        'obrigatoria' => $obrigatoria,
        'status' => $status,
        'origem' => $origem,
    ];

    if (dashboard_planning_column_exists($conn, 'imagem_funcao_planejada', 'template_id')) {
        $data['template_id'] = !empty($templateItem['template_id']) ? (int) $templateItem['template_id'] : null;
    }
    if (dashboard_planning_column_exists($conn, 'imagem_funcao_planejada', 'template_item_id')) {
        $data['template_item_id'] = !empty($templateItem['template_item_id']) ? (int) $templateItem['template_item_id'] : null;
    }
    if (dashboard_planning_column_exists($conn, 'imagem_funcao_planejada', 'template_versao')) {
        $data['template_versao'] = !empty($templateItem['versao']) ? (int) $templateItem['versao'] : null;
    }
    if (dashboard_planning_column_exists($conn, 'imagem_funcao_planejada', 'responsavel_sugerido_id')) {
        $data['responsavel_sugerido_id'] = $responsavelSugeridoId;
    }

    if ($existing) {
        $setParts = [];
        $types = '';
        $values = [];
        foreach ($data as $column => $value) {
            $setParts[] = $column . ' = ?';
            if (is_int($value) || $value === null) {
                $types .= 'i';
                $values[] = $value;
            } else {
                $types .= 's';
                $values[] = $value;
            }
        }
        $types .= 'i';
        $values[] = (int) $existing['idimagem_funcao_planejada'];

        $sql = 'UPDATE imagem_funcao_planejada SET ' . implode(', ', $setParts) . ' WHERE idimagem_funcao_planejada = ?';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['success' => false, 'message' => $conn->error];
        }

        $stmt->bind_param($types, ...$values);
        if (!$stmt->execute()) {
            $message = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => $message];
        }
        $stmt->close();

        dashboard_log_planned_function_event(
            $conn,
            (int) $existing['idimagem_funcao_planejada'],
            $imageId,
            $functionId,
            'UPDATE_CONFIG',
            [
                'imagem_nome' => $image['imagem_nome'] ?? '',
                'tipo_imagem' => $image['tipo_imagem'] ?? '',
                'ordem' => $ordem,
                'obrigatoria' => $obrigatoria,
                'responsavel_sugerido_id' => $responsavelSugeridoId,
                'status' => $status,
            ],
            $actorColaboradorId
        );

        return [
            'success' => true,
            'action' => 'updated',
            'planned_id' => (int) $existing['idimagem_funcao_planejada'],
        ];
    }

    $columns = ['imagem_id', 'funcao_id'];
    $placeholders = ['?', '?'];
    $types = 'ii';
    $values = [$imageId, $functionId];
    foreach ($data as $column => $value) {
        $columns[] = $column;
        $placeholders[] = '?';
        if (is_int($value) || $value === null) {
            $types .= 'i';
            $values[] = $value;
        } else {
            $types .= 's';
            $values[] = $value;
        }
    }

    $sql = 'INSERT INTO imagem_funcao_planejada (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'message' => $conn->error];
    }

    $stmt->bind_param($types, ...$values);
    if (!$stmt->execute()) {
        $message = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => $message];
    }

    $plannedId = (int) $stmt->insert_id;
    $stmt->close();

    dashboard_log_planned_function_event(
        $conn,
        $plannedId,
        $imageId,
        $functionId,
        'MANUAL_ADD',
        [
            'imagem_nome' => $image['imagem_nome'] ?? '',
            'tipo_imagem' => $image['tipo_imagem'] ?? '',
            'ordem' => $ordem,
            'obrigatoria' => $obrigatoria,
            'responsavel_sugerido_id' => $responsavelSugeridoId,
            'template_item_id' => $templateItem['template_item_id'] ?? null,
        ],
        $actorColaboradorId
    );

    return [
        'success' => true,
        'action' => 'inserted',
        'planned_id' => $plannedId,
    ];
}

function dashboard_cancel_planned_function(
    mysqli $conn,
    int $imageId,
    int $functionId,
    ?int $actorColaboradorId = null
): array {
    $existing = dashboard_fetch_planned_function_row($conn, $imageId, $functionId);
    if (!$existing) {
        return ['success' => true, 'action' => 'noop'];
    }

    if (!empty($existing['funcao_imagem_id'])) {
        return [
            'success' => false,
            'message' => 'A função já possui execução vinculada e não pode ser removida do planejamento.',
        ];
    }

    $stmt = $conn->prepare('UPDATE imagem_funcao_planejada SET status = ? WHERE idimagem_funcao_planejada = ?');
    if (!$stmt) {
        return ['success' => false, 'message' => $conn->error];
    }

    $status = 'CANCELADO';
    $plannedId = (int) $existing['idimagem_funcao_planejada'];
    $stmt->bind_param('si', $status, $plannedId);
    if (!$stmt->execute()) {
        $message = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => $message];
    }
    $stmt->close();

    dashboard_log_planned_function_event(
        $conn,
        $plannedId,
        $imageId,
        $functionId,
        'MANUAL_REMOVE',
        ['status' => 'CANCELADO'],
        $actorColaboradorId
    );

    return ['success' => true, 'action' => 'cancelled'];
}

function dashboard_get_function_catalog(mysqli $conn): array
{
    $result = $conn->query(
        "SELECT idfuncao, nome_funcao
         FROM funcao
         ORDER BY FIELD(idfuncao, 1, 8, 2, 3, 4, 5, 7, 6, 9), idfuncao"
    );
    if (!$result) {
        return [];
    }

    $functions = [];
    while ($row = $result->fetch_assoc()) {
        $functions[] = [
            'idfuncao' => (int) ($row['idfuncao'] ?? 0),
            'nome_funcao' => (string) ($row['nome_funcao'] ?? ''),
        ];
    }

    return $functions;
}

function dashboard_get_collaborators_by_function(mysqli $conn): array
{
    if (!dashboard_table_exists($conn, 'funcao_colaborador')) {
        return [];
    }

    // Mesmas regras de exclusão do carregar_dados.php:
    //   - IDs 21, 15, 30 excluídos globalmente.
    //   - IDs 7 e 34 excluídos da função 4 (Finalização).
    //   - Funções 2 e 3 (Modelagem/Composição): exclui quem também está na função 4.
    //   - Função 4: exclui quem também está na função 7 (Finalização Planta Humanizada).
    $sql = "SELECT DISTINCT
                fc.funcao_id,
                c.idcolaborador,
                c.nome_colaborador,
                COALESCE(c.imagem, iu.thumb) AS foto_colaborador,
                fc.nivel_finalizacao
            FROM funcao_colaborador fc
            INNER JOIN colaborador c ON c.idcolaborador = fc.colaborador_id
            LEFT JOIN usuario u ON u.idcolaborador = c.idcolaborador
            LEFT JOIN informacoes_usuario iu ON iu.usuario_id = u.idusuario
            WHERE c.ativo = 1
              AND fc.colaborador_id IS NOT NULL
              AND fc.colaborador_id NOT IN (21, 15, 30)
              AND NOT (fc.funcao_id = 4 AND fc.colaborador_id IN (7, 34))
              AND NOT (
                  fc.funcao_id IN (2, 3)
                  AND EXISTS (
                      SELECT 1 FROM funcao_colaborador fc2
                      WHERE fc2.colaborador_id = fc.colaborador_id
                        AND fc2.funcao_id = 4
                  )
              )
              AND NOT (
                  fc.funcao_id = 4
                  AND EXISTS (
                      SELECT 1 FROM funcao_colaborador fc2
                      WHERE fc2.colaborador_id = fc.colaborador_id
                        AND fc2.funcao_id = 7
                  )
              )
            ORDER BY fc.funcao_id ASC, c.nome_colaborador ASC";

    $result = $conn->query($sql);
    if (!$result) {
        return [];
    }

    $grouped = [];
    while ($row = $result->fetch_assoc()) {
        $functionId = (int) ($row['funcao_id'] ?? 0);
        if (!isset($grouped[$functionId])) {
            $grouped[$functionId] = [];
        }

        $grouped[$functionId][] = [
            'idcolaborador'      => (int) ($row['idcolaborador'] ?? 0),
            'nome_colaborador'   => (string) ($row['nome_colaborador'] ?? ''),
            'foto_colaborador'   => $row['foto_colaborador'] ?? null,
            'nivel_finalizacao'  => isset($row['nivel_finalizacao']) ? (int) $row['nivel_finalizacao'] : null,
        ];
    }

    return $grouped;
}

function dashboard_get_operational_queue_summary(mysqli $conn, int $obraId): array
{
    $summary = [
        'planning_ready' => dashboard_planning_tables_ready($conn),
        'planned_todo' => 0,
        'execution_pending' => 0,
        'total_backlog' => 0,
        'images_without_planning' => 0,
        'images_without_template' => 0,
    ];

    $sqlExecution = "SELECT COUNT(*) AS total
                     FROM funcao_imagem fi
                     INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
                     WHERE ico.obra_id = ?
                       AND (LOWER(TRIM(fi.status)) = 'não iniciado' OR LOWER(TRIM(fi.status)) = 'nao iniciado')";
    $stmtExecution = $conn->prepare($sqlExecution);
    if ($stmtExecution) {
        $stmtExecution->bind_param('i', $obraId);
        $stmtExecution->execute();
        $resultExecution = $stmtExecution->get_result();
        $rowExecution = $resultExecution ? $resultExecution->fetch_assoc() : null;
        $summary['execution_pending'] = isset($rowExecution['total']) ? (int) $rowExecution['total'] : 0;
        $stmtExecution->close();
    }

    if ($summary['planning_ready']) {
        $stmtPlanning = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM imagem_funcao_planejada ifp
             INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = ifp.imagem_id
             WHERE ico.obra_id = ?
               AND ifp.status = 'TODO'
               AND ifp.funcao_imagem_id IS NULL"
        );
        if ($stmtPlanning) {
            $stmtPlanning->bind_param('i', $obraId);
            $stmtPlanning->execute();
            $resultPlanning = $stmtPlanning->get_result();
            $rowPlanning = $resultPlanning ? $resultPlanning->fetch_assoc() : null;
            $summary['planned_todo'] = isset($rowPlanning['total']) ? (int) $rowPlanning['total'] : 0;
            $stmtPlanning->close();
        }

        $sqlImages = "SELECT
                ico.idimagens_cliente_obra,
                ico.tipo_imagem,
                COUNT(CASE WHEN ifp.status <> 'CANCELADO' THEN 1 END) AS total_planned,
                MAX(CASE WHEN template.idimagem_funcao_template IS NOT NULL THEN 1 ELSE 0 END) AS has_template
            FROM imagens_cliente_obra ico
            LEFT JOIN imagem_funcao_planejada ifp ON ifp.imagem_id = ico.idimagens_cliente_obra
            LEFT JOIN imagem_funcao_template template
                ON LOWER(TRIM(template.tipo_imagem)) = LOWER(TRIM(ico.tipo_imagem))
               AND template.ativo = 1
            WHERE ico.obra_id = ?
            GROUP BY ico.idimagens_cliente_obra, ico.tipo_imagem";
        $stmtImages = $conn->prepare($sqlImages);
        if ($stmtImages) {
            $stmtImages->bind_param('i', $obraId);
            $stmtImages->execute();
            $resultImages = $stmtImages->get_result();
            while ($resultImages && ($row = $resultImages->fetch_assoc())) {
                if ((int) ($row['total_planned'] ?? 0) === 0) {
                    $summary['images_without_planning']++;
                }
                if (trim((string) ($row['tipo_imagem'] ?? '')) !== '' && strcasecmp((string) ($row['tipo_imagem'] ?? ''), 'Desconhecido') !== 0 && (int) ($row['has_template'] ?? 0) === 0) {
                    $summary['images_without_template']++;
                }
            }
            $stmtImages->close();
        }
    }

    $summary['total_backlog'] = $summary['planned_todo'] + $summary['execution_pending'];

    return $summary;
}

function dashboard_fetch_planned_queue_dataset(mysqli $conn, int $obraId): array
{
    $summary = dashboard_get_operational_queue_summary($conn, $obraId);
    $functions = dashboard_get_function_catalog($conn);
    $collaboratorsByFunction = dashboard_get_collaborators_by_function($conn);
    $planningReady = !empty($summary['planning_ready']);

    $images = [];
    $groups = [];

    if ($planningReady) {
        $sqlImages = "SELECT
                ico.idimagens_cliente_obra AS imagem_id,
                ico.imagem_nome,
                ico.tipo_imagem,
                COALESCE(plan.todo_count, 0) AS fila_planejada,
                COALESCE(exec.pending_count, 0) AS fila_execucao,
                COALESCE(exec_all.total_count, 0) AS total_execucao
            FROM imagens_cliente_obra ico
            LEFT JOIN (
                SELECT imagem_id, COUNT(*) AS todo_count
                FROM imagem_funcao_planejada
                WHERE status = 'TODO' AND funcao_imagem_id IS NULL
                GROUP BY imagem_id
            ) plan ON plan.imagem_id = ico.idimagens_cliente_obra
            LEFT JOIN (
                SELECT imagem_id, COUNT(*) AS pending_count
                FROM funcao_imagem
                WHERE LOWER(TRIM(status)) IN ('não iniciado', 'nao iniciado')
                GROUP BY imagem_id
            ) exec ON exec.imagem_id = ico.idimagens_cliente_obra
            LEFT JOIN (
                SELECT imagem_id, COUNT(*) AS total_count
                FROM funcao_imagem
                GROUP BY imagem_id
            ) exec_all ON exec_all.imagem_id = ico.idimagens_cliente_obra
            WHERE ico.obra_id = ?
            ORDER BY FIELD(ico.tipo_imagem, 'Fachada', 'Imagem Interna', 'Unidade', 'Imagem Externa', 'Planta Humanizada'), ico.idimagens_cliente_obra";
    } else {
        $sqlImages = "SELECT
                ico.idimagens_cliente_obra AS imagem_id,
                ico.imagem_nome,
                ico.tipo_imagem,
                0 AS fila_planejada,
                COALESCE(exec.pending_count, 0) AS fila_execucao,
                COALESCE(exec_all.total_count, 0) AS total_execucao
            FROM imagens_cliente_obra ico
            LEFT JOIN (
                SELECT imagem_id, COUNT(*) AS pending_count
                FROM funcao_imagem
                WHERE LOWER(TRIM(status)) IN ('não iniciado', 'nao iniciado')
                GROUP BY imagem_id
            ) exec ON exec.imagem_id = ico.idimagens_cliente_obra
            LEFT JOIN (
                SELECT imagem_id, COUNT(*) AS total_count
                FROM funcao_imagem
                GROUP BY imagem_id
            ) exec_all ON exec_all.imagem_id = ico.idimagens_cliente_obra
            WHERE ico.obra_id = ?
            ORDER BY FIELD(ico.tipo_imagem, 'Fachada', 'Imagem Interna', 'Unidade', 'Imagem Externa', 'Planta Humanizada'), ico.idimagens_cliente_obra";
    }
    $stmtImages = $conn->prepare($sqlImages);
    if ($stmtImages) {
        $stmtImages->bind_param('i', $obraId);
        $stmtImages->execute();
        $resultImages = $stmtImages->get_result();
        while ($resultImages && ($row = $resultImages->fetch_assoc())) {
            $imageId = (int) ($row['imagem_id'] ?? 0);
            $type = (string) ($row['tipo_imagem'] ?? 'Sem tipo');
            $images[$imageId] = [
                'imagem_id' => $imageId,
                'imagem_nome' => (string) ($row['imagem_nome'] ?? ''),
                'tipo_imagem' => $type,
                'fila_planejada' => (int) ($row['fila_planejada'] ?? 0),
                'fila_execucao' => (int) ($row['fila_execucao'] ?? 0),
                'total_execucao' => (int) ($row['total_execucao'] ?? 0),
                'planned' => [],
            ];
            if (!isset($groups[$type])) {
                $groups[$type] = [
                    'tipo_imagem' => $type,
                    'images' => [],
                ];
            }
        }
        $stmtImages->close();
    }

    if ($planningReady) {
        $sqlPlanned = "SELECT
                ifp.idimagem_funcao_planejada,
                ifp.imagem_id,
                ifp.funcao_id,
                ifp.template_id,
                ifp.template_item_id,
                ifp.template_versao,
                ifp.funcao_imagem_id,
                ifp.ordem,
                ifp.obrigatoria,
                ifp.status,
                ifp.origem,
                ifp.responsavel_sugerido_id,
                fun.nome_funcao,
                fi.status AS execucao_status
            FROM imagem_funcao_planejada ifp
            INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = ifp.imagem_id
            INNER JOIN funcao fun ON fun.idfuncao = ifp.funcao_id
            LEFT JOIN funcao_imagem fi ON fi.idfuncao_imagem = ifp.funcao_imagem_id
            WHERE ico.obra_id = ?
              AND ifp.status <> 'CANCELADO'
            ORDER BY FIELD(ico.tipo_imagem, 'Fachada', 'Imagem Interna', 'Unidade', 'Imagem Externa', 'Planta Humanizada'), ico.idimagens_cliente_obra, ifp.ordem, fun.nome_funcao";
        $stmtPlanned = $conn->prepare($sqlPlanned);
        if ($stmtPlanned) {
            $stmtPlanned->bind_param('i', $obraId);
            $stmtPlanned->execute();
            $resultPlanned = $stmtPlanned->get_result();
            while ($resultPlanned && ($row = $resultPlanned->fetch_assoc())) {
                $imageId = (int) ($row['imagem_id'] ?? 0);
                if (!isset($images[$imageId])) {
                    continue;
                }

                $images[$imageId]['planned'][] = [
                    'idimagem_funcao_planejada' => (int) ($row['idimagem_funcao_planejada'] ?? 0),
                    'funcao_id' => (int) ($row['funcao_id'] ?? 0),
                    'nome_funcao' => (string) ($row['nome_funcao'] ?? ''),
                    'template_id' => isset($row['template_id']) ? (int) $row['template_id'] : null,
                    'template_item_id' => isset($row['template_item_id']) ? (int) $row['template_item_id'] : null,
                    'template_versao' => isset($row['template_versao']) ? (int) $row['template_versao'] : null,
                    'funcao_imagem_id' => isset($row['funcao_imagem_id']) ? (int) $row['funcao_imagem_id'] : null,
                    'ordem' => isset($row['ordem']) ? (int) $row['ordem'] : 1,
                    'obrigatoria' => isset($row['obrigatoria']) ? (int) $row['obrigatoria'] : 1,
                    'status' => (string) ($row['status'] ?? 'TODO'),
                    'origem' => (string) ($row['origem'] ?? 'PLANEJAMENTO'),
                    'responsavel_sugerido_id' => isset($row['responsavel_sugerido_id']) ? (int) $row['responsavel_sugerido_id'] : null,
                    'execucao_status' => (string) ($row['execucao_status'] ?? ''),
                ];
            }
            $stmtPlanned->close();
        }
    }

    foreach ($images as $image) {
        $type = $image['tipo_imagem'];
        $groups[$type]['images'][] = $image;
    }

    uasort($groups, static function (array $left, array $right): int {
        $rankCompare = dashboard_planning_type_rank($left['tipo_imagem'] ?? '') <=> dashboard_planning_type_rank($right['tipo_imagem'] ?? '');
        if ($rankCompare !== 0) {
            return $rankCompare;
        }

        return strcmp((string) ($left['tipo_imagem'] ?? ''), (string) ($right['tipo_imagem'] ?? ''));
    });

    $groupList = [];
    foreach ($groups as $group) {
        $plannedCount = 0;
        $executionCount = 0;
        foreach ($group['images'] as $image) {
            $plannedCount += (int) ($image['fila_planejada'] ?? 0);
            $executionCount += (int) ($image['fila_execucao'] ?? 0);
        }
        $group['total_imagens'] = count($group['images']);
        $group['planned_todo'] = $plannedCount;
        $group['execution_pending'] = $executionCount;
        $groupList[] = $group;
    }

    return [
        'summary' => $summary,
        'functions' => $functions,
        'collaborators_by_function' => $collaboratorsByFunction,
        'groups' => $groupList,
    ];
}
