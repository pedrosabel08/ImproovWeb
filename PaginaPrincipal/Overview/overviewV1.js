/* Visão Geral V1 — somente camada de apresentação sobre os contratos existentes. */
(function () {
  "use strict";

  const root = document.getElementById("overview-v1");
  if (!root) return;
  let loaded = false;
  let loading = null;

  const esc = (value) => String(value ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
  const severity = (value) => ({ critical: "Crítico", high: "Ação", warning: "Atenção" })[value] || "Info";
  const number = (value) => Number(value || 0);
  const panel = (title, icon, content, action = "", count = null) => `<section class="flow-overview__panel"><header class="flow-overview__panel-header"><h2><i class="${icon}"></i>${esc(title)}${count !== null ? `<b>${number(count)}</b>` : ""}</h2>${action}</header>${content}</section>`;
  const empty = (title, detail) => `<div class="flow-overview__empty"><i class="ri-checkbox-circle-line"></i><strong>${esc(title)}</strong><span>${esc(detail)}</span></div>`;

  function timeline(task) {
    const stages = task.timeline || [];
    if (!stages.length) return "";
    return `<div class="flow-overview__timeline">${stages.map((stage) => `<div class="flow-overview__timeline-stage is-${esc(stage.state)}"><i></i><span>${esc(stage.label)}</span></div>`).join("")}</div>`;
  }

  function taskCard(task) {
    const image = task.thumbnail_url ? `<img src="${esc(task.thumbnail_url)}" alt="" loading="lazy">` : '<div class="flow-overview__thumb-fallback"><i class="ri-image-line"></i></div>';
    const state = task.exception ? `<span class="flow-overview__state is-${esc(task.exception.severity)}"><i class="ri-error-warning-line"></i>${esc(task.exception.label)}</span>` : `<span class="flow-overview__state is-normal"><i class="ri-play-circle-line"></i>${esc(task.status || "Em andamento")}</span>`;
    return `<article class="flow-overview__task"><div class="flow-overview__thumb">${image}</div><div class="flow-overview__task-main"><p class="flow-overview__eyebrow">${esc(task.project)}</p><h3>${esc(task.image_name || "Tarefa")}</h3><p class="flow-overview__task-function"><i class="ri-shape-line"></i>${esc(task.function_name)}${task.substatus ? ` <em>${esc(task.substatus)}</em>` : ""}</p>${timeline(task)}<div class="flow-overview__task-meta">${task.exception ? `<span class="is-${esc(task.exception.severity)}">${esc(task.exception.label)}</span>` : ""}<span><i class="ri-calendar-line"></i>${esc(task.deadline?.label)}</span></div></div><div class="flow-overview__task-actions">${state}<button class="flow-overview__button" data-action="open-task" data-task-id="${number(task.task_id)}">Abrir tarefa</button></div></article>`;
  }

  function nextList(tasks) {
    if (!tasks?.length) return empty("Sua fila está livre", "Não há uma próxima tarefa liberada agora.");
    return `<ol class="flow-overview__next-list">${tasks.map((task, index) => `<li><span class="flow-overview__row-number">${index + 1}</span><button data-action="open-task" data-task-id="${number(task.task_id)}"><strong>${esc(task.project)}</strong><span>${esc(task.image_name)}</span></button><small>${esc(task.function_name)}</small><time class="is-${esc(task.deadline?.state)}">${esc(task.deadline?.label)}</time></li>`).join("")}</ol><button class="flow-overview__footer-link" data-action="open-kanban">Ver todas as tarefas <i class="ri-arrow-right-line"></i></button>`;
  }

  function attentionList(items) {
    if (!items?.length) return empty("Tudo sob controle", "Nenhuma situação exige sua atenção agora.");
    return `<div class="flow-overview__attention-list">${items.map((item) => `<article class="flow-overview__attention-item is-${esc(item.severity)}"><span class="flow-overview__attention-icon"><i class="${item.severity === "critical" || item.severity === "high" ? "ri-alarm-warning-line" : "ri-information-line"}"></i></span><div><span class="flow-overview__severity">${severity(item.severity)}</span><p>${esc(item.title)}${item.detail ? ` · ${esc(item.detail)}` : ""}</p></div><button class="flow-overview__icon-button" data-action="${esc(item.action?.type)}" data-task-id="${number(item.entity_type === "task" ? item.entity_id : 0)}" data-url="${esc(item.action?.url || "")}" aria-label="Abrir"><i class="ri-arrow-right-s-line"></i></button></article>`).join("")}</div>`;
  }

  function weekLoad(load) {
    if (!load?.available) return empty("Carga indisponível", "Sua carga planejada ainda não está disponível para esta semana.");
    const days = load.days || [];
    return `<p class="flow-overview__panel-subtitle">Distribuição da sua capacidade planejada ao longo da semana.</p><div class="flow-overview__load-days">${days.map((day) => `<div class="flow-overview__load-day is-${esc(day.state)}"><span>${esc(day.weekday)}</span><strong>${Math.round(number(day.percent))}%</strong><i><b style="width:${Math.min(number(day.percent), 100)}%"></b></i></div>`).join("")}</div><div class="flow-overview__load-scale"><span>0%</span><span>100%</span><span>150%</span></div>`;
  }

  function completedBlock(data, inProgressCount) {
    if (!data?.available) return empty("Histórico indisponível", "Não foi possível carregar as conclusões deste mês.");
    const recent = (data.recent || []).length ? `<div class="flow-overview__recent-list">${data.recent.map((item) => `<button data-action="open-task" data-task-id="${number(item.task_id)}"><i class="ri-checkbox-circle-fill"></i><strong>${esc(item.project)}</strong><span>${esc(item.image_name)}</span><small>${esc(item.function_name)}</small></button>`).join("")}</div>` : '<p class="flow-overview__no-recent">Ainda não há conclusões registradas neste mês.</p>';
    return `<div class="flow-overview__completed">${recent}<div class="flow-overview__completed-metrics"><div><span>Concluídas no mês</span><strong>${number(data.count)}</strong><small>${data.trend_percent == null ? "Sem base anterior" : `${number(data.trend_percent) > 0 ? "+" : ""}${data.trend_percent}% vs mês anterior`}</small></div><div><span>Pontualidade</span><strong>${data.punctuality_percent == null ? "—" : `${Math.round(number(data.punctuality_percent))}%`}</strong><small>tarefas planejadas</small></div><div><span>Em andamento</span><strong>${number(inProgressCount)}</strong><small>agora</small></div></div></div>`;
  }

  function kpisCollaborator(data) {
    const complete = data.completed || {};
    const days = data.week_load?.days || [];
    const average = days.length ? Math.round(days.reduce((sum, day) => sum + number(day.percent), 0) / days.length) : null;
    return `<div class="flow-overview__kpis"><article class="flow-overview__kpi is-success"><span><i class="ri-checkbox-circle-line"></i></span><div><label>Concluídas no mês</label><strong>${complete.available ? number(complete.count) : "—"}</strong><small>${complete.trend_percent == null ? "Sem base anterior" : `${number(complete.trend_percent) > 0 ? "+" : ""}${complete.trend_percent}% vs mês anterior`}</small></div></article><article class="flow-overview__kpi is-active"><span><i class="ri-focus-3-line"></i></span><div><label>Pontualidade</label><strong>${complete.available && complete.punctuality_percent != null ? `${Math.round(number(complete.punctuality_percent))}%` : "—"}</strong><small>tarefas planejadas</small></div></article><article class="flow-overview__kpi is-purple"><span><i class="ri-loader-4-line"></i></span><div><label>Em andamento</label><strong>${number(data.summary?.in_progress_count)}</strong><small>tarefas em execução</small></div><i class="flow-overview__mini-bar"><b style="width:${Math.min(number(data.summary?.in_progress_count) * 34, 100)}%"></b></i></article><article class="flow-overview__kpi is-warning"><span><i class="ri-pie-chart-2-line"></i></span><div><label>Carga da semana</label><strong>${average == null ? "—" : `${average}%`}</strong><small>${average == null ? "Sem planejamento" : average > 100 ? "Sobrecarga" : average > 80 ? "Atenção" : "Adequada"}</small></div><i class="flow-overview__mini-bar"><b style="width:${Math.min(number(average), 100)}%"></b></i></article></div>`;
  }

  function renderCollaborator(data) {
    const ongoing = data.in_progress?.length ? `<div class="flow-overview__task-list">${data.in_progress.map(taskCard).join("")}</div>` : empty("Nada em execução agora", "Você não tem nenhuma tarefa em andamento.");
    root.className = "flow-overview flow-overview--collaborator";
    root.innerHTML = `${kpisCollaborator(data)}<div class="flow-overview__grid flow-overview__grid--collaborator"><div class="flow-overview__main-column">${panel("Em andamento", "ri-loader-4-line", `<p class="flow-overview__panel-subtitle">Foque no que está em progresso agora.</p>${ongoing}`, "", data.summary?.in_progress_count)}${panel("Carga da semana", "ri-bar-chart-horizontal-line", weekLoad(data.week_load))}</div><aside class="flow-overview__side-column">${panel("A seguir", "ri-arrow-right-line", `<p class="flow-overview__panel-subtitle">Próximas tarefas na sua fila de prioridade.</p>${nextList(data.next)}`, "", data.next?.length || 0)}${panel("Alertas", "ri-alarm-warning-line", attentionList(data.attention), "", data.attention?.length || 0)}</aside></div><div class="flow-overview__below">${panel("Concluído recentemente", "ri-check-double-line", `<p class="flow-overview__panel-subtitle">Suas últimas entregas concluídas.</p>${completedBlock(data.completed, data.summary?.in_progress_count)}`)}</div>`;
  }

  function managerKpis(data) {
    const team = data.team || [];
    const overloaded = team.filter((person) => number(person.peak_percent) > 100).length;
    const capacity = (data.capacity || []).length;
    const production = data.production || {};
    return `<div class="flow-overview__kpis"><article class="flow-overview__kpi is-danger"><span><i class="ri-alarm-warning-line"></i></span><div><label>Críticos</label><strong>${number(data.summary?.critical_count)}</strong><small>exigem intervenção</small></div></article><article class="flow-overview__kpi is-warning"><span><i class="ri-team-line"></i></span><div><label>Equipe sobrecarregada</label><strong>${overloaded}</strong><small>pico acima de 100%</small></div></article><article class="flow-overview__kpi is-active"><span><i class="ri-checkbox-circle-line"></i></span><div><label>Concluídas no mês</label><strong>${production.available ? number(production.count) : "—"}</strong><small>${production.trend_percent == null ? "Sem base anterior" : `${number(production.trend_percent) > 0 ? "+" : ""}${production.trend_percent}% vs mês anterior`}</small></div></article><article class="flow-overview__kpi is-purple"><span><i class="ri-layout-grid-line"></i></span><div><label>Funções em atenção</label><strong>${capacity}</strong><small>capacidade planejada</small></div></article></div>`;
  }

  function teamList(team) {
    if (!team?.length) return empty("Equipe sem carga planejada", "Ainda não há alocações materializadas para o período.");
    return `<div class="flow-overview__team-list">${team.map((person) => `<article class="flow-overview__team-member is-${esc(person.state)}"><span class="flow-overview__avatar">${esc((person.name || "?").trim().charAt(0))}</span><div><strong>${esc(person.name)}</strong><span>${esc(person.function_name || "Sem função planejada")}</span></div><strong>${Math.round(number(person.peak_percent))}%</strong><span>${number(person.wip)} em execução</span></article>`).join("")}</div>`;
  }

  function capacityMatrix(capacity) {
    if (!capacity?.length) return empty("Capacidade equilibrada", "Nenhuma função exige apoio nas próximas semanas.");
    return `<div class="flow-overview__capacity-list">${capacity.map((item) => `<article class="flow-overview__capacity-row is-${esc(item.classification)}"><strong>${esc(item.name)}</strong>${(item.weeks || []).map((week) => `<span><b>${esc(String(week.pico_demanda ?? "—"))}</b>/<b>${esc(String(week.capacidade_principal_referencia ?? "—"))}</b><small>${esc(String(week.semana || "").slice(5).split("-").reverse().join("/"))}</small></span>`).join("")}</article>`).join("")}</div>`;
  }

  function risksList(risks) {
    if (!risks?.length) return empty("Sem riscos operacionais", "Nenhum projeto apresenta risco agora.");
    return `<div class="flow-overview__risk-list">${risks.map((risk) => `<article class="is-${esc(risk.severity)}"><span class="flow-overview__severity">${severity(risk.severity)}</span><div><strong>${esc(risk.project)}</strong><p>${esc(risk.title)} ${esc(risk.detail)}</p></div><button class="flow-overview__icon-button" data-action="open-planning" aria-label="Abrir planejamento"><i class="ri-arrow-right-s-line"></i></button></article>`).join("")}</div>`;
  }

  function production(data) {
    if (!data?.available) return empty("Produção indisponível", "Tente novamente em instantes.");
    return `<div class="flow-overview__production"><div><span>Concluídas no mês</span><strong>${number(data.count)}</strong><small>${data.trend_percent == null ? "Sem base anterior" : `${number(data.trend_percent) > 0 ? "+" : ""}${data.trend_percent}% vs mês anterior`}</small></div><div><span>Pontualidade</span><strong>${data.punctuality_percent == null ? "—" : `${Math.round(number(data.punctuality_percent))}%`}</strong><small>tarefas planejadas</small></div></div>`;
  }

  function renderManager(data) {
    root.className = "flow-overview flow-overview--manager";
    root.innerHTML = `${managerKpis(data)}${panel("Atenção necessária", "ri-alarm-warning-line", `<p class="flow-overview__panel-subtitle">Situações que precisam de intervenção antes de afetarem a operação.</p>${attentionList(data.attention)}`, "", data.summary?.attention_count)}<div class="flow-overview__grid flow-overview__grid--manager"><div>${panel("Equipe", "ri-team-line", teamList(data.team), '<button class="flow-overview__link" data-action="open-capacity">Ver capacidade <i class="ri-arrow-right-line"></i></button>')}</div><div>${panel("Projetos em risco", "ri-radar-line", risksList(data.risks), '<button class="flow-overview__link" data-action="open-planning">Abrir planejamento <i class="ri-arrow-right-line"></i></button>')}</div></div><div class="flow-overview__below flow-overview__below--manager">${panel("Capacidade global", "ri-layout-grid-line", capacityMatrix(data.capacity), '<button class="flow-overview__link" data-action="open-capacity">Abrir capacidade <i class="ri-arrow-right-line"></i></button>')}${panel("Produção", "ri-line-chart-line", production(data.production))}</div>`;
  }

  const render = (overview) => overview.mode === "manager" ? renderManager(overview) : renderCollaborator(overview);
  const openTask = (taskId) => {
    if (!taskId) return;
    const card = document.querySelector(`.kanban-card[data-id="${CSS.escape(String(taskId))}"]`);
    if (card) return card.click();
    document.getElementById("kanbanBtn")?.click();
    window.setTimeout(() => document.querySelector(`.kanban-card[data-id="${CSS.escape(String(taskId))}"]`)?.click(), 450);
  };
  root.addEventListener("click", (event) => {
    const control = event.target.closest("[data-action]");
    if (!control) return;
    const action = control.dataset.action;
    if (action === "retry") { loaded = false; open(); }
    if (action === "open-task") openTask(number(control.dataset.taskId));
    if (action === "open-kanban") document.getElementById("kanbanBtn")?.click();
    if (action === "open-capacity") window.location.assign("PlanejamentoCapacidade/");
    if (action === "open-planning") window.location.assign("PlanejamentoProducao/");
    if (action === "open-pending" && control.dataset.url) window.location.assign(control.dataset.url);
  });
  async function open() {
    if (loaded || loading) return loading;
    root.innerHTML = '<div class="flow-overview__loading"><i class="ri-loader-4-line"></i><span>Preparando sua Visão Geral…</span></div>';
    loading = fetch("PaginaPrincipal/Overview/getOverviewV1.php").then((response) => { if (!response.ok) throw new Error(`HTTP ${response.status}`); return response.json(); }).then((payload) => { if (!payload.success || !payload.overview) throw new Error(payload.error || "Resposta inválida"); render(payload.overview); loaded = true; }).catch((error) => { console.error("Overview V1", error); root.innerHTML = '<div class="flow-overview__error"><i class="ri-error-warning-line"></i><strong>Não foi possível carregar a Visão Geral.</strong><button data-action="retry">Tentar novamente</button><button data-action="open-kanban">Abrir Kanban</button></div>'; }).finally(() => { loading = null; });
    return loading;
  }
  window.FlowOverviewV1 = { open, refresh: () => { loaded = false; return open(); } };
})();
