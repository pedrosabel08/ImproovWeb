---
applyTo: "**/*.php, **/*.html, **/*.css, **/*.js"
description: "Padrão oficial de decisão, interface e integração do Flow. Use antes de criar ou alterar telas, componentes, CSS, JavaScript ou endpoints que atendem a interface."
---

# Guia oficial de desenvolvimento do Flow

## Propósito e autoridade

Este é o padrão oficial para decisões de desenvolvimento do Flow. Ele orienta desenvolvedores e agentes de IA na análise, implementação, revisão e evolução de interfaces e das integrações que as sustentam.

Em desenvolvimento novo, este guia e o padrão canônico prevalecem. Módulos legados permanecem preservados até uma migração planejada: uma alteração local não autoriza reescrever, renomear ou substituir o módulo inteiro.

Este guia não é um catálogo para copiar e colar. Ele define como decidir. Quando houver mais de uma solução possível, aplique a ordem de prioridade abaixo:

1. Entender antes de modificar.
2. Reutilizar antes de criar.
3. Preservar consistência antes de inovar.
4. Resolver a causa raiz antes de aplicar correção local.
5. Evoluir incrementalmente antes de propor grande reescrita.
6. Preservar a identidade visual e arquitetural do Flow.

## Processo de decisão obrigatório

Antes de escrever código, siga esta sequência. Não pule etapas por parecerem simples.

1. Entenda a regra de negócio, os dados envolvidos, permissões, impacto e estados de erro.
2. Identifique a família visual correta da tela.
3. Procure uma tela ou fluxo semelhante no projeto.
4. Procure componente, helper, token, estilo, endpoint e evento já existentes.
5. Verifique módulos vizinhos e a infraestrutura compartilhada, especialmente sidebar, sessão, notificações e atualização de contagens.
6. Decida entre reutilizar, estender ou criar. Criação nova é a última opção.
7. Se criar algo novo for inevitável, justifique em comentário de implementação ou no PR qual lacuna existente ele resolve.
8. Só então implemente e valide todos os estados, temas e resoluções aplicáveis.

### Checklist de reutilização

Antes de criar qualquer componente, classe CSS, helper, função JavaScript ou endpoint PHP, responda:

- Existe componente visual semelhante?
- Existe classe CSS ou padrão de layout equivalente?
- Existe helper, função de renderização ou utilitário equivalente?
- Existe token semântico equivalente?
- Existe layout ou fluxo semelhante em outro módulo?
- É possível estender a solução encontrada sem quebrar seu contrato?

Se a resposta for sim para qualquer item, reutilize ou estenda a solução. Crie uma nova apenas quando a diferença for funcionalmente relevante, recorrente e não puder ser expressa pelo padrão existente.

## Práticas proibidas

As práticas abaixo são obrigatoriamente proibidas:

- Duplicar componente, CSS, helper, função, endpoint ou convenção já existente.
- Criar novo padrão visual para resolver necessidade pontual.
- Adicionar biblioteca para resolver problema simples ou já coberto por código interno.
- Criar uma nova convenção de nomenclatura concorrente.
- Usar cor hardcoded em componente, exceto token de domínio explicitamente formalizado.
- Usar !important sem causa técnica documentada e sem alternativa de escopo.
- Copiar markup de sidebar, modal, toast ou sessão para uma tela local.
- Quebrar retrocompatibilidade para resolver problema local.
- Alterar comportamento global, token global ou helper compartilhado para corrigir caso isolado sem avaliar consumidores.
- Reescrever um módulo amplo quando uma evolução incremental resolve a necessidade.
- Fazer polling para dado que já possui canal WebSocket ou atualização por evento disponível.
- Registrar listeners duplicados, renderizar a página inteira para alteração local ou repetir consulta sem necessidade.

## Famílias visuais e como escolhê-las

Escolha a família antes de copiar classes ou CSS. A escolha depende da intenção da tela, não de preferência estética.

| Família                | Referências                                            | Use quando                                                          | Decisão canônica                                                                     |
| ---------------------- | ------------------------------------------------------ | ------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| Operacional tokenizada | Render e Entregas                                      | Há filtros, lista/grid, status, ações e modais operacionais         | Use superfícies, filtros, cards/tabelas, tokens e estados compartilháveis.           |
| Dashboard operacional  | Gestão                                                 | Há leitura de indicadores, riscos, capacidade, atalhos e tendência  | Use KPIs, painéis, densidade informacional e atualização parcial.                    |
| Fluxo especializado    | Flow Block e Flow Review                               | O domínio possui vocabulário, tabela, estados ou interação próprios | Preserve o prefixo e tokens de domínio; reutilize as regras transversais deste guia. |
| Legado suportado       | Calendário, DataTables, Bootstrap e estilos históricos | A tarefa é manutenção localizada                                    | Preserve contratos; não introduza dependência ou padrão legado em código novo.       |

Uma família pode ter identidade própria, mas não pode ignorar tokens semânticos, responsividade, acessibilidade, performance ou os dois temas.

## Padrões implícitos formalizados

As regras abaixo foram formalizadas a partir de recorrências entre módulos ou de contratos estruturais dos módulos de referência. Use-as como decisão canônica.

| Padrão observado                                                                                     | Regra explícita                                                             | Justificativa                                                           |
| ---------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| Módulos operacionais, dashboards e fluxos especializados têm estruturas diferentes                   | Escolha a família visual antes de reutilizar markup, classes ou CSS         | Evita copiar uma solução correta para um contexto errado.               |
| Tokens de superfície, texto, borda, raio e destaque se repetem em módulos modernos                   | Nomeie e consuma tokens semânticos; não propague valores literais           | Mantém consistência e reduz divergência entre light e dark.             |
| Módulos especializados usam prefixos como fb-, fr- e sb-; estados repetem is-\*                      | Use prefixo apenas para domínio e is-\* para estado transitório             | Evita colisão sem criar convenções concorrentes.                        |
| Sidebar concentra permissões, URLs, badges e dados globais                                           | Consuma a sidebar compartilhada; nunca copie sua estrutura localmente       | Preserva compatibilidade e impede divergência de segurança e navegação. |
| Telas recentes carregam dados assíncronos com skeleton, renderização parcial, vazio, erro e feedback | Modele explicitamente o ciclo carregar → renderizar → vazio/erro → feedback | Reduz re-renderização e torna falhas recuperáveis.                      |
| JavaScript usa fetch, helpers de renderização e conteúdo dinâmico                                    | Centralize helpers e atualize somente a região afetada                      | Evita listeners e DOM duplicados.                                       |
| Endpoints recentes usam sessão, validação, prepared statements e JSON                                | Todo endpoint novo deve preservar esse contrato de segurança e resposta     | Mantém previsibilidade e reduz risco de injeção ou estado inválido.     |
| KPIs, quick actions, cards e tabelas são usados conforme densidade e finalidade da informação        | Escolha o componente pela intenção da informação, não pela aparência        | Mantém leitura operacional consistente.                                 |

## Arquitetura de telas

- Trate a sidebar como infraestrutura compartilhada. Inclua-a e consuma seus contratos; não replique seu HTML, permissões, URLs, badges ou variáveis globais.
- Mantenha conteúdo da página isolado no contêiner principal. Overlays, modais e diálogos ficam fora da área rolável quando precisarem cobrir a tela.
- Estruture telas operacionais em contexto da página, filtros, resumo/KPIs quando necessário e área principal rolável.
- Escolha cards para leitura resumida, ações rápidas, kanban ou itens exploráveis. Escolha tabela para comparação de múltiplos campos e ações densas. Escolha painel KPI para indicador, tendência e contexto curto.
- Não transforme tabela em cards automaticamente: em tela estreita, preserve scroll horizontal quando os dados comparativos perderem legibilidade empilhados.
- Use drawer para contexto secundário ou filtros móveis; use modal para tarefa focal que exige confirmação ou formulário; use toast para retorno breve e não bloqueante.

**Racional:** a arquitetura observada separa navegação, contexto e tarefa principal, reduzindo duplicação e preservando fluxos globais.

## Layout, espaçamento e responsividade

- Use grid ou flex conforme a relação de conteúdo. Não imponha uma estrutura única de body a todas as famílias.
- Trabalhe na escala de espaçamento recorrente: 4, 8, 10, 12, 14, 16, 20 e 24px. Use valores fora dela apenas quando o componente exigir medida estrutural.
- Use 8px em controles compactos, 12px em cards e filtros, e 16px ou 20px em painéis e modais de maior destaque.
- Preserve densidade em desktop e notebook; reduza colunas, não legibilidade, em telas menores.
- Valide, quando aplicável: desktop, notebook, iPad landscape, iPad portrait e mobile portrait.
- Os breakpoints devem responder ao ponto em que o conteúdo perde legibilidade. As faixas recorrentes são 1380/1366, 1240/1100/1024, 980/900, 768/720, 640/600 e 480px; não crie breakpoint novo sem necessidade de conteúdo.
- Filtros densos podem ser apresentados em sheet ou drawer em resoluções menores. Reutilize os mesmos campos e estado; não duplique regras de filtro.

**Racional:** o projeto já usa famílias com densidades e breakpoints distintos; a regra é preservar leitura e interação, não obedecer a um número arbitrário.

## Tokens, cores, tipografia e movimento

### Tokens semânticos

- Defina tokens no escopo mais reutilizável possível e prefira nomes semânticos: superfície, texto, borda, foco, destaque, perigo, sucesso e estado.
- Use as famílias recorrentes de tokens como --bg-_, --text-_, --border-_, --accent, --radius-_, --shadow-_ e --transition-_.
- Tokens específicos de domínio podem usar prefixo próprio, como --fb-_ ou --sb-_, quando representam conceito exclusivo desse domínio.
- Não crie alias de token apenas para repetir uma cor existente. Crie novo token somente se houver papel semântico distinto.
- Status de produção e imagem pertencem ao apêndice de domínio; não devem ser usados como paleta genérica de interface.

### Light e dark obrigatórios

- Light é a base em :root.
- Dark é aplicado exclusivamente com @media (prefers-color-scheme: dark).
- Não use alternador manual, localStorage, data-theme ou preferência persistida.
- Todo token alterado para dark deve manter contraste e função semântica equivalentes ao light.
- Todo componente e estado deve funcionar em light e dark: fundo, texto, borda, sombra, ícone, foco, loading, vazio, erro, sucesso e conteúdo desabilitado.
- Não aplique cores diretas a componentes quando existir token aplicável.

### Tipografia e ícones

- Use Inter na área principal; a sidebar mantém Nunito.
- Preserve hierarquia por peso, tamanho, contraste e espaço. Título de página, título de seção, label, metadado, valor e status devem ser distinguíveis sem depender apenas de cor.
- Use Font Awesome já carregado pelo módulo ou infraestrutura. Não adicione outro pacote de ícones para necessidade simples.
- Use números tabulares para valores comparativos, totais, datas e KPIs quando a leitura em coluna importar.

### Movimento

- Use transições curtas para hover, foco, seleção e abertura. Prefira opacity e transform a propriedades que provocam reflow.
- Animação deve comunicar mudança de estado; nunca ser decorativa a ponto de atrasar uma ação.
- Respeite prefers-reduced-motion quando uma animação relevante for introduzida.

**Racional:** tokens e movimento consistentes reduzem divergência visual entre módulos e garantem paridade de tema.

## Componentes e estados

Todo componente deve declarar e validar, quando aplicável:

- default;
- hover;
- focus visível;
- active ou selected;
- disabled;
- loading;
- empty;
- error;
- success.

### Botões e ações

- Escolha ação primária, secundária, destrutiva ou contextual pela consequência, não somente pela cor.
- Desabilite ação enquanto a operação não puder ser repetida com segurança e apresente loading quando houver espera perceptível.
- Ação destrutiva exige confirmação proporcional ao risco. Use diálogo próprio ou SweetAlert2 já adotado no módulo; não use alert, confirm ou prompt nativos.
- Não use botão genérico para ação de linha se houver padrão compacto existente no módulo.

### Formulários

- Todo campo precisa de label associado, erro próximo ao campo e foco visível.
- Valide no cliente para feedback imediato e no servidor como autoridade.
- Preserve o valor informado quando a solicitação falhar, salvo risco de segurança.
- Use select, textarea, checkbox e input conforme o tipo de dado; não simule controle nativo sem necessidade.

### Cards, KPIs, tabelas e quick actions

- Card de item apresenta contexto resumido e ação explorável.
- KPI apresenta rótulo, valor, tom semântico, tendência ou contexto; não use KPI para dado que exige tabela.
- Quick action representa uma ação frequente ou atalho operacional e deve comunicar estado/resultados.
- Tabela mantém cabeçalhos semânticos, alinhamento consistente, scroll horizontal quando necessário e ações compactas.
- Escolha componente pela intenção da informação, não pela estética desejada.

### Modais, drawers, tabs e dropdowns

- Modal fecha por botão explícito, Escape quando seguro e backdrop quando não houver risco de perda de dados.
- Modal e drawer precisam de título, foco inicial coerente, retorno do foco ao gatilho e navegação por teclado.
- Tabs e dropdowns devem refletir estado selecionado e não duplicar conteúdo ou lógica.
- Reutilize o diálogo ou drawer existente da família antes de criar um novo.

### Feedback

- Use Toastify para retorno breve, não bloqueante e sem decisão do usuário.
- Use SweetAlert2 ou diálogo próprio para confirmação, decisão, entrada focal ou erro que exige ação.
- Use alertas inline para contexto persistente da tela.
- Use skeleton durante carregamento de estrutura conhecida; use empty state com orientação quando não houver dados; use estado de erro recuperável quando a consulta falhar.

## Convenções CSS

- Mantenha CSS por módulo, agrupado por tokens, base, layout, componentes, estados e responsividade.
- Use kebab-case para classes. Use prefixo de domínio somente quando a classe não for transversal.
- Use modificadores com -- e estado transitório com is-.
- Evite seletor excessivamente amplo, dependência de ordem no DOM e especificidade crescente.
- Não use !important sem justificativa técnica documentada.
- Coloque a variação dark junto dos tokens ou da seção de componente correspondente, mantendo fácil comparação com light.
- Não crie reset global ou regra global para resolver problema local.

**Racional:** a convenção atual de prefixos e estados permite especialização sem colisão entre módulos.

## Convenções JavaScript

- Inicialize a tela uma única vez em DOMContentLoaded ou no ponto de entrada já usado pelo módulo.
- Centralize referências de elementos e helpers de renderização antes de registrar novos listeners.
- Prefira async/await, fetch e tratamento explícito de resposta a cadeias dispersas de callbacks.
- A operação assíncrona deve seguir: validar → indicar loading → chamar endpoint → atualizar apenas a área afetada → apresentar feedback → restaurar interação.
- Reutilize helpers de renderização e escape conteúdo dinâmico antes de inserir HTML.
- Prefira atualização parcial de DOM; não re-renderize página, tabela ou painel inteiro por mudança localizada.
- Use delegação de eventos para listas, tabelas e conteúdo dinâmico.
- Não registre listener dentro de fluxo que possa executar repetidamente sem guarda.
- Não faça polling quando a infraestrutura existente já disponibilizar WebSocket ou evento adequado.

**Racional:** os módulos recentes já aplicam fetch assíncrono, skeleton, empty state e atualização parcial; formalizar o ciclo reduz listeners e renders duplicados.

## Convenções PHP e JSON

- Inicie pela política de sessão e autorização existente antes de processar a requisição.
- Valide entrada no servidor mesmo que o cliente já valide.
- Use prepared statements e bind_param para valores externos.
- Use transação para operação composta que precisa ser atômica.
- Responda JSON com Content-Type adequado em endpoints de interface.
- Use status HTTP coerente: 401 para sessão ausente, 403 para acesso negado, 422 para validação e 500 para falha inesperada.
- Preserve o contrato já consumido pelo módulo. Ao criar endpoint novo, use envelope consistente com sucesso, mensagem, dados e erro quando aplicável.
- Não exponha detalhes internos de exceção ao usuário final; registre contexto técnico conforme a infraestrutura existente.

**Racional:** sessão, prepared statements e respostas JSON já são padrões recorrentes nos endpoints mais recentes e evitam inconsistência e falhas de segurança.

## Acessibilidade obrigatória

- Use elementos HTML semânticos antes de adicionar ARIA.
- Todo controle interativo deve ter nome acessível, foco visível e uso por teclado.
- Associe labels aos campos e descreva erros, ajuda e estados necessários.
- Não comunique status apenas por cor; combine texto, ícone, rótulo ou padrão visual.
- Garanta contraste legível em light e dark.
- Modal, drawer, tabs, dropdown e sheet devem comunicar estado com aria-expanded, aria-controls, aria-selected ou papel equivalente quando aplicável.

## Performance de interface

- Evite renderizações, listeners, consultas e requests duplicados.
- Reutilize dados já carregados quando ainda válidos.
- Faça atualização parcial da interface e preserve foco, seleção e rolagem quando possível.
- Reduza DOM excessivo; paginação, carregamento incremental ou virtualização devem ser considerados em listas grandes.
- Não bloqueie toda a tela para carregar painel independente.
- Use skeleton para estrutura conhecida e erro recuperável para falha parcial.

## Fluxo obrigatório para criação ou alteração de tela

1. Entender necessidade, regra de negócio, dados, permissões e impacto.
2. Executar processo de decisão e checklist de reutilização.
3. Escolher família visual e referência adequada.
4. Definir layout, componente correto e todos os estados.
5. Reutilizar ou estender componentes, helpers e tokens.
6. Implementar HTML semântico, CSS tokenizado, JavaScript e integração PHP.
7. Validar dados, loading, vazio, erro, sucesso, seleção e comportamento destrutivo.
8. Validar desktop, notebook, iPad landscape, iPad portrait e mobile quando aplicável.
9. Validar light e dark via prefers-color-scheme.
10. Validar teclado, foco, contraste e consistência com módulos vizinhos.
11. Corrigir divergências antes de considerar a tarefa concluída.

## Apêndice de domínio: status de produção e imagem

Os status de imagem, suas classes e cores pertencem ao domínio de produção. Ao trabalhar nesses fluxos:

- Reutilize a classe e o mapeamento de status já existentes.
- Não use esses códigos como cor genérica de botão, card ou alerta.
- Preserve texto ou ícone que torne o estado compreensível sem depender de cor.
- Ao introduzir status novo, atualize seu mapeamento de domínio e valide light/dark, tabela, card e modal que o consomem.

## Checklist final

- A decisão foi baseada em regra de negócio e padrão existente?
- Houve busca por componente, helper, token, layout e endpoint equivalentes?
- A solução reutiliza ou estende antes de criar?
- Os dois temas funcionam via prefers-color-scheme?
- Todos os estados relevantes foram validados?
- A interface é responsiva, acessível e consistente com a família visual?
- Não houve biblioteca, CSS, listener, consulta, helper ou comportamento global duplicado?
- A alteração preserva compatibilidade dos módulos existentes?
