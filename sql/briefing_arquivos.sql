-- Briefing de Arquivos (MVP)
-- Tabelas para registrar requisitos de arquivos por tipo de imagem.
-- Suporta múltiplos requisitos por categoria (ex.: Arquitetônico -> DWG e SKP).

-- 1) Tabela de tipos de imagem existentes na obra para o briefing
CREATE TABLE IF NOT EXISTS briefing_tipo_imagem (
    id INT AUTO_INCREMENT PRIMARY KEY,
    obra_id INT NOT NULL,
    tipo_imagem VARCHAR(60) NOT NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_obra_tipo (obra_id, tipo_imagem),
    KEY idx_obra (obra_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Tabela central de requisitos de arquivo por (tipo_imagem + categoria + tipo_arquivo)
-- Observação: para origem interna, o sistema salva tipo_arquivo='INTERNAL' como placeholder.
CREATE TABLE IF NOT EXISTS briefing_requisitos_arquivo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    briefing_tipo_imagem_id INT NOT NULL,
    categoria VARCHAR(40) NOT NULL,
    origem ENUM('cliente','interno') NOT NULL,
    tipo_arquivo VARCHAR(20) NOT NULL,
    obrigatorio TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pendente','recebido','validado','dispensado') NOT NULL DEFAULT 'pendente',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_tipo_categoria_arquivo (briefing_tipo_imagem_id, categoria, tipo_arquivo),
    KEY idx_tipo_categoria (briefing_tipo_imagem_id, categoria),
    KEY idx_tipo (briefing_tipo_imagem_id),

    CONSTRAINT fk_bra_bti FOREIGN KEY (briefing_tipo_imagem_id)
        REFERENCES briefing_tipo_imagem(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
