import * as THREE from "three";
import { LightProbeGenerator } from "three/addons/lights/LightProbeGenerator.js";
import {
  ENVIRONMENT_SETTINGS as SETTINGS,
  ENVIRONMENT_THRESHOLDS,
  smoothRange,
  nightFactor,
} from "./environment-config.mjs?v=20261030-night-neutral-v1";

/** GI difusa exclusivamente atmosférica. Não recebe nem amostra HDRIs. */
export class ProceduralLightProbe {
  constructor(renderer, scene, sky, onChange = () => {}) {
    Object.assign(this, { renderer, scene, sky, onChange });
    this.settings = SETTINGS.lightProbe;
    this.probe = new THREE.LightProbe();
    this.probe.name = "GI difusa do céu procedural";
    this.probe.visible = false;
    scene.add(this.probe);
    this.target = new THREE.WebGLCubeRenderTarget(this.settings.cubeSize, {
      type: THREE.HalfFloatType,
      format: THREE.RGBAFormat,
      generateMipmaps: false,
    });
    this.target.texture.colorSpace = THREE.LinearSRGBColorSpace;
    this.camera = new THREE.CubeCamera(0.1, 50, this.target);
    this.cache = new Map();
    this.baseIntensity = this.settings.intensity;
    this.updates = 0;
    this.state = "inactive";
  }
  key(result) {
    const worldAzimuth =
      (Math.atan2(result.direction.x, result.direction.z) * 180) / Math.PI;
    return (
      Math.round(result.altitude / this.settings.elevationThreshold) +
      ":" +
      Math.round(worldAzimuth / this.settings.directionThreshold)
    );
  }
  setIntensity(value) {
    this.baseIntensity = value;
    const day = this.result
      ? smoothRange(
          ENVIRONMENT_THRESHOLDS.night,
          ENVIRONMENT_THRESHOLDS.daylight,
          this.result.altitude,
        )
      : 0;
    const night = this.result ? nightFactor(this.result.altitude) : 1;
    this.probe.intensity =
      value *
      (THREE.MathUtils.lerp(this.settings.baselineScale, 1, day) +
        (this.settings.nightScale - this.settings.baselineScale) * night);
    this.updateColors();
    this.onChange();
  }
  updateColors() {
    if (!this.last) return;
    // Preserva os coeficientes originais no cache; durante o dia a SH é intocada.
    this.probe.sh.copy(this.last.sh);
    const amount = this.result
      ? nightFactor(this.result.altitude) * this.settings.nightNeutralization
      : 0;
    for (const coefficient of this.probe.sh.coefficients) {
      const luminance =
        coefficient.x * 0.2126 +
        coefficient.y * 0.7152 +
        coefficient.z * 0.0722;
      coefficient.x += (luminance - coefficient.x) * amount;
      coefficient.y += (luminance - coefficient.y) * amount;
      coefficient.z += (luminance - coefficient.z) * amount;
    }
  }
  schedule(result) {
    if (this.disposed) return;
    this.result = { ...result, direction: { ...result.direction } };
    this.enabled = true;
    this.setIntensity(this.baseIntensity);
    const key = this.key(result);
    clearTimeout(this.timer);
    this.lastInput = performance.now();
    if (key === this.appliedKey) {
      this.probe.visible = true;
      this.state = "ready";
      this.onChange();
      return;
    }
    if (this.cache.has(key)) {
      this.apply(key, this.cache.get(key));
      return;
    }
    this.state = this.inFlight ? "generating" : "scheduled";
    this.timer = setTimeout(() => this.generate(), this.settings.debounceMs);
    this.onChange();
  }
  async generate() {
    if (this.disposed || !this.enabled || this.inFlight) return;
    const snapshot = this.result;
    const key = this.key(snapshot);
    this.inFlight = true;
    this.state = "generating";
    this.onChange();
    try {
      this.sky.updateProbe(snapshot);
      this.camera.update(this.renderer, this.sky.probeScene);
      const generated = await LightProbeGenerator.fromCubeRenderTarget(
        this.renderer,
        this.target,
      );
      if (this.disposed) return;
      const entry = {
        sh: generated.sh.clone(),
        altitude: snapshot.altitude,
        azimuth: snapshot.azimuth,
        timestamp: Date.now(),
      };
      this.updates++;
      this.cache.set(key, entry);
      if (this.cache.size > this.settings.cacheSize)
        this.cache.delete(this.cache.keys().next().value);
      if (
        this.enabled &&
        key === this.key(this.result) &&
        performance.now() - this.lastInput >= this.settings.debounceMs
      )
        this.apply(key, entry);
    } catch (error) {
      if (!this.disposed && this.enabled) {
        this.state = "error";
        this.error = error.message;
        this.onChange();
      }
    } finally {
      this.inFlight = false;
      if (this.disposed) this.target.dispose();
      else if (
        this.enabled &&
        this.key(this.result) !== this.appliedKey &&
        this.state !== "error"
      )
        this.schedule(this.result);
    }
  }
  apply(key, entry) {
    this.probe.visible = true;
    this.appliedKey = key;
    this.last = entry;
    this.updateColors();
    this.state = "ready";
    this.error = "";
    this.onChange();
  }
  deactivate() {
    this.enabled = false;
    clearTimeout(this.timer);
    this.probe.visible = false;
    this.state = "inactive";
    this.onChange();
  }
  diagnostics() {
    return {
      active: this.probe.visible,
      intensity: this.probe.intensity,
      state: this.state,
      updatedAt: this.last?.timestamp || null,
      altitude: this.last?.altitude ?? null,
      azimuth: this.last?.azimuth ?? null,
      updates: this.updates,
      error: this.error || "",
    };
  }
  dispose() {
    if (this.disposed) return;
    this.disposed = true;
    this.enabled = false;
    clearTimeout(this.timer);
    this.scene.remove(this.probe);
    this.cache.clear();
    if (!this.inFlight) this.target.dispose();
  }
}
