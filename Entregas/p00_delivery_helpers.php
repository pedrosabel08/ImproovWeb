<?php

function improov_p00_table_has_column(mysqli $conn, string $table, string $column): bool
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

function improov_p00_acomp_has_metadata(mysqli $conn): bool
{
    return improov_p00_table_has_column($conn, 'acompanhamento_email', 'metadata');
}

function improov_p00_ensure_schema(mysqli $conn): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    if (!improov_p00_table_has_column($conn, 'entregas', 'tipo_entrega')) {
        @$conn->query(
            "ALTER TABLE entregas
             ADD COLUMN tipo_entrega VARCHAR(20) NOT NULL DEFAULT 'PADRAO' AFTER status_id"
        );
    }

    @$conn->query(
        "CREATE TABLE IF NOT EXISTS entregas_p00_versoes (
            id INT NOT NULL AUTO_INCREMENT,
            entrega_id INT NOT NULL,
            versao_num INT NOT NULL,
            versao_label VARCHAR(20) NOT NULL,
            versao_origem_id INT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Pendente',
            imagem_id INT NULL,
            funcao_imagem_id INT NULL,
            historico_id INT NULL,
            arquivo_principal VARCHAR(255) NULL,
            arquivos_json MEDIUMTEXT NULL,
            nas_path VARCHAR(255) NULL,
            data_prevista DATE NULL,
            data_aprovacao DATETIME NULL,
            data_entregue DATETIME NULL,
            origem_alteracao VARCHAR(50) NULL,
            origem_alteracao_detalhe VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_entregas_p00_versao (entrega_id, versao_num),
            KEY idx_entregas_p00_entrega_status (entrega_id, status),
            KEY idx_entregas_p00_versao_origem (versao_origem_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    if (!improov_p00_table_has_column($conn, 'entregas_p00_versoes', 'versao_origem_id')) {
        @$conn->query(
            "ALTER TABLE entregas_p00_versoes
             ADD COLUMN versao_origem_id INT NULL AFTER versao_label"
        );
    }

    if (!improov_p00_table_has_column($conn, 'entregas_p00_versoes', 'origem_alteracao')) {
        @$conn->query(
            "ALTER TABLE entregas_p00_versoes
             ADD COLUMN origem_alteracao VARCHAR(50) NULL AFTER data_entregue"
        );
    }

    if (!improov_p00_table_has_column($conn, 'entregas_p00_versoes', 'origem_alteracao_detalhe')) {
        @$conn->query(
            "ALTER TABLE entregas_p00_versoes
             ADD COLUMN origem_alteracao_detalhe VARCHAR(255) NULL AFTER origem_alteracao"
        );
    }

    $ready = true;
}

function improov_p00_build_version_label(int $versionNumber): string
{
    return 'V' . max(1, $versionNumber);
}

function improov_p00_fetch_status_name(mysqli $conn, int $statusId): ?string
{
    $stmt = $conn->prepare('SELECT nome_status FROM status_imagem WHERE idstatus = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $statusId);
    $stmt->execute();
    $stmt->bind_result($statusName);
    $found = $stmt->fetch();
    $stmt->close();

    return $found ? (string) $statusName : null;
}

function improov_p00_is_status(mysqli $conn, int $statusId, string $expected): bool
{
    $statusName = improov_p00_fetch_status_name($conn, $statusId);
    return mb_strtoupper(trim((string) $statusName), 'UTF-8') === mb_strtoupper(trim($expected), 'UTF-8');
}

function improov_p00_fetch_image_context(mysqli $conn, int $imageId): ?array
{
    $sql = "SELECT
                i.idimagens_cliente_obra AS imagem_id,
                i.obra_id,
                i.substatus_id,
                i.tipo_imagem,
                s.nome_status,
                o.nomenclatura
            FROM imagens_cliente_obra i
            JOIN status_imagem s ON s.idstatus = i.status_id
            JOIN obra o ON o.idobra = i.obra_id
            WHERE i.idimagens_cliente_obra = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
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

function improov_p00_is_candidate_image(array $imageRow): bool
{
    $statusName = mb_strtoupper(trim((string) ($imageRow['nome_status'] ?? '')), 'UTF-8');
    $imageType = mb_strtolower(trim((string) ($imageRow['tipo_imagem'] ?? '')), 'UTF-8');

    return $statusName === 'P00'
        && in_array($imageType, ['fachada', 'imagem externa'], true);
}

function improov_p00_next_acompanhamento_ordem(mysqli $conn, int $obraId): int
{
    $stmt = $conn->prepare('SELECT IFNULL(MAX(ordem), 0) + 1 AS next_ordem FROM acompanhamento_email WHERE obra_id = ?');
    if (!$stmt) {
        return 1;
    }

    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return isset($row['next_ordem']) ? (int) $row['next_ordem'] : 1;
}

function improov_p00_has_pending_handoff(mysqli $conn, int $obraId): bool
{
    $stmt = $conn->prepare(
        "SELECT 1
         FROM acompanhamento_email
         WHERE obra_id = ?
           AND tipo = 'P00_HANDOFF'
           AND status = 'pendente'
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

function improov_p00_register_handoff_for_image(mysqli $conn, int $imageId, ?int $colaboradorId = null): bool
{
    improov_p00_ensure_schema($conn);

    $image = improov_p00_fetch_image_context($conn, $imageId);
    if (!$image || !improov_p00_is_candidate_image($image)) {
        return false;
    }

    if ((int) ($image['substatus_id'] ?? 0) !== 2) {
        return false;
    }

    $obraId = (int) ($image['obra_id'] ?? 0);
    if ($obraId <= 0 || improov_p00_has_pending_handoff($conn, $obraId)) {
        return false;
    }

    $ordem = improov_p00_next_acompanhamento_ordem($conn, $obraId);
    $assunto = 'Handoff P00 disponível para a obra ' . ((string) ($image['nomenclatura'] ?? ('#' . $obraId))) . '.';
    $data = date('Y-m-d');
    $tipo = 'P00_HANDOFF';
    $status = 'pendente';
    $metadataJson = json_encode([
        'imagem_id' => (int) ($image['imagem_id'] ?? 0),
        'motivo' => 'substatus_todo',
        'status' => (string) ($image['nome_status'] ?? ''),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (improov_p00_acomp_has_metadata($conn)) {
        $stmt = $conn->prepare(
            'INSERT INTO acompanhamento_email (obra_id, colaborador_id, assunto, data, ordem, tipo, status, metadata) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('iississs', $obraId, $colaboradorId, $assunto, $data, $ordem, $tipo, $status, $metadataJson);
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO acompanhamento_email (obra_id, colaborador_id, assunto, data, ordem, tipo, status) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('iississ', $obraId, $colaboradorId, $assunto, $data, $ordem, $tipo, $status);
    }

    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function improov_p00_resolve_handoff(mysqli $conn, int $obraId, int $entregaId): void
{
    $stmt = $conn->prepare(
        "UPDATE acompanhamento_email
         SET status = 'resolvido', entrega_id = ?
         WHERE obra_id = ?
           AND tipo = 'P00_HANDOFF'
           AND status = 'pendente'"
    );
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('ii', $entregaId, $obraId);
    $stmt->execute();
    $stmt->close();
}

function improov_p00_fetch_pending_handoff_counts(mysqli $conn): array
{
    $counts = [];
    $result = $conn->query(
        "SELECT obra_id, COUNT(*) AS total
         FROM acompanhamento_email
         WHERE tipo = 'P00_HANDOFF'
           AND status = 'pendente'
         GROUP BY obra_id"
    );
    if (!$result) {
        return $counts;
    }

    while ($row = $result->fetch_assoc()) {
        $counts[(string) $row['obra_id']] = (int) ($row['total'] ?? 0);
    }

    return $counts;
}

function improov_p00_create_initial_version(mysqli $conn, int $entregaId, ?int $imageId, ?string $dueDate): int
{
    improov_p00_ensure_schema($conn);

    $versionNumber = 1;
    $versionLabel = improov_p00_build_version_label($versionNumber);
    $stmt = $conn->prepare(
        'INSERT INTO entregas_p00_versoes (entrega_id, versao_num, versao_label, status, imagem_id, data_prevista) VALUES (?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar versão inicial P00: ' . $conn->error);
    }

    $status = 'Pendente';
    $stmt->bind_param('iissis', $entregaId, $versionNumber, $versionLabel, $status, $imageId, $dueDate);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Erro ao criar versão inicial P00: ' . $error);
    }

    $versionId = (int) $stmt->insert_id;
    $stmt->close();

    return $versionId;
}

function improov_p00_fetch_latest_version(mysqli $conn, int $entregaId): ?array
{
    improov_p00_ensure_schema($conn);

    $stmt = $conn->prepare(
        'SELECT * FROM entregas_p00_versoes WHERE entrega_id = ? ORDER BY versao_num DESC, id DESC LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $entregaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function improov_p00_fetch_version_by_id(mysqli $conn, int $versionId): ?array
{
    improov_p00_ensure_schema($conn);

    $stmt = $conn->prepare('SELECT * FROM entregas_p00_versoes WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $versionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function improov_p00_update_version_status(mysqli $conn, int $versionId, string $status): void
{
    improov_p00_ensure_schema($conn);

    $stmt = $conn->prepare(
        'UPDATE entregas_p00_versoes
         SET status = ?,
             updated_at = NOW()
         WHERE id = ?'
    );
    if (!$stmt) {
        throw new RuntimeException('Erro ao atualizar status da versão P00: ' . $conn->error);
    }

    $stmt->bind_param('si', $status, $versionId);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Erro ao atualizar status da versão P00: ' . $error);
    }

    $stmt->close();
}

function improov_p00_create_followup_version(mysqli $conn, int $sourceVersionId, array $payload = []): int
{
    improov_p00_ensure_schema($conn);

    $sourceVersion = improov_p00_fetch_version_by_id($conn, $sourceVersionId);
    if (!$sourceVersion) {
        throw new RuntimeException('Versão P00 de origem não encontrada.');
    }

    $entregaId = (int) ($sourceVersion['entrega_id'] ?? 0);
    if ($entregaId <= 0) {
        throw new RuntimeException('Versão P00 sem entrega vinculada.');
    }

    $stmtNext = $conn->prepare('SELECT COALESCE(MAX(versao_num), 0) + 1 AS next_version FROM entregas_p00_versoes WHERE entrega_id = ?');
    if (!$stmtNext) {
        throw new RuntimeException('Erro ao calcular próxima versão P00: ' . $conn->error);
    }

    $stmtNext->bind_param('i', $entregaId);
    $stmtNext->execute();
    $nextRow = $stmtNext->get_result()->fetch_assoc();
    $stmtNext->close();

    $versionNumber = isset($nextRow['next_version']) ? (int) $nextRow['next_version'] : 1;
    $versionLabel = improov_p00_build_version_label($versionNumber);
    $status = 'Pendente';
    $imagemId = isset($sourceVersion['imagem_id']) ? (int) $sourceVersion['imagem_id'] : null;
    $funcaoImagemId = isset($sourceVersion['funcao_imagem_id']) ? (int) $sourceVersion['funcao_imagem_id'] : null;
    $dataPrevista = isset($sourceVersion['data_prevista']) ? (string) $sourceVersion['data_prevista'] : null;
    $origemAlteracao = isset($payload['origem_alteracao']) ? trim((string) $payload['origem_alteracao']) : null;
    $origemDetalhe = isset($payload['origem_alteracao_detalhe']) ? trim((string) $payload['origem_alteracao_detalhe']) : null;
    $origemAlteracao = $origemAlteracao !== '' ? substr($origemAlteracao, 0, 50) : null;
    $origemDetalhe = $origemDetalhe !== '' ? substr($origemDetalhe, 0, 255) : null;

    $stmt = $conn->prepare(
        'INSERT INTO entregas_p00_versoes (
            entrega_id,
            versao_origem_id,
            versao_num,
            versao_label,
            status,
            imagem_id,
            funcao_imagem_id,
            data_prevista,
            origem_alteracao,
            origem_alteracao_detalhe
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar nova versão P00: ' . $conn->error);
    }

    $stmt->bind_param(
        'iiissiisss',
        $entregaId,
        $sourceVersionId,
        $versionNumber,
        $versionLabel,
        $status,
        $imagemId,
        $funcaoImagemId,
        $dataPrevista,
        $origemAlteracao,
        $origemDetalhe
    );

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Erro ao criar nova versão P00: ' . $error);
    }

    $newVersionId = (int) $stmt->insert_id;
    $stmt->close();

    return $newVersionId;
}

function improov_p00_fetch_latest_delivery(mysqli $conn, int $obraId, int $statusId): ?array
{
    improov_p00_ensure_schema($conn);

    $stmt = $conn->prepare(
        "SELECT id, obra_id, status_id, data_prevista, status, tipo_entrega
         FROM entregas
         WHERE obra_id = ?
           AND status_id = ?
           AND tipo_entrega = 'P00'
           AND (arquivada IS NULL OR arquivada = 0)
         ORDER BY id DESC
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ii', $obraId, $statusId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function improov_p00_mark_version_ready(mysqli $conn, int $versionId, array $payload): void
{
    improov_p00_ensure_schema($conn);

    $stmt = $conn->prepare(
        'UPDATE entregas_p00_versoes
         SET status = ?,
             funcao_imagem_id = ?,
             historico_id = ?,
             arquivo_principal = ?,
             arquivos_json = ?,
             nas_path = ?,
             data_aprovacao = NOW(),
             updated_at = NOW()
         WHERE id = ?'
    );
    if (!$stmt) {
        throw new RuntimeException('Erro ao atualizar versão P00: ' . $conn->error);
    }

    $status = (string) ($payload['status'] ?? 'Entrega pendente');
    $funcaoImagemId = isset($payload['funcao_imagem_id']) ? (int) $payload['funcao_imagem_id'] : null;
    $historicoId = isset($payload['historico_id']) ? (int) $payload['historico_id'] : null;
    $arquivoPrincipal = isset($payload['arquivo_principal']) ? (string) $payload['arquivo_principal'] : null;
    $arquivosJson = isset($payload['arquivos_json']) ? (string) $payload['arquivos_json'] : null;
    $nasPath = isset($payload['nas_path']) ? (string) $payload['nas_path'] : null;
    $stmt->bind_param('siisssi', $status, $funcaoImagemId, $historicoId, $arquivoPrincipal, $arquivosJson, $nasPath, $versionId);

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Erro ao persistir versão P00: ' . $error);
    }

    $stmt->close();
}