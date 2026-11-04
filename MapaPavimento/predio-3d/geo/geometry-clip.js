import * as THREE from "three";

/** Final CPU guard for generated ribbons and 3D triangles; never a shader clip.
 * Polygon rings are already intersected before triangulation. This also clips
 * the width of road/river ribbons, not just their centre lines. */
export function clipBufferGeometry(geometry, bounds) {
  const positions = geometry.attributes.position,
    normals = geometry.attributes.normal,
    r = bounds.radius;
  const output = [],
    outNormals = [];
  let untouched = true;
  const lerp = (a, b, t) =>
    Object.fromEntries(
      Object.keys(a).map((k) => [k, a[k] + (b[k] - a[k]) * t]),
    );
  for (let i = 0; i < positions.count; i += 3) {
    let triangle = [];
    for (let j = 0; j < 3; j++) {
      const x = positions.getX(i + j),
        y = positions.getY(i + j),
        z = positions.getZ(i + j),
        p = bounds.toEN({ x, z });
      triangle.push({
        x,
        y,
        z,
        e: p.x,
        n: p.z,
        nx: normals.getX(i + j),
        ny: normals.getY(i + j),
        nz: normals.getZ(i + j),
      });
    }
    for (const [axis, sign] of [
      ["e", 1],
      ["e", -1],
      ["n", 1],
      ["n", -1],
    ]) {
      const input = triangle;
      triangle = [];
      for (let k = 0; k < input.length; k++) {
        const a = input[k],
          b = input[(k + 1) % input.length],
          da = r - sign * a[axis],
          db = r - sign * b[axis];
        if (da >= -1e-6) triangle.push(a);
        else untouched = false;
        if (da >= 0 !== db >= 0) triangle.push(lerp(a, b, da / (da - db)));
      }
      if (!triangle.length) break;
    }
    for (let k = 1; k < triangle.length - 1; k++)
      for (const p of [triangle[0], triangle[k], triangle[k + 1]]) {
        output.push(p.x, p.y, p.z);
        outNormals.push(p.nx, p.ny, p.nz);
      }
  }
  if (untouched) return geometry;
  geometry.dispose();
  if (!output.length) return null;
  const clipped = new THREE.BufferGeometry();
  clipped.setAttribute("position", new THREE.Float32BufferAttribute(output, 3));
  clipped.setAttribute(
    "normal",
    new THREE.Float32BufferAttribute(outNormals, 3),
  );
  return clipped;
}
