function initImproovSidebar() {
  if (window.__improovSidebarInitialized) return;
  window.__improovSidebarInitialized = true;

  var sidebar = document.querySelector(".sidebar");
  if (!sidebar) return;

  var basePath =
    window.location.pathname.includes("/flow/ImproovWeb/") ||
    window.location.pathname.includes("/flow/ImproovWeb")
      ? "/flow/ImproovWeb/"
      : "/ImproovWeb/";
  var railButtons = Array.from(
    sidebar.querySelectorAll("[data-sidebar-panel]"),
  );
  var sections = Array.from(sidebar.querySelectorAll("[data-sidebar-section]"));
  var panel = sidebar.querySelector(".sidebar-panel");
  var panelTitle = sidebar.querySelector("[data-sidebar-panel-title]");
  var closeButton = sidebar.querySelector(".sidebar-panel-close");
  var projectPanel = sidebar.querySelector(".sidebar-project-panel");
  var projectSearch = document.getElementById("sidebar-project-search");
  var projectResults = document.getElementById("obras-list");
  var projectQuick = document.getElementById("sidebar-project-quick");
  var projectEmpty = document.getElementById("sidebar-project-empty");
  var projectViewButtons = Array.from(
    sidebar.querySelectorAll("[data-project-view]"),
  );
  var globalSearch = document.getElementById("sidebar-global-search");
  var globalResults = document.getElementById("sidebar-global-results");
  var globalEmpty = document.getElementById("sidebar-global-empty");
  var globalHint = document.getElementById("sidebar-search-hint");
  var favoriteKey = "favoritos";
  var recentKey = "improov_sidebar_recent_obras";
  var maxRecent = 5;

  function normalizeSidebarLinks() {
    sidebar.querySelectorAll("a[href]").forEach(function (link) {
      if (link.classList.contains("obra-item")) return;
      var rawHref = link.getAttribute("href");
      if (
        !rawHref ||
        rawHref.startsWith("#") ||
        rawHref.startsWith("javascript:")
      )
        return;

      try {
        var parsed = new URL(rawHref, window.location.origin);
        var match = parsed.pathname.match(/^\/(?:flow\/)?ImproovWeb\/?(.*)$/i);
        if (!match) return;
        link.setAttribute(
          "href",
          window.location.origin +
            basePath +
            (match[1] || "").replace(/^\/+/, "") +
            parsed.search +
            parsed.hash,
        );
      } catch (_) {
        // Mantém o href original se ele não puder ser normalizado.
      }
    });
  }

  function readIds(key) {
    try {
      var values = JSON.parse(localStorage.getItem(key));
      return Array.isArray(values) ? values.map(String) : [];
    } catch (_) {
      return [];
    }
  }

  function writeIds(key, values) {
    localStorage.setItem(key, JSON.stringify(values));
  }

  function allowedProjectIds() {
    return Array.from(
      projectResults
        ? projectResults.querySelectorAll("[data-sidebar-project]")
        : [],
    ).map(function (item) {
      return String(item.dataset.obraId);
    });
  }

  function projectItemById(id) {
    if (!projectResults) return null;
    return (
      Array.from(
        projectResults.querySelectorAll("[data-sidebar-project]"),
      ).find(function (item) {
        return String(item.dataset.obraId) === String(id);
      }) || null
    );
  }

  function setFavoriteState(item, isFavorite) {
    if (!item) return;
    item.querySelectorAll(".favorite-icon").forEach(function (icon) {
      icon.classList.toggle("favorited", isFavorite);
      icon.setAttribute(
        "aria-label",
        isFavorite ? "Remover dos favoritos" : "Adicionar aos favoritos",
      );
    });
  }

  function syncFavoriteIndicators() {
    var favoriteIds = readIds(favoriteKey);
    sidebar.querySelectorAll("[data-sidebar-project]").forEach(function (item) {
      setFavoriteState(item, favoriteIds.includes(String(item.dataset.obraId)));
    });
  }

  function quickProjectClone(item) {
    var clone = item.cloneNode(true);
    clone.removeAttribute("hidden");
    clone.classList.add("sidebar-project-quick-item");
    clone.querySelectorAll(".sidebar-badge").forEach(function (badge) {
      badge.remove();
    });
    return clone;
  }

  function renderQuickProjects() {
    if (!projectQuick || !projectResults) return;
    var favorites = readIds(favoriteKey);
    var recents = readIds(recentKey);
    var groups = [
      { title: "Favoritas", ids: favorites },
      {
        title: "Recentes",
        ids: recents.filter(function (id) {
          return !favorites.includes(id);
        }),
      },
    ];

    projectQuick.replaceChildren();
    groups.forEach(function (group) {
      var items = group.ids.map(projectItemById).filter(Boolean);
      if (!items.length) return;
      var container = document.createElement("section");
      var title = document.createElement("p");
      var list = document.createElement("ul");
      title.className = "sidebar-project-quick-title";
      title.textContent = group.title;
      items.forEach(function (item) {
        list.appendChild(quickProjectClone(item));
      });
      container.append(title, list);
      projectQuick.appendChild(container);
    });

    if (!projectQuick.children.length) {
      var hint = document.createElement("p");
      hint.className = "sidebar-project-empty";
      hint.textContent = "Busque uma obra ou marque-a como favorita.";
      projectQuick.appendChild(hint);
    }
  }

  function clearProjectSearch() {
    if (!projectSearch || !projectPanel || !projectResults) return;
    projectSearch.value = "";
    projectPanel.classList.remove("is-searching");
    projectResults.querySelectorAll("[hidden]").forEach(function (item) {
      item.hidden = false;
    });
    projectEmpty.hidden = true;
  }

  function setProjectView(view) {
    if (!projectPanel || !projectResults) return;
    clearProjectSearch();
    var showAll = view === "all";
    projectPanel.classList.toggle("is-browsing", showAll);
    projectViewButtons.forEach(function (button) {
      button.classList.toggle("is-active", button.dataset.projectView === view);
    });
    if (!showAll) renderQuickProjects();
  }

  function filterProjects(query) {
    if (!projectResults || !projectPanel || !projectEmpty) return;
    var normalized = query.trim().toLocaleLowerCase("pt-BR");
    var projects = Array.from(
      projectResults.querySelectorAll("[data-sidebar-project]"),
    );
    var labels = Array.from(
      projectResults.querySelectorAll(
        ".sidebar-package-label, .sidebar-project-status-label",
      ),
    );
    projectPanel.classList.toggle("is-searching", Boolean(normalized));
    if (normalized) projectPanel.classList.remove("is-browsing");

    if (!normalized) {
      clearProjectSearch();
      return;
    }

    labels.forEach(function (label) {
      label.hidden = true;
    });
    var matches = 0;
    projects.forEach(function (item) {
      var name =
        (item.querySelector(".obra-item") || item).dataset.name ||
        item.textContent;
      var isMatch = name.toLocaleLowerCase("pt-BR").includes(normalized);
      item.hidden = !isMatch;
      if (!isMatch) return;
      matches += 1;

      var previous = item.previousElementSibling;
      while (previous) {
        if (
          previous.classList.contains("sidebar-package-label") ||
          previous.classList.contains("sidebar-project-status-label")
        ) {
          previous.hidden = false;
          if (previous.classList.contains("sidebar-project-status-label"))
            break;
        }
        previous = previous.previousElementSibling;
      }
    });
    projectEmpty.hidden = matches !== 0;
  }

  function createGlobalResult(link) {
    var item = document.createElement("li");
    var clone = link.cloneNode(true);
    clone.querySelectorAll(".sidebar-badge").forEach(function (badge) {
      badge.remove();
    });
    item.appendChild(clone);
    return item;
  }

  function createGlobalProjectResult(project) {
    var item = project.cloneNode(true);
    item.removeAttribute("hidden");
    item.classList.add("sidebar-search-project");
    item.querySelectorAll(".sidebar-badge").forEach(function (badge) {
      badge.remove();
    });
    return item;
  }

  function renderGlobalSearch(query) {
    if (!globalResults || !globalEmpty || !globalHint) return;
    var normalized = query.trim().toLocaleLowerCase("pt-BR");
    globalResults.replaceChildren();
    globalHint.hidden = Boolean(normalized);
    globalEmpty.hidden = true;
    if (!normalized) return;

    var shown = 0;
    var seenRoutes = {};
    Array.from(sidebar.querySelectorAll(".sidebar-panel-links a")).forEach(
      function (link) {
        if (shown >= 12) return;
        var label = (
          link.getAttribute("title") ||
          link.textContent ||
          ""
        ).trim();
        var route = link.getAttribute("href") || label;
        if (
          seenRoutes[route] ||
          !label.toLocaleLowerCase("pt-BR").includes(normalized)
        )
          return;
        seenRoutes[route] = true;
        globalResults.appendChild(createGlobalResult(link));
        shown += 1;
      },
    );

    var seenProjects = {};
    Array.from(
      projectResults
        ? projectResults.querySelectorAll("[data-sidebar-project]")
        : [],
    ).forEach(function (project) {
      if (shown >= 24 || seenProjects[project.dataset.obraId]) return;
      var link = project.querySelector(".obra-item");
      var name = (link && link.dataset.name) || project.textContent || "";
      if (!name.toLocaleLowerCase("pt-BR").includes(normalized)) return;
      seenProjects[project.dataset.obraId] = true;
      globalResults.appendChild(createGlobalProjectResult(project));
      shown += 1;
    });

    globalEmpty.hidden = shown !== 0;
  }

  function clearGlobalSearch() {
    if (!globalSearch) return;
    globalSearch.value = "";
    renderGlobalSearch("");
  }

  function closePanel() {
    sidebar.classList.remove("is-open");
    panel.setAttribute("aria-hidden", "true");
    railButtons.forEach(function (button) {
      button.setAttribute("aria-expanded", "false");
    });
    sections.forEach(function (section) {
      section.hidden = true;
    });
    clearProjectSearch();
    clearGlobalSearch();
  }

  function openPanel(name) {
    var section = sidebar.querySelector(
      '[data-sidebar-section="' + name + '"]',
    );
    var button = sidebar.querySelector('[data-sidebar-panel="' + name + '"]');
    if (!section || !button) return;
    var isCurrent = sidebar.classList.contains("is-open") && !section.hidden;
    if (isCurrent) {
      closePanel();
      return;
    }

    sections.forEach(function (item) {
      item.hidden = item !== section;
    });
    railButtons.forEach(function (item) {
      item.setAttribute("aria-expanded", String(item === button));
    });
    sidebar.classList.add("is-open");
    panel.setAttribute("aria-hidden", "false");
    panelTitle.textContent = button.textContent.trim();
    clearProjectSearch();
    if (name === "obras") renderQuickProjects();
    if (name === "busca" && globalSearch) globalSearch.focus();
  }

  function toggleFavorite(id) {
    var favorites = readIds(favoriteKey);
    var position = favorites.indexOf(String(id));
    if (position === -1) favorites.unshift(String(id));
    else favorites.splice(position, 1);
    writeIds(favoriteKey, favorites);
    syncFavoriteIndicators();
    renderQuickProjects();
  }

  function rememberRecent(id) {
    var recents = readIds(recentKey).filter(function (recentId) {
      return recentId !== String(id);
    });
    recents.unshift(String(id));
    writeIds(recentKey, recents.slice(0, maxRecent));
  }

  function selectProject(link) {
    var obraId = link.dataset.id;
    if (!obraId) return;
    localStorage.setItem("obraId", obraId);
    localStorage.setItem("obraNome", link.dataset.name || "");
    rememberRecent(obraId);
    window.location.href = window.location.origin + basePath + "Dashboard/obra";
  }

  normalizeSidebarLinks();
  var knownProjects = allowedProjectIds();
  if (Array.isArray(window.IMPROOV_ALLOWED_OBRA_IDS)) {
    var allowed = window.IMPROOV_ALLOWED_OBRA_IDS.map(String);
    [favoriteKey, recentKey].forEach(function (key) {
      writeIds(
        key,
        readIds(key).filter(function (id) {
          return allowed.includes(id);
        }),
      );
    });
    var selectedObraId = localStorage.getItem("obraId");
    if (selectedObraId && !allowed.includes(String(selectedObraId))) {
      localStorage.removeItem("obraId");
      localStorage.removeItem("obraNome");
    }
  } else {
    [favoriteKey, recentKey].forEach(function (key) {
      writeIds(
        key,
        readIds(key).filter(function (id) {
          return knownProjects.includes(id);
        }),
      );
    });
  }
  syncFavoriteIndicators();

  railButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      openPanel(button.dataset.sidebarPanel);
    });
  });
  closeButton.addEventListener("click", closePanel);
  projectSearch.addEventListener("input", function () {
    filterProjects(projectSearch.value);
  });
  projectViewButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      setProjectView(button.dataset.projectView);
    });
  });
  if (globalSearch) {
    globalSearch.addEventListener("input", function () {
      renderGlobalSearch(globalSearch.value);
    });
  }

  document.addEventListener("click", function (event) {
    var favoriteIcon = event.target.closest(".favorite-icon");
    if (favoriteIcon && sidebar.contains(favoriteIcon)) {
      event.preventDefault();
      event.stopPropagation();
      toggleFavorite(favoriteIcon.dataset.id);
      return;
    }

    var projectLink = event.target.closest(".obra-item");
    if (projectLink && sidebar.contains(projectLink)) {
      event.preventDefault();
      selectProject(projectLink);
      return;
    }

    if (
      sidebar.classList.contains("is-open") &&
      !sidebar.contains(event.target)
    )
      closePanel();
  });

  document.addEventListener("keydown", function (event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
      event.preventDefault();
      openPanel("busca");
      return;
    }
    if (event.key === "Escape" && sidebar.classList.contains("is-open"))
      closePanel();
  });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initImproovSidebar, {
    once: true,
  });
} else {
  initImproovSidebar();
}
