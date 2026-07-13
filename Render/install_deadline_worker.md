# Instalação do worker Deadline no Windows

## Antes de instalar

1. Faça backup do banco e valide a restauração.
2. Execute `deadline_migration_report.py` e salve o resultado.
3. Aplique a migration somente depois da revisão do relatório.
4. Instale as dependências:

   ```powershell
   C:\Users\usuario\AppData\Local\Programs\Python\Python313\python.exe -m pip install -r requirements-deadline-worker.txt
   ```

5. Confirme que a conta que executará o processo consegue chamar
   `deadlinecommand`, acessar os compartilhamentos de render e conectar ao banco.
6. Mantenha `DEADLINE_DELETE_DRY_RUN=1` na primeira ativação.

## Opção preferencial: NSSM

Em PowerShell elevado, execute:

```powershell
.\install_deadline_worker_service.ps1 -NssmPath C:\tools\nssm\nssm.exe
```

O instalador configura:

- Application: caminho de `python.exe`;
- Arguments: caminho absoluto de `deadline_worker.py`;
- Startup directory: pasta `Render`;
- início automático;
- reinício após falha com espera de 5 segundos;
- captura e rotação da saída do serviço.

Depois da instalação, execute `nssm edit FlowDeadlineWorker` e configure a aba
`Log on` com a mesma conta Windows que já acessa o repositório Deadline e os
compartilhamentos UNC. Não use `LocalSystem` se essa conta não tiver acesso à
farm. A senha não é gravada nos arquivos do projeto.

O serviço não é iniciado pelo instalador. Após a migration e a validação:

```powershell
.\start_deadline_worker.bat
```

Use `stop_deadline_worker.bat` e `restart_deadline_worker.bat` para operação.

## Alternativa: Agendador de Tarefas

Crie uma nova tarefa, sem reutilizar a tarefa periódica antiga:

- Executar independentemente de o usuário estar conectado;
- Executar com privilégios mais altos;
- Disparador: `Ao iniciar o sistema`, com atraso opcional de 30 segundos;
- Programa: caminho de `python.exe`;
- Argumentos: caminho absoluto de `Render\deadline_worker.py`;
- Iniciar em: caminho absoluto da pasta `Render`;
- Se a tarefa já estiver em execução: `Não iniciar uma nova instância`;
- Reiniciar a cada 1 minuto em caso de falha;
- Tentar reiniciar pelo menos 10 vezes;
- Não definir repetição a cada cinco minutos;
- Não encerrar a tarefa por duração máxima.

O lock de arquivo do worker oferece uma segunda proteção contra instâncias
duplicadas. O lock transacional da fila protege cada comando mesmo se duas
máquinas diferentes forem iniciadas por engano.

## Troca de produção

1. Inicie o novo worker em dry-run.
2. Confirme heartbeat e logs.
3. Confirme que jobs ativos são associados e sincronizados corretamente.
4. Confirme que comandos ficam `PENDENTE` em dry-run e nenhum job é excluído.
5. Desative a tarefa antiga `Script Render Deadline`.
6. Altere `DEADLINE_DELETE_DRY_RUN=0`.
7. Reinicie o worker e acompanhe os primeiros comandos até `CONCLUIDO`.

Não mantenha o monitor periódico e o worker contínuo sincronizando em paralelo.

## Parada e rollback operacional

1. Pare o serviço com `stop_deadline_worker.bat`.
2. Não apague linhas de `deadline_comandos` nem `render_tentativas`.
3. Se for indispensável reativar temporariamente o monitor antigo, defina
   `DEADLINE_WORKER_MODE=legacy`, `DEADLINE_LEGACY_MODE=1` e mantenha a exclusão
   direta desabilitada; ele não substitui a fila de comandos.
4. Reverter o código não reverte com segurança os dados históricos. Preserve as
   tabelas até uma decisão de migração reversa explícita.
5. O SQL de rollback estrutural só funciona enquanto as novas tabelas estiverem
   vazias.
