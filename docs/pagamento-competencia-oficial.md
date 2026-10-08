# Pagamentos com competência oficial — entrega de 07/10/2026

Atualização posterior: [ajustes de adendo, sábado e retiradas](pagamento-ajustes-adendo-retiradas.md). A previsão vigente de setembro é **06/10/2026**; os registros abaixo de 07/10 descrevem a entrega inicial e o alerta já enviado. Não representam um novo envio.

O fluxo implementado é **Produção → Fechamento mensal → Valor reconhecido → Pagamento → Quitação**, a partir de **setembro/2026**. Revisado continua significando confirmar o PDF da revisão mensal vigente. As regras operacionais de produção e os lançamentos históricos foram preservados.

## Análise e reaproveitamento

| Ponto solicitado | Estrutura encontrada e decisão |
|---|---|
| Fluxo anterior | Visão Geral e Por colaborador consultavam tarefas elegíveis e o ledger. Fechamento já consolidava regras mensais por colaborador, gerava revisões e confirmava PDFs, mas faltava um cabeçalho oficial da competência e sua conclusão coletiva. |
| Participação | Já existe `colaborador.participa_fechamento_mensal`, junto com remuneração e valor fixo. Reutilizada a seleção existente de ativos explicitamente participantes, sem inferir participação por produção. |
| Fechamentos individuais | Reutilizados `pagamento_fechamento`, `pagamento_fechamento_revisao`, `pagamento_fechamento_decisao`, `pagamento_fechamento_operacao` e `pagamento_fechamento_documento`, incluindo unicidade por pessoa/competência e confirmação dos bytes do PDF. |
| Valores e pagamentos | Reutilizados `FechamentoMensalRules`, composição/revisão/documentação existentes, `PagamentoService`, `pagamentos`, `pagamento_itens` e `pagamento_eventos`. Não foi criado outro ledger de dinheiro. |
| Banco necessário | Cinco tabelas aditivas de competência, participantes, responsáveis, auditoria e vínculos ao ledger. `DESCONTO` acrescentado aos enums existentes de decisões/operações. Doze gatilhos protegem identidade, participantes, valores, vínculos e liquidação. |
| Modelo oficial | Cabeçalho único por competência, participantes capturados, referência à revisão/PDF e valor de cada pessoa. Conclusão com 100% revisados congela essas referências, a reconciliação e o total geral. |
| KPIs e gráficos | Uma projeção compartilhada usa o mesmo snapshot oficial. Em andamento, somente o parcial revisado é apresentado como parcial, sem liberar pagamento ou gráfico financeiro definitivo. |
| Pendências | Duas entradas em `checklist_operacional` e `checklist_operacional_item`, com item automático. Responsáveis capturados pelos IDs reais: Pedro **21**, André L. **9**, Giovana **43**. |
| Slack | Reutilizados eventos, notificações, entregas e `SlackApiAdapter` do FlowConnect, com `SLACK_WEBHOOK_CONTRATOS_URL`. Nenhum webhook paralelo. |
| Calendário | `pagamento_previsao()` centraliza a previsão e chama `entregas_adicionar_dias_uteis()`, em `Entregas/prazo_entrega_helper.php`. Usa os fins de semana e feriados já definidos nesse calendário. |
| Idempotência | Índices únicos, transações com bloqueio geral antes dos individuais, chaves de requisição com hash, outbox/entrega únicos e `flock` no cron. |

Competência e datas são distintas: setembro/2026, criação em outubro, previsão em **07/10/2026**, data efetiva informada ao registrar a quitação. O calendário existente não ganhou uma segunda lista de feriados.

## Regras financeiras

- **Total fechado:** soma dos valores individuais congelados na conclusão. É `NULL` enquanto a competência estiver em andamento.
- **Pago:** soma de `pagamento_itens.valor` vinculados aos fechamentos individuais pela tabela `pagamento_competencia_lancamento`. Valores são tratados em centavos.
- **Pendente:** Total fechado menos Pago.
- **Progresso:** colaboradores integralmente pagos / participantes capturados. Um pagamento histórico parcial pode aumentar Pago sem marcar a pessoa como paga.
- **Componentes:** fixo, serviços/adendos, bônus/extras, acompanhamento especial e descontos vêm da revisão mensal. Desconto manual exige valor válido e motivo, gera nova revisão e aparece no PDF.
- **Pessoa paga:** registra o saldo integral restante, data, usuário e observação no ledger existente e atualiza a situação financeira das funções do snapshot. Não altera o status de produção da tarefa. FIXO sem tarefas também pode ser quitado.
- **Sem novo valor monetário:** a quitação preserva as datas já existentes no cabeçalho do pagamento. Se há saldo efetivamente registrado, a data passa a representar a liquidação desse saldo.
- **Valor zero:** exige confirmação explícita de quitação para contar a pessoa no progresso, sem fabricar lançamento monetário.
- **Competência quitada:** somente 100% dos participantes pagos. O último pagamento encerra automaticamente a competência e a pendência. O endpoint de conclusão do pagamento também verifica todos os participantes.
- **Seleção de pessoas:** nas competências novas, a lista da aba Por colaborador vem dos participantes capturados, com os nomes históricos, inclusive inativos. Ao trocar para um mês legado, a seleção anterior é restaurada.
- **Snapshot:** alterações posteriores em tarefa, cadastro ou preço não recalculam uma competência concluída. Novas revisões, descontos e confirmações incompatíveis ficam bloqueados após a conclusão. Não foi implementada reabertura.
- **Revisão removida:** uma nova revisão antes da conclusão invalida a confirmação anterior para o fechamento coletivo e registra o evento correspondente.
- **Histórico documental:** PDF confirmado deve corresponder ao valor reconhecido. Se houver pagamento histórico ainda ausente do PDF, a conclusão exige atualizar a revisão e confirmar o novo documento, preservando os documentos anteriores.

Todas as mutações exigem gestor autenticado, CSRF e chave idempotente. O usuário vem da sessão, não do corpo enviado pelo cliente. Tentativas prematuras retornam mensagem com a quantidade pendente. Os endpoints financeiros antigos bloqueiam lançamentos pelo caminho legado nas competências do novo fluxo quando executados com esta versão do código.

## Histórico e estado real na entrega

A migration `sql/2026-10-07_pagamento_competencia.sql` foi aplicada no banco compartilhado. As alterações de enum foram precedidas por backup privado das duas tabelas existentes. A criação dos gatilhos pela conexão administrativa SSH e a criação do ciclo de setembro foram expressamente autorizadas.

O ciclo de setembro tem **18 participantes capturados**, **1 revisado**, parcial revisado de **R$ 4.400**, estado **Em andamento / Aguardando fechamento**, previsão **07/10/2026** e total oficial ainda indefinido. Foram reutilizados os 18 fechamentos individuais, 32 revisões e 27 documentos existentes. Não foram confirmados PDFs nem registrados novos pagamentos reais durante os testes.

O pagamento de Adriana, colaboradora **14**, cabeçalho **209**, mantém **64 itens e R$ 3.840**. A projeção identifica esse pagamento histórico. Os vínculos oficiais são gravados atomicamente na conclusão geral, junto com o congelamento financeiro, sem recriar o pagamento. Se o valor e todas as funções já estiverem quitados, a pessoa é reconhecida como paga nesse momento. Antes disso, a interface distingue o histórico a reconciliar do pagamento oficialmente liberado.

A auditoria final preservou os contadores históricos: **198 pagamentos, 3.846 itens**, ledger total **R$ 427.390**. Não foram gerados ciclos anteriores a setembro automaticamente. Meses anteriores continuam pela projeção legada.

## Automações e implantação

O novo job foi instalado **somente como pacote financeiro privado no servidor**, conforme a escolha feita pelo usuário. O código de interface/endpoints desta entrega está no workspace e foi validado nas URLs oficiais de desenvolvimento. **As páginas públicas do servidor não foram publicadas nesta etapa.**

- Entrada CLI: `scripts/pagamento_competencia_diario.php`.
- Cron novo: `/etc/cron.d/improov-pagamento-competencia`.
- Frequência: a cada **15 minutos**, com `flock` para impedir sobreposição.
- Pacote: `/root/improov-pagamento-competencia/current`, versão `v20261007_80ad024e3d86`.
- Configuração privada: `/root/improov-pagamento-competencia/job.env`, permissões 600, carregada por `PAGAMENTO_JOB_ENV`.
- Log: `/root/improov-pagamento-competencia/runner.log`.
- Comando: `/usr/bin/flock -n /root/improov-pagamento-competencia/runner.lock /usr/bin/php /root/improov-pagamento-competencia/current/scripts/pagamento_competencia_diario.php --entregar`.
- O cron e os demais workflows existentes foram preservados. O serviço `cron` foi verificado ativo.

Em cada execução, o job assegura o ciclo do mês anterior, com criação única e preparação automática somente de participantes sem revisão. Não sobrescreve revisões ou documentos preexistentes. No **dia 1**, publica `pagamento.competencia.fechamento`. Na **data prevista**, publica `pagamento.competencia.pagamento`, com valores oficiais ou alerta de bloqueio, conforme o fechamento.

Não existem envios retroativos do alerta do primeiro dia. O CLI real não aceita uma data fictícia para provocar alertas. Os testes de calendário usam um banco sintético e FlowConnect em `shadow`.

Hoje, 07/10/2026, o alerta real de fechamento pendente foi entregue pelo webhook autorizado: evento **114257958**, entrega **13627**, estado **SENT**. Execuções repetidas reutilizaram o evento e retornaram entregas vazias, sem reenviar.

O webhook não oferece confirmação idempotente de uma entrega que sofreu timeout após possível aceite. Para evitar reenvio incerto, falhas financeiras ambíguas e claims expirados vão para erro/dead letter, sem retry automático. Uma resposta 429 permite retry seguro. A fila existente registra essas situações para análise.

Além do CLI próprio, `FlowConnect/workers/operational_scheduler_worker.php` recebeu um hook financeiro protegido por feature flag e existência do schema. `delivery_worker.php` reconhece o tratamento de falhas dos eventos financeiros. A infraestrutura compartilhada mantém o comportamento anterior para os outros módulos.

## Banco e auditoria

| Tabela nova | Finalidade |
|---|---|
| `pagamento_competencia` | Competência única, criação, responsável, previsão, conclusão, total congelado, instante da quitação. |
| `pagamento_competencia_colaborador` | Participantes capturados, nome/remuneração/fixo do cadastro, revisão oficial, revisão/usuário/data, total, reconciliação e pagamento/usuário/data. |
| `pagamento_competencia_responsavel` | IDs dos três responsáveis capturados para o ciclo. |
| `pagamento_competencia_lancamento` | Referência única ao item do ledger existente e ao fechamento individual. Não duplica o valor monetário. |
| `pagamento_competencia_evento` | Auditoria imutável com usuário, timestamp, registro, chave/hash e valores anteriores/novos. |

Os eventos incluem criação, revisão, remoção de revisão, conclusão, pagamento individual e quitação coletiva. Decisões manuais continuam nos journals existentes. `log_alteracoes` exige referência operacional à tarefa, por isso os eventos de competência usam auditoria financeira própria, sem fabricar tarefas para registrar logs.

## Endpoints

| Endpoint | Comportamento |
|---|---|
| `Pagamento/api/fechamento/competencia.php` | Novo GET de resumo e POST `concluir`, `pagar`, `quitar`, com regras e idempotência no backend. |
| `Pagamento/api/fechamento/mensal.php`, `iniciar.php`, `preparar.php`, `decidir.php` | Rotas existentes reutilizadas por `FechamentoHttp` e serviços mensais, agora integradas ao ciclo capturado e aos descontos. |
| Rotas documentais em `Pagamento/api/fechamento/` | Geração/listagem/visualização/confirmar preservadas, com sincronização de revisão e guarda de competência concluída. |
| `getVisaoGeral.php` | Reutilizado via `resumo_geral.php`, que passa a usar a projeção oficial a partir do corte. |
| `getResumo.php`, `getColaborador.php`, `dados_grafico.php` | Consulta oficial nas competências novas, legado nos meses anteriores. |
| `updateStatusPagamento.php` / writers que usam `financeiro_v2.php` | Impedem pagamento da competência nova pelo fluxo antigo. |
| `PaginaPrincipal/update_checklist_operacional.php` | Impede conclusão manual de checklist financeiro. |

## Arquivos desta evolução

Novos:

- `sql/2026-10-07_pagamento_competencia.sql`, `sql/validacao_pagamento_competencia.sql`.
- `helpers/pagamento_competencia_helper.php`, `helpers/pagamento_pendencias_helper.php`.
- `Pagamento/services/FechamentoCompetenciaService.php`, `FechamentoCompetenciaAutomacao.php`, `FechamentoCompetenciaNotificacoes.php`.
- `Pagamento/api/fechamento/competencia.php`, `Pagamento/resumo_competencia.php`, `Pagamento/competencia.js`.
- `scripts/deploy_pagamento_competencia.php`, `deploy_pagamento_competencia_cron.php`, `pagamento_competencia_diario.php`, `audit_pagamento_competencia.php`, `inspect_pagamento_competencia_servidor.php`, `validar_pagamento_competencia.php`.
- `tests/pagamento_competencia_test.php`, `pagamento_competencia_worker.php`, `competencia-browser.php`, `pagamento_competencia_browser_quitar.php`.
- Este documento e evidências em `output/ui/competencia/`.

Integrações alteradas/reaproveitadas no código mensal já existente no workspace:

- `Pagamento/services/FechamentoComposicaoRepository.php`, `FechamentoMensalRules.php`, `FechamentoRevisaoService.php`, `FechamentoDocumentoService.php`, `FechamentoDocumentoProjection.php`, `FechamentoInterfaceService.php`, `AdendoDocumentalApresentacao.php`.
- `Pagamento/api/FechamentoHttp.php`.
- `Pagamento/index.php`, `script.js`, `animacoes.js`, `visao-geral.js`, `visao-geral.css`, `fechamento.php`, `mensal.js`, `mensal.css`.
- `Pagamento/resumo_geral.php`, `getResumo.php`, `getColaborador.php`, `dados_grafico.php`, `financeiro_v2.php`, `updateStatusPagamento.php`.
- `helpers/pendencias_operacionais_helper.php`, `PaginaPrincipal/update_checklist_operacional.php`.
- `FlowConnect/config/events/immediate_legacy.php`, `application/EventPlanner.php`, `infrastructure/DeliveryRepository.php`, `workers/delivery_worker.php`, `workers/operational_scheduler_worker.php`.
- `scripts/diagnostico/ComparacaoComposicaoFinanceira.php`: aceita o hash auditado do gerador documental já existente, preservando o golden financeiro da regressão.

O workspace já continha alterações de outras entregas, inclusive o módulo mensal e o cadastro de participação. Esta lista identifica a integração desta evolução, sem atribuir todos os itens de `git status` a ela.

## Testes e consultas executáveis

O teste completo cria um banco exclusivo `pagamento_1cb_test_*` em MySQL 8 **loopback, porta 3320**, usa dados sintéticos e FlowConnect em `shadow`. Precisa do MySQL de homologação já provisionado e das dependências PHP existentes. Não usa o banco compartilhado para registrar pagamentos de teste.

```powershell
php tests/pagamento_competencia_test.php
php tests/fechamento_mensal_v1_rules_test.php
php tests/fechamento_mensal_v1_integration_test.php
php scripts/audit_pagamento_competencia.php 2026-09
php scripts/validar_pagamento_competencia.php 2026-09
php scripts/inspect_pagamento_competencia_servidor.php
```

Os três últimos comandos são somente de leitura. O SQL equivalente está em `sql/validacao_pagamento_competencia.sql`: ajuste `@competencia` e execute no banco. Consultas de inconsistência devem retornar zero linhas. O cabeçalho em andamento conserva `total_fechado_centavos = NULL`, mesmo já tendo registros individuais revisados.

Para homologar a interface com valores quitáveis, sem tocar em pessoas reais:

```powershell
php tests/pagamento_competencia_test.php --browser-ready
```

Abra primeiro `http://localhost:8066/ImproovWeb/`, faça o login de desenvolvimento e acesse a rota isolada `/ImproovWeb/tests/competencia-browser.php`. Ela tem sessão separada, funciona somente em loopback nessa URL oficial e transporta os endpoints reais para o banco sintético. A fixture deixa 7/7 PDFs confirmados, mas aguarda a conclusão geral no navegador. Depois de concluir e testar um pagamento individual, pode completar os demais dados sintéticos com:

```powershell
php tests/pagamento_competencia_browser_quitar.php
```

Atualize a Visão Geral sintética: esperado R$ 37.895 fechado/pago, zero pendente, 7/7 e Quitado. As duas pendências sintéticas devem estar concluídas.

## Roteiro manual completo

| Etapa | Ação e resultado esperado |
|---|---|
| 1. Novo mês | Na fixture automatizada, executar o ciclo em 01/10/2026 com timezone São Paulo. Não alterar o relógio ou a data do job real. |
| 2. Criação automática | Exatamente um fechamento de setembro, com a relação capturada dos participantes e preparação dos novos indivíduos. |
| 3. Pendências | Duas pendências únicas, vinculadas aos três IDs. Pagamento inicia como Aguardando fechamento. |
| 4. Slack dia 1 | Um evento de fechamento com a competência e link corretos. Em teste, shadow, sem envio externo. |
| 5. Somente FIXO | Pessoa sem tarefas presente, com R$ 3.000 fixos. |
| 6. FIXO + variável | Conferir os componentes mensais e o PDF, incluindo serviços, extras e produtividade existentes. |
| 7. Revisão pendente | Tentar concluir pelo backend antes de confirmar todos os PDFs. Deve recusar e informar a quantidade pendente. Desconto sem motivo também deve recusar. |
| 8. 100% revisado | Gerar e confirmar PDFs vigentes, depois Concluir fechamento. Fecha somente com todos os integrantes revisados. |
| 9. Snapshot | Anotar total/revisão/hash. No teste, alterar produção/cadastro depois e confirmar que o valor fechado não muda. |
| 10. Pagamento parcial | Quitar apenas uma pessoa. Todas as funções dela ficam pagas, demais pessoas continuam pendentes. |
| 11. KPIs | Total fechado constante, Pago acrescido do saldo registrado, Pendente = Total − Pago. Gráficos usam os mesmos valores. |
| 12. 5º dia útil | Fixture executa 07/10/2026 e também valida virada de ano com feriado existente. |
| 13. Slack pagamento | Fechado: mensagem com total/pago/pendente/progresso. Não fechado: alerta explícito de bloqueio e revisões pendentes. |
| 14. Quitação antecipada | POST quitar com participante não pago deve recusar, mesmo se a interface fosse contornada. |
| 15. Todos pagos | Quitar os integrantes restantes, incluindo quem possui valor zero. |
| 16. Pendência encerrada | Ao registrar o último integrante pago, a pendência de pagamento conclui automaticamente. |
| 17. Competência quitada | Situação Quitado, 100% pessoas e saldo zero. |
| 18. Repetição | Reexecutar job/requisições e workers concorrentes. Mesmo ciclo, mesmos participantes, pendências, alerta e ledger sem duplicidade. |

Para usar dados reais, navegue por Financeiro → Pagamentos e selecione setembro/2026. Confirme os documentos e registre pagamentos somente após a revisão financeira efetiva. O roteiro de mutações desta entrega foi executado com dados sintéticos.

## Resultado da validação

- Novo fluxo: **57 verificações**, incluindo concorrência, reconciliação histórica, PDF consistente, imutabilidade, descontos, progresso e idempotência.
- Regressões executadas: regras mensais **53**, integração mensal **48**, domínio financeiro **230**, composição **169**, persistência **117**, documentos **194**, FlowConnect **158**.
- PHP e JavaScript verificados sintaticamente, e diff verificado.
- Navegador: ambas as URLs oficiais responderam, com login. Validado no Edge autorizado, inclusive desconto com motivo obrigatório, nova revisão e confirmação do PDF: abas reais, FIXO sem tarefas, histórico da Adriana, pendências/CTA; fechamento e pagamentos completos na sessão sintética. Meses anteriores ao corte continuam usando o legado.
- Responsividade: desktop 1920×1080, notebook 1366×768, iPad landscape 1024×768, portrait 768×1024 e mobile 390×844. A tabela de fechamento tem rolagem contida, sem ampliar o corpo da página.
- Evidência da quitação integral sintética: `output/ui/competencia/quitacao-sintetica.jpg`. Evidência do fechamento real: `output/ui/competencia/setembro-real.jpg`.
