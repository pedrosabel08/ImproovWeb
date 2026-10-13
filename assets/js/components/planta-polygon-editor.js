/* Editor compartilhado de área útil e ambientes. Pontos independem do viewport. */
(() => {
  class PlantaPolygonEditor extends EventTarget {
    constructor(viewer) {
      super(); this.viewer=viewer; this.points=[]; this.active=false;
      viewer.addEventListener('plantaclick', e=>{
        if(!this.active)return;
        const first=this.points.length?viewer.toScreen(this.points[0]):null;
        if(!this.closed && this.points.length>=3 && Math.hypot(e.detail.clientX-first[0],e.detail.clientY-first[1])<16) {
          this.closed=true; this.render(); return;
        }
        if(this.points.length>=200)return;
        if(this.closed) {
          let closest=null;
          this.points.forEach((p,i)=>{
            const a=viewer.toScreen(p),b=viewer.toScreen(this.points[(i+1)%this.points.length]);
            const dx=b[0]-a[0],dy=b[1]-a[1],t=Math.max(0,Math.min(1,((e.detail.clientX-a[0])*dx+(e.detail.clientY-a[1])*dy)/(dx*dx+dy*dy)));
            const distance=Math.hypot(e.detail.clientX-a[0]-t*dx,e.detail.clientY-a[1]-t*dy);
            if(distance<14 && (!closest || distance<closest.distance))closest={i,distance};
          });
          if(closest){this.points.splice(closest.i+1,0,e.detail.point);this.selected=closest.i+1;}
        } else this.points.push(e.detail.point);
        this.render();
      });
      viewer.addEventListener('vertexstart',e=>{
        if(!this.active)return;
        if(!this.closed && e.detail===0 && this.points.length>=3)this.closed=true;
        this.selected=e.detail;this.render();
      });
      viewer.addEventListener('vertexmove',e=>{
        if(!this.active || !viewer.containsPoint(e.detail.point))return;
        this.points[e.detail.index]=e.detail.point;this.render();
      });
      viewer.addEventListener('viewchange',()=>{if(this.active)this.render(false)});
    }
    start(geometry=null, pageSpace=false) {
      this.points=(geometry?.points||[]).map(p=>[...p]);this.closed=this.points.length>=3;
      this.pageSpace=pageSpace;this.selected=null;this.active=true;this.viewer.setDrawing(true);this.render();
    }
    cancel(){this.active=false;this.points=[];this.viewer.setDrawing(false);this.viewer.draft.replaceChildren();this.dispatchEvent(new Event('cancel'));}
    undo(){if(this.closed){this.closed=false;this.selected=null;}else this.points.pop();this.render();}
    clear(){this.points=[];this.closed=false;this.selected=null;this.render();}
    addVertex(){
      if(!this.closed || this.points.length>=200)return;
      const i=this.selected??this.points.length-1,a=this.points[i],b=this.points[(i+1)%this.points.length];
      this.points.splice(i+1,0,[(a[0]+b[0])/2,(a[1]+b[1])/2]);this.selected=i+1;this.render();
    }
    removeVertex(){
      if(this.selected===null || (this.closed && this.points.length<=3))return;
      this.points.splice(this.selected,1);this.selected=null;this.render();
    }
    geometry(){return {version:this.pageSpace?3:2,coordinate_space:this.pageSpace?'page_normalized':'useful_area_normalized',points:this.points.map(p=>p.map(v=>Number(v.toFixed(8))))};}
    finish(){if(this.points.length<3)return;this.closed=true;this.active=false;this.viewer.setDrawing(false);this.dispatchEvent(new CustomEvent('complete',{detail:this.geometry()}));}
    render(notify=true){
      const group=this.viewer.draft;group.replaceChildren();
      const points=this.points.map(p=>this.viewer.toDisplayPixels(p));
      if(points.length>1)group.append(window.plantaSvgElement(this.closed?'polygon':'polyline',{points:points.map(p=>p.join(',')).join(' '),class:'pv-draft','vector-effect':'non-scaling-stroke'}));
      points.forEach((p,i)=>group.append(window.plantaSvgElement('circle',{
        cx:p[0],cy:p[1],r:(i===this.selected?7:6)/this.viewer.scale,'data-vertex':i,
        class:'pv-vertex'+(i===0?' is-first':'')+(i===this.selected?' is-active':''),'vector-effect':'non-scaling-stroke'
      })));
      if(notify)this.dispatchEvent(new CustomEvent('change',{detail:this.points.length}));
    }
  }
  window.PlantaPolygonEditor=PlantaPolygonEditor;
})();
