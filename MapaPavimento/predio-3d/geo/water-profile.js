export const WATER_PROFILES_ENABLED = true;

export function waterProfile(feature) {
  const tags = feature.visualTags ?? {};
  const kind = tags.water || tags.waterway || feature.waterway;
  if (/^(river|riverbank|stream|canal|ditch|drain|tidal_channel)$/.test(kind))
    return "flowing";
  if (
    /^(lake|lagoon|pond|reservoir|basin|oxbow)$/.test(kind) ||
    tags.landuse === "reservoir"
  )
    return "standing";
  return "generic";
}
