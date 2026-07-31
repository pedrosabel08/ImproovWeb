# Revisão de preservação — redesign do SIRE

Data: 2026-07-29

## Base comparada

- Estado anterior: `HEAD` e histórico do diretório `SIRE` (incluindo os commits de Golden Sample, importação e Eventos).
- Estado em evolução: alterações locais da Biblioteca Visual Classificada V1.
- Alterações mais profundas encontradas: `catalogo.js`, `catalogo_ajax.php`, `index.php` e `catalogo.css`.

## Elementos preservados

- Importação automática de referências do Flow e respectivo armazenamento.
- Endpoints existentes de catálogo, Golden Sample, referências e fila de Eventos.
- Busca, paginação no backend, botão “Carregar mais”, lazy loading e fallback de miniatura.
- Filtros reais por Obra, Ambiente, Golden Sample e os sete pilares; pilares mantêm OR internamente e AND entre si.
- Ações de Golden Sample, abertura ampliada da imagem, “Ver original”, fechamento por Esc e Select2 com criação de valor.
- IDs e `data-id` consumidos pelos scripts: `refGrid`, `refLightbox`, `lb*`, filtros, modais e formulários de referência externa.

## Elementos recuperados

- `sire_helpers.php` e `catalogo_ajax.php`: a grade voltou a receber `thumbnail_url` por meio da assinatura existente de `thumb.php` (`path`, `w`, `q`). O original permanece restrito ao modal e à ação “Ver original”.
- `catalogo.js::renderCards`: “Carregar mais” voltou a anexar somente os novos cards, sem reconstruir e recarregar as imagens anteriores.
- `catalogo.js::toggleGolden`: restaurados bloqueio contra duplo clique, atualização otimista, confirmação do servidor, rollback e atualização parcial do card/modal.
- Estados `loading` e fallback das thumbnails foram restaurados.
- `catalogo.js::loadEventRefs`: foram restaurados data/tipo do evento, participantes, status e observação da referência de evento, além de estado de erro e estado de carregamento do botão.
- A abertura ampliada com zoom/arraste já havia sido recuperada na V1 e permanece conectada à imagem principal.

## Elementos substituídos

- Barra horizontal de filtros: passa a ser sidebar de 280 px no desktop e usa o mesmo contrato do Render em telas menores (`#filters`, `.filters.open`, `#filter-toggle-btn`), acrescentando backdrop, botão explícito e fechamento por Esc.
- As abas provisórias “Detalhes/Classificação” foram consolidadas em um único modal: imagem à esquerda e classificação/metadados à direita. Os campos, IDs, salvar, Golden Sample e “Ver original” foram preservados.
- Cards extensos: passam a dar prioridade à imagem e mostram apenas origem, Golden e até dois valores de classificação; os metadados completos continuam no modal.

## Elementos removidos definitivamente

- `#filterEstilo` e `#filterTipo` em `SIRE/index.php`. A versão histórica não os preenchia nem os enviava ao backend — `catalogo_ajax.php` tampouco retornava esses campos. Não existiam listeners, endpoint ou seletor consumidor no estado funcional. Foram substituídos pelos sete filtros oficiais de pilares. Risco: nenhum fluxo funcional conhecido; validado por busca, filtros e paginação.
- Inclusão de `../css/modalSessao.php` já removida na V1: o arquivo não existe no repositório e provocava warning PHP. `modalSessao.css` e `controleSessao.js`, que existem e são usados, permanecem.
- Seletores e listeners das abas provisórias do redesign foram removidos depois que a classificação passou a fazer parte estrutural do painel direito. Nenhuma ação dependia deles.

## Riscos e mitigação

- **Seletores externos:** IDs e endpoints existentes foram mantidos; a revisão inclui `git diff --check`, lint PHP e sintaxe JavaScript.
- **Modal/Select2:** `dropdownParent` é definido no modal para impedir dropdown atrás do backdrop; há confirmação de alterações não salvas e retenção de foco.
- **Responsividade:** validada em desktop e 390×844; a regra móvel é baseada no padrão do Render e não em um componente paralelo.
- **Fila de Eventos:** continua oculta com zero ou uma referência e exibe apenas duas ou mais; a renderização rica recuperada trata falha de carregamento.
- **Critério “liberada”:** o endpoint aceita apenas status explícitos de liberação, ignora arquivadas/inválidas e deduplica por URL, hash ou caminho. Foram validados no banco de desenvolvimento os cenários 0, 1, 2 e duplicadas; todos os registros temporários foram removidos.
- **Tema:** as superfícies e textos usam tokens existentes, inclusive complemento dos tokens ausentes no tema escuro; não foram adicionados gradientes.
