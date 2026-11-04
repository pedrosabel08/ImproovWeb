import * as THREE from "three";
import {
  ENVIRONMENT_SETTINGS as SETTINGS,
  smoothRange,
} from "flow/environment-config";

/** Emissão linear PBR. A cobertura noturna representa o interior iluminado;
 * no dia restaura exatamente o vidro adaptado pelo viewer. */
export class NightGlass {
  constructor(settings = SETTINGS.nightGlass) {
    this.settings = settings;
    this.color = new THREE.Color(settings.color);
    this.materials = new Map();
    this.factor = this.target = 0;
    this.enabled = false;
    this.meshes = 0;
  }
  registerModel(root, obraId) {
    this.clear();
    this.enabled =
      this.settings.enabled && Number(obraId) === this.settings.obraId;
    if (!this.enabled) return;
    root.traverse((object) => {
      if (!object.isMesh) return;
      const materials = Array.isArray(object.material)
        ? object.material
        : [object.material];
      let matched = false;
      for (const material of materials)
        if (this.register(material)) matched = true;
      if (matched) this.meshes++;
    });
  }
  register(material, source = null) {
    if (
      !this.enabled ||
      !material?.isMeshStandardMaterial ||
      !/^vidro$/i.test(material.name || "")
    )
      return false;
    if (this.materials.has(material)) return true;
    const original = this.materials.get(source);
    const state = {
      color: (original?.color || material.emissive).clone(),
      intensity: original?.intensity ?? material.emissiveIntensity,
      opacity: original?.opacity ?? material.opacity,
      compile: material.onBeforeCompile,
      key: material.customProgramCacheKey,
      uniform: { value: new THREE.Vector3() },
      dispose: () => this.materials.delete(material),
    };
    material.addEventListener("dispose", state.dispose);
    this.materials.set(material, state);
    material.onBeforeCompile = function (shader, renderer) {
      state.compiled = true;
      state.compile.call(this, shader, renderer);
      // Apenas observa a compilação. Emissão, tone mapping e conversão de cor
      // ficam integralmente a cargo do shader padrão e do hook de reflexos.
    };
    material.customProgramCacheKey = function () {
      return state.key.call(this) + ":flow-night-glass-native-v2";
    };
    material.needsUpdate = true;
    this.apply(material, state);
    return true;
  }
  setFactor(value, now = performance.now()) {
    this.tick(now);
    const target = this.enabled
      ? THREE.MathUtils.clamp(value * this.settings.factorMultiplier, 0, 1)
      : 0;
    if (target === this.target) return;
    this.target = target;
    this.transition = { from: this.factor, started: now };
  }
  apply(material, state) {
    material.opacity = THREE.MathUtils.lerp(
      state.opacity,
      Math.max(state.opacity, this.settings.nightOpacity),
      this.factor,
    );
    const intensity = this.settings.maxIntensity * this.factor;
    state.uniform.value
      .set(this.color.r, this.color.g, this.color.b)
      .multiplyScalar(intensity);
    material.emissive.copy(state.color).multiplyScalar(state.intensity);
    material.emissive.r += state.uniform.value.x;
    material.emissive.g += state.uniform.value.y;
    material.emissive.b += state.uniform.value.z;
    material.emissiveIntensity = this.factor === 0 ? state.intensity : 1;
    if (this.factor === 0) material.emissive.copy(state.color);
  }
  tick(now = performance.now()) {
    if (!this.transition) return;
    const t = smoothRange(
      0,
      this.settings.transitionMs,
      now - this.transition.started,
    );
    this.factor = THREE.MathUtils.lerp(this.transition.from, this.target, t);
    for (const [material, state] of this.materials) this.apply(material, state);
    if (t === 1) this.transition = null;
  }
  clear() {
    this.factor = this.target = 0;
    this.transition = null;
    for (const [material, state] of this.materials) {
      this.apply(material, state);
      material.onBeforeCompile = state.compile;
      material.customProgramCacheKey = state.key;
      material.needsUpdate = true;
      material.removeEventListener("dispose", state.dispose);
    }
    this.materials.clear();
    this.enabled = false;
    this.meshes = 0;
  }
}
