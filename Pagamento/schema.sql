-- Schema for Pagamento module enhancements

-- pagamentos: aggregate per collaborator/month
CREATE TABLE IF NOT EXISTS pagamentos (
  idpagamento INT AUTO_INCREMENT PRIMARY KEY,
  colaborador_id INT NOT NULL,
  mes_ref CHAR(7) NOT NULL, -- YYYY-MM
  valor_total DECIMAL(12,2) DEFAULT 0.00,
  status ENUM('PENDENTE','ENVIADO','CONFIRMANDO','PAGO') DEFAULT 'PENDENTE',
  enviado_em DATETIME NULL,
  pago_em DATETIME NULL,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  criado_por INT NULL,
  observacoes TEXT NULL,
  UNIQUE KEY uniq_pagamento_colab_mes (colaborador_id, mes_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- pagamento_itens: link tasks included in a pagamento
CREATE TABLE IF NOT EXISTS pagamento_itens (
  idpagamento_item INT AUTO_INCREMENT PRIMARY KEY,
  pagamento_id INT NOT NULL,
  origem ENUM('funcao_imagem','acompanhamento','animacao') NOT NULL,
  origem_id INT NOT NULL,
  valor DECIMAL(12,2) DEFAULT 0.00,
  observacao VARCHAR(255) DEFAULT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pagamento_itens_pagamento FOREIGN KEY (pagamento_id) REFERENCES pagamentos(idpagamento) ON DELETE CASCADE,
  INDEX idx_origem (origem, origem_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- pagamento_eventos: audit trail for actions
CREATE TABLE IF NOT EXISTS pagamento_eventos (
  idpagamento_evento INT AUTO_INCREMENT PRIMARY KEY,
  pagamento_id INT NOT NULL,
  tipo VARCHAR(50) NOT NULL, -- created, status_change, enviado, pago, comentario, etc
  descricao TEXT NULL,
  usuario_id INT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_eventos_pagamento FOREIGN KEY (pagamento_id) REFERENCES pagamentos(idpagamento) ON DELETE CASCADE,
  INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
