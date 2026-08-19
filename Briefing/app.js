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
    templateMode: "edit",
    templateSaving: false,
    briefingSaving: false,
    templateDrag: null,
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
  function detailIcon(name) {
    const icons = {
      maximize:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3H3v5M16 3h5v5M21 16v5h-5M3 16v5h5" /></svg>',
      restore:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 8h13v13H8zM3 16V3h13" /></svg>',
      trash:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5" /></svg>',
      more: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="1.2" fill="currentColor" stroke="none" /><circle cx="12" cy="12" r="1.2" fill="currentColor" stroke="none" /><circle cx="12" cy="19" r="1.2" fill="currentColor" stroke="none" /></svg>',
      close:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>',
    };
    return icons[name] || "";
  }
  function setDetailIcon(button, name) {
    button.innerHTML = detailIcon(name);
  }
  function toggleDetailMaximize() {
    const host = $("detail");
    const maximized = host.classList.toggle("is-maximized");
    const control = host.querySelector(".detail-maximize");
    if (!control) return;
    setDetailIcon(control, maximized ? "restore" : "maximize");
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
    summary.className = "icon-button detail-icon-button detail-menu-trigger";
    setDetailIcon(summary, "more");
    summary.setAttribute("aria-label", "Mais ações");
    summary.title = "Mais ações";
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
    close.className = "icon-button detail-icon-button";
    setDetailIcon(close, "close");
    close.setAttribute("aria-label", "Fechar detalhe");
    close.title = "Fechar detalhe";
    close.onclick = closeDetail;
    const maximize = document.createElement("button");
    maximize.type = "button";
    maximize.className = "icon-button detail-icon-button detail-maximize";
    const isMaximized = host.classList.contains("is-maximized");
    setDetailIcon(maximize, isMaximized ? "restore" : "maximize");
    maximize.setAttribute(
      "aria-label",
      isMaximized ? "Restaurar tamanho do detalhe" : "Maximizar detalhe",
    );
    maximize.title = maximize.getAttribute("aria-label");
    maximize.onclick = toggleDetailMaximize;
    const remove = document.createElement("button");
    remove.type = "button";
    remove.className = "icon-button detail-icon-button danger";
    setDetailIcon(remove, "trash");
    remove.setAttribute("aria-label", "Excluir briefing");
    remove.title = "Excluir briefing";
    remove.onclick = () => deleteBriefing(briefing).catch(showError);
    actions.append(menu, maximize, remove, close);
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
  async function deleteBriefing(briefing) {
    if (
      !confirm(
        `Excluir o briefing “${briefing.titulo}”? Esta ação não pode ser desfeita.`,
      )
    )
      return;
    await api({ action: "briefing.delete", briefing_id: briefing.id });
    closeDetail();
    notice("Briefing excluído.");
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
  const TEMPLATE_TYPES = {
    SHORT_TEXT: {
      label: "Resposta curta",
      icon: "Aa",
      hint: "Uma linha de texto",
    },
    LONG_TEXT: {
      label: "Resposta longa",
      icon: "≡",
      hint: "Texto com mais detalhes",
    },
    YES_NO: { label: "Sim ou não", icon: "○", hint: "Duas alternativas fixas" },
    SINGLE_SELECT: {
      label: "Escolha única",
      icon: "◉",
      hint: "Uma alternativa",
    },
    MULTI_SELECT: {
      label: "Múltipla escolha",
      icon: "☑",
      hint: "Uma ou mais alternativas",
    },
    NUMBER: { label: "Número", icon: "#", hint: "Valor numérico" },
    DATE: { label: "Data", icon: "◷", hint: "Dia, mês e ano" },
    LINK: { label: "Link", icon: "↗", hint: "URL externa" },
    REFERENCE: {
      label: "Referências visuais",
      icon: "▧",
      hint: "Imagem ou referência",
    },
  };
  const TEMPLATE_TYPE_KEYS = Object.keys(TEMPLATE_TYPES);
  function parseValidation(value) {
    if (value && typeof value === "object") return { ...value };
    if (typeof value === "string" && value.trim()) {
      try {
        const parsed = JSON.parse(value);
        return parsed && typeof parsed === "object" ? parsed : {};
      } catch (_error) {
        return {};
      }
    }
    return {};
  }
  function newQuestion(type = "SHORT_TEXT") {
    return {
      text: "",
      type,
      required: false,
      allow_not_applicable: false,
      options: [],
      validation: {},
    };
  }
  function normalizeOption(value) {
    if (typeof value === "string") return { label: value, value };
    const label =
      value?.label || value?.rotulo || value?.value || value?.valor || "";
    return { label, value: value?.value || value?.valor || label };
  }
  function normalizeQuestion(question) {
    const type = TEMPLATE_TYPES[question.type || question.tipo]
      ? question.type || question.tipo
      : "SHORT_TEXT";
    return {
      text: question.text || question.pergunta || "",
      type,
      code: question.code || question.codigo || "",
      help: question.help || question.ajuda || "",
      required: Boolean(question.required ?? question.obrigatoria),
      allow_not_applicable: Boolean(
        question.allow_not_applicable ?? question.permite_nao_aplica,
      ),
      options: (question.options || []).map(normalizeOption),
      validation: parseValidation(
        question.validation ?? question.validacao_json,
      ),
    };
  }
  function normalizeTemplate(template) {
    return {
      id: Number(template.id) || 0,
      sections: (template.sections || []).map((section) => ({
        title: section.title || section.titulo || "",
        description: section.description || section.descricao || "",
        questions: (section.questions || []).map(normalizeQuestion),
      })),
    };
  }
  function cloneQuestion(question) {
    return JSON.parse(JSON.stringify(question));
  }
  function updateValidation(questionData, key, value) {
    if (value === "" || value === null || value === undefined) {
      delete questionData.validation[key];
    } else {
      questionData.validation[key] = value;
    }
  }
  function inputField(labelText, value, onInput, options = {}) {
    const label = document.createElement("label");
    label.className = options.className || "template-config-field";
    label.textContent = labelText;
    const input = document.createElement(options.tagName || "input");
    input.type = options.type || "text";
    input.value = value ?? "";
    input.placeholder = options.placeholder || "";
    if (options.min !== undefined) input.min = options.min;
    if (options.max !== undefined) input.max = options.max;
    input.oninput = (event) => onInput(event.target.value);
    label.append(input);
    return label;
  }
  function actionMenu(items) {
    const menu = document.createElement("details");
    menu.className = "template-context-menu";
    const summary = document.createElement("summary");
    summary.className = "template-context-trigger";
    summary.textContent = "⋯";
    summary.setAttribute("aria-label", "Mais ações");
    summary.title = "Mais ações";
    const list = document.createElement("div");
    list.className = "template-context-list";
    items.forEach((item) => {
      const button = document.createElement("button");
      button.type = "button";
      button.textContent = item.label;
      if (item.danger) button.className = "danger";
      button.onclick = () => {
        menu.removeAttribute("open");
        item.onClick();
      };
      list.append(button);
    });
    menu.append(summary, list);
    return menu;
  }
  function setDragHandle(handle, drag) {
    handle.draggable = true;
    handle.ondragstart = (event) => {
      state.templateDrag = drag;
      event.dataTransfer.effectAllowed = "move";
      event.dataTransfer.setData("text/plain", JSON.stringify(drag));
      handle
        .closest(".template-section, .template-question, .template-option-row")
        ?.classList.add("is-dragging");
    };
    handle.ondragend = () => {
      state.templateDrag = null;
      document
        .querySelectorAll(".is-dragging")
        .forEach((element) => element.classList.remove("is-dragging"));
    };
  }
  function renderQuestionConfiguration(questionData) {
    const config = document.createElement("div");
    config.className = "template-question-config";
    const type = questionData.type;
    if (type === "SHORT_TEXT") {
      config.append(
        inputField(
          "Placeholder opcional",
          questionData.validation.placeholder || "",
          (value) => updateValidation(questionData, "placeholder", value),
          { placeholder: "Digite sua resposta..." },
        ),
      );
      const preview = document.createElement("div");
      preview.className = "question-preview-field";
      preview.textContent =
        questionData.validation.placeholder || "Digite sua resposta...";
      config.append(preview);
    } else if (type === "LONG_TEXT") {
      config.append(
        inputField(
          "Placeholder opcional",
          questionData.validation.placeholder || "",
          (value) => updateValidation(questionData, "placeholder", value),
          { placeholder: "Conte um pouco mais..." },
        ),
      );
      const preview = document.createElement("textarea");
      preview.disabled = true;
      preview.placeholder =
        questionData.validation.placeholder || "Conte um pouco mais...";
      config.append(preview);
    } else if (type === "YES_NO") {
      const preview = document.createElement("div");
      preview.className = "choice-preview fixed-choice-preview";
      ["Sim", "Não"].forEach((label) => {
        const item = document.createElement("label");
        const radio = document.createElement("input");
        radio.type = "radio";
        radio.disabled = true;
        item.append(radio, document.createTextNode(label));
        preview.append(item);
      });
      config.append(preview);
    } else if (["SINGLE_SELECT", "MULTI_SELECT"].includes(type)) {
      const options = document.createElement("div");
      options.className = "template-options-editor";
      if (!questionData.options.length) {
        const empty = document.createElement("p");
        empty.className = "template-options-empty";
        empty.textContent =
          "Adicione as alternativas que o cliente poderá escolher.";
        options.append(empty);
      }
      questionData.options.forEach((optionData, optionIndex) => {
        const row = document.createElement("div");
        row.className = "template-option-row";
        const handle = document.createElement("button");
        handle.type = "button";
        handle.className = "template-drag-handle option-drag-handle";
        handle.textContent = "⋮⋮";
        handle.title = "Arrastar alternativa";
        setDragHandle(handle, { kind: "option", questionData, optionIndex });
        const marker = document.createElement("input");
        marker.type = type === "MULTI_SELECT" ? "checkbox" : "radio";
        marker.disabled = true;
        const text = document.createElement("input");
        text.type = "text";
        text.value = optionData.label;
        text.placeholder = `Alternativa ${optionIndex + 1}`;
        text.oninput = (event) => {
          optionData.label = event.target.value;
        };
        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "template-option-remove";
        remove.textContent = "Excluir";
        remove.onclick = () => {
          questionData.options.splice(optionIndex, 1);
          renderTemplateEditor();
        };
        row.append(handle, marker, text, remove);
        row.ondragover = (event) => {
          if (state.templateDrag?.kind === "option") event.preventDefault();
        };
        row.ondrop = (event) => {
          event.preventDefault();
          const drag = state.templateDrag;
          if (!drag || drag.kind !== "option") return;
          const from = questionData.options.indexOf(
            drag.questionData.options[drag.optionIndex],
          );
          if (
            drag.questionData !== questionData ||
            from < 0 ||
            from === optionIndex
          )
            return;
          const [moved] = questionData.options.splice(from, 1);
          questionData.options.splice(optionIndex, 0, moved);
          state.templateDrag = null;
          renderTemplateEditor();
        };
        options.append(row);
      });
      const addOption = document.createElement("button");
      addOption.type = "button";
      addOption.className = "template-add-option";
      addOption.textContent = "+ Adicionar opção";
      addOption.onclick = () => {
        questionData.options.push({ label: "", value: "" });
        renderTemplateEditor();
        const rows = $("template-sections").querySelectorAll(
          ".template-option-row input[type='text']",
        );
        rows[rows.length - 1]?.focus();
      };
      options.append(addOption);
      config.append(options);
    } else if (type === "NUMBER") {
      const fields = document.createElement("div");
      fields.className = "template-config-grid three-columns";
      fields.append(
        inputField(
          "Mínimo",
          questionData.validation.min,
          (value) => updateValidation(questionData, "min", value),
          { type: "number", placeholder: "Opcional" },
        ),
        inputField(
          "Máximo",
          questionData.validation.max,
          (value) => updateValidation(questionData, "max", value),
          { type: "number", placeholder: "Opcional" },
        ),
        inputField(
          "Unidade",
          questionData.validation.unit,
          (value) => updateValidation(questionData, "unit", value),
          { placeholder: "Ex.: m²" },
        ),
      );
      config.append(fields);
      const preview = document.createElement("input");
      preview.type = "number";
      preview.disabled = true;
      preview.placeholder = questionData.validation.unit
        ? `Informe um valor em ${questionData.validation.unit}`
        : "Informe um número...";
      config.append(preview);
    } else if (type === "DATE") {
      const fields = document.createElement("div");
      fields.className = "template-config-grid two-columns";
      fields.append(
        inputField(
          "Data mínima",
          questionData.validation.min,
          (value) => updateValidation(questionData, "min", value),
          { type: "date" },
        ),
        inputField(
          "Data máxima",
          questionData.validation.max,
          (value) => updateValidation(questionData, "max", value),
          { type: "date" },
        ),
      );
      config.append(fields);
      const preview = document.createElement("input");
      preview.type = "date";
      preview.disabled = true;
      config.append(preview);
    } else if (type === "LINK") {
      config.append(
        inputField(
          "Placeholder opcional",
          questionData.validation.placeholder || "",
          (value) => updateValidation(questionData, "placeholder", value),
          { placeholder: "https://..." },
        ),
      );
      const preview = document.createElement("div");
      preview.className = "question-preview-link";
      preview.textContent =
        questionData.validation.placeholder || "https://...";
      config.append(preview);
      const hint = document.createElement("small");
      hint.textContent = "O cliente deverá inserir uma URL válida.";
      config.append(hint);
    } else if (type === "REFERENCE") {
      const preview = document.createElement("div");
      preview.className = "reference-preview";
      preview.innerHTML =
        '<span aria-hidden="true">＋</span><div><strong>Adicionar imagem ou link</strong><small>O cliente poderá enviar uma referência visual para este briefing.</small></div>';
      config.append(preview);
    }
    return config;
  }
  function renderQuestionCard(
    sectionData,
    questionData,
    sectionIndex,
    questionIndex,
  ) {
    const card = document.createElement("article");
    card.className = "template-question-card";
    const top = document.createElement("div");
    top.className = "template-question-top";
    const handle = document.createElement("button");
    handle.type = "button";
    handle.className = "template-drag-handle";
    handle.textContent = "⋮⋮";
    handle.title = "Arrastar pergunta";
    setDragHandle(handle, { kind: "question", sectionIndex, questionIndex });
    const number = document.createElement("span");
    number.className = "template-question-number";
    number.textContent = `PERGUNTA ${String(questionIndex + 1).padStart(2, "0")}`;
    const menu = actionMenu([
      {
        label: "Duplicar pergunta",
        onClick: () => {
          sectionData.questions.splice(
            questionIndex + 1,
            0,
            cloneQuestion(questionData),
          );
          renderTemplateEditor();
        },
      },
      {
        label: "Excluir pergunta",
        danger: true,
        onClick: () => {
          if (
            (questionData.text || questionData.options.length) &&
            !confirm("Excluir esta pergunta?")
          )
            return;
          sectionData.questions.splice(questionIndex, 1);
          renderTemplateEditor();
        },
      },
    ]);
    top.append(handle, number, menu);
    const main = document.createElement("div");
    main.className = "template-question-main";
    const grid = document.createElement("div");
    grid.className = "template-question-grid redesigned-question-grid";
    const question = document.createElement("textarea");
    question.rows = 2;
    question.required = true;
    question.placeholder = "Escreva a pergunta que o cliente verá...";
    question.value = questionData.text;
    question.oninput = (event) => {
      questionData.text = event.target.value;
    };
    const typeWrap = document.createElement("label");
    typeWrap.className = "template-type-field";
    typeWrap.textContent = "Tipo de resposta";
    const type = document.createElement("select");
    TEMPLATE_TYPE_KEYS.forEach((value) =>
      type.append(
        option(
          value,
          `${TEMPLATE_TYPES[value].icon}  ${TEMPLATE_TYPES[value].label}`,
        ),
      ),
    );
    type.value = questionData.type;
    type.onchange = (event) => {
      questionData.type = event.target.value;
      questionData.validation = {};
      if (!["SINGLE_SELECT", "MULTI_SELECT"].includes(questionData.type))
        questionData.options = [];
      renderTemplateEditor();
    };
    typeWrap.append(type);
    const hint = document.createElement("small");
    hint.textContent = TEMPLATE_TYPES[questionData.type].hint;
    typeWrap.append(hint);
    grid.append(question, typeWrap);
    main.append(grid, renderQuestionConfiguration(questionData));
    const footer = document.createElement("div");
    footer.className = "template-question-footer";
    [
      ["required", "Obrigatória", "required"],
      [
        "allow_not_applicable",
        "Permitir “Não se aplica”",
        "allow_not_applicable",
      ],
    ].forEach((item) => {
      const label = document.createElement("label");
      label.className = "check template-toggle";
      const input = document.createElement("input");
      input.type = "checkbox";
      input.checked = !!questionData[item[2]];
      input.onchange = (event) => {
        questionData[item[2]] = event.target.checked;
      };
      label.append(input, document.createTextNode(item[1]));
      footer.append(label);
    });
    card.append(top, main, footer);
    card.ondragover = (event) => {
      if (state.templateDrag?.kind === "question") event.preventDefault();
    };
    card.ondrop = (event) => {
      event.preventDefault();
      const drag = state.templateDrag;
      if (
        !drag ||
        drag.kind !== "question" ||
        drag.sectionIndex !== sectionIndex ||
        drag.questionIndex === questionIndex
      )
        return;
      const questions = sectionData.questions;
      const [moved] = questions.splice(drag.questionIndex, 1);
      questions.splice(questionIndex, 0, moved);
      state.templateDrag = null;
      renderTemplateEditor();
    };
    return card;
  }
  function renderTemplateEditor() {
    const host = $("template-sections");
    host.replaceChildren();
    if (!state.template.sections.length) {
      const empty = document.createElement("div");
      empty.className = "template-builder-empty";
      empty.innerHTML =
        '<span class="template-empty-icon">＋</span><strong>Comece criando a primeira seção do briefing.</strong><p>Organize o formulário em blocos para facilitar a resposta do cliente.</p>';
      const add = document.createElement("button");
      add.type = "button";
      add.className = "button secondary";
      add.textContent = "+ Adicionar seção";
      add.onclick = () => {
        state.template.sections.push({
          title: "",
          description: "",
          questions: [],
        });
        renderTemplateEditor();
      };
      empty.append(add);
      host.append(empty);
      renderTemplatePreview();
      return;
    }
    state.template.sections.forEach((sectionData, sectionIndex) => {
      const sectionElement = document.createElement("section");
      sectionElement.className = "template-section-card";
      const head = document.createElement("div");
      head.className = "template-section-head redesigned-section-head";
      const handle = document.createElement("button");
      handle.type = "button";
      handle.className = "template-drag-handle";
      handle.textContent = "⋮⋮";
      handle.title = "Arrastar seção";
      setDragHandle(handle, { kind: "section", sectionIndex });
      const identity = document.createElement("div");
      identity.className = "template-section-identity";
      const label = document.createElement("span");
      label.className = "template-section-number";
      label.textContent = `SEÇÃO ${String(sectionIndex + 1).padStart(2, "0")}`;
      identity.append(label);
      const title = document.createElement("input");
      title.required = true;
      title.placeholder = "Nome da seção, ex.: Informações gerais";
      title.value = sectionData.title;
      title.oninput = (event) => {
        sectionData.title = event.target.value;
      };
      identity.append(title);
      const menu = actionMenu([
        {
          label: "Excluir seção",
          danger: true,
          onClick: () => {
            if (
              (sectionData.title || sectionData.questions.length) &&
              !confirm("Excluir esta seção e suas perguntas?")
            )
              return;
            state.template.sections.splice(sectionIndex, 1);
            renderTemplateEditor();
          },
        },
      ]);
      head.append(handle, identity, menu);
      const description = document.createElement("textarea");
      description.className = "template-section-description";
      description.rows = 2;
      description.placeholder = "Descrição opcional para orientar o cliente...";
      description.value = sectionData.description || "";
      description.oninput = (event) => {
        sectionData.description = event.target.value;
      };
      const questions = document.createElement("div");
      questions.className = "template-questions-list";
      if (!sectionData.questions.length) {
        const empty = document.createElement("div");
        empty.className = "template-section-empty";
        empty.innerHTML =
          "<strong>Nenhuma pergunta nesta seção.</strong><span>Adicione a primeira pergunta para começar.</span>";
        questions.append(empty);
      } else {
        sectionData.questions.forEach((questionData, questionIndex) =>
          questions.append(
            renderQuestionCard(
              sectionData,
              questionData,
              sectionIndex,
              questionIndex,
            ),
          ),
        );
      }
      const addQuestion = document.createElement("button");
      addQuestion.type = "button";
      addQuestion.className = "template-add-question";
      addQuestion.textContent = "+ Adicionar pergunta";
      addQuestion.onclick = () => {
        sectionData.questions.push(newQuestion());
        renderTemplateEditor();
      };
      questions.append(addQuestion);
      sectionElement.append(head, description, questions);
      sectionElement.ondragover = (event) => {
        if (
          state.templateDrag?.kind === "section" &&
          !event.target.closest(".template-question-card")
        )
          event.preventDefault();
      };
      sectionElement.ondrop = (event) => {
        if (event.target.closest(".template-question-card")) return;
        event.preventDefault();
        const drag = state.templateDrag;
        if (
          !drag ||
          drag.kind !== "section" ||
          drag.sectionIndex === sectionIndex
        )
          return;
        const [moved] = state.template.sections.splice(drag.sectionIndex, 1);
        state.template.sections.splice(sectionIndex, 0, moved);
        state.templateDrag = null;
        renderTemplateEditor();
      };
      host.append(sectionElement);
    });
    const addSection = document.createElement("button");
    addSection.type = "button";
    addSection.className = "template-add-section button secondary";
    addSection.textContent = "+ Adicionar seção";
    addSection.onclick = () => {
      state.template.sections.push({
        title: "",
        description: "",
        questions: [],
      });
      renderTemplateEditor();
    };
    host.append(addSection);
    renderTemplatePreview();
  }
  function appendPreviewControl(host, questionData) {
    const type = questionData.type;
    if (type === "LONG_TEXT") {
      const field = document.createElement("textarea");
      field.disabled = true;
      field.placeholder =
        questionData.validation.placeholder || "Conte um pouco mais...";
      host.append(field);
    } else if (type === "YES_NO") {
      const choices = document.createElement("div");
      choices.className = "preview-choice-list";
      ["Sim", "Não"].forEach((value) => {
        const label = document.createElement("label");
        const input = document.createElement("input");
        input.type = "radio";
        input.disabled = true;
        label.append(input, document.createTextNode(value));
        choices.append(label);
      });
      host.append(choices);
    } else if (["SINGLE_SELECT", "MULTI_SELECT"].includes(type)) {
      const choices = document.createElement("div");
      choices.className = "preview-choice-list";
      questionData.options.forEach((optionData) => {
        const label = document.createElement("label");
        const input = document.createElement("input");
        input.type = type === "MULTI_SELECT" ? "checkbox" : "radio";
        input.disabled = true;
        label.append(
          input,
          document.createTextNode(optionData.label || "Alternativa sem nome"),
        );
        choices.append(label);
      });
      if (!questionData.options.length)
        choices.append(document.createTextNode("Nenhuma opção adicionada."));
      host.append(choices);
    } else if (type === "REFERENCE") {
      const reference = document.createElement("div");
      reference.className = "reference-preview preview-only";
      reference.innerHTML =
        '<span aria-hidden="true">＋</span><div><strong>Adicionar imagem ou link</strong><small>Área de referência visual</small></div>';
      host.append(reference);
    } else {
      const field = document.createElement("input");
      field.disabled = true;
      field.type =
        type === "NUMBER"
          ? "number"
          : type === "DATE"
            ? "date"
            : type === "LINK"
              ? "url"
              : "text";
      field.placeholder =
        questionData.validation.placeholder ||
        (type === "LINK"
          ? "https://..."
          : type === "NUMBER"
            ? "Informe um número..."
            : "Digite sua resposta...");
      if (questionData.validation.min) field.min = questionData.validation.min;
      if (questionData.validation.max) field.max = questionData.validation.max;
      host.append(field);
    }
  }
  function renderTemplatePreview() {
    const host = $("template-preview");
    host.replaceChildren();
    if (!state.template.sections.length) {
      host.innerHTML =
        '<div class="template-preview-empty"><strong>O preview aparecerá aqui.</strong><p>Adicione uma seção e perguntas para visualizar o formulário.</p></div>';
      return;
    }
    state.template.sections.forEach((sectionData, sectionIndex) => {
      const section = document.createElement("section");
      section.className = "client-preview-section";
      const eyebrow = document.createElement("span");
      eyebrow.className = "template-section-number";
      eyebrow.textContent = `SEÇÃO ${String(sectionIndex + 1).padStart(2, "0")}`;
      const title = document.createElement("h3");
      title.textContent = sectionData.title || "Seção sem nome";
      section.append(eyebrow, title);
      if (sectionData.description) {
        const description = document.createElement("p");
        description.textContent = sectionData.description;
        section.append(description);
      }
      sectionData.questions.forEach((questionData) => {
        const question = document.createElement("div");
        question.className = "client-preview-question";
        const label = document.createElement("strong");
        label.textContent = questionData.text || "Pergunta sem texto";
        if (questionData.required) {
          const required = document.createElement("span");
          required.className = "required-mark";
          required.textContent = " *";
          label.append(required);
        }
        const type = document.createElement("small");
        type.textContent = TEMPLATE_TYPES[questionData.type].label;
        question.append(label, type);
        appendPreviewControl(question, questionData);
        if (questionData.allow_not_applicable) {
          const na = document.createElement("label");
          const input = document.createElement("input");
          input.type = "checkbox";
          input.disabled = true;
          na.append(input, document.createTextNode(" Não se aplica"));
          question.append(na);
        }
        section.append(question);
      });
      host.append(section);
    });
  }
  function setTemplateMode(mode) {
    state.templateMode = mode;
    const editing = mode === "edit";
    $("template-sections").hidden = !editing;
    $("template-preview").hidden = editing;
    [
      ["template-mode-edit", editing],
      ["template-mode-preview", !editing],
    ].forEach(([id, active]) => {
      $(id).classList.toggle("is-active", active);
      $(id).setAttribute("aria-selected", String(active));
    });
    if (!editing) renderTemplatePreview();
  }
  function closeTemplateEditor() {
    const dialog = $("template-dialog");
    if (!dialog.open || dialog.classList.contains("is-closing")) return;
    dialog.classList.add("is-closing");
    setTimeout(() => {
      dialog.close();
      dialog.classList.remove("is-closing");
    }, 180);
  }
  function openTemplateEditor(template = null, duplicate = false) {
    state.template = template
      ? normalizeTemplate(template)
      : { id: 0, sections: [] };
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
    state.templateMode = "edit";
    renderTemplateEditor();
    setTemplateMode("edit");
    document.body.classList.add("template-modal-open");
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
        '<div class="project-cell"><strong></strong><small></small></div><div><strong></strong><small> perguntas</small></div><div class="activity-cell"><strong></strong><small></small></div><span class="row-arrow">›</span>';
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
  $("template-mode-edit").onclick = () => setTemplateMode("edit");
  $("template-mode-preview").onclick = () => setTemplateMode("preview");
  $("close-template").onclick = closeTemplateEditor;
  $("cancel-template").onclick = closeTemplateEditor;
  $("template-dialog").addEventListener("cancel", (event) => {
    event.preventDefault();
    closeTemplateEditor();
  });
  $("template-dialog").addEventListener("close", () => {
    document.body.classList.remove("template-modal-open");
  });
  $("template-dialog").addEventListener("show", () => {
    document.body.classList.add("template-modal-open");
  });
  $("template-dialog").addEventListener("toggle", (event) => {
    if (event.newState === "open")
      document.body.classList.add("template-modal-open");
  });
  $("template-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    if (state.templateSaving) return;
    if (!$("template-form").reportValidity()) return;
    const invalidSection = state.template.sections.find(
      (section) => !section.title.trim(),
    );
    if (invalidSection) {
      showError(new Error("Dê um nome para todas as seções."));
      setTemplateMode("edit");
      return;
    }
    const invalidQuestion = state.template.sections
      .flatMap((section) => section.questions)
      .find((question) => !question.text.trim());
    if (invalidQuestion) {
      showError(new Error("Preencha o texto de todas as perguntas."));
      setTemplateMode("edit");
      return;
    }
    state.templateSaving = true;
    const saveButton = $("save-template");
    saveButton.disabled = true;
    saveButton.setAttribute("aria-busy", "true");
    saveButton.dataset.label = saveButton.textContent;
    saveButton.textContent = "Salvando…";
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
    } finally {
      state.templateSaving = false;
      saveButton.disabled = false;
      saveButton.removeAttribute("aria-busy");
      saveButton.textContent = saveButton.dataset.label || "Salvar template";
    }
  });
  function closeBriefingDialog() {
    const dialog = $("briefing-dialog");
    if (!dialog.open || dialog.classList.contains("is-closing")) return;
    dialog.classList.add("is-closing");
    setTimeout(() => {
      dialog.close();
      dialog.classList.remove("is-closing");
    }, 180);
  }
  $("close-briefing").onclick = closeBriefingDialog;
  $("cancel-briefing").onclick = closeBriefingDialog;
  $("briefing-dialog").addEventListener("cancel", (event) => {
    event.preventDefault();
    closeBriefingDialog();
  });
  $("briefing-dialog").addEventListener("close", () => {
    document.body.classList.remove("briefing-modal-open");
  });
  $("new-briefing").onclick = () => {
    $("briefing-form").reset();
    $("briefing-requires-review").checked = true;
    document.body.classList.add("briefing-modal-open");
    $("briefing-dialog").showModal();
  };
  $("briefing-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    if (state.briefingSaving || !$("briefing-form").reportValidity()) return;
    state.briefingSaving = true;
    const saveButton = $("save-briefing");
    saveButton.disabled = true;
    saveButton.setAttribute("aria-busy", "true");
    saveButton.dataset.label = saveButton.textContent;
    saveButton.textContent = "Criando…";
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
    } finally {
      state.briefingSaving = false;
      saveButton.disabled = false;
      saveButton.removeAttribute("aria-busy");
      saveButton.textContent = saveButton.dataset.label || "Criar briefing";
    }
  });
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
