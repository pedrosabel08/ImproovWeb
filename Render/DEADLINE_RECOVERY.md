# Diagnóstico e recuperação no Render

Na tela de detalhes de um render, gestores podem abrir **Diagnóstico e recuperação** e selecionar um job vinculado às suas tentativas. A página consulta o executor e mostra estado, tarefas, Workers, Pool/Group, Job Reports e classificações coletadas. Os relatórios registram fatos observados; o diagnóstico e as recomendações são avaliações. A recuperação não cria nem reenvia notificações do Flow Connect.

## Habilitação e validação

1. Em homologação, com as tabelas do worker contínuo disponíveis, aplique `sql/2026-11-17_deadline_recovery.sql`.
2. Atualize o Flow e reinicie o worker contínuo com sua conta de serviço usual. A integração usa o mesmo `DEADLINE_COMMAND`, conexão SQL e permissões no Repository. Sem a migração, o painel informa indisponibilidade e o worker continua suas rotinas existentes.
3. Em um job de teste vinculado ao Flow, confirme no Monitor: bloquear e desbloquear um Worker, rejeitar o bloqueio do último Worker elegível, retomar uma e depois todas as tarefas em falha, suspender sem interromper uma tarefa em andamento, retomar o job e trocar Pool/Group. Confira um segundo job para verificar o isolamento.
4. Verifique os relatórios e as intervenções. Teste o acesso sem sessão, com um usuário sem perfil de gestor, com uma configuração sem Workers elegíveis e com uma confirmação vencida. Em homologação, simule a perda de comunicação depois do comando para conferir o estado “Resultado não confirmado”.

## Operações do Deadline

A sintaxe da CLI foi conferida no cliente **10.4.2.2** com `deadlinecommand -Version` e `deadlinecommand -Help <operação>`.

| Operação | Comando ou método |
| --- | --- |
| Consulta | `-ExecuteScriptNoGui` com ponte fixa do SDK: `GetJob`, `GetJobTasks`, `GetPoolNames`, `GetGroupNames`, `GetLimitGroup`, `GetPluginLimitGroups`, `GetJobReports`; inventário existente com `-GetSlaveNames` e `-GetSlave … True` |
| Bloquear/desbloquear | `-AddSlavesToJobMachineLimitList <job> <worker>` / `-RemoveSlavesFromJobMachineLimitList <job> <worker>` |
| Retomar erros | `-ResumeFailedJobTasks <job> <ids>`; IDs explícitos, inclusive ao selecionar todas as tarefas |
| Suspender/retomar | `-SuspendJobNonRenderingTasks <job>` / `-ResumeJob <job>` |
| Alterar Pool/Group | `JobPool` e `JobGroup` em um único `RepositoryUtils.SaveJob`; preserva o Pool secundário |

Referências oficiais: [comandos de Jobs](https://docs.thinkboxsoftware.com/products/deadline/latest/1_User%20Manual/manual/command-line-arguments-jobs.html), [comandos de tarefas](https://docs.thinkboxsoftware.com/products/deadline/latest/1_User%20Manual/manual/command-line-arguments-tasks.html), [comandos de Workers](https://docs.thinkboxsoftware.com/products/deadline/latest/1_User%20Manual/manual/command-line-arguments-workers.html), [SDK do Repository](https://docs.thinkboxsoftware.com/products/deadline/10.4/2_Scripting%20Reference/class_deadline_1_1_scripting_1_1_repository_utils.html) e [propriedades do job](https://docs.thinkboxsoftware.com/products/deadline/10.4/2_Scripting%20Reference/class_deadline_1_1_jobs_1_1_job.html).

## Auditoria e limites

- A tabela registra o usuário, o colaborador, os horários em UTC, o render, o job, os parâmetros solicitados, o estado anterior, as respostas dos comandos e o estado observado. A mesma consulta não autoriza duas intervenções e o banco só admite uma solicitação em curso por job.
- A confirmação expira em cinco minutos. A fila não executa solicitações que esperaram mais de dois minutos. A consulta ao Deadline após o comando determina o resultado: envio à fila e código de saída zero não bastam.
- A validação de elegibilidade usa Workers em execução ou ociosos, Pools, Group, Allow/Deny List, limites de job/plugin e RAM física quando o job declara `FlowRequiredRamBytes`. Ela não garante o agendamento futuro nem disponibilidade imediata de um stub de licença. Um job com Allow List mantém essa configuração e não oferece edição de Deny List.
- São mostrados até 50 relatórios recentes por tipo, 50 ocorrências coletadas e 50 intervenções recentes. As intervenções continuam registradas na tabela de auditoria.
- Edições externas feitas no Deadline Monitor durante a operação podem causar resultado não confirmado. Se o executor cair em meio a uma operação, a solicitação fica bloqueada em `running` para evitar repetição. O operador deve confirmar que o processo encerrou, conferir o estado no Monitor e encerrar a linha como `unverified` com `finished_at` em UTC, preservando o conteúdo de auditoria. Não colocar a linha de volta na fila.
