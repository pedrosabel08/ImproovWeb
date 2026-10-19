import * as THREE from "three";

/** World-space bounds of drawable model surfaces. Helpers live outside root.
 * Axis-aligned local clipping is applied to the bounds too; oblique planes are
 * conservatively retained so a shadow caster is never cut out of the frustum. */
export function visibleModelBounds(root) {
  const result = new THREE.Box3();
  if (!root) return result;
  root.updateWorldMatrix(true, true);
  root.traverseVisible((mesh) => {
    if (!mesh.isMesh || !mesh.geometry || mesh.userData.isShadowCatcher) return;
    const materials = Array.isArray(mesh.material)
      ? mesh.material
      : [mesh.material];
    if (!mesh.geometry.boundingBox) mesh.geometry.computeBoundingBox();
    const world = mesh.geometry.boundingBox
      .clone()
      .applyMatrix4(mesh.matrixWorld);
    for (const material of materials) {
      if (!material || !material.visible || material.opacity <= 0.01) continue;
      const bounds = world.clone();
      if (!material.clipIntersection) {
        for (const plane of material.clippingPlanes || []) {
          for (const axis of ["x", "y", "z"]) {
            const normal = plane.normal[axis];
            if (Math.abs(Math.abs(normal) - 1) > 1e-7) continue;
            if (
              ["x", "y", "z"].some(
                (other) =>
                  other !== axis && Math.abs(plane.normal[other]) > 1e-7,
              )
            )
              continue;
            const limit = -plane.constant / normal;
            if (normal > 0)
              bounds.min[axis] = Math.max(bounds.min[axis], limit);
            else bounds.max[axis] = Math.min(bounds.max[axis], limit);
          }
        }
      }
      if (!bounds.isEmpty()) result.union(bounds);
    }
  });
  return result;
}

function isCeiling(object, root) {
  for (let node = object; node && node !== root; node = node.parent) {
    if (/(^|[^a-z])(?:tetos?|ceilings?)(?=[^a-z]|$)/i.test(node.name || ""))
      return true;
  }
  return false;
}

/** Visibilidade reversível; geometrias compartilhadas são recortadas só no material. */
export class FloorVisibility {
  constructor(root, floors, records) {
    this.floors = floors;
    this.entries = [];
    this.materials = new Map();
    root.updateMatrixWorld(true);
    root.traverse((mesh) => {
      if (!mesh.isMesh || mesh.userData.isShadowCatcher) return;
      this.entries.push({
        mesh,
        visible: mesh.visible,
        castShadow: mesh.castShadow,
        ceiling: isCeiling(mesh, root),
        floor: records.get(mesh)?.floor,
        bounds: new THREE.Box3().setFromObject(mesh, true),
      });
    });
  }
  restore() {
    for (const [mesh, original] of this.materials) {
      const clones = Array.isArray(mesh.material)
        ? mesh.material
        : [mesh.material];
      mesh.material = original;
      clones.forEach((material) => material.dispose());
    }
    this.materials.clear();
    this.entries.forEach(({ mesh, visible, castShadow }) => {
      mesh.visible = visible;
      mesh.castShadow = castShadow;
    });
  }
  apply(floor) {
    this.restore();
    const centerY = floor.bounds.getCenter(new THREE.Vector3()).y;
    const tolerance = Math.max(
      floor.bounds.getSize(new THREE.Vector3()).length() * 1e-5,
      1e-5,
    );
    const upper = new Set(
      this.floors
        .filter(
          (item) =>
            item.bounds.getCenter(new THREE.Vector3()).y > centerY + tolerance,
        )
        .map((item) => item.id),
    );
    const ceiling = floor.bounds.max.y + tolerance;
    const plane = new THREE.Plane(new THREE.Vector3(0, -1, 0), ceiling);
    for (const {
      mesh,
      visible,
      ceiling: isCeiling,
      floor: owner,
      bounds,
    } of this.entries) {
      if (!visible) continue;
      // Exported ceilings can live outside the floor group. Hide them in inspection
      // mode as well, retaining their original visibility for the building view.
      if (isCeiling) {
        mesh.visible = false;
        continue;
      }
      if (owner) {
        mesh.visible = !upper.has(owner);
        continue;
      }
      // A next-floor slab can start exactly at the cut height. Clipping it at
      // maxY+tolerance leaves its underside (a thin, opaque shadow blocker).
      // Classify the whole slab as above the cut, including export roundoff.
      if (bounds.min.y >= floor.bounds.max.y - tolerance) {
        mesh.visible = false;
        continue;
      }
      if (bounds.max.y <= ceiling) continue;
      const original = mesh.material;
      this.materials.set(mesh, original);
      const clones = (Array.isArray(original) ? original : [original]).map(
        (material) => {
          const clone = material.clone();
          clone.clippingPlanes = [...(material.clippingPlanes || []), plane];
          clone.clipIntersection = false;
          clone.clipShadows = true;
          return clone;
        },
      );
      mesh.material = Array.isArray(original) ? clones : clones[0];
    }
    for (const { mesh } of this.entries) {
      for (let node = mesh; node; node = node.parent) {
        if (!node.visible) {
          mesh.castShadow = false;
          break;
        }
      }
    }
    return upper;
  }
}
