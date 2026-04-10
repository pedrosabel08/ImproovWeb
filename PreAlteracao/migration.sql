-- =============================================================
-- Migração: Fluxo Pré-Alteração
-- RVW → RVW_DONE → PRE_ALT → READY_FOR_PLANNING
-- =============================================================

-- 1. Novos substatuses
INSERT INTO
    `substatus_imagem` (
        `id`,
        `nome_substatus`,
        `nome_completo`
    )
VALUES (
        10,
        'RVW_DONE',
        'Review concluído pelo cliente'
    ),
    (
        11,
        'PRE_ALT',
        'Em pré-análise'
    ),
    (
        12,
        'READY_FOR_PLANNING',
        'Pronto para planejamento'
    );

-- 2. Tabela de análise de pré-alteração (uma ficha por imagem por entrega)
CREATE TABLE IF NOT EXISTS `pre_alt_analise` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `imagem_id` INT NOT NULL COMMENT 'FK → imagens_cliente_obra.idimagens_cliente_obra',
    `entrega_id` INT NOT NULL COMMENT 'FK → entregas.id',
    `complexidade` ENUM('S', 'M', 'C', 'TA') NOT NULL DEFAULT 'S' COMMENT 'S=Simples M=Médio C=Complexo TA=Troca de ângulo',
    `acao` TEXT NULL COMMENT 'Observações gerais da analista',
    `necessita_retorno` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = imagem requer retorno do cliente antes do planejamento',
    `responsavel_id` INT UNSIGNED NULL COMMENT 'FK → colaborador.idcolaborador',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_imagem_entrega` (`imagem_id`, `entrega_id`),
    KEY `idx_imagem_id` (`imagem_id`),
    KEY `idx_entrega_id` (`entrega_id`),
    CONSTRAINT `fk_paa_imagem` FOREIGN KEY (`imagem_id`) REFERENCES `imagens_cliente_obra` (`idimagens_cliente_obra`) ON DELETE CASCADE,
    CONSTRAINT `fk_paa_entrega` FOREIGN KEY (`entrega_id`) REFERENCES `entregas` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 3. Adicionar FKs com ON DELETE CASCADE (drop primeiro caso já existam)
ALTER TABLE `pre_alt_analise`
  MODIFY COLUMN `imagem_id` INT NOT NULL COMMENT 'FK → imagens_cliente_obra.idimagens_cliente_obra',
  MODIFY COLUMN `entrega_id` INT NOT NULL COMMENT 'FK → entregas.id';

ALTER TABLE `pre_alt_analise`
  DROP FOREIGN KEY IF EXISTS `fk_paa_imagem`,
  DROP FOREIGN KEY IF EXISTS `fk_paa_entrega`;

ALTER TABLE `pre_alt_analise`
  ADD CONSTRAINT `fk_paa_imagem`  FOREIGN KEY (`imagem_id`)  REFERENCES `imagens_cliente_obra` (`idimagens_cliente_obra`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_paa_entrega` FOREIGN KEY (`entrega_id`) REFERENCES `entregas` (`id`) ON DELETE CASCADE;