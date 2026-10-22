/* Local regression of project restoration. Test sessions only; no business actions. */
const fs = require("fs"),
  path = require("path"),
  cp = require("child_process");
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
const stamp = "project-return-" + Date.now();
const results = [],
  failures = [];
const check = (ok, test) => {
  results.push({ test, ok: !!ok });
  if (!ok) failures.push(test);
};
let lastLogin = 0;
async function signIn(page) {
  await new Promise((resolve) =>
    setTimeout(resolve, Math.max(0, 1100 - (Date.now() - lastLogin))),
  );
  await page.locator("#login").fill(login);
  await page.locator("#senha").fill(password);
  const response = page.waitForResponse(
    (r) =>
      r.url().split("?")[0] === new URL("login.php", base).href &&
      r.request().method() === "POST",
  );
  await page.getByRole("button", { name: "Entrar", exact: true }).click();
  const payload = await (await response).json();
  lastLogin = Date.now();
  if (payload.status !== "success") throw new Error("Test login failed");
  await page.waitForURL(
    (u) =>
      !["index.html", "login-preview.php", ""].includes(
        u.pathname.slice(base.length - new URL(base).origin.length),
      ),
    { timeout: 20000 },
  );
  return payload;
}
async function projectReady(page, id) {
  await page.waitForFunction(
    (expected) =>
      window.FlowProjectContext?.obraId === expected &&
      window.__obraLoadMetrics?.state === "READY" &&
      String(window.__obra3dData?.obra?.idobra) === expected &&
      !!document.getElementById("nomenclatura")?.textContent.trim(),
    String(id),
    { timeout: 45000 },
  );
}
async function run() {
  const browser = await chromium.launch({ channel: "chrome", headless: true });
  const contexts = [];
  const fresh = async () => {
    const c = await browser.newContext();
    contexts.push(c);
    return { context: c, page: await c.newPage() };
  };
  try {
    const { context, page } = await fresh();
    await page.goto(base);
    await signIn(page);
    await page.waitForSelector(".sidebar");
    await page
      .locator("#modalIframeChangelog")
      .waitFor({ state: "visible", timeout: 2000 })
      .catch(() => {});
    await page.keyboard.press("Escape");
    if (
      await page
        .getByRole("button", { name: "Agora não", exact: true })
        .isVisible()
    )
      await page
        .getByRole("button", { name: "Agora não", exact: true })
        .click();
    // Discover real projects from the authenticated sidebar; don't invent IDs.
    const projectIds = await page
      .locator(".sidebar a[data-id]")
      .evaluateAll((elements) =>
        [...new Set(elements.map((a) => a.dataset.id))]
          .filter((id) => /^\d+$/.test(id))
          .slice(0, 2),
      );
    if (projectIds.length < 2)
      throw new Error(
        "Two accessible projects are required for the tab regression",
      );
    const [idA, idB] = projectIds;
    await page.locator('button[data-sidebar-panel="obras"]').click();
    await page.locator('button[data-project-view="all"]').click();
    await page.locator(`.sidebar a[data-id="${idA}"]:visible`).first().click();
    await projectReady(page, idA);
    check(
      new URL(page.url()).searchParams.get("obra_id") === idA,
      "Opening a legacy sidebar link persists the project ID in the URL",
    );
    const projectAUrl = new URL(page.url());
    projectAUrl.searchParams.set("tab", "review");
    projectAUrl.hash = "secao-infos-obra";
    await page.goto(projectAUrl.href);
    await projectReady(page, idA);
    await page.waitForLoadState("networkidle", { timeout: 20000 });
    const sessionId = (await context.cookies(base)).find(
      (c) => c.name === "PHPSESSID",
    )?.value;
    cp.execFileSync(
      php,
      [path.join(root, "tools/auth_navigation_session_fixture.php")],
      {
        cwd: root,
        env: { ...process.env, FLOW_AUTH_TEST_LOCAL: "1" },
        input: JSON.stringify({ id: sessionId, operation: "expire" }),
        encoding: "utf8",
      },
    );
    await page
      .evaluate(
        (id) =>
          fetch("infosObra.php?obraId=" + encodeURIComponent(id)).catch(
            () => {},
          ),
        idA,
      )
      .catch(() => {});
    await page.waitForURL((u) => u.pathname.endsWith("/login-preview.php"), {
      timeout: 20000,
    });
    check(
      !page.url().includes("obra_id="),
      "Project ID is stored in the session, absent from the login URL",
    );
    await page.evaluate(() => localStorage.clear());
    const payload = await signIn(page);
    await projectReady(page, idA);
    check(
      page.url() === projectAUrl.href && payload.return_restored,
      "Expired AJAX session restores exact project URL, query and fragment",
    );
    check(
      await page.evaluate(
        (id) =>
          window.FlowProjectContext.obraId === id &&
          localStorage.getItem("obraId") === id,
        idA,
      ),
      "Project loads and rebuilds its compatibility cache after localStorage was cleared",
    );
    check(
      await page.evaluate(
        (uid) =>
          Number(window.FlowProjectContext.usuarioId) === Number(uid) &&
          Number(localStorage.getItem("idusuario")) === Number(uid),
        payload.user.id,
      ),
      "User context comes from the authenticated session without passing through Home",
    );
    const canonical = await page
      .locator('meta[property="og:url"]')
      .getAttribute("content");
    check(
      !canonical.includes("?") && canonical.endsWith("/Dashboard/obra"),
      "Public Open Graph canonical remains institutional",
    );

    const second = await context.newPage();
    const projectBUrl = new URL(projectAUrl.href);
    projectBUrl.searchParams.set("obra_id", idB);
    projectBUrl.hash = "";
    await second.goto(projectBUrl.href);
    await projectReady(second, idB);
    check(
      await page.evaluate(
        (id) =>
          window.FlowProjectContext.obraId === id && String(obraId) === id,
        idA,
      ),
      "Opening project B does not change project A in the first tab",
    );
    await page.evaluate(() => localStorage.removeItem("obraId"));
    const refreshed = page.waitForResponse(
      (r) =>
        new URL(r.url()).pathname.endsWith("/Dashboard/infosObra.php") &&
        new URL(r.url()).searchParams.get("obraId") === idA,
    );
    await page.evaluate(() => infosObra(window.FlowProjectContext.obraId));
    await refreshed;
    await projectReady(page, idA);
    check(
      true,
      "Refreshing project information works even after the shared cache is removed",
    );
    check(
      await second.evaluate(
        (id) => window.FlowProjectContext.obraId === id,
        idB,
      ),
      "The second tab retains project B after the first tab refreshes",
    );

    const direct = await fresh();
    await direct.page.goto(projectAUrl.href);
    check(
      !(await direct.page.locator("#flow-obra-context").count()),
      "Anonymous response contains no private project bootstrap",
    );
    await signIn(direct.page);
    await projectReady(direct.page, idA);
    check(
      true,
      "Direct project link in a fresh browser loads project data after login without prior localStorage",
    );
    const alias = new URL(projectAUrl.href);
    alias.searchParams.delete("obra_id");
    alias.searchParams.set("idobra", idB);
    await direct.page.goto(alias.href);
    await projectReady(direct.page, idB);
    check(true, "Existing idobra alias initializes the correct project");
    const invalid = new URL(projectAUrl.href);
    invalid.searchParams.set("obra_id", "invalid");
    await direct.page.goto(invalid.href);
    await direct.page.waitForURL((u) => u.pathname.endsWith("/Projetos/"));
    check(
      true,
      "Invalid explicit project ID never falls back to a cached unrelated project",
    );
    invalid.searchParams.set("obra_id", "2147483647");
    await direct.page.goto(invalid.href);
    await direct.page.waitForURL((u) => u.pathname.endsWith("/Projetos/"));
    check(
      true,
      "Nonexistent project returns to the project list instead of an empty document",
    );
    await direct.page.evaluate(() => localStorage.removeItem("obraId"));
    await direct.page.goto(new URL("Dashboard/obra", base).href);
    await direct.page.waitForURL((u) => u.pathname.endsWith("/Projetos/"));
    check(true, "Legacy link without ID or cache opens the project list");
  } catch (error) {
    failures.push(error.message.split("\n")[0]);
    throw error;
  } finally {
    await Promise.all(contexts.map((c) => c.close()));
    await browser.close();
    fs.writeFileSync(
      path.join(root, "output", stamp + ".json"),
      JSON.stringify({ base, results, failures }, null, 2),
    );
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
  }
  if (failures.length) process.exitCode = 1;
}
run().catch((error) => {
  console.error(error.stack);
  process.exitCode = 1;
});
