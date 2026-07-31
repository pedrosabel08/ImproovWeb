-- Fluxo incremental de notificacoes: aprovacao, modulo relacionado e auditoria.
-- Seguro para registros existentes: nao remove nem altera campos antigos.

CREATE TABLE IF NOT EXISTS notificacoes_modulos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(50) NOT NULL,
  nome VARCHAR(100) NOT NULL,
  url VARCHAR(500) NOT NULL,
  icone VARCHAR(100) NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_notificacoes_modulos_codigo (codigo),
  KEY idx_notificacoes_modulos_ativo_nome (ativo, nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO notificacoes_modulos (codigo, nome, url, icone, ativo) VALUES
  ('SIRE', 'SIRE', '/ImproovWeb/SIRE/', 'fa-images', 1),
  ('KANBAN', 'Kanban', '/ImproovWeb/Quadro/', 'fa-table-columns', 1),
  ('PLANO_FOTOGRAFICO', 'Plano Fotográfico', '/ImproovWeb/Fotografico/', 'fa-camera', 1),
  ('FLOW_REVIEW', 'Flow Review', '/ImproovWeb/FlowReview/', 'fa-check-double', 1),
  ('CONTRATOS', 'Contratos', '/ImproovWeb/Contratos/', 'fa-file-signature', 1),
  ('DASHBOARD', 'Dashboard', '/ImproovWeb/Dashboard/', 'fa-chart-line', 1),
  ('OBRAS', 'Obras', '/ImproovWeb/Projetos/', 'fa-building', 1)
ON DUPLICATE KEY UPDATE nome = VALUES(nome), url = VALUES(url), icone = VALUES(icone);

-- Adiciona uma coluna somente quando ela ainda nao existe.
SET @add_column = (SELECT COUNT(*) = 0 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notificacoes' AND column_name = 'modulo_id');
SET @sql = IF(@add_column, 'ALTER TABLE notificacoes ADD COLUMN modulo_id INT UNSIGNED NULL AFTER criado_por', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @add_column = (SELECT COUNT(*) = 0 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notificacoes' AND column_name = 'versao_publicacao');
SET @sql = IF(@add_column, 'ALTER TABLE notificacoes ADD COLUMN versao_publicacao VARCHAR(20) NULL AFTER modulo_id', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @add_column = (SELECT COUNT(*) = 0 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notificacoes' AND column_name = 'status_publicacao');
SET @sql = IF(@add_column, 'ALTER TABLE notificacoes ADD COLUMN status_publicacao VARCHAR(30) NULL AFTER versao_publicacao', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @add_column = (SELECT COUNT(*) = 0 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notificacoes' AND column_name = 'enviado_para_aprovacao_por');
SET @sql = IF(@add_column, 'ALTER TABLE notificacoes ADD COLUMN enviado_para_aprovacao_por INT NULL AFTER status_publicacao', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @add_column = (SELECT COUNT(*) = 0 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notificacoes' AND column_name = 'enviado_para_aprovacao_em');
SET @sql = IF(@add_column, 'ALTER TABLE notificacoes ADD COLUMN enviado_para_aprovacao_em DATETIME NULL AFTER enviado_para_aprovacao_por', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @add_column = (SELECT COUNT(*) = 0 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notificacoes' AND column_name = 'aprovado_por');
SET @sql = IF(@add_column, 'ALTER TABLE notificacoes ADD COLUMN aprovado_por INT NULL AFTER enviado_para_aprovacao_em', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @add_column = (SELECT COUNT(*) = 0 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notificacoes' AND column_name = 'aprovado_em');
SET @sql = IF(@add_column, 'ALTER TABLE notificacoes ADD COLUMN aprovado_em DATETIME NULL AFTER aprovado_por', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @add_column = (SELECT COUNT(*) = 0 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notificacoes' AND column_name = 'rejeitado_por');
SET @sql = IF(@add_column, 'ALTER TABLE notificacoes ADD COLUMN rejeitado_por INT NULL AFTER aprovado_em', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @add_column = (SELECT COUNT(*) = 0 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notificacoes' AND column_name = 'rejeitado_em');
SET @sql = IF(@add_column, 'ALTER TABLE notificacoes ADD COLUMN rejeitado_em DATETIME NULL AFTER rejeitado_por', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @add_column = (SELECT COUNT(*) = 0 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notificacoes' AND column_name = 'publicado_por');
SET @sql = IF(@add_column, 'ALTER TABLE notificacoes ADD COLUMN publicado_por INT NULL AFTER rejeitado_em', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @add_column = (SELECT COUNT(*) = 0 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notificacoes' AND column_name = 'publicado_em');
SET @sql = IF(@add_column, 'ALTER TABLE notificacoes ADD COLUMN publicado_em DATETIME NULL AFTER publicado_por', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @add_column = (SELECT COUNT(*) = 0 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notificacoes' AND column_name = 'motivo_rejeicao');
SET @sql = IF(@add_column, 'ALTER TABLE notificacoes ADD COLUMN motivo_rejeicao TEXT NULL AFTER publicado_em', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS notificacoes_historico (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  notificacao_id INT UNSIGNED NOT NULL,
  acao VARCHAR(40) NOT NULL,
  status_anterior VARCHAR(30) NULL,
  status_novo VARCHAR(30) NULL,
  motivo TEXT NULL,
  criado_por INT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notificacoes_historico_notificacao (notificacao_id, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Os cadastros antigos ja ativos eram visiveis ao publico; preserva essa regra.
UPDATE notificacoes
SET status_publicacao = CASE WHEN ativa = 1 THEN 'PUBLICADA' ELSE 'ENCERRADA' END
WHERE status_publicacao IS NULL OR status_publicacao = '';

SET @add_index = (SELECT COUNT(*) = 0 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'notificacoes' AND index_name = 'idx_notificacoes_publicacao');
SET @sql = IF(@add_index, 'ALTER TABLE notificacoes ADD KEY idx_notificacoes_publicacao (status_publicacao, ativa, inicio_em, fim_em, prioridade)', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
