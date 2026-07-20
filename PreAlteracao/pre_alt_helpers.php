<?php

function pre_alt_ensure_schema(mysqli $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    $conn->query("CREATE TABLE IF NOT EXISTS pre_alt_lote (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        obra_id INT NOT NULL,
        status_id INT NOT NULL,
        data_finalizacao_cliente DATE NOT NULL,
        status ENUM('EM_TRIAGEM','AGUARDANDO_CLIENTE','PRONTO_PLANEJAMENTO','PLANEJADO','CANCELADO') NOT NULL DEFAULT 'EM_TRIAGEM',
        prioridade ENUM('BAIXA','NORMAL','ALTA','CRITICA') NOT NULL DEFAULT 'NORMAL',
        prazo DATE NULL,
        responsavel_id INT UNSIGNED NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_pre_alt_lote_status (status, data_finalizacao_cliente),
        KEY idx_pre_alt_lote_obra_status_data (obra_id, status_id, data_finalizacao_cliente),
        CONSTRAINT fk_pre_alt_lote_obra FOREIGN KEY (obra_id) REFERENCES obra (idobra) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS pre_alt_lote_batches (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        pre_alt_lote_id INT UNSIGNED NOT NULL,
        review_batch_id INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY ux_pre_alt_lote_batch (pre_alt_lote_id, review_batch_id),
        KEY idx_pre_alt_lote_batches_batch (review_batch_id),
        CONSTRAINT fk_pre_alt_lote_batches_lote FOREIGN KEY (pre_alt_lote_id) REFERENCES pre_alt_lote (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_pre_alt_lote_batches_batch FOREIGN KEY (review_batch_id) REFERENCES review_batch (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS pre_alt_itens (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        pre_alt_lote_id INT UNSIGNED NOT NULL,
        review_batch_item_id INT NOT NULL,
        entrega_id INT NOT NULL,
        entrega_item_id INT NULL,
        imagem_id INT NOT NULL,
        resultado ENUM('ALTERACAO','SEM_ALTERACAO','AGUARDANDO_CLIENTE') NULL DEFAULT 'ALTERACAO',
        nivel_complexidade TINYINT UNSIGNED NULL,
        tipo_alteracao VARCHAR(80) NULL,
        acao TEXT NULL,
        necessita_retorno TINYINT(1) NOT NULL DEFAULT 0,
        quantidade_comentarios INT UNSIGNED NULL,
        responsavel_id INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY ux_pre_alt_item_batch_item (review_batch_item_id),
        KEY idx_pre_alt_itens_lote (pre_alt_lote_id),
        KEY idx_pre_alt_itens_imagem (imagem_id),
        KEY idx_pre_alt_itens_resultado (resultado, nivel_complexidade),
        CONSTRAINT fk_pre_alt_itens_lote FOREIGN KEY (pre_alt_lote_id) REFERENCES pre_alt_lote (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_pre_alt_itens_review_item FOREIGN KEY (review_batch_item_id) REFERENCES review_batch_items (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_pre_alt_itens_entrega FOREIGN KEY (entrega_id) REFERENCES entregas (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_pre_alt_itens_imagem FOREIGN KEY (imagem_id) REFERENCES imagens_cliente_obra (idimagens_cliente_obra) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (!pre_alt_column_exists($conn, 'pre_alt_lote', 'prioridade')) {
        $conn->query("ALTER TABLE pre_alt_lote ADD COLUMN prioridade ENUM('BAIXA','NORMAL','ALTA','CRITICA') NOT NULL DEFAULT 'NORMAL' AFTER status");
    }
    if (!pre_alt_column_exists($conn, 'pre_alt_lote', 'prazo')) {
        $conn->query("ALTER TABLE pre_alt_lote ADD COLUMN prazo DATE NULL AFTER prioridade");
    }
    if (!pre_alt_column_exists($conn, 'pre_alt_lote', 'responsavel_id')) {
        $conn->query("ALTER TABLE pre_alt_lote ADD COLUMN responsavel_id INT UNSIGNED NULL AFTER prazo");
    }
    if (!pre_alt_column_exists($conn, 'pre_alt_itens', 'quantidade_comentarios')) {
        $conn->query("ALTER TABLE pre_alt_itens ADD COLUMN quantidade_comentarios INT UNSIGNED NULL AFTER necessita_retorno");
    }
    if (!pre_alt_column_exists($conn, 'pre_alt_itens', 'reanalise_pos_retorno')) {
        $conn->query("ALTER TABLE pre_alt_itens ADD COLUMN reanalise_pos_retorno TINYINT(1) NOT NULL DEFAULT 0 AFTER quantidade_comentarios");
    }
    if (pre_alt_table_exists($conn, 'alteracoes') && !pre_alt_column_exists($conn, 'alteracoes', 'nivel_complexidade')) {
        $conn->query("ALTER TABLE alteracoes ADD COLUMN nivel_complexidade TINYINT UNSIGNED NULL AFTER status_id");
    }

    $conn->query("CREATE TABLE IF NOT EXISTS pre_alt_lote_historico (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        pre_alt_lote_id INT UNSIGNED NOT NULL,
        item_id INT UNSIGNED NULL,
        batch_id VARCHAR(36) NULL,
        tipo_evento VARCHAR(40) NOT NULL,
        campo VARCHAR(80) NULL,
        valor_anterior TEXT NULL,
        valor_novo TEXT NULL,
        observacao TEXT NULL,
        usuario_id INT UNSIGNED NULL,
        colaborador_id INT UNSIGNED NULL,
        contexto_json LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_pre_alt_hist_lote_data (pre_alt_lote_id, created_at),
        KEY idx_pre_alt_hist_batch (batch_id),
        KEY idx_pre_alt_hist_evento (tipo_evento),
        CONSTRAINT fk_pre_alt_hist_lote FOREIGN KEY (pre_alt_lote_id) REFERENCES pre_alt_lote (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS pre_alt_cliente_interacoes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        pre_alt_lote_id INT UNSIGNED NOT NULL,
        tipo ENUM('SOLICITACAO','RETORNO') NOT NULL,
        ocorrido_em DATETIME NOT NULL,
        resultado_retorno ENUM('APROVADA','ALTERACAO') NULL,
        observacao TEXT NULL,
        usuario_id INT UNSIGNED NULL,
        colaborador_id INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_pre_alt_cliente_interacao_lote_data (pre_alt_lote_id, ocorrido_em),
        KEY idx_pre_alt_cliente_interacao_tipo_data (tipo, ocorrido_em),
        CONSTRAINT fk_pre_alt_cliente_interacao_lote FOREIGN KEY (pre_alt_lote_id) REFERENCES pre_alt_lote (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS pre_alt_cliente_interacao_itens (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        interacao_id INT UNSIGNED NOT NULL,
        pre_alt_item_id INT UNSIGNED NOT NULL,
        estado_anterior_json LONGTEXT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY ux_pre_alt_cliente_interacao_item (interacao_id, pre_alt_item_id),
        KEY idx_pre_alt_cliente_interacao_itens_item (pre_alt_item_id),
        CONSTRAINT fk_pre_alt_cliente_interacao_item_interacao FOREIGN KEY (interacao_id) REFERENCES pre_alt_cliente_interacoes (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_pre_alt_cliente_interacao_item_pre_alt_item FOREIGN KEY (pre_alt_item_id) REFERENCES pre_alt_itens (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS pre_alt_liberacoes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        pre_alt_lote_id INT UNSIGNED NOT NULL,
        data_triagem DATE NOT NULL,
        entrega_ef_id INT NULL,
        entrega_alteracao_id INT NULL,
        observacao TEXT NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_pre_alt_liberacoes_lote_data (pre_alt_lote_id, created_at),
        KEY idx_pre_alt_liberacoes_entrega_ef (entrega_ef_id),
        KEY idx_pre_alt_liberacoes_entrega_alt (entrega_alteracao_id),
        CONSTRAINT fk_pre_alt_liberacoes_lote FOREIGN KEY (pre_alt_lote_id) REFERENCES pre_alt_lote (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_pre_alt_liberacoes_entrega_ef FOREIGN KEY (entrega_ef_id) REFERENCES entregas (id) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_pre_alt_liberacoes_entrega_alt FOREIGN KEY (entrega_alteracao_id) REFERENCES entregas (id) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS pre_alt_liberacao_itens (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        liberacao_id INT UNSIGNED NOT NULL,
        pre_alt_item_id INT UNSIGNED NOT NULL,
        entrega_destino_id INT NOT NULL,
        status_destino_id INT NOT NULL,
        prazo DATE NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY ux_pre_alt_liberacao_item (pre_alt_item_id),
        KEY idx_pre_alt_liberacao_itens_liberacao (liberacao_id),
        KEY idx_pre_alt_liberacao_itens_entrega (entrega_destino_id),
        CONSTRAINT fk_pre_alt_liberacao_itens_liberacao FOREIGN KEY (liberacao_id) REFERENCES pre_alt_liberacoes (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_pre_alt_liberacao_itens_item FOREIGN KEY (pre_alt_item_id) REFERENCES pre_alt_itens (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_pre_alt_liberacao_itens_entrega FOREIGN KEY (entrega_destino_id) REFERENCES entregas (id) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function pre_alt_column_exists(mysqli $conn, string $tableName, string $columnName): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function pre_alt_table_exists(mysqli $conn, string $tableName): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function pre_alt_schema_ready(mysqli $conn): bool
{
    $res = $conn->query(
        "SELECT COUNT(*) AS total
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name IN ('pre_alt_lote', 'pre_alt_lote_batches', 'pre_alt_itens')"
    );
    $row = $res ? $res->fetch_assoc() : null;
    return (int) ($row['total'] ?? 0) === 3;
}

function pre_alt_actor(): array
{
    return [
        'usuario_id' => isset($_SESSION['idusuario']) ? (int) $_SESSION['idusuario'] : null,
        'colaborador_id' => isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null,
    ];
}

function pre_alt_batch_id(): string
{
    try {
        return bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        return str_replace('.', '', uniqid('batch_', true));
    }
}

function pre_alt_hist_value($value): ?string
{
    if ($value === null) {
        return null;
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_scalar($value)) {
        return (string) $value;
    }
    return json_encode($value, JSON_UNESCAPED_UNICODE);
}

function pre_alt_registrar_historico(
    mysqli $conn,
    int $loteId,
    string $tipoEvento,
    ?string $campo = null,
    $valorAnterior = null,
    $valorNovo = null,
    ?string $observacao = null,
    ?int $itemId = null,
    ?string $batchId = null,
    ?array $contexto = null,
    ?int $usuarioId = null,
    ?int $colaboradorId = null
): void {
    if ($loteId <= 0 || !pre_alt_table_exists($conn, 'pre_alt_lote_historico')) {
        return;
    }

    $actor = pre_alt_actor();
    $usuarioId = $usuarioId ?? $actor['usuario_id'];
    $colaboradorId = $colaboradorId ?? $actor['colaborador_id'];
    $anterior = pre_alt_hist_value($valorAnterior);
    $novo = pre_alt_hist_value($valorNovo);
    $contextoJson = $contexto !== null ? json_encode($contexto, JSON_UNESCAPED_UNICODE) : null;

    $stmt = $conn->prepare(
        "INSERT INTO pre_alt_lote_historico (
            pre_alt_lote_id, item_id, batch_id, tipo_evento, campo,
            valor_anterior, valor_novo, observacao, usuario_id, colaborador_id, contexto_json
        ) VALUES (?, ?, NULLIF(?, ''), ?, NULLIF(?, ''), ?, ?, NULLIF(?, ''), ?, ?, ?)"
    );
    if (!$stmt) {
        return;
    }
    $batchValue = $batchId ?? '';
    $campoValue = $campo ?? '';
    $obsValue = $observacao ?? '';
    $stmt->bind_param(
        'iissssssiis',
        $loteId,
        $itemId,
        $batchValue,
        $tipoEvento,
        $campoValue,
        $anterior,
        $novo,
        $obsValue,
        $usuarioId,
        $colaboradorId,
        $contextoJson
    );
    @$stmt->execute();
    $stmt->close();
}

function pre_alt_buscar_interacoes_cliente(mysqli $conn, int $loteId): array
{
    if ($loteId <= 0 || !pre_alt_table_exists($conn, 'pre_alt_cliente_interacoes')) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT
            ci.id,
            ci.tipo,
            ci.ocorrido_em,
            ci.resultado_retorno,
            ci.observacao,
            ci.usuario_id,
            ci.colaborador_id,
            ci.created_at,
            c.nome_colaborador AS colaborador_nome
         FROM pre_alt_cliente_interacoes ci
         LEFT JOIN colaborador c ON c.idcolaborador = ci.colaborador_id
         WHERE ci.pre_alt_lote_id = ?
         ORDER BY ci.ocorrido_em DESC, ci.id DESC"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $loteId);
    $stmt->execute();
    $res = $stmt->get_result();

    $interacoes = [];
    while ($row = $res->fetch_assoc()) {
        $id = (int) $row['id'];
        $row['id'] = $id;
        $row['usuario_id'] = isset($row['usuario_id']) ? (int) $row['usuario_id'] : null;
        $row['colaborador_id'] = isset($row['colaborador_id']) ? (int) $row['colaborador_id'] : null;
        $row['itens'] = [];
        $interacoes[$id] = $row;
    }
    $stmt->close();

    if (!$interacoes) {
        return [];
    }

    $ids = implode(',', array_map('intval', array_keys($interacoes)));
    $resItens = $conn->query(
        "SELECT
            cii.interacao_id,
            pai.id AS item_id,
            pai.imagem_id,
            ico.imagem_nome AS nome_imagem,
            cii.estado_anterior_json
         FROM pre_alt_cliente_interacao_itens cii
         INNER JOIN pre_alt_itens pai ON pai.id = cii.pre_alt_item_id
         LEFT JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = pai.imagem_id
         WHERE cii.interacao_id IN ($ids)
         ORDER BY cii.id ASC"
    );
    if ($resItens) {
        while ($item = $resItens->fetch_assoc()) {
            $interacaoId = (int) $item['interacao_id'];
            $snapshot = null;
            if (!empty($item['estado_anterior_json'])) {
                $decoded = json_decode($item['estado_anterior_json'], true);
                $snapshot = is_array($decoded) ? $decoded : null;
            }
            $interacoes[$interacaoId]['itens'][] = [
                'item_id' => (int) $item['item_id'],
                'imagem_id' => (int) $item['imagem_id'],
                'nome_imagem' => (string) ($item['nome_imagem'] ?? ''),
                'estado_anterior' => $snapshot,
            ];
        }
    }

    return array_values($interacoes);
}

function pre_alt_recalcular_status_lote(mysqli $conn, int $loteId, ?string $batchId = null, ?string $observacao = null): string
{
    $statusAtual = null;
    $stmtAtual = $conn->prepare('SELECT status FROM pre_alt_lote WHERE id = ? LIMIT 1');
    if ($stmtAtual) {
        $stmtAtual->bind_param('i', $loteId);
        $stmtAtual->execute();
        $rowAtual = $stmtAtual->get_result()->fetch_assoc();
        $stmtAtual->close();
        $statusAtual = $rowAtual['status'] ?? null;
    }

    if (in_array($statusAtual, ['PLANEJADO', 'CANCELADO'], true)) {
        return $statusAtual;
    }

    $stmt = $conn->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN pli.id IS NULL THEN 1 ELSE 0 END) AS restantes,
            SUM(CASE WHEN pli.id IS NULL AND pai.resultado IS NULL THEN 1 ELSE 0 END) AS sem_resultado,
            SUM(CASE WHEN pli.id IS NULL AND pai.resultado = 'ALTERACAO' AND pai.nivel_complexidade IS NULL THEN 1 ELSE 0 END) AS alteracao_sem_nivel,
            SUM(CASE WHEN pli.id IS NULL AND (pai.resultado = 'AGUARDANDO_CLIENTE' OR pai.necessita_retorno = 1) THEN 1 ELSE 0 END) AS aguardando
         FROM pre_alt_itens pai
         LEFT JOIN pre_alt_liberacao_itens pli ON pli.pre_alt_item_id = pai.id
         WHERE pai.pre_alt_lote_id = ?"
    );
    if (!$stmt) {
        return 'EM_TRIAGEM';
    }

    $stmt->bind_param('i', $loteId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $total = (int) ($row['total'] ?? 0);
    $restantes = (int) ($row['restantes'] ?? 0);
    $incompletos = (int) ($row['sem_resultado'] ?? 0) + (int) ($row['alteracao_sem_nivel'] ?? 0);
    $aguardando = (int) ($row['aguardando'] ?? 0);

    if ($total === 0) {
        $novoStatus = 'EM_TRIAGEM';
    } elseif ($restantes === 0) {
        $novoStatus = 'PLANEJADO';
    } elseif ($aguardando > 0) {
        $novoStatus = 'AGUARDANDO_CLIENTE';
    } elseif ($incompletos > 0) {
        $novoStatus = 'EM_TRIAGEM';
    } else {
        $novoStatus = 'PRONTO_PLANEJAMENTO';
    }

    $stmtUpdate = $conn->prepare('UPDATE pre_alt_lote SET status = ?, updated_at = NOW() WHERE id = ?');
    if ($stmtUpdate) {
        $stmtUpdate->bind_param('si', $novoStatus, $loteId);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }

    if ($statusAtual !== null && $novoStatus !== $statusAtual) {
        pre_alt_registrar_historico(
            $conn,
            $loteId,
            'ALTERACAO_STATUS',
            'status',
            $statusAtual,
            $novoStatus,
            $observacao,
            null,
            $batchId
        );
    }

    return $novoStatus;
}

function pre_alt_criar_de_review_batch(mysqli $conn, int $reviewBatchId, ?int $createdBy = null, ?string $dataFinalizacaoCliente = null): int
{
    if (!pre_alt_schema_ready($conn)) {
        pre_alt_ensure_schema($conn);
    }

    $stmtBatch = $conn->prepare(
        "SELECT rb.id, rb.entrega_id, e.obra_id, e.status_id
         FROM review_batch rb
         INNER JOIN entregas e ON e.id = rb.entrega_id
         WHERE rb.id = ?
         LIMIT 1"
    );
    if (!$stmtBatch) {
        throw new RuntimeException('Nao foi possivel consultar o lote de review.');
    }
    $stmtBatch->bind_param('i', $reviewBatchId);
    $stmtBatch->execute();
    $batch = $stmtBatch->get_result()->fetch_assoc();
    $stmtBatch->close();

    if (!$batch) {
        throw new RuntimeException('Lote de review nao encontrado.');
    }

    $obraId = (int) $batch['obra_id'];
    $statusId = (int) $batch['status_id'];
    $dataFinalizacaoCliente = $dataFinalizacaoCliente ?: date('Y-m-d');

    $loteId = 0;
    $stmtFind = $conn->prepare(
        "SELECT id
         FROM pre_alt_lote
         WHERE obra_id = ?
           AND status_id = ?
           AND data_finalizacao_cliente = ?
           AND status NOT IN ('PLANEJADO', 'CANCELADO')
         ORDER BY id DESC
         LIMIT 1"
    );
    if ($stmtFind) {
        $stmtFind->bind_param('iis', $obraId, $statusId, $dataFinalizacaoCliente);
        $stmtFind->execute();
        $row = $stmtFind->get_result()->fetch_assoc();
        $stmtFind->close();
        $loteId = (int) ($row['id'] ?? 0);
    }

    if ($loteId <= 0) {
        $stmtInsert = $conn->prepare(
            "INSERT INTO pre_alt_lote (obra_id, status_id, data_finalizacao_cliente, status, created_by, responsavel_id)
             VALUES (?, ?, ?, 'EM_TRIAGEM', ?, ?)"
        );
        if (!$stmtInsert) {
            throw new RuntimeException('Nao foi possivel criar o lote de pre-alteracao.');
        }
        $stmtInsert->bind_param('iisii', $obraId, $statusId, $dataFinalizacaoCliente, $createdBy, $createdBy);
        $stmtInsert->execute();
        $loteId = (int) $stmtInsert->insert_id;
        $stmtInsert->close();

        pre_alt_registrar_historico(
            $conn,
            $loteId,
            'CRIACAO',
            'lote',
            null,
            'EM_TRIAGEM',
            'Triagem criada a partir do lote de Review.',
            null,
            null,
            ['review_batch_id' => $reviewBatchId]
        );
    }

    $stmtLink = $conn->prepare(
        "INSERT IGNORE INTO pre_alt_lote_batches (pre_alt_lote_id, review_batch_id)
         VALUES (?, ?)"
    );
    if ($stmtLink) {
        $stmtLink->bind_param('ii', $loteId, $reviewBatchId);
        $stmtLink->execute();
        $stmtLink->close();
    }

    $stmtItens = $conn->prepare(
        "INSERT INTO pre_alt_itens (
            pre_alt_lote_id,
            review_batch_item_id,
            entrega_id,
            entrega_item_id,
            imagem_id,
            resultado
        )
        SELECT
            ?,
            rbi.id,
            rb.entrega_id,
            rbi.entrega_item_id,
            rbi.imagem_id,
            'ALTERACAO'
        FROM review_batch_items rbi
        INNER JOIN review_batch rb ON rb.id = rbi.review_batch_id
        WHERE rbi.review_batch_id = ?
        ON DUPLICATE KEY UPDATE
            pre_alt_lote_id = VALUES(pre_alt_lote_id),
            entrega_id = VALUES(entrega_id),
            entrega_item_id = VALUES(entrega_item_id),
            imagem_id = VALUES(imagem_id),
            updated_at = NOW()"
    );
    if (!$stmtItens) {
        throw new RuntimeException('Nao foi possivel criar os itens de pre-alteracao.');
    }
    $stmtItens->bind_param('ii', $loteId, $reviewBatchId);
    $stmtItens->execute();
    $stmtItens->close();

    $stmtCount = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM pre_alt_itens pai
         INNER JOIN review_batch_items rbi ON rbi.id = pai.review_batch_item_id
         WHERE pai.pre_alt_lote_id = ?
           AND rbi.review_batch_id = ?"
    );
    if (!$stmtCount) {
        throw new RuntimeException('Nao foi possivel validar os itens de pre-alteracao.');
    }
    $stmtCount->bind_param('ii', $loteId, $reviewBatchId);
    $stmtCount->execute();
    $rowCount = $stmtCount->get_result()->fetch_assoc();
    $stmtCount->close();

    if ((int) ($rowCount['total'] ?? 0) <= 0) {
        throw new RuntimeException('Nenhum item do lote de review foi encontrado para enviar a Pre-Alteracao.');
    }

    pre_alt_recalcular_status_lote($conn, $loteId);

    return $loteId;
}

function pre_alt_nivel_label(?int $nivel): string
{
    $labels = [
        1 => 'Muito baixa (ajustes superficiais)',
        2 => 'Baixa (ajustes de acabamento)',
        3 => 'Media (revisao de composicao)',
        4 => 'Alta (revisao estrutural)',
        5 => 'Muito alta (alteracao de projeto)',
    ];

    return $labels[$nivel] ?? '';
}
