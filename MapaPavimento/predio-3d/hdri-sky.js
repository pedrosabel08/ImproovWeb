import * as THREE from "three";
import {
  ENVIRONMENT_SETTINGS as SETTINGS,
  smoothRange,
} from "./environment-config.mjs?v=20261030-night-neutral-v1";
import { SKY_PROJECTION_GLSL, horizonVisible } from "./sky-projection.js";

/** EXR → céu visual. Nenhuma atmosfera, luz ou informação solar neste shader. */
export function createHdriSky(scene, onCameraChange = () => {}) {
  const empty = new THREE.DataTexture(new Uint8Array([0, 0, 0, 255]), 1, 1);
  empty.needsUpdate = true;
  const uniforms = {
    skyCameraWorld: { value: new THREE.Matrix4() },
    skyProjectionInverse: { value: new THREE.Matrix4() },
    orthographic: { value: true },
    domeRadius: { value: SETTINGS.sky.domeRadius },
    hdriCurrent: { value: empty },
    hdriPrevious: { value: empty },
    currentCalibration: { value: new THREE.Vector2() },
    previousCalibration: { value: new THREE.Vector2() },
    currentExposure: { value: 1 },
    previousExposure: { value: 1 },
    hdriBlend: { value: 1 },
    showHorizon: { value: false },
    insideDome: { value: true },
  };
  const material = new THREE.ShaderMaterial({
    uniforms,
    depthWrite: false,
    depthTest: false,
    vertexShader: `varying vec2 screen;
      void main() { screen = position.xy; gl_Position = vec4(position.xy, 1.0, 1.0); }`,
    fragmentShader: `varying vec2 screen;
      uniform sampler2D hdriCurrent, hdriPrevious;
      uniform vec2 currentCalibration, previousCalibration;
      uniform float hdriBlend, currentExposure, previousExposure;
      uniform bool showHorizon, insideDome;
      ${SKY_PROJECTION_GLSL}
      vec2 panoramaUv(vec3 ray, vec2 calibration) {
        // x = rotação azimutal, y = correção angular da latitude capturada.
        return vec2(fract(atan(ray.z, ray.x) / 6.28318530718 + 0.5 + calibration.x / 6.28318530718),
          clamp(0.5 + (asin(clamp(ray.y, -1.0, 1.0)) + calibration.y) / 3.14159265359, 0.0, 1.0));
      }
      void main() {
        vec3 ray = worldSkyDirection(screen);
        vec3 color = texture2D(hdriCurrent, panoramaUv(ray, currentCalibration)).rgb * currentExposure;
        if (hdriBlend < 1.0) color = mix(texture2D(hdriPrevious, panoramaUv(ray, previousCalibration)).rgb * previousExposure, color, hdriBlend);
        gl_FragColor = vec4(color, 1.0);
        #include <tonemapping_fragment>
        #include <colorspace_fragment>
        // O MESMO equador mundial da amostragem; espessura aproximada de 2 pixels.
        float width = max(fwidth(ray.y), 0.000001);
        if (showHorizon && insideDome) gl_FragColor.rgb = mix(gl_FragColor.rgb, vec3(0.2, 1.0, 0.8), 1.0 - smoothstep(width * 0.5, width * 1.5, abs(ray.y)));
      }`,
  });
  const mesh = new THREE.Mesh(new THREE.PlaneGeometry(2, 2), material);
  mesh.name = "Ambiente esférico HDRI · mundo Y-up";
  mesh.frustumCulled = false;
  mesh.renderOrder = -10000;
  mesh.raycast = () => {};
  mesh.visible = false;
  scene.add(mesh);
  let active = false,
    transition = null,
    pending = null,
    calibration = { horizonOffset: 0, rotation: 0 };
  let lastNotify = 0,
    notifyTimer;
  let cameraState = { visible: false, pitch: null, insideDome: true };
  const lastWorld = new THREE.Matrix4().set(
      0,
      0,
      0,
      0,
      0,
      0,
      0,
      0,
      0,
      0,
      0,
      0,
      0,
      0,
      0,
      0,
    ),
    lastProjection = lastWorld.clone();
  mesh.onBeforeRender = (renderer, scene, camera) => {
    uniforms.skyCameraWorld.value.copy(camera.matrixWorld);
    uniforms.skyProjectionInverse.value.copy(camera.projectionMatrixInverse);
    uniforms.orthographic.value = !!camera.isOrthographicCamera;
    if (
      lastWorld.equals(camera.matrixWorld) &&
      lastProjection.equals(camera.projectionMatrix)
    )
      return;
    lastWorld.copy(camera.matrixWorld);
    lastProjection.copy(camera.projectionMatrix);
    const forward = camera.getWorldDirection(new THREE.Vector3());
    const position = new THREE.Vector3().setFromMatrixPosition(
      camera.matrixWorld,
    );
    const insideDome = position.length() < uniforms.domeRadius.value;
    uniforms.insideDome.value = insideDome;
    cameraState = {
      visible: insideDome && horizonVisible(camera, uniforms.domeRadius.value),
      pitch: THREE.MathUtils.radToDeg(
        Math.asin(THREE.MathUtils.clamp(forward.y, -1, 1)),
      ),
      insideDome,
    };
    // Matrizes/shader sempre atualizados; DOM de diagnóstico no máximo a 10 Hz.
    clearTimeout(notifyTimer);
    const delay = Math.max(0, 100 - (performance.now() - lastNotify));
    if (!delay) {
      lastNotify = performance.now();
      onCameraChange();
    } else
      notifyTimer = setTimeout(() => {
        lastNotify = performance.now();
        onCameraChange();
      }, delay);
  };
  return {
    mesh,
    uniforms,
    get state() {
      return active
        ? uniforms.hdriCurrent.value === empty
          ? "Aguardando HDRI"
          : "HDRI esférico"
        : "Inativo";
    },
    diagnostics() {
      return {
        ...cameraState,
        ...calibration,
        radius: uniforms.domeRadius.value,
        debug: uniforms.showHorizon.value,
      };
    },
    showHorizon(value) {
      uniforms.showHorizon.value = !!value;
    },
    update() {
      active = true;
      mesh.visible = uniforms.hdriCurrent.value !== empty;
    },
    setEnvironment(texture, preset) {
      if (uniforms.hdriCurrent.value === texture) {
        pending = null;
        return;
      }
      // Termina o blend em curso e retém só o último destino. Evita saltos ao
      // cruzar vários períodos rapidamente com arquivos já em cache.
      if (transition) {
        pending = { texture, preset };
        return;
      }
      const first = uniforms.hdriCurrent.value === empty;
      uniforms.hdriPrevious.value = first
        ? texture
        : uniforms.hdriCurrent.value;
      uniforms.previousCalibration.value.copy(
        uniforms.currentCalibration.value,
      );
      uniforms.previousExposure.value = uniforms.currentExposure.value;
      uniforms.hdriCurrent.value = texture;
      mesh.visible = active;
      calibration = {
        horizonOffset: preset.horizonOffset,
        rotation: preset.rotation,
      };
      uniforms.currentCalibration.value.set(
        THREE.MathUtils.degToRad(preset.rotation),
        THREE.MathUtils.degToRad(preset.horizonOffset),
      );
      uniforms.currentExposure.value = preset.exposure ?? 1;
      if (first) {
        uniforms.previousCalibration.value.copy(
          uniforms.currentCalibration.value,
        );
        uniforms.previousExposure.value = uniforms.currentExposure.value;
      }
      uniforms.hdriBlend.value = first ? 1 : 0;
      transition = first ? null : { started: performance.now() };
    },
    tick(now) {
      if (!transition) return;
      uniforms.hdriBlend.value = smoothRange(
        0,
        SETTINGS.sky.transitionMs,
        now - transition.started,
      );
      if (uniforms.hdriBlend.value === 1) {
        transition = null;
        if (pending) {
          const next = pending;
          pending = null;
          this.setEnvironment(next.texture, next.preset);
        }
      }
    },
    hide() {
      active = false;
      mesh.visible = false;
      transition = pending = null;
      clearTimeout(notifyTimer);
      uniforms.hdriBlend.value = 1;
    },
    dispose() {
      clearTimeout(notifyTimer);
      scene.remove(mesh);
      mesh.geometry.dispose();
      material.dispose();
      empty.dispose();
    },
  };
}
