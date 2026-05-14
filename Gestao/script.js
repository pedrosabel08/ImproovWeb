const gestaoConfig = window.GESTAO_CONFIG || {};

const gestaoState = {
  period: 7,
  criticalOnly: false,
  includeHold: true,
  onlyOverloaded: false,
  search: "",
  data: null,
  loading: false,
};

const gestaoEls = {};

document.addEventListener("DOMContentLoaded", () => {
  cacheGestaoElements();

  if (!gestaoEls.kpiGrid) {
    return;
  }

  gestaoState.period = Number(gestaoEls.periodFilter?.value || 7);
  gestaoState.includeHold = Boolean(gestaoEls.includeHold?.checked);

  bindGestaoEvents();
  renderLoadingState();
  loadDashboard();
});

function cacheGestaoElements() {
  gestaoEls.kpiGrid = document.getElementById("kpiGrid");
  gestaoEls.riskRadarPanel = document.getElementById("riskRadarPanel");
  gestaoEls.bottlenecksPanel = document.getElementById("bottlenecksPanel");
  gestaoEls.capacityPanel = document.getElementById("capacityPanel");
  gestaoEls.schedulePanel = document.getElementById("schedulePanel");
  gestaoEls.activitiesPanel = document.getElementById("activitiesPanel");
  gestaoEls.statusStrip = document.getElementById("status-operacional");
  gestaoEls.lastUpdated = document.getElementById("lastUpdated");

  gestaoEls.periodFilter = document.getElementById("periodFilter");
  gestaoEls.filtersButton = document.getElementById("filtersButton");
  gestaoEls.filtersDrawer = document.getElementById("filtros-operacionais");
  gestaoEls.criticalOnly = document.getElementById("criticalOnly");
  gestaoEls.includeHold = document.getElementById("includeHold");
  gestaoEls.onlyOverloaded = document.getElementById("onlyOverloaded");
  gestaoEls.projectSearch = document.getElementById("projectSearch");

  gestaoEls.sidebar = document.getElementById("gestaoSidebar");
  gestaoEls.sidebarToggle = document.getElementById("sidebarToggle");
  gestaoEls.sidebarOverlay = document.getElementById("sidebarOverlay");

  gestaoEls.entregaModal = document.getElementById("entregaModal");
  gestaoEls.openEntregaModal = document.getElementById("openEntregaModal");
  gestaoEls.closeEntregaModal = document.getElementById("closeEntregaModal");
  gestaoEls.entregaForm = document.getElementById("formAdicionarEntrega");
  gestaoEls.obraSelect = document.getElementById("obra_id");
  gestaoEls.statusSelect = document.getElementById("status_id");
  gestaoEls.imagensContainer = document.getElementById("imagens_container");

  let weekPopover = document.getElementById("weekPopover");
  if (!weekPopover) {
    weekPopover = document.createElement("div");
    weekPopover.id = "weekPopover";
    weekPopover.className = "week-popover";
    weekPopover.hidden = true;
    document.body.appendChild(weekPopover);
  }
  gestaoEls.weekPopover = weekPopover;

  let kpiPopover = document.getElementById("kpiPopover");
  if (!kpiPopover) {
    kpiPopover = document.createElement("div");
    kpiPopover.id = "kpiPopover";
    kpiPopover.className = "week-popover";
    kpiPopover.hidden = true;
    document.body.appendChild(kpiPopover);
  }
  gestaoEls.kpiPopover = kpiPopover;
  gestaoEls._openKpiKey = null;
}

function bindGestaoEvents() {
  gestaoEls.periodFilter?.addEventListener("change", () => {
    gestaoState.period = Number(gestaoEls.periodFilter.value || 7);
    loadDashboard();
  });

  gestaoEls.filtersButton?.addEventListener("click", () => {
    toggleFiltersDrawer();
  });

  gestaoEls.criticalOnly?.addEventListener("change", () => {
    gestaoState.criticalOnly = gestaoEls.criticalOnly.checked;
    renderDashboard();
  });

  gestaoEls.includeHold?.addEventListener("change", () => {
    gestaoState.includeHold = gestaoEls.includeHold.checked;
    renderDashboard();
  });

  gestaoEls.onlyOverloaded?.addEventListener("change", () => {
    gestaoState.onlyOverloaded = gestaoEls.onlyOverloaded.checked;
    renderDashboard();
  });

  gestaoEls.projectSearch?.addEventListener("input", (event) => {
    gestaoState.search = event.target.value.trim().toLowerCase();
    renderDashboard();
  });

  gestaoEls.sidebarToggle?.addEventListener("click", openSidebar);
  gestaoEls.sidebarOverlay?.addEventListener("click", closeSidebar);

  document.querySelectorAll(".sidebar-link").forEach((link) => {
    link.addEventListener("click", () => {
      if (window.innerWidth <= 980) {
        closeSidebar();
      }
    });
  });

  gestaoEls.openEntregaModal?.addEventListener("click", openDeliveryModal);
  gestaoEls.closeEntregaModal?.addEventListener("click", closeDeliveryModal);

  document.querySelectorAll("[data-close-entrega]").forEach((element) => {
    element.addEventListener("click", closeDeliveryModal);
  });

  gestaoEls.obraSelect?.addEventListener("change", loadImagesForDelivery);
  gestaoEls.statusSelect?.addEventListener("change", loadImagesForDelivery);
  gestaoEls.entregaForm?.addEventListener("submit", submitDeliveryForm);

  document.addEventListener("click", handleOutsideInteractions);
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeFiltersDrawer();
      closeSidebar();
      closeDeliveryModal();
      closeWeekPopover();
      closeKpiPopover();
    }
  });
}

function handleOutsideInteractions(event) {
  if (
    !gestaoEls.filtersDrawer?.hidden &&
    !gestaoEls.filtersDrawer.contains(event.target) &&
    !gestaoEls.filtersButton?.contains(event.target)
  ) {
    closeFiltersDrawer();
  }

  if (
    gestaoEls.weekPopover &&
    !gestaoEls.weekPopover.hidden &&
    !gestaoEls.weekPopover.contains(event.target) &&
    !event.target.closest(".day-card")
  ) {
    closeWeekPopover();
  }

  if (
    gestaoEls.kpiPopover &&
    !gestaoEls.kpiPopover.hidden &&
    !gestaoEls.kpiPopover.contains(event.target) &&
    !event.target.closest(".kpi-card")
  ) {
    closeKpiPopover();
  }
}

async function loadDashboard() {
  gestaoState.loading = true;
  renderLoadingState();

  try {
    const url = new URL(
      gestaoConfig.dashboardUrl || "getDashboardData.php",
      window.location.href,
    );
    url.searchParams.set("period", String(gestaoState.period));

    const response = await fetch(url.toString(), {
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const payload = await response.json();
    if (payload.error) {
      throw new Error(
        payload.details || payload.message || "Falha ao carregar a dashboard.",
      );
    }

    gestaoState.data = payload;
    renderDashboard();
  } catch (error) {
    console.error(error);
    renderErrorState(error.message || "Não foi possível carregar os dados.");
    showToast("Não foi possível carregar a dashboard.", "error");
  } finally {
    gestaoState.loading = false;
  }
}

function renderLoadingState() {
  gestaoEls.kpiGrid.innerHTML = `<div class="loading-grid loading-grid--kpis">${Array.from({ length: 9 }, () => '<div class="skeleton-card"></div>').join("")}</div>`;

  const panelSkeleton = `
        <div class="loading-grid loading-grid--panel">
            <div class="skeleton-line"></div>
            <div class="skeleton-line"></div>
            <div class="skeleton-line"></div>
        </div>
    `;

  gestaoEls.riskRadarPanel.innerHTML = panelSkeleton;
  gestaoEls.bottlenecksPanel.innerHTML = panelSkeleton;
  gestaoEls.capacityPanel.innerHTML = panelSkeleton;
  gestaoEls.schedulePanel.innerHTML = panelSkeleton;
  gestaoEls.activitiesPanel.innerHTML = panelSkeleton;
  gestaoEls.statusStrip.innerHTML = `<div class="loading-grid loading-grid--kpis">${Array.from({ length: 7 }, () => '<div class="skeleton-card"></div>').join("")}</div>`;

  if (gestaoEls.lastUpdated) {
    gestaoEls.lastUpdated.textContent = "Atualizando dados";
  }
}

function renderErrorState(message) {
  const markup = renderEmptyState(message);
  gestaoEls.kpiGrid.innerHTML = markup;
  gestaoEls.riskRadarPanel.innerHTML = markup;
  gestaoEls.bottlenecksPanel.innerHTML = markup;
  gestaoEls.capacityPanel.innerHTML = markup;
  gestaoEls.schedulePanel.innerHTML = markup;
  gestaoEls.activitiesPanel.innerHTML = markup;
  gestaoEls.statusStrip.innerHTML = markup;

  if (gestaoEls.lastUpdated) {
    gestaoEls.lastUpdated.textContent = "Falha ao atualizar";
  }
}

function renderDashboard() {
  if (!gestaoState.data) {
    return;
  }

  if (gestaoEls.lastUpdated) {
    gestaoEls.lastUpdated.textContent = `Atualizado às ${formatTime(gestaoState.data.updated_at)}`;
  }

  renderKpis(gestaoState.data.kpis || []);

  const filteredData = getFilteredData();
  renderRiskRadar(filteredData.riskRadar);
  renderBottlenecks(filteredData.bottlenecks);
  renderCapacity(filteredData.capacity);
  renderWeekSchedule(filteredData.weekSchedule);
  renderActivities(filteredData.activities);
  renderFooterStatuses(gestaoState.data.footer_statuses || []);
}

function getFilteredData() {
  const search = gestaoState.search;
  const matches = (...values) => {
    if (!search) {
      return true;
    }

    return values.some((value) =>
      String(value || "")
        .toLowerCase()
        .includes(search),
    );
  };

  const riskRadar = (gestaoState.data.risk_radar || []).filter((row) => {
    const isHold = String(row.reason || "")
      .toLowerCase()
      .startsWith("hold:");
    return (
      matches(row.project, row.delivery, row.reason) &&
      (gestaoState.includeHold || !isHold) &&
      (!gestaoState.criticalOnly || row.risk === "Alto")
    );
  });

  const bottlenecks = (gestaoState.data.bottlenecks || []).filter((row) => {
    return (
      matches(row.function) &&
      (!gestaoState.criticalOnly || row.severity === "critical")
    );
  });

  const capacity = (gestaoState.data.capacity || []).filter((row) => {
    const overloaded = row.status === "Sobrecarregado";
    return (
      matches(row.name, row.function, row.status) &&
      (!gestaoState.onlyOverloaded || overloaded) &&
      (!gestaoState.criticalOnly || row.status !== "Ocioso")
    );
  });

  const weekSchedule = (gestaoState.data.week_schedule || []).filter((row) => {
    return (
      matches(row.label, row.date_label, row.priority) &&
      (!gestaoState.criticalOnly || row.priority !== "Leve")
    );
  });

  const activities = (gestaoState.data.activities || []).filter((row) => {
    return (
      matches(row.project, row.label, row.description) &&
      (!gestaoState.criticalOnly ||
        ["warning", "critical"].includes(row.tone) ||
        row.label === "Entrega realizada")
    );
  });

  return {
    riskRadar,
    bottlenecks,
    capacity,
    weekSchedule,
    activities,
  };
}

function renderKpis(kpis) {
  if (!kpis.length) {
    gestaoEls.kpiGrid.innerHTML = renderEmptyState(
      "Sem indicadores para o período selecionado.",
    );
    return;
  }

  gestaoEls.kpiGrid.innerHTML = kpis
    .map((kpi) => {
      const hasDetail = (kpi.detail || []).length > 0;
      return `
            <article class="kpi-card${hasDetail ? " kpi-card--clickable" : ""}" data-tone="${escapeHtml(kpi.tone || "info")}" data-kpi-key="${escapeHtml(kpi.key || "")}">
                <div class="kpi-head">
                    <p class="kpi-label">${escapeHtml(kpi.label)}</p>
                    <span class="kpi-icon"><i class="${escapeHtml(safeIcon(kpi.icon))}"></i></span>
                </div>
                <div class="kpi-value">${formatNumber(kpi.value)}</div>
                <div class="kpi-meta">
                    <span class="kpi-trend"><i class="fa-solid fa-arrow-up"></i>${escapeHtml(String(kpi.trend))} ${escapeHtml(kpi.trend_label || "")}</span>
                    <span class="kpi-micro">${escapeHtml(kpi.micro || "")}</span>
                </div>
                ${hasDetail ? `<span class="kpi-expand-hint"><i class="fa-solid fa-chevron-down"></i></span>` : ""}
            </article>
        `;
    })
    .join("");

  gestaoEls.kpiGrid.querySelectorAll(".kpi-card--clickable").forEach((card) => {
    const key = card.dataset.kpiKey;
    const kpiData = kpis.find((k) => k.key === key);
    card.addEventListener("click", () => {
      if (gestaoEls._openKpiKey === key && !gestaoEls.kpiPopover?.hidden) {
        closeKpiPopover();
      } else {
        openKpiPopover(kpiData, card);
      }
    });
  });
}

function renderRiskRadar(rows) {
  if (!rows.length) {
    gestaoEls.riskRadarPanel.innerHTML = renderEmptyState(
      "Nenhum risco dentro dos filtros atuais.",
    );
    return;
  }

  gestaoEls.riskRadarPanel.innerHTML = `
        <div class="risk-table">
            <div class="table-head">
                <span>Entrega/Projeto</span>
                <span>Risco</span>
                <span>Motivo</span>
            </div>
            <div class="table-body">
            ${rows
              .map(
                (row) => `
                <article class="table-row table-row--risk">
                    <div class="cell cell--project" data-label="Entrega/Projeto">
                        <strong>${escapeHtml(row.project)}</strong>
                        <span>${escapeHtml(row.delivery)}${row.due_label ? ` · ${escapeHtml(row.due_label)}` : ""}</span>
                    </div>
                    <div class="cell" data-label="Risco">
                        <span class="badge badge--${escapeHtml(row.risk_tone || "info")}">${escapeHtml(row.risk)}</span>
                    </div>
                    <div class="cell" data-label="Motivo">
                        <span>${escapeHtml(row.reason)}</span>
                    </div>
                </article>
            `,
              )
              .join("")}
              </div>
        </div>
    `;
}

function renderBottlenecks(rows) {
  if (!rows.length) {
    gestaoEls.bottlenecksPanel.innerHTML = renderEmptyState(
      "Nenhum gargalo encontrado para os filtros atuais.",
    );
    return;
  }

  gestaoEls.bottlenecksPanel.innerHTML = `
        <div class="bottleneck-table">
            <div class="table-head">
                <span>Função</span>
            <span title="A iniciar" aria-label="A iniciar" style="color: var(--text-muted);"><i class="fa-regular fa-clock" aria-hidden="true"></i></span>
            <span title="Andamento" aria-label="Andamento" style="color: var(--warning);"><i class="fa-solid fa-hourglass-start" aria-hidden="true"></i></span>
            <span title="Aprovação" aria-label="Aprovação" style="color: var(--info);"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
            <span title="Atrasadas" aria-label="Atrasadas" style="color: var(--critical);"><i class="fa-solid fa-calendar-xmark" aria-hidden="true"></i></span>
            <span title="Tempo médio" aria-label="Tempo médio" style="color: var(--text-muted);"><i class="fa-solid fa-stopwatch" aria-hidden="true"></i></span>
            </div>
              <div class="table-body">
            ${rows
              .map((row) => {
                const tone = severityTone(row.severity);
                return `
                    <article class="table-row" data-severity="${escapeHtml(row.severity)}" style="--tone-color: ${tone};">
                        <div class="cell" data-label="Função">
                            <div class="metric-stack">
                                <strong>${escapeHtml(row.function)}</strong>
                                <div class="row-meter"><span style="width:${Math.min(Number(row.load_percent || 0), 100)}%"></span></div>
                            </div>
                        </div>
                        <div class="cell cell--metric" data-label="A iniciar">${formatNumber(row.to_start)}</div>
                        <div class="cell cell--metric" data-label="Andamento">${formatNumber(row.in_progress)}</div>
                        <div class="cell cell--metric" data-label="Aprovação">${formatNumber(row.approval)}</div>
                        <div class="cell cell--metric" data-label="Atrasadas">${formatNumber(row.overdue)}</div>
                        <div class="cell cell--numeric" data-label="Tempo médio"><strong>${escapeHtml(row.avg_days)} dias</strong></div>
                    </article>
                `;
              })
              .join("")}
              </div>
        </div>
    `;
}

function renderCapacity(rows) {
  if (!rows.length) {
    gestaoEls.capacityPanel.innerHTML = renderEmptyState(
      "Nenhuma capacidade encontrada com os filtros atuais.",
    );
    return;
  }

  gestaoEls.capacityPanel.innerHTML = `
        <div class="capacity-table">
            <div class="table-head">
                <span>Colaborador</span>
                <span>Função</span>
                <span>Capacidade</span>
                <span>Status</span>
            </div>
            <div class="table-body">
            ${rows
              .map((row) => {
                const tone = statusTone(row.status_tone);
                return `
                    <article class="table-row" style="--tone-color: ${tone};">
                        <div class="cell" data-label="Colaborador">
                            <div class="capacity-person">
                                <div class="capacity-avatar">
                                    ${row.avatar ? `<img src="${escapeHtml(row.avatar)}" alt="${escapeHtml(row.name)}">` : `<span>${escapeHtml(getInitial(row.name))}</span>`}
                                </div>
                                <div>
                                    <strong>${escapeHtml(row.name)}</strong>
                                </div>
                            </div>
                        </div>
                        <div class="cell" data-label="Função"><strong>${escapeHtml(row.function)}</strong></div>
                        <div class="cell" data-label="Capacidade">
                            <strong>${formatNumber(row.capacity)}%</strong>
                            <div class="capacity-bar"><span style="width:${Math.min(Number(row.capacity || 0), 100)}%"></span></div>
                        </div>
                        <div class="cell" data-label="Status"><span class="capacity-status">${escapeHtml(row.status)}</span></div>
                    </article>
                `;
              })
              .join("")}
              </div>
        </div>
    `;
}

function renderWeekSchedule(rows) {
  if (!rows.length) {
    gestaoEls.schedulePanel.innerHTML = renderEmptyState(
      "Sem entregas ou prazos visíveis nesta janela.",
    );
    return;
  }

  const maxMetric = Math.max(
    1,
    ...rows.flatMap((row) => [
      Number(row.deliveries || 0),
      ...(row.tasks || []).map((t) => Number(t.count || 0)),
    ]),
  );

  gestaoEls.schedulePanel.innerHTML = `
        <div class="day-grid">
            ${rows
              .map(
                (row, i) => `
                <article class="day-card" data-tone="${escapeHtml(row.priority_tone || "info")}"${i === 0 ? ' data-today="true"' : ""}>
                    <div class="day-head">
                        <span class="day-name">${escapeHtml(row.label)}</span>
                        <span class="badge badge--${escapeHtml(row.priority_tone || "info")}">${escapeHtml(row.priority)}</span>
                    </div>
                    <span class="day-date-lbl">${escapeHtml(row.date_label)}</span>
                    <div class="day-count-row">
                        <span class="day-num">${formatNumber(row.deliveries)}</span>
                        <span class="day-num-label">entregas</span>
                    </div>
                    <div class="day-divider"></div>
                    <div class="day-metrics">
                        <div class="day-metric">
                            <span class="day-metric-dot day-metric-dot--deliveries"></span>
                            <span class="day-metric-label">Entregas</span>
                            <div class="day-metric-track"><div class="day-metric-fill day-metric-fill--deliveries" style="width:${((Number(row.deliveries || 0) / maxMetric) * 100).toFixed(0)}%"></div></div>
                            <span class="day-metric-count">${formatNumber(row.deliveries)}</span>
                        </div>
                        ${(row.tasks || [])
                          .map(
                            (t) => `
                            <div class="day-metric">
                                <span class="day-metric-dot day-metric-dot--renders"></span>
                                <span class="day-metric-label">${escapeHtml(t.function)}</span>
                                <div class="day-metric-track"><div class="day-metric-fill day-metric-fill--renders" style="width:${((Number(t.count || 0) / maxMetric) * 100).toFixed(0)}%"></div></div>
                                <span class="day-metric-count">${formatNumber(t.count)}</span>
                            </div>
                        `,
                          )
                          .join("")}
                    </div>
                </article>
            `,
              )
              .join("")}
        </div>
        <div class="day-legend">
            <span class="day-legend-item"><span class="day-legend-dot" style="background:var(--info)"></span>Tarefas</span>
            <span class="day-legend-item"><span class="day-legend-dot" style="background:var(--accent-alt)"></span>Entregas</span>
        </div>
    `;

  gestaoEls.schedulePanel.querySelectorAll(".day-card").forEach((card, i) => {
    const row = rows[i];
    card.style.cursor = "pointer";
    card.addEventListener("click", () => {
      const popover = gestaoEls.weekPopover;
      if (popover && !popover.hidden && popover.dataset.dayDate === row.date) {
        closeWeekPopover();
        return;
      }
      if (popover) {
        popover.dataset.dayDate = row.date;
      }
      openWeekPopover(row, card);
    });
  });
}

function renderActivities(rows) {
  if (!rows.length) {
    gestaoEls.activitiesPanel.innerHTML = renderEmptyState(
      "Nenhuma atividade recente corresponde aos filtros atuais.",
    );
    return;
  }

  gestaoEls.activitiesPanel.innerHTML = `
        <div class="activity-list">
            ${rows
              .map(
                (row) => `
                <article class="activity-item" data-tone="${escapeHtml(row.tone || "info")}">
                    <span class="activity-icon"><i class="${escapeHtml(safeIcon(row.icon))}"></i></span>
                    <div class="activity-copy">
                        <strong>${escapeHtml(row.project)}</strong>
                        <p>${escapeHtml(row.label)}</p>
                        <small>${escapeHtml(row.description)}</small>
                    </div>
                    <span class="activity-time">${escapeHtml(row.relative_time)}</span>
                </article>
            `,
              )
              .join("")}
        </div>
    `;
}

function renderFooterStatuses(rows) {
  if (!rows.length) {
    gestaoEls.statusStrip.innerHTML = renderEmptyState(
      "Sem atalhos operacionais disponíveis.",
    );
    return;
  }

  gestaoEls.statusStrip.innerHTML = rows
    .map(
      (row) => `
        <article class="status-card" data-tone="${escapeHtml(mapFooterTone(row.tone))}">
            <div class="status-top">
                <span class="status-icon"><i class="${escapeHtml(safeIcon(row.icon))}"></i></span>
                <strong>${escapeHtml(row.label)}</strong>
            </div>
            <div class="status-count">${formatNumber(row.count)}</div>
            <p>${escapeHtml(row.description)}</p>
        </article>
    `,
    )
    .join("");
}

function renderEmptyState(message) {
  return `<div class="empty-state">${escapeHtml(message)}</div>`;
}

function toggleFiltersDrawer(forceOpen) {
  const shouldOpen =
    typeof forceOpen === "boolean"
      ? forceOpen
      : Boolean(gestaoEls.filtersDrawer?.hidden);

  if (!gestaoEls.filtersDrawer || !gestaoEls.filtersButton) {
    return;
  }

  gestaoEls.filtersDrawer.hidden = !shouldOpen;
  gestaoEls.filtersButton.setAttribute("aria-expanded", String(shouldOpen));
}

function closeFiltersDrawer() {
  toggleFiltersDrawer(false);
}

function openSidebar() {
  gestaoEls.sidebar?.classList.add("is-open");
  gestaoEls.sidebarOverlay?.classList.add("is-visible");
}

function closeSidebar() {
  gestaoEls.sidebar?.classList.remove("is-open");
  gestaoEls.sidebarOverlay?.classList.remove("is-visible");
}

function openDeliveryModal() {
  if (!gestaoEls.entregaModal) {
    return;
  }

  gestaoEls.entregaModal.classList.add("is-open");
  gestaoEls.entregaModal.setAttribute("aria-hidden", "false");
  document.body.classList.add("modal-open");
  closeFiltersDrawer();
}

function closeDeliveryModal() {
  if (!gestaoEls.entregaModal) {
    return;
  }

  gestaoEls.entregaModal.classList.remove("is-open");
  gestaoEls.entregaModal.setAttribute("aria-hidden", "true");
  document.body.classList.remove("modal-open");
}

function openWeekPopover(detail, cardEl) {
  const popover = gestaoEls.weekPopover;
  if (!popover) {
    return;
  }

  const totalTasks = (detail.tasks_detail || []).reduce(
    (s, f) => s + (f.items || []).length,
    0,
  );

  const deliveriesHtml = (detail.deliveries_detail || []).length
    ? (detail.deliveries_detail || [])
        .map(
          (d) => `
          <div class="wpop-item">
            <span class="wpop-item-name">${escapeHtml(d.name)}</span>
            ${d.label ? `<span class="wpop-item-tag">${escapeHtml(d.label)}</span>` : ""}
          </div>`,
        )
        .join("")
    : `<p class="wpop-empty">Nenhuma entrega neste dia</p>`;

  const tasksHtml = (detail.tasks_detail || []).length
    ? (detail.tasks_detail || [])
        .map(
          (fn) => `
          <div class="wpop-fn-group">
            <div class="wpop-fn-header">
              <span>${escapeHtml(fn.function)}</span>
              <span class="wpop-fn-count">${formatNumber((fn.items || []).length)}</span>
            </div>
            ${(fn.items || [])
              .map(
                (item) => `
              <div class="wpop-item">
                <span class="wpop-item-name">${escapeHtml(item.imagem || "\u2014")}</span>
                <span class="wpop-item-person"><i class="fa-solid fa-user-tie" style="font-size:.6rem;opacity:.55;"></i> ${escapeHtml(item.responsavel || "Sem respons\u00e1vel")}</span>
              </div>`,
              )
              .join("")}
          </div>`,
        )
        .join("")
    : `<p class="wpop-empty">Sem tarefas neste dia</p>`;

  popover.innerHTML = `
    <div class="wpop-header">
      <span class="wpop-title">${escapeHtml(detail.label)} &middot; ${escapeHtml(detail.date_label)}</span>
      <button class="wpop-close" aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="wpop-body">
      <section class="wpop-section">
        <h4 class="wpop-section-title">
          <i class="fa-regular fa-calendar"></i> Entregas
          <span class="wpop-count">${escapeHtml(String((detail.deliveries_detail || []).length))}</span>
        </h4>
        ${deliveriesHtml}
      </section>
      <section class="wpop-section">
        <h4 class="wpop-section-title">
          <i class="fa-solid fa-list-check"></i> Tarefas
          <span class="wpop-count">${escapeHtml(String(totalTasks))}</span>
        </h4>
        ${tasksHtml}
      </section>
    </div>
  `;

  popover
    .querySelector(".wpop-close")
    ?.addEventListener("click", closeWeekPopover);

  positionWeekPopover(cardEl);
  popover.hidden = false;
}

function closeWeekPopover() {
  if (gestaoEls.weekPopover) {
    gestaoEls.weekPopover.hidden = true;
  }
}

function positionWeekPopover(cardEl) {
  const popover = gestaoEls.weekPopover;
  const rect = cardEl.getBoundingClientRect();
  const vpWidth = window.innerWidth;
  const vpHeight = window.innerHeight;
  const popoverWidth = 300;
  const popoverMaxHeight = Math.min(460, vpHeight - 24);

  let top = rect.top;
  let left = rect.right + 10;

  if (left + popoverWidth > vpWidth - 8) {
    left = rect.left - popoverWidth - 10;
  }

  if (left < 8) {
    left = Math.max(8, Math.min(rect.left, vpWidth - popoverWidth - 8));
    top = rect.bottom + 8;
  }

  if (top + popoverMaxHeight > vpHeight - 8) {
    top = vpHeight - popoverMaxHeight - 8;
  }

  top = Math.max(8, top);

  popover.style.top = `${top}px`;
  popover.style.left = `${left}px`;
  popover.style.width = `${popoverWidth}px`;
  popover.style.maxHeight = `${popoverMaxHeight}px`;
}

// ── KPI Popover ─────────────────────────────────────────────────────────────

function openKpiPopover(kpi, cardEl) {
  const popover = gestaoEls.kpiPopover;
  if (!popover || !kpi) return;

  gestaoEls._openKpiKey = kpi.key;
  const detail = kpi.detail || [];

  let bodyHtml = "";

  switch (kpi.key) {
    case "projetos_ativos": {
      bodyHtml = detail.length
        ? detail
            .map(
              (p) => `
              <div class="wpop-item">
                <span class="wpop-item-name">${escapeHtml(p.name)}</span>
              </div>`,
            )
            .join("")
        : `<p class="wpop-empty">Nenhum projeto ativo</p>`;
      break;
    }

    case "imagens_producao":
    case "imagens_hold": {
      bodyHtml = detail.length
        ? detail
            .map(
              (g) => `
              <div class="wpop-fn-group">
                <div class="wpop-fn-header">
                  <span>${escapeHtml(g.project)}</span>
                  <span class="wpop-fn-count">${formatNumber((g.images || []).length)}</span>
                </div>
                ${(g.images || [])
                  .map(
                    (img) => `
                  <div class="wpop-item">
                    <span class="wpop-item-name">${escapeHtml(img)}</span>
                  </div>`,
                  )
                  .join("")}
              </div>`,
            )
            .join("")
        : `<p class="wpop-empty">Nenhuma imagem</p>`;
      break;
    }

    case "entregas_proximas":
    case "entregas_atrasadas": {
      bodyHtml = detail.length
        ? detail
            .map(
              (d) => `
              <div class="wpop-item wpop-item--delivery">
                <span class="wpop-item-name">${escapeHtml(d.project)}</span>
                <span class="wpop-item-tag">${escapeHtml(d.status)}</span>
              </div>
              <div class="wpop-item-sub">${formatNumber(d.pending)} pendente${d.pending !== 1 ? "s" : ""}</div>`,
            )
            .join("")
        : `<p class="wpop-empty">Nenhuma entrega</p>`;
      break;
    }

    case "tarefas_atrasadas":
    case "sem_iniciar":
    case "imagens_aprovacao": {
      bodyHtml = detail.length
        ? detail
            .map(
              (fn) => `
              <div class="wpop-fn-group">
                <div class="wpop-fn-header">
                  <span>${escapeHtml(fn.function)}</span>
                  <span class="wpop-fn-count">${formatNumber((fn.items || []).length)}</span>
                </div>
                ${(fn.items || [])
                  .map(
                    (item) => `
                  <div class="wpop-item">
                    <span class="wpop-item-name">${escapeHtml(item.image || "\u2014")}</span>
                    <span class="wpop-item-person"><i class="fa-solid fa-user-tie" style="font-size:.6rem;opacity:.55;"></i> ${escapeHtml(item.person || "Sem respons\u00e1vel")}</span>
                  </div>`,
                  )
                  .join("")}
              </div>`,
            )
            .join("")
        : `<p class="wpop-empty">Nenhuma tarefa</p>`;
      break;
    }

    default:
      bodyHtml = `<p class="wpop-empty">Sem detalhes dispon\u00edveis</p>`;
  }

  popover.innerHTML = `
    <div class="wpop-header">
      <span class="wpop-title">${escapeHtml(kpi.label)}</span>
      <button class="wpop-close" aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="wpop-body">
      <section class="wpop-section">
        <h4 class="wpop-section-title">
          <i class="${escapeHtml(safeIcon(kpi.icon))}"></i> Detalhes
          <span class="wpop-count">${formatNumber(kpi.value)}</span>
        </h4>
        ${bodyHtml}
      </section>
    </div>
  `;

  popover
    .querySelector(".wpop-close")
    ?.addEventListener("click", closeKpiPopover);

  positionKpiPopover(cardEl);
  popover.hidden = false;
}

function closeKpiPopover() {
  if (gestaoEls.kpiPopover) {
    gestaoEls.kpiPopover.hidden = true;
  }
  gestaoEls._openKpiKey = null;
}

function positionKpiPopover(cardEl) {
  const popover = gestaoEls.kpiPopover;
  const rect = cardEl.getBoundingClientRect();
  const vpWidth = window.innerWidth;
  const vpHeight = window.innerHeight;
  const popoverWidth = 300;
  const popoverMaxHeight = Math.min(460, vpHeight - 24);

  let top = rect.bottom + 8;
  let left = rect.left;

  if (left + popoverWidth > vpWidth - 8) {
    left = rect.right - popoverWidth;
  }

  if (left < 8) {
    left = 8;
  }

  if (top + popoverMaxHeight > vpHeight - 8) {
    top = rect.top - popoverMaxHeight - 8;
  }

  if (top < 8) {
    top = 8;
  }

  popover.style.top = `${top}px`;
  popover.style.left = `${left}px`;
  popover.style.width = `${popoverWidth}px`;
  popover.style.maxHeight = `${popoverMaxHeight}px`;
}

// ────────────────────────────────────────────────────────────────────────────

async function loadImagesForDelivery() {
  const obraId = gestaoEls.obraSelect?.value;
  const statusId = gestaoEls.statusSelect?.value;

  if (!obraId || !statusId) {
    resetImagesContainer(
      "Selecione uma obra e um status para listar as imagens.",
    );
    return;
  }

  try {
    resetImagesContainer("Carregando imagens...");
    const url = new URL(
      gestaoConfig.getImagensUrl || "../Entregas/get_imagens.php",
      window.location.href,
    );
    url.searchParams.set("obra_id", obraId);
    url.searchParams.set("status_id", statusId);

    const response = await fetch(url.toString(), {
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const imagens = await response.json();
    if (!Array.isArray(imagens) || !imagens.length) {
      resetImagesContainer("Nenhuma imagem encontrada para esses critérios.");
      return;
    }

    gestaoEls.imagensContainer.innerHTML = imagens
      .map(
        (imagem) => `
            <label class="checkbox-item">
                <input type="checkbox" name="imagem_ids[]" value="${escapeHtml(String(imagem.id))}">
                <p>${escapeHtml(imagem.nome || "")}</p>
            </label>
        `,
      )
      .join("");
  } catch (error) {
    console.error(error);
    resetImagesContainer("Não foi possível carregar as imagens.");
    showToast("Falha ao carregar imagens da entrega.", "error");
  }
}

async function submitDeliveryForm(event) {
  event.preventDefault();

  if (!gestaoEls.entregaForm) {
    return;
  }

  const formData = new FormData(gestaoEls.entregaForm);
  if (!formData.getAll("imagem_ids[]").length) {
    showToast("Selecione pelo menos uma imagem para a entrega.", "error");
    return;
  }

  try {
    const response = await fetch(
      gestaoConfig.saveEntregaUrl || "../Entregas/save_entrega.php",
      {
        method: "POST",
        body: formData,
      },
    );

    const payload = await response.json();
    if (!response.ok || !payload.success) {
      throw new Error(
        payload.msg || payload.message || "Falha ao salvar entrega.",
      );
    }

    showToast("Entrega adicionada com sucesso.", "success");
    gestaoEls.entregaForm.reset();
    resetImagesContainer(
      "Selecione uma obra e um status para listar as imagens.",
    );
    closeDeliveryModal();
    loadDashboard();
  } catch (error) {
    console.error(error);
    showToast(error.message || "Falha ao salvar entrega.", "error");
  }
}

function resetImagesContainer(message) {
  if (gestaoEls.imagensContainer) {
    gestaoEls.imagensContainer.innerHTML = `<p>${escapeHtml(message)}</p>`;
  }
}

function formatNumber(value) {
  return new Intl.NumberFormat("pt-BR").format(Number(value || 0));
}

function formatTime(isoValue) {
  const date = new Date(isoValue);
  if (Number.isNaN(date.getTime())) {
    return "agora";
  }

  return new Intl.DateTimeFormat("pt-BR", {
    hour: "2-digit",
    minute: "2-digit",
  }).format(date);
}

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function getInitial(name) {
  return (
    String(name || "?")
      .trim()
      .charAt(0)
      .toUpperCase() || "?"
  );
}

function showToast(message, type = "info") {
  const backgroundByType = {
    success: "linear-gradient(135deg, #1f7a4c, #2fb16a)",
    error: "linear-gradient(135deg, #7f1d1d, #dc2626)",
    info: "linear-gradient(135deg, #1d4ed8, #2563eb)",
  };

  if (window.Toastify) {
    window
      .Toastify({
        text: message,
        duration: 3200,
        close: true,
        gravity: "top",
        position: "right",
        stopOnFocus: true,
        style: {
          background: backgroundByType[type] || backgroundByType.info,
          color: "#ffffff",
        },
      })
      .showToast();
    return;
  }

  window.alert(message);
}

function severityTone(severity) {
  if (severity === "critical") {
    return "var(--critical)";
  }

  if (severity === "warning") {
    return "var(--warning)";
  }

  return "var(--success)";
}

function statusTone(statusToneValue) {
  if (statusToneValue === "overloaded") {
    return "var(--critical)";
  }

  if (statusToneValue === "idle") {
    return "var(--success)";
  }

  return "var(--warning)";
}

function mapFooterTone(tone) {
  if (tone === "accent") {
    return "accent";
  }

  if (tone === "warning") {
    return "warning";
  }

  if (tone === "success") {
    return "success";
  }

  if (tone === "critical") {
    return "critical";
  }

  return "info";
}

function safeIcon(iconClass) {
  const fallbackMap = {
    "fa-regular fa-box": "fa-solid fa-box",
    "fa-regular fa-list-check": "fa-solid fa-list-check",
    "fa-regular fa-sliders": "fa-solid fa-sliders",
    "fa-regular fa-user-group": "fa-solid fa-user-group",
    "fa-regular fa-chart-line": "fa-solid fa-chart-line",
    "fa-regular fa-waveform-lines": "fa-solid fa-bolt",
    "fa-solid fa-sparkle": "fa-solid fa-bolt",
  };

  return fallbackMap[iconClass] || iconClass;
}
