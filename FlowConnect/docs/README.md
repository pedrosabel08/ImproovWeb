# Flow Connect V1 — núcleo FlowReview

## Estado desta entrega

- Código do núcleo, produtores, workers, templates, scripts, testes e migration criado.
- Migration `001_flow_connect_core.sql` **não aplicada**.
- Todas as flags têm fallback `off`; nenhum `active` foi habilitado.
- Cron legado `FlowReview/sla_check_cron.php` permanece presente e operacional em `off`/`shadow`.
- Nenhum wrapper Slack foi removido.
- `FlowReviewExt` e os demais módulos não foram alterados.

## Fluxo

1. O produtor conclui a regra de negócio.
2. Se a família está `off`, não acessa a outbox e mantém o Slack legado.
3. Em `shadow` ou `active`, persiste um envelope estruturado e idempotente.
4. O event worker faz claim curto, planeja, renderiza o preview e cria notification/deliveries lógicas em `shadow` quando o evento prevê comunicação; eventos `HISTORY_ONLY` permanecem apenas históricos.
5. O delivery worker chama o Slack fora da transação de claim e registra cada tentativa, mas exclui deliveries cuja notification está em `SHADOW`.
6. O scheduler reconcilia tarefas em aprovação, confirma o estado e cria eventos de SLA idempotentes.

Em `active`, o produtor só ignora o envio legado correspondente quando a persistência da outbox retornou um ID válido. Falha de persistência usa o legado como fallback e evita perda silenciosa.

## Scheduler operacional temporal

O motor de ciclos operacionais fica separado dos workers de evento e entrega: ele confirma a origem, registra marcos idempotentes e inclui eventos na outbox. Nunca chama Slack nem o webhook de atrasos.

```powershell
php FlowConnect/workers/operational_scheduler_worker.php --once
php FlowConnect/workers/operational_scheduler_worker.php --daemon
```

Em `--daemon`, a rodada atual termina antes de respeitar `SIGTERM`/`SIGINT`; nenhuma nova rodada é iniciada e nenhum lock é mantido durante a espera. Sem trabalho, aguarda `FLOW_CONNECT_OPERATIONAL_SCHEDULER_IDLE_SECONDS` (padrão: 1). Falhas de conexão recebem backoff de até 30 segundos.

## Eventos e contratos

| Evento | Produtor real | Payload principal | Idempotência | Destinatário | Saída padrão |
|---|---|---|---|---|---|
| `review.mencao.criada` | comentário/resposta | IDs de comentário/resposta/menção, autor, mencionado e contexto | `review:mencao:{origem}:{id}:colaborador:{id}:v1` | `mentioned_user` | DM |
| `review.angulo.escolhido` | decisão P00 | função, imagem, responsável, revisor, status e decisão | `review:angulo:{histórico}:decisao:{decisão}:historico:{id}:v1` | `task_responsible` | DM informativa |
| `review.angulo.escolhido_com_ajustes` | decisão P00 | igual ao anterior + observação segura | mesma família por decisão | `task_responsible` | DM de ação |
| `review.angulo.ajuste_solicitado` | decisão P00 | igual ao anterior + observação segura | mesma família por decisão | `task_responsible` | DM de ação |
| `review.tarefa.aprovada` | revisão de imagem/animação | entidade, função, imagem, obra, revisor e status | `review:tarefa:{id}:historico:{id}:status:{status}:v1` | gestores; animação usa responsável | `HISTORY_ONLY` por padrão |
| `review.tarefa.ajuste_solicitado` | revisão de imagem/animação | contexto da tarefa e transição | mesma família | responsável; animação usa responsável | DM de ação |
| `review.tarefa.aprovada_com_ajustes` | revisão de imagem/animação | contexto da tarefa e transição | mesma família | responsável; animação usa responsável | DM de ação |
| `review.tarefa.enviada_direcao` | primeira aprovação operacional | histórico da direção e contexto | `review:tarefa:{id}:historico:{id}:enviada_direcao:v1` | gestores | somente histórico |
| `review.direcao.validacao_solicitada` | mesma transação, causada pelo evento anterior | histórico da direção e contexto | `review:direcao:{historico}:solicitada:v1` | `direction_group` | DM de ação |
| `review.sftp.envio_falhou` | após rollback da revisão | operação, tentativa e erro seguro | `review:sftp:{id}:operacao:{op}:tentativa:{n}:falhou:v1` | `technical_admins` | DM técnica crítica |
| `review.aprovacao.sla_excedido` | cron legado ou scheduler | horas, limite, janela e contexto | `review:sla:{id}:nivel:{n}:janela:{data}:v1` | gestores | DM atrasada |

`review.tarefa.reprovada` possui contrato e template reservados, mas não tem produtor: o código atual não possui um caminho funcional de reprovação e nenhum estado foi inventado.

## Relação de criação, cobrança, resolução e cancelamento

- Entrada em `Em aprovação` é reconciliada pelo scheduler como schedule de SLA.
- Schedule vencido cria `review.aprovacao.sla_excedido` com janela diária e repetição configurável.
- Qualquer evento `review.tarefa.*` ou `review.direcao.validacao_solicitada` resolve o schedule ainda aberto da entidade.
- Mudança da tarefa para fora de `Em aprovação` também encerra o schedule quando o scheduler confirmar o estado.
- `cancelled_at`, `resolved_at` e `silence_until` impedem novas cobranças sem apagar histórico anterior.

## Duplicidades ainda existentes fora do escopo

- `FlowReviewExt/salvar_decisao.php` ainda pode representar a mesma decisão de ângulo ou imagem.
- `FlowReviewExt/solicitar_refazer_angulos.php` ainda publica a solicitação de novos ângulos.
- `scripts/slack_overdue_daily.php` ainda pode se sobrepor ao SLA de aprovação, embora represente uma consulta temporal diferente.
- `notificacoes_slack.php` ainda mantém resumos legados por função.

Esses arquivos não foram alterados. A decisão de fonte única entre FlowReview e FlowReviewExt continua pendente.
