// Presentation only: no project data, selection or camera changes belong here.
export const IMAGERY_SOURCE = 'earth-imagery';
export const DEM_SOURCE = 'terrain-dem';
export const SHADE_SOURCE = 'hillshade-dem';
export const HILLSHADE_LAYER = 'earth-hillshade';
const zoom = (...stops) => ['interpolate', ['linear'], ['zoom'], ...stops];
const TERRAIN_ZOOM = 6.5;

// Native 5.13 globe normally flattens at z11–12. Flatten regionally instead:
// its terrain fog-matrix path is unsupported while the sphere is rendering.
export const cartographicProjection = type => ({ type: type === 'globe'
  ? zoom(5, 'vertical-perspective', TERRAIN_ZOOM, 'mercator') : 'mercator' });

export const sky = {
  'sky-color': '#071623',
  'horizon-color': '#4389bc',
  'fog-color': '#182f43',
  'sky-horizon-blend': 0.35,
  'horizon-fog-blend': 0.65,
  'fog-ground-blend': 0.85,
  'atmosphere-blend': zoom(0, 0.55, 3, 0.45, 6, 0.2, 9, 0),
};

export function prepareCartography(style, config) {
  // Mutate the freshly fetched style, never replace provider sources or credits.
  if (config.style === 'https://tiles.openfreemap.org/styles/dark') {
    const labelZooms = {
      place_country_major: 1.5, place_country_minor: 3.5, place_country_other: 5,
      place_state: 4.5, place_city_large: 5, place_city: 7, place_town: 9,
      place_village: 11, place_suburb: 12, place_other: 13,
      water_name: 8, highway_name_motorway: 9, highway_name_other: 12,
    };
    for (const layer of style.layers) {
      layer.paint ||= {};
      if (layer.type === 'background') layer.paint['background-color'] = '#172c3b';
      if (layer.id === 'water') layer.paint['fill-color'] = '#091d2c';
      if (layer.type === 'fill' && layer['source-layer'] === 'landcover')
        layer.paint['fill-color'] = '#1b3540';
      if (layer.id === 'landcover_wood') delete layer.paint['fill-pattern'];
      if (layer.type === 'symbol' && layer.layout?.['text-field']) {
        layer.paint['text-color'] = '#c1d1da';
        layer.paint['text-halo-color'] = '#102432';
        layer.paint['text-halo-width'] = 1.4;
        layer.layout['text-field'] = ['coalesce', ['get', 'name:pt'], ['get', 'name:latin'], ['get', 'name']];
        layer.layout['text-transform'] = 'none';
        layer.layout['text-allow-overlap'] = false;
        layer.layout['text-ignore-placement'] = false;
        layer.layout['text-padding'] = 8;
        // This legacy style references circle-11, absent from the current sprite.
        // Geographic names do not need extra dots alongside project markers.
        if (layer['source-layer'] === 'place') delete layer.layout['icon-image'];
        if (labelZooms[layer.id] !== undefined) {
          layer.minzoom = Math.max(layer.minzoom || 0, labelZooms[layer.id]);
          layer.paint['text-opacity'] = zoom(layer.minzoom, 0, layer.minzoom + 0.7, 0.92);
        }
      }
      if (layer.type === 'line' && layer['source-layer'] === 'boundary') {
        layer.paint['line-color'] = '#71929e';
        const state = layer.id === 'boundary_state';
        layer.minzoom = Math.max(layer.minzoom || 0, state ? 4.5 : 2.5);
        layer.paint['line-opacity'] = state ? zoom(4.5, 0, 6, 0.28, 10, 0.5) : zoom(2.5, 0, 5, 0.32, 9, 0.55);
        layer.paint['line-width'] = zoom(3, 0.4, 9, 1, 18, 2);
      }
      if (layer.type === 'line' && layer['source-layer'] === 'transportation') {
        layer.minzoom = Math.max(layer.minzoom || 0, 6.5);
        layer.paint['line-color'] = layer.id.includes('casing') ? '#142a37' : '#4b6571';
      }
    }
  }
  if (config.imagery) {
    style.sources[IMAGERY_SOURCE] = {
      type: 'raster', tiles: [config.imagery], tileSize: 256,
      minzoom: 0, maxzoom: 8,
      attribution: '<a href="https://nasa-gibs.github.io/gibs-api-docs/">Imagery: NASA GIBS / Blue Marble</a>',
    };
    // The vector surface stays underneath, so a missing JPEG leaves a working map.
    const index = style.layers.findIndex(l => l.type !== 'background' && l.type !== 'fill');
    style.layers.splice(index < 0 ? style.layers.length : index, 0, {
      id: IMAGERY_SOURCE, type: 'raster', source: IMAGERY_SOURCE, maxzoom: 8,
      paint: {
        'raster-opacity': zoom(0, 1, 6, 1, 8, 0),
        'raster-brightness-min': 0.02, 'raster-brightness-max': 0.78,
        'raster-saturation': -0.25, 'raster-contrast': 0.08,
        'raster-fade-duration': 300,
      },
    });
  }
  return style;
}

// Exaggeration is a number in 5.13, not a zoom expression. Keep the regional
// transition flat, then grow to the real vertical scale after gestures settle.
export function terrainExaggeration(zoomLevel) {
  if (zoomLevel < TERRAIN_ZOOM) return 0;
  return Math.round(Math.min(1, (zoomLevel - TERRAIN_ZOOM) / 3) * 10) / 10;
}

export function attachRelief(map, config) {
  if (!config.dem) return null;
  const dem = {
    type: 'raster-dem', url: config.dem,
    tileSize: config.demTileSize, encoding: config.demEncoding,
    minzoom: 5, maxzoom: 12,
    attribution: config.demAttribution,
  };
  map.addSource(DEM_SOURCE, dem);
  // 5.13 explicitly recommends separate sources: sharing reduces hillshade
  // quality. URLs are identical, so the browser's HTTP cache reuses tile bytes.
  map.addSource(SHADE_SOURCE, { ...dem, maxzoom: 11 });
  const before = map.getStyle().layers.find(l => l.type === 'line' || l.type === 'symbol')?.id;
  map.addLayer({
    id: HILLSHADE_LAYER, type: 'hillshade', source: SHADE_SOURCE, minzoom: 5,
    paint: {
      'hillshade-exaggeration': zoom(5, 0, 7, 0.08, 10, 0.16, 14, 0.12),
      'hillshade-shadow-color': '#152b38',
      'hillshade-highlight-color': '#536e7c',
      'hillshade-accent-color': '#2d4855',
      'hillshade-illumination-direction': 315,
      'hillshade-illumination-anchor': 'map',
    },
  }, before);
  let enabled = true, failed = false, last = null;
  const sync = () => {
    const value = enabled && !failed ? terrainExaggeration(map.getZoom()) : 0;
    if (value === last) return;
    last = value;
    // 5.13 replaces Terrain/RenderToTexture on setTerrain(options) without
    // releasing the previous instance. Dispose it first to avoid stale cluster
    // textures and accumulating GPU targets when exaggeration changes.
    if (map.getTerrain()) map.setTerrain(null);
    if (value > 0) map.setTerrain({ source: DEM_SOURCE, exaggeration: value });
  };
  const visibility = () => map.setLayoutProperty(HILLSHADE_LAYER, 'visibility', enabled && !failed ? 'visible' : 'none');
  // setTerrain rebuilds render targets in 5.13. Never do that on every frame.
  // The view's natural scale provides continuous relief during the gesture.
  map.on('zoomend', sync);
  // Disable before entering the globe path on zoom-out (not after its animation).
  map.on('zoom', () => { if (map.getZoom() < TERRAIN_ZOOM && last) sync(); });
  sync();
  return {
    setEnabled(value) { enabled = value; visibility(); sync(); },
    fail() {
      // Idempotent: an outage can emit several tile errors in the same frame.
      if (failed) return;
      failed = true;
      visibility();
      sync();
    },
  };
}

export function addLocalBase(map) {
  if (map.getSource('earth-fallback')) return;
  map.addSource('earth-fallback', {
    type: 'geojson', data: new URL('./styles/countries.geojson', import.meta.url).href,
    attribution: '<a href="https://www.naturalearthdata.com/about/terms-of-use/">Natural Earth</a>',
  });
  const layers = map.getStyle().layers;
  const background = layers.find(l => l.type === 'background');
  if (background) map.setPaintProperty(background.id, 'background-color', '#091d2c');
  const before = layers.find(l => l.type !== 'background')?.id;
  map.addLayer({ id: 'earth-fallback-land', type: 'fill', source: 'earth-fallback',
    paint: { 'fill-color': '#172c3b', 'fill-outline-color': '#466a7b' } }, before);
}
