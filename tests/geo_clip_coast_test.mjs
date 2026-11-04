import assert from "node:assert/strict";
import { createEnvironmentBounds,clipPolygons,polygonArea,differencePolygons } from "../MapaPavimento/predio-3d/geo/environment-clip.js";
import { buildCoastalAreas } from "../MapaPavimento/predio-3d/geo/coastline-builder.js";
import { parseOSM } from "../MapaPavimento/predio-3d/geo/osm-parser.js";
import { loadViewerModule } from "./viewer_module_loader.mjs";
import { pathToFileURL } from "node:url";
import { resolve } from "node:path";
let checks=0;
const ok=(condition,message)=>{assert.ok(condition,message);checks++;};
const sum=polys=>polys.reduce((s,p)=>s+polygonArea(p),0);
const rectangle=(a,b,c,d)=>({outer:[{x:a,z:b},{x:c,z:b},{x:c,z:d},{x:a,z:d},{x:a,z:b}],holes:[]});
const id=p=>p;
for(const north of [0,45,84.7,90,180,270]) {
  const bounds=createEnvironmentBounds(100,north), local=p=>({outer:p.outer.map(bounds.toLocal),holes:p.holes.map(r=>r.map(bounds.toLocal))});
  const huge=local(rectangle(-10000,-10000,10000,10000));
  const clipped=clipPolygons([huge],bounds);
  ok(Math.abs(sum(clipped)-40000)<.01,"Huge enclosing polygon clips despite distant centre/vertices");
  const hole=rectangle(-30,-30,30,30).outer;
  const withHole=local({...rectangle(-200,-200,200,200),holes:[hole]});
  ok(Math.abs(sum(clipPolygons([withHole],bounds))-36400)<.01,"Interior hole preserved");
  const boundaryHole=local({...rectangle(-200,-200,200,200),holes:[rectangle(-150,-20,30,20).outer]});
  const cut=clipPolygons([boundaryHole],bounds);
  ok(Math.abs(sum(cut)-34800)<.01,"Hole meeting edge becomes correct notch");
  const concave={outer:[[-150,-80],[150,-80],[150,80],[-150,80],[-150,40],[120,40],[120,-40],[-150,-40],[-150,-80]].map(([x,z])=>({x,z})),holes:[]};
  ok(clipPolygons([local(concave)],bounds).length===2,"Concave polygon splits into disconnected components");
  ok(clipPolygons([local(rectangle(200,200,300,300))],bounds).length===0,"Empty intersection omitted");
  const feature=(line)=>({line:line.map(([x,z])=>bounds.toLocal({x,z}))});
  const coast=buildCoastalAreas([feature([[0,-200],[0,200]])],id,bounds);
  ok(coast.state==="ready"&&Math.abs(sum(coast.sea)-20000)<.01,"Directed mainland coast divides area exactly");
  ok(sum(clipPolygons(coast.sea,{...bounds,polygon:local(rectangle(-100,-100,-1,100))}))===0,"Sea is right/east of northbound coast");
  ok(Math.abs(sum(coast.land)+sum(coast.sea)-40000)<.01,"Land and sea share one closed boundary");
  ok(Math.abs(sum(differencePolygons(coast.sea,coast.land,bounds))-sum(coast.sea))<.001,"No overlap of land/sea");
  const reversed=buildCoastalAreas([feature([[0,200],[0,-200]])],id,bounds);
  ok(sum(clipPolygons(reversed.sea,{...bounds,polygon:local(rectangle(1,-100,100,100))}))===0,"Reverse coast changes sea side");
  const island=feature([[-20,-20],[20,-20],[20,20],[-20,20],[-20,-20]]);
  const islands=buildCoastalAreas([island],id,bounds);
  ok(islands.state==="ready"&&Math.abs(sum(islands.sea)-38400)<.01,"Closed island excluded from sea");
  const channel=buildCoastalAreas([feature([[-40,-200],[-40,200]]),feature([[40,200],[40,-200]])],id,bounds);
  ok(channel.state==="ready"&&Math.abs(sum(channel.sea)-16000)<.01,"Multiple mainland coasts form water channel");
  const disconnected=buildCoastalAreas([feature([[-40,200],[-40,-200]]),feature([[40,-200],[40,200]])],id,bounds);
  ok(disconnected.state==="ready"&&Math.abs(sum(disconnected.sea)-24000)<.01,"Two disconnected sea regions preserved");
  const broken=buildCoastalAreas([feature([[0,0],[0,200]])],id,bounds);
  ok(broken.state==="incomplete"&&!broken.sea.length,"Incomplete interior coast cannot flood land");
}
const tags=[{natural:"wood"},{landuse:"forest"},{landuse:"grass"},{natural:"grassland"},{leisure:"park"},{water:"lake"}];
const closed=[{lat:0,lon:0},{lat:0,lon:1},{lat:1,lon:1},{lat:1,lon:0},{lat:0,lon:0}];
const parsed=parseOSM({elements:tags.map((tags,i)=>({type:"way",id:i,tags,geometry:closed}))});
ok(parsed.vegetation.map(f=>f.category).join(",")==="FOREST,FOREST,GRASS,GRASS,PARK","Visual categories follow exact tags");
ok(parsed.water[0].category==="WATER_INLAND","water=* polygon recognised");
const sharedCoast=parseOSM({elements:[
  {type:"relation",id:90,tags:{type:"multipolygon",natural:"water"},members:[{type:"way",ref:91,role:"outer",geometry:closed}]},
  {type:"way",id:91,tags:{natural:"coastline"},geometry:closed},
]});
ok(sharedCoast.water.length===1&&sharedCoast.coastlines.length===1,"Water relation cannot suppress its shared coastline member");
const path=process.argv.find(a=>a.startsWith("--three-module="))?.slice(15);
if(!path) throw Error("Use --three-module=CAMINHO/three.module.js");
const url=pathToFileURL(resolve(path)).href;
const {ribbonGeometry}=await loadViewerModule("geo/road-builder.js",url);
const {clipBufferGeometry}=await loadViewerModule("geo/geometry-clip.js",url);
const {GeoDebug}=await loadViewerModule("geo/geo-debug.js",url);
const debugBounds=createEnvironmentBounds(100,0),debug=new GeoDebug(debugBounds);
debug.polygons([rectangle(-200,-200,200,200)],"original");
debug.polygons([rectangle(0,-100,100,100)],"SEA");
debug.line([{x:0,z:-100},{x:0,z:100}],"coastline");
const debugGroup=debug.finish();
ok(debugGroup.children.some(o=>o.name==="Debug · bounds")&&debugGroup.children.some(o=>o.name==="Debug · SEA"),"Debug contains bounds and sea outlines");
let disposedDebug=0;debugGroup.children.forEach(o=>o.geometry.addEventListener("dispose",()=>disposedDebug++));
const debugCount=debugGroup.children.length;debug.dispose();
ok(disposedDebug===debugCount,"Debug resources disposed");
for(const north of [0,84.7,180]) {
  const bounds=createEnvironmentBounds(100,north);
  const ribbon=ribbonGeometry([{x:-150,z:98},{x:150,z:98}].map(bounds.toLocal),20);
  const clipped=clipBufferGeometry(ribbon,bounds),p=clipped.attributes.position;
  ok(p.count>0,"Crossing ribbon preserved");
  for(let i=0;i<p.count;i++) {
    const en=bounds.toEN({x:p.getX(i),z:p.getZ(i)});
    assert.ok(Math.abs(en.x)<=100.001&&Math.abs(en.z)<=100.001,"Ribbon WIDTH must respect boundary");
  }
  checks++;clipped.dispose();
}
console.log(`Geo clipping/coast: ${checks} checks passed`);
