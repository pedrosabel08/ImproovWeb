-- Unidades operacionais do Flow. Os registros de funcao_imagem permanecem
-- independentes; esta estrutura registra apenas a decisão explícita de operar
-- um conjunto como uma única unidade de WIP.

CREATE TABLE IF NOT EXISTS unidade_trabalho (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo VARCHAR(50) NOT NULL,
    imagem_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    estado ENUM('ATIVA', 'INATIVA') NOT NULL DEFAULT 'ATIVA',
    criado_por_colaborador_id INT NULL,
    criado_por_usuario_id INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_unidade_tipo_imagem (tipo, imagem_id),
    KEY idx_unidade_colaborador_estado (colaborador_id, estado),
    CONSTRAINT fk_unidade_imagem
        FOREIGN KEY (imagem_id) REFERENCES imagens_cliente_obra (idimagens_cliente_obra)
        ON DELETE CASCADE,
    CONSTRAINT fk_unidade_colaborador
        FOREIGN KEY (colaborador_id) REFERENCES colaborador (idcolaborador)
        ON DELETE RESTRICT,
    CONSTRAINT fk_unidade_criador_colaborador
        FOREIGN KEY (criado_por_colaborador_id) REFERENCES colaborador (idcolaborador)
        ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS unidade_trabalho_item (
    unidade_trabalho_id BIGINT UNSIGNED NOT NULL,
    funcao_imagem_id INT NOT NULL,
    ordem TINYINT UNSIGNED NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (unidade_trabalho_id, funcao_imagem_id),
    UNIQUE KEY uq_unidade_item_funcao_imagem (funcao_imagem_id),
    KEY idx_unidade_item_ordem (unidade_trabalho_id, ordem),
    CONSTRAINT fk_unidade_item_unidade
        FOREIGN KEY (unidade_trabalho_id) REFERENCES unidade_trabalho (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_unidade_item_funcao_imagem
        FOREIGN KEY (funcao_imagem_id) REFERENCES funcao_imagem (idfuncao_imagem)
        ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS unidade_trabalho_evento (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    unidade_trabalho_id BIGINT UNSIGNED NOT NULL,
    evento VARCHAR(50) NOT NULL,
    ator_colaborador_id INT NULL,
    ator_usuario_id INT NULL,
    detalhes JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_unidade_evento_data (unidade_trabalho_id, criado_em),
    CONSTRAINT fk_unidade_evento_unidade
        FOREIGN KEY (unidade_trabalho_id) REFERENCES unidade_trabalho (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_unidade_evento_colaborador
        FOREIGN KEY (ator_colaborador_id) REFERENCES colaborador (idcolaborador)
        ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
