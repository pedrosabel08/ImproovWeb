import * as THREE from "three";

// A esfera não acompanha câmera, edifício ou target dos controles.
export const SKY_PROJECTION_GLSL = `
  uniform mat4 skyCameraWorld, skyProjectionInverse;
  uniform bool orthographic;
  uniform float domeRadius;
  vec3 worldSkyDirection(vec2 screen) {
    vec4 nearPoint = skyProjectionInverse * vec4(screen, -1.0, 1.0);
    nearPoint /= nearPoint.w;
    vec3 origin = (skyCameraWorld * vec4(nearPoint.xyz, 1.0)).xyz;
    vec3 direction = orthographic ? normalize(mat3(skyCameraWorld) * vec3(0.0, 0.0, -1.0))
      : normalize(origin - skyCameraWorld[3].xyz);
    if (!orthographic) origin = skyCameraWorld[3].xyz;
    float b = dot(origin, direction);
    float discriminant = b * b + domeRadius * domeRadius - dot(origin, origin);
    float distance = -b + sqrt(max(0.0, discriminant));
    // Fora da esfera: fallback direcional mundial, nunca textura presa à viewport.
    return discriminant >= 0.0 && distance > 0.0 ? normalize(origin + direction * distance) : direction;
  }`;

export function skyDirection(camera, x, y, radius) {
  const origin = new THREE.Vector3(x, y, -1).unproject(camera);
  const position = new THREE.Vector3().setFromMatrixPosition(
    camera.matrixWorld,
  );
  const direction = camera.isOrthographicCamera
    ? camera.getWorldDirection(new THREE.Vector3())
    : origin.clone().sub(position).normalize();
  if (!camera.isOrthographicCamera) origin.copy(position);
  const b = origin.dot(direction),
    discriminant = b * b + radius * radius - origin.lengthSq();
  const distance = -b + Math.sqrt(Math.max(0, discriminant));
  return discriminant >= 0 && distance > 0
    ? origin.addScaledVector(direction, distance).normalize()
    : direction;
}

/** Círculo Y=0 recortado pelos quatro planos laterais e plano frontal.
 * Raízes analíticas: não usa amostragem de pixels nem percentuais da viewport. */
export function horizonVisible(camera, radius) {
  const vp = new THREE.Matrix4().multiplyMatrices(
    camera.projectionMatrix,
    camera.matrixWorldInverse,
  );
  const frustum = new THREE.Frustum().setFromProjectionMatrix(vp);
  const forward = camera.getWorldDirection(new THREE.Vector3());
  const position = new THREE.Vector3().setFromMatrixPosition(
    camera.matrixWorld,
  );
  const planes = frustum.planes.slice(0, 4);
  planes.push(new THREE.Plane(forward, -position.dot(forward)));
  const boundaries = [0, 2 * Math.PI];
  for (const plane of planes) {
    const a = plane.normal.x * radius,
      b = plane.normal.z * radius,
      r = Math.hypot(a, b);
    if (r < 1e-12) {
      if (plane.constant < 0) return false;
      continue;
    }
    const value = -plane.constant / r;
    if (Math.abs(value) > 1) continue;
    const phase = Math.atan2(b, a),
      delta = Math.acos(value);
    for (const angle of [phase - delta, phase + delta])
      boundaries.push(((angle % (2 * Math.PI)) + 2 * Math.PI) % (2 * Math.PI));
  }
  boundaries.sort((a, b) => a - b);
  const inside = (angle) =>
    planes.every(
      (p) =>
        radius * (p.normal.x * Math.cos(angle) + p.normal.z * Math.sin(angle)) +
          p.constant >=
        -1e-7,
    );
  return boundaries.some(
    (angle, i) =>
      inside(angle) || (i > 0 && inside((angle + boundaries[i - 1]) / 2)),
  );
}
