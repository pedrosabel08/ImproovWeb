import { localPolygon } from "./feature-builder.js?v=20261103-boundary-v4";
import {
  clipPolygons,
  differencePolygons,
  polygonArea,
} from "./environment-clip.js?v=20261103-boundary-v4";

/** Every area is local metres and geometrically clipped BEFORE its builder triangulates. */
export function prepareEnvironment(
  data,
  project,
  bounds,
  coastal,
  debug = null,
) {
  const prepared = { buildings: [], roads: [], water: [], vegetation: [] };
  const diagnostics = [];
  for (const kind of Object.keys(prepared))
    for (const feature of data[kind]) {
      const originals = feature.polygons.map((p) => localPolygon(p, project));
      let polygons = clipPolygons(originals, bounds);
      // Vegetation and freshwater never paint over the classified ocean.
      if (kind === "vegetation" || kind === "water")
        polygons = differencePolygons(polygons, coastal.sea, bounds);
      const line = feature.line?.map(project) || null;
      if (kind === "vegetation" || feature.polygons.length) {
        const row = {
          id: feature.id,
          tags: feature.visualTags,
          category: feature.category,
          originalAreaM2: Math.round(
            originals.reduce((s, p) => s + polygonArea(p), 0),
          ),
          clippedAreaM2: Math.round(
            polygons.reduce((s, p) => s + polygonArea(p), 0),
          ),
        };
        diagnostics.push(row);
        if (debug) {
          debug.polygons(originals, "original");
          debug.polygons(polygons, feature.category);
        }
      }
      if (polygons.length || line?.length > 1)
        prepared[kind].push({ ...feature, polygons, line });
    }
  return { data: prepared, diagnostics };
}
