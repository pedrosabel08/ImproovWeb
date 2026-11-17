import assert from "node:assert/strict";
import { pathToFileURL } from "node:url";
import { resolve } from "node:path";
import { loadViewerModule } from "./viewer_module_loader.mjs";
import { geoProjector } from "../MapaPavimento/predio-3d/geo/geo-coordinates.js";
import { parseOSM, buildingHeight } from "../MapaPavimento/predio-3d/geo/osm-parser.js";
import { loadOSM, browserOSMCache, osmCacheKey, overpassQuery } from "../MapaPavimento/predio-3d/geo/osm-service.js";
import { solarDirection } from "../MapaPavimento/predio-3d/solar-position.mjs";

const modulePath = process.argv.find(a => a.startsWith("--three-module="))?.split("=")[1];
if (!modulePath) throw new Error("Use --three-module=CAMINHO/three.module.js r180");
const url = pathToFileURL(resolve(modulePath)).href;
const THREE = await import(url);
const { polygonGeometry, GeometryBatches } = await loadViewerModule("geo/feature-builder.js", url);
const { buildBuilding } = await loadViewerModule("geo/building-builder.js", url);

let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
for (const north of [0, 45, 84.7, 90, 180, 270, 359.9]) {
  const config = {latitude:-27,longitude:-48,norte_graus:north};
  const project = geoProjector(config);
  const origin = project({lat:-27,lon:-48});
  ok(Math.hypot(origin.x,origin.z) === 0, "Geographic origin");
  const n = project({lat:-26.999,lon:-48}), e = project({lat:-27,lon:-47.999});
  ok(Math.abs(Math.hypot(n.x,n.z)-111.19508) < .001,"Metres north");
  const ns = solarDirection(0,0,north), es = solarDirection(90,0,north);
  ok(n.x*ns.x+n.z*ns.z > 111.19,"North agrees with SunCalc basis");
  ok(e.x*es.x+e.z*es.z > 99,"East agrees with SunCalc basis");
  ok(Math.abs(n.x*e.x+n.z*e.z) < 1e-7,"Orthogonal axes");
}
const square = (lat,lon,size=.0001) => [{lat,lon},{lat:lat+size,lon},{lat:lat+size,lon:lon+size},{lat,lon:lon+size},{lat,lon}];
const outer = square(-27,-48,.001), hole = square(-26.9998,-47.9998);
const fixture = {elements:[
  {type:"way",id:1,tags:{building:"house",name:"Unused private name"},geometry:square(-26.998,-48)},
  {type:"way",id:2,tags:{building:"yes"},geometry:outer},
  {type:"relation",id:2,tags:{type:"multipolygon",building:"residential"},members:[
    {type:"way",ref:2,role:"outer",geometry:outer.slice(0,3)},
    {type:"way",ref:3,role:"outer",geometry:[outer[0],outer[3],outer[2]]},
    {type:"way",ref:4,role:"inner",geometry:hole},
  ]},
  {type:"way",id:5,tags:{highway:"primary"},geometry:[{lat:-27,lon:-48},{lat:-27,lon:-47.999}]},
  {type:"way",id:6,tags:{natural:"water"},geometry:square(-27,-48)},
  {type:"way",id:7,tags:{landuse:"forest"},geometry:square(-27,-48)},
  {type:"way",id:8,tags:{building:"yes"},geometry:[{lat:-27,lon:-48},null]},
]};
const data = parseOSM(fixture);
ok(data.buildings.length === 2,"Relation/member deduplication");
ok(data.buildings.find(b => b.id === "relation/2").polygons[0].holes.length === 1,"Joined multipolygon holes");
ok(!JSON.stringify(data).includes("Unused private name"),"Only visual fields normalised");
ok(data.roads.length === 1 && data.water.length === 1 && data.vegetation.length === 1,"Visual categories");
for (const [tags, expected] of [[{height:"12.5 m"},12.5],[{height:"30 ft"},9.144],
  [{"building:levels":"4"},12],[{building:"house"},6],[{building:"residential"},9],[{},8]])
  ok(Math.abs(buildingHeight(tags)-expected)<1e-6,"Height priority/units/fallback");
assert.throws(() => parseOSM({elements:[],remark:"timeout"})); checks++;
assert.throws(() => overpassQuery({latitude:null,longitude:-48})); checks++;

// A genuine hole must reduce roof area, and all roof normals must face +Y.
const local = {outer:[{x:0,z:0},{x:10,z:0},{x:10,z:10},{x:0,z:10},{x:0,z:0}],
  holes:[[{x:2,z:2},{x:4,z:2},{x:4,z:4},{x:2,z:4},{x:2,z:2}]]};
const geometry = polygonGeometry(local,12); geometry.computeBoundingBox();
ok(Math.abs(geometry.boundingBox.max.y-12)<1e-6 && geometry.boundingBox.min.y>-1e-6,"Vertical metre extrusion");
const p=geometry.attributes.position, n=geometry.attributes.normal; let roofArea=0;
for (let i=0;i<p.count;i+=3) if (n.getY(i)>.99) {
  const a=new THREE.Vector3().fromBufferAttribute(p,i), b=new THREE.Vector3().fromBufferAttribute(p,i+1), c=new THREE.Vector3().fromBufferAttribute(p,i+2);
  roofArea+=b.sub(a).cross(c.sub(a)).length()/2;
}
ok(Math.abs(roofArea-96)<1e-6,"Roof respects courtyard hole"); geometry.dispose();
const config={latitude:-27,longitude:-48,norte_graus:0};
const group=new THREE.Group(), material=new THREE.MeshStandardMaterial();
const batches=new GeometryBatches(group,{building0:material});
const metrics={buildings:0,polygons:0,excludedBuildings:0};
buildBuilding(data.buildings.find(b=>b.id==="way/1"),{project:geoProjector(config),site:{minX:-10,maxX:10,minZ:-10,maxZ:10},radius:1000,batches,metrics});
batches.finish();
ok(metrics.buildings===1 && group.children.length===1,"Building batch");
ok(group.children[0].castShadow===false,"Distant building does not cast shadows");
ok(group.children[0].geometry.attributes.position.array.every(Number.isFinite),"Finite geometry");
const { ribbonGeometry } = await loadViewerModule("geo/road-builder.js", url);
const roadGroup = new THREE.Group(), roadMaterial = new THREE.MeshStandardMaterial({transparent:true});
const roadBatches = new GeometryBatches(roadGroup,{road:roadMaterial,ground:material});
roadBatches.add("road",ribbonGeometry([{x:0,z:0},{x:50,z:0}],7));
roadBatches.add("ground",polygonGeometry(local));
roadBatches.finish();
const roadMesh = roadGroup.children.find(mesh=>mesh.name === "OSM · road");
const groundMesh = roadGroup.children.find(mesh=>mesh.name === "OSM · ground");
ok(roadMesh.renderOrder < groundMesh.renderOrder && roadMesh.material.depthTest && roadMesh.material.depthWrite,
  "Roads render before other transparent surfaces while retaining depth checks");
ok(roadMesh.receiveShadow && !roadMesh.castShadow,"Road surface receives building shadows without casting shadows");
const { urbanMaterial, URBAN_FOG_ENABLED } = await loadViewerModule("geo/urban-haze.js",url);
const noFogMaterial=urbanMaterial(0x555d55,{radius:1000});
ok(!URBAN_FOG_ENABLED && noFogMaterial.onBeforeCompile===THREE.Material.prototype.onBeforeCompile,
  "Fog comparison mode bypasses both haze and distance fade shader");
noFogMaterial.dispose();
roadGroup.children.forEach(mesh=>mesh.geometry.dispose()); roadMaterial.dispose();

const stored=new Map(), storage={get length(){return stored.size;},key:i=>[...stored.keys()][i],
 getItem:k=>stored.get(k),setItem:(k,v)=>stored.set(k,v),removeItem:k=>stored.delete(k)};
let requests=0;
const fetcher=async()=>{requests++;return {ok:true,json:async()=>({sucesso:true,contexto_osm:{payload:fixture,origin:{latitude:requests===1?config.latitude:-27.1,longitude:config.longitude},acquired_at:Math.floor(Date.now()/1000),expires_at:Math.floor(Date.now()/1000)+604800,stale:false}})};};
const cache=browserOSMCache(storage);
const cachedConfig={...config,obra_id:102};
await loadOSM(cachedConfig,{cache,fetcher});
const cached=await loadOSM(cachedConfig,{cache,fetcher});
ok(requests===1 && cached.cacheHit,"Repeat open uses cache");
ok(osmCacheKey(config)!==osmCacheKey({...config,latitude:-27.01}),"Cache key includes location");
ok(osmCacheKey(config,1000)!==osmCacheKey(config,500),"Cache key includes radius");
const throwing=browserOSMCache({getItem(){throw Error("disabled");},setItem(){throw Error("quota");}});
await loadOSM({...cachedConfig,latitude:-27.1},{cache:throwing,fetcher});
ok(requests===2,"Cache denial does not stop acquisition");
await assert.rejects(loadOSM({...config,latitude:-27.2},{fetcher:async()=>({ok:false,status:503})})); checks++;
await assert.rejects(loadOSM({...config,latitude:-27.2},{fetcher:async()=>({ok:true,json:async()=>({elements:[],remark:"partial"})})})); checks++;

const workConfig={...config,obra_id:102};
let backendOptions,backendURL,writes=0;
const backendCache={get:()=>null,set:()=>writes++};
const backendResponse=(extra={})=>({ok:true,json:async()=>({sucesso:true,contexto_osm:{
  payload:fixture,origin:{latitude:config.latitude,longitude:config.longitude},acquired_at:Math.floor(Date.now()/1000)-100,expires_at:Math.floor(Date.now()/1000)+604700,cache_hit:false,stale:false,...extra}})});
const backend=await loadOSM(workConfig,{cache:backendCache,fetcher:async(url,options)=>{
  backendURL=new URL(url);backendOptions=options;return backendResponse();}});
ok(backendURL.pathname.endsWith('/MapaPavimento/api.php')&&backendURL.searchParams.get('action')==='osm_contexto'&&backendURL.searchParams.get('obra_id')==='102',"Saved work uses module API");
ok(backendOptions.method==='GET'&&backendOptions.credentials==='same-origin'&&!backendOptions.body,"Backend gets only work ID, with same-origin session");
ok(backend.acquisitionSource==='server'&&!backend.serverCacheHit&&writes===1,"Live server response populates browser cache");
const stale=await loadOSM(workConfig,{cache:backendCache,fetcher:async()=>backendResponse({cache_hit:true,stale:true})});
ok(stale.stale&&stale.serverCacheHit&&writes===1,"Stale server response does not reset browser cache age");
await assert.rejects(loadOSM(workConfig,{cache:backendCache,fetcher:async()=>backendResponse({origin:{latitude:0,longitude:0}})}));checks++;
ok(writes===1,"Changed saved location rejected before browser cache write");
const controller=new AbortController();controller.abort();
await assert.rejects(loadOSM(workConfig,{cache:backendCache,signal:controller.signal,fetcher:async()=>backendResponse()}));checks++;
ok(writes===1,"Cancelled acquisition cannot populate cache");
group.children.forEach(m=>m.geometry.dispose()); material.dispose();

// Real controller lifecycle, with HTTP responses isolated from external availability.
const previousFetch=globalThis.fetch;
globalThis.window={localStorage:storage};
globalThis.document={createElement:()=>({style:{},setAttribute(){},remove(){this.removed=true;}})};
const {GeoEnvironment}=await loadViewerModule("geo/geo-environment.js",url);
const root=new THREE.Group();
const ground=new THREE.Mesh(new THREE.BoxGeometry(30,.2,30),new THREE.MeshStandardMaterial({color:0x667755}));
ground.name="Terreno_1"; root.add(ground); root.position.set(120,10,-80); root.updateMatrixWorld(true);
const scene=new THREE.Scene(); scene.add(root);
const viewport={dataset:{},append(){}};
const sky={mesh:{visible:false},uniforms:{}};
const environment=new GeoEnvironment({scene,viewport,sky,elevationLoader:async()=>{throw Error("DEM offline fixture");}});
await environment.setConfig({...config,obra_id:102,latitude:-27.3});
globalThis.fetch=async()=>({ok:true,json:async()=>({sucesso:true,contexto_osm:{payload:fixture,origin:{latitude:-27.3,longitude:config.longitude},acquired_at:Math.floor(Date.now()/1000),expires_at:Math.floor(Date.now()/1000)+604800,stale:false}})});
const modelBounds=new THREE.Box3().setFromObject(root);
await environment.setModel(root,modelBounds);
ok(viewport.dataset.geoState==="ready" && scene.children.includes(root),"Scene succeeds without changing model");
ok(environment.group.position.x===120 && environment.group.position.z===-80,"Non-zero GLB anchor preserved");
ok(environment.group.parent===scene && !root.children.includes(environment.group),"Environment outside model bounds/picking");
ok(environment.group.children.length<=10,"Draw calls grouped");
ok(environment.group.children.every(m=>m.geometry.attributes.position.array.every(Number.isFinite)),"All environment geometry finite");
for(const direction of [[1,1,1],[0,1,0],[1,0,0],[0,.01,1]]) {
  const camera=new THREE.OrthographicCamera(-100,100,100,-100,.01,100000);
  const target=new THREE.Vector3(120,60,-80),point=new THREE.Vector3(125,20,-75);
  camera.position.copy(target).add(new THREE.Vector3(...direction).normalize().multiplyScalar(100));
  camera.lookAt(target);camera.updateMatrixWorld();
  const before=point.clone().project(camera);
  environment.fitCameraDepth(camera,target);
  const after=point.clone().project(camera);
  ok(Math.abs(before.x-after.x)<1e-9&&Math.abs(before.y-after.y)<1e-9,"Environment depth clearance preserves orthographic screen framing");
  const forward=camera.getWorldDirection(new THREE.Vector3());
  for(const x of [-1000,1000]) for(const z of [-1000,1000]) {
    const corner=new THREE.Vector3(x,0,z).add(environment.group.position);
    assert.ok(corner.sub(camera.position).dot(forward)>camera.near,"Near plane cannot cut environment foreground");
  }
  checks++;
}
sky.mesh.visible=true; environment.update();
ok(environment.materials.ground.userData.hazeUniforms.urbanSkyEnabled.value,"Haze follows current HDRI visibility");
let disposed=0; environment.group.children.forEach(m=>m.geometry.addEventListener("dispose",()=>disposed++));
const meshes=environment.group.children.length;
globalThis.fetch=async()=>{throw Error("OSM offline");};
await environment.setConfig({...config,obra_id:102,latitude:-27.4});
ok(viewport.dataset.geoState==="error" && scene.children.includes(root) && scene.children.length===1,"OSM failure keeps GLB alive, no partial group");
ok(disposed===meshes,"Switch/failure releases all GPU geometries");
const old = Math.floor(Date.now()/1000)-8*86400;
const oldContext = {payload:fixture,origin:{latitude:-27.3001,longitude:config.longitude},acquired_at:old,expires_at:old+604800,stale:true,cache_hit:true};
let finishRefresh;
globalThis.fetch = input => new URL(input).searchParams.has("cache_only")
  ? Promise.resolve({ok:true,json:async()=>({sucesso:true,contexto_osm:oldContext})})
  : new Promise(resolve=>{finishRefresh=resolve;});
await environment.setConfig({...config,obra_id:102,latitude:-27.3001});
ok(viewport.dataset.geoState==="ready" && environment.loading.hidden && !!finishRefresh,
  "Stale server cache is presented while refresh is still pending");
finishRefresh({ok:false,status:503});
await new Promise(resolve=>setImmediate(resolve));
ok(viewport.dataset.geoState==="ready" && environment.group.parent===scene,
  "Background failure preserves the cached environment");

let finishMiss;
globalThis.fetch = input => new URL(input).searchParams.has("cache_only")
  ? Promise.resolve({ok:true,json:async()=>({sucesso:true,contexto_osm:null})})
  : new Promise(resolve=>{finishMiss=resolve;});
const miss = environment.setConfig({...config,obra_id:102,latitude:-27.7});
await new Promise(resolve=>setImmediate(resolve));
ok(!environment.loading.hidden && scene.children.includes(root), "Cache miss loads without blocking the model");
finishMiss({ok:false,status:503});
await miss;
ok(environment.loading.hidden && viewport.dataset.geoState==="error", "Loading ends on upstream failure");
globalThis.fetch=async()=>({ok:true,json:async()=>({sucesso:true,contexto_osm:{payload:{elements:[]},origin:{latitude:-27.5,longitude:config.longitude},acquired_at:Math.floor(Date.now()/1000),expires_at:Math.floor(Date.now()/1000)+604800,stale:false}})});
await environment.setConfig({...config,obra_id:102,latitude:-27.5});
ok(viewport.dataset.geoState==="empty" && scene.children.length===1,"Empty response preserves current viewer");
let release;
globalThis.fetch=()=>new Promise(resolve=>{release=resolve;});
const pending=environment.setConfig({...config,obra_id:102,latitude:-27.6});
await environment.setModel(null,null);
release({ok:true,json:async()=>fixture}); await pending;
ok(viewport.dataset.geoState==="inactive" && scene.children.length===1,"Cancelled old request never installs on next model");
environment.dispose();
ok(environment.attribution.removed,"Attribution cleaned on disposal");
globalThis.fetch=previousFetch; delete globalThis.window; delete globalThis.document;
ground.geometry.dispose(); ground.material.dispose();
console.log(`Geo environment: ${checks} checks passed`);
