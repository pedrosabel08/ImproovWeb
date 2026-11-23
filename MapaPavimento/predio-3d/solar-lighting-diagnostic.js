import { MeshStandardMaterial } from "three";

const MODES = {
  A: "Solar da obra",
  B: "Preenchimento convencional",
  C: "Preenchimento convencional + ambiente",
};
const WARMUP_MS = 1000;
const SAMPLE_MS = 2000;

export class SolarLightingDiagnostic {
  constructor({ renderer, scene, solar }) {
    Object.assign(this, { renderer, scene, solar });
    this.navigationFill = {
      sky: solar.ambient.color.clone(),
      ground: solar.ambient.groundColor.clone(),
      intensity: solar.ambient.intensity,
    };
    this.grayMaterials = new Map();
    this.downloads = [];
    this.panel = document.createElement("details");
    this.panel.className = "solar-technical";
    this.panel.innerHTML = `
      <summary>Comparação de iluminação A/B/C</summary>
      <p class="solar-meta" data-reference hidden>Referência local · São Paulo, Norte 0°</p>
      <label class="solar-environment-control">Iluminação
        <select>${Object.entries(MODES)
          .map(
            ([key, label]) =>
              `<option value="${key}">${key} — ${label}</option>`,
          )
          .join("")}</select>
      </label>
      <label class="solar-horizon-debug"><input type="checkbox"> Cinza-claro nas superfícies opacas</label>
      <button type="button" class="btn">Registrar A/B/C</button>
      <p class="solar-meta" role="status">Enquadre a fachada e mantenha a vista e o horário durante o registro.</p>
      <div data-results></div>`;
    this.mode = this.panel.querySelector("select");
    this.gray = this.panel.querySelector("input");
    this.button = this.panel.querySelector("button");
    this.status = this.panel.querySelector('[role="status"]');
    this.results = this.panel.querySelector("[data-results]");
    this.button.disabled = true;
    solar.body.append(this.panel);
    this.button.addEventListener("click", () => {
      if (this.run) {
        this.finish("Registro cancelado.");
        return;
      }
      this.run = {
        index: 0,
        samples: [],
        frames: [],
        gray: this.gray.checked,
        requestedAt: performance.now(),
      };
      this.mode.disabled = this.gray.disabled = true;
      this.button.textContent = "Cancelar registro";
      this.status.textContent =
        "Aguardando estabilização da vista e do ambiente…";
    });
    window.addEventListener("pagehide", (event) => {
      if (event.persisted) return;
      for (const material of this.grayMaterials.values()) material.dispose();
      for (const url of this.downloads) URL.revokeObjectURL(url);
    });
  }

  showReferenceLocation() {
    this.panel.querySelector("[data-reference]").hidden = false;
  }

  finish(message) {
    this.run = null;
    this.mode.disabled = this.gray.disabled = false;
    this.button.textContent = "Registrar A/B/C";
    this.status.textContent = message;
  }

  reset() {
    if (this.run)
      this.finish("Registro interrompido. Volte à vista 3D com o Sol ativo.");
    this.mode.value = "A";
    this.gray.checked = false;
    this.button.disabled = true;
  }

  // O material auxiliar mantém a orientação das faces também no passe de sombras.
  grayMaterial(original) {
    if (
      !original?.visible ||
      original.transparent ||
      original.opacity !== 1 ||
      original.alphaTest ||
      original.alphaHash ||
      original.transmission ||
      original.userData?.viewerGlass
    )
      return original;
    const { side, shadowSide, flatShading = false } = original;
    const key = JSON.stringify([side, shadowSide, flatShading]);
    if (!this.grayMaterials.has(key)) {
      this.grayMaterials.set(
        key,
        new MeshStandardMaterial({
          color: 0xc8c8c8,
          roughness: 0.8,
          metalness: 0,
          side,
          shadowSide,
          flatShading,
        }),
      );
    }
    return this.grayMaterials.get(key);
  }

  render(camera, model, source, { now, busy }) {
    const { renderer, scene, solar } = this;
    const { environment, ambient, light, result } = solar;
    if (!model || !result) {
      if (
        !this.button.disabled ||
        this.run ||
        this.mode.value !== "A" ||
        this.gray.checked
      )
        this.reset();
      renderer.render(scene, camera);
      return;
    }
    this.button.disabled = !this.run && busy;
    if (busy && this.run)
      this.finish("Registro interrompido pela mudança de vista.");
    const run = this.run;
    const probe = environment.probe.probe;
    const stable =
      environment.state !== "loading" &&
      !environment.fillTransition &&
      !environment.nightGlass.transition &&
      !environment.probe.inFlight &&
      environment.probe.state !== "scheduled" &&
      now -
        Math.max(
          environment.lastEnvironmentInput,
          environment.lastSolarInput,
        ) >=
        800;
    if (run && now - run.requestedAt > 20000 && !run.reference) {
      this.finish(
        "O ambiente não estabilizou em 20 s. Tente registrar novamente.",
      );
    }
    if (this.run && (stable || run.reference)) {
      const reference = this.reference(camera, model, source);
      // A rodada é descartada se a câmera, o Sol, o GLB ou o renderer mudar.
      const signature = JSON.stringify(reference, (_, value) =>
        typeof value === "number" ? Math.round(value * 1e9) / 1e9 : value,
      );
      if (
        !stable ||
        document.hidden ||
        (run.signature && run.signature !== signature)
      ) {
        this.finish(
          "Registro interrompido. Mantenha a vista, o horário e os ajustes durante a comparação.",
        );
      } else if (!run.reference) {
        Object.assign(run, { reference, signature, startedAt: now });
        this.status.textContent = `Registrando A — ${MODES.A}…`;
      }
    }
    const recording = this.run?.reference ? this.run : null;
    const mode = recording
      ? Object.keys(MODES)[recording.index]
      : this.mode.value;
    const gray = recording ? recording.gray : this.gray.checked;
    const replaceFill = mode !== "A";
    const originalFill = replaceFill
      ? {
          environment: scene.environment,
          environmentIntensity: scene.environmentIntensity,
          sky: ambient.color.clone(),
          ground: ambient.groundColor.clone(),
          intensity: ambient.intensity,
          probeVisible: probe.visible,
        }
      : null;
    const materials = gray ? new Map() : null;
    try {
      if (replaceFill) {
        probe.visible = false;
        ambient.color.copy(this.navigationFill.sky);
        ambient.groundColor.copy(this.navigationFill.ground);
        ambient.intensity = this.navigationFill.intensity;
        scene.environment = mode === "C" ? environment.fallbackTexture : null;
        scene.environmentIntensity = mode === "C" ? 1 : 0;
      }
      if (gray)
        model.traverseVisible((object) => {
          if (!object.isMesh) return;
          materials.set(object, object.material);
          object.material = Array.isArray(object.material)
            ? object.material.map((material) => this.grayMaterial(material))
            : this.grayMaterial(object.material);
        });
      renderer.render(scene, camera);
      if (
        recording &&
        !recording.saving &&
        now - recording.startedAt >= WARMUP_MS
      ) {
        recording.frames.push(now);
        const duration = now - recording.frames[0];
        if (duration >= SAMPLE_MS) {
          const sample = {
            mode,
            gray,
            durationMs: duration,
            frameIntervals: recording.frames.length - 1,
            fps: ((recording.frames.length - 1) * 1000) / duration,
            drawCalls: renderer.info.render.calls,
            triangles: renderer.info.render.triangles,
            environment: scene.environment ? "procedural de navegação" : null,
            environmentIntensity: scene.environmentIntensity,
            shadowMapAllocated: Boolean(light.shadow.map),
            probeIntensity:
              probe.visible && probe.layers.test(camera.layers)
                ? probe.intensity
                : 0,
            ...this.inspectFrame(camera, model),
          };
          recording.saving = true;
          renderer.domElement.toBlob((png) => {
            if (this.run !== recording) return;
            if (!png) {
              this.finish("O navegador não conseguiu exportar a captura.");
              return;
            }
            recording.samples.push({ sample, png });
            if (recording.samples.length === Object.keys(MODES).length) {
              this.publish(recording);
              return;
            }
            recording.index++;
            recording.startedAt = performance.now();
            recording.frames = [];
            recording.saving = false;
            const next = Object.keys(MODES)[recording.index];
            this.status.textContent = `Registrando ${next} — ${MODES[next]}…`;
          }, "image/png");
        }
      }
    } finally {
      if (materials) {
        for (const [object, material] of materials) object.material = material;
      }
      if (originalFill) {
        scene.environment = originalFill.environment;
        scene.environmentIntensity = originalFill.environmentIntensity;
        ambient.color.copy(originalFill.sky);
        ambient.groundColor.copy(originalFill.ground);
        ambient.intensity = originalFill.intensity;
        probe.visible = originalFill.probeVisible;
      }
    }
  }

  reference(camera, model, source) {
    const { renderer, solar, scene } = this;
    const { environment, light, result, config } = solar;
    const { shadow } = light;
    return {
      model: source,
      modelId: model.uuid,
      camera: {
        type: camera.type,
        position: camera.position.toArray(),
        orientation: camera.quaternion.toArray(),
        projection: camera.projectionMatrix.toArray(),
        layers: camera.layers.mask,
      },
      solar: {
        localDate: solar.date.value,
        localTime: solar.time.value,
        timezone: config.timezone,
        latitude: config.latitude,
        longitude: config.longitude,
        north: config.norte_graus,
        azimuth: result.azimuth,
        altitude: result.altitude,
        direction: result.direction,
        position: light.position.toArray(),
        target: light.target.position.toArray(),
        color: light.color.getHexString(),
        intensity: light.intensity,
      },
      shadows: {
        enabled: renderer.shadowMap.enabled,
        type: renderer.shadowMap.type,
        castShadow: light.castShadow,
        mapSize: shadow.mapSize.toArray(),
        bias: shadow.bias,
        normalBias: shadow.normalBias,
        radius: shadow.radius,
        intensity: shadow.intensity,
        projection: shadow.camera.projectionMatrix.toArray(),
      },
      renderer: {
        toneMapping: renderer.toneMapping,
        exposure: renderer.toneMappingExposure,
        outputColorSpace: renderer.outputColorSpace,
        pixelRatio: renderer.getPixelRatio(),
        resolution: [renderer.domElement.width, renderer.domElement.height],
      },
      inputs: {
        hdri: environment.current,
        hdriState: environment.state,
        hdriError: environment.error,
        reflectionScale: environment.reflectionScale,
        hdriOverride: environment.override,
        probe: environment.probe.diagnostics(),
        tuning: { ...environment.runtime },
        hemisphereDayIntensity: environment.hemisphereDayIntensity,
        solarUpdated: environment.lastSolarInput,
        background: scene.background?.uuid ?? null,
      },
    };
  }

  inspectFrame(camera, model) {
    const lights = [];
    this.scene.traverseVisible((object) => {
      if (!object.isLight || !object.layers.test(camera.layers)) return;
      lights.push({
        type: object.isLightProbe ? "LightProbe" : object.type,
        name: object.name,
        intensity: object.intensity,
        color: object.color.getHexString(),
        ground: object.groundColor?.getHexString(),
      });
    });
    const materials = new Set();
    model.traverseVisible((object) => {
      if (!object.isMesh || !object.layers.test(camera.layers)) return;
      for (const material of [].concat(object.material)) {
        if (material?.visible) materials.add(material);
      }
    });
    const standard = [...materials].filter(
      (material) => material.isMeshStandardMaterial,
    );
    const explicit = standard.filter((material) => material.envMap);
    const hdri = this.solar.environment.reflections.texture;
    return {
      lights,
      materials: {
        visible: materials.size,
        standard: standard.length,
        unlit: [...materials].filter((material) => material.isMeshBasicMaterial)
          .length,
        explicitEnvMap: explicit.length,
        selectiveHDRI: explicit.filter((material) => material.envMap === hdri)
          .length,
        sceneEnvironment: this.scene.environment
          ? standard.length - explicit.length
          : 0,
      },
    };
  }

  publish(run) {
    for (const url of this.downloads) URL.revokeObjectURL(url);
    this.downloads = [];
    this.results.replaceChildren();
    const suffix = run.gray ? "cinza" : "glb";
    const addLink = (blob, filename, label) => {
      const link = document.createElement("a");
      const url = URL.createObjectURL(blob);
      this.downloads.push(url);
      link.href = url;
      link.download = filename;
      link.textContent = label;
      const row = document.createElement("p");
      row.className = "solar-meta";
      row.append(link);
      this.results.append(row);
    };
    for (const { sample, png } of run.samples) {
      addLink(
        png,
        `iluminacao-${sample.mode}-${suffix}.png`,
        `${sample.mode} · ${sample.fps.toFixed(1)} FPS · baixar PNG`,
      );
    }
    const report = {
      comparison: run.reference,
      samples: run.samples.map(({ sample }) => sample),
    };
    addLink(
      new Blob([JSON.stringify(report, null, 2)], { type: "application/json" }),
      `iluminacao-${suffix}.json`,
      "Baixar medições e condições do teste",
    );
    this.finish("Comparação concluída. Baixe as três capturas e o registro.");
  }
}
