const { test } = require("node:test");
const assert = require("node:assert/strict");
const M = require("../../Entregas/central-model.js");
const today = "2026-11-27";
const entry = (id, fields = {}) => ({
  id,
  obra_id: 1,
  nomenclatura: "OBRA_Á",
  nome_etapa: "R01",
  kanban_status: "pendente",
  data_prevista: today,
  ready_count: 0,
  ...fields,
});
const state = (fields) => ({
  obra: "",
  stage: "",
  search: "",
  quick: "all",
  day: "",
  ...fields,
});

test("date boundaries reject missing or impossible dates; valid timestamps keep the date", () => {
  for (const value of [null, "", "0000-00-00", "2026-02-30", "2026-13-01"])
    assert.equal(M.date(value), "");
  assert.equal(M.date("2026-11-27 15:30:00"), today);
  assert.equal(M.addDays("2026-12-31", 1), "2027-01-01");
  assert.equal(M.addDays("2024-02-28", 1), "2024-02-29");
  assert.equal(M.daysBetween("2026-03-07", "2026-03-09"), 2);
});
test("completed and paused deliveries never become ready or upcoming from production alone", () => {
  const completed = entry(1, { kanban_status: "concluida", ready_count: 3 });
  const hold = entry(2, { em_hold: true, ready_count: 2 });
  const production = entry(3, {
    producao: { etapa: "Finalização", concluida: true },
  });
  assert.equal(M.ready(completed), false);
  assert.equal(M.ready(hold), false);
  assert.equal(M.ready(production), false);
  assert.equal(M.ready(entry(4, { ready_count: 1 })), true);
  for (const e of [completed, hold])
    assert.equal(M.quick(e, "today", today), false);
});
test("backend status distinguishes overdue open deliveries from completed late deliveries", () => {
  assert.equal(
    M.quick(entry(1, { kanban_status: "atrasada" }), "late", today),
    true,
  );
  assert.equal(
    M.quick(
      entry(2, { kanban_status: "concluida", status: "Entregue com atraso" }),
      "late",
      today,
    ),
    false,
  );
  assert.equal(
    M.group(
      entry(3, { kanban_status: "concluida", review_batches_overdue: 2 }),
    ),
    "sent",
  );
  assert.equal(M.group(entry(4, { review_batches_overdue: 1 })), "attention");
  assert.equal(
    M.group(entry(5, { em_hold: true, review_batches_overdue: 1 })),
    "pending",
  );
});
test("ready totals count images across deliveries of the same work and preserve the delivery count", () => {
  const list = [
    entry(1, { ready_count: 2, total_itens: 10, entregues: 4 }),
    entry(2, { ready_count: 5, total_itens: 8, entregues: 1 }),
    entry(3, { ready_count: 3, kanban_status: "concluida" }),
    entry(4, { ready_count: 4, em_hold: true }),
    entry(5, { ready_count: 0 }),
    entry(6, { obra_id: 2, nome_etapa: "R02", ready_count: 3 }),
  ];
  assert.deepEqual(M.readySummary(list), { images: 10, deliveries: 3 });
  assert.deepEqual(M.readySummary([]), { images: 0, deliveries: 0 });
  assert.equal(M.pendingCount(list[0]), 6);
  assert.equal(M.readyCount(list[0]), 2);
  assert.deepEqual(M.readySummary(M.baseFilter(list, state({ obra: "1" }))), {
    images: 7,
    deliveries: 2,
  });
  assert.deepEqual(
    M.readySummary(M.filter(list, state({ stage: "R02" }), today)),
    { images: 3, deliveries: 1 },
  );
});
test("seven-day range includes today and the sixth following day, excluding the seventh", () => {
  const list = [
    entry(1),
    entry(2, { data_prevista: "2026-12-03" }),
    entry(3, { data_prevista: "2026-12-04" }),
    entry(4, { data_prevista: "2026-11-26" }),
    entry(5, { data_prevista: null }),
  ];
  assert.deepEqual(
    M.filter(list, state({ quick: "week" }), today).map((e) => e.id),
    [1, 2],
  );
  assert.equal(
    M.quick(entry(6, { data_prevista: "2026-11-28" }), "tomorrow", today),
    true,
  );
});
test("combined work, stage, search, quick and date filters share one data source", () => {
  const list = [
    entry(1, { ready_count: 2 }),
    entry(2, { obra_id: 2 }),
    entry(3, { nome_etapa: "R02" }),
    entry(4, { data_prevista: "2026-11-28", ready_count: 1 }),
  ];
  assert.deepEqual(
    M.filter(
      list,
      state({
        obra: "1",
        stage: "r01",
        search: "obra_a",
        quick: "ready",
        day: today,
      }),
      today,
    ).map((e) => e.id),
    [1],
  );
  assert.deepEqual(
    M.filter(list, state({ search: "4" }), today).map((e) => e.id),
    [4],
  );
  assert.equal(
    M.filter(list, state({ search: "inexistente" }), today).length,
    0,
  );
  assert.equal(list.length, 4);
});
test("sorting does not mutate source and missing deadlines sort last", () => {
  const list = [
    entry(1, { data_prevista: null, pct_entregue: 100 }),
    entry(2, { data_prevista: "2026-11-28", pct_entregue: 40 }),
    entry(3, { pct_entregue: 80 }),
  ];
  assert.deepEqual(
    M.sort(list, "deadline").map((e) => e.id),
    [3, 2, 1],
  );
  assert.deepEqual(
    M.sort(list, "progress").map((e) => e.id),
    [1, 3, 2],
  );
  assert.deepEqual(
    list.map((e) => e.id),
    [1, 2, 3],
  );
});
test("calendar spans use real received and deadline dates and clip only for display", () => {
  const e = entry(1, {
    data_recebimento: "2026-11-20",
    data_prevista: "2026-12-02",
  });
  assert.deepEqual(M.span(e, "2026-11-23", "2026-11-29"), {
    from: "2026-11-23",
    to: "2026-11-29",
    start: "2026-11-20",
    due: "2026-12-02",
  });
  assert.equal(
    M.span(entry(2, { data_prevista: null }), "2026-11-23", "2026-11-29"),
    null,
  );
  assert.equal(
    M.span(
      entry(3, { data_prevista: "2026-11-22" }),
      "2026-11-23",
      "2026-11-29",
    ),
    null,
  );
  for (const received of [null, "2026-12-10", "0000-00-00"]) {
    const span = M.span(
      entry(4, { data_recebimento: received }),
      "2026-11-23",
      "2026-11-29",
    );
    assert.equal(span.from, today);
    assert.equal(span.to, today);
  }
});
test("segmented progress partitions delivered, ready and remaining images without overlap", () => {
  const p = M.progress(
    entry(1, { total_itens: 14, entregues: 10, ready_count: 2 }),
  );
  assert.deepEqual([p.delivered, p.ready, p.other, p.pending], [10, 2, 2, 4]);
  assert.equal(p.pct, 71.4);
  assert.ok(
    Math.abs(p.deliveredPercent + p.readyPercent + p.otherPercent - 100) <
      1e-10,
  );
  assert.ok(Math.abs(p.readyPercent - 14.2857142857) < 1e-8);
  // Availability-only updates must leave delivered percentage unchanged.
  const next = M.progress(
    entry(1, { total_itens: 14, entregues: 10, ready_count: 3 }),
  );
  assert.equal(next.pct, p.pct);
  assert.equal(next.ready, 3);
  assert.equal(next.other, 1);
  const excess = M.progress(
    entry(2, { total_itens: 14, entregues: 10, ready_count: 20 }),
  );
  assert.deepEqual([excess.delivered, excess.ready, excess.other], [10, 4, 0]);
  assert.equal(
    M.progress(
      entry(3, {
        total_itens: 14,
        entregues: 10,
        ready_count: 2,
        em_hold: true,
      }),
    ).ready,
    0,
  );
  const zero = M.progress(
    entry(4, { total_itens: 0, entregues: 10, ready_count: 2 }),
  );
  assert.deepEqual(
    [zero.delivered, zero.ready, zero.other, zero.pct],
    [0, 0, 0, 0],
  );
  const complete = M.progress(
    entry(5, {
      total_itens: 14,
      entregues: 14,
      ready_count: 2,
      kanban_status: "concluida",
    }),
  );
  assert.deepEqual(
    [complete.deliveredPercent, complete.readyPercent, complete.otherPercent],
    [100, 0, 0],
  );
});
