CREATE TABLE IF NOT EXISTS pos_referencias_visuais (
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
    KEY idx_pos_referencias_ativas (
        pos_producao_id,
        removido_em,
        ordem
    ),
    CONSTRAINT fk_pos_referencias_pos FOREIGN KEY (pos_producao_id) REFERENCES pos_producao (idpos_producao) ON DELETE CASCADE,
    CONSTRAINT fk_pos_referencias_criador FOREIGN KEY (criado_por_colaborador_id) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL,
    CONSTRAINT fk_pos_referencias_removedor FOREIGN KEY (removido_por_colaborador_id) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS pos_referencias_visuais_historico (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referencia_id BIGINT UNSIGNED NOT NULL,
    acao ENUM('CRIADA', 'REMOVIDA') NOT NULL,
    colaborador_id INT NULL,
    dados_json JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pos_referencias_historico_ref (referencia_id, criado_em),
    CONSTRAINT fk_pos_referencias_historico_ref FOREIGN KEY (referencia_id) REFERENCES pos_referencias_visuais (id) ON DELETE CASCADE,
    CONSTRAINT fk_pos_referencias_historico_colaborador FOREIGN KEY (colaborador_id) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
