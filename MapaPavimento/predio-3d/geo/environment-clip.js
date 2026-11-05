import polygonClipping from "./vendor/polygon-clipping.js?v=20261103-boundary-v4";
import { northDirection } from "../north-direction.js";

// Micrometre grid removes round-off from repeated North basis transforms at shared edges.
const coordinate = (n) => Math.round(n * 1e6) / 1e6;
export const toMulti = (polygons) =>
  polygons.map((p) =>
    [p.outer, ...p.holes].map((r) =>
      r.map((p) => [coordinate(p.x), coordinate(p.z)]),
    ),
  );
export const fromMulti = (multi) =>
  multi.map((p) => ({
    outer: p[0].map(([x, z]) => ({ x, z })),
    holes: p.slice(1).map((r) => r.map(([x, z]) => ({ x, z }))),
  }));
export const mapPolygons = (polygons, transform) =>
  polygons.map((p) => ({
    outer: p.outer.map(transform),
    holes: p.holes.map((r) => r.map(transform)),
  }));
export function signedArea(ring) {
  return (
    ring.reduce((sum, p, i) => {
      const q = ring[(i + 1) % ring.length];
      return sum + p.x * q.z - q.x * p.z;
    }, 0) / 2
  );
}
export const polygonArea = (polygon) =>
  Math.max(
    0,
    Math.abs(signedArea(polygon.outer)) -
      polygon.holes.reduce((sum, r) => sum + Math.abs(signedArea(r)), 0),
  );

/** Geographic E/N square transformed with exactly the solar north/east basis. */
export function createEnvironmentBounds(radius, northDegrees) {
  const n = northDirection(northDegrees),
    e = { x: -n.z, z: n.x };
  const toLocal = ({ x, z }) => ({
    x: x * e.x + z * n.x,
    z: x * e.z + z * n.z,
  });
  const toEN = (p) => ({ x: p.x * e.x + p.z * e.z, z: p.x * n.x + p.z * n.z });
  const enRing = [
    { x: -radius, z: -radius },
    { x: radius, z: -radius },
    { x: radius, z: radius },
    { x: -radius, z: radius },
    { x: -radius, z: -radius },
  ];
  const polygon = { outer: enRing.map(toLocal), holes: [] };
  return { radius, polygon, toLocal, toEN };
}

export function clipPolygons(polygons, bounds) {
  if (!polygons.length) return [];
  return mapPolygons(
    fromMulti(
      polygonClipping.intersection(
        toMulti(mapPolygons(polygons, bounds.toEN)),
        toMulti(mapPolygons([bounds.polygon], bounds.toEN)),
      ),
    ),
    bounds.toLocal,
  ).filter((p) => polygonArea(p) > 0.001);
}
export function differencePolygons(subject, subtract, bounds = null) {
  if (!subject.length || !subtract.length) return subject;
  if (bounds)
    return mapPolygons(
      differencePolygons(
        mapPolygons(subject, bounds.toEN),
        mapPolygons(subtract, bounds.toEN),
      ),
      bounds.toLocal,
    );
  return fromMulti(
    polygonClipping.difference(toMulti(subject), toMulti(subtract)),
  );
}
export function unionPolygons(a, b) {
  if (!a.length) return b;
  if (!b.length) return a;
  return fromMulti(polygonClipping.union(toMulti(a), toMulti(b)));
}
export function intersectPolygons(a,b) {
  if(!a.length||!b.length)return [];
  return fromMulti(polygonClipping.intersection(toMulti(a),toMulti(b)));
}

/** Liang–Barsky, preserving direction. EN square only, used for coastline graph. */
export function clipSegment(a, b, radius) {
  const dx = b.x - a.x,
    dz = b.z - a.z;
  let start = 0,
    end = 1;
  for (const [p, q] of [
    [-dx, a.x + radius],
    [dx, radius - a.x],
    [-dz, a.z + radius],
    [dz, radius - a.z],
  ]) {
    if (Math.abs(p) < 1e-12) {
      if (q < 0) return null;
      continue;
    }
    const t = q / p;
    if (p < 0) start = Math.max(start, t);
    else end = Math.min(end, t);
    if (start > end) return null;
  }
  if (end - start < 1e-10) return null;
  return [start, end].map((t) => ({
    x: Math.max(-radius, Math.min(radius, a.x + t * dx)),
    z: Math.max(-radius, Math.min(radius, a.z + t * dz)),
  }));
}
