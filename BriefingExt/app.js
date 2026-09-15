(() => {
  const state = {
    token: window.BRIEFING_ACCESS_TOKEN || "",
    csrf: "",
    participant: null,
    briefing: null,
    section: 0,
    answers: new Map(),
    operations: new Map(),
    remoteRequests: new Map(),
    feedbackTimers: new Map(),
    pendingStructuralRefresh: false,
    conflictDialog: null,
  };
  const $ = (id) => document.getElementById(id);
  const setNotice = (message) => {
    $("notice").textContent = message || "";
  };
  const uuid = () =>
    crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
  const api = async (data, csrf = false, options = {}) => {
    const controller = new AbortController();
    const timeout = setTimeout(
      () => controller.abort(),
      options.timeout || 15000,
    );
    let response;
    try {
      response = await fetch("api.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          ...(csrf ? { "X-Briefing-CSRF": state.csrf } : {}),
        },
        body: JSON.stringify(data),
        signal: controller.signal,
      });
    } catch (error) {
      const message =
        error.name === "AbortError"
          ? "A solicitação demorou demais."
          : "Não foi possível conectar ao servidor.";
      const networkError = new Error(message);
      networkError.network = true;
      throw networkError;
    } finally {
      clearTimeout(timeout);
    }
    const json = await response.json().catch(() => ({}));
    if (!response.ok || !json.ok) {
      if (
        csrf &&
        !options.csrfRetried &&
        json.message === "Token CSRF inválido."
      ) {
        const refreshed = await api(
          { action: "csrf.refresh", token: state.token },
          false,
          { ...options, csrfRetried: true },
        );
        state.csrf = refreshed.csrf;
        return api(data, true, { ...options, csrfRetried: true });
      }
      const error = new Error(
        json.message || "Não foi possível concluir a operação.",
      );
      error.status = response.status;
      error.data = json;
      throw error;
    }
    return json;
  };
  const setSave = (text, kind = "") => {
    const element = $("save-state");
    const labels = {
      Salvo: "Todas as alterações salvas",
      "Salvando…": "Salvando…",
      "Sincronizando…": "Sincronizando…",
      "Alteração pendente": "Não foi possível salvar uma alteração",
      Conflito: "Uma resposta precisa ser revisada",
      Enviado: "Briefing enviado",
      "Sem conexão": "Não foi possível salvar uma alteração",
    };
    element.textContent = labels[text] || text;
    element.className = `save-state ${kind}`;
  };
  const requestCode = async (action) => {
    const result = await api({
      action,
      token: state.token,
      email: $("email").value,
      name: $("name")?.value,
      role: $("role")?.value,
      phone: $("phone")?.value,
    });
    if (result.next === "register") {
      $("start-form").hidden = true;
      $("register-form").hidden = false;
      $("access-copy").textContent =
        "Complete seus dados para receber o código de acesso.";
      return;
    }
    $("start-form").hidden = true;
    $("register-form").hidden = true;
    $("verify-form").hidden = false;
    $("access-copy").textContent = "Enviamos um código para seu e-mail.";
  };
  $("start-form").onsubmit = async (event) => {
    event.preventDefault();
    try {
      await requestCode("access.start");
      setNotice("");
    } catch (error) {
      setNotice(error.message);
    }
  };
  $("register-form").onsubmit = async (event) => {
    event.preventDefault();
    try {
      await requestCode("access.register");
      setNotice("");
    } catch (error) {
      setNotice(error.message);
    }
  };
  $("back-email").onclick = () => {
    $("register-form").hidden = true;
    $("start-form").hidden = false;
  };
  $("again").onclick = () => $("start-form").requestSubmit();
  $("verify-form").onsubmit = async (event) => {
    event.preventDefault();
    try {
      const result = await api({
        action: "access.verify",
        token: state.token,
        email: $("email").value,
        code: $("code").value,
      });
      state.csrf = result.csrf;
      $("access").hidden = true;
      $("briefing-app").hidden = false;
      await bootstrap();
    } catch (error) {
      setNotice(error.message);
    }
  };
  async function bootstrap({ force = false } = {}) {
    if (!force && state.briefing && hasLocalWork()) {
      state.pendingStructuralRefresh = true;
      return false;
    }
    try {
      setSave("Sincronizando…");
      const result = await api({
        action: "briefing.bootstrap",
        token: state.token,
      });
      state.csrf = result.csrf || state.csrf;
      state.participant = result.participant || state.participant;
      hydrateAnswers(result.briefing);
      state.briefing = result.briefing;
      render();
      setSave("Salvo", "saved");
      window.FlowBriefingWS?.connect?.(() =>
        api({ action: "ws.ticket", token: state.token }, true).then(
          (item) => item.ticket,
        ),
      );
    } catch (error) {
      setNotice(error.message);
      setSave("Sem conexão", "error");
    }
  }
  const cloneValue = (value) => {
    if (value === undefined) return null;
    if (value === null || typeof value !== "object") return value;
    return JSON.parse(JSON.stringify(value));
  };
  const sameValue = (left, right) =>
    JSON.stringify(left ?? null) === JSON.stringify(right ?? null);
  const questions = () =>
    state.briefing?.sections.flatMap((section) => section.questions) || [];
  const findQuestion = (questionId) =>
    questions().find((question) => String(question.id) === String(questionId));
  const normalizeAnswer = (answer = {}) => ({
    id: answer.id ? Number(answer.id) : null,
    value: cloneValue(answer.value),
    notApplicable: !!(answer.not_applicable ?? answer.notApplicable),
    version: Number(answer.version || 0),
    updatedAt: answer.updated_at || answer.updatedAt || null,
    updatedBy:
      answer.updated_by ||
      answer.updatedBy ||
      (answer.author
        ? { name: answer.author, participant_id: answer.author_id }
        : null),
  });
  function answerState(question) {
    const key = String(question.id);
    let current = state.answers.get(key);
    if (!current) {
      const answer = normalizeAnswer(question.answer);
      current = {
        value: cloneValue(answer.value),
        notApplicable: answer.notApplicable,
        persistedValue: cloneValue(answer.value),
        persistedNotApplicable: answer.notApplicable,
        version: answer.version,
        dirty: false,
        saving: false,
        status: "clean",
        error: null,
        retryItem: null,
        saveAgain: false,
        remoteUpdatePending: false,
        remoteVersion: null,
        remoteAnswer: null,
        conflict: null,
      };
      state.answers.set(key, current);
    }
    return current;
  }
  function answerStateChanged(current) {
    current.dirty =
      !sameValue(current.value, current.persistedValue) ||
      current.notApplicable !== current.persistedNotApplicable;
    if (!current.dirty && !current.saving && current.status !== "saved") {
      current.status = "clean";
    }
  }
  function questionCard(questionId) {
    return document.querySelector(`[data-question-id="${String(questionId)}"]`);
  }
  function isQuestionFocused(questionId) {
    const card = questionCard(questionId);
    return !!card?.contains(document.activeElement);
  }
  function hasLocalWork() {
    return [...state.answers.entries()].some(
      ([questionId, current]) =>
        current.dirty || current.saving || isQuestionFocused(questionId),
    );
  }
  function setQuestionStatus(questionId, status, errorMessage = "") {
    const card = questionCard(questionId);
    if (!card) return;
    const node = card.querySelector(".answer-save-status");
    if (!node) return;
    const labels = {
      dirty: "Alterado",
      saving: "Salvando…",
      saved: "✓ Salvo",
      error: "Erro ao salvar",
      conflict: "Conflito",
      clean: "",
    };
    node.className = `answer-save-status ${status}`;
    node.replaceChildren();
    const label = document.createElement("span");
    label.textContent = errorMessage || (labels[status] ?? status);
    node.append(label);
    if (status === "error") {
      const retry = document.createElement("button");
      retry.type = "button";
      retry.className = "retry-answer";
      retry.textContent = "Tentar novamente";
      retry.onclick = () => retryQuestion(questionId);
      node.append(retry);
    }
    if (status === "saved") {
      clearTimeout(state.feedbackTimers.get(String(questionId)));
      state.feedbackTimers.set(
        String(questionId),
        setTimeout(() => {
          const current = state.answers.get(String(questionId));
          if (current?.status === "saved") {
            current.status = "clean";
            setQuestionStatus(questionId, "clean");
          }
        }, 3500),
      );
    }
  }
  function rememberRemote(current, answer) {
    const remote = normalizeAnswer(answer);
    if (remote.version <= current.version) return;
    current.remoteUpdatePending = true;
    current.remoteVersion = remote.version;
    current.remoteAnswer = remote;
  }
  function updateQuestionControl(question) {
    const current = answerState(question);
    const card = questionCard(question.id);
    if (!card) return;
    const control = card.querySelector("[data-answer-control]");
    if (control?.classList.contains("choices")) {
      const values = Array.isArray(current.value) ? current.value : [];
      control.querySelectorAll("input").forEach((input) => {
        input.checked = values.includes(input.value);
      });
    } else if (control) {
      control.value = current.value ?? "";
    }
    const notApplicable = card.querySelector("[data-not-applicable]");
    if (notApplicable) notApplicable.checked = current.notApplicable;
  }
  function applyAnswerSnapshot(question, answer, feedback = "saved") {
    const remote = normalizeAnswer(answer);
    const current = answerState(question);
    current.value = cloneValue(remote.value);
    current.notApplicable = remote.notApplicable;
    current.persistedValue = cloneValue(remote.value);
    current.persistedNotApplicable = remote.notApplicable;
    current.version = remote.version;
    current.dirty = false;
    current.saving = false;
    current.error = null;
    current.retryItem = null;
    current.remoteUpdatePending = false;
    current.remoteVersion = null;
    current.remoteAnswer = null;
    current.conflict = null;
    question.answer = {
      ...(question.answer || {}),
      id: remote.id,
      value: cloneValue(remote.value),
      not_applicable: remote.notApplicable,
      version: remote.version,
      updated_at: remote.updatedAt,
      author: remote.updatedBy?.name || question.answer?.author || null,
      author_id:
        remote.updatedBy?.participant_id || question.answer?.author_id || null,
    };
    updateQuestionControl(question);
    current.status = feedback;
    setQuestionStatus(question.id, feedback);
    if (feedback === "saved") setSave("Salvo", "saved");
  }
  function hydrateAnswers(briefing) {
    briefing.sections
      .flatMap((section) => section.questions)
      .forEach((question) => {
        const incoming = normalizeAnswer(question.answer);
        const current = state.answers.get(String(question.id));
        if (!current) {
          answerState(question);
        } else if (!current.dirty && !current.saving) {
          applyAnswerSnapshot(question, incoming, "clean");
        } else if (incoming.version > current.version) {
          rememberRemote(current, incoming);
        }
      });
  }
  function valueOf(question, element) {
    if (question.tipo === "MULTI_SELECT")
      return [...element.querySelectorAll("input:checked")].map(
        (input) => input.value,
      );
    if (question.tipo === "SINGLE_SELECT")
      return element.querySelector("input:checked")?.value || "";
    if (question.tipo === "YES_NO")
      return element.value === "sim"
        ? true
        : element.value === "nao"
          ? false
          : "";
    return element.value;
  }
  function recordLocalChange(
    question,
    value,
    notApplicable,
    immediate = false,
  ) {
    if (!question.editable) return;
    const current = answerState(question);
    current.value = cloneValue(value);
    current.notApplicable =
      notApplicable === undefined ? current.notApplicable : !!notApplicable;
    current.error = null;
    current.conflict = null;
    answerStateChanged(current);
    current.status = current.dirty ? "dirty" : "clean";
    setQuestionStatus(question.id, current.status);
    if (immediate && current.dirty) {
      saveQuestion(question, { immediate: true });
    }
  }
  function fieldFor(question) {
    const current = answerState(question);
    const value = current.value;
    let validation = {};
    if (question.validacao_json) {
      try {
        validation =
          typeof question.validacao_json === "string"
            ? JSON.parse(question.validacao_json)
            : question.validacao_json;
      } catch (_error) {
        validation = {};
      }
    }
    validation = validation && typeof validation === "object" ? validation : {};
    let field;
    if (question.tipo === "LONG_TEXT") {
      field = document.createElement("textarea");
      field.value = value || "";
    } else if (question.tipo === "YES_NO") {
      field = document.createElement("select");
      field.append(
        new Option("Selecione", ""),
        new Option("Sim", "sim"),
        new Option("Não", "nao"),
      );
      field.value = value === true ? "sim" : value === false ? "nao" : "";
    } else if (["SINGLE_SELECT", "MULTI_SELECT"].includes(question.tipo)) {
      field = document.createElement("div");
      field.className = "choices";
      question.options.forEach((option) => {
        const label = document.createElement("label");
        const input = document.createElement("input");
        input.type = question.tipo === "MULTI_SELECT" ? "checkbox" : "radio";
        input.name = `q-${question.id}`;
        input.value = option.value;
        input.checked = Array.isArray(value)
          ? value.includes(option.value)
          : value === option.value;
        input.disabled = !question.editable;
        input.onchange = () =>
          recordLocalChange(
            question,
            valueOf(question, field),
            undefined,
            true,
          );
        label.append(input, document.createTextNode(option.label));
        field.append(label);
      });
    } else {
      field = document.createElement("input");
      field.type =
        question.tipo === "NUMBER"
          ? "number"
          : question.tipo === "DATE"
            ? "date"
            : question.tipo === "LINK"
              ? "url"
              : "text";
      field.value = value || "";
    }
    if (validation.placeholder && "placeholder" in field) {
      field.placeholder = validation.placeholder;
    }
    if (question.tipo === "NUMBER") {
      if (validation.min !== undefined && validation.min !== "")
        field.min = validation.min;
      if (validation.max !== undefined && validation.max !== "")
        field.max = validation.max;
    }
    if (question.tipo === "DATE") {
      if (validation.min) field.min = validation.min;
      if (validation.max) field.max = validation.max;
    }
    field.dataset.answerControl = "true";
    field.disabled = !question.editable;
    if (question.tipo === "YES_NO") {
      field.onchange = () =>
        recordLocalChange(question, valueOf(question, field), undefined, true);
    } else if (!["SINGLE_SELECT", "MULTI_SELECT"].includes(question.tipo)) {
      field.oninput = () =>
        recordLocalChange(question, valueOf(question, field));
      field.onblur = () => handleBlur(question);
    }
    return field;
  }
  const initials = (name = "") =>
    name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join("").toUpperCase() || "FL";
  const answered = (question) => {
    const current = answerState(question);
    return current.notApplicable || (current.value !== null && current.value !== undefined && current.value !== "" && (!Array.isArray(current.value) || current.value.length));
  };
  const sectionStats = (section) => {
    const total = section.questions.length;
    const complete = section.questions.filter(answered).length;
    return { total, complete, done: total > 0 && total === complete };
  };
  const requestFor = (questionId) =>
    (state.briefing?.requests || []).find((request) => Number(request.briefing_question_id) === Number(questionId));
  const setAvatar = (element, name, small = false) => {
    element.className = `avatar${small ? " small" : ""}`;
    element.textContent = initials(name);
  };
  function updateProgressUi() {
    const progress = state.briefing.progress || { percent: 0, answered: 0, total: 0 };
    const percent = Number(progress.percent || 0);
    $("progress-fill").style.width = `${percent}%`;
    $("progress-label").textContent = `${progress.answered} de ${progress.total} perguntas respondidas`;
    $("progress-percent").textContent = `${percent}%`;
    $("progress-ring").style.setProperty("--progress", `${percent}%`);
    $("progress-detail").textContent = `${progress.answered} de ${progress.total} perguntas respondidas`;
    $("progress-message").textContent = percent >= 75 ? "Você está quase lá!" : percent ? "Seu projeto está tomando forma" : "Vamos começar";
    $("summary-answers").textContent = `${progress.answered} de ${progress.total}`;
    const completedSections = state.briefing.sections.filter((section) => sectionStats(section).done).length;
    $("summary-sections").textContent = `${completedSections} de ${state.briefing.sections.length}`;
  }
  function goToSection(index) {
    state.section = Math.max(0, Math.min(index, state.briefing.sections.length - 1));
    render();
    $("form").scrollIntoView({ behavior: "smooth", block: "start" });
  }
  function renderAuthor(question, side) {
    const answer = question.answer || {};
    const authorName = answer.author || answer.updated_by?.name;
    if (!authorName) return;
    const author = document.createElement("div");
    author.className = "answer-author";
    const avatar = document.createElement("span");
    setAvatar(avatar, authorName, true);
    const copy = document.createElement("span");
    const name = document.createElement("strong");
    name.textContent = authorName;
    copy.append(name, document.createTextNode(`atualizou ${formatUpdatedAt(answer.updated_at || answer.updatedAt)}`));
    author.append(avatar, copy);
    side.append(author);
  }
  function render() {
    const briefing = state.briefing;
    if (!briefing?.sections?.length) return;
    state.section = Math.min(state.section, briefing.sections.length - 1);
    const participantName = state.participant?.name || "Participante";
    $("title").textContent = briefing.nome_obra || briefing.titulo;
    $("subtitle").textContent = `${briefing.temporal_status || ""}`.replaceAll("_", " ").toLowerCase();
    $("project-monogram").textContent = initials(briefing.nome_obra || briefing.titulo);
    $("welcome-title").textContent = `Olá, ${participantName.split(" ")[0]}!`;
    $("welcome-copy").textContent = `Preparamos algumas perguntas para entender melhor o que você imagina para ${briefing.nome_obra || briefing.titulo}.`;
    const chip = $("participant-chip");
    chip.replaceChildren();
    const avatar = document.createElement("span");
    setAvatar(avatar, participantName);
    chip.append(avatar, document.createTextNode(participantName));
    const currentSection = briefing.sections[state.section];
    $("mobile-section-count").textContent = `Seção ${state.section + 1} de ${briefing.sections.length}`;
    $("mobile-section-name").textContent = currentSection.titulo;
    updateProgressUi();
    $("sections").replaceChildren(
      ...briefing.sections.map((section, index) => {
        const stats = sectionStats(section);
        const hasRequest = section.questions.some((question) => requestFor(question.id));
        const button = document.createElement("button");
        button.type = "button";
        button.className = `section-nav-button${index === state.section ? " active" : ""}${stats.done ? " complete" : ""}${hasRequest ? " has-request" : ""}`;
        button.setAttribute("aria-current", index === state.section ? "step" : "false");
        button.innerHTML = `<span class="nav-order">${index + 1}.</span><span class="nav-title"></span><span class="nav-progress">${stats.done ? "✓" : `${stats.complete} / ${stats.total}`}</span>`;
        button.querySelector(".nav-title").textContent = section.titulo;
        button.onclick = () => goToSection(index);
        return button;
      }),
    );
    const request = currentSection.questions.map((question) => ({ question, request: requestFor(question.id) })).find((item) => item.request);
    $("request-summary").hidden = !request;
    if (request) {
      $("request-title").textContent = currentSection.titulo;
      $("request-copy").textContent = request.request.mensagem || `Verifique a pergunta: ${request.question.pergunta}`;
    }
    const form = $("form");
    form.replaceChildren();
    const heading = document.createElement("div");
    heading.className = "section-heading";
    const icon = document.createElement("span");
    icon.className = "section-heading-icon";
    icon.textContent = "✦";
    const headingCopy = document.createElement("div");
    const headingTitle = document.createElement("h2");
    headingTitle.textContent = `${state.section + 1}. ${currentSection.titulo}`;
    headingCopy.append(headingTitle);
    heading.append(icon, headingCopy);
    form.append(heading);
    if (!currentSection.questions.length) {
      const empty = document.createElement("p");
      empty.className = "answer-meta";
      empty.textContent = "Esta etapa não possui perguntas no momento.";
      form.append(empty);
    }
    currentSection.questions.forEach((question, index) => {
      const card = document.createElement("div");
      card.className = "question";
      card.dataset.questionId = String(question.id);
      const number = document.createElement("span");
      number.className = "question-number";
      number.textContent = String(index + 1).padStart(2, "0");
      const main = document.createElement("div");
      main.className = "question-main";
      const label = document.createElement("label");
      label.textContent = question.pergunta;
      if (question.obrigatoria) label.append(Object.assign(document.createElement("span"), { className: "required", textContent: " *" }));
      main.append(label);
      if (question.ajuda) main.append(Object.assign(document.createElement("p"), { className: "help", textContent: question.ajuda }));
      main.append(fieldFor(question));
      if (question.permite_nao_aplica) {
        const notApplicable = document.createElement("label");
        notApplicable.className = "na";
        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.dataset.notApplicable = "true";
        checkbox.checked = answerState(question).notApplicable;
        checkbox.disabled = !question.editable;
        checkbox.onchange = () => recordLocalChange(question, checkbox.checked ? null : valueOf(question, card.querySelector("[data-answer-control]")), checkbox.checked, true);
        notApplicable.append(checkbox, document.createTextNode(" Não se aplica"));
        main.append(notApplicable);
      }
      const side = document.createElement("div");
      side.className = "answer-side";
      const status = document.createElement("div");
      status.className = "answer-save-status";
      side.append(status);
      renderAuthor(question, side);
      card.append(number, main, side);
      const questionRequest = requestFor(question.id);
      if (questionRequest) card.append(Object.assign(document.createElement("p"), { className: "request", textContent: `Complemento solicitado: ${questionRequest.mensagem || "Revise esta resposta."}` }));
      if (!question.editable) card.append(Object.assign(document.createElement("p"), { className: "answer-meta", textContent: "Resposta disponível somente para leitura." }));
      form.append(card);
      setQuestionStatus(question.id, answerState(question).status);
    });
    const editable = briefing.sections.flatMap((sectionItem) => sectionItem.questions).some((question) => question.editable);
    $("submit").disabled = !editable || !["AGUARDANDO_CLIENTE", "EM_PREENCHIMENTO", "AJUSTES_SOLICITADOS"].includes(briefing.status);
    $("previous-section").disabled = state.section === 0;
    $("previous-section").onclick = () => goToSection(state.section - 1);
    const last = state.section === briefing.sections.length - 1;
    $("next-section").textContent = last ? "Enviar briefing" : "Continuar →";
    $("next-section").onclick = () => last ? $("submit").click() : goToSection(state.section + 1);
    $("toggle-sections").onclick = () => {
      const sidebar = document.querySelector(".project-sidebar");
      const open = sidebar.classList.toggle("mobile-open");
      $("toggle-sections").setAttribute("aria-expanded", String(open));
    };
  }
  async function saveQuestion(
    question,
    { force = false, immediate = false } = {},
  ) {
    if (!question?.editable) return false;
    const current = answerState(question);
    if (current.saving) {
      current.saveAgain = current.saveAgain || immediate;
      return current.promise || false;
    }
    if (!force && !current.dirty) return true;
    const item = {
      id: uuid(),
      question,
      value: cloneValue(current.value),
      notApplicable: current.notApplicable,
      expected: current.version,
      immediate,
    };
    state.operations.set(String(question.id), item);
    current.saving = true;
    current.status = "saving";
    current.error = null;
    setQuestionStatus(question.id, "saving");
    setSave("Salvando…");
    item.promise = send(item);
    current.promise = item.promise;
    return item.promise;
  }
  async function send(item) {
    const key = String(item.question.id);
    if (state.operations.get(key)?.id !== item.id) return false;
    try {
      const result = await api(
        {
          action: "answer.save",
          token: state.token,
          question_id: item.question.id,
          value: item.value,
          not_applicable: item.notApplicable,
          expected_version: item.expected,
          operation_uuid: item.id,
        },
        true,
      );
      const current = answerState(item.question);
      const answer = normalizeAnswer(result.answer);
      const changedWhileSaving =
        !sameValue(current.value, item.value) ||
        current.notApplicable !== item.notApplicable;
      item.question.answer = {
        ...(item.question.answer || {}),
        id: answer.id,
        value: cloneValue(answer.value),
        not_applicable: answer.notApplicable,
        version: answer.version,
        updated_at: answer.updatedAt,
        author: state.participant?.name || item.question.answer?.author || null,
      };
      current.persistedValue = cloneValue(answer.value);
      current.persistedNotApplicable = answer.notApplicable;
      current.version = answer.version;
      current.saving = false;
      current.promise = null;
      current.retryItem = null;
      current.conflict = null;
      state.operations.delete(key);
      if (!changedWhileSaving) {
        current.value = cloneValue(answer.value);
        current.notApplicable = answer.notApplicable;
      }
      answerStateChanged(current);
      current.status = current.dirty ? "dirty" : "saved";
      setQuestionStatus(item.question.id, current.status);
      if (result.progress) {
        state.briefing.progress = result.progress;
        updateProgressUi();
      }
      setSave(
        current.dirty ? "Alteração pendente" : "Salvo",
        current.dirty ? "error" : "saved",
      );
      const saveAgain = current.saveAgain && current.dirty;
      current.saveAgain = false;
      await applyPendingRemoteIfSafe(item.question);
      await processPendingStructuralRefresh();
      if (saveAgain) return saveQuestion(item.question, { immediate: true });
      return true;
    } catch (error) {
      const current = answerState(item.question);
      current.saving = false;
      current.promise = null;
      state.operations.delete(key);
      if (error.data?.code === "VERSION_CONFLICT" || error.data?.conflict) {
        current.status = "conflict";
        current.conflict = error.data;
        setQuestionStatus(item.question.id, "conflict");
        setSave("Conflito", "error");
        showConflict(item.question, error.data);
      } else {
        current.status = "error";
        current.error = error.message;
        current.retryItem = item;
        setQuestionStatus(
          item.question.id,
          "error",
          "Não foi possível salvar.",
        );
        setNotice(error.message);
        setSave("Alteração pendente", "error");
      }
      return false;
    }
  }
  async function retryQuestion(questionId) {
    const question = findQuestion(questionId);
    const current = question && answerState(question);
    if (!question || !current) return;
    const item = current.retryItem;
    if (
      item &&
      sameValue(item.value, current.value) &&
      item.notApplicable === current.notApplicable
    ) {
      state.operations.set(String(question.id), item);
      current.saving = true;
      current.status = "saving";
      setQuestionStatus(question.id, "saving");
      item.promise = send(item);
      current.promise = item.promise;
      return;
    }
    await saveQuestion(question, { immediate: true });
  }
  async function handleBlur(question) {
    const current = answerState(question);
    if (!current.dirty && current.remoteUpdatePending) {
      await applyPendingRemoteIfSafe(question);
    } else if (current.dirty) {
      await saveQuestion(question);
    }
    await processPendingStructuralRefresh();
  }
  async function flush(questionId) {
    const question = findQuestion(questionId);
    if (!question) return true;
    const current = answerState(question);
    if (!current.dirty) return true;
    return saveQuestion(question, { force: true });
  }
  function formatAnswer(answer) {
    if (answer?.notApplicable) return "Não se aplica";
    if (Array.isArray(answer?.value))
      return answer.value.join(", ") || "(vazio)";
    if (
      answer?.value === null ||
      answer?.value === undefined ||
      answer.value === ""
    )
      return "(vazio)";
    return String(answer.value);
  }
  function formatUpdatedAt(value) {
    if (!value) return "agora";
    const date = new Date(String(value).replace(" ", "T") + "Z");
    return Number.isNaN(date.getTime())
      ? "agora"
      : date.toLocaleString("pt-BR");
  }
  function showConflict(question, data) {
    state.conflictDialog?.remove();
    const conflict = data.conflict || data;
    const current = answerState(question);
    const dialog = document.createElement("dialog");
    dialog.className = "conflict-dialog";
    const title = document.createElement("h2");
    title.textContent = "Esta resposta foi alterada enquanto você respondia";
    const explanation = document.createElement("p");
    explanation.textContent = "Escolha qual versão deseja manter.";
    const currentBlock = document.createElement("div");
    currentBlock.className = "conflict-version current";
    currentBlock.innerHTML = "<strong>Resposta atual</strong>";
    const currentText = document.createElement("p");
    currentText.textContent = formatAnswer({
      value: data.current_value ?? conflict.value,
      notApplicable: conflict.not_applicable,
    });
    currentBlock.append(currentText);
    const currentMeta = document.createElement("small");
    currentMeta.textContent = `${conflict.updated_by?.name ? `Alterada por ${conflict.updated_by.name}` : "Alterada remotamente"} · versão ${data.current_version ?? conflict.version} · ${formatUpdatedAt(conflict.updated_at || data.updated_at)}`;
    currentBlock.append(currentMeta);
    const localBlock = document.createElement("div");
    localBlock.className = "conflict-version local";
    localBlock.innerHTML = "<strong>Sua resposta</strong>";
    const localText = document.createElement("p");
    localText.textContent = formatAnswer({
      value: current.value,
      notApplicable: current.notApplicable,
    });
    localBlock.append(localText);
    const actions = document.createElement("div");
    actions.className = "conflict-actions";
    const keep = document.createElement("button");
    keep.type = "button";
    keep.className = "secondary";
    keep.textContent = "Manter resposta atual";
    const useMine = document.createElement("button");
    useMine.type = "button";
    useMine.textContent = "Usar minha resposta";
    keep.onclick = () => {
      applyAnswerSnapshot(question, {
        value: data.current_value ?? conflict.value,
        not_applicable: conflict.not_applicable,
        version: data.current_version ?? conflict.version,
        updated_at: conflict.updated_at || data.updated_at,
        updated_by: conflict.updated_by || data.updated_by,
      });
      dialog.close();
      dialog.remove();
      state.conflictDialog = null;
      setNotice("");
      processPendingStructuralRefresh();
    };
    useMine.onclick = async () => {
      keep.disabled = true;
      useMine.disabled = true;
      const localValue = cloneValue(current.value);
      const localNotApplicable = current.notApplicable;
      current.persistedValue = cloneValue(data.current_value ?? conflict.value);
      current.persistedNotApplicable = !!conflict.not_applicable;
      current.version = Number(data.current_version ?? conflict.version);
      current.value = localValue;
      current.notApplicable = localNotApplicable;
      current.remoteUpdatePending = false;
      current.remoteAnswer = null;
      current.dirty = true;
      dialog.close();
      dialog.remove();
      state.conflictDialog = null;
      await saveQuestion(question, { force: true, immediate: true });
    };
    actions.append(keep, useMine);
    dialog.append(title, explanation, currentBlock, localBlock, actions);
    document.body.append(dialog);
    state.conflictDialog = dialog;
    dialog.showModal();
  }
  async function fetchRemoteAnswer(questionId, announcedVersion) {
    const key = String(questionId);
    if (state.remoteRequests.has(key)) return state.remoteRequests.get(key);
    const promise = api({
      action: "answer.get",
      token: state.token,
      question_id: questionId,
    })
      .then(async (result) => {
        state.remoteRequests.delete(key);
        const question = findQuestion(questionId);
        if (!question) return;
        const current = answerState(question);
        const answer = normalizeAnswer(result.answer);
        if (answer.version <= current.version) return;
        if (current.dirty || current.saving || isQuestionFocused(questionId)) {
          rememberRemote(current, answer);
        } else {
          applyAnswerSnapshot(question, answer);
        }
        if (result.progress) {
          state.briefing.progress = result.progress;
          updateProgressUi();
        }
      })
      .catch((error) => {
        state.remoteRequests.delete(key);
        setNotice(
          `Não foi possível sincronizar a pergunta ${questionId}. ${error.message}`,
        );
      });
    state.remoteRequests.set(key, promise);
    return promise;
  }
  async function applyPendingRemoteIfSafe(question) {
    const current = answerState(question);
    if (current.dirty || current.saving || isQuestionFocused(question.id))
      return;
    if (current.remoteAnswer) {
      applyAnswerSnapshot(question, current.remoteAnswer);
      return;
    }
    if (current.remoteUpdatePending)
      await fetchRemoteAnswer(question.id, current.remoteVersion);
  }
  async function processPendingStructuralRefresh() {
    if (!state.pendingStructuralRefresh || hasLocalWork()) return;
    state.pendingStructuralRefresh = false;
    await bootstrap({ force: true });
  }
  $("submit").onclick = async () => {
    try {
      if (!confirm("Concluir e enviar este briefing para conferência?")) return;
      for (const question of questions()) {
        if (!(await flush(question.id))) return;
      }
      const result = await api(
        { action: "briefing.submit", token: state.token },
        true,
      );
      state.briefing.status = result.status;
      render();
      setSave("Enviado", "saved");
      setNotice(
        result.status === "APROVADO"
          ? "Briefing concluído e aprovado."
          : "Briefing enviado para conferência.",
      );
    } catch (error) {
      setNotice(error.message);
    }
  };
  window.addEventListener("briefing:realtime", (event) => {
    const payload = event.detail || {};
    if (Number(payload.briefing_id) !== Number(state.briefing?.id)) return;
    if (payload.event === "answer.updated") {
      const questionId = payload.metadata?.question_id;
      const version = Number(payload.metadata?.version || 0);
      if (!questionId) return;
      const question = findQuestion(questionId);
      if (!question) return;
      const current = answerState(question);
      if (version && version <= current.version) return;
      if (current.dirty || current.saving || isQuestionFocused(questionId)) {
        current.remoteUpdatePending = true;
        current.remoteVersion = version || current.remoteVersion;
        fetchRemoteAnswer(questionId, version);
      } else {
        fetchRemoteAnswer(questionId, version);
      }
      return;
    }
    if (
      [
        "briefing.status_updated",
        "briefing.submitted",
        "briefing.approved",
        "question.complement_requested",
        "briefing.access.revoked",
      ].includes(payload.event)
    ) {
      if (hasLocalWork()) {
        state.pendingStructuralRefresh = true;
        setNotice(
          "Há uma atualização remota aguardando o término da sua edição.",
        );
      } else {
        bootstrap({ force: true });
      }
    }
  });
  window.addEventListener("beforeunload", (event) => {
    if ([...state.answers.values()].some((current) => current.dirty)) {
      event.preventDefault();
      event.returnValue = "";
    }
  });
  async function inspectAccess() {
    if (!state.token) {
      setNotice("Link de acesso inválido.");
      return;
    }
    try {
      const result = await api({
        action: "access.inspect",
        token: state.token,
      });
      if (result.authenticated) {
        $("access").hidden = true;
        $("briefing-app").hidden = false;
        await bootstrap();
      }
    } catch (error) {
      setNotice(error.message);
      $("start-form").querySelector("button").disabled = true;
    }
  }
  inspectAccess();
})();
