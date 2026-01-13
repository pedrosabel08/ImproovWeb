-- Migração: remover vínculo com obra/projeto do módulo Flow Referências
-- Execute no mesmo banco onde estão as tabelas flow_ref_*

START TRANSACTION;

-- Remover FK e índice se existirem
SET @fk := (
  SELECT CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'flow_ref_upload'
    AND COLUMN_NAME = 'obra_id'
    AND REFERENCED_TABLE_NAME IS NOT NULL
  LIMIT 1
);

SET @sql := IF(@fk IS NOT NULL, CONCAT('ALTER TABLE flow_ref_upload DROP FOREIGN KEY ', @fk), 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT INDEX_NAME
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'flow_ref_upload'
    AND COLUMN_NAME = 'obra_id'
  LIMIT 1
);

SET @sql := IF(@idx IS NOT NULL, CONCAT('ALTER TABLE flow_ref_upload DROP INDEX ', @idx), 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remover coluna obra_id se existir
SET @col := (
  SELECT 1
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'flow_ref_upload'
    AND COLUMN_NAME = 'obra_id'
  LIMIT 1
);

SET @sql := IF(@col IS NOT NULL, 'ALTER TABLE flow_ref_upload DROP COLUMN obra_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;
