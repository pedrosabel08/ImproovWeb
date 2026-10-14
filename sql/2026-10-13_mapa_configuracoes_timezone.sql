-- Executada pelo runner somente se a coluna ainda não existir.
ALTER TABLE mapa_obra_configuracoes_espaciais
    ADD COLUMN timezone VARCHAR(64) NOT NULL DEFAULT 'America/Sao_Paulo' AFTER norte_graus;
