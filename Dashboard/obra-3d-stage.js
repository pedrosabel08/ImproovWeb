import * as THREE from "three";

/** Acabamento do hero: linhas reais no plano XZ, sem participar da iluminação. */
export function createTechnicalStage(bounds) {
  const stage = new THREE.Group();
  stage.name = "Stage técnico da apresentação";
  const size = bounds.getSize(new THREE.Vector3());
  const center = bounds.getCenter(new THREE.Vector3());
  const radius = Math.max(Math.hypot(size.x, size.z) / 2, 0.001);
  stage.position.set(
    center.x,
    bounds.min.y - Math.max(size.y * 0.001, 0.001),
    center.z,
  );
  for (const [index, factor] of [1.12, 1.48, 1.82].entries()) {
    const points = Array.from({ length: 192 }, (_, step) => {
      const angle = (step / 192) * Math.PI * 2;
      return new THREE.Vector3(
        Math.cos(angle) * radius * factor,
        0,
        Math.sin(angle) * radius * factor,
      );
    });
    const ring = new THREE.LineLoop(
      new THREE.BufferGeometry().setFromPoints(points),
      new THREE.LineBasicMaterial({
        color: 0x7898b2,
        transparent: true,
        opacity: index === 1 ? 0.22 : 0.13,
        depthWrite: false,
        toneMapped: false,
      }),
    );
    ring.raycast = () => {};
    stage.add(ring);
  }
  return stage;
}

/** Disco e oito raios finos; sem textura, flare, emissão ou luz adicional. */
export function createSolarIndicator() {
  const sun = new THREE.Group();
  sun.name = "Indicador da posição solar";
  const color = 0xb3bec9;
  sun.add(
    new THREE.Mesh(
      new THREE.CircleGeometry(0.36, 32),
      new THREE.MeshBasicMaterial({
        color,
        transparent: true,
        opacity: 0.8,
        depthWrite: false,
        toneMapped: false,
      }),
    ),
  );
  const points = [];
  for (let ray = 0; ray < 8; ray++) {
    const angle = (ray * Math.PI) / 4;
    for (const radius of [0.57, 0.9])
      points.push(
        new THREE.Vector3(
          Math.cos(angle) * radius,
          Math.sin(angle) * radius,
          0,
        ),
      );
  }
  sun.add(
    new THREE.LineSegments(
      new THREE.BufferGeometry().setFromPoints(points),
      new THREE.LineBasicMaterial({
        color,
        transparent: true,
        opacity: 0.65,
        depthWrite: false,
        toneMapped: false,
      }),
    ),
  );
  sun.traverse((object) => {
    object.raycast = () => {};
  });
  sun.visible = false;
  return sun;
}
