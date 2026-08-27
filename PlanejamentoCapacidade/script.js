(() => {
  "use strict";

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [
    ...root.querySelectorAll(selector),
  ];
  const state = {
    data: null,
    weeks: 8,
    view: "heatmap",
    selected: null,
    simulation: null,
  };
  const labels = {
    SAUDAVEL: "Saudável",
    NECESSITA_APOIO: "Necessita apoio",
    CONFLITO: "Conflito",
    SEM_PRINCIPAIS_CONFIGURADOS: "Configuração incompleta",
    SEM_CAPACIDADE_CONFIGURADA: "Sem capacidade configurada",
    SEM_DEMANDA: "Sem demanda",
    RESOLVE: "Resolve",
    RESOLVE_COM_VALIDACAO: "Resolve com validação",
    RESOLVE_COM_APOIO: "Resolve com apoio",
    RESOLVE_PARCIALMENTE: "Resolve parcialmente",
    TRANSFERE_PROBLEMA: "Transfere problema",
    INVIAVEL: "Inviável",
    DEPENDENTE_DE_APOIO: "Depende de apoio",
    SEM_GANHO: "Sem ganho",
    PIORA_CENARIO: "Piora o cenário",
  };
  const severity = {
    SEM_CAPACIDADE_CONFIGURADA: 5,
    CONFLITO: 4,
    SEM_PRINCIPAIS_CONFIGURADOS: 3,
    NECESSITA_APOIO: 2,
    SAUDAVEL: 1,
    SEM_DEMANDA: 0,
  };

  function escapeHtml(value) {
    return String(value ?? "").replace(
      /[&<>'"]/g,
      (char) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          "'": "&#39;",
          '"': "&quot;",
        })[char],
    );
  }
  function toIso(date) {
    return date.toISOString().slice(0, 10);
  }
  function isoDate(value) {
    return new Date(`${value}T12:00:00`);
  }
  function addDays(value, amount) {
    const date = isoDate(value);
    date.setDate(date.getDate() + amount);
    return toIso(date);
  }
  function monday(value) {
    const date = isoDate(value);
    const offset = (date.getDay() + 6) % 7;
    date.setDate(date.getDate() - offset);
    return toIso(date);
  }
  function daysBetween(start, end) {
    return Math.round((isoDate(end) - isoDate(start)) / 86400000);
  }
  function today() {
    return toIso(new Date());
  }
  function formatDate(value, options = { day: "2-digit", month: "short" }) {
    return value
      ? new Intl.DateTimeFormat("pt-BR", options)
          .format(isoDate(value))
          .replace(".", "")
      : "—";
  }
  function formatRange(start) {
    return `${formatDate(start)}–${formatDate(addDays(start, 6))}`;
  }
  function number(value) {
    return Number(value ?? 0).toLocaleString("pt-BR", {
      maximumFractionDigits: 1,
    });
  }
  function signed(value) {
    const numeric = Number(value ?? 0);
    return `${numeric > 0 ? "+" : ""}${number(numeric)}`;
  }
  function stageByCode(code) {
    return (
      (state.data?.etapas || []).find((item) => item.codigo_etapa === code) ||
      null
    );
  }
  function weekly(stage, week) {
    return (stage?.semanas || []).find((item) => item.semana === week) || null;
  }
  function stageName(code) {
    return (
      (state.data?.catalogo_etapas || []).find(
        (item) => item.codigo_etapa === code,
      )?.nome_painel ||
      stageByCode(code)?.nome_painel ||
      code
    );
  }
  function isProblem(status) {
    return [
      "NECESSITA_APOIO",
      "CONFLITO",
      "SEM_PRINCIPAIS_CONFIGURADOS",
      "SEM_CAPACIDADE_CONFIGURADA",
    ].includes(status);
  }
  function matchesStatus(status) {
    const selected = $("#filter-status").value;
    if (!selected) return true;
    return selected === "PROBLEMAS" ? isProblem(status) : status === selected;
  }
  function allWeeks() {
    const start = $("#period-start").value;
    const end = $("#period-end").value;
    const weeks = [];
    for (let cursor = monday(start); cursor <= end; cursor = addDays(cursor, 7))
      weeks.push(cursor);
    return weeks;
  }
  function setPeriod(start, weeks = state.weeks) {
    const mondayStart = monday(start);
    $("#period-start").value = mondayStart;
    $("#period-end").value = addDays(mondayStart, weeks * 7 - 1);
  }

  function cellText(item) {
    if (!item || Number(item.pico_demanda) <= 0)
      return '<span class="capacity-cell-empty">—</span>';
    if (item.classificacao === "CONFLITO")
      return `<strong>CONFLITO</strong><span>−${number(item.deficit_maximo)}</span>`;
    if (item.classificacao === "SEM_PRINCIPAIS_CONFIGURADOS")
      return "<strong>SEM PRINCIPAIS</strong><span>Configurar base</span>";
    if (item.classificacao === "SEM_CAPACIDADE_CONFIGURADA")
      return "<strong>SEM CONFIG.</strong><span>Revisar cadastro</span>";
    if (item.classificacao === "NECESSITA_APOIO")
      return `<strong>APOIO</strong><span>+${number(item.apoio_maximo)} necessário</span>`;
    const demand = number(item.pico_demanda);
    const principal =
      item.capacidade_principal_referencia == null
        ? "—"
        : number(item.capacidade_principal_referencia);
    return `<strong>${demand} <i>/</i> ${principal}</strong><span>${item.principal_no_limite ? "No limite" : "Capacidade principal"}</span>`;
  }
  function cellTitle(stage, item, week) {
    if (!item || Number(item.pico_demanda) <= 0)
      return `${stage.nome_painel}\n${formatRange(week)}\nSem demanda planejada`;
    return [
      stage.nome_painel,
      formatRange(week),
      `Status: ${labels[item.classificacao] || item.classificacao}`,
      `Pico de demanda: ${number(item.pico_demanda)}`,
      `Principais: ${number(item.capacidade_principal_referencia)}`,
      `Secundários potenciais: ${number(item.capacidade_secundaria_referencia)}`,
      `Apoio necessário: ${number(item.apoio_maximo)}`,
      `Dias em apoio: ${item.dias_necessita_apoio}`,
      `Dias em conflito: ${item.dias_conflito}`,
    ].join("\n");
  }
  function cellAria(stage, item, week) {
    return `${stage.nome_painel}, ${formatRange(week)}. ${item && Number(item.pico_demanda) > 0 ? labels[item.classificacao] : "sem demanda"}. Clique para ver detalhes.`;
  }

  function renderKpis() {
    const summary = state.data?.resumo || {};
    const totals = summary.funcoes_por_classificacao || {};
    $("#kpi-plans").textContent = number(summary.planos_considerados);
    $("#kpi-healthy").textContent = number(totals.SAUDAVEL);
    $("#kpi-support").textContent = number(totals.NECESSITA_APOIO);
    $("#kpi-conflict").textContent = number(totals.CONFLITO);
    const priority = state.data?.prioridade;
    const card = $("#priority-card");
    if (!priority || !isProblem(priority.semana?.classificacao)) {
      card.hidden = true;
      return;
    }
    card.hidden = false;
    $("#priority-title").textContent =
      `${priority.nome_painel} · ${formatRange(priority.semana.semana)}`;
    const item = priority.semana;
    const extra =
      item.classificacao === "CONFLITO"
        ? `Déficit ${number(item.deficit_maximo)}`
        : item.classificacao === "NECESSITA_APOIO"
          ? `Apoio necessário ${number(item.apoio_maximo)}`
          : "Nenhum principal configurado";
    $("#priority-detail").textContent =
      `Pico ${number(item.pico_demanda)} · Principais ${number(item.capacidade_principal_referencia)} · ${extra}`;
    $("#priority-open").onclick = () =>
      openDrawer(priority.codigo_etapa, priority.semana.semana);
  }
  function populateStages() {
    const select = $("#filter-stage");
    const previous = select.value;
    select.innerHTML =
      '<option value="">Todas as funções</option>' +
      (state.data?.catalogo_etapas || [])
        .map(
          (stage) =>
            `<option value="${escapeHtml(stage.codigo_etapa)}">${escapeHtml(stage.nome_painel)}</option>`,
        )
        .join("");
    select.value = [...select.options].some(
      (option) => option.value === previous,
    )
      ? previous
      : "";
  }
  function visibleStages() {
    const selected = $("#filter-stage").value;
    const catalog = state.data?.catalogo_etapas || [];
    return catalog
      .map((meta) => ({ ...meta, ...stageByCode(meta.codigo_etapa) }))
      .filter((stage) => {
        if (selected && stage.codigo_etapa !== selected) return false;
        const hasSelected = (stage.semanas || []).some(
          (item) =>
            Number(item.pico_demanda) > 0 && matchesStatus(item.classificacao),
        );
        return !$("#filter-status").value || hasSelected;
      });
  }
  function renderHeatmap() {
    const weeks = allWeeks();
    const stages = visibleStages();
    const heatmap = $("#capacity-heatmap");
    const hasPlans = Number(state.data?.resumo?.planos_considerados || 0) > 0;
    const empty = $("#capacity-empty");
    empty.hidden = hasPlans && stages.length > 0;
    if (!empty.hidden) {
      empty.innerHTML = hasPlans
        ? '<i class="fa-solid fa-circle-check"></i><strong>Nenhuma função corresponde aos filtros.</strong><span>Altere os filtros para voltar à visão consolidada.</span>'
        : '<i class="fa-solid fa-calendar-check"></i><strong>Nenhum planejamento confirmado neste período.</strong><span>O painel consome somente versões vigentes e confirmadas das R00s.</span>';
    }
    heatmap.style.setProperty("--week-count", weeks.length);
    heatmap.innerHTML = `
      <div class="capacity-week-corner"><span>Função</span><small>Demanda / principais</small></div>
      ${weeks.map((week) => `<div class="capacity-week-head ${week === monday(today()) ? "is-today" : ""}"><strong>${formatRange(week)}</strong><span>${week === monday(today()) ? "Esta semana" : ""}</span></div>`).join("")}
      ${stages
        .map((stage) => {
          const capacity = state.data?.capacidades?.[stage.codigo_etapa] || {};
          const principalLabel =
            Number(capacity.capacidade_principal) === 1
              ? "principal"
              : "principais";
          return `<button type="button" class="capacity-stage-label" data-stage="${escapeHtml(stage.codigo_etapa)}" title="Abrir resumo da função"><strong>${escapeHtml(stage.nome_painel || stage.etapa)}</strong><span>${number(capacity.capacidade_principal)} ${principalLabel} · ${number(capacity.capacidade_secundaria)} apoio potencial</span></button>${weeks
            .map((week, index) => {
              const item = weekly(stage, week);
              const status =
                item && Number(item.pico_demanda) > 0
                  ? item.classificacao
                  : "SEM_DEMANDA";
              const filtered = !matchesStatus(status);
              const occupancy =
                status === "SAUDAVEL"
                  ? Math.min(1, Number(item?.pico_ocupacao || 0))
                  : 0;
              return `<button type="button" class="capacity-cell is-${status.toLowerCase().replaceAll("_", "-")} ${filtered ? "is-filtered" : ""}" data-stage="${escapeHtml(stage.codigo_etapa)}" data-week="${week}" data-week-label="${formatRange(week)}" aria-label="${escapeHtml(cellAria(stage, item, week))}" title="${escapeHtml(cellTitle(stage, item, week))}" style="--occupancy:${occupancy}; --cell-delay:${(index + 1) * 30}ms">${cellText(item)}</button>`;
            })
            .join("")}`;
        })
        .join("")}`;
    $$(".capacity-cell", heatmap).forEach((button) =>
      button.addEventListener("click", () =>
        openDrawer(button.dataset.stage, button.dataset.week),
      ),
    );
    $$(".capacity-stage-label", heatmap).forEach((button) =>
      button.addEventListener("click", () =>
        openStageSummary(button.dataset.stage),
      ),
    );
  }
  function projectBars(stage) {
    const projects = new Map();
    (stage?.dias || []).forEach((day) =>
      (day.projetos || []).forEach((project) => {
        const key = String(project.versao_id);
        const current = projects.get(key) || {
          ...project,
          inicio: day.data,
          fim: day.data,
        };
        current.inicio = current.inicio < day.data ? current.inicio : day.data;
        current.fim = current.fim > day.data ? current.fim : day.data;
        projects.set(key, current);
      }),
    );
    return [...projects.values()].sort(
      (a, b) =>
        (a.margem_dias_uteis ?? Infinity) - (b.margem_dias_uteis ?? Infinity),
    );
  }
  function renderTimeline() {
    const stages = visibleStages();
    const selectedCode =
      $("#filter-stage").value ||
      stages.find((item) => stageByCode(item.codigo_etapa))?.codigo_etapa;
    const stage = stageByCode(selectedCode);
    const container = $("#timeline-view");
    if (!stage) {
      container.innerHTML = "";
      return;
    }
    const start = $("#period-start").value;
    const end = $("#period-end").value;
    const total = Math.max(1, daysBetween(start, end) + 1);
    const projects = projectBars(stage);
    container.innerHTML = `<div class="capacity-timeline-heading"><div><p>Sobreposição por função</p><h3>${escapeHtml(stage.nome_painel || stage.etapa)}</h3></div><span>${projects.length} projeto${projects.length === 1 ? "" : "s"} consumindo capacidade no período</span></div><div class="capacity-timeline-bars">${projects
      .map((project) => {
        const left =
          (Math.max(0, daysBetween(start, project.inicio)) / total) * 100;
        const right =
          (Math.min(total, daysBetween(start, project.fim) + 1) / total) * 100;
        return `<button type="button" class="capacity-project-bar" data-stage="${stage.codigo_etapa}" data-week="${monday(project.inicio)}" title="${escapeHtml(`${project.obra}\n${formatDate(project.inicio)}–${formatDate(project.fim)}\nCapacidade planejada: ${number(project.capacidade_planejada)}`)}"><span>${escapeHtml(project.obra)}</span><i style="--bar-left:${left}%;--bar-width:${Math.max(1, right - left)}%"></i><small>${number(project.capacidade_planejada)} capacidade · margem ${signed(project.margem_dias_uteis)}d</small></button>`;
      })
      .join("")}</div>`;
    $$(".capacity-project-bar", container).forEach((button) =>
      button.addEventListener("click", () =>
        openDrawer(button.dataset.stage, button.dataset.week),
      ),
    );
  }
  function render() {
    renderKpis();
    renderHeatmap();
    renderTimeline();
    const hasPlans = Number(state.data?.resumo?.planos_considerados || 0) > 0;
    const hasStages = visibleStages().length > 0;
    $("#timeline-view").hidden =
      state.view !== "timeline" || !hasPlans || !hasStages;
    $("#heatmap-view").hidden =
      state.view !== "heatmap" || !hasPlans || !hasStages;
  }

  function statusBadge(status) {
    return `<span class="capacity-status is-${String(status).toLowerCase().replaceAll("_", "-")}">${escapeHtml(labels[status] || status)}</span>`;
  }
  function openStageSummary(code) {
    const stage = stageByCode(code);
    const capacity = state.data?.capacidades?.[code] || {};
    const summary = (state.data?.resumo_etapas || []).find(
      (item) => item.codigo_etapa === code,
    );
    if (!stage || !summary?.semana_critica) return;
    openDrawer(code, summary.semana_critica.semana);
  }
  function setPageScrollLock(locked) {
    const root = document.documentElement;
    const body = document.body;
    if (!root || !body) return;
    if (locked) {
      const alreadyLocked =
        root.classList.contains("capacity-drawer-open") ||
        body.classList.contains("capacity-drawer-open");
      root.classList.add("capacity-drawer-open");
      body.classList.add("capacity-drawer-open");
      if (!alreadyLocked) {
        const scrollbarWidth = Math.max(
          0,
          window.innerWidth - root.clientWidth,
        );
        body.style.setProperty(
          "--capacity-scrollbar-compensation",
          `${scrollbarWidth}px`,
        );
      }
      return;
    }
    root.classList.remove("capacity-drawer-open");
    body.classList.remove("capacity-drawer-open");
    body.style.removeProperty("--capacity-scrollbar-compensation");
  }
  function openDrawer(code, weekStart) {
    const stage = stageByCode(code);
    const item = weekly(stage, weekStart);
    if (!stage || !item) return;
    state.selected = { code, weekStart };
    const weekEnd = addDays(weekStart, 6);
    const daily = (stage.dias || []).filter(
      (day) =>
        day.data >= weekStart &&
        day.data <= weekEnd &&
        Number(day.demanda_planejada) > 0,
    );
    const projects = item.projetos || [];
    const capacityPotential = Number(item.capacidade_total_referencia ?? 0);
    $("#drawer-content").innerHTML = `
      <p class="capacity-drawer-eyebrow">${escapeHtml(stage.nome_painel || stage.etapa)}</p>
      <h2>${formatRange(weekStart)}</h2>
      ${statusBadge(item.classificacao)}
      <div class="capacity-drawer-metrics">
        <div><span>Pico de demanda</span><strong>${number(item.pico_demanda)}</strong></div>
        <div><span>Principais</span><strong>${number(item.capacidade_principal_referencia)}</strong></div>
        <div><span>Apoio potencial</span><strong>${number(item.capacidade_secundaria_referencia)}</strong></div>
        <div><span>Capacidade potencial</span><strong>${number(capacityPotential)}</strong></div>
        <div><span>Apoio necessário</span><strong>${number(item.apoio_maximo)}</strong></div>
        <div><span>Déficit máximo</span><strong class="${Number(item.deficit_maximo) > 0 ? "is-negative" : ""}">${number(item.deficit_maximo)}</strong></div>
      </div>
      <p class="capacity-drawer-note"><i class="fa-solid fa-circle-info"></i> Secundários são elegíveis para apoio, não pessoas automaticamente disponíveis.</p>
      <section class="capacity-drawer-section"><div class="capacity-drawer-section-title"><h3>Dias úteis</h3><span>${item.dias_conflito} conflito · ${item.dias_necessita_apoio} apoio</span></div><div class="capacity-daily-list">${daily.length ? daily.map((day) => `<div class="capacity-daily-row"><time>${formatDate(day.data, { weekday: "short", day: "2-digit", month: "2-digit" })}</time><strong>${number(day.demanda_planejada)} <i>/</i> ${number(day.capacidade_principal)}</strong><span class="is-${String(day.classificacao).toLowerCase().replaceAll("_", "-")}">${day.classificacao === "CONFLITO" ? `−${number(day.deficit)}` : day.classificacao === "NECESSITA_APOIO" ? `+${number(day.necessidade_apoio)} apoio` : day.classificacao === "SEM_PRINCIPAIS_CONFIGURADOS" ? "sem principais" : day.principal_no_limite ? "no limite" : "normal"}</span></div>`).join("") : '<p class="capacity-no-details">Nenhuma demanda nesta semana.</p>'}</div></section>
      <section class="capacity-drawer-section"><div class="capacity-drawer-section-title"><h3>Projetos consumindo capacidade</h3><span>${projects.length}</span></div><div class="capacity-project-list">${projects.length ? projects.map((project) => `<article><div><strong>${escapeHtml(project.obra)}</strong><span>${formatDate(project.primeiro_dia_semana)}–${formatDate(project.ultimo_dia_semana)} · ${number(project.dias_consumindo_semana)} dia${Number(project.dias_consumindo_semana) === 1 ? "" : "s"}</span></div><b>${number(project.capacidade_planejada)} capacidade</b><em class="${Number(project.margem_dias_uteis) < 0 ? "is-negative" : ""}">margem ${signed(project.margem_dias_uteis)}d</em><a href="../PlanejamentoProducao/?obra_id=${encodeURIComponent(project.obra_id)}&entrega_id=${encodeURIComponent(project.entrega_id)}">Abrir plano <i class="fa-solid fa-arrow-up-right-from-square"></i></a></article>`).join("") : '<p class="capacity-no-details">Nenhuma obra consumindo capacidade nesta semana.</p>'}</div></section>${isProblem(item.classificacao) ? '<button type="button" class="capacity-simulate-button" id="simulate-solution"><i class="fa-solid fa-wand-magic-sparkles"></i> Simular solução</button>' : ""}`;
    $("#simulate-solution")?.addEventListener("click", openSimulation);
    $("#capacity-drawer").classList.add("is-open");
    $("#capacity-drawer").setAttribute("aria-hidden", "false");
    $("#capacity-scrim").hidden = false;
    setPageScrollLock(true);
  }
  function closeDrawer() {
    state.simulation = null;
    $("#capacity-drawer").classList.remove("is-simulation");
    $("#capacity-drawer").classList.remove("is-open");
    $("#capacity-drawer").setAttribute("aria-hidden", "true");
    $("#capacity-scrim").hidden = true;
    setPageScrollLock(false);
  }

  function simulationPayload(actions = [], suggestions = false) {
    return {
      inicio: $("#period-start").value,
      fim: $("#period-end").value,
      conflito: {
        codigo_etapa: state.simulation.code,
        semana: state.simulation.weekStart,
      },
      acoes: actions,
      sugestoes: suggestions,
    };
  }
  async function requestSimulation(actions = [], suggestions = false) {
    const response = await fetch(document.body.dataset.simulationUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify(simulationPayload(actions, suggestions)),
    });
    const payload = await response.json();
    if (!response.ok || !payload.success)
      throw new Error(
        payload.message || "Não foi possível calcular o cenário.",
      );
    return payload.simulacao;
  }
  async function applySimulation(scenario) {
    if (
      !window.confirm(
        "Aplicar este cenário criará novas versões dos planejamentos afetados. A baseline será preservada. Deseja continuar?",
      )
    )
      return;
    const response = await fetch(document.body.dataset.applySimulationUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        ...simulationPayload(scenario.acoes || []),
        confirmado: true,
      }),
    });
    const payload = await response.json();
    if (!response.ok || !payload.success)
      throw new Error(payload.message || "Não foi possível aplicar o cenário.");
    closeDrawer();
    await load();
    alert(
      "Cenário aplicado. O planejamento global foi recalculado com as novas versões.",
    );
  }
  function actionText(action) {
    if (action.tipo === "DESLOCAR_ETAPA")
      return `Deslocar ${stageName(action.codigo_etapa)} em +${action.dias_uteis} dia(s)`;
    if (action.tipo === "ALTERAR_CAPACIDADE")
      return `${stageName(action.codigo_etapa)} para ${action.pessoas} pessoa(s)`;
    if (action.tipo === "CAPACIDADE_EXTRAORDINARIA")
      return `Produção extraordinária em ${formatDate(action.data)}: +${action.pessoas} pessoa(s)`;
    if (action.tipo === "CAPACIDADE_EXTERNA")
      return `Capacidade externa: +${action.pessoas} pessoa(s)`;
    return `Usar ${action.quantidade} apoio(s) secundário(s)`;
  }
  function scenarioSummary(scenario) {
    const compare = scenario.comparacao || {};
    const affected = scenario.planos_afetados || [];
    const external = scenario.intervencoes_capacidade?.externa || [];
    const extraordinary =
      scenario.intervencoes_capacidade?.extraordinaria || [];
    const action = scenario.acoes?.[0] || {};
    const type =
      action.tipo === "CAPACIDADE_EXTRAORDINARIA"
        ? "extra"
        : action.tipo === "CAPACIDADE_EXTERNA"
          ? "external"
          : action.tipo === "DESLOCAR_ETAPA"
            ? "shift"
            : action.tipo === "APOIO_SECUNDARIO"
              ? "support"
              : "capacity";
    const project = affected[0]?.obra || "Projetos do período";
    const margin = affected[0] || {};
    const resolved =
      Number(compare.deficit_depois || 0) <= 0 &&
      Number(compare.conflitos_depois || 0) === 0 &&
      !(scenario.novos_conflitos || []).length;
    const title =
      action.tipo === "DESLOCAR_ETAPA"
        ? `Adiar ${stageName(action.codigo_etapa)} do ${project} em ${action.dias_uteis} dia(s) úteis`
        : action.tipo === "CAPACIDADE_EXTRAORDINARIA"
          ? `Trabalhar no fim de semana (${action.pessoas} pessoa(s))`
          : action.tipo === "CAPACIDADE_EXTERNA"
            ? `Contratar freelancer (${action.pessoas} pessoa(s))`
            : action.tipo === "APOIO_SECUNDARIO"
              ? `Usar ${action.quantidade} pessoa(s) de apoio`
              : actionText(action);
    const explanation =
      action.tipo === "DESLOCAR_ETAPA"
        ? "Usa parte da margem disponível do projeto para eliminar a sobreposição de capacidade."
        : action.tipo === "CAPACIDADE_EXTRAORDINARIA"
          ? "Adiciona capacidade interna extraordinária em uma data normalmente não produtiva."
          : action.tipo === "CAPACIDADE_EXTERNA"
            ? "Adiciona capacidade externa temporária somente durante o conflito."
            : "Altera a capacidade planejada e recalcula o impacto global.";
    const status =
      scenario.classificacao === "TRANSFERE_PROBLEMA"
        ? "Transfere o conflito"
        : resolved
          ? "Resolve o conflito"
          : labels[scenario.classificacao] || scenario.classificacao;
    const externalInfo = external[0]
      ? `+${number(external[0].pico_pessoas)} externo · ${number(external[0].pessoa_dias)} pessoa-dia(s)`
      : extraordinary[0]
        ? `+${number(extraordinary[0].pessoas)} em ${formatDate(extraordinary[0].data)}`
        : "Nenhuma";
    return `<article class="capacity-decision-card capacity-decision-card--${type} is-${String(scenario.classificacao || "").toLowerCase()}"><header><div><span class="capacity-decision-tag">${type === "extra" ? "Capacidade extra · interna" : type === "external" ? "Freelancer · externa" : type === "shift" ? "Deslocamento de etapa" : "Alternativa calculada"}</span><h4>${escapeHtml(title)}</h4><p>${escapeHtml(explanation)}</p></div><strong class="capacity-decision-status">${escapeHtml(status)}</strong></header><div class="capacity-decision-grid"><div><small>O que será feito</small><p>${escapeHtml(explanation)}</p></div><div><small>Impacto no projeto</small><p>${escapeHtml(project)}</p><small>Margem disponível</small><p>${margin.antes?.margem_dias_uteis == null ? "—" : `${signed(margin.antes.margem_dias_uteis)} → ${signed(margin.depois?.margem_dias_uteis)} dias úteis`}</p><small>Entrega final</small><p>${margin.antes?.fim_previsto && margin.antes.fim_previsto === margin.depois?.fim_previsto ? "Não muda" : "Recalculada"}</p></div><div><small>Resultado global</small><p>Déficit <b>${number(compare.deficit_antes)} → ${number(compare.deficit_depois)}</b> ${Number(compare.deficit_depois || 0) <= 0 ? "✓" : ""}</p><p>Conflitos <b>${number(compare.conflitos_antes)} → ${number(compare.conflitos_depois)}</b> ${Number(compare.conflitos_depois || 0) === 0 ? "✓" : ""}</p><small>Capacidade extra</small><p>${externalInfo}</p></div></div><footer><span>Custo operacional <b>${type === "external" ? "Alto" : type === "extra" || type === "shift" ? "Médio" : "Baixo"}</b></span><span>Risco <b>${scenario.novos_conflitos?.length ? "Alto" : resolved ? "Baixo" : "Médio"}</b></span><span>Confiança <b>${scenario.depende_validacao_operacional ? "Média" : "Alta"}</b></span><button type="button" class="capacity-scenario-use" data-suggestion="${scenario.__index ?? ""}">${scenario.__recommended ? "Comparar cenário" : "Ver impacto"}</button></footer>${scenario.novos_conflitos?.length ? `<div class="capacity-decision-warning"><strong>Transfere o conflito</strong><span>${scenario.novos_conflitos.length} novo(s) conflito(s) criado(s).</span></div>` : ""}</article>`;
  }
  function scenarioGroupMarkup(scenarios) {
    const groups = [
      ["extra", "Capacidade extra · interna"],
      ["external", "Freelancer · externa"],
      ["shift", "Deslocamento de projeto"],
      ["support", "Apoio secundário"],
      ["capacity", "Alteração de capacidade"],
    ];
    return groups
      .map(([kind, title]) => {
        const items = scenarios.filter((scenario) => {
          const tipo = scenario.acoes?.[0]?.tipo;
          return (
            (kind === "extra" && tipo === "CAPACIDADE_EXTRAORDINARIA") ||
            (kind === "external" && tipo === "CAPACIDADE_EXTERNA") ||
            (kind === "shift" && tipo === "DESLOCAR_ETAPA") ||
            (kind === "support" && tipo === "APOIO_SECUNDARIO") ||
            (kind === "capacity" && tipo === "ALTERAR_CAPACIDADE")
          );
        });
        return items.length
          ? `<div class="capacity-decision-group"><h4>${title}</h4>${items.map((scenario) => scenarioSummary(scenario)).join("")}</div>`
          : "";
      })
      .join("");
  }
  function renderSimulationLegacy() {
    const sim = state.simulation;
    const projects = sim.item?.projetos || [];
    const suggestions = sim.suggestions || [];
    const active = sim.active;
    $("#drawer-content").innerHTML =
      `<button type="button" class="capacity-simulation-back" id="simulation-back"><i class="fa-solid fa-arrow-left"></i> Voltar ao conflito</button><p class="capacity-drawer-eyebrow">Sandbox de decisão · nada foi alterado</p><h2>${escapeHtml(sim.stage.nome_painel || sim.stage.etapa)} · ${formatRange(sim.weekStart)}</h2><div class="capacity-simulation-origin"><span>Déficit atual</span><strong>−${number(sim.item.deficit_maximo)}</strong><span>Pico ${number(sim.item.pico_demanda)} · principais ${number(sim.item.capacidade_principal_referencia)}</span></div><section class="capacity-drawer-section"><div class="capacity-drawer-section-title"><h3>Alternativas calculadas</h3><span>${suggestions.length}</span></div><div class="capacity-scenarios">${suggestions.length ? suggestions.map((scenario, index) => `${scenarioSummary(scenario)}<button type="button" class="capacity-scenario-use" data-suggestion="${index}">Comparar cenário</button>`).join("") : '<p class="capacity-no-details">Nenhuma alternativa segura foi encontrada automaticamente.</p>'}</div></section><section class="capacity-drawer-section"><div class="capacity-drawer-section-title"><h3>Cenário manual</h3><span>não salva</span></div><form id="simulation-manual" class="capacity-simulation-form"><label>Projeto<select id="simulation-project">${projects.map((project) => `<option value="${project.entrega_id}">${escapeHtml(project.obra)} · margem ${signed(project.margem_dias_uteis)}d</option>`).join("")}</select></label><label>Intervenção<select id="simulation-type"><option value="DESLOCAR_ETAPA">Deslocar etapa</option><option value="ALTERAR_CAPACIDADE">Alterar pessoas</option><option value="APOIO_SECUNDARIO">Usar apoio secundário</option></select></label><label>Quantidade<input id="simulation-value" type="number" min="1" max="20" value="1"></label><button type="submit" class="capacity-simulate-button">Simular cenário manual</button></form></section>${
        active
          ? `<section class="capacity-drawer-section capacity-simulation-result"><div class="capacity-drawer-section-title"><h3>Comparação antes / depois</h3><span>${escapeHtml(labels[active.classificacao] || active.classificacao)}</span></div>${scenarioSummary(active)}<div class="capacity-simulation-steps">${(
              active.planos_afetados || []
            )
              .map((plan) => {
                const before = plan.etapas_antes?.[sim.code] || {};
                const after = plan.etapas_depois?.[sim.code] || {};
                return `<div><strong>${escapeHtml(plan.obra)}</strong><span>${formatDate(before.inicio)}–${formatDate(before.limite)} → ${formatDate(after.inicio)}–${formatDate(after.limite)}</span></div>`;
              })
              .join(
                "",
              )}</div><p class="capacity-drawer-note"><i class="fa-solid fa-lock"></i> A aplicação cria nova versão, preserva a baseline e revalida a R00 antes de gravar.</p>${active.planos_afetados?.length && !active.depende_validacao_operacional ? '<button type="button" class="capacity-simulate-button" id="apply-simulation">Aplicar cenário</button>' : ""}</section>`
          : ""
      }`;
    $("#simulation-back").addEventListener("click", () =>
      openDrawer(sim.code, sim.weekStart),
    );
    $$("[data-suggestion]").forEach((button) =>
      button.addEventListener("click", () => {
        state.simulation.active =
          suggestions[Number(button.dataset.suggestion)];
        renderSimulation();
      }),
    );
    $("#simulation-manual").addEventListener("submit", async (event) => {
      event.preventDefault();
      const type = $("#simulation-type").value;
      const value = Number($("#simulation-value").value || 1);
      const action =
        type === "APOIO_SECUNDARIO"
          ? { tipo: type, codigo_etapa: sim.code, quantidade: value }
          : {
              tipo: type,
              entrega_id: Number($("#simulation-project").value),
              codigo_etapa: sim.code,
              [type === "DESLOCAR_ETAPA" ? "dias_uteis" : "pessoas"]: value,
            };
      try {
        state.simulation.active = await requestSimulation([action]);
        renderSimulation();
      } catch (error) {
        alert(error.message);
      }
    });
    $("#apply-simulation")?.addEventListener("click", async () => {
      try {
        await applySimulation(active);
      } catch (error) {
        alert(error.message);
      }
    });
  }
  function renderSimulation() {
    const sim = state.simulation;
    const suggestions = (sim.suggestions || []).map((scenario, index) => ({
      ...scenario,
      __index: index,
    }));
    const active = sim.active;
    const projects = sim.item?.projetos || [];
    const viable = suggestions.filter(
      (scenario) =>
        ["RESOLVE", "RESOLVE_COM_VALIDACAO", "RESOLVE_COM_APOIO"].includes(
          scenario.classificacao,
        ) &&
        Number(scenario.comparacao?.deficit_depois || 0) <= 0 &&
        Number(scenario.comparacao?.conflitos_depois || 0) === 0 &&
        !(scenario.novos_conflitos || []).length,
    );
    viable.sort(
      (a, b) =>
        Number(a.score_operacional ?? 999999) -
        Number(b.score_operacional ?? 999999),
    );
    const recommendation =
      viable.length &&
      (viable.length === 1 ||
        viable[0].score_operacional !== viable[1].score_operacional)
        ? { ...viable[0], __recommended: true }
        : null;
    const recommendationIndex = recommendation?.__index;
    const others = suggestions.filter(
      (scenario) =>
        scenario.__index !== recommendationIndex &&
        ![
          "SEM_GANHO",
          "TRANSFERE_PROBLEMA",
          "INVIAVEL",
          "PIORA_CENARIO",
        ].includes(scenario.classificacao),
    );
    const less = suggestions.filter(
      (scenario) =>
        !others.includes(scenario) && scenario.__index !== recommendationIndex,
    );
    const indicators = `<aside class="capacity-decision-indicators"><div class="capacity-drawer-section-title"><h3>Entenda os indicadores</h3></div><dl><div><dt>Déficit</dt><dd>Diferença entre demanda e capacidade disponível.</dd></div><div><dt>Conflitos</dt><dd>Dias ou períodos em que há déficit.</dd></div><div><dt>Margem</dt><dd>Dias úteis disponíveis até a entrega.</dd></div><div><dt>Custo operacional</dt><dd>Impacto necessário para executar a alternativa.</dd></div><div><dt>Confiança</dt><dd>Qualidade dos dados usados na simulação.</dd></div></dl></aside>`;
    const comparison = active
      ? `<section class="capacity-decision-comparison"><div class="capacity-drawer-section-title"><h3>Atual <span>×</span> Simulado</h3><span>${escapeHtml(labels[active.classificacao] || active.classificacao)}</span></div><div class="capacity-comparison-table"><span></span><b>Atual</b><b>Simulado</b><span>Déficit</span><strong>${number(active.comparacao?.deficit_antes)}</strong><strong>${number(active.comparacao?.deficit_depois)}</strong><span>Conflitos</span><strong>${number(active.comparacao?.conflitos_antes)}</strong><strong>${number(active.comparacao?.conflitos_depois)}</strong><span>Projetos afetados</span><strong colspan="2">${(active.planos_afetados || []).map((plan) => escapeHtml(plan.obra)).join(", ") || "Nenhum"}</strong></div>${active.planos_afetados?.length && !active.depende_validacao_operacional ? '<button type="button" class="capacity-simulate-button" id="apply-simulation">Aplicar cenário confirmado</button>' : ""}</section>`
      : "";
    $("#drawer-content").innerHTML =
      `<div class="capacity-decision-layout"><main class="capacity-decision-main"><button type="button" class="capacity-simulation-back" id="simulation-back"><i class="fa-solid fa-arrow-left"></i> Voltar ao conflito</button><p class="capacity-drawer-eyebrow">Sandbox de decisão · nada foi alterado</p><h2>${escapeHtml(sim.stage.nome_painel || sim.stage.etapa)} · ${formatRange(sim.weekStart)}</h2><section class="capacity-decision-conflict"><div class="capacity-decision-conflict-metrics"><div><small>Déficit atual</small><strong class="is-negative">−${number(sim.item.deficit_maximo)} pessoa(s)</strong></div><div><small>Pico de demanda</small><strong>${number(sim.item.pico_demanda)} pessoa(s)</strong></div><div><small>Capacidade principal</small><strong>${number(sim.item.capacidade_principal_referencia)} pessoa(s)</strong></div><div><small>Apoio elegível</small><strong>${number(sim.item.capacidade_secundaria_referencia)} pessoa(s)</strong></div></div><p>${projects.length > 1 ? `${projects.length} projetos competem pela mesma etapa neste período.` : "A demanda planejada ultrapassa a capacidade disponível neste período."}</p><a href="#capacity-timeline">Ver linha do tempo do conflito</a></section>${recommendation ? `<section class="capacity-decision-section"><h3 class="capacity-decision-heading is-recommended"><i class="fa-solid fa-circle-check"></i> Recomendação</h3>${scenarioSummary(recommendation)}</section>` : `<section class="capacity-decision-section"><h3 class="capacity-decision-heading"><i class="fa-solid fa-scale-balanced"></i> Melhores alternativas encontradas</h3><p class="capacity-decision-lead">Não há uma alternativa claramente superior; compare os impactos antes de decidir.</p></section>`}<section class="capacity-decision-section"><h3 class="capacity-decision-heading"><i class="fa-solid fa-filter"></i> Outras alternativas <span>${others.length}</span></h3><div class="capacity-scenarios">${scenarioGroupMarkup(others) || '<p class="capacity-no-details">Nenhuma alternativa adicional foi encontrada.</p>'}</div></section><details class="capacity-decision-less"><summary><i class="fa-solid fa-flask"></i> Soluções menos eficientes <span>${less.length}</span><small>Alternativas com maior impacto ou sem benefício adicional.</small></summary><div class="capacity-scenarios">${scenarioGroupMarkup(less) || '<p class="capacity-no-details">Nenhuma.</p>'}</div></details><section class="capacity-decision-manual"><div class="capacity-drawer-section-title"><h3><i class="fa-solid fa-flask"></i> Criar simulação manual</h3><span>não salva</span></div><p>Não encontrou a alternativa que precisa? Crie sua própria simulação.</p><form id="simulation-manual" class="capacity-simulation-form"><label>Aplicar em<select id="simulation-project">${projects.map((project) => `<option value="${project.entrega_id}">${escapeHtml(project.obra)} · margem ${signed(project.margem_dias_uteis)}d</option>`).join("")}</select></label><label>Tipo de intervenção<select id="simulation-type"><option value="DESLOCAR_ETAPA">Deslocar etapa</option><option value="ALTERAR_CAPACIDADE">Alterar pessoas</option><option value="APOIO_SECUNDARIO">Apoio secundário</option><option value="CAPACIDADE_EXTRAORDINARIA">Capacidade extra · interna</option><option value="CAPACIDADE_EXTERNA">Freelancer · externa</option></select></label><label>Valor<input id="simulation-value" type="number" min="1" max="20" value="1"></label><label>Data inicial<input id="simulation-date-start" type="date" value="${sim.weekStart}"></label><label>Data final<input id="simulation-date-end" type="date" value="${addDays(sim.weekStart, 5)}"></label><button type="submit" class="capacity-simulate-button">Simular cenário manual</button></form></section><p class="capacity-decision-footnote"><i class="fa-solid fa-circle-info"></i> O planejamento usa dias úteis como calendário padrão. Finais de semana só entram quando adicionados como capacidade extraordinária.</p>${comparison}</main></div>`;
    $("#simulation-back").addEventListener("click", () =>
      openDrawer(sim.code, sim.weekStart),
    );
    $$(`[data-suggestion]`).forEach((button) =>
      button.addEventListener("click", () => {
        state.simulation.active =
          suggestions[Number(button.dataset.suggestion)];
        renderSimulation();
      }),
    );
    $("#simulation-manual").addEventListener("submit", async (event) => {
      event.preventDefault();
      const type = $("#simulation-type").value;
      const value = Number($("#simulation-value").value || 1);
      const base = { tipo: type, codigo_etapa: sim.code };
      const action =
        type === "APOIO_SECUNDARIO"
          ? { ...base, quantidade: value }
          : type === "CAPACIDADE_EXTRAORDINARIA"
            ? {
                ...base,
                data: $("#simulation-date-start").value,
                pessoas: value,
              }
            : type === "CAPACIDADE_EXTERNA"
              ? {
                  ...base,
                  data_inicio: $("#simulation-date-start").value,
                  data_fim: $("#simulation-date-end").value,
                  pessoas: value,
                }
              : {
                  ...base,
                  entrega_id: Number($("#simulation-project").value),
                  [type === "DESLOCAR_ETAPA" ? "dias_uteis" : "pessoas"]: value,
                };
      try {
        state.simulation.active = await requestSimulation([action]);
        renderSimulation();
      } catch (error) {
        alert(error.message);
      }
    });
    $("#apply-simulation")?.addEventListener("click", async () => {
      try {
        await applySimulation(active);
      } catch (error) {
        alert(error.message);
      }
    });
  }
  async function openSimulation() {
    const selected = state.selected;
    const stage = stageByCode(selected?.code);
    const item = weekly(stage, selected?.weekStart);
    if (!stage || !item) return;
    state.simulation = {
      code: selected.code,
      weekStart: selected.weekStart,
      stage,
      item,
      suggestions: [],
      active: null,
    };
    $("#capacity-drawer").classList.add("is-simulation");
    renderSimulation();
    try {
      state.simulation.suggestions =
        (await requestSimulation([], true)).sugestoes || [];
      renderSimulation();
    } catch (error) {
      $("#drawer-content").insertAdjacentHTML(
        "beforeend",
        `<p class="capacity-no-details is-negative">${escapeHtml(error.message)}</p>`,
      );
    }
  }

  async function load() {
    const loading = $("#capacity-loading");
    const panel = $("#capacity-panel");
    loading.hidden = false;
    panel.classList.add("is-loading");
    const query = new URLSearchParams({
      inicio: $("#period-start").value,
      fim: $("#period-end").value,
    });
    try {
      const response = await fetch(`${document.body.dataset.apiUrl}?${query}`, {
        headers: { Accept: "application/json" },
      });
      const payload = await response.json();
      if (!response.ok || !payload.success)
        throw new Error(
          payload.message || "Não foi possível consultar a capacidade.",
        );
      state.data = payload.capacidade;
      populateStages();
      render();
    } catch (error) {
      state.data = null;
      $("#capacity-empty").hidden = false;
      $("#capacity-empty").innerHTML =
        `<i class="fa-solid fa-circle-exclamation"></i><strong>Não foi possível carregar o painel.</strong><span>${escapeHtml(error.message)}</span>`;
      $("#capacity-heatmap").innerHTML = "";
    } finally {
      loading.hidden = true;
      panel.classList.remove("is-loading");
    }
  }
  function wire() {
    $$("[data-weeks]").forEach((button) =>
      button.addEventListener("click", () => {
        state.weeks = Number(button.dataset.weeks);
        $$("[data-weeks]").forEach((item) =>
          item.classList.toggle("is-active", item === button),
        );
        setPeriod($("#period-start").value, state.weeks);
        load();
      }),
    );
    $("#period-prev").addEventListener("click", () => {
      setPeriod(addDays($("#period-start").value, -state.weeks * 7));
      load();
    });
    $("#period-next").addEventListener("click", () => {
      setPeriod(addDays($("#period-start").value, state.weeks * 7));
      load();
    });
    $("#period-start").addEventListener("change", () => {
      setPeriod($("#period-start").value);
      load();
    });
    $("#period-end").addEventListener("change", () => {
      state.weeks = Math.max(
        1,
        Math.round(
          (daysBetween($("#period-start").value, $("#period-end").value) + 1) /
            7,
        ),
      );
      load();
    });
    $("#filter-stage").addEventListener("change", render);
    $("#filter-status").addEventListener("change", render);
    $$("[data-view]").forEach((button) =>
      button.addEventListener("click", () => {
        state.view = button.dataset.view;
        $$("[data-view]").forEach((item) =>
          item.classList.toggle("is-active", item === button),
        );
        render();
      }),
    );
    $("#drawer-close").addEventListener("click", closeDrawer);
    $("#capacity-scrim").addEventListener("click", closeDrawer);
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") closeDrawer();
    });
  }
  setPeriod(today(), state.weeks);
  wire();
  load();
})();
