/* Inspeção visual OCG por diferença de renderizações isoladas. Sem alterar o documento. */
(() => {
  class PlantaLayerInspector extends EventTarget {
    constructor(viewer) {
      super();this.viewer=viewer;this.generation=0;
      viewer.container.addEventListener('pointermove',e=>{
        if(!this.active || viewer.drag)return;
        const point=viewer.toPoint(e.clientX,e.clientY);
        if(!viewer.containsPoint(point))return this.emit(null);
        const crop=viewer.crop||{x:0,y:0,width:1,height:1};
        const x=Math.floor((crop.x+point[0]*crop.width)*this.width),y=Math.floor((crop.y+point[1]*crop.height)*this.height);
        const names=[];
        for(const layer of this.masks){
          let found=false;
          for(let dy=-2;dy<=2&&!found;dy++)for(let dx=-2;dx<=2;dx++){
            const px=x+dx,py=y+dy;if(px<0||py<0||px>=this.width||py>=this.height)continue;
            const index=py*this.width+px;
            if(layer.bits[index>>3]&(1<<(index&7))){found=true;break;}
          }
          if(found)names.push(layer.name);
        }
        this.emit({names,x:e.clientX,y:e.clientY});
      });
      viewer.container.addEventListener('pointerleave',()=>this.emit(null));
    }
    emit(detail){this.dispatchEvent(new CustomEvent('inspect',{detail}));}
    cancel(){this.generation++;this.task?.cancel();this.active=false;this.viewer.container.classList.remove('is-inspecting');this.emit(null);}
    async start(){
      this.cancel();const generation=this.generation,viewer=this.viewer,page=viewer.page,doc=viewer.document;
      const groups=viewer.getLayers().groups;
      if(!groups.length)throw new Error('Este PDF não possui layers editáveis. Não há camada identificável.');
      const key=doc.fingerprints[0]+':'+page.pageNumber;
      if(this.key!==key){
        const density=Math.min(1.5,1000/Math.max(viewer.pageWidth,viewer.pageHeight),Math.sqrt(24000000/(viewer.pageWidth*viewer.pageHeight*groups.length)));
        const viewport=page.getViewport({scale:density});this.width=Math.ceil(viewport.width);this.height=Math.ceil(viewport.height);
        const canvas=document.createElement('canvas');canvas.width=this.width;canvas.height=this.height;const ctx=canvas.getContext('2d',{willReadFrequently:true});
        const render=async id=>{
          const config=await doc.getOptionalContentConfig();
          if(generation!==this.generation)throw new Error('Inspeção cancelada.');
          groups.forEach(group=>config.setVisibility(group.id,group.id===id));
          ctx.clearRect(0,0,this.width,this.height);
          this.task=page.render({canvasContext:ctx,viewport,optionalContentConfigPromise:Promise.resolve(config)});
          await this.task.promise;this.task=null;
          if(generation!==this.generation)throw new Error('Inspeção cancelada.');
          return ctx.getImageData(0,0,this.width,this.height).data;
        };
        const baseline=await render(null),masks=[];
        for(let n=0;n<groups.length;n++){
          const layer=groups[n],pixels=await render(layer.id),bits=new Uint8Array(Math.ceil(this.width*this.height/8));
          for(let i=0,p=0;i<pixels.length;i+=4,p++)if(Math.abs(pixels[i]-baseline[i])+Math.abs(pixels[i+1]-baseline[i+1])+Math.abs(pixels[i+2]-baseline[i+2])>24)bits[p>>3]|=1<<(p&7);
          masks.push({id:layer.id,name:layer.name,bits});
          this.dispatchEvent(new CustomEvent('progress',{detail:{done:n+1,total:groups.length}}));
          await new Promise(resolve=>setTimeout(resolve,0));
        }
        this.masks=masks;this.key=key;
      }
      if(generation!==this.generation)return;
      this.active=true;viewer.container.classList.add('is-inspecting');
    }
  }
  window.PlantaLayerInspector=PlantaLayerInspector;
})();
