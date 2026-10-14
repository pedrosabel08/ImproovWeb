-- Uma configuração por obra; NULL significa não definido.
-- Norte: plano XZ, +Y vertical, 0°=-Z, 90°=+X (horário visto de +Y).
CREATE TABLE IF NOT EXISTS mapa_obra_configuracoes_espaciais (
    obra_id INT NOT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    norte_graus DECIMAL(4,1) NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'America/Sao_Paulo',
    versao INT UNSIGNED NOT NULL DEFAULT 1,
    atualizado_por INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (obra_id),
    CONSTRAINT fk_mpe_obra FOREIGN KEY (obra_id) REFERENCES obra(idobra) ON DELETE CASCADE,
    CONSTRAINT fk_mpe_autor FOREIGN KEY (atualizado_por) REFERENCES colaborador(idcolaborador),
    CONSTRAINT ck_mpe_latitude CHECK (latitude BETWEEN -90 AND 90),
    CONSTRAINT ck_mpe_longitude CHECK (longitude BETWEEN -180 AND 180),
    CONSTRAINT ck_mpe_norte CHECK (norte_graus >= 0 AND norte_graus < 360)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
