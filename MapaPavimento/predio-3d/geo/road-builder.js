import * as THREE from "three";
import {
  distanceBand,
  simplifyPoints,
} from "./feature-builder.js?v=20261103-boundary-v4";
import { SURFACE_Y } from "./environment-config.js?v=20261103-boundary-v4";
import { roadLayer } from "./surface-classification.js?v=1";

const WIDTHS = {
  motorway: 20,
  motorway_link: 9,
  trunk: 18,
  trunk_link: 8,
  primary: 14,
  primary_link: 8,
  secondary: 11,
  secondary_link: 7,
  tertiary: 9,
  tertiary_link: 6,
  residential: 7,
  unclassified: 7,
  living_street: 5,
  service: 4,
  track: 3,
  pedestrian: 3,
  footway: 1.8,
  path: 1.5,
  cycleway: 2,
  steps: 1.5,
};

/** Connected ribbon with limited miter joins. Two triangles per segment, flat +Y normals. */
export function ribbonGeometry(
  points,
  width,
  elevation = SURFACE_Y.road,
  lateralOffset = 0,
) {
  const clean = points.filter(
    (p, i) =>
      i === 0 ||
      Math.hypot(p.x - points[i - 1].x, p.z - points[i - 1].z) > 0.01,
  );
  if (clean.length < 2) return null;
  const edges = clean.map((p, i) => {
    const a = clean[Math.max(0, i - 1)],
      b = clean[Math.min(clean.length - 1, i + 1)];
    const direction = new THREE.Vector2(b.x - a.x, b.z - a.z).normalize();
    const next = clean[Math.min(clean.length - 1, i + 1)];
    const segment = new THREE.Vector2(next.x - p.x, next.z - p.z);
    if (segment.lengthSq() === 0) segment.copy(direction);
    else segment.normalize();
    const miter = Math.max(0.5, Math.abs(direction.dot(segment)));
    const half = width / 2 / miter;
    const dx = -direction.y * half;
    const dz = direction.x * half;
    const shift = lateralOffset / miter;
    const centre = shift
      ? { x: p.x - direction.y * shift, z: p.z + direction.x * shift }
      : p;
    return [
      { x: centre.x + dx, z: centre.z + dz },
      { x: centre.x - dx, z: centre.z - dz },
    ];
  });
  const positions = [];
  for (let i = 1; i < edges.length; i++) {
    const [a, b] = edges[i - 1],
      [c, d] = edges[i];
    for (const p of [a, c, b, b, c, d]) positions.push(p.x, elevation, p.z);
  }
  const geometry = new THREE.BufferGeometry();
  geometry.setAttribute(
    "position",
    new THREE.Float32BufferAttribute(positions, 3),
  );
  geometry.computeVertexNormals();
  return geometry;
}

export function roadDimensions(feature, project, radius) {
  const width = WIDTHS[feature.highway];
  if (!width || !feature.line) return null;
  const points = feature.line.map(project),
    band = distanceBand(points, radius);
  if (band < 0) return null;
  return { points: simplifyPoints(points, [0, 0.6, 1.5][band]), width };
}

export function buildRoad(
  feature,
  { project, batches, metrics, radius, terrain, mappedSidewalks },
) {
  if (mappedSidewalks?.has(feature)) return;
  const road = roadDimensions(feature, project, radius);
  if (!road) return;
  let geometry = ribbonGeometry(road.points, road.width);
  if (geometry) {
    if (terrain) geometry = terrain.drape(geometry, SURFACE_Y.road);
    batches.add(roadLayer(feature), geometry);
    metrics.roads++;
  }
}
