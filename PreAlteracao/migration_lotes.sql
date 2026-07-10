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
    `prioridade` ENUM('BAIXA', 'NORMAL', 'ALTA', 'CRITICA') NOT NULL DEFAULT 'NORMAL',
    `prazo` DATE NULL,
    `responsavel_id` INT UNSIGNED NULL,
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
    `quantidade_comentarios` INT UNSIGNED NULL,
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

CREATE TABLE IF NOT EXISTS `pre_alt_lote_historico` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pre_alt_lote_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NULL,
    `batch_id` VARCHAR(36) NULL,
    `tipo_evento` VARCHAR(40) NOT NULL,
    `campo` VARCHAR(80) NULL,
    `valor_anterior` TEXT NULL,
    `valor_novo` TEXT NULL,
    `observacao` TEXT NULL,
    `usuario_id` INT UNSIGNED NULL,
    `colaborador_id` INT UNSIGNED NULL,
    `contexto_json` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pre_alt_hist_lote_data` (`pre_alt_lote_id`, `created_at`),
    KEY `idx_pre_alt_hist_batch` (`batch_id`),
    KEY `idx_pre_alt_hist_evento` (`tipo_evento`),
    CONSTRAINT `fk_pre_alt_hist_lote`
        FOREIGN KEY (`pre_alt_lote_id`) REFERENCES `pre_alt_lote` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `alteracoes`
    ADD COLUMN IF NOT EXISTS `nivel_complexidade` TINYINT UNSIGNED NULL AFTER `status_id`;

-- Registro auditavel de contato e retorno do cliente na triagem.
ALTER TABLE `pre_alt_itens`
    ADD COLUMN IF NOT EXISTS `reanalise_pos_retorno` TINYINT(1) NOT NULL DEFAULT 0 AFTER `quantidade_comentarios`;

CREATE TABLE IF NOT EXISTS `pre_alt_cliente_interacoes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pre_alt_lote_id` INT UNSIGNED NOT NULL,
    `tipo` ENUM('SOLICITACAO', 'RETORNO') NOT NULL,
    `ocorrido_em` DATETIME NOT NULL,
    `resultado_retorno` ENUM('APROVADA', 'ALTERACAO') NULL,
    `observacao` TEXT NULL,
    `usuario_id` INT UNSIGNED NULL,
    `colaborador_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pre_alt_cliente_interacao_lote_data` (`pre_alt_lote_id`, `ocorrido_em`),
    KEY `idx_pre_alt_cliente_interacao_tipo_data` (`tipo`, `ocorrido_em`),
    CONSTRAINT `fk_pre_alt_cliente_interacao_lote`
        FOREIGN KEY (`pre_alt_lote_id`) REFERENCES `pre_alt_lote` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pre_alt_cliente_interacao_itens` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `interacao_id` INT UNSIGNED NOT NULL,
    `pre_alt_item_id` INT UNSIGNED NOT NULL,
    `estado_anterior_json` LONGTEXT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_pre_alt_cliente_interacao_item` (`interacao_id`, `pre_alt_item_id`),
    KEY `idx_pre_alt_cliente_interacao_itens_item` (`pre_alt_item_id`),
    CONSTRAINT `fk_pre_alt_cliente_interacao_item_interacao`
        FOREIGN KEY (`interacao_id`) REFERENCES `pre_alt_cliente_interacoes` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_pre_alt_cliente_interacao_item_pre_alt_item`
        FOREIGN KEY (`pre_alt_item_id`) REFERENCES `pre_alt_itens` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
