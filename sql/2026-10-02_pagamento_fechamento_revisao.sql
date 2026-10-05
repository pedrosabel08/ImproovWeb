-- FASE 1C-A. Modelo previamente registrado em docs/pagamento-adendos-fase1c-a.md.
-- Aplicação manual UMA VEZ, após autorização no destino; NÃO aplicar no banco compartilhado.
-- MySQL 8 / MariaDB 10.4, InnoDB. Sem backfill ou mudanças nas tabelas legadas.
CREATE TABLE pagamento_fechamento (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 colaborador_id INT NOT NULL,
 competencia CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 estado ENUM('PENDENTE','PRONTO') NOT NULL DEFAULT 'PENDENTE',
 lock_version INT UNSIGNED NOT NULL DEFAULT 0,
 numero_revisao INT UNSIGNED NOT NULL DEFAULT 0,
 criado_por INT NOT NULL,
 criado_em DATETIME(6) NOT NULL,
 CHECK(lock_version=numero_revisao),
 UNIQUE KEY uq_pf_escopo (colaborador_id,competencia),
 KEY idx_pf_estado (estado,competencia),
 CONSTRAINT fk_pf_colaborador FOREIGN KEY (colaborador_id) REFERENCES colaborador(idcolaborador) ON DELETE RESTRICT,
 CONSTRAINT fk_pf_autor FOREIGN KEY (criado_por) REFERENCES usuario(idusuario) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pagamento_fechamento_decisao (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 fechamento_id BIGINT UNSIGNED NOT NULL,
 tipo ENUM('FIXO','BONUS','LIQUIDACAO') NOT NULL,
 autor_id INT NOT NULL,
 registrado_em DATETIME(6) NOT NULL,
 motivo VARCHAR(1000) NOT NULL,
 antes_json JSON NOT NULL,
 depois_json JSON NOT NULL,
 UNIQUE KEY uq_pfd_escopo (id,fechamento_id),
 KEY idx_pfd_vigente (fechamento_id,tipo,id),
 CONSTRAINT fk_pfd_fechamento FOREIGN KEY (fechamento_id) REFERENCES pagamento_fechamento(id) ON DELETE RESTRICT,
 CONSTRAINT fk_pfd_autor FOREIGN KEY (autor_id) REFERENCES usuario(idusuario) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pagamento_fechamento_extra (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 decisao_id BIGINT UNSIGNED NOT NULL,
 fechamento_id BIGINT UNSIGNED NOT NULL,
 referencia VARCHAR(191) COLLATE utf8mb4_bin NOT NULL,
 categoria VARCHAR(160) NOT NULL,
 valor_centavos BIGINT NOT NULL CHECK(valor_centavos>0),
 autor_id INT NOT NULL,
 registrado_em DATETIME(6) NOT NULL,
 UNIQUE KEY uq_pfe_referencia (decisao_id,referencia),
 KEY idx_pfe_escopo (fechamento_id,decisao_id),
 CONSTRAINT fk_pfe_decisao FOREIGN KEY (decisao_id,fechamento_id) REFERENCES pagamento_fechamento_decisao(id,fechamento_id) ON DELETE RESTRICT,
 CONSTRAINT fk_pfe_autor FOREIGN KEY (autor_id) REFERENCES usuario(idusuario) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pagamento_fechamento_evidencia (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 decisao_id BIGINT UNSIGNED NOT NULL,
 fechamento_id BIGINT UNSIGNED NOT NULL,
 referencia VARCHAR(191) COLLATE utf8mb4_bin NOT NULL,
 tipo ENUM('PAGAMENTO_FIXO','APURACAO_FIXO_SEM_PAGAMENTO') NOT NULL,
 valor_centavos BIGINT NOT NULL,
 origem_verificavel VARCHAR(1000) NOT NULL,
 motivo VARCHAR(1000) NOT NULL,
 autor_id INT NOT NULL,
 registrado_em DATETIME(6) NOT NULL,
 CHECK((tipo='PAGAMENTO_FIXO' AND valor_centavos>0) OR (tipo='APURACAO_FIXO_SEM_PAGAMENTO' AND valor_centavos=0)),
 UNIQUE KEY uq_pfv_referencia (decisao_id,referencia),
 KEY idx_pfv_escopo (fechamento_id,decisao_id),
 CONSTRAINT fk_pfv_decisao FOREIGN KEY (decisao_id,fechamento_id) REFERENCES pagamento_fechamento_decisao(id,fechamento_id) ON DELETE RESTRICT,
 CONSTRAINT fk_pfv_autor FOREIGN KEY (autor_id) REFERENCES usuario(idusuario) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pagamento_fechamento_revisao (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 fechamento_id BIGINT UNSIGNED NOT NULL,
 numero INT UNSIGNED NOT NULL,
 snapshot_em DATETIME(6) NOT NULL,
 timezone VARCHAR(40) NOT NULL,
 financial_rule_version VARCHAR(80) NOT NULL,
 composition_rule_version VARCHAR(80) NOT NULL,
 estado ENUM('PENDENTE','PRONTO') NOT NULL,
 subtotal_servicos_centavos BIGINT NOT NULL,
 fixo_centavos BIGINT NULL,
 especial_centavos BIGINT NOT NULL,
 extras_centavos BIGINT NULL,
 componentes_conhecidos_centavos BIGINT NOT NULL,
 total_final_centavos BIGINT NULL,
 total_final_determinado BOOLEAN NOT NULL,
 bloqueado BOOLEAN NOT NULL,
 fixo_decisao_id BIGINT UNSIGNED NULL,
 bonus_decisao_id BIGINT UNSIGNED NULL,
 liquidacao_decisao_id BIGINT UNSIGNED NULL,
 snapshot_json JSON NOT NULL,
 snapshot_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 criado_por INT NOT NULL,
 criado_em DATETIME(6) NOT NULL,
 CHECK ((estado='PRONTO' AND total_final_determinado=1 AND bloqueado=0 AND total_final_centavos IS NOT NULL) OR (estado='PENDENTE' AND total_final_determinado=0 AND bloqueado=1 AND total_final_centavos IS NULL)),
 UNIQUE KEY uq_pfr_numero (fechamento_id,numero),
 UNIQUE KEY uq_pfr_escopo (id,fechamento_id),
 KEY idx_pfr_hash (snapshot_hash),
 KEY idx_pfr_autor (criado_por,criado_em),
 CONSTRAINT fk_pfr_fechamento FOREIGN KEY (fechamento_id) REFERENCES pagamento_fechamento(id) ON DELETE RESTRICT,
 CONSTRAINT fk_pfr_autor FOREIGN KEY (criado_por) REFERENCES usuario(idusuario) ON DELETE RESTRICT,
 CONSTRAINT fk_pfr_fixo FOREIGN KEY (fixo_decisao_id,fechamento_id) REFERENCES pagamento_fechamento_decisao(id,fechamento_id) ON DELETE RESTRICT,
 CONSTRAINT fk_pfr_bonus FOREIGN KEY (bonus_decisao_id,fechamento_id) REFERENCES pagamento_fechamento_decisao(id,fechamento_id) ON DELETE RESTRICT,
 CONSTRAINT fk_pfr_liquidacao FOREIGN KEY (liquidacao_decisao_id,fechamento_id) REFERENCES pagamento_fechamento_decisao(id,fechamento_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pagamento_fechamento_operacao (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 fechamento_id BIGINT UNSIGNED NOT NULL,
 chave VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 tipo ENUM('PREPARAR','FIXO','BONUS','LIQUIDACAO') NOT NULL,
 autor_id INT NOT NULL,
 registrado_em DATETIME(6) NOT NULL,
 motivo VARCHAR(1000) NOT NULL,
 antes_json JSON NOT NULL,
 depois_json JSON NOT NULL,
 revisao_id BIGINT UNSIGNED NOT NULL,
 UNIQUE KEY uq_pfo_chave (fechamento_id,chave),
 CONSTRAINT fk_pfo_fechamento FOREIGN KEY (fechamento_id) REFERENCES pagamento_fechamento(id) ON DELETE RESTRICT,
 CONSTRAINT fk_pfo_autor FOREIGN KEY (autor_id) REFERENCES usuario(idusuario) ON DELETE RESTRICT,
 CONSTRAINT fk_pfo_revisao FOREIGN KEY (revisao_id,fechamento_id) REFERENCES pagamento_fechamento_revisao(id,fechamento_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Triggers de uma única instrução, sem DELIMITER; preservam snapshots e histórico.
CREATE TRIGGER pfr_no_update BEFORE UPDATE ON pagamento_fechamento_revisao FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Revisao financeira imutavel';
CREATE TRIGGER pfr_no_delete BEFORE DELETE ON pagamento_fechamento_revisao FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Revisao financeira imutavel';
CREATE TRIGGER pfd_no_update BEFORE UPDATE ON pagamento_fechamento_decisao FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Decisao financeira append-only';
CREATE TRIGGER pfd_no_delete BEFORE DELETE ON pagamento_fechamento_decisao FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Decisao financeira append-only';
CREATE TRIGGER pfe_no_update BEFORE UPDATE ON pagamento_fechamento_extra FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Extra financeiro append-only';
CREATE TRIGGER pfe_no_delete BEFORE DELETE ON pagamento_fechamento_extra FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Extra financeiro append-only';
CREATE TRIGGER pfv_no_update BEFORE UPDATE ON pagamento_fechamento_evidencia FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Evidencia financeira append-only';
CREATE TRIGGER pfv_no_delete BEFORE DELETE ON pagamento_fechamento_evidencia FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Evidencia financeira append-only';
CREATE TRIGGER pfo_no_update BEFORE UPDATE ON pagamento_fechamento_operacao FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Operacao financeira append-only';
CREATE TRIGGER pfo_no_delete BEFORE DELETE ON pagamento_fechamento_operacao FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Operacao financeira append-only';
