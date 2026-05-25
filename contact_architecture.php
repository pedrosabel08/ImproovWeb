<?php

function contact_arch_has_table(mysqli $conn, string $table): bool
{
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

    return $exists;
}

function contact_arch_has_column(mysqli $conn, string $table, string $column): bool
{
    if (function_exists('dashboard_table_has_column')) {
        return dashboard_table_has_column($conn, $table, $column);
    }

    $sql = "SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

function contact_arch_contact_schema(mysqli $conn): array
{
    static $cache = [];

    $cacheKey = spl_object_id($conn);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $schema = [
        'id' => contact_arch_has_column($conn, 'contato_cliente', 'idcontato_cliente') ? 'idcontato_cliente' : 'idcontato',
        'client' => 'cliente_id',
        'name' => contact_arch_has_column($conn, 'contato_cliente', 'nome') ? 'nome' : 'nome_contato',
        'email' => 'email',
        'role' => contact_arch_has_column($conn, 'contato_cliente', 'cargo') ? 'cargo' : null,
        'phone' => contact_arch_has_column($conn, 'contato_cliente', 'telefone') ? 'telefone' : null,
        'type' => contact_arch_has_column($conn, 'contato_cliente', 'tipo') ? 'tipo' : null,
        'active' => contact_arch_has_column($conn, 'contato_cliente', 'ativo') ? 'ativo' : null,
        'notes' => contact_arch_has_column($conn, 'contato_cliente', 'observacoes') ? 'observacoes' : null,
        'obra' => contact_arch_has_column($conn, 'contato_cliente', 'obra_id') ? 'obra_id' : null,
        'created_at' => contact_arch_has_column($conn, 'contato_cliente', 'created_at') ? 'created_at' : null,
        'updated_at' => contact_arch_has_column($conn, 'contato_cliente', 'updated_at') ? 'updated_at' : null,
        'new_architecture' => contact_arch_has_column($conn, 'contato_cliente', 'idcontato_cliente')
            && contact_arch_has_column($conn, 'contato_cliente', 'nome')
            && contact_arch_has_column($conn, 'contato_cliente', 'ativo'),
    ];

    $cache[$cacheKey] = $schema;
    return $schema;
}

function contact_arch_link_schema(mysqli $conn): array
{
    static $cache = [];

    $cacheKey = spl_object_id($conn);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $exists = contact_arch_has_table($conn, 'obra_contato');
    $schema = [
        'exists' => $exists,
        'table' => 'obra_contato',
        'id' => $exists && contact_arch_has_column($conn, 'obra_contato', 'idobra_contato') ? 'idobra_contato' : null,
        'obra' => $exists && contact_arch_has_column($conn, 'obra_contato', 'obra_id') ? 'obra_id' : null,
        'contact' => $exists && contact_arch_has_column($conn, 'obra_contato', 'contato_cliente_id') ? 'contato_cliente_id' : null,
        'active' => $exists && contact_arch_has_column($conn, 'obra_contato', 'ativo') ? 'ativo' : null,
    ];

    $cache[$cacheKey] = $schema;
    return $schema;
}

function contact_arch_strip_accents(string $value): string
{
    return strtr($value, [
        'Á' => 'A',
        'À' => 'A',
        'Ã' => 'A',
        'Â' => 'A',
        'Ä' => 'A',
        'É' => 'E',
        'Ê' => 'E',
        'È' => 'E',
        'Ë' => 'E',
        'Í' => 'I',
        'Ì' => 'I',
        'Î' => 'I',
        'Ï' => 'I',
        'Ó' => 'O',
        'Ò' => 'O',
        'Õ' => 'O',
        'Ô' => 'O',
        'Ö' => 'O',
        'Ú' => 'U',
        'Ù' => 'U',
        'Û' => 'U',
        'Ü' => 'U',
        'Ç' => 'C',
    ]);
}

function contact_arch_normalize_type(string $value): string
{
    $normalized = strtoupper(contact_arch_strip_accents(trim($value)));
    $normalized = preg_replace('/[^A-Z]/', '', $normalized);
    if (!is_string($normalized) || $normalized === '') {
        return 'OUTRO';
    }

    $allowed = ['COMERCIAL', 'APROVACAO', 'FINANCEIRO', 'MARKETING', 'ARQUITETO', 'OUTRO'];
    return in_array($normalized, $allowed, true) ? $normalized : 'OUTRO';
}

function contact_arch_clean_contact(array $contact): array
{
    return [
        'name' => trim((string) ($contact['name'] ?? $contact['nome'] ?? $contact['nome_contato'] ?? '')),
        'email' => trim((string) ($contact['email'] ?? '')),
        'phone' => trim((string) ($contact['phone'] ?? $contact['telefone'] ?? '')),
        'role' => trim((string) ($contact['role'] ?? $contact['cargo'] ?? '')),
        'type' => contact_arch_normalize_type((string) ($contact['type'] ?? $contact['tipo'] ?? 'OUTRO')),
        'notes' => trim((string) ($contact['notes'] ?? $contact['observacoes'] ?? '')),
    ];
}

function contact_arch_is_empty_contact(array $contact): bool
{
    $cleaned = contact_arch_clean_contact($contact);
    return $cleaned['name'] === ''
        && $cleaned['email'] === ''
        && $cleaned['phone'] === ''
        && $cleaned['role'] === ''
        && $cleaned['notes'] === '';
}

function contact_arch_get_obra_client_context(mysqli $conn, int $obraId): ?array
{
    if ($obraId <= 0) {
        return null;
    }

    $hasObraClientColumn = contact_arch_has_column($conn, 'obra', 'cliente');
    $hasImageClientFallback = contact_arch_has_table($conn, 'imagens_cliente_obra')
        && contact_arch_has_column($conn, 'imagens_cliente_obra', 'obra_id')
        && contact_arch_has_column($conn, 'imagens_cliente_obra', 'cliente_id');

    if (!$hasObraClientColumn && !$hasImageClientFallback) {
        return null;
    }

    $obraSql = $hasObraClientColumn
        ? 'SELECT o.idobra, o.cliente AS cliente_id, o.nome_obra FROM obra o WHERE o.idobra = ? LIMIT 1'
        : 'SELECT o.idobra, 0 AS cliente_id, o.nome_obra FROM obra o WHERE o.idobra = ? LIMIT 1';

    $stmt = $conn->prepare($obraSql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    $clienteId = (int) ($row['cliente_id'] ?? 0);

    if ($clienteId <= 0 && $hasImageClientFallback) {
        $fallbackStmt = $conn->prepare("SELECT cliente_id FROM imagens_cliente_obra WHERE obra_id = ? AND cliente_id IS NOT NULL AND cliente_id > 0 GROUP BY cliente_id ORDER BY COUNT(*) DESC, cliente_id DESC LIMIT 1");
        if ($fallbackStmt) {
            $fallbackStmt->bind_param('i', $obraId);
            $fallbackStmt->execute();
            $fallbackResult = $fallbackStmt->get_result();
            $fallbackRow = $fallbackResult ? $fallbackResult->fetch_assoc() : null;
            $fallbackStmt->close();

            if ($fallbackRow && isset($fallbackRow['cliente_id'])) {
                $clienteId = (int) $fallbackRow['cliente_id'];
            }
        }
    }

    if ($clienteId <= 0) {
        return null;
    }

    $clienteNome = '';
    $clientStmt = $conn->prepare('SELECT nome_cliente FROM cliente WHERE idcliente = ? LIMIT 1');
    if ($clientStmt) {
        $clientStmt->bind_param('i', $clienteId);
        $clientStmt->execute();
        $clientResult = $clientStmt->get_result();
        $clientRow = $clientResult ? $clientResult->fetch_assoc() : null;
        $clientStmt->close();
        $clienteNome = (string) ($clientRow['nome_cliente'] ?? '');
    }

    return [
        'obra_id' => (int) $row['idobra'],
        'cliente_id' => $clienteId,
        'obra_nome' => (string) ($row['nome_obra'] ?? ''),
        'cliente_nome' => $clienteNome,
    ];
}

function contact_arch_find_existing_contact_id(mysqli $conn, int $clienteId, array $contact): ?int
{
    $schema = contact_arch_contact_schema($conn);
    $cleaned = contact_arch_clean_contact($contact);

    if ($clienteId <= 0) {
        return null;
    }

    if ($cleaned['email'] !== '') {
        $sql = 'SELECT ' . $schema['id'] . ' AS contact_id FROM contato_cliente WHERE ' . $schema['client'] . ' = ? AND LOWER(' . $schema['email'] . ') = LOWER(?) LIMIT 1';
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('is', $clienteId, $cleaned['email']);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();
            if ($row && isset($row['contact_id'])) {
                return (int) $row['contact_id'];
            }
        }
    }

    if ($cleaned['name'] !== '' && $schema['phone'] && $cleaned['phone'] !== '') {
        $sql = 'SELECT ' . $schema['id'] . ' AS contact_id FROM contato_cliente WHERE ' . $schema['client'] . ' = ? AND LOWER(' . $schema['name'] . ') = LOWER(?) AND LOWER(COALESCE(' . $schema['phone'] . ", '')) = LOWER(?) LIMIT 1";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('iss', $clienteId, $cleaned['name'], $cleaned['phone']);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();
            if ($row && isset($row['contact_id'])) {
                return (int) $row['contact_id'];
            }
        }
    }

    if ($cleaned['name'] !== '' && $schema['role'] && $cleaned['role'] !== '') {
        $sql = 'SELECT ' . $schema['id'] . ' AS contact_id FROM contato_cliente WHERE ' . $schema['client'] . ' = ? AND LOWER(' . $schema['name'] . ') = LOWER(?) AND LOWER(COALESCE(' . $schema['role'] . ", '')) = LOWER(?) LIMIT 1";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('iss', $clienteId, $cleaned['name'], $cleaned['role']);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();
            if ($row && isset($row['contact_id'])) {
                return (int) $row['contact_id'];
            }
        }
    }

    return null;
}

function contact_arch_fetch_contact_row(mysqli $conn, int $contactId): ?array
{
    $schema = contact_arch_contact_schema($conn);
    if ($contactId <= 0) {
        return null;
    }

    $select = [
        $schema['id'] . ' AS contact_id',
        $schema['client'] . ' AS cliente_id',
        $schema['name'] . ' AS name',
        $schema['email'] . ' AS email',
    ];

    if ($schema['role']) {
        $select[] = $schema['role'] . ' AS role';
    } else {
        $select[] = "'' AS role";
    }
    if ($schema['phone']) {
        $select[] = $schema['phone'] . ' AS phone';
    } else {
        $select[] = "'' AS phone";
    }
    if ($schema['type']) {
        $select[] = $schema['type'] . ' AS type';
    } else {
        $select[] = "'OUTRO' AS type";
    }
    if ($schema['notes']) {
        $select[] = $schema['notes'] . ' AS notes';
    } else {
        $select[] = "'' AS notes";
    }
    if ($schema['active']) {
        $select[] = $schema['active'] . ' AS is_active';
    } else {
        $select[] = '1 AS is_active';
    }

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM contato_cliente WHERE ' . $schema['id'] . ' = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $contactId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'contact_id' => (int) ($row['contact_id'] ?? 0),
        'cliente_id' => (int) ($row['cliente_id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
        'phone' => (string) ($row['phone'] ?? ''),
        'role' => (string) ($row['role'] ?? ''),
        'type' => contact_arch_normalize_type((string) ($row['type'] ?? 'OUTRO')),
        'notes' => (string) ($row['notes'] ?? ''),
        'is_active' => (int) ($row['is_active'] ?? 1),
    ];
}

function contact_arch_save_client_contact(mysqli $conn, int $clienteId, array $contact): int
{
    $schema = contact_arch_contact_schema($conn);
    $cleaned = contact_arch_clean_contact($contact);

    if ($clienteId <= 0) {
        throw new RuntimeException('Cliente invalido para salvar contato.');
    }
    if ($cleaned['name'] === '') {
        throw new RuntimeException('Informe o nome do contato.');
    }

    $existingId = contact_arch_find_existing_contact_id($conn, $clienteId, $cleaned);
    if ($existingId) {
        return contact_arch_update_client_contact_by_id($conn, $existingId, $cleaned);
    }

    $columns = [$schema['client'], $schema['name'], $schema['email']];
    $placeholders = ['?', '?', '?'];
    $types = 'iss';
    $values = [$clienteId, $cleaned['name'], $cleaned['email']];

    if ($schema['role']) {
        $columns[] = $schema['role'];
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $cleaned['role'];
    }

    if ($schema['phone']) {
        $columns[] = $schema['phone'];
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $cleaned['phone'];
    }

    if ($schema['type']) {
        $columns[] = $schema['type'];
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $cleaned['type'];
    }

    if ($schema['notes']) {
        $columns[] = $schema['notes'];
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $cleaned['notes'];
    }

    if ($schema['active']) {
        $columns[] = $schema['active'];
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = 1;
    }

    $sql = 'INSERT INTO contato_cliente (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Erro ao inserir contato do cliente: ' . $conn->error);
    }

    $stmt->bind_param($types, ...$values);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Erro ao inserir contato do cliente: ' . $error);
    }

    $contactId = (int) $stmt->insert_id;
    $stmt->close();

    return $contactId;
}

function contact_arch_update_client_contact_by_id(mysqli $conn, int $contactId, array $contact): int
{
    $schema = contact_arch_contact_schema($conn);
    $cleaned = contact_arch_clean_contact($contact);
    $current = contact_arch_fetch_contact_row($conn, $contactId);
    if (!$current) {
        throw new RuntimeException('Contato do cliente nao encontrado para atualizacao.');
    }

    $name = $cleaned['name'] !== '' ? $cleaned['name'] : (string) ($current['name'] ?? '');
    $email = $cleaned['email'] !== '' ? $cleaned['email'] : (string) ($current['email'] ?? '');
    $role = $cleaned['role'] !== '' ? $cleaned['role'] : (string) ($current['role'] ?? '');
    $phone = $cleaned['phone'] !== '' ? $cleaned['phone'] : (string) ($current['phone'] ?? '');
    $type = $cleaned['type'] !== 'OUTRO' || empty($current['type']) ? $cleaned['type'] : (string) $current['type'];
    $notes = $cleaned['notes'] !== '' ? $cleaned['notes'] : (string) ($current['notes'] ?? '');

    $assignments = [];
    $types = '';
    $values = [];

    $assignments[] = $schema['name'] . ' = ?';
    $types .= 's';
    $values[] = $name;

    $assignments[] = $schema['email'] . ' = ?';
    $types .= 's';
    $values[] = $email;

    if ($schema['role']) {
        $assignments[] = $schema['role'] . ' = ?';
        $types .= 's';
        $values[] = $role;
    }

    if ($schema['phone']) {
        $assignments[] = $schema['phone'] . ' = ?';
        $types .= 's';
        $values[] = $phone;
    }

    if ($schema['type']) {
        $assignments[] = $schema['type'] . ' = ?';
        $types .= 's';
        $values[] = $type;
    }

    if ($schema['notes']) {
        $assignments[] = $schema['notes'] . ' = ?';
        $types .= 's';
        $values[] = $notes;
    }

    if ($schema['active']) {
        $assignments[] = $schema['active'] . ' = ?';
        $types .= 'i';
        $values[] = 1;
    }

    $sql = 'UPDATE contato_cliente SET ' . implode(', ', $assignments) . ' WHERE ' . $schema['id'] . ' = ?';
    $types .= 'i';
    $values[] = $contactId;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Erro ao atualizar contato do cliente: ' . $conn->error);
    }

    $stmt->bind_param($types, ...$values);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Erro ao atualizar contato do cliente: ' . $error);
    }
    $stmt->close();

    return $contactId;
}

function contact_arch_fetch_client_contacts(mysqli $conn, int $clienteId, ?int $obraId = null): array
{
    $schema = contact_arch_contact_schema($conn);
    $linkSchema = contact_arch_link_schema($conn);

    if ($clienteId <= 0) {
        return [];
    }

    $select = [
        'cc.' . $schema['id'] . ' AS contact_id',
        'cc.' . $schema['name'] . ' AS name',
        'cc.' . $schema['email'] . ' AS email',
    ];

    $select[] = $schema['role'] ? 'cc.' . $schema['role'] . ' AS role' : "'' AS role";
    $select[] = $schema['phone'] ? 'cc.' . $schema['phone'] . ' AS phone' : "'' AS phone";
    $select[] = $schema['type'] ? 'cc.' . $schema['type'] . ' AS type' : "'OUTRO' AS type";
    $select[] = $schema['notes'] ? 'cc.' . $schema['notes'] . ' AS notes' : "'' AS notes";
    $select[] = $schema['active'] ? 'cc.' . $schema['active'] . ' AS contact_active' : '1 AS contact_active';

    $types = 'i';
    $params = [$clienteId];
    $joinSql = '';
    if ($obraId && $linkSchema['exists'] && $linkSchema['obra'] && $linkSchema['contact']) {
        $select[] = $linkSchema['id'] ? 'oc.' . $linkSchema['id'] . ' AS obra_contact_id' : 'NULL AS obra_contact_id';
        $select[] = $linkSchema['active'] ? 'COALESCE(oc.' . $linkSchema['active'] . ', 0) AS obra_selected' : 'CASE WHEN oc.' . $linkSchema['contact'] . ' IS NULL THEN 0 ELSE 1 END AS obra_selected';
        $joinSql = ' LEFT JOIN ' . $linkSchema['table'] . ' oc ON oc.' . $linkSchema['contact'] . ' = cc.' . $schema['id'] . ' AND oc.' . $linkSchema['obra'] . ' = ?';
        $types = 'ii';
        $params = [$clienteId, $obraId];
    } else {
        $select[] = 'NULL AS obra_contact_id';
        $select[] = '0 AS obra_selected';
    }

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM contato_cliente cc' . $joinSql . ' WHERE cc.' . $schema['client'] . ' = ?';
    if ($schema['active']) {
        $sql .= ' AND cc.' . $schema['active'] . ' = 1';
    }
    $sql .= ' ORDER BY cc.' . $schema['name'] . ' ASC';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Erro ao consultar contatos do cliente: ' . $conn->error);
    }

    if ($types === 'ii') {
        $stmt->bind_param($types, $obraId, $clienteId);
    } else {
        $stmt->bind_param($types, $clienteId);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $contacts = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $contacts[] = [
            'contact_id' => (int) ($row['contact_id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'role' => (string) ($row['role'] ?? ''),
            'type' => contact_arch_normalize_type((string) ($row['type'] ?? 'OUTRO')),
            'notes' => (string) ($row['notes'] ?? ''),
            'contact_active' => (int) ($row['contact_active'] ?? 1),
            'obra_contact_id' => isset($row['obra_contact_id']) ? (int) $row['obra_contact_id'] : null,
            'obra_selected' => (int) ($row['obra_selected'] ?? 0) === 1,
        ];
    }
    $stmt->close();

    return $contacts;
}

function contact_arch_fetch_linked_contacts(mysqli $conn, int $obraId): array
{
    $schema = contact_arch_contact_schema($conn);
    $linkSchema = contact_arch_link_schema($conn);

    if ($obraId <= 0 || !$linkSchema['exists'] || !$linkSchema['obra'] || !$linkSchema['contact']) {
        return [];
    }

    $select = [
        'cc.' . $schema['id'] . ' AS contact_id',
        'cc.' . $schema['name'] . ' AS name',
        'cc.' . $schema['email'] . ' AS email',
    ];
    $select[] = $schema['role'] ? 'cc.' . $schema['role'] . ' AS role' : "'' AS role";
    $select[] = $schema['phone'] ? 'cc.' . $schema['phone'] . ' AS phone' : "'' AS phone";
    $select[] = $schema['type'] ? 'cc.' . $schema['type'] . ' AS type' : "'OUTRO' AS type";
    $select[] = $schema['notes'] ? 'cc.' . $schema['notes'] . ' AS notes' : "'' AS notes";

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM ' . $linkSchema['table'] . ' oc INNER JOIN contato_cliente cc ON cc.' . $schema['id'] . ' = oc.' . $linkSchema['contact'] . ' WHERE oc.' . $linkSchema['obra'] . ' = ?';
    if ($linkSchema['active']) {
        $sql .= ' AND oc.' . $linkSchema['active'] . ' = 1';
    }
    if ($schema['active']) {
        $sql .= ' AND cc.' . $schema['active'] . ' = 1';
    }
    $sql .= ' ORDER BY cc.' . $schema['name'] . ' ASC';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Erro ao consultar contatos vinculados da obra: ' . $conn->error);
    }

    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $result = $stmt->get_result();
    $contacts = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $contacts[] = [
            'contact_id' => (int) ($row['contact_id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'role' => (string) ($row['role'] ?? ''),
            'type' => contact_arch_normalize_type((string) ($row['type'] ?? 'OUTRO')),
            'notes' => (string) ($row['notes'] ?? ''),
        ];
    }
    $stmt->close();

    return $contacts;
}

function contact_arch_contacts_count_for_obra(mysqli $conn, int $obraId): int
{
    $linkSchema = contact_arch_link_schema($conn);
    $schema = contact_arch_contact_schema($conn);

    if ($obraId <= 0 || !$linkSchema['exists'] || !$linkSchema['obra'] || !$linkSchema['contact']) {
        return 0;
    }

    $sql = 'SELECT COUNT(*) AS total FROM ' . $linkSchema['table'] . ' oc INNER JOIN contato_cliente cc ON cc.' . $schema['id'] . ' = oc.' . $linkSchema['contact'] . ' WHERE oc.' . $linkSchema['obra'] . ' = ?';
    if ($linkSchema['active']) {
        $sql .= ' AND oc.' . $linkSchema['active'] . ' = 1';
    }
    if ($schema['active']) {
        $sql .= ' AND cc.' . $schema['active'] . ' = 1';
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return isset($row['total']) ? (int) $row['total'] : 0;
}

function contact_arch_sync_obra_contacts(mysqli $conn, int $obraId, array $contactIds): array
{
    $schema = contact_arch_contact_schema($conn);
    $linkSchema = contact_arch_link_schema($conn);
    if (!$linkSchema['exists'] || !$linkSchema['obra'] || !$linkSchema['contact']) {
        throw new RuntimeException('A tabela obra_contato nao foi encontrada. Execute a migracao da nova arquitetura de contatos.');
    }

    $context = contact_arch_get_obra_client_context($conn, $obraId);
    if (!$context || $context['cliente_id'] <= 0) {
        throw new RuntimeException('Nao foi possivel localizar o cliente da obra para sincronizar os contatos.');
    }

    $normalizedIds = array_values(array_unique(array_filter(array_map(static function ($value) {
        return (int) $value;
    }, $contactIds), static function (int $value) {
        return $value > 0;
    })));

    $validIds = [];
    if (!empty($normalizedIds)) {
        $placeholders = implode(', ', array_fill(0, count($normalizedIds), '?'));
        $sql = 'SELECT ' . $schema['id'] . ' AS contact_id FROM contato_cliente WHERE ' . $schema['client'] . ' = ? AND ' . $schema['id'] . ' IN (' . $placeholders . ')';
        if ($schema['active']) {
            $sql .= ' AND ' . $schema['active'] . ' = 1';
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Erro ao validar contatos selecionados: ' . $conn->error);
        }

        $types = 'i' . str_repeat('i', count($normalizedIds));
        $params = array_merge([$context['cliente_id']], $normalizedIds);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $validIds[] = (int) ($row['contact_id'] ?? 0);
        }
        $stmt->close();

        if (count($validIds) !== count($normalizedIds)) {
            throw new RuntimeException('Existe contato selecionado que nao pertence ao cliente da obra.');
        }
    }

    if ($linkSchema['active']) {
        $deactivateSql = 'UPDATE ' . $linkSchema['table'] . ' SET ' . $linkSchema['active'] . ' = 0 WHERE ' . $linkSchema['obra'] . ' = ?';
    } else {
        $deactivateSql = 'DELETE FROM ' . $linkSchema['table'] . ' WHERE ' . $linkSchema['obra'] . ' = ?';
    }
    $deactivateStmt = $conn->prepare($deactivateSql);
    if (!$deactivateStmt) {
        throw new RuntimeException('Erro ao limpar vinculos de contatos da obra: ' . $conn->error);
    }
    $deactivateStmt->bind_param('i', $obraId);
    if (!$deactivateStmt->execute()) {
        $error = $deactivateStmt->error;
        $deactivateStmt->close();
        throw new RuntimeException('Erro ao atualizar vinculos de contatos da obra: ' . $error);
    }
    $deactivateStmt->close();

    if (empty($validIds)) {
        return ['selected_ids' => [], 'linked_count' => 0];
    }

    $insertColumns = [$linkSchema['obra'], $linkSchema['contact']];
    $insertValues = ['?', '?'];
    $types = 'ii';
    if ($linkSchema['active']) {
        $insertColumns[] = $linkSchema['active'];
        $insertValues[] = '?';
        $types .= 'i';
    }

    $sql = 'INSERT INTO ' . $linkSchema['table'] . ' (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertValues) . ') ON DUPLICATE KEY UPDATE ';
    $updates = [];
    if ($linkSchema['active']) {
        $updates[] = $linkSchema['active'] . ' = VALUES(' . $linkSchema['active'] . ')';
    }
    $sql .= implode(', ', $updates);

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Erro ao vincular contatos na obra: ' . $conn->error);
    }

    foreach ($validIds as $contactId) {
        if ($linkSchema['active']) {
            $isActive = 1;
            $stmt->bind_param($types, $obraId, $contactId, $isActive);
        } else {
            $stmt->bind_param($types, $obraId, $contactId);
        }
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Erro ao vincular contatos na obra: ' . $error);
        }
    }
    $stmt->close();

    return [
        'selected_ids' => $validIds,
        'linked_count' => count($validIds),
    ];
}