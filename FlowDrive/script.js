// ── File Manager state ──────────────────────────────────────────
let allFiles = [];
let activeCatFilter = null;
let sortState = { col: null, dir: 1 };

const CATEGORIAS = {
  1: { name: "Arquitet\u00f4nico", icon: "ri-building-line" },
  2: { name: "Refer\u00eancias", icon: "ri-image-2-line" },
  3: { name: "Paisagismo", icon: "ri-leaf-line" },
  4: { name: "Luminot\u00e9cnico", icon: "ri-lightbulb-line" },
  5: { name: "Estrutural", icon: "ri-hammer-line" },
  6: { name: "Altera\u00e7\u00f5es", icon: "ri-edit-line" },
  7: { name: "\u00c2ngulo Definido", icon: "ri-compass-3-line" },
};

function formatSize(bytes) {
  if (!bytes || Number(bytes) === 0) return "\u2014";
  const b = Number(bytes);
  const KB = 1024;
  const MB = KB * 1024;
  const GB = MB * 1024;
  if (b < KB) return b + " B";
  if (b < MB) return (b / KB).toFixed(0) + " KB";
  if (b < GB) return (b / MB).toFixed(1) + " MB";
  // show GB with two decimals when large, trim trailing .00
  const g = (b / GB).toFixed(2).replace(/\.00$/, "");
  return g + " GB";
}

function fileTypeClass(tipo) {
  const map = {
    PDF: "fi-pdf",
    DWG: "fi-dwg",
    SKP: "fi-skp",
    IMG: "fi-img",
    IFC: "fi-ifc",
  };
  return map[tipo] || "fi-other";
}

function fileTypeIcon(tipo) {
  const icons = {
    PDF: "ri-file-pdf-line",
    DWG: "ri-file-code-line",
    SKP: "ri-cube-line",
    IMG: "ri-image-line",
    IFC: "ri-database-line",
  };
  return icons[tipo] || "ri-file-line";
}

function renderRecents(files) {
  const container = document.getElementById("recentFiles");
  if (!container) return;
  const recents = files.slice(0, 8);
  if (recents.length === 0) {
    container.innerHTML =
      '<span style="font-size:0.82rem;opacity:0.45;">Nenhum arquivo encontrado.</span>';
    return;
  }
  container.innerHTML = recents
    .map((f) => {
      const cls = fileTypeClass(f.tipo);
      const icon = fileTypeIcon(f.tipo);
      const date = f.recebido_em
        ? new Date(f.recebido_em).toLocaleDateString("pt-BR")
        : "\u2014";
      return `<div class="recent-card" data-id="${f.idarquivo}" data-tipo="${f.tipo}">
            <span class="rc-icon ${cls}"><i class="${icon}"></i></span>
            <span class="rc-name">${f.nome_interno || f.nome_original || "\u2014"}</span>
            <span class="rc-proj">${f.projeto || "\u2014"}</span>
            <span class="rc-date">${date}</span>
        </div>`;
    })
    .join("");
  container.querySelectorAll(".recent-card").forEach((card) => {
    card.addEventListener("click", () => {
      if (card.dataset.tipo === "PDF" && card.dataset.id) {
        window.open(
          `visualizar_pdf.php?idarquivo=${encodeURIComponent(card.dataset.id)}`,
          "_blank",
          "noopener",
        );
      }
    });
  });
}

function renderStorageChart(files) {
  const total = files.reduce((s, f) => s + (Number(f.tamanho) || 0), 0);
  const MAX_BYTES = 200 * 1024 * 1024 * 1024;
  const pct = Math.min((total / MAX_BYTES) * 100, 100);
  const fill = document.getElementById("storageFill");
  if (fill) fill.setAttribute("stroke-dasharray", `${pct.toFixed(2)} 100`);
  const usedEl = document.getElementById("storageUsed");
  if (usedEl) usedEl.textContent = formatSize(total);
}

function renderCategories(files) {
  const container = document.getElementById("categoryCards");
  if (!container) return;
  const counts = {};
  const sizes = {};
  files.forEach((f) => {
    const id = f.categoria_id;
    counts[id] = (counts[id] || 0) + 1;
    sizes[id] = (sizes[id] || 0) + (Number(f.tamanho) || 0);
  });
  container.innerHTML = Object.entries(CATEGORIAS)
    .map(([id, cat]) => {
      const count = counts[id] || 0;
      const isActive = String(activeCatFilter) === String(id);
      return `<div class="category-card${isActive ? " active" : ""}" data-cat="${id}">
            <span class="cat-icon"><i class="${cat.icon}"></i></span>
            <div class="cat-info">
                <div class="cat-name">${cat.name}</div>
                <div class="cat-count">${count} arquivo${count !== 1 ? "s" : ""}</div>
                <div class="cat-size">${formatSize(sizes[id] || 0)}</div>
            </div>
        </div>`;
    })
    .join("");
  container.querySelectorAll(".category-card").forEach((card) => {
    card.addEventListener("click", () => {
      const cat = card.dataset.cat;
      activeCatFilter = String(activeCatFilter) === String(cat) ? null : cat;
      const clearBtn = document.getElementById("clearCatFilter");
      if (clearBtn) clearBtn.style.display = activeCatFilter ? "" : "none";
      renderCategories(allFiles);
      renderTable(allFiles);
    });
  });
}

// renderTable é chamado pelo DataTables via createdRow — manter
// compatibilidade com renderCategories que chama renderTable(allFiles)
function renderTable() {
  if (window._dtTable) window._dtTable.ajax.reload(null, false);
}

async function toggleFileStatus(idarquivo, status) {
  const s = (status || "").toLowerCase();
  let action;
  if (s === "atualizado") {
    if (
      !confirm(
        "Mover arquivo para ANTIGO? Ser\u00e1 movido para a pasta OLD no servidor.",
      )
    )
      return;
    action = "antigo";
  } else if (s === "antigo") {
    if (
      !confirm(
        "Marcar arquivo como ATUALIZADO? Ser\u00e1 restaurado para a pasta principal.",
      )
    )
      return;
    action = "atualizado";
  } else {
    const sel = prompt(
      "Status atual: " +
        (status || "N/A") +
        "\nDigite 'A' para ANTIGO ou 'U' para ATUALIZADO",
    );
    if (!sel) return;
    action =
      sel.toUpperCase() === "A"
        ? "antigo"
        : sel.toUpperCase() === "U"
          ? "atualizado"
          : null;
    if (!action) return;
  }
  try {
    const resp = await fetch("moveArquivoStatus.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ idarquivo, action }),
    });
    const j = await resp.json();
    if (j.success) {
      // Recarrega somente a tabela DT (sem refazer recentes/categorias)
      if (window._dtTable) window._dtTable.ajax.reload(null, false);
      else carregarArquivos(_lastFiltros);
    } else alert("Erro: " + (j.error || "erro desconhecido"));
  } catch (err) {
    console.error("Erro ao mudar status", err);
  }
}

// Context menu
const ctxMenu = document.getElementById("ctxMenu");
function openCtxMenu(ev, item) {
  if (!ctxMenu) return;
  ctxMenu.innerHTML = "";
  const addItem = (icon, label, fn) => {
    const d = document.createElement("div");
    d.className = "ctx-menu-item";
    d.innerHTML = `<i class="${icon}"></i> ${label}`;
    d.addEventListener("click", () => {
      closeCtxMenu();
      fn();
    });
    ctxMenu.appendChild(d);
  };
  if (item.tipo === "PDF" && item.idarquivo) {
    addItem("ri-eye-line", "Visualizar PDF", () =>
      window.open(
        `visualizar_pdf.php?idarquivo=${encodeURIComponent(item.idarquivo)}`,
        "_blank",
        "noopener",
      ),
    );
    const sep = document.createElement("div");
    sep.className = "ctx-menu-sep";
    ctxMenu.appendChild(sep);
  }
  if (item.status === "atualizado") {
    addItem("ri-arrow-go-back-line", "Mover para Antigo", () =>
      toggleFileStatus(item.idarquivo, item.status),
    );
  } else if (item.status === "antigo") {
    addItem("ri-checkbox-circle-line", "Marcar como Atualizado", () =>
      toggleFileStatus(item.idarquivo, item.status),
    );
  } else {
    addItem("ri-arrow-go-back-line", "Mover para Antigo", () =>
      toggleFileStatus(item.idarquivo, "atualizado"),
    );
    addItem("ri-checkbox-circle-line", "Marcar como Atualizado", () =>
      toggleFileStatus(item.idarquivo, "pendente"),
    );
  }
  const btnRect = ev.currentTarget.getBoundingClientRect();
  const x = btnRect.right;
  const y = btnRect.bottom + 4;
  ctxMenu.style.left = Math.min(x, window.innerWidth - 200) + "px";
  ctxMenu.style.top = Math.min(y, window.innerHeight - 200) + "px";
  ctxMenu.style.display = "block";
}

function closeCtxMenu() {
  if (ctxMenu) ctxMenu.style.display = "none";
}
document.addEventListener("click", closeCtxMenu);

// ── Modal ────────────────────────────────────────────────────────
const modal = document.getElementById("uploadModal");
const btnOpen = document.getElementById("btnUpload");

function openModal() {
  // Pre-fill Projeto from active filter
  const obraVal = document.getElementById("filter_obra")?.value || "";
  if (obraVal) {
    const sel = modal.querySelector('select[name="obra_id"]');
    if (sel) sel.value = obraVal;
  }
  // Pre-fill Categoria from active category filter
  if (activeCatFilter) {
    const sel = modal.querySelector('select[name="tipo_categoria"]');
    if (sel) sel.value = activeCatFilter;
  }
  modal.style.display = "flex";
}

function closeModal() {
  modal.style.display = "none";
  // reset drop zone preview
  const preview = document.getElementById("dropZonePreview");
  if (preview) preview.innerHTML = "";
  const dz = document.getElementById("dropZone");
  if (dz) dz.classList.remove("drop-zone--active", "drop-zone--over");
}

btnOpen.addEventListener("click", openModal);
document.getElementById("closeModal").addEventListener("click", closeModal);
const closeModal2El = document.getElementById("closeModal2");
if (closeModal2El) closeModal2El.addEventListener("click", closeModal);
// Close on backdrop click
modal.addEventListener("click", (e) => {
  if (e.target === modal) closeModal();
});

// ── Drop-zone inside modal ────────────────────────────────────────
(function initDropZone() {
  const dz = document.getElementById("dropZone");
  const fileInput = document.getElementById("arquivoFile");
  const preview = document.getElementById("dropZonePreview");
  if (!dz || !fileInput) return;

  // Clicking anywhere on the drop zone triggers file picker
  dz.addEventListener("click", (e) => {
    if (e.target === fileInput) return;
    fileInput.click();
  });

  function setFiles(files) {
    // Assign to input via DataTransfer
    try {
      const dt = new DataTransfer();
      Array.from(files).forEach((f) => dt.items.add(f));
      fileInput.files = dt.files;
    } catch (_) {}
    renderPreview(files);
    dz.classList.add("drop-zone--active");
    atualizarSufixos();
  }

  function renderPreview(files) {
    if (!preview) return;
    preview.innerHTML = Array.from(files)
      .map((f) => {
        const size =
          f.size < 1024 * 1024
            ? (f.size / 1024).toFixed(0) + " KB"
            : (f.size / (1024 * 1024)).toFixed(1) + " MB";
        const ext = f.name.split(".").pop().toUpperCase();
        const cls = fileTypeClass(ext);
        return `<span class="dz-chip">
          <span class="fi-badge ${cls}" style="font-size:0.7rem;padding:2px 5px;"><i class="${fileTypeIcon(ext)}"></i></span>
          <span class="dz-chip-name">${f.name}</span>
          <span class="dz-chip-size">${size}</span>
        </span>`;
      })
      .join("");
  }

  fileInput.addEventListener("change", () => {
    if (fileInput.files.length) renderPreview(fileInput.files);
    atualizarSufixos();
  });

  dz.addEventListener("dragover", (e) => {
    e.preventDefault();
    dz.classList.add("drop-zone--over");
  });
  dz.addEventListener("dragleave", () =>
    dz.classList.remove("drop-zone--over"),
  );
  dz.addEventListener("drop", (e) => {
    e.preventDefault();
    dz.classList.remove("drop-zone--over");
    const files = e.dataTransfer?.files;
    if (files?.length) setFiles(files);
  });
})();

// ── Global page drag-and-drop → open modal ────────────────────────
(function initPageDrop() {
  let dragCounter = 0;
  const overlay = document.createElement("div");
  overlay.id = "pageDragOverlay";
  overlay.innerHTML = `<div class="pdo-inner"><i class="ri-inbox-archive-line"></i><span>Solte para fazer upload</span></div>`;
  document.body.appendChild(overlay);

  document.addEventListener("dragenter", (e) => {
    if (!e.dataTransfer?.types?.includes("Files")) return;
    // ignore drags inside the modal itself
    if (modal.contains(e.target)) return;
    dragCounter++;
    if (dragCounter === 1) overlay.classList.add("pdo--visible");
  });
  document.addEventListener("dragleave", (e) => {
    if (modal.contains(e.target)) return;
    dragCounter = Math.max(0, dragCounter - 1);
    if (dragCounter === 0) overlay.classList.remove("pdo--visible");
  });
  document.addEventListener("dragover", (e) => {
    if (!modal.contains(e.target)) e.preventDefault();
  });
  document.addEventListener("drop", (e) => {
    if (modal.contains(e.target)) return;
    e.preventDefault();
    overlay.classList.remove("pdo--visible");
    dragCounter = 0;
    const files = e.dataTransfer?.files;
    if (!files?.length) return;
    openModal();
    // Give the modal a tick to render, then inject files
    requestAnimationFrame(() => {
      const dz = document.getElementById("dropZone");
      const fileInput = document.getElementById("arquivoFile");
      const preview = document.getElementById("dropZonePreview");
      if (!fileInput) return;
      try {
        const dt = new DataTransfer();
        Array.from(files).forEach((f) => dt.items.add(f));
        fileInput.files = dt.files;
      } catch (_) {}
      if (dz) dz.classList.add("drop-zone--active");
      if (preview) {
        preview.innerHTML = Array.from(files)
          .map((f) => {
            const size =
              f.size < 1024 * 1024
                ? (f.size / 1024).toFixed(0) + " KB"
                : (f.size / (1024 * 1024)).toFixed(1) + " MB";
            const ext = f.name.split(".").pop().toUpperCase();
            return `<span class="dz-chip">
              <span class="fi-badge ${fileTypeClass(ext)}" style="font-size:0.7rem;padding:2px 5px;"><i class="${fileTypeIcon(ext)}"></i></span>
              <span class="dz-chip-name">${f.name}</span>
              <span class="dz-chip-size">${size}</span>
            </span>`;
          })
          .join("");
      }
      atualizarSufixos();
    });
  });
})();

let _lastFiltros = {};

// ── Helpers para renderizar células (usados pelo DataTables columns.render) ──
function _renderFileBadge(tipo) {
  const cls = fileTypeClass(tipo);
  const icon = fileTypeIcon(tipo);
  return `<span class="fi-badge ${cls}"><i class="${icon}"></i></span>`;
}
function _renderNome(nome_interno, type, row) {
  const n = nome_interno || row.nome_original || "\u2014";
  return `<span style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;" title="${n}">${n}</span>`;
}
function _renderStatus(status) {
  const cls =
    status === "atualizado"
      ? "status-atualizado"
      : status === "pendente"
        ? "status-pendente"
        : status === "antigo"
          ? "status-antigo"
          : "";
  return `<span class="${cls}">${status || "\u2014"}</span>`;
}
function _renderActions(data, type, item) {
  const canView = item.tipo === "PDF" && item.idarquivo;
  const toggleIcon =
    item.status === "atualizado"
      ? "ri-arrow-go-back-line"
      : "ri-checkbox-circle-line";
  const toggleTitle =
    item.status === "atualizado"
      ? "Mover para Antigo"
      : "Marcar como Atualizado";
  return `<div class="action-cell">
    <button class="action-btn btn-view" title="Ver PDF" ${canView ? "" : 'disabled style="opacity:0.2;cursor:default;"'}><i class="ri-eye-line"></i></button>
    <button class="action-btn btn-toggle" title="${toggleTitle}"><i class="${toggleIcon}"></i></button>
    <button class="action-btn btn-more" title="Mais op\u00e7\u00f5es"><i class="ri-more-2-fill"></i></button>
  </div>`;
}

// ── DataTables init ───────────────────────────────────────────────────────────
function buildAjaxParams(d) {
  // Injeta filtros de obra/tipo/arquivo/categoria nos parâmetros do DataTables
  const obra = document.getElementById("filter_obra")?.value || "";
  const tipo = document.getElementById("filter_tipo")?.value || "";
  const tipoArq = document.getElementById("filter_tipo_arquivo")?.value || "";
  if (obra) d.obra_id = obra;
  if (tipo) d.tipo = tipo;
  if (tipoArq) d.tipo_arquivo = tipoArq;
  if (activeCatFilter) d.categoria_id = activeCatFilter;
  // Sobrescreve o campo de busca do DataTables com nosso input customizado
  const search = document.getElementById("fm-search")?.value || "";
  if (!d.search) d.search = {};
  d.search.value = search;
}

function initDataTable() {
  if ($.fn.DataTable.isDataTable("#tabelaArquivos")) {
    $("#tabelaArquivos").DataTable().destroy();
  }

  window._dtTable = $("#tabelaArquivos").DataTable({
    serverSide: true,
    processing: true,
    searching: true, // será controlado pelo nosso input
    ordering: true,
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
    language: {
      processing: "Carregando...",
      search: "Buscar:",
      lengthMenu: "Exibir _MENU_ por página",
      info: "Mostrando _START_–_END_ de _TOTAL_ arquivos",
      infoEmpty: "Nenhum arquivo encontrado",
      infoFiltered: "(filtrado de _MAX_ arquivos)",
      paginate: { previous: "←", next: "→" },
      zeroRecords: "Nenhum arquivo encontrado",
    },
    dom: "rtip", // sem search/length nativo do DT — usamos os nossos
    ajax: {
      url: "getArquivos.php",
      type: "GET",
      data: buildAjaxParams,
    },
    order: [[6, "desc"]], // Data desc por padrão
    columns: [
      {
        data: "tipo",
        orderable: false,
        className: "text-center",
        render: (_d, _t, row) => _renderFileBadge(row.tipo),
      },
      { data: "nome_interno", render: _renderNome },
      { data: "projeto", defaultContent: "\u2014" },
      {
        data: "categoria_id",
        orderable: false,
        render: (id) =>
          `<span class="cat-badge">${CATEGORIAS[id]?.name || "\u2014"}</span>`,
      },
      { data: "tipo", defaultContent: "\u2014" },
      { data: "tamanho", render: (v) => formatSize(v) },
      {
        data: "recebido_em",
        render: (v) => (v ? new Date(v).toLocaleDateString("pt-BR") : "\u2014"),
      },
      { data: "status", className: "statusTd", render: _renderStatus },
      {
        data: null,
        orderable: false,
        className: "th-actions",
        render: _renderActions,
      },
    ],
    createdRow(row, data) {
      // Wiring dos botões de ação em cada linha criada
      const canView = data.tipo === "PDF" && data.idarquivo;
      const btnView = row.querySelector(".btn-view");
      const btnToggle = row.querySelector(".btn-toggle");
      const btnMore = row.querySelector(".btn-more");
      if (canView && btnView) {
        btnView.addEventListener("click", (e) => {
          e.stopPropagation();
          window.open(
            `visualizar_pdf.php?idarquivo=${encodeURIComponent(data.idarquivo)}`,
            "_blank",
            "noopener",
          );
        });
      }
      if (btnToggle) {
        btnToggle.addEventListener("click", async (e) => {
          e.stopPropagation();
          await toggleFileStatus(data.idarquivo, data.status);
        });
      }
      if (btnMore) {
        btnMore.addEventListener("click", (e) => {
          e.stopPropagation();
          openCtxMenu(e, data);
        });
      }
    },
    drawCallback(settings) {
      // Atualiza contador de total após cada draw
      const api = this.api();
      const total = api.page.info().recordsDisplay;
      const el = document.getElementById("totalCount");
      if (el) el.textContent = total + " arquivo" + (total !== 1 ? "s" : "");
    },
  });
}

// ── Carrega recentes + categorias (sem server-side, limit pequeno) ────────────
async function carregarArquivos(filtros = {}) {
  _lastFiltros = filtros;
  try {
    // Recentes: busca apenas 8 mais novos (modo legado, sem 'draw')
    const params = new URLSearchParams({ limit: 8 });
    Object.entries(filtros).forEach(([k, v]) => {
      if (v) params.set(k, v);
    });
    const resp = await fetch("getArquivos.php?" + params.toString());
    const recentes = await resp.json();
    renderRecents(recentes);

    // Categorias: busca todos sem limit para ter contagem precisa
    const paramsCat = new URLSearchParams();
    Object.entries(filtros).forEach(([k, v]) => {
      if (v) paramsCat.set(k, v);
    });
    const respCat = await fetch("getArquivos.php?" + paramsCat.toString());
    allFiles = await respCat.json();
    renderCategories(allFiles);
    renderStorageChart(allFiles);
  } catch (err) {
    console.error("Erro ao carregar arquivos:", err);
  }

  // (Re)inicializa DataTables — tabela vai buscar dados sozinha via ajax
  if (typeof $ !== "undefined" && $.fn && $.fn.DataTable) {
    initDataTable();
  }
}

// Carrega na inicialização
$(document).ready(function () {
  carregarArquivos();
});

// ── Wire filtros e controles FM ───────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
  const obraFilter = document.getElementById("filter_obra");
  const tipoFilter = document.getElementById("filter_tipo");
  const tipoArquivoFilter = document.getElementById("filter_tipo_arquivo");
  const searchInput = document.getElementById("fm-search");
  const clearCatBtn = document.getElementById("clearCatFilter");

  function aplicarFiltros() {
    const filtros = {};
    if (obraFilter?.value) filtros.obra_id = obraFilter.value;
    if (tipoFilter?.value) filtros.tipo = tipoFilter.value;
    if (tipoArquivoFilter?.value)
      filtros.tipo_arquivo = tipoArquivoFilter.value;
    carregarArquivos(filtros);
  }

  [obraFilter, tipoFilter, tipoArquivoFilter].forEach((el) =>
    el?.addEventListener("change", aplicarFiltros),
  );

  // Busca inline — delega para o DataTables (server-side)
  let searchTimer;
  searchInput?.addEventListener("input", () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      if (window._dtTable) window._dtTable.ajax.reload(null, false);
    }, 300);
  });

  // Limpar filtro de categoria
  clearCatBtn?.addEventListener("click", () => {
    activeCatFilter = null;
    clearCatBtn.style.display = "none";
    renderCategories(allFiles);
    if (window._dtTable) window._dtTable.ajax.reload(null, false);
  });
});

const tipoArquivoSelect = document.querySelector('select[name="tipo_arquivo"]');
const tipoImagemSelect = document.querySelector('select[name="tipo_imagem[]"]');
const referenciasContainer = document.getElementById("referenciasContainer");
const arquivoFile = document.getElementById("arquivoFile");
const tipoCategoria = document.getElementById("tipo_categoria");

// ── Sufixo helpers (Select2 + DB) ────────────────────────────────
let _sufixosCache = {};      // cache: { [tipoArquivo]: string[] }
let _currentTipoArquivo = "";

// Fallback list when DB is unreachable
const SUFIXOS = {
  DWG: [
    "TERREO",
    "LAZER",
    "COBERTURA",
    "MEZANINO",
    "CORTES",
    "GERAL",
    "TIPO",
    "GARAGEM",
    "FACHADA",
    "DUPLEX",
    "ROOFTOP",
    "LOGO",
    "ACABAMENTOS",
    "ESQUADRIA",
    "ARQUITETONICO",
    "REFERENCIA",
    "IMPLANTACAO",
    "SUBSOLO",
    "G1",
    "G2",
    "G3",
    "G4",
    "DUPLEX_SUPERIOR",
    "DUPLEX_INFERIOR",
    "TOON",
    "DIFERENCIADO",
    "CAIXA_AGUA",
    "CASA_MAQUINA",
    "PENTHOUSE",
    "ROOFTOP",
  ],
  PDF: [
    "DOCUMENTACAO",
    "RELATORIO",
    "LOGO",
    "ARQUITETONICO",
    "REFERENCIA",
    "ESQUADRIA",
    "ACABAMENTOS",
    "TIPOLOGIA",
    "IMPLANTACAO",
    "SUBSOLO",
    "G1",
    "G2",
    "G3",
    "G4",
    "DUPLEX_SUPERIOR",
    "DUPLEX_INFERIOR",
    "TERREO",
    "LAZER",
    "COBERTURA",
    "MEZANINO",
    "CORTES",
    "GERAL",
    "TIPO",
    "GARAGEM",
    "FACHADA",
    "TOON",
    "DIFERENCIADO",
    "PENTHOUSE",
    "ROOFTOP",
  ],
  SKP: ["MODELAGEM", "REFERENCIA", "TOON", "DIFERENCIADO", "PENTHOUSE"],
  IMG: [
    "FACHADA",
    "INTERNA",
    "EXTERNA",
    "UNIDADE",
    "LOGO",
    "REFERENCIAS",
    "GERAL",
    "TOON",
    "DIFERENCIADO",
    "PENTHOUSE",
  ],
  IFC: ["BIM"],
  Outros: ["GERAL", "TOON", "DIFERENCIADO", "PENTHOUSE"],
};

/** Normalize: uppercase, spaces → _, strip non-alphanumeric */
function normalizeSufixo(val) {
  return val.trim().toUpperCase().replace(/\s+/g, "_").replace(/[^A-Z0-9_]/g, "");
}

/** Validate: max 2 words separated by a single underscore */
function validarSufixo(val) {
  if (!val) return true;
  const up = normalizeSufixo(val);
  if (!up) return false;
  const parts = up.split("_");
  return parts.length <= 2 && parts.every((p) => p.length > 0);
}

/**
 * (Re)initialise Select2 on `selector` with the given string array as options.
 * Tags mode allows the user to type a new value.
 */
function initSufixoSelect2(selector, options) {
  if (typeof $ === "undefined" || !$.fn || !$.fn.select2) return;
  const $el = $(selector);
  if (!$el.length) return;
  try { $el.select2("destroy"); } catch (_) {}

  const data = options.map((v) => ({ id: v, text: v }));

  $el.select2({
    data,
    tags: true,
    placeholder: "Selecione ou digite…",
    allowClear: true,
    width: "100%",
    dropdownParent: $("#uploadModal"),
    language: {
      noResults: () => "Nenhum resultado. Digite para criar.",
    },
    createTag(params) {
      const term = normalizeSufixo(params.term);
      if (!term) return null;
      if (!validarSufixo(term)) return null;
      // don't create a tag that already exists
      if (options.some((o) => o.toUpperCase() === term)) return null;
      return { id: term, text: term, newTag: true };
    },
    templateResult(item) {
      if (item.newTag) {
        const el = document.createElement("span");
        el.innerHTML = `<i class="ri-add-line"></i> <small>Criar</small> <strong>${item.id}</strong>`;
        return el;
      }
      return item.text || item.id;
    },
  });
}

/** Load suffix list from DB (with in-memory cache). Falls back to SUFIXOS. */
async function carregarSufixos(tipoArquivo) {
  if (!tipoArquivo) return [];
  if (_sufixosCache[tipoArquivo]) return _sufixosCache[tipoArquivo];
  try {
    const resp = await fetch(`getSufixos.php?tipo_arquivo=${encodeURIComponent(tipoArquivo)}`);
    const data = await resp.json();
    const result = Array.isArray(data) && data.length ? data : (SUFIXOS[tipoArquivo] || []);
    _sufixosCache[tipoArquivo] = result;
    return result;
  } catch (_) {
    return SUFIXOS[tipoArquivo] || [];
  }
}

/** POST a new suffix value to the DB and invalidate cache. */
async function salvarSufixoNovo(tipoArquivo, valor) {
  if (!tipoArquivo || !valor) return;
  const up = normalizeSufixo(valor);
  if (!up || !validarSufixo(up)) return;
  try {
    await fetch("getSufixos.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ tipo_arquivo: tipoArquivo, valor: up }),
    });
    delete _sufixosCache[tipoArquivo]; // invalidate so next load re-fetches
  } catch (_) {}
}

/**
 * When multiple files are selected in geral mode, show a per-file suffix row
 * instead of the single global sufixo field.
 */
function renderPerFileSufixos(files, sufixosOptions) {
  const container = document.getElementById("perFileSufixoContainer");
  const fieldSufixo = document.getElementById("fieldSufixo");
  if (!container) return;

  // Destroy existing Select2 instances inside container before clearing HTML
  container.querySelectorAll(".sufixo-select2").forEach((el) => {
    try { $(el).select2("destroy"); } catch (_) {}
  });

  if (!files || files.length <= 1) {
    container.style.display = "none";
    container.innerHTML = "";
    return;
  }

  // Multiple files: hide the global sufixo field, show per-file container
  if (fieldSufixo) fieldSufixo.style.display = "none";
  container.innerHTML = '<div class="pf-header">Sufixo por arquivo</div>';
  container.style.display = "";

  // ── "Apply to all" row ──────────────────────────────────────────
  const applyAllRow = document.createElement("div");
  applyAllRow.className = "pf-apply-all-row";
  applyAllRow.innerHTML = `
    <select id="sufixo_apply_all" class="sufixo-apply-all-select" style="width:100%"></select>
    <button type="button" id="btnApplyAllSufixo" class="btn-apply-all" title="Aplicar para todos">
      <i class="ri-check-double-line"></i> Aplicar para todos
    </button>`;
  container.appendChild(applyAllRow);
  initSufixoSelect2("#sufixo_apply_all", sufixosOptions);

  document.getElementById("btnApplyAllSufixo")?.addEventListener("click", () => {
    const applyVal = $("#sufixo_apply_all").val();
    if (!applyVal) return;
    container.querySelectorAll(".sufixo-select2").forEach((el) => {
      const $el = $(el);
      // If option doesn't exist yet, add it (tags mode)
      if (!$el.find(`option[value="${applyVal}"]`).length) {
        $el.append(new Option(applyVal, applyVal, true, true));
      }
      $el.val(applyVal).trigger("change");
    });
  });

  Array.from(files).forEach((file, i) => {
    const uid = `sufixo_pf_${i}`;
    const row = document.createElement("div");
    row.className = "pf-sufixo-row";
    row.innerHTML = `
      <span class="pf-filename" title="${file.name}">${file.name}</span>
      <div class="pf-select-wrap">
        <select id="${uid}" name="sufixo_por_arquivo[]" class="sufixo-select2" style="width:100%"></select>
      </div>`;
    container.appendChild(row);
    initSufixoSelect2(`#${uid}`, sufixosOptions);
  });
}

/**
 * Unified updater: called after tipo_arquivo changes or files change.
 * Decides whether to show single sufixo (Select2) or per-file rows.
 */
async function atualizarSufixos() {
  const tipoArquivo = tipoArquivoSelect ? tipoArquivoSelect.value : "";
  _currentTipoArquivo = tipoArquivo;

  const fieldSufixo = document.getElementById("fieldSufixo");
  const container = document.getElementById("perFileSufixoContainer");

  if (!tipoArquivo) {
    if (fieldSufixo) fieldSufixo.style.display = "none";
    if (container) { container.style.display = "none"; container.innerHTML = ""; }
    return;
  }

  const options = await carregarSufixos(tipoArquivo);
  const modo = document.querySelector('input[name="refsSkpModo"]:checked')?.value || "geral";
  const files = document.getElementById("arquivoFile")?.files;
  const isMultiGeral = modo === "geral" && files && files.length > 1;

  if (isMultiGeral) {
    // Per-file mode: hide global field and show per-file rows
    if (fieldSufixo) fieldSufixo.style.display = "none";
    renderPerFileSufixos(files, options);
  } else {
    // Single sufixo: destroy per-file rows, show global field
    if (container) {
      container.querySelectorAll(".sufixo-select2").forEach((el) => {
        try { $(el).select2("destroy"); } catch (_) {}
      });
      container.style.display = "none";
      container.innerHTML = "";
    }
    if (fieldSufixo) fieldSufixo.style.display = "";
    initSufixoSelect2("#sufixoSelect", options);
  }
}

tipoArquivoSelect.addEventListener("change", async () => {
  const tipoArquivo = tipoArquivoSelect.value;
  referenciasContainer.innerHTML = "";
  // Mostra o modo para SKP ou REFS
  // Mostrar a opção de modo (geral / porImagem) para todos os tipos — permitir envio por imagem universal
  document.getElementById("refsSkpModo").style.display = "block";

  const modo =
    document.querySelector('input[name="refsSkpModo"]:checked')?.value ||
    "geral";

  // Se modo porImagem, mostrar inputs por imagem para TODOS os tipos configurados
  if (modo === "porImagem") {
    const obraId = document.querySelector('select[name="obra_id"]').value;
    const tipoImagemIds = Array.from(tipoImagemSelect.selectedOptions).map(
      (o) => o.value,
    );

    if (!obraId || tipoImagemIds.length === 0) return;

    const res = await fetch("getImagensObra.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ obra_id: obraId, tipo_imagem: tipoImagemIds }),
    });

    arquivoFile.style.display = "none";
    arquivoFile.required = false;
    arquivoFile.disabled = true;

    const imagens = await res.json();
    imagens.forEach((img) => {
      const div = document.createElement("div");
      div.className = "ref-imagem-block";
      div.innerHTML = `
                <label>${img.imagem_nome}</label>
                <input type="file" name="arquivos_por_imagem[${img.id}][]" multiple>
                <textarea name="observacoes_por_imagem[${img.id}]" placeholder="Observação para esta imagem (opcional)" rows="2" style="width:100%;margin-top:6px;"></textarea>
            `;
      referenciasContainer.appendChild(div);
    });
  } else {
    // Upload geral
    arquivoFile.style.display = "block";
    arquivoFile.required = true;
    arquivoFile.disabled = false;
  }

  // Load suffix options from DB and (re)init Select2
  await atualizarSufixos();
});
document.getElementById("refsSkpModo").addEventListener("change", () => {
  tipoArquivoSelect.dispatchEvent(new Event("change"));
});

function buildFormData(form) {
  const formData = new FormData();

  const inputs = form.querySelectorAll("input, select, textarea");
  inputs.forEach((input) => {
    // ignore file inputs here (handled below)
    if (input.type === "file") return;

    // skip inputs without a name attribute
    if (!input.name) return;

    // checkboxes: only append if checked
    if (input.type === "checkbox") {
      if (input.checked) formData.append(input.name, input.value || "on");
      return;
    }

    // radios: only append the checked one
    if (input.type === "radio") {
      if (input.checked) formData.append(input.name, input.value);
      return;
    }

    // multi-select handling
    if (input.tagName === "SELECT" && input.multiple) {
      Array.from(input.selectedOptions).forEach((option) =>
        formData.append(input.name, option.value),
      );
      return;
    }

    // default for other inputs/selects/textareas
    formData.append(input.name, input.value);
  });

  // arquivos
  const fileInputs = form.querySelectorAll('input[type="file"]');
  fileInputs.forEach((input) => {
    Array.from(input.files).forEach((file) => {
      if (file.size > 0) formData.append(input.name, file);
    });
  });

  return formData;
}

document
  .getElementById("uploadForm")
  .addEventListener("submit", async function (e) {
    e.preventDefault();

    const form = e.target;
    const obra_id = form.obra_id.value;
    const tipo_arquivo = form.tipo_arquivo.value;
    const tipo_categoria = form.tipo_categoria.value;
    const tipo_imagem = Array.from(form["tipo_imagem[]"].selectedOptions).map(
      (o) => o.value,
    );

    // Se modo porImagem, checar por imagem; caso contrário checagem padrão para outros tipos
    const modoSubmit =
      document.querySelector('input[name="refsSkpModo"]:checked')?.value ||
      "geral";
    if (modoSubmit === "porImagem") {
      let imagensInputs =
        referenciasContainer.querySelectorAll('input[type="file"]');
      let existeAlgum = false;

      for (let input of imagensInputs) {
        // 🔎 Pula inputs sem arquivos
        if (!input.files || input.files.length === 0) continue;

        let imagemIdMatch = input.name.match(/\[(\d+)\]/);
        if (!imagemIdMatch) continue; // segurança caso não bata o regex
        let imagemId = imagemIdMatch[1];

        // Checa se existe para cada imagem que realmente tem arquivo
        const checkRes = await fetch("checkArquivoExistente.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            obra_id,
            tipo_arquivo,
            tipo_categoria,
            tipo_imagem,
            imagem_id: imagemId,
            tipo_categoria: tipo_categoria,
          }),
        });
        const checkData = await checkRes.json();
        if (checkData.existe) existeAlgum = true;
      }

      if (existeAlgum) {
        const confirm = await Swal.fire({
          title: "Já existe arquivo para uma ou mais imagens!",
          text: "Deseja substituir os arquivos existentes?",
          icon: "warning",
          showCancelButton: true,
          confirmButtonText: "Sim, substituir",
          cancelButtonText: "Não, continuar",
        });

        form.querySelector('[name="flag_substituicao"]').checked =
          confirm.isConfirmed;
      }
    } else {
      // Checagem padrão para outros tipos
      const checkRes = await fetch("checkArquivoExistente.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          obra_id,
          tipo_arquivo,
          tipo_imagem,
          tipo_categoria,
        }),
      });
      const checkData = await checkRes.json();

      if (checkData.existe) {
        const confirm = await Swal.fire({
          title: "Já existe arquivo desse tipo!",
          text: "Deseja substituir o arquivo existente?",
          icon: "warning",
          showCancelButton: true,
          confirmButtonText: "Sim, substituir",
          cancelButtonText: "Não, continuar",
        });

        if (confirm.isConfirmed) {
          form.querySelector('[name="flag_substituicao"]').checked = true;
        } else {
          // Usuário cancelou, garante que a substituição continue como false
          form.querySelector('[name="flag_substituicao"]').checked = false;
          // Aqui não precisa retornar, o envio continua
        }
      }
    }

    // Save any new typed sufixos to DB before building the payload
    if (_currentTipoArquivo) {
      const cached = _sufixosCache[_currentTipoArquivo] || [];
      const allSufixoSelects = form.querySelectorAll("#sufixoSelect, .sufixo-select2");
      for (const sel of allSufixoSelects) {
        const val = sel.value ? normalizeSufixo(sel.value) : "";
        if (val && !cached.includes(val)) await salvarSufixoNovo(_currentTipoArquivo, val);
      }
    }

    // Agora sim monta o FormData
    const formData = buildFormData(form);

    const modo =
      document.querySelector('input[name="refsSkpModo"]:checked')?.value ||
      "geral";
    formData.append("refsSkpModo", modo);

    // Remover arquivos vazios
    for (let [key, value] of formData.entries()) {
      if (value instanceof File && value.size === 0) {
        formData.delete(key);
      }
    }

    // Debug
    for (let [key, value] of formData.entries()) {
      console.log("Final:", key, value);
    }
    try {
      // Preparar resumo dos parâmetros para exibir no Swal
      const paramsSummary = [];
      for (let [key, value] of formData.entries()) {
        if (value instanceof File) continue; // arquivos listados separadamente
        if (paramsSummary.find((p) => p.key === key)) continue; // evita chaves repetidas
        paramsSummary.push({ key, value: String(value) });
      }

      // Lista de nomes de arquivos e tamanho total
      const fileNames = [];
      let totalBytes = 0;
      for (let [k, v] of formData.entries()) {
        if (v instanceof File) {
          fileNames.push(v.name + " (" + Math.round(v.size / 1024) + " KB)");
          totalBytes += v.size;
        }
      }

      // Mostrar Swal com barra de progresso e detalhes
      const swalHtml = `
            <div style="text-align:left;margin-bottom:8px">
                <strong>Parâmetros:</strong>
                <strong>Arquivos (${fileNames.length}):</strong>
                <ul style="padding-left:18px;margin:6px 0">${fileNames.map((n) => `<li>${n}</li>`).join("")}</ul>
            </div>
            <div style="margin-top:8px">
                <div id="swal-upload-progress" style="width:100%;background:#eee;border-radius:6px;overflow:hidden;height:14px">
                    <div id="swal-upload-bar" style="width:0%;height:100%;background:#3085d6"></div>
                </div>
                <div id="swal-upload-info" style="margin-top:6px;font-size:13px;color:#666">0% - 0 KB de ${Math.round(totalBytes / 1024)} KB</div>
            </div>`;

      let xhr = new XMLHttpRequest();
      xhr.open("POST", "upload.php", true);

      // Mostrar modal Swal e iniciar upload sem aguardar sua resolução
      let startTime = null;

      const swalPromise = Swal.fire({
        title: "Enviando arquivos",
        html: swalHtml,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: "Cancelar",
        allowOutsideClick: false,
        allowEscapeKey: false,
        allowEnterKey: false,
        didOpen: () => {
          // Avoid backdrop clicks bubbling to global handlers (which might close other modals)
          try {
            const container = Swal.getContainer();
            if (container) {
              ["click", "mousedown", "touchstart", "pointerdown"].forEach(
                (evt) => {
                  container.addEventListener(
                    evt,
                    (e) => e.stopPropagation(),
                    true,
                  );
                },
              );
            }
          } catch (e) {}
        },
        willOpen: () => {
          // attach progress handler
          const container = Swal.getHtmlContainer();
          const bar = container
            ? container.querySelector("#swal-upload-bar")
            : null;
          const info = container
            ? container.querySelector("#swal-upload-info")
            : null;

          startTime = Date.now();

          xhr.upload.onprogress = function (e) {
            if (e.lengthComputable) {
              const now = Date.now();
              const elapsed = (now - startTime) / 1000; // seconds
              const uploadedMB = e.loaded / (1024 * 1024);
              const totalMB = e.total / (1024 * 1024);
              const percent = (e.loaded / e.total) * 100;
              const speed = uploadedMB / (elapsed || 0.0001); // MB/s
              const remainingMB = Math.max(0, totalMB - uploadedMB);
              const estimatedTime = remainingMB / (speed || 0.0001);

              if (bar) bar.style.width = percent + "%";
              if (info) {
                info.textContent = `${percent.toFixed(2)}% - ${Math.round(e.loaded / 1024)} KB de ${Math.round(e.total / 1024)} KB — Tempo: ${elapsed.toFixed(1)}s — Velocidade: ${speed.toFixed(2)} MB/s — Estimativa: ${estimatedTime.toFixed(1)}s`;
              }
            }
          };

          xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
              // Response handling below after Swal.close()
            }
          };
        },
      });

      // Start sending immediately so progress events update the open Swal
      xhr.send(formData);

      // Prepare a promise that resolves when upload completes (or errors/aborts)
      const uploadPromise = new Promise((resolve, reject) => {
        xhr.onload = function () {
          const rawResponse = xhr.responseText || "";

          if (xhr.status < 200 || xhr.status >= 300) {
            let serverMessage = "";
            try {
              const json = JSON.parse(rawResponse);
              if (Array.isArray(json.errors) && json.errors.length > 0) {
                serverMessage = json.errors.join(" | ");
              } else if (json.error) {
                serverMessage = String(json.error);
              }
            } catch (e) {
              serverMessage = rawResponse.slice(0, 300);
            }

            return reject(
              new Error(
                `Erro ${xhr.status} no upload.${serverMessage ? " " + serverMessage : ""}`,
              ),
            );
          }

          try {
            const json = JSON.parse(rawResponse || "{}");
            resolve(json);
          } catch (err) {
            reject(new Error("Resposta inválida do servidor (JSON)."));
          }
        };
        xhr.onerror = function () {
          reject(new Error("Erro na requisição"));
        };
        xhr.onabort = function () {
          reject(new Error("Envio cancelado pelo usuário"));
        };
      });

      // Race between upload completion and user cancelling the Swal.
      // If user cancels first, abort XHR. If upload completes first, process the response.
      const race = await Promise.race([
        uploadPromise
          .then((res) => ({ type: "upload", res }))
          .catch((err) => ({ type: "upload_error", err })),
        swalPromise.then((res) => ({ type: "swal", res })),
      ]);

      if (
        race.type === "swal" &&
        race.res &&
        race.res.dismiss === Swal.DismissReason.cancel
      ) {
        try {
          xhr.abort();
        } catch (e) {}
        Swal.close();
        throw new Error("Envio cancelado pelo usuário");
      }

      if (race.type === "upload_error") {
        Swal.close();
        throw race.err;
      }

      // At this point upload finished successfully
      Swal.close();
      const uploadResult = race.res;

      const result = uploadResult;
      // Mensagens de sucesso
      if (result.success && result.success.length > 0) {
        result.success.forEach((msg) => {
          Toastify({
            text: msg,
            duration: 3000,
            close: true,
            gravity: "top",
            position: "center",
            backgroundColor: "green",
            stopOnFocus: true,
          }).showToast();
        });

        // Recarrega tabela
        form.reset();
        closeModal();
        if (window._dtTable) {
          window._dtTable.ajax.reload(null, false);
          carregarArquivos(_lastFiltros); // atualiza recentes + categorias
        } else {
          carregarArquivos();
        }
      }

      // Mensagens de erro
      if (result.errors && result.errors.length > 0) {
        result.errors.forEach((msg) => {
          Toastify({
            text: msg,
            duration: 5000,
            close: true,
            gravity: "top",
            position: "center",
            backgroundColor: "red",
            stopOnFocus: true,
          }).showToast();
        });
      }
    } catch (err) {
      console.error(err);
      Swal.close();
      Toastify({
        text: err.message || "Erro ao enviar os arquivos.",
        duration: 5000,
        close: true,
        gravity: "top",
        position: "center",
        backgroundColor: "red",
        stopOnFocus: true,
      }).showToast();
    }
  });
