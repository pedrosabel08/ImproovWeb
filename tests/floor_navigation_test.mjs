import assert from "node:assert/strict";
import { pathToFileURL } from "node:url";
import { resolve } from "node:path";
import { loadViewerModule } from "./viewer_module_loader.mjs";
const modulePath = process.argv.find(arg => arg.startsWith("--three-module="))?.slice("--three-module=".length);
if (!modulePath) throw new Error("Use --three-module=CAMINHO/three.module.js (Three.js r180).");
const threeURL = pathToFileURL(resolve(modulePath)).href;
const THREE = await import(threeURL);
const { floorOrbitPose } = await loadViewerModule("floor-navigation.js", threeURL);
const { boxCorners } = await loadViewerModule("solar-shadow.js", threeURL);
let cases = 0;
for (const scale of [0.01, 1, 100]) for (const aspect of [0.4, 1, 2.5])
for (const angle of [new THREE.Vector3(1, .6, 1), new THREE.Vector3(-2, .2, 1), new THREE.Vector3(0, 1, 0)]) {
  const bounds = new THREE.Box3(new THREE.Vector3(15, 80, -10).multiplyScalar(scale), new THREE.Vector3(55, 83, 20).multiplyScalar(scale));
  const camera = new THREE.OrthographicCamera(-10, 10, 10, -10, .001, 100000);
  const oldTarget = new THREE.Vector3(3, 2, -4);
  camera.position.copy(oldTarget).add(angle);
  const pose = floorOrbitPose(bounds, camera, oldTarget, aspect);
  assert.ok(pose.target.distanceTo(bounds.getCenter(new THREE.Vector3())) < 1e-8);
  assert.ok(pose.position.clone().sub(pose.target).normalize().distanceTo(angle.clone().normalize()) < 1e-8, "Mantém o ângulo escolhido pelo usuário.");
  assert.equal(pose.top, false);
  camera.position.copy(pose.position); camera.up.copy(pose.up);
  camera.top = pose.height / 2; camera.bottom = -camera.top;
  camera.left = -camera.top * aspect; camera.right = camera.top * aspect;
  camera.lookAt(pose.target); camera.updateMatrixWorld(); camera.updateProjectionMatrix();
  for (const corner of boxCorners(bounds)) {
    const screen = corner.project(camera);
    assert.ok(Math.abs(screen.x) < 1 && Math.abs(screen.y) < 1, "Enquadra todos os cantos do pavimento em diferentes proporções.");
  }
  cases++;
}
console.log(`OK: ${cases} enquadramentos de pavimentos, escalas, proporções e direções; centro e órbita preservados.`);
