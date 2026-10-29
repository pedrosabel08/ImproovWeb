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
    dayCount: 3,
    backlogLevel: "",
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
      : validPerson(item.responsavel_planejado_id);
  const complete = (item) =>
    hasResponsible(item) &&
    validDate(item.fim_planejado) &&
    ((confirmed() && !state.replanning) ||
      (Number(item.pontos_snapshot) > 0 &&
        Boolean(
          state.plan.capacidade?.agenda?.[item.responsavel_planejado_id]?.[
            item.fim_planejado
          ],
        )));
  const confirmed = () => Boolean(state.plan?.planejamento?.versao_atual_id);
  const editable = () =>
    !!state.plan &&
    state.plan.pode_editar !== false &&
    (!confirmed() || state.replanning) &&
    !state.saving &&
    !state.conflict;
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
  const capacity = (id, d) => state.plan.capacidade?.agenda?.[id]?.[d];
  function cellLoad(id, d, items = state.items) {
    return items
      .filter(
        (i) =>
          Number(i.responsavel_planejado_id) === Number(id) &&
          i.fim_planejado === d,
      )
      .reduce((sum, i) => sum + points(i), 0);
  }
  function excessCells(items = state.items) {
    const found = [];
    const keys = new Set(
      items
        .filter((i) => i.responsavel_planejado_id && i.fim_planejado)
        .map((i) => `${i.responsavel_planejado_id}|${i.fim_planejado}`),
    );
    for (const key of keys) {
      const [id, d] = key.split("|"),
        a = capacity(id, d);
      if (!a) continue;
      const excess =
        Math.round(
          (cellLoad(id, d, items) +
            Number(a.comprometidos) -
            Number(a.capacidade)) *
            100,
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
  function applyDecisions(decisions) {
    if (!editable()) return false;
    const next = state.items.map((i) => {
      const change = decisions.get(Number(i.entrega_item_id));
      return change ? { ...i, ...change, excecao_capacidade: null } : { ...i };
    });
    if (
      next.some((i) => i.fim_planejado && !days().includes(i.fim_planejado))
    ) {
      message("Escolha um dia útil dentro da agenda disponível.", true);
      renderRows();
      return false;
    }
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
          !window.confirm(
            `${personName(s.colaborador_id)} · ${formatDate(s.dia)}: capacidade excedida em ${num(s.excesso)} pts. Planejar mesmo assim?`,
          )
        ) {
          renderRows();
          renderPlanner();
          return false;
        }
        const item = next.find(
          (i) =>
            Number(i.responsavel_planejado_id) === s.colaborador_id &&
            i.fim_planejado === s.dia,
        );
        item.excecao_capacidade = s;
      }
    }
    state.items = next;
    next.forEach((i) => {
      if (validPerson(i.responsavel_planejado_id))
        state.people.add(Number(i.responsavel_planejado_id));
    });
    markDirty();
    renderRows();
    return true;
  }
  const thumbnail = (item) =>
    item.thumbnail_url
      ? `<img class="ap-thumb" src="${escape(item.thumbnail_url)}" alt="" loading="lazy">`
      : '<span class="ap-thumb ap-thumb-empty" aria-hidden="true">▧</span>';
  function card(item, backlog = false) {
    const id = Number(item.entrega_item_id),
      name = escape(item.imagem_nome),
      dis = editable() ? "" : " disabled";
    return `<article class="ap-card${state.selected.has(id) ? " is-selected" : ""}" data-card="${id}" draggable="${editable()}"><span class="ap-drag" aria-hidden="true">⠿</span><input type="checkbox" data-card-select="${id}" aria-label="Selecionar ${name}"${state.selected.has(id) ? " checked" : ""}${dis}>${thumbnail(item)}<div><strong>${name}</strong><small>N${escape(level(item))} · ${pointsLabel(item)}${backlog && complete(item) ? " · Planejada" : ""}</small></div>${!backlog ? `<button type="button" class="ap-card-lock" data-pin="${id}" aria-label="${item.fixado ? "Desafixar" : "Fixar"} ${name}" aria-pressed="${!!item.fixado}"${dis}>${item.fixado ? "◆" : "◇"}</button><button type="button" class="ap-card-remove" data-unplan="${id}" aria-label="Retirar ${name} do planejamento"${dis}>×</button>` : ""}</article>`;
  }
  function renderPlanner() {
    if (!state.plan) return;
    const allDays = days(),
      mobile = window.matchMedia("(max-width: 600px)").matches;
    state.dayIndex = Math.min(
      Math.max(0, state.dayIndex),
      Math.max(0, allDays.length - 1),
    );
    const visibleDays = allDays.slice(
      state.dayIndex,
      state.dayIndex + (mobile ? 1 : state.dayCount),
    );
    $("ap-mobile-day").innerHTML = allDays
      .map(
        (d) =>
          `<option value="${d}"${d === allDays[state.dayIndex] ? " selected" : ""}>${formatDate(d)}</option>`,
      )
      .join("");
    $("ap-period-label").textContent = visibleDays.length
      ? `${formatDate(visibleDays[0])} a ${formatDate(visibleDays.at(-1))}`
      : "Nenhum dia útil";
    $("ap-day-prev").disabled = state.dayIndex === 0;
    $("ap-day-next").disabled =
      state.dayIndex + visibleDays.length >= allDays.length;
    $("ap-more-days").disabled = mobile || state.dayCount >= 10;
    const total = state.items.reduce((s, i) => s + points(i), 0),
      ready = state.items.filter(complete);
    const unknownPoints = state.items.some((i) => i.pontos_snapshot == null);
    $("ap-demand").textContent = unknownPoints
      ? `Pontos não registrados · ${state.items.length} imagens`
      : `${num(total)} pontos · ${state.items.length} imagens`;
    $("ap-allocated").textContent = unknownPoints
      ? `${ready.length} imagens planejadas · sem snapshot de pontos`
      : `${num(ready.reduce((s, i) => s + points(i), 0))} / ${num(total)} pts alocados`;
    $("ap-level-summary").innerHTML = [1, 2, 3, 4, 5]
      .map((n) => {
        const items = state.items.filter((i) => Number(level(i)) === n);
        return `<div><span class="ap-level" data-level="${n}">N${n}</span><small>${items.length} imagens</small><strong>${items.some((i) => i.pontos_snapshot == null) ? "Sem pontos" : num(items.reduce((s, i) => s + points(i), 0)) + " pts"}</strong></div>`;
      })
      .join("");
    const finish = ready.length
      ? ready
          .map((i) => i.fim_planejado)
          .sort()
          .at(-1)
      : null;
    const due = date(state.plan.entrega.data_prevista),
      completePlan = ready.length === state.items.length && ready.length > 0;
    $("ap-forecast").textContent = finish
      ? formatDate(finish) + (completePlan ? "" : " · parcial")
      : "—";
    const margin =
      finish && due
        ? days().filter(
            (d) =>
              d > (finish <= due ? finish : due) &&
              d <= (finish <= due ? due : finish),
          ).length
        : null;
    $("ap-margin").textContent =
      margin === null
        ? "Planeje as imagens para calcular a margem"
        : finish <= due
          ? `Dentro do prazo · margem ${margin} dias úteis`
          : `Após o prazo · ${margin} dias úteis`;
    $("ap-margin").className =
      finish && finish > due ? "ap-late" : "ap-healthy";
    const pending = state.items.filter((i) => !complete(i));
    $("ap-backlog-count").textContent = `${pending.length} não planejadas`;
    $("ap-backlog-levels").innerHTML = ["", "1", "2", "3", "4", "5"]
      .map(
        (n) =>
          `<button type="button" class="ap-button${state.backlogLevel === n ? " is-active" : ""}" data-backlog-level="${n}" aria-pressed="${state.backlogLevel === n}">${n ? "N" + n : "Todas"} ${state.items.filter((i) => !n || level(i) === n).length}</button>`,
      )
      .join("");
    const q = $("ap-backlog-search").value.trim().toLocaleLowerCase("pt-BR");
    const backlog = state.items.filter(
      (i) =>
        (!$("ap-backlog-pending").checked || !complete(i)) &&
        (!state.backlogLevel || level(i) === state.backlogLevel) &&
        (!q || i.imagem_nome.toLocaleLowerCase("pt-BR").includes(q)),
    );
    $("ap-backlog").innerHTML =
      [5, 4, 3, 2, 1, 0]
        .map((n) => {
          const items = backlog.filter((i) => Number(level(i)) === n);
          return items.length
            ? `<div class="ap-backlog-group"><h3>${n ? "N" + n : "Sem nível"} · ${items.some((i) => i.pontos_snapshot == null) ? "pontos não registrados" : num(items.reduce((s, i) => s + points(i), 0)) + " pontos"} <small>(${items.length} imagens)</small></h3>${items.map((i) => card(i, true)).join("")}</div>`
            : "";
        })
        .join("") || '<p class="ap-empty">Nenhuma imagem neste filtro.</p>';
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
    $("ap-grid").style.setProperty("--ap-days", visibleDays.length || 1);
    $("ap-grid").innerHTML =
      `<div class="ap-grid-corner">Colaborador</div>${visibleDays.map((d) => `<div class="ap-grid-day"><strong>${formatDate(d).slice(0, 5)}</strong><small>${new Date(d + "T12:00:00").toLocaleDateString("pt-BR", { weekday: "long" })}</small></div>`).join("")}` +
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
            `<div class="ap-grid-person"><span class="ap-avatar">${escape(p.nome.slice(0, 1))}</span><strong>${escape(p.nome)}</strong><small class="ap-role${p.tipo_atuacao === "SECUNDARIA" || p.historico ? " is-secondary" : ""}">${personRole}</small><small>${p.historico ? "Responsável histórico" : num(nominal) + " pts/dia nominais"}</small>${!p.historico ? `<button type="button" class="ap-button" data-agenda="${id}">Ver agenda</button><button class="ap-person-remove" type="button" data-remove-person="${id}"${editable() ? "" : " disabled"}>Remover da grade</button>` : ""}</div>` +
            visibleDays
              .map((d) => {
                const a = capacity(id, d),
                  load = cellLoad(id, d),
                  committed = Number(a?.comprometidos || 0),
                  cap = Number(a?.capacidade || 0),
                  used = load + committed,
                  ratio = cap > 0 ? used / cap : used ? 2 : 0;
                const cls =
                  ratio > 1
                    ? " is-overloaded"
                    : ratio === 1
                      ? " is-full"
                      : ratio >= 0.8
                        ? " is-warning"
                        : "";
                const items = state.items.filter(
                  (i) =>
                    Number(i.responsavel_planejado_id) === id &&
                    i.fim_planejado === d,
                );
                const unknownLoad = items.some(
                    (i) => i.pontos_snapshot == null,
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
                return `<div class="ap-grid-cell${!unknownOffer && !unknownLoad ? cls : ""}"${p.historico ? "" : ` data-drop-person="${id}" data-drop-day="${d}"`}><strong class="ap-cell-load">${loadLabel}</strong>${!unknownOffer && !unknownLoad ? `<meter min="0" max="${cap || 1}" value="${Math.min(used, cap || 1)}" aria-label="Capacidade de ${escape(p.nome)} em ${formatDate(d)}"></meter>` : ""}<small>${offerLabel}${committed ? " · " + num(committed) + " pts de outras entregas" : ""}</small>${ratio > 1 && a ? `<span class="ap-late">Sobrecarga conhecida: ${num(used - cap)} pts</span>` : ""}${a?.ocupacao_r00 ? `<small>R00: ${num(a.ocupacao_r00 * 100)}% do dia</small>` : ""}${(a?.avisos || []).map((s) => `<small class="ap-capacity-warning">${escape(s)}</small>`).join("")}${items.map((i) => card(i)).join("")}${!p.historico ? `<button type="button" class="ap-add-image" data-place-person="${id}" data-place-day="${d}"${editable() ? "" : " disabled"}>+ Adicionar seleção</button>` : ""}</div>`;
              })
              .join("")
          );
        })
        .join("") +
      (!people.length
        ? '<p class="ap-empty">Adicione um colaborador para planejar as imagens.</p>'
        : "");
    const usedIds = new Set(
      ready.map((i) => Number(i.responsavel_planejado_id)),
    );
    $("ap-team-capacity").textContent = [...usedIds].some(
      (id) => !validPerson(id),
    )
      ? `${usedIds.size} ${usedIds.size === 1 ? "colaborador utilizado" : "colaboradores utilizados"} · capacidade histórica não estimada`
      : `${usedIds.size} ${usedIds.size === 1 ? "colaborador utilizado" : "colaboradores utilizados"} · ${num([...usedIds].reduce((sum, id) => sum + Number(state.plan.configuracao?.colaboradores?.[id] ?? state.plan.configuracao?.capacidade_padrao ?? 0), 0))} pts/dia nominais`;
    $("ap-grid-summary").textContent =
      `${ready.length}/${state.items.length} imagens · conclusão ${formatDate(finish)}`;
    $("ap-suggest").disabled = !editable() || !state.people.size;
    $("ap-suggest-order").disabled = !editable() || !state.people.size;
    const types = [
      ...new Set(state.items.map((i) => i.tipo_imagem).filter(Boolean)),
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
    return state.items.filter(
      (item) =>
        (!query ||
          String(item.imagem_nome)
            .toLocaleLowerCase("pt-BR")
            .includes(query)) &&
        (!pending ||
          (pending === "responsavel"
            ? !hasResponsible(item)
            : !validDate(item.fim_planejado))) &&
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
  function message(text, error = false) {
    $("ap-message").hidden = !text;
    $("ap-message").textContent = text;
    $("ap-message").classList.toggle("is-error", error);
    $("ap-message").setAttribute("role", error ? "alert" : "status");
  }
  function setLoading(
    loading,
    label = "Carregando planejamento…",
    suggestion = false,
  ) {
    const slot =
      suggestion && loading ? $("ap-suggestion-loading") : $("ap-loading-home");
    if ($("ap-loading").parentElement !== slot)
      slot.appendChild($("ap-loading"));
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
    const disabled = editable() ? "" : " disabled";
    $("ap-rows").innerHTML = items
      .map((item) => {
        const id = Number(item.entrega_item_id);
        const name = escape(item.imagem_nome || `Imagem ${item.imagem_id}`);
        return `<tr data-id="${id}" class="${state.selected.has(id) ? "is-selected" : ""}"><td><input type="checkbox" data-select="${id}" aria-label="Selecionar ${name}"${state.selected.has(id) ? " checked" : ""}${disabled}></td><td><div class="ap-table-image">${thumbnail(item)}<span>${name}</span></div></td><td>${levelBadge(item)}</td><td data-label="Responsável"><select data-field="responsavel_planejado_id" aria-label="Responsável de ${name}"${disabled}>${personOptions(item.responsavel_planejado_id)}</select></td><td data-label="Pontos">${pointsLabel(item)}</td><td data-label="Dia planejado"><input type="date" data-field="fim_planejado" aria-label="Dia planejado de ${name}" value="${escape(item.fim_planejado || "")}"${disabled}></td><td><span data-item-status>${itemStatus(item)}</span> <button type="button" class="ap-card-lock" data-pin="${id}" aria-label="${item.fixado ? "Desafixar" : "Fixar"} ${name}" aria-pressed="${!!item.fixado}"${disabled}>${item.fixado ? "◆" : "◇"}</button></td></tr>`;
      })
      .join("");
    renderSelection();
  }
  function itemStatus(item) {
    const ready = complete(item);
    return `<span class="ap-item-state${ready ? " is-complete" : ""}">${ready ? (confirmed() && !state.replanning ? "Planejada" : "Completa") : item.inicio_planejado && item.fim_planejado && item.inicio_planejado > item.fim_planejado ? "Rever datas" : "Pendente"}</span>`;
  }
  function renderSelection() {
    const items = visibleItems();
    const count = items.filter((item) =>
      state.selected.has(Number(item.entrega_item_id)),
    ).length;
    $("ap-select-all").checked = items.length > 0 && count === items.length;
    $("ap-select-all").indeterminate = count > 0 && count < items.length;
    $("ap-select-all").disabled = !editable() || !items.length;
    $("ap-selection").textContent = `${state.selected.size} selecionadas`;
    $("ap-apply").disabled = !editable() || !state.selected.size;
    $("ap-clear").disabled = !editable() || !state.selected.size;
  }
  function renderSummary() {
    const count = state.items.filter(complete).length;
    const withoutPerson = state.items.filter(
      (item) => !hasResponsible(item),
    ).length;
    const withoutDate = state.items.filter(
      (item) => !validDate(item.fim_planejado),
    ).length;
    $("ap-complete").textContent = `${count}/${state.items.length} completos`;
    $("ap-progress").max = state.items.length || 1;
    $("ap-progress").value = count;
    $("ap-status").textContent =
      confirmed() && !state.replanning
        ? "Planejada"
        : state.replanning
          ? "Replanejamento em rascunho"
          : count === 0
            ? "Planejamento pendente"
            : count === state.items.length
              ? "Pronto para confirmar"
              : "Planejamento parcial";
    $("ap-missing").textContent =
      `${withoutPerson} sem responsável · ${withoutDate} sem dia planejado`;
    $("ap-save-status").textContent = state.suggesting
      ? "Gerando sugestão…"
      : state.saving
        ? "Salvando…"
        : state.dirty
          ? "Alterações não salvas"
          : confirmed() && !state.replanning
            ? "Versão confirmada. Histórico preservado."
            : "Rascunho salvo";
    $("ap-save").hidden = $("ap-confirm").hidden =
      confirmed() && !state.replanning;
    $("ap-save").disabled = !editable();
    $("ap-confirm").disabled =
      !editable() || count !== state.items.length || !state.items.length;
    $("ap-replan").hidden = !confirmed() || state.replanning;
    $("ap-replan").disabled =
      state.plan.pode_editar === false || state.saving || state.conflict;
    $("ap-replan-fields").hidden = !state.replanning;
    $("ap-bulk").disabled = !editable();
    renderDistribution();
    renderSelection();
    renderPlanner();
  }
  function renderDistribution() {
    const groups = new Map();
    state.items.forEach((item) => {
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
    renderSummary();
  }
  function acceptPlan(plan) {
    const firstLoad = !state.plan;
    state.plan = plan;
    if (
      plan.planejamento.estado === "REPLANEJAMENTO" ||
      (plan.planejamento.versao_atual_id && plan.planejamento.rascunho_pendente)
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
    if (firstLoad) {
      const scheduled = state.items
        .map((i) => i.fim_planejado)
        .filter(Boolean)
        .sort()[0];
      if (scheduled) state.dayIndex = Math.max(0, days().indexOf(scheduled));
    }
    state.selected.clear();
    state.dirty = false;
    state.conflict = false;
    const e = plan.entrega;
    $("ap-project").textContent =
      `${e.nomenclatura || "Projeto"} · ${e.etapa || "Alteração"}`;
    $("ap-received").textContent = formatDate(e.data_recebimento);
    $("ap-due").textContent = formatDate(e.data_prevista);
    $("ap-person").innerHTML =
      '<option value="">Todos</option><option value="none">Sem responsável</option>' +
      personOptions(null).replace('<option value="">Selecione</option>', "");
    $("ap-bulk-person").innerHTML = personOptions(null, "Manter responsável");
    $("ap-content").hidden = false;
    $("ap-reload").hidden = true;
    renderRows();
    renderSummary();
    if (plan.desatualizado)
      message(
        "O contexto da entrega mudou desde a versão confirmada. Revise as imagens e confirme um replanejamento antes de continuar.",
        true,
      );
  }
  async function load() {
    setLoading(true);
    message("");
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
      state.replanning = false;
      acceptPlan(result.plano || result);
    } catch (error) {
      message(error.message, true);
      $("ap-reload").hidden = false;
    } finally {
      setLoading(false);
    }
  }
  async function save(action) {
    if (!editable()) return;
    if (action === "confirmar" && state.replanning && !$("ap-reason").value) {
      message("Selecione o motivo do replanejamento.", true);
      $("ap-reason").focus();
      return;
    }
    if (
      action === "confirmar" &&
      state.replanning &&
      $("ap-reason").value === "OUTRO" &&
      !$("ap-note").value.trim()
    ) {
      message("Descreva o motivo do replanejamento.", true);
      $("ap-note").focus();
      return;
    }
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
    if (
      action === "confirmar" &&
      !window.confirm(
        confirmed()
          ? `${movedDays} imagens mudaram de dia; ${movedPeople} mudaram de responsável. Confirmar a nova versão e aplicar os responsáveis? A baseline será preservada.`
          : "Confirmar o planejamento e aplicar os responsáveis às tarefas desta entrega?",
      )
    )
      return;
    state.saving = true;
    renderRows();
    renderSummary();
    message("");
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
          itens: state.items.map(
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
      if (action === "confirmar") state.replanning = false;
      state.saving = false;
      acceptPlan(result.plano);
      message(
        action === "confirmar"
          ? "Planejamento confirmado. Os responsáveis foram aplicados às tarefas."
          : "Rascunho salvo.",
      );
    } catch (error) {
      message(error.message, true);
    } finally {
      state.saving = false;
      setLoading(false);
      renderRows();
      renderSummary();
    }
  }
  $("ap-rows").addEventListener("change", (event) => {
    if (!editable()) return;
    const target = event.target;
    const row = target.closest("tr[data-id]");
    if (!row) return;
    const id = Number(row.dataset.id);
    if (target.matches("[data-select]")) {
      target.checked ? state.selected.add(id) : state.selected.delete(id);
      row.classList.toggle("is-selected", target.checked);
      renderSelection();
      renderPlanner();
      return;
    }
    const item = state.items.find(
      (entry) => Number(entry.entrega_item_id) === id,
    );
    const field = target.dataset.field;
    if (
      !item ||
      ![
        "responsavel_planejado_id",
        "inicio_planejado",
        "fim_planejado",
      ].includes(field)
    )
      return;
    applyDecisions(
      new Map([
        [
          id,
          {
            [field]:
              field === "responsavel_planejado_id"
                ? Number(target.value) || null
                : target.value || null,
          },
        ],
      ]),
    );
  });
  $("ap-select-all").addEventListener("change", (event) => {
    if (!editable()) return;
    visibleItems().forEach((item) =>
      event.target.checked
        ? state.selected.add(Number(item.entrega_item_id))
        : state.selected.delete(Number(item.entrega_item_id)),
    );
    renderRows();
    renderPlanner();
  });
  $("ap-search").addEventListener("input", () => state.plan && renderRows());
  ["ap-pending", "ap-level", "ap-person"].forEach((id) =>
    $(id).addEventListener("change", () => state.plan && renderRows()),
  );
  $("ap-apply").addEventListener("click", () => {
    if (!editable()) return;
    const person = Number($("ap-bulk-person").value) || null;
    const start = $("ap-bulk-start").value;
    const finish = $("ap-bulk-finish").value;
    if (!person && !start && !finish) {
      message("Escolha ao menos um responsável ou uma data para aplicar.");
      return;
    }
    const changes = new Map();
    state.selected.forEach((id) =>
      changes.set(id, {
        ...(person ? { responsavel_planejado_id: person } : {}),
        ...(start ? { inicio_planejado: start } : {}),
        ...(finish ? { fim_planejado: finish } : {}),
      }),
    );
    if (applyDecisions(changes))
      message(
        "Decisões aplicadas à seleção. Salve o rascunho para guardá-las.",
      );
  });
  $("ap-clear").addEventListener("click", () => {
    applyDecisions(
      new Map(
        [...state.selected].map((id) => [
          id,
          {
            responsavel_planejado_id: null,
            inicio_planejado: null,
            fim_planejado: null,
            fixado: false,
          },
        ]),
      ),
    );
  });
  $("ap-save").addEventListener("click", () => save("rascunho"));
  $("ap-confirm").addEventListener("click", () => save("confirmar"));
  $("ap-replan").addEventListener("click", () => {
    state.replanning = true;
    state.items = state.items.map((i) => ({
      ...i,
      nivel_complexidade_snapshot:
        i.nivel_atual ?? i.nivel_complexidade_snapshot,
      pontos_snapshot:
        i.pontos_replanejamento ??
        i.pontos_snapshot ??
        state.plan.configuracao.niveis[level(i)],
    }));
    message(
      "Revise as decisões e informe o motivo. A versão confirmada permanece vigente até a nova confirmação.",
    );
    renderRows();
    renderSummary();
  });
  $("ap-cancel-replan").addEventListener("click", () => {
    if (
      state.dirty &&
      !window.confirm("Descartar as alterações locais deste replanejamento?")
    )
      return;
    state.replanning = false;
    acceptPlan(state.plan);
    message("");
  });
  $("ap-reload").addEventListener("click", () => {
    if (
      !state.dirty ||
      window.confirm(
        "Recarregar os dados? As alterações locais não salvas serão descartadas.",
      )
    )
      load();
  });
  $("ap-history").addEventListener("click", () => {
    if (!state.plan) return;
    $("ap-history-title").textContent = "Histórico do planejamento";
    const history = state.plan.planejamento.historico || [];
    $("ap-history-list").innerHTML =
      history
        .map(
          (version) =>
            `<article class="ap-history-entry"><div><strong>${Number(version.id) === Number(state.plan.planejamento.baseline_versao_id) ? "Baseline" : "Versão " + escape(version.numero || version.id)}${Number(version.id) === Number(state.plan.planejamento.versao_atual_id) ? " · Vigente" : ""}</strong><p>${formatDate(version.confirmado_em)}${version.confirmado_por ? " · " + escape(version.confirmado_por) : ""}${version.motivo_codigo ? "<br>" + escape(version.motivo_codigo.replaceAll("_", " ")) : ""}</p></div><button class="ap-button" type="button" data-version="${Number(version.id)}">Ver imagens</button></article>`,
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
    $("ap-history-title").textContent = "Planejado × executado";
    $("ap-history-list").replaceChildren();
    $("ap-history-snapshot").innerHTML =
      `<div class="ap-table-wrap"><table class="ap-history-table"><thead><tr><th>Imagem</th><th>Baseline</th><th>Plano vigente</th><th>Executor</th><th>Início real</th><th>Conclusão da alteração</th><th>Entrega ao cliente</th><th>Resultado</th></tr></thead><tbody>${facts.itens.map((r) => `<tr><td>${escape(names.get(Number(r.entrega_item_id)))}</td><td>${planned(r.baseline)}</td><td>${planned(r.vigente)}</td><td>${escape(r.executor_nome || "Sem evidência")}</td><td>${formatDate(r.inicio_real)}</td><td>${formatDate(r.conclusao_alteracao)}</td><td>${formatDate(r.entrega_cliente)}</td><td>${escape(r.resultado)}</td></tr>`).join("")}</tbody></table></div><h3>Realizado por dia</h3><div class="ap-table-wrap"><table class="ap-history-table"><thead><tr><th>Colaborador</th><th>Dia</th><th>Pts planejados</th><th>Pts concluídos</th><th>Pendentes do planejado no dia</th></tr></thead><tbody>${Object.entries(
        facts.por_dia,
      )
        .flatMap(([id, ds]) =>
          Object.entries(ds).map(
            ([d, v]) =>
              `<tr><td>${escape(personName(id))}</td><td>${formatDate(d)}</td><td>${v.planejados == null ? "Não registrados" : num(v.planejados)}</td><td>${v.concluidos == null ? "Não registrados" : num(v.concluidos)}</td><td>${v.pendentes_do_planejado == null ? "Não registrados" : num(v.pendentes_do_planejado)}</td></tr>`,
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
      $("ap-history-snapshot").innerHTML =
        `<h3>Imagens da versão</h3><div class="ap-table-wrap"><table class="ap-history-table"><thead><tr><th>Imagem</th><th>Nível</th><th>Pontos históricos</th><th>Responsável</th><th>Dia planejado</th></tr></thead><tbody>${items.map((item) => `<tr><td>${escape(item.imagem_nome || `Item ${item.entrega_item_id}`)}</td><td>${levelBadge(item)}</td><td>${item.pontos_snapshot == null ? "Sem snapshot de pontos" : num(item.pontos_snapshot) + " pts"}</td><td>${escape(item.responsavel_planejado_nome || item.responsavel_nome || personName(item.responsavel_planejado_id))}</td><td>${formatDate(item.fim_planejado)}</td></tr>`).join("")}</tbody></table></div>`;
    } catch (error) {
      if (request === historyRequest)
        $("ap-history-snapshot").textContent = error.message;
    }
  });
  $("ap-backlog-search").addEventListener("input", renderPlanner);
  $("ap-backlog-pending").addEventListener("change", renderPlanner);
  $("ap-backlog-levels").addEventListener("click", (event) => {
    const b = event.target.closest("[data-backlog-level]");
    if (b) {
      state.backlogLevel = b.dataset.backlogLevel;
      renderPlanner();
    }
  });
  $("ap-mobile-day").addEventListener("change", (event) => {
    state.dayIndex = days().indexOf(event.target.value);
    renderPlanner();
  });
  $("ap-day-prev").addEventListener("click", () => {
    state.dayIndex = Math.max(
      0,
      state.dayIndex - (window.innerWidth <= 600 ? 1 : state.dayCount),
    );
    renderPlanner();
  });
  $("ap-day-next").addEventListener("click", () => {
    state.dayIndex += window.innerWidth <= 600 ? 1 : state.dayCount;
    renderPlanner();
  });
  $("ap-more-days").addEventListener("click", () => {
    state.dayCount = Math.min(10, state.dayCount + 1);
    renderPlanner();
  });
  window.addEventListener("resize", () => state.plan && renderPlanner());
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
  $("alteration-plan").addEventListener("change", (event) => {
    const id = Number(event.target.dataset.cardSelect);
    if (!id || !editable()) return;
    event.target.checked ? state.selected.add(id) : state.selected.delete(id);
    renderRows();
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
    const pin = event.target.closest("[data-pin]"),
      unplan = event.target.closest("[data-unplan]"),
      place = event.target.closest("[data-place-person]"),
      remove = event.target.closest("[data-remove-person]"),
      agenda = event.target.closest("[data-agenda]");
    if (agenda) {
      const id = Number(agenda.dataset.agenda);
      $("ap-history-title").textContent = "Agenda de Alterações";
      $("ap-history-list").replaceChildren();
      $("ap-history-snapshot").innerHTML =
        `<h3>Agenda de ${escape(personName(id))}</h3><div class="ap-table-wrap"><table class="ap-history-table"><thead><tr><th>Dia</th><th>Nominal</th><th>Oferta efetiva</th><th>Outras alterações</th><th>Esta entrega</th><th>Contexto</th></tr></thead><tbody>${days()
          .map((d) => {
            const a = capacity(id, d);
            return `<tr><td>${formatDate(d)}</td><td>${num(a.nominal)}</td><td>${num(a.capacidade)}</td><td>${num(a.comprometidos)}</td><td>${num(cellLoad(id, d))}</td><td>${escape(a.avisos.join("; "))}${a.ocupacao_r00 ? " R00: " + num(a.ocupacao_r00 * 100) + "%" : ""}</td></tr>`;
          })
          .join("")}</tbody></table></div>`;
      $("ap-history-dialog").showModal();
      return;
    }
    if (!editable()) return;
    if (pin) {
      const item = state.items.find(
        (i) => Number(i.entrega_item_id) === Number(pin.dataset.pin),
      );
      if (!item.fixado && !complete(item)) {
        message(
          "A imagem precisa de responsável e dia antes de ser fixada.",
          true,
        );
        return;
      }
      item.fixado = !item.fixado;
      markDirty();
      renderRows();
    } else if (unplan)
      applyDecisions(
        new Map([
          [
            Number(unplan.dataset.unplan),
            {
              responsavel_planejado_id: null,
              fim_planejado: null,
              inicio_planejado: null,
              fixado: false,
            },
          ],
        ]),
      );
    else if (place) {
      if (!state.selected.size) {
        message(
          "Selecione uma ou mais imagens no painel lateral ou na tabela.",
        );
        return;
      }
      applyDecisions(
        new Map(
          [...state.selected].map((id) => [
            id,
            {
              responsavel_planejado_id: Number(place.dataset.placePerson),
              fim_planejado: place.dataset.placeDay,
              inicio_planejado: null,
            },
          ]),
        ),
      );
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
  $("alteration-plan").addEventListener("dragstart", (event) => {
    const el = event.target.closest("[data-card]");
    if (!el || !editable()) return event.preventDefault();
    const id = Number(el.dataset.card),
      ids = state.selected.has(id) ? [...state.selected] : [id];
    event.dataTransfer.setData(
      "application/x-flow-images",
      JSON.stringify(ids),
    );
    event.dataTransfer.effectAllowed = "move";
  });
  $("ap-grid").addEventListener("dragover", (event) => {
    if (editable() && event.target.closest("[data-drop-person]"))
      event.preventDefault();
  });
  $("ap-grid").addEventListener("drop", (event) => {
    const cell = event.target.closest("[data-drop-person]");
    if (!cell || !editable()) return;
    event.preventDefault();
    try {
      const ids = JSON.parse(
        event.dataTransfer.getData("application/x-flow-images"),
      );
      applyDecisions(
        new Map(
          ids.map((id) => [
            Number(id),
            {
              responsavel_planejado_id: Number(cell.dataset.dropPerson),
              fim_planejado: cell.dataset.dropDay,
              inicio_planejado: null,
            },
          ]),
        ),
      );
    } catch (_) {
      message(
        "Não foi possível mover as imagens. Selecione-as e use Adicionar seleção.",
        true,
      );
    }
  });
  $("ap-backlog").addEventListener("dragover", (event) => {
    if (editable()) event.preventDefault();
  });
  $("ap-backlog").addEventListener("drop", (event) => {
    if (!editable()) return;
    event.preventDefault();
    try {
      const ids = JSON.parse(
        event.dataTransfer.getData("application/x-flow-images"),
      );
      applyDecisions(
        new Map(
          ids.map((id) => [
            Number(id),
            {
              responsavel_planejado_id: null,
              fim_planejado: null,
              inicio_planejado: null,
              fixado: false,
            },
          ]),
        ),
      );
    } catch (_) {
      message("Não foi possível retirar a imagem.", true);
    }
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
    state.saving = true;
    state.suggesting = true;
    renderSummary();
    setLoading(true, "Gerando sugestão…", true);
    message("");
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
          itens: state.items,
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
        ...suggested.get(Number(i.entrega_item_id)),
      }));
      const firstDay = state.items
        .map((i) => i.fim_planejado)
        .filter(Boolean)
        .sort()[0];
      if (firstDay) state.dayIndex = Math.max(0, days().indexOf(firstDay));
      state.dirty = true;
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
