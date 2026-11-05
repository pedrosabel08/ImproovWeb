import { northDirection } from "../north-direction.js";

export const DEM_ZOOM = 12, DEM_SIZE = 512;
const memory = new Map();
export function demPixel(lat, lon) {
  const size = DEM_SIZE * 2 ** DEM_ZOOM;
  return { x: (lon + 180) / 360 * size,
    y: (1 - Math.asinh(Math.tan(lat * Math.PI / 180)) / Math.PI) / 2 * size };
}
export function demTiles(config) {
  if (!Number.isFinite(config.latitude) || Math.abs(config.latitude) >= 84 ||
      !Number.isFinite(config.longitude) || Math.abs(config.longitude) > 180)
    throw new Error("Localização DEM indisponível.");
  const dlat = 1005 / 111195.0802, dlon = dlat / Math.cos(config.latitude * Math.PI / 180);
  const a = demPixel(config.latitude + dlat, config.longitude - dlon);
  const b = demPixel(config.latitude - dlat, config.longitude + dlon), tiles = [], n = 2 ** DEM_ZOOM;
  for (let x = Math.floor((a.x - 1) / DEM_SIZE); x <= Math.floor((b.x + 1) / DEM_SIZE); x++)
    for (let y = Math.floor((a.y - 1) / DEM_SIZE); y <= Math.floor((b.y + 1) / DEM_SIZE); y++)
      if (y >= 0 && y < n) tiles.push({x: ((x % n) + n) % n, y});
  if (tiles.length > 16) throw new Error("Área DEM excessiva.");
  return tiles;
}
export const terrariumHeight = (r, g, b) => r * 256 + g + b / 256 - 32768;

async function decodeTile(blob) {
  const bitmap = await createImageBitmap(blob, {colorSpaceConversion: "none", premultiplyAlpha: "none"});
  try {
    if (bitmap.width !== DEM_SIZE || bitmap.height !== DEM_SIZE) throw new Error("Dimensões DEM inválidas.");
    const canvas = document.createElement("canvas"); canvas.width = canvas.height = DEM_SIZE;
    const context = canvas.getContext("2d", {willReadFrequently: true});
    context.drawImage(bitmap, 0, 0);
    const rgba = context.getImageData(0, 0, DEM_SIZE, DEM_SIZE).data;
    const heights = new Float32Array(DEM_SIZE * DEM_SIZE);
    for (let i = 0; i < heights.length; i++) {
      if (rgba[i * 4 + 3] !== 255) throw new Error("DEM com lacunas.");
      heights[i] = terrariumHeight(...rgba.subarray(i * 4, i * 4 + 3));
      if (heights[i] < -12000 || heights[i] > 9000) throw new Error("Altitude DEM inválida.");
    }
    return heights;
  } finally { bitmap.close(); }
}

/** All sampling is local. A handful of cached raster downloads, never a request per vertex. */
export async function loadElevation(config, {signal, fetcher = fetch, decode = decodeTile} = {}) {
  if (!Number.isInteger(config.obra_id) || config.obra_id < 1) throw new Error("Obra DEM não cadastrada.");
  const controller = new AbortController(), abort = () => controller.abort();
  signal?.addEventListener("abort", abort, {once: true});
  const timer = setTimeout(abort, 15000);
  try {
    if (signal?.aborted) throw new DOMException("Cancelado", "AbortError");
    const tiles = demTiles(config), loaded = new Map(); let cacheHits = 0;
    await Promise.all(tiles.map(async ({x, y}) => {
      const key = `${DEM_ZOOM}/${x}/${y}`, cached = memory.get(key);
      let heights;
      if (cached && Date.now() - cached.time < 86400000) { heights = cached.heights; cacheHits++; }
      else {
        const url = new URL("../../api.php", import.meta.url);
        url.search = new URLSearchParams({action: "dem_tile", obra_id: config.obra_id, x, y});
        const response = await fetcher(url, {credentials: "same-origin", signal: controller.signal});
        if (!response.ok) throw new Error(`DEM HTTP ${response.status}`);
        heights = await decode(await response.blob());
        if (controller.signal.aborted) throw new DOMException("Cancelado", "AbortError");
        memory.set(key, {heights, time: Date.now()});
        while (memory.size > 16) memory.delete(memory.keys().next().value);
      }
      loaded.set(`${x}/${y}`, heights);
    }));
    if (controller.signal.aborted) throw new DOMException("Cancelado", "AbortError");
    const pixel = (x, y) => {
      const n = 2 ** DEM_ZOOM;
      const tile = loaded.get(`${((Math.floor(x / DEM_SIZE) % n) + n) % n}/${Math.floor(y / DEM_SIZE)}`);
      if (!tile) throw new Error("Amostra fora do recorte DEM.");
      return tile[((y % DEM_SIZE + DEM_SIZE) % DEM_SIZE) * DEM_SIZE + ((x % DEM_SIZE + DEM_SIZE) % DEM_SIZE)];
    };
    const sampleGeo = (lat, lon) => {
      const p = demPixel(lat, lon), x = p.x - .5, y = p.y - .5;
      const ix = Math.floor(x), iy = Math.floor(y), u = x - ix, v = y - iy;
      return (pixel(ix, iy) * (1 - u) + pixel(ix + 1, iy) * u) * (1 - v) +
        (pixel(ix, iy + 1) * (1 - u) + pixel(ix + 1, iy + 1) * u) * v;
    };
    const n = northDirection(config.norte_graus), metre = 6371008.8 * Math.PI / 180;
    return {source: "Mapterhorn / Copernicus GLO-30", tiles: tiles.length, cacheHits,
      reference: sampleGeo(config.latitude, config.longitude),
      sample: (x, z) => sampleGeo(config.latitude + (x * n.x + z * n.z) / metre,
        config.longitude + (-x * n.z + z * n.x) / (metre * Math.cos(config.latitude * Math.PI / 180)))};
  } catch (error) { controller.abort(); throw error; }
  finally { clearTimeout(timer); signal?.removeEventListener("abort", abort); }
}
