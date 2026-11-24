const FLIGHT_KEYS = new Set([
  "KeyW", "KeyS", "KeyA", "KeyD", "Space", "ShiftLeft", "ShiftRight",
  "ArrowUp", "ArrowDown", "ArrowLeft", "ArrowRight",
]);
const INPUTS = "input,textarea,select,[contenteditable],dialog[open]";

export function setupSeagullMode({
  viewport, bar, scene, canvas, orbitCamera, controls, available,
  getSource, getFloor, disposeResources, show, onChange, onExit,
}) {
  const floorButton = document.createElement("button");
  floorButton.type = "button";
  floorButton.className = "btn";
  floorButton.textContent = "Voar à altura do pavimento";
  floorButton.hidden = true;
  const animation = document.createElement("select");
  animation.className = "btn";
  animation.setAttribute("aria-label", "Animação da gaivota");
  animation.title = animation.getAttribute("aria-label");
  animation.append(new Option("Animação automática", "-1"));
  animation.hidden = true;
  animation.addEventListener("change", () => canvas.focus({ preventScroll: true }));
  bar.append(floorButton, animation);

  const pad = document.createElement("div");
  pad.hidden = true;
  pad.className = "exploration-pad seagull-pad";
  pad.setAttribute("role", "group");
  pad.setAttribute("aria-label", "Controles de voo");
  for (const [code, label, glyph] of [
    ["KeyA", "Virar à esquerda", "←"], ["KeyW", "Avançar", "↑"],
    ["KeyS", "Recuar", "↓"], ["KeyD", "Virar à direita", "→"],
    ["Space", "Subir", "+ altitude"], ["ShiftLeft", "Descer", "− altitude"],
  ]) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "btn";
    button.textContent = glyph;
    button.dataset.key = code;
    button.setAttribute("aria-label", label);
    pad.append(button);
  }
  viewport.append(pad);
  const altitude = document.createElement("span");
  altitude.className = "flight-altitude";
  altitude.title = "Cota de voo no sistema de coordenadas da cena";
  altitude.hidden = true;
  bar.prepend(altitude);
  const meters = new Intl.NumberFormat("pt-BR", { maximumFractionDigits: 1 });
  let lastAltitude;
  const help = "W/S avançar e recuar · A/D virar · arraste para mirar · Espaço/Shift altitude · roda distância · Esc sair";
  const touch = matchMedia("(any-pointer: coarse), (max-width: 600px)");
  const keys = new Set();
  const touchKeys = new Map();
  let mode, request, flyer, saved, waitingCamera, drag, previousFrame;
  let avatar, avatarLoading, avatarUnavailable = false, disposed = false;

  function clearInput() {
    keys.clear();
    for (const [id, button] of touchKeys)
      if (button.hasPointerCapture(id)) button.releasePointerCapture(id);
    touchKeys.clear();
    if (drag && canvas.hasPointerCapture(drag.id)) canvas.releasePointerCapture(drag.id);
    drag = null;
    previousFrame = null;
  }

  function instruction(message) {
    const commands = touch.matches ? "Arraste para mirar e use os controles de voo." : help;
    show(message ?? (avatarUnavailable ? "Modelo da gaivota indisponível. " + commands : commands));
  }

  async function prepareAvatar() {
    if (avatar || avatarUnavailable) return;
    show("Carregando o modelo da gaivota…", true);
    avatarLoading ??= import("./seagull-avatar.js?v=1")
      .then(({ loadSeagull }) => loadSeagull(disposeResources))
      .then((loaded) => {
        if (disposed) {
          loaded.dispose();
          return;
        }
        avatar = loaded;
        const seconds = new Intl.NumberFormat("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        avatar.clips.forEach((clip, index) => {
          animation.add(new Option(`${clip.name} · ${seconds.format(clip.duration)} s`, String(index)));
        });
      })
      .catch((error) => {
        avatarUnavailable = true;
        console.warn("[Flow modelo da gaivota]", error);
      })
      .finally(() => { avatarLoading = null; });
    await avatarLoading;
    if (flyer && flyer.altitudeTarget == null) instruction();
  }

  function listen(signal) {
    const input = { capture: true, signal };
    window.addEventListener("keydown", (event) => {
      if (event.defaultPrevented) return;
      if (event.code === "Escape" && !document.querySelector("dialog[open]")) {
        event.preventDefault();
        event.stopImmediatePropagation();
        onExit();
        return;
      }
      if (event.ctrlKey || event.metaKey || event.altKey ||
          event.target.closest?.(INPUTS) || !FLIGHT_KEYS.has(event.code) ||
          // Let the focused button handle Space activation on keyboard.
          (event.code === "Space" && event.target.closest?.("button"))) return;
      event.preventDefault();
      keys.add(event.code);
    }, input);
    window.addEventListener("keyup", (event) => keys.delete(event.code), input);
    window.addEventListener("blur", clearInput, { signal });
    document.addEventListener("visibilitychange", clearInput, { signal });
    document.addEventListener("focusin", () => keys.clear(), { signal });
    touch.addEventListener("change", () => {
      pad.hidden = !flyer || !touch.matches;
      if (flyer && !request) instruction();
    }, { signal });
    canvas.addEventListener("pointerdown", (event) => {
      if (!flyer || drag || event.button !== 0) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      canvas.focus({ preventScroll: true });
      drag = { id: event.pointerId, x: event.clientX, y: event.clientY };
      canvas.setPointerCapture(event.pointerId);
    }, input);
    canvas.addEventListener("pointermove", (event) => {
      if (!flyer || drag?.id !== event.pointerId) return;
      event.stopImmediatePropagation();
      flyer.look(event.clientX - drag.x, event.clientY - drag.y);
      drag.x = event.clientX;
      drag.y = event.clientY;
    }, input);
    for (const name of ["pointerup", "pointercancel", "lostpointercapture"]) {
      canvas.addEventListener(name, (event) => {
        if (drag?.id !== event.pointerId) return;
        event.stopImmediatePropagation();
        drag = null;
      }, input);
      pad.addEventListener(name, (event) => touchKeys.delete(event.pointerId), { signal });
    }
    canvas.addEventListener("wheel", (event) => {
      if (!flyer) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      flyer.zoom(event.deltaY);
    }, { ...input, passive: false });
    pad.addEventListener("pointerdown", (event) => {
      const button = event.target.closest("button[data-key]");
      if (!flyer || request || !button) return;
      event.preventDefault();
      button.setPointerCapture(event.pointerId);
      touchKeys.set(event.pointerId, button);
    }, { signal });
  }

  async function prepare() {
    request?.abort();
    const controller = new AbortController();
    request = controller;
    const source = getSource();
    if (flyer) {
      saved = { model: source.model, pose: flyer.snapshot() };
      waitingCamera = flyer.camera;
      flyer = null;
    }
    const state = saved?.model === source.model ? saved.pose : null;
    clearInput();
    show("Preparando a área de voo…", true);
    try {
      const [{ SeagullFlight }, { ExplorationCollision }] = await Promise.all([
        import("./seagull-flight.js?v=1"),
        import("./exploration-collision.js?v=5"),
      ]);
      controller.signal.throwIfAborted();
      if (!source.bounds || source.bounds.isEmpty()) {
        onExit("O modelo não tem dimensões disponíveis para preparar o voo.");
        return;
      }
      const collision = new ExplorationCollision({ ...source, flight: true });
      await collision.prepare(controller.signal);
      if (request !== controller) return;
      const next = new SeagullFlight({ ...source, collision, orbitCamera, orbitTarget: controls.target });
      if (!next.spawn(state)) {
        onExit("Não foi possível encontrar um ponto livre para começar o voo.");
        return;
      }
      flyer = next;
      request = null;
      waitingCamera = null;
      pad.hidden = !touch.matches;
      instruction();
      onChange(flyer.position);
      void prepareAvatar();
    } catch (error) {
      if (request !== controller) return;
      console.warn("[Flow Gaivota]", error);
      onExit("Não foi possível preparar a área de voo.");
    }
  }

  floorButton.addEventListener("click", () => {
    const floor = getFloor();
    if (!flyer || request || !floor) return;
    flyer.goToFloor(floor);
    canvas.focus({ preventScroll: true });
    instruction("Ajustando a altitude para o pavimento selecionado…");
  });

  return {
    get camera() { return flyer?.camera; },
    get collision() { return flyer?.collision; },
    pause(value) {
      if (!mode) return;
      clearInput();
      if (value) {
        mode.input.abort();
        avatar?.detach();
      } else {
        mode.input = new AbortController();
        listen(mode.input.signal);
      }
    },
    get engaged() { return !!mode; },
    start(anchor) {
      if (mode || disposed || !available()) return;
      avatarUnavailable = false;
      mode = { input: new AbortController(), controlsEnabled: controls.enabled, model: getSource().model };
      if (anchor) saved = {model: mode.model, pose: anchor};
      controls.enabled = false;
      canvas.tabIndex = 0;
      canvas.focus({ preventScroll: true });
      viewport.classList.add("seagull-flying");
      listen(mode.input.signal);
      return prepare();
    },
    refresh() {
      if (mode) void prepare();
    },
    get anchor() { return flyer?.snapshot(); },
    stop() {
      if (!mode) return;
      if (flyer) saved = { model: mode.model, pose: flyer.snapshot() };
      request?.abort();
      request = null;
      mode.input.abort();
      clearInput();
      canvas.removeAttribute("tabindex");
      controls.enabled = mode.controlsEnabled;
      mode = flyer = waitingCamera = null;
      avatar?.detach();
      pad.hidden = floorButton.hidden = animation.hidden = true;
      viewport.classList.remove("seagull-flying");
      altitude.hidden = true;
      lastAltitude = undefined;
      onChange(null);
    },
    invalidate() {
      saved = null;
    },
    dispose() {
      this.stop();
      disposed = true;
      saved = null;
      avatar?.dispose();
      avatar = null;
    },
    update(now) {
      if (!mode) return null;
      if (!available()) {
        onExit();
        return null;
      }
      floorButton.hidden = !getFloor();
      floorButton.disabled = !flyer || !!request;
      if (!flyer) return waitingCamera ?? null;
      const dt = previousFrame == null ? 0 : Math.min((now - previousFrame) / 1000, 0.075);
      previousFrame = now;
      const touched = Array.from(touchKeys.values(), (button) => button.dataset.key);
      const pressed = (...options) => options.some((key) => keys.has(key) || touched.includes(key));
      const throttle = Number(pressed("KeyW", "ArrowUp")) - Number(pressed("KeyS", "ArrowDown"));
      const steer = Number(pressed("KeyD", "ArrowRight")) - Number(pressed("KeyA", "ArrowLeft"));
      const lift = Number(pressed("Space")) - Number(pressed("ShiftLeft", "ShiftRight"));
      const guiding = flyer.altitudeTarget != null;
      const result = flyer.update(dt, throttle, steer, lift);
      const decimeters = Math.round(flyer.position.y * 10);
      if (decimeters !== lastAltitude) {
        lastAltitude = decimeters;
        altitude.textContent = `Cota · ${meters.format(decimeters / 10)} m`;
        altitude.hidden = false;
      }
      if (avatar) {
        avatar.update(dt, flyer, throttle, Number(animation.value));
        if (!avatar.root.parent) scene.add(avatar.root);
        animation.hidden = false;
      }
      if (result === "blocked") {
        instruction("A passagem está bloqueada. Contorne o obstáculo e tente ajustar a altitude novamente.");
      } else if (guiding && flyer.altitudeTarget == null) {
        instruction();
      }
      return flyer.camera;
    },
  };
}
