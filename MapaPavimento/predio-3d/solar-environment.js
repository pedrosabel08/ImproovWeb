import * as THREE from "three";
import { ExrDecoder } from "./exr-decoder.js?v=20261030-horizon-final";
import { createHdriSky } from "./hdri-sky.js?v=20261030-night-neutral-v1";
import { createProceduralAtmosphere } from "./procedural-atmosphere.js?v=20261030-night-neutral-v1";
import { ProceduralLightProbe } from "./procedural-light-probe.js?v=20261030-night-neutral-v1";
import { MaterialReflections } from "./material-reflections.js?v=20261030-night-neutral-v1";
import {
  ENVIRONMENTS,
  ENVIRONMENT_SETTINGS as SETTINGS,
  ENVIRONMENT_THRESHOLDS,
  selectEnvironment,
  adjacentEnvironment,
  smoothRange,
  nightFactor,
} from "./environment-config.mjs?v=20261030-night-neutral-v1";

/** Coordena o ambiente existente. SolarLighting é o único dono da luz solar. */
export class SolarEnvironment {
  constructor(renderer, scene, fallbackTexture, fallback) {
    Object.assign(this, { renderer, scene, fallbackTexture, fallback });
    this.cache = new Map();
    this.listeners = new Set();
    this.override = "auto";
    this.current = this.requested = null;
    this.state = "navigation";
    this.runtime = {
      probe: SETTINGS.lightProbe.intensity,
      hemisphere: null,
      glass: SETTINGS.glassIntensity,
    };
    this.abort = new AbortController();
    this.sky = createHdriSky(scene, () => this.notify());
    this.atmosphere = createProceduralAtmosphere();
    this.probe = new ProceduralLightProbe(
      renderer,
      scene,
      this.atmosphere,
      () => this.notify(),
    );
    this.reflections = new MaterialReflections();
    this.generator = new THREE.PMREMGenerator(renderer);
    this.decoder = new ExrDecoder();
  }
  subscribe(callback) {
    this.listeners.add(callback);
    callback(this.diagnostics());
    return () => this.listeners.delete(callback);
  }
  diagnostics() {
    return {
      current: this.current,
      requested: this.requested,
      state: this.state,
      override: this.override,
      intensity: this.reflectionScale || 0,
      globalEnvironment: !!this.scene.environment,
      nightFactor: this.result ? nightFactor(this.result.altitude) : 0,
      processed: [...this.cache.values()].filter((entry) => entry.target)
        .length,
      probe: this.probe?.diagnostics(),
      sky: this.sky.state,
      horizon: this.sky.diagnostics(),
      hemisphere: this.ambient
        ? {
            skyColor: "#" + this.ambient.color.getHexString(),
            groundColor: "#" + this.ambient.groundColor.getHexString(),
            intensity: this.ambient.intensity,
          }
        : null,
      runtime: {
        ...this.runtime,
        hemisphere: this.runtime.hemisphere ?? SETTINGS.hemisphere.intensity,
      },
      reflectionMaterials: this.reflections?.materials.size || 0,
      error: this.error || "",
    };
  }
  notify() {
    if (!this.disposed)
      for (const listener of this.listeners) listener(this.diagnostics());
  }
  registerModel(root) {
    this.reflections.registerModel(root);
  }
  registerMaterial(material, source) {
    this.reflections.register(material, source);
  }
  clearModel() {
    this.reflections.clear();
  }
  showHorizon(value) {
    this.sky.showHorizon(value);
    this.notify();
  }
  setTuning(key, rawValue) {
    const range = SETTINGS.tuning[key],
      value = Number(rawValue);
    if (!range || !Number.isFinite(value)) return;
    this.runtime[key] = THREE.MathUtils.clamp(value, range.min, range.max);
    if (key === "probe") this.probe.setIntensity(this.runtime.probe);
    if (key === "hemisphere") this.updateHemisphere();
    if (key === "glass") this.reflections.setGlassIntensity(this.runtime.glass);
    this.notify();
  }
  resetTuning() {
    this.runtime = {
      probe: SETTINGS.lightProbe.intensity,
      hemisphere: null,
      glass: SETTINGS.glassIntensity,
    };
    this.probe.setIntensity(this.runtime.probe);
    this.reflections.setGlassIntensity(this.runtime.glass);
    this.updateHemisphere();
    this.notify();
  }
  setOverride(key) {
    if (key !== "auto" && !Object.hasOwn(ENVIRONMENTS, key)) return;
    this.override = key;
    // Não chama update: nem Sol, atmosfera física nem SH participam do override.
    if (this.result)
      this.requestPreset(key === "auto" ? selectEnvironment(this.result) : key);
    this.notify();
  }
  navigation() {
    this.result = null;
    this.requested = this.current = null;
    clearTimeout(this.timer);
    clearTimeout(this.preloadTimer);
    this.fillTransition = null;
    this.sky.hide();
    this.probe.deactivate();
    this.reflections.set(null);
    this.scene.environment = this.fallbackTexture;
    this.scene.environmentIntensity = 1;
    if (this.navigationColors) {
      this.ambient.color.copy(this.navigationColors.sky);
      this.ambient.groundColor.copy(this.navigationColors.ground);
    }
    this.state = "navigation";
    this.error = "";
    this.notify();
  }
  update(result, light, ambient) {
    if (this.disposed) return;
    const entering = !this.result;
    this.navigationColors ??= {
      sky: ambient.color.clone(),
      ground: ambient.groundColor.clone(),
    };
    Object.assign(this, { result, light, ambient });
    // A fachada nunca recebe diffuse IBL dos EXRs. Maps apenas nos reflexivos.
    this.scene.environment = null;
    this.scene.environmentIntensity = 0;
    this.sky.update(result);
    this.atmosphere.update(result);
    this.fillColors = this.atmosphere.hemisphereColors();
    this.probe.schedule(result);
    const day = this.dayFactor();
    this.reflectionScale = THREE.MathUtils.lerp(
      SETTINGS.reflections.nightIntensity,
      SETTINGS.reflections.dayIntensity,
      day,
    );
    this.reflections.set(
      this.current ? this.cache.get(this.current)?.target?.texture : null,
      this.reflectionScale,
    );
    this.updateHemisphere(entering);
    this.lastSolarInput = performance.now();
    this.requestPreset(
      this.override === "auto" ? selectEnvironment(result) : this.override,
    );
    this.notify();
  }
  dayFactor() {
    return smoothRange(
      ENVIRONMENT_THRESHOLDS.night,
      ENVIRONMENT_THRESHOLDS.daylight,
      this.result.altitude,
    );
  }
  fillColor(side) {
    const color = this.fillColors[side].clone();
    const luminance = color.r * 0.2126 + color.g * 0.7152 + color.b * 0.0722;
    const target = SETTINGS.hemisphere.targetLuminance;
    if (luminance > 1e-8) color.multiplyScalar(target / luminance);
    else color.setRGB(target, target, target);
    return color.lerp(
      new THREE.Color().setRGB(target, target, target),
      1 - SETTINGS.hemisphere.chromaticStrength,
    );
  }
  updateHemisphere(immediate = false) {
    if (!this.result || !this.ambient) return;
    const target = {
      sky: this.fillColor("sky"),
      ground: this.fillColor("ground"),
      intensity:
        (this.runtime.hemisphere ?? SETTINGS.hemisphere.intensity) *
        (THREE.MathUtils.lerp(
          SETTINGS.hemisphere.baselineScale,
          1,
          this.dayFactor(),
        ) +
          (SETTINGS.hemisphere.nightScale - SETTINGS.hemisphere.baselineScale) *
            nightFactor(this.result.altitude)),
    };
    if (immediate) {
      this.ambient.color.copy(target.sky);
      this.ambient.groundColor.copy(target.ground);
      this.ambient.intensity = target.intensity;
      this.fillTransition = null;
    } else
      this.fillTransition = {
        started: performance.now(),
        target,
        sky: this.ambient.color.clone(),
        ground: this.ambient.groundColor.clone(),
        intensity: this.ambient.intensity,
      };
  }
  tick(now = performance.now()) {
    if (!this.result || this.disposed) return;
    this.sky.tick(now);
    const transition = this.fillTransition;
    if (!transition) return;
    const t = smoothRange(
      0,
      SETTINGS.hemisphere.transitionMs,
      now - transition.started,
    );
    this.ambient.color.copy(transition.sky).lerp(transition.target.sky, t);
    this.ambient.groundColor
      .copy(transition.ground)
      .lerp(transition.target.ground, t);
    this.ambient.intensity = THREE.MathUtils.lerp(
      transition.intensity,
      transition.target.intensity,
      t,
    );
    if (t === 1) {
      this.fillTransition = null;
      this.notify();
    }
  }
  requestPreset(key) {
    this.lastEnvironmentInput = performance.now();
    if (key !== this.requested) {
      this.requested = key;
      this.error = "";
      this.state = "loading";
    }
    clearTimeout(this.timer);
    clearTimeout(this.preloadTimer);
    if (this.cache.get(key)?.target) this.apply(key);
    else this.timer = setTimeout(() => this.activate(key), SETTINGS.debounceMs);
  }
  async fetchEntry(key) {
    let entry = this.cache.get(key);
    if (entry?.failed && Date.now() - entry.failed > SETTINGS.retryDelayMs) {
      this.cache.delete(key);
      entry = null;
    }
    if (!entry) {
      entry = {};
      this.cache.set(key, entry);
      entry.download = fetch(
        new URL("../../assets/hdri/" + ENVIRONMENTS[key].file, import.meta.url),
        { signal: this.abort.signal },
      )
        .then((response) => {
          if (!response.ok)
            throw new Error("HDRI indisponível (HTTP " + response.status + ")");
          return response.arrayBuffer();
        })
        .then((buffer) => {
          entry.buffer = buffer;
          return entry;
        })
        .catch((error) => {
          entry.failed = Date.now();
          throw error;
        });
    }
    await entry.download;
    return entry;
  }
  async activate(key) {
    if (this.disposed || this.requested !== key) return;
    try {
      const entry = await this.fetchEntry(key);
      if (
        this.disposed ||
        this.requested !== key ||
        performance.now() - this.lastEnvironmentInput < SETTINGS.debounceMs
      )
        return;
      if (entry.failed && Date.now() - entry.failed < SETTINGS.retryDelayMs)
        throw new Error("Falha ao processar HDRI; nova tentativa em instantes");
      if (!entry.target) {
        if (!entry.decoding) {
          entry.decoding = this.decoder.decode(
            entry.buffer,
            SETTINGS.maxIBLLuminance,
            SETTINGS.sky,
          );
          entry.buffer = null;
        }
        const data = await entry.decoding;
        if (
          this.disposed ||
          this.requested !== key ||
          performance.now() - this.lastEnvironmentInput < SETTINGS.debounceMs
        )
          return;
        if (!entry.target) {
          entry.texture = new THREE.DataTexture(
            data.data,
            data.width,
            data.height,
            data.format,
            data.type,
          );
          entry.texture.colorSpace = data.colorSpace;
          entry.texture.flipY = false;
          entry.texture.minFilter = entry.texture.magFilter =
            THREE.LinearFilter;
          entry.texture.needsUpdate = true;
          try {
            entry.target = this.generator.fromEquirectangular(entry.texture);
          } catch (error) {
            entry.texture.dispose();
            entry.texture = null;
            throw error;
          }
          entry.target.texture.name =
            ENVIRONMENTS[key].file + " (somente especular)";
          entry.decoding = null;
        }
      }
      this.apply(key);
    } catch (error) {
      if (this.disposed || this.requested !== key) return;
      const entry = this.cache.get(key);
      if (entry) entry.failed = Date.now();
      this.state = "error";
      this.error = error.message;
      this.notify();
    }
  }
  apply(key) {
    if (this.disposed || this.requested !== key) return;
    const entry = this.cache.get(key);
    this.current = key;
    this.state = "ready";
    this.sky.setEnvironment(entry.texture, ENVIRONMENTS[key]);
    this.reflections.setRotation(ENVIRONMENTS[key].rotation);
    this.reflections.set(entry.target.texture, this.reflectionScale);
    this.notify();
    const next =
      this.override === "auto" ? adjacentEnvironment(this.result) : null;
    if (next && !this.cache.has(next))
      this.preloadTimer = setTimeout(
        () => this.fetchEntry(next).catch(() => {}),
        SETTINGS.preloadDelayMs,
      );
  }
  dispose() {
    if (this.disposed) return;
    this.disposed = true;
    clearTimeout(this.timer);
    clearTimeout(this.preloadTimer);
    this.abort.abort();
    this.decoder.dispose();
    this.probe.dispose();
    this.reflections.clear();
    this.listeners.clear();
    this.scene.environment = null;
    for (const entry of this.cache.values()) {
      entry.target?.dispose();
      entry.texture?.dispose();
    }
    this.cache.clear();
    this.generator.dispose();
    this.sky.dispose();
    this.atmosphere.dispose();
    this.fallback.dispose();
  }
}
