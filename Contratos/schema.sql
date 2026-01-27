-- Tabela contratos
CREATE TABLE IF NOT EXISTS contratos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    competencia VARCHAR(7) NOT NULL, -- formato YYYY-MM
    status ENUM('nao_gerado','enviado','assinado','recusado','expirado') NOT NULL DEFAULT 'nao_gerado',
    zapsign_doc_token VARCHAR(191) DEFAULT NULL,
    data_envio DATETIME DEFAULT NULL,
    data_inicio DATE DEFAULT NULL,
    data_fim DATE DEFAULT NULL,
    assinado_em DATETIME DEFAULT NULL,
    payload_enviado LONGTEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_contrato (colaborador_id, competencia),
    INDEX idx_contrato_status (status),
    INDEX idx_contrato_token (zapsign_doc_token)
);

ALTER TABLE contratos
    ADD CONSTRAINT fk_contratos_colaborador
    FOREIGN KEY (colaborador_id) REFERENCES colaborador(idcolaborador)
    ON DELETE CASCADE;
