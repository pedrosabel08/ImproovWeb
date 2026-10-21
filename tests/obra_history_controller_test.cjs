const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
require('../assets/js/components/obra-history.js');

async function main() {
  let actions, lastInput, sent, succeed = true, saves = 0, submitted;
  const node = tag => ({
    tag, children: [], handlers: {}, value: '',
    append(...items) { this.children.push(...items); },
    addEventListener(type, callback) { this.handlers[type] = callback; },
    setAttribute() {}, replaceWith() {}, focus() {}, remove() {},
    submit() { submitted = Object.fromEntries(this.children.map(item => [item.name, item.value])); },
  });
  const rows = Array.from({ length: 200 }, (_, i) => ({ id: i + 1, data: '2026-10-21', assunto: `Evento ${i}`, tipo: 'arquivo' }));
  const span = node('span');
  const sandbox = {
    console, URL, document: {
      baseURI: 'http://localhost/ImproovWeb/Dashboard/obra.php',
      getElementById() { return node('div'); }, body: node('body'),
      createElement(tag) { const item = node(tag); if (tag === 'input') lastInput = item; return item; },
    },
    FlowObraHistory: { ...globalThis.FlowObraHistory, mount(container, options) {
      actions = options;
      return { setRows() {}, refresh() {}, state: () => ({ category: 'arquivos', period: 'all', query: '' }) };
    } },
    window: { __acompFetched: rows },
    idObra: 12, OBRA_ACOMP_CACHE: new Map([['12', rows]]),
    async salvarAcompanhamento(...args) { saves++; sent = args; return succeed; },
    Toastify() { return { showToast() {} }; },
    FlowAlert: { async confirm() { return { isConfirmed: false }; } },
    renderAcompanhamentosList() {},
  };
  vm.createContext(sandbox);
  vm.runInContext(fs.readFileSync('Dashboard/obra-history-controller.js', 'utf8'), sandbox);
  sandbox.renderObraHistory(rows);
  const title = { querySelector(selector) { return selector === 'input' ? null : span; } };
  const record = { id: 7, assunto: 'Referência original', tipo: 'arquivo' };
  actions.edit(record, title); lastInput.value = 'Referência atualizada'; await lastInput.handlers.blur();
  assert.deepEqual(sent, [7, 12, 'Referência atualizada', 'arquivo'], 'edição mantém o tipo de arquivo');
  assert.equal(record.assunto, 'Referência atualizada');
  assert(!sandbox.OBRA_ACOMP_CACHE.has('12'), 'edição invalida cache');
  succeed = false;
  actions.edit(record, title); lastInput.value = 'Não persistida'; await lastInput.handlers.blur();
  assert.equal(record.assunto, 'Referência atualizada', 'falha de gravação mantém o assunto original');
  actions.edit(record, title); lastInput.value = '   '; await lastInput.handlers.blur();
  assert.equal(saves, 2, 'texto vazio não é gravado');
  await actions.remove(record);
  assert.equal(sandbox.window.__acompFetched.length, 200, 'cancelar exclusão mantém a coleção');
  sandbox.exportObraHistory(12, 'arquivos');
  assert.equal(submitted.idobra, 12);
  assert.equal(JSON.parse(submitted.registro_ids).length, 200, 'PDF inclui registros além do lote visível de 80');
  console.log('Histórico: edição, falha de gravação, cancelamento e PDF completo passaram.');
}
main().catch(error => { console.error(error); process.exitCode = 1; });
