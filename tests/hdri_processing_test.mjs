import assert from 'node:assert/strict';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';
import { processHdri } from '../MapaPavimento/predio-3d/hdri-processing.mjs';
import { ENVIRONMENT_SETTINGS as SETTINGS } from '../MapaPavimento/predio-3d/environment-config.mjs';
const modulePath = process.argv.find(arg => arg.startsWith('--three-module='))?.slice(15);
if (!modulePath) throw new Error('Informe --three-module=CAMINHO/three.module.js (r180).');
const { DataUtils } = await import(pathToFileURL(resolve(modulePath)).href);
const settings = { maxLuminance: SETTINGS.maxIBLLuminance, sky: SETTINGS.sky };
const fixture = (exposure = 1) => {
  const width = 64, height = 32, data = new Float32Array(width * height * 4);
  for (let y = 0; y < height; y++) for (let x = 0; x < width; x++) {
    const i = (y * width + x) * 4;
    data[i] = exposure * (y < height / 2 ? 1 : .1);
    data[i + 1] = exposure * .2;
    data[i + 2] = exposure * (y < height / 2 ? .1 : 1);
    data[i + 3] = 1;
  }
  return { width, height, data };
};
const baseline = processHdri(fixture(), settings, DataUtils);
assert.ok(baseline.hemisphere.sky[2] > baseline.hemisphere.sky[0] * 9);
assert.ok(baseline.hemisphere.ground[0] > baseline.hemisphere.ground[2] * 9);
const source = fixture();
const hotspot = (24 * source.width + 0) * 4;
source.data[hotspot] = source.data[hotspot + 1] = source.data[hotspot + 2] = 100000;
const rayTail = hotspot + 4;
source.data[rayTail] = source.data[rayTail + 1] = source.data[rayTail + 2] = 4;
const prepared = processHdri(source, settings, DataUtils);
assert.equal(prepared.removedHotspots, 1);
assert.ok(Math.abs(DataUtils.fromHalfFloat(prepared.pixels[hotspot + 2]) - 1) < .05,
  'Hotspot deve ser reconstruído com céu local, inclusive na emenda do panorama; não virar disco branco limitado.');
assert.ok(Math.abs(DataUtils.fromHalfFloat(prepared.pixels[rayTail + 2]) - 1) < .05,
  'Raios solares abaixo do threshold também devem desaparecer, sem deixar uma segunda estrela no céu.');
const darker = processHdri(fixture(.1), settings, DataUtils);
for (let c = 0; c < 3; c++)
  assert.ok(Math.abs(baseline.hemisphere.sky[c] / 10 - darker.hemisphere.sky[c]) < 1e-7);
const black = fixture(0);
black.data[0] = NaN; black.data[1] = Infinity; black.data[2] = -1;
const safe = processHdri(black, settings, DataUtils);
assert.equal(safe.skyAverage, SETTINGS.sky.luminanceFloor);
assert.ok([...safe.pixels].every(value => Number.isFinite(DataUtils.fromHalfFloat(value))));
console.log('OK: orientação EXR dos hemisférios, médias lineares, hotspot reconstruído, exposição e pixels inválidos.');
