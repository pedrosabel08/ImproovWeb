/* Local authenticated UI audit. Only photographic persistence is routed to an isolated CGI and database. */
const fs = require("node:fs");
const path = require("node:path");
const { execFileSync } = require("node:child_process");
const assert = require("node:assert/strict");
const { chromium } = require(process.env.FLOW_PLAYWRIGHT_MODULE || "playwright");
const { isolatedApi } = require("../tests/photo_capture_test.cjs");
const root=path.resolve(__dirname,"..");
const evidence=path.join(root,"docs","evidencias-fotografia-3d");
const before=process.argv.includes("--baseline");
const performanceOnly=process.argv.includes("--performance-only");
const sampleId=process.env.FLOW_QA_SAMPLE || "";
assert.match(sampleId,/^[a-z0-9-]*$/i);
const baseRevision="382e7939";
const base="http://localhost:8066/ImproovWeb/";
const files=new Set(execFileSync("git",["diff","--name-only",baseRevision,"--","MapaPavimento/predio-3d"],{encoding:"utf8"}).trim().split(/\r?\n/));
const changedHtml="MapaPavimento/predio-3d/visualizador-glb-navegacao.html";
const original=name=>execFileSync("git",["show",baseRevision+":"+name],{cwd:root,maxBuffer:8e6});
async function main(){
  const browser=await chromium.launch({channel:process.env.FLOW_QA_BROWSER||"chrome",headless:true});
  let api, page;
  try{
    const context=await browser.newContext({viewport:{width:1366,height:768}});
    page=await context.newPage();page.setDefaultTimeout(40000);
    const errors=[];
    page.on("pageerror",e=>{errors.push(e.message);console.log("JS:",e.message);});
    const credentials=fs.readFileSync(path.join(root,"AGENTS.md"),"utf8");
    await page.goto(base,{waitUntil:"domcontentloaded"});
    await page.locator("#login").fill(process.env.FLOW_AUDIT_LOGIN||credentials.match(/Usuario:\s*(\S+)/)[1]);
    await page.locator("#senha").fill(process.env.FLOW_AUDIT_PASSWORD||credentials.match(/Senha:\s*(\S+)/)[1]);
    await page.getByRole("button",{name:"Entrar",exact:true}).click();
    const tools=page.locator('button[data-sidebar-panel="ferramentas"]');
    await tools.waitFor();
    if(await page.locator("#modalIframeChangelog:visible").count())await page.keyboard.press("Escape");
    await tools.click();await page.locator('aside.sidebar a[title="Mapa de pavimento"]:visible').click();
    await page.locator("#obraSelect").selectOption({label:"RKS_DCF — DCF"});
    const obra=Number(await page.locator("#obraSelect").inputValue());
    const totals={assets:0,captures:0};
    const elapsed=[];
    page.on("request",r=>{
      if(/action=modelo_3d_arquivo|\.glb(?:$|\?)/.test(r.url()))totals.assets++;
    });
    await page.route("**/MapaPavimento/api.php?*",async route=>{
      const r=route.request(),u=new URL(r.url()),action=u.searchParams.get("action");
      if(action==="modelo_3d_contexto" && !before && !performanceOnly) {
        const response=await route.fetch();const data=await response.json();
        if(data.modelo){
          // Legacy model gets its backfilled version ID only in this isolated test context.
          data.modelo.versao_id ||= 51;
          if(!api)api=isolatedApi(obra,Number(data.modelo.id),Number(data.modelo.versao_id));
        }
        return route.fulfill({response,json:data});
      }
      if(/^enquadramento/.test(action)){
        assert.ok(api);
        if(action==="enquadramento_capturar")totals.captures++;
        const res=api.request(action,{method:r.method(),query:Object.fromEntries(u.searchParams),data:r.postDataBuffer(),type:r.headers()["content-type"]||"application/json"});
        if(res.status>=400)console.log("API",action,res.status,res.body.toString().slice(0,200));
        return route.fulfill({status:res.status,contentType:res.contentType,body:res.body});
      }
      if(r.method()!=="GET")return route.abort();
      return route.continue();
    });
    // Use the exact installed Three revision on both runs, including worker imports.
    const three=path.join(root,"tools/geo-cache/node_modules/three");
    assert.equal(JSON.parse(fs.readFileSync(path.join(three,"package.json"))).version,"0.180.0");
    await context.route("https://cdn.jsdelivr.net/npm/three@0.180.0/**",r=>{
      const file=new URL(r.request().url()).pathname.split("/three@0.180.0/")[1];
      const local=path.resolve(three,file);
      if(!local.startsWith(three+path.sep)||!fs.existsSync(local))return r.continue();
      return r.fulfill({path:local,contentType:"text/javascript",headers:{"access-control-allow-origin":"*"}});
    });
    if(before||performanceOnly){
      await page.route("**/predio-3d/**",async route=>{
        const name=decodeURIComponent(new URL(route.request().url()).pathname).replace(/^\/ImproovWeb\//,"");
        if(before && name.endsWith("visualizador-glb-navegacao.php")){
          const response=await route.fetch();
          // The PHP wrapper still supplies the authenticated sidebar, metadata and per-page configuration.
          assert.ok(original(changedHtml).includes("exploration-mode.css"));
          const html=(await response.text()).replace(/<link[^>]+photo-studio\.css[^>]*>/g,"");
          return route.fulfill({response,body:html});
        }
        if(files.has(name)&&/\.(m?js|css)$/.test(name)){
          if(before && name.includes("/photo-"))return route.abort();
          return route.fulfill({body:before?original(name):fs.readFileSync(path.join(root,name)),
            contentType:name.endsWith(".css")?"text/css":"text/javascript"});
        }
        return route.fallback();
      });
    }
    console.log("Abrindo modelo.");
    await page.locator("#openModel3d").click();
    await page.locator('.viewport[data-geo-state="ready"]').waitFor({timeout:120000});
    const ready=await page.evaluate(()=>performance.now());
    const profiler=await context.newCDPSession(page);
    const sample=async mode=>{
      await profiler.send("HeapProfiler.collectGarbage");
      if(mode==="voo")await page.keyboard.down("w");
      const data=await page.evaluate(async()=>{
        const frames=[];let prev;
        await new Promise(resolve=>{function frame(now){if(prev)frames.push(now-prev);prev=now;if(frames.length===90)resolve();else requestAnimationFrame(frame);}requestAnimationFrame(frame);});
        frames.sort((a,b)=>a-b);
        return {medianMs:+frames[45].toFixed(2),p95Ms:+frames[85].toFixed(2),heapMiB:Math.round(performance.memory.usedJSHeapSize/1048576)};
      });if(mode==="voo")await page.keyboard.up("w");elapsed.push({mode,...data});console.log(mode,data);
    };
    await sample("orbital");
    const screenshot=name=>page.screenshot({path:path.join(evidence,name+".jpg"),type:"jpeg",quality:82,timeout:30000});
    const button=(name,ctx=page)=>ctx.getByRole("button",{name,exact:true});
    if(!before&&!performanceOnly)await screenshot("01-orbital");
    if(before)await page.locator("select.navigation-mode").selectOption("seagull");
    else await button("Voo").click();
    await page.getByLabel("Animação da gaivota").waitFor();
    await sample("voo");
    const metrics={revision:before?baseRevision:"entrega-5",browser:browser.version(),viewport:"1366x768",readyMs:Math.round(ready),samples:elapsed};
    const label=before?"antes":"depois";
    fs.writeFileSync(path.join(evidence,label+sampleId+".json"),JSON.stringify(metrics,null,2)+"\n");
    if(before||performanceOnly)return;
    await screenshot("02-voo");
    await page.locator(".photo-library-launch").click();
    await page.locator("[data-image]").selectOption("31");
    await button("Fechar",page.locator(".photo-library[open]")).click();
    const assetsBefore=totals.assets;
    await page.locator(".photo-trigger").click();
    const panel=page.locator(".photo-layer");
    assert.equal(await panel.locator('[data-field="ratio"]').inputValue(),"4:5");
    await panel.locator('[data-field="focal"]').fill("28");
    await panel.locator('[data-guide="thirds"]').check();
    await button("Manhã",panel).click();
    await screenshot("03-fotografia");
    await button("Ocultar HUD (H)",panel).click();
    assert.equal(await page.locator(".photo-controls").isVisible(),false);
    await button("Mostrar controles (H)",panel).click();
    for(const [width,height] of [[1920,1080],[2560,1080],[1180,820],[820,1180],[390,844],[1366,768]]){
      await page.setViewportSize({width,height});
      const frame=await panel.locator(".photo-frame").boundingBox();
      assert.ok(Math.abs(frame.width/frame.height-.8)<.003);
      for(const selector of [".photo-controls",".photo-actions",".photo-launch"]){
        const box=await page.locator(selector).boundingBox();
        assert.ok(box.x>=0 && box.y>=0 && box.x+box.width<=width+1 && box.y+box.height<=height+1,selector);
      }
      const modes=await page.locator(".hud-modes").boundingBox();
      assert.ok(modes.x+modes.width<=width+1,"Barra dos modos fora da janela");
      if(width===390)await screenshot("05-fotografia-mobile");
    }
    assert.equal(totals.captures,0);
    await button("Salvar enquadramento",panel).click();
    const modal=page.locator(".flow-alert--modal:visible");
    await modal.locator("input").fill("Manhã");
    await button("Salvar",modal).click();
    const capture=button("Gerar captura",panel);
    async function generate(){
      const uploaded=page.waitForResponse(r=>r.url().includes("action=enquadramento_capturar"));
      await capture.click();assert.equal((await uploaded).status(),200);
      await page.waitForFunction(()=>!document.querySelector(".photo-actions button").disabled);
    }
    await generate();
    async function openLibrary(){await panel.locator("[data-library] button").click();await page.locator(".photo-library[open] [data-row]").first().waitFor();}
    await openLibrary();
    await button("Duplicar",page.locator("[data-row]").first()).click();
    await modal.locator("input").fill("Tarde");await button("Salvar",modal).click();
    const card=name=>page.locator("[data-row]").filter({has:page.locator("strong",{hasText:name})});
    await button("Abrir",card("Tarde")).click();
    await button("Tarde",panel).click();
    await button("Salvar enquadramento",panel).click();
    await page.waitForResponse(r=>r.url().includes("action=enquadramentos_listar"));
    await generate();await openLibrary();
    await card("Manhã").getByRole("checkbox").check();await card("Tarde").getByRole("checkbox").check();
    await button("Comparar",page.locator("dialog[open] footer")).click();
    const compare=page.locator(".photo-comparison[open]");
    await compare.locator("img").last().waitFor();
    await page.waitForFunction(()=>[...document.querySelectorAll(".photo-comparison img")].every(i=>i.naturalWidth>0));
    for(const toast of await page.locator(".flow-alert--toast .flow-alert__close:visible").all())await toast.click();
    await screenshot("04-comparacao");
    await button("Preferir",compare).first().click();await compare.waitFor({state:"hidden"});
    await button("Aprovar para produção",card("Manhã")).click();
    await button("Aprovar",modal).click();
    await card("Manhã").getByText("✓ Aprovado").waitFor();
    const downloaded=page.waitForEvent("download");
    await button("Exportar câmera",card("Manhã")).click();
    const exported=JSON.parse(fs.readFileSync(await (await downloaded).path()));
    assert.equal(exported.composicao.solar.time,"08:00");
    await button("Fechar",page.locator(".photo-library[open]")).click();
    await button("Sair da fotografia (Esc)").click();
    await button("Orbital").click();
    await page.locator(".viewport").getByText("Mais ferramentas",{exact:true}).count();
    await page.locator(".hud-menu").locator("..").locator("summary").click();
    await page.getByLabel("Marcadores de câmeras").selectOption("chosen");
    await page.locator(".photo-marker").first().waitFor({state:"attached"});
    assert.equal(totals.assets,assetsBefore,"O GLB ou o avatar foi recarregado durante a fotografia");
    assert.equal(totals.captures,2);
    await page.reload({waitUntil:"domcontentloaded"});await page.locator('.viewport[data-geo-state="ready"]').waitFor({timeout:120000});
    await page.locator(".photo-library-launch").click();await page.locator("[data-image]").selectOption("31");
    await button("Abrir",card("Manhã")).click();
    await panel.waitFor();assert.equal(await panel.locator('[data-field=time]').inputValue(),"08:00");
    assert.equal(await panel.locator('[data-field=focal]').inputValue(),"28");
    assert.equal(await button("Salvar como cópia",panel).count(),1);
    await button("Sair da fotografia (Esc)").click();
    await page.evaluate(()=>localStorage.setItem("theme","light"));
    await page.reload({waitUntil:"domcontentloaded"});
    await page.locator('.viewport[data-geo-state="ready"]').waitFor({timeout:120000});
    assert.equal(await page.locator("html").getAttribute("data-theme"),"light");
    await screenshot("06-orbital-claro");
    await button("Caminhada").click();
    const scene=page.locator("canvas.gl");
    const canvas=await scene.boundingBox();
    await scene.click({position:{x:Math.round(canvas.width*.32),y:Math.round(canvas.height*.71)}});
    await page.locator(".photo-trigger").waitFor({timeout:30000});
    await screenshot("07-caminhada");
    await page.keyboard.press("f");
    await panel.waitFor();
    await page.keyboard.press("Escape");
    await panel.waitFor({state:"hidden"});
    assert.equal(await button("Caminhada").getAttribute("aria-pressed"),"true");
    assert.deepEqual(errors,[]);
    console.log("OK: imagem, recorte, salvar, duplicar, duas capturas, revisão, aprovação, comparação, exportação, marcador e reabertura.");
  }catch(e){
    if(page){console.error((await page.locator("body").innerText().catch(()=>"")).slice(-1800));}
    throw e;
  } finally {await browser.close();if(api)api.dispose();}
}
main().catch(e=>{console.error(e);process.exitCode=1;});
