import * as THREE from "three";
import { entranceFocal, frameProjection, frameSize, gate } from "./photo-optics.mjs";

const editable = "input,select,textarea,[contenteditable],dialog[open]";
const ratios = ["16:9","9:16","3:2","2:3","4:3","3:4","4:5","5:4","1:1"];
const rad = Math.PI / 180;
function numberField(name, title, step, min = "", max = "") {
  return `<label>${title}<input data-field="${name}" type="number" step="${step}" min="${min}" max="${max}"></label>`;
}

export class PhotoStudio {
  constructor({ viewport, exploration, solar, model, floor, reference }) {
    Object.assign(this, { viewport, exploration, solar, model, floor, reference });
    this.keys = new Set();
    this.listeners = new EventTarget();
    this.trigger = document.createElement("button");
    this.trigger.type = "button";
    this.trigger.className = "btn photo-trigger";
    this.trigger.innerHTML = '<i class="fa-solid fa-camera" aria-hidden="true"></i> Fotografar <kbd>F</kbd>';
    this.trigger.hidden = true;
    viewport.querySelector(".hud-modes").after(this.trigger);
    this.trigger.onclick = () => this.enter();
    this.layer = document.createElement("section");
    this.layer.className = "photo-layer";
    this.layer.hidden = true;
    this.layer.innerHTML = `<div class="photo-frame"><div class="photo-thirds"></div><div class="photo-horizon"></div><div class="photo-center">+</div><div class="photo-plumb"></div></div>
      <button class="btn photo-restore" type="button">Mostrar controles (H)</button>
      <aside class="photo-controls"><header><strong>Fotografia</strong><button type="button" data-collapse aria-label="Recolher controles fotográficos">›</button></header>
      <div class="photo-fields"><div data-library></div>
      <fieldset><legend>Câmera</legend><label>Lente (mm)<input data-field="focal" type="number" min="10" max="300" step="any" list="photoFocals"></label>
      <datalist id="photoFocals">${[16,20,24,28,35,50,70,85].map(n=>`<option value="${n}"></option>`).join("")}</datalist>
      <label>Proporção<select data-field="ratio">${ratios.map(r=>`<option>${r}</option>`).join("")}<option value="custom">Personalizada</option></select></label>
      <label data-custom>Largura : altura<input data-field="custom" placeholder="4:5" aria-label="Proporção personalizada"></label>
      ${numberField("height","Altura sobre o terreno (m)",0.1)}${numberField("altitude","Cota da câmera (m)",0.1)}
      ${numberField("yaw","Direção (°)",0.5)}${numberField("pitch","Inclinação (°)",0.5,-89,89)}${numberField("roll","Horizonte (°)",0.5,-180,180)}
      ${numberField("speed","Velocidade (m/s)",0.1,0.1,50)}
      <label><input type="checkbox" data-field="vertical">Correção vertical</label></fieldset>
      <fieldset><legend>Estudo solar</legend><label><input type="checkbox" data-field="sun">Ativar sol</label>
      <label>Data<input type="date" data-field="date"></label><label>Horário<input type="time" data-field="time"></label>
      <input aria-label="Horário solar da fotografia" data-field="minutes" type="range" min="0" max="1439">
      <div class="photo-presets">${["Agora","Manhã","Meio-dia","Tarde"].map(t=>`<button type="button">${t}</button>`).join("")}</div>
      <details><summary>Posição solar</summary><output data-solar></output></details></fieldset>
      <fieldset><legend>Guias de composição</legend>${[["thirds","Regra dos terços"],["horizon","Linha do horizonte"],["center","Centro"],["plumb","Verticais"]].map(([key,title])=>`<label><input type="checkbox" data-guide="${key}">${title}</label>`).join("")}</fieldset></div></aside>
      <div class="photo-actions"><button type="button" data-clean>Ocultar HUD (H)</button><button type="button" data-exit>Sair da fotografia (Esc)</button></div>`;
    viewport.append(this.layer);
    this.frame = this.layer.querySelector(".photo-frame");
    this.panel = this.layer.querySelector("aside");
    this.field = Object.fromEntries([...this.layer.querySelectorAll("[data-field]")].map(el=>[el.dataset.field,el]));
    this.layer.querySelector("[data-exit]").onclick = () => this.exit();
    this.layer.querySelector("[data-clean]").onclick = this.layer.querySelector(".photo-restore").onclick =
      () => this.viewport.closest(".app").classList.toggle("photo-clean");
    this.layer.querySelector("[data-collapse]").onclick = (e) => {
      const closed = this.panel.classList.toggle("folded");
      e.currentTarget.textContent = closed ? "‹" : "›";
      e.currentTarget.setAttribute("aria-label", closed ? "Abrir controles fotográficos" : "Recolher controles fotográficos");
    };
    this.layer.addEventListener("input", e => this.input(e.target));
    this.layer.querySelector(".photo-presets").onclick = e => {
      if (e.target.tagName !== "BUTTON") return;
      const time = {"Manhã":"08:00","Meio-dia":"12:00","Tarde":"16:30"}[e.target.textContent];
      if (time) solar.time.value = time; else solar.now();
      solar.relight(); this.syncSun(); this.dirty = true;
    };
    window.addEventListener("keydown", e => this.key(e), true);
    window.addEventListener("keyup", e => this.keys.delete(e.code));
    window.addEventListener("blur", () => { this.keys.clear(); this.drag = null; });
    this.layer.addEventListener("focusin", () => this.keys.clear());
    const canvas = viewport.querySelector("canvas.gl");
    canvas.addEventListener("pointerdown", e => {
      if (!this.active || e.button !== 0) return;
      this.drag = [e.clientX,e.clientY,e.pointerId]; canvas.setPointerCapture(e.pointerId);
    });
    canvas.addEventListener("pointermove", e => {
      if (!this.active || this.drag?.[2] !== e.pointerId) return;
      this.rotation.y -= (e.clientX-this.drag[0]) * .002;
      this.rotation.x = THREE.MathUtils.clamp(this.rotation.x-(e.clientY-this.drag[1])*.002,-89*rad,89*rad);
      this.drag = [e.clientX,e.clientY,e.pointerId]; this.changed();
    });
    for (const event of ["pointerup","lostpointercapture","pointercancel"])
      canvas.addEventListener(event, () => this.drag = null);
    window.addEventListener("beforeunload",e=>{ if(this.active && this.dirty){e.preventDefault();e.returnValue="";} });
    new ResizeObserver(()=>{if(this.active)this.project();}).observe(viewport);
  }
  key(e) {
    if (e.repeat && ["KeyF","KeyH","Escape"].includes(e.code)) return;
    if (e.ctrlKey || e.metaKey || e.altKey || e.target.closest?.(editable) || document.querySelector('dialog[open],[role="alertdialog"]')) return;
    if (!this.active && e.code==="KeyF" && !this.trigger.hidden) this.enter();
    else if (!this.active) return;
    else if (e.code==="Escape") void this.exit();
    else if (e.code==="KeyH") this.layer.querySelector("[data-clean]").click();
    else if (["KeyW","KeyA","KeyS","KeyD","KeyQ","KeyE","ArrowLeft","ArrowRight","ArrowUp","ArrowDown"].includes(e.code)) this.keys.add(e.code);
    else return;
    e.preventDefault(); e.stopImmediatePropagation();
  }
  enter(aspect = this.imageAspect) {
    const source = this.exploration.camera;
    if (this.active || !source || !this.model()) return false;
    this.camera = source.clone();
    this.rotation = new THREE.Euler().setFromQuaternion(source.quaternion,"YXZ");
    this.sourceMode = this.viewport.dataset.navigation;
    this.aspect = aspect || source.aspect;
    this.focal = entranceFocal(source.fov,source.zoom,source.aspect,this.aspect);
    this.speed = 1; this.vertical = false; this.active = true; this.dirty = false;
    this.originalSun = this.solar.snapshot();
    this.navigationSunPosition = this.solar.explorerPosition;
    this.solar.setExplorerPosition(this.camera.position);
    this.exploration.pause(true);
    this.viewport.closest(".app").classList.add("photo-active");
    this.layer.hidden = false; this.trigger.hidden = true;
    this.previousFrame = null; this.panel.classList.remove("folded");
    this.sync(); this.syncSun(); this.project();
    this.listeners.dispatchEvent(new Event("enter")); return true;
  }
  async exit(force = false) {
    if (!this.active || this.confirming) return;
    if (this.dirty && !force) {
      this.confirming = true;
      try {
        const answer = await FlowAlert.confirm({title:"Descartar ajustes fotográficos?",message:"Os ajustes desde o último salvamento serão descartados.",confirmText:"Descartar"});
        if (!answer.isConfirmed) return;
      } finally { this.confirming = false; }
    }
    this.active = this.dirty = false; this.keys.clear(); this.drag = null;
    this.layer.hidden = true; this.viewport.closest(".app").classList.remove("photo-active","photo-clean");
    this.solar.setExplorerPosition(this.navigationSunPosition);
    this.solar.restoreSnapshot(this.originalSun);
    this.exploration.pause(false);
    this.listeners.dispatchEvent(new Event("exit"));
    this.trigger.focus({preventScroll:true});
  }
  input(el) {
    if (!this.active) return;
    if (el.dataset.guide) {this.frame.classList.toggle("guide-"+el.dataset.guide,el.checked);return;}
    const key = el.dataset.field;
    if (!key) return;
    const n = el.valueAsNumber;
    if (el.type==="number" && (!Number.isFinite(n) || !el.validity.valid)) return;
    const pos = this.camera.position;
    if (key==="ratio" || key==="custom") {
      this.layer.querySelector("[data-custom]").hidden = this.field.ratio.value!=="custom";
      const ratio = this.field.ratio.value==="custom"?this.field.custom.value:this.field.ratio.value;
      const parts=ratio.trim().split(/[:/]/).map(Number);
      if(parts.length!==2 || parts.some(v=>!Number.isFinite(v)||v<=0))return;
      const aspect=parts[0]/parts[1]; if(aspect<.05 || aspect>20)return;
      this.aspect=aspect;
    } else if(key==="focal") this.focal=n;
    else if(key==="speed") this.speed=n;
    else if(key==="height") pos.y=this.exploration.groundAt(pos.x,pos.z)+n;
    else if(key==="altitude") pos.y=n;
    else if(key==="vertical") this.vertical=el.checked;
    else if(["pitch","yaw","roll"].includes(key)) this.rotation[{pitch:"x",yaw:"y",roll:"z"}[key]]=n*rad;
    else {
      if(key==="sun") this.solar.enabled=el.checked && !this.solar.toggle.disabled;
      else if(key==="date" || key==="time") {if(!el.value)return; this.solar[key].value=el.value;}
      else if(key==="minutes") this.solar.time.value=[Math.floor(n/60),n%60].map(v=>String(v).padStart(2,"0")).join(":");
      this.solar.syncEnabled();this.solar.relight();this.syncSun();
    }
    this.changed();
  }
  changed() {this.dirty=true;this.project();this.sync();}
  project() {
    const w=this.viewport.clientWidth,h=this.viewport.clientHeight;
    if(!w || !h)return;
    const size=frameSize(w,h,this.aspect);
    this.frame.style.width=size.width+"px"; this.frame.style.height=size.height+"px";
    this.projectCamera(this.camera,w/h);
    // Project a horizontal vanishing line into the same cropped image coordinates.
    const up=new THREE.Vector3(0,1,0).applyQuaternion(this.camera.quaternion.clone().invert());
    const m=this.camera.projectionMatrix.elements;
    const y=(up.z-up.x*m[8]/m[0])*m[5]/(up.y || 1e-8)-m[9];
    const guide=this.frame.querySelector(".photo-horizon");
    guide.style.top=(50-y*h/size.height*50)+"%";
    guide.style.transform="translateX(-50%) rotate("+Math.atan2(up.x,up.y)+"rad)";
  }
  projectCamera(cam, viewportAspect) {
    cam.aspect=viewportAspect; cam.zoom=1; cam.fov=frameProjection(this.focal,this.aspect,viewportAspect);
    cam.quaternion.setFromEuler(this.vertical?new THREE.Euler(0,this.rotation.y,0,"YXZ"):this.rotation);
    cam.updateProjectionMatrix();
    if(this.vertical) {
      cam.projectionMatrix.elements[9]=Math.tan(this.rotation.x)*cam.projectionMatrix.elements[5];
      cam.projectionMatrixInverse.copy(cam.projectionMatrix).invert();
    }
    cam.updateMatrixWorld();
  }
  sync() {
    const pos=this.camera.position;
    for(const [key,value] of Object.entries({focal:this.focal,height:pos.y-this.exploration.groundAt(pos.x,pos.z),altitude:pos.y,yaw:this.rotation.y/rad,pitch:this.rotation.x/rad,roll:this.rotation.z/rad,speed:this.speed}))
      if(document.activeElement!==this.field[key]) this.field[key].value=Number(value.toFixed(3));
    this.field.vertical.checked=this.vertical;
    this.field.roll.disabled=this.vertical;
    const ratio=ratios.find(r=>{const [w,h]=r.split(":");return Math.abs(w/h-this.aspect)<1e-7;});
    this.field.ratio.value=ratio || "custom";this.layer.querySelector("[data-custom]").hidden=!!ratio;
    if(document.activeElement!==this.field.custom)this.field.custom.value=Number(this.aspect.toFixed(6))+":1";
  }
  syncSun() {
    const s=this.solar;
    this.field.sun.checked=s.enabled;this.field.sun.disabled=s.toggle.disabled;
    this.field.date.value=s.date.value;this.field.time.value=s.time.value;
    this.field.minutes.value=s.range.value;
    this.layer.querySelector("[data-solar]").textContent=s.result?`Azimute ${s.result.azimuth.toFixed(1)}° · Elevação ${s.result.altitude.toFixed(1)}° · ${s.config.timezone}`:"Sol indisponível";
  }
  tick(now) {
    if(!this.active) {
      const hidden=!this.exploration.camera;
      if(this.trigger.hidden!==hidden)this.trigger.hidden=hidden;
      return null;
    }
    const dt=Math.min((now-(this.previousFrame??now))/1000,.08);this.previousFrame=now;
    if(this.keys.size) {
      const k=this.keys, step=this.speed*dt;
      const x=Number(k.has("KeyD"))-Number(k.has("KeyA")),z=Number(k.has("KeyS"))-Number(k.has("KeyW"));
      this.camera.position.add(new THREE.Vector3(x,0,z).applyAxisAngle(new THREE.Vector3(0,1,0),this.rotation.y).multiplyScalar(step));
      this.camera.position.y+=step*(Number(k.has("KeyE"))-Number(k.has("KeyQ")));
      this.rotation.y+=dt*(Number(k.has("ArrowLeft"))-Number(k.has("ArrowRight")));
      this.rotation.x=THREE.MathUtils.clamp(this.rotation.x+dt*(Number(k.has("ArrowUp"))-Number(k.has("ArrowDown"))),-89*rad,89*rad);
      this.changed();
    }
    return this.camera;
  }
  snapshot() {
    const root=this.model();root.updateWorldMatrix(true,false);
    const q=new THREE.Quaternion().setFromEuler(this.rotation);
    const matrix=new THREE.Matrix4().compose(this.camera.position,q,new THREE.Vector3(1,1,1));
    const local=root.matrixWorld.clone().invert().multiply(matrix);
    const opticalCamera=this.camera.clone();
    this.projectCamera(opticalCamera,this.aspect);
    const opticalLocal=root.matrixWorld.clone().invert().multiply(opticalCamera.matrixWorld);
    const target=new THREE.Vector3(0,0,-1).applyMatrix4(local);
    const anchor=this.exploration.anchor;
    return {schema:1,source:this.sourceMode,anchor:anchor && {...anchor,position:anchor.position.toArray()},local:local.toArray(),modelToScene:root.matrixWorld.toArray(),
      reference:this.reference(),floor:this.floor(),unit:"m",up:"Y",forward:"-Z",projection:"perspective",
      targetLocal:target.toArray(),opticalLocal:opticalLocal.toArray(),projectionMatrix:opticalCamera.projectionMatrix.toArray(),
      focal:this.focal,aspect:this.aspect,sensor:[36,24],gate:gate(this.aspect),fov:frameProjection(this.focal,this.aspect,this.aspect),
      vertical:this.vertical,shiftY:this.vertical?Math.tan(this.rotation.x)/(2*Math.tan(frameProjection(this.focal,this.aspect,this.aspect)*rad/2)):0,
      roll:this.rotation.z,height:Number(this.field.height.value),solar:this.solar.snapshot()};
  }
  restore(state) {
    const root=this.model();root.updateWorldMatrix(true,false);
    const world=root.matrixWorld.clone().multiply(new THREE.Matrix4().fromArray(state.local));
    const q=new THREE.Quaternion();world.decompose(this.camera.position,q,new THREE.Vector3());
    this.rotation.setFromQuaternion(q,"YXZ");
    this.aspect=state.aspect;this.focal=state.focal;this.vertical=state.vertical;
    this.solar.restoreSnapshot(state.solar);this.syncSun();this.sync();this.project();this.dirty=false;
  }
}
