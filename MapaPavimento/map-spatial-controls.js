import {
  solarAt,
  solarNow,
  solarConfigError,
} from "./predio-3d/solar-position.mjs";

/** Overlays independentes do zoom; usam o Norte e o fuso salvos da obra. */
export class MapSpatialControls {
  constructor(viewer, getObra) {
    Object.assign(this, { viewer, getObra });
    this.root = document.createElement("div");
    this.root.className = "map-spatial-controls";
    // Os controles não iniciam o arraste nem a seleção de ambientes do canvas.
    for (const type of [
      "pointerdown",
      "pointerup",
      "click",
      "dblclick",
      "wheel",
    ])
      this.root.addEventListener(type, (event) => event.stopPropagation());
    this.root.innerHTML = `<div class="map-compass" role="img" aria-label="Norte não configurado">
      <div class="map-compass-dial"><b class="north">N</b><span class="east">L</span><span class="south">S</span><span class="west">O</span><i></i><em class="compass-origin"></em></div><span class="compass-sun" aria-hidden="true">☀</span><output class="compass-clock">—</output><small>Norte da obra</small></div>
      <button type="button" class="mp-button mp-secondary solar-map-button" aria-expanded="false" aria-controls="mapSolarPopover">☀ Estudo solar</button>
      <section id="mapSolarPopover" class="map-solar-popover" popover="auto" aria-labelledby="mapSolarTitle">
      <header><h3 id="mapSolarTitle">Estudo solar</h3><button class="map-solar-close" type="button" aria-label="Fechar estudo solar">×</button></header>
      <div class="map-solar-fields"><label><span>▦ <span>Data</span></span><input type="date" required></label><label><span>◷ <span>Horário</span></span><input type="time" required></label></div>
      <dl><div><dt>∠ <span>Azimute</span></dt><dd class="map-azimuth">—</dd></div><div><dt>↗ <span>Elevação</span></dt><dd class="map-altitude">—</dd></div></dl>
      <div class="map-solar-slider"><span aria-hidden="true">☀</span><div><input type="range" min="0" max="1435" step="5" aria-label="Horário do estudo solar"><div class="map-solar-range-labels"><span>00h</span><span>24h</span></div></div></div>
      <label class="map-solar-toggle"><input type="checkbox" role="switch"><span>Mostrar incidência</span><span title="Indicação de direção solar; não calcula sombras na planta." aria-label="Indicação de direção solar; não calcula sombras na planta.">ⓘ</span></label><p class="solar-map-status" role="status"></p></section>`;
    viewer.container.append(this.root);
    this.panel = this.root.querySelector("section");
    this.button = this.root.querySelector(".solar-map-button");
    this.root
      .querySelector(".map-solar-close")
      .addEventListener("click", () => this.panel.hidePopover());
    this.button.setAttribute("popovertarget", this.panel.id);
    const positionPopover = () => {
      const rect = this.button.getBoundingClientRect();
      const compass = this.root
        .querySelector(".map-compass")
        .getBoundingClientRect();
      const beside = innerWidth >= 760;
      this.panel.style.left =
        Math.max(
          12,
          Math.min(beside ? compass.right + 10 : rect.left, innerWidth - 332),
        ) + "px";
      this.panel.style.top = beside
        ? Math.max(12, compass.bottom - (this.panel.offsetHeight || 320)) + "px"
        : Math.max(12, rect.top - (this.panel.offsetHeight || 320) - 8) + "px";
    };
    this.panel.addEventListener("beforetoggle", (event) => {
      if (event.newState === "open") positionPopover();
    });
    this.panel.addEventListener("toggle", (event) => {
      this.button.setAttribute(
        "aria-expanded",
        String(event.newState === "open"),
      );
      if (event.newState === "open") positionPopover();
    });
    window.addEventListener("resize", () => {
      if (this.panel.matches(":popover-open")) positionPopover();
    });
    this.date = this.root.querySelector("[type=date]");
    this.time = this.root.querySelector("[type=time]");
    this.range = this.root.querySelector("[type=range]");
    this.incidence = this.root.querySelector("[type=checkbox]");
    this.direction = document.createElement("div");
    this.direction.className = "map-solar-direction";
    this.direction.hidden = true;
    this.direction.setAttribute("aria-hidden", "true");
    this.direction.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="mapSunBeam"><stop stop-color="#f5c857" stop-opacity=".20"/><stop offset="1" stop-color="#f5c857" stop-opacity=".01"/></linearGradient></defs><polygon class="solar-beam" fill="url(#mapSunBeam)"/><line class="solar-axis"/><g class="map-sun"><circle r="8"/>${Array.from({ length: 8 }, (_, i) => `<line x1="0" y1="-13" x2="0" y2="-18" transform="rotate(${i * 45})"/>`).join("")}</g></svg>`;
    viewer.container.append(this.direction);
    this.root.addEventListener("input", (event) => {
      if (event.target === this.range) {
        const minutes = Number(this.range.value);
        this.time.value = `${String(Math.floor(minutes / 60)).padStart(2, "0")}:${String(minutes % 60).padStart(2, "0")}`;
      }
      this.update();
    });
    viewer.addEventListener("viewchange", () => this.update());
    new ResizeObserver(() => this.update()).observe(viewer.container);
  }
  async load() {
    const obra = this.getObra();
    const generation = (this.generation = (this.generation || 0) + 1);
    this.config = null;
    this.error = "";
    this.incidence.checked = true;
    this.update();
    if (!obra) return;
    try {
      const url = new URL("api.php", document.baseURI);
      url.search = new URLSearchParams({
        action: "espacial_configuracao",
        obra_id: obra,
      });
      const response = await fetch(url);
      const data = await response.json();
      if (generation !== this.generation) return;
      if (!response.ok || !data.sucesso)
        throw new Error(
          data.erro || "Não foi possível consultar a orientação.",
        );
      this.config = data.configuracao;
      this.error = "";
      const now = solarNow(this.config.timezone);
      this.date.value = now.date;
      this.time.value = now.time;
    } catch (error) {
      if (generation === this.generation) this.error = error.message;
    }
    this.update();
  }
  update() {
    const rotation = this.viewer.rotation || 0;
    const north = this.config?.norte_graus;
    const compass = this.root.querySelector(".map-compass");
    const hasNorth = Number.isFinite(north);
    compass.classList.toggle("is-unconfigured", !hasNorth);
    compass.setAttribute(
      "aria-label",
      hasNorth ? `Norte da obra: ${north} graus` : "Norte não configurado",
    );
    this.root.querySelector(".map-compass-dial").style.transform =
      `rotate(${(north || 0) + rotation}deg)`;
    this.root
      .querySelectorAll(".map-compass-dial b, .map-compass-dial span")
      .forEach((label) => {
        label.style.transform = `rotate(${-((north || 0) + rotation)}deg)`;
      });
    this.root.querySelector(".map-compass small").textContent = hasNorth
      ? "Norte da obra"
      : "Norte não definido";
    const error =
      this.error ||
      (this.config
        ? solarConfigError(this.config)
        : "Selecione uma obra com localização e Norte configurados.");
    for (const input of [this.date, this.time, this.range, this.incidence])
      input.disabled = !!error;
    this.direction.hidden = true;
    const status = this.root.querySelector(".solar-map-status");
    status.textContent = error;
    if (error) {
      this.root.querySelector(".compass-sun").hidden = true;
      this.root.querySelector(".compass-clock").textContent = "—";
      this.root.querySelector(".map-azimuth").textContent = "—";
      this.root.querySelector(".map-altitude").textContent = "—";
      return;
    }
    try {
      const sun = solarAt(this.config, this.date.value, this.time.value);
      this.root.querySelector(".map-azimuth").textContent =
        `${sun.azimuth.toFixed(1)}°`;
      this.root.querySelector(".map-altitude").textContent =
        `${sun.altitude.toFixed(1)}°`;
      const [hours, minutes] = this.time.value.split(":").map(Number);
      this.range.value = hours * 60 + minutes;
      this.range.style.setProperty(
        "--solar-progress",
        `${(Number(this.range.value) / 1435) * 100}%`,
      );
      this.root.querySelector(".compass-clock").textContent =
        `${sun.altitude > 0 ? "☀" : "☾"} ${this.time.value}`;
      const angle = ((north + sun.azimuth + rotation) * Math.PI) / 180;
      const compassSun = this.root.querySelector(".compass-sun");
      compassSun.hidden = sun.altitude <= 0;
      compassSun.style.left = `${50 + Math.sin(angle) * 34}%`;
      compassSun.style.top = `${44 - Math.cos(angle) * 29}%`;
      status.textContent =
        sun.altitude <= 0 ? "Sol abaixo do horizonte." : this.config.timezone;
      this.direction.hidden =
        !this.incidence.checked || sun.altitude <= 0 || !this.viewer.page;
      this.drawSun(angle);
    } catch (error) {
      status.textContent = error.message;
    }
  }
  drawSun(angle) {
    if (this.direction.hidden) return;
    const container = this.viewer.container.getBoundingClientRect();
    const plant = this.viewer.surface.getBoundingClientRect();
    const width = container.width,
      height = container.height;
    if (!width || !height) return;
    const cx = plant.left - container.left + plant.width / 2;
    const cy = plant.top - container.top + plant.height / 2;
    const dx = Math.sin(angle),
      dy = -Math.cos(angle);
    const radius =
      1 /
      Math.sqrt(
        (dx / (plant.width / 2 + 100)) ** 2 +
          (dy / (plant.height / 2 + 90)) ** 2,
      );
    const sx = Math.max(28, Math.min(width - 28, cx + dx * radius));
    const sy = Math.max(28, Math.min(height - 28, cy + dy * radius));
    const spread = Math.min(
      95,
      Math.max(24, Math.min(plant.width, plant.height) * 0.3),
    );
    const svg = this.direction.querySelector("svg");
    svg.setAttribute("viewBox", `0 0 ${width} ${height}`);
    svg
      .querySelector("polygon")
      .setAttribute(
        "points",
        `${sx},${sy} ${cx - dy * spread},${cy + dx * spread} ${cx + dy * spread},${cy - dx * spread}`,
      );
    const line = svg.querySelector(".solar-axis");
    for (const [key, value] of Object.entries({
      x1: sx,
      y1: sy,
      x2: cx,
      y2: cy,
    }))
      line.setAttribute(key, value);
    svg
      .querySelector(".map-sun")
      .setAttribute("transform", `translate(${sx} ${sy})`);
    const gradient = svg.querySelector("linearGradient");
    gradient.setAttribute("gradientUnits", "userSpaceOnUse");
    for (const [key, value] of Object.entries({
      x1: sx,
      y1: sy,
      x2: cx,
      y2: cy,
    }))
      gradient.setAttribute(key, value);
  }
}
