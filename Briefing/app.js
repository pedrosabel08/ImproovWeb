(() => {
  const PAGE_SIZE = 20;
  const STATUS = {
    RASCUNHO: "Rascunho",
    PRONTO_PARA_ENVIO: "Pronto para envio",
    AGUARDANDO_CLIENTE: "Aguardando cliente",
    EM_PREENCHIMENTO: "Em preenchimento",
    EM_CONFERENCIA: "Em conferência",
    AJUSTES_SOLICITADOS: "Aguardando complemento",
    APROVADO: "Concluído",
  };
  const FILTERS = [
    ["", "Todos", "all"],
    ["EM_PREENCHIMENTO", "Em preenchimento", "filling"],
    ["EM_CONFERENCIA", "Em conferência", "review"],
    ["AJUSTES_SOLICITADOS", "Aguardando complemento", "adjustment"],
    ["late", "Atrasados", "late"],
    ["APROVADO", "Concluídos", "done"],
  ];
  const state = {
    csrf: "",
    templates: [],
    obras: [],
    collaborators: [],
    briefings: [],
    summary: {},
    pagination: { page: 1, total: 0, pages: 1 },
    filters: {
      search: "",
      status: "",
      reviewerId: "",
      due: "",
      sort: "activity",
      page: 1,
    },
    current: null,
    currentTemplate: null,
    detailTab: "overview",
    realtimeBriefingId: null,
    template: { sections: [] },
    view: "briefings",
  };
  const $ = (id) => document.getElementById(id);
  const notice = (message = "") => {
    $("notice").textContent = message;
  };
  const option = (value, label) => {
    const element = document.createElement("option");
    element.value = value;
    element.textContent = label;
    return element;
  };
  const text = (value) => String(value ?? "");
  const statusLabel = (value) =>
    STATUS[value] || text(value).replaceAll("_", " ");
  const statusClass = (value) =>
    "status-" + text(value).toLowerCase().replaceAll("_", "-");
  function toDate(value) {
    if (!value) return null;
    const raw = text(value).includes("T")
      ? value
      : text(value).replace(" ", "T") + "Z";
    const date = new Date(raw);
    return Number.isNaN(date.getTime()) ? null : date;
  }
  function shortDate(value) {
    const date = toDate(value);
    return date
      ? new Intl.DateTimeFormat("pt-BR", {
          day: "2-digit",
          month: "short",
          year: "numeric",
        })
          .format(date)
          .replace(".", "")
      : "Sem prazo";
  }
  function dateTime(value) {
    const date = toDate(value);
    return date
      ? new Intl.DateTimeFormat("pt-BR", {
          day: "2-digit",
          month: "short",
          hour: "2-digit",
          minute: "2-digit",
        })
          .format(date)
          .replace(".", "")
      : "Sem registro";
  }
  function relative(value) {
    const date = toDate(value);
    if (!date) return "Sem atividade";
    const seconds = Math.round((date.getTime() - Date.now()) / 1000);
    const units = [
      [60, "second"],
      [60, "minute"],
      [24, "hour"],
      [7, "day"],
      [4.345, "week"],
      [12, "month"],
      [Infinity, "year"],
    ];
    let amount = seconds;
    for (const unit of units) {
      if (Math.abs(amount) < unit[0])
        return new Intl.RelativeTimeFormat("pt-BR", { numeric: "auto" }).format(
          Math.round(amount),
          unit[1],
        );
      amount /= unit[0];
    }
    return shortDate(value);
  }
  async function api(data, retry = true) {
    const response = await fetch("api.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-Briefing-CSRF": state.csrf,
      },
      body: JSON.stringify(data),
    });
    const result = await response.json();
    if (!response.ok || !result.ok) {
      if (
        retry &&
        data.action !== "bootstrap" &&
        result.message === "Token CSRF inválido."
      ) {
        const bootstrap = await api({ action: "bootstrap" }, false);
        state.csrf = bootstrap.csrf;
        return api(data, false);
      }
      throw new Error(result.message || "Erro inesperado.");
    }
    return result;
  }
  function fillSelect(id, items, empty) {
    const select = $(id);
    const previous = select.value;
    select.replaceChildren(option("", empty));
    items.forEach((item) =>
      select.append(
        option(
          item.id ?? item.idobra ?? item.idcolaborador,
          item.name || item.nome_obra || item.nome_colaborador,
        ),
      ),
    );
    select.value = previous;
  }
  function makeStatusBadge(briefing) {
    const badge = document.createElement("span");
    badge.className = "status-badge " + statusClass(briefing.status);
    const dot = document.createElement("i");
    badge.append(dot, document.createTextNode(statusLabel(briefing.status)));
    return badge;
  }
  function makeProgress(progress) {
    const wrap = document.createElement("div");
    wrap.className = "table-progress";
    const value = document.createElement("strong");
    value.textContent = String(progress.percent) + "%";
    const bar = document.createElement("div");
    bar.className = "progress";
    const fill = document.createElement("span");
    fill.style.width = String(progress.percent) + "%";
    bar.append(fill);
    wrap.append(value, bar);
    return wrap;
  }
  function makeDeadline(briefing) {
    const cell = document.createElement("div");
    cell.className = "deadline-cell";
    const title = document.createElement("strong");
    const caption = document.createElement("small");
    if (!briefing.prazo_em) {
      title.textContent = "Sem prazo";
      caption.textContent = "Não definido";
    } else {
      title.textContent = shortDate(briefing.prazo_em);
      const due = toDate(briefing.prazo_em);
      const days = due ? Math.ceil((due.getTime() - Date.now()) / 86400000) : 0;
      if (briefing.temporal_status === "VENCIDO") {
        caption.className = "late";
        caption.textContent =
          "Atrasado " +
          Math.max(1, Math.abs(days)) +
          " dia" +
          (Math.abs(days) === 1 ? "" : "s");
      } else if (days <= 1) {
        caption.className = "warning";
        caption.textContent = days === 0 ? "Vence hoje" : "Vence em 1 dia";
      } else {
        caption.className = "ok";
        caption.textContent = "No prazo";
      }
    }
    cell.append(title, caption);
    return cell;
  }
  function renderQuickFilters() {
    const host = $("quick-filters");
    host.replaceChildren();
    FILTERS.forEach((item) => {
      const value = item[0];
      const button = document.createElement("button");
      const summaryKey =
        {
          EM_PREENCHIMENTO: "filling",
          EM_CONFERENCIA: "review",
          AJUSTES_SOLICITADOS: "adjustment",
          APROVADO: "done",
        }[value] || value;
      const count =
        value === ""
          ? Number(state.summary.total ?? state.pagination.total)
          : Number(
              value === "late" ? state.summary.late : state.summary[summaryKey],
            );
      button.type = "button";
      button.className =
        "quick-filter " +
        item[2] +
        (state.filters.status === value ? " is-active" : "");
      button.setAttribute(
        "aria-pressed",
        String(state.filters.status === value),
      );
      button.innerHTML =
        '<span class="filter-dot"></span><span></span><strong></strong>';
      button.children[1].textContent = item[1];
      button.children[2].textContent = String(count || 0);
      button.onclick = () => {
        state.filters.status = value;
        state.filters.page = 1;
        $("filter-status").value = value === "late" ? "" : value;
        $("filter-due").value = value === "late" ? "late" : "";
        loadBriefings().catch(showError);
      };
      host.append(button);
    });
  }
  function renderList() {
    const host = $("briefing-list");
    host.replaceChildren();
    if (!state.briefings.length) {
      const empty = document.createElement("div");
      empty.className = "list-empty";
      const filtered =
        state.filters.search ||
        state.filters.status ||
        state.filters.reviewerId ||
        state.filters.due;
      empty.innerHTML =
        "<strong>" +
        (filtered
          ? "Nenhum briefing encontrado para esses filtros."
          : "Nenhum briefing criado ainda.") +
        "</strong><p>" +
        (filtered
          ? "Ajuste a busca ou limpe os filtros para ver outros briefings."
          : "Quando um briefing for criado, ele aparecerá aqui.") +
        "</p>";
      if (filtered) {
        const clear = document.createElement("button");
        clear.className = "text-button";
        clear.textContent = "Limpar filtros";
        clear.onclick = clearFilters;
        empty.append(clear);
      }
      host.append(empty);
      return;
    }
    state.briefings.forEach((briefing) => {
      const row = document.createElement("button");
      row.type = "button";
      row.className =
        "briefing-row" +
        (Number(state.current?.id) === Number(briefing.id)
          ? " is-selected"
          : "");
      const project = document.createElement("div");
      project.className = "project-cell";
      const name = document.createElement("strong");
      const obra = document.createElement("small");
      name.textContent = briefing.titulo;
      obra.textContent = briefing.nome_obra || "Obra #" + briefing.obra_id;
      project.append(name, obra);
      const status = document.createElement("div");
      status.append(makeStatusBadge(briefing));
      const activity = document.createElement("div");
      activity.className = "activity-cell";
      const when = document.createElement("strong");
      const actor = document.createElement("small");
      when.textContent = relative(
        briefing.last_activity_at || briefing.atualizado_em,
      );
      actor.textContent = briefing.last_actor_name
        ? "por " + briefing.last_actor_name
        : "Sem responsável";
      activity.append(when, actor);
      const arrow = document.createElement("span");
      arrow.className = "row-arrow";
      arrow.textContent = "›";
      row.append(
        project,
        makeProgress(briefing.progress),
        status,
        makeDeadline(briefing),
        activity,
        arrow,
      );
      row.onclick = () => loadDetail(briefing.id).catch(showError);
      host.append(row);
    });
  }
  function renderPagination() {
    const host = $("pagination");
    host.replaceChildren();
    const pagination = state.pagination;
    if (!pagination.total) return;
    const summary = document.createElement("span");
    const start = (pagination.page - 1) * PAGE_SIZE + 1;
    const end = Math.min(pagination.total, pagination.page * PAGE_SIZE);
    summary.textContent =
      "Mostrando " +
      start +
      " a " +
      end +
      " de " +
      pagination.total +
      " briefings";
    const controls = document.createElement("div");
    controls.className = "pagination-controls";
    const addPage = (label, page, disabled, active) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "page-button" + (active ? " is-active" : "");
      button.textContent = label;
      button.disabled = disabled;
      button.onclick = () => {
        state.filters.page = page;
        loadBriefings().catch(showError);
      };
      controls.append(button);
    };
    addPage("‹", pagination.page - 1, pagination.page <= 1, false);
    for (
      let number = Math.max(1, pagination.page - 1);
      number <= Math.min(pagination.pages, pagination.page + 1);
      number += 1
    )
      addPage(String(number), number, false, number === pagination.page);
    addPage(
      "›",
      pagination.page + 1,
      pagination.page >= pagination.pages,
      false,
    );
    host.append(summary, controls);
  }
  function section(title, body) {
    const element = document.createElement("section");
    element.className = "detail-section";
    const heading = document.createElement("h3");
    heading.textContent = title;
    element.append(heading, body);
    return element;
  }
  function addMenuItem(host, label, handler, danger = false) {
    const button = document.createElement("button");
    button.type = "button";
    button.textContent = label;
    if (danger) button.className = "danger";
    button.onclick = () => handler().catch(showError);
    host.append(button);
  }
  function toggleDetailMaximize() {
    const host = $("detail");
    const maximized = host.classList.toggle("is-maximized");
    const control = host.querySelector(".detail-maximize");
    if (!control) return;
    control.textContent = maximized ? "↙" : "⛶";
    control.setAttribute(
      "aria-label",
      maximized ? "Restaurar tamanho do detalhe" : "Maximizar detalhe",
    );
    control.title = control.getAttribute("aria-label");
  }
  function renderDetail(briefing) {
    const host = $("detail");
    host.classList.add("is-open");
    host.replaceChildren();
    const header = document.createElement("div");
    header.className = "detail-head";
    const info = document.createElement("div");
    const heading = document.createElement("h2");
    const meta = document.createElement("p");
    heading.textContent = briefing.titulo;
    meta.textContent =
      (briefing.nome_obra || "") +
      (briefing.criado_em
        ? " · criado em " + shortDate(briefing.criado_em)
        : "");
    info.append(heading, meta, makeStatusBadge(briefing));
    const actions = document.createElement("div");
    actions.className = "detail-top-actions";
    const menu = document.createElement("details");
    menu.className = "detail-menu";
    const summary = document.createElement("summary");
    summary.textContent = "⋮";
    summary.setAttribute("aria-label", "Mais ações");
    const menuList = document.createElement("div");
    menuList.className = "detail-menu-list";
    addMenuItem(menuList, "Atualizar dados", refreshCurrent);
    if (briefing.status === "RASCUNHO")
      addMenuItem(menuList, "Preparar envio", () => prepareBriefing(briefing));
    if (
      ["PRONTO_PARA_ENVIO", "AGUARDANDO_CLIENTE", "EM_PREENCHIMENTO"].includes(
        briefing.status,
      )
    )
      addMenuItem(
        menuList,
        briefing.external_access ? "Gerar novo link" : "Gerar link",
        () => createLink(briefing),
      );
    if (briefing.external_access)
      addMenuItem(menuList, "Revogar acesso", () => revokeLink(briefing), true);
    menu.append(summary, menuList);
    const close = document.createElement("button");
    close.type = "button";
    close.className = "icon-button";
    close.textContent = "×";
    close.setAttribute("aria-label", "Fechar detalhe");
    close.onclick = closeDetail;
    const maximize = document.createElement("button");
    maximize.type = "button";
    maximize.className = "icon-button detail-maximize";
    const isMaximized = host.classList.contains("is-maximized");
    maximize.textContent = isMaximized ? "↙" : "⛶";
    maximize.setAttribute(
      "aria-label",
      isMaximized ? "Restaurar tamanho do detalhe" : "Maximizar detalhe",
    );
    maximize.title = maximize.getAttribute("aria-label");
    maximize.onclick = toggleDetailMaximize;
    actions.append(menu, maximize, close);
    header.append(info, actions);
    const metrics = document.createElement("div");
    metrics.className = "detail-metrics";
    [
      [briefing.progress.percent + "%", "respondido"],
      [
        briefing.progress.answered + " / " + briefing.progress.total,
        "perguntas",
      ],
      [briefing.participants.length, "participantes"],
      [relative(briefing.atualizado_em), "última atividade"],
    ].forEach((item) => {
      const metric = document.createElement("div");
      metric.innerHTML = "<strong></strong><span></span>";
      metric.children[0].textContent = item[0];
      metric.children[1].textContent = item[1];
      metrics.append(metric);
    });
    const buttons = document.createElement("div");
    buttons.className = "detail-actions";
    if (["EM_CONFERENCIA", "AJUSTES_SOLICITADOS"].includes(briefing.status)) {
      const complement = document.createElement("button");
      complement.type = "button";
      complement.className = "button secondary";
      complement.textContent = "Solicitar complemento";
      complement.onclick = () => openComplement(briefing);
      buttons.append(complement);
    }
    if (briefing.status === "EM_CONFERENCIA") {
      const approve = document.createElement("button");
      approve.type = "button";
      approve.className = "button";
      approve.textContent = "✓ Aprovar briefing";
      approve.onclick = () => approveBriefing(briefing).catch(showError);
      buttons.append(approve);
    }
    if (briefing.status === "RASCUNHO") {
      const prepare = document.createElement("button");
      prepare.type = "button";
      prepare.className = "button";
      prepare.textContent = "Preparar envio";
      prepare.onclick = () => prepareBriefing(briefing).catch(showError);
      buttons.append(prepare);
    }
    const tabs = document.createElement("nav");
    tabs.className = "detail-tabs";
    [
      ["overview", "Visão geral"],
      ["answers", "Respostas " + briefing.progress.total],
      ["participants", "Participantes " + briefing.participants.length],
      ["history", "Histórico"],
    ].forEach((item) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className =
        "detail-tab" + (state.detailTab === item[0] ? " is-active" : "");
      button.textContent = item[1];
      button.onclick = () => {
        state.detailTab = item[0];
        renderDetail(state.current);
      };
      tabs.append(button);
    });
    const content = document.createElement("div");
    content.className = "detail-content";
    if (state.detailTab === "overview")
      content.append(renderOverview(briefing));
    if (state.detailTab === "answers") content.append(renderAnswers(briefing));
    if (state.detailTab === "participants")
      content.append(renderParticipants(briefing));
    if (state.detailTab === "history") content.append(renderHistory(briefing));
    host.append(header, metrics, buttons, tabs, content);
  }
  function renderOverview(briefing) {
    const stack = document.createElement("div");
    stack.className = "overview-stack";
    const progress = document.createElement("div");
    progress.className = "overview-item";
    progress.innerHTML =
      "<span>Progresso</span><strong>" +
      briefing.progress.percent +
      "% das perguntas respondidas</strong>";
    progress.append(makeProgress(briefing.progress));
    const deadline = document.createElement("div");
    deadline.className = "overview-item";
    deadline.innerHTML =
      "<span>Prazo</span><strong>" +
      (briefing.prazo_em
        ? shortDate(briefing.prazo_em)
        : "Sem prazo definido") +
      "</strong><small class='" +
      (briefing.temporal_status === "VENCIDO" ? "late" : "ok") +
      "'>" +
      (briefing.temporal_status === "VENCIDO" ? "Atrasado" : "No prazo") +
      "</small>";
    const access = document.createElement("div");
    access.className = "overview-item external-access";
    access.innerHTML = briefing.external_access
      ? "<span>Acesso externo</span><strong>Ativo até " +
        shortDate(briefing.external_access.expira_em) +
        "</strong><small>Último acesso " +
        (briefing.external_access.ultimo_uso_em
          ? relative(briefing.external_access.ultimo_uso_em)
          : "ainda não registrado") +
        "</small>"
      : "<span>Acesso externo</span><strong>Sem link ativo</strong><small>Gere um link quando o briefing estiver pronto.</small>";
    stack.append(progress, deadline, access);
    const activity = document.createElement("div");
    activity.className = "activity-list";
    if (!briefing.events?.length)
      activity.innerHTML =
        '<p class="muted">Ainda não há atividades registradas.</p>';
    (briefing.events || [])
      .slice(0, 4)
      .forEach((event) => activity.append(eventItem(event, briefing)));
    const wrap = document.createElement("div");
    wrap.append(
      section("Resumo", stack),
      section("Atividades recentes", activity),
    );
    return wrap;
  }
  function answerValue(answer) {
    if (answer.not_applicable) return "Não se aplica";
    if (
      answer.value === null ||
      answer.value === "" ||
      typeof answer.value === "undefined"
    )
      return "Sem resposta";
    return Array.isArray(answer.value)
      ? answer.value.join(", ")
      : String(answer.value);
  }
  function renderAnswers(briefing) {
    const wrap = document.createElement("div");
    const requests = new Map(
      (briefing.requests || []).map((request) => [
        Number(request.briefing_question_id),
        request,
      ]),
    );
    briefing.sections.forEach((item) => {
      const body = document.createElement("div");
      body.className = "answers-section";
      item.questions.forEach((question) => {
        const answer = document.createElement("article");
        answer.className = "answer-item";
        const title = document.createElement("strong");
        const value = document.createElement("p");
        const author = document.createElement("small");
        title.textContent = question.pergunta;
        value.textContent = answerValue(question.answer);
        author.textContent =
          (question.answer.author ? question.answer.author + " · " : "") +
          (question.answer.updated_at
            ? dateTime(question.answer.updated_at)
            : "Sem alteração");
        answer.append(title, value, author);
        const request = requests.get(Number(question.id));
        if (request) {
          const warning = document.createElement("div");
          warning.className = "answer-warning";
          warning.textContent = "Complemento solicitado: " + request.mensagem;
          answer.append(warning);
        }
        body.append(answer);
      });
      wrap.append(section(item.titulo, body));
    });
    return wrap;
  }
  function initials(name) {
    return text(name || "?")
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0])
      .join("")
      .toUpperCase();
  }
  function renderParticipants(briefing) {
    const list = document.createElement("div");
    list.className = "participants-list";
    if (!briefing.participants.length)
      list.innerHTML =
        '<p class="muted">Nenhum participante externo acessou este briefing ainda.</p>';
    briefing.participants.forEach((participant) => {
      const item = document.createElement("article");
      item.className = "participant-item";
      item.innerHTML =
        '<span class="avatar"></span><div><strong></strong><small></small><p></p></div>';
      item.querySelector(".avatar").textContent = initials(participant.nome);
      item.querySelector("strong").textContent = participant.nome;
      item.querySelector("small").textContent = participant.email || "";
      item.querySelector("p").textContent =
        "Último acesso " +
        (participant.ultima_atividade_em
          ? relative(participant.ultima_atividade_em)
          : "não registrado") +
        (participant.respostas_count
          ? " · " + participant.respostas_count + " respostas"
          : "");
      list.append(item);
    });
    return section("Participantes", list);
  }
  function metadata(event) {
    try {
      return event.metadata_json ? JSON.parse(event.metadata_json) : {};
    } catch (_) {
      return {};
    }
  }
  function questionLabel(briefing, id) {
    for (const item of briefing.sections || []) {
      const question = item.questions.find(
        (value) => Number(value.id) === Number(id),
      );
      if (question) return "“" + question.pergunta + "”";
    }
    return "uma resposta";
  }
  function eventDescription(event, briefing) {
    const data = metadata(event);
    const actor = event.ator_nome || "Sistema";
    const question = data.question_id
      ? questionLabel(briefing, data.question_id)
      : "uma resposta";
    const descriptions = {
      "briefing.created": actor + " criou o briefing",
      "briefing.prepared": actor + " preparou o briefing para envio",
      "briefing.link_issued": actor + " gerou o acesso externo",
      "briefing.link_revoked": actor + " revogou o acesso externo",
      "briefing.client_submitted":
        actor + " enviou o briefing para conferência",
      "briefing.approved": actor + " aprovou o briefing",
      "answer.updated": actor + " atualizou " + question,
      "question.complement_requested":
        actor + " solicitou complemento em " + question,
      "participant.accessed": actor + " acessou o briefing",
    };
    return descriptions[event.tipo] || actor + " atualizou o briefing";
  }
  function eventItem(event, briefing) {
    const item = document.createElement("article");
    item.className = "activity-item";
    item.innerHTML =
      '<span class="avatar"></span><div><strong></strong><small></small></div>';
    item.querySelector(".avatar").textContent = initials(event.ator_nome);
    item.querySelector("strong").textContent = eventDescription(
      event,
      briefing,
    );
    item.querySelector("small").textContent = relative(event.criado_em);
    return item;
  }
  function renderHistory(briefing) {
    const list = document.createElement("div");
    list.className = "timeline";
    if (!briefing.events?.length)
      list.innerHTML = '<p class="muted">Nenhum evento registrado ainda.</p>';
    (briefing.events || []).forEach((event) => {
      const item = document.createElement("article");
      item.innerHTML =
        "<time></time><div><strong></strong><small></small></div>";
      item.querySelector("time").textContent = dateTime(event.criado_em);
      item.querySelector("strong").textContent = eventDescription(
        event,
        briefing,
      );
      item.querySelector("small").textContent = event.ator_nome || "Sistema";
      list.append(item);
    });
    return section("Histórico", list);
  }
  function showError(error) {
    notice(error?.message || "Não foi possível concluir esta ação.");
  }
  function connectRealtime(id) {
    const briefingId = Number(id);
    if (
      !briefingId ||
      state.realtimeBriefingId === briefingId ||
      !window.FlowBriefingWS
    )
      return;
    window.FlowBriefingWS.close();
    state.realtimeBriefingId = briefingId;
    window.FlowBriefingWS.connect(() =>
      api({ action: "briefing.ws_ticket", briefing_id: briefingId }).then(
        (result) => result.ticket,
      ),
    );
  }
  async function loadDetail(id, preserveTab = false) {
    const result = await api({ action: "briefing.detail", briefing_id: id });
    state.current = result.briefing;
    if (!preserveTab) state.detailTab = "overview";
    connectRealtime(result.briefing.id);
    renderList();
    renderDetail(result.briefing);
  }
  function closeDetail() {
    state.current = null;
    state.realtimeBriefingId = null;
    window.FlowBriefingWS?.close();
    renderList();
    const host = $("detail");
    host.classList.remove("is-open", "is-maximized");
    host.innerHTML =
      '<div class="detail-empty"><strong>Selecione um briefing</strong><p>Consulte o progresso, as respostas, participantes e histórico sem sair da listagem.</p></div>';
  }
  async function refreshCurrent() {
    if (!state.current) return;
    await loadDetail(state.current.id, true);
    await loadBriefings();
  }
  async function loadBriefings() {
    $("briefing-list").setAttribute("aria-busy", "true");
    const result = await api({
      action: "briefing.list",
      search: state.filters.search,
      status: state.filters.status,
      reviewer_id: state.filters.reviewerId,
      due: state.filters.due,
      sort: state.filters.sort,
      page: state.filters.page,
      limit: PAGE_SIZE,
    });
    state.briefings = result.briefings || [];
    state.summary = result.summary || {};
    state.pagination = result.pagination || {
      page: 1,
      total: state.briefings.length,
      pages: 1,
    };
    state.filters.page = Number(state.pagination.page);
    renderQuickFilters();
    renderList();
    renderPagination();
    $("briefing-list").removeAttribute("aria-busy");
  }
  function clearFilters() {
    state.filters = {
      search: "",
      status: "",
      reviewerId: "",
      due: "",
      sort: "activity",
      page: 1,
    };
    $("briefing-search").value = "";
    $("filter-status").value = "";
    $("filter-reviewer").value = "";
    $("filter-due").value = "";
    $("filter-sort").value = "activity";
    loadBriefings().catch(showError);
  }
  async function prepareBriefing(briefing) {
    await api({ action: "briefing.prepare", briefing_id: briefing.id });
    notice("Briefing preparado para envio.");
    await refreshCurrent();
  }
  async function createLink(briefing) {
    const result = await api({
      action: "briefing.create_link",
      briefing_id: briefing.id,
    });
    navigator.clipboard?.writeText(result.url).catch(() => {});
    notice("Link gerado. " + result.url);
    await refreshCurrent();
  }
  async function revokeLink(briefing) {
    if (
      !confirm(
        "Revogar o acesso externo agora? Sessões abertas perderão o acesso nas próximas requisições.",
      )
    )
      return;
    await api({ action: "briefing.revoke_link", briefing_id: briefing.id });
    notice("Acesso externo revogado.");
    await refreshCurrent();
  }
  async function approveBriefing(briefing) {
    if (!confirm("Aprovar e congelar um snapshot deste briefing?")) return;
    await api({ action: "briefing.approve", briefing_id: briefing.id });
    notice("Briefing aprovado.");
    await refreshCurrent();
  }
  function openComplement(briefing) {
    const select = $("complement-question");
    select.replaceChildren(option("", "Selecione a pergunta"));
    briefing.sections.forEach((section) =>
      section.questions.forEach((question) =>
        select.append(
          option(question.id, section.titulo + " — " + question.pergunta),
        ),
      ),
    );
    $("complement-message").value = "";
    $("complement-dialog").showModal();
  }
  function normalizeTemplate(template) {
    return {
      id: Number(template.id) || 0,
      sections: (template.sections || []).map((section) => ({
        title: section.title || section.titulo || "",
        questions: (section.questions || []).map((question) => ({
          text: question.text || question.pergunta || "",
          type: question.type || question.tipo || "SHORT_TEXT",
          required: Boolean(question.required ?? question.obrigatoria),
          allow_not_applicable: Boolean(
            question.allow_not_applicable ?? question.permite_nao_aplica,
          ),
          options: (question.options || []).map((value) =>
            typeof value === "string"
              ? value
              : value.label || value.rotulo || "",
          ),
        })),
      })),
    };
  }
  function renderTemplateEditor() {
    const host = $("template-sections");
    host.replaceChildren();
    state.template.sections.forEach((sectionData, sectionIndex) => {
      const sectionElement = document.createElement("div");
      sectionElement.className = "template-section";
      const head = document.createElement("div");
      head.className = "template-section-head";
      const title = document.createElement("input");
      title.placeholder = "Título da seção";
      title.value = sectionData.title;
      title.oninput = (event) => {
        sectionData.title = event.target.value;
      };
      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "text-button danger-button";
      remove.textContent = "Excluir seção";
      remove.onclick = () => {
        state.template.sections.splice(sectionIndex, 1);
        renderTemplateEditor();
      };
      head.append(title, remove);
      sectionData.questions.forEach((questionData, questionIndex) => {
        const row = document.createElement("div");
        row.className = "template-question";
        const grid = document.createElement("div");
        grid.className = "template-question-grid";
        const question = document.createElement("input");
        question.placeholder = "Pergunta";
        question.value = questionData.text;
        question.oninput = (event) => {
          questionData.text = event.target.value;
        };
        const type = document.createElement("select");
        [
          "SHORT_TEXT",
          "LONG_TEXT",
          "YES_NO",
          "SINGLE_SELECT",
          "MULTI_SELECT",
          "NUMBER",
          "DATE",
          "LINK",
          "REFERENCE",
        ].forEach((value) => type.append(option(value, value)));
        type.value = questionData.type;
        type.onchange = (event) => {
          questionData.type = event.target.value;
        };
        grid.append(question, type);
        const required = document.createElement("label");
        required.className = "check";
        const requiredInput = document.createElement("input");
        requiredInput.type = "checkbox";
        requiredInput.checked = !!questionData.required;
        requiredInput.onchange = (event) => {
          questionData.required = event.target.checked;
        };
        required.append(requiredInput, document.createTextNode(" Obrigatória"));
        const allowNa = document.createElement("label");
        allowNa.className = "check";
        const allowNaInput = document.createElement("input");
        allowNaInput.type = "checkbox";
        allowNaInput.checked = !!questionData.allow_not_applicable;
        allowNaInput.onchange = (event) => {
          questionData.allow_not_applicable = event.target.checked;
        };
        allowNa.append(
          allowNaInput,
          document.createTextNode(" Permite não se aplica"),
        );
        const choices = document.createElement("input");
        choices.className = "question-options";
        choices.placeholder = "Opções separadas por vírgula";
        choices.value = questionData.options.join(", ");
        choices.oninput = (event) => {
          questionData.options = event.target.value
            .split(",")
            .map((value) => value.trim())
            .filter(Boolean);
        };
        const removeQuestion = document.createElement("button");
        removeQuestion.type = "button";
        removeQuestion.className = "text-button danger-button question-remove";
        removeQuestion.textContent = "Excluir pergunta";
        removeQuestion.onclick = () => {
          sectionData.questions.splice(questionIndex, 1);
          renderTemplateEditor();
        };
        row.append(grid, required, allowNa, choices, removeQuestion);
        sectionElement.append(row);
      });
      const addQuestion = document.createElement("button");
      addQuestion.type = "button";
      addQuestion.className = "text-button";
      addQuestion.textContent = "Adicionar pergunta";
      addQuestion.onclick = () => {
        sectionData.questions.push({
          text: "",
          type: "SHORT_TEXT",
          options: [],
          required: false,
          allow_not_applicable: false,
        });
        renderTemplateEditor();
      };
      sectionElement.append(head, addQuestion);
      host.append(sectionElement);
    });
  }
  function openTemplateEditor(template = null, duplicate = false) {
    state.template = template
      ? normalizeTemplate(template)
      : {
          id: 0,
          sections: [
            {
              title: "Informações gerais",
              questions: [
                {
                  text: "",
                  type: "SHORT_TEXT",
                  options: [],
                  required: false,
                  allow_not_applicable: false,
                },
              ],
            },
          ],
        };
    if (duplicate) state.template.id = 0;
    $("template-name").value = template
      ? (duplicate ? "Cópia de " : "") + template.nome
      : "";
    $("template-review").checked = template
      ? Boolean(template.exige_conferencia_interna)
      : true;
    $("template-reviewer").value =
      template?.revisor_padrao_colaborador_id || "";
    $("template-dialog-title").textContent = duplicate
      ? "Duplicar template"
      : state.template.id
        ? "Editar template"
        : "Novo template";
    renderTemplateEditor();
    $("template-dialog").showModal();
  }
  async function loadTemplates() {
    const result = await api({ action: "template.list" });
    state.templates = result.templates || [];
    fillSelect(
      "briefing-template",
      state.templates.filter((template) => Number(template.ativo) !== 0),
      "Selecione o template",
    );
    renderTemplates();
  }
  function renderTemplates() {
    const host = $("template-list");
    host.replaceChildren();
    const search = $("template-search").value.trim().toLowerCase();
    const templates = state.templates.filter(
      (template) =>
        !search || text(template.nome).toLowerCase().includes(search),
    );
    if (!templates.length) {
      host.innerHTML =
        '<div class="list-empty"><strong>Nenhum template encontrado.</strong><p>Crie um template ou ajuste sua busca.</p></div>';
      return;
    }
    templates.forEach((template) => {
      const row = document.createElement("button");
      row.type = "button";
      row.className =
        "template-row" +
        (Number(state.currentTemplate?.id) === Number(template.id)
          ? " is-selected"
          : "");
      row.innerHTML =
        '<div class="project-cell"><strong></strong><small></small></div><div><strong></strong><small>perguntas</small></div><div class="activity-cell"><strong></strong><small></small></div><span class="row-arrow">›</span>';
      row.querySelector(".project-cell strong").textContent = template.nome;
      row.querySelector(".project-cell small").textContent =
        "Versão " +
        (template.versao || 1) +
        (Number(template.ativo) === 0 ? " · arquivado" : "");
      row.querySelectorAll("strong")[1].textContent = String(
        template.questions_count || 0,
      );
      row.querySelector(".activity-cell strong").textContent = relative(
        template.atualizado_em,
      );
      row.querySelector(".activity-cell small").textContent = dateTime(
        template.atualizado_em,
      );
      row.onclick = () => showTemplate(template.id).catch(showError);
      host.append(row);
    });
  }
  async function showTemplate(id) {
    const result = await api({ action: "template.get", template_id: id });
    state.currentTemplate = result.template;
    renderTemplates();
    const template = result.template;
    const host = $("template-detail");
    host.replaceChildren();
    const count = template.sections.reduce(
      (total, item) => total + item.questions.length,
      0,
    );
    const head = document.createElement("div");
    head.className = "detail-head";
    head.innerHTML = "<div><h2></h2><p></p></div>";
    head.querySelector("h2").textContent = template.nome;
    head.querySelector("p").textContent =
      "Versão " + (template.versao || 1) + " · " + count + " perguntas";
    const actions = document.createElement("div");
    actions.className = "detail-actions";
    [
      ["Editar", "button secondary", () => openTemplateEditor(template)],
      [
        "Duplicar",
        "button secondary",
        () => openTemplateEditor(template, true),
      ],
      [
        "Criar briefing",
        "button",
        () => {
          showView("briefings");
          $("new-briefing").click();
          $("briefing-template").value = String(template.id);
        },
      ],
    ].forEach((item) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = item[1];
      button.textContent = item[0];
      button.onclick = item[2];
      actions.append(button);
    });
    const structure = document.createElement("div");
    structure.className = "template-preview";
    template.sections.forEach((item) => {
      const part = document.createElement("section");
      part.innerHTML = "<h3></h3><p></p>";
      part.querySelector("h3").textContent = item.titulo;
      part.querySelector("p").textContent =
        item.questions.length +
        " pergunta" +
        (item.questions.length === 1 ? "" : "s");
      structure.append(part);
    });
    host.append(head, actions, section("Estrutura", structure));
  }
  function showView(view) {
    state.view = view;
    $("briefings-view").hidden = view !== "briefings";
    $("templates-view").hidden = view !== "templates";
    $("view-briefings").classList.toggle("is-active", view === "briefings");
    $("view-templates").classList.toggle("is-active", view === "templates");
    if (view === "templates") loadTemplates().catch(showError);
  }
  function bindFilters() {
    let timer;
    $("briefing-search").oninput = (event) => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        state.filters.search = event.target.value.trim();
        state.filters.page = 1;
        loadBriefings().catch(showError);
      }, 220);
    };
    $("filter-status").onchange = (event) => {
      state.filters.status = event.target.value;
      state.filters.page = 1;
      $("filter-due").value = "";
      loadBriefings().catch(showError);
    };
    $("filter-reviewer").onchange = (event) => {
      state.filters.reviewerId = event.target.value;
      state.filters.page = 1;
      loadBriefings().catch(showError);
    };
    $("filter-due").onchange = (event) => {
      state.filters.due = event.target.value;
      state.filters.page = 1;
      state.filters.status = event.target.value === "late" ? "late" : "";
      $("filter-status").value = "";
      loadBriefings().catch(showError);
    };
    $("filter-sort").onchange = (event) => {
      state.filters.sort = event.target.value;
      state.filters.page = 1;
      loadBriefings().catch(showError);
    };
    $("clear-filters").onclick = clearFilters;
    $("template-search").oninput = renderTemplates;
  }
  async function init() {
    const bootstrap = await api({ action: "bootstrap" });
    state.csrf = bootstrap.csrf;
    state.obras = bootstrap.obras || [];
    state.collaborators = bootstrap.collaborators || [];
    fillSelect("briefing-obra", state.obras, "Selecione a obra");
    fillSelect(
      "briefing-reviewer",
      state.collaborators,
      "Qualquer pessoa interna",
    );
    fillSelect("filter-reviewer", state.collaborators, "Responsável");
    fillSelect(
      "template-reviewer",
      state.collaborators,
      "Definir por briefing",
    );
    $("filter-status").replaceChildren(
      option("", "Status"),
      ...Object.entries(STATUS).map((item) => option(item[0], item[1])),
    );
    bindFilters();
    await loadTemplates();
    await loadBriefings();
  }
  $("view-briefings").onclick = () => showView("briefings");
  $("view-templates").onclick = () => showView("templates");
  $("open-templates").onclick = () => showView("templates");
  $("new-template").onclick = () => openTemplateEditor();
  $("add-section").onclick = () => {
    state.template.sections.push({ title: "", questions: [] });
    renderTemplateEditor();
  };
  $("save-template").onclick = async (event) => {
    event.preventDefault();
    try {
      const result = await api({
        action: "template.save",
        template_id: state.template.id || null,
        name: $("template-name").value,
        requires_internal_review: $("template-review").checked,
        default_reviewer_id: $("template-reviewer").value || null,
        sections: state.template.sections,
      });
      $("template-dialog").close();
      notice("Template salvo.");
      await loadTemplates();
      if (state.view === "templates") await showTemplate(result.template_id);
    } catch (error) {
      showError(error);
    }
  };
  $("new-briefing").onclick = () => {
    $("briefing-form").reset();
    $("briefing-requires-review").checked = true;
    $("briefing-dialog").showModal();
  };
  $("save-briefing").onclick = async (event) => {
    event.preventDefault();
    try {
      const result = await api({
        action: "briefing.create",
        template_id: $("briefing-template").value,
        obra_id: $("briefing-obra").value,
        title: $("briefing-title").value,
        due_at: $("briefing-due").value,
        reviewer_id: $("briefing-reviewer").value || null,
        requires_internal_review: $("briefing-requires-review").checked,
      });
      $("briefing-dialog").close();
      notice("Briefing criado.");
      showView("briefings");
      await loadBriefings();
      await loadDetail(result.briefing_id);
    } catch (error) {
      showError(error);
    }
  };
  $("save-complement").onclick = async (event) => {
    event.preventDefault();
    try {
      await api({
        action: "briefing.request_complement",
        briefing_id: state.current.id,
        question_id: $("complement-question").value,
        message: $("complement-message").value,
      });
      $("complement-dialog").close();
      notice("Complemento solicitado.");
      await refreshCurrent();
    } catch (error) {
      showError(error);
    }
  };
  window.addEventListener("briefing:realtime", (event) => {
    const payload = event.detail || {};
    if (
      !state.current ||
      (payload.briefing_id &&
        Number(payload.briefing_id) !== Number(state.current.id))
    )
      return;
    loadDetail(state.current.id, true).catch(() => {});
    loadBriefings().catch(() => {});
  });
  window.addEventListener("keydown", (event) => {
    if (
      event.key === "Escape" &&
      !document.querySelector("dialog[open]") &&
      $("detail").classList.contains("is-open")
    ) {
      closeDetail();
    }
  });
  init().catch(showError);
})();
