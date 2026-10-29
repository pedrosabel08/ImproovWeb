// Bootstrap leve: Three.js, GSAP e o GLB só são solicitados se houver cadastro.
const openedAt = performance.timeOrigin || Date.now();
const hero = document.getElementById("obra3dHero");
const slot = document.getElementById("obra3dSlot");
const initialLoading = document.getElementById("obra3dInitialLoading");
let initialOrbCleanup;
let currentId = "",
  generation = 0,
  controller,
  viewer,
  scroll,
  layer,
  orbCleanup;
let metadata = window.__obra3dData;

function finishInitialLoading() {
  initialOrbCleanup?.();
  initialOrbCleanup = null;
  if (initialLoading) initialLoading.hidden = true;
}

// Primeiro a obra inteira; depois apenas o modelo, quando os dados já existem.
function syncLoadingPhase() {
  const hasInfo = hero.dataset.metadataReady === "true";
  const pending = ["checking", "loading", "ready"].includes(hero.dataset.state);
  if (initialLoading) {
    const parent = hasInfo ? slot : hero;
    if (initialLoading.parentElement !== parent) parent.append(initialLoading);
    initialLoading.hidden = !pending || (hasInfo && !!layer);
  }
  hero.setAttribute(
    "aria-busy",
    String(pending && (!hasInfo || hero.dataset.state !== "ready")),
  );
  if (initialLoading && !initialLoading.hidden) {
    if (window.thinkingOrbs && !initialOrbCleanup)
      initialOrbCleanup = window.thinkingOrbs(initialLoading);
  } else finishInitialLoading();
  if (!layer) return;
  layer.toggleAttribute("data-waiting-info", !hasInfo);
  const loading = layer.querySelector(".obra-3d-loading");
  loading.hidden = !hasInfo || layer.dataset.state !== "loading";
  if (!loading.hidden && window.thinkingOrbs && !orbCleanup)
    orbCleanup = window.thinkingOrbs(loading);
  else if (loading.hidden) {
    orbCleanup?.();
    orbCleanup = null;
  }
}

function loadScript(src, available) {
  if (available()) return Promise.resolve();
  return new Promise((resolve, reject) => {
    const script = document.createElement("script");
    script.src = src;
    script.onload = resolve;
    script.onerror = () => {
      script.remove();
      reject(new Error(`Falha ao carregar ${src}`));
    };
    document.head.append(script);
  });
}

let dependencies;
function loadDependencies() {
  return (dependencies ??= (async () => {
    await loadScript(
      "https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js",
      () => !!window.gsap,
    );
    await loadScript(
      "https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollTrigger.min.js",
      () => !!window.ScrollTrigger,
    );
    return Promise.all([
      import("./obra-3d-viewer.js?v=20261029"),
      import("./obra-3d-scroll.js?v=20261023"),
    ]);
  })().catch((error) => {
    dependencies = null;
    throw error;
  }));
}

function updateInfo() {
  if (metadata?.obraId !== currentId) return;
  const obra = metadata.obra || {};
  document.getElementById("obra3dName").textContent =
    obra.nome_real || obra.nome_obra || "";
  const location = document.getElementById("obra3dLocation");
  location.textContent = obra.local?.trim() || "";
  location.hidden = !location.textContent;
  hero.dataset.metadataReady = "true";
  syncLoadingPhase();
}

// Os cards originais são a fonte de verdade, inclusive ao atualizar filtros/dados.
function mirrorStats() {
  hero.querySelectorAll("[data-obra3d-mirror]").forEach((target) => {
    const value =
      document.querySelector(target.dataset.obra3dMirror)?.textContent.trim() ||
      "—";
    if (target.textContent !== value) target.textContent = value;
  });
}
const statsObserver = new MutationObserver(mirrorStats);
statsObserver.observe(document.getElementById("obraDashboard"), {
  subtree: true,
  childList: true,
  characterData: true,
});

function clear(hide = true) {
  controller?.abort();
  scroll?.destroy();
  scroll = null;
  viewer?.destroy();
  viewer = null;
  orbCleanup?.();
  orbCleanup = null;
  layer?.remove();
  layer = null;
  hero.hidden = hide;
  hero.removeAttribute("data-metadata-ready");
}

async function api(action, id, signal) {
  const url = new URL("../MapaPavimento/api.php", location.href);
  url.search = new URLSearchParams({ action, obra_id: id });
  const response = await fetch(url, { signal, credentials: "same-origin" });
  const data = await response.json();
  if (!response.ok || !data.sucesso)
    throw new Error(data.erro || `HTTP ${response.status}`);
  return data;
}

async function start(id) {
  id = String(id || "");
  if (!/^\d+$/.test(id) || id === currentId) return;
  const token = ++generation;
  clear(false);
  currentId = id;
  hero.dataset.state = "checking";
  hero.setAttribute("aria-busy", "true");
  updateInfo();
  syncLoadingPhase();
  controller = new AbortController();
  const signal = controller.signal;
  try {
    let data;
    const entry = window.__obra3dEntry;
    if (entry?.id === id && !entry.used) {
      entry.used = true;
      const result = await entry.result;
      if (result.error) throw result.error;
      data = result.data;
    } else {
      entry?.controller.abort();
      data = await api("modelos_3d", id, signal);
    }
    if (token !== generation) return;
    if (!data.modelos.length) {
      finishInitialLoading();
      hero.hidden = true;
      hero.dataset.state = "absent";
      hero.setAttribute("aria-busy", "false");
      return;
    }
    const model = data.modelos[0]; // Mesma seleção inicial do viewer completo (nome,id).
    updateInfo();
    mirrorStats();
    hero.hidden = false;
    hero.dataset.state = "loading";
    layer = document.createElement("div");
    layer.className = "obra-3d-layer";
    layer.dataset.modelId = model.id;
    layer.dataset.state = "loading";
    const fullUrl = new URL(
      "../MapaPavimento/predio-3d/visualizador-glb-navegacao.php",
      location.href,
    );
    fullUrl.search = new URLSearchParams({ obra_id: id, modelo_id: model.id });
    layer.innerHTML = `<div class="obra-3d-mini-frame" aria-hidden="true"></div><div class="obra-3d-canvas-wrap"></div>
      <div class="obra-3d-loading" role="status"><canvas data-thinking-orb data-orb-state="composing" data-orb-size="48" aria-label="Carregando modelo 3D"></canvas><span>Carregando empreendimento</span></div>
      <nav class="obra-3d-toolbar" aria-label="Controles do modelo 3D" hidden>
        <button type="button" data-action="fit" aria-label="Enquadrar modelo" title="Enquadrar modelo"><i class="fa-solid fa-expand" aria-hidden="true"></i></button>
        <button type="button" data-action="in" aria-label="Aproximar modelo" title="Aproximar"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>
        <button type="button" data-action="out" aria-label="Afastar modelo" title="Afastar"><i class="fa-solid fa-minus" aria-hidden="true"></i></button>
        <a data-action="open" aria-label="Abrir visualização completa do modelo" title="Abrir visualização completa"><i class="fa-solid fa-cube" aria-hidden="true"></i></a>
        <div class="obra-3d-north" role="img"><span>N</span><i class="fa-solid fa-location-arrow" aria-hidden="true"></i></div>
      </nav>
      <p class="obra-3d-error" role="status" hidden>Não foi possível carregar o modelo 3D. Os demais recursos da obra continuam disponíveis.</p>
      <button class="obra-3d-return" type="button" aria-label="Voltar à visualização principal da obra" title="Voltar ao empreendimento" hidden></button>`;
    layer.querySelector('[data-action="open"]').href = fullUrl.href;
    slot.append(layer);
    syncLoadingPhase();
    // Reserva o espaço desde o início; o layer sai do container apenas no desktop.
    const rect = slot.getBoundingClientRect();
    Object.assign(layer.style, {
      position: "absolute",
      width: `${rect.width}px`,
      height: `${rect.height}px`,
    });
    const activeLayer = layer;
    void loadScript(
      "../assets/js/thinking-orbs.js",
      () => !!window.thinkingOrbs,
    )
      .then(() => {
        if (token === generation && activeLayer.isConnected) syncLoadingPhase();
      })
      .catch((error) => console.warn("[Obra 3D] Loading indisponível", error));
    // Norte é opcional: sua ausência não impede o carregamento do modelo.
    const spatial = api("espacial_configuracao", id, signal).catch((error) => {
      if (error.name !== "AbortError")
        console.warn(
          "[Obra 3D] Usando orientação convencional do viewer",
          error,
        );
      return null;
    });
    const [{ Obra3DViewer }, { Obra3DScroll }] = await loadDependencies();
    if (token !== generation) return;
    viewer = new Obra3DViewer(activeLayer);
    const activeViewer = viewer;
    scroll = new Obra3DScroll({
      hero,
      slot,
      layer: activeLayer,
      viewer: activeViewer,
    });
    const src = new URL("../MapaPavimento/api.php", location.href);
    src.search = new URLSearchParams({
      action: "modelo_3d_arquivo",
      obra_id: id,
      modelo_id: model.id,
    });
    await activeViewer.load(src.href);
    if (token !== generation) return;
    void spatial.then((config) => {
      if (token === generation)
        activeViewer.setSpatialConfiguration(
          config?.configuracao ?? null,
          openedAt,
        );
    });
    orbCleanup?.();
    orbCleanup = null;
    activeLayer.querySelector(".obra-3d-loading").hidden = true;
    activeLayer.querySelector(".obra-3d-toolbar").hidden = false;
    activeLayer.dataset.state = "ready";
    hero.dataset.state = "ready";
    syncLoadingPhase();
    scroll.ready();
  } catch (error) {
    if (token !== generation || error.name === "AbortError") return;
    console.error(`[Obra 3D] obra=${id}:`, error);
    finishInitialLoading();
    hero.dataset.state = "error";
    hero.setAttribute("aria-busy", "false");
    if (!layer) hero.hidden = true;
    orbCleanup?.();
    orbCleanup = null;
    scroll?.destroy();
    scroll = null;
    viewer?.destroy();
    viewer = null;
    if (layer) {
      // No erro o layer volta para a seção: nunca flutua sobre a tabela.
      slot.append(layer);
      Object.assign(layer.style, {
        position: "absolute",
        transform: "none",
        width: "100%",
        height: "100%",
      });
      layer.querySelector(".obra-3d-loading").hidden = true;
      layer.querySelector(".obra-3d-toolbar").hidden = true;
      layer.querySelector(".obra-3d-return").hidden = true;
      layer.querySelector(".obra-3d-error").hidden = false;
      layer.dataset.state = "error";
    }
  }
}

function onData(event) {
  metadata = event.detail;
  void start(metadata.obraId);
  updateInfo();
}
window.addEventListener("obra-dados-updated", onData);
function dispose() {
  ++generation;
  clear();
  finishInitialLoading();
  window.__obra3dEntry?.controller.abort();
  currentId = "";
  statsObserver.disconnect();
  window.removeEventListener("obra-dados-updated", onData);
}
// pagehide mantém compatibilidade com o back/forward cache.
window.addEventListener("pagehide", dispose);
window.addEventListener("pageshow", (event) => {
  if (event.persisted) location.reload();
});
const initialId = metadata?.obraId || window.FlowProjectContext.obraId;
if (/^\d+$/.test(String(initialId || ""))) void start(initialId);
else {
  finishInitialLoading();
  hero.hidden = true;
  hero.setAttribute("aria-busy", "false");
}
