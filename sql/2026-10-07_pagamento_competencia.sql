-- Aditiva. Sem backfill de competências, pagamentos ou participação.
ALTER TABLE pagamento_fechamento_decisao MODIFY tipo ENUM('FIXO','BONUS','LIQUIDACAO','DESCONTO') NOT NULL;
ALTER TABLE pagamento_fechamento_operacao MODIFY tipo ENUM('PREPARAR','FIXO','BONUS','LIQUIDACAO','DESCONTO') NOT NULL;
CREATE TABLE IF NOT EXISTS pagamento_competencia (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 competencia CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 estado ENUM('EM_ANDAMENTO','CONCLUIDO') NOT NULL DEFAULT 'EM_ANDAMENTO',
 criado_em DATETIME(6) NOT NULL, criado_por INT NOT NULL,
 previsto_em DATE NOT NULL, concluido_em DATETIME(6) NULL, concluido_por INT NULL,
 total_fechado_centavos BIGINT NULL, quitado_em DATETIME(6) NULL,
 UNIQUE KEY uq_pc_competencia (competencia),
 FOREIGN KEY (criado_por) REFERENCES usuario(idusuario),
 FOREIGN KEY (concluido_por) REFERENCES usuario(idusuario),
 CHECK ((estado='EM_ANDAMENTO' AND total_fechado_centavos IS NULL AND concluido_em IS NULL)
     OR (estado='CONCLUIDO' AND total_fechado_centavos>=0 AND concluido_em IS NOT NULL AND concluido_por IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagamento_competencia_colaborador (
 competencia_id INT UNSIGNED NOT NULL, fechamento_id BIGINT UNSIGNED NOT NULL,
 nome VARCHAR(255) NOT NULL, tipo_remuneracao VARCHAR(20) NULL, fixo_cadastro DECIMAL(12,2) NULL,
 revisao_id BIGINT UNSIGNED NULL, revisado_em DATETIME(6) NULL, revisado_por INT NULL,
 total_centavos BIGINT NULL, reconciliacao_json JSON NULL,
 pago_em DATE NULL, pago_por INT NULL,
 PRIMARY KEY (competencia_id,fechamento_id), UNIQUE KEY uq_pcc_fechamento (fechamento_id),
 FOREIGN KEY (competencia_id) REFERENCES pagamento_competencia(id),
 FOREIGN KEY (fechamento_id) REFERENCES pagamento_fechamento(id),
 FOREIGN KEY (revisao_id,fechamento_id) REFERENCES pagamento_fechamento_revisao(id,fechamento_id),
 FOREIGN KEY (revisado_por) REFERENCES usuario(idusuario), FOREIGN KEY (pago_por) REFERENCES usuario(idusuario),
 CHECK (total_centavos IS NULL OR total_centavos>=0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagamento_competencia_evento (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 competencia_id INT UNSIGNED NOT NULL, fechamento_id BIGINT UNSIGNED NULL,
 tipo VARCHAR(40) NOT NULL, chave VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_hash CHAR(64) NOT NULL, usuario_id INT NOT NULL, criado_em DATETIME(6) NOT NULL,
 antes_json JSON NOT NULL, depois_json JSON NOT NULL,
 UNIQUE KEY uq_pce_chave (competencia_id,chave),
 FOREIGN KEY (competencia_id) REFERENCES pagamento_competencia(id),
 FOREIGN KEY (fechamento_id) REFERENCES pagamento_fechamento(id), FOREIGN KEY (usuario_id) REFERENCES usuario(idusuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagamento_competencia_responsavel (
 competencia_id INT UNSIGNED NOT NULL, colaborador_id INT NOT NULL,
 PRIMARY KEY (competencia_id,colaborador_id),
 FOREIGN KEY (competencia_id) REFERENCES pagamento_competencia(id),
 FOREIGN KEY (colaborador_id) REFERENCES colaborador(idcolaborador)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vínculo ao ledger existente. Não armazena uma segunda cópia do valor pago.
CREATE TABLE IF NOT EXISTS pagamento_competencia_lancamento (
 pagamento_item_id INT NOT NULL PRIMARY KEY, fechamento_id BIGINT UNSIGNED NOT NULL,
 FOREIGN KEY (pagamento_item_id) REFERENCES pagamento_itens(idpagamento_item) ON DELETE RESTRICT,
 FOREIGN KEY (fechamento_id) REFERENCES pagamento_fechamento(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
CREATE TRIGGER pc_no_delete BEFORE DELETE ON pagamento_competencia FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Competência financeira não pode ser excluída'; END$$
CREATE TRIGGER pc_snapshot BEFORE UPDATE ON pagamento_competencia FOR EACH ROW
BEGIN
 IF NEW.competencia<>OLD.competencia OR NEW.criado_em<>OLD.criado_em OR NEW.criado_por<>OLD.criado_por OR NEW.previsto_em<>OLD.previsto_em THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Identidade da competência é imutável';
 END IF;
 IF OLD.estado='CONCLUIDO' AND (NEW.estado<>OLD.estado OR NOT(NEW.total_fechado_centavos<=>OLD.total_fechado_centavos) OR NOT(NEW.concluido_em<=>OLD.concluido_em) OR NOT(NEW.concluido_por<=>OLD.concluido_por)) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fechamento concluído é imutável';
 END IF;
 IF NEW.estado='CONCLUIDO' AND OLD.estado<>'CONCLUIDO' THEN
  IF NOT EXISTS(SELECT 1 FROM pagamento_competencia_colaborador WHERE competencia_id=OLD.id) OR EXISTS(SELECT 1 FROM pagamento_competencia_colaborador WHERE competencia_id=OLD.id AND (revisao_id IS NULL OR revisado_em IS NULL OR total_centavos IS NULL)) THEN
   SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Há colaboradores pendentes de revisão';
  END IF;
  IF NEW.total_fechado_centavos<>(SELECT SUM(total_centavos) FROM pagamento_competencia_colaborador WHERE competencia_id=OLD.id) THEN
   SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Total fechado divergente';
  END IF;
 END IF;
 IF NEW.quitado_em IS NOT NULL AND (NEW.estado<>'CONCLUIDO' OR EXISTS(SELECT 1 FROM pagamento_competencia_colaborador WHERE competencia_id=OLD.id AND pago_em IS NULL)) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Há colaboradores pendentes de pagamento';
 END IF;
 IF OLD.quitado_em IS NOT NULL AND NOT(NEW.quitado_em<=>OLD.quitado_em) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Quitação não pode ser removida';
 END IF;
END$$
CREATE TRIGGER pcc_no_delete BEFORE DELETE ON pagamento_competencia_colaborador FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Participantes históricos não podem ser removidos'; END$$
CREATE TRIGGER pcc_snapshot BEFORE UPDATE ON pagamento_competencia_colaborador FOR EACH ROW
BEGIN
 IF NEW.competencia_id<>OLD.competencia_id OR NEW.fechamento_id<>OLD.fechamento_id OR NEW.nome<>OLD.nome OR NOT(NEW.tipo_remuneracao<=>OLD.tipo_remuneracao) OR NOT(NEW.fixo_cadastro<=>OLD.fixo_cadastro) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Participante histórico é imutável';
 END IF;
 IF (SELECT estado FROM pagamento_competencia WHERE id=OLD.competencia_id)='CONCLUIDO' AND
 (NOT(NEW.revisao_id<=>OLD.revisao_id) OR NOT(NEW.total_centavos<=>OLD.total_centavos) OR NOT(NEW.reconciliacao_json<=>OLD.reconciliacao_json) OR NOT(NEW.revisado_em<=>OLD.revisado_em) OR NOT(NEW.revisado_por<=>OLD.revisado_por)) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Snapshot individual concluído é imutável';
 END IF;
 IF OLD.pago_em IS NOT NULL AND (NOT(NEW.pago_em<=>OLD.pago_em) OR NOT(NEW.pago_por<=>OLD.pago_por)) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Pagamento confirmado é imutável';
 END IF;
END$$
CREATE TRIGGER pce_no_update BEFORE UPDATE ON pagamento_competencia_evento FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria financeira imutável'; END$$
CREATE TRIGGER pce_no_delete BEFORE DELETE ON pagamento_competencia_evento FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria financeira imutável'; END$$
CREATE TRIGGER pcl_no_update BEFORE UPDATE ON pagamento_competencia_lancamento FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Vínculo de pagamento imutável'; END$$
CREATE TRIGGER pcl_no_delete BEFORE DELETE ON pagamento_competencia_lancamento FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Vínculo de pagamento imutável'; END$$
CREATE TRIGGER pcl_ledger_guard BEFORE UPDATE ON pagamento_itens FOR EACH ROW
BEGIN
 IF EXISTS(SELECT 1 FROM pagamento_competencia_lancamento WHERE pagamento_item_id=OLD.idpagamento_item) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Lançamento financeiro vinculado ao fechamento é imutável';
 END IF;
END$$
CREATE TRIGGER pcc_roster_guard BEFORE INSERT ON pagamento_competencia_colaborador FOR EACH ROW
BEGIN
 IF EXISTS(SELECT 1 FROM pagamento_competencia_evento WHERE competencia_id=NEW.competencia_id AND tipo='CRIADO') THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Snapshot de participantes já registrado';
 END IF;
 IF NOT EXISTS(SELECT 1 FROM pagamento_fechamento f JOIN pagamento_competencia c ON c.id=NEW.competencia_id WHERE f.id=NEW.fechamento_id AND f.competencia=c.competencia AND c.estado='EM_ANDAMENTO') THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Competência do participante inválida';
 END IF;
END$$
CREATE TRIGGER pcc_payment_guard BEFORE UPDATE ON pagamento_competencia_colaborador FOR EACH ROW
BEGIN
 IF NEW.pago_em IS NOT NULL AND OLD.pago_em IS NULL THEN
  IF (SELECT estado FROM pagamento_competencia WHERE id=OLD.competencia_id)<>'CONCLUIDO' OR NEW.pago_por IS NULL OR NEW.total_centavos IS NULL OR
     NEW.total_centavos<>COALESCE((SELECT SUM(ROUND(pi.valor*100)) FROM pagamento_competencia_lancamento l JOIN pagamento_itens pi ON pi.idpagamento_item=l.pagamento_item_id WHERE l.fechamento_id=OLD.fechamento_id),0) THEN
   SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Liquidação exige fechamento concluído e valor integral';
  END IF;
 END IF;
END$$
CREATE TRIGGER pf_competencia_guard BEFORE UPDATE ON pagamento_fechamento FOR EACH ROW
BEGIN
 IF EXISTS(SELECT 1 FROM pagamento_competencia_colaborador m JOIN pagamento_competencia c ON c.id=m.competencia_id WHERE m.fechamento_id=OLD.id AND c.estado='CONCLUIDO') AND
 (NEW.colaborador_id<>OLD.colaborador_id OR NEW.competencia<>OLD.competencia OR NEW.numero_revisao<>OLD.numero_revisao OR NEW.lock_version<>OLD.lock_version) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Revisão da competência concluída é imutável';
 END IF;
END$$
DELIMITER ;
