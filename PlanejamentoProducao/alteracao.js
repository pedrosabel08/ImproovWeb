(() => {
  "use strict";
  const $ = (id) => document.getElementById(id);
  if (!$("alteration-plan")) return;
  const entregaId = Number(document.body.dataset.entregaId);
  const state = {
    plan: null,
    items: [],
    selected: new Set(),
    dirty: false,
    saving: false,
    suggesting: false,
    replanning: false,
    conflict: false,
    people: new Set(),
    typeRules: {},
    typePriority: [],
    priorityEnabled: false,
    dayIndex: 0,
    editorId: null,
    selectionAnchor: null,
    railStatus: "",
    saveError: false,
  };
  const escape = (value) =>
    String(value ?? "").replace(
      /[&<>"']/g,
      (char) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[char],
    );
  const date = (value) => String(value || "").slice(0, 10);
  const today = () =>
    new Date().toLocaleDateString("sv-SE", {
      timeZone: "America/Sao_Paulo",
    });
  const formatDate = (value) =>
    /^\d{4}-\d{2}-\d{2}$/.test(date(value))
      ? date(value).split("-").reverse().join("/")
      : "—";
  const level = (item) =>
    String(item.nivel_complexidade_snapshot || "").replace(/^N/i, "");
  const validDate = (value) =>
    /^\d{4}-\d{2}-\d{2}$/.test(value || "") &&
    !Number.isNaN(Date.parse(value + "T12:00:00")) &&
    new Date(value + "T12:00:00").toISOString().slice(0, 10) === value;
  const validPerson = (id) =>
    Number(id) > 0 &&
    state.plan.colaboradores.some((person) => Number(person.id) === Number(id));
  const hasResponsible = (item) =>
    confirmed() && !state.replanning
      ? Number(item.responsavel_planejado_id) > 0
      : validPerson(item.responsavel_planejado_id) || keepsResponsible(item);
  const keepsResponsible = (item) =>
    Number(item.responsavel_preservado_id) > 0 &&
    Number(item.responsavel_preservado_id) ===
      Number(item.responsavel_planejado_id);
  const complete = (item) =>
    hasResponsible(item) &&
    validDate(item.fim_planejado) &&
    ((confirmed() && !state.replanning) ||
      (Number(item.pontos_snapshot) > 0 &&
        (keepsResponsible(item) ||
          Boolean(
            state.plan.capacidade?.agenda?.[item.responsavel_planejado_id]?.[
              item.fim_planejado
            ],
          ))));
  const confirmed = () => Boolean(state.plan?.planejamento?.versao_atual_id);
  const editable = () =>
    !!state.plan &&
    state.plan.pode_editar !== false &&
    (!confirmed() || state.replanning) &&
    !state.saving &&
    !state.conflict;
  const pendingWork = (item) => item.pode_editar_datas !== false;
  const canAssign = (item) => item.pode_editar_responsavel !== false;
  const pendingItems = () => state.items.filter(pendingWork);
  const suggestibleItems = () => pendingItems().filter(canAssign);
  const personName = (id) =>
    state.plan.colaboradores.find((person) => Number(person.id) === Number(id))
      ?.nome ||
    state.items.find(
      (item) =>
        Number(item.responsavel_planejado_id) === Number(id) &&
        item.responsavel_planejado_nome,
    )?.responsavel_planejado_nome ||
    (id ? "Responsável indisponível" : "Sem responsável");
  const roleName = (p) =>
    p.tipo_atuacao === "PRINCIPAL" ? "Principal" : "Secundária";
  const personOptions = (selected, placeholder = "Selecione") =>
    `<option value="">${placeholder}</option>` +
    (selected && !validPerson(selected)
      ? `<option value="${Number(selected)}" selected disabled>${escape(personName(selected))} (indisponível)</option>`
      : "") +
    ["PRINCIPAL", "SECUNDARIA"]
      .map(
        (role) =>
          `<optgroup label="Alteração ${role === "PRINCIPAL" ? "principal" : "secundária"}">${state.plan.colaboradores
            .filter((p) => (p.tipo_atuacao || "SECUNDARIA") === role)
            .map(
              (person) =>
                `<option value="${Number(person.id)}"${Number(selected) === Number(person.id) ? " selected" : ""}>${escape(person.nome)} · ${roleName(person)}</option>`,
            )
            .join("")}</optgroup>`,
      )
      .join("");
  const points = (item) => Number(item.pontos_snapshot) || 0;
  const pointsLabel = (item) =>
    item.pontos_snapshot == null
      ? "Pontos não registrados"
      : `${num(points(item))} pts`;
  const num = (n) =>
    Number(n || 0).toLocaleString("pt-BR", { maximumFractionDigits: 2 });
  const days = () => state.plan.capacidade?.dias || [];
  const todayIndex = () => {
    const current = today();
    const index = days().findIndex((day) => day >= current);
    return index < 0 ? Math.max(0, days().length - 5) : index;
  };
  const capacity = (id, d) => state.plan.capacidade?.agenda?.[id]?.[d];
  const selectionItems = () =>
    state.items.filter((item) =>
      state.selected.has(Number(item.entrega_item_id)),
    );
  const cellKey = (id, d) => `${id}|${d}`;
  const canMove = (item) => pendingWork(item) && !item.fixado;
  const changePlacement = (item, person, day) =>
    Number(item.responsavel_planejado_id || 0) !== Number(person || 0) ||
    item.fim_planejado !== day;
  function cellLoad(id, d) {
    return state.items
      .filter(
        (i) =>
          pendingWork(i) &&
          Number(i.responsavel_planejado_id) === Number(id) &&
          i.fim_planejado === d,
      )
      .reduce((sum, i) => sum + points(i), 0);
  }
  function excessCells(items = state.items) {
    const found = [];
    const loads = new Map();
    for (const item of items) {
      if (
        !pendingWork(item) ||
        !item.responsavel_planejado_id ||
        !item.fim_planejado
      )
        continue;
      const key = cellKey(item.responsavel_planejado_id, item.fim_planejado);
      loads.set(key, (loads.get(key) || 0) + points(item));
    }
    for (const [key, load] of loads) {
      const [id, d] = key.split("|"),
        a = capacity(id, d);
      if (!a) continue;
      const excess =
        Math.round(
          (load + Number(a.comprometidos) - Number(a.capacidade)) * 100,
        ) / 100;
      if (excess > 0)
        found.push({
          colaborador_id: Number(id),
          dia: d,
          excesso: excess,
          fingerprint: state.plan.capacidade.fingerprint,
        });
    }
    return found;
  }
  function gridCardPositions() {
    if (
      matchMedia("(prefers-reduced-motion: reduce)").matches ||
      $("ap-calendar-view").hidden
    )
      return null;
    return new Map(
      [...$("ap-grid").querySelectorAll("[data-card-shell]")].map((card) => [
        card.dataset.cardShell,
        card.getBoundingClientRect(),
      ]),
    );
  }
  function animateGridChanges(previous) {
    if (!previous) return;
    $("ap-grid")
      .querySelectorAll("[data-card-shell]")
      .forEach((card) => {
        const old = previous.get(card.dataset.cardShell);
        if (!old) return;
        const next = card.getBoundingClientRect();
        const x = old.left - next.left;
        const y = old.top - next.top;
        if (x || y)
          card.animate(
            [{ transform: `translate(${x}px, ${y}px)` }, { transform: "none" }],
            {
              duration: 150,
              easing: "ease-out",
            },
          );
      });
  }
  async function applyDecisions(decisions) {
    if (!editable()) return false;
    if (
      state.items.some((i) => {
        const change = decisions.get(Number(i.entrega_item_id));
        return (
          change &&
          (!pendingWork(i) ||
            (i.fixado &&
              change.fixado !== false &&
              changePlacement(
                i,
                Object.hasOwn(change, "responsavel_planejado_id")
                  ? change.responsavel_planejado_id
                  : i.responsavel_planejado_id,
                Object.hasOwn(change, "fim_planejado")
                  ? change.fim_planejado
                  : i.fim_planejado,
              )) ||
            (!canAssign(i) &&
              Object.hasOwn(change, "responsavel_planejado_id") &&
              change.responsavel_planejado_id !== i.responsavel_planejado_id))
        );
      })
    ) {
      message(
        "A seleção contém uma imagem bloqueada para essa alteração. Verifique a situação, a fixação e o responsável.",
        true,
      );
      renderRows();
      renderPlanner();
      return false;
    }
    const originalItems = state.items;
    const next = state.items.map((i) => {
      const change = decisions.get(Number(i.entrega_item_id));
      if (!change) return { ...i };
      const nextItem = { ...i, ...change, excecao_capacidade: null };
      if (!canAssign(i))
        nextItem.responsavel_planejado_id = i.responsavel_planejado_id;
      return nextItem;
    });
    if (
      next.some(
        (i) =>
          pendingWork(i) &&
          i.fim_planejado &&
          !days().includes(i.fim_planejado),
      )
    ) {
      message("Escolha um dia útil dentro da agenda disponível.", true);
      renderRows();
      return false;
    }
    if (
      next.some(
        (item) =>
          decisions.has(Number(item.entrega_item_id)) &&
          item.inicio_planejado &&
          item.fim_planejado &&
          item.inicio_planejado > item.fim_planejado,
      )
    ) {
      message("O início não pode ser posterior ao dia planejado.", true);
      return false;
    }
    const current = today();
    if (
      originalItems.some((item) => {
        const day = decisions.get(Number(item.entrega_item_id))?.fim_planejado;
        return day && day < current && day !== item.fim_planejado;
      }) &&
      !(await confirmAction(
        "A data escolhida é anterior a hoje. Aplicar mesmo assim?",
      ))
    )
      return false;
    for (const s of excessCells(next)) {
      const already = next.some(
        (i) =>
          Number(i.responsavel_planejado_id) === s.colaborador_id &&
          i.fim_planejado === s.dia &&
          i.excecao_capacidade?.fingerprint === s.fingerprint &&
          Number(i.excecao_capacidade.excesso) >= s.excesso,
      );
      if (!already) {
        if (
          !(await confirmAction(
            `${personName(s.colaborador_id)} · ${formatDate(s.dia)}: capacidade excedida em ${num(s.excesso)} pts. Planejar mesmo assim?`,
          ))
        ) {
          renderRows();
          renderPlanner();
          return false;
        }
        const item = next.find(
          (i) =>
            pendingWork(i) &&
            Number(i.responsavel_planejado_id) === s.colaborador_id &&
            i.fim_planejado === s.dia,
        );
        item.excecao_capacidade = s;
      }
    }
    if (!editable() || state.items !== originalItems) {
      message(
        "O planejamento mudou enquanto a confirmação estava aberta. Revise e tente novamente.",
        true,
      );
      renderRows();
      renderPlanner();
      return false;
    }
    const previous = gridCardPositions();
    state.items = next;
    renderEditor();
    next.forEach((i) => {
      if (validPerson(i.responsavel_planejado_id))
        state.people.add(Number(i.responsavel_planejado_id));
    });
    markDirty();
    animateGridChanges(previous);
    renderRows();
    return true;
  }
  const thumbnail = (item) =>
    item.thumbnail_url
      ? `<img class="ap-thumb" src="${escape(item.thumbnail_url)}" alt="" loading="lazy" draggable="false">`
      : '<span class="ap-thumb ap-thumb-empty" aria-hidden="true">▧</span>';
  function card(item, showStatus = false) {
    const id = Number(item.entrega_item_id);
    const name = escape(item.imagem_nome);
    const active = state.selected.has(id);
    const status = cardStatus(item);
    const locked = !pendingWork(item) || !canAssign(item) || item.fixado;
    const done = Boolean(
      item.data_entregue || item.motivo_bloqueio === "Alteração concluída",
    );
    const icon = done
      ? "circle-check"
      : locked
        ? "lock"
        : complete(item)
          ? "check"
          : "circle";
    return `<div class="ap-card-shell${active ? " is-active" : ""}" data-card-shell="${id}">
      ${editable() && pendingWork(item) ? `<input type="checkbox" class="ap-card-checkbox" data-select="${id}" aria-label="Selecionar ${name}"${active ? " checked" : ""}>` : ""}
      <button type="button" class="ap-card" data-card="${id}" data-edit-image="${id}" draggable="${editable() && canMove(item)}" aria-pressed="${active}" title="${name} · ${escape(status)}">
        ${thumbnail(item)}<span class="ap-card-copy"><strong>${name}</strong><small>${levelBadge(item)} <span>${pointsLabel(item)}</span>${showStatus ? `<span class="ap-card-id">#${Number(item.imagem_id)}</span>` : ""}</small></span>
        ${showStatus || locked ? `<i class="fa-solid fa-${icon} ap-card-icon${done ? " is-done" : ""}" aria-label="${escape(status)}"></i>` : ""}
      </button></div>`;
  }
  function cardStatus(item) {
    if (item.fixado && pendingWork(item)) return "Fixada no planejamento";
    return (
      item.motivo_bloqueio ||
      item.motivo_bloqueio_responsavel ||
      (item.data_entregue
        ? "Entregue"
        : complete(item)
          ? "Planejada"
          : "Não planejada")
    );
  }
  function externalCard(item) {
    const origin = [item.obra, item.etapa].filter(Boolean).join(" · ");
    const detail = [
      origin,
      item.imagem_nome,
      level(item) && `N${level(item)}`,
      pointsLabel(item),
      "Somente leitura",
    ]
      .filter(Boolean)
      .join(" · ");
    return `<a class="ap-card ap-external" draggable="false" href="index.php?entrega_id=${Number(item.entrega_id)}" title="${escape(detail)}" aria-label="Abrir planejamento de origem · ${escape(detail)}">
      ${thumbnail(item)}<span class="ap-card-copy"><small class="ap-external-origin">${escape(origin)}</small><strong>${escape(item.imagem_nome)}</strong><small>${levelBadge(item)} <span>${pointsLabel(item)}</span></small></span><i class="fa-solid fa-lock ap-card-icon" aria-hidden="true"></i>
    </a>`;
  }
  const railStatuses = {
    "": () => true,
    pending: (item) => pendingWork(item) && !complete(item),
    planned: (item) => pendingWork(item) && complete(item),
    done: (item) =>
      Boolean(
        item.data_entregue || item.motivo_bloqueio === "Alteração concluída",
      ),
  };
  function railItems(status = state.railStatus) {
    const query = classificationSearchKey($("ap-rail-search").value.trim());
    const selectedLevel = $("ap-rail-level").value;
    return state.items.filter(
      (item) =>
        (!query ||
          classificationSearchKey(String(item.imagem_nome)).includes(query)) &&
        (!selectedLevel || (level(item) || "none") === selectedLevel) &&
        railStatuses[status](item),
    );
  }
  function renderRail() {
    clearTimeout(railSearchTimer);
    const items = railItems();
    $("ap-rail-states")
      .querySelectorAll("button")
      .forEach((button) => {
        const status = button.dataset.railState;
        button.setAttribute(
          "aria-pressed",
          String(status === state.railStatus),
        );
        button.querySelector("span").textContent = railItems(status).length;
      });
    const done = state.items.filter(railStatuses.done).length;
    $("ap-rail-count").textContent =
      items.length === state.items.length
        ? `${pendingItems().length} elegíveis · ${done} concluídas`
        : `${items.length} / ${state.items.length} imagens`;
    $("ap-rail-items").innerHTML =
      items.map((item) => card(item, true)).join("") ||
      '<p class="ap-empty">Nenhuma imagem corresponde aos filtros.</p>';
  }
  let railSearchTimer;
  $("ap-rail-search").addEventListener("input", () => {
    clearTimeout(railSearchTimer);
    railSearchTimer = setTimeout(renderRail, 120);
  });
  $("ap-rail-level").addEventListener("change", renderRail);
  $("ap-rail-states").addEventListener("click", (event) => {
    const button = event.target.closest("[data-rail-state]");
    if (!button) return;
    state.railStatus = button.dataset.railState;
    renderRail();
  });
  $("ap-rail-toggle").addEventListener("click", ({ currentTarget }) => {
    const collapsed = currentTarget
      .closest(".ap-calendar-workspace")
      .classList.toggle("is-rail-collapsed");
    const label = collapsed ? "Expandir imagens" : "Recolher imagens";
    currentTarget.setAttribute("aria-expanded", String(!collapsed));
    currentTarget.setAttribute("aria-label", label);
    currentTarget.title = label;
    currentTarget.querySelector("i").className =
      `fa-solid fa-angles-${collapsed ? "right" : "left"}`;
    $("ap-rail-controls").inert = collapsed;
    $("ap-rail-items").inert = collapsed;
  });
  $("ap-show-external").addEventListener("change", ({ target }) =>
    $("ap-grid").classList.toggle("hide-external", !target.checked),
  );
  $("ap-today").addEventListener("click", () => {
    state.dayIndex = todayIndex();
    renderPlanner();
  });

  function renderForecast(work, due) {
    const dates = work.map((item) => {
      const operational = date(item.execucao?.previsao);
      const planned = complete(item) ? item.fim_planejado : "";
      return operational > planned ? operational : planned;
    });
    const allScheduled = dates.length && dates.every(validDate);
    const finish = allScheduled ? dates.sort().at(-1) : null;
    const stale = finish && dates.some((day) => day < today());
    const overdue = validDate(due) && due < today() && work.length > 0;
    const forecast = $("ap-forecast");
    forecast.textContent = !work.length
      ? "Sem pendências"
      : finish && !stale
        ? formatDate(finish)
        : "Indisponível";
    forecast.title = !work.length
      ? "O escopo atual não tem alterações pendentes."
      : stale
        ? "Há imagens pendentes com datas planejadas vencidas."
        : finish
          ? "Última data planejada ou previsão operacional informada para as alterações pendentes."
          : "A previsão depende das datas das alterações pendentes.";
    const margin = $("ap-margin");
    margin.className = "";
    if (overdue) {
      margin.textContent = "Prazo vencido";
      margin.className = "ap-late";
      return;
    }
    if (!finish || stale || !validDate(due)) {
      margin.textContent = "—";
      return;
    }
    const start = finish <= due ? finish : due;
    const end = finish <= due ? due : finish;
    const distance = days().filter((day) => day > start && day <= end).length;
    margin.textContent = `${distance ? (finish <= due ? "+" : "−") : ""}${distance} ${distance === 1 ? "dia útil" : "dias úteis"}`;
    margin.className = finish > due ? "ap-late" : distance ? "ap-healthy" : "";
  }

  function capacityBadge(total, cap) {
    if (total > cap) return "is-overloaded";
    return cap > 0 && total / cap >= 0.85 ? "is-near-limit" : "";
  }

  function renderLoad(load, committed, cap, context) {
    const externalWidth =
      cap > 0
        ? Math.min(100, (committed / cap) * 100)
        : committed > 0
          ? 100
          : 0;
    const currentWidth =
      cap > 0
        ? Math.min(100 - externalWidth, (load / cap) * 100)
        : load > 0
          ? 100 - externalWidth
          : 0;
    return `<span class="ap-load-bar" role="img" aria-label="${escape(context)}"><span style="inline-size:${externalWidth}%"></span><span style="inline-size:${currentWidth}%"></span></span>`;
  }

  function renderPlanner() {
    if (!state.plan) return;
    const allDays = days();
    const lastStart = Math.max(0, allDays.length - 5);
    state.dayIndex = Math.min(Math.max(0, state.dayIndex), lastStart);
    const visibleDays = allDays.slice(state.dayIndex, state.dayIndex + 5);
    const current = today();
    $("ap-today").disabled =
      state.dayIndex === Math.min(todayIndex(), lastStart);
    renderRail();
    const period = visibleDays.length
      ? `${formatDate(visibleDays[0]).slice(0, 5)} a ${formatDate(visibleDays.at(-1)).slice(0, 5)} · ${visibleDays.length} dias úteis`
      : "Nenhum dia útil";
    $("ap-mobile-day").innerHTML =
      allDays
        .slice(0, lastStart + 1)
        .map((d) =>
          d === visibleDays[0]
            ? `<option value="${d}" selected>${period}</option>`
            : `<option value="${d}">${formatDate(d)}</option>`,
        )
        .join("") || '<option value="">Nenhum dia útil</option>';
    $("ap-mobile-day").disabled = allDays.length <= 5;
    $("ap-day-prev").disabled = state.dayIndex === 0;
    $("ap-day-next").disabled =
      state.dayIndex + visibleDays.length >= allDays.length;
    const work = pendingItems();
    const total = work.reduce((s, i) => s + points(i), 0),
      ready = work.filter(complete);
    const unknownPoints = work.some((i) => i.pontos_snapshot == null);
    $("ap-demand").textContent = unknownPoints
      ? `Sem pontos · ${work.length} img.`
      : `${num(total)} pts · ${work.length} img.`;
    $("ap-allocated").textContent = unknownPoints
      ? `${ready.length} imagens planejadas · sem snapshot de pontos`
      : `${num(ready.reduce((s, i) => s + points(i), 0))} / ${num(total)} pts alocados`;
    $("ap-level-summary").innerHTML = [1, 2, 3, 4, 5]
      .map((n) => {
        const items = work.filter((i) => Number(level(i)) === n);
        return `<div><span class="ap-level" data-level="${n}">N${n}</span><small>${items.length} imagens</small><strong>${items.some((i) => i.pontos_snapshot == null) ? "Sem pontos" : num(items.reduce((s, i) => s + points(i), 0)) + " pts"}</strong></div>`;
      })
      .join("");
    const finish = ready.length
      ? ready
          .map((i) => i.fim_planejado)
          .sort()
          .at(-1)
      : null;
    const due = date(state.plan.entrega.data_prevista);
    renderForecast(work, due);
    const people = state.plan.colaboradores.filter((p) =>
      state.people.has(Number(p.id)),
    );
    const historicalIds = [
      ...new Set(
        state.items
          .map((i) => Number(i.responsavel_planejado_id))
          .filter((id) => id && !validPerson(id)),
      ),
    ];
    historicalIds.forEach((id) =>
      people.push({ id, nome: personName(id), historico: true }),
    );
    const byCell = new Map();
    state.items.forEach((item) => {
      if (!item.responsavel_planejado_id || !item.fim_planejado) return;
      const key = cellKey(item.responsavel_planejado_id, item.fim_planejado);
      if (!byCell.has(key)) byCell.set(key, []);
      byCell.get(key).push(item);
    });
    const weekCapacity = people.reduce(
      (sum, person) =>
        sum +
        visibleDays.reduce(
          (total, day) =>
            total + Number(capacity(person.id, day)?.capacidade || 0),
          0,
        ),
      0,
    );
    const unknownWeek = people.some((person) =>
      visibleDays.some((day) => {
        const cell = capacity(person.id, day);
        return !cell || cell.avisos?.length;
      }),
    );
    $("ap-week-capacity").textContent = people.length
      ? unknownWeek
        ? "Capacidade do período parcialmente estimada"
        : `Capacidade do período · ${num(weekCapacity)} pts`
      : "";
    $("ap-grid").style.setProperty("--ap-days", visibleDays.length || 1);
    $("ap-grid").innerHTML =
      `<div class="ap-grid-corner">Colaborador</div>${visibleDays
        .map((d) => {
          const day = new Date(d + "T12:00:00");
          return `<div class="ap-grid-day${d < current ? " is-past" : ""}${d === due ? " is-due" : ""}"${d === current ? ' aria-current="date"' : ""}${d === due ? ' title="Prazo do cliente"' : ""}><strong>${day.toLocaleDateString("pt-BR", { weekday: "short" }).replace(".", "")} ${day.getDate()}</strong><small>${day.toLocaleDateString("pt-BR", { month: "short" })}</small></div>`;
        })
        .join("")}` +
      people
        .map((p) => {
          const id = Number(p.id),
            nominal =
              state.plan.configuracao?.colaboradores?.[id] ??
              state.plan.configuracao?.capacidade_padrao;
          const personRole = p.historico
            ? "Vínculo de Alteração indisponível"
            : `Alteração ${roleName(p).toLowerCase()}`;
          return (
            `<div class="ap-grid-person"><span class="ap-avatar">${escape(p.nome.slice(0, 1))}</span><strong>${escape(p.nome)}</strong><small class="ap-role">${personRole}</small><small>${p.historico ? "Responsável histórico" : num(nominal) + " pts/dia"}</small>${!p.historico ? `<div class="ap-person-actions"><button type="button" class="ap-button" data-agenda="${id}" aria-label="Agenda e capacidade de ${escape(p.nome)}" title="Agenda e capacidade"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button><button class="ap-person-remove ap-button" type="button" data-remove-person="${id}" aria-label="Retirar ${escape(p.nome)} da grade" title="Remover da grade"${editable() ? "" : " disabled"}>×</button></div>` : ""}</div>` +
            visibleDays
              .map((d) => {
                const items = byCell.get(cellKey(id, d)) || [];
                const a = capacity(id, d),
                  load = items.reduce(
                    (sum, item) => sum + (pendingWork(item) ? points(item) : 0),
                    0,
                  ),
                  committed = Number(a?.comprometidos || 0),
                  cap = Number(a?.capacidade || 0),
                  used = load + committed;
                const unknownLoad = items.some(
                    (i) => pendingWork(i) && i.pontos_snapshot == null,
                  ),
                  unknownOffer = !a || a.avisos?.length;
                const offerLabel = !a
                  ? "Capacidade indisponível"
                  : a.avisos?.length
                    ? `${num(a.nominal)} pts/dia nominais · disponibilidade não estimada`
                    : `${num(Math.max(0, cap - used))} pts disponíveis`;
                const loadLabel = unknownLoad
                  ? `${items.length} imagens · pontos não registrados`
                  : !a
                    ? `${items.length} imagens`
                    : `${num(used)} / ${num(cap)} pts`;
                const context = [
                  offerLabel,
                  `${num(committed)} pts de outras entregas`,
                  `${num(load)} pts desta entrega`,
                  used > cap && a ? `Sobrecarga de ${num(used - cap)} pts` : "",
                  a?.ocupacao_r00
                    ? `R00: ${num(a.ocupacao_r00 * 100)}% do dia`
                    : "",
                  ...(a?.avisos || []),
                ]
                  .filter(Boolean)
                  .join(" · ");
                const external = state.plan.tarefas_externas?.[id]?.[d] || [];
                return `<div class="ap-grid-cell ${a ? capacityBadge(used, cap) : ""}"${a && !p.historico ? ` data-drop-person="${id}" data-drop-day="${d}"` : ""}>
                  <div class="ap-load" title="${escape(context)}">
                    <strong class="ap-cell-load">${loadLabel}</strong>
                    ${!unknownOffer && !unknownLoad ? renderLoad(load, committed, cap, context) : `<small>${escape(offerLabel)}</small>`}
                  </div>
                  ${items.map((item) => card(item)).join("")}
                  ${external.map(externalCard).join("")}
                  ${a && !p.historico && editable() ? `<button type="button" class="ap-add-image" data-place-person="${id}" data-place-day="${d}" aria-label="Adicionar imagem para ${escape(p.nome)} em ${formatDate(d)}" title="Adicionar imagem"><span aria-hidden="true">+</span></button>` : ""}
                </div>`;
              })
              .join("")
          );
        })
        .join("") +
      (!people.length
        ? '<p class="ap-empty">Adicione um colaborador para planejar as imagens.</p>'
        : "");
    if (state.editorId !== null) positionEditor();
    const usedIds = new Set(
      ready.map((i) => Number(i.responsavel_planejado_id)),
    );
    $("ap-team-capacity").textContent = [...usedIds].some(
      (id) => !validPerson(id),
    )
      ? `${usedIds.size} ${usedIds.size === 1 ? "colaborador utilizado" : "colaboradores utilizados"} · capacidade histórica não estimada`
      : `${usedIds.size} ${usedIds.size === 1 ? "colaborador utilizado" : "colaboradores utilizados"} · ${num([...usedIds].reduce((sum, id) => sum + Number(state.plan.configuracao?.colaboradores?.[id] ?? state.plan.configuracao?.capacidade_padrao ?? 0), 0))} pts/dia nominais`;
    $("ap-grid-summary").textContent =
      `${ready.length}/${work.length} imagens · conclusão ${formatDate(finish)}`;
    $("ap-suggest").disabled =
      !editable() || !state.people.size || !suggestibleItems().length;
    $("ap-suggest-order").disabled = !editable() || !state.people.size;
    const types = [
      ...new Set(
        suggestibleItems()
          .map((i) => i.tipo_imagem)
          .filter(Boolean),
      ),
    ].sort((a, b) => a.localeCompare(b, "pt-BR"));
    state.typePriority = [
      ...state.typePriority.filter((type) => types.includes(type)),
      ...types.filter((type) => !state.typePriority.includes(type)),
    ];
    $("ap-type-priority-enabled").checked = state.priorityEnabled;
    $("ap-type-priority-enabled").disabled = !editable() || !types.length;
    $("ap-type-priority").innerHTML = state.typePriority
      .map(
        (type, index) =>
          `<li><strong>${index + 1}.</strong><span>${escape(type)}</span><button type="button" class="ap-button" data-priority-index="${index}" data-priority-move="-1" aria-label="Subir ${escape(type)}"${!editable() || !state.priorityEnabled || index === 0 ? " disabled" : ""}>↑</button><button type="button" class="ap-button" data-priority-index="${index}" data-priority-move="1" aria-label="Descer ${escape(type)}"${!editable() || !state.priorityEnabled || index === state.typePriority.length - 1 ? " disabled" : ""}>↓</button></li>`,
      )
      .join("");
    $("ap-type-rules").innerHTML =
      types
        .map(
          (type) =>
            `<label>${escape(type)}<select data-type-rule="${escape(type)}"${editable() ? "" : " disabled"}><option value="">Distribuir automaticamente</option>${state.typeRules[type] && !state.people.has(Number(state.typeRules[type])) ? `<option selected disabled value="${Number(state.typeRules[type])}">${escape(personName(state.typeRules[type]))} (fora da grade)</option>` : ""}${people.map((person) => `<option value="${Number(person.id)}"${Number(state.typeRules[type]) === Number(person.id) ? " selected" : ""}>${escape(person.nome)}</option>`).join("")}</select></label>`,
        )
        .join("") || "<span>Nenhum tipo de imagem disponível.</span>";
  }
  const levelBadge = (item) =>
    level(item)
      ? `<span class="ap-level" data-level="${escape(level(item))}">N${escape(level(item))}</span>`
      : '<span class="ap-item-state">—</span>';
  const visibleItems = () => {
    const query = $("ap-search").value.trim().toLocaleLowerCase("pt-BR");
    const pending = $("ap-pending").value;
    const selectedLevel = $("ap-level").value;
    const person = $("ap-person").value;
    const matchesStatus = {
      responsavel: (item) => !hasResponsible(item),
      data: (item) => !validDate(item.fim_planejado),
      planejada: complete,
      pendente: (item) => pendingWork(item) && !complete(item),
      bloqueada: (item) => !pendingWork(item) || !canAssign(item),
    };
    return state.items.filter(
      (item) =>
        (!query ||
          String(item.imagem_nome)
            .toLocaleLowerCase("pt-BR")
            .includes(query)) &&
        (!pending || matchesStatus[pending](item)) &&
        (!selectedLevel ||
          (selectedLevel === "none"
            ? !level(item)
            : level(item) === selectedLevel)) &&
        (!person ||
          (person === "none"
            ? !item.responsavel_planejado_id
            : Number(item.responsavel_planejado_id) === Number(person))),
    );
  };
  async function confirmAction(text) {
    if (!window.FlowAlert?.confirm) return false;
    const result = await window.FlowAlert.confirm({
      title: "Confirmar ação",
      message: text,
    });
    return result.isConfirmed;
  }
  function message(text, error = false) {
    if (error)
      window.FlowAlert?.error({
        title: "Atenção",
        message: text,
        mode: "modal",
      });
    else if (typeof window.Toastify === "function")
      window
        .Toastify({ text, duration: 4000, gravity: "top", position: "right" })
        .showToast();
    else window.FlowAlert?.info({ message: text });
  }
  function setLoading(loading, label = "Carregando planejamento…") {
    $("ap-loading").hidden = !loading;
    $("ap-loading").querySelector("span").textContent = label;
    $("ap-loading")
      .querySelector("canvas")
      .setAttribute("aria-label", label.replace(/…$/, ""));
    $("alteration-plan").setAttribute("aria-busy", String(loading));
  }
  function renderRows() {
    const items = visibleItems();
    $("ap-visible").textContent =
      `${items.length} de ${state.items.length} imagens`;
    $("ap-empty").hidden = items.length > 0;
    $("ap-empty").textContent = state.items.length
      ? "Nenhuma imagem corresponde aos filtros."
      : "Esta entrega ainda não possui imagens para planejar.";
    $("ap-rows").innerHTML = items
      .map((item) => {
        const id = Number(item.entrega_item_id);
        const disabled = editable() && pendingWork(item) ? "" : " disabled";
        const name = escape(item.imagem_nome || `Imagem ${item.imagem_id}`);
        return `<article class="ap-list-row${state.selected.has(id) ? " is-selected" : ""}" data-id="${id}">
          <input type="checkbox" data-select="${id}" aria-label="Selecionar ${name}"${state.selected.has(id) ? " checked" : ""}${disabled}>
          <button type="button" class="ap-list-image" data-edit-image="${id}">${thumbnail(item)}<strong>${name}</strong></button>
          <span class="ap-list-score">${levelBadge(item)} ${pointsLabel(item)}</span>
          <span>${escape(personName(item.responsavel_planejado_id))}</span>
          <time>${formatDate(item.fim_planejado)}</time>
          <div class="ap-list-execution">${
            item.pode_transferir_execucao
              ? `<span class="ap-item-state">${escape(item.execucao.status)}</span><button type="button" class="ap-button" data-transfer-image="${id}">Transferir execução</button>`
              : itemStatus(item)
          }</div>
        </article>`;
      })
      .join("");
    renderSelection();
  }
  function itemStatus(item) {
    const notice =
      item.motivo_bloqueio ||
      (item.atribuicao_divergente
        ? "Atribuição operacional difere do plano"
        : item.motivo_bloqueio_responsavel);
    if (notice) return `<span class="ap-item-state">${escape(notice)}</span>`;
    const ready = complete(item);
    return `<span class="ap-item-state${ready ? " is-complete" : ""}">${ready ? (confirmed() && !state.replanning ? "Planejada" : "Completa") : item.inicio_planejado && item.fim_planejado && item.inicio_planejado > item.fim_planejado ? "Rever datas" : "Pendente"}</span>`;
  }
  function renderSelection() {
    const items = visibleItems().filter(pendingWork);
    const count = items.filter((item) =>
      state.selected.has(Number(item.entrega_item_id)),
    ).length;
    $("ap-select-all").checked = items.length > 0 && count === items.length;
    $("ap-select-all").indeterminate = count > 0 && count < items.length;
    $("ap-select-all").disabled = !editable() || !items.length;
    const selected = selectionItems();
    const bar = $("ap-bulk");
    const showBulk = selected.length >= 2;
    $("ap-selection").textContent =
      `${selected.length} ${selected.length === 1 ? "imagem selecionada" : "imagens selecionadas"} · ${num(selected.reduce((total, item) => total + points(item), 0))} pts`;
    if (showBulk && !bar.matches(":popover-open")) bar.showPopover();
    if (!showBulk && bar.matches(":popover-open")) bar.hidePopover();
    ["ap-bulk-replan", "ap-bulk-assign", "ap-clear"].forEach((id) => {
      $(id).disabled = !editable();
    });
    updateActiveCards();
  }
  function renderSummary() {
    const work = pendingItems();
    const count = work.filter(complete).length;
    const withoutPerson = work.filter((item) => !hasResponsible(item)).length;
    const withoutDate = work.filter(
      (item) => !validDate(item.fim_planejado),
    ).length;
    $("ap-complete").textContent = `${count} / ${work.length}`;
    $("ap-progress").max = work.length || 1;
    $("ap-progress").value = count;
    $("ap-status").textContent =
      confirmed() && !state.replanning
        ? "Planejamento confirmado"
        : state.replanning
          ? "Replanejamento em rascunho"
          : count === 0
            ? "Planejamento pendente"
            : count === work.length
              ? "Pronto para confirmar"
              : "Planejamento parcial";
    $("ap-missing").textContent =
      `${withoutPerson} sem responsável · ${withoutDate} sem dia planejado`;
    $("ap-save-status").textContent = state.suggesting
      ? "Gerando sugestão…"
      : state.saving
        ? "Salvando rascunho…"
        : state.saveError
          ? "Erro ao salvar"
          : state.dirty
            ? "Alterações não salvas"
            : confirmed() && !state.replanning
              ? ""
              : state.plan.planejamento.rascunho_pendente
                ? "Rascunho salvo"
                : "Sem alterações locais";
    $("ap-save").hidden = $("ap-confirm").hidden =
      confirmed() && !state.replanning;
    $("ap-save").disabled = !editable();
    $("ap-confirm").disabled =
      !editable() || count !== work.length || !work.length;
    $("ap-replan").hidden = !confirmed() || state.replanning;
    $("ap-replan").disabled =
      state.plan.pode_editar === false || state.saving || state.conflict;
    $("ap-cancel-replan").disabled = !state.dirty && !state.replanning;
    $("ap-capacity-settings").hidden =
      state.plan.pode_configurar_capacidade === false;
    updateEditorAvailability();
    renderDistribution();
    renderSelection();
    renderPlanner();
  }
  function renderDistribution() {
    const groups = new Map();
    pendingItems().forEach((item) => {
      const id = Number(item.responsavel_planejado_id) || 0;
      if (!groups.has(id))
        groups.set(id, {
          name: personName(id),
          count: 0,
          points: 0,
          unknownPoints: false,
          levels: {},
        });
      const group = groups.get(id);
      group.count++;
      group.points += points(item);
      group.unknownPoints ||= item.pontos_snapshot == null;
      if (level(item))
        group.levels[level(item)] = (group.levels[level(item)] || 0) + 1;
    });
    $("ap-distribution").innerHTML =
      [...groups.entries()]
        .sort(([a, first], [b, second]) =>
          a === 0
            ? 1
            : b === 0
              ? -1
              : first.name.localeCompare(second.name, "pt-BR"),
        )
        .map(
          ([, group]) =>
            `<article class="ap-person-summary"><div><strong>${escape(group.name)}</strong><span>${group.count} imagens · ${group.unknownPoints ? "pontos não registrados" : num(group.points) + " pts"}</span></div><div class="ap-person-levels">${Object.entries(
              group.levels,
            )
              .sort()
              .map(
                ([n, total]) =>
                  `<span class="ap-level" data-level="${escape(n)}">N${escape(n)} × ${total}</span>`,
              )
              .join("")}</div></article>`,
        )
        .join("") || "<p>Nenhuma imagem nesta entrega.</p>";
  }
  function markDirty() {
    state.dirty = true;
    state.saveError = false;
    renderSummary();
  }
  function acceptPlan(plan) {
    const firstLoad = !state.plan;
    state.plan = plan;
    state.reviewedDraft = false;
    if (
      !plan.planejamento.rascunho_requer_revisao &&
      (plan.planejamento.estado === "REPLANEJAMENTO" ||
        (plan.planejamento.versao_atual_id &&
          plan.planejamento.rascunho_pendente))
    )
      state.replanning = true;
    state.items = (plan.itens || []).map((item) => ({
      ...item,
      responsavel_planejado_id: Number(item.responsavel_planejado_id) || null,
      inicio_planejado: date(item.inicio_planejado) || null,
      fim_planejado: date(item.fim_planejado) || null,
    }));
    state.people = new Set([...state.people].filter((id) => validPerson(id)));
    state.items.forEach((i) => {
      if (validPerson(i.responsavel_planejado_id))
        state.people.add(Number(i.responsavel_planejado_id));
    });
    if (!state.people.size)
      plan.colaboradores
        .filter((p) => p.tipo_atuacao === "PRINCIPAL")
        .forEach((p) => state.people.add(Number(p.id)));
    if (!state.people.size && plan.colaboradores.length)
      state.people.add(Number(plan.colaboradores[0].id));
    if (firstLoad) state.dayIndex = todayIndex();
    state.selected = new Set(
      [...state.selected].filter((id) =>
        state.items.some(
          (item) => Number(item.entrega_item_id) === id && pendingWork(item),
        ),
      ),
    );
    state.dirty = false;
    state.conflict = false;
    state.saveError = false;
    const eligible = pendingItems().length;
    const delivered = state.items.filter((i) => i.data_entregue).length;
    $("ap-partial").hidden = !confirmed() || eligible === state.items.length;
    $("ap-partial").textContent =
      `Replanejamento parcial · ${delivered} ${delivered === 1 ? "entregue" : "entregues"} · ${eligible} ${eligible === 1 ? "elegível" : "elegíveis"}`;
    const e = plan.entrega;
    $("alteration-title").textContent =
      `${e.nomenclatura || "Projeto"} / ${e.etapa || "Alteração"}`;
    $("ap-project").textContent = `Lote ${e.id}`;
    $("ap-received").textContent = formatDate(e.data_recebimento);
    $("ap-due").textContent = formatDate(e.data_prevista);
    const baseline = plan.planejamento.historico?.find(
      (entry) =>
        Number(entry.id) === Number(plan.planejamento.baseline_versao_id),
    );
    $("ap-original-forecast").hidden = !validDate(date(baseline?.fim_previsto));
    $("ap-original-forecast").textContent =
      ` · Previsão original ${formatDate(baseline?.fim_previsto)}`;
    $("ap-person").innerHTML =
      '<option value="">Todos</option><option value="none">Sem responsável</option>' +
      personOptions(null).replace('<option value="">Selecione</option>', "");
    $("ap-bulk-person").innerHTML = personOptions(null, "Manter atual");
    $("ap-content").hidden = false;
    $("ap-reload").hidden = true;
    renderRows();
    renderSummary();
    renderEditor();
    if (plan.planejamento.rascunho_requer_revisao)
      message(
        "O contexto do rascunho salvo mudou. Revise o plano atual e refaça as decisões antes de salvar.",
        true,
      );
    else if (plan.desatualizado)
      message(
        "O contexto da entrega mudou desde a versão confirmada. Revise as imagens e confirme um replanejamento antes de continuar.",
        true,
      );
  }
  async function load() {
    setLoading(true);
    try {
      const response = await fetch(`alteracao.php?entrega_id=${entregaId}`, {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
        cache: "no-store",
      });
      const result = await response.json();
      if (!response.ok || result.success === false)
        throw new Error(
          result.message || "Não foi possível carregar o planejamento.",
        );
      if (result.classificacao_pendente) {
        showClassification(result.classificacao_pendente, result.csrf_token);
        return;
      }
      state.replanning = false;
      acceptPlan(result.plano || result);
    } catch (error) {
      message(error.message, true);
      $("ap-reload").hidden = false;
    } finally {
      setLoading(false);
    }
  }
  const classificationDialog = $("ap-classification-dialog");
  const classificationForm = $("ap-classification-form");
  const classificationSubmit = $("ap-classification-submit");
  const classificationError = $("ap-classification-error");
  const classificationClose = $("ap-classification-close");
  const classificationWorkspace = $("ap-classification-workspace");
  const classificationItems = $("ap-classification-items");
  const classificationSearch = $("ap-classification-search");
  const classificationType = $("ap-classification-type");
  const classificationFilter = $("ap-classification-filter");
  const classificationSelectAll = $("ap-classification-select-all");
  const classificationBatchLevel = $("ap-classification-batch-level");
  const classificationApply = $("ap-classification-apply");
  const classificationOptions = [
    "Muito baixa",
    "Baixa",
    "Média",
    "Alta",
    "Muito alta",
  ]
    .map(
      (label, index) =>
        `<option value="${index + 1}">N${index + 1} — ${label}</option>`,
    )
    .join("");
  const classificationSearchKey = (value) =>
    value.normalize("NFD").replace(/\p{M}/gu, "").toLocaleLowerCase("pt-BR");
  let classificationRows = [];

  function renderClassification() {
    const query = classificationSearchKey(classificationSearch.value.trim());
    let visible = 0;
    let selected = 0;
    let classified = 0;
    for (const { row, name, type, checkbox, level } of classificationRows) {
      const matchesLevel =
        !classificationFilter.value ||
        (level.value || "pending") === classificationFilter.value;
      row.hidden =
        !name.includes(query) ||
        (classificationType.value !== "" &&
          classificationType.value !== type) ||
        !matchesLevel;
      if (row.hidden) checkbox.checked = false;
      row.classList.toggle("is-selected", checkbox.checked);
      row.classList.toggle("has-level", Boolean(level.value));
      visible += Number(!row.hidden);
      selected += Number(checkbox.checked);
      classified += Number(Boolean(level.value));
    }
    classificationSelectAll.disabled = visible === 0;
    classificationSelectAll.checked = visible > 0 && selected === visible;
    classificationSelectAll.indeterminate = selected > 0 && selected < visible;
    classificationApply.disabled = !selected || !classificationBatchLevel.value;
    $("ap-classification-selected").textContent =
      `${selected} ${selected === 1 ? "selecionada" : "selecionadas"}`;
    $("ap-classification-visible").textContent =
      `${visible} de ${classificationRows.length} imagens visíveis`;
    $("ap-classification-empty").hidden = visible !== 0;
    $("ap-classification-progress").textContent =
      `${classified} de ${classificationRows.length} imagens classificadas`;
  }

  function resetClassificationFilters() {
    classificationSearch.value =
      classificationType.value =
      classificationFilter.value =
        "";
    renderClassification();
  }

  function showClassification(pending, csrfToken) {
    $("ap-content").hidden = true;
    $("ap-reload").hidden = false;
    classificationForm.dataset.liberacaoId = pending.liberacao?.id ?? 0;
    classificationForm.dataset.csrfToken = csrfToken;
    classificationError.hidden = true;
    $("ap-classification-title").textContent = pending.itens.length
      ? "Classificar imagens"
      : "Iniciar planejamento";
    $("ap-classification-description").textContent = pending.itens.length
      ? "Escolha o nível de complexidade de cada imagem pendente para continuar."
      : "As imagens desta entrega já têm nível de complexidade. Continue para abrir o planejamento.";
    classificationSubmit.textContent = pending.itens.length
      ? "Salvar classificação"
      : "Continuar";
    classificationWorkspace.hidden = pending.itens.length === 0;
    $("ap-classification-progress").hidden = classificationWorkspace.hidden;
    const types = [
      ...new Set(pending.itens.map((item) => item.tipo_imagem).filter(Boolean)),
    ].sort((a, b) => a.localeCompare(b, "pt-BR"));
    classificationType.innerHTML =
      '<option value="">Todos os tipos</option>' +
      types
        .map(
          (type) => `<option value="${escape(type)}">${escape(type)}</option>`,
        )
        .join("");
    classificationFilter.innerHTML =
      '<option value="">Todos os níveis</option><option value="pending">Sem nível</option>' +
      classificationOptions;
    classificationBatchLevel.innerHTML =
      '<option value="">Ação em lote</option>' +
      classificationOptions +
      '<option value="clear">Limpar nível</option>';
    classificationItems.innerHTML = pending.itens
      .map(
        (item) =>
          `<div class="ap-classification-row">
        <input type="checkbox" aria-label="Selecionar ${escape(item.imagem_nome)}">
        <div class="ap-classification-image"><strong>${escape(item.imagem_nome)}</strong>${item.tipo_imagem ? `<small>${escape(item.tipo_imagem)}</small>` : ""}</div>
        <select name="${Number(item.entrega_item_id)}" aria-label="Nível de ${escape(item.imagem_nome)}" required>
          <option value="">Sem nível</option>${classificationOptions}
        </select>
      </div>`,
      )
      .join("");
    classificationRows = [...classificationItems.children].map(
      (row, index) => ({
        row,
        name: classificationSearchKey(pending.itens[index].imagem_nome),
        type: pending.itens[index].tipo_imagem,
        checkbox: row.querySelector("input"),
        level: row.querySelector("select"),
      }),
    );
    resetClassificationFilters();
    classificationDialog.showModal();
    if (classificationRows.length) classificationSearch.focus();
  }
  classificationSearch.addEventListener("input", renderClassification);
  classificationSearch.addEventListener("keydown", (event) => {
    if (event.key === "Enter") event.preventDefault();
  });
  classificationType.addEventListener("change", renderClassification);
  classificationFilter.addEventListener("change", renderClassification);
  classificationBatchLevel.addEventListener("change", renderClassification);
  classificationItems.addEventListener("change", (event) => {
    if (event.target.matches("select")) classificationError.hidden = true;
    renderClassification();
  });
  $("ap-classification-reset").addEventListener("click", () => {
    resetClassificationFilters();
    classificationSearch.focus();
  });
  classificationSelectAll.addEventListener("change", () => {
    for (const { row, checkbox } of classificationRows) {
      if (!row.hidden) checkbox.checked = classificationSelectAll.checked;
    }
    renderClassification();
  });
  classificationApply.addEventListener("click", () => {
    const value = classificationBatchLevel.value;
    for (const { checkbox, level } of classificationRows) {
      if (!checkbox.checked) continue;
      level.value = value === "clear" ? "" : value;
      checkbox.checked = false;
    }
    classificationBatchLevel.value = "";
    classificationError.hidden = true;
    renderClassification();
    classificationBatchLevel.focus();
  });
  classificationClose.addEventListener("click", () =>
    classificationDialog.close(),
  );
  classificationDialog.addEventListener("cancel", (event) => {
    if (classificationSubmit.disabled) event.preventDefault();
  });
  classificationForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (classificationSubmit.disabled) return;
    const missing = classificationRows.find(({ level }) => !level.value);
    if (missing) {
      if (missing.row.hidden) resetClassificationFilters();
      classificationError.textContent =
        "Escolha um nível para cada imagem antes de salvar.";
      classificationError.hidden = false;
      missing.level.focus();
      return;
    }
    const levels = Object.fromEntries(new FormData(classificationForm));
    classificationError.hidden = true;
    classificationSubmit.disabled = classificationClose.disabled = true;
    classificationWorkspace.inert = true;
    classificationForm.setAttribute("aria-busy", "true");
    try {
      const response = await fetch("alteracao.php", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          acao: "classificar",
          entrega_id: entregaId,
          liberacao_id: Number(classificationForm.dataset.liberacaoId),
          csrf_token: classificationForm.dataset.csrfToken,
          niveis: levels,
        }),
      });
      const result = await response.json();
      if (!response.ok || !result.success)
        throw new Error(
          result.message || "Não foi possível classificar as imagens.",
        );
      classificationDialog.close();
      state.replanning = false;
      acceptPlan(result.plano);
    } catch (error) {
      classificationError.textContent = error.message;
      classificationError.hidden = false;
    } finally {
      classificationSubmit.disabled = classificationClose.disabled = false;
      classificationWorkspace.inert = false;
      classificationForm.removeAttribute("aria-busy");
    }
  });
  function openConfirmation() {
    if (!editable() || $("ap-confirm").disabled) return;
    const oldItems = new Map(
      (state.plan.vigente_itens || []).map((i) => [
        Number(i.entrega_item_id),
        i,
      ]),
    );
    const movedDays = state.items.filter(
      (i) =>
        oldItems.has(Number(i.entrega_item_id)) &&
        oldItems.get(Number(i.entrega_item_id)).fim_planejado !==
          i.fim_planejado,
    ).length;
    const movedPeople = state.items.filter(
      (i) =>
        oldItems.has(Number(i.entrega_item_id)) &&
        Number(
          oldItems.get(Number(i.entrega_item_id)).responsavel_planejado_id,
        ) !== Number(i.responsavel_planejado_id),
    ).length;
    $("ap-confirm-title").textContent = state.replanning
      ? "Confirmar replanejamento"
      : "Confirmar planejamento";
    $("ap-replan-fields").hidden = !state.replanning;
    $("ap-reason").required = state.replanning;
    $("ap-note").required =
      state.replanning && $("ap-reason").value === "OUTRO";
    $("ap-note").setCustomValidity("");
    $("ap-confirm-summary").textContent = state.replanning
      ? `${movedDays} imagens mudaram de dia e ${movedPeople} de responsável. A nova versão preserva o histórico.`
      : "Os responsáveis serão aplicados às tarefas desta entrega.";
    $("ap-confirm-dialog").showModal();
  }
  async function save(action) {
    if (!editable()) return;
    if (
      state.plan.planejamento.rascunho_requer_revisao &&
      !state.reviewedDraft
    ) {
      if (
        !(await confirmAction(
          "Substituir o rascunho anterior pelas decisões revisadas nesta tela?",
        )) ||
        !editable()
      )
        return;
      state.reviewedDraft = true;
    }
    state.saving = true;
    state.saveError = false;
    renderRows();
    renderSummary();
    setLoading(
      true,
      action === "confirmar"
        ? "Confirmando planejamento…"
        : "Salvando rascunho…",
    );
    try {
      const response = await fetch("alteracao.php", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          acao: action,
          entrega_id: entregaId,
          lock_version: state.plan.planejamento.lock_version,
          fingerprint: state.plan.fingerprint,
          capacidade_fingerprint: state.plan.capacidade?.fingerprint,
          revisar_rascunho: Boolean(state.reviewedDraft),
          itens: pendingItems().map(
            ({
              entrega_item_id,
              responsavel_planejado_id,
              inicio_planejado,
              fim_planejado,
              fixado,
              excecao_capacidade,
            }) => ({
              entrega_item_id,
              responsavel_planejado_id,
              inicio_planejado,
              fim_planejado,
              fixado,
              excecao_capacidade,
            }),
          ),
          replanejar: state.replanning,
          motivo_codigo: $("ap-reason").value || null,
          motivo_observacao: $("ap-note").value.trim() || null,
          csrf_token: state.plan.csrf_token,
        }),
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        if (
          response.status === 409 ||
          /lock_version|fingerprint|concorr|escopo|contexto.*mud|desatualiz/i.test(
            result.message || "",
          )
        ) {
          state.conflict = true;
          $("ap-reload").hidden = false;
        }
        throw new Error(
          result.message || "Não foi possível salvar o planejamento.",
        );
      }
      if (action === "confirmar") {
        state.replanning = false;
        $("ap-reason").value = "";
        $("ap-note").value = "";
      }
      state.saving = false;
      acceptPlan(result.plano);
      message(
        action === "confirmar"
          ? "Planejamento confirmado. Os responsáveis foram aplicados às tarefas."
          : "Rascunho salvo.",
      );
    } catch (error) {
      state.saveError = true;
      message(error.message, true);
    } finally {
      state.saving = false;
      setLoading(false);
      renderRows();
      renderSummary();
    }
  }
  let editorTrigger;
  const editor = $("ap-editor");
  function positionEditor() {
    if (!editor.matches(":popover-open")) return;
    const view = $("ap-list-view").hidden
      ? $("ap-calendar-view")
      : $("ap-list-view");
    const anchor = editorTrigger?.isConnected
      ? editorTrigger
      : view.querySelector(`[data-edit-image="${state.editorId}"]`);
    const box = anchor?.getBoundingClientRect();
    const margin = 10;
    const { width, height } = editor.getBoundingClientRect();
    const viewport = document.documentElement;
    const left = box
      ? box.right + width + margin * 2 <= viewport.clientWidth
        ? box.right + margin
        : box.left - width - margin
      : viewport.clientWidth - width - margin;
    const top = box?.top ?? margin;
    editor.style.left = `${Math.max(margin, Math.min(left, viewport.clientWidth - width - margin))}px`;
    editor.style.top = `${Math.max(margin, Math.min(top, viewport.clientHeight - height - margin))}px`;
  }
  function openEditor(trigger) {
    editorTrigger = trigger;
    const id = Number(trigger.dataset.editImage);
    state.editorId = id;
    renderEditor();
    if (!editor.matches(":popover-open")) editor.showPopover();
    positionEditor();
    $("ap-editor-close").focus({ preventScroll: true });
  }

  function closeEditor(restoreFocus = true) {
    if (state.editorId === null) return;
    const id = state.editorId;
    state.editorId = null;
    if (editor.matches(":popover-open")) editor.hidePopover();
    const view = $("ap-list-view").hidden
      ? $("ap-calendar-view")
      : $("ap-list-view");
    const trigger =
      editorTrigger?.isConnected && view.contains(editorTrigger)
        ? editorTrigger
        : view.querySelector(`[data-edit-image="${id}"]`);
    if (restoreFocus)
      (
        trigger || document.querySelector('.ap-tabs [aria-selected="true"]')
      ).focus({ preventScroll: true });
    editorTrigger = null;
  }
  window.addEventListener("resize", positionEditor);
  document.addEventListener("scroll", positionEditor, {
    capture: true,
    passive: true,
  });

  function updateActiveCards() {
    $("alteration-plan")
      .querySelectorAll(
        "[data-edit-image], [data-select], [data-card-shell], .ap-list-row[data-id]",
      )
      .forEach((element) => {
        const id = Number(
          element.dataset.editImage ||
            element.dataset.select ||
            element.dataset.cardShell ||
            element.dataset.id,
        );
        const selected = state.selected.has(id);
        if (element.matches("[data-edit-image]"))
          element.setAttribute("aria-pressed", String(selected));
        else if (element.matches("[data-select]")) element.checked = selected;
        else
          element.classList.toggle(
            element.matches("[data-card-shell]") ? "is-active" : "is-selected",
            selected,
          );
      });
  }

  const editorItem = () =>
    state.items.find((item) => Number(item.entrega_item_id) === state.editorId);

  function updateEditorAvailability() {
    const item = editorItem();
    if (!item) return;
    const locked = !editable() || !pendingWork(item);
    $("ap-editor-person").disabled = locked || !canAssign(item);
    $("ap-editor-day").disabled = locked;
    $("ap-editor-pin").disabled = locked;
    $("ap-editor-save").disabled = locked;
    $("ap-editor-remove").disabled =
      locked || Boolean(item.fixado) || !item.fim_planejado;
  }

  function renderEditor() {
    const item = editorItem();
    if (!item) {
      closeEditor(false);
      return;
    }
    $("ap-editor-image").innerHTML =
      `${thumbnail(item)}<strong>${escape(item.imagem_nome)}</strong>`;
    $("ap-editor-person").innerHTML = personOptions(
      item.responsavel_planejado_id,
      "Sem responsável",
    );
    $("ap-editor-person").title = item.motivo_bloqueio_responsavel || "";
    $("ap-editor-day").value = item.fim_planejado || "";
    $("ap-editor-level").innerHTML = levelBadge(item);
    $("ap-editor-points").textContent = pointsLabel(item);
    $("ap-editor-status").innerHTML = itemStatus(item);
    $("ap-editor-pin").checked = !!item.fixado;
    const transfer = $("ap-editor-transfer");
    transfer.hidden = !item.pode_transferir_execucao;
    transfer.dataset.transferImage = item.entrega_item_id;
    updateEditorAvailability();
  }

  const transferDialog = $("ap-transfer-dialog");
  const transferForm = $("ap-transfer-form");
  const transferPerson = $("ap-transfer-person");
  const transferDate = $("ap-transfer-date");
  const transferReason = $("ap-transfer-reason");
  const transferError = $("ap-transfer-error");
  const transferSubmit = $("ap-transfer-submit");
  const transferCancel = $("ap-transfer-cancel");
  let transferItem = null;

  function openTransfer(id) {
    const item = state.items.find((i) => i.entrega_item_id === id);
    if (!item?.pode_transferir_execucao || state.saving || state.conflict)
      return;
    if (state.dirty) {
      message(
        "Salve o rascunho ou descarte as alterações locais antes de transferir a execução.",
        true,
      );
      return;
    }
    transferItem = item;
    transferForm.reset();
    transferPerson.innerHTML = personOptions(null);
    [...transferPerson.options]
      .find(
        (option) =>
          Number(option.value) === Number(item.responsavel_operacional_id),
      )
      ?.remove();
    const vigente = state.plan.vigente_itens?.find(
      (i) => i.entrega_item_id === id,
    );
    const previsao = date(
      item.execucao.previsao || vigente?.fim_planejado || item.fim_planejado,
    );
    transferDate.min = today();
    transferDate.value = previsao >= transferDate.min ? previsao : "";
    const status = item.execucao.status;
    const responsavel = personName(item.responsavel_operacional_id);
    $("ap-transfer-summary").textContent =
      `${item.imagem_nome} · ${responsavel}. A função manterá o status ${status}.${item.execucao.situacao === "AGUARDANDO_INICIO" ? " O novo responsável poderá iniciar o ajuste quando começar o trabalho." : ""}`;
    transferError.hidden = true;
    transferSubmit.disabled = false;
    transferDialog.showModal();
    transferPerson.focus();
  }

  transferCancel.addEventListener("click", () => transferDialog.close());
  transferDialog.addEventListener("cancel", (event) => {
    if (state.saving) event.preventDefault();
  });
  transferForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!transferItem || state.saving || state.conflict) return;
    const motivo = transferReason.value.trim();
    if (!motivo) {
      transferError.textContent = "Informe o motivo da transferência.";
      transferError.hidden = false;
      transferReason.focus();
      return;
    }
    const item = transferItem;
    const dados = {
      acao: "transferir_execucao",
      entrega_id: entregaId,
      entrega_item_id: item.entrega_item_id,
      ciclo_id: item.execucao.ciclo_id,
      situacao_ciclo: item.execucao.situacao,
      status_tarefa: item.execucao.status,
      novo_responsavel_id: Number(transferPerson.value),
      fim_planejado: transferDate.value,
      motivo,
      lock_version: state.plan.planejamento.lock_version,
      fingerprint: state.plan.fingerprint,
      csrf_token: state.plan.csrf_token,
    };
    state.saving = true;
    transferError.hidden = true;
    const controls = [
      transferPerson,
      transferDate,
      transferReason,
      transferSubmit,
      transferCancel,
    ];
    controls.forEach((control) => (control.disabled = true));
    $("ap-transfer-loading").append($("ap-loading"));
    setLoading(true, "Transferindo execução…");
    try {
      const response = await fetch("alteracao.php", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify(dados),
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        if (response.status === 409) {
          state.conflict = true;
          $("ap-reload").hidden = false;
        }
        throw new Error(
          result.message || "Não foi possível transferir a execução.",
        );
      }
      transferDialog.close();
      state.replanning = false;
      state.saving = false;
      acceptPlan(result.plano);
      message(
        `Execução transferida para ${personName(dados.novo_responsavel_id)}.`,
      );
    } catch (error) {
      transferError.textContent = error.message;
      transferError.hidden = false;
    } finally {
      state.saving = false;
      setLoading(false);
      $("ap-loading-home").append($("ap-loading"));
      controls.forEach((control) => (control.disabled = false));
      transferSubmit.disabled = state.conflict;
      renderSummary();
    }
  });

  $("ap-editor-close").addEventListener("click", () => closeEditor());
  $("ap-editor-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const item = editorItem();
    if (!item || !editable() || !pendingWork(item)) return;
    const change = {
      responsavel_planejado_id: canAssign(item)
        ? Number($("ap-editor-person").value) || null
        : item.responsavel_planejado_id,
      fim_planejado: $("ap-editor-day").value || null,
      fixado: $("ap-editor-pin").checked,
    };
    if (change.fixado && !item.fixado && !complete({ ...item, ...change })) {
      message(
        "A imagem precisa de responsável e dia antes de ser fixada.",
        true,
      );
      return;
    }
    if (
      Object.entries(change).every(
        ([key, value]) =>
          (key === "fixado" ? Boolean(item[key]) : item[key]) === value,
      )
    )
      return;
    if (await applyDecisions(new Map([[state.editorId, change]]))) {
      message("Alterações aplicadas. Salve o rascunho para guardá-las.");
    }
  });
  $("ap-editor-remove").addEventListener("click", async () => {
    const item = editorItem();
    if (!item || !editable() || !pendingWork(item)) return;
    if (
      await applyDecisions(
        new Map([
          [
            state.editorId,
            {
              responsavel_planejado_id: canAssign(item)
                ? null
                : item.responsavel_planejado_id,
              fim_planejado: null,
              inicio_planejado: null,
              fixado: false,
            },
          ],
        ]),
      )
    )
      closeEditor();
  });

  const calendarTab = $("ap-tab-calendar");
  const listTab = $("ap-tab-list");
  function selectTab(tab) {
    const showCalendar = tab === calendarTab;
    calendarTab.setAttribute("aria-selected", String(showCalendar));
    listTab.setAttribute("aria-selected", String(!showCalendar));
    calendarTab.tabIndex = showCalendar ? 0 : -1;
    listTab.tabIndex = showCalendar ? -1 : 0;
    $("ap-calendar-view").hidden = !showCalendar;
    $("ap-list-view").hidden = showCalendar;
    closeEditor(false);
    if (state.plan) renderSelection();
    tab.focus();
  }
  [calendarTab, listTab].forEach((tab) => {
    tab.addEventListener("click", () => selectTab(tab));
    tab.addEventListener("keydown", (event) => {
      const target = {
        ArrowLeft: tab === calendarTab ? listTab : calendarTab,
        ArrowRight: tab === calendarTab ? listTab : calendarTab,
        Home: calendarTab,
        End: listTab,
      }[event.key];
      if (!target) return;
      event.preventDefault();
      selectTab(target);
    });
  });

  const moreDialog = $("ap-more-dialog");
  $("ap-more").addEventListener("click", () => moreDialog.showModal());
  $("ap-more-close").addEventListener("click", () => moreDialog.close());

  const picker = $("ap-picker");
  const pickerSelection = new Set();
  let pickerDestination;
  function renderPicker() {
    const query = $("ap-picker-search").value.trim().toLocaleLowerCase("pt-BR");
    const { person, day } = pickerDestination;
    const items = pendingItems().filter(
      (item) =>
        canMove(item) &&
        (canAssign(item) || item.responsavel_planejado_id === person) &&
        !(
          item.responsavel_planejado_id === person && item.fim_planejado === day
        ) &&
        String(item.imagem_nome).toLocaleLowerCase("pt-BR").includes(query),
    );
    $("ap-picker-items").innerHTML =
      items
        .map((item) => {
          const id = Number(item.entrega_item_id);
          return `<label class="ap-picker-item"><input type="checkbox" value="${id}"${pickerSelection.has(id) ? " checked" : ""}>${thumbnail(item)}<strong>${escape(item.imagem_nome)}</strong>${levelBadge(item)}<small>${pointsLabel(item)}${complete(item) ? " · " + formatDate(item.fim_planejado) : ""}</small></label>`;
        })
        .join("") ||
      '<p class="ap-empty">Nenhuma imagem elegível corresponde à busca.</p>';
    updatePickerSelection();
  }

  function updatePickerSelection() {
    $("ap-picker-apply").disabled = !pickerSelection.size;
    $("ap-picker-apply").textContent = pickerSelection.size
      ? `Adicionar seleção (${pickerSelection.size})`
      : "Adicionar seleção";
  }

  function openPicker(person, day) {
    pickerDestination = { person, day };
    pickerSelection.clear();
    pendingItems().forEach((item) => {
      const id = Number(item.entrega_item_id);
      if (
        state.selected.has(id) &&
        canMove(item) &&
        (canAssign(item) || item.responsavel_planejado_id === person) &&
        !(
          item.responsavel_planejado_id === person && item.fim_planejado === day
        )
      )
        pickerSelection.add(id);
    });
    $("ap-picker-search").value = "";
    $("ap-picker-destination").textContent =
      `${personName(person)} · ${formatDate(day)}`;
    renderPicker();
    picker.showModal();
    $("ap-picker-search").focus();
  }

  $("ap-picker-search").addEventListener("input", renderPicker);
  $("ap-picker-items").addEventListener("change", (event) => {
    if (!event.target.matches('input[type="checkbox"]')) return;
    const id = Number(event.target.value);
    event.target.checked ? pickerSelection.add(id) : pickerSelection.delete(id);
    updatePickerSelection();
  });
  $("ap-picker-close").addEventListener("click", () => picker.close());
  function placeImages(ids, person, day) {
    const selected = new Set(ids);
    const decisions = new Map(
      state.items
        .filter(
          (item) =>
            selected.has(Number(item.entrega_item_id)) &&
            changePlacement(item, person, day),
        )
        .map((item) => [
          Number(item.entrega_item_id),
          {
            responsavel_planejado_id: person,
            fim_planejado: day,
            inicio_planejado: null,
          },
        ]),
    );
    return decisions.size ? applyDecisions(decisions) : false;
  }
  $("ap-picker-apply").addEventListener("click", async () => {
    const { person, day } = pickerDestination;
    picker.close();
    if (await placeImages([...pickerSelection], person, day))
      message(
        "Imagens adicionadas. Salve o rascunho para guardar as alterações.",
      );
  });

  $("alteration-plan").addEventListener("change", (event) => {
    const input = event.target.closest("[data-select]");
    if (!input || !editable()) return;
    const id = Number(input.dataset.select);
    if (!pendingItems().some((item) => Number(item.entrega_item_id) === id))
      return;
    input.checked ? state.selected.add(id) : state.selected.delete(id);
    state.selectionAnchor = id;
    closeEditor(false);
    renderSelection();
  });
  $("ap-select-all").addEventListener("change", (event) => {
    if (!editable()) return;
    visibleItems()
      .filter(pendingWork)
      .forEach((item) =>
        event.target.checked
          ? state.selected.add(Number(item.entrega_item_id))
          : state.selected.delete(Number(item.entrega_item_id)),
      );
    renderSelection();
  });
  $("ap-search").addEventListener("input", () => {
    if (state.plan) {
      renderRows();
    }
  });
  ["ap-pending", "ap-level", "ap-person"].forEach((id) =>
    $(id).addEventListener("change", () => state.plan && renderRows()),
  );
  const bulkDialog = $("ap-bulk-dialog");
  function bulkChanges(remove = false) {
    const person = Number($("ap-bulk-person").value) || null;
    const finish = $("ap-bulk-finish").value;
    const changes = new Map();
    const blocked = [];
    for (const item of selectionItems()) {
      const nextPerson = remove
        ? canAssign(item)
          ? null
          : item.responsavel_planejado_id
        : (person ?? item.responsavel_planejado_id);
      const nextDay = remove ? null : finish || item.fim_planejado;
      if (!changePlacement(item, nextPerson, nextDay)) continue;
      const restriction = !canMove(item)
        ? cardStatus(item)
        : !canAssign(item) && nextPerson !== item.responsavel_planejado_id
          ? item.motivo_bloqueio_responsavel || "Responsável protegido"
          : "";
      if (restriction) {
        blocked.push(`${item.imagem_nome} (${restriction})`);
        continue;
      }
      changes.set(Number(item.entrega_item_id), {
        responsavel_planejado_id: nextPerson,
        fim_planejado: nextDay,
        inicio_planejado:
          nextDay === item.fim_planejado ? item.inicio_planejado : null,
      });
    }
    return { changes, blocked };
  }
  function capacityPreview(
    person,
    day,
    delta,
    originalLoad = cellLoad(person, day),
  ) {
    const cell = capacity(person, day);
    if (!cell || cell.avisos?.length)
      return { text: "Disponibilidade não estimada", tone: "" };
    const total =
      Math.round((originalLoad + Number(cell.comprometidos) + delta) * 100) /
      100;
    const excess = total - Number(cell.capacidade);
    return {
      text: `${num(total)} / ${num(cell.capacidade)} pts${excess > 0 ? ` · excesso de ${num(excess)} pts` : ""}`,
      tone: capacityBadge(total, Number(cell.capacidade)),
    };
  }
  function renderBulkPreview() {
    const { changes, blocked } = bulkChanges();
    const finish = $("ap-bulk-finish").value;
    const invalid = finish && !days().includes(finish);
    const deltas = new Map();
    const adjust = (person, day, delta) => {
      if (!person || !day) return;
      const key = cellKey(person, day);
      deltas.set(key, (deltas.get(key) || 0) + delta);
    };
    selectionItems().forEach((item) => {
      const change = changes.get(Number(item.entrega_item_id));
      if (!change) return;
      adjust(item.responsavel_planejado_id, item.fim_planejado, -points(item));
      adjust(
        change.responsavel_planejado_id,
        change.fim_planejado,
        points(item),
      );
    });
    const preview = [...deltas]
      .filter(([, delta]) => delta)
      .map(([key, delta]) => {
        const [person, day] = key.split("|");
        return `${personName(person)} · ${formatDate(day)} · ${capacityPreview(person, day, delta).text}`;
      });
    $("ap-bulk-preview").textContent =
      [
        invalid ? "Escolha um dia útil dentro da agenda." : "",
        blocked.length
          ? `Imagens bloqueadas: ${blocked.join(", ")}. Ajuste os campos ou a seleção.`
          : "",
        ...preview,
      ]
        .filter(Boolean)
        .join("\n") || "Escolha um dia ou responsável para ver a alteração.";
    $("ap-apply").disabled =
      !changes.size || Boolean(blocked.length || invalid);
  }
  function openBulk(onlyPerson) {
    if (!editable() || !state.selected.size) return;
    closeEditor(false);
    const items = selectionItems();
    $("ap-bulk-title").textContent =
      `${onlyPerson ? "Alterar responsável de" : "Replanejar"} ${items.length} ${items.length === 1 ? "imagem" : "imagens"}`;
    $("ap-bulk-points").textContent =
      `${num(items.reduce((sum, item) => sum + points(item), 0))} pts selecionados`;
    $("ap-bulk-date-field").hidden = onlyPerson;
    $("ap-bulk-finish").value = "";
    $("ap-bulk-person").value = "";
    renderBulkPreview();
    bulkDialog.showModal();
  }
  $("ap-bulk-replan").addEventListener("click", () => openBulk(false));
  $("ap-bulk-assign").addEventListener("click", () => openBulk(true));
  $("ap-bulk-close").addEventListener("click", () => bulkDialog.close());
  $("ap-bulk-fields").addEventListener("change", renderBulkPreview);
  $("ap-bulk-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const { changes, blocked } = bulkChanges();
    if (blocked.length || !changes.size) return;
    bulkDialog.close();
    if (await applyDecisions(changes))
      message("Alterações aplicadas à seleção.");
  });
  $("ap-clear").addEventListener("click", async () => {
    const { changes, blocked } = bulkChanges(true);
    if (blocked.length) {
      message(
        `Imagens bloqueadas: ${blocked.join(", ")}. Ajuste a seleção para remover o planejamento.`,
        true,
      );
      return;
    }
    if (!changes.size) {
      message("As imagens selecionadas ainda não foram planejadas.");
      return;
    }
    if (
      (await confirmAction(
        "Remover do planejamento as imagens selecionadas?",
      )) &&
      (await applyDecisions(changes))
    ) {
      message("Imagens removidas do planejamento.");
    }
  });
  $("ap-deselect").addEventListener("click", clearSelection);
  $("ap-save").addEventListener("click", () => save("rascunho"));
  $("ap-confirm").addEventListener("click", openConfirmation);
  $("ap-confirm-close").addEventListener("click", () =>
    $("ap-confirm-dialog").close(),
  );
  $("ap-reason").addEventListener("change", () => {
    $("ap-note").setCustomValidity("");
    $("ap-note").required = $("ap-reason").value === "OUTRO";
  });
  $("ap-note").addEventListener("input", () =>
    $("ap-note").setCustomValidity(""),
  );
  $("ap-confirm-form").addEventListener("submit", (event) => {
    event.preventDefault();
    if (
      state.replanning &&
      $("ap-reason").value === "OUTRO" &&
      !$("ap-note").value.trim()
    ) {
      $("ap-note").setCustomValidity("Descreva o motivo do replanejamento.");
      $("ap-note").reportValidity();
      return;
    }
    $("ap-confirm-dialog").close();
    void save("confirmar");
  });
  $("ap-replan").addEventListener("click", () => {
    state.replanning = true;
    state.items = state.items.map((i) =>
      pendingWork(i)
        ? {
            ...i,
            nivel_complexidade_snapshot:
              i.nivel_atual ?? i.nivel_complexidade_snapshot,
            pontos_snapshot:
              i.pontos_replanejamento ??
              i.pontos_snapshot ??
              state.plan.configuracao.niveis[level(i)],
          }
        : i,
    );
    message("Replanejamento aberto. O motivo será solicitado na confirmação.");
    renderRows();
    renderSummary();
    renderEditor();
  });
  $("ap-cancel-replan").addEventListener("click", async () => {
    if (
      state.dirty &&
      !(await confirmAction("Descartar as alterações locais do planejamento?"))
    )
      return;
    state.replanning = false;
    state.selected.clear();
    closeEditor(false);
    $("ap-reason").value = "";
    $("ap-note").value = "";
    if (moreDialog.open) moreDialog.close();
    acceptPlan(state.plan);
    message("Alterações locais descartadas.");
  });
  $("ap-reload").addEventListener("click", async () => {
    if (
      !state.dirty ||
      (await confirmAction(
        "Recarregar os dados? As alterações locais não salvas serão descartadas.",
      ))
    )
      load();
  });
  $("ap-history").addEventListener("click", () => {
    if (!state.plan) return;
    if (moreDialog.open) moreDialog.close();
    $("ap-history-title").textContent = "Histórico do planejamento";
    const history = state.plan.planejamento.historico || [];
    $("ap-history-list").innerHTML =
      history
        .map(
          (version) =>
            `<article class="ap-history-entry"><div><strong>${Number(version.id) === Number(state.plan.planejamento.baseline_versao_id) ? "Baseline" : "Versão " + escape(version.numero || version.id)}${Number(version.id) === Number(state.plan.planejamento.versao_atual_id) ? " · Vigente" : ""}</strong><p>${formatDate(version.confirmado_em)}${version.confirmado_por ? " · " + escape(version.confirmado_por) : ""}${version.motivo_codigo ? "<br>" + escape(version.motivo_codigo === "TRANSFERENCIA_EXECUCAO" ? "Transferência de execução" : version.motivo_codigo.replaceAll("_", " ")) : ""}</p></div><button class="ap-button" type="button" data-version="${Number(version.id)}">Ver imagens</button></article>`,
        )
        .join("") ||
      "<p>Nenhuma versão confirmada. A primeira confirmação criará a baseline.</p>";
    $("ap-history-snapshot").replaceChildren();
    $("ap-history-dialog").showModal();
  });
  $("ap-close-history").addEventListener("click", () =>
    $("ap-history-dialog").close(),
  );
  $("ap-realized").addEventListener("click", () => {
    if (!state.plan) return;
    const facts = state.plan.realizado || { itens: [], por_dia: {} };
    const names = new Map(
      state.items.map((i) => [Number(i.entrega_item_id), i.imagem_nome]),
    );
    const planned = (p) =>
      p
        ? `${escape(p.responsavel_planejado_nome || personName(p.responsavel_planejado_id))}<br>${formatDate(p.fim_planejado)}`
        : "Sem versão";
    $("ap-more-dialog").close();
    $("ap-history-title").textContent = "Planejado × executado";
    $("ap-history-list").replaceChildren();
    $("ap-history-snapshot").innerHTML =
      `<div class="ap-table-wrap"><table class="ap-history-table"><thead><tr><th>Imagem</th><th>Baseline</th><th>Plano vigente</th><th>Executor</th><th>Início real</th><th>Conclusão da alteração</th><th>Entrega ao cliente</th><th>Resultado</th></tr></thead><tbody>${facts.itens.map((r) => `<tr><td>${escape(names.get(Number(r.entrega_item_id)))}</td><td>${planned(r.baseline)}</td><td>${planned(r.vigente)}</td><td>${escape(r.executor_nome || "Sem evidência")}</td><td>${formatDate(r.inicio_real)}</td><td>${formatDate(r.conclusao_alteracao)}</td><td>${formatDate(r.entrega_cliente)}</td><td>${escape(r.resultado)}</td></tr>`).join("")}</tbody></table></div><h3>Realizado por dia</h3><div class="ap-table-wrap"><table class="ap-history-table"><thead><tr><th>Colaborador</th><th>Dia</th><th>Pts planejados</th><th>Pts concluídos</th><th>Pendentes na data planejada</th><th>Carga pendente atual</th></tr></thead><tbody>${Object.entries(
        facts.por_dia,
      )
        .flatMap(([id, ds]) =>
          Object.entries(ds).map(
            ([d, v]) =>
              `<tr><td>${escape(personName(id))}</td><td>${formatDate(d)}</td><td>${v.planejados == null ? "Não registrados" : num(v.planejados)}</td><td>${v.concluidos == null ? "Não registrados" : num(v.concluidos)}</td><td>${v.pendentes_do_planejado == null ? "Não registrados" : num(v.pendentes_do_planejado)}</td><td>${v.carga_pendente == null ? "Não registrada" : num(v.carga_pendente)}</td></tr>`,
          ),
        )
        .join(
          "",
        )}</tbody></table></div><p>Conclusão e executor vêm dos ciclos vinculados à rodada. Ausência de evidência não é preenchida com dados de outra revisão.</p>`;
    $("ap-history-dialog").showModal();
  });
  let historyRequest = 0;
  $("ap-history-list").addEventListener("click", async (event) => {
    const button = event.target.closest("[data-version]");
    if (!button) return;
    const request = ++historyRequest;
    $("ap-history-snapshot").textContent = "Carregando versão…";
    try {
      const response = await fetch(
        `history.php?entrega_id=${entregaId}&versao_id=${Number(button.dataset.version)}`,
        { headers: { Accept: "application/json" }, credentials: "same-origin" },
      );
      const result = await response.json();
      if (!response.ok || !result.success)
        throw new Error(result.message || "Não foi possível abrir a versão.");
      if (request !== historyRequest) return;
      const items = result.versao.snapshot?.itens || [];
      const transfer = result.versao.snapshot?.transferencia_execucao;
      const transferSummary = transfer
        ? `<h3>Transferência de execução</h3><p>${escape(transfer.imagem_nome)} · ${escape(transfer.responsavel_anterior_nome || personName(transfer.responsavel_anterior_id))} → ${escape(transfer.novo_responsavel_nome)} · ${escape(transfer.status)}</p><p>${escape(result.versao.motivo_observacao)}</p>`
        : "";
      $("ap-history-snapshot").innerHTML =
        `${transferSummary}<h3>Imagens da versão</h3><div class="ap-table-wrap"><table class="ap-history-table"><thead><tr><th>Imagem</th><th>Nível</th><th>Pontos históricos</th><th>Responsável</th><th>Dia planejado</th></tr></thead><tbody>${items.map((item) => `<tr><td>${escape(item.imagem_nome || `Item ${item.entrega_item_id}`)}</td><td>${levelBadge(item)}</td><td>${item.pontos_snapshot == null ? "Sem snapshot de pontos" : num(item.pontos_snapshot) + " pts"}</td><td>${escape(item.responsavel_planejado_nome || item.responsavel_nome || personName(item.responsavel_planejado_id))}</td><td>${formatDate(item.fim_planejado)}</td></tr>`).join("")}</tbody></table></div>`;
    } catch (error) {
      if (request === historyRequest)
        $("ap-history-snapshot").textContent = error.message;
    }
  });
  $("ap-mobile-day").addEventListener("change", (event) => {
    state.dayIndex = days().indexOf(event.target.value);
    renderPlanner();
  });
  $("ap-day-prev").addEventListener("click", () => {
    state.dayIndex = Math.max(0, state.dayIndex - 5);
    renderPlanner();
  });
  $("ap-day-next").addEventListener("click", () => {
    state.dayIndex += 5;
    renderPlanner();
  });
  $("ap-add-person").addEventListener("click", () => {
    if (!state.plan) return;
    const d = days()[state.dayIndex];
    $("ap-people-list").innerHTML = ["PRINCIPAL", "SECUNDARIA"]
      .map(
        (role) =>
          `<h3>Alteração ${role === "PRINCIPAL" ? "principal" : "secundária"}</h3>` +
          state.plan.colaboradores
            .filter((p) => p.tipo_atuacao === role)
            .map((p) => {
              const a = capacity(p.id, d),
                used = Number(a?.comprometidos || 0) + cellLoad(p.id, d);
              return `<label class="ap-candidate"><input type="checkbox" data-show-person="${Number(p.id)}"${state.people.has(Number(p.id)) ? " checked" : ""}><span><strong>${escape(p.nome)}</strong><small>${roleName(p)} · ${formatDate(d)}</small><small>${a?.avisos?.length ? "Disponibilidade não estimada" : num(Math.max(0, Number(a?.capacidade || 0) - used)) + " pts disponíveis"} · ${num(used)} pts comprometidos</small>${a?.avisos?.length ? "<small>Capacidade efetiva não estimada</small>" : ""}</span></label>`;
            })
            .join(""),
      )
      .join("");
    $("ap-people-dialog").showModal();
  });
  $("ap-close-people").addEventListener("click", () =>
    $("ap-people-dialog").close(),
  );
  function clearSelection() {
    state.selected.clear();
    state.selectionAnchor = null;
    renderSelection();
  }
  function selectImage(trigger, event) {
    const id = Number(trigger.dataset.editImage);
    const item = state.items.find(
      (entry) => Number(entry.entrega_item_id) === id,
    );
    if (!item) return;
    const inRail = $("ap-rail-items").contains(trigger);
    const modifier = event.ctrlKey || event.metaKey;
    if (
      editable() &&
      pendingWork(item) &&
      (modifier || (event.shiftKey && inRail))
    ) {
      closeEditor(false);
      if (event.shiftKey && inRail) {
        const visible = railItems()
          .filter(pendingWork)
          .map((entry) => Number(entry.entrega_item_id));
        const end = visible.indexOf(id);
        const start = visible.includes(state.selectionAnchor)
          ? visible.indexOf(state.selectionAnchor)
          : end;
        if (!modifier) state.selected.clear();
        visible
          .slice(Math.min(start, end), Math.max(start, end) + 1)
          .forEach((value) => state.selected.add(value));
      } else {
        if (state.selected.has(id)) state.selected.delete(id);
        else state.selected.add(id);
        state.selectionAnchor = id;
      }
      renderSelection();
      return;
    }
    if (modifier || event.shiftKey) return;
    if (editable() && pendingWork(item)) {
      state.selected = new Set([id]);
      state.selectionAnchor = id;
    } else {
      state.selected.clear();
    }
    renderSelection();
    openEditor(trigger);
  }
  document.addEventListener("keydown", (event) => {
    if (
      !state.plan ||
      document.querySelector("dialog[open]") ||
      window.Swal?.isVisible?.()
    )
      return;
    const target = event.target;
    if (
      target.closest(
        "select, textarea, input:not([type=checkbox]), [contenteditable=true]",
      )
    )
      return;
    if (event.key === "Escape") {
      if (state.editorId !== null) closeEditor();
      else if (state.selected.size) clearSelection();
      else return;
      event.preventDefault();
    }
    if (
      event.key.toLowerCase() !== "a" ||
      !(event.ctrlKey || event.metaKey) ||
      !editable()
    )
      return;
    const items = target.closest("#ap-rail-items")
      ? railItems()
      : target.closest("#ap-list-view")
        ? visibleItems()
        : null;
    if (!items) return;
    event.preventDefault();
    closeEditor(false);
    state.selected = new Set(
      items.filter(pendingWork).map((item) => Number(item.entrega_item_id)),
    );
    renderSelection();
  });
  document.addEventListener("pointerdown", (event) => {
    if (
      state.editorId === null ||
      event.target.closest("#ap-editor, [data-edit-image]")
    )
      return;
    closeEditor(false);
  });
  $("ap-people-list").addEventListener("change", (event) => {
    const id = Number(event.target.dataset.showPerson);
    if (!id) return;
    if (event.target.checked) state.people.add(id);
    else if (
      state.items.some((i) => Number(i.responsavel_planejado_id) === id)
    ) {
      event.target.checked = true;
      message(
        "Retire ou redistribua as imagens antes de remover o colaborador da grade.",
      );
    } else state.people.delete(id);
    renderPlanner();
  });
  $("alteration-plan").addEventListener(
    "error",
    (event) => {
      if (event.target.matches?.("img.ap-thumb")) {
        const span = document.createElement("span");
        span.className = "ap-thumb ap-thumb-empty";
        span.textContent = "▧";
        span.setAttribute("aria-hidden", "true");
        event.target.replaceWith(span);
      }
    },
    true,
  );
  $("alteration-plan").addEventListener("click", (event) => {
    const transfer = event.target.closest("[data-transfer-image]");
    if (transfer) {
      openTransfer(Number(transfer.dataset.transferImage));
      return;
    }
    const edit = event.target.closest("[data-edit-image]");
    if (edit) {
      selectImage(edit, event);
      return;
    }
    const external = event.target.closest("a.ap-external");
    if (
      external &&
      state.dirty &&
      !event.ctrlKey &&
      !event.metaKey &&
      !event.shiftKey
    ) {
      event.preventDefault();
      void confirmAction(
        "Abrir a entrega de origem e descartar as alterações locais?",
      ).then((accepted) => {
        if (!accepted) return;
        state.dirty = false;
        location.assign(external.href);
      });
      return;
    }
    const place = event.target.closest("[data-place-person]");
    const remove = event.target.closest("[data-remove-person]");
    const agenda = event.target.closest("[data-agenda]");
    if (agenda) {
      const id = Number(agenda.dataset.agenda);
      $("ap-history-title").textContent = "Agenda de Alterações";
      $("ap-history-list").replaceChildren();
      const settings = state.plan.pode_configurar_capacidade
        ? '<a class="ap-button" href="../PlanejamentoCapacidade/#alteracao-config" target="_blank" rel="noopener">Configurar capacidade</a>'
        : "";
      $("ap-history-snapshot").innerHTML =
        `<h3>Agenda de ${escape(personName(id))}</h3>${settings}<div class="ap-table-wrap"><table class="ap-history-table"><thead><tr><th>Dia</th><th>Nominal</th><th>Oferta efetiva</th><th>Outras alterações</th><th>Esta entrega</th><th>Contexto</th></tr></thead><tbody>${days()
          .map((d) => {
            const a = capacity(id, d);
            if (!a) return "";
            return `<tr><td>${formatDate(d)}</td><td>${num(a.nominal)}</td><td>${num(a.capacidade)}</td><td>${num(a.comprometidos)}</td><td>${num(cellLoad(id, d))}</td><td>${escape(a.avisos.join("; "))}${a.ocupacao_r00 ? " R00: " + num(a.ocupacao_r00 * 100) + "%" : ""}</td></tr>`;
          })
          .join("")}</tbody></table></div>`;
      $("ap-history-dialog").showModal();
      return;
    }
    if (!editable()) return;
    if (place) {
      openPicker(Number(place.dataset.placePerson), place.dataset.placeDay);
    } else if (remove) {
      const id = Number(remove.dataset.removePerson);
      if (state.items.some((i) => Number(i.responsavel_planejado_id) === id)) {
        message(
          "Retire ou redistribua as imagens antes de remover o colaborador da grade.",
        );
        return;
      }
      state.people.delete(id);
      renderPlanner();
    }
  });
  const grid = $("ap-grid");
  let draggedItems = [];
  let hoveredCell = null;

  function highlightCell(cell) {
    if (hoveredCell === cell) return;
    hoveredCell?.classList.remove(
      "is-drop-hover",
      "is-drop-excess",
      "is-drop-near",
    );
    hoveredCell?.querySelector(".ap-drop-preview")?.remove();
    hoveredCell = cell;
    if (!cell) return;
    const person = Number(cell.dataset.dropPerson);
    const day = cell.dataset.dropDay;
    const moving = draggedItems.filter((item) =>
      changePlacement(item, person, day),
    );
    const added = moving.reduce((sum, item) => sum + points(item), 0);
    const result = capacityPreview(person, day, added);
    const preview = document.createElement("span");
    preview.className = "ap-drop-preview";
    preview.textContent = moving.length
      ? `+${num(added)} pts → ${result.text}`
      : `Sem mudança · ${result.text}`;
    cell.classList.add("is-drop-hover");
    if (result.tone)
      cell.classList.add(
        result.tone === "is-overloaded" ? "is-drop-excess" : "is-drop-near",
      );
    cell.querySelector(".ap-load").append(preview);
  }

  function clearDrag() {
    highlightCell(null);
    grid
      .querySelectorAll(".is-drop-target")
      .forEach((cell) => cell.classList.remove("is-drop-target"));
    draggedItems = [];
  }

  $("alteration-plan").addEventListener("dragstart", (event) => {
    const el = event.target.closest("[data-card]");
    if (!el || !editable()) return event.preventDefault();
    const id = Number(el.dataset.card);
    clearDrag();
    const ids = state.selected.has(id) ? state.selected : new Set([id]);
    draggedItems = state.items.filter((item) =>
      ids.has(Number(item.entrega_item_id)),
    );
    if (!draggedItems.length || !draggedItems.every(canMove)) {
      clearDrag();
      message(
        "Desmarque as imagens bloqueadas ou desafixe-as antes de mover o grupo.",
      );
      return event.preventDefault();
    }
    closeEditor(false);
    event.dataTransfer.setData("application/x-flow-images", String(id));
    event.dataTransfer.effectAllowed = "move";
    grid.querySelectorAll("[data-drop-person]").forEach((cell) => {
      const person = Number(cell.dataset.dropPerson);
      if (
        draggedItems.every(
          (item) => canAssign(item) || item.responsavel_planejado_id === person,
        )
      ) {
        cell.classList.add("is-drop-target");
      }
    });
  });
  document.addEventListener("dragend", clearDrag);
  function allowDrop(event) {
    const cell = editable() ? event.target.closest(".is-drop-target") : null;
    highlightCell(cell);
    if (!cell) return;
    event.preventDefault();
    event.dataTransfer.dropEffect = "move";
  }
  grid.addEventListener("dragenter", allowDrop);
  grid.addEventListener("dragover", allowDrop);
  grid.addEventListener("dragleave", (event) => {
    if (!grid.contains(event.relatedTarget)) highlightCell(null);
  });
  grid.addEventListener("drop", async (event) => {
    const cell = event.target.closest(".is-drop-target");
    const items = draggedItems;
    clearDrag();
    if (!cell || !editable() || !items.length) return;
    event.preventDefault();
    if (
      await placeImages(
        items.map((item) => Number(item.entrega_item_id)),
        Number(cell.dataset.dropPerson),
        cell.dataset.dropDay,
      )
    )
      message(items.length === 1 ? "Imagem movida." : "Imagens movidas.");
  });
  $("ap-type-rules").addEventListener("change", (event) => {
    const select = event.target.closest("select[data-type-rule]");
    if (!select) return;
    if (select.value)
      state.typeRules[select.dataset.typeRule] = Number(select.value);
    else delete state.typeRules[select.dataset.typeRule];
  });
  $("ap-type-priority-enabled").addEventListener("change", (event) => {
    state.priorityEnabled = event.target.checked;
    renderPlanner();
  });
  $("ap-type-priority").addEventListener("click", (event) => {
    const button = event.target.closest("button[data-priority-move]");
    if (!button || !editable() || !state.priorityEnabled) return;
    const index = Number(button.dataset.priorityIndex),
      target = index + Number(button.dataset.priorityMove);
    if (target < 0 || target >= state.typePriority.length) return;
    [state.typePriority[index], state.typePriority[target]] = [
      state.typePriority[target],
      state.typePriority[index],
    ];
    renderPlanner();
    $("ap-type-priority")
      .querySelector(
        `button[data-priority-index="${target}"][data-priority-move="${button.dataset.priorityMove}"]`,
      )
      ?.focus();
  });
  $("ap-suggest").addEventListener("click", async () => {
    if (!editable()) return;
    moreDialog.close();
    state.saving = true;
    state.suggesting = true;
    renderSummary();
    setLoading(true, "Gerando sugestão…");
    try {
      const response = await fetch("alteracao.php", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          acao: "sugerir",
          entrega_id: entregaId,
          csrf_token: state.plan.csrf_token,
          lock_version: state.plan.planejamento.lock_version,
          fingerprint: state.plan.fingerprint,
          capacidade_fingerprint: state.plan.capacidade.fingerprint,
          ordem_complexidade: $("ap-suggest-order").value,
          responsaveis_por_tipo: state.typeRules,
          prioridade_tipos: state.priorityEnabled ? state.typePriority : [],
          colaboradores: [...state.people],
          itens: pendingItems(),
        }),
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        if (response.status === 409) {
          state.conflict = true;
          $("ap-reload").hidden = false;
        }
        throw new Error(result.message || "Não foi possível gerar a sugestão.");
      }
      const suggested = new Map(
        result.sugestao.itens.map((i) => [Number(i.entrega_item_id), i]),
      );
      state.items = state.items.map((i) => ({
        ...i,
        ...(pendingWork(i) && canAssign(i)
          ? suggested.get(Number(i.entrega_item_id))
          : {}),
      }));
      const firstDay = state.items
        .map((i) => i.fim_planejado)
        .filter(Boolean)
        .sort()[0];
      if (firstDay) state.dayIndex = Math.max(0, days().indexOf(firstDay));
      state.dirty = true;
      renderEditor();
      message(result.sugestao.mensagem);
    } catch (error) {
      message(error.message, true);
    } finally {
      state.saving = false;
      state.suggesting = false;
      setLoading(false);
      renderRows();
      renderSummary();
    }
  });
  $("ap-theme").addEventListener("click", () => {
    const theme =
      document.documentElement.dataset.theme === "light" ? "dark" : "light";
    document.documentElement.dataset.theme = theme;
    document.body.classList.toggle("light", theme === "light");
    try {
      localStorage.setItem("theme", theme);
    } catch (_) {
      /* Storage is optional. */
    }
  });
  new MutationObserver(() =>
    document.body.classList.toggle(
      "light",
      document.documentElement.dataset.theme === "light",
    ),
  ).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ["data-theme"],
  });
  window.addEventListener("beforeunload", (event) => {
    if (state.dirty) {
      event.preventDefault();
      event.returnValue = "";
    }
  });
  window.thinkingOrbs?.($("alteration-plan"));
  load();
})();
