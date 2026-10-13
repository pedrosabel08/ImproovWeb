import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { EmpreendimentoMapBridge } from './empreendimento-map-bridge.js';

document.body.innerHTML = `
<style>
  :root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#e8edf4;background:#101318;color-scheme:dark;--muted:#8590a1;--line:#29303b;--panel:#171b22;--accent:#91e5c0;--blue:#94bfff}
  *{box-sizing:border-box}body{margin:0;min-height:100vh}button,input{font:inherit}.app{height:100vh;min-height:560px;display:grid;grid-template-rows:62px 1fr}.top{display:flex;align-items:center;gap:14px;padding:0 18px;border-bottom:1px solid var(--line);background:#14171d}.brand{display:flex;align-items:center;gap:10px;min-width:195px}.mark{width:32px;height:32px;border-radius:9px;background:#263e37;color:var(--accent);display:grid;place-items:center;font-weight:800}.brand strong{font-size:13px}.brand small{display:block;color:var(--muted);font-size:10px;margin-top:2px}.load{display:flex;gap:7px;align-items:center;flex:1;min-width:0}.load input{max-width:440px;flex:1;min-width:100px;background:#0f1217;border:1px solid var(--line);border-radius:7px;color:#dfe7f1;padding:9px 10px;font-size:12px}.btn{background:#222832;border:1px solid #343d49;padding:8px 11px;border-radius:7px;color:inherit;font-size:11px;cursor:pointer}.btn:hover{background:#2b3440}.btn.primary{background:#a1edca;border-color:#a1edca;color:#13241c;font-weight:700}.status{font-size:10px;color:var(--muted);white-space:nowrap}.layout{display:grid;grid-template-columns:290px minmax(350px,1fr) 290px;min-height:0}.side{background:var(--panel);min-height:0;overflow:auto}.left{border-right:1px solid var(--line)}.right{border-left:1px solid var(--line)}.head{height:48px;padding:0 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--line);position:sticky;top:0;background:var(--panel);z-index:2}.head h2{margin:0;color:#aeb8c6;font-size:10px;text-transform:uppercase;letter-spacing:.09em}.count{font:10px ui-monospace,monospace;color:var(--muted)}.tree{padding:10px 8px 24px}.row{display:flex;align-items:center;gap:7px;min-height:32px;padding:5px 8px;border-radius:5px;font-size:12px;white-space:nowrap;overflow:hidden;color:#cbd3df;cursor:pointer}.row:hover{background:#222832}.row.selected{background:#283e38;color:#b8f1d4}.row.floor{font-weight:700;color:#dfe7ef}.row.floor.active{background:#233b34;color:var(--accent);cursor:default}.row.env{padding-left:25px}.row.back{color:var(--accent);border-bottom:1px solid var(--line);border-radius:0;margin-bottom:7px}.twisty{width:12px;color:#7d8b9a;font-size:10px;text-align:center}.ico{width:14px;color:var(--blue);text-align:center}.label{overflow:hidden;text-overflow:ellipsis}.viewport{position:relative;min-width:0;min-height:0;overflow:hidden;background:radial-gradient(ellipse at 50% 42%,#252d38 0%,#171b22 53%,#111419 100%)}canvas.gl{display:block;width:100%;height:100%}.viewtools{position:absolute;top:12px;right:12px;display:flex;gap:6px}.viewtools button{background:#191e26df;border:1px solid #3a4350;border-radius:6px;padding:7px 9px;color:inherit;font-size:10px;cursor:pointer}.hint{position:absolute;bottom:12px;left:50%;transform:translateX(-50%);background:#13171dd9;border:1px solid #333b47;color:#a8b2bf;padding:7px 10px;border-radius:6px;font-size:9px;white-space:nowrap;pointer-events:none}.drop{position:absolute;inset:10px;border:1px dashed var(--accent);border-radius:10px;background:#183126aa;display:none;place-items:center;color:#d1ffe7;pointer-events:none}.viewport.drag .drop{display:grid}.summary{padding:12px;border-bottom:1px solid var(--line)}.stats{display:grid;grid-template-columns:1fr 1fr;gap:7px}.stat{background:#1e242c;border:1px solid #2b333e;border-radius:6px;padding:8px}.stat strong{display:block;font:600 16px ui-monospace,monospace}.stat small{display:block;color:var(--muted);font-size:9px;margin-top:3px}.details{padding:13px}.placeholder{font-size:11px;color:var(--muted);line-height:1.6}.detail-title{font-size:14px;font-weight:650;overflow-wrap:anywhere}.detail-type{font:10px ui-monospace,monospace;color:var(--accent);margin:5px 0 14px}.yaml{padding:11px;background:#11151a;border:1px solid #29303b;border-radius:6px;font:11px/1.8 ui-monospace,monospace;white-space:pre-wrap;color:#dce4ed}.section{font-size:9px;text-transform:uppercase;letter-spacing:.1em;color:#8390a0;margin:16px 0 6px}.kv{display:flex;justify-content:space-between;gap:8px;border-bottom:1px solid #262d36;padding:7px 0;font-size:10px}.kv span:first-child{color:#96a1af}.kv span:last-child{text-align:right;color:#dce4ed;font-family:ui-monospace,monospace;overflow-wrap:anywhere}.empty-tree{padding:12px;color:var(--muted);font-size:11px;line-height:1.6}
  .floor-switcher{position:absolute;top:12px;left:50%;transform:translateX(-50%);display:flex;gap:4px;padding:4px;border:1px solid #3a4350;border-radius:9px;background:#141a22ed;z-index:3;max-width:calc(100% - 24px);overflow:auto}.floor-switcher button{border:1px solid transparent;background:transparent;color:#a9b6c6;border-radius:5px;padding:7px 12px;font:600 11px ui-monospace,monospace;cursor:pointer}.floor-switcher button:hover{background:#29343e;color:#e8edf4}.floor-switcher button[aria-pressed="true"]{background:#a1edca;color:#13241c}.hover-tooltip{position:absolute;z-index:10;pointer-events:none;max-width:240px;padding:10px 13px;border:1px solid #496259;border-radius:8px;background:#101916f2;box-shadow:0 5px 20px #0005;color:#e8f7ef}.hover-tooltip strong{display:block;font-size:12px;font-weight:650}.hover-tooltip span{display:block;font:12px ui-monospace,monospace;color:#b7e7d1;margin-top:5px}.hover-tooltip small{display:block;font-size:9px;color:#90a49a;margin-top:5px}.floor-switcher[hidden],.hover-tooltip[hidden]{display:none}.row:not(.back){cursor:default}
  @media(max-width:1200px){.viewtools{top:58px}}
  @media(max-width:980px){.layout{grid-template-columns:200px minmax(240px,1fr) 220px}.brand{min-width:155px}.status{display:none}}
  @media(max-width:650px){.app{height:100dvh;grid-template-rows:auto 1fr}.top{min-height:94px;flex-wrap:wrap;padding:9px 11px;gap:7px}.brand{flex:1}.load{order:2;flex-basis:100%}.load input{max-width:none}.layout{grid-template-columns:1fr;grid-template-rows:auto minmax(360px,60vh) auto;overflow:auto}.left{border:0;border-bottom:1px solid var(--line);max-height:160px}.tree{display:flex;overflow:auto;gap:4px;padding:8px}.row{min-width:max-content}.row.env{padding-left:8px}.right{width:auto;border-left:0;border-top:1px solid var(--line)}.hint{font-size:8px;white-space:normal;text-align:center;width:max-content;max-width:90%}}
</style>
<div class="app"><header class="top"><div class="brand"><div class="mark">3D</div><div><strong id="projectName">Empreendimento</strong><small id="modeLabel">Selecione um pavimento</small></div></div>
<button class="btn" id="backToProject" hidden>Voltar ao empreendimento</button><div class="load"><input id="modelUrl" value="./PAVTO_TIPO_01.glb" aria-label="Caminho do arquivo GLB"><button class="btn primary" id="loadUrl">Carregar</button><button class="btn" id="chooseFile">Abrir arquivo…</button><input id="modelFile" type="file" accept=".glb,.gltf,model/gltf-binary,model/gltf+json" hidden></div><div class="status" id="status">Carregando…</div></header>
<main class="layout"><aside class="side left"><div class="head"><h2 id="treeTitle">Pavimentos</h2><span class="count" id="treeCount">—</span></div><div class="tree" id="tree"><div class="empty-tree">Carregando empreendimento…</div></div></aside>
<section class="viewport" id="viewport"><div class="drop">Solte o arquivo GLB/GLTF aqui</div><nav class="floor-switcher" id="floorSwitcher" aria-label="Trocar pavimento" hidden></nav><div class="viewtools"><button id="fit">Enquadrar</button><button id="axes">Eixos: ligados</button><button id="grid">Grade: ligada</button></div><div class="hover-tooltip" id="hoverTooltip" role="tooltip" hidden><strong id="hoverName"></strong><span id="hoverDimensions"></span><small>Dimensões aproximadas</small></div><div class="hint" id="hint">Clique em um pavimento para entrar na planta de topo</div></section>
<aside class="side right"><div class="head"><h2>Inspeção</h2><span class="count" id="selectedType">—</span></div><div class="summary"><div class="stats" id="stats"></div></div><div class="details" id="details"><div class="placeholder">Selecione um objeto da cena para inspecionar.</div></div></aside></main></div>`;

const $ = id => document.getElementById(id);
const viewport = $('viewport');
const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
renderer.setPixelRatio(Math.min(devicePixelRatio || 1, 2));
renderer.outputColorSpace = THREE.SRGBColorSpace;
renderer.shadowMap.enabled = true;
renderer.shadowMap.type = THREE.PCFSoftShadowMap;
renderer.toneMapping = THREE.ACESFilmicToneMapping;
renderer.localClippingEnabled = true;
renderer.domElement.className = 'gl';
viewport.prepend(renderer.domElement);
const scene = new THREE.Scene();
scene.background = new THREE.Color(0x171b22);
const camera = new THREE.OrthographicCamera(-10, 10, 10, -10, 0.01, 100000);
camera.position.set(10, 9, 12);
const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
scene.add(new THREE.HemisphereLight(0xe9f1ff, 0x7b827b, 1.65));
const light = new THREE.DirectionalLight(0xfff6e8, 2.25);
light.castShadow = true;
light.shadow.mapSize.set(2048, 2048);
light.shadow.bias = -.0001;
light.shadow.radius = 2;
scene.add(light, light.target);
const grid = new THREE.GridHelper(10, 20, 0x536171, 0x343d49); grid.material.transparent = true; grid.material.opacity = .45; scene.add(grid);
const axes = new THREE.AxesHelper(1.5); scene.add(axes);
const loader = new GLTFLoader(), raycaster = new THREE.Raycaster(), pointer = new THREE.Vector2();
const floorObjectPattern = /^(PAVTO_.+_[0-9]+)_([0-9]+)$/i;
// Only meshes with a recognized floor identifier use the picking layer.
const PICKING_LAYER = 1;
raycaster.layers.set(PICKING_LAYER);
let modelRoot = null, floors = [], allRecords = [], activeFloor = null;
let allFloorMeshes = [], activeFloorMeshes = [], activeContextMeshes = [];
let recordByObject = new Map(), unassignedContextMeshes = [];
let contextMaterials = new Map();
let originalVisibility = new Map(), selected = null, dragStart = null, currentObjectUrl = null;
let hoveredFloor = null, hoveredRecord = null, highlightMaterials = new Map(), highlightHelpers = [], transitioning = false, transitionToken = 0, fullCameraPose = null;
let mapBridge = null, focusedFloor = null, mapVisible = false, fullControlState = null;
const dimensionFormat = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function status(text, error = false) { $('status').textContent = text; $('status').style.color = error ? '#ff9b9b' : ''; }
function parseFloorObjectName(name) {
  const match = floorObjectPattern.exec((name || '').trim());
  return match ? { floor: match[1].toUpperCase(), objectNumber: match[2] } : null;
}
function buildIndex(root, sourceName = '') {
  const byFloor = new Map(), records = [], floorIds = new Set();
  let hasObjectNames = false;
  root.traverse(object => {
    const parsed = parseFloorObjectName(object.name);
    if (parsed && object.isMesh) { floorIds.add(parsed.floor); hasObjectNames = true; }
    else if (/^PAVTO_.+_[0-9]{1,3}$/i.test(object.name || '')) floorIds.add(object.name.trim().toUpperCase());
  });
  const addRecord = (object, floorId, objectNumber = '') => {
    if (!byFloor.has(floorId)) byFloor.set(floorId, { id: floorId, records: [], contextMeshes: [], bounds: new THREE.Box3() });
    const record = { object, floor: floorId, objectNumber, name: object.name };
    const floor = byFloor.get(floorId);
    floor.records.push(record);
    floor.bounds.union(new THREE.Box3().setFromObject(object, true));
    records.push(record);
  };
  root.traverse(object => {
    if (!object.isMesh) return;
    const parsed = parseFloorObjectName(object.name);
    if (parsed) {
      addRecord(object, parsed.floor, parsed.objectNumber);
      return;
    }
    const ownName = (object.name || '').trim().toUpperCase();
    if (floorIds.has(ownName)) {
      addRecord(object, ownName);
      return;
    }
    for (let ancestor = object.parent; ancestor && ancestor !== root; ancestor = ancestor.parent) {
      const ancestorName = (ancestor.name || '').trim().toUpperCase();
      if (floorIds.has(ancestorName)) {
        addRecord(object, ancestorName);
        break;
      }
    }
  });
  // A standalone floor file can use its filename as the identifier while its meshes
  // are being renamed to the shared-prefix convention for a whole-building GLB.
  if (!hasObjectNames) {
    const fileName = sourceName.split('?')[0].replace(/\\/g, '/').split('/').pop()?.replace(/\.(glb|gltf)$/i, '').trim().toUpperCase() || '';
    if (/^PAVTO_[A-Z0-9_]+$/.test(fileName)) { byFloor.clear(); records.length = 0; root.traverse(object => {
      if (object.isMesh) addRecord(object, fileName);
    }); }
  }
  const floors = [...byFloor.values()].sort((a, b) => a.bounds.min.y - b.bounds.min.y || a.id.localeCompare(b.id, 'pt-BR', { numeric: true }));
  return { floors, records };
}
function floorLabel(id) { return id.replace(/_/g, ' '); }
function contextIndex(root) {
  const descendantFloors = new Map();
  allRecords.forEach(record => {
    for (let ancestor=record.object.parent; ancestor; ancestor=ancestor.parent) {
      if (!descendantFloors.has(ancestor)) descendantFloors.set(ancestor, new Set());
      descendantFloors.get(ancestor).add(record.floor);
      if (ancestor===root) break;
    }
  });
  const byHeight = [...floors].sort((a,b)=>a.bounds.min.y-b.bounds.min.y);
  byHeight.forEach((floor,i) => {
    const next = byHeight[i+1];
    floor.minY = floor.bounds.min.y - .15;
    floor.maxY = (next ? Math.min(floor.bounds.max.y,next.bounds.min.y) : floor.bounds.max.y) + .01;
  });
  unassignedContextMeshes = [];
  root.traverse(object => {
    if (!object.isMesh || recordByObject.has(object)) return;
    object.layers.disable(PICKING_LAYER);
    let assigned = [], explicitFloor = false;
    for (let ancestor=object; ancestor && ancestor!==root; ancestor=ancestor.parent) {
      const floorId = (ancestor.name || '').trim().toUpperCase();
      if (floors.some(floor => floor.id === floorId)) {
        explicitFloor = true;
        assigned = floors.filter(floor=>floor.id===floorId);
        break;
      }
      const ids = descendantFloors.get(ancestor);
      if (ids?.size===1) {
        assigned = floors.filter(floor=>ids.has(floor.id));
        break;
      }
    }
    if (!assigned.length && !explicitFloor) {
      const bounds = new THREE.Box3().setFromObject(object, true);
      const centerY = (bounds.min.y+bounds.max.y)/2;
      const thin = bounds.max.y-bounds.min.y<.25;
      const nearestLevel = thin ? [...byHeight].sort((a,b)=>Math.abs(centerY-a.bounds.min.y)-Math.abs(centerY-b.bounds.min.y))[0] : null;
      const atLevel = nearestLevel && Math.abs(centerY-nearestLevel.bounds.min.y)<=.25;
      assigned = byHeight.filter((floor,i) => {
        const nextBase = byHeight[i+1]?.bounds.min.y ?? Infinity;
        const horizontal = bounds.max.x>=floor.bounds.min.x && bounds.min.x<=floor.bounds.max.x
          && bounds.max.z>=floor.bounds.min.z && bounds.min.z<=floor.bounds.max.z;
        const vertical = thin
          ? (atLevel ? floor===nearestLevel : centerY>=floor.minY && centerY<nextBase-.001 && centerY<=floor.maxY)
          : bounds.max.y>floor.bounds.min.y+.001 && bounds.min.y<floor.maxY-.01;
        return horizontal && vertical;
      });
    }
    assigned.forEach(floor=>floor.contextMeshes.push(object));
    if (!assigned.length) unassignedContextMeshes.push(object);
  });
}
function restoreContextMaterials() {
  for (const [mesh,original] of contextMaterials) {
    const clones = Array.isArray(mesh.material) ? mesh.material : [mesh.material];
    mesh.material = original;
    clones.forEach(material=>material.dispose());
  }
  contextMaterials.clear();
}
function clipFloorContext(floor) {
  // Shared architectural meshes may span multiple storeys. Clip them only at runtime.
  const planes = [new THREE.Plane(new THREE.Vector3(0,1,0),-floor.minY),
    new THREE.Plane(new THREE.Vector3(0,-1,0),floor.maxY)];
  activeContextMeshes.forEach(mesh=>{
    const original=mesh.material, materials=Array.isArray(original)?original:[original];
    contextMaterials.set(mesh,original);
    const clones=materials.map(material=>{
      const clone=material.clone();clone.clippingPlanes=[...(material.clippingPlanes||[]),...planes];
      clone.clipIntersection=false;clone.clipShadows=true;return clone;
    });
    mesh.material=Array.isArray(original)?clones:clones[0];
  });
}
function prettyName(name) {
  return name.replace(/_/g,' ').toLocaleLowerCase('pt-BR').replace(/^\p{L}/u,char=>char.toLocaleUpperCase('pt-BR'));
}
function hideTooltip() { $('hoverTooltip').hidden=true; }
function showTooltip(record,event) {
  if (!activeFloor || !record) { hideTooltip(); return; }
  const tooltip=$('hoverTooltip'), bounds=new THREE.Box3().setFromObject(record.object,true), size=bounds.getSize(new THREE.Vector3());
  $('hoverName').textContent=record.object.name;
  $('hoverDimensions').textContent=`${dimensionFormat.format(size.x)} × ${dimensionFormat.format(size.z)} m`;
  tooltip.hidden=false;
  const rect=viewport.getBoundingClientRect();
  // Keep the preferred cursor offset, clamping only when an edge would clip the tooltip.
  const x=Math.max(8,Math.min(event.clientX-rect.left+14,rect.width-tooltip.offsetWidth-8));
  const y=Math.max(8,Math.min(event.clientY-rect.top+14,rect.height-tooltip.offsetHeight-8));
  tooltip.style.left=`${x}px`;tooltip.style.top=`${y}px`;
}
function renderFloorSwitcher() {
  const nav=$('floorSwitcher');nav.replaceChildren();nav.hidden=!activeFloor;
  if (!activeFloor) return;
  floors.forEach(floor=>{
    const button=document.createElement('button');button.type='button';button.textContent=floorLabel(floor.id);
    button.setAttribute('aria-label',`Pavimento ${floorLabel(floor.id)}`);
    button.setAttribute('aria-pressed',String(floor.id===activeFloor.id));
    button.addEventListener('click',()=>activateFloor(floor));nav.append(button);
  });
}
function clearVisualHighlights() {
  for (const [object, material] of highlightMaterials) {
    const clones = Array.isArray(object.material) ? object.material : [object.material];
    object.material = material;
    clones.forEach(clone => { if (clone !== material && (!Array.isArray(material) || !material.includes(clone))) clone.dispose(); });
  }
  highlightMaterials.clear();
  highlightHelpers.forEach(helper => { scene.remove(helper); helper.geometry.dispose(); helper.material.dispose(); });
  highlightHelpers = [];
}
function refreshHighlights() {
  clearVisualHighlights();
  const targets = new Map();
  if (activeFloor) {
    if (selected) targets.set(selected.object,{record:selected,color:0x63d7ab});
    const hovered = hoveredRecord?.floor === activeFloor.id ? hoveredRecord : null;
    if (hovered && hovered!==selected) targets.set(hovered.object,{record:hovered,color:0x8bc4e8});
  } else if (hoveredFloor || focusedFloor) {
    const floor = hoveredFloor || focusedFloor;
    allRecords.filter(record => record.floor === floor.id).forEach(record=>targets.set(record.object,{record,color:0x63d7ab}));
  }
  targets.forEach(({record,color}) => {
    const object = record.object, original = object.material;
    highlightMaterials.set(object, original);
    const materials = Array.isArray(original) ? original : [original];
    const clones = materials.map(material => {
      const clone = material.clone();
      if (clone.color) clone.color.lerp(new THREE.Color(color), .35);
      if (clone.emissive) { clone.emissive.set(color); clone.emissiveIntensity = .16; }
      return clone;
    });
    object.material = Array.isArray(original) ? clones : clones[0];
    const helper = new THREE.BoxHelper(object, color);
    helper.material.depthTest = false; helper.material.transparent = true; helper.material.opacity = .9; helper.renderOrder = 999;
    scene.add(helper); highlightHelpers.push(helper);
  });
}
function clearSelection() {
  selected = null;
  clearVisualHighlights();
}
function selectRecord(record) {
  if (!activeFloor || !record || record.floor !== activeFloor.id) return;
  selected = record; hoveredRecord = record; refreshHighlights();
  renderTree(); showDetails(record);
}
function showDetails(record) {
  $('selectedType').textContent = record.object.type;
  const d = $('details'); d.replaceChildren();
  const name = document.createElement('div'); name.className = 'detail-title'; name.textContent = record.object.name; d.append(name);
  const type = document.createElement('div'); type.className = 'detail-type'; type.textContent = `${record.object.type} · ${record.object.uuid}`; d.append(type);
  const yaml = document.createElement('pre'); yaml.className = 'yaml';
  yaml.textContent = `Pavimento: ${record.floor}\nObjeto: ${record.objectNumber || record.object.name}\nMesh: ${record.object.name}`; d.append(yaml);
  const section = document.createElement('div'); section.className = 'section'; section.textContent = 'Mesh selecionado'; d.append(section);
  const kv = (label, value) => { const row = document.createElement('div'); row.className = 'kv'; const a=document.createElement('span'),b=document.createElement('span');a.textContent=label;b.textContent=value;row.append(a,b);d.append(row); };
  kv('Vértices', String(record.object.geometry?.attributes?.position?.count ?? '—'));
  kv('Pai no GLB', record.object.parent?.name || '(sem nome)');
}
function makeRow(label, cls, icon, onClick, uuid) {
  const row = document.createElement('div'); row.className = `row ${cls}`; if (uuid) row.dataset.meshUuid = uuid;
  row.innerHTML = `<span class="twisty"></span><span class="ico">${icon}</span>`;
  const text = document.createElement('span'); text.className='label'; text.textContent=label; row.append(text);
  if (onClick) {
    row.setAttribute('role','button');row.tabIndex=0;row.addEventListener('click',onClick);
    row.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();onClick();}});
  }
  return row;
}
function renderTree() {
  const tree = $('tree'); tree.replaceChildren();
  if (!activeFloor) {
    $('treeTitle').textContent = 'Pavimentos'; $('treeCount').textContent = `${floors.length} pav.`;
    floors.forEach(floor => {
      const link=mapBridge?.links.get(floor.id);
      const row = makeRow(`${floorLabel(floor.id)} · ${link?.subtipo_nome || floor.records.length+' objetos'}`, 'floor', '▱', ()=>activateFloor(floor));
      row.querySelector('.twisty').textContent = '›'; tree.append(row);
    });
    return;
  }
  $('treeTitle').textContent = 'Objetos do pavimento'; $('treeCount').textContent = `${activeFloor.records.length} objetos`;
  const back = makeRow(`‹ ${$('projectName').textContent}`,'back','',()=>leaveFloor()); tree.append(back);
  const title = makeRow(`${floorLabel(activeFloor.id)} · vista de topo`, 'floor active', '▱', null); tree.append(title);
  [...activeFloor.records].sort((a,b)=>Number(a.objectNumber)-Number(b.objectNumber)||a.object.name.localeCompare(b.object.name,'pt-BR',{numeric:true})).forEach(record => {
    const row = makeRow(record.object.name, 'env', '◈', ()=>{selectRecord(record);}, record.object.uuid);
    if (selected === record) row.classList.add('selected');
    tree.append(row);
  });
}
function updateStats(root) {
  let meshes=0, triangles=0;
  root.traverse(o => { if (!o.isMesh) return; meshes++; triangles += o.geometry?.index ? o.geometry.index.count/3 : (o.geometry?.attributes?.position?.count || 0)/3; });
  $('stats').replaceChildren();
  [['Pavimentos',floors.length],['Objetos identificados',allRecords.length],['Meshes',meshes],['Triângulos',Math.round(triangles).toLocaleString('pt-BR')]].forEach(([label,value])=>{
    const card=document.createElement('div');card.className='stat';const n=document.createElement('strong'),l=document.createElement('small');n.textContent=value;l.textContent=label;card.append(n,l);$('stats').append(card);
  });
}
function visibleFloorOnly(floor) {
  restoreContextMaterials();
  modelRoot.traverse(o => { o.visible = false; });
  activeFloorMeshes=floor.records.map(record=>record.object).filter(wasVisible);
  activeContextMeshes=mapBridge?[]:floor.contextMeshes.filter(wasVisible);
  [...activeFloorMeshes,...activeContextMeshes].forEach(object => {
    for (let parent=object;parent;parent=parent.parent) {
      parent.visible=true;if(parent===modelRoot)break;
    }
  });
  clipFloorContext(floor);
  viewport.dataset.pickableCount=String(activeFloorMeshes.length);
  viewport.dataset.contextCount=String(activeContextMeshes.length);
  viewport.dataset.activeFloor=floor.id;
  const box=floorBounds(floor);
  updateSun(box);grid.position.y=floor.bounds.min.y-.015;
}
function wasVisible(object) {
  for(let parent=object;parent;parent=parent.parent) {
    if(originalVisibility.get(parent)===false)return false;
    if(parent===modelRoot)break;
  }
  return true;
}
function updateSun(bounds) {
  if(bounds.isEmpty())return;
  const center=bounds.getCenter(new THREE.Vector3());
  const radius=Math.max(bounds.getSize(new THREE.Vector3()).length()/2,.5);
  light.position.copy(center).add(new THREE.Vector3(radius*.9,radius*2,radius*.65));
  light.target.position.copy(center);
  const shadowCamera=light.shadow.camera, extent=radius*1.2;
  shadowCamera.left=shadowCamera.bottom=-extent;shadowCamera.right=shadowCamera.top=extent;
  shadowCamera.near=.01;shadowCamera.far=radius*6;
  shadowCamera.updateProjectionMatrix();
  light.shadow.normalBias=radius*.0005;
  light.shadow.needsUpdate=true;
}
function applyCameraPose(pose) {
  const aspect = Math.max(viewport.clientWidth / Math.max(viewport.clientHeight,1), .1);
  camera.left = -pose.height*aspect/2; camera.right = pose.height*aspect/2;
  camera.top = pose.height/2; camera.bottom = -pose.height/2;
  camera.position.copy(pose.position); camera.up.copy(pose.up); controls.target.copy(pose.target);
  camera.zoom=pose.zoom??1;
  camera.lookAt(pose.target); camera.updateProjectionMatrix();camera.updateMatrixWorld();
}
function captureCameraPose() {
  return { position:camera.position.clone(), target:controls.target.clone(), up:camera.up.clone(), height:camera.top*2, zoom:camera.zoom, top:!controls.enableRotate };
}
function cameraPoseFor(bounds, top=false) {
  const size=new THREE.Vector3(),center=new THREE.Vector3();bounds.getSize(size);bounds.getCenter(center);
  const aspect=Math.max(viewport.clientWidth/Math.max(viewport.clientHeight,1),.1);
  const height=top
    ? Math.max(size.z,size.x/aspect,.1)*1.25
    : Math.max(size.length()*1.3/Math.min(1,aspect),.1);
  const position=top?new THREE.Vector3(center.x,center.y+height*2,center.z):center.clone().add(new THREE.Vector3(height*1.15,height*.85,height*1.25));
  return {position,target:center,up:top?new THREE.Vector3(0,0,-1):new THREE.Vector3(0,1,0),height,top};
}
function transitionCamera(destination,duration=650) {
  if(!destination)return Promise.resolve(false);
  if(matchMedia('(prefers-reduced-motion: reduce)').matches)duration=0;
  const token=++transitionToken,source=captureCameraPose(),started=performance.now();
  const fromPos=source.position.clone(),fromTarget=source.target.clone(),fromUp=source.up.clone();
  const toPos=destination.position.clone(),toTarget=destination.target.clone(),toUp=destination.up.clone();
  transitioning=true;controls.enabled=false;
  return new Promise(resolve=>{
  function step(now){
    if(token!==transitionToken){resolve(false);return;}
    const t=duration?Math.min((now-started)/duration,1):1,ease=t*t*(3-2*t);
    const pose={position:fromPos.clone().lerp(toPos,ease),target:fromTarget.clone().lerp(toTarget,ease),up:fromUp.clone().lerp(toUp,ease).normalize(),height:source.height+(destination.height-source.height)*ease,zoom:source.zoom+((destination.zoom??1)-source.zoom)*ease,top:destination.top};
    applyCameraPose(pose);
    if(t<1)requestAnimationFrame(step);
    else{
      transitioning=false;controls.enabled=!mapVisible;controls.enableRotate=!destination.top;controls.update();resolve(true);
      if(activeFloor)status(`Pavimento ${floorLabel(activeFloor.id)} · passe o mouse sobre um objeto`);
      else if(modelRoot)status(`${$('projectName').textContent} · passe o mouse sobre um pavimento`);
    }
  }
  requestAnimationFrame(step);
  });
}
function setCamera(bounds,top=false,animate=false) {
  const pose=cameraPoseFor(bounds,top);
  if(animate)transitionCamera(pose);
  else{++transitionToken;transitioning=false;applyCameraPose(pose);controls.enabled=true;controls.enableRotate=!top;controls.update();}
  return pose;
}
function fitFull() { if(!modelRoot)return;const box=new THREE.Box3().setFromObject(modelRoot,true);if(!box.isEmpty()){setCamera(box,false,false);fullCameraPose=captureCameraPose();updateSun(box);grid.position.y=box.min.y-.015;} }
function floorBounds(floor) {
  const box=floor.bounds.clone();
  if(mapBridge)return box;
  floor.contextMeshes.filter(wasVisible).forEach(mesh=>{
    const bounds=new THREE.Box3().setFromObject(mesh,true);
    bounds.min.y=Math.max(bounds.min.y,floor.minY);bounds.max.y=Math.min(bounds.max.y,floor.maxY);
    if(!bounds.isEmpty())box.union(bounds);
  });
  return box;
}
function fitFloor(floor,animate=true) { const box=floorBounds(floor);if(!box.isEmpty())setCamera(box,true,animate); }
function activateFloor(floor) { if(transitioning||mapBridge?.busy||mapVisible)return;return mapBridge ? mapBridge.activateFloor(floor) : enterFloor(floor); }
async function fadeOtherObjects(floor, from=1, to=0) {
  if(matchMedia('(prefers-reduced-motion: reduce)').matches)return;
  const keep=new Set(floor.records.map(record=>record.object)), originals=new Map(), clones=new Map();
  modelRoot.traverse(object=>{
    if(!object.isMesh||keep.has(object)||!wasVisible(object))return;
    const materials=Array.isArray(object.material)?object.material:[object.material];originals.set(object,object.material);
    const faded=materials.map(material=>{if(!clones.has(material)){const clone=material.clone();clone.transparent=true;clone.depthWrite=false;clones.set(material,clone);}return clones.get(material);});
    object.material=Array.isArray(object.material)?faded:faded[0];
  });
  const started=performance.now();
  try { await new Promise(resolve=>{function step(now){const t=Math.min((now-started)/400,1);for(const [original,clone] of clones)clone.opacity=original.opacity*(from+(to-from)*t);if(t<1)requestAnimationFrame(step);else resolve();}step(started);}); }
  finally { originals.forEach((material,object)=>{object.material=material;});clones.forEach(material=>material.dispose()); }
}
async function enterFloor(floor) {
  if(!modelRoot||activeFloor===floor)return;
  if(!activeFloor){const damping=controls.enableDamping;controls.enableDamping=false;controls.update();controls.enableDamping=damping;fullCameraPose=captureCameraPose();fullControlState={enableRotate:controls.enableRotate,enablePan:controls.enablePan,enableZoom:controls.enableZoom};}
  if(mapBridge){
    transitioning=true;controls.enabled=false;focusedFloor=floor;hoveredFloor=null;refreshHighlights();
    await fadeOtherObjects(floor);
  }
  hideTooltip();clearSelection();hoveredFloor=null;hoveredRecord=null;activeFloor=floor;
  focusedFloor=null;visibleFloorOnly(floor);renderTree();renderFloorSwitcher();
  $('backToProject').hidden=false;$('modeLabel').textContent=`Vista de topo · ${floorLabel(floor.id)}`;
  $('hint').textContent='Passe o mouse sobre os objetos para inspecionar; arraste para orbitar';
  $('details').innerHTML='<div class="placeholder">Pavimento isolado. Selecione uma peça na cena ou na lista de objetos para ver seus dados.</div>';$('selectedType').textContent='—';
  status(`Abrindo ${floorLabel(floor.id)}…`);
  await transitionCamera(cameraPoseFor(floorBounds(floor),true),900);
}
async function leaveFloor() {
  if(!activeFloor)return;hideTooltip();clearSelection();restoreContextMaterials();hoveredFloor=null;hoveredRecord=null;
  const floor=activeFloor;focusedFloor=null;
  modelRoot.traverse(o=>{o.visible=originalVisibility.get(o) ?? true;});activeFloor=null;activeFloorMeshes=[];activeContextMeshes=[];renderTree();renderFloorSwitcher();
  delete viewport.dataset.activeFloor;viewport.dataset.pickableCount=String(allFloorMeshes.length);viewport.dataset.contextCount='0';
  const box=new THREE.Box3().setFromObject(modelRoot,true);updateSun(box);grid.position.y=box.min.y-.015;
  $('backToProject').hidden=true;$('modeLabel').textContent='Empreendimento completo · passe o mouse para destacar pavimentos';
  $('hint').textContent='Passe o mouse sobre um pavimento e clique para entrar';
  $('details').innerHTML='<div class="placeholder">No modelo completo, o hover destaca os objetos agrupados por pavimento. Clique em um pavimento para abrir sua vista de topo.</div>';$('selectedType').textContent='—';
  status(`${allRecords.length} objetos identificados no empreendimento`);
  await Promise.all([transitionCamera(fullCameraPose,850),mapBridge?fadeOtherObjects(floor,0,1):Promise.resolve()]);
  if(fullControlState)Object.assign(controls,fullControlState);controls.enabled=true;
}
function clearModel() {
  ++transitionToken;transitioning=false;clearSelection();restoreContextMaterials();
  if(modelRoot){scene.remove(modelRoot);const materials=new Set(),geometries=new Set(),textures=new Set();modelRoot.traverse(object=>{if(object.geometry)geometries.add(object.geometry);if(object.material)(Array.isArray(object.material)?object.material:[object.material]).forEach(material=>materials.add(material));});materials.forEach(material=>{Object.values(material).forEach(value=>{if(value?.isTexture)textures.add(value);});material.dispose();});textures.forEach(texture=>texture.dispose());geometries.forEach(geometry=>geometry.dispose());}
  modelRoot=null;floors=[];allRecords=[];activeFloor=null;focusedFloor=null;hoveredFloor=null;hoveredRecord=null;allFloorMeshes=[];activeFloorMeshes=[];activeContextMeshes=[];recordByObject.clear();
  originalVisibility=new Map();unassignedContextMeshes=[];$('stats').replaceChildren();$('selectedType').textContent='—';
  $('backToProject').hidden=true;renderTree();renderFloorSwitcher();
}
function install(gltf, sourceName) {
  ++transitionToken;transitioning=false;controls.enabled=true;
  hideTooltip();clearModel();modelRoot=gltf.scene;scene.add(modelRoot);
  modelRoot.updateMatrixWorld(true);
  originalVisibility=new Map();modelRoot.traverse(o=>originalVisibility.set(o,o.visible));
  const indexed=buildIndex(modelRoot,sourceName);floors=indexed.floors;allRecords=indexed.records;activeFloor=null;
  recordByObject=new Map(allRecords.map(record=>[record.object,record]));
  allFloorMeshes=allRecords.map(record=>record.object).filter(wasVisible);
  activeFloorMeshes=[];activeContextMeshes=[];
  modelRoot.traverse(object=>{
    if(!object.isMesh)return;
    if(recordByObject.has(object))object.layers.enable(PICKING_LAYER);
    else object.layers.disable(PICKING_LAYER);
    object.receiveShadow=true;
    object.castShadow=!/(?:^|_)(?:PISO|LAJE|FLOOR|SLAB)(?:_|$)/i.test(object.name);
  });
  contextIndex(modelRoot);
  delete viewport.dataset.activeFloor;viewport.dataset.pickableCount=String(allFloorMeshes.length);viewport.dataset.contextCount='0';
  const project=sourceName.replace(/\.(glb|gltf)$/i,'');$('projectName').textContent=project;document.title=`${project} · Visualizador espacial`;
  renderTree();renderFloorSwitcher();updateStats(modelRoot);fitFull();
  $('backToProject').hidden=true;$('modeLabel').textContent='Empreendimento completo · passe o mouse para destacar pavimentos';$('hint').textContent='Passe o mouse sobre um pavimento e clique para entrar';
  $('details').innerHTML=floors.length
    ? '<div class="placeholder">No modelo completo, o hover destaca os objetos agrupados por pavimento. Clique em um pavimento para abrir sua vista de topo.</div>'
    : '<div class="placeholder">Nenhum pavimento identificado. Nomeie os objetos como PAVTO_TIPO_01_01, PAVTO_TIPO_01_02 e assim por diante.</div>';$('selectedType').textContent='—';
  status(floors.length ? `${floors.length} pavimento(s) · ${allRecords.length} objetos identificados` : 'Nenhum pavimento identificado',!floors.length);
  if(new URLSearchParams(location.search).has('debug')){
  console.groupCollapsed(`Diagnóstico GLB · ${sourceName}`);
  modelRoot.traverse(object=>console.log({name:object.name,type:object.type,uuid:object.uuid,parent:object.parent?.name,
    children:object.children.length,userData:object.userData}));
  console.table(allRecords.map(r=>({categoria:'PAVIMENTO',pavimento:r.floor,objeto:r.objectNumber||r.name,mesh:r.object.name})));
  console.table(floors.map(floor=>({pavimento:floor.id,objetos:floor.records.length,contexto:floor.contextMeshes.length})));
  if(unassignedContextMeshes.length)console.log('Contexto sem pavimento identificado:',unassignedContextMeshes.map(mesh=>mesh.name||mesh.uuid));
  console.groupEnd();
  }
}
async function loadUrl(url) { const src=(url||'').trim();if(!src)return;status('Carregando empreendimento…');const gltf=await loader.loadAsync(src);install(gltf,decodeURIComponent(src.split('/').pop().split('?')[0])||src); }
function loadFile(file) { if(!file||mapBridge)return;const url=URL.createObjectURL(file);status('Carregando arquivo…');loader.load(url,gltf=>{install(gltf,file.name);if(currentObjectUrl)URL.revokeObjectURL(currentObjectUrl);currentObjectUrl=url;},undefined,error=>{URL.revokeObjectURL(url);console.error(error);status(error?.message||'Falha ao abrir arquivo',true);}); }
const loadDiagnostic=()=>loadUrl($('modelUrl').value).catch(error=>{console.error(error);status(error.message,true);});
$('loadUrl').addEventListener('click',loadDiagnostic);$('modelUrl').addEventListener('keydown',e=>{if(e.key==='Enter')loadDiagnostic();});
$('chooseFile').addEventListener('click',()=>$('modelFile').click());$('modelFile').addEventListener('change',e=>loadFile(e.target.files?.[0]));
$('fit').addEventListener('click',()=>activeFloor?fitFloor(activeFloor,false):fitFull());$('backToProject').addEventListener('click',()=>mapBridge?.mapShown?mapBridge.requestReturn():leaveFloor());$('axes').addEventListener('click',e=>{axes.visible=!axes.visible;e.currentTarget.textContent=`Eixos: ${axes.visible?'ligados':'desligados'}`;});$('grid').addEventListener('click',e=>{grid.visible=!grid.visible;e.currentTarget.textContent=`Grade: ${grid.visible?'ligada':'desligada'}`;});
renderer.domElement.addEventListener('pointerdown',e=>{hideTooltip();dragStart={x:e.clientX,y:e.clientY,button:e.button};});
function pickRecord(event) {
  if(!modelRoot)return null;
  const rect=renderer.domElement.getBoundingClientRect();
  pointer.x=((event.clientX-rect.left)/rect.width)*2-1;pointer.y=-((event.clientY-rect.top)/rect.height)*2+1;
  raycaster.setFromCamera(pointer,camera);
  // Only identified floor objects are pickable; hidden floors and context cannot intercept clicks.
  const hits=activeFloor
    ? raycaster.intersectObjects(activeFloorMeshes,true)
    : raycaster.intersectObjects(allFloorMeshes,true);
  for(const hit of hits){
    const record=recordByObject.get(hit.object);
    if(record && wasVisible(hit.object) && (!activeFloor || record.floor===activeFloor.id))return record;
  }
  return null;
}
renderer.domElement.addEventListener('pointermove',event=>{
  if(transitioning||mapBridge?.busy||!modelRoot||event.buttons){hideTooltip();return;}
  const record=pickRecord(event);
  if(activeFloor){
    const next=record?.floor===activeFloor.id?record:null;
    if(next?.object.uuid!==hoveredRecord?.object.uuid){hoveredRecord=next;hoveredFloor=null;refreshHighlights();}
    showTooltip(next,event);
    status(next?`Objeto ${next.object.name} · clique para selecionar`:`Pavimento ${floorLabel(activeFloor.id)} · passe o mouse sobre um objeto`);
  }else{
    hideTooltip();
    const next=record?floors.find(floor=>floor.id===record.floor)||null:null;
    if(next?.id!==hoveredFloor?.id){hoveredFloor=next;hoveredRecord=null;refreshHighlights();}
    status(next?`Pavimento ${floorLabel(next.id)} · clique para entrar`:`${$('projectName').textContent} · passe o mouse sobre um pavimento`);
  }
});
renderer.domElement.addEventListener('pointerleave',()=>{
  hideTooltip();hoveredFloor=null;hoveredRecord=null;refreshHighlights();
  if(activeFloor)status(`Pavimento ${floorLabel(activeFloor.id)} · passe o mouse sobre um objeto`);
  else if(modelRoot)status(`${$('projectName').textContent} · passe o mouse sobre um pavimento`);
});
renderer.domElement.addEventListener('pointerup',e=>{
  if(transitioning||mapBridge?.busy||!dragStart||dragStart.button!==0||Math.hypot(e.clientX-dragStart.x,e.clientY-dragStart.y)>5){dragStart=null;return;}
  dragStart=null;const record=pickRecord(e);if(!record)return;
  if(!activeFloor){const floor=floors.find(item=>item.id===record.floor);if(floor)activateFloor(floor);}
  else if(record.floor===activeFloor.id){selectRecord(record);showTooltip(record,e);}
});
renderer.domElement.addEventListener('pointercancel',()=>{dragStart=null;hideTooltip();hoveredRecord=null;hoveredFloor=null;refreshHighlights();});
controls.addEventListener('start',()=>{hideTooltip();hoveredRecord=null;hoveredFloor=null;refreshHighlights();});
window.addEventListener('blur',()=>{hideTooltip();hoveredRecord=null;hoveredFloor=null;refreshHighlights();});
viewport.addEventListener('dragover',e=>{e.preventDefault();viewport.classList.add('drag');});viewport.addEventListener('dragleave',e=>{if(!viewport.contains(e.relatedTarget))viewport.classList.remove('drag');});viewport.addEventListener('drop',e=>{e.preventDefault();viewport.classList.remove('drag');loadFile([...e.dataTransfer.files].find(f=>/\.(glb|gltf)$/i.test(f.name)));});
new ResizeObserver(()=>{const w=viewport.clientWidth,h=viewport.clientHeight;if(!w||!h)return;hideTooltip();renderer.setSize(w,h,false);camera.left=-camera.top*(w/h);camera.right=camera.top*(w/h);camera.updateProjectionMatrix();}).observe(viewport);
(function animate(){requestAnimationFrame(animate);if(!mapVisible){if(!transitioning)controls.update();renderer.render(scene,camera);}})();
const params=new URLSearchParams(location.search);
if(params.has('src')&&!params.has('obra_id')){$('modelUrl').value=params.get('src');loadDiagnostic();}
else{
  mapBridge=new EmpreendimentoMapBridge({
    loadModel:loadUrl,clearModel,enterFloor,leaveFloor,status,refreshTree:renderTree,
    highlight:floor=>{focusedFloor=floor;hoveredFloor=null;refreshHighlights();},
    setMapVisible:value=>{mapVisible=value;controls.enabled=!value;}
  });
  mapBridge.start();
}
