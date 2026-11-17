// OSM semantics only. The viewer maps these surface layers to visual assets.
const EARTH = /^(ground|dirt|earth|mud)$/;
const VEHICULAR_STREETS =
  /^(motorway|trunk|primary|secondary|tertiary)(_link)?$|^(residential|unclassified|living_street)$/;

export function isSoilArea(tags) {
  // A closed road loop is not an area. Similarly, parking and circular
  // paths need an explicit area and earth surface to qualify as soil.
  if (tags.area === "no" || (tags.highway && tags.area !== "yes")) return false;
  return tags.natural === "mud" || EARTH.test(tags.surface ?? "");
}

export function roadLayer(feature) {
  const surface = feature.visualTags?.surface;
  if (surface) return surface === "asphalt" ? "asphalt" : "road";
  // OSM often omits surface on regular streets. Paths, tracks and service
  // roads need an explicit asphalt tag; neither "paved" nor gravel is asphalt.
  return VEHICULAR_STREETS.test(feature.highway) ? "asphalt" : "road";
}

export function mappedLandLayer(tags) {
  if (tags.area === "no" || (tags.highway && tags.area !== "yes")) return null;
  if (/^(beach|sand)$/.test(tags.natural)) {
    if (
      /^(rock|bare_rock|stone|pebblestone|gravel|shingle)$/.test(tags.surface)
    )
      return "rock";
    return !tags.surface || tags.surface === "sand" ? "sand" : null;
  }
  if (
    /^(bare_rock|scree|shingle)$/.test(tags.natural) ||
    /^(rock|bare_rock|stone)$/.test(tags.surface)
  )
    return "rock";
  return null;
}

export function isMappedSidewalk(tags = {}) {
  if (tags.footway === "crossing") return false;
  if (!/^(footway|pedestrian)$/.test(tags.highway)) return false;
  if (tags.surface) return /^concrete(:plates|:lanes)?$/.test(tags.surface);
  return tags.highway === "footway" && tags.footway === "sidewalk";
}

export function sidewalkSides(tags = {}) {
  const value = tags.sidewalk;
  return ["left", "right"].filter((side) => {
    const explicit = tags[`sidewalk:${side}`] ?? tags["sidewalk:both"];
    if (explicit) return explicit === "yes";
    return value === side || value === "both";
  });
}
