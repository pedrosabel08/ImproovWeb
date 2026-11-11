import { createHash } from "node:crypto";
import { readFile, writeFile, rename } from "node:fs/promises";
import { registerHooks } from "node:module";
import { dirname, join } from "node:path";
import webp, { init } from "@jsquash/webp/decode.js";
import dracoPackage from "draco3dgltf";

const sha = bytes => createHash("sha256").update(bytes).digest("hex");
const root = new URL("../../MapaPavimento/predio-3d/", import.meta.url);
const three = import.meta.resolve("three");
registerHooks({
  resolve(specifier, context, next) {
    return next(specifier === "three" ? three : specifier, context);
  },
});

const THREE = await import("three");
const { GLTFLoader } = await import("three/addons/loaders/GLTFLoader.js");
const { DRACOLoader } = await import("three/addons/loaders/DRACOLoader.js");
const { generateEnvironment } = await import(new URL("geo/generate-environment.js", root));
const { parseOSM } = await import(new URL("geo/osm-parser.js", root));
const { loadElevation, terrariumHeight } = await import(new URL("geo/elevation-service.js", root));
const { buildIndex, modelGroundSelection, modelAlignmentSignature } = await import(new URL("geo/model-alignment.js", root));
const { hideExcludedObjects } = await import(new URL("model-scene.js", root));
const { visibleModelBounds } = await import(new URL("floor-visibility.js", root));

async function dracoLoader() {
  // Reuse the pinned Three.js worker's decode routine and attribute semantics.
  const source = await readFile(new URL(import.meta.resolve("three/addons/loaders/DRACOLoader.js")), "utf8");
  const start = source.indexOf("function decodeGeometry( draco, decoder, array, taskConfig )");
  const end = source.lastIndexOf("export { DRACOLoader }");
  if (start < 0 || end <= start) throw Error("Unsupported Three.js Draco adapter");
  const decode = Function("self", source.slice(start, end).trimEnd().slice(0, -1) +
    "\nreturn decodeGeometry;")(globalThis);
  const draco = await dracoPackage.createDecoderModule({});
  const loader = new DRACOLoader();
  loader.preload = () => loader;
  loader.decodeGeometry = async (buffer, task) => {
    const decoder = new draco.Decoder();
    try {
      return loader._createGeometry(decode(draco, decoder, new Int8Array(buffer), task));
    } finally {
      draco.destroy(decoder);
    }
  };
  return loader;
}

async function checked(input) {
  const buffer = await readFile(input.path);
  if (sha(buffer) !== input.sha256) throw Error("Input changed while the job was queued");
  return buffer;
}
function unguard(buffer) {
  return buffer.subarray(buffer.indexOf(10) + 1);
}
function boxValues(box) {
  return [box.min.toArray(), box.max.toArray()];
}
function serialize(result, alignment, inputMs, generationMs) {
  let offset = 0;
  const chunks = [];
  const meshes = result.group.children.map(mesh => {
    const attributes = {};
    for (const key of ["position", "normal"]) {
      const { array } = mesh.geometry.attributes[key];
      if (!(array instanceof Float32Array) || mesh.geometry.index) throw Error("Unexpected mesh format");
      const bytes = Buffer.from(array.buffer, array.byteOffset, array.byteLength);
      attributes[key] = { offset, length: array.length };
      offset += bytes.length;
      chunks.push(bytes);
    }
    return {
      name: mesh.name, layer: mesh.userData.geoLayer, attributes,
      castShadow: mesh.castShadow, receiveShadow: mesh.receiveShadow, renderOrder: mesh.renderOrder,
    };
  });
  if (offset > 64 * 1024 * 1024) throw Error("Geometry exceeds cache limit");
  const metadata = {
    format: 1, alignment, origin: result.group.position.toArray(),
    meshes, metrics: result.metrics, timings: { inputMs, generationMs },
  };
  const header = Buffer.from(JSON.stringify(metadata));
  const size = Buffer.alloc(4);
  size.writeUInt32LE(header.length);
  return Buffer.concat([size, header, ...chunks]);
}

const jobFile = process.argv[2];
const dir = dirname(jobFile);
try {
  const job = JSON.parse(await readFile(jobFile, "utf8"));
  const start = performance.now();
  const [glb, osm, draco] = await Promise.all([checked(job.model), checked(job.osm), dracoLoader()]);
  // Images do not participate in geometry or alignment. The browser still decodes them normally.
  globalThis.self = globalThis;
  globalThis.createImageBitmap = async () => ({ width: 1, height: 1, close() {} });
  const fetchEmbedded = globalThis.fetch;
  globalThis.fetch = (input, options) => {
    const url = typeof input === "string" ? input : input.url;
    if (!/^(blob:|data:)/.test(url)) throw Error("External GLB resources are unsupported");
    return fetchEmbedded(input, options);
  };
  const loader = new GLTFLoader().setDRACOLoader(draco);
  const { scene } = await loader.parseAsync(glb.buffer.slice(glb.byteOffset, glb.byteOffset + glb.byteLength), "");
  hideExcludedObjects(scene);
  scene.updateMatrixWorld(true);
  const { groundObjects, groundInfo } = modelGroundSelection(scene);
  const { floors } = buildIndex(scene, "api.php");
  for (const object of groundObjects) object.visible = false;
  const bounds = visibleModelBounds(scene);
  const anchor = new THREE.Box3();
  for (const floor of floors) anchor.union(floor.bounds);
  const buildingBounds = anchor.isEmpty() ? bounds : anchor;
  if (bounds.isEmpty()) throw Error("Empty model bounds");

  const module = await WebAssembly.compile(await readFile(new URL("./codec/dec/webp_dec.wasm", import.meta.resolve("@jsquash/webp/decode.js"))));
  await init(module);
  const dem = await loadElevation(job.config, {
    fetcher: async url => {
      const key = [url.searchParams.get("x"), url.searchParams.get("y")].join("-");
      return { ok: true, blob: async () => unguard(await checked(job.tiles[key])) };
    },
    decode: async buffer => {
      const { data, width, height } = await webp(Uint8Array.from(buffer).buffer);
      if (width !== 512 || height !== 512) throw Error("Invalid DEM dimensions");
      const heights = new Float32Array(512 * 512);
      for (let i = 0; i < heights.length; i++) {
        const p = i * 4;
        if (data[p + 3] !== 255) throw Error("Invalid DEM alpha");
        const altitude = terrariumHeight(data[p], data[p + 1], data[p + 2]);
        if (altitude < -12000 || altitude > 9000) throw Error("Invalid DEM altitude");
        heights[i] = altitude;
      }
      return heights;
    },
  });
  const data = parseOSM(JSON.parse(unguard(osm)).payload);
  if (!Object.values(data).some(items => items.length)) throw Error("Empty OSM input");
  const generation = performance.now();
  const result = await generateEnvironment({ data, config: job.config, bounds, buildingBounds, groundInfo, dem });
  if (!result?.terrain) throw Error("Generation used flat fallback");
  const alignment = { bounds: boxValues(bounds), anchor: boxValues(buildingBounds), elevation: groundInfo?.elevation ?? null,
    signature: await modelAlignmentSignature(bounds, buildingBounds, groundInfo),
    location: [job.config.latitude, job.config.longitude, job.config.norte_graus] };
  const serializing = performance.now();
  const packageBytes = serialize(result, alignment, generation - start, serializing - generation);
  const serializationMs = performance.now() - serializing;
  await writeFile(join(dir, "geometry.tmp"), packageBytes, { flag: "wx" });
  await rename(join(dir, "geometry.tmp"), join(dir, "geometry.bin"));
  const manifest = {
    format: 1, key: job.key, bytes: packageBytes.length, sha256: sha(packageBytes), serializationMs,
  };
  await writeFile(join(dir, "manifest.tmp"), JSON.stringify(manifest), { flag: "wx" });
  await rename(join(dir, "manifest.tmp"), join(dir, "manifest.json"));
} catch (error) {
  await writeFile(join(dir, "failed"), String(error.stack ?? error)).catch(() => {});
  process.exitCode = 1;
}

