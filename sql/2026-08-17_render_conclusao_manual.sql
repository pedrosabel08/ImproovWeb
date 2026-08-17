-- Conclusao manual de render: auditoria por tentativa e operador responsavel.
-- Aplicar apos sql/2026-07-13_deadline_continuous_worker.sql.

CREATE TABLE IF NOT EXISTS render_conclusoes_manuais (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    render_id INT NOT NULL,
    tentativa_id BIGINT UNSIGNED NOT NULL,
    colaborador_id INT NOT NULL,
    status_anterior VARCHAR(50) NOT NULL,
    justificativa TEXT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_render_conclusao_manual_tentativa (tentativa_id),
    KEY idx_render_conclusoes_manuais_render (render_id, criado_em),
    KEY idx_render_conclusoes_manuais_colaborador (colaborador_id),
    CONSTRAINT fk_render_conclusoes_manuais_render FOREIGN KEY (render_id) REFERENCES render_alta (idrender_alta) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_render_conclusoes_manuais_tentativa FOREIGN KEY (tentativa_id) REFERENCES render_tentativas (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_render_conclusoes_manuais_colaborador FOREIGN KEY (colaborador_id) REFERENCES colaborador (idcolaborador) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;