-- Planejamento visual de dependencias da Pre-Alteracao.
-- Fonte operacional: dados relacionais; posicoes e metadados visuais ficam em JSON.

CREATE TABLE IF NOT EXISTS pre_alt_diagramas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pre_alt_lote_id INT UNSIGNED NOT NULL,
    nome VARCHAR(160) NOT NULL,
    status ENUM('RASCUNHO','VALIDADO','PUBLICADO','ARQUIVADO') NOT NULL DEFAULT 'RASCUNHO',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY ux_pre_alt_diagramas_lote (pre_alt_lote_id),
    KEY idx_pre_alt_diagramas_status (status, updated_at),
    CONSTRAINT fk_pre_alt_diagramas_lote
        FOREIGN KEY (pre_alt_lote_id) REFERENCES pre_alt_lote (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pre_alt_diagrama_grupos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    diagrama_id INT UNSIGNED NOT NULL,
    nome VARCHAR(120) NOT NULL,
    responsavel_id INT UNSIGNED NULL,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    pos_x DECIMAL(10,2) NULL,
    pos_y DECIMAL(10,2) NULL,
    width DECIMAL(10,2) NULL,
    height DECIMAL(10,2) NULL,
    visual_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pre_alt_diag_grupos_diagrama (diagrama_id, ordem),
    CONSTRAINT fk_pre_alt_diag_grupos_diagrama
        FOREIGN KEY (diagrama_id) REFERENCES pre_alt_diagramas (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pre_alt_diagrama_itens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    diagrama_id INT UNSIGNED NOT NULL,
    pre_alt_item_id INT UNSIGNED NOT NULL,
    grupo_id INT UNSIGNED NULL,
    responsavel_id INT UNSIGNED NULL,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    pos_x DECIMAL(10,2) NULL,
    pos_y DECIMAL(10,2) NULL,
    width DECIMAL(10,2) NULL,
    height DECIMAL(10,2) NULL,
    visual_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY ux_pre_alt_diag_item (diagrama_id, pre_alt_item_id),
    KEY idx_pre_alt_diag_itens_grupo (grupo_id),
    CONSTRAINT fk_pre_alt_diag_itens_diagrama
        FOREIGN KEY (diagrama_id) REFERENCES pre_alt_diagramas (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_pre_alt_diag_itens_pre_alt
        FOREIGN KEY (pre_alt_item_id) REFERENCES pre_alt_itens (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_pre_alt_diag_itens_grupo
        FOREIGN KEY (grupo_id) REFERENCES pre_alt_diagrama_grupos (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pre_alt_diagrama_gates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    diagrama_id INT UNSIGNED NOT NULL,
    titulo VARCHAR(160) NOT NULL,
    gate_tipo ENUM('APROVACAO','FINALIZACAO','MANUAL') NOT NULL DEFAULT 'APROVACAO',
    pos_x DECIMAL(10,2) NULL,
    pos_y DECIMAL(10,2) NULL,
    width DECIMAL(10,2) NULL,
    height DECIMAL(10,2) NULL,
    visual_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pre_alt_diag_gates_diagrama (diagrama_id),
    CONSTRAINT fk_pre_alt_diag_gates_diagrama
        FOREIGN KEY (diagrama_id) REFERENCES pre_alt_diagramas (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pre_alt_diagrama_dependencias (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    diagrama_id INT UNSIGNED NOT NULL,
    origem_tipo ENUM('GRUPO','ITEM','GATE') NOT NULL,
    origem_id INT UNSIGNED NOT NULL,
    destino_tipo ENUM('GRUPO','ITEM','GATE') NOT NULL,
    destino_id INT UNSIGNED NOT NULL,
    condicao ENUM('APROVADA','FINALIZADA') NOT NULL DEFAULT 'APROVADA',
    agregacao ENUM('ALL') NOT NULL DEFAULT 'ALL',
    observacao TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY ux_pre_alt_diag_dep_logica (
        diagrama_id,
        origem_tipo,
        origem_id,
        destino_tipo,
        destino_id,
        condicao,
        agregacao
    ),
    KEY idx_pre_alt_diag_dep_origem (origem_tipo, origem_id),
    KEY idx_pre_alt_diag_dep_destino (destino_tipo, destino_id),
    CONSTRAINT fk_pre_alt_diag_deps_diagrama
        FOREIGN KEY (diagrama_id) REFERENCES pre_alt_diagramas (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
