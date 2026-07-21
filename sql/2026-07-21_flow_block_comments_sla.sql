-- Flow Block: threads de comentários, edição/exclusão e SLA operacional.

ALTER TABLE flow_issue
    MODIFY COLUMN status ENUM('ABERTA','AGUARDANDO_ACAO','PAUSADA','RESOLVIDA','CANCELADA') NOT NULL DEFAULT 'ABERTA',
    ADD COLUMN sla_atendimento_em DATETIME NULL AFTER encerramento_observacao,
    ADD COLUMN primeira_tratativa_em DATETIME NULL AFTER sla_atendimento_em,
    ADD COLUMN pausada_em DATETIME NULL AFTER primeira_tratativa_em,
    ADD COLUMN pausada_por_colaborador_id INT NULL AFTER pausada_em,
    ADD COLUMN pausa_motivo TEXT NULL AFTER pausada_por_colaborador_id,
    ADD COLUMN retorno_previsto_em DATETIME NULL AFTER pausa_motivo,
    ADD COLUMN proxima_cobranca_em DATETIME NULL AFTER retorno_previsto_em,
    ADD INDEX idx_flow_issue_cobranca (proxima_cobranca_em, status);

UPDATE flow_issue
SET sla_atendimento_em = DATE_ADD(criado_em, INTERVAL 2 HOUR),
    proxima_cobranca_em = DATE_ADD(criado_em, INTERVAL 2 HOUR)
WHERE sla_atendimento_em IS NULL;

ALTER TABLE flow_issue_atividade
    ADD COLUMN atividade_pai_id BIGINT NULL AFTER criado_por_colaborador_id,
    ADD COLUMN atualizado_em DATETIME NULL AFTER criado_em,
    ADD COLUMN excluido_em DATETIME NULL AFTER atualizado_em,
    ADD COLUMN excluido_por_colaborador_id INT NULL AFTER excluido_em,
    ADD INDEX idx_flow_issue_atividade_pai (atividade_pai_id, criado_em);
