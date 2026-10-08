-- Permite acrescentar um participante a um ciclo aberto somente com evento de auditoria prévio.
-- A inserção do evento e do snapshot deve ocorrer na mesma transação da aplicação.
DROP TRIGGER IF EXISTS pcc_roster_guard;

DELIMITER $$
CREATE TRIGGER pcc_roster_guard BEFORE INSERT ON pagamento_competencia_colaborador FOR EACH ROW
BEGIN
 IF NOT EXISTS(
   SELECT 1
   FROM pagamento_fechamento f
   JOIN pagamento_competencia c ON c.id=NEW.competencia_id
   JOIN colaborador p ON p.idcolaborador=f.colaborador_id
   WHERE f.id=NEW.fechamento_id
     AND f.competencia=c.competencia
     AND c.estado='EM_ANDAMENTO'
     AND p.ativo=1
     AND p.participa_fechamento_mensal=1
     AND NEW.nome=p.nome_colaborador
     AND NEW.tipo_remuneracao<=>p.tipo_remuneracao
     AND NEW.fixo_cadastro<=>p.valor_fixo
 ) THEN
   SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Participante não elegível para a competência';
 END IF;

 IF EXISTS(SELECT 1 FROM pagamento_competencia_evento WHERE competencia_id=NEW.competencia_id AND tipo='CRIADO')
   AND NOT EXISTS(
     SELECT 1
     FROM pagamento_competencia_evento e
     JOIN pagamento_fechamento f ON f.id=e.fechamento_id
     WHERE e.competencia_id=NEW.competencia_id
       AND e.fechamento_id=NEW.fechamento_id
       AND e.tipo='PARTICIPANTE_INCLUIDO'
       AND CAST(JSON_UNQUOTE(JSON_EXTRACT(e.depois_json,'$.colaborador_id')) AS UNSIGNED)=f.colaborador_id
   ) THEN
   SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Inclusão após início exige evento de auditoria';
 END IF;
END$$
DELIMITER ;
