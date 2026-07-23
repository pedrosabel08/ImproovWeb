(() => {
  "use strict";
  const $ = (id) => document.getElementById(id);
  const root = location.pathname.split("/Fotografico")[0] || "/ImproovWeb";
  const state = {
    plans: [],
    plan: null,
    collaborators: [],
    selectedPin: null,
    csrf: "",
    saving: 0,
    pendingPinDeletes: new Map(),
  };
  let planBroadcast = null;
  try {
    if (typeof BroadcastChannel !== "undefined") {
      planBroadcast = new BroadcastChannel("improov-fotografico");
      planBroadcast.onmessage = (event) =>
        window.dispatchEvent(
          new CustomEvent("improov:fotograficoUpdated", {
            detail: event.data || {},
          }),
        );
    }
  } catch (_) {
    planBroadcast = null;
  }
  const broadcastPlanUpdate = (data) => {
    try {
      planBroadcast?.postMessage(data);
    } catch (_) {}
  };
  const periods = [
    ["DIURNO", "Diurno"],
    ["GOLDEN_HOUR", "Golden Hour"],
    ["BLUE_HOUR", "Blue Hour"],
    ["NOTURNO", "Noturno"],
  ];
  let images = [];
  const pinSaveChains = new Map(),
    pinSaveTimers = new Map(),
    ownRealtimeEventIds = new Map(),
    seenRealtimeEventIds = new Map();
  const realtimeEventTtl = 60_000;
  function rememberRealtimeEvent(bucket, id) {
    const value = String(id || "");
    if (!value) return;
    const now = Date.now();
    bucket.set(value, now);
    for (const [knownId, timestamp] of bucket) {
      if (now - timestamp > realtimeEventTtl) bucket.delete(knownId);
    }
  }
  const isOwnRealtimeEvent = (id) =>
    !!id && ownRealtimeEventIds.has(String(id));
  const isSeenRealtimeEvent = (id) =>
    !!id && seenRealtimeEventIds.has(String(id));
  const labels = {
    PLANO_A_FAZER: "Plano a fazer",
    EM_ELABORACAO: "Em elaboração",
    PRONTO_PARA_PUBLICAR: "Pronto para publicar",
    PRONTO_EXECUCAO: "Pronto para execução",
    EM_CONFERENCIA: "Em conferência",
    CONCLUIDO: "Concluído",
    HOLD: "Em HOLD",
    CANCELADO: "Cancelado",
  };
  const value = (v) => (v == null || v === "" ? "—" : String(v));
  const esc = (v) => {
    const e = document.createElement("span");
    e.textContent = value(v);
    return e.innerHTML;
  };
  const when = (v, time = false) =>
    v
      ? new Intl.DateTimeFormat("pt-BR", {
          dateStyle: "short",
          ...(time ? { timeStyle: "short" } : {}),
        }).format(new Date(String(v).replace(" ", "T")))
      : "—";
  const eventId = () =>
    `foto-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
  let planMutationQueue = Promise.resolve();
  function queuePlanMutation(task) {
    const queued = planMutationQueue.catch(() => {}).then(task);
    planMutationQueue = queued.catch(() => {});
    return queued;
  }
  function refreshQueuedPlanVersion(data, planId) {
    if (
      state.plan &&
      Number(state.plan.id) === Number(planId) &&
      Number(data?.lock_version)
    ) {
      state.plan.lock_version = Number(data.lock_version);
      state.csrf = data.csrf_token || state.csrf;
    }
  }
  function notice(message, error = false) {
    const box = $("fotoMessage");
    box.textContent = message;
    box.hidden = false;
    box.classList.toggle("is-error", error);
    clearTimeout(notice.timer);
    notice.timer = setTimeout(() => (box.hidden = true), 5000);
  }
  async function api(action, { method = "GET", data = null } = {}) {
    const request = async () => {
      const requestData = { ...(data || {}) };
      if (
        method !== "GET" &&
        requestData.plano_id &&
        Object.hasOwn(requestData, "version") &&
        Number(requestData.plano_id) === Number(state.plan?.id)
      )
        requestData.version = state.plan.lock_version;
      const url = new URL(`${root}/Fotografico/api.php`, location.origin);
      url.searchParams.set("action", action);
      if (method === "GET" && data)
        Object.entries(requestData).forEach(([k, v]) =>
          url.searchParams.set(k, v),
        );
      const options = { method, headers: {} };
      let clientEventId = "";
      if (method !== "GET") {
        clientEventId = eventId();
        options.headers = {
          "Content-Type": "application/json",
          "X-CSRF-Token": state.csrf,
        };
        options.body = JSON.stringify({
          ...requestData,
          client_event_id: clientEventId,
        });
        rememberRealtimeEvent(ownRealtimeEventIds, clientEventId);
      }
      const response = await fetch(url, options);
      const body = await response.json().catch(() => null);
      if (!response.ok || !body?.success) {
        const error = new Error(
          body?.error?.message || "Não foi possível concluir a ação.",
        );
        error.code = body?.error?.code;
        throw error;
      }
      if (method !== "GET")
        broadcastPlanUpdate({
          plan_id: Number(body.data?.id || requestData.plano_id || 0),
          event: "plan.updated",
          action,
          client_event_id: clientEventId,
        });
      refreshQueuedPlanVersion(
        body.data,
        Number(body.data?.id || requestData.plano_id || 0),
      );
      return body.data;
    };
    return method !== "GET" && data?.plano_id
      ? queuePlanMutation(request)
      : request();
  }
  async function upload(type, entityId, file) {
    return queuePlanMutation(async () => {
      const form = new FormData(),
        clientEventId = eventId();
      rememberRealtimeEvent(ownRealtimeEventIds, clientEventId);
      form.append("plano_id", state.plan.id);
      form.append("version", state.plan.lock_version);
      form.append("tipo", type);
      form.append("entidade_id", entityId);
      form.append("client_event_id", clientEventId);
      form.append("arquivo", file);
      const response = await fetch(`${root}/Fotografico/upload.php`, {
        method: "POST",
        headers: { "X-CSRF-Token": state.csrf },
        body: form,
      });
      const body = await response.json().catch(() => null);
      if (!response.ok || !body?.success)
        throw new Error(body?.error?.message || "Falha no upload.");
      broadcastPlanUpdate({
        plan_id: Number(state.plan.id),
        event: "plan.updated",
        action: "upload",
        client_event_id: clientEventId,
      });
      refreshQueuedPlanVersion(body.data, state.plan.id);
      return body.data;
    });
  }
  const draftEditable = () =>
    !!state.plan?.permissions?.edit &&
    ["PLANO_A_FAZER", "EM_ELABORACAO", "PRONTO_PARA_PUBLICAR"].includes(
      state.plan.status,
    ) &&
    activeVersion()?.status === "RASCUNHO";

  const activeVersion = () => state.plan?.versao_ativa;
  const checklist = () =>
    state.plan?.checklist ||
    state.plan?.prontidao || {
      pronto: false,
      total: 0,
      completed: 0,
      pending: [],
      bloqueios: [],
    };
  const pendingItems = () => checklist().pending || checklist().bloqueios || [];
  const isPublished = () => activeVersion()?.status === "PUBLICADA";
  const mapPath = () =>
    activeVersion()?.mapa_caminho
      ? `${root}/${activeVersion().mapa_caminho.replace(/^\//, "")}`
      : "";
  function applyMutationDelta(delta, selected = state.selectedPin) {
    if (!state.plan) return;
    Object.assign(state.plan, {
      id: delta.id || state.plan.id,
      status: delta.status || state.plan.status,
      lock_version: delta.lock_version || state.plan.lock_version,
      responsavel_execucao_id: Object.hasOwn(delta, "responsavel_execucao_id")
        ? delta.responsavel_execucao_id
        : state.plan.responsavel_execucao_id,
      data_planejada: Object.hasOwn(delta, "data_planejada")
        ? delta.data_planejada
        : state.plan.data_planejada,
      local: Object.hasOwn(delta, "local") ? delta.local : state.plan.local,
      maps_url: Object.hasOwn(delta, "maps_url")
        ? delta.maps_url
        : state.plan.maps_url,
    });
    if (delta.resumo?.prontidao) {
      state.plan.prontidao = delta.resumo.prontidao;
      state.plan.checklist = delta.resumo.prontidao;
    }
    if (delta.pin) {
      const index = (state.plan.posicoes || []).findIndex(
        (pos) => String(pos.id) === String(delta.pin.id),
      );
      if (index >= 0) state.plan.posicoes[index] = delta.pin;
      else (state.plan.posicoes ||= []).push(delta.pin);
    }
    if (delta.deleted_pin_id)
      state.plan.posicoes = (state.plan.posicoes || []).filter(
        (pos) => String(pos.id) !== String(delta.deleted_pin_id),
      );
    (delta.affected_images || []).forEach((changed) => {
      const index = (state.plan.imagens || []).findIndex(
        (image) => String(image.id) === String(changed.id),
      );
      if (index >= 0) Object.assign(state.plan.imagens[index], changed);
    });
    state.selectedPin = selected;
    render();
  }
  async function applyOrRefreshMutation(delta, selected = state.selectedPin) {
    applyMutationDelta(delta, selected);
    if (delta.refresh_required) await loadPlan(state.plan.id, selected);
  }
  function applyPlan(plan, selected = state.selectedPin) {
    const pendingDeletes = state.pendingPinDeletes;
    const currentPositions = state.plan?.posicoes || [];
    const pendingEdits = new Set([
      ...pinSaveTimers.keys(),
      ...pinSaveChains.keys(),
    ]);
    const positions = (plan.posicoes || [])
      .filter((pos) => !pendingDeletes.has(String(pos.id)))
      .map((pos) => {
        const local = currentPositions.find(
          (item) => String(item.id) === String(pos.id),
        );
        return pendingEdits.has(String(pos.id)) && local
          ? { ...pos, ...local, id: pos.id }
          : pos;
      });
    currentPositions
      .filter((pos) => pos.pendente_local)
      .forEach((local) => {
        const alreadyPersisted = positions.some(
          (pos) =>
            String(pos.id) === String(local.id) ||
            String(pos.codigo) === String(local.codigo),
        );
        if (!alreadyPersisted) positions.push(local);
      });
    plan = { ...plan, posicoes: positions };
    state.plan = plan;
    images = plan.imagens || [];
    state.csrf = plan.csrf_token || state.csrf;
    state.selectedPin = (plan.posicoes || []).some(
      (p) => String(p.id) === String(selected),
    )
      ? selected
      : null;
    render();
  }
  async function loadCollaborators() {
    state.collaborators = (await api("collaborators")).colaboradores || [];
  }
  async function loadPlans() {
    const data = await api("list");
    state.plans = data.planos || [];
    state.csrf = data.csrf_token || state.csrf;
    $("fotoNewCampaign").hidden = !data.can_manage;
    renderPlanList();
  }
  async function loadPlan(id, selected) {
    applyPlan(await api("get", { data: { plano_id: id } }), selected);
    $("fotoListView").hidden = true;
    $("fotoDetailView").hidden = false;
  }
  function renderPlanList() {
    const q = $("fotoSearch").value.trim().toLowerCase(),
      filter = $("fotoStatusFilter").value,
      out = $("fotoPlanList");
    out.replaceChildren();
    const rows = state.plans.filter(
      (p) =>
        (!filter || p.status === filter) &&
        `${p.nome_obra} ${p.nomenclatura} ${p.responsavel_plano_nome || ""}`
          .toLowerCase()
          .includes(q),
    );
    if (!rows.length) {
      out.innerHTML =
        '<div class="foto-card foto-muted">Nenhum plano fotográfico encontrado.</div>';
      return;
    }
    rows.forEach((p) => {
      const button = document.createElement("button");
      button.className = "foto-plan-card";
      const charge = p.proxima_cobranca
        ? new Date(String(p.proxima_cobranca).replace(" ", "T"))
        : null;
      const overdueCharge =
        charge &&
        !Number.isNaN(charge.getTime()) &&
        charge.getTime() < Date.now();
      const chargeText = charge
        ? `${overdueCharge ? "Cobrança atrasada desde" : "Próxima cobrança:"} ${when(p.proxima_cobranca, true)}`
        : "Sem cobrança programada";
      button.innerHTML = `<header><span class="foto-status" data-status="${esc(p.status)}">${esc(labels[p.status] || p.status)}</span><small>Campanha ${p.campanha_numero}</small></header><h2>${esc(p.nomenclatura || p.nome_obra)}</h2><p>${esc(p.nome_obra)}</p><footer><small>${esc(p.responsavel_execucao_nome || "Sem executor")}</small><small>${p.pendencias_abertas || 0} pendências</small></footer><small class="foto-plan-charge${overdueCharge ? " is-overdue" : ""}">${esc(chargeText)}</small>`;
      button.onclick = () =>
        loadPlan(p.id).catch((e) => notice(e.message, true));
      out.append(button);
    });
  }
  const stat = (label, v, detail = "") =>
    `<div class="foto-stat"><small>${esc(label)}</small><strong>${esc(v)}</strong>${detail ? `<em>${esc(detail)}</em>` : ""}</div>`;
  const status = (s) =>
    `<span class="foto-status" data-status="${esc(s)}">${esc(labels[s] || s)}</span>`;
  function renderSectionBadge(id, items, completeLabel = "Completo") {
    document.querySelectorAll(`#${id}`).forEach((node) => {
      node.className = `foto-section-badge${items.length ? " is-pending" : " is-complete"}`;
      node.textContent = items.length
        ? `${items.length} pendência${items.length === 1 ? "" : "s"}`
        : completeLabel;
    });
  }
  function renderPlanGuidance() {
    const p = state.plan,
      check = checklist(),
      pending = pendingItems(),
      published = isPublished();
    const guidance = $("fotoPlanGuidance"),
      about = $("fotoPlanAbout");
    if (!guidance || !about) return;
    if (published) {
      guidance.className = "foto-plan-guidance is-published";
      guidance.innerHTML = `<strong><i class="fa-solid fa-lock"></i> Plano publicado</strong><p>O planejamento está bloqueado para edição. Caso precise alterar, crie uma revisão.</p>`;
    } else if (check.pronto) {
      guidance.className = "foto-plan-guidance is-ready";
      guidance.innerHTML = `<strong><i class="fa-solid fa-circle-check"></i> Plano pronto para publicação</strong><p>Todos os requisitos foram atendidos. Revise as informações e publique o plano.</p><button type="button" class="foto-btn foto-btn-primary" data-publish-plan>Publicar plano</button>`;
    } else {
      guidance.className = "foto-plan-guidance";
      guidance.innerHTML = `<strong><i class="fa-solid fa-triangle-exclamation"></i> Plano incompleto</strong><p>${check.completed || 0} de ${check.total || pending.length} requisitos concluídos.</p><ul>${pending
        .slice(0, 4)
        .map((item) => `<li>${esc(item.mensagem)}</li>`)
        .join(
          "",
        )}</ul><button type="button" class="foto-btn foto-btn-secondary" data-open-pending>Ver pendências</button>`;
    }
    const hasPending = (code) => pending.some((item) => item.codigo === code);
    const pointPending = pending.filter((item) =>
      String(item.codigo || "").startsWith("PONTO_"),
    ).length;
    const imagePending = pending.filter(
      (item) =>
        String(item.codigo || "").startsWith("IMAGEM_") ||
        item.codigo === "EXCLUSAO_SEM_MOTIVO",
    ).length;
    const checklistLine = (ok, text) =>
      `<li class="${ok ? "is-ok" : "is-pending"}"><i class="fa-solid ${ok ? "fa-circle-check" : "fa-circle-exclamation"}"></i>${esc(text)}</li>`;
    if (published) {
      guidance.innerHTML = `<div class="foto-guidance-state"><span class="foto-guidance-icon"><i class="fa-solid fa-lock"></i></span><div><strong>Plano publicado</strong><p>O planejamento está bloqueado. Para alterar, crie uma revisão.</p></div></div>`;
    } else if (check.pronto) {
      guidance.innerHTML = `<div class="foto-guidance-state"><span class="foto-guidance-icon"><i class="fa-solid fa-circle-check"></i></span><div><strong>Plano pronto para publicação</strong><p>Todos os requisitos foram atendidos. Revise as informações e publique o plano.</p></div></div><div class="foto-guidance-action"><button type="button" class="foto-btn foto-btn-primary" data-publish-plan>Publicar plano</button></div>`;
    } else {
      guidance.innerHTML = `<div class="foto-guidance-state"><span class="foto-guidance-icon"><i class="fa-solid fa-triangle-exclamation"></i></span><div><strong>Plano incompleto</strong><p>Faltam ${pending.length} pendência${pending.length === 1 ? "" : "s"} para publicar o plano e iniciar a execução fotográfica.</p></div></div><div class="foto-guidance-checks"><ul>${checklistLine(!hasPending("MAPA_OBRIGATORIO"), "Mapa de posições")}${checklistLine(!hasPending("EXECUTOR_OBRIGATORIO"), "Executor definido")}</ul><ul>${imagePending ? checklistLine(false, `${imagePending} imagem${imagePending === 1 ? "" : "ens"} sem posição`) : checklistLine(true, "Imagens vinculadas")}${pointPending ? checklistLine(false, `${pointPending} período não definido`) : checklistLine(true, "Períodos definidos")}</ul></div><div class="foto-guidance-action"><button type="button" class="foto-btn foto-btn-secondary" data-open-pending>Ver pendências</button></div>`;
    }
    guidance
      .querySelector("[data-publish-plan]")
      ?.addEventListener("click", publishPlan);
    guidance
      .querySelector("[data-open-pending]")
      ?.addEventListener("click", openChecklist);
    const creation = (p.sla || []).find((item) => item.tipo === "CRIACAO");
    const execution = (p.sla || []).find((item) => item.tipo === "EXECUCAO");
    about.innerHTML = `<h2>Sobre o plano</h2><dl><div><dt>Status</dt><dd>${esc(labels[p.status] || p.status)}</dd></div><div><dt>Versão ativa</dt><dd>${esc(activeVersion()?.numero ? `V${activeVersion().numero} · ${activeVersion().status}` : "—")}</dd></div><div><dt>Última edição</dt><dd>${esc(when(p.updated_at, true))}</dd></div><div><dt>Responsável</dt><dd>${esc(p.responsavel_plano_nome || "Sem responsável")}</dd></div><div><dt>SLA criação</dt><dd>${esc(creation?.completed_at ? "Concluído" : when(creation?.due_at_effective))}</dd></div><div><dt>SLA execução</dt><dd>${esc(execution ? when(execution.due_at_effective) : "Aguardando publicação")}</dd></div></dl><p><i class="fa-solid fa-lock"></i> Após publicar, o plano ficará imutável.</p>`;
    renderSectionBadge(
      "fotoMapBadge",
      pending.filter((item) => item.anchor === "fotoMapCard"),
    );
    renderSectionBadge(
      "fotoPointsBadge",
      pending.filter((item) => item.anchor === "fotoPointsCard"),
    );
    renderSectionBadge(
      "fotoPointsFullBadge",
      pending.filter((item) => item.anchor === "fotoPointsCard"),
    );
    renderSectionBadge(
      "fotoImagesBadge",
      pending.filter((item) => item.anchor === "fotoImagesCard"),
    );
  }
  function openChecklist() {
    const dialog = $("fotoChecklistDialog"),
      list = $("fotoChecklistList");
    if (!dialog || !list) return;
    const pending = pendingItems();
    list.innerHTML = pending.length
      ? pending
          .map(
            (item, index) =>
              `<button type="button" data-pending-index="${index}"><i class="fa-regular fa-square"></i><span>${esc(item.mensagem)}</span></button>`,
          )
          .join("")
      : '<p class="foto-muted">Nenhuma pendência neste plano.</p>';
    list.querySelectorAll("[data-pending-index]").forEach((button) => {
      button.onclick = () =>
        focusPending(pending[Number(button.dataset.pendingIndex)]);
    });
    dialog.showModal();
  }
  function focusPending(item) {
    $("fotoChecklistDialog")?.close();
    const planTab = document.querySelector(
      '.foto-tabs button[data-tab="plan"]',
    );
    planTab?.click();
    if (item.posicao_id) {
      state.selectedPin = item.posicao_id;
      renderPoints();
    }
    requestAnimationFrame(() => {
      const target = item.imagem_plano_id
        ? document.querySelector(`[data-image-id="${item.imagem_plano_id}"]`)
        : item.posicao_id
          ? document.querySelector(`[data-point-id="${item.posicao_id}"]`)
          : $(item.anchor || "fotoPlanGuidance");
      if (!target) return;
      target.scrollIntoView({ behavior: "smooth", block: "center" });
      target.classList.add("foto-checklist-highlight");
      target
        .querySelector("input, select, textarea, button")
        ?.focus({ preventScroll: true });
      setTimeout(
        () => target.classList.remove("foto-checklist-highlight"),
        2800,
      );
    });
  }
  async function publishPlan() {
    if (!checklist().pronto) return openChecklist();
    const version = activeVersion();
    if (!version) return notice("Versão do plano não encontrada.", true);
    await action(
      "publish",
      { versao_id: version.id },
      "Plano publicado e pronto para execução.",
    );
  }
  function render() {
    if (!state.plan) return;
    const p = state.plan;
    $("fotoCrumb").textContent = `Plano #PF-${String(p.id).padStart(4, "0")}`;
    $("fotoTitle").textContent = `Plano #PF-${String(p.id).padStart(4, "0")}`;
    $("fotoSubtitle").textContent =
      `Obra: ${p.nomenclatura || p.nome_obra} · Campanha ${p.campanha_numero}`;
    const st = $("fotoStatus");
    st.textContent = labels[p.status] || p.status;
    st.dataset.status = p.status;
    $("fotoAddress").value = p.local || "";
    $("fotoMapsUrl").value = p.obra_maps_url || "";
    $("fotoPlannedDate").value = p.data_planejada || "";
    $("fotoExecutor").replaceChildren(
      new Option("Selecione", ""),
      ...state.collaborators.map((c) => new Option(c.nome, c.id)),
    );
    $("fotoExecutor").value = p.responsavel_execucao_id || "";
    const ready = checklist().pronto;
    $("fotoPendingCount").textContent = pendingItems().length;
    $("fotoPendingButton").hidden = isPublished() && !pendingItems().length;
    $("fotoPendingButton").disabled = !pendingItems().length;
    // Somente o rascunho ativo pode ser publicado. Depois da publicacao a
    // acao fica oculta; ao abrir uma revisao, o novo rascunho a exibe outra vez.
    $("fotoPublish").hidden = activeVersion()?.status !== "RASCUNHO";
    $("fotoPublish").disabled = !p.permissions?.edit || !ready;
    $("fotoPublish").title = ready
      ? "Publicar plano"
      : "Resolva todas as pendências para publicar.";
    $("fotoRevision").hidden =
      !p.permissions?.edit ||
      !isPublished() ||
      !["PRONTO_EXECUCAO", "CONCLUIDO"].includes(p.status);
    [
      "fotoAddress",
      "fotoMapsUrl",
      "fotoPlannedDate",
      "fotoExecutor",
      "fotoMapUpload",
      "fotoAddPosition",
    ].forEach((id) => {
      document.querySelectorAll(`#${id}`).forEach((control) => {
        control.disabled = !draftEditable();
      });
    });
    renderOverview();
    renderPlanGuidance();
    renderMap();
    renderPoints();
    renderImages();
    renderExecution();
    renderIssues();
    renderHistory();
  }
  function renderOverview() {
    const p = state.plan,
      creation = (p.sla || []).find((x) => x.tipo === "CRIACAO"),
      execution = (p.sla || []).find((x) => x.tipo === "EXECUCAO");
    $("fotoOverviewStats").innerHTML =
      stat(
        "SLA criação",
        creation?.completed_at ? "Concluído" : when(creation?.due_at_effective),
        creation?.resultado || "Em andamento",
      ) +
      stat(
        "Data de criação",
        when(p.created_at, true),
        p.responsavel_plano_nome || "Sem responsável",
      ) +
      stat(
        "Previsão de execução",
        when(p.data_planejada),
        p.responsavel_execucao_nome || "Sem executor",
      ) +
      stat(
        "Condição atual",
        labels[p.status] || p.status,
        p.holds?.find((h) => !h.encerrado_em)?.codigo || "Sem HOLD",
      );
    const included = (p.imagens || []).filter(
      (x) => x.decisao === "INCLUIDA",
    ).length;
    $("fotoOverviewSummary").innerHTML =
      `<div class="foto-summary-item"><i class="fa-regular fa-image"></i><strong>${included}</strong><span>Imagens confirmadas</span></div><div class="foto-summary-item"><i class="fa-solid fa-location-dot"></i><strong>${(p.posicoes || []).length}</strong><span>Posições</span></div><div class="foto-summary-item"><i class="fa-regular fa-clock"></i><strong>${(p.posicoes || []).reduce((n, x) => n + (x.capturas || []).length, 0)}</strong><span>Períodos planejados</span></div>`;
    $("fotoSla").innerHTML = [creation, execution]
      .filter(Boolean)
      .map(
        (s) =>
          `<div class="foto-sla-row"><span>SLA ${s.tipo.toLowerCase()}</span><small>${s.completed_at ? `Concluído ${when(s.completed_at, true)}` : `Vence ${when(s.due_at_effective, true)}`}</small></div>`,
      )
      .join("");
    const ready = p.prontidao || { pronto: false, bloqueios: [] };
    $("fotoReadiness").className =
      `foto-readiness${ready.pronto ? " is-ready" : ""}`;
    $("fotoReadiness").innerHTML = ready.pronto
      ? '<strong><i class="fa-solid fa-circle-check"></i> Plano completo e pronto para execução.</strong>'
      : `<strong><i class="fa-solid fa-triangle-exclamation"></i> O plano ainda não pode seguir para execução:</strong><ul>${(ready.bloqueios || []).map((b) => `<li>${esc(b.mensagem)}</li>`).join("")}</ul>`;
  }
  function renderMap() {
    const image = $("fotoMapImage"),
      viewport = $("fotoMapViewport"),
      empty = $("fotoMapEmpty"),
      path = mapPath(),
      hasMap = Boolean(path);
    viewport.hidden = !hasMap;
    $("fotoMapZoom").hidden = !hasMap;
    if (!hasMap) {
      if (empty) empty.hidden = false;
      return;
    }
    if (empty) empty.remove();
    if (image.src !== new URL(path, location.origin).href) {
      image.src = path;
      image.onload = () => renderMapPins();
    }
    renderMapPins();
  }
  function renderMapPins() {
    const layer = $("fotoPins");
    layer.replaceChildren();
    (state.plan.posicoes || []).forEach((pos) => {
      const pin = document.createElement("button");
      pin.type = "button";
      pin.className = `foto-pin${String(pos.id) === String(state.selectedPin) ? " is-selected" : ""}`;
      pin.style.left = `${Number(pos.x_percentual)}%`;
      pin.style.top = `${Number(pos.y_percentual)}%`;
      pin.dataset.id = pos.id;
      pin.innerHTML = `<span class="foto-pin-shape"><span class="foto-pin-label">${esc(pos.codigo)}</span></span>`;

      let moving = false,
        moved = false,
        pointerStart = null;
      pin.addEventListener("pointerdown", (e) => {
        if (!draftEditable()) return;
        moving = true;
        moved = false;
        pointerStart = { x: e.clientX, y: e.clientY };
        pin.setPointerCapture(e.pointerId);
        e.preventDefault();
      });
      pin.addEventListener("pointermove", (e) => {
        if (!moving) return;
        if (
          !moved &&
          Math.hypot(e.clientX - pointerStart.x, e.clientY - pointerStart.y) < 4
        )
          return;
        moved = true;
        placePin(pos, e);
      });
      pin.addEventListener("pointerup", async (e) => {
        if (!moving) return;
        moving = false;
        pointerStart = null;
        if (!moved) return;
        placePin(pos, e);
        selectPin(pos.id, false);
        await persistPin(pos, "Pin reposicionado.", "move");
      });
      pin.addEventListener("pointercancel", () => {
        moving = false;
        moved = false;
        pointerStart = null;
      });
      pin.addEventListener("click", (e) => {
        e.stopPropagation();
        selectPin(pos.id, false);
      });
      layer.append(pin);
    });
  }
  function pointFromEvent(event) {
    const image = $("fotoMapImage"),
      rect = image.getBoundingClientRect();
    return {
      x_percentual: Math.max(
        0,
        Math.min(100, ((event.clientX - rect.left) / rect.width) * 100),
      ),
      y_percentual: Math.max(
        0,
        Math.min(100, ((event.clientY - rect.top) / rect.height) * 100),
      ),
    };
  }
  function placePin(pos, event) {
    Object.assign(pos, pointFromEvent(event));
    const pin = $("fotoPins").querySelector(
      `[data-id="${CSS.escape(String(pos.id))}"]`,
    );
    if (pin) {
      pin.style.left = `${pos.x_percentual}%`;
      pin.style.top = `${pos.y_percentual}%`;
    }
  }
  function selectPin(id, scroll = true) {
    state.selectedPin = id;
    renderMapPins();
    renderPoints();
    if (scroll) {
      const pin = $("fotoPins").querySelector(
        `[data-id="${CSS.escape(String(id))}"]`,
      );
      pin?.scrollIntoView({
        behavior: "smooth",
        block: "center",
        inline: "center",
      });
      requestAnimationFrame(() =>
        document
          .querySelector(`[data-point-id="${CSS.escape(String(id))}"]`)
          ?.scrollIntoView({ behavior: "smooth", block: "center" }),
      );
    }
  }
  const nextPinCode = () =>
    `P${String((state.plan?.posicoes || []).filter((p) => !String(p.id).startsWith("local-")).length + 1).padStart(2, "0")}`;
  const renderLocalPins = () => {
    renderMapPins();
    renderPoints();
    renderOverview();
  };
  async function createPin(coords) {
    if (!draftEditable()) return;
    const localPin = {
      id: `local-${eventId()}`,
      codigo: nextPinCode(),
      x_percentual: coords.x_percentual,
      y_percentual: coords.y_percentual,
      observacao: "",
      capturas: [],
      pendente_local: true,
    };
    state.plan.posicoes ||= [];
    state.plan.posicoes.push(localPin);
    state.selectedPin = localPin.id;
    renderLocalPins();
    try {
      const next = await api("pin_create", {
        method: "POST",
        data: {
          plano_id: state.plan.id,
          version: state.plan.lock_version,
          versao_id: activeVersion().id,
          ...coords,
        },
      });
      const persisted = next.pin;
      state.plan.posicoes = state.plan.posicoes.filter(
        (pos) => String(pos.id) !== String(localPin.id),
      );
      applyMutationDelta(next, persisted?.id || null);
      notice("Ponto criado.");
    } catch (e) {
      state.plan.posicoes = state.plan.posicoes.filter(
        (p) => p.id !== localPin.id,
      );
      state.selectedPin = null;
      renderLocalPins();
      notice(e.message, true);
    }
  }
  function schedulePinPersist(pos, success, delay = 180, mode = "capturas") {
    const key = String(pos.id);
    clearTimeout(pinSaveTimers.get(key));
    pinSaveTimers.set(
      key,
      setTimeout(() => {
        pinSaveTimers.delete(key);
        persistPin(pos, success, mode);
      }, delay),
    );
  }
  async function persistPin(pos, success, mode = "all") {
    if (!draftEditable() || String(pos.id).startsWith("local-")) return;
    const key = String(pos.id),
      snapshot = {
        x_percentual: pos.x_percentual,
        y_percentual: pos.y_percentual,
        observacao: pos.observacao || "",
        ...(mode === "capturas" || mode === "all"
          ? {
              capturas: (pos.capturas || []).map((c) => ({
                periodo_codigo: c.periodo_codigo,
                prioridade: c.prioridade || 1,
                observacao: c.observacao || "",
                plano_imagem_ids: [...(c.plano_imagem_ids || [])],
              })),
            }
          : {}),
      };
    renderLocalPins();
    const previous = pinSaveChains.get(key) || Promise.resolve();
    const save = previous
      .catch(() => {})
      .then(async () => {
        const next = await api("pin_update", {
          method: "POST",
          data: {
            plano_id: state.plan.id,
            version: state.plan.lock_version,
            versao_id: activeVersion().id,
            pin_id: pos.id,
            mutation_kind: mode === "move" ? "MOVE" : "UPDATE",
            ...snapshot,
          },
        });
        if (pinSaveChains.get(key) === save && !pinSaveTimers.has(key))
          applyMutationDelta(next, pos.id);
        else {
          state.plan.lock_version = next.lock_version;
        }
        return next;
      });
    pinSaveChains.set(key, save);
    try {
      await save;
      notice(success);
    } catch (e) {
      notice(e.message, true);
      if (pinSaveChains.get(key) === save)
        await loadPlan(state.plan.id, pos.id);
    } finally {
      if (pinSaveChains.get(key) === save) pinSaveChains.delete(key);
    }
  }
  function renderPoints() {
    const points = state.plan.posicoes || [],
      images = state.plan.imagens || [],
      out = $("fotoPointsPreview");
    const fullCard = $("fotoPointsFullCard");
    fullCard?.querySelector(".foto-card-head p") &&
      (fullCard.querySelector(".foto-card-head p").textContent =
        "Confira os períodos recomendados para cada ponto.");
    const fullAdd = fullCard?.querySelector("#fotoAddPosition");
    if (fullAdd) fullAdd.hidden = true;
    document.querySelectorAll("#fotoPositionCount").forEach((node) => {
      node.textContent = `${points.length} posições · ${images.filter((i) => i.decisao === "INCLUIDA" && !i.posicoes_vinculadas).length} imagens sem posição`;
    });
    out.replaceChildren();
    points.forEach((pos) => {
      const pointPending = pendingItems().filter(
        (item) => Number(item.posicao_id) === Number(pos.id),
      );
      const open = String(pos.id) === String(state.selectedPin),
        item = document.createElement("article");
      item.className = `foto-point${open ? " is-open" : ""}`;
      item.dataset.pointId = pos.id;
      const captureLabel =
        (pos.capturas || [])
          .map(
            (c) =>
              c.periodo_nome ||
              periods.find((p) => p[0] === c.periodo_codigo)?.[1],
          )
          .join(", ") || "Sem períodos";
      item.innerHTML = `<button type="button" class="foto-point-summary"><span class="foto-point-code">${esc(pos.codigo)}</span><span><strong>${esc(pos.observacao || "Sem descrição")}</strong><small>${esc(captureLabel)}</small>${pointPending.length ? `<em class="foto-local-pending">${esc(pointPending.map((x) => (x.codigo === "PONTO_SEM_CAPTURA" ? "Período não definido" : x.mensagem)).join(" · "))}</em>` : '<em class="foto-local-complete"><i class="fa-solid fa-check"></i> Completo</em>'}</span><i class="fa-solid fa-chevron-down"></i></button><div class="foto-point-editor"></div>`;
      item.querySelector(".foto-point-summary").onclick = () => {
        if (String(state.selectedPin) === String(pos.id)) {
          state.selectedPin = null;
        } else {
          state.selectedPin = pos.id;
        }

        renderPoints();
      };
      if (open)
        renderPointEditor(item.querySelector(".foto-point-editor"), pos);
      out.append(item);
    });
    if (!points.length)
      out.innerHTML =
        '<div class="foto-empty-small">Ainda não há pontos no mapa.</div>';
  }
  function renderPointsPreview(points) {
    const out = $("fotoPointsPreview");
    if (!out) return;
    out.replaceChildren();
    const preview = points.slice(0, 1);
    preview.forEach((pos) => {
      const pending = pendingItems().filter(
        (item) => Number(item.posicao_id) === Number(pos.id),
      );
      const periodsText =
        (pos.capturas || [])
          .map((capture) => capture.periodo_nome || capture.periodo_codigo)
          .join(", ") || "Sem períodos";
      const item = document.createElement("button");
      item.type = "button";
      item.className = "foto-point-preview";
      item.innerHTML = `<span class="foto-point-code">${esc(pos.codigo)}</span><span class="foto-point-preview-copy"><strong>${esc(pos.observacao || "Sem descrição")}</strong><small>${esc(periodsText)}</small></span>${pending.length ? `<em class="foto-local-pending">${esc(pending.map((x) => (x.codigo === "PONTO_SEM_CAPTURA" ? "Período não definido" : x.mensagem)).join(" · "))}</em>` : '<em class="foto-local-complete"><i class="fa-solid fa-check"></i> Completo</em>'}<i class="fa-solid fa-chevron-down"></i>`;
      item.onclick = () => {
        state.selectedPin = pos.id;
        renderPoints();
        $("fotoPointsFullCard")?.scrollIntoView({
          behavior: "smooth",
          block: "center",
        });
      };
      out.append(item);
    });
    if (points.length > 1) {
      const all = document.createElement("button");
      all.type = "button";
      all.className = "foto-text-btn foto-preview-all";
      all.textContent = `Ver todos os pontos (${points.length})`;
      all.onclick = () =>
        $("fotoPointsFullCard")?.scrollIntoView({
          behavior: "smooth",
          block: "center",
        });
      out.append(all);
    }
    if (!points.length)
      out.innerHTML =
        '<div class="foto-empty-small">Ainda não há pontos no mapa.</div>';
  }
  function renderPointEditor(out, pos) {
    const editable = draftEditable();
    const note = document.createElement("textarea");
    note.rows = 3;
    note.placeholder = "Descrição/observação do ponto";
    note.value = pos.observacao || "";
    note.disabled = !editable;
    let noteTimer;
    note.addEventListener("input", () => {
      pos.observacao = note.value;
      const title = out.parentElement?.querySelector(
        ".foto-point-summary strong",
      );
      if (title) title.textContent = pos.observacao || "Sem descrição";
      clearTimeout(noteTimer);
      noteTimer = setTimeout(
        () => persistPin(pos, "Descrição atualizada.", "details"),
        550,
      );
    });
    out.append(note);
    const periodVisual = {
      DIURNO: { className: "is-diurno", icon: "fa-sun" },
      GOLDEN_HOUR: { className: "is-golden-hour", icon: "fa-cloud-sun" },
      BLUE_HOUR: { className: "is-blue-hour", icon: "fa-cloud-moon" },
      NOTURNO: { className: "is-noturno", icon: "fa-moon" },
    };
    periods.forEach(([code, label]) => {
      const capture = (pos.capturas || []).find(
        (c) => c.periodo_codigo === code,
      );
      const visual = periodVisual[code] || {
        className: "is-neutral",
        icon: "fa-calendar",
      };
      const row = document.createElement("section");
      row.className = `foto-period-card ${visual.className}${capture ? " is-enabled" : ""}`;
      row.innerHTML = `<header class="foto-period-card-head"><label class="foto-period-toggle"><input type="checkbox" ${capture ? "checked" : ""} ${editable ? "" : "disabled"}><span class="foto-period-icon"><i class="fa-solid ${visual.icon}"></i></span><span class="foto-period-name">${esc(label)}</span></label><label class="foto-period-priority"><span class="foto-sr-only">Prioridade de ${esc(label)}</span><select ${editable && capture ? "" : "disabled"}><option value="1">Prioridade 1</option><option value="2">Prioridade 2</option><option value="3">Prioridade 3</option></select></label></header><div class="foto-period-images"></div>`;
      const enabled = row.querySelector(".foto-period-toggle input"),
        priority = row.querySelector("select"),
        list = row.querySelector(".foto-period-images");
      priority.value = capture?.prioridade || 1;
      images
        // Imagens pendentes precisam estar disponiveis aqui para que a
        // primeira vinculacao possa ser feita no proprio ponto. Excluidas e
        // removidas continuam fora do planejamento.
        .filter((i) => !["EXCLUIDA", "REMOVIDA"].includes(i.decisao))
        .forEach((image) => {
          const check = document.createElement("label");
          check.className = "foto-period-image";
          const checked = (capture?.plano_imagem_ids || [])
            .map(Number)
            .includes(Number(image.id));
          check.innerHTML = `<input type="checkbox" value="${image.id}" ${checked ? "checked" : ""} ${editable && capture ? "" : "disabled"}><span>${esc(image.imagem_nome)}</span>`;
          check.querySelector("input").addEventListener("change", (e) => {
            if (
              e.target.checked &&
              linkedInAnotherPoint(image.id, pos.id, code) &&
              !confirm(
                `A imagem “${image.imagem_nome}” já está vinculada a outro ponto/período. Deseja reutilizá-la?`,
              )
            ) {
              e.target.checked = false;
              return;
            }
            syncCapture(pos, code, label, enabled, priority, list);
          });
          list.append(check);
        });
      const sync = () => syncCapture(pos, code, label, enabled, priority, list);
      enabled.addEventListener("change", sync);
      priority.addEventListener("change", sync);
      out.append(row);
    });
    const footer = document.createElement("div");
    footer.className = "foto-point-actions";
    footer.innerHTML =
      '<button type="button" class="foto-text-btn">Localizar no mapa</button><button type="button" class="foto-text-btn foto-danger-text">Excluir ponto</button>';
    const [locate, remove] = footer.querySelectorAll("button");
    locate.onclick = () => selectPin(pos.id);
    remove.disabled = !editable;
    remove.onclick = () => removePin(pos);
    out.append(footer);
  }
  function linkedInAnotherPoint(imageId, pointId, period) {
    return (state.plan.posicoes || []).some(
      (pos) =>
        String(pos.id) !== String(pointId) &&
        (pos.capturas || []).some(
          (c) =>
            c.periodo_codigo === period &&
            (c.plano_imagem_ids || []).map(Number).includes(Number(imageId)),
        ),
    );
  }
  function syncCapture(pos, code, label, enabled, priority, list) {
    const index = (pos.capturas || []).findIndex(
      (c) => c.periodo_codigo === code,
    );
    if (!enabled.checked) {
      if (index >= 0) pos.capturas.splice(index, 1);
    } else {
      const capture = {
        ...(index >= 0 ? pos.capturas[index] : {}),
        periodo_codigo: code,
        periodo_nome: label,
        prioridade: Number(priority.value),
        plano_imagem_ids: [...list.querySelectorAll("input:checked")].map((x) =>
          Number(x.value),
        ),
      };
      if (index >= 0) pos.capturas[index] = capture;
      else (pos.capturas ||= []).push(capture);
    }
    renderLocalPins();
    schedulePinPersist(pos, "Capturas atualizadas.");
  }
  async function removePin(pos) {
    const linked = (pos.capturas || []).some(
        (c) => (c.plano_imagem_ids || []).length,
      ),
      key = String(pos.id);
    if (
      linked &&
      !confirm("Este ponto possui imagens vinculadas. Deseja excluí-lo?")
    )
      return;
    const index = state.plan.posicoes.findIndex((p) => String(p.id) === key);
    if (index < 0) return;
    clearTimeout(pinSaveTimers.get(key));
    pinSaveTimers.delete(key);
    state.pendingPinDeletes.set(key, pos);
    state.plan.posicoes.splice(index, 1);
    state.selectedPin = null;
    renderLocalPins();
    const previous = pinSaveChains.get(key) || Promise.resolve();
    const deletion = previous
      .catch(() => {})
      .then(() =>
        api("pin_delete", {
          method: "POST",
          data: {
            plano_id: state.plan.id,
            version: state.plan.lock_version,
            versao_id: activeVersion().id,
            pin_id: pos.id,
            confirmar_exclusao: linked,
          },
        }),
      );
    pinSaveChains.set(key, deletion);
    try {
      const next = await deletion;
      state.pendingPinDeletes.delete(key);
      applyMutationDelta(next);
      notice("Ponto excluído.");
    } catch (e) {
      state.pendingPinDeletes.delete(key);
      state.plan.posicoes.splice(index, 0, pos);
      state.selectedPin = pos.id;
      renderLocalPins();
      notice(e.message, true);
    } finally {
      if (pinSaveChains.get(key) === deletion) pinSaveChains.delete(key);
    }
  }
  function renderImages() {
    const out = $("fotoImages");
    out.replaceChildren();
    (state.plan.imagens || []).forEach((image) => {
      const row = document.createElement("div");
      row.className = "foto-image-row";
      row.dataset.imageId = image.id;
      const imagePending = pendingItems().filter(
        (item) => Number(item.imagem_plano_id) === Number(image.id),
      );
      const badges = imagePending.flatMap(
        (item) =>
          item.tags || [
            item.codigo === "IMAGEM_SEM_VINCULO"
              ? "Sem posição"
              : item.mensagem,
          ],
      );
      row.innerHTML = `<div class="foto-image-name"><strong>${esc(image.imagem_nome)}</strong><small>${esc(image.tipo_imagem || "Sem tipo")} · Referência: ${esc(image.referencia_nome || image.pavimento_referencia || "Sem referência")}</small>${badges.length ? `<em class="foto-local-pending">${esc([...new Set(badges)].join(" · "))}</em>` : '<em class="foto-local-complete"><i class="fa-solid fa-check"></i> Completa</em>'}</div><select><option value="PENDENTE">Pendente</option><option value="INCLUIDA">Confirmada</option><option value="EXCLUIDA">Excluir</option></select><span>${esc(image.periodos_vinculados || "Sem período")}</span><span>${esc(image.prioridade_vinculada ? `P${image.prioridade_vinculada}` : "—")}</span><span>${esc(image.posicoes_vinculadas || "Sem posição")}</span>`;
      const select = row.querySelector("select");
      select.value = image.decisao;
      select.disabled = !draftEditable();
      select.onchange = async () => {
        let reason = image.motivo_exclusao || "";
        if (select.value === "EXCLUIDA" && !reason) {
          reason = prompt("Motivo da exclusão:") || "";
          if (!reason) {
            select.value = image.decisao;
            return;
          }
        }
        try {
          const next = await api("image_update", {
            method: "POST",
            data: {
              plano_id: state.plan.id,
              version: state.plan.lock_version,
              versao_id: activeVersion().id,
              imagem_plano_id: image.id,
              decisao: select.value,
              motivo_exclusao: reason,
            },
          });
          await applyOrRefreshMutation(next, state.selectedPin);
          notice("Decisão da imagem atualizada.");
        } catch (e) {
          notice(e.message, true);
          await loadPlan(state.plan.id, state.selectedPin);
        }
      };
      out.append(row);
    });
  }
  function renderExecution() {
    const p = state.plan,
      last = p.resumo_execucao?.ultima_tentativa;
    $("fotoExecutionStats").innerHTML =
      stat("Status", labels[p.status] || p.status) +
      stat(
        "Data planejada",
        when(p.data_planejada),
        p.responsavel_execucao_nome || "Sem executor",
      ) +
      stat(
        "Tentativas",
        p.resumo_execucao?.tentativas || 0,
        last ? when(last.executado_em) : "Nenhuma",
      ) +
      stat(
        "Última decisão",
        last?.decisao_conferencia || "Pendente",
        last?.conferente_nome || "",
      );
    $("fotoSubmitExecution").disabled = !(
      p.permissions?.execute &&
      p.status === "PRONTO_EXECUCAO" &&
      isPublished()
    );
    $("fotoReviewSubmit").disabled = !(
      p.permissions?.review && p.status === "EM_CONFERENCIA"
    );
    const out = $("fotoExecutions");
    out.replaceChildren();
    (p.execucoes || []).forEach((ex) => {
      const row = document.createElement("div");
      row.className = "foto-timeline-item";
      row.innerHTML = `<div class="foto-timeline-icon"><i class="fa-solid fa-camera"></i></div><div><h3>Tentativa ${ex.tentativa} · ${esc(ex.decisao_conferencia || ex.resultado)}</h3><small>Executada ${when(ex.executado_em, true)} · enviada por ${esc(ex.enviado_por_nome || ex.responsavel_nome)}</small><a class="foto-exec-link" target="_blank" rel="noopener" href="${esc(ex.material_url || "#")}">${esc(ex.material_url || "Sem link")}</a><p>${esc(ex.observacao || "")}${ex.consideracao ? `\nConferência: ${esc(ex.consideracao)}` : ""}</p></div>`;
      out.append(row);
    });
    if (!out.children.length)
      out.innerHTML =
        '<div class="foto-empty-small">Nenhuma tentativa registrada.</div>';
  }
  function timeline(id, rows, icon) {
    const out = $(id);
    out.replaceChildren();
    rows.forEach((row) => {
      const item = document.createElement("div");
      item.className = "foto-timeline-item";
      item.innerHTML = `<div class="foto-timeline-icon"><i class="fa-solid ${icon}"></i></div><div><h3>${esc(row.titulo || row.codigo || row.tipo || "Evento")}</h3><small>${when(row.criado_em || row.aberto_em, true)} · ${esc(row.responsavel_nome || row.ator_nome || "")}</small><p>${esc(row.detalhes || "")}</p></div>`;
      out.append(item);
    });
    if (!out.children.length)
      out.innerHTML = '<div class="foto-empty-small">Nenhum registro.</div>';
  }
  function renderIssues() {
    const p = state.plan,
      open = (p.pendencias || []).filter((x) => x.status === "ABERTA"),
      holds = p.holds || [];
    $("fotoIssueStats").innerHTML =
      stat("Pendências abertas", open.length) +
      stat("Itens em HOLD", holds.filter((x) => !x.encerrado_em).length) +
      stat(
        "Impacto no SLA",
        holds.some((x) => !x.encerrado_em && Number(x.afeta_sla))
          ? "Pausado"
          : "Sem pausa",
      ) +
      stat(
        "SLA execução",
        (p.sla || []).find((x) => x.tipo === "EXECUCAO")?.resultado ||
          "Aguardando",
      );
    timeline("fotoIssues", open, "fa-circle-exclamation");
    timeline("fotoHolds", holds, "fa-pause");
    $("fotoOpenHold").hidden = !p.permissions?.manage;
  }
  function renderHistory() {
    timeline("fotoHistory", state.plan.eventos || [], "fa-clock-rotate-left");
  }
  async function updatePlan(fields, success) {
    try {
      await applyOrRefreshMutation(
        await api("plan_update", {
          method: "POST",
          data: {
            plano_id: state.plan.id,
            version: state.plan.lock_version,
            versao_id: activeVersion()?.id,
            ...fields,
          },
        }),
        state.selectedPin,
      );
      notice(success);
    } catch (e) {
      notice(e.message, true);
      await loadPlan(state.plan.id, state.selectedPin);
    }
  }
  $("fotoRefresh").onclick = () =>
    loadPlan(state.plan.id, state.selectedPin).catch((e) =>
      notice(e.message, true),
    );
  $("fotoPendingButton").onclick = openChecklist;
  $("fotoChecklistClose").onclick = () => $("fotoChecklistDialog").close();
  $("fotoChecklistDialog").addEventListener("click", (event) => {
    if (event.target === $("fotoChecklistDialog"))
      $("fotoChecklistDialog").close();
  });
  $("fotoPublish").onclick = publishPlan;
  $("fotoBack").onclick = async () => {
    $("fotoDetailView").hidden = true;
    $("fotoListView").hidden = false;
    await loadPlans();
  };
  $("fotoSearch").oninput = renderPlanList;
  $("fotoStatusFilter").onchange = renderPlanList;
  document.querySelectorAll(".foto-tabs button").forEach(
    (button) =>
      (button.onclick = () => {
        document
          .querySelectorAll(".foto-tabs button")
          .forEach((x) => x.classList.toggle("is-active", x === button));
        document
          .querySelectorAll(".foto-panel")
          .forEach((x) =>
            x.classList.toggle(
              "is-active",
              x.dataset.panel === button.dataset.tab,
            ),
          );
      }),
  );
  function activateTab(tab) {
    const button = document.querySelector(
      `.foto-tabs button[data-tab="${tab}"]`,
    );
    if (button) button.click();
  }
  [
    ["fotoAddress", "endereco"],
    ["fotoMapsUrl", "maps_url"],
    ["fotoPlannedDate", "data_planejada"],
  ].forEach(([id, key]) => {
    $(id).addEventListener("change", () =>
      updatePlan({ [key]: $(id).value.trim() }, "Dados do plano atualizados."),
    );
  });
  $("fotoExecutor").onchange = () =>
    updatePlan(
      { responsavel_execucao_id: Number($("fotoExecutor").value || 0) },
      "Responsável atualizado.",
    );
  $("fotoMapUpload").onclick = () => $("fotoMapFile").click();
  $("fotoMapFile").onchange = async (e) => {
    const file = e.target.files?.[0];
    if (!file || !activeVersion()) return;
    try {
      await upload("mapa", activeVersion().id, file);
      await loadPlan(state.plan.id);
      await updatePlan({}, "Mapa enviado e prontidão recalculada.");
    } catch (err) {
      notice(err.message, true);
    }
    e.target.value = "";
  };
  $("fotoMapZoom").onclick = () => $("fotoMapImage").requestFullscreen?.();
  $("fotoMapImage").onclick = (e) => {
    if (!draftEditable()) return;
    createPin(pointFromEvent(e));
  };
  document.querySelectorAll("#fotoAddPosition").forEach((button) => {
    button.onclick = () =>
      mapPath()
        ? createPin({ x_percentual: 50, y_percentual: 50 })
        : notice("Envie a imagem do mapa antes de criar posições.", true);
  });
  $("fotoRevision").onclick = () => {
    const reason = prompt("Motivo da revisão:");
    if (reason !== null)
      action(
        "create_revision",
        { motivo: reason || "Revisão do plano" },
        "Revisão criada.",
      );
  };
  async function action(name, fields, success) {
    try {
      await applyOrRefreshMutation(
        await api(name, {
          method: "POST",
          data: {
            plano_id: state.plan.id,
            version: state.plan.lock_version,
            ...fields,
          },
        }),
      );
      notice(success);
    } catch (e) {
      notice(e.message, true);
    }
  }
  $("fotoSubmitExecution").onclick = async () => {
    const executed = $("fotoExecutedAt").value,
      material = $("fotoMaterialUrl").value.trim();
    if (!executed || !material)
      return notice("Informe data e link do Drive.", true);
    try {
      const next = await api("submit_execution", {
        method: "POST",
        data: {
          plano_id: state.plan.id,
          version: state.plan.lock_version,
          executado_em: executed.replace("T", " ") + ":00",
          material_url: material,
          observacao: $("fotoExecutionNote").value.trim(),
        },
      });
      await applyOrRefreshMutation(next);
      const execution = state.plan.execucoes?.[0];
      for (const file of $("fotoEvidenceFiles").files || [])
        await upload("evidencia_execucao", execution.id, file);
      notice("Tentativa enviada para conferência.");
    } catch (e) {
      notice(e.message, true);
    }
  };
  $("fotoReviewSubmit").onclick = () => {
    const decision = $("fotoReviewDecision").value,
      note = $("fotoReviewNote").value.trim(),
      execution = (state.plan.execucoes || []).find(
        (x) => x.resultado === "EM_CONFERENCIA",
      );
    if (!decision || !execution)
      return notice("Selecione a decisão e a tentativa pendente.", true);
    if (decision !== "APROVADO" && !note)
      return notice("Informe a consideração da conferência.", true);
    action(
      "review",
      {
        execucao_id: Number(execution.id),
        decisao: decision,
        consideracao: note,
      },
      "Conferência registrada.",
    );
  };
  $("fotoOpenHold").onclick = () => $("fotoHoldDialog").showModal();
  $("fotoConfirmHold").onclick = () => {
    $("fotoHoldDialog").close();
    action(
      "hold_open",
      {
        codigo: $("fotoHoldCode").value,
        detalhes: $("fotoHoldDetails").value.trim(),
      },
      "HOLD aberto.",
    );
  };
  $("fotoNewCampaign").onclick = () => {
    const obra = Number(prompt("ID da obra para a nova campanha:"));
    if (obra)
      api("create_campaign", { method: "POST", data: { obra_id: obra } })
        .then((p) => loadPlan(p.id))
        .catch((e) => notice(e.message, true));
  };
  window.addEventListener("improov:fotograficoUpdated", (event) => {
    const data = event.detail || {};
    const clientEventId = String(data.client_event_id || "");
    const eventId = String(data.event_id || "");
    if (
      !state.plan ||
      Number(data.plan_id) !== Number(state.plan.id) ||
      isOwnRealtimeEvent(clientEventId) ||
      isSeenRealtimeEvent(clientEventId) ||
      isSeenRealtimeEvent(eventId)
    )
      return;
    rememberRealtimeEvent(seenRealtimeEventIds, clientEventId);
    rememberRealtimeEvent(seenRealtimeEventIds, eventId);
    clearTimeout(state.wsTimer);
    state.wsTimer = setTimeout(
      () =>
        loadPlan(state.plan.id, state.selectedPin)
          .then(() => notice("Plano atualizado por outro usuário."))
          .catch(() => {}),
      180,
    );
  });
  function connectRealtime() {
    if (window.improovUploadWS?.connect) {
      window.improovUploadWS.connect();
      return;
    }
    const url =
      location.protocol === "https:"
        ? `wss://${location.hostname}/ws/`
        : `ws://${location.hostname}:8082`;
    try {
      const socket = new WebSocket(url);
      socket.addEventListener("message", (event) => {
        try {
          const message = JSON.parse(event.data);
          if (String(message?.channel || "").startsWith("fotografico:"))
            window.dispatchEvent(
              new CustomEvent("improov:fotograficoUpdated", {
                detail: message.payload || {},
              }),
            );
        } catch (_) {}
      });
      socket.addEventListener("close", () => setTimeout(connectRealtime, 2500));
      socket.addEventListener("error", () => socket.close());
    } catch (_) {
      setTimeout(connectRealtime, 2500);
    }
  }
  (async () => {
    try {
      await loadCollaborators();
      await loadPlans();
      const id = Number(window.FOTOGRAFICO_INITIAL?.planId || 0);
      if (id) {
        await loadPlan(id);
        const tab = new URLSearchParams(location.search).get("tab");
        if (
          ["overview", "plan", "execution", "issues", "history"].includes(tab)
        )
          activateTab(tab);
      }
      connectRealtime();
    } catch (e) {
      notice(e.message, true);
    }
  })();
})();
