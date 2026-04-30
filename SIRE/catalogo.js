/**
 * Catálogo de Referências — catalogo.js
 * Segue exatamente os padrões de Render/script.js
 */

"use strict";

// ── Config ────────────────────────────────────────────────────────────────────

const API_URL = "catalogo_ajax.php";
const PAGE_LIMIT = 48;
const THUMB_W = 360;
const THUMB_Q = 75;

// ── Estado ────────────────────────────────────────────────────────────────────

let allRefs = [];
let filtered = [];
let currentPage = 1;
let totalRefs = 0;

// ── Utilitários ───────────────────────────────────────────────────────────────

function thumbUrl(nomeArquivo) {
  if (!nomeArquivo) return "../assets/logo.jpg";
  return (
    `https://improov.com.br/flow/ImproovWeb/thumb.php` +
    `?path=${encodeURI("uploads/" + nomeArquivo + ".jpg")}` +
    `&w=${THUMB_W}&q=${THUMB_Q}`
  );
}

function originalUrl(nomeArquivo) {
  if (!nomeArquivo) return "../assets/logo.jpg";
  return `https://improov.com.br/flow/ImproovWeb/uploads/${encodeURI(nomeArquivo + ".jpg")}`;
}

function formatDate(str) {
  if (!str) return "—";
  const d = new Date(str.replace(" ", "T"));
  if (isNaN(d)) return str;
  return d.toLocaleDateString("pt-BR", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
}

function isFilterActive() {
  return (
    $("#filterObra").val() !== "" ||
    $("#filterAmbiente").val() !== "" ||
    $("#filterEstilo").val() !== "" ||
    $("#filterTipo").val() !== "" ||
    $("#searchInput").val().trim() !== ""
  );
}

function updateResultsBadge(count) {
  $("#resultsCount").text(count);
  if (totalRefs > 0 && count !== totalRefs) {
    $("#resultsTotal").text(" / " + totalRefs);
  } else {
    $("#resultsTotal").text("");
  }
  if (isFilterActive()) {
    $("#resultsBadge").addClass("has-filter");
    $("#filterDot").addClass("visible");
    $("#btnLimpar").css("display", "inline-flex");
  } else {
    $("#resultsBadge").removeClass("has-filter");
    $("#filterDot").removeClass("visible");
    $("#btnLimpar").css("display", "none");
  }
}

// ── Renderização ──────────────────────────────────────────────────────────────

function renderCards(refs, append) {
  const grid = document.getElementById("refGrid");

  if (!append && !refs.length) {
    grid.innerHTML = `
      <div class="empty-state">
        <i class="fa-solid fa-images"></i>
        <p>Nenhuma referência encontrada</p>
        <span>Tente ajustar os filtros ou a busca</span>
      </div>`;
    updateResultsBadge(0);
    return;
  }

  let html = "";
  refs.forEach(function (ref) {
    const img = thumbUrl(ref.nome_arquivo);
    const obra = ref.obra_nomenclatura || ref.nomenclatura || "—";
    const ambiente = ref.ambiente || "—";
    const estilo = ref.estilo || "";
    const titulo = ref.ambiente || ref.nome_arquivo || "—";
    const data = formatDate(ref.importado_em);

    html += `
      <div class="ref-card"
           data-id="${ref.id}"
           data-nome="${escAttr(ref.nome_arquivo)}"
           data-obra="${escAttr(obra)}"
           data-ambiente="${escAttr(ambiente)}"
           data-estilo="${escAttr(estilo)}"
           data-nomenclatura="${escAttr(ref.nomenclatura || "")}"
           data-data="${escAttr(data)}">
        <div class="card-thumb-wrap">
          <img loading="lazy"
               decoding="async"
               src="${img}"
               alt="${escAttr(titulo)}"
               class="loading"
               onload="this.classList.remove('loading')"
               onerror="this.src='../assets/logo.jpg';this.classList.remove('loading')">
        </div>
        <div class="card-body">
          <p class="card-title" title="${escAttr(titulo)}">${esc(titulo)}</p>
          <div class="card-meta-row">
            <div class="card-meta-item">
              <i class="fa-solid fa-building"></i>
              <span title="${escAttr(obra)}">${esc(obra)}</span>
            </div>
            ${
              ambiente !== "—"
                ? `
            <div class="card-meta-item">
              <i class="fa-solid fa-door-open"></i>
              <span title="${escAttr(ambiente)}">${esc(ambiente)}</span>
            </div>`
                : ""
            }
          </div>
          <div class="card-footer">
            <span class="card-date">
              <i class="fa-regular fa-calendar"></i>
              ${data}
            </span>
          </div>
        </div>
      </div>`;
  });

  if (append) {
    grid.insertAdjacentHTML("beforeend", html);
  } else {
    grid.innerHTML = html;
  }
}

function esc(str) {
  if (!str) return "";
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function escAttr(str) {
  return esc(str);
}

// ── Filtros locais ────────────────────────────────────────────────────────────

function applyFilters() {
  const search = $("#searchInput").val().trim().toLowerCase();
  const obra = $("#filterObra").val();
  const ambiente = $("#filterAmbiente").val().toLowerCase();
  const estilo = $("#filterEstilo").val().toLowerCase();
  const tipo = $("#filterTipo").val().toLowerCase();

  filtered = allRefs.filter(function (ref) {
    if (obra && String(ref.obra_id) !== obra) return false;
    if (ambiente && (ref.ambiente || "").toLowerCase() !== ambiente)
      return false;
    if (estilo && (ref.estilo || "").toLowerCase() !== estilo) return false;
    if (tipo && (ref.tipo || "").toLowerCase() !== tipo) return false;
    if (search) {
      const haystack = [
        ref.nome_arquivo,
        ref.nomenclatura,
        ref.obra_nomenclatura,
        ref.ambiente,
        ref.estilo,
        ref.tipo,
      ]
        .join(" ")
        .toLowerCase();
      if (!haystack.includes(search)) return false;
    }
    return true;
  });

  currentPage = 1;
  renderCards(filtered.slice(0, PAGE_LIMIT), false);
  updateResultsBadge(filtered.length);
  updateLoadMore();
}

function updateLoadMore() {
  const shown = Math.min(currentPage * PAGE_LIMIT, filtered.length);
  const wrap = document.getElementById("loadMoreWrap");
  const counter = document.getElementById("loadMoreCounter");
  if (shown < filtered.length) {
    wrap.style.display = "flex";
    counter.textContent = `(${shown} / ${filtered.length})`;
  } else {
    wrap.style.display = "none";
  }
}

// ── Carregamento da API ───────────────────────────────────────────────────────

function loadRefs() {
  $.ajax({
    url: API_URL,
    method: "GET",
    data: { action: "getRefs" },
    dataType: "json",
    success: function (res) {
      if (res && res.status === "sucesso") {
        allRefs = res.refs || [];
        totalRefs = res.total || allRefs.length;
        filtered = allRefs.slice();
        renderCards(filtered.slice(0, PAGE_LIMIT), false);
        updateResultsBadge(filtered.length);
        updateLoadMore();
        populateDynamicFilters();
      } else {
        showError(
          res && res.message ? res.message : "Erro ao carregar referências.",
        );
      }
    },
    error: function () {
      showError("Erro de comunicação com o servidor.");
    },
  });
}

function showError(msg) {
  Toastify({
    text: msg,
    duration: 4000,
    gravity: "top",
    position: "right",
    style: {
      background: "#ef4444",
      borderRadius: "8px",
      fontFamily: '"Inter", sans-serif',
      fontSize: "13px",
      fontWeight: "500",
    },
  }).showToast();
}

// ── Filtros dinâmicos: estilo e tipo ─────────────────────────────────────────

function populateDynamicFilters() {
  const estilos = [
    ...new Set(allRefs.map((r) => r.estilo).filter(Boolean)),
  ].sort();
  const tipos = [...new Set(allRefs.map((r) => r.tipo).filter(Boolean))].sort();

  const $estilo = $("#filterEstilo");
  const $tipo = $("#filterTipo");

  estilos.forEach(function (e) {
    $estilo.append(`<option value="${escAttr(e)}">${esc(e)}</option>`);
  });
  tipos.forEach(function (t) {
    $tipo.append(`<option value="${escAttr(t)}">${esc(t)}</option>`);
  });
}

// ── Lightbox ──────────────────────────────────────────────────────────────────

function openLightbox(card) {
  const nome = $(card).data("nome") || "";
  const obra = $(card).data("obra") || "—";
  const ambiente = $(card).data("ambiente") || "—";
  const estilo = $(card).data("estilo") || "—";
  const nomenclatura = $(card).data("nomenclatura") || "—";
  const data = $(card).data("data") || "—";

  const fullSrc = originalUrl(nome);
  const thumbSrc = thumbUrl(nome);

  // Preenche o modal
  $("#lb_titulo").text(ambiente !== "—" ? ambiente : nome);
  $("#lb_obra").text(obra);

  const $badge = $("#lb_ambiente");
  if (ambiente && ambiente !== "—") {
    $badge.text(ambiente).show();
  } else {
    $badge.hide();
  }

  $("#lb_nomenclatura").text(nomenclatura);
  $("#lb_arquivo").text(nome + ".jpg");
  $("#lb_obra_det").text(obra);
  $("#lb_ambiente_det").text(ambiente);
  $("#lb_estilo").text(estilo);
  $("#lb_data").text(data);

  // Carrega thumb inicialmente, depois troca para original
  const $img = $("#lbMainImg");
  $img.attr("src", thumbSrc).css("opacity", 0.7);
  const fullImg = new Image();
  fullImg.onload = function () {
    $img.attr("src", fullSrc).css("opacity", 1);
  };
  fullImg.onerror = function () {
    $img.css("opacity", 1);
  };
  fullImg.src = fullSrc;

  // Botão "Ver original"
  $("#btnVerOriginal")
    .off("click.lb")
    .on("click.lb", function () {
      window.open(fullSrc, "_blank", "noopener");
    });

  $("#refLightbox").addClass("is-open");
}

// Clique na imagem principal → fullscreen (mesma lógica de Render/script.js)
$("#lbMainImg")
  .off("click")
  .on("click", function () {
    const src = $(this).attr("src");
    openFullscreen(src);
  });

function openFullscreen(src) {
  const fullScreenDiv = $(`
    <div id="fullscreenImgDiv">
      <div id="image_wrapper">
        <img id="fullscreenImg" src="${src}">
      </div>
    </div>
  `);

  $("body").append(fullScreenDiv);

  const $imageWrapper = fullScreenDiv.find("#image_wrapper");

  let currentZoom = 1;
  const zoomStep = 0.1;
  const maxZoom = 5;
  const minZoom = 0.1;

  let isDragging = false;
  let startX, startY;
  let currentTranslateX = 0;
  let currentTranslateY = 0;
  let dragMoved = false;

  function applyTransforms() {
    $imageWrapper.css(
      "transform",
      `scale(${currentZoom}) translate(${currentTranslateX}px, ${currentTranslateY}px)`,
    );
  }

  // Zoom com Ctrl + scroll
  fullScreenDiv.on("wheel", function (event) {
    if (event.ctrlKey) {
      event.preventDefault();
      if (event.originalEvent.deltaY < 0) {
        currentZoom = Math.min(currentZoom + zoomStep, maxZoom);
      } else {
        currentZoom = Math.max(currentZoom - zoomStep, minZoom);
      }
      if (currentZoom === minZoom) {
        currentTranslateX = 0;
        currentTranslateY = 0;
      }
      applyTransforms();
    }
  });

  // Drag
  $imageWrapper.on("mousedown.fullscreen", function (e) {
    if (e.button !== 0) return;
    isDragging = true;
    dragMoved = false;
    startX = e.clientX - currentTranslateX;
    startY = e.clientY - currentTranslateY;
    $imageWrapper.css("cursor", "grabbing").css("transition", "none");
    e.preventDefault();
  });

  const mouseMoveHandler = function (e) {
    if (!isDragging) return;
    e.preventDefault();
    const dx = e.clientX - startX;
    const dy = e.clientY - startY;
    if (Math.abs(dx) > 3 || Math.abs(dy) > 3) dragMoved = true;
    currentTranslateX = dx;
    currentTranslateY = dy;
    applyTransforms();
  };

  const mouseUpHandler = function () {
    if (isDragging) {
      isDragging = false;
      $imageWrapper
        .css("cursor", "grab")
        .css("transition", "transform 0.1s ease-out");
    }
  };

  $(document).on("mousemove.fullscreen", mouseMoveHandler);
  $(document).on("mouseup.fullscreen", mouseUpHandler);

  // Fechar clicando no fundo ou tecla Esc
  fullScreenDiv.on("click", function (e) {
    if (e.target.id === "fullscreenImgDiv") {
      $(document).off(".fullscreen");
      $(this).remove();
    }
  });

  $(document).on("keydown.fullscreenEsc", function (e) {
    if (e.key === "Escape") {
      $(document).off(".fullscreen").off(".fullscreenEsc");
      fullScreenDiv.remove();
    }
  });

  fullScreenDiv.on("remove", function () {
    $(document).off(".fullscreen").off(".fullscreenEsc");
  });

  applyTransforms();
}

// ── Fechar Lightbox ───────────────────────────────────────────────────────────

function closeLightbox() {
  $("#refLightbox").removeClass("is-open");
  $("#lbMainImg").attr("src", "");
}

$("#closeLightbox, #closeLightboxFooter").on("click", closeLightbox);

$("#refLightbox").on("click", function (e) {
  if (e.target === this) closeLightbox();
});

$(document).on("keydown", function (e) {
  if (e.key === "Escape" && $("#refLightbox").hasClass("is-open")) {
    closeLightbox();
  }
});

// ── Event Delegation (cards) ─────────────────────────────────────────────────

$("#refGrid")
  .off("click.refClick")
  .on("click.refClick", ".ref-card", function () {
    openLightbox(this);
  });

// ── Busca com debounce ────────────────────────────────────────────────────────

let searchTimer = null;

$("#searchInput").on("input", function () {
  const val = $(this).val();
  if (val.trim()) {
    $("#searchClear").addClass("visible");
  } else {
    $("#searchClear").removeClass("visible");
  }
  clearTimeout(searchTimer);
  searchTimer = setTimeout(applyFilters, 280);
});

$("#searchClear").on("click", function () {
  $("#searchInput").val("").trigger("input").focus();
});

// ── Filtros ───────────────────────────────────────────────────────────────────

$("#btnAplicar").on("click", applyFilters);

$("#filterObra, #filterAmbiente, #filterEstilo, #filterTipo").on(
  "change",
  function () {
    applyFilters();
  },
);

$("#btnLimpar").on("click", function () {
  $("#searchInput").val("");
  $("#searchClear").removeClass("visible");
  $("#filterObra").val("");
  $("#filterAmbiente").val("");
  $("#filterEstilo").val("");
  $("#filterTipo").val("");
  applyFilters();
});

// ── Load More ─────────────────────────────────────────────────────────────────

$("#btnLoadMore").on("click", function () {
  $(this).addClass("loading");
  currentPage++;
  const start = (currentPage - 1) * PAGE_LIMIT;
  const slice = filtered.slice(start, start + PAGE_LIMIT);
  renderCards(slice, true);
  updateLoadMore();
  $(this).removeClass("loading");
});

// ── Init ──────────────────────────────────────────────────────────────────────

$(function () {
  $("#btnLimpar").hide();
  loadRefs();
});
