(function () {
  function init() {
    if (window.__improovSidebarInitialized) return;

    var sidebar = document.querySelector(".sidebar");
    if (!sidebar) return;
    window.__improovSidebarInitialized = true;

    var basePath = window.location.pathname.includes("/flow/ImproovWeb")
      ? "/flow/ImproovWeb/"
      : "/ImproovWeb/";
    var railButtons = Array.from(
      sidebar.querySelectorAll("[data-sidebar-panel]"),
    );
    var sections = Array.from(
      sidebar.querySelectorAll("[data-sidebar-section]"),
    );
    var panel = sidebar.querySelector(".sidebar-panel");
    var panelTitle = sidebar.querySelector("[data-sidebar-panel-title]");
    var closeButton = sidebar.querySelector(".sidebar-panel-close");
    var projectPanel = sidebar.querySelector(".sidebar-project-panel");
    var projectSearch = sidebar.querySelector("#sidebar-project-search");
    var projectResults =
      sidebar.querySelector("[data-project-results]") ||
      sidebar.querySelector("#obras-list");
    var projectQuick = sidebar.querySelector("#sidebar-project-quick");
    var projectEmpty = sidebar.querySelector("#sidebar-project-empty");
    var projectViews = Array.from(
      sidebar.querySelectorAll("[data-project-view]"),
    );
    var globalSearch = sidebar.querySelector("#sidebar-global-search");
    var globalResults = sidebar.querySelector("#sidebar-global-results");
    var globalEmpty = sidebar.querySelector("#sidebar-global-empty");
    var globalHint = sidebar.querySelector("#sidebar-search-hint");
    var favoriteKey = "favoritos";
    var recentKey = "improov_sidebar_recent_obras";

    function ids(key) {
      try {
        var value = JSON.parse(localStorage.getItem(key));
        return Array.isArray(value) ? value.map(String) : [];
      } catch (_) {
        return [];
      }
    }

    function saveIds(key, value) {
      localStorage.setItem(key, JSON.stringify(value));
    }

    function normalizeLinks() {
      sidebar.querySelectorAll("a[href]").forEach(function (link) {
        if (link.classList.contains("obra-item")) return;
        var href = link.getAttribute("href");
        if (!href || href.charAt(0) === "#") return;
        try {
          var url = new URL(href, window.location.origin);
          var match = url.pathname.match(/^\/(?:flow\/)?ImproovWeb\/?(.*)$/i);
          if (match)
            link.href =
              window.location.origin +
              basePath +
              (match[1] || "").replace(/^\/+/, "") +
              url.search +
              url.hash;
        } catch (_) {}
      });
    }

    function projects() {
      return Array.from(
        projectResults
          ? projectResults.querySelectorAll("[data-sidebar-project]")
          : [],
      );
    }

    function projectId(item) {
      var link = item && item.querySelector(".obra-item");
      return String(
        (item && item.dataset.obraId) || (link && link.dataset.id) || "",
      );
    }

    function projectById(id) {
      return (
        projects().find(function (item) {
          return projectId(item) === String(id);
        }) || null
      );
    }

    function syncFavorites() {
      var favorites = ids(favoriteKey);
      sidebar
        .querySelectorAll("[data-sidebar-project]")
        .forEach(function (item) {
          item.querySelectorAll(".favorite-icon").forEach(function (icon) {
            var active = favorites.includes(projectId(item));
            icon.classList.toggle("favorited", active);
            icon.setAttribute(
              "aria-label",
              active ? "Remover dos favoritos" : "Adicionar aos favoritos",
            );
          });
        });
    }

    function cloneProject(item) {
      var clone = item.cloneNode(true);
      clone.removeAttribute("hidden");
      clone.querySelectorAll(".sidebar-badge").forEach(function (badge) {
        badge.remove();
      });
      return clone;
    }

    function renderQuickProjects() {
      if (!projectQuick) return;
      var favorites = ids(favoriteKey);
      var recent = ids(recentKey).filter(function (id) {
        return !favorites.includes(id);
      });
      projectQuick.replaceChildren();
      [
        ["Favoritos", favorites],
        ["Recentes", recent],
      ].forEach(function (group) {
        var matching = group[1].map(projectById).filter(Boolean);
        if (!matching.length) return;
        var section = document.createElement("section");
        var title = document.createElement("p");
        var list = document.createElement("ul");
        title.className = "sidebar-project-quick-title";
        title.textContent = group[0];
        matching.forEach(function (item) {
          list.appendChild(cloneProject(item));
        });
        section.append(title, list);
        projectQuick.appendChild(section);
      });
      if (!projectQuick.children.length) {
        var hint = document.createElement("p");
        hint.className = "sidebar-project-empty";
        hint.textContent = "Busque um projeto ou marque-o como favorito.";
        projectQuick.appendChild(hint);
      }
    }

    function restoreProjectList() {
      projects().forEach(function (item) {
        item.hidden = false;
      });
      projectResults
        .querySelectorAll(
          ".sidebar-package-label, .sidebar-project-status-label",
        )
        .forEach(function (item) {
          item.hidden = false;
        });
      projectEmpty.hidden = true;
    }

    function setProjectView(view) {
      if (!projectPanel) return;
      projectSearch.value = "";
      projectPanel.classList.remove("is-searching");
      projectPanel.classList.toggle("is-browsing", view === "all");
      projectViews.forEach(function (button) {
        button.classList.toggle(
          "is-active",
          button.dataset.projectView === view,
        );
      });
      restoreProjectList();
      if (view !== "all") renderQuickProjects();
    }

    function searchProjects(value) {
      var query = value.trim().toLocaleLowerCase("pt-BR");
      if (!query) {
        setProjectView("quick");
        return;
      }
      projectPanel.classList.add("is-searching");
      projectPanel.classList.remove("is-browsing");
      var matched = 0;
      projects().forEach(function (item) {
        var link = item.querySelector(".obra-item");
        var name = (
          (link && link.dataset.name) ||
          item.textContent ||
          ""
        ).toLocaleLowerCase("pt-BR");
        var show = name.includes(query);
        item.hidden = !show;
        if (show) matched += 1;
      });
      projectResults
        .querySelectorAll(
          ".sidebar-package-label, .sidebar-project-status-label",
        )
        .forEach(function (item) {
          item.hidden = true;
        });
      projectEmpty.hidden = matched !== 0;
    }

    function renderGlobalSearch(value) {
      var query = value.trim().toLocaleLowerCase("pt-BR");
      globalResults.replaceChildren();
      globalHint.hidden = Boolean(query);
      globalEmpty.hidden = true;
      if (!query) return;
      var count = 0;
      var routes = {};
      var names = {};
      sidebar
        .querySelectorAll(".sidebar-panel-links a")
        .forEach(function (link) {
          if (count >= 12) return;
          var name = (link.title || link.textContent || "").trim();
          var route = link.getAttribute("href") || name;
          if (
            routes[route] ||
            names[name] ||
            !name.toLocaleLowerCase("pt-BR").includes(query)
          )
            return;
          routes[route] = true;
          names[name] = true;
          var wrapper = document.createElement("li");
          var clone = link.cloneNode(true);
          clone.querySelectorAll(".sidebar-badge").forEach(function (badge) {
            badge.remove();
          });
          wrapper.appendChild(clone);
          globalResults.appendChild(wrapper);
          count += 1;
        });
      var seenProjects = {};
      projects().forEach(function (item) {
        if (count >= 24 || seenProjects[item.dataset.obraId]) return;
        var link = item.querySelector(".obra-item");
        var name = (
          (link && link.dataset.name) ||
          item.textContent ||
          ""
        ).toLocaleLowerCase("pt-BR");
        if (!name.includes(query)) return;
        seenProjects[item.dataset.obraId] = true;
        globalResults.appendChild(cloneProject(item));
        count += 1;
      });
      globalEmpty.hidden = count !== 0;
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
      setProjectView("quick");
      globalSearch.value = "";
      renderGlobalSearch("");
    }

    function openPanel(name) {
      var section = sidebar.querySelector(
        '[data-sidebar-section="' + name + '"]',
      );
      var button = sidebar.querySelector('[data-sidebar-panel="' + name + '"]');
      if (!section || !button) return;
      if (sidebar.classList.contains("is-open") && !section.hidden) {
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
      panelTitle.textContent =
        button.getAttribute("aria-label") || button.textContent.trim();
      if (name === "obras") setProjectView("quick");
      if (name === "busca") globalSearch.focus();
    }

    function selectProject(link) {
      if (!link.dataset.id) return;
      localStorage.setItem("obraId", link.dataset.id);
      localStorage.setItem("obraNome", link.dataset.name || "");
      var recent = ids(recentKey).filter(function (id) {
        return id !== String(link.dataset.id);
      });
      recent.unshift(String(link.dataset.id));
      saveIds(recentKey, recent.slice(0, 5));
      window.location.href =
        window.location.origin + basePath + "Dashboard/obra";
    }

    normalizeLinks();
    if (Array.isArray(window.IMPROOV_ALLOWED_OBRA_IDS)) {
      var allowed = window.IMPROOV_ALLOWED_OBRA_IDS.map(String);
      // Algumas telas incluem a sidebar antes de carregar as obras. Não use uma
      // lista vazia para limpar preferências locais, pois isso apagaria os
      // favoritos e recentes que poderão ser exibidos em outra tela.
      if (allowed.length) {
        [favoriteKey, recentKey].forEach(function (key) {
          saveIds(
            key,
            ids(key).filter(function (id) {
              return allowed.includes(id);
            }),
          );
        });
      }
    }
    syncFavorites();

    railButtons.forEach(function (button) {
      button.addEventListener("click", function () {
        sidebar.dataset.lastSidebarAction = button.dataset.sidebarPanel;
        openPanel(button.dataset.sidebarPanel);
      });
    });
    closeButton.addEventListener("click", closePanel);
    projectSearch.addEventListener("input", function () {
      searchProjects(projectSearch.value);
    });
    projectViews.forEach(function (button) {
      button.addEventListener("click", function () {
        setProjectView(button.dataset.projectView);
      });
    });
    globalSearch.addEventListener("input", function () {
      renderGlobalSearch(globalSearch.value);
    });

    document.addEventListener("click", function (event) {
      var favorite = event.target.closest(".favorite-icon");
      if (favorite && sidebar.contains(favorite)) {
        event.preventDefault();
        var list = ids(favoriteKey);
        var pos = list.indexOf(String(favorite.dataset.id));
        if (pos < 0) list.unshift(String(favorite.dataset.id));
        else list.splice(pos, 1);
        saveIds(favoriteKey, list);
        syncFavorites();
        renderQuickProjects();
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
        var imageSelectionModal = document.getElementById("modalSelecionarImagens");
        if (imageSelectionModal && imageSelectionModal.classList.contains("is-open")) {
          event.preventDefault();
          return;
        }

        var openModal = document.querySelector(".modal.is-open");
        if (openModal) {
          event.preventDefault();
          return;
        }

        event.preventDefault();
        openPanel("busca");
      }
      if (event.key === "Escape" && sidebar.classList.contains("is-open"))
        closePanel();
    });
  }

  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", init, { once: true });
  else init();
})();
