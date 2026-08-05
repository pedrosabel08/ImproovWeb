-- Snapshot documental da estrutura atualmente observada no banco flowdb.
--
-- Este arquivo NÃO é uma migration e não deve ser executado automaticamente.
-- O banco real permanece como fonte de verdade nesta etapa.
--
-- Não foi adicionada constraint de unicidade em pagamento_itens: existem cinco
-- pares históricos com a mesma origem/origem_id em competências diferentes e
-- eles precisam ser classificados antes de qualquer mudança de idempotência.

CREATE TABLE IF NOT EXISTS pagamentos (
  idpagamento INT NOT NULL AUTO_INCREMENT,
  colaborador_id INT NOT NULL,
  mes_ref CHAR(7) NOT NULL,
  valor_total DECIMAL(12,2) DEFAULT '0.00',
  status VARCHAR(100) DEFAULT NULL,
  enviado_em DATETIME DEFAULT NULL,
  pago_em DATETIME DEFAULT NULL,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  criado_por INT DEFAULT NULL,
  observacoes TEXT,
  data_envio_validacao DATETIME DEFAULT NULL,
  data_resposta DATETIME DEFAULT NULL,
  data_geracao_adendo DATETIME DEFAULT NULL,
  data_pagamento DATETIME DEFAULT NULL,
  PRIMARY KEY (idpagamento),
  UNIQUE KEY uniq_pagamento_colab_mes (colaborador_id, mes_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS pagamento_itens (
  idpagamento_item INT NOT NULL AUTO_INCREMENT,
  pagamento_id INT NOT NULL,
  origem VARCHAR(100) NOT NULL,
  origem_id INT NOT NULL,
  valor DECIMAL(12,2) DEFAULT '0.00',
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  observacao VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (idpagamento_item),
  KEY fk_pagamento_itens_pagamento (pagamento_id),
  KEY idx_origem (origem, origem_id),
  CONSTRAINT fk_pagamento_itens_pagamento
    FOREIGN KEY (pagamento_id) REFERENCES pagamentos (idpagamento)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS pagamento_eventos (
  idpagamento_evento INT NOT NULL AUTO_INCREMENT,
  pagamento_id INT NOT NULL,
  tipo VARCHAR(50) NOT NULL,
  descricao TEXT,
  usuario_id INT DEFAULT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (idpagamento_evento),
  KEY fk_eventos_pagamento (pagamento_id),
  KEY idx_tipo (tipo),
  CONSTRAINT fk_eventos_pagamento
    FOREIGN KEY (pagamento_id) REFERENCES pagamentos (idpagamento)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS historico_pagamento (
  id INT NOT NULL AUTO_INCREMENT,
  colaborador_id INT NOT NULL,
  funcao_id INT NOT NULL,
  data_pagamento DATE NOT NULL,
  PRIMARY KEY (id),
  KEY colaborador_id (colaborador_id),
  KEY funcao_id (funcao_id),
  CONSTRAINT historico_pagamento_ibfk_1
    FOREIGN KEY (colaborador_id) REFERENCES colaborador (idcolaborador)
    ON DELETE CASCADE,
  CONSTRAINT historico_pagamento_ibfk_2
    FOREIGN KEY (funcao_id) REFERENCES funcao (idfuncao)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resumo_pagamento (
  data VARCHAR(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  colaborador_id INT NOT NULL,
  funcao_id INT NOT NULL,
  total_imagens INT DEFAULT '0',
  PRIMARY KEY (data, colaborador_id, funcao_id),
  KEY colaborador_id (colaborador_id),
  KEY funcao_id (funcao_id),
  CONSTRAINT resumo_pagamento_ibfk_1
    FOREIGN KEY (colaborador_id) REFERENCES colaborador (idcolaborador)
    ON DELETE CASCADE,
  CONSTRAINT resumo_pagamento_ibfk_2
    FOREIGN KEY (funcao_id) REFERENCES funcao (idfuncao)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adendos (
  id INT NOT NULL AUTO_INCREMENT,
  colaborador_id INT NOT NULL,
  competencia VARCHAR(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  status ENUM('nao_gerado','gerado','visualizado','enviado','assinado','recusado','expirado')
    COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nao_gerado',
  zapsign_doc_token VARCHAR(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  sign_url VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  data_envio DATETIME DEFAULT NULL,
  assinado_em DATETIME DEFAULT NULL,
  payload_enviado LONGTEXT COLLATE utf8mb4_unicode_ci,
  arquivo_nome VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  arquivo_path VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_adendo (colaborador_id, competencia),
  KEY idx_adendo_status (status),
  KEY idx_adendo_token (zapsign_doc_token),
  KEY idx_adendo_arquivo (arquivo_nome),
  CONSTRAINT fk_adendos_colaborador
    FOREIGN KEY (colaborador_id) REFERENCES colaborador (idcolaborador)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS log_adendos (
  id INT NOT NULL AUTO_INCREMENT,
  adendo_id INT DEFAULT NULL,
  colaborador_id INT DEFAULT NULL,
  zapsign_doc_token VARCHAR(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  status VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  acao VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  origem VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  ip VARCHAR(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  detalhe TEXT COLLATE utf8mb4_unicode_ci,
  ocorrido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_log_adendo (adendo_id),
  KEY idx_log_adendo_colaborador (colaborador_id),
  KEY idx_log_adendo_token (zapsign_doc_token),
  KEY idx_log_adendo_status (status),
  CONSTRAINT fk_log_adendos_adendo
    FOREIGN KEY (adendo_id) REFERENCES adendos (id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrations futuras devem ser mantidas separadas deste snapshot e só podem
-- ser propostas após a reconciliação dos registros duplicados identificados.
