/* Authenticated, read-only browser check. Never confirms or edits business data. */
const fs = require("node:fs"),
  path = require("node:path"),
  os = require("node:os");
const { chromium } = require(
  process.env.FLOW_PLAYWRIGHT_MODULE ||
    path.join(
      os.homedir(),
      ".cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright",
    ),
);
const root = path.resolve(__dirname, "..");
const credentials = fs.readFileSync(path.join(root, "AGENTS.md"), "utf8");
const login =
  process.env.FLOW_AUDIT_LOGIN || credentials.match(/Usuario:\s*(\S+)/)?.[1];
const password =
  process.env.FLOW_AUDIT_PASSWORD || credentials.match(/Senha:\s*(\S+)/)?.[1];
const report = { origins: [], checks: [], limitations: [], urls: [] };
const check = (ok, description) => {
  report.checks.push({ ok: !!ok, description });
  if (!ok) throw new Error(description);
};
(async () => {
  const browser = await chromium.launch({
    channel: process.env.FLOW_BROWSER_CHANNEL || "chrome",
    headless: true,
  });
  const output = path.join(root, "output/planejamento-alteracao-readonly");
  fs.mkdirSync(output, { recursive: true });
  try {
    const context = await browser.newContext({
      ignoreHTTPSErrors: true,
      viewport: { width: 1440, height: 1000 },
    });
    const page = await context.newPage();
    async function openModule(panel, title) {
      const button = page.locator(`button[data-sidebar-panel="${panel}"]`);
      const link = page.locator(`.sidebar a[title="${title}"]:visible`).first();
      for (let attempt = 0; attempt < 3; attempt++) {
        const modal = page.locator('#modalIframeChangelog');
        if (await modal.isVisible()) {
          await modal.click({ position: { x: 5, y: 5 } });
          await modal.waitFor({ state: 'hidden' });
        }
        if (await button.getAttribute('aria-expanded') !== 'true') await button.click();
        try { await link.click({ noWaitAfter: true, timeout: 5000 }); return; }
        catch (error) { if (!await modal.isVisible() || attempt === 2) throw error; }
      }
    }
    const browserErrors = (report.browser_errors = []);
    page.on("pageerror", (error) => browserErrors.push(error.message));
    page.on("console", (entry) => {
      if (entry.type() === "error")
        (report.console_errors ||= []).push(entry.text().slice(0, 350));
    });
    page.setDefaultTimeout(20000);
    for (const origin of [
      "https://improov/ImproovWeb/",
      "http://localhost:8066/ImproovWeb/",
    ]) {
      try {
        const response = await page.goto(origin, {
          waitUntil: "domcontentloaded",
          timeout: 10000,
        });
        report.origins.push({ url: origin, status: response.status() });
      } catch (error) {
        report.origins.push({
          url: origin,
          error: error.message.split("\n")[0],
        });
      }
    }
    for (const endpoint of [
      "PlanejamentoCapacidade/alteracao_config.php",
      "PlanejamentoCapacidade/alteracao_agenda.php",
      "PlanejamentoProducao/alteracao.php?entrega_id=1",
      "PlanejamentoProducao/thumbnail.php?imagem_id=1",
      "PlanejamentoProducao/history.php?entrega_id=1&versao_id=1",
    ]) {
      const denied = await context.request.get(
        "http://localhost:8066/ImproovWeb/" + endpoint,
      );
      check(
        denied.status() === 401,
        `Acesso anônimo recusado: ${endpoint.split("?")[0]}`,
      );
    }
    await page.locator("#login").fill(login);
    await page.locator("#senha").fill(password);
    const response = page.waitForResponse(
      (response) =>
        response.url().endsWith("/login.php") &&
        response.request().method() === "POST",
    );
    await page.getByRole("button", { name: "Entrar", exact: true }).click();
    const result = await (await response).json();
    check(result.status === "success", "Login com usuário de desenvolvimento");
    try {
      await page.waitForURL(
        (url) =>
          !/login-preview\.php|index\.html/.test(url.pathname) &&
          url.pathname !== "/ImproovWeb/",
        { waitUntil: "domcontentloaded", timeout: 5000 },
      );
    } catch (error) {
      if (!result.redirect_url) throw error;
      const destination = new URL(result.redirect_url, page.url());
      if (destination.origin !== new URL(page.url()).origin) throw error;
      report.limitations.push(
        "Redirecionamento visual do login não ocorreu; navegação GET seguiu o destino exato fornecido pelo servidor após login bem-sucedido.",
      );
      report.limitations.push(...browserErrors);
      await page.goto(destination.href, { waitUntil: "domcontentloaded" });
    }
    report.urls.push(page.url());
    // Os endpoints abaixo são os mesmos carregados pelas interfaces do módulo.
    const configUrl =
      "http://localhost:8066/ImproovWeb/PlanejamentoCapacidade/alteracao_config.php";
    const configCheck = await context.request.get(configUrl);
    const settings = await configCheck.json();
    check(
      settings.success && settings.colaboradores.length === 5,
      "Configuração real lista os cinco vínculos elegíveis de Alteração",
    );
    check(
      settings.colaboradores.filter((p) => p.tipo_atuacao === "PRINCIPAL")
        .length === 2 &&
        settings.colaboradores.filter((p) => p.tipo_atuacao === "SECUNDARIA")
          .length === 3,
      "API real distingue dois principais e três secundários",
    );
    const agendaResponse = await context.request.get(
      "http://localhost:8066/ImproovWeb/PlanejamentoCapacidade/alteracao_agenda.php",
    );
    const agenda = await agendaResponse.json();
    check(
      agendaResponse.ok() && agenda.success && agenda.dias.length === 10,
      "Agenda global R01+ agrega dez dias úteis e compromissos R00",
    );
    await page
      .waitForLoadState("load", { timeout: 20000 })
      .catch(() =>
        report.limitations.push(
          "Alguma dependência da Home não concluiu o carregamento em 20 segundos.",
        ),
      );
    const changelog = page.locator('#modalIframeChangelog');
    if (await changelog.isVisible()) {
      await changelog.click({ position: { x: 5, y: 5 } });
      await changelog.waitFor({ state: 'hidden' });
    }
    await page.keyboard.press("Escape");
    if (
      await page
        .getByRole("button", { name: "Agora não", exact: true })
        .isVisible()
    )
      await page
        .getByRole("button", { name: "Agora não", exact: true })
        .click();
    await openModule('gestao', 'Planejamento Global de Capacidade');
    await page.waitForURL(
      (url) => /\/PlanejamentoCapacidade\/?$/.test(url.pathname),
      { waitUntil: "domcontentloaded" },
    );
    report.urls.push(page.url());
    await page.locator("#alteracao-config summary").click();
    await page.locator("#alteracao-config-save").waitFor({ state: "visible" });
    await page.waitForFunction(
      () => !document.getElementById("alteracao-config-save").disabled,
    );
    check(
      (await page.locator("[data-config-person]").count()) === 5 &&
        (await page.locator("[data-config-level]").count()) === 5,
      "Configuração administrativa real carrega pesos e cinco overrides",
    );
    await page.locator("#alteracao-agenda summary").click();
    await page
      .locator("#alteracao-agenda-content table")
      .waitFor({ timeout: 45000 });
    check(
      (await page.locator("#alteracao-agenda-content tbody tr").count()) === 5,
      "Agenda real apresenta todos os colaboradores elegíveis",
    );
    for (const [name, width, height] of [
      ["desktop", 1920, 1080],
      ["notebook", 1366, 768],
      ["ipad-landscape", 1180, 820],
      ["ipad-portrait", 820, 1180],
      ["mobile", 390, 844],
    ]) {
      await page.setViewportSize({ width, height });
      await page
        .locator("#alteracao-config")
        .screenshot({ path: path.join(output, `configuracao-${name}.png`) });
      check(
        await page
          .locator("#alteracao-config")
          .evaluate((el) => el.scrollWidth <= el.clientWidth),
        `Configuração sem corte: ${name}`,
      );
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.keyboard.press("Escape");
    if (
      await page
        .getByRole("button", { name: "Agora não", exact: true })
        .isVisible()
    )
      await page
        .getByRole("button", { name: "Agora não", exact: true })
        .click();
    await openModule('producao', 'Entregas');
    await page.waitForURL((url) => /\/Entregas\/?$/.test(url.pathname), {
      waitUntil: "domcontentloaded",
    });
    report.urls.push(page.url());
    try {
      await page
        .locator(".card-entrega")
        .first()
        .waitFor({ state: "visible", timeout: 30000 });
    } catch (error) {
      await page.screenshot({
        path: path.join(output, "entregas-bloqueio.png"),
        fullPage: true,
      });
      const listing = await context.request.get(
        "http://localhost:8066/ImproovWeb/Entregas/listar_entregas.php",
      );
      const listingText = await listing.text();
      try {
        const value = JSON.parse(listingText);
        report.listing = {
          status: listing.status(),
          count: Array.isArray(value) ? value.length : null,
          keys: Array.isArray(value) ? [] : Object.keys(value),
        };
      } catch (_) {
        report.listing = {
          status: listing.status(),
          invalid_json: true,
          error: listingText.slice(0, 450),
        };
      }
      throw error;
    }
    check(
      (await page.locator(".card-entrega").count()) > 0,
      "Menu Produção → Entregas carrega cards reais",
    );
    const r00Cards = page
      .locator(".card-entrega")
      .filter({ has: page.locator("h4", { hasText: /R00/ }) });
    const card = (await r00Cards.count())
      ? r00Cards.first()
      : page.locator(".card-entrega").first();
    const detailsResponse = page.waitForResponse(
      (response) =>
        response.url().includes("/Entregas/get_entrega_item.php?") &&
        response.request().method() === "GET",
    );
    await card.click();
    const details = await (await detailsResponse).json();
    await page.locator("#entregaModal.is-open").waitFor();
    check(
      Array.isArray(details.itens) &&
        (await page.locator("#modalImagens .modal-imagem-item").count()) > 0,
      "Detalhe abre com imagens reais da entrega",
    );
    check(
      details.planejamento_modelo !== "IMAGENS_ALTERACAO" &&
        (await page.locator("#deliveryPlanLink").isHidden()),
      "Entrega legada não recebe entrada de planejamento obrigatório R01+",
    );
    const endpoint = new URL(
      "PlanejamentoProducao/alteracao.php",
      "http://localhost:8066/ImproovWeb/",
    );
    const legacyId = Number(
      details.entrega_id || details.id || (await card.getAttribute("data-id")),
    );
    if (legacyId > 0) {
      endpoint.searchParams.set("entrega_id", String(legacyId));
      const legacyPlanResponse = await context.request.get(endpoint.href);
      const legacyPlan = await legacyPlanResponse.json();
      check(
        legacyPlanResponse.status() === 422 &&
          /fluxo legado/.test(legacyPlan.message || ""),
        "API R01+ preserva entrega legada sem criar planejamento",
      );
      // Explicitly invalid CSRF is rejected before connecting or writing data.
      const denied = await context.request.post(endpoint.href, {
        data: {
          entrega_id: legacyId,
          acao: "confirmar",
          csrf_token: "invalid-audit-token",
        },
      });
      check(
        denied.status() === 403,
        "API de confirmação recusa CSRF inválido sem mutação",
      );
    }
    for (const [name, width, height] of [
      ["desktop", 1920, 1080],
      ["notebook", 1366, 768],
      ["ipad-landscape", 1180, 820],
      ["ipad-portrait", 820, 1180],
      ["mobile", 390, 844],
    ]) {
      await page.setViewportSize({ width, height });
      await page.screenshot({
        path: path.join(output, `entrega-detalhe-${name}.png`),
        fullPage: true,
      });
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.locator("#entregaModal .modal-footer .fecharModal").click();
    const r01Cards = page
      .locator(".card-entrega")
      .filter({ has: page.locator("h4", { hasText: /R0[1-5]/ }) });
    let foundImagePlan = false;
    for (let n = 0; n < Math.min(10, await r01Cards.count()); n++) {
      const response = page.waitForResponse(
        (r) =>
          r.url().includes("/Entregas/get_entrega_item.php?") &&
          r.request().method() === "GET",
      );
      await r01Cards.nth(n).click();
      const delivery = await (await response).json();
      await page.locator("#entregaModal.is-open").waitFor();
      if (delivery.planejamento_modelo === "IMAGENS_ALTERACAO") {
        await page.locator("#deliveryPlanLink").click();
        await page.waitForURL(
          (url) => /\/PlanejamentoProducao\/index.php$/.test(url.pathname),
          { waitUntil: "domcontentloaded" },
        );
        await page.locator("#ap-content:visible").waitFor({ timeout: 45000 });
        check(
          (await page.locator("#ap-grid .ap-grid-person").count()) > 0,
          "Planner R01+ real abre pelo detalhe de Entregas",
        );
        check(
          (await page.locator("#ap-bulk-person optgroup").count()) === 2,
          "Planner real diferencia principal e secundária na escolha",
        );
        report.urls.push(page.url());
        if ((await page.locator('#ap-demand').textContent()).includes('não registrados')) {
          check((await page.locator('#ap-complete').textContent()) === '8/8 completos', 'Plano anterior ao modelo de pontos mantém suas oito decisões históricas');
          check((await page.locator('#ap-grid').textContent()).includes('Responsável histórico'), 'Grade mantém responsável histórico fora da elegibilidade atual');
        }
        await page.screenshot({
          path: path.join(output, "planner-real.png"),
          fullPage: true,
        });
        if (await page.locator('#ap-replan').isEnabled()) {
          await page.locator('#ap-replan').click();
          check((await page.locator('#ap-demand').textContent()).includes('75 pontos'), 'Replanejamento real calcula 75 pontos sem alterar o histórico');
          await page.evaluate(() => {
            window.__orbSuggestionObserved = false;
            const loading = document.getElementById('ap-loading');
            new MutationObserver(() => {
              if (!loading.hidden && loading.parentElement.id === 'ap-suggestion-loading') window.__orbSuggestionObserved = true;
            }).observe(document.getElementById('alteration-plan'), {attributes:true,childList:true,subtree:true});
          });
          const generate = async (order) => {
            await page.locator('#ap-suggest-order').selectOption(order);
            const wait = page.waitForResponse((r) => r.url().includes('/PlanejamentoProducao/alteracao.php') && r.request().method() === 'POST');
            await page.locator('#ap-suggest').click();
            const response = await wait;
            const payload = response.request().postDataJSON();
            const result = await response.json();
            await page.waitForFunction(() => document.getElementById('ap-loading').hidden);
            check(payload.ordem_complexidade === order, `Sugestão real envia ordem ${order}`);
            return result;
          };
          const suggestion = await generate('MAIOR');
          if (suggestion.success) {
            check(suggestion.sugestao.itens.length === 8 && suggestion.sugestao.itens.reduce((sum, i) => sum + Number(i.pontos_snapshot), 0) === 75, 'Sugestão real distribui as oito imagens e 75 pontos');
            check(await page.locator('.ap-grid-cell.is-overloaded').count() === 0, 'Sugestão real não excede capacidade disponível');
          } else if (/Não foi encontrada|decisão fixada|fixadas já|colaboradores das imagens fixadas/.test(suggestion.message || '')) report.limitations.push('Sugestão real respeitou o contexto disponível: ' + suggestion.message);
          else throw new Error(suggestion.message || 'Falha ao gerar sugestão real.');
          const smaller = await generate('MENOR');
          check(smaller.success && smaller.sugestao.itens.length === 8, 'Sugestão real por menor complexidade retorna oito imagens');
          check(await page.evaluate(() => window.__orbSuggestionObserved), 'Orb exibido ao gerar sugestão real');
          for (const [name, width, height] of [['desktop', 1920, 1080], ['notebook', 1366, 768], ['ipad-landscape', 1180, 820], ['ipad-portrait', 820, 1180], ['mobile', 390, 844]]) {
            await page.setViewportSize({ width, height });
            check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Planner real sem overflow: ${name}`);
            check(await page.locator('#ap-suggest-order').evaluate((el) => { const r=el.getBoundingClientRect(); return r.width > 0 && r.left >= 0 && r.right <= innerWidth; }), `Seletor de ordem visível: ${name}`);
            await page.locator('#alteration-title').scrollIntoViewIfNeeded();
            await page.screenshot({ path: path.join(output, `planner-real-${name}.png`) });
            await page.locator('.ap-planner').screenshot({ path: path.join(output, `planner-grid-real-${name}.png`) });
          }
          await page.locator('#ap-theme').click();
          await page.screenshot({ path: path.join(output, 'planner-real-mobile-theme.png') });
          page.once('dialog', (dialog) => dialog.accept());
          await page.locator('#ap-cancel-replan').click();
          check((await page.locator('#ap-demand').textContent()).includes('não registrados'), 'Descartar alterações locais restaura o snapshot sem gravar no banco');
        }
        foundImagePlan = true;
        break;
      }
      await page.locator("#entregaModal .modal-footer .fecharModal").click();
    }
    if (foundImagePlan) {
      report.limitations.push(
        "R01+ real validada em leitura; nenhuma entrega foi confirmada ou alterada pela auditoria.",
      );
    } else
      report.limitations.push(
        "Os cards de R01–R05 consultados utilizam o modelo legado; nenhuma entrega foi convertida para forçar a homologação.",
      );
    if (foundImagePlan) {
      report.browser_errors = browserErrors;
      fs.writeFileSync(
        path.join(output, "report.json"),
        JSON.stringify(report, null, 2),
      );
      console.log(JSON.stringify(report, null, 2));
      return;
    }
    await page.locator('button[data-sidebar-panel="obras"]').click();
    await page.locator('button[data-project-view="all"]').click();
    const projectLink = page
      .locator(`.sidebar a[data-id="${Number(details.obra_id)}"]:visible`)
      .first();
    if (await projectLink.count()) {
      await projectLink.click({ noWaitAfter: true });
      await page.waitForURL(
        (url) => url.searchParams.get("obra_id") === String(details.obra_id),
        { waitUntil: "domcontentloaded" },
      );
      report.urls.push(page.url());
      await page
        .waitForFunction(
          () => ["READY", "ERROR"].includes(window.__obraLoadMetrics?.state),
          null,
          { timeout: 20000 },
        )
        .catch(() =>
          report.limitations.push(
            "Contexto visual do projeto não concluiu a carga em 20 segundos.",
          ),
        );
      const planLink = page.locator("#quick_production_plan");
      if (await planLink.isVisible()) {
        await planLink.click({ noWaitAfter: true });
        await page.waitForURL(
          (url) => /\/PlanejamentoProducao\/index.php$/.test(url.pathname),
          { waitUntil: "domcontentloaded" },
        );
        report.urls.push(page.url());
        await page
          .locator("#stage-list .planning-stage-row")
          .first()
          .waitFor({ state: "visible", timeout: 45000 });
        check(
          (await page.locator("#planning-title").textContent()) ===
            "Plano de Produção por Função" &&
            (await page.locator("#alteration-plan").count()) === 0,
          "Planejamento R00 mantém interface e despacho originais",
        );
        check(
          (await page.locator("#stage-list .planning-stage-row").count()) > 0,
          "Planejamento R00 calcula etapas reais sem confirmar",
        );
        await page.screenshot({
          path: path.join(output, "r00-planejamento.png"),
          fullPage: true,
        });
      } else
        report.limitations.push(
          "Projeto da entrega consultada não oferece link ativo de Planejamento R00; nenhuma URL foi inferida.",
        );
    } else
      report.limitations.push(
        "Projeto da entrega não está visível na lista autenticada de obras; nenhuma URL foi inferida.",
      );
    report.browser_errors = browserErrors;
    fs.writeFileSync(
      path.join(output, "report.json"),
      JSON.stringify(report, null, 2),
    );
    console.log(JSON.stringify(report, null, 2));
  } finally {
    await browser.close();
  }
})().catch((error) => {
  report.error = error.message;
  fs.mkdirSync(path.join(root, "output/planejamento-alteracao-readonly"), {
    recursive: true,
  });
  fs.writeFileSync(
    path.join(root, "output/planejamento-alteracao-readonly/report.json"),
    JSON.stringify(report, null, 2),
  );
  console.error(error.message);
  process.exitCode = 1;
});
