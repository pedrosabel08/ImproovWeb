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

// Wavelength multipliers refer to the shared 108 m normal tile. All four
// profiles use the existing solar light and specular PMREM in the PBR pass.
export const WATER_LOOKS = {
  ocean: {
    color: 0x184b60,
    variationColor: 0x347587,
    roughness: 0.31,
    waveStrength: 0.57,
    scale: 1.2,
    speed: 0.9,
    sunlightReflection: 0.46,
    environmentReflection: 0.98,
  },
  flowing: {
    color: 0x315c60,
    variationColor: 0x66827d,
    roughness: 0.37,
    waveStrength: 0.32,
    scale: 2.5,
    speed: 0.66,
    sunlightReflection: 0.32,
    environmentReflection: 0.91,
  },
  standing: {
    color: 0x355758,
    variationColor: 0x768a78,
    roughness: 0.24,
    waveStrength: 0.14,
    scale: 1.65,
    speed: 0.22,
    sunlightReflection: 0.24,
    environmentReflection: 1.0,
  },
  generic: {
    color: 0x345b69,
    variationColor: 0x66848a,
    roughness: 0.38,
    waveStrength: 0.25,
    scale: 1.6,
    speed: 0.45,
    sunlightReflection: 0.3,
    environmentReflection: 0.9,
  },
};

// Match collinear portions, since the draped sand has more vertices than the sea.
// A centimetre of tolerance covers Float32 rounding at the edge of the extent.
function sandyShoreline(coast, sand, bounds) {
  const bins = new Map(),
    tolerance = 0.01,
    binWidth = 48;
  function visitBins(a, b, callback) {
    const parts = Math.max(
      1,
      Math.ceil(Math.hypot(b.x - a.x, b.z - a.z) / binWidth),
    );
    for (let i = 0; i < parts; i++) {
      const range = (axis) => {
        const delta = (b[axis] - a[axis]) / parts,
          start = a[axis] + i * delta;
        return [
          Math.floor((Math.min(start, start + delta) - tolerance) / binWidth),
          Math.floor((Math.max(start, start + delta) + tolerance) / binWidth),
        ];
      };
      const [left, right] = range("x"),
        [bottom, top] = range("z");
      for (let x = left; x <= right; x++)
        for (let z = bottom; z <= top; z++) callback(x + "," + z);
    }
  }
  for (const edge of coast) {
    const { a, b } = edge;
    edge.length = Math.hypot(b.x - a.x, b.z - a.z);
    edge.ux = (b.x - a.x) / edge.length;
    edge.uz = (b.z - a.z) / edge.length;
    edge.intervals = [];
    visitBins(a, b, (key) => {
      if (!bins.has(key)) bins.set(key, new Set());
      bins.get(key).add(edge);
    });
  }
  for (const mesh of sand) {
    const p = mesh.geometry.attributes.position;
    for (let i = 0; i < p.count; i += 3) {
      const corners = [0, 1, 2].map((j) =>
        bounds.toEN({ x: p.getX(i + j), z: p.getZ(i + j) }),
      );
      for (let j = 0; j < 3; j++) {
        const a = corners[j],
          b = corners[(j + 1) % 3],
          candidates = new Set();
        visitBins(a, b, (key) =>
          bins.get(key)?.forEach((edge) => candidates.add(edge)),
        );
        for (const edge of candidates) {
          const { ux, uz } = edge;
          const side = (p) => ux * (p.z - edge.a.z) - uz * (p.x - edge.a.x);
          if (Math.max(Math.abs(side(a)), Math.abs(side(b))) > tolerance)
            continue;
          const along = (p) => ux * (p.x - edge.a.x) + uz * (p.z - edge.a.z);
          const start = Math.max(0, Math.min(along(a), along(b))),
            end = Math.min(edge.length, Math.max(along(a), along(b)));
          if (end > start) edge.intervals.push([start, end]);
        }
      }
    }
  }
  return coast.flatMap((edge) => {
    const merged = [];
    for (const interval of edge.intervals.sort((a, b) => a[0] - b[0])) {
      const last = merged.at(-1);
      if (last && interval[0] <= last[1] + tolerance)
        last[1] = Math.max(last[1], interval[1]);
      else merged.push(interval);
    }
    const at = (t) => ({
      x: edge.a.x + edge.ux * t,
      z: edge.a.z + edge.uz * t,
    });
    return merged.map(([start, end]) => ({ a: at(start), b: at(end) }));
  });
}

/** Recover the classified shoreline from the sea mesh, including on cache hits.
 * Interior triangle edges cancel. Edges on a side of the acquisition square
 * are artificial and never contribute to the shoreline gradient. */
export function createShoreDistance(sea, bounds, coastState, sand = null) {
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
  const coastEdges = [...edges.values()].filter((edge) => edge.count === 1);
  const shoreline = sand
    ? sandyShoreline(coastEdges, sand, bounds)
    : coastEdges;
  if (!shoreline.length) return null;
  const size = sand ? Math.min(1024, Math.max(128, Math.ceil(radius))) : 384;
  return rasterizeShore(shoreline, radius, size);
}

function rasterizeShore(shoreline, radius, size) {
  const step = (2 * radius) / size;
  const width = WATER_SETTINGS.shoreWidthM;
  const pixels = new Uint8Array(size * size).fill(255);
  const cell = (value) =>
    Math.max(0, Math.min(size - 1, Math.floor((value + radius) / step)));
  let work = 0;
  for (const { a, b } of shoreline) {
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
      if (work > 1500000) {
        // Preserve a usable mask for long, fragmented coasts at lower resolution.
        if (size > 128)
          return rasterizeShore(
            shoreline,
            radius,
            Math.max(128, Math.floor(size / 2)),
          );
        return null;
      }
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

export function configureWaterMaterial(
  material,
  motion,
  shoreDistance,
  sandy = false,
  profile,
) {
  const settings = { ...WATER_SETTINGS, ...WATER_LOOKS[profile] };
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
      value: new THREE.Vector2(...settings.primary.scale).multiplyScalar(
        settings.scale ?? 1,
      ),
    },
    flowWaveSecondaryScale: {
      value: new THREE.Vector2(...settings.secondary.scale).multiplyScalar(
        settings.scale ?? 1,
      ),
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
    flowWetSand: { value: new THREE.Color(0x68665b) },
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
      uniform vec3 flowWaterVariation, flowWaterShore, flowWetSand;
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
          float distanceRatio = texture2D(flowShoreDistance, en / (2.0 * edgeRadius) + 0.5).r;
          float shore = 1.0 - smoothstep(0.0, 1.0, distanceRatio);
          diffuseColor.rgb = mix(diffuseColor.rgb, flowWaterShore, 0.54 * shore);
          ${sandy ? "diffuseColor.rgb = mix(diffuseColor.rgb, flowWetSand, 0.94 * (1.0 - smoothstep(0.0, 0.50, distanceRatio)));" : ""}
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
      (sandy ? ":beach" : "") +
      (shoreDistance ? ":flow-water-shore" : ":flow-water")
    );
  };
}
