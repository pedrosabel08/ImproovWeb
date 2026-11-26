# Recuperação controlada de scripts EF via NAS

Esta preparação não altera o Deadline nem libera tarefas. O job com falha é
`6b083cd3e93a14bf6cb50272`, solicitação Flow 8, imagem 3107, snapshot 130.
O estado e o vínculo apresentados anteriormente podem ter mudado: confirme-os.

1. Consulte o Job, suas tarefas, relatórios e o vínculo Flow atual, inclusive
   `render_ef_id`, tentativa ativa e UUID. Confirme que não há tarefa renderizando,
   outra tentativa ativa nem saídas já produzidas. Não altere jobs em andamento.
   Preserve logs, dados do Job e histórico. Trabalhe somente com o Job suspenso
   e sem tarefas executando; confirme também a inexistência de cópias em execução.
2. Em ambiente isolado, execute `test_asset_scripts_maxbatch.py` com
   `FLOW_TEST_MAXBATCH` apontando para o 3ds Max Batch 2024. Homologue ambas as
   fases no plugin Deadline da mesma versão, sem usar o Job de produção. Valide
   acesso aos assets e evite produzir saídas de produção. Uma falha interrompe
   o procedimento.
3. Exporte, por acesso administrativo somente leitura e em arquivo privado,
   o `plano_json` integral da solicitação 8. Verifique origem NAS, snapshot,
   UUID e Job ID contra os registros; não use a projeção pública `plan` da API,
   pois ela não contém o plano completo nem o hash da cena. Preserve o hash do
   plano, o operador, data e hora. Confirme o SHA-256 do `.max` auxiliar e dos
   auxiliares originais com o plano e que a pasta destino continua segura.
4. Em máquina com acesso de leitura ao Repository, use uma pasta privada nova
   fora do Repository e execute, ajustando apenas os caminhos locais:

   ```powershell
   python Render/deadline_ef_script_recovery.py --plan 'C:\privado\plano-8.json' --job 6b083cd3e93a14bf6cb50272 --source '\\imp-frm-001\Deadline_Repository\DeadlineRepository10\jobs\6b083cd3e93a14bf6cb50272' --staging 'C:\privado\recuperacao-job-8'
   ```

   A ferramenta exige os dois scripts legados exatos do plano, guarda cópias
   originais, produz os novos scripts e um manifesto com hashes. Ela não verifica
   estado/vínculo do Job nem instala scripts. Confira o manifesto e armazene-o
   com logs, hash do plano, nome do operador e horário em registro restrito e
   durável. Se houver divergência, pare; não force a substituição.

5. A recuperação do mesmo ID é tecnicamente possível apenas se a infraestrutura
   confirmar um procedimento suportado para substituir auxiliares desse Job
   suspenso e invalidar eventuais cópias em cache, preservando metadados e histórico.
   A preparação não executa essa substituição. Após homologação e confirmação do
   procedimento, faça-a de forma controlada, com backup e auditoria dos hashes
   anterior e novo. Confira novamente Job, tarefas, arquivos, cena, plano e vínculo;
   scripts alterados devem corresponder exatamente ao gerador atualizado. Não
   requeue, não use ResumeJob e não acione Retomar nesta etapa.
6. Instale a correção no worker do Flow somente pelo procedimento de implantação
   aplicável. A ação Retomar pode registrar e **liberar** o mesmo Job: somente
   usá-la após autorização explícita para renderizar e conferência dos estados.
   Se o Job já foi vinculado ou estiver Failed, a recuperação das tarefas e a
   reconciliação Flow/Deadline exigem revisão operacional; não suponha que Retomar
   seja suficiente nem faça alterações manuais no banco.

Se não for possível garantir a substituição e sincronização segura, mantenha o
Job bloqueado e desenhe uma nova tentativa vinculada ao registro original através
de fluxo suportado e auditado. O fluxo atual impede outra submissão para a mesma
solicitação após registrar um Job; não limpe ID/UUID nem crie Job manualmente.
Não há neste procedimento criação automática de nova tentativa, e sua necessidade
depende da inspeção do estado real e da capacidade operacional da instalação.
