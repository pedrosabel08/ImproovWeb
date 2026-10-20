-- Solicitações manuais de Render EF. Não modifica Jobs nem registros existentes.
CREATE TABLE IF NOT EXISTS render_ef_solicitacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    render_origem_id INT NOT NULL,
    imagem_id INT NOT NULL,
    criado_por INT NOT NULL,
    origem_job_id CHAR(24) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDENTE',
    etapa VARCHAR(50) NOT NULL DEFAULT 'FILA',
    mensagem TEXT NOT NULL,
    plano_json LONGTEXT NULL,
    novo_job_id CHAR(24) NULL,
    render_ef_id INT NULL,
    worker_id VARCHAR(100) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_render_ef_imagem (imagem_id),
    UNIQUE KEY uniq_render_ef_job (novo_job_id),
    KEY idx_render_ef_fila (status, id),
    CONSTRAINT fk_render_ef_origem FOREIGN KEY (render_origem_id) REFERENCES render_alta (idrender_alta),
    CONSTRAINT fk_render_ef_imagem FOREIGN KEY (imagem_id) REFERENCES imagens_cliente_obra (idimagens_cliente_obra)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;