# Public cartographic fallback

`dark.json`: snapshot of https://tiles.openfreemap.org/styles/dark retrieved on
2026-11-04. Used only if fetching the configured style fails. Original sources,
sprites and glyphs remain public OpenFreeMap resources. This is not an offline
street map. Upstream licenses are preserved in LICENSE.md and LICENSE-Dark.md.

`countries.geojson`: Natural Earth 1:110m admin-0 countries from
https://github.com/nvkelso/natural-earth-vector/blob/master/geojson/ne_110m_admin_0_countries.geojson
retrieved on 2026-11-04. Properties removed and coordinates rounded to 3 decimals.
Only loaded when vector cartography fails, beneath all normal layers. This gives
a coarse geographic reference and preserves project markers even during an
outage; it cannot supply street-level detail. No project data is included.

Natural Earth data is public domain, including commercial use and modification:
https://www.naturalearthdata.com/about/terms-of-use/
