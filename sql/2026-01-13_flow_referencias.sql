-- Flow Referências
-- Tabelas + seed da taxonomia (Eixo/Categoria/Subcategoria + tipo/permitidos)

CREATE TABLE IF NOT EXISTS flow_ref_axis (
  id INT NOT NULL AUTO_INCREMENT,
  nome VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  ordem INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS flow_ref_category (
  id INT NOT NULL AUTO_INCREMENT,
  axis_id INT NOT NULL,
  nome VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  ordem INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_axis_slug (axis_id, slug),
  KEY idx_axis (axis_id),
  CONSTRAINT fk_flow_ref_category_axis
    FOREIGN KEY (axis_id) REFERENCES flow_ref_axis(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS flow_ref_subcategory (
  id INT NOT NULL AUTO_INCREMENT,
  category_id INT NOT NULL,
  nome VARCHAR(160) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  tipo_label VARCHAR(40) NOT NULL,
  allowed_exts_json TEXT NOT NULL,
  ordem INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_cat_slug (category_id, slug),
  KEY idx_cat (category_id),
  CONSTRAINT fk_flow_ref_subcategory_category
    FOREIGN KEY (category_id) REFERENCES flow_ref_category(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS flow_ref_upload (
  id INT NOT NULL AUTO_INCREMENT,
  obra_id INT NOT NULL,
  axis_id INT NOT NULL,
  category_id INT NOT NULL,
  subcategory_id INT NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  path VARCHAR(500) NOT NULL,
  ext VARCHAR(16) NOT NULL,
  mime VARCHAR(120) DEFAULT NULL,
  size_bytes BIGINT DEFAULT NULL,
  descricao TEXT,
  colaborador_id INT DEFAULT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_obra (obra_id),
  KEY idx_axis (axis_id),
  KEY idx_category (category_id),
  KEY idx_subcategory (subcategory_id),
  KEY idx_uploaded_at (uploaded_at),
  CONSTRAINT fk_flow_ref_upload_obra
    FOREIGN KEY (obra_id) REFERENCES obra(idobra)
    ON DELETE CASCADE,
  CONSTRAINT fk_flow_ref_upload_axis
    FOREIGN KEY (axis_id) REFERENCES flow_ref_axis(id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_flow_ref_upload_category
    FOREIGN KEY (category_id) REFERENCES flow_ref_category(id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_flow_ref_upload_subcategory
    FOREIGN KEY (subcategory_id) REFERENCES flow_ref_subcategory(id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_flow_ref_upload_colaborador
    FOREIGN KEY (colaborador_id) REFERENCES colaborador(idcolaborador)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Seed (idempotente via INSERT IGNORE + slugs únicas)
-- ------------------------------------------------------------

INSERT IGNORE INTO flow_ref_axis (id, nome, slug, ordem, ativo) VALUES
  (1, 'Heart (Referências)', 'heart', 1, 1),
  (2, 'Art (Base interno)', 'art', 2, 1),
  (3, 'Build (Arquivos base)', 'build', 3, 1);

INSERT IGNORE INTO flow_ref_category (id, axis_id, nome, slug, ordem, ativo) VALUES
  (1, 1, 'Referência Fotográfica (HEART)', 'referencia-fotografica-heart', 1, 1),
  (2, 2, 'Golden Samples', 'golden-samples', 1, 1),
  (3, 2, 'Documento de Diretriz / Checklist', 'documento-diretriz-checklist', 2, 1),
  (4, 3, 'Arquivo Base / Cena Base', 'arquivo-base-cena-base', 1, 1);

-- Heart -> Referência Fotográfica (HEART)
INSERT IGNORE INTO flow_ref_subcategory (category_id, nome, slug, tipo_label, allowed_exts_json, ordem, ativo) VALUES
  (1, 'Arquitetura — Externa', 'arquitetura-externa', 'jpg', '["jpg","jpeg"]', 1, 1),
  (1, 'Arquitetura — Interna', 'arquitetura-interna', 'jpg', '["jpg","jpeg"]', 2, 1),
  (1, 'Arquitetura — Fachada', 'arquitetura-fachada', 'jpg', '["jpg","jpeg"]', 3, 1),
  (1, 'Arquitetura — Unidades', 'arquitetura-unidades', 'jpg', '["jpg","jpeg"]', 4, 1);

-- Art -> Golden Samples
INSERT IGNORE INTO flow_ref_subcategory (category_id, nome, slug, tipo_label, allowed_exts_json, ordem, ativo) VALUES
  (2, 'Fachadas', 'fachadas', 'jpg', '["jpg","jpeg"]', 1, 1),
  (2, 'Imagens Externas', 'imagens-externas', 'jpg', '["jpg","jpeg"]', 2, 1),
  (2, 'Imagens Internas', 'imagens-internas', 'jpg', '["jpg","jpeg"]', 3, 1),
  (2, 'Unidades', 'unidades', 'jpg', '["jpg","jpeg"]', 4, 1),
  (2, 'Plantas Humanizadas', 'plantas-humanizadas', 'jpg', '["jpg","jpeg"]', 5, 1),
  (2, 'Animação', 'animacao', 'video', '["mp4","mov"]', 6, 1),
  (2, 'Manual SIRE', 'manual-sire', 'pdf', '["pdf"]', 7, 1),
  (2, 'SIRE por Tipo de Imagem', 'sire-por-tipo-de-imagem', 'pdf', '["pdf"]', 8, 1);

-- Art -> Documento de Diretriz / Checklist
-- OBS: na planilha consta tipo 'Flow' para Checklist de Produção; aqui permitimos PDF/DOCX/XLSX.
INSERT IGNORE INTO flow_ref_subcategory (category_id, nome, slug, tipo_label, allowed_exts_json, ordem, ativo) VALUES
  (3, 'Checklist de Produção', 'checklist-de-producao', 'flow', '["pdf","doc","docx","xls","xlsx"]', 1, 1),
  (3, 'Red Flags', 'red-flags', 'pdf', '["pdf"]', 2, 1),
  (3, 'SOP / Guia Rápido', 'sop-guia-rapido', 'pdf', '["pdf"]', 3, 1);

-- Build -> Arquivo Base / Cena Base
INSERT IGNORE INTO flow_ref_subcategory (category_id, nome, slug, tipo_label, allowed_exts_json, ordem, ativo) VALUES
  (4, 'Cena Base (.max)', 'cena-base-max', 'max', '["max"]', 1, 1),
  (4, 'Cena Golden Sample (.max)', 'cena-golden-sample-max', 'max', '["max"]', 2, 1),
  (4, 'Setup de Câmera', 'setup-de-camera', 'pdf', '["pdf"]', 3, 1),
  (4, 'Template de Composição', 'template-de-composicao', 'pdf', '["pdf"]', 4, 1),
  (4, 'LUT False Color', 'lut-false-color', 'cube', '["cube"]', 5, 1),
  (4, 'LUT Leitura de Contraste', 'lut-leitura-de-contraste', 'cube', '["cube"]', 6, 1),
  (4, 'Action Photoshop', 'action-photoshop', 'psd', '["psd"]', 7, 1);
  (4, 'Kit de Construção', 'kit-construcao', 'max', '["max"]', 8, 1);
