import { pointInRing } from "./geo-coordinates.js?v=20261103-boundary-v4";

const validPoint = (p) =>
  p &&
  Number.isFinite(p.lat) &&
  Math.abs(p.lat) <= 90 &&
  Number.isFinite(p.lon) &&
  Math.abs(p.lon) <= 180;
const same = (a, b) =>
  a && b && Math.abs(a.lat - b.lat) < 1e-8 && Math.abs(a.lon - b.lon) < 1e-8;
const closed = (ring) => ring.length >= 4 && same(ring[0], ring.at(-1));

/** Overpass relations often split a ring into several member ways, sometimes reversed. */
function joinRings(parts) {
  const pending = parts.map((p) => [...p]),
    rings = [];
  while (pending.length) {
    let ring = pending.pop(),
      changed = true;
    while (!closed(ring) && changed) {
      changed = false;
      for (let i = 0; i < pending.length; i++) {
        let part = pending[i];
        if (same(ring.at(-1), part.at(-1))) part = [...part].reverse();
        if (same(ring.at(-1), part[0])) ring.push(...part.slice(1));
        else {
          if (same(ring[0], part[0])) part = [...part].reverse();
          if (same(ring[0], part.at(-1))) ring.unshift(...part.slice(0, -1));
          else continue;
        }
        pending.splice(i, 1);
        changed = true;
        break;
      }
    }
    if (closed(ring)) rings.push(ring);
  }
  return rings;
}

export function buildingHeight(tags) {
  const raw = String(tags.height ?? "").trim();
  const match = raw.match(
    /^(\d+(?:[.,]\d+)?)\s*(m|metres?|meters?|ft|feet|')?$/i,
  );
  if (match) {
    const height =
      Number(match[1].replace(",", ".")) *
      (/^(ft|feet|')$/i.test(match[2] || "") ? 0.3048 : 1);
    if (height > 0 && height <= 500) return height;
  }
  const levels = Number(
    String(tags["building:levels"] || "").replace(",", "."),
  );
  if (levels > 0 && levels <= 150) return levels * 3;
  return tags.building === "house" || tags.building === "detached"
    ? 6
    : tags.building === "residential"
      ? 9
      : 8;
}

function category(tags) {
  if (tags.natural === "coastline") return "coastlines";
  if (tags.building && tags.building !== "no") return "buildings";
  if (
    tags.natural === "water" ||
    tags.water ||
    tags.landuse === "reservoir" ||
    tags.waterway
  )
    return "water";
  if (
    tags.leisure === "park" ||
    /^(forest|grass)$/.test(tags.landuse) ||
    /^(wood|grassland)$/.test(tags.natural)
  )
    return "vegetation";
  if (tags.highway && tags.area !== "yes") return "roads";
  return null;
}

/** Normalised geographic features, no POI/name payload or raw Overpass dependency downstream. */
export function parseOSM(payload) {
  if (!payload || !Array.isArray(payload.elements) || payload.remark)
    throw new Error("Resposta Overpass incompleta ou inválida.");
  const result = {
    buildings: [],
    roads: [],
    water: [],
    vegetation: [],
    coastlines: [],
  };
  const claimed = new Set();
  const geometry = (points) =>
    Array.isArray(points) && points.every(validPoint)
      ? points.map(({ lat, lon }) => ({ lat, lon }))
      : [];
  const elements = [...payload.elements].sort(
    (a, b) =>
      (a.type === "relation" ? -1 : 0) - (b.type === "relation" ? -1 : 0),
  );
  for (const element of elements) {
    const tags = element.tags || {},
      kind = category(tags);
    if (
      !kind ||
      (element.type === "way" && claimed.has(`${kind}:${element.id}`))
    )
      continue;
    let polygons = [],
      line = null;
    if (element.type === "relation" && tags.type === "multipolygon") {
      const members = (element.members || []).filter((m) => m.type === "way");
      const outers = joinRings(
        members
          .filter((m) => m.role !== "inner")
          .map((m) => geometry(m.geometry))
          .filter((p) => p.length > 1),
      );
      const inners = joinRings(
        members
          .filter((m) => m.role === "inner")
          .map((m) => geometry(m.geometry))
          .filter((p) => p.length > 1),
      );
      polygons = outers.map((outer) => ({
        outer,
        holes: inners.filter((hole) =>
          pointInRing(
            { x: hole[0].lon, z: hole[0].lat },
            outer.map((p) => ({ x: p.lon, z: p.lat })),
          ),
        ),
      }));
      if (polygons.length)
        members.forEach((m) => claimed.add(`${kind}:${m.ref}`));
    } else if (element.type === "way") {
      const points = geometry(element.geometry);
      if (
        kind === "roads" ||
        kind === "coastlines" ||
        (kind === "water" && tags.waterway && !closed(points))
      )
        line = points;
      else if (closed(points)) polygons = [{ outer: points, holes: [] }];
    }
    if (!polygons.length && (!line || line.length < 2)) continue;
    const feature = { id: `${element.type}/${element.id}`, polygons, line };
    feature.visualTags = Object.fromEntries(
      [
        "building",
        "highway",
        "natural",
        "landuse",
        "leisure",
        "water",
        "waterway",
      ]
        .filter((key) => tags[key])
        .map((key) => [key, tags[key]]),
    );
    feature.category =
      kind === "vegetation"
        ? tags.natural === "wood" || tags.landuse === "forest"
          ? "FOREST"
          : tags.leisure === "park"
            ? "PARK"
            : "GRASS"
        : kind === "water"
          ? "WATER_INLAND"
          : kind === "coastlines"
            ? "SEA"
            : kind.toUpperCase();
    if (kind === "buildings") feature.height = buildingHeight(tags);
    if (kind === "roads") feature.highway = tags.highway;
    if (kind === "water") feature.waterway = tags.waterway || "water";
    if (kind === "vegetation")
      feature.cover = tags.landuse || tags.natural || tags.leisure;
    result[kind].push(feature);
  }
  return result;
}
