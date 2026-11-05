import {
  localPolygon,
  polygonGeometry,
} from "./feature-builder.js?v=20261103-boundary-v4";
import { SURFACE_Y } from "./environment-config.js?v=20261103-boundary-v4";

export function buildVegetation(feature, { project, batches, metrics, terrain }) {
  const polygons=feature.polygons.map(p=>localPolygon(p,project));
  for (const polygon of terrain ? terrain.vegetationPolygons(polygons) : polygons) {
    let geometry = polygonGeometry(polygon);
    if (terrain) geometry = terrain.drape(geometry, SURFACE_Y.vegetation);
    else geometry.translate(0, SURFACE_Y.vegetation, 0);
    batches.add(feature.category, geometry);
    metrics.polygons++;
  }
  metrics.vegetation++;
}
