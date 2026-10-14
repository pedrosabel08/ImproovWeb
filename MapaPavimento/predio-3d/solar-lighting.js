import * as THREE from "three";
import { solarAt, solarNow, solarConfigError } from "./solar-position.mjs";
import { fitSolarShadow, boxCorners } from "./solar-shadow.js";
import { SolarProjection } from "./solar-projection.js";

/** Uma única DirectionalLight: navegação quando desativado, Sol astronômico quando ativado. */
export class SolarLighting {
  constructor({ scene, light, ambient, viewport, onRestore, onConfigure, getModel = () => null }) {
    Object.assign(this, {
      scene,
      light,
      ambient,
      viewport,
      onRestore,
      onConfigure,
      getModel,
    });
    this.enabled = false;
    this.panel = document.createElement("section");
    this.panel.id = "solarPanel";
    this.panel.className = "solar-panel";
    this.panel.setAttribute("aria-labelledby", "solarTitle");
    this.panel.innerHTML =
      '<header><h2 id="solarTitle">☀ Sol</h2><label class="solar-toggle"><input id="solarEnabled" type="checkbox" disabled> Ativar Sol</label></header><div id="solarControls" hidden><div class="solar-fields"><label>Data<input id="solarDate" type="date" required></label><label>Horário<input id="solarTime" type="time" step="60" required></label></div><label class="solar-slider-label" for="solarRange">Horário do empreendimento <output id="solarClock" for="solarRange"></output></label><input id="solarRange" type="range" min="0" max="1435" step="5" value="720" aria-label="Horário do Sol" aria-describedby="solarZone"><div class="solar-ticks" aria-hidden="true"><span>00:00</span><span>06:00</span><span>12:00</span><span>18:00</span><span>23:55</span></div><button type="button" class="btn" id="solarNow">Agora</button><p id="solarLocalDate" class="solar-date"></p><dl class="solar-readings"><div><dt>Azimute</dt><dd id="solarAzimuth">—</dd></div><div><dt>Elevação</dt><dd id="solarAltitude">—</dd></div></dl><p id="solarZone" class="solar-meta"></p><p id="solarNorth" class="solar-meta"></p><div class="solar-projection-options" role="group" aria-label="Projeção solar no 3D"><label><input id="solarProjectionEnabled" type="checkbox" aria-describedby="solarProjectionHelp"> Mostrar projeção solar</label><label><input id="solarPathEnabled" type="checkbox" aria-describedby="solarProjectionHelp"> Mostrar trajetória do dia</label><p id="solarProjectionHelp" class="solar-meta" hidden>Setas: sentido da luz. Pontos: primeiro bloqueio opaco. Curva: trajetória do dia.</p><p id="solarProjectionStatus" class="solar-meta" role="status" aria-live="polite" hidden></p></div></div><p id="solarStatus" class="solar-status" role="status" aria-live="polite">Carregando configurações espaciais…</p><button type="button" class="btn" id="solarConfigure" hidden>Configurar localização e Norte</button>';
    document.querySelector(".right .head").after(this.panel);
    const select = (id) => this.panel.querySelector("#" + id);
    this.toggle = select("solarEnabled");
    this.body = select("solarControls");
    this.date = select("solarDate");
    this.time = select("solarTime");
    this.range = select("solarRange");
    this.clock = select("solarClock");
    this.status = select("solarStatus");
    this.configure = select("solarConfigure");
    this.projectionToggle = select("solarProjectionEnabled");
    this.pathToggle = select("solarPathEnabled");
    this.projectionHelp = select("solarProjectionHelp");
    this.projectionStatus = select("solarProjectionStatus");
    this.projection = new SolarProjection(scene, viewport, state => {
      this.rayStatus = state;
      this.updateProjectionStatus();
    });
    this.projectionToggle.addEventListener("change", () => this.refreshProjection());
    this.pathToggle.addEventListener("change", () => this.refreshProjection());
    this.toggle.addEventListener("change", () => {
      this.enabled = this.toggle.checked;
      this.body.hidden = !this.enabled;
      document
        .querySelector(".app")
        .classList.toggle("solar-enabled", this.enabled);
      this.relight();
    });
    this.date.addEventListener("input", () => this.relight());
    this.time.addEventListener("input", () => this.relight());
    this.range.addEventListener("input", () => {
      const minutes = Number(this.range.value);
      this.time.value =
        String(Math.floor(minutes / 60)).padStart(2, "0") +
        ":" +
        String(minutes % 60).padStart(2, "0");
      this.relight();
    });
    select("solarNow").addEventListener("click", () => {
      this.now();
      this.relight();
    });
    this.configure.addEventListener("click", () => this.onConfigure());
    this.marker = new THREE.Mesh(
      new THREE.SphereGeometry(1, 16, 12),
      new THREE.MeshBasicMaterial({ color: 0xffdb8b, toneMapped: false }),
    );
    this.marker.name = "Indicador visual do Sol";
    this.marker.visible = false;
    this.marker.castShadow = this.marker.receiveShadow = false;
    this.marker.raycast = () => {};
    scene.add(this.marker);
    this.markerLabel = document.createElement("span");
    this.markerLabel.className = "solar-marker-label";
    this.markerLabel.textContent = "☀";
    this.markerLabel.setAttribute("role", "img");
    this.markerLabel.hidden = true;
    viewport.append(this.markerLabel);
    // Receptor local neutro para visualizar sombras mesmo em GLBs sem terreno.
    // Sua extensão acompanha o edifício; não é chão infinito nem área de estudo solar.
    this.receiver = new THREE.Mesh(
      new THREE.PlaneGeometry(1, 1),
      new THREE.MeshStandardMaterial({
        color: 0x4a5055,
        roughness: 1,
        metalness: 0,
      }),
    );
    this.receiver.rotation.x = -Math.PI / 2;
    this.receiver.receiveShadow = true;
    this.receiver.castShadow = false;
    this.receiver.raycast = () => {};
    this.receiver.visible = false;
    scene.add(this.receiver);
    this.light.shadow.autoUpdate = false;
    this.viewPoint = new THREE.Vector3();
  }
  setBounds(bounds) {
    this.bounds = bounds && !bounds.isEmpty() ? bounds.clone() : null;
    this.projection.setModel(this.bounds ? this.getModel() : null, this.bounds);
    this.projectionToggle.checked = this.pathToggle.checked = false;
    if (this.bounds) {
      this.center = this.bounds.getCenter(new THREE.Vector3());
      const size = this.bounds.getSize(new THREE.Vector3());
      this.radius = Math.max(size.length() / 2, 0.5);
      const half = Math.max(size.x, size.z, size.y, 1) * 1.25;
      const y = this.bounds.min.y - this.radius * 0.0005;
      this.receiver.position.set(this.center.x, y, this.center.z);
      this.receiver.scale.set(half * 2, half * 2, 1);
      this.receiverBounds = new THREE.Box3(
        new THREE.Vector3(this.center.x - half, y, this.center.z - half),
        new THREE.Vector3(this.center.x + half, y, this.center.z + half),
      );
      this.marker.scale.setScalar(this.radius * 0.018);
    } else {
      this.enabled = false;
      this.toggle.checked = false;
      this.body.hidden = true;
      document.querySelector(".app").classList.remove("solar-enabled");
    }
    this.relight();
  }
  fitReceiver(direction) {
    const y = this.bounds.min.y - this.radius * 0.0005;
    const footprint = new THREE.Box3(
      new THREE.Vector3(this.bounds.min.x, y, this.bounds.min.z),
      new THREE.Vector3(this.bounds.max.x, y, this.bounds.max.z),
    );
    if (direction.y > 0) {
      // Projeta os oito cantos até o chão na direção oposta à luz. Assim 08h/16h
      // não cortam sombras longas na borda de um receptor dimensionado só pela planta.
      const reach = this.radius * 10;
      for (const corner of boxCorners(this.bounds)) {
        const travel = (corner.y - y) / direction.y;
        const x = THREE.MathUtils.clamp(
          corner.x - direction.x * travel,
          this.center.x - reach,
          this.center.x + reach,
        );
        const z = THREE.MathUtils.clamp(
          corner.z - direction.z * travel,
          this.center.z - reach,
          this.center.z + reach,
        );
        footprint.expandByPoint(new THREE.Vector3(x, y, z));
      }
    }
    const padding = this.radius * 0.15;
    footprint.min.x -= padding;
    footprint.max.x += padding;
    footprint.min.z -= padding;
    footprint.max.z += padding;
    const center = footprint.getCenter(new THREE.Vector3()),
      size = footprint.getSize(new THREE.Vector3());
    this.receiver.position.copy(center);
    this.receiver.scale.set(size.x, size.z, 1);
    this.receiverBounds = footprint;
  }
  setConfig(config, loadError = "") {
    const changed =
      config &&
      (!this.config ||
        config.obra_id !== this.config.obra_id ||
        config.timezone !== this.config.timezone);
    this.config = config ? { ...config } : null;
    this.loadError = loadError;
    if (!config || solarConfigError(config)) {
      this.enabled = false;
      this.toggle.checked = false;
      this.body.hidden = true;
      document.querySelector(".app").classList.remove("solar-enabled");
    }
    if (changed && !solarConfigError(config)) this.now();
    this.relight();
  }
  setSuspended(value) {
    this.suspended = value;
    this.relight();
  }
  now() {
    if (!this.config || solarConfigError(this.config)) return;
    const now = solarNow(this.config.timezone);
    this.date.value = now.date;
    this.time.value = now.time;
  }
  navigation() {
    this.result = null;
    this.projection.hide();
    this.projectionToggle.disabled = this.pathToggle.disabled = true;
    this.projectionHelp.hidden = this.projectionStatus.hidden = true;
    this.marker.visible = this.receiver.visible = false;
    this.markerLabel.hidden = true;
    this.light.intensity = 2.25;
    this.ambient.intensity = 1.65;
    if (this.bounds) this.onRestore(this.bounds);
  }
  relight() {
    const error = this.loadError || solarConfigError(this.config);
    this.toggle.disabled = !!error || !this.bounds || this.suspended;
    this.configure.hidden = !error || (!this.config && !this.loadError);
    if (error || !this.bounds || !this.enabled || this.suspended) {
      this.navigation();
      this.status.textContent = !this.bounds
        ? "Sol disponível após carregar o empreendimento."
        : error ||
          (this.suspended
            ? "Sol pausado durante a definição do Norte."
            : "Ative para simular data e horário.");
      this.panel.classList.remove("below-horizon");
      return;
    }
    let result;
    try {
      result = solarAt(this.config, this.date.value, this.time.value);
    } catch (error) {
      // Não mantém um Sol anterior como se correspondesse à entrada inválida.
      this.navigation();
      this.light.intensity = 0;
      this.ambient.intensity = 0.45;
      this.panel.querySelector("#solarAzimuth").textContent = "—";
      this.panel.querySelector("#solarAltitude").textContent = "—";
      this.result = null;
      this.panel.querySelector("#solarLocalDate").textContent =
        "Data ou horário inválidos";
      this.clock.value = this.time.value || "—";
      this.status.textContent = error.message;
      return;
    }
    this.result = result;
    const direction = new THREE.Vector3(
      result.direction.x,
      result.direction.y,
      result.direction.z,
    );
    this.light.position
      .copy(this.center)
      .addScaledVector(direction, this.radius * 8);
    this.light.target.position.copy(this.center);
    this.light.intensity = result.intensity;
    this.ambient.intensity = 0.45;
    this.fitReceiver(direction);
    this.receiver.visible = true;
    this.marker.visible = result.altitude > 0;
    this.marker.position
      .copy(this.center)
      .addScaledVector(direction, this.radius * 1.12);
    fitSolarShadow(this.light, this.bounds, this.receiverBounds);
    const minutes =
      Number(this.time.value.slice(0, 2)) * 60 +
      Number(this.time.value.slice(3));
    this.range.value = String(Math.min(1435, Math.floor(minutes / 5) * 5));
    this.range.setAttribute("aria-valuetext", this.time.value);
    this.clock.value = this.time.value;
    this.panel.querySelector("#solarLocalDate").textContent = result.label;
    this.panel.querySelector("#solarAzimuth").textContent =
      result.azimuth.toFixed(1) + "°";
    this.panel.querySelector("#solarAltitude").textContent =
      result.altitude.toFixed(1) + "°";
    this.panel.querySelector("#solarZone").textContent =
      this.config.timezone + " (UTC" + result.offset + ")";
    this.panel.querySelector("#solarNorth").textContent =
      "Norte salvo: " + this.config.norte_graus.toFixed(1) + "°";
    this.panel.classList.toggle("below-horizon", result.altitude <= 0);
    this.status.textContent =
      result.altitude <= 0
        ? "Sol abaixo do horizonte"
        : result.ambiguous
          ? "Horário repetido no fuso: primeira ocorrência (UTC" +
            result.offset +
            ")."
          : "Sol acima do horizonte";
    this.refreshProjection();
    this.markerLabel.title =
      "Posição do Sol · azimute " +
      result.azimuth.toFixed(1) +
      "° · elevação " +
      result.altitude.toFixed(1) +
      "°";
  }
  setMapVisible(value) {
    this.mapVisible = value;
    this.refreshProjection();
  }
  setProjectionSuspended(value) {
    this.projectionSuspended = value;
    this.refreshProjection();
  }
  refreshProjection() {
    const active = this.enabled && this.bounds && this.result && !this.suspended && !this.mapVisible && !this.projectionSuspended;
    this.projectionToggle.disabled = !active;
    this.pathToggle.disabled = !active;
    if (!active) {
      this.projection.hide();
      this.projectionHelp.hidden = this.projectionStatus.hidden = true;
      return;
    }
    if (this.projectionToggle.checked && this.result.altitude > 0) {
      void this.projection.requestRays(this.result.direction, this.receiverBounds);
    } else this.projection.hideRays();
    if (this.pathToggle.checked) this.projection.showPath(this.config, this.date.value);
    else this.projection.hidePath();
    this.updateProjectionStatus();
  }
  updateProjectionStatus() {
    const active = this.enabled && !!this.result && !this.suspended && !this.mapVisible && !this.projectionSuspended;
    const selected = this.projectionToggle.checked || this.pathToggle.checked;
    this.projectionHelp.hidden = this.projectionStatus.hidden = !active || !selected;
    if (!active || !selected) return;
    if (this.projectionToggle.checked) {
      const state = this.rayStatus;
      this.projectionStatus.textContent = this.result.altitude <= 0 ? "Projeção indisponível: Sol abaixo do horizonte." : state?.error || (state?.pending ? "Calculando projeção…" : state ? state.rays + " raios exibidos · " + state.buildings + " impactos no modelo." : "Calculando projeção…");
    } else this.projectionStatus.textContent = this.projection.path.visible ? "Trajetória do dia · horários locais da obra." : "Sem trajetória acima do horizonte nesta data.";
  }
  update(camera) {
    this.projection.update(camera);
    if (!this.marker.visible) {
      this.markerLabel.hidden = true;
      return;
    }
    this.viewPoint.copy(this.marker.position).project(camera);
    this.markerLabel.hidden = this.viewPoint.z < -1 || this.viewPoint.z > 1;
    const x = ((this.viewPoint.x + 1) / 2) * this.viewport.clientWidth;
    const y = ((1 - this.viewPoint.y) / 2) * this.viewport.clientHeight;
    const outside =
      x < 18 ||
      x > this.viewport.clientWidth - 18 ||
      y < 18 ||
      y > this.viewport.clientHeight - 18;
    this.markerLabel.classList.toggle("offscreen", outside);
    this.markerLabel.style.left =
      Math.max(18, Math.min(this.viewport.clientWidth - 18, x)) + "px";
    this.markerLabel.style.top =
      Math.max(18, Math.min(this.viewport.clientHeight - 18, y)) + "px";
    this.markerLabel.setAttribute(
      "aria-label",
      outside ? "Sol fora do enquadramento, nesta direção" : "Posição do Sol",
    );
  }
}
