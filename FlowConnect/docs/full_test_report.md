# Relatório completo de validação — Flow Connect / FlowReview

## Resultado

Recomendação: **aprovado somente para continuidade em shadow**. A bateria comprovou a outbox, o planejamento, os workers, destinatários, previews, idempotência e ausência de chamadas externas no ambiente local. Não há evidência suficiente para ativar `active`: os produtores reais de interface não foram submetidos, pois preservariam o Slack legado para pessoas reais, e o canal Slack ativo não fez parte desta bateria.

## Ambiente e configuração

- Data da bateria: 2026-07-31, aproximadamente 18:13–18:25 UTC.
- Projeto local: `C:\xampp\htdocs\ImproovWeb`.
- Commit base consultado: `ccc679b5` (a árvore continha alterações não commitadas desta implementação).
- Tabelas Flow Connect: as sete tabelas existem.
- Modos observados: `mention`, `angle`, `task`, `direction`, `sftp` e `sla` em `shadow`.
- Identidades: 25 registradas; 12 `ACTIVE` e 13 `UNRESOLVED`.
- Segredos, tokens e valores de `.env` não foram lidos nem impressos.

## Fotografia inicial

| Tabela | Linhas no início |
|---|---:|
| `flow_connect_events` | 2 |
| `flow_connect_notifications` | 2 |
| `flow_connect_deliveries` | 2 |
| `flow_connect_delivery_attempts` | 0 |
| `flow_connect_schedules` | 0 |
| `flow_connect_slack_identities` | 25 |
| `flow_connect_dead_letters` | 0 |

Não havia eventos `PENDING` antigos, claims vencidos nem attempts externas. Fixtures da suíte usaram a chave `it:flow-connect:{timestamp}:{pid}` e foram removidos ao fim de cada execução.

## Comandos executados

```text
php FlowConnect/scripts/health_check.php
php FlowConnect/tests/run.php
php FlowConnect/tests/integration/run.php
php FlowConnect/workers/event_worker.php --once --limit=100 --verbose
php FlowConnect/workers/delivery_worker.php --once --limit=100 --verbose
php FlowConnect/scripts/test_report.php --started-at=2026-07-31T18:13:30Z --event-family=review
curl.exe -k -s -o NUL -w "%{http_code}" https://improov/ImproovWeb/FlowReview/
```

O HTTP sem sessão respondeu `302` para autenticação; o navegador autenticado abriu `https://improov/ImproovWeb/FlowReview/` com título `Flow Review`, fila visível e zero erros de console.

## Matriz de casos

| Família / infraestrutura | Resultado | Evidência |
|---|---|---|
| Contratos, flags, templates e arquitetura | PASS | 79 assertions em `FlowConnect/tests/run.php` |
| Menção, comentário/resposta, auto-menção, repetição, identidade ausente, texto especial | PASS | suíte `mentions`; delivery lógica, preview, idempotência e dead-letter seguro |
| Ângulo escolhido, com ajustes e ajuste solicitado | PASS | suíte `angles`; três tipos semânticos e previews |
| Tarefa aprovada, ajuste, aprovada com ajustes e animação | PASS | suíte `tasks`; aprovação simples preservada como `HISTORY_ONLY` |
| Direção: fato e solicitação correlacionada | PASS | suíte `direction`; fato sem delivery e solicitação com `causation_event_uuid` |
| SFTP técnico seguro | PASS parcial | suíte `sftp`; categoria `TECHNICAL`, severidade `CRITICO`, destinatário `ADMIN` e payload sanitizado |
| SLA, limite, resolução, cancelamento, silêncio e claim expirado | PASS parcial | suíte `sla`; policies e schedules próprios; scheduler real gerou eventos em shadow |
| Event worker, repetição, claim expirado e evento inválido | PASS | suíte `workers`; evento inválido alcançou `DEAD` com dead-letter seguro |
| Delivery worker em shadow | PASS | execução manual: `claimed=0`; attempts permaneceram 0 |
| Identidade ACTIVE / UNRESOLVED / INACTIVE e fallback | PASS | suíte `shadow_mode`; lookup ativo, negativo e papéis configurados |
| Navegador FlowReview | PASS leitura | página real autenticada e sem erros; nenhuma ação mutável submetida |
| Comentário, resposta, ângulo, tarefa e direção pela UI | BLOCKED | em shadow, a ação acionaria o Slack legado para destinatários reais; não há sandbox autorizado |
| Falha SFTP real com rollback de negócio | BLOCKED | não existe seam de simulação segura já disponível; não foi alterada regra de produção |
| SLA real abaixo/acima com alteração de datas de negócio | BLOCKED | não havia registro de teste isolado; dados de negócio não foram modificados |
| `active`, Slack API, 429/timeout real e entrega externa | NOT APPLICABLE | vedados pela bateria em shadow |
| Leitura de flags por Apache após alteração dinâmica | BLOCKED | não existe endpoint seguro que exponha modo sem produzir efeito de negócio |

## Evidências persistentes de shadow

O scheduler encontrou tarefas reais em aprovação e produziu 10 eventos `review.aprovacao.sla_excedido` durante a janela observada. A evidência inclui os eventos `34–42` e `77`, notifications `64–72` e `133`, e 20 deliveries lógicas.

- Todas as notifications estão em `delivery_mode=SHADOW` e `status=READY`.
- Todas as deliveries estão em `PENDING`, com preview preenchido, `sent_at=NULL` e `attempt_count=0`.
- `flow_connect_delivery_attempts` permanece com 0 linhas.
- O event worker processou explicitamente os eventos `40`, `41` e `42`; o delivery worker reportou `claimed=0`.
- As consultas de idempotência não encontraram chaves repetidas na suíte; o schema mantém unicidade para evento e delivery.

## Bugs encontrados e corrigidos

1. `SHADOW` convertia eventos `HISTORY_ONLY` em deliveries lógicas. Corrigido em `EventPlanner`: aprovação simples e fato `review.tarefa.enviada_direcao` permanecem apenas históricos; eventos comunicáveis continuam com preview e delivery shadow.
2. `test_report.php` comparava timestamp UTC diretamente com `received_at` local do MySQL. Corrigido: ISO 8601 com fuso é normalizado para `America/Sao_Paulo` no filtro.
3. A regressão anterior de auto-menção foi mantida coberta: `mentioned_user` usa `payload.mencionado_id`, inclusive quando o autor é o próprio mencionado.
4. A primeira execução ampliada da suíte deixou um fixture atrás de eventos reais criados pelo scheduler, por assumir uma fila global vazia. A suíte foi corrigida para respeitar FIFO com lote amplo; a repetição passou sem duplicidade nem attempts.

## Riscos e pendências para active

- Os fluxos reais de UI não foram executados por não existir destinatário Slack sandbox; em shadow eles preservariam o legado e poderiam gerar notificações reais.
- O rollback de negócio após falha SFTP real não foi exercitado sem um seam local aprovado.
- Não houve chamada Slack, portanto autenticação, resposta da API, rate limit e retry externo só foram cobertos por contrato/policy, não por integração ativa.
- Os IDs de papéis de direção, gestores e técnicos continuam provisórios e precisam de validação operacional antes de `active`.
- A fonte única entre FlowReview e FlowReviewExt permanece pendente.

## Arquivos criados ou alterados nesta bateria

- Criados: `FlowConnect/tests/integration/` (suíte reutilizável), `FlowConnect/scripts/test_report.php`, este relatório.
- Alterados: `FlowConnect/application/EventPlanner.php`, `FlowConnect/infrastructure/NotificationRepository.php`, `FlowConnect/infrastructure/DeliveryRepository.php`, `FlowConnect/application/RecipientResolver.php`, `FlowConnect/infrastructure/SlackIdentityRepository.php`, `FlowConnect/scripts/inspect_shadow.php`, testes unitários e documentação relacionada.

## Próximo passo recomendado

Manter todas as famílias em `shadow`. Antes de considerar canário/`active`, preparar um destinatário Slack sandbox e uma fixture de negócio isolada para executar pela interface ao menos menção e ajuste de tarefa, validando em paralelo a mensagem legada e a entrega Flow Connect.

Mensagem de commit sugerida: `test(flow-connect): adiciona validação integrada do FlowReview em shadow`
