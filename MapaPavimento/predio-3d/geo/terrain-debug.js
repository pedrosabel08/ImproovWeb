import { auditSurfaceGeometry, auditSurfaceProjection } from "./surface-mesh.js?v=20261104-surface-v3";
import { SURFACE_Y } from "./environment-config.js?v=20261103-boundary-v4";

/** Explicit ?geo_debug=1 only. No per-frame audit and no normal-experience UI. */
export function installTerrainDebug(group,viewport,terrain) {
  const panel=document.createElement('details');
  panel.className='geo-terrain-debug';panel.open=true;
  panel.style.cssText='position:absolute;left:12px;bottom:80px;z-index:10;background:#171b22;color:#e4e9ec;padding:10px;font:12px/1.6 sans-serif;max-height:45%;overflow:auto';
  const summary=document.createElement('summary');summary.textContent='Diagnóstico das superfícies';panel.append(summary);
  const layers=new Map(),report=[];
  for(const mesh of group.children.filter(o=>o.isMesh)) {
    const key=mesh.userData.geoLayer||mesh.name.replace('OSM · ','');
    if(!layers.has(key))layers.set(key,[]);layers.get(key).push(mesh);
    if(['ground','model-ground','water','sea','road','FOREST','GRASS','PARK'].includes(key)) {
      const row={layer:key,...auditSurfaceGeometry(mesh.geometry)};
      const offset=key==='road'?SURFACE_Y.road:['FOREST','GRASS','PARK'].includes(key)?SURFACE_Y.vegetation:null;
      if(offset!=null&&terrain?.mesh)Object.assign(row,auditSurfaceProjection(mesh.geometry,terrain.mesh,offset,2000));
      report.push(row);
    }
  }
  const checkbox=(text,checked,change)=>{
    const label=document.createElement('label'),input=document.createElement('input');
    label.style.display='block';input.type='checkbox';input.checked=checked;
    input.addEventListener('change',()=>change(input.checked));label.append(input,document.createTextNode(text));panel.append(label);
  };
  const names={ground:'Malha DEM', 'model-ground':'Terreno GLB (cópia)',road:'Ruas',water:'Água: rios e polígonos unidos',sea:'Mar',FOREST:'Mata',GRASS:'Gramado',PARK:'Parques'};
  for(const [key,meshes] of layers)checkbox(names[key]||key,true,on=>meshes.forEach(m=>m.visible=on));
  const materials=[...new Set(group.children.filter(o=>o.isMesh).map(m=>m.material))];
  checkbox('Wireframe',false,on=>materials.forEach(m=>m.wireframe=on));
  const output=document.createElement('pre');output.style.cssText='white-space:pre-wrap;max-width:350px';
  output.textContent=JSON.stringify(report,null,2);panel.append(output);viewport.append(panel);
  viewport.dataset.geoGeometryAudit=JSON.stringify(report);
  console.debug('[Flow DEM] Auditoria geométrica',report);
  return {dispose(){materials.forEach(m=>m.wireframe=false);panel.remove();delete viewport.dataset.geoGeometryAudit;}};
}
