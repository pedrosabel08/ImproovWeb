-- Flow Block: uma resolução só libera a tarefa após confirmação do dono da tarefa.
ALTER TABLE flow_issue
    ADD COLUMN confirmada_por_colaborador_id INT NULL AFTER encerramento_observacao,
    ADD COLUMN confirmada_em DATETIME NULL AFTER confirmada_por_colaborador_id,
    ADD COLUMN confirmacao_observacao TEXT NULL AFTER confirmada_em,
    ADD INDEX idx_flow_issue_confirmacao (status, confirmada_em);

-- Resoluções sem confirmação passam a ser bloqueantes. A sincronização evita
-- que registros resolvidos durante a transição do fluxo deixem a tarefa em andamento.
UPDATE funcao_imagem fi
JOIN flow_issue i ON i.funcao_imagem_id = fi.idfuncao_imagem
SET fi.status = 'HOLD'
WHERE i.bloqueante = 1
  AND (
      i.status IN ('ABERTA', 'AGUARDANDO_ACAO', 'PAUSADA')
      OR (i.status = 'RESOLVIDA' AND i.confirmada_em IS NULL)
  );
