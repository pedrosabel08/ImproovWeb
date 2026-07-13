# Worker contínuo Flow × Deadline

## Responsabilidades

- O PHP registra a intenção de negócio e confirma tudo em uma única transação:
  cria tentativas, encerra a tentativa anterior e insere `DELETE_JOB` em
  `deadline_comandos`.
- `deadline_worker.py` é o único processo autorizado a executar `DeleteJob`.
- `deadline_monitor.py` conserva as regras existentes de prévias, POS, P00 e
  notificações, mas é chamado pelo worker com um vínculo estrito de tentativa.
  A execução periódica antiga fica desativada quando
  `DEADLINE_WORKER_MODE=continuous` e `DEADLINE_LEGACY_MODE=0`.
- `script.py` continua responsável apenas pelo Backburner. Um registro que tenha
  `deadline_job_id` não é removido por esse script.

Não são gravados metadados customizados nos jobs do Deadline. A associação usa
nome do job e o estado persistido no banco do Flow.

## Modelo persistente

### `render_tentativas`

Mantém o histórico imutável dos ciclos de envio. Há no máximo uma tentativa
ativa por `render_id`, garantido pela coluna gerada `ativa_render_id` e por uma
chave única. O `deadline_job_id` antigo permanece na tentativa encerrada.

Estados operacionais:

- `AGUARDANDO_JOB`: tentativa criada pelo Flow, ainda sem job associado;
- `VINCULADA`: job descoberto e associado de forma inequívoca;
- `EM_ANDAMENTO`, `EM_APROVACAO`, `ERRO`: observações do Deadline;
- `EXCLUSAO_PENDENTE`: ação do Flow já confirmada e comando persistido;
- `APROVADA`, `REPROVADA`, `REFAZENDO`, `ENCERRADA`, `CANCELADA` e
  `INCONSISTENTE`: estados terminais ou de auditoria.

`render_alta.deadline_job_id` permanece como cache de compatibilidade. A fonte
de verdade passa a ser a tentativa. A reconciliação só corrige o cache quando
existe exatamente uma tentativa ativa.

`deleteRender` passou a arquivar logicamente o render, encerrar suas tentativas
e enfileirar os jobs vinculados. Se a mesma combinação imagem/status for criada
depois, `addRender.php` reativa o registro e abre uma nova tentativa; o histórico
e comandos anteriores permanecem intactos.

### `deadline_comandos`

Fila transacional e idempotente. A chave `(deadline_job_id, tipo)` impede dois
`DELETE_JOB` para o mesmo job. O consumidor reserva uma linha com
`FOR UPDATE SKIP LOCKED`, confirma a reserva e libera a transação antes de chamar
o executável externo.

Estados: `PENDENTE`, `PROCESSANDO`, `CONCLUIDO` e `ERRO`. Falhas usam backoff de
10 s, 30 s, 60 s, 5 min e 15 min, limitado por `max_tentativas`. Um job já
inexistente no Deadline é sucesso idempotente. Locks vencidos voltam para
`PENDENTE` durante a reconciliação.

### `deadline_workers` e `render_tentativa_eventos`

`deadline_workers` contém PID, host, versão, modo e heartbeat. Eventos têm chave
única por tentativa/tipo/chave para impedir notificações repetidas do mesmo
estado.

## Rotinas independentes

- Fila: a cada 3 segundos por padrão;
- sincronização de tentativas ativas: 15 segundos;
- descoberta de jobs desconhecidos: 30 segundos;
- reconciliação: 5 minutos;
- heartbeat: 1 minuto.

Uma falha em uma rotina não interrompe as demais. A conexão MySQL é validada e
reaberta após falha.

### Sincronização ativa

Somente jobs já presentes em tentativas ativas e operacionais são consultados
com `-GetJob` e `-GetJobTasks`. Antes de executar efeitos existentes, o worker
confirma `tentativa_id + render_id + deadline_job_id + ativa=1`. Transições que
regrediriam ou reabririam uma tentativa são recusadas.

### Descoberta

`-GetJobs True` é usado apenas para descobrir IDs que nunca apareceram em
nenhuma tentativa. Para cada job novo:

1. tenta o nome exato da imagem;
2. se não houver nome exato, tenta o prefixo histórico normalizado;
3. exige exatamente uma imagem e uma tentativa ativa `AGUARDANDO_JOB` no status
   atual da imagem;
4. recusa jobs sem data de submissão verificável ou submetidos antes da criação
   da tentativa (com tolerância de cinco minutos para diferença de relógio);
5. se dois jobs competirem pela mesma tentativa no mesmo ciclo, nenhum é
   associado;
6. o vínculo é revalidado e gravado em transação.

Jobs ambíguos, antigos ou sem tentativa aguardando são apenas registrados no
log. Nunca reabrem um render encerrado.

## Instalação e rollout

Pré-requisitos:

1. Python 3.13 e dependências de `requirements-deadline-worker.txt`;
2. `deadlinecommand` disponível no `PATH` da conta do serviço ou caminho
   absoluto em `DEADLINE_COMMAND`;
3. acesso aos mesmos compartilhamentos, FTP e variáveis usados pelo monitor;
4. NSSM para hospedagem como serviço Windows.

Sequência segura:

1. criar e validar um backup completo do banco;
2. executar `deadline_migration_report.py` e salvar o JSON;
3. revisar duplicidades, IDs inválidos e renders terminais ainda vinculados;
4. aplicar `sql/2026-07-13_deadline_continuous_worker.sql` em homologação;
5. executar novamente o relatório e validar o backfill;
6. manter `DEADLINE_DELETE_DRY_RUN=1`;
7. desativar a tarefa agendada antiga de `deadline_monitor.py`;
8. instalar o serviço em PowerShell elevado:

   ```powershell
   .\install_deadline_worker_service.ps1 -NssmPath C:\tools\nssm\nssm.exe
   ```

9. iniciar com `start_deadline_worker.bat` e observar heartbeat, logs,
   associações e fila;
10. somente após a conferência operacional, alterar
    `DEADLINE_DELETE_DRY_RUN=0` e reiniciar o serviço.

O instalador não inicia automaticamente o serviço. Isso preserva a janela de
validação após a instalação.

Scripts operacionais:

- `start_deadline_worker.bat`;
- `stop_deadline_worker.bat`;
- `restart_deadline_worker.bat`.

Para remover o serviço, pare-o e execute em PowerShell elevado:

```powershell
C:\tools\nssm\nssm.exe remove FlowDeadlineWorker confirm
```

## Configuração

As variáveis ficam em `Render/.env`:

- `DEADLINE_WORKER_MODE=continuous`;
- `DEADLINE_LEGACY_MODE=0`;
- `DEADLINE_DELETE_DRY_RUN=1` durante rollout;
- `DEADLINE_COMMAND` e `DEADLINE_COMMAND_TIMEOUT`;
- `DEADLINE_WORKER_ID` opcional; vazio gera `hostname-pid`;
- intervalos `DEADLINE_*_INTERVAL_SECONDS`;
- `DEADLINE_COMMAND_LOCK_TIMEOUT_SECONDS`;
- `DEADLINE_LOG_RETENTION_DAYS`.

Logs estruturados em JSON são gravados em
`Render/logs/deadline_worker.log`, com rotação diária e retenção configurável.

## Diagnóstico e operação

Relatório somente do banco:

```powershell
python deadline_migration_report.py
```

Relatório com uma leitura adicional de IDs no Deadline:

```powershell
python deadline_migration_report.py --check-deadline
```

O diagnóstico nunca executa `DeleteJob` e não altera o banco.

Consultas úteis:

```sql
SELECT * FROM deadline_workers ORDER BY ultimo_heartbeat DESC;
SELECT status, COUNT(*) FROM deadline_comandos GROUP BY status;
SELECT * FROM deadline_comandos WHERE status IN ('PENDENTE','PROCESSANDO','ERRO');
SELECT * FROM render_tentativas WHERE ativa = 1 ORDER BY atualizado_em;
```

## Rollback

O rollback de estrutura está em
`sql/2026-07-13_deadline_continuous_worker_rollback.sql`. Ele se recusa a apagar
as tabelas se já houver tentativas ou comandos, pois isso destruiria histórico.
Depois que o worker produzir dados, rollback exige exportação e decisão manual;
não existe retorno automático seguro ao modelo de um único Job ID.

## Garantias e limites

- Nenhuma migration é aplicada automaticamente pelo worker.
- O backfill não cria exclusões históricas em massa.
- O worker não cria jobs; ele associa jobs submetidos externamente ao Flow.
- A execução de `DeleteJob` só ocorre a partir de um comando confirmado no
  banco e somente quando o dry-run está desligado.
- Efeitos externos existentes, como FTP e Slack, continuam dependentes dos
  respectivos serviços; a chave de evento impede repetição por estado, mas não
  oferece transação distribuída com serviços externos.
