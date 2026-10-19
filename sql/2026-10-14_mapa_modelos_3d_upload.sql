-- arquivo em mapa_modelos_3d continua apontando para a versão ativa.
CREATE TABLE IF NOT EXISTS mapa_modelo_3d_versoes (
    id INT NOT NULL AUTO_INCREMENT,
    modelo_id INT NOT NULL,
    numero INT UNSIGNED NOT NULL,
    arquivo VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    tamanho_bytes BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    criado_por INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mp3d_versao_numero (modelo_id, numero),
    UNIQUE KEY uq_mp3d_versao_hash (modelo_id, sha256),
    CONSTRAINT fk_mp3d_versao_modelo FOREIGN KEY (modelo_id) REFERENCES mapa_modelos_3d (id) ON DELETE CASCADE,
    CONSTRAINT fk_mp3d_versao_autor FOREIGN KEY (criado_por) REFERENCES colaborador (idcolaborador)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;