(function () {
  "use strict";

  const root = document.getElementById("overview-v1");
  const refreshButton = document.getElementById("overview-refresh");
  const freshness = document.getElementById("overview-freshness");
  const config = window.FLOW_OVERVIEW_CONFIG || {};
  if (!root) return;

  let overviewData = null;
  let deliveries = [];
  let deliveriesError = false;
  let calendarMonth = new Date();
  let selectedDate = localDateKey(new Date());
  let requestSequence = 0;

  const esc = (value) => String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
  const num = (value) => Number(value || 0);
  const clamp = (value, min, max) => Math.min(max, Math.max(min, num(value)));
  const severityLabel = (value) => ({ critical: "Crítico", high: "Ação", warning: "Atenção" })[value] || "Info";
  const statusLabel = (value) => ({ atrasada: "Atraso", hold: "Hold", parcial: "Parcial", concluida: "Concluída", pendente: "Pendente" })[value] || "Entrega";

  function localDateKey(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
  }

  function panel(title, icon, content, options = {}) {
    const count = options.count == null ? "" : `<b>${num(options.count)}</b>`;
    const action = options.action || "";
    const className = options.className ? ` ${options.className}` : "";
    return `<section class="flow-panel${className}"><header class="flow-panel__header"><div><h2><i class="${icon}"></i>${esc(title)}${count}</h2>${options.subtitle ? `<p>${esc(options.subtitle)}</p>` : ""}</div>${action}</header>${content}</section>`;
  }

  function empty(title, detail, icon = "ri-checkbox-circle-line") {
    return `<div class="flow-empty"><i class="${icon}"></i><strong>${esc(title)}</strong><span>${esc(detail)}</span></div>`;
  }

  function sectionError(title, detail) {
    return `<div class="flow-empty is-error"><i class="ri-cloud-off-line"></i><strong>${esc(title)}</strong><span>${esc(detail)}</span><button type="button" data-action="refresh">Tentar novamente</button></div>`;
  }

  function skeletonPanel(rows = 3, className = "") {
    return `<section class="flow-panel flow-skeleton-panel ${className}"><header class="flow-panel__header"><span class="flow-skeleton w-38"></span><span class="flow-skeleton w-16"></span></header><div class="flow-skeleton-rows">${Array.from({ length: rows }, (_, index) => `<div class="flow-skeleton-row"><i class="flow-skeleton"></i><span><b class="flow-skeleton w-${index % 2 ? "54" : "72"}"></b><b class="flow-skeleton w-${index % 2 ? "72" : "46"}"></b></span><em class="flow-skeleton"></em></div>`).join("")}</div></section>`;
  }

  function renderSkeleton(mode) {
    root.className = `flow-overview flow-overview--${mode} is-loading`;
    root.setAttribute("aria-busy", "true");
    const kpis = `<div class="flow-kpis">${Array.from({ length: 4 }, () => `<article class="flow-kpi flow-kpi--skeleton"><i class="flow-skeleton"></i><div><span class="flow-skeleton w-54"></span><strong class="flow-skeleton w-38"></strong><small class="flow-skeleton w-72"></small></div></article>`).join("")}</div>`;
    root.innerHTML = mode === "manager"
      ? `${kpis}<div class="manager-primary">${skeletonPanel(5, "area-calendar")}${skeletonPanel(5, "area-attention")}</div><div class="manager-secondary">${skeletonPanel(4)}${skeletonPanel(4)}</div>${skeletonPanel(4, "area-capacity")}<div class="flow-loading-signature"><span class="overview-orb"></span><div><strong>Organizando prioridades</strong><small>Cruzando entregas, bloqueios e capacidade.</small></div></div>`
      : `${kpis}<div class="collaborator-grid"><div>${skeletonPanel(3, "area-progress")}</div><aside>${skeletonPanel(4, "area-attention")}${skeletonPanel(3, "area-next")}</aside></div>${skeletonPanel(3, "area-completed")}<div class="flow-loading-signature"><span class="overview-orb"></span><div><strong>Preparando seu foco</strong><small>Organizando tarefas e sinais acionáveis.</small></div></div>`;
  }

  function kpi(icon, label, value, detail, tone, meter = null) {
    return `<article class="flow-kpi is-${tone}"><span><i class="${icon}"></i></span><div><label>${esc(label)}</label><strong>${esc(value)}</strong><small>${esc(detail)}</small></div>${meter == null ? "" : `<i class="flow-kpi__meter"><b style="width:${clamp(meter, 0, 100)}%"></b></i>`}</article>`;
  }

  function attentionList(items, mode) {
    if (!Array.isArray(items)) return sectionError("Alertas indisponíveis", "Os demais blocos continuam atualizados.");
    if (!items.length) return empty("Tudo sob controle", mode === "manager" ? "Nenhum sinal exige intervenção operacional agora." : "Nenhuma ação pendente depende de você agora.");
    return `<div class="attention-list">${items.slice(0, 5).map((item) => {
      const actionLabel = ({ open_capacity: "Abrir capacidade", open_planning: "Abrir planejamento", open_project: "Abrir projeto", open_kanban: "Abrir Kanban", open_task: "Abrir tarefa" })[item.action?.type]
        || ({ fotografico: "Abrir planejamento", flow_block: "Abrir Flow Block", render: "Abrir Render", pre_alteracao: "Abrir triagem", projeto: "Abrir projeto", imagem: "Abrir imagem", cobranca_cliente: "Abrir entregas" })[item.type]
        || "Abrir pendência";
      return `<article class="attention-item is-${esc(item.severity)}"><span class="attention-item__icon"><i class="${item.severity === "critical" ? "ri-alarm-warning-line" : "ri-error-warning-line"}"></i></span><div class="attention-item__copy"><span>${severityLabel(item.severity)}</span><strong>${esc(item.title)}</strong>${item.detail ? `<small>${esc(item.detail)}</small>` : ""}</div><button type="button" class="flow-button" data-action="${esc(item.action?.type || "open_task")}" data-task-id="${num(item.type === "flow_review" ? item.entity_id : 0)}" data-assignee-id="${num(item.assignee_id)}" data-project-id="${num(item.project_id)}" data-url="${esc(item.action?.url || "")}">${actionLabel}</button></article>`;
    }).join("")}</div>`;
  }

  function timeline(task) {
    const stages = Array.isArray(task.timeline) ? task.timeline : [];
    if (!stages.length) return "";
    return `<div class="task-timeline" aria-label="Etapas da tarefa">${stages.map((stage) => `<span class="is-${esc(stage.state)}"><i></i><small>${esc(stage.label)}</small></span>`).join("")}</div>`;
  }

  function taskCard(task) {
    const thumb = task.thumbnail_url ? `<img src="${esc(task.thumbnail_url)}" alt="" loading="lazy">` : `<span class="task-thumb__empty"><i class="ri-image-line"></i></span>`;
    const exception = task.exception;
    return `<article class="task-card"><div class="task-thumb">${thumb}</div><div class="task-card__body"><span class="task-card__project">${esc(task.project || "Projeto")}</span><h3>${esc(task.image_name || "Tarefa")}</h3><p><i class="ri-shape-line"></i>${esc(task.function_name || "Etapa")}${task.substatus ? `<em>${esc(task.substatus)}</em>` : ""}</p>${timeline(task)}<div class="task-card__meta"><span class="${exception ? `is-${esc(exception.severity)}` : ""}"><i class="${exception ? "ri-error-warning-line" : "ri-play-circle-line"}"></i>${esc(exception?.label || task.status || "Em andamento")}</span><time><i class="ri-calendar-line"></i>${esc(task.deadline?.label || "Sem prazo")}</time></div></div><button type="button" class="flow-button" data-action="open_task" data-task-id="${num(task.task_id)}">Abrir tarefa</button></article>`;
  }

  function nextList(tasks) {
    if (!Array.isArray(tasks)) return sectionError("Fila indisponível", "Não foi possível consultar as próximas tarefas.");
    if (!tasks.length) return empty("Sua fila está livre", "Não há uma próxima tarefa liberada neste momento.");
    return `<ol class="next-list">${tasks.map((task, index) => `<li><span>${String(index + 1).padStart(2, "0")}</span><button type="button" data-action="open_task" data-task-id="${num(task.task_id)}"><strong>${esc(task.project)}</strong><small>${esc(task.image_name)}</small></button><em>${esc(task.function_name)}</em><time class="is-${esc(task.deadline?.state)}">${esc(task.deadline?.label)}</time></li>`).join("")}</ol><button type="button" class="flow-panel__footer" data-action="open_kanban">Ver toda a fila <i class="ri-arrow-right-line"></i></button>`;
  }

  function weekLoad(load) {
    if (!load || load.available === false) return sectionError("Carga indisponível", "O planejamento semanal não respondeu, mas suas tarefas seguem disponíveis.");
    const days = Array.isArray(load.days) ? load.days : [];
    if (!days.length) return empty("Semana sem carga planejada", "Ainda não existem alocações para os próximos dias.", "ri-calendar-check-line");
    return `<div class="week-load">${days.map((day) => `<article class="is-${esc(day.state)}"><span>${esc(day.weekday)}</span><strong>${Math.round(num(day.percent))}%</strong><i><b style="width:${clamp(day.percent, 0, 100)}%"></b></i></article>`).join("")}</div>`;
  }

  function completedBlock(data) {
    if (!data || data.available === false) return sectionError("Histórico indisponível", "Não foi possível buscar as conclusões recentes.");
    const recent = Array.isArray(data.recent) ? data.recent : [];
    if (!recent.length) return empty("Nenhuma conclusão recente", "Suas próximas entregas concluídas aparecerão aqui.", "ri-history-line");
    return `<div class="completed-list">${recent.map((item) => `<button type="button" data-action="open_task" data-task-id="${num(item.task_id)}"><i class="ri-checkbox-circle-fill"></i><strong>${esc(item.project)}</strong><span>${esc(item.image_name)}</span><small>${esc(item.function_name)}</small></button>`).join("")}</div>`;
  }

  function teamList(team) {
    if (!Array.isArray(team)) return sectionError("Equipe indisponível", "A consulta de carga da equipe falhou isoladamente.");
    if (!team.length) return empty("Equipe sem carga planejada", "As alocações aparecerão aqui assim que forem confirmadas.", "ri-team-line");
    return `<div class="team-list">${team.slice(0, 4).map((person) => `<article class="is-${esc(person.state)}"><span class="team-avatar">${esc((person.name || "?").trim().charAt(0))}</span><div><strong>${esc(person.name)}</strong><small>${esc(person.function_name || "Sem função planejada")}</small></div><div class="team-load"><strong>${Math.round(num(person.peak_percent))}%</strong><i><b style="width:${clamp(person.peak_percent, 0, 100)}%"></b></i></div><span>${num(person.wip)} tarefas</span><i class="ri-arrow-right-s-line"></i></article>`).join("")}</div>`;
  }

  function riskList(risks) {
    if (!Array.isArray(risks)) return sectionError("Riscos indisponíveis", "A projeção de projetos não respondeu.");
    if (!risks.length) return empty("Nenhum projeto em risco", "As projeções atuais estão dentro das margens operacionais.", "ri-shield-check-line");
    return `<div class="risk-list">${risks.map((risk) => `<article class="is-${esc(risk.severity)}"><div><strong>${esc(risk.project)}</strong><span class="severity-chip">${severityLabel(risk.severity)}</span></div><p>${esc(risk.title)} ${esc(risk.detail)}</p><button type="button" data-action="open_planning" data-project-id="${num(risk.entity_id)}" data-delivery-id="${num(risk.delivery_id)}"><i class="ri-arrow-right-s-line"></i></button></article>`).join("")}</div>`;
  }

  function capacityMatrix(capacity) {
    if (!Array.isArray(capacity)) return sectionError("Capacidade indisponível", "A matriz não respondeu; os demais sinais continuam utilizáveis.");
    if (!capacity.length) return empty("Capacidade equilibrada", "Nenhuma função exige apoio nas próximas quatro semanas.", "ri-shield-check-line");
    const weeks = capacity[0]?.weeks || [];
    const stateLabel = (value) => ({ SAUDAVEL: "Disponível", NECESSITA_APOIO: "Apoio", CONFLITO: "Conflito", SEM_PRINCIPAIS_CONFIGURADOS: "Sem principais", SEM_CAPACIDADE_CONFIGURADA: "Sem capacidade", SEM_DEMANDA: "" })[String(value || "")] || "";
    const weekHeaders = weeks.map((week, index) => `<div class="capacity-week-head${index === 0 ? " is-current" : ""}"><strong>${esc(formatWeekRange(week.semana))}</strong><span>${index === 0 ? "Esta semana" : ""}</span></div>`).join("");
    const rows = capacity.map((item) => `<div class="capacity-stage-label"><strong>${esc(item.name)}</strong></div>${(item.weeks || []).map((week) => { const demand = num(week.pico_demanda); const limit = week.capacidade_principal_referencia == null ? null : num(week.capacidade_principal_referencia); const state = String(week.classificacao || item.classification || "SEM_DEMANDA").toLowerCase().replaceAll("_", "-"); const ratio = limit && limit > 0 ? Math.min(1, demand / limit) : 0; const display = demand > 0 ? `${demand} <i>/</i> ${limit ?? "—"}` : "—"; return `<button type="button" class="capacity-cell is-${esc(state)}" data-action="open_capacity" style="--occupancy:${ratio}"><strong>${display}</strong><span>${esc(stateLabel(week.classificacao))}</span></button>`; }).join("")}`).join("");
    return `<div class="capacity-heatmap-wrap"><div class="capacity-matrix" style="--capacity-week-count:${weeks.length}"><div class="capacity-week-corner"><strong>Função</strong><small>Demanda / capacidade</small></div>${weekHeaders}${rows}</div></div>`;
  }

  function formatWeekRange(value) {
    if (!value) return "";
    const start = new Date(`${String(value).slice(0, 10)}T12:00:00`);
    const end = new Date(start);
    end.setDate(end.getDate() + 6);
    const short = (date) => date.toLocaleDateString("pt-BR", { day: "2-digit", month: "short" }).replace(".", "");
    return `${short(start)} – ${short(end)}`;
  }

  function deliveryTone(status) {
    return ({ atrasada: "critical", hold: "neutral", parcial: "attention", concluida: "healthy", pendente: "active" })[status] || "active";
  }

  function deliveryPopover(items, title) {
    const content = items.length ? items.slice(0, 4).map((item) => {
      const delivered = num(item.entregues);
      const total = num(item.total_itens);
      const progress = total > 0 ? clamp((delivered / total) * 100, 0, 100) : num(item.pct_entregue);
      return `<button type="button" class="delivery-popover__item" data-action="open_delivery" data-project-id="${num(item.obra_id)}" data-delivery-id="${num(item.id)}"><div><strong>${esc(item.nomenclatura || "Projeto")}</strong><span class="severity-chip is-${deliveryTone(item.kanban_status)}">${statusLabel(item.kanban_status)}</span></div><p>${esc(item.nome_etapa || "Entrega")}</p><div class="delivery-progress"><span><b>${delivered}</b> entregues de <b>${total}</b></span><i><b style="width:${progress}%"></b></i></div></button>`;
    }).join("") : empty("Nenhuma entrega neste dia", "Escolha outro dia marcado para consultar o andamento.", "ri-calendar-check-line");
    return `<aside class="delivery-popover" role="dialog" aria-label="Entregas de ${esc(title)}"><header><span>${esc(title)}</span><button type="button" data-action="clear_date" aria-label="Fechar entregas"><i class="ri-close-line"></i></button></header><div>${content}</div>${items.length > 4 ? `<button type="button" class="flow-panel__footer" data-action="open_deliveries">Ver todas as entregas <i class="ri-arrow-right-line"></i></button>` : ""}</aside>`;
  }

  function calendarBlock() {
    if (deliveriesError) return sectionError("Entregas indisponíveis", "O calendário não respondeu. As demais decisões continuam disponíveis.");
    const year = calendarMonth.getFullYear();
    const month = calendarMonth.getMonth();
    const firstWeekday = (new Date(year, month, 1).getDay() + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const previousDays = new Date(year, month, 0).getDate();
    const monthLabel = calendarMonth.toLocaleDateString("pt-BR", { month: "long", year: "numeric" });
    const detailTitle = selectedDate ? new Date(`${selectedDate}T12:00:00`).toLocaleDateString("pt-BR", { day: "2-digit", month: "short", weekday: "short" }) : "Entregas";
    const byDate = new Map();
    deliveries.forEach((item) => {
      if (!item.data_prevista) return;
      if (!byDate.has(item.data_prevista)) byDate.set(item.data_prevista, []);
      byDate.get(item.data_prevista).push(item);
    });
    const cells = [];
    for (let index = 0; index < 42; index += 1) {
      const dayOffset = index - firstWeekday + 1;
      let cellDate;
      let outside = false;
      if (dayOffset < 1) { cellDate = new Date(year, month - 1, previousDays + dayOffset); outside = true; }
      else if (dayOffset > daysInMonth) { cellDate = new Date(year, month + 1, dayOffset - daysInMonth); outside = true; }
      else cellDate = new Date(year, month, dayOffset);
      const key = localDateKey(cellDate);
      const events = byDate.get(key) || [];
      const side = index % 7 > 3 ? "left" : "right";
      cells.push(`<div class="calendar-day-cell is-popover-${side}"><button type="button" class="calendar-day${outside ? " is-outside" : ""}${key === selectedDate ? " is-selected" : ""}${key === localDateKey(new Date()) ? " is-today" : ""}" data-action="select_date" data-date="${key}"><span>${cellDate.getDate()}</span><i>${events.slice(0, 3).map((event) => `<b class="is-${deliveryTone(event.kanban_status)}"></b>`).join("")}</i>${events.length > 3 ? `<small>+${events.length - 3}</small>` : ""}</button>${key === selectedDate ? deliveryPopover(events, detailTitle) : ""}</div>`);
    }
    return `<div class="delivery-calendar"><div class="calendar-main"><div class="calendar-toolbar"><button type="button" data-action="calendar_prev" aria-label="Mês anterior"><i class="ri-arrow-left-s-line"></i></button><strong>${esc(monthLabel)}</strong><button type="button" data-action="calendar_next" aria-label="Próximo mês"><i class="ri-arrow-right-s-line"></i></button></div><div class="calendar-weekdays">${["SEG", "TER", "QUA", "QUI", "SEX", "SÁB", "DOM"].map((day) => `<span>${day}</span>`).join("")}</div><div class="calendar-grid">${cells.join("")}</div></div></div>`;
  }

  function managerKpis(data) {
    const team = Array.isArray(data.team) ? data.team : [];
    const risks = Array.isArray(data.risks) ? data.risks : [];
    const production = data.production || {};
    const delayed = deliveries.filter((item) => item.kanban_status === "atrasada").length;
    return `<div class="flow-kpis">${kpi("ri-alarm-warning-line", "Situações críticas", String(num(data.summary?.critical_count)), "exigem intervenção", "danger")}${kpi("ri-user-unfollow-line", "Sobrecargas", String(team.filter((person) => num(person.peak_percent) > 100).length), "na equipe planejada", "warning")}${kpi("ri-time-line", "Entregas atrasadas", deliveriesError ? "—" : String(delayed), deliveriesError ? "consulta indisponível" : "pedem replanejamento", "attention")}${kpi("ri-focus-3-line", "Pontualidade", production.available && production.punctuality_percent != null ? `${Math.round(num(production.punctuality_percent))}%` : "—", risks.length ? `${risks.length} projeto(s) em risco` : "operação projetada", "active", production.punctuality_percent)}</div>`;
  }

  function renderManager(data) {
    root.className = "flow-overview flow-overview--manager";
    root.innerHTML = `${managerKpis(data)}${panel("Calendário de entregas", "ri-calendar-event-line", calendarBlock(), { className: "area-calendar area-calendar--wide" })}<div class="manager-secondary manager-secondary--three">${panel("Atenção necessária", "ri-alarm-warning-line", attentionList(data.attention, "manager"), { count: data.summary?.attention_count, className: "area-attention", subtitle: "Intervenções priorizadas por impacto operacional." })}${panel("Equipe", "ri-team-line", teamList(data.team), { action: '<button type="button" class="flow-link" data-action="open_capacity">Ver toda a equipe <i class="ri-arrow-right-line"></i></button>' })}${panel("Projetos em risco", "ri-radar-line", riskList(data.risks), { action: '<button type="button" class="flow-link" data-action="open_planning">Ver todos <i class="ri-arrow-right-line"></i></button>' })}</div>${panel("Capacidade global", "ri-layout-grid-line", capacityMatrix(data.capacity), { action: '<button type="button" class="flow-link" data-action="open_capacity">Abrir capacidade <i class="ri-arrow-right-line"></i></button>', className: "area-capacity", subtitle: "Demanda principal por semana." })}`;
  }

  function collaboratorKpis(data) {
    const complete = data.completed || {};
    return `<div class="flow-kpis flow-kpis--collaborator">${kpi("ri-loader-4-line", "Em andamento", String(num(data.summary?.in_progress_count)), "tarefas em execução", "active")}${kpi("ri-alarm-warning-line", "Atenção necessária", String(num(data.summary?.attention_count)), "ações que dependem de você", num(data.summary?.attention_count) ? "danger" : "healthy")}${kpi("ri-checkbox-circle-line", "Concluídas no mês", complete.available ? String(num(complete.count)) : "—", complete.trend_percent == null ? "sem base anterior" : `${num(complete.trend_percent) > 0 ? "+" : ""}${complete.trend_percent}% vs mês anterior`, "healthy")}</div>`;
  }

  function renderCollaborator(data) {
    const ongoing = !Array.isArray(data.in_progress) ? sectionError("Tarefas indisponíveis", "Não foi possível consultar sua execução atual.") : data.in_progress.length ? `<div class="task-list">${data.in_progress.map(taskCard).join("")}</div>` : empty("Nada em execução agora", "Quando uma tarefa começar, ela aparecerá aqui com prazo e contexto.", "ri-play-circle-line");
    root.className = "flow-overview flow-overview--collaborator";
    root.innerHTML = `${collaboratorKpis(data)}<div class="collaborator-grid"><div class="collaborator-main">${panel("Em andamento", "ri-loader-4-line", ongoing, { count: data.summary?.in_progress_count, className: "area-progress", subtitle: "O trabalho que concentra seu foco agora." })}</div><aside>${panel("Atenção necessária", "ri-alarm-warning-line", attentionList(data.attention, "collaborator"), { count: data.summary?.attention_count, className: "area-attention" })}${panel("A seguir", "ri-arrow-right-line", nextList(data.next), { count: data.next?.length || 0, className: "area-next" })}</aside></div>${panel("Concluído recentemente", "ri-check-double-line", completedBlock(data.completed), { className: "area-completed", action: `<span class="completion-summary">${data.completed?.punctuality_percent == null ? "" : `${Math.round(num(data.completed.punctuality_percent))}% pontuais`}</span>` })}`;
  }

  function renderCurrent() {
    if (!overviewData) return;
    if (overviewData.mode === "manager") renderManager(overviewData);
    else renderCollaborator(overviewData);
    root.setAttribute("aria-busy", "false");
  }

  function navigateAction(control) {
    const action = control.dataset.action;
    if (action === "refresh") return load(true);
    if (action === "open_task") {
      const params = new URLSearchParams({ focus_task: control.dataset.taskId || "" });
      if (control.dataset.assigneeId && num(control.dataset.assigneeId) > 0) params.set("colaborador_id", control.dataset.assigneeId);
      return window.location.assign(`${config.kanbanUrl}?${params}`);
    }
    if (action === "open_kanban") return window.location.assign(config.kanbanUrl);
    if (action === "open_capacity") return window.location.assign(config.capacityUrl);
    if (action === "open_project") return window.location.assign(`Dashboard/obra.php?obra_id=${encodeURIComponent(control.dataset.projectId || "")}`);
    if (action === "open_planning") {
      const params = new URLSearchParams();
      if (control.dataset.projectId) params.set("obra_id", control.dataset.projectId);
      if (control.dataset.deliveryId) params.set("entrega_id", control.dataset.deliveryId);
      return window.location.assign(`${config.planningUrl}${params.size ? `?${params}` : ""}`);
    }
    if (action === "open_delivery") return window.location.assign(`${config.deliveriesPageUrl}?obra_id=${encodeURIComponent(control.dataset.projectId || "")}`);
    if (action === "open_deliveries") return window.location.assign(config.deliveriesPageUrl);
    if (action === "open_pending" && control.dataset.url) return window.location.assign(control.dataset.url);
    if (action === "calendar_prev" || action === "calendar_next") {
      calendarMonth = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth() + (action === "calendar_prev" ? -1 : 1), 1);
      const monthDeliveries = deliveries.filter((item) => String(item.data_prevista || "").startsWith(`${calendarMonth.getFullYear()}-${String(calendarMonth.getMonth() + 1).padStart(2, "0")}`));
      selectedDate = monthDeliveries[0]?.data_prevista || localDateKey(new Date(calendarMonth.getFullYear(), calendarMonth.getMonth(), 1));
      return renderCurrent();
    }
    if (action === "select_date") { selectedDate = control.dataset.date; return renderCurrent(); }
    if (action === "clear_date") { selectedDate = null; return renderCurrent(); }
  }

  root.addEventListener("click", (event) => {
    const control = event.target.closest("[data-action]");
    if (control) navigateAction(control);
  });
  refreshButton?.addEventListener("click", () => load(true));

  async function load(force = false) {
    const sequence = ++requestSequence;
    renderSkeleton(config.mode || "collaborator");
    refreshButton?.classList.add("is-refreshing");
    refreshButton?.setAttribute("disabled", "disabled");
    if (freshness) freshness.innerHTML = '<i class="ri-pulse-line"></i><span>Atualizando dados</span>';
    deliveries = [];
    deliveriesError = false;
    try {
      const overviewRequest = fetch(`${config.overviewUrl}${force ? `?refresh=${Date.now()}` : ""}`, { headers: { Accept: "application/json" } }).then(async (response) => {
        const payload = await response.json();
        if (!response.ok || !payload.success || !payload.overview) throw new Error(payload.error || `HTTP ${response.status}`);
        return payload;
      });
      const requests = [overviewRequest];
      if (config.mode === "manager") requests.push(fetch(config.deliveriesUrl, { headers: { Accept: "application/json" } }).then((response) => { if (!response.ok) throw new Error(`HTTP ${response.status}`); return response.json(); }));
      const results = await Promise.allSettled(requests);
      if (sequence !== requestSequence) return;
      if (results[0].status === "rejected") throw results[0].reason;
      overviewData = results[0].value.overview;
      if (config.mode === "manager") {
        deliveriesError = results[1].status === "rejected";
        deliveries = deliveriesError || !Array.isArray(results[1].value) ? [] : results[1].value;
        const currentMonthDelivery = deliveries.find((item) => String(item.data_prevista || "").startsWith(`${calendarMonth.getFullYear()}-${String(calendarMonth.getMonth() + 1).padStart(2, "0")}`));
        if (!deliveries.some((item) => item.data_prevista === selectedDate) && currentMonthDelivery) selectedDate = currentMonthDelivery.data_prevista;
      }
      renderCurrent();
      const generated = new Date(results[0].value.generated_at || Date.now());
      if (freshness) freshness.innerHTML = `<i class="ri-checkbox-circle-line"></i><span>Atualizado às ${generated.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}</span>`;
    } catch (error) {
      console.error("Visão Geral", error);
      root.className = "flow-overview flow-overview--fatal";
      root.innerHTML = `<div class="flow-fatal"><span class="overview-orb"></span><strong>Não foi possível montar sua Visão Geral.</strong><p>O Kanban continua disponível enquanto tentamos recuperar os sinais operacionais.</p><div><button type="button" class="flow-button is-primary" data-action="refresh">Tentar novamente</button><button type="button" class="flow-button" data-action="open_kanban">Abrir Kanban</button></div></div>`;
      root.setAttribute("aria-busy", "false");
      if (freshness) freshness.innerHTML = '<i class="ri-error-warning-line"></i><span>Falha na atualização</span>';
    } finally {
      if (sequence === requestSequence) {
        refreshButton?.classList.remove("is-refreshing");
        refreshButton?.removeAttribute("disabled");
      }
    }
  }

  window.FlowOverviewV1 = {
    open: () => overviewData ? renderCurrent() : load(),
    refresh: () => load(true),
  };
  load();
})();
