-- Flow Block: a pausa é renovada sem transicionar a Issue de volta para aberta.
ALTER TABLE flow_issue
    ADD COLUMN pausa_observacao TEXT NULL AFTER pausa_motivo;
