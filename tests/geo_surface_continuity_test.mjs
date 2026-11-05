import assert from 'node:assert/strict';
import {pathToFileURL} from 'node:url';
import {resolve} from 'node:path';
import {loadViewerModule} from './viewer_module_loader.mjs';
const path=process.argv.find(a=>a.startsWith('--three-module='))?.split('=')[1];
const url=pathToFileURL(resolve(path)).href;
const THREE=await import(url);
const {TerrainSurface}=await loadViewerModule('geo/terrain-surface.js',url);
const {createEnvironmentBounds,polygonArea}=await loadViewerModule('geo/environment-clip.js',url);
const {polygonGeometry}=await loadViewerModule('geo/feature-builder.js',url);
const {auditSurfaceGeometry,auditSurfaceProjection,SurfaceMesh,improveSurfaceTriangles}=await loadViewerModule('geo/surface-mesh.js',url);
const {buildVegetation}=await loadViewerModule('geo/vegetation-builder.js',url);
const {clipBufferGeometry}=await loadViewerModule('geo/geometry-clip.js',url);
const {inspectModelGround}=await loadViewerModule('geo/model-ground.js',url);
const bounds=createEnvironmentBounds(1000,0), site={minX:-2,maxX:2,minZ:-2,maxZ:2};
const dem={reference:100,sample:(x,z)=>100+.2*x+.3*z};
const surface=new TerrainSurface({dem,bounds,site});
const river={polygons:[],waterway:'river',line:[{x:100,z:400},{x:180,z:280},{x:130,z:150},{x:240,z:40}]};
surface.prepareWater([river]);
const narrow=new TerrainSurface({dem,bounds,site});
narrow.prepareWater([{polygons:[],waterway:'stream',line:[{x:200,z:100},{x:200,z:400}]}]);
const bankJump=Math.abs(narrow.sample(201.509,255)-narrow.sample(201.511,255));
console.log({bankJumpM:bankJump});
assert.ok(bankJump<.01,'No discontinuous snap just outside the exact channel edge');
const inputSurface=new TerrainSurface({dem,bounds,site});inputSurface.prepareWater([river]);
const corners=[{x:70,z:20},{x:270,z:20},{x:270,z:420},{x:70,z:420}],pivot={x:100.00001,z:80};
const fan=new THREE.BufferGeometry();fan.setAttribute('position',new THREE.Float32BufferAttribute(corners.flatMap((a,i)=>[a,corners[(i+1)%4],pivot].flatMap(p=>[p.x,0,p.z])),3));
const quad=polygonGeometry({outer:[...corners,corners[0]],holes:[]});
const canonicalA=inputSurface.drape(fan,0,null,false,true),canonicalB=inputSurface.drape(quad,0,null,false,true);
const indexA=new SurfaceMesh([canonicalA]),indexB=new SurfaceMesh([canonicalB]);
assert.equal(indexA.candidates(-1e9,-1e9,1e9,1e9).length,indexA.faces.length,'Queries from long OSM ways stay bounded by the acquired surface');
for(const x of [80,100,145,185,260])for(const z of [30,80,200,300,410]) {
  const a=indexA.sample(x,z),b=indexB.sample(x,z);
  assert.ok(Math.abs(a-b)<.002,'Changing only source triangulation must not change the topographic surface '+JSON.stringify({x,z,a,b,facesA:indexA.faces.length,facesB:indexB.faces.length}));
}
canonicalA.dispose();canonicalB.dispose();
const elbow=new TerrainSurface({dem,bounds,site});
elbow.prepareWater([{polygons:[],waterway:'river',line:[{x:100,z:200},{x:100,z:300},{x:200,z:300}]}]);
for(const polygon of elbow.waterFootprint)for(const p of polygon.outer)
  assert.ok(Math.abs(elbow.sample(p.x,p.z)-elbow.waterHeight(null,p.x,p.z)+.12)<.0001,'Miter corners and end caps use the actual water footprint, not a centreline distance band');
const region={outer:[{x:70,z:20},{x:270,z:20},{x:270,z:420},{x:70,z:420},{x:70,z:20}],holes:[]};
const land=surface.groundPolygons([region]).map(p=>surface.geometry(p));
surface.installMesh(land);
// Sample the interior of actual rendered faces, not only their vertices.
let maximum=0, degenerates=0, inverted=0,maximumSlope=0;
for(const geometry of land) {
  const p=geometry.attributes.position;
  for(let i=0;i<p.count;i+=3) {
    const x=(p.getX(i)+p.getX(i+1)+p.getX(i+2))/3,z=(p.getZ(i)+p.getZ(i+1)+p.getZ(i+2))/3;
    const y=(p.getY(i)+p.getY(i+1)+p.getY(i+2))/3;
    maximum=Math.max(maximum,Math.abs(y-surface.sample(x,z)));
    const area=(p.getZ(i+1)-p.getZ(i))*(p.getX(i+2)-p.getX(i))-(p.getX(i+1)-p.getX(i))*(p.getZ(i+2)-p.getZ(i));
    if(Math.abs(area)<1e-9)degenerates++;
    if(area< -1e-9)inverted++;
    if(area>1e-9) {
      const ax=p.getX(i+1)-p.getX(i),ay=p.getY(i+1)-p.getY(i),az=p.getZ(i+1)-p.getZ(i);
      const bx=p.getX(i+2)-p.getX(i),by=p.getY(i+2)-p.getY(i),bz=p.getZ(i+2)-p.getZ(i);
      maximumSlope=Math.max(maximumSlope,Math.hypot(ay*bz-az*by,ax*by-ay*bx)/area);
    }
  }
}
console.log({maximumInteriorErrorM:maximum,degenerates,inverted,maximumSlope});
assert.ok(maximumSlope<2,'A gentle DEM and continuous bank must not produce needle faces with extreme gradients');
assert.ok(maximum<.002,'Rendered ground interiors and sampling must describe the SAME planes');
assert.equal(degenerates,0,'Clipping must not emit collapsed faces');
assert.equal(inverted,0,'All surface faces must point upward');
const concave={outer:[{x:80,z:30},{x:260,z:30},{x:260,z:410},{x:190,z:410},{x:190,z:340},{x:80,z:340},{x:80,z:30}],
  holes:[[{x:90,z:50},{x:90,z:100},{x:115,z:100},{x:115,z:50},{x:90,z:50}]]};
const vegetation=[];
buildVegetation({polygons:[concave],category:'FOREST'},{project:p=>p,terrain:surface,metrics:{polygons:0,vegetation:0},batches:{add:(key,g)=>vegetation.push(g)}});
let overlayArea=0;
for(const geometry of vegetation) {
  const audit=auditSurfaceGeometry(geometry);
  for(const key of ['invalidIndices','nonFinite','degenerate','inverted','inconsistentNormals'])assert.equal(audit[key],0,key);
  const projection=auditSurfaceProjection(geometry,surface.mesh,.01);
  assert.ok(projection.maximumErrorM<.002,JSON.stringify(projection));
  assert.equal(projection.missingSamples,0);
  const p=geometry.attributes.position;
  for(let i=0;i<p.count;i+=3)overlayArea+=Math.abs((p.getZ(i+1)-p.getZ(i))*(p.getX(i+2)-p.getX(i))-(p.getX(i+1)-p.getX(i))*(p.getZ(i+2)-p.getZ(i)))/2;
  geometry.dispose();
}
const expectedArea=surface.vegetationPolygons([concave]).reduce((sum,p)=>sum+polygonArea(p),0);
assert.ok(Math.abs(overlayArea-expectedArea)<.02,'Concavity, hole and channel area survive draping without bridges or duplicate coverage');
// Connected features must use one level at confluences, independent of feature order.
const branch={polygons:[],waterway:'stream',line:[{x:260,z:320},{x:180,z:280}]};
const joined=new TerrainSurface({dem,bounds,site});joined.prepareWater([river,branch]);
const reverse=new TerrainSurface({dem,bounds,site});reverse.prepareWater([branch,river]);
for(const [x,z] of [[180,280],[179,282],[210,235],[140,150]]) {
  assert.equal(joined.waterHeight(river,x,z),joined.waterHeight(branch,x,z));
  assert.ok(Math.abs(joined.waterHeight(river,x,z)-reverse.waterHeight(river,x,z))<1e-5,'Feature order cannot change confluence levels');
}
const lakes=new TerrainSurface({dem,bounds,site});
const lake=x=>({polygons:[{outer:[{x,z:100},{x:x+20,z:100},{x:x+20,z:120},{x,z:120},{x,z:100}],holes:[]}]});
const lakeA=lake(100),lakeB=lake(125);lakes.prepareWater([lakeA,lakeB]);
assert.ok(Math.abs(lakes.waterHeight(lakeA,101,110)-lakes.waterHeight(lakeA,119,110))<1e-5,'Nearby disconnected lakes remain horizontal');
let waterError=0;
for(const polygon of joined.waterFootprint) {
  const geometry=joined.geometry(polygon,.04,(x,z)=>joined.waterHeight(null,x,z)),p=geometry.attributes.position;
  const audit=auditSurfaceGeometry(geometry);
  assert.equal(audit.degenerate+audit.inverted+audit.nonFinite,0);
  for(let i=0;i<p.count;i+=3) {
    const x=(p.getX(i)+p.getX(i+1)+p.getX(i+2))/3,z=(p.getZ(i)+p.getZ(i+1)+p.getZ(i+2))/3;
    waterError=Math.max(waterError,Math.abs((p.getY(i)+p.getY(i+1)+p.getY(i+2))/3-joined.waterHeight(null,x,z)-.04));
  }
  geometry.dispose();
}
assert.ok(waterError<.002,'Water interior agrees with common planar profile');
const indexed=new THREE.BufferGeometry();indexed.setAttribute('position',new THREE.Float32BufferAttribute([80,0,30,80,0,50,100,0,30],3));indexed.setIndex([0,1,2]);
const indexedDrape=surface.drape(indexed,.025);assert.ok(indexedDrape.attributes.position.count>0);indexedDrape.dispose();
const invalid=new THREE.BufferGeometry();invalid.setAttribute('position',new THREE.Float32BufferAttribute([NaN,0,0,0,0,1,1,0,0],3));
assert.throws(()=>surface.drape(invalid),/inválida/);invalid.dispose();
// Float32 precision and the final rotated-square clip must not reintroduce bad faces.
for(const angle of [0,37,91,179]) {
  const rotated=createEnvironmentBounds(1000,angle);
  const clipped=clipBufferGeometry(polygonGeometry(rotated.polygon),rotated);
  const audit=auditSurfaceGeometry(clipped);
  assert.equal(audit.degenerate+audit.inverted+audit.nonFinite,0,'Final boundary clip');clipped.dispose();
}
const collapsed=new THREE.BufferGeometry();collapsed.setAttribute('position',new THREE.Float32BufferAttribute([1000,0,0,1000,0,1,1000.000001,0,0],3));
collapsed.setAttribute('normal',new THREE.Float32BufferAttribute([0,1,0,0,1,0,0,1,0],3));
assert.equal(clipBufferGeometry(collapsed,bounds),null,'Collapsed Float32 sliver is discarded');
const wall=new THREE.BufferGeometry();wall.setAttribute('position',new THREE.Float32BufferAttribute([0,0,0,0,10,0,10,0,0],3));wall.computeVertexNormals();
const kept=clipBufferGeometry(wall,bounds);assert.equal(kept.attributes.position.count,3,'Valid vertical walls remain');kept.dispose();
// Minimal curved-bank sliver: all faces/indices are valid, but the original
// interior diagonal amplifies a 16 cm change into a near-vertical needle.
const needlePoints=[[234.404663,-6.216892,-201.218933],[234.93956,-6.216892,-206.762482],[233.699142,-6.054729,-193.973419],[250,-5.2,-195]];
const needle=new THREE.BufferGeometry();needle.setAttribute('position',new THREE.Float32BufferAttribute([0,1,2,1,3,2].flatMap(i=>needlePoints[i]),3));needle.computeVertexNormals();
assert.ok(auditSurfaceGeometry(needle).maximumSlope>20,'The regression fixture really contains a needle');
const heights=new Map(needlePoints.map(p=>[`${Math.fround(p[0])}/${Math.fround(p[2])}`,p[1]]));
const improved=new THREE.BufferGeometry();improved.setAttribute('position',new THREE.Float32BufferAttribute(improveSurfaceTriangles(needle).flatMap(t=>t.flatMap(p=>[p.x,heights.get(`${p.x}/${p.z}`),p.z])),3));improved.computeVertexNormals();
assert.ok(auditSurfaceGeometry(improved).maximumSlope<1,'Constrained edge flips remove the needle without moving any boundary vertex or height');
needle.dispose();improved.dispose();
const overlapping=new THREE.Mesh(new THREE.BufferGeometry(),new THREE.MeshBasicMaterial());
const square=[[-10,-10],[-10,10],[10,10],[10,10],[10,-10],[-10,-10]];
overlapping.geometry.setAttribute('position',new THREE.Float32BufferAttribute([1,-1].flatMap(sign=>square.flatMap(([x,z])=>[x,sign*.1*x,z])),3));
const sourceCopy=overlapping.geometry.attributes.position.array.slice();
const envelope=inspectModelGround(overlapping,new THREE.Vector3(),0);
let envelopeArea=0;
for(let i=0;i<envelope.positions.length;i+=9) {
  const p=envelope.positions.slice(i,i+9),x=(p[0]+p[3]+p[6])/3,z=(p[2]+p[5]+p[8])/3;
  assert.ok(Math.abs((p[1]+p[4]+p[7])/3-envelope.sample(x,z))<.0001,'Only the upper GLB face is rendered and projected');
  envelopeArea+=Math.abs((p[5]-p[2])*(p[6]-p[0])-(p[3]-p[0])*(p[8]-p[2]))/2;
}
assert.ok(Math.abs(envelopeArea-400)<.01,'Overlapping GLB projections are represented once');
assert.deepEqual(overlapping.geometry.attributes.position.array,sourceCopy,'Original GLB buffer remains unchanged');
overlapping.geometry.dispose();overlapping.material.dispose();
const stepped=new THREE.Mesh(new THREE.BufferGeometry(),new THREE.MeshBasicMaterial());
const rotatedQuad=(minX,maxX,y)=>[[minX,-2],[minX,2],[maxX,2],[maxX,2],[maxX,-2],[minX,-2]].flatMap(([x,z])=>[
  x*Math.cos(.731833)-z*Math.sin(.731833)+.123456789,y,x*Math.sin(.731833)+z*Math.cos(.731833)+.987654321]);
stepped.geometry.setAttribute('position',new THREE.BufferAttribute(new Float64Array([...rotatedQuad(-2,2,-2),...rotatedQuad(0,2,0)]),3));
const steppedGround=inspectModelGround(stepped,new THREE.Vector3(),0);
for(const edge of steppedGround.edges)assert.ok(Math.abs(edge.ay-edge.by)<.0001,'Rounded GLB boundaries must use one supporting plane per segment, not interpolate endpoints from different levels');
stepped.geometry.dispose();stepped.material.dispose();
land.forEach(g=>g.dispose());
console.log('Surface continuity: ground/vegetation interiors and edges, concavity, holes, channel exclusion, curved rivers, confluence/order, winding, finite coordinates and indexed input passed', {waterError,overlayArea,expectedArea});
