# Render em alta por snapshot do job aprovado

Entrega para homologação. **Nenhuma migration aplicada no banco real, nenhum
worker operacional reiniciado e nenhum job submetido, interrompido ou excluído.**
As três flags de ativação continuam desativadas quando ausentes.

## Diagnóstico e decisão

A descoberta existente resolve o nome do job para uma imagem e só vincula quando
há correspondência inequívoca: primeiro render ou uma única tentativa aguardando.
Ambiguidades continuam registradas e não viram snapshots de imagens por aproximação.
A captura nova parte exclusivamente de `render_tentativas.deadline_job_id`, depois
desse vínculo, nunca de todos os jobs da farm.

A aprovação usa `render_alta.status='Aprovado'` e o Job ID vinculado à tentativa.
Tentativas aprovadas podem já estar `ENCERRADA` após DeleteJob: o marcador
`motivo_encerramento='APROVADA_NO_FLOW'` preserva essa evidência. Também são aceitas
tentativas com estado `APROVADA`. O cache Job ID do render, quando presente, deve
corresponder à tentativa escolhida. Zero ou múltiplas correspondências bloqueiam;
não há `ORDER BY data DESC LIMIT 1` para escolher a configuração aprovada.

O mecanismo legado exportava um job excluído a cada preparação e dependia de seu
arquivo auxiliar. O caminho NAS anterior abria Max Batch e selecionava câmeras e
perfis. Foram reaproveitados: worker/monitor, permissões e CSRF, identificação de
tentativas, NAS allowlisted, fingerprint, UUID/reserva por imagem, destinos
exclusivos, criação Suspended, vínculo transacional antes de ResumeJob, reconciliação
e verificação de saídas. A recuperação legada de solicitações JOB existentes permanece.

Foram retirados do fluxo: execução Batch local/remota, jobs CommandLine, launcher,
protocolo/relatório de inspeção, allowlist/perfis de inspeção, seleção manual de câmera
e polling de inspeções. `deadline_inspection.py` contém **somente o filtro** de jobs
legados, necessário para que nunca sejam interpretados como renders. `inspect_scene()`
é um guard que lança erro, sem iniciar processo. O script de inspeção e seu perfil
foram removidos. O smoke test local foi desativado.

## Captura e persistência

No ciclo da fila do worker existente, `SnapshotCapture` procura tentativas vinculadas
ainda sem snapshot. A descoberta continua com o intervalo existente (30 s por padrão);
a captura é despachada pelo ciclo da fila (3 s quando ociosa). Um executor IO com
concorrência 1 exporta configurações/copia auxiliares; o banco permanece na thread
principal. Hash do `.max` na preparação também roda em thread IO, sem abrir a cena.

O SDK `RepositoryUtils.GetJob`/`GetDeletedJob` e `CreateJobSubmissionFiles` exporta Job
Info e Plugin Info sem restaurar o job. `GetJob`/`GetDeletedJob` fornece dados adicionais.
**Verificado no Deadline 10.4.2.2 real, somente leitura:** exportação ativa funcionou;
o SDK omitiu `ChunkSize` em um job e `GetJob` o expôs como `1`. Esse campo é preenchido
somente a partir do valor recuperado, com origem registrada. `GenerateSubmissionInfoFiles`
também está disponível na instalação. Nenhum comando de submissão é usado na captura.

Tabelas novas:

| Tabela | Conteúdo |
|---|---|
| `render_job_snapshots` | Um registro imutável por Job ID/tentativa, imagem, formato, origem, estado, conteúdo completo, hash, resumo e data |
| `render_job_snapshot_capturas` | Fila persistente, reserva, prioridade, contador, erros e próxima tentativa |
| `render_job_snapshot_eventos` | Histórico de capturas/falhas |

O conteúdo histórico só é inserido; nunca recebe UPDATE/DELETE pelo aplicativo.
UNIQUE por Job ID e tentativa evita duplicação. Um recapture não sobrescreve o primeiro
snapshot. Ao consumir, conferir hash SHA256, formato e IDs. Restringir a conta de banco
a SELECT/INSERT na tabela histórica quando a administração provisionar permissões.
Administradores com privilégios de alteração continuam sendo parte do limite de confiança.

Cada conteúdo armazena os dicionários completos, arquivos exportados em Base64 para
preservar os bytes originais, texto exportado, resposta bruta do Deadline, metadados,
campos recuperados por fonte e referências/hashes de auxiliares. `normalizado_json`
contém câmera/renderer/versão/resolução quando disponíveis, parâmetros da fila e
lista de campos ausentes. `CAPTURADO` não significa que todas as informações para
alta estejam disponíveis: a preparação ainda valida cada campo necessário.

Auxiliares não primários são copiados para pasta UUID exclusiva sob
`DEADLINE_SNAPSHOT_ROOT`, UNC privado fora da árvore web, com hash/tamanho conferidos
antes/depois. São preservados todos os auxiliares declarados além da cena principal;
o `.max` original é identificado, mas a alta usa o `.max` final confirmado. Binários
não vão ao MySQL. Ausência ou mudança de auxiliar impede selar a captura e gera erro.
O compartilhamento exige ACL somente para contas autorizadas, espaço e backup.
Pastas órfãs de capturas interrompidas devem ter retenção administrativa, sem remover
arquivos referenciados por snapshots históricos. Nenhuma limpeza automática foi criada.

Reservas `CAPTURANDO` interrompidas são recuperadas após 15 min. Falhas têm backoff
de 60 s, até cinco tentativas; eventos mantêm o diagnóstico. Uma solicitação de alta
sem snapshot prioriza sua captura; após três falhas, apresenta bloqueio específico.
Intervenção controlada pode reabrir a captura após corrigir o ambiente, sem alterar
snapshots existentes. Não existe reenvio de render nessa recuperação.

Risco inevitável: exclusão/purge externo antes da primeira captura pode eliminar
configuração/auxiliares. DeleteJob normal continua funcionando; o exportador tenta
também os excluídos. Não foram instalados event plugins nem alterada a rotina do
Submitter/farm. Para reduzir a janela, habilitar captura antes do uso da alta e
manter retenção suficiente de jobs excluídos. Não há recuperação por inspeção.

## Preparação e reprodução

1. O colaborador abre o render aprovado e escolhe **explicitamente** um registro
   `.max` da própria imagem. Confirma arquivo e dimensão de alta (5000 px na UI).
2. A solicitação registra `arquivo_log_id`, autor/data da confirmação, UUID e
   tentativa aprovada. O worker revalida vínculo, obra, upload concluído e hash.
3. Preferir snapshot persistido. Se ausente, agendar a captura do Job ID comprovado,
   inclusive excluído. Se não recuperável, bloquear. Nunca abrir Max como fallback.
4. Copiar Job/Plugin Info do snapshot. Manter câmera, renderer, Max, frames, fila e
   configurações técnicas. Campos ausentes não são inventados: câmera/renderer sem
   override continuam a cargo da cena, com indicação de indisponibilidade na interface.
5. Usar RenderWidth/RenderHeight do snapshot. Se ausentes, solicitar largura e altura
   comprovadas, confirmação explícita e registrar autor/data/fonte. Não inferir 16:9
   nem usar dimensões de thumbnail. 1920×1080 → 5000×2813, com arredondamento ao pixel.
6. Usar cópia isolada do `.max` confirmado como primeiro auxiliar. Restantes vêm do
   arquivo histórico e têm hashes revalidados. Não é preciso encontrar o job antigo.
7. Alterar nome/identidade, resolução e saídas exclusivas com UUID; `SaveFile=true`
   garante escrita da saída. Missing files/DLLs/XRefs nunca são ignorados. Qualidade
   Corona e demais parâmetros permanecem preservados. OnJobComplete=Nothing mantém
   o novo job disponível ao monitor; dependências/hooks que exijam revisão bloqueiam.
8. Scripts de sessão PreLoad/PostLoad preservam a pasta da cena final para assets
   relativos sem salvar cena/INI. Scripts originais só são encadeados quando seus
   auxiliares foram preservados e seu hash foi revisado pela infraestrutura. Jobs
   MAXScript e hooks de job/tarefa não revisados bloqueiam a reprodução automática.
9. Criar Suspended, conferir parâmetros, cena e todos os auxiliares copiados ao
   Deadline; vincular a tentativa no Flow e só então liberar o mesmo Job ID. Metadados
   `FlowJobType=HighResolution`, `FlowSnapshotID`, `FlowRenderEFRequest` e
   `FlowSubmissionUUID` mantêm rastreabilidade. Resposta perdida usa reconciliação,
   nunca segunda submissão presumida.

Exemplo anonimizado baseado nos campos da exportação real:
[`tests/fixtures/deadline_snapshot_v1.json`](tests/fixtures/deadline_snapshot_v1.json).
Identificadores, nomes, câmera, paths e valores de teste foram substituídos. Exemplo
de reconstrução demonstrado pelos testes: Camera_aprovada/Corona:Corona, Max 2024,
1920×1080, frame 0, still/farm_dedicated → mesma configuração, `.max` final confirmado,
5000×2813, destino `renders2/QA_TEST/EF/flow_<imagem>_<UUID>`, novo job suspenso.

Limitações: exportação não é inspeção do conteúdo do `.max`. Não comprova que o
arquivo final manteve câmera, materiais ou dependências externas do aprovado. A
responsabilidade da versão final permanece na confirmação do colaborador; erros
reais do Max/renderer serão tratados pelo Deadline e monitor existentes. Não é
possível reconstruir fielmente um job purgado antes de preservar seus parâmetros.

Caso indicado: o upload `5.KMK_COS-Espaco-IMG-ALT-EF.max` foi encontrado como concluído.
O Job ID da tentativa aprovada R01 já não estava disponível entre ativos/excluídos.
Sem snapshot anterior, o fluxo real permanece bloqueado, mesmo com o `.max` presente.
A demonstração da UI usa somente fixtures em sessão, claramente marcadas SIMULAÇÃO.

## Arquivos e implantação pendente

Novos: `deadline_snapshots.py`, `deadline_snapshot_export.py`, esta documentação,
fixture/testes de snapshots e `sql/2026-11-06_deadline_job_snapshots.sql`.
Integração: `deadline_worker.py`, `deadline_render_ef.py`, `deadline_nas_submission.py`,
`deadline_scene.py`, `deadline_repository.py`, filtro legado `deadline_inspection.py`,
`render_ef.php`, `render_ef_helpers.php`, `render-ef.js`, `index.php`, testes e router QA.
O filtro de inspeções em `deadline_monitor.py` permanece para compatibilidade segura.

Ordem das migrations, **somente em janela autorizada**, com backup e worker parado:

1. `2026-10-20_render_ef.sql`, se pendente.
2. `2026-11-06_render_ef_nas.sql`, se pendente: ainda necessária para seleção de
   arquivo, UUID, reserva e auditoria. Não habilita inspeção.
3. `2026-11-06_deadline_job_snapshots.sql`: tabelas acima e referência ao snapshot,
   tentativa aprovada e confirmação do arquivo em `render_ef_solicitacoes`.

A migration proposta de inspeções remotas não faz parte da entrega final. Não aplicar.
Não apagar eventuais dados de versões anteriormente implantadas; inspecionar esses
estados na janela operacional. O código não retoma nem submete inspeções legadas.
Publicar os arquivos juntos; heartbeat da nova versão **2.4**. A API exige essa
capacidade para novas preparações, evitando que versões antigas consumam o novo fluxo.

| Variável | Inicial / finalidade |
|---|---|
| `DEADLINE_SNAPSHOT_CAPTURE_ENABLED` | `0`: captura automática desligada |
| `DEADLINE_EF_SNAPSHOT_ENABLED` | `0`: preparação/submissão nova por snapshot desligada |
| `DEADLINE_EF_SUBMIT_ENABLED` | `0`: criação/liberação real de renders desligada |
| `DEADLINE_SNAPSHOT_ROOT` | UNC privado de auxiliares históricos, fora da web |
| `DEADLINE_SNAPSHOT_REVIEWED_SCRIPTS` | JSON privado no ambiente, `{ "<sha256>": true }` somente para scripts auditados |

Flags antigas de inspeção não têm efeito. Perfis de inspeção/render genéricos não
são usados para fabricar configurações ausentes.

## Homologação sem afetar a farm

1. Em banco isolado, aplicar migrations e executar testes com mocks de submissão.
   O banco real continua intocado. Não iniciar `deadline_worker.py` operacional.
2. Obter uma exportação somente leitura de job existente, anonimizar uma fixture e
   conferir Job Info, Plugin Info, fontes e auxiliares. Isso já foi feito nesta sessão.
3. Persistir fixture no banco de teste vinculada à tentativa aprovada, simular job
   indisponível e reconstruir a alta somente do snapshot. Conferir hashes e proporção.
4. Usar `tests/ui_router.php` somente no PHP `cli-server`, pela URL oficial
   `http://localhost:8066/ImproovWeb/`. Login e CSRF são reais; a API EF grava apenas
   fixtures em sessão e nunca chama Deadline. O painel de QA permite concluir o envio apenas na fixture, sem criar job real.
5. Ensaiar snapshot ausente, dimensão desconhecida, arquivo não confirmado/alterado,
   auxiliares ausentes, reinício, resposta perdida e falha no vínculo. Conferir que
   não há processos Max/inspeção e nenhum job real.
6. Para produção, obter autorização específica de migrations/publicação/reinício.
   Provisionar ACLs/backup e habilitar primeiro apenas captura. Conferir snapshots
   sem mudar o Submitter. Depois habilitar preparação e revisar planos com envio real
   ainda desligado. Um render real exige outra autorização explícita.

Testes SQL são opt-in: `FLOW_EF_DATABASE_TESTS=1`, conexão exclusiva via
`FLOW_EF_TEST_HOST/PORT/USER/PASSWORD`; criam/removem somente schema aleatório
`flow_ef_test_*`. Usar `python -B -m unittest discover -s Render/tests -p 'test_*.py' -q`.
Não executar com credenciais/banco de produção.

Resultado desta entrega: **83 testes aprovados, sem skips**, incluindo MariaDB
descartável e endpoint PHP real contra fixtures. Sintaxe Python/PHP/JavaScript e
`git diff --check` sem erros. Modal validado pelo navegador interno em 1440×900,
1366×768, 1024×768, 768×1024 e 390×844, sem overflow horizontal. Foram demonstrados
snapshot preservado com job indisponível, bloqueio sem snapshot e confirmação
controlada de proporção portrait (1200×1600 → 3750×5000). Title, favicon, Open Graph
e Twitter Card continuam únicos no HTML. O envio real ficou desabilitado na simulação; o painel de QA permite concluir somente a fixture em sessão.

Referências oficiais:
[comandos de jobs](https://docs.thinkboxsoftware.com/products/deadline/latest/1_User%20Manual/manual/command-line-arguments-jobs.html),
[SDK RepositoryUtils 10.4](https://docs.thinkboxsoftware.com/products/deadline/10.4/2_Scripting%20Reference/class_deadline_1_1_scripting_1_1_repository_utils.html).

O painel `tests/snapshot_test_panel.html`, injetado apenas pelo router CLI de QA, permite reiniciar três cenários (snapshot disponível, sem resolução e sem snapshot). A ação Enviar conclui somente a simulação em sessão e não chama Deadline.
