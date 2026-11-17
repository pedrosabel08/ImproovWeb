import * as THREE from "three";
import { visibleSurface } from "./measurement-geometry.js";

export function createPersonPicker(camera, canvas) {
  const raycaster = new THREE.Raycaster();
  const pointer = new THREE.Vector2();
  const normalMatrix = new THREE.Matrix3();
  raycaster.layers.enableAll();

  return (event, { meshes, group }) => {
    const rect = canvas.getBoundingClientRect();
    if (!rect.width || !rect.height) return null;
    pointer.set(
      ((event.clientX - rect.left) / rect.width) * 2 - 1,
      1 - ((event.clientY - rect.top) / rect.height) * 2,
    );
    camera.updateMatrixWorld();
    raycaster.setFromCamera(pointer, camera);
    const hits = raycaster.intersectObjects(meshes, false);
    group?.traverseVisible((mesh) => {
      if (
        !mesh.isMesh ||
        !/^(ground|model-ground|road|building\d*|terreno GLB)$/.test(
          mesh.userData.geoLayer || mesh.name.replace(/^OSM · /, ""),
        )
      )
        return;
      // Geographic batches intentionally opt out of the ordinary inspection picker.
      THREE.Mesh.prototype.raycast.call(mesh, raycaster, hits);
    });
    const hit = hits
      .sort((a, b) => a.distance - b.distance)
      .find((hit) => hit.face && visibleSurface(hit));
    if (!hit) return null;
    normalMatrix.getNormalMatrix(hit.object.matrixWorld);
    return {
      point: hit.point,
      normalY: hit.face.normal.clone().applyNormalMatrix(normalMatrix).y,
      towardCamera: raycaster.ray.direction
        .clone()
        .negate()
        .setY(0)
        .normalize(),
    };
  };
}
