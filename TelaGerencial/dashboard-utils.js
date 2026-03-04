// dashboard-utils.js — animated counters and number formatting
(function () {
  function formatNumber(n) {
    if (n === null || n === undefined) return "0";
    return Number(n).toLocaleString("pt-BR");
  }

  function animateNumber(el, to, duration) {
    if (!el) return;
    duration = duration || 900;
    const raw = (el.textContent || "").replace(/\D/g, "");
    const start = raw ? parseInt(raw, 10) : 0;
    const end = parseInt(to || 0, 10);
    const range = end - start;
    if (range === 0) {
      el.textContent = formatNumber(end);
      return;
    }
    const startTime = performance.now();
    function step(now) {
      const p = Math.min((now - startTime) / duration, 1);
      const eased = 1 - Math.pow(1 - p, 3); // ease-out cubic
      el.textContent = formatNumber(Math.round(start + range * eased));
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  window.DashboardUtils = { animateNumber, formatNumber };
})();
