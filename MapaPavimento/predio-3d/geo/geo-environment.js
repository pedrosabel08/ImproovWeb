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
} from "./feature-builder.js?v=20261104-surface-v3";
import { clipBufferGeometry } from "./geometry-clip.js?v=20261104-surface-v3";
import { buildBuilding } from "./building-builder.js?v=20261104-terrain-v1";
import { buildRoad } from "./road-builder.js?v=20261104-terrain-v1";
import { buildWater } from "./water-builder.js?v=20261104-surface-v3";
import { buildVegetation } from "./vegetation-builder.js?v=20261104-surface-v3";
import { urbanMaterial, softenEnvironmentEdge } from "./urban-haze.js?v=20261104-terrain-v1";
import { loadElevation } from "./elevation-service.js?v=20261104-terrain-v1";
import { TerrainSurface } from "./terrain-surface.js?v=20261104-surface-v3";
import { inspectModelGround } from "./model-ground.js?v=20261104-surface-v3";
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
import { installTerrainDebug } from "./terrain-debug.js?v=20261104-surface-v3";

/** New group outside modelRoot: never enters picking, fit, floor or solar bounds. */
export class GeoEnvironment {
  constructor({
    scene,
    viewport,
    sky,
    onChange = () => {},
    options = GEO_OPTIONS,
    elevationLoader = loadElevation,
  }) {
    Object.assign(this, { scene, viewport, sky, onChange });
    this.options = options;
    this.surfaceDebug=options.geoDebug||new URLSearchParams(window.location?.search||'').get('geo_debug')==='1';
    this.elevationLoader = elevationLoader;
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
    this.pendingSurfaceParts?.forEach(g=>g.dispose());this.pendingSurfaceParts=null;
    this.pendingBeds?.forEach(g=>g.dispose());this.pendingBeds=null;
    this.debug?.dispose();
    this.terrainDebug?.dispose();this.terrainDebug=null;
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
    this.demAttribution.hidden = true;
    this.terrain = null;
    delete this.viewport.dataset.demState;
    delete this.viewport.dataset.geoMetrics;
    delete this.viewport.dataset.geoBuildStage;
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
      const demPromise = this.elevationLoader(this.config, {signal:this.abort.signal})
        .then(data=>({data}), error=>({error}));
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
        this.abort.abort();
        this.viewport.dataset.geoState = "empty";
        this.notice.textContent = "Nenhum dado de entorno foi encontrado para esta localização.";
        this.notice.hidden = false;
        return;
      }
      const elevation = await demPromise;
      if (generation !== this.generation) return;
      this.viewport.dataset.demState = elevation.data ? "ready" : "fallback";
      if (elevation.error) console.debug("[Flow DEM] Terreno plano preservado.", elevation.error.message);
      await this.build(response.data, generation, response, elevation.data);
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
  async build(data, generation, response, dem = null) {
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
    const site = {
      minX: this.bounds.min.x - anchor.x - 1,
      maxX: this.bounds.max.x - anchor.x + 1,
      minZ: this.bounds.min.z - anchor.z - 1,
      maxZ: this.bounds.max.z - anchor.z + 1,
    };
    let ground = null;
    const terrainTimes={};
    if (dem) {
      try {
        let stage=performance.now();
        ground = inspectModelGround(this.groundInfo?.object, anchor, groundY);
        terrainTimes.groundMs=performance.now()-stage;stage=performance.now();
        await new Promise(resolve=>setTimeout(resolve,0));
        if(generation!==this.generation)return;
        this.terrain = new TerrainSurface({dem, bounds:environmentBounds, ground, site, coastLines:coastal.coastLines,deferGrid:true});
        this.viewport.dataset.geoBuildStage='water-profile';
        this.terrain.prepareWater(prepared.data.water,{rebuild:false});
        terrainTimes.waterMs=performance.now()-stage;
        stage=performance.now();
        this.viewport.dataset.geoBuildStage='dem-grid';
        await this.terrain.rebuildAsync(this.abort.signal);
        if(generation!==this.generation)return;
        terrainTimes.gridMs=performance.now()-stage;
      } catch (error) {
        if(generation!==this.generation)return;
        this.terrain = null;
        this.viewport.dataset.demState = "fallback";
        console.debug("[Flow DEM] Alinhamento indisponível; terreno plano preservado.",error.message);
      }
    }
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
    for (const [key,material] of Object.entries(this.materials)) {
      softenEnvironmentEdge(material,environmentBounds);
      if(["road","FOREST","GRASS","PARK","water"].includes(key)) {
        material.polygonOffset=true;material.polygonOffsetFactor=-1;material.polygonOffsetUnits=-2;
      }
    }
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
      dem: this.terrain ? {source:dem.source, tiles:dem.tiles, memoryCacheHits:dem.cacheHits,
        referenceAltitudeM:dem.reference, modelReferenceY:groundY+this.terrain.referenceModel,
        seaWorldY:groundY+this.terrain.seaLevel, verticalScale:1,
        groundTriangles:ground?.triangles ?? 0, groundSourceTriangles:ground?.sourceTriangles ?? 0, groundReliefM:ground ? ground.max-ground.min : null,
        groundAreaM2:ground?.footprint.reduce((sum,p)=>sum+polygonArea(p),0),
        minRelativeM:this.terrain.heights.reduce((a,b)=>Math.min(a,b),Infinity),
        maxRelativeM:this.terrain.heights.reduce((a,b)=>Math.max(a,b),-Infinity),
        alignment:ground ? "glb-surface" : "protected-footprint", gridAxis:this.terrain.axis.length} : {fallback:true},
      terrainTimes,
    };
    const context = {
      project: (point) => point,
      site,
      radius,
      batches: this.batches,
      metrics,
      terrain:this.terrain,
    };
    const surfaceParts=[];this.pendingSurfaceParts=surfaceParts;
    this.viewport.dataset.geoBuildStage='ground-mesh';
    // Exact complementary land/sea footprints: no ocean plane over land.
    for (const p of this.terrain?.groundPolygons(coastal.land) || coastal.land) {
      let geometry=this.terrain ? await this.terrain.geometryAsync(p,this.abort.signal) : polygonGeometry(p);
      if(generation!==this.generation) {geometry.dispose();return;}
      if(this.terrain)geometry=clipBufferGeometry(geometry,environmentBounds);
      if(!geometry)continue;
      if(this.terrain)surfaceParts.push(geometry);
      else this.batches.add("ground",geometry);
      await new Promise(resolve=>setTimeout(resolve,0));
      if (generation !== this.generation) return;
    }
    let modelGeometry=null;
    if (this.terrain && ground) {
      let geometry=new THREE.BufferGeometry();
      geometry.setAttribute("position",new THREE.Float32BufferAttribute(ground.positions,3));
      geometry.computeVertexNormals();
      geometry=clipBufferGeometry(geometry,environmentBounds);
      if(geometry) {
        surfaceParts.push(geometry);
        modelGeometry=geometry;
      }
    }
    if(this.terrain) {
      // Build and index ground BEFORE draping other layers. Keep a bed in the
      // CPU index so roads crossing a channel retain their existing behaviour.
      const beds=[];this.pendingBeds=beds;
      this.viewport.dataset.geoBuildStage='water-mesh';
      for(const polygon of this.terrain.waterFootprint) {
        const bed=clipBufferGeometry(this.terrain.geometry(polygon,0,(x,z)=>this.terrain.waterHeight(null,x,z)-.12),environmentBounds);
        if(bed)beds.push(bed);
        const water=this.terrain.geometry(polygon,SURFACE_Y.water,(x,z)=>this.terrain.waterHeight(null,x,z));
        this.batches.add("water",water);metrics.polygons++;
        await new Promise(resolve=>setTimeout(resolve,0));
        if(generation!==this.generation) {surfaceParts.forEach(g=>g.dispose());beds.forEach(g=>g.dispose());return;}
      }
      this.terrain.installMesh([...surfaceParts,...beds]);
      beds.forEach(g=>g.dispose());
      this.pendingBeds=null;
      surfaceParts.forEach(g=>{
        if(g===modelGeometry) {
          const mesh=new THREE.Mesh(g,this.materials.ground);mesh.name='OSM · terreno GLB';
          mesh.userData.geoLayer='model-ground';mesh.receiveShadow=true;mesh.raycast=()=>{};this.group.add(mesh);
        } else this.batches.add("ground",g);
      });
    }
    this.pendingSurfaceParts=null;
    let count = 0;
    for (const [kind, builder] of [
      ["buildings", buildBuilding],
      ["roads", buildRoad],
      ["water", buildWater],
      ["vegetation", buildVegetation],
    ]) {
      this.viewport.dataset.geoBuildStage=kind;
      for (const feature of prepared.data[kind]) {
        builder(feature, context);
        if (++count % 80 === 0) {
          await new Promise((resolve) => setTimeout(resolve, 0));
          if (generation !== this.generation) return;
        }
      }
    }
    for (const p of coastal.sea) {
      const geometry = polygonGeometry(p);
      geometry.translate(0, this.terrain?.seaLevel ?? SURFACE_Y.sea, 0);
      this.batches.add("sea", geometry);
    }
    this.batches.finish();
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
    this.viewport.dataset.geoMetrics = JSON.stringify(metrics);
    this.viewport.dataset.geoState = "ready";
    this.viewport.dataset.geoBuildStage='ready';
    this.scene.add(this.group);
    this.onChange();
    this.attribution.hidden = false;
    this.demAttribution.hidden = !this.terrain;
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
    this.demAttribution.remove();
    this.notice.remove();
  }
}
