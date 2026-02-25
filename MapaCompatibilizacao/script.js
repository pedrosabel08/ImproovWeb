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
let plantaAtiva = null; // { id, versao, imagem_url }
let marcacoes = []; // array de marcações renderizadas
let imagensCache = []; // cache das imagens da obra

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

/** Converte coordenadas do evento em porcentagem relativa à imagem (considera zoom) */
function clientToPercent(clientX, clientY) {
  const rect = plantaImg.getBoundingClientRect();
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
  const rect = plantaImg.getBoundingClientRect();
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
  marcacoes = [];
  imagensCache = [];
  drawingPoints = [];
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
  desativarEdicao();
}

/* ============================================================
   CARREGAR MAPA (planta + marcações)
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

    marcacoes = data.marcacoes ?? [];

    if (!data.planta) {
      showEmpty();
      emptyState.querySelector("p").textContent =
        "Nenhuma planta cadastrada para esta obra. Envie uma imagem para começar.";
      emptyState.classList.remove("hidden");
      if (btnToggleEdicao) btnToggleEdicao.classList.add("hidden");
      if (POD_EDITAR) {
        btnUploadPlanta?.classList.remove("hidden");
      }
      return;
    }

    plantaAtiva = data.planta;

    // Carregar imagem — montar URL absoluta usando a base da aplicação
    // evita perder o segmento /ImproovWeb quando o app não está na raiz do host
    const appBase = (window.IMPROOV_APP_BASE || '').replace(/\/$/, '');
    const imagePath = plantaAtiva.imagem_url.replace(/^\//, '');
    plantaImg.src = `${location.origin}${appBase}/${imagePath}`;
    plantaImg.onload = () => {
      emptyState.classList.add("hidden");
      plantaOuter.classList.remove("hidden");
      zoomControls?.classList.remove("hidden");
      if (POD_EDITAR) btnToggleEdicao?.classList.remove("hidden");
      renderMarcacoes();
    };
    plantaImg.onerror = () => {
      toast("Falha ao carregar imagem da planta.", false);
      showEmpty();
    };

    // Info versão
    plantaInfo.classList.remove("hidden");
    plantaVersao.textContent = `Versão ${plantaAtiva.versao}`;

    // Progresso
    if (data.total_marcacoes > 0) {
      progressoWrap.classList.remove("hidden");
      progressoBar.style.width = `${data.percentual_conclusao}%`;
      progressoTexto.textContent = `${data.finalizadas} / ${data.total_marcacoes} finalizadas (${data.percentual_conclusao}%)`;
    } else {
      progressoWrap.classList.add("hidden");
    }

    // Carregar imagens para o select (em paralelo, sem esperar)
    carregarImagens();
  } catch (err) {
    console.error(err);
    toast("Erro de comunicação com o servidor.", false);
    showEmpty();
  }
}

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

  marcacoes.forEach((m) => {
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
      "font-size": "2.2",
      fill: "#1a252f",
      stroke: "#fff",
      "stroke-width": "0.5",
      "paint-order": "stroke",
      "pointer-events": "none",
    });
    text.textContent = m.nome_ambiente;
    g.appendChild(text);

    // Interatividade
    g.addEventListener("mouseenter", (e) => mostrarTooltip(e, m));
    g.addEventListener("mousemove", (e) => moverTooltip(e));
    g.addEventListener("mouseleave", () => esconderTooltip());
    if (POD_EDITAR) {
      g.addEventListener("click", (e) => {
        if (editMode) {
          e.stopPropagation();
          abrirModalEditar(m);
        }
      });
    }

    plantaSvg.appendChild(g);
  });
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
   UPLOAD DE PLANTA
   ============================================================ */
btnUploadPlanta?.addEventListener("click", () => {
  if (!obraId) {
    toast("Selecione uma obra antes de enviar a planta.", false);
    return;
  }
  inputPlanta.click();
});

inputPlanta?.addEventListener("change", async () => {
  const file = inputPlanta.files[0];
  if (!file) return;

  const fd = new FormData();
  fd.append("obra_id", obraId);
  fd.append("planta", file);

  btnUploadPlanta.disabled = true;
  btnUploadPlanta.innerHTML =
    '<i class="fa-solid fa-spinner fa-spin"></i> Enviando…';

  try {
    const resp = await fetch(`${BASE_URL}/upload_planta.php`, {
      method: "POST",
      body: fd,
    });
    const data = await resp.json();
    if (data.sucesso) {
      toast(`Planta v${data.versao} enviada com sucesso!`);
      await carregarMapa();
    } else {
      toast(data.erro || "Erro ao enviar planta.", false);
    }
  } catch {
    toast("Falha de comunicação ao enviar planta.", false);
  } finally {
    btnUploadPlanta.disabled = false;
    btnUploadPlanta.innerHTML =
      '<i class="fa-solid fa-upload"></i> Nova Planta';
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
function abrirModalEditar(m) {
  editarMarcacaoId.value = m.id;
  editarNomeAmbiente.value = m.nome_ambiente;
  popularSelectImagens(editarSelectImagem);
  editarSelectImagem.value = m.imagem_id ?? "";
  abrirModal(modalEditar);
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
   INICIALIZAÇÃO
   ============================================================ */
showEmpty();
