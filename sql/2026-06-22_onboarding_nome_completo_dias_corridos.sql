ALTER TABLE cliente
  ADD COLUMN nome_completo VARCHAR(150) NULL AFTER nome_cliente;

ALTER TABLE obra
  ADD COLUMN nome_completo VARCHAR(150) NULL AFTER nome_obra,
  ADD COLUMN prazo_dias_corridos TINYINT(1) NOT NULL DEFAULT 0 AFTER dias_uteis;

ALTER TABLE obra_pacote
  ADD COLUMN prazo_dias_corridos TINYINT(1) NOT NULL DEFAULT 0 AFTER prazo_contratual;
