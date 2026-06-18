CREATE TABLE IF NOT EXISTS alteracao_aprovacao_interna (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcao_imagem_id INT NOT NULL,
    imagem_id INT NOT NULL,
    status_id INT NOT NULL,
    origem ENUM('flowreview','presencial','whatsapp') NOT NULL,
    registrado_por_colaborador_id INT NOT NULL,
    registrado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    render_id INT NULL,
    historico_aprovacao_id INT NULL,
    observacao VARCHAR(255) NULL,
    UNIQUE KEY uniq_funcao_status (funcao_imagem_id, status_id),
    KEY idx_imagem_status (imagem_id, status_id),
    KEY idx_status_id (status_id),
    KEY idx_origem (origem),
    KEY idx_registrado_por (registrado_por_colaborador_id),
    KEY idx_render_id (render_id),
    KEY idx_historico_aprovacao_id (historico_aprovacao_id),
    CONSTRAINT fk_aai_funcao_imagem
        FOREIGN KEY (funcao_imagem_id) REFERENCES funcao_imagem (idfuncao_imagem)
        ON DELETE CASCADE,
    CONSTRAINT fk_aai_imagem
        FOREIGN KEY (imagem_id) REFERENCES imagens_cliente_obra (idimagens_cliente_obra)
        ON DELETE CASCADE,
    CONSTRAINT fk_aai_status
        FOREIGN KEY (status_id) REFERENCES status_imagem (idstatus)
        ON DELETE RESTRICT,
    CONSTRAINT fk_aai_colaborador
        FOREIGN KEY (registrado_por_colaborador_id) REFERENCES colaborador (idcolaborador)
        ON DELETE RESTRICT,
    CONSTRAINT fk_aai_render
        FOREIGN KEY (render_id) REFERENCES render_alta (idrender_alta)
        ON DELETE SET NULL,
    CONSTRAINT fk_aai_historico
        FOREIGN KEY (historico_aprovacao_id) REFERENCES historico_aprovacoes (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
