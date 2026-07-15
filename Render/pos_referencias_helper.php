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

function pos_referencias_list(mysqli $conn, int $posId): array
{
    $stmt = $conn->prepare("SELECT id, arquivo, nome_original, mime_type, tamanho_bytes, checksum_sha256, ordem, criado_em
        FROM pos_referencias_visuais
        WHERE pos_producao_id = ? AND removido_em IS NULL
        ORDER BY ordem, id");
    $stmt->bind_param('i', $posId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function pos_referencias_insert_uploads(mysqli $conn, int $posId, int $colaboradorId, array $files): array
{
    if (empty($files['name']) || !is_array($files['name'])) return [];
    $directory = dirname(__DIR__) . '/uploads/pos_referencias';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Não foi possível preparar o diretório de referências.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $saved = [];
    $maxBytes = 10 * 1024 * 1024;
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

        $stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $target = $directory . '/' . $stored;
        if (!move_uploaded_file($tmp, $target)) throw new RuntimeException('Não foi possível salvar a referência.');
        $saved[] = $target;
        $order = $orderBase + $index;
        $safeName = mb_substr(basename((string)$originalName), 0, 255);
        $stmt = $conn->prepare("INSERT INTO pos_referencias_visuais
            (pos_producao_id, arquivo, nome_original, mime_type, tamanho_bytes, checksum_sha256, ordem, criado_por_colaborador_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isssisii', $posId, $stored, $safeName, $mime, $size, $checksum, $order, $colaboradorId);
        if (!$stmt->execute()) throw new RuntimeException('Não foi possível registrar a referência.');
        $referenceId = (int)$conn->insert_id;
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
