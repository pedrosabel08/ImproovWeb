# Central de Entregas

Reformulação local de 27/11/2026, baseada na referência fornecida. Sem deploy, migration ou mudança dos cálculos de KPI, endpoints, permissões, geração automática, sidebar e fragmento do modal de detalhes.

## Arquivos

- `Entregas/index.php`: composição da Central, controles e carregamento dos assets locais.
- `Entregas/central.css`: apresentação restrita à Central e ao detalhamento dos KPIs, temas e responsividade.
- `Entregas/central-model.js`: filtros, ordenação, agrupamento e datas, sem escrita em dados de negócio.
- `Entregas/central.js`: renderização das três visualizações sobre a mesma lista, preferência por usuário e integrações com o script existente.
- `Entregas/card-ui.js` e `Entregas/card-ui.css`: indicador compacto, progresso segmentado, transição e tooltip compartilhados também com os cards de `Dashboard/obra.php`.
- `Entregas/script.js`: pontos de integração, atualização sem recarregar o documento, proteção contra respostas antigas e compatibilidade do rótulo P00 no relatório.
- `config/page_metadata.php`: título e descrição institucionais.
- `tests/entregas/CentralModelTest.cjs`: regressões das regras de apresentação.
- Este documento.

## Comportamento

Operacional é o padrão do primeiro acesso. A última visualização fica no `localStorage`, com chave por usuário. Obra, etapa, pesquisa, filtro rápido, dia e ordenação ficam no `sessionStorage` por usuário e sobrevivem à atualização da lista e do documento. Grupos mantêm o estado ao mudar filtros; Enviadas / Aguardando começa recolhido.

Requer atenção inclui atrasos abertos e cobranças de review vencidas em entregas abertas. HOLD continua pausado em A entregar, sem contagem como atraso ou disponibilidade para envio. O Kanban preserva a classificação fornecida pela API; não há movimentação entre colunas por arraste. A seleção de planejamento sobrevive à alternância das visualizações.

Os contadores rápidos usam a base selecionada por obra, etapa e pesquisa. Hoje, Amanhã, Próximos 7 dias e a faixa de prazos consideram entregas abertas, sem HOLD. Sete dias significa hoje até seis dias adiante. Clicar novamente na data remove sua seleção. Limpar remove todos os filtros, preservando a ordenação.

Prontas para envio significa entregas com pelo menos uma imagem disponível, conforme `ready_count` da API existente. Seu contador principal soma as imagens prontas e o texto secundário informa a quantidade de entregas. O resumo da lista também informa o total de imagens prontas dentro dos filtros ativos. Não é inferido de etapa de produção, progresso ou conclusão. Progresso representa imagens efetivamente entregues, conforme a mesma API.

No Kanban, a linha avulsa “N imagens a entregar” foi removida por solicitação do usuário, deixando o card mais compacto. A disponibilidade permanece no cabeçalho e no progresso segmentado. O bloco azul foi removido: um indicador verde com pacote/check e “1 pronta para envio” ou “N prontas para envio” fica ao lado do nome. Ícones de notificações e observações permanecem no cabeçalho. O espaço do cabeçalho é reservado e a altura dos cards é padronizada, com ou sem imagens prontas. Em telas estreitas, a coluna mantém largura suficiente para o indicador e o nome, com rolagem horizontal; nomes extensos preservam a etapa e têm texto completo no tooltip. Os badges de situação continuam removidos, pois a coluna já os identifica; HOLD permanece explícito em texto. No Operacional, o indicador mostra somente o ícone de pacote/check e a quantidade, com descrição completa acessível e no tooltip.

A barra agora tem três segmentos sem sobreposição: entregues em azul, prontas ainda não entregues em verde e demais pendências em cinza. As larguras são proporcionais ao total real de itens, enquanto o percentual textual considera somente entregues. O particionamento limita cada grupo ao saldo disponível e trata total zero sem divisão inválida. A regra de elegibilidade continua vindo de `ready_count`. A transição de atualização dura 250 ms e respeita movimento reduzido. Não há legenda permanente; hover e foco da barra mostram um único tooltip flutuante, seguindo o padrão de tooltips de progresso do Flow, com contagens, cores e tokens de tema da interface. Esses componentes também são reutilizados pelos cards de entrega da obra, sem modificar o modal.

Calendário semanal agrupa por obra e usa barras somente entre recebimento e prazo reais, recortadas ao período visível. Sem recebimento válido, o evento fica apenas no prazo. Datas ausentes nunca são inventadas; essas entregas aparecem em Sem prazo definido. O mês distribui por prazo. Em telas estreitas, a grade tem rolagem horizontal identificada e acesso por teclado.

Os KPIs continuam globais para o período de 30 dias, incluindo histórico de obras inativas e arquivadas, como no cálculo anterior. Não acompanham os filtros da lista. O detalhamento mostra valor anterior, datas, série e definição. Com atraso mede entregas já concluídas fora do prazo, e não o filtro de atrasos abertos. O SLA mantém sua série acumulada original.

Todos os cards, linhas e eventos usam os mesmos metadados e o mesmo handler de abertura do modal. O fragmento `includes/entrega-detalhe-modal.php`, `Entregas/detalhes.css` e as operações internas do modal não foram reformulados. Atualizações existentes chamam `carregarKanban()` e reaplicam o estado da Central. Nenhuma infraestrutura nova de tempo real foi criada.

O loading novo reutiliza Thinking Orbs global, canvas transparente, estado composing, label em português e suporte a movimento reduzido. Transições de interação duram 180 ms; os segmentos de progresso usam 250 ms. Ambas respeitam `prefers-reduced-motion`.

## Validação executada

- Login real em `https://improov/ImproovWeb/`, navegação Produção → Entregas no navegador interno.
- Operacional, Kanban, calendário semanal/mensal e abertura do modal original pelos três modos.
- Consistência: base de 95 entregas, Kanban com 6 atrasadas, 10 a entregar e 79 enviadas/aguardando na execução. Esses números são observações, nunca valores fixos da interface.
- Busca, filtro de etapa combinado, filtros rápidos, seleção/remoção de dia e ordenação. Consulta e fechamento dos detalhes mantiveram o resultado filtrado após atualizar os dados.
- Preferência de visualização preservada depois de recarregar o documento.
- Navegação semanal e mensal, retorno a hoje e ocultação da faixa superior no calendário.
- Seleção de planejamento, manutenção da seleção ao alternar modos e geração real do cronograma existente.
- Acesso a arquivadas e retorno às ativas; abertura de Nova Entrega e relatório. P00 do relatório revalidado com o rótulo correto.
- Tema escuro inspecionado no navegador interno em desktop 1920×1080, notebook 1366×768, iPad landscape 1024×768, iPad portrait 768×1024 e mobile 390×844. Corrigidos título oculto por CSS legado e largura dos filtros móveis. Sidebar preservada.
- HTTP sem cookies na segunda origem oficial `http://localhost:8066/ImproovWeb/`: Entregas leva ao login contextual sem corpo privado; favicon e imagem OG retornam 200 e image/png.
- HTML autenticado com um title, um favicon, metadata institucional e canonical sem parâmetros privados.
- `node --test tests/entregas/CentralModelTest.cjs`: 9 testes passaram, incluindo datas inválidas, limites do período, distinção entre envio/produção, HOLD, filtros combinados, spans reais, soma de imagens em múltiplas entregas de uma mesma obra e segmentos sem dupla contagem.
- `php tools/test_page_metadata.php`: 850 verificações sem falhas.
- `php tools/test_auth_navigation.php`: 190 verificações sem falhas.
- `php tools/test_entrega_imagem_etapa.php`: 44 verificações sem falhas.
- `php tests/entregas/RegistroItemTest.php`: passou com a configuração local e acesso de rede autorizado. Usa somente tabela temporária isolada para validar primeira entrega, reentrega, idempotência e isolamento entre entregas.
- Lint PHP, verificação de sintaxe JavaScript e `git diff --check` dos arquivos alterados.

No ajuste de destaque de imagens, foram conferidos no navegador 12 imagens prontas em 6 entregas e o recorte por pesquisa com 7 imagens em 2 entregas. A soma dos destaques dos cards coincidiu com o contador geral. Foi confirmada a ausência de badges de situação no Kanban, a abertura do modal original e a apresentação nas cinco resoluções previstas. A contagem representa itens disponíveis por entrega/etapa, conforme a API; não deduplica uma mesma imagem quando ela pertence a obrigações de entrega diferentes.

## Limitações da validação

O navegador interno disponível expõe apenas controle de viewport, sem emulação do esquema de cores. A paleta clara e os seletores do padrão existente (`data-theme` e preferência do sistema) foram implementados; a inspeção visual interativa do tema claro continua pendente.

Não foram registrados envios, criadas entregas ou gravadas mudanças de planejamento em registros reais durante a inspeção. O registro foi testado pelo helper real sobre tabela temporária; o cronograma foi calculado pela interface. NAS, cobranças externas e notificações não foram acionados como testes mutáveis.

Houve um registro isolado de MutationObserver no console da sessão inicial, antes dos testes da implementação; ele não reapareceu nas recargas e interações da Central. Não foi atribuída sua origem sem evidência.
