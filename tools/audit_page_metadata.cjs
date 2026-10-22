/**
 * Local browser/HTTP audit. Requires Playwright (external test tooling only).
 * FLOW_PLAYWRIGHT_MODULE may point to the bundled Playwright directory.
 * Uses only the two approved local origins. Credentials are read from AGENTS.md
 * or FLOW_AUDIT_LOGIN / FLOW_AUDIT_PASSWORD and never written to the report.
 */
const fs = require("fs");
const path = require("path");
const cp = require("child_process");
const { chromium, request } = require(
  process.env.FLOW_PLAYWRIGHT_MODULE || "playwright",
);
const root = path.resolve(__dirname, "..");
const artifactPrefix = "link-preview-" + Date.now();
const base =
  process.env.FLOW_AUDIT_BASE_URL || "http://localhost:8066/ImproovWeb/";
if (
  ![
    "https://improov/ImproovWeb/",
    "http://localhost:8066/ImproovWeb/",
  ].includes(base)
) {
  throw new Error("Use an official local URL.");
}
const php = process.env.FLOW_AUDIT_PHP || "php";
const catalog = JSON.parse(
  cp.execFileSync(
    php,
    [
      "-r",
      "require 'config/page_metadata.php'; echo json_encode(flow_metadata_catalog());",
    ],
    { cwd: root, encoding: "utf8" },
  ),
);
const instructions = fs.readFileSync(path.join(root, "AGENTS.md"), "utf8");
const login =
  process.env.FLOW_AUDIT_LOGIN || instructions.match(/Usuario:\s*(\S+)/)?.[1];
const password =
  process.env.FLOW_AUDIT_PASSWORD || instructions.match(/Senha:\s*(\S+)/)?.[1];
const failures = [];
const report = {
  base,
  anonymous: [],
  authenticated: [],
  responsive: [],
  security: [],
  failures,
};
const check = (ok, message) => {
  if (!ok) failures.push(message);
};
const values = (html) => {
  const result = {
    title: (html.match(/<title\b[^>]*>([\s\S]*?)<\/title>/i) || [])[1],
    titleCount: (html.match(/<title\b/gi) || []).length,
  };
  for (const tag of html.match(/<(?:meta|link)\b[^>]*>/gi) || []) {
    const attrs = Object.fromEntries(
      [...tag.matchAll(/([\w:-]+)\s*=\s*["']([^"']*)["']/g)].map((m) => [
        m[1],
        m[2],
      ]),
    );
    const key =
      attrs.property || attrs.name || (attrs.rel === "icon" ? "favicon" : "");
    if (key) {
      result[key] = attrs.content || attrs.href;
      result[key + ":count"] = (result[key + ":count"] || 0) + 1;
    }
  }
  return result;
};
const verify = (html, key, label) => {
  const actual = values(html),
    expected = catalog[key];
  check(
    actual.title === expected.title && actual.titleCount === 1,
    `${label}: title missing, duplicated or wrong`,
  );
  for (const tag of [
    "og:title",
    "og:description",
    "og:image",
    "og:url",
    "og:type",
    "og:site_name",
    "twitter:card",
    "twitter:title",
    "twitter:description",
    "twitter:image",
    "favicon",
  ]) {
    check(
      actual[tag] && actual[tag + ":count"] === 1,
      `${label}: ${tag} missing or duplicated`,
    );
  }
  check(
    actual["og:title"] === expected.title &&
      actual["twitter:title"] === expected.title,
    `${label}: social title mismatch`,
  );
  check(
    actual["og:description"] === expected.description &&
      actual["twitter:description"] === expected.description,
    `${label}: description mismatch`,
  );
  check(
    actual["og:url"] === new URL(expected.route, base).href,
    `${label}: canonical mismatch`,
  );
  check(
    actual["og:site_name"] === "Improov Flow" &&
      actual["og:type"] === "website" &&
      actual["twitter:card"] === "summary_large_image",
    `${label}: protocol mismatch`,
  );
  check(
    actual["og:image"] === new URL("assets/og/flow-default.png", base).href &&
      actual["twitter:image"] === actual["og:image"],
    `${label}: image mismatch`,
  );
  check(
    actual.favicon === new URL("assets/icons/flow-favicon.png", base).href,
    `${label}: favicon mismatch`,
  );
  check(
    !JSON.stringify(actual).includes("PRIVATE_SENTINEL"),
    `${label}: private value reflected`,
  );
  return actual.title;
};
async function run() {
  const anonymous = await request.newContext();
  // No cookies are reused across these requests, even Set-Cookie from a redirect.
  async function publicResponse(route, headers = {}) {
    let url = new URL(route, base).href,
      firstStatus,
      chain = [];
    for (let i = 0; i < 5; i++) {
      const response = await anonymous.get(url, {
        maxRedirects: 0,
        headers: { Cookie: "", ...headers },
      });
      const status = response.status();
      firstStatus ??= status;
      chain.push(status);
      if (status >= 300 && status < 400 && response.headers().location) {
        check(
          !/\b(?:Warning|Fatal error|Notice)\b/i.test(await response.text()),
          `anonymous ${route}: PHP diagnostic in redirect body`,
        );
        url = new URL(response.headers().location, url).href;
        if (!url.startsWith(base))
          throw new Error("Unexpected redirect outside official app origin");
        continue;
      }
      return { response, firstStatus, chain };
    }
    throw new Error("Redirect loop");
  }
  for (const [key, meta] of Object.entries(catalog)) {
    const routes = new Set([meta.route, ...meta.files]);
    for (const route of routes) {
      const { response, firstStatus, chain } = await publicResponse(route);
      const html = await response.text();
      check(
        response.status() === 200,
        `anonymous ${route}: final HTTP ${response.status()}`,
      );
      const title = verify(html, key, `anonymous ${route}`);
      if (firstStatus === 302) {
        check(
          html.includes('id="login"') &&
            html.includes('id="senha"') &&
            !html.includes("Navegação principal"),
          `anonymous ${route}: expected only login template`,
        );
      }
      report.anonymous.push({ route, firstStatus, chain, title });
    }
  }
  for (const route of [
    "",
    "index.html",
    "login-preview.php",
    "index.html?preview=unknown",
    "index.html?preview[]=flow-review",
    "index.html?preview=%3Cscript%3EPRIVATE_SENTINEL%3C/script%3E",
  ]) {
    const { response } = await publicResponse(route);
    verify(await response.text(), "flow", `fallback ${route}`);
  }
  for (const ua of [
    "Mozilla/5.0",
    "facebookexternalhit/1.1",
    "WhatsApp/2.0",
    "Slackbot-LinkExpanding 1.0",
    "Discordbot/2.0",
  ]) {
    const { response, firstStatus } = await publicResponse(
      "FlowReview/?obra_nome=PRIVATE_SENTINEL&imagem_id=987654321",
      { "User-Agent": ua },
    );
    const html = await response.text();
    verify(html, "flow-review", `User-Agent ${ua}`);
    check(
      firstStatus === 302 && html.includes('id="login"'),
      `User-Agent ${ua}: access bypass`,
    );
    report.security.push({
      test: "User-Agent public login only",
      userAgent: ua,
      status: firstStatus,
    });
  }
  for (const asset of [
    "assets/og/flow-default.png",
    "assets/icons/flow-favicon.png",
  ]) {
    const { response } = await publicResponse(asset);
    check(
      response.status() === 200 &&
        response.headers()["content-type"]?.startsWith("image/png"),
      `asset ${asset}: not a public PNG`,
    );
    report.security.push({
      test: "public asset",
      asset,
      status: response.status(),
    });
  }
  for (const route of [
    "Custos/index.php",
    "Custos/getCustosObra.php",
    "MapaPavimento/api.php",
    "notificacoes/actions/preview.php",
  ]) {
    const { response, firstStatus } = await publicResponse(route, {
      Accept: "application/json",
    });
    check(
      firstStatus === 401 &&
        response.headers()["content-type"]?.includes("application/json"),
      `API ${route}: unauthenticated contract changed`,
    );
    report.security.push({
      test: "API access denied",
      route,
      status: firstStatus,
    });
  }
  const browser = await chromium.launch({ channel: "chrome", headless: true });
  try {
    const context = await browser.newContext({
      viewport: { width: 1440, height: 900 },
    });
    let page = await context.newPage();
    await page.goto(base);
    await page.locator("#login").fill(login);
    await page.locator("#senha").fill(password);
    await Promise.all([
      page.waitForURL(/inicio\.php|PaginaPrincipal\/Home\//),
      page.getByRole("button", { name: "Entrar", exact: true }).click(),
    ]);
    await page.locator("aside.sidebar").waitFor();
    if (await page.locator("#modalIframeChangelog:visible").count())
      await page.keyboard.press("Escape");
    // Discover allowed routes from the authenticated, active sidebar itself.
    const links = await page
      .locator("aside.sidebar a[href]")
      .evaluateAll((nodes) =>
        nodes
          .map((a) => ({
            href: a.href,
            section: a
              .closest("[data-sidebar-section]")
              ?.getAttribute("data-sidebar-section"),
            target: a.getAttribute("target"),
          }))
          .filter(
            (a) =>
              a.href.startsWith(location.origin + "/ImproovWeb/") &&
              !a.href.includes("#"),
          ),
      );
    const routes = [
      ...new Map(
        links.map((link) => [
          link.href.replace(/[?#].*$/, "").replace(/\/$/, ""),
          link,
        ]),
      ).values(),
    ];
    for (const link of routes) {
      if (await page.locator("#modalIframeChangelog:visible").count())
        await page.keyboard.press("Escape");
      const url = new URL(link.href);
      url.search = "";
      url.hash = "";
      const relative = url.pathname
        .replace("/ImproovWeb/", "")
        .replace(/\/$/, "");
      const entry = Object.entries(catalog).find(
        ([, m]) => m.route.replace(/\/$/, "") === relative,
      );
      if (!entry) continue;
      // URLs come from the authenticated active sidebar, never guessed routes.
      // Direct navigation also exercises the shared-link behavior with a session.
      const response = await page.goto(url.href, {
        waitUntil: "domcontentloaded",
      });
      verify(
        await response.text(),
        entry[0],
        `initial authenticated HTML ${relative}`,
      );
      const title = verify(
        await page.content(),
        entry[0],
        `authenticated DOM ${relative}`,
      );
      report.authenticated.push({
        route: relative,
        title,
        via: "URL observed in active sidebar",
      });
    }
    check(
      report.authenticated.length === 32,
      "Not all 32 static sidebar destinations were audited",
    );
    // Exercise the real dynamic project selection without recording its private ID/name.
    const projectButton = page.locator('[data-sidebar-panel="obras"]');
    await projectButton.click();
    await page.locator('aside.sidebar [data-project-view="all"]').click();
    const projectLink = page
      .locator(
        "aside.sidebar [data-project-results] [data-sidebar-project] a.obra-item",
      )
      .first();
    const [projectResponse] = await Promise.all([
      page.waitForNavigation({ waitUntil: "domcontentloaded" }),
      projectLink.click(),
    ]);
    verify(
      await projectResponse.text(),
      "projeto",
      "authenticated dynamic project initial HTML",
    );
    verify(
      await page.content(),
      "projeto",
      "authenticated dynamic project DOM",
    );
    report.authenticated.push({
      route: "Dashboard/obra",
      title: catalog.projeto.title,
      via: "dynamic sidebar project click",
    });
    // Additional protected entry points without selecting or exposing any project.
    for (const [key, meta] of Object.entries(catalog)) {
      for (const file of meta.files) {
        if (
          ["FlowReviewExt/", "review/"].some((prefix) =>
            file.startsWith(prefix),
          ) ||
          file.endsWith("visualizador-glb-navegacao.php") ||
          ["projeto", "planejamento-producao", "alma"].includes(key)
        )
          continue;
        if (report.authenticated.some((item) => item.title === meta.title))
          continue;
        const response = await context.request.get(new URL(file, base).href);
        if (response.status() === 200) {
          verify(
            await response.text(),
            key,
            `additional authenticated ${file}`,
          );
          report.authenticated.push({
            route: file,
            title: meta.title,
            via: "authenticated HTTP",
          });
        }
      }
    }
    // Metadata changes must leave the existing login body/layout unchanged.
    const publicPage = await browser.newPage();
    for (const [name, width, height] of [
      ["desktop", 1920, 1080],
      ["notebook", 1366, 768],
      ["ipad-landscape", 1024, 768],
      ["ipad-portrait", 768, 1024],
      ["mobile", 390, 844],
    ]) {
      await publicPage.setViewportSize({ width, height });
      await publicPage.goto(
        new URL("index.html?preview=flow-review", base).href,
      );
      const geometry = await publicPage.locator("form").evaluate((form) => {
        const rect = form.getBoundingClientRect();
        return {
          left: rect.left,
          right: rect.right,
          top: rect.top,
          bottom: rect.bottom,
          viewport: innerWidth,
          scrollWidth: document.documentElement.scrollWidth,
        };
      });
      check(
        geometry.left >= 0 &&
          geometry.right <= width &&
          geometry.scrollWidth <= width,
        `${name}: login form clipped horizontally`,
      );
      await publicPage.screenshot({
        path: path.join(root, "output", `${artifactPrefix}-${name}.png`),
      });
      report.responsive.push({
        name,
        width,
        height,
        formFits:
          geometry.left >= 0 &&
          geometry.right <= width &&
          geometry.scrollWidth <= width,
      });
    }
    await publicPage.close();
    await context.close();
  } finally {
    await browser.close();
    await anonymous.dispose();
  }
}
fs.mkdirSync(path.join(root, "output"), { recursive: true });
run()
  .catch((error) => failures.push(error.message))
  .finally(() => {
    const reportPath = path.join(
      root,
      "output",
      `${artifactPrefix}-audit.json`,
    );
    console.log(
      JSON.stringify(
        {
          anonymous: report.anonymous.length,
          authenticated: report.authenticated.length,
          responsive: report.responsive,
          security: report.security.length,
          reportPath,
          failures,
        },
        null,
        2,
      ),
    );
    fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
    process.exitCode = failures.length ? 1 : 0;
  });
