-- ============================================================
-- SIRE — Golden Samples
-- Script: add_golden_sample.sql
-- Descrição: Adiciona suporte a Golden Samples na tabela de referências
-- ============================================================

ALTER TABLE referencias_imagens
    ADD COLUMN golden_sample TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Marca a referência como Golden Sample (favorita)';

-- Índice para otimizar listagem com ordenação prioritária
CREATE INDEX idx_golden_sample ON referencias_imagens (golden_sample);
