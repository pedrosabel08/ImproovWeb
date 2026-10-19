import assert from 'node:assert/strict';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';
import { loadViewerModule } from './viewer_module_loader.mjs';
const path = process.argv.find(arg => arg.startsWith('--three-module='))?.slice('--three-module='.length);
if (!path) throw new Error('Use --three-module=CAMINHO/three.module.js (Three.js r180).');
const url = pathToFileURL(resolve(path)).href;
const THREE = await import(url);
const { measurementValue, measurementSegment, formatMeasurement, visibleSurface, projectMeasurementPoint } = await loadViewerModule('measurement-geometry.js', url);
const { ModelMeasurement } = await loadViewerModule('model-measurement.js', url);
const a = new THREE.Vector3(1, 2, 3), b = new THREE.Vector3(4, 6, 15);
assert.equal(measurementValue('distance', a, b), 13);
assert.equal(measurementValue('horizontal', a, b), Math.hypot(3, 12));
assert.equal(measurementValue('vertical', a, b), 4);
assert.equal(measurementValue('vertical', b, a), 4);
assert.equal(measurementValue('horizontal', a, new THREE.Vector3(1, 60, 3)), 0);
assert.equal(formatMeasurement(0.42), '0,42 m');
assert.equal(formatMeasurement(12.46), '12,46 m');
assert.deepEqual(measurementSegment('horizontal', a, b).end.toArray(), [4,2,15]);
assert.deepEqual(measurementSegment('vertical', a, b).end.toArray(), [1,6,3]);
assert.deepEqual(a.toArray(), [1,2,3], 'As projeções não alteram os pontos reais.');
assert.deepEqual(b.toArray(), [4,6,15]);

// Interseções reais em malha com translação, rotação e escala não uniforme no GLB.
const root = new THREE.Group(); root.position.set(10,20,30); root.rotation.y = Math.PI/2; root.scale.set(2,3,4);
const mesh = new THREE.Mesh(new THREE.BoxGeometry(2,2,2),new THREE.MeshBasicMaterial());
root.add(mesh); root.updateMatrixWorld(true);
const raycaster = new THREE.Raycaster(); raycaster.layers.enableAll();
raycaster.set(new THREE.Vector3(10,20,50),new THREE.Vector3(0,0,-1));
const hit = raycaster.intersectObjects([mesh],false).find(visibleSurface);
assert.ok(hit.point.distanceTo(new THREE.Vector3(10,20,32)) < 1e-10, 'O ponto deve ser a superfície em world space, não o centro.');
raycaster.set(new THREE.Vector3(10,50,30),new THREE.Vector3(0,-1,0));
const top = raycaster.intersectObjects([mesh],false).find(visibleSurface);
raycaster.set(new THREE.Vector3(10,-10,30),new THREE.Vector3(0,1,0));
const base = raycaster.intersectObjects([mesh],false).find(visibleSurface);
assert.equal(measurementValue('vertical',top.point,base.point),6, 'Escala do GLB aplicada uma única vez.');
root.visible = false; assert.equal(visibleSurface(hit),false); root.visible = true;
mesh.material.opacity=0; assert.equal(visibleSurface(hit),false); mesh.material.opacity=1;
mesh.material.clippingPlanes = [new THREE.Plane(new THREE.Vector3(0,0,-1),31)];
assert.equal(visibleSurface(hit),false,'Superfície cortada não pode interceptar a trena.');
mesh.material.clippingPlanes=[];

const camera = new THREE.OrthographicCamera(-10,10,10,-10,0.1,1000);
camera.position.set(0,0,30); camera.lookAt(0,0,0); camera.updateMatrixWorld(true);
let projection = projectMeasurementPoint(new THREE.Vector3(),camera,800,600);
assert.equal(projection.x,400); assert.equal(projection.y,300); assert.equal(projection.visible,true);
projection = projectMeasurementPoint(new THREE.Vector3(100,0,0),camera,800,600);
assert.equal(projection.visible,false);

// DOM mínimo para testar o controlador real e seus recursos Three.js, sem cópia da lógica.
class Element extends EventTarget {
  constructor(tag='div') { super(); this.tagName=tag; this.children=[]; this.nodes=new Map(); this.dataset={}; this.style={}; this.attributes={}; this.scrollTop=this.offsetHeight=0; this.value=tag==='select'?'distance':''; this.classList={toggle() {}}; }
  append(...children) { children.forEach(child=>{child.parent=this;this.children.push(child);}); }
  after() {}
  remove() { if(this.parent)this.parent.children=this.parent.children.filter(node=>node!==this); }
  replaceChildren(...children) { this.children=[];this.append(...children); }
  setAttribute(key,value) { this.attributes[key]=value; }
  querySelector(selector) { if(!this.nodes.has(selector))this.nodes.set(selector,new Element(selector==='select'?'select':'div'));return this.nodes.get(selector); }
  scrollIntoView() {}
  getBoundingClientRect() { return {top:0}; }
}
const originalDocument = globalThis.document;
const document = new EventTarget();
document.createElement = tag=>new Element(tag);
document.querySelector = ()=>new Element();
document.getElementById = ()=>new Element();
document.closest = ()=>null;
globalThis.document=document;
try {
  const scene=new THREE.Scene(),viewport=new Element(); viewport.clientWidth=800; viewport.clientHeight=600;
  let modes=[];
  const tool=new ModelMeasurement({scene,camera,viewport,pick:()=>({point:b}),onModeChange:active=>modes.push(active)});
  tool.begin();assert.equal(tool.active,false,'Sem modelo não há medição.');
  tool.setAvailable(true);tool.begin();tool.choose(a);
  tool.setAvailable(false);assert.equal(tool.active,true,'Uma espera temporária não pode devolver os cliques à navegação.');
  tool.setAvailable(true);
  assert.ok(tool.draft);assert.equal(tool.records.length,0);
  tool.move({clientX:10,clientY:20});tool.update();
  assert.equal(tool.draft.valor,13,'Prévia recalculada antes do segundo clique.');
  assert.equal(tool.draft.line.visible,true);
  tool.move(null);assert.equal(tool.draft.pointB,null);assert.equal(tool.draft.line.visible,false);
  tool.choose(b);assert.equal(tool.records.length,1);assert.equal(tool.records[0].valor,13);
  assert.notEqual(tool.records[0].pointA,a,'Não reter referência mutável ao resultado do Raycaster.');
  tool.type.value='vertical';tool.type.dispatchEvent(new Event('change'));
  assert.equal(tool.selected.valor,4);
  tool.begin();tool.type.value='horizontal';tool.choose(a);tool.choose(b);
  assert.equal(tool.records.length,2);assert.equal(new Set(tool.records.map(m=>m.id)).size,2);
  assert.equal(tool.records[0].tipo,'vertical');assert.equal(tool.records[1].tipo,'horizontal');
  tool.update();const initialX=tool.records[1].valueLabel.style.left;
  camera.position.set(30,20,30);camera.lookAt(0,0,0);camera.updateMatrixWorld(true);tool.update();
  assert.notEqual(tool.records[1].valueLabel.style.left,initialX,'Label acompanha rotação da câmera.');
  const anchor=tool.records[1].midpoint.clone();
  const beforePan=projectMeasurementPoint(anchor,camera,800,600);
  camera.position.add(new THREE.Vector3(4,0,0));camera.lookAt(4,0,0);camera.updateMatrixWorld(true);
  const afterPan=projectMeasurementPoint(anchor,camera,800,600);
  assert.notEqual(afterPan.x,beforePan.x,'Label acompanha pan da câmera.');
  camera.zoom=1.5;camera.updateProjectionMatrix();
  const afterZoom=projectMeasurementPoint(anchor,camera,800,600);
  assert.notEqual(afterZoom.x,afterPan.x,'Label acompanha zoom.');
  const afterResize=projectMeasurementPoint(anchor,camera,1600,1200);
  assert.equal(afterResize.x,afterZoom.x*2);assert.equal(afterResize.y,afterZoom.y*2);
  assert.equal(tool.records[1].valor,Math.hypot(3,12),'Câmera e resize não alteram o valor medido.');
  tool.begin();tool.choose(a);tool.exit();
  assert.equal(tool.draft,null);assert.equal(tool.records.length,2,'Sair descarta apenas a medição incompleta.');
  tool.select(tool.records[0]);tool.removeCurrent();assert.equal(tool.records.length,1);
  tool.setSuspended(true);assert.equal(tool.overlay.hidden,true);assert.equal(tool.active,false);
  tool.setSuspended(false);assert.equal(tool.overlay.hidden,false);
  tool.clearAll();assert.equal(tool.group.children.length,0);assert.equal(tool.overlay.children.length,0);
  tool.begin();tool.choose(a);tool.choose(b);tool.reset();
  assert.equal(tool.records.length,0);assert.equal(tool.available,false);
  assert.equal(tool.group.children.length,0);assert.equal(tool.overlay.children.length,0);
  assert.ok(modes.includes(true)&&modes.includes(false));
  console.log('OK: distância/horizontal/vertical, metros, world space com transforms, visibilidade/cortes, prévia, múltiplas medições, labels, seleção, limpeza, suspensão e troca de modelo.');
} finally { globalThis.document=originalDocument;mesh.geometry.dispose();mesh.material.dispose(); }
