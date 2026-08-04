# Resumo consolidado de pendências

O produtor é `php FlowConnect/workers/pending_summary_worker.php --once`. Ele somente roda nas janelas de `FLOW_CONNECT_PENDING_SUMMARY_TIMES`, publica `pending.summary.ready` e usa a chave `pending-summary:{data}:{hora}:{colaborador}:v1`.

O evento está catalogado em `FlowConnect/config/events/pending_summary.php`. O catálogo define `severity=INFO`, `category=SUMMARY`, `template=pending_summary`, `recipient_strategy=summary_owner` e `delivery_mode=DM`. O `EventValidator` carrega esse catálogo e também valida o schema específico do payload; o `EventPlanner` carrega o mesmo arquivo para planejar a notification.

Para testar uma única pessoa, acrescente `--collaborator-id=21`. O filtro é aplicado depois da coleta dos providers e antes da publicação; nenhuma outra pessoa recebe evento nessa execução.

O modo é resolvido por `FLOW_CONNECT_PENDING_SUMMARY_MODE`, depois `FLOW_CONNECT_MODE`/`FLOW_CONNECT_OPERATIONAL_MODE`, com padrão `off`. Em `shadow`, o `event_worker` cria a notification, resolve o `summary_owner` e renderiza a prévia. Como a notification fica em `SHADOW`, o `delivery_worker` não a seleciona e Slack não é chamado.

## Shadow

1. Mantenha `upload_pending_summary_worker.php` e o timer antigo em execução.
2. Execute o novo worker por timer a cada cinco minutos; só a janela configurada publica eventos.
3. Processe os eventos com `event_worker` e compare uma janela com `php FlowConnect/scripts/inspect_pending_summary_shadow.php 2026-08-05T10:15`.
4. Verifique totais de Arquivos, destinatários, `delivery_mode=SHADOW`, ausência de tentativas de envio e repetição da mesma janela.

## Substituição e rollback

Após aprovação explícita, altere o modo para active e apenas então desative o timer antigo e habilite o novo. Para rollback, volte o modo para shadow e religue o timer antigo; os eventos já criados em shadow nunca serão enviados. Não remover o worker legado nesta etapa.

Os exemplos de `systemd/` não são instalados automaticamente. O timer dispara a cada cinco minutos para que a lista no `.env` seja a única fonte de horários.
