// Exercises the real individual-view renderer and submit handler without network or a database.
const { test } = require("node:test");
const assert = require("node:assert/strict");
const vm = require("node:vm");
const fs = require("node:fs");
const path = require("node:path");

function setup() {
  const attributes = () => ({
    attrs: {},
    isConnected: true,
    setAttribute(key, value) {
      this.attrs[key] = value;
    },
    removeAttribute(key) {
      delete this.attrs[key];
    },
  });
  let form;
  const message = { textContent: "" };
  const card = {
    html: "",
    set innerHTML(html) {
      if (form) form.button.isConnected = false;
      this.html = html;
      form = html.includes('id="competencia-payment-form"')
        ? Object.assign(attributes(), {
            button: Object.assign(attributes(), {
              disabled: false,
              innerHTML: "Registrar quitação",
            }),
            querySelector() {
              return this.button;
            },
          })
        : null;
    },
    get innerHTML() {
      return this.html;
    },
    querySelector(selector) {
      return selector === "form" ? form : message;
    },
    append() {},
  };
  const selector = {
    value: "1",
    options: [{ value: "1", text: "Pessoa sintética" }],
  };
  const person = {
    colaborador_id: 1,
    nome: "Pessoa sintética",
    tipo_remuneracao: "VARIAVEL",
    pagamento_status: "PENDENTE",
    total_centavos: 325500,
    pago_centavos: 0,
    pendente_centavos: 325500,
    resumo: { SERVICOS: 325500 },
  };
  const data = {
    estado: "CONCLUIDO",
    previsto_em: "2026-10-06",
    colaboradores: [person],
  };
  const calls = [],
    animation = [],
    orb = [];
  const storage = new Map();
  const context = {
    document: {
      body: {
        dataset: { pagamentoInicio: "2026-09", paymentView: "colaborador" },
      },
      querySelector: (selector) =>
        selector.includes("csrf") ? { content: "synthetic-token" } : null,
      getElementById: (id) =>
        ({
          colaborador: selector,
          "competencia-individual": card,
          mes: { value: "9" },
          ano: { value: "2026" },
        })[id],
    },
    sessionStorage: {
      getItem: (key) => storage.get(key),
      setItem: (key, v) => storage.set(key, v),
      removeItem: (key) => storage.delete(key),
    },
    FormData: class {
      get(key) {
        return key === "data_pagamento" ? "2026-10-08" : "Teste isolado";
      }
    },
    CustomEvent: class {
      constructor(type) {
        this.type = type;
      }
    },
    crypto: { randomUUID: () => "synthetic-operation" },
    fetch: async (url, options) => {
      if (!options.method)
        return { ok: true, json: async () => ({ success: true, data }) };
      return new Promise((resolve) =>
        calls.push({ body: JSON.parse(options.body), resolve }),
      );
    },
    window: {
      pagamentoMotion: {
        stop: (root) => animation.push(["stop", root]),
        animate: (root) => animation.push(["animate", root]),
      },
      thinkingOrbs: (root) => {
        orb.push(["start", root]);
        return () => orb.push(["stop", root]);
      },
      dispatchEvent() {},
    },
  };
  vm.runInNewContext(
    fs.readFileSync(
      path.join(__dirname, "../Pagamento/competencia.js"),
      "utf8",
    ),
    context,
  );
  context.window.pagamentoAtualizarColaboradoresCompetencia = () => {};
  return {
    context,
    card,
    person,
    message,
    calls,
    animation,
    orb,
    storage,
    get form() {
      return form;
    },
  };
}

test("individual values join the shared money animation and stop before replacement", async () => {
  const s = setup();
  await s.context.window.pagamentoCarregarCompetencia("1", 9, 2026);
  assert.equal(
    (s.card.html.match(/data-number-kind="money"/g) || []).length,
    7,
  );
  assert.match(s.card.html, /data-payment-number="325500"/);
  assert.deepEqual(
    s.animation.map((a) => a[0]),
    ["stop", "animate"],
  );
  s.person.pago_centavos = null;
  await s.context.window.pagamentoCarregarCompetencia("1", 9, 2026);
  assert.equal(
    (s.card.html.match(/data-number-kind="money"/g) || []).length,
    6,
  );
});

test("submit shows the orb, blocks duplicates and restores the button on error", async () => {
  const s = setup();
  await s.context.window.pagamentoCarregarCompetencia("1", 9, 2026);
  const form = s.form,
    button = form.button;
  s.message.textContent = "Erro anterior";
  const pending = form.onsubmit({ preventDefault() {} });
  assert.equal(button.disabled, true);
  assert.equal(button.attrs["aria-busy"], "true");
  assert.match(button.innerHTML, /Registrando…/);
  assert.match(button.innerHTML, /data-orb-state="working"/);
  assert.equal(s.message.textContent, "");
  await form.onsubmit({ preventDefault() {} });
  assert.equal(s.calls.length, 1);
  s.calls[0].resolve({
    ok: false,
    status: 400,
    json: async () => ({ success: false, error: "Erro sintético" }),
  });
  await pending;
  assert.equal(button.disabled, false);
  assert.equal(button.innerHTML, "Registrar quitação");
  assert.equal(button.attrs["aria-busy"], undefined);
  assert.equal(form.attrs["aria-busy"], undefined);
  assert.equal(s.message.textContent, "Erro sintético");
  assert.equal(s.storage.size, 0);
  assert.equal(s.orb.at(-1)[0], "stop");
});

test("successful synthetic payment refreshes the values and disposes the orb", async () => {
  const s = setup();
  await s.context.window.pagamentoCarregarCompetencia("1", 9, 2026);
  const form = s.form,
    button = form.button;
  const pending = form.onsubmit({ preventDefault() {} });
  s.person.pagamento_status = "PAGO";
  s.person.pago_centavos = 325500;
  s.person.pendente_centavos = 0;
  s.person.pago_em = "2026-10-08";
  s.calls[0].resolve({
    ok: true,
    json: async () => ({ success: true, data: {} }),
  });
  await pending;
  assert.equal(s.form, null);
  assert.equal(button.isConnected, false);
  assert.match(s.card.html, /Pagamento registrado/);
  assert.match(s.card.html, /08\/10\/2026/);
  assert.equal(s.storage.size, 0);
  assert.equal(s.orb.at(-1)[0], "stop");
  assert.equal(s.animation.filter((a) => a[0] === "animate").length, 2);
});
