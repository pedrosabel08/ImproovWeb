(() => {
  const modal = document.getElementById("renderEfModal");
  const dialog = modal.querySelector(".render-ef-dialog");
  const button = document.getElementById("renderHighResolution");
  const byId = (id) => document.getElementById(id);
  let renderId,
    request,
    timer,
    destroyOrb,
    generation = 0;
  let retryAction = "prepare";
  const stages = {
    FILA: [0, "Aguardando o worker"],
    INSPECAO: [0, "Conferindo o Job e o .max"],
    PASTA: [1, "Conferindo a pasta EF"],
    REVISAO: [1, "Confira antes de enviar"],
    CRIANDO_PASTA: [2, "Preparando a pasta EF"],
    RECUPERANDO: [2, "Recuperando a origem"],
    CONFIGURACOES: [2, "Preparando as configurações EF"],
    SUBMETENDO: [2, "Criando o novo Job"],
    SUBMISSAO_INCERTA: [2, "Confira o Deadline Monitor"],
    VALIDANDO: [2, "Validando o novo Job suspenso"],
    VINCULANDO: [3, "Registrando o render EF"],
    LIBERANDO: [4, "Liberando para renderização"],
    CONCLUIDO: [4, "Render EF na fila do Deadline"],
  };
  function orb(show, state = "composing") {
    destroyOrb?.();
    destroyOrb = null;
    const canvas = byId("renderEfOrb");
    canvas.hidden = !show;
    canvas.dataset.orbState = state;
    canvas.setAttribute("aria-label", byId("renderEfStage").textContent);
    if (show) destroyOrb = window.thinkingOrbs?.(canvas);
  }
  function message(title, text, error = false) {
    byId("renderEfStage").textContent = title;
    byId("renderEfMessage").textContent = text;
    modal.classList.toggle("has-error", error);
  }
  async function api(action, post = false) {
    const data = { action, render_id: renderId };
    if (post)
      Object.assign(data, {
        csrf: dialog.dataset.csrf,
        request_id: request?.id || "",
        plan_hash: request?.plan_hash || "",
      });
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 20000);
    try {
      const response = await fetch(
        `render_ef.php${post ? "" : "?" + new URLSearchParams(data)}`,
        {
          method: post ? "POST" : "GET",
          credentials: "same-origin",
          cache: "no-store",
          signal: controller.signal,
          ...(post
            ? {
                headers: {
                  "Content-Type": "application/x-www-form-urlencoded",
                },
                body: new URLSearchParams(data),
              }
            : {}),
        },
      );
      const payload = await response.json();
      if (!response.ok || payload.status !== "sucesso")
        throw new Error(
          payload.message || "Não foi possível consultar o render EF.",
        );
      return payload;
    } finally {
      clearTimeout(timeout);
    }
  }
  function details() {
    const list = byId("renderEfDetails");
    list.replaceChildren();
    list.hidden = !request;
    if (!request) return;
    const plan = request.plan || {};
    const rows = [
      ["Job original", request.origem_job_id],
      ["Novo Job EF", request.novo_job_id],
      ["Nome do novo Job", plan.job_name],
      ["Cena original", plan.scene],
      ["Frames", plan.frames],
      [
        "Resolução anterior",
        plan.old_width ? `${plan.old_width} × ${plan.old_height} px` : "",
      ],
      [
        "Nova resolução",
        plan.width
          ? `${plan.width} × ${plan.height} px${plan.rounding ? " · arredondada ao pixel mais próximo" : ""}`
          : "",
      ],
      ["Câmera", plan.camera],
      ["Renderer", plan.renderer],
      ["Pasta EF", plan.destination],
      ["Saída principal", plan.outputs?.RenderOutput],
      [
        "Render Elements",
        plan.outputs
          ? `${Object.keys(plan.outputs).filter((key) => key !== "RenderOutput").length} saídas direcionadas à pasta EF`
          : "",
      ],
    ];
    for (const [label, value] of rows) {
      if (!value) continue;
      const term = document.createElement("dt"),
        description = document.createElement("dd");
      term.textContent = label;
      description.textContent = value;
      list.append(term, description);
    }
  }
  function display(payload) {
    request = payload.request;
    byId("renderEfWorkerWarning").hidden =
      payload.worker_online ||
      ["PRONTO", "CONCLUIDO", "ERRO", "ATENCAO"].includes(request?.status);
    const pending = ["PENDENTE", "EXECUTAR", "PROCESSANDO"].includes(
      request?.status,
    );
    byId("renderEfBackground").hidden = !pending;
    const [step, title] = stages[request?.etapa] || [0, "Conferindo o render"];
    message(
      request?.status === "ERRO" ? "Envio interrompido" : title,
      request?.mensagem || "Preparando a inspeção do render.",
      ["ERRO", "ATENCAO"].includes(request?.status),
    );
    orb(
      pending,
      ["INSPECAO", "PASTA"].includes(request?.etapa)
        ? "searching"
        : "composing",
    );
    modal.querySelectorAll("[data-ef-step]").forEach((item) => {
      const index = Number(item.dataset.efStep);
      item.classList.toggle(
        "is-done",
        index < step || request?.status === "CONCLUIDO",
      );
      item.classList.toggle(
        "is-current",
        index === step && request?.status !== "CONCLUIDO",
      );
      if (index === step) item.setAttribute("aria-current", "step");
      else item.removeAttribute("aria-current");
    });
    byId("renderEfConfirm").hidden = request?.status !== "PRONTO";
    byId("renderEfNotice").hidden = request?.status !== "PRONTO";
    byId("renderEfOpen").hidden =
      request?.status !== "CONCLUIDO" || !request?.render_ef_id;
    retryAction = request?.can_resume ? "resume" : "prepare";
    byId("renderEfRetry").textContent =
      retryAction === "resume"
        ? "Retomar o mesmo Job EF"
        : "Conferir novamente";
    byId("renderEfRetry").hidden = !(
      request?.status === "ERRO" ||
      (request?.status === "ATENCAO" && request?.can_resume)
    );
    details();
    if (pending) timer = setTimeout(() => run("status"), 1500);
    if (request?.status === "CONCLUIDO") {
      loadRenders(1);
      loadRenderKpis();
    }
  }
  async function run(action, post = false) {
    const current = generation;
    clearTimeout(timer);
    ["renderEfConfirm", "renderEfRetry"].forEach((id) => {
      byId(id).disabled = true;
    });
    orb(true);
    try {
      let payload = await api(action, post);
      if (current !== generation || modal.hidden) return;
      if (!payload.request && action === "status")
        payload = await api("prepare", true);
      if (current !== generation || modal.hidden) return;
      display(payload);
    } catch (error) {
      if (current !== generation || modal.hidden) return;
      orb(false);
      message(
        "Não foi possível atualizar a solicitação",
        error.name === "AbortError"
          ? "A consulta demorou mais que o esperado. Confira o estado novamente; isso não fará outro envio."
          : error.message,
        true,
      );
      byId("renderEfConfirm").hidden = true;
      byId("renderEfRetry").hidden = false;
      byId("renderEfRetry").textContent = "Consultar estado novamente";
      retryAction = "status";
    } finally {
      if (current === generation)
        ["renderEfConfirm", "renderEfRetry"].forEach((id) => {
          byId(id).disabled = false;
        });
    }
  }
  function close() {
    generation++;
    clearTimeout(timer);
    orb(false);
    modal.hidden = true;
    byId("myModal").inert = false;
    button.focus();
  }
  button.addEventListener("click", () => {
    if (button.disabled) return;
    fecharMenuMaisAcoes();
    renderId = byId("modal_idrender").textContent.trim();
    request = null;
    generation++;
    modal.hidden = false;
    byId("myModal").inert = true;
    [
      "renderEfConfirm",
      "renderEfRetry",
      "renderEfOpen",
      "renderEfDetails",
      "renderEfNotice",
      "renderEfBackground",
      "renderEfWorkerWarning",
    ].forEach((id) => {
      byId(id).hidden = true;
    });
    message(
      "Conferindo o render",
      "Consultando a solicitação e verificando a disponibilidade do Job original.",
    );
    byId("renderEfClose").focus();
    run("status");
  });
  byId("renderEfClose").addEventListener("click", close);
  byId("renderEfCancel").addEventListener("click", close);
  byId("renderEfConfirm").addEventListener("click", () => run("confirm", true));
  byId("renderEfRetry").addEventListener("click", () =>
    run(retryAction, retryAction !== "status"),
  );
  byId("renderEfOpen").addEventListener("click", () => {
    const id = request.render_ef_id;
    close();
    editRender(id);
  });
  modal.addEventListener("click", (event) => {
    if (event.target === modal) close();
  });
  modal.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      event.preventDefault();
      event.stopPropagation();
      close();
    }
    if (event.key !== "Tab") return;
    const controls = [
      ...modal.querySelectorAll("button:not([hidden]):not(:disabled)"),
    ].filter((item) => item.offsetParent !== null);
    const first = controls[0],
      last = controls[controls.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });
})();
