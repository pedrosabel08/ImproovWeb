-- Vínculo auditável de FlowBlocks contextuais às aprovações que os bloqueiam.
CREATE TABLE IF NOT EXISTS flow_issue_dependencia (
    issue_id INT NOT NULL,
    requirement_code VARCHAR(100) NOT NULL,
    tarefa_bloqueada_id INT NOT NULL,
    predecessora_funcao_imagem_id INT NOT NULL,
    approval_cycle_key VARCHAR(120) NOT NULL,
    aprovacao_status VARCHAR(60) NOT NULL,
    aprovacao_historico_id INT NULL,
    aprovadores_json JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    encerrada_em DATETIME NULL,
    PRIMARY KEY (issue_id),
    UNIQUE KEY uq_flow_issue_dependencia_ciclo (tarefa_bloqueada_id, requirement_code, predecessora_funcao_imagem_id, approval_cycle_key),
    KEY idx_flow_issue_dependencia_predecessora (predecessora_funcao_imagem_id, encerrada_em),
    KEY idx_flow_issue_dependencia_bloqueada (tarefa_bloqueada_id, encerrada_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
