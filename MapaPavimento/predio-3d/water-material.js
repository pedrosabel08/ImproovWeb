import * as THREE from "three";

export const WATER_SETTINGS = Object.freeze({
  color: 0x194e5d,
  variationColor: 0x306979,
  shoreColor: 0x478284,
  shoreWidthM: 32,
  tileMetres: 108,
  waveStrength: 0.48,
  primary: Object.freeze({ scale: [0.48, 1.32], velocity: [0.019, 0.038] }),
  secondary: Object.freeze({
    scale: [0.89, 1.73],
    velocity: [-0.031, 0.022],
    rotation: 0.49,
    strength: 0.42,
  }),
  normalMipBias: 2.0,
  roughness: 0.49,
  sunlightReflection: 0.13,
  environmentReflection: 0.72,
});

/** Recover the classified shoreline from the sea mesh, including on cache hits.
 * Interior triangle edges cancel. Edges on a side of the acquisition square
 * are artificial and never contribute to the shoreline gradient. */
export function createShoreDistance(sea, bounds, coastState) {
  if (coastState !== "ready" || !sea.length || WATER_SETTINGS.shoreWidthM <= 0)
    return null;
  const edges = new Map();
  const radius = bounds.radius;
  const onSameSide = (a, b) =>
    (Math.abs(a - radius) < 0.01 && Math.abs(b - radius) < 0.01) ||
    (Math.abs(a + radius) < 0.01 && Math.abs(b + radius) < 0.01);
  const key = (p) => `${Math.round(p.x * 1000)}/${Math.round(p.z * 1000)}`;
  for (const mesh of sea) {
    const position = mesh.geometry.attributes.position;
    for (let i = 0; i < position.count; i += 3) {
      const corners = [0, 1, 2].map((j) =>
        bounds.toEN({ x: position.getX(i + j), z: position.getZ(i + j) }),
      );
      for (let j = 0; j < 3; j++) {
        const a = corners[j];
        const b = corners[(j + 1) % 3];
        if (onSameSide(a.x, b.x) || onSameSide(a.z, b.z)) continue;
        const aKey = key(a);
        const bKey = key(b);
        if (aKey === bKey) continue;
        const edgeKey = aKey < bKey ? `${aKey}:${bKey}` : `${bKey}:${aKey}`;
        const edge = edges.get(edgeKey);
        if (edge) edge.count++;
        else edges.set(edgeKey, { a, b, count: 1 });
      }
    }
  }
  const size = 384;
  const step = (2 * radius) / size;
  const width = WATER_SETTINGS.shoreWidthM;
  const pixels = new Uint8Array(size * size).fill(255);
  const cell = (value) =>
    Math.max(0, Math.min(size - 1, Math.floor((value + radius) / step)));
  let work = 0;
  let coast = false;
  for (const { a, b, count } of edges.values()) {
    if (count !== 1) continue;
    coast = true;
    const dx = b.x - a.x;
    const dz = b.z - a.z;
    const invLength2 = 1 / (dx * dx + dz * dz);
    // Subdivide long bounds for rasterization only. Distances always use the original segment.
    const parts = Math.ceil(Math.hypot(dx, dz) / width);
    for (let part = 0; part < parts; part++) {
      const start = part / parts;
      const end = (part + 1) / parts;
      const i0 = cell(a.x + Math.min(dx * start, dx * end) - width);
      const i1 = cell(a.x + Math.max(dx * start, dx * end) + width);
      const j0 = cell(a.z + Math.min(dz * start, dz * end) - width);
      const j1 = cell(a.z + Math.max(dz * start, dz * end) + width);
      work += (i1 - i0 + 1) * (j1 - j0 + 1);
      if (work > 1500000) return null;
      for (let j = j0; j <= j1; j++) {
        const z = (j + 0.5) * step - radius - a.z;
        for (let i = i0; i <= i1; i++) {
          const x = (i + 0.5) * step - radius - a.x;
          const t = Math.max(0, Math.min(1, (x * dx + z * dz) * invLength2));
          const distance = Math.hypot(x - t * dx, z - t * dz);
          const pixel = j * size + i;
          pixels[pixel] = Math.min(
            pixels[pixel],
            Math.round((255 * distance) / width),
          );
        }
      }
    }
  }
  if (!coast) return null;
  const texture = new THREE.DataTexture(pixels, size, size, THREE.RedFormat);
  texture.minFilter = texture.magFilter = THREE.LinearFilter;
  texture.needsUpdate = true;
  return texture;
}

export function createWaterMotion() {
  return {
    primary: { value: new THREE.Vector2() },
    secondary: { value: new THREE.Vector2() },
  };
}

export function updateWaterMotion(motion, seconds) {
  for (const key of ["primary", "secondary"]) {
    const [u, v] = WATER_SETTINGS[key].velocity;
    // Each repeat axis wraps independently, so the phase never jumps.
    motion[key].value.set((seconds * u) % 1, (seconds * v) % 1);
  }
}

export function configureWaterMaterial(material, motion, shoreDistance) {
  const settings = WATER_SETTINGS;
  material.color.setHex(settings.color);
  material.normalScale.setScalar(settings.waveStrength);
  material.roughness = settings.roughness;
  material.aoMapIntensity = 0.12;
  const previous = material.onBeforeCompile;
  const cacheKey = material.customProgramCacheKey;
  const uniforms = {
    flowWavePrimary: motion.primary,
    flowWaveSecondary: motion.secondary,
    flowWavePrimaryScale: {
      value: new THREE.Vector2(...settings.primary.scale),
    },
    flowWaveSecondaryScale: {
      value: new THREE.Vector2(...settings.secondary.scale),
    },
    flowWaveSecondaryRotation: {
      value: new THREE.Vector2(
        Math.cos(settings.secondary.rotation),
        Math.sin(settings.secondary.rotation),
      ),
    },
    flowWaveSecondaryStrength: { value: settings.secondary.strength },
    flowWaveMipBias: { value: settings.normalMipBias },
    flowWaterVariation: { value: new THREE.Color(settings.variationColor) },
    flowWaterShore: { value: new THREE.Color(settings.shoreColor) },
    flowShoreDistance: { value: shoreDistance },
    flowSunReflection: { value: settings.sunlightReflection },
    flowEnvironmentReflection: { value: settings.environmentReflection },
  };
  material.onBeforeCompile = function (shader, renderer) {
    previous.call(this, shader, renderer);
    Object.assign(shader.uniforms, uniforms);
    if (shoreDistance) {
      shader.vertexShader =
        "attribute float flowCoastalWater; varying float flowIsCoastal;\n" +
        shader.vertexShader;
      shader.vertexShader = shader.vertexShader.replace(
        "#include <begin_vertex>",
        "#include <begin_vertex>\nflowIsCoastal = flowCoastalWater;",
      );
    }
    shader.fragmentShader =
      `
      uniform vec2 flowWavePrimary, flowWaveSecondary, flowWavePrimaryScale, flowWaveSecondaryScale, flowWaveSecondaryRotation;
      uniform float flowWaveSecondaryStrength, flowWaveMipBias, flowSunReflection, flowEnvironmentReflection;
      uniform vec3 flowWaterVariation, flowWaterShore;
      ${shoreDistance ? "uniform sampler2D flowShoreDistance; varying float flowIsCoastal;" : ""}
    ` + shader.fragmentShader;
    shader.fragmentShader = shader.fragmentShader.replace(
      "#include <normal_fragment_maps>",
      THREE.ShaderChunk.normal_fragment_maps.replace(
        "vec3 mapN = texture2D( normalMap, vNormalMapUv ).xyz * 2.0 - 1.0;\n\tmapN.xy *= normalScale;",
        `vec2 waveUv = vNormalMapUv;
        float c = flowWaveSecondaryRotation.x;
        float s = flowWaveSecondaryRotation.y;
        mat2 waveRotation = mat2(c, s, -s, c);
        vec2 primaryUv = waveUv * flowWavePrimaryScale + flowWavePrimary;
        vec2 secondaryUv = (waveRotation * waveUv) * flowWaveSecondaryScale + flowWaveSecondary;
        vec3 mapN = texture2D(normalMap, primaryUv, flowWaveMipBias).xyz * 2.0 - 1.0;
        vec2 crossWave = texture2D(normalMap, secondaryUv, flowWaveMipBias).xy * 2.0 - 1.0;
        mapN.xy += transpose(waveRotation) * crossWave * flowWaveSecondaryStrength;
        float footprint = max(length(dFdx(waveUv)), length(dFdy(waveUv)));
        mapN.xy *= normalScale * mix(1.0, 0.22, smoothstep(0.014, 0.06, footprint));`,
      ),
    );
    shader.fragmentShader = shader.fragmentShader.replace(
      "#include <map_fragment>",
      `#ifdef USE_MAP
        float waterTint = 0.5 + 0.5 * sin(dot(vMapUv, vec2(1.09, 0.71)))
          * sin(dot(vMapUv, vec2(-0.52, 1.58)));
        diffuseColor.rgb = mix(diffuseColor.rgb, flowWaterVariation, 0.43 * waterTint);
        diffuseColor.rgb = mix(diffuseColor.rgb, texture2D(map, vMapUv).rgb, 0.023);
        ${
          shoreDistance
            ? `if (flowIsCoastal > 0.5) {
          vec2 en = vec2(dot(contextXZ, edgeBasis.xy), dot(contextXZ, edgeBasis.zw));
          float shore = 1.0 - smoothstep(0.0, 1.0, texture2D(flowShoreDistance, en / (2.0 * edgeRadius) + 0.5).r);
          diffuseColor.rgb = mix(diffuseColor.rgb, flowWaterShore, 0.54 * shore);
        }`
            : ""
        }
      #endif`,
    );
    shader.fragmentShader = shader.fragmentShader.replace(
      "#include <roughnessmap_fragment>",
      `float roughnessFactor = roughness;
      #ifdef USE_ROUGHNESSMAP
        roughnessFactor += 0.12 * (texture2D(roughnessMap, vRoughnessMapUv, 2.0).g - 0.76);
      #endif`,
    );
    // Keep the Three.js Fresnel response and the existing solar/HDRI inputs.
    shader.fragmentShader = shader.fragmentShader.replace(
      "vec3 totalSpecular = reflectedLight.directSpecular + reflectedLight.indirectSpecular;",
      "vec3 totalSpecular = flowSunReflection * reflectedLight.directSpecular + flowEnvironmentReflection * reflectedLight.indirectSpecular;",
    );
  };
  material.customProgramCacheKey = function () {
    return (
      cacheKey.call(this) +
      (shoreDistance ? ":flow-water-shore" : ":flow-water")
    );
  };
}
