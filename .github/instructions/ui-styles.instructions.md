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

  /* Substatus de pré-alteração */
  --status-rvw-done: #d97706;
  --status-pre-alt: #7c3aed;
  --status-ready-for-planning: #0891b2;

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

  /* Tabelas */
  --bg-table-head: #f8f9fb;
  --bg-table-row: #f4f7ff;
  --bg-table-row-hover: #0b0e11;
  --bg-table-row-selected: rgba(79, 128, 225, 0.07);
  --border-table: #e8ecf1;

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
    --bg-filter: #1e2130;
    --bg-input: #1a1d2e;
    --bg-modal: #1e2130;
    --bg-table-head: #1a1d2e;
    --bg-table-row: #22263a;
    --bg-table-row-hover: #2c3040;
    --bg-table-row-selected: rgba(109, 155, 255, 0.1);
    --border-card: #2c3040;
    --border-input: #2c3040;
    --border-table: #2c3040;
    --text-primary: #e8eaed;
    --text-secondary: #9aa3b0;
    --text-muted: #4b5563;
    --accent: #6d9bff;
    --accent-hover: #5a8af0;
    --accent-subtle: rgba(109, 155, 255, 0.12);
    --bg-count: #232638;
    --bg-surface-alt: #1a1d2e;
  }

  #gif {
    content: url("../gif/assinatura_branco.gif");
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
      id="gif"
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

**Variantes de botão standalone:**

| Classe          | Fundo                   | Texto                   |
| --------------- | ----------------------- | ----------------------- |
| `btn-primary`   | `var(--accent)`         | `var(--text-on-accent)` |
| `btn-secondary` | `var(--bg-count)`       | `var(--text-secondary)` |
| `btn-success`   | `rgba(16,185,129,0.12)` | `#059669`               |
| `btn-warning`   | `rgba(245,158,11,0.12)` | `#d97706`               |
| `btn-danger`    | `rgba(239,68,68,0.1)`   | `#dc2626`               |

**Input ao lado de botões:**

```css
.btn-input {
  height: 36px;
  border: 1px solid var(--border-input);
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-family: inherit;
  background: var(--bg-input);
  color: var(--text-primary);
  padding: 0 12px;
  min-width: 120px;
}
.btn-input:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-subtle);
}
```

---

## Badges de Status

```html
<span class="status-badge s-finalizado">Finalizado</span>
<span class="status-badge s-andamento">Em Andamento</span>
<span class="status-badge s-aprovacao">Aprovação</span>
<span class="status-badge s-reprovado">Reprovado</span>
<span class="status-badge s-refazendo">Refazendo</span>
<span class="status-badge s-outro">Outro</span>
<!-- Substatus de pré-alteração -->
<span class="status-badge s-rvw-done">RVW_DONE</span>
<span class="status-badge s-pre-alt">PRE_ALT</span>
<span class="status-badge s-ready-for-planning">READY_FOR_PLANNING</span>
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
/* Substatus de pré-alteração (IDs 10, 11, 12) */
.s-rvw-done {
  background: rgba(245, 158, 11, 0.12);
  color: #d97706;
}
.s-pre-alt {
  background: rgba(139, 92, 246, 0.12);
  color: #7c3aed;
}
.s-ready-for-planning {
  background: rgba(8, 145, 178, 0.12);
  color: #0891b2;
}
```

> **Tabela canônica de status** (badges translúcidos — `s-*`)
>
> | Status             | CSS var                       | Cor (hex) | Classe badge           |
> | ------------------ | ----------------------------- | --------- | ---------------------- |
> | Finalizado         | `--status-finalizado`         | `#10b981` | `s-finalizado`         |
> | Em Andamento       | `--status-andamento`          | `#f59e0b` | `s-andamento`          |
> | Em Aprovação       | `--status-aprovacao`          | `#8b5cf6` | `s-aprovacao`          |
> | Reprovado          | `--status-reprovado`          | `#ef4444` | `s-reprovado`          |
> | Refazendo          | `--status-refazendo`          | `#f97316` | `s-refazendo`          |
> | Outro              | `--status-outro`              | `#94a3b8` | `s-outro`              |
> | RVW_DONE           | `--status-rvw-done`           | `#d97706` | `s-rvw-done`           |
> | PRE_ALT            | `--status-pre-alt`            | `#7c3aed` | `s-pre-alt`            |
> | READY_FOR_PLANNING | `--status-ready-for-planning` | `#0891b2` | `s-ready-for-planning` |

---

## Status Imagem — células de tabela (`si-*`)

Usados por `applyStatusImagem()` / `modalApplyStatusImagem()` para colorir células `<td>`.  
As classes usam fundo sólido (não translúcido). Aplique via `cell.classList.add(cls)`.

```css
/* CSS vars declaradas em styleObra.css e styleIndex.css */
--si-p00: #ffc21c;
--si-r00: #1cf4ff;
--si-r01: #ff6200;
--si-r02: #ff3c00;
--si-r03: #ff0000;
--si-r04: #6449ff;
--si-r05: #7d36f7;
--si-ef: #0dff00;
--si-hold: #ff0000;
--si-tea: #f7eb07;
--si-ren: #0c9ef2;
--si-apr: #0c45f2;
--si-app: #7d36f7;
--si-rvw: #228b22;
--si-ok: #6495ed;
--si-fin: #228b22;
--si-drv: #00f3ff;
--si-rvw-done: #d97706;
--si-pre-alt: #7c3aed;
--si-ready-for-planning: #0891b2;
```

> **Tabela canônica de status imagem** (`si-*`)
>
> | Status             | Classe                  | Background | Texto  |
> | ------------------ | ----------------------- | ---------- | ------ |
> | P00                | `si-p00`                | `#ffc21c`  | preto  |
> | R00                | `si-r00`                | `#1cf4ff`  | preto  |
> | R01                | `si-r01`                | `#ff6200`  | preto  |
> | R02                | `si-r02`                | `#ff3c00`  | preto  |
> | R03                | `si-r03`                | `#ff0000`  | preto  |
> | R04                | `si-r04`                | `#6449ff`  | preto  |
> | R05                | `si-r05`                | `#7d36f7`  | preto  |
> | EF                 | `si-ef`                 | `#0dff00`  | preto  |
> | HOLD               | `si-hold`               | `#ff0000`  | preto  |
> | TEA                | `si-tea`                | `#f7eb07`  | preto  |
> | REN                | `si-ren`                | `#0c9ef2`  | branco |
> | APR                | `si-apr`                | `#0c45f2`  | branco |
> | APP                | `si-app`                | `#7d36f7`  | branco |
> | RVW                | `si-rvw`                | `#228b22`  | branco |
> | OK                 | `si-ok`                 | `#6495ed`  | branco |
> | TO-DO              | `si-to-do`              | `#6495ed`  | branco |
> | FIN                | `si-fin`                | `#228b22`  | branco |
> | DRV                | `si-drv`                | `#00f3ff`  | preto  |
> | RVW_DONE           | `si-rvw-done`           | `#d97706`  | branco |
> | PRE_ALT            | `si-pre-alt`            | `#7c3aed`  | branco |
> | READY_FOR_PLANNING | `si-ready-for-planning` | `#0891b2`  | branco |

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
  width: min(50vw, 92vw);
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

## Diálogos (SweetAlert2)

**Nunca usar `alert()`, `confirm()` ou `prompt()` nativos do browser.** Sempre substituir por SweetAlert2.

Incluir no HTML antes dos scripts do módulo:

```html
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
```

### Alerta simples (com barra de progresso)

```js
Swal.fire({
  icon: "success", // 'success' | 'error' | 'warning' | 'info' | 'question'
  title: "Sucesso",
  text: "Operação realizada com sucesso!",
  timer: 3000,
  timerProgressBar: true,
});
```

### Confirmação

```js
const { isConfirmed } = await Swal.fire({
  title: "Tem certeza?",
  text: "Esta ação não pode ser desfeita.",
  icon: "question",
  showCancelButton: true,
  confirmButtonText: "Confirmar",
  cancelButtonText: "Cancelar",
  confirmButtonColor: "#4f80e1",
});
if (!isConfirmed) return;
```

### Input de texto

```js
const { value, isConfirmed } = await Swal.fire({
  title: "Informe o valor",
  input: "text",
  inputLabel: "Descrição do campo",
  inputPlaceholder: "Ex: 1500",
  showCancelButton: true,
  confirmButtonText: "Continuar",
  cancelButtonText: "Cancelar",
  confirmButtonColor: "#4f80e1",
  inputValidator: (v) => {
    if (!v) return "Campo obrigatório.";
  },
});
if (!isConfirmed || !value) return;
```

**Regras:**

- Usar `timerProgressBar: true` sempre que o diálogo fechar automaticamente.
- Usar `confirmButtonColor: '#4f80e1'` (accent do projeto) nos botões primários.
- Erros: `icon: 'error'`, `timer: 3000`, `timerProgressBar: true`.
- Avisos: `icon: 'warning'`, `timer: 3000`, `timerProgressBar: true`.
- Confirmações e inputs não devem ter `timer` (aguardar ação do usuário).

---

---

## Tabelas de Dados

Toda tabela de dados deve ser envolvida em `.table-section` com header e `.table-wrap` para scroll horizontal.

```html
<div class="table-section">
  <div class="table-section-header">
    <span class="table-section-title">
      <i class="fa-solid fa-list"></i>
      Título da Seção
    </span>
    <span class="table-section-count">42</span>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Nome</th>
          <th class="col-center">Status</th>
          <th class="col-right">Valor (R$)</th>
          <th class="col-checkbox"></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Item</td>
          <td class="col-center">
            <span class="status-badge s-finalizado">Finalizado</span>
          </td>
          <td class="col-right col-numeric">R$ 200,00</td>
          <td class="col-checkbox"><input type="checkbox" /></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
```

```css
.table-section {
  background: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  overflow: hidden;
}
.table-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border-table);
  background: var(--bg-table-head);
}
.table-section-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 8px;
}
.table-section-count {
  background: var(--bg-count);
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: var(--radius-full);
}
.table-wrap {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.data-table thead th {
  background: var(--bg-table-head);
  color: var(--text-muted);
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  padding: 10px 12px;
  text-align: left;
  border-bottom: 1px solid var(--border-table);
  white-space: nowrap;
  text-overflow: ellipsis;
  overflow: hidden;
}
/* Alinhamento de colunas */
.data-table thead th.col-center,
.data-table tbody td.col-center {
  text-align: center;
}
.data-table thead th.col-right,
.data-table tbody td.col-right {
  text-align: right;
}

.data-table tbody tr {
  border-bottom: 1px solid var(--border-table);
  transition: background var(--transition-fast);
}
.data-table tbody tr:last-child {
  border-bottom: none;
}
.data-table tbody tr:hover td {
  background: var(--bg-table-row-hover);
}
.data-table tbody tr.row-selected td {
  background: var(--bg-table-row-selected);
}

.data-table tbody td {
  padding: 10px 12px;
  color: var(--text-primary);
  vertical-align: middle;
  background: var(--bg-table-row);
}
/* Modificadores de célula */
.data-table tbody td.col-numeric {
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.data-table tbody td.col-muted {
  color: var(--text-tertiary);
  font-size: 12px;
}
.data-table td.col-checkbox,
.data-table th.col-checkbox {
  width: 40px;
  text-align: center;
}
```

---

## Cartões de Totais

Use para resumir métricas numéricas no topo ou acima de tabelas.

```html
<div class="totals-bar">
  <div class="total-card">
    <div class="total-card-label">Total (R$)</div>
    <div class="total-card-value" id="totalValor">0,00</div>
  </div>
  <div class="total-card">
    <div class="total-card-label">Pagas</div>
    <div class="total-card-value is-paid" id="total-imagens-pagas">0</div>
    <div class="total-card-sublabel">Pago (R$)</div>
    <div class="total-card-subvalue is-paid" id="totalValorPago">0,00</div>
  </div>
  <div class="total-card">
    <div class="total-card-label">Não Pagas</div>
    <div class="total-card-value is-unpaid" id="total-imagens-nao-pagas">0</div>
    <div class="total-card-sublabel">Não Pago (R$)</div>
    <div class="total-card-subvalue is-unpaid" id="totalValorNaoPago">0,00</div>
  </div>
</div>
```

```css
.totals-bar {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: stretch;
}
.total-card {
  background: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-md);
  padding: 10px 16px;
  min-width: 120px;
  box-shadow: var(--shadow-card);
}
.total-card-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: var(--text-muted);
}
.total-card-value {
  font-size: 22px;
  font-weight: 700;
  color: var(--text-primary);
  margin-top: 2px;
  font-variant-numeric: tabular-nums;
}
.total-card-value.is-paid {
  color: #10b981;
}
.total-card-value.is-unpaid {
  color: #ef4444;
}
.total-card-sublabel {
  font-size: 10.5px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  color: var(--text-muted);
  margin-top: 6px;
}
.total-card-subvalue {
  font-size: 13px;
  font-weight: 700;
  color: var(--text-secondary);
  font-variant-numeric: tabular-nums;
}
.total-card-subvalue.is-paid {
  color: #10b981;
}
.total-card-subvalue.is-unpaid {
  color: #ef4444;
}
```

---

## Grupo de Filtro por Checkbox

Use para filtrar listas por tipo/categoria com contadores atualizados dinamicamente.

```html
<div class="checkbox-filter-group">
  <label class="checkbox-label">
    <input type="checkbox" name="Tipo A" onclick="filtrarTabela()" />
    <span>Tipo A</span>
    <span class="tipo-count">12</span>
  </label>
  <label class="checkbox-label">
    <input type="checkbox" name="Tipo B" onclick="filtrarTabela()" />
    <span>Tipo B</span>
    <span class="tipo-count">5</span>
  </label>
</div>
```

```css
.checkbox-filter-group {
  background: var(--bg-filter);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-md);
  padding: 12px 16px;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: 6px 16px;
  box-shadow: var(--shadow-sm);
}
.checkbox-label {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  cursor: pointer;
  color: var(--text-secondary);
  font-size: 13px;
  font-weight: 500;
  user-select: none;
  padding: 3px 0;
}
.checkbox-label input[type="checkbox"] {
  width: 14px;
  height: 14px;
  cursor: pointer;
  accent-color: var(--accent);
  flex-shrink: 0;
}
.checkbox-label .tipo-count {
  display: inline-block;
  margin-left: 4px;
  background: var(--bg-count);
  color: var(--text-muted);
  font-size: 11px;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: var(--radius-full);
}
```

---

## Botões de Ação em Linha (Tabela)

Use `.btn-row` para botões compactos dentro de células de tabela.

```html
<td>
  <button class="btn-row send">
    <i class="fa-solid fa-paper-plane"></i> Enviar
  </button>
  <button class="btn-row validate">Validar</button>
  <button class="btn-row pay">Pagar</button>
  <button class="btn-row danger">Excluir</button>
</td>
```

```css
.btn-row {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 12px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  white-space: nowrap;
  transition:
    background var(--transition-fast),
    transform var(--transition-fast);
}
.btn-row:hover {
  transform: translateY(-1px);
}
.btn-row:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  transform: none;
}
```

| Modificador | Fundo                   | Texto     |
| ----------- | ----------------------- | --------- |
| `.send`     | `rgba(245,158,11,0.12)` | `#d97706` |
| `.validate` | `rgba(59,130,246,0.12)` | `#2563eb` |
| `.adendo`   | `rgba(124,58,237,0.12)` | `#7c3aed` |
| `.pay`      | `rgba(16,185,129,0.12)` | `#059669` |
| `.danger`   | `rgba(239,68,68,0.1)`   | `#dc2626` |

---

## Caixas de Alerta

```html
<div class="alert-box warning">
  <i class="fa-solid fa-triangle-exclamation"></i>
  <div>Atenção: 3 itens com valor divergente.</div>
</div>
<div class="alert-box info">
  <i class="fa-solid fa-circle-info"></i>
  <div>Informação relevante aqui.</div>
</div>
<div class="alert-box danger">
  <i class="fa-solid fa-circle-xmark"></i>
  <div>Erro ao processar operação.</div>
</div>
<div class="alert-box success">
  <i class="fa-solid fa-circle-check"></i>
  <div>Operação concluída com sucesso.</div>
</div>
```

```css
.alert-box {
  border-radius: var(--radius-md);
  padding: 12px 16px;
  font-size: 13px;
  font-weight: 500;
  display: flex;
  align-items: flex-start;
  gap: 10px;
}
.alert-box i {
  margin-top: 1px;
  flex-shrink: 0;
}
.alert-box.warning {
  background: rgba(245, 158, 11, 0.1);
  border: 1px solid rgba(245, 158, 11, 0.3);
  color: #92400e;
}
.alert-box.info {
  background: rgba(59, 130, 246, 0.08);
  border: 1px solid rgba(59, 130, 246, 0.25);
  color: #1e40af;
}
.alert-box.danger {
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.25);
  color: #991b1b;
}
.alert-box.success {
  background: rgba(16, 185, 129, 0.08);
  border: 1px solid rgba(16, 185, 129, 0.25);
  color: #065f46;
}
```

---

## Regras gerais

- Nunca usar Bootstrap — sempre CSS variables do projeto.
- Sidebar sempre incluída via `<?php include '../sidebar.php'; ?>`.
- Corpo da página sempre em grid `60px 1fr`.
- Todos os inputs/selects com `height: 36px` e focus ring com `box-shadow: 0 0 0 3px var(--accent-subtle)`.
- Ícones sempre com Font Awesome 6 (`fa-solid fa-...`).
- Animações de entrada: `opacity 0 + translateY` com `cubic-bezier(0.4, 0, 0.2, 1)`.
- Modais com animação spring: `cubic-bezier(0.34, 1.56, 0.64, 1)`.
- Tabelas sempre envolvidas em `.table-section` com `.table-wrap` para scroll horizontal.
- Cabeçalhos de tabela sempre com `text-transform: uppercase` e `var(--text-muted)`.
- `th` sempre com `white-space: nowrap; text-overflow: ellipsis; overflow: hidden;` para evitar quebra de linha e respeitando largura da coluna.
- Linhas de tabela com hover `var(--bg-table-row-hover)` e seleção `var(--bg-table-row-selected)`.
- **Nunca usar `alert()`, `confirm()` ou `prompt()` nativos** — sempre SweetAlert2 com `timerProgressBar: true` quando auto-fechável.
