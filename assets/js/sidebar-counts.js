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
      el.style.display = "inline-block";
      el.removeAttribute("aria-hidden");
      el.setAttribute("aria-label", num + " alertas");
      el.classList.remove("is-small");
    } else {
      el.textContent = "";
      el.style.display = "none";
      el.setAttribute("aria-hidden", "true");
      el.removeAttribute("aria-label");
    }
  }

  function updateBadges(data) {
    try {
      if (!data) return;
      if (data.modules) {
        Object.keys(data.modules).forEach(function (k) {
          var el = document.querySelector(
            '.sidebar-badge[data-module="' + k + '"]'
          );
          if (el) setBadge(el, data.modules[k]);
        });

        var entregasPendencias = parseInt(data.modules.entregas_pendencias) || 0;
        var entregasBadge = document.querySelector(
          '.sidebar-badge[data-module="entregas"]'
        );
        var entregasLink = document.querySelector(
          '[data-module-link="entregas"]'
        );

        if (entregasPendencias > 0) {
          setBadge(entregasBadge, 0);
          if (entregasLink) {
            var pendingHref = entregasLink.getAttribute("data-pending-href");
            if (pendingHref) entregasLink.setAttribute("href", pendingHref);
          }
        } else if (entregasLink) {
          var defaultHref = entregasLink.getAttribute("data-default-href");
          if (defaultHref) entregasLink.setAttribute("href", defaultHref);
        }
      }
      if (data.counts_by_obra) {
        Object.keys(data.counts_by_obra).forEach(function (obraId) {
          var el = document.querySelector(
            '.sidebar-badge[data-obra-id="' + obraId + '"]'
          );
          if (el) setBadge(el, data.counts_by_obra[obraId]);
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
      var resp = await fetch(url, { credentials: "same-origin", cache: "no-store" });
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
