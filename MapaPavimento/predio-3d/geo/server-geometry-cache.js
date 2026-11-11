import * as THREE from "three";
import { modelAlignmentSignature } from "./model-alignment.js?v=20261110-cache-v1";
import {
  boundedLocalGeometry, localGeometryCandidate, readLocalGeometry,
  removeLocalGeometry, saveLocalGeometry, touchLocalGeometry,
} from "./browser-geometry-cache.js?v=1";

export function cacheRequest(config, model, signal, warm = false, known = null) {
  const url = new URL("../../api.php", import.meta.url);
  url.search = new URLSearchParams({
    action: "geometria_cache", obra_id: config.obra_id, modelo_id: model.id, v: model.version,
    ...(warm ? { warm: 1 } : {}),
    ...(known ? { known } : {}),
  });
  return fetch(url, { credentials: "same-origin", signal });
}

export async function readGeometryCache(response) {
  if (!response.ok || response.status !== 200) throw Error("Cache unavailable");
  const start = performance.now();
  const expected = response.headers.get("X-Flow-Geometry-SHA256");
  const size = Number(response.headers.get("Content-Length"));
  if (!/^[a-f0-9]{64}$/.test(expected) || size < 5 || size > 65 * 1024 * 1024) throw Error("Invalid geometry response");
  const buffer = await response.arrayBuffer();
  const transferMs = performance.now() - start;
  return decodeGeometryCache(buffer, size, expected, transferMs,
    Number(response.headers.get("X-Flow-Geometry-Serialization-Ms")));
}

async function decodeGeometryCache(buffer, size, expected, transferMs, serializationMs) {
  const start = performance.now();
  if (!/^[a-f0-9]{64}$/.test(expected) || size < 5 || size > 65 * 1024 * 1024 ||
      !(buffer instanceof ArrayBuffer) || buffer.byteLength !== size) throw Error("Invalid geometry size or digest");
  const digest = await crypto.subtle.digest("SHA-256", buffer);
  const hash = Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, "0")).join("");
  if (hash !== expected) throw Error("Geometry integrity mismatch");
  const headerSize = new DataView(buffer).getUint32(0, true);
  if (headerSize < 2 || headerSize > 1024 * 1024 || 4 + headerSize > size) throw Error("Invalid geometry header");
  const header = JSON.parse(new TextDecoder().decode(new Uint8Array(buffer, 4, headerSize)));
  if (header.format !== 1) throw Error("Unsupported geometry format");
  return {
    buffer, header, transferMs, validationMs: performance.now() - start, serializationMs, sha: expected,
    dataOffset: 4 + headerSize,
  };
}

export async function validateGeometryAlignment(cached, config, bounds, anchor, groundInfo) {
  const start = performance.now();
  const alignment = cached.header.alignment;
  const location = [config.latitude, config.longitude, config.norte_graus];
  if (JSON.stringify(alignment?.location) !== JSON.stringify(location) ||
      alignment.signature !== await modelAlignmentSignature(bounds, anchor, groundInfo)) {
    throw Error("Geometry alignment mismatch");
  }
  return performance.now() - start;
}

export async function fetchGeometryCache(obraId, model, signal) {
  const start = performance.now();
  let metrics;
  try {
    const scope = JSON.stringify([obraId, model.id, model.version]);
    const candidate = await boundedLocalGeometry(() => localGeometryCandidate(scope), null);
    const localLookupMs = performance.now() - start;
    signal?.throwIfAborted();
    const timeout = AbortSignal.timeout(2500);
    const deadline = signal ? AbortSignal.any([signal, timeout]) : timeout;
    let response = await cacheRequest({ obra_id: obraId }, model, deadline, false, candidate);
    if (response.status !== 200 && response.status !== 204) throw Error("Geometry cache unavailable");
    metrics = {
      source: "miss",
      lookupMs: performance.now() - start,
      localLookupMs,
      needsInputs: response.headers.get("X-Flow-Geometry-State") === "inputs-missing",
    };
    if (response.headers.get("X-Flow-Geometry-State") === "known") {
      const manifest = await response.json();
      if (manifest.format !== 1 || manifest.sha256 !== candidate) throw Error("Invalid authorized manifest");
      const localStart = performance.now();
      const buffer = await boundedLocalGeometry(() => readLocalGeometry(candidate), null, 250);
      metrics.localReadMs = performance.now() - localStart;
      if (buffer) {
        try {
          const cached = await decodeGeometryCache(buffer, manifest.size, candidate, 0, manifest.serialization_ms);
          deadline.throwIfAborted();
          touchLocalGeometry(candidate).catch(() => {});
          return { cached, metrics: { ...metrics, source: "indexeddb" } };
        } catch {
          removeLocalGeometry(candidate).catch(() => {});
        }
      }
      response = await cacheRequest({ obra_id: obraId }, model, deadline);
      if (response.status !== 200 && response.status !== 204) throw Error("Geometry cache unavailable");
      metrics.needsInputs = response.headers.get("X-Flow-Geometry-State") === "inputs-missing";
    }
    const cached = response.status === 200 ? await readGeometryCache(response) : null;
    if (cached && !deadline.aborted) saveLocalGeometry(scope, cached.sha, cached.buffer).catch(() => {});
    return { cached, metrics };
  } catch (error) {
    return { cached: null, metrics: { ...metrics, source: "error", lookupMs: metrics?.lookupMs ?? performance.now() - start }, error };
  }
}

export function restoreGeometryCache(cached, materials) {
  const { buffer, header, dataOffset } = cached;
  const group = new THREE.Group();
  group.name = "Entorno urbano OpenStreetMap";
  const origin = header.origin;
  if (!Array.isArray(origin) || origin.length !== 3 || !origin.every(Number.isFinite) ||
      !Array.isArray(header.meshes) || !header.meshes.length) throw Error("Invalid scene metadata");
  group.position.fromArray(origin);
  let cursor = 0;
  try {
    for (const record of header.meshes) {
      const key = record.name === "OSM · terreno GLB" ? "ground" : record.name?.slice("OSM · ".length);
      if (!Object.hasOwn(materials, key)) throw Error("Unknown geometry material");
      const attributes = {};
      for (const name of ["position", "normal"]) {
        const item = record.attributes?.[name];
        if (!item || item.offset !== cursor || !Number.isSafeInteger(item.length) ||
            item.length < 9 || item.length % 9 || dataOffset + cursor + item.length * 4 > buffer.byteLength) {
          throw Error("Invalid geometry attribute");
        }
        const array = new Float32Array(buffer.slice(dataOffset + cursor, dataOffset + cursor + item.length * 4));
        if (!array.every(Number.isFinite)) throw Error("Invalid geometry coordinates");
        attributes[name] = new THREE.BufferAttribute(array, 3);
        cursor += item.length * 4;
      }
      if (attributes.position.count !== attributes.normal.count) throw Error("Geometry attributes disagree");
      const geometry = new THREE.BufferGeometry();
      for (const [name, attribute] of Object.entries(attributes)) geometry.setAttribute(name, attribute);
      geometry.computeBoundingSphere();
      const mesh = new THREE.Mesh(geometry, materials[key]);
      mesh.name = record.name;
      if (record.layer) mesh.userData.geoLayer = record.layer;
      mesh.castShadow = record.castShadow === true;
      mesh.receiveShadow = record.receiveShadow === true;
      mesh.renderOrder = record.renderOrder === -1 ? -1 : 0;
      mesh.raycast = () => {};
      group.add(mesh);
    }
    if (dataOffset + cursor !== buffer.byteLength) throw Error("Unexpected geometry data");
    return group;
  } catch (error) {
    group.traverse(object => object.geometry?.dispose());
    throw error;
  }
}
