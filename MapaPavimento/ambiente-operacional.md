# Inspeção operacional de ambientes

O clique na demarcação ou na lista passa por `selectEnvironment()` e abre `AmbienteOperacionalPanel`. A sidebar mostra cor, nome e quantidade de imagens. `plantaEnvironmentColor(id)` atribui um matiz determinístico ao ID; planta, label, lista e painel reutilizam essa identidade, sem nova persistência. Hover/foco usa `setEnvironmentHover()`, e a seleção continua única. Fechar o painel limpa seleção e hover. O clique na lista traz a área de volta à janela somente quando ela está fora da visualização.

O painel mantém a planta ao fundo e possui estados normal (`compacto`), maximizado (`completo`) e minimizado. Minimizado é uma síntese operacional, com thumbnail/empty state compacto, stepper e etapa ativa/próxima; os três modos preservam a imagem selecionada sem novas consultas.

Uma imagem abre diretamente; várias imagens abrem “Imagens do ambiente”, com seleção individual e retorno à lista. A timeline pertence exclusivamente à imagem selecionada. A etapa oficial vem de `imagem.status_nome`, acompanhada por uma descrição de apresentação; nunca é inferida pela conclusão das funções.

## Dados e autorização

`GET api.php?action=imagens_contexto_operacional&obra_id=102&imagem_ids=3244,3245`

- Exige sessão e acesso à obra pelo `mp_require_obra` existente.
- Aceita até 100 IDs positivos, elimina duplicados e valida todos contra a obra antes de carregar qualquer contexto.
- Se um ID não pertencer à obra ou não existir, recusa integralmente a consulta com 403.
- Retorna `{sucesso: true, contextos: [...]}` a partir de `flow_imagens_contextos_operacionais_lote`.
- Não calcula função atual, conclusão, responsável ou bloqueios no endpoint.
- Cada abertura faz uma chamada em lote. Selecionar uma imagem ou alternar o tamanho não faz novas consultas. “Atualizar” refaz a consulta; ao retornar à janela, dados com mais de 30 segundos também são atualizados. Respostas de ambientes anteriores são descartadas por token de requisição.

## Apresentação compartilhada

`assets/js/components/imagem-timeline.js` expõe `FlowImagemTimeline.render(contexto, 'compacto' | 'completo')` e `summary(contexto)`. Não interpreta status para determinar estados: utiliza `estado_visual` e os grupos de IDs do contrato. Cada `funcao_imagem_id` gera um estágio separado, inclusive em funções repetidas.

A apresentação reutiliza a linguagem da timeline do Kanban: sequência horizontal, conectores, ícones de conclusão/atividade/pendência/bloqueio. O Kanban não foi alterado. O componente poderá ser aplicado nele posteriormente, usando o contrato operacional.

Os cards mostram estado, responsável e conclusão real ou prazo planejado (operacional como fallback). O clique na função abre seu diagnóstico: datas operacionais e planejadas separadas, compromisso, previsão, desvio individual, ciclos e requisitos. `ATIVA_COM_RESTRICAO_DE_INICIO` é aviso e nunca reclassifica a etapa como bloqueada. Sem evidência de data, a UI não inventa conclusão ou prazo.

Container queries trocam os cards por stepper/resumo quando o próprio painel tem até 640px úteis. Cards refluem em três colunas na largura intermediária; no mobile, mais de cinco funções refluem no stepper, sem scroll horizontal. Atraso usa `status_temporal_individual`: código `ATRASADO`, ou `BLOQUEADO` com dias vencidos informados pelo servidor, sem recalcular datas no navegador. Atrasos históricos de funções já concluídas ficam no diagnóstico. Etapas ativas simultâneas são mantidas; sem ativas, a primeira pendente na ordem fornecida é destacada como próxima.

A engrenagem de cada função oferece “Abrir FlowReview” (`funcao_imagem_id`, `obra_nome`) e “Abrir card da tarefa”. Esta segunda ação usa `FlowTaskOverlay`: dialog com iframe autenticado de `inicio.php?embed_task=1&focus_task=ID&imagem_id=ID`, invocando a função original `abrirSidebar()` e o endpoint original `getInfosCard.php`. Não depende de encontrar o card no Kanban do colaborador. Mensagens de ready/close validam origem e janela; o fechamento preserva a planta, seleção, modo e scroll. O FlowReview aceita o ID explícito sem disputar o antigo `fr_goto` entre abas. As ações de edição do ambiente continuam disponíveis em uma seção recolhida.

## Timeline temporal e movimento

O donut e a contagem foram removidos. `overview()` agora desenha uma linha por tarefa real, inclusive sem responsável. A escala usa início real ou planejado, conclusão real ou prazo planejado/operacional. Datas ausentes não geram intervalos inventados; início posterior ao término não é invertido para fabricar uma barra. Marcas pontilhadas mostram o término planejado quando diferente da conclusão. `hoje` vem do backend em America/Sao_Paulo. A mesma classificação operacional alimenta cards e timeline. Até 640px úteis, permanece o stepper; no minimizado a timeline temporal fica sempre oculta.

`imagem-etapas.css` concentra as variáveis e classes `si-*` extraídas de `Dashboard/styleObra.css`, que agora importa esse arquivo. Dashboard e mapa compartilham a paleta P00/R00–R05/EF e demais etapas, nos dois temas. Ícones de funções são Remix Icons; a página carrega sua folha uma vez.

FlowMotion já existe no início do Flow, mas não estava carregado no mapa. Para evitar importar GSAP somente para esse painel, as mudanças de modo usam Web Animations nativo: geometria inicial/final, transformação por 320ms e conteúdo por 200ms com pequeno atraso. O DOM não é reconstruído em `setMode()`; o scroll é guardado por modo. Com reduced motion, nenhuma dessas animações é iniciada.

Validação desta evolução: Suítes Torre A/B com datas passadas/futuras, próxima Composição e Pós bloqueada; card original 120334 incorporado, fechamento e alternância dos modos; desktop/notebook/iPad/mobile e temas claro/escuro. Testes de contrato cobrem uma/várias funções sem responsável; não foi alterada a alocação de dados reais para criar esses cenários. `ambiente_operacional_motion_test.mjs` cobre scroll, ausência de reconstrução e reduced motion. A consulta CLI adicional para localizar exemplos reais sem responsável foi impedida pelas permissões de rede do terminal.

`imagem.obra_nome` identifica a obra para o link existente do FlowReview. `imagem.atualizado_em` vem da maior data dos logs reais já carregados das funções. Não representa o horário da consulta. Histórico/comentários são acessados pelo FlowReview, sem contagem ou conteúdo inventados no mapa.

## Validação

### Fluxo previsto e header maximizado

O contrato mantém `etapas` como tarefas reais e acrescenta `fluxo` para a apresentação da imagem. `imagem_fluxo_operacional_helper.php` reutiliza `flow_planejamento_carregar_itens_obra()`, incluindo declarações de `imagem_funcao_planejada` ainda não materializadas. A função prevista tem `funcao_imagem_id=null`, chave `id_etapa` própria, responsável ausente e janela do snapshot vigente. Não se geram registros no banco nem links de card/revisão para IDs inexistentes. Uma tarefa materializada substitui a declaração na consulta compartilhada.

Caderno + Filtro usa a classificação compartilhada `CADERNO_FILTRO` e o representante do planejamento para a janela comum. Para a apresentação operacional, uma tarefa ainda ativa/em aprovação mantém o bloco ativo; a união só conclui quando todas as componentes operacionais concluírem. Os estados das tarefas permanecem no contrato original, nos diagnósticos individuais e nos menus, com os IDs corretos. O estado unido é calculado no backend, compartilhado por cards e timeline.

A informação `.mp-op-image-info` é movida para o header no modo completo e devolvida ao hero ao restaurar/minimizar. É o mesmo nó, sem duplicação de botões ou reconstrução em mudanças de modo. O header adapta sua disposição para desktop e telas menores.

Caso real validado: imagem 3267, Living Torre B. Finalização prevista de 26/10 a 03/11/2026 sem responsável; Caderno concluído e Filtro em aprovação mantidos como uma etapa ativa; cinco blocos na apresentação. Card do Filtro abriu com ID 120297 e imagem 3267, mantendo ambiente, geometria da planta e modo do painel. Header conferido em desktop, 1366×768, 1024×768, 768×1024 e 390×844, temas claro/escuro, sem overflow horizontal.

`tests/imagem_fluxo_operacional_test.php` cobre declarações reais sem tarefa, várias funções sem responsável, Caderno/Filtro em estados diferentes, união mista, datas do snapshot e materialização sem duplicação. Os testes de apresentação e movimento também cobrem a chave de etapa prevista, ausência de links inválidos e movimentação do mesmo nó do header.

Evolução visual validada em 16/10/2026 no ambiente autenticado: Playground (sem prévia, próxima função e atraso), Piscina Leste (duas imagens e duas funções ativas), Espaço Kids (prévia real e seis funções concluídas) e Sala de jogos (HOLD/bloqueios). Planta/lista tiveram hover e clique conferidos nos dois sentidos; fechamento limpa o estado. Menus abriram o card 120328 e a revisão 120351; a função sem envios mostrou o estado vazio correspondente. Painéis normal, minimizado e maximizado, preview ampliado, temas dark/light e viewports desktop, 1366×768, 1024×768, 768×1024 e 390×844 foram conferidos sem overflow horizontal do painel. Cadastro/edição/vínculos, redesenho/edição de pontos, camadas, página, zoom/pan/fit continuaram acessíveis.

- `tests/imagem_contexto_operacional_http_test.php`: autenticação, lote, acesso à obra, rejeição de imagens de outras obras, entradas inválidas, limite e métodos HTTP.
- `tests/imagem_timeline_test.mjs`: status reais, simultaneidade, avisos, funções repetidas e ausência de promoção automática para “Imagem finalizada”.
- `tests/imagem_contexto_operacional_test.php`: contrato backend existente.
- Navegador autenticado: imagem única no Salão de festas 2, R00 com todas as funções concluídas no Espaço Kids, HOLD na Sala de Jogos, Ajuste/ciclo 2 no Espaço Gourmet, duas imagens na Piscina Leste e duas etapas ativas com restrição de início. Clique pela planta e sidebar, minimizar/restaurar/maximizar, navegação com painel minimizado e abertura da tarefa original no Flow. Layouts desktop, notebook, iPad landscape/portrait e mobile conferidos.
