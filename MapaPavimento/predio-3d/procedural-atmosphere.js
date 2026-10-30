import * as THREE from "three";
import {
  atmosphereInterval,
  smoothRange,
  ENVIRONMENT_SETTINGS as SETTINGS,
  ENVIRONMENT_THRESHOLDS,
} from "./environment-config.mjs?v=20261030-night-neutral-v1";

// Campo técnico de GI/fill. Nunca amostra EXR, desenha disco solar ou compõe o fundo.
export const ATMOSPHERE_GLSL = `
  uniform vec3 zenith, horizon, ground, sunDirection;
  uniform float warmth, skyIntensity, daylight, forwardScattering, forwardExponent;
  vec3 atmosphere(vec3 ray, float h) {
    vec3 color = h >= 0.0 ? mix(horizon, zenith, pow(h, 0.45))
      : mix(horizon, ground, pow(-h, 0.3));
    float halo = pow(max(0.0, dot(ray, sunDirection)), 8.0);
    color += vec3(0.22, 0.085, 0.025) * halo * warmth * exp(-abs(h) * 2.0);
    float forward = pow(max(0.0, dot(ray, sunDirection)), forwardExponent);
    color += horizon * forward * daylight * forwardScattering * smoothstep(-0.03, 0.18, h);
    return color * skyIntensity;
  }`;

export function createProceduralAtmosphere() {
  const uniforms = {
    zenith: { value: new THREE.Color() },
    horizon: { value: new THREE.Color() },
    ground: { value: new THREE.Color() },
    warmth: { value: 0 },
    sunDirection: { value: new THREE.Vector3(0, 1, 0) },
    skyIntensity: { value: SETTINGS.atmosphereLighting.intensity },
    daylight: { value: 0 },
    forwardScattering: { value: SETTINGS.atmosphereLighting.forwardScattering },
    forwardExponent: { value: SETTINGS.atmosphereLighting.forwardExponent },
  };
  const probeScene = new THREE.Scene();
  const material = new THREE.ShaderMaterial({
    uniforms,
    side: THREE.BackSide,
    toneMapped: false,
    vertexShader: `varying vec3 skyDirection;
      void main() { skyDirection = position; gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0); }`,
    fragmentShader: `varying vec3 skyDirection; ${ATMOSPHERE_GLSL}
      void main() { vec3 ray = normalize(skyDirection); gl_FragColor = vec4(atmosphere(ray, ray.y), 1.0); }`,
  });
  const sphere = new THREE.Mesh(new THREE.SphereGeometry(10, 24, 12), material);
  probeScene.add(sphere);
  const color = new THREE.Color(),
    sample = new THREE.Color(),
    scatter = new THREE.Color(),
    ray = new THREE.Vector3();
  function update(result) {
    const { from, to, mix } = atmosphereInterval(result.altitude);
    for (const key of ["zenith", "horizon", "ground"])
      uniforms[key].value.set(from[key]).lerp(color.set(to[key]), mix);
    uniforms.sunDirection.value.copy(result.direction);
    uniforms.daylight.value = smoothRange(
      ENVIRONMENT_THRESHOLDS.night,
      ENVIRONMENT_THRESHOLDS.earlyMorning,
      result.altitude,
    );
    uniforms.warmth.value =
      smoothRange(-12, 0, result.altitude) *
      (1 - smoothRange(0, 30, result.altitude));
  }
  return {
    uniforms,
    probeScene,
    update,
    updateProbe: update,
    // Integra o mesmo campo em ângulo sólido, apenas a cada mudança solar.
    hemisphereColors() {
      const result = {};
      for (const [side, sign] of [
        ["sky", 1],
        ["ground", -1],
      ]) {
        const sum = new THREE.Color(0, 0, 0);
        for (let i = 0; i < 12; i++) {
          const h = (sign * (i + 0.5)) / 12,
            r = Math.sqrt(1 - h * h);
          for (let j = 0; j < 8; j++) {
            const angle = ((j + 0.5) / 8) * Math.PI * 2;
            ray.set(r * Math.cos(angle), h, r * Math.sin(angle));
            sample
              .copy(uniforms.horizon.value)
              .lerp(
                uniforms[h >= 0 ? "zenith" : "ground"].value,
                Math.pow(Math.abs(h), h >= 0 ? 0.45 : 0.3),
              );
            const dot = Math.max(0, ray.dot(uniforms.sunDirection.value));
            const halo =
              Math.pow(dot, 8) *
              uniforms.warmth.value *
              Math.exp(-Math.abs(h) * 2);
            sample.r += 0.22 * halo;
            sample.g += 0.085 * halo;
            sample.b += 0.025 * halo;
            const forward =
              Math.pow(dot, uniforms.forwardExponent.value) *
              uniforms.daylight.value *
              uniforms.forwardScattering.value *
              smoothRange(-0.03, 0.18, h);
            sample.add(
              scatter.copy(uniforms.horizon.value).multiplyScalar(forward),
            );
            sum.add(sample);
          }
        }
        result[side] = sum.multiplyScalar(uniforms.skyIntensity.value / 96);
      }
      return result;
    },
    dispose() {
      sphere.geometry.dispose();
      material.dispose();
      probeScene.clear();
    },
  };
}
