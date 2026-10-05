-- FASE 1C-B: modelo registrado ANTES em docs/pagamento-adendos-fase1c-b.md.
-- Aplicar uma vez somente em destino autorizado; sem migração/backfill legado.
CREATE TABLE pagamento_fechamento_documento (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 fechamento_id BIGINT UNSIGNED NOT NULL,
 revisao_id BIGINT UNSIGNED NOT NULL,
 numero INT UNSIGNED NOT NULL,
 uuid CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 estado ENUM('PREVIEW','CONFIRMADO') NULL COMMENT 'NULL: reserva interna, ainda não exposta',
 financial_snapshot_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 modelo_json JSON NOT NULL,
 html_snapshot LONGTEXT NOT NULL,
 template_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 arquivo_preview VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 arquivo_definitivo VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 pdf_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 tamanho_bytes BIGINT UNSIGNED NULL,
 criado_por INT NOT NULL,
 criado_em DATETIME(6) NOT NULL,
 gerado_em DATETIME(6) NULL,
 confirmado_por INT NULL,
 confirmado_em DATETIME(6) NULL,
 CHECK(numero>0),
 CHECK(COALESCE((estado IS NULL AND pdf_hash IS NULL AND tamanho_bytes IS NULL AND gerado_em IS NULL AND confirmado_por IS NULL AND confirmado_em IS NULL)
    OR (estado='PREVIEW' AND pdf_hash IS NOT NULL AND tamanho_bytes>0 AND gerado_em IS NOT NULL AND confirmado_por IS NULL AND confirmado_em IS NULL)
    OR (estado='CONFIRMADO' AND pdf_hash IS NOT NULL AND tamanho_bytes>0 AND gerado_em IS NOT NULL AND confirmado_por IS NOT NULL AND confirmado_em IS NOT NULL),FALSE)=1),
 UNIQUE KEY uq_pdoc_numero (fechamento_id,numero),
 UNIQUE KEY uq_pdoc_uuid (uuid),
 UNIQUE KEY uq_pdoc_preview (arquivo_preview),
 UNIQUE KEY uq_pdoc_definitivo (arquivo_definitivo),
 UNIQUE KEY uq_pdoc_escopo (id,fechamento_id),
 KEY idx_pdoc_revisao (revisao_id,estado),
 KEY idx_pdoc_estado_data (estado,criado_em),
 CONSTRAINT fk_pdoc_fechamento FOREIGN KEY (fechamento_id) REFERENCES pagamento_fechamento(id) ON DELETE RESTRICT,
 CONSTRAINT fk_pdoc_revisao FOREIGN KEY (revisao_id,fechamento_id) REFERENCES pagamento_fechamento_revisao(id,fechamento_id) ON DELETE RESTRICT,
 CONSTRAINT fk_pdoc_criador FOREIGN KEY (criado_por) REFERENCES usuario(idusuario) ON DELETE RESTRICT,
 CONSTRAINT fk_pdoc_confirmador FOREIGN KEY (confirmado_por) REFERENCES usuario(idusuario) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pagamento_fechamento_documento_operacao (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 documento_id BIGINT UNSIGNED NOT NULL,
 autor_id INT NOT NULL,
 chave VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 tipo ENUM('GERAR','VISUALIZAR','CONFIRMAR') NOT NULL,
 estado ENUM('RESERVADA','CONCLUIDA') NOT NULL DEFAULT 'RESERVADA',
 request_json JSON NOT NULL,
 antes_json JSON NOT NULL,
 depois_json JSON NULL,
 criado_em DATETIME(6) NOT NULL,
 concluido_em DATETIME(6) NULL,
 CHECK((estado='RESERVADA' AND depois_json IS NULL AND concluido_em IS NULL) OR (estado='CONCLUIDA' AND depois_json IS NOT NULL AND concluido_em IS NOT NULL)),
 UNIQUE KEY uq_pdop_chave (autor_id,chave),
 KEY idx_pdop_recuperar (estado,tipo,id),
 KEY idx_pdop_visualizacao (documento_id,autor_id,tipo,estado),
 CONSTRAINT fk_pdop_documento FOREIGN KEY (documento_id) REFERENCES pagamento_fechamento_documento(id) ON DELETE RESTRICT,
 CONSTRAINT fk_pdop_autor FOREIGN KEY (autor_id) REFERENCES usuario(idusuario) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
CREATE TRIGGER pdoc_no_update BEFORE UPDATE ON pagamento_fechamento_documento FOR EACH ROW
BEGIN
 IF OLD.estado='CONFIRMADO' OR NOT (NEW.id<=>OLD.id) OR NOT (NEW.fechamento_id<=>OLD.fechamento_id)
 OR NOT (NEW.revisao_id<=>OLD.revisao_id) OR NOT (NEW.numero<=>OLD.numero) OR NOT (NEW.uuid<=>OLD.uuid)
 OR NOT (NEW.financial_snapshot_hash<=>OLD.financial_snapshot_hash) OR NOT (NEW.modelo_json<=>OLD.modelo_json)
 OR NOT (NEW.html_snapshot<=>OLD.html_snapshot) OR NOT (NEW.template_hash<=>OLD.template_hash)
 OR NOT (NEW.arquivo_preview<=>OLD.arquivo_preview) OR NOT (NEW.arquivo_definitivo<=>OLD.arquivo_definitivo)
 OR NOT (NEW.criado_por<=>OLD.criado_por) OR NOT (NEW.criado_em<=>OLD.criado_em) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Documento/base documental imutavel';
 END IF;
 IF OLD.estado IS NULL THEN
  IF NOT (NEW.estado<=>'PREVIEW') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Transicao documental invalida'; END IF;
 ELSEIF OLD.estado='PREVIEW' THEN
  IF NOT (NEW.estado<=>'CONFIRMADO') OR NOT (NEW.pdf_hash<=>OLD.pdf_hash) OR NOT (NEW.tamanho_bytes<=>OLD.tamanho_bytes)
  OR NOT (NEW.gerado_em<=>OLD.gerado_em) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preview/bytes imutaveis'; END IF;
 END IF;
END$$
CREATE TRIGGER pdoc_no_delete BEFORE DELETE ON pagamento_fechamento_documento FOR EACH ROW
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Documento/reserva append-only'$$
CREATE TRIGGER pdop_no_update BEFORE UPDATE ON pagamento_fechamento_documento_operacao FOR EACH ROW
BEGIN
 IF OLD.estado='CONCLUIDA' OR NOT (NEW.estado<=>'CONCLUIDA') OR NOT (NEW.id<=>OLD.id)
 OR NOT (NEW.documento_id<=>OLD.documento_id) OR NOT (NEW.autor_id<=>OLD.autor_id) OR NOT (NEW.chave<=>OLD.chave)
 OR NOT (NEW.request_hash<=>OLD.request_hash) OR NOT (NEW.tipo<=>OLD.tipo) OR NOT (NEW.request_json<=>OLD.request_json)
 OR NOT (NEW.antes_json<=>OLD.antes_json) OR NOT (NEW.criado_em<=>OLD.criado_em) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Operacao documental imutavel';
 END IF;
END$$
CREATE TRIGGER pdop_no_delete BEFORE DELETE ON pagamento_fechamento_documento_operacao FOR EACH ROW
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Operacao documental append-only'$$
DELIMITER ;
