<?php

function eventos_obra_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function eventos_obra_require_auth(): void
{
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        eventos_obra_json(['success' => false, 'error' => 'Nao autenticado'], 401);
    }
}

function eventos_obra_can_edit(): bool
{
    $nivel = isset($_SESSION['nivel_acesso']) ? (int) $_SESSION['nivel_acesso'] : 0;
    return in_array($nivel, [1, 3], true);
}

function eventos_obra_require_editor(): void
{
    if (!eventos_obra_can_edit()) {
        eventos_obra_json(['success' => false, 'error' => 'Sem permissao para alterar eventos da obra'], 403);
    }
}

function eventos_obra_session_colaborador_id(): ?int
{
    if (isset($_SESSION['idcolaborador']) && (int) $_SESSION['idcolaborador'] > 0) {
        return (int) $_SESSION['idcolaborador'];
    }
    return null;
}

function eventos_obra_session_usuario_id(): ?int
{
    if (isset($_SESSION['idusuario']) && (int) $_SESSION['idusuario'] > 0) {
        return (int) $_SESSION['idusuario'];
    }
    return null;
}

function eventos_obra_table_has_column(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare(
        "SELECT 1
           FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
          LIMIT 1"
    );
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

function eventos_obra_table_exists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare(
        "SELECT 1
           FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
          LIMIT 1"
    );
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

function eventos_obra_table_has_index(mysqli $conn, string $table, string $index): bool
{
    $stmt = $conn->prepare(
        "SELECT 1
           FROM INFORMATION_SCHEMA.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND INDEX_NAME = ?
          LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $table, $index);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

function eventos_obra_ensure_schema(mysqli $conn): void
{
    $columns = [
        'hora_evento' => "ALTER TABLE eventos_obra ADD COLUMN hora_evento TIME NULL AFTER data_evento",
        'participantes' => "ALTER TABLE eventos_obra ADD COLUMN participantes TEXT NULL AFTER descricao",
        'ata' => "ALTER TABLE eventos_obra ADD COLUMN ata MEDIUMTEXT NULL AFTER participantes",
        'origem_modulo' => "ALTER TABLE eventos_obra ADD COLUMN origem_modulo VARCHAR(40) NULL DEFAULT NULL AFTER tipo_evento",
        'created_at' => "ALTER TABLE eventos_obra ADD COLUMN created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER origem_modulo",
        'updated_at' => "ALTER TABLE eventos_obra ADD COLUMN updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        'arquivado_em' => "ALTER TABLE eventos_obra ADD COLUMN arquivado_em DATETIME NULL AFTER updated_at",
        'arquivado_por' => "ALTER TABLE eventos_obra ADD COLUMN arquivado_por INT NULL AFTER arquivado_em",
    ];

    foreach ($columns as $column => $sql) {
        if (!eventos_obra_table_has_column($conn, 'eventos_obra', $column)) {
            if (!$conn->query($sql)) {
                throw new RuntimeException('Erro ao ajustar eventos_obra.' . $column . ': ' . $conn->error);
            }
        }
    }

    if (!eventos_obra_table_has_index($conn, 'eventos_obra', 'idx_eventos_obra_modulo_data')) {
        if (!$conn->query("ALTER TABLE eventos_obra ADD INDEX idx_eventos_obra_modulo_data (obra_id, origem_modulo, arquivado_em, data_evento)")) {
            throw new RuntimeException('Erro ao criar indice de eventos_obra: ' . $conn->error);
        }
    }

    if (!eventos_obra_table_exists($conn, 'evento_obra_referencias')) {
        $sql = "CREATE TABLE evento_obra_referencias (
            id INT NOT NULL AUTO_INCREMENT,
            evento_id INT NOT NULL,
            obra_id INT NOT NULL,
            tipo ENUM('url','upload') NOT NULL,
            url TEXT NULL,
            nome_original VARCHAR(255) NULL,
            nome_arquivo VARCHAR(255) NULL,
            caminho VARCHAR(500) NULL,
            mime VARCHAR(120) NULL,
            tamanho_bytes BIGINT NULL,
            hash_sha1 CHAR(40) NULL,
            origem VARCHAR(40) NOT NULL DEFAULT 'Evento',
            status VARCHAR(40) NOT NULL DEFAULT 'Pendente',
            observacao VARCHAR(120) NOT NULL DEFAULT 'Reunião',
            status_sire ENUM('pendente','classificado','ignorado','erro') NOT NULL DEFAULT 'pendente',
            criado_por INT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            arquivado_em DATETIME NULL,
            arquivado_por INT NULL,
            PRIMARY KEY (id),
            KEY idx_eor_evento (evento_id),
            KEY idx_eor_obra (obra_id),
            KEY idx_eor_sire (status_sire, arquivado_em),
            KEY idx_eor_criado (criado_em),
            CONSTRAINT fk_eor_evento FOREIGN KEY (evento_id) REFERENCES eventos_obra (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_eor_obra FOREIGN KEY (obra_id) REFERENCES obra (idobra) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            throw new RuntimeException('Erro ao criar evento_obra_referencias: ' . $conn->error);
        }
    }
}

function eventos_obra_assert_obra_access(mysqli $conn, int $obraId): void
{
    if ($obraId <= 0) {
        eventos_obra_json(['success' => false, 'error' => 'obra_id invalido'], 400);
    }

    if (function_exists('improov_usuario_pode_acessar_obra') && !improov_usuario_pode_acessar_obra($conn, $obraId)) {
        eventos_obra_json(['success' => false, 'error' => 'Sem acesso a esta obra'], 403);
    }
}

function eventos_obra_norm_date(?string $date): ?string
{
    $date = trim((string) $date);
    if ($date === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('Y-m-d') : null;
}

function eventos_obra_norm_time(?string $time): ?string
{
    $time = trim((string) $time);
    if ($time === '') {
        return null;
    }
    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        return $time . ':00';
    }
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        return $time;
    }
    return null;
}

function eventos_obra_public_ref_path(string $storedName): string
{
    return 'uploads/eventos_obra/' . $storedName;
}

function eventos_obra_abs_upload_dir(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'eventos_obra';
}

function eventos_obra_store_upload(mysqli $conn, int $eventoId, int $obraId, array $file, ?int $usuarioId): array
{
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $original = (string) ($file['name'] ?? '');
    $tmp = (string) ($file['tmp_name'] ?? '');
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    $size = isset($file['size']) ? (int) $file['size'] : null;
    $mime = isset($file['type']) ? (string) $file['type'] : null;

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Falha ao enviar '{$original}' (codigo {$error}).");
    }

    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        throw new InvalidArgumentException("Tipo de imagem invalido para '{$original}'.");
    }

    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException("Arquivo temporario invalido para '{$original}'.");
    }

    $dir = eventos_obra_abs_upload_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Nao foi possivel criar pasta de uploads de eventos.');
    }

    $safeOriginal = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($original, PATHINFO_FILENAME));
    if ($safeOriginal === '' || $safeOriginal === null) {
        $safeOriginal = 'referencia';
    }
    $stored = 'obra_' . $obraId . '_evt_' . $eventoId . '_' . bin2hex(random_bytes(6)) . '_' . $safeOriginal . '.' . $ext;
    $target = $dir . DIRECTORY_SEPARATOR . $stored;

    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException("Nao foi possivel salvar '{$original}'.");
    }

    $hash = sha1_file($target) ?: null;
    $path = eventos_obra_public_ref_path($stored);

    $stmt = $conn->prepare(
        "INSERT INTO evento_obra_referencias
            (evento_id, obra_id, tipo, nome_original, nome_arquivo, caminho, mime, tamanho_bytes, hash_sha1, criado_por)
         VALUES (?, ?, 'upload', ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        @unlink($target);
        throw new RuntimeException('Erro ao preparar referencia upload: ' . $conn->error);
    }

    $stmt->bind_param('iissssisi', $eventoId, $obraId, $original, $stored, $path, $mime, $size, $hash, $usuarioId);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        @unlink($target);
        throw new RuntimeException('Erro ao registrar referencia upload: ' . $err);
    }
    $id = $stmt->insert_id;
    $stmt->close();

    return ['id' => $id, 'tipo' => 'upload', 'nome_original' => $original, 'caminho' => $path];
}

function eventos_obra_add_url_ref(mysqli $conn, int $eventoId, int $obraId, string $url, ?int $usuarioId): ?int
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException("URL invalida: {$url}");
    }

    $stmt = $conn->prepare(
        "INSERT INTO evento_obra_referencias
            (evento_id, obra_id, tipo, url, criado_por)
         VALUES (?, ?, 'url', ?, ?)"
    );
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar referencia URL: ' . $conn->error);
    }

    $stmt->bind_param('iisi', $eventoId, $obraId, $url, $usuarioId);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Erro ao registrar referencia URL: ' . $err);
    }
    $id = $stmt->insert_id;
    $stmt->close();

    return $id;
}

function eventos_obra_normalize_files_array(array $files): array
{
    if (!isset($files['name'])) {
        return [];
    }

    if (!is_array($files['name'])) {
        if ((int) ($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }
        return [$files];
    }

    $out = [];
    foreach ($files['name'] as $idx => $name) {
        $error = (int) ($files['error'][$idx] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = [
            'name' => $name,
            'type' => $files['type'][$idx] ?? null,
            'tmp_name' => $files['tmp_name'][$idx] ?? null,
            'error' => $error,
            'size' => $files['size'][$idx] ?? null,
        ];
    }
    return $out;
}
