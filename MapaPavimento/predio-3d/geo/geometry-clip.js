import * as THREE from "three";
import { crossXZ, resolvedFace } from "./surface-mesh.js?v=20261104-surface-v3";

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
    for (let k = 1; k < triangle.length - 1; k++) {
      const face=[triangle[0],triangle[k],triangle[k+1]].map(p=>({...p,x:Math.fround(p.x),y:Math.fround(p.y),z:Math.fround(p.z)}));
      if(!face.every(p=>[p.x,p.y,p.z,p.nx,p.ny,p.nz].every(Number.isFinite)))throw new Error('Coordenada ou normal de recorte inválida.');
      const [a,b,c]=face;
      const nx=(b.y-a.y)*(c.z-a.z)-(b.z-a.z)*(c.y-a.y),ny=crossXZ(a,b,c),nz=(b.x-a.x)*(c.y-a.y)-(b.y-a.y)*(c.x-a.x);
      // Preserve vertical walls. Top faces must remain resolved and upward
      // AFTER the final Float32 boundary clip, not only in their CPU builder.
      if(Math.hypot(nx,ny,nz)<=1e-10||(face.every(p=>p.ny>0)&&(!resolvedFace(face)||ny<=0))) {untouched=false;continue;}
      for (const p of face) {
        output.push(p.x, p.y, p.z);
        outNormals.push(p.nx, p.ny, p.nz);
      }
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
