const apiUrl = new URL("../api.php", import.meta.url);
const element = (tag, text, className) => {
  const node = document.createElement(tag);
  if (text !== undefined) node.textContent = text;
  if (className) node.className = className;
  return node;
};
const mb = (bytes) =>
  bytes < 1048576
    ? `${Math.max(1, Math.ceil(bytes / 1024))} KB`
    : `${(bytes / 1048576).toLocaleString("pt-BR", { maximumFractionDigits: 1 })} MB`;

/** Envio e recuperação de versões sem alterar os vínculos do empreendimento. */
export class ModelUpload {
  constructor(bridge) {
    this.bridge = bridge;
    this.actions = element("div", undefined, "model-actions");
    this.uploadButton = this.button("Enviar modelo 3D", () => this.open());
    this.historyButton = this.button("Versões", () => this.openHistory());
    this.actions.append(this.uploadButton, this.historyButton);
    bridge.context.append(this.actions);
    this.notice = element("p", "", "model-upload-notice");
    this.notice.setAttribute("role", "status");
    this.notice.hidden = true;
    bridge.context.append(this.notice);

    this.dialog = element(
      "dialog",
      undefined,
      "link-dialog model-upload-dialog",
    );
    this.dialog.setAttribute("aria-labelledby", "modelUploadTitle");
    this.dialog.innerHTML = `<form>
      <h2 id="modelUploadTitle">Enviar modelo 3D</h2>
      <p class="spatial-work"></p>
      <label>Destino<select name="modelo_id" aria-label="Destino do modelo"></select></label>
      <label class="model-name-label">Nome do modelo<input name="nome" maxlength="150" required autocomplete="off" placeholder="Ex.: Modelo completo"></label>
      <label>Arquivo GLB<input name="glb" type="file" accept=".glb,model/gltf-binary" required aria-describedby="modelUploadHelp modelFileInfo"></label>
      <p id="modelUploadHelp" class="spatial-help"></p>
      <p id="modelFileInfo" class="spatial-help" aria-live="polite"></p>
      <p class="model-version-help spatial-help" hidden>A nova versão será ativada após a validação. Os vínculos dos pavimentos serão preservados e a versão anterior continuará no histórico.</p>
      <div class="model-upload-progress" role="status" hidden><canvas data-thinking-orb data-orb-state="working" data-orb-size="48" aria-label="Enviando modelo 3D"></canvas><span></span></div>
      <p class="bridge-error" role="alert" hidden></p>
      <footer><button class="btn" type="button" data-close>Cancelar</button><button class="btn primary" type="submit">Enviar modelo</button></footer>
    </form>`;
    document.body.append(this.dialog);
    window.thinkingOrbs(this.dialog);
    this.form = this.dialog.querySelector("form");
    this.target = this.form.elements.modelo_id;
    this.name = this.form.elements.nome;
    this.file = this.form.elements.glb;
    this.error = this.dialog.querySelector(".bridge-error");
    this.progress = this.dialog.querySelector(".model-upload-progress");
    this.target.addEventListener("change", () => this.syncTarget());
    this.file.addEventListener("change", () => {
      const file = this.file.files[0];
      this.dialog.querySelector("#modelFileInfo").textContent = file
        ? `${file.name} · ${mb(file.size)}`
        : "";
      if (file && !this.name.value)
        this.name.value = file.name.replace(/\.glb$/i, "").slice(0, 150);
      this.file.setCustomValidity(
        file && file.size > bridge.uploadMax
          ? `O arquivo ultrapassa o limite de ${mb(bridge.uploadMax)}.`
          : "",
      );
    });
    this.form.addEventListener("submit", (event) => {
      event.preventDefault();
      void this.send();
    });
    this.dialog
      .querySelector("[data-close]")
      .addEventListener("click", () => this.dialog.close());
    this.dialog.addEventListener("cancel", (event) => {
      if (this.sending) event.preventDefault();
    });

    this.history = element(
      "dialog",
      undefined,
      "link-dialog model-upload-dialog",
    );
    this.history.setAttribute("aria-labelledby", "modelHistoryTitle");
    this.history.innerHTML =
      '<h2 id="modelHistoryTitle">Versões do modelo</h2><p class="spatial-work"></p><div class="model-upload-progress" role="status" hidden><canvas data-thinking-orb data-orb-state="composing" data-orb-size="48" aria-label="Buscando versões do modelo"></canvas><span>Buscando versões…</span></div><div class="model-version-list"></div><p class="bridge-error" role="alert" hidden></p><footer><button type="button" class="btn">Fechar</button></footer>';
    this.history
      .querySelector("footer button")
      .addEventListener("click", () => this.history.close());
    this.history.addEventListener("cancel", (event) => {
      if (this.restoring) event.preventDefault();
    });
    document.body.append(this.history);
    window.thinkingOrbs(this.history);
    this.sync();
  }

  button(label, callback) {
    const button = element("button", label, "btn");
    button.type = "button";
    button.addEventListener("click", callback);
    return button;
  }

  sync() {
    const bridge = this.bridge;
    this.actions.hidden = !bridge.canEdit;
    this.uploadButton.disabled =
      bridge.busy || bridge.mapShown || !bridge.obraId;
    this.historyButton.disabled = this.uploadButton.disabled || !bridge.modelId;
  }

  open() {
    if (this.uploadButton.disabled) return;
    this.form.reset();
    this.file.setCustomValidity("");
    this.error.hidden = true;
    this.notice.hidden = true;
    this.dialog.querySelector(".spatial-work").textContent =
      this.bridge.workSelect.selectedOptions[0]?.textContent;
    this.dialog.querySelector("#modelUploadHelp").textContent =
      `GLB com texturas incorporadas, até ${mb(this.bridge.uploadMax)}. Preserve a nomenclatura PAVTO dos pavimentos.`;
    this.dialog.querySelector("#modelFileInfo").textContent = "";
    this.target.replaceChildren(new Option("Novo modelo nesta obra", "0"));
    if (this.bridge.modelId)
      this.target.append(
        new Option(
          `Nova versão de ${this.bridge.model?.nome || this.bridge.modelSelect.selectedOptions[0]?.textContent}`,
          String(this.bridge.modelId),
        ),
      );
    this.expectedFile = this.bridge.model?.arquivo;
    this.syncTarget();
    this.dialog.showModal();
  }

  syncTarget() {
    const update = this.target.value !== "0";
    this.name.disabled = update;
    this.dialog.querySelector(".model-name-label").hidden = update;
    this.dialog.querySelector(".model-version-help").hidden = !update;
    this.dialog.querySelector("[type=submit]").textContent = update
      ? "Enviar nova versão"
      : "Enviar modelo";
  }

  setSending(value, message = "") {
    this.sending = value;
    this.dialog.setAttribute("aria-busy", String(value));
    for (const control of this.form.elements) control.disabled = value;
    if (!value) this.syncTarget();
    this.progress.hidden = !value;
    this.progress.querySelector("span").textContent = message;
  }

  async send() {
    if (this.sending) return;
    const file = this.file.files[0];
    if (!file || !/\.glb$/i.test(file.name)) {
      this.showError(this.error, "Selecione um arquivo .glb.");
      return;
    }
    const data = new FormData(this.form);
    data.set("obra_id", String(this.bridge.obraId));
    if (this.target.value !== "0")
      data.set("arquivo_atual", this.expectedFile || "");
    this.error.hidden = true;
    this.setSending(true, "Enviando modelo 3D…");
    try {
      const result = await new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        const url = new URL(apiUrl);
        url.searchParams.set("action", "modelo_3d_upload");
        request.open("POST", url);
        request.setRequestHeader("X-CSRF-Token", this.bridge.csrf);
        request.setRequestHeader("Accept", "application/json");
        request.timeout = 600000;
        request.upload.onprogress = (event) => {
          this.progress.querySelector("span").textContent =
            event.lengthComputable
              ? `Enviando modelo 3D · ${Math.round((event.loaded / event.total) * 100)}%`
              : "Enviando modelo 3D…";
        };
        request.upload.onload = () => {
          this.progress.querySelector("span").textContent =
            "Validando e salvando o modelo…";
        };
        request.onerror = () =>
          reject(
            new Error(
              "A conexão foi interrompida. Verifique se o modelo foi cadastrado antes de reenviar.",
            ),
          );
        request.ontimeout = () =>
          reject(
            new Error(
              "O envio demorou mais que o esperado. Recarregue os modelos para verificar se foi concluído.",
            ),
          );
        request.onload = () => {
          let result;
          try {
            result = JSON.parse(request.responseText);
          } catch {
            reject(
              new Error(
                request.status === 413
                  ? "O GLB ultrapassa o limite de envio do servidor. Ajuste o limite no VPS."
                  : "O servidor não concluiu o envio. Atualize a página e tente novamente.",
              ),
            );
            return;
          }
          if (request.status < 200 || request.status >= 300 || !result.sucesso)
            reject(new Error(result.erro || "Não foi possível enviar o GLB."));
          else resolve(result);
        };
        request.send(data);
      });
      this.dialog.close();
      this.bridge.modelId = result.id;
      await this.bridge.start();
      this.notice.textContent = result.duplicado
        ? `Este arquivo já está na versão ${result.numero}. Consulte “Versões” para ativá-la.`
        : `Modelo enviado · versão ${result.numero} · ${result.pavimentos.length} pavimentos identificados.`;
      this.notice.hidden = false;
    } catch (error) {
      this.showError(this.error, error.message);
    } finally {
      this.setSending(false);
    }
  }

  showError(node, message) {
    node.textContent = message;
    node.hidden = false;
  }

  async openHistory() {
    if (this.historyButton.disabled) return;
    const list = this.history.querySelector(".model-version-list");
    const errorNode = this.history.querySelector(".bridge-error");
    errorNode.hidden = true;
    this.history.querySelector(".spatial-work").textContent =
      this.bridge.model?.nome ||
      this.bridge.modelSelect.selectedOptions[0]?.textContent;
    list.replaceChildren();
    const loading = this.history.querySelector(".model-upload-progress");
    loading.hidden = false;
    this.history.showModal();
    try {
      const data = await this.bridge.api("modelo_3d_versoes", {
        modelo_id: this.bridge.modelId,
      });
      if (!this.history.open) return;
      list.replaceChildren();
      if (!data.versoes.length)
        list.append(
          element(
            "p",
            "Modelo original. O histórico será criado quando você enviar a primeira atualização.",
            "spatial-help",
          ),
        );
      for (const version of data.versoes) {
        const row = element("article", undefined, "model-version-row");
        const details = element("div");
        details.append(
          element(
            "strong",
            `Versão ${version.numero}${version.ativa ? " · Atual" : ""}`,
          ),
          element("p", version.nome_original),
          element(
            "small",
            `${mb(version.tamanho_bytes)} · ${version.criado_em}`,
          ),
        );
        row.append(details);
        if (!version.ativa)
          row.append(
            this.button("Usar esta versão", () =>
              this.activate(version.id, data.arquivo_atual, errorNode),
            ),
          );
        list.append(row);
      }
    } catch (error) {
      list.replaceChildren();
      this.showError(errorNode, error.message);
    } finally {
      loading.hidden = true;
    }
  }

  async activate(versionId, currentFile, errorNode) {
    if (this.restoring) return;
    this.restoring = true;
    errorNode.hidden = true;
    for (const button of this.history.querySelectorAll("button"))
      button.disabled = true;
    this.history.setAttribute("aria-busy", "true");
    try {
      await this.bridge.api(
        "modelo_3d_ativar_versao",
        {
          modelo_id: this.bridge.modelId,
          versao_id: versionId,
          arquivo_atual: currentFile,
        },
        "POST",
      );
      this.history.close();
      await this.bridge.start();
      this.notice.textContent =
        "Versão ativada. Os vínculos dos pavimentos foram preservados.";
      this.notice.hidden = false;
    } catch (error) {
      this.showError(errorNode, error.message);
    } finally {
      this.restoring = false;
      for (const button of this.history.querySelectorAll("button"))
        button.disabled = false;
      this.history.setAttribute("aria-busy", "false");
    }
  }
}
