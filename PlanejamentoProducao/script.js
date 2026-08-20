(() => {
  const body = document.body;
  const obraId = Number(body.dataset.obraId);
  const endpoint = "preview.php";
  const state = {
    people: {},
    plan: null,
    loading: false,
    selected: null,
    scale: "week",
    scenarios: [],
  };
  const $ = (selector) => document.querySelector(selector);
  const formatDate = (value) =>
    value
      ? new Intl.DateTimeFormat("pt-BR", { timeZone: "UTC" }).format(
          new Date(`${value}T12:00:00Z`),
        )
      : "—";
  const formatMargin = (value) =>
    value === null
      ? "Sem prazo R00"
      : `${value >= 0 ? "+" : ""}${value} dias úteis`;
  const stageClass = (code) =>
    code.includes("CADERNO")
      ? "stage-prep"
      : code.includes("MODELAGEM")
        ? "stage-model"
        : code === "COMPOSICAO"
          ? "stage-compose"
          : code.includes("FINALIZACAO")
            ? "stage-final"
            : "stage-post";
  const stageIcon = (code) =>
    code.includes("CADERNO")
      ? "fa-book-open"
      : code === "MODELAGEM_FACHADA"
        ? "fa-cube"
        : code.includes("MODELAGEM")
          ? "fa-cubes"
          : code === "COMPOSICAO"
            ? "fa-layer-group"
            : code === "FINALIZACAO_GLOBAL"
              ? "fa-globe"
              : code.includes("FINALIZACAO")
                ? "fa-circle-check"
                : "fa-star";
  const daysBetween = (start, end) =>
    Math.max(
      1,
      Math.round(
        (new Date(`${end}T12:00:00Z`) - new Date(`${start}T12:00:00Z`)) /
          86400000,
      ),
    );

  function range(plan) {
    const dates = plan.etapas
      .filter((stage) => stage.inicio && stage.limite)
      .flatMap((stage) => [stage.inicio, stage.limite]);
    dates.push(
      plan.data_inicio,
      plan.data_entrega || plan.fim_previsto,
      plan.data_hoje,
    );
    dates.sort();
    const ultimoMarco = new Date(`${dates.at(-1)}T12:00:00Z`);
    ultimoMarco.setUTCDate(ultimoMarco.getUTCDate() + 7);
    return {
      start: dates[0],
      end: ultimoMarco.toISOString().slice(0, 10),
      days: daysBetween(dates[0], ultimoMarco.toISOString().slice(0, 10)),
    };
  }
  function position(date, timeline) {
    return Math.max(
      0,
      Math.min(100, (daysBetween(timeline.start, date) / timeline.days) * 100),
    );
  }
  function isChanged(stage) {
    const previous = state.plan?.etapas?.find(
      (item) => item.codigo === stage.codigo,
    );
    return (
      previous &&
      (previous.duracao_dias_uteis !== stage.duracao_dias_uteis ||
        previous.limite !== stage.limite ||
        previous.pessoas_alocadas !== stage.pessoas_alocadas)
    );
  }
  function relationship(plan, code) {
    const active = plan.etapas.filter((stage) => !stage.nao_aplicavel);
    const related = new Set([code]);
    const visitParents = (target) => {
      const stage = active.find((item) => item.codigo === target);
      (stage?.dependencias || []).forEach((dependency) => {
        if (!related.has(dependency)) {
          related.add(dependency);
          visitParents(dependency);
        }
      });
    };
    const visitChildren = (target) =>
      active
        .filter((stage) => (stage.dependencias || []).includes(target))
        .forEach((stage) => {
          if (!related.has(stage.codigo)) {
            related.add(stage.codigo);
            visitChildren(stage.codigo);
          }
        });
    visitParents(code);
    visitChildren(code);
    return related;
  }

  function renderSummary(plan) {
    $("[data-plan-title]").textContent =
      plan.obra?.nomenclatura || `Obra #${plan.obra_id}`;
    $("#summary-start").textContent = formatDate(plan.data_inicio);
    $("#summary-today").textContent = formatDate(plan.data_hoje);
    $("#summary-due").textContent = formatDate(plan.data_entrega);
    $("#summary-finish").textContent = formatDate(plan.fim_previsto);
    const margin = $("#summary-margin");
    margin.classList.toggle("negative", Number(plan.margem_dias_uteis) < 0);
    margin.querySelector("strong").textContent = formatMargin(
      plan.margem_dias_uteis,
    );
    const card = $("#plan-status-card");
    const labels = {
      VIAVEL: "Planejamento viável",
      ATENCAO: "Atenção à margem",
      INVIAVEL: "Inviável",
      DESATUALIZADO: "Plano desatualizado",
      SEM_PREVISAO_CONFIAVEL: "Histórico insuficiente",
    };
    card.className = `planning-hero-status is-${String(plan.status_plano || "").toLowerCase()}`;
    card.innerHTML = `<span>Status do plano</span><strong>${labels[plan.status_plano] || plan.status_plano}</strong><small id="plan-exception-count" hidden></small>`;
  }
  function detailMarkup(stage) {
    const metric = stage.metrica || {};
    const hasHistory =
      typeof metric.metodo === "string" && metric.metodo.startsWith("MEDIANA");
    const history = hasHistory
      ? `<div><span>Amostra</span><strong>${metric.amostra_ciclos_validos} ciclos</strong></div><div><span>Confiança</span><strong>${metric.confianca}</strong></div><div><span>Produtividade</span><strong>${metric.tarefas_por_dia_util_pessoa} tarefa/dia/pessoa</strong></div>`
      : `<div><span>Origem</span><strong>${metric.origem || "Marco calculado"}</strong></div>`;
    return `<div class="planning-detail-grid"><div><span>Volume</span><strong>${stage.volume} tarefas</strong></div><div><span>Pessoas</span><strong>${stage.pessoas_alocadas || "—"}</strong></div><div><span>Duração</span><strong>${stage.duracao_dias_uteis} dias úteis</strong></div><div><span>Limite</span><strong>${formatDate(stage.limite)}</strong></div>${history}</div><p class="planning-formula">${stage.formula || "Marco global: maior data-limite entre os pools de Finalização."}</p>`;
  }
  function capacity(stage) {
    if (!stage.capacidade_editavel)
      return `<span class="planning-fixed" title="${stage.formula || ""}">—</span>`;
    return `<div class="planning-capacity" aria-label="Pessoas alocadas em ${stage.nome}"><button data-capacity="-1" data-stage="${stage.codigo}" type="button" aria-label="Remover uma pessoa">−</button><output>${stage.pessoas_alocadas}</output><button data-capacity="1" data-stage="${stage.codigo}" type="button" aria-label="Adicionar uma pessoa">+</button></div>`;
  }
  function renderStageRows(plan) {
    const list = $("#stage-list");
    const active = plan.etapas.filter((stage) => !stage.nao_aplicavel);
    const related = state.selected ? relationship(plan, state.selected) : null;
    const dependencyLabel = (stage) => {
      const dependencies = stage.dependencias || [];
      if (!dependencies.length) return "—";
      return dependencies.length === 1
        ? String(
            active.findIndex((item) => item.codigo === dependencies[0]) + 1,
          )
        : dependencies
            .map((code) => active.findIndex((item) => item.codigo === code) + 1)
            .join(", ");
    };
    const compactDate = (date) => formatDate(date).slice(0, 5);
    list.innerHTML = active
      .map(
        (
          stage,
          index,
        ) => `<article class="planning-stage-row ${stage.caminho_critico ? "is-critical" : ""} ${state.selected === stage.codigo ? "is-selected" : ""} ${related && !related.has(stage.codigo) ? "is-dim" : ""} ${isChanged(stage) ? "planning-changed" : ""}" data-stage-row="${stage.codigo}">
      <span class="planning-cell planning-index">${index + 1}</span>
      <button type="button" class="planning-stage-name ${stageClass(stage.codigo)}" data-detail="${stage.codigo}" aria-label="Ver cálculo de ${stage.nome}"><i class="fa-solid ${stageIcon(stage.codigo)}" aria-hidden="true"></i><span>${stage.nome}${stage.caminho_critico ? ' <em class="fa-solid fa-bolt" title="Caminho crítico"></em>' : ""}</span></button>
      <span class="planning-cell">${stage.volume} imgs</span>
      <span class="planning-cell planning-duration">${stage.duracao_dias_uteis ?? "—"} dias</span>
      <span class="planning-cell planning-date" title="${formatDate(stage.inicio)}">${compactDate(stage.inicio)}</span>
      <span class="planning-cell planning-date" title="${formatDate(stage.limite)}">${compactDate(stage.limite)}</span>
      ${capacity(stage)}
      <button type="button" class="planning-dependencies" data-detail="${stage.codigo}" title="Depende de: ${(stage.dependencias || []).map((code) => active.find((item) => item.codigo === code)?.nome || code).join(", ") || "nenhuma etapa"}" aria-label="Ver dependências de ${stage.nome}">${dependencyLabel(stage)}</button>
    </article>`,
      )
      .join("");
  }
  const parseDate = (value) => new Date(`${value}T12:00:00Z`);
  const isoDate = (date) => date.toISOString().slice(0, 10);
  const addCalendarDays = (date, days) =>
    new Date(date.getTime() + days * 86400000);
  const monthName = (date) =>
    new Intl.DateTimeFormat("pt-BR", {
      month: "long",
      year: "numeric",
      timeZone: "UTC",
    })
      .format(date)
      .replace(/^./, (letter) => letter.toUpperCase());
  function timelineScale(plan, timeline) {
    const head = $("#timeline-head");
    const start = parseDate(timeline.start);
    const end = parseDate(timeline.end);
    const months = [];
    let cursor = new Date(
      Date.UTC(start.getUTCFullYear(), start.getUTCMonth(), 1),
    );
    while (cursor <= end) {
      const next = new Date(
        Date.UTC(cursor.getUTCFullYear(), cursor.getUTCMonth() + 1, 1),
      );
      const visibleStart = cursor < start ? start : cursor;
      const visibleEnd =
        addCalendarDays(next, -1) > end ? end : addCalendarDays(next, -1);
      months.push(
        `<span class="timeline-month" style="left:${position(isoDate(visibleStart), timeline)}%;width:${Math.max(1, position(isoDate(visibleEnd), timeline) - position(isoDate(visibleStart), timeline))}%">${monthName(cursor)}</span>`,
      );
      cursor = next;
    }
    const step = state.scale === "month" ? 28 : state.scale === "day" ? 1 : 7;
    const ticks = [];
    for (let tick = start; tick <= end; tick = addCalendarDays(tick, step)) {
      const tickEnd = addCalendarDays(tick, step - 1);
      const label =
        state.scale === "month"
          ? monthName(tick)
          : state.scale === "day"
            ? String(tick.getUTCDate()).padStart(2, "0")
            : `${String(tick.getUTCDate()).padStart(2, "0")}–${String(Math.min(tickEnd.getUTCDate(), 31)).padStart(2, "0")}`;
      ticks.push(
        `<span class="timeline-tick" style="left:${position(isoDate(tick), timeline)}%">${label}</span>`,
      );
    }
    head.innerHTML = `<div class="timeline-months">${months.join("")}</div><div class="timeline-ticks is-${state.scale}">${ticks.join("")}</div>`;
    document
      .querySelectorAll("[data-scale]")
      .forEach((button) =>
        button.classList.toggle(
          "is-active",
          button.dataset.scale === state.scale,
        ),
      );
  }
  function renderTimeline(plan) {
    const target = $("#timeline");
    const timeline = range(plan);
    const active = plan.etapas.filter(
      (stage) => !stage.nao_aplicavel && stage.inicio && stage.limite,
    );
    const related = state.selected ? relationship(plan, state.selected) : null;
    timelineScale(plan, timeline);
    target.innerHTML = `<div class="timeline-marker marker-today" data-label="Hoje" data-date="${formatDate(plan.data_hoje)}" style="left:${position(plan.data_hoje, timeline)}%"></div>${plan.data_entrega ? `<div class="timeline-marker marker-due" data-label="Entrega" data-date="${formatDate(plan.data_entrega)}" style="left:${position(plan.data_entrega, timeline)}%"></div>` : ""}${plan.fim_previsto ? `<div class="timeline-marker marker-finish" data-label="Fim previsto" data-date="${formatDate(plan.fim_previsto)}" style="left:${position(plan.fim_previsto, timeline)}%"></div>` : ""}<svg class="planning-connectors" aria-hidden="true"></svg>${active
      .map((stage, index) => {
        const left = position(stage.inicio, timeline);
        const right = position(stage.limite, timeline);
        return `<div class="planning-bar-row ${state.selected === stage.codigo ? "is-selected" : ""} ${related && !related.has(stage.codigo) ? "is-dim" : ""}" data-bar-row="${stage.codigo}" style="top:${index * 58}px"><button type="button" data-detail="${stage.codigo}" class="planning-bar ${stageClass(stage.codigo)} ${stage.caminho_critico ? "is-critical" : ""}" style="left:${left}%;width:${Math.max(1.7, right - left)}%" aria-label="${stage.nome}: ${formatDate(stage.inicio)} até ${formatDate(stage.limite)}"><span>${stage.nome}</span></button></div>`;
      })
      .join("")}`;
    requestAnimationFrame(() => drawConnectors(plan, timeline));
  }
  function bindDetailTriggers(plan) {
    document.querySelectorAll("[data-detail]").forEach((button) => {
      button.addEventListener("click", () => {
        const stage = plan.etapas.find(
          (item) => item.codigo === button.dataset.detail,
        );
        if (stage) selectStage(stage);
      });
    });
  }
  function drawConnectors(plan, timeline) {
    const svg = $(".planning-connectors");
    const target = $("#timeline");
    if (!svg || !target) return;
    svg.setAttribute(
      "viewBox",
      `0 0 ${target.clientWidth} ${target.clientHeight}`,
    );
    svg.setAttribute("width", target.clientWidth);
    svg.setAttribute("height", target.clientHeight);
    const active = plan.etapas.filter(
      (stage) => !stage.nao_aplicavel && stage.inicio && stage.limite,
    );
    const map = Object.fromEntries(
      active.map((stage, index) => [stage.codigo, { stage, index }]),
    );
    svg.innerHTML = active
      .flatMap(({ codigo, dependencias }, index) =>
        (dependencias || [])
          .filter((dependency) => map[dependency])
          .map((dependency) => {
            const from = map[dependency];
            const x1 =
              (position(from.stage.limite, timeline) / 100) *
              target.clientWidth;
            const x2 =
              (position(active[index].inicio, timeline) / 100) *
              target.clientWidth;
            const y1 = from.index * 58 + 29;
            const y2 = index * 58 + 29;
            const mid = Math.max(x1 + 10, (x1 + x2) / 2);
            return `<path class="planning-connector" d="M ${x1} ${y1} H ${mid} V ${y2} H ${x2}"/>`;
          }),
      )
      .join("");
  }
  function renderExceptions(plan) {
    const badge = $("#plan-exception-count");
    if (!badge) return;
    if (!plan.excecoes?.length) {
      badge.hidden = true;
      badge.textContent = "";
      return;
    }
    badge.hidden = false;
    badge.textContent = `${plan.excecoes.length} exceção${plan.excecoes.length === 1 ? "" : "ões"}`;
    badge.title = plan.excecoes
      .map(
        (item) =>
          `${item.codigo.replaceAll("_", " ")}${item.imagem_id ? ` · Imagem ${item.imagem_id}` : ""}`,
      )
      .join("\n");
  }
  function bottleneck(plan) {
    const active = plan.etapas.filter((stage) => !stage.nao_aplicavel);
    const global = active.find(
      (stage) => stage.codigo === "FINALIZACAO_GLOBAL",
    );
    const gate = (global?.dependencias || [])
      .map((code) => active.find((stage) => stage.codigo === code))
      .filter(Boolean)
      .sort((a, b) => String(b.limite).localeCompare(String(a.limite)))[0];
    return (
      gate ||
      active
        .filter(
          (stage) => stage.caminho_critico && stage.codigo !== "POS_PRODUCAO",
        )
        .sort(
          (a, b) => (b.duracao_dias_uteis || 0) - (a.duracao_dias_uteis || 0),
        )[0] ||
      null
    );
  }
  function scenarioResultText(plan) {
    if (plan.margem_dias_uteis === null) return "Sem prazo R00";
    return plan.margem_dias_uteis >= 0
      ? `Cumpre em ${formatDate(plan.fim_previsto)}`
      : `Atraso de ${Math.abs(plan.margem_dias_uteis)} dias`;
  }
  async function calculateScenario(people) {
    const params = new URLSearchParams({
      obra_id: obraId,
      pessoas: JSON.stringify(people),
    });
    const response = await fetch(`${endpoint}?${params}`);
    const plan = await response.json();
    if (!response.ok || plan.erro)
      throw new Error(plan.erro || "Não foi possível simular o cenário.");
    return plan;
  }
  async function renderScenarioSuggestions(plan) {
    const target = $("#scenario-list");
    if (!target) return;
    if (plan.status_plano === "VIAVEL") {
      state.scenarios = [];
      target.innerHTML = `<span class="planning-scenario-empty">A capacidade atual já atende à entrega.</span>`;
      return;
    }
    const priority = [
      "COMPOSICAO",
      "FINALIZACAO_INTERNA",
      "MODELAGEM_INTERNA",
      "CADERNO_FILTRO",
      "POS_PRODUCAO",
    ];
    const candidates = priority
      .map((code) =>
        plan.etapas.find(
          (stage) =>
            stage.codigo === code &&
            !stage.nao_aplicavel &&
            stage.capacidade_editavel,
        ),
      )
      .filter(Boolean)
      .slice(0, 2);
    if (!candidates.length) {
      target.innerHTML = `<span class="planning-scenario-empty">Use os controles de pessoas para testar a capacidade.</span>`;
      return;
    }
    const requestId = `${plan.fim_previsto}|${JSON.stringify(state.people)}`;
    state.scenarioRequest = requestId;
    target.innerHTML = `<span class="planning-scenario-empty">Calculando cenários reais…</span>`;
    const scenarios = await Promise.all(
      candidates.map(async (stage) => {
        const people = {
          ...state.people,
          [stage.codigo]: (stage.pessoas_alocadas || 1) + 1,
        };
        try {
          const simulated = await calculateScenario(people);
          return { stage, plan: simulated, people };
        } catch (_) {
          return null;
        }
      }),
    );
    if (state.scenarioRequest !== requestId) return;
    state.scenarios = scenarios.filter(Boolean);
    target.innerHTML = state.scenarios.length
      ? state.scenarios
          .map(
            ({ stage, plan: simulated }) =>
              `<button type="button" class="planning-scenario" data-scenario="${stage.codigo}"><span>Aumentar ${stage.nome} para ${stage.pessoas_alocadas + 1} pessoas</span><b class="${simulated.margem_dias_uteis >= 0 ? "is-good" : ""}">${scenarioResultText(simulated)}</b></button>`,
          )
          .join("")
      : `<span class="planning-scenario-empty">Não foi possível calcular sugestões agora.</span>`;
    document.querySelectorAll("[data-scenario]").forEach((button) =>
      button.addEventListener("click", () => {
        const scenario = state.scenarios.find(
          (item) => item.stage.codigo === button.dataset.scenario,
        );
        if (scenario) showScenario(scenario);
      }),
    );
  }
  function renderDiagnosis(plan) {
    const margin = plan.margem_dias_uteis;
    const diagnosis = $("#diagnosis-summary");
    const detail = $("#diagnosis-bottleneck");
    const goal = $("#diagnosis-goal");
    if (margin !== null && margin < 0)
      diagnosis.innerHTML = `<b>Plano inviável:</b> previsão de conclusão ${Math.abs(margin)} dias úteis após a entrega prevista.`;
    else if (margin !== null)
      diagnosis.innerHTML = `<b>Plano viável:</b> margem operacional de ${margin} dias úteis.`;
    else diagnosis.textContent = "Planejamento sem prazo R00 para comparação.";
    const gate = bottleneck(plan);
    detail.textContent = gate
      ? `Gargalo atual: ${gate.nome} — determina o próximo marco do caminho crítico.`
      : "";
    goal.textContent = plan.data_entrega
      ? `Cenários para ${formatDate(plan.data_entrega)}:`
      : "Simulações de capacidade:";
    renderScenarioSuggestions(plan);
  }
  function detail(stage) {
    $("#detail-content").innerHTML =
      `<p class="planning-eyebrow">Como foi calculado?</p><h2 id="detail-title">${stage.nome}</h2><p>${stage.caminho_critico ? "Esta etapa está no caminho crítico do plano." : "Esta etapa não determina o fim previsto no cenário atual."}</p>${detailMarkup(stage)}`;
    $("#planning-detail").classList.add("is-open");
    $("#planning-detail").setAttribute("aria-hidden", "false");
    $("#planning-scrim").hidden = false;
  }
  function selectStage(stage) {
    state.selected = state.selected === stage.codigo ? null : stage.codigo;
    renderStageRows(state.plan);
    renderTimeline(state.plan);
    bindDetailTriggers(state.plan);
    if (state.selected) detail(stage);
  }
  function showScenario(scenario) {
    const simulated = scenario.plan;
    $("#detail-content").innerHTML =
      `<p class="planning-eyebrow">Simulação real · não salva</p><h2 id="detail-title">${scenario.stage.nome} com ${scenario.stage.pessoas_alocadas + 1} pessoas</h2><p>${scenarioResultText(simulated)}. O motor recalculou dependências, fim previsto e margem usando a mesma regra do plano.</p><div class="planning-detail-grid"><div><span>Fim previsto</span><strong>${formatDate(simulated.fim_previsto)}</strong></div><div><span>Margem</span><strong>${formatMargin(simulated.margem_dias_uteis)}</strong></div></div>`;
    $("#planning-detail").classList.add("is-open");
    $("#planning-detail").setAttribute("aria-hidden", "false");
    $("#planning-scrim").hidden = false;
  }
  async function load() {
    if (state.loading) return;
    state.loading = true;
    const before = state.plan;
    const params = new URLSearchParams({
      obra_id: obraId,
      pessoas: JSON.stringify(state.people),
    });
    try {
      const response = await fetch(`${endpoint}?${params}`);
      const plan = await response.json();
      if (!response.ok || plan.erro)
        throw new Error(
          plan.erro || "Não foi possível calcular o planejamento.",
        );
      state.plan = plan;
      renderSummary(plan);
      renderStageRows(plan);
      renderTimeline(plan);
      renderExceptions(plan);
      bindDetailTriggers(plan);
      renderDiagnosis(plan);
      if (before) $("#planning-board").classList.add("planning-changed");
    } catch (error) {
      $("#plan-status-card").innerHTML =
        `<span>Não foi possível carregar</span><strong>${error.message}</strong>`;
    } finally {
      state.loading = false;
    }
  }
  document.addEventListener("click", (event) => {
    const capacityButton = event.target.closest("[data-capacity]");
    if (capacityButton) {
      const code = capacityButton.dataset.stage;
      state.people[code] = Math.max(
        1,
        Number(state.people[code] || 1) +
          Number(capacityButton.dataset.capacity),
      );
      load();
      return;
    }
    const scaleButton = event.target.closest("[data-scale]");
    if (scaleButton) {
      state.scale = scaleButton.dataset.scale;
      renderTimeline(state.plan);
      bindDetailTriggers(state.plan);
    }
  });
  $("#reset-simulation").addEventListener("click", () => {
    state.people = {};
    load();
  });
  const closeDetail = () => {
    $("#planning-detail").classList.remove("is-open");
    $("#planning-detail").setAttribute("aria-hidden", "true");
    $("#planning-scrim").hidden = true;
  };
  $("#detail-close").addEventListener("click", closeDetail);
  $("#planning-scrim").addEventListener("click", closeDetail);
  $("#theme-toggle").addEventListener("click", () => {
    body.classList.toggle("light");
    $("#theme-toggle").innerHTML = body.classList.contains("light")
      ? '<i class="fa-solid fa-moon"></i>'
      : '<i class="fa-solid fa-sun"></i>';
  });
  $("#show-scenarios").addEventListener("click", () => {
    if (!state.scenarios.length) return;
    const lines = state.scenarios
      .map(
        ({ stage, plan }) =>
          `<div><span>${stage.nome}: ${stage.pessoas_alocadas} → ${stage.pessoas_alocadas + 1} pessoas</span><strong>${scenarioResultText(plan)}</strong></div>`,
      )
      .join("");
    $("#detail-content").innerHTML =
      `<p class="planning-eyebrow">Simulações reais · não salvas</p><h2 id="detail-title">Cenários para a entrega</h2><div class="planning-detail-grid">${lines}</div>`;
    $("#planning-detail").classList.add("is-open");
    $("#planning-detail").setAttribute("aria-hidden", "false");
    $("#planning-scrim").hidden = false;
  });
  window.addEventListener(
    "resize",
    () => state.plan && renderTimeline(state.plan),
  );
  load();
})();
