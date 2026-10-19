import * as THREE from "three";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";
import {
  solarConfigError,
  solarAtInstant,
} from "../MapaPavimento/predio-3d/solar-position.mjs?v=2";
import { createTechnicalStage, createSolarIndicator } from "./obra-3d-stage.js";
import { createModelLoader } from "../MapaPavimento/predio-3d/model-loader.js";
import { setupEnvironment } from "../MapaPavimento/predio-3d/viewer-environment.js";
import { northDirection } from "../MapaPavimento/predio-3d/north-direction.js";
import { visibleModelBounds } from "../MapaPavimento/predio-3d/floor-visibility.js";
import { createViewerGlass } from "../MapaPavimento/predio-3d/viewer-glass.js";
import { solarOpaqueMaterial } from "../MapaPavimento/predio-3d/solar-ray-tracer.js?v=20261016-alpha-glass";
import { fitSolarShadow } from "../MapaPavimento/predio-3d/solar-shadow.js";

function disposeModel(root) {
  const geometries = new Set(),
    materials = new Set(),
    textures = new Set();
  root?.traverse((object) => {
    if (object.geometry) geometries.add(object.geometry);
    for (const material of Array.isArray(object.material)
      ? object.material
      : [object.material]) {
      if (!material) continue;
      materials.add(material);
      Object.values(material).forEach((value) => {
        if (value?.isTexture) textures.add(value);
      });
    }
  });
  textures.forEach((texture) => {
    texture.source?.data?.close?.();
    texture.dispose();
  });
  materials.forEach((material) => material.dispose());
  geometries.forEach((geometry) => geometry.dispose());
}

export class Obra3DViewer {
  constructor(layer) {
    this.layer = layer;
    this.host = layer.querySelector(".obra-3d-canvas-wrap");
    this.renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    this.renderer.setPixelRatio(Math.min(devicePixelRatio || 1, 2));
    this.renderer.outputColorSpace = THREE.SRGBColorSpace;
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
    this.renderer.toneMappingExposure = 1;
    this.renderer.shadowMap.enabled = true;
    this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    this.renderer.domElement.className = "obra-3d-gl";
    this.renderer.domElement.setAttribute(
      "aria-label",
      "Modelo 3D da obra. Arraste para girar; use a roda para ampliar e o botão direito para mover.",
    );
    this.renderer.domElement.tabIndex = 0;
    this.host.append(this.renderer.domElement);
    this.scene = new THREE.Scene();
    this.environment = setupEnvironment(this.renderer, this.scene);
    this.camera = new THREE.OrthographicCamera(-10, 10, 10, -10, 0.01, 100000);
    this.controls = new OrbitControls(this.camera, this.renderer.domElement);
    this.controls.listenToKeyEvents(this.renderer.domElement);
    this.controls.enableDamping = true;
    this.controls.maxPolarAngle = Math.PI / 2 - 0.01;
    this.controls.zoomToCursor = false;
    this.controls.minZoom = 0.2;
    this.controls.maxZoom = 5;
    this.ambient = new THREE.HemisphereLight(0xe9f1ff, 0x7b827b, 1.65);
    this.light = new THREE.DirectionalLight(0xfff6e8, 2.25);
    this.light.castShadow = true;
    this.light.shadow.mapSize.set(2048, 2048);
    this.light.shadow.bias = -0.0001;
    this.light.shadow.radius = 2;
    // O modelo e o Sol são estáticos: órbita e scroll não recalculam sombras.
    this.light.shadow.autoUpdate = false;
    this.light.position.set(1, 3, 2);
    this.scene.add(this.ambient, this.light, this.light.target);
    this.sun = createSolarIndicator();
    this.scene.add(this.sun);
    this.layer.dataset.environment = "procedural";
    this.loader = createModelLoader();
    this.abort = new AbortController();
    const options = { signal: this.abort.signal };
    this.invalidate = this.invalidate.bind(this);
    this.controls.addEventListener("change", this.invalidate);
    document.addEventListener(
      "visibilitychange",
      () => {
        if (document.hidden) {
          cancelAnimationFrame(this.frame);
          this.frame = null;
        } else this.invalidate();
      },
      options,
    );
    layer
      .querySelector('[data-action="fit"]')
      .addEventListener("click", () => this.fit(true), options);
    layer
      .querySelector('[data-action="in"]')
      .addEventListener("click", () => this.zoom(0.8), options);
    layer
      .querySelector('[data-action="out"]')
      .addEventListener("click", () => this.zoom(1.25), options);
    this.renderer.domElement.addEventListener(
      "pointerdown",
      () => this.stopMotion(),
      options,
    );
    this.renderer.domElement.addEventListener(
      "wheel",
      () => this.stopMotion(),
      { ...options, passive: true },
    );
    this.resizeObserver = new ResizeObserver(() => this.resize());
    this.resizeObserver.observe(this.host);
    this.setNorth(null);
    this.resize();
  }

  setSpatialConfiguration(config, openedAt) {
    if (this.destroyed) return;
    this.setNorth(config?.norte_graus ?? null);
    const error = solarConfigError(config);
    this.layer.dataset.solarState = error ? "unavailable" : "ready";
    if (error || !this.bounds) return;
    // Instantaneo: o relogio nao avanca e nao ha controles de simulacao.
    const result = solarAtInstant(config, openedAt);
    const sphere = this.bounds.getBoundingSphere(new THREE.Sphere());
    const direction = new THREE.Vector3(
      result.direction.x,
      result.direction.y,
      result.direction.z,
    );
    this.light.color.set(0xffe4b3);
    this.light.position
      .copy(sphere.center)
      .addScaledVector(direction, sphere.radius * 8);
    this.light.target.position.copy(sphere.center);
    this.light.intensity = result.intensity;
    this.ambient.intensity = 0.45;
    fitSolarShadow(this.light, this.bounds);
    this.sun.position
      .copy(sphere.center)
      .addScaledVector(direction, sphere.radius);
    this.sun.scale.setScalar(sphere.radius * 0.045);
    this.sunAboveHorizon = result.altitude > 0;
    this.sun.visible = this.sunAboveHorizon && !this.minimized;
    Object.assign(this.layer.dataset, {
      solarInstant: result.utcISO,
      solarAzimuth: String(result.azimuth),
      solarAltitude: String(result.altitude),
      solarState: this.sunAboveHorizon ? "day" : "night",
    });
    this.invalidate();
  }

  async load(src) {
    const gltf = await this.loader.loader.loadAsync(src);
    if (this.destroyed) {
      disposeModel(gltf.scene);
      return;
    }
    this.model = gltf.scene;
    const adjustedGlass = new Map();
    // A apresentação destaca a arquitetura. O entorno continua no viewer completo.
    this.model.traverse((object) => {
      if (/^(terreno|terrain|ground|entorno)(?:_|\b)/i.test(object.name))
        object.visible = false;
      if (!object.isMesh) return;
      const materials = Array.isArray(object.material)
        ? object.material
        : [object.material];
      const viewerMaterials = materials.map((material) => {
        if (
          !material?.isMeshPhysicalMaterial ||
          !/(vidro|glass)/i.test(material.name || "")
        )
          return material;
        if (!adjustedGlass.has(material))
          adjustedGlass.set(material, createViewerGlass(material));
        return adjustedGlass.get(material);
      });
      object.material = Array.isArray(object.material)
        ? viewerMaterials
        : viewerMaterials[0];
      object.castShadow = viewerMaterials.some(solarOpaqueMaterial);
      object.receiveShadow = object.castShadow;
    });
    this.originalGlass = [...adjustedGlass.keys()];
    this.scene.add(this.model);
    this.bounds = visibleModelBounds(this.model);
    if (this.bounds.isEmpty())
      throw new Error("O GLB não contém geometria visível.");
    // Fora do modelo/bounds: decoração não afeta framing, sombras ou minimap.
    this.stage = createTechnicalStage(this.bounds);
    this.stage.visible = !this.minimized;
    this.scene.add(this.stage);
    const sphere = this.bounds.getBoundingSphere(new THREE.Sphere());
    this.light.position
      .copy(sphere.center)
      .addScaledVector(
        new THREE.Vector3(1, 3, 2).normalize(),
        sphere.radius * 8,
      );
    this.light.target.position.copy(sphere.center);
    fitSolarShadow(this.light, this.bounds);
    this.fit(false);
    this.invalidate();
  }

  resize() {
    if (this.destroyed) return;
    const width = this.host.clientWidth,
      height = this.host.clientHeight;
    if (!width || !height) return;
    if (this.width === width && this.height === height) return;
    this.width = width;
    this.height = height;
    this.renderer.setSize(width, height, false);
    if (this.model) this.fit(false);
    this.invalidate();
  }

  fit(animate = false) {
    if (!this.model) return;
    // Mesma projeção e margem da vista completa, sem trajetória solar/terreno.
    const sphere = this.bounds.getBoundingSphere(new THREE.Sphere());
    const aspect = Math.max(this.width / Math.max(this.height, 1), 0.1);
    const height = Math.max(
      (this.bounds.getSize(new THREE.Vector3()).length() * 1.3) /
        Math.min(1, aspect),
      0.1,
    );
    this.camera.left = (-height * aspect) / 2;
    this.camera.right = (height * aspect) / 2;
    this.camera.top = height / 2;
    this.camera.bottom = -height / 2;
    this.camera.updateProjectionMatrix();
    // Azimute da vista completa, preservando a inclinação mais baixa do hero.
    const offset = new THREE.Vector3(1.15, 0.425, 1.25).multiplyScalar(height);
    this.move(sphere.center.clone().add(offset), sphere.center, animate);
    this.setZoom(1.35, animate);
  }

  stopMotion() {
    window.gsap.killTweensOf([
      this.camera,
      this.camera.position,
      this.controls.target,
    ]);
    // Consome o resíduo do damping antes de uma nova ação explícita da câmera.
    this.controls.enableDamping = false;
    this.controls.update();
    this.controls.enableDamping = true;
  }

  move(position, target, animate) {
    this.stopMotion();
    if (!animate || matchMedia("(prefers-reduced-motion: reduce)").matches) {
      this.controls.target.copy(target);
      this.camera.position.copy(position);
      this.controls.update();
      this.invalidate();
      return;
    }
    const vars = {
      duration: 0.45,
      ease: "power2.out",
      onUpdate: this.invalidate,
      overwrite: true,
    };
    window.gsap.to(this.controls.target, {
      ...vars,
      x: target.x,
      y: target.y,
      z: target.z,
    });
    window.gsap.to(this.camera.position, {
      ...vars,
      x: position.x,
      y: position.y,
      z: position.z,
    });
  }

  zoom(factor) {
    this.stopMotion();
    this.setZoom(
      THREE.MathUtils.clamp(
        this.camera.zoom / factor,
        this.controls.minZoom,
        this.controls.maxZoom,
      ),
      true,
    );
  }

  setZoom(zoom, animate) {
    const update = () => {
      this.camera.updateProjectionMatrix();
      this.controls.dispatchEvent({ type: "change" });
      this.invalidate();
    };
    if (!animate || matchMedia("(prefers-reduced-motion: reduce)").matches) {
      this.camera.zoom = zoom;
      update();
      return;
    }
    window.gsap.to(this.camera, {
      zoom,
      duration: 0.45,
      ease: "power2.out",
      onUpdate: update,
      overwrite: "auto",
    });
  }

  setNorth(degrees) {
    this.north = northDirection(degrees == null ? 0 : Number(degrees));
    const compass = this.layer.querySelector(".obra-3d-north");
    const label =
      degrees == null
        ? "Orientação do modelo: −Z. Norte geográfico não cadastrado."
        : `Norte do empreendimento: ${Number(degrees).toLocaleString("pt-BR")} graus.`;
    compass.title = label;
    compass.setAttribute("aria-label", label);
    this.invalidate();
  }

  updateNorth() {
    if (!this.north || this.minimized) return;
    // Mesma projeção horizontal utilizada por NorthIndicator no viewer completo.
    const up = new THREE.Vector3(0, 1, 0).applyQuaternion(
      this.camera.quaternion,
    );
    up.y = 0;
    if (up.lengthSq() < 1e-10) {
      this.camera.getWorldDirection(up);
      up.y = 0;
    }
    up.normalize();
    const right = new THREE.Vector3(-up.z, 0, up.x);
    const north = new THREE.Vector3(this.north.x, 0, this.north.z);
    const heading = Math.atan2(north.dot(right), north.dot(up));
    // O glifo location-arrow aponta a 45°; a correção mantém 0° para cima.
    this.layer.querySelector(".obra-3d-north i").style.transform =
      `rotate(${heading - Math.PI / 4}rad)`;
  }

  setMinimized(value) {
    if (value === this.minimized) return;
    this.minimized = value;
    this.controls.enabled = !value;
    this.sun.visible = !!this.sunAboveHorizon && !value;
    if (this.stage) this.stage.visible = !value;
    this.renderer.domElement.tabIndex = value ? -1 : 0;
    this.renderer.domElement.setAttribute("aria-hidden", String(value));
    this.invalidate();
  }

  screenBounds() {
    if (!this.bounds) return null;
    this.camera.updateMatrixWorld();
    const projected = new THREE.Box2();
    for (const x of [this.bounds.min.x, this.bounds.max.x]) {
      for (const y of [this.bounds.min.y, this.bounds.max.y]) {
        for (const z of [this.bounds.min.z, this.bounds.max.z]) {
          const point = new THREE.Vector3(x, y, z).project(this.camera);
          projected.expandByPoint(
            new THREE.Vector2(
              ((point.x + 1) * this.host.clientWidth) / 2,
              ((1 - point.y) * this.host.clientHeight) / 2,
            ),
          );
        }
      }
    }
    // O canvas já recorta a vista ao fazer zoom/pan; o minimap mantém essa vista.
    return {
      left: Math.max(0, projected.min.x - 8),
      right: Math.min(this.host.clientWidth, projected.max.x + 8),
      top: Math.max(0, projected.min.y - 8),
      bottom: Math.min(this.host.clientHeight, projected.max.y + 8),
    };
  }

  invalidate() {
    if (this.destroyed || document.hidden || this.frame != null) return;
    this.frame = requestAnimationFrame(() => {
      this.frame = null;
      // change agenda o próximo frame somente enquanto o damping ainda muda a câmera.
      this.controls.update();
      this.updateNorth();
      // Mantém a leitura do ícone, mas sua posição continua sendo o vetor solar real.
      this.sun.quaternion.copy(this.camera.quaternion);
      this.renderer.render(this.scene, this.camera);
    });
  }

  destroy() {
    this.destroyed = true;
    cancelAnimationFrame(this.frame);
    this.stopMotion();
    this.abort.abort();
    this.resizeObserver.disconnect();
    this.controls.removeEventListener("change", this.invalidate);
    this.controls.dispose();
    this.loader.dispose();
    disposeModel(this.model);
    this.originalGlass?.forEach((material) => material.dispose());
    this.light.shadow.dispose();
    this.scene.environment = null;
    disposeModel(this.sun);
    disposeModel(this.stage);
    this.environment?.dispose();
    this.renderer.dispose();
    this.renderer.domElement.remove();
  }
}
