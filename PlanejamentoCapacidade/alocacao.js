(() => {
  "use strict";

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [
    ...root.querySelectorAll(selector),
  ];
  const state = {
    mode: "capacity",
    loadedKey: null,
    pendingKey: null,
    allocation: null,
    selected: new Map(),
    simulation: null,
    validation: null,
    operational: {},
    queue: null,
  };
  const labels = {
    ALOCADO: "Alocado",
    NAO_ALOCADO: "Não alocado",
    PARCIALMENTE_ALOCADO: "Parcialmente alocado",
    SUBALOCADO: "Subalocado",
    SOBREALOCADO: "Sobrealocado",
    CONFLITO: "Conflito de carga",
    FORA_DO_PLANO: "Fora do plano",
    PENDENTE_MATERIALIZACAO: "Pendente de materialização",
    CAPACIDADE_PENDENTE_VALIDACAO: "Capacidade pendente de validação",
    ALOCACAO_VALIDADA_COM_EXCECAO: "Alocação validada com exceção",
    NORMAL: "Dentro da capacidade",
    SOBRECARGA_NAO_VALIDADA: "Sobrecarga não validada",
    SOBRECARGA_VALIDADA: "Sobrecarga excepcional validada",
    VALIDACAO_DESATUALIZADA: "Validação desatualizada",
    NO_PLANO: "Compatível com o plano",
    MARGEM_CONSUMIDA: "Margem consumida",
    ATRASO_PROJETADO: "Atraso projetado",
    FILA_NAO_CALCULAVEL: "Fila não calculável",
    BLOQUEADO: "Bloqueado",
    RESOLVE: "Resolve",
    RESOLVE_PARCIALMENTE: "Resolve parcialmente",
    TRANSFERE_PROBLEMA: "Transfere problema",
    SEM_GANHO: "Sem ganho",
    PRINCIPAL: "Principal",
    SECUNDARIA: "Secundário",
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
      : new Intl.DateTimeFormat("pt-BR", {
          day: "2-digit",
          month: "short",
        })
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
  const allocationUrls = () => ({
    simulate:
      document.body.dataset.allocationSimulateUrl || "alocacao_simular.php",
    apply: document.body.dataset.allocationApplyUrl || "alocacao_aplicar.php",
    validate:
      document.body.dataset.allocationValidationUrl ||
      "alocacao_validar_capacidade.php",
    suggest:
      document.body.dataset.allocationSuggestUrl || "alocacao_sugerir.php",
    projection:
      document.body.dataset.operationalProjectionApiUrl ||
      "projecao_operacional.php",
    queueSimulate:
      document.body.dataset.queueSimulateUrl || "fila_operacional_simular.php",
    queueConfirm:
      document.body.dataset.queueConfirmUrl || "fila_operacional_confirmar.php",
    queueSuggest:
      document.body.dataset.queueSuggestUrl || "fila_operacional_sugerir.php",
  });
  const hasIssue = (project) =>
    [
      "NAO_ALOCADO",
      "PARCIALMENTE_ALOCADO",
      "SUBALOCADO",
      "SOBREALOCADO",
      "CONFLITO",
      "FORA_DO_PLANO",
      "PENDENTE_MATERIALIZACAO",
      "CAPACIDADE_PENDENTE_VALIDACAO",
      "ALOCACAO_VALIDADA_COM_EXCECAO",
    ].includes(project.status_alocacao) ||
    Number(project.responsabilidades_divergentes || 0) > 0;
  const personStatus = (person) => String(person.status_carga || "NORMAL");
  const bar = (percent) => {
    const safe = Math.max(0, Math.min(160, Number(percent || 0)));
    return `<span class="allocation-load-bar" aria-label="${num(safe, 1)}% de carga"><i style="width:${safe}%"></i></span>`;
  };
  const taskName = (task, unit) =>
    task.imagem_nome || unit.imagem_nome || `Imagem #${task.imagem_id || "—"}`;

  function statusBadge(status) {
    return `<span class="allocation-status is-${statusClass(status)}">${esc(statusLabel(status))}</span>`;
  }

  function dailyMarkup(person) {
    const days = person.carga_dias || [];
    if (!days.length)
      return `<p class="allocation-muted">Sem carga nominal na janela consultada.</p>`;
    return `<details class="allocation-daily"><summary>Ver detalhe diário (${days.length} dias)</summary><div class="allocation-daily-table"><table><thead><tr><th>Data</th><th>Carga</th><th>Referências</th></tr></thead><tbody>${days.map((day) => `<tr><td>${date(day.data)}</td><td>${num(day.percentual, 1)}%</td><td>${(day.referencias || []).map((ref) => esc(`${ref.obra || "Obra"} · ${ref.codigo_etapa || ""}`)).join("<br>") || "—"}</td></tr>`).join("")}</tbody></table></div></details>`;
  }

  function taskMarkup(task, unit, personId) {
    const id = Number(task.tarefa_id || 0);
    const checked = state.selected.get(id)?.para_colaborador_id === personId;
    const current = Number(task.colaborador_id || 0);
    return `<label class="allocation-task-row"><input type="checkbox" data-task-id="${id}" data-current-person="${current}" ${checked ? "checked" : ""}><span><strong>${esc(taskName(task, unit))}</strong><small>${esc(task.status || "Sem status")}</small></span></label>`;
  }

  function personMarkup(person, project, candidate = false) {
    const status = personStatus(person);
    const load = Number(person.carga_periodo_percentual || 0);
    const tasks = Number(
      person.unidades_atribuidas ?? person.tarefas_atribuidas ?? 0,
    );
    const role = person.tipo_atuacao || (candidate ? "—" : "Responsável atual");
    const owned = candidate
      ? []
      : (project.tarefas_operacionais || []).flatMap((unit) =>
          (unit.tarefas || [])
            .filter(
              (task) =>
                Number(task.colaborador_id || 0) === Number(person.id || 0),
            )
            .map((task) => ({ task, unit })),
        );
    const conflictDays = (person.conflitos || []).length;
    const selectedCount = owned.filter(({ task }) =>
      state.selected.has(Number(task.tarefa_id)),
    ).length;
    const targetButton = candidate
      ? `<button type="button" class="allocation-move-target" data-target-person="${Number(person.colaborador_id || person.id || 0)}">Mover selecionadas para ${esc(person.nome)}</button>`
      : "";
    const validateButton =
      !candidate && load > 100.01 && status !== "SOBRECARGA_VALIDADA"
        ? `<button type="button" class="allocation-validation-button" data-validation-person="${Number(person.id)}">Validar capacidade excepcional</button>`
        : "";
    return `<details class="allocation-person ${conflictDays ? "is-conflict" : ""} is-${statusClass(status)}" ${candidate ? (state.selected.size ? "open" : "") : selectedCount ? "open" : ""}><summary><span class="allocation-person-title"><strong>${esc(person.nome || `Colaborador #${person.id}`)}</strong>${statusBadge(role)}</span><span class="allocation-person-load">${bar(load)} <b>${num(load, 1)}%</b></span><span class="allocation-person-meta">${tasks} unidade${tasks === 1 ? "" : "s"}</span></summary><div class="allocation-person-body">${candidate ? `<p class="allocation-candidate-note">${person.classificacao === "APOIO_DISPONIVEL" ? "Disponível como apoio." : statusLabel(person.classificacao || "RECOMENDADO")}</p>${targetButton}` : `<div class="allocation-person-toolbar"><span>${statusBadge(status)}</span>${conflictDays ? `<strong> ${conflictDays} dia${conflictDays === 1 ? "" : "s"} acima de 100%</strong>` : ""}${selectedCount ? `<span>${selectedCount} selecionada${selectedCount === 1 ? "" : "s"}</span>` : ""}</div><div class="allocation-task-list">${owned.length ? owned.map(({ task, unit }) => taskMarkup(task, unit, Number(person.id))).join("") : `<p class="allocation-muted">Nenhuma tarefa operacional atribuída.</p>`}</div><div class="allocation-person-actions">${validateButton}</div>${dailyMarkup(person)}`}</div></details>`;
  }

  function operationalMarkup(project) {
    const projection = state.operational[Number(project.entrega_id)];
    if (!projection)
      return `<div class="allocation-operational is-loading"><i class="fa-solid fa-spinner fa-spin"></i> Calculando projeção operacional…</div>`;
    if (projection.erro)
      return `<div class="allocation-operational is-warning">Não foi possível calcular a projeção: ${esc(projection.erro)}</div>`;
    const stages = projection.etapas || [];
    const explanation = stages
      .map((stage) => {
        const fronts = (stage.frentes || [])
          .map((front) => {
            const queue =
              (projection.filas_responsaveis || []).find(
                (item) =>
                  Number(item.colaborador_id) === Number(front.colaborador_id),
              ) || {};
            const previous = (queue.anteriores || [])
              .slice(0, 6)
              .map(
                (item) =>
                  `${item.unidade?.obra || "Obra"} · ${String(item.unidade?.etapa || "").replaceAll("_", " ")} (${num(item.esforco_pessoa_dia, 1)} pd)`,
              )
              .join("<br>");
            const queueBlocks = (queue.fila_completa || []).filter(
              (block) => String(block.codigo_etapa || "") === String(stage.codigo || ""),
            );
            const organizer = queueBlocks.length
              ? `<button type="button" class="queue-organize-button" data-open-queue data-queue-entrega="${Number(project.entrega_id)}" data-queue-stage="${esc(stage.codigo)}" data-queue-person="${Number(front.colaborador_id)}"><i class="fa-solid fa-list-ol"></i> Organizar fila</button>`
              : "";
            return `<li><strong>${esc(queue.nome || `Colaborador #${num(front.colaborador_id)}`)}</strong> · disponível ${date(queue.disponivel_em)} · ${num(front.esforco_pessoa_dia, 1)} pessoa-dia(s)${organizer}${previous ? `<small>${previous}</small>` : ""}</li>`;
          })
          .join("");
        return `<article><strong>${esc(String(stage.nome || stage.codigo || "").replaceAll("_", " "))}</strong><span>${date(stage.inicio_operacional_projetado)} → ${date(stage.fim_operacional_projetado)}</span>${fronts ? `<ul>${fronts}</ul>` : ""}</article>`;
      })
      .join("");
    const confirmed = (projection.confirmacoes_fila || []).length > 0;
    const snapshot = projection.projecao_confirmada;
    const queueInfo = confirmed
      ? `Fila confirmada${snapshot?.desatualizada ? " · projeção desatualizada" : ""}`
      : "Fila derivada";
    return `<section class="allocation-operational is-${statusClass(projection.status_operacional)}"><header><div><span>Projeção operacional · ${queueInfo}</span><strong>${date(projection.fim_operacional_projetado)}</strong></div>${statusBadge(projection.status_operacional)}</header><div class="allocation-operational-metrics"><div><span>Plano vigente</span><b>${date(project.inicio)} → ${date(project.limite)}</b></div><div><span>Margem restante</span><b>${projection.margem_operacional_dias_uteis == null ? "—" : `${num(projection.margem_operacional_dias_uteis)} dias úteis`}</b></div><div><span>Confiança da fila</span><b>${esc(projection.confianca_fila || "BAIXA")}</b></div></div>${snapshot?.desatualizada ? `<p class="queue-projection-stale"><i class="fa-solid fa-clock-rotate-left"></i> A ordem continua confirmada, mas a execução mudou desde a projeção de ${date(snapshot.confirmado_em?.slice(0, 10))}. Reavalie a data operacional.</p>` : ""}<details><summary>Por que essa data?</summary><p>${esc(projection.explicacao || "")}</p><div class="allocation-operational-explanation">${explanation || "Sem etapas operacionais materializadas."}</div></details></section>`;
  }

  function projectMarkup(project) {
    const people = project.pessoas || [];
    const candidates = project.candidatos || [];
    const divergence =
      Number(project.responsabilidades_divergentes || 0) > 0
        ? `<span class="allocation-alert is-warning"><i class="fa-solid fa-code-branch"></i> Responsabilidade divergente em ${num(project.responsabilidades_divergentes)} unidade(s).</span>`
        : "";
    const selected = people.reduce(
      (total, person) =>
        total +
        (project.tarefas_operacionais || [])
          .flatMap((unit) => unit.tarefas || [])
          .filter(
            (task) =>
              Number(task.colaborador_id || 0) === Number(person.id || 0) &&
              state.selected.has(Number(task.tarefa_id)),
          ).length,
      0,
    );
    const assisted =
      project.materializado > 0 && project.pendente_materializacao === 0
        ? `<button type="button" class="allocation-assisted-button" data-assisted-entrega="${Number(project.entrega_id)}" data-assisted-stage="${esc(project.codigo_etapa)}"><i class="fa-solid fa-wand-magic-sparkles"></i> Encontrar melhor distribuição</button>`
        : "";
    const selectionToolbar = selected
      ? `<div class="allocation-selection-toolbar"><span><strong>${selected}</strong> tarefa${selected === 1 ? "" : "s"} selecionada${selected === 1 ? "" : "s"}</span><button type="button" data-open-candidates>Escolher candidato</button></div>`
      : "";
    return `<details class="allocation-project ${hasIssue(project) ? "has-issue" : ""}" ${hasIssue(project) || selected ? "open" : ""}><summary><div><strong>${esc(project.obra)}</strong><span>${date(project.inicio)} → ${date(project.limite)} · ${num(project.pessoas_planejadas)} pessoa${Number(project.pessoas_planejadas) === 1 ? "" : "s"} planejada${Number(project.pessoas_planejadas) === 1 ? "" : "s"}</span></div>${statusBadge(project.status_alocacao)}</summary><div class="allocation-project-body"><div class="allocation-flow-metrics"><div><span>Planejado</span><strong>${num(project.planejado)}</strong></div><i class="fa-solid fa-arrow-right"></i><div><span>Materializado</span><strong>${num(project.materializado)}</strong></div><i class="fa-solid fa-arrow-right"></i><div><span>Alocado</span><strong>${num(project.alocado)}</strong></div></div>${operationalMarkup(project)}<div class="allocation-project-alerts">${Number(project.sem_responsavel) ? `<span class="allocation-alert is-danger"><i class="fa-solid fa-user-slash"></i> ${num(project.sem_responsavel)} tarefa(s) real(is) sem responsável</span>` : ""}${Number(project.pendente_materializacao) ? `<span class="allocation-alert is-warning"><i class="fa-solid fa-cubes-stacked"></i> ${num(project.pendente_materializacao)} pendente(s) de materialização</span>` : ""}${divergence}</div><div class="allocation-load-summary"><span>Carga planejada</span><strong>${num(project.carga_total_planejada_pessoa_dia, 1)} pessoa-dias</strong><em>${num(project.carga_nominal_atribuida_pessoa_dia, 1)} atribuídos · ${num(project.carga_nao_atribuida_pessoa_dia, 1)} sem responsável · ${num(project.carga_nao_materializada_pessoa_dia, 1)} não materializados</em></div>${selectionToolbar}${assisted}${people.length ? `<section><h5>Responsáveis atuais <span>${people.length}</span></h5><div class="allocation-person-grid">${people.map((person) => personMarkup(person, project)).join("")}</div></section>` : `<p class="allocation-muted">Não há responsável em tarefa operacional materializada.</p>`}<details class="allocation-candidates" ${selected ? "open" : ""}><summary><i class="fa-solid fa-user-plus"></i> Candidatos elegíveis <span>${candidates.length}</span></summary><div class="allocation-person-grid">${candidates.map((candidate) => personMarkup(candidate, project, true)).join("") || `<p class="allocation-muted">Nenhum colaborador elegível configurado.</p>`}</div></details></div></details>`;
  }

  function groupMarkup(group) {
    const ready = (group.projetos || []).every((project) =>
      ["ALOCADO", "ALOCACAO_VALIDADA_COM_EXCECAO"].includes(
        project.status_alocacao,
      ),
    );
    return `<article class="allocation-stage-card"><header><div><p>${esc(String(group.codigo_etapa || "").replaceAll("_", " "))}</p><h4>${esc(group.etapa)}</h4></div><span class="allocation-stage-state ${ready ? "is-ready" : "is-attention"}"><i class="fa-solid ${ready ? "fa-circle-check" : "fa-triangle-exclamation"}"></i> ${ready ? "Operação preparada" : "Requer atenção"}</span></header><div class="allocation-stage-totals"><div><span>Planejado</span><strong>${num(group.planejado)}</strong></div><div><span>Materializado</span><strong>${num(group.materializado)}</strong></div><div><span>Alocado</span><strong>${num(group.alocado)}</strong></div><div class="${Number(group.pendente_materializacao) ? "is-warning" : ""}"><span>Pendente</span><strong>${num(group.pendente_materializacao)}</strong></div></div><div class="allocation-project-list">${(group.projetos || []).map(projectMarkup).join("")}</div></article>`;
  }

  function render(data) {
    state.allocation = data;
    state.simulation = null;
    const summary = data.resumo || {};
    [
      ["allocation-planned", summary.planejado],
      ["allocation-materialized", summary.materializado],
      ["allocation-unassigned", summary.sem_responsavel],
      ["allocation-pending", summary.pendente_materializacao],
      ["allocation-overloads", summary.sobrecargas],
      ["allocation-awaiting", summary.aguardando_validacao],
    ].forEach(([id, value]) => {
      const node = $(`#${id}`);
      if (node) node.textContent = num(value);
    });
    $("#allocation-period-label").textContent =
      `${date(data.periodo?.inicio)} → ${date(data.periodo?.fim)}`;
    $("#allocation-stage-list").innerHTML = (data.grupos || [])
      .map(groupMarkup)
      .join("");
    $("#allocation-empty").hidden = (data.grupos || []).length > 0;
    if (!(data.grupos || []).length)
      $("#allocation-empty").textContent =
        "Não há etapas planejadas vigentes neste período.";
    const notes = $("#allocation-notes");
    const exceptions = data.excecoes || [];
    notes.hidden = !exceptions.length;
    notes.innerHTML = exceptions.length
      ? `<h3><i class="fa-solid fa-circle-info"></i> Observações técnicas</h3>${exceptions.map((item) => `<p><strong>${esc(String(item.codigo || "").replaceAll("_", " "))}</strong> · ${esc(item.mensagem || "Verifique a consistência do planejamento e da operação.")}</p>`).join("")}<small>${esc(data.formula_carga || "")}</small>`
      : "";
    closeSimulation();
  }

  function setAction(message, type = "") {
    const node = $("#allocation-action-status");
    if (!node) return;
    node.hidden = !message;
    node.className = `allocation-action-status ${type ? `is-${type}` : ""}`;
    node.textContent = message || "";
  }

  async function loadOperational() {
    const ids = [
      ...new Set(
        (state.allocation?.grupos || [])
          .flatMap((group) => group.projetos || [])
          .map((project) => Number(project.entrega_id))
          .filter(Boolean),
      ),
    ];
    if (!ids.length) return;
    try {
      const response = await fetch(
        `${allocationUrls().projection}?${new URLSearchParams({ entrega_ids: ids.join(",") })}`,
      );
      const payload = await response.json();
      if (!response.ok || !payload.success)
        throw new Error(
          payload.message ||
            "Não foi possível calcular a projeção operacional.",
        );
      const projections = payload.projecao?.projecoes || [];
      state.operational = Object.fromEntries(
        projections.map((projection) => [
          Number(projection.entrega_id),
          projection,
        ]),
      );
    } catch (error) {
      ids.forEach((id) => {
        state.operational[id] = { erro: error.message };
      });
    }
    if (state.allocation) render(state.allocation);
  }

  function queueOrder() {
    return (state.queue?.order || []).map((item) => ({
      entrega_id: Number(item.entrega_id),
      codigo_etapa: String(item.codigo_etapa || ""),
      referencia_fila: String(item.referencia_fila || ""),
    }));
  }

  function queueImpactMarkup(impact) {
    const before = impact.antes || {};
    const after = impact.depois || {};
    const delta = Number(impact.variacao_fim_dias_uteis || 0);
    const change = delta === 0 ? "sem alteração" : delta < 0 ? `${Math.abs(delta)} dia(s) antes` : `${delta} dia(s) depois`;
    return `<article class="queue-impact ${delta > 0 ? "is-negative" : delta < 0 ? "is-positive" : ""}"><strong>${esc(impact.obra || "Obra")}</strong><span>${date(before.inicio_operacional_projetado)} → ${date(before.fim_operacional_projetado)} <i class="fa-solid fa-arrow-right"></i> ${date(after.inicio_operacional_projetado)} → ${date(after.fim_operacional_projetado)}</span><b>${esc(change)} · margem ${num(before.margem_operacional_dias_uteis)} → ${num(after.margem_operacional_dias_uteis)} dias úteis</b>${statusBadge(after.status_operacional)}</article>`;
  }

  function renderQueueDialog() {
    const queue = state.queue;
    const content = $("#queue-dialog-content");
    if (!queue || !content) return;
    const simulation = queue.simulation;
    const items = queue.order || [];
    const hasChanges = JSON.stringify(queue.initial) !== JSON.stringify(queueOrder());
    const classification = simulation?.classificacao;
    const current = simulation?.fila_atual || queue.blocks;
    content.innerHTML = `<div class="queue-dialog-summary"><div><span>Responsável</span><strong>${esc(queue.personName)}</strong></div><div><span>Etapa</span><strong>${esc(String(queue.stage).replaceAll("_", " "))}</strong></div><div><span>Origem</span><strong>${esc(queue.type || "DERIVADA")}</strong></div></div><p class="queue-dialog-intro">A ordem organiza blocos de trabalho da mesma função. Não altera tarefas, responsáveis, baseline ou prazo individual.</p><section class="queue-list"><h3>Ordem proposta</h3>${items.map((item, index) => { const source = current.find((block) => String(block.fila_chave || "") === String(item.referencia_fila || "")) || item; return `<div class="queue-item ${Number(item.entrega_id) === Number(queue.entregaId) ? "is-focus" : ""}"><b>${index + 1}</b><div><strong>${esc(source.obra || `Entrega #${item.entrega_id}`)}</strong><span>${num(source.unidades)} unidade(s) · ${num(source.esforco_pessoa_dia, 1)} pessoa-dia(s) · prioridade ${num(source.prioridade)}</span></div><div class="queue-item-actions"><button type="button" data-queue-move="up" data-queue-index="${index}" ${index === 0 ? "disabled" : ""} aria-label="Subir ${esc(source.obra || "bloco")}"><i class="fa-solid fa-arrow-up"></i></button><button type="button" data-queue-move="down" data-queue-index="${index}" ${index === items.length - 1 ? "disabled" : ""} aria-label="Descer ${esc(source.obra || "bloco")}"><i class="fa-solid fa-arrow-down"></i></button></div></div>`; }).join("")}</section>${simulation ? `<section class="queue-comparison"><header><div><p>Atual → simulado</p><h3>${esc(statusLabel(classification))}</h3></div>${statusBadge(classification)}</header><p>${classification === "TRANSFERE_PROBLEMA" ? "A mudança melhora a obra foco, mas cria atraso projetado em outra obra." : classification === "SEM_GANHO" ? "A ordem proposta não altera o fim operacional da obra foco." : "Veja todos os impactos antes de confirmar."}</p><div class="queue-impact-list">${(simulation.impactos || []).map(queueImpactMarkup).join("")}</div>${simulation.heuristica ? `<small>${esc(simulation.heuristica)}</small>` : ""}</section>` : `<p class="queue-dialog-hint">Use as setas para simular outra ordem. Nada foi salvo.</p>`}`;
    $("#queue-dialog-confirm").disabled = !simulation || !hasChanges;
  }

  function closeQueueDialog() {
    $("#queue-dialog")?.close();
    state.queue = null;
  }

  function openQueue(entregaId, stage, personId) {
    const projection = state.operational[Number(entregaId)];
    const person = (projection?.filas_responsaveis || []).find(
      (item) => Number(item.colaborador_id) === Number(personId),
    );
    const blocks = (person?.fila_completa || []).filter(
      (block) => String(block.codigo_etapa || "") === String(stage || ""),
    );
    if (!projection || !person || !blocks.length)
      return setAction("Não foi possível carregar a fila deste responsável.", "warning");
    const order = blocks.map((block) => ({
      entrega_id: Number(block.entrega_id),
      codigo_etapa: String(block.codigo_etapa),
      referencia_fila: String(block.fila_chave || ""),
    }));
    state.queue = {
      entregaId: Number(entregaId), stage: String(stage), personId: Number(personId),
      personName: person.nome || `Colaborador #${personId}`, type: person.tipo_fila,
      blocks, order, initial: order.map((item) => ({ ...item })),
      fingerprint: person.fingerprints_etapas?.[stage] || "", simulation: null,
    };
    renderQueueDialog();
    const dialog = $("#queue-dialog");
    if (dialog?.showModal) dialog.showModal();
    else dialog?.setAttribute("open", "open");
  }

  async function simulateQueue() {
    const queue = state.queue;
    if (!queue) return;
    setAction("Recalculando impacto da fila…");
    try {
      const response = await fetch(allocationUrls().queueSimulate, {
        method: "POST", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ colaborador_id: queue.personId, entrega_id: queue.entregaId, codigo_etapa: queue.stage, ordem: queueOrder(), fingerprint_atual: queue.fingerprint }),
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) throw new Error(payload.message || "Não foi possível simular a fila.");
      queue.simulation = payload.simulacao;
      renderQueueDialog();
      setAction("Simulação pronta. Nenhuma alteração foi salva.", "success");
    } catch (error) {
      setAction(error.message, "danger");
    }
  }

  async function suggestQueue() {
    const queue = state.queue;
    if (!queue) return;
    setAction("Testando posições possíveis da obra foco…");
    try {
      const response = await fetch(allocationUrls().queueSuggest, {
        method: "POST", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ colaborador_id: queue.personId, entrega_id: queue.entregaId, codigo_etapa: queue.stage, fingerprint_atual: queue.fingerprint }),
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) throw new Error(payload.message || "Não foi possível encontrar outra ordem.");
      queue.order = payload.simulacao?.ordem_proposta || queue.order;
      queue.simulation = payload.simulacao;
      renderQueueDialog();
      setAction("Melhor ordem limitada encontrada. Revise o impacto antes de confirmar.", "success");
    } catch (error) { setAction(error.message, "danger"); }
  }

  async function confirmQueue() {
    const queue = state.queue;
    if (!queue?.simulation) return;
    const requiresReason = queue.simulation.classificacao === "TRANSFERE_PROBLEMA";
    const reason = window.prompt(requiresReason ? "Esta ordem transfere problema. Informe o motivo obrigatório:" : "Motivo da decisão (opcional):", "");
    if (reason === null || (requiresReason && !reason.trim())) return setAction("A confirmação foi cancelada: informe o motivo para uma transferência de problema.", "warning");
    setAction("Revalidando, registrando fila e gravando projeções…");
    try {
      const response = await fetch(allocationUrls().queueConfirm, {
        method: "POST", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ confirmado: true, colaborador_id: queue.personId, entrega_id: queue.entregaId, codigo_etapa: queue.stage, ordem: queueOrder(), fingerprint_atual: queue.fingerprint, motivo: reason.trim() }),
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) throw new Error(payload.message || "Não foi possível confirmar a fila.");
      closeQueueDialog();
      setAction("Fila confirmada, projeção registrada e histórico preservado.", "success");
      await load();
    } catch (error) { setAction(error.message, "danger"); }
  }

  function selectedMovements(targetId) {
    return [...state.selected.values()].map((item) => ({
      tarefa_id: item.tarefa_id,
      de_colaborador_id: item.de_colaborador_id,
      para_colaborador_id: Number(targetId || item.para_colaborador_id),
    }));
  }

  function closeSimulation() {
    state.simulation = null;
    const panel = $("#allocation-simulation-panel");
    if (panel) panel.hidden = true;
  }

  function impactMarkup(item) {
    const before = item.antes || {};
    const after = item.depois || {};
    const status = after.status_carga || "NORMAL";
    return `<article class="allocation-impact-row"><strong>${esc(item.nome || `Colaborador #${item.colaborador_id}`)}</strong><span>${num(before.carga_percentual, 1)}% → ${num(after.carga_percentual, 1)}%</span>${statusBadge(status)}</article>`;
  }

  function showSimulation(simulation) {
    state.simulation = simulation;
    const panel = $("#allocation-simulation-panel");
    const content = $("#allocation-simulation-content");
    if (!panel || !content) return;
    const result = simulation.resultado || {};
    const requiresValidation = Boolean(simulation.requer_validacao);
    content.innerHTML = `<div class="allocation-simulation-summary"><p>${result.sobrecargas_antes || 0} sobrecarga(s) → <strong>${result.sobrecargas_depois || 0}</strong></p><p>${result.conflitos_antes || 0} conflito(s) → <strong>${result.conflitos_depois || 0}</strong></p><p class="${requiresValidation ? "is-warning" : "is-success"}">${requiresValidation ? "A simulação ainda possui sobrecarga. Valide a capacidade excepcional antes de aplicar." : "Distribuição sem sobrecarga nominal pendente."}</p></div><div class="allocation-impact-list">${(simulation.impactos || []).map(impactMarkup).join("")}</div>`;
    const apply = $("#allocation-simulation-apply");
    apply.disabled = requiresValidation;
    apply.title = requiresValidation
      ? "Valide as sobrecargas antes de aplicar."
      : "Aplicar redistribuição";
    panel.hidden = false;
    panel.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }

  async function simulate(movements) {
    if (!movements.length)
      return setAction(
        "Selecione pelo menos uma tarefa e um colaborador destino.",
        "warning",
      );
    setAction("Calculando impacto global…");
    try {
      const response = await fetch(allocationUrls().simulate, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ...period(), movimentos: movements }),
      });
      const payload = await response.json();
      if (!response.ok || !payload.success)
        throw new Error(
          payload.message || "Não foi possível simular a redistribuição.",
        );
      setAction("Simulação pronta. Nenhuma tarefa foi alterada.", "success");
      showSimulation(payload.simulacao);
    } catch (error) {
      setAction(error.message, "danger");
    }
  }

  async function suggestDistribution(button) {
    setAction("Buscando a melhor distribuição determinística…");
    button.disabled = true;
    try {
      const response = await fetch(allocationUrls().suggest, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          ...period(),
          entrega_id: Number(button.dataset.assistedEntrega),
          codigo_etapa: button.dataset.assistedStage,
        }),
      });
      const payload = await response.json();
      if (!response.ok || !payload.success)
        throw new Error(
          payload.message || "Não foi possível buscar uma distribuição.",
        );
      const suggestion = payload.sugestao || {};
      if (!(suggestion.movimentos || []).length) {
        setAction(
          suggestion.mensagem || "Nenhuma distribuição melhor foi encontrada.",
          "warning",
        );
        return;
      }
      setAction("Distribuição sugerida. Nada foi alterado.", "success");
      showSimulation(suggestion);
    } catch (error) {
      setAction(error.message, "danger");
    } finally {
      button.disabled = false;
    }
  }

  async function applySimulation() {
    if (!state.simulation || !state.allocation) return;
    const observation =
      window.prompt("Observação da redistribuição (opcional):", "") ?? "";
    const movimentos = state.simulation.movimentos || [];
    setAction("Revalidando e aplicando…");
    try {
      const response = await fetch(allocationUrls().apply, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          ...period(),
          movimentos,
          fingerprint_atual: state.simulation.fingerprint_atual,
          observacao,
          confirmado: true,
        }),
      });
      const payload = await response.json();
      if (!response.ok || !payload.success)
        throw new Error(
          payload.message || "A redistribuição não foi aplicada.",
        );
      setAction("Redistribuição aplicada e auditada.", "success");
      await load();
    } catch (error) {
      setAction(error.message, "danger");
    }
  }

  function openValidation(personId) {
    const item = state.allocation?.grupos
      ?.flatMap((group) => group.projetos || [])
      .map((project) => ({
        project,
        person: (project.pessoas || []).find(
          (person) => Number(person.id) === Number(personId),
        ),
      }))
      .find((item) => item.person);
    if (!item) return;
    state.validation = { project: item.project, person: item.person };
    const dialog = $("#allocation-validation-dialog");
    $("#allocation-validation-summary").textContent =
      `${item.person.nome} · ${item.project.obra} · ${item.project.etapa} · ${date(item.project.inicio)} → ${date(item.project.limite)} · carga ${num(item.person.carga_periodo_percentual, 1)}% (${(item.person.conflitos || []).length} dia(s) acima de 100%).`;
    $("#allocation-validation-observation").value = "";
    $("#allocation-validation-confirm").checked = false;
    if (dialog?.showModal) dialog.showModal();
    else dialog?.setAttribute("open", "open");
  }

  async function submitValidation(event) {
    event.preventDefault();
    if (!state.validation) return;
    const person = state.validation.person;
    const project = state.validation.project;
    const observation = $("#allocation-validation-observation").value.trim();
    if (!$("#allocation-validation-confirm").checked || observation.length < 5)
      return setAction(
        "Confirme a validação e informe uma observação de pelo menos 5 caracteres.",
        "warning",
      );
    setAction("Registrando validação contextual…");
    try {
      const response = await fetch(allocationUrls().validate, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          ...period(),
          confirmado: true,
          observacao: observation,
          entrega_id: project.entrega_id,
          codigo_etapa: project.codigo_etapa,
          colaborador_id: person.id,
          fingerprint_carga: person.fingerprint_carga,
        }),
      });
      const payload = await response.json();
      if (!response.ok || !payload.success)
        throw new Error(
          payload.message || "Não foi possível registrar a validação.",
        );
      $("#allocation-validation-dialog")?.close();
      state.validation = null;
      setAction(
        "Capacidade excepcional validada para este contexto.",
        "success",
      );
      await load();
    } catch (error) {
      setAction(error.message, "danger");
    }
  }

  async function load() {
    const { inicio, fim } = period();
    if (!inicio || !fim || state.mode !== "allocation") return;
    const key = `${inicio}:${fim}`;
    state.pendingKey = key;
    $("#allocation-loading").hidden = false;
    $("#allocation-empty").hidden = true;
    try {
      const response = await fetch(
        `${document.body.dataset.allocationApiUrl}?${new URLSearchParams({ inicio, fim })}`,
      );
      const payload = await response.json();
      if (!response.ok || !payload.success)
        throw new Error(
          payload.message || "Não foi possível consultar a alocação.",
        );
      if (state.pendingKey !== key) return;
      state.loadedKey = key;
      state.selected.clear();
      state.operational = {};
      render(payload.alocacao);
      loadOperational();
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
    $("#allocation-stage-list")?.addEventListener("change", (event) => {
      const input = event.target.closest("input[data-task-id]");
      if (!input) return;
      const id = Number(input.dataset.taskId);
      const current = Number(input.dataset.currentPerson || 0);
      if (input.checked)
        state.selected.set(id, {
          tarefa_id: id,
          de_colaborador_id: current,
          para_colaborador_id: current,
        });
      else state.selected.delete(id);
      render(state.allocation);
    });
    $("#allocation-stage-list")?.addEventListener("click", (event) => {
      const target = event.target.closest("[data-target-person]");
      const openQueueButton = event.target.closest("[data-open-queue]");
      const validation = event.target.closest("[data-validation-person]");
      const assisted = event.target.closest("[data-assisted-entrega]");
      const openCandidates = event.target.closest("[data-open-candidates]");
      if (validation) {
        event.preventDefault();
        openValidation(Number(validation.dataset.validationPerson));
        return;
      }
      if (openQueueButton) {
        event.preventDefault();
        event.stopPropagation();
        openQueue(
          Number(openQueueButton.dataset.queueEntrega),
          openQueueButton.dataset.queueStage,
          Number(openQueueButton.dataset.queuePerson),
        );
        return;
      }
      if (assisted) {
        event.preventDefault();
        suggestDistribution(assisted);
        return;
      }
      if (openCandidates) {
        event.preventDefault();
        event.stopPropagation();
        const project = openCandidates.closest(".allocation-project");
        const candidates = project?.querySelector(".allocation-candidates");
        if (candidates) candidates.open = true;
        project
          ?.querySelectorAll(".allocation-person.is-candidate")
          .forEach((candidate) => {
            candidate.open = true;
          });
        return;
      }
      if (target) {
        event.preventDefault();
        event.stopPropagation();
        simulate(selectedMovements(Number(target.dataset.targetPerson)));
      }
    });
    $("#allocation-simulation-apply")?.addEventListener(
      "click",
      applySimulation,
    );
    $("#allocation-simulation-cancel")?.addEventListener(
      "click",
      closeSimulation,
    );
    $("#allocation-simulation-close")?.addEventListener(
      "click",
      closeSimulation,
    );
    $("#allocation-validation-cancel")?.addEventListener("click", () =>
      $("#allocation-validation-dialog")?.close(),
    );
    $("#allocation-validation-form")?.addEventListener(
      "submit",
      submitValidation,
    );
    $("#queue-dialog-close")?.addEventListener("click", closeQueueDialog);
    $("#queue-dialog-cancel")?.addEventListener("click", closeQueueDialog);
    $("#queue-dialog-suggest")?.addEventListener("click", suggestQueue);
    $("#queue-dialog-confirm")?.addEventListener("click", confirmQueue);
    $("#queue-dialog-content")?.addEventListener("click", (event) => {
      const control = event.target.closest("[data-queue-move]");
      if (!control || !state.queue) return;
      const index = Number(control.dataset.queueIndex);
      const destination = control.dataset.queueMove === "up" ? index - 1 : index + 1;
      if (destination < 0 || destination >= state.queue.order.length) return;
      const [item] = state.queue.order.splice(index, 1);
      state.queue.order.splice(destination, 0, item);
      state.queue.simulation = null;
      renderQueueDialog();
      simulateQueue();
    });
  });
})();
