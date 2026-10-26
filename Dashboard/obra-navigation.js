// Navegação da obra: preserva os handlers dos recursos existentes.
(() => {
  const header = document.querySelector(".container > header");
  const scroller = document.querySelector(".container");
  const more = document.getElementById("obraMore");
  const hero = document.getElementById("obra3dHero");
  if (!header || !scroller) return;
  // Apenas mudanças de tamanho atualizam o offset; o sticky não usa scroll handlers.
  const syncHeaderHeight = () =>
    scroller.style.setProperty(
      "--obra-header-height",
      `${header.offsetHeight}px`,
    );
  new ResizeObserver(syncHeaderHeight).observe(header);
  syncHeaderHeight();
  let metadata = window.__obra3dData;
  let liveEarth;
  let liveReview;
  const panel = more?.querySelector(".obra-more-panel");
  if (panel) {
    panel.id = "obraMorePanel";
    more.querySelector("summary").setAttribute("aria-controls", panel.id);
  }
  const mobilePanel = document.getElementById("quickMobileMenu");
  // O renderer flutua no body. Menus também precisam sair do stacking context
  // animado da .container para aparecer acima dele.
  if (mobilePanel) document.body.append(mobilePanel);
  function placePanel() {
    if (!panel) return;
    panel.hidden = !more.open;
    if (more.open) {
      document.body.append(panel);
      const rect = more.getBoundingClientRect();
      Object.assign(panel.style, {
        position: "fixed",
        top: `${header.getBoundingClientRect().bottom}px`,
        right: `${Math.max(12, document.documentElement.clientWidth - rect.right)}px`,
      });
      if (document.activeElement === more.querySelector("summary")) {
        panel
          .querySelector(
            'a:not([aria-hidden="true"]):not([aria-disabled="true"])',
          )
          ?.focus({ preventScroll: true });
      }
    } else {
      more.append(panel);
      panel.removeAttribute("style");
    }
  }
  more?.addEventListener("toggle", placePanel);
  panel?.addEventListener("keydown", (event) => {
    if (event.key !== "Tab") return;
    const links = [...panel.querySelectorAll("a")].filter(
      (link) =>
        link.getBoundingClientRect().width &&
        link.getAttribute("aria-disabled") !== "true",
    );
    if (
      (event.shiftKey && document.activeElement === links[0]) ||
      (!event.shiftKey && document.activeElement === links.at(-1))
    ) {
      event.preventDefault();
      more.open = false;
      const next =
        !event.shiftKey && document.getElementById("quick_flow_review");
      (next && next.getBoundingClientRect().width
        ? next
        : more.querySelector("summary")
      ).focus({ preventScroll: true });
    }
  });
  window.addEventListener("resize", () => {
    if (more?.open) placePanel();
  });
  const reduced = () => matchMedia("(prefers-reduced-motion: reduce)").matches;

  function externalUrl(value) {
    const raw = String(value || "").trim();
    if (!raw) return "";
    try {
      const url = new URL(
        /^[a-z][a-z\d+.-]*:/i.test(raw) ? raw : `https://${raw}`,
      );
      return /^https?:$/.test(url.protocol) ? url.href : "";
    } catch {
      return "";
    }
  }

  function sync() {
    const id = String(metadata?.obraId || "");
    for (const key of ["quick", "mobile"]) {
      const model = document.getElementById(`${key}_predio_3d`);
      if (model)
        model.hidden =
          !hero || hero.hidden || hero.dataset.state === "checking";
      const map = document.getElementById(`${key}_mapa_pavimento`);
      if (map) {
        map.hidden = !/^\d+$/.test(id);
        map.href = map.hidden
          ? "#"
          : `../MapaPavimento/index.php?obra_id=${encodeURIComponent(id)}`;
      }
    }
    syncOptionalLinks();
    syncEmptyLinks();
    updateActive();
  }
  function syncEmptyLinks() {
    const empty = document.querySelector(".obra-links-empty");
    if (empty)
      empty.hidden = [
        "quick_review",
        "quick_fotografico",
        "quick_google_earth",
      ].some((id) => {
        const link = document.getElementById(id);
        return (
          link && !link.hidden && link.getAttribute("aria-hidden") !== "true"
        );
      });
  }
  function syncOptionalLinks() {
    for (const [field, suffix, live] of [
      ["google_earth", "google_earth", liveEarth],
      ["link_review", "review", liveReview],
    ]) {
      const url = externalUrl(
        live ??
          metadata?.obra?.[field] ??
          document.getElementById(field)?.value,
      );
      for (const prefix of ["quick", "mobile"]) {
        const link = document.getElementById(`${prefix}_${suffix}`);
        if (!link) continue;
        link.hidden = false;
        if (link.style.display !== "inline-flex")
          link.style.display = "inline-flex";
        if (link.getAttribute("aria-hidden") !== "false")
          link.setAttribute("aria-hidden", "false");
        link.setAttribute("aria-disabled", String(!url));
        link.setAttribute("tabindex", url ? "0" : "-1");
        link.href = url || "#";
        link.title = url
          ? suffix === "review"
            ? "Review"
            : "Google Earth"
          : "Link não cadastrado nesta obra";
      }
    }
  }

  const anchors = [
    ...header.querySelectorAll('a[href^="#"]'),
    ...(mobilePanel?.querySelectorAll('a[href^="#"]') || []),
  ].filter((link) =>
    ["#obra3dHero", "#tabela-obra", "#list_acomp"].includes(
      link.getAttribute("href"),
    ),
  );
  function updateActive() {
    const threshold = header.getBoundingClientRect().bottom + 125;
    const sections = ["obra3dHero", "tabela-obra", "list_acomp"]
      .map((id) => ({ id, element: document.getElementById(id) }))
      .filter((section) => section.element && !section.element.hidden);
    let current = sections[0]?.id;
    for (const section of sections) {
      const element =
        section.id === "tabela-obra"
          ? section.element.closest(".filtro-tabela")
          : section.element;
      if (element.getBoundingClientRect().top <= threshold)
        current = section.id;
    }
    for (const link of anchors) {
      const active = link.getAttribute("href") === `#${current}`;
      if (active) link.setAttribute("aria-current", "location");
      else link.removeAttribute("aria-current");
    }
  }
  let frame;
  window.addEventListener("resize", updateActive);
  scroller.addEventListener(
    "scroll",
    () => {
      if (frame) return;
      frame = requestAnimationFrame(() => {
        frame = null;
        updateActive();
      });
    },
    { passive: true },
  );

  function navigate(event) {
    const link = event.target.closest("a");
    if (!link) return;
    if (link.getAttribute("aria-disabled") === "true") {
      event.preventDefault();
      return;
    }
    if (more) more.open = false;
    if (
      event.defaultPrevented ||
      event.button !== 0 ||
      event.ctrlKey ||
      event.metaKey ||
      event.shiftKey ||
      event.altKey
    )
      return;
    const href = link.getAttribute("href") || "";
    if (!href.startsWith("#") || href.length < 2) return;
    let target = document.getElementById(href.slice(1));
    if (!target || target.hidden) return;
    if (target.id === "tabela-obra")
      target = target.closest(".filtro-tabela") || target;
    event.preventDefault();
    const top =
      target.id === "obra3dHero"
        ? 0
        : target.getBoundingClientRect().top -
          scroller.getBoundingClientRect().top +
          scroller.scrollTop -
          header.offsetHeight -
          14;
    scroller.scrollTo({
      top: Math.max(0, top),
      behavior: reduced() ? "instant" : "smooth",
    });
    target.setAttribute("tabindex", "-1");
    target.focus({ preventScroll: true });
  }
  header.addEventListener("click", navigate);
  panel?.addEventListener("click", navigate);
  mobilePanel?.addEventListener("click", navigate);
  document.addEventListener("click", (event) => {
    if (
      more?.open &&
      !more.contains(event.target) &&
      !panel?.contains(event.target)
    )
      more.open = false;
  });
  document.addEventListener(
    "keydown",
    (event) => {
      if (event.key !== "Escape") return;
      let handled = false;
      if (more?.open) {
        more.open = false;
        more.querySelector("summary").focus();
        handled = true;
      }
      if (
        document.getElementById("quickMobileMenu")?.classList.contains("open")
      ) {
        document.getElementById("quickMobileClose")?.click();
        document.getElementById("quickHamburger")?.focus();
        handled = true;
      }
      if (handled) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    },
    true,
  );
  window.addEventListener("obra-dados-updated", (event) => {
    metadata = event.detail;
    liveEarth = undefined;
    liveReview = undefined;
    setTimeout(sync, 100); // Metadados e campos são preenchidos pelo fluxo existente.
  });
  for (const event of ["input", "change"])
    document.getElementById("google_earth")?.addEventListener(event, (e) => {
      liveEarth = e.target.value;
      sync();
    });
  for (const event of ["input", "change"])
    document.getElementById("link_review")?.addEventListener(event, (e) => {
      liveReview = e.target.value;
      sync();
    });
  const optionalObserver = new MutationObserver(syncOptionalLinks);
  for (const id of ["quick_review", "mobile_review"]) {
    const link = document.getElementById(id);
    if (link)
      optionalObserver.observe(link, {
        attributes: true,
        attributeFilter: ["style", "aria-hidden"],
      });
  }
  const linksObserver = new MutationObserver(syncEmptyLinks);
  for (const id of ["quick_review", "quick_fotografico"]) {
    const link = document.getElementById(id);
    if (link)
      linksObserver.observe(link, {
        attributes: true,
        attributeFilter: ["aria-hidden", "hidden"],
      });
  }
  if (hero)
    new MutationObserver(sync).observe(hero, {
      attributes: true,
      attributeFilter: ["hidden", "data-state"],
    });
  sync();
})();
