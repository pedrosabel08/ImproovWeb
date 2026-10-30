// Calibração visual do Prédio 3D. Graus de elevação astronômica, nunca horas civis.
export const ENVIRONMENTS = {
  // Graus fixos no mundo: nunca seguem a câmera ou alteram o SunCalc.
  morningEarly: {
    file: "HDRI_manha.exr",
    label: "Manhã",
    horizonOffset: 0,
    rotation: 0,
    exposure: 1,
  },
  morningLate: {
    file: "HDRI_manha2.exr",
    label: "Manhã 2",
    horizonOffset: 0,
    rotation: 0,
    exposure: 1,
  },
  afternoon: {
    file: "HDRI_tarde.exr",
    label: "Tarde",
    horizonOffset: 0,
    rotation: 0,
    exposure: 1,
  },
  // O EXR noturno tem média semelhante ao diurno (~0.60 de luminância).
  // Correção escalar somente do fundo, sem filtro cromático nem impacto na GI.
  night: {
    file: "HDRI_noite.exr",
    label: "Noite",
    horizonOffset: 0,
    rotation: 0,
    exposure: 0.15,
  },
};

export const ENVIRONMENT_THRESHOLDS = {
  night: -6,
  earlyMorning: 8,
  // Na descida, mantém ambiente diurno próximo ao zênite e aquece abaixo deste limite.
  afternoon: 30,
  daylight: 30,
  preloadMargin: 4,
};

export const ENVIRONMENT_SETTINGS = {
  debounceMs: 180,
  preloadDelayMs: 900,
  retryDelayMs: 30000,
  glassIntensity: 0.8,
  glassIor: 1.45,
  // Hotspots dos EXRs não competem com o Sol físico na iluminação difusa.
  // Mantém HDR nos reflexos, limitando somente luminâncias extremas do mapa IBL.
  maxIBLLuminance: 8,
  lightProbe: {
    intensity: 0.2,
    baselineScale: 0.08,
    // A atmosfera noturna é escura: compensa somente para leitura do modelo.
    nightScale: 3,
    nightNeutralization: 0.9,

    debounceMs: 200,
    elevationThreshold: 1,
    directionThreshold: 2,
    cubeSize: 32,
    cacheSize: 32,
  },

  hemisphere: {
    targetLuminance: 0.5,
    chromaticStrength: 0.2,
    intensity: 0.06,
    baselineScale: 0.08,
    // Fill de visualização: evita que fachadas desapareçam no período noturno.
    nightScale: 10,
    transitionMs: 600,
  },
  atmosphereLighting: {
    intensity: 1,
    forwardScattering: 0.08,
    forwardExponent: 4,
  },
  sky: {
    // Esfera fixa na origem mundial (metros), equador em Y=0.
    // Raios ortográficos reais intersectam a esfera, sem FOV artificial.
    domeRadius: 10000,
    transitionMs: 600,
    // Remove o disco solar antes da composição; não apenas limita seu brilho.
    hotspotThreshold: 12,
    luminanceFloor: 0.015,
  },
  reflections: {
    dayIntensity: 0.4,
    nightIntensity: 0.18,
    metalnessThreshold: 0.3,
    roughnessThreshold: 0.25,
    clearcoatThreshold: 0.1,
    transmissionThreshold: 0.05,
  },
  tuning: {
    probe: { min: 0, max: 2, step: 0.01 },
    hemisphere: { min: 0, max: 0.3, step: 0.005 },
    glass: { min: 0, max: 2, step: 0.01 },
  },
  // Gradiente contínuo: noite → crepúsculo → horizonte quente → dia.
  atmosphere: [
    {
      elevation: -18,
      zenith: "#10243d",
      horizon: "#22395b",
      ground: "#101826",
      sun: "#ff8844",
    },
    {
      elevation: -6,
      zenith: "#17335a",
      horizon: "#6b6481",
      ground: "#1a2638",
      sun: "#ff984f",
    },
    {
      elevation: 0,
      zenith: "#234574",
      horizon: "#e69667",
      ground: "#3d4652",
      sun: "#ffab65",
    },
    {
      elevation: 8,
      zenith: "#377bb1",
      horizon: "#f1c498",
      ground: "#657582",
      sun: "#ffd4a0",
    },
    {
      elevation: 30,
      zenith: "#234574",
      horizon: "rgb(115, 136, 148)",
      ground: "#a8cfe9",
      sun: "#ffab65",
    },
    {
      elevation: 90,
      zenith: "#62a9d5",
      horizon: "#d4e5ef",
      ground: "#91a9ba",
      sun: "#fffaf2",
    },
  ],
};

export function smoothRange(min, max, value) {
  const t = Math.max(0, Math.min(1, (value - min) / (max - min)));
  return t * t * (3 - 2 * t);
}

export function selectEnvironment(
  { altitude, rising },
  thresholds = ENVIRONMENT_THRESHOLDS,
) {
  if (altitude <= thresholds.night) return "night";
  if (!rising)
    return altitude < thresholds.afternoon ? "afternoon" : "morningLate";
  return altitude < thresholds.earlyMorning ? "morningEarly" : "morningLate";
}

// Somente um vizinho é antecipado. Não encadeia o download dos quatro arquivos.
export function adjacentEnvironment(
  result,
  thresholds = ENVIRONMENT_THRESHOLDS,
) {
  const key = selectEnvironment(result, thresholds);
  const { altitude, rising } = result;
  const margin = thresholds.preloadMargin;
  if (key === "night")
    return rising && altitude > thresholds.night - margin
      ? "morningEarly"
      : null;
  if (key === "morningEarly") return "morningLate";
  if (key === "morningLate")
    return !rising && altitude < thresholds.afternoon + margin
      ? "afternoon"
      : null;
  return altitude < thresholds.night + margin ? "night" : null;
}

export function atmosphereInterval(altitude) {
  const stops = ENVIRONMENT_SETTINGS.atmosphere;
  for (let i = 1; i < stops.length; i++) {
    if (altitude <= stops[i].elevation)
      return {
        from: stops[i - 1],
        to: stops[i],
        mix: smoothRange(stops[i - 1].elevation, stops[i].elevation, altitude),
      };
  }
  return { from: stops.at(-1), to: stops.at(-1), mix: 0 };
}

// Ponto de extensão para futuras luzes arquitetônicas; não cria luzes agora.
export function nightFactor(altitude) {
  return 1 - smoothRange(ENVIRONMENT_THRESHOLDS.night, 0, altitude);
}
