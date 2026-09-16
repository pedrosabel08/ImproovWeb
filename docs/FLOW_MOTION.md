# FlowMotion

FlowMotion é a camada opt-in de animação do Flow. Ela padroniza entradas curtas e discretas com GSAP, sem substituir transições CSS de hover, foco, cor ou borda.

**Todas as novas páginas do Flow devem utilizar FlowMotion como base de motion, salvo quando houver uma razão técnica documentada para não fazê-lo.**

## Inclusão e arquitetura

Inclua o helper depois que `asset_url()` estiver disponível. Em um módulo de primeiro nível:

```php
<?php require_once __DIR__ . '/../includes/flow-motion-assets.php'; ?>
<?php flow_motion_assets('../'); ?>
```

Na raiz do projeto, use `flow_motion_assets()`. O helper carrega uma única versão pinada de GSAP (`3.12.5`), `script/motion/flow-motion-presets.js` e `script/motion/flow-motion.js`. Ele é opt-in: nunca deve ser incluído por `sidebar.php` ou outro include global.

## Estrutura declarativa

```html
<header data-motion="header">...</header>
<section data-motion="toolbar">...</section>
<aside data-motion="sidebar">...</aside>
<main><section data-motion="section">...</section></main>

<div data-motion-group="cards">
  <article data-motion-item>...</article>
</div>
```

Depois do markup estático, chame `FlowMotion.init()`. A chamada é idempotente e não reanima os elementos estáticos já processados.

## API

- `FlowMotion.init(root?)` — entrada dos elementos declarativos ainda não inicializados.
- `FlowMotion.enterPage(root?)` — sequência de header, toolbar, sidebar e sections.
- `FlowMotion.enterItems(container, { items, initial, preset })` — anima itens fornecidos pelo módulo.
- `FlowMotion.reveal(element, { preset })` — revelação curta para uma atualização localizada.
- `FlowMotion.pop(element, { preset })` — ênfase breve, sem pulsação contínua.
- `FlowMotion.openModal(element, { preset })` e `closeModal(element, { onComplete })` — entrada e saída de painel modal.
- `FlowMotion.canAnimate()` — `false` sem GSAP ou com reduced motion.

As funções de animação retornam o tween/timeline ou `null` quando não há animação possível.

## Tokens e presets

Os valores vivem em `flow-motion-presets.js`: duração `fast` 200ms, `normal` 360ms e `slow` 520ms; distância `xs` 4px, `sm` 8px e `md` 12px; stagger por duração total `tight` 120ms, `normal` 220ms e `relaxed` 320ms. Eases: `enter`, `exit` e `emphasized`.

Presets disponíveis: `subtle`, `card`, `sidebar`, `modal` e `emphasized`. Páginas não devem espalhar valores arbitrários de duração, distância ou easing.

## AJAX, listas e atualizações

O módulo é responsável por animar apenas os elementos que acabou de criar; FlowMotion não usa `MutationObserver`.

```js
const elements = renderItems(data);
FlowMotion.enterItems(container, { items: elements, initial: false });
```

Na primeira carga, use `initial: true`. Em filtro, refresh, paginação ou “carregar mais”, use `initial: false` e passe só os itens novos: nunca selecione e reanime os já existentes.

Para atualizações de estado frequentes, prefira `FlowMotion.reveal(changedElement)` ou `FlowMotion.pop(changedElement)` quando a mudança tiver valor visual real.

## Modais e acessibilidade

Ao abrir modal, mantenha a lógica atual de visibilidade e chame `FlowMotion.openModal(dialogElement)`. Se um modal já possuir keyframes que animam `transform`, neutralize apenas a animação conflitante nessa página; CSS e GSAP não devem disputar a mesma propriedade.

Com `prefers-reduced-motion: reduce`, ou se GSAP não carregar, a API não altera a visibilidade nem bloqueia a página. Não use `opacity: 0` global no CSS como estado inicial. FlowMotion prepara o estado apenas ao criar o tween e limpa `transform`, `opacity` e `visibility` ao final.

## Boas práticas e migração

Use GSAP para entradas, sequências, conteúdo novo e modal; deixe `:hover`, `:focus`, cores, fundos e bordas em CSS. Anime apenas `transform` e `opacity`, sem `will-change` permanente ou animações decorativas contínuas.

Para migrar uma página antiga, primeiro audite markup, renderizadores AJAX, modais e animações CSS. Depois adicione o helper somente à página, marque sua estrutura com os atributos declarativos e conecte seus renderizadores a `enterItems`. Valide desktop, notebook, tablet, mobile, reduced motion, console e rede antes de ampliar o escopo.
