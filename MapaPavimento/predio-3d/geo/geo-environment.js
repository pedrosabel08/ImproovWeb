import * as THREE from "three";
import { SOLAR_VISUAL_RADIUS_MULTIPLIER } from "../solar-visuals.js?v=20261103-solar-orbit-v1";
import {
  validGeoConfig,
} from "./geo-coordinates.js?v=20261103-boundary-v4";
import {
  loadOSM,
  browserOSMCache,
  OSM_RADIUS,
} from "./osm-service.js?v=20261112-overpass";
import { urbanMaterial, softenEnvironmentEdge } from "./urban-haze.js?v=20261104-terrain-v1";
import { loadElevation } from "./elevation-service.js?v=20261104-terrain-v1";
import {
  GEO_OPTIONS,
  GEO_COLORS,
} from "./environment-config.js?v=20261103-boundary-v4";
import { GeoDebug } from "./geo-debug.js?v=20261103-boundary-v4";
import { installTerrainDebug } from "./terrain-debug.js?v=20261104-surface-v3";
import { generateEnvironment } from "./generate-environment.js?v=20261110-shared-v1";
import { createEnvironmentBounds } from "./environment-clip.js?v=20261103-boundary-v4";
import { cacheRequest, fetchGeometryCache, restoreGeometryCache, validateGeometryAlignment } from "./server-geometry-cache.js?v=20261110-cache-v3";

/** New group outside modelRoot: never enters picking, fit, floor or solar bounds. */
export class GeoEnvironment {
  constructor({
    scene,
    viewport,
    sky,
    onChange = () => {},
    options = GEO_OPTIONS,
    elevationLoader = loadElevation,
    serverCache = typeof document !== "undefined" &&
      document.querySelector?.('meta[name="flow-geo-cache"][content="1"]') != null,
  }) {
    Object.assign(this, { scene, viewport, sky, onChange });
    this.options = options;
    this.surfaceDebug=options.geoDebug||new URLSearchParams(window.location?.search||'').get('geo_debug')==='1';
    this.elevationLoader = elevationLoader;
    this.serverCache = serverCache;
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
    this.demAttribution = document.createElement("a");
    this.demAttribution.href = "https://mapterhorn.com/attribution/";
    this.demAttribution.target = "_blank";
    this.demAttribution.rel = "noopener noreferrer";
    this.demAttribution.textContent = "© Mapterhorn · Copernicus DEM";
    this.demAttribution.title = "© DLR e.V. 2010–2014 and © Airbus Defence and Space GmbH 2014–2018 provided under COPERNICUS by the European Union and ESA; all rights reserved.";
    this.demAttribution.style.cssText = this.attribution.style.cssText + ";bottom:65px";
    this.demAttribution.hidden = true;
    viewport.append(this.demAttribution);
    this.notice = document.createElement("div");
    this.notice.setAttribute("role", "status");
    this.notice.style.cssText = "position:absolute;left:12px;bottom:48px;z-index:2;max-width:min(380px,calc(100% - 24px));padding:8px 10px;border-radius:6px;background:var(--spatial-panel,#171b22);color:var(--spatial-text,#e4e9ec);font:12px/1.4 sans-serif";
    this.notice.hidden = true;
    viewport.append(this.notice);
    this.loading = document.createElement("div");
    this.loading.setAttribute("role", "status");
    this.loading.style.cssText = this.notice.style.cssText + ";pointer-events:none";
    this.loading.innerHTML = '<div style="display:flex;align-items:center;gap:6px"><canvas data-thinking-orb data-orb-state="searching" data-orb-size="32" aria-label="Buscando ruas e edifícios"></canvas><span>Carregando entorno OpenStreetMap…</span></div>';
    this.loading.hidden = true;
    viewport.append(this.loading);
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
  setModel(root, bounds, buildingBounds = bounds, groundInfo = null, cacheModel = null) {
    this.model = root;
    this.groundInfo = groundInfo;
    this.cacheModel = cacheModel;
    this.bounds = bounds?.clone();
    this.buildingBounds = buildingBounds?.clone();
    return this.refresh();
  }
  prefetchCache(obraId, model, signal) {
    if (this.serverCache && !this.surfaceDebug && obraId && model?.id && model?.version) {
      return fetchGeometryCache(obraId, model, signal);
    }
    return null;
  }
  clear() {
    ++this.generation;
    this.abort?.abort();
    this.abort = null;
    this.hideOsmLoading();
    this.debug?.dispose();
    this.terrainDebug?.dispose();this.terrainDebug=null;
    this.debug = null;
    if (this.group) {
      this.scene.remove(this.group);
      this.group.traverse((object) => object.geometry?.dispose());
    }
    this.materials && Object.values(this.materials).forEach((m) => m.dispose());
    const hadGroup = !!this.group;
    this.materials = this.group = null;
    if (hadGroup) this.onChange();
    this.attribution.hidden = true;
    this.demAttribution.hidden = true;
    this.terrain = null;
    delete this.viewport.dataset.demState;
    delete this.viewport.dataset.geoMetrics;
    delete this.viewport.dataset.geoBuildStage;
    this.viewport.dataset.geoState = "inactive";
  }
  hideOsmLoading() {
    this.loading.hidden = true;
    this.stopOsmOrb?.();
    this.stopOsmOrb = null;
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
    const start = performance.now();
    this.abort = new AbortController();
    this.viewport.dataset.geoState = "loading";
    try {
      const model = this.cacheModel;
      const config = this.config;
      const signal = this.abort.signal;
      const useCache = this.serverCache && !this.surfaceDebug && !!model?.id && !!model?.version &&
        (model.obraId == null || model.obraId === config.obra_id);
      let cacheMetrics;
      if (useCache) {
        try {
          const request = model.prefetch ?? fetchGeometryCache(config.obra_id, model, signal);
          model.prefetch = null;
          const { cached, metrics, error } = await request;
          if (signal.aborted) return;
          cacheMetrics = metrics;
          if (error) throw error;
          if (cached) {
            cacheMetrics.alignmentMs = await validateGeometryAlignment(cached, config, this.bounds, this.buildingBounds, this.groundInfo);
            if (signal.aborted) return;
            this.installCached(cached, cacheMetrics);
            this.measurePresentation(generation, start);
            return;
          }
        } catch (error) {
          if (signal.aborted) return;
          cacheMetrics = { ...cacheMetrics, source: "error" };
          console.debug("[Flow Geo Cache] Usando geração convencional.", error.message);
        }
      }
      const demPromise = this.elevationLoader(this.config, {signal:this.abort.signal})
        .then(data=>({data}), error=>({error}));
      const osmOptions = { cache: this.cache, signal };
      let response = await loadOSM(config, { ...osmOptions, cacheOnly: true });
      if (signal.aborted) return;
      if (!response) {
        this.loading.hidden = false;
        this.stopOsmOrb = window.thinkingOrbs?.(this.loading);
        try {
          response = await loadOSM(config, osmOptions);
        } finally {
          if (generation === this.generation) this.hideOsmLoading();
        }
      } else if (response.stale) {
        // Keep the displayed scene stable. The refresh populates the cache for the next view.
        loadOSM(config, osmOptions).catch(() => {});
      }
      if (generation !== this.generation) return;
      if (response.stale) {
        this.notice.textContent = "O entorno exibido foi carregado de uma versão anterior.";
        this.notice.hidden = false;
      }
      if (!Object.values(response.data).some((items) => items.length)) {
        if (!response.stale) this.abort.abort();
        this.viewport.dataset.geoState = "empty";
        this.notice.textContent = "Nenhum dado de entorno foi encontrado para esta localização.";
        this.notice.hidden = false;
        return;
      }
      const elevation = await demPromise;
      if (generation !== this.generation) return;
      this.viewport.dataset.demState = elevation.data ? "ready" : "fallback";
      if (elevation.error) console.debug("[Flow DEM] Terreno plano preservado.", elevation.error.message);
      if (cacheMetrics?.needsInputs && elevation.data && !response.stale) {
        cacheRequest(config, model, signal, true).catch(() => {});
      }
      await this.build(response.data, generation, response, elevation.data);
      if (useCache && generation === this.generation && this.group) {
        this.group.userData.metrics.geometryCache = cacheMetrics;
        this.viewport.dataset.geoMetrics = JSON.stringify(this.group.userData.metrics);
        this.measurePresentation(generation, start);
      }
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
  installCached(cached, cacheMetrics) {
    const start = performance.now();
    const bounds = createEnvironmentBounds(OSM_RADIUS, this.config.norte_graus);
    const materials = this.createMaterials(bounds, OSM_RADIUS);
    let group;
    try {
      group = restoreGeometryCache(cached, materials);
    } catch (error) {
      Object.values(materials).forEach(material => material.dispose());
      throw error;
    }
    const metrics = {
      ...cached.header.metrics,
      meshes: group.children.length,
      triangles: group.children.reduce((sum, mesh) => sum + mesh.geometry.attributes.position.count / 3, 0),
      geometryCache: {
        ...cacheMetrics, source: cacheMetrics.source === "indexeddb" ? "indexeddb" : "server",
        transferMs: cached.transferMs, validationMs: cached.validationMs,
        restorationMs: performance.now() - start, serializationMs: cached.serializationMs, ...cached.header.timings,
      },
    };
    group.userData.environmentBounds = bounds.polygon;
    group.userData.terrainDimension = OSM_RADIUS * 2;
    group.userData.geoOrigin = { latitude: this.config.latitude, longitude: this.config.longitude };
    group.userData.metrics = metrics;
    Object.assign(this, { group, materials });
    this.viewport.dataset.demState = "ready";
    this.present(metrics, true);
  }
  measurePresentation(generation, start) {
    requestAnimationFrame(() => requestAnimationFrame(() => {
      if (generation !== this.generation || !this.group) return;
      const metrics = this.group.userData.metrics;
      metrics.geometryCache.presentationMs = performance.now();
      metrics.geometryCache.environmentPresentationMs = performance.now() - start;
      this.viewport.dataset.geoMetrics = JSON.stringify(metrics);
    }));
  }
  async build(data, generation, response, dem = null) {
    const result = await generateEnvironment({
      data, response, dem,
      config: this.config,
      bounds: this.bounds,
      buildingBounds: this.buildingBounds,
      groundInfo: this.groundInfo,
      signal: this.abort.signal,
      createMaterials: (environmentBounds, radius) => this.createMaterials(environmentBounds, radius),
      createDebug: bounds => this.options.geoDebug ? new GeoDebug(bounds) : null,
      onStage: stage => { this.viewport.dataset.geoBuildStage = stage; },
      onDemFallback: error => {
        this.viewport.dataset.demState = "fallback";
        console.debug("[Flow DEM] Alinhamento indisponível; terreno plano preservado.", error.message);
      },
    });
    if (!result) return;
    const { group, materials, terrain, debug, metrics, environmentBounds, radius, prepared, coastal, project, start } = result;
    if (generation !== this.generation) {
      group.traverse(object => object.geometry?.dispose());
      Object.values(materials).forEach(material => material.dispose());
      debug?.dispose();
      return;
    }
    Object.assign(this, { group, materials, terrain, debug });
    this.viewport.dataset.geoBuildStage='audit';
    if(this.surfaceDebug)this.terrainDebug=installTerrainDebug(this.group,this.viewport,this.terrain);
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
    this.present(metrics, !!this.terrain);
  }
  present(metrics, demReady) {
    this.viewport.dataset.geoMetrics = JSON.stringify(metrics);
    this.viewport.dataset.geoState = "ready";
    this.viewport.dataset.geoBuildStage='ready';
    this.scene.add(this.group);
    this.onChange();
    this.attribution.hidden = false;
    this.demAttribution.hidden = !demReady;
    this.update();
    console.debug("[Flow OSM] Entorno", metrics);
  }
  createMaterials(environmentBounds, radius) {
    const materials = Object.fromEntries(
      Object.entries(GEO_COLORS).map(([key, color]) => [
        key,
        urbanMaterial(color, {
          sky: this.sky,
          radius,
          ground: key === "ground",
        }),
      ]),
    );
    for (const [key,material] of Object.entries(materials)) {
      softenEnvironmentEdge(material,environmentBounds);
      if(["road","FOREST","GRASS","PARK","water"].includes(key)) {
        material.polygonOffset=true;material.polygonOffsetFactor=-1;material.polygonOffsetUnits=-2;
      }
    }
    return materials;
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
    this.demAttribution.remove();
    this.notice.remove();
    this.loading.remove();
  }
}
