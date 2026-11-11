-- Uma imagem incluída pelo planejamento não tem item de Review de origem.
ALTER TABLE pre_alt_itens MODIFY COLUMN review_batch_item_id INT NULL;
