import {
  BEACH_BY_PROJECT,
  SIDEWALK_SETTINGS,
} from "./environment-config.js?v=20261113-surfaces";
import {
  clipPolygons,
  differencePolygons,
  dissolvePolygons,
  intersectPolygons,
  polygonArea,
} from "./environment-clip.js?v=20261113-surfaces";
import { localPolygon } from "./feature-builder.js?v=20261113-surfaces";
import { waterwayWidth } from "./water-builder.js?v=20261113-surfaces";
import {
  ribbonGeometry,
  roadDimensions,
} from "./road-builder.js?v=20261113-surfaces";
import {
  isMappedSidewalk,
  sidewalkSides,
} from "./surface-classification.js?v=3";

const polygonsOf = (features) =>
  features.flatMap((feature) => feature.polygons);

// Reuse the exact corridor of the current road builder, including its joins
// and simplification. Union the triangles before clipping intersecting ways.
function ribbonPolygons(points, width, offset = 0) {
  const geometry = ribbonGeometry(points, width, 0, offset);
  if (!geometry) return [];
  const position = geometry.attributes.position;
  const polygons = [];
  for (let i = 0; i < position.count; i += 3) {
    const outer = [0, 1, 2, 0].map((j) => ({
      x: position.getX(i + j),
      z: position.getZ(i + j),
    }));
    const polygon = { outer, holes: [] };
    if (polygonArea(polygon) > 0.0001) polygons.push(polygon);
  }
  geometry.dispose();
  return polygons;
}

function footprintBounds(polygons) {
  const box = {
    minX: Infinity,
    maxX: -Infinity,
    minZ: Infinity,
    maxZ: -Infinity,
  };
  for (const { outer } of polygons)
    for (const { x, z } of outer) {
      box.minX = Math.min(box.minX, x);
      box.maxX = Math.max(box.maxX, x);
      box.minZ = Math.min(box.minZ, z);
      box.maxZ = Math.max(box.maxZ, z);
    }
  return box;
}

function overlaps(a, b) {
  return (
    a.minX <= b.maxX && a.maxX >= b.minX && a.minZ <= b.maxZ && a.maxZ >= b.minZ
  );
}

function polygonsNear(subject, candidates) {
  const boxes = subject.map((polygon) => footprintBounds([polygon]));
  return candidates.filter((polygon) => {
    const box = footprintBounds([polygon]);
    return boxes.some((other) => overlaps(box, other));
  });
}

function subtract(subject, candidates, bounds) {
  return differencePolygons(
    subject,
    dissolvePolygons(polygonsNear(subject, candidates)),
    bounds,
  ).filter((polygon) => polygonArea(polygon) > 0.001);
}

function beachOverride(entry, project, bounds, coastal, terrain, exclusions) {
  if (
    !entry ||
    coastal.state !== "ready" ||
    !terrain ||
    !(entry.widthM >= 2 && entry.widthM <= 150)
  )
    return [];
  const ring = entry.ring;
  if (
    !Array.isArray(ring) ||
    ring.length < 3 ||
    ring.length > 500 ||
    !ring.every(
      (point) =>
        Number.isFinite(point?.lat) &&
        Number.isFinite(point?.lon) &&
        Math.abs(point.lat) <= 90 &&
        Math.abs(point.lon) <= 180,
    )
  )
    return [];
  const manual = clipPolygons(
    [localPolygon({ outer: ring, holes: [] }, project)],
    bounds,
  );
  // The polygon author defines the candidate coast segment. Both sides of the
  // strip are built so the existing validated land polygon picks the land side.
  const strip = coastal.coastLines.flatMap((line) =>
    ribbonPolygons(line, entry.widthM * 2),
  );
  const nearCoast = intersectPolygons(
    manual,
    dissolvePolygons(polygonsNear(manual, strip)),
  );
  return subtract(
    intersectPolygons(nearCoast, coastal.land),
    exclusions,
    bounds,
  );
}

export function prepareLandSurfaces({
  prepared,
  config,
  project,
  bounds,
  coastal,
  terrain,
  beachByProject = BEACH_BY_PROJECT,
}) {
  const { roads } = prepared;
  const mappedSidewalks = new Set(
    roads.filter((road) => isMappedSidewalk(road.visualTags)),
  );
  const sidewalkArea = polygonsOf(prepared.sidewalk);
  const sandArea = polygonsOf(prepared.sand);
  const rockArea = polygonsOf(prepared.rock);
  const soilArea = polygonsOf(prepared.soil);
  const manualBeach = beachByProject[config.obra_id];
  const extraRoads = roads.filter(
    (road) =>
      !mappedSidewalks.has(road) && sidewalkSides(road.visualTags).length,
  );
  const layers = {
    sidewalk: [],
    "sidewalk-extra": [],
    rock: [],
    sand: [],
    "sand-manual": [],
    soil: [],
  };
  if (
    !mappedSidewalks.size &&
    !sidewalkArea.length &&
    !extraRoads.length &&
    !sandArea.length &&
    !rockArea.length &&
    !soilArea.length &&
    !manualBeach
  )
    return { layers, mappedSidewalks };

  const dimensions = new Map(
    roads.map((road) => [
      road,
      roadDimensions(road, (point) => point, bounds.radius),
    ]),
  );
  const corridor = (road, padding = 0, offset = 0) => {
    const line = dimensions.get(road);
    return line
      ? ribbonPolygons(line.points, line.width + 2 * padding, offset)
      : [];
  };
  const water = terrain
    ? terrain.waterFootprint
    : prepared.water.flatMap((feature) => [
        ...feature.polygons,
        ...(feature.line
          ? ribbonPolygons(feature.line, waterwayWidth(feature.waterway))
          : []),
      ]);
  const structures = [
    ...polygonsOf(prepared.buildings),
    ...(terrain?.ground?.footprint ?? []),
  ];
  const hardExclusions = [...structures, ...water, ...coastal.sea];
  const existingSidewalk = [
    ...sidewalkArea,
    ...[...mappedSidewalks].flatMap((road) => corridor(road)),
  ];
  const detailRadius = SIDEWALK_SETTINGS.detailRadiusM;
  const detailBounds = {
    ...bounds,
    radius: detailRadius,
    polygon: {
      outer: bounds.polygon.outer.map((p) => ({
        x: (p.x * detailRadius) / bounds.radius,
        z: (p.z * detailRadius) / bounds.radius,
      })),
      holes: [],
    },
  };
  const extraSidewalk = extraRoads.flatMap((road) => {
    const line = dimensions.get(road);
    if (!line) return [];
    const offset =
      line.width / 2 +
      SIDEWALK_SETTINGS.roadGapM +
      SIDEWALK_SETTINGS.widthM / 2;
    return sidewalkSides(road.visualTags).flatMap((side) =>
      clipPolygons(
        dissolvePolygons(
          ribbonPolygons(
            line.points,
            SIDEWALK_SETTINGS.widthM,
            side === "left" ? -offset : offset,
          ),
        ),
        detailBounds,
      ),
    );
  });

  const vehicleAndPathRoads = roads.filter(
    (road) => !mappedSidewalks.has(road),
  );
  const roadCandidates = [
    ...existingSidewalk,
    ...extraSidewalk,
    ...rockArea,
    ...sandArea,
  ];
  const limit = footprintBounds(roadCandidates);
  const nearbyRoads = vehicleAndPathRoads.filter((road) => {
    const line = dimensions.get(road);
    if (!line) return false;
    const box = footprintBounds([{ outer: line.points }]);
    const pad = line.width * 2;
    const expanded = {
      minX: box.minX - pad,
      maxX: box.maxX + pad,
      minZ: box.minZ - pad,
      maxZ: box.maxZ + pad,
    };
    return manualBeach || overlaps(limit, expanded);
  });
  const roadFootprint = nearbyRoads.flatMap((road) => corridor(road, 0.03));
  layers.sidewalk = subtract(
    clipPolygons(dissolvePolygons(existingSidewalk), bounds),
    [...hardExclusions, ...roadFootprint],
    bounds,
  );
  const mappedClearance = [...mappedSidewalks].flatMap((road) =>
    corridor(road, SIDEWALK_SETTINGS.mappedClearanceM),
  );
  layers["sidewalk-extra"] = subtract(
    dissolvePolygons(extraSidewalk),
    [
      ...hardExclusions,
      ...roadFootprint,
      ...layers.sidewalk,
      ...mappedClearance,
    ],
    bounds,
  );

  const vegetation = polygonsOf(prepared.vegetation);
  const occupiedLand = [
    ...hardExclusions,
    ...roadFootprint,
    ...layers.sidewalk,
    ...layers["sidewalk-extra"],
    ...vegetation,
  ];
  layers.rock = subtract(dissolvePolygons(rockArea), occupiedLand, bounds);
  layers.sand = subtract(
    dissolvePolygons(sandArea),
    [...occupiedLand, ...layers.rock],
    bounds,
  );
  layers["sand-manual"] = beachOverride(
    manualBeach,
    project,
    bounds,
    coastal,
    terrain,
    [
      ...occupiedLand,
      ...layers.rock,
      ...layers.sand,
      ...polygonsOf(prepared.urbanExclusion),
    ],
  );
  layers.soil = subtract(
    dissolvePolygons(soilArea),
    [
      ...water,
      ...(terrain?.ground?.footprint ?? []),
      ...vegetation,
      ...layers.rock,
      ...layers.sand,
      ...layers["sand-manual"],
    ],
    bounds,
  );
  return { layers, mappedSidewalks };
}

/** Clip the manual beach overlay against the DEM's actual triangles. */
export function limitManualBeachHeight(geometry, maxY) {
  const position = geometry.attributes.position;
  const vertices = [];
  for (let i = 0; i < position.count; i += 3) {
    const triangle = [0, 1, 2].map((j) => [
      position.getX(i + j),
      position.getY(i + j),
      position.getZ(i + j),
    ]);
    const clipped = [];
    for (let j = 0; j < 3; j++) {
      const a = triangle[j];
      const b = triangle[(j + 1) % 3];
      if (a[1] <= maxY) clipped.push(a);
      if ((a[1] < maxY && b[1] > maxY) || (a[1] > maxY && b[1] < maxY)) {
        const t = (maxY - a[1]) / (b[1] - a[1]);
        clipped.push([
          a[0] + (b[0] - a[0]) * t,
          maxY,
          a[2] + (b[2] - a[2]) * t,
        ]);
      }
    }
    for (let j = 1; j + 1 < clipped.length; j++)
      vertices.push(...clipped[0], ...clipped[j], ...clipped[j + 1]);
  }
  geometry.dispose();
  const result = new geometry.constructor();
  result.setAttribute("position", new position.constructor(vertices, 3));
  result.computeVertexNormals();
  return result;
}
