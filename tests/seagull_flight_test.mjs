import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { registerHooks } from "node:module";
import { resolve } from "node:path";
import { pathToFileURL } from "node:url";
import { loadViewerModule } from "./viewer_module_loader.mjs";

const path = process.argv.find((arg) => arg.startsWith("--three-module="))?.slice("--three-module=".length);
assert(path, "Use --three-module=/path/to/three.module.js");
const url = pathToFileURL(resolve(path)).href;
const T = await import(url);
const { ExplorationCollision } = await loadViewerModule("exploration-collision.js", url);
const { SeagullFlight } = await loadViewerModule("seagull-flight.js", url);
const { FloorVisibility } = await loadViewerModule("floor-visibility.js", url);
const v = (x, y, z) => new T.Vector3(x, y, z);
const box = (min, max) => new T.Box3(v(...min), v(...max));
const camera = new T.OrthographicCamera(-25, 25, 25, -25, 0.01, 4000);
camera.position.set(0, 18, 40);
const bounds = box([-9, 0, -8], [9, 36, 8]);

async function collisionFor(meshes = [], extra = {}) {
  const collision = new ExplorationCollision({ meshes, flight: true, ...extra });
  await collision.prepare(new AbortController().signal);
  return collision;
}

function flightFor(collision, { extent = bounds, group, position, yaw = 0 } = {}) {
  const flight = new SeagullFlight({
    bounds: extent, group, collision, orbitCamera: camera, orbitTarget: v(0, 8, 0),
  });
  assert(flight.spawn(position ? { position, yaw, pitch: 0.27, distance: 5 * flight.unit } : null));
  return flight;
}

function step(flight, seconds, throttle = 0, steer = 0, lift = 0, fps = 60) {
  let result;
  for (let frame = 0; frame < Math.round(seconds * fps); frame++)
    result = flight.update(1 / fps, throttle, steer, lift) || result;
  return result;
}

const thin = new T.Mesh(new T.PlaneGeometry(14, 18), new T.MeshBasicMaterial({ transparent: true, opacity: 0.15 }));
thin.position.y = 9;
const collision = await collisionFor([thin]);
assert(collision.flightBlocked(v(0, 8, 5), v(0, 8, -5), 0.32));
assert(collision.flightBlocked(v(0, 8, -5), v(0, 8, 5), 0.32));
assert(collision.flightBlocked(v(7.24, 8, 5), v(7.24, 8, -5), 0.32));
assert(!collision.flightBlocked(v(7.4, 8, 5), v(7.4, 8, -5), 0.32));
const wallApproach = flightFor(collision);
step(wallApproach, 3, 1);
assert(wallApproach.position.z > 0.31 && wallApproach.position.z < 0.75);
step(wallApproach, 0.5, -1);
assert(wallApproach.position.z > 1);
const withCameraWall = flightFor(collision, { position: v(0, 8, -2) });
assert(withCameraWall.camera.position.z < 0 && withCameraWall.camera.position.z > -0.8);

const empty = await collisionFor();
const a = flightFor(empty, { position: v(10, 14, 20) });
const b = flightFor(empty, { position: v(10, 14, 20) });
step(a, 2 / 3, 1, 0, 1, 30);
step(b, 2 / 3, 1, 0, 1, 120);
assert(a.position.distanceTo(b.position) < 1e-8, "Straight flight is consistent across frame rates.");
assert(a.speed < a.maxSpeed && a.verticalSpeed > 0);
step(a, 0.7, 1, 1);
assert(a.yaw < -0.7 && a.bank < 0);
step(a, 20, 0, 0, 1);
assert(a.position.y <= a.ceiling && a.position.y > a.ceiling - 0.2);
step(a, 20, 0, 0, -1);
assert(a.position.y >= a.clearance && a.position.y < a.clearance + 0.2);

const terrainGroup = new T.Group();
terrainGroup.position.set(40, 5, -30);
const rotation = Math.PI / 4;
terrainGroup.userData.geoBounds = {
  radius: 14,
  toEN: ({ x, z }) => ({
    x: x * Math.cos(rotation) + z * Math.sin(rotation),
    z: -x * Math.sin(rotation) + z * Math.cos(rotation),
  }),
};
const terrain = { mesh: { sample: (x) => x / 5 } };
const dem = await collisionFor([], { group: terrainGroup, terrain });
const terrainFlight = flightFor(dem, {
  group: terrainGroup, extent: bounds.clone().translate(terrainGroup.position),
  position: v(44, 10, -30), yaw: -Math.PI / 2,
});
step(terrainFlight, 5, 0, 0, -1);
assert(terrainFlight.position.y >= 5.8 + terrainFlight.clearance);
assert(terrainFlight.position.y < 6.6);
step(terrainFlight, 5, 1, 0, 1);
assert(terrainFlight.inside(terrainFlight.position) && terrainFlight.position.x < 59.4);
assert(terrainFlight.position.x > 59);

const ground = new T.Mesh(new T.PlaneGeometry(40, 40), new T.MeshBasicMaterial());
ground.userData.geoLayer = "ground";
ground.rotation.x = -Math.PI / 2;
ground.position.y = 4;
const cachedGroup = new T.Group().add(ground);
const cached = await collisionFor([], { group: cachedGroup });
assert.equal(cached.groundHeight(0, 0, 0), 4);
const cachedFlight = flightFor(cached, { position: v(0, 10, 2) });
step(cachedFlight, 3, 0, 0, -1);
assert(cachedFlight.position.y >= 4 + cachedFlight.clearance);

const facade = new T.Mesh(new T.PlaneGeometry(10, 32), new T.MeshBasicMaterial());
facade.position.y = 16;
const slab = new T.Mesh(new T.BoxGeometry(8, 0.25, 8), new T.MeshBasicMaterial());
slab.position.y = 15;
const model = new T.Group().add(facade, slab);
const floor = { id: "lower", bounds: box([-5, 7, -4], [5, 10, 4]) };
const upper = { id: "upper", bounds: box([-5, 14, -4], [5, 17, 4]) };
const visibility = new FloorVisibility(model, [floor, upper], new Map([[slab, { floor: "upper" }]]));
visibility.apply(floor);
const cut = await collisionFor([facade, slab]);
assert(cut.flightBlocked(v(2, 8, 2), v(2, 8, -2), .32));
assert(!cut.flightBlocked(v(2, 15, 2), v(2, 15, -2), .32));
assert(!cut.flightBlocked(v(2, 14, 2), v(2, 16, 2), .32));
visibility.restore();
const full = await collisionFor([facade, slab]);
assert(full.flightBlocked(v(2, 15, 2), v(2, 15, -2), .32));
const blockedAltitude = flightFor(full, { position: v(3, 22, 1) });
blockedAltitude.goToFloor(floor);
assert.equal(step(blockedAltitude, 5), "blocked");
assert(blockedAltitude.position.y > 15.4);
const freeAltitude = flightFor(cut, { position: v(8, 20, 2) });
freeAltitude.goToFloor(floor);
step(freeAltitude, 0.5);
assert(freeAltitude.position.y < 20 && freeAltitude.position.y > 8.5);
assert.equal(step(freeAltitude, 5), "arrived");
assert(Math.abs(freeAltitude.position.y - 8.5) < 0.045);
const resumed = flightFor(cut);
assert(resumed.spawn(freeAltitude.snapshot()));
assert(resumed.position.equals(freeAltitude.position));
freeAltitude.goToFloor(upper);
step(freeAltitude, .5, 0, 0, -1);
assert.equal(freeAltitude.altitudeTarget, null);

for (const scale of [0.035, 1, 6.5]) {
  const extent = box([-9, 0, -8], [9, 36, 8]);
  extent.min.multiplyScalar(scale);
  extent.max.multiplyScalar(scale);
  const flight = flightFor(empty, { extent });
  const roof = { bounds: box([-5 * scale, 35 * scale, -5 * scale], [5 * scale, 36 * scale, 5 * scale]) };
  flight.goToFloor(roof);
  assert.equal(step(flight, 60), "arrived");
  assert(flight.position.y >= roof.bounds.min.y && flight.position.y <= roof.bounds.max.y);
}

console.log("OK: resposta a 30/120 FPS, subida e descida, fachada fina, câmera, DEM e cache, perímetro rotacionado, recorte de pavimentos, retomada e três escalas.");

// Resolve the viewer's browser import map using the local Three.js package.
const loader = registerHooks({
  resolve(specifier, context, next) {
    if (specifier === "three") return next(url, context);
    if (specifier.startsWith("three/addons/")) {
      const addon = new URL("../examples/jsm/" + specifier.slice("three/addons/".length), url);
      return next(addon.href, context);
    }
    return next(specifier, context);
  },
});
try {
  const { GLTFLoader } = await import("three/addons/loaders/GLTFLoader.js");
  const { SeagullAvatar } = await import("../MapaPavimento/predio-3d/seagull-avatar.js");
  const asset = await readFile(new URL("../assets/person/seagull-2.glb", import.meta.url));
  const gltf = await new GLTFLoader().parseAsync(
    asset.buffer.slice(asset.byteOffset, asset.byteOffset + asset.byteLength),
    "",
  );
  let released = 0;
  const avatar = new SeagullAvatar(gltf, (root) => {
    assert.equal(root, gltf.scene);
    released++;
  });
  assert.deepEqual(avatar.clips.map((clip) => [clip.name, clip.duration.toFixed(2)]), [
    ["flap", "3.33"], ["planer", "8.33"], ["planer 2", "16.67"],
  ]);
  const animatedScene = new T.Scene().add(avatar.root);
  const flyer = flightFor(empty, { position: v(4, 17, 19), yaw: Math.PI / 2 });
  const cameraBefore = flyer.camera.position.clone();
  const time = avatar.actions.map((action) => action.time);
  for (const state of [0, 1, 2]) {
    const previous = avatar.weights[state];
    avatar.update(1 / 60, flyer, 0, state);
    assert(avatar.weights[state] - previous < 0.2, "Switching clips preserves the current blend.");
    for (let frame = 0; frame < 60; frame++) avatar.update(1 / 60, flyer, 0, state);
    assert(avatar.weights[state] > 0.99);
    assert(avatar.actions[state].time > time[state]);
    assert(avatar.anchor.getWorldPosition(v(0, 0, 0)).distanceTo(flyer.position) < 1e-9);
    assert(flyer.camera.position.equals(cameraBefore));
  }
  const facing = v(0, 0, 1).applyQuaternion(avatar.root.quaternion);
  assert(facing.x < -0.99, "The beak faces the direction of flight.");
  const width = new T.Box3().setFromObject(avatar.root, true).getSize(v(0, 0, 0)).z;
  assert(width > 1.05 && width < 1.35, "The animated wingspan is normalized.");
  for (const [throttle, speed, vertical, expected] of [
    [1, 0.35, 0, 0], [0, 0.6, 0.4, 0], [1, 0.95, 0, 1], [0, 0.6, -0.4, 2],
  ]) {
    flyer.speed = speed * flyer.maxSpeed;
    flyer.verticalSpeed = vertical * flyer.climbSpeed;
    for (let frame = 0; frame < 60; frame++) avatar.update(1 / 60, flyer, throttle, -1);
    assert(avatar.weights[expected] > 0.99);
  }
  avatar.detach();
  assert.equal(animatedScene.children.length, 0);
  animatedScene.add(avatar.root);
  assert.equal(animatedScene.children.length, 1);
  avatar.dispose();
  assert.equal(released, 1);
  assert.equal(animatedScene.children.length, 0);
  console.log("OK: GLTFLoader, durações reais dos três clipes, blend contínuo, escala, orientação, câmera independente e liberação do personagem.");
} finally {
  loader.deregister();
}
