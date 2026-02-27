-- ============================================================
-- migration_v2_mapa_compat.sql
-- Suporte a PDF nativo no MapaCompatibilizacao:
--   • planta_compatibilizacao: arquivo_id + arquivo_ids_json
--   • planta_marcacoes: pagina_pdf
-- ============================================================

-- Referência ao registro em `arquivos` (1 PDF)
ALTER TABLE planta_compatibilizacao
    ADD COLUMN IF NOT EXISTS arquivo_id INT NULL AFTER imagem_id;

-- Para visualização unificada de N PDFs em sequência
ALTER TABLE planta_compatibilizacao
    ADD COLUMN IF NOT EXISTS arquivo_ids_json TEXT NULL AFTER arquivo_id;

-- Página do PDF a que a marcação pertence (NULL = legado / sem página)
ALTER TABLE planta_marcacoes
    ADD COLUMN IF NOT EXISTS pagina_pdf SMALLINT UNSIGNED NULL AFTER coordenadas_json;
