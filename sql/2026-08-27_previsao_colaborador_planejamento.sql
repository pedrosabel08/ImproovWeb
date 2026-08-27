-- Prazo necessario continua calculado do plano vigente; esta migration guarda
-- somente a previsao declarada pelo colaborador e seus eventos auditaveis.

CREATE TABLE IF NOT EXISTS funcao_imagem_previsao_conclusao (
    funcao_imagem_id INT NOT NULL,
    previsao_conclusao DATE NOT NULL,
    justificativa VARCHAR(500) NULL,
    criado_por_colaborador_id INT NULL,
    criado_por_usuario_id INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (funcao_imagem_id),
    CONSTRAINT fk_fipc_funcao_imagem FOREIGN KEY (funcao_imagem_id) REFERENCES funcao_imagem(idfuncao_imagem) ON DELETE CASCADE,
    CONSTRAINT fk_fipc_colaborador FOREIGN KEY (criado_por_colaborador_id) REFERENCES colaborador(idcolaborador) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS funcao_imagem_previsao_historico (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    funcao_imagem_id INT NOT NULL,
    evento ENUM('PREVISAO_INFORMADA','PREVISAO_ALTERADA','CONCLUSAO_REGISTRADA') NOT NULL,
    prazo_necessario DATE NULL,
    previsao_anterior DATE NULL,
    previsao_conclusao DATE NULL,
    diferenca_dias_uteis INT NULL,
    justificativa VARCHAR(500) NULL,
    versao_planejamento_id BIGINT UNSIGNED NULL,
    ator_colaborador_id INT NULL,
    ator_usuario_id INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fiph_tarefa_data (funcao_imagem_id, criado_em),
    CONSTRAINT fk_fiph_funcao_imagem FOREIGN KEY (funcao_imagem_id) REFERENCES funcao_imagem(idfuncao_imagem) ON DELETE CASCADE,
    CONSTRAINT fk_fiph_versao FOREIGN KEY (versao_planejamento_id) REFERENCES entrega_planejamento_versao(id) ON DELETE SET NULL,
    CONSTRAINT fk_fiph_colaborador FOREIGN KEY (ator_colaborador_id) REFERENCES colaborador(idcolaborador) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
