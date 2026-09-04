-- ALMA V1 - Direcao Visual por imagem, biblioteca versionada e auditoria.
-- Requer MySQL 8.0+.
-- Aplicar antes de publicar o modulo ALMA e, em seguida, aplicar o seed
-- 2026-09-03_alma_biblioteca_v1_seed.sql.

CREATE TABLE IF NOT EXISTS alma_biblioteca_versao (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(20) NOT NULL,
    nome VARCHAR(120) NOT NULL,
    estado ENUM(
        'RASCUNHO',
        'PUBLICADA',
        'ARQUIVADA'
    ) NOT NULL DEFAULT 'RASCUNHO',
    origem_documento VARCHAR(255) NULL,
    checksum_origem CHAR(64) NULL,
    criada_por_usuario_id INT NULL,
    publicada_por_usuario_id INT NULL,
    criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    publicada_em DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alma_biblioteca_versao_codigo (codigo),
    KEY idx_alma_biblioteca_versao_estado (estado, publicada_em),
    CONSTRAINT fk_alma_biblioteca_versao_criador FOREIGN KEY (criada_por_usuario_id) REFERENCES usuario (idusuario) ON DELETE SET NULL,
    CONSTRAINT fk_alma_biblioteca_versao_publicador FOREIGN KEY (publicada_por_usuario_id) REFERENCES usuario (idusuario) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alma_biblioteca_dimensao (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    versao_id BIGINT UNSIGNED NOT NULL,
    dimensao_pai_id BIGINT UNSIGNED NULL,
    codigo VARCHAR(80) NOT NULL,
    etapa_codigo VARCHAR(32) NOT NULL,
    etapa_nome VARCHAR(40) NOT NULL,
    pilar_codigo VARCHAR(40) NOT NULL,
    pilar_nome VARCHAR(80) NOT NULL,
    nome VARCHAR(120) NOT NULL,
    tipo_conteudo ENUM('PILAR', 'DIMENSAO') NOT NULL,
    ordem_jornada SMALLINT UNSIGNED NOT NULL,
    ordem_no_pilar SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    permite_multiplas TINYINT(1) NOT NULL DEFAULT 0,
    exige_item_biblioteca TINYINT(1) NOT NULL DEFAULT 1,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alma_dimensao_versao_codigo (versao_id, codigo),
    KEY idx_alma_dimensao_jornada (
        versao_id,
        ordem_jornada,
        ordem_no_pilar
    ),
    KEY idx_alma_dimensao_pilar (versao_id, pilar_codigo),
    CONSTRAINT fk_alma_dimensao_versao FOREIGN KEY (versao_id) REFERENCES alma_biblioteca_versao (id) ON DELETE RESTRICT,
    CONSTRAINT fk_alma_dimensao_pai FOREIGN KEY (dimensao_pai_id) REFERENCES alma_biblioteca_dimensao (id) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alma_biblioteca_item (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    dimensao_id BIGINT UNSIGNED NOT NULL,
    codigo VARCHAR(120) NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    resumo TEXT NULL,
    diferenca_principal TEXT NULL,
    descricao TEXT NULL,
    principio_fundamental TEXT NULL,
    diretriz_completa LONGTEXT NULL,
    ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alma_item_dimensao_codigo (dimensao_id, codigo),
    KEY idx_alma_item_dimensao_ativo_ordem (dimensao_id, ativo, ordem),
    CONSTRAINT fk_alma_item_dimensao FOREIGN KEY (dimensao_id) REFERENCES alma_biblioteca_dimensao (id) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alma_biblioteca_item_secao (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id BIGINT UNSIGNED NOT NULL,
    codigo VARCHAR(80) NOT NULL,
    titulo VARCHAR(160) NOT NULL,
    conteudo LONGTEXT NULL,
    ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alma_item_secao_codigo (item_id, codigo),
    KEY idx_alma_item_secao_ordem (item_id, ordem),
    CONSTRAINT fk_alma_item_secao_item FOREIGN KEY (item_id) REFERENCES alma_biblioteca_item (id) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alma_biblioteca_secao_entrada (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    secao_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(40) NOT NULL DEFAULT 'ITEM',
    texto TEXT NOT NULL,
    ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_alma_secao_entrada_ordem (secao_id, ordem),
    CONSTRAINT fk_alma_secao_entrada_secao FOREIGN KEY (secao_id) REFERENCES alma_biblioteca_item_secao (id) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alma_direcao (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    imagem_id INT NOT NULL,
    revisao_ativa_id BIGINT UNSIGNED NULL,
    criada_por_usuario_id INT NULL,
    criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alma_direcao_imagem (imagem_id),
    KEY idx_alma_direcao_revisao_ativa (revisao_ativa_id),
    CONSTRAINT fk_alma_direcao_imagem FOREIGN KEY (imagem_id) REFERENCES imagens_cliente_obra (idimagens_cliente_obra) ON DELETE CASCADE,
    CONSTRAINT fk_alma_direcao_criador FOREIGN KEY (criada_por_usuario_id) REFERENCES usuario (idusuario) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alma_direcao_revisao (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    direcao_id BIGINT UNSIGNED NOT NULL,
    numero INT UNSIGNED NOT NULL,
    biblioteca_versao_id BIGINT UNSIGNED NOT NULL,
    revisao_anterior_id BIGINT UNSIGNED NULL,
    estado ENUM(
        'RASCUNHO',
        'ATIVA',
        'SUBSTITUIDA'
    ) NOT NULL DEFAULT 'RASCUNHO',
    ativa_token VARCHAR(5) NULL,
    intencao_geral TEXT NULL,
    sintese_narrativa TEXT NULL,
    criada_por_usuario_id INT NULL,
    atualizada_por_usuario_id INT NULL,
    criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ativada_em DATETIME NULL,
    lock_version INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alma_revisao_numero (direcao_id, numero),
    UNIQUE KEY uq_alma_revisao_uma_ativa (direcao_id, ativa_token),
    KEY idx_alma_revisao_estado_data (
        direcao_id,
        estado,
        atualizada_em
    ),
    KEY idx_alma_revisao_biblioteca (biblioteca_versao_id),
    CONSTRAINT chk_alma_revisao_ativa_token CHECK (
        (
            estado = 'ATIVA'
            AND ativa_token = 'ATIVA'
        )
        OR (
            estado <> 'ATIVA'
            AND ativa_token IS NULL
        )
    ),
    CONSTRAINT fk_alma_revisao_direcao FOREIGN KEY (direcao_id) REFERENCES alma_direcao (id) ON DELETE CASCADE,
    CONSTRAINT fk_alma_revisao_biblioteca FOREIGN KEY (biblioteca_versao_id) REFERENCES alma_biblioteca_versao (id) ON DELETE RESTRICT,
    CONSTRAINT fk_alma_revisao_anterior FOREIGN KEY (revisao_anterior_id) REFERENCES alma_direcao_revisao (id) ON DELETE SET NULL,
    CONSTRAINT fk_alma_revisao_criador FOREIGN KEY (criada_por_usuario_id) REFERENCES usuario (idusuario) ON DELETE SET NULL,
    CONSTRAINT fk_alma_revisao_atualizador FOREIGN KEY (atualizada_por_usuario_id) REFERENCES usuario (idusuario) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Ponte de leitura rapida. Sem FK para evitar dependencia circular na migration.
-- A API valida que revisao_ativa_id pertence a esta direcao.

CREATE TABLE IF NOT EXISTS alma_revisao_selecao (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    revisao_id BIGINT UNSIGNED NOT NULL,
    dimensao_id BIGINT UNSIGNED NOT NULL,
    item_biblioteca_id BIGINT UNSIGNED NULL,
    principal TINYINT(1) NOT NULL DEFAULT 1,
    resumo_contextual VARCHAR(255) NULL,
    aplicacao_imagem TEXT NULL,
    justificativa TEXT NULL,
    observacao_operacional TEXT NULL,
    ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_alma_selecao_revisao_dimensao (
        revisao_id,
        dimensao_id,
        ordem
    ),
    KEY idx_alma_selecao_item (item_biblioteca_id),
    CONSTRAINT fk_alma_selecao_revisao FOREIGN KEY (revisao_id) REFERENCES alma_direcao_revisao (id) ON DELETE CASCADE,
    CONSTRAINT fk_alma_selecao_dimensao FOREIGN KEY (dimensao_id) REFERENCES alma_biblioteca_dimensao (id) ON DELETE RESTRICT,
    CONSTRAINT fk_alma_selecao_item FOREIGN KEY (item_biblioteca_id) REFERENCES alma_biblioteca_item (id) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alma_revisao_referencia (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    revisao_id BIGINT UNSIGNED NOT NULL,
    selecao_id BIGINT UNSIGNED NULL,
    dimensao_id BIGINT UNSIGNED NOT NULL,
    sire_referencia_id BIGINT NOT NULL,
    representa TEXT NOT NULL,
    relevancia TEXT NULL,
    aplicar TEXT NOT NULL,
    nao_copiar TEXT NULL,
    observacao TEXT NULL,
    criada_por_usuario_id INT NULL,
    criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_alma_referencia_revisao_dimensao (revisao_id, dimensao_id),
    KEY idx_alma_referencia_sire (sire_referencia_id),
    CONSTRAINT fk_alma_referencia_revisao FOREIGN KEY (revisao_id) REFERENCES alma_direcao_revisao (id) ON DELETE CASCADE,
    CONSTRAINT fk_alma_referencia_selecao FOREIGN KEY (selecao_id) REFERENCES alma_revisao_selecao (id) ON DELETE SET NULL,
    CONSTRAINT fk_alma_referencia_dimensao FOREIGN KEY (dimensao_id) REFERENCES alma_biblioteca_dimensao (id) ON DELETE RESTRICT,
    CONSTRAINT fk_alma_referencia_sire FOREIGN KEY (sire_referencia_id) REFERENCES sire_referencia (id) ON DELETE RESTRICT,
    CONSTRAINT fk_alma_referencia_criador FOREIGN KEY (criada_por_usuario_id) REFERENCES usuario (idusuario) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alma_evento (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    direcao_id BIGINT UNSIGNED NOT NULL,
    revisao_id BIGINT UNSIGNED NULL,
    ator_usuario_id INT NULL,
    entidade_tipo VARCHAR(40) NOT NULL,
    entidade_id BIGINT UNSIGNED NULL,
    acao VARCHAR(60) NOT NULL,
    antes_json JSON NULL,
    depois_json JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_alma_evento_direcao_data (direcao_id, criado_em),
    KEY idx_alma_evento_revisao_data (revisao_id, criado_em),
    KEY idx_alma_evento_acao_data (acao, criado_em),
    CONSTRAINT fk_alma_evento_direcao FOREIGN KEY (direcao_id) REFERENCES alma_direcao (id) ON DELETE CASCADE,
    CONSTRAINT fk_alma_evento_revisao FOREIGN KEY (revisao_id) REFERENCES alma_direcao_revisao (id) ON DELETE SET NULL,
    CONSTRAINT fk_alma_evento_ator FOREIGN KEY (ator_usuario_id) REFERENCES usuario (idusuario) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;