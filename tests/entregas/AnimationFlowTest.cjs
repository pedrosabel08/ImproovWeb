const { test } = require("node:test");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");
const M = require("../../Entregas/central-model.js");

test("refresh after successful registration animates old progress without GSAP", async () => {
  const source = fs.readFileSync("Entregas/central.js", "utf8");
  const method = source.match(
    /async finishDelivery\(refresh\) \{([\s\S]*?)\n      \},\n      setSelected/,
  );
  assert.ok(method, "delivery sequence exists");
  const widths = [];
  const segments = Object.fromEntries(
    ["delivered", "ready", "other"].map((name) => [
      name,
      {
        style: {
          set width(value) {
            if (name === "delivered") widths.push(parseFloat(value));
          },
          removeProperty() {},
        },
      },
    ]),
  );
  const bar = {
    querySelectorAll: () => Object.values(segments),
    querySelector: (s) => segments[s.slice(4)],
  };
  const counter = { textContent: "" },
    percentage = { textContent: "" };
  const el = {
    dataset: { id: "1" },
    querySelector: (s) =>
      s === ".delivery-progress"
        ? bar
        : s === ".central-progress small"
          ? counter
          : s === ".central-progress strong"
            ? percentage
            : null,
    getBoundingClientRect: () => ({ left: 0, top: 0, right: 100, bottom: 100 }),
  };
  const old = {
    id: 1,
    kanban_status: "pendente",
    total_itens: 14,
    entregues: 10,
    ready_count: 2,
  };
  const updated = { ...old, entregues: 11, ready_count: 1 };
  let now = 0,
    rendered;
  const context = {
    M,
    deliverySession: {
      id: "1",
      progress: M.progress(old),
      list: "pendente",
      registered: true,
    },
    sequencing: false,
    deferredData: null,
    all: [old],
    root: { querySelector: () => el, querySelectorAll: () => [el] },
    CSS: { escape: String },
    matchMedia: () => ({ matches: false }),
    window: {},
    innerWidth: 1000,
    innerHeight: 800,
    performance: { now: () => 0 },
    requestAnimationFrame: (cb) => setImmediate(() => cb((now += 100))),
    render: (data) => {
      rendered = data;
    },
    setTimeout,
    console,
  };
  vm.createContext(context);
  const finish = vm.runInContext(
    `(async function(refresh){${method[1]}\n})`,
    context,
  );
  await finish(async () => {
    context.deferredData = [updated];
  });
  assert.ok(widths.length > 2, "several progress frames were displayed");
  assert.ok(widths[0] < widths[widths.length - 1]);
  assert.equal(counter.textContent, "11/14 imagens entregues");
  assert.equal(percentage.textContent, "78,6%");
  assert.equal(rendered[0].entregues, 11);
});
