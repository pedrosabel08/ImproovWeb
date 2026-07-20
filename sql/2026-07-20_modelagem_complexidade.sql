CREATE TABLE IF NOT EXISTS complexidade_modelagem (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(30) NOT NULL,
    nome VARCHAR(50) NOT NULL,
    ordem TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY ux_complexidade_modelagem_codigo (codigo),
    UNIQUE KEY ux_complexidade_modelagem_nome (nome),
    KEY idx_complexidade_modelagem_ativo_ordem (ativo, ordem)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO complexidade_modelagem (codigo, nome, ordem, ativo)
VALUES ('BAIXA', 'Baixa', 1, 1),
    ('MEDIA', 'Média', 2, 1),
    ('ALTA', 'Alta', 3, 1)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    ordem = VALUES(ordem),
    ativo = VALUES(ativo);

ALTER TABLE obra
    ADD COLUMN complexidade_modelagem_id TINYINT UNSIGNED NULL AFTER status_obra,
    ADD KEY idx_obra_complexidade_modelagem (complexidade_modelagem_id),
    ADD CONSTRAINT fk_obra_complexidade_modelagem
        FOREIGN KEY (complexidade_modelagem_id) REFERENCES complexidade_modelagem(id)
        ON UPDATE CASCADE ON DELETE SET NULL;
