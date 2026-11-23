import assert from "node:assert/strict";
import { resolve } from "node:path";
import { pathToFileURL } from "node:url";
import { loadViewerModule } from "./viewer_module_loader.mjs";

const threePath = process.argv.find(arg => arg.startsWith("--three-module="))?.split("=")[1];
if (!threePath) throw new Error("Use --three-module=CAMINHO/three.module.js r180");
const threeURL = pathToFileURL(resolve(threePath)).href;
const THREE = await import(threeURL);
const { parseOSM } = await loadViewerModule("geo/osm-parser.js", threeURL);
const { generateEnvironment } = await loadViewerModule("geo/generate-environment.js", threeURL);
const { TerrainMaterials } = await loadViewerModule("geo/terrain-materials.js", threeURL);
const { GeometryBatches } = await loadViewerModule("geo/feature-builder.js", threeURL);
const { createShoreDistance } = await loadViewerModule("water-material.js", threeURL);
const { waterProfile } = await loadViewerModule("geo/water-profile.js", threeURL);
const { waterSurfaceParts } = await loadViewerModule("geo/water-builder.js", threeURL);
const { auditSurfaceProjection } = await loadViewerModule("geo/surface-mesh.js", threeURL);
const { prepareLandSurfaces, limitManualBeachHeight } = await loadViewerModule("geo/land-surfaces.js", threeURL);
const { createEnvironmentBounds, polygonArea } = await loadViewerModule("geo/environment-clip.js", threeURL);

const config = { latitude: 0, longitude: 0, norte_graus: 0, obra_id: 1 };
const point = (x, z) => ({ lon: x / 111195.0802, lat: -z / 111195.0802 });
const ring = (x, z, w, h) => [
  point(x, z), point(x+w, z), point(x+w, z+h), point(x, z+h), point(x, z),
];
const way = (id, tags, geometry) => ({ type: "way", id, tags, geometry });
const data = parseOSM({ elements: [
  way(1, { surface: "dirt" }, ring(180, 180, 100, 100)),
  way(2, { natural: "mud" }, ring(260, 180, 40, 100)),
  way(3, { leisure: "garden" }, ring(180, 180, 40, 40)),
  way(4, { natural: "water" }, ring(250, 190, 30, 40)),
  way(5, { highway: "residential", surface: "asphalt" }, [point(160,320), point(300,320)]),
  way(6, { highway: "residential", surface: "gravel" }, [point(160,350), point(300,350)]),
  way(7, { highway: "footway", surface: "dirt" }, ring(350, 350, 30, 30)),
  way(8, { landuse: "residential" }, ring(410, 410, 30, 30)),
  way(9, { landcover: "grass" }, ring(330, 300, 20, 20)),
  way(10, { surface: "dirt", area: "no" }, ring(310, 220, 10, 10)),
  way(11, { natural: "beach" }, ring(295, 180, 35, 70)),
  way(12, { natural: "bare_rock" }, ring(400, 180, 30, 30)),
  way(13, { natural: "beach", surface: "pebblestone" }, ring(440, 180, 20, 20)),
  way(14, { highway: "footway", footway: "sidewalk" }, [point(350,170), point(350,290)]),
  way(15, { highway: "residential", sidewalk: "left" }, [point(370,320), point(430,320)]),
  way(16, { highway: "service" }, [point(401,300), point(401,340)]),
  way(17, { natural: "water" }, ring(340, 220, 20, 20)),
  way(18, { landuse: "grass" }, ring(400, 180, 10, 10)),
  way(19, { natural: "beach", surface: "concrete" }, ring(480, 180, 20, 20)),
  // A bend and a crossing on the steeper western half of the synthetic DEM.
  way(20, {highway:"residential",surface:"asphalt"}, [point(-420,90),point(-360,90),point(-330,115),point(-310,160)]),
  way(21, {highway:"residential",surface:"asphalt"}, [point(-380,45),point(-380,145)]),
] });
assert.deepEqual(data.soil.map(f => f.id), ["way/1", "way/2"]);
assert.deepEqual(data.vegetation.map(f => f.category), ["PARK", "GRASS", "GRASS"]);
assert.deepEqual(data.sand.map(f => f.id), ["way/11"]);
assert.deepEqual(data.rock.map(f => f.id), ["way/12", "way/13"]);

const box = new THREE.Box3(new THREE.Vector3(-5, 0, -5), new THREE.Vector3(5, 15, 5));
const sample = (x, z) => 18 + 0.002*x + 0.001*z + 0.18*Math.max(0,-x-50);
const { group, metrics, terrain, environmentBounds, radius } = await generateEnvironment({
  data, config, bounds: box, buildingBounds: box, dem: { sample, reference: 18 },
  createMaterials: () => Object.fromEntries(
    ["ground", "soil", "asphalt", "road", "PARK", "GRASS", "water", "sand", "rock", "sidewalk", "sidewalk-extra"]
      .map(key => [key, new THREE.MeshStandardMaterial()]),
  ),
});
const get = key => group.children.find(mesh => mesh.name === `OSM · ${key}`);
assert.equal(metrics.soilAreaM2, 8850, "Union of dirt polygons, excluding garden, sand and inland water");
assert.ok(get("asphalt") && get("road"), "Asphalt and unpaved roads use separate batches");
const soil = get("soil");
const report = auditSurfaceProjection(soil.geometry, terrain.mesh, 0.01);
assert.ok(report.missingSamples === 0 && report.maximumErrorM < 0.0001, JSON.stringify(report));
assert.equal(metrics.surfaceAreasM2.rock, 1200, "The vegetation footprint takes priority over classified rock");
assert.equal(metrics.surfaceAreasM2.sidewalk, 180, "Water cuts the mapped sidewalk");
assert.equal(metrics.surfaceAreasM2["sidewalk-extra"], 101, "Crossing carriageway cuts the inferred sidewalk");
const inferredPositions = get("sidewalk-extra").geometry.attributes.position.array;
assert.ok(inferredPositions.every((value, i) => i % 3 !== 2 || value < 320 - 3.5),
  "The left sidewalk stays on the left in the direction of the OSM way");
for (const key of ["sand", "rock", "sidewalk", "sidewalk-extra"]) {
  const mesh = get(key);
  assert.ok(mesh?.geometry.attributes.position.count > 0, key);
  const projection = auditSurfaceProjection(mesh.geometry, terrain.mesh, key.startsWith("sidewalk") ? 0.025 : 0.01);
  assert.ok(projection.missingSamples === 0 && projection.maximumErrorM < 0.0001, JSON.stringify(projection));
}

const manualBounds = createEnvironmentBounds(100, 0);
const area = (x, z, w, h) => ({ outer: [{x,z}, {x:x+w,z}, {x:x+w,z:z+h}, {x,z:z+h}, {x,z}], holes: [] });
const manualPrepared = Object.fromEntries(
  ["roads", "water", "buildings", "vegetation", "sidewalk", "sand", "rock", "soil", "urbanExclusion"].map(key => [key, []]),
);
for (const [tags, expected] of [[{water:"lagoon"},"standing"],[{waterway:"canal"},"flowing"],
  [{landuse:"reservoir"},"standing"],[{natural:"water"},"generic"]])
  assert.equal(waterProfile({visualTags:tags}), expected);
const mixed = waterSurfaceParts(area(0,0,40,10), [
  {profile:"flowing",footprint:[area(0,0,30,10)]},
  {profile:"standing",footprint:[area(20,0,20,10)]},
]);
assert.deepEqual(mixed.map(p=>[p.profile,polygonArea(p.polygon)]), [["standing",200],["flowing",200]]);
const emptyGroup = new THREE.Group();
const emptyGeometry = new THREE.BufferGeometry();
emptyGeometry.setAttribute("position",new THREE.Float32BufferAttribute([],3));
const emptyBatches = new GeometryBatches(emptyGroup,{});
emptyBatches.add("water",emptyGeometry,false,"generic");
emptyBatches.finish();
assert.equal(emptyGroup.children.length,0,"A subpixel remainder must not create an unrestorable water batch");
manualPrepared.urbanExclusion.push({ polygons: [area(0, -10, 40, 12)] });
manualPrepared.rock.push({ polygons: [area(0, 10, 20, 7)] });
const manualArgs = {
  prepared: manualPrepared, config, bounds: manualBounds,
  project: p => ({ x: p.lon, z: p.lat }),
  terrain: { waterFootprint: [] },
  coastal: { state: "ready", sea: [area(-100, -100, 100, 200)], land: [area(0, -100, 100, 200)],
    coastLines: [[{ x: 0, z: -100 }, { x: 0, z: 100 }]] },
  beachByProject: {1: {widthM: 20, ring: area(-25, -25, 100, 50).outer.map(({x,z}) => ({lon:x, lat:z}))}},
};
const manual = prepareLandSurfaces(manualArgs).layers["sand-manual"];
assert.equal(manual.reduce((sum, polygon) => sum + polygonArea(polygon), 0), 620);
assert.ok(manual.every(p => p.outer.every(v => v.x >= 0 && v.x <= 20)));
assert.deepEqual(prepareLandSurfaces({...manualArgs, terrain: null}).layers["sand-manual"], []);
const slope = new THREE.BufferGeometry();
slope.setAttribute("position", new THREE.Float32BufferAttribute([0,0,0, 0,0,10, 10,10,0], 3));
const lowBeach = limitManualBeachHeight(slope, 4);
assert.ok(lowBeach.attributes.position.count > 0);
assert.ok(lowBeach.attributes.position.array.every((v, i) => i % 3 !== 1 || v <= 4));
lowBeach.dispose();

const manager = new TerrainMaterials();
const loaded = new Map();
manager.loader.loadAsync = async url => {
  assert.ok(!loaded.has(url), "Every runtime map is fetched once");
  const texture = new THREE.Texture();
  loaded.set(url, texture);
  return texture;
};
const apply = enabled => new Promise(resolve => manager.apply(
  group, environmentBounds, null, radius, enabled,
  (state, packs) => { if (state !== "loading") resolve({ state, packs }); },
));
const original = new Map(group.children.map(mesh => [mesh, [mesh.material, mesh.geometry]]));
assert.deepEqual(await apply(true), {state: "ready", packs: ["asphalt", "concrete", "grass", "rock", "sand", "soil", "water"]});
assert.equal(loaded.size, 21);
const asphalt = get("asphalt");
const asphaltShader = {uniforms: {}, vertexShader: "#include <uv_vertex>", fragmentShader: "#include <map_fragment>"};
asphalt.material.onBeforeCompile(asphaltShader);
assert.doesNotMatch(asphaltShader.vertexShader, /flowWarp|v(?:Map|NormalMap|RoughnessMap|AoMap)Uv\s*\+=/);
assert.match(asphaltShader.fragmentShader, /flowVariation/);
assert.equal(asphalt.material.map.repeat.x, 1 / 22);
for (let i = 0; i < asphalt.geometry.attributes.position.count; i++) {
  const {uv, position} = asphalt.geometry.attributes;
  assert.equal(uv.getX(i), position.getX(i));
  assert.equal(uv.getY(i), -position.getZ(i));
}
for (const [key, folder] of Object.entries({ asphalt: "asphalt-track", sand: "aerial-beach-01", rock: "rocky-terrain-02", sidewalk: "concrete-floor-01", soil: "dirt-aerial-02" }))
  assert.equal(get(key).material.name, `Flow · ${folder}`);
assert.equal(get("sidewalk").material, get("sidewalk-extra").material);
assert.equal(get("GRASS").material, get("PARK").material);
assert.equal(get("road").material, original.get(get("road"))[0]);
assert.equal(get("ground").material, original.get(get("ground"))[0]);
assert.equal(soil.material.map.colorSpace, THREE.SRGBColorSpace);
assert.equal(soil.material.normalMap.colorSpace, THREE.NoColorSpace);
assert.equal(soil.material.aoMap, soil.material.roughnessMap);
assert.equal(soil.geometry.attributes.uv.count, soil.geometry.attributes.position.count);
assert.equal(soil.material.map.repeat.x, 1 / 20);
assert.equal(soil.material.displacementMap, null);
const water = get("water").material;
const shader = { uniforms: {}, vertexShader: "#include <begin_vertex>", fragmentShader: "#include <normal_fragment_maps>" };
water.onBeforeCompile(shader);
assert.match(shader.fragmentShader, /secondaryUv/);
assert.doesNotMatch(shader.fragmentShader, /texture2D\(flowShoreDistance/, "Inland water without a classified sea has no coastal tint");
manager.update(1500);
const phases = [shader.uniforms.flowWavePrimary, shader.uniforms.flowWaveSecondary];
assert.notDeepEqual(phases[0].value.toArray(), phases[1].value.toArray());
const moving = phases.map(phase => phase.value.toArray());
manager.update(1600);
assert.notDeepEqual(phases.map(phase => phase.value.toArray()), moving);
manager.motion = { matches: true };
manager.update(2500);
assert.ok(phases.every(phase => phase.value.lengthSq() === 0));

// A sea rectangle has a real coast at x=0 and three artificial edges.
const seaGeometry = new THREE.BufferGeometry();
seaGeometry.setAttribute("position", new THREE.Float32BufferAttribute([
  0, 0, -100, 100, 0, -100, 100, 0, 100,
  0, 0, -100, 100, 0, 100, 0, 0, 100,
], 3));
const sea = [new THREE.Mesh(seaGeometry)];
const square = { radius: 100, toEN: point => point };
assert.equal(createShoreDistance(sea, square, "incomplete"), null);
const distance = createShoreDistance(sea, square, "ready");
const size = distance.image.width;
const distanceAt = (x,z) => distance.image.data[
  Math.floor((z + 100) * size / 200) * size + Math.floor((x + 100) * size / 200)
];
assert.ok(distanceAt(3, 0) < distanceAt(16, 0));
assert.equal(distanceAt(98, 98), 255, "Acquisition perimeter does not lighten the water");
assert.equal(distanceAt(50, 0), 255, "Internal triangulation edge does not lighten the water");
distance.dispose();
const classifiedSand = new THREE.BufferGeometry();
classifiedSand.setAttribute("position", new THREE.Float32BufferAttribute([
  0,0,-60, -8,0,-60, 0,0,-10,
], 3));
assert.equal(createShoreDistance(sea, square, "ready", []), null);
const beachDistance = createShoreDistance(sea, square, "ready", [new THREE.Mesh(classifiedSand)]);
const beachPixel = (x,z) => {
  const {width,data} = beachDistance.image;
  return data[Math.floor((z+100)*width/200)*width+Math.floor((x+100)*width/200)];
};
assert.ok(beachPixel(-3,-40)<beachPixel(-10,-40) && beachPixel(3,-40)<beachPixel(10,-40));
assert.equal(beachPixel(1,45),255,"Unclassified coast has no beach treatment");
assert.equal(beachPixel(98,-40),255,"Artificial perimeter stays deep");
beachDistance.dispose();
classifiedSand.dispose();
seaGeometry.dispose();
assert.deepEqual(await apply(false), {state: "off", packs: []});
assert.ok(group.children.every(mesh=>mesh.visible), "Disabling textures preserves every classified surface");
assert.ok(group.children.every(mesh => mesh.material === original.get(mesh)[0] && mesh.geometry === original.get(mesh)[1]));
await apply(true);
assert.equal(loaded.size, 21);
let disposals = 0;
for (const texture of loaded.values()) texture.addEventListener("dispose", () => disposals++);
manager.dispose();
await Promise.resolve();
assert.equal(disposals, 21);
const unavailable = new TerrainMaterials();
unavailable.loader.loadAsync = async () => { throw Error("404 fixture"); };
const missing = await new Promise(resolve => unavailable.apply(
  group, environmentBounds, null, radius, true,
  (state, packs) => { if (state !== "loading") resolve({ state, packs }); },
));
assert.deepEqual(missing, {state: "fallback", packs: []});
assert.ok(group.children.every(mesh=>mesh.visible), "Missing maps leave the base surfaces visible");
assert.ok(group.children.every(mesh => mesh.material === original.get(mesh)[0]));
unavailable.dispose();
const partial = new TerrainMaterials();
partial.loader.loadAsync = async url => {
  if (url.includes("/normal.webp")) throw Error("Missing normal");
  return new THREE.Texture();
};
const hidden = get("rock");
hidden.visible = false;
await new Promise(resolve => partial.apply(group,environmentBounds,null,radius,true,state=>{
  if (state !== "loading") { assert.equal(state,"fallback"); resolve(); }
}));
assert.ok(group.children.every(mesh=>mesh.material===original.get(mesh)[0] && mesh.visible===(mesh!==hidden)));
partial.dispose();
for (const mesh of group.children) {
  mesh.geometry.dispose();
  original.get(mesh)[0].dispose();
}
console.log("Terrain PBR: OSM, DEM, material sharing, animation, fallback, toggle and disposal passed.");
