import * as THREE from "three";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";
import { GLTFLoader } from "three/addons/loaders/GLTFLoader.js";
import { DRACOLoader } from "three/addons/loaders/DRACOLoader.js";
import { EmpreendimentoMapBridge } from "./empreendimento-map-bridge.js?v=20261016-deferred-floor";
import { NorthIndicator } from "./north-indicator.js?v=20261016-model-compass";
import { SolarLighting } from "./solar-lighting.js?v=20261016-projection-off";
import { solarOpaqueMaterial } from "./solar-ray-tracer.js?v=20261016-alpha-glass";
import { ModelMeasurement } from "./model-measurement.js";
import { visibleSurface } from "./measurement-geometry.js";
import { floorOrbitPose } from "./floor-navigation.js";
import {
  FloorVisibility,
  visibleModelBounds,
} from "./floor-visibility.js?v=20261016-shadow-visibility-2";
import { setupEnvironment } from "./viewer-environment.js?v=20261016-orthographic-background";
import { fitSolarShadow } from "./solar-shadow.js";

const themeStyle = getComputedStyle(document.documentElement);
const highlightPalette = {
  selected: new THREE.Color(
    themeStyle.getPropertyValue("--spatial-accent").trim() || "#74a9e8",
  ),
  hover: new THREE.Color(
    themeStyle.getPropertyValue("--spatial-highlight-hover").trim() ||
      "#a8c7e8",
  ),
};

(document.getElementById("flowSpatialWorkspace") || document.body).innerHTML = `
<style>
  :root{font-family:var(--spatial-font);color:var(--spatial-text);background:var(--spatial-bg);color-scheme:dark;--muted:var(--spatial-text-muted);--line:var(--spatial-border);--panel:var(--spatial-panel);--accent:var(--spatial-accent);--blue:#94bfff}
  *{box-sizing:border-box}body{margin:0;min-height:100vh}button,input{font:inherit}.app{height:100vh;min-height:560px;display:grid;grid-template-rows:62px 1fr}.top{display:flex;align-items:center;gap:14px;padding:0 18px;border-bottom:1px solid var(--line);background:#14171d}.brand{display:flex;align-items:center;gap:10px;min-width:195px}.brand strong{font-size:13px}.brand small{display:block;color:var(--muted);font-size:10px;margin-top:2px}.load{display:flex;gap:7px;align-items:center;flex:1;min-width:0}.load input{max-width:440px;flex:1;min-width:100px;background:#0f1217;border:1px solid var(--line);border-radius:7px;color:#dfe7f1;padding:9px 10px;font-size:12px}.status{font-size:10px;color:var(--muted);white-space:nowrap}.layout{display:grid;grid-template-columns:290px minmax(350px,1fr) 290px;min-height:0}.side{background:var(--panel);min-height:0;overflow:auto}.left{border-right:1px solid var(--line)}.right{border-left:1px solid var(--line)}.head{height:48px;padding:0 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--line);position:sticky;top:0;background:var(--panel);z-index:2}.head h2{margin:0;color:#aeb8c6;font-size:10px;text-transform:uppercase;letter-spacing:.09em}.count{font:10px ui-monospace,monospace;color:var(--muted)}.tree{padding:10px 8px 24px}.row{display:flex;align-items:center;gap:7px;min-height:32px;padding:5px 8px;border-radius:5px;font-size:12px;white-space:nowrap;overflow:hidden;color:#cbd3df;cursor:pointer}.row:hover{background:#222832}.row.selected{background:#283e38;color:#b8f1d4}.row.floor{font-weight:700;color:#dfe7ef}.row.floor.active{background:#233b34;color:var(--accent);cursor:default}.row.env{padding-left:25px}.row.back{color:var(--accent);border-bottom:1px solid var(--line);border-radius:0;margin-bottom:7px}.twisty{width:12px;color:#7d8b9a;font-size:10px;text-align:center}.ico{width:14px;color:var(--blue);text-align:center}.label{overflow:hidden;text-overflow:ellipsis}.viewport{position:relative;min-width:0;min-height:0;overflow:hidden;background:radial-gradient(ellipse at 50% 42%,#252d38 0%,#171b22 53%,#111419 100%)}canvas.gl{display:block;width:100%;height:100%}.viewtools{position:absolute;top:12px;right:12px;display:flex;gap:6px}.hint{position:absolute;bottom:12px;left:50%;transform:translateX(-50%);white-space:nowrap}.drop{position:absolute;inset:10px;border:1px dashed var(--accent);border-radius:10px;background:#183126aa;display:none;place-items:center;color:#d1ffe7;pointer-events:none}.viewport.drag .drop{display:grid}.summary{padding:12px;border-bottom:1px solid var(--line)}.stats{display:grid;grid-template-columns:1fr 1fr;gap:7px}.stat{background:#1e242c;border:1px solid #2b333e;border-radius:6px;padding:8px}.stat strong{display:block;font:600 16px ui-monospace,monospace}.stat small{display:block;color:var(--muted);font-size:9px;margin-top:3px}.details{padding:13px}.placeholder{font-size:11px;color:var(--muted);line-height:1.6}.detail-title{font-size:14px;font-weight:650;overflow-wrap:anywhere}.detail-type{font:10px ui-monospace,monospace;color:var(--accent);margin:5px 0 14px}.yaml{padding:11px;background:#11151a;border:1px solid #29303b;border-radius:6px;font:11px/1.8 ui-monospace,monospace;white-space:pre-wrap;color:#dce4ed}.section{font-size:9px;text-transform:uppercase;letter-spacing:.1em;color:#8390a0;margin:16px 0 6px}.kv{display:flex;justify-content:space-between;gap:8px;border-bottom:1px solid #262d36;padding:7px 0;font-size:10px}.kv span:first-child{color:#96a1af}.kv span:last-child{text-align:right;color:#dce4ed;font-family:ui-monospace,monospace;overflow-wrap:anywhere}.empty-tree{padding:12px;color:var(--muted);font-size:11px;line-height:1.6}
  .floor-switcher{position:absolute;top:12px;left:50%;transform:translateX(-50%);display:flex;gap:4px;padding:4px;border:1px solid #3a4350;border-radius:9px;background:#141a22ed;z-index:3;max-width:calc(100% - 24px);overflow:auto}.floor-switcher button{border:1px solid transparent;background:transparent;color:#a9b6c6;border-radius:5px;padding:7px 12px;font:600 11px ui-monospace,monospace;cursor:pointer}.floor-switcher button:hover{background:#29343e;color:#e8edf4}.floor-switcher button[aria-pressed="true"]{background:#a1edca;color:#13241c}.hover-tooltip{position:absolute;z-index:10;pointer-events:none;max-width:240px;padding:10px 13px;border:1px solid #496259;border-radius:8px;background:#101916f2;box-shadow:0 5px 20px #0005;color:#e8f7ef}.hover-tooltip strong{display:block;font-size:12px;font-weight:650}.hover-tooltip span{display:block;font:12px ui-monospace,monospace;color:#b7e7d1;margin-top:5px}.hover-tooltip small{display:block;font-size:9px;color:#90a49a;margin-top:5px}.floor-switcher[hidden],.hover-tooltip[hidden]{display:none}.row:not(.back){cursor:default}
  @media(max-width:1200px){.viewtools{top:58px}}
  @media(max-width:980px){.layout{grid-template-columns:200px minmax(240px,1fr) 220px}.brand{min-width:155px}.status{display:none}}
  @media(max-width:650px){.app{height:100dvh;grid-template-rows:auto 1fr}.top{min-height:94px;flex-wrap:wrap;padding:9px 11px;gap:7px}.brand{flex:1}.load{order:2;flex-basis:100%}.load input{max-width:none}.layout{grid-template-columns:1fr;grid-template-rows:auto minmax(360px,60vh) auto;overflow:auto}.left{border:0;border-bottom:1px solid var(--line);max-height:160px}.tree{display:flex;overflow:auto;gap:4px;padding:8px}.row{min-width:max-content}.row.env{padding-left:8px}.right{width:auto;border-left:0;border-top:1px solid var(--line)}.hint{font-size:8px;white-space:normal;text-align:center;width:max-content;max-width:90%}}
</style>
<div class="app spatial-workspace"><header class="top spatial-header"><div class="brand spatial-brand"><div class="mark spatial-mark">3D</div><div><strong id="projectName">Empreendimento</strong><small id="modeLabel">Selecione um pavimento</small></div></div>
<button class="btn" id="backToProject" hidden>Voltar ao empreendimento</button><div class="load"><input id="modelUrl" value="./PAVTO_TIPO_01.glb" aria-label="Caminho do arquivo GLB"><button class="btn primary" id="loadUrl">Carregar</button><button class="btn" id="chooseFile">Abrir arquivo…</button><input id="modelFile" type="file" accept=".glb,.gltf,model/gltf-binary,model/gltf+json" hidden></div><div class="status" id="status">Carregando…</div></header>
<main class="layout"><aside class="side left spatial-inspector"><div class="head spatial-inspector-head"><h2 id="treeTitle">Pavimentos</h2><span class="count" id="treeCount">—</span></div><div class="tree" id="tree"><div class="empty-tree">Carregando empreendimento…</div></div></aside>
<section class="viewport spatial-viewport" id="viewport"><div class="drop">Solte o arquivo GLB/GLTF aqui</div><nav class="floor-switcher" id="floorSwitcher" aria-label="Trocar pavimento" hidden></nav><div class="viewtools spatial-toolbar"><button id="fit">Enquadrar</button><button id="axes">Eixos: ligados</button><button id="grid">Grade: ligada</button></div><div class="hover-tooltip" id="hoverTooltip" role="tooltip" hidden><strong id="hoverName"></strong><span id="hoverDimensions"></span><small>Dimensões aproximadas</small></div><div class="hint spatial-hint" id="hint">Clique em um pavimento para entrar na planta de topo</div></section>
<aside class="side right spatial-inspector"><div class="head spatial-inspector-head"><h2>Inspeção</h2><span class="count" id="selectedType">—</span></div><div class="summary"><div class="stats" id="stats"></div></div><div class="details" id="details"><div class="placeholder">Selecione um objeto da cena para inspecionar.</div></div></aside></main></div>`;

const $ = (id) => document.getElementById(id);
const viewport = $("viewport");
$("hint").textContent =
  "Clique em um pavimento para aproximar e explorar em 3D";
const floorPopover = document.createElement("section");
floorPopover.id = "floorPopover";
floorPopover.className = "floor-popover link-summary";
floorPopover.setAttribute("aria-label", "Pavimento selecionado");
floorPopover.hidden = true;
floorPopover.innerHTML =
  '<div id="floorPopoverContent" aria-live="polite"></div><button class="btn" id="resetBuildingView">Voltar ao empreendimento</button>';
viewport.append(floorPopover);
$("resetBuildingView").addEventListener("click", () => resetBuildingView());
for (const [side, label] of [
  ["left", "Pavimentos"],
  ["right", "Hub de Inspeção"],
]) {
  const sidebar = document.querySelector(`.side.${side}`);
  sidebar.id = `${side}Sidebar`;
  const button = document.createElement("button");
  button.className = "btn sidebar-toggle";
  button.type = "button";
  button.setAttribute("aria-controls", sidebar.id);
  button.setAttribute("aria-expanded", "true");
  button.setAttribute("aria-label", `Recolher ${label}`);
  button.title = `Recolher ${label}`;
  button.textContent = side === "left" ? "‹" : "›";
  button.addEventListener("click", () => toggleSidebar(side, label));
  sidebar.querySelector(".head").append(button);
}
function toggleSidebar(side, label, desired = null) {
  const sidebar = document.querySelector(`.side.${side}`);
  const collapsed = desired ?? !sidebar.classList.contains("is-collapsed");
  sidebar.classList.toggle("is-collapsed", collapsed);
  document
    .querySelector(".layout")
    .classList.toggle(`${side}-collapsed`, collapsed);
  const button = sidebar.querySelector(".sidebar-toggle");
  button.setAttribute("aria-expanded", String(!collapsed));
  button.setAttribute(
    "aria-label",
    `${collapsed ? "Restaurar" : "Recolher"} ${label}`,
  );
  button.title = button.getAttribute("aria-label");
  button.textContent = (side === "left") !== collapsed ? "‹" : "›";
  // ResizeObserver acompanha também as dimensões intermediárias da transição.
}
const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
renderer.setPixelRatio(Math.min(devicePixelRatio || 1, 2));
renderer.outputColorSpace = THREE.SRGBColorSpace;
renderer.shadowMap.enabled = true;
renderer.shadowMap.type = THREE.PCFSoftShadowMap;
renderer.toneMapping = THREE.ACESFilmicToneMapping;
renderer.localClippingEnabled = true;
renderer.domElement.className = "gl";
viewport.prepend(renderer.domElement);
const scene = new THREE.Scene();
scene.background = null;
const viewerEnvironment = setupEnvironment(renderer, scene);
const camera = new THREE.OrthographicCamera(-10, 10, 10, -10, 0.01, 100000);
camera.position.set(10, 9, 12);
const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
controls.zoomToCursor = false;
// Mantém a órbita acima do horizonte, sem revelar a face inferior do terreno.
controls.maxPolarAngle = Math.PI / 2 - 0.01;
const ambient = new THREE.HemisphereLight(0xe9f1ff, 0x7b827b, 1.65);
scene.add(ambient);
const light = new THREE.DirectionalLight(0xfff6e8, 2.25);
light.castShadow = true;
light.shadow.mapSize.set(2048, 2048);
light.shadow.bias = -0.0001;
light.shadow.radius = 2;
scene.add(light, light.target);
const grid = new THREE.GridHelper(10, 20, 0x536171, 0x343d49);
grid.material.transparent = true;
grid.material.opacity = 0.45;
scene.add(grid);
const axes = new THREE.AxesHelper(1.5);
scene.add(axes);
axes.visible = grid.visible = false;
$("axes").textContent = "Eixos: desligados";
$("grid").textContent = "Grade: desligada";
$("axes").setAttribute("aria-pressed", "false");
$("grid").setAttribute("aria-pressed", "false");
const dracoLoader = new DRACOLoader();
dracoLoader.setDecoderPath(
  "https://www.gstatic.com/draco/versioned/decoders/1.5.7/",
);
const loader = new GLTFLoader().setDRACOLoader(dracoLoader),
  raycaster = new THREE.Raycaster(),
  pointer = new THREE.Vector2();
const floorObjectPattern = /^(PAVTO_.+_[0-9]+)_([0-9]+)$/i;
// Only meshes with a recognized floor identifier use the picking layer.
const PICKING_LAYER = 1;
raycaster.layers.set(PICKING_LAYER);
let modelRoot = null,
  floors = [],
  allRecords = [],
  activeFloor = null;
let minimumCameraY = null;
let floorVisibility = null;
let allFloorMeshes = [],
  modelMeshes = [],
  activeFloorMeshes = [],
  activeContextMeshes = [];
let recordByObject = new Map(),
  unassignedContextMeshes = [];
let contextMaterials = new Map();
let originalVisibility = new Map(),
  selected = null,
  dragStart = null,
  currentObjectUrl = null;
const viewerState = {
  mode: "building",
  selectedFloor: null,
  hoveredFloor: null,
  sidebarBeforeFloor: null,
};
let hoveredRecord = null,
  highlightMaterials = new Map(),
  highlightHelpers = [],
  transitioning = false,
  transitionToken = 0,
  fullCameraPose = null;
let mapBridge = null,
  mapVisible = false,
  fullControlState = null;
let northDefining = false,
  northPreviousControls = true;
const northIndicator = new NorthIndicator(
  scene,
  camera,
  viewport,
  () => modelRoot,
);
const solarLighting = new SolarLighting({
  scene,
  light,
  ambient,
  viewport,
  getModel: () => modelRoot,
  onRestore: updateNavigationLight,
  onConfigure: () => mapBridge?.spatial.open(),
  onCompassChange: (value) => northIndicator.setEnvironmentVisible(value),
});
const dimensionFormat = new Intl.NumberFormat("pt-BR", {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});
let measurementUI = null;
const measurements = new ModelMeasurement({
  scene,
  camera,
  viewport,
  pick: pickMeasurement,
  onModeChange(active) {
    if (active && !measurementUI)
      measurementUI = {
        hint: $("hint").textContent,
        mode: $("modeLabel").textContent,
      };
    document.querySelector(".app").classList.toggle("measuring", active);
    if (active && matchMedia("(max-width: 600px)").matches)
      document.querySelector(".layout").scrollTop = 0;
    $("tree").inert = $("floorSwitcher").inert = active;
    $("backToProject").disabled = active || !!mapBridge?.busy;
    dragStart = null;
    hideTooltip();
    viewerState.hoveredFloor = hoveredRecord = null;
    if (active) {
      clearSelection();
      $("hint").textContent =
        "Medir · clique em dois pontos do modelo. Arraste para girar; use a roda para ampliar.";
      $("modeLabel").textContent = "Medição · 1 unidade = 1 metro";
    } else if (measurementUI) {
      $("hint").textContent = measurementUI.hint;
      $("modeLabel").textContent = measurementUI.mode;
      measurementUI = null;
    }
    refreshHighlights();
  },
});

function status(text, error = false) {
  $("status").textContent = text;
  $("status").style.color = error ? "#ff9b9b" : "";
}
function parseFloorObjectName(name) {
  const match = floorObjectPattern.exec((name || "").trim());
  return match
    ? { floor: match[1].toUpperCase(), objectNumber: match[2] }
    : null;
}
function buildIndex(root, sourceName = "") {
  const byFloor = new Map(),
    records = [],
    floorIds = new Set();
  let hasObjectNames = false;
  root.traverse((object) => {
    const parsed = parseFloorObjectName(object.name);
    if (parsed && object.isMesh) {
      floorIds.add(parsed.floor);
      hasObjectNames = true;
    } else if (/^PAVTO_.+_[0-9]{1,3}$/i.test(object.name || ""))
      floorIds.add(object.name.trim().toUpperCase());
  });
  const addRecord = (object, floorId, objectNumber = "") => {
    if (!byFloor.has(floorId))
      byFloor.set(floorId, {
        id: floorId,
        records: [],
        contextMeshes: [],
        bounds: new THREE.Box3(),
      });
    const record = { object, floor: floorId, objectNumber, name: object.name };
    const floor = byFloor.get(floorId);
    floor.records.push(record);
    floor.bounds.union(new THREE.Box3().setFromObject(object, true));
    records.push(record);
  };
  root.traverse((object) => {
    if (!object.isMesh) return;
    const parsed = parseFloorObjectName(object.name);
    if (parsed) {
      addRecord(object, parsed.floor, parsed.objectNumber);
      return;
    }
    const ownName = (object.name || "").trim().toUpperCase();
    if (floorIds.has(ownName)) {
      addRecord(object, ownName);
      return;
    }
    for (
      let ancestor = object.parent;
      ancestor && ancestor !== root;
      ancestor = ancestor.parent
    ) {
      const ancestorName = (ancestor.name || "").trim().toUpperCase();
      if (floorIds.has(ancestorName)) {
        addRecord(object, ancestorName);
        break;
      }
    }
  });
  // A standalone floor file can use its filename as the identifier while its meshes
  // are being renamed to the shared-prefix convention for a whole-building GLB.
  if (!hasObjectNames) {
    const fileName =
      sourceName
        .split("?")[0]
        .replace(/\\/g, "/")
        .split("/")
        .pop()
        ?.replace(/\.(glb|gltf)$/i, "")
        .trim()
        .toUpperCase() || "";
    if (/^PAVTO_[A-Z0-9_]+$/.test(fileName)) {
      byFloor.clear();
      records.length = 0;
      root.traverse((object) => {
        if (object.isMesh) addRecord(object, fileName);
      });
    }
  }
  const floors = [...byFloor.values()].sort(
    (a, b) =>
      a.bounds.min.y - b.bounds.min.y ||
      a.id.localeCompare(b.id, "pt-BR", { numeric: true }),
  );
  return { floors, records };
}
function floorLabel(id) {
  return id.replace(/_/g, " ");
}
function contextIndex(root) {
  const descendantFloors = new Map();
  allRecords.forEach((record) => {
    for (
      let ancestor = record.object.parent;
      ancestor;
      ancestor = ancestor.parent
    ) {
      if (!descendantFloors.has(ancestor))
        descendantFloors.set(ancestor, new Set());
      descendantFloors.get(ancestor).add(record.floor);
      if (ancestor === root) break;
    }
  });
  const byHeight = [...floors].sort((a, b) => a.bounds.min.y - b.bounds.min.y);
  byHeight.forEach((floor, i) => {
    const next = byHeight[i + 1];
    floor.minY = floor.bounds.min.y - 0.15;
    floor.maxY =
      (next
        ? Math.min(floor.bounds.max.y, next.bounds.min.y)
        : floor.bounds.max.y) + 0.01;
  });
  unassignedContextMeshes = [];
  root.traverse((object) => {
    if (!object.isMesh || recordByObject.has(object)) return;
    object.layers.disable(PICKING_LAYER);
    let assigned = [],
      explicitFloor = false;
    for (
      let ancestor = object;
      ancestor && ancestor !== root;
      ancestor = ancestor.parent
    ) {
      const floorId = (ancestor.name || "").trim().toUpperCase();
      if (floors.some((floor) => floor.id === floorId)) {
        explicitFloor = true;
        assigned = floors.filter((floor) => floor.id === floorId);
        break;
      }
      const ids = descendantFloors.get(ancestor);
      if (ids?.size === 1) {
        assigned = floors.filter((floor) => ids.has(floor.id));
        break;
      }
    }
    if (!assigned.length && !explicitFloor) {
      const bounds = new THREE.Box3().setFromObject(object, true);
      const centerY = (bounds.min.y + bounds.max.y) / 2;
      const thin = bounds.max.y - bounds.min.y < 0.25;
      const nearestLevel = thin
        ? [...byHeight].sort(
            (a, b) =>
              Math.abs(centerY - a.bounds.min.y) -
              Math.abs(centerY - b.bounds.min.y),
          )[0]
        : null;
      const atLevel =
        nearestLevel && Math.abs(centerY - nearestLevel.bounds.min.y) <= 0.25;
      assigned = byHeight.filter((floor, i) => {
        const nextBase = byHeight[i + 1]?.bounds.min.y ?? Infinity;
        const horizontal =
          bounds.max.x >= floor.bounds.min.x &&
          bounds.min.x <= floor.bounds.max.x &&
          bounds.max.z >= floor.bounds.min.z &&
          bounds.min.z <= floor.bounds.max.z;
        const vertical = thin
          ? atLevel
            ? floor === nearestLevel
            : centerY >= floor.minY &&
              centerY < nextBase - 0.001 &&
              centerY <= floor.maxY
          : bounds.max.y > floor.bounds.min.y + 0.001 &&
            bounds.min.y < floor.maxY - 0.01;
        return horizontal && vertical;
      });
    }
    assigned.forEach((floor) => floor.contextMeshes.push(object));
    if (!assigned.length) unassignedContextMeshes.push(object);
  });
}
function restoreContextMaterials() {
  for (const [mesh, original] of contextMaterials) {
    const clones = Array.isArray(mesh.material)
      ? mesh.material
      : [mesh.material];
    mesh.material = original;
    clones.forEach((material) => material.dispose());
  }
  contextMaterials.clear();
}
function clipFloorContext(floor) {
  // Shared architectural meshes may span multiple storeys. Clip them only at runtime.
  const planes = [
    new THREE.Plane(new THREE.Vector3(0, 1, 0), -floor.minY),
    new THREE.Plane(new THREE.Vector3(0, -1, 0), floor.maxY),
  ];
  activeContextMeshes.forEach((mesh) => {
    const original = mesh.material,
      materials = Array.isArray(original) ? original : [original];
    contextMaterials.set(mesh, original);
    const clones = materials.map((material) => {
      const clone = material.clone();
      if (
        material.userData?.viewerGlass ||
        (material.isMeshPhysicalMaterial && material.transmission > 0)
      )
        return clone;
      clone.clippingPlanes = [...(material.clippingPlanes || []), ...planes];
      clone.clipIntersection = false;
      clone.clipShadows = true;
      return clone;
    });
    mesh.material = Array.isArray(original) ? clones : clones[0];
  });
}
function prettyName(name) {
  return name
    .replace(/_/g, " ")
    .toLocaleLowerCase("pt-BR")
    .replace(/^\p{L}/u, (char) => char.toLocaleUpperCase("pt-BR"));
}
function hideTooltip() {
  $("hoverTooltip").hidden = true;
}
function showTooltip(record, event) {
  if (!activeFloor || !record) {
    hideTooltip();
    return;
  }
  const tooltip = $("hoverTooltip"),
    bounds = new THREE.Box3().setFromObject(record.object, true),
    size = bounds.getSize(new THREE.Vector3());
  $("hoverName").textContent = record.object.name;
  $("hoverDimensions").textContent =
    `${dimensionFormat.format(size.x)} × ${dimensionFormat.format(size.z)} m`;
  tooltip.hidden = false;
  const rect = viewport.getBoundingClientRect();
  // Keep the preferred cursor offset, clamping only when an edge would clip the tooltip.
  const x = Math.max(
    8,
    Math.min(
      event.clientX - rect.left + 14,
      rect.width - tooltip.offsetWidth - 8,
    ),
  );
  const y = Math.max(
    8,
    Math.min(
      event.clientY - rect.top + 14,
      rect.height - tooltip.offsetHeight - 8,
    ),
  );
  tooltip.style.left = `${x}px`;
  tooltip.style.top = `${y}px`;
}
function renderFloorSwitcher() {
  const nav = $("floorSwitcher");
  nav.replaceChildren();
  nav.hidden = !activeFloor;
  if (!activeFloor) return;
  floors.forEach((floor) => {
    const button = document.createElement("button");
    button.type = "button";
    button.textContent = floorLabel(floor.id);
    button.setAttribute("aria-label", `Pavimento ${floorLabel(floor.id)}`);
    button.setAttribute("aria-pressed", String(floor.id === activeFloor.id));
    button.addEventListener("click", () => activateFloor(floor));
    nav.append(button);
  });
}
function clearVisualHighlights() {
  for (const [object, material] of highlightMaterials) {
    const clones = Array.isArray(object.material)
      ? object.material
      : [object.material];
    object.material = material;
    clones.forEach((clone) => {
      if (
        clone !== material &&
        (!Array.isArray(material) || !material.includes(clone))
      )
        clone.dispose();
    });
  }
  highlightMaterials.clear();
  highlightHelpers.forEach((helper) => {
    scene.remove(helper);
    helper.geometry.dispose();
    helper.material.dispose();
  });
  highlightHelpers = [];
}
function refreshHighlights() {
  clearVisualHighlights();
  const targets = new Map();
  if (activeFloor) {
    if (selected)
      targets.set(selected.object, {
        record: selected,
        color: highlightPalette.selected,
      });
    const hovered =
      hoveredRecord?.floor === activeFloor.id ? hoveredRecord : null;
    if (hovered && hovered !== selected)
      targets.set(hovered.object, {
        record: hovered,
        color: highlightPalette.hover,
      });
  } else if (viewerState.hoveredFloor || viewerState.selectedFloor) {
    const highlightedFloors = new Set([
      viewerState.selectedFloor?.id,
      viewerState.hoveredFloor?.id,
    ]);
    allRecords
      .filter(
        (record) =>
          highlightedFloors.has(record.floor) &&
          record.object.visible &&
          wasVisible(record.object),
      )
      .forEach((record) =>
        targets.set(record.object, {
          record,
          color:
            record.floor === viewerState.selectedFloor?.id
              ? highlightPalette.selected
              : highlightPalette.hover,
        }),
      );
  }
  targets.forEach(({ record, color }) => {
    const object = record.object,
      original = object.material;
    highlightMaterials.set(object, original);
    const materials = Array.isArray(original) ? original : [original];
    const clones = materials.map((material) => {
      const clone = material.clone();
      if (
        material.userData?.viewerGlass ||
        (material.isMeshPhysicalMaterial && material.transmission > 0)
      )
        return clone;
      if (clone.color) clone.color.lerp(new THREE.Color(color), 0.35);
      if (clone.emissive) {
        clone.emissive.set(color);
        clone.emissiveIntensity = 0.16;
      }
      return clone;
    });
    object.material = Array.isArray(original) ? clones : clones[0];
    if (!activeFloor) return;
    const helper = new THREE.BoxHelper(object, color);
    helper.material.depthTest = false;
    helper.material.depthWrite = false;
    helper.castShadow = helper.receiveShadow = false;
    helper.material.transparent = true;
    helper.material.opacity = 0.9;
    helper.renderOrder = 999;
    scene.add(helper);
    highlightHelpers.push(helper);
  });
  if (!activeFloor && viewerState.selectedFloor) {
    const helper = new THREE.Box3Helper(
      viewerState.selectedFloor.bounds,
      highlightPalette.selected,
    );
    helper.material.depthTest = false;
    helper.material.depthWrite = false;
    helper.castShadow = helper.receiveShadow = false;
    helper.material.transparent = true;
    helper.material.opacity = 0.75;
    helper.renderOrder = 999;
    scene.add(helper);
    highlightHelpers.push(helper);
  }
}
function clearSelection() {
  selected = null;
  clearVisualHighlights();
}
function selectRecord(record) {
  if (measurements.active) return;
  if (!activeFloor || !record || record.floor !== activeFloor.id) return;
  selected = record;
  hoveredRecord = record;
  refreshHighlights();
  renderTree();
  showDetails(record);
}
function showDetails(record) {
  $("selectedType").textContent = record.object.type;
  const d = $("details");
  d.replaceChildren();
  const name = document.createElement("div");
  name.className = "detail-title";
  name.textContent = record.object.name;
  d.append(name);
  const type = document.createElement("div");
  type.className = "detail-type";
  type.textContent = `${record.object.type} · ${record.object.uuid}`;
  d.append(type);
  const yaml = document.createElement("pre");
  yaml.className = "yaml";
  yaml.textContent = `Pavimento: ${record.floor}\nObjeto: ${record.objectNumber || record.object.name}\nMesh: ${record.object.name}`;
  d.append(yaml);
  const section = document.createElement("div");
  section.className = "section";
  section.textContent = "Mesh selecionado";
  d.append(section);
  const kv = (label, value) => {
    const row = document.createElement("div");
    row.className = "kv";
    const a = document.createElement("span"),
      b = document.createElement("span");
    a.textContent = label;
    b.textContent = value;
    row.append(a, b);
    d.append(row);
  };
  kv(
    "Vértices",
    String(record.object.geometry?.attributes?.position?.count ?? "—"),
  );
  kv("Pai no GLB", record.object.parent?.name || "(sem nome)");
}
function makeRow(label, cls, icon, onClick, uuid) {
  const row = document.createElement("div");
  row.className = `row ${cls}`;
  if (uuid) row.dataset.meshUuid = uuid;
  row.innerHTML = `<span class="twisty"></span><span class="ico">${icon}</span>`;
  const text = document.createElement("span");
  text.className = "label";
  text.textContent = label;
  row.append(text);
  if (onClick) {
    row.setAttribute("role", "button");
    row.tabIndex = 0;
    row.addEventListener("click", onClick);
    row.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        onClick();
      }
    });
  }
  return row;
}
function renderTree() {
  const tree = $("tree");
  tree.replaceChildren();
  if (!activeFloor) {
    $("treeTitle").textContent = "Pavimentos";
    $("treeCount").textContent = `${floors.length} pav.`;
    floors.forEach((floor) => {
      const link = mapBridge?.links.get(floor.id);
      const row = makeRow(
        `${floorLabel(floor.id)} · ${link?.subtipo_nome || floor.records.length + " objetos"}`,
        "floor",
        "▱",
        () => activateFloor(floor),
      );
      row.querySelector(".twisty").textContent = "›";
      row.classList.toggle(
        "active",
        viewerState.selectedFloor?.id === floor.id,
      );
      row.setAttribute(
        "aria-pressed",
        String(viewerState.selectedFloor?.id === floor.id),
      );
      const hover = () => hoverFloor(floor);
      row.addEventListener("pointerenter", hover);
      row.addEventListener("focus", hover);
      row.addEventListener("pointerleave", clearFloorHover);
      row.addEventListener("blur", clearFloorHover);
      tree.append(row);
    });
    return;
  }
  $("treeTitle").textContent = "Objetos do pavimento";
  $("treeCount").textContent = `${activeFloor.records.length} objetos`;
  const back = makeRow(`‹ ${$("projectName").textContent}`, "back", "", () =>
    leaveFloor(),
  );
  tree.append(back);
  const title = makeRow(
    `${floorLabel(activeFloor.id)} · vista de topo`,
    "floor active",
    "▱",
    null,
  );
  tree.append(title);
  [...activeFloor.records]
    .sort(
      (a, b) =>
        Number(a.objectNumber) - Number(b.objectNumber) ||
        a.object.name.localeCompare(b.object.name, "pt-BR", { numeric: true }),
    )
    .forEach((record) => {
      const row = makeRow(
        record.object.name,
        "env",
        "◈",
        () => {
          selectRecord(record);
        },
        record.object.uuid,
      );
      if (selected === record) row.classList.add("selected");
      tree.append(row);
    });
}
function updateStats(root) {
  let meshes = 0,
    triangles = 0;
  root.traverse((o) => {
    if (!o.isMesh) return;
    meshes++;
    triangles += o.geometry?.index
      ? o.geometry.index.count / 3
      : (o.geometry?.attributes?.position?.count || 0) / 3;
  });
  $("stats").replaceChildren();
  [
    ["Pavimentos", floors.length],
    ["Objetos identificados", allRecords.length],
    ["Meshes", meshes],
    ["Triângulos", Math.round(triangles).toLocaleString("pt-BR")],
  ].forEach(([label, value]) => {
    const card = document.createElement("div");
    card.className = "stat";
    const n = document.createElement("strong"),
      l = document.createElement("small");
    n.textContent = value;
    l.textContent = label;
    card.append(n, l);
    $("stats").append(card);
  });
}
function visibleFloorOnly(floor) {
  restoreContextMaterials();
  modelRoot.traverse((o) => {
    o.visible = false;
  });
  activeFloorMeshes = floor.records
    .map((record) => record.object)
    .filter(wasVisible);
  activeContextMeshes = mapBridge ? [] : floor.contextMeshes.filter(wasVisible);
  [...activeFloorMeshes, ...activeContextMeshes].forEach((object) => {
    for (let parent = object; parent; parent = parent.parent) {
      parent.visible = true;
      if (parent === modelRoot) break;
    }
  });
  clipFloorContext(floor);
  viewport.dataset.pickableCount = String(activeFloorMeshes.length);
  viewport.dataset.contextCount = String(activeContextMeshes.length);
  viewport.dataset.activeFloor = floor.id;
  const box = floorBounds(floor);
  updateSun();
  grid.position.y = floor.bounds.min.y - 0.015;
}
function wasVisible(object) {
  for (let parent = object; parent; parent = parent.parent) {
    if (originalVisibility.get(parent) === false) return false;
    if (parent === modelRoot) break;
  }
  return true;
}
function updateSun() {
  // Refit shadows for both solar and navigation lighting after visibility changes.
  solarLighting.setVisibleBounds(visibleModelBounds(modelRoot));
}
function updateNavigationLight(bounds) {
  if (bounds.isEmpty()) return;
  const center = bounds.getCenter(new THREE.Vector3());
  const radius = Math.max(
    bounds.getSize(new THREE.Vector3()).length() / 2,
    0.5,
  );
  light.position
    .copy(center)
    .add(new THREE.Vector3(radius * 0.9, radius * 2, radius * 0.65));
  light.target.position.copy(center);
  fitSolarShadow(light, bounds);
}
function applyCameraPose(pose) {
  const aspect = Math.max(
    viewport.clientWidth / Math.max(viewport.clientHeight, 1),
    0.1,
  );
  camera.left = (-pose.height * aspect) / 2;
  camera.right = (pose.height * aspect) / 2;
  camera.top = pose.height / 2;
  camera.bottom = -pose.height / 2;
  camera.position.copy(pose.position);
  camera.up.copy(pose.up);
  controls.target.copy(pose.target);
  camera.zoom = pose.zoom ?? 1;
  camera.lookAt(pose.target);
  camera.updateProjectionMatrix();
  camera.updateMatrixWorld();
}
function captureCameraPose() {
  return {
    position: camera.position.clone(),
    target: controls.target.clone(),
    up: camera.up.clone(),
    height: camera.top * 2,
    zoom: camera.zoom,
    top: !controls.enableRotate,
  };
}
function cameraPoseFor(bounds, top = false) {
  const size = new THREE.Vector3(),
    center = new THREE.Vector3();
  bounds.getSize(size);
  bounds.getCenter(center);
  const aspect = Math.max(
    viewport.clientWidth / Math.max(viewport.clientHeight, 1),
    0.1,
  );
  const height = top
    ? Math.max(size.z, size.x / aspect, 0.1) * 1.25
    : Math.max(
        (size.length() *
          (solarLighting.enabled && solarLighting.pathToggle.checked
            ? 1.48
            : 1.3)) /
          Math.min(1, aspect),
        0.1,
      );
  const position = top
    ? new THREE.Vector3(center.x, center.y + height * 2, center.z)
    : center
        .clone()
        .add(new THREE.Vector3(height * 1.15, height * 0.85, height * 1.25));
  return {
    position,
    target: center,
    up: top ? new THREE.Vector3(0, 0, -1) : new THREE.Vector3(0, 1, 0),
    height,
    top,
  };
}
function transitionCamera(destination, duration = 650) {
  if (!destination) return Promise.resolve(false);
  viewport.dataset.cameraView = destination.bottom ? "Inferior" : "";
  controls.maxPolarAngle = destination.bottom ? Math.PI : Math.PI / 2;
  if (matchMedia("(prefers-reduced-motion: reduce)").matches) duration = 0;
  // Consome a inércia anterior antes de fixar o novo target do pavimento.
  const damping = controls.enableDamping;
  controls.enableDamping = false;
  controls.update();
  controls.enableDamping = damping;
  const token = ++transitionToken,
    source = captureCameraPose(),
    started = performance.now();
  const fromPos = source.position.clone(),
    fromTarget = source.target.clone(),
    fromUp = source.up.clone();
  const toPos = destination.position.clone(),
    toTarget = destination.target.clone(),
    toUp = destination.up.clone();
  const fromOffset = fromPos.clone().sub(fromTarget),
    toOffset = toPos.clone().sub(toTarget);
  const orbitRotation = new THREE.Quaternion().setFromUnitVectors(
    fromOffset.clone().normalize(),
    toOffset.clone().normalize(),
  );
  transitioning = true;
  controls.enabled = false;
  return new Promise((resolve) => {
    function step(now) {
      if (token !== transitionToken) {
        resolve(false);
        return;
      }
      const t = duration ? Math.min((now - started) / duration, 1) : 1,
        ease = t * t * (3 - 2 * t);
      const pose = {
        position: fromTarget
          .clone()
          .lerp(toTarget, ease)
          .add(
            fromOffset
              .clone()
              .normalize()
              .applyQuaternion(
                new THREE.Quaternion().slerp(orbitRotation, ease),
              )
              .multiplyScalar(
                THREE.MathUtils.lerp(
                  fromOffset.length(),
                  toOffset.length(),
                  ease,
                ),
              ),
          ),
        target: fromTarget.clone().lerp(toTarget, ease),
        up: fromUp
          .clone()
          .applyQuaternion(
            new THREE.Quaternion()
              .setFromUnitVectors(fromUp, toUp)
              .slerp(new THREE.Quaternion(), 1 - ease),
          )
          .normalize(),
        height: source.height + (destination.height - source.height) * ease,
        zoom: source.zoom + ((destination.zoom ?? 1) - source.zoom) * ease,
        top: destination.top,
      };
      applyCameraPose(pose);
      if (t < 1) requestAnimationFrame(step);
      else {
        transitioning = false;
        controls.enabled = !mapVisible;
        controls.enableRotate = !destination.top;
        controls.update();
        resolve(true);
        if (activeFloor)
          status(
            `Pavimento ${floorLabel(activeFloor.id)} · passe o mouse sobre um objeto`,
          );
        else if (viewerState.selectedFloor)
          status(
            `Pavimento ${floorLabel(viewerState.selectedFloor.id)} · navegação 3D`,
          );
        else if (modelRoot)
          status(
            `${$("projectName").textContent} · passe o mouse sobre um pavimento`,
          );
      }
    }
    requestAnimationFrame(step);
  });
}
function setCamera(bounds, top = false, animate = false) {
  const pose = cameraPoseFor(bounds, top);
  if (animate) transitionCamera(pose);
  else {
    ++transitionToken;
    transitioning = false;
    applyCameraPose(pose);
    controls.enabled = true;
    controls.enableRotate = !top;
    controls.update();
  }
  return pose;
}
function fitFull() {
  if (!modelRoot) return;
  const box = new THREE.Box3().setFromObject(modelRoot, true);
  if (!box.isEmpty()) {
    setCamera(box, false, false);
    fullCameraPose = captureCameraPose();
    updateSun();
    grid.position.y = box.min.y - 0.015;
  }
}
function floorBounds(floor) {
  const box = floor.bounds.clone();
  if (mapBridge) return box;
  floor.contextMeshes.filter(wasVisible).forEach((mesh) => {
    const bounds = new THREE.Box3().setFromObject(mesh, true);
    bounds.min.y = Math.max(bounds.min.y, floor.minY);
    bounds.max.y = Math.min(bounds.max.y, floor.maxY);
    if (!bounds.isEmpty()) box.union(bounds);
  });
  return box;
}
function fitFloor(floor, animate = true) {
  const box = floorBounds(floor);
  if (!box.isEmpty()) setCamera(box, true, animate);
}
function activateFloor(floor) {
  if (measurements.active || northDefining || mapBridge?.busy || mapVisible)
    return;
  return mapBridge ? mapBridge.activateFloor(floor) : focusFloor(floor);
}

// A seleção 3D é independente da vista isolada usada apenas ao abrir o mapa.
async function focusFloor(floor) {
  if (!modelRoot || !floor || floor.bounds.isEmpty()) return;
  if (viewerState.mode === "building") {
    const damping = controls.enableDamping;
    controls.enableDamping = false;
    controls.update();
    controls.enableDamping = damping;
    fullCameraPose = captureCameraPose();
    viewerState.sidebarBeforeFloor = {
      left: $("leftSidebar").classList.contains("is-collapsed"),
      right: $("rightSidebar").classList.contains("is-collapsed"),
    };
  }
  clearVisualHighlights();
  floorVisibility?.apply(floor);
  viewerState.mode = "floor";
  viewerState.selectedFloor = floor;
  viewerState.hoveredFloor = hoveredRecord = null;
  selected = null;
  hideTooltip();
  viewport.dataset.focusedFloor = floor.id;
  viewport.dataset.viewerMode = "floor";
  toggleSidebar("left", "Pavimentos", true);
  toggleSidebar("right", "Hub de Inspeção", true);
  refreshSolarVisibility();
  controls.enableRotate = true;
  refreshHighlights();
  renderTree();
  openFloorPopover(floor);
  $("backToProject").hidden = false;
  $("modeLabel").textContent = `Navegação 3D · ${floorLabel(floor.id)}`;
  $("hint").textContent =
    "Arraste para orbitar o pavimento; selecione outro para trocar";
  await settleSidebarLayout();
  if (viewerState.selectedFloor !== floor) return;
  await transitionCamera(
    floorOrbitPose(
      floor.bounds,
      camera,
      controls.target,
      viewport.clientWidth / Math.max(viewport.clientHeight, 1),
    ),
    850,
  );
}

function hoverFloor(floor) {
  if (measurements.active || northDefining || transitioning || mapBridge?.busy)
    return;
  if (
    !floor.records.some(
      (record) => record.object.visible && wasVisible(record.object),
    )
  )
    return;
  viewerState.hoveredFloor = floor;
  refreshHighlights();
}
function clearFloorHover() {
  if (!viewerState.hoveredFloor) return;
  viewerState.hoveredFloor = null;
  refreshHighlights();
}
function settleSidebarLayout() {
  return new Promise((resolve) => {
    const layout = document.querySelector(".layout");
    requestAnimationFrame(() => {
      const animations = layout.getAnimations();
      Promise.allSettled(
        animations.map((animation) => animation.finished),
      ).then(() => {
        resizeViewport();
        resolve();
      });
    });
  });
}
function refreshSolarVisibility() {
  if (!modelRoot || !solarLighting.bounds) return;
  modelRoot.updateWorldMatrix(true, true);
  // Box3.setFromObject includes invisible objects and unclipped geometry. Use
  // only the remaining model surfaces, never the navigation camera or helpers.
  solarLighting.setVisibleBounds(visibleModelBounds(modelRoot));
}

function openFloorPopover(floor) {
  const content = $("floorPopoverContent");
  if (mapBridge) mapBridge.showSummary(floor, content);
  else {
    content.replaceChildren();
    const title = document.createElement("h3");
    title.textContent = floorLabel(floor.id);
    const info = document.createElement("p");
    info.textContent =
      "Vincule este pavimento a uma obra para consultar ambientes e imagens.";
    const button = document.createElement("button");
    button.className = "btn primary";
    button.textContent = "Ver mapa arquitetônico";
    button.disabled = true;
    const help = document.createElement("p");
    help.textContent =
      "Abra um modelo cadastrado em uma obra para acessar o mapa vinculado.";
    content.append(title, info, button, help);
  }
  $("floorPopover").hidden = false;
}
function closeFloorPopover() {
  $("floorPopover").hidden = true;
}
async function resetBuildingView() {
  if (!modelRoot) return;
  ++transitionToken;
  transitioning = false;
  clearVisualHighlights();
  floorVisibility?.restore();
  viewerState.mode = "building";
  viewerState.selectedFloor = null;
  viewerState.hoveredFloor = hoveredRecord = null;
  selected = null;
  if (mapBridge) mapBridge.currentFloor = null;
  delete viewport.dataset.focusedFloor;
  viewport.dataset.viewerMode = "building";
  if (viewerState.sidebarBeforeFloor) {
    toggleSidebar("left", "Pavimentos", viewerState.sidebarBeforeFloor.left);
    toggleSidebar(
      "right",
      "Hub de Inspeção",
      viewerState.sidebarBeforeFloor.right,
    );
    viewerState.sidebarBeforeFloor = null;
  }
  refreshSolarVisibility();
  closeFloorPopover();
  refreshHighlights();
  renderTree();
  $("backToProject").hidden = true;
  $("modeLabel").textContent =
    "Empreendimento completo · passe o mouse para destacar pavimentos";
  $("hint").textContent =
    "Clique em um pavimento para aproximar e explorar em 3D";
  mapBridge?.showIntro();
  // Recalcula o enquadramento para o espaço atual, inclusive após recolher painéis.
  await settleSidebarLayout();
  if (viewerState.mode !== "building") return;
  const box = new THREE.Box3().setFromObject(modelRoot, true);
  const pose = fullCameraPose || cameraPoseFor(box);
  await transitionCamera(pose, 850);
}
async function fadeOtherObjects(floor, from = 1, to = 0) {
  if (matchMedia("(prefers-reduced-motion: reduce)").matches) return;
  const keep = new Set(floor.records.map((record) => record.object)),
    originals = new Map(),
    clones = new Map();
  modelRoot.traverse((object) => {
    if (!object.isMesh || keep.has(object) || !wasVisible(object)) return;
    const materials = Array.isArray(object.material)
      ? object.material
      : [object.material];
    originals.set(object, object.material);
    const faded = materials.map((material) => {
      if (!clones.has(material)) {
        const clone = material.clone();
        clone.transparent = true;
        clone.depthWrite = false;
        clones.set(material, clone);
      }
      return clones.get(material);
    });
    object.material = Array.isArray(object.material) ? faded : faded[0];
  });
  const started = performance.now();
  try {
    await new Promise((resolve) => {
      function step(now) {
        const t = Math.min((now - started) / 400, 1);
        for (const [original, clone] of clones)
          clone.opacity = original.opacity * (from + (to - from) * t);
        if (t < 1) requestAnimationFrame(step);
        else resolve();
      }
      step(started);
    });
  } finally {
    originals.forEach((material, object) => {
      object.material = material;
    });
    clones.forEach((material) => material.dispose());
  }
}
async function enterFloor(floor) {
  if (!modelRoot || activeFloor === floor) return;
  closeFloorPopover();
  if (!activeFloor && !viewerState.selectedFloor) {
    const damping = controls.enableDamping;
    controls.enableDamping = false;
    controls.update();
    controls.enableDamping = damping;
    fullCameraPose = captureCameraPose();
    fullControlState = {
      enableRotate: controls.enableRotate,
      enablePan: controls.enablePan,
      enableZoom: controls.enableZoom,
    };
  }
  if (mapBridge) {
    solarLighting.setProjectionSuspended(true);
    transitioning = true;
    controls.enabled = false;
    viewerState.selectedFloor = floor;
    viewerState.hoveredFloor = null;
    refreshHighlights();
    await fadeOtherObjects(floor);
  }
  hideTooltip();
  clearSelection();
  viewerState.hoveredFloor = null;
  hoveredRecord = null;
  activeFloor = floor;
  viewerState.selectedFloor = null;
  visibleFloorOnly(floor);
  renderTree();
  renderFloorSwitcher();
  $("backToProject").hidden = false;
  $("modeLabel").textContent = `Vista de topo · ${floorLabel(floor.id)}`;
  $("hint").textContent =
    "Passe o mouse sobre os objetos para inspecionar; arraste para orbitar";
  $("details").innerHTML =
    '<div class="placeholder">Pavimento isolado. Selecione uma peça na cena ou na lista de objetos para ver seus dados.</div>';
  $("selectedType").textContent = "—";
  status(`Abrindo ${floorLabel(floor.id)}…`);
  await transitionCamera(cameraPoseFor(floorBounds(floor), true), 900);
}
async function leaveFloor() {
  if (!activeFloor) {
    await resetBuildingView();
    solarLighting.setProjectionSuspended(false);
    return;
  }
  hideTooltip();
  clearSelection();
  restoreContextMaterials();
  viewerState.hoveredFloor = null;
  hoveredRecord = null;
  viewerState.selectedFloor = null;
  modelRoot.traverse((o) => {
    o.visible = originalVisibility.get(o) ?? true;
  });
  activeFloor = null;
  delete viewport.dataset.focusedFloor;
  activeFloorMeshes = [];
  activeContextMeshes = [];
  renderTree();
  renderFloorSwitcher();
  delete viewport.dataset.activeFloor;
  viewport.dataset.pickableCount = String(allFloorMeshes.length);
  viewport.dataset.contextCount = "0";
  const box = new THREE.Box3().setFromObject(modelRoot, true);
  updateSun();
  grid.position.y = box.min.y - 0.015;
  $("backToProject").hidden = true;
  $("modeLabel").textContent =
    "Empreendimento completo · passe o mouse para destacar pavimentos";
  $("hint").textContent =
    "Passe o mouse sobre um pavimento e clique para entrar";
  $("details").innerHTML =
    '<div class="placeholder">No modelo completo, o hover destaca os objetos agrupados por pavimento. Clique em um pavimento para abrir sua vista de topo.</div>';
  $("selectedType").textContent = "—";
  status(`${allRecords.length} objetos identificados no empreendimento`);
  if (fullControlState) Object.assign(controls, fullControlState);
  controls.enabled = true;
  solarLighting.setProjectionSuspended(false);
  await resetBuildingView();
}
function clearModel() {
  clearVisualHighlights();
  floorVisibility?.restore();
  floorVisibility = null;
  if (viewerState.sidebarBeforeFloor) {
    toggleSidebar("left", "Pavimentos", viewerState.sidebarBeforeFloor.left);
    toggleSidebar(
      "right",
      "Hub de Inspeção",
      viewerState.sidebarBeforeFloor.right,
    );
  }
  viewerState.mode = "building";
  viewerState.sidebarBeforeFloor = null;
  minimumCameraY = null;
  closeFloorPopover();
  delete viewport.dataset.focusedFloor;
  measurements.reset();
  solarLighting.setBounds(null);
  ++transitionToken;
  transitioning = false;
  clearSelection();
  restoreContextMaterials();
  if (modelRoot) {
    scene.remove(modelRoot);
    const materials = new Set(),
      geometries = new Set(),
      textures = new Set();
    modelRoot.traverse((object) => {
      if (object.geometry) geometries.add(object.geometry);
      if (object.material)
        (Array.isArray(object.material)
          ? object.material
          : [object.material]
        ).forEach((material) => materials.add(material));
    });
    materials.forEach((material) => {
      Object.values(material).forEach((value) => {
        if (value?.isTexture) textures.add(value);
      });
      material.dispose();
    });
    textures.forEach((texture) => texture.dispose());
    geometries.forEach((geometry) => geometry.dispose());
  }
  modelRoot = null;
  floors = [];
  allRecords = [];
  activeFloor = null;
  viewerState.selectedFloor = null;
  viewerState.hoveredFloor = null;
  hoveredRecord = null;
  allFloorMeshes = [];
  modelMeshes = [];
  activeFloorMeshes = [];
  activeContextMeshes = [];
  recordByObject.clear();
  originalVisibility = new Map();
  unassignedContextMeshes = [];
  $("stats").replaceChildren();
  $("selectedType").textContent = "—";
  $("backToProject").hidden = true;
  fullCameraPose = null;
  fullControlState = null;
  renderTree();
  renderFloorSwitcher();
}
function install(gltf, sourceName) {
  ++transitionToken;
  transitioning = false;
  controls.enabled = true;
  hideTooltip();
  clearModel();
  modelRoot = gltf.scene;
  // The viewer has no real world behind the model. Physical transmission samples
  // the environment cubemap in that case and can turn balcony panes into bright
  // white cards. Alpha blending lets the actual scene background (and geometry
  // behind the pane) show through, so it follows background changes automatically.
  const adjustedGlass = new Map();
  modelRoot.traverse((object) => {
    if (!object.isMesh) return;
    const materials = Array.isArray(object.material)
      ? object.material
      : [object.material];
    let changed = false;
    const viewerMaterials = materials.map((material) => {
      if (
        !material?.isMeshPhysicalMaterial ||
        !/(vidro|glass)/i.test(material.name || "")
      )
        return material;
      if (!adjustedGlass.has(material)) {
        const glass = material.clone();
        glass.userData.viewerGlass = true;
        glass.transmission = 0;
        glass.opacity = Math.min(material.opacity, 0.16);
        glass.transparent = true;
        glass.depthWrite = false;
        glass.depthTest = true;
        glass.roughness = Math.max(0.16, Math.min(material.roughness, 0.3));
        glass.metalness = 0;
        glass.ior = 1.1;
        glass.thickness = 0;
        glass.envMapIntensity = Math.min(material.envMapIntensity, 0.12);
        glass.needsUpdate = true;
        adjustedGlass.set(material, glass);
      }
      changed = true;
      return adjustedGlass.get(material);
    });
    if (changed)
      object.material = Array.isArray(object.material)
        ? viewerMaterials
        : viewerMaterials[0];
  });
  scene.add(modelRoot);
  modelRoot.updateMatrixWorld(true);
  originalVisibility = new Map();
  modelRoot.traverse((o) => originalVisibility.set(o, o.visible));
  const indexed = buildIndex(modelRoot, sourceName);
  floors = indexed.floors;
  allRecords = indexed.records;
  activeFloor = null;
  recordByObject = new Map(allRecords.map((record) => [record.object, record]));
  allFloorMeshes = allRecords.map((record) => record.object).filter(wasVisible);
  activeFloorMeshes = [];
  activeContextMeshes = [];
  modelRoot.traverse((object) => {
    if (!object.isMesh) return;
    modelMeshes.push(object);
    if (recordByObject.has(object)) object.layers.enable(PICKING_LAYER);
    else object.layers.disable(PICKING_LAYER);
    // Lajes, pisos e paredes também bloqueiam o Sol; vidro totalmente transmissivo não é opaco.
    const materials = Array.isArray(object.material)
      ? object.material
      : [object.material];
    object.castShadow = materials.some(solarOpaqueMaterial);
    object.receiveShadow = object.castShadow;
  });
  contextIndex(modelRoot);
  floorVisibility = new FloorVisibility(modelRoot, floors, recordByObject);
  const modelBounds = new THREE.Box3().setFromObject(modelRoot, true);
  minimumCameraY = modelBounds.isEmpty()
    ? null
    : modelBounds.min.y +
      Math.max(
        modelBounds.getSize(new THREE.Vector3()).length() * 0.0005,
        camera.near * 2,
      );
  solarLighting.setBounds(modelBounds);
  delete viewport.dataset.activeFloor;
  viewport.dataset.pickableCount = String(allFloorMeshes.length);
  viewport.dataset.contextCount = "0";
  const project = sourceName.replace(/\.(glb|gltf)$/i, "");
  $("projectName").textContent = project;
  document.title = `${project} · Visualizador espacial`;
  renderTree();
  renderFloorSwitcher();
  updateStats(modelRoot);
  fitFull();
  $("backToProject").hidden = true;
  $("modeLabel").textContent =
    "Empreendimento completo · passe o mouse para destacar pavimentos";
  $("hint").textContent =
    "Clique em um pavimento para aproximar e explorar em 3D";
  $("details").innerHTML = floors.length
    ? '<div class="placeholder">Clique em um pavimento para aproximar e explorar em 3D. Acesse a planta pelo botão Ver mapa arquitetônico.</div>'
    : '<div class="placeholder">Nenhum pavimento identificado. Nomeie os objetos como PAVTO_TIPO_01_01, PAVTO_TIPO_01_02 e assim por diante.</div>';
  $("selectedType").textContent = "—";
  status(
    floors.length
      ? `${floors.length} pavimento(s) · ${allRecords.length} objetos identificados`
      : "Nenhum pavimento identificado",
    !floors.length,
  );
  if (new URLSearchParams(location.search).has("debug")) {
    console.groupCollapsed(`Diagnóstico GLB · ${sourceName}`);
    modelRoot.traverse((object) =>
      console.log({
        name: object.name,
        type: object.type,
        uuid: object.uuid,
        parent: object.parent?.name,
        children: object.children.length,
        userData: object.userData,
      }),
    );
    console.table(
      allRecords.map((r) => ({
        categoria: "PAVIMENTO",
        pavimento: r.floor,
        objeto: r.objectNumber || r.name,
        mesh: r.object.name,
      })),
    );
    console.table(
      floors.map((floor) => ({
        pavimento: floor.id,
        objetos: floor.records.length,
        contexto: floor.contextMeshes.length,
      })),
    );
    if (unassignedContextMeshes.length)
      console.log(
        "Contexto sem pavimento identificado:",
        unassignedContextMeshes.map((mesh) => mesh.name || mesh.uuid),
      );
    console.groupEnd();
  }
}
async function loadUrl(url) {
  const src = (url || "").trim();
  if (!src) return;
  status("Carregando empreendimento…");
  const gltf = await loader.loadAsync(src);
  install(gltf, decodeURIComponent(src.split("/").pop().split("?")[0]) || src);
}
function loadFile(file) {
  if (!file || mapBridge) return;
  const url = URL.createObjectURL(file);
  status("Carregando arquivo…");
  loader.load(
    url,
    (gltf) => {
      install(gltf, file.name);
      if (currentObjectUrl) URL.revokeObjectURL(currentObjectUrl);
      currentObjectUrl = url;
    },
    undefined,
    (error) => {
      URL.revokeObjectURL(url);
      console.error(error);
      status(error?.message || "Falha ao abrir arquivo", true);
    },
  );
}
const loadDiagnostic = () =>
  loadUrl($("modelUrl").value).catch((error) => {
    console.error(error);
    status(error.message, true);
  });
$("loadUrl").addEventListener("click", loadDiagnostic);
$("modelUrl").addEventListener("keydown", (e) => {
  if (e.key === "Enter") loadDiagnostic();
});
$("chooseFile").addEventListener("click", () => $("modelFile").click());
$("modelFile").addEventListener("change", (e) => loadFile(e.target.files?.[0]));
$("fit").addEventListener("click", () =>
  activeFloor
    ? fitFloor(activeFloor, false)
    : viewerState.selectedFloor
      ? focusFloor(viewerState.selectedFloor)
      : fitFull(),
);
$("backToProject").addEventListener("click", () =>
  mapBridge?.mapShown ? mapBridge.requestReturn() : leaveFloor(),
);
$("axes").addEventListener("click", (e) => {
  axes.visible = !axes.visible;
  e.currentTarget.textContent = `Eixos: ${axes.visible ? "ligados" : "desligados"}`;
  e.currentTarget.setAttribute("aria-pressed", String(axes.visible));
});
$("grid").addEventListener("click", (e) => {
  grid.visible = !grid.visible;
  e.currentTarget.textContent = `Grade: ${grid.visible ? "ligada" : "desligada"}`;
  e.currentTarget.setAttribute("aria-pressed", String(grid.visible));
});
renderer.domElement.addEventListener("pointerdown", (e) => {
  hideTooltip();
  pickingPointers.add(e.pointerId);
  if (pickingPointers.size > 1) multiPointerGesture = true;
  dragStart = multiPointerGesture
    ? null
    : {
        x: e.clientX,
        y: e.clientY,
        button: e.button,
        measuring: measurements.active,
      };
});
const pickingPointers = new Set();
let multiPointerGesture = false;
function raycastModel(event, measuring = false) {
  if (!modelRoot) return [];
  const rect = renderer.domElement.getBoundingClientRect();
  if (!rect.width || !rect.height) return [];
  pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
  pointer.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
  raycaster.setFromCamera(pointer, camera);
  if (measuring) raycaster.layers.enableAll();
  else raycaster.layers.set(PICKING_LAYER);
  return raycaster
    .intersectObjects(
      measuring
        ? modelMeshes
        : activeFloor
          ? activeFloorMeshes
          : allFloorMeshes,
      false,
    )
    .filter((hit) => !hit.object.userData.isShadowCatcher);
}
function pickMeasurement(event) {
  return raycastModel(event, true).find(visibleSurface) || null;
}
function pickRecord(event) {
  // Only identified floor objects are pickable; hidden floors and context cannot intercept clicks.
  const hits = raycastModel(event);
  for (const hit of hits) {
    const record = recordByObject.get(hit.object);
    if (
      record &&
      visibleSurface(hit) &&
      wasVisible(hit.object) &&
      (!activeFloor || record.floor === activeFloor.id)
    )
      return record;
  }
  return null;
}
renderer.domElement.addEventListener("pointermove", (event) => {
  if (
    northDefining ||
    transitioning ||
    mapBridge?.busy ||
    !modelRoot ||
    event.buttons
  ) {
    if (measurements.active) measurements.move(null);
    hideTooltip();
    return;
  }
  if (measurements.active) {
    measurements.move(event);
    return;
  }
  const record = pickRecord(event);
  if (activeFloor) {
    const next = record?.floor === activeFloor.id ? record : null;
    if (next?.object.uuid !== hoveredRecord?.object.uuid) {
      hoveredRecord = next;
      viewerState.hoveredFloor = null;
      refreshHighlights();
    }
    showTooltip(next, event);
    status(
      next
        ? `Objeto ${next.object.name} · clique para selecionar`
        : `Pavimento ${floorLabel(activeFloor.id)} · passe o mouse sobre um objeto`,
    );
  } else {
    hideTooltip();
    const next = record
      ? floors.find((floor) => floor.id === record.floor) || null
      : null;
    if (next?.id !== viewerState.hoveredFloor?.id) {
      viewerState.hoveredFloor = next;
      hoveredRecord = null;
      refreshHighlights();
    }
    status(
      next
        ? `Pavimento ${floorLabel(next.id)} · clique para aproximar`
        : `${$("projectName").textContent} · passe o mouse sobre um pavimento`,
    );
  }
});
renderer.domElement.addEventListener("pointerleave", () => {
  measurements.move(null);
  hideTooltip();
  viewerState.hoveredFloor = null;
  hoveredRecord = null;
  refreshHighlights();
  if (measurements.active) return;
  if (activeFloor)
    status(
      `Pavimento ${floorLabel(activeFloor.id)} · passe o mouse sobre um objeto`,
    );
  else if (modelRoot)
    status(
      `${$("projectName").textContent} · passe o mouse sobre um pavimento`,
    );
});
renderer.domElement.addEventListener("pointerup", (e) => {
  const multiPointer = multiPointerGesture;
  pickingPointers.delete(e.pointerId);
  if (!pickingPointers.size) multiPointerGesture = false;
  if (
    multiPointer ||
    northDefining ||
    transitioning ||
    mapBridge?.busy ||
    !dragStart ||
    dragStart.measuring !== measurements.active ||
    dragStart.button !== 0 ||
    Math.hypot(e.clientX - dragStart.x, e.clientY - dragStart.y) > 5
  ) {
    dragStart = null;
    return;
  }
  dragStart = null;
  if (measurements.active) {
    measurements.choose(pickMeasurement(e)?.point);
    return;
  }
  const record = pickRecord(e);
  if (!record) return;
  if (!activeFloor) {
    const floor = floors.find((item) => item.id === record.floor);
    if (floor) activateFloor(floor);
  } else if (record.floor === activeFloor.id) {
    selectRecord(record);
    showTooltip(record, e);
  }
});
renderer.domElement.addEventListener("pointercancel", (event) => {
  pickingPointers.delete(event.pointerId);
  if (!pickingPointers.size) multiPointerGesture = false;
  measurements.move(null);
  dragStart = null;
  hideTooltip();
  hoveredRecord = null;
  viewerState.hoveredFloor = null;
  refreshHighlights();
});
controls.addEventListener("change", () => {
  if (
    minimumCameraY === null ||
    transitioning ||
    northDefining ||
    viewport.dataset.cameraView === "Inferior"
  )
    return;
  const correction = minimumCameraY - camera.position.y;
  if (correction <= 0) return;
  // O pan também respeita o chão, preservando a direção e a distância da órbita.
  camera.position.y += correction;
  controls.target.y += correction;
  camera.updateMatrixWorld();
});
controls.addEventListener("start", () => {
  hideTooltip();
  hoveredRecord = null;
  viewerState.hoveredFloor = null;
  refreshHighlights();
});
window.addEventListener("blur", () => {
  pickingPointers.clear();
  multiPointerGesture = false;
  dragStart = null;
  measurements.move(null);
  hideTooltip();
  hoveredRecord = null;
  viewerState.hoveredFloor = null;
  refreshHighlights();
});
document.addEventListener("keydown", (event) => {
  if (
    event.key !== "Escape" ||
    event.defaultPrevented ||
    viewerState.mode !== "floor" ||
    measurements.active ||
    northDefining ||
    mapVisible ||
    mapBridge?.busy ||
    document.querySelector("dialog[open]") ||
    event.target.closest('input,textarea,select,[contenteditable="true"]')
  )
    return;
  event.preventDefault();
  void resetBuildingView();
});
viewport.addEventListener("dragover", (e) => {
  e.preventDefault();
  viewport.classList.add("drag");
});
viewport.addEventListener("dragleave", (e) => {
  if (!viewport.contains(e.relatedTarget)) viewport.classList.remove("drag");
});
viewport.addEventListener("drop", (e) => {
  e.preventDefault();
  viewport.classList.remove("drag");
  loadFile(
    [...e.dataTransfer.files].find((f) => /\.(glb|gltf)$/i.test(f.name)),
  );
});
let lastViewportWidth = 0;
let lastViewportHeight = 0;
let resizeViewportTimer = 0;
function resizeViewport() {
  const w = viewport.clientWidth,
    h = viewport.clientHeight;
  if (!w || !h) return;
  if (w === lastViewportWidth && h === lastViewportHeight) return;
  lastViewportWidth = w;
  lastViewportHeight = h;
  hideTooltip();
  renderer.setSize(w, h, false);
  // Esta câmera é ortográfica: left/right fazem o papel do aspect da perspectiva.
  camera.aspect = w / h;
  camera.left = -camera.top * (w / h);
  camera.right = camera.top * (w / h);
  camera.updateProjectionMatrix();
}
function scheduleResizeViewport() {
  window.clearTimeout(resizeViewportTimer);
  resizeViewportTimer = window.setTimeout(() => {
    resizeViewportTimer = 0;
    resizeViewport();
  }, 80);
}
const layout = document.querySelector(".layout");
layout.addEventListener("transitionend", (event) => {
  if (event.target !== layout || event.propertyName !== "grid-template-columns")
    return;
  window.clearTimeout(resizeViewportTimer);
  resizeViewportTimer = 0;
  resizeViewport();
});
new ResizeObserver(scheduleResizeViewport).observe(viewport);
window.addEventListener("resize", scheduleResizeViewport);
resizeViewport();
(function animate() {
  requestAnimationFrame(animate);
  $("backToProject").disabled =
    measurements.active || northDefining || !!mapBridge?.busy;
  $("resetBuildingView").disabled =
    measurements.active || northDefining || !!mapBridge?.busy || mapVisible;
  measurements.setAvailable(
    !!modelRoot &&
      !mapVisible &&
      !northDefining &&
      !transitioning &&
      !mapBridge?.busy,
  );
  if (!mapVisible) {
    if (!transitioning && !northDefining) controls.update();
    northIndicator.update();
    solarLighting.update(camera);
    measurements.update();
    renderer.render(scene, camera);
  }
})();
toggleSidebar("left", "Pavimentos", true);
toggleSidebar("right", "Hub de Inspeção", true);
const params = new URLSearchParams(location.search);
if (params.has("src") && !params.has("obra_id")) {
  $("modelUrl").value = params.get("src");
  loadDiagnostic();
} else {
  axes.visible = grid.visible = false;
  $("axes").textContent = "Eixos: desligados";
  $("grid").textContent = "Grade: desligada";
  mapBridge = new EmpreendimentoMapBridge({
    loadModel: loadUrl,
    clearModel,
    enterFloor,
    findFloor: (id) => floors.find((floor) => floor.id === id),
    leaveFloor,
    focusFloor,
    status,
    refreshTree: () => {
      renderTree();
      if (viewerState.selectedFloor && !activeFloor)
        openFloorPopover(viewerState.selectedFloor);
    },
    highlight: (floor) => {
      viewerState.selectedFloor = floor;
      viewerState.hoveredFloor = null;
      refreshHighlights();
    },
    setMapVisible: (value) => {
      mapVisible = value;
      solarLighting.setMapVisible(value);
      northIndicator.setMapVisible(value);
      measurements.setSuspended(value);
      controls.enabled = !value;
    },
    spatial: {
      setConfig: (config, error) => solarLighting.setConfig(config, error),
      setNorth: (degrees, defining = false) =>
        northIndicator.set(degrees, defining),
      setDefining: (value) => {
        northDefining = value;
        measurements.setSuspended(value);
        solarLighting.setSuspended(value);
        viewport.classList.toggle("north-defining", value);
        dragStart = null;
        hideTooltip();
        viewerState.hoveredFloor = null;
        hoveredRecord = null;
        refreshHighlights();
        if (value) {
          northPreviousControls = controls.enabled;
          controls.enabled = false;
        } else controls.enabled = northPreviousControls;
      },
    },
  });
  mapBridge.start();
}

// ViewCube: ações acessíveis com o mesmo enquadramento e transição da navegação.
const viewCube = document.createElement("nav");
viewCube.className = "spatial-viewcube";
viewCube.setAttribute("aria-label", "ViewCube: orientação da câmera");
const cubeViews = [
  ["Superior", [0, 1, 0], [0, 0, -1]],
  ["Frente", [0, 0, 1]],
  ["Trás", [0, 0, -1]],
  ["Esquerda", [-1, 0, 0]],
  ["Direita", [1, 0, 0]],
  ["Inferior", [0, -1, 0], [0, 0, 1]],
  ["Perspectiva", [1.15, 0.85, 1.25]],
];
for (const [label, direction, up = [0, 1, 0]] of cubeViews) {
  const button = document.createElement("button");
  button.type = "button";
  button.textContent = label;
  button.setAttribute("aria-label", "Vista " + label.toLowerCase());
  button.addEventListener("click", async () => {
    if (!modelRoot || transitioning || northDefining || mapBridge?.busy) return;
    const focusedFloor = activeFloor || viewerState.selectedFloor;
    const bounds = focusedFloor ? floorBounds(focusedFloor) : modelBounds;
    const pose = cameraPoseFor(
      bounds,
      label === "Superior" || label === "Inferior",
    );
    pose.position
      .copy(pose.target)
      .add(
        new THREE.Vector3(...direction)
          .normalize()
          .multiplyScalar(pose.height * 2),
      );
    pose.up.set(...up);
    pose.bottom = label === "Inferior";
    await transitionCamera(pose);
    viewCube
      .querySelectorAll("button")
      .forEach((item) =>
        item.setAttribute("aria-pressed", String(item === button)),
      );
  });
  viewCube.append(button);
}
viewport.append(viewCube);
