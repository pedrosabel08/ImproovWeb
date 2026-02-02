-- Tabela contratos
CREATE TABLE IF NOT EXISTS contratos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    competencia VARCHAR(7) NOT NULL, -- formato YYYY-MM
    status ENUM('nao_gerado','gerado','visualizado','enviado','assinado','recusado','expirado') NOT NULL DEFAULT 'nao_gerado',
    zapsign_doc_token VARCHAR(191) DEFAULT NULL,
    sign_url VARCHAR(255) DEFAULT NULL,
    data_envio DATETIME DEFAULT NULL,
    data_inicio DATE DEFAULT NULL,
    data_fim DATE DEFAULT NULL,
    assinado_em DATETIME DEFAULT NULL,
    payload_enviado LONGTEXT,
    arquivo_nome VARCHAR(255) DEFAULT NULL,
    arquivo_path VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_contrato (colaborador_id, competencia),
    INDEX idx_contrato_status (status),
    INDEX idx_contrato_token (zapsign_doc_token),
    INDEX idx_contrato_arquivo (arquivo_nome)
);

ALTER TABLE contratos
    ADD CONSTRAINT fk_contratos_colaborador
    FOREIGN KEY (colaborador_id) REFERENCES colaborador(idcolaborador)
    ON DELETE CASCADE;

-- Migração para base já existente
ALTER TABLE contratos
    ADD COLUMN IF NOT EXISTS arquivo_nome VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS arquivo_path VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS sign_url VARCHAR(255) DEFAULT NULL;

-- Atualizar enum de status (ajuste conforme a sua versão do MySQL)
-- Exemplo compatível com MySQL 8:
ALTER TABLE contratos
    MODIFY COLUMN status ENUM('nao_gerado','gerado','visualizado','enviado','assinado','recusado','expirado') NOT NULL DEFAULT 'nao_gerado';
