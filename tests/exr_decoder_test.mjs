import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';
import { ENVIRONMENTS, ENVIRONMENT_SETTINGS } from '../MapaPavimento/predio-3d/environment-config.mjs';
import { processHdri } from '../MapaPavimento/predio-3d/hdri-processing.mjs';

const modulePath = process.argv.find(arg => arg.startsWith('--three-module='))?.slice(15);
if (!modulePath) throw new Error('Informe --three-module=CAMINHO/three.module.js (r180).');
const threeURL = pathToFileURL(resolve(modulePath)).href;
const THREE = await import(threeURL);
const asModule = source => 'data:text/javascript;base64,' + Buffer.from(source).toString('base64');
const fflate = asModule(readFileSync(new URL('../assets/libs/three/libs/fflate.module.js', import.meta.url), 'utf8'));
const loaderSource = readFileSync(new URL('../assets/libs/three/loaders/EXRLoader.js', import.meta.url), 'utf8')
  .replace("from 'https://cdn.jsdelivr.net/npm/three@0.180.0/build/three.module.js'", 'from ' + JSON.stringify(threeURL))
  .replace("from '../libs/fflate.module.js'", 'from ' + JSON.stringify(fflate));
const { EXRLoader } = await import(asModule(loaderSource));
// Executa o MESMO handler do worker, com self capturado; dispensa DOM/WebGL.
let reply;
const self = { postMessage(value) { reply = value; } };
const workerSource = readFileSync(new URL('../MapaPavimento/predio-3d/exr-decoder-worker.js', import.meta.url), 'utf8')
  .replace(/^import\b[\s\S]*?;\s*/gm, '');
new Function('EXRLoader', 'DataUtils', 'FloatType', 'HalfFloatType', 'self', 'processHdri', workerSource)(
  EXRLoader, THREE.DataUtils, THREE.FloatType, THREE.HalfFloatType, self, processHdri,
);
for (const [id, preset] of Object.entries(ENVIRONMENTS)) {
  const bytes = readFileSync(new URL('../assets/hdri/' + preset.file, import.meta.url));
  const buffer = bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength);
  self.onmessage({ data: { id, buffer, maxLuminance: ENVIRONMENT_SETTINGS.maxIBLLuminance, sky: ENVIRONMENT_SETTINGS.sky } });
  assert.equal(reply.id, id);
  assert.equal(reply.error, undefined);
  const { data, width, height, type } = reply.result;
  assert.equal(width, 1024); assert.equal(height, 512);
  assert.equal(type, THREE.HalfFloatType);
  assert.equal(data.length, width * height * 4);
  assert.ok(reply.result.hemisphere.sky.every(Number.isFinite));
  assert.ok(reply.result.hemisphere.ground.every(Number.isFinite));
  assert.ok(reply.result.skyAverage >= ENVIRONMENT_SETTINGS.sky.luminanceFloor);
  let maximum = 0;
  for (let i = 0; i < data.length; i += 4) {
    const r = THREE.DataUtils.fromHalfFloat(data[i]);
    const g = THREE.DataUtils.fromHalfFloat(data[i + 1]);
    const b = THREE.DataUtils.fromHalfFloat(data[i + 2]);
    assert.ok(Number.isFinite(r + g + b) && Math.min(r, g, b) >= 0);
    maximum = Math.max(maximum, r * .2126 + g * .7152 + b * .0722);
  }
  assert.ok(maximum <= ENVIRONMENT_SETTINGS.maxIBLLuminance + .01, 'Hotspot excedeu o limite após conversão para half-float.');
  console.log('OK: ' + preset.file + ' → RGBA half-float finito, 1024×512, luminância máxima ' + maximum.toFixed(2) + ', média céu ' + reply.result.skyAverage.toFixed(4));
}
self.onmessage({ data: { id: 'invalid', buffer: new ArrayBuffer(2), maxLuminance: 12 } });
assert.equal(reply.id, 'invalid');
assert.ok(reply.error && !reply.result, 'EXR inválido deve produzir erro recuperável.');
