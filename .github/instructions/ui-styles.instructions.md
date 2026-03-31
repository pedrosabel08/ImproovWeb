---
applyTo: "**/*.php, **/*.html, **/*.css"
description: "Padrões visuais e de UI do projeto ImproovWeb. Use when: criando telas, adicionando estilos, construindo layouts, criando componentes, modais, cards, badges, botões ou filtros."
---

# Padrões de UI — ImproovWeb

O módulo de referência é `Render/` (index.php + style.css). Toda nova tela deve seguir estes padrões.

---

## Stack e dependências

```html
<!-- Google Fonts -->
<link
  href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
  rel="stylesheet"
/>
<!-- Font Awesome 6.6 -->
<link
  rel="stylesheet"
  href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
/>
<!-- Toastify (notificações) -->
<link
  rel="stylesheet"
  href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css"
/>
<!-- Sidebar e modal de sessão globais -->
<link rel="stylesheet" href="../css/styleSidebar.css" />
<link rel="stylesheet" href="../css/modalSessao.css" />

<!-- jQuery 3.6 + Toastify + Sidebar + Controle de sessão -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script src="../script/sidebar.js"></script>
<script src="../script/controleSessao.js"></script>
```

**Não usar Bootstrap.** Todo o design system é via CSS variables customizadas.

---

## CSS Variables (copiar no `:root` de cada módulo)

```css
:root {
  /* Backgrounds */
  --bg-body: #f0f2f5;
  --bg-surface: #ffffff;
  --bg-surface-alt: #f8f9fb;
  --bg-card: #ffffff;
  --bg-card-hover: #f4f7ff;
  --bg-filter: #ffffff;
  --bg-input: #ffffff;
  --bg-count: #e8ecf1;
  --bg-overlay: rgba(0, 0, 0, 0.5);
  --bg-modal: #ffffff;
  --bg-section: #f8f9fb;
  --bg-skeleton: #e8ecf1;
  --bg-skeleton-shine: #f4f6f9;

  /* Bordas */
  --border-card: #e2e6eb;
  --border-card-hover: #a3bffa;
  --border-input: #d1d5db;
  --border-modal: #e2e6eb;

  /* Textos */
  --text-primary: #1a1d23;
  --text-secondary: #4b5563;
  --text-tertiary: #6b7280;
  --text-muted: #9ca3af;
  --text-on-accent: #ffffff;

  /* Accent (azul principal) */
  --accent: #4f80e1;
  --accent-hover: #3b6fd6;
  --accent-subtle: rgba(79, 128, 225, 0.1);
  --accent-glow: rgba(79, 128, 225, 0.25);

  /* Status */
  --status-finalizado: #10b981;
  --status-andamento: #f59e0b;
  --status-aprovacao: #8b5cf6;
  --status-reprovado: #ef4444;
  --status-refazendo: #f97316;
  --status-outro: #94a3b8;

  /* Sombras */
  --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.06);
  --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
  --shadow-lg: 0 8px 30px rgba(0, 0, 0, 0.14);
  --shadow-card: 0 1px 3px rgba(0, 0, 0, 0.05);
  --shadow-card-hover: 0 4px 14px rgba(79, 128, 225, 0.15);
  --shadow-modal: 0 20px 60px rgba(0, 0, 0, 0.18);

  /* Border radius */
  --radius-xs: 4px;
  --radius-sm: 8px;
  --radius-md: 12px;
  --radius-lg: 16px;
  --radius-xl: 20px;
  --radius-full: 999px;

  /* Transições */
  --transition-fast: 0.15s cubic-bezier(0.4, 0, 0.2, 1);
  --transition-normal: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  --transition-slow: 0.35s cubic-bezier(0.4, 0, 0.2, 1);

  /* Botão Limpar */
  --btn-clear-border: #ffb3b3;
  --btn-clear-bg: #fff5f5;
  --btn-clear-color: #c92a2a;
  --btn-clear-bg-hover: #ffe3e3;
}

/* Dark mode automático */
@media (prefers-color-scheme: dark) {
  :root {
    --bg-body: #0f1117;
    --bg-card: #1e2130;
    --bg-modal: #1e2130;
    --text-primary: #e8eaed;
    --accent: #6d9bff;
  }
}
```

---

## Tipografia

- **Font principal:** `"Inter"` → fallback: `-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`
- **Font sidebar:** `"Nunito"`
- **Font monospace (paths/código):** `"Menlo", "Consolas", monospace; font-size: 11px`

| Elemento                | font-size | font-weight | observação                                         |
| ----------------------- | --------- | ----------- | -------------------------------------------------- |
| Título da página        | 20px      | 700         | `letter-spacing: -0.3px`                           |
| Label de filtro         | 11px      | 600         | `text-transform: uppercase; letter-spacing: 0.4px` |
| Título de card          | 13px      | 700         | —                                                  |
| Meta de card            | 11.5px    | 400         | `color: var(--text-tertiary)`                      |
| Badge de status         | 11px      | 600         | `letter-spacing: 0.2px`                            |
| Título de seção (modal) | 10px      | 700         | `uppercase; letter-spacing: 0.6px`                 |
| Label de detalhe        | 10.5px    | 600         | `text-transform: uppercase`                        |
| Valor de detalhe        | 13px      | 500         | —                                                  |

---

## Estrutura de Layout (body)

```css
body {
  display: grid;
  grid-template-columns: 60px 1fr;
  height: 100vh;
  overflow: hidden;
  background: var(--bg-body);
  font-family: "Inter", sans-serif;
  color: var(--text-primary);
}
```

```html
<body>
  <?php include '../sidebar.php'; ?>

  <div class="container">
    <div class="page-header">...</div>
    <div class="filters">...</div>
    <div class="grid-scroll-area">
      <!-- ou table-scroll-area -->
      <!-- conteúdo principal -->
    </div>
  </div>

  <!-- Modais e overlays aqui (fora do .container) -->
  <div id="myModal" class="modal">...</div>
  <?php include '../css/modalSessao.php'; ?>
</body>
```

```css
.container {
  grid-column: 2;
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px 24px;
  height: 100vh;
  overflow: hidden;
}

.grid-scroll-area {
  flex: 1;
  overflow-y: auto;
}
```

---

## Page Header

```html
<div class="page-header">
  <div class="page-header-left">
    <img
      src="../gif/assinatura_preto.gif"
      class="page-header-logo"
      style="height:36px; opacity:0.85"
    />
    <h1 class="page-title">Nome da Página</h1>
  </div>
  <div class="results-summary">
    <span class="results-badge" id="resultsBadge">
      <i class="fa-solid fa-layer-group"></i>
      <span id="resultsCount">0</span> itens
    </span>
  </div>
</div>
```

---

## Filter Bar

```html
<div class="filters">
  <div class="filter-group">
    <label class="filter-label">Nome do filtro</label>
    <div class="input-wrap">
      <i class="fa-solid fa-magnifying-glass search-icon"></i>
      <input type="text" class="filter-input" placeholder="Buscar..." />
    </div>
  </div>
  <div class="filter-group">
    <label class="filter-label">Status</label>
    <select class="filter-select">
      <option value="">Todos</option>
    </select>
  </div>
  <div class="filter-actions">
    <button class="btn-apply">
      <i class="fa-solid fa-magnifying-glass"></i> Aplicar
    </button>
    <button class="btn-clear">Limpar</button>
  </div>
</div>
```

```css
.filters {
  background: var(--bg-filter);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-md);
  padding: 14px 16px;
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: flex-end;
  box-shadow: var(--shadow-sm);
}

.filter-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: var(--text-muted);
  margin-bottom: 4px;
  display: block;
}

.filter-input,
.filter-select {
  height: 36px;
  border: 1px solid var(--border-input);
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-family: inherit;
  background: var(--bg-input);
  color: var(--text-primary);
  padding: 0 10px;
  transition:
    border-color var(--transition-fast),
    box-shadow var(--transition-fast);
}

.filter-input:focus,
.filter-select:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-subtle);
}
```

---

## Botões

Regra geral: sem `border`, `height: 36px`, `border-radius: var(--radius-sm)`, `font-weight: 600`, `font-family: inherit`, `cursor: pointer`, hover com `translateY(-1px)`.

```css
.btn-apply {
  height: 36px;
  padding: 0 16px;
  background: var(--accent);
  color: var(--text-on-accent);
  border: none;
  border-radius: var(--radius-sm);
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  transition:
    background var(--transition-fast),
    transform var(--transition-fast),
    box-shadow var(--transition-fast);
}
.btn-apply:hover {
  background: var(--accent-hover);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px var(--accent-glow);
}

.btn-clear {
  display: none;
  align-items: center;
  gap: 5px;
  padding: 0 12px;
  height: 34px;
  border: 1px solid var(--btn-clear-border);
  border-radius: var(--radius-sm);
  background: var(--btn-clear-bg);
  color: var(--btn-clear-color);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}
.btn-clear:hover {
  background: var(--btn-clear-bg-hover);
}
```

**Botões de ação em modal:**

| Variante            | Fundo                   | Texto                   |
| ------------------- | ----------------------- | ----------------------- |
| Aprovar / Confirmar | `rgba(16,185,129,0.12)` | `#059669`               |
| Reprovar / Atenção  | `rgba(245,158,11,0.12)` | `#d97706`               |
| Excluir / Perigo    | `rgba(239,68,68,0.1)`   | `#dc2626`               |
| Primário (enviar)   | `var(--accent)`         | `var(--text-on-accent)` |
| Secundário (fechar) | `var(--bg-count)`       | `var(--text-secondary)` |

---

## Badges de Status

```html
<span class="status-badge s-finalizado">Finalizado</span>
<span class="status-badge s-andamento">Em Andamento</span>
<span class="status-badge s-aprovacao">Aprovação</span>
<span class="status-badge s-reprovado">Reprovado</span>
<span class="status-badge s-refazendo">Refazendo</span>
<span class="status-badge s-outro">Outro</span>
```

```css
.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: var(--radius-full);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.2px;
  white-space: nowrap;
}
.s-finalizado,
.s-aprovado {
  background: rgba(16, 185, 129, 0.12);
  color: #059669;
}
.s-andamento {
  background: rgba(245, 158, 11, 0.12);
  color: #d97706;
}
.s-aprovacao {
  background: rgba(139, 92, 246, 0.12);
  color: #7c3aed;
}
.s-reprovado,
.s-erro {
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
}
.s-refazendo {
  background: rgba(249, 115, 22, 0.12);
  color: #ea580c;
}
.s-outro {
  background: var(--bg-count);
  color: var(--text-tertiary);
}
```

---

## Cards

```css
.item-card {
  background: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  overflow: hidden;
  transition:
    transform var(--transition-normal),
    box-shadow var(--transition-normal),
    border-color var(--transition-normal),
    background var(--transition-normal);
  cursor: pointer;
  position: relative;
}
.item-card::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  opacity: 0;
  transition: opacity var(--transition-normal);
}
.item-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-card-hover);
  border-color: var(--border-card-hover);
  background: var(--bg-card-hover);
}
.item-card:hover::before {
  opacity: 1;
}
```

---

## Modais

```html
<div id="myModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 class="modal-title">Título</h2>
      <button class="modal-close" onclick="fecharModal()">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="modal-body">...</div>
    <div class="modal-footer">
      <button class="btn-action btn-secundario">Fechar</button>
      <button class="btn-action btn-primario">Confirmar</button>
    </div>
  </div>
</div>
```

```css
.modal {
  display: none;
  position: fixed;
  inset: 0;
  background: var(--bg-overlay);
  backdrop-filter: blur(4px);
  z-index: 1000;
  align-items: center;
  justify-content: center;
}
.modal.is-open {
  display: flex;
}

.modal-content {
  background: var(--bg-modal);
  border: 1px solid var(--border-modal);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-modal);
  width: min(560px, 92vw);
  max-height: 92vh;
  display: flex;
  flex-direction: column;
  animation: modalSlideUp 0.32s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes modalSlideUp {
  from {
    opacity: 0;
    transform: translateY(24px) scale(0.96);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 22px;
  border-bottom: 1px solid var(--border-modal);
}
.modal-body {
  padding: 20px 22px;
  overflow-y: auto;
  flex: 1;
}
.modal-footer {
  padding: 14px 18px;
  border-top: 1px solid var(--border-modal);
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
```

---

## Skeleton Loaders

```html
<div class="skeleton-card">
  <div class="skeleton-thumb"></div>
  <div class="skeleton-body">
    <div class="skeleton-line medium"></div>
    <div class="skeleton-line short"></div>
    <div class="skeleton-line medium"></div>
  </div>
</div>
```

```css
.skeleton-card,
.skeleton-thumb,
.skeleton-line {
  background: var(--bg-skeleton);
  border-radius: var(--radius-sm);
  position: relative;
  overflow: hidden;
}
.skeleton-card::after,
.skeleton-line::after {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(
    90deg,
    transparent,
    var(--bg-skeleton-shine),
    transparent
  );
  animation: shimmer 1.5s infinite;
}
@keyframes shimmer {
  from {
    transform: translateX(-100%);
  }
  to {
    transform: translateX(100%);
  }
}
.skeleton-line.medium {
  width: 70%;
  height: 12px;
}
.skeleton-line.short {
  width: 40%;
  height: 12px;
}
```

---

## Scrollbars customizados

```css
::-webkit-scrollbar {
  width: 5px;
  height: 5px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  background: var(--text-muted);
  border-radius: var(--radius-full);
}
::-webkit-scrollbar-thumb:hover {
  background: var(--text-tertiary);
}
```

---

## Notificações (Toastify)

```js
Toastify({
  text: "Mensagem aqui",
  duration: 3000,
  gravity: "top",
  position: "right",
  style: {
    background: "var(--accent)",
    borderRadius: "var(--radius-sm)",
    fontFamily: '"Inter", sans-serif',
    fontSize: "13px",
    fontWeight: "500",
  },
}).showToast();
```

Para erros use `background: "#ef4444"`. Para sucesso use `background: "#10b981"`.

---

## Regras gerais

- Nunca usar Bootstrap — sempre CSS variables do projeto.
- Sidebar sempre incluída via `<?php include '../sidebar.php'; ?>`.
- Corpo da página sempre em grid `60px 1fr`.
- Todos os inputs/selects com `height: 36px` e focus ring com `box-shadow: 0 0 0 3px var(--accent-subtle)`.
- Ícones sempre com Font Awesome 6 (`fa-solid fa-...`).
- Animações de entrada: `opacity 0 + translateY` com `cubic-bezier(0.4, 0, 0.2, 1)`.
- Modais com animação spring: `cubic-bezier(0.34, 1.56, 0.64, 1)`.
