-- Briefing external access v2
-- Rollback (after the application no longer uses these structures):
-- DROP TABLE external_otp_challenge;
-- DROP TABLE external_auth_session;
-- ALTER TABLE briefing_participant DROP INDEX uq_bp_briefing_contact;
-- ALTER TABLE briefing_access_link DROP COLUMN revogado_motivo, DROP COLUMN revogado_por_colaborador_id;
-- ALTER TABLE contato_cliente DROP INDEX uq_contato_cliente_email_normalizado, DROP COLUMN email_normalizado;

-- Pre-flight: this must return no rows before adding the UNIQUE index.
SELECT LOWER(TRIM(email)) AS email_normalizado, COUNT(*) AS total
FROM contato_cliente
WHERE email IS NOT NULL AND TRIM(email) <> ''
GROUP BY LOWER(TRIM(email))
HAVING COUNT(*) > 1;

ALTER TABLE contato_cliente
  ADD COLUMN email_normalizado VARCHAR(255) NULL AFTER email;

UPDATE contato_cliente
SET email_normalizado = NULLIF(LOWER(TRIM(email)), '')
WHERE email_normalizado IS NULL OR email_normalizado <> NULLIF(LOWER(TRIM(email)), '');

ALTER TABLE contato_cliente
  ADD UNIQUE KEY uq_contato_cliente_email_normalizado (email_normalizado);

ALTER TABLE briefing_access_link
  ADD COLUMN revogado_motivo VARCHAR(40) NULL AFTER revogado_em,
  ADD COLUMN revogado_por_colaborador_id INT NULL AFTER revogado_motivo;

ALTER TABLE briefing_participant
  ADD COLUMN primeiro_acesso_em DATETIME NULL AFTER verificado_em,
  ADD UNIQUE KEY uq_bp_briefing_contact (briefing_id, contato_cliente_id);

CREATE TABLE IF NOT EXISTS external_auth_session (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contato_cliente_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  csrf_hash CHAR(64) NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_em DATETIME NOT NULL,
  ultimo_uso_em DATETIME NULL,
  revogado_em DATETIME NULL,
  ip_criacao VARCHAR(45) NULL,
  user_agent_criacao VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_external_auth_session_token (token_hash),
  KEY idx_external_auth_session_contact (contato_cliente_id, expira_em),
  CONSTRAINT fk_external_auth_session_contact FOREIGN KEY (contato_cliente_id)
    REFERENCES contato_cliente(idcontato_cliente) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS external_otp_challenge (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  briefing_access_link_id BIGINT UNSIGNED NOT NULL,
  contato_cliente_id INT NULL,
  email_normalizado VARCHAR(255) NOT NULL,
  finalidade VARCHAR(32) NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_em DATETIME NOT NULL,
  consumido_em DATETIME NULL,
  ultimo_envio_em DATETIME NOT NULL,
  ip_solicitacao VARCHAR(45) NULL,
  pending_payload JSON NULL,
  PRIMARY KEY (id),
  KEY idx_external_otp_lookup (briefing_access_link_id, email_normalizado, finalidade, expira_em),
  KEY idx_external_otp_ip (briefing_access_link_id, ip_solicitacao, criado_em),
  CONSTRAINT fk_external_otp_link FOREIGN KEY (briefing_access_link_id)
    REFERENCES briefing_access_link(id) ON DELETE CASCADE,
  CONSTRAINT fk_external_otp_contact FOREIGN KEY (contato_cliente_id)
    REFERENCES contato_cliente(idcontato_cliente) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
