import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
import { resolve, dirname } from 'node:path';
import { loadViewerModule } from './viewer_module_loader.mjs';
import * as config from '../MapaPavimento/predio-3d/environment-config.mjs';
const modulePath = process.argv.find(arg => arg.startsWith('--three-module='))?.slice(15);
if (!modulePath) throw new Error('Informe --three-module=CAMINHO/three.module.js (r180).');
const threeURL = pathToFileURL(resolve(modulePath)).href;
const actual = await import(threeURL);
const generatorPath = process.argv.find(arg => arg.startsWith('--light-probe-module='))?.slice('--light-probe-module='.length)
  || resolve(dirname(modulePath), '../examples/jsm/lights/LightProbeGenerator.js');
const { LightProbeGenerator } = await loadViewerModule(pathToFileURL(resolve(generatorPath)).href, threeURL);
const THREE = { ...actual, CubeCamera: class { update(renderer) { renderer.captures++; } } };
const source = readFileSync(new URL('../MapaPavimento/predio-3d/procedural-light-probe.js', import.meta.url), 'utf8');
const ProceduralLightProbe = new Function('THREE', 'LightProbeGenerator', 'SETTINGS', 'ENVIRONMENT_THRESHOLDS', 'smoothRange', 'nightFactor',
  source.slice(source.indexOf('class ProceduralLightProbe')) + '\nreturn ProceduralLightProbe;'
)(THREE, LightProbeGenerator, config.ENVIRONMENT_SETTINGS, config.ENVIRONMENT_THRESHOLDS, config.smoothRange, config.nightFactor);
const sky = { probeScene: new actual.Scene(), updateProbe(result) { this.result = result; } };
const scene = new actual.Scene();
const renderer = {
  captures: 0, isWebGLRenderer: true, coordinateSystem: actual.WebGLCoordinateSystem,
  async readRenderTargetPixelsAsync(target, x, y, width, height, pixels, face) {
    const day = config.smoothRange(-18, 30, sky.result.altitude);
    const value = 0.01 + day * (0.3 + (face === 0 ? sky.result.direction.x * .1 : 0));
    for (let i = 0; i < pixels.length; i += 4) {
      pixels[i] = actual.DataUtils.toHalfFloat(value);
      pixels[i + 1] = actual.DataUtils.toHalfFloat(value * .8);
      pixels[i + 2] = actual.DataUtils.toHalfFloat(value * .6);
      pixels[i + 3] = actual.DataUtils.toHalfFloat(1);
    }
  },
};
const probe = new ProceduralLightProbe(renderer, scene, sky);
const morning = { altitude: 30, azimuth: 90, direction: { x: .866, y: .5, z: 0 } };
async function generate(result) {
  probe.schedule(result); clearTimeout(probe.timer); probe.lastInput = performance.now() - 1000;
  await probe.generate();
}
try {
  for (let i = 0; i < 40; i++) probe.schedule({ ...morning, altitude: 20 + i / 10 });
  assert.equal(renderer.captures, 0, 'Slider ativo não deve disparar capturas por input/frame.');
  await new Promise(resolve => setTimeout(resolve, config.ENVIRONMENT_SETTINGS.lightProbe.debounceMs + 60));
  assert.equal(renderer.captures, 1);
  assert.equal(probe.probe.visible, true);
  assert.ok(probe.probe.sh.coefficients.every(v => [v.x, v.y, v.z].every(Number.isFinite)));
  const captures = renderer.captures;
  probe.schedule({ ...probe.result, altitude: probe.result.altitude + .01 });
  assert.equal(renderer.captures, captures, 'Variação abaixo do threshold deve reutilizar SH.');
  await generate(morning);
  const morningSH = probe.probe.sh.clone();
  await generate({ ...morning, azimuth: 270, direction: { x: -.866, y: .5, z: 0 } });
  assert.ok(!probe.probe.sh.equals(morningSH), 'SH deve responder à direção/azimute, não somente à elevação.');
  const beforeCache = renderer.captures;
  probe.schedule(morning);
  assert.equal(renderer.captures, beforeCache);
  assert.ok(probe.probe.sh.equals(morningSH));
  probe.setIntensity(0.9);
  assert.equal(renderer.captures, beforeCache, 'Slider de intensidade não deve recapturar atmosfera.');
  await generate({ altitude: -20, azimuth: 240, direction: { x: -.8, y: -.34, z: .49 } });
  assert.equal(probe.probe.intensity, 0.9 * config.ENVIRONMENT_SETTINGS.lightProbe.nightScale);
  assert.ok(probe.probe.sh.coefficients[0].length() < morningSH.coefficients[0].length());
  const originalNight = probe.last.sh.coefficients[0];
  const neutralNight = probe.probe.sh.coefficients[0];
  assert.ok(Math.abs(neutralNight.x - neutralNight.z) < Math.abs(originalNight.x - originalNight.z), 'Probe noturna deve preservar luminância com menor saturação.');
  const lum = v => .2126 * v.x + .7152 * v.y + .0722 * v.z;
  assert.ok(Math.abs(lum(neutralNight) - lum(originalNight)) < 1e-10);
  const savedNight = probe.last.sh.clone();
  probe.setIntensity(0.9);
  assert.ok(probe.last.sh.equals(savedNight), 'Neutralização não pode modificar a SH original em cache.');
  probe.schedule(morning);
  assert.ok(probe.probe.sh.equals(morningSH), 'A coloração diurna original deve ser restaurada ao retornar ao dia.');
  probe.deactivate();
  assert.equal(probe.probe.visible, false);
  probe.dispose();
  assert.equal(scene.children.length, 0);
  assert.equal(probe.cache.size, 0);
  console.log('OK: debounce real, thresholds/cache SH, direção/Norte, noite, tuning sem captura e ciclo de vida da LightProbe.');
} finally { probe.dispose(); }
