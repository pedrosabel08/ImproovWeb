import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const file = new URL('../MapaPavimento/predio-3d/empreendimento-map-bridge.js', import.meta.url);
const source = (await readFile(file, 'utf8'))
  .replace(/^import .*;\r?\n/gm, '')
  .replaceAll('import.meta.url', JSON.stringify(file.href));
const { EmpreendimentoMapBridge } = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'));
const saved = Object.fromEntries(['location','history','document','matchMedia'].map(key => [key, globalThis[key]]));
const element = () => ({ textContent:'', disabled:false, hidden:true, style:{},
  classList:{ add(){},remove(){} }, setAttribute(){}, focus(){},
  animate(){ return { finished:Promise.resolve() }; } });
try {
  globalThis.location = { origin:'http://localhost:8066', href:'http://localhost:8066/ImproovWeb/MapaPavimento/predio-3d/visualizador-glb-navegacao.php?obra_id=102' };
  globalThis.history = { replaceState(){} };
  const nodes = new Map();
  globalThis.document = { getElementById(id){ if (!nodes.has(id)) nodes.set(id,element()); return nodes.get(id); }, querySelector(){ return element(); } };
  globalThis.matchMedia = () => ({ matches:true });
  const bridge = Object.create(EmpreendimentoMapBridge.prototype);
  const previous = { id:'LAZER' }, next = { id:'TIPO' };
  const calls = [];
  Object.assign(bridge, { obraId:102, bridgeId:'test-bridge', mapShown:true, busy:false,
    currentFloor:previous, expectedLink:{subtipo_id:61,referencia_id:1},
    links:new Map([['TIPO',{object_name:'TIPO',subtipo_id:63,mapa_configurado:true}]]),
    subtypes:[{id:63,nome:'Tipo'}], pendingMap:null, loadedMapKey:'102:61:1',
    hooks:{ findFloor:id => id === 'TIPO' ? next : null, status(){},
      focusFloor:async floor => calls.push(['focus',floor.id]),
      setMapVisible:value => calls.push(['visible',value]) },
    app:element(), stage:element(), background:[], buildingButton:element(), mapButton:element(), mapError:element(),
    setBusy(value){ calls.push(['busy',value]); }, showSummary(floor){ this.currentFloor = floor; },
  });
  const send = (type, extra = {}, source = bridge.frame.contentWindow) => bridge.onMessage({
    origin:location.origin, source, data:{type,bridge:'test-bridge',obra_id:102,subtipo_id:63,...extra},
  });
  bridge.frame = { contentWindow:{postMessage(){ send('mp:leave-ok'); }} };
  send('mp:context-change',{},{});
  assert.equal(bridge.currentFloor,previous,'Mensagens de outra janela devem ser recusadas.');
  send('mp:context-change');
  assert.equal(bridge.currentFloor,next);
  assert.equal(bridge.mapShown,true);
  assert.deepEqual(calls,[],'Trocar a planta não deve carregar nem reposicionar o prédio.');
  send('mp:ready',{referencia_id:2});
  assert.equal(bridge.loadedMapKey,'102:63:2');
  bridge.frame.contentWindow.postMessage = () => send('mp:leave-blocked',{message:'Conclua a edição.'});
  await bridge.requestReturn();
  assert.equal(bridge.mapShown,true,'Edição em andamento deve manter a planta aberta.');
  assert.equal(calls.length,0,'Uma saída bloqueada não deve mover nem carregar o prédio.');
  bridge.frame.contentWindow.postMessage = () => send('mp:leave-ok');
  await bridge.requestReturn();
  assert.equal(bridge.mapShown,false);
  assert.deepEqual(calls.filter(call => call[0] === 'focus'),[['focus','TIPO']],
    'O pavimento correto só deve ser enquadrado ao voltar ao 3D.');
  assert.equal(bridge.focusOnReturn,false);
  console.log('OK: troca 2D local, origem/janela verificadas, referência sincronizada e foco 3D adiado até o retorno.');
} finally {
  for (const [key,value] of Object.entries(saved)) {
    if (value === undefined) delete globalThis[key]; else globalThis[key] = value;
  }
}
