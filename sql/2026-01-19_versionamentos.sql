-- Registro de versionamento do sistema

CREATE TABLE IF NOT EXISTS versionamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    versao VARCHAR(20) NOT NULL,
    descricao TEXT NULL,
    tipo ENUM('major', 'minor', 'patch', 'manual') NOT NULL DEFAULT 'patch',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    criado_por INT NULL,
    INDEX idx_versionamentos_criado_em (criado_em),
    INDEX idx_versionamentos_versao (versao)
);
