import * as THREE from "three";
import { polygonGeometry } from "./feature-builder.js?v=20261103-boundary-v4";
import { differencePolygons, unionPolygons, intersectPolygons, polygonArea } from "./environment-clip.js?v=20261104-surface-v3";
import { pointInRing } from "./geo-coordinates.js?v=20261103-boundary-v4";
import { ribbonGeometry } from "./road-builder.js?v=20261104-terrain-v1";
import { SurfaceMesh, crossXZ, clipFace, faceHeight, resolvedFace, cleanSurfaceRing, improveSurfaceTriangles } from "./surface-mesh.js?v=20261104-surface-v3";

const smooth = t => { t = Math.max(0,Math.min(1,t)); return t*t*(3-2*t); };
export function closestEdge(x,z,edges) {
  let result = {distance:Infinity, height:0};
  for (const {a,b,ay=0,by=0} of edges) {
    const dx=b.x-a.x,dz=b.z-a.z, length=dx*dx+dz*dz;
    const t=length ? Math.max(0,Math.min(1,((x-a.x)*dx+(z-a.z)*dz)/length)) : 0;
    const distance=Math.hypot(x-a.x-t*dx,z-a.z-t*dz);
    if(distance<result.distance) result={distance,height:ay+(by-ay)*t};
  }
  return result;
}
function axis(extent) {
  const positive=[0];
  while(positive.at(-1)<extent) {
    const p=positive.at(-1), step=6+Math.min(30,p*.035);
    positive.push(Math.min(extent,p+step));
  }
  return [...positive.slice(1).reverse().map(x=>-x),...positive];
}
function interval(axis,value) {
  let lo=0,hi=axis.length-1;
  while(hi-lo>1) { const mid=(lo+hi)>>1; if(axis[mid]<=value)lo=mid;else hi=mid; }
  return Math.min(axis.length-2,lo);
}
function clip(poly, distance) {
  const out=[];
  for(let i=0;i<poly.length;i++) {
    const a=poly[i],b=poly[(i+1)%poly.length],da=distance(a),db=distance(b);
    if(da>=-1e-8)out.push(a);
    if((da>0&&db<0)||(da<0&&db>0)) {
      const t=da/(da-db);out.push({x:a.x+(b.x-a.x)*t,z:a.z+(b.z-a.z)*t});
    }
  }
  return out;
}

/** Single continuous, nonuniform lattice: 6 m near the site, up to 36 m far away.
 * Every draped surface is cut by the SAME cells/diagonals, so interpolation cannot
 * leave vegetation/roads floating above a different terrain triangulation. */
export class TerrainSurface {
  constructor({dem,bounds,ground=null,site,coastLines=[],deferGrid=false}) {
    Object.assign(this,{dem,bounds,ground,site});
    this.reference=dem.reference;
    this.referenceModel=ground?.sample(0,0) ?? closestEdge(0,0,ground?.edges || []).height;
    this.seaLevel=this.referenceModel-this.reference;
    this.coastEdges=coastLines.flatMap(line=>line.slice(1).map((b,i)=>({a:line[i],b})));
    const extent=Math.max(...bounds.polygon.outer.map(p=>Math.max(Math.abs(p.x),Math.abs(p.z))));
    this.axis=axis(extent); const n=this.axis.length;
    this.heights=new Float32Array(n*n);
    if(!deferGrid)this.rebuild();
  }
  rebuild() {
    const n=this.axis.length;
    for(let j=0;j<n;j++)for(let i=0;i<n;i++)this.heights[j*n+i]=this.rawHeight(this.axis[i],this.axis[j]);
  }
  async rebuildAsync(signal) {
    const n=this.axis.length;let deadline=performance.now()+12;
    for(let j=0;j<n;j++) {
      if(signal?.aborted)throw new DOMException("Cancelado","AbortError");
      for(let i=0;i<n;i++)this.heights[j*n+i]=this.rawHeight(this.axis[i],this.axis[j]);
      if(performance.now()>deadline) {
        await new Promise(resolve=>setTimeout(resolve,0));deadline=performance.now()+12;
      }
    }
  }
  prepareWater(features,{rebuild=true}={}) {
    this.mesh=null;this.waterLevels=null;
    this.water=new Map();this.waterControls=[];this.waterFootprint=[];
    const allControls=[];
    for(const feature of features) {
      const controls=[];let footprint=[];
      for(const polygon of feature.polygons) {
        const levels=polygon.outer.map(p=>this.rawHeight(p.x,p.z)).sort((a,b)=>a-b);
        const level=levels[Math.floor((levels.length-1)*.2)],edges=polygon.outer.slice(1).map((b,i)=>({a:polygon.outer[i],b}));
        controls.push({polygon,level,edges,footprint:[polygon]});
        footprint.push(polygon);
      }
      if(feature.line) {
        const points=[];
        for(let i=1;i<feature.line.length;i++) {
          const a=feature.line[i-1],b=feature.line[i],steps=Math.max(1,Math.ceil(Math.hypot(b.x-a.x,b.z-a.z)/12));
          for(let k=0;k<steps;k++)points.push({x:a.x+(b.x-a.x)*k/steps,z:a.z+(b.z-a.z)*k/steps});
        }
        points.push(feature.line.at(-1));
        const levels=points.map(p=>this.rawHeight(p.x,p.z));
        const smoothed=levels.map((_,i)=>{
          const window=levels.slice(Math.max(0,i-2),i+3).sort((a,b)=>a-b);return window[Math.floor(window.length/2)];
        });
        if(smoothed[0]<smoothed.at(-1)) {points.reverse();smoothed.reverse();}
        for(let i=1;i<smoothed.length;i++)smoothed[i]=Math.min(smoothed[i-1],smoothed[i]);
        const edges=points.slice(1).map((b,i)=>({a:points[i],b,ay:smoothed[i],by:smoothed[i+1]}));
        controls.push({edges,width:({river:12,stream:3,canal:6,drain:1.5,ditch:1}[feature.waterway]||3)/2});
        const ribbon=ribbonGeometry(feature.line,controls.at(-1).width*2),p=ribbon?.attributes.position;
        if(p) {
          const polygons=[];
          for(let i=0;i<p.count;i+=3) {
            const ring=[0,1,2,0].map(k=>({x:p.getX(i+k),z:p.getZ(i+k)}));
            polygons.push({outer:ring,holes:[]});
          }
          footprint.push(...polygons);
          controls.at(-1).footprint=polygons;
          ribbon.dispose();
        }
      }
      for(const control of controls) {
        control.footprint=unionPolygons((control.footprint||[]).slice(0,1),(control.footprint||[]).slice(1));
        control.bankEdges=control.footprint.flatMap(p=>[p.outer,...p.holes].flatMap(r=>r.slice(1).map((b,i)=>({a:r[i],b}))));
        const points=control.polygon?.outer || control.edges.flatMap(e=>[e.a,e.b]);
        const margin=(control.width||0)+10;
        control.box={minX:Math.min(...points.map(p=>p.x))-margin,maxX:Math.max(...points.map(p=>p.x))+margin,
          minZ:Math.min(...points.map(p=>p.z))-margin,maxZ:Math.max(...points.map(p=>p.z))+margin};
        allControls.push(control);
      }
      this.waterFootprint.push(...footprint);
      this.water.set(feature,controls);
    }
    if(this.waterFootprint.length>1)this.waterFootprint=unionPolygons(this.waterFootprint.slice(0,1),this.waterFootprint.slice(1));
    // Disconnected lakes retain their own horizontal level. Only joined water
    // systems share the river field; proximity alone is not connectivity.
    this.horizontalWater=[];
    for(const polygon of this.waterFootprint) {
      const xs=polygon.outer.map(p=>p.x),zs=polygon.outer.map(p=>p.z);
      polygon.box={minX:Math.min(...xs),maxX:Math.max(...xs),minZ:Math.min(...zs),maxZ:Math.max(...zs)};
      const matches=allControls.filter(c=>c.box.minX<=polygon.box.maxX&&c.box.maxX>=polygon.box.minX&&c.box.minZ<=polygon.box.maxZ&&c.box.maxZ>=polygon.box.minZ)
        .filter(c=>intersectPolygons([polygon],c.footprint||[]).reduce((sum,p)=>sum+polygonArea(p),0)>1e-6);
      if(matches.length&&matches.every(c=>c.polygon&&Math.abs(c.level-matches[0].level)<1e-6)) {
        polygon.level=matches[0].level;this.horizontalWater.push(polygon);
        matches.forEach(c=>c.horizontalLevel=polygon.level);
      }
    }
    this.waterControls=allControls;
    // A single piecewise-planar water field for every connected/overlapping
    // feature. Evaluating nearest-segment profiles independently at newly cut
    // vertices used to give each triangulation a different surface.
    const n=this.axis.length;
    if(allControls.length) {
      this.waterLevels=new Float32Array(n*n);
      for(let j=0;j<n;j++)for(let i=0;i<n;i++)this.waterLevels[j*n+i]=this.profileWaterHeight(this.axis[i],this.axis[j]);
    }
    if(rebuild)this.rebuild();
  }
  waterControl(control,x,z) {
    const b=control.box;
    if(b&&(x<b.minX||x>b.maxX||z<b.minZ||z>b.maxZ))return null;
    const near=closestEdge(x,z,control.edges);
    const inside=control.footprint.some(p=>pointInRing({x,z},p.outer)&&!p.holes.some(r=>pointInRing({x,z},r)));
    return {level:control.level ?? near.height,horizontalLevel:control.horizontalLevel,distance:inside?0:closestEdge(x,z,control.bankEdges).distance};
  }
  waterHeight(feature,x,z) {
    if(feature?.level!=null)return feature.level;
    for(const polygon of this.horizontalWater||[]) {
      const b=polygon.box;
      if(x<b.minX-.0002||x>b.maxX+.0002||z<b.minZ-.0002||z>b.maxZ+.0002)continue;
      if((pointInRing({x,z},polygon.outer)||closestEdge(x,z,polygon.outer.slice(1).map((b,i)=>({a:polygon.outer[i],b}))).distance<.0002)&&!polygon.holes.some(r=>pointInRing({x,z},r)))return polygon.level;
    }
    return this.waterLevels ? this.latticeHeight(this.waterLevels,x,z) : this.profileWaterHeight(x,z);
  }
  profileWaterHeight(x,z) {
    let best=null;
    for(const control of this.waterControls||[]) {
      // Outside a control's bank box, still select the nearest profile for grid
      // interpolation; a thin channel may have no lattice node in its box.
      const b=control.box;
      if(best&&b&&Math.hypot(Math.max(b.minX-x,0,x-b.maxX),Math.max(b.minZ-z,0,z-b.maxZ))>best.distance)continue;
      const near=closestEdge(x,z,control.edges);
      const inside=control.polygon&&pointInRing({x,z},control.polygon.outer)&&!control.polygon.holes.some(r=>pointInRing({x,z},r));
      const at={level:control.level??near.height,distance:inside?0:Math.max(0,near.distance-(control.width||0))};
      if(!best||at.distance<best.distance-1e-8||(Math.abs(at.distance-best.distance)<1e-8&&at.level<best.level))best=at;
    }
    return best?.level ?? this.rawHeight(x,z);
  }
  rawHeight(x,z) {
    // Nodes of the rotated square's AABB outside the acquisition area are clamped.
    const en=this.bounds.toEN({x,z}), p=this.bounds.toLocal({x:Math.max(-1000,Math.min(1000,en.x)),z:Math.max(-1000,Math.min(1000,en.z))});
    let y=this.dem.sample(p.x,p.z)-this.reference+this.referenceModel;
    const coast=closestEdge(x,z,this.coastEdges);
    if(coast.distance<45)y=this.seaLevel+(y-this.seaLevel)*smooth(coast.distance/45);
    if(this.ground) {
      const inside=this.ground.sample(x,z);
      if(inside!==null)return inside;
      const edge=closestEdge(x,z,this.ground.edges);
      if(edge.distance<45)y=edge.height+(y-edge.height)*smooth(edge.distance/45);
    } else {
      const distance=Math.hypot(Math.max(this.site.minX-x,0,x-this.site.maxX),Math.max(this.site.minZ-z,0,z-this.site.maxZ));
      y*=smooth(distance/45); // conservative protected footprint when no ground was identified
    }
    return y;
  }
  sample(x,z,includeModel=true) {
    const plane=this.mesh?.sample(x,z);if(plane!=null)return plane;
    // Preserve the actual nonplanar GLB surface at the join, including its boundary.
    const model=includeModel?this.ground?.sample(x,z):null; if(model!=null)return model;
    let y=this.latticeHeight(this.heights,x,z);
    const coast=closestEdge(x,z,this.coastEdges);
    if(coast.distance<45)y=this.seaLevel+(y-this.seaLevel)*smooth(coast.distance/45);
    // Apply a continuous bank constraint to the interpolated DEM. The former
    // 1 cm snap produced metre-scale changes between almost coincident cut
    // vertices, creating steep spikes even on an otherwise smooth DEM.
    let bank=null;
    for(const control of this.waterControls||[]) {
      const at=this.waterControl(control,x,z);
      if(at&&(!bank||at.distance<bank.distance))bank=at;
    }
    if(bank&&bank.distance<10) {
      const bed=(bank.horizontalLevel??this.waterHeight(null,x,z))-.12;
      y=bed+(y-bed)*smooth(bank.distance/10);
    }
    if(this.ground) {
      const edge=closestEdge(x,z,this.ground.edges);
      if(edge.distance<45)y=edge.height+(y-edge.height)*smooth(edge.distance/45);
    }
    return y;
  }
  latticeHeight(heights,x,z) {
    const a=this.axis,n=a.length,i=interval(a,x),j=interval(a,z);
    const u=Math.max(0,Math.min(1,(x-a[i])/(a[i+1]-a[i]))),v=Math.max(0,Math.min(1,(z-a[j])/(a[j+1]-a[j])));
    const h00=heights[j*n+i],h10=heights[j*n+i+1],h01=heights[(j+1)*n+i],h11=heights[(j+1)*n+i+1];
    return u+v<=1 ? h00+(h10-h00)*u+(h01-h00)*v : h11+(h01-h11)*(1-u)+(h10-h11)*(1-v);
  }
  /** CPU-only cutting; the iterator also permits cooperative ground construction. */
  *drapeIterator(geometry,offset=0,height=null,projectModel=true,canonical=false) {
    const p=geometry.attributes.position,index=geometry.index,axis=this.axis,cells=new Map();let values=[];
    // DEM polygons already exclude the GLB. Float32 rounding of their boundary
    // must not snap an exterior vertex onto a different, higher GLB face.
    const sample=height||((x,z)=>this.sample(x,z,!canonical));
    const emitTriangle=(a,b,c,at=sample)=>{
      // Validate AFTER Float32 conversion: round-off can collapse a clipped sliver.
      const face=[a,b,c].map(p=>({x:Math.fround(p.x),y:Math.fround(at(p.x,p.z)+offset),z:Math.fround(p.z)}));
      if(!face.every(p=>[p.x,p.y,p.z].every(Number.isFinite)))throw new Error("Coordenada de superfície inválida.");
      const area=crossXZ(...face);if(!resolvedFace(face))return;
      if(area<0)[face[1],face[2]]=[face[2],face[1]];
      for(const p of face)values.push(p.x,p.y,p.z);
    };
    const emit=(poly,at)=>{for(let k=1;k<poly.length-1;k++)emitTriangle(poly[0],poly[k],poly[k+1],at);};
    const lattice=tri=>{
      const i0=interval(axis,Math.min(...tri.map(p=>p.x))),i1=interval(axis,Math.max(...tri.map(p=>p.x)));
      const j0=interval(axis,Math.min(...tri.map(p=>p.z))),j1=interval(axis,Math.max(...tri.map(p=>p.z)));
      for(let j=j0;j<=j1;j++) {
        const row=clip(clip(tri,p=>p.z-axis[j]),p=>axis[j+1]-p.z);
        if(row.length<3)continue;
        const start=Math.max(i0,interval(axis,Math.min(...row.map(p=>p.x)))),end=Math.min(i1,interval(axis,Math.max(...row.map(p=>p.x))));
        for(let i=start;i<=end;i++) {
          const cell=clip(clip(row,p=>p.x-axis[i]),p=>axis[i+1]-p.x);
          if(cell.length<3)continue;
          const diagonal=p=>1-(p.x-axis[i])/(axis[i+1]-axis[i])-(p.z-axis[j])/(axis[j+1]-axis[j]);
          for(const [half,part] of [clip(cell,diagonal),clip(cell,p=>-diagonal(p))].entries()) {
            if(!canonical)emit(part);
            else if(part.length>=3) {
              const key=`${i}/${j}/${half}`;
              if(!cells.has(key))cells.set(key,[]);
              cells.get(key).push({outer:[...part,part[0]],holes:[]});
            }
          }
        }
      }
    };
    for(let t=0;t<(index?.count??p.count);t+=3) {
      const tri=[0,1,2].map(k=>{const id=index?index.getX(t+k):t+k;if(id>=p.count||id<0)throw new Error("Índice de superfície inválido.");return {x:p.getX(id),z:p.getZ(id)};});
      if(!tri.every(p=>[p.x,p.z].every(Number.isFinite)))throw new Error("Coordenada de superfície inválida.");
      if(Math.abs(crossXZ(...tri))<=1e-9) {yield;continue;}
      if(this.mesh&&projectModel&&!height) {
        // Intersect the actual ground planes, including channel banks and GLB
        // grade breaks. A second independent tessellation is not equivalent.
        for(const face of this.mesh.candidates(Math.min(...tri.map(p=>p.x)),Math.min(...tri.map(p=>p.z)),Math.max(...tri.map(p=>p.x)),Math.max(...tri.map(p=>p.z))))
          emit(clipFace(tri,face),(x,z)=>faceHeight(face,x,z));
        yield;continue;
      }
      const candidates=projectModel&&this.ground ? this.ground.candidates(Math.min(...tri.map(p=>p.x)),Math.min(...tri.map(p=>p.z)),Math.max(...tri.map(p=>p.x)),Math.max(...tri.map(p=>p.z))) : [];
      if(!candidates.length) {lattice(tri);yield;continue;}
      // Cut overlays by the actual GLB top-face edges too. A lattice alone can
      // bridge across a kerb/grade break and intersect the source surface.
      for(const face of candidates) {
        const area=(face[1].x-face[0].x)*(face[2].z-face[0].z)-(face[1].z-face[0].z)*(face[2].x-face[0].x),sign=Math.sign(area);
        let part=tri;
        for(let i=0;i<3;i++) {
          const a=face[i],b=face[(i+1)%3];
          part=clip(part,p=>sign*((b.x-a.x)*(p.z-a.z)-(b.z-a.z)*(p.x-a.x)));
        }
        if(part.length>=3)emit(part);
      }
      const outside=differencePolygons([{outer:[...tri,tri[0]],holes:[]}],this.ground.footprint);
      for(const polygon of outside) {
        const cut=polygonGeometry(polygon),v=cut.attributes.position;
        for(let i=0;i<v.count;i+=3)lattice([0,1,2].map(k=>({x:v.getX(i+k),z:v.getZ(i+k)})));
        cut.dispose();
      }
      yield;
    }
    // Remove the source polygon's artificial diagonals BEFORE assigning a
    // nonlinear bank height. Otherwise long, thin remnants of those diagonals
    // become needle planes even though each vertex/triangle is valid.
    for(const parts of cells.values()) {
      const polygons=unionPolygons(parts.slice(0,1),parts.slice(1));
      for(const polygon of polygons) {
        const cut=polygonGeometry({outer:cleanSurfaceRing(polygon.outer),holes:polygon.holes.map(cleanSurfaceRing)});
        for(const tri of improveSurfaceTriangles(cut))emitTriangle(...tri);
        cut.dispose();
      }
      yield;
    }
    geometry.dispose();
    const result=new THREE.BufferGeometry();result.setAttribute("position",new THREE.Float32BufferAttribute(values,3));result.computeVertexNormals();return result;
  }
  drape(...args) {
    const iterator=this.drapeIterator(...args);let result;
    do {result=iterator.next();}while(!result.done);
    return result.value;
  }
  async geometryAsync(polygon,signal) {
    const geometry=polygonGeometry(polygon),iterator=this.drapeIterator(geometry,0,undefined,false,true);
    let result,deadline=performance.now()+12;
    try {
      do {
        if(signal?.aborted)throw new DOMException("Cancelado","AbortError");
        result=iterator.next();
        if(!result.done&&performance.now()>deadline) {
          await new Promise(resolve=>setTimeout(resolve,0));deadline=performance.now()+12;
        }
      }while(!result.done);
      return result.value;
    } finally {iterator.return();if(!result?.done)geometry.dispose();}
  }
  groundPolygons(land) {
    let polygons=this.ground ? differencePolygons(land,this.ground.footprint,this.bounds) : land;
    if(this.waterFootprint?.length)polygons=differencePolygons(polygons,this.waterFootprint,this.bounds);
    return polygons;
  }
  installMesh(geometries) {this.mesh=new SurfaceMesh(geometries);}
  vegetationPolygons(polygons) {
    return this.waterFootprint?.length ? differencePolygons(polygons,this.waterFootprint,this.bounds) : polygons;
  }
  geometry(polygon,offset=0,height) { return this.drape(polygonGeometry(polygon),offset,height,false,!height); }
  densifyRing(ring) {
    return ring.flatMap((a,i)=>{
      const b=ring[(i+1)%ring.length],steps=Math.max(1,Math.ceil(Math.hypot(b.x-a.x,b.z-a.z)/6));
      return Array.from({length:steps},(_,k)=>({x:a.x+(b.x-a.x)*k/steps,z:a.z+(b.z-a.z)*k/steps}));
    });
  }
  /** Rigid roofs, vertical walls and a foundation reaching each sampled ground point. */
  building(geometry,polygon,buildingHeight) {
    const boundary=polygon.outer.flatMap((a,i,ring)=>{
      const b=ring[(i+1)%ring.length],steps=Math.max(1,Math.ceil(Math.hypot(b.x-a.x,b.z-a.z)/6));
      return Array.from({length:steps},(_,k)=>this.sample(a.x+(b.x-a.x)*k/steps,a.z+(b.z-a.z)*k/steps));
    });
    const top=Math.max(...boundary),p=geometry.attributes.position;
    for(let i=0;i<p.count;i++)p.setY(i,p.getY(i)>buildingHeight/2?top+buildingHeight:this.sample(p.getX(i),p.getZ(i))-.15);
    geometry.computeVertexNormals();return geometry;
  }
}
