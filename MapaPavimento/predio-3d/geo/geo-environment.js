import * as THREE from "three";
import { SOLAR_VISUAL_RADIUS_MULTIPLIER } from "../solar-visuals.js?v=20261103-solar-orbit-v1";
import {
  validGeoConfig,
  geoProjector,
} from "./geo-coordinates.js?v=20261103-boundary-v4";
import {
  loadOSM,
  browserOSMCache,
  OSM_RADIUS,
} from "./osm-service.js?v=20261103-osm-cache-v3";
import {
  GeometryBatches,
  polygonGeometry,
} from "./feature-builder.js?v=20261103-boundary-v4";
import { buildBuilding } from "./building-builder.js?v=20261103-boundary-v4";
import { buildRoad } from "./road-builder.js?v=20261103-boundary-v4";
import { buildWater } from "./water-builder.js?v=20261103-boundary-v4";
import { buildVegetation } from "./vegetation-builder.js?v=20261103-boundary-v4";
import { urbanMaterial } from "./urban-haze.js?v=20261103-boundary-v4";
import {
  GEO_OPTIONS,
  GEO_COLORS,
  SURFACE_Y,
} from "./environment-config.js?v=20261103-boundary-v4";
import {
  createEnvironmentBounds,
  polygonArea,
} from "./environment-clip.js?v=20261103-boundary-v4";
import { buildCoastalAreas } from "./coastline-builder.js?v=20261103-boundary-v4";
import { prepareEnvironment } from "./prepare-environment.js?v=20261103-boundary-v4";
import { GeoDebug } from "./geo-debug.js?v=20261103-boundary-v4";

/** New group outside modelRoot: never enters picking, fit, floor or solar bounds. */
export class GeoEnvironment {
  constructor({
    scene,
    viewport,
    sky,
    onChange = () => {},
    options = GEO_OPTIONS,
  }) {
    Object.assign(this, { scene, viewport, sky, onChange });
    this.options = options;
    let storage;
    try {
      storage = window.localStorage;
    } catch {
      /* private mode */
    }
    this.cache = browserOSMCache(storage);
    this.generation = 0;
    this.attribution = document.createElement("a");
    this.attribution.href = "https://www.openstreetmap.org/copyright";
    this.attribution.target = "_blank";
    this.attribution.rel = "noopener noreferrer";
    this.attribution.textContent = "© OpenStreetMap contributors";
    this.attribution.className = "geo-attribution";
    this.attribution.hidden = true;
    this.attribution.style.cssText =
      "position:absolute;right:10px;bottom:44px;z-index:2;font:10px/1.4 sans-serif;color:var(--spatial-text-muted,#aab4b7);background:var(--spatial-panel,#171b22);padding:3px 5px;border-radius:3px;max-width:calc(100% - 20px)";
    viewport.append(this.attribution);
    this.notice = document.createElement("div");
    this.notice.setAttribute("role", "status");
    this.notice.style.cssText = "position:absolute;left:12px;bottom:48px;z-index:2;max-width:min(380px,calc(100% - 24px));padding:8px 10px;border-radius:6px;background:var(--spatial-panel,#171b22);color:var(--spatial-text,#e4e9ec);font:12px/1.4 sans-serif";
    this.notice.hidden = true;
    viewport.append(this.notice);
  }
  setConfig(config) {
    const key = validGeoConfig(config)
      ? JSON.stringify([config.latitude, config.longitude, config.norte_graus, config.obra_id])
      : null;
    if (key === this.configKey) return;
    this.configKey = key;
    this.config = key ? { ...config } : null;
    return this.refresh();
  }
  setModel(root, bounds, buildingBounds = bounds, groundInfo = null) {
    this.model = root;
    this.groundInfo = groundInfo;
    this.bounds = bounds?.clone();
    this.buildingBounds = buildingBounds?.clone();
    return this.refresh();
  }
  clear() {
    ++this.generation;
    this.abort?.abort();
    this.abort = null;
    this.batches?.dispose();
    this.debug?.dispose();
    this.debug = null;
    this.batches = null;
    if (this.group) {
      this.scene.remove(this.group);
      this.group.traverse((object) => object.geometry?.dispose());
    }
    this.materials && Object.values(this.materials).forEach((m) => m.dispose());
    const hadGroup = !!this.group;
    this.materials = this.group = null;
    if (hadGroup) this.onChange();
    this.attribution.hidden = true;
    delete this.viewport.dataset.geoMetrics;
    this.viewport.dataset.geoState = "inactive";
  }
  async refresh() {
    this.clear();
    this.notice.hidden = true;
    if (this.model && this.bounds && !this.config) {
      this.notice.textContent = "Entorno indisponível. Configure a localização e o Norte da obra.";
      this.notice.hidden = false;
    }
    if (
      this.disposed ||
      !this.model ||
      !this.bounds ||
      this.bounds.isEmpty() ||
      !this.config
    )
      return;
    const generation = this.generation;
    this.abort = new AbortController();
    this.viewport.dataset.geoState = "loading";
    try {
      const response = await loadOSM(this.config, {
        cache: this.cache,
        signal: this.abort.signal,
      });
      if (generation !== this.generation) return;
      if (response.stale) {
        this.notice.textContent = "O entorno exibido está desatualizado; não foi possível consultar dados mais recentes.";
        this.notice.hidden = false;
      }
      if (!Object.values(response.data).some((items) => items.length)) {
        this.viewport.dataset.geoState = "empty";
        this.notice.textContent = "Nenhum dado de entorno foi encontrado para esta localização.";
        this.notice.hidden = false;
        return;
      }
      await this.build(response.data, generation, response);
    } catch (error) {
      if (generation !== this.generation) return;
      this.clear();
      this.viewport.dataset.geoState = "error";
      this.notice.textContent = "Não foi possível carregar o entorno OpenStreetMap. Tente novamente mais tarde.";
      this.notice.hidden = false;
      console.debug(
        "[Flow OSM] Entorno indisponível; visualizador preservado.",
        error.message,
      );
    }
  }
  async build(data, generation, response) {
    const start = performance.now(),
      radius = OSM_RADIUS;
    const anchor = this.buildingBounds.getCenter(new THREE.Vector3());
    const groundY = this.groundInfo?.elevation ?? this.bounds.min.y;
    const environmentBounds = createEnvironmentBounds(
        radius,
        this.config.norte_graus,
      ),
      project = geoProjector(this.config);
    let coastal;
    try {
      coastal = buildCoastalAreas(
        data.coastlines || [],
        project,
        environmentBounds,
      );
    } catch (error) {
      coastal = {
        sea: [],
        land: [environmentBounds.polygon],
        coastLines: [],
        state: "processing-error",
      };
      console.debug(
        "[GeoEnvironment] Costa não classificada; preservando terra.",
        error.message,
      );
    }
    if (this.options.geoDebug) this.debug = new GeoDebug(environmentBounds);
    const prepared = prepareEnvironment(
      data,
      project,
      environmentBounds,
      coastal,
      this.debug,
    );
    this.group = new THREE.Group();
    this.group.name = "Entorno urbano OpenStreetMap";
    this.group.position.set(anchor.x, groundY, anchor.z);
    this.group.userData.geoOrigin = {
      latitude: this.config.latitude,
      longitude: this.config.longitude,
    };
    this.materials = Object.fromEntries(
      Object.entries(GEO_COLORS).map(([key, color]) => [
        key,
        urbanMaterial(color, {
          sky: this.sky,
          radius,
          ground: key === "ground",
        }),
      ]),
    );
    this.batches = new GeometryBatches(
      this.group,
      this.materials,
      environmentBounds,
    );
    const metrics = {
      buildings: 0,
      roads: 0,
      polygons: 0,
      water: 0,
      vegetation: 0,
      excludedBuildings: 0,
      sourceBuildings: data.buildings.length,
      cacheHit: response.cacheHit,
      acquisitionSource: response.acquisitionSource,
      serverCacheHit: response.serverCacheHit,
      stale: response.stale,
      processingMs: response.processingMs,
      acquisitionMs: response.acquisitionMs,
      seaAreaM2: Math.round(
        coastal.sea.reduce((s, p) => s + polygonArea(p), 0),
      ),
      coastline: coastal.state,
      environmentSizeM: radius * 2,
    };
    const site = {
      minX: this.bounds.min.x - anchor.x - 1,
      maxX: this.bounds.max.x - anchor.x + 1,
      minZ: this.bounds.min.z - anchor.z - 1,
      maxZ: this.bounds.max.z - anchor.z + 1,
    };
    const context = {
      project: (point) => point,
      site,
      radius,
      batches: this.batches,
      metrics,
    };
    let count = 0;
    for (const [kind, builder] of [
      ["buildings", buildBuilding],
      ["roads", buildRoad],
      ["water", buildWater],
      ["vegetation", buildVegetation],
    ]) {
      for (const feature of prepared.data[kind]) {
        builder(feature, context);
        if (++count % 80 === 0) {
          await new Promise((resolve) => setTimeout(resolve, 0));
          if (generation !== this.generation) return;
        }
      }
    }
    // Exact complementary land/sea footprints: no ocean plane over land.
    for (const p of coastal.land)
      this.batches.add("ground", polygonGeometry(p));
    for (const p of coastal.sea) {
      const geometry = polygonGeometry(p);
      geometry.translate(0, SURFACE_Y.sea, 0);
      this.batches.add("sea", geometry);
    }
    this.batches.finish();
    metrics.meshes = this.group.children.length;
    metrics.buildMs = performance.now() - start;
    metrics.triangles = this.group.children.reduce(
      (sum, mesh) => sum + mesh.geometry.attributes.position.count / 3,
      0,
    );
    this.group.userData.metrics = metrics;
    this.group.userData.environmentBounds = environmentBounds.polygon;
    this.group.userData.terrainDimension = radius * 2;
    this.group.userData.categories = prepared.diagnostics;
    if (this.debug) {
      for (const coast of data.coastlines || [])
        this.debug.line(coast.line.map(project), "original");
      this.debug.polygons(coastal.sea, "SEA");
      coastal.coastLines.forEach((line) => this.debug.line(line, "coastline"));
      this.group.add(this.debug.finish());
      console.groupCollapsed(
        "[GeoEnvironment] Tags, categorias e áreas antes/depois do recorte (m²)",
      );
      console.table(
        prepared.diagnostics.map((row) => ({
          ...row,
          tags: JSON.stringify(row.tags),
        })),
      );
      console.groupEnd();
      console.debug("[GeoEnvironment] coastline", coastal.state);
    }
    this.viewport.dataset.geoMetrics = JSON.stringify(metrics);
    this.viewport.dataset.geoState = "ready";
    this.scene.add(this.group);
    this.onChange();
    this.attribution.hidden = false;
    this.update();
    console.debug("[Flow OSM] Entorno", metrics);
  }
  update() {
    if (!this.materials) return;
    for (const material of Object.values(this.materials))
      material.userData.hazeUniforms.urbanSkyEnabled.value =
        !!this.sky?.mesh.visible;
  }
  /** Translating an orthographic eye backwards along its view ray preserves
   * screen positions, zoom and pan; it keeps the near plane ahead of the whole
   * environment instead of slicing the foreground when users zoom out. */
  fitCameraDepth(camera, target) {
    if (
      !this.group ||
      this.group.parent !== this.scene ||
      !camera.isOrthographicCamera
    )
      return;
    const backward = camera.getWorldDirection(new THREE.Vector3()).negate();
    if (backward.lengthSq() === 0) return;
    const center = this.group.position.clone();
    center.y += 250;
    // A esfera celeste visual também deve ficar à frente do plano próximo.
    const radius = Math.hypot(OSM_RADIUS * 2 * SOLAR_VISUAL_RADIUS_MULTIPLIER, 250);
    const distance = center.sub(camera.position).dot(backward.clone().negate());
    const correction = Math.max(0, camera.near + 10 + radius - distance);
    if (correction > 0) {
      camera.position.addScaledVector(backward, correction);
      camera.updateMatrixWorld();
    }
  }
  dispose() {
    this.disposed = true;
    this.clear();
    this.attribution.remove();
    this.notice.remove();
  }
}
