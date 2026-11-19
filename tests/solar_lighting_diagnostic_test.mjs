import assert from "node:assert/strict";
import { resolve } from "node:path";
import { pathToFileURL } from "node:url";
import { loadViewerModule } from "./viewer_module_loader.mjs";

const modulePath = process.argv.find((arg) => arg.startsWith("--three-module="))?.slice(15);
if (!modulePath) throw new Error("Informe --three-module=CAMINHO/three.module.js (r180).");
const threeURL = pathToFileURL(resolve(modulePath)).href;
const THREE = await import(threeURL);
const { SolarLightingDiagnostic } = await loadViewerModule("solar-lighting-diagnostic.js", threeURL);

const scene = new THREE.Scene();
const ambient = new THREE.HemisphereLight(0xe9f1ff, 0x7b827b, 1.65);
const navigationFill = { sky: ambient.color.clone(), ground: ambient.groundColor.clone(), intensity: ambient.intensity };
const sun = new THREE.DirectionalLight(0xffbd8d, 2.38);
sun.castShadow = true;
const probe = new THREE.LightProbe(undefined, 0.2);
const fallback = new THREE.Texture();
const hdr = new THREE.Texture();
const material = new THREE.MeshStandardMaterial({ envMap: hdr });
const model = new THREE.Mesh(undefined, material);
scene.add(ambient, sun, probe, model);
ambient.color.set(0xb4bcc7);
ambient.groundColor.set(0xb6bcc1);
ambient.intensity = 0.06;
scene.environmentIntensity = 0;

const renderer = {};
const camera = new THREE.OrthographicCamera();
const environment = {
  state: "ready", nightGlass: {}, probe: { probe },
  fallbackTexture: fallback, lastEnvironmentInput: 0, lastSolarInput: 0,
};
const diagnostic = Object.assign(Object.create(SolarLightingDiagnostic.prototype), {
  renderer, scene, navigationFill, solar: { ambient, result: { altitude: 45 }, environment },
  button: {}, mode: {}, gray: { checked: false }, grayMaterials: new Map(),
});

for (const mode of ["A", "B", "C"]) {
  diagnostic.mode.value = mode;
  renderer.render = (actualScene, actualCamera) => {
    assert.equal(actualScene, scene);
    assert.equal(actualCamera, camera);
    assert.equal(ambient.intensity, mode === "A" ? 0.06 : 1.65);
    assert.equal(ambient.color.getHex(), mode === "A" ? 0xb4bcc7 : 0xe9f1ff);
    assert.equal(probe.visible, mode === "A");
    assert.equal(scene.environment, mode === "C" ? fallback : null);
    assert.equal(scene.environmentIntensity, mode === "C" ? 1 : 0);
    assert.equal(sun.intensity, 2.38);
    assert.equal(sun.castShadow, true);
    assert.equal(model.material, material);
    assert.equal(material.envMap, hdr);
  };
  diagnostic.render(camera, model, "teste.glb", { now: 1000 });
  assert.equal(ambient.intensity, 0.06);
  assert.equal(ambient.color.getHex(), 0xb4bcc7);
  assert.equal(probe.visible, true);
  assert.equal(scene.environment, null);
  assert.equal(scene.environmentIntensity, 0);
}

diagnostic.gray.checked = true;
renderer.render = () => {
  assert.notEqual(model.material, material);
  assert.equal(model.material.isMeshStandardMaterial, true);
  assert.equal(model.material.color.getHex(), 0xc8c8c8);
  throw new Error("falha de renderização");
};
assert.throws(() => diagnostic.render(camera, model, "teste.glb", { now: 1000 }), /falha de renderização/);
assert.equal(model.material, material);
assert.equal(scene.environment, null);
assert.equal(ambient.intensity, 0.06);
assert.equal(probe.visible, true);
console.log("OK: A/B/C, material cinza e restauração da cena mesmo quando o render falha.");
