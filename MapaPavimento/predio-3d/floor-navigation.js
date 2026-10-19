import * as THREE from "three";
import { boxCorners } from "./solar-shadow.js";

/** Enquadramento ortográfico no ângulo atual, sem impor uma vista de topo. */
export function floorOrbitPose(bounds, camera, target, aspect) {
  if (!bounds || bounds.isEmpty()) return null;
  const center = bounds.getCenter(new THREE.Vector3());
  const radius = Math.max(
    bounds.getSize(new THREE.Vector3()).length() / 2,
    0.05,
  );
  const direction = camera.position.clone().sub(target).normalize();
  if (!direction.lengthSq()) camera.getWorldDirection(direction).negate();
  const up = camera.up.clone().normalize();
  const right = new THREE.Vector3().crossVectors(up, direction).normalize();
  if (!right.lengthSq()) right.set(1, 0, 0);
  const vertical = new THREE.Vector3()
    .crossVectors(direction, right)
    .normalize();
  let halfWidth = 0,
    halfHeight = 0;
  for (const corner of boxCorners(bounds)) {
    const offset = corner.sub(center);
    halfWidth = Math.max(halfWidth, Math.abs(offset.dot(right)));
    halfHeight = Math.max(halfHeight, Math.abs(offset.dot(vertical)));
  }
  return {
    position: center.clone().addScaledVector(direction, radius * 3),
    target: center,
    up,
    height:
      Math.max(halfHeight, halfWidth / Math.max(aspect, 0.1), radius * 0.08) *
      2.7,
    zoom: 1,
    top: false,
  };
}
