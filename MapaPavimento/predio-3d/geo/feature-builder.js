import * as THREE from "three";
import { pointInRing } from "./geo-coordinates.js?v=20261103-boundary-v4";
import { clipBufferGeometry } from "./geometry-clip.js?v=20261104-surface-v3";

export function localPolygon(polygon, project) {
  return {
    outer: polygon.outer.map(project),
    holes: polygon.holes.map((ring) => ring.map(project)),
  };
}
export function polygonShape(polygon) {
  // XY shape is (world X, -world Z); rotate -PI/2 to extrude towards +Y.
  const points = (ring) => ring.map((p) => new THREE.Vector2(p.x, -p.z));
  const shape = new THREE.Shape(points(polygon.outer));
  shape.holes = polygon.holes.map((ring) => new THREE.Path(points(ring)));
  return shape;
}
export function polygonGeometry(polygon, height = 0) {
  const shape = polygonShape(polygon);
  const geometry =
    height > 0
      ? new THREE.ExtrudeGeometry(shape, {
          depth: height,
          bevelEnabled: false,
          steps: 1,
          curveSegments: 1,
        })
      : new THREE.ShapeGeometry(shape, 1).toNonIndexed();
  geometry.rotateX(-Math.PI / 2);
  return geometry;
}
export function distanceBand(points, radius) {
  const nearest = Math.min(...points.map((p) => Math.hypot(p.x, p.z)));
  // Visibility is decided by geometric clipping, including the square corners.
  return nearest < 300 ? 0 : nearest < 700 ? 1 : 2;
}
/** Douglas–Peucker in metres. Closed rings keep at least three distinct vertices. */
export function simplifyPoints(points, tolerance) {
  if (points.length < 5 || tolerance <= 0) return points;
  const keep = new Set([0, points.length - 1]),
    pending = [[0, points.length - 1]];
  while (pending.length) {
    const [start, end] = pending.pop(),
      a = points[start],
      b = points[end];
    const dx = b.x - a.x,
      dz = b.z - a.z,
      length = dx * dx + dz * dz;
    let furthest = -1,
      maximum = tolerance * tolerance;
    for (let i = start + 1; i < end; i++) {
      const p = points[i],
        t = length
          ? Math.max(
              0,
              Math.min(1, ((p.x - a.x) * dx + (p.z - a.z) * dz) / length),
            )
          : 0;
      const distance = (p.x - a.x - t * dx) ** 2 + (p.z - a.z - t * dz) ** 2;
      if (distance > maximum) {
        maximum = distance;
        furthest = i;
      }
    }
    if (furthest >= 0) {
      keep.add(furthest);
      pending.push([start, furthest], [furthest, end]);
    }
  }
  const simplified = [...keep].sort((a, b) => a - b).map((i) => points[i]);
  return simplified.length >= 4 ? simplified : points;
}
export function overlapsSite(polygon, site) {
  const minX = Math.min(...polygon.outer.map((p) => p.x)),
    maxX = Math.max(...polygon.outer.map((p) => p.x));
  const minZ = Math.min(...polygon.outer.map((p) => p.z)),
    maxZ = Math.max(...polygon.outer.map((p) => p.z));
  return (
    (minX <= site.maxX &&
      maxX >= site.minX &&
      minZ <= site.maxZ &&
      maxZ >= site.minZ) ||
    pointInRing({ x: 0, z: 0 }, polygon.outer)
  );
}

/** Merge positions/normals into small batches, no per-building material/draw call. */
export class GeometryBatches {
  constructor(group, materials, bounds = null) {
    Object.assign(this, { group, materials, bounds });
    this.buckets = new Map();
  }
  add(key, geometry, shadows = false) {
    if (this.bounds) geometry = clipBufferGeometry(geometry, this.bounds);
    if (!geometry) return;
    const bucketKey = `${key}:${shadows}`;
    if (!this.buckets.has(bucketKey))
      this.buckets.set(bucketKey, { key, shadows, parts: [], vertices: 0 });
    const bucket = this.buckets.get(bucketKey);
    bucket.parts.push(geometry);
    bucket.vertices += geometry.attributes.position.count;
    if (bucket.vertices >= 90000) this.flush(bucketKey);
  }
  flush(key) {
    const bucket = this.buckets.get(key);
    if (!bucket?.parts.length) return;
    const positions = new Float32Array(bucket.vertices * 3),
      normals = new Float32Array(bucket.vertices * 3);
    let offset = 0;
    for (const part of bucket.parts) {
      positions.set(part.attributes.position.array, offset);
      normals.set(part.attributes.normal.array, offset);
      offset += part.attributes.position.array.length;
      part.dispose();
    }
    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute("position", new THREE.BufferAttribute(positions, 3));
    geometry.setAttribute("normal", new THREE.BufferAttribute(normals, 3));
    geometry.computeBoundingSphere();
    const mesh = new THREE.Mesh(geometry, this.materials[bucket.key]);
    mesh.name = `OSM · ${bucket.key}`;
    // Roads cover a large area in one batch. Render them before other transparent
    // objects so the batch centre cannot put them on top of the GLB or sun halo.
    if (bucket.key === "road") mesh.renderOrder = -1;
    mesh.castShadow = bucket.shadows;
    mesh.receiveShadow =
      bucket.shadows ||
      ["ground", "road", "FOREST", "GRASS", "PARK"].includes(bucket.key);
    mesh.raycast = () => {};
    this.group.add(mesh);
    this.buckets.delete(key);
  }
  finish() {
    for (const key of [...this.buckets.keys()]) this.flush(key);
  }
  dispose() {
    for (const bucket of this.buckets.values())
      bucket.parts.forEach((p) => p.dispose());
    this.buckets.clear();
  }
}
