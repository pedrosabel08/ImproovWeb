/** Respeita objetos explicitamente marcados para não aparecer na exportação. */
export function hideExcludedObjects(root) {
  root.traverse((object) => {
    if (/^esconder(?:_|\b)/i.test((object.name || "").trim()))
      object.visible = false;
  });
}
