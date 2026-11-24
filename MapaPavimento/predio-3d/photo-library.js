const txt=(tag,value)=>Object.assign(document.createElement(tag),{textContent:value});
const alertError=e=>FlowAlert.error({title:"Fotografia",message:e.message});
export function action(label,fn) {
  const b=txt("button",label);b.type="button";
  b.onclick=async()=>{if(b.disabled)return;b.disabled=true;try{await fn();}catch(e){alertError(e);}finally{b.disabled=false;}};
  return b;
}
export class PhotoLibrary {
  constructor(studio,bridge,openScene) {
    Object.assign(this,{studio,bridge,openScene});
    this.rows=[];this.images=[];this.selectedImage=null;this.selectedId=null;this.generation=0;
    this.launch=action("Imagens e enquadramentos",()=>this.open());
    this.launch.className="btn photo-library-launch";
    studio.viewport.append(this.launch);
    this.dialog=document.createElement("dialog");this.dialog.className="link-dialog photo-library";
    this.dialog.innerHTML=`<header><h2>Imagens e enquadramentos</h2><button type="button" data-close>Fechar</button></header>
      <div class="library-filters"><label>Buscar imagem<input type="search" aria-label="Buscar imagem" placeholder="Nome ou número"></label>
      <label>Tipo<select data-type></select></label><label>Imagem do projeto<select data-image></select></label></div>
      <p data-library-status role="status"></p><div data-list></div><footer></footer>`;
    document.body.append(this.dialog);
    this.dialog.querySelector("[data-close]").onclick=()=>this.dialog.close();
    this.search=this.dialog.querySelector("input");this.type=this.dialog.querySelector("[data-type]");
    this.image=this.dialog.querySelector("[data-image]");this.list=this.dialog.querySelector("[data-list]");
    this.search.oninput=this.type.onchange=()=>this.renderImages();
    this.image.onchange=()=>{this.selectedImage=Number(this.image.value)||null;this.selectedId=null;this.render();};
    this.studioButton=action("Imagens e enquadramentos",()=>this.open());
    this.studio.layer.querySelector("[data-library]").append(this.studioButton);
    this.saveButton=action("Salvar enquadramento",()=>this.save());
    this.studio.layer.querySelector(".photo-actions").prepend(this.saveButton);
    studio.listeners.addEventListener("enter",()=>{this.selectedId=null;this.pending=null;this.syncSelection();});
    this.events=new EventTarget();
  }
  context() {return [this.bridge.obraId,this.bridge.modelId,this.bridge.model?.versao_id].join("/");}
  invalidate() {
    this.generation++;this.loaded=null;this.rows=[];this.selectedId=null;this.pending=null;
    if(this.work!==this.bridge.obraId)this.selectedImage=null;
    this.work=this.bridge.obraId;
    this.dialog.close();this.syncSelection();
    this.events.dispatchEvent(new Event("change"));
  }
  async refresh() {
    const context=this.context(),gen=++this.generation;
    const data=await this.bridge.api("enquadramentos_listar",{modelo_id:this.bridge.modelId});
    if(context!==this.context()||gen!==this.generation)throw new Error("O modelo mudou. Abra a lista novamente.");
    this.loaded=context; this.rows=data.enquadramentos;this.images=data.imagens;this.admin=data.admin;this.author=Number(data.autor_id);
    this.versions=data.versoes;
    if(!this.images.some(i=>Number(i.id)===this.selectedImage))this.selectedImage=null;
    this.type.replaceChildren(new Option("Todos os tipos",""),...Array.from(new Set(this.images.map(i=>i.tipo).filter(Boolean))).sort().map(s=>new Option(s,s)));
    this.renderImages();this.render();
  }
  async open() {
    if(!this.bridge.modelId)throw new Error("Selecione um modelo 3D.");
    await this.refresh();if(!this.dialog.open){this.dialog.show();this.search.focus();}
  }
  renderImages() {
    const q=this.search.value.toLocaleLowerCase("pt-BR").trim();
    const isExternal=i=>/fachada|extern|aére|fotomontagem/i.test(i.tipo||i.nome);
    const selected=this.images.find(i=>Number(i.id)===this.selectedImage);
    const filtered=this.images.filter(i=>i.nome.toLocaleLowerCase("pt-BR").includes(q)&&(!this.type.value||i.tipo===this.type.value))
      .sort((a,b)=>Number(isExternal(b))-Number(isExternal(a))||a.nome.localeCompare(b.nome,"pt-BR",{numeric:true}));
    if(selected&&!filtered.includes(selected))filtered.unshift(selected);
    const groups=new Map();
    for(const r of this.rows) {
      const status=r.aprovado?"aprovado":r.preferido?"preferido":"com enquadramento";
      if(!groups.has(r.imagem_id)||status==="aprovado"||groups.get(r.imagem_id)==="com enquadramento")groups.set(r.imagem_id,status);
    }
    this.image.replaceChildren(new Option("Enquadramentos não vinculados",""),...filtered.map(i=>new Option(i.nome+" · "+(groups.get(i.id)||"sem enquadramento"),i.id)));
    this.image.value=this.selectedImage??"";
  }
  syncSelection() {
    const image=this.images.find(i=>Number(i.id)===this.selectedImage);
    this.launch.textContent=image?image.nome:"Imagens e enquadramentos";
    this.studioButton.textContent=this.launch.textContent;
    this.studio.imageAspect=image?.largura && image?.altura?image.largura/image.altura:null;
    this.current=this.rows.find(r=>Number(r.id)===this.selectedId)||null;
    this.saveButton.textContent=this.current?.aprovado_em?"Salvar como cópia":"Salvar enquadramento";
  }
  render() {
    this.syncSelection();this.list.replaceChildren();
    const rows=this.rows.filter(r=>(Number(r.imagem_id)||null)===this.selectedImage);
    this.dialog.querySelector("[data-library-status]").textContent=rows.length?`${rows.length} enquadramento(s)`:"Nenhum enquadramento registrado para esta seleção.";
    for(const row of rows) {
      const card=document.createElement("article");card.dataset.row=row.id;
      const s=row.composicao;
      card.append(txt("strong",row.nome),txt("small",`v${row.versao_numero} · ${s.focal.toFixed(1)} mm · ${s.solar.date} ${s.solar.time} · ${row.autor} · ${row.criado_em}`));
      if(row.preferido)card.append(txt("span","★ Preferido"));
      if(row.aprovado)card.append(txt("span","✓ Aprovado"));
      else if(row.aprovado_em)card.append(txt("span","Aprovação anterior"));
      card.append(action("Abrir",()=>this.show(row)),action("Duplicar",()=>this.duplicate(row)));
      if(Number(row.versao_id)!==Number(this.bridge.model.versao_id))card.append(action("Copiar para a versão em uso",()=>this.copyHere(row)));
      if(!row.aprovado_em && (this.admin||Number(row.autor_id)===this.author)){
        card.append(action("Renomear",async()=>{
          const name=await this.name(row.nome);if(name)await this.write("editar",row,{nome:name,composicao:row.composicao});
        }));
        if(!row.imagem_id)card.append(action("Vincular a imagem",()=>this.link(row)));
        card.append(action("Excluir",async()=>{
          const a=await FlowAlert.confirm({title:"Excluir enquadramento?",message:row.nome,confirmText:"Excluir"});
          if(a.isConfirmed)await this.write("excluir",row);
        }));
      }
      this.list.append(card);
    }
    this.events.dispatchEvent(new Event("change"));
  }
  async name(value) {
    const res=await FlowAlert.input({title:"Nome do enquadramento",value,maxlength:150,confirmText:"Salvar"});
    return res.isConfirmed?String(res.value??"").trim():null;
  }
  async link(row) {
    const res=await FlowAlert.input({title:"Vincular à imagem",inputType:"select",options:Object.fromEntries(this.images.map(i=>[i.id,i.nome])),confirmText:"Vincular"});
    if(res.isConfirmed && Number(res.value))await this.write("editar",row,{nome:row.nome,composicao:row.composicao,imagem_id:Number(res.value)});
  }
  async write(operacao,row,extra={}) {
    const context=this.context();
    const payload={operacao,id:row?.id??0,revisao:Number(row?.revisao??0),
      versao_id:Number(row?.versao_id??this.bridge.model.versao_id),imagem_id:row?row.imagem_id:this.selectedImage,...extra};
    const result=await this.bridge.api("enquadramento_gravar",payload,"POST");
    if(this.context()!==context)throw new Error("O modelo mudou durante o salvamento.");
    await this.refresh();return Number(result.id);
  }
  async save() {
    if(!this.studio.active)return;
    if(this.loaded!==this.context())await this.refresh();
    const row=this.current;
    const create=!row||!!row.aprovado_em;
    const name=create?await this.name(row?row.nome+" · cópia":"Enquadramento"):row.nome;
    if(!name)return;
    this.pending??=crypto.randomUUID();
    this.selectedId=await this.write(row?(create?"duplicar":"editar"):"criar",row,{nome:name,imagem_id:this.selectedImage,
      chave:this.pending,composicao:this.studio.snapshot()});
    this.pending=null;this.studio.dirty=false;this.syncSelection();
    FlowAlert.success({message:"Enquadramento salvo."});
  }
  async duplicate(row) {
    const name=await this.name(row.nome+" · cópia");if(!name)return;
    await this.write("duplicar",row,{nome:name,chave:crypto.randomUUID(),composicao:row.composicao});
  }
  async copyHere(row) {
    const dest=Number(this.bridge.model.versao_id);
    const image=Number(row.imagem_id)||null;
    this.dialog.close();
    await this.openScene(row.composicao);
    this.studio.restore(row.composicao);
    const ok=await FlowAlert.confirm({title:"Validar na versão em uso",message:"Confira a posição, escala, orientação e o sol na cena de destino antes de registrar a cópia.",confirmText:"Registrar cópia"});
    if(!ok.isConfirmed)return;
    await this.write("duplicar",row,{nome:row.nome+" · cópia",chave:crypto.randomUUID(),validado:true,versao_id:dest,imagem_id:image,composicao:this.studio.snapshot()});
  }
  async show(row) {
    this.dialog.close();
    if(this.studio.active && this.studio.dirty) {await this.studio.exit();if(this.studio.active)return;}
    if(Number(this.bridge.model.versao_id)!==Number(row.versao_id)){
      if(this.studio.active)await this.studio.exit(true);
      this.bridge.photoVersion=Number(row.versao_id);
      await this.bridge.loadModel(this.bridge.beginModelLoad());await this.refresh();
    }
    await this.openScene(row.composicao);
    this.studio.restore(row.composicao);
    this.selectedId=Number(row.id);this.selectedImage=Number(row.imagem_id)||null;this.syncSelection();
  }
}
