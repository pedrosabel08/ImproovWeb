import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import {
  prepareCartography, attachRelief, terrainExaggeration, addLocalBase, cartographicProjection,
  IMAGERY_SOURCE, DEM_SOURCE, SHADE_SOURCE, HILLSHADE_LAYER,
} from '../MapaImproov/mapa-cartography.mjs';

const original = JSON.parse(await readFile(new URL('../MapaImproov/styles/dark.json', import.meta.url)));
const config = { style: 'https://tiles.openfreemap.org/styles/dark', imagery: 'https://example.org/{z}/{y}/{x}.jpeg',
  dem: 'https://example.org/dem.json', demEncoding: 'terrarium', demTileSize: 512, demAttribution: 'DEM credit' };
const style = prepareCartography(structuredClone(original), config);
const layer = id => style.layers.find(l => l.id === id);
assert.deepEqual(style.sources.openmaptiles, original.sources.openmaptiles, 'Provider and attribution must survive');
assert.equal(style.layers.filter(l => l.source === IMAGERY_SOURCE).length, 1);
assert.equal(layer(IMAGERY_SOURCE).maxzoom, 8, 'Never overzoom global photography into city streets');
assert.equal(style.sources[IMAGERY_SOURCE].tileSize, 256);
assert.ok(style.layers.indexOf(layer(IMAGERY_SOURCE)) < style.layers.indexOf(layer('boundary_state')));
for (const id of ['place_state', 'place_city', 'place_city_large', 'place_suburb'])
  assert.ok(layer(id).minzoom >= 4.5, `${id} must not pollute the globe`);
assert.ok(layer('place_country_minor').minzoom > layer('place_country_major').minzoom);
assert.ok(layer('place_suburb').minzoom > layer('place_city').minzoom);
assert.equal(layer('place_city_large').layout['icon-image'], undefined, 'Do not request the missing legacy sprite');
assert.equal(cartographicProjection('mercator').type, 'mercator');
assert.deepEqual(cartographicProjection('globe').type.slice(-4), [5,'vertical-perspective',6.5,'mercator']);
for (const z of [0, 2, 4.99, 6.49]) assert.equal(terrainExaggeration(z), 0, 'No geometry in the spherical render path');
const values = [5, 5.5, 6, 7, 8, 12, 18].map(terrainExaggeration);
assert.deepEqual([...values].sort((a,b)=>a-b), values, 'Terrain must grow progressively');
assert.equal(values.at(-1), 1, 'Never exceed real vertical scale');

const sources = new Map(), layers = [], events = new Map(), terrainCalls = [], layouts = [];
let z = 2;
let currentTerrain = null;
const map = {
  addSource(id, spec) { assert.ok(!sources.has(id)); sources.set(id, spec); },
  getSource: id => sources.get(id),
  getStyle: () => ({ layers: style.layers }),
  addLayer(spec) { layers.push(spec); },
  getZoom: () => z,
  on: (name, fn) => events.set(name, fn),
  getTerrain: () => currentTerrain,
  setTerrain: spec => { currentTerrain = spec; terrainCalls.push(spec); },
  setLayoutProperty: (...args) => layouts.push(args),
  setPaintProperty() {},
};
const relief = attachRelief(map, config);
assert.equal(sources.get(DEM_SOURCE).encoding, 'terrarium');
assert.equal(sources.get(DEM_SOURCE).tileSize, 512, 'Match native DEM pixels');
assert.equal(sources.get(SHADE_SOURCE).url, sources.get(DEM_SOURCE).url, 'Separate rendering sources reuse HTTP cache');
assert.equal(layers.find(l=>l.id === HILLSHADE_LAYER).minzoom, 5);
z = 7; events.get('zoomend')();
assert.ok(terrainCalls.at(-1).exaggeration > 0);
const callCount = terrainCalls.length;
events.get('zoomend')(); assert.equal(terrainCalls.length, callCount, 'No redundant terrain rebuild');
z = 8; events.get('zoomend')();
assert.equal(terrainCalls.at(-2), null, 'Release old GPU targets before replacing terrain exaggeration');
z = 5.9; events.get('zoom')(); assert.equal(terrainCalls.at(-1), null, 'Remove geometry before the globe transition on zoom-out');
z = 7; events.get('zoomend')();
relief.setEnabled(false); assert.equal(terrainCalls.at(-1), null);
assert.deepEqual(layouts.at(-1), [HILLSHADE_LAYER, 'visibility', 'none']);
relief.setEnabled(true); assert.ok(terrainCalls.at(-1));
relief.fail(); assert.equal(terrainCalls.at(-1), null, 'DEM outage removes geometry and keeps base map');
const failedCount = terrainCalls.length;
relief.fail(); relief.setEnabled(true); z = 12; events.get('zoomend')();
assert.equal(terrainCalls.length, failedCount, 'Tile failures are idempotent and cannot reactivate failed terrain');
addLocalBase(map); addLocalBase(map);
assert.equal(layers.filter(l => l.id === 'earth-fallback-land').length, 1);
const countries = JSON.parse(await readFile(new URL('../MapaImproov/styles/countries.geojson', import.meta.url)));
assert.ok(countries.features.length > 150, 'Offline fallback needs worldwide geography');
assert.ok(countries.features.every(f=>Object.keys(f.properties).length === 0), 'Fallback contains only public geometry');
console.log('Cartography: source compatibility, zoom hierarchy, terrain lifecycle and outage fallback passed.');
