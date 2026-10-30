import * as THREE from "three";
import {
  solarAt,
  solarNow,
  solarConfigError,
} from "./solar-position.mjs?v=20261030-environment";
import { fitSolarShadow } from "./solar-shadow.js";
import {
  createShadowCatcher,
  fitShadowCatcher,
  hasVisibleGroundReceiver,
} from "./shadow-catcher.js?v=20261030-spherical";
import { SolarProjection } from "./solar-projection.js?v=20261030-mobile-labels";
import { solarGlowTexture, SOLAR_ORBIT_SCALE } from "./solar-visuals.js";
import {
  ENVIRONMENTS,
  ENVIRONMENT_SETTINGS,
  atmosphereInterval,
} from "./environment-config.mjs?v=20261030-night-neutral-v1";

/** Uma única DirectionalLight: navegação quando desativado, Sol astronômico quando ativado. */
export class SolarLighting {
  constructor({
    scene,
    light,
    ambient,
    environment,
    viewport,
    onRestore,
    onConfigure,
    onCompassChange = () => {},
    getModel = () => null,
  }) {
    Object.assign(this, {
      scene,
      light,
      ambient,
      environment,
      viewport,
      onRestore,
      onConfigure,
      onCompassChange,
      getModel,
    });
    this.enabled = false;
    this.preferredEnabled = true;
    this.navigationColor = light.color.clone();
    this.solarColor = new THREE.Color();
    this.panel = document.createElement("section");
    this.panel.id = "solarPanel";
    this.panel.className = "solar-panel";
    this.panel.setAttribute("aria-labelledby", "solarTitle");
    this.panel.innerHTML = `
      <header><h2 id="solarTitle"><span class="solar-title-icon" aria-hidden="true">☀</span> Sol</h2>
        <label class="solar-toggle"><input id="solarEnabled" type="checkbox" disabled> Ativar Sol</label></header>
      <div id="solarControls" hidden>
        <div class="solar-fields">
          <label>Data<input id="solarDate" type="date" required></label>
          <label>Horário<input id="solarTime" type="time" step="60" required></label>
        </div>
        <label class="solar-slider-label" for="solarRange">Horário do empreendimento <output id="solarClock" for="solarRange"></output></label>
        <input id="solarRange" type="range" min="0" max="1435" step="5" value="720" aria-label="Horário do Sol" aria-describedby="solarZone">
        <div class="solar-ticks" aria-hidden="true"><span>00:00</span><span>06:00</span><span>12:00</span><span>18:00</span><span>23:55</span></div>
        <div class="solar-moment"><p id="solarLocalDate" class="solar-date"></p><button type="button" class="btn" id="solarNow">Agora</button></div>
        <dl class="solar-readings"><div><dt>Azimute</dt><dd id="solarAzimuth">—</dd></div><div><dt>Elevação</dt><dd id="solarAltitude">—</dd></div></dl>
        <p id="solarZone" class="solar-meta"></p><p id="solarNorth" class="solar-meta"></p>
        <details class="solar-technical solar-environment-debug">
          <summary>Ambiente · diagnóstico</summary>
          <label class="solar-environment-control" for="solarEnvironment">HDRI para comparação
            <select id="solarEnvironment"><option value="auto">Automático</option>${Object.entries(
              ENVIRONMENTS,
            )
              .map(
                ([key, preset]) =>
                  `<option value="${key}">${preset.label}</option>`,
              )
              .join("")}</select>
          </label>
          <label class="solar-horizon-debug"><input id="solarHorizonEnabled" type="checkbox"> Mostrar horizonte geométrico</label>
          <dl class="solar-environment-readings">
            <div><dt>HDRI atual</dt><dd id="solarEnvironmentCurrent">—</dd></div>
            <div><dt>Horizon offset</dt><dd id="solarHorizonOffset">0°</dd></div>
            <div><dt>HDRI rotation</dt><dd id="solarHdriRotation">0°</dd></div>
            <div><dt>Horizon visible</dt><dd id="solarHorizonVisible">—</dd></div>
            <div><dt>Elevação da câmera</dt><dd id="solarCameraPitch">—</dd></div>
            <div><dt>Intensidade do Sol</dt><dd id="solarSunIntensity">—</dd></div>
            <div><dt>Override</dt><dd id="solarEnvironmentOverride">Automático</dd></div>
            <div><dt>GI global por HDRI</dt><dd id="solarGlobalEnvironment">Desativada</dd></div>
            <div><dt>Reflexos (escala)</dt><dd id="solarEnvironmentIntensity">—</dd></div>
            <div><dt>Período solar</dt><dd id="solarPeriod">—</dd></div>
            <div><dt>HDRIs processados</dt><dd id="solarEnvironmentCached">0</dd></div>
            <div><dt>LightProbe</dt><dd id="solarProbeState">Inativa</dd></div>
            <div><dt>Probe · intensidade</dt><dd id="solarProbeIntensity">—</dd></div>
            <div><dt>Probe · última atualização</dt><dd id="solarProbeUpdated">—</dd></div>
            <div><dt>Probe · elevação usada</dt><dd id="solarProbeAltitude">—</dd></div>
            <div><dt>Probe · azimute usado</dt><dd id="solarProbeAzimuth">—</dd></div>
            <div><dt>Capturas da Probe</dt><dd id="solarProbeCount">0</dd></div>
            <div><dt>Hemisphere · céu</dt><dd id="solarHemisphereSky">—</dd></div>
            <div><dt>Hemisphere · solo</dt><dd id="solarHemisphereGround">—</dd></div>
            <div><dt>Hemisphere · intensidade</dt><dd id="solarHemisphereIntensity">—</dd></div>
            <div><dt>Céu visual</dt><dd id="solarSkyState">—</dd></div>
            <div><dt>Materiais com reflexos</dt><dd id="solarReflectionMaterials">0</dd></div>
          </dl>
          <fieldset class="solar-environment-tuning"><legend>Ajustes temporários</legend>
            ${[
              ["probe", "LightProbe intensity"],
              ["hemisphere", "Hemisphere intensity"],
              ["glass", "Glass reflection intensity"],
            ]
              .map(([key, label]) => {
                const range = ENVIRONMENT_SETTINGS.tuning[key];
                return `<label for="solarTune-${key}">${label}<output id="solarTuneValue-${key}"></output></label><input id="solarTune-${key}" type="range" min="${range.min}" max="${range.max}" step="${range.step}" aria-label="${label}">`;
              })
              .join("")}
            <p class="solar-meta">Valores base; as contribuições variam com a elevação solar. Ajustes somente nesta sessão.</p>
            <button type="button" class="btn" id="solarResetEnvironmentTuning">Restaurar ajustes</button>
          </fieldset>
          <p id="solarEnvironmentStatus" class="solar-meta" role="status"></p>
          <p id="solarProbeError" class="solar-meta" role="status"></p>
          <p class="solar-meta">Comparação manual altera somente céu HDRI e reflexos. Sol, atmosfera, LightProbe e Hemisphere permanecem iguais. A linha marca o equador mundial Y = 0 da esfera ambiental e pode sair da viewport.</p>
          <p id="solarDomeWarning" class="solar-meta" hidden>Câmera fora da esfera ambiental: usando direção mundial no céu. Horizonte esférico indisponível nesta posição.</p>
        </details>
      </div>
      <p id="solarStatus" class="solar-status" role="status" aria-live="polite">Carregando configurações espaciais…</p>
      <fieldset id="solarVisualOptions" class="solar-projection-options" hidden>
        <legend>Opções visuais</legend>
        <label><input id="solarProjectionEnabled" type="checkbox" aria-describedby="solarProjectionHelp"> Mostrar projeção solar</label>
        <label><input id="solarPathEnabled" type="checkbox" aria-describedby="solarProjectionHelp"> Mostrar trajetória do dia</label>
        <label><input id="solarCompassEnabled" type="checkbox" aria-describedby="solarCompassHelp"> Mostrar bússola do entorno</label>
        <p id="solarCompassHelp" class="solar-meta">Orientada pelo Norte salvo da obra.</p>
        <details class="solar-technical"><summary>Sobre a visualização</summary>
          <p id="solarProjectionHelp" class="solar-meta" hidden>Raios: direção da luz. Pontos: primeiro bloqueio. Arco: trajetória do dia no horário local da obra.</p>
          <p id="solarProjectionStatus" class="solar-meta" role="status" aria-live="polite" hidden></p>
        </details>
      </fieldset>
      <button type="button" class="btn" id="solarConfigure" hidden>Configurar localização e Norte</button>`;
    document.querySelector(".right .head").after(this.panel);
    const select = (id) => this.panel.querySelector("#" + id);
    select("solarEnvironment").addEventListener("change", (event) =>
      environment?.setOverride(event.target.value),
    );
    select("solarHorizonEnabled").addEventListener("change", (event) =>
      environment?.showHorizon(event.target.checked),
    );
    for (const key of ["probe", "hemisphere", "glass"])
      select("solarTune-" + key).addEventListener("input", (event) =>
        environment?.setTuning(key, event.target.value),
      );
    select("solarResetEnvironmentTuning").addEventListener("click", () =>
      environment?.resetTuning(),
    );
    environment?.subscribe((state) => {
      select("solarEnvironmentCurrent").textContent = state.current
        ? ENVIRONMENTS[state.current].file
        : "Ambiente de navegação";
      select("solarEnvironmentIntensity").textContent =
        state.intensity.toFixed(2);
      select("solarEnvironmentCached").textContent = String(state.processed);
      select("solarEnvironmentOverride").textContent =
        state.override === "auto"
          ? "Automático"
          : ENVIRONMENTS[state.override].label;
      select("solarHorizonOffset").textContent =
        (state.horizon?.horizonOffset ?? 0).toFixed(1) + "°";
      select("solarHdriRotation").textContent =
        (state.horizon?.rotation ?? 0).toFixed(1) + "°";
      select("solarHorizonVisible").textContent = state.horizon?.visible
        ? "Sim"
        : "Não";
      select("solarCameraPitch").textContent =
        state.horizon?.pitch != null
          ? state.horizon.pitch.toFixed(1) + "°"
          : "—";
      select("solarDomeWarning").hidden = state.horizon?.insideDome !== false;
      select("solarGlobalEnvironment").textContent = state.globalEnvironment
        ? "Fallback de navegação"
        : "Desativada";
      select("solarProbeState").textContent =
        (state.probe?.active ? "Ativa" : "Inativa") +
        (state.probe?.state === "scheduled"
          ? " · aguardando pausa"
          : state.probe?.state === "generating"
            ? " · atualizando"
            : "");
      select("solarProbeIntensity").textContent =
        state.probe?.intensity?.toFixed(3) || "0.000";
      select("solarProbeUpdated").textContent = state.probe?.updatedAt
        ? new Date(state.probe.updatedAt).toLocaleTimeString("pt-BR")
        : "—";
      select("solarProbeAltitude").textContent =
        state.probe?.altitude != null
          ? state.probe.altitude.toFixed(1) + "°"
          : "—";
      select("solarProbeAzimuth").textContent =
        state.probe?.azimuth != null
          ? state.probe.azimuth.toFixed(1) + "°"
          : "—";
      select("solarProbeCount").textContent = String(state.probe?.updates || 0);
      select("solarHemisphereSky").textContent =
        state.hemisphere?.skyColor || "—";
      select("solarHemisphereGround").textContent =
        state.hemisphere?.groundColor || "—";
      select("solarHemisphereIntensity").textContent =
        state.hemisphere?.intensity.toFixed(3) || "0.000";
      select("solarSkyState").textContent = state.sky || "Inativo";
      select("solarReflectionMaterials").textContent = String(
        state.reflectionMaterials,
      );
      select("solarProbeError").textContent = state.probe?.error
        ? "LightProbe: " + state.probe.error
        : "";
      for (const key of ["probe", "hemisphere", "glass"]) {
        select("solarTune-" + key).value = String(state.runtime[key]);
        select("solarTuneValue-" + key).value = state.runtime[key].toFixed(3);
      }
      select("solarEnvironmentStatus").textContent =
        state.state === "loading"
          ? "Preparando " + ENVIRONMENTS[state.requested].label + "…"
          : state.state === "error"
            ? "Não foi possível carregar o HDRI. " +
              state.error +
              ". Céu e estudo solar continuam disponíveis."
            : state.override !== "auto"
              ? "Comparação manual ativa."
              : "Seleção automática por elevação e movimento solar.";
      select("solarSunIntensity").textContent = this.light.intensity.toFixed(2);
    });
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
    this.compassToggle = select("solarCompassEnabled");
    this.projectionToggle.checked = false;
    this.pathToggle.checked = true;
    this.projectionHelp = select("solarProjectionHelp");
    this.projectionStatus = select("solarProjectionStatus");
    this.projection = new SolarProjection(scene, viewport, (state) => {
      this.rayStatus = state;
      this.updateProjectionStatus();
    });
    this.projectionToggle.addEventListener("change", () =>
      this.refreshProjection(),
    );
    this.pathToggle.addEventListener("change", () => this.refreshProjection());
    this.compassToggle.addEventListener("change", () =>
      this.refreshProjection(),
    );
    this.toggle.addEventListener("change", () => {
      this.enabled = this.toggle.checked;
      this.preferredEnabled = this.enabled;
      this.syncEnabled();
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
    this.marker = new THREE.Group();
    const core = new THREE.Mesh(
      new THREE.SphereGeometry(1, 24, 16),
      new THREE.MeshBasicMaterial({
        color: 0xfff5cc,
        toneMapped: false,
        depthWrite: false,
      }),
    );
    const halo = new THREE.Sprite(
      new THREE.SpriteMaterial({
        map: solarGlowTexture(true),
        transparent: true,
        opacity: 0.85,
        blending: THREE.AdditiveBlending,
        depthWrite: false,
        toneMapped: false,
      }),
    );
    halo.scale.set(13, 13, 1);
    this.marker.add(core, halo);
    this.marker.traverse((object) => {
      object.raycast = () => {};
      object.castShadow = object.receiveShadow = false;
    });
    this.marker.name = "Indicador visual do Sol";
    this.marker.visible = false;
    this.marker.castShadow = this.marker.receiveShadow = false;
    this.marker.raycast = () => {};
    scene.add(this.marker);
    this.markerLabel = document.createElement("span");
    this.markerLabel.className = "solar-marker-label";
    this.markerLabel.setAttribute("role", "img");
    this.markerLabel.hidden = true;
    viewport.append(this.markerLabel);
    this.receiver = createShadowCatcher();
    scene.add(this.receiver);
    this.light.shadow.autoUpdate = false;
    this.viewPoint = new THREE.Vector3();
  }
  setBounds(bounds) {
    this.projection.setModel(
      bounds && !bounds.isEmpty() ? this.getModel() : null,
      bounds,
    );
    this.setVisibleBounds(bounds);
  }
  setVisibleBounds(bounds) {
    this.hasGroundReceiver = hasVisibleGroundReceiver(this.getModel?.());
    this.bounds = bounds && !bounds.isEmpty() ? bounds.clone() : null;
    this.projection.setBounds(this.bounds);
    if (this.bounds) {
      this.center = this.bounds.getCenter(new THREE.Vector3());
      const size = this.bounds.getSize(new THREE.Vector3());
      this.radius = Math.max(size.length() / 2, 0.5);
      this.fitReceiver(new THREE.Vector3(0, 1, 0));
      this.marker.scale.setScalar(this.radius * 0.032);
      this.enabled =
        this.preferredEnabled &&
        !!this.config &&
        !this.loadError &&
        !solarConfigError(this.config);
    } else {
      this.enabled = false;
      this.toggle.checked = false;
      this.body.hidden = true;
      document.querySelector(".app").classList.remove("solar-enabled");
    }
    this.syncEnabled();
    this.relight();
  }
  syncEnabled() {
    this.toggle.checked = this.enabled;
    this.body.hidden = !this.enabled;
    this.panel.querySelector("#solarVisualOptions").hidden = !this.enabled;
    document
      .querySelector(".app")
      .classList.toggle("solar-enabled", this.enabled);
  }
  fitReceiver(direction) {
    this.receiverBounds = fitShadowCatcher(
      this.receiver,
      this.bounds,
      direction,
    );
  }
  setConfig(config, loadError = "") {
    const changed =
      config &&
      (!this.config ||
        config.obra_id !== this.config.obra_id ||
        config.timezone !== this.config.timezone);
    this.config = config ? { ...config } : null;
    this.loadError = loadError;
    if (!config || loadError || solarConfigError(config)) {
      this.enabled = false;
      this.toggle.checked = false;
      this.body.hidden = true;
      document.querySelector(".app").classList.remove("solar-enabled");
    }
    if (changed && !solarConfigError(config)) this.now();
    if (config && !loadError && !solarConfigError(config) && this.bounds)
      this.enabled = this.preferredEnabled;
    this.syncEnabled();
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
    this.environment?.navigation();
    this.projection.hide();
    this.onCompassChange(false);
    this.compassToggle.disabled = true;
    this.projectionToggle.disabled = this.pathToggle.disabled = true;
    this.projectionHelp.hidden = this.projectionStatus.hidden = true;
    this.marker.visible = this.receiver.visible = false;
    this.markerLabel.hidden = true;
    this.light.intensity = 2.25;
    this.light.color.copy(this.navigationColor);
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
      this.panel.querySelector("#solarSunIntensity").textContent = "0.00";
      this.panel.querySelector("#solarPeriod").textContent = "—";
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
    // Mesma curva cromática anterior, agora sob responsabilidade exclusiva do Sol.
    const atmosphere = atmosphereInterval(result.altitude);
    this.light.color
      .set(atmosphere.from.sun)
      .lerp(this.solarColor.set(atmosphere.to.sun), atmosphere.mix);
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
    if (!this.environment) this.ambient.intensity = 0.45;
    this.environment?.update(result, this.light, this.ambient);
    this.panel.querySelector("#solarSunIntensity").textContent =
      this.light.intensity.toFixed(2);
    this.panel.querySelector("#solarPeriod").textContent = result.rising
      ? "Sol subindo"
      : "Sol descendo";
    this.fitReceiver(direction);
    this.receiver.visible = result.altitude > 0 && !this.hasGroundReceiver;
    this.marker.visible = result.altitude > 0;
    this.marker.position
      .copy(this.center)
      .addScaledVector(direction, this.radius * SOLAR_ORBIT_SCALE);
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
    const active =
      this.enabled &&
      this.bounds &&
      this.result &&
      !this.suspended &&
      !this.mapVisible &&
      !this.projectionSuspended;
    this.compassToggle.disabled = !active;
    this.onCompassChange(!!active && this.compassToggle.checked);
    this.projectionToggle.disabled = !active;
    this.pathToggle.disabled = !active;
    if (!active) {
      this.projection.hide();
      this.projectionHelp.hidden = this.projectionStatus.hidden = true;
      return;
    }
    if (this.projectionToggle.checked && this.result.altitude > 0) {
      void this.projection.requestRays(
        this.result.direction,
        this.receiverBounds,
      );
    } else this.projection.hideRays();
    if (this.pathToggle.checked)
      this.projection.showPath(this.config, this.date.value);
    else this.projection.hidePath();
    this.updateProjectionStatus();
  }
  updateProjectionStatus() {
    const active =
      this.enabled &&
      !!this.result &&
      !this.suspended &&
      !this.mapVisible &&
      !this.projectionSuspended;
    const selected = this.projectionToggle.checked || this.pathToggle.checked;
    this.projectionHelp.hidden = this.projectionStatus.hidden =
      !active || !selected;
    if (!active || !selected) return;
    if (this.projectionToggle.checked) {
      const state = this.rayStatus;
      this.projectionStatus.textContent =
        this.result.altitude <= 0
          ? "Projeção indisponível: Sol abaixo do horizonte."
          : state?.error ||
            (state?.pending
              ? "Calculando projeção…"
              : state
                ? state.rays +
                  " raios exibidos · " +
                  state.buildings +
                  " impactos no modelo."
                : "Calculando projeção…");
    } else
      this.projectionStatus.textContent = this.projection.path.visible
        ? "Trajetória do dia · horários locais da obra."
        : "Sem trajetória acima do horizonte nesta data.";
  }
  update(camera) {
    this.projection.update(camera);
    if (!this.marker.visible) {
      this.markerLabel.hidden = true;
      return;
    }
    this.viewPoint.copy(this.marker.position).project(camera);
    const x = ((this.viewPoint.x + 1) / 2) * this.viewport.clientWidth;
    const y = ((1 - this.viewPoint.y) / 2) * this.viewport.clientHeight;
    const outside =
      x < 18 ||
      x > this.viewport.clientWidth - 18 ||
      y < 18 ||
      y > this.viewport.clientHeight - 18;
    // O astro é desenhado no 3D. O HUD só o substitui quando sai do enquadramento.
    this.markerLabel.hidden =
      !outside || this.viewPoint.z < -1 || this.viewPoint.z > 1;
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
