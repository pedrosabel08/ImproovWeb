-- Multiple notification attachments. Run manually in the application database.
-- Does not remove or change the legacy arquivo_nome and arquivo_path fields.
CREATE TABLE IF NOT EXISTS notificacoes_anexos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  notificacao_id INT UNSIGNED NOT NULL,
  nome_original VARCHAR(255) NOT NULL,
  nome_arquivo VARCHAR(255) NOT NULL,
  caminho VARCHAR(500) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  tamanho BIGINT UNSIGNED NOT NULL,
  ordem INT UNSIGNED NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notificacoes_anexos_notificacao_ordem (notificacao_id, ordem, id),
  CONSTRAINT fk_notificacoes_anexos_notificacao
    FOREIGN KEY (notificacao_id) REFERENCES notificacoes(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
