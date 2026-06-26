ALTER TABLE eventos_obra
    ADD COLUMN hora_evento TIME NULL AFTER data_evento,
    ADD COLUMN participantes TEXT NULL AFTER descricao,
    ADD COLUMN ata MEDIUMTEXT NULL AFTER participantes,
    ADD COLUMN origem_modulo VARCHAR(40) NULL DEFAULT NULL AFTER tipo_evento,
    ADD COLUMN created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER origem_modulo,
    ADD COLUMN updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD COLUMN arquivado_em DATETIME NULL AFTER updated_at,
    ADD COLUMN arquivado_por INT NULL AFTER arquivado_em;

ALTER TABLE eventos_obra
    ADD INDEX idx_eventos_obra_modulo_data (obra_id, origem_modulo, arquivado_em, data_evento);

CREATE TABLE IF NOT EXISTS evento_obra_referencias (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
