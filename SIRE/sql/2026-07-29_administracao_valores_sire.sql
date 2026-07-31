-- Administração do vocabulário SIRE.
-- Mantém associações existentes e apenas amplia os valores já cadastrados.

ALTER TABLE sire_pilar_valor
ADD COLUMN descricao TEXT NULL AFTER nome,
ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER descricao,
ADD COLUMN atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER criado_em,
ADD KEY idx_sire_pilar_valor_pilar_ativo_nome (pilar_id, ativo, nome);

CREATE TABLE IF NOT EXISTS sire_pilar_valor_caracteristica (
    id BIGINT NOT NULL AUTO_INCREMENT,
    pilar_valor_id BIGINT NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    ordem INT NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sire_pilar_valor_caracteristica_valor_ordem (pilar_valor_id, ordem),
    CONSTRAINT fk_sire_pilar_valor_caracteristica_valor FOREIGN KEY (pilar_valor_id) REFERENCES sire_pilar_valor (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;