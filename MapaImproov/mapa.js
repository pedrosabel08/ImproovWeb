import {
  filterProjects,
  featureCollection,
  restoreState,
} from "./mapa-data.mjs";
import {
  prepareCartography, attachRelief, addLocalBase, cartographicProjection, sky, IMAGERY_SOURCE, DEM_SOURCE, SHADE_SOURCE,
} from "./mapa-cartography.mjs?v=5";

const $ = (id) => document.getElementById(id);
const config = JSON.parse($("mapConfig").textContent);
const storageKey = `flow-mapa-improov:v1:${$("mapaImproov").dataset.user}`;
let restored;
try {
  restored = restoreState(sessionStorage.getItem(storageKey));
} catch {}
let projects = [],
  filtered = [],
  selected = null,
  map,
  loaded = false,
  dataController,
  disposed = false;
let projection = restored?.projection || "globe",
  terrain = true,
  relief,
  popup,
  mapDeadline,
  dataDeadline;
const filterIds = ["search", "status", "model", "location"];
const reducedMotion = matchMedia("(prefers-reduced-motion: reduce)");
const cleanupOrb = window.thinkingOrbs?.($("loading"));
const filters = () =>
  Object.fromEntries(filterIds.map((id) => [id, $(id).value]));
const duration = () => (reducedMotion.matches ? 0 : 900);
const text = (tag, value, className) => {
  const element = document.createElement(tag);
  element.textContent = value;
  if (className) element.className = className;
  return element;
};

function save() {
  if (disposed) return;
  const center = map?.getCenter();
  const camera = center
    ? {
        center: [center.wrap().lng, center.lat],
        zoom: map.getZoom(),
        bearing: map.getBearing(),
        pitch: map.getPitch(),
      }
    : restored?.camera;
  try {
    sessionStorage.setItem(
      storageKey,
      JSON.stringify({
        version: 1,
        camera,
        projection,
        selected,
        filters: filters(),
      }),
    );
  } catch {}
}

function showNotice(message = "", retry = false) {
  $("notice").hidden = !message;
  $("noticeText").textContent = message;
  $("retry").hidden = !retry;
}

function mapNotice(message = "") {
  $("mapNotice").hidden = !message;
  $("mapNoticeText").textContent = message;
}

function populateFilters() {
  const previous = filters();
  $("status").replaceChildren(new Option("Todos os status", ""));
  const statuses = new Map(
    projects.map((p) => [String(p.status_id), p.status]),
  );
  statuses.forEach((label, value) => $("status").add(new Option(label, value)));
  $("location").replaceChildren(new Option("Todas as localizações", ""));
  [...new Set(projects.map((p) => p.localizacao).filter(Boolean))]
    .sort((a, b) => a.localeCompare(b, "pt-BR"))
    .forEach((value) => $("location").add(new Option(value, value)));
  $("locationField").hidden = $("location").options.length < 2;
  const initial = restored?.filters || previous;
  filterIds.forEach((id) => {
    $(id).value = initial[id] || "";
    if ($(id).selectedIndex === -1) $(id).value = "";
  });
}

function applyFilters() {
  filtered = filterProjects(projects, filters());
  $("totalCount").textContent = filtered.length;
  const located = filtered.filter((p) => p.coordinates !== null).length;
  $("locatedCount").textContent = located;
  $("missingCount").textContent = filtered.length - located;
  $("glbCount").textContent = filtered.filter((p) => p.has_glb).length;
  $("resultCount").textContent =
    `${filtered.length} ${filtered.length === 1 ? "obra" : "obras"}`;
  const fragment = document.createDocumentFragment();
  filtered.forEach((p) => {
    const button = text("button", "", "mi-result");
    button.type = "button";
    button.dataset.obra = p.obra_id;
    button.setAttribute("aria-pressed", String(selected === p.obra_id));
    const title = text("span", "", "mi-result-title");
    title.append(
      text("strong", p.codigo || "Código não cadastrado"),
      text("span", p.has_glb ? "3D" : "", "mi-result-model"),
    );
    button.append(
      title,
      text("span", p.nome, "mi-result-name"),
      text(
        "small",
        p.coordinates
          ? p.localizacao || "Localização geográfica cadastrada"
          : "Sem localização no mapa",
      ),
    );
    button.addEventListener("click", () => selectProject(p.obra_id));
    fragment.append(button);
  });
  if (!filtered.length)
    fragment.append(
      text("p", "Nenhum empreendimento corresponde à pesquisa.", "mi-empty"),
    );
  $("results").replaceChildren(fragment);
  if (selected && !filtered.some((p) => p.obra_id === selected))
    closeDetails(false);
  if (loaded) {
    popup?.remove();
    map.getSource("obras").setData(featureCollection(filtered));
    updateSelection();
  }
  if (projects.length && !located)
    showNotice(
      filtered.length
        ? "Estas obras ainda não possuem coordenadas válidas. Consulte os detalhes pela lista."
        : "Nenhuma obra corresponde aos filtros.",
    );
  else if (!projects.length)
    showNotice("Nenhuma obra disponível para este usuário.");
  else showNotice();
  save();
}

function padding() {
  if (innerWidth <= 640)
    return {
      top: 80,
      bottom: $("detailsPanel").hidden ? 50 : Math.min(innerHeight * 0.5, 400),
      left: 20,
      right: 55,
    };
  if (innerWidth <= 1180)
    return {
      top: 70,
      bottom: 50,
      left: 30,
      right: $("detailsPanel").hidden ? 60 : 360,
    };
  return {
    top: 70,
    bottom: 60,
    left: 330,
    right: $("detailsPanel").hidden ? 70 : 370,
  };
}

function brazilZoom() {
  const p = padding();
  const area = $("map").getBoundingClientRect();
  const available = Math.max(
    160,
    Math.min(area.width - p.left - p.right, area.height - p.top - p.bottom),
  );
  return Math.max(0.75, Math.min(2.25, 2.25 + Math.log2(available / 620)));
}

function updateSelection() {
  if (!loaded) return;
  map
    .getSource("selected-location")
    .setData(featureCollection(filtered.filter((p) => p.obra_id === selected)));
  map.setFilter("selected", ["==", ["get", "obra_id"], selected || -1]);
}

function selectProject(id, fly = true, focus = true) {
  const p = filtered.find((p) => p.obra_id === Number(id));
  if (!p) return;
  selected = p.obra_id;
  $("detailsPanel").hidden = false;
  $("detailCode").textContent = p.codigo || "Código não cadastrado";
  $("detailName").textContent = p.nome;
  $("detailLocation").textContent =
    p.localizacao || "Localização descritiva não cadastrada";
  $("detailStatus").textContent = p.status;
  $("detailStatus").dataset.status = p.status_id;
  $("coverCode").textContent = p.codigo || "Código não cadastrado";
  $("detailModel").textContent = p.has_glb
    ? "GLB cadastrado"
    : "Não cadastrado";
  $("detailModel").classList.toggle("mi-available", p.has_glb);
  $("detailCoordinates").textContent = p.coordinates
    ? `${p.coordinates[1].toFixed(5)}, ${p.coordinates[0].toFixed(5)}`
    : "Não cadastrada";
  $("openViewer").toggleAttribute("href", p.can_open_3d);
  if (p.can_open_3d) $("openViewer").href = p.viewer_url;
  $("openViewer").setAttribute("aria-disabled", String(!p.can_open_3d));
  $("openViewer").tabIndex = p.can_open_3d ? 0 : -1;
  $("viewerReason").hidden = p.can_open_3d;
  $("viewerReason").textContent = p.has_glb
    ? "O visualizador 3D não está disponível para esta obra nas permissões atuais do Flow."
    : "Nenhum modelo GLB está cadastrado para esta obra.";
  $("openProject").href = `../Dashboard/obra?obra_id=${p.obra_id}`;
  document
    .querySelectorAll(".mi-result")
    .forEach((b) =>
      b.setAttribute(
        "aria-pressed",
        String(Number(b.dataset.obra) === selected),
      ),
    );
  popup?.remove();
  closeFilters();
  updateSelection();
  if (p.coordinates && loaded && fly)
    map.flyTo({
      center: p.coordinates,
      zoom: Math.max(map.getZoom(), 12),
      padding: padding(),
      duration: duration(),
      maxDuration: 1400,
    });
  if (focus) $("detailsPanel").focus({ preventScroll: true });
  save();
}

function closeDetails(focus = true) {
  const old = selected;
  selected = null;
  $("detailsPanel").hidden = true;
  updateSelection();
  if (loaded) map.easeTo({ padding: padding(), duration: duration() });
  document
    .querySelectorAll(".mi-result")
    .forEach((b) => b.setAttribute("aria-pressed", "false"));
  if (focus) {
    if (innerWidth <= 1180) $("filtersToggle").focus();
    else document.querySelector(`.mi-result[data-obra="${old}"]`)?.focus();
  }
  save();
}

function closeFilters() {
  $("filtersPanel").classList.remove("is-open");
  $("filtersToggle").setAttribute("aria-expanded", "false");
}

function chooseProjection(type) {
  projection = type;
  $("globeMode").setAttribute("aria-pressed", String(type === "globe"));
  $("flatMode").setAttribute("aria-pressed", String(type === "mercator"));
  if (loaded) map.setProjection(cartographicProjection(type));
  save();
}

function brazil(animate = true) {
  if (!loaded) return;
  map.flyTo({
    center: [-53, -14],
    zoom: brazilZoom(),
    pitch: 0,
    bearing: 0,
    padding: padding(),
    duration: animate ? duration() : 0,
  });
}

function overlapList(items, coordinates) {
  if (!items.length) return;
  const content = document.createElement("div");
  content.className = "mi-overlap";
  content.append(
    text("strong", `${items.length} empreendimentos nesta região`),
  );
  items.forEach((p) => {
    const button = text("button", `${p.codigo} · ${p.nome}`);
    button.type = "button";
    button.addEventListener("click", () => selectProject(p.obra_id));
    content.append(button);
  });
  popup?.remove();
  popup = new maplibregl.Popup({
    maxWidth: "310px",
    closeButton: true,
    focusAfterOpen: true,
  })
    .setLngLat(coordinates)
    .setDOMContent(content)
    .addTo(map);
}

function addLayers() {
  map.addSource("obras", {
    type: "geojson",
    data: featureCollection(filtered),
    cluster: true,
    clusterMaxZoom: 15,
    clusterRadius: 46,
  });
  map.addSource("selected-location", {
    type: "geojson",
    data: featureCollection(filtered.filter((p) => p.obra_id === selected)),
  });
  map.addLayer({
    id: "clusters",
    type: "circle",
    source: "obras",
    filter: ["has", "point_count"],
    paint: {
      "circle-color": "#163e49",
      "circle-stroke-color": "#60d4d4",
      "circle-stroke-width": 1.5,
      "circle-radius": ["step", ["get", "point_count"], 20, 10, 25, 50, 31],
      "circle-opacity": 0.95,
    },
  });
  map.addLayer({
    id: "cluster-count",
    type: "symbol",
    source: "obras",
    filter: ["has", "point_count"],
    layout: {
      "text-field": ["get", "point_count_abbreviated"],
      "text-font": ["Noto Sans Regular"],
      "text-size": 14,
    },
    paint: { "text-color": "#ecffff" },
  });
  const statusColor = [
    "match",
    ["get", "status_id"],
    0,
    "#58d6cc",
    2,
    "#f2c465",
    "#91a0b5",
  ];
  map.addLayer({
    id: "points",
    type: "circle",
    source: "obras",
    filter: ["!", ["has", "point_count"]],
    paint: {
      "circle-radius": 7,
      "circle-color": statusColor,
      "circle-stroke-color": "#0d242f",
      "circle-stroke-width": 2,
    },
  });
  map.addLayer({
    id: "selected",
    type: "circle",
    source: "selected-location",
    filter: ["==", ["get", "obra_id"], selected || -1],
    paint: {
      "circle-radius": 13,
      "circle-color": "transparent",
      "circle-stroke-color": "#ffd064",
      "circle-stroke-width": 3,
    },
  });
  map.on("click", "clusters", async (event) => {
    const feature = event.features[0];
    try {
      const source = map.getSource("obras");
      const zoom = await source.getClusterExpansionZoom(
        feature.properties.cluster_id,
      );
      const leaves = await source.getClusterLeaves(
        feature.properties.cluster_id,
        feature.properties.point_count,
        0,
      );
      const coordinates = leaves.map((f) => f.geometry.coordinates);
      const near = coordinates.every(
        (c) =>
          Math.abs(c[0] - coordinates[0][0]) < 0.0001 &&
          Math.abs(c[1] - coordinates[0][1]) < 0.0001,
      );
      if (near || zoom >= 15) {
        const ids = new Set(leaves.map((f) => Number(f.properties.obra_id)));
        overlapList(
          filtered.filter((p) => ids.has(p.obra_id)),
          feature.geometry.coordinates,
        );
      } else
        map.easeTo({
          center: feature.geometry.coordinates,
          zoom,
          padding: padding(),
          duration: duration(),
        });
    } catch {
      mapNotice(
        "Não foi possível abrir o agrupamento. Use a lista de empreendimentos.",
      );
    }
  });
  map.on("click", "points", (event) => {
    const features = map.queryRenderedFeatures(event.point, {
      layers: ["points"],
    });
    const ids = new Set(features.map((f) => Number(f.properties.obra_id)));
    const items = filtered.filter((p) => ids.has(p.obra_id));
    if (items.length > 1) overlapList(items, event.lngLat);
    else selectProject(items[0]?.obra_id);
  });
  ["points", "clusters"].forEach((layer) => {
    map.on("mouseenter", layer, () => {
      map.getCanvas().style.cursor = "pointer";
    });
    map.on("mouseleave", layer, () => {
      map.getCanvas().style.cursor = "";
    });
  });
}

async function startMap() {
  let localBaseNeeded = false;
  const failedSources = new Set();
  const simplifiedNotice = "Cartografia indisponível. Exibindo mapa simplificado sem detalhes de ruas. Os empreendimentos continuam disponíveis.";
  clearTimeout(mapDeadline);
  loaded = false;
  relief = null;
  terrain = true;
  $("terrainToggle").hidden = true;
  $("terrainToggle").disabled = false;
  $("terrainToggle").setAttribute("aria-pressed", "true");
  map?.remove();
  map = null;
  mapNotice();
  if (!window.maplibregl) {
    mapNotice(
      "O mapa precisa de WebGL neste navegador. A pesquisa e os detalhes continuam disponíveis.",
    );
    return;
  }
  try {
    map = new maplibregl.Map({
      container: "map",
      style: {
        version: 8,
        sources: {},
        layers: [
          {
            id: "background",
            type: "background",
            paint: { "background-color": "#071623" },
          },
        ],
      },
      center: [-53, -14],
      zoom: innerWidth <= 640 ? 1.7 : 2.25,
      maxZoom: 18,
      maxPitch: 60,
      attributionControl: false,
      renderWorldCopies: false,
      locale: {
        "NavigationControl.ZoomIn": "Aproximar",
        "NavigationControl.ZoomOut": "Afastar",
        "NavigationControl.ResetBearing": "Orientar para o Norte",
        "Map.Title": "Mapa de empreendimentos",
        "Popup.Close": "Fechar lista de empreendimentos",
      },
      transformRequest: (url) => ({ url, credentials: "omit" }),
    });
    map.addControl(
      new maplibregl.NavigationControl({ visualizePitch: true }),
      "top-right",
    );
    map.addControl(
      new maplibregl.AttributionControl({
        customAttribution: '<a href="./styles/NOTICE.md" target="_blank" rel="noopener">Fontes e licenças</a>',
      }),
      "bottom-right",
    );
    map.on("error", (event) => {
      const sourceId = event.sourceId || "style";
      const firstFailure = !failedSources.has(sourceId);
      if (firstFailure) console.warn("Mapa Improov: falha cartográfica", sourceId, event.error?.message || "");
      failedSources.add(sourceId);
      if (event.sourceId === DEM_SOURCE || event.sourceId === SHADE_SOURCE) {
        terrain = false;
        relief?.fail();
        $("terrainToggle").disabled = true;
        $("terrainToggle").setAttribute("aria-pressed", "false");
        mapNotice(
          "Relevo indisponível. O mapa-base e os empreendimentos continuam disponíveis.",
        );
      } else if (event.sourceId === IMAGERY_SOURCE) {
        // Hide the failed source once; keep the existing vector cartography.
        if (firstFailure && map.getLayer(IMAGERY_SOURCE))
          map.setLayoutProperty(IMAGERY_SOURCE, "visibility", "none");
        mapNotice("Imagens da Terra indisponíveis. A cartografia e os empreendimentos continuam disponíveis.");
      } else {
        if (event.sourceId && event.sourceId !== 'obras' && event.sourceId !== 'selected-location') {
          localBaseNeeded = true;
          if (loaded) addLocalBase(map);
        }
        mapNotice(
          "Alguns dados cartográficos estão indisponíveis. O mapa simplificado e a pesquisa permanecem disponíveis.",
        );
      }
      if (localBaseNeeded) mapNotice(simplifiedNotice);
    });
    map.on("moveend", save);
    map
      .getCanvas()
      .addEventListener("webglcontextlost", () =>
        mapNotice(
          "A renderização do mapa foi interrompida. Tente recarregar o mapa.",
        ),
      );
    let style, localStyle = false;
    try {
      const response = await fetch(config.style, {
        credentials: "omit",
        referrerPolicy: "no-referrer",
        signal: AbortSignal.timeout(15000),
      });
      if (!response.ok) throw new Error("style");
      style = await response.json();
      if (style.version !== 8 || !style.sources || !Array.isArray(style.layers)) throw new Error("style-format");
    } catch {
      // A local copy of the public base style keeps its tile sources and credits.
      const response = await fetch("./styles/dark.json", { signal: AbortSignal.timeout(5000) });
      if (!response.ok) throw new Error("style-fallback");
      style = await response.json();
      localStyle = true;
    }
    style.projection = cartographicProjection(projection);
    prepareCartography(style, localStyle ? { ...config, style: 'https://tiles.openfreemap.org/styles/dark' } : config);
    map.once("style.load", () => {
      if (disposed) return;
      clearTimeout(mapDeadline);
      map.setProjection(cartographicProjection(projection));
      map.setSky(sky);
      addLayers();
      loaded = true;
      if (localBaseNeeded) addLocalBase(map);
      if (config.dem) {
        relief = attachRelief(map, config);
        $("terrainToggle").hidden = false;
        $("terrainToggle").setAttribute("aria-pressed", "true");
        $("terrainToggle").title = "Relevo progressivo na aproximação regional";
      }
      if (restored?.camera)
        map.jumpTo({ ...restored.camera, padding: padding() });
      else brazil();
      mapNotice(localBaseNeeded ? simplifiedNotice : "");
      updateSelection();
    });
    map.setStyle(style, { diff: false });
    mapDeadline = setTimeout(() => {
      if (!loaded)
        mapNotice(
          "O serviço cartográfico demorou para responder. Use a lista ou tente novamente.",
        );
    }, 20000);
  } catch {
    mapNotice(
      "Não foi possível iniciar o mapa. Verifique a conexão e o suporte a WebGL. A lista continua disponível.",
    );
  }
}

async function loadProjects() {
  dataController?.abort();
  dataController = new AbortController();
  clearTimeout(dataDeadline);
  dataDeadline = setTimeout(() => dataController.abort(), 20000);
  $("loading").hidden = false;
  showNotice();
  try {
    const response = await fetch("./api.php", {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
      signal: dataController.signal,
    });
    if (response.status === 401)
      throw new Error("Sua sessão expirou. Entre novamente no Flow.");
    const data = await response.json();
    if (!response.ok || !data.sucesso)
      throw new Error(
        data.erro || "Não foi possível consultar os empreendimentos.",
      );
    projects = data.obras;
    populateFilters();
    applyFilters();
    if (restored?.selected) selectProject(restored.selected, false, false);
    restored = restored ? { ...restored, filters: null, selected: null } : null;
  } catch (error) {
    if (!disposed) {
      $("resultCount").textContent = "Consulta indisponível";
      showNotice(
        error.name === "AbortError"
          ? "A consulta demorou para responder. Tente novamente."
          : error.message,
        true,
      );
    }
  } finally {
    clearTimeout(dataDeadline);
    $("loading").hidden = true;
  }
}

filterIds.forEach((id) =>
  $(id).addEventListener(id === "search" ? "input" : "change", applyFilters),
);
$("clearFilters").addEventListener("click", () => {
  filterIds.forEach((id) => {
    $(id).value = "";
  });
  applyFilters();
});
$("closeDetails").addEventListener("click", () => closeDetails());
$("filtersToggle").addEventListener("click", () => {
  const open = !$("filtersPanel").classList.contains("is-open");
  $("filtersPanel").classList.toggle("is-open", open);
  $("filtersToggle").setAttribute("aria-expanded", String(open));
  if (open) {
    closeDetails(false);
    $("search").focus();
  }
});
$("closeFilters").addEventListener("click", () => {
  closeFilters();
  $("filtersToggle").focus();
});
$("globeMode").addEventListener("click", () => chooseProjection("globe"));
$("flatMode").addEventListener("click", () => chooseProjection("mercator"));
$("resetBrazil").addEventListener("click", brazil);
$("terrainToggle").addEventListener("click", () => {
  if (!loaded || !config.dem) return;
  terrain = !terrain;
  relief?.setEnabled(terrain);
  $("terrainToggle").setAttribute("aria-pressed", String(terrain));
});
$("themeToggle").addEventListener("click", () => {
  const theme =
    document.documentElement.dataset.theme === "dark" ? "light" : "dark";
  document.documentElement.dataset.theme = theme;
  try {
    localStorage.setItem("flow-mapa-theme", theme);
  } catch {}
});
$("retry").addEventListener("click", loadProjects);
$("retryMap").addEventListener("click", () => {
  save();
  try {
    restored = restoreState(sessionStorage.getItem(storageKey));
  } catch {}
  startMap();
});
document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") {
    closeFilters();
    closeDetails();
  }
});
window.addEventListener("resize", () => {
  if (!loaded) return;
  map.resize();
  map.jumpTo({
    padding: padding(),
    ...(map.getZoom() < 4 ? { zoom: brazilZoom() } : {}),
  });
});
window.addEventListener("pagehide", (event) => {
  save();
  if (event.persisted) return;
  disposed = true;
  clearTimeout(mapDeadline);
  clearTimeout(dataDeadline);
  dataController?.abort();
  popup?.remove();
  cleanupOrb?.();
  map?.remove();
});
window.addEventListener("pageshow", (event) => {
  if (event.persisted) {
    map?.resize();
    loadProjects();
  }
});
document
  .querySelectorAll('.sidebar a[title="Mapa Improov"]')
  .forEach((link) => link.setAttribute("aria-current", "page"));
chooseProjection(projection);
startMap();
loadProjects();
