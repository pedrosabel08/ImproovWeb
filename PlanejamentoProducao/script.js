(() => {
  const body = document.body;
  const obraId = Number(body.dataset.obraId);
  const initialEntregaId = Number(body.dataset.entregaId || 0);
  const endpoint = "preview.php";
  const state = {
    people: {},
    plan: null,
    loading: false,
    reloadQueued: false,
    selected: null,
    scale: "week",
    scenarios: [],
    entregaId: initialEntregaId,
    replanning: false,
    saving: false,
  };
  const $ = (selector) => document.querySelector(selector);
  const escapeHtml = (value) =>
    String(value ?? "").replace(
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
      : code === "MODELAGEM_FACHADA"
        ? "stage-model-fachada"
        : code === "MODELAGEM_INTERNA"
          ? "stage-model-interna"
          : code === "COMPOSICAO"
            ? "stage-compose"
            : code === "FINALIZACAO_GLOBAL"
              ? "stage-final-global"
              : code === "FINALIZACAO_INTERNA"
                ? "stage-final-interna"
                : code === "FINALIZACAO_EXTERNA" ||
                    code === "FINALIZACAO_PLANTA"
                  ? "stage-final-externa"
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

  const motionReduced = () =>
    window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false;
  const stageMap = (plan) =>
    new Map((plan?.etapas || []).map((stage) => [stage.codigo, stage]));
  const activeStages = (plan) =>
    (plan?.etapas || []).filter((stage) => !stage.nao_aplicavel);
  const executionMap = (plan) =>
    new Map(
      (plan?.execucao?.etapas || []).map((stage) => [stage.codigo, stage]),
    );
  const executionStage = (plan, code) => executionMap(plan).get(code) || null;
  const operationalData = (plan) => plan?.projecao_operacional || null;
  const isCompleted = (plan) => plan?.ciclo_vida?.estado === "CONCLUIDO";
  const deviationLabel = (days) =>
    days == null ? "—" : `${days > 0 ? "+" : ""}${days}d`;
  const deviationClass = (days) =>
    days == null || days === 0
      ? "is-on-time"
      : days > 0
        ? "is-late"
        : "is-early";
  const observedStage = (plan, stage) => {
    const execution = executionStage(plan, stage.codigo);
    const closed = execution?.execucao === "CONCLUIDA";
    const actual = closed ? execution.conclusao_real : null;
    const projection =
      !closed && !isCompleted(plan)
        ? operationalStage(plan, stage.codigo)
        : null;
    return {
      actual: Boolean(actual),
      closed,
      date: actual || projection?.fim_operacional_projetado || null,
      deviation: actual
        ? execution.desvio_real_dias_uteis
        : projection?.desvio_plano_vigente_dias_uteis,
      label: actual
        ? "Concluída"
        : isCompleted(plan) || closed
          ? "Sem registro"
          : projection?.fim_operacional_projetado
            ? "Projeção"
            : "Não calculável",
    };
  };
  const deliveryResultText = (plan) => {
    const days = plan.ciclo_vida?.antecedencia_prazo_dias_corridos;
    if (days == null) return "Prazo da R00 não informado";
    return days === 0
      ? "Entregue na data do prazo"
      : `Entregue ${Math.abs(days)} dia${Math.abs(days) === 1 ? "" : "s"} corrido${Math.abs(days) === 1 ? "" : "s"} ${days > 0 ? "antes" : "após"} o prazo`;
  };
  const operationalStage = (plan, code) =>
    (operationalData(plan)?.etapas || []).find(
      (stage) => stage.codigo === code,
    ) || null;
  const operationalLabel = (value) =>
    ({
      NO_PLANO: "No plano",
      MARGEM_CONSUMIDA: "Margem consumida",
      ATRASO_PROJETADO: "Atraso projetado",
      FILA_NAO_CALCULAVEL: "Não calculável",
      BLOQUEADO: "Bloqueada",
    })[value] ||
    value ||
    "—";
  const executionLabel = (value) =>
    ({
      NO_PRAZO: "No prazo",
      ATENCAO: "Atenção",
      EM_RISCO: "Em risco",
      CONCLUIDA: "Concluída",
    })[value] ||
    value ||
    "Aguardando plano confirmado";
  const stageExecutionLabel = (value) =>
    ({
      NAO_INICIADA: "Não iniciada",
      EM_ANDAMENTO: "Em andamento",
      CONCLUIDA: "Concluída",
      NAO_APLICAVEL: "Não aplicável",
    })[value] ||
    value ||
    "—";

  function dateMotionDelay(value, plan) {
    if (!value || !plan?.data_hoje) return 0;
    if (value === plan.data_hoje) return 0;
    const dates = activeStages(plan)
      .flatMap((stage) => [stage.inicio, stage.limite])
      .concat([plan.data_hoje, plan.data_entrega, plan.fim_previsto])
      .filter(Boolean)
      .map((date) => Math.abs(daysBetween(plan.data_hoje, date)));
    const maxDistance = Math.max(...dates, 1);
    return Math.round(
      Math.min(
        220,
        (Math.abs(daysBetween(plan.data_hoje, value)) / maxDistance) * 220,
      ),
    );
  }

  function stageDepths(plan) {
    const map = stageMap(plan);
    const depths = new Map();
    const visit = (code, stack = new Set()) => {
      if (depths.has(code)) return depths.get(code);
      if (stack.has(code)) return 0;
      const stage = map.get(code);
      if (!stage) return 0;
      const nextStack = new Set(stack).add(code);
      const depth = Math.max(
        0,
        ...(stage.dependencias || []).map(
          (dependency) => visit(dependency, nextStack) + 1,
        ),
      );
      depths.set(code, depth);
      return depth;
    };
    activeStages(plan).forEach((stage) => visit(stage.codigo));
    return depths;
  }

  function planMotion(previous, next, reason = "recalculate") {
    const initial = !previous;
    const previousStages = stageMap(previous);
    const nextStages = stageMap(next);
    const changedCodes = new Set();
    const changedFields = new Map();
    const impactedCodes = new Set();
    const impactDepth = new Map();
    const children = new Map();

    activeStages(next).forEach((stage) => {
      (stage.dependencias || []).forEach((dependency) => {
        if (!children.has(dependency)) children.set(dependency, []);
        children.get(dependency).push(stage.codigo);
      });
    });

    if (!initial) {
      nextStages.forEach((stage, code) => {
        const before = previousStages.get(code);
        if (!before) {
          changedCodes.add(code);
          changedFields.set(code, new Set(["created"]));
          return;
        }
        const fields = [
          "pessoas_alocadas",
          "duracao_dias_uteis",
          "inicio",
          "limite",
          "volume",
          "caminho_critico",
          "nao_aplicavel",
        ].filter((field) => before[field] !== stage[field]);
        if (fields.length) {
          changedCodes.add(code);
          changedFields.set(code, new Set(fields));
        }
      });

      const queue = [...changedCodes].map((code) => [code, 0]);
      while (queue.length) {
        const [code, depth] = queue.shift();
        (children.get(code) || []).forEach((child) => {
          const nextDepth = depth + 1;
          if (!impactDepth.has(child) || nextDepth < impactDepth.get(child)) {
            impactDepth.set(child, nextDepth);
            impactedCodes.add(child);
            queue.push([child, nextDepth]);
          }
        });
      }
      changedCodes.forEach((code) => impactedCodes.delete(code));
    }

    return {
      previous,
      next,
      initial,
      reason,
      changedCodes,
      changedFields,
      impactedCodes,
      impactDepth,
      depths: stageDepths(next),
      statusChanged: initial || previous?.status_plano !== next?.status_plano,
      summaryChanged:
        initial ||
        [
          "data_inicio",
          "data_hoje",
          "data_entrega",
          "fim_previsto",
          "margem_dias_uteis",
          "status_plano",
        ].some((field) => previous?.[field] !== next?.[field]),
    };
  }

  function animateText(element, value, shouldAnimate = true, delay = 0) {
    if (!element || element.textContent === String(value ?? "")) return;
    element.textContent = value ?? "";
    if (!shouldAnimate || motionReduced()) return;
    element.classList.remove("planning-value-change");
    element.style.setProperty("--motion-delay", `${delay}ms`);
    void element.offsetWidth;
    element.classList.add("planning-value-change");
    window.setTimeout(
      () => element.classList.remove("planning-value-change"),
      260 + delay,
    );
  }

  function markMotion(element, className, delay = 0, duration = null) {
    if (!element || motionReduced()) return;
    element.classList.remove(className);
    element.style.setProperty("--motion-delay", `${delay}ms`);
    if (duration !== null) {
      element.style.setProperty("--motion-duration", `${duration}ms`);
    }
    void element.offsetWidth;
    element.classList.add(className);
    window.setTimeout(
      () => element.classList.remove(className),
      Math.max(700, delay + (duration || 420) + 80),
    );
  }

  function animateLayout(element, mutate, duration = 380) {
    if (!element) return mutate();
    if (motionReduced()) return mutate();
    const first = element.getBoundingClientRect();
    mutate();
    const last = element.getBoundingClientRect();
    const dx = first.left - last.left;
    const dy = first.top - last.top;
    if (Math.abs(dx) < 0.5 && Math.abs(dy) < 0.5) return;
    element.animate(
      [
        { transform: `translate(${dx}px, ${dy}px)` },
        { transform: "translate(0, 0)" },
      ],
      { duration, easing: "cubic-bezier(.4, 0, .2, 1)" },
    );
  }

  function range(plan) {
    const dates = plan.etapas
      .filter((stage) => stage.inicio && stage.limite)
      .flatMap((stage) => [stage.inicio, stage.limite]);
    dates.push(
      plan.data_inicio,
      plan.data_entrega || plan.fim_previsto,
      isCompleted(plan) ? plan.ciclo_vida.data_entrega_real : plan.data_hoje,
      isCompleted(plan)
        ? null
        : operationalData(plan)?.fim_operacional_projetado,
    );
    (isCompleted(plan) ? [] : operationalData(plan)?.etapas || []).forEach(
      (stage) => dates.push(stage.fim_operacional_projetado),
    );
    (plan.execucao?.etapas || []).forEach((stage) =>
      dates.push(stage.conclusao_real),
    );
    const validDates = dates.filter(Boolean).sort();
    const ultimoMarco = new Date(`${validDates.at(-1)}T12:00:00Z`);
    ultimoMarco.setUTCDate(ultimoMarco.getUTCDate() + 7);
    return {
      start: validDates[0],
      end: ultimoMarco.toISOString().slice(0, 10),
      days: daysBetween(validDates[0], ultimoMarco.toISOString().slice(0, 10)),
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
    $("#summary-operational").textContent = formatDate(
      operationalData(plan)?.fim_operacional_projetado,
    );
    $("#summary-operational-status").textContent = operationalLabel(
      operationalData(plan)?.status_operacional,
    );
    const margin = $("#summary-margin");
    margin.classList.toggle("negative", Number(plan.margem_dias_uteis) < 0);
    margin.querySelector("strong").textContent = formatMargin(
      plan.margem_dias_uteis,
    );
    if ($("#summary-margin-operational"))
      $("#summary-margin-operational").textContent =
        `Operacional: ${formatMargin(operationalData(plan)?.margem_operacional_dias_uteis ?? null)}`;
    const card = $("#plan-status-card");
    const labels = {
      VIAVEL: "Viável",
      ATENCAO: "Atenção",
      INVIAVEL: "Inviável",
      DESATUALIZADO: "Plano desatualizado",
      SEM_PREVISAO_CONFIAVEL: "Sem previsão confiável",
    };
    card.className = `planning-hero-status is-${String(plan.status_plano || "").toLowerCase()}`;
    card.innerHTML = `<span>Status do plano</span><strong>${labels[plan.status_plano] || plan.status_plano}</strong><small id="plan-exception-count" hidden></small>`;
  }
  function renderSummaryAnimated(
    plan,
    motion = planMotion(null, plan, "initial"),
  ) {
    if (isCompleted(plan)) {
      renderCompletedSummary(plan);
      return;
    }
    $("#summary-observed-label").textContent = "Projeção operacional";
    $("#summary-result-label").textContent = "Margens";
    $("#summary-reference-label").textContent = "Hoje";
    const intro = motion.initial;
    const values = [
      [
        $("[data-plan-title]"),
        plan.obra?.nomenclatura || "Obra #" + plan.obra_id,
        null,
      ],
      [$("#summary-start"), formatDate(plan.data_inicio), plan.data_inicio],
      [$("#summary-today"), formatDate(plan.data_hoje), plan.data_hoje],
      [$("#summary-due"), formatDate(plan.data_entrega), plan.data_entrega],
      [$("#summary-finish"), formatDate(plan.fim_previsto), plan.fim_previsto],
      [
        $("#summary-operational"),
        formatDate(operationalData(plan)?.fim_operacional_projetado),
        operationalData(plan)?.fim_operacional_projetado,
      ],
      [
        $("#summary-margin-planned"),
        formatMargin(plan.margem_dias_uteis),
        null,
      ],
    ];
    values.forEach(([element, value, date]) => {
      if (!element) return;
      if (intro) {
        element.classList.add("planning-intro-value");
        element.style.setProperty(
          "--motion-delay",
          dateMotionDelay(date, plan) + "ms",
        );
        element.textContent = value;
      } else {
        animateText(
          element,
          value,
          motion.summaryChanged,
          dateMotionDelay(date, plan),
        );
      }
    });
    if (intro) {
      $(".planning-title-block")?.classList.add("planning-intro-item");
      $(".planning-title-block")?.style.setProperty("--motion-delay", "0ms");
      document
        .querySelectorAll(".planning-summary article")
        .forEach((article, index) => {
          article.classList.add("planning-intro-item");
          article.style.setProperty("--motion-delay", index * 45 + "ms");
        });
    }

    const margin = $("#summary-margin");
    margin.querySelector("strong").className = "";
    margin.classList.toggle("negative", Number(plan.margem_dias_uteis) < 0);
    const operational = operationalData(plan);
    const operationalCard = $("#summary-operational-card");
    const operationalStatus = $("#summary-operational-status");
    if (operationalCard) {
      operationalCard.className = `planning-operational-card is-${String(operational?.status_operacional || "fila-nao-calculavel").toLowerCase()}`;
      operationalStatus.textContent = operational?.erro
        ? "Indisponível"
        : operationalLabel(operational?.status_operacional);
      if (operational?.projecao_condicional)
        operationalStatus.textContent += " · depende de impedimento";
    }
    const operationalMargin = $("#summary-margin-operational");
    if (operationalMargin) {
      operationalMargin.textContent = operational?.erro
        ? "Operacional: não calculável"
        : `Operacional: ${formatMargin(operational?.margem_operacional_dias_uteis ?? null)}`;
      operationalMargin.classList.toggle(
        "negative",
        Number(operational?.margem_operacional_dias_uteis) < 0,
      );
    }
    const labels = {
      VIAVEL: "Viável",
      ATENCAO: "Atenção",
      INVIAVEL: "Inviável",
      DESATUALIZADO: "Plano desatualizado",
      SEM_PREVISAO_CONFIAVEL: "Sem previsão confiável",
    };
    const statusLabel = labels[plan.status_plano] || plan.status_plano;
    const statusClass = "is-" + String(plan.status_plano || "").toLowerCase();
    const card = $("#plan-status-card");
    card.className = "planning-hero-status " + statusClass;
    const diagnosis = $("#planning-diagnosis");
    if (diagnosis) diagnosis.className = "planning-diagnosis " + statusClass;
    let statusValue = card.querySelector("strong");
    if (!statusValue) {
      card.innerHTML =
        '<span>Status do plano</span><strong></strong><small id="plan-exception-count" hidden></small>';
      statusValue = card.querySelector("strong");
    }
    animateText(statusValue, statusLabel, !intro && motion.statusChanged);
    statusValue.textContent = statusLabel;
    if (intro) {
      card.classList.add("planning-intro-item");
      card.style.setProperty("--motion-delay", "260ms");
    } else if (motion.statusChanged) {
      markMotion(card, "planning-recalc-source", 0, 420);
    }
  }

  function renderCompletedSummary(plan) {
    const cycle = plan.ciclo_vida;
    $("[data-plan-title]").textContent =
      plan.obra?.nomenclatura || `Obra #${plan.obra_id}`;
    $("#summary-start").textContent = formatDate(plan.data_inicio);
    $("#summary-finish").textContent = formatDate(plan.fim_previsto);
    $("#summary-due").textContent = formatDate(plan.data_entrega);
    $("#summary-observed-label").textContent = "Entrega real R00";
    $("#summary-operational").textContent = formatDate(cycle.data_entrega_real);
    $("#summary-operational-card").className =
      "planning-operational-card is-delivered";
    $("#summary-operational-status").textContent = "Entrega registrada";
    $("#summary-result-label").textContent = "Resultado";
    const result = $("#summary-margin-planned");
    result.textContent =
      cycle.desvio_plano_dias_uteis == null
        ? "Sem fim planejado"
        : `${cycle.desvio_plano_dias_uteis > 0 ? "+" : ""}${cycle.desvio_plano_dias_uteis} dias úteis vs. plano`;
    result.className = deviationClass(cycle.desvio_plano_dias_uteis);
    const deadlineResult = $("#summary-margin-operational");
    deadlineResult.textContent = deliveryResultText(plan);
    deadlineResult.className =
      cycle.antecedencia_prazo_dias_corridos == null
        ? "is-on-time"
        : cycle.antecedencia_prazo_dias_corridos >= 0
          ? "is-early"
          : "is-late";
    $("#summary-margin").classList.remove("negative");
    $("#summary-reference-label").textContent = "Concluído em";
    $("#summary-today").textContent = formatDate(cycle.data_entrega_real);
    $("#plan-status-card").className = "planning-hero-status is-concluido";
    $("#plan-status-card").innerHTML =
      '<span>Status do plano</span><strong>Concluído</strong><small id="plan-exception-count" hidden></small>';
    $("#planning-diagnosis").className = "planning-diagnosis is-concluido";
  }

  function detailMarkup(stage) {
    const metric = stage.metrica || {};
    const hasHistory =
      typeof metric.metodo === "string" && metric.metodo.startsWith("MEDIANA");
    const history = hasHistory
      ? `<div><span>Amostra</span><strong>${metric.amostra_ciclos_validos} ciclos</strong></div><div><span>Confiança</span><strong>${metric.confianca}</strong></div><div><span>Produtividade</span><strong>${metric.tarefas_por_dia_util_pessoa} tarefa/dia/pessoa</strong></div>`
      : `<div><span>Origem</span><strong>${metric.origem || "Marco calculado"}</strong></div>`;
    const execution = executionStage(state.plan, stage.codigo);
    const observation = observedStage(state.plan, stage);
    const operational =
      !isCompleted(state.plan) && !observation.actual
        ? operationalStage(state.plan, stage.codigo)
        : null;
    const pendingApprovals = operational?.tarefas_aguardando_aprovacao || [];
    const holdCount = (operational?.tarefas_em_hold || []).length;
    const blockCount = (operational?.impedimentos_ativos || []).length;
    const holdInfo =
      holdCount || blockCount
        ? `<p class="planning-formula planning-operational-warning">${holdCount ? `${holdCount} tarefa(s) em HOLD incluída(s) na projeção, conforme a fila. ` : ""}${blockCount ? `${blockCount} impedimento(s) ativo(s) no Flow Block: a data considera sua resolução antes da retomada.` : ""}</p>`
        : "";
    const effortInfo = operational
      ? `<p class="planning-formula">Esforço produtivo restante: ${Number(operational.esforco_pendente_pessoa_dias || 0).toLocaleString("pt-BR")} pessoa-dia(s). ${pendingApprovals.length ? `${pendingApprovals.length} tarefa(s) aguardando aprovação/direção; essa espera não consome capacidade e não tem prazo externo estimado.` : "Tempo decorrido desde o início não é descontado como trabalho sem apontamento de esforço."}</p>`
      : "";
    const operationalInfo = operational
      ? `<p class="planning-eyebrow planning-detail-section">Projeção operacional</p><div class="planning-detail-grid planning-operational-detail"><div><span>Janela operacional</span><strong>${formatDate(operational.inicio_operacional_projetado)} → ${formatDate(operational.fim_operacional_projetado)}</strong></div><div><span>Status</span><strong>${operationalLabel(operational.status_operacional)}</strong></div><div><span>Desvio vs plano</span><strong class="${Number(operational.desvio_plano_vigente_dias_uteis) > 0 ? "is-negative" : ""}">${operational.desvio_plano_vigente_dias_uteis == null ? "—" : `${operational.desvio_plano_vigente_dias_uteis >= 0 ? "+" : ""}${operational.desvio_plano_vigente_dias_uteis} dias úteis`}</strong></div><div><span>Confiança</span><strong>${operational.confianca || "—"}</strong></div></div><p class="planning-formula">${operational.dependencias?.length ? `Depende de: ${operational.dependencias.join(", ")}.` : "Frente operacional calculada pela fila atual, execução e calendário do Flow."}</p>${holdInfo}${effortInfo}<a class="planning-detail-link" href="../PlanejamentoCapacidade/index.php">Ver fila operacional na Central</a>`
      : "";
    const executionInfo = execution
      ? `<p class="planning-eyebrow planning-detail-section">Execução realizada</p><div class="planning-detail-grid planning-execution-grid"><div><span>Progresso</span><strong>${execution.percentual_concluido === null ? "Marco global" : `${execution.concluidas}/${execution.volume_atual} · ${Math.round(execution.percentual_concluido)}%`}</strong></div><div><span>Estado</span><strong>${stageExecutionLabel(execution.execucao)}</strong></div><div><span>Início real</span><strong>${formatDate(execution.inicio_real)}</strong></div><div><span>Conclusão real</span><strong>${formatDate(execution.conclusao_real)}</strong></div><div><span>Desvio real</span><strong class="${deviationClass(execution.desvio_real_dias_uteis)}">${deviationLabel(execution.desvio_real_dias_uteis)} úteis</strong></div></div>`
      : "";
    return `<div class="planning-detail-grid"><div><span>Volume</span><strong>${stage.volume} tarefas</strong></div><div><span>Pessoas</span><strong>${stage.pessoas_alocadas || "—"}</strong></div><div><span>Duração</span><strong>${stage.duracao_dias_uteis} dias úteis</strong></div><div><span>Limite</span><strong>${formatDate(stage.limite)}</strong></div>${history}</div><p class="planning-formula">${stage.formula || "Marco global: maior data-limite entre os pools de Finalização."}</p>${operationalInfo}${executionInfo}`;
  }
  function capacity(stage) {
    if (!stage.capacidade_editavel)
      return `<span class="planning-fixed" title="${stage.formula || ""}">—</span>`;
    const locked = capacityControlsLocked();
    const disabled = locked ? " disabled" : "";
    return `<div class="planning-capacity${locked ? " is-locked" : ""}" aria-label="Pessoas alocadas em ${stage.nome}" title="${locked ? "Plano confirmado. Replaneje para alterar a capacidade." : "Alterar pessoas alocadas"}"><button data-capacity="-1" data-stage="${stage.codigo}" type="button" aria-label="Remover uma pessoa"${disabled}>−</button><output>${stage.pessoas_alocadas}</output><button data-capacity="1" data-stage="${stage.codigo}" type="button" aria-label="Adicionar uma pessoa"${disabled}>+</button></div>`;
  }

  function capacityControlsLocked() {
    return (
      isCompleted(state.plan) ||
      (state.plan?.fonte === "VERSAO_CONFIRMADA" && !state.replanning) ||
      state.plan?.fonte === "VERSAO_HISTORICA"
    );
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
    target.innerHTML = `<div class="timeline-marker marker-today" data-label="Hoje" data-date="${formatDate(plan.data_hoje)}" style="left:${position(plan.data_hoje, timeline)}%"></div>${plan.data_entrega ? `<div class="timeline-marker marker-due" data-label="Entrega R00" data-date="${formatDate(plan.data_entrega)}" style="left:${position(plan.data_entrega, timeline)}%"></div>` : ""}${plan.fim_previsto ? `<div class="timeline-marker marker-finish" data-label="Fim planejado" data-date="${formatDate(plan.fim_previsto)}" style="left:${position(plan.fim_previsto, timeline)}%"></div>` : ""}<svg class="planning-connectors" aria-hidden="true"></svg>${active
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
      if (button.dataset.detailBound === "true") return;
      button.dataset.detailBound = "true";
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
    const exceptions = [
      ...(plan.excecoes || []),
      ...(plan.execucao?.excecoes || []),
    ];
    if (!exceptions.length) {
      badge.hidden = true;
      badge.textContent = "";
      badge.title = "";
      return;
    }
    badge.hidden = false;
    badge.textContent = `⚠ ${exceptions.length === 1 ? "1 exceção" : `${exceptions.length} exceções`}`;
    badge.title = exceptions
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
    if (isCompleted(plan)) {
      state.scenarioRequest = null;
      state.scenarios = [];
      target.innerHTML =
        '<span class="planning-scenario-empty">Registro histórico da entrega R00.</span>';
      return;
    }
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
      diagnosis.innerHTML = `<b>Gargalo atual:</b> conclusão ${Math.abs(margin)} dias úteis após a Entrega R00.`;
    else if (margin !== null)
      diagnosis.innerHTML = "<b>Gargalo atual:</b> nenhum identificado";
    else diagnosis.innerHTML = "<b>Gargalo atual:</b> sem previsão confiável";
    const gate = bottleneck(plan);
    detail.textContent = gate?.limite
      ? `Próximo marco crítico: ${formatDate(gate.limite)}`
      : "O caminho crítico será definido quando houver previsão confiável.";
    goal.textContent = "";
    renderScenarioSuggestions(plan);
  }
  function renderDiagnosisAnimated(plan, motion) {
    if (isCompleted(plan)) {
      $("#diagnosis-summary").textContent =
        `Entrega realizada em ${formatDate(plan.ciclo_vida.data_entrega_real)}`;
      $("#diagnosis-bottleneck").textContent =
        `Fim planejado: ${formatDate(plan.fim_previsto)} · Entrega R00 (prazo): ${formatDate(plan.data_entrega)}. ${deliveryResultText(plan)}.`;
      $("#diagnosis-bottleneck").title = "";
      $(".planning-diagnosis-main > i").className = "fa-regular fa-flag";
      renderScenarioSuggestions(plan);
      return;
    }
    $(".planning-diagnosis-main > i").className = "fa-solid fa-tower-broadcast";
    const diagnosis = $("#diagnosis-summary");
    const detail = $("#diagnosis-bottleneck");
    const goal = $("#diagnosis-goal");
    const execution = plan.execucao?.disponivel ? plan.execucao : null;
    const operational = operationalData(plan);
    const operationalStages = operational?.etapas || [];
    const executionStages = executionMap(plan);
    const next = execution
      ? executionStages.get(execution.proximo_marco)
      : null;
    const currentBottleneck = execution
      ? executionStages.get(execution.gargalo)
      : null;
    const gate = bottleneck(plan);
    const operationalCritical =
      operationalStages.find((stage) =>
        [
          "ATRASO_PROJETADO",
          "BLOQUEADO",
          "FILA_NAO_CALCULAVEL",
          "MARGEM_CONSUMIDA",
        ].includes(stage.status_operacional),
      ) ||
      operationalStages.find(
        (stage) =>
          stage.inicio_operacional_projetado && stage.fim_operacional_projetado,
      );
    const summary = operational
      ? `<b>Projeção operacional:</b> ${operationalLabel(operational.status_operacional)}${operational.projecao_condicional ? " · depende de impedimento" : operational.tarefas_em_hold ? ` · ${Number(operational.tarefas_em_hold)} em HOLD` : ""}`
      : execution
        ? `<b>Execução:</b> ${executionLabel(execution.saude)}`
        : gate
          ? "<b>Gargalo atual:</b> " + gate.nome
          : "<b>Gargalo atual:</b> nenhum identificado";
    const bottleneckText = operational
      ? operationalCritical
        ? `Próximo marco crítico: ${operationalCritical.nome || operationalCritical.codigo} · ${formatDate(operationalCritical.fim_operacional_projetado)} · ${operationalLabel(operationalCritical.status_operacional)}`
        : "Nenhum desvio operacional identificado."
      : execution
        ? next
          ? `Próximo marco: ${plan.etapas.find((stage) => stage.codigo === next.codigo)?.nome || next.codigo} · ${next.concluidas}/${next.volume_atual} concluídas · limite ${formatDate(next.limite_planejado)}`
          : execution.excecoes?.length
            ? `${execution.excecoes.length} exceção${execution.excecoes.length === 1 ? "" : "ões"} requer${execution.excecoes.length === 1 ? "" : "em"} revisão.`
            : "Nenhuma intervenção necessária."
        : gate?.limite
          ? "Próximo marco crítico: " + formatDate(gate.limite)
          : "O caminho crítico será definido quando houver previsão confiável.";
    const changed = !motion.initial && motion.summaryChanged;
    if (motion.initial) {
      [
        [$("#planning-diagnosis"), 180],
        [$(".planning-diagnosis-main"), 180],
        [$(".planning-scenarios-button"), 220],
      ].forEach(([element, delay]) => {
        element?.classList.add("planning-intro-item");
        element?.style.setProperty("--motion-delay", delay + "ms");
      });
    }
    if (diagnosis.innerHTML !== summary) {
      diagnosis.innerHTML = summary;
      if (changed) markMotion(diagnosis, "planning-value-change", 0, 220);
    }
    animateText(detail, bottleneckText, changed);
    if (goal) goal.textContent = "";
    if (execution && currentBottleneck) {
      detail.title = `Gargalo projetado: ${plan.etapas.find((stage) => stage.codigo === currentBottleneck.codigo)?.nome || currentBottleneck.codigo}`;
    }
    renderScenarioSuggestions(plan);
  }

  function renderLifecycle(plan) {
    const meta = plan.planejamento || { estado: "RASCUNHO", historico: [] };
    const stateName = meta.estado || "RASCUNHO";
    const isOfficial = plan.fonte === "VERSAO_CONFIRMADA";
    const canPersist = plan.persistencia_disponivel === true;
    const labels = {
      RASCUNHO: "Plano em revisão",
      CONFIRMADO: "Plano confirmado",
      DESATUALIZADO: "Plano desatualizado",
      REPLANEJAMENTO: "Replanejamento em análise",
      CONCLUIDO: "Plano concluído",
    };
    $("#planning-lifecycle-label").textContent = "Estado do plano";
    $("#planning-lifecycle-title").textContent = isCompleted(plan)
      ? "Plano concluído"
      : state.replanning
        ? "Replanejamento em análise"
        : "Em andamento";
    const current = (meta.historico || []).find(
      (version) => Number(version.id) === Number(meta.versao_atual_id),
    );
    $("#planning-lifecycle-detail").textContent =
      isOfficial && current
        ? `Versão ${current.numero} vigente desde ${formatDate(current.confirmado_em?.slice(0, 10))}.`
        : canPersist
          ? "Aguardando confirmação do gestor."
          : "A migration de persistência ainda não foi aplicada; a tela permanece em simulação.";
    $("#planning-mode-label").innerHTML = isOfficial
      ? '<i class="fa-solid fa-shield-halved"></i> Plano oficial'
      : '<i class="fa-solid fa-flask"></i> Simulação';
    $("#planning-toolbar-hint").textContent = state.replanning
      ? "Ajuste a proposta atual; apenas a confirmação criará uma nova versão."
      : isOfficial
        ? "Esta é a versão confirmada. Replaneje para alterar capacidade sem perder o histórico."
        : "Use +/− para ajustar a capacidade da proposta antes de confirmar.";

    const confirm = $("#confirm-plan");
    confirm.hidden = isCompleted(plan);
    const history = $("#show-plan-history");
    const reason = $("#planning-replan-reason");
    const note = $("#planning-replan-note");
    const requiresReplan = isOfficial && !state.replanning;
    confirm.disabled = isCompleted(plan) || state.saving || !canPersist;
    confirm.innerHTML = state.saving
      ? '<i class="fa-solid fa-spinner fa-spin"></i> Salvando…'
      : requiresReplan
        ? '<i class="fa-solid fa-arrows-rotate"></i> Replanejar'
        : state.replanning
          ? '<i class="fa-solid fa-check"></i> Confirmar replanejamento'
          : '<i class="fa-solid fa-check"></i> Confirmar plano';
    reason.hidden = isCompleted(plan) || !state.replanning;
    note.hidden =
      isCompleted(plan) || !state.replanning || reason.value !== "OUTRO";
    if (isCompleted(plan))
      $("#planning-toolbar-hint").textContent =
        "Planejado × realizado · registro histórico da R00 entregue.";
    $("#reset-simulation").hidden = isCompleted(plan);
    const allVersions = meta.historico || [];
    const versions = allVersions.filter(
      (version) => version.tipo !== "BASELINE",
    );
    history.hidden =
      !allVersions.length &&
      !isCompleted(plan) &&
      !plan.historico_projecoes?.length;
    history.innerHTML =
      '<i class="fa-solid fa-clock-rotate-left"></i> Histórico';
  }

  function renderPlan(plan, before, reason = "recalculate") {
    const motion = planMotion(before, plan, before ? reason : "initial");
    state.plan = plan;
    body.classList.toggle("is-completed", isCompleted(plan));
    body.classList.toggle("is-in-progress", !isCompleted(plan));
    if (isCompleted(plan)) state.replanning = false;
    $("#stage-observed-label").textContent = isCompleted(plan)
      ? "Conclusão"
      : "Conclusão / Projeção";
    state.entregaId = Number(plan.entrega_id || state.entregaId || 0);
    renderSummaryAnimated(plan, motion);
    renderStageRowsAnimated(plan, motion, before);
    renderTimelineAnimated(plan, motion);
    renderExceptions(plan);
    bindDetailTriggers(plan);
    renderDiagnosisAnimated(plan, motion);
    renderLifecycle(plan);
    if (before && (motion.changedCodes.size || motion.impactedCodes.size)) {
      markMotion($("#planning-board"), "planning-changed", 0, 420);
    }
  }

  function detail(stage) {
    $("#detail-content").innerHTML =
      `<p class="planning-eyebrow">Como foi calculado?</p><h2 id="detail-title">${stage.nome}</h2><p>${stage.caminho_critico ? "Esta etapa está no caminho crítico do plano." : "Esta etapa não determina o fim planejado no cenário atual."}</p>${detailMarkup(stage)}`;
    $("#planning-detail").classList.add("is-open");
    $("#planning-detail").setAttribute("aria-hidden", "false");
    $("#planning-scrim").hidden = false;
  }
  function selectStage(stage) {
    state.selected = state.selected === stage.codigo ? null : stage.codigo;
    const motion = planMotion(state.plan, state.plan, "selection");
    renderStageRowsAnimated(state.plan, motion, state.plan);
    renderTimelineAnimated(state.plan, motion);
    bindDetailTriggers(state.plan);
    if (state.selected) detail(stage);
  }
  function showScenario(scenario) {
    const simulated = scenario.plan;
    $("#detail-content").innerHTML =
      `<p class="planning-eyebrow">Simulação real · não salva</p><h2 id="detail-title">${scenario.stage.nome} com ${scenario.stage.pessoas_alocadas + 1} pessoas</h2><p>${scenarioResultText(simulated)}. O motor recalculou dependências, fim planejado e margem usando a mesma regra do plano.</p><div class="planning-detail-grid"><div><span>Fim planejado</span><strong>${formatDate(simulated.fim_previsto)}</strong></div><div><span>Margem</span><strong>${formatMargin(simulated.margem_dias_uteis)}</strong></div></div>`;
    $("#planning-detail").classList.add("is-open");
    $("#planning-detail").setAttribute("aria-hidden", "false");
    $("#planning-scrim").hidden = false;
  }
  async function load() {
    if (state.loading) {
      state.reloadQueued = true;
      return;
    }
    state.loading = true;
    const before = state.plan;
    const params = new URLSearchParams({
      obra_id: obraId,
      pessoas: JSON.stringify(state.people),
    });
    if (state.entregaId > 0) params.set("entrega_id", String(state.entregaId));
    if (state.replanning) params.set("replanejar", "1");
    try {
      const response = await fetch(`${endpoint}?${params}`);
      const plan = await response.json();
      if (!response.ok || plan.erro)
        throw new Error(
          plan.erro || "Não foi possível calcular o planejamento.",
        );
      renderPlan(plan, before, before ? "recalculate" : "initial");
    } catch (error) {
      $("#plan-status-card").innerHTML =
        `<span>Não foi possível carregar</span><strong>${error.message}</strong>`;
    } finally {
      state.loading = false;
      if (state.reloadQueued) {
        state.reloadQueued = false;
        load();
      }
    }
  }
  document.addEventListener("click", (event) => {
    const capacityButton = event.target.closest("[data-capacity]");
    if (capacityButton) {
      if (capacityControlsLocked()) return;
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
      renderTimelineAnimated(
        state.plan,
        planMotion(state.plan, state.plan, "scale"),
      );
      bindDetailTriggers(state.plan);
    }
  });
  $("#reset-simulation").addEventListener("click", () => {
    if (isCompleted(state.plan)) return;
    state.people = {};
    load();
  });
  $("#planning-replan-reason").addEventListener("change", () =>
    renderLifecycle(state.plan),
  );
  $("#confirm-plan").addEventListener("click", async () => {
    if (!state.plan || state.saving || isCompleted(state.plan)) return;
    if (state.plan.fonte === "VERSAO_CONFIRMADA" && !state.replanning) {
      state.replanning = true;
      state.people = Object.fromEntries(
        (state.plan.etapas || [])
          .filter((stage) => stage.capacidade_editavel)
          .map((stage) => [stage.codigo, stage.pessoas_alocadas]),
      );
      // Libera os controles imediatamente. O recálculo é assíncrono e não
      // deve deixar a tela parecendo bloqueada enquanto o snapshot simulado
      // é carregado.
      const replanMotion = planMotion(state.plan, state.plan, "replanning");
      renderStageRowsAnimated(state.plan, replanMotion, state.plan);
      renderLifecycle(state.plan);
      await load();
      return;
    }
    const reason = $("#planning-replan-reason").value;
    const note = $("#planning-replan-note").value.trim();
    if (state.replanning && (!reason || (reason === "OUTRO" && !note))) {
      $("#planning-lifecycle-detail").textContent =
        "Informe o motivo do replanejamento antes de confirmar.";
      return;
    }
    state.saving = true;
    renderLifecycle(state.plan);
    try {
      const response = await fetch("confirm.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          entrega_id: state.entregaId,
          pessoas: state.people,
          fingerprint: state.plan.fingerprint,
          lock_version: state.plan.planejamento?.lock_version || 0,
          replanejar: state.replanning,
          motivo_codigo: reason || null,
          motivo_observacao: note || null,
        }),
      });
      const result = await response.json();
      if (!response.ok || !result.success)
        throw new Error(
          result.message || "Não foi possível confirmar o plano.",
        );
      const before = state.plan;
      state.replanning = false;
      state.saving = false;
      renderPlan(result.plano, before, "confirmation");
      await load();
      $("#planning-lifecycle-detail").textContent =
        "Plano confirmado com sucesso. O histórico foi preservado.";
    } catch (error) {
      state.saving = false;
      renderLifecycle(state.plan);
      $("#planning-lifecycle-detail").textContent = error.message;
    }
  });
  function openHistoryModal() {
    const versions = state.plan?.planejamento?.historico || [];
    const entries = versions
      .map(
        (version) =>
          `<article class="planning-history-entry"><div><span>${version.tipo === "BASELINE" ? "Baseline" : `Replanejamento #${Math.max(1, Number(version.numero) - 1)}`}</span><strong>${version.vigente ? "Vigente" : "Substituído"}</strong></div><p>${formatDate(String(version.confirmado_em || "").slice(0, 10))}${version.confirmado_por ? ` · ${escapeHtml(version.confirmado_por)}` : ""}</p><div class="planning-history-metrics"><span>Fim planejado <b>${formatDate(version.fim_previsto)}</b></span><span>Margem <b>${formatMargin(version.margem_dias_uteis)}</b></span><span>Status <b>${escapeHtml(version.status_plano || "—")}</b></span></div></article>`,
      )
      .join("");
    const projections = (state.plan.historico_projecoes || [])
      .map(
        (item) =>
          `<article class="planning-history-entry"><div><span>Projeção registrada em ${formatDate(item.data_referencia)}</span><strong>${formatDate(item.fim_operacional_projetado)}</strong></div><p>${escapeHtml(operationalLabel(item.status_operacional))} · ${formatMargin(item.margem_operacional_dias_uteis)}</p></article>`,
      )
      .join("");
    const delivered = isCompleted(state.plan)
      ? `<article class="planning-history-entry"><div><span>Entrega real R00</span><strong>${formatDate(state.plan.ciclo_vida.data_entrega_real)}</strong></div><p>${escapeHtml(deliveryResultText(state.plan))}. Fonte: registro de entrega.</p></article>`
      : "";
    $("#detail-content").innerHTML =
      `<p class="planning-eyebrow">Registro de versões e entregas</p><h2 id="detail-title">Histórico do plano</h2>${delivered}<p>Versões confirmadas desta R00.</p>${entries ? `<div class="planning-history-list">${entries}</div>` : "<p>Nenhuma versão confirmada registrada.</p>"}<h3>Projeções registradas</h3>${projections || "<p>Ainda não há snapshots de projeção registrados para esta R00.</p>"}`;
    $("#planning-detail").classList.add("is-open");
    $("#planning-detail").setAttribute("aria-hidden", "false");
    $("#planning-scrim").hidden = false;
  }
  $("#show-plan-history").addEventListener("click", openHistoryModal);
  const closeDetail = () => {
    $("#planning-detail").classList.remove("is-open");
    $("#planning-detail").setAttribute("aria-hidden", "true");
    $("#planning-scrim").hidden = true;
  };
  $("#detail-close").addEventListener("click", closeDetail);
  $("#planning-scrim").addEventListener("click", closeDetail);
  const themeRoot = document.documentElement;
  const themeButton = $("#theme-toggle");
  const syncTheme = () => {
    const light = themeRoot.dataset.theme === "light";
    body.classList.toggle("light", light);
    const action = light ? "Ativar tema escuro" : "Ativar tema claro";
    themeButton.setAttribute("aria-label", action);
    themeButton.title = action;
    themeButton.innerHTML = `<i class="fa-solid fa-${light ? "moon" : "sun"}" aria-hidden="true"></i>`;
  };
  themeButton.addEventListener("click", () => {
    const next = themeRoot.dataset.theme === "light" ? "dark" : "light";
    themeRoot.dataset.theme = next;
    try {
      localStorage.setItem("theme", next);
    } catch (_) {}
    syncTheme();
  });
  new MutationObserver(syncTheme).observe(themeRoot, {
    attributes: true,
    attributeFilter: ["data-theme"],
  });
  window.addEventListener("storage", (event) => {
    if (
      event.key === "theme" &&
      (event.newValue === "light" || event.newValue === "dark")
    )
      themeRoot.dataset.theme = event.newValue;
  });
  const colorPreference = window.matchMedia?.("(prefers-color-scheme: light)");
  colorPreference?.addEventListener?.("change", (event) => {
    let saved = null;
    try {
      saved = localStorage.getItem("theme");
    } catch (_) {}
    const requested = new URLSearchParams(location.search).get("tema");
    if (!saved && requested !== "light" && requested !== "dark")
      themeRoot.dataset.theme = event.matches ? "light" : "dark";
  });
  syncTheme();
  // $("#show-scenarios").addEventListener("click", () => {
  //   if (!state.scenarios.length) return;
  //   const lines = state.scenarios
  //     .map(
  //       ({ stage, plan }) =>
  //         `<div><span>${stage.nome}: ${stage.pessoas_alocadas} → ${stage.pessoas_alocadas + 1} pessoas</span><strong>${scenarioResultText(plan)}</strong></div>`,
  //     )
  //     .join("");
  //   $("#detail-content").innerHTML =
  //     `<p class="planning-eyebrow">Simulações reais · não salvas</p><h2 id="detail-title">Cenários para a entrega</h2><div class="planning-detail-grid">${lines}</div>`;
  //   $("#planning-detail").classList.add("is-open");
  //   $("#planning-detail").setAttribute("aria-hidden", "false");
  //   $("#planning-scrim").hidden = false;
  // });
  window.addEventListener(
    "resize",
    () =>
      state.plan &&
      renderTimelineAnimated(
        state.plan,
        planMotion(state.plan, state.plan, "resize"),
      ),
  );
  function animatedCapacity(stage) {
    if (!stage.capacidade_editavel) {
      return (
        '<span class="planning-fixed" data-field="capacity" title="' +
        (stage.formula || "") +
        '">—</span>'
      );
    }
    const locked = capacityControlsLocked();
    const disabled = locked ? " disabled" : "";
    return (
      '<div class="planning-capacity' +
      (locked ? " is-locked" : "") +
      '" data-field="capacity" aria-label="Pessoas alocadas em ' +
      stage.nome +
      '" title="' +
      (locked
        ? "Plano confirmado. Replaneje para alterar a capacidade."
        : "Alterar pessoas alocadas") +
      '"><button data-capacity="-1" data-stage="' +
      stage.codigo +
      '" type="button" aria-label="Remover uma pessoa"' +
      disabled +
      ">−</button><output>" +
      stage.pessoas_alocadas +
      '</output><button data-capacity="1" data-stage="' +
      stage.codigo +
      '" type="button" aria-label="Adicionar uma pessoa"' +
      disabled +
      ">+</button></div>"
    );
  }

  function animatedStageRowMarkup(stage, index, active) {
    const execution = executionStage(state.plan, stage.codigo);
    const dependencies = stage.dependencias || [];
    const dependencyLabel =
      dependencies.length === 0
        ? "—"
        : dependencies.length === 1
          ? String(
              active.findIndex((item) => item.codigo === dependencies[0]) + 1,
            )
          : dependencies
              .map(
                (code) => active.findIndex((item) => item.codigo === code) + 1,
              )
              .join(", ");
    const criticalMark = stage.caminho_critico
      ? '<em class="fa-solid fa-bolt" title="Caminho crítico"></em>'
      : "";
    return (
      '<article class="planning-stage-row" data-stage-row="' +
      stage.codigo +
      '"><span class="planning-cell planning-index" data-field="index">' +
      (index + 1) +
      '</span><button type="button" class="planning-stage-name ' +
      stageClass(stage.codigo) +
      '" data-field="name" data-detail="' +
      stage.codigo +
      '" aria-label="Ver cálculo de ' +
      stage.nome +
      '"><i class="fa-solid ' +
      stageIcon(stage.codigo) +
      '" aria-hidden="true"></i><span data-stage-label>' +
      stage.nome +
      " " +
      criticalMark +
      "</span>" +
      (execution && execution.percentual_concluido !== null
        ? '<small class="planning-progress-pill is-' +
          String(execution.execucao || "").toLowerCase() +
          '">' +
          Math.round(execution.percentual_concluido) +
          "%</small>"
        : "") +
      '</button><span class="planning-cell" data-field="volume">' +
      stage.volume +
      ' imgs</span><span class="planning-cell planning-duration" data-field="duration">' +
      (stage.duracao_dias_uteis ?? "—") +
      ' dias</span><span class="planning-cell planning-date" data-field="start" title="' +
      formatDate(stage.inicio) +
      '">' +
      formatDate(stage.inicio).slice(0, 5) +
      '</span><span class="planning-cell planning-date" data-field="limit" title="' +
      formatDate(stage.limite) +
      '">' +
      formatDate(stage.limite).slice(0, 5) +
      "</span>" +
      animatedCapacity(stage) +
      '<button type="button" class="planning-dependencies" data-field="dependencies" data-detail="' +
      stage.codigo +
      '" title="Depende de: ' +
      dependencies
        .map(
          (code) => active.find((item) => item.codigo === code)?.nome || code,
        )
        .join(", ") +
      '">' +
      dependencyLabel +
      '</button><span class="planning-cell planning-observed-cell" data-field="observed"></span><span class="planning-cell planning-deviation" data-field="deviation"></span></article>'
    );
  }

  function updateAnimatedStageRow(row, stage, index, active, motion, previous) {
    const before = stageMap(previous).get(stage.codigo);
    const fields = motion.changedFields.get(stage.codigo) || new Set();
    const shouldAnimate =
      !motion.initial &&
      (fields.size > 0 || motion.impactedCodes.has(stage.codigo));
    const setField = (field, value, date = null) => {
      const element = row.querySelector('[data-field="' + field + '"]');
      if (!element) return;
      if (motion.initial) {
        element.classList.add("planning-intro-value");
        element.style.setProperty(
          "--motion-delay",
          (date ? 280 + dateMotionDelay(date, motion.next) : 300) + "ms",
        );
        return;
      }
      const sourceField =
        {
          duration: "duracao_dias_uteis",
          start: "inicio",
          limit: "limite",
          volume: "volume",
        }[field] || field;
      if (
        shouldAnimate &&
        before &&
        before[sourceField] !== stage[sourceField]
      ) {
        animateText(
          element,
          value,
          true,
          (motion.impactDepth.get(stage.codigo) || 0) * 50,
        );
      } else if (element.textContent !== String(value)) {
        element.textContent = value;
      }
    };
    const rowClass = [
      "planning-stage-row",
      stage.caminho_critico ? "is-critical" : "",
      state.selected === stage.codigo ? "is-selected" : "",
      state.selected &&
      !relationship(motion.next, state.selected).has(stage.codigo)
        ? "is-dim"
        : "",
    ].filter(Boolean);
    if (!motion.initial && motion.changedCodes.has(stage.codigo)) {
      rowClass.push("planning-recalc-source");
    }
    if (!motion.initial && motion.impactedCodes.has(stage.codigo)) {
      rowClass.push("planning-recalc-impact");
    }
    row.className = rowClass.join(" ");
    row.dataset.stageRow = stage.codigo;
    row.querySelector('[data-field="index"]').textContent = index + 1;
    const name = row.querySelector('[data-field="name"]');
    const label = row.querySelector("[data-stage-label]");
    name.className = "planning-stage-name " + stageClass(stage.codigo);
    name.setAttribute("aria-label", "Ver cálculo de " + stage.nome);
    name.dataset.detail = stage.codigo;
    name.querySelector("i").className = "fa-solid " + stageIcon(stage.codigo);
    label.textContent = stage.nome;
    if (stage.caminho_critico) {
      label.insertAdjacentHTML(
        "beforeend",
        ' <em class="fa-solid fa-bolt" title="Caminho crítico"></em>',
      );
    }
    const execution = executionStage(motion.next, stage.codigo);
    const observation = observedStage(motion.next, stage);
    const observed = row.querySelector('[data-field="observed"]');
    observed.className = `planning-cell planning-observed-cell ${observation.actual ? "is-actual" : observation.closed || isCompleted(motion.next) ? "is-missing" : "is-projected"}`;
    observed.innerHTML = `<small>${observation.actual ? "✓" : observation.date ? "◇" : "—"} ${escapeHtml(observation.label)}</small><strong>${formatDate(observation.date).slice(0, 5)}</strong>`;
    const deviation = row.querySelector('[data-field="deviation"]');
    deviation.className = `planning-cell planning-deviation ${deviationClass(observation.deviation)}`;
    deviation.textContent = deviationLabel(observation.deviation);
    deviation.title = `${observation.actual ? "Desvio real" : "Desvio projetado"} em dias úteis`;
    name.title = stageTooltip(motion.next, stage);
    let progress = row.querySelector(".planning-progress-pill");
    if (execution && execution.percentual_concluido !== null) {
      if (!progress) {
        name.insertAdjacentHTML(
          "beforeend",
          '<small class="planning-progress-pill"></small>',
        );
        progress = name.querySelector(".planning-progress-pill");
      }
      progress.className =
        "planning-progress-pill is-" +
        String(execution.execucao || "").toLowerCase();
      progress.textContent = Math.round(execution.percentual_concluido) + "%";
      progress.title = `${execution.concluidas}/${execution.volume_atual} concluídas · ${stageExecutionLabel(execution.execucao)}`;
    } else if (progress) {
      progress.remove();
    }
    setField("volume", stage.volume + " imgs");
    setField("duration", (stage.duracao_dias_uteis ?? "—") + " dias");
    setField("start", formatDate(stage.inicio).slice(0, 5), stage.inicio);
    setField("limit", formatDate(stage.limite).slice(0, 5), stage.limite);
    const start = row.querySelector('[data-field="start"]');
    const limit = row.querySelector('[data-field="limit"]');
    start.title = formatDate(stage.inicio);
    limit.title = formatDate(stage.limite);
    const dependencies = stage.dependencias || [];
    const dependencyLabel =
      dependencies.length === 0
        ? "—"
        : dependencies.length === 1
          ? String(
              active.findIndex((item) => item.codigo === dependencies[0]) + 1,
            )
          : dependencies
              .map(
                (code) => active.findIndex((item) => item.codigo === code) + 1,
              )
              .join(", ");
    const dependencyButton = row.querySelector('[data-field="dependencies"]');
    dependencyButton.textContent = dependencyLabel;
    dependencyButton.title =
      "Depende de: " +
      dependencies
        .map(
          (code) => active.find((item) => item.codigo === code)?.nome || code,
        )
        .join(", ");
    const oldCapacity = row.querySelector('[data-field="capacity"]');
    const currentCapacityEditable =
      oldCapacity?.classList.contains("planning-capacity") || false;
    const desiredCapacityEditable = Boolean(stage.capacidade_editavel);
    const currentCapacityLocked =
      oldCapacity?.classList.contains("is-locked") || false;
    const desiredCapacityLocked =
      desiredCapacityEditable && capacityControlsLocked();
    const capacityNeedsRefresh =
      oldCapacity &&
      (currentCapacityEditable !== desiredCapacityEditable ||
        (desiredCapacityEditable &&
          currentCapacityLocked !== desiredCapacityLocked));
    if (capacityNeedsRefresh) {
      oldCapacity.insertAdjacentHTML("afterend", animatedCapacity(stage));
      oldCapacity.remove();
    } else if (stage.capacidade_editavel && oldCapacity) {
      const output = oldCapacity.querySelector("output");
      if (before && before.pessoas_alocadas !== stage.pessoas_alocadas) {
        animateText(output, stage.pessoas_alocadas, shouldAnimate);
      } else {
        output.textContent = stage.pessoas_alocadas;
      }
    }
  }

  function renderStageRowsAnimated(plan, motion, previous) {
    motion.next = plan;
    const list = $("#stage-list");
    const active = activeStages(plan);
    const existing = new Map(
      [...list.querySelectorAll("[data-stage-row]")].map((row) => [
        row.dataset.stageRow,
        row,
      ]),
    );
    const activeCodes = new Set(active.map((stage) => stage.codigo));
    [...existing.entries()].forEach(([code, row]) => {
      if (!activeCodes.has(code)) row.remove();
    });
    active.forEach((stage, index) => {
      let row = existing.get(stage.codigo);
      if (!row) {
        list.insertAdjacentHTML(
          "beforeend",
          animatedStageRowMarkup(stage, index, active),
        );
        row = list.querySelector('[data-stage-row="' + stage.codigo + '"]');
      }
      updateAnimatedStageRow(row, stage, index, active, motion, previous);
      if (motion.initial) {
        row.classList.add("planning-intro-item");
        row.style.setProperty("--motion-delay", 280 + index * 28 + "ms");
      }
      list.appendChild(row);
    });
  }

  function timelineBarMarkup(stage, index) {
    const left = position(stage.inicio, range(state.plan));
    const right = position(stage.limite, range(state.plan));
    const execution = executionStage(state.plan, stage.codigo);
    const progress = Math.max(
      0,
      Math.min(100, Number(execution?.percentual_concluido || 0)),
    );
    const projection = operationalStage(
      state.plan,
      stage.codigo,
    )?.fim_operacional_projetado;
    const projectLeft =
      projection && stage.limite
        ? position(stage.limite, range(state.plan))
        : 0;
    const projectWidth =
      projection && stage.limite && projection > stage.limite
        ? Math.max(0.8, position(projection, range(state.plan)) - projectLeft)
        : 0;
    const actual = execution?.conclusao_real;
    return (
      '<div class="planning-bar-row" data-bar-row="' +
      stage.codigo +
      '" style="top:' +
      index * 58 +
      'px"><button type="button" data-detail="' +
      stage.codigo +
      '" class="planning-bar ' +
      stageClass(stage.codigo) +
      '" style="left:' +
      left +
      "%;width:" +
      Math.max(1.7, right - left) +
      '%" aria-label="' +
      stage.nome +
      ": " +
      formatDate(stage.inicio) +
      " até " +
      formatDate(stage.limite) +
      '"><i class="planning-bar-progress" style="width:' +
      progress +
      '%"></i><span>' +
      stage.nome +
      "</span></button>" +
      (projectWidth
        ? '<i class="planning-bar-projection" style="left:' +
          projectLeft +
          "%;width:" +
          projectWidth +
          '%"></i>'
        : "") +
      (actual
        ? '<i class="planning-bar-real" style="left:' +
          position(actual, range(state.plan)) +
          '%" title="Concluída em ' +
          formatDate(actual) +
          '"></i>'
        : "") +
      "</div>"
    );
  }

  function stageTooltip(plan, stage) {
    const observation = observedStage(plan, stage);
    const execution = executionStage(plan, stage.codigo);
    return [
      stage.nome,
      `Início planejado: ${formatDate(stage.inicio)}`,
      `Fim planejado: ${formatDate(stage.limite)}`,
      `${observation.actual || observation.closed || isCompleted(plan) ? "Conclusão real" : "Projeção operacional"}: ${formatDate(observation.date)}`,
      `${observation.actual ? "Desvio real" : "Desvio projetado"}: ${deviationLabel(observation.deviation)} úteis`,
      `Progresso: ${execution?.percentual_concluido == null ? "Marco global" : `${Math.round(execution.percentual_concluido)}%`}`,
      `Pessoas previstas: ${stage.pessoas_alocadas || "—"}`,
    ].join("\n");
  }

  const planningTooltip = document.createElement("div");
  planningTooltip.id = "planning-tooltip";
  planningTooltip.className = "planning-tooltip";
  planningTooltip.setAttribute("role", "tooltip");
  planningTooltip.hidden = true;
  document.body.appendChild(planningTooltip);
  let tooltipTrigger = null;

  function hidePlanningTooltip() {
    if (
      tooltipTrigger?.getAttribute("aria-describedby") === planningTooltip.id
    ) {
      tooltipTrigger.removeAttribute("aria-describedby");
    }
    tooltipTrigger = null;
    planningTooltip.hidden = true;
  }

  function showPlanningTooltip(trigger) {
    if (!state.plan) return;
    const plan = state.plan;
    const code = trigger.dataset.tooltipStage;
    const stage = code ? stageMap(plan).get(code) : null;
    let content = "";
    if (stage) {
      const observation = observedStage(plan, stage);
      const execution = executionStage(plan, code);
      const isLimit = trigger.dataset.tooltipKind === "limit";
      const actual = observation.actual;
      const variant = isLimit ? "limit" : actual ? "actual" : "projected";
      const title = isLimit
        ? "Fim planejado"
        : actual
          ? "Conclusão real"
          : "Projeção operacional";
      const date = isLimit ? stage.limite : observation.date;
      const delta = observation.deviation;
      const deltaText =
        delta == null
          ? "Sem desvio"
          : delta === 0
            ? "No prazo"
            : `${delta > 0 ? "+" : ""}${delta} ${Math.abs(delta) === 1 ? "dia útil" : "dias úteis"}`;
      const operational = operationalStage(plan, code);
      const holdCount =
        !actual && !isCompleted(plan)
          ? operational?.tarefas_em_hold?.length || 0
          : 0;
      const blocks =
        !actual && !isCompleted(plan)
          ? operational?.impedimentos_ativos?.length || 0
          : 0;
      const counterpartLabel =
        observation.actual || observation.closed || isCompleted(plan)
          ? "Conclusão real"
          : "Projeção operacional";
      const info = [
        ["Início planejado", formatDate(stage.inicio)],
        [
          isLimit ? counterpartLabel : "Fim planejado",
          formatDate(isLimit ? observation.date : stage.limite),
        ],
        [
          "Progresso",
          execution?.percentual_concluido == null
            ? "—"
            : `${Math.round(execution.percentual_concluido)}%`,
        ],
        [
          "Pessoas previstas",
          stage.pessoas_alocadas == null ? "—" : stage.pessoas_alocadas,
        ],
      ];
      content = `<div class="planning-tooltip-header"><span class="planning-tooltip-symbol is-${variant}" aria-hidden="true">${isLimit ? "│" : actual ? "✓" : "◆"}</span><div><span class="planning-tooltip-kicker">Marco da etapa</span><strong>${escapeHtml(stage.nome)}</strong></div></div><div class="planning-tooltip-highlight"><div><span>${title}</span><strong>${formatDate(date)}</strong></div>${!isLimit && delta != null ? `<b class="planning-tooltip-deviation ${deviationClass(delta)}">${escapeHtml(deltaText)}</b>` : ""}</div><dl class="planning-tooltip-grid">${info.map(([label, value]) => `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd></div>`).join("")}</dl>${holdCount || blocks ? `<p class="planning-tooltip-note">${holdCount ? `${holdCount} ${holdCount === 1 ? "tarefa" : "tarefas"} em HOLD na projeção.` : ""}${blocks ? ` ${blocks} ${blocks === 1 ? "impedimento ativo" : "impedimentos ativos"}: prazo condicionado à resolução.` : ""}</p>` : ""}<div class="planning-tooltip-footer">${isLimit ? "Limite previsto no plano vigente" : "Clique no marco para ver os detalhes"}</div>`;
    } else if (trigger.dataset.tooltipGlobal) {
      const marker = trigger.closest(".timeline-marker");
      const kind = trigger.dataset.tooltipGlobal;
      const descriptions = {
        today: "Data de referência da tela.",
        finish: "Término previsto na versão vigente do plano.",
        operational: plan.projecao_operacional?.projecao_condicional
          ? "Data projetada condicionada à resolução de impedimentos registrados."
          : "Término estimado a partir da fila e das tarefas atuais.",
        due: "Prazo previsto para a entrega R00.",
        actual: "Data em que a entrega R00 foi registrada.",
      };
      content = `<div class="planning-tooltip-header"><span class="planning-tooltip-symbol is-${kind}" aria-hidden="true">│</span><div><span class="planning-tooltip-kicker">Marco do projeto</span><strong>${escapeHtml(marker.dataset.label)}</strong></div></div><div class="planning-tooltip-highlight"><div><span>Data</span><strong>${escapeHtml(marker.dataset.date)}</strong></div></div><p class="planning-tooltip-description">${escapeHtml(descriptions[kind] || "")}</p>`;
    }
    if (!content) return;
    hidePlanningTooltip();
    planningTooltip.innerHTML = content;
    planningTooltip.hidden = false;
    tooltipTrigger = trigger;
    trigger.setAttribute("aria-describedby", planningTooltip.id);
    const anchor = trigger.getBoundingClientRect();
    const tip = planningTooltip.getBoundingClientRect();
    const padding = 10;
    const left = Math.max(
      padding,
      Math.min(
        window.innerWidth - tip.width - padding,
        anchor.left + anchor.width / 2 - tip.width / 2,
      ),
    );
    const top =
      anchor.top - tip.height - 12 >= padding
        ? anchor.top - tip.height - 12
        : Math.min(
            window.innerHeight - tip.height - padding,
            anchor.bottom + 12,
          );
    planningTooltip.style.left = `${left}px`;
    planningTooltip.style.top = `${Math.max(padding, top)}px`;
  }

  const tooltipTarget = (target) =>
    target instanceof Element
      ? target.closest("[data-tooltip-stage], [data-tooltip-global]")
      : null;
  document.addEventListener("pointerover", (event) => {
    const trigger = tooltipTarget(event.target);
    if (
      trigger &&
      event.pointerType !== "touch" &&
      !trigger.contains(event.relatedTarget)
    )
      showPlanningTooltip(trigger);
  });
  document.addEventListener("pointerout", (event) => {
    const trigger = tooltipTarget(event.target);
    if (
      trigger &&
      !trigger.contains(event.relatedTarget) &&
      !trigger.matches(":focus-visible")
    )
      hidePlanningTooltip();
  });
  document.addEventListener("focusin", (event) => {
    const trigger = tooltipTarget(event.target);
    if (trigger) showPlanningTooltip(trigger);
  });
  document.addEventListener("focusout", (event) => {
    if (tooltipTarget(event.target)) hidePlanningTooltip();
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") hidePlanningTooltip();
  });
  document.addEventListener("click", (event) => {
    if (tooltipTarget(event.target)) hidePlanningTooltip();
  });
  document.addEventListener("scroll", hidePlanningTooltip, true);
  window.addEventListener("resize", hidePlanningTooltip);

  function stageMilestoneMarkup(plan, stage, timeline) {
    const observation = observedStage(plan, stage);
    const limit = position(stage.limite, timeline);
    let markup = `<span data-stage-observation data-tooltip-stage="${stage.codigo}" data-tooltip-kind="limit" class="planning-limit-tick" style="left:${limit}%" tabindex="0" role="img" aria-label="${escapeHtml(`${stage.nome}: fim planejado ${formatDate(stage.limite)}`)}"></span>`;
    if (!observation.date) return markup;
    const end = position(observation.date, timeline);
    const kind = observation.actual ? "actual" : "projected";
    const status = deviationClass(observation.deviation);
    markup += `<span data-stage-observation class="planning-observed-link is-${kind} ${status}" style="left:${Math.min(limit, end)}%;width:${Math.abs(end - limit)}%"></span>`;
    markup += `<button type="button" data-stage-observation data-detail="${stage.codigo}" data-tooltip-stage="${stage.codigo}" data-tooltip-kind="${kind}" class="planning-milestone is-${kind}" style="left:${end}%" aria-label="${escapeHtml(`${stage.nome}: ${observation.label} ${formatDate(observation.date)}, ${deviationLabel(observation.deviation)} úteis`)}"><span>${observation.actual ? "✓" : ""}</span></button>`;
    markup += `<span data-stage-observation class="planning-milestone-date is-${kind}" style="left:${end}%">${formatDate(observation.date).slice(0, 5)}</span>`;
    return markup;
  }

  function updateTimelineMarker(target, kind, label, date, timeline, motion) {
    if (!date) {
      target.querySelector(".marker-" + kind)?.remove();
      return;
    }
    let marker = target.querySelector(".marker-" + kind);
    const oldLeft = marker?.style.left;
    if (!marker) {
      marker = document.createElement("div");
      marker.className = "timeline-marker marker-" + kind;
      marker.innerHTML =
        '<button type="button" class="timeline-marker-trigger"></button>';
      target.prepend(marker);
    }
    marker.dataset.label = label;
    marker.dataset.date = formatDate(date);
    const trigger = marker.querySelector(".timeline-marker-trigger");
    trigger.dataset.tooltipGlobal = kind;
    trigger.textContent = label;
    trigger.setAttribute("aria-label", `${label}: ${formatDate(date)}`);
    marker.style.left = position(date, timeline) + "%";
    if (!motion.initial && oldLeft && oldLeft !== marker.style.left) {
      markMotion(marker, "planning-marker-reposition", 0, 360);
    }
    if (motion.initial) {
      marker.classList.add("planning-marker-build");
      marker.style.setProperty(
        "--motion-delay",
        kind === "today" ? "320ms" : "820ms",
      );
    }
  }

  function renderTimelineAnimated(
    plan,
    motion = planMotion(null, plan, "initial"),
  ) {
    hidePlanningTooltip();
    motion.next = plan;
    const target = $("#timeline");
    const timeline = range(plan);
    const active = activeStages(plan).filter(
      (stage) => stage.inicio && stage.limite,
    );
    target.style.minHeight = `${48 + active.length * 72 + 34}px`;
    $("#planning-legend").innerHTML = isCompleted(plan)
      ? '<b class="legend-finish"></b>Fim planejado <b class="legend-actual"></b>Entrega real <b class="legend-deadline"></b>Entrega R00 (prazo) <b class="legend-check">✓</b>Conclusão'
      : '<b class="legend-today"></b>Hoje <b class="legend-finish"></b>Fim planejado <b class="legend-due"></b>Entrega R00 <b class="legend-projection"></b>Projeção <b class="legend-check">✓</b>Conclusão';
    timelineScale(plan, timeline);
    if (motion.initial) {
      $(".planning-stage-head")?.classList.add("planning-intro-item");
      $(".planning-stage-head")?.style.setProperty("--motion-delay", "250ms");
      $(".planning-timeline-head")?.classList.add("planning-intro-item");
      $(".planning-timeline-head")?.style.setProperty(
        "--motion-delay",
        "250ms",
      );
    } else if (motion.reason === "scale") {
      markMotion($(".planning-timeline-head"), "planning-value-change", 0, 180);
    }
    if (!target.querySelector(".planning-connectors")) {
      target.insertAdjacentHTML(
        "afterbegin",
        '<svg class="planning-connectors" aria-hidden="true"></svg>',
      );
    }
    updateTimelineMarker(
      target,
      "today",
      "Hoje",
      isCompleted(plan) ? null : plan.data_hoje,
      timeline,
      motion,
    );
    updateTimelineMarker(
      target,
      "due",
      "Entrega R00 (prazo)",
      plan.data_entrega,
      timeline,
      motion,
    );
    updateTimelineMarker(
      target,
      "finish",
      "Fim planejado",
      plan.fim_previsto,
      timeline,
      motion,
    );
    updateTimelineMarker(
      target,
      "operational",
      "Projeção operacional",
      isCompleted(plan)
        ? null
        : operationalData(plan)?.fim_operacional_projetado || null,
      timeline,
      motion,
    );
    updateTimelineMarker(
      target,
      "actual",
      "Entrega real",
      isCompleted(plan) ? plan.ciclo_vida.data_entrega_real : null,
      timeline,
      motion,
    );
    // Datas coincidentes mantêm linhas na posição correta e rótulos escalonados.
    const markers = [...target.querySelectorAll(".timeline-marker")].sort(
      (a, b) => parseFloat(a.style.left) - parseFloat(b.style.left),
    );
    let previousMarker = null;
    let level = 0;
    markers.forEach((marker) => {
      level =
        previousMarker &&
        parseFloat(marker.style.left) - parseFloat(previousMarker.style.left) <
          12
          ? (level + 1) % 3
          : 0;
      marker.style.setProperty("--marker-level", level);
      previousMarker = marker;
    });

    const related = state.selected ? relationship(plan, state.selected) : null;
    const existing = new Map(
      [...target.querySelectorAll("[data-bar-row]")].map((row) => [
        row.dataset.barRow,
        row,
      ]),
    );
    const activeCodes = new Set(active.map((stage) => stage.codigo));
    [...existing.entries()].forEach(([code, row]) => {
      if (!activeCodes.has(code)) row.remove();
    });
    const beforeMap = stageMap(motion.previous);
    active.forEach((stage, index) => {
      let row = existing.get(stage.codigo);
      if (!row) {
        target.insertAdjacentHTML("beforeend", timelineBarMarkup(stage, index));
        row = target.querySelector('[data-bar-row="' + stage.codigo + '"]');
      }
      const bar = row.querySelector(".planning-bar");
      const left = position(stage.inicio, timeline);
      const width = Math.max(1.7, position(stage.limite, timeline) - left);
      const before = beforeMap.get(stage.codigo);
      const isSource = motion.changedCodes.has(stage.codigo);
      const isImpact = motion.impactedCodes.has(stage.codigo);
      row.className = [
        "planning-bar-row",
        state.selected === stage.codigo ? "is-selected" : "",
        related && !related.has(stage.codigo) ? "is-dim" : "",
        isSource ? "planning-recalc-source" : "",
        isImpact ? "planning-recalc-impact" : "",
      ]
        .filter(Boolean)
        .join(" ");
      animateLayout(
        row,
        () => {
          row.style.top = 48 + index * 72 + "px";
        },
        360,
      );
      bar.className = [
        "planning-bar",
        stageClass(stage.codigo),
        stage.caminho_critico ? "is-critical" : "",
      ]
        .filter(Boolean)
        .join(" ");
      bar.dataset.detail = stage.codigo;
      bar.setAttribute(
        "aria-label",
        stage.nome +
          ": " +
          formatDate(stage.inicio) +
          " até " +
          formatDate(stage.limite),
      );
      bar.querySelector("span").textContent = stage.nome;
      bar.title = stageTooltip(plan, stage);
      const execution = executionStage(plan, stage.codigo);
      let fill = bar.querySelector(".planning-bar-progress");
      if (!fill) {
        bar.insertAdjacentHTML(
          "afterbegin",
          '<i class="planning-bar-progress"></i>',
        );
        fill = bar.querySelector(".planning-bar-progress");
      }
      fill.style.width = `${Math.max(0, Math.min(100, Number(execution?.percentual_concluido || 0)))}%`;
      row
        .querySelectorAll(
          ".planning-bar-projection, .planning-bar-real, [data-stage-observation]",
        )
        .forEach((element) => element.remove());
      row.insertAdjacentHTML(
        "beforeend",
        stageMilestoneMarkup(plan, stage, timeline),
      );
      animateLayout(
        bar,
        () => {
          bar.style.left = left + "%";
          bar.style.width = width + "%";
        },
        Math.max(
          250,
          Math.min(550, 250 + (stage.duracao_dias_uteis || 1) * 18),
        ),
      );
      if (motion.initial) {
        bar.classList.add("planning-build-bar");
        bar.style.setProperty(
          "--motion-delay",
          370 + (motion.depths.get(stage.codigo) || 0) * 70 + "ms",
        );
        bar.style.setProperty(
          "--motion-duration",
          Math.max(
            250,
            Math.min(550, 250 + (stage.duracao_dias_uteis || 1) * 18),
          ) + "ms",
        );
      } else if (isSource) {
        markMotion(bar, "planning-recalc-source", 0, 420);
      } else if (isImpact && before) {
        markMotion(
          bar,
          "planning-recalc-impact",
          (motion.impactDepth.get(stage.codigo) || 1) * 50,
          360,
        );
      }
      target.appendChild(row);
    });
    requestAnimationFrame(() => drawConnectorsAnimated(plan, timeline, motion));
  }

  function drawConnectorsAnimated(plan, timeline, motion) {
    const svg = $(".planning-connectors");
    const target = $("#timeline");
    if (!svg || !target) return;
    svg.setAttribute(
      "viewBox",
      "0 0 " + target.clientWidth + " " + target.clientHeight,
    );
    svg.setAttribute("width", target.clientWidth);
    svg.setAttribute("height", target.clientHeight);
    const active = activeStages(plan).filter(
      (stage) => stage.inicio && stage.limite,
    );
    const map = Object.fromEntries(
      active.map((stage, index) => [stage.codigo, { stage, index }]),
    );
    const desired = new Map();
    active.forEach((stage, index) => {
      (stage.dependencias || [])
        .filter((dependency) => map[dependency])
        .forEach((dependency) => {
          const from = map[dependency];
          const x1 =
            (position(from.stage.limite, timeline) / 100) * target.clientWidth;
          const x2 =
            (position(stage.inicio, timeline) / 100) * target.clientWidth;
          const y1 = 48 + from.index * 72 + 36;
          const y2 = 48 + index * 72 + 36;
          const mid = Math.max(x1 + 10, (x1 + x2) / 2);
          desired.set(
            dependency + "->" + stage.codigo,
            "M " + x1 + " " + y1 + " H " + mid + " V " + y2 + " H " + x2,
          );
        });
    });
    [...svg.querySelectorAll("[data-connector]")].forEach((path) => {
      if (!desired.has(path.dataset.connector)) path.remove();
    });
    desired.forEach((d, key) => {
      let path = svg.querySelector('[data-connector="' + key + '"]');
      const isNew = !path;
      if (!path) {
        path = document.createElementNS("http://www.w3.org/2000/svg", "path");
        path.classList.add("planning-connector");
        path.dataset.connector = key;
        svg.appendChild(path);
      }
      path.setAttribute("d", d);
      const [fromCode, toCode] = key.split("->");
      const connectorAffected =
        motion.initial ||
        motion.changedCodes.has(fromCode) ||
        motion.changedCodes.has(toCode) ||
        motion.impactedCodes.has(fromCode) ||
        motion.impactedCodes.has(toCode);
      if (isNew || connectorAffected) {
        const length = Math.max(1, path.getTotalLength?.() || 1);
        const connectorDelay = motion.initial
          ? 780 + (motion.depths.get(toCode) || 0) * 35
          : (motion.impactDepth.get(toCode) || 0) * 50;
        path.style.setProperty("--connector-length", length);
        path.style.strokeDasharray = length + " " + length;
        path.style.strokeDashoffset = length;
        path.classList.remove("planning-build-connector");
        void path.offsetWidth;
        path.classList.add("planning-build-connector");
        path.style.setProperty("--motion-delay", connectorDelay + "ms");
        requestAnimationFrame(() => {
          path.style.strokeDashoffset = "0";
        });
        window.setTimeout(() => {
          path.style.removeProperty("stroke-dasharray");
          path.style.removeProperty("stroke-dashoffset");
          path.classList.remove("planning-build-connector");
        }, connectorDelay + 520);
      }
    });
  }

  load();
})();
