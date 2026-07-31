# Relatório de implementação

## Arquivos criados

- Raiz: `FlowConnect/bootstrap.php`, `FlowConnect/.env.example`.
- Configuração: `FlowConnect/config/flow_connect.php`, `FlowConnect/config/feature_flags.php`, `FlowConnect/config/events/flow_review.php`.
- Contratos: `FlowConnect/contracts/EventEnvelope.php`, `FlowConnect/contracts/EventValidator.php`.
- Aplicação: `FlowConnect/application/EventPublisher.php`, `FlowConnect/application/FlowReviewEventFactory.php`, `FlowConnect/application/EventPlanner.php`, `FlowConnect/application/RecipientResolver.php`, `FlowConnect/application/TemplateRenderer.php`, `FlowConnect/application/RetryPolicy.php`, `FlowConnect/application/SlaSchedulePolicy.php`.
- Infraestrutura: `FlowConnect/infrastructure/EventRepository.php`, `FlowConnect/infrastructure/NotificationRepository.php`, `FlowConnect/infrastructure/DeliveryRepository.php`, `FlowConnect/infrastructure/SlackIdentityRepository.php`, `FlowConnect/infrastructure/DeadLetterRepository.php`.
- Canais: `FlowConnect/channels/ChannelAdapter.php`, `FlowConnect/channels/SlackApiAdapter.php`.
- Templates: `FlowConnect/templates/slack/flow_review/_base.php`, `mention_created.php`, `angle_chosen.php`, `angle_chosen_with_adjustments.php`, `angle_adjustment_requested.php`, `task_approved.php`, `task_adjustment_requested.php`, `task_approved_with_adjustments.php`, `task_rejected.php`, `direction_validation_requested.php`, `sftp_failed.php`, `approval_sla_exceeded.php`.
- Workers: `FlowConnect/workers/_cli.php`, `FlowConnect/workers/event_worker.php`, `FlowConnect/workers/delivery_worker.php`, `FlowConnect/workers/scheduler_worker.php`.
- Scripts: `FlowConnect/scripts/sync_slack_identities.php`, `FlowConnect/scripts/inspect_shadow.php`, `FlowConnect/scripts/health_check.php`.
- Banco: `FlowConnect/migrations/001_flow_connect_core.sql`.
- Testes: `FlowConnect/tests/bootstrap.php`, `EventContractTest.php`, `ModeAndRecipientTest.php`, `TemplateRetrySlaTest.php`, `StaticArchitectureTest.php`, `run.php`, `integration_after_migration.sql`.
- Documentação: `FlowConnect/docs/README.md`, `operations.md`, `test_plan.md`, `implementation_report.md`.

## Arquivos alterados

- `FlowReview/atualizar_angulo.php`
- `FlowReview/mencao_slack_helper.php`
- `FlowReview/salvar_comentario.php`
- `FlowReview/responder_comentario.php`
- `FlowReview/revisarTarefa.php`
- `FlowReview/sla_check_cron.php`

## Decisões

- Transações existentes receberam a outbox dentro do mesmo commit.
- Rotas sem transação não ganharam uma transação nova.
- Falha SFTP publica o evento técnico somente depois do rollback.
- `off` não toca nas tabelas do Flow Connect.
- `active` ignora o legado apenas se o evento foi persistido.
- `shadow` resolve destinatário, renderiza preview e cria delivery lógica; somente o worker bloqueia a chamada externa.
- Aprovação simples começa como `HISTORY_ONLY`.
- Identidades Slack são sincronizadas e persistidas; delivery não usa `users.list`.
- IDs 21/2 e 21/9 ficam somente em configuração provisória e devem ser validados.
- Reprovação não foi conectada a produtor porque não existe caminho real.

## Riscos pendentes

- Migration e constraints ainda precisam de revisão do responsável pelo banco.
- Os IDs provisórios de direção, gestores e administradores técnicos precisam de validação humana.
- A fonte única entre FlowReview e FlowReviewExt continua indefinida.
- Shadow com banco e teste concorrente dependem da migration aprovada.
- O cron legado e o scheduler novo precisam rodar juntos em shadow antes de qualquer desligamento.

## Validação executada nesta etapa

- Sintaxe válida nos 51 arquivos PHP do escopo verificado.
- 79 assertions de contrato, modos, destinatários, templates, retry, SLA e arquitetura passaram.
- FlowReview abriu autenticado nas URLs `https://improov/ImproovWeb/FlowReview/` e `http://localhost:8066/ImproovWeb/FlowReview/`, com as flags em `off`.
- Health check somente leitura confirmou que as sete tabelas ainda não existem e que todas as famílias permanecem `off`.
- Nenhum token, webhook ou ID Slack foi incluído no novo núcleo.
- Os testes de banco em shadow/active e de concorrência real ficaram para depois da revisão e aplicação manual da migration.

## Mensagem de commit sugerida

`feat(flow-connect): adiciona núcleo e migra notificações do FlowReview em modo seguro`
