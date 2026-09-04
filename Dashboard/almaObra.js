(() => {
  const frame = document.getElementById("obraAlmaFrame");
  const almaLinks = document.querySelectorAll(".alma-obra");

  const urlId = new URLSearchParams(window.location.search).get("obra_id");
  const obraId = Number(urlId || localStorage.getItem("obraId") || 0);

  if (!obraId) {
    if (frame) {
      frame.hidden = true;
    }

    almaLinks.forEach((link) => {
      link.removeAttribute("href");
      link.setAttribute("aria-disabled", "true");
    });

    return;
  }

  const fullUrl = `../ALMA/?obra_id=${encodeURIComponent(String(obraId))}`;

  // Desktop + mobile
  almaLinks.forEach((link) => {
    link.href = fullUrl;
    link.removeAttribute("aria-disabled");
  });

  // Embed da página ALMA, caso exista
  if (frame) {
    frame.src = `${fullUrl}&embed=1`;

    window.addEventListener("message", (event) => {
      if (
        event.origin !== window.location.origin ||
        event.source !== frame.contentWindow
      ) {
        return;
      }

      if (event.data?.type !== "alma:height") {
        return;
      }

      const height = Math.max(
        760,
        Math.min(1400, Number(event.data.height) || 0),
      );

      frame.style.height = `${height}px`;
    });
  }
})();
