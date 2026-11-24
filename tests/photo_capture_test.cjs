const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const os = require("node:os");
const crypto = require("node:crypto");
const { execFileSync, spawnSync } = require("node:child_process");

const root = path.resolve(__dirname, "..");
const php = process.env.FLOW_QA_PHP || "php";
const cgi = process.env.FLOW_QA_PHP_CGI || "php-cgi";

function isolatedApi(obra = 10, model = 11, version = 51) {
  const schema = "qa_photo_" + crypto.randomBytes(8).toString("hex");
  const password = crypto.randomBytes(16).toString("hex");
  const port = Number(process.env.FLOW_QA_DB_PORT || 33791);
  const directory = fs.mkdtempSync(path.join(os.tmpdir(), "flow-photo-"));
  const settings = JSON.stringify({ schema, password, port, obra, model, version });
  const database = cmd => execFileSync(php, [path.join(__dirname,"photo_qa_database.php"),cmd], {input:settings});
  database("create");
  const csrf = crypto.randomBytes(32).toString("hex");
  const session = crypto.randomBytes(16).toString("hex");
  // Session fixture belongs exclusively to this PHP-CGI process and temporary save_path.
  fs.writeFileSync(path.join(directory,"sess_"+session), 'logado|b:1;nivel_acesso|i:1;idcolaborador|i:21;mapa_pavimento_csrf|s:64:"' + csrf + '";');
  function request(action, { method="GET", query={}, data, type="application/json", token=csrf, authenticated=true }={}) {
    const search = new URLSearchParams({action,...query}).toString();
    const input = data == null ? Buffer.alloc(0) : Buffer.isBuffer(data) ? data : Buffer.from(JSON.stringify(data));
    const file = path.join(root,"MapaPavimento","api.php");
    const proc = spawnSync(cgi, ["-d","session.save_path="+directory,"-d","display_errors=0"], {
      cwd:root, input, maxBuffer:12*1024*1024,
      env:{...process.env, DB_HOST:"127.0.0.1",DB_PORT:String(port),DB_DATABASE:schema,DB_USERNAME:schema,DB_PASSWORD:password,
        FLOW_PHOTO_STORAGE_ROOT:path.join(directory,"media"), SCRIPT_FILENAME:file,SCRIPT_NAME:"/ImproovWeb/MapaPavimento/api.php",
        REQUEST_URI:"/ImproovWeb/MapaPavimento/api.php?"+search,QUERY_STRING:search, REQUEST_METHOD:method,
        SERVER_PROTOCOL:"HTTP/1.1",GATEWAY_INTERFACE:"CGI/1.1",SERVER_NAME:"localhost",SERVER_PORT:"8066",REDIRECT_STATUS:"1",
        DOCUMENT_ROOT:path.dirname(root),HTTP_COOKIE:authenticated?"PHPSESSID="+session:"",HTTP_X_CSRF_TOKEN:token,
        CONTENT_TYPE:type,CONTENT_LENGTH:String(input.length)}
    });
    if(proc.error)throw proc.error;
    const separator=proc.stdout.indexOf("\r\n\r\n");
    assert.ok(separator>=0, "Resposta CGI incompleta");
    const headers=proc.stdout.subarray(0,separator).toString("latin1");
    const status=Number(headers.match(/^Status: (\d+)/im)?.[1]||200);
    const contentType=headers.match(/^Content-Type: ([^\r\n]+)/im)?.[1]||"application/octet-stream";
    const body=proc.stdout.subarray(separator+4);
    return {status,contentType,body,json:()=>JSON.parse(body.toString("utf8"))};
  }
  return {request, csrf, dispose(){
    database("dispose");
    fs.rmSync(directory,{recursive:true,force:true});
  }};
}

async function main(){
  const api=isolatedApi();
  try {
    const list=()=>api.request("enquadramentos_listar",{query:{obra_id:10,modelo_id:11}});
    assert.equal(list().status,200, list().body.toString());
    const identity=[1,0,0,0,0,1,0,0,0,0,1,0,0,0,0,1];
    const data={obra_id:10,versao_id:51,imagem_id:31,nome:"Luz de teste",operacao:"criar",chave:crypto.randomUUID(),
      composicao:{schema:1,source:"seagull",local:identity,modelToScene:identity,vertical:false,unit:"m",projection:"perspective",
      focal:24,aspect:.8,height:12,roll:0,shiftY:0,fov:50,solar:{enabled:false,config:null,date:"2026-11-24",time:"08:00"}}};
    const write=(payload,opts={})=>api.request("enquadramento_gravar",{method:"POST",data:payload,...opts});
    assert.equal(write(data,{authenticated:false}).status,401);
    assert.equal(write(data,{token:"invalid"}).status,403);
    const saved=write(data);assert.equal(saved.status,200,saved.body.toString());
    const id=saved.json().id; assert.ok(id>0);
    const jpeg = execFileSync(php,["-r","$im=imagecreatetruecolor(1638,2048);imagejpeg($im);"]);
    async function capture(revision,bytes=jpeg) {
      const form=new FormData();
      for(const [key,value] of Object.entries({obra_id:10,id,revisao:revision}))form.set(key,value);
      form.set("captura",new Blob([bytes],{type:"image/jpeg"}),"captura.jpg");
      const body=new Response(form);
      return api.request("enquadramento_capturar",{method:"POST",data:Buffer.from(await body.arrayBuffer()),type:body.headers.get("content-type")});
    }
    const file=(tipo,obra_id=10)=>api.request("enquadramento_arquivo",{query:{id,tipo,obra_id}});
    assert.equal(file("captura").status,404);
    assert.ok((await capture(1,Buffer.from("invalid"))).status>=400);
    const received=await capture(1);assert.equal(received.status,200,received.body.toString());
    assert.equal((await capture(1)).status,409);
    assert.equal((await capture(2)).status,200);
    assert.equal(list().json().enquadramentos.length,1);
    for(const [kind,expected] of [["miniatura",[256,320]],["captura",[1638,2048]]]) {
      const jpg=file(kind);assert.equal(jpg.contentType,"image/jpeg");
      const dims=execFileSync(php,["-r","echo json_encode(array_slice(getimagesizefromstring(stream_get_contents(STDIN)),0,2));"],{input:jpg.body});
      assert.deepEqual(JSON.parse(dims),expected);
    }
    assert.equal(file("captura",20).status,403);
    assert.equal(write({...data,id,operacao:"aprovar",revisao:3}).status,200);
    assert.equal((await capture(4)).status,409);
    console.log("OK: autenticação, CSRF, ausência de captura automática, multipart, recorte, miniatura, repetição, isolamento da obra e captura aprovada.");
  } finally {api.dispose();}
}
module.exports={isolatedApi};
if(require.main===module)main().catch(e=>{console.error(e);process.exitCode=1;});
