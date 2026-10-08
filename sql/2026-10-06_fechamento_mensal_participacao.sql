-- NULL preserva os cadastros ainda não revisados. Não fazer backfill/inferência.
ALTER TABLE colaborador
    ADD COLUMN participa_fechamento_mensal TINYINT NULL DEFAULT NULL,
    ADD CONSTRAINT chk_colaborador_participa_fechamento CHECK (participa_fechamento_mensal IN (0,1));
