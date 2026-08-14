(() => {
  const state = {
    csrf: "",
    templates: [],
    obras: [],
    collaborators: [],
    briefings: [],
    current: null,
    template: { sections: [] },
  };
  const $ = (id) => document.getElementById(id);
  const notice = (message = "") => {
    $("notice").textContent = message;
  };
  const api = async (data, method = "POST") => {
    const r = await fetch("api.php", {
      method,
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-Briefing-CSRF": state.csrf,
      },
      body: method === "POST" ? JSON.stringify(data) : undefined,
    });
    const json = await r.json();
    if (!r.ok || !json.ok) throw new Error(json.message || "Erro inesperado.");
    return json;
  };
  const option = (value, label) => {
    const o = document.createElement("option");
    o.value = value;
    o.textContent = label;
    return o;
  };
  function fillSelect(id, items, empty) {
    const el = $(id);
    el.replaceChildren(option("", empty));
    items.forEach((x) =>
      el.append(
        option(x.id ?? x.idobra, x.name || x.nome_obra || x.nome_colaborador),
      ),
    );
  }
  function renderKpis() {
    const total = state.briefings.length,
      open = state.briefings.filter(
        (x) => !["APROVADO"].includes(x.status),
      ).length,
      late = state.briefings.filter(
        (x) => x.temporal_status === "VENCIDO",
      ).length;
    $("kpis").replaceChildren(
      ...[
        ["Total", total],
        ["Em andamento", open],
        ["Vencidos", late],
      ].map(([l, v]) => {
        const d = document.createElement("div");
        d.className = "kpi";
        d.textContent = l;
        const b = document.createElement("strong");
        b.textContent = v;
        d.append(b);
        return d;
      }),
    );
  }
  function renderList() {
    const host = $("briefing-list");
    host.replaceChildren();
    if (!state.briefings.length) {
      host.textContent = "Nenhum briefing criado ainda.";
      return;
    }
    state.briefings.forEach((b) => {
      const button = document.createElement("button");
      button.className = "briefing-card";
      button.onclick = () => loadDetail(b.id);
      const title = document.createElement("h3");
      title.textContent = b.titulo;
      const row = document.createElement("div");
      row.className = "row";
      const small = document.createElement("span");
      small.className = "muted";
      small.textContent = b.nome_obra || `Obra #${b.obra_id}`;
      const badge = document.createElement("span");
      badge.className = `badge ${b.status}`;
      badge.textContent = b.status.replaceAll("_", " ");
      row.append(small, badge);
      const progress = document.createElement("div");
      progress.className = "progress";
      const fill = document.createElement("span");
      fill.style.width = `${b.progress.percent}%`;
      progress.append(fill);
      const footer = document.createElement("div");
      footer.className = "row muted";
      footer.textContent = `${b.progress.answered}/${b.progress.total} respondidas`;
      const deadline = document.createElement("span");
      deadline.className = `badge ${b.temporal_status}`;
      deadline.textContent = b.temporal_status.replaceAll("_", " ");
      footer.append(deadline);
      button.append(title, row, progress, footer);
      host.append(button);
    });
  }
  function block(title, content) {
    const s = document.createElement("section");
    s.className = "section";
    const h = document.createElement("h3");
    h.textContent = title;
    s.append(h, content);
    return s;
  }
  async function loadDetail(id) {
    try {
      const { briefing: b } = await api({
        action: "briefing.detail",
        briefing_id: id,
      });
      state.current = b;
      const host = $("detail");
      host.replaceChildren();
      const title = document.createElement("h2");
      title.textContent = b.titulo;
      const meta = document.createElement("p");
      meta.className = "muted";
      meta.textContent = `${b.nome_obra || ""} · ${b.status.replaceAll("_", " ")} · ${b.temporal_status.replaceAll("_", " ")}`;
      const p = document.createElement("div");
      p.className = "progress";
      const f = document.createElement("span");
      f.style.width = `${b.progress.percent}%`;
      p.append(f);
      const count = document.createElement("p");
      count.className = "muted";
      count.textContent = `${b.progress.answered} de ${b.progress.total} perguntas respondidas`;
      host.append(title, meta, p, count, detailActions(b));
      const sections = document.createElement("div");
      b.sections.forEach((s) => {
        const e = document.createElement("div");
        const st = document.createElement("strong");
        st.textContent = s.titulo;
        e.append(st);
        s.questions.forEach((q) => {
          const qd = document.createElement("div");
          qd.className = "question";
          const qt = document.createElement("p");
          qt.textContent = q.pergunta;
          const av = document.createElement("small");
          av.className = "muted";
          av.textContent = q.answer.not_applicable
            ? "Não se aplica"
            : q.answer.value === null
              ? "Sem resposta"
              : `Resposta: ${Array.isArray(q.answer.value) ? q.answer.value.join(", ") : q.answer.value}`;
          qd.append(qt, av);
          e.append(qd);
        });
        sections.append(e);
      });
      host.append(block("Perguntas", sections));
      const people = document.createElement("p");
      people.className = "muted";
      people.textContent = b.participants.length
        ? b.participants
            .map(
              (x) =>
                `${x.nome}${x.ultima_atividade_em ? ` · ${x.ultima_atividade_em}` : ""}`,
            )
            .join("\n")
        : "Nenhum participante externo ainda.";
      host.append(block("Participantes", people));
      const timeline = document.createElement("ul");
      timeline.className = "timeline";
      (b.events || []).forEach((e) => {
        const li = document.createElement("li");
        li.textContent = `${e.tipo.replaceAll(".", " ")} · ${e.ator_nome} · ${e.criado_em}`;
        timeline.append(li);
      });
      host.append(block("Histórico", timeline));
    } catch (e) {
      notice(e.message);
    }
  }
  function detailActions(b) {
    const d = document.createElement("div");
    d.className = "detail-actions";
    const action = (label, fn) => {
      const x = document.createElement("button");
      x.className = "button secondary";
      x.textContent = label;
      x.onclick = fn;
      d.append(x);
    };
    if (b.status === "RASCUNHO")
      action("Preparar envio", async () => {
        await api({ action: "briefing.prepare", briefing_id: b.id });
        await reload();
      });
    if (
      ["PRONTO_PARA_ENVIO", "AGUARDANDO_CLIENTE", "EM_PREENCHIMENTO"].includes(
        b.status,
      )
    )
      action("Gerar link", async () => {
        const r = await api({
          action: "briefing.create_link",
          briefing_id: b.id,
        });
        navigator.clipboard?.writeText(r.url).catch(() => {});
        notice(
          "Link gerado. Copie-o abaixo se o navegador não permitir a cópia automática: " +
            r.url,
        );
        await reload();
      });
    if (["EM_CONFERENCIA", "AJUSTES_SOLICITADOS"].includes(b.status))
      action("Solicitar complemento", async () => {
        const q = prompt("ID da pergunta (visível na lista técnica):");
        const message = prompt("O que precisa ser complementado?");
        if (q && message) {
          await api({
            action: "briefing.request_complement",
            briefing_id: b.id,
            question_id: Number(q),
            message,
          });
          await loadDetail(b.id);
        }
      });
    if (b.status === "EM_CONFERENCIA")
      action("Aprovar", async () => {
        if (confirm("Aprovar e congelar um snapshot deste briefing?")) {
          await api({ action: "briefing.approve", briefing_id: b.id });
          await reload();
        }
      });
    return d;
  }
  function renderTemplate() {
    const host = $("template-sections");
    host.replaceChildren();
    state.template.sections.forEach((s, si) => {
      const section = document.createElement("div");
      section.className = "template-section";
      const head = document.createElement("input");
      head.placeholder = "Título da seção";
      head.value = s.title || "";
      head.oninput = (e) => (s.title = e.target.value);
      section.append(head);
      const qs = document.createElement("div");
      (s.questions || []).forEach((q, qi) => {
        const row = document.createElement("div");
        row.className = "template-question";
        const grid = document.createElement("div");
        grid.className = "template-question-grid";
        const text = document.createElement("input");
        text.placeholder = "Pergunta";
        text.value = q.text || "";
        text.oninput = (e) => (q.text = e.target.value);
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
        ].forEach((t) => type.append(option(t, t)));
        type.value = q.type || "SHORT_TEXT";
        type.onchange = (e) => (q.type = e.target.value);
        grid.append(text, type);
        const props = document.createElement("label");
        props.className = "check";
        const required = document.createElement("input");
        required.type = "checkbox";
        required.checked = !!q.required;
        required.onchange = (e) => (q.required = e.target.checked);
        props.append(required, document.createTextNode(" Obrigatória"));
        const na = document.createElement("label");
        na.className = "check";
        const check = document.createElement("input");
        check.type = "checkbox";
        check.checked = !!q.allow_not_applicable;
        check.onchange = (e) => (q.allow_not_applicable = e.target.checked);
        na.append(check, document.createTextNode(" Permite não se aplica"));
        const options = document.createElement("input");
        options.className = "question-options";
        options.placeholder = "Opções separadas por vírgula";
        options.value = (q.options || [])
          .map((o) => (typeof o === "string" ? o : o.label))
          .join(", ");
        options.oninput = (e) =>
          (q.options = e.target.value
            .split(",")
            .map((v) => v.trim())
            .filter(Boolean));
        row.append(grid, props, na, options);
        qs.append(row);
      });
      const add = document.createElement("button");
      add.type = "button";
      add.className = "text-button";
      add.textContent = "Adicionar pergunta";
      add.onclick = () => {
        s.questions.push({ type: "SHORT_TEXT", options: [] });
        renderTemplate();
      };
      section.append(qs, add);
      host.append(section);
    });
  }
  async function reload() {
    const result = await api({ action: "briefing.list" });
    state.briefings = result.briefings;
    renderKpis();
    renderList();
    if (state.current) await loadDetail(state.current.id);
  }
  async function init() {
    try {
      const r = await api({ action: "bootstrap" });
      state.csrf = r.csrf;
      state.obras = r.obras;
      state.collaborators = r.collaborators;
      fillSelect("briefing-obra", r.obras, "Selecione a obra");
      fillSelect(
        "briefing-reviewer",
        r.collaborators,
        "Qualquer pessoa interna",
      );
      fillSelect("template-reviewer", r.collaborators, "Definir por briefing");
      state.templates = r.templates;
      fillSelect("briefing-template", r.templates, "Selecione o template");
      await reload();
    } catch (e) {
      notice(e.message);
    }
  }
  $("refresh").onclick = reload;
  $("new-template").onclick = () => {
    state.template = {
      sections: [
        {
          title: "Informações gerais",
          questions: [{ type: "SHORT_TEXT", options: [] }],
        },
      ],
    };
    $("template-name").value = "";
    $("template-review").checked = true;
    $("template-reviewer").value = "";
    renderTemplate();
    $("template-dialog").showModal();
  };
  $("add-section").onclick = () => {
    state.template.sections.push({ title: "", questions: [] });
    renderTemplate();
  };
  $("save-template").onclick = async (e) => {
    e.preventDefault();
    try {
      const r = await api({
        action: "template.save",
        name: $("template-name").value,
        requires_internal_review: $("template-review").checked,
        default_reviewer_id: $("template-reviewer").value || null,
        sections: state.template.sections,
      });
      $("template-dialog").close();
      notice("Template salvo.");
      state.templates.push({
        id: r.template_id,
        name: $("template-name").value,
      });
      fillSelect("briefing-template", state.templates, "Selecione o template");
    } catch (err) {
      notice(err.message);
    }
  };
  $("new-briefing").onclick = () => {
    $("briefing-form").reset();
    $("briefing-requires-review").checked = true;
    $("briefing-dialog").showModal();
  };
  $("save-briefing").onclick = async (e) => {
    e.preventDefault();
    try {
      const r = await api({
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
      await reload();
      await loadDetail(r.briefing_id);
    } catch (err) {
      notice(err.message);
    }
  };
  window.addEventListener("briefing:realtime", () => reload().catch(() => {}));
  init();
})();
