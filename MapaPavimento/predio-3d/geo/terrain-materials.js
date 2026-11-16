import * as THREE from "three";
import {
  softenEnvironmentEdge,
  urbanMaterial,
} from "./urban-haze.js?v=20261104-terrain-v1";
import {
  WATER_SETTINGS,
  configureWaterMaterial,
  createShoreDistance,
  createWaterMotion,
  updateWaterMotion,
} from "../water-material.js?v=2";

// Texture packs are presentation only. Geometry and its cache keep surface keys.
const LAYER_PACK = {
  asphalt: "asphalt",
  soil: "soil",
  GRASS: "grass",
  PARK: "grass",
  sand: "sand",
  rock: "rock",
  sidewalk: "concrete",
  "sidewalk-extra": "concrete",
  sea: "water",
  water: "water",
};
const PACKS = {
  grass: { folder: "grass-001", metres: 4, normal: 0.55, ao: 0.55 },
  asphalt: {
    folder: "asphalt-track",
    metres: 22,
    normal: 0.36,
    ao: 0,
    roughness: 0.95,
    roughnessFile: "roughness",
    baseTint: 0x686b6d,
  },
  sand: {
    folder: "aerial-beach-01",
    metres: 18,
    normal: 0.35,
    ao: 0,
    roughnessFile: "roughness",
  },
  rock: {
    folder: "rocky-terrain-02",
    metres: 7,
    normal: 0.6,
    ao: 0,
    roughnessFile: "roughness",
    slopeTint: 0x777361,
  },
  concrete: {
    folder: "concrete-floor-01",
    metres: 3,
    normal: 0.33,
    ao: 0,
    roughnessFile: "roughness",
  },
  soil: { folder: "dirt-aerial-02", metres: 20, normal: 0.65, ao: 0.5 },
  water: { folder: "water-002", metres: WATER_SETTINGS.tileMetres, ao: 0.12 },
};
const ROOT = new URL("../../../assets/textures/terrain-pbr/", import.meta.url);
const TEXTURED_OVERLAYS = new Set(["soil", "sand", "rock", "sidewalk-extra"]);

function createWorldUV(geometry) {
  if (geometry.userData.geoMetreUV) return;
  const p = geometry.attributes.position;
  const uv = new Float32Array(p.count * 2);
  for (let i = 0; i < p.count; i++) {
    uv[i * 2] = p.getX(i);
    uv[i * 2 + 1] = -p.getZ(i);
  }
  geometry.setAttribute("uv", new THREE.BufferAttribute(uv, 2));
  geometry.userData.geoMetreUV = true;
}

function landShader(material, { baseTint, slopeTint }) {
  const previous = material.onBeforeCompile;
  const key = material.customProgramCacheKey;
  material.onBeforeCompile = function (shader, renderer) {
    previous.call(this, shader, renderer);
    if (baseTint) {
      shader.uniforms.flowLandTint = { value: new THREE.Color(baseTint) };
      shader.fragmentShader =
        "uniform vec3 flowLandTint;\n" + shader.fragmentShader;
    }
    if (slopeTint) {
      shader.uniforms.flowRockTint = { value: new THREE.Color(slopeTint) };
      shader.vertexShader = "varying float flowSlope;\n" + shader.vertexShader;
      shader.vertexShader = shader.vertexShader.replace(
        "#include <begin_vertex>",
        "#include <begin_vertex>\nflowSlope = 1.0 - abs(normalize(normal).y);",
      );
      shader.fragmentShader =
        "varying float flowSlope; uniform vec3 flowRockTint;\n" +
        shader.fragmentShader;
    }
    // The low-frequency warp is shared by all maps and evaluated per vertex.
    // In metre UVs it avoids a grid of identical cracks without extra lookups.
    shader.vertexShader = shader.vertexShader.replace(
      "#include <uv_vertex>",
      `#include <uv_vertex>
       #ifdef USE_MAP
        vec2 flowMetres = MAP_UV;
        vec2 flowWarp = vec2(
          sin(dot(flowMetres, vec2(0.086, 0.051))) + 0.37 * sin(flowMetres.y * 0.12),
          sin(dot(flowMetres, vec2(-0.062, 0.079))) + 0.41 * sin(flowMetres.x * 0.14)
        ) * 0.35;
        vMapUv += flowWarp;
        #ifdef USE_NORMALMAP
          vNormalMapUv += flowWarp;
        #endif
        #ifdef USE_ROUGHNESSMAP
          vRoughnessMapUv += flowWarp;
        #endif
        #ifdef USE_AOMAP
          vAoMapUv += flowWarp;
        #endif
       #endif`,
    );
    shader.fragmentShader = shader.fragmentShader.replace(
      "#include <map_fragment>",
      `#include <map_fragment>
       #ifdef USE_MAP
        // Broad non-periodic modulation breaks up kilometre-scale repetition.
        vec2 flowGround = vMapUv;
        float flowVariation = sin(dot(flowGround, vec2(0.23, 0.17)))
          * sin(dot(flowGround, vec2(-0.093, 0.18)));
        diffuseColor.rgb *= 1.0 + 0.085 * flowVariation;
        ${baseTint ? "diffuseColor.rgb = mix(diffuseColor.rgb, flowLandTint, 0.16);" : ""}
        ${slopeTint ? "diffuseColor.rgb = mix(diffuseColor.rgb, flowRockTint, 0.23 * (1.0 - smoothstep(0.03, 0.22, flowSlope)));" : ""}
       #endif`,
    );
  };
  material.customProgramCacheKey = function () {
    return (
      key.call(this) +
      ":flow-pbr-land-v1" +
      (baseTint ? ":tint" : "") +
      (slopeTint ? ":slope" : "")
    );
  };
}

/** One texture set per visible surface, lazy and reused between model loads. */
export class TerrainMaterials {
  constructor({ anisotropy = 4, registerWater = () => {} } = {}) {
    this.loader = new THREE.TextureLoader();
    this.anisotropy = Math.min(4, Math.max(1, anisotropy));
    this.registerWater = registerWater;
    this.packs = new Map();
    this.materials = new Map();
    this.revision = 0;
    this.waveMotion = createWaterMotion();
    this.motion = globalThis.matchMedia?.("(prefers-reduced-motion: reduce)");
  }
  load(key) {
    if (!this.packs.has(key)) {
      const { folder, metres, roughnessFile = "roughness-ao" } = PACKS[key];
      const loadMap = async (name, colorSpace = THREE.NoColorSpace) => {
        const url = new URL(`${folder}/${name}.webp?v=1`, ROOT);
        const texture = await this.loader.loadAsync(url.href).catch(() => null);
        if (!texture) return null;
        if (this.disposed) {
          texture.dispose();
          return null;
        }
        texture.colorSpace = colorSpace;
        texture.wrapS = texture.wrapT = THREE.RepeatWrapping;
        texture.repeat.setScalar(1 / metres);
        texture.anisotropy = this.anisotropy;
        return texture;
      };
      this.packs.set(
        key,
        Promise.all([
          loadMap("color", THREE.SRGBColorSpace),
          loadMap("normal"),
          loadMap(roughnessFile),
        ]).then(([map, normalMap, packed]) => ({
          map,
          normalMap,
          roughnessMap: packed,
          aoMap: PACKS[key].ao ? packed : null,
        })),
      );
    }
    return this.packs.get(key);
  }
  material(key, maps, bounds, sky, radius) {
    if (!this.materials.has(key)) {
      const water = key === "water";
      const material = urbanMaterial(0xffffff, { sky, radius });
      material.name = `Flow · ${PACKS[key].folder}`;
      material.map = maps.map;
      material.normalMap = maps.normalMap;
      material.roughnessMap = maps.roughnessMap;
      material.aoMap = maps.aoMap;
      material.roughness = PACKS[key].roughness ?? 0.98;
      material.aoMapIntensity = PACKS[key].ao;
      material.polygonOffset = !water;
      material.polygonOffsetFactor = -1;
      // Deterministic priority where the legacy road ribbons meet at junctions.
      material.polygonOffsetUnits = key === "asphalt" ? -4 : -2;
      softenEnvironmentEdge(material, bounds);
      if (water) {
        configureWaterMaterial(material, this.waveMotion, this.shoreDistance);
        this.registerWater(material);
      } else {
        material.normalScale.setScalar(PACKS[key].normal);
        landShader(material, PACKS[key]);
      }
      this.materials.set(key, material);
    }
    return this.materials.get(key);
  }
  apply(group, bounds, sky, radius, enabled, onStatus) {
    const revision = ++this.revision;
    const layers = new Map();
    this.animateWater = false;
    for (const mesh of group.children) {
      const layer = mesh.name?.slice("OSM · ".length);
      const key = mesh.isMesh && LAYER_PACK[layer];
      if (!key) continue;
      mesh.userData.geoBaseMaterial ??= mesh.material;
      mesh.material = mesh.userData.geoBaseMaterial;
      if (TEXTURED_OVERLAYS.has(layer)) mesh.visible = false;
      if (!layers.has(key)) layers.set(key, []);
      layers.get(key).push(mesh);
    }
    if (!enabled || !layers.size) {
      onStatus(enabled ? "empty" : "off", []);
      return;
    }
    onStatus("loading", []);
    const applied = [];
    const pending = [...layers].map(async ([key, meshes]) => {
      const maps = await this.load(key);
      if (revision !== this.revision || this.disposed || !maps.map) return;
      if (key === "water" && this.shoreDistance === undefined) {
        const sea = meshes.filter((mesh) => mesh.name === "OSM · sea");
        this.shoreDistance = createShoreDistance(
          sea,
          bounds,
          group.userData.metrics?.coastline,
        );
        if (this.shoreDistance)
          for (const mesh of meshes) {
            const count = mesh.geometry.attributes.position.count;
            const coastal = new Uint8Array(count);
            if (mesh.name === "OSM · sea") coastal.fill(255);
            mesh.geometry.setAttribute(
              "flowCoastalWater",
              new THREE.BufferAttribute(coastal, 1, true),
            );
          }
      }
      const material = this.material(key, maps, bounds, sky, radius);
      for (const mesh of meshes) {
        createWorldUV(mesh.geometry);
        if (TEXTURED_OVERLAYS.has(mesh.name.slice("OSM · ".length)))
          mesh.visible = true;
        mesh.material = material;
      }
      if (key === "water") this.animateWater = !!maps.normalMap;
      applied.push(key);
    });
    Promise.all(pending).then(() => {
      if (revision === this.revision && !this.disposed)
        onStatus(
          applied.length === layers.size ? "ready" : "fallback",
          applied.sort(),
        );
    });
  }
  update(now) {
    if (this.animateWater)
      updateWaterMotion(
        this.waveMotion,
        this.motion?.matches ? 0 : now * 0.001,
      );
  }
  clear() {
    this.revision++;
    this.animateWater = false;
    this.shoreDistance?.dispose();
    this.shoreDistance = undefined;
    for (const material of this.materials.values()) material.dispose();
    this.materials.clear();
  }
  dispose() {
    this.disposed = true;
    this.clear();
    for (const promise of this.packs.values())
      promise.then((maps) =>
        new Set(Object.values(maps)).forEach((texture) => texture?.dispose()),
      );
    this.packs.clear();
  }
}
