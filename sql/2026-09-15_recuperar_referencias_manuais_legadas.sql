-- Recupera conclusões de Referências feitas antes da migração para ALMA.
-- Os IDs foram extraídos do backup anterior à migração em 15/09/2026.
-- A conclusão histórica torna-se uma evidência legada de ALMA, sempre
-- automática: não restaura a possibilidade de marcação manual na interface.
UPDATE checklist_operacional_item
SET label = 'ALMA',
    required = 1,
    update_mode = 'AUTOMATICO',
    done = 1,
    done_by = COALESCE(done_by, 1),
    done_at = COALESCE(done_at, NOW())
WHERE checklist_id IN (1, 2, 230, 247, 248, 249, 263, 353)
  AND item_key = 'referencias_mood';

UPDATE checklist_operacional checklist
SET checklist.status = CASE WHEN EXISTS (
    SELECT 1
      FROM checklist_operacional_item item
     WHERE item.checklist_id = checklist.id
       AND item.required = 1
       AND item.done = 0
) THEN 'aberto' ELSE 'concluido' END
WHERE checklist.id IN (1, 2, 230, 247, 248, 249, 263, 353)
  AND checklist.module_key = 'projeto';
