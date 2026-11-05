import {
  localPolygon,
  polygonGeometry,
  overlapsSite,
  distanceBand,
  simplifyPoints,
} from "./feature-builder.js?v=20261103-boundary-v4";

export function buildBuilding(
  feature,
  { project, site, radius, batches, metrics, terrain },
) {
  let rendered = false;
  for (const polygon of feature.polygons) {
    const local = localPolygon(polygon, project),
      band = distanceBand(local.outer, radius);
    if (band < 0 || overlapsSite(local, site)) {
      metrics.excludedBuildings++;
      continue;
    }
    // Keep original close footprints; simplify sub-metre details in distant volumes.
    const tolerance = [0, 0.6, 1.5][band];
    local.outer = simplifyPoints(local.outer, tolerance);
    local.holes = local.holes.map((ring) => simplifyPoints(ring, tolerance));
    if(terrain) {
      local.outer=terrain.densifyRing(local.outer);
      local.holes=local.holes.map(ring=>terrain.densifyRing(ring));
    }
    const geometry = polygonGeometry(local, feature.height);
    terrain?.building(geometry, local, feature.height);
    // Only close casters; the solar shadow frustum continues to use GLB bounds only.
    const close =
      band === 0 && local.outer.every((p) => Math.hypot(p.x, p.z) < 120);
    batches.add(`building${band}`, geometry, close);
    rendered = true;
    metrics.polygons++;
  }
  if (rendered) metrics.buildings++;
}
