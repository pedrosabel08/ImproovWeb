import * as THREE from "three";

export function solarOpaqueMaterial(material) {
  return !!material && material.visible !== false && !(material.transmission >= .95) && !(material.transparent && material.opacity <= .01);
}

function visibleInModel(mesh, root) {
  for (let node = mesh; node; node = node.parent) {
    if (!node.visible) return false;
    if (node === root) return true;
  }
  return false;
}

/** Campo de raios PARALELOS: direção de viagem = oposta ao vetor empreendimento → Sol.
 * Amostra a projeção do bounding box num plano perpendicular ao Sol, independente da câmera. */
export function createSolarRayField(bounds, sunDirection, columns = 5, rows = 5) {
  if (!bounds || bounds.isEmpty() || sunDirection.y <= 0) return [];
  const towardSun = new THREE.Vector3(sunDirection.x, sunDirection.y, sunDirection.z).normalize();
  const direction = towardSun.clone().negate();
  const center = bounds.getCenter(new THREE.Vector3());
  const radius = Math.max(bounds.getSize(new THREE.Vector3()).length() / 2, .5);
  const up = Math.abs(towardSun.y) > .98 ? new THREE.Vector3(0, 0, 1) : new THREE.Vector3(0, 1, 0);
  const right = new THREE.Vector3().crossVectors(up, towardSun).normalize();
  const vertical = new THREE.Vector3().crossVectors(towardSun, right).normalize();
  let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
  for (const x of [bounds.min.x, bounds.max.x]) for (const y of [bounds.min.y, bounds.max.y]) for (const z of [bounds.min.z, bounds.max.z]) {
    const offset = new THREE.Vector3(x, y, z).sub(center);
    minX = Math.min(minX, offset.dot(right)); maxX = Math.max(maxX, offset.dot(right));
    minY = Math.min(minY, offset.dot(vertical)); maxY = Math.max(maxY, offset.dot(vertical));
  }
  const rays = [];
  for (let row = 0; row < rows; row++) for (let column = 0; column < columns; column++) {
    const u = minX + (maxX - minX) * (column + .5) / columns;
    const v = minY + (maxY - minY) * (row + .5) / rows;
    rays.push({ origin: center.clone().addScaledVector(right, u).addScaledVector(vertical, v).addScaledVector(towardSun, radius * 1.8), direction: direction.clone(), far: radius * 8, right, radius });
  }
  return rays;
}

/** Índice de caixas em coordenadas do GLB, criado uma vez por carregamento.
 * O material de teste é DoubleSide para detectar paredes/lajes mesmo pelo verso.
 * O material original do mesh é restaurado no MESMO bloco síncrono, inclusive em erro;
 * nenhum material compartilhado, geometria, layer ou estado visual é modificado permanentemente. */
export class SolarRayIndex {
  constructor(root) {
    this.root = root;
    this.entries = [];
    this.testMaterial = new THREE.MeshBasicMaterial({ side: THREE.DoubleSide });
    this.raycaster = new THREE.Raycaster();
    this.boxPoint = new THREE.Vector3();
    this.hits = [];
    root.updateWorldMatrix(true, true);
    root.traverse(mesh => {
      if (!mesh.isMesh || !mesh.geometry || !mesh.castShadow) return;
      const materials = Array.isArray(mesh.material) ? mesh.material : [mesh.material];
      if (!materials.some(solarOpaqueMaterial)) return;
      const bounds = new THREE.Box3().setFromObject(mesh, true);
      if (bounds.isEmpty()) return;
      this.entries.push({ mesh, bounds, materials, testMaterial: Array.isArray(mesh.material) ? materials.map(() => this.testMaterial) : this.testMaterial });
    });
  }
  trace(origin, direction, far, ground = null) {
    this.raycaster.set(origin, direction);
    this.raycaster.near = 0;
    let best = null, distance = far;
    if (ground && Math.abs(direction.y) > 1e-9) {
      const travel = (ground.min.y - origin.y) / direction.y;
      if (travel > 0 && travel <= far) {
        const point = origin.clone().addScaledVector(direction, travel);
        if (point.x >= ground.min.x && point.x <= ground.max.x && point.z >= ground.min.z && point.z <= ground.max.z) {
          distance = travel;
          best = { point, distance, kind: "ground", object: null };
        }
      }
    }
    const candidates = [];
    for (const entry of this.entries) {
      if (!entry.mesh.castShadow || !visibleInModel(entry.mesh, this.root)) continue;
      if (!this.raycaster.ray.intersectBox(entry.bounds, this.boxPoint)) continue;
      const near = entry.bounds.containsPoint(origin) ? 0 : origin.distanceTo(this.boxPoint);
      if (near <= distance) candidates.push({ entry, near });
    }
    candidates.sort((a, b) => a.near - b.near);
    for (const { entry, near } of candidates) {
      if (near > distance) break;
      this.raycaster.far = distance;
      this.hits.length = 0;
      const original = entry.mesh.material;
      try {
        entry.mesh.material = entry.testMaterial;
        entry.mesh.raycast(this.raycaster, this.hits);
      } finally { entry.mesh.material = original; }
      for (const hit of this.hits) {
        if (hit.distance >= distance || !solarOpaqueMaterial(entry.materials[hit.face?.materialIndex ?? 0])) continue;
        distance = hit.distance;
        best = { point: hit.point.clone(), distance, kind: "model", object: entry.mesh };
      }
    }
    return best;
  }
  dispose() { this.testMaterial.dispose(); this.entries.length = 0; this.root = null; }
}
