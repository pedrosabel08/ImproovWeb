import { dialNorth, normalizeNorth } from "./north-direction.js";
const formatNorth = (value) =>
  value == null ? "Não definido" : value.toFixed(1) + "°";

/** Obra, API e permissões do bridge existente; rascunhos apenas no formulário. */
export class EmpreendimentoSpatialSettings {
  constructor(bridge, hooks) {
    Object.assign(this, { bridge, hooks });
    this.generation = 0;
    this.button = document.createElement("button");
    this.button.type = "button";
    this.button.className = "btn spatial-button";
    this.button.textContent = "⚙ Configurações espaciais";
    this.button.disabled = true;
    this.button.hidden = true;
    bridge.context.after(this.button);
    this.button.addEventListener("click", () => this.open());
    this.dialog = document.createElement("dialog");
    this.dialog.className = "link-dialog spatial-dialog";
    this.dialog.setAttribute("aria-labelledby", "spatialTitle");
    this.dialog.innerHTML =
      '<form id="spatialForm"><h2 id="spatialTitle">Configurações espaciais</h2><p class="spatial-work"></p><label>Latitude<input id="spatialLatitude" type="text" inputmode="decimal" autocomplete="off" placeholder="Não definida" aria-describedby="spatialLatitudeHelp"></label><small id="spatialLatitudeHelp">Coordenada decimal entre −90 e 90.</small><label>Longitude<input id="spatialLongitude" type="text" inputmode="decimal" autocomplete="off" placeholder="Não definida" aria-describedby="spatialLongitudeHelp"></label><small id="spatialLongitudeHelp">Coordenada decimal entre −180 e 180.</small><label>Fuso horário<input id="spatialTimezone" type="text" autocomplete="off" placeholder="America/Sao_Paulo" aria-describedby="spatialTimezoneHelp"></label><small id="spatialTimezoneHelp">Use o fuso do empreendimento, por exemplo America/Sao_Paulo ou Europe/Lisbon.</small><div class="spatial-north"><span>Norte do empreendimento</span><strong id="spatialNorthValue"></strong><button type="button" class="btn" id="spatialDefine">Definir no modelo</button></div><p class="bridge-error" role="alert" hidden></p><p class="spatial-help">Campos vazios permanecem não definidos. As alterações serão gravadas ao salvar.</p><footer><button type="button" class="btn" id="spatialCancel">Cancelar</button><button type="submit" class="btn primary" id="spatialSave">Salvar</button></footer></form>';
    document.body.append(this.dialog);
    this.form = this.dialog.querySelector("form");
    this.latitude = this.dialog.querySelector("#spatialLatitude");
    this.longitude = this.dialog.querySelector("#spatialLongitude");
    this.timezone = this.dialog.querySelector("#spatialTimezone");
    this.error = this.dialog.querySelector(".bridge-error");
    this.defineButton = this.dialog.querySelector("#spatialDefine");
    this.saveButton = this.dialog.querySelector("#spatialSave");
    this.cancelButton = this.dialog.querySelector("#spatialCancel");
    this.defineButton.addEventListener("click", () => this.beginNorth());
    this.cancelButton.addEventListener("click", () => this.cancel());
    this.dialog.addEventListener("cancel", (event) => {
      event.preventDefault();
      if (!this.saving) this.cancel();
    });
    this.form.addEventListener("submit", (event) => {
      event.preventDefault();
      this.save();
    });
    this.panel = document.createElement("section");
    this.panel.className = "north-editor";
    this.panel.hidden = true;
    this.panel.setAttribute("aria-labelledby", "northEditorTitle");
    this.panel.innerHTML =
      '<h2 id="northEditorTitle">Definindo Norte</h2><p>Gire o controle até a seta sobre o empreendimento apontar para o Norte verdadeiro.</p><div class="north-dial" aria-hidden="true"><span class="dial-z">−Z</span><span class="dial-x">+X</span><span class="dial-arrow">↑<b>N</b></span><span class="dial-center"></span></div><label for="northRange">Orientação: <output id="northDegrees" for="northRange"></output></label><input id="northRange" type="range" min="0" max="359.9" step="0.1" aria-describedby="northHelp"><small id="northHelp">0° = −Z · 90° = +X. Use o círculo ou as setas do teclado. A câmera permanece parada.</small><footer><button type="button" class="btn" id="northCancel">Cancelar</button><button type="button" class="btn primary" id="northConfirm">Confirmar</button></footer>';
    document.getElementById("viewport").append(this.panel);
    this.range = this.panel.querySelector("input");
    this.range.addEventListener("input", () =>
      this.preview(Number(this.range.value)),
    );
    const dial = this.panel.querySelector(".north-dial");
    const rotate = (event) => {
      const rect = dial.getBoundingClientRect();
      const x = event.clientX - rect.left - rect.width / 2,
        y = event.clientY - rect.top - rect.height / 2;
      if (Math.hypot(x, y) > 5) this.preview(dialNorth(x, y));
    };
    dial.addEventListener("pointerdown", (event) => {
      if (event.button !== 0) return;
      event.preventDefault();
      dial.setPointerCapture(event.pointerId);
      rotate(event);
    });
    dial.addEventListener("pointermove", (event) => {
      if (dial.hasPointerCapture(event.pointerId)) rotate(event);
    });
    dial.addEventListener("pointerup", (event) => {
      if (dial.hasPointerCapture(event.pointerId))
        dial.releasePointerCapture(event.pointerId);
    });
    this.panel
      .querySelector("#northCancel")
      .addEventListener("click", () => this.finishNorth(false));
    this.panel
      .querySelector("#northConfirm")
      .addEventListener("click", () => this.finishNorth(true));
    document.addEventListener("keydown", (event) => {
      if (this.defining && event.key === "Escape") {
        event.preventDefault();
        this.finishNorth(false);
      }
    });
  }
  sync() {
    this.button.hidden = !this.bridge.canEdit;
    this.button.disabled =
      !this.bridge.canEdit ||
      this.bridge.busy ||
      this.bridge.mapShown ||
      !this.ready ||
      this.saving ||
      this.defining;
  }
  reset() {
    ++this.generation;
    this.ready = false;
    this.saved = null;
    this.hooks.setNorth(null);
    this.hooks.setConfig?.(null);
    this.sync();
  }
  async load() {
    const generation = ++this.generation,
      obraId = this.bridge.obraId;
    this.loadError = null;
    try {
      const data = await this.bridge.api("espacial_configuracao");
      if (generation !== this.generation || obraId !== this.bridge.obraId)
        return;
      this.saved = data.configuracao;
      this.hooks.setNorth(this.saved.norte_graus);
      this.hooks.setConfig?.(this.saved);
    } catch (error) {
      if (generation !== this.generation || obraId !== this.bridge.obraId)
        return;
      this.loadError = error.message;
      this.saved = null;
      this.hooks.setNorth(null);
      this.hooks.setConfig?.(null, this.loadError);
    }
    this.ready = true;
    this.sync();
  }
  async open() {
    if (!this.bridge.canEdit || this.button.disabled) return;
    this.button.disabled = true;
    const obraId = this.bridge.obraId,
      modelId = this.bridge.modelId;
    await this.load();
    if (obraId !== this.bridge.obraId || modelId !== this.bridge.modelId)
      return;
    if (this.bridge.busy || this.bridge.mapShown || !this.ready) return;
    this.draft = {
      ...(this.saved || { latitude: null, longitude: null, norte_graus: null }),
    };
    this.latitude.value = this.draft.latitude ?? "";
    this.longitude.value = this.draft.longitude ?? "";
    this.timezone.value = this.draft.timezone || "America/Sao_Paulo";
    this.latitude.readOnly =
      this.longitude.readOnly =
      this.timezone.readOnly =
        !this.bridge.canEdit;
    this.defineButton.hidden = this.saveButton.hidden = !this.bridge.canEdit;
    this.saveButton.disabled = this.defineButton.disabled = !!this.loadError;
    this.dialog.querySelector(".spatial-work").textContent =
      this.bridge.workSelect.selectedOptions[0]?.textContent || "Obra";
    this.message(this.loadError || "");
    this.updateNorthValue();
    this.dialog.showModal();
  }
  message(text) {
    this.error.textContent = text;
    this.error.hidden = !text;
  }
  updateNorthValue() {
    this.dialog.querySelector("#spatialNorthValue").textContent = formatNorth(
      this.draft.norte_graus,
    );
    this.defineButton.textContent =
      this.draft.norte_graus == null ? "Definir no modelo" : "Redefinir";
  }
  cancel() {
    if (this.saving) return;
    this.dialog.close();
    this.hooks.setNorth(this.saved?.norte_graus ?? null);
    this.draft = null;
    this.button.focus();
  }
  beginNorth() {
    if (!this.bridge.canEdit || this.loadError) return;
    this.defining = true;
    this.previewDegrees = this.draft.norte_graus ?? 0;
    this.dialog.close();
    this.panel.hidden = false;
    this.inertElements = [
      ...document.querySelectorAll(".top, .side, .viewtools, .floor-switcher"),
    ].map((element) => [element, element.inert]);
    this.inertElements.forEach(([element]) => {
      element.inert = true;
    });
    this.hooks.setDefining(true);
    this.preview(this.previewDegrees);
    this.sync();
    this.range.focus();
  }
  preview(value) {
    this.previewDegrees = normalizeNorth(value);
    this.range.value = this.previewDegrees;
    this.panel.querySelector("output").value = formatNorth(this.previewDegrees);
    this.range.setAttribute("aria-valuetext", formatNorth(this.previewDegrees));
    this.panel.querySelector(".dial-arrow").style.transform =
      "rotate(" + this.previewDegrees + "deg)";
    this.hooks.setNorth(this.previewDegrees, true);
  }
  finishNorth(confirm) {
    if (!this.defining) return;
    if (confirm) this.draft.norte_graus = this.previewDegrees;
    this.defining = false;
    this.panel.hidden = true;
    this.inertElements.forEach(([element, inert]) => {
      element.inert = inert;
    });
    this.hooks.setDefining(false);
    this.hooks.setNorth(this.draft.norte_graus);
    this.updateNorthValue();
    this.sync();
    this.dialog.showModal();
    this.defineButton.focus();
  }
  coordinate(field, min, max, name) {
    const text = field.value.trim().replace(",", ".");
    if (!text) return null;
    if (
      !/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/.test(text) ||
      !Number.isFinite(Number(text)) ||
      Number(text) < min ||
      Number(text) > max
    ) {
      field.focus();
      throw new Error(
        name +
          " deve ser uma coordenada decimal entre " +
          min +
          " e " +
          max +
          ".",
      );
    }
    return Number(text);
  }
  async save() {
    if (this.saving || !this.bridge.canEdit || this.loadError) return;
    let values;
    try {
      values = {
        ...this.draft,
        latitude: this.coordinate(this.latitude, -90, 90, "Latitude"),
        longitude: this.coordinate(this.longitude, -180, 180, "Longitude"),
        timezone: this.timezone.value.trim(),
      };
      try {
        new Intl.DateTimeFormat("pt-BR", { timeZone: values.timezone });
      } catch {
        this.timezone.focus();
        throw new Error(
          "Informe um fuso horário válido, como America/Sao_Paulo.",
        );
      }
    } catch (error) {
      this.message(error.message);
      return;
    }
    this.saving = true;
    this.message("");
    this.form.querySelectorAll("button, input").forEach((element) => {
      element.disabled = true;
    });
    this.saveButton.textContent = "Salvando…";
    try {
      const data = await this.bridge.api("espacial_salvar", values, "POST");
      this.saved = data.configuracao;
      this.hooks.setNorth(this.saved.norte_graus);
      this.hooks.setConfig?.(this.saved);
      this.dialog.close();
      this.draft = null;
      this.bridge.hooks.status("Configurações espaciais salvas.");
    } catch (error) {
      this.message(error.message);
    } finally {
      this.saving = false;
      this.form.querySelectorAll("button, input").forEach((element) => {
        element.disabled = false;
      });
      this.saveButton.textContent = "Salvar";
      this.sync();
      if (!this.dialog.open) this.button.focus();
    }
  }
}
