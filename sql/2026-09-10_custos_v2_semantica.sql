-- Diagnóstico: pagamento_itens só possui origem/origem_id e texto livre.
-- Os índices pagamento_id e (origem,origem_id) já existem; não duplicá-los.
-- Executar UMA VEZ após conferir SHOW CREATE TABLE no banco de destino.
-- Nenhum valor/registro histórico é alterado; sem UNIQUE(origem,origem_id).
ALTER TABLE pagamento_itens
    ADD COLUMN tipo_lancamento VARCHAR(40) NULL,
    ADD COLUMN chave_lancamento VARCHAR(160) NULL,
    ADD UNIQUE KEY uq_pagamento_lancamento (chave_lancamento);
-- NULL permite preservar todos os registros legados, inclusive duplicados.
-- Novos escritores usam origem:id:tipo. Parcelas têm tipos/chaves distintos.
-- Backfill NÃO automático: classificar e reconciliar antes de atribuir chaves.
-- A aplicação também funciona sem esta migration usando locks e adaptador legado.
-- Reversão funcional: reverter aplicação mantendo colunas opcionais (preserva dados).
