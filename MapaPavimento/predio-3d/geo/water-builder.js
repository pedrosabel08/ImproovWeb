import {
  localPolygon,
  polygonGeometry,
} from "./feature-builder.js?v=20261103-boundary-v4";
import { ribbonGeometry } from "./road-builder.js?v=20261103-boundary-v4";
import { SURFACE_Y } from "./environment-config.js?v=20261103-boundary-v4";

export function buildWater(feature, { project, batches, metrics }) {
  for (const polygon of feature.polygons) {
    const geometry = polygonGeometry(localPolygon(polygon, project));
    geometry.translate(0, SURFACE_Y.water, 0);
    batches.add("water", geometry);
    metrics.polygons++;
  }
  if (feature.line) {
    const width =
      { river: 12, stream: 3, canal: 6, drain: 1.5, ditch: 1 }[
        feature.waterway
      ] || 3;
    const geometry = ribbonGeometry(
      feature.line.map(project),
      width,
      SURFACE_Y.water,
    );
    if (geometry) batches.add("water", geometry);
  }
  metrics.water++;
}
