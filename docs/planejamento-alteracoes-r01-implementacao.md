# Planejamento R01+: fundação manual e evolução implementada

Última conferência documental: **29/10/2026**.

## Estado atual e leitura deste relatório

A fundação manual descrita nas seções A–G foi implementada em 28/10/2026 e sua migration foi aplicada com autorização. Essas seções registram aquela entrega inicial; não representam, isoladamente, todo o escopo atual.

Após essa fundação, o código recebeu a evolução documentada em [Planejamento R01+ por pontos e dia](planejamento-alteracoes-pontos.md), que é o detalhamento técnico da etapa mais recente. O estado atual já inclui:

- Pontos N1–N5 configuráveis, capacidade por colaborador/dia, exceções e elegibilidade por função principal/secundária.
- Agenda integrada aos compromissos R01+ e à ocupação R00, com tratamento explícito de disponibilidade desconhecida.
- Sugestão de distribuição, fixação de decisões, grade diária, drag/drop, backlog e miniaturas autenticadas.
- Snapshots de pontos/configuração, fingerprint adicional da capacidade e auditoria de sobrecarga confirmada.
- Consulta de planejado × executado por rodada, preservando baseline, versão vigente e evidências dos ciclos.
- Migration adicional `sql/2026-10-28_planejamento_alteracao_pontos.sql`, registrada como aplicada no relatório dessa evolução.

Na conferência de 29/10/2026 foram reexecutadas com sucesso **57 verificações acumuladas da suíte de pontos** e **66 verificações da interface com API sintética**, incluindo as duas ordens de complexidade e o desenho do orb. O relatório autenticado mais recente, [report.json](../output/planejamento-alteracao-readonly/report.json), registra **43 verificações aprovadas**, nenhuma falha nas verificações e nenhum erro JavaScript. Há um erro de recurso HTTP 404 registrado no console, sem falha nos cenários verificados. Esse relatório substituiu as evidências anteriores no mesmo caminho.

A auditoria atual abriu uma R01+ real pelo detalhe da entrega, consultou configuração/agenda e gerou sugestões nas duas ordens em memória, depois descartadas. **Isso valida leitura e interação reais; não comprova uma nova liberação e confirmação ponta a ponta em dados reais.** A confirmação e seus efeitos operacionais permanecem cobertos pelos testes em tabelas temporárias. O navegador interno continuou indisponível nas tentativas registradas; a auditoria utilizou Chrome automatizado externo.

As contagens 38/41/6 e os bloqueios de CDN descritos adiante são resultados históricos da primeira entrega. Para os resultados mais recentes e as limitações atuais, consultar a seção de validação de [planejamento-alteracoes-pontos.md](planejamento-alteracoes-pontos.md). O número de 14 cabeçalhos R00 e a tabela inicialmente vazia também descrevem o momento da aplicação da primeira migration, não uma contagem atual do banco.

## Registro da primeira entrega — 28/10/2026

Data: 28/10/2026. Escopo: fundação e planejamento manual por imagem, correspondentes às Fases 0, 1 e 2 do diagnóstico. Este relatório não altera o diagnóstico que já estava modificado no workspace.

## A. Resumo

Novas entregas R01–R05 criadas pela liberação da Pré-alteração recebem o modelo explícito `IMAGENS_ALTERACAO`. Elas nascem pendentes de planejamento, sem decisão de responsável herdada da tarefa reutilizada. A gestão decide responsável, conclusão e, opcionalmente, início por imagem. Pode salvar parcialmente, selecionar várias imagens, aplicar decisões em massa, limpar decisões, pesquisar e filtrar. A distribuição por colaborador mostra somente quantidades e níveis, sem estimativa de esforço.

A confirmação usa uma transação para bloquear entrega, cabeçalho, itens, origem da triagem, imagens, tarefas e colaboradores; validar versão e fingerprint; criar versão e itens históricos; preservar/criar baseline; aplicar responsáveis e registrar evento. O rascunho não altera tarefas. Novos ciclos de execução guardam o item da entrega e a versão confirmada. A execução e os writers legados não podem substituir silenciosamente o responsável confirmado.

## B. Arquivos e responsabilidades

| Arquivo                                                                                     | Responsabilidade                                                                                                                                                                      |
| ------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `helpers/planejamento_alteracao_helper.php`                                                 | Modelo, escopo por entrega, fingerprint, validação, leitura, rascunho e confirmação transacional por imagem. Reutiliza raiz, versões, baseline, JSON, histórico e eventos existentes. |
| `helpers/planejamento_alteracao_operacional_helper.php`                                     | Resolve a rodada pela liberação de destino; protege início/atribuição; associa ciclo ao item, prazo e versão.                                                                         |
| `PlanejamentoProducao/alteracao.php`                                                        | API GET/POST com autenticação, autorização de obra/gestão, CSRF e conflitos HTTP 409.                                                                                                 |
| `PlanejamentoProducao/index.php`                                                            | Despacho explícito de modelo; R01+ usa o template por imagem e dispensa carregamentos globais do R00.                                                                                 |
| `PlanejamentoProducao/alteracao-page.php`                                                   | Template autenticado por imagem, resumo, filtros, ações em massa e histórico. Acesso direto ao fragmento é recusado.                                                                  |
| `PlanejamentoProducao/alteracao.js`                                                         | Edição, seleção, distribuição, rascunho, confirmação, replanejamento, histórico e erros de concorrência.                                                                              |
| `PlanejamentoProducao/alteracao.css`                                                        | Temas claro/escuro e layout responsivo; lista com campos visíveis nos formatos estreitos.                                                                                             |
| `PlanejamentoProducao/history.php`                                                          | Histórico compartilhado com despacho por modelo; preserva o guard R00 para os demais planos.                                                                                          |
| `Entregas/get_entrega_item.php`                                                             | Expõe o modelo do planejamento no detalhe existente.                                                                                                                                  |
| `Entregas/script.js`                                                                        | Exibe “Planejar entrega” usando o `entrega_id` exato quando o modelo é por imagens.                                                                                                   |
| `includes/entrega-detalhe-modal.php`                                                        | Entrada no rodapé do detalhe compartilhado da entrega.                                                                                                                                |
| `PreAlteracao/conclusao_helpers.php`                                                        | Data canônica do retorno; prazo comercial existente; preparação/reset de tarefa com bloqueio de ciclo anterior ativo.                                                                 |
| `PreAlteracao/concluir_triagem.php`                                                         | Cria o planejamento na mesma transação da liberação, após itens e vínculos; mantém liberação parcial.                                                                                 |
| `helpers/alteracoes_helper.php`                                                             | Preserva recebimento e nível em upserts genéricos; atualização autoritativa da cópia operacional somente ao liberar nova rodada.                                                      |
| `Alteracao/updateStatusLote.php`                                                            | Proteção da alocação, início canônico com ciclo, pausa/retomada e envio para aprovação nas novas rodadas.                                                                             |
| `helpers/inicio_operacional_helper.php`                                                     | Proteção central do início, incluindo outros consumidores do serviço.                                                                                                                 |
| `helpers/janela_operacional_helper.php`                                                     | Prazo por imagem, vínculo de rodada/versão e preservação dos snapshots do ciclo na retomada; bloqueio de transferência divergente.                                                    |
| `insereFuncao.php`, `insereFuncao2.php`                                                     | Impedem sobrescrever decisões das novas rodadas pelos editores legados.                                                                                                               |
| `atribuir_flow_radar.php`                                                                   | Impede reatribuição que contorne o plano.                                                                                                                                             |
| `Dashboard/addRevisao.php`, `Dashboard/addRevisaoBatch.php`, `Dashboard/addRenderBatch.php` | Impedem avançar uma rodada gerenciada contornando sua liberação pela Pré-alteração.                                                                                                   |
| `sql/2026-10-28_planejamento_alteracao_manual.sql`                                          | Discriminador, rascunho, itens confirmados e identidade da rodada nos ciclos.                                                                                                         |
| `tools/apply_planejamento_alteracao_migration.php`                                          | Aplicação CLI explícita com preflight, bloqueio de execução concorrente e conferência das FKs/índices. Reexecução verifica o schema sem reaplicar DDL.                                |
| `tests/planejamento_producao/PlanejamentoAlteracaoTest.php`                                 | Regras puras de itens, datas e fingerprint.                                                                                                                                           |
| `tests/planejamento_producao/PlanejamentoAlteracaoIntegracaoTest.php`                       | Persistência real com tabelas temporárias copiadas do schema configurado.                                                                                                             |
| `tests/planejamento_producao/PlanejamentoAlteracaoOperacionalTest.php`                      | Políticas, datas, upsert, reset, resolução de rodada, ciclos e rollback.                                                                                                              |
| `tools/test_planejamento_alteracao_ui.cjs`                                                  | Teste de DOM/interação e responsividade com API sintética e Chrome real.                                                                                                              |
| `tools/audit_planejamento_alteracao_readonly.cjs`                                           | Navegação autenticada de leitura pelas URLs oficiais, menu e detalhe legado; registra limitações externas.                                                                            |

Os arquivos `Entregas/detalhes.css`, `docs/planejamento-alteracoes.md` e a exclusão em `Backup/` já estavam modificados antes desta implementação e não fazem parte dela.

## C. Banco e decisões

Migration aditiva, de aplicação única, dependente das migrations atuais de planejamento, Pré-alteração e Janela Operacional. Foi conferida contra os tipos do schema configurado. O schema consultado não possui triggers que atribuam automaticamente identidade de rodada aos ciclos.

- `entrega_planejamento_producao.modelo`: `FUNCOES_R00` por default; novas liberações de alteração gravam explicitamente `IMAGENS_ALTERACAO`.
- `rascunho_json` nullable no mesmo cabeçalho. Escolhida a alternativa A: a interface salva a unidade inteira; normalizar decisões incompletas traria uma segunda persistência editável sem benefício nesta etapa. Toda gravação bloqueia a raiz e incrementa `lock_version`.
- `entrega_planejamento_item`: filha da versão, com `UNIQUE(versao_id, entrega_item_id)`, referências fortes à versão, item da entrega, tarefa, triagem e responsável; início nullable, fim obrigatório e snapshot de nível. A aplicação também rejeita imagem duplicada na entrega e verifica obra, tarefa e origem da liberação.
- `janela_operacional_ciclo.entrega_item_id`: nullable, indexado e com FK; sem preenchimento artificial dos ciclos antigos. A referência já existente à versão permanece.
- Versões confirmadas e seus itens não são editados pelo serviço. Replanejar cria outra versão, mantendo a baseline e os snapshots anteriores.
- A coluna compartilhada `entrega_planejamento_versao.data_inicio` continua obrigatória: quando nenhum item tem início, recebe a data do recebimento apenas como referência do cabeçalho. Os inícios individuais permanecem NULL; nenhum início operacional é inventado.
- A coluna compartilhada `prazo_r00` recebe o prazo cliente também no snapshot por imagens, sem executar o cálculo R00 ou criar outro prazo paralelo. O significado é discriminado pelo modelo.

Não há backfill de planos, responsáveis, baseline ou prazos. Após autorização explícita do usuário em 28/10/2026, a migration foi aplicada ao banco configurado: três operações executadas, seis FKs e o índice único por versão/item verificados. Os 14 cabeçalhos existentes permaneceram `FUNCOES_R00`; a tabela de itens confirmados por imagem foi criada vazia. A reexecução do aplicador reconheceu a migration instalada e executou zero operações DDL. Evidência de conferência: `output/planejamento-alteracao-readonly/migration-activation.json`.

As escritas de testes funcionais continuam limitadas às tabelas temporárias da conexão, sem triggers, constraints externas ou alteração de registros reais. A estrutura está habilitada para as próximas liberações da Pré-alteração; a homologação autenticada de uma nova rodada real ainda não foi declarada concluída. A liberação falha integralmente se a estrutura do novo planejamento estiver ausente.

## D. Fluxo real

```text
Pré-alteração N1–N5
  PreAlteracao/conclusao_helpers.php
      ↓ retorno canônico + prazo comercial existente de sete dias úteis
Liberação, inclusive parcial
  PreAlteracao/concluir_triagem.php
      ↓ entrega + entregas_itens + liberação + tarefas + cabeçalho na transação
Planejar entrega (entrega_id explícito)
  includes/entrega-detalhe-modal.php → Entregas/script.js
      ↓ despacho por modelo
PlanejamentoProducao/index.php → alteracao-page.php / alteracao.js
      ↓ salvar rascunho / confirmar
PlanejamentoProducao/alteracao.php
  → flow_planejamento_alteracao_salvar()
      ↓ versão + itens + baseline + responsáveis + evento, atomicamente
Alterações
  Alteracao/updateStatusLote.php
  → flow_planejamento_alteracao_validar_operacao()
  → flow_inicio_operacional_iniciar()
  → flow_janela_criar_ciclo()
  → flow_planejamento_alteracao_vincular_ciclo()
```

Quem foi confirmado pode iniciar normalmente; outra pessoa recebe erro e precisa de uma decisão explícita da gestão por replanejamento. A troca durante ciclo ativo fica bloqueada nesta etapa; após encerramento do ciclo, a gestão pode confirmar outra versão. O rascunho de replanejamento não substitui a alocação da versão vigente.

## E. Compatibilidade e divergências verificadas

- O motor, grafo, durações e dependências R00 não foram alterados. O despacho da página seleciona outra experiência e outro serviço para `IMAGENS_ALTERACAO`.
- R01 antiga sem o novo discriminador mantém execução e autoatribuição legadas. Abrir uma entrega antiga não cria cabeçalho, decisão ou baseline.
- `pre_alt_lote.status=PLANEJADO` continua significando liberação da triagem; não é utilizado como evidência de produção planejada.
- A unidade é a entrega criada naquela liberação. Um lote de dez imagens pode liberar seis e produzir um plano com seis, mantendo quatro fora dele.
- Recebimento usa `pre_alt_lote.data_finalizacao_cliente`; na ausência de data válida, usa o retorno resolvido do Review/Cobrança já carregado no resumo. Se ambas faltarem, falha com orientação, em vez de substituir por triagem/hoje. Não atualiza entregas antigas.
- O código real repete a última etapa cadastrada depois de R05. Como a tabela legada `alteracoes` é identificada por tarefa + etapa, sua cópia operacional precisa poder receber o retorno/nível da nova liberação nessa situação. O parâmetro autoritativo é utilizado somente pela liberação; updates genéricos preservam os valores existentes. A história da nova rodada continua sendo a entrega, a triagem vinculada e o snapshot.
- Os triggers existentes não criam o vínculo de ciclo/rodada; ele foi implementado no serviço canônico, inclusive no início pelo lote de Alterações.
- A preparação de schema legado da Pré-alteração foi antecipada ao `begin_transaction`, evitando commits implícitos de DDL dentro da liberação.
- A página reutiliza o documento autenticado, o catálogo institucional, o helper de head e o interceptor central existentes. Não adiciona documento público, metadata privada ou bypass de autenticação. Loading reutiliza o Thinking Orbs global.

## F. Testes e resultados

| Verificação                                                                                       | Resultado                                                                                                                                                                                                                                                                                                                |
| ------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `PlanejamentoAlteracaoTest.php`                                                                   | 14 verificações passaram: dez pendentes, parcial, responsável/fim obrigatórios, datas inválidas, duplicidade, item externo e fingerprint.                                                                                                                                                                                |
| `FLOW_R01_TEMP_DB_TEST=1 php tests/planejamento_producao/PlanejamentoAlteracaoIntegracaoTest.php` | 38 verificações, incluindo as 14 puras: rascunho não atribui; NULL recebe responsável na confirmação; baseline; replanejamento; conflito de lock; contexto mudado; parcial com seis; R02 não herda decisão; falha tardia desfaz versão, baseline e atribuição; ausência de migration permite desfazer a criação inteira. |
| `PlanejamentoAlteracaoOperacionalTest.php --db`                                                   | Passou: datas canônicas/fallback, proteção do responsável, fluxo legado, N1–N5, recebimento, etapa repetida, R01/R02 e ciclos distintos, reset real e rollback; somente tabelas temporárias.                                                                                                                             |
| Dez suítes PHP preexistentes de `tests/planejamento_producao/*Test.php`                           | Todas passaram. Nenhum cálculo R00 foi alterado.                                                                                                                                                                                                                                                                         |
| `PlanejamentoInterfaceTest.cjs`                                                                   | Passou com Chrome instalado: estados R00, gráficos, tooltip, temas, preferência do sistema e mobile.                                                                                                                                                                                                                     |
| `tools/test_planejamento_alteracao_ui.cjs`                                                        | 41 verificações passaram com API sintética: edição, filtros, massa, limpar, salvar, confirmar, replanejar, conflito, histórico, pessoa inativa, autorização e cinco resoluções.                                                                                                                                          |
| `tools/audit_planejamento_alteracao_readonly.cjs`                                                 | Seis verificações passaram após a migration: login, menu Produção → Entregas, detalhe com imagens reais, link R01+ ausente para entrega legada, API R01+ preservando o legado e confirmação recusando CSRF inválido antes de mutação. Ambas as origens responderam 200; capturas em cinco resoluções.                    |
| Janela Operacional Policy/Architecture e UnidadeTrabalho                                          | Passaram.                                                                                                                                                                                                                                                                                                                |
| `tools/test_page_metadata.php`                                                                    | 811 verificações, sem falhas.                                                                                                                                                                                                                                                                                            |
| `tools/test_auth_navigation.php`                                                                  | 188 verificações, sem falhas.                                                                                                                                                                                                                                                                                            |
| Sintaxe PHP e JavaScript dos arquivos alterados; `git diff --check`                               | Sem erros.                                                                                                                                                                                                                                                                                                               |

Resoluções da UI nova: desktop, notebook, iPad landscape, iPad portrait e mobile. Screenshots sintéticos em `output/planejamento-alteracao-ui/`, incluindo light/dark. Esses testes comprovam interação/layout do código novo; não substituem a homologação da liberação real.

Uma suíte adicional, `tests/unidade_trabalho/StartRequirementPolicyTest.php`, falha na linha 30 por uma confirmação de pendências em `PaginaPrincipal/scriptIndex.js`. A mesma expressão está no HEAD anterior e esse arquivo não foi alterado nesta implementação.

O navegador interno não conseguiu abrir nenhuma das duas URLs oficiais, inclusive após a aplicação da migration: o host de acesso ao navegador estava indisponível antes de carregar o login. A URL HTTP respondeu 200 por HTTP, e as duas URLs responderam no Chrome automatizado externo. Na auditoria autenticada externa, o login respondeu sucesso, mas Toastify ausente impediu o redirect visual; o teste seguiu a `redirect_url` exata devolvida pelo servidor e navegou pelo menu real. Houve dependências externas preexistentes indisponíveis (`Toastify`, `flatpickr`, `$`), e a obra selecionada não apresentava o atalho de plano R00 existente; não se criou plano para habilitar o atalho. Relatório: `output/planejamento-alteracao-readonly/report.json`. A regressão UI R00 foi executada separadamente com fixtures. Nenhuma entrega existente foi convertida ou confirmada para forçar a validação: a homologação de uma nova R01+ real continua distinta dos testes em tabelas temporárias e das fixtures da interface.

Comandos de UI podem usar o Playwright já instalado, sem adicionar dependências à aplicação:

```powershell
$env:FLOW_PLAYWRIGHT_MODULE = 'C:/Users/usuario/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'
$env:FLOW_QA_BROWSER = 'C:/Program Files/Google/Chrome/Application/chrome.exe'
node tests/planejamento_producao/PlanejamentoInterfaceTest.cjs
node tools/test_planejamento_alteracao_ui.cjs
```

## G. Escopo que ficou para depois da primeira entrega

Na primeira entrega ficaram fora do escopo os pesos N1–N5, a capacidade quantitativa e as sugestões; esses três grupos já foram implementados na evolução por pontos e dia. Também foi adicionada a consulta de planejado × executado por rodada. Portanto, esses itens não devem ser lidos como pendências atuais.

Continuam fora do motor atual: IA/aprendizado e recalibração automática, EF/Render, Gantt R01, dashboard analítico avançado, gestão de ausências individuais por período e experiência completa de transferência durante ciclo ativo. A homologação de uma nova liberação com confirmação real continua distinta da auditoria de leitura e dos testes isolados.
