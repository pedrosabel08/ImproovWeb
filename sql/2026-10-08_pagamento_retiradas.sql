-- Reutiliza os journals imutáveis existentes; não altera snapshots históricos.
ALTER TABLE pagamento_fechamento_decisao MODIFY tipo ENUM('FIXO','BONUS','LIQUIDACAO','DESCONTO','SERVICOS') NOT NULL;
ALTER TABLE pagamento_fechamento_operacao MODIFY tipo ENUM('PREPARAR','FIXO','BONUS','LIQUIDACAO','DESCONTO','SERVICOS') NOT NULL;

DELIMITER $$
DROP TRIGGER IF EXISTS pc_snapshot$$
CREATE TRIGGER pc_snapshot BEFORE UPDATE ON pagamento_competencia FOR EACH ROW
BEGIN
 IF NEW.competencia<>OLD.competencia OR NEW.criado_em<>OLD.criado_em OR NEW.criado_por<>OLD.criado_por THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Identidade da competência é imutável';
 END IF;
 IF NEW.previsto_em<>OLD.previsto_em AND (OLD.estado<>'EM_ANDAMENTO' OR NEW.estado<>'EM_ANDAMENTO' OR NOT EXISTS(SELECT 1 FROM pagamento_competencia_evento e WHERE e.competencia_id=OLD.id AND e.tipo='PREVISAO_ALTERADA' AND JSON_UNQUOTE(JSON_EXTRACT(e.antes_json,'$.previsto_em'))=CAST(OLD.previsto_em AS CHAR) AND JSON_UNQUOTE(JSON_EXTRACT(e.depois_json,'$.previsto_em'))=CAST(NEW.previsto_em AS CHAR))) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Previsão exige fechamento aberto e alteração auditada';
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
DELIMITER ;
