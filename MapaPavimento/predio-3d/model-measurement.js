import * as THREE from "three";
import {
  measurementTypes,
  measurementValue,
  measurementSegment,
  formatMeasurement,
  projectMeasurementPoint,
} from "./measurement-geometry.js";

const node = (tag, text, className) => {
  const result = document.createElement(tag);
  if (text !== undefined) result.textContent = text;
  if (className) result.className = className;
  return result;
};

/** Trena em world space. Raycasting e ciclo de render pertencem ao visualizador existente. */
export class ModelMeasurement {
  constructor({ scene, camera, viewport, pick, onModeChange }) {
    Object.assign(this, { scene, camera, viewport, pick, onModeChange });
    this.records = [];
    this.scope = null;
    this.active = this.available = this.suspended = false;
    this.sequence = 0;
    this.projected = new THREE.Vector3();
    this.group = new THREE.Group();
    this.group.name = "Medições temporárias";
    scene.add(this.group);
    this.overlay = node("div", undefined, "measurement-overlay");
    viewport.append(this.overlay);
    const inspector = (this.inspector = document.querySelector(".right"));
    this.navigation = node("nav", undefined, "inspection-tools");
    this.navigation.setAttribute("aria-label", "Ferramentas de inspeção");
    this.inspectButton = this.button("Inspecionar", () => this.exit());
    this.measureButton = this.button("Medir", () => this.begin());
    this.solarButton = this.button("Sol", () => {
      this.exit();
      this.focusPanel(document.getElementById("solarPanel"));
    });
    this.navigation.append(
      this.inspectButton,
      this.measureButton,
      this.solarButton,
    );
    inspector.querySelector(".head").after(this.navigation);
    this.panel = node("section", undefined, "measurement-panel");
    this.panel.hidden = true;
    this.panel.id = "measurementPanel";
    this.panel.setAttribute("aria-labelledby", "measurementTitle");
    this.panel.innerHTML = `<header><h2 id="measurementTitle">Medição</h2><span>Metros</span></header>
      <label for="measurementType">Tipo de medição</label><select id="measurementType"><option value="distance">Distância</option><option value="horizontal">Horizontal</option><option value="vertical">Vertical</option></select>
      <p class="measurement-help" id="measurementTypeHelp">Distância real entre dois pontos do modelo.</p>
      <div class="measurement-points"><span id="measurementPointA">Ponto A: não definido</span><span id="measurementPointB">Ponto B: não definido</span></div>
      <p class="measurement-instruction" id="measurementInstruction" role="status"></p>
      <div class="measurement-result"><span>Resultado</span><output id="measurementResult">—</output></div>
      <div class="measurement-actions"></div><div class="measurement-history"><h3>Medições na cena <span id="measurementCount">0</span></h3><div id="measurementList"></div></div>
      <p class="measurement-help">1 unidade = 1 metro. Medições temporárias, sem gravação no projeto.</p>`;
    this.navigation.after(this.panel);
    const find = (selector) => this.panel.querySelector(selector);
    this.type = find("select");
    this.type.setAttribute("aria-describedby", "measurementTypeHelp");
    this.result = find("output");
    this.instruction = find("#measurementInstruction");
    this.pointAStatus = find("#measurementPointA");
    this.pointBStatus = find("#measurementPointB");
    this.list = find("#measurementList");
    this.newButton = this.button("Nova medição", () => this.begin());
    this.clearButton = this.button("Limpar", () => this.removeCurrent());
    this.exitButton = this.button("Sair do modo", () => this.exit());
    this.clearAllButton = this.button("Limpar todas", () => this.clearAll());
    find(".measurement-actions").append(
      this.newButton,
      this.clearButton,
      this.exitButton,
      this.clearAllButton,
    );
    this.type.addEventListener("change", () => {
      if (this.selected) {
        this.selected.tipo = this.type.value;
        this.updateRecord(this.selected, this.selected.pointB);
        this.renderList();
      }
      if (this.draft?.pointB) this.updateRecord(this.draft, this.draft.pointB);
      this.sync();
    });
    document.addEventListener("keydown", (event) => {
      if (
        event.target.closest(
          'input,select,textarea,[contenteditable="true"],dialog',
        ) ||
        event.ctrlKey ||
        event.metaKey ||
        event.altKey
      )
        return;
      if (event.key === "Escape" && this.active) {
        event.preventDefault();
        this.cancelDraft();
        this.selected = null;
        this.sync();
      }
      if (event.key === "Delete" && this.selected && !this.suspended) {
        event.preventDefault();
        this.removeCurrent();
      }
    });
    this.sync();
  }

  button(text, callback) {
    const result = node("button", text, "btn");
    result.type = "button";
    result.addEventListener("click", callback);
    return result;
  }

  setAvailable(value) {
    if (value === this.available) return;
    this.available = value;
    this.sync();
  }

  setScope(scope) {
    if (scope === this.scope) return;
    this.scope = scope;
    this.cancelDraft();
    this.selected = null;
    this.records.forEach((record) => this.setRecordVisible(record));
    this.renderList();
    this.sync();
  }

  setRecordVisible(record) {
    const visible = record.scope === this.scope;
    record.line.visible = visible && record.pointB !== null;
    record.guide.visible = visible && record.pointB !== null && record.hasGuide;
    record.markerA.hidden = !visible || record.pointB === null;
    record.markerB.hidden = !visible || record.pointB === null;
    record.valueLabel.hidden = !visible || record.pointB === null;
  }

  setSuspended(value) {
    this.suspended = value;
    if (value) this.exit();
    this.group.visible = !value;
    this.overlay.hidden = value;
    this.navigation.inert = value;
    this.panel.inert = value;
  }

  begin() {
    if (!this.available || this.suspended) return;
    this.cancelDraft();
    this.selected = null;
    if (!this.active) {
      this.active = true;
      this.onModeChange(true);
    }
    this.sync();
    this.focusPanel();
  }

  focusPanel(panel = this.panel) {
    if (!panel) return;
    // Rola apenas o Hub; não desloca o canvas ou o documento em telas menores.
    const top =
      panel.getBoundingClientRect().top -
      this.inspector.getBoundingClientRect().top +
      this.inspector.scrollTop;
    this.inspector.scrollTop = Math.max(
      0,
      top -
        this.navigation.offsetHeight -
        this.inspector.querySelector(".head").offsetHeight,
    );
  }

  exit() {
    this.cancelDraft();
    this.active = false;
    this.onModeChange(false);
    this.sync();
  }

  cancelDraft() {
    if (this.draft) this.disposeRecord(this.draft);
    this.draft = null;
    this.pendingPointer = null;
  }

  move(event) {
    if (!this.active || !this.draft) return;
    this.pendingPointer = event
      ? { clientX: event.clientX, clientY: event.clientY }
      : null;
    if (!event) this.hidePreview();
  }

  hidePreview() {
    if (!this.draft) return;
    this.draft.line.visible = this.draft.guide.visible = false;
    this.draft.pointB = null;
    this.draft.valor = null;
    this.draft.valueLabel.hidden = this.draft.markerB.hidden = true;
    this.result.textContent = "—";
    this.pendingPointer = null;
  }

  choose(point) {
    if (!this.active || !this.available || this.selected) return;
    if (!point) {
      this.instruction.textContent =
        "Clique em uma superfície do modelo para definir o ponto.";
      return;
    }
    if (!this.draft) {
      this.draft = this.createRecord(point);
      this.sync();
      return;
    }
    const record = this.draft;
    record.id = `medicao-${++this.sequence}`;
    record.scope = this.scope;
    record.tipo = this.type.value;
    this.updateRecord(record, point);
    this.records.push(record);
    this.draft = null;
    this.pendingPointer = null;
    this.selected = record;
    this.renderList();
    this.sync();
  }

  createRecord(pointA) {
    const geometry = () =>
      new THREE.BufferGeometry().setAttribute(
        "position",
        new THREE.Float32BufferAttribute(new Float32Array(6), 3),
      );
    const line = new THREE.Line(
      geometry(),
      new THREE.LineBasicMaterial({
        color: 0xb2dfd5,
        transparent: true,
        opacity: 0.85,
        depthTest: false,
        depthWrite: false,
        toneMapped: false,
      }),
    );
    const guide = new THREE.Line(
      geometry(),
      new THREE.LineDashedMaterial({
        color: 0xa8bbc2,
        transparent: true,
        opacity: 0.4,
        dashSize: 0.35,
        gapSize: 0.25,
        depthTest: false,
        depthWrite: false,
        toneMapped: false,
      }),
    );
    line.renderOrder = guide.renderOrder = 920;
    line.frustumCulled = guide.frustumCulled = false;
    line.raycast = guide.raycast = () => {};
    line.visible = guide.visible = false;
    this.group.add(line, guide);
    const markerA = node("span", "A", "measurement-marker");
    const markerB = node("span", "B", "measurement-marker");
    markerA.setAttribute("aria-hidden", "true");
    markerB.setAttribute("aria-hidden", "true");
    const valueLabel = this.button("", () => {
      if (record.id) this.select(record);
    });
    valueLabel.className = "measurement-label";
    markerA.hidden = markerB.hidden = valueLabel.hidden = true;
    this.overlay.append(markerA, markerB, valueLabel);
    const record = {
      id: null,
      scope: this.scope,
      tipo: this.type.value,
      pointA: pointA.clone(),
      pointB: null,
      valor: null,
      line,
      guide,
      markerA,
      markerB,
      valueLabel,
      midpoint: pointA.clone(),
    };
    return record;
  }

  updateRecord(record, pointB) {
    record.pointB = pointB.clone();
    if (!record.id) record.tipo = this.type.value;
    record.valor = measurementValue(record.tipo, record.pointA, record.pointB);
    const { end, midpoint } = measurementSegment(
      record.tipo,
      record.pointA,
      record.pointB,
    );
    record.midpoint.copy(midpoint);
    const updateLine = (line, a, b) => {
      const positions = line.geometry.getAttribute("position");
      positions.setXYZ(0, a.x, a.y, a.z);
      positions.setXYZ(1, b.x, b.y, b.z);
      positions.needsUpdate = true;
    };
    updateLine(record.line, record.pointA, end);
    updateLine(record.guide, end, record.pointB);
    record.guide.computeLineDistances();
    record.hasGuide = end.distanceToSquared(record.pointB) > 1e-12;
    const visible = record.scope === this.scope;
    record.line.visible = visible;
    record.guide.visible = visible && record.hasGuide;
    record.markerA.hidden = record.markerB.hidden = record.valueLabel.hidden =
      !visible;
    record.valueLabel.textContent = formatMeasurement(record.valor);
    record.valueLabel.setAttribute(
      "aria-label",
      `${measurementTypes[record.tipo]}: ${formatMeasurement(record.valor)}`,
    );
    record.valueLabel.title = `${measurementTypes[record.tipo]} · ${record.id ? "Selecionar medição" : "Prévia"}`;
    if (record === this.draft || record === this.selected)
      this.result.textContent = formatMeasurement(record.valor);
  }

  select(record) {
    this.cancelDraft();
    this.selected = record;
    this.type.value = record.tipo;
    this.panel.hidden = false;
    this.renderList();
    this.sync();
    this.focusPanel();
  }

  removeCurrent() {
    if (this.draft) this.cancelDraft();
    else if (this.selected) {
      this.records = this.records.filter((record) => record !== this.selected);
      this.disposeRecord(this.selected);
      this.selected = null;
    }
    this.renderList();
    this.sync();
  }

  clearAll() {
    this.cancelDraft();
    const removed = this.records.filter((record) => record.scope === this.scope);
    removed.forEach((record) => this.disposeRecord(record));
    this.records = this.records.filter((record) => record.scope !== this.scope);
    this.selected = null;
    this.renderList();
    this.sync();
  }

  reset() {
    this.exit();
    this.cancelDraft();
    this.records.forEach((record) => this.disposeRecord(record));
    this.records = [];
    this.selected = null;
    this.renderList();
    this.sync();
    this.setAvailable(false);
  }

  disposeRecord(record) {
    for (const line of [record.line, record.guide]) {
      this.group.remove(line);
      line.geometry.dispose();
      line.material.dispose();
    }
    record.markerA.remove();
    record.markerB.remove();
    record.valueLabel.remove();
  }

  renderList() {
    this.list.replaceChildren();
    const records = this.records.filter((record) => record.scope === this.scope);
    records.forEach((record, index) => {
      const button = this.button("", () => this.select(record));
      button.className = "measurement-item";
      button.dataset.measurementId = record.id;
      button.dataset.pointA = JSON.stringify(record.pointA.toArray());
      button.dataset.pointB = JSON.stringify(record.pointB.toArray());
      button.dataset.value = String(record.valor);
      button.setAttribute("aria-pressed", String(record === this.selected));
      button.append(
        node("span", `${index + 1}. ${measurementTypes[record.tipo]}`),
        node("strong", formatMeasurement(record.valor)),
      );
      this.list.append(button);
    });
    this.panel.querySelector("#measurementCount").textContent = String(
      records.length,
    );
    this.panel.querySelector(".measurement-history").hidden =
      !records.length;
  }

  sync() {
    const current = this.draft || this.selected;
    const scopedRecords = this.records.filter(
      (record) => record.scope === this.scope,
    );
    this.overlay.classList.toggle("is-picking", this.active && !this.selected);
    this.measureButton.disabled = !this.available;
    this.inspectButton.setAttribute("aria-pressed", String(!this.active));
    this.measureButton.setAttribute("aria-pressed", String(this.active));
    this.panel.hidden = !this.active && !scopedRecords.length;
    this.type.disabled = !this.available;
    this.newButton.disabled = !this.available;
    this.clearButton.disabled = !current;
    this.exitButton.hidden = !this.active;
    this.clearAllButton.disabled = !scopedRecords.length && !this.draft;
    this.pointAStatus.textContent = `Ponto A: ${current ? "definido" : "não definido"}`;
    this.pointBStatus.textContent = `Ponto B: ${this.selected ? "definido" : "não definido"}`;
    this.instruction.textContent = !this.active
      ? "Selecione uma medição para consultar o resultado."
      : this.selected
        ? "Medição concluída. Use Nova medição para escolher outros pontos."
        : this.draft
          ? "Clique no segundo ponto. Esc cancela a medição atual."
          : "Clique no primeiro ponto do modelo.";
    this.result.textContent =
      current?.valor != null ? formatMeasurement(current.valor) : "—";
    this.panel.querySelector("#measurementTypeHelp").textContent = {
      distance: "Distância real entre dois pontos do modelo.",
      horizontal:
        "Distância horizontal, desconsiderando a diferença de altura.",
      vertical: "Diferença de altura entre os dois pontos.",
    }[this.type.value];
    scopedRecords.forEach((record, index) => {
      record.line.material.opacity = record === this.selected ? 1 : 0.65;
      record.valueLabel.classList.toggle(
        "is-selected",
        record === this.selected,
      );
      this.list.children[index]?.setAttribute(
        "aria-pressed",
        String(record === this.selected),
      );
    });
  }

  update() {
    if (this.suspended) return;
    if (this.pendingPointer && this.active && this.draft) {
      const hit = this.pick(this.pendingPointer);
      this.pendingPointer = null;
      if (hit) this.updateRecord(this.draft, hit.point);
      else this.hidePreview();
    }
    const width = this.viewport.clientWidth,
      height = this.viewport.clientHeight;
    const place = (element, point) => {
      const projected = projectMeasurementPoint(
        point,
        this.camera,
        width,
        height,
        this.projected,
      );
      element.hidden = !projected.visible;
      element.style.left = `${projected.x}px`;
      element.style.top = `${projected.y}px`;
    };
    for (const record of this.draft
      ? [...this.records, this.draft]
      : this.records) {
      if (record.scope !== this.scope) continue;
      place(record.markerA, record.pointA);
      if (record.pointB) {
        place(record.markerB, record.pointB);
        place(record.valueLabel, record.midpoint);
      }
    }
  }
}
