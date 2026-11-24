import * as THREE from "three";
import {action} from "./photo-library.js";

export function renderPhoto({studio, renderer, scene, helpers, credits}) {
  const aspect=studio.aspect;
  const w=aspect>=1?2048:Math.round(2048*aspect),h=aspect>=1?Math.round(2048/aspect):2048;
  const camera=studio.camera.clone();
  studio.projectCamera(camera,aspect);
  const size=renderer.getSize(new THREE.Vector2()),ratio=renderer.getPixelRatio();
  const target=renderer.getRenderTarget();
  const list=helpers.filter(Boolean).map(o=>[o,o.visible]);
  const copy=document.createElement("canvas");copy.width=w;copy.height=h;
  const ctx=copy.getContext("2d");
  try {
    list.forEach(([o])=>{o.visible=false;});
    renderer.setRenderTarget(null);renderer.setPixelRatio(1);renderer.setSize(w,h,false);
    renderer.render(scene,camera);
    ctx.drawImage(renderer.domElement,0,0,w,h);
    // The viewport can have a transparent CSS background in navigation lighting.
    ctx.globalCompositeOperation="destination-over";ctx.fillStyle=getComputedStyle(studio.viewport).getPropertyValue("--spatial-bg")||"#171a1c";
    ctx.fillRect(0,0,w,h);ctx.globalCompositeOperation="source-over";
    if(credits.length) {
      const text=credits.join(" · ");ctx.font="12px sans-serif";
      const width=ctx.measureText(text).width+12;
      ctx.fillStyle="#182026c9";ctx.fillRect(0,h-22,width,22);
      ctx.fillStyle="white";ctx.fillText(text,6,h-7,Math.max(1,w-12));
    }
  } finally {
    list.forEach(([o,visible])=>{o.visible=visible;});
    renderer.setRenderTarget(target);renderer.setPixelRatio(ratio);renderer.setSize(size.x,size.y,false);
    renderer.render(scene,studio.camera);
  }
  return new Promise((resolve,reject)=>copy.toBlob(blob=>{
    copy.width=copy.height=1;
    if(blob)resolve(blob);else reject(new Error("A captura não foi gerada. Tente novamente."));
  },"image/jpeg",.92));
}
export class PhotoReview {
  constructor(library,{capture,flyTo}) {
    Object.assign(this,{library,capture,flyTo});
    this.studio=library.studio;this.bridge=library.bridge;
    this.selected=new Set();this.markers=[];
    this.projection=new THREE.Matrix4();
    this.previousProjection=new THREE.Matrix4();
    this.point=new THREE.Vector3();this.direction=new THREE.Vector3();
    this.selectionKey=null;
    this.markerLayer=document.createElement("div");this.markerLayer.className="photo-markers";
    this.studio.viewport.append(this.markerLayer);
    this.filter=document.createElement("select");
    this.filter.setAttribute("aria-label","Marcadores de câmeras");
    for(const [value,title] of [["off","Ocultar câmeras"],["image","Câmeras da imagem"],["chosen","Preferidas ou aprovadas"],["all","Todas as câmeras"]])this.filter.add(new Option(title,value));
    this.studio.viewport.querySelector(".hud-menu").prepend(this.filter);
    this.filter.onchange=async()=>{try{if(this.filter.value!=="off")await library.refresh();this.buildMarkers();}
      catch(e){this.filter.value="off";this.buildMarkers();FlowAlert.error({message:e.message});}};
    this.compareButton=action("Comparar",()=>this.compare());
    this.compareButton.disabled=true;
    library.dialog.querySelector("footer").prepend(this.compareButton);
    this.captureButton=action("Gerar captura",()=>this.generate());
    this.studio.layer.querySelector(".photo-actions").prepend(this.captureButton);
    this.bundleButton=action("Comparar",()=>library.open());
    this.studio.layer.querySelector(".photo-actions").append(this.bundleButton);
    this.preview=document.createElement("dialog");this.preview.className="photo-library link-dialog photo-comparison";
    document.body.append(this.preview);
    this.studio.listeners.addEventListener("enter",()=>this.captureButton.hidden=!library.current);
    library.events.addEventListener("change",()=>this.decorate());
    this.studio.listeners.addEventListener("selection",()=>{this.captureButton.hidden=!library.current;});
  }
  url(row,kind) {
    const url=new URL("../api.php",import.meta.url);
    url.search=new URLSearchParams({action:"enquadramento_arquivo",obra_id:this.bridge.obraId,id:row.id,tipo:kind,v:row.captura});
    return url.href;
  }
  image(row,kind) {
    const img=new Image();img.loading="lazy";img.alt=row.nome;img.src=this.url(row,kind);return img;
  }
  decorate() {
    const lib=this.library;
    const key=lib.context()+"/"+lib.selectedImage;
    if(key!==this.selectionKey){this.selectionKey=key;this.selected.clear();}
    const rows=new Map(lib.rows.map(r=>[String(r.id),r]));
    for(const id of this.selected)if(!rows.has(id))this.selected.delete(id);
    for(const card of lib.list.querySelectorAll("[data-row]")){
      const row=rows.get(card.dataset.row);if(!row)continue;
      if(row.captura)card.prepend(this.image(row,"miniatura"));
      if(row.imagem_id) {
        const label=document.createElement("label"),box=document.createElement("input");
        box.type="checkbox";box.checked=this.selected.has(String(row.id));
        box.onchange=()=>{if(box.checked)this.selected.add(String(row.id));else this.selected.delete(String(row.id));this.compareButton.disabled=this.selected.size<2;};
        label.append(box," Comparar");card.append(label);
        this.decisions(card,row);
      }
      card.append(action("Exportar câmera",()=>this.export(row)));
    }
    this.compareButton.disabled=this.selected.size<2;
    this.buildMarkers();
  }
  decisions(card,row) {
    const lib=this.library;
    if(!row.preferido)card.append(action("Preferir",async()=>{await lib.write("preferir",row);this.preview.close();}));
    if(lib.admin && !row.aprovado)card.append(action("Aprovar para produção",async()=>{
      const ok=await FlowAlert.confirm({title:"Aprovar para produção?",message:"A composição ficará preservada. Ajustes futuros serão feitos em uma cópia.",confirmText:"Aprovar"});
      if(ok.isConfirmed){await lib.write("aprovar",row);this.preview.close();}
    }));
  }
  compare() {
    const rows=this.library.rows.filter(r=>this.selected.has(String(r.id)) && Number(r.imagem_id)===this.library.selectedImage);
    if(rows.length<2)return;
    this.preview.replaceChildren();
    const title=document.createElement("h2");title.textContent="Comparar enquadramentos";
    this.preview.append(title,action("Fechar comparação",()=>this.preview.close()));
    const grid=document.createElement("div");grid.className="photo-compare-grid";
    for(const row of rows) {
      const card=document.createElement("article"),name=document.createElement("strong"),info=document.createElement("p");
      const s=row.composicao;name.textContent=row.nome;
      info.textContent=`${s.focal.toFixed(1)} mm · ${s.height.toFixed(2)} m · ${s.solar.date} ${s.solar.time} · v${row.versao_numero} · ${row.aprovado?"✓ Aprovado":row.preferido?"★ Preferido":"Registrado"}`;
      card.append(name);
      if(row.captura)card.append(this.image(row,"captura"));else card.append("Captura ainda não solicitada");
      card.append(info,action("Abrir",async()=>{this.preview.close();await this.library.show(row);}));
      this.decisions(card,row);grid.append(card);
    }
    this.preview.append(grid);this.preview.show();
  }
  async generate() {
    const lib=this.library,row=lib.current;
    if(!row || this.studio.dirty)throw new Error("Salve o enquadramento antes de gerar a captura.");
    if(!lib.admin && Number(row.autor_id)!==lib.author)throw new Error("Somente o autor ou um administrador pode gravar a captura.");
    const form=new FormData();form.set("obra_id",this.bridge.obraId);form.set("id",row.id);form.set("revisao",row.revisao);
    const blob=await this.capture();form.set("captura",blob,"captura.jpg");
    const response=await fetch(new URL("../api.php?action=enquadramento_capturar",import.meta.url),{method:"POST",headers:{"X-CSRF-Token":this.bridge.csrf},body:form});
    const result=await response.json();if(!response.ok||!result.sucesso)throw new Error(result.erro||"Falha ao salvar a captura.");
    await lib.refresh();FlowAlert.success({message:"Captura e miniatura disponíveis para revisão."});
  }
  export(row) {
    const payload={schema:"flow.photo/1",obra_id:row.obra_id,imagem_id:row.imagem_id,modelo_id:this.bridge.modelId,
      versao_id:row.versao_id,versao:row.versao_numero,enquadramento_id:row.id,nome:row.nome,composicao:row.composicao};
    const url=URL.createObjectURL(new Blob([JSON.stringify(payload,null,2)],{type:"application/json"}));
    const a=document.createElement("a");a.href=url;a.download="camera-"+row.id+".json";a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
  }
  buildMarkers() {
    this.markers=[];this.markerLayer.replaceChildren();this.lastMatrix=null;
    const mode=this.filter.value,lib=this.library;
    if(mode==="off" || !lib.loaded)return;
    for(const row of lib.rows) {
      if(Number(row.versao_id)!==Number(this.bridge.model?.versao_id))continue;
      if(mode==="image" && (Number(row.imagem_id)||null)!==lib.selectedImage)continue;
      if(mode==="chosen" && !row.preferido && !row.aprovado)continue;
      const b=action(row.nome,async()=>{
        const s=row.composicao;
        if(!await this.flyTo(s))return;
        const image=lib.images.find(i=>i.id==row.imagem_id);
        const a=await FlowAlert.confirm({title:row.nome,message:`${image?.nome||"Não vinculado"} · ${s.focal.toFixed(1)} mm · ${s.solar.time} · v${row.versao_numero}`,confirmText:"Abrir fotografia"});
        if(a.isConfirmed)await lib.show(row);
      });
      b.className="photo-marker";b.dataset.other=String(!!lib.selectedImage && Number(row.imagem_id)!==lib.selectedImage);
      const glyph=document.createElement("i");glyph.className="fa-solid fa-camera";glyph.setAttribute("aria-hidden","true");b.prepend(glyph);
      if(row.aprovado || row.preferido)b.append(row.aprovado?" ✓":" ★");
      const arrow=document.createElement("span");arrow.textContent="↑";arrow.setAttribute("aria-hidden","true");b.prepend(arrow);
      const root=this.studio.model();if(!root)continue;
      const world=root.matrixWorld.clone().multiply(new THREE.Matrix4().fromArray(row.composicao.local));
      this.markers.push({b,arrow,p:new THREE.Vector3().setFromMatrixPosition(world),tip:new THREE.Vector3(0,0,-1).applyMatrix4(world)});
      this.markerLayer.append(b);
    }
  }
  update(camera) {
    if(!this.markers.length || this.studio.active || this.studio.viewport.dataset.navigation!=="orbit")return;
    camera.updateMatrixWorld();
    const matrix=this.projection.multiplyMatrices(camera.projectionMatrix,camera.matrixWorldInverse);
    const w=this.studio.viewport.clientWidth,h=this.studio.viewport.clientHeight;
    if(this.lastMatrix?.equals(matrix)&&w===this.width&&h===this.height)return;
    this.width=w;this.height=h;this.lastMatrix=this.previousProjection.copy(matrix);
    for(const m of this.markers) {
      const p=this.point.copy(m.p).applyMatrix4(matrix),tip=this.direction.copy(m.tip).applyMatrix4(matrix);
      m.b.hidden=Math.abs(p.x)>1 || Math.abs(p.y)>1 || Math.abs(p.z)>1;
      if(m.b.hidden)continue;
      m.b.style.left=(p.x+1)*w/2+"px";m.b.style.top=(1-p.y)*h/2+"px";
      m.arrow.style.transform="rotate("+Math.atan2((tip.x-p.x)*w,(tip.y-p.y)*h)+"rad)";
    }
  }
}
