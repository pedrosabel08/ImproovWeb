(() => {
  const panel = document.getElementById("renderFarm");
  if (!panel) return;
  const get = (id) => document.getElementById(id);
  const labels = {
    rendering: "Renderizando",
    idle: "Ocioso",
    offline: "Offline",
    stalled: "Stalled",
    disabled: "Desabilitado",
    unknown: "Indisponível",
  };
  const incidentLabels = {
    offline: "Worker offline",
    stalled: "Worker em stalled",
    task_failure: "Falha em tarefa",
    communication: "Falha de comunicação com o Deadline",
  };
  const settings = {
    notice: {
      partial:
        "A consulta retornou dados parciais. Alguns Workers podem estar sem atualização.",
      stale: "A coleta está atrasada. Exibindo os últimos dados disponíveis.",
      unavailable:
        "Dados do Deadline indisponíveis. A atualização será tentada novamente.",
      waiting: "A coleta da Farm ainda não enviou dados para este ambiente.",
    },
    pollInterval: 10000,
  };
  const number = new Intl.NumberFormat("pt-BR", { maximumFractionDigits: 1 });
  let snapshot;
  let selectedName = "";
  let detailView = "overview";
  let active = false;
  let showHistory = false;
  let request;
  let timer;

  function display(value) {
    return value == null || value === "" ? "—" : escapeHtml(value);
  }

  function showTime(epoch) {
    return epoch ? new Date(Number(epoch) * 1000).toLocaleString("pt-BR") : "—";
  }

  function percent(value, label) {
    return value == null
      ? "—"
      : `<span class="farm-progress"><progress max="100" value="${value}" aria-label="${label}"></progress><b>${number.format(value)}%</b></span>`;
  }

  function statusBadge(worker) {
    const state = Object.hasOwn(labels, worker.state)
      ? worker.state
      : "unknown";
    return `<span class="farm-status state-${state}"><span></span>${labels[state]}</span>`;
  }

  function options(id, values, optionLabels = {}) {
    const select = get(id);
    const current = select.value;
    const keys = [...new Set(values.filter(Boolean))];
    if (current && !keys.includes(current)) keys.push(current);
    keys.sort((a, b) =>
      (optionLabels[a] || a).localeCompare(optionLabels[b] || b),
    );
    select.replaceChildren(
      new Option("Todos", ""),
      ...keys.map((key) => new Option(optionLabels[key] || key, key)),
    );
    select.value = current;
  }

  function pairs(entries) {
    return `<dl>${entries.map(([key, value]) => `<div><dt>${key}</dt><dd>${display(value)}</dd></div>`).join("")}</dl>`;
  }

  function incidentList(items) {
    if (!items.length) return "<li>Nenhum incidente registrado.</li>";
    return items
      .map((item) => {
        const label = incidentLabels[item.kind] || "Incidente";
        const worker = item.worker
          ? `<strong>${escapeHtml(item.worker.toUpperCase())}</strong> · `
          : "";
        const state =
          item.resolved_reason === "worker_available"
            ? "Disponível novamente"
            : item.resolved_at
              ? "Resolvido"
              : "Em aberto";
        return `<li class="${item.resolved_at ? "is-resolved" : ""}"><span>${worker}${label}</span>
        <small>${state} · ${showTime(item.resolved_at || item.opened_at)}</small></li>`;
      })
      .join("");
  }

  function workerDetail(worker) {
    if (detailView === "history") {
      return `<div class="farm-box">${pairs([
        ["Tarefas concluídas", worker.completed],
        ["Tarefas com falha", worker.failed],
        ["Última atualização", worker.updated],
        ["Última gravação", worker.last_write],
      ])}<ul class="farm-incident-list">${incidentList(
        snapshot.incidents.filter(
          (item) => item.worker === worker.name.toLowerCase(),
        ),
      )}</ul></div>`;
    }
    const job = pairs([
      ["Status", labels[worker.state]],
      ["Job atual", worker.job_name],
      ["ID do job", worker.job_id],
      ["Plugin", worker.plugin],
      ["Pool", worker.pool],
      ["Grupo", worker.group],
      ["Prioridade", worker.priority],
      ["Usuário", worker.job_user],
    ]);
    const currentTask = worker.job_id
      ? `<section class="farm-current-task"><h3>Progresso da tarefa</h3>
      ${percent(worker.progress, "Progresso da tarefa")}<p>${display(worker.task_status)}</p>
      ${pairs([
        ["Tempo decorrido", worker.elapsed],
        ["Tempo restante estimado", worker.remaining],
      ])}</section>`
      : "";
    if (detailView === "tasks") {
      return `<div class="farm-box">${job}${currentTask}
        ${pairs([
          ["Tarefas atuais", worker.task_names],
          ["Tarefas concluídas", worker.completed],
          ["Com falha", worker.failed],
        ])}</div>`;
    }
    const ram =
      worker.memory > 0 && worker.memory_free != null
        ? Math.max(
            0,
            Math.min(
              100,
              (100 * (worker.memory - worker.memory_free)) / worker.memory,
            ),
          )
        : null;
    const ipOnly =
      worker.host && worker.host.toLowerCase() !== worker.name.toLowerCase();
    return `<div class="farm-detail-top">
      ${worker.preview ? `<img src="${renderPreviewUrl(worker.preview, 760)}" alt="Prévia do job atual">` : ""}
      <div class="farm-box">${job}</div>
      </div>${currentTask}
      <div class="farm-detail-bottom">
        <section class="farm-box"><h3>Recursos da máquina</h3>
          <div class="farm-resource"><span>CPU${worker.threads ? ` (${worker.threads} threads)` : ""}</span>${percent(worker.cpu, "Uso de CPU")}</div>
          <div class="farm-resource"><span>RAM${worker.memory ? ` (${number.format(worker.memory / 2 ** 30)} GiB)` : ""}</span>${percent(ram, "Uso de memória")}</div>
          <div class="farm-resource"><span>RAM livre</span><span>${worker.memory_free == null ? "—" : `${number.format(worker.memory_free / 2 ** 30)} GiB`}</span></div>
          <div class="farm-resource"><span>Disco livre</span><span>${display(worker.disk)}</span></div>
        </section>
        <section class="farm-box"><h3>Informações do Worker</h3>${pairs([
          ["SO", worker.os],
          ["Versão Deadline", worker.version],
          ["IP", worker.ip],
          ...(ipOnly ? [["Host", worker.host]] : []),
          ["MAC", worker.mac],
          ["Placa de vídeo", worker.gpu],
          [
            "Uptime",
            worker.uptime == null ? null : renderDuration(worker.uptime),
          ],
          ["Estado desde", worker.state_since],
          ["Pools", worker.pools?.join(", ")],
          ["Grupos", worker.groups?.join(", ")],
        ])}</section>
      </div>`;
  }

  function draw() {
    if (!active || !snapshot) return;
    const { workers, incidents, observed_at: observed, health } = snapshot;
    const search = get("farmSearch").value.trim().toLowerCase();
    const state = get("farmStatus").value;
    const pool = get("farmPool").value;
    const group = get("farmGroup").value;
    const matching = workers.filter(
      (worker) =>
        (!state || worker.state === state) &&
        (!pool || worker.pool === pool || worker.pools?.includes(pool)) &&
        (!group || worker.group === group || worker.groups?.includes(group)) &&
        (!search ||
          [worker.name, worker.host, worker.ip, worker.job_name].some((value) =>
            value?.toLowerCase().includes(search),
          )),
    );
    get("farmCount").hidden = !observed;
    get("farmCount").textContent = number.format(workers.length);
    const resultBadge = get("resultsBadge");
    resultBadge.hidden = !observed;
    get("resultsCount").textContent = number.format(matching.length);
    get("resultsTotal").textContent = ` / ${number.format(workers.length)}`;
    get("resultsUnit").textContent = "Workers";
    get("filterDot").classList.toggle(
      "visible",
      !!(search || state || pool || group),
    );
    panel.querySelectorAll("[data-farm-metric]").forEach((metric) => {
      const key = metric.dataset.farmMetric;
      const count =
        key === "total"
          ? matching.length
          : matching.filter((worker) => worker.state === key).length;
      metric.textContent = observed ? number.format(count) : "—";
      const progress = panel.querySelector(`[data-farm-share="${key}"]`);
      if (progress) {
        progress.hidden = !matching.length;
        progress.value = matching.length ? (100 * count) / matching.length : 0;
      }
    });

    const live = incidents.filter((item) => !item.resolved_at);
    get("farmAlertsCount").textContent =
      observed || live.length ? `(${live.length})` : "";
    const alertToggle = get("farmAlertsToggle");
    alertToggle.hidden = !incidents.length;
    alertToggle.textContent = showHistory ? "Mostrar ativos" : "Ver histórico";
    alertToggle.setAttribute("aria-expanded", String(showHistory));
    get("farmAlertList").innerHTML =
      showHistory || live.length
        ? incidentList(showHistory ? incidents : live)
        : `<li>${observed ? "Nenhum incidente em aberto." : "Aguardando dados do Deadline."}</li>`;
    const notice = get("farmNotice");
    notice.hidden = health === "ok";
    const noticeText = settings.notice[health] || settings.notice.unavailable;
    if (!notice.hidden && notice.textContent !== noticeText)
      notice.textContent = noticeText;

    const workspace = get("farmWorkspace");
    workspace.hidden = !observed;
    get("farmShowing").textContent =
      `${number.format(matching.length)} de ${number.format(workers.length)} Workers`;
    get("farmUpdated").textContent = observed
      ? `Atualização às ${new Date(observed * 1000).toLocaleTimeString("pt-BR")}`
      : "";

    const focused = document.activeElement?.dataset.workerName;
    const selected = matching.find((worker) => worker.name === selectedName);
    get("farmWorkers").innerHTML =
      matching
        .map(
          (worker) => `
      <tr class="${worker.name === selected?.name ? "is-selected" : ""}">
        <th scope="row"><button type="button" data-worker-name="${escapeHtml(worker.name)}" aria-controls="farmDetail"
          aria-expanded="${worker.name === selected?.name}">${display(worker.name)}</button><small>${display(worker.ip)}</small></th>
        <td>${statusBadge(worker)}</td>
        <td><span class="farm-job-name" title="${escapeHtml(worker.job_name || "")}">${display(worker.job_name)}</span>
          ${worker.plugin || worker.pool ? `<small>${[worker.plugin, worker.pool].filter(Boolean).map(escapeHtml).join(" · ")}</small>` : ""}</td>
        <td>${percent(worker.unavailable ? null : worker.progress, "Progresso do Worker")}</td>
        <td>${display(worker.unavailable ? null : worker.elapsed)}</td>
      </tr>`,
        )
        .join("") ||
      '<tr><td colspan="5" class="farm-empty">Nenhum Worker encontrado.</td></tr>';
    if (focused)
      [...get("farmWorkers").querySelectorAll("button")]
        .find((button) => button.dataset.workerName === focused)
        ?.focus({ preventScroll: true });

    get("farmDetail").hidden = !selected;
    workspace.classList.toggle("has-detail", !!selected);
    if (selected) {
      get("farmWorkerName").textContent = selected.name;
      get("farmWorkerDescription").textContent =
        selected.description || selected.host || "";
      get("farmWorkerDot").className =
        `farm-worker-dot state-${selected.state}`;
      get("farmDetailBody").innerHTML = workerDetail(selected);
      panel
        .querySelectorAll("[data-farm-detail]")
        .forEach((button) =>
          button.setAttribute(
            "aria-pressed",
            String(button.dataset.farmDetail === detailView),
          ),
        );
    }
  }

  async function load() {
    if (!active || request) return;
    const controller = new AbortController();
    request = controller;
    get("farmRefresh").disabled = true;
    get("farmLoading").hidden = !!snapshot;
    try {
      const response = await fetch("farm.php", {
        signal: controller.signal,
        cache: "no-store",
      });
      if (!response.ok) throw new Error("Farm indisponível");
      const data = await response.json();
      if (!active || request !== controller) return;
      snapshot = data;
      options("farmStatus", Object.keys(labels), labels);
      options(
        "farmPool",
        data.workers.flatMap((worker) => [
          ...(worker.pools || []),
          worker.pool,
        ]),
      );
      options(
        "farmGroup",
        data.workers.flatMap((worker) => [
          ...(worker.groups || []),
          worker.group,
        ]),
      );
      draw();
    } catch (error) {
      if (!active || request !== controller || error.name === "AbortError")
        return;
      snapshot = snapshot
        ? { ...snapshot, health: "stale" }
        : { workers: [], incidents: [], health: "unavailable" };
      draw();
    } finally {
      if (request === controller) {
        request = null;
        get("farmLoading").hidden = true;
        get("farmRefresh").disabled = false;
      }
    }
  }

  window.setRenderFarmActive = (isActive) => {
    active = isActive;
    clearInterval(timer);
    if (active) {
      draw();
      load();
      timer = setInterval(() => {
        if (!document.hidden) load();
      }, settings.pollInterval);
    } else {
      request?.abort();
      request = null;
    }
  };
  document.addEventListener("visibilitychange", () => {
    if (!document.hidden && active) load();
  });
  get("farmRefresh").addEventListener("click", load);
  get("farmSearch").addEventListener("input", draw);
  ["farmStatus", "farmPool", "farmGroup"].forEach((id) =>
    get(id).addEventListener("change", draw),
  );
  get("farmAlertsToggle").addEventListener("click", () => {
    showHistory = !showHistory;
    draw();
  });
  get("farmWorkers").addEventListener("click", (event) => {
    const rowButton = event.target
      .closest("tr")
      ?.querySelector("[data-worker-name]");
    if (!rowButton) return;
    selectedName = rowButton.dataset.workerName;
    detailView = "overview";
    draw();
    if (window.matchMedia("(max-width: 1150px)").matches)
      get("farmDetail").scrollIntoView({ block: "nearest" });
  });
  get("farmDetailClose").addEventListener("click", () => {
    const previous = selectedName;
    selectedName = "";
    draw();
    [...get("farmWorkers").querySelectorAll("button")]
      .find((button) => button.dataset.workerName === previous)
      ?.focus({ preventScroll: true });
  });
  panel.querySelectorAll("[data-farm-detail]").forEach((button) =>
    button.addEventListener("click", () => {
      detailView = button.dataset.farmDetail;
      draw();
    }),
  );
})();
