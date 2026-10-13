import * as THREE from "three";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";
import { GLTFLoader } from "three/addons/loaders/GLTFLoader.js";

const template = `
<style>
  :root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#e8edf4;background:#101318;color-scheme:dark;--muted:#8590a1;--line:#29303b;--panel:#171b22;--accent:#91e5c0;--blue:#94bfff}
  *{box-sizing:border-box}body{margin:0;min-height:100vh}button,input{font:inherit}.poc{height:100vh;min-height:560px;display:grid;grid-template-rows:62px 1fr}.top{display:flex;align-items:center;gap:14px;padding:0 18px;border-bottom:1px solid var(--line);background:#14171d}.brand{display:flex;align-items:center;gap:10px;min-width:205px}.mark{width:32px;height:32px;border-radius:9px;background:#263e37;color:var(--accent);display:grid;place-items:center;font-weight:800}.brand strong{font-size:13px}.brand small{display:block;color:var(--muted);font-size:10px;margin-top:2px}.load{display:flex;gap:7px;align-items:center;flex:1;min-width:0}.load input{max-width:440px;flex:1;min-width:100px;background:#0f1217;border:1px solid var(--line);border-radius:7px;color:#dfe7f1;padding:9px 10px;font-size:12px}.btn{background:#222832;border:1px solid #343d49;padding:8px 11px;border-radius:7px;color:inherit;font-size:11px;cursor:pointer}.btn:hover{background:#2b3440}.btn.primary{background:#a1edca;border-color:#a1edca;color:#13241c;font-weight:700}.status{font-size:10px;color:var(--muted);white-space:nowrap}.layout{display:grid;grid-template-columns:290px minmax(350px,1fr) 290px;min-height:0}.side{background:var(--panel);min-height:0;overflow:auto}.left{border-right:1px solid var(--line)}.right{border-left:1px solid var(--line)}.head{height:48px;padding:0 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--line);position:sticky;top:0;background:var(--panel);z-index:2}.head h2{margin:0;color:#aeb8c6;font-size:10px;text-transform:uppercase;letter-spacing:.09em}.count{font:10px ui-monospace,monospace;color:var(--muted)}.tree{padding:10px 8px 24px}.row{display:flex;align-items:center;gap:7px;min-height:31px;padding:5px 8px;border-radius:5px;font-size:12px;white-space:nowrap;overflow:hidden;color:#cbd3df;cursor:pointer}.row:hover{background:#222832}.row.selected{background:#283e38;color:#b8f1d4}.row.floor{font-weight:700;color:#dfe7ef;cursor:default}.row.env{padding-left:23px}.row.mesh{padding-left:40px;color:#a8b6c7}.twisty{width:12px;color:#7d8b9a;font-size:10px;text-align:center}.ico{width:14px;color:var(--blue);text-align:center}.label{overflow:hidden;text-overflow:ellipsis}.viewport{position:relative;min-width:0;min-height:0;overflow:hidden;background:radial-gradient(ellipse at 50% 42%,#252d38 0%,#171b22 53%,#111419 100%)}canvas.gl{display:block;width:100%;height:100%}.viewtools{position:absolute;top:12px;right:12px;display:flex;gap:6px}.viewtools button{background:#191e26df;border:1px solid #3a4350;border-radius:6px;padding:7px 9px;color:inherit;font-size:10px;cursor:pointer}.hint{position:absolute;bottom:12px;left:50%;transform:translateX(-50%);background:#13171dd9;border:1px solid #333b47;color:#a8b2bf;padding:7px 10px;border-radius:6px;font-size:9px;white-space:nowrap;pointer-events:none}.drop{position:absolute;inset:10px;border:1px dashed var(--accent);border-radius:10px;background:#183126aa;display:none;place-items:center;color:#d1ffe7;pointer-events:none}.viewport.drag .drop{display:grid}.summary{padding:12px;border-bottom:1px solid var(--line)}.stats{display:grid;grid-template-columns:1fr 1fr;gap:7px}.stat{background:#1e242c;border:1px solid #2b333e;border-radius:6px;padding:8px}.stat strong{display:block;font:600 16px ui-monospace,monospace}.stat small{display:block;color:var(--muted);font-size:9px;margin-top:3px}.details{padding:13px}.placeholder{font-size:11px;color:var(--muted);line-height:1.6}.detail-title{font-size:14px;font-weight:650;overflow-wrap:anywhere}.detail-type{font:10px ui-monospace,monospace;color:var(--accent);margin:5px 0 14px}.yaml{padding:11px;background:#11151a;border:1px solid #29303b;border-radius:6px;font:11px/1.8 ui-monospace,monospace;white-space:pre-wrap;color:#dce4ed}.section{font-size:9px;text-transform:uppercase;letter-spacing:.1em;color:#8390a0;margin:16px 0 6px}.kv{display:flex;justify-content:space-between;gap:8px;border-bottom:1px solid #262d36;padding:7px 0;font-size:10px}.kv span:first-child{color:#96a1af}.kv span:last-child{text-align:right;color:#dce4ed;font-family:ui-monospace,monospace;overflow-wrap:anywhere}.empty-tree{padding:12px;color:var(--muted);font-size:11px;line-height:1.6}
  @media(max-width:980px){.layout{grid-template-columns:240px minmax(300px,1fr)}.right{position:absolute;z-index:5;right:0;top:62px;bottom:0;width:275px;box-shadow:-12px 0 24px #0005}.brand{min-width:155px}.status{display:none}}
  @media(max-width:650px){.poc{height:100dvh;grid-template-rows:auto 1fr}.top{min-height:94px;flex-wrap:wrap;padding:9px 11px;gap:7px}.brand{flex:1}.load{order:2;flex-basis:100%}.load input{max-width:none}.layout{grid-template-columns:1fr;grid-template-rows:195px minmax(300px,1fr)}.left{border:0;border-bottom:1px solid var(--line)}.tree{display:flex;overflow:auto;gap:4px;padding:8px}.row{min-width:max-content}.row.env{padding-left:8px}.row.mesh{padding-left:8px}.right{top:94px;width:min(275px,86vw)}.hint{font-size:8px}}
</style>
<div class="poc">
  <header class="top">
    <div class="brand"><div class="mark">3D</div><div><strong id="pocProjectName">Empreendimento</strong><small id="pocModeLabel">Pavimentos → ambientes</small></div></div>
    <div class="load"><input id="pocUrl" value="./Terreo_Teste_002.glb" aria-label="Caminho do arquivo GLB"><button class="btn primary" id="pocLoad">Carregar</button><button class="btn" id="pocChoose">Abrir arquivo…</button><input id="pocFile" type="file" accept=".glb,.gltf,model/gltf-binary,model/gltf+json" hidden></div>
    <div class="status" id="pocStatus">Carregando…</div>
  </header>
  <main class="layout">
    <aside class="side left"><div class="head"><h2>Estrutura lógica</h2><span class="count" id="pocCount">—</span></div><div class="tree" id="pocTree"><div class="empty-tree">Lendo meshes no padrão Pxx_Axx_NOME…</div></div></aside>
    <section class="viewport" id="pocViewport"><div class="drop">Solte o arquivo GLB/GLTF aqui</div><div class="viewtools"><button id="pocFit">Enquadrar</button><button id="pocAxes">Eixos: ligados</button><button id="pocGrid">Grade: ligada</button></div><div class="hint">Clique no mesh ou na árvore · arraste para orbitar · roda para zoom</div></section>
    <aside class="side right"><div class="head"><h2>Inspeção do mesh</h2><span class="count" id="pocSelectedType">—</span></div><div class="summary"><div class="stats" id="pocStats"></div></div><div class="details" id="pocDetails"><div class="placeholder">Selecione um ambiente na árvore ou clique diretamente em um mesh válido no modelo.</div></div></aside>
  </main>
</div>`;

document.body.innerHTML = template;
const $ = (id) => document.getElementById(id);
const viewport = $("pocViewport");
const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
renderer.outputColorSpace = THREE.SRGBColorSpace;
renderer.domElement.className = "gl";
viewport.prepend(renderer.domElement);

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x171b22);
const camera = new THREE.PerspectiveCamera(45, 1, 0.01, 100000);
camera.position.set(5, 4, 7);
const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
scene.add(new THREE.HemisphereLight(0xdcecff, 0x39404c, 2));
const keyLight = new THREE.DirectionalLight(0xffffff, 2.6);
keyLight.position.set(5, 8, 6);
scene.add(keyLight);
const fillLight = new THREE.DirectionalLight(0xb4d1ff, 1.3);
fillLight.position.set(-6, 3, -4);
scene.add(fillLight);
const grid = new THREE.GridHelper(10, 20, 0x536171, 0x343d49);
grid.material.transparent = true;
grid.material.opacity = 0.45;
scene.add(grid);
const axes = new THREE.AxesHelper(1.5);
scene.add(axes);
const loader = new GLTFLoader();
const raycaster = new THREE.Raycaster();
const pointer = new THREE.Vector2();
const expression = /^P(\d+)_A(\d+)_(.+)$/;
let modelRoot = null;
let floors = [];
let meshIndex = new Map();
let selectedRecord = null;
let selectedMaterial = null;
let highlightHelper = null;
let dragStart = null;
let activeObjectUrl = null;
let activeFloor = null;
let originalVisibility = new Map();

function setStatus(text, error = false) {
  $("pocStatus").textContent = text;
  $("pocStatus").style.color = error ? "#ff9b9b" : "";
}
function parseMeshName(name) {
  const match = expression.exec((name || "").trim());
  if (!match) return null;
  return {
    floor: match[1],
    room: match[2],
    name: match[3],
    displayName: match[3].replace(/_/g, " "),
  };
}
function collectLogicalMeshes(root) {
  const floorMap = new Map();
  const valid = new Map();
  root.traverse((object) => {
    if (!object.isMesh) return;
    const parsed = parseMeshName(object.name);
    if (!parsed) return;
    const floorId = parsed.floor;
    const envId = parsed.room;
    const envKey = `${envId}:${parsed.name}`;
    const record = { object, ...parsed, floorId, envId, envKey };
    valid.set(object.uuid, record);
    if (!floorMap.has(floorId))
      floorMap.set(floorId, { id: floorId, environments: new Map() });
    const floor = floorMap.get(floorId);
    if (!floor.environments.has(envKey))
      floor.environments.set(envKey, {
        id: envId,
        name: parsed.name,
        displayName: parsed.displayName,
        meshes: [],
      });
    floor.environments.get(envKey).meshes.push(record);
  });
  const result = [...floorMap.values()]
    .sort((a, b) => Number(a.id) - Number(b.id))
    .map((floor) => ({
      ...floor,
      environments: [...floor.environments.values()]
        .sort((a, b) => Number(a.id) - Number(b.id))
        .map((env) => ({
          ...env,
          meshes: env.meshes.sort((a, b) =>
            a.object.name.localeCompare(b.object.name),
          ),
        })),
    }));
  return { floors: result, valid };
}
function formatFloor(value) {
  return value.padStart(2, "0");
}
function formatRoom(value) {
  return value.padStart(2, "0");
}
function setRowSelected(uuid) {
  document
    .querySelectorAll(".row.selected")
    .forEach((row) => row.classList.remove("selected"));
  if (uuid)
    document
      .querySelector(`.row[data-mesh-uuid="${CSS.escape(uuid)}"]`)
      ?.classList.add("selected");
}
function renderTree() {
  const tree = $("pocTree");
  tree.replaceChildren();
  const meshCount = [...meshIndex.values()].length;
  $("pocCount").textContent = `${floors.length} pav. · ${meshCount} meshes`;
  if (!meshCount) {
    const empty = document.createElement("div");
    empty.className = "empty-tree";
    empty.textContent = "Nenhum mesh segue o padrão Pxx_Axx_NOME.";
    tree.append(empty);
    return;
  }
  floors.forEach((floor) => {
    const floorRow = document.createElement("div");
    floorRow.className = "row floor";
    floorRow.innerHTML =
      '<span class="twisty">▾</span><span class="ico">▱</span>';
    const floorLabel = document.createElement("span");
    floorLabel.className = "label";
    floorLabel.textContent = `PAVIMENTO ${formatFloor(floor.id)}`;
    floorRow.append(floorLabel);
    tree.append(floorRow);
    floor.environments.forEach((env) => {
      const envRow = document.createElement("div");
      envRow.className = "row env";
      envRow.innerHTML = `<span class="twisty">${env.meshes.length > 1 ? "▾" : ""}</span><span class="ico">○</span>`;
      const label = document.createElement("span");
      label.className = "label";
      label.textContent = `A${formatRoom(env.id)} - ${env.name}`;
      envRow.append(label);
      if (env.meshes.length === 1) {
        envRow.dataset.meshUuid = env.meshes[0].object.uuid;
        envRow.addEventListener("click", () => selectMesh(env.meshes[0]));
      } else {
        envRow.title = `${env.meshes.length} meshes neste ambiente`;
        envRow.classList.add("floor");
        tree.append(envRow);
        env.meshes.forEach((record) => {
          const meshRow = document.createElement("div");
          meshRow.className = "row mesh";
          meshRow.dataset.meshUuid = record.object.uuid;
          meshRow.innerHTML =
            '<span class="twisty"></span><span class="ico">◈</span>';
          const meshLabel = document.createElement("span");
          meshLabel.className = "label";
          meshLabel.textContent = record.object.name;
          meshRow.append(meshLabel);
          meshRow.addEventListener("click", () => selectMesh(record));
          tree.append(meshRow);
        });
        return;
      }
      tree.append(envRow);
    });
  });
}
function updateStats(root) {
  let allMeshes = 0,
    triangles = 0;
  root.traverse((object) => {
    if (!object.isMesh) return;
    allMeshes++;
    const geometry = object.geometry;
    triangles += geometry?.index
      ? geometry.index.count / 3
      : (geometry?.attributes?.position?.count || 0) / 3;
  });
  const environmentCount = floors.reduce(
    (total, floor) => total + floor.environments.length,
    0,
  );
  const values = [
    floors.length,
    environmentCount,
    allMeshes,
    Math.round(triangles).toLocaleString("pt-BR"),
  ];
  const labels = [
    "Pavimentos lógicos",
    "Ambientes válidos",
    "Meshes totais",
    "Triângulos",
  ];
  $("pocStats").replaceChildren();
  values.forEach((value, i) => {
    const card = document.createElement("div");
    card.className = "stat";
    const strong = document.createElement("strong");
    const small = document.createElement("small");
    strong.textContent = value;
    small.textContent = labels[i];
    card.append(strong, small);
    $("pocStats").append(card);
  });
}
function clearHighlight() {
  if (selectedMaterial && selectedRecord) {
    selectedRecord.object.material = selectedMaterial;
    if (Array.isArray(selectedMaterial))
      selectedMaterial.forEach((material) => {
        if (material.userData.__pocClone) material.dispose();
      });
    else if (selectedMaterial.userData.__pocClone) selectedMaterial.dispose();
  }
  selectedMaterial = null;
  if (highlightHelper) scene.remove(highlightHelper);
  highlightHelper = null;
}
function applyHighlight(record) {
  clearHighlight();
  selectedRecord = record;
  const mesh = record.object;
  selectedMaterial = mesh.material;
  const originals = Array.isArray(mesh.material)
    ? mesh.material
    : [mesh.material];
  const highlighted = originals.map((material) => {
    const clone = material.clone();
    clone.userData.__pocClone = true;
    if (clone.emissive) {
      clone.emissive.set(0x20d79a);
      clone.emissiveIntensity = Math.max(clone.emissiveIntensity || 0, 0.8);
    } else if (clone.color) {
      clone.color.lerp(new THREE.Color(0x20d79a), 0.45);
    }
    return clone;
  });
  mesh.material = Array.isArray(selectedMaterial)
    ? highlighted
    : highlighted[0];
  highlightHelper = new THREE.BoxHelper(mesh, 0x91e5c0);
  highlightHelper.material.depthTest = false;
  highlightHelper.material.transparent = true;
  highlightHelper.material.opacity = 0.9;
  highlightHelper.renderOrder = 999;
  scene.add(highlightHelper);
}
function showDetails(record) {
  const { object, floor, room, name } = record;
  $("pocSelectedType").textContent = object.type;
  const details = $("pocDetails");
  details.replaceChildren();
  const title = document.createElement("div");
  title.className = "detail-title";
  title.textContent = object.name;
  details.append(title);
  const type = document.createElement("div");
  type.className = "detail-type";
  type.textContent = `${object.type} · ${object.uuid}`;
  details.append(type);
  const yaml = document.createElement("pre");
  yaml.className = "yaml";
  yaml.textContent = `Pavimento: ${formatFloor(floor)}\nAmbiente: ${formatRoom(room)}\nNome: ${name}\nMesh: ${object.name}`;
  details.append(yaml);
  const section = document.createElement("div");
  section.className = "section";
  section.textContent = "Diagnóstico do mesh";
  details.append(section);
  const addKv = (label, value) => {
    const row = document.createElement("div");
    row.className = "kv";
    const a = document.createElement("span"),
      b = document.createElement("span");
    a.textContent = label;
    b.textContent = value;
    row.append(a, b);
    details.append(row);
  };
  addKv("Pai no GLB", object.parent?.name || "(sem nome)");
  addKv("Filhos", String(object.children.length));
  addKv(
    "Vértices",
    String(object.geometry?.attributes?.position?.count ?? "—"),
  );
  addKv("userData", JSON.stringify(object.userData || {}));
}
function selectMesh(record) {
  if (!record) return;
  applyHighlight(record);
  setRowSelected(record.object.uuid);
  showDetails(record);
}
function frameModel(object) {
  if (!object) return;
  const box = new THREE.Box3().setFromObject(object);
  if (box.isEmpty()) return;
  const center = new THREE.Vector3();
  const size = new THREE.Vector3();
  box.getCenter(center);
  box.getSize(size);
  const radius = Math.max(size.x, size.y, size.z, 0.01);
  controls.target.copy(center);
  camera.near = Math.max(radius / 10000, 0.001);
  camera.far = Math.max(radius * 100, 100);
  camera.position
    .copy(center)
    .add(new THREE.Vector3(radius * 1.6, radius * 1.05, radius * 1.8));
  camera.updateProjectionMatrix();
  controls.update();
}
function install(gltf, label) {
  clearHighlight();
  if (modelRoot) scene.remove(modelRoot);
  modelRoot = gltf.scene;
  scene.add(modelRoot);
  const indexed = collectLogicalMeshes(modelRoot);
  floors = indexed.floors;
  meshIndex = indexed.valid;
  renderTree();
  updateStats(modelRoot);
  frameModel(modelRoot);
  $("pocDetails").innerHTML =
    '<div class="placeholder">Selecione um ambiente na árvore ou clique diretamente em um mesh válido no modelo.</div>';
  $("pocSelectedType").textContent = "—";
  setStatus(`${label} · ${meshIndex.size} mesh(es) no padrão`);
  console.table(
    [...meshIndex.values()].map((record) => ({
      pavimento: record.floor,
      ambiente: record.room,
      nome: record.name,
      mesh: record.object.name,
    })),
  );
}
function loadUrl(url) {
  const source = (url || "").trim();
  if (!source) return;
  setStatus("Carregando GLB…");
  loader.load(
    source,
    (gltf) => install(gltf, source.split("/").pop() || source),
    undefined,
    (error) => {
      console.error("Falha ao carregar GLB:", error);
      setStatus(error?.message || "Falha ao carregar GLB", true);
    },
  );
}
function loadFile(file) {
  if (!file) return;
  const objectUrl = URL.createObjectURL(file);
  setStatus("Carregando arquivo…");
  loader.load(
    objectUrl,
    (gltf) => {
      install(gltf, file.name);
      if (activeObjectUrl) URL.revokeObjectURL(activeObjectUrl);
      activeObjectUrl = objectUrl;
    },
    undefined,
    (error) => {
      URL.revokeObjectURL(objectUrl);
      console.error("Falha ao carregar arquivo:", error);
      setStatus(error?.message || "Falha ao abrir arquivo", true);
    },
  );
}
$("pocLoad").addEventListener("click", () => loadUrl($("pocUrl").value));
$("pocUrl").addEventListener("keydown", (event) => {
  if (event.key === "Enter") loadUrl($("pocUrl").value);
});
$("pocChoose").addEventListener("click", () => $("pocFile").click());
$("pocFile").addEventListener("change", (event) =>
  loadFile(event.target.files?.[0]),
);
$("pocFit").addEventListener("click", () => frameModel(modelRoot));
$("pocAxes").addEventListener("click", (event) => {
  axes.visible = !axes.visible;
  event.currentTarget.textContent = `Eixos: ${axes.visible ? "ligados" : "desligados"}`;
});
$("pocGrid").addEventListener("click", (event) => {
  grid.visible = !grid.visible;
  event.currentTarget.textContent = `Grade: ${grid.visible ? "ligada" : "desligada"}`;
});
renderer.domElement.addEventListener("pointerdown", (event) => {
  dragStart = { x: event.clientX, y: event.clientY, button: event.button };
});
renderer.domElement.addEventListener("pointerup", (event) => {
  if (
    !dragStart ||
    dragStart.button !== 0 ||
    Math.hypot(event.clientX - dragStart.x, event.clientY - dragStart.y) > 5
  ) {
    dragStart = null;
    return;
  }
  dragStart = null;
  const rect = renderer.domElement.getBoundingClientRect();
  pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
  pointer.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
  raycaster.setFromCamera(pointer, camera);
  const hits = raycaster.intersectObjects(modelRoot?.children || [], true);
  const match = hits.map((hit) => meshIndex.get(hit.object.uuid)).find(Boolean);
  if (match) selectMesh(match);
});
viewport.addEventListener("dragover", (event) => {
  event.preventDefault();
  viewport.classList.add("drag");
});
viewport.addEventListener("dragleave", (event) => {
  if (!viewport.contains(event.relatedTarget))
    viewport.classList.remove("drag");
});
viewport.addEventListener("drop", (event) => {
  event.preventDefault();
  viewport.classList.remove("drag");
  loadFile(
    [...event.dataTransfer.files].find((file) =>
      /\.gl(b|tf)$/i.test(file.name),
    ),
  );
});
new ResizeObserver(() => {
  const width = viewport.clientWidth,
    height = viewport.clientHeight;
  if (!width || !height) return;
  renderer.setSize(width, height, false);
  camera.aspect = width / height;
  camera.updateProjectionMatrix();
}).observe(viewport);
(function animate() {
  requestAnimationFrame(animate);
  controls.update();
  if (highlightHelper) highlightHelper.update();
  renderer.render(scene, camera);
})();
loadUrl(new URLSearchParams(location.search).get("src") || $("pocUrl").value);
