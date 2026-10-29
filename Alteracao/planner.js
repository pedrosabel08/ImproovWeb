/* Weekly execution view. Planning remains owned by Planejamento R01+. */
(() => {
  "use strict";
  const $ = (id) => document.getElementById(id);
  const escape = (value) =>
    String(value ?? "").replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[c],
    );
  const normalized = (value) =>
    String(value ?? "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  const shortDate = (value) =>
    value ? value.slice(8, 10) + "/" + value.slice(5, 7) : "";
  const fullDate = (value) => shortDate(value) + "/" + value.slice(0, 4);
  const filterIds = {
    obra_id: "filtro-obra",
    colaborador_id: "filtro-colaborador",
    status_imagem: "filtro-status-imagem",
    nivel: "filtro-nivel",
    status: "filtro-status",
    busca: "filtro-busca",
  };
  const statusClass = (status) =>
    ({
      "nao iniciado": "pending",
      hold: "hold",
      "em andamento": "working",
      "em aprovacao": "approval",
      ajuste: "adjustment",
      "aprovado com ajustes": "adjusted",
      finalizado: "done",
      aprovado: "done",
    })[normalized(status)] || "pending";
  const state = {
    data: null,
    week: "",
    day: "",
    view: "alteracoes",
    backlog: false,
    filters: {},
    hideDone: false,
    request: 0,
    controller: null,
    open: null,
    alignedWeek: "",
  };

  function readUrl() {
    const params = new URLSearchParams(location.search);
    state.week = params.get("semana") || "";
    state.day = params.get("dia") || "";
    state.view = params.get("visao") === "render" ? "render" : "alteracoes";
    state.backlog = params.get("sem_planejamento") === "1";
    state.hideDone = params.get("ocultar_concluidas") === "1";
    for (const [key, id] of Object.entries(filterIds)) {
      state.filters[key] = params.get(key) || "";
      if ($(id).tagName === "INPUT") $(id).value = state.filters[key];
    }
    $("ocultar-concluidas").checked = state.hideDone;
  }

  function saveUrl() {
    const url = new URL(location.href);
    const params = {
      ...state.filters,
      semana: state.week,
      dia: state.day,
      visao: state.view === "render" ? "render" : "",
      sem_planejamento: state.backlog ? "1" : "",
      ocultar_concluidas: state.hideDone ? "1" : "",
    };
    for (const [key, value] of Object.entries(params))
      value ? url.searchParams.set(key, value) : url.searchParams.delete(key);
    history.replaceState(null, "", url.pathname + url.search + url.hash);
  }

  function options(id, entries, initial) {
    const select = $(id);
    select.replaceChildren();
    for (const [value, label] of initial.concat(
      entries.map((i) => [String(i.id), i.nome]),
    )) {
      const option = document.createElement("option");
      option.value = value;
      option.textContent = label;
      select.append(option);
    }
  }

  function fillFilters() {
    const filters = state.data.filtros;
    options("filtro-obra", filters.obras, [["", "Todas as obras"]]);
    options("filtro-colaborador", filters.colaboradores, [
      ["", "Todos"],
      ["sem", "Sem responsável"],
    ]);
    options(
      "filtro-status-imagem",
      filters.status_imagens.map((i) => ({ id: i.nome, nome: i.nome })),
      [["", "Todas"]],
    );
    for (const [key, id] of Object.entries(filterIds)) {
      if (
        $(id).tagName === "SELECT" &&
        state.filters[key] &&
        !Array.from($(id).options).some((o) => o.value === state.filters[key])
      ) {
        // Keep a selected filter when the next/previous week has no matching tasks.
        const option = document.createElement("option");
        option.value = state.filters[key];
        option.textContent = state.filters[key];
        $(id).append(option);
      }
      $(id).value = state.filters[key] || "";
    }
  }

  function matches(item) {
    const f = state.filters;
    if (f.obra_id && String(item.obra_id) !== f.obra_id) return false;
    if (
      f.colaborador_id &&
      (f.colaborador_id === "sem"
        ? !!item.colaborador_id
        : String(item.colaborador_id) !== f.colaborador_id)
    )
      return false;
    if (f.status_imagem && item.status_nome !== f.status_imagem) return false;
    if (
      f.nivel &&
      (f.nivel === "sem"
        ? !!item.nivel_complexidade
        : String(item.nivel_complexidade) !== f.nivel)
    )
      return false;
    if (
      f.status &&
      (f.status === "concluidas"
        ? !item.concluida
        : normalized(item.status_funcao) !== normalized(f.status))
    )
      return false;
    if (state.hideDone && item.concluida) return false;
    const text = normalized(
      [
        item.imagem_nome,
        item.obra_nome,
        item.obra_nome_comercial,
        item.colaborador_nome,
        item.status_nome,
        item.status_funcao,
        item.nivel_complexidade ? "N" + item.nivel_complexidade : "",
      ].join(" "),
    );
    return normalized(f.busca)
      .split(/\s+/)
      .every((word) => text.includes(word));
  }

  function card(item) {
    const article = document.createElement("article");
    article.className =
      "planner-card" +
      (item.concluida ? " is-done" : "") +
      (!item.colaborador_id ? " needs-owner" : "");
    article.dataset.funcaoId = item.funcao_id;
    article.dataset.entregaItemId = item.entrega_item_id || "";
    article.dataset.plannedDay = item.dia_planejado || "";
    const nivel = Number(item.nivel_complexidade);
    const etapa = /^R0[1-5]$|^EF$/.test(item.status_nome)
      ? item.status_nome.toLowerCase()
      : "default";
    const actionLabel = item.historica
      ? "Ver planejamento"
      : item.planejada
        ? "Editar planejamento"
        : "Planejar";
    const action = item.planejamento_url
      ? `<a class="planner-plan-link" href="${escape(item.planejamento_url)}" aria-label="${actionLabel}: ${escape(item.imagem_nome)}" title="${actionLabel}">${item.planejada ? "" : "Planejar "}<i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>`
      : "";
    article.innerHTML = `<button type="button" class="planner-card-open" title="${escape(item.imagem_nome)}" aria-label="Abrir alteração: ${escape(item.imagem_nome)}">
      <span class="planner-thumb" aria-hidden="true">${item.thumbnail_url ? `<img src="${escape(item.thumbnail_url)}" alt="" loading="lazy" decoding="async" width="62" height="68">` : '<i class="fa-regular fa-image"></i>'}</span>
      <span class="planner-card-info"><strong class="planner-card-title">${escape(item.imagem_nome)}</strong>
        <span class="planner-card-badges"><span class="planner-stage stage-${etapa}">${escape(item.status_nome)}</span><span class="planner-level level-${nivel || "none"}">${nivel >= 1 && nivel <= 5 ? "N" + nivel : "Sem nível"}</span></span>
        <span class="planner-owner${item.colaborador_id ? "" : " is-missing"}" title="${escape(item.colaborador_nome || "Sem responsável")}"><i class="fa-solid fa-user" aria-hidden="true"></i> ${escape(item.colaborador_nome || "Sem responsável")}</span>
        <span class="planner-card-bottom"><span class="planner-status status-${statusClass(item.status_funcao)}"><i aria-hidden="true">${item.concluida ? "✓" : "●"}</i> ${escape(item.concluida ? "Concluída" : item.status_funcao)}</span><span class="planner-project" title="${escape(item.obra_nome)}">${escape(item.obra_nome)}</span></span>
      </span></button>
      ${item.atrasada ? '<span class="planner-overdue"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Atrasada</span>' : ""}
      ${!item.planejada && !item.is_ef ? `<div class="planner-card-planning"><span title="${escape(item.prazo ? "Prazo legado: " + item.prazo + "; não é um dia confirmado." : "")}">${escape(item.sem_planejamento_motivo || "Sem dia confirmado")}</span>${action || '<a class="planner-plan-link" href="../Entregas/">Ver entregas</a>'}</div>` : action ? `<div class="planner-card-planning planned-action">${action}</div>` : ""}`;
    article
      .querySelector("button")
      .addEventListener("click", () =>
        item.historica
          ? location.assign(item.detalhe_url)
          : state.open(item.imagem_id),
      );
    article.querySelector("img")?.addEventListener(
      "error",
      (event) => {
        event.currentTarget.replaceWith(
          Object.assign(document.createElement("i"), {
            className: "fa-regular fa-image",
          }),
        );
      },
      { once: true },
    );
    return article;
  }

  function list(container, items, empty) {
    container.replaceChildren();
    if (!items.length) {
      const p = document.createElement("p");
      p.className = "planner-empty";
      p.textContent = empty;
      container.append(p);
    } else {
      const fragment = document.createDocumentFragment();
      items.forEach((item) => fragment.append(card(item)));
      container.append(fragment);
    }
  }

  function setFeedback(message, retry = false) {
    const box = $("planner-feedback");
    box.replaceChildren();
    box.hidden = !message;
    if (message) {
      const span = document.createElement("span");
      span.textContent = message;
      box.append(span);
    }
    if (retry) {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "planner-text-btn";
      button.textContent = "Tentar novamente";
      button.addEventListener("click", reload);
      box.append(button);
    }
  }

  function render() {
    if (!state.data) return;
    const week = state.data.semana;
    const items = state.data.items.filter(matches);
    const alterations = items.filter((i) => !i.is_ef);
    const planned = alterations.filter((i) => i.planejada);
    const unplanned = alterations.filter((i) => !i.planejada);
    const renders = items.filter((i) => i.is_ef);
    $("planned-count").textContent = planned.length;
    $("unplanned-count").textContent = unplanned.length;
    $("render-count").textContent = renders.length;
    $("resultsCount").textContent =
      state.view === "render" ? renders.length : alterations.length;
    const obra =
      $("filtro-obra").selectedOptions[0]?.textContent || "Todas as obras";
    $("planner-subtitle").textContent =
      `${obra} · Semana de ${fullDate(week.inicio)}`;
    $("semana-label").textContent =
      `${shortDate(week.inicio)} – ${fullDate(week.fim)}`;
    $("filtro-semana").value = week.inicio;
    const renderView = state.view === "render";
    $("tab-alteracoes").setAttribute("aria-selected", String(!renderView));
    $("tab-render").setAttribute("aria-selected", String(renderView));
    $("tab-alteracoes").tabIndex = renderView ? -1 : 0;
    $("tab-render").tabIndex = renderView ? 0 : -1;
    $("planner-content").hidden = renderView;
    $("render-content").hidden = !renderView;
    $("toggle-unplanned").hidden = renderView;
    $("toggle-unplanned").setAttribute("aria-expanded", String(state.backlog));
    $("unplanned-panel").hidden = !state.backlog;
    list(
      $("unplanned-cards"),
      unplanned,
      "Nenhuma alteração sem planejamento para estes filtros.",
    );
    list(
      $("render-cards"),
      renders,
      "Nenhum Render em Alta para estes filtros.",
    );
    const board = $("planner-board");
    board.replaceChildren();
    if (!week.dias.some((d) => d.data === state.day))
      state.day = week.dias.find((d) => d.hoje)?.data || week.inicio;
    for (const day of week.dias) {
      const cards = planned.filter((i) => i.dia_planejado === day.data);
      const section = document.createElement("section");
      section.className =
        "planner-day" +
        (day.hoje ? " is-today" : "") +
        (day.data === state.day ? " is-active-day" : "");
      section.dataset.day = day.data;
      section.setAttribute("aria-label", `${day.nome}, ${fullDate(day.data)}`);
      section.innerHTML = `<header class="planner-day-header"><div><h2>${shortDate(day.data)} ${day.hoje ? '<span class="planner-today">Hoje</span>' : ""}</h2><p>${escape(day.nome)}${!day.util ? " · Feriado" : ""}</p></div><span class="planner-day-count">${cards.length} ${cards.length === 1 ? "item" : "itens"}</span></header><div class="planner-card-list"></div>`;
      list(
        section.querySelector(".planner-card-list"),
        cards,
        day.util
          ? "Nenhuma alteração planejada"
          : "Sem expediente no calendário",
      );
      board.append(section);
    }
    const dayIndex = week.dias.findIndex((d) => d.data === state.day);
    $("dia-label").textContent =
      `${shortDate(state.day)} · ${week.dias[dayIndex].nome}`;
    $("dia-anterior").disabled = dayIndex === 0;
    $("dia-proximo").disabled = dayIndex === 4;
    if (state.alignedWeek !== week.inicio && !renderView) {
      const selected = board.querySelector(".is-active-day");
      if (selected && board.scrollWidth > board.clientWidth)
        board.scrollLeft = selected.offsetLeft - board.offsetLeft;
      state.alignedWeek = week.inicio;
    }
    const exceptional = planned.filter(
      (i) => !week.dias.some((d) => d.data === i.dia_planejado),
    );
    $("planner-exceptional").hidden = !exceptional.length;
    list($("planner-exceptional"), exceptional, "");
    const entregaUrls = [
      ...new Set(alterations.map((i) => i.planejamento_url).filter(Boolean)),
    ];
    $("editar-planejamento").href =
      entregaUrls.length === 1 ? entregaUrls[0] : "../Entregas/";
    setFeedback(
      !state.data.planejamento_disponivel
        ? "Planejamento R01+ indisponível neste ambiente. A fila está preservada em Sem planejamento."
        : !renderView && !planned.length
          ? `Nenhuma alteração planejada nesta semana para estes filtros.${unplanned.length ? " Consulte os itens em Sem planejamento." : ""}`
          : "",
    );
    saveUrl();
  }

  async function reload() {
    const request = ++state.request;
    state.controller?.abort();
    state.controller = new AbortController();
    $("planner-loading").hidden = false;
    $("planner-content").setAttribute("aria-busy", "true");
    $("planner-content").classList.add("is-loading");
    $("render-content").classList.add("is-loading");
    setFeedback("");
    try {
      const response = await fetch(
        "getAlteracao.php" +
          (state.week ? "?semana=" + encodeURIComponent(state.week) : ""),
        {
          signal: state.controller.signal,
          headers: { Accept: "application/json" },
        },
      );
      const data = await response.json();
      if (!response.ok || !data.success)
        throw new Error(
          data.message || "Não foi possível carregar as alterações.",
        );
      if (request !== state.request) return;
      state.data = data;
      state.week = data.semana.inicio;
      state.alignedWeek = "";
      fillFilters();
      render();
    } catch (error) {
      if (error.name !== "AbortError" && request === state.request) {
        state.data = null;
        $("planner-content").hidden = true;
        $("render-content").hidden = true;
        $("resultsCount").textContent = "0";
        setFeedback(error.message, true);
      }
    } finally {
      if (request === state.request) {
        $("planner-loading").hidden = true;
        $("planner-content").setAttribute("aria-busy", "false");
        $("planner-content").classList.remove("is-loading");
        $("render-content").classList.remove("is-loading");
      }
    }
  }

  function init(open) {
    state.open = open;
    readUrl();
    for (const [key, id] of Object.entries(filterIds))
      $(id).addEventListener(key === "busca" ? "input" : "change", () => {
        state.filters[key] = $(id).value;
        render();
      });
    $("ocultar-concluidas").addEventListener("change", (event) => {
      state.hideDone = event.target.checked;
      render();
    });
    $("btn-limpar-filtros").addEventListener("click", () => {
      for (const key of Object.keys(filterIds)) {
        state.filters[key] = "";
        $(filterIds[key]).value = "";
      }
      state.hideDone = false;
      $("ocultar-concluidas").checked = false;
      render();
    });
    $("semana-anterior").addEventListener("click", () => {
      if (state.data) {
        state.week = state.data.semana.anterior;
        state.day = "";
        reload();
      }
    });
    $("semana-proxima").addEventListener("click", () => {
      if (state.data) {
        state.week = state.data.semana.proxima;
        state.day = "";
        reload();
      }
    });
    $("semana-atual").addEventListener("click", () => {
      state.week = "";
      state.day = "";
      reload();
    });
    $("filtro-semana").addEventListener("change", (event) => {
      if (event.target.value) {
        state.week = event.target.value;
        state.day = "";
        reload();
      }
    });
    for (const view of ["alteracoes", "render"])
      $("tab-" + view).addEventListener("click", () => {
        state.view = view;
        render();
      });
    document
      .querySelector(".planner-tabs")
      .addEventListener("keydown", (event) => {
        if (["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)) {
          event.preventDefault();
          state.view =
            event.key === "Home"
              ? "alteracoes"
              : event.key === "End"
                ? "render"
                : state.view === "render"
                  ? "alteracoes"
                  : "render";
          render();
          $("tab-" + state.view).focus();
        }
      });
    $("toggle-unplanned").addEventListener("click", () => {
      state.backlog = !state.backlog;
      render();
    });
    $("close-unplanned").addEventListener("click", () => {
      state.backlog = false;
      render();
      $("toggle-unplanned").focus();
    });
    for (const [id, delta] of [
      ["dia-anterior", -1],
      ["dia-proximo", 1],
    ])
      $(id).addEventListener("click", () => {
        const days = state.data.semana.dias;
        const next = days.findIndex((d) => d.data === state.day) + delta;
        if (days[next]) {
          state.day = days[next].data;
          render();
        }
      });
    window.addEventListener("popstate", () => {
      readUrl();
      reload();
    });
    window.addEventListener("storage", (event) => {
      if (event.key === "theme") {
        if (["light", "dark"].includes(event.newValue))
          document.documentElement.dataset.theme = event.newValue;
        else delete document.documentElement.dataset.theme;
      }
    });
    window.thinkingOrbs?.($("planner-loading"));
    reload();
  }
  window.FlowAlteracoesPlanner = { init, reload };
})();
