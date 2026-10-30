import assert from 'node:assert/strict';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';
import { loadViewerModule } from './viewer_module_loader.mjs';
const modulePath = process.argv.find(a => a.startsWith('--three-module='))?.slice(15);
if (!modulePath) throw new Error('Informe --three-module=CAMINHO/three.module.js (r180).');
const url = pathToFileURL(resolve(modulePath)).href;
const THREE = await import(url);
const { skyDirection, horizonVisible } = await loadViewerModule('sky-projection.js', url);
const { createHdriSky } = await loadViewerModule('hdri-sky.js', url);
const { createProceduralAtmosphere } = await loadViewerModule('procedural-atmosphere.js', url);
const radius = 1000;
const ortho = new THREE.OrthographicCamera(-160, 160, 100, -100, 0.1, 10000);
function pose(camera, pitch, yaw = 0) {
  camera.position.set(0, 40, 0);
  const p = THREE.MathUtils.degToRad(pitch), y = THREE.MathUtils.degToRad(yaw);
  camera.lookAt(camera.position.clone().add(new THREE.Vector3(Math.sin(y) * Math.cos(p), Math.sin(p), -Math.cos(y) * Math.cos(p))));
  camera.updateProjectionMatrix(); camera.updateMatrixWorld(true);
}
pose(ortho, 0);
assert.ok(horizonVisible(ortho, radius));
// Projeta o equador real, depois recupera sua direção pela mesma interseção do shader.
let checked = 0;
for (const pitch of [-70, -10, 0, 10, 70]) for (const yaw of [0, 85, 180, 270]) {
  pose(ortho, pitch, yaw);
  for (const zoom of [0.5, 1, 3]) {
    ortho.zoom = zoom; ortho.updateProjectionMatrix();
    const cameraDir = ortho.getWorldDirection(new THREE.Vector3());
    for (let a = 0; a < 360; a += 2) {
      const angle = THREE.MathUtils.degToRad(a);
      const point = new THREE.Vector3(radius * Math.cos(angle), 0, radius * Math.sin(angle));
      if (point.clone().sub(ortho.position).dot(cameraDir) <= 0) continue;
      const screen = point.clone().project(ortho);
      if (Math.abs(screen.x) > 1 || Math.abs(screen.y) > 1) continue;
      assert.ok(Math.abs(skyDirection(ortho, screen.x, screen.y, radius).y) < 1e-10);
      assert.ok(horizonVisible(ortho, radius)); checked++;
    }
  }
}
assert.ok(checked > 20);
ortho.zoom = 1;
pose(ortho, -70); assert.equal(horizonVisible(ortho, radius), false);
pose(ortho, 70); assert.equal(horizonVisible(ortho, radius), false);
pose(ortho, -10); const down = new THREE.Vector3(0, 0, -radius).project(ortho).y;
pose(ortho, 10); const up = new THREE.Vector3(0, 0, -radius).project(ortho).y;
assert.ok(down > up, 'Olhar para baixo deve levar o horizonte para cima.');
pose(ortho, 0, 90); assert.ok(skyDirection(ortho, 0, 0, radius).x > .99);
pose(ortho, 0, 0); assert.ok(skyDirection(ortho, 0, 0, radius).z < -.99);
// Assimetria, roll e translação entram pela matriz real, sem acoplamento ao prédio.
ortho.left = -100; ortho.right = 300; ortho.position.set(70, 80, 20);
ortho.rotateZ(.3); ortho.updateProjectionMatrix(); ortho.updateMatrixWorld(true);
assert.ok(horizonVisible(ortho, radius));
const perspective = new THREE.PerspectiveCamera(50, 1.6, .1, 10000);
pose(perspective, 0); assert.ok(horizonVisible(perspective, radius));
pose(perspective, -80); assert.equal(horizonVisible(perspective, radius), false);
const scene = new THREE.Scene(), sky = createHdriSky(scene);
sky.update();
const a = new THREE.Texture(), b = new THREE.Texture();
sky.setEnvironment(a, { rotation: 0, horizonOffset: 0 });
sky.setEnvironment(b, { rotation: 30, horizonOffset: 2 });
assert.equal(sky.uniforms.hdriPrevious.value, a);
assert.equal(sky.uniforms.hdriCurrent.value, b);
sky.tick(performance.now() + 1000); assert.equal(sky.uniforms.hdriBlend.value, 1);
const c = new THREE.Texture();
sky.setEnvironment(a, { rotation: 0, horizonOffset: 0 });
sky.tick(performance.now() + 200);
const blendBeforeQueue = sky.uniforms.hdriBlend.value;
sky.setEnvironment(c, { rotation: 90, horizonOffset: 0, exposure: .025 });
assert.equal(sky.uniforms.hdriCurrent.value, a, 'Troca rápida deve terminar o blend corrente, sem salto de textura.');
assert.equal(sky.uniforms.hdriBlend.value, blendBeforeQueue);
sky.tick(performance.now() + 1000);
assert.equal(sky.uniforms.hdriCurrent.value, c);
assert.equal(sky.uniforms.currentExposure.value, .025);
sky.setEnvironment(b, { rotation: 0, horizonOffset: 0 });
sky.setEnvironment(c, { rotation: 90, horizonOffset: 0 });
sky.tick(performance.now() + 1000);
assert.equal(sky.uniforms.hdriCurrent.value, c, 'Voltar ao destino em blend deve cancelar destinos pendentes.');
sky.showHorizon(true); assert.equal(sky.uniforms.showHorizon.value, true);
assert.ok(!sky.mesh.material.fragmentShader.includes('atmosphere('));
const atmosphere = createProceduralAtmosphere();
atmosphere.update({ altitude: 50, direction: new THREE.Vector3(0, 1, 0) });
const colors = atmosphere.hemisphereColors();
assert.ok([colors.sky, colors.ground].every(c => [c.r, c.g, c.b].every(Number.isFinite)));
assert.ok(!atmosphere.probeScene.children[0].material.fragmentShader.includes('sampler2D'));
sky.hide(); assert.equal(sky.mesh.visible, false);
sky.dispose(); atmosphere.dispose(); assert.equal(scene.children.length, 0);
console.log('OK: horizonte mundial, raios ortográficos/perspectivos, pitch/órbita/zoom/roll/pan, crossfade e isolamento visual/GI. Casos de equador: ' + checked);
