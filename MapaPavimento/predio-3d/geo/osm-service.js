import { parseOSM } from "./osm-parser.js?v=20261103-boundary-v4";

export const OSM_RADIUS = 1000;
const VERSION = 3,
  TTL = 7 * 86400000,
  PREFIX = "flow-osm-v3:";
const memory = new Map();
export function osmCacheKey({ latitude, longitude }, radius = OSM_RADIUS) {
  return `${PREFIX}${latitude.toFixed(6)}:${longitude.toFixed(6)}:${radius}`;
}
export function overpassQuery({ latitude, longitude }, radius = OSM_RADIUS) {
  if (
    !Number.isFinite(latitude) ||
    Math.abs(latitude) >= 89 ||
    !Number.isFinite(longitude) ||
    Math.abs(longitude) > 180 ||
    !Number.isFinite(radius) ||
    radius < 100 ||
    radius > 1500
  )
    throw new Error("Consulta geográfica inválida.");
  // Query the entire geographic square, including its corners and coastline.
  const latDelta = (radius + 5) / 111195.0802,
    lonDelta = latDelta / Math.cos((latitude * Math.PI) / 180);
  const around = `(${latitude - latDelta},${longitude - lonDelta},${latitude + latDelta},${longitude + lonDelta})`;
  return `[out:json][timeout:25];(${[
    '[building][building!="no"]',
    "[highway]",
    '[natural="water"]',
    "[water]",
    '[natural="coastline"]',
    "[waterway]",
    '[landuse="reservoir"]',
    '[leisure="park"]',
    '[landuse~"^(forest|grass)$"]',
    '[natural~"^(wood|grassland)$"]',
  ]
    .map(
      (filter) =>
        `way${filter}${around};relation[type="multipolygon"]${filter}${around};`,
    )
    .join("")});out geom;`;
}
function validEntry(entry) {
  const point = (p) =>
    Number.isFinite(p?.lat) &&
    Math.abs(p.lat) <= 90 &&
    Number.isFinite(p?.lon) &&
    Math.abs(p.lon) <= 180;
  const ring = (r) => Array.isArray(r) && r.length >= 4 && r.every(point);
  const feature = (f) =>
    f &&
    Array.isArray(f.polygons) &&
    f.polygons.every(
      (p) => ring(p?.outer) && Array.isArray(p.holes) && p.holes.every(ring),
    ) &&
    (f.line === null ||
      (Array.isArray(f.line) && f.line.length >= 2 && f.line.every(point)));
  return (
    entry?.version === VERSION &&
    Number.isFinite(entry.savedAt) &&
    Date.now() - entry.savedAt >= 0 &&
    Date.now() - entry.savedAt < TTL &&
    Number.isFinite(entry.expiresAt) && Date.now() < entry.expiresAt &&
    ["buildings", "roads", "water", "vegetation", "coastlines"].every(
      (k) => Array.isArray(entry.data?.[k]) && entry.data[k].every(feature),
    ) &&
    entry.data.buildings.every(
      (f) => Number.isFinite(f.height) && f.height > 0 && f.height <= 500,
    )
  );
}
/** Small replaceable adapter. Only normalised visual data are stored; max four neighbourhoods. */
export function browserOSMCache(storage) {
  return {
    get(key) {
      if (validEntry(memory.get(key))) return memory.get(key).data;
      try {
        const entry = JSON.parse(storage?.getItem(key) || "null");
        if (validEntry(entry)) {
          memory.set(key, entry);
          return entry.data;
        }
      } catch {
        /* Cache disabled/corrupt: network remains available. */
      }
      return null;
    },
    set(key, data, acquiredAt, expiresAt) {
      const entry = { version: VERSION, savedAt: acquiredAt, expiresAt, data };
      memory.set(key, entry);
      while (memory.size > 4) memory.delete(memory.keys().next().value);
      try {
        const keys = Array.from({ length: storage?.length || 0 }, (_, i) =>
          storage.key(i),
        ).filter((k) => k?.startsWith(PREFIX) && k !== key);
        while (keys.length >= 4) storage.removeItem(keys.shift());
        storage?.setItem(key, JSON.stringify(entry));
      } catch {
        /* Quota/private mode: retain memory cache. */
      }
    },
  };
}

export async function loadOSM(
  config,
  { radius = OSM_RADIUS, signal, cache, fetcher = fetch, cacheOnly = false } = {},
) {
  overpassQuery(config, radius);
  if (!Number.isSafeInteger(config.obra_id) || config.obra_id <= 0 || radius !== OSM_RADIUS)
    throw new Error("Consulta OSM requer uma obra autorizada e o raio padrão.");
  const key = osmCacheKey(config, radius);
  const start = performance.now();
  const cached = cache?.get(key);
  if (cached)
    return {
      data: cached,
      cacheHit: true,
      processingMs: 0,
      acquisitionMs: performance.now() - start,
      acquisitionSource: "browser-cache",
    };
  const abort = new AbortController();
  const cancel = () => abort.abort();
  signal?.addEventListener("abort", cancel, { once: true });
  if (signal?.aborted) abort.abort();
  const timeout = setTimeout(cancel, cacheOnly ? 10000 : 125000);
  try {
    const api = new URL("../../api.php", import.meta.url);
    api.searchParams.set("action", "osm_contexto");
    api.searchParams.set("obra_id", config.obra_id);
    if (cacheOnly) api.searchParams.set("cache_only", "1");
    const response = await fetcher(api.href, {
      method: "GET",
      headers: { Accept: "application/json" },
      signal: abort.signal,
      credentials: "same-origin",
    });
    if (!response.ok) throw new Error(`Overpass HTTP ${response.status}`);
    const result = await response.json();
    const upstream = result.contexto_osm;
    if (cacheOnly && result.sucesso && upstream === null) return null;
    if (!result.sucesso || !upstream?.origin ||
      !Number.isFinite(upstream.origin.latitude) || !Number.isFinite(upstream.origin.longitude) ||
      Math.abs(upstream.origin.latitude - config.latitude) > 1e-7 ||
      Math.abs(upstream.origin.longitude - config.longitude) > 1e-7) {
      throw new Error("Localização da obra mudou. Atualize o visualizador.");
    }
    const payload = upstream.payload;
    const parsing = performance.now();
    const data = parseOSM(payload);
    if (abort.signal.aborted)
      throw new DOMException("Consulta cancelada", "AbortError");
    const acquiredAt = upstream.acquired_at * 1000, expiresAt = upstream.expires_at * 1000;
    if (!upstream.stale && Number.isFinite(acquiredAt) && Number.isFinite(expiresAt) && acquiredAt <= Date.now() && expiresAt > Date.now() && expiresAt <= acquiredAt + TTL)
      cache?.set(key, data, acquiredAt, expiresAt);
    return {
      data,
      cacheHit: false,
      processingMs: performance.now() - parsing,
      acquisitionMs: performance.now() - start,
      acquisitionSource: "server",
      serverCacheHit: Boolean(upstream?.cache_hit),
      stale: Boolean(upstream?.stale),
    };
  } finally {
    clearTimeout(timeout);
    signal?.removeEventListener("abort", cancel);
  }
}
