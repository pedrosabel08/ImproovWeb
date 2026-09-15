-- Projeto: ALMA substitui a marcação manual de Referências.
-- O item histórico referencias_mood é preservado apenas como chave de
-- compatibilidade e passa a ser sincronizado automaticamente.
UPDATE checklist_operacional_item item
JOIN checklist_operacional checklist
  ON checklist.id = item.checklist_id
LEFT JOIN alma_projeto_direcao alma
  ON alma.obra_id = checklist.obra_id
SET item.label = 'ALMA',
    item.required = 1,
    item.update_mode = 'AUTOMATICO',
    -- Referências concluídas manualmente antes desta mudança permanecem
    -- atendidas como ALMA legado; somente itens ainda pendentes exigem ALMA.
    item.done = CASE WHEN alma.id IS NOT NULL OR item.done = 1 THEN 1 ELSE 0 END,
    item.done_by = CASE WHEN alma.id IS NOT NULL OR item.done = 1 THEN COALESCE(item.done_by, 1) ELSE NULL END,
    item.done_at = CASE WHEN alma.id IS NOT NULL OR item.done = 1 THEN COALESCE(item.done_at, NOW()) ELSE NULL END
WHERE checklist.module_key = 'projeto'
  AND item.item_key = 'referencias_mood';

-- Fotográfico: atende quando a obra tem link, ou quando não há plano aberto e
-- existe ao menos um plano concluído. Um plano aberto mantém a pendência ativa,
-- salvo quando o link da obra já foi informado.
UPDATE checklist_operacional_item item
JOIN checklist_operacional checklist
  ON checklist.id = item.checklist_id
JOIN obra
  ON obra.idobra = checklist.obra_id
LEFT JOIN (
    SELECT
        plano.obra_id,
        MAX(CASE WHEN plano.status NOT IN ('CONCLUIDO', 'CANCELADO') THEN 1 ELSE 0 END) AS possui_plano_aberto,
        MAX(CASE WHEN plano.status = 'CONCLUIDO' THEN 1 ELSE 0 END) AS possui_plano_concluido
    FROM fotografico_plano plano
    GROUP BY plano.obra_id
) fotografico
  ON fotografico.obra_id = checklist.obra_id
SET
    item.required = CASE
        WHEN NULLIF(TRIM(COALESCE(obra.fotografico, '')), '') IS NOT NULL
          OR COALESCE(fotografico.possui_plano_aberto, 0) = 1
          OR COALESCE(fotografico.possui_plano_concluido, 0) = 1
        THEN 1 ELSE 0
    END,
    item.done = CASE
        WHEN NULLIF(TRIM(COALESCE(obra.fotografico, '')), '') IS NOT NULL
          OR (
              COALESCE(fotografico.possui_plano_aberto, 0) = 0
              AND COALESCE(fotografico.possui_plano_concluido, 0) = 1
          )
        THEN 1 ELSE 0
    END,
    item.done_by = CASE
        WHEN NULLIF(TRIM(COALESCE(obra.fotografico, '')), '') IS NOT NULL
          OR (
              COALESCE(fotografico.possui_plano_aberto, 0) = 0
              AND COALESCE(fotografico.possui_plano_concluido, 0) = 1
          )
        THEN COALESCE(item.done_by, 1) ELSE NULL
    END,
    item.done_at = CASE
        WHEN NULLIF(TRIM(COALESCE(obra.fotografico, '')), '') IS NOT NULL
          OR (
              COALESCE(fotografico.possui_plano_aberto, 0) = 0
              AND COALESCE(fotografico.possui_plano_concluido, 0) = 1
          )
        THEN COALESCE(item.done_at, NOW()) ELSE NULL
    END
WHERE checklist.module_key = 'projeto'
  AND item.item_key = 'fotografico';

-- Recalcula o status dos checklists de Projeto após a troca de fonte das
-- evidências. Os eventos futuros do plano e a edição de obra.fotografico
-- continuam mantendo o item sincronizado.
UPDATE checklist_operacional checklist
SET checklist.status = CASE WHEN EXISTS (
    SELECT 1
      FROM checklist_operacional_item item
     WHERE item.checklist_id = checklist.id
       AND item.required = 1
       AND item.done = 0
) THEN 'aberto' ELSE 'concluido' END
WHERE checklist.module_key = 'projeto';
