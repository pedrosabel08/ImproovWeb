import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';
import { loadViewerModule } from './viewer_module_loader.mjs';
import * as config from '../MapaPavimento/predio-3d/environment-config.mjs';
// GPU/EXR são substituídos por recursos rastreados. Exercita o controlador real:
// cache, resposta fora de ordem, debounce, falha HTTP e descarte durante download.
const source = readFileSync(new URL('../MapaPavimento/predio-3d/solar-environment.js', import.meta.url), 'utf8');
const modulePath = process.argv.find(arg => arg.startsWith('--three-module='))?.slice(15);
if (!modulePath) throw new Error('Informe --three-module=CAMINHO/three.module.js (r180).');
const actualThree = await import(pathToFileURL(resolve(modulePath)).href);
const { createProceduralAtmosphere } = await loadViewerModule('procedural-atmosphere.js', pathToFileURL(resolve(modulePath)).href);
let generated = 0, decoded = 0, released = 0, sourceReleased = 0, fallbackReleased = 0;
const color = () => new actualThree.Color();
const THREE = {
  ...actualThree,
  DataTexture: class extends actualThree.DataTexture { dispose() { super.dispose(); sourceReleased++; } },
  PMREMGenerator: class {
    fromEquirectangular() { generated++; return { texture: {}, dispose() { released++; } }; }
    dispose() {}
  },
};
const createHdriSky = () => ({
  mesh: { visible: false }, uniforms: { zenith: { value: color() }, ground: { value: color() } }, state: 'Dia',
  update() { this.mesh.visible = true; }, setEnvironment(texture) { this.texture = texture; },
  hide() { this.mesh.visible = false; }, tick() {}, dispose() {},
  diagnostics() { return {}; },
});
const ProceduralLightProbe = class {
  constructor() { this.scheduled = 0; }
  schedule() { this.scheduled++; } setIntensity() {} deactivate() {} dispose() {}
  diagnostics() { return { intensity: .55, active: true }; }
};
const MaterialReflections = class {
  constructor() { this.materials = new Map(); }
  set(texture) { this.texture = texture; } clear() {} setGlassIntensity() {} setRotation() {}
};
const ExrDecoder = class {
  async decode() { decoded++; return { data: new Uint16Array(4), width: 1, height: 1, format: THREE.RGBAFormat, type: THREE.HalfFloatType, colorSpace: THREE.LinearSRGBColorSpace,
    hemisphere: { sky: [.1, .2, .4], ground: [.2, .15, .1] }, skyAverage: .2 }; }
  dispose() {}
};
const SolarEnvironment = new Function('THREE', 'ExrDecoder', 'createHdriSky', 'createProceduralAtmosphere', 'ProceduralLightProbe', 'MaterialReflections', 'SETTINGS', ...Object.keys(config),
  source.slice(source.indexOf('class SolarEnvironment')).replace('import.meta.url', JSON.stringify('http://localhost:8066/ImproovWeb/MapaPavimento/predio-3d/viewer-environment.js')) + '\nreturn SolarEnvironment;'
)(THREE, ExrDecoder, createHdriSky, createProceduralAtmosphere, ProceduralLightProbe, MaterialReflections, config.ENVIRONMENT_SETTINGS, ...Object.values(config));
const fetchOriginal = globalThis.fetch;
const requests = [];
globalThis.fetch = (url, options) => new Promise((resolve, reject) => {
  requests.push({ url: String(url), resolve: () => resolve({ ok: true, arrayBuffer: async () => new ArrayBuffer(1) }), reject });
  options.signal.addEventListener('abort', () => reject(new Error('aborted')), { once: true });
});
const scene = { environment: {}, environmentIntensity: 1 };
const fallbackTexture = scene.environment;
const environment = new SolarEnvironment({}, scene, fallbackTexture, { dispose() { fallbackReleased++; } });
const light = { intensity: 2, color: color() };
const ambient = { intensity: 0, color: color(), groundColor: color() };
const morning = { altitude: 25, rising: true, intensity: 2, direction: { x: 0, y: 1, z: 0 } };
const afternoon = { ...morning, rising: false };
const settle = () => new Promise(resolve => setImmediate(resolve));
function start(result) {
  environment.update(result, light, ambient);
  clearTimeout(environment.timer);
  environment.lastEnvironmentInput = performance.now() - 1000;
  return environment.activate(environment.requested);
}
try {
  const first = start(morning);
  await settle();
  const duplicate = environment.activate('morningLate');
  assert.equal(requests.length, 1, 'Downloads simultâneos do mesmo preset devem ser deduplicados.');
  const second = start(afternoon);
  await settle();
  requests[1].resolve();
  await second;
  assert.equal(environment.current, 'afternoon');
  requests[0].resolve();
  await Promise.all([first, duplicate]);
  assert.equal(environment.current, 'afternoon', 'Download antigo não pode sobrescrever a escolha nova.');
  assert.equal(generated, 1, 'Preset obsoleto não deve gerar PMREM.');
  await start(morning);
  assert.equal(generated, 2);
  const cached = environment.reflections.texture;
  assert.equal(scene.environment, null, 'HDRI não pode voltar ao environment global.');
  await start(afternoon);
  await start(morning);
  assert.equal(environment.reflections.texture, cached);
  assert.equal(requests.length, 2);
  assert.equal(decoded, 2);
  assert.equal(sourceReleased, 0, 'Texturas visuais permanecem em cache até o descarte.');
  const probeCalls = environment.probe.scheduled;
  const physical = environment.result;
  const sunColor = light.color.clone();
  environment.tick(performance.now() + 1000);
  const fill = { sky: ambient.color.clone(), ground: ambient.groundColor.clone(), intensity: ambient.intensity };
  environment.setOverride('afternoon');
  environment.tick(performance.now() + 1000);
  assert.equal(environment.result, physical);
  assert.equal(environment.probe.scheduled, probeCalls, 'Override não pode agendar LightProbe.');
  assert.equal(light.intensity, 2);
  assert.ok(light.color.equals(sunColor));
  assert.ok(ambient.color.equals(fill.sky) && ambient.groundColor.equals(fill.ground));
  assert.equal(ambient.intensity, fill.intensity, 'Override não pode mudar Hemisphere.');
  environment.setOverride('auto');
  const normalized = environment.fillColor('sky');
  environment.cache.get('morningLate').hemisphere = { sky: [10, 2000, 40] };
  assert.ok(normalized.equals(environment.fillColor('sky')), 'Dados de EXR não podem participar do preenchimento.');
  const night = { ...morning, altitude: -20, intensity: 0 };
  const third = start(night);
  await settle();
  requests[2].reject(new Error('HTTP 404'));
  await third;
  assert.equal(environment.state, 'error');
  assert.equal(environment.reflections.texture, cached, 'Falha deve preservar os últimos reflexos válidos.');
  assert.equal(scene.environment, null);
  assert.equal(light.intensity, 2, 'Environment nunca escreve na DirectionalLight, nem à noite.');
  assert.equal(environment.diagnostics().nightFactor, 1);
  environment.tick(performance.now() + 1000);
  assert.ok(Math.abs(ambient.intensity - config.ENVIRONMENT_SETTINGS.hemisphere.intensity * config.ENVIRONMENT_SETTINGS.hemisphere.nightScale) < 1e-10,
    'Fill noturno deve usar o valor de visualização configurado, independentemente do HDRI.');
  environment.navigation();
  assert.equal(scene.environment, fallbackTexture);
  assert.equal(environment.sky.mesh.visible, false);
  const pending = start({ ...morning, altitude: 4 });
  await settle();
  environment.dispose();
  await pending;
  assert.equal(released, 2);
  assert.equal(sourceReleased, 2);
  assert.equal(fallbackReleased, 1);
  assert.equal(scene.environment, null);
  assert.equal(environment.cache.size, 0);
  assert.equal(generated, 2, 'Descarte não pode ressuscitar texturas de um download pendente.');
  console.log('OK: cache, concorrência, falha HTTP, descarte, sem IBL global, override isolado e preenchimento invariável à exposição do EXR.');
} finally {
  environment.dispose();
  globalThis.fetch = fetchOriginal;
}
