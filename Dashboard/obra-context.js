/* URL/server context is local to this document. Storage is legacy compatibility only. */
(() => {
  const element = document.getElementById('flow-obra-context');
  if (!element || window.FlowProjectContext) return;
  const seed = JSON.parse(element.textContent);
  const positiveId = value => {
    const text = String(value ?? '');
    if (!/^\d+$/.test(text)) return '';
    const id = Number(text);
    return Number.isSafeInteger(id) && id > 0 ? String(id) : '';
  };
  const readCache = key => {
    try { return localStorage.getItem(key); } catch (_) { return null; }
  };
  const obraId = positiveId(seed.obraId);
  if (!obraId) {
    // Old bookmarks/sidebar links lack an ID. Resolve once, then let PHP validate
    // the explicit URL before rendering the actual project or starting its APIs.
    const cached = positiveId(readCache('obraId'));
    const destination = new URL(location.href);
    if (cached) destination.searchParams.set('obra_id', cached);
    else {
      const base = location.pathname.startsWith('/flow/ImproovWeb/') ? '/flow/ImproovWeb' : '/ImproovWeb';
      destination.pathname = base + '/Projetos/';
      destination.search = '';
      destination.hash = '';
    }
    location.replace(destination.href);
    return;
  }

  window.FlowProjectContext = Object.freeze({
    obraId,
    usuarioId: Number(seed.usuarioId) || 0,
    colaboradorId: Number(seed.colaboradorId) || 0,
  });
  // Older links in other modules may still use the cache. No project script
  // reads it to decide which project/user this tab represents.
  try {
    if (localStorage.getItem('obraId') !== obraId) localStorage.removeItem('obraNome');
    localStorage.setItem('obraId', obraId);
    localStorage.setItem('idusuario', String(window.FlowProjectContext.usuarioId));
    localStorage.setItem('idcolaborador', String(window.FlowProjectContext.colaboradorId));
  } catch (_) { /* Explicit project URLs work without browser storage. */ }
})();
