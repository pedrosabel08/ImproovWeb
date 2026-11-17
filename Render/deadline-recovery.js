(() => {
  const panel = document.getElementById("deadlineRecovery");
  if (!panel) return;
  const job = document.getElementById("deadlineRecoveryJob");
  const refresh = document.getElementById("deadlineRecoveryRefresh");
  const loader = document.getElementById("deadlineRecoveryLoading");
  window.thinkingOrbs?.(loader);
  const status = document.getElementById("deadlineRecoveryStatus");
  const actions = document.getElementById("deadlineActions");
  const machines = document.getElementById("deadlineMachines");
  const timeline = document.getElementById("deadlineRecoveryHistory");
  const image = document.getElementById("deadlineRecoveryImage");
  const jobState = document.getElementById("deadlineRecoveryState");
  const opener = document.getElementById("openDeadlineRecovery");
  const scroll = panel.querySelector(".deadline-recovery-scroll");
  const tabs = [...panel.querySelectorAll('[role="tab"]')];
  const manager = panel.dataset.manager === "1";
  let confirming = false;
  let mainJob, renderStatus, resubmitNotice;

  function activate(tab) {
    for (const item of tabs) {
      const active = item === tab;
      item.setAttribute("aria-selected", String(active));
      item.tabIndex = active ? 0 : -1;
      document.getElementById(item.getAttribute("aria-controls")).hidden =
        !active;
    }
    scroll.scrollTop = 0;
  }

  tabs.forEach((tab, index) => {
    tab.addEventListener("click", () => activate(tab));
    tab.addEventListener("keydown", (event) => {
      const positions = {
        ArrowRight: (index + 1) % tabs.length,
        ArrowLeft: (index + tabs.length - 1) % tabs.length,
        Home: 0,
        End: tabs.length - 1,
      };
      if (!(event.key in positions)) return;
      event.preventDefault();
      const next = tabs[positions[event.key]];
      activate(next);
      next.focus();
    });
  });

  const validJob = /^[a-f\d]{24}$/i;
  const pending = new Map();
  let renderId,
    generation = 0;
  const names = {
    deny: "Editar Deny List",
    retry: "Retomar tarefas com erro",
    route: "Alterar Pool/Group",
    suspend: "Suspender job",
    resume: "Retomar job",
    resubmit: "Resubmit Job",
  };
  const states = {
    active: "Ativo",
    pending: "Pendente",
    failed: "Falhou",
    completed: "Concluído",
    suspended: "Suspenso",
  };
  const outcomes = {
    queued: "Na fila",
    running: "Em execução",
    succeeded: "Confirmado no Deadline",
    rejected: "Não executado",
    failed: "Falha de consulta",
    unverified: "Resultado não confirmado",
  };

  function node(parent, tag, value) {
    const el = document.createElement(tag);
    if (value !== undefined) el.textContent = value;
    parent.append(el);
    return el;
  }

  const actionIcons = {
    deny: "lock",
    retry: "rotate-right",
    route: "gear",
    suspend: "pause",
    resume: "play",
    resubmit: "copy",
  };

  function icon(parent, name) {
    const glyph = document.createElement("i");
    glyph.className = "fa-solid fa-" + name;
    glyph.setAttribute("aria-hidden", "true");
    parent.prepend(glyph);
  }

  function badge(parent, label, tone = "neutral") {
    const element = node(parent, "span", label);
    element.className = "deadline-badge";
    element.dataset.tone = tone;
  }

  function button(parent, label, onClick, glyph) {
    const el = node(parent, "button", label);
    el.type = "button";
    el.className = "errors-toggle";
    if (glyph) icon(el, glyph);
    if (onClick) el.addEventListener("click", onClick);
    return el;
  }

  function disclosure(parent, label) {
    const el = node(parent, "details");
    node(el, "summary", label);
    return el;
  }

  function section(parent, title, glyph) {
    const box = node(parent, "section");
    box.className = "deadline-recovery-section";
    const heading = node(box, "h3", title);
    if (glyph) icon(heading, glyph);
    return box;
  }

  function select(parent, label, values, current) {
    const el = node(node(parent, "label", label + " "), "select");
    for (const value of new Set([current, ...values])) {
      const option = node(el, "option", value || "Nenhum");
      option.value = value;
    }
    el.value = current;
    return el;
  }

  function date(value) {
    return value
      ? new Date(value.replace(" ", "T") + "Z").toLocaleString("pt-BR")
      : "Horário indisponível";
  }

  async function api(target, request, body) {
    const query = new URLSearchParams({
      action: "deadlineRecovery",
      render_id: target.render_id,
      job_id: target.job_id,
    });
    if (request) query.set("request", request);
    const response = await fetch("ajax.php?" + query, {
      method: body ? "POST" : "GET",
      cache: "no-store",
      headers: {
        Accept: "application/json",
        ...(body
          ? {
              "Content-Type": "application/json",
              "X-Render-CSRF": panel.dataset.csrf,
            }
          : {}),
      },
      body: body ? JSON.stringify({ ...target, ...body }) : undefined,
    });
    const data = await response.json();
    if (!response.ok)
      throw new Error(
        data.message || "Não foi possível consultar a recuperação.",
      );
    return data;
  }

  function history(parent, rows = []) {
    const box = section(parent, "Intervenções no Flow", "clock-rotate-left");
    if (!rows.length)
      node(box, "p", "Nenhuma intervenção registrada para este job.");
    for (const row of rows) {
      const item = disclosure(box, names[row.action] || row.action);
      const heading = item.querySelector("summary");
      icon(heading, actionIcons[row.action] || "clock");
      badge(
        heading,
        outcomes[row.status] || row.status,
        {
          succeeded: "green",
          failed: "red",
          rejected: "red",
          unverified: "yellow",
          running: "yellow",
        }[row.status],
      );
      const actor = row.actor_name || "Usuário #" + row.user_id;
      node(heading, "small", date(row.created_at) + " · " + actor);
      if (row.message) node(item, "p", row.message);
      const params = JSON.parse(row.params_json || "{}");
      if (row.action === "route")
        node(
          item,
          "p",
          "Solicitado: Pool " +
            (params.pool || "—") +
            " / Group " +
            (params.group || "—"),
        );
      if (row.action === "deny")
        node(
          item,
          "p",
          (params.blocked ? "Bloquear " : "Desbloquear ") + params.worker,
        );
      if (row.action === "retry")
        node(item, "p", "Tarefas: " + params.tasks.join(", "));
      if (row.action === "resubmit") {
        node(
          item,
          "p",
          `${params.name} · Frames ${params.frames} · ${params.chunk} por tarefa`,
        );
        if (row.new_job_id) node(item, "p", "Novo job: " + row.new_job_id);
      }
    }
  }

  function evidence(parent, rows = [], reports = [], total = reports.length) {
    const box = section(parent, "Diagnósticos e ocorrências", "file-lines");
    const stored = new Map(rows.map((row) => [row.report_id, row]));
    const combined = new Map(reports.map((report) => [report.id, report]));
    for (const row of rows) {
      if (!combined.has(row.report_id))
        combined.set(row.report_id, {
          id: row.report_id,
          worker: row.worker,
          task: row.task_id,
          at: row.occurred_at,
          kind: "error",
        });
    }
    if (!combined.size)
      node(box, "p", "Nenhum relatório disponível nesta consulta.");
    if (total > reports.length)
      node(
        box,
        "p",
        "Exibindo até 50 relatórios por tipo de " +
          total +
          " no Deadline, além das ocorrências coletadas.",
      );
    for (const report of [...combined.values()].sort((a, b) =>
      (b.at || "").localeCompare(a.at || ""),
    )) {
      const row = stored.get(report.id);
      const diagnosis = JSON.parse(row?.diagnosis_json || "null");
      const facts = JSON.parse(row?.evidence_json || "{}");
      const item = disclosure(
        box,
        (report.worker || "Worker indisponível") +
          (report.task != null && report.task >= 0
            ? " · Tarefa " + report.task
            : ""),
      );
      const heading = item.querySelector("summary");
      const error = report.kind !== "log";
      icon(heading, error ? "triangle-exclamation" : "file-lines");
      badge(heading, error ? "Erro" : "Log", error ? "red" : "neutral");
      node(heading, "small", date(report.at));
      node(item, "p", "Relatório " + report.id);
      node(
        item,
        "p",
        "Evidência registrada: " +
          (report.message || facts.main || "Mensagem indisponível"),
      );
      if (diagnosis) {
        node(
          item,
          "p",
          "Avaliação do classificador (" +
            diagnosis.confidence +
            "): " +
            diagnosis.diagnosis,
        );
        if (diagnosis.recommendation)
          node(item, "p", "Recomendação: " + diagnosis.recommendation);
      }
    }
  }

  function tools(parent, snap, act) {
    const state = section(
      parent,
      "Controle do job",
      snap.status === "suspended" ? "play" : "pause",
    );
    node(state, "p", states[snap.status] || snap.status);
    const configurable = ["active", "suspended", "failed", "pending"].includes(
      snap.status,
    );
    if (["active", "suspended"].includes(snap.status)) {
      const suspension = snap.status === "active";
      const control = button(
        state,
        names[suspension ? "suspend" : "resume"],
        () =>
          act(
            suspension ? "suspend" : "resume",
            {},
            suspension
              ? "As tarefas já em execução podem continuar."
              : "O Deadline voltará a distribuir tarefas deste job.",
          ),
        suspension ? "pause" : "play",
      );
      control.dataset.intent = suspension ? "danger" : "positive";
    }
    const failed = snap.tasks.filter((t) => t.status === "failed");
    const tasks = section(parent, "Tarefas com erro", "rotate-right");
    node(
      tasks,
      "p",
      failed.length === 1
        ? "1 tarefa com falha"
        : failed.length + " tarefas com falha",
    );
    if (failed.length && ["active", "failed"].includes(snap.status)) {
      const list = node(disclosure(tasks, "Selecionar tarefas"), "div");
      list.className = "deadline-recovery-list";
      const selected = new Set();
      for (const t of failed) {
        const label = node(list, "label");
        const input = node(label, "input");
        input.type = "checkbox";
        input.addEventListener("change", () => {
          if (input.checked) selected.add(t.id);
          else selected.delete(t.id);
          restart.disabled = !selected.size;
        });
        node(
          label,
          "span",
          "Tarefa " + t.id + (t.worker ? " · " + t.worker : ""),
        );
      }
      const toolbar = node(tasks, "div");
      toolbar.className = "deadline-recovery-toolbar";
      const retry = (ids) =>
        act(
          "retry",
          { tasks: ids },
          "Retomar " +
            ids.length +
            " tarefa(s) em falha. As tarefas concluídas e em execução serão preservadas.",
        );
      const restart = button(
        toolbar,
        "Retomar selecionadas",
        () => retry([...selected]),
        "rotate-right",
      );
      restart.disabled = true;
      button(
        toolbar,
        "Retomar todas com erro",
        () => retry(failed.map((t) => t.id)),
        "rotate-right",
      );
    } else {
      const restart = button(tasks, "Reiniciar", null, "rotate-right");
      restart.disabled = true;
      if (failed.length)
        node(tasks, "p", "Retomada indisponível no estado atual.");
    }

    const routing = section(parent, "Configuração de execução", "gear");
    if (configurable && snap.workers_complete) {
      const bar = node(routing, "div");
      bar.className = "deadline-recovery-routing";
      const pool = select(bar, "Pool", snap.pools, snap.pool);
      const group = select(bar, "Group", snap.groups, snap.group);
      const option = (s) => !s || s.toLowerCase() === "none";
      const warns = () =>
        snap.required_ram
          ? snap.workers
              .filter(
                (w) =>
                  w.memory &&
                  w.memory < snap.required_ram &&
                  (option(group.value) || w.groups?.includes(group.value)) &&
                  (option(pool.value) ||
                    snap.secondary?.toLowerCase() === "none" ||
                    w.pools?.includes(pool.value) ||
                    w.pools?.includes(snap.secondary)),
              )
              .map((w) => w.name)
          : [];
      const warning = node(routing, "p");
      const save = button(
        bar,
        "Salvar alterações",
        () =>
          act(
            "route",
            { pool: pool.value, group: group.value },
            "Aplicar Pool " +
              (pool.value || "nenhum") +
              " e Group " +
              (group.value || "nenhum") +
              "." +
              (warns().length
                ? " RAM abaixo do mínimo declarado: " + warns().join(", ") + "."
                : ""),
          ),
        "check",
      );
      save.dataset.intent = "positive";
      const sync = () => {
        save.disabled = pool.value === snap.pool && group.value === snap.group;
        warning.textContent = warns().length
          ? "Workers abaixo da RAM mínima declarada: " + warns().join(", ")
          : "";
      };
      pool.addEventListener("change", sync);
      group.addEventListener("change", sync);
      sync();
    } else {
      node(
        routing,
        "p",
        "Pool " + (snap.pool || "—") + " · Group " + (snap.group || "—"),
      );
      node(routing, "p", "Alteração de Pool e Group indisponível.");
    }
    if (snap.secondary)
      node(routing, "p", "Pool secundário: " + snap.secondary);
  }

  function renderWorkers(snap, act) {
    const box = section(machines, "Workers e Deny List", "server");
    const keys = (names) => new Set(names.map((name) => name.toLowerCase()));
    const eligible = keys(snap.eligible);
    const listed = keys(snap.listed);
    const rendering = keys(
      snap.tasks
        .filter((task) => task.status === "rendering" && task.worker)
        .map((task) => task.worker),
    );
    const fleet = new Map();
    for (const name of [
      ...snap.listed,
      ...snap.workers.map((worker) => worker.name),
      ...snap.tasks.map((task) => task.worker).filter(Boolean),
    ]) {
      fleet.set(name.toLowerCase(), name);
    }
    const known = keys(snap.workers.map((worker) => worker.name));
    const editable =
      snap.workers_complete &&
      !snap.allow &&
      ["active", "suspended", "failed", "pending"].includes(snap.status);
    if (!snap.workers_complete)
      node(box, "p", "A consulta dos Workers ficou incompleta.");
    if (snap.allow) node(box, "p", "Este job usa uma Allow List.");
    if (!fleet.size) {
      node(box, "p", "Nenhum Worker disponível nesta consulta.");
      return;
    }
    const order = (key) =>
      rendering.has(key) ? 0 : eligible.has(key) ? 1 : listed.has(key) ? 2 : 3;
    const used = keys(snap.tasks.map((task) => task.worker).filter(Boolean));
    const ordered = [...fleet].sort(
      ([a], [b]) => order(a) - order(b) || a.localeCompare(b),
    );
    const associated = ([key]) => order(key) < 3 || used.has(key);
    const linked = ordered.filter(associated);
    const others = ordered.filter((worker) => !associated(worker));
    if (linked.length) workerTable(box, linked, "Workers associados ao job");
    else node(box, "p", "Nenhum Worker associado nesta consulta.");
    if (others.length) {
      workerTable(
        disclosure(box, `Demais Workers da fazenda (${others.length})`),
        others,
        "Demais Workers da fazenda",
      );
    }

    function workerTable(parent, entries, label) {
      const table = node(parent, "table");
      table.className = "deadline-recovery-workers";
      table.setAttribute("aria-label", label);
      const head = node(node(table, "thead"), "tr");
      for (const title of ["Worker", "Situação", "Deny List"]) {
        node(head, "th", title).scope = "col";
      }
      const body = node(table, "tbody");
      for (const [key, name] of entries) addRow(body, key, name);
    }

    function addRow(body, key, name) {
      const blocked = !snap.allow && listed.has(key);
      const running = rendering.has(key);
      const row = node(body, "tr");
      if (running) row.className = "is-rendering";
      node(row, "th", name).scope = "row";
      const situation = node(row, "td");
      if (running) badge(situation, "Renderizando", "yellow");
      if (blocked) badge(situation, "Bloqueado", "red");
      if (!running && !blocked) {
        if (eligible.has(key)) badge(situation, "Elegível", "green");
        else
          badge(
            situation,
            snap.workers_complete
              ? "Sem elegibilidade confirmada"
              : "Indisponível",
          );
      }
      const cell = node(row, "td");
      if (editable && (known.has(key) || blocked)) {
        const label = blocked ? "Desbloquear" : "Bloquear";
        button(
          cell,
          label,
          () =>
            act(
              "deny",
              { worker: name, blocked: !blocked },
              label +
                " " +
                name +
                " somente neste job. As tarefas em execução podem continuar.",
            ),
          blocked ? "unlock" : "lock",
        ).setAttribute("aria-label", label + " " + name);
      } else node(cell, "span", "—");
    }
  }

  function updateHeader(snap) {
    const current = job.value === mainJob;
    const summary = (field) =>
      current
        ? document.getElementById("modal_deadline_" + field).textContent
        : "—";
    const running = snap?.tasks.filter((task) => task.status === "rendering");
    jobState.textContent = snap
      ? states[snap.status] || snap.status
      : summary("state");
    jobState.dataset.tone =
      {
        active: "green",
        completed: "green",
        failed: "red",
        suspended: "yellow",
      }[snap?.status] ||
      (jobState.textContent === "Renderizando" ? "yellow" : "neutral");
    document.getElementById("deadlineRecoveryWorker").textContent = running
      ? [...new Set(running.map((task) => task.worker).filter(Boolean))].join(
          ", ",
        ) || "—"
      : summary("worker");
    document.getElementById("deadlineRecoveryRunning").textContent =
      running?.length ?? summary("running");
    document.getElementById("deadlineRecoveryFailed").textContent = snap
      ? snap.tasks.filter((task) => task.status === "failed").length
      : summary("failed");
  }

  function updateSummary(snap, stamp) {
    const rendering = snap.tasks.filter((task) => task.status === "rendering");
    document.getElementById("modal_deadline_state").textContent =
      states[snap.status] || snap.status;
    document.getElementById("modal_deadline_worker").textContent =
      [...new Set(rendering.map((task) => task.worker).filter(Boolean))].join(
        ", ",
      ) || "—";
    const running = document.getElementById("modal_deadline_running");
    const failed = document.getElementById("modal_deadline_failed");
    running.textContent = rendering.length;
    failed.textContent = snap.tasks.filter(
      (task) => task.status === "failed",
    ).length;
    running.title = failed.title = stamp ? date(stamp) : "";
    if (stamp)
      document.getElementById("modal_deadline_sync").textContent =
        "Sincronizado em " + date(stamp);
  }

  function clearPanels() {
    actions.replaceChildren();
    machines.replaceChildren();
    timeline.replaceChildren();
  }

  function showHistory(data, snap) {
    timeline.replaceChildren();
    evidence(timeline, data.evidence, snap?.reports, snap?.report_count);
    history(timeline, data.history);
  }

  function unavailable(message) {
    for (const target of [actions, machines]) {
      target.replaceChildren();
      node(target, "p", message);
    }
  }

  function resubmitOptions(defaults) {
    return FlowAlert.input({
      type: "warning",
      title: "Resubmit Job",
      message:
        "O novo job será vinculado como uma tentativa e seguirá para revisão. O Deadline reutiliza o destino de saída do job original e pode sobrescrever os arquivos desse destino.",
      confirmText: "Reenviar job",
      cancelText: "Cancelar",
      fields: [
        {
          name: "name",
          label: "Nome do novo job",
          value: defaults.name,
          maxlength: 160,
          required: true,
        },
        {
          name: "frames",
          label: "Lista de frames",
          value: defaults.frames,
          maxlength: 1000,
          required: true,
        },
        {
          name: "chunk",
          label: "Frames por tarefa",
          type: "number",
          value: defaults.chunk,
          min: 1,
          required: true,
        },
        {
          name: "suspended",
          label: "Enviar suspenso",
          optionLabel: "Enviar suspenso",
          type: "checkbox",
        },
      ],
      validate(values) {
        if (!values.name.trim()) return "Informe o nome do novo job.";
        if (
          !/^-?\d+(?:\s*-\s*-?\d+(?:[xX]\d+)?)?(?:\s*,\s*-?\d+(?:\s*-\s*-?\d+(?:[xX]\d+)?)?)*$/.test(
            values.frames.trim(),
          )
        )
          return "Informe os frames, por exemplo 1,3,5-10 ou 1-20x2.";
        const chunk = Number(values.chunk);
        if (!Number.isInteger(chunk) || chunk < 1 || chunk > 2147483647)
          return "Informe um número inteiro válido de frames por tarefa.";
      },
    });
  }

  const keyFor = (t) => t.render_id + ":" + t.job_id;
  const target = () => ({ render_id: renderId, job_id: job.value });

  async function load(action, params = {}, source = null, previous = "") {
    if (!manager || !renderId || !job.value) return;
    const current = ++generation;
    const selected = target();
    const key = keyFor(selected);
    const stillHere = () => current === generation;
    const busy = (value) => {
      loader.hidden = !value;
      refresh.disabled = value;
      actions.inert = machines.inert = value;
    };
    busy(true);
    status.textContent = previous || "Consultando o Deadline…";
    try {
      let id = pending.get(key);
      if (!id) {
        if (action === "inspect") {
          const cached = await api(selected);
          if (!stillHere()) return;
          showHistory(cached);
          id = cached.request?.id;
        }
        if (!id) {
          const response = await api(selected, null, {
            operation: action,
            params,
            source,
            confirmed: action !== "inspect",
          });
          id = response.id;
        }
        pending.set(key, id);
      }
      for (let i = 0; i < 75 && stillHere(); i++) {
        const data = await api(selected, id);
        if (!stillHere()) return;
        const req = data.request;
        if (["queued", "running"].includes(req.status)) {
          await new Promise((resolve) => setTimeout(resolve, 2000));
          continue;
        }
        pending.delete(key);
        if (req.result.attempt && req.result.new_job_id) {
          resubmitNotice = req.result.message;
          status.textContent = resubmitNotice;
          unavailable("Atualizando a nova tentativa…");
          document.dispatchEvent(
            new CustomEvent("render:deadline-resubmitted", {
              detail: { renderId: selected.render_id },
            }),
          );
          return;
        }
        if (req.status === "succeeded" && !req.result.state) {
          const ram = req.result.incompatible_ram || [];
          return load(
            "inspect",
            {},
            null,
            "Alteração confirmada no Deadline." +
              (ram.length ? " RAM insuficiente: " + ram.join(", ") + "." : ""),
          );
        }
        clearPanels();
        if (req.result.state) {
          const snap = req.result.state;
          const interact = async (open) => {
            if (!window.FlowAlert || !stillHere() || !panel.open || confirming)
              return null;
            confirming = true;
            try {
              const confirmation = open();
              const region = document.getElementById("flow-alert-modal-region");
              if (region) panel.append(region);
              const answer = await confirmation;
              return answer.isConfirmed && stillHere() ? answer : null;
            } finally {
              confirming = false;
            }
          };
          const confirmedAction = async (name, args, message) => {
            const answer = await interact(() =>
              FlowAlert.confirm({
                type: "warning",
                title: names[name] + "?",
                message: message + " Job: " + selected.job_id,
                confirmText: "Confirmar",
                cancelText: "Cancelar",
              }),
            );
            if (answer) load(name, args, req.id);
          };
          updateHeader(snap);
          if (selected.job_id === mainJob) updateSummary(snap, req.finished_at);
          tools(actions, snap, confirmedAction);
          if (
            snap.resubmit &&
            selected.job_id === mainJob &&
            ["Em aprovação", "Aprovado", "Finalizado"].includes(renderStatus)
          ) {
            const control = actions.querySelector(".deadline-recovery-section");
            button(
              control,
              names.resubmit,
              async () => {
                const answer = await interact(() =>
                  resubmitOptions(snap.resubmit),
                );
                if (!answer) return;
                const { name, frames, chunk, suspended } = answer.value;
                load(
                  "resubmit",
                  {
                    name: name.trim(),
                    frames: frames.trim(),
                    chunk: Number(chunk),
                    suspended,
                  },
                  req.id,
                );
              },
              actionIcons.resubmit,
            );
          }
          renderWorkers(snap, confirmedAction);
          showHistory(data, snap);
          status.textContent = previous;
        } else {
          showHistory(data);
          status.textContent = req.result.message || outcomes[req.status];
          unavailable("Atualize o job para consultar as ferramentas.");
        }
        return;
      }
      if (stillHere())
        status.textContent =
          "O resultado ainda não foi confirmado. Use Atualizar para verificar a solicitação.";
    } catch (error) {
      if (stillHere()) {
        status.textContent = error.message;
        unavailable("Dados do job indisponíveis.");
      }
    } finally {
      if (stillHere()) busy(false);
    }
  }

  function reset() {
    generation++;
    clearPanels();
    actions.inert = machines.inert = false;
    status.textContent = "";
    if (refresh) refresh.disabled = false;
    loader.hidden = true;
  }

  document.addEventListener("render:deadline-job", ({ detail }) => {
    reset();
    renderId = detail.renderId;
    mainJob = detail.jobId?.toLowerCase();
    renderStatus = detail.renderStatus;
    image.textContent = detail.imageName || "";
    job.replaceChildren();
    const attempts = new Map();
    for (const attempt of detail.attempts || []) {
      const id = attempt.deadline_job_id?.toLowerCase();
      if (validJob.test(id || "") && !attempts.has(id))
        attempts.set(id, attempt.numero_tentativa);
    }
    const ids = new Set([mainJob, ...attempts.keys()]);
    for (const id of ids) {
      if (!validJob.test(id || "")) continue;
      const number = attempts.get(id);
      const label = number ? `Tentativa ${number}` : "Job";
      node(
        job,
        "option",
        `${label}${id === mainJob ? " (atual)" : ""} · ${id}`,
      ).value = id;
    }
    updateHeader();
    activate(tabs[manager ? 0 : 2]);
    if (panel.open && resubmitNotice) load("inspect", {}, null, resubmitNotice);
    resubmitNotice = null;
  });

  opener.addEventListener("click", () => {
    if (!job.value || panel.open) return;
    panel.showModal();
    if (manager && loader.hidden) load("inspect");
    if (!manager)
      unavailable("As ações de recuperação estão disponíveis para gestores.");
  });
  document
    .getElementById("closeDeadlineRecovery")
    .addEventListener("click", () => {
      if (!confirming) panel.close();
    });
  panel.addEventListener("cancel", (event) => {
    if (confirming) event.preventDefault();
  });
  panel.addEventListener("close", () => opener.focus({ preventScroll: true }));
  job.addEventListener("change", () => {
    reset();
    updateHeader();
    if (manager) load("inspect");
    else
      unavailable("As ações de recuperação estão disponíveis para gestores.");
  });
  refresh?.addEventListener("click", () => load("inspect"));
})();
