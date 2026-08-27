-- Complemento para bases onde a migration inicial V1.5 já foi aplicada.
-- Distingue trabalho operacional sem entrega/plano (OBRA:id:etapa), impedindo
-- que obras distintas vinculadas à entrega 0 sejam misturadas na mesma fila.
ALTER TABLE entrega_planejamento_fila_operacional
    ADD COLUMN referencia_fila VARCHAR(100) NOT NULL DEFAULT '' AFTER codigo_etapa,
    ADD KEY idx_fila_referencia (colaborador_id, codigo_etapa, referencia_fila, ativo);
