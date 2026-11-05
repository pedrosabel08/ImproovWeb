import * as THREE from "three";
import { unionPolygons, differencePolygons } from "./environment-clip.js?v=20261104-surface-v3";
import { polygonGeometry } from "./feature-builder.js?v=20261104-surface-v3";
import { clipFace, faceHeight, crossXZ, resolvedFace } from "./surface-mesh.js?v=20261104-surface-v3";

function higherPart(poly,height) {
  const out=[];
  for(let i=0;i<poly.length;i++) {
    const a=poly[i],b=poly[(i+1)%poly.length],da=height(a),db=height(b);
    if(da>=0)out.push(a);
    if((da>0&&db<0)||(da<0&&db>0)) {const t=da/(da-db);out.push({x:a.x+t*(b.x-a.x),z:a.z+t*(b.z-a.z)});}
  }
  return out;
}

/** Read world-space top faces, before the existing viewer hides exported terrain.
 * No source buffer, material, transform, visibility or model bounds is changed here. */
export function inspectModelGround(object, anchor, groundY) {
  if (!object) return null;
  const triangles = [], polygons = [], positions = [], bins = new Map(), cell = 24;
  const a = new THREE.Vector3(), b = a.clone(), c = a.clone(), normal = a.clone();
  object.updateWorldMatrix(true, true);
  object.traverse(child => {
    if (!child.isMesh || !child.geometry?.attributes.position) return;
    const p = child.geometry.attributes.position, index = child.geometry.index;
    for (let i = 0, length = index?.count ?? p.count; i < length; i += 3) {
      [a, b, c].forEach((v, j) => v.fromBufferAttribute(p, index ? index.getX(i + j) : i + j)
        .applyMatrix4(child.matrixWorld).sub(new THREE.Vector3(anchor.x, groundY, anchor.z)));
      normal.subVectors(b, a).cross(new THREE.Vector3().subVectors(c, a));
      if (normal.y <= 1e-6 || normal.y / normal.length() < .25) continue;
      const t = [a.clone(), b.clone(), c.clone()], id = triangles.length;
      triangles.push(t); t.forEach(v => positions.push(v.x, v.y, v.z));
      polygons.push({outer: [...t, t[0]].map(v => ({x:v.x, z:v.z})), holes: []});
      const minX = Math.floor(Math.min(a.x,b.x,c.x)/cell), maxX = Math.floor(Math.max(a.x,b.x,c.x)/cell);
      const minZ = Math.floor(Math.min(a.z,b.z,c.z)/cell), maxZ = Math.floor(Math.max(a.z,b.z,c.z)/cell);
      if ((maxX-minX+1)*(maxZ-minZ+1) > 100000) throw new Error("Terreno GLB fora de escala.");
      for (let x=minX;x<=maxX;x++) for (let z=minZ;z<=maxZ;z++) {
        const key = `${x}/${z}`; if (!bins.has(key)) bins.set(key, []); bins.get(key).push(id);
      }
    }
  });
  if (!triangles.length) return null;
  const sourceTriangles=triangles.slice(),upper=[];
  for(let id=0;id<sourceTriangles.length;id++) {
    const face=sourceTriangles[id],covered=[],near=new Set();
    for(let x=Math.floor(Math.min(...face.map(p=>p.x))/cell);x<=Math.floor(Math.max(...face.map(p=>p.x))/cell);x++)
      for(let z=Math.floor(Math.min(...face.map(p=>p.z))/cell);z<=Math.floor(Math.max(...face.map(p=>p.z))/cell);z++)
        for(const n of bins.get(`${x}/${z}`)||[])if(n!==id)near.add(n);
    for(const n of near) {
      const other=sourceTriangles[n],overlap=clipFace(face,other);
      if(overlap.length<3)continue;
      const delta=p=>faceHeight(other,p.x,p.z)-faceHeight(face,p.x,p.z);
      const coplanar=overlap.every(p=>Math.abs(delta(p))<1e-5);
      const part=coplanar?(n<id?overlap:[]):higherPart(overlap,delta);
      if(part.length>=3)covered.push({outer:[...part,part[0]],holes:[]});
    }
    const original={outer:[...face,face[0]],holes:[]};
    if(!covered.length) {upper.push(face);continue;}
    for(const polygon of differencePolygons([original],covered)) {
      const cut=polygonGeometry(polygon),p=cut.attributes.position;
      for(let i=0;i<p.count;i+=3) {
        const tri=[0,1,2].map(k=>({x:p.getX(i+k),z:p.getZ(i+k)}));
        if(crossXZ(...tri)<=0||!resolvedFace(tri))continue;
        upper.push(tri.map(v=>new THREE.Vector3(v.x,faceHeight(face,v.x,v.z),v.z)));
      }
      cut.dispose();
    }
  }
  triangles.splice(0,triangles.length,...upper);positions.length=0;polygons.length=0;bins.clear();
  triangles.forEach((t,id)=>{
    t.forEach(v=>positions.push(v.x,v.y,v.z));polygons.push({outer:[...t,t[0]],holes:[]});
    for(let x=Math.floor(Math.min(...t.map(p=>p.x))/cell);x<=Math.floor(Math.max(...t.map(p=>p.x))/cell);x++)
      for(let z=Math.floor(Math.min(...t.map(p=>p.z))/cell);z<=Math.floor(Math.max(...t.map(p=>p.z))/cell);z++) {
        const key=`${x}/${z}`;if(!bins.has(key))bins.set(key,[]);bins.get(key).push(id);
      }
  });
  // One sweep across all projected top faces, instead of repeated growing unions.
  const footprint = unionPolygons(polygons.slice(0,1),polygons.slice(1));
  const sampleFace = (x,z) => {
    let result = null;
    for (const id of bins.get(`${Math.floor(x/cell)}/${Math.floor(z/cell)}`) || []) {
      const [a,b,c] = triangles[id], d = (b.z-c.z)*(a.x-c.x)+(c.x-b.x)*(a.z-c.z);
      const u = ((b.z-c.z)*(x-c.x)+(c.x-b.x)*(z-c.z))/d;
      const v = ((c.z-a.z)*(x-c.x)+(a.x-c.x)*(z-c.z))/d, w = 1-u-v;
      const height=u*a.y+v*b.y+w*c.y;
      if (Math.min(u,v,w) >= -1e-7&&(!result||height>result.height))result={height,face:triangles[id]};
    }
    return result;
  };
  const sample=(x,z)=>sampleFace(x,z)?.height??null;
  const boundaryFace=(x,z)=>{
    const exact=sampleFace(x,z);if(exact)return exact.face;
    let best=null;
    const near=new Set();
    for(let i=Math.floor((x-.001)/cell);i<=Math.floor((x+.001)/cell);i++)
      for(let j=Math.floor((z-.001)/cell);j<=Math.floor((z+.001)/cell);j++)for(const id of bins.get(`${i}/${j}`)||[])near.add(id);
    for(const id of near) {
      const face=triangles[id];let distance=Infinity;
      for(let i=0;i<3;i++) {
        const a=face[i],b=face[(i+1)%3],dx=b.x-a.x,dz=b.z-a.z;
        const t=Math.max(0,Math.min(1,((x-a.x)*dx+(z-a.z)*dz)/(dx*dx+dz*dz)));
        distance=Math.min(distance,Math.hypot(x-a.x-t*dx,z-a.z-t*dz));
      }
      const height=faceHeight(face,x,z);
      if(distance<.001&&(!best||distance<best.distance-1e-7||(Math.abs(distance-best.distance)<1e-7&&height>best.height)))best={face,distance,height};
    }
    if(!best)throw new Error('Borda do terreno GLB sem face de apoio.');
    return best.face;
  };
  const edges = [];
  for (const p of footprint) for (const ring of [p.outer,...p.holes])
    for (let i=1;i<ring.length;i++) {
      const a=ring[i-1],b=ring[i],dx=b.x-a.x,dz=b.z-a.z,length=dx*dx+dz*dz,cuts=[0,1];
      for(const tri of triangles)for(const p of tri) {
        const t=((p.x-a.x)*dx+(p.z-a.z)*dz)/length;
        if(t>0&&t<1&&Math.hypot(p.x-a.x-t*dx,p.z-a.z-t*dz)<.0002)cuts.push(t);
      }
      cuts.sort((a,b)=>a-b);
      for(let k=1;k<cuts.length;k++) {
        if((cuts[k]-cuts[k-1])*Math.sqrt(length)<1e-5)continue;
        const at=t=>({x:a.x+t*dx,z:a.z+t*dz}),start=at(cuts[k-1]),end=at(cuts[k]),mid=at((cuts[k-1]+cuts[k])/2);
        const face=boundaryFace(mid.x,mid.z);
        edges.push({a:start,b:end,ay:faceHeight(face,start.x,start.z),by:faceHeight(face,end.x,end.z)});
      }
    }
  const extent=triangles.reduce((b,t)=>({minX:Math.min(b.minX,...t.map(p=>p.x)),minZ:Math.min(b.minZ,...t.map(p=>p.z)),
    maxX:Math.max(b.maxX,...t.map(p=>p.x)),maxZ:Math.max(b.maxZ,...t.map(p=>p.z))}),{minX:Infinity,minZ:Infinity,maxX:-Infinity,maxZ:-Infinity});
  const candidates=(minX,minZ,maxX,maxZ)=>{
    minX=Math.max(minX,extent.minX);minZ=Math.max(minZ,extent.minZ);maxX=Math.min(maxX,extent.maxX);maxZ=Math.min(maxZ,extent.maxZ);
    if(minX>maxX||minZ>maxZ)return [];
    const found=new Set();
    for(let x=Math.floor(minX/cell);x<=Math.floor(maxX/cell);x++)for(let z=Math.floor(minZ/cell);z<=Math.floor(maxZ/cell);z++)
      for(const id of bins.get(`${x}/${z}`)||[])found.add(id);
    return [...found].map(id=>triangles[id]);
  };
  return {footprint, edges, sample, positions, candidates,
    min: triangles.reduce((m,t)=>Math.min(m,...t.map(p=>p.y)),Infinity),
    max: triangles.reduce((m,t)=>Math.max(m,...t.map(p=>p.y)),-Infinity), triangles:triangles.length,sourceTriangles:sourceTriangles.length};
}
