/** Mesmo vidro para a apresentação e o viewer completo, sem alterar o GLB. */
export function createViewerGlass(material) {
  const glass = material.clone();
  glass.userData.viewerGlass = true;
  glass.transmission = 0;
  glass.opacity = Math.min(material.opacity, 0.16);
  glass.transparent = true;
  glass.depthWrite = false;
  glass.depthTest = true;
  glass.roughness = Math.max(0.16, Math.min(material.roughness, 0.3));
  glass.metalness = 0;
  glass.ior = 1.1;
  glass.thickness = 0;
  glass.envMapIntensity = Math.min(material.envMapIntensity, 0.12);
  glass.needsUpdate = true;
  return glass;
}
