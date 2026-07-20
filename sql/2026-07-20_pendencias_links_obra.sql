CREATE TABLE IF NOT EXISTS pendencias_links_obra (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    obra_id INT NOT NULL,
    tipo_link VARCHAR(30) NOT NULL,
    origem VARCHAR(50) NOT NULL,
    status_id INT NULL,
    entrega_id INT NULL,
    status ENUM('aberta','concluida') NOT NULL DEFAULT 'aberta',
    criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    concluida_em DATETIME NULL,
    concluida_por INT NULL,
    atualizada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY ux_pendencias_links_obra_tipo (obra_id, tipo_link),
    KEY idx_pendencias_links_status (status),
    KEY idx_pendencias_links_entrega (entrega_id),
    KEY idx_pendencias_links_status_etapa (status_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
