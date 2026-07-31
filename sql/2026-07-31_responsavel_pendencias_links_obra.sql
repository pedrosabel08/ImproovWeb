-- Atribui as pendências de Google Earth ao André (colaborador 9).
ALTER TABLE pendencias_links_obra
    ADD COLUMN responsavel_id INT NULL AFTER entrega_id,
    ADD KEY idx_pendencias_links_responsavel (responsavel_id);

UPDATE pendencias_links_obra
   SET responsavel_id = 9
 WHERE tipo_link = 'google_earth'
   AND (responsavel_id IS NULL OR responsavel_id = 0);
