-- Mapa de Pavimento: entidades independentes do MapaCompatibilizacao.
-- Aplicar pelo processo de migrations; nenhuma DDL é executada pela interface.
CREATE TABLE IF NOT EXISTS obra_pavimentos (
    id INT NOT NULL AUTO_INCREMENT,
    obra_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    ordem INT NOT NULL DEFAULT 0,
    criado_por INT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pavimento_nome (obra_id, nome),
    CONSTRAINT fk_mp_pavimento_obra FOREIGN KEY (obra_id) REFERENCES obra (idobra) ON DELETE CASCADE,
    CONSTRAINT fk_mp_pavimento_autor FOREIGN KEY (criado_por) REFERENCES colaborador (idcolaborador)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS referencias_arquitetonicas (
    id INT NOT NULL AUTO_INCREMENT,
    pavimento_id INT NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    caminho VARCHAR(500) NOT NULL,
    mime VARCHAR(80) NOT NULL DEFAULT 'application/pdf',
    tamanho_bytes BIGINT UNSIGNED NOT NULL,
    paginas SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    largura_pontos DOUBLE NOT NULL,
    altura_pontos DOUBLE NOT NULL,
    rotacao SMALLINT NOT NULL DEFAULT 0,
    criado_por INT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mp_referencia_pavimento (pavimento_id),
    CONSTRAINT fk_mp_referencia_pavimento FOREIGN KEY (pavimento_id) REFERENCES obra_pavimentos (id) ON DELETE CASCADE,
    CONSTRAINT fk_mp_referencia_autor FOREIGN KEY (criado_por) REFERENCES colaborador (idcolaborador)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ambientes (
    id INT NOT NULL AUTO_INCREMENT,
    pavimento_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    versao_edicao INT NOT NULL DEFAULT 1,
    criado_por INT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mp_ambiente_nome (pavimento_id, nome),
    CONSTRAINT fk_mp_ambiente_pavimento FOREIGN KEY (pavimento_id) REFERENCES obra_pavimentos (id) ON DELETE CASCADE,
    CONSTRAINT fk_mp_ambiente_autor FOREIGN KEY (criado_por) REFERENCES colaborador (idcolaborador)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ambiente_demarcacoes (
    id INT NOT NULL AUTO_INCREMENT,
    ambiente_id INT NOT NULL,
    referencia_id INT NOT NULL,
    geometria_json JSON NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mp_demarcacao_ambiente (ambiente_id),
    KEY idx_mp_demarcacao_referencia (referencia_id),
    CONSTRAINT fk_mp_demarcacao_ambiente FOREIGN KEY (ambiente_id) REFERENCES ambientes (id) ON DELETE CASCADE,
    CONSTRAINT fk_mp_demarcacao_referencia FOREIGN KEY (referencia_id) REFERENCES referencias_arquitetonicas (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ambiente_imagens (
    ambiente_id INT NOT NULL,
    imagem_id INT NOT NULL,
    PRIMARY KEY (ambiente_id, imagem_id),
    KEY idx_mp_vinculo_imagem (imagem_id),
    CONSTRAINT fk_mp_vinculo_ambiente FOREIGN KEY (ambiente_id) REFERENCES ambientes (id) ON DELETE CASCADE,
    CONSTRAINT fk_mp_vinculo_imagem FOREIGN KEY (imagem_id) REFERENCES imagens_cliente_obra (idimagens_cliente_obra) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;