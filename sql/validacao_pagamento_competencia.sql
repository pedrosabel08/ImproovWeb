-- Consultas somente de leitura. SET altera apenas a variável desta sessão.
SET @competencia = '2026-09';

-- 1. Cabeçalho e revisão capturada. Total oficial deve ser NULL em andamento.
SELECT c.id,c.competencia,c.estado,c.criado_em,c.previsto_em,c.concluido_em,
 c.concluido_por,c.quitado_em,c.total_fechado_centavos,
 COUNT(m.fechamento_id) aptos,COUNT(m.revisao_id) revisados,
 COUNT(m.fechamento_id)-COUNT(m.revisao_id) pendentes_revisao,
 COALESCE(SUM(m.total_centavos),0) parcial_revisado_centavos,
 COUNT(m.pago_em) pessoas_pagas
FROM pagamento_competencia c
LEFT JOIN pagamento_competencia_colaborador m ON m.competencia_id=c.id
WHERE CAST(c.competencia AS BINARY)=CAST(@competencia AS BINARY) GROUP BY c.id;

-- 2. Fonte financeira: vínculos ao ledger, sem recalcular tarefas/preços.
SELECT c.competencia,c.estado,c.total_fechado_centavos,
 CASE WHEN c.estado='CONCLUIDO' THEN COALESCE(p.pago,0) END pago_centavos,
 CASE WHEN c.estado='CONCLUIDO' THEN c.total_fechado_centavos-COALESCE(p.pago,0) END pendente_centavos
FROM pagamento_competencia c LEFT JOIN (
 SELECT m.competencia_id,SUM(ROUND(pi.valor*100)) pago
 FROM pagamento_competencia_colaborador m
 JOIN pagamento_competencia_lancamento l ON l.fechamento_id=m.fechamento_id
 JOIN pagamento_itens pi ON pi.idpagamento_item=l.pagamento_item_id GROUP BY m.competencia_id
) p ON p.competencia_id=c.id WHERE CAST(c.competencia AS BINARY)=CAST(@competencia AS BINARY);

-- 3. Todos os participantes históricos, inclusive FIXO sem tarefas.
SELECT f.colaborador_id,m.nome,m.tipo_remuneracao,m.fixo_cadastro,m.revisao_id,
 m.total_centavos,COALESCE(p.pago,0) pago_vinculado_centavos,m.revisado_em,m.pago_em,m.pago_por
FROM pagamento_competencia c JOIN pagamento_competencia_colaborador m ON m.competencia_id=c.id
JOIN pagamento_fechamento f ON f.id=m.fechamento_id LEFT JOIN (
 SELECT l.fechamento_id,SUM(ROUND(pi.valor*100)) pago FROM pagamento_competencia_lancamento l
 JOIN pagamento_itens pi ON pi.idpagamento_item=l.pagamento_item_id GROUP BY l.fechamento_id
) p ON p.fechamento_id=m.fechamento_id
WHERE CAST(c.competencia AS BINARY)=CAST(@competencia AS BINARY) ORDER BY m.nome;

-- 4. Inconsistências: todas estas consultas devem retornar zero linhas.
SELECT competencia,COUNT(*) duplicados FROM pagamento_competencia GROUP BY competencia HAVING COUNT(*)>1;
SELECT m.competencia_id,f.colaborador_id,COUNT(*) duplicados
FROM pagamento_competencia_colaborador m JOIN pagamento_fechamento f ON f.id=m.fechamento_id
GROUP BY m.competencia_id,f.colaborador_id HAVING COUNT(*)>1;
SELECT c.id,'CONCLUIDO_INVALIDO' erro FROM pagamento_competencia c WHERE c.estado='CONCLUIDO' AND (
 NOT EXISTS(SELECT 1 FROM pagamento_competencia_colaborador m WHERE m.competencia_id=c.id)
 OR EXISTS(SELECT 1 FROM pagamento_competencia_colaborador m WHERE m.competencia_id=c.id
 AND (m.revisao_id IS NULL OR m.revisado_em IS NULL OR m.total_centavos IS NULL))
 OR c.total_fechado_centavos<>(SELECT SUM(m.total_centavos) FROM pagamento_competencia_colaborador m WHERE m.competencia_id=c.id));
SELECT c.id,'QUITACAO_INVALIDA' erro FROM pagamento_competencia c WHERE c.quitado_em IS NOT NULL AND (
 c.estado<>'CONCLUIDO' OR EXISTS(SELECT 1 FROM pagamento_competencia_colaborador m WHERE m.competencia_id=c.id AND m.pago_em IS NULL));
SELECT m.fechamento_id,'PESSOA_PAGA_INVALIDA' erro FROM pagamento_competencia_colaborador m
JOIN pagamento_competencia c ON c.id=m.competencia_id WHERE m.pago_em IS NOT NULL AND (
 c.estado<>'CONCLUIDO' OR m.total_centavos<>COALESCE((SELECT SUM(ROUND(pi.valor*100))
 FROM pagamento_competencia_lancamento l JOIN pagamento_itens pi ON pi.idpagamento_item=l.pagamento_item_id
 WHERE l.fechamento_id=m.fechamento_id),0));
SELECT m.fechamento_id,'PDF_DIVERGENTE' erro FROM pagamento_competencia_colaborador m
JOIN pagamento_competencia c ON c.id=m.competencia_id
JOIN pagamento_fechamento_revisao r ON r.id=m.revisao_id
WHERE c.estado='CONCLUIDO' AND (m.total_centavos<>CAST(JSON_UNQUOTE(JSON_EXTRACT(r.snapshot_json,'$.composicao.total_final_centavos')) AS SIGNED)
 OR NOT EXISTS(SELECT 1 FROM pagamento_fechamento_documento d WHERE d.revisao_id=m.revisao_id AND d.estado='CONFIRMADO'));

-- 5. Pendências, responsáveis e estados derivados.
SELECT ch.id,ch.entity_type,ch.status,i.update_mode,i.done,i.done_at
FROM pagamento_competencia c JOIN checklist_operacional ch ON ch.module_key='pagamentos' AND ch.entity_id=c.id
JOIN checklist_operacional_item i ON i.checklist_id=ch.id WHERE CAST(c.competencia AS BINARY)=CAST(@competencia AS BINARY);
SELECT r.colaborador_id,p.nome_colaborador FROM pagamento_competencia c
JOIN pagamento_competencia_responsavel r ON r.competencia_id=c.id
JOIN colaborador p ON p.idcolaborador=r.colaborador_id WHERE CAST(c.competencia AS BINARY)=CAST(@competencia AS BINARY);
SELECT ch.id,'PENDENCIA_INCONSISTENTE' erro FROM pagamento_competencia c
JOIN checklist_operacional ch ON ch.module_key='pagamentos' AND ch.entity_id=c.id
WHERE ch.entity_type IN ('fechamento','pagamento') AND (ch.status='concluido')<>
 CASE WHEN ch.entity_type='fechamento' THEN c.estado='CONCLUIDO' ELSE c.quitado_em IS NOT NULL END;

-- 6. Auditoria e Slack (não retorna webhook, credenciais ou conteúdo pessoal).
SELECT e.id,e.tipo,e.fechamento_id,e.usuario_id,e.criado_em,e.chave
FROM pagamento_competencia_evento e JOIN pagamento_competencia c ON c.id=e.competencia_id
WHERE CAST(c.competencia AS BINARY)=CAST(@competencia AS BINARY) ORDER BY e.id;
SELECT e.id,e.event_type,e.idempotency_key,e.status,n.id notificacao_id,d.id entrega_id,d.status entrega_status,d.sent_at,d.attempt_count
FROM flow_connect_events e LEFT JOIN flow_connect_notifications n ON n.event_id=e.id
LEFT JOIN flow_connect_deliveries d ON d.notification_id=n.id
WHERE CAST(e.idempotency_key AS BINARY) IN (CAST(CONCAT('pagamento:fechamento:',@competencia,':v1') AS BINARY),CAST(CONCAT('pagamento:pagamento:',@competencia,':v1') AS BINARY));
SELECT idempotency_key,COUNT(*) duplicados FROM flow_connect_events
WHERE event_type IN ('pagamento.competencia.fechamento','pagamento.competencia.pagamento')
GROUP BY idempotency_key HAVING COUNT(*)>1;

-- 7. Guardas de banco desta migration: esperado 12 gatilhos.
SELECT TRIGGER_NAME,EVENT_MANIPULATION,EVENT_OBJECT_TABLE FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME IN ('pc_no_delete','pc_snapshot','pcc_no_delete','pcc_snapshot',
 'pce_no_update','pce_no_delete','pcl_no_update','pcl_no_delete','pcl_ledger_guard','pcc_roster_guard','pcc_payment_guard','pf_competencia_guard') ORDER BY TRIGGER_NAME;

-- 8. Reconciliação histórica autorizada de Adriana: R$ 3.840 e 64 itens em setembro.
SELECT p.idpagamento,p.colaborador_id,p.mes_ref,p.status,p.valor_total,COUNT(pi.idpagamento_item) itens,SUM(pi.valor) valor_pago
FROM pagamentos p JOIN pagamento_itens pi ON pi.pagamento_id=p.idpagamento
WHERE p.colaborador_id=14 AND CAST(p.mes_ref AS BINARY)=CAST(@competencia AS BINARY) GROUP BY p.idpagamento;
