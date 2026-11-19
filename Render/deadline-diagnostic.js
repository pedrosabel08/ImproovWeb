(() => {
  const dialog = document.getElementById("deadlineDiagnosis");
  if (!dialog) return;
  const content = document.getElementById("deadlineDiagnosisContent");
  const status = document.getElementById("deadlineDiagnosisStatus");
  const dashboard = document.getElementById("deadlineDiagnosisDashboard");
  const metrics = document.getElementById("deadlineDiagnosisMetrics");
  const filters = document.getElementById("deadlineDiagnosisFilters");
  const tabs = document.getElementById("deadlineDiagnosisTabs");
  const scroll = dialog.querySelector(".dd-scroll");
  const state = {
    open: "Aberto",
    monitoring: "Em monitoramento",
    resolved: "Resolvido",
  };
  const role = { cause: "Causa", symptom: "Sintoma" };
  const historyLabels = {
    opened: "Incidente aberto",
    reopened: "Incidente reaberto",
    monitoring: "Em monitoramento",
    resolved: "Incidente resolvido",
    open: "Incidente voltou ao estado aberto",
    alert_shadow: "Evento em shadow",
    alert_active: "Evento enfileirado no Flow Connect",
    occurrence: "Ocorrência registrada",
  };
  const unavailable = {
    worker: "Worker",
    task_id: "Tarefa",
    frame: "Frames",
    occurred_at: "Horário da ocorrência",
    plugin: "Plugin",
    peak_ram: "Pico de RAM",
    renderer: "Renderer",
    worker_capacity: "Capacidade do Worker",
    report_body: "Conteúdo do relatório",
    full_excerpt: "Parte do log foi omitida pelo limite de coleta",
  };
  const requests = new Map();
  const cached = new Map();
  let renderId;
  let applied;
  let active;
  let sort;

  function node(tag, text, parent) {
    const element = document.createElement(tag);
    if (text !== null && text !== undefined) element.textContent = text;
    parent?.append(element);
    return element;
  }

  function time(value) {
    return value
      ? new Date(value.replace(" ", "T") + "Z").toLocaleString("pt-BR", {
          timeZone: "America/Sao_Paulo",
        })
      : "Indisponível";
  }

  function fields(parent, entries) {
    const list = node("dl", null, parent);
    list.className = "render-ef-details";
    for (const [label, value] of entries) {
      if (value === null || value === undefined || value === "") continue;
      const item = node("div", null, list);
      node("dt", label, item);
      node("dd", value, item);
    }
  }

  function list(parent, lines) {
    if (!lines?.length) return;
    const items = node("ul", null, parent);
    for (const line of lines) node("li", line, items);
  }

  function severity(value) {
    return value === "CRITICO" ? "Crítica" : "Requer atenção";
  }

  function gib(bytes) {
    return bytes == null
      ? null
      : `${(bytes / 1024 ** 3).toLocaleString("pt-BR", { maximumFractionDigits: 1 })} GiB`;
  }

  function occurrence(parent, item, expand) {
    const diagnosis = item.diagnosis;
    const evidence = item.evidence;
    const report = item.report;
    const details = node("details", null, parent);
    details.open = expand;
    node(
      "summary",
      `${time(item.occurred_at)} · ${item.worker || "Worker indisponível"} · ${diagnosis?.category_label || "Relatório pendente"}`,
      details,
    );
    const capacity = report.capacity || {};
    const assessment =
      diagnosis?.confidence === "indeterminado"
        ? "Diagnóstico"
        : diagnosis?.role === "symptom"
          ? "Sintoma identificado"
          : "Causa";
    fields(details, [
      ["Erro registrado", evidence.main],
      [assessment, diagnosis?.diagnosis],
      ["Confiança", diagnosis?.confidence || "indeterminado"],
      ["Severidade", diagnosis && severity(diagnosis.severity)],
      [
        "Estado",
        item.incident_state &&
          (item.episode === item.current_episode
            ? state[item.incident_state]
            : "Resolvido em episódio anterior"),
      ],
      [
        "Incidente",
        item.incident_id && `#${item.incident_id} · Episódio ${item.episode}`,
      ],
      ["Job", item.job_name || item.job_id],
      ["ID do job", item.job_id],
      ["Coletado em", item.collected_at && time(item.collected_at)],
      ["Tarefa", item.task_id],
      ["Frames", item.frame],
      ["Processo", evidence.process],
      ["Exceção", evidence.exception],
      ["Aplicativo", evidence.application_version],
      ["Renderer", evidence.renderer],
      ["Plugin", report.plugin],
      [
        "RAM reportada no momento da falha",
        evidence.ram_percent == null ? null : `${evidence.ram_percent}%`,
      ],
      ["Pico de RAM (valor do relatório)", report.peak_ram],
      ["RAM física informada na coleta", gib(capacity.physical_bytes)],
      ["RAM disponível na coleta", gib(capacity.free_bytes)],
      ["Requisito declarado de RAM", gib(capacity.required_bytes)],
      ["Grupos do Worker na coleta", capacity.groups?.join(", ")],
      ["Pools do Worker na coleta", capacity.pools?.join(", ")],
      ["Grupo do job", report.job_group],
      ["Pool do job", report.job_pool],
      ["Explicação", diagnosis?.technical],
      ["Recomendação", diagnosis?.recommendation],
      [
        "Regra",
        diagnosis &&
          `${diagnosis.rule} v${diagnosis.version} · Catálogo v${diagnosis.catalog_version}`,
      ],
      ["Assinatura", item.signature],
      ["Relatório no Deadline Monitor", item.report_id],
    ]);
    original(details, item);
    if (diagnosis?.alternatives?.length) {
      node("h3", "Sintomas e hipóteses alternativas", details);
      list(
        details,
        diagnosis.alternatives.map(
          (item) =>
            `${role[item.role]} · ${item.confidence} · ${item.technical}`,
        ),
      );
    }
    if (diagnosis?.factors?.length) {
      node("h3", "Possíveis fatores contribuintes", details);
      list(details, diagnosis.factors);
    }
    if (evidence.unavailable?.length) {
      node("h3", "Dados indisponíveis ou parciais", details);
      list(
        details,
        evidence.unavailable.map((key) => unavailable[key] || key),
      );
    }
    if (evidence.excerpts?.length) {
      const source = node("details", null, details);
      node("summary", "Trechos do relatório", source);
      excerpt(source, evidence.excerpts);
    }
    if (item.classifications.length > 1) {
      const history = node("details", null, details);
      node("summary", "Histórico de classificação", history);
      list(
        history,
        item.classifications.map(
          (event) =>
            `${time(event.recorded_at)} · ${event.rule} · ${event.confidence} · Catálogo v${event.catalog_version}`,
        ),
      );
    }
  }

  const tabItems = [
    ["list", "Incidentes", "list-ul"],
    ["analysis", "Análise geral", "chart-simple"],
    ["occurrences", "Ocorrências", "clock"],
    ["reports", "Relatórios", "file-lines"],
  ];
  const periods = {
    1: "Últimas 24 horas",
    7: "Últimos 7 dias",
    30: "Últimos 30 dias",
  };
  const number = new Intl.NumberFormat("pt-BR");
  const count = (value) => number.format(Number(value) || 0);
  const field = (name) => filters.elements.namedItem(name);

  function icon(name, parent) {
    const element = node("i", null, parent);
    element.className = `fa-solid fa-${name}`;
    element.setAttribute("aria-hidden", "true");
    return element;
  }

  function button(parent, label, action, className = "dd-button") {
    const element = node("button", label, parent);
    element.type = "button";
    element.className = className;
    element.addEventListener("click", action);
    return element;
  }

  function nav(container, items, selected, panel, activate) {
    container.replaceChildren();
    container.setAttribute("role", "tablist");
    const buttons = items.map(([id, label, glyph]) => {
      const tab = button(container, null, () => activate(id));
      tab.id = `${container.id}-${id}`;
      tab.dataset.tab = id;
      tab.setAttribute("role", "tab");
      tab.setAttribute("aria-controls", panel.id);
      icon(glyph, tab);
      node("span", label, tab);
      return tab;
    });
    panel.setAttribute("role", "tabpanel");
    markTab(container, selected, panel);
    container.onkeydown = (event) => {
      const current = buttons.indexOf(event.target);
      if (
        current < 0 ||
        !["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)
      )
        return;
      event.preventDefault();
      const next =
        event.key === "Home"
          ? 0
          : event.key === "End"
            ? buttons.length - 1
            : (current +
                buttons.length +
                (event.key === "ArrowRight" ? 1 : -1)) %
              buttons.length;
      buttons[next].focus();
    };
  }

  function markTab(container, selected, panel) {
    for (const tab of container.children) {
      const current = tab.dataset.tab === selected;
      tab.setAttribute("aria-selected", String(current));
      tab.tabIndex = current ? 0 : -1;
      if (current) panel.setAttribute("aria-labelledby", tab.id);
    }
  }

  function pending() {
    status.hidden = requests.size === 0;
  }

  function cancel(...keys) {
    for (const key of keys.length ? keys : [...requests.keys()]) {
      const pendingRequest = requests.get(key);
      pendingRequest?.controller.abort();
      pendingRequest?.target.removeAttribute("aria-busy");
      requests.delete(key);
    }
    pending();
  }

  async function query(key, params, target, show, retry, cache = false) {
    cancel(key);
    const search = new URLSearchParams({
      action: "getDeadlineDiagnoses",
      ...params,
    });
    const url = search.toString();
    if (cache && cached.has(url)) {
      show(cached.get(url));
      return;
    }
    const request = { controller: new AbortController(), target };
    requests.set(key, request);
    target.setAttribute("aria-busy", "true");
    pending();
    try {
      const response = await fetch(`ajax.php?${url}`, {
        signal: request.controller.signal,
      });
      const result = await response.json().catch(() => null);
      if (!response.ok || !result)
        throw new Error(
          result?.message || "Não foi possível consultar o diagnóstico.",
        );
      if (requests.get(key) !== request) return;
      if (cache) {
        if (cached.size >= 24) cached.delete(cached.keys().next().value);
        cached.set(url, result);
      }
      show(result);
    } catch (error) {
      if (requests.get(key) !== request || error.name === "AbortError") return;
      const box = node("div", null, target);
      box.className = "dd-error";
      node("p", error.message, box).setAttribute("role", "alert");
      button(box, "Tentar novamente", () => {
        box.remove();
        retry();
      });
    } finally {
      if (requests.get(key) === request) {
        requests.delete(key);
        target.removeAttribute("aria-busy");
        pending();
      }
    }
  }

  function empty(parent, message) {
    node("p", message, parent).className = "dd-empty";
  }

  function original(parent, item) {
    if (!item.original_url) return;
    const link = node("a", "Abrir relatório original", parent);
    link.href = item.original_url;
    link.target = "_blank";
    link.rel = "noopener noreferrer";
  }

  function excerpt(parent, lines) {
    const text = lines.join("\n");
    const pre = node("pre", text, parent);
    pre.tabIndex = 0;
    const feedback = node("span", null, parent);
    feedback.className = "dd-copy-feedback";
    feedback.setAttribute("role", "status");
    button(parent, "Copiar trecho", async () => {
      try {
        await navigator.clipboard.writeText(text);
        feedback.textContent = "Trecho copiado.";
      } catch {
        const range = document.createRange();
        range.selectNodeContents(pre);
        window.getSelection().removeAllRanges();
        window.getSelection().addRange(range);
        feedback.textContent = "Trecho selecionado. Use o atalho de copiar.";
      }
    });
  }

  function dateLabel(value) {
    return value.split("-").reverse().join("/");
  }

  function summary() {
    const refresh = () =>
      query(
        "summary",
        { ...applied, view: "summary" },
        metrics,
        (data) => {
          metrics.replaceChildren();
          const s = data.summary;
          const categories = data.category;
          const ratio =
            categories && Number(s.occurrences)
              ? number.format(
                  Math.round((100 * categories.total) / s.occurrences),
                ) + "%"
              : "";
          const last = s.last_at ? time(s.last_at) : "—";
          const cards = [
            [
              "triangle-exclamation",
              "Incidentes",
              count(s.incidents),
              "Incidentes distintos no período",
            ],
            [
              "layer-group",
              "Ocorrências",
              count(s.occurrences),
              "Relatórios de falha registrados",
            ],
            [
              "desktop",
              "Máquina mais problemática",
              data.worker?.worker || "—",
              data.worker &&
                `${count(data.worker.total)} falhas · ${count(data.worker.jobs)} ${Number(data.worker.jobs) === 1 ? "job" : "jobs distintos"}`,
            ],
            [
              "cube",
              "Ocorrência mais recorrente",
              categories?.label || "—",
              categories && `${count(categories.total)} ocorrências · ${ratio}`,
            ],
            [
              "calendar-days",
              "Última ocorrência",
              last,
              "Horário de São Paulo",
            ],
          ];
          for (const [glyph, label, value, note] of cards) {
            const card = node("article", null, metrics);
            card.className = "dd-metric";
            icon(glyph, card);
            const body = node("div", null, card);
            node("span", label, body);
            node("strong", value, body);
            if (note) node("small", note, body);
          }
          for (const [name, options] of Object.entries(data.facets)) {
            const select = field(name);
            const previous = select.value;
            const previousLabel = select.selectedOptions[0]?.textContent;
            select.replaceChildren(new Option("Todos", ""));
            for (const item of options)
              select.add(new Option(item.label, item.value));
            if (previous && !options.some((item) => item.value === previous))
              select.add(new Option(previousLabel, previous));
            select.value = previous;
          }
        },
        refresh,
      );
    refresh();
  }

  function renderFeed(parent, params, mode, detail = false) {
    const rows = node("div", null, parent);
    rows.className = "dd-feed";
    const footer = node("div", null, parent);
    footer.className = "dd-feed-footer";
    function fetchPage(before) {
      footer.replaceChildren();
      query(
        detail ? "detail-pane" : "main",
        {
          ...params,
          view: mode,
          ...(before ? { before } : {}),
        },
        footer,
        (result) => {
          const items = result[mode];
          if (result.collection?.last_error && !before) {
            empty(
              rows,
              "A coleta do job está incompleta. Os dados já registrados estão disponíveis aqui.",
            );
          }
          if (!items.length && !before)
            empty(
              rows,
              mode === "reports"
                ? "Nenhum relatório técnico disponível."
                : "Nenhuma ocorrência registrada.",
            );
          items.forEach((item, index) => {
            if (mode === "occurrences") {
              occurrence(rows, item, detail && !before && index === 0);
            } else {
              const block = node("details", null, rows);
              node(
                "summary",
                `${time(item.occurred_at)} · ${item.worker || "Worker indisponível"} · ${item.job_name || item.job_id}`,
                block,
              );
              fields(block, [
                ["ID do job", item.job_id],
                ["Relatório no Deadline Monitor", item.report_id],
              ]);
              if (item.excerpts.length) excerpt(block, item.excerpts);
              original(block, item);
            }
          });
          if (result.next)
            button(footer, "Carregar mais", () => fetchPage(result.next));
        },
        () => fetchPage(before),
        !before,
      );
    }
    fetchPage();
  }

  function renderOverview(parent, data) {
    const { incident, correlation: c } = data;
    const latest = data.occurrences[0];
    const diagnosis = latest?.diagnosis;
    const overview = node("div", null, parent);
    overview.className = "dd-overview";
    fields(overview, [
      ["Worker", incident.worker || "Indisponível"],
      ["Episódio", incident.episode],
      ["Primeira ocorrência", time(incident.first_at)],
      ["Última ocorrência", time(incident.last_at)],
      ["Jobs afetados neste episódio", count(c.jobs_in_incident)],
      ["Ocorrências neste episódio", count(c.occurrences_in_incident)],
      ["Jobs com falha neste Worker (30 dias)", count(c.jobs_on_worker)],
      [
        "Outros Workers com a mesma assinatura (30 dias)",
        count(c.other_workers_with_signature),
      ],
      [
        "Workers com a mesma assinatura (30 dias)",
        count(c.workers_with_signature),
      ],
      [
        "Workers com falhas nos jobs deste episódio (30 dias)",
        count(c.workers_on_jobs),
      ],
      ["Falhas do mesmo renderer (30 dias)", count(c.renderer_failures)],
      [
        "Resolução",
        incident.resolved_at &&
          `${time(incident.resolved_at)} · ${incident.resolution_reason || ""}`,
      ],
    ]);
    const cards = node("div", null, overview);
    cards.className = "dd-notes";
    for (const [glyph, title, message, modifier] of [
      [
        "lightbulb",
        "Diagnóstico",
        diagnosis?.diagnosis || "Diagnóstico pendente.",
        "",
      ],
      ["circle-info", "Causa provável", diagnosis?.technical, ""],
      [
        "circle-check",
        "Recomendação",
        diagnosis?.recommendation,
        " dd-recommendation",
      ],
    ]) {
      if (!message) continue;
      const card = node("article", null, cards);
      card.className = "dd-note" + modifier;
      const h = node("h4", null, card);
      icon(glyph, h);
      node("span", title, h);
      node("p", message, card);
    }
    const evidence = latest?.evidence;
    const capacity = latest?.report.capacity;
    const technical = node("div", null, parent);
    technical.className = "dd-technical";
    fields(technical, [
      ["Renderer", evidence?.renderer || "Indisponível"],
      ["Plugin", latest?.report.plugin || "Indisponível"],
      [
        "RAM no momento da falha",
        [
          evidence?.ram_percent == null ? null : `${evidence.ram_percent}%`,
          capacity?.physical_bytes == null
            ? null
            : `${gib(capacity.physical_bytes)} físicos na coleta`,
          capacity?.free_bytes == null
            ? null
            : `${gib(capacity.free_bytes)} disponíveis na coleta`,
        ]
          .filter(Boolean)
          .join(" · ") || "Indisponível",
      ],
      ["Assinatura", incident.signature],
    ]);
  }

  function selectIncident(item, detail, workspace, listRows, focusDetail) {
    const listPosition = scroll.scrollTop;
    cancel("incident", "detail-pane");
    for (const entry of listRows.children) {
      if (entry.dataset.id === String(item.id))
        entry.setAttribute("aria-current", "true");
      else entry.removeAttribute("aria-current");
    }
    detail.replaceChildren();
    detail.scrollTop = 0;
    workspace.classList.add("dd-show-detail");
    const header = node("header", null, detail);
    header.className = "dd-pane-heading";
    const h = node("h3", null, header);
    h.tabIndex = -1;
    icon("triangle-exclamation", h);
    node(
      "span",
      `Incidente #${item.id} · ${state[item.state] || item.state}`,
      h,
    );
    button(header, "‹ Voltar à lista", () => {
      workspace.classList.remove("dd-show-detail");
      if (!listRows.getClientRects().length) return;
      scroll.scrollTop = listPosition;
      listRows
        .querySelector('[aria-current="true"]')
        ?.focus({ preventScroll: true });
    });
    const body = node("div", null, detail);
    body.className = "dd-detail";
    if (focusDetail && matchMedia("(max-width: 840px)").matches) h.focus();
    function showIncident() {
      query(
        "incident",
        { incident_id: item.id, view: "overview" },
        body,
        (data) => {
          const subnav = node("nav", null, body);
          subnav.id = "deadlineIncidentTabs";
          subnav.className = "dd-tabs dd-subtabs";
          subnav.setAttribute("aria-label", "Detalhes do incidente");
          const panel = node("section", null, body);
          panel.id = "deadlineIncidentPanel";
          const show = (id) => {
            cancel("detail-pane");
            markTab(subnav, id, panel);
            panel.replaceChildren();
            if (id === "overview") renderOverview(panel, data);
            else if (id === "history") {
              if (!data.history.length)
                empty(panel, "Nenhum evento registrado neste incidente.");
              else {
                const history = node("ol", null, panel);
                history.className = "dd-history";
                for (const event of data.history) {
                  const entry = node("li", null, history);
                  node(
                    "strong",
                    historyLabels[event.kind] || event.kind,
                    entry,
                  );
                  node(
                    "span",
                    [
                      time(event.recorded_at),
                      event.episode && `Episódio ${event.episode}`,
                      event.detail?.reason ||
                        event.detail?.action ||
                        event.detail?.job,
                    ]
                      .filter(Boolean)
                      .join(" · "),
                    entry,
                  );
                }
              }
            } else renderFeed(panel, { incident_id: item.id }, id, true);
          };
          nav(
            subnav,
            [
              ["overview", "Visão geral", "chart-simple"],
              [
                "occurrences",
                `Ocorrências (${count(data.correlation.occurrences_all_episodes)})`,
                "list-ul",
              ],
              ["history", "Histórico do incidente", "clock"],
              ["reports", "Trechos do relatório", "file-lines"],
            ],
            "overview",
            panel,
            show,
          );
          show("overview");
        },
        showIncident,
        true,
      );
    }
    showIncident();
  }

  function renderIncidents() {
    const workspace = node("div", null, content);
    workspace.className = "dd-workspace";
    const listPanel = node("section", null, workspace);
    listPanel.className = "dd-list-panel";
    const head = node("header", null, listPanel);
    head.className = "dd-pane-heading";
    const title = node("h3", "Incidentes", head);
    const order = node("select", null, head);
    order.setAttribute("aria-label", "Ordenar incidentes");
    [
      ["recent", "Mais recentes"],
      ["oldest", "Mais antigos"],
      ["occurrences", "Mais ocorrências"],
    ].forEach(([value, label]) => order.add(new Option(label, value)));
    order.value = sort;
    const rows = node("div", null, listPanel);
    rows.className = "dd-list-rows";
    const pages = node("nav", null, listPanel);
    pages.className = "dd-pagination";
    pages.setAttribute("aria-label", "Páginas de incidentes");
    const detail = node("section", null, workspace);
    detail.className = "dd-detail-panel";
    function load(page, focusPage = false) {
      cancel("incident", "detail-pane");
      rows.replaceChildren();
      pages.replaceChildren();
      detail.replaceChildren();
      workspace.classList.remove("dd-show-detail");
      query(
        "main",
        { ...applied, view: "list", page, sort },
        rows,
        (result) => {
          title.textContent = `Incidentes (${count(result.total)})`;
          if (!result.incidents.length) {
            empty(
              rows,
              "Nenhum incidente encontrado. Ajuste os filtros para ampliar a busca.",
            );
            empty(detail, "Os detalhes do incidente aparecem aqui.");
            return;
          }
          for (const item of result.incidents) {
            const entry = button(
              rows,
              null,
              () => selectIncident(item, detail, workspace, rows, true),
              "deadline-incident",
            );
            entry.dataset.id = item.id;
            const dot = node("span", null, entry);
            const level = item.diagnosis?.severity;
            dot.className = `dd-dot ${level === "CRITICO" ? "dd-critical" : level ? "dd-attention" : ""}`;
            if (level) dot.setAttribute("aria-label", severity(level));
            else dot.setAttribute("aria-hidden", "true");
            const copy = node("span", null, entry);
            node(
              "strong",
              `#${item.id} · ${item.worker || "Worker indisponível"}`,
              copy,
            );
            node(
              "small",
              `${item.diagnosis?.category_label || "Diagnóstico pendente"} · ${count(item.occurrences)} ${Number(item.occurrences) === 1 ? "ocorrência" : "ocorrências"}`,
              copy,
            );
            const badge = node("span", state[item.state] || item.state, entry);
            badge.className = "dd-badge";
            badge.dataset.state = item.state;
          }
          if (!matchMedia("(max-width: 840px)").matches)
            selectIncident(result.incidents[0], detail, workspace, rows, false);
          else empty(detail, "Selecione um incidente para investigar.");
          if (result.pages > 1) {
            const control = (label, destination, name, disabled = false) => {
              const el = button(pages, label, () => load(destination, true));
              el.setAttribute("aria-label", name);
              el.disabled = disabled;
              return el;
            };
            control("‹", result.page - 1, "Página anterior", result.page === 1);
            const nearby = [
              ...new Set([
                1,
                result.page - 1,
                result.page,
                result.page + 1,
                result.pages,
              ]),
            ]
              .filter((n) => n >= 1 && n <= result.pages)
              .sort((a, b) => a - b);
            let previous = 0;
            for (const n of nearby) {
              if (n - previous > 1) node("span", "…", pages);
              const el = control(count(n), n, `Página ${n}`);
              if (n === result.page) {
                el.setAttribute("aria-current", "page");
                if (focusPage) el.focus({ preventScroll: true });
              }
              previous = n;
            }
            control(
              "›",
              result.page + 1,
              "Próxima página",
              result.page === result.pages,
            );
          }
        },
        () => load(page),
        true,
      );
    }
    order.addEventListener("change", () => {
      sort = order.value;
      load(1);
    });
    load(1);
  }

  function chart(parent, title, items, label, value) {
    const card = node("section", null, parent);
    card.className = "dd-chart";
    node("h3", title, card);
    if (!items.length) return empty(card, "Sem dados no período.");
    const max = items.reduce(
      (largest, item) => Math.max(largest, Number(value(item))),
      1,
    );
    const bars = node("ol", null, card);
    bars.className = "dd-bars";
    for (const item of items) {
      const row = node("li", null, bars);
      node("span", label(item), row);
      const track = node("div", null, row);
      track.setAttribute("aria-hidden", "true");
      const fill = node("span", null, track);
      fill.style.width = `${(100 * value(item)) / max}%`;
      node("strong", count(value(item)), row);
    }
  }

  function renderAnalysis() {
    const refresh = () =>
      query(
        "main",
        { ...applied, view: "analysis" },
        content,
        (data) => {
          const grid = node("div", null, content);
          grid.className = "dd-analysis";
          chart(
            grid,
            "Ocorrências por dia",
            data.days,
            (x) => dateLabel(x.day),
            (x) => x.occurrences,
          );
          chart(
            grid,
            "Incidentes por dia",
            data.days,
            (x) => dateLabel(x.day),
            (x) => x.incidents,
          );
          chart(
            grid,
            "Distribuição por categoria de falha",
            data.categories,
            (x) => x.label,
            (x) => x.total,
          );
          chart(
            grid,
            "Workers com mais falhas",
            data.workers,
            (x) => x.worker,
            (x) => x.total,
          );
          empty(
            content,
            "A série mostra dias com registros. Um incidente pode aparecer em vários dias; o resumo conta cada incidente uma vez no período.",
          );
        },
        refresh,
        true,
      );
    refresh();
  }

  function showMain(id) {
    active = id;
    cancel("main", "incident", "detail-pane");
    content.replaceChildren();
    markTab(tabs, id, content);
    if (id === "list") renderIncidents();
    else if (id === "analysis") renderAnalysis();
    else renderFeed(content, renderId ? { render_id: renderId } : applied, id);
  }

  function dates() {
    const custom = field("period").value === "custom";
    filters.querySelector(".dd-dates").hidden = !custom;
    for (const name of ["start", "end"]) {
      field(name).disabled = !custom;
      field(name).required = custom;
      field(name).setCustomValidity("");
    }
  }

  function applyFilters() {
    cached.clear();
    applied = Object.fromEntries(new FormData(filters));
    document.getElementById("deadlineDiagnosisPeriod").textContent =
      `(${periods[applied.period] || `${dateLabel(applied.start)} a ${dateLabel(applied.end)}`})`;
    metrics.replaceChildren();
    showMain(active);
    summary();
  }

  field("period").addEventListener("change", dates);
  for (const name of ["start", "end"])
    field(name).addEventListener("input", () =>
      field("end").setCustomValidity(""),
    );
  filters.addEventListener("submit", (event) => {
    event.preventDefault();
    if (
      field("period").value === "custom" &&
      field("start").value > field("end").value
    ) {
      field("end").setCustomValidity(
        "A data final deve ser igual ou posterior à inicial.",
      );
      field("end").reportValidity();
      return;
    }
    applyFilters();
  });
  function resetFilters() {
    filters.reset();
    dates();
    applyFilters();
  }
  filters.querySelector(".dd-clear").addEventListener("click", resetFilters);

  function open(id) {
    cancel();
    cached.clear();
    renderId = id;
    sort = "recent";
    active = id ? "occurrences" : "list";
    dashboard.hidden = Boolean(id);
    nav(tabs, id ? tabItems.slice(2) : tabItems, active, content, showMain);
    if (!dialog.open) dialog.showModal();
    scroll.scrollTop = 0;
    if (id) showMain(active);
    else resetFilters();
  }

  document
    .getElementById("openDeadlineIncidents")
    ?.addEventListener("click", () => open(null));
  document
    .getElementById("openRenderDiagnoses")
    ?.addEventListener("click", () => {
      const id = Number(document.getElementById("modal_idrender").textContent);
      if (id) open(id);
    });
  dialog.addEventListener("close", () => {
    cancel();
    cached.clear();
  });
})();
