import * as THREE from "three";

export function boxCorners(box) {
  const points = [];
  for (const x of [box.min.x, box.max.x])
    for (const y of [box.min.y, box.max.y])
      for (const z of [box.min.z, box.max.z])
        points.push(new THREE.Vector3(x, y, z));
  return points;
}

/** Ajusta o frustum no espaço da luz, após aplicar a direção solar.
 * Inclui o edifício inteiro e o receptor local do chão. Não segue a câmera de navegação.
 * O raio solar real não é necessário para luz direcional (raios paralelos). */
export function fitSolarShadow(light, bounds, receiverBounds = null) {
  if (!bounds || bounds.isEmpty()) return;
  const center = bounds.getCenter(new THREE.Vector3());
  const radius = Math.max(
    bounds.getSize(new THREE.Vector3()).length() / 2,
    0.5,
  );
  const camera = light.shadow.camera;
  light.updateMatrixWorld(true);
  light.target.updateMatrixWorld(true);
  camera.position.copy(light.position);
  const direction = center.clone().sub(light.position).normalize();
  camera.up.set(0, 1, 0);
  if (Math.abs(direction.y) > 0.999) camera.up.set(0, 0, 1);
  camera.lookAt(light.target.position);
  camera.updateMatrixWorld(true);
  const points = boxCorners(bounds);
  if (receiverBounds) points.push(...boxCorners(receiverBounds));
  const inLight = new THREE.Box3().setFromPoints(
    points.map((point) => point.applyMatrix4(camera.matrixWorldInverse)),
  );
  const padding = radius * 0.03;
  camera.left = inLight.min.x - padding;
  camera.right = inLight.max.x + padding;
  camera.bottom = inLight.min.y - padding;
  camera.top = inLight.max.y + padding;
  camera.near = Math.max(radius * 0.001, -inLight.max.z - padding);
  camera.far = Math.max(camera.near + radius, -inLight.min.z + padding);
  camera.updateProjectionMatrix();
  light.shadow.bias = -0.00005;
  light.shadow.normalBias = radius * 0.00025;
  light.shadow.needsUpdate = true;
}
