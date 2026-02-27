-- ================================================================
-- Mapa de Compatibilização — Migração Multi-Planta
-- Execute uma única vez no banco de dados.
-- ================================================================

-- 1. Adicionar colunas à tabela planta_compatibilizacao
ALTER TABLE planta_compatibilizacao
    ADD COLUMN imagem_id  INT          NULL DEFAULT NULL AFTER obra_id,
    ADD COLUMN pagina_pdf SMALLINT     NULL DEFAULT NULL AFTER imagem_id;

-- 2. FK opcional (descomente se quiser enforçar integridade referencial)
-- ALTER TABLE planta_compatibilizacao
--     ADD CONSTRAINT fk_planta_imagem
--         FOREIGN KEY (imagem_id)
--         REFERENCES imagens_cliente_obra(idimagens_cliente_obra)
--         ON DELETE SET NULL;

-- ================================================================
-- Observação: registros existentes ficarão com imagem_id = NULL,
-- o que é tratado como "planta legada" pelo sistema.
-- ================================================================
