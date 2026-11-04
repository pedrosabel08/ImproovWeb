// No fog. One geographic square, in metres, shared by every layer.
export const GEO_OPTIONS = Object.freeze({
  geoDebug: false,
  enableForestTrees: false,
});
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
  ground: 0x39453f,
  FOREST: 0x284b36,
  GRASS: 0x89977b,
  PARK: 0x58785a,
  water: 0x638c99,
  sea: 0x2b617e,
});
