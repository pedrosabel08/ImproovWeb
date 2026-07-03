<?php

function fr_media_column_exists(mysqli $conn, string $table, string $column): bool
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
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    $stmt->close();
    return $exists;
}

function fr_media_index_exists(mysqli $conn, string $table, string $index): bool
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
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    $stmt->close();
    return $exists;
}

function fr_media_column_is_nullable(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare(
        "SELECT IS_NULLABLE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return true;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return !$row || strtoupper((string)($row['IS_NULLABLE'] ?? 'YES')) === 'YES';
}

function fr_approval_media_ensure_schema(mysqli $conn): void
{
    if (!fr_media_column_is_nullable($conn, 'historico_aprovacoes_imagens', 'funcao_imagem_id')) {
        @$conn->query("ALTER TABLE historico_aprovacoes_imagens MODIFY funcao_imagem_id INT NULL");
    }
    if (!fr_media_column_exists($conn, 'historico_aprovacoes_imagens', 'funcao_animacao_id')) {
        @$conn->query("ALTER TABLE historico_aprovacoes_imagens ADD COLUMN funcao_animacao_id INT NULL AFTER funcao_imagem_id");
    }
    if (!fr_media_column_exists($conn, 'historico_aprovacoes_imagens', 'media_tipo')) {
        @$conn->query("ALTER TABLE historico_aprovacoes_imagens ADD COLUMN media_tipo VARCHAR(20) NOT NULL DEFAULT 'imagem' AFTER caminho_imagem");
    }
    if (!fr_media_column_exists($conn, 'historico_aprovacoes_imagens', 'mime_type')) {
        @$conn->query("ALTER TABLE historico_aprovacoes_imagens ADD COLUMN mime_type VARCHAR(100) NULL AFTER media_tipo");
    }
    if (!fr_media_column_exists($conn, 'historico_aprovacoes_imagens', 'tamanho')) {
        @$conn->query("ALTER TABLE historico_aprovacoes_imagens ADD COLUMN tamanho BIGINT NULL AFTER mime_type");
    }
    if (!fr_media_column_exists($conn, 'historico_aprovacoes_imagens', 'duracao_ms')) {
        @$conn->query("ALTER TABLE historico_aprovacoes_imagens ADD COLUMN duracao_ms INT NULL AFTER tamanho");
    }
    if (!fr_media_column_exists($conn, 'historico_aprovacoes_imagens', 'poster_path')) {
        @$conn->query("ALTER TABLE historico_aprovacoes_imagens ADD COLUMN poster_path VARCHAR(255) NULL AFTER duracao_ms");
    }
    if (!fr_media_index_exists($conn, 'historico_aprovacoes_imagens', 'idx_hai_funcao_animacao')) {
        @$conn->query("CREATE INDEX idx_hai_funcao_animacao ON historico_aprovacoes_imagens (funcao_animacao_id, indice_envio, data_envio)");
    }
    if (!fr_media_index_exists($conn, 'historico_aprovacoes_imagens', 'idx_hai_media_tipo')) {
        @$conn->query("CREATE INDEX idx_hai_media_tipo ON historico_aprovacoes_imagens (media_tipo)");
    }

    if (!fr_media_column_is_nullable($conn, 'historico_aprovacoes', 'funcao_imagem_id')) {
        @$conn->query("ALTER TABLE historico_aprovacoes MODIFY funcao_imagem_id INT NULL");
    }
    if (!fr_media_column_exists($conn, 'historico_aprovacoes', 'funcao_animacao_id')) {
        @$conn->query("ALTER TABLE historico_aprovacoes ADD COLUMN funcao_animacao_id INT NULL AFTER funcao_imagem_id");
    }
    if (!fr_media_index_exists($conn, 'historico_aprovacoes', 'idx_ha_funcao_animacao')) {
        @$conn->query("CREATE INDEX idx_ha_funcao_animacao ON historico_aprovacoes (funcao_animacao_id, data_aprovacao)");
    }

    if (!fr_media_column_exists($conn, 'comentarios_imagem', 'video_time_ms')) {
        @$conn->query("ALTER TABLE comentarios_imagem ADD COLUMN video_time_ms INT NULL AFTER y");
    }
    if (!fr_media_index_exists($conn, 'comentarios_imagem', 'idx_comentarios_ap_video_time')) {
        @$conn->query("CREATE INDEX idx_comentarios_ap_video_time ON comentarios_imagem (ap_imagem_id, video_time_ms)");
    }

    @$conn->query(
        "UPDATE historico_aprovacoes_imagens hi
         INNER JOIN funcao_animacao fa ON fa.id = hi.funcao_imagem_id
         SET hi.funcao_animacao_id = fa.id,
             hi.funcao_imagem_id = NULL,
             hi.media_tipo = COALESCE(NULLIF(hi.media_tipo, ''), 'imagem')
         WHERE hi.funcao_animacao_id IS NULL
           AND hi.funcao_imagem_id IS NOT NULL
           AND (
                hi.nome_arquivo LIKE '%-ANI-%'
                OR hi.imagem LIKE '%-ANI-%'
                OR hi.caminho_imagem LIKE '%-ANI-%'
           )"
    );
}
