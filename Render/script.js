let allRenders = [];
let currentPage = 1;
let totalRenders = 0;
const PAGE_LIMIT = 200;
let renderFilterOptions = null;
let filterSearchTimeout = null;
let renderRequestSequence = 0;
let renderMotionInitial = true;

let activeRenderView = "pending";
let libraryLayout = "cards";
let detailRequestSequence = 0;
let renderReturnFocus;
const renderNumber = new Intl.NumberFormat("pt-BR", {
  maximumFractionDigits: 1,
});
const renderGroups = {
  rendering: "Em execução",
  queued: "Na fila",
  error: "Com erro",
  rework: "Com retrabalho",
  other: "Demais pendências",
};

function renderDuration(seconds) {
  const total = Math.max(0, Math.round(Number(seconds)));
  return [Math.floor(total / 3600), Math.floor(total / 60) % 60, total % 60]
    .map((part) => String(part).padStart(2, "0"))
    .join(":");
}

function renderPreviewUrl(filename, width) {
  const base = "https://improov.com.br/flow/ImproovWeb/";
  return width
    ? base +
        "thumb.php?path=" +
        encodeURIComponent("uploads/renders/" + filename) +
        "&w=" +
        width +
        "&q=75"
    : base + "uploads/renders/" + encodeURIComponent(filename);
}

function updateRenderSummary(summary) {
  const metricValue = (value, suffix = "") =>
    value == null ? "—" : renderNumber.format(value) + suffix;
  $("#pendingCount").text(metricValue(summary?.pending));
  $("#approvedCount").text(metricValue(summary?.approved));
  document.querySelectorAll("[data-render-metric]").forEach((metric) => {
    const key = metric.dataset.renderMetric;
    const value = summary?.[key];
    metric.textContent =
      key === "mean_seconds" && value != null
        ? renderDuration(value)
        : metricValue(
            value,
            key === "success_rate" ? "%" : key === "per_day" ? "/dia" : "",
          );
  });
  const success = document.getElementById("renderSuccessProgress");
  success.hidden = summary?.success_rate == null;
  success.value = summary?.success_rate ?? 0;
}

function setRenderView(view) {
  if (activeRenderView === view) return;
  const wasFarm = activeRenderView === "farm";
  activeRenderView = view;
  const farm = view === "farm";
  document.querySelectorAll("[data-render-view]").forEach((tab) => {
    const selected = tab.dataset.renderView === view;
    tab.setAttribute("aria-selected", String(selected));
    tab.tabIndex = selected ? 0 : -1;
  });
  document.getElementById("renderLibrary").hidden = farm;
  document.getElementById("renderFarm").hidden = !farm;
  $("#filters").removeClass("open");
  $("#filter-toggle-btn").attr("aria-expanded", "false");
  if (!farm) {
    document
      .getElementById("renderLibrary")
      .setAttribute(
        "aria-labelledby",
        view === "pending" ? "tabPending" : "tabApproved",
      );
    document.getElementById("renderMetrics").hidden = view !== "pending";
    document.getElementById("renderViewSwitch").hidden = view !== "approved";
    document
      .getElementById("renderGrid")
      .classList.toggle(
        "is-list",
        view === "approved" && libraryLayout === "list",
      );
    if (!wasFarm) $("#filterStatus").val("");
    allRenders = [];
    $("#renderGrid").empty().attr("aria-busy", "true");
    $("#renderLoading").prop("hidden", false);
    $("#loadMoreWrap").hide();
    updateResultsBadge(0, isFilterActive());
    loadRenders(1);
  } else {
    $("#resultsBadge").prop("hidden", true);
    renderRequestSequence++;
  }
  document.querySelector(".render-center").scrollTop = 0;
  window.setRenderFarmActive?.(farm);
}

const renderTabs = [...document.querySelectorAll("[data-render-view]")];
renderTabs.forEach((tab, index) => {
  tab.addEventListener("click", () => setRenderView(tab.dataset.renderView));
  tab.addEventListener("keydown", (event) => {
    const destinations = {
      ArrowLeft: (index + 2) % 3,
      ArrowRight: (index + 1) % 3,
      Home: 0,
      End: 2,
    };
    if (!Object.hasOwn(destinations, event.key)) return;
    event.preventDefault();
    const next = renderTabs[destinations[event.key]];
    next.focus();
    setRenderView(next.dataset.renderView);
  });
});
document.querySelectorAll("[data-library-layout]").forEach((button) => {
  button.addEventListener("click", () => {
    libraryLayout = button.dataset.libraryLayout;
    document
      .getElementById("renderGrid")
      .classList.toggle("is-list", libraryLayout === "list");
    document.querySelectorAll("[data-library-layout]").forEach((item) => {
      item.setAttribute("aria-pressed", String(item === button));
    });
  });
});

function renderObraFilter() {
  const selected = $("#filterObra").val();
  const obras =
    renderFilterOptions?.obras ||
    [
      ...new Set(allRenders.map((r) => r.obra_nomenclatura).filter(Boolean)),
    ].sort();
  $("#filterObra").empty().append('<option value="">Todas</option>');
  obras.forEach((nome) => {
    $("#filterObra").append($("<option>").val(nome).text(nome));
  });
  if (selected && obras.includes(selected)) {
    $("#filterObra").val(selected);
  }
}

function renderCollaboratorFilter() {
  const selected = $("#filterColaborador").val();
  const colaboradores =
    renderFilterOptions?.colaboradores ||
    [
      ...new Set(allRenders.map((r) => r.nome_colaborador).filter(Boolean)),
    ].sort();
  $("#filterColaborador").empty().append('<option value="">Todos</option>');
  colaboradores.forEach((nome) => {
    $("#filterColaborador").append($("<option>").val(nome).text(nome));
  });
  if (selected && colaboradores.includes(selected)) {
    $("#filterColaborador").val(selected);
  }
}

function renderStatusFilter() {
  const selected = $("#filterStatus").val();
  const status =
    renderFilterOptions?.status ||
    [...new Set(allRenders.map((r) => r.status).filter(Boolean))].sort();
  const select = document.getElementById("filterStatus");
  select.replaceChildren(new Option("Todos", ""));
  const flowOptions = document.createElement("optgroup");
  flowOptions.label = "Status no Flow";
  status.forEach((nome) => {
    flowOptions.append(new Option(nome, nome));
  });
  select.append(flowOptions);
  if (activeRenderView === "pending") {
    const groups = document.createElement("optgroup");
    groups.label = "Grupos de pendências";
    Object.entries(renderGroups).forEach(([key, label]) =>
      groups.append(new Option(label, "group:" + key)),
    );
    select.append(groups);
  }
  if ([...select.options].some((option) => option.value === selected))
    select.value = selected;
}

function renderStatusImagemFilter() {
  const selected = $("#filterStatusImagem").val();
  const statusImagens =
    renderFilterOptions?.statusImagem ||
    [...new Set(allRenders.map((r) => r.nome_status).filter(Boolean))].sort();
  $("#filterStatusImagem").empty().append('<option value="">Todos</option>');
  statusImagens.forEach((nome) => {
    $("#filterStatusImagem").append($("<option>").val(nome).text(nome));
  });
  if (selected && statusImagens.includes(selected)) {
    $("#filterStatusImagem").val(selected);
  }
}

function formatarData(data) {
  const dataObj = data instanceof Date ? data : new Date(data);

  const pad = (num) => num.toString().padStart(2, "0");

  const dia = pad(dataObj.getDate());
  const mes = pad(dataObj.getMonth() + 1);
  const ano = dataObj.getFullYear();

  const hora = pad(dataObj.getHours());
  const min = pad(dataObj.getMinutes());
  const seg = pad(dataObj.getSeconds());

  return `${dia}/${mes}/${ano} ${hora}:${min}:${seg}`;
}

/* --- Badge helper --- */
function getStatusBadgeClass(status) {
  const map = {
    Finalizado: "s-finalizado",
    Aprovado: "s-aprovado",
    "Em andamento": "s-andamento",
    "Em aprovação": "s-aprovacao",
    Reprovado: "s-reprovado",
    Refazendo: "s-refazendo",
    Erro: "s-erro",
    RVW_DONE: "s-rvw-done",
    PRE_ALT: "s-pre-alt",
    READY_FOR_PLANNING: "s-ready-for-planning",
  };
  return map[status] || "s-outro";
}

function getStatusIcon(status) {
  const icons = {
    Finalizado: "fa-circle-check",
    Aprovado: "fa-circle-check",
    "Em andamento": "fa-circle-half-stroke",
    "Em aprovação": "fa-circle-dot",
    Reprovado: "fa-circle-xmark",
    Refazendo: "fa-rotate-right",
    Erro: "fa-circle-exclamation",
    RVW_DONE: "fa-circle-check",
    PRE_ALT: "fa-magnifying-glass",
    READY_FOR_PLANNING: "fa-circle-check",
  };
  return icons[status] || "fa-circle";
}

function formatDateShort(dateStr) {
  if (!dateStr) return "—";
  const d = new Date(dateStr);
  if (isNaN(d)) return "—";
  return d.toLocaleDateString("pt-BR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
}

function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>'"]/g, (character) => {
    const entities = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      "'": "&#39;",
      '"': "&quot;",
    };
    return entities[character];
  });
}

function normalizeDeadlineProgress(value) {
  if (value === null || value === undefined || String(value).trim() === "") {
    return null;
  }
  const progress = Number(value);
  return Number.isFinite(progress) && progress >= 0 && progress <= 100
    ? progress
    : null;
}

function formatDeadlineProgress(progress) {
  const rounded = Math.round(progress * 10) / 10;
  return Number.isInteger(rounded)
    ? String(rounded)
    : rounded.toLocaleString("pt-BR");
}

function deadlineProgressTone(progress) {
  if (progress <= 25) return "is-gray";
  if (progress <= 60) return "is-blue";
  if (progress <= 90) return "is-orange";
  if (progress < 100) return "is-yellow";
  return "is-green";
}

function formatDeadlineTaskDuration(value) {
  const raw = String(value || "").trim();
  if (!raw || raw.includes("?")) return "";
  const parts = raw.split(":").map((part) => Number(part));
  if (parts.length !== 3 || parts.some((part) => !Number.isFinite(part))) {
    return raw;
  }
  const [hours, minutes, seconds] = parts;
  const formatted = [];
  if (hours > 0) formatted.push(`${hours}h`);
  if (minutes > 0 || hours > 0) formatted.push(`${minutes}m`);
  formatted.push(`${seconds}s`);
  return formatted.join(" ");
}

function renderDeadlineProgress(render, context = "card") {
  const progress = normalizeDeadlineProgress(
    render.deadline_task_progress ?? render.deadline_job_progress,
  );
  const tooltipId = `deadlineProgressTooltip-${context}-${Number(render.idrender_alta)}`;
  if (progress === null) {
    return `
      <div class="deadline-progress is-unavailable" tabindex="0" aria-describedby="${tooltipId}" aria-label="Progresso do Deadline indisponível">
        <div class="deadline-progress-track" aria-hidden="true"><span class="deadline-progress-fill"></span></div>
        <span class="deadline-progress-value">—</span>
        <span class="deadline-progress-tooltip" id="${tooltipId}" role="tooltip">Progresso indisponível no Deadline</span>
      </div>`;
  }

  const percentage = formatDeadlineProgress(progress);
  const eta = String(render.deadline_estimated_time_remaining || "").trim();
  const taskSummary = String(
    render.deadline_task_render_summary ||
      render.deadline_task_render_status ||
      "",
  ).trim();
  const elapsed = formatDeadlineTaskDuration(render.deadline_task_elapsed);
  const remaining = formatDeadlineTaskDuration(
    render.deadline_task_time_remaining,
  );
  const taskTimes =
    elapsed || remaining
      ? `<span class="deadline-progress-time-grid"><span><small>Decorrido</small><b>${escapeHtml(elapsed || "—")}</b></span><span><small>Restante estimado</small><b>${escapeHtml(remaining || "—")}</b></span></span>`
      : "";
  return `
    <div class="deadline-progress ${deadlineProgressTone(progress)}" tabindex="0" aria-describedby="${tooltipId}" aria-label="Progresso do Deadline: ${percentage}% concluído">
      <div class="deadline-progress-track" aria-hidden="true"><progress class="deadline-progress-fill" max="100" value="${progress}"></progress></div>
      <span class="deadline-progress-value">${percentage}%</span>
      <span class="deadline-progress-tooltip" id="${tooltipId}" role="tooltip"><strong>Progresso do Deadline</strong><span>${percentage}% concluído</span>${taskSummary ? `<span class="deadline-progress-task-status">${escapeHtml(taskSummary)}</span>` : ""}${taskTimes}${!remaining && eta ? `<span>Tempo restante estimado: ${escapeHtml(eta)}</span>` : ""}</span>
    </div>`;
}

function renderCards(renders, motionStartIndex) {
  const grid = document.getElementById("renderGrid");

  if (!renders.length) {
    grid.innerHTML = `
      <div class="empty-state">
        <i class="fa-solid fa-layer-group"></i>
        <p>Nenhum render encontrado</p>
        <span>Tente ajustar os filtros aplicados</span>
      </div>`;
    updateResultsBadge(0, true, totalRenders);
    return;
  }

  let html = "";
  let lastGroup;
  renders.forEach(function (render) {
    const imgUrl = render.previa_jpg
      ? renderPreviewUrl(render.previa_jpg, 540)
      : "../assets/logo.jpg";

    const sc = getStatusBadgeClass(render.status);
    const ico = getStatusIcon(render.status);
    const dateLabel = formatDateShort(render.data);
    const isInProgress =
      render.execution_state === "rendering" ||
      (!render.execution_state && render.status === "Em andamento");
    const deadlineProgress = isInProgress ? renderDeadlineProgress(render) : "";
    const manualBadge = render.concluido_manualmente
      ? '<span class="status-badge manual-completion-badge" title="Execução concluída fora do Deadline"><i class="fa-solid fa-hand"></i> Feito manualmente</span>'
      : "";
    const obra = escapeHtml(render.obra_nomenclatura || "—");
    const colab = escapeHtml(render.nome_colaborador || "—");
    const image = escapeHtml(render.imagem_nome || "Imagem sem nome");
    const status = escapeHtml(render.status || "—");
    if (activeRenderView === "pending" && render.render_group !== lastGroup) {
      lastGroup = render.render_group;
      html += `<h2 class="render-group-title">${renderGroups[lastGroup] || renderGroups.other}</h2>`;
    }

    html += `
      <article class="render-card" tabindex="0" data-motion-item data-id="${Number(render.idrender_alta)}" data-status="${status}">
        <div class="card-thumb-wrap">
          <img loading="lazy" decoding="async" src="${imgUrl}" alt="" class="loading"
               onload="this.classList.remove('loading')" onerror="this.onerror=null; this.src='../assets/logo.jpg'">
        </div>
        <button type="button" class="card-menu" aria-label="Trocar responsável de ${image}" title="Trocar responsável"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>
        ${deadlineProgress}
        <div class="card-body">
          <p class="card-title" title="${image}">${image}</p>
          <div class="card-meta-row">
            <div class="card-meta-item">
              <i class="fa-solid fa-building"></i>
              <span title="${obra}">${obra} · ${escapeHtml(render.nome_status || "—")}</span>
            </div>
            <div class="card-meta-item">
              <i class="fa-solid fa-user"></i>
              <span title="${colab}">${colab}</span>
            </div>
          </div>
          <div class="card-footer">
            <span class="status-badge ${sc}">
              <i class="fa-solid ${ico}"></i>
              ${status}
            </span>
            ${manualBadge}
            <span class="card-date">
              <i class="fa-regular fa-calendar"></i>
              ${dateLabel}
            </span>
          </div>
        </div>
      </article>`;
  });

  grid.innerHTML = html;
  updateResultsBadge(renders.length, isFilterActive(), totalRenders);
  if (window.FlowMotion) {
    const cards = Array.prototype.slice.call(
      grid.querySelectorAll(".render-card"),
    );
    const newCards = cards.slice(Math.max(0, Number(motionStartIndex) || 0));
    window.FlowMotion.enterItems(grid, {
      items: newCards,
      initial: renderMotionInitial,
      preset: "card",
    });
    renderMotionInitial = false;
  }
}

// Use event delegation to avoid re-attaching handlers on every re-render
$("#renderGrid")
  .off("click.renderClick")
  .on(
    "click.renderClick keydown.renderClick",
    ".render-card",
    function (event) {
      if ($(event.target).closest(".card-menu").length) return;
      if (event.type === "keydown") {
        if (event.target !== this || !["Enter", " "].includes(event.key))
          return;
        event.preventDefault();
      }
      renderReturnFocus = this;
      const idrender_alta = $(this).data("id");
      editRender(idrender_alta);
    },
  );

function positionDeadlineProgressTooltip(element) {
  const tooltip = element.querySelector(".deadline-progress-tooltip");
  if (!tooltip) return;
  tooltip.style.setProperty("--deadline-tooltip-shift", "0px");
  const target = element.getBoundingClientRect();
  const tooltipWidth = tooltip.offsetWidth;
  const viewportPadding = 12;
  const centeredLeft = target.left + target.width / 2 - tooltipWidth / 2;
  const minShift = viewportPadding - centeredLeft;
  const maxShift =
    window.innerWidth - viewportPadding - (centeredLeft + tooltipWidth);
  const shift = minShift > 0 ? minShift : maxShift < 0 ? maxShift : 0;
  tooltip.style.setProperty(
    "--deadline-tooltip-shift",
    `${Math.round(shift)}px`,
  );
}

$("#renderGrid")
  .off("mouseenter.deadlineProgress focusin.deadlineProgress")
  .on(
    "mouseenter.deadlineProgress focusin.deadlineProgress",
    ".deadline-progress",
    function () {
      positionDeadlineProgressTooltip(this);
    },
  )
  .off("keydown.deadlineProgress")
  .on("keydown.deadlineProgress", ".deadline-progress", function (event) {
    if (event.key !== "Enter" && event.key !== " ") return;
    event.preventDefault();
    $(this).closest(".render-card").trigger("click");
  });

window.addEventListener("resize", () => {
  document
    .querySelectorAll(".deadline-progress:hover, .deadline-progress:focus")
    .forEach(positionDeadlineProgressTooltip);
});

function loadRenders(page) {
  if (activeRenderView === "farm") return;
  page = page || 1;
  const requestSequence = ++renderRequestSequence;
  const btn = document.getElementById("btnLoadMore");
  if (page > 1) {
    btn.disabled = true;
    btn.querySelector("i").hidden = true;
    btn.querySelector("canvas").hidden = false;
  }

  $.ajax({
    url: "ajax.php",
    method: "GET",
    data: {
      action: "getRenders",
      view: activeRenderView,
      page: page,
      limit: PAGE_LIMIT,
      ...getActiveFilters(),
    },
    dataType: "json",
    success: function (response) {
      if (requestSequence !== renderRequestSequence) return;
      if (response.status === "sucesso") {
        const loadedBefore = allRenders.length;
        if (page === 1) {
          allRenders = response.renders;
        } else {
          allRenders = allRenders.concat(response.renders);
        }
        currentPage = page;

        const total = response.total || 0;
        totalRenders = total;
        updateRenderSummary(response.summary);
        const loaded = allRenders.length;
        const hasMore = loaded < total;

        renderFilterOptions = response.filterOptions || renderFilterOptions;
        renderObraFilter();
        renderCollaboratorFilter();
        renderStatusFilter();
        renderStatusImagemFilter();
        updateFilterActions();
        renderCards(allRenders, page === 1 ? 0 : loadedBefore);

        // Show / hide "Carregar mais"
        const wrap = document.getElementById("loadMoreWrap");
        if (wrap) wrap.style.display = hasMore ? "flex" : "none";
        const counter = document.getElementById("loadMoreCounter");
        if (counter)
          counter.textContent = hasMore ? `(${loaded} / ${total})` : "";
      } else if (page === 1) {
        showRenderLoadError();
      }
    },
    error: function () {
      if (requestSequence === renderRequestSequence && page === 1)
        showRenderLoadError();
    },
    complete: function () {
      if (requestSequence !== renderRequestSequence) return;
      $("#renderLoading").prop("hidden", true);
      $("#renderGrid").attr("aria-busy", "false");
      btn.disabled = false;
      btn.querySelector("i").hidden = false;
      btn.querySelector("canvas").hidden = true;
    },
  });
}

function showRenderLoadError() {
  updateRenderSummary(null);
  updateResultsBadge(0, isFilterActive());
  $("#loadMoreWrap").hide();
  $("#renderGrid").html(
    '<div class="empty-state" role="status"><p>Não foi possível carregar os renders.</p><button type="button" id="retryRenders">Tentar novamente</button></div>',
  );
}
$("#renderGrid").on("click", "#retryRenders", () => loadRenders(1));

function getActiveFilters() {
  const status = $("#filterStatus").val() || "";
  const isGroup = status.startsWith("group:");
  return {
    status: isGroup ? "" : status,
    renderGroup: isGroup ? status.slice("group:".length) : "",
    statusImagem: $("#filterStatusImagem").val() || "",
    colaborador: $("#filterColaborador").val() || "",
    obra: $("#filterObra").val() || "",
    search: $("#filterSearch").val().trim(),
    dateFrom: $("#filterDateFrom").val() || "",
    dateTo: $("#filterDateTo").val() || "",
  };
}

/* --- Results badge --- */
function updateResultsBadge(count, hasFilter, total) {
  const badge = document.getElementById("resultsBadge");
  const countEl = document.getElementById("resultsCount");
  const totalEl = document.getElementById("resultsTotal");
  const dot = document.getElementById("filterDot");
  if (!badge) return;
  badge.hidden = false;
  document.getElementById("resultsUnit").textContent = "renders";
  countEl.textContent = renderNumber.format(count);
  if (total !== undefined && total > 0) {
    totalEl.textContent = " / " + renderNumber.format(total);
  } else {
    totalEl.textContent = "";
  }
  if (hasFilter) {
    badge.classList.add("has-filter");
    dot.classList.add("visible");
  } else {
    badge.classList.remove("has-filter");
    dot.classList.remove("visible");
  }
}

function isFilterActive() {
  return !!(
    $("#filterStatus").val() ||
    $("#filterStatusImagem").val() ||
    $("#filterColaborador").val() ||
    $("#filterObra").val() ||
    $("#filterSearch").val() ||
    $("#filterDateFrom").val() ||
    $("#filterDateTo").val()
  );
}

function updateFilterActions() {
  const hasFilter = isFilterActive();
  document.getElementById("btnLimpar").style.display = hasFilter
    ? "inline-flex"
    : "none";
}

function applyRenderFilters() {
  loadRenders(1);
}

// Os filtros são aplicados no servidor antes da paginação.
$("#filterStatus, #filterStatusImagem, #filterColaborador, #filterObra").on(
  "change",
  applyRenderFilters,
);
$("#filterDateFrom, #filterDateTo").on("change", applyRenderFilters);
$("#filterSearch").on("input", function () {
  clearTimeout(filterSearchTimeout);
  filterSearchTimeout = setTimeout(applyRenderFilters, 300);
});

$("#btnAplicar").on("click", applyRenderFilters);

$("#btnLimpar").on("click", function () {
  $("#filterStatus, #filterStatusImagem, #filterColaborador, #filterObra").val(
    "",
  );
  $("#filterSearch").val("");
  $("#filterDateFrom, #filterDateTo").val("");
  applyRenderFilters();
});

/* --- Timeline helpers --- */
function buildTimelineEntry(ev) {
  var d = ev.data ? new Date(ev.data.replace(" ", "T")) : null;
  var pad = function (n) {
    return String(n).padStart(2, "0");
  };
  var datePart = d
    ? pad(d.getDate()) + "/" + pad(d.getMonth() + 1) + "/" + d.getFullYear()
    : "—";
  var timePart = d ? pad(d.getHours()) + ":" + pad(d.getMinutes()) : "";

  var srcClass = ev.source === "log" ? "tl-source-log" : "tl-source-fallback";
  var startClass = ev.is_start ? "tl-is-start" : "";

  var actionHtml;
  if (ev.type === "manual_completion") {
    var actor = escapeHtml(ev.autor || "Usuário não identificado");
    var reason = escapeHtml(ev.justificativa || "Sem justificativa informada.");
    actionHtml =
      '<span class="status-badge manual-completion-badge"><i class="fa-solid fa-hand"></i> Feito manualmente</span>' +
      '<span class="tl-manual-detail">Por ' +
      actor +
      ": " +
      reason +
      "</span>";
  } else if (ev.is_start || !ev.status_anterior) {
    var bc = getStatusBadgeClass(ev.status_novo);
    actionHtml =
      '<span class="status-badge ' + bc + '">' + ev.status_novo + "</span>";
  } else {
    var bc2 = getStatusBadgeClass(ev.status_novo);
    actionHtml =
      '<span class="tl-prev" style="color:var(--text-tertiary);font-size:11.5px;">' +
      ev.status_anterior +
      "</span>" +
      ' <i class="fa-solid fa-arrow-right" style="font-size:8px;color:var(--text-muted);"></i> ' +
      '<span class="status-badge ' +
      bc2 +
      '">' +
      ev.status_novo +
      "</span>";
  }

  return (
    '<div class="tl-entry ' +
    srcClass +
    " " +
    startClass +
    '">' +
    '<div class="tl-date">' +
    datePart +
    '<span class="tl-time">' +
    timePart +
    "</span>" +
    "</div>" +
    '<div class="tl-right">' +
    '<div class="tl-action">' +
    actionHtml +
    "</div>" +
    "</div>" +
    "</div>"
  );
}

function renderTimeline(events) {
  var $container = $("#renderTimeline");
  if (!events || !events.length) {
    $container.html('<div class="tl-empty">Sem histórico disponível.</div>');
    return;
  }
  var html = "";
  for (var i = 0; i < events.length; i++) {
    html += buildTimelineEntry(events[i]);
  }
  $container.html(html);
}

function loadRenderTimeline(renderId, sequence) {
  $("#renderTimeline").empty();
  $("#timelineLoading").prop("hidden", false);
  $.ajax({
    url: "ajax.php",
    method: "GET",
    data: { action: "getRenderTimeline", render_id: renderId },
    dataType: "json",
    success: function (resp) {
      if (sequence !== detailRequestSequence) return;
      if (resp && resp.status === "sucesso") {
        renderTimeline(resp.timeline || []);
      } else {
        $("#renderTimeline").html(
          '<div class="tl-empty">Erro ao carregar histórico.</div>',
        );
      }
    },
    error: function () {
      if (sequence !== detailRequestSequence) return;
      $("#renderTimeline").html(
        '<div class="tl-empty">Erro ao carregar histórico.</div>',
      );
    },
    complete: function () {
      if (sequence === detailRequestSequence)
        $("#timelineLoading").prop("hidden", true);
    },
  });
}

const executionLabels = {
  rendering: "Renderizando",
  queued: "Na fila",
  completed: "Concluído",
  failed: "Falhou",
  suspended: "Suspenso",
};
const attemptLabels = {
  AGUARDANDO_JOB: "Aguardando job",
  VINCULADA: "Vinculada",
  EM_ANDAMENTO: "Em andamento",
  EM_APROVACAO: "Em aprovação",
  ERRO: "Erro",
  APROVADA: "Aprovada",
  REPROVADA: "Reprovada",
  REFAZENDO: "Refazendo",
  EXCLUSAO_PENDENTE: "Exclusão pendente",
  ENCERRADA: "Encerrada",
  CANCELADA: "Cancelada",
  CONCLUIDA_MANUALMENTE: "Concluída manualmente",
  INCONSISTENTE: "Inconsistente",
};

function updateProcessingDetails(render, attempts, logs) {
  const execution = render.execution;
  const progress = normalizeDeadlineProgress(
    render.deadline_task_progress ?? render.deadline_job_progress,
  );
  const elapsed = formatDeadlineTaskDuration(render.deadline_task_elapsed);
  const remaining = formatDeadlineTaskDuration(
    render.deadline_task_time_remaining ||
      render.deadline_estimated_time_remaining,
  );
  $("#modalDeadline").prop("hidden", !render.detail_job_id);
  const inferredState =
    render.status === "Em andamento" && elapsed ? "rendering" : "";
  $("#modal_deadline_state")
    .text(
      executionLabels[execution?.execution_state] ||
        executionLabels[inferredState] ||
        "—",
    )
    .attr(
      "title",
      execution?.observed_at
        ? `Último registro do Deadline: ${formatarData(execution.observed_at)}`
        : "",
    );
  $("#modal_deadline_worker").text(execution?.worker || render.computer || "—");
  $("#modal_deadline_job").text(render.detail_job_id || "—");
  $("#modalDeadlineProgress").html(
    progress === null ? "" : renderDeadlineProgress(render, "detail"),
  );
  $("#modalDeadlineTimes").prop("hidden", !elapsed && !remaining);
  $("#modalDeadlineElapsed").text(elapsed || "—");
  $("#modalDeadlineRemaining").text(remaining || "—");
  $("#attemptCount").text(attempts.length ? `(${attempts.length})` : "");

  const attemptRows = attempts.map((attempt) => {
    const attemptStatus = attemptLabels[attempt.status] || attempt.status;
    const date =
      attempt.encerrado_em ||
      attempt.concluido_em ||
      attempt.iniciado_em ||
      attempt.criado_em;
    return `<article><header><strong>Tentativa ${Number(attempt.numero_tentativa)} · ${escapeHtml(attemptStatus)}</strong><time>${formatarData(date)}</time></header>
      ${attempt.deadline_job_id ? `<span>Job ${escapeHtml(attempt.deadline_job_id)}</span>` : ""}
      ${attempt.motivo_encerramento ? `<span>${escapeHtml(attempt.motivo_encerramento)}</span>` : ""}</article>`;
  });
  $("#historyAttempts").html(
    attemptRows.join("") ||
      '<p class="tl-empty">Nenhuma tentativa registrada.</p>',
  );
  const logRows = logs.map((log) => {
    const name = log.tipo.replaceAll("_", " ").toLocaleLowerCase("pt-BR");
    return `<article><header><strong>Tentativa ${Number(log.numero_tentativa)} · ${escapeHtml(name)}</strong><time>${formatarData(log.criado_em)}</time></header>
      ${log.message ? `<span>${escapeHtml(log.message)}</span>` : ""}</article>`;
  });
  if (render.errors)
    logRows.unshift(
      `<article><strong>Erros registrados no Flow</strong><pre>${escapeHtml(render.errors)}</pre></article>`,
    );
  $("#historyLogs").html(
    logRows.join("") ||
      '<p class="tl-empty">Nenhum log de processamento disponível.</p>',
  );
}

$("[data-history-tab]").on("click", function () {
  const eventButton = this;
  $("[data-history-tab]").each(function () {
    const selected = this === eventButton;
    this.setAttribute("aria-pressed", String(selected));
    document.getElementById(this.getAttribute("aria-controls")).hidden =
      !selected;
  });
});

// Função para abrir o modal e carregar os dados para edição
function editRender(idrender_alta) {
  const sequence = ++detailRequestSequence;
  const isOpen = $("#myModal").hasClass("is-open");
  $.ajax({
    url: "ajax.php",
    method: "GET",
    data: { action: "getRender", idrender_alta: idrender_alta },
    dataType: "json",
    success: function (response) {
      if (sequence !== detailRequestSequence) return;
      if (response.status == "sucesso") {
        const r = response.render;
        const canRenderEf =
          String(r.imagem_etapa_atual || "")
            .trim()
            .toUpperCase() === "EF";
        $("#renderHighResolution")
          .prop("disabled", !canRenderEf)
          .attr(
            "title",
            canRenderEf
              ? "Criar novo render EF em alta resolução"
              : "Disponível somente quando a imagem já está na etapa EF",
          );
        const isPlantaHumanizada =
          String(r.tipo_imagem || "")
            .trim()
            .toLowerCase() === "planta humanizada";
        $("#myModal").data("tipo-imagem", r.tipo_imagem || "");

        // — Header fields —
        $("#modal_imagem_id").text(r.imagem_nome || "—");
        $("#modal_obra_nome").text(r.obra_nomenclatura || "");
        $("#modal_stage").text(r.nome_status || "");
        $("#modal_image_type").text(r.tipo_imagem || "—");

        // Status badge in header subtitle
        const sc = getStatusBadgeClass(r.status);
        const ico = getStatusIcon(r.status);
        const manualHeaderBadge = r.concluido_manualmente
          ? ' <span class="status-badge manual-completion-badge"><i class="fa-solid fa-hand"></i> Feito manualmente</span>'
          : "";
        $("#modal_status_badge").html(
          `<span class="status-badge ${sc}"><i class="fa-solid ${ico}"></i> ${escapeHtml(r.status)}</span>${manualHeaderBadge}`,
        );

        if (r.status !== "Aprovado" || isPlantaHumanizada) {
          $("#openRenderReferences").hide();
        } else {
          $("#openRenderReferences").show();
        }

        // — Detail fields —
        $("#modal_idrender").text(r.idrender_alta || "—");
        $("#modal_numero_bg").text(r.numero_bg || "—");

        $("#modal_status").text(r.status || "—");
        $("#modal_status_id").text(r.nome_status || "—");
        $("#modal_responsavel_id").text(r.nome_colaborador || "—");
        $("#modal_computer").text(r.computer || "—");

        $("#modal_job_folder").text(r.job_folder || "—");
        $("#modal_previa_jpg").text(r.previa_jpg || "—");
        updateProcessingDetails(
          r,
          response.attempts || [],
          response.processing_logs || [],
        );

        // — Error section —
        if (r.concluido_manualmente) {
          const actor = r.concluido_manualmente_por || "—";
          $("#modal_manual_completion")
            .text(`Feito manualmente por ${actor}`)
            .attr("title", r.justificativa_conclusao_manual || "");
          $("#manualCompletionDetail").show();
        } else {
          $("#manualCompletionDetail").hide();
          $("#modal_manual_completion").text("—").attr("title", "");
        }

        const errors = r.errors || "";
        if (errors) {
          $("#errorsContainer").show();
          $("#modal_errors").text(errors).slideUp(0);
          $("#toggleErrors").removeClass("open");
        } else {
          $("#errorsContainer").hide();
        }

        $("#toggleErrors")
          .off("click")
          .on("click", function (event) {
            event.preventDefault();
            const $body = $("#modal_errors");
            const $btn = $(this);
            if ($btn.hasClass("open")) {
              $btn.removeClass("open");
              $body.slideUp(200);
            } else {
              $btn.addClass("open");
              $body.slideDown(200);
            }
          });

        // — Image preview + gallery —
        const previews = response.previews || [];
        const $gallery = $("#modalGallery");
        $gallery.empty();

        if (previews.length > 0) {
          const firstUrl = renderPreviewUrl(previews[0].filename);
          $("#modalPreviewImg").attr("src", firstUrl);

          previews.forEach(function (p, idx) {
            const thumbUrl = renderPreviewUrl(p.filename);
            const $thumb = $(
              `<img class="modal-thumb${idx === 0 ? " active" : ""}" loading="lazy" decoding="async" src="${thumbUrl}" alt="Preview ${idx + 1}">`,
            );
            $gallery.append($thumb);
          });

          // Thumbnail click
          $gallery
            .off("click.gallery")
            .on("click.gallery", ".modal-thumb", function () {
              $("#modalPreviewImg").attr("src", $(this).attr("src"));
              $gallery.find(".modal-thumb").removeClass("active");
              $(this).addClass("active");
            });
        } else {
          const imgUrl = r.previa_jpg
            ? renderPreviewUrl(r.previa_jpg)
            : "../assets/logo.jpg";
          $("#modalPreviewImg").attr("src", imgUrl);
        }

        // — Open modal —
        $("#myModal").addClass("is-open");
        if (!isOpen) {
          $("[data-history-tab='timeline']").trigger("click");
          document.querySelector("#myModal .modal-body").scrollTop = 0;
          window.FlowMotion?.openModal(
            document.querySelector("#myModal .modal-content"),
          );
          document.getElementById("closeModal").focus({ preventScroll: true });
        }

        // — Load timeline —
        loadRenderTimeline(idrender_alta, sequence);

        // — Action button visibility —
        if (r.status === "Reprovado" || r.status === "Refazendo") {
          $("#aprovarRender").hide();
          $("#reprovarRender").hide();
        } else if (r.status === "Aprovado") {
          $("#aprovarRender").hide();
          $("#reprovarRender")
            .show()
            .data("target-status", "Refazendo")
            .html('<i class="fa-solid fa-rotate-right"></i> Refazer');
        } else {
          $("#aprovarRender").show();
          $("#reprovarRender")
            .show()
            .data("target-status", "Reprovado")
            .html('<i class="fa-solid fa-rotate-right"></i> Reprovar');
        }
        $("#completeRenderManually").toggle(!!r.can_complete_manually);
      }
    },
  });
}

$("#modalPreviewImg")
  .off("click")
  .on("click", function () {
    const src = $(this).attr("src");

    // Criar modal fullscreen
    const fullScreenDiv = $(`
        <div id="fullscreenImgDiv">
            <div id="image_wrapper">
                <img id="fullscreenImg" src="${src}">
            </div>
        </div>
    `);

    $("body").append(fullScreenDiv);

    // scope the elements inside the newly created fullScreenDiv to avoid global selectors
    const $imageWrapper = fullScreenDiv.find("#image_wrapper");
    const $img = fullScreenDiv.find("#fullscreenImg");

    // Zoom & Pan variables
    let currentZoom = 1;
    const zoomStep = 0.1;
    const maxZoom = 5;
    const minZoom = 0.1;

    let isDragging = false;
    let startX, startY;
    let currentTranslateX = 0;
    let currentTranslateY = 0;
    let dragMoved = false;

    // Função para aplicar transformações
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

    // Iniciar drag
    $imageWrapper.on("mousedown.fullscreen", function (e) {
      if (e.button === 0 && !e.ctrlKey) {
        isDragging = true;
        dragMoved = false;
        startX = e.clientX - currentTranslateX;
        startY = e.clientY - currentTranslateY;
        $imageWrapper.css("cursor", "grabbing").css("transition", "none");
      }
    });

    // Use namespaced document handlers so we can remove them when closing
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

    // Fechar modal clicando no fundo - limpar handlers namespaced
    fullScreenDiv.on("click", function (e) {
      if (e.target.id === "fullscreenImgDiv") {
        $(document).off(".fullscreen");
        $(this).remove();
      }
    });

    // Ensure handlers are cleaned up if the element is removed programmatically
    fullScreenDiv.on("remove", function () {
      $(document).off(".fullscreen");
    });

    applyTransforms();
  });
$("#modalPreviewImg").on("keydown", function (event) {
  if (["Enter", " "].includes(event.key)) {
    event.preventDefault();
    $(this).trigger("click");
  }
});

// Fechar o modal (novo botão + clique no overlay)
function closeRenderDetails() {
  detailRequestSequence++;
  $("#myModal").removeClass("is-open");
  renderReturnFocus?.focus({ preventScroll: true });
}
$("#closeModal").on("click", closeRenderDetails);

$("#myModal").on("click", function (e) {
  if (e.target === this) {
    closeRenderDetails();
  }
});

$("#aprovarRender").click(function () {
  const idrender_alta = $("#modal_idrender").text();

  $.post(
    "ajax.php",
    {
      action: "updateRender",
      idrender_alta: idrender_alta,
      status: "Aprovado",
    },
    function (response) {
      console.log("Resposta updateRender:", response);
      if (response.status === "sucesso") {
        // Atualiza os renders
        loadRenders();

        Toastify({
          text: "Render aprovado com sucesso!",
          duration: 3000,
          gravity: "top",
          position: "right",
          backgroundColor: "#4caf50", // verde
        }).showToast();

        // Abre o modal POS
        $("#modalPOS").addClass("is-open");
        $("#pos_render_id").val(idrender_alta);

        // NÃO fechamos o modal principal
      } else {
        Toastify({
          text: "Erro ao atualizar Render!",
          duration: 3000,
          gravity: "top",
          position: "right",
          backgroundColor: "#f44336", // vermelho
        }).showToast();
      }
    },
    "json",
  ).fail(function (xhr, status, error) {
    console.error("AJAX error:", error, xhr.responseText);
    Toastify({
      text: "Erro de comunicação com o servidor!",
      duration: 3000,
      gravity: "top",
      position: "right",
      backgroundColor: "#f44336",
    }).showToast();
  });
});

function abrirPosAposAprovarRender(idrender_alta) {
  loadRenders();

  Toastify({
    text: "Render aprovado com sucesso!",
    duration: 3000,
    gravity: "top",
    position: "right",
    backgroundColor: "#4caf50",
  }).showToast();

  $("#modalPOS, #myModal").removeClass("is-open");
}

function mostrarModalAprovacaoInterna() {
  if (!window.FlowAlert) {
    Toastify({
      text: "A aprova\u00e7\u00e3o interna precisa ser registrada antes de seguir.",
      duration: 4000,
      gravity: "top",
      position: "right",
      backgroundColor: "#f44336",
    }).showToast();
    return;
  }

  FlowAlert.choose({
    type: "warning",
    title: "A altera\u00e7\u00e3o foi aprovada?",
    message:
      "A altera\u00e7\u00e3o desta imagem n\u00e3o possui aprova\u00e7\u00e3o interna registrada.",
    choices: [
      { label: "Sim, presencialmente", value: "presencial" },
      { label: "Sim, via WhatsApp", value: "whatsapp" },
    ],
    cancelText: "N\u00e3o",
  }).then(function (result) {
    if (
      result.isConfirmed &&
      ["presencial", "whatsapp"].includes(result.value)
    ) {
      aprovarRender(result.value);
      return;
    }

    Toastify({
      text: "Aprova\u00e7\u00e3o do Render cancelada.",
      duration: 3000,
      gravity: "top",
      position: "right",
      backgroundColor: "#607d8b",
    }).showToast();
  });
}

function aprovarRender(approvalOrigin) {
  const idrender_alta = $("#modal_idrender").text();
  const isPlantaHumanizada =
    String($("#myModal").data("tipo-imagem") || "")
      .trim()
      .toLowerCase() === "planta humanizada";
  const statusImagem = String($("#modal_status_id").text() || "")
    .trim()
    .toLowerCase();
  if (!isPlantaHumanizada && statusImagem !== "p00" && !approvalOrigin) {
    if (window.RenderReferenceReview)
      window.RenderReferenceReview.open(idrender_alta);
    return;
  }
  if (!isPlantaHumanizada && statusImagem !== "p00") {
    if (window.RenderReferenceReview)
      window.RenderReferenceReview.open(idrender_alta, approvalOrigin);
    return;
  }
  const payload = {
    action: "updateRender",
    idrender_alta: idrender_alta,
    status: "Aprovado",
  };

  if (approvalOrigin) {
    payload.approval_origin = approvalOrigin;
  }

  $.post(
    "ajax.php",
    payload,
    function (response) {
      console.log("Resposta updateRender:", response);
      if (response.status === "sucesso") {
        abrirPosAposAprovarRender(idrender_alta);
        return;
      }

      if (response.status === "aprovacao_interna_pendente") {
        mostrarModalAprovacaoInterna();
        return;
      }

      Toastify({
        text: response.message || "Erro ao atualizar Render!",
        duration: 3000,
        gravity: "top",
        position: "right",
        backgroundColor: "#f44336",
      }).showToast();
    },
    "json",
  ).fail(function (xhr, status, error) {
    console.error("AJAX error:", error, xhr.responseText);
    Toastify({
      text: "Erro de comunicacao com o servidor!",
      duration: 3000,
      gravity: "top",
      position: "right",
      backgroundColor: "#f44336",
    }).showToast();
  });
}

function concluirRenderManualmente(justificativa) {
  const idrender_alta = Number($("#modal_idrender").text());
  if (!idrender_alta) return;
  const $button = $("#completeRenderManually");
  $button.prop("disabled", true);
  $.post(
    "ajax.php",
    {
      action: "completeRenderManually",
      idrender_alta: idrender_alta,
      justificativa: justificativa,
    },
    function (response) {
      if (response && response.status === "sucesso") {
        loadRenders();

        $("#myModal").removeClass("is-open");
        Toastify({
          text: response.message || "Render enviado para aprovação.",
          duration: 4000,
          gravity: "top",
          position: "right",
          backgroundColor: "#0e7490",
        }).showToast();
        return;
      }
      Toastify({
        text:
          (response && response.message) ||
          "Não foi possível concluir o render manualmente.",
        duration: 4500,
        gravity: "top",
        position: "right",
        backgroundColor: "#f44336",
      }).showToast();
    },
    "json",
  )
    .fail(function (xhr) {
      const message = xhr.responseJSON && xhr.responseJSON.message;
      Toastify({
        text: message || "Erro de comunicação com o servidor.",
        duration: 4500,
        gravity: "top",
        position: "right",
        backgroundColor: "#f44336",
      }).showToast();
    })
    .always(function () {
      $button.prop("disabled", false);
    });
}

$("#completeRenderManually")
  .off("click")
  .on("click", function () {
    if (!window.FlowAlert) return;
    FlowAlert.input({
      type: "warning",
      title: "Marcar render como feito manualmente?",
      message:
        "O job no Deadline será encerrado e o render seguirá para aprovação.",
      field: {
        type: "textarea",
        label: "Justificativa",
        placeholder:
          "Explique por que o render foi concluído fora do Deadline.",
        maxlength: 2000,
        rows: 4,
        required: true,
      },
      confirmText: "Confirmar conclusão manual",
      cancelText: "Cancelar",
      validate: function (value) {
        return String(value || "").trim()
          ? undefined
          : "Informe a justificativa para continuar.";
      },
    }).then(function (result) {
      if (result.isConfirmed) {
        concluirRenderManualmente(String(result.value || "").trim());
      }
    });
  });

$("#aprovarRender")
  .off("click")
  .on("click", function () {
    aprovarRender();
  });

$("#openRenderReferences").on("click", function () {
  const idrender = Number($("#modal_idrender").text());
  if (idrender && window.RenderReferenceReview)
    window.RenderReferenceReview.open(idrender, null, true);
});

// Fechar modal POS
$("#fecharPOS").click(function () {
  $("#modalPOS").removeClass("is-open");
  $("#pos_caminho").val("");
  $("#pos_referencias").val("");
});

// Enviar dados do POS
$("#enviarPOS").click(function () {
  const render_id = $("#pos_render_id").val();
  const refs = $("#pos_caminho").val();
  const obs = $("#pos_referencias").val();

  if (!render_id) {
    Toastify({
      text: "ID do render não definido!",
      duration: 3000,
      gravity: "top",
      position: "right",
      backgroundColor: "#f44336", // vermelho
    }).showToast();
    return;
  }

  $.post(
    "ajax.php",
    {
      action: "updatePOS",
      render_id: render_id,
      refs: refs,
      obs: obs,
    },
    function (response) {
      if (response.status === "sucesso") {
        Toastify({
          text: "Pós-produção atualizada com sucesso!",
          duration: 3000,
          gravity: "top",
          position: "right",
          backgroundColor: "#4caf50", // verde
        }).showToast();

        $("#modalPOS").removeClass("is-open");
        $("#pos_caminho").val("");
        $("#pos_referencias").val("");
        $("#myModal").removeClass("is-open");
      } else {
        Toastify({
          text: "Erro ao atualizar pós-produção!",
          duration: 3000,
          gravity: "top",
          position: "right",
          backgroundColor: "#f44336", // vermelho
        }).showToast();
      }
    },
    "json",
  ).fail(function (xhr, status, error) {
    console.error("AJAX error:", error, xhr.responseText);
    Toastify({
      text: "Erro de comunicação com o servidor!",
      duration: 3000,
      gravity: "top",
      position: "right",
      backgroundColor: "#f44336",
    }).showToast();
  });
});

function enviarParaPos(approvalOrigin) {
  const form = new FormData();
  form.append("action", "approveToPos");
  form.append("idrender_alta", $("#pos_render_id").val());
  form.append("refs", $("#pos_caminho").val());
  form.append("obs", $("#pos_referencias").val());
  if (approvalOrigin) form.append("approval_origin", approvalOrigin);
  Array.from($("#pos_references_files")[0].files || []).forEach((file) =>
    form.append("references[]", file),
  );
  $.ajax({
    url: "ajax.php",
    method: "POST",
    data: form,
    processData: false,
    contentType: false,
    dataType: "json",
    success: function (response) {
      if (response.status === "sucesso") {
        if (response.skip_pos_producao) {
          abrirPosAposAprovarRender($("#pos_render_id").val());
          Toastify({
            text: "Planta Humanizada aprovada sem envio para P\u00f3s-Produ\u00e7\u00e3o.",
            duration: 4000,
            gravity: "top",
            position: "right",
            backgroundColor: "#0e7490",
          }).showToast();
          return;
        }
        abrirPosAposAprovarRender($("#pos_render_id").val());
        $("#pos_caminho, #pos_referencias, #pos_references_files").val("");
        $("#posReferencesPreview").empty();
      } else if (response.status === "aprovacao_interna_pendente") {
        mostrarModalAprovacaoInterna();
      } else {
        Toastify({
          text: response.message || "Erro ao enviar para Pós-Produção!",
          duration: 4000,
          gravity: "top",
          position: "right",
          backgroundColor: "#f44336",
        }).showToast();
      }
    },
    error: function () {
      Toastify({
        text: "Erro de comunicação com o servidor!",
        duration: 3500,
        gravity: "top",
        position: "right",
        backgroundColor: "#f44336",
      }).showToast();
    },
  });
}

$("#enviarPOS")
  .off("click")
  .on("click", function () {
    if (!$("#pos_render_id").val()) return;
    enviarParaPos();
  });

$("#pos_references_files").on("change", function () {
  const preview = $("#posReferencesPreview").empty();
  Array.from(this.files || []).forEach(function (file) {
    if (!file.type.startsWith("image/")) return;
    const url = URL.createObjectURL(file);
    $("<img>", { src: url, alt: file.name })
      .on("load", function () {
        URL.revokeObjectURL(url);
      })
      .appendTo(preview);
  });
});

$("#reprovarRender").click(function () {
  const idrender_alta = $("#modal_idrender").text();
  const targetStatus = $(this).data("target-status") || "Reprovado";
  $.post(
    "ajax.php",
    {
      action: "updateRender",
      idrender_alta: idrender_alta,
      status: targetStatus,
    },
    function (response) {
      if (response.status === "sucesso") {
        loadRenders();

        $("#myModal").removeClass("is-open");
        Toastify({
          text:
            response.message ||
            "Render reprovado. A remoção no Deadline está pendente.",
          duration: 3000,
          gravity: "top",
          position: "right",
          backgroundColor: "#4ca8afff", // verde
        }).showToast();
      } else {
        Toastify({
          text: "Erro ao reprovar Render!",
          duration: 3000,
          gravity: "top",
          position: "right",
          backgroundColor: "#f44336", // vermelho
        }).showToast();
      }
    },
    "json",
  ).fail(function (xhr, status, error) {
    console.error("AJAX error:", error, xhr.responseText);
    Toastify({
      text: "Erro de comunicação com o servidor!",
      duration: 3000,
      gravity: "top",
      position: "right",
      backgroundColor: "#f44336",
    }).showToast();
  });
});

function fecharMenuMaisAcoes() {
  const $container = $(".modal-more-actions");
  $container.removeClass("is-open");
  $("#modalMoreActions").attr("aria-expanded", "false");
  $("#modalMoreMenu").attr("aria-hidden", "true");
}

$("#modalMoreActions")
  .off("click")
  .on("click", function (event) {
    event.preventDefault();
    event.stopPropagation();
    const $container = $(this).closest(".modal-more-actions");
    const isOpen = $container.toggleClass("is-open").hasClass("is-open");
    $(this).attr("aria-expanded", String(isOpen));
    $("#modalMoreMenu").attr("aria-hidden", String(!isOpen));
  });

$(document)
  .off("click.renderMoreActions")
  .on("click.renderMoreActions", function (event) {
    if (!$(event.target).closest(".modal-more-actions").length) {
      fecharMenuMaisAcoes();
    }
  });

$("#closeModal")
  .add("#myModal")
  .off("click.closeMoreActions")
  .on("click.closeMoreActions", function () {
    fecharMenuMaisAcoes();
  });

// Excluir o render
$("#deleteRender")
  .off("click")
  .on("click", function (e) {
    e.preventDefault(); // Evita submit do formulário se for button type="submit"
    const idrender_alta = $("#modal_idrender").text();
    fecharMenuMaisAcoes();
    $.ajax({
      url: "ajax.php",
      method: "POST",
      data: {
        action: "deleteRender",
        idrender_alta: idrender_alta,
      },
      dataType: "json",
      success: function (response) {
        if (response.status == "sucesso") {
          loadRenders();

          $("#myModal").removeClass("is-open");

          Toastify({
            text: response.message || "Render arquivado com segurança.",
            duration: 3000,
            gravity: "top",
            position: "right",
            backgroundColor: "#f15e1aff", // verde
          }).showToast();
        } else {
          Toastify({
            text: response.message || "Erro ao arquivar o render.",
            duration: 3000,
            gravity: "top",
            position: "right",
            backgroundColor: "#f44336",
          }).showToast();
        }
      },
    });
  });

// Carregar os renders quando a página for carregada
$(document).ready(function () {
  document
    .querySelectorAll(
      "#renderLoading canvas, #farmLoading canvas, #timelineLoading canvas, #btnLoadMore canvas",
    )
    .forEach((canvas) => window.thinkingOrbs?.(canvas, { theme: "dark" }));
  loadRenders(1);

  $("#btnLoadMore").on("click", function () {
    loadRenders(currentPage + 1);
  });

  // ── Context menu: trocar responsável ──────────────────────────────
  let ctxRenderId = null;
  const $ctxMenu = $("#ctxMenu");

  // Pré-carregar colaboradores uma vez
  $.ajax({
    url: "ajax.php",
    method: "GET",
    data: { action: "getColaboradores" },
    dataType: "json",
    success: function (res) {
      if (res.status === "sucesso") {
        const $sel = $("#ctxResponsavelSelect");
        $sel.empty();
        res.colaboradores.forEach(function (c) {
          $sel.append(
            `<option value="${c.idcolaborador}">${c.nome_colaborador}</option>`,
          );
        });
      }
    },
  });

  // Abrir menu no botão direito do card
  function showResponsibleMenu(card, clientX, clientY) {
    ctxRenderId = $(card).data("id");

    // Posicionar o menu perto do cursor, ajustando para não sair da tela
    const menuW = 230,
      menuH = 130;
    let x = clientX,
      y = clientY;
    if (x + menuW > window.innerWidth) x = window.innerWidth - menuW - 8;
    if (y + menuH > window.innerHeight) y = window.innerHeight - menuH - 8;

    $ctxMenu.css({ top: y, left: x }).addClass("is-open");
  }
  $("#renderGrid").on("contextmenu", ".render-card", function (event) {
    event.preventDefault();
    showResponsibleMenu(this, event.clientX, event.clientY);
  });
  $("#renderGrid").on("click", ".card-menu", function (event) {
    event.stopPropagation();
    const rect = this.getBoundingClientRect();
    showResponsibleMenu(
      this.closest(".render-card"),
      rect.left,
      rect.bottom + 4,
    );
    document.getElementById("ctxResponsavelSelect").focus();
  });

  // Salvar novo responsável
  $("#ctxSalvar").on("click", function () {
    if (!ctxRenderId) return;
    const responsavel_id = $("#ctxResponsavelSelect").val();

    $.post(
      "ajax.php",
      {
        action: "updateResponsavel",
        idrender_alta: ctxRenderId,
        responsavel_id: responsavel_id,
      },
      function (res) {
        if (res.status === "sucesso") {
          $ctxMenu.removeClass("is-open");
          loadRenders(1);
          Toastify({
            text: "Responsável atualizado com sucesso!",
            duration: 3000,
            gravity: "top",
            position: "right",
            backgroundColor: "#4caf50",
          }).showToast();
        } else {
          Toastify({
            text: "Erro ao atualizar responsável!",
            duration: 3000,
            gravity: "top",
            position: "right",
            backgroundColor: "#f44336",
          }).showToast();
        }
      },
      "json",
    );
  });

  // Fechar ao clicar fora
  $(document).on("click", function (e) {
    if (!$(e.target).closest("#ctxMenu").length) {
      $ctxMenu.removeClass("is-open");
    }
  });

  // Fechar ao pressionar Escape
  $(document).on("keydown.ctx", function (e) {
    if (e.key === "Escape") $ctxMenu.removeClass("is-open");
  });
});

let renderRealtimeReloadTimer = null;
window.addEventListener("improov:renderUpdated", function (event) {
  const detail = event.detail || {};
  if (renderRealtimeReloadTimer) clearTimeout(renderRealtimeReloadTimer);
  renderRealtimeReloadTimer = setTimeout(function () {
    loadRenders(1);

    const openedId = Number($("#modal_idrender").text());
    if (
      $("#myModal").hasClass("is-open") &&
      openedId &&
      Number(detail.render_id) === openedId
    )
      editRender(openedId);
  }, 180);
});

$("#filter-toggle-btn").on("click", function () {
  const expanded = this.getAttribute("aria-expanded") === "true";
  $("#filters").toggleClass("open", !expanded);
  this.setAttribute("aria-expanded", String(!expanded));
});
