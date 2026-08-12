/* sidebar-counts.js
   Fetches aggregated sidebar counts and updates badge placeholders.
   Uses window.IMPROOV_APP_BASE for the base path and polls every 30s.
*/
(function () {
  if (!window.fetch) return;

  var POLL_MS = 30000;
  var isFetching = false;

  function setBadge(el, n) {
    if (!el) return;
    var num = parseInt(n) || 0;
    if (num > 0) {
      el.textContent = String(num);
      el.style.display = "inline-grid";
      el.removeAttribute("aria-hidden");
      el.setAttribute("aria-label", num + " notificações pendentes");
      el.classList.remove("is-small");
    } else {
      el.textContent = "";
      el.style.display = "none";
      el.setAttribute("aria-hidden", "true");
      el.removeAttribute("aria-label");
    }
  }

  var RAIL_GROUPS = {
    producao: [
      "flow_review",
      "render",
      "pos_producao",
      "pre_alt_analise",
      "entregas",
      "entregas_pendencias",
    ],
    gestao: ["onboarding"],
    alertas: [
      "flow_review",
      "render",
      "pos_producao",
      "pre_alt_analise",
      "entregas",
      "entregas_pendencias",
      "onboarding",
    ],
  };

  var CENTRAL_MODULE_ALIASES = {
    FLOW_REVIEW: "flow_review",
    ENTREGAS: "entregas",
    PRE_ALTERACAO: "pre_alt_analise",
    POS_PRODUCAO: "pos_producao",
    RENDER: "render",
    ONBOARDING: "onboarding",
  };

  function centralModuleKey(code) {
    var normalized = String(code || "GERAL").toUpperCase();
    return CENTRAL_MODULE_ALIASES[normalized] ||
      "notification_" + normalized.toLowerCase().replace(/[^a-z0-9]+/g, "_");
  }

  function mergeCentralModuleCounts(modules, notificationModules) {
    var merged = Object.assign({}, modules || {});
    (notificationModules || []).forEach(function (module) {
      var key = centralModuleKey(module.codigo);
      merged[key] = (parseInt(merged[key]) || 0) + (parseInt(module.total) || 0);
    });
    return merged;
  }

  function resolveModuleHref(rawHref) {
    var href = String(rawHref || "notificacoes");
    try {
      var parsed = new URL(href, window.location.origin);
      var match = parsed.pathname.match(/^\/(?:flow\/)?ImproovWeb\/?(.*)$/i);
      if (!match) return parsed.href;
      return window.location.origin +
        (window.IMPROOV_APP_BASE || "/ImproovWeb") +
        "/" +
        (match[1] || "").replace(/^\/+/, "") +
        parsed.search +
        parsed.hash;
    } catch (_) {
      return href;
    }
  }

  function renderCentralNotificationModules(notificationModules) {
    var list = document.querySelector("[data-sidebar-alerts-list]");
    if (!list) return;

    var activeKeys = {};
    (notificationModules || []).forEach(function (module) {
      var code = String(module.codigo || "GERAL").toUpperCase();
      var key = centralModuleKey(code);
      if (CENTRAL_MODULE_ALIASES[code]) return;
      activeKeys[key] = true;

      var item = list.querySelector('[data-sidebar-central-module="' + key + '"]');
      if (!item) {
        item = document.createElement("li");
        item.setAttribute("data-sidebar-central-module", key);
        item.setAttribute("data-sidebar-alert-module", key);

        var link = document.createElement("a");
        link.className = "sidebar-central-alert-link";
        link.target = "_self";

        var icon = document.createElement("i");
        var iconTokens = String(module.icone || "fa-bell")
          .split(/\s+/)
          .filter(function (token) { return /^fa-/.test(token); });
        icon.className = ["fa-solid"].concat(iconTokens).join(" ");

        var label = document.createElement("span");
        var badge = document.createElement("span");
        badge.className = "sidebar-badge";
        badge.setAttribute("data-module", key);
        badge.setAttribute("aria-hidden", "true");
        link.append(icon, label, badge);
        item.appendChild(link);

        var empty = list.querySelector("[data-sidebar-alerts-empty]");
        list.insertBefore(item, empty || null);
      }

      var link = item.querySelector("a");
      var label = item.querySelector("a > span:not(.sidebar-badge)");
      var badge = item.querySelector(".sidebar-badge");
      var name = String(module.nome || "Notificações gerais");
      if (link) {
        link.href = resolveModuleHref(module.url);
        link.title = name;
      }
      if (label) label.textContent = name;
      setBadge(badge, module.total);
    });

    list.querySelectorAll("[data-sidebar-central-module]").forEach(function (item) {
      if (!activeKeys[item.getAttribute("data-sidebar-central-module")]) item.remove();
    });
  }

  function setRailBadge(el, n) {
    if (!el) return;
    var num = parseInt(n) || 0;
    if (num > 0) {
      el.textContent = num > 99 ? "99+" : String(num);
      el.style.display = "inline-block";
      el.removeAttribute("aria-hidden");
      el.setAttribute("aria-label", num + " notificações pendentes");
    } else {
      el.textContent = "";
      el.style.display = "none";
      el.setAttribute("aria-hidden", "true");
      el.removeAttribute("aria-label");
    }
  }

  function updateAlertModules(modules) {
    var visible = 0;
    document.querySelectorAll("[data-sidebar-alert-module]").forEach(function (item) {
      var key = item.getAttribute("data-sidebar-alert-module");
      var count = parseInt(modules[key]) || 0;
      if (key === "entregas") {
        count += parseInt(modules.entregas_pendencias) || 0;
      }
      var hasNotifications = count > 0;
      item.hidden = !hasNotifications;
      item.setAttribute("aria-hidden", hasNotifications ? "false" : "true");
      if (hasNotifications) visible += 1;
    });
    var empty = document.querySelector("[data-sidebar-alerts-empty]");
    if (empty) empty.hidden = visible > 0;
  }

  function updateRailBadges(modules) {
    Object.keys(RAIL_GROUPS).forEach(function (group) {
      var total;
      if (group === "alertas") {
        total = Object.keys(modules).reduce(function (sum, key) {
          return sum + (key === "obras_updates" ? 0 : (parseInt(modules[key]) || 0));
        }, 0);
      } else {
        total = RAIL_GROUPS[group].reduce(function (sum, key) {
          return sum + (parseInt(modules[key]) || 0);
        }, 0);
      }
      document
        .querySelectorAll('[data-sidebar-sum="' + group + '"]')
        .forEach(function (badge) {
          setRailBadge(badge, total);
        });
    });
  }

  function updateBadges(data) {
    try {
      if (!data) return;
      var notificationModules = data.notification_modules || [];
      var moduleCounts = mergeCentralModuleCounts(data.modules || {}, notificationModules);
      if (data.modules) {
        Object.keys(moduleCounts).forEach(function (k) {
          document
            .querySelectorAll('.sidebar-badge[data-module="' + k + '"]')
            .forEach(function (el) {
              var badgeValue = moduleCounts[k];
              if (k === "entregas" && el.closest("[data-sidebar-alert-module]")) {
                badgeValue = (parseInt(moduleCounts.entregas) || 0) +
                  (parseInt(moduleCounts.entregas_pendencias) || 0);
              }
              setBadge(el, badgeValue);
            });
        });

        var entregasPendencias =
          parseInt(moduleCounts.entregas_pendencias) || 0;
        var entregasLinks = document.querySelectorAll(
          '[data-module-link="entregas"]',
        );

        if (entregasPendencias > 0) {
          entregasLinks.forEach(function (entregasLink) {
            var pendingHref = entregasLink.getAttribute("data-pending-href");
            if (pendingHref) entregasLink.setAttribute("href", pendingHref);
          });
        } else {
          entregasLinks.forEach(function (entregasLink) {
            var defaultHref = entregasLink.getAttribute("data-default-href");
            if (defaultHref) entregasLink.setAttribute("href", defaultHref);
          });
        }

        renderCentralNotificationModules(notificationModules);
        updateAlertModules(moduleCounts);
        updateRailBadges(moduleCounts);
      }
      if (data.counts_by_obra) {
        Object.keys(data.counts_by_obra).forEach(function (obraId) {
          document
            .querySelectorAll('.sidebar-badge[data-obra-id="' + obraId + '"]')
            .forEach(function (el) {
              setBadge(el, data.counts_by_obra[obraId]);
            });
        });
      }
    } catch (e) {
      console.debug("updateBadges error", e);
    }
  }

  async function fetchCounts() {
    if (isFetching) return;
    isFetching = true;
    try {
      var base = window.IMPROOV_APP_BASE || "";
      var url = base + "/sidebar_counts.php";
      var resp = await fetch(url, {
        credentials: "same-origin",
        cache: "no-store",
      });
      if (!resp.ok) return;
      var js = await resp.json();
      if (js && js.ok) updateBadges(js);
    } catch (e) {
      console.debug("sidebar-counts fetch error", e);
    } finally {
      isFetching = false;
    }
  }

  window.refreshSidebarCounts = fetchCounts;

  document.addEventListener("DOMContentLoaded", function () {
    document
      .querySelectorAll(".sidebar-badge[data-href]")
      .forEach(function (badge) {
        badge.addEventListener("click", function (event) {
          var href = badge.getAttribute("data-href");
          if (!href) return;
          event.preventDefault();
          event.stopPropagation();
          window.location.href = href;
        });
      });

    fetchCounts();
    setInterval(fetchCounts, POLL_MS);
  });
})();
