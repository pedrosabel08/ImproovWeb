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

-- Tabela de log das ações do contrato
CREATE TABLE IF NOT EXISTS log_contratos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contrato_id INT DEFAULT NULL,
    colaborador_id INT DEFAULT NULL,
    zapsign_doc_token VARCHAR(191) DEFAULT NULL,
    status VARCHAR(50) DEFAULT NULL,
    acao VARCHAR(50) NOT NULL,
    origem VARCHAR(50) DEFAULT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    detalhe TEXT DEFAULT NULL,
    ocorrido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_contrato (contrato_id),
    INDEX idx_log_colaborador (colaborador_id),
    INDEX idx_log_token (zapsign_doc_token),
    INDEX idx_log_status (status)
);

ALTER TABLE log_contratos
    ADD CONSTRAINT fk_log_contratos_contrato
    FOREIGN KEY (contrato_id) REFERENCES contratos(id)
    ON DELETE SET NULL;

-- Tabela adendos
CREATE TABLE IF NOT EXISTS adendos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    competencia VARCHAR(7) NOT NULL, -- formato YYYY-MM
    status ENUM('nao_gerado','gerado','visualizado','enviado','assinado','recusado','expirado') NOT NULL DEFAULT 'nao_gerado',
    zapsign_doc_token VARCHAR(191) DEFAULT NULL,
    sign_url VARCHAR(255) DEFAULT NULL,
    data_envio DATETIME DEFAULT NULL,
    assinado_em DATETIME DEFAULT NULL,
    payload_enviado LONGTEXT,
    arquivo_nome VARCHAR(255) DEFAULT NULL,
    arquivo_path VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_adendo (colaborador_id, competencia),
    INDEX idx_adendo_status (status),
    INDEX idx_adendo_token (zapsign_doc_token),
    INDEX idx_adendo_arquivo (arquivo_nome)
);

ALTER TABLE adendos
    ADD CONSTRAINT fk_adendos_colaborador
    FOREIGN KEY (colaborador_id) REFERENCES colaborador(idcolaborador)
    ON DELETE CASCADE;

-- Migração para base já existente
ALTER TABLE adendos
    ADD COLUMN IF NOT EXISTS arquivo_nome VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS arquivo_path VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS sign_url VARCHAR(255) DEFAULT NULL;

-- Atualizar enum de status (ajuste conforme a sua versão do MySQL)
ALTER TABLE adendos
    MODIFY COLUMN status ENUM('nao_gerado','gerado','visualizado','enviado','assinado','recusado','expirado') NOT NULL DEFAULT 'nao_gerado';

-- Tabela de log das ações do adendo
CREATE TABLE IF NOT EXISTS log_adendos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    adendo_id INT DEFAULT NULL,
    colaborador_id INT DEFAULT NULL,
    zapsign_doc_token VARCHAR(191) DEFAULT NULL,
    status VARCHAR(50) DEFAULT NULL,
    acao VARCHAR(50) NOT NULL,
    origem VARCHAR(50) DEFAULT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    detalhe TEXT DEFAULT NULL,
    ocorrido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_adendo (adendo_id),
    INDEX idx_log_adendo_colaborador (colaborador_id),
    INDEX idx_log_adendo_token (zapsign_doc_token),
    INDEX idx_log_adendo_status (status)
);

ALTER TABLE log_adendos
    ADD CONSTRAINT fk_log_adendos_adendo
    FOREIGN KEY (adendo_id) REFERENCES adendos(id)
    ON DELETE SET NULL;
