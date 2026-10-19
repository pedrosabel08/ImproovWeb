import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
class Element {
  constructor(tag) { this.tagName = tag; this.children = []; this.dataset = {}; this.attributes = {}; this.ownText = ''; this.style = {setProperty() {}}; this.classList = {add() {}}; }
  set textContent(value) { this.ownText = String(value); }
  get textContent() { return this.ownText + this.children.map(c => c.textContent).join(' '); }
  append(...children) { this.children.push(...children); }
  setAttribute(key,value) { this.attributes[key] = value; }
  getAttribute(key) { return this.attributes[key] ?? null; }
  addEventListener() {}
}
const window = {};
vm.runInNewContext(fs.readFileSync('assets/js/components/imagem-timeline.js','utf8'), {window, URL, document:{baseURI:'https://improov/ImproovWeb/MapaPavimento/index.php',createElement:tag=>new Element(tag)}});
const {render,summary} = window.FlowImagemTimeline;
const examples = JSON.parse(fs.readFileSync('docs/imagem-contexto-operacional-exemplos.json','utf8')).casos;
const walk = root => [root,...root.children.flatMap(walk)];
for (const mode of ['compacto','completo']) {
  for (const context of Object.values(examples)) {
    const output = render(context,mode);
    assert.deepEqual(walk(output).filter(e=>e.tagName==='li').map(e=>e.dataset.funcaoImagemId),context.etapas.map(s=>s.funcao_imagem_id));
    for (const stage of context.etapas) assert.ok(output.textContent.includes(stage.status),'Status original deve ser preservado.');
  }
}
assert.ok(summary(examples.E).textContent.includes('2 etapas ativas'));
assert.ok(summary(examples.E).textContent.includes('Modelagem'));
assert.ok(summary(examples.E).textContent.includes('Composição'));
assert.ok(summary(examples.E).textContent.includes('Possui restrição de início'));
assert.ok(summary(examples.B).textContent.includes('Todas as etapas cadastradas concluídas'));
assert.ok(!summary(examples.B).textContent.includes('Imagem finalizada'));
const repeated = structuredClone(examples.E);
repeated.etapas.push({...repeated.etapas[0],funcao_imagem_id:99999});
assert.equal(walk(render(repeated)).filter(e=>e.tagName==='li').length,repeated.etapas.length,'Não fundir funções repetidas.');
const mixed = structuredClone(examples.B);
mixed.etapas.push({funcao_imagem_id:99999,estado_visual:null,status:'Não se aplica'});
assert.ok(!summary(mixed).textContent.includes('Todas as etapas cadastradas concluídas'),'Não atribuir conclusão a tarefa dispensada.');
for (const stage of repeated.etapas) {
  const task = window.FlowImagemTimeline.taskLink(stage);
  const review = window.FlowImagemTimeline.reviewLink(stage, repeated);
  assert.equal(new URL(task.href).searchParams.get('focus_task'), String(stage.funcao_imagem_id));
  assert.equal(new URL(review.href).searchParams.get('funcao_imagem_id'), String(stage.funcao_imagem_id));
  assert.equal(task.textContent, 'Abrir card da tarefa');
  assert.equal(review.textContent, 'Abrir FlowReview');
}
const onlyPending = structuredClone(examples.E);
onlyPending.etapas[0].estado_visual = 'PENDENTE';
onlyPending.etapas_ativas = [];
onlyPending.etapas_pendentes = [onlyPending.etapas[0].funcao_imagem_id];
assert.ok(summary(onlyPending).textContent.includes('Próxima etapa'));
assert.ok(summary(onlyPending).textContent.includes(onlyPending.etapas[0].funcao_nome));
const temporalContext = {etapas:[{estado_visual:'BLOQUEADA',contexto_planejamento:{status_temporal_individual:{codigo:'BLOQUEADO',dias:2}}},{estado_visual:'CONCLUIDA',contexto_planejamento:{status_temporal_individual:{codigo:'CONCLUIDO_COM_ATRASO',dias:3}}}]};
assert.equal(window.FlowImagemTimeline.stats(temporalContext).late,1,'Prazo vencido informado em tarefa bloqueada conta; atraso histórico concluído não permanece em aberto.');
console.log('OK: apresentação fiel ao contrato, etapas simultâneas, status reais, avisos, repetição e conclusão sem promoção da imagem.');
const temporal = structuredClone(examples.E);
temporal.hoje = '2026-10-16';
temporal.etapas.forEach(s => { s.responsavel_id = null; s.responsavel_nome = null; });
const chart = window.FlowImagemTimeline.overview(temporal);
assert.equal(walk(chart).filter(e => e.className?.startsWith('it-time-row')).length, temporal.etapas.length);
assert.ok(chart.textContent.includes('Hoje 16/10'));
assert.ok(!chart.textContent.includes('Resumo da imagem'));
assert.ok(render(temporal).textContent.includes('Sem responsável'));
assert.ok(walk(chart).some(e => e.innerHTML?.includes('ri-')));
const plannedStage = {id_etapa:'planejada:7:4',funcao_imagem_id:null,funcao_nome:'Finalização',somente_planejada:true,estado_visual:'PENDENTE',tarefas:[],status:'TODO',contexto_planejamento:{prazo_planejado:'2026-11-03'}};
const withFlow = {...temporal,fluxo:{...temporal,etapas:[plannedStage],etapas_ativas:[],etapas_pendentes:[plannedStage.id_etapa],etapas_concluidas:[],etapas_bloqueadas:[]}};
const plannedOutput = render(withFlow);
assert.ok(plannedOutput.textContent.includes('Planejada'));
assert.ok(plannedOutput.textContent.includes('Sem responsável'));
assert.equal(walk(plannedOutput).filter(e=>e.tagName==='a').length,0,'Não criar links com ID inexistente.');
assert.equal(walk(window.FlowImagemTimeline.overview(withFlow)).filter(e=>e.className?.startsWith('it-time-row')).length,1);
