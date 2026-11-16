// No fog. One geographic square, in metres, shared by every layer.
export const GEO_OPTIONS = Object.freeze({
  geoDebug: false,
  enableForestTrees: false,
  terrainTextures: true,
});
export const SIDEWALK_SETTINGS = Object.freeze({
  widthM: 1.8,
  detailRadiusM: 450,
  roadGapM: 0.12,
  mappedClearanceM: 1.2,
});
// Add a surveyed land polygon (WGS84, {lat, lon}) by obra_id when OSM lacks
// a mapped beach. widthM limits its inland extent from the validated coastline.
export const BEACH_BY_PROJECT = Object.freeze({});
// Shared local heights; no per-feature random offsets. Water is below land.
export const SURFACE_Y = Object.freeze({
  sea: -0.02,
  ground: 0,
  vegetation: 0.01,
  road: 0.025,
  water: 0.04,
});
export const GEO_COLORS = Object.freeze({
  building0: 0xc0c2bd,
  building1: 0xb5b9b5,
  building2: 0xacb2af,
  road: 0x454b49,
  asphalt: 0x454b49,
  soil: 0x39453f,
  sand: 0xb8a17a,
  rock: 0x665f53,
  sidewalk: 0x454b49,
  "sidewalk-extra": 0x454b49,
  ground: 0x39453f,
  FOREST: 0x284b36,
  GRASS: 0x89977b,
  PARK: 0x58785a,
  water: 0x638c99,
  sea: 0x2b617e,
});
