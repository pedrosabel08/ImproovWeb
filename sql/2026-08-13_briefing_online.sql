-- Briefing Online: domain tables. This migration is intentionally independent
-- from the legacy briefing_requisitos_arquivo tables.

CREATE TABLE IF NOT EXISTS briefing_template (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(180) NOT NULL,
  versao INT UNSIGNED NOT NULL DEFAULT 1,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  exige_conferencia_interna TINYINT(1) NOT NULL DEFAULT 1,
  revisor_padrao_colaborador_id INT NULL,
  criado_por_colaborador_id INT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_briefing_template_active (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefing_template_section (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  template_id BIGINT UNSIGNED NOT NULL,
  titulo VARCHAR(180) NOT NULL,
  descricao TEXT NULL,
  ordem INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id), KEY idx_bts_template (template_id),
  CONSTRAINT fk_bts_template FOREIGN KEY (template_id) REFERENCES briefing_template(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefing_template_question (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  section_id BIGINT UNSIGNED NOT NULL,
  codigo VARCHAR(100) NULL,
  pergunta TEXT NOT NULL,
  ajuda TEXT NULL,
  tipo ENUM('SHORT_TEXT','LONG_TEXT','YES_NO','SINGLE_SELECT','MULTI_SELECT','NUMBER','DATE','LINK','REFERENCE') NOT NULL,
  obrigatoria TINYINT(1) NOT NULL DEFAULT 0,
  permite_nao_aplica TINYINT(1) NOT NULL DEFAULT 0,
  ordem INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  validacao_json JSON NULL,
  PRIMARY KEY (id), KEY idx_btq_section (section_id),
  CONSTRAINT fk_btq_section FOREIGN KEY (section_id) REFERENCES briefing_template_section(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefing_template_question_option (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id BIGINT UNSIGNED NOT NULL,
  rotulo VARCHAR(255) NOT NULL,
  valor VARCHAR(255) NOT NULL,
  ordem INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id), KEY idx_btqo_question (question_id),
  CONSTRAINT fk_btqo_question FOREIGN KEY (question_id) REFERENCES briefing_template_question(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefing_online (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  obra_id INT NOT NULL,
  template_id BIGINT UNSIGNED NULL,
  template_versao INT UNSIGNED NULL,
  briefing_origem_id BIGINT UNSIGNED NULL,
  titulo VARCHAR(180) NOT NULL,
  status ENUM('RASCUNHO','PRONTO_PARA_ENVIO','AGUARDANDO_CLIENTE','EM_PREENCHIMENTO','EM_CONFERENCIA','AJUSTES_SOLICITADOS','APROVADO') NOT NULL DEFAULT 'RASCUNHO',
  exige_conferencia_interna TINYINT(1) NOT NULL DEFAULT 1,
  revisor_colaborador_id INT NULL,
  criado_por_colaborador_id INT NULL,
  prazo_em DATETIME NULL,
  link_expira_em DATETIME NULL,
  enviado_em DATETIME NULL,
  aprovado_em DATETIME NULL,
  aprovado_por_colaborador_id INT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_briefing_obra_status (obra_id,status), KEY idx_briefing_prazo (prazo_em),
  CONSTRAINT fk_briefing_template FOREIGN KEY (template_id) REFERENCES briefing_template(id) ON DELETE SET NULL,
  CONSTRAINT fk_briefing_origem FOREIGN KEY (briefing_origem_id) REFERENCES briefing_online(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefing_section (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  briefing_id BIGINT UNSIGNED NOT NULL,
  template_section_id BIGINT UNSIGNED NULL,
  titulo VARCHAR(180) NOT NULL,
  descricao TEXT NULL,
  ordem INT NOT NULL DEFAULT 0,
  ativa TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id), KEY idx_bs_briefing (briefing_id),
  CONSTRAINT fk_bs_briefing FOREIGN KEY (briefing_id) REFERENCES briefing_online(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefing_question (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  briefing_section_id BIGINT UNSIGNED NOT NULL,
  template_question_id BIGINT UNSIGNED NULL,
  codigo VARCHAR(100) NULL,
  pergunta TEXT NOT NULL,
  ajuda TEXT NULL,
  tipo ENUM('SHORT_TEXT','LONG_TEXT','YES_NO','SINGLE_SELECT','MULTI_SELECT','NUMBER','DATE','LINK','REFERENCE') NOT NULL,
  obrigatoria TINYINT(1) NOT NULL DEFAULT 0,
  permite_nao_aplica TINYINT(1) NOT NULL DEFAULT 0,
  ordem INT NOT NULL DEFAULT 0,
  ativa TINYINT(1) NOT NULL DEFAULT 1,
  validacao_json JSON NULL,
  PRIMARY KEY (id), KEY idx_bq_section (briefing_section_id),
  CONSTRAINT fk_bq_section FOREIGN KEY (briefing_section_id) REFERENCES briefing_section(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefing_question_option (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id BIGINT UNSIGNED NOT NULL,
  rotulo VARCHAR(255) NOT NULL,
  valor VARCHAR(255) NOT NULL,
  ordem INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id), KEY idx_bqo_question (question_id),
  CONSTRAINT fk_bqo_question FOREIGN KEY (question_id) REFERENCES briefing_question(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefing_participant (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  briefing_id BIGINT UNSIGNED NOT NULL,
  contato_cliente_id INT NULL,
  email VARCHAR(255) NOT NULL,
  nome VARCHAR(180) NOT NULL,
  empresa VARCHAR(180) NULL,
  papel ENUM('CLIENTE','INTERNO_VISUALIZADOR','INTERNO_EDITOR','REVISOR') NOT NULL DEFAULT 'CLIENTE',
  verificado_em DATETIME NULL,
  ultimo_acesso_em DATETIME NULL,
  ultima_atividade_em DATETIME NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_bp_briefing_email (briefing_id,email), KEY idx_bp_contact (contato_cliente_id),
  CONSTRAINT fk_bp_briefing FOREIGN KEY (briefing_id) REFERENCES briefing_online(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefing_access_link (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  briefing_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira_em DATETIME NOT NULL,
  revogado_em DATETIME NULL,
  ultimo_uso_em DATETIME NULL,
  criado_por_colaborador_id INT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_bal_token (token_hash), KEY idx_bal_briefing (briefing_id),
  CONSTRAINT fk_bal_briefing FOREIGN KEY (briefing_id) REFERENCES briefing_online(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefing_otp_challenge (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  access_link_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(255) NOT NULL,
  code_hash CHAR(64) NOT NULL,
  tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  expira_em DATETIME NOT NULL,
  verificado_em DATETIME NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_boc_lookup (access_link_id,email,expira_em),
  CONSTRAINT fk_boc_link FOREIGN KEY (access_link_id) REFERENCES briefing_access_link(id) ON DELETE CASCADE,
  CONSTRAINT fk_boc_participant FOREIGN KEY (participant_id) REFERENCES briefing_participant(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefing_external_session (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  briefing_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  csrf_hash CHAR(64) NOT NULL,
  expira_em DATETIME NOT NULL,
  revogado_em DATETIME NULL,
  ultimo_acesso_em DATETIME NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_bes_token (token_hash), KEY idx_bes_briefing_participant (briefing_id,participant_id),
  CONSTRAINT fk_bes_briefing FOREIGN KEY (briefing_id) REFERENCES briefing_online(id) ON DELETE CASCADE,
  CONSTRAINT fk_bes_participant FOREIGN KEY (participant_id) REFERENCES briefing_participant(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefing_answer (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  briefing_question_id BIGINT UNSIGNED NOT NULL,
  valor_json JSON NULL,
  nao_aplica TINYINT(1) NOT NULL DEFAULT 0,
  versao INT UNSIGNED NOT NULL DEFAULT 1,
  respondido_por_participant_id BIGINT UNSIGNED NULL,
  respondido_em DATETIME NULL,
  atualizado_por_participant_id BIGINT UNSIGNED NULL,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_ba_question (briefing_question_id),
  CONSTRAINT fk_ba_question FOREIGN KEY (briefing_question_id) REFERENCES briefing_question(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefing_answer_operation (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  briefing_id BIGINT UNSIGNED NOT NULL,
  operacao_uuid CHAR(36) NOT NULL,
  answer_id BIGINT UNSIGNED NULL,
  resultado_json JSON NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_bao_operation (briefing_id,operacao_uuid),
  CONSTRAINT fk_bao_briefing FOREIGN KEY (briefing_id) REFERENCES briefing_online(id) ON DELETE CASCADE,
  CONSTRAINT fk_bao_answer FOREIGN KEY (answer_id) REFERENCES briefing_answer(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefing_answer_edit_session (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  answer_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  antes_json JSON NULL,
  depois_json JSON NULL,
  iniciado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_salvamento_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fechado_em DATETIME NULL,
  PRIMARY KEY (id), KEY idx_baes_open (answer_id,participant_id,fechado_em),
  CONSTRAINT fk_baes_answer FOREIGN KEY (answer_id) REFERENCES briefing_answer(id) ON DELETE CASCADE,
  CONSTRAINT fk_baes_participant FOREIGN KEY (participant_id) REFERENCES briefing_participant(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefing_question_request (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  briefing_question_id BIGINT UNSIGNED NOT NULL,
  mensagem TEXT NOT NULL,
  solicitado_por_colaborador_id INT NOT NULL,
  status ENUM('SOLICITADO','RESPONDIDO_AGUARDANDO_CONFERENCIA','RESOLVIDO') NOT NULL DEFAULT 'SOLICITADO',
  respondido_em DATETIME NULL,
  resolvido_por_colaborador_id INT NULL,
  resolvido_em DATETIME NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_bqr_question_status (briefing_question_id,status),
  CONSTRAINT fk_bqr_question FOREIGN KEY (briefing_question_id) REFERENCES briefing_question(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefing_comment (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  briefing_id BIGINT UNSIGNED NOT NULL,
  briefing_question_id BIGINT UNSIGNED NULL,
  visibilidade ENUM('INTERNO','CLIENTE') NOT NULL,
  mensagem TEXT NOT NULL,
  autor_tipo ENUM('COLABORADOR','PARTICIPANTE') NOT NULL,
  autor_colaborador_id INT NULL,
  autor_participant_id BIGINT UNSIGNED NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_bc_briefing (briefing_id,visibilidade),
  CONSTRAINT fk_bc_briefing FOREIGN KEY (briefing_id) REFERENCES briefing_online(id) ON DELETE CASCADE,
  CONSTRAINT fk_bc_question FOREIGN KEY (briefing_question_id) REFERENCES briefing_question(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefing_attachment (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  briefing_id BIGINT UNSIGNED NOT NULL,
  briefing_question_id BIGINT UNSIGNED NULL,
  answer_id BIGINT UNSIGNED NULL,
  caminho VARCHAR(500) NOT NULL,
  nome_original VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  tamanho_bytes BIGINT UNSIGNED NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  autor_participant_id BIGINT UNSIGNED NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_ba_briefing (briefing_id),
  CONSTRAINT fk_batt_briefing FOREIGN KEY (briefing_id) REFERENCES briefing_online(id) ON DELETE CASCADE,
  CONSTRAINT fk_batt_question FOREIGN KEY (briefing_question_id) REFERENCES briefing_question(id) ON DELETE SET NULL,
  CONSTRAINT fk_batt_answer FOREIGN KEY (answer_id) REFERENCES briefing_answer(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefing_event (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  briefing_id BIGINT UNSIGNED NOT NULL,
  tipo VARCHAR(100) NOT NULL,
  ator_tipo ENUM('SISTEMA','COLABORADOR','PARTICIPANTE') NOT NULL DEFAULT 'SISTEMA',
  ator_colaborador_id INT NULL,
  ator_participant_id BIGINT UNSIGNED NULL,
  metadata_json JSON NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_be_briefing_date (briefing_id,criado_em),
  CONSTRAINT fk_be_briefing FOREIGN KEY (briefing_id) REFERENCES briefing_online(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefing_snapshot (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  briefing_id BIGINT UNSIGNED NOT NULL,
  versao_snapshot INT UNSIGNED NOT NULL,
  tipo ENUM('ENVIADO','APROVADO') NOT NULL,
  conteudo_json JSON NOT NULL,
  hash_sha256 CHAR(64) NOT NULL,
  criado_por_tipo ENUM('SISTEMA','COLABORADOR','PARTICIPANTE') NOT NULL,
  criado_por_id BIGINT UNSIGNED NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_bs_version_kind (briefing_id,versao_snapshot,tipo),
  CONSTRAINT fk_bsnap_briefing FOREIGN KEY (briefing_id) REFERENCES briefing_online(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
