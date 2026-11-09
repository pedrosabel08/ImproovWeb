-- Aplicar uma vez, após backup, com o worker parado. Não submete Jobs.
-- Nenhum registro, Job ID ou tentativa histórica é removido.
ALTER TABLE render_ef_solicitacoes
    MODIFY origem_job_id CHAR(24) NULL,
    ADD origem VARCHAR(16) NOT NULL DEFAULT 'JOB',
    ADD arquivo_log_id INT NULL,
    ADD submissao_uuid CHAR(36) NULL,
    ADD configuracao_json LONGTEXT NULL,
    ADD submissao_iniciada_em DATETIME NULL,
    ADD confirmado_por INT NULL,
    ADD confirmado_em DATETIME NULL,
    ADD operacao_ativa_imagem INT GENERATED ALWAYS AS (
        CASE WHEN status NOT IN ('CONCLUIDO', 'CANCELADO', 'ERRO')
             OR (status = 'ERRO' AND (novo_job_id IS NOT NULL OR submissao_iniciada_em IS NOT NULL))
        THEN imagem_id ELSE NULL END
    ) STORED,
    DROP INDEX uniq_render_ef_imagem,
    ADD UNIQUE KEY uniq_render_ef_operacao_ativa (operacao_ativa_imagem),
    ADD UNIQUE KEY uniq_render_ef_submissao (submissao_uuid),
    ADD KEY idx_render_ef_historico (imagem_id, id);

-- UUID legado determinístico, preservando identidade e correlação anterior por id.
UPDATE render_ef_solicitacoes
SET submissao_uuid = CONCAT('00000000-0000-4000-8000-', LPAD(HEX(id), 12, '0'))
WHERE submissao_uuid IS NULL;

CREATE TABLE IF NOT EXISTS render_ef_eventos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    solicitacao_id BIGINT UNSIGNED NOT NULL,
    etapa VARCHAR(50) NOT NULL,
    status VARCHAR(30) NOT NULL,
    mensagem TEXT NOT NULL,
    colaborador_id INT NULL,
    origem VARCHAR(16) NOT NULL DEFAULT 'WORKER',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ef_eventos (solicitacao_id, id),
    CONSTRAINT fk_ef_eventos_solicitacao FOREIGN KEY (solicitacao_id)
        REFERENCES render_ef_solicitacoes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
