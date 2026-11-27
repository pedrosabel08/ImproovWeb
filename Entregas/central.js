(function () {
  "use strict";
  document.addEventListener("DOMContentLoaded", () => {
    const root = document.getElementById("deliveryCentral");
    if (!root) return; // The original details script is also used by Dashboard/obra.
    const M = window.FlowDeliveryModel;
    const UI = window.FlowDeliveryCardUI;
    const $ = (id) => document.getElementById(id);
    const esc = (value) =>
      String(value ?? "").replace(
        /[&<>"']/g,
        (ch) =>
          ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;",
          })[ch],
      );
    const today = () => getTodayLocalDate();
    const labelDate = (key) =>
      M.date(key)
        ? new Intl.DateTimeFormat("pt-BR", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
          }).format(new Date(key + "T12:00:00"))
        : "Sem prazo";
    const keyFor = (date) =>
      `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
    const storeKey = "flow.delivery.central." + root.dataset.user;
    const read = (storage, key) => {
      try {
        return JSON.parse(storage.getItem(key) || "null");
      } catch (_) {
        return null;
      }
    };
    const saved = read(sessionStorage, storeKey) || {};
    const storedView = read(localStorage, storeKey + ".view");
    const state = {
      obra: "",
      stage: "",
      search: "",
      quick: "all",
      day: "",
      sort: "deadline",
      ...saved,
      view: ["operational", "kanban", "calendar"].includes(storedView)
        ? storedView
        : "operational",
    };
    if (
      !["all", "late", "today", "tomorrow", "week", "ready"].includes(
        state.quick,
      )
    )
      state.quick = "all";
    let all = [],
      selected = new Set(),
      upcomingStart = today(),
      calendarAnchor = today(),
      calendarMode = "week",
      kpis;
    let deliverySession = null;
    let sequencing = false;
    let deferredData = null;
    const collapsed = { attention: false, pending: false, sent: true };
    let destroyOrb;
    const persist = () => {
      try {
        sessionStorage.setItem(
          storeKey,
          JSON.stringify({
            obra: state.obra,
            stage: state.stage,
            search: state.search,
            quick: state.quick,
            day: state.day,
            sort: state.sort,
          }),
        );
        localStorage.setItem(storeKey + ".view", JSON.stringify(state.view));
      } catch (_) {}
    };
    const situation = (e) =>
      M.hold(e) ? "hold" : e.kanban_status || "pendente";
    const situations = {
      atrasada: "Atrasada",
      pendente: "A entregar",
      parcial: "Em andamento",
      concluida: "Enviada / Aguardando",
      hold: "HOLD",
    };
    function remaining(e) {
      if (M.hold(e)) return "Pausada";
      if (e.kanban_status === "concluida") return "Entrega realizada";
      const due = M.date(e.data_prevista);
      if (!due) return "Sem prazo definido";
      const days = M.daysBetween(today(), due);
      return days < 0
        ? `${Math.abs(days)} ${days === -1 ? "dia" : "dias"} de atraso`
        : days === 0
          ? "Entrega hoje"
          : days === 1
            ? "Falta 1 dia"
            : `Faltam ${days} dias`;
    }
    function card(e, variant) {
      const el = document.createElement("div");
      const stage = String(e.nome_etapa || "")
        .replace(/[^\w-]/g, "")
        .toUpperCase();
      const progress = M.progress(e);
      const pct = progress.pct;
      el.className = `card-entrega central-delivery central-${variant} status-${stage}`;
      el.dataset.situation = situation(e);
      Object.assign(el.dataset, {
        id: String(e.id),
        kanbanStatus: e.kanban_status || "",
        arquivada: e.arquivada ? "1" : "0",
        canUnarchive: e.can_unarchive ? "1" : "0",
        dataPrevista: e.data_prevista || "",
        statusId: e.status_id || "0",
        emHold: M.hold(e) ? "1" : "0",
        motivoHold: e.motivo_hold || "",
        observacoes: e.observacoes || "",
      });
      el.setAttribute("role", "button");
      el.tabIndex = 0;
      el.classList.toggle("card-selecionado", selected.has(Number(e.id)));
      const readyCount = progress.ready;
      const pendingCount = progress.pending;
      const imageLabel = (count) => (count === 1 ? "imagem" : "imagens");
      el.setAttribute(
        "aria-label",
        `${e.nomenclatura || "Entrega"} - ${e.nome_etapa || ""}, ${situations[situation(e)]}, ${labelDate(e.data_prevista)}, ${pct}% entregue, ${pendingCount} ${imageLabel(pendingCount)} a entregar, ${readyCount} ${readyCount === 1 ? "pronta" : "prontas"} para envio`,
      );
      const ready = UI.readyIndicator(progress, variant === "row");
      const reviews =
        Number(e.review_batches_overdue) > 0
          ? `<span class="central-indicator central-warning" title="${Number(e.review_batches_overdue)} lotes de review vencidos"><i class="fa-regular fa-bell" aria-hidden="true"></i> ${Number(e.review_batches_overdue)}</span>`
          : "";
      const pending =
        Number(e.review_batches_pending || 0) +
        Number(e.review_batches_snoozed || 0);
      const otherReviews = pending
        ? `<span class="central-indicator" title="${pending} lotes de review pendentes ou adiados"><i class="fa-regular fa-comment" aria-hidden="true"></i> ${pending}</span>`
        : "";
      const note = e.observacoes
        ? `<span class="central-note" title="${esc(e.observacoes)}" aria-label="${esc(e.observacoes)}"><i class="fa-regular fa-note-sticky" aria-hidden="true"></i></span>`
        : "";
      const isKanban = variant === "tile" && state.view === "kanban";
      const statusBadge = isKanban
        ? ""
        : `<span class="central-status" title="${esc(e.motivo_hold || "")}">${esc(situations[situation(e)] || "A entregar")}</span>`;
      const identity = `<div class="central-identity"><h4 title="${esc(`${e.nomenclatura || 'Entrega'} – ${e.nome_etapa || ''}`)}"><span class="central-work-code">${esc(e.nomenclatura || "Entrega")}</span> <span class="central-stage">– ${esc(e.nome_etapa || "")}</span></h4><small>${variant === "event" ? `Prazo: ${labelDate(e.data_prevista)}` : `Entrega #${Number(e.id)}`}</small></div>`;
      const notifications = `${reviews}${otherReviews}${note}`;
      const header = variant === "tile"
        ? `<div class="central-card-header">${identity}<div class="central-header-actions">${ready}<div class="central-indicators">${notifications}</div></div></div>`
        : identity;
      el.innerHTML = `<span class="card-checkbox${selected.has(Number(e.id)) ? " checked" : ""}"></span>${header}<div class="central-situation">${statusBadge}<small title="${esc(e.motivo_hold || "")}">${variant === "tile" && M.hold(e) ? "Em HOLD" : remaining(e)}</small></div><div class="central-deadline"><i class="fa-regular fa-calendar" aria-hidden="true"></i><time datetime="${esc(M.date(e.data_prevista))}">${labelDate(e.data_prevista)}</time></div><div class="central-progress"><div><small>${progress.delivered}/${progress.total} imagens entregues</small><strong>${pct.toLocaleString("pt-BR")}%</strong></div>${UI.progressMarkup(progress)}</div>${variant === "tile" ? "" : `<div class="central-indicators">${ready}${notifications}</div>`}<i class="central-chevron fa-solid fa-chevron-right" aria-hidden="true"></i>`;
      if (variant === "event") {
        el.title = `${e.nomenclatura} – ${e.nome_etapa}\nRecebimento: ${labelDate(e.data_recebimento)}\nPrazo: ${labelDate(e.data_prevista)}\n${pct}% entregue · ${remaining(e)}`;
      }
      return el;
    }
    function empty(text) {
      const el = document.createElement("p");
      el.className = "central-empty";
      el.textContent = text;
      return el;
    }
    function operational(list) {
      const target = $("centralOperational");
      target.replaceChildren();
      const titles = {
        attention: "Requer atenção",
        pending: "A entregar",
        sent: "Enviadas / Aguardando",
      };
      for (const key of Object.keys(titles)) {
        const entries = list.filter((e) => M.group(e) === key);
        const section = document.createElement("section");
        section.className = "central-group";
        section.dataset.group = key;
        const toggle = document.createElement("button");
        toggle.type = "button";
        toggle.className = "central-group-toggle";
        toggle.setAttribute("aria-expanded", String(!collapsed[key]));
        toggle.setAttribute("aria-controls", "centralGroup-" + key);
        toggle.innerHTML = `<i class="fa-solid fa-chevron-${collapsed[key] ? "right" : "down"}" aria-hidden="true"></i> ${titles[key]} <span>${entries.length}</span>`;
        const body = document.createElement("div");
        body.id = "centralGroup-" + key;
        body.hidden = collapsed[key];
        toggle.addEventListener("click", () => {
          collapsed[key] = !collapsed[key];
          body.hidden = collapsed[key];
          toggle.setAttribute("aria-expanded", String(!collapsed[key]));
          toggle.querySelector("i").className =
            `fa-solid fa-chevron-${collapsed[key] ? "right" : "down"}`;
          if (!collapsed[key]) animate(body);
        });
        if (entries.length) {
          const head = document.createElement("div");
          head.className = "central-list-head";
          head.innerHTML =
            "<span>Entrega</span><span>Situação</span><span>Prazo</span><span>Progresso</span><span>Para envio / avisos</span>";
          body.append(head);
          entries.forEach((e) => body.append(card(e, "row")));
        } else body.append(empty("Nenhuma entrega neste grupo."));
        section.append(toggle, body);
        target.append(section);
      }
    }
    function kanban(list) {
      root.querySelectorAll("#kanban .column").forEach((col) => {
        const statuses = col.dataset.status.split(",");
        const entries = list.filter((e) => statuses.includes(e.kanban_status));
        const target = col.querySelector(".column-cards");
        target.replaceChildren();
        col.querySelector(".column-count").textContent = entries.length;
        entries.forEach((e) => target.append(card(e, "tile")));
        if (!entries.length) target.append(empty("Nenhuma entrega."));
      });
    }
    function upcoming(base) {
      $("upcomingPeriod").textContent =
        `${labelDate(upcomingStart).slice(0, 5)} – ${labelDate(M.addDays(upcomingStart, 6)).slice(0, 5)}`;
      const days = $("centralDays");
      days.replaceChildren();
      for (let i = 0; i < 7; i++) {
        const key = M.addDays(upcomingStart, i);
        const count = base.filter(
          (e) => M.open(e) && M.date(e.data_prevista) === key,
        ).length;
        const date = new Date(key + "T12:00:00");
        const button = document.createElement("button");
        button.type = "button";
        button.dataset.day = key;
        button.classList.toggle("is-today", key === today());
        button.classList.toggle("has-deliveries", count > 0);
        button.setAttribute("aria-pressed", String(state.day === key));
        button.setAttribute(
          "aria-label",
          `${labelDate(key)}, ${count} entregas previstas${state.day === key ? ", remover filtro" : ""}`,
        );
        button.innerHTML = `<span>${new Intl.DateTimeFormat("pt-BR", { weekday: "short" }).format(date).replace(".", "")}</span><strong>${date.getDate().toString().padStart(2, "0")}</strong><small>${count ? `${count}<span class="central-day-delivery-label"> ${count === 1 ? "entrega" : "entregas"}</span>` : "—"}</small>`;
        button.addEventListener("click", () => {
          state.day = state.day === key ? "" : key;
          state.quick = "all";
          render();
        });
        days.append(button);
      }
    }
    function calendar(list) {
      const target = $("centralCalendarGrid");
      target.replaceChildren();
      const d = new Date(calendarAnchor + "T12:00:00");
      let start, end;
      if (calendarMode === "week") {
        start = M.addDays(calendarAnchor, -((d.getDay() + 6) % 7));
        end = M.addDays(start, 6);
        $("centralCalendarTitle").textContent =
          `${labelDate(start)} – ${labelDate(end)}`;
      } else {
        start = keyFor(new Date(d.getFullYear(), d.getMonth(), 1));
        end = keyFor(new Date(d.getFullYear(), d.getMonth() + 1, 0));
        $("centralCalendarTitle").textContent = new Intl.DateTimeFormat(
          "pt-BR",
          { month: "long", year: "numeric" },
        ).format(d);
      }
      root
        .querySelectorAll("[data-calendar-mode]")
        .forEach((b) =>
          b.setAttribute(
            "aria-pressed",
            String(b.dataset.calendarMode === calendarMode),
          ),
        );
      if (calendarMode === "week") {
        const grid = document.createElement("div");
        grid.className = "central-week";
        const head = document.createElement("div");
        head.className = "central-week-head";
        head.innerHTML = "<span>Obra</span>";
        for (let i = 0; i < 7; i++) {
          const day = M.addDays(start, i);
          const cell = document.createElement("span");
          cell.classList.toggle("is-today", day === today());
          cell.textContent = new Intl.DateTimeFormat("pt-BR", {
            weekday: "short",
            day: "2-digit",
            month: "2-digit",
          }).format(new Date(day + "T12:00:00"));
          head.append(cell);
        }
        grid.append(head);
        const groups = new Map();
        for (const e of list) {
          const span = M.span(e, start, end);
          if (span) {
            const key = String(e.obra_id || e.nomenclatura);
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push({ e, span });
          }
        }
        for (const entries of groups.values()) {
          const lane = document.createElement("div");
          lane.className = "central-week-lane";
          const name = document.createElement("strong");
          name.className = "central-week-name";
          name.textContent = entries[0].e.nomenclatura;
          name.style.gridRow = `1 / span ${entries.length}`;
          lane.append(name);
          entries.forEach(({ e, span }, i) => {
            const event = card(e, "event");
            event.style.gridColumn = `${M.daysBetween(start, span.from) + 2} / ${M.daysBetween(start, span.to) + 3}`;
            event.style.gridRow = String(i + 1);
            lane.append(event);
          });
          grid.append(lane);
        }
        target.append(grid);
        if (!groups.size)
          target.append(
            empty(
              "Nenhuma entrega neste período. Navegue pelas semanas ou volte para hoje.",
            ),
          );
      } else {
        const month = document.createElement("div");
        month.className = "central-month";
        ["Seg", "Ter", "Qua", "Qui", "Sex", "Sáb", "Dom"].forEach((day) => {
          const label = document.createElement("strong");
          label.className = "central-month-heading";
          label.textContent = day;
          month.append(label);
        });
        const gridStart = M.addDays(
          start,
          -((new Date(start + "T12:00:00").getDay() + 6) % 7),
        );
        const length = Math.ceil((M.daysBetween(gridStart, end) + 1) / 7) * 7;
        for (let i = 0; i < length; i++) {
          const key = M.addDays(gridStart, i),
            cell = document.createElement("div");
          cell.className = "central-month-day";
          cell.classList.toggle("outside-month", key < start || key > end);
          cell.classList.toggle("is-today", key === today());
          const time = document.createElement("time");
          time.dateTime = key;
          time.textContent = String(Number(key.slice(-2)));
          cell.append(time);
          list
            .filter((e) => M.date(e.data_prevista) === key)
            .forEach((e) => cell.append(card(e, "event")));
          month.append(cell);
        }
        target.append(month);
      }
      const undated = list.filter((e) => !M.date(e.data_prevista));
      if (undated.length) {
        const section = document.createElement("section");
        section.className = "central-undated";
        const h = document.createElement("h3");
        h.textContent = `Sem prazo definido (${undated.length})`;
        section.append(h);
        undated.forEach((e) => section.append(card(e, "tile")));
        target.append(section);
      }
    }
    function animate(target) {
      if (!matchMedia("(prefers-reduced-motion: reduce)").matches)
        target.animate([{ opacity: 0.45 }, { opacity: 1 }], {
          duration: 180,
          easing: "ease-out",
        });
    }
    function render(data) {
      if (deliverySession || sequencing) {
        if (data) deferredData = data;
        return;
      }
      UI.hideTooltip();
      if (data) all = data;
      const base = M.baseFilter(all, state),
        list = M.sort(M.filter(all, state, today()), state.sort);
      $("centralUpcoming").hidden = state.view === "calendar";
      $("centralOperational").hidden = state.view !== "operational";
      $("centralKanban").hidden = state.view !== "kanban";
      $("centralCalendar").hidden = state.view !== "calendar";
      root.dataset.view = state.view;
      root
        .querySelectorAll("[data-central-view]")
        .forEach((b) =>
          b.setAttribute(
            "aria-pressed",
            String(b.dataset.centralView === state.view),
          ),
        );
      root.querySelectorAll("[data-quick]").forEach((b) => {
        b.setAttribute("aria-pressed", String(b.dataset.quick === state.quick));
        if (b.dataset.quick === "ready") {
          const summary = M.readySummary(base);
          b.querySelector("span").textContent =
            `${summary.images} ${summary.images === 1 ? "imagem" : "imagens"}`;
          b.querySelector("small").textContent =
            `em ${summary.deliveries} ${summary.deliveries === 1 ? "entrega" : "entregas"}`;
        } else
          b.querySelector("span").textContent = base.filter((e) =>
            M.quick(e, b.dataset.quick, today()),
          ).length;
      });
      $("centralDaySelection").hidden = !state.day;
      $("centralDaySelection").textContent = state.day
        ? `Prazo: ${labelDate(state.day)}`
        : "";
      const visibleReady = M.readySummary(list);
      $("centralResultCount").textContent =
        `${list.length} de ${all.length} entregas · ${visibleReady.images} ${visibleReady.images === 1 ? "imagem pronta" : "imagens prontas"} para envio`;
      $("btnLimparFiltros").style.display =
        state.obra ||
        state.stage ||
        state.search ||
        state.day ||
        state.quick !== "all"
          ? "inline-flex"
          : "none";
      $("centralArchiveNotice").hidden = !window._modoArquivadas;
      for (const [id, value] of [
        ["filterObra", state.obra],
        ["filterStatus", state.stage],
      ]) {
        const select = $(id);
        if (value && ![...select.options].some((o) => o.value === value)) {
          const opt = new Option(
            id === "filterObra" ? "Obra selecionada (sem entregas)" : value,
            value,
          );
          select.add(opt);
        }
        select.value = value;
      }
      upcoming(base);
      // Build only the visible view: hidden duplicates must not affect planning or context menus.
      $("centralOperational").replaceChildren();
      root
        .querySelectorAll("#kanban .column-cards")
        .forEach((c) => c.replaceChildren());
      $("centralCalendarGrid").replaceChildren();
      if (state.view === "operational") operational(list);
      else if (state.view === "kanban") kanban(list);
      else calendar(list);
      updateCalendarScrollHint();
      persist();
      animate($("centralWorkspace"));
    }
    function clear() {
      Object.assign(state, {
        obra: "",
        stage: "",
        search: "",
        day: "",
        quick: "all",
      });
      $("centralSearch").value = "";
      render();
    }
    $("centralToday").textContent = new Intl.DateTimeFormat("pt-BR", {
      weekday: "long",
      day: "numeric",
      month: "long",
    }).format(new Date());
    $("centralToday").dateTime = today();
    // Keep the legacy controls and IDs, positioned in the compact shared toolbar.
    root.insertBefore(
      $("centralFilters"),
      root.querySelector(".central-quick-filters"),
    );
    $("centralSearch").value = state.search;
    $("centralSort").value = state.sort;
    for (const [id, key] of [
      ["filterObra", "obra"],
      ["filterStatus", "stage"],
      ["centralSort", "sort"],
    ])
      $(id).addEventListener("change", () => {
        state[key] = $(id).value;
        render();
      });
    $("centralSearch").addEventListener("input", () => {
      state.search = $("centralSearch").value;
      render();
    });
    root.querySelectorAll("[data-central-view]").forEach((b) =>
      b.addEventListener("click", () => {
        state.view = b.dataset.centralView;
        render();
      }),
    );
    root.querySelectorAll("[data-quick]").forEach((b) =>
      b.addEventListener("click", () => {
        state.quick = b.dataset.quick;
        state.day = "";
        render();
      }),
    );
    root.querySelectorAll("[data-upcoming-shift]").forEach((b) =>
      b.addEventListener("click", () => {
        upcomingStart = M.addDays(
          upcomingStart,
          Number(b.dataset.upcomingShift),
        );
        render();
      }),
    );
    $("upcomingToday").addEventListener("click", () => {
      upcomingStart = today();
      render();
    });
    root.querySelectorAll("[data-calendar-mode]").forEach((b) =>
      b.addEventListener("click", () => {
        calendarMode = b.dataset.calendarMode;
        render();
      }),
    );
    root.querySelectorAll("[data-calendar-shift]").forEach((b) =>
      b.addEventListener("click", () => {
        const shift = Number(b.dataset.calendarShift);
        calendarAnchor =
          calendarMode === "week"
            ? M.addDays(calendarAnchor, 7 * shift)
            : keyFor(
                new Date(
                  Number(calendarAnchor.slice(0, 4)),
                  Number(calendarAnchor.slice(5, 7)) - 1 + shift,
                  1,
                ),
              );
        render();
      }),
    );
    $("calendarToday").addEventListener("click", () => {
      calendarAnchor = today();
      render();
    });
    root.addEventListener("keydown", (event) => {
      const target = event.target.closest(".central-delivery");
      if (target && ["Enter", " "].includes(event.key)) {
        event.preventDefault();
        target.click();
      }
    });
    $("centralRetry").addEventListener("click", () =>
      window.carregarKanban?.(),
    );
    function updateCalendarScrollHint() {
      const grid = $("centralCalendarGrid");
      $("centralCalendarScrollHint").hidden =
        state.view !== "calendar" || grid.scrollWidth <= grid.clientWidth;
    }
    new ResizeObserver(updateCalendarScrollHint).observe(
      $("centralCalendarGrid"),
    );
    const descriptions = {
      total:
        "Entregas realizadas no período, com base na data de conclusão e no status registrado. Inclui o histórico de obras inativas e entregas arquivadas.",
      no_prazo:
        "Entregas registradas como “Entregue no prazo” e concluídas no período.",
      com_atraso:
        "Entregas concluídas no período e registradas como “Entregue com atraso”. Este indicador é diferente das entregas abertas atualmente atrasadas.",
      antecipadas:
        "Entregas registradas como “Entrega antecipada” e concluídas no período.",
      pontualidade:
        "Percentual de entregas no prazo ou antecipadas sobre todas as entregas realizadas no período. A tendência mostra o SLA acumulado.",
    };
    const dialog = $("centralKpiDetail");
    $("closeCentralKpi").addEventListener("click", () => dialog.close());
    dialog.addEventListener("click", (e) => {
      if (
        e.target === dialog &&
        (e.clientX < dialog.getBoundingClientRect().left ||
          e.clientX > dialog.getBoundingClientRect().right ||
          e.clientY < dialog.getBoundingClientRect().top ||
          e.clientY > dialog.getBoundingClientRect().bottom)
      )
        dialog.close();
    });
    root.querySelectorAll("[data-kpi-card]").forEach((c) => {
      c.tabIndex = 0;
      c.setAttribute("role", "button");
      c.setAttribute("aria-haspopup", "dialog");
      c.title = "Consultar indicador e comparação";
      const show = () => {
        const key = c.dataset.kpiCard,
          metric = kpis?.metrics?.[key];
        if (!metric) return;
        $("centralKpiTitle").textContent =
          c.querySelector(".kpi-label").textContent;
        $("centralKpiDescription").textContent = descriptions[key];
        const unit = key === "pontualidade" ? "%" : "";
        $("centralKpiValues").textContent =
          `Atual: ${Number(metric.current).toLocaleString("pt-BR")}${unit} · Anterior: ${Number(metric.previous).toLocaleString("pt-BR")}${unit}`;
        $("centralKpiDates").textContent =
          `${labelDate(kpis.period.current.from)} a ${labelDate(kpis.period.current.to)} · Comparação: ${labelDate(kpis.period.previous.from)} a ${labelDate(kpis.period.previous.to)}`;
        const daily = $("centralKpiDaily");
        daily.replaceChildren();
        (metric.series || []).forEach((value, i) => {
          const p = document.createElement("p");
          p.textContent = `${labelDate(M.addDays(kpis.period.current.from, i))}: ${Number(value).toLocaleString("pt-BR")}${unit}`;
          daily.append(p);
        });
        dialog.showModal();
      };
      c.addEventListener("click", show);
      c.addEventListener("keydown", (e) => {
        if (["Enter", " "].includes(e.key)) {
          e.preventDefault();
          show();
        }
      });
    });
    window.FlowDeliveryCentral = {
      render,
      clear,
      card,
      beginDelivery(id) {
        const item = all.find(e => String(e.id) === String(id));
        if (item) deliverySession = {id:String(id), item:{...item}, progress:M.progress(item), list:item.kanban_status, registered:false};
      },
      registeredDelivery() { if (deliverySession) deliverySession.registered = true; },
      async finishDelivery(refresh) {
        if (!deliverySession) { await refresh(); return; }
        const session = deliverySession;
        sequencing = true;
        try {
          await refresh();
          const latest = deferredData || all;
          const next = latest.find(e => String(e.id) === session.id);
          const before = session.progress;
          const after = next && M.progress(next);
          const el = root.querySelector(`.central-delivery[data-id="${CSS.escape(session.id)}"]`);
          const changed = session.registered && after && after.delivered > before.delivered;
          const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
          if (changed && el && !reduced) {
            await new Promise(resolve => {
              const data = {delivered:before.delivered, ready:before.ready, other:before.other, pct:before.pct};
              const bar = el.querySelector('.delivery-progress');
              const counter = el.querySelector('.central-progress small');
              const percentage = el.querySelector('.central-progress strong');
              const target = {delivered:after.delivered, ready:after.ready, other:after.other, pct:after.pct};
              const segments = bar.querySelectorAll('.delivery-progress-segment');
              segments.forEach(segment => segment.style.transition = 'none');
              const start = performance.now() + 150;
              function frame(now) {
                const fraction = Math.min(1, Math.max(0, (now - start) / 700));
                const eased = fraction < .5 ? 2*fraction*fraction : 1-Math.pow(-2*fraction+2,2)/2;
                for (const key of Object.keys(target)) data[key] = before[key] + (target[key] - before[key])*eased;
                const total = after.total || 1;
                for (const [name,key] of [['delivered','delivered'],['ready','ready'],['other','other']]) {
                  const segment = bar.querySelector(`.is-${name}`);
                  if (segment) segment.style.width = `${Math.max(0,data[key])/total*100}%`;
                }
                if (counter) counter.textContent = `${Math.round(data.delivered)}/${after.total} imagens entregues`;
                if (percentage) percentage.textContent = `${(Math.round(data.pct*10)/10).toLocaleString('pt-BR')}%`;
                const badge = el.querySelector('.delivery-ready-indicator');
                if (badge) { badge.querySelector('strong').textContent = Math.round(data.ready); badge.style.visibility = Math.round(data.ready) ? '' : 'hidden'; }
                if (fraction < 1) requestAnimationFrame(frame);
                else { segments.forEach(segment => segment.style.removeProperty('transition')); resolve(); }
              }
              requestAnimationFrame(frame);
            });
            if (after.delivered === after.total && after.total > 0 && before.delivered < before.total) {
              const notice = document.createElement('span');
              notice.className = 'central-completed-notice';
              notice.innerHTML = '<i class="fa-solid fa-circle-check" aria-hidden="true"></i> Entrega concluída';
              el.append(notice);
              notice.style.opacity = '1';
              await new Promise(resolve => setTimeout(resolve, 500));
              await el.animate([{opacity:1,transform:'translateX(0)'},{opacity:0,transform:'translateX(12px)'}],{duration:200,easing:'ease-out',fill:'forwards'}).finished;
            }
          }
          const first = new Map([...root.querySelectorAll('.central-delivery')].map(node => [node.dataset.id,node.getBoundingClientRect()]));
          deliverySession = null;
          sequencing = false;
          deferredData = null;
          render(latest);
          if (changed && !reduced) {
            root.querySelectorAll('.central-delivery').forEach(node => {
              const old = first.get(node.dataset.id);
              const rect = node.getBoundingClientRect();
              if (old && node.dataset.id !== session.id && (old.top !== rect.top || old.left !== rect.left))
                node.animate([{transform:`translate(${old.left-rect.left}px, ${old.top-rect.top}px)`},{transform:'translate(0, 0)'}],{duration:250,easing:'ease-out'});
              if (node.dataset.id === session.id && session.list !== next.kanban_status && rect.bottom > 0 && rect.top < innerHeight && rect.right > 0 && rect.left < innerWidth && node.getClientRects().length)
                node.animate([{opacity:0,transform:'translateY(8px)'},{opacity:1,transform:'translateY(0)'}],{duration:250,easing:'ease-out'});
            });
          }
        } finally {
          if (deliverySession === session) { deliverySession = null; sequencing = false; const pending = deferredData; deferredData = null; if (pending) render(pending); }
        }
      },
      setSelected(ids) {
        selected = new Set(ids);
        root.querySelectorAll(".central-delivery").forEach((c) => {
          const checked = selected.has(Number(c.dataset.id));
          c.classList.toggle("card-selecionado", checked);
          c.querySelector(".card-checkbox")?.classList.toggle(
            "checked",
            checked,
          );
        });
      },
      setKpis(data) {
        kpis = data;
        $("entregasKpiPeriodLabel").textContent =
          `${labelDate(data.period.current.from)} – ${labelDate(data.period.current.to)} · vs. período anterior`;
      },
      loading(on) {
        $("centralWorkspace").setAttribute("aria-busy", String(on));
        if (on) {
          $("centralFeedback").hidden = false;
          $("centralFeedbackText").textContent = "Carregando entregas…";
          $("centralRetry").hidden = true;
          const orb = $("centralFeedback").querySelector("canvas");
          orb.hidden = false;
          destroyOrb?.();
          destroyOrb = window.thinkingOrbs?.(orb, { size: 28 });
        } else {
          destroyOrb?.();
          destroyOrb = null;
          if ($("centralRetry").hidden) $("centralFeedback").hidden = true;
        }
      },
      error() {
        $("centralFeedback").hidden = false;
        $("centralFeedbackText").textContent =
          "Não foi possível atualizar as entregas. Tente novamente.";
        $("centralFeedback").querySelector("canvas").hidden = true;
        $("centralRetry").hidden = false;
      },
    };
    render();
  });
})();
