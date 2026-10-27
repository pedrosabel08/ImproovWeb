/* Isolated UI acceptance: real markup/CSS/JS, synthetic API, no database writes. */
const fs = require("fs");
const path = require("path");
const http = require("http");
const assert = require("node:assert/strict");
const { chromium } = require(
  process.env.FLOW_PLAYWRIGHT_MODULE || "playwright",
);
const root = path.resolve(__dirname, "../..");
const output =
  process.env.FLOW_PLANNING_QA_OUTPUT ||
  path.join(root, "output/planning-lifecycle");
fs.mkdirSync(output, { recursive: true });

function fixture(completed) {
  const names = [
    "Caderno + Filtro de Assets",
    "Modelagem da Fachada",
    "Modelagem Interna",
    "Composição",
    "Finalização Externa",
    "Finalização Interna",
    "Finalização Planta",
    "Finalização (marco global)",
    "Pós-Produção",
  ];
  const codes = [
    "CADERNO_FILTRO",
    "MODELAGEM_FACHADA",
    "MODELAGEM_INTERNA",
    "COMPOSICAO",
    "FINALIZACAO_EXTERNA",
    "FINALIZACAO_INTERNA",
    "FINALIZACAO_PLANTA",
    "FINALIZACAO_GLOBAL",
    "POS_PRODUCAO",
  ];
  const ends = completed
    ? [
        "2026-08-21",
        "2026-08-21",
        "2026-09-01",
        "2026-09-11",
        "2026-09-22",
        "2026-09-22",
        "2026-09-17",
        "2026-09-22",
        "2026-09-28",
      ]
    : [
        "2026-10-01",
        "2026-09-25",
        "2026-10-14",
        "2026-10-26",
        "2026-11-04",
        "2026-11-03",
        "2026-11-03",
        "2026-11-04",
        "2026-11-13",
      ];
  const starts = completed
    ? [
        "2026-08-12",
        "2026-08-12",
        "2026-08-21",
        "2026-09-01",
        "2026-09-11",
        "2026-09-11",
        "2026-09-11",
        "2026-09-11",
        "2026-09-22",
      ]
    : [
        "2026-09-16",
        "2026-09-16",
        "2026-10-01",
        "2026-10-14",
        "2026-10-26",
        "2026-10-26",
        "2026-10-26",
        "2026-10-26",
        "2026-11-04",
      ];
  const deps = [[], [], [0], [2], [3], [3], [3], [4, 5, 6], [7]];
  const etapas = codes.map((codigo, i) => ({
    codigo,
    nome: names[i],
    volume: [22, 1, 22, 22, 12, 20, 10, 42, 31][i],
    inicio: starts[i],
    limite: ends[i],
    duracao_dias_uteis: [11, 7, 8, 8, 6, 5, 5, 6, 7][i],
    pessoas_alocadas: [2, 1, 3, 3, 2, 4, 2, null, 1][i],
    capacidade_editavel: ![1, 7].includes(i),
    dependencias: deps[i].map((n) => codes[n]),
    caminho_critico: [0, 2, 3, 4, 7, 8].includes(i),
    metrica: {},
    formula: "Estimativa de produção por capacidade.",
  }));
  const realDates = completed
    ? [
        "2026-08-20",
        "2026-08-19",
        "2026-09-02",
        "2026-09-12",
        "2026-09-22",
        "2026-09-21",
        "2026-09-16",
        "2026-09-22",
        "2026-10-09",
      ]
    : ["2026-09-29", "2026-09-24", "2026-10-14"];
  return {
    obra: { nomenclatura: completed ? "RAY_BRH" : "CIB_HYP" },
    obra_id: 1,
    entrega_id: 1,
    data_inicio: starts[0],
    data_hoje: "2026-10-27",
    fim_previsto: ends[8],
    data_entrega: completed ? "2026-10-16" : "2026-11-13",
    margem_dias_uteis: completed ? 13 : 0,
    status_plano: completed ? "VIAVEL" : "ATENCAO",
    fonte: "VERSAO_CONFIRMADA",
    persistencia_disponivel: true,
    fingerprint: "a".repeat(64),
    etapas,
    planejamento: {
      estado: "CONFIRMADO",
      versao_atual_id: 9,
      lock_version: 1,
      historico: [
        {
          id: 9,
          numero: 2,
          tipo: "REPLANEJAMENTO",
          vigente: 1,
          confirmado_em: "2026-09-23 10:00:00",
          fim_previsto: ends[8],
          margem_dias_uteis: 0,
          status_plano: "ATENCAO",
        },
      ],
    },
    ciclo_vida: {
      estado: completed ? "CONCLUIDO" : "EM_ANDAMENTO",
      data_entrega_real: completed ? "2026-10-09" : null,
      entrega_realizada_em: completed ? "2026-10-09 16:00:00" : null,
      desvio_plano_dias_uteis: completed ? 9 : null,
      antecedencia_prazo_dias_corridos: completed ? 7 : null,
    },
    execucao: {
      disponivel: true,
      etapas: etapas.map((stage, i) => ({
        codigo: stage.codigo,
        execucao: realDates[i]
          ? "CONCLUIDA"
          : i === 3
            ? "EM_ANDAMENTO"
            : "NAO_INICIADA",
        percentual_concluido:
          i === 7 ? null : realDates[i] ? 100 : i === 3 ? 60 : 0,
        concluidas: realDates[i] ? stage.volume : 0,
        volume_atual: stage.volume,
        conclusao_real: realDates[i] || null,
        desvio_real_dias_uteis: realDates[i]
          ? [-1, -2, 1, 1, 0, -1, -1, 0, 9][i]
          : null,
      })),
      excecoes: [],
    },
    projecao_operacional: {
      fim_operacional_projetado: "2026-11-20",
      status_operacional: "ATRASO_PROJETADO",
      margem_operacional_dias_uteis: -5,
      tarefas_em_hold: 2,
      impedimentos_ativos: 1,
      projecao_condicional: true,
      etapas: etapas.map((stage, i) => ({
        codigo: stage.codigo,
        fim_operacional_projetado:
          i < 3
            ? realDates[i]
            : [
                "2026-10-29",
                "2026-11-12",
                "2026-11-10",
                "2026-11-10",
                "2026-11-12",
                "2026-11-20",
              ][i - 3],
        desvio_plano_vigente_dias_uteis: 3,
        status_operacional: i < 3 ? "NO_PLANO" : "ATRASO_PROJETADO",
        tarefas_em_hold: i === 3 ? [101, 102] : [],
        impedimentos_ativos:
          i === 3 ? [{ id: 1, tarefa_id: 101, status: "ABERTA" }] : [],
      })),
    },
    historico_projecoes: [
      {
        id: 1,
        data_referencia: "2026-10-08",
        fim_operacional_projetado: "2026-10-09",
        status_operacional: "MARGEM_CONSUMIDA",
        margem_operacional_dias_uteis: 4,
      },
    ],
    excecoes: [],
  };
}

let html = fs.readFileSync(
  path.join(root, "PlanejamentoProducao/index.php"),
  "utf8",
);
html = html
  .slice(html.indexOf("<!doctype"))
  .replace(/<\?php[\s\S]*?\?>|<\?=[\s\S]*?\?>/g, "")
  .replace(/<link[^>]*href="https:[^>]*>/g, "")
  .replace(/<script[^>]*src="(?!script\.js)[^"]*"[^>]*><\/script>/g, "");
html = html.replace(
  'data-obra-id="" data-entrega-id=""',
  'data-obra-id="1" data-entrega-id="1"',
);
const server = http.createServer((req, res) => {
  const url = new URL(req.url, "http://localhost");
  if (url.pathname.endsWith("preview.php")) {
    const plan = fixture(url.pathname.startsWith("/completed/"));
    if (url.pathname.startsWith("/all-done/"))
      plan.execucao.etapas.forEach((stage, i) => {
        stage.execucao = "CONCLUIDA";
        stage.percentual_concluido = 100;
        stage.conclusao_real = plan.etapas[i].limite;
        stage.desvio_real_dias_uteis = 0;
      });
    if (url.pathname.startsWith("/completed/missing/"))
      plan.execucao.etapas[0].conclusao_real = null;
    if (url.searchParams.has("replanejar")) plan.fonte = "SIMULACAO";
    const people = JSON.parse(url.searchParams.get("pessoas") || "{}");
    plan.etapas.forEach((stage) => {
      if (people[stage.codigo]) stage.pessoas_alocadas = people[stage.codigo];
    });
    res.setHeader("Content-Type", "application/json");
    return res.end(JSON.stringify(plan));
  }
  if (url.pathname.endsWith("script.js")) {
    res.setHeader("Content-Type", "text/javascript");
    return res.end(
      fs.readFileSync(path.join(root, "PlanejamentoProducao/script.js")),
    );
  }
  if (url.pathname.endsWith("style.css")) {
    res.setHeader("Content-Type", "text/css");
    return res.end(
      fs
        .readFileSync(path.join(root, "PlanejamentoProducao/style.css"), "utf8")
        .replace(/@import url\([^)]*\);/g, ""),
    );
  }
  res.setHeader("Content-Type", "text/html");
  res.end(html);
});

(async () => {
  await new Promise((resolve) => server.listen(0, "127.0.0.1", resolve));
  const browser = await chromium.launch({
    headless: true,
    ...(process.env.FLOW_QA_BROWSER
      ? { executablePath: process.env.FLOW_QA_BROWSER }
      : {}),
  });
  const errors = [];
  const page = await browser.newPage({
    viewport: { width: 1800, height: 1100 },
    reducedMotion: "reduce",
    colorScheme: "dark",
  });
  page.on("pageerror", (error) => errors.push(error.message));
  const origin = `http://127.0.0.1:${server.address().port}`;
  try {
    await page.goto(`${origin}/open/`);
    await page.waitForSelector(".planning-milestone.is-projected");
    assert.equal(await page.locator("#confirm-plan").innerText(), "Replanejar");
    assert.equal(
      await page.locator(".planning-milestone.is-actual").count(),
      3,
    );
    assert.equal(
      await page
        .locator('[data-bar-row="COMPOSICAO"] .planning-milestone.is-projected')
        .count(),
      1,
    );
    assert.equal(
      await page
        .locator(
          '[data-bar-row="MODELAGEM_INTERNA"] .planning-milestone.is-projected',
        )
        .count(),
      0,
    );
    assert.equal(await page.locator(".marker-operational").count(), 1);
    assert.match(
      await page.locator("#summary-operational-status").innerText(),
      /depende de impedimento/i,
    );
    assert.match(
      await page
        .locator('[data-stage-row="COMPOSICAO"] [data-field="deviation"]')
        .innerText(),
      /\+3d/,
    );
    for (const scale of ["month", "day", "week"]) {
      await page.locator(`[data-scale="${scale}"]`).click();
      assert.match(
        await page.locator(`[data-scale="${scale}"]`).getAttribute("class"),
        /is-active/,
      );
    }
    await page.screenshot({
      path: path.join(output, "em-andamento.png"),
      fullPage: true,
    });
    const projectedMilestone = page.locator(
      '[data-bar-row="COMPOSICAO"] .planning-milestone',
    );
    await projectedMilestone.hover();
    assert.equal(await projectedMilestone.getAttribute("title"), null);
    assert.equal(
      await projectedMilestone.getAttribute("aria-describedby"),
      "planning-tooltip",
    );
    assert.match(
      await page.locator("#planning-tooltip").innerText(),
      /Composição/,
    );
    assert.match(
      await page.locator("#planning-tooltip").innerText(),
      /Projeção operacional/,
    );
    assert.match(
      await page.locator("#planning-tooltip").innerText(),
      /29\/10\/2026/,
    );
    assert.match(
      await page.locator("#planning-tooltip").innerText(),
      /\+3 dias úteis/,
    );
    assert.match(
      await page.locator("#planning-tooltip").innerText(),
      /2 tarefas em HOLD/,
    );
    await page.screenshot({ path: path.join(output, "tooltip-marco.png") });
    await page.mouse.move(5, 5);
    assert.equal(await page.locator("#planning-tooltip").isVisible(), false);
    await projectedMilestone.focus();
    assert.equal(await page.locator("#planning-tooltip").isVisible(), true);
    await page.keyboard.press("Escape");
    assert.equal(await page.locator("#planning-tooltip").isVisible(), false);
    await page.locator(".marker-due .timeline-marker-trigger").focus();
    assert.match(
      await page.locator("#planning-tooltip").innerText(),
      /Entrega R00 \(prazo\)/,
    );
    assert.match(
      await page.locator("#planning-tooltip").innerText(),
      /13\/11\/2026/,
    );
    await page.keyboard.press("Escape");
    await page
      .locator('[data-bar-row="COMPOSICAO"] .planning-limit-tick')
      .focus();
    assert.match(
      await page.locator("#planning-tooltip").innerText(),
      /Fim planejado/,
    );
    assert.match(
      await page.locator("#planning-tooltip").innerText(),
      /26\/10\/2026/,
    );
    await page.keyboard.press("Escape");
    await page
      .locator('[data-bar-row="COMPOSICAO"] .planning-milestone')
      .click();
    assert.match(
      await page.locator("#detail-content").innerText(),
      /Projeção operacional/i,
    );
    assert.match(
      await page.locator("#detail-content").innerText(),
      /2 tarefa\(s\) em HOLD incluída\(s\)/i,
    );
    assert.match(
      await page.locator("#detail-content").innerText(),
      /impedimento\(s\) ativo\(s\) no Flow Block/i,
    );
    await page.locator("#detail-close").click();
    await page.locator("#confirm-plan").click();
    await page.waitForFunction(() =>
      [...document.querySelectorAll("[data-capacity]")].some(
        (button) => !button.disabled,
      ),
    );
    await page.locator('[data-stage="COMPOSICAO"][data-capacity="1"]').click();
    await page.waitForFunction(
      () =>
        document.querySelector('[data-stage-row="COMPOSICAO"] output')
          .textContent === "4",
    );
    await page.locator("#reset-simulation").click();
    await page.waitForFunction(
      () =>
        document.querySelector('[data-stage-row="COMPOSICAO"] output')
          .textContent === "3",
    );
    await page.goto(`${origin}/all-done/`);
    await page.waitForSelector(
      "body.is-in-progress .planning-milestone.is-actual",
    );
    assert.equal(await page.locator("#confirm-plan").isVisible(), true);
    assert.equal(
      await page.locator("#summary-observed-label").innerText(),
      "Projeção operacional",
    );
    await page.goto(`${origin}/completed/`);
    await page.waitForSelector("body.is-completed");
    assert.equal(
      await page.locator("#summary-operational").innerText(),
      "09/10/2026",
    );
    assert.equal(await page.locator("#summary-due").innerText(), "16/10/2026");
    assert.equal(await page.locator("#confirm-plan").isVisible(), false);
    assert.equal(await page.locator("#reset-simulation").isVisible(), false);
    assert.equal(await page.locator(".marker-operational").count(), 0);
    assert.equal(await page.locator(".marker-today").count(), 0);
    assert.equal(await page.locator(".marker-actual").count(), 1);
    assert.equal(
      await page.locator(".planning-milestone.is-projected").count(),
      0,
    );
    assert.equal(
      await page.locator(".planning-milestone.is-actual").count(),
      9,
    );
    assert.match(
      await page.locator("#summary-margin-planned").innerText(),
      /\+9 dias úteis vs\. plano/,
    );
    assert.match(
      await page.locator("#summary-margin-planned").getAttribute("class"),
      /is-late/,
    );
    assert.equal(
      await page
        .locator("#summary-margin-planned")
        .evaluate((el) => getComputedStyle(el).color),
      "rgb(255, 77, 96)",
    );
    assert.match(
      await page.locator("#summary-margin-operational").innerText(),
      /7 dias corridos antes/,
    );
    assert.match(
      await page.locator("#summary-margin-operational").getAttribute("class"),
      /is-early/,
    );
    await page.locator("#show-plan-history").click();
    assert.match(
      await page.locator("#detail-content").innerText(),
      /Entrega real R00/,
    );
    assert.match(
      await page.locator("#detail-content").innerText(),
      /09\/10\/2026/,
    );
    assert.match(
      await page.locator("#detail-content").innerText(),
      /Projeções registradas/,
    );
    await page.locator("#detail-close").click();
    await page.waitForFunction(
      () =>
        document.querySelector("#planning-detail").getBoundingClientRect()
          .left >= innerWidth,
    );
    await page.screenshot({
      path: path.join(output, "concluido.png"),
      fullPage: true,
    });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({
      path: path.join(output, "concluido-mobile.png"),
      fullPage: true,
    });
    assert.equal(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
      true,
    );
    const mobileMilestone = page.locator(
      '[data-bar-row="POS_PRODUCAO"] .planning-milestone',
    );
    await mobileMilestone.scrollIntoViewIfNeeded();
    await mobileMilestone.focus();
    assert.match(
      await page.locator("#planning-tooltip").innerText(),
      /Conclusão real/,
    );
    assert.equal(
      await page.locator("#planning-tooltip").evaluate((el) => {
        const r = el.getBoundingClientRect();
        return (
          r.left >= 0 &&
          r.right <= innerWidth &&
          r.top >= 0 &&
          r.bottom <= innerHeight
        );
      }),
      true,
    );
    await page.keyboard.press("Escape");
    await page.goto(`${origin}/completed/missing/`);
    await page.waitForSelector("body.is-completed");
    assert.equal(
      await page
        .locator('[data-bar-row="CADERNO_FILTRO"] .planning-milestone')
        .count(),
      0,
    );
    assert.match(
      await page
        .locator('[data-stage-row="CADERNO_FILTRO"] [data-field="observed"]')
        .innerText(),
      /Sem registro/,
    );
    await page.setViewportSize({ width: 1800, height: 1100 });
    await page.goto(`${origin}/open/?tema=light`);
    await page.waitForSelector("body.light.is-in-progress .planning-milestone.is-projected");
    assert.equal(await page.locator("#timeline").evaluate((el) => getComputedStyle(el).backgroundColor), "rgb(248, 250, 253)");
    assert.equal(await page.locator("#theme-toggle").getAttribute("aria-label"), "Ativar tema escuro");
    await page.screenshot({ path: path.join(output, "light-em-andamento.png"), fullPage: true });
    await page.locator('[data-bar-row="COMPOSICAO"] .planning-milestone').hover();
    assert.equal(await page.locator("#planning-tooltip").evaluate((el) => getComputedStyle(el).backgroundColor), "rgb(255, 255, 255)");
    await page.screenshot({ path: path.join(output, "light-tooltip.png") });
    await page.locator("#theme-toggle").click();
    assert.equal(await page.locator("body").evaluate((el) => el.classList.contains("light")), false);
    assert.equal(await page.evaluate(() => localStorage.getItem("theme")), "dark");
    await page.locator("#theme-toggle").click();
    assert.equal(await page.locator("body").evaluate((el) => el.classList.contains("light")), true);
    assert.equal(await page.evaluate(() => localStorage.getItem("theme")), "light");
    await page.goto(`${origin}/completed/?tema=light`);
    await page.waitForSelector("body.light.is-completed");
    assert.match(await page.locator("#plan-status-card").innerText(), /Concluído/i);
    await page.screenshot({ path: path.join(output, "light-concluido.png"), fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    await page.screenshot({ path: path.join(output, "light-mobile.png"), fullPage: true });
    const systemLight = await browser.newPage({ viewport: { width: 800, height: 700 }, colorScheme: "light", reducedMotion: "reduce" });
    await systemLight.goto(`${origin}/open/`);
    await systemLight.waitForSelector("body.light");
    await systemLight.close();
    assert.deepEqual(errors, []);
    console.log(
      "OK: estados, gráficos, tooltip, temas claro/escuro, preferência do sistema, alternância e viewport móvel.",
    );
  } finally {
    await browser.close();
    server.close();
  }
})().catch((error) => {
  console.error(error);
  server.close();
  process.exitCode = 1;
});
