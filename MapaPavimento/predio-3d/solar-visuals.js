import * as THREE from "three";

// Distância apenas dos auxiliares visuais; não altera a luz ou o vetor solar.
export const SOLAR_ORBIT_SCALE = 1;

/** Textura procedural estática: halo suave, sem pós-processamento ou animação. */
export function solarGlowTexture(star = false) {
  if (typeof document === "undefined") return null;
  const canvas = document.createElement("canvas");
  canvas.width = canvas.height = 256;
  const context = canvas.getContext("2d");
  const glow = context.createRadialGradient(128, 128, 0, 128, 128, 128);
  for (const [stop, color] of [
    [0, "rgba(255,253,224,1)"],
    [0.08, "rgba(255,239,170,.95)"],
    [0.18, "rgba(255,208,100,.55)"],
    [0.4, "rgba(243,163,44,.15)"],
    [1, "rgba(243,163,44,0)"],
  ])
    glow.addColorStop(stop, color);
  context.fillStyle = glow;
  context.fillRect(0, 0, 256, 256);
  if (star) {
    context.translate(128, 128);
    for (let i = 0; i < 8; i++) {
      context.rotate(Math.PI / 4);
      const ray = context.createLinearGradient(0, 0, 100, 0);
      ray.addColorStop(0, "rgba(255,239,175,.35)");
      ray.addColorStop(1, "rgba(255,199,83,0)");
      context.fillStyle = ray;
      context.beginPath();
      context.moveTo(0, -3);
      context.lineTo(i % 2 ? 66 : 100, 0);
      context.lineTo(0, 3);
      context.fill();
    }
  }
  const texture = new THREE.CanvasTexture(canvas);
  texture.colorSpace = THREE.SRGBColorSpace;
  return texture;
}
