(() => {
  "use strict";

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [
    ...root.querySelectorAll(selector),
  ];
  const state = { mode: "capacity", loadedKey: null, pendingKey: null };
  const labels = {
    NAO_ALOCADO: "Não alocado",
    PARCIALMENTE_ALOCADO: "Parcialmente alocado",
    ALOCADO: "Alocado",
    SUBALOCADO: "Subalocado",
    SOBREALOCADO: "Sobrealocado",
    CONFLITO: "Conflito de carga",
    FORA_DO_PLANO: "Fora do plano",
    PENDENTE_MATERIALIZACAO: "Pendente de materialização",
    PRINCIPAL: "Principal",
    SECUNDARIA: "Secundário",
    RECOMENDADO: "Recomendado",
    APOIO_DISPONIVEL: "Apoio disponível",
    RESPONSAVEL_ATUAL: "Responsável atual",
    RESPONSAVEL_ATUAL_COM_CONFLITO: "Responsável atual · conflito",
  };

  const esc = (value) =>
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
  const num = (value, digits = 0) =>
    new Intl.NumberFormat("pt-BR", {
      minimumFractionDigits: digits,
      maximumFractionDigits: digits,
    }).format(Number(value || 0));
  const date = (value) => {
    if (!value) return "—";
    const parsed = new Date(`${value}T12:00:00`);
    return Number.isNaN(parsed.getTime())
      ? "—"
      : new Intl.DateTimeFormat("pt-BR", { day: "2-digit", month: "short" })
          .format(parsed)
          .replace(".", "");
  };
  const period = () => ({
    inicio: $("#period-start")?.value,
    fim: $("#period-end")?.value,
  });
  const statusClass = (status) =>
    String(status || "")
      .toLowerCase()
      .replaceAll("_", "-");
  const statusLabel = (status) =>
    labels[status] || String(status || "—").replaceAll("_", " ");
  const hasIssue = (project) =>
    [
      "NAO_ALOCADO",
      "PARCIALMENTE_ALOCADO",
      "SUBALOCADO",
      "SOBREALOCADO",
      "CONFLITO",
      "FORA_DO_PLANO",
      "PENDENTE_MATERIALIZACAO",
    ].includes(project.status_alocacao) ||
    Number(project.responsabilidades_divergentes || 0) > 0;

  function bar(percent) {
    const safe = Math.max(0, Math.min(160, Number(percent || 0)));
    return `<span class="allocation-load-bar" aria-label="${num(safe, 1)}% de carga"><i style="width:${safe}%"></i></span>`;
  }

  function personMarkup(person, candidate = false) {
    const conflicts = person.conflitos || [];
    const role =
      person.tipo_atuacao ||
      (person.elegivel === false ? "FORA_DO_PLANO" : "—");
    const tasks = Number(
      person.tarefas_atribuidas ?? person.unidades_atribuidas ?? 0,
    );
    const shared = Number(
      person.tarefas_compartilhadas_sem_peso ??
        person.unidades_compartilhadas ??
        0,
    );
    const load = Number(person.carga_periodo_percentual ?? 0);
    const roleLabel =
      person.elegivel === false ? "Fora da elegibilidade" : statusLabel(role);
    return `<article class="allocation-person ${conflicts.length ? "is-conflict" : ""} ${candidate ? "is-candidate" : ""}">
      <div class="allocation-person-head"><strong>${esc(person.nome)}</strong><span class="allocation-role is-${statusClass(role)}">${esc(roleLabel)}</span></div>
      <div class="allocation-person-load"><span>${bar(load)}</span><b>${num(load, 0)}%</b></div>
      <div class="allocation-person-meta"><span>${tasks} unidade${tasks === 1 ? "" : "s"}</span>${shared ? `<span>${shared} compartilhada${shared === 1 ? "" : "s"}*</span>` : ""}</div>
      <small>${conflicts.length ? `<i class="fa-solid fa-triangle-exclamation"></i> ${conflicts.length} dia${conflicts.length === 1 ? "" : "s"} acima de 100%` : candidate && person.classificacao === "APOIO_DISPONIVEL" ? "Disponível como apoio" : "Sem conflito na janela"}</small>
    </article>`;
  }

  function projectMarkup(project) {
    const persons = project.pessoas || [];
    const candidates = project.candidatos || [];
    const candidateMarkup = candidates.length
      ? candidates.map((candidate) => personMarkup(candidate, true)).join("")
      : '<p class="allocation-muted">Nenhum colaborador elegível configurado para esta etapa.</p>';
    const loadNote =
      Number(project.carga_compartilhada_sem_peso_pessoa_dia || 0) > 0
        ? `<p class="allocation-shared-note"><i class="fa-solid fa-code-branch"></i> ${num(project.carga_compartilhada_sem_peso_pessoa_dia, 1)} pessoa-dia está compartilhado sem peso individual, pois Caderno e Filtro possuem responsáveis diferentes.</p>`
        : "";
    const divergence = Number(project.responsabilidades_divergentes || 0)
      ? `<span class="allocation-alert is-warning"><i class="fa-solid fa-code-branch"></i> Responsabilidade divergente em ${project.responsabilidades_divergentes} unidade${project.responsabilidades_divergentes === 1 ? "" : "s"}</span>`
      : "";
    return `<details class="allocation-project ${hasIssue(project) ? "has-issue" : ""}" ${hasIssue(project) ? "open" : ""}>
      <summary>
        <div><strong>${esc(project.obra)}</strong><span>${date(project.inicio)} → ${date(project.limite)} · ${num(project.pessoas_planejadas)} pessoa${Number(project.pessoas_planejadas) === 1 ? "" : "s"} planejada${Number(project.pessoas_planejadas) === 1 ? "" : "s"}</span></div>
        <span class="allocation-status is-${statusClass(project.status_alocacao)}">${esc(statusLabel(project.status_alocacao))}</span>
      </summary>
      <div class="allocation-project-body">
        <div class="allocation-flow-metrics" aria-label="Planejado, materializado e alocado">
          <div><span>Planejado</span><strong>${num(project.planejado)}</strong></div><i class="fa-solid fa-arrow-right"></i>
          <div><span>Materializado</span><strong>${num(project.materializado)}</strong></div><i class="fa-solid fa-arrow-right"></i>
          <div><span>Alocado</span><strong>${num(project.alocado)}</strong></div>
        </div>
        <div class="allocation-project-alerts">
          ${Number(project.sem_responsavel) ? `<span class="allocation-alert is-danger"><i class="fa-solid fa-user-slash"></i> ${num(project.sem_responsavel)} tarefa${Number(project.sem_responsavel) === 1 ? "" : "s"} real(is) sem responsável</span>` : ""}
          ${Number(project.pendente_materializacao) ? `<span class="allocation-alert is-warning"><i class="fa-solid fa-cubes-stacked"></i> ${num(project.pendente_materializacao)} pendente${Number(project.pendente_materializacao) === 1 ? "" : "s"} de materialização</span>` : ""}
          ${divergence}
        </div>
        <div class="allocation-load-summary"><span>Carga planejada</span><strong>${num(project.carga_total_planejada_pessoa_dia, 1)} pessoa-dias</strong><em>${num(project.carga_nominal_atribuida_pessoa_dia, 1)} atribuídos · ${num(project.carga_nao_atribuida_pessoa_dia, 1)} sem responsável · ${num(project.carga_nao_materializada_pessoa_dia, 1)} não materializados</em></div>
        ${persons.length ? `<section><h5>Responsáveis atuais <span>${persons.length}</span></h5><div class="allocation-person-grid">${persons.map((person) => personMarkup(person)).join("")}</div></section>` : '<p class="allocation-muted">Não há responsável em tarefa operacional materializada.</p>'}
        ${loadNote}
        <details class="allocation-candidates"><summary><i class="fa-solid fa-user-plus"></i> Candidatos elegíveis <span>${candidates.length}</span></summary><div class="allocation-person-grid">${candidateMarkup}</div></details>
      </div>
    </details>`;
  }

  function groupMarkup(group) {
    const ready = (group.projetos || []).every((project) => !hasIssue(project));
    return `<article class="allocation-stage-card">
      <header><div><p>${esc(group.codigo_etapa.replaceAll("_", " "))}</p><h4>${esc(group.etapa)}</h4></div><span class="allocation-stage-state ${ready ? "is-ready" : "is-attention"}"><i class="fa-solid ${ready ? "fa-circle-check" : "fa-triangle-exclamation"}"></i> ${ready ? "Operação preparada" : "Requer atenção"}</span></header>
      <div class="allocation-stage-totals"><div><span>Planejado</span><strong>${num(group.planejado)}</strong></div><div><span>Materializado</span><strong>${num(group.materializado)}</strong></div><div><span>Alocado</span><strong>${num(group.alocado)}</strong></div><div class="${Number(group.pendente_materializacao) ? "is-warning" : ""}"><span>Pendente</span><strong>${num(group.pendente_materializacao)}</strong></div></div>
      <div class="allocation-project-list">${(group.projetos || []).map(projectMarkup).join("")}</div>
    </article>`;
  }

  function render(data) {
    const summary = data.resumo || {};
    $("#allocation-planned").textContent = num(summary.planejado);
    $("#allocation-materialized").textContent = num(summary.materializado);
    $("#allocation-unassigned").textContent = num(summary.sem_responsavel);
    $("#allocation-pending").textContent = num(summary.pendente_materializacao);
    $("#allocation-period-label").textContent =
      `${date(data.periodo?.inicio)} → ${date(data.periodo?.fim)}`;
    const groups = data.grupos || [];
    $("#allocation-stage-list").innerHTML = groups.map(groupMarkup).join("");
    $("#allocation-empty").hidden = groups.length > 0;
    if (!groups.length)
      $("#allocation-empty").textContent =
        "Não há etapas planejadas vigentes neste período.";
    const exceptions = data.excecoes || [];
    const notes = $("#allocation-notes");
    notes.hidden = !exceptions.length;
    notes.innerHTML = exceptions.length
      ? `<h3><i class="fa-solid fa-circle-info"></i> Observações técnicas</h3>${exceptions.map((item) => `<p><strong>${esc(item.codigo.replaceAll("_", " "))}</strong> · ${esc(item.mensagem || "Verifique a consistência do planejamento e da operação.")}</p>`).join("")}<small>${esc(data.formula_carga || "")}</small>`
      : "";
  }

  async function load() {
    const { inicio, fim } = period();
    if (!inicio || !fim || state.mode !== "allocation") return;
    const key = `${inicio}:${fim}`;
    state.pendingKey = key;
    $("#allocation-loading").hidden = false;
    $("#allocation-empty").hidden = true;
    try {
      const query = new URLSearchParams({ inicio, fim });
      const response = await fetch(
        `${document.body.dataset.allocationApiUrl}?${query}`,
      );
      const payload = await response.json();
      if (!response.ok || !payload.success)
        throw new Error(
          payload.message || "Não foi possível consultar a alocação.",
        );
      if (state.pendingKey !== key) return;
      state.loadedKey = key;
      render(payload.alocacao);
    } catch (error) {
      if (state.pendingKey !== key) return;
      $("#allocation-stage-list").innerHTML = "";
      const empty = $("#allocation-empty");
      empty.hidden = false;
      empty.textContent = error.message;
    } finally {
      if (state.pendingKey === key) $("#allocation-loading").hidden = true;
    }
  }

  function setMode(mode) {
    state.mode = mode;
    const allocation = mode === "allocation";
    $("#capacity-workspace").hidden = allocation;
    $("#allocation-workspace").hidden = !allocation;
    document.body.classList.toggle("is-allocation-workspace", allocation);
    $$("[data-capacity-workspace]").forEach((button) =>
      button.classList.toggle(
        "is-active",
        button.dataset.capacityWorkspace === mode,
      ),
    );
    if (allocation) load();
  }

  document.addEventListener("DOMContentLoaded", () => {
    $$("[data-capacity-workspace]").forEach((button) =>
      button.addEventListener("click", () =>
        setMode(button.dataset.capacityWorkspace),
      ),
    );
    [$("#period-start"), $("#period-end")]
      .filter(Boolean)
      .forEach((input) => input.addEventListener("change", () => load()));
    $$("[data-weeks], #period-prev, #period-next").forEach((control) =>
      control.addEventListener("click", () => window.setTimeout(load, 0)),
    );
  });
})();
