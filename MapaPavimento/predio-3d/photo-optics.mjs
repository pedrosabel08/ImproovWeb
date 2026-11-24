// Full-frame gate in millimetres, cropped centrally to the deliverable aspect.
export function gate(aspect) {
  return { width: Math.min(36, 24 * aspect), height: Math.min(24, 36 / aspect) };
}
export function frameSize(width, height, aspect) {
  const w = Math.min(width, height * aspect);
  return { width: w, height: w / aspect };
}
export function entranceFocal(fov, zoom, viewportAspect, aspect) {
  const fraction = frameSize(viewportAspect, 1, aspect).height;
  return gate(aspect).height * zoom / (2 * Math.tan(fov * Math.PI / 360) * fraction);
}
export function frameProjection(focal, aspect, viewportAspect) {
  const fraction = frameSize(viewportAspect, 1, aspect).height;
  return 360 / Math.PI * Math.atan(gate(aspect).height / (2 * focal * fraction));
}
