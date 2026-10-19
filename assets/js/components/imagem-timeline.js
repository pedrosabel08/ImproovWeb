/* Presentation only: states, dates and dependencies come from the operational contract. */
(() => {
  const scriptUrl =
    document.currentScript?.src ||
    new URL("../assets/js/components/imagem-timeline.js", document.baseURI);
  const taskPage = new URL("../../../inicio.php", scriptUrl);
  const reviewPage = new URL("../../../FlowReview/index.php", scriptUrl);
  const node = (tag, text, cls) => {
    const n = document.createElement(tag);
    if (text != null) n.textContent = text;
    if (cls) n.className = cls;
    return n;
  };
  const date = (value) => {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value || "");
    return match ? `${match[3]}/${match[2]}/${match[1]}` : "Não definido";
  };
  const labels = {
    CONCLUIDA: "Concluída",
    ATIVA: "Em andamento",
    PENDENTE: "Não iniciada",
    BLOQUEADA: "Bloqueada",
  };
  const glyphs = {
    CONCLUIDA: '<i class="ri-checkbox-circle-fill"></i>',
    ATIVA: '<i class="ri-radio-button-line"></i>',
    PENDENTE: '<i class="ri-circle-line"></i>',
    BLOQUEADA: '<i class="ri-prohibited-line"></i>',
  };
  const stateIcon = (state) => {
    const icon = node("span", null, "it-stage-icon");
    icon.innerHTML = glyphs[state] || glyphs.PENDENTE;
    icon.setAttribute("aria-hidden", "true");
    return icon;
  };
  const stageKey = (stage) => stage.id_etapa ?? stage.funcao_imagem_id;
  const presentation = (context) => context.fluxo || context;
  const stageLabel = (stage) =>
    stage.somente_planejada
      ? stage.participa_operacao === false
        ? stage.status || "Sem participação operacional"
        : "Planejada"
      : labels[stage.estado_visual] || stage.status;
  const group = (context, key) => {
    const ids = new Set(context[key] || []);
    return (context.etapas || []).filter((stage) => ids.has(stageKey(stage)));
  };
  const operational = (context) =>
    (context.etapas || []).filter(
      (s) => s.participa_operacao !== false && s.estado_visual,
    );
  const primary = (context) => {
    const active = group(context, "etapas_ativas");
    return active.length
      ? active
      : group(context, "etapas_pendentes").slice(0, 1);
  };
  const isLate = (stage) => {
    const temporal = stage.contexto_planejamento?.status_temporal_individual;
    return (
      temporal?.codigo === "ATRASADO" ||
      (temporal?.codigo === "BLOQUEADO" && Number(temporal.dias) > 0)
    );
  };
  const relevantDate = (stage) =>
    stage.estado_visual === "CONCLUIDA"
      ? ["Concluída em", stage.execucao_individual?.conclusao_real]
      : stage.contexto_planejamento?.prazo_planejado
        ? ["Prazo planejado", stage.contexto_planejamento.prazo_planejado]
        : ["Prazo operacional", stage.prazo];
  function stats(context) {
    context = presentation(context);
    return {
      total: operational(context).length,
      concluded: group(context, "etapas_concluidas").length,
      active: group(context, "etapas_ativas").length,
      pending: group(context, "etapas_pendentes").length,
      blocked: group(context, "etapas_bloqueadas").length,
      late: operational(context).filter(isLate).length,
    };
  }
  function statusLabel(image) {
    const code = image.status_nome || "";
    const descriptions = {
      P00: "Prévia",
      R00: "Revisão inicial",
      EF: "Entrega final",
    };
    const description =
      image.status_descricao ||
      descriptions[code] ||
      (/^R\d+$/.test(code) ? `Revisão ${Number(code.slice(1))}` : "");
    return description && description !== code
      ? `${code} · ${description}`
      : code || "Status não informado";
  }
  function taskLink(stage) {
    const url = new URL(taskPage);
    url.searchParams.set("focus_task", stage.funcao_imagem_id);
    if (stage.responsavel_id)
      url.searchParams.set("colaborador_id", stage.responsavel_id);
    const a = node("a", "Abrir card da tarefa");
    a.className = "it-link-task";
    a.href = url.href;
    a.addEventListener("click", (event) => {
      event.preventDefault();
      window.FlowTaskOverlay.open(stage, a);
    });
    a.setAttribute(
      "aria-label",
      `Abrir card da tarefa de ${stage.funcao_nome || "função"}`,
    );
    return a;
  }
  function reviewLink(stage, context) {
    const url = new URL(reviewPage);
    url.searchParams.set("funcao_imagem_id", stage.funcao_imagem_id);
    if (context.imagem?.obra_nome)
      url.searchParams.set("obra_nome", context.imagem.obra_nome);
    const a = node("a", "Abrir FlowReview");
    a.className = "it-link-review";
    a.href = url.href;
    a.target = "_blank";
    a.rel = "noopener";
    a.setAttribute(
      "aria-label",
      `Abrir FlowReview de ${stage.funcao_nome || "função"}`,
    );
    return a;
  }
  function actions(stage, context) {
    const menu = node("details", null, "it-actions");
    const toggle = node("summary");
    toggle.innerHTML = '<i class="ri-settings-3-line" aria-hidden="true"></i>';
    toggle.setAttribute(
      "aria-label",
      `Ações de ${stage.funcao_nome || "função"}`,
    );
    toggle.title = `Ações de ${stage.funcao_nome || "função"}`;
    const links = node("div", null, "it-action-menu");
    const tasks = stage.tarefas || (stage.funcao_imagem_id ? [stage] : []);
    if (!tasks.length) {
      links.append(
        node(
          "p",
          "Função prevista no fluxo. Card e FlowReview ficam disponíveis quando a tarefa for criada.",
          "it-planned-explanation",
        ),
      );
    }
    for (const task of tasks) {
      if (tasks.length > 1)
        links.append(node("strong", task.funcao_nome, "it-action-group"));
      links.append(reviewLink(task, context), taskLink(task));
    }
    menu.append(toggle, links);
    menu.addEventListener("toggle", () => {
      if (menu.open)
        menu.parentElement
          .closest(".imagem-timeline")
          ?.querySelectorAll(".it-actions[open]")
          .forEach((other) => {
            if (other !== menu) other.open = false;
          });
    });
    menu.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        e.stopPropagation();
        menu.open = false;
        toggle.focus();
      }
    });
    return menu;
  }
  function summary(context) {
    context = presentation(context);
    const box = node("div", null, "it-summary");
    const stages = primary(context),
      count = stats(context);
    if (!stages.length) {
      box.append(
        node(
          "p",
          count.total &&
            count.concluded === count.total &&
            count.total === (context.etapas || []).length
            ? "Todas as etapas cadastradas concluídas"
            : count.blocked
              ? "Etapas bloqueadas · aguardando liberação"
              : (context.etapas || []).length
                ? "Nenhuma etapa ativa"
                : "Nenhuma função cadastrada",
        ),
      );
    } else {
      const active = stages[0].estado_visual === "ATIVA";
      box.append(
        node(
          "h4",
          active
            ? stages.length === 1
              ? "Etapa ativa"
              : `${stages.length} etapas ativas`
            : stages[0].somente_planejada
              ? "Próxima etapa prevista"
              : "Próxima etapa",
        ),
      );
      for (const stage of stages) {
        const row = node("div", null, "it-summary-stage");
        row.append(node("strong", stage.funcao_nome || "Função sem nome"));
        row.append(node("span", stage.responsavel_nome || "Sem responsável"));
        row.append(
          node(
            "small",
            `${relevantDate(stage)[0]}: ${date(relevantDate(stage)[1])}`,
          ),
        );
        if (isLate(stage))
          row.append(
            node(
              "small",
              `Atrasada · ${stage.contexto_planejamento.status_temporal_individual.rotulo}`,
              "it-late",
            ),
          );
        if (stage.avisos?.includes("ATIVA_COM_RESTRICAO_DE_INICIO"))
          row.append(
            node("small", "⚠ Possui restrição de início", "it-warning"),
          );
        box.append(row);
      }
    }
    return box;
  }
  function overview(context) {
    context = presentation(context);
    const box = node("section", null, "it-temporal");
    box.setAttribute("aria-label", "Timeline da imagem");
    box.append(
      node("h4", "Timeline da imagem"),
      node("small", "Andamento temporal das funções"),
    );
    const day = (v) =>
      /^\d{4}-\d{2}-\d{2}/.test(v || "")
        ? Date.parse(v.slice(0, 10) + "T12:00:00Z")
        : null;
    const today = day(context.hoje);
    const rows = (context.etapas || []).map((stage) => {
      const p = stage.contexto_planejamento || {},
        e = stage.execucao_individual || {};
      return {
        stage,
        start: day(e.inicio_real || p.inicio_planejado),
        end: day(
          stage.estado_visual === "CONCLUIDA"
            ? e.conclusao_real
            : p.prazo_planejado || stage.prazo,
        ),
        planned: day(p.prazo_planejado),
      };
    });
    const dates = rows
      .flatMap((r) => [r.start, r.end, r.planned])
      .filter((v) => v != null);
    if (today != null) dates.push(today);
    if (!dates.length) {
      box.append(node("p", "Sem datas registradas."));
      return box;
    }
    const lo = Math.min(...dates),
      hi = Math.max(...dates),
      pad = Math.max((hi - lo) * 0.04, 86400000),
      min = lo - pad,
      span = hi - lo + 2 * pad;
    const pos = (d) => (((d - min) / span) * 100).toFixed(3) + "%";
    const chart = node("div", null, "it-time-chart"),
      axis = node("div", null, "it-time-axis");
    let last = -100;
    for (const d of [
      ...new Set(rows.map((r) => r.end).filter((v) => v != null)),
    ].sort((a, b) => a - b)) {
      const x = ((d - min) / span) * 100;
      if (
        x - last < 9 ||
        (today != null && Math.abs(x - ((today - min) / span) * 100) < 10)
      )
        continue;
      const tick = node("span", date(new Date(d).toISOString()).slice(0, 5));
      tick.style.left = pos(d);
      axis.append(tick);
      last = x;
    }
    chart.append(axis);
    const highlighted = new Set(primary(context).map(stageKey));
    for (const r of rows) {
      const s = r.stage,
        next =
          !s.somente_planejada &&
          highlighted.has(stageKey(s)) &&
          s.estado_visual === "PENDENTE";
      const row = node(
        "div",
        null,
        "it-time-row is-" +
          (s.estado_visual || "informativa").toLowerCase() +
          (next ? " is-next" : "") +
          (isLate(s) ? " is-atrasada" : ""),
      );
      row.dataset.stageKey = stageKey(s);
      if (s.funcao_imagem_id) row.dataset.funcaoImagemId = s.funcao_imagem_id;
      const label = node("span", s.funcao_nome, "it-time-label");
      label.title =
        s.funcao_nome + " · " + (s.responsavel_nome || "Sem responsável");
      if (!s.responsavel_nome)
        label.append(node("small", "Sem responsável", "it-time-owner"));
      const track = node("div", null, "it-time-track");
      track.setAttribute("role", "img");
      track.setAttribute(
        "aria-label",
        s.funcao_nome +
          ": " +
          (next ? "Próxima etapa" : stageLabel(s)) +
          ". " +
          (s.responsavel_nome || "Sem responsável") +
          ". Início: " +
          date(
            s.execucao_individual?.inicio_real ||
              s.contexto_planejamento?.inicio_planejado,
          ) +
          ". " +
          relevantDate(s)[0] +
          ": " +
          date(relevantDate(s)[1]),
      );
      track.title = track.getAttribute("aria-label");
      if (r.end != null) {
        if (r.start != null && r.start <= r.end) {
          const bar = node("span", null, "it-time-bar");
          bar.style.left = pos(r.start);
          bar.style.width = (((r.end - r.start) / span) * 100).toFixed(3) + "%";
          track.append(bar);
        }
        const mark = stateIcon(next ? "ATIVA" : s.estado_visual);
        mark.classList.add("it-time-mark");
        mark.style.left = pos(r.end);
        track.append(mark);
        if (
          s.estado_visual === "CONCLUIDA" &&
          r.planned != null &&
          r.planned !== r.end
        ) {
          const planned = node("span", null, "it-time-planned");
          planned.style.left = pos(r.planned);
          planned.title =
            "Término planejado: " +
            date(s.contexto_planejamento.prazo_planejado);
          track.append(planned);
        }
      } else
        track.append(node("small", "Sem término registrado", "it-time-empty"));
      row.append(label, track);
      chart.append(row);
    }
    if (today != null) {
      const now = node("div", null, "it-time-today");
      now.style.setProperty("--today", pos(today));
      now.append(node("span", "Hoje " + date(context.hoje).slice(0, 5)));
      chart.append(now);
    }
    box.append(chart);
    if (
      rows.some(
        (r) =>
          r.stage.estado_visual === "CONCLUIDA" &&
          r.planned != null &&
          r.end != null &&
          r.planned !== r.end,
      )
    )
      box.append(
        node("small", "Traço pontilhado: término planejado.", "it-time-legend"),
      );
    return box;
  }
  function blockedNotice(context) {
    const blocked = group(context, "etapas_bloqueadas");
    const restricted = group(context, "etapas_ativas").filter((s) =>
      s.avisos?.includes("ATIVA_COM_RESTRICAO_DE_INICIO"),
    );
    if (!blocked.length && !restricted.length) return null;
    const box = node("details", null, "it-blocked-notice");
    const names = blocked.map((s) => s.funcao_nome).filter(Boolean);
    box.append(
      node(
        "summary",
        blocked.length
          ? `⚠ ${new Intl.ListFormat("pt-BR").format(names)} ${blocked.length === 1 ? "está bloqueada" : "estão bloqueadas"}`
          : "⚠ Há funções em andamento com restrições de início",
      ),
    );
    for (const stage of [...blocked, ...restricted]) {
      const reasons = stage.requisitos_inicio?.bloqueios || [];
      const row = node("div", null, "it-blocked-reason");
      row.append(node("strong", stage.funcao_nome));
      if (stage.execucao_individual?.hold)
        row.append(node("p", `Tarefa em HOLD. Status: ${stage.status}.`));
      for (const reason of reasons)
        row.append(
          node(
            "p",
            `${reason.label || reason.codigo || "Requisito pendente"}${reason.origem_tarefa?.nome ? " · " + reason.origem_tarefa.nome : ""}`,
          ),
        );
      if (!reasons.length && !stage.execucao_individual?.hold)
        row.append(
          node("p", "Consulte os requisitos de início nos detalhes da função."),
        );
      box.append(row);
    }
    return box;
  }
  function diagnosticFields(stage) {
    if (stage.somente_planejada) {
      const p = stage.contexto_planejamento || {};
      return [
        ["Situação", stageLabel(stage)],
        ["Responsável", "Sem responsável"],
        ["Início planejado", date(p.inicio_planejado)],
        ["Prazo planejado", date(p.prazo_planejado)],
        [
          "Situação do prazo",
          p.status_temporal_individual?.rotulo || "Não definida",
        ],
      ];
    }
    const p = stage.contexto_planejamento || {},
      fields = [
        ["Status operacional", stage.status || "Não informado"],
        ["Responsável", stage.responsavel_nome || "Sem responsável"],
        ["Prazo operacional", date(stage.prazo)],
        ["Início planejado", date(p.inicio_planejado)],
        ["Prazo planejado", date(p.prazo_planejado)],
        ["Compromisso", date(p.compromisso)],
        ["Previsão do colaborador", date(p.previsao_colaborador)],
        [
          "Desvio individual",
          p.desvio_individual_dias_uteis == null
            ? "Não disponível"
            : `${p.desvio_individual_dias_uteis > 0 ? "+" : ""}${p.desvio_individual_dias_uteis} dias úteis`,
        ],
        ["Início real", date(stage.execucao_individual?.inicio_real)],
        ["Conclusão real", date(stage.execucao_individual?.conclusao_real)],
        [
          "Situação do prazo",
          p.status_temporal_individual?.rotulo || "Não definida",
        ],
      ];
    if (stage.ultimo_ciclo)
      fields.push([
        "Ciclo",
        `${stage.ultimo_ciclo.numero_ciclo || "—"} · ${stage.ultimo_ciclo.situacao || ""}`,
      ]);
    return fields;
  }
  function render(context, mode = "compacto") {
    context = presentation(context);
    const root = node(
      "section",
      null,
      `imagem-timeline it-${mode === "completo" ? "full" : "compact"}`,
    );
    root.setAttribute("aria-label", "Funções da imagem");
    root.append(node("h4", "Funções da imagem"));
    const stages = context.etapas || [],
      highlighted = new Set(primary(context).map(stageKey));
    const stepper = node("div", null, "it-stepper");
    stepper.style.setProperty("--it-stage-count", Math.max(1, stages.length));
    stepper.dataset.manyStages = String(stages.length > 5);
    const list = node("ol", null, "it-process-timeline");
    const detail = node("section", null, "it-stage-detail");
    detail.hidden = true;
    detail.setAttribute("aria-label", "Detalhes operacionais da função");
    let currentStage = null,
      returnFocus = null;
    const triggers = [];
    const closeDetail = () => {
      detail.hidden = true;
      currentStage = null;
      triggers.forEach((t) => t.setAttribute("aria-expanded", "false"));
      returnFocus?.focus({ preventScroll: true });
    };
    const openDetail = (stage, trigger) => {
      if (currentStage === stageKey(stage) && !detail.hidden) {
        closeDetail();
        return;
      }
      currentStage = stageKey(stage);
      returnFocus = trigger;
      detail.replaceChildren();
      detail.hidden = false;
      triggers.forEach((t) =>
        t.setAttribute(
          "aria-expanded",
          String(t.dataset.stageId === String(stageKey(stage))),
        ),
      );
      const head = node("div", null, "it-detail-head");
      const close = node("button", "×", "mp-icon-button");
      close.type = "button";
      close.setAttribute("aria-label", "Fechar detalhes da função");
      close.addEventListener("click", closeDetail);
      head.append(
        node("h4", stage.funcao_nome),
        actions(stage, context),
        close,
      );
      detail.append(head);
      const p = stage.contexto_planejamento || {},
        fields = diagnosticFields(stage);
      if (stage.componentes?.length)
        fields.splice(
          0,
          fields.length,
          ["Situação", stageLabel(stage)],
          ["Responsável", stage.responsavel_nome || "Sem responsável"],
          ["Início planejado", date(p.inicio_planejado)],
          ["Prazo planejado", date(p.prazo_planejado)],
          ["Início real", date(stage.execucao_individual?.inicio_real)],
          ["Conclusão real", date(stage.execucao_individual?.conclusao_real)],
        );
      const dl = node("dl", null, "it-planning");
      fields.forEach(([label, value]) =>
        dl.append(node("dt", label), node("dd", value)),
      );
      detail.append(dl);
      if (stage.somente_planejada)
        detail.append(
          node(
            "p",
            "Esta função está prevista, mas ainda não possui uma tarefa. Seu prazo vem do planejamento vigente.",
            "it-planned-explanation",
          ),
        );
      if (stage.componentes?.length) {
        const members = node("section", null, "it-members");
        members.append(node("h4", "Tarefas da etapa"));
        for (const member of stage.componentes) {
          const disclosure = node("details");
          disclosure.append(
            node(
              "summary",
              (member.funcao_nome || "Função") + " · " + stageLabel(member),
            ),
          );
          const memberDates = node("dl", null, "it-planning");
          diagnosticFields(member).forEach(([label, value]) =>
            memberDates.append(node("dt", label), node("dd", value)),
          );
          disclosure.append(memberDates);
          members.append(disclosure);
        }
        detail.append(members);
      }
      const requirements = stage.requisitos_inicio;
      if (requirements?.requisitos_avaliados?.length) {
        const section = node("details");
        section.append(node("summary", "Requisitos de início"));
        for (const reason of requirements.requisitos_avaliados)
          section.append(
            node(
              "p",
              `${reason.label}: ${{ ATENDIDO: "Atendido", PENDENTE: "Pendente", NAO_APLICAVEL: "Não aplicável" }[reason.estado] || reason.estado}${reason.origem_tarefa?.nome ? " · " + reason.origem_tarefa.nome : ""}`,
            ),
          );
        detail.append(section);
      }
      if (stage.avisos?.includes("CONCLUIDA_SEM_DATA_NO_HISTORICO"))
        detail.append(
          node("p", "Conclusão sem data no histórico.", "it-warning"),
        );
      if (stage.avisos?.includes("ATIVA_COM_RESTRICAO_DE_INICIO"))
        detail.append(
          node(
            "p",
            "A tarefa está em andamento e possui restrições de início.",
            "it-warning",
          ),
        );
      detail.scrollIntoView({ block: "nearest", behavior: "auto" });
    };
    stages.forEach((stage) => {
      const state = stage.estado_visual,
        next =
          !stage.somente_planejada &&
          highlighted.has(stageKey(stage)) &&
          state === "PENDENTE";
      const cls = `is-${(state || "informativa").toLowerCase()}${next ? " is-next" : ""}${isLate(stage) ? " is-atrasada" : ""}`;
      const register = (button) => {
        button.type = "button";
        button.dataset.stageId = stageKey(stage);
        button.setAttribute("aria-expanded", "false");
        button.setAttribute(
          "aria-label",
          `${stage.funcao_nome}: ${next ? "Próxima etapa" : stageLabel(stage)}. Ver detalhes`,
        );
        button.addEventListener("click", () => openDetail(stage, button));
        triggers.push(button);
      };
      const step = node("button", null, `it-step ${cls}`);
      register(step);
      const icon = stateIcon(state);
      icon.setAttribute("aria-hidden", "true");
      const stepName = node("span", null, "it-step-name");
      stepName.title = stage.funcao_nome || "Função sem nome";
      const shortNames = {
        "Filtro de assets": "Filtro",
        "Caderno + Filtro de Assets": "Caderno + Filtro",
        Modelagem: "Model.",
        Composição: "Compos.",
        Finalização: "Finaliz.",
        "Pós-produção": "Pós-prod.",
      };
      stepName.append(
        node("span", stage.funcao_nome || "Função sem nome", "it-step-label"),
        node(
          "span",
          shortNames[stage.funcao_nome] || stage.funcao_nome || "Função",
          "it-step-abbrev",
        ),
      );
      step.append(icon, stepName);
      stepper.append(step);
      const item = node("li", null, `it-process-stage ${cls}`);
      item.dataset.stageKey = stageKey(stage);
      if (stage.funcao_imagem_id)
        item.dataset.funcaoImagemId = stage.funcao_imagem_id;
      const open = node("button", null, "it-stage-open");
      register(open);
      const badge = stage.somente_planejada
        ? stageLabel(stage)
        : next
          ? "Próxima etapa"
          : labels[state] || "Sem participação operacional";
      const [deadlineLabel, deadline] = relevantDate(stage);
      open.append(
        stateIcon(state),
        node("strong", stage.funcao_nome || "Função sem nome"),
        node("span", badge, "it-state"),
        node("small", "Responsável"),
        node(
          "span",
          stage.responsavel_nome || "Sem responsável",
          stage.responsavel_nome ? "" : "it-owner-empty",
        ),
        node("small", deadlineLabel),
        node("span", date(deadline), "it-relevant-date"),
      );
      const original = node(
        "span",
        stage.status || "Status não informado",
        "it-original-status",
      );
      open.append(original);
      if (isLate(stage))
        open.append(
          node(
            "small",
            `Atrasada · ${stage.contexto_planejamento.status_temporal_individual.rotulo}`,
            "it-late",
          ),
        );
      item.append(open, actions(stage, context));
      list.append(item);
    });
    root.append(stepper, list);
    const nextSummary = summary(context);
    nextSummary.classList.add("it-next-summary");
    const first = primary(context)[0];
    if (!first && group(context, "etapas_bloqueadas").length)
      nextSummary.hidden = true;
    if (first) {
      const openNext = node("button", "Ver detalhes da função", "it-next-open");
      openNext.type = "button";
      openNext.addEventListener("click", () => openDetail(first, openNext));
      openNext.textContent = "›";
      openNext.setAttribute(
        "aria-label",
        `Ver detalhes da função ${first.funcao_nome}`,
      );
      nextSummary.append(openNext);
    }
    root.append(nextSummary, detail);
    root.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && !detail.hidden) {
        e.preventDefault();
        e.stopPropagation();
        closeDetail();
      }
    });
    const notice = blockedNotice(context);
    if (notice) root.append(notice);
    if (
      context.avisos?.some((a) => a.codigo === "FUNCAO_COM_MULTIPLOS_REGISTROS")
    )
      root.append(
        node(
          "p",
          "Há funções com mais de um registro. Cada tarefa foi preservada separadamente.",
          "it-warning",
        ),
      );
    return root;
  }
  window.FlowImagemTimeline = {
    render,
    summary,
    taskLink,
    reviewLink,
    date,
    stats,
    statusLabel,
    overview,
  };
})();
