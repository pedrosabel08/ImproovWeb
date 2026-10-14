/** GLB/Three.js: +Y vertical, 0°=-Z, 90°=+X; horário visto de +Y. */
export function normalizeNorth(degrees) {
  const value = ((degrees % 360) + 360) % 360;
  return (Math.round(value * 10) % 3600) / 10;
}
export function northDirection(degrees) {
  const angle = (degrees * Math.PI) / 180;
  return { x: Math.sin(angle), y: 0, z: -Math.cos(angle) };
}
export function dialNorth(x, y) {
  return normalizeNorth((Math.atan2(x, -y) * 180) / Math.PI);
}
