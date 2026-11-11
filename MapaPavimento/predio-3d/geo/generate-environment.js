import * as THREE from "three";
import {
  GeometryBatches,
  polygonGeometry,
} from "./feature-builder.js?v=20261104-surface-v3";
import { clipBufferGeometry } from "./geometry-clip.js?v=20261104-surface-v3";
import { buildBuilding } from "./building-builder.js?v=20261104-terrain-v1";
import { buildRoad } from "./road-builder.js?v=20261104-terrain-v1";
import { buildWater } from "./water-builder.js?v=20261104-surface-v3";
import { buildVegetation } from "./vegetation-builder.js?v=20261104-surface-v3";
import { TerrainSurface } from "./terrain-surface.js?v=20261104-surface-v3";
import { inspectModelGround } from "./model-ground.js?v=20261104-surface-v3";
import {
  createEnvironmentBounds,
  polygonArea,
} from "./environment-clip.js?v=20261103-boundary-v4";
import { buildCoastalAreas } from "./coastline-builder.js?v=20261103-boundary-v4";
import { prepareEnvironment } from "./prepare-environment.js?v=20261103-boundary-v4";
import { geoProjector } from "./geo-coordinates.js?v=20261103-boundary-v4";
import { GEO_COLORS, SURFACE_Y } from "./environment-config.js?v=20261103-boundary-v4";
import { OSM_RADIUS } from "./osm-service.js?v=20261103-osm-cache-v3";

/** Build detached geometry. The browser supplies its materials and optional diagnostics. */
export async function generateEnvironment({
  data, config, bounds, buildingBounds, groundInfo, response = {}, dem,
  signal = new AbortController().signal,
  createMaterials = () => Object.fromEntries(Object.keys(GEO_COLORS).map(key => [key, null])),
  createDebug = () => null,
  onStage = () => {},
  onDemFallback = () => {},
}) {
  let group, batches, terrain, materials, debug, pendingSurfaceParts, pendingBeds;
  let complete = false;
  try {
    if (signal.aborted) return null;
    const start = performance.now(),
      radius = OSM_RADIUS;
    const anchor = buildingBounds.getCenter(new THREE.Vector3());
    const groundY = groundInfo?.elevation ?? bounds.min.y;
    const environmentBounds = createEnvironmentBounds(
        radius,
        config.norte_graus,
      ),
      project = geoProjector(config);
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
    debug = createDebug(environmentBounds);
    const prepared = prepareEnvironment(
      data,
      project,
      environmentBounds,
      coastal,
      debug,
    );
    const site = {
      minX: bounds.min.x - anchor.x - 1,
      maxX: bounds.max.x - anchor.x + 1,
      minZ: bounds.min.z - anchor.z - 1,
      maxZ: bounds.max.z - anchor.z + 1,
    };
    let ground = null;
    const terrainTimes={};
    if (dem) {
      try {
        let stage=performance.now();
        ground = inspectModelGround(groundInfo?.object, anchor, groundY);
        terrainTimes.groundMs=performance.now()-stage;stage=performance.now();
        await new Promise(resolve=>setTimeout(resolve,0));
        if (signal.aborted) return null;
        terrain = new TerrainSurface({dem, bounds:environmentBounds, ground, site, coastLines:coastal.coastLines,deferGrid:true});
        onStage('water-profile');
        terrain.prepareWater(prepared.data.water,{rebuild:false});
        terrainTimes.waterMs=performance.now()-stage;
        stage=performance.now();
        onStage('dem-grid');
        await terrain.rebuildAsync(signal);
        if (signal.aborted) return null;
        terrainTimes.gridMs=performance.now()-stage;
      } catch (error) {
        if (signal.aborted) return null;
        terrain = null;
        onDemFallback(error);
      }
    }
    group = new THREE.Group();
    group.name = "Entorno urbano OpenStreetMap";
    group.position.set(anchor.x, groundY, anchor.z);
    group.userData.geoOrigin = {
      latitude: config.latitude,
      longitude: config.longitude,
    };
    materials = createMaterials(environmentBounds, radius);
    batches = new GeometryBatches(
      group,
      materials,
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
      dem: terrain ? {source:dem.source, tiles:dem.tiles, memoryCacheHits:dem.cacheHits,
        referenceAltitudeM:dem.reference, modelReferenceY:groundY+terrain.referenceModel,
        seaWorldY:groundY+terrain.seaLevel, verticalScale:1,
        groundTriangles:ground?.triangles ?? 0, groundSourceTriangles:ground?.sourceTriangles ?? 0, groundReliefM:ground ? ground.max-ground.min : null,
        groundAreaM2:ground?.footprint.reduce((sum,p)=>sum+polygonArea(p),0),
        minRelativeM:terrain.heights.reduce((a,b)=>Math.min(a,b),Infinity),
        maxRelativeM:terrain.heights.reduce((a,b)=>Math.max(a,b),-Infinity),
        alignment:ground ? "glb-surface" : "protected-footprint", gridAxis:terrain.axis.length} : {fallback:true},
      terrainTimes,
    };
    const context = {
      project: (point) => point,
      site,
      radius,
      batches: batches,
      metrics,
      terrain:terrain,
    };
    const surfaceParts = [];
    pendingSurfaceParts = new Set();
    onStage('ground-mesh');
    // Exact complementary land/sea footprints: no ocean plane over land.
    for (const p of terrain?.groundPolygons(coastal.land) || coastal.land) {
      let geometry=terrain ? await terrain.geometryAsync(p,signal) : polygonGeometry(p);
      if(signal.aborted) { geometry?.dispose(); return null; }
      if(terrain)geometry=clipBufferGeometry(geometry,environmentBounds);
      if(!geometry)continue;
      if(terrain) { surfaceParts.push(geometry); pendingSurfaceParts.add(geometry); }
      else batches.add("ground",geometry);
      await new Promise(resolve=>setTimeout(resolve,0));
      if (signal.aborted) return null;
    }
    let modelGeometry=null;
    if (terrain && ground) {
      let geometry=new THREE.BufferGeometry();
      geometry.setAttribute("position",new THREE.Float32BufferAttribute(ground.positions,3));
      geometry.computeVertexNormals();
      geometry=clipBufferGeometry(geometry,environmentBounds);
      if(geometry) {
        surfaceParts.push(geometry);
        pendingSurfaceParts.add(geometry);
        modelGeometry=geometry;
      }
    }
    if(terrain) {
      // Build and index ground BEFORE draping other layers. Keep a bed in the
      // CPU index so roads crossing a channel retain their existing behaviour.
      const beds=[];pendingBeds=beds;
      onStage('water-mesh');
      for(const polygon of terrain.waterFootprint) {
        const bed=clipBufferGeometry(terrain.geometry(polygon,0,(x,z)=>terrain.waterHeight(null,x,z)-.12),environmentBounds);
        if(bed)beds.push(bed);
        const water=terrain.geometry(polygon,SURFACE_Y.water,(x,z)=>terrain.waterHeight(null,x,z));
        batches.add("water",water);metrics.polygons++;
        await new Promise(resolve=>setTimeout(resolve,0));
        if(signal.aborted) return null;
      }
      terrain.installMesh([...surfaceParts,...beds]);
      beds.forEach(g=>g.dispose());
      pendingBeds=null;
      surfaceParts.forEach(g=>{
        pendingSurfaceParts.delete(g);
        if(g===modelGeometry) {
          const mesh=new THREE.Mesh(g,materials.ground);mesh.name='OSM · terreno GLB';
          mesh.userData.geoLayer='model-ground';mesh.receiveShadow=true;mesh.raycast=()=>{};group.add(mesh);
        } else batches.add("ground",g);
      });
    }
    pendingSurfaceParts=null;
    let count = 0;
    for (const [kind, builder] of [
      ["buildings", buildBuilding],
      ["roads", buildRoad],
      ["water", buildWater],
      ["vegetation", buildVegetation],
    ]) {
      onStage(kind);
      for (const feature of prepared.data[kind]) {
        builder(feature, context);
        if (++count % 80 === 0) {
          await new Promise((resolve) => setTimeout(resolve, 0));
          if (signal.aborted) return null;
        }
      }
    }
    for (const p of coastal.sea) {
      const geometry = polygonGeometry(p);
      geometry.translate(0, terrain?.seaLevel ?? SURFACE_Y.sea, 0);
      batches.add("sea", geometry);
    }
    batches.finish();
    complete = true;
    return { group, materials, terrain, debug, metrics, environmentBounds, radius, prepared, coastal, project, start };
  } finally {
    if (!complete) {
      pendingSurfaceParts?.forEach(geometry => geometry.dispose());
      pendingBeds?.forEach(geometry => geometry.dispose());
      batches?.dispose();
      group?.traverse(object => object.geometry?.dispose());
      debug?.dispose();
      Object.values(materials ?? {}).forEach(material => material?.dispose());
    }
  }
}
