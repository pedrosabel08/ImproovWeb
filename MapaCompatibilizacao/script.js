/**
 * Mapa de Compatibilização de Planta — script.js
 *
 * Arquitetura SVG idêntica à de Revisao/script.js:
 * - viewBox="0 0 100 100" + preserveAspectRatio="none"
 * - Todas as coordenadas em porcentagem (0–100) relativas à <img>
 * - getBoundingClientRect() ja considera o transform de zoom
 */

"use strict";

/* ============================================================
   ELEMENTOS DOM
   ============================================================ */
const selectObra = document.getElementById("selectObra");
const plantaInfo = document.getElementById("plantaInfo");
const plantaVersao = document.getElementById("plantaVersao");
const progressoWrap = document.getElementById("progressoWrap");
const progressoBar = document.getElementById("progressoBar");
const progressoTexto = document.getElementById("progressoTexto");
const avisoNaoMarcadas = document.getElementById("avisoNaoMarcadas");
const avisoNaoMarcadasTxt = document.getElementById("avisoNaoMarcadasTexto");
const emptyState = document.getElementById("emptyState");
const plantaOuter = document.getElementById("plantaOuter");
const plantaWrapper = document.getElementById("plantaWrapper");
const plantaImg = document.getElementById("plantaImg");
const pdfCanvas = document.getElementById("pdfCanvas");
const pdfNavEl = document.getElementById("pdfNav");
const btnPdfPrev = document.getElementById("btnPdfPrev");
const btnPdfNext = document.getElementById("btnPdfNext");
const pdfPaginaInfo = document.getElementById("pdfPaginaInfo");
const plantaSvg = document.getElementById("plantaSvg");
const tooltip = document.getElementById("mcTooltip");

// Painel esquerdo (opcionais — inexistentes para nivel != 1/2)
const btnUploadPlanta = document.getElementById("btnUploadPlanta");
const inputPlanta = document.getElementById("inputPlanta");
const btnToggleEdicao = document.getElementById("btnToggleEdicao");
const instrucaoEdicao = document.getElementById("instrucaoEdicao");
const btnCancelarDesenho = document.getElementById("btnCancelarDesenho");
const zoomControls = document.getElementById("zoomControls");
const btnZoomIn = document.getElementById("btnZoomIn");
const btnZoomOut = document.getElementById("btnZoomOut");
const btnZoomReset = document.getElementById("btnZoomReset");

// Modal gerenciar plantas (wizard)
const modalGerenciarPlantas = document.getElementById("modalGerenciarPlantas");

// Modal novo vínculo
const modalVinculo = document.getElementById("modalVinculo");
const inputNomeAmbiente = document.getElementById("inputNomeAmbiente");
const selectImagem = document.getElementById("selectImagem");
const imagemVinculadaInfo = document.getElementById("imagemVinculadaInfo");
const btnFecharModal = document.getElementById("btnFecharModal");
const btnCancelarVinculo = document.getElementById("btnCancelarVinculo");
const btnSalvarVinculo = document.getElementById("btnSalvarVinculo");

// Modal editar marcação
const modalEditar = document.getElementById("modalEditar");
const editarMarcacaoId = document.getElementById("editarMarcacaoId");
const editarNomeAmbiente = document.getElementById("editarNomeAmbiente");
const editarSelectImagem = document.getElementById("editarSelectImagem");
const btnFecharModalEditar = document.getElementById("btnFecharModalEditar");
const btnCancelarEditar = document.getElementById("btnCancelarEditar");
const btnSalvarEditar = document.getElementById("btnSalvarEditar");
const btnDeletarMarcacao = document.getElementById("btnDeletarMarcacao");

/* ============================================================
   ESTADO GLOBAL
   ============================================================ */
let obraId = 0;
let plantaAtiva = null; // planta seleccionada no momento
let plantasObra = []; // todas as plantas ativas da obra
let marcacoes = []; // marcações da planta selecionada
let marcacoesPorPlanta = {}; // cache: planta_id → marcacoes[]
let imagensCache = []; // cache das imagens da obra

// --- PDF viewer ---
let pdfVirtualPages = []; // [{doc, pageNum}] — documento servido por servir_planta_pdf.php
let pdfTotalPaginas = 0;  // total de páginas
let pdfPaginaAtual = 1; // página virtual ativa (1-indexed)
let _pendingPlantaId = null; // ID da planta a selecionar após carregarMapa()

// --- Wizard Gerenciar Plantas ---
let wizardPdfs = []; // PDFs disponíveis (buscar_pdfs_obra.php)
let wizardSelecionadas = new Set(); // Set(idarquivo)

// --- Modo edição ---
let editMode = false;
let drawingPoints = []; // [[x, y], ...] em % 0–100
let previewGroup = null; // <g> de preview no SVG

// --- Zoom / pan ---
let zoomLevel = 1;
let panX = 0;
let panY = 0;
let isPanning = false;
let panStartX = 0;
let panStartY = 0;
let panRefX = 0;
let panRefY = 0;

/* ============================================================
   UTILITÁRIOS
   ============================================================ */
function toast(msg, ok = true) {
  Toastify({
    text: msg,
    duration: 3000,
    gravity: "top",
    position: "right",
    style: { background: ok ? "#2ecc71" : "#e74c3c" },
  }).showToast();
}

function corParaRgba(cor, alpha = 0.35) {
  const mapa = {
    verde: `rgba(46,204,113,${alpha})`,
    amarelo: `rgba(241,196,15,${alpha})`,
    branco: `rgba(236,240,241,${alpha})`,
  };
  return mapa[cor] ?? `rgba(200,200,200,${alpha})`;
}
function corParaStroke(cor) {
  return (
    { verde: "#27ae60", amarelo: "#d4ac0d", branco: "#aab7b8" }[cor] ?? "#888"
  );
}

function applyTransform() {
  plantaWrapper.style.transform = `translate(${panX}px, ${panY}px) scale(${zoomLevel})`;
}

/** Elemento ativo para coordenadas: canvas PDF ou img legada */
function getActiveImageEl() {
  return pdfCanvas && pdfCanvas.style.display !== "none"
    ? pdfCanvas
    : plantaImg;
}

/** Converte coordenadas do evento em porcentagem relativa à imagem (considera zoom) */
function clientToPercent(clientX, clientY) {
  const rect = getActiveImageEl().getBoundingClientRect();
  const x = Math.max(
    0,
    Math.min(100, ((clientX - rect.left) / rect.width) * 100),
  );
  const y = Math.max(
    0,
    Math.min(100, ((clientY - rect.top) / rect.height) * 100),
  );
  return [x, y];
}

/** Converte coordenada % para pixels na tela (útil para detecção de proximidade) */
function percentToClient(xPct, yPct) {
  const rect = getActiveImageEl().getBoundingClientRect();
  return {
    x: rect.left + (xPct / 100) * rect.width,
    y: rect.top + (yPct / 100) * rect.height,
  };
}

function svgNs(tag, attrs = {}) {
  const el = document.createElementNS("http://www.w3.org/2000/svg", tag);
  for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, v);
  return el;
}

/* ============================================================
   ZOOM / PAN
   ============================================================ */
function zoomAt(clientX, clientY, factor) {
  const newZoom = Math.max(0.5, Math.min(6, zoomLevel * factor));
  // Ponto pivô em coords do wrapper (antes do zoom)
  const pivotWX = (clientX - panX) / zoomLevel;
  const pivotWY = (clientY - panY) / zoomLevel;
  panX = clientX - pivotWX * newZoom;
  panY = clientY - pivotWY * newZoom;
  zoomLevel = newZoom;
  applyTransform();
}

function resetZoom() {
  zoomLevel = 1;
  panX = 0;
  panY = 0;
  applyTransform();
}

plantaOuter.addEventListener(
  "wheel",
  (e) => {
    if (!plantaAtiva) return;
    e.preventDefault();
    const factor = e.deltaY < 0 ? 1.12 : 1 / 1.12;
    zoomAt(e.clientX, e.clientY, factor);
  },
  { passive: false },
);

if (btnZoomIn)
  btnZoomIn.addEventListener("click", () => {
    const r = plantaOuter.getBoundingClientRect();
    zoomAt(r.left + r.width / 2, r.top + r.height / 2, 1.3);
  });
if (btnZoomOut)
  btnZoomOut.addEventListener("click", () => {
    const r = plantaOuter.getBoundingClientRect();
    zoomAt(r.left + r.width / 2, r.top + r.height / 2, 1 / 1.3);
  });
if (btnZoomReset) btnZoomReset.addEventListener("click", resetZoom);

/* ============================================================
   SELEÇÃO DE OBRA
   ============================================================ */
selectObra.addEventListener("change", () => {
  obraId = parseInt(selectObra.value, 10) || 0;
  plantaAtiva = null;
  plantasObra = [];
  marcacoes = [];
  marcacoesPorPlanta = {};
  imagensCache = [];
  drawingPoints = [];
  pdfVirtualPages = [];
  pdfTotalPaginas = 0;
  pdfPaginaAtual = 1;
  if (previewGroup) {
    previewGroup.remove();
    previewGroup = null;
  }
  resetZoom();
  if (!obraId) {
    showEmpty();
    return;
  }
  carregarMapa();
});

function showEmpty() {
  emptyState.classList.remove("hidden");
  plantaOuter.classList.add("hidden");
  plantaInfo.classList.add("hidden");
  progressoWrap.classList.add("hidden");
  avisoNaoMarcadas.classList.add("hidden");
  zoomControls?.classList.add("hidden");
  btnToggleEdicao?.classList.add("hidden");
  pdfNavEl?.classList.add("hidden");
  document.getElementById("mcTabs")?.classList.add("hidden");
  const lg = document.getElementById("mcLegenda");
  if (lg) lg.remove();
  desativarEdicao();
}

/* ============================================================
   CARREGAR MAPA (multi-planta)
   ============================================================ */
async function carregarMapa() {
  try {
    const resp = await fetch(
      `${BASE_URL}/buscar_marcacoes.php?obra_id=${obraId}`,
    );
    const data = await resp.json();
    if (!data.sucesso) {
      toast(data.erro || "Erro ao carregar mapa.", false);
      showEmpty();
      return;
    }

    const plantas = data.plantas ?? [];
    const todasMarcacoes = data.marcacoes ?? [];

    // Cache por planta
    marcacoesPorPlanta = {};
    todasMarcacoes.forEach((m) => {
      if (!marcacoesPorPlanta[m.planta_id])
        marcacoesPorPlanta[m.planta_id] = [];
      marcacoesPorPlanta[m.planta_id].push(m);
    });

    if (plantas.length === 0) {
      showEmpty();
      emptyState.querySelector("p").textContent =
        'Nenhuma planta cadastrada para esta obra. Clique em "Nova Planta" para começar.';
      if (POD_EDITAR) btnUploadPlanta?.classList.remove("hidden");
      return;
    }

    plantasObra = plantas;

    // Renderizar abas
    renderAbas();

    const keepId =
      _pendingPlantaId ||
      (plantaAtiva && plantasObra.find((p) => p.id === plantaAtiva.id)
        ? plantaAtiva.id
        : plantasObra[0].id);
    _pendingPlantaId = null;
    await selecionarPlanta(keepId, false);

    plantaInfo.classList.remove("hidden");
    carregarImagens();
  } catch (err) {
    console.error(err);
    toast("Erro de comunicação com o servidor.", false);
    showEmpty();
  }
}

/** Renderiza as abas de navegação entre plantas */
function renderAbas() {
  const mcTabs = document.getElementById("mcTabs");
  if (!mcTabs) return;
  mcTabs.innerHTML = "";

  if (plantasObra.length <= 1) {
    mcTabs.classList.add("hidden");
    return;
  }
  mcTabs.classList.remove("hidden");

  plantasObra.forEach((p) => {
    const btn = document.createElement("button");
    btn.className = "mc-tab" + (plantaAtiva?.id === p.id ? " active" : "");
    const tabLabel = p.arquivo_nome || p.imagem_nome || `Planta v${p.versao}`;
    btn.textContent = tabLabel;
    btn.title = tabLabel;
    btn.addEventListener("click", () => selecionarPlanta(p.id));
    mcTabs.appendChild(btn);
  });
}

/** Seleciona uma planta pela aba e carrega seu conteúdo (PDF nativo ou imagem legada) */
async function selecionarPlanta(plantaId, atualizarAbas = true) {
  plantaAtiva = plantasObra.find((p) => p.id === plantaId) || plantasObra[0];
  if (!plantaAtiva) return;

  marcacoes = marcacoesPorPlanta[plantaAtiva.id] || [];

  // Progresso desta planta
  const total = marcacoes.length;
  const finalizadas = marcacoes.filter((m) => m.cor === "verde").length;
  if (total > 0) {
    progressoWrap.classList.remove("hidden");
    const pct = Math.round((finalizadas / total) * 100);
    progressoBar.style.width = `${pct}%`;
    progressoTexto.textContent = `${finalizadas} / ${total} finalizadas (${pct}%)`;
  } else {
    progressoWrap.classList.add("hidden");
  }

  const tabLabel = plantaAtiva.arquivo_nome || plantaAtiva.imagem_nome;
  plantaVersao.textContent = tabLabel
    ? `${tabLabel} — v${plantaAtiva.versao}`
    : `Versão ${plantaAtiva.versao}`;

  if (atualizarAbas) renderAbas();

  const usaPdf = !!(plantaAtiva.arquivo_id || plantaAtiva.arquivo_ids_json);
  if (usaPdf) {
    await carregarPdfPlanta();
  } else {
    await carregarImagemLegada();
  }
}

/** Carrega o PDF da planta ativa via PDF.js — um único documento (1 arquivo ou merge server-side) */
async function carregarPdfPlanta() {
  if (!window.pdfjsLib) {
    toast("PDF.js não carregado.", false);
    return;
  }

  pdfVirtualPages = [];
  pdfTotalPaginas = 0;
  pdfPaginaAtual  = 1;

  try {
    // servir_planta_pdf.php faz o merge server-side quando necessário
    const url = `${BASE_URL}/servir_planta_pdf.php?planta_id=${plantaAtiva.id}`;
    const doc  = await pdfjsLib.getDocument({ url }).promise;

    // Preencher virtual pages a partir do documento único
    for (let pg = 1; pg <= doc.numPages; pg++) {
      pdfVirtualPages.push({ doc, pageNum: pg });
    }
    pdfTotalPaginas = pdfVirtualPages.length;

    plantaImg.style.display = "none";
    pdfCanvas.style.display = "block";
    pdfNavEl?.classList.toggle("hidden", pdfTotalPaginas <= 1);

    emptyState.classList.add("hidden");
    plantaOuter.classList.remove("hidden");
    zoomControls?.classList.remove("hidden");
    if (POD_EDITAR) btnToggleEdicao?.classList.remove("hidden");

    await renderizarPaginaPDF(1);
  } catch (err) {
    console.error(err);
    toast("Falha ao carregar PDF da planta.", false);
    showEmpty();
  }
}

/** Renderiza a página virtual N no pdfCanvas e atualiza as marcações */
async function renderizarPaginaPDF(virtualPage) {
  if (!pdfVirtualPages.length) return;
  const idx = Math.max(
    0,
    Math.min(virtualPage - 1, pdfVirtualPages.length - 1),
  );
  const { doc, pageNum } = pdfVirtualPages[idx];
  pdfPaginaAtual = idx + 1;

  try {
    const page = await doc.getPage(pageNum);
    const viewport = page.getViewport({ scale: 2 }); // 2× para boa nitidez
    pdfCanvas.width = viewport.width;
    pdfCanvas.height = viewport.height;
    const ctx = pdfCanvas.getContext("2d");
    await page.render({ canvasContext: ctx, viewport }).promise;

    if (pdfPaginaInfo)
      pdfPaginaInfo.textContent = `Página ${pdfPaginaAtual} / ${pdfTotalPaginas}`;
    if (btnPdfPrev) btnPdfPrev.disabled = pdfPaginaAtual <= 1;
    if (btnPdfNext) btnPdfNext.disabled = pdfPaginaAtual >= pdfTotalPaginas;

    renderMarcacoes();
  } catch (err) {
    console.error("Erro ao renderizar página PDF:", err);
    toast("Erro ao renderizar página.", false);
  }
}

/** Carrega imagem legada (imagem_path) para plantas sem arquivo_id */
async function carregarImagemLegada() {
  return new Promise((resolve) => {
    pdfVirtualPages = [];
    pdfTotalPaginas = 0;
    pdfPaginaAtual = 1;
    pdfCanvas.style.display = "none";
    plantaImg.style.display = "block";
    pdfNavEl?.classList.add("hidden");

    const appBase = (window.IMPROOV_APP_BASE || "").replace(/\/$/, "");
    const imagPath = plantaAtiva.imagem_url?.replace(/^\//, "") || "";
    plantaImg.src = `${location.origin}${appBase}/${imagPath}`;
    plantaImg.onload = () => {
      emptyState.classList.add("hidden");
      plantaOuter.classList.remove("hidden");
      zoomControls?.classList.remove("hidden");
      if (POD_EDITAR) btnToggleEdicao?.classList.remove("hidden");
      renderMarcacoes();
      resolve();
    };
    plantaImg.onerror = () => {
      toast("Falha ao carregar imagem da planta.", false);
      showEmpty();
      resolve();
    };
  });
}

/* ============================================================
   NAVEGAÇÃO DE PÁGINAS PDF
   ============================================================ */
btnPdfPrev?.addEventListener("click", () => {
  if (pdfPaginaAtual > 1) renderizarPaginaPDF(pdfPaginaAtual - 1);
});
btnPdfNext?.addEventListener("click", () => {
  if (pdfPaginaAtual < pdfTotalPaginas) renderizarPaginaPDF(pdfPaginaAtual + 1);
});

async function carregarImagens() {
  try {
    const url = `${BASE_URL}/buscar_imagens.php?obra_id=${obraId}&planta_id=${plantaAtiva?.id ?? 0}`;
    const resp = await fetch(url);
    const data = await resp.json();
    if (data.sucesso) {
      imagensCache = data.imagens ?? [];
      popularSelectImagens(selectImagem);
      popularSelectImagens(editarSelectImagem);
      verificarAvisoNaoVinculadas();
    }
  } catch (err) {
    console.error("Erro ao carregar imagens:", err);
  }
}

function popularSelectImagens(selectEl) {
  if (!selectEl) return;
  const valorAtual = selectEl.value;
  // Remove opções antigas (mantém a primeira — "Nenhuma")
  while (selectEl.options.length > 1) selectEl.remove(1);
  imagensCache.forEach((img) => {
    const opt = document.createElement("option");
    opt.value = img.id;
    opt.textContent = img.imagem_nome + (img.vinculada ? " ✓" : "");
    selectEl.appendChild(opt);
  });
  if (valorAtual) selectEl.value = valorAtual;
}

function verificarAvisoNaoVinculadas() {
  const vinculadosIds = new Set(
    marcacoes.map((m) => m.imagem_id).filter(Boolean),
  );
  const naoVinculadas = imagensCache.filter(
    (img) => !vinculadosIds.has(img.id),
  );
  if (naoVinculadas.length > 0) {
    avisoNaoMarcadas.classList.remove("hidden");
    avisoNaoMarcadasTxt.textContent = `${naoVinculadas.length} imagem(ns) da obra ainda não foram marcadas na planta.`;
  } else {
    avisoNaoMarcadas.classList.add("hidden");
  }
}

/* ============================================================
   RENDERIZAR MARCAÇÕES NO SVG
   ============================================================ */
function renderMarcacoes() {
  // Remove marcações antigas (mantém previewGroup se existir)
  plantaSvg.querySelectorAll(".mc-poligono").forEach((el) => el.remove());

  // Remove legenda antiga
  const oldLegenda = document.getElementById("mcLegenda");
  if (oldLegenda) oldLegenda.remove();

  marcacoes.forEach((m) => {
    // Filtrar marcações de outras páginas quando em modo PDF
    if (
      pdfVirtualPages.length > 0 &&
      m.pagina_pdf !== null &&
      m.pagina_pdf !== undefined &&
      m.pagina_pdf !== pdfPaginaAtual
    )
      return;

    let pontos;
    try {
      pontos = JSON.parse(m.coordenadas_json);
    } catch {
      return;
    }
    if (!Array.isArray(pontos) || pontos.length < 3) return;

    const pointsStr = pontos.map((p) => `${p[0]},${p[1]}`).join(" ");

    const g = svgNs("g", { class: "mc-poligono", "data-id": m.id });

    const poly = svgNs("polygon", {
      points: pointsStr,
      fill: corParaRgba(m.cor, 0.38),
      stroke: corParaStroke(m.cor),
      "stroke-width": "0.45",
    });
    g.appendChild(poly);

    // Label do ambiente
    const centro = calcCentroide(pontos);
    const text = svgNs("text", {
      x: centro[0],
      y: centro[1],
      "text-anchor": "middle",
      "dominant-baseline": "middle",
      "font-size": "1.6px",
      fill: "#1a252f",
      stroke: "#fff",
      "stroke-width": "0.35",
      "paint-order": "stroke",
      "pointer-events": "none",
      // use the same font as the app
      "font-family": "'Inter', Arial, sans-serif",
      "font-weight": "600",
    });
    text.textContent = m.nome_ambiente;
    g.appendChild(text);

    // compensate SVG non-uniform scaling (preserveAspectRatio="none") so
    // text doesn't appear stretched: scale horizontally by svgHeight/svgWidth
    try {
      const r = plantaSvg.getBoundingClientRect();
      if (r.width > 0 && r.height > 0) {
        const comp = r.height / r.width; // scaleX compensation factor
        // translate to center, scale horizontally, translate back
        const tx = centro[0];
        const ty = centro[1];
        text.setAttribute(
          "transform",
          `translate(${tx},${ty}) scale(${comp},1) translate(${-tx},${-ty})`,
        );
      }
    } catch (e) {
      // ignore if measurement fails
    }

    // create a subtle rounded background behind the label for readability
    // must append group to DOM first to measure text bbox
    plantaSvg.appendChild(g);
    try {
      const bbox = text.getBBox();
      const padX = 0.6;
      const padY = 0.35;
      const rect = svgNs("rect", {
        x: bbox.x - padX,
        y: bbox.y - padY,
        width: Math.max(8, bbox.width + padX * 2),
        height: bbox.height + padY * 2,
        rx: "0.4",
        ry: "0.4",
        fill: "rgba(255,255,255,0.92)",
        stroke: "rgba(0,0,0,0.05)",
      });
      // insert rect before text
      g.insertBefore(rect, text);
    } catch (err) {
      // fallback: already appended text, nothing else to do
    }
    // Interatividade
    g.addEventListener("mouseenter", (e) => mostrarTooltip(e, m));
    g.addEventListener("mousemove", (e) => moverTooltip(e));
    g.addEventListener("mouseleave", () => esconderTooltip());
    // Abrir modal ao clicar na marcação quando o usuário tiver permissão
    g.addEventListener("click", (e) => {
      e.stopPropagation();
      if (POD_EDITAR) {
        abrirModalEditar(m, e.clientX, e.clientY);
      }
    });
  });

  // depois de desenhar tudo, renderiza a legenda clicável
  renderLegenda();
}

/** Foca / centraliza a planta na marcação (zoom + pan) */
function focusOnMarcacao(m) {
  let pontos;
  try {
    pontos = JSON.parse(m.coordenadas_json);
  } catch {
    return;
  }
  const centro = calcCentroide(pontos);

  // coordenadas do centro na tela (considerando transform atual)
  const centroClient = percentToClient(centro[0], centro[1]);

  // escolher zoom alvo
  const targetZoom = Math.min(3, Math.max(1.3, 2));

  // zoom em torno do centro (mantém o centro no mesmo ponto da tela)
  zoomAt(centroClient.x, centroClient.y, targetZoom / zoomLevel);

  // reposicionar para colocar o centro no centro do viewport
  const newCentro = percentToClient(centro[0], centro[1]);
  const outer = plantaOuter.getBoundingClientRect();
  const centerOutX = outer.left + outer.width / 2;
  const centerOutY = outer.top + outer.height / 2;
  const dx = centerOutX - newCentro.x;
  const dy = centerOutY - newCentro.y;
  panX += dx;
  panY += dy;
  applyTransform();
}

/** Renderiza legenda de ambientes abaixo da barra de progresso */
function renderLegenda() {
  // remove antiga
  const existing = document.getElementById("mcLegenda");
  if (existing) existing.remove();

  if (!marcacoes || marcacoes.length === 0) return;

  const legenda = document.createElement("div");
  legenda.id = "mcLegenda";
  legenda.className = "mc-legenda";

  // criar item para cada marcação (use ordem original)
  marcacoes.forEach((m) => {
    const item = document.createElement("div");
    item.className = "mc-legenda-item";
    item.style.cursor = "pointer";
    item.title = m.nome_ambiente;
    item.addEventListener("click", () => focusOnMarcacao(m));

    const corBox = document.createElement("span");
    corBox.className = "mc-legenda-cor";
    corBox.style.background = corParaStroke(m.cor);

    const label = document.createElement("span");
    label.style.fontSize = "13px";
    label.style.color = "#1a252f";
    label.textContent = m.nome_ambiente;

    item.appendChild(corBox);
    item.appendChild(label);
    legenda.appendChild(item);
  });

  // inserir logo após a barra de progresso
  if (progressoWrap && progressoWrap.parentNode) {
    progressoWrap.parentNode.insertBefore(legenda, progressoWrap.nextSibling);
  }
}

function calcCentroide(pontos) {
  const n = pontos.length;
  const sx = pontos.reduce((a, p) => a + p[0], 0) / n;
  const sy = pontos.reduce((a, p) => a + p[1], 0) / n;
  return [sx, sy];
}

/* ============================================================
   TOOLTIP
   ============================================================ */
function mostrarTooltip(e, m) {
  const badges = {
    verde: "badge-verde",
    amarelo: "badge-amarelo",
    branco: "badge-branco",
  };
  tooltip.innerHTML = `
        <div class="tt-ambiente">${escapeHtml(m.nome_ambiente)}</div>
        ${m.imagem_nome ? `<div>${escapeHtml(m.imagem_nome)}</div>` : '<div style="color:#aaa">Sem imagem vinculada</div>'}
        <div class="tt-status">
            <span class="tt-badge ${badges[m.cor] ?? ""}"></span>
            ${escapeHtml(m.status_texto ?? "")}
        </div>`;
  tooltip.classList.remove("hidden");
  moverTooltip(e);
}
function moverTooltip(e) {
  const tw = tooltip.offsetWidth,
    th = tooltip.offsetHeight;
  let left = e.clientX + 14;
  let top = e.clientY + 14;
  if (left + tw + 10 > window.innerWidth) left = e.clientX - tw - 10;
  if (top + th + 10 > window.innerHeight) top = e.clientY - th - 10;
  tooltip.style.left = `${left}px`;
  tooltip.style.top = `${top}px`;
}
function esconderTooltip() {
  tooltip.classList.add("hidden");
}

function escapeHtml(s) {
  return String(s ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}

/* ============================================================
   UPLOAD DE PLANTA — agora abre o wizard
   ============================================================ */
btnUploadPlanta?.addEventListener("click", () => {
  if (!obraId) {
    toast("Selecione uma obra antes.", false);
    return;
  }
  abrirWizard();
});

// input legado (imagem direta) — mantido por compatibilidade
inputPlanta?.addEventListener("change", async () => {
  const file = inputPlanta.files[0];
  if (!file) return;
  const fd = new FormData();
  fd.append("obra_id", obraId);
  fd.append("planta", file);
  btnUploadPlanta.disabled = true;
  try {
    const resp = await fetch(`${BASE_URL}/upload_planta.php`, {
      method: "POST",
      body: fd,
    });
    const data = await resp.json();
    if (data.sucesso) {
      toast(`Planta v${data.versao} enviada!`);
      await carregarMapa();
    } else toast(data.erro || "Erro ao enviar planta.", false);
  } catch {
    toast("Falha de comunicação.", false);
  } finally {
    btnUploadPlanta.disabled = false;
    inputPlanta.value = "";
  }
});

/* ============================================================
   MODO EDIÇÃO
   ============================================================ */
btnToggleEdicao?.addEventListener("click", () => {
  editMode = !editMode;
  if (editMode) {
    ativarEdicao();
  } else {
    desativarEdicao();
  }
});

function ativarEdicao() {
  btnToggleEdicao.innerHTML =
    '<i class="fa-solid fa-eye"></i> Modo Visualização';
  btnToggleEdicao.classList.add("active");
  instrucaoEdicao?.classList.remove("hidden");
  plantaOuter.classList.add("editing");
  editMode = true;
}

function desativarEdicao() {
  editMode = false;
  cancelarDesenho();
  btnToggleEdicao?.classList.remove("active");
  if (btnToggleEdicao)
    btnToggleEdicao.innerHTML = '<i class="fa-solid fa-pen"></i> Modo Edição';
  instrucaoEdicao?.classList.add("hidden");
  plantaOuter.classList.remove("editing");
}

btnCancelarDesenho?.addEventListener("click", () => {
  cancelarDesenho();
});

function cancelarDesenho() {
  drawingPoints = [];
  if (previewGroup) {
    previewGroup.remove();
    previewGroup = null;
  }
  btnCancelarDesenho?.classList.add("hidden");
}

/* ============================================================
   DESENHO DE POLÍGONO (CLIQUE A CLIQUE)
   ============================================================ */
plantaOuter.addEventListener("pointerdown", (e) => {
  if (!plantaAtiva) return;

  // Iniciar pan se não estiver em modo edição
  if (!editMode) {
    isPanning = true;
    panStartX = e.clientX;
    panStartY = e.clientY;
    panRefX = panX;
    panRefY = panY;
    return;
  }

  // Em modo edição: só clique esquerdo
  if (e.button !== 0) return;
  e.preventDefault();
  e.stopPropagation();

  const [xPct, yPct] = clientToPercent(e.clientX, e.clientY);

  // Verificar se clicou perto do primeiro ponto (fechar polígono)
  if (drawingPoints.length >= 3) {
    const first = drawingPoints[0];
    const fp = percentToClient(first[0], first[1]);
    const dist = Math.hypot(e.clientX - fp.x, e.clientY - fp.y);
    if (dist < 14) {
      fecharPoligono();
      return;
    }
  }

  // Adicionar novo ponto
  drawingPoints.push([xPct, yPct]);
  atualizarPreview();
  btnCancelarDesenho?.classList.remove("hidden");
});

document.addEventListener("pointermove", (e) => {
  if (isPanning && !editMode) {
    const dx = e.clientX - panStartX;
    const dy = e.clientY - panStartY;
    panX = panRefX + dx;
    panY = panRefY + dy;
    applyTransform();
  }
});

document.addEventListener("pointerup", () => {
  isPanning = false;
});

function atualizarPreview() {
  if (previewGroup) previewGroup.remove();
  previewGroup = svgNs("g", { id: "mc-preview" });

  const pts = drawingPoints;
  if (pts.length === 0) {
    plantaSvg.appendChild(previewGroup);
    return;
  }

  // Polígono de preview (preenchimento parcial)
  if (pts.length >= 2) {
    const poly = svgNs("polygon", {
      points: pts.map((p) => `${p[0]},${p[1]}`).join(" "),
      class: "mc-preview-poly",
    });
    previewGroup.appendChild(poly);
  }

  // Linha até cursor (não temos aqui — só pontos e linhas fixas)
  // Linhas entre pontos
  for (let i = 1; i < pts.length; i++) {
    const line = svgNs("line", {
      x1: pts[i - 1][0],
      y1: pts[i - 1][1],
      x2: pts[i][0],
      y2: pts[i][1],
      class: "mc-preview-line",
    });
    previewGroup.appendChild(line);
  }

  // Círculos nos vértices
  pts.forEach((p, idx) => {
    const circle = svgNs("circle", {
      cx: p[0],
      cy: p[1],
      r: "0.9",
      class: idx === 0 ? "mc-preview-circle primeiro" : "mc-preview-circle",
    });
    previewGroup.appendChild(circle);
  });

  plantaSvg.appendChild(previewGroup);
}

function fecharPoligono() {
  if (drawingPoints.length < 3) return;
  // Abrir modal de nome/vínculo
  inputNomeAmbiente.value = "";
  popularSelectImagens(selectImagem);
  imagemVinculadaInfo.classList.add("hidden");
  abrirModal(modalVinculo);
}

/* ============================================================
   MODAL NOVO VÍNCULO
   ============================================================ */
function abrirModal(modal) {
  modal.classList.remove("hidden");
  document.body.style.overflow = "hidden";
}
function fecharModalEl(modal) {
  modal.classList.add("hidden");
  document.body.style.overflow = "";
  // cleanup any inline positioning applied to modalEditar
  if (modal === modalEditar) {
    try {
      const content = modalEditar.querySelector(".mc-modal-content");
      if (content) {
        content.style.position = "";
        content.style.left = "";
        content.style.top = "";
      }
      modalEditar.style.alignItems = "";
    } catch (e) {}
  }
}

btnFecharModal?.addEventListener("click", () => {
  fecharModalEl(modalVinculo);
  cancelarDesenho();
});
btnCancelarVinculo?.addEventListener("click", () => {
  fecharModalEl(modalVinculo);
  cancelarDesenho();
});
modalVinculo?.addEventListener("click", (e) => {
  if (e.target === modalVinculo) {
    fecharModalEl(modalVinculo);
    cancelarDesenho();
  }
});

selectImagem?.addEventListener("change", () => {
  const imgId = parseInt(selectImagem.value, 10);
  if (imgId) {
    const img = imagensCache.find((i) => i.id === imgId);
    if (img) {
      imagemVinculadaInfo.textContent = `Status: ${img.nome_status || "—"}${img.vinculada ? " · Já vinculada a outro ambiente" : ""}`;
      imagemVinculadaInfo.classList.remove("hidden");
    }
  } else {
    imagemVinculadaInfo.classList.add("hidden");
  }
});

btnSalvarVinculo?.addEventListener("click", async () => {
  const nome = inputNomeAmbiente.value.trim();
  if (!nome) {
    toast("Informe o nome do ambiente.", false);
    inputNomeAmbiente.focus();
    return;
  }
  if (!plantaAtiva) {
    toast("Nenhuma planta ativa.", false);
    return;
  }

  const imagemId = parseInt(selectImagem.value, 10) || null;
  const payload = {
    planta_id: plantaAtiva.id,
    nome_ambiente: nome,
    imagem_id: imagemId,
    coordenadas_json: JSON.stringify(drawingPoints),
    pagina_pdf: pdfVirtualPages.length > 0 ? pdfPaginaAtual : null,
  };

  btnSalvarVinculo.disabled = true;
  try {
    const resp = await fetch(`${BASE_URL}/salvar_marcacao.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await resp.json();
    if (data.sucesso) {
      toast(`Ambiente "${nome}" salvo!`);
      fecharModalEl(modalVinculo);
      cancelarDesenho();
      await carregarMapa();
    } else {
      toast(data.erro || "Erro ao salvar.", false);
    }
  } catch {
    toast("Falha de comunicação.", false);
  } finally {
    btnSalvarVinculo.disabled = false;
  }
});

/* ============================================================
   MODAL EDITAR MARCAÇÃO
   ============================================================ */
function abrirModalEditar(m, clientX, clientY) {
  editarMarcacaoId.value = m.id;
  editarNomeAmbiente.value = m.nome_ambiente;
  popularSelectImagens(editarSelectImagem);
  editarSelectImagem.value = m.imagem_id ?? "";

  // open modal first
  abrirModal(modalEditar);

  // position modal content near the click / marker if coords provided
  try {
    const content = modalEditar.querySelector(".mc-modal-content");
    if (!content) return;

    // reset any previous inline positioning
    content.style.position = "";
    content.style.left = "";
    content.style.top = "";
    content.style.right = "";
    content.style.transform = "";

    // if no coords given, try to position at the centroid of the marker
    let px = clientX,
      py = clientY;
    if (typeof px !== "number" || typeof py !== "number") {
      const atual = marcacoes.find((mm) => mm.id === m.id);
      if (atual) {
        try {
          const pts = JSON.parse(atual.coordenadas_json);
          const centro = calcCentroide(pts);
          const c = percentToClient(centro[0], centro[1]);
          px = c.x;
          py = c.y;
        } catch (e) {}
      }
    }

    // measure after visible
    const rect = content.getBoundingClientRect();
    const margin = 12;
    let left = px + 90; // default to right side
    let top = py - rect.height / 2;

    // choose left side if not enough space on right
    if (left + rect.width + margin > window.innerWidth) {
      left = px - rect.width - 90;
    }
    // clamp top
    if (top < margin) top = margin;
    if (top + rect.height + margin > window.innerHeight)
      top = window.innerHeight - rect.height - margin;

    // apply absolute positioning
    content.style.position = "absolute";
    content.style.left = `${Math.max(8, left)}px`;
    content.style.top = `${Math.max(8, top)}px`;
    // ensure overlay still covers screen but the content sits where we want
    modalEditar.style.alignItems = "flex-start";
  } catch (err) {
    // ignore positioning errors
  }
}

btnFecharModalEditar?.addEventListener("click", () =>
  fecharModalEl(modalEditar),
);
btnCancelarEditar?.addEventListener("click", () => fecharModalEl(modalEditar));
modalEditar?.addEventListener("click", (e) => {
  if (e.target === modalEditar) fecharModalEl(modalEditar);
});

btnSalvarEditar?.addEventListener("click", async () => {
  const id = parseInt(editarMarcacaoId.value, 10);
  const nome = editarNomeAmbiente.value.trim();
  if (!nome) {
    toast("Informe o nome do ambiente.", false);
    return;
  }

  // Buscar coordenadas atuais da marcação
  const atual = marcacoes.find((m) => m.id === id);
  if (!atual) {
    toast("Marcação não encontrada.", false);
    return;
  }

  const imagemId = parseInt(editarSelectImagem.value, 10) || null;
  const payload = {
    planta_id: plantaAtiva.id,
    nome_ambiente: nome,
    imagem_id: imagemId,
    coordenadas_json: atual.coordenadas_json,
    pagina_pdf: pdfVirtualPages.length > 0 ? pdfPaginaAtual : null,
  };

  btnSalvarEditar.disabled = true;
  try {
    const resp = await fetch(`${BASE_URL}/salvar_marcacao.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await resp.json();
    if (data.sucesso) {
      toast("Marcação atualizada!");
      fecharModalEl(modalEditar);
      await carregarMapa();
    } else {
      toast(data.erro || "Erro ao atualizar.", false);
    }
  } catch {
    toast("Falha de comunicação.", false);
  } finally {
    btnSalvarEditar.disabled = false;
  }
});

btnDeletarMarcacao?.addEventListener("click", async () => {
  const id = parseInt(editarMarcacaoId.value, 10);
  if (!confirm(`Excluir esta marcação? Esta ação não pode ser desfeita.`))
    return;

  try {
    const resp = await fetch(`${BASE_URL}/deletar_marcacao.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ marcacao_id: id, obra_id: obraId }),
    });
    const data = await resp.json();
    if (data.sucesso) {
      toast("Marcação excluída.");
      fecharModalEl(modalEditar);
      await carregarMapa();
    } else {
      toast(data.erro || "Erro ao excluir.", false);
    }
  } catch {
    toast("Falha de comunicação.", false);
  }
});

/* ============================================================
   WIZARD — GERENCIAR PLANTAS (PDF NATIVO)
   ============================================================ */

/** Abre o wizard e carrega a lista de PDFs da obra */
async function abrirWizard() {
  wizardPdfs = [];
  wizardSelecionadas = new Set();

  // Reset visual
  const lista = document.getElementById("listaPdfsObra");
  if (lista)
    lista.innerHTML =
      '<p class="mc-wizard-loading"><i class="fa-solid fa-spinner fa-spin"></i> Carregando…</p>';
  const btnCriar = document.getElementById("btnWizardCriar");
  if (btnCriar) btnCriar.disabled = true;
  const unificaWrap = document.getElementById("wizardUnificarWrap");
  if (unificaWrap) unificaWrap.classList.add("hidden");
  const unificaCheck = document.getElementById("wizardUnificarCheck");
  if (unificaCheck) {
    unificaCheck.checked = false;
    unificaCheck.onchange = atualizarWizardState;
  }

  abrirModal(modalGerenciarPlantas);

  try {
    const resp = await fetch(
      `${BASE_URL}/buscar_pdfs_obra.php?obra_id=${obraId}`,
    );
    const data = await resp.json();
    if (!data.sucesso) {
      toast(data.erro || "Erro ao carregar PDFs.", false);
      fecharModalEl(modalGerenciarPlantas);
      return;
    }
    wizardPdfs = data.pdfs || [];
  } catch {
    toast("Falha de comunicação.", false);
    fecharModalEl(modalGerenciarPlantas);
    return;
  }
  renderWizardPdfs();
}

/** Renderiza a lista de PDFs no modal */
function renderWizardPdfs() {
  const lista = document.getElementById("listaPdfsObra");
  if (!lista) return;
  lista.innerHTML = "";

  if (wizardPdfs.length === 0) {
    lista.innerHTML =
      '<p style="color:#888;text-align:center;padding:20px">Nenhum PDF de Planta Humanizada Arquitetônica cadastrado para esta obra.</p>';
    const btnCriar = document.getElementById("btnWizardCriar");
    if (btnCriar) btnCriar.disabled = true;
    return;
  }

  wizardPdfs.forEach((pdf) => {
    const item = document.createElement("label");
    item.className = "mc-wizard-plant-item";

    const cb = document.createElement("input");
    cb.type = "checkbox";
    cb.value = pdf.id;
    cb.checked = wizardSelecionadas.has(pdf.id);
    cb.addEventListener("change", () => {
      if (cb.checked) wizardSelecionadas.add(pdf.id);
      else wizardSelecionadas.delete(pdf.id);
      atualizarWizardState();
    });

    const nome = document.createElement("span");
    nome.className = "mc-wizard-plant-nome";
    nome.textContent = pdf.nome;

    item.append(cb, nome);
    lista.appendChild(item);
  });

  atualizarWizardState();
}

/** Atualiza botão e toggle "Unificar" conforme seleção */
function atualizarWizardState() {
  const n = wizardSelecionadas.size;
  const unificaWrap = document.getElementById("wizardUnificarWrap");
  const unificaCheck = document.getElementById("wizardUnificarCheck");
  const btnCriar = document.getElementById("btnWizardCriar");

  if (unificaWrap) unificaWrap.classList.toggle("hidden", n <= 1);
  if (unificaCheck && n <= 1) unificaCheck.checked = false;

  const podecriar = n === 1 || (n >= 2 && unificaCheck?.checked);
  if (btnCriar) {
    btnCriar.disabled = !podecriar;
    if (n === 0) {
      btnCriar.innerHTML = '<i class="fa-solid fa-plus"></i> Criar Planta';
    } else if (n === 1) {
      btnCriar.innerHTML =
        '<i class="fa-solid fa-plus"></i> Criar Planta (1 PDF)';
    } else {
      const label = unificaCheck?.checked
        ? "Criar Planta Unificada"
        : "Criar Planta Unificada";
      btnCriar.innerHTML = `<i class="fa-solid fa-layer-group"></i> ${label} (${n} PDFs)`;
    }
  }
}

/** Cria a planta com o(s) PDF(s) selecionado(s) */
async function criarPlantaWizard() {
  const n = wizardSelecionadas.size;
  if (n === 0) {
    toast("Selecione ao menos um PDF.", false);
    return;
  }

  // Guarda extra: não permite múltiplos sem unificar marcado
  if (n >= 2) {
    const unificaCheck = document.getElementById("wizardUnificarCheck");
    if (!unificaCheck?.checked) {
      toast('Marque "Unificar PDFs" para usar múltiplos arquivos.', false);
      return;
    }
  }

  const ids = [...wizardSelecionadas];
  const payload = { obra_id: obraId };
  if (n === 1) {
    payload.arquivo_id = ids[0];
  } else {
    payload.arquivo_ids_json = JSON.stringify(ids);
  }

  const btnCriar = document.getElementById("btnWizardCriar");
  if (btnCriar) {
    btnCriar.disabled = true;
    btnCriar.textContent = n >= 2 ? "Unificando PDFs…" : "Criando planta…";
  }

  try {
    const resp = await fetch(`${BASE_URL}/criar_planta_pdf.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await resp.json();
    if (data.sucesso) {
      toast(`Planta criada (v${data.versao})!`);
      _pendingPlantaId = data.planta_id;
      fecharModalEl(modalGerenciarPlantas);
      await carregarMapa();
    } else {
      toast(data.erro || "Erro ao criar planta.", false);
      if (btnCriar) { btnCriar.disabled = false; btnCriar.textContent = "Criar Planta"; }
    }
  } catch {
    toast("Falha de comunicação.", false);
    if (btnCriar) { btnCriar.disabled = false; btnCriar.textContent = "Criar Planta"; }
  }
}

// ---------- Botões do wizard ----------
document
  .getElementById("btnFecharWizard")
  ?.addEventListener("click", () => fecharModalEl(modalGerenciarPlantas));
document
  .getElementById("btnWizardCancelar")
  ?.addEventListener("click", () => fecharModalEl(modalGerenciarPlantas));
document
  .getElementById("btnWizardCriar")
  ?.addEventListener("click", criarPlantaWizard);
modalGerenciarPlantas?.addEventListener("click", (e) => {
  if (e.target === modalGerenciarPlantas) fecharModalEl(modalGerenciarPlantas);
});

/* ============================================================
   INICIALIZAÇÃO
   ============================================================ */
showEmpty();
