import { northDirection } from "../north-direction.js";

const EARTH_RADIUS = 6371008.8;
const RAD = Math.PI / 180;

export function validGeoConfig(config) {
  return (
    !!config &&
    Number.isFinite(config.latitude) &&
    Math.abs(config.latitude) < 89 &&
    Number.isFinite(config.longitude) &&
    Math.abs(config.longitude) <= 180 &&
    Number.isFinite(config.norte_graus) &&
    config.norte_graus >= 0 &&
    config.norte_graus < 360
  );
}

/** Tangent-plane approximation for a 1 km neighbourhood, in metres.
 * At north=0: N=-Z, S=+Z, E=+X, W=-X, up=+Y.
 * Uses the SAME north/east basis as solarDirection; the GLB never rotates.
 * Output origin (0,0) is the geographic site; scene anchoring is separate. */
export function geoProjector(config) {
  if (!validGeoConfig(config))
    throw new Error("Localização ou Norte não definido.");
  const north = northDirection(config.norte_graus);
  const longitudeScale = EARTH_RADIUS * RAD * Math.cos(config.latitude * RAD);
  return ({ lat, lon }) => {
    const deltaLon = ((lon - config.longitude + 540) % 360) - 180;
    const east = deltaLon * longitudeScale;
    const metresNorth = (lat - config.latitude) * EARTH_RADIUS * RAD;
    return {
      x: east * -north.z + metresNorth * north.x,
      z: east * north.x + metresNorth * north.z,
    };
  };
}

export function pointInRing(point, ring) {
  let inside = false;
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const a = ring[i],
      b = ring[j];
    if (
      a.z > point.z !== b.z > point.z &&
      point.x < ((b.x - a.x) * (point.z - a.z)) / (b.z - a.z) + a.x
    )
      inside = !inside;
  }
  return inside;
}
