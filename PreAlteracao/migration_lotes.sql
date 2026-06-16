-- =============================================================
-- Migração: Pré-Alteração por lote de review
-- A triagem deixa de usar substatus da imagem e passa a usar
-- lotes gerenciais ligados a review_batch/review_batch_items.
-- =============================================================

CREATE TABLE IF NOT EXISTS `pre_alt_lote` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `obra_id` INT NOT NULL,
    `status_id` INT NOT NULL COMMENT 'Etapa da entrega, ex.: R01/R02/EF',
    `data_finalizacao_cliente` DATE NOT NULL,
    `status` ENUM(
        'EM_TRIAGEM',
        'AGUARDANDO_CLIENTE',
        'PRONTO_PLANEJAMENTO',
        'PLANEJADO',
        'CANCELADO'
    ) NOT NULL DEFAULT 'EM_TRIAGEM',
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pre_alt_lote_status` (`status`, `data_finalizacao_cliente`),
    KEY `idx_pre_alt_lote_obra_status_data` (`obra_id`, `status_id`, `data_finalizacao_cliente`),
    CONSTRAINT `fk_pre_alt_lote_obra`
        FOREIGN KEY (`obra_id`) REFERENCES `obra` (`idobra`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pre_alt_lote_batches` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pre_alt_lote_id` INT UNSIGNED NOT NULL,
    `review_batch_id` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_pre_alt_lote_batch` (`pre_alt_lote_id`, `review_batch_id`),
    KEY `idx_pre_alt_lote_batches_batch` (`review_batch_id`),
    CONSTRAINT `fk_pre_alt_lote_batches_lote`
        FOREIGN KEY (`pre_alt_lote_id`) REFERENCES `pre_alt_lote` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_pre_alt_lote_batches_batch`
        FOREIGN KEY (`review_batch_id`) REFERENCES `review_batch` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pre_alt_itens` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pre_alt_lote_id` INT UNSIGNED NOT NULL,
    `review_batch_item_id` INT NOT NULL,
    `entrega_id` INT NOT NULL,
    `entrega_item_id` INT NULL,
    `imagem_id` INT NOT NULL,
    `resultado` ENUM(
        'ALTERACAO',
        'SEM_ALTERACAO',
        'AGUARDANDO_CLIENTE'
    ) NULL DEFAULT 'ALTERACAO',
    `nivel_complexidade` TINYINT UNSIGNED NULL COMMENT '1 a 5; nulo quando não houver alteração',
    `tipo_alteracao` VARCHAR(80) NULL,
    `acao` TEXT NULL,
    `necessita_retorno` TINYINT(1) NOT NULL DEFAULT 0,
    `responsavel_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_pre_alt_item_batch_item` (`review_batch_item_id`),
    KEY `idx_pre_alt_itens_lote` (`pre_alt_lote_id`),
    KEY `idx_pre_alt_itens_imagem` (`imagem_id`),
    KEY `idx_pre_alt_itens_resultado` (`resultado`, `nivel_complexidade`),
    CONSTRAINT `fk_pre_alt_itens_lote`
        FOREIGN KEY (`pre_alt_lote_id`) REFERENCES `pre_alt_lote` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_pre_alt_itens_review_item`
        FOREIGN KEY (`review_batch_item_id`) REFERENCES `review_batch_items` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_pre_alt_itens_entrega`
        FOREIGN KEY (`entrega_id`) REFERENCES `entregas` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_pre_alt_itens_imagem`
        FOREIGN KEY (`imagem_id`) REFERENCES `imagens_cliente_obra` (`idimagens_cliente_obra`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
