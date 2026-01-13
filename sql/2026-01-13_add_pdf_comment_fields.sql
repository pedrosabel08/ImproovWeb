-- Add support for point comments on PDFs in Flow Review
-- Comments continue to support JPG approval images via `ap_imagem_id`.

ALTER TABLE comentarios_imagem
  ADD COLUMN arquivo_log_id INT NULL,
  ADD COLUMN pagina INT NULL;

-- Optional (recommended) indexes for performance
CREATE INDEX idx_comentarios_imagem_arquivo_log ON comentarios_imagem (arquivo_log_id);
CREATE INDEX idx_comentarios_imagem_arquivo_log_pagina ON comentarios_imagem (arquivo_log_id, pagina);
