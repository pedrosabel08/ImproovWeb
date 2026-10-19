import * as THREE from "three";

/** Cubemap apenas para iluminação/reflexos. O fundo é o CSS do viewport.
 * O BackgroundCubeMaterial de r180 usa um cubo unitário: numa câmera ortográfica
 * ele vira um quadrado central e deixa o clear branco do shadow pass descoberto.
 * background=null faz o renderer restaurar o clear transparente em cada frame. */
export function setupEnvironment(renderer, scene) {
  const skyScene = new THREE.Scene();
  const material = new THREE.ShaderMaterial({
    side: THREE.BackSide,
    uniforms: {
      zenith: { value: new THREE.Color("#171a1c") },
      horizon: { value: new THREE.Color("#222629") },
      ground: { value: new THREE.Color("#171a1c") },
    },
    vertexShader: `varying vec3 skyDirection;
      void main() { skyDirection = position; gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0); }`,
    fragmentShader: `varying vec3 skyDirection; uniform vec3 zenith; uniform vec3 horizon; uniform vec3 ground;
      void main() { float h = normalize(skyDirection).y;
        vec3 color = h >= 0.0 ? mix(horizon, zenith, pow(h, 0.45)) : mix(horizon, ground, pow(-h, 0.3));
        gl_FragColor = vec4(color, 1.0); }`,
  });
  const geometry = new THREE.SphereGeometry(10, 32, 16);
  skyScene.add(new THREE.Mesh(geometry, material));
  const background = new THREE.WebGLCubeRenderTarget(512, {
    type: THREE.HalfFloatType,
  });
  new THREE.CubeCamera(0.1, 100, background).update(renderer, skyScene);
  const generator = new THREE.PMREMGenerator(renderer);
  const environment = generator.fromCubemap(background.texture);
  generator.dispose();
  geometry.dispose();
  material.dispose();
  scene.background = null;
  renderer.setClearColor(0x000000, 0);
  scene.environment = environment.texture;
  scene.environmentIntensity = 1;
  return {
    dispose() {
      background.dispose();
      environment.dispose();
    },
  };
}
