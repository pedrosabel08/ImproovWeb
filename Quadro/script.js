/* =========================================================
   Quadro de Produção — por Função > agrupado por Obra
   ========================================================= */

// ── Config por função ─────────────────────────────────────
const FUNCAO_CONFIG = {
  1: { icon: "ri-book-2-line", color: "#ffd43b" },
  2: { icon: "ri-cube-line", color: "#74c0fc" },
  3: { icon: "ri-layout-grid-line", color: "#b197fc" },
  4: { icon: "ri-image-2-line", color: "#69db7c" },
  5: { icon: "ri-magic-line", color: "#f783ac" },
  6: { icon: "ri-edit-line", color: "#ffa94d" },
  7: { icon: "ri-map-2-line", color: "#4dd0e1" },
  8: { icon: "ri-filter-line", color: "#ff6b6b" },
};

// ── Drag-to-scroll no kanban ──────────────────────────────
const kanban = document.getElementById("kanban-container");
let isDragging = false,
  startX,
  scrollLeft;

kanban.addEventListener("mousedown", (e) => {
  if (e.target.closest(".kanban-card")) return; // não interfere no clique do card
  isDragging = true;
  kanban.classList.add("dragging");
  startX = e.pageX - kanban.offsetLeft;
  scrollLeft = kanban.scrollLeft;
});
kanban.addEventListener("mousemove", (e) => {
  if (!isDragging) return;
  e.preventDefault();
  kanban.scrollLeft = scrollLeft - (e.pageX - kanban.offsetLeft - startX);
});
["mouseup", "mouseleave"].forEach((ev) =>
  kanban.addEventListener(ev, () => {
    isDragging = false;
    kanban.classList.remove("dragging");
  }),
);

// ── Detail modal ─────────────────────────────────────────
const detailModal = document.getElementById("detail-modal");
const detailTitle = document.getElementById("detail-title");
const detailIcon = document.getElementById("detail-icon");
const detailContent = document.getElementById("detail-content");

// All items fetched for the currently open card (pre-filter)
let allDetailItems = [];
let currentObraNome = ""; // nome_obra completo — usado para navegar ao FlowReview

document
  .getElementById("detail-close")
  .addEventListener("click", fecharDetalhe);

// Close modal when clicking the backdrop
detailModal.addEventListener("click", (e) => {
  if (e.target === detailModal) fecharDetalhe();
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") fecharDetalhe();
});

function fecharDetalhe() {
  detailModal.classList.remove("open");
  document
    .querySelectorAll(".kanban-card.selected")
    .forEach((c) => c.classList.remove("selected"));
}

// ── Raw data store (used for client-side filtering) ──────
let rawData = [];

// ── Refresh button ────────────────────────────────────────
document
  .getElementById("btn-refresh")
  .addEventListener("click", carregarQuadro);

// ── Filter event listeners — kanban ──────────────────────
["filtro-colaborador", "filtro-status", "filtro-tipo"].forEach((id) => {
  document.getElementById(id).addEventListener("change", () => {
    aplicarFiltros();
    // Se o modal estiver aberto, re-filtra também
    if (detailModal.classList.contains("open")) aplicarFiltrosModal();
  });
});

document.getElementById("btn-clear-filters").addEventListener("click", () => {
  document.getElementById("filtro-colaborador").value = "";
  document.getElementById("filtro-status").value = "";
  document.getElementById("filtro-tipo").value = "";
  aplicarFiltros();
  if (detailModal.classList.contains("open")) aplicarFiltrosModal();
});

// ── Filter event listeners — modal ───────────────────────
[
  "modal-filtro-colaborador",
  "modal-filtro-status",
  "modal-filtro-tipo",
].forEach((id) => {
  document.getElementById(id).addEventListener("change", aplicarFiltrosModal);
});

document.getElementById("modal-btn-clear").addEventListener("click", () => {
  document.getElementById("modal-filtro-colaborador").value = "";
  document.getElementById("modal-filtro-status").value = "";
  document.getElementById("modal-filtro-tipo").value = "";
  aplicarFiltrosModal();
});

// ── Utilities ─────────────────────────────────────────────
function sftpToPublicUrl(rawPath) {
  if (!rawPath) return null;
  const p = rawPath.replace(/\\/g, "/");
  const mFull = p.match(
    /\/mnt\/clientes\/\d+\/([^\/]+)\/05\.Exchange\/01\.Input\/(.*)/i,
  );
  if (mFull) {
    return `https://improov.com.br/flow/ImproovWeb/uploads/angulo_definido/${mFull[1]}/${mFull[2]}`;
  }
  const m = p.match(/\/Angulo_definido\/(.*)/i);
  if (m)
    return `https://improov.com.br/flow/ImproovWeb/uploads/angulo_definido/${m[1]}`;
  const idx = p.indexOf("/05.Exchange/01.Input/");
  if (idx >= 0)
    return `https://improov.com.br/flow/ImproovWeb/uploads/${p.substring(idx + "/05.Exchange/01.Input/".length)}`;
  return null;
}

function getImageSrc(raw) {
  if (!raw) return "";
  const pub = sftpToPublicUrl(raw);
  const url = pub || raw;
  if (url.startsWith("http://") || url.startsWith("https://")) return url;
  return `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURIComponent(url)}&w=360&q=70`;
}

function formatarData(str) {
  if (!str) return "—";
  const [y, m, d] = str.split("-");
  return `${d}/${m}/${y}`;
}

function formatarDuracao(min) {
  if (!min || min <= 0) return "—";
  const d = Math.floor(min / 1440);
  const h = Math.floor((min % 1440) / 60);
  const mn = min % 60;
  if (d > 0) return `${d}d ${h}h ${mn}min`;
  if (h > 0) return `${h}h ${mn}min`;
  return `${mn}min`;
}

function showLoading(show) {
  document.getElementById("loading").style.display = show ? "flex" : "none";
}

function isAtrasada(prazoStr) {
  if (!prazoStr) return false;
  const [y, m, d] = prazoStr.split("-").map(Number);
  const hoje = new Date();
  hoje.setHours(0, 0, 0, 0);
  return new Date(y, m - 1, d) < hoje;
}

// ── Populate kanban filters from data ────────────────────
function popularFiltros(data) {
  const selColab = document.getElementById("filtro-colaborador");
  const selTipo = document.getElementById("filtro-tipo");

  const colaboradores = new Set();
  const tipos = new Set();

  data.forEach((item) => {
    if (item.colaboradores) {
      item.colaboradores.split("|||").forEach((c) => {
        if (c.trim()) colaboradores.add(c.trim());
      });
    }
    if (item.tipos_imagem) {
      item.tipos_imagem.split("|||").forEach((t) => {
        if (t && t.trim()) tipos.add(t.trim());
      });
    }
  });

  // Rebuild options (keep first default option)
  selColab.innerHTML = '<option value="">Todos os colaboradores</option>';
  [...colaboradores].sort().forEach((c) => {
    const o = document.createElement("option");
    o.value = c;
    o.textContent = c;
    selColab.appendChild(o);
  });

  selTipo.innerHTML = '<option value="">Todos os tipos</option>';
  [...tipos].sort().forEach((t) => {
    const o = document.createElement("option");
    o.value = t;
    o.textContent = t;
    selTipo.appendChild(o);
  });
}

// ── Aggregate per-tipo rows into one row per (funcao, obra) ─
function agregarPorObra(data) {
  const map = new Map();
  const numFields = [
    "total",
    "nao_iniciado",
    "em_andamento",
    "em_aprovacao",
    "ajuste",
    "aprovado_ajustes",
    "aprovado",
    "hold",
  ];
  data.forEach((item) => {
    const key = `${item.idfuncao}_${item.idobra}`;
    if (!map.has(key)) {
      map.set(key, { ...item });
    } else {
      const m = map.get(key);
      numFields.forEach((f) => {
        m[f] = (parseInt(m[f]) || 0) + (parseInt(item[f]) || 0);
      });
      m.prioridade_alta = Math.min(
        parseInt(m.prioridade_alta) || 3,
        parseInt(item.prioridade_alta) || 3,
      );
      if (
        item.proximo_prazo &&
        (!m.proximo_prazo || item.proximo_prazo < m.proximo_prazo)
      ) {
        m.proximo_prazo = item.proximo_prazo;
      }
      if (item.colaboradores) {
        const existing = new Set(
          (m.colaboradores || "")
            .split("|||")
            .filter(Boolean)
            .map((s) => s.trim()),
        );
        item.colaboradores.split("|||").forEach((c) => existing.add(c.trim()));
        m.colaboradores = [...existing].sort().join("|||");
      }
      if (!m.ultima_imagem && item.ultima_imagem)
        m.ultima_imagem = item.ultima_imagem;
    }
  });
  return [...map.values()];
}

// ── Apply active filters and re-render kanban ─────────────
function aplicarFiltros() {
  const colaborador = document.getElementById("filtro-colaborador").value;
  const status = document.getElementById("filtro-status").value;
  const tipo = document.getElementById("filtro-tipo").value;

  const hasFilter = colaborador || status || tipo;
  document.getElementById("btn-clear-filters").style.display = hasFilter
    ? "inline-flex"
    : "none";

  const filtered = rawData.filter((item) => {
    if (colaborador) {
      const list = item.colaboradores
        ? item.colaboradores.split("|||").map((s) => s.trim())
        : [];
      if (!list.includes(colaborador)) return false;
    }
    if (status) {
      if (!(parseInt(item[status]) > 0)) return false;
    }
    if (tipo) {
      const list = item.tipos_imagem
        ? item.tipos_imagem.split("|||").map((s) => s.trim())
        : [];
      if (!list.includes(tipo)) return false;
    }
    return true;
  });

  // Sem filtro de tipo: agrupa todos os tipos num único card por obra
  const toRender = tipo ? filtered : agregarPorObra(filtered);
  renderKanban(toRender);
}

// ── Apply active filters inside the modal ─────────────────
function aplicarFiltrosModal() {
  // Combines kanban-level filters AND modal-level filters
  const colaborador = document.getElementById("filtro-colaborador").value;
  const tipoKanban = document.getElementById("filtro-tipo").value;
  const modalColaborador = document.getElementById(
    "modal-filtro-colaborador",
  ).value;
  const modalStatus = document.getElementById("modal-filtro-status").value;
  const modalTipo = document.getElementById("modal-filtro-tipo").value;

  const hasFilter = modalColaborador || modalStatus || modalTipo;
  document.getElementById("modal-btn-clear").style.display = hasFilter
    ? "inline-flex"
    : "none";

  const filtered = allDetailItems.filter((item) => {
    // Kanban-level colaborador filter
    if (colaborador && item.nome_colaborador !== colaborador) return false;
    // Kanban-level tipo filter
    if (tipoKanban && item.tipo_imagem !== tipoKanban) return false;
    // Modal colaborador
    if (modalColaborador && item.nome_colaborador !== modalColaborador)
      return false;
    // Modal status (exact string match with fi.status)
    if (modalStatus && item.status !== modalStatus) return false;
    // Modal tipo
    if (modalTipo && item.tipo_imagem !== modalTipo) return false;
    return true;
  });

  renderDetalhes(filtered);
}

// ── Populate modal filters from fetched detail items ──────
function popularFiltrosModal(items) {
  const selColab = document.getElementById("modal-filtro-colaborador");
  const selTipo = document.getElementById("modal-filtro-tipo");

  const colaboradores = new Set();
  const tipos = new Set();

  items.forEach((item) => {
    if (item.nome_colaborador) colaboradores.add(item.nome_colaborador);
    if (item.tipo_imagem) tipos.add(item.tipo_imagem);
  });

  // Pre-select kanban filters if set
  const kColaborador = document.getElementById("filtro-colaborador").value;
  const kTipo = document.getElementById("filtro-tipo").value;

  selColab.innerHTML = '<option value="">Todos os colaboradores</option>';
  [...colaboradores].sort().forEach((c) => {
    const o = document.createElement("option");
    o.value = c;
    o.textContent = c;
    if (c === kColaborador) o.selected = true;
    selColab.appendChild(o);
  });

  selTipo.innerHTML = '<option value="">Todos os tipos</option>';
  [...tipos].sort().forEach((t) => {
    const o = document.createElement("option");
    o.value = t;
    o.textContent = t;
    if (t === kTipo) o.selected = true;
    selTipo.appendChild(o);
  });

  // Pre-select status from kanban filter (convert key → label)
  const statusKeyToLabel = {
    em_andamento: "Em andamento",
    em_aprovacao: "Em aprovação",
    ajuste: "Ajuste",
    hold: "HOLD",
    aprovado_ajustes: "Aprovado com ajustes",
    aprovado: "Aprovado",
    nao_iniciado: "Não iniciado",
  };
  const kStatus = document.getElementById("filtro-status").value;
  const modalStatusSel = document.getElementById("modal-filtro-status");
  if (kStatus && statusKeyToLabel[kStatus]) {
    modalStatusSel.value = statusKeyToLabel[kStatus];
  } else {
    modalStatusSel.value = "";
  }
}
function buildStatusBadges(item) {
  const badges = [
    {
      key: "em_andamento",
      cls: "andamento",
      icon: "ri-time-line",
      label: "em andamento",
    },
    {
      key: "em_aprovacao",
      cls: "aprovacao",
      icon: "ri-search-line",
      label: "em aprovação",
    },
    {
      key: "ajuste",
      cls: "ajuste",
      icon: "ri-error-warning-line",
      label: "ajuste",
    },
    { key: "hold", cls: "hold", icon: "ri-pause-circle-line", label: "hold" },
    {
      key: "aprovado_ajustes",
      cls: "aprov-ajuste",
      icon: "ri-checkbox-circle-line",
      label: "ap. c/ ajuste",
    },
    {
      key: "aprovado",
      cls: "aprovado",
      icon: "ri-check-double-line",
      label: "aprovado",
    },
    {
      key: "nao_iniciado",
      cls: "nao-iniciado",
      icon: "ri-stop-circle-line",
      label: "não iniciado",
    },
  ];
  return badges
    .filter((b) => parseInt(item[b.key]) > 0)
    .map(
      (b) =>
        `<span class="badge badge-${b.cls}"><i class="${b.icon}"></i> ${item[b.key]} ${b.label}</span>`,
    )
    .join("");
}

// ── STATUS pill class ─────────────────────────────────────
function statusClass(status) {
  const map = {
    "Não iniciado": "nao-iniciado",
    "Em andamento": "andamento",
    "Em aprovação": "aprovacao",
    Ajuste: "ajuste",
    "Aprovado com ajustes": "aprov-ajuste",
    Aprovado: "aprovado",
    HOLD: "hold",
  };
  return map[status] || "nao-iniciado";
}

// ── Render card ───────────────────────────────────────────
function criarCard(item) {
  const prioNum = parseInt(item.prioridade_alta);
  const prio = prioNum === 1 ? "alta" : prioNum === 2 ? "media" : "baixa";
  const prioText = prioNum === 1 ? "Alta" : prioNum === 2 ? "Média" : "Baixa";
  const atrasada = isAtrasada(item.proximo_prazo);
  const imgSrc = getImageSrc(item.ultima_imagem);

  const card = document.createElement("div");
  card.className = "kanban-card";
  card.dataset.funcaoId = item.idfuncao;
  card.dataset.obraId = item.idobra;
  card.dataset.funcaoNome = item.nome_funcao;
  card.dataset.obraNome = item.nomenclatura;

  card.innerHTML = `
        <div class="header-kanban">
            <span class="priority ${prio}">${prioText}</span>
            <span class="total-img"><i class="ri-image-line"></i> ${item.total}</span>
        </div>
        ${imgSrc ? `<img loading="lazy" src="${imgSrc}" alt="" class="card-thumb">` : '<img loading="lazy" src="../assets/logo.jpg" alt="" class="card-thumb">'}
        <h5>${item.nomenclatura}</h5>
        <div class="status-badges">${buildStatusBadges(item)}</div>
        <div class="card-footer">
            <span class="date ${atrasada ? "atrasada" : ""}">
                <i class="fa-regular fa-calendar"></i>
                ${formatarData(item.proximo_prazo)}
            </span>
        </div>`;

  card.addEventListener("click", () => {
    document
      .querySelectorAll(".kanban-card.selected")
      .forEach((c) => c.classList.remove("selected"));
    card.classList.add("selected");
    abrirDetalhe(
      item.idfuncao,
      item.idobra,
      item.nome_funcao,
      item.nomenclatura,
      item.nome_obra,
    );
  });

  return card;
}

// ── Render kanban column ──────────────────────────────────
function criarColuna(funcaoId, funcaoData) {
  const cfg = FUNCAO_CONFIG[funcaoId] || {
    icon: "ri-tools-line",
    color: "#ced4da",
  };
  const total = funcaoData.obras.length;

  const col = document.createElement("div");
  col.className = "kanban-box";

  col.innerHTML = `
        <div class="header" style="background-color: ${cfg.color};">
            <div class="title">
                <i class="${cfg.icon}"></i>
                <span>${funcaoData.nome_funcao}</span>
            </div>
            <span class="task-count">${total}</span>
        </div>
        <div class="content"></div>`;

  const content = col.querySelector(".content");
  funcaoData.obras.forEach((item) => content.appendChild(criarCard(item)));

  return col;
}

// ── Render full kanban ────────────────────────────────────
function renderKanban(data) {
  kanban.innerHTML = "";

  // Group by funcao (preserving order from server)
  const byFuncao = new Map();
  data.forEach((item) => {
    if (!byFuncao.has(item.idfuncao)) {
      byFuncao.set(item.idfuncao, { nome_funcao: item.nome_funcao, obras: [] });
    }
    byFuncao.get(item.idfuncao).obras.push(item);
  });

  if (byFuncao.size === 0) {
    kanban.innerHTML =
      '<p class="kanban-empty">Nenhuma tarefa em andamento no momento.</p>';
    return;
  }

  byFuncao.forEach((funcaoData, funcaoId) => {
    kanban.appendChild(criarColuna(parseInt(funcaoId), funcaoData));
  });
}

// ── Load main data ────────────────────────────────────────
function carregarQuadro() {
  showLoading(true);
  fecharDetalhe();
  fetch("get_quadro.php")
    .then((r) => r.json())
    .then((data) => {
      rawData = data;
      popularFiltros(data);
      aplicarFiltros();
      showLoading(false);
    })
    .catch((err) => {
      console.error("Erro ao carregar quadro:", err);
      showLoading(false);
    });
}

// ── Detail modal open ─────────────────────────────────────
function abrirDetalhe(
  funcaoId,
  obraId,
  funcaoNome,
  obraNome,
  nomeObraCompleto,
) {
  const cfg = FUNCAO_CONFIG[funcaoId] || {
    icon: "ri-tools-line",
    color: "#ced4da",
  };

  detailIcon.className = cfg.icon;
  detailIcon.style.color = cfg.color;
  detailTitle.textContent = `${funcaoNome} — ${obraNome}`;
  currentObraNome = nomeObraCompleto || obraNome;
  detailContent.innerHTML = `<div class="detail-loading"><i class="ri-loader-4-line spin"></i> Carregando…</div>`;

  // Reset modal filters
  document.getElementById("modal-filtro-colaborador").value = "";
  document.getElementById("modal-filtro-status").value = "";
  document.getElementById("modal-filtro-tipo").value = "";
  document.getElementById("modal-btn-clear").style.display = "none";

  detailModal.classList.add("open");

  fetch(
    `get_detalhes.php?funcao_id=${encodeURIComponent(funcaoId)}&obra_id=${encodeURIComponent(obraId)}`,
  )
    .then((r) => r.json())
    .then((items) => {
      allDetailItems = items;
      popularFiltrosModal(items);
      aplicarFiltrosModal();
    })
    .catch((err) => {
      detailContent.innerHTML =
        '<p class="detail-empty">Erro ao carregar detalhes.</p>';
      console.error(err);
    });
}

function renderDetalhes(items) {
  if (!items.length) {
    detailContent.innerHTML =
      '<p class="detail-empty">Nenhuma imagem ativa nesta função/obra.</p>';
    return;
  }

  detailContent.innerHTML = "";
  items.forEach((item) => {
    const imgSrc = getImageSrc(item.ultima_imagem);
    const prioNum = parseInt(item.prioridade);
    const prio = prioNum === 1 ? "alta" : prioNum === 2 ? "media" : "baixa";
    const prioText = prioNum === 1 ? "Alta" : prioNum === 2 ? "Média" : "Baixa";
    const atrasada = isAtrasada(item.prazo);
    const tempoTxt = item.tempo_em_andamento
      ? formatarDuracao(parseInt(item.tempo_em_andamento))
      : null;

    const div = document.createElement("div");
    div.className = "detail-image-card";
    div.innerHTML = `
            <div class="detail-card-header">
                <span class="priority ${prio}">${prioText}</span>
                <span class="status-pill sp-${statusClass(item.status)}">${item.status}</span>
            </div>
            ${imgSrc ? `<img loading="lazy" src="${imgSrc}" alt="" class="detail-thumb">` : ""}
            <p class="detail-image-nome">${item.imagem_nome}</p>
               ${item.tipo_imagem ? `<span class="card-tipo-badge">${item.tipo_imagem}</span>` : ""}
            <div class="detail-card-meta">
                ${item.nome_colaborador ? `<span><i class="ri-user-line"></i> ${item.nome_colaborador}</span>` : ""}
                <span class="${atrasada ? "atrasada" : ""}"><i class="fa-regular fa-calendar"></i> ${formatarData(item.prazo)}</span>
                ${tempoTxt ? `<span class="tempo"><i class="ri-time-line"></i> ${tempoTxt}</span>` : ""}
                ${item.indice_envio ? `<span><i class="ri-file-line"></i> v${item.indice_envio}</span>` : ""}
                ${parseInt(item.comentarios_ultima_versao) > 0 ? `<span><i class="ri-chat-3-line"></i> ${item.comentarios_ultima_versao}</span>` : ""}
            </div>
            <a href="#" class="btn-ir-flowreview-detail" data-id="${item.idfuncao_imagem}">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Flow Review
            </a>`;

    detailContent.appendChild(div);

    div
      .querySelector(".btn-ir-flowreview-detail")
      .addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        localStorage.setItem(
          "fr_goto",
          JSON.stringify({
            idfuncao_imagem: item.idfuncao_imagem,
            nome_obra: currentObraNome,
          }),
        );
        const _p = window.location.pathname;
        const _si = _p.indexOf("/ImproovWeb");
        const _imBase =
          _si !== -1
            ? window.location.origin + _p.slice(0, _si + "/ImproovWeb".length)
            : "https://improov.com.br/flow/ImproovWeb";
        const base = `${_imBase}/FlowReview/index.php`;
        const url = currentObraNome
          ? `${base}?obra_nome=${encodeURIComponent(currentObraNome)}`
          : base;
        window.open(url, "_blank");
      });
  });
}

// ── Bootstrap ─────────────────────────────────────────────
carregarQuadro();
