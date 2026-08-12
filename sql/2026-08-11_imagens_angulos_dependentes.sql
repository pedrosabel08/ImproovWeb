-- Relação um-para-muitos entre uma imagem principal e seus ângulos secundários.
-- Todas as imagens atuais continuam principais porque a nova coluna inicia como NULL.
ALTER TABLE imagens_cliente_obra
    ADD COLUMN imagem_principal_id INT NULL AFTER obra_id,
    ADD KEY idx_imagens_principal (imagem_principal_id),
    ADD CONSTRAINT fk_imagens_imagem_principal
        FOREIGN KEY (imagem_principal_id)
        REFERENCES imagens_cliente_obra (idimagens_cliente_obra)
        ON DELETE RESTRICT
        ON UPDATE CASCADE;
