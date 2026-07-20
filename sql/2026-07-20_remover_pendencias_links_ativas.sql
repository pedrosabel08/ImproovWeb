-- Correção segura para ambientes que receberam a versão baseada em obra.pendencias_links_ativas.
SET @has_pendencias_links_ativas := (
    SELECT COUNT(*)
      FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'obra'
       AND column_name = 'pendencias_links_ativas'
);
SET @drop_pendencias_links_ativas := IF(
    @has_pendencias_links_ativas > 0,
    'ALTER TABLE obra DROP COLUMN pendencias_links_ativas',
    'SELECT 1'
);
PREPARE stmt_pendencias_links_ativas FROM @drop_pendencias_links_ativas;
EXECUTE stmt_pendencias_links_ativas;
DEALLOCATE PREPARE stmt_pendencias_links_ativas;
