import * as THREE from "three";

export const measurementTypes = {
  distance: "Distância",
  horizontal: "Horizontal",
  vertical: "Vertical",
};
const metres = new Intl.NumberFormat("pt-BR", {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});
export const formatMeasurement = (value) => `${metres.format(value)} m`;

/** Pontos em world space; 1 unidade do GLB = 1 metro, sem fator de escala adicional. */
export function measurementValue(type, pointA, pointB) {
  if (type === "horizontal")
    return Math.hypot(pointB.x - pointA.x, pointB.z - pointA.z);
  if (type === "vertical") return Math.abs(pointB.y - pointA.y);
  return pointA.distanceTo(pointB);
}

export function measurementSegment(type, pointA, pointB) {
  const end = pointB.clone();
  if (type === "horizontal") end.y = pointA.y;
  if (type === "vertical") {
    end.x = pointA.x;
    end.z = pointA.z;
  }
  return {
    end,
    midpoint: new THREE.Vector3().addVectors(pointA, end).multiplyScalar(0.5),
  };
}

/** Raycaster também retorna meshes invisíveis e partes cortadas: descartá-los antes de medir. */
export function visibleSurface(hit) {
  for (let object = hit.object; object; object = object.parent)
    if (!object.visible) return false;
  const material = Array.isArray(hit.object.material)
    ? hit.object.material[hit.face?.materialIndex || 0]
    : hit.object.material;
  if (!material || material.visible === false || material.opacity === 0)
    return false;
  const planes = material.clippingPlanes || [];
  const clipped = (plane) => plane.distanceToPoint(hit.point) < -1e-7;
  return (
    !planes.length ||
    !(material.clipIntersection ? planes.every(clipped) : planes.some(clipped))
  );
}

/** Mesmo mecanismo HTML + Vector3.project usado nas etiquetas solares e no Norte. */
export function projectMeasurementPoint(
  point,
  camera,
  width,
  height,
  projected = new THREE.Vector3(),
) {
  projected.copy(point).project(camera);
  return {
    x: (projected.x * 0.5 + 0.5) * width,
    y: (-projected.y * 0.5 + 0.5) * height,
    visible:
      projected.z >= -1 &&
      projected.z <= 1 &&
      Math.abs(projected.x) <= 1 &&
      Math.abs(projected.y) <= 1,
  };
}
