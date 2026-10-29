-- Aplicar uma vez, após 2026-08-20_planejamento_producao_versionado.sql.
-- Sem backfill de planos, datas ou responsáveis históricos.
ALTER TABLE entrega_planejamento_producao
    ADD COLUMN modelo ENUM('FUNCOES_R00','IMAGENS_ALTERACAO') NOT NULL DEFAULT 'FUNCOES_R00',
    ADD COLUMN rascunho_json JSON NULL;

CREATE TABLE entrega_planejamento_item (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    versao_id BIGINT UNSIGNED NOT NULL,
    entrega_item_id INT NOT NULL,
    funcao_imagem_id INT NOT NULL,
    pre_alt_item_id INT UNSIGNED NULL,
    responsavel_planejado_id INT NOT NULL,
    inicio_planejado DATE NULL,
    fim_planejado DATE NOT NULL,
    nivel_complexidade_snapshot TINYINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_planejamento_item_versao (versao_id, entrega_item_id),
    KEY idx_planejamento_item_tarefa (funcao_imagem_id),
    CONSTRAINT fk_planejamento_item_versao FOREIGN KEY (versao_id) REFERENCES entrega_planejamento_versao(id),
    CONSTRAINT fk_planejamento_item_entrega FOREIGN KEY (entrega_item_id) REFERENCES entregas_itens(id),
    CONSTRAINT fk_planejamento_item_tarefa FOREIGN KEY (funcao_imagem_id) REFERENCES funcao_imagem(idfuncao_imagem),
    CONSTRAINT fk_planejamento_item_triagem FOREIGN KEY (pre_alt_item_id) REFERENCES pre_alt_itens(id),
    CONSTRAINT fk_planejamento_item_responsavel FOREIGN KEY (responsavel_planejado_id) REFERENCES colaborador(idcolaborador)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE janela_operacional_ciclo
    ADD COLUMN entrega_item_id INT NULL,
    ADD KEY idx_janela_ciclo_entrega_item (entrega_item_id),
    ADD CONSTRAINT fk_janela_ciclo_entrega_item FOREIGN KEY (entrega_item_id) REFERENCES entregas_itens(id);
