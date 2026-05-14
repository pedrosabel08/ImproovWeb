CREATE TABLE IF NOT EXISTS funcao_imagem_prazo_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcao_imagem_id INT NOT NULL,
    prazo_anterior DATE DEFAULT NULL,
    prazo_novo DATE DEFAULT NULL,
    alterado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    alterado_por_colaborador_id INT DEFAULT NULL,
    alterado_por_usuario_id INT DEFAULT NULL,
    origem VARCHAR(50) NOT NULL DEFAULT 'manual',
    motivo VARCHAR(255) DEFAULT NULL,
    status_anterior VARCHAR(50) DEFAULT NULL,
    status_novo VARCHAR(50) DEFAULT NULL,
    KEY idx_fiph_funcao (funcao_imagem_id),
    KEY idx_fiph_alterado_em (alterado_em),
    KEY idx_fiph_colaborador (alterado_por_colaborador_id),
    KEY idx_fiph_origem (origem),
    KEY idx_fiph_status_novo (status_novo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sla_notificacoes_enviadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_alerta VARCHAR(50) NOT NULL,
    data_referencia DATE NOT NULL,
    funcao_imagem_id INT NOT NULL,
    canal VARCHAR(80) NOT NULL DEFAULT '',
    enviado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    payload_hash CHAR(40) DEFAULT NULL,
    KEY idx_sne_data_tipo (data_referencia, tipo_alerta),
    KEY idx_sne_funcao (funcao_imagem_id),
    UNIQUE KEY uq_sne_tipo_data_funcao_canal (tipo_alerta, data_referencia, funcao_imagem_id, canal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migração incremental: adiciona colunas de status e torna prazo_novo nullable
-- (executar apenas se a tabela funcao_imagem_prazo_historico já existia antes desta versão)
ALTER TABLE funcao_imagem_prazo_historico
    MODIFY COLUMN prazo_novo DATE DEFAULT NULL,
    ADD COLUMN status_anterior VARCHAR(50) DEFAULT NULL AFTER motivo,
    ADD COLUMN status_novo     VARCHAR(50) DEFAULT NULL AFTER status_anterior;