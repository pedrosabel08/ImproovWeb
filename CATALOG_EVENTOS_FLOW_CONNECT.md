# Catálogo de Eventos — futuro Flow Connect

**Data da varredura:** 31/07/2026  
**Escopo:** envios Slack encontrados no código do projeto `ImproovWeb`.  
**Regra desta etapa:** somente documentação; nenhum código de aplicação foi alterado.

## Critérios

- `ativo`: existe caminho de execução no produto ou em um worker/script usado pelo fluxo.
- `legado`: implementação ainda presente, mas com sinais de fluxo antigo, configuração provisória ou sobreposição.
- `sem chamada`: função capaz de enviar, porém sem chamada encontrada na varredura.
- `diagnóstico`: envio usado para testar conectividade ou autenticação, não para informar negócio.
- `precisa validação`: o código envia ou pode enviar, mas falta confirmar agendamento, produção, destinatário ou intenção funcional.
- Chamadas a `users.list` foram tratadas como resolução de destinatário, não como eventos de negócio.
- Os valores dos tokens e segredos não são reproduzidos neste documento. IDs de canal, nomes de variáveis e valores não secretos são listados quando encontrados.

## Catálogo de eventos

### Calendário

#### `calendario.entrega.proximos_7_dias`

| Campo                      | Registro                                                                                  |
| -------------------------- | ----------------------------------------------------------------------------------------- |
| Módulo                     | Calendário                                                                                |
| Arquivo / função chamadora | `calendar.php` — `enviarNotificacaoSlack()`; chamada no fluxo principal do arquivo        |
| Descrição funcional        | Informa os eventos de calendário dos próximos sete dias, agrupados em uma única mensagem. |
| Entidade principal         | Evento de calendário / entrega                                                            |
| Tipo                       | Resumo                                                                                    |
| Destinatário atual         | Destino configurado em `SLACK_WEBHOOK_URL`                                                |
| Estratégia sugerida        | Canal de operações ou grupo de calendário; configuração por ambiente, sem URL no código.  |
| Tipo de destino            | Canal                                                                                     |
| Método atual               | Webhook                                                                                   |
| Prioridade                 | Média                                                                                     |
| Repetição                  | Esperada conforme a execução do script; não há idempotência visível.                      |
| Escalonamento              | Não; somente se o resumo não for publicado.                                               |
| Agrupamento                | Já agrupado por janela de sete dias.                                                      |
| Risco de duplicidade       | Médio: depende de quantas vezes `calendar.php` é executado.                               |
| Status                     | Precisa validação                                                                         |
| Observações de migração    | Criar um evento de resumo com chave de janela/data e deduplicação por período.            |

### Contratos

#### `contratos.documento.status_atualizado`

| Campo                      | Registro                                                                                                             |
| -------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| Módulo                     | Contratos / ZapSign                                                                                                  |
| Arquivo / função chamadora | `Contratos/webhook.php` — `slack_send_webhook()`; chamada após inferir `enviado`, `visualizado` ou `assinado`        |
| Descrição funcional        | Avisa que o financeiro criou, que alguém visualizou ou que alguém assinou um contrato ou adendo.                     |
| Entidade principal         | Contrato ou adendo                                                                                                   |
| Tipo                       | Imediato                                                                                                             |
| Destinatário atual         | Webhook `SLACK_WEBHOOK_CONTRATOS_URL`                                                                                |
| Estratégia sugerida        | Canal/grupo de contratos, com roteamento por tipo de documento e status.                                             |
| Tipo de destino            | Canal                                                                                                                |
| Método atual               | Webhook                                                                                                              |
| Prioridade                 | Alta para `assinado`; média para `enviado` e `visualizado`.                                                          |
| Repetição                  | Pode ocorrer em cada evento recebido pelo webhook.                                                                   |
| Escalonamento              | Escalonar apenas falha de assinatura, webhook inválido ou evento sem associação.                                     |
| Agrupamento                | Não recomendado para assinatura; possível agrupar visualizações.                                                     |
| Risco de duplicidade       | Alto: o endpoint recebe eventos repetidos e o envio não tem chave de evento visível.                                 |
| Status                     | Ativo                                                                                                                |
| Observações de migração    | Usar `doc_token + evento + status` como chave idempotente. Separar contrato e adendo como atributos do mesmo evento. |

### Dashboard e fotografias legadas

#### `fotografico.registro.criado`

| Campo                      | Registro                                                                                                         |
| -------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| Módulo                     | Dashboard / Fotográfico legado                                                                                   |
| Arquivo / função chamadora | `Dashboard/add_fotografico_registro.php` — `send_simple_slack_dm()`; chamada após inserir `fotografico_registro` |
| Descrição funcional        | Avisa os finalizadores da obra que um novo registro fotográfico foi criado.                                      |
| Entidade principal         | Registro fotográfico / obra                                                                                      |
| Tipo                       | Imediato                                                                                                         |
| Destinatário atual         | Finalizadores (`funcao_id = 4`) identificados por `nome_slack`                                                   |
| Estratégia sugerida        | Destinatário derivado do papel da obra; fallback para grupo Fotográfico quando não houver usuário resolvido.     |
| Tipo de destino            | DM                                                                                                               |
| Método atual               | Slack API `chat.postMessage`                                                                                     |
| Prioridade                 | Média                                                                                                            |
| Repetição                  | Uma por colaborador, com deduplicação local por `nome_slack`/colaborador.                                        |
| Escalonamento              | Se não houver finalizador configurado, escalar para administrador do Fotográfico.                                |
| Agrupamento                | Possível agrupar vários registros da mesma obra em uma janela curta.                                             |
| Risco de duplicidade       | Alto: pode se sobrepor às notificações do módulo Fotográfico moderno.                                            |
| Status                     | Precisa validação                                                                                                |
| Observações de migração    | Confirmar se esse fluxo ainda é usado; migrar para `fotografico.registro.criado` com outbox.                     |

### Arquivos e FlowDrive

#### `arquivo.upload.status`

| Campo                      | Registro                                                                                                           |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| Módulo                     | FlowDrive                                                                                                          |
| Arquivo / função chamadora | `FlowDrive/upload.php` — `send_slack_token_message()`; chamada no fluxo de atualização da função/imagem            |
| Descrição funcional        | Envia ao colaborador o resultado do upload: sucesso ou falha, nome do arquivo e destino remoto.                    |
| Entidade principal         | Arquivo / upload                                                                                                   |
| Tipo                       | Imediato                                                                                                           |
| Destinatário atual         | Colaborador associado, resolvido por `nome_slack`; exige `FLOW_TOKEN`.                                             |
| Estratégia sugerida        | DM ao responsável; canal de operações somente para falhas persistentes.                                            |
| Tipo de destino            | DM                                                                                                                 |
| Método atual               | Slack API `chat.postMessage`                                                                                       |
| Prioridade                 | Alta para falha; média para sucesso.                                                                               |
| Repetição                  | Pode repetir em retries do upload.                                                                                 |
| Escalonamento              | Falha após retry deve escalar para suporte/operações.                                                              |
| Agrupamento                | Agrupar sucessos por lote; manter falhas individuais.                                                              |
| Risco de duplicidade       | Alto com `scripts/upload_worker.php`.                                                                              |
| Status                     | Ativo                                                                                                              |
| Observações de migração    | Unificar com `arquivo.upload.finalizado` e `arquivo.upload.falhou`; incluir `job_id`, tentativa e hash do arquivo. |

#### `arquivo.upload.webhook_legacy`

| Campo                      | Registro                                                                    |
| -------------------------- | --------------------------------------------------------------------------- |
| Módulo                     | FlowDrive                                                                   |
| Arquivo / função chamadora | `FlowDrive/upload.php` — `send_slack_webhook()`; nenhuma chamada encontrada |
| Descrição funcional        | Wrapper genérico para publicar texto em webhook Slack.                      |
| Entidade principal         | Arquivo / upload                                                            |
| Tipo                       | Técnico                                                                     |
| Destinatário atual         | `SLACK_WEBHOOK_URL`, se a função fosse chamada                              |
| Estratégia sugerida        | Remover ou substituir pelo transportador central do Flow Connect.           |
| Tipo de destino            | Canal                                                                       |
| Método atual               | Webhook                                                                     |
| Prioridade                 | Baixa                                                                       |
| Repetição                  | Indeterminada                                                               |
| Escalonamento              | Não                                                                         |
| Agrupamento                | Possível                                                                    |
| Risco de duplicidade       | Médio se for reativado sem coordenação com o worker.                        |
| Status                     | Sem chamada                                                                 |
| Observações de migração    | Não migrar como evento ativo antes de localizar um consumidor real.         |

#### `arquivo.upload.worker_status`

| Campo                      | Registro                                                                                                                   |
| -------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| Módulo                     | Worker de uploads                                                                                                          |
| Arquivo / função chamadora | `scripts/upload_worker.php` — `send_slack_notification_for_colaborador()`; chamadas em falhas de SFTP/API e após conclusão |
| Descrição funcional        | DM de arquivo enviado com sucesso ou falha, incluindo arquivo original e caminho remoto.                                   |
| Entidade principal         | Job de upload / arquivo                                                                                                    |
| Tipo                       | Imediato                                                                                                                   |
| Destinatário atual         | Colaborador do job, por `nome_slack`; não usa canal.                                                                       |
| Estratégia sugerida        | DM ao responsável; grupo técnico para falha definitiva.                                                                    |
| Tipo de destino            | DM                                                                                                                         |
| Método atual               | Slack API `chat.postMessage`; fallback HTTP em instalações sem cURL                                                        |
| Prioridade                 | Alta para falha; média para sucesso.                                                                                       |
| Repetição                  | Alta em retries e retomadas de jobs.                                                                                       |
| Escalonamento              | Após número máximo de tentativas ou falha de resolução do usuário.                                                         |
| Agrupamento                | Sucessos por job/lote; falhas separadas por arquivo.                                                                       |
| Risco de duplicidade       | Alto com `FlowDrive/upload.php`.                                                                                           |
| Status                     | Ativo                                                                                                                      |
| Observações de migração    | Centralizar em uma única transição de job; usar `job_id + tentativa + status` como chave.                                  |

#### `arquivo.upload.publicado`

| Campo                      | Registro                                                                                                       |
| -------------------------- | -------------------------------------------------------------------------------------------------------------- |
| Módulo                     | Upload / FlowReview                                                                                            |
| Arquivo / função chamadora | `uploadArquivos.php` — envio inline na região de “função já no servidor”                                       |
| Descrição funcional        | Avisa que a função da imagem foi refeita e o arquivo já está disponível no servidor.                           |
| Entidade principal         | Arquivo enviado / função da imagem                                                                             |
| Tipo                       | Imediato                                                                                                       |
| Destinatário atual         | Webhook `SLACK_WEBHOOK_POS_URL`.                                                                               |
| Estratégia sugerida        | Canal da etapa ou responsável do arquivo, conforme preferência da equipe.                                      |
| Tipo de destino            | Canal                                                                                                          |
| Método atual               | Webhook                                                                                                        |
| Prioridade                 | Média                                                                                                          |
| Repetição                  | Pode repetir em reprocessamento do upload.                                                                     |
| Escalonamento              | Não para sucesso; falha do webhook deve ser técnica.                                                           |
| Agrupamento                | Agrupar por lote de arquivos enviados.                                                                         |
| Risco de duplicidade       | Alto com `pos.imagem.finalizada` e `arquivo.upload.worker_status`.                                             |
| Status                     | Ativo                                                                                                          |
| Observações de migração    | Definir se representa `upload.finalizado` ou `função.refeita`; não manter os dois significados no mesmo canal. |

#### `arquivo.upload.refeito`

| Campo                      | Registro                                                                                        |
| -------------------------- | ----------------------------------------------------------------------------------------------- |
| Módulo                     | Upload / FlowReview                                                                             |
| Arquivo / função chamadora | `uploadArquivos.php` — envio inline no bloco “função refeita”                                   |
| Descrição funcional        | Avisa que o colaborador reenviou arquivos após ajuste ou enquanto a função estava em aprovação. |
| Entidade principal         | Reenvio de arquivo / ajuste de tarefa                                                           |
| Tipo                       | Imediato                                                                                        |
| Destinatário atual         | Webhook `SLACK_WEBHOOK_POS_URL`.                                                                |
| Estratégia sugerida        | Responsáveis da etapa; canal somente quando o reenvio muda a fila de revisão.                   |
| Tipo de destino            | Canal                                                                                           |
| Método atual               | Webhook                                                                                         |
| Prioridade                 | Alta                                                                                            |
| Repetição                  | Pode ocorrer a cada reenvio.                                                                    |
| Escalonamento              | Se o reenvio não atualizar a tarefa ou falhar no servidor.                                      |
| Agrupamento                | Agrupar reenvios do mesmo lote, mantendo a última versão.                                       |
| Risco de duplicidade       | Alto com `arquivo.upload.status`, `arquivo.upload.worker_status` e `pos.imagem.finalizada`.     |
| Status                     | Ativo                                                                                           |
| Observações de migração    | Representar como transição de revisão/arquivo, com `versao`, `motivo` e `tentativa`.            |

### FlowReview

#### `review.angulo.decisao_registrada`

| Campo                      | Registro                                                                                                   |
| -------------------------- | ---------------------------------------------------------------------------------------------------------- |
| Módulo                     | FlowReview                                                                                                 |
| Arquivo / função chamadora | `FlowReview/atualizar_angulo.php` — `slack_post_message()`; chamada após persistir a decisão do ângulo     |
| Descrição funcional        | Informa ângulo escolhido, escolhido com ajustes ou ajustes solicitados; adiciona observação quando houver. |
| Entidade principal         | Ângulo / imagem P00                                                                                        |
| Tipo                       | Imediato                                                                                                   |
| Destinatário atual         | Colaborador relacionado ao `colaborador_id`.                                                               |
| Estratégia sugerida        | Responsável da tarefa; canal somente para decisões que exigem visibilidade coletiva.                       |
| Tipo de destino            | DM                                                                                                         |
| Método atual               | Slack API                                                                                                  |
| Prioridade                 | Alta para ajustes solicitados; média para escolha.                                                         |
| Repetição                  | Pode repetir em novas decisões da mesma versão.                                                            |
| Escalonamento              | Ajustes sem resposta dentro do SLA.                                                                        |
| Agrupamento                | Não agrupar decisões de ângulo diferentes.                                                                 |
| Risco de duplicidade       | Alto com `FlowReviewExt/salvar_decisao.php`, que também envia decisões de ângulo.                          |
| Status                     | Ativo                                                                                                      |
| Observações de migração    | Definir um único dono do evento de decisão de ângulo.                                                      |

#### `review.mencao.criada`

| Campo                      | Registro                                                                                                                        |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| Módulo                     | FlowReview                                                                                                                      |
| Arquivo / função chamadora | `FlowReview/mencao_slack_helper.php` — `enviarSlackMencoes()`; chamado por `salvar_comentario.php` e `responder_comentario.php` |
| Descrição funcional        | Avisa cada colaborador mencionado em comentário ou resposta, com remetente, função, imagem, obra e link.                        |
| Entidade principal         | Menção em comentário                                                                                                            |
| Tipo                       | Imediato                                                                                                                        |
| Destinatário atual         | Cada colaborador mencionado, exceto duplicados e, conforme contexto, o remetente.                                               |
| Estratégia sugerida        | DM individual; usar preferências de notificação e supressão por autor.                                                          |
| Tipo de destino            | DM                                                                                                                              |
| Método atual               | Slack API                                                                                                                       |
| Prioridade                 | Média                                                                                                                           |
| Repetição                  | Pode repetir em edição/resposta se a menção for recriada.                                                                       |
| Escalonamento              | Não; apenas registrar usuário não resolvido.                                                                                    |
| Agrupamento                | Agrupar várias menções do mesmo comentário em uma mensagem.                                                                     |
| Risco de duplicidade       | Médio entre salvar comentário e responder comentário; deduplicação atual é apenas por requisição.                               |
| Status                     | Ativo                                                                                                                           |
| Observações de migração    | Persistir `comentario_id/resposta_id + mencionado_id` como chave de entrega.                                                    |

#### `review.tarefa.revisao_concluida`

| Campo                      | Registro                                                                                                     |
| -------------------------- | ------------------------------------------------------------------------------------------------------------ |
| Módulo                     | FlowReview                                                                                                   |
| Arquivo / função chamadora | `FlowReview/revisarTarefa.php` — `enviarNotificacaoSlack()`; chamada no fluxo final para os alvos de revisão |
| Descrição funcional        | Informa que uma função/imagem foi revisada, aprovada, ajustada ou aprovada com ajustes.                      |
| Entidade principal         | Tarefa de revisão / função da imagem                                                                         |
| Tipo                       | Imediato                                                                                                     |
| Destinatário atual         | Lista fixa de nomes: Pedro Sabel e Andre L. de Souza, resolvidos via `users.list`.                           |
| Estratégia sugerida        | Grupo de responsáveis da etapa, com regras por função; evitar lista fixa no código.                          |
| Tipo de destino            | Grupo                                                                                                        |
| Método atual               | Slack API                                                                                                    |
| Prioridade                 | Alta para ajuste; média para aprovação.                                                                      |
| Repetição                  | Pode repetir em reprocessamento ou resolução de conflito SFTP.                                               |
| Escalonamento              | Ajuste sem tratamento e falha operacional devem escalar; aprovação simples não.                              |
| Agrupamento                | Agrupar aprovações de um lote/imagem; não agrupar ajustes críticos.                                          |
| Risco de duplicidade       | Alto com notificações internas e `FlowReviewExt/salvar_decisao.php`.                                         |
| Status                     | Ativo                                                                                                        |
| Observações de migração    | Separar `aprovacao_registrada`, `ajuste_solicitado` e `aprovacao_com_ajustes`; eliminar destinatários fixos. |

#### `review.animacao.revisao_concluida`

| Campo                      | Registro                                                                                 |
| -------------------------- | ---------------------------------------------------------------------------------------- |
| Módulo                     | FlowReview / Animação                                                                    |
| Arquivo / função chamadora | `FlowReview/revisarTarefa.php` — `enviarNotificacaoSlack()`; ramo de revisão de animação |
| Descrição funcional        | Avisa o colaborador da animação sobre a revisão e o status resultante.                   |
| Entidade principal         | Função de animação                                                                       |
| Tipo                       | Imediato                                                                                 |
| Destinatário atual         | `nome_slack` do colaborador da animação.                                                 |
| Estratégia sugerida        | Responsável atual da função.                                                             |
| Tipo de destino            | DM                                                                                       |
| Método atual               | Slack API                                                                                |
| Prioridade                 | Alta para ajuste; média nos demais status.                                               |
| Repetição                  | Uma por alteração de status; pode repetir em retry da requisição.                        |
| Escalonamento              | Ajustes não tratados.                                                                    |
| Agrupamento                | Por lote de animação, se houver várias tarefas da mesma revisão.                         |
| Risco de duplicidade       | Médio com a mensagem genérica de revisão do mesmo arquivo.                               |
| Status                     | Ativo                                                                                    |
| Observações de migração    | Reusar o mesmo evento de revisão com `tipo_entidade = animacao`.                         |

#### `review.direcao.validacao_pendente`

| Campo                      | Registro                                                                                                     |
| -------------------------- | ------------------------------------------------------------------------------------------------------------ |
| Módulo                     | FlowReview                                                                                                   |
| Arquivo / função chamadora | `FlowReview/revisarTarefa.php` — `enviarNotificacaoSlack()`; ramo de aprovação que cria histórico da direção |
| Descrição funcional        | Informa que uma função/imagem aguarda validação da direção.                                                  |
| Entidade principal         | Aprovação de direção                                                                                         |
| Tipo                       | Imediato                                                                                                     |
| Destinatário atual         | Usuários associados aos colaboradores 21 e 2.                                                                |
| Estratégia sugerida        | Grupo de direção configurável por papel.                                                                     |
| Tipo de destino            | Grupo                                                                                                        |
| Método atual               | Slack API                                                                                                    |
| Prioridade                 | Alta                                                                                                         |
| Repetição                  | Pode repetir se o histórico for criado novamente.                                                            |
| Escalonamento              | Escalonar se permanecer pendente além do SLA.                                                                |
| Agrupamento                | Agrupar por obra/função durante uma janela curta.                                                            |
| Risco de duplicidade       | Médio com cron de SLA.                                                                                       |
| Status                     | Ativo                                                                                                        |
| Observações de migração    | Substituir IDs fixos por uma regra de papel e registrar `historico_direcao_id`.                              |

#### `review.sftp.envio_falhou`

| Campo                      | Registro                                                                                  |
| -------------------------- | ----------------------------------------------------------------------------------------- |
| Módulo                     | FlowReview                                                                                |
| Arquivo / função chamadora | `FlowReview/revisarTarefa.php` — `enviarNotificacaoSlack()`; ramo de falha de envio SFTP  |
| Descrição funcional        | Avisa que o status não foi alterado porque o envio SFTP falhou.                           |
| Entidade principal         | Tarefa de revisão / transferência SFTP                                                    |
| Tipo                       | Técnico                                                                                   |
| Destinatário atual         | Pedro Sabel e Andre L. de Souza.                                                          |
| Estratégia sugerida        | Grupo técnico/administrador do FlowReview, com contexto de erro e retry.                  |
| Tipo de destino            | Grupo                                                                                     |
| Método atual               | Slack API                                                                                 |
| Prioridade                 | Crítica                                                                                   |
| Repetição                  | Pode repetir em cada tentativa de envio.                                                  |
| Escalonamento              | Sim, imediatamente após falha definitiva.                                                 |
| Agrupamento                | Agrupar falhas iguais por job/servidor; manter primeira e última ocorrência.              |
| Risco de duplicidade       | Alto em retries e duplicidade de execução da rota.                                        |
| Status                     | Ativo                                                                                     |
| Observações de migração    | Criar evento técnico separado do evento de negócio; incluir erro, tentativa e correlação. |

#### `review.sla.excedido`

| Campo                      | Registro                                                                                             |
| -------------------------- | ---------------------------------------------------------------------------------------------------- |
| Módulo                     | FlowReview / cron de SLA                                                                             |
| Arquivo / função chamadora | `FlowReview/sla_check_cron.php` — `enviarSlack()`; resolução com `resolverSlackUserId()`             |
| Descrição funcional        | Avisa que uma tarefa está há mais horas em aprovação do que o limite configurado.                    |
| Entidade principal         | Tarefa de aprovação                                                                                  |
| Tipo                       | Temporal                                                                                             |
| Destinatário atual         | Consulta SQL filtra `idusuario IN (1)`; comentário cita mais usuários, portanto precisa confirmação. |
| Estratégia sugerida        | Responsável da tarefa + grupo de liderança após escalonamento.                                       |
| Tipo de destino            | Grupo                                                                                                |
| Método atual               | Slack API                                                                                            |
| Prioridade                 | Alta                                                                                                 |
| Repetição                  | Cron pode reenviar a cada execução; há tabela de registro, mas a idempotência deve ser confirmada.   |
| Escalonamento              | Sim: responsável, liderança e administrador em níveis de atraso.                                     |
| Agrupamento                | Agrupar tarefas em breach por responsável/obra.                                                      |
| Risco de duplicidade       | Médio com `scripts/slack_overdue_daily.php`.                                                         |
| Status                     | Precisa validação                                                                                    |
| Observações de migração    | Migrar para regras de SLA do Flow Connect, com níveis, janela e chave diária.                        |

### FlowReviewExt

#### `review.decisao.registrada`

| Campo                      | Registro                                                                                             |
| -------------------------- | ---------------------------------------------------------------------------------------------------- |
| Módulo                     | FlowReviewExt                                                                                        |
| Arquivo / função chamadora | `FlowReviewExt/salvar_decisao.php`; envio direto no fluxo principal, sem função de envio nomeada     |
| Descrição funcional        | Envia ao finalizador um resumo da decisão registrada, imagem, data da versão, responsável e horário. |
| Entidade principal         | Decisão de entrega / imagem                                                                          |
| Tipo                       | Imediato                                                                                             |
| Destinatário atual         | Finalizador (`funcao_id = 4`) associado à imagem.                                                    |
| Estratégia sugerida        | Responsável efetivo da entrega; fallback para grupo de revisão.                                      |
| Tipo de destino            | DM                                                                                                   |
| Método atual               | Slack API                                                                                            |
| Prioridade                 | Alta                                                                                                 |
| Repetição                  | Pode repetir junto com reenvio de decisão ou retry HTTP.                                             |
| Escalonamento              | Decisão sem finalizador ou sem usuário Slack deve ser encaminhada ao administrador.                  |
| Agrupamento                | Não agrupar decisões individuais; agrupar apenas resumo de lote.                                     |
| Risco de duplicidade       | Alto com `review.angulo.decisao_registrada` e notificações internas.                                 |
| Status                     | Ativo                                                                                                |
| Observações de migração    | Separar evento de persistência da decisão dos eventos específicos de ângulo/canal.                   |

#### `review.angulo.decisao_publicada`

| Campo                      | Registro                                                                                                       |
| -------------------------- | -------------------------------------------------------------------------------------------------------------- |
| Módulo                     | FlowReviewExt                                                                                                  |
| Arquivo / função chamadora | `FlowReviewExt/salvar_decisao.php`; ramo `angulo_id > 0`                                                       |
| Descrição funcional        | Publica no canal a escolha do ângulo com imagem, responsável e link; também envia DM adicional ao finalizador. |
| Entidade principal         | Decisão de ângulo                                                                                              |
| Tipo                       | Imediato                                                                                                       |
| Destinatário atual         | Canal fixo `C09V1SES7B2` e finalizador.                                                                        |
| Estratégia sugerida        | Canal/grupo da etapa de ângulo; DM somente para responsável direto.                                            |
| Tipo de destino            | Canal e DM                                                                                                     |
| Método atual               | Slack API                                                                                                      |
| Prioridade                 | Alta                                                                                                           |
| Repetição                  | Alta: o arquivo já envia uma DM de decisão antes deste ramo.                                                   |
| Escalonamento              | Ajustes ou escolha sem upload subsequente.                                                                     |
| Agrupamento                | Canal pode receber resumo; DM deve ser individual.                                                             |
| Risco de duplicidade       | Crítico: DM preliminar + DM de ângulo + `atualizar_angulo.php`.                                                |
| Status                     | Precisa validação                                                                                              |
| Observações de migração    | Consolidar em um único evento `review.angulo.decisao_registrada`, com entregas separadas por destino.          |

#### `review.imagem.decisao_publicada`

| Campo                      | Registro                                                                          |
| -------------------------- | --------------------------------------------------------------------------------- |
| Módulo                     | FlowReviewExt                                                                     |
| Arquivo / função chamadora | `FlowReviewExt/salvar_decisao.php`; ramo sem `angulo_id`                          |
| Descrição funcional        | Publica decisão normal de imagem no canal, sem DM adicional de decisão de imagem. |
| Entidade principal         | Decisão de imagem                                                                 |
| Tipo                       | Imediato                                                                          |
| Destinatário atual         | Canal fixo `C09V1SES7B2`.                                                         |
| Estratégia sugerida        | Canal da etapa ou grupo dos responsáveis.                                         |
| Tipo de destino            | Canal                                                                             |
| Método atual               | Slack API                                                                         |
| Prioridade                 | Média/alta conforme decisão.                                                      |
| Repetição                  | Pode repetir em retries.                                                          |
| Escalonamento              | Somente decisão com ajuste pendente.                                              |
| Agrupamento                | Sim, por obra ou lote.                                                            |
| Risco de duplicidade       | Alto com `review.tarefa.revisao_concluida`.                                       |
| Status                     | Precisa validação                                                                 |
| Observações de migração    | Definir se FlowReview ou FlowReviewExt será a fonte única das decisões.           |

#### `review.angulo.refazer_solicitado`

| Campo                      | Registro                                                                         |
| -------------------------- | -------------------------------------------------------------------------------- |
| Módulo                     | FlowReviewExt                                                                    |
| Arquivo / função chamadora | `FlowReviewExt/solicitar_refazer_angulos.php`; envio direto no fluxo principal   |
| Descrição funcional        | Avisa o canal que foram solicitados novos ângulos, com observação e solicitante. |
| Entidade principal         | Solicitação de refazer ângulos                                                   |
| Tipo                       | Imediato                                                                         |
| Destinatário atual         | Canal fixo `C09V1SES7B2`.                                                        |
| Estratégia sugerida        | Grupo da tarefa de ângulo e responsável atual.                                   |
| Tipo de destino            | Canal                                                                            |
| Método atual               | Slack API                                                                        |
| Prioridade                 | Alta                                                                             |
| Repetição                  | Pode repetir em nova solicitação ou retry.                                       |
| Escalonamento              | Se não houver resposta dentro do SLA.                                            |
| Agrupamento                | Agrupar solicitações da mesma imagem; não ocultar a observação.                  |
| Risco de duplicidade       | Médio com mensagens de ajuste de `atualizar_angulo.php`.                         |
| Status                     | Ativo                                                                            |
| Observações de migração    | Ligar a solicitação à tarefa, versão e evento de cobrança.                       |

### Fotográfico

#### `fotografico.plano.notificacao`

| Campo                      | Registro                                                                                                                                                                                                               |
| -------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Módulo                     | Fotográfico                                                                                                                                                                                                            |
| Arquivo / função chamadora | `Fotografico/fotografico_service.php` — `fotografico_notificar_colaborador()` enfileira; `fotografico_enviar_notificacoes_pendentes()` envia via `Fotografico/fotografico_slack.php` — `fotografico_slack_enviar_dm()` |
| Descrição funcional        | Notifica publicação, atribuição, conferência, retorno ou mudança operacional do plano fotográfico.                                                                                                                     |
| Entidade principal         | Plano fotográfico / execução / conferência                                                                                                                                                                             |
| Tipo                       | Imediato                                                                                                                                                                                                               |
| Destinatário atual         | Responsável do evento e acompanhamento fixo `FOTOGRAFICO_RESPONSAVEL_ACOMPANHAMENTO_ID = 21`.                                                                                                                          |
| Estratégia sugerida        | Papéis do plano, com grupo de acompanhamento opcional.                                                                                                                                                                 |
| Tipo de destino            | DM                                                                                                                                                                                                                     |
| Método atual               | Slack API                                                                                                                                                                                                              |
| Prioridade                 | Alta para retorno/pendência; média para atribuição e conferência.                                                                                                                                                      |
| Repetição                  | Deduplicação local por chave de plano, evento e destinatário; precisa persistir fora da requisição.                                                                                                                    |
| Escalonamento              | Sim para pendência não resolvida.                                                                                                                                                                                      |
| Agrupamento                | Agrupar eventos do mesmo plano em janela curta; não agrupar mudança crítica.                                                                                                                                           |
| Risco de duplicidade       | Médio: fila em memória não protege contra duas requisições/processos.                                                                                                                                                  |
| Status                     | Ativo                                                                                                                                                                                                                  |
| Observações de migração    | É o melhor candidato inicial para outbox transacional e catálogo de templates.                                                                                                                                         |

#### `fotografico.pendencia.cobranca`

| Campo                      | Registro                                                                                                                      |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| Módulo                     | Fotográfico / cron                                                                                                            |
| Arquivo / função chamadora | `scripts/fotografico_pendencias_cron.php` — `foto_cobranca_enviar_dm()`                                                       |
| Descrição funcional        | Cobra pendência aberta após o horário de cobrança, informando obra, plano, tipo, descrição, responsável, tempo aberto e link. |
| Entidade principal         | Pendência fotográfica                                                                                                         |
| Tipo                       | Temporal                                                                                                                      |
| Destinatário atual         | Responsável pela resolução, responsável pela cobrança e acompanhamento fixo.                                                  |
| Estratégia sugerida        | Responsável primeiro; cobrança/gestão em escalonamentos posteriores.                                                          |
| Tipo de destino            | DM                                                                                                                            |
| Método atual               | Slack API                                                                                                                     |
| Prioridade                 | Alta                                                                                                                          |
| Repetição                  | Esperada; tabela `fotografico_pendencia_cobranca_envio` reserva a entrega.                                                    |
| Escalonamento              | Sim, por idade da pendência e número de cobranças.                                                                            |
| Agrupamento                | Agrupar várias pendências do mesmo responsável/obra.                                                                          |
| Risco de duplicidade       | Médio; depende da reserva e da concorrência entre cronos.                                                                     |
| Status                     | Ativo                                                                                                                         |
| Observações de migração    | Modelar como sequência de `criada -> cobrança -> resolução/cancelamento`, com política de silêncio.                           |

### Notificações gerais

#### `notificacao.interna.visualizada`

| Campo                      | Registro                                                                                             |
| -------------------------- | ---------------------------------------------------------------------------------------------------- |
| Módulo                     | Notificações gerais                                                                                  |
| Arquivo / função chamadora | `notificacao_modulo_status.php` — `slack_send_dm()`; chamado quando a atualização afeta alguma linha |
| Descrição funcional        | Avisa que uma pessoa viu ou confirmou uma notificação interna.                                       |
| Entidade principal         | Notificação interna                                                                                  |
| Tipo                       | Imediato                                                                                             |
| Destinatário atual         | Pedro Sabel e Andre L. de Souza.                                                                     |
| Estratégia sugerida        | Grupo de gestores interessados no tipo de notificação; preferências configuráveis.                   |
| Tipo de destino            | Grupo                                                                                                |
| Método atual               | Slack API                                                                                            |
| Prioridade                 | Média                                                                                                |
| Repetição                  | Evitada por `affected_rows`, mas depende do comportamento do banco.                                  |
| Escalonamento              | Não                                                                                                  |
| Agrupamento                | Sim, por usuário e janela de confirmação.                                                            |
| Risco de duplicidade       | Médio em múltiplas ações sobre a mesma notificação.                                                  |
| Status                     | Ativo                                                                                                |
| Observações de migração    | Evitar evento Slack para cada leitura; preferir resumo ou somente confirmação relevante.             |

#### `postagem.pendente.resumo`

| Campo                      | Registro                                                                                           |
| -------------------------- | -------------------------------------------------------------------------------------------------- |
| Módulo                     | Notificações de postagem                                                                           |
| Arquivo / função chamadora | `notificacoes/enviarSlackPendentes.php`; fluxo principal do endpoint                               |
| Descrição funcional        | Resume até 20 imagens pendentes para postagem/entrega e marca os registros como notificados.       |
| Entidade principal         | Pendência de postagem                                                                              |
| Tipo                       | Resumo                                                                                             |
| Destinatário atual         | Webhook `SLACK_WEBHOOK_URL`; fallback para `SLACK_CHANNEL`.                                        |
| Estratégia sugerida        | Canal de postagem/operação, com resumo por execução.                                               |
| Tipo de destino            | Canal                                                                                              |
| Método atual               | Webhook ou Slack API                                                                               |
| Prioridade                 | Média                                                                                              |
| Repetição                  | Uma por execução; marca status, mas precisa chave de resumo.                                       |
| Escalonamento              | Se o volume permanecer pendente por mais de uma janela.                                            |
| Agrupamento                | Já agrupado; manter limite e paginação.                                                            |
| Risco de duplicidade       | Alto com `notificacoes_slack.php`.                                                                 |
| Status                     | Precisa validação                                                                                  |
| Observações de migração    | Transformar em `postagem.pendente.resumo` idempotente, sem misturar envio com alteração de status. |

#### `review.pendencia.resumo_legado`

| Campo                      | Registro                                                                                                         |
| -------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| Módulo                     | Notificações Slack legadas                                                                                       |
| Arquivo / função chamadora | `notificacoes_slack.php` — `enviarMensagemSlack()`; loop de consultas por função                                 |
| Descrição funcional        | Avisa canais de Modelagem, Composição, Finalização, Pós-produção e Planta Humanizada sobre tarefas em aprovação. |
| Entidade principal         | Tarefa de revisão pendente                                                                                       |
| Tipo                       | Resumo                                                                                                           |
| Destinatário atual         | Canais hardcoded por função.                                                                                     |
| Estratégia sugerida        | Substituir por resumo do FlowReview com roteamento configurável.                                                 |
| Tipo de destino            | Canal                                                                                                            |
| Método atual               | Slack API                                                                                                        |
| Prioridade                 | Média                                                                                                            |
| Repetição                  | Alta; cada execução consulta novamente o banco e não registra entrega.                                           |
| Escalonamento              | Não implementado.                                                                                                |
| Agrupamento                | Já agrupa quando há mais de duas tarefas.                                                                        |
| Risco de duplicidade       | Crítico com SLA e pendências de postagem.                                                                        |
| Status                     | Legado                                                                                                           |
| Observações de migração    | Desativar somente após comparar volume e destinatários com os novos eventos de revisão.                          |

### Pós-produção

#### `pos.imagem.finalizada`

| Campo                      | Registro                                                                                                        |
| -------------------------- | --------------------------------------------------------------------------------------------------------------- |
| Módulo                     | Pós-produção                                                                                                    |
| Arquivo / função chamadora | `Pos-Producao/inserir_pos_producao.php` e `Pos-Producao/toggle_status_pos.php`; fluxo principal de cada arquivo |
| Descrição funcional        | Avisa que a imagem foi feita na pós-produção.                                                                   |
| Entidade principal         | Imagem / etapa pós-produção                                                                                     |
| Tipo                       | Imediato                                                                                                        |
| Destinatário atual         | Webhook `SLACK_WEBHOOK_POS_URL`.                                                                                |
| Estratégia sugerida        | Canal de pós-produção; opcionalmente DM do responsável.                                                         |
| Tipo de destino            | Canal                                                                                                           |
| Método atual               | Webhook                                                                                                         |
| Prioridade                 | Média                                                                                                           |
| Repetição                  | Alta: existem dois endpoints com o mesmo texto e gatilho semelhante.                                            |
| Escalonamento              | Não                                                                                                             |
| Agrupamento                | Agrupar conclusões por lote/obra.                                                                               |
| Risco de duplicidade       | Crítico entre os dois arquivos e com `uploadArquivos.php`.                                                      |
| Status                     | Precisa validação                                                                                               |
| Observações de migração    | Definir uma única transição de status como fonte do evento.                                                     |

### Pré-alteração

#### `pre_alteracao.planejamento_liberado`

| Campo                      | Registro                                                                                             |
| -------------------------- | ---------------------------------------------------------------------------------------------------- |
| Módulo                     | Pré-alteração                                                                                        |
| Arquivo / função chamadora | `PreAlteracao/update_complexidade.php` — `verificarReadyForPlanning()` chama `notificarPedroSlack()` |
| Descrição funcional        | Informa que todas as imagens da obra foram analisadas e estão prontas para planejamento.             |
| Entidade principal         | Obra / planejamento                                                                                  |
| Tipo                       | Imediato                                                                                             |
| Destinatário atual         | Usuário cujo colaborador contém `Sabel` no nome.                                                     |
| Estratégia sugerida        | Papel de planejamento, não pessoa por nome parcial.                                                  |
| Tipo de destino            | Administrador                                                                                        |
| Método atual               | Slack API                                                                                            |
| Prioridade                 | Alta                                                                                                 |
| Repetição                  | Pode repetir quando o endpoint recalcula o estado.                                                   |
| Escalonamento              | Sim se planejamento não iniciar.                                                                     |
| Agrupamento                | Não necessário por obra; possível resumo diário.                                                     |
| Risco de duplicidade       | Médio por reexecução da verificação.                                                                 |
| Status                     | Ativo                                                                                                |
| Observações de migração    | Criar transição idempotente `ready_for_planning` por obra e versão de análise.                       |

### Scripts temporais de atraso

#### `review.tarefa.atrasada_resumo`

| Campo                      | Registro                                                                                                     |
| -------------------------- | ------------------------------------------------------------------------------------------------------------ |
| Módulo                     | SLA / tarefas em andamento                                                                                   |
| Arquivo / função chamadora | `scripts/slack_overdue_daily.php` — `enviarMensagemSlack()`; loop principal após `quebrarMensagensSlack()`   |
| Descrição funcional        | Resumo diário de tarefas em andamento com prazo vencido, função, responsável, obra, prazos e dias de atraso. |
| Entidade principal         | Tarefa de função/imagem                                                                                      |
| Tipo                       | Temporal / resumo                                                                                            |
| Destinatário atual         | `SLACK_CHANNEL`.                                                                                             |
| Estratégia sugerida        | Canal de operação com agrupamento por responsável e níveis de atraso.                                        |
| Tipo de destino            | Canal                                                                                                        |
| Método atual               | Slack API                                                                                                    |
| Prioridade                 | Alta                                                                                                         |
| Repetição                  | Diária; registra em `sla_notificacoes_enviadas`.                                                             |
| Escalonamento              | Sim conforme dias de atraso.                                                                                 |
| Agrupamento                | Já agrupa e divide mensagens por tamanho.                                                                    |
| Risco de duplicidade       | Médio com `review.sla.excedido`; pode ser o mesmo problema observado em fases diferentes.                    |
| Status                     | Precisa validação                                                                                            |
| Observações de migração    | Unificar com motor de SLA e diferenciar “em aprovação” de “em andamento atrasado”.                           |

### SIRE

#### `sire.importacao_referencias.falhou`

| Campo                      | Registro                                                                                                |
| -------------------------- | ------------------------------------------------------------------------------------------------------- |
| Módulo                     | SIRE                                                                                                    |
| Arquivo / função chamadora | `SIRE/importar_referencias.php` — `sire_notify_slack_user()`; chamadas em falha de query e conexão SFTP |
| Descrição funcional        | Avisa que a importação de referências falhou e inclui o erro técnico.                                   |
| Entidade principal         | Job de importação                                                                                       |
| Tipo                       | Técnico                                                                                                 |
| Destinatário atual         | Usuário de banco `idusuario = 1`, via `nome_slack`.                                                     |
| Estratégia sugerida        | Grupo técnico/administrador do SIRE, com escalonamento por severidade.                                  |
| Tipo de destino            | Administrador                                                                                           |
| Método atual               | Slack API                                                                                               |
| Prioridade                 | Crítica                                                                                                 |
| Repetição                  | Uma por etapa de falha; pode repetir em retries do job.                                                 |
| Escalonamento              | Sim                                                                                                     |
| Agrupamento                | Agrupar falhas do mesmo job, mas não esconder a primeira falha.                                         |
| Risco de duplicidade       | Médio em retries.                                                                                       |
| Status                     | Ativo                                                                                                   |
| Observações de migração    | Separar alerta técnico de resumo operacional; usar `job_id` e tentativa.                                |

#### `sire.importacao_referencias.resumo`

| Campo                      | Registro                                                                                        |
| -------------------------- | ----------------------------------------------------------------------------------------------- |
| Módulo                     | SIRE                                                                                            |
| Arquivo / função chamadora | `SIRE/importar_referencias.php` — `sire_notify_slack_user()` no encerramento                    |
| Descrição funcional        | Resume processados, importados, ignorados, duplicados, não encontrados, erros e caminho do log. |
| Entidade principal         | Job de importação                                                                               |
| Tipo                       | Resumo                                                                                          |
| Destinatário atual         | Usuário de banco `idusuario = 1`.                                                               |
| Estratégia sugerida        | Canal/grupo operacional do SIRE; DM somente para falha crítica.                                 |
| Tipo de destino            | Administrador                                                                                   |
| Método atual               | Slack API                                                                                       |
| Prioridade                 | Média; alta quando há erros.                                                                    |
| Repetição                  | Uma por execução do job.                                                                        |
| Escalonamento              | Se houver erro maior que zero ou falha de conexão.                                              |
| Agrupamento                | Um resumo por job.                                                                              |
| Risco de duplicidade       | Baixo, salvo reexecução do mesmo lote.                                                          |
| Status                     | Ativo                                                                                           |
| Observações de migração    | Anexar correlação do job e link seguro para o log, sem caminho interno exposto.                 |

### Respostas diárias

#### `resposta_diaria.questionario.respondido`

| Campo                      | Registro                                                                           |
| -------------------------- | ---------------------------------------------------------------------------------- |
| Módulo                     | Respostas diárias                                                                  |
| Arquivo / função chamadora | `submit_respostas.php`; envio direto após inserir em `respostas_diarias`           |
| Descrição funcional        | Publica colaborador, resposta de finalização, atividade do dia e bloqueio.         |
| Entidade principal         | Resposta diária                                                                    |
| Tipo                       | Imediato                                                                           |
| Destinatário atual         | Webhook `SLACK_WEBHOOK_DAILY_URL`.                                                 |
| Estratégia sugerida        | Canal de acompanhamento diário; bloquear dados sensíveis e usar resumo por equipe. |
| Tipo de destino            | Canal                                                                              |
| Método atual               | Webhook                                                                            |
| Prioridade                 | Média; alta se houver bloqueio.                                                    |
| Repetição                  | Pode repetir se o formulário for reenviado.                                        |
| Escalonamento              | Bloqueios devem gerar evento separado e escalar.                                   |
| Agrupamento                | Agrupar respostas por dia/equipe.                                                  |
| Risco de duplicidade       | Médio por reenvio do formulário.                                                   |
| Status                     | Ativo                                                                              |
| Observações de migração    | Separar “resposta recebida” de “bloqueio informado”.                               |

### Render

#### `render.job.status_atualizado`

| Campo                      | Registro                                                                                                                           |
| -------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| Módulo                     | Render                                                                                                                             |
| Arquivo / função chamadora | `Render/script.py` — `send_webhook_message()` e `send_dm_to_user()`; chamados por `process_job_folder()`                           |
| Descrição funcional        | Publica status do render no canal e replica a mensagem por DM ao responsável; há também aviso de render sem colaborador atribuído. |
| Entidade principal         | Job de render                                                                                                                      |
| Tipo                       | Imediato                                                                                                                           |
| Destinatário atual         | Canal em `SLACK_WEBHOOK_URL` e responsável resolvido por `FLOW_TOKEN`.                                                             |
| Estratégia sugerida        | Canal de render + DM ao responsável; administrador somente para ausência de responsável/falha técnica.                             |
| Tipo de destino            | Canal e DM                                                                                                                         |
| Método atual               | Webhook e Slack API                                                                                                                |
| Prioridade                 | Alta para falha/sem responsável; média para sucesso.                                                                               |
| Repetição                  | Pode repetir em reprocessamento de pasta/job.                                                                                      |
| Escalonamento              | Sim para falha, ausência de responsável ou status sem transição.                                                                   |
| Agrupamento                | Agrupar sucessos; manter falhas e ausência de responsável individuais.                                                             |
| Risco de duplicidade       | Alto com `Render/deadline_monitor.py`.                                                                                             |
| Status                     | Precisa validação                                                                                                                  |
| Observações de migração    | Confirmar qual worker é oficial antes de migrar; não deixar os dois publicarem o mesmo status.                                     |

#### `render.job.transicao`

| Campo                      | Registro                                                                                                                |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| Módulo                     | Render / Deadline Monitor                                                                                               |
| Arquivo / função chamadora | `Render/deadline_monitor.py` — `send_transition_notifications()`, `send_webhook_message()` e `send_dm_to_user()`        |
| Descrição funcional        | Publica transições de render no canal, envia DM ao responsável e registra notificação interna; também trata rollup P00. |
| Entidade principal         | Job/tarefa de render                                                                                                    |
| Tipo                       | Imediato                                                                                                                |
| Destinatário atual         | Canal `SLACK_WEBHOOK_URL`, responsável por `FLOW_TOKEN` e notificação interna.                                          |
| Estratégia sugerida        | Fonte única para eventos Render; canal + DM conforme severidade.                                                        |
| Tipo de destino            | Canal, DM e grupo interno                                                                                               |
| Método atual               | Webhook e Slack API                                                                                                     |
| Prioridade                 | Alta para transições de erro; média para sucesso.                                                                       |
| Repetição                  | O código usa tentativas/eventos, mas a cobertura deve ser validada.                                                     |
| Escalonamento              | Sim para erro, ausência de responsável e falha de entrega.                                                              |
| Agrupamento                | Rollup P00 pode ser agrupado; erros não.                                                                                |
| Risco de duplicidade       | Alto com `Render/script.py`.                                                                                            |
| Status                     | Precisa validação                                                                                                       |
| Observações de migração    | Escolher este ou `Render/script.py` como produtor oficial e converter o outro em consumidor/compatibilidade.            |

### Diagnóstico Slack

#### `slack.diagnostico.conectividade`

| Campo                      | Registro                                                                       |
| -------------------------- | ------------------------------------------------------------------------------ |
| Módulo                     | Operação / diagnóstico                                                         |
| Arquivo / função chamadora | `scripts/slack_monitor.sh`; bloco de monitoramento executado em loop           |
| Descrição funcional        | Testa DNS, TCP, webhook e `auth.test`; publica ping de diagnóstico no webhook. |
| Entidade principal         | Integração Slack / conectividade                                               |
| Tipo                       | Diagnóstico                                                                    |
| Destinatário atual         | Webhook `SLACK_WEBHOOK_MAIN_URL`.                                              |
| Estratégia sugerida        | Canal técnico de integração ou observabilidade, nunca canal operacional.       |
| Tipo de destino            | Canal                                                                          |
| Método atual               | Webhook e Slack API `auth.test`                                                |
| Prioridade                 | Baixa para o ping; alta se o monitor detectar indisponibilidade.               |
| Repetição                  | 40 iterações a cada 30 segundos por execução.                                  |
| Escalonamento              | Sim somente quando DNS/TCP/auth/webhook falhar.                                |
| Agrupamento                | Agrupar diagnóstico em log; não enviar um ping por iteração em produção.       |
| Risco de duplicidade       | Alto se houver mais de uma instância do monitor.                               |
| Status                     | Diagnóstico                                                                    |
| Observações de migração    | Substituir por health check observável; não cadastrar como evento de negócio.  |

## Possíveis notificações duplicadas

1. `arquivo.upload.status` em `FlowDrive/upload.php` e `arquivo.upload.worker_status` em `scripts/upload_worker.php`: ambos enviam sucesso/falha de upload para o mesmo colaborador.
2. `review.angulo.decisao_registrada` em `FlowReview/atualizar_angulo.php` e os ramos de ângulo de `FlowReviewExt/salvar_decisao.php`: podem notificar DM e canal para a mesma decisão.
3. `review.decisao.registrada` + `review.angulo.decisao_publicada`: `salvar_decisao.php` envia uma DM de decisão antes da DM específica de ângulo.
4. `review.tarefa.revisao_concluida` + `review.imagem.decisao_publicada`: revisão do FlowReview e decisão do FlowReviewExt podem representar a mesma mudança.
5. `review.tarefa.revisao_concluida` + notificações internas em `notificacoes_gerais`: o mesmo ajuste pode chegar por Slack e pela central interna.
6. `review.tarefa.revisao_concluida` + `review.sla.excedido`/`review.tarefa.atrasada_resumo`: o mesmo item pode receber alerta imediato e temporal sem política de silêncio.
7. `fotografico.registro.criado` + `fotografico.plano.notificacao`: possível sobreposição entre o fluxo legado do Dashboard e o módulo Fotográfico novo.
8. `pos.imagem.finalizada` em `inserir_pos_producao.php` e `toggle_status_pos.php`: mesma mensagem e mesmo webhook.
9. `pos.imagem.finalizada` + mensagens de “função refeita” em `uploadArquivos.php`: podem ocorrer no mesmo ciclo de reenvio.
10. `postagem.pendente.resumo` + `review.pendencia.resumo_legado`: ambos listam pendências relacionadas a imagens/tarefas.
11. `render.job.status_atualizado` + `render.job.transicao`: dois workers podem publicar canal e DM para o mesmo job.
12. `fotografico.plano.notificacao` + `fotografico.pendencia.cobranca`: são eventos diferentes, mas precisam de silêncio entre a criação e a cobrança.
13. `review.mencao.criada`: comentário e resposta podem gerar duas DMs para a mesma menção se a relação for recriada.
14. `calendario.entrega.proximos_7_dias`: execução repetida do script pode republicar o mesmo resumo.

## Funções de envio duplicadas ou equivalentes

### Wrappers equivalentes à Slack API

- `FlowReview/atualizar_angulo.php` — `slack_post_message()`.
- `FlowReview/mencao_slack_helper.php` — `_mencao_slack_post_message()`.
- `FlowReview/revisarTarefa.php` — `enviarNotificacaoSlack()`.
- `FlowReview/sla_check_cron.php` — `enviarSlack()`.
- `Dashboard/add_fotografico_registro.php` — `send_simple_slack_dm()`.
- `Fotografico/fotografico_slack.php` — `fotografico_slack_enviar_dm()`.
- `scripts/fotografico_pendencias_cron.php` — `foto_cobranca_enviar_dm()`.
- `notificacao_modulo_status.php` — `slack_send_dm()`.
- `PreAlteracao/update_complexidade.php` — `notificarPedroSlack()`.
- `SIRE/importar_referencias.php` — `sire_notify_slack_user()`.
- `scripts/upload_worker.php` — `send_slack_notification_for_colaborador()`.
- `FlowDrive/upload.php` — `send_slack_token_message()`.
- `Render/script.py` e `Render/deadline_monitor.py` — `send_dm_to_user()`.

### Wrappers equivalentes a webhook

- `calendar.php` — `enviarNotificacaoSlack()`.
- `Contratos/webhook.php` — `slack_send_webhook()`.
- `FlowDrive/upload.php` — `send_slack_webhook()` sem chamada encontrada.
- `Render/script.py` e `Render/deadline_monitor.py` — `send_webhook_message()`.
- `notificacoes/enviarSlackPendentes.php` — envio inline com fallback webhook/API.
- `Pos-Producao/inserir_pos_producao.php`, `toggle_status_pos.php` e `uploadArquivos.php` — envio inline para `SLACK_WEBHOOK_POS_URL`.
- `submit_respostas.php` — envio inline para `SLACK_WEBHOOK_DAILY_URL`.

### Resolução de destinatários duplicada

Há várias implementações de `users.list`, comparação por `real_name`, `display_name` ou `nome_slack`. O Flow Connect deve ter um único resolvedor/cache de identidade Slack, com atualização e expiração controladas.

## Webhooks, tokens e IDs hardcoded

### Variáveis de ambiente usadas

- `SLACK_TOKEN`.
- `FLOW_TOKEN`.
- `SLACK_CHANNEL`.
- `SLACK_API_URL`.
- `SLACK_WEBHOOK_URL`.
- `SLACK_WEBHOOK_CONTRATOS_URL`.
- `SLACK_WEBHOOK_POS_URL`.
- `SLACK_WEBHOOK_DAILY_URL`.
- `SLACK_WEBHOOK_MAIN_URL`.

### IDs de canais hardcoded

- `C09V1SES7B2`: `FlowReviewExt/salvar_decisao.php` e `solicitar_refazer_angulos.php`.
- `C087WRC2ZME`: Modelagem em `notificacoes_slack.php`.
- `C087LMQJLGH`: Composição em `notificacoes_slack.php`.
- `C086TFA7JJ3`: Finalização em `notificacoes_slack.php`.
- `C08781CH95G`: Pós-produção em `notificacoes_slack.php`.
- `C087FR3640J`: Planta Humanizada em `notificacoes_slack.php`.

### Valores/configurações hardcoded relevantes

- `notificacoes_slack.php` define `SLACK_TOKEN` como `?`, indicando configuração inválida/provisória.
- `FlowReviewExt` fixa o canal `C09V1SES7B2` em vez de usar configuração por ambiente.
- `scripts/slack_monitor.sh` contém prefixo de webhook Slack hardcoded e monta o restante a partir de `.env`; deve ser tratado como material sensível e removido do código.
- `FlowReview/revisarTarefa.php` fixa os nomes `Pedro Sabel` e `Andre L. de Souza`.
- `notificacao_modulo_status.php` fixa os mesmos dois nomes.
- `FlowReview/sla_check_cron.php` filtra diretamente `idusuario IN (1)`; o comentário menciona mais usuários, portanto há divergência.
- `SIRE/importar_referencias.php` fixa `$slackUsuarioId = 1`.
- O método `chat.postMessage` e os endpoints `users.list` aparecem literalmente em diversos arquivos; devem ser centralizados.

## Envios que notificam somente Pedro e André

- `FlowReview/revisarTarefa.php`: ramo final de revisão direcionado aos nomes `Pedro Sabel` e `Andre L. de Souza`.
- `FlowReview/revisarTarefa.php`: ramo de falha SFTP consulta esses dois nomes.
- `notificacao_modulo_status.php`: ao visualizar/confirmar uma notificação, envia aos mesmos dois nomes.

Não classifiquei `PreAlteracao/update_complexidade.php` como “Pedro e André”, pois ele busca somente alguém cujo nome contém `Sabel`. Também não classifiquei o SLA como confirmado, porque o SQL atual filtra apenas `idusuario = 1` e não prova que esse usuário seja Pedro ou André.

## Scripts temporais ou executados por cron

| Script                                    | Periodicidade indicada/encontrada                        | Evento                               |
| ----------------------------------------- | -------------------------------------------------------- | ------------------------------------ |
| `FlowReview/sla_check_cron.php`           | Comentário indica execução horária                       | `review.sla.excedido`                |
| `scripts/fotografico_pendencias_cron.php` | Comentário indica a cada 5 minutos                       | `fotografico.pendencia.cobranca`     |
| `scripts/slack_overdue_daily.php`         | Nome e lógica indicam execução diária                    | `review.tarefa.atrasada_resumo`      |
| `scripts/upload_worker.php`               | Worker/daemon de processamento                           | `arquivo.upload.worker_status`       |
| `Render/deadline_monitor.py`              | Worker contínuo/Deadline                                 | `render.job.transicao`               |
| `Render/script.py`                        | Worker legado/principal a validar                        | `render.job.status_atualizado`       |
| `SIRE/importar_referencias.php`           | Script operacional; agendamento não comprovado no código | `sire.importacao_referencias.*`      |
| `calendar.php`                            | Script operacional; agendamento não comprovado no código | `calendario.entrega.proximos_7_dias` |
| `scripts/slack_monitor.sh`                | 40 ciclos de 30 segundos por execução                    | `slack.diagnostico.conectividade`    |

## Envios técnicos que devem ficar separados de alertas operacionais

- `review.sftp.envio_falhou`: falha de infraestrutura/transferência, não uma mudança de negócio.
- `sire.importacao_referencias.falhou`: erro de query ou SFTP; deve ir para observabilidade/suporte.
- `slack.diagnostico.conectividade`: ping, DNS, TCP e `auth.test`; nunca deve poluir canais operacionais.
- Falhas de `arquivo.upload.worker_status`: podem ser operacionais para o responsável, mas devem gerar um evento técnico separado para retries definitivos.
- Falhas de Render em `render.job.status_atualizado`/`render.job.transicao`: separar sucesso de render, falha de engine, ausência de responsável e falha de Slack.
- Falha de resolução `users.list`, usuário sem `nome_slack` e token ausente: devem ser métricas/logs técnicos, não notificações de negócio.
- Falhas de webhook/API em todos os módulos: devem ir para outbox/observabilidade com correlação, e não ser reenviadas como novas notificações de negócio sem política.

## Proposta de ordem de migração

1. **Fundação:** criar catálogo, identidade de destinatários, transportes webhook/API, outbox, idempotência, preferências, níveis de prioridade e correlação.
2. **FlowReview:** migrar decisões, revisões, menções, direção e SFTP; é o maior núcleo de sobreposição.
3. **SLA:** unificar `review.sla.excedido` e `review.tarefa.atrasada_resumo`, diferenciando “em aprovação” de “em andamento atrasado”.
4. **Fotográfico:** migrar eventos imediatos e cobranças temporais; preservar reserva de cobrança e silêncio por pendência.
5. **Arquivos/uploads:** unificar FlowDrive, worker e `uploadArquivos.php` em eventos de job, tentativa e status.
6. **Pós-produção:** escolher a única fonte de `pos.imagem.finalizada` e retirar os dois envios equivalentes.
7. **Render:** escolher entre `Render/script.py` e `Render/deadline_monitor.py` como produtor oficial antes da migração.
8. **Contratos, Calendário, SIRE e Respostas diárias:** migrar como módulos independentes, com templates e destinatários próprios.
9. **Legados:** substituir `notificacoes_slack.php`, wrappers sem chamada e configurações hardcoded.
10. **Diagnóstico:** migrar o monitor Shell para health checks, métricas e alertas técnicos.

## Relação entre criação, cobrança, resolução e cancelamento

O modelo funcional sugerido é:

```text
criação ──► notificação imediata ──► aguardando/responsável
                                  │
                                  ├── prazo atingido ──► cobrança temporal
                                  │                         │
                                  │                         └── escalonamento
                                  │
                                  ├── resolução ──► confirmação/encerramento
                                  │                 └── cancelar cobranças futuras
                                  │
                                  └── cancelamento ──► encerrar evento e silenciar retries
```

### Aplicação aos módulos

- **Fotográfico:** plano/pendência criada → `fotografico.plano.notificacao` ou `fotografico.pendencia.criada` → `fotografico.pendencia.cobranca` → `fotografico.pendencia.resolvida` ou `fotografico.pendencia.cancelada`.
- **FlowReview:** tarefa criada/aprovação solicitada → `review.tarefa.aprovacao_solicitada` → `review.sla.excedido` → `review.tarefa.revisao_concluida` ou `review.tarefa.cancelada`.
- **Arquivos:** job criado → `arquivo.upload.iniciado` → `arquivo.upload.falhou` com retry/escalonamento ou `arquivo.upload.finalizado`.
- **Render:** job criado → `render.job.iniciado` → `render.job.falhou` ou `render.job.finalizado`; ausência de responsável deve ser um evento técnico separado.
- **Ângulos:** solicitação criada → `review.angulo.refazer_solicitado` → cobrança se pendente → `review.angulo.decisao_registrada`.
- **Contratos:** documento criado → `contratos.documento.status_atualizado` para cada transição válida; cancelamento/revogação deve silenciar eventos posteriores.

### Eventos de cancelamento ainda não encontrados

Não foi identificado envio Slack explícito para cancelamento de pendência, tarefa, upload ou render. O Flow Connect deve prever esses eventos para cancelar notificações pendentes e impedir cobranças após o encerramento:

- `fotografico.pendencia.cancelada`;
- `review.tarefa.cancelada`;
- `arquivo.upload.cancelado`;
- `render.job.cancelado`;
- `contratos.documento.cancelado`.

## Pontos que precisam de validação antes da arquitetura final

- Qual worker de Render é oficial: `Render/script.py` ou `Render/deadline_monitor.py`.
- Quais endpoints de pós-produção realmente permanecem ativos.
- Se `calendar.php`, SIRE e os crons possuem agendamento único em produção.
- Se os IDs e nomes fixos representam papéis permanentes ou apenas configuração histórica.
- Se `FlowReviewExt` e `FlowReview` são ambos fontes de decisão ou se um deles apenas replica dados.
- Se `notificacoes_slack.php` ainda é executado por algum scheduler.
- Qual canal deve substituir cada ID hardcoded.
- Quais sucessos devem ser DM e quais devem ser somente evento interno/canal.
