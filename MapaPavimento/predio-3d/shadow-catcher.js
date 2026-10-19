import * as THREE from "three";
import { boxCorners } from "./solar-shadow.js";

/** Um quad técnico fora do GLB. O alpha desaparece antes das bordas do receptor. */
export function createShadowCatcher() {
  const fade = {
    footprintMin: { value: new THREE.Vector2() },
    footprintMax: { value: new THREE.Vector2() },
    fadeRange: { value: new THREE.Vector2(1, 2) },
  };
  const material = new THREE.ShadowMaterial({
    opacity: 0.36,
    depthWrite: false,
  });
  material.onBeforeCompile = (shader) => {
    Object.assign(shader.uniforms, fade);
    shader.vertexShader =
      "varying vec2 catcherWorldXZ;\n" + shader.vertexShader;
    shader.vertexShader = shader.vertexShader.replace(
      "#include <begin_vertex>",
      "#include <begin_vertex>\ncatcherWorldXZ = (modelMatrix * vec4(transformed, 1.0)).xz;",
    );
    shader.fragmentShader =
      `varying vec2 catcherWorldXZ;
      uniform vec2 footprintMin;
      uniform vec2 footprintMax;
      uniform vec2 fadeRange;
      ` + shader.fragmentShader;
    shader.fragmentShader = shader.fragmentShader.replace(
      "#include <fog_fragment>",
      `#include <fog_fragment>
      vec2 outsideFootprint = max(max(footprintMin - catcherWorldXZ, catcherWorldXZ - footprintMax), vec2(0.0));
      gl_FragColor.a *= 1.0 - smoothstep(fadeRange.x, fadeRange.y, length(outsideFootprint));`,
    );
  };
  material.customProgramCacheKey = () => "spatial-shadow-catcher-v1";
  const catcher = new THREE.Mesh(new THREE.PlaneGeometry(1, 1), material);
  catcher.name = "Shadow Catcher técnico";
  catcher.userData.isShadowCatcher = true;
  catcher.userData.shadowFade = fade;
  catcher.rotation.x = -Math.PI / 2;
  catcher.receiveShadow = true;
  catcher.castShadow = false;
  catcher.raycast = () => {};
  catcher.visible = false;
  return catcher;
}

/** Projeção na direção do Sol, limitada à região em que o fade ainda é visível. */
export function fitShadowCatcher(catcher, bounds, direction) {
  const radius = Math.max(
    bounds.getSize(new THREE.Vector3()).length() / 2,
    0.5,
  );
  const y = bounds.min.y - radius * 0.0005;
  const fadeStart = radius * 0.2,
    fadeEnd = radius * 1.5;
  const footprint = new THREE.Box3(
    new THREE.Vector3(bounds.min.x, y, bounds.min.z),
    new THREE.Vector3(bounds.max.x, y, bounds.max.z),
  );
  if (direction.y > 0) {
    for (const corner of boxCorners(bounds)) {
      const travel = (corner.y - y) / direction.y;
      footprint.expandByPoint(
        new THREE.Vector3(
          THREE.MathUtils.clamp(
            corner.x - direction.x * travel,
            bounds.min.x - fadeEnd,
            bounds.max.x + fadeEnd,
          ),
          y,
          THREE.MathUtils.clamp(
            corner.z - direction.z * travel,
            bounds.min.z - fadeEnd,
            bounds.max.z + fadeEnd,
          ),
        ),
      );
    }
  }
  const padding = radius * 0.03;
  footprint.min.x -= padding;
  footprint.max.x += padding;
  footprint.min.z -= padding;
  footprint.max.z += padding;
  const center = footprint.getCenter(new THREE.Vector3());
  const size = footprint.getSize(new THREE.Vector3());
  catcher.position.copy(center);
  catcher.scale.set(size.x, size.z, 1);
  const fade = catcher.userData.shadowFade;
  fade.footprintMin.value.set(bounds.min.x, bounds.min.z);
  fade.footprintMax.value.set(bounds.max.x, bounds.max.z);
  fade.fadeRange.value.set(fadeStart, fadeEnd);
  return footprint;
}
