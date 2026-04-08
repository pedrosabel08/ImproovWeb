# 🏗️ Redesign — Seção "Informações da Obra"

> Documento de arquitetura completa do redesign da seção de Informações da Obra no sistema Improov.
> Planejado e implementado em sessão única — Abril de 2026.

---

## Contexto

A seção "Informações da Obra" existia como um formulário flat (lista de `<input type="text">` com `.campo`) abaixo da tabela principal da obra. Os campos de briefing eram todos exibidos simultaneamente, sem hierarquia visual. As observações/instruções eram renderizadas em uma `<table>` via DataTables com ordenação via SortableJS. Não havia conexão visual desses dados com o modal de tarefa nem com o mindmap.

### Problemas identificados

- Nenhuma separação semântica entre categorias (padrão, materiais, produção, links)
- Todos os inputs sempre em modo de edição — sem controle de permissão
- Observações em tabela com DataTables: pesada, sem identidade visual
- Informações de briefing invisíveis no modal de tarefa e no mindmap
- Dois containers separados (`#infos-obra`) no mesmo arquivo, gerando inconsistência

---

## Arquitetura Geral

O plano foi dividido em **4 fases independentes e complementares**:

```
Phase 1 — Redesign visual do briefing
Phase 2 — Substituição da tabela de observações por cards
Phase 3 — Surfacing do briefing no modal de tarefa e mindmap
Phase 4 — Unificação dos containers com tabs + quick-link
```

---

## Phase 1 — Redesign Visual do Briefing

**Objetivo:** Substituir o formulário flat por grupos colapsáveis com modo read/edit, auto-save e feedback visual por campo.

### HTML (`Dashboard/obra.php`)

Os campos foram reorganizados em **4 grupos semânticos** dentro de `#tab-briefing`:

| Grupo               | Campos                                                              |
| ------------------- | ------------------------------------------------------------------- |
| Padrão              | `nivel`, `conceito`, `valor_media`, `outro_padrao`                  |
| Materiais           | `vidro`, `esquadria`, `soleira`, `acab_calcadas`                    |
| Produção            | `assets`, `comp_planta`                                             |
| Links & Localização | `fotografico`, `link_drive`, `link_review`, `local`, `altura_drone` |

Cada campo usa a estrutura `.campo-row`:

```html
<div class="campo-row">
  <span class="campo-val" id="val-{id}">—</span>
  <input type="text" id="{id}" name="{id}" class="campo-input" />
  <span class="campo-si" id="si-{id}"></span>
  <button class="campo-edit-btn" data-target="{id}">
    <i class="fa-solid fa-pencil"></i>
  </button>
</div>
```

- **`campo-val`** — exibe o valor em modo leitura
- **`campo-input`** — oculto por padrão; exibido via `.is-editing`
- **`campo-si`** — indicador de status do save (⏳ / ✓ / ✗)
- **`campo-edit-btn`** — visível apenas para admins via `.is-admin`

### JavaScript (`Dashboard/scriptObra.js`)

#### `setWithArrow(id, value)`

Atualizado para popular tanto o `<input>` quanto o `<span id="val-{id}">` simultaneamente.

#### `salvarNoBancoSilent(campo, valor, obraId, siId)`

Nova função de save silencioso com feedback visual no `campo-si`. Não exibe toast — apenas o indicador inline.

#### `initInfosObraUI()` — IIFE executada no carregamento

Responsável por toda a interatividade da seção:

1. **Tabs** — troca entre "Briefing" e "Instruções" via `data-tab`
2. **Collapse dos grupos** — toggle `aria-expanded` + animação da seta `.bgh-chevron`
3. **Modo edição** — clique no lápis: oculta span, exibe input, muda ícone para ✕; clique em ✕: cancela e restaura valor
4. **Auto-save com debounce** — 900ms após última tecla no `.campo-input`, chama `salvarNoBancoSilent`
5. **Save no Enter** — salva imediatamente + fecha modo edição
6. **Save no blur** — salva ao perder foco se ainda em `.is-editing`
7. **Link span clicável** — `.campo-val--link` abre URL em nova aba ao clicar

#### Admin check

Após carregar dados da obra, verifica `localStorage.getItem("usuarioId")`:

- Admins (IDs 1, 2, 9): adiciona `.is-admin` ao `#secao-infos-obra`
- CSS controla a visibilidade dos botões: `#secao-infos-obra.is-admin .campo-edit-btn { display: inline-flex }`

### CSS (`Dashboard/styleObra.css`)

Novas classes adicionadas:

- `.briefing-group` / `.briefing-group-header` / `.briefing-group-body` / `.bgh-chevron`
- `.campo-row` / `.campo-val` / `.campo-val--link` / `.campo-input` / `.campo-si`
- `.campo-si.saving` / `.campo-si.saved` / `.campo-si.error`
- `.campo-edit-btn` — `display: none` por padrão
- `#secao-infos-obra.is-admin .campo-edit-btn` — `display: inline-flex`

---

## Phase 2 — Cards de Observações/Instruções

**Objetivo:** Substituir a `#tabelaInfos` (DataTables + SortableJS em tabela) por uma lista de cards visuais com ordenação por drag-and-drop.

### JavaScript (`Dashboard/scriptObra.js`)

O bloco antigo (verificava `data.infos.length === 0`, inicializava DataTable, criava `<tr>`) foi substituído pela IIFE `renderObsCards()`:

- Renderiza `data.infos` como `.obs-card` dentro de `#obsCardList`
- Cada card exibe: **descrição** (`.obs-card-text`) + **data** (`.obs-card-date`)
- Admins recebem botão `.obs-card-edit` (lápis) → abre `#modalObservacao` com os dados preenchidos
- Drag-to-reorder via SortableJS em `#obsCardList` → chama `atualizarOrdem.php`
- Estado vazio: renderiza `<p class="obs-empty">Nenhuma instrução cadastrada.</p>`

### HTML (`Dashboard/obra.php`)

Dentro de `#tab-instrucoes`:

```html
<button id="obsAdd" class="obs-add-new-btn">
  <i class="fa-solid fa-plus"></i> Nova instrução
</button>
<div id="obsCardList" class="obs-card-list">
  <!-- populado pelo JS -->
</div>
```

> `id="obsAdd"` mantido pois há event listener existente no arquivo referenciando esse ID.

### CSS (`Dashboard/styleObra.css`)

Novas classes:

- `.obs-add-new-btn` — botão estilizado com borda accent
- `.obs-card-list` / `.obs-card` / `.obs-card-body` / `.obs-card-text` / `.obs-card-date`
- `.obs-card-edit` — botão de edição por card, visível para todos (a abertura do modal é restrita por lógica JS)
- `.obs-empty` — estado vazio com estilo muted

---

## Phase 3 — Briefing no Modal de Tarefa e Mindmap

**Objetivo:** Surfacing dos dados de briefing para contexto rápido no modal de edição de tarefa e no mindmap da página inicial.

### 3A — Backend (`PaginaPrincipal/getInfosCard.php`)

Adicionadas duas queries com prepared statements ao endpoint do mindmap:

- **Query 8:** `SELECT nivel, conceito, valor_media, outro_padrao, vidro, esquadria, soleira, acab_calcadas, assets, comp_planta FROM briefing WHERE obra_id = ?`
- **Query 9:** `SELECT id, descricao, data FROM observacao_obra WHERE obra_id = ? ORDER BY ordem ASC`

JSON response agora inclui: `briefing_obra`, `obra_links`, `observacoes_obra`.

### 3B — Mindmap (`PaginaPrincipal/scriptIndex.js`)

Novo nó `"Informações da Obra"` inserido no `rightSlot` do mindmap após o botão Flow Review.

A IIFE `renderInfoObraNode()` renderiza dentro do corpo do nó:

- **Chips** de briefing (`data.briefing_obra`) — pills com campos preenchidos
- **Botões de link** (`data.obra_links`) — Drive, Fotográfico, Review
- **Lista de observações** (`data.observacoes_obra`) — máx. 3, com "+N mais"

### 3C — Estilos do Mindmap (`PaginaPrincipal/styleIndex.css`)

Novas classes: `.mindmap-info-obra`, `.mindmap-briefing-chips`, `.mindmap-briefing-chip`, `.mindmap-briefing-links`, `.mindmap-briefing-link-btn`, `.mindmap-obs-sep`, `.mindmap-obs-list`, `.mindmap-obs-item`.

### 3D — Painel no Modal de Tarefa (`Dashboard/scriptObra.js`)

Adicionado ao início de `atualizarModal()`:

- Caches globais populados em `infosObra()`: `window.__obraBriefing`, `window.__obraLinks`, `window.__obraObservacoes`
- `renderBriefingContextPanel()` injeta `#briefing-context-panel` como `prepend` no `#form-edicao`
- Exibe: chips de briefing (`.bc-chip`), botões de link (`.bc-link-btn`), lista de obs (`.bc-obs-list`)

### 3E — Estilos do Painel (`Dashboard/styleObra.css`)

Novas classes: `.briefing-context-panel`, `.bc-chips`, `.bc-chip`, `.bc-links`, `.bc-link-btn`, `.bc-obs-list`, `.bc-obs-item`, `.bc-obs-more`.

---

## Phase 4 — Unificação + Quick-Link

**Objetivo:** Unificar os dois containers `#infos-obra` existentes em um único `#secao-infos-obra` com navegação por tabs e atualizar o quick-link da header.

### HTML (`Dashboard/obra.php`)

Container único `#secao-infos-obra` substitui o segundo `#infos-obra`:

```
#secao-infos-obra
  └── #obsSection              ← preservado (initQuickAccess verifica getElementById)
        ├── .infos-obra-header
        │     ├── <h1>Informações da Obra</h1>
        │     └── .info-obra-tabs
        │           ├── [Briefing]    → #tab-briefing
        │           └── [Instruções] → #tab-instrucoes
        ├── #tab-briefing.info-tab-content.is-active
        │     └── #briefing
        │           ├── .briefing-group (Padrão)
        │           ├── .briefing-group (Materiais)
        │           ├── .briefing-group (Produção)
        │           └── .briefing-group (Links & Localização)
        └── #tab-instrucoes.info-tab-content (display:none inicial)
              ├── #obsAdd.obs-add-new-btn
              └── #obsCardList.obs-card-list
```

#### IDs preservados (compatibilidade retroativa)

| ID                                          | Motivo                                                 |
| ------------------------------------------- | ------------------------------------------------------ |
| `#obsSection`                               | `initQuickAccess()` faz `getElementById("obsSection")` |
| `#obsAdd`                                   | Event listener existente no JS                         |
| `#briefing`                                 | Referenciado em outras partes do codebase              |
| Todos os inputs (`nivel`, `conceito`, etc.) | `infosObra()`, `setWithArrow()`, `salvarNoBanco()`     |

### Quick-link (`Dashboard/obra.php`)

| Antes                | Depois                     |
| -------------------- | -------------------------- |
| `href="#obsSection"` | `href="#secao-infos-obra"` |
| `fa-note-sticky`     | `fa-clipboard-list`        |
| Label: "Observações" | Label: "Info. Obra"        |

Atualizado em desktop (`#quickAccess`) e mobile (`#quickMobileMenu`).

### CSS (`Dashboard/styleObra.css`)

Novas classes: `.infos-obra-header`, `.info-obra-tabs`, `.info-tab`, `.info-tab.is-active`, `.info-tab-content`.

---

## Tabelas de Banco Envolvidas

| Tabela            | Campos lidos                                                                                                                  | Campos escritos                       |
| ----------------- | ----------------------------------------------------------------------------------------------------------------------------- | ------------------------------------- |
| `briefing`        | `nivel`, `conceito`, `valor_media`, `outro_padrao`, `vidro`, `esquadria`, `soleira`, `acab_calcadas`, `assets`, `comp_planta` | Todos via `salvar.php`                |
| `obra`            | `link_drive`, `link_review`, `fotografico`, `local`, `altura_drone`, `nome_real`, `liberar_modelagem`                         | Todos via `salvar.php`                |
| `observacao_obra` | `id`, `descricao`, `data`, `ordem`                                                                                            | `atualizarOrdem.php`, modal existente |

> Sem migrations de banco — todos os campos já existiam nas tabelas.

---

## Arquivos Modificados

| Arquivo                            | Fase(s)    | Tipo de mudança                                                                       |
| ---------------------------------- | ---------- | ------------------------------------------------------------------------------------- |
| `Dashboard/obra.php`               | 1, 2, 4    | HTML: novo container, grupos, tabs, cards                                             |
| `Dashboard/scriptObra.js`          | 1, 2, 3, 4 | JS: setWithArrow, renderObsCards, initInfosObraUI, caches, renderBriefingContextPanel |
| `Dashboard/styleObra.css`          | 1, 2, 3, 4 | CSS: todos os novos estilos                                                           |
| `PaginaPrincipal/getInfosCard.php` | 3          | PHP: queries 8 e 9, novos campos no JSON                                              |
| `PaginaPrincipal/scriptIndex.js`   | 3          | JS: novo nó no mindmap (rightSlot)                                                    |
| `PaginaPrincipal/styleIndex.css`   | 3          | CSS: estilos do nó mindmap                                                            |

---

## Decisões de Projeto

| Decisão                                          | Justificativa                                                               |
| ------------------------------------------------ | --------------------------------------------------------------------------- |
| Sem migration de banco                           | Todos os campos já existiam; não havia risco de quebrar outros módulos      |
| Painel de briefing no modal = read-only          | Modal é de tarefa, não de obra; edição fica na seção dedicada               |
| Colaboradores não-admins = read-only no briefing | Controle via CSS class `.is-admin` — sem bloqueio server-side adicional     |
| Mindmap no `rightSlot`                           | Posição já reservada no grid do mindmap; consistência visual com outros nós |
| `#obsSection` preservado no DOM                  | Compatibilidade retroativa com `initQuickAccess()` sem refatorar o JS       |
| `salvarNoBancoSilent` separado                   | Manter `salvarNoBanco` original para outros usos; novo fluxo sem toast      |
