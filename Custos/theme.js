// Adopt Flow's theme attribute when present; persistence uses the conventional
// theme key. The current shared sidebar exposes tokens but no global theme controller.
(() => {
  let saved;
  try {
    saved = localStorage.getItem("theme");
  } catch (_) {}
  if (!document.documentElement.dataset.theme)
    document.documentElement.dataset.theme =
      saved === "light" ? "light" : "dark";
})();
