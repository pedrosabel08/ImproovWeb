# Diagnóstico do Deadline

## Integração com o Flow

- `deadline_worker.py` mantém a sincronização de jobs, as tentativas em `render_tentativas`, o progresso em `deadline_job_progress` e a fila de comandos existente. Em `deadline_monitor.py`, `deadline_error_info` preenche o resumo de erro do render com status da tarefa e referências aos relatórios. `send_transition_notifications` publica avisos de transição do render com deduplicação por tentativa.
- O coletor usa a descoberta e as observações de tarefa do worker existente. `deadline_report_export.py` segue o ponto de integração de `deadline_snapshot_export.py`, executado pelo SDK do Deadline. Um lock no banco serializa coletas concorrentes e os índices únicos protegem a identidade das ocorrências e incidentes.
- `deadline_diagnostic_store.py` grava o evento de alerta em `flow_connect_events` na mesma transação da ocorrência e do incidente. O planejador, o resolvedor e o worker de entregas do Flow Connect processam os alertas pelo contrato existente. `Render/ajax.php` fornece os diagnósticos pela sessão autenticada do Render.

## Arquivos

| Área                       | Arquivos                                                                                                                                                                                                                                                                                  |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Coleta, regras e histórico | `Render/deadline_worker.py`, `Render/deadline_report_export.py`, `Render/deadline_diagnostic_collector.py`, `Render/deadline_diagnosis.py`, `Render/deadline_diagnostic_store.py`, `Render/deadline_diagnostic_reclassify.py`                                                             |
| Interface e API            | `Render/index.php`, `Render/ajax.php`, `Render/deadline_diagnostic_flow.php`, `Render/deadline-diagnostic.js`, `Render/style.css`                                                                                                                                                         |
| Flow Connect               | `config/events/deadline.php`, `contracts/EventValidator.php`, `application/EventPlanner.php`, `application/RecipientResolver.php`, `infrastructure/DeliveryRepository.php`, `channels/SlackApiAdapter.php`, `templates/slack/operational/deadline_incident.php`, dentro de `FlowConnect/` |
| Banco                      | `sql/2026-11-16_deadline_diagnosis.sql`                                                                                                                                                                                                                                                   |
| Testes                     | `Render/tests/test_deadline_diagnosis.py`, `Render/tests/fixtures/deadline_imp_frm_004.txt`, `FlowConnect/tests/DeadlineIncidentTest.php`, `FlowConnect/tests/run.php`                                                                                                                    |

## Coleta e classificação

O worker contínuo reutiliza a descoberta de jobs da farm. Uma mudança no contador de erros de um job vinculado prioriza sua consulta. O worker executa `deadline_report_export.py` com o mesmo comando usado para exportar snapshots. O SDK lê os Error Reports do job, com ID, tarefa, frames, Worker e data fornecidos pelo repositório. O coletor lê apenas corpos novos, com metadados alterados ou com uma leitura pendente. A ausência de um dado fica explícita.

Quando houver acúmulo, os relatórios ainda não coletados mais recentes são lidos primeiro; relatórios antigos continuam elegíveis nas consultas seguintes. A linha de configuração `Ignore missing DLL errors` não é evidência de que um plugin esteja ausente.

As ocorrências são únicas por Job ID e Report ID. A assinatura considera a regra, o renderer conhecido e, para falhas que precisam de mais detalhe, a mensagem normalizada. O incidente reúne a mesma assinatura no mesmo Worker e pode abranger vários jobs. Os índices também permitem consultar assinaturas em Workers diferentes e falhas distintas no mesmo Worker. Relatórios sem Worker são correlacionados no próprio job. Relatórios sem horário comprovado ficam fora das janelas de notificação comuns e de recuperação. Um relatório crítico sem horário pode gerar alerta se aparecer após uma coleta completa já dentro do período de ativação.

Cada regra tem ID, versão, padrões obrigatórios e complementares, prioridade, confiança, severidade, explicação e recomendação em `deadline_diagnosis.py`. O catálogo cobre RAM, encerramento e inicialização do 3ds Max, Corona, assets, rede/NAS, licença, plugin, timeout, comunicação com Deadline, Worker sem resposta, I/O, incompatibilidade de configuração e falha desconhecida. O encerramento isolado do 3ds Max fica indeterminado. Para o cenário IMP-FRM-004, o aviso explícito do Corona, a RAM elevada e o encerramento sustentam a classificação provável de memória insuficiente. Grupos e pools são coletados como configuração. Uma incompatibilidade de RAM só é registrada quando a RAM física medida pode ser comparada com o requisito explícito `FlowRequiredRamBytes`, quando esse campo de Extra Info é fornecido pelo job.

Um incidente passa a monitoramento após haver conclusão relevante para cada combinação afetada de job, tarefa e Worker. A coleta exige uma tarefa completada após a falha e um Job Log Report associado à mesma execução, com tempo de render positivo. A resolução exige o período de estabilidade e uma consulta completa de todos os jobs afetados após esse período. Uma nova falha comprovadamente posterior à resolução abre outro episódio no mesmo incidente. O histórico preserva episódios, mudanças de estado, classificação e eventos enfileirados.

## Implantação

1. Aplique `sql/2026-11-16_deadline_diagnosis.sql` no banco usado pelo worker, pelo Render e pelo Flow Connect. O schema principal do Flow Connect deve existir para shadow e active. Execute a migração primeiro em homologação.
2. Configure `DEADLINE_DIAGNOSTIC_ENABLED=1` no serviço existente do worker e reinicie esse serviço conforme o procedimento operacional usual. O padrão é desligado. Use `DEADLINE_DIAGNOSTIC_NOTIFY_MODE=shadow` para registrar os alertas planejados sem entrega. Verifique a versão instalada do Deadline, compare IDs e horários dos relatórios com o Deadline Monitor e valide as assinaturas com logs reais antes do corte.
3. Configure `FLOW_RENDER_DEADLINE_DIAGNOSIS=1` no ambiente PHP para liberar o painel. Gestores acessam a lista da farm. A visualização pelo render reutiliza as permissões do Render EF para gestores, responsáveis e colaboradores de render/pós associados à imagem. O Flow guarda trechos redigidos e as referências para localizar o original no Deadline Monitor. Opcionalmente, configure `FLOW_RENDER_DEADLINE_REPORT_ORIGIN` para uma origem HTTPS aprovada, autenticada e restrita aos relatórios da farm. O link direto fica visível aos gestores.
4. Em shadow, compare as ocorrências e incidentes do Flow com os Job Reports e os alertas atuais. Consulte `deadline_diagnostic_jobs.last_error` para falhas de leitura e `flow_connect_events` com `source_module='deadline'` para o histórico do produtor. Os workers do Flow Connect devem planejar eventos shadow sem entrega ao Slack.
5. Após validar shadow, configure `FLOW_CONNECT_DEADLINE_WEBHOOK_URL` nos workers de eventos e entrega do Flow Connect. Aponte para o canal operacional restrito da farm. Configure `DEADLINE_DIAGNOSTIC_ALERT_FROM_UTC` para o início do corte, com fuso explícito (por exemplo um instante com sufixo `Z`), e altere `DEADLINE_DIAGNOSTIC_NOTIFY_MODE=active`. O corte evita emitir alertas históricos importados na primeira coleta. Verifique evento, notificação e entrega no Flow Connect. Só então configure `FLOW_CONNECT_RENDER_FARM_TASK_FAILURE_ENABLED=0` no emissor legado e recarregue o serviço. A chave afeta apenas falhas de tarefa e suas resoluções; eventos já enfileirados seguem seu fluxo normal. Os avisos de transição de render pertencem ao fluxo de aprovação.

| Variável do serviço Deadline                      | Padrão                                                                                                  |
| ------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| `DEADLINE_DIAGNOSTIC_NOTIFY_MODE`                 | `off`. A coleta pode funcionar com alertas desligados.                                                  |
| `DEADLINE_DIAGNOSTIC_THRESHOLD`                   | 3 ocorrências para alertas comuns                                                                       |
| `DEADLINE_DIAGNOSTIC_WINDOW_MINUTES`              | 15 minutos                                                                                              |
| `DEADLINE_DIAGNOSTIC_UPDATE_MINUTES`              | 60 minutos entre atualizações, mediante mudança relevante                                               |
| `DEADLINE_DIAGNOSTIC_STABILITY_MINUTES`           | 30 minutos após a recuperação comprovada                                                                |
| `DEADLINE_DIAGNOSTIC_JOB_POLL_SECONDS`            | 60 segundos até o job ficar elegível para nova consulta                                                 |
| `DEADLINE_DIAGNOSTIC_REPORTS_PER_POLL`            | 8 corpos por consulta, até 25                                                                           |
| `DEADLINE_DIAGNOSTIC_COMMAND_TIMEOUT`             | 15 segundos de limite por chamada ao Deadline                                                           |
| `DEADLINE_DIAGNOSTIC_UNSPECIFIED_DATETIME_IS_UTC` | Desligado. Ative somente se a instalação comprovar que valores com `DateTime.Kind=Unspecified` são UTC. |

Alertas críticos são enfileirados no mesmo ciclo em que a evidência é coletada. A latência até a publicação no Slack depende da consulta à farm e dos workers do Flow Connect. Os demais alertas exigem a janela móvel configurada. Criticidade imediata está explícita nas regras de perda de resposta do Worker, comunicação com o repositório e indisponibilidade do servidor de licença. As atualizações usam o webhook existente do Flow Connect, que não devolve um ID de mensagem para edição. A deduplicação é por incidente, episódio, modo e sequência. Em um timeout ambíguo após o aceite pelo Slack, o webhook não oferece garantia de entrega exatamente uma vez.

## Reclassificação e limites

Ao alterar o catálogo, incremente `CATALOG_VERSION` e a versão das regras alteradas. Execute `python Render/deadline_diagnostic_reclassify.py` para reclassificar os registros anteriores, ou informe `--job <ID_DO_DEADLINE>`. O comando usa as evidências preservadas, registra a nova classificação e mantém o vínculo operacional original. Caso uma nova regra exija uma linha que não foi preservada, consulte o Job Report original, se ainda estiver disponível no repositório. A coleta usa IDs e metadados dos relatórios finalizados; uma alteração do corpo sem alteração dos metadados não é detectada no ciclo normal.

A reclassificação não reconstrói a correlação dos incidentes existentes. Revise os vínculos e os alertas já produzidos antes de usar ocorrências reclassificadas para validar o corte de notificações.

Os alertas refletem falhas que geraram um Error Report. A janela de polling depende do número de jobs, do intervalo do worker e da latência do repositório. Jobs ausentes saem da rotação um dia após a última descoberta, com registro da última falha de acesso. A RAM disponível, os grupos e os pools são observações da coleta. Alterações entre coletas são fatores possíveis e podem não refletir a configuração histórica exata na hora da falha.

A coleta não copia logs integrais. Aplica limites de tamanho e redação de credenciais comuns, URLs e caminhos. A validação local usa um log representativo do caso IMP-FRM-004 e mensagens sintéticas para as demais categorias. O repositório 10.4.2.2 e a entrega real ao Slack precisam de validação em shadow/homologação.

## Testes

- `python -m unittest discover -s Render/tests -p test_deadline_diagnosis.py` executa parser, assinaturas, simulação do SDK e configuração.
- Defina `FLOW_TEST_DIAGNOSTICS_MYSQL_PORT` para uma instância local de testes para incluir migração, concorrência, recuperação, falhas temporárias e integração com os leitores PHP. A suíte usa um schema temporário próprio. As variáveis opcionais `FLOW_TEST_DIAGNOSTICS_MYSQL_USER` e `FLOW_TEST_DIAGNOSTICS_MYSQL_PASSWORD` configuram o usuário dessa instância.
- `php FlowConnect/tests/run.php` verifica o contrato, o template, os destinatários e a política de retries.

## Rollback

1. Defina `FLOW_CONNECT_DEADLINE_DELIVERY_PAUSED=1` no ambiente do worker de entrega e recarregue o processo para pausar a fila do Deadline. Uma entrega reclamada antes da pausa ainda pode terminar.
2. Volte `DEADLINE_DIAGNOSTIC_NOTIFY_MODE` para `shadow` ou `off`. Para parar também a coleta, defina `DEADLINE_DIAGNOSTIC_ENABLED=0` e reinicie o worker existente. O diagnóstico no Render pode ser ocultado com `FLOW_RENDER_DEADLINE_DIAGNOSIS=0`.
3. Se o emissor equivalente foi desabilitado em produção, restaure essa política. Preserve as tabelas de diagnóstico e os eventos do Flow Connect para auditoria. Antes de remover a pausa, revise entregas antigas ainda pendentes do Deadline, que voltam a ficar elegíveis ao reativar a fila.

Referências oficiais do Deadline. [SDK de relatórios do repositório](https://docs.thinkboxsoftware.com/products/deadline/10.4/2_Scripting%20Reference/class_deadline_1_1_scripting_1_1_repository_utils.html) · [Campos dos relatórios](https://docs.thinkboxsoftware.com/products/deadline/latest/2_Scripting%20Reference/class_deadline_1_1_reports_1_1_report.html) · [Endpoint do relatório original](https://docs.thinkboxsoftware.com/products/deadline/10.4/1_User%20Manual/manual/rest-job-reports.html)
