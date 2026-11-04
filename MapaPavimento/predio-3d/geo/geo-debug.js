import * as THREE from "three";

const COLORS = {
  original: 0xeb65bd,
  bounds: 0x00ffff,
  FOREST: 0x39c26f,
  GRASS: 0xa2eb63,
  PARK: 0x45ae88,
  WATER_INLAND: 0x59cefa,
  SEA: 0x387bff,
  coastline: 0xffda48,
  BUILDINGS: 0xdddddd,
};
/** Optional wire outlines only. No textures, animation or public diagnostic UI. */
export class GeoDebug {
  constructor(bounds) {
    this.group = new THREE.Group();
    this.group.name = "OSM debug";
    this.parts = new Map();
    this.polygons([bounds.polygon], "bounds");
  }
  line(points, category) {
    if (!this.parts.has(category)) this.parts.set(category, []);
    const target = this.parts.get(category);
    for (let i = 1; i < points.length; i++)
      target.push(
        points[i - 1].x,
        0.12,
        points[i - 1].z,
        points[i].x,
        0.12,
        points[i].z,
      );
  }
  polygons(polygons, category) {
    for (const p of polygons)
      for (const r of [p.outer, ...p.holes]) this.line(r, category);
  }
  finish() {
    for (const [category, positions] of this.parts) {
      const g = new THREE.BufferGeometry();
      g.setAttribute(
        "position",
        new THREE.Float32BufferAttribute(positions, 3),
      );
      const material = new THREE.LineBasicMaterial({
        color: COLORS[category] || 0xffffff,
        depthTest: false,
      });
      const line = new THREE.LineSegments(g, material);
      line.name = `Debug · ${category}`;
      line.renderOrder = 2000;
      line.raycast = () => {};
      this.group.add(line);
    }
    this.parts.clear();
    return this.group;
  }
  dispose() {
    this.group.traverse((o) => {
      o.geometry?.dispose();
      o.material?.dispose();
    });
    this.group.removeFromParent();
  }
}
