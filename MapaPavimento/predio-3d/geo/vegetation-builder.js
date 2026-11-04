import {
  localPolygon,
  polygonGeometry,
} from "./feature-builder.js?v=20261103-boundary-v4";
import { SURFACE_Y } from "./environment-config.js?v=20261103-boundary-v4";

export function buildVegetation(feature, { project, batches, metrics }) {
  for (const polygon of feature.polygons) {
    const geometry = polygonGeometry(localPolygon(polygon, project));
    geometry.translate(0, SURFACE_Y.vegetation, 0);
    batches.add(feature.category, geometry);
    metrics.polygons++;
  }
  metrics.vegetation++;
}
