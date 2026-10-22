/* Local integration tests. No database changes except the normal login logs/access time.
 * Expiration/authorization fixtures edit only isolated test browser sessions via CLI.
 * Failed business requests must return 401 and are never replayed after login.
 */
const fs = require("fs");
const path = require("path");
const cp = require("child_process");
const { chromium } = require(
  process.env.FLOW_PLAYWRIGHT_MODULE || "playwright",
);
const root = path.resolve(__dirname, "..");
const base =
  process.env.FLOW_AUDIT_BASE_URL || "http://localhost:8066/ImproovWeb/";
if (
  ![
    "https://improov/ImproovWeb/",
    "http://localhost:8066/ImproovWeb/",
  ].includes(base)
)
  throw new Error("Use an official local URL");
const instructions = fs.readFileSync(path.join(root, "AGENTS.md"), "utf8");
const login =
  process.env.FLOW_AUDIT_LOGIN || instructions.match(/Usuario:\s*(\S+)/)?.[1];
const password =
  process.env.FLOW_AUDIT_PASSWORD || instructions.match(/Senha:\s*(\S+)/)?.[1];
const php = process.env.FLOW_AUDIT_PHP || "php";
const stamp = "auth-navigation-" + Date.now();
const results = [],
  failures = [];
const check = (ok, label) => {
  results.push({ test: label, ok: !!ok });
  if (!ok) failures.push(label);
};
const url = (route) => new URL(route, base).href;
const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
let lastLogin = 0;
async function submitLogin(page, expected) {
  // The existing login checks UPDATE affected_rows; NOW() must advance between logins.
  await pause(Math.max(0, 1100 - (Date.now() - lastLogin)));
  await page.locator("#login").fill(login);
  await page.locator("#senha").fill(password);
  const response = page.waitForResponse(
    (r) =>
      r.url().split("?")[0] === url("login.php") &&
      r.request().method() === "POST",
  );
  await page.getByRole("button", { name: "Entrar", exact: true }).click();
  const payload = await (await response).json();
  lastLogin = Date.now();
  check(
    payload.status === "success",
    "login success: " + expected.split("?")[0],
  );
  await page.waitForURL(url(expected), { timeout: 20000 });
  await page.waitForLoadState("networkidle", { timeout: 20000 });
  return payload;
}
async function fixture(context, operation, extra = {}) {
  const id = (await context.cookies(base)).find(
    (c) => c.name === "PHPSESSID",
  )?.value;
  const output = cp.execFileSync(
    php,
    [path.join(root, "tools/auth_navigation_session_fixture.php")],
    {
      cwd: root,
      env: { ...process.env, FLOW_AUTH_TEST_LOCAL: "1" },
      input: JSON.stringify({ id, operation, ...extra }),
      encoding: "utf8",
    },
  );
  if (!JSON.parse(output).ok) throw new Error("Fixture failed");
}
async function run() {
  const browser = await chromium.launch({ headless: true, channel: "chrome" });
  const contexts = [];
  async function fresh() {
    const context = await browser.newContext();
    contexts.push(context);
    return { context, page: await context.newPage() };
  }
  try {
    const documents = JSON.parse(
      cp.execFileSync(
        php,
        [
          "-r",
          'require "config/auth_navigation.php"; echo json_encode(flow_auth_page_files());',
        ],
        { cwd: root, encoding: "utf8" },
      ),
    );
    const documentChecks = await fresh();
    for (const file of documents) {
      const response = await documentChecks.context.request.get(url(file), {
        headers: { Accept: "text/html" },
        maxRedirects: 0,
      });
      check(
        response.status() === 302 &&
          (response.headers().location || "").includes("/login-preview.php"),
        `document anonymous guard: ${file} (HTTP ${response.status()})`,
      );
      check(
        !/\b(?:Warning|Notice|Fatal error)\b/.test(await response.text()),
        `document anonymous guard has no PHP diagnostics: ${file}`,
      );
    }
    if (process.env.FLOW_AUTH_DOCUMENTS_ONLY === "1") {
      console.log(
        JSON.stringify(
          {
            report: "output/" + stamp + ".json",
            checks: results.length,
            failures,
          },
          null,
          2,
        ),
      );
      if (failures.length) process.exitCode = 1;
      return;
    }
    // Direct link, query and fragment, failed credentials, consume-once.
    let { context, page } = await fresh();
    const target = "FlowReview/?imagem_id=583&modo=revisao#auth-qa";
    await page.goto(url(target));
    const loginUrl = new URL(page.url());
    const token = loginUrl.searchParams.get("login_context");
    check(
      loginUrl.pathname.endsWith("/login-preview.php") &&
        /^[a-f0-9]{32}$/.test(token),
      "anonymous document stores an opaque context",
    );
    check(
      !page.url().includes("583") && !page.url().includes("modo="),
      "private query absent from login URL",
    );
    check(
      (await page.title()) === "Flow Review | Improov",
      "Open Graph login title preserved",
    );
    const og = await page
      .locator('meta[property="og:url"]')
      .getAttribute("content");
    check(
      og === url("FlowReview/"),
      "Open Graph canonical excludes return context",
    );
    const badLogin = await context.request.post(url("login.php"), {
      form: { login, senha: "invalid-auth-return-test", login_context: token },
    });
    check(
      (await badLogin.json()).status === "error",
      "failed credentials do not consume context",
    );
    const payload = await submitLogin(page, target);
    check(
      payload.return_restored && page.url() === url(target),
      "direct link restores exact query and fragment",
    );
    await page.goto(url("login-preview.php?login_context=" + token));
    const reused = await submitLogin(page, "inicio.php");
    check(!reused.return_restored, "consumed context falls back to inicio.php");

    // Expired session while navigating to another protected document.
    await fixture(context, "expire");
    await page.goto(url("FlowTrack/?id=123&tab=review"));
    check(
      new URL(page.url()).pathname.endsWith("/login-preview.php"),
      "expired document session redirects to login",
    );
    await submitLogin(page, "FlowTrack/?id=123&tab=review");
    check(
      page.url() === url("FlowTrack/?id=123&tab=review"),
      "expired navigation restores the attempted document",
    );

    // AJAX POST: expires before dispatch, 401, new document GET, no replay.
    await page.goto(url("FlowReview/?imagem_id=583&modo=revisao#ajax-context"));
    await page.waitForFunction(() => !!window.FlowAuthNavigation);
    await page.waitForLoadState("networkidle", { timeout: 20000 });
    let posts = 0,
      observed401 = false;
    page.on("request", (r) => {
      if (
        r.url().split("?")[0] === url("FlowReview/revisarTarefa.php") &&
        r.method() === "POST"
      )
        posts++;
    });
    page.on("response", (r) => {
      if (
        r.url().split("?")[0] === url("FlowReview/revisarTarefa.php") &&
        r.status() === 401
      )
        observed401 = true;
    });
    await fixture(context, "expire");
    await page
      .evaluate(() =>
        fetch("revisarTarefa.php", {
          method: "POST",
          body: new URLSearchParams({ auth_return_test: "no-action" }),
        }).catch(() => {}),
      )
      .catch(() => {});
    await page
      .waitForURL((u) => u.pathname.endsWith("/login-preview.php"), {
        timeout: 15000,
      })
      .catch(async (error) => {
        console.error(
          JSON.stringify({
            stage: "ajax",
            path: new URL(page.url()).pathname,
            posts,
            observed401,
            client: await page.evaluate(() => ({
              installed: !!window.FlowAuthNavigation,
              script: document.querySelector('script[src*="authNavigation.js"]')
                ?.src,
              fetchWrapped: String(window.fetch).includes(
                "X-Flow-Auth-Required",
              ),
            })),
          }),
        );
        throw error;
      });
    check(
      observed401 && posts === 1,
      `expired AJAX business POST returns 401 once (requests=${posts}, 401=${observed401})`,
    );
    await submitLogin(
      page,
      "FlowReview/?imagem_id=583&modo=revisao#ajax-context",
    );
    check(
      posts === 1,
      `business POST is not replayed after authentication (requests=${posts})`,
    );

    // Missing cookie during XHR uses the same central detection.
    await page.waitForFunction(() => !!window.FlowAuthNavigation);
    await context.clearCookies();
    await page
      .evaluate(
        () =>
          new Promise((resolve) => {
            const xhr = new XMLHttpRequest();
            xhr.open("GET", "../renova_sessao.php");
            xhr.onloadend = resolve;
            xhr.send();
          }),
      )
      .catch(() => {});
    await page.waitForURL((u) => u.pathname.endsWith("/login-preview.php"), {
      timeout: 15000,
    });
    check(true, "XHR missing session navigates to contextual login");
    await submitLogin(
      page,
      "FlowReview/?imagem_id=583&modo=revisao#ajax-context",
    );

    // Main login endpoint must accept credentials even if its old session expired.
    await page.goto(url("index.html"));
    await fixture(context, "expire");
    await submitLogin(page, "inicio.php");
    check(
      page.url() === url("inicio.php"),
      "direct login with expired previous cookie uses the standard destination",
    );

    // Revalidate server-stored values: external/encoded/missing routes all fall back.
    for (const value of [
      "https://example.com",
      "//example.com",
      "/ImproovWeb/%252e%252e/evil.php",
      "/ImproovWeb/missing-auth-return.php",
    ]) {
      const current = await fresh();
      await current.page.goto(url("FlowReview/?tab=review"));
      const currentToken = new URL(current.page.url()).searchParams.get(
        "login_context",
      );
      await fixture(current.context, "tamper", {
        context: currentToken,
        value,
      });
      const result = await submitLogin(current.page, "inicio.php");
      check(
        result.return_restored === false,
        "unsafe or unavailable stored destination rejected: " + value,
      );
    }

    // Contexts are bound to the session and direct login ignores URL parameters.
    const owner = await fresh();
    await owner.page.goto(url("FlowReview/?tab=owner"));
    const ownerToken = new URL(owner.page.url()).searchParams.get(
      "login_context",
    );
    const stranger = await fresh();
    await stranger.page.goto(
      url(
        "login-preview.php?login_context=" +
          ownerToken +
          "&return=https://example.com&next=//example.com",
      ),
    );
    check(
      !(await submitLogin(stranger.page, "inicio.php")).return_restored,
      "context cannot be used by another session",
    );

    // Explicit logout clears even a still-unused context.
    await owner.page.goto(url("logout.php"));
    await owner.page.goto(url("login-preview.php?login_context=" + ownerToken));
    check(
      !(await submitLogin(owner.page, "inicio.php")).return_restored,
      "logout clears pending destinations",
    );

    // Two tabs share one cookie but keep independent server-side destinations.
    const tabs = await fresh();
    const second = await tabs.context.newPage();
    await tabs.page.goto(url("FlowReview/?tab=A"));
    const tokenA = new URL(tabs.page.url()).searchParams.get("login_context");
    await second.goto(url("FlowTrack/?tab=B"));
    const tokenB = new URL(second.url()).searchParams.get("login_context");
    check(tokenA !== tokenB, "two tabs have distinct contexts");
    await submitLogin(tabs.page, "FlowReview/?tab=A");
    await submitLogin(second, "FlowTrack/?tab=B");
    check(
      tabs.page.url() === url("FlowReview/?tab=A") &&
        second.url() === url("FlowTrack/?tab=B"),
      "both tabs restore their own destination across session rotation",
    );

    // Authorization still executes at destination: a restricted test session gets 403.
    const restricted = await fresh();
    await restricted.page.goto(url("Custos/?tab=restricted"));
    await submitLogin(restricted.page, "Custos/?tab=restricted");
    await fixture(restricted.context, "restrict");
    const denied = await restricted.context.request.get(
      url("Custos/?tab=restricted"),
      { maxRedirects: 0 },
    );
    check(
      denied.status() === 403,
      "restored destination still enforces its existing authorization",
    );

    // API requests are unmistakable and never create a navigation context.
    const api = await fresh();
    const apiResponse = await api.context.request.get(
      url("FlowReview/atualizar.php"),
      { headers: { "X-Flow-Auth-Required": "1" }, maxRedirects: 0 },
    );
    check(
      apiResponse.status() === 401 &&
        /application\/json/.test(apiResponse.headers()["content-type"]),
      "API returns 401 JSON without redirecting to HTML",
    );
    check(
      !apiResponse.headers().location,
      "API response has no document redirect",
    );
    const fixtureOverHttp = await api.context.request.get(
      url("tools/auth_navigation_session_fixture.php"),
    );
    check(
      fixtureOverHttp.status() === 404,
      "CLI test fixture is inaccessible through HTTP",
    );

    // Public login layout is unchanged at the required viewport sizes.
    for (const [name, width, height] of [
      ["desktop", 1920, 1080],
      ["notebook", 1366, 768],
      ["ipad-landscape", 1024, 768],
      ["ipad-portrait", 768, 1024],
      ["mobile", 390, 844],
    ]) {
      await api.page.setViewportSize({ width, height });
      await api.page.goto(url("FlowReview/?tab=responsive"));
      const fits = await api.page.locator("form").evaluate((el) => {
        const r = el.getBoundingClientRect();
        return r.left >= 0 && r.right <= innerWidth && r.height <= innerHeight;
      });
      check(fits, "login form fits viewport: " + name);
      await api.page.screenshot({
        path: path.join(root, "output", `${stamp}-${name}.png`),
      });
    }
  } catch (error) {
    failures.push(error.message.split("\n")[0]);
    throw error;
  } finally {
    await Promise.all(contexts.map((c) => c.close()));
    await browser.close();
    fs.writeFileSync(
      path.join(root, "output", `${stamp}.json`),
      JSON.stringify({ base, results, failures }, null, 2),
    );
  }
  console.log(
    JSON.stringify(
      { report: "output/" + stamp + ".json", checks: results.length, failures },
      null,
      2,
    ),
  );
  if (failures.length) process.exitCode = 1;
}
run().catch((error) => {
  console.error(error.stack);
  process.exitCode = 1;
});
