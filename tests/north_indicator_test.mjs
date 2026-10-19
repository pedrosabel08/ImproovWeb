import assert from "node:assert/strict";
import { pathToFileURL } from "node:url";
import { resolve } from "node:path";
import { loadViewerModule } from "./viewer_module_loader.mjs";
const modulePath = process.argv.find(arg => arg.startsWith("--three-module="))?.slice("--three-module=".length);
if (!modulePath) throw new Error("Use --three-module=CAMINHO/three.module.js (Three.js r180).");
const threeURL = pathToFileURL(resolve(modulePath)).href;
const THREE = await import(threeURL);
const { NorthIndicator } = await loadViewerModule("north-indicator.js", threeURL);
const { northDirection } = await loadViewerModule("north-direction.js", threeURL);
const originalDocument = globalThis.document;
const element = () => ({ style: {}, children: [], hidden: false, attributes: {},
  append(child) { this.children.push(child); },
  setAttribute(key, value) { this.attributes[key] = value; },
});
globalThis.document = { createElement: element };
try {
  const scene = new THREE.Scene(), camera = new THREE.PerspectiveCamera();
  const model = new THREE.Mesh(new THREE.BoxGeometry(10, 30, 8));
  model.position.set(15, 17, -9);
  const viewport = { ...element(), clientWidth: 1024, clientHeight: 768 };
  const north = new NorthIndicator(scene, camera, viewport, () => model);
  for (const degrees of [0, 83.5, 90, 180, 270]) {
    north.set(degrees);
    assert.equal(north.environment.visible, false, "A bússola do entorno começa oculta.");
    assert.equal(north.group.visible, false, "A seta técnica só aparece ao definir o Norte.");
    assert.equal(north.compass.hidden, true, "A orientação aparece apenas ao redor do modelo.");
    north.setEnvironmentVisible(true);
    assert.equal(north.environment.visible, true);
    for (const cardinal of north.environmentLabels) {
      const actual = cardinal.position.clone().sub(north.environment.position).normalize();
      const expected = northDirection(degrees + cardinal.angle);
      assert.ok(actual.distanceTo(new THREE.Vector3(expected.x, 0, expected.z)) < 1e-8);
    }
    const tickNorth = new THREE.Vector3(0, 0, -1).applyEuler(north.environment.rotation);
    const expected = northDirection(degrees);
    assert.ok(tickNorth.distanceTo(new THREE.Vector3(expected.x, 0, expected.z)) < 1e-8);
    north.setMapVisible(true);
    assert.equal(north.environment.visible, false);
    assert.equal(north.compass.hidden, true);
    north.setMapVisible(false);
    assert.equal(north.environment.visible, true, "Retornar do mapa preserva a opção.");
    north.set(degrees, true);
    assert.equal(north.environment.visible, false);
    assert.equal(north.group.visible, true);
    north.set(degrees, false);
    assert.equal(north.environment.visible, true);
    north.setEnvironmentVisible(false);
    assert.ok(north.environmentLabels.every(label => label.element.hidden));
  }
  north.set(null);
  north.setEnvironmentVisible(true);
  assert.equal(north.environment.visible, false, "Sem Norte salvo não há orientação inventada.");
  assert.equal(north.compass.hidden, true);
  console.log("OK: bússola opcional, N/L/S/O e marcas alinhados ao Norte salvo, HUD, definição do Norte e retorno do mapa.");
} finally {
  globalThis.document = originalDocument;
}
