# Planejamento R01+ por pontos e dia

Implementação iniciada em 28/10/2026, com validação final em 29/10/2026, sobre a fundação manual existente. Não converte entregas legadas nem altera o motor de planejamento R00.

## A. Arquitetura

`helpers/planejamento_alteracao_capacidade_helper.php` concentra configuração, calendário, agenda, validação de carga e sugestão. A unidade continua sendo `entrega_item_id`: cada imagem recebe nível, pontos, responsável e um dia em `fim_planejado`. `inicio_planejado` permanece compatível com versões anteriores. O término humano é `MAX(fim_planejado)`; prazo cliente permanece `entregas.data_prevista`.

Elegibilidade vem de `funcao_colaborador.funcao_id=6`, com colaborador ativo e elegível para capacidade. `tipo_atuacao` diferencia principal e secundária em seletores, candidatos, grade e configuração. Nenhum ID ou nome foi colocado na regra de negócio. Vínculos repetidos são agregados, com principal prevalecendo.

## B. Banco e snapshots

Migration `sql/2026-10-28_planejamento_alteracao_pontos.sql`, aplicada pelo CLI `php tools/apply_planejamento_alteracao_pontos.php`. Adiciona `parametros_json` à tabela existente `planejamento_capacidade_etapa` e cria a configuração `ALTERACAO_PONTOS`, sem atualizar planos ou responsáveis. O runner verifica a coluna antes de aplicar e preserva uma configuração já existente.

Pesos e overrides ficam nesse JSON; capacidade padrão usa a coluna existente. Não há nova tabela de parâmetros ou data duplicada. `snapshot_json` da versão existente preserva `pontos_snapshot`, nível, decisões fixadas, configuração, contexto da agenda e exceções. A agenda lê snapshots vigentes em uma consulta agregada, sem persistir pontos novamente em outra coluna. Versões antigas sem pontos continuam sem snapshot de pontos: não recebem backfill inventado. Um novo replanejamento pode atribuir pontos pela configuração atual, preservando a versão antiga.

Mudanças futuras de pesos não recalculam os pontos de uma rodada já confirmada. Se a triagem mudar o nível da imagem, a versão anterior conserva sua classificação, enquanto o novo replanejamento utiliza a classificação atual.

Sobrecarga confirmada é auditada na tabela existente `entrega_planejamento_capacidade_validacao`, por versão, colaborador e dia, com ator, contexto e percentual. Baseline, eventos e aplicação operacional continuam no serviço transacional existente.

## C. Configuração

Gestão → Capacidade da Produção → **Configurar planejamento de Alterações**. Também há um link no planner. Padrões iniciais: N1=5, N2=10, N3=15, N4=20, N5=25; capacidade=25 pts/dia. Esses números estão na migration, não no motor.

Override vazio usa o padrão; zero indica colaborador indisponível. Exceções temporárias globais usam `planejamento_capacidade_etapa_periodo` para `ALTERACAO_PONTOS`, na unidade pontos. A configuração é independente da entrega, exige gestão, CSRF e fingerprint para evitar sobrescrita concorrente. Não foi encontrada uma agenda individual de ausências estruturada no núcleo atual; não foi inventada uma fonte de férias. Indisponibilidade individual pode ser configurada pelo override zero.

## D. Motor de sugestão

Prioridades: cumprir prazo, respeitar oferta, preservar fixações, reduzir equipe e fragmentação, usar continuidade confirmada e preferência por função principal, depois encaixar no primeiro dia viável. O seletor junto a **Gerar sugestão** define a ordem das imagens não fixadas: maior complexidade primeiro (padrão, N5 a N1) ou menor complexidade primeiro (N1 a N5). A ordem usa o nível, independentemente dos pontos configurados; em níveis iguais, prioriza os maiores pontos e depois o identificador. Cada execução permite comparar outra proposta sem confirmar ou gravar o plano. Durante o cálculo, o Thinking Orbs global aparece junto ao botão. A rotina testa grupos crescentes e encaixa as imagens indivisíveis no primeiro dia viável, com a menor sobra de capacidade. Testa até 512 combinações por tamanho de equipe; é uma heurística determinística, não uma prova de ótimo global. A mensagem de inviabilidade significa que a busca não encontrou distribuição nesse contexto.

Itens fixados com sobrecarga ou após o prazo impedem a sugestão. Dias com ocupação desconhecida não são considerados livres na distribuição automática. Sugestão não grava rascunho ou tarefas; é necessário salvar e confirmar. O cenário 75 pontos (N1×3, N2×3, N3×2) cabe em um colaborador com 25 pontos por dia, em três dias úteis. Prazo menor exige duas ou três pessoas quando houver oferta.

## E. Interface

Backlog pesquisável, filtros por nível, opção de mostrar só pendências, seleção múltipla, miniaturas autenticadas, grade por colaborador/dia, barras de capacidade, disponibilidade e compromissos. Os cards podem ser movidos por drag/drop ou por seleção + botão de adicionar; podem ser retirados e fixados. A tabela detalhada permanece abaixo, com edição e ações em massa.

Movimentação recalcula pontos, conclusão e margem no navegador. Sobrecarga manual exige uma confirmação explícita com o excesso calculado; cancelar preserva o estado anterior. O backend revalida a decisão e o contexto. Desktop e notebook possuem rolagem interna; tablet permite recolher o backlog; mobile mostra um único dia com seletor, preservando a leitura por pessoa. Light/dark e Thinking Orbs global foram preservados. Miniaturas passam por autenticação/autorização da obra e cache privado.

## F. Agenda e concorrência

**Agenda diária de Alterações · R01–R05**, em Capacidade da Produção, mostra dez dias úteis e lê compromissos das versões confirmadas. A mesma função alimenta a grade de cada entrega, descontando outras R01–R05 e excluindo a versão da própria entrega que está sendo editada. Rascunhos não consomem capacidade. Entregas arquivadas não entram na oferta comprometida.

R00 fornece frações planejadas de pessoa/dia pela alocação canônica. O adaptador usa as mesmas tarefas materializadas, janela e fórmula do motor existente, sem converter imagens R00 em pontos. Ocupação confiável de 60% reduz a oferta nominal de 25 para 10 pontos. Modelagem de fachada (janela gerencial sem evidência de ocupação integral), responsabilidade compartilhada sem peso e janelas inconsistentes recebem aviso explícito; sua ocupação não é inventada. A oferta efetiva permanece não estimada para essa parcela e a sugestão evita tais dias.

`lock_version` e fingerprint de escopo foram mantidos. Configuração, candidatos e agenda compõem um segundo fingerprint, exigido para salvar/confirmar/sugerir. Confirmações R01+ e mudanças de configuração se serializam na linha de configuração, em transação READ COMMITTED. A confirmação reconsulta R00 antes da gravação. Alterações posteriores na agenda R00 podem afetar a disponibilidade de um plano já confirmado, como qualquer compromisso futuro; não foi modificado o protocolo de escrita R00 para impor um bloqueio global entre motores.

## G. Planejado × executado

`helpers/planejamento_alteracao_realizado_helper.php` lê somente ciclos com `entrega_item_id` da rodada consultada. O botão **Planejado × executado** separa responsável/dia baseline, responsável/dia vigente, executor, início real, conclusão humana e entrega efetiva ao cliente. Conclusão exige ciclo encerrado por conclusão/envio para aprovação, nunca transferência. Ausência de trilha permanece sem evidência; dados da tarefa reutilizada em outra rodada não são usados como substituto.

O payload fornece pontos planejados, pontos concluídos e pendência do planejado por colaborador/dia. Esses fatos permitem futura calibração; não há mudança automática de pesos, algoritmo de aprendizado ou dashboard analítico adicional.

## H. Validação

- `php tests/planejamento_producao/PlanejamentoAlteracaoPontosTest.php`: 57 verificações acumuladas, incluindo as duas ordens de complexidade, pesos não monotônicos, fixações, configuração, snapshots, calendário, capacidade e adaptador R00.
- `FLOW_R01_TEMP_DB_TEST=1 php tests/planejamento_producao/PlanejamentoAlteracaoIntegracaoTest.php`: 45 verificações, somente tabelas temporárias, incluindo transações, baseline, replanejamento, exceção auditada, alteração posterior de configuração, fingerprint desatualizado e elegibilidade por função.
- `php tests/planejamento_producao/PlanejamentoAlteracaoOperacionalTest.php --db`: passou com tabelas temporárias.
- Suítes PHP existentes de `tests/planejamento_producao`: passaram; interface R00 `PlanejamentoInterfaceTest.cjs` também passou usando Playwright já instalado.
- `node tools/test_planejamento_alteracao_ui.cjs`: 66 verificações, API sintética, incluindo envio de ambas as ordens e canvas do Thinking Orbs desenhado durante a geração, além de drag/drop, fixações, filtros, rascunho, histórico, concorrência, temas e cinco resoluções. Capturas em `output/planejamento-alteracao-ui/`.
- `php tools/test_page_metadata.php`: 811 verificações; `php tools/test_auth_navigation.php`: 188 verificações; lint PHP/JS sem erros.
- `node tools/audit_planejamento_alteracao_readonly.cjs`: 43 verificações na auditoria autenticada das URLs oficiais, incluindo ambas as ordens, orb e seletor nas cinco resoluções. O cenário real de oito imagens e 75 pontos gerou sugestões sem gravação ou confirmação de dados de negócio. Relatório em `output/planejamento-alteracao-readonly/report.json`.

## I. Limitações e homologação

O navegador interno falhou antes do login em **ambas** as URLs oficiais (`https://improov/ImproovWeb/` e `http://localhost:8066/ImproovWeb/`), com `browser access host is unavailable`. Chrome automatizado alcançou ambas e realizou login. Portanto, não se declara homologação no navegador interno.

O relatório final registra 43 verificações reais aprovadas, nenhum erro JavaScript e um recurso HTTP 404 no console. A auditoria fecha o aviso de novidades da Home pelo próprio modal antes de navegar. O cenário antigo foi conferido em leitura; as sugestões de 75 pontos foram geradas somente em memória e descartadas. Nenhuma entrega histórica foi convertida, criada ou confirmada para forçar um teste visual. Capturas da grade real ficam em `output/planejamento-alteracao-readonly/planner-grid-real-*.png`.

A sugestão é uma estimativa de encaixe com oferta planejada, não uma garantia de execução. EF/Render, recalibração automática e gestão de ausências individuais por período permanecem fora deste motor. Históricos sem pontos retornam métricas não registradas, em vez de zero. Responsáveis históricos inelegíveis permanecem visíveis; uma sugestão só os redistribui quando a decisão não está fixada e somente passa a valer após confirmação explícita do plano.
