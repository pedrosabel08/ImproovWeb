import * as THREE from "three";
import { ENVIRONMENT_SETTINGS as SETTINGS } from "./environment-config.mjs?v=20261030-night-neutral-v1";

const diffuseTerm = "return PI * envMapColor.rgb * envMapIntensity;";
// Hook local aos materiais deste viewer. Não altera ShaderChunk nem outros renderers.
export function specularEnvironmentChunk() {
  const chunk = THREE.ShaderChunk.envmap_physical_pars_fragment;
  if (!chunk.includes(diffuseTerm))
    throw new Error(
      "Revisar isolamento especular para esta versão do Three.js",
    );
  return chunk.replace(
    diffuseTerm,
    "return vec3(0.0); // FLOW: diffuse GI comes only from LightProbe/Hemisphere",
  );
}

export class MaterialReflections {
  constructor() {
    this.materials = new Map();
    this.glassIntensity = SETTINGS.glassIntensity;
    this.scale = 1;
    this.rotation = 0;
  }
  register(material, source = null) {
    if (!material?.isMeshStandardMaterial || this.materials.has(material))
      return;
    const original = source && this.materials.get(source);
    const glass =
      material.userData?.viewerGlass ||
      /vidro|glass/i.test(material.name || "");
    const eligible =
      glass ||
      /água|agua|water|piscina|metal|inox|espelho/i.test(material.name || "") ||
      material.metalness >= SETTINGS.reflections.metalnessThreshold ||
      material.roughness <= SETTINGS.reflections.roughnessThreshold ||
      material.transmission > SETTINGS.reflections.transmissionThreshold ||
      (material.clearcoat > SETTINGS.reflections.clearcoatThreshold &&
        material.clearcoatRoughness <= SETTINGS.reflections.roughnessThreshold);
    if (!eligible) return;
    const state = {
      glass,
      envMap: original ? original.envMap : material.envMap,
      intensity: original ? original.intensity : material.envMapIntensity,
      rotation: original
        ? original.rotation.clone()
        : material.envMapRotation.clone(),
      compile: material.onBeforeCompile,
      key: material.customProgramCacheKey,
    };
    const manager = this;
    // Material.clone não copia onBeforeCompile; registra explicitamente os clones.
    material.onBeforeCompile = function (shader, renderer) {
      state.compile.call(this, shader, renderer);
      if (manager.texture)
        shader.fragmentShader = shader.fragmentShader.replace(
          "#include <envmap_physical_pars_fragment>",
          specularEnvironmentChunk(),
        );
    };
    material.customProgramCacheKey = function () {
      return (
        state.key.call(this) +
        (manager.texture ? ":flow-specular-only-r180" : ":flow-native-r180")
      );
    };
    state.dispose = () => {
      this.materials.delete(material);
    };
    material.addEventListener("dispose", state.dispose);
    this.materials.set(material, state);
    this.apply(material, state);
  }
  registerModel(root) {
    root.traverse((object) => {
      if (!object.isMesh) return;
      for (const material of Array.isArray(object.material)
        ? object.material
        : [object.material])
        this.register(material);
    });
  }
  apply(material, state) {
    const next = this.texture || state.envMap;
    if (material.envMap !== next) {
      material.envMap = next;
      material.needsUpdate = true;
    }
    material.envMapIntensity = this.texture
      ? (state.glass ? this.glassIntensity : state.intensity) * this.scale
      : state.intensity;
    if (this.texture) material.envMapRotation.set(0, this.rotation, 0);
    else material.envMapRotation.copy(state.rotation);
  }
  set(texture, scale = this.scale) {
    this.texture = texture;
    this.scale = scale;
    for (const [material, state] of this.materials) this.apply(material, state);
  }
  setGlassIntensity(value) {
    this.glassIntensity = value;
    this.set(this.texture);
  }
  setRotation(degrees) {
    this.rotation = THREE.MathUtils.degToRad(degrees);
    this.set(this.texture);
  }
  clear() {
    for (const [material, state] of this.materials) {
      material.envMap = state.envMap;
      material.envMapIntensity = state.intensity;
      material.envMapRotation.copy(state.rotation);
      material.onBeforeCompile = state.compile;
      material.customProgramCacheKey = state.key;
      material.removeEventListener("dispose", state.dispose);
      material.needsUpdate = true;
    }
    this.materials.clear();
  }
}
