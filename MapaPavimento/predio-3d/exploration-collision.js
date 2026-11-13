import * as THREE from "three";

const CELL = 6;
const UP = new THREE.Vector3(0, 1, 0);
const DOWN = new THREE.Vector3(0, -1, 0);
const RADIUS = 0.27;
const HEIGHT = 1.78;
const STEP = 0.25;
const DROP = 0.42;
const WALKABLE = 0.68;
const pause = () => new Promise((resolve) => setTimeout(resolve, 0));
const cell = (value) => Math.floor(value / CELL);
const triangleBounds = (a, b, c) => [
  Math.min(a.x, b.x, c.x),
  Math.min(a.z, b.z, c.z),
  Math.max(a.x, b.x, c.x),
  Math.max(a.z, b.z, c.z),
];

function layer(mesh) {
  return mesh.userData.geoLayer || mesh.name.replace(/^OSM · /, "");
}

// Squared distance between finite segments (including parallel edges).
function segmentDistance(a, b, c, d, u, v, w) {
  u.subVectors(b, a);
  v.subVectors(d, c);
  w.subVectors(a, c);
  const aa = u.lengthSq(),
    bb = u.dot(v),
    cc = v.lengthSq(),
    dd = u.dot(w),
    ee = v.dot(w);
  const det = aa * cc - bb * bb;
  let s =
    det > 1e-10 ? THREE.MathUtils.clamp((bb * ee - cc * dd) / det, 0, 1) : 0;
  const t = cc > 1e-10 ? THREE.MathUtils.clamp((bb * s + ee) / cc, 0, 1) : 0;
  s = aa > 1e-10 ? THREE.MathUtils.clamp((bb * t - dd) / aa, 0, 1) : 0;
  return w.addScaledVector(u, s).addScaledVector(v, -t).lengthSq();
}

/** Prepared only for Explorar. World-space triangle grid; no copies of scene meshes or materials. */
export class ExplorationCollision {
  constructor({ meshes, group, terrain, modelSurface }) {
    this.meshes = meshes;
    this.group = group;
    this.terrain = terrain;
    this.modelSurface = modelSurface;
    this.cells = new Map();
    this.large = [];
    this.nearby = new Set();
    this.tri = new THREE.Triangle();
    this.ray = new THREE.Ray();
    this.normal = new THREE.Vector3();
    this.point = new THREE.Vector3();
    this.start = new THREE.Vector3();
    this.end = new THREE.Vector3();
    this.u = new THREE.Vector3();
    this.v = new THREE.Vector3();
    this.w = new THREE.Vector3();
    this.local = new THREE.Vector3();
    this.cameraRay = new THREE.Vector3();
    this.inverse = group?.matrixWorld.clone().invert();
  }

  async prepare(signal) {
    const sources = this.meshes.map((mesh) => ({ mesh, kind: "model" }));
    // Live DEM already has a surface index. A server-cached DEM only has the
    // actual rendered triangles, so recover support from those exact triangles.
    this.group?.traverseVisible((mesh) => {
      if (!mesh.isMesh) return;
      const key = layer(mesh);
      if (/^buildings?/i.test(key)) sources.push({ mesh, kind: "obstacle" });
      if (!this.terrain && ["ground", "model-ground"].includes(key))
        sources.push({ mesh, kind: "ground" });
    });
    let deadline = performance.now() + 10;
    for (const { mesh, kind } of sources) {
      if (!mesh.isMesh || !mesh.geometry.attributes.position) continue;
      const surface =
        kind === "model" && this.modelSurface
          ? this.modelSurface(mesh)
          : mesh.material;
      if (!surface) continue;
      if (kind === "model" && !this.modelSurface) {
        let hidden = false;
        for (let parent = mesh; parent; parent = parent.parent)
          if (!parent.visible) {
            hidden = true;
            break;
          }
        if (hidden) continue;
      }
      mesh.updateWorldMatrix(true, false);
      const {
        index,
        attributes: { position },
      } = mesh.geometry;
      const matrix = mesh.matrixWorld.clone();
      const materialGroups = Array.isArray(surface)
        ? mesh.geometry.groups
        : [
            {
              start: 0,
              count: index?.count ?? position.count,
              materialIndex: 0,
            },
          ];
      for (const { start, count, materialIndex } of materialGroups) {
        const material = Array.isArray(surface)
          ? surface[materialIndex]
          : surface;
        if (!material || material.visible === false || material.opacity <= 0.01)
          continue;
        const data = {
          position,
          index,
          matrix,
          kind,
          planes: material.clippingPlanes,
          clipIntersection: material.clipIntersection,
        };
        const first = Math.max(start, mesh.geometry.drawRange.start);
        const last = Math.min(
          start + count,
          mesh.geometry.drawRange.start + mesh.geometry.drawRange.count,
          index?.count ?? position.count,
        );
        for (let offset = first; offset + 2 < last; offset += 3) {
          if (offset % 768 === first % 768 && performance.now() > deadline) {
            await pause();
            signal.throwIfAborted();
            deadline = performance.now() + 10;
          }
          const ref = { data, offset };
          const { a, b, c } = this.read(ref);
          if (data.planes?.length && !data.clipIntersection) {
            // FloorVisibility cuts tall meshes with world-space planes. Index the
            // clipped triangles so neither Josh nor the camera hit invisible pieces.
            let vertices = [a, b, c];
            for (const plane of data.planes) {
              const clipped = [];
              for (let i = 0; i < vertices.length; i++) {
                const u = vertices[i],
                  v = vertices[(i + 1) % vertices.length];
                const d = plane.distanceToPoint(u),
                  e = plane.distanceToPoint(v);
                if (d >= 0) clipped.push(u);
                if (d < 0 !== e < 0)
                  clipped.push(u.clone().lerp(v, d / (d - e)));
              }
              vertices = clipped;
            }
            if (
              vertices.length !== 3 ||
              vertices[0] !== a ||
              vertices[1] !== b ||
              vertices[2] !== c
            ) {
              for (let i = 1; i + 1 < vertices.length; i++) {
                const triangle = new THREE.Triangle(
                  vertices[0].clone(),
                  vertices[i].clone(),
                  vertices[i + 1].clone(),
                );
                this.insert(
                  { data, triangle },
                  ...triangleBounds(triangle.a, triangle.b, triangle.c),
                );
              }
              continue;
            }
          }
          this.insert(ref, ...triangleBounds(a, b, c));
        }
      }
    }
    signal.throwIfAborted();
  }

  insert(ref, minX, minZ, maxX, maxZ) {
    if (![minX, minZ, maxX, maxZ].every(Number.isFinite)) return;
    Object.assign(ref, { minX, minZ, maxX, maxZ });
    const x0 = cell(minX),
      x1 = cell(maxX),
      z0 = cell(minZ),
      z1 = cell(maxZ);
    if ((x1 - x0 + 1) * (z1 - z0 + 1) > 64) this.large.push(ref);
    else
      for (let x = x0; x <= x1; x++)
        for (let z = z0; z <= z1; z++) {
          const key = `${x}/${z}`;
          let bucket = this.cells.get(key);
          if (!bucket) this.cells.set(key, (bucket = []));
          bucket.push(ref);
        }
  }

  read({ data: { position, index, matrix }, offset, triangle }) {
    if (triangle) return triangle;
    const { a, b, c } = this.tri;
    a.fromBufferAttribute(
      position,
      index ? index.getX(offset) : offset,
    ).applyMatrix4(matrix);
    b.fromBufferAttribute(
      position,
      index ? index.getX(offset + 1) : offset + 1,
    ).applyMatrix4(matrix);
    c.fromBufferAttribute(
      position,
      index ? index.getX(offset + 2) : offset + 2,
    ).applyMatrix4(matrix);
    return this.tri;
  }

  query(minX, minZ, maxX, maxZ) {
    const hits = this.nearby;
    hits.clear();
    const overlaps = (ref) =>
      ref.minX <= maxX &&
      ref.maxX >= minX &&
      ref.minZ <= maxZ &&
      ref.maxZ >= minZ;
    for (let x = cell(minX); x <= cell(maxX); x++)
      for (let z = cell(minZ); z <= cell(maxZ); z++) {
        const bucket = this.cells.get(`${x}/${z}`);
        if (bucket) for (const ref of bucket) if (overlaps(ref)) hits.add(ref);
      }
    for (const ref of this.large) if (overlaps(ref)) hits.add(ref);
    return hits;
  }

  demHeight(x, z) {
    if (!this.terrain || !this.group) return null;
    const local = this.local
      .set(x, this.group.position.y, z)
      .applyMatrix4(this.inverse);
    const height = this.terrain.mesh
      ? this.terrain.mesh.sample(local.x, local.z)
      : this.terrain.sample(local.x, local.z);
    return height == null
      ? null
      : local.setY(height).applyMatrix4(this.group.matrixWorld).y;
  }

  support(x, z, maxY, minY = -Infinity) {
    const dem = this.demHeight(x, z);
    if (dem != null && dem > maxY) return null;
    let result = dem != null && dem <= maxY && dem >= minY ? dem : -Infinity;
    this.ray.origin.set(x, maxY + 0.001, z);
    this.ray.direction.copy(DOWN);
    for (const ref of this.query(x, z, x, z)) {
      if (ref.data.kind === "obstacle") continue;
      const t = this.read(ref);
      if (t.getNormal(this.normal).y < WALKABLE) continue;
      if (ref.data.kind === "ground" && Math.max(t.a.y, t.b.y, t.c.y) > maxY) {
        this.ray.origin.y = Math.max(t.a.y, t.b.y, t.c.y) + 0.001;
        const surface = this.ray.intersectTriangle(
          t.a,
          t.b,
          t.c,
          false,
          this.point,
        );
        this.ray.origin.y = maxY + 0.001;
        if (surface && surface.y > maxY) return null;
      }
      const hit = this.ray.intersectTriangle(t.a, t.b, t.c, false, this.point);
      if (hit && hit.y >= minY && hit.y <= maxY)
        result = Math.max(result, hit.y);
    }
    return Number.isFinite(result) ? result : null;
  }

  blocked(x, y, z) {
    // Small skin prevents coplanar floors from counting as penetrations.
    this.start.set(x, y + RADIUS + 0.035, z);
    this.end.set(x, y + HEIGHT - RADIUS, z);
    this.ray.set(this.start, UP);
    const threshold = (RADIUS - 0.005) ** 2;
    for (const ref of this.query(
      x - RADIUS,
      z - RADIUS,
      x + RADIUS,
      z + RADIUS,
    )) {
      if (ref.data.kind === "ground") continue;
      const t = this.read(ref),
        { a, b, c } = t;
      if (
        Math.max(a.y, b.y, c.y) <= y + 0.02 ||
        Math.min(a.y, b.y, c.y) >= y + HEIGHT
      )
        continue;
      const hit = this.ray.intersectTriangle(a, b, c, false, this.point);
      if (hit && hit.y <= this.end.y) return true;
      if (
        t
          .closestPointToPoint(this.start, this.point)
          .distanceToSquared(this.start) < threshold ||
        t
          .closestPointToPoint(this.end, this.point)
          .distanceToSquared(this.end) < threshold ||
        segmentDistance(this.start, this.end, a, b, this.u, this.v, this.w) <
          threshold ||
        segmentDistance(this.start, this.end, b, c, this.u, this.v, this.w) <
          threshold ||
        segmentDistance(this.start, this.end, c, a, this.u, this.v, this.w) <
          threshold
      )
        return true;
    }
    return false;
  }

  tryMove(position, dx, dz) {
    const x = position.x + dx,
      z = position.z + dz;
    const y = this.support(x, z, position.y + STEP, position.y - DROP);
    if (y == null) return false;
    if (this.blocked(x, y, z)) {
      // At a stair riser, the capsule reaches the next tread before its centre
      // does. Check just past the capsule so shallow stairs remain walkable.
      const distance = Math.hypot(dx, dz);
      if (!distance) return false;
      const aheadX = (dx / distance) * RADIUS,
        aheadZ = (dz / distance) * RADIUS;
      const stepY = this.support(
        x + aheadX,
        z + aheadZ,
        position.y + STEP,
        y + 0.03,
      );
      if (stepY != null && !this.blocked(x, stepY, z)) {
        position.set(x, stepY, z);
        return true;
      }
      if (
        y >= position.y - 0.03 ||
        this.blocked(x, position.y, z) ||
        this.support(
          x - aheadX,
          z - aheadZ,
          position.y + 0.01,
          position.y - 0.03,
        ) == null
      )
        return false;
      position.set(x, position.y, z);
      return true;
    }
    position.set(x, y, z);
    return true;
  }

  cameraDistance(target, desired) {
    const direction = this.cameraRay.subVectors(desired, target);
    let limit = direction.length();
    direction.normalize();
    this.ray.set(target, direction);
    for (const ref of this.query(
      Math.min(target.x, desired.x),
      Math.min(target.z, desired.z),
      Math.max(target.x, desired.x),
      Math.max(target.z, desired.z),
    )) {
      const { a, b, c } = this.read(ref);
      const hit = this.ray.intersectTriangle(a, b, c, false, this.point);
      if (hit) limit = Math.min(limit, hit.distanceTo(target) - 0.16);
    }
    if (this.terrain)
      for (let d = 0.25; d < limit; d += 0.25) {
        const p = this.point.copy(target).addScaledVector(direction, d);
        const y = this.demHeight(p.x, p.z);
        if (y != null && p.y < y + 0.15) {
          limit = d - 0.15;
          break;
        }
      }
    return Math.max(0.08, limit);
  }

  place({ point, normalY, towardCamera }) {
    const floor = normalY >= WALKABLE;
    const heading = Math.atan2(towardCamera.x, towardCamera.z);
    // For a wall, find the floor on the visible side. For a floor, stay on the
    // clicked level and shift only enough to clear a nearby wall or rail.
    const minY = point.y - (floor ? 0.42 : 2.9);
    const radii = floor ? [0, 0.35, 0.65, 1] : [0.4, 0.7, 1.15];
    for (const radius of radii) {
      const angles =
        radius === 0
          ? [0]
          : floor
            ? [0, -0.8, 0.8, -1.6, 1.6, Math.PI]
            : [0, -0.55, 0.55, -1, 1];
      for (const angle of angles) {
        const x = point.x + Math.sin(heading + angle) * radius;
        const z = point.z + Math.cos(heading + angle) * radius;
        const y = this.support(x, z, point.y + STEP, minY);
        if (y != null && !this.blocked(x, y, z))
          return new THREE.Vector3(x, y, z);
      }
    }
    return null;
  }
}
