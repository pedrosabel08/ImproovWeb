import { createPersonPicker } from "./person-camera.js?v=2";
import { setupSeagullMode } from "./seagull-mode.js?v=3";

const KEYS = new Set([
  "KeyW",
  "KeyA",
  "KeyS",
  "KeyD",
  "ArrowUp",
  "ArrowLeft",
  "ArrowDown",
  "ArrowRight",
  "ShiftLeft",
  "ShiftRight",
]);

export function setupExploration({
  viewport,
  scene,
  canvas,
  orbitCamera,
  controls,
  available,
  getSource,
  getFlightSource,
  getFloor,
  disposeResources,
  onChange,
}) {
  const bar = document.createElement("div");
  bar.className = "person-camera-controls";
  bar.setAttribute("role", "group");
  bar.setAttribute("aria-label", "Controles do personagem");
  bar.innerHTML = `<button class="btn" type="button" aria-pressed="false" aria-label="Posicionar câmera" title="Posicionar câmera" hidden>
    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="currentColor"><circle cx="12" cy="4" r="3"/><path d="M8 8h8l2 7-2 .6-1-4V22h-2v-7h-2v7H9V11.6l-1 4L6 15z"/></svg>
    </button><button class="btn" type="button" hidden>Terceira pessoa</button>
    <button class="btn" type="button" hidden>Voltar à vista aérea</button>`;
  const [button, perspectiveButton, exitButton] = bar.children;
  const modes = document.createElement("nav");
  modes.className = "hud-modes";
  modes.setAttribute("aria-label", "Modo de navegação");
  for (const [value, label, icon] of [
    ["orbit", "Orbital", "rotate"], ["josh", "Caminhada", "person-walking"],
    ["seagull", "Voo", "paper-plane"],
  ]) {
    const mode = document.createElement("button");
    mode.type = "button";
    mode.dataset.mode = value;
    mode.innerHTML = `<i aria-hidden="true" class="fa-solid fa-${icon}"></i>`;
    mode.append(label);
    mode.addEventListener("click", () => selectMode(value));
    modes.append(mode);
  }
  function markMode(value) {
    viewport.dataset.navigation = value;
    for (const mode of modes.children)
      mode.setAttribute("aria-pressed", String(mode.dataset.mode === value));
  }
  markMode("orbit");
  function focusOrbital() {
    modes.firstElementChild.focus({ preventScroll: true });
  }
  const hint = document.createElement("div");
  hint.id = "explorationHint";
  hint.className = "exploration-hint";
  hint.hidden = true;
  hint.setAttribute("role", "status");
  hint.innerHTML =
    '<canvas data-thinking-orb data-orb-state="composing" data-orb-size="32" aria-label="Preparando a câmera" hidden></canvas><span></span>';
  const [orb, message] = hint.children;
  const pad = document.createElement("div");
  pad.hidden = true;
  pad.className = "exploration-pad";
  pad.setAttribute("role", "group");
  pad.setAttribute("aria-label", "Movimentar personagem");
  for (const [key, label, glyph] of [
    ["KeyA", "Ir à esquerda", "←"],
    ["KeyW", "Avançar", "↑"],
    ["KeyS", "Recuar", "↓"],
    ["KeyD", "Ir à direita", "→"],
  ]) {
    const control = document.createElement("button");
    control.type = "button";
    control.className = "btn";
    control.dataset.key = key;
    control.textContent = glyph;
    control.setAttribute("aria-label", label);
    pad.append(control);
  }
  viewport.append(modes, bar, hint, pad);
  const pick = createPersonPicker(orbitCamera, canvas);
  const touch = matchMedia("(any-pointer: coarse), (max-width: 600px)");
  const keys = new Set(),
    touchKeys = new Map();
  let explorer = null,
    placing = false,
    loading = null,
    characterPromise = null,
    cache = null,
    savedJosh = null;
  let previousEnabled = true,
    previousFrame = null,
    drag = null,
    orbStop = null;
  const placementPointers = new Set();
  let paused = false;

  function updateTouch() {
    if (!explorer) return;
    pad.hidden = !touch.matches;
  }
  touch.addEventListener("change", updateTouch);

  function show(text = "", busy = false) {
    hint.hidden = !text;
    orb.hidden = !busy;
    message.textContent = text;
    orbStop?.();
    orbStop = null;
    if (busy) {
      orb.setAttribute("aria-label", text.replace(/…$/, ""));
      orbStop = window.thinkingOrbs?.(hint);
    }
  }

  const flight = setupSeagullMode({
    viewport, bar, scene, canvas, orbitCamera, controls, available,
    getSource: getFlightSource, getFloor, disposeResources, show,
    onChange: (position) => onChange(position, "seagull"),
    onExit(message) {
      stop();
      if (message) show(message);
      focusOrbital();
    },
  });

  function stop() {
    paused = false;
    stopJosh();
    flight.stop();
    markMode("orbit");
    exitButton.hidden = true;
    show();
  }

  function stopJosh() {
    const wasActive = !!explorer;
    if (wasActive) savedJosh = {
      model: cache.model,
      position: explorer.position.clone(),
      yaw: explorer.yaw,
      pitch: explorer.pitch,
      firstPerson: explorer.firstPerson,
      firstPersonFov: explorer.firstPersonFov,
      distance: explorer.distance,
    };
    if (wasActive && drag && canvas.hasPointerCapture(drag.id))
      canvas.releasePointerCapture(drag.id);
    loading?.abort();
    loading = null;
    explorer?.dispose();
    explorer = null;
    placing = false;
    keys.clear();
    touchKeys.clear();
    placementPointers.clear();
    drag = null;
    pad.hidden = true;
    viewport.classList.remove(
      "exploring",
      "exploration-loading",
      "exploration-placing",
    );
    delete viewport.dataset.explorationAnimation;
    delete viewport.dataset.personPerspective;
    button.setAttribute("aria-pressed", "false");
    button.title = "Posicionar câmera";
    button.setAttribute("aria-label", button.title);
    button.hidden = perspectiveButton.hidden = true;
    if (wasActive) {
      controls.enabled = previousEnabled;
      onChange(null, "josh");
    }
  }

  function startPlacement() {
    if (!available()) return;
    stopJosh();
    placing = true;
    button.hidden = false;
    viewport.classList.add("exploration-placing");
    button.setAttribute("aria-pressed", "true");
    button.title = "Cancelar posicionamento (Esc)";
    button.setAttribute("aria-label", "Cancelar posicionamento");
    show("Clique no terreno ou em um pavimento para posicionar a câmera.");
  }

  function syncPerspective() {
    perspectiveButton.textContent = explorer.firstPerson
      ? "Terceira pessoa"
      : "Primeira pessoa";
    viewport.dataset.personPerspective = explorer.firstPerson
      ? "first"
      : "third";
  }

  async function place(event, resume = null) {
    if (!available() || loading) return;
    const source = getSource();
    const hit = event ? pick(event, source) : null;
    if (event && !hit) {
      show("Clique em uma superfície visível do terreno ou do prédio.");
      return;
    }
    const controller = new AbortController();
    loading = controller;
    viewport.classList.add("exploration-loading");
    show("Preparando a câmera…", true);
    try {
      const { loadJosh, ExplorationCharacter } =
        await import("./exploration-character.js?v=5");
      controller.signal.throwIfAborted();
      const matching =
        cache &&
        cache.model === source.model &&
        cache.group === source.group &&
        cache.scope === source.scope;
      const collision = matching
        ? cache.collision
        : await ExplorationCharacter.prepare(source, controller.signal);
      if (loading !== controller) return;
      cache = {
        model: source.model,
        group: source.group,
        scope: source.scope,
        collision,
      };
      const position = resume?.position.clone() ?? collision.place(hit);
      if (!position || (resume && collision.blocked(position.x, position.y, position.z))) {
        loading = null;
        viewport.classList.remove("exploration-loading");
        show("Escolha um ponto com espaço livre sobre o piso.");
        return;
      }
      characterPromise ||= loadJosh().catch((error) => {
        characterPromise = null;
        throw error;
      });
      const josh = await characterPromise;
      if (loading !== controller) return;
      const current = getSource();
      if (
        !available() ||
        current.model !== source.model ||
        current.group !== source.group ||
        current.scope !== source.scope
      ) {
        stop();
        return;
      }
      explorer = new ExplorationCharacter(josh, collision, orbitCamera, {
        position,
        towardCamera: hit?.towardCamera,
        resume,
      });
      loading = null;
      placing = false;
      scene.add(josh.scene);
      previousEnabled = controls.enabled;
      controls.enabled = false;
      previousFrame = null;
      viewport.classList.remove("exploration-loading", "exploration-placing");
      viewport.classList.add("exploring");
      button.title = "Reposicionar câmera";
      button.setAttribute("aria-label", button.title);
      perspectiveButton.hidden = exitButton.hidden = false;
      syncPerspective();
      updateTouch();
      show(touch.matches ? "Arraste para olhar e use as setas para caminhar." :
        "WASD mover · arraste para olhar · Shift correr · V perspectiva · Esc sair");
      onChange(explorer.position, "josh");
    } catch (failure) {
      if (loading !== controller) return;
      loading = null;
      viewport.classList.remove("exploration-loading");
      console.warn("[Flow câmera de inspeção]", failure);
      show(
        "Não foi possível preparar a câmera. Escolha um ponto para tentar novamente.",
      );
    }
  }

  function selectMode(next) {
    if (viewport.dataset.navigation === next) return;
    stop();
    if (!available() || next === "orbit") return;
    markMode(next);
    exitButton.textContent = next === "seagull" ? "Sair do voo" : "Voltar à vista aérea";
    exitButton.hidden = false;
    if (next === "seagull") {
      // Both modes can build a large collision index. Keep only the active one.
      cache = null;
      flight.start();
    } else {
      startPlacement();
      const source = getSource();
      if (savedJosh?.model === source.model) void place(null, savedJosh);
    }
  }
  button.addEventListener("click", () => (placing ? stop() : startPlacement()));
  exitButton.addEventListener("click", () => {
    stop();
    focusOrbital();
  });
  perspectiveButton.addEventListener("click", () => {
    if (!explorer) return;
    explorer.togglePerspective();
    syncPerspective();
  });
  window.addEventListener(
    "keydown",
    (event) => {
      if (
        paused ||
        (!explorer && !placing) ||
        event.defaultPrevented ||
        event.target.closest?.(
          "input,textarea,select,[contenteditable],dialog[open]",
        )
      )
        return;
      if (event.code === "Escape") {
        event.preventDefault();
        event.stopImmediatePropagation();
        stop();
        focusOrbital();
        return;
      }
      if (
        explorer &&
        event.code === "KeyV" &&
        !event.repeat &&
        !event.ctrlKey &&
        !event.metaKey &&
        !event.altKey
      ) {
        event.preventDefault();
        explorer.togglePerspective();
        syncPerspective();
        return;
      }
      if (
        !explorer ||
        event.ctrlKey ||
        event.metaKey ||
        event.altKey ||
        !KEYS.has(event.code)
      )
        return;
      event.preventDefault();
      keys.add(event.code);
    },
    true,
  );
  window.addEventListener("keyup", (event) => keys.delete(event.code), true);
  window.addEventListener("blur", () => {
    keys.clear();
    touchKeys.clear();
    placementPointers.clear();
    if (drag && canvas.hasPointerCapture(drag.id))
      canvas.releasePointerCapture(drag.id);
    drag = null;
  });
  document.addEventListener("focusin", (event) => {
    if (
      event.target.closest?.(
        "input,textarea,select,[contenteditable],dialog[open]",
      )
    )
      keys.clear();
  });
  document.addEventListener("visibilitychange", () => {
    if (document.hidden) {
      keys.clear();
      touchKeys.clear();
      previousFrame = null;
    }
  });

  canvas.addEventListener(
    "pointerdown",
    (event) => {
      if (paused || (!explorer && !placing)) return;
      if (placing && event.pointerType === "touch") {
        placementPointers.add(event.pointerId);
        if (placementPointers.size > 1) {
          drag = null;
          return;
        }
      }
      if (event.button !== 0 || drag != null) return;
      if (explorer) {
        event.stopImmediatePropagation();
        event.preventDefault();
        canvas.setPointerCapture(event.pointerId);
      }
      drag = { id: event.pointerId, x: event.clientX, y: event.clientY };
    },
    true,
  );
  canvas.addEventListener(
    "pointermove",
    (event) => {
      if (paused || !explorer) return;
      event.stopImmediatePropagation();
      if (explorer && drag?.id === event.pointerId) {
        explorer.look(event.clientX - drag.x, event.clientY - drag.y);
        drag.x = event.clientX;
        drag.y = event.clientY;
      }
    },
    true,
  );
  for (const name of ["pointerup", "pointercancel", "lostpointercapture"])
    canvas.addEventListener(
      name,
      (event) => {
        if (paused || (!explorer && !placing)) return;
        if (placing && event.type !== "lostpointercapture")
          placementPointers.delete(event.pointerId);
        if (explorer) event.stopImmediatePropagation();
        if (drag?.id !== event.pointerId) return;
        const click =
          event.type === "pointerup" &&
          !placementPointers.size &&
          Math.hypot(event.clientX - drag.x, event.clientY - drag.y) <= 6;
        drag = null;
        if (placing && click) void place(event);
      },
      true,
    );
  canvas.addEventListener(
    "wheel",
    (event) => {
      if (paused || !explorer) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      explorer.zoom(event.deltaY);
    },
    { capture: true, passive: false },
  );
  pad.addEventListener("pointerdown", (event) => {
    const control = event.target.closest("button");
    if (!explorer || !control) return;
    event.preventDefault();
    control.setPointerCapture(event.pointerId);
    touchKeys.set(event.pointerId, control.dataset.key);
  });
  for (const name of ["pointerup", "pointercancel", "lostpointercapture"])
    pad.addEventListener(name, (event) => touchKeys.delete(event.pointerId));

  return {
    selectMode,
    get camera() { return explorer?.camera || flight.camera; },
    groundAt(x, z) {
      return (cache?.collision || flight.collision)?.groundHeight(x, z, getFlightSource().bounds?.min.y ?? 0) ?? 0;
    },
    pause(value) {
      paused = value;
      keys.clear();
      touchKeys.clear();
      drag = null;
      previousFrame = null;
      if (explorer) explorer.josh.scene.visible = !value;
      flight.pause(value);
    },
    get engaged() {
      return !!(placing || explorer || flight.engaged);
    },
    get flying() {
      return flight.engaged;
    },
    refreshFlight() {
      flight.refresh();
    },
    stop,
    invalidate() {
      stop();
      cache = null;
      savedJosh = null;
      flight.invalidate();
    },
    dispose() {
      this.invalidate();
      flight.dispose();
    },
    update(now) {
      if (paused) return this.camera;
      const enabled = available();
      if (!enabled && (explorer || placing)) stop();
      button.disabled = !enabled;
      if (modes.firstElementChild.disabled === enabled)
        for (const mode of modes.children) mode.disabled = !enabled;
      if (flight.engaged) return flight.update(now);
      if (!explorer) return null;
      const dt =
        previousFrame == null
          ? 0
          : Math.min((now - previousFrame) / 1000, 0.065);
      previousFrame = now;
      const pressed = (...options) =>
        options.some((key) => keys.has(key)) ||
        (touchKeys.size > 0 &&
          [...touchKeys.values()].some((key) => options.includes(key)));
      const x =
        Number(pressed("KeyD", "ArrowRight")) -
        Number(pressed("KeyA", "ArrowLeft"));
      const z =
        Number(pressed("KeyW", "ArrowUp")) -
        Number(pressed("KeyS", "ArrowDown"));
      const state = explorer.update(
        dt,
        x,
        z,
        pressed("ShiftLeft", "ShiftRight"),
      );
      const animation = ["idle", "walking", "running"][state];
      if (viewport.dataset.explorationAnimation !== animation)
        viewport.dataset.explorationAnimation = animation;
      return explorer.camera;
    },
  };
}
