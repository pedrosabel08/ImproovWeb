import { EmpreendimentoSpatialSettings } from "./empreendimento-spatial-settings.js";

const apiUrl = new URL("../api.php", import.meta.url);
const mapUrl = new URL("../index.php", import.meta.url);
const reducedMotion = () =>
  matchMedia("(prefers-reduced-motion: reduce)").matches;
const el = (tag, text, className) => {
  const element = document.createElement(tag);
  if (text !== undefined) element.textContent = text;
  if (className) element.className = className;
  return element;
};
const action = (label, callback, primary = false) => {
  const button = el("button", label, `btn${primary ? " primary" : ""}`);
  button.type = "button";
  button.addEventListener("click", callback);
  return button;
};
async function fade(element, from, to) {
  element.style.opacity = String(to);
  const animation = element.animate([{ opacity: from }, { opacity: to }], {
    duration: reducedMotion() ? 0 : 280,
    easing: "ease-in-out",
  });
  await animation.finished.catch(() => {});
}

/** Resolve o contexto persistido; o iframe executa o MapaPavimento original. */
export class EmpreendimentoMapBridge {
  constructor(hooks) {
    this.hooks = hooks;
    this.busy = false;
    this.mapShown = false;
    this.links = new Map();
    this.params = new URLSearchParams(location.search);
    this.obraId = Number(this.params.get("obra_id") || 74);
    this.modelId = Number(this.params.get("modelo_id") || 0);
    this.createUI();
    this.spatial = new EmpreendimentoSpatialSettings(this, hooks.spatial);
    window.addEventListener("message", (event) => this.onMessage(event));
  }

  createUI() {
    this.app = document.querySelector(".app");
    this.app.classList.add("integrated");
    this.layout = document.querySelector(".layout");
    this.background = [...this.layout.children];
    this.context = el("div", undefined, "model-context");
    const workLabel = el("label", "Obra"),
      modelLabel = el("label", "Modelo 3D");
    this.workSelect = el("select");
    this.workSelect.id = "obra3d";
    this.modelSelect = el("select");
    this.modelSelect.id = "modelo3d";
    workLabel.append(this.workSelect);
    modelLabel.append(this.modelSelect);
    this.context.append(workLabel, modelLabel);
    document
      .querySelector(".top")
      .insertBefore(this.context, document.getElementById("status"));
    this.workSelect.addEventListener("change", () => {
      this.obraId = Number(this.workSelect.value);
      this.modelId = 0;
      this.start();
    });
    this.modelSelect.addEventListener("change", () => {
      this.modelId = Number(this.modelSelect.value);
      this.loadModel().catch((error) => this.showFailure(error));
    });

    this.dialog = el("dialog", undefined, "link-dialog");
    this.dialog.id = "linkFloorDialog";
    this.dialog.setAttribute("aria-labelledby", "linkFloorTitle");
    this.dialog.innerHTML =
      '<form><h2 id="linkFloorTitle">Vincular pavimento</h2><small>Objeto 3D</small><p class="link-object" id="linkObjectName"></p><label>Pavimento da obra<select id="linkSubtype" required></select></label><p class="bridge-error" role="alert" hidden></p><footer></footer></form>';
    this.cancelButton = action("Cancelar", () => this.dialog.close());
    this.saveButton = action("Vincular", () => {}, true);
    this.saveButton.type = "submit";
    this.dialog
      .querySelector("footer")
      .append(this.cancelButton, this.saveButton);
    this.dialog.querySelector("form").addEventListener("submit", (event) => {
      event.preventDefault();
      this.saveLink();
    });
    this.dialog.addEventListener("cancel", (event) => {
      if (this.saving) event.preventDefault();
    });
    document.body.append(this.dialog);

    this.stage = el("section", undefined, "map-stage");
    this.stage.id = "architecturalMap";
    this.stage.setAttribute("aria-label", "Mapa arquitetônico do pavimento");
    this.stage.inert = true;
    const bar = el("header", undefined, "map-bar");
    this.mapTitle = el("strong");
    this.changeMapLink = action("Alterar vínculo", () =>
      this.requestReturn(true),
    );
    this.mapError = el("p", "", "bridge-error");
    this.mapError.hidden = true;
    this.mapError.setAttribute("role", "alert");
    bar.append(this.mapTitle, this.changeMapLink, this.mapError);
    this.frame = el("iframe");
    this.frame.id = "floorMapFrame";
    this.frame.title = "Mapa arquitetônico do pavimento";
    this.frame.addEventListener("load", () => {
      if (!this.pendingMap || this.frame.contentDocument?.URL === "about:blank")
        return;
      if (!this.frame.contentDocument?.getElementById("mapaPavimento")) {
        clearTimeout(this.mapTimer);
        this.pendingMap.reject(
          new Error(
            "Sua sessão expirou ou o mapa não está disponível. Entre no Flow e tente novamente.",
          ),
        );
        this.pendingMap = null;
      }
    });
    this.stage.append(bar, this.frame);
    this.layout.append(this.stage);
    this.loading = el("div", undefined, "integration-busy");
    this.loading.hidden = true;
    this.loading.setAttribute("role", "status");
    this.loading.innerHTML =
      '<canvas data-thinking-orb data-orb-state="composing" data-orb-size="64" aria-label="Carregando empreendimento"></canvas><p></p>';
    this.layout.append(this.loading);
    window.thinkingOrbs(this.loading);
    document.getElementById("backToProject").textContent =
      "← Voltar ao empreendimento";
  }

  setBusy(value, text = "Carregando") {
    this.busy = value;
    this.loading.hidden = !value;
    this.loading.querySelector("p").textContent = text;
    this.loading.querySelector("canvas").setAttribute("aria-label", text);
    this.workSelect.disabled = value || this.mapShown;
    this.modelSelect.disabled =
      value || this.mapShown || !this.modelSelect.options.length;
    document.getElementById("backToProject").disabled = value;
    this.changeMapLink.disabled = value || !this.canEdit;
    this.app.setAttribute("aria-busy", String(value));
    this.spatial?.sync();
  }

  async api(actionName, data = {}, method = "GET") {
    const url = new URL(apiUrl);
    url.searchParams.set("action", actionName);
    const options = {
      method,
      headers: { Accept: "application/json" },
      credentials: "same-origin",
    };
    const payload = { obra_id: this.obraId, ...data };
    if (method === "GET")
      Object.entries(payload).forEach(([key, value]) =>
        url.searchParams.set(key, value),
      );
    else {
      options.headers["Content-Type"] = "application/json";
      options.headers["X-CSRF-Token"] = this.csrf;
      options.body = JSON.stringify(payload);
    }
    const response = await fetch(url, options);
    const result = await response.json().catch(() => {
      throw new Error("Resposta inválida do servidor. Atualize a página.");
    });
    if (!response.ok || !result.sucesso)
      throw new Error(result.erro || "Não foi possível concluir a operação.");
    return result;
  }

  async start() {
    this.spatial.reset();
    this.setBusy(true, "Buscando obra e modelos 3D");
    try {
      const data = await this.api("modelos_3d");
      this.csrf = data.csrf;
      this.canEdit = data.can_edit;
      this.workSelect.replaceChildren(
        ...data.obras.map((work) => new Option(work.nome, work.id)),
      );
      this.workSelect.value = String(this.obraId);
      document.getElementById("projectName").textContent =
        this.workSelect.selectedOptions[0]?.textContent || "Empreendimento";
      this.models = data.modelos;
      this.modelSelect.replaceChildren(
        ...this.models.map((model) => new Option(model.nome, model.id)),
      );
      if (!this.models.some((model) => model.id === this.modelId))
        this.modelId = this.models[0]?.id || 0;
      this.modelSelect.value = String(this.modelId);
      if (!this.modelId) {
        this.hooks.clearModel();
        this.links.clear();
        this.currentFloor = null;
        this.hooks.status(
          "Esta obra ainda não possui um modelo 3D cadastrado.",
        );
        const panel = this.panel();
        panel.append(
          el("h3", "Nenhum modelo 3D cadastrado"),
          el("p", "Selecione outra obra para visualizar seu empreendimento."),
        );
        this.updateUrl();
        return;
      }
      await this.loadModel();
    } catch (error) {
      this.showFailure(error);
    } finally {
      this.setBusy(false);
    }
  }

  async refreshContext() {
    const data = await this.api("modelo_3d_contexto", {
      modelo_id: this.modelId,
    });
    this.model = data.modelo;
    this.subtypes = data.subtipos;
    this.links = new Map(data.vinculos.map((link) => [link.object_name, link]));
    return data;
  }

  async loadModel() {
    this.spatial.reset();
    this.setBusy(true, "Carregando empreendimento 3D");
    try {
      this.currentFloor = null;
      this.hooks.highlight(null);
      this.hooks.clearModel();
      const data = await this.refreshContext();
      await this.hooks.loadModel(data.modelo.src);
      void this.spatial.load();
      document.getElementById("projectName").textContent =
        this.workSelect.selectedOptions[0]?.textContent || data.modelo.nome;
      document.title = `${data.modelo.nome} · Mapa do empreendimento`;
      this.updateUrl();
      this.hooks.refreshTree();
      this.showIntro();
    } finally {
      this.setBusy(false);
    }
  }

  updateUrl() {
    const url = new URL(location.href);
    url.searchParams.set("obra_id", this.obraId);
    if (this.modelId) url.searchParams.set("modelo_id", this.modelId);
    else url.searchParams.delete("modelo_id");
    url.searchParams.delete("src");
    history.replaceState(null, "", url);
  }

  panel() {
    const panel = document.getElementById("details");
    panel.replaceChildren();
    panel.classList.add("link-summary");
    return panel;
  }

  showIntro() {
    const panel = this.panel();
    panel.append(
      el("h3", "Entre em um pavimento"),
      el(
        "p",
        "Clique no prédio ou selecione um pavimento na lista. O vínculo leva ao mapa arquitetônico cadastrado no Flow.",
      ),
    );
  }

  showFailure(error) {
    this.hooks.status(error.message, true);
    const panel = this.panel();
    panel.append(
      el("h3", "Não foi possível abrir o empreendimento"),
      el("p", error.message, "bridge-error"),
    );
    const actions = el("div", undefined, "link-actions");
    actions.append(action("Tentar novamente", () => this.start()));
    const login = el("a", "Entrar no Flow", "btn");
    login.href = new URL("../../index.html", import.meta.url);
    actions.append(login);
    panel.append(actions);
  }

  showSummary(floor) {
    const link = this.links.get(floor.id),
      panel = this.panel();
    panel.append(el("h3", floor.id));
    panel.append(
      el(
        "p",
        link
          ? `Vinculado a: ${link.subtipo_nome}`
          : "Este objeto 3D ainda não está vinculado a um pavimento da obra.",
      ),
    );
    const actions = el("div", undefined, "link-actions");
    if (link) {
      if (link.mapa_configurado)
        actions.append(
          action("Entrar no mapa", () => this.openMap(floor), true),
        );
      else {
        panel.append(
          el(
            "p",
            `O pavimento ${link.subtipo_nome} ainda não possui um Mapa de Pavimento configurado.`,
          ),
        );
        if (this.canEdit) {
          const configure = el("a", "Configurar mapa", "btn primary");
          const url = new URL(mapUrl);
          url.search = new URLSearchParams({
            obra_id: this.obraId,
            subtipo_id: link.subtipo_id,
          });
          configure.href = url.href;
          configure.target = "_blank";
          configure.rel = "noopener";
          configure.title = "Configurar mapa em uma nova aba";
          actions.append(configure);
        }
      }
    }
    if (this.canEdit)
      actions.append(
        action(
          link ? "Alterar vínculo" : "Vincular pavimento",
          () => this.showLinkDialog(floor),
          !link,
        ),
      );
    else if (!link)
      panel.append(
        el(
          "p",
          "Solicite a um usuário com permissão de edição que configure o vínculo.",
        ),
      );
    panel.append(actions);
  }

  async activateFloor(floor) {
    if (this.busy || this.mapShown) return;
    this.currentFloor = floor;
    this.hooks.highlight(floor);
    this.showSummary(floor);
    if (!this.links.has(floor.id)) {
      if (this.canEdit) this.showLinkDialog(floor);
      return;
    }
    await this.openMap(floor);
  }

  showLinkDialog(floor) {
    if (this.busy || !this.canEdit) return;
    this.currentFloor = floor;
    this.hooks.highlight(floor);
    const link = this.links.get(floor.id);
    this.dialog.querySelector("#linkObjectName").textContent = floor.id;
    const select = this.dialog.querySelector("select");
    select.replaceChildren(
      new Option("Selecione o pavimento", ""),
      ...this.subtypes.map((subtype) => new Option(subtype.nome, subtype.id)),
    );
    select.value = link ? String(link.subtipo_id) : "";
    this.dialog.querySelector("h2").textContent = link
      ? "Alterar vínculo"
      : "Vincular pavimento";
    this.saveButton.textContent = link ? "Salvar vínculo" : "Vincular";
    this.saveButton.disabled = !this.subtypes.length;
    const error = this.dialog.querySelector('[role="alert"]');
    error.hidden = !!this.subtypes.length;
    error.textContent = this.subtypes.length
      ? ""
      : "Esta obra ainda não possui subtipos associados a imagens ou mapas.";
    this.dialog.showModal();
    select.focus();
  }

  async saveLink() {
    if (this.saving) return;
    this.saving = true;
    const floor = this.currentFloor,
      link = this.links.get(floor.id),
      error = this.dialog.querySelector('[role="alert"]');
    this.saveButton.disabled = this.cancelButton.disabled = true;
    this.dialog.querySelector("select").disabled = true;
    error.hidden = true;
    try {
      const data = await this.api(
        "modelo_3d_vincular",
        {
          modelo_id: this.modelId,
          object_name: floor.id,
          subtipo_id: Number(this.dialog.querySelector("select").value),
          versao: link?.versao || 0,
        },
        "POST",
      );
      this.links = new Map(
        data.vinculos.map((item) => [item.object_name, item]),
      );
      this.dialog.close();
      this.hooks.refreshTree();
      this.showSummary(floor);
      this.hooks.status(
        "Vínculo salvo. Selecione Entrar no mapa para continuar.",
      );
    } catch (failure) {
      error.textContent = failure.message;
      error.hidden = false;
    } finally {
      this.saving = false;
      this.saveButton.disabled = this.cancelButton.disabled = false;
      this.dialog.querySelector("select").disabled = false;
    }
  }

  prepareFrame(link) {
    this.bridgeId = crypto.randomUUID();
    this.expectedLink = link;
    const url = new URL(mapUrl);
    url.search = new URLSearchParams({
      embed: "3d",
      obra_id: this.obraId,
      subtipo_id: link.subtipo_id,
      bridge: this.bridgeId,
    });
    this.frame.src = url.href;
    return new Promise((resolve, reject) => {
      this.pendingMap = { resolve, reject };
      this.mapTimer = setTimeout(() => {
        this.pendingMap = null;
        reject(
          new Error(
            "O mapa demorou para carregar. Verifique a sessão e tente novamente.",
          ),
        );
      }, 45000);
    });
  }

  onMessage(event) {
    const data = event.data;
    if (
      event.origin !== location.origin ||
      event.source !== this.frame.contentWindow ||
      data?.bridge !== this.bridgeId ||
      Number(data.obra_id) !== this.obraId ||
      Number(data.subtipo_id) !== this.expectedLink?.subtipo_id
    )
      return;
    if (
      data.type === "mp:ready" &&
      this.pendingMap &&
      Number(data.referencia_id) === this.expectedLink.referencia_id
    ) {
      clearTimeout(this.mapTimer);
      this.pendingMap.resolve();
      this.pendingMap = null;
    } else if (data.type === "mp:error" && this.pendingMap) {
      clearTimeout(this.mapTimer);
      this.pendingMap.reject(new Error(data.message));
      this.pendingMap = null;
    } else if (
      this.pendingLeave &&
      ["mp:leave-ok", "mp:leave-blocked"].includes(data.type)
    ) {
      clearTimeout(this.leaveTimer);
      this.pendingLeave(data);
      this.pendingLeave = null;
    }
  }

  async openMap(floor) {
    if (this.busy) return;
    this.setBusy(true, "Preparando o mapa arquitetônico");
    try {
      await this.refreshContext();
      const link = this.links.get(floor.id);
      this.showSummary(floor);
      if (!link?.mapa_configurado) return;
      this.currentFloor = floor;
      this.mapError.hidden = true;
      this.mapTitle.textContent = `${link.subtipo_nome} · Mapa arquitetônico`;
      this.changeMapLink.hidden = !this.canEdit;
      const results = await Promise.allSettled([
        this.prepareFrame(link),
        this.hooks.enterFloor(floor),
      ]);
      const failure = results.find((result) => result.status === "rejected");
      if (failure) throw failure.reason;
      this.mapShown = true;
      this.app.classList.add("map-open");
      this.background.forEach((element) => {
        element.inert = true;
      });
      this.stage.inert = false;
      this.stage.classList.add("is-visible");
      await fade(this.stage, 0, 1);
      this.hooks.setMapVisible(true);
      document.getElementById("modeLabel").textContent =
        `Mapa arquitetônico · ${link.subtipo_nome}`;
      document.getElementById("backToProject").focus();
      this.hooks.status(`Mapa do ${link.subtipo_nome}`);
    } catch (error) {
      clearTimeout(this.mapTimer);
      this.hooks.setMapVisible(false);
      this.frame.removeAttribute("src");
      await this.hooks.leaveFloor();
      this.hooks.highlight(floor);
      this.showSummary(floor);
      this.panelError(error.message);
      this.hooks.status(error.message, true);
    } finally {
      this.setBusy(false);
    }
  }

  panelError(text) {
    const error = el("p", text, "bridge-error");
    error.setAttribute("role", "alert");
    document.getElementById("details").append(error);
  }

  async requestReturn(editLink = false) {
    if (this.busy || this.returning || !this.mapShown) return;
    this.returning = true;
    const response = await new Promise((resolve) => {
      this.pendingLeave = resolve;
      this.leaveTimer = setTimeout(() => {
        this.pendingLeave = null;
        resolve({
          type: "mp:leave-blocked",
          message:
            "Não foi possível verificar a edição do mapa. Aguarde e tente novamente.",
        });
      }, 5000);
      this.frame.contentWindow.postMessage(
        { type: "mp:request-leave", bridge: this.bridgeId },
        location.origin,
      );
    });
    if (response.type !== "mp:leave-ok") {
      this.returning = false;
      this.mapError.textContent = response.message;
      this.mapError.hidden = false;
      return;
    }
    const floor = this.currentFloor;
    this.setBusy(true, "Voltando ao empreendimento");
    try {
      this.hooks.setMapVisible(false);
      await fade(this.stage, 1, 0);
      this.stage.classList.remove("is-visible");
      this.stage.inert = true;
      this.background.forEach((element) => {
        element.inert = false;
      });
      this.mapShown = false;
      this.app.classList.remove("map-open");
      this.frame.removeAttribute("src");
      this.expectedLink = null;
      await this.hooks.leaveFloor();
      this.currentFloor = null;
      this.showIntro();
    } finally {
      this.returning = false;
      this.setBusy(false);
    }
    if (editLink) {
      this.currentFloor = floor;
      this.showSummary(floor);
      this.showLinkDialog(floor);
    }
  }
}
