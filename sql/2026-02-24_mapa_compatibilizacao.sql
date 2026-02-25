-- =============================================================================
-- Mapa de Compatibilização de Planta
-- Data: 2026-02-24
-- Tabelas: planta_compatibilizacao, planta_marcacoes
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. planta_compatibilizacao
--    Uma planta por obra por versão. Apenas uma versão pode estar ativa.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `planta_compatibilizacao` (
    `id`           INT          NOT NULL AUTO_INCREMENT,
    `obra_id`      INT          NOT NULL COMMENT 'FK → obra.idobra',
    `versao`       INT          NOT NULL DEFAULT 1,
    `imagem_path`  VARCHAR(255) NOT NULL,
    `ativa`        TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1 = ativa, 0 = inativa',
    `criado_por`   INT          NOT NULL COMMENT 'FK → colaborador.idcolaborador',
    `criado_em`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_obra_ativa` (`obra_id`, `ativa`)
   , CONSTRAINT `fk_planta_obra` FOREIGN KEY (`obra_id`) REFERENCES `obra` (`idobra`) ON DELETE CASCADE ON UPDATE CASCADE
   , CONSTRAINT `fk_planta_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `colaborador` (`idcolaborador`) ON DELETE RESTRICT ON UPDATE CASCADE
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Versões de planta baixa para o mapa de compatibilização';

-- -----------------------------------------------------------------------------
-- 2. planta_marcacoes
--    Polígonos desenhados sobre a planta, vinculando ambiente → imagem.
--    coordenadas_json: [[x, y], ...] em porcentagem (0–100).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `planta_marcacoes` (
    `id`                INT          NOT NULL AUTO_INCREMENT,
    `planta_id`         INT          NOT NULL COMMENT 'FK → planta_compatibilizacao.id',
    `nome_ambiente`     VARCHAR(100) NOT NULL,
    `imagem_id`         INT          NULL     COMMENT 'FK → imagens_cliente_obra.idimagens_cliente_obra',
    `coordenadas_json`  LONGTEXT     NOT NULL,
    `criado_por`        INT          NOT NULL COMMENT 'FK → colaborador.idcolaborador',
    `criado_em`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_planta_ambiente` (`planta_id`, `nome_ambiente`),
    KEY `idx_planta_marcacoes_planta` (`planta_id`),
    KEY `idx_planta_marcacoes_imagem` (`imagem_id`)
   , CONSTRAINT `fk_marcacoes_planta` FOREIGN KEY (`planta_id`) REFERENCES `planta_compatibilizacao` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
   , CONSTRAINT `fk_marcacoes_imagem` FOREIGN KEY (`imagem_id`) REFERENCES `imagens_cliente_obra` (`idimagens_cliente_obra`) ON DELETE SET NULL ON UPDATE CASCADE
   , CONSTRAINT `fk_marcacoes_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `colaborador` (`idcolaborador`) ON DELETE RESTRICT ON UPDATE CASCADE
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Marcações de ambientes (polígonos) sobre a planta de compatibilização';
