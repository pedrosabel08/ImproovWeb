-- O pavimento continua sendo subtipo_imagem. O modelo contém somente vínculos.
CREATE TABLE IF NOT EXISTS mapa_modelos_3d (
    id INT NOT NULL AUTO_INCREMENT,
    obra_id INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    arquivo VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mp3d_obra_arquivo (obra_id, arquivo),
    CONSTRAINT fk_mp3d_modelo_obra FOREIGN KEY (obra_id) REFERENCES obra(idobra) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mapa_modelo_pavimentos (
    modelo_id INT NOT NULL,
    object_name VARCHAR(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    subtipo_id INT NOT NULL,
    versao INT UNSIGNED NOT NULL DEFAULT 1,
    atualizado_por INT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (modelo_id, object_name),
    KEY idx_mp3d_subtipo (subtipo_id),
    CONSTRAINT fk_mp3d_vinculo_modelo FOREIGN KEY (modelo_id) REFERENCES mapa_modelos_3d(id) ON DELETE CASCADE,
    CONSTRAINT fk_mp3d_vinculo_subtipo FOREIGN KEY (subtipo_id) REFERENCES subtipo_imagem(id),
    CONSTRAINT fk_mp3d_vinculo_autor FOREIGN KEY (atualizado_por) REFERENCES colaborador(idcolaborador)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
