-- 2026-01-14 - Módulo de Notificações (CRUD)

CREATE TABLE IF NOT EXISTS notificacoes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo VARCHAR(200) NOT NULL,
  mensagem TEXT NOT NULL,
  tipo VARCHAR(20) NOT NULL DEFAULT 'info',
  canal VARCHAR(20) NOT NULL DEFAULT 'banner',
  segmentacao_tipo VARCHAR(20) NOT NULL DEFAULT 'geral',
  prioridade INT NOT NULL DEFAULT 0,
  ativa TINYINT(1) NOT NULL DEFAULT 1,
  inicio_em DATETIME NULL,
  fim_em DATETIME NULL,
  fixa TINYINT(1) NOT NULL DEFAULT 0,
  fechavel TINYINT(1) NOT NULL DEFAULT 1,
  exige_confirmacao TINYINT(1) NOT NULL DEFAULT 0,
  cta_label VARCHAR(100) NULL,
  cta_url VARCHAR(500) NULL,
  arquivo_nome VARCHAR(255) NULL,
  arquivo_path VARCHAR(500) NULL,
  payload_json LONGTEXT NULL,
  criado_por INT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notificacoes_ativa_datas (ativa, inicio_em, fim_em, prioridade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Garantir a coluna segmentacao_tipo em bases existentes (idempotente)
SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'notificacoes'
    AND COLUMN_NAME = 'segmentacao_tipo'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE notificacoes ADD COLUMN segmentacao_tipo VARCHAR(20) NOT NULL DEFAULT \'geral\' AFTER canal',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Garantir colunas de arquivo PDF (idempotente)
SET @col_arquivo_nome := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'notificacoes'
    AND COLUMN_NAME = 'arquivo_nome'
);

SET @sql_arquivo_nome := IF(
  @col_arquivo_nome = 0,
  'ALTER TABLE notificacoes ADD COLUMN arquivo_nome VARCHAR(255) NULL AFTER cta_url',
  'SELECT 1'
);

PREPARE stmt FROM @sql_arquivo_nome;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_arquivo_path := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'notificacoes'
    AND COLUMN_NAME = 'arquivo_path'
);

SET @sql_arquivo_path := IF(
  @col_arquivo_path = 0,
  'ALTER TABLE notificacoes ADD COLUMN arquivo_path VARCHAR(500) NULL AFTER arquivo_nome',
  'SELECT 1'
);

PREPARE stmt FROM @sql_arquivo_path;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Segmentação (alvos)
CREATE TABLE IF NOT EXISTS notificacoes_alvos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  notificacao_id INT UNSIGNED NOT NULL,
  tipo VARCHAR(20) NOT NULL,
  alvo_id INT UNSIGNED NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_alvos_notif (notificacao_id),
  KEY idx_alvos_tipo_id (tipo, alvo_id),
  CONSTRAINT fk_notif_alvos_notif
    FOREIGN KEY (notificacao_id) REFERENCES notificacoes(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Destinatários / status de leitura (para admin ver quem viu/quando)
CREATE TABLE IF NOT EXISTS notificacoes_destinatarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  notificacao_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  visto_em DATETIME NULL,
  confirmado_em DATETIME NULL,
  dispensado_em DATETIME NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_notif_usuario (notificacao_id, usuario_id),
  KEY idx_usuario (usuario_id),
  KEY idx_visto (visto_em),
  CONSTRAINT fk_notif_dest_notif
    FOREIGN KEY (notificacao_id) REFERENCES notificacoes(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
