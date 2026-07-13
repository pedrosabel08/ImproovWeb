-- Rollback estrutural seguro apenas antes de existirem dados operacionais.
-- Em producao, prefira parar o worker e reverter o codigo, preservando as tabelas.

DROP PROCEDURE IF EXISTS rollback_deadline_continuous_worker_if_empty;

DELIMITER $$

CREATE PROCEDURE rollback_deadline_continuous_worker_if_empty()
BEGIN
    DECLARE total_tentativas BIGINT DEFAULT 0;
    DECLARE total_comandos BIGINT DEFAULT 0;

    SELECT COUNT(*) INTO total_tentativas FROM render_tentativas;
    SELECT COUNT(*) INTO total_comandos FROM deadline_comandos;

    IF total_tentativas > 0 OR total_comandos > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Rollback recusado: preserve tentativas e comandos existentes.';
    ELSE
        DROP TABLE IF EXISTS render_tentativa_eventos;
        DROP TABLE IF EXISTS deadline_workers;
        DROP TABLE IF EXISTS deadline_comandos;
        DROP TABLE IF EXISTS render_tentativas;
SET @deadline_drop_archive_column = (
    SELECT IF(
        COUNT(*) = 1,
        'ALTER TABLE render_alta DROP COLUMN excluido_em',
        'SELECT ''render_alta.excluido_em nao existe'' AS informacao'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'render_alta'
      AND COLUMN_NAME = 'excluido_em'
);
PREPARE deadline_drop_archive_column_stmt FROM @deadline_drop_archive_column;
EXECUTE deadline_drop_archive_column_stmt;
DEALLOCATE PREPARE deadline_drop_archive_column_stmt;
    END IF;
END$$

DELIMITER;

CALL rollback_deadline_continuous_worker_if_empty ();

DROP PROCEDURE rollback_deadline_continuous_worker_if_empty;