// CPU spatial index of the actual rendered planes. No DEM re-sampling here.
export const crossXZ = (a,b,c) => (b.z-a.z)*(c.x-a.x)-(b.x-a.x)*(c.z-a.z);
export function resolvedFace(face) {
  const longest=Math.max(...face.map((a,i)=>{const b=face[(i+1)%3];return Math.hypot(b.x-a.x,b.z-a.z);}));
  const precision=Math.max(1e-7,...face.flatMap(p=>[Math.abs(p.x),Math.abs(p.z)]).map(v=>v*2**-23));
  return Math.abs(crossXZ(...face))>longest*precision;
}
/** Remove only sub-Float32 deviations on otherwise collinear clipped edges. */
export function cleanSurfaceRing(ring) {
  const points=ring.map(p=>({x:Math.fround(p.x),z:Math.fround(p.z)}));
  if(points.length>1&&Math.hypot(points[0].x-points.at(-1).x,points[0].z-points.at(-1).z)<1e-6)points.pop();
  let changed=true;
  while(changed&&points.length>3) {
    changed=false;
    for(let i=0;i<points.length;i++) {
      const a=points[(i+points.length-1)%points.length],b=points[i],c=points[(i+1)%points.length];
      const length=Math.hypot(c.x-a.x,c.z-a.z),epsilon=Math.max(1e-5,...[a,b,c].flatMap(p=>[Math.abs(p.x),Math.abs(p.z)]).map(v=>v*2**-22));
      if(length&&Math.abs(crossXZ(a,b,c))<=length*epsilon&&(b.x-a.x)*(b.x-c.x)+(b.z-a.z)*(b.z-c.z)<=epsilon*epsilon) {
        points.splice(i,1);changed=true;break;
      }
    }
  }
  if(!points.length)return [];
  const area=points.reduce((sum,a,i)=>{const b=points[(i+1)%points.length];return sum+a.x*b.z-b.x*a.z;},0);
  if(area>0)points.reverse();
  let start=0;
  for(let i=1;i<points.length;i++)if(points[i].x<points[start].x||(points[i].x===points[start].x&&points[i].z<points[start].z))start=i;
  const ordered=[...points.slice(start),...points.slice(0,start)];
  return [...ordered,ordered[0]];
}

/** Constrained edge flips inside one clipped cell. Preserve boundary/hole edges,
 * but remove Earcut diagonals that create needle triangles along a curved bank. */
export function improveSurfaceTriangles(geometry) {
  const p=geometry.attributes.position,vertices=[],ids=new Map(),triangles=[];
  for(let i=0;i<p.count;i+=3)triangles.push([0,1,2].map(k=>{
    const point={x:p.getX(i+k),z:p.getZ(i+k)},key=`${point.x}/${point.z}`;
    if(!ids.has(key)){ids.set(key,vertices.length);vertices.push(point);}return ids.get(key);
  }));
  const orient=t=>{if(crossXZ(...t.map(i=>vertices[i]))<0)[t[1],t[2]]=[t[2],t[1]];return t;};
  triangles.forEach(orient);
  const quality=t=>{
    const f=t.map(i=>vertices[i]);
    return Math.abs(crossXZ(...f))/Math.max(...f.map((a,i)=>{const b=f[(i+1)%3];return (b.x-a.x)**2+(b.z-a.z)**2;}));
  };
  for(let pass=0;pass<triangles.length*4;pass++) {
    const edges=new Map();let changed=false;
    triangles.forEach((tri,id)=>tri.forEach((a,k)=>{
      const b=tri[(k+1)%3],key=a<b?`${a}/${b}`:`${b}/${a}`;
      if(!edges.has(key))edges.set(key,[]);edges.get(key).push({id,a,b,other:tri[(k+2)%3]});
    }));
    for(const pair of edges.values()) {
      if(pair.length!==2)continue;
      const [one,two]=pair,a=vertices[one.a],b=vertices[one.b],c=vertices[one.other],d=vertices[two.other];
      if(crossXZ(c,d,a)*crossXZ(c,d,b)>=-1e-12)continue;
      const first=orient([one.other,two.other,one.a]),second=orient([two.other,one.other,one.b]);
      const before=Math.min(quality(triangles[one.id]),quality(triangles[two.id])),after=Math.min(quality(first),quality(second));
      if(after>before+1e-10) {
        triangles[one.id]=first;triangles[two.id]=second;changed=true;break;
      }
    }
    if(!changed)break;
  }
  return triangles.map(t=>t.map(i=>vertices[i]));
}

export function faceHeight(face,x,z) {
  const [a,b,c]=face,d=crossXZ(a,b,c);
  const u=crossXZ({x,z},b,c)/d,v=crossXZ(a,{x,z},c)/d;
  return u*a.y+v*b.y+(1-u-v)*c.y;
}
export function clipFace(poly,face) {
  const sign=Math.sign(crossXZ(...face));
  for(let i=0;i<3&&poly.length;i++) {
    const a=face[i],b=face[(i+1)%3],out=[];
    const distance=p=>sign*crossXZ(a,b,p);
    for(let j=0;j<poly.length;j++) {
      const p=poly[j],q=poly[(j+1)%poly.length],dp=distance(p),dq=distance(q);
      if(dp>=0)out.push(p);
      if((dp>0&&dq<0)||(dp<0&&dq>0)) {
        const t=dp/(dp-dq);out.push({x:p.x+(q.x-p.x)*t,z:p.z+(q.z-p.z)*t});
      }
    }
    poly=out;
  }
  return poly;
}
export class SurfaceMesh {
  constructor(geometries,cell=36) {
    this.cell=cell;this.bins=new Map();this.faces=[];
    this.extent={minX:Infinity,minZ:Infinity,maxX:-Infinity,maxZ:-Infinity};
    for(const geometry of geometries) {
      const p=geometry.attributes.position,index=geometry.index;
      for(let i=0;i<(index?.count??p.count);i+=3) {
        const face=[0,1,2].map(k=>{
          const n=index?index.getX(i+k):i+k;return {x:p.getX(n),y:p.getY(n),z:p.getZ(n)};
        });
        if(!face.every(p=>[p.x,p.y,p.z].every(Number.isFinite))||crossXZ(...face)<=0||!resolvedFace(face))continue;
        const xs=face.map(p=>p.x),zs=face.map(p=>p.z);
        face.box={minX:Math.min(...xs),maxX:Math.max(...xs),minZ:Math.min(...zs),maxZ:Math.max(...zs)};
        for(const key of ['minX','minZ'])this.extent[key]=Math.min(this.extent[key],face.box[key]);
        for(const key of ['maxX','maxZ'])this.extent[key]=Math.max(this.extent[key],face.box[key]);
        const id=this.faces.length;this.faces.push(face);
        for(let x=Math.floor(Math.min(...xs)/cell);x<=Math.floor(Math.max(...xs)/cell);x++)
          for(let z=Math.floor(Math.min(...zs)/cell);z<=Math.floor(Math.max(...zs)/cell);z++) {
            const key=`${x}/${z}`;if(!this.bins.has(key))this.bins.set(key,[]);this.bins.get(key).push(id);
          }
      }
    }
  }
  candidates(minX,minZ,maxX,maxZ) {
    minX=Math.max(minX,this.extent.minX);minZ=Math.max(minZ,this.extent.minZ);
    maxX=Math.min(maxX,this.extent.maxX);maxZ=Math.min(maxZ,this.extent.maxZ);
    if(minX>maxX||minZ>maxZ)return [];
    const ids=new Set(),c=this.cell;
    for(let x=Math.floor(minX/c);x<=Math.floor(maxX/c);x++)for(let z=Math.floor(minZ/c);z<=Math.floor(maxZ/c);z++)
      for(const id of this.bins.get(`${x}/${z}`)||[])ids.add(id);
    return [...ids].map(id=>this.faces[id]).filter(f=>f.box.minX<=maxX&&f.box.maxX>=minX&&f.box.minZ<=maxZ&&f.box.maxZ>=minZ);
  }
  sample(x,z) {
    let result=null,near=null;
    for(const face of this.candidates(x-.0002,z-.0002,x+.0002,z+.0002)) {
      const p={x,z};
      if(face.every((a,i)=>crossXZ(a,face[(i+1)%3],p)>=0))
        result=Math.max(result??-Infinity,faceHeight(face,x,z));
      else if(result==null)for(let i=0;i<3;i++) {
        // Round-off recovery only if no face contains the point. Clamp to an
        // edge; never extrapolate a thin face using a distance-based epsilon.
        const a=face[i],b=face[(i+1)%3],dx=b.x-a.x,dz=b.z-a.z;
        const t=Math.max(0,Math.min(1,((x-a.x)*dx+(z-a.z)*dz)/(dx*dx+dz*dz)));
        const distance=Math.hypot(x-a.x-t*dx,z-a.z-t*dz);
        if(distance<=.0002&&(!near||distance<near.distance))near={distance,height:a.y+t*(b.y-a.y)};
      }
    }
    return result??near?.height??null;
  }
}

/** Compare interiors/edges of a draped layer with the indexed ground planes.
 * Vertex-only checks miss the bank bridges that caused the original defect. */
export function auditSurfaceProjection(geometry,surface,offset=0,maxTriangles=Infinity) {
  const p=geometry.attributes.position;
  let maximumErrorM=0,missingSamples=0,sampledTriangles=0,worst=null;
  const step=Math.max(1,Math.ceil(p.count/3/maxTriangles))*3;
  for(let i=0;i<p.count;i+=step) {
    sampledTriangles++;
    for(const weights of [[1/3,1/3,1/3],[.5,.5,0],[0,.5,.5],[.5,0,.5]]) {
    const at=axis=>weights.reduce((sum,w,k)=>sum+w*p[axis](i+k),0);
    const x=at('getX'),z=at('getZ'),y=surface.sample(x,z);
    if(y==null)missingSamples++;
    else {
      const error=Math.abs(at('getY')-y-offset);
      if(error>maximumErrorM) {maximumErrorM=error;worst={x,z,actualY:at('getY'),expectedY:y+offset,
        face:[0,1,2].map(k=>[p.getX(i+k),p.getY(i+k),p.getZ(i+k)])};}
    }
    }
  }
  return {maximumErrorM,missingSamples,sampledTriangles,worst};
}

/** Opt-in audit: indices, finite buffers, collapsed/inverted faces and normals. */
export function auditSurfaceGeometry(geometry) {
  const p=geometry.attributes.position,n=geometry.attributes.normal,index=geometry.index;
  const report={triangles:0,invalidIndices:0,nonFinite:0,degenerate:0,inverted:0,inconsistentNormals:0,maximumSlope:0};
  for(let i=0;i<(index?.count??p.count);i+=3) {
    report.triangles++;
    const ids=[0,1,2].map(k=>index?index.getX(i+k):i+k);
    if(ids.some(id=>!Number.isInteger(id)||id<0||id>=p.count)) {report.invalidIndices++;continue;}
    const face=ids.map(id=>({x:p.getX(id),y:p.getY(id),z:p.getZ(id)}));
    if(!face.every(v=>[v.x,v.y,v.z].every(Number.isFinite))) {report.nonFinite++;continue;}
    const area=crossXZ(...face);
    if(Math.abs(area)<=1e-9)report.degenerate++;
    else if(area<0)report.inverted++;
    if(area>1e-9) {
      const [a,b,c]=face,nx=(b.y-a.y)*(c.z-a.z)-(b.z-a.z)*(c.y-a.y),nz=(b.x-a.x)*(c.y-a.y)-(b.y-a.y)*(c.x-a.x);
      const slope=Math.hypot(nx,nz)/area;
      if(slope>report.maximumSlope) {report.maximumSlope=slope;report.steepestFace=face;}
    }
    if(n&&ids.some(id=>![n.getX(id),n.getY(id),n.getZ(id)].every(Number.isFinite)||n.getY(id)<=0))report.inconsistentNormals++;
  }
  return report;
}
