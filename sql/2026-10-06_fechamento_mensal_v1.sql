-- Aditiva: antigos permanecem NULL; nenhuma classificação inferida.
ALTER TABLE colaborador ADD COLUMN tipo_remuneracao ENUM('FIXO','VARIAVEL','FIXO_VARIAVEL') NULL DEFAULT NULL;
