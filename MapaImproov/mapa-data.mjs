export const normalize = (value) =>
  String(value ?? "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLocaleLowerCase("pt-BR");

export function filterProjects(projects, filters) {
  const query = normalize(filters.search).trim();
  return projects.filter(
    (p) =>
      (!query ||
        normalize(`${p.codigo} ${p.nome} ${p.localizacao}`).includes(query)) &&
      (filters.status === "" || String(p.status_id) === filters.status) &&
      (filters.model === "" || p.has_glb === (filters.model === "yes")) &&
      (filters.location === "" || p.localizacao === filters.location),
  );
}

export function featureCollection(projects) {
  return {
    type: "FeatureCollection",
    features: projects
      .filter((p) => p.coordinates !== null)
      .map((p) => ({
        type: "Feature",
        id: p.obra_id,
        geometry: { type: "Point", coordinates: p.coordinates },
        properties: {
          obra_id: p.obra_id,
          status_id: p.status_id,
          codigo: p.codigo,
        },
      })),
  };
}

export function restoreState(raw) {
  try {
    const state = JSON.parse(raw);
    if (!state || state.version !== 1) return null;
    const camera = state.camera;
    const validCamera =
      camera &&
      Array.isArray(camera.center) &&
      camera.center.length === 2 &&
      camera.center.every(Number.isFinite) &&
      Math.abs(camera.center[0]) <= 180 &&
      Math.abs(camera.center[1]) <= 90 &&
      Number.isFinite(camera.zoom) &&
      camera.zoom >= 0 &&
      camera.zoom <= 18 &&
      Number.isFinite(camera.bearing) &&
      Math.abs(camera.bearing) <= 360 &&
      Number.isFinite(camera.pitch) &&
      camera.pitch >= 0 &&
      camera.pitch <= 60;
    return {
      projection: state.projection === "mercator" ? "mercator" : "globe",
      selected:
        Number.isSafeInteger(state.selected) && state.selected > 0
          ? state.selected
          : null,
      filters: Object.fromEntries(
        ["search", "status", "model", "location"].map((k) => [
          k,
          typeof state.filters?.[k] === "string"
            ? state.filters[k].slice(0, 300)
            : "",
        ]),
      ),
      camera: validCamera ? camera : null,
    };
  } catch {
    return null;
  }
}
