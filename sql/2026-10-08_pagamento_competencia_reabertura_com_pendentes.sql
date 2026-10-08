-- Reabertura auditada de uma competência parcialmente paga para corrigir somente
-- um participante que continua pendente. Não altera nem remove pagamentos existentes.
-- Executar como administrador MySQL, em janela sem operações concorrentes de fechamento.
DELIMITER $$

DROP TRIGGER IF EXISTS pc_snapshot$$

CREATE TRIGGER pc_snapshot BEFORE UPDATE ON pagamento_competencia FOR EACH ROW
BEGIN
 IF NEW.competencia<>OLD.competencia OR NEW.criado_em<>OLD.criado_em OR NEW.criado_por<>OLD.criado_por THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Identidade da competência é imutável';
 END IF;
 IF NEW.previsto_em<>OLD.previsto_em AND (OLD.estado<>'EM_ANDAMENTO' OR NEW.estado<>'EM_ANDAMENTO' OR NOT EXISTS(
   SELECT 1 FROM pagamento_competencia_evento e WHERE e.competencia_id=OLD.id AND e.tipo='PREVISAO_ALTERADA'
    AND JSON_UNQUOTE(JSON_EXTRACT(e.antes_json,'$.previsto_em'))=CAST(OLD.previsto_em AS CHAR)
    AND JSON_UNQUOTE(JSON_EXTRACT(e.depois_json,'$.previsto_em'))=CAST(NEW.previsto_em AS CHAR))) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Previsão exige fechamento aberto e alteração auditada';
 END IF;
 IF OLD.estado='CONCLUIDO' AND NEW.estado='EM_ANDAMENTO' THEN
  IF OLD.quitado_em IS NOT NULL OR NEW.quitado_em IS NOT NULL OR NEW.total_fechado_centavos IS NOT NULL
   OR NEW.concluido_em IS NOT NULL OR NEW.concluido_por IS NOT NULL
   OR NOT EXISTS(
    SELECT 1 FROM pagamento_competencia_evento e
    JOIN pagamento_competencia_colaborador m ON m.competencia_id=e.competencia_id AND m.fechamento_id=e.fechamento_id
    JOIN pagamento_fechamento f ON f.id=m.fechamento_id
    JOIN usuario u ON u.idusuario=e.usuario_id
    WHERE e.competencia_id=OLD.id AND e.tipo='REABERTURA' AND e.chave LIKE 'reabrir:%' AND e.criado_em>OLD.concluido_em
     AND m.pago_em IS NULL
     AND f.competencia=OLD.competencia AND f.colaborador_id=CAST(JSON_UNQUOTE(JSON_EXTRACT(e.depois_json,'$.colaborador_id')) AS UNSIGNED)
     AND JSON_UNQUOTE(JSON_EXTRACT(e.antes_json,'$.estado'))='CONCLUIDO'
     AND CAST(JSON_UNQUOTE(JSON_EXTRACT(e.antes_json,'$.total_fechado_centavos')) AS SIGNED)=OLD.total_fechado_centavos
     AND JSON_UNQUOTE(JSON_EXTRACT(e.depois_json,'$.estado'))='EM_ANDAMENTO'
     AND CAST(JSON_UNQUOTE(JSON_EXTRACT(e.depois_json,'$.fechamento_id')) AS UNSIGNED)=m.fechamento_id
     AND CAST(JSON_UNQUOTE(JSON_EXTRACT(e.depois_json,'$.colaborador_id')) AS UNSIGNED)=f.colaborador_id
     AND CAST(JSON_UNQUOTE(JSON_EXTRACT(e.depois_json,'$.autor_id')) AS UNSIGNED)=e.usuario_id
     AND u.ativo=1 AND u.nivel_acesso IN (1,5)) THEN
   SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reabertura exige auditoria, participante não pago e competência não quitada';
  END IF;
 ELSEIF OLD.estado='CONCLUIDO' AND (NEW.estado<>OLD.estado OR NOT(NEW.total_fechado_centavos<=>OLD.total_fechado_centavos)
   OR NOT(NEW.concluido_em<=>OLD.concluido_em) OR NOT(NEW.concluido_por<=>OLD.concluido_por)) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fechamento concluído é imutável';
 END IF;
 IF NEW.estado='CONCLUIDO' AND OLD.estado<>'CONCLUIDO' THEN
  IF NOT EXISTS(SELECT 1 FROM pagamento_competencia_colaborador WHERE competencia_id=OLD.id)
   OR EXISTS(SELECT 1 FROM pagamento_competencia_colaborador WHERE competencia_id=OLD.id AND (revisao_id IS NULL OR revisado_em IS NULL OR total_centavos IS NULL)) THEN
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

DELIMITER;