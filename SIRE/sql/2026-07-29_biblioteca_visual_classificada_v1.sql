-- SIRE V1: biblioteca visual classificada
-- Execute este arquivo antes de publicar os novos endpoints do SIRE.

CREATE TABLE IF NOT EXISTS sire_referencia (
    id BIGINT NOT NULL AUTO_INCREMENT,
    referencia_imagem_id BIGINT NULL,
    titulo VARCHAR(255) NULL,
    origem ENUM('Flow', 'Upload', 'URL') NOT NULL DEFAULT 'Flow',
    descricao TEXT NULL,
    golden_sample TINYINT(1) NOT NULL DEFAULT 0,
    url_externa TEXT NULL,
    nome_arquivo VARCHAR(255) NULL,
    caminho_arquivo VARCHAR(500) NULL,
    mime VARCHAR(120) NULL,
    tamanho_bytes BIGINT NULL,
    criado_por INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sire_referencia_flow (referencia_imagem_id),
    KEY idx_sire_referencia_origem_data (origem, criado_em),
    KEY idx_sire_referencia_golden_data (golden_sample, criado_em)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sire_pilar (
    id INT NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(40) NOT NULL,
    nome VARCHAR(80) NOT NULL,
    ordem INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sire_pilar_codigo (codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sire_pilar_valor (
    id BIGINT NOT NULL AUTO_INCREMENT,
    pilar_id INT NOT NULL,
    nome VARCHAR(160) NOT NULL,
    criado_por INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sire_pilar_valor (pilar_id, nome),
    KEY idx_sire_pilar_valor_pilar_nome (pilar_id, nome),
    CONSTRAINT fk_sire_pilar_valor_pilar FOREIGN KEY (pilar_id) REFERENCES sire_pilar (id) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sire_referencia_valor (
    referencia_id BIGINT NOT NULL,
    valor_id BIGINT NOT NULL,
    classificado_por INT NULL,
    classificado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (referencia_id, valor_id),
    KEY idx_sire_referencia_valor_valor_ref (valor_id, referencia_id),
    CONSTRAINT fk_sire_referencia_valor_referencia FOREIGN KEY (referencia_id) REFERENCES sire_referencia (id) ON DELETE CASCADE,
    CONSTRAINT fk_sire_referencia_valor_valor FOREIGN KEY (valor_id) REFERENCES sire_pilar_valor (id) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT IGNORE INTO
    sire_pilar (codigo, nome, ordem)
VALUES ('atmosfera', 'Atmosfera', 1),
    ('luz', 'Luz', 2),
    ('fotografia', 'Fotografia', 3),
    (
        'arquitetura',
        'Arquitetura',
        4
    ),
    (
        'materialidade',
        'Materialidade',
        5
    ),
    ('composicao', 'Composição', 6),
    ('lifestyle', 'Lifestyle', 7);

-- Migra as referências Flow já existentes sem modificar sua tabela de origem.
INSERT IGNORE INTO
    sire_referencia (
        referencia_imagem_id,
        titulo,
        origem,
        golden_sample,
        nome_arquivo,
        criado_em
    )
SELECT ri.id, COALESCE(
        NULLIF(ri.nomenclatura, ''), ri.nome_arquivo
    ), 'Flow', ri.golden_sample, ri.nome_arquivo, ri.importado_em
FROM referencias_imagens ri;