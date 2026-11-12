/* Isolated DOM regression using synthetic API responses. Does not change project data.
 * This supplements authenticated testing of the real Flow; it does not replace it.
 * FLOW_PLAYWRIGHT_MODULE may point to the installed Playwright runtime.
 */
const fs = require("node:fs");
const path = require("node:path");
const os = require("node:os");
const assert = require("node:assert/strict");
const runtime =
  process.env.FLOW_PLAYWRIGHT_MODULE ||
  path.join(
    os.homedir(),
    ".cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright",
  );
const { chromium } = require(runtime);
const root = path.resolve(__dirname, "..");
const clone = (value) => JSON.parse(JSON.stringify(value));
const results = [];
const check = (value, name) => {
  assert.ok(value, name);
  results.push(name);
};
const base = "http://localhost:8066/ImproovWeb/PlanejamentoProducao/";
function fixture() {
  return {
    modelo: "IMAGENS_ALTERACAO",
    entrega: {
      id: 900001,
      obra_id: 1,
      etapa: "R01",
      nomenclatura: "Projeto de teste",
      data_recebimento: "2026-10-28",
      data_prevista: "2026-11-06",
    },
    planejamento: {
      id: 1,
      estado: "RASCUNHO",
      lock_version: 0,
      versao_atual_id: null,
      baseline_versao_id: null,
      historico: [],
      rascunho_pendente: false,
    },
    colaboradores: [
      { id: 11, nome: "João", tipo_atuacao: "PRINCIPAL" },
      { id: 12, nome: "Maria", tipo_atuacao: "SECUNDARIA" },
    ],
    configuracao: {
      capacidade_padrao: 25,
      niveis: { 1: 5, 2: 10, 3: 15, 4: 20, 5: 25 },
      colaboradores: {},
    },
    capacidade: {
      fingerprint: "capacity-v1",
      dias: [
        "2026-10-28",
        "2026-10-29",
        "2026-10-30",
        "2026-11-03",
        "2026-11-04",
        "2026-11-05",
        "2026-11-06",
      ],
      agenda: Object.fromEntries(
        [11, 12].map((id) => [
          id,
          Object.fromEntries(
            [
              "2026-10-28",
              "2026-10-29",
              "2026-10-30",
              "2026-11-03",
              "2026-11-04",
              "2026-11-05",
              "2026-11-06",
            ].map((d) => [
              d,
              {
                nominal: 25,
                capacidade: 25,
                comprometidos: 0,
                ocupacao_r00: 0,
                avisos: [],
              },
            ]),
          ),
        ]),
      ),
    },
    itens: Array.from({ length: 10 }, (_, i) => ({
      entrega_item_id: i + 1,
      imagem_id: i + 100,
      imagem_nome: `Imagem ${String(i + 1).padStart(2, "0")}`,
      tipo_imagem: i < 5 ? "Unidade" : "Fachada",
      nivel_complexidade_snapshot: (i % 5) + 1,
      pontos_snapshot: ((i % 5) + 1) * 5,
      responsavel_planejado_id: null,
      inicio_planejado: null,
      fim_planejado: null,
    })),
    pode_editar: true,
    fingerprint: "fixture-v1",
    csrf_token: "synthetic-test-token",
    desatualizado: false,
  };
}
(async () => {
  const browser = await chromium.launch({
    channel: process.env.FLOW_BROWSER_CHANNEL || "chrome",
    headless: true,
  });
  try {
    const page = await browser.newPage({
      viewport: { width: 1440, height: 1000 },
    });
    await page.clock.setFixedTime(new Date("2026-10-28T12:00:00-03:00"));
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    let acceptDialogs = true;
    await page.exposeFunction("acceptFlowAlert", () => acceptDialogs);
    let plan = fixture(),
      posts = [],
      forceConflict = false,
      holdSuggestion = true,
      releaseSuggestion;
    await page.route("**/alteracao.php*", async (route) => {
      if (route.request().method() === "GET")
        return route.fulfill({ json: plan });
      const body = route.request().postDataJSON();
      posts.push(body);
      if (forceConflict)
        return route.fulfill({
          status: 409,
          json: {
            success: false,
            message:
              "O plano foi alterado por outro gestor. Recarregue a página.",
          },
        });
      if (body.acao === "sugerir") {
        if (holdSuggestion)
          await new Promise((resolve) => {
            releaseSuggestion = resolve;
          });
        const dates = [
          "2026-10-30",
          "2026-11-04",
          "2026-11-04",
          "2026-10-30",
          "2026-10-28",
          "2026-11-03",
          "2026-11-05",
          "2026-11-05",
          "2026-11-03",
          "2026-10-29",
        ];
        return route.fulfill({
          json: {
            success: true,
            sugestao: {
              itens: body.itens.map((i, index) =>
                i.fixado
                  ? i
                  : {
                      ...i,
                      responsavel_planejado_id: 11,
                      fim_planejado: dates[index],
                      excecao_capacidade: null,
                    },
              ),
              mensagem: "Sugestão gerada sem exceder capacidade.",
            },
          },
        });
      }
      plan.itens = plan.itens.map((item) => ({
        ...item,
        ...body.itens.find(
          (decision) => decision.entrega_item_id === item.entrega_item_id,
        ),
      }));
      plan.planejamento.lock_version++;
      plan.planejamento.rascunho_pendente = body.acao === "rascunho";
      if (body.acao === "confirmar") {
        const id = (plan.planejamento.versao_atual_id || 0) + 1;
        plan.planejamento.versao_atual_id = id;
        plan.planejamento.baseline_versao_id ||= id;
        plan.planejamento.estado = "CONFIRMADO";
        plan.planejamento.historico.push({
          id,
          numero: id,
          confirmado_em: "2026-10-28",
          confirmado_por: "Gestão",
        });
      } else
        plan.planejamento.estado = plan.planejamento.versao_atual_id
          ? "REPLANEJAMENTO"
          : "RASCUNHO";
      return route.fulfill({ json: { success: true, plano: clone(plan) } });
    });
    await page.route("**/history.php*", (route) =>
      route.fulfill({
        json: {
          success: true,
          versao: {
            snapshot: {
              itens: fixture().itens.map((item) => ({
                ...item,
                responsavel_planejado_id: 11,
                responsavel_planejado_nome: "João histórico",
                fim_planejado: "2026-11-03",
              })),
            },
          },
        },
      }),
    );
    const template = fs
      .readFileSync(
        path.join(root, "PlanejamentoProducao/alteracao-page.php"),
        "utf8",
      )
      .replace(/<\?php[\s\S]*?\?>/g, "")
      .replace(/<\?=[\s\S]*?\?>/g, "1")
      .replace(/<script[\s\S]*?<\/script>/g, "");
    const styles = [
      "PlanejamentoProducao/style.css",
      "PlanejamentoProducao/alteracao.css",
    ]
      .map((file) => fs.readFileSync(path.join(root, file), "utf8"))
      .join("\n")
      .replace(/^@import[^\n]*$/gm, "");
    await page.setContent(
      `<html data-theme="dark"><head><base href="${base}"><style>${styles}</style></head><body class="planning-page" data-entrega-id="900001">${template}</body></html>`,
    );
    await page.addScriptTag({
      content: fs.readFileSync(
        path.join(root, "assets/js/thinking-orbs.js"),
        "utf8",
      ),
    });
    await page.evaluate(() => {
      window.testMessages = [];
      const record = ({ message }) => window.testMessages.push(message);
      window.FlowAlert = {
        confirm: async () => ({ isConfirmed: await window.acceptFlowAlert() }),
        error: record,
        info: record,
      };
    });
    const openImage = async (id) => {
      await page.locator("#ap-tab-list").click();
      await page.locator(`#ap-rows [data-edit-image="${id}"]`).click();
    };
    const closeEditor = () => page.locator("#ap-editor-close").click();
    const openSuggestion = async () => {
      await page.locator("#ap-more").click();
      const suggestion = page.locator(".ap-suggestion");
      if ((await suggestion.getAttribute("open")) === null)
        await suggestion.locator("summary").first().click();
    };
    const waitMessage = (message) =>
      page.waitForFunction(
        (text) => window.testMessages.at(-1)?.includes(text),
        message,
      );
    const boot = async () => {
      await page.addScriptTag({
        content: fs.readFileSync(
          path.join(root, "PlanejamentoProducao/alteracao.js"),
          "utf8",
        ),
      });
      await page.waitForFunction(
        () => !document.getElementById("ap-content").hidden,
      );
    };
    await boot();
    check(
      (
        await page.locator('.ap-grid-day[aria-current="date"]').textContent()
      ).includes("Hoje"),
      "Agenda abre no dia atual",
    );
    await page.locator("#ap-rail-search").fill("01");
    await page.locator("#ap-rail-level").selectOption("1");
    await page.locator("#ap-rail-pending").check();
    const railCard = page.locator('#ap-rail-items [data-card="1"]');
    check(
      (await page.locator("#ap-rail-items [data-card]").count()) === 1,
      "Filtros da lista lateral",
    );
    const railTarget = page.locator(
      '[data-drop-person="11"][data-drop-day="2026-10-29"]',
    );
    await railCard.dragTo(railTarget);
    await railTarget.locator('[data-card="1"]').waitFor();
    check(
      (await railCard.count()) === 0,
      "Arraste da lista para o dia atualiza o filtro de pendências",
    );
    await page.locator("#ap-rail-pending").uncheck();
    const calendarWidth = () =>
      page.locator("#ap-grid-scroll").evaluate((grid) => grid.clientWidth);
    const widthBeforeDrawer = await calendarWidth();
    await railCard.click();
    check(
      (await page.locator("#ap-editor").isVisible()) &&
        (await calendarWidth()) === widthBeforeDrawer,
      "Drawer abre pela lista sem reduzir a grade",
    );
    await page.clock.setFixedTime(new Date("2026-10-29T12:00:00-03:00"));
    acceptDialogs = false;
    await page.locator("#ap-editor-day").fill("2026-10-28");
    await page.locator("#ap-editor-save").click();
    check(
      (await railTarget.locator('[data-card="1"]').count()) === 1,
      "Cancelar confirmação de data passada mantém o dia anterior",
    );
    acceptDialogs = true;
    await page.clock.setFixedTime(new Date("2026-10-28T12:00:00-03:00"));
    await page.locator("#ap-editor-remove").click();
    await railTarget.locator('[data-card="1"]').waitFor({ state: "detached" });
    await page.locator("#ap-rail-search").clear();
    await page.locator("#ap-rail-level").selectOption("");
    check(
      (await page.locator("#ap-complete").textContent()) === "0 / 10",
      "10 imagens iniciam pendentes",
    );
    check(
      await page.locator("#ap-confirm").isDisabled(),
      "Confirmação incompleta permanece bloqueada",
    );
    await page.locator("#ap-tab-list").click();
    await page.locator("#ap-search").fill("01");
    check(
      (await page.locator("#ap-rows .ap-list-row").count()) === 1,
      "Pesquisa por imagem",
    );
    await page.locator("#ap-search").fill("");
    await page.locator("#ap-level").selectOption("2");
    check(
      (await page.locator("#ap-rows .ap-list-row").count()) === 2,
      "Filtro por N2",
    );
    await page.locator("#ap-select-all").check();
    check(
      (await page.locator("#ap-selection").textContent()) === "2 selecionadas",
      "Selecionar todas respeita filtros",
    );
    await page.locator("#ap-bulk-person").selectOption("11");
    await page.locator("#ap-bulk-finish").fill("2026-11-03");
    await page.locator("#ap-apply").click();
    check(
      (await page.locator("#ap-complete").textContent()) === "2 / 10",
      "Atribuição e conclusão em massa",
    );
    check(
      (await page.locator("#ap-distribution").textContent()).includes("N2 × 2"),
      "Resumo preserva distribuição por nível",
    );
    await page.locator("#ap-save").click();
    await waitMessage("Rascunho salvo.");
    check(
      posts.length === 1 &&
        posts[0].itens.length === 10 &&
        posts[0].lock_version === 0 &&
        posts[0].csrf_token === plan.csrf_token,
      "Rascunho envia plano inteiro, versão e CSRF",
    );
    check(
      plan.planejamento.baseline_versao_id === null,
      "Rascunho não cria baseline",
    );
    await page.locator("#ap-level").selectOption("");
    await page.locator("#ap-pending").selectOption("responsavel");
    check(
      (await page.locator("#ap-rows .ap-list-row").count()) === 8,
      "Filtro sem responsável",
    );
    await page.locator("#ap-pending").selectOption("data");
    check(
      (await page.locator("#ap-rows .ap-list-row").count()) === 8,
      "Filtro sem conclusão planejada",
    );
    await page.locator("#ap-pending").selectOption("responsavel");
    await page.locator("#ap-person").selectOption("11");
    check(
      (await page.locator("#ap-rows .ap-list-row").count()) === 0,
      "Filtros combinados exibem estado vazio",
    );
    await page.locator("#ap-pending").selectOption("");
    check(
      (await page.locator("#ap-rows .ap-list-row").count()) === 2,
      "Filtro por colaborador",
    );
    await page.locator("#ap-person").selectOption("");
    await page.locator("#ap-select-all").check();
    await page.locator("#ap-bulk-person").selectOption("12");
    await page.locator("#ap-apply").click();
    check(
      (await page.locator("#ap-complete").textContent()) === "10 / 10",
      "Seleção e substituição em massa completa entrega",
    );
    await page.locator("#ap-clear").click();
    check(
      (await page.locator("#ap-complete").textContent()) === "0 / 10",
      "Limpar decisões de rascunho",
    );
    await page.locator("#ap-apply").click();
    check(
      await page.locator("#ap-confirm").isEnabled(),
      "Plano completo habilita confirmação",
    );
    await openImage(1);
    await page.locator("#ap-editor-day").fill("2026-11-02");
    await page.locator("#ap-editor-save").click();
    await waitMessage("dia útil");
    check(
      (await page.locator('#ap-rows [data-id="1"] time').textContent()) ===
        "03/11/2026",
      "Feriado rejeitado sem mudar decisão",
    );
    await page.locator("#ap-confirm").click();
    await page.waitForFunction(
      () => document.getElementById("ap-status").textContent === "Planejada",
    );
    check(
      plan.planejamento.baseline_versao_id === 1 &&
        (await page.locator("#ap-editor-person").isDisabled()),
      "Confirmação cria baseline e bloqueia edição direta",
    );
    await page.locator("#ap-history").click();
    check(
      await page.locator("#ap-history-dialog").isVisible(),
      "Histórico abre em diálogo acessível",
    );
    await page.locator('[data-version="1"]').click();
    await page.waitForSelector(".ap-history-table");
    check(
      (await page.locator("#ap-history-snapshot").textContent()).includes(
        "João histórico",
      ),
      "Histórico usa nome do snapshot",
    );
    await page.locator("#ap-close-history").click();
    await page.locator("#ap-replan").click();
    await page.locator("#ap-confirm").click();
    await waitMessage("motivo");
    check(
      !(await page.locator("#ap-reason").inputValue()),
      "Replanejamento exige motivo para confirmar",
    );
    await page.locator("#ap-reason").selectOption("REDISTRIBUICAO_EQUIPE");
    await page.locator("#ap-confirm").click();
    await page.waitForFunction(
      () => document.getElementById("ap-status").textContent === "Planejada",
    );
    check(
      plan.planejamento.versao_atual_id === 2 &&
        plan.planejamento.baseline_versao_id === 1 &&
        posts.at(-1).replanejar,
      "Replanejamento preserva baseline e envia intenção explícita",
    );
    await page.locator("#ap-replan").click();
    await page.locator("#ap-save").click();
    await waitMessage("Rascunho salvo.");
    check(
      await page.locator("#ap-confirm").isVisible(),
      "Rascunho de replanejamento mantém edição disponível",
    );
    forceConflict = true;
    await page.locator("#ap-save").click();
    await page.waitForFunction(
      () => !document.getElementById("ap-reload").hidden,
    );
    check(
      await page.locator("#ap-save").isDisabled(),
      "Conflito bloqueia sobrescrita até recarregar",
    );
    forceConflict = false;
    await page.locator("#ap-reload").click();
    await page.waitForFunction(
      () => document.getElementById("ap-reload").hidden,
    );
    check(
      await page.locator("#ap-save").isEnabled(),
      "Recarregar retoma rascunho persistido de replanejamento",
    );
    check(
      (await page
        .locator('#ap-bulk-person optgroup[label="Alteração principal"] option')
        .count()) === 1 &&
        (await page
          .locator(
            '#ap-bulk-person optgroup[label="Alteração secundária"] option',
          )
          .count()) === 1,
      "Escolha distingue função principal e secundária",
    );
    await closeEditor();
    await page.locator("#ap-tab-calendar").click();
    await page.locator("#ap-add-person").click();
    check(
      (await page.locator("#ap-people-list").textContent()).includes(
        "pts disponíveis",
      ) &&
        (await page.locator("#ap-people-list").textContent()).includes(
          "secundária",
        ),
      "Candidatos exibem qualificação e disponibilidade diária",
    );
    await page.locator("#ap-close-people").click();
    await openSuggestion();
    await page
      .getByText("Distribuir por tipo de imagem", { exact: true })
      .click();
    await page.locator('[data-type-rule="Unidade"]').selectOption("11");
    await page.getByText("Priorizar tipos de imagem", { exact: true }).click();
    await page.locator("#ap-type-priority-enabled").check();
    await page.getByRole("button", { name: "Subir Unidade" }).click();
    await page.locator("#ap-suggest").click();
    await page
      .locator("#ap-loading-home #ap-loading")
      .waitFor({ state: "visible" });
    check(
      (await page
        .locator(
          '#ap-loading-home canvas[data-thinking-orb][aria-label="Gerando sugestão"]',
        )
        .isVisible()) && (await page.locator("#ap-suggest-order").isDisabled()),
      "Thinking Orbs visível durante geração; ordem bloqueada",
    );
    await page.waitForFunction(() => {
      const c = document.querySelector("#ap-loading-home canvas");
      return (
        c.width > 0 &&
        c.height > 0 &&
        c
          .getContext("2d")
          .getImageData(0, 0, c.width, c.height)
          .data.some((v, i) => i % 4 === 3 && v > 0)
      );
    });
    check(true, "Thinking Orbs global desenha no canvas durante a sugestão");
    holdSuggestion = false;
    releaseSuggestion();
    await waitMessage("Sugestão gerada");
    check(
      (await page.locator(".ap-grid-cell.is-overloaded").count()) === 0 &&
        posts.at(-1).acao === "sugerir",
      "Sugestão agregada sem sobrecarga, sem confirmar",
    );
    check(
      posts.at(-1).ordem_complexidade === "MAIOR" &&
        (await page.locator("#ap-loading").isHidden()),
      "Padrão maior complexidade e loading encerra",
    );
    check(
      posts.at(-1).responsaveis_por_tipo.Unidade === 11,
      "Regra de responsável por tipo enviada à sugestão",
    );
    check(
      posts.at(-1).prioridade_tipos.join(",") === "Unidade,Fachada",
      "Prioridade alterada enviada na ordem escolhida",
    );
    await openSuggestion();
    await page.locator("#ap-suggest-order").selectOption("MENOR");
    await page.locator("#ap-suggest").click();
    await page.waitForFunction(
      () => !document.getElementById("ap-suggest").disabled,
    );
    check(
      posts.at(-1).ordem_complexidade === "MENOR",
      "Geração envia a ordem menor complexidade",
    );
    await openImage(1);
    await page.locator("#ap-editor-pin").check();
    await page.locator("#ap-editor-save").click();
    await openSuggestion();
    await page.locator("#ap-suggest").click();
    await page.waitForFunction(
      () => !document.getElementById("ap-suggest").disabled,
    );
    check(
      (await page.locator("#ap-editor-pin").isChecked()) &&
        posts.at(-1).itens[0].fixado,
      "Fixação enviada e preservada durante sugestão",
    );
    await closeEditor();
    await page.locator("#ap-tab-calendar").click();
    const target = page.locator(
      '[data-drop-person="12"][data-drop-day="2026-10-29"]',
    );
    await page.locator('#ap-grid [data-card="4"]').dragTo(target);
    await target.locator('[data-card="4"]').waitFor();
    check(true, "Drag move entre colaborador e dia");
    acceptDialogs = false;
    await page.locator('#ap-grid [data-card="5"]').dragTo(target);
    await page.waitForFunction(() =>
      document.querySelector(
        '[data-drop-person="11"][data-drop-day="2026-10-28"] [data-card="5"]',
      ),
    );
    check(
      (await target.locator('[data-card="5"]').count()) === 0,
      "Cancelar sobrecarga reverte a movimentação visual",
    );
    acceptDialogs = true;
    await page.locator('#ap-grid [data-card="5"]').dragTo(target);
    await target.locator('[data-card="5"]').waitFor();
    check(
      (await target.getAttribute("class")).includes("is-overloaded"),
      "Sobrecarga explícita visível na célula",
    );
    await target.locator('[data-card="5"]').click();
    await page.locator("#ap-editor-remove").click();
    await target.locator('[data-card="5"]').waitFor({ state: "detached" });
    check(
      (await page.locator('#ap-rows [data-id="5"] time').textContent()) === "—",
      "Retirar imagem devolve à lista sem data",
    );
    const newTarget = page.locator(
      '[data-drop-person="12"][data-drop-day="2026-10-30"]',
    );
    await newTarget.locator("[data-place-person]").click();
    await page.locator('#ap-picker-items input[value="5"]').check();
    await page.locator("#ap-picker-apply").click();
    await newTarget.locator('[data-card="5"]').waitFor();
    check(true, "Alternativa acessível ao drag permite adicionar seleção");
    await page.locator("#ap-more").click();
    await page.locator("#ap-realized").click();
    check(
      (await page.locator("#ap-history-title").textContent()) ===
        "Planejado × executado",
      "Leitura de execução separa fatos das decisões",
    );
    await page.locator("#ap-close-history").click();
    await newTarget.locator('[data-card="5"]').click();
    for (const [name, width, height] of [
      ["desktop", 1920, 1080],
      ["notebook", 1366, 768],
      ["ipad-landscape", 1180, 820],
      ["ipad-portrait", 820, 1180],
      ["mobile", 390, 844],
    ]) {
      await page.setViewportSize({ width, height });
      await page.waitForFunction(
        (expected) =>
          document.querySelectorAll(".ap-grid-day").length === expected,
        5,
      );
      check(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
        `Sem overflow na página: ${name}`,
      );
      check(
        (await page.locator(".ap-grid-day").count()) === 5 &&
          (await page.locator("#ap-editor").isVisible()) &&
          (await page
            .locator("#ap-grid-scroll")
            .evaluate((grid) => grid.scrollWidth <= grid.clientWidth)),
        `Cinco dias visíveis com painel aberto: ${name}`,
      );
      const output = path.join(root, "output", "planejamento-alteracao-ui");
      fs.mkdirSync(output, { recursive: true });
      await page.screenshot({
        path: path.join(output, `${name}.png`),
        fullPage: true,
      });
      if (name === "mobile")
        await page.screenshot({
          path: path.join(output, "mobile-viewport.png"),
        });
    }
    await closeEditor();
    await page.locator("#ap-theme").click();
    check(
      await page
        .locator("body")
        .evaluate((body) => body.classList.contains("light")),
      "Alternância de tema light",
    );
    await page.screenshot({
      path: path.join(
        root,
        "output/planejamento-alteracao-ui/mobile-light.png",
      ),
      fullPage: true,
    });
    await page.setViewportSize({ width: 1440, height: 1000 });
    plan.planejamento.estado = "CONFIRMADO";
    plan.planejamento.rascunho_pendente = false;
    plan.itens[0].responsavel_planejado_id = 99;
    plan.itens[0].responsavel_planejado_nome = "Colaborador histórico";
    plan.itens[0].fixado = true;
    await page.locator("#ap-reload").evaluate((element) => {
      element.hidden = false;
    });
    await page.locator("#ap-reload").click();
    await page.waitForFunction(
      () => document.getElementById("ap-reload").hidden,
    );
    check(
      (await page.locator("#ap-complete").textContent()) === "10 / 10",
      "Versão confirmada preserva compromisso de colaborador inativo",
    );
    await openImage(1);
    check(
      (await page.locator("#ap-editor-person").inputValue()) === "99" &&
        (await page.locator("#ap-distribution").textContent()).includes(
          "Colaborador histórico",
        ),
      "Responsável inativo visível com nome histórico",
    );
    await page.locator("#ap-replan").click();
    check(
      (await page.locator("#ap-complete").textContent()) === "9 / 10" &&
        (await page.locator("#ap-confirm").isDisabled()),
      "Novo replanejamento exige responsável ativo",
    );
    await page.locator("#ap-editor-pin").uncheck();
    await page.locator("#ap-editor-save").click();
    check(
      !(await page.locator("#ap-editor-pin").isChecked()),
      "Decisão fixada de responsável inelegível pode ser liberada para redistribuição",
    );
    plan.itens.forEach((i) => {
      i.pontos_replanejamento =
        plan.configuracao.niveis[i.nivel_complexidade_snapshot];
      i.pontos_snapshot = null;
    });
    await page.locator("#ap-reload").evaluate((el) => {
      el.hidden = false;
    });
    await page.locator("#ap-reload").click();
    await page.waitForFunction(
      () => document.getElementById("ap-reload").hidden,
    );
    check(
      (await page.locator("#ap-complete").textContent()) === "10 / 10" &&
        (await page.locator("#ap-demand").textContent()).includes("Sem pontos"),
      "Plano anterior aos pontos não vira demanda zero nem perde decisões",
    );
    check(
      (await page.locator("#ap-grid").textContent()).includes(
        "Responsável histórico",
      ),
      "Grade exibe responsável histórico fora da lista de candidatos",
    );
    await page.locator("#ap-replan").click();
    check(
      (await page.locator("#ap-demand").textContent()).includes("150 pts") &&
        (await page.locator("#ap-editor-person").inputValue()) === "99",
      "Replanejar calcula pesos atuais sem substituir responsável automaticamente",
    );
    await page.locator("#ap-cancel-replan").click();
    check(
      (await page.locator("#ap-demand").textContent()).includes("Sem pontos"),
      "Cancelar replanejamento restaura ausência de snapshot de pontos",
    );
    plan.capacidade.agenda[11]["2026-10-28"].avisos = [
      "Compromisso R00 sem ocupação diária confiável",
    ];
    await page.locator("#ap-reload").evaluate((el) => {
      el.hidden = false;
    });
    await page.locator("#ap-reload").click();
    await page.waitForFunction(
      () => document.getElementById("ap-reload").hidden,
    );
    await page.locator("#ap-tab-calendar").click();
    const unknownCell = await page
      .locator('[data-drop-person="11"][data-drop-day="2026-10-28"]')
      .textContent();
    check(
      unknownCell.includes("disponibilidade não estimada") &&
        !unknownCell.includes("pts disponíveis"),
      "Compromisso desconhecido não é apresentado como capacidade livre",
    );
    plan.pode_editar = false;
    await page.locator("#ap-reload").evaluate((element) => {
      element.hidden = false;
    });
    await page.locator("#ap-reload").click();
    await page.waitForFunction(
      () => document.getElementById("ap-reload").hidden,
    );
    check(
      (await page.locator("#ap-save").isDisabled()) &&
        (await page.locator("#ap-editor-person").isDisabled()),
      "Leitura sem permissão de gestão não permite editar",
    );
    plan.pode_editar = true;
    plan.planejamento.estado = "CONFIRMADO";
    plan.planejamento.rascunho_pendente = false;
    Object.assign(plan.itens[0], {
      data_entregue: "2026-10-29 12:00:00",
      pode_editar_datas: false,
      pode_editar_responsavel: false,
      motivo_bloqueio: "Entregue ao cliente",
    });
    Object.assign(plan.itens[1], {
      pode_editar_datas: true,
      pode_editar_responsavel: false,
      motivo_bloqueio_responsavel: "Responsável protegido durante a execução",
    });
    await page.locator("#ap-reload").evaluate((el) => {
      el.hidden = false;
    });
    await page.locator("#ap-reload").click();
    await page.waitForFunction(
      () => document.getElementById("ap-reload").hidden,
    );
    check(
      (await page.locator("#ap-partial").textContent()).includes(
        "1 entregue · 9 elegíveis",
      ),
      "Entrega mista mostra quantidades e permite iniciar replanejamento",
    );
    await page.locator("#ap-replan").click();
    await openImage(1);
    const deliveredRow = page.locator('#ap-rows [data-id="1"]');
    check(
      (await deliveredRow.textContent()).includes("Entregue ao cliente") &&
        (await page.locator("#ap-editor-day").isDisabled()) &&
        (await page.locator("#ap-editor-person").isDisabled()) &&
        (await deliveredRow.locator("[data-select]").isDisabled()),
      "Imagem entregue permanece visível e somente leitura",
    );
    await openImage(2);
    check(
      (await page.locator("#ap-editor-day").isEnabled()) &&
        (await page.locator("#ap-editor-person").isDisabled()),
      "Imagem em execução permite data e protege atribuição",
    );
    await page.locator("#ap-select-all").check();
    check(
      (await page.locator("#ap-selection").textContent()) === "9 selecionadas",
      "Seleção em massa exclui itens bloqueados",
    );
    await page.locator("#ap-save").click();
    await page.waitForFunction(
      () => !document.getElementById("ap-save").disabled,
    );
    check(
      posts.at(-1).itens.length === 9 &&
        !posts.at(-1).itens.some((i) => i.entrega_item_id === 1),
      "Rascunho envia apenas decisões elegíveis",
    );
    check(errors.length === 0, `Sem erros JavaScript: ${errors.join("; ")}`);
    console.log(
      JSON.stringify(
        {
          checks: results.length,
          failures: [],
          scope: "isolated-dom-synthetic-api",
          results,
        },
        null,
        2,
      ),
    );
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
