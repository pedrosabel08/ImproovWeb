(() => {
  const root = document.documentElement;
  const $ = (selector) => document.querySelector(selector);
  const escape = (value) =>
    String(value ?? "").replace(
      /[&<>'"]/g,
      (char) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          "'": "&#039;",
          '"': "&quot;",
        })[char],
    );
  const target = (cta = {}) => {
    const raw = String(cta.target || "").trim();
    if (!raw) return "../../inicio.php";
    return /^(https?:)?\/\//.test(raw) || raw.startsWith("/")
      ? raw
      : "../../" + raw.replace(/^\.\//, "");
  };
  const empty = (message) => `<div class="home-empty">${escape(message)}</div>`;
  const truncate = (value) =>
    `class="home-truncate" data-home-tooltip="${escape(value)}"`;
  let backgrounds = {};
  let initialAnimationPlayed = false;
  try {
    backgrounds = JSON.parse(document.body.dataset.backgrounds || "{}");
  } catch (_) {
    backgrounds = {};
  }

  const reducedMotion = () =>
    window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
  const canAnimate = () => Boolean(window.gsap && !reducedMotion());

  function updateHeader() {
    const now = new Date();
    const hour = Number(
      new Intl.DateTimeFormat("en-US", {
        hour: "numeric",
        hourCycle: "h23",
        timeZone: "America/Sao_Paulo",
      }).format(now),
    );
    const greeting =
      hour < 12 ? "Bom dia" : hour < 18 ? "Boa tarde" : "Boa noite";
    $("#homeSalutation").textContent =
      `${greeting}, ${document.body.dataset.userName || "você"}`;
    $("#homeDate").textContent = new Intl.DateTimeFormat("pt-BR", {
      weekday: "long",
      day: "numeric",
      month: "long",
      timeZone: "America/Sao_Paulo",
    })
      .format(now)
      .replace(/^./, (letter) => letter.toUpperCase());
  }

  function setBackground(theme) {
    const list = Array.isArray(backgrounds[theme]) ? backgrounds[theme] : [];
    const [year, month, day] = new Intl.DateTimeFormat("en-CA", {
      timeZone: "America/Sao_Paulo",
    })
      .format(new Date())
      .split("-")
      .map(Number);
    const index = Math.floor(Date.UTC(year, month - 1, day) / 86400000);
    $(".home-background").style.backgroundImage = list.length
      ? `url("${list[index % list.length]}")`
      : "";
  }

  function initTheme() {
    const theme =
      localStorage.getItem("flow-home-theme") ||
      (matchMedia("(prefers-color-scheme: light)").matches ? "light" : "dark");
    root.dataset.theme = theme;
    setBackground(theme);
    $("#themeToggle").innerHTML =
      `<i class="${theme === "dark" ? "ri-sun-line" : "ri-moon-line"}"></i>`;
    $("#themeToggle").addEventListener("click", () => {
      const next = root.dataset.theme === "dark" ? "light" : "dark";
      root.dataset.theme = next;
      localStorage.setItem("flow-home-theme", next);
      setBackground(next);
      $("#themeToggle").innerHTML =
        `<i class="${next === "dark" ? "ri-sun-line" : "ri-moon-line"}"></i>`;
    });
  }

  function renderAttention(items) {
    $("#attentionCount").textContent = items.length;
    const list = $("#attentionList");
    list.classList.remove("home-skeleton-list");
    list.innerHTML = items.length
      ? items
          .slice(0, 3)
          .map(
            (item) =>
              `<a class="attention-card severity-${escape(item.severity || "info")}" data-home-attention-item href="${escape(target(item.cta))}"><span class="attention-icon" data-home-attention-dot></span><span class="attention-card__copy"><h3 ${truncate(item.title || item.imagem?.nome || "Ação necessária")}>${escape(item.title || item.imagem?.nome || "Ação necessária")}</h3><p>${escape(item.description || "Revise esta situação.")}</p></span></a>`,
          )
          .join("")
      : empty("Tudo em ordem. Nenhuma pendência agora.");
  }

  function renderCurrent(task) {
    const node = $("#currentWork");
    const card = $("[data-home-card='resume']");
    const grid = $("#homeContent");
    node.classList.remove("home-skeleton-current");
    if (!task) {
      card.hidden = true;
      grid.dataset.hasCurrent = "false";
      return;
    }
    card.hidden = false;
    grid.dataset.hasCurrent = "true";
    const title = task.imagem?.nome || task.funcao?.nome || "Próxima ação";
    const context = [task.funcao?.nome, task.status]
      .filter(Boolean)
      .join(" · ");
    const preview = task.preview_url
      ? `<div class="current-work__preview" data-home-current-preview><img src="${escape(task.preview_url)}" alt=""><span class="current-work__project">${escape(task.obra?.nome || "Projeto")}</span></div>`
      : `<div class="current-work__preview" data-home-current-preview><span class="current-work__project">${escape(task.obra?.nome || "Projeto")}</span></div>`;
    const stateLabel =
      String(task.status || "").toLowerCase() === "ajuste"
        ? "Tarefa em ajuste"
        : "Tarefa em andamento";
    node.innerHTML = `${preview}<div class="current-work__body"><div><h3 ${truncate(title)} data-home-current-detail>${escape(title)}</h3><p class="task-meta" data-home-current-detail>${escape(context || task.obra?.nome || "")}</p><p class="task-status" data-home-current-detail><i class="ri-time-line"></i>${stateLabel}</p></div><a class="primary-cta" data-home-current-cta href="${escape(target(task.cta))}">${escape(task.cta?.label || "Continuar tarefa")} <i class="ri-arrow-right-line"></i></a></div>`;
  }

  function renderNext(tasks) {
    const node = $("#nextList");
    node.innerHTML = tasks.length
      ? `<span class="next-timeline-line" data-home-timeline-line aria-hidden="true"></span>${tasks
          .slice(0, 3)
          .map((task, index) => {
            const title = [
              task.obra?.nome,
              task.imagem?.nome || task.funcao?.nome,
            ]
              .filter(Boolean)
              .join(" · ");
            const released = Boolean(task.is_released);
            const detail = released
              ? [
                  task.funcao?.nome,
                  task.deadline
                    ? `prazo ${task.deadline.split("-").reverse().slice(0, 2).join("/")}`
                    : "sem prazo",
                ]
                  .filter(Boolean)
                  .join(" · ")
              : [
                  task.funcao?.nome,
                  task.block_reason || "aguardando etapa anterior",
                ]
                  .filter(Boolean)
                  .join(" · ");
            const state = released
              ? index === 0
                ? "A seguir"
                : "Depois"
              : "Depois";
            const ctaLabel = released
              ? "Abrir"
              : task.is_blocked
                ? "Bloqueada"
                : "Aguardando";
            return `<a class="next-item ${released ? "is-released" : "is-blocked"}" href="${escape(target(task.cta))}"><span class="next-time" data-home-timeline-content>${state}</span><span class="task-glyph" data-home-timeline-dot></span><span class="next-item__copy" data-home-timeline-content><h3 ${truncate(title || "Tarefa")}>${escape(title || "Tarefa")}</h3><p class="task-meta">${escape(detail)}</p></span><span class="mini-cta" data-home-timeline-badge>${ctaLabel}</span></a>`;
          })
          .join("")}`
      : empty("Nenhuma próxima tarefa na fila.");
  }

  function renderQuickAccess(items) {
    const node = $("#shortcutGrid");
    node.classList.remove("home-skeleton-list");
    node.innerHTML = items.length
      ? items
          .slice(0, 6)
          .map(
            (item) =>
              `<a data-home-shortcut href="${escape(target(item))}"><i class="${escape(item.icon || "ri-window-line")}"></i><strong ${truncate(item.label || "Tela")}>${escape(item.label || "Tela")}</strong><span>${item.fixed ? "Acesso fixo" : "Visitado recentemente"}</span><b><i class="ri-arrow-right-s-line"></i></b></a>`,
          )
          .join("")
      : empty("Nenhuma tela recente disponível.");
  }

  function initTruncationTooltips() {
    let active = null;
    let tooltip = null;
    const remove = () => {
      if (tooltip) tooltip.remove();
      tooltip = null;
      active = null;
    };
    const place = (event) => {
      if (!tooltip) return;
      const gap = 10;
      const rect = tooltip.getBoundingClientRect();
      const x = Math.max(
        8,
        Math.min(
          event.clientX - rect.width / 2,
          window.innerWidth - rect.width - 8,
        ),
      );
      const above = event.clientY - rect.height - gap;
      tooltip.style.left = `${x}px`;
      tooltip.style.top = `${above >= 8 ? above : event.clientY + gap}px`;
      tooltip.dataset.position = above >= 8 ? "above" : "below";
    };
    const show = (element, event) => {
      if (active === element || element.scrollWidth <= element.clientWidth)
        return;
      remove();
      active = element;
      tooltip = document.createElement("div");
      tooltip.className = "home-name-tooltip";
      tooltip.setAttribute("role", "tooltip");
      tooltip.textContent =
        element.dataset.homeTooltip || element.textContent || "";
      document.body.append(tooltip);
      place(event);
    };
    document.addEventListener("pointerover", (event) => {
      const element = event.target.closest("[data-home-tooltip]");
      if (element) show(element, event);
    });
    document.addEventListener("pointermove", place);
    document.addEventListener("pointerout", (event) => {
      if (active && event.target.closest("[data-home-tooltip]") === active)
        remove();
    });
    window.addEventListener("scroll", remove, { passive: true });
    window.addEventListener("resize", remove, { passive: true });
  }

  function renderPerformance(performance) {
    const node = $("#performancePanel");
    node.classList.remove("home-skeleton-current");
    if (!performance?.available) {
      node.innerHTML = `<div class="performance-note">O resumo de conclusão ainda não está disponível para este período.</div>`;
      return;
    }
    const count = Number(performance.count || 0);
    const punctuality =
      performance.punctuality_percent == null
        ? "—"
        : `${Math.round(performance.punctuality_percent)}%`;
    const trend =
      performance.trend_percent == null
        ? "Seu ritmo está sendo consolidado neste mês."
        : `${performance.trend_percent >= 0 ? "↑" : "↓"} ${Math.abs(performance.trend_percent)}% em relação ao mês anterior`;
    const month = new Intl.DateTimeFormat("pt-BR", {
      month: "long",
      timeZone: "America/Sao_Paulo",
    }).format(new Date());
    const scope =
      performance.scope === "principal_functions"
        ? "Funções principais"
        : "Todas as funções atribuídas";
    node.innerHTML = `<div class="performance-metric"><strong data-home-count data-count-value="${count}">${count}</strong><span>tarefas concluídas</span></div><div class="performance-metric"><strong data-home-count data-count-value="${performance.punctuality_percent == null ? "" : Math.round(performance.punctuality_percent)}" data-count-suffix="%">${punctuality}</strong><span>no prazo</span></div><p class="performance-trend" data-home-performance-detail>${escape(trend)}</p>`;
  }

  function initHomeAnimations() {
    if (initialAnimationPlayed) return;
    initialAnimationPlayed = true;
    if (!canAnimate()) return;

    const gsap = window.gsap;
    const all = (selector) => Array.from(document.querySelectorAll(selector));
    const card = (name) => $(`[data-home-card="${name}"]`);
    const visibleCards = [
      card("attention"),
      card("resume"),
      card("shortcuts"),
    ].filter((element) => element && !element.hidden);
    const headerItems = all(
      '[data-home-animate="greeting"], [data-home-animate="date"]',
    );
    const controls = all('[data-home-animate="control"]');
    const attentionItems = all("[data-home-attention-item]");
    const attentionDots = all("[data-home-attention-dot]");
    const currentDetails = all("[data-home-current-detail]");
    const shortcuts = all("[data-home-shortcut]");
    const timelineContent = all("[data-home-timeline-content]");
    const timelineDots = all("[data-home-timeline-dot]");
    const timelineBadges = all("[data-home-timeline-badge]");
    const metricCounts = all("[data-home-count]");
    const lowerCards = [card("next"), card("progress")].filter(Boolean);

    const clear = "transform,opacity,visibility,filter";
    const animation = gsap.timeline({
      defaults: { ease: "power2.out", overwrite: "auto" },
    });

    animation
      .fromTo(
        '[data-home-animate="background"]',
        { autoAlpha: 0, filter: "blur(5px)" },
        {
          autoAlpha: 1,
          filter: "blur(0px)",
          duration: 0.36,
          clearProps: clear,
        },
        0,
      )
      .fromTo(
        headerItems,
        { autoAlpha: 0, y: -10 },
        {
          autoAlpha: 1,
          y: 0,
          duration: 0.34,
          stagger: 0.07,
          clearProps: clear,
        },
        0.1,
      )
      .fromTo(
        '[data-home-animate="quote"]',
        { autoAlpha: 0, x: 10 },
        { autoAlpha: 1, x: 0, duration: 0.34, clearProps: clear },
        0.19,
      )
      .fromTo(
        controls,
        { autoAlpha: 0, scale: 0.95 },
        {
          autoAlpha: 1,
          scale: 1,
          duration: 0.28,
          stagger: 0.06,
          clearProps: clear,
        },
        0.22,
      )
      .fromTo(
        visibleCards,
        { autoAlpha: 0, y: 14, scale: 0.985 },
        {
          autoAlpha: 1,
          y: 0,
          scale: 1,
          duration: 0.43,
          stagger: 0.1,
          clearProps: clear,
        },
        0.2,
      )
      .fromTo(
        attentionItems,
        { autoAlpha: 0, x: -7 },
        {
          autoAlpha: 1,
          x: 0,
          duration: 0.26,
          stagger: 0.065,
          clearProps: clear,
        },
        0.38,
      )
      .fromTo(
        "[data-home-attention-count]",
        { autoAlpha: 0, scale: 0.7 },
        {
          autoAlpha: 1,
          scale: 1,
          duration: 0.32,
          ease: "back.out(1.5)",
          clearProps: clear,
        },
        0.44,
      )
      .fromTo(
        attentionDots,
        { autoAlpha: 0, scale: 0.75 },
        {
          autoAlpha: 1,
          scale: 1,
          duration: 0.22,
          stagger: 0.06,
          clearProps: clear,
        },
        0.44,
      )
      .to(
        attentionDots,
        {
          boxShadow: "0 0 7px currentColor",
          duration: 0.15,
          yoyo: true,
          repeat: 1,
          clearProps: "box-shadow",
        },
        0.61,
      )
      .fromTo(
        "[data-home-current-preview] img",
        { scale: 1.04 },
        {
          scale: 1,
          duration: 0.95,
          ease: "power2.out",
          clearProps: "transform",
        },
        0.4,
      )
      .fromTo(
        currentDetails,
        { autoAlpha: 0, y: 8 },
        { autoAlpha: 1, y: 0, duration: 0.3, stagger: 0.07, clearProps: clear },
        0.52,
      )
      .fromTo(
        "[data-home-current-cta]",
        { autoAlpha: 0, y: 8 },
        { autoAlpha: 1, y: 0, duration: 0.3, clearProps: clear },
        0.64,
      )
      .to(
        "[data-home-current-cta]",
        {
          scale: 1.02,
          duration: 0.11,
          yoyo: true,
          repeat: 1,
          clearProps: "transform",
        },
        0.94,
      )
      .fromTo(
        shortcuts,
        { autoAlpha: 0, y: 8, scale: 0.96 },
        {
          autoAlpha: 1,
          y: 0,
          scale: 1,
          duration: 0.3,
          stagger: 0.06,
          clearProps: clear,
        },
        0.45,
      )
      .fromTo(
        lowerCards,
        { autoAlpha: 0, y: 12, scale: 0.99 },
        {
          autoAlpha: 1,
          y: 0,
          scale: 1,
          duration: 0.36,
          stagger: 0.08,
          clearProps: clear,
        },
        0.57,
      )
      .fromTo(
        "[data-home-timeline-line]",
        { scaleY: 0, transformOrigin: "top" },
        {
          scaleY: 1,
          duration: 0.38,
          ease: "power1.out",
          clearProps: "transform",
        },
        0.68,
      )
      .fromTo(
        timelineDots,
        { autoAlpha: 0, scale: 0 },
        {
          autoAlpha: 1,
          scale: 1,
          duration: 0.25,
          stagger: 0.09,
          ease: "back.out(1.4)",
          clearProps: clear,
        },
        0.74,
      )
      .fromTo(
        timelineContent,
        { autoAlpha: 0, x: 8 },
        {
          autoAlpha: 1,
          x: 0,
          duration: 0.28,
          stagger: 0.08,
          clearProps: clear,
        },
        0.77,
      )
      .fromTo(
        timelineBadges,
        { autoAlpha: 0, y: 5 },
        {
          autoAlpha: 1,
          y: 0,
          duration: 0.22,
          stagger: 0.08,
          clearProps: clear,
        },
        0.94,
      )
      .fromTo(
        "[data-home-performance-detail]",
        { autoAlpha: 0, y: 7 },
        {
          autoAlpha: 1,
          y: 0,
          duration: 0.28,
          stagger: 0.08,
          clearProps: clear,
        },
        0.98,
      );

    metricCounts.forEach((element) => {
      const value = Number(element.dataset.countValue);
      if (!Number.isFinite(value)) return;
      const suffix = element.dataset.countSuffix || "";
      const counter = { value: 0 };
      animation.to(
        counter,
        {
          value,
          duration: 0.95,
          ease: "power1.out",
          onUpdate: () => {
            element.textContent = `${Math.round(counter.value)}${suffix}`;
          },
        },
        0.7,
      );
    });
  }

  function render(data) {
    const work = data.work || data.personal_work || {};
    renderAttention(data.attention || []);
    renderCurrent(work.current || null);
    renderNext(work.next || []);
    renderQuickAccess(data.quick_access || []);
    renderPerformance(data.performance || null);
    $("#homeContent").setAttribute("aria-busy", "false");
    initHomeAnimations();
  }

  async function load() {
    try {
      const response = await fetch("getHome.php", {
        headers: { Accept: "application/json" },
        credentials: "same-origin",
      });
      const data = await response.json();
      if (!response.ok || !data.success)
        throw new Error(data.error || "Não foi possível carregar a Home.");
      render(data);
    } catch (error) {
      $("#attentionList").classList.remove("home-skeleton-list");
      $("#attentionList").innerHTML = empty(
        error.message || "Não foi possível carregar prioridades.",
      );
      $("#currentWork").classList.remove("home-skeleton-current");
      $("#currentWork").innerHTML = empty("Tente atualizar a página.");
      $("#performancePanel").classList.remove("home-skeleton-current");
      $("#performancePanel").innerHTML = empty("Resumo indisponível.");
      $("#homeContent").setAttribute("aria-busy", "false");
    }
  }
  initTheme();
  updateHeader();
  initTruncationTooltips();
  load();
})();
