<?php

function pos_referencias_ensure_schema(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS pos_referencias_visuais (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        pos_producao_id INT NOT NULL,
        arquivo VARCHAR(255) NOT NULL,
        nome_original VARCHAR(255) NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        tamanho_bytes INT UNSIGNED NOT NULL,
        checksum_sha256 CHAR(64) NULL,
        origem VARCHAR(32) NOT NULL DEFAULT 'upload',
        render_id INT NULL,
        ordem INT NOT NULL DEFAULT 0,
        criado_por_colaborador_id INT NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        removido_em DATETIME NULL,
        removido_por_colaborador_id INT NULL,
        UNIQUE KEY uniq_pos_referencia_arquivo (pos_producao_id, arquivo),
        UNIQUE KEY uniq_pos_referencia_checksum (pos_producao_id, checksum_sha256),
        KEY idx_pos_referencias_ativas (pos_producao_id, removido_em, ordem),
        CONSTRAINT fk_pos_referencias_pos FOREIGN KEY (pos_producao_id)
            REFERENCES pos_producao (idpos_producao) ON DELETE CASCADE,
        CONSTRAINT fk_pos_referencias_criador FOREIGN KEY (criado_por_colaborador_id)
            REFERENCES colaborador (idcolaborador) ON DELETE SET NULL,
        CONSTRAINT fk_pos_referencias_removedor FOREIGN KEY (removido_por_colaborador_id)
            REFERENCES colaborador (idcolaborador) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Instalações que já criaram a tabela antes deste campo continuam compatíveis.
    $column = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_referencias_visuais' AND COLUMN_NAME = 'checksum_sha256'");
    if (!$column || $column->num_rows === 0) {
        $conn->query('ALTER TABLE pos_referencias_visuais ADD COLUMN checksum_sha256 CHAR(64) NULL AFTER tamanho_bytes');
        $conn->query('ALTER TABLE pos_referencias_visuais ADD UNIQUE KEY uniq_pos_referencia_checksum (pos_producao_id, checksum_sha256)');
    }

    foreach ([
        'origem' => "ALTER TABLE pos_referencias_visuais ADD COLUMN origem VARCHAR(32) NOT NULL DEFAULT 'upload' AFTER checksum_sha256",
        'render_id' => 'ALTER TABLE pos_referencias_visuais ADD COLUMN render_id INT NULL AFTER origem',
    ] as $columnName => $sql) {
        $column = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_referencias_visuais' AND COLUMN_NAME = '{$columnName}'");
        if (!$column || $column->num_rows === 0) $conn->query($sql);
    }

    $conn->query("CREATE TABLE IF NOT EXISTS pos_referencias_visuais_historico (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        referencia_id BIGINT UNSIGNED NOT NULL,
        acao ENUM('CRIADA','REMOVIDA') NOT NULL,
        colaborador_id INT NULL,
        dados_json JSON NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_pos_referencias_historico_ref (referencia_id, criado_em),
        CONSTRAINT fk_pos_referencias_historico_ref FOREIGN KEY (referencia_id)
            REFERENCES pos_referencias_visuais (id) ON DELETE CASCADE,
        CONSTRAINT fk_pos_referencias_historico_colaborador FOREIGN KEY (colaborador_id)
            REFERENCES colaborador (idcolaborador) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function pos_referencias_ensure_annotations_schema(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS pos_referencias_comentarios (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        referencia_id BIGINT UNSIGNED NOT NULL,
        texto TEXT NULL,
        tipo ENUM('ponto','freehand') NOT NULL DEFAULT 'ponto',
        x DOUBLE NULL,
        y DOUBLE NULL,
        path_data LONGTEXT NULL,
        possui_desenho TINYINT(1) NOT NULL DEFAULT 0,
        cor VARCHAR(20) NOT NULL DEFAULT '#f59e0b',
        espessura TINYINT UNSIGNED NOT NULL DEFAULT 2,
        responsavel_id INT NOT NULL,
        data DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        excluido_em DATETIME NULL,
        excluido_por INT NULL,
        KEY idx_ref_comentarios (referencia_id, excluido_em, data),
        CONSTRAINT fk_pos_ref_comentario_ref FOREIGN KEY (referencia_id) REFERENCES pos_referencias_visuais(id) ON DELETE CASCADE,
        CONSTRAINT fk_pos_ref_comentario_autor FOREIGN KEY (responsavel_id) REFERENCES colaborador(idcolaborador) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    foreach ([
        'possui_desenho' => 'ALTER TABLE pos_referencias_comentarios ADD COLUMN possui_desenho TINYINT(1) NOT NULL DEFAULT 0 AFTER path_data',
        'espessura' => 'ALTER TABLE pos_referencias_comentarios ADD COLUMN espessura TINYINT UNSIGNED NOT NULL DEFAULT 2 AFTER cor',
        'excluido_em' => 'ALTER TABLE pos_referencias_comentarios ADD COLUMN excluido_em DATETIME NULL AFTER data',
        'excluido_por' => 'ALTER TABLE pos_referencias_comentarios ADD COLUMN excluido_por INT NULL AFTER excluido_em',
    ] as $columnName => $sql) {
        $column = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_referencias_comentarios' AND COLUMN_NAME = '{$columnName}'");
        if (!$column || $column->num_rows === 0) $conn->query($sql);
    }
}

function pos_referencias_list(mysqli $conn, int $posId): array
{
    // A referência principal é lógica: o arquivo continua sendo o preview do
    // Render. Retornamos o nome real do JPG para que os consumidores não
    // tentem resolver o identificador render-preview-{id} como se fosse upload.
    $stmt = $conn->prepare("SELECT rv.id, rv.arquivo, rv.nome_original, rv.mime_type, rv.tamanho_bytes,
            rv.checksum_sha256, rv.origem, rv.render_id, rv.ordem, rv.criado_em,
            CASE WHEN rv.origem = 'render_principal' THEN r.previa_jpg ELSE NULL END AS preview_render
        FROM pos_referencias_visuais rv
        LEFT JOIN render_alta r ON r.idrender_alta = rv.render_id
        WHERE rv.pos_producao_id = ? AND rv.removido_em IS NULL
        ORDER BY rv.ordem, rv.id");
    $stmt->bind_param('i', $posId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function pos_referencias_ensure_render_principal(mysqli $conn, int $posId, int $renderId, string $previewPath, int $colaboradorId): int
{
    $stmt = $conn->prepare("SELECT id FROM pos_referencias_visuais WHERE pos_producao_id = ? AND origem = 'render_principal' AND removido_em IS NULL LIMIT 1");
    $stmt->bind_param('i', $posId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existing) return (int)$existing['id'];

    $arquivo = 'render-preview-' . $renderId;
    $nome = 'Render principal';
    $mime = 'image/render-preview';
    $size = 0;
    $order = -1;
    $origin = 'render_principal';
    $stmt = $conn->prepare("INSERT INTO pos_referencias_visuais
        (pos_producao_id, arquivo, nome_original, mime_type, tamanho_bytes, origem, render_id, ordem, criado_por_colaborador_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isssisiii', $posId, $arquivo, $nome, $mime, $size, $origin, $renderId, $order, $colaboradorId);
    if (!$stmt->execute()) throw new RuntimeException('Não foi possível preparar a referência principal do Render.');
    $referenceId = (int)$conn->insert_id;
    $stmt->close();
    $history = $conn->prepare("INSERT INTO pos_referencias_visuais_historico (referencia_id, acao, colaborador_id, dados_json) VALUES (?, 'CRIADA', ?, ?)");
    $details = json_encode(['origem' => $origin, 'render_id' => $renderId, 'previa_jpg' => $previewPath], JSON_UNESCAPED_UNICODE);
    $history->bind_param('iis', $referenceId, $colaboradorId, $details);
    $history->execute();
    $history->close();
    return $referenceId;
}

function pos_referencias_annotations_list(mysqli $conn, int $referenceId): array
{
    pos_referencias_ensure_annotations_schema($conn);
    $stmt = $conn->prepare("SELECT c.*, IF(c.possui_desenho = 1 OR (c.tipo = 'freehand' AND c.path_data IS NOT NULL), 1, 0) AS possui_desenho, col.nome_colaborador FROM pos_referencias_comentarios c JOIN colaborador col ON col.idcolaborador = c.responsavel_id WHERE c.referencia_id = ? AND c.excluido_em IS NULL ORDER BY c.data, c.id");
    $stmt->bind_param('i', $referenceId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function pos_referencias_drawing_is_valid(?string $pathData): bool
{
    if (!$pathData) return false;
    $drawing = json_decode($pathData, true);
    if (!is_array($drawing) || !in_array($drawing['tool'] ?? '', ['pencil', 'arrow', 'rectangle', 'circle'], true)) return false;
    $validPoint = static fn($point): bool => is_array($point) && is_numeric($point['x'] ?? null) && is_numeric($point['y'] ?? null);
    if (($drawing['tool'] ?? '') === 'pencil') {
        $points = $drawing['points'] ?? [];
        if (!is_array($points) || count($points) < 2 || !$validPoint($points[0]) || !$validPoint($points[count($points) - 1])) return false;
        return abs((float)$points[0]['x'] - (float)$points[count($points) - 1]['x']) > 0.25 || abs((float)$points[0]['y'] - (float)$points[count($points) - 1]['y']) > 0.25;
    }
    if (!$validPoint($drawing['start'] ?? null) || !$validPoint($drawing['end'] ?? null)) return false;
    return abs((float)$drawing['start']['x'] - (float)$drawing['end']['x']) > 0.25 || abs((float)$drawing['start']['y'] - (float)$drawing['end']['y']) > 0.25;
}

function pos_referencias_annotation_create(mysqli $conn, int $referenceId, int $authorId, string $text, string $type, ?float $x, ?float $y, ?string $pathData, string $color, int $width, ?bool $requestedHasDrawing = null): int
{
    pos_referencias_ensure_annotations_schema($conn);
    $hasDrawing = pos_referencias_drawing_is_valid($pathData) && $requestedHasDrawing !== false;
    if (!$hasDrawing) $pathData = null;
    $type = $hasDrawing ? 'freehand' : 'ponto';
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#f59e0b';
    $width = max(1, min(12, $width));
    if ($text === '' && !$hasDrawing) throw new RuntimeException('Adicione um comentário ou faça um desenho válido.');
    $hasDrawingInt = $hasDrawing ? 1 : 0;
    $stmt = $conn->prepare('INSERT INTO pos_referencias_comentarios (referencia_id, texto, tipo, x, y, path_data, possui_desenho, cor, espessura, responsavel_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('issddsisii', $referenceId, $text, $type, $x, $y, $pathData, $hasDrawingInt, $color, $width, $authorId);
    if (!$stmt->execute()) throw new RuntimeException('Não foi possível salvar a anotação.');
    $id = (int)$conn->insert_id;
    $stmt->close();
    return $id;
}

function pos_referencias_annotation_remove(mysqli $conn, int $annotationId, int $authorId): bool
{
    pos_referencias_ensure_annotations_schema($conn);
    $stmt = $conn->prepare('UPDATE pos_referencias_comentarios SET excluido_em = NOW(), excluido_por = ? WHERE id = ? AND responsavel_id = ? AND excluido_em IS NULL');
    $stmt->bind_param('iii', $authorId, $annotationId, $authorId);
    $stmt->execute();
    $removed = $stmt->affected_rows > 0;
    $stmt->close();
    return $removed;
}

function pos_referencias_remove(mysqli $conn, int $referenceId, int $authorId): bool
{
    $stmt = $conn->prepare("UPDATE pos_referencias_visuais SET removido_em = NOW(), removido_por_colaborador_id = ? WHERE id = ? AND origem <> 'render_principal' AND removido_em IS NULL");
    $stmt->bind_param('ii', $authorId, $referenceId);
    $stmt->execute();
    $removed = $stmt->affected_rows > 0;
    $stmt->close();
    if ($removed) {
        $history = $conn->prepare("INSERT INTO pos_referencias_visuais_historico (referencia_id, acao, colaborador_id, dados_json) VALUES (?, 'REMOVIDA', ?, NULL)");
        $history->bind_param('ii', $referenceId, $authorId);
        $history->execute();
        $history->close();
    }
    return $removed;
}

function pos_referencias_sftp_connect(): array
{
    $autoload = dirname(__DIR__) . '/FlowReview/vendor/autoload.php';
    if (!class_exists('phpseclib3\\Net\\SFTP')) {
        if (!is_file($autoload)) {
            throw new RuntimeException('Biblioteca SFTP não está disponível para enviar a referência.');
        }
        require_once $autoload;
    }
    require_once dirname(__DIR__) . '/config/secure_env.php';

    $config = improov_sftp_config('IMPROOV_VPS_SFTP');
    $host = trim((string) ($config['host'] ?? ''));
    if ($host !== '72.60.137.192') {
        throw new RuntimeException('O envio de referências exige o SFTP VPS 72.60.137.192.');
    }
    $basePath = rtrim((string) improov_env('IMPROOV_VPS_SFTP_REMOTE_PATH'), '/');
    if ($basePath === '') {
        throw new RuntimeException('Caminho remoto do SFTP VPS não configurado.');
    }

    $sftp = new \phpseclib3\Net\SFTP($host, (int) $config['port'], 60);
    if (!$sftp->login((string) $config['user'], (string) $config['pass'])) {
        throw new RuntimeException('Não foi possível autenticar no SFTP VPS para enviar a referência.');
    }

    $directory = $basePath . '/uploads/pos_referencias';
    if (!$sftp->is_dir($directory) && !$sftp->mkdir($directory, -1, true)) {
        throw new RuntimeException('Não foi possível preparar o diretório remoto de referências.');
    }

    return [$sftp, $directory];
}

function pos_referencias_cleanup_uploaded_files(array $savedFiles): void
{
    $remotePaths = array_values(array_filter(array_column($savedFiles, 'remote_path')));
    if (!$remotePaths) return;

    try {
        [$sftp] = pos_referencias_sftp_connect();
        foreach ($remotePaths as $remotePath) {
            $sftp->delete((string) $remotePath);
        }
    } catch (Throwable $e) {
        // A transação do banco já falhou. O histórico não deve ser afetado por
        // uma falha de limpeza do arquivo remoto; o nome aleatório evita colisão.
    }
}

function pos_referencias_insert_uploads(mysqli $conn, int $posId, int $colaboradorId, array $files): array
{
    if (empty($files['name']) || !is_array($files['name'])) return [];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $saved = [];
    $maxBytes = 10 * 1024 * 1024;
    $sftp = null;
    $remoteDirectory = '';
    $orderBase = 0;
    $orderStmt = $conn->prepare('SELECT COALESCE(MAX(ordem), -1) FROM pos_referencias_visuais WHERE pos_producao_id = ?');
    $orderStmt->bind_param('i', $posId);
    $orderStmt->execute();
    $orderBase = (int)$orderStmt->get_result()->fetch_row()[0] + 1;
    $orderStmt->close();

    foreach ($files['name'] as $index => $originalName) {
        if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if (($files['error'][$index] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Falha no upload da referência.');
        $tmp = $files['tmp_name'][$index] ?? '';
        $size = (int)($files['size'][$index] ?? 0);
        if (!$tmp || $size <= 0 || $size > $maxBytes) throw new RuntimeException('Cada referência deve ter no máximo 10 MB.');
        $mime = $finfo->file($tmp);
        if (!isset($allowed[$mime])) throw new RuntimeException('Formato de referência não permitido. Use JPG, PNG ou WEBP.');

        $checksum = hash_file('sha256', $tmp);
        $duplicate = $conn->prepare('SELECT id FROM pos_referencias_visuais WHERE pos_producao_id = ? AND checksum_sha256 = ? AND removido_em IS NULL LIMIT 1');
        $duplicate->bind_param('is', $posId, $checksum);
        $duplicate->execute();
        $alreadyExists = $duplicate->get_result()->num_rows > 0;
        $duplicate->close();
        if ($alreadyExists) continue;

        if ($sftp === null) {
            [$sftp, $remoteDirectory] = pos_referencias_sftp_connect();
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $remotePath = $remoteDirectory . '/' . $stored;
        if (!$sftp->put($remotePath, $tmp, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE)) {
            throw new RuntimeException('Não foi possível enviar a referência para o SFTP VPS.');
        }
        $saved[] = ['remote_path' => $remotePath, 'input_index' => (int)$index];
        $order = $orderBase + $index;
        $safeName = mb_substr(basename((string)$originalName), 0, 255);
        $stmt = $conn->prepare("INSERT INTO pos_referencias_visuais
            (pos_producao_id, arquivo, nome_original, mime_type, tamanho_bytes, checksum_sha256, origem, ordem, criado_por_colaborador_id)
            VALUES (?, ?, ?, ?, ?, ?, 'upload', ?, ?)");
        $stmt->bind_param('isssisii', $posId, $stored, $safeName, $mime, $size, $checksum, $order, $colaboradorId);
        if (!$stmt->execute()) throw new RuntimeException('Não foi possível registrar a referência.');
        $referenceId = (int)$conn->insert_id;
        $saved[count($saved) - 1]['reference_id'] = $referenceId;
        $stmt->close();
        $history = $conn->prepare("INSERT INTO pos_referencias_visuais_historico
            (referencia_id, acao, colaborador_id, dados_json) VALUES (?, 'CRIADA', ?, ?)");
        $details = json_encode(['arquivo' => $stored, 'nome_original' => $safeName], JSON_UNESCAPED_UNICODE);
        $history->bind_param('iis', $referenceId, $colaboradorId, $details);
        $history->execute();
        $history->close();
    }
    return $saved;
}
