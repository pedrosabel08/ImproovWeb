import {
  localPolygon,
  polygonGeometry,
} from "./feature-builder.js?v=20261103-boundary-v4";
import { ribbonGeometry } from "./road-builder.js?v=20261103-boundary-v4";
import { SURFACE_Y } from "./environment-config.js?v=20261103-boundary-v4";
import {
  differencePolygons,
  dissolvePolygons,
  intersectPolygons,
} from "./environment-clip.js";

import { WATER_PROFILES_ENABLED, waterProfile } from "./water-profile.js";

// Use one hydraulic field across a confluence, even if the visible material changes.
export function waterSurfaceParts(polygon, controls) {
  const profiles = new Set(controls.map((control) => control.profile));
  if (!WATER_PROFILES_ENABLED || profiles.size <= 1)
    return [
      {
        polygon,
        profile: WATER_PROFILES_ENABLED ? controls[0]?.profile : undefined,
      },
    ];
  let remaining = [polygon];
  const parts = [];
  for (const profile of ["standing", "flowing"]) {
    const covered = dissolvePolygons(
      controls.filter((c) => c.profile === profile).flatMap((c) => c.footprint),
    );
    const area = intersectPolygons(remaining, covered);
    parts.push(...area.map((polygon) => ({ polygon, profile })));
    remaining = differencePolygons(remaining, covered);
  }
  return parts.concat(
    remaining.map((polygon) => ({ polygon, profile: "generic" })),
  );
}

export function waterwayWidth(waterway) {
  return (
    { river: 12, stream: 3, canal: 6, drain: 1.5, ditch: 1 }[waterway] || 3
  );
}

export function buildWater(feature, { project, batches, metrics, terrain }) {
  if (terrain) {
    // The controller emits the union once. Rebuilding independent ribbons here
    // would restore overlapping faces and mismatching banks at confluences.
    metrics.water++;
    return;
  }
  const profile = WATER_PROFILES_ENABLED ? waterProfile(feature) : undefined;
  for (const polygon of feature.polygons) {
    const geometry = polygonGeometry(localPolygon(polygon, project));
    geometry.translate(0, SURFACE_Y.water, 0);
    batches.add("water", geometry, false, profile);
    metrics.polygons++;
  }
  if (feature.line) {
    const width = waterwayWidth(feature.waterway);
    const geometry = ribbonGeometry(
      feature.line.map(project),
      width,
      SURFACE_Y.water,
    );
    if (geometry) {
      batches.add("water", geometry, false, profile);
    }
  }
  metrics.water++;
}
