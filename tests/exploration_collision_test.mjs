import assert from 'node:assert/strict';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { loadViewerModule } from './viewer_module_loader.mjs';

const path = process.argv.find(arg => arg.startsWith('--three-module='))?.split('=').slice(1).join('=');
assert(path, 'Use --three-module=/path/to/three.module.js');
const url = pathToFileURL(resolve(path)).href;
const T = await import(url);
const { ExplorationCollision } = await loadViewerModule('exploration-collision.js', url);
const { SolarLighting } = await loadViewerModule('solar-lighting.js', url);
const signal = new AbortController().signal;
const v = (x, y, z) => new T.Vector3(x, y, z);

const geo = new T.Group();
geo.position.set(135, 12, -43);
geo.updateMatrixWorld(true);
const wall = new T.Mesh(new T.PlaneGeometry(6, 3), new T.MeshStandardMaterial({ transparent: true, opacity: .15 }));
wall.position.set(137, 14, -43); wall.rotation.y = Math.PI / 2;
const blocked = new ExplorationCollision({ meshes: [wall], group: geo, terrain: { mesh: { sample: (x, z) => Math.abs(z) < 10 ? x / 10 : null } } });
await blocked.prepare(signal);
assert.equal(blocked.support(136, -43, 14), 12.1, 'DEM accounts for horizontal and vertical model offset');
const person = v(136, 12.1, -43);
for (let i = 0; i < 50; i++) blocked.tryMove(person, .06, 0);
assert(person.x > 136.6 && person.x < 136.75, 'Thin glass wall blocks a capsule at radius distance');
const reverse = v(138, 12.3, -43);
for (let i = 0; i < 50; i++) blocked.tryMove(reverse, -.06, 0);
assert(reverse.x > 137.25 && reverse.x < 137.4, 'Collision works from both sides');
assert(blocked.tryMove(person, 0, .06), 'The walker can slide alongside a wall');
assert(blocked.cameraDistance(v(136, 13, -43), v(139, 14, -43)) < 1.1, 'Camera retracts before the wall');
assert.equal(blocked.support(136, -53.2, 14), null, 'DEM outside its drawn footprint does not create phantom ground');

const cachedGround = new T.Mesh(new T.PlaneGeometry(6, 6), new T.MeshStandardMaterial());
cachedGround.name = 'OSM · ground';
cachedGround.rotation.x = -Math.PI / 2; cachedGround.rotation.z = -.1;
geo.add(cachedGround); geo.updateMatrixWorld(true);
const cached = new ExplorationCollision({ meshes: [], group: geo, terrain: null });
await cached.prepare(signal);
assert.equal(cached.support(135, -43, 13), 12, 'Cached DEM support comes from drawn terrain triangles');
assert.equal(cached.support(135, -43, 11), null);

const slab = (x, y, width) => {
  const mesh = new T.Mesh(new T.BoxGeometry(width, .4, 4), new T.MeshStandardMaterial());
  mesh.position.set(x, y - .2, 0);
  return mesh;
};
const stairs = new ExplorationCollision({ meshes: [slab(-1, 0, 2), slab(.5, .18, 1), slab(2, .36, 2), slab(2, 3, 2)] });
await stairs.prepare(signal);
const upper = stairs.place({ point: v(2, 3, 0), normalY: 1, towardCamera: v(0, 0, 1) });
assert(upper && upper.y > 2.9, 'Placement selects the clicked floor, even above another walkable floor');
const walker = v(-1, 0, 0);
for (let i = 0; i < 40; i++) stairs.tryMove(walker, .08, 0);
assert(walker.x > 2 && Math.abs(walker.y - .36) < .01, 'The capsule can climb modeled stairs');
for (let i = 0; i < 40; i++) stairs.tryMove(walker, -.08, 0);
assert(walker.x < -.8 && Math.abs(walker.y) < .01, 'The capsule can descend modeled stairs');

wall.material.clippingPlanes = [new T.Plane(v(0, 0, 1), 42)];
const clipped = new ExplorationCollision({ meshes: [wall] });
await clipped.prepare(signal);
assert(!clipped.blocked(137, 12.2, -43), 'Hidden parts of a clipped mesh do not collide');
assert(clipped.blocked(137, 12.2, -41), 'Visible parts of the same mesh still collide');

const light = new T.DirectionalLight();
light.position.set(8, 20, -5); light.target.position.set(4, 5, -2);
const pos = light.position.clone(), target = light.target.position.clone();
const state = Object.assign(Object.create(SolarLighting.prototype), {
  light, result: { altitude: 30 }, receiverBounds: null,
  bounds: new T.Box3(v(-30, 0, -30), v(30, 25, 30)),
  explorerShadowBounds: new T.Box3(), explorerShadowCenter: v(0,0,0),
  explorerShadowDirection: v(0,0,0), aerialLightPosition: v(0,0,0),
  aerialLightTarget: v(0,0,0), viewPoint: v(0,0,0), explorerShadowFrame: -Infinity,
});
state.setExplorerPosition(v(130, 12, -43));
state.updateExplorerShadow(50);
assert(light.position.clone().sub(light.target.position).normalize().distanceTo(pos.clone().sub(target).normalize()) < 1e-10);
assert(light.shadow.needsUpdate, 'Local shadow updates while exploring');
state.setExplorerPosition(null);
assert(light.position.equals(pos) && light.target.position.equals(target), 'Leaving restores the aerial light position and target');
state.setExplorerPosition(v(130, 12, -43));
state.result.altitude = -10;
light.shadow.needsUpdate = false;
state.updateExplorerShadow(100);
assert(!light.shadow.needsUpdate, 'No dynamic shadow work below the horizon');
console.log('OK: translated and cached DEM, capsule against thin and clipped walls, camera obstruction, and solar state restoration.');
