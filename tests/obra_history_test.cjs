const assert = require('node:assert/strict');
require('../assets/js/components/obra-history.js');
const h = globalThis.FlowObraHistory;
const rows = [
  { id: 1, data: '2026-10-21', tipo: 'manual', assunto: 'Aprovação do paisagismo', usuario_nome: 'Ana', metadata: { grupo: 'Fachada' } },
  { id: 2, data: '2026-10-20 14:32:00', tipo: 'arquivo', assunto: 'Referências', metadata: { ambiente: 'Fachada' } },
  { id: 3, data: '2026-09-01', tipo: 'entrega', assunto: 'Entrega R00', entrega: { etapa: 'R00' } },
  { id: 4, data: null, tipo: 'PROJECT_START', assunto: 'Início' },
];
const now = new Date('2026-10-21T17:00:00');
assert.equal(h.date(rows[0]).getDate(), 21, 'DATE deve preservar o dia no fuso local');
assert.equal(h.date({ data: '0000-00-00' }), null);
assert.equal(h.date({ data: 'invalid' }), null);
assert.equal(h.kind(rows[0]), 'manuais');
assert.equal(h.kind({ tipo: 'aprovacao' }), 'aprovacoes');
assert.equal(h.kind({ tipo: 'manual', assunto: 'Aguardando aprovação' }), 'manuais');
assert.equal(h.category(rows[0]), 'manuais', 'aprovação continua no filtro manual legado');
assert.equal(h.kind(rows[3]), 'onboarding');
assert.equal(h.category({ tipo: 'upload' }), 'arquivos');
assert.equal(h.category({ tipo: 'entregas' }), 'entregas');
assert(h.search(rows[0], 'aprovacao ANA fachada'), 'busca ignora acentos e procura autor/grupo');
assert(h.search(rows[2], 'r00'));
assert(h.search({ metadata: { pacotes: null } }, ''));
assert.deepEqual(h.filter(rows, { category: 'todos', query: 'fachada', period: '7' }, now).map(r => r.id), [1, 2]);
assert.deepEqual(h.filter(rows, { category: 'entregas', query: '', period: 'all' }, now).map(r => r.id), [3]);
assert.deepEqual(h.filter(rows, { category: 'manuais', kind: 'onboarding', query: '', period: 'all' }, now).map(r => r.id), [4], 'tipo visual filtra dentro da categoria manual');
assert.deepEqual(h.filter(rows, { category: 'manuais', kind: 'manuais', query: '', period: 'all' }, now).map(r => r.id), [1]);
assert.equal(h.filter(rows, { category: 'manuais', kind: 'aprovacoes', query: '', period: 'all' }, now).length, 0);
assert(!h.inPeriod(rows[2], '30', now));
assert(h.inPeriod(rows[2], '90', now));
assert(!h.inPeriod({ data: '2026-10-22' }, '7', now), 'não incluir datas futuras');
assert(h.inPeriod({ data: '2026-10-15' }, '7', now));
assert(!h.inPeriod({ data: '2026-10-14' }, '7', now));
assert(h.inPeriod({ data: '2026-07-31' }, '90', new Date('2026-10-31T12:00:00')));
assert(h.inPeriod({ data: '2026-02-28' }, '90', new Date('2026-05-31T12:00:00')), 'subtração de meses ajusta o último dia');
assert.equal([...h.group([...rows].reverse()).keys()][0], '2026-10-21');
assert.equal(h.consolidate([rows[1], { ...rows[1], id: 5 }]).length, 2, 'textos/datas iguais não provam um lote');
const batch = [2, 5].map(id => ({ ...rows[1], id, metadata: { upload_batch_id: 22, grupo: 'Fachada' } }));
assert.equal(h.consolidate(batch).length, 1);
assert.equal(h.consolidate([batch[0], { ...batch[1], metadata: { ...batch[1].metadata, grupo: 'Lazer' } }]).length, 2);
assert.equal(rows.length, 4, 'filtros não alteram os registros de origem');
console.log('Histórico: datas, busca, classificação, filtros e consolidação segura passaram.');
