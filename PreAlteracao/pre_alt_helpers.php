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

function pre_alt_recalcular_status_lote(mysqli $conn, int $loteId): string
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
            SUM(CASE WHEN resultado IS NULL THEN 1 ELSE 0 END) AS sem_resultado,
            SUM(CASE WHEN resultado = 'ALTERACAO' AND nivel_complexidade IS NULL THEN 1 ELSE 0 END) AS alteracao_sem_nivel,
            SUM(CASE WHEN resultado = 'AGUARDANDO_CLIENTE' OR necessita_retorno = 1 THEN 1 ELSE 0 END) AS aguardando
         FROM pre_alt_itens
         WHERE pre_alt_lote_id = ?"
    );
    if (!$stmt) {
        return 'EM_TRIAGEM';
    }

    $stmt->bind_param('i', $loteId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $total = (int) ($row['total'] ?? 0);
    $incompletos = (int) ($row['sem_resultado'] ?? 0) + (int) ($row['alteracao_sem_nivel'] ?? 0);
    $aguardando = (int) ($row['aguardando'] ?? 0);

    if ($total === 0) {
        $novoStatus = 'EM_TRIAGEM';
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
            "INSERT INTO pre_alt_lote (obra_id, status_id, data_finalizacao_cliente, status, created_by)
             VALUES (?, ?, ?, 'EM_TRIAGEM', ?)"
        );
        if (!$stmtInsert) {
            throw new RuntimeException('Nao foi possivel criar o lote de pre-alteracao.');
        }
        $stmtInsert->bind_param('iisi', $obraId, $statusId, $dataFinalizacaoCliente, $createdBy);
        $stmtInsert->execute();
        $loteId = (int) $stmtInsert->insert_id;
        $stmtInsert->close();
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
