/**
 * upload-badge.js
 * Barra inferior fixa de progresso de upload (Chrome/Edge style)
 * Incluído via sidebar.php após upload-ws.js
 *
 * API pública: window.UploadBadge
 *   .add(id, filename, totalBytes, xhrRef)   → cria item na barra
 *   .phase1Progress(id, percent, loaded, total) → Fase 1: browser → servidor
 *   .phase2Progress(id, percent, message)    → Fase 2: servidor → VPS/NAS
 *   .complete(id)                            → marca concluído, remove em 4s
 *   .error(id, msg)                          → marca erro, persiste até dismiss
 *   .dismiss(id)                             → remove imediatamente (aborta XHR se ativo)
 */
(function () {
  "use strict";

  const BAR_ID = "upload-badge-bar";
  const STATE_KEY = "improov_upload_state"; // persiste uploads Fase 2 entre páginas
  const STALE_MS = 4 * 60 * 60 * 1000;     // descarta estado com mais de 4h
  const STALE_REHYDRATE_MS = 15000;         // remove item rehydratado sem atualização em 15s

  // State: id → { id, name, totalBytes, xhr, phase, done, error, _staleTimer, _isRehydrated }
  const items = {};
  let activeCount = 0;

  let _barEl = null;
  let _listEl = null;
  let _countEl = null;
  let _isCollapsed = false;

  // ── LocalStorage helpers ───────────────────────────────────

  function _loadState() {
    try {
      var entries = JSON.parse(localStorage.getItem(STATE_KEY) || "[]");
      var now = Date.now();
      return entries.filter(function (e) { return e.ts && (now - e.ts) < STALE_MS; });
    } catch (e) { return []; }
  }

  function _persistItem(id) {
    var item = items[id];
    if (!item || item.done || item.error) return;
    try {
      var state = _loadState().filter(function (s) { return s.id !== id; });
      state.push({ id: id, name: item.name, totalBytes: item.totalBytes, ts: Date.now() });
      localStorage.setItem(STATE_KEY, JSON.stringify(state));
    } catch (e) {}
  }

  function _unpersistItem(id) {
    try {
      var state = _loadState().filter(function (s) { return s.id !== id; });
      if (state.length === 0) localStorage.removeItem(STATE_KEY);
      else localStorage.setItem(STATE_KEY, JSON.stringify(state));
    } catch (e) {}
  }

  // ── DOM helpers ────────────────────────────────────────────

  function _bar() {
    if (!_barEl) {
      _barEl = document.getElementById(BAR_ID);
      if (_barEl) {
        _listEl = _barEl.querySelector(".upload-badge-list");
        _countEl = _barEl.querySelector("#upload-badge-count");
        var header = _barEl.querySelector(".upload-badge-header");
        if (header) header.addEventListener("click", _toggleCollapse);
      }
    }
    return _barEl;
  }

  function _toggleCollapse() {
    _isCollapsed = !_isCollapsed;
    var b = _bar();
    if (b) b.classList.toggle("collapsed", _isCollapsed);
  }

  function _show() {
    var b = _bar();
    if (b) b.classList.add("visible");
    // auto-expand if collapsed when new item arrives
    if (_isCollapsed) {
      _isCollapsed = false;
      b.classList.remove("collapsed");
    }
  }

  function _hideIfEmpty() {
    if (Object.keys(items).length === 0) {
      var b = _bar();
      if (b) b.classList.remove("visible");
    }
  }

  function _updateCount() {
    var b = _bar();
    if (!b) return;
    var el = _countEl || b.querySelector("#upload-badge-count");
    _countEl = el;
    if (!el) return;
    var active = Object.values(items).filter(function (i) {
      return !i.done && !i.error;
    }).length;
    el.textContent = active;
  }

  function _esc(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function _fmt(bytes) {
    if (!bytes && bytes !== 0) return "";
    if (bytes < 1024) return bytes + " B";
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + " KB";
    if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + " MB";
    return (bytes / 1073741824).toFixed(2) + " GB";
  }

  function _createItemEl(id, filename) {
    var div = document.createElement("div");
    div.className = "upload-badge-item";
    div.dataset.uploadId = id;
    div.innerHTML =
      '<div class="upload-badge-item-info">' +
      '<i class="fa-solid fa-file upload-badge-item-icon"></i>' +
      '<div class="upload-badge-item-details">' +
      '<span class="upload-badge-item-name" title="' +
      _esc(filename) +
      '">' +
      _esc(filename) +
      "</span>" +
      '<span class="upload-badge-item-phase">Preparando...</span>' +
      "</div>" +
      '<span class="upload-badge-item-size"></span>' +
      '<button class="upload-badge-item-dismiss" title="Cancelar / Fechar" aria-label="Fechar">&times;</button>' +
      "</div>" +
      '<div class="upload-badge-item-bar-track">' +
      '<div class="upload-badge-item-bar"></div>' +
      "</div>";

    div
      .querySelector(".upload-badge-item-dismiss")
      .addEventListener("click", function (e) {
        e.stopPropagation();
        dismiss(id);
      });
    return div;
  }

  function _els(id) {
    var b = _bar();
    if (!b || !_listEl) return {};
    var item = _listEl.querySelector(
      '[data-upload-id="' + CSS.escape(id) + '"]',
    );
    if (!item) return {};
    return {
      item: item,
      bar: item.querySelector(".upload-badge-item-bar"),
      phase: item.querySelector(".upload-badge-item-phase"),
      size: item.querySelector(".upload-badge-item-size"),
      icon: item.querySelector(".upload-badge-item-icon"),
    };
  }

  // ── Public API ──────────────────────────────────────────────

  /**
   * Adiciona um novo item de upload à barra.
   * @param {string} id        - ID único do upload (client_id ou temporário)
   * @param {string} filename  - Nome do arquivo
   * @param {number} totalBytes - Tamanho total em bytes
   * @param {XMLHttpRequest} [xhrRef] - Referência ao XHR (para cancelamento)
   */
  function add(id, filename, totalBytes, xhrRef) {
    var b = _bar();
    if (!b || !_listEl) return id;

    // Se já existe, remove o antigo (retry)
    if (items[id]) dismiss(id);

    var el = _createItemEl(id, filename);
    _listEl.appendChild(el);
    items[id] = {
      id: id,
      name: filename,
      totalBytes: totalBytes || 0,
      xhr: xhrRef || null,
      done: false,
      error: false,
      phase: 0,
      _staleTimer: null,
      _isRehydrated: false,
    };
    activeCount++;
    _updateCount();
    _show();
    return id;
  }

  /**
   * Atualiza progresso da Fase 1 (XHR browser → servidor).
   * @param {string} id
   * @param {number} percent  - 0 a 100
   * @param {number} loaded   - bytes enviados
   * @param {number} total    - bytes totais
   */
  function phase1Progress(id, percent, loaded, total) {
    if (!items[id]) return;
    items[id].phase = 1;
    var e = _els(id);
    if (!e.bar) return;

    var pct = Math.min(100, Math.max(0, percent || 0));
    e.bar.classList.remove("indeterminate");
    e.bar.style.width = pct + "%";
    if (e.phase)
      e.phase.textContent =
        "Fase 1: Enviando para servidor — " + pct.toFixed(1) + "%";
    if (e.size && loaded !== undefined && total !== undefined) {
      e.size.textContent = _fmt(loaded) + " / " + _fmt(total);
    }
  }

  /**
   * Atualiza progresso da Fase 2 (WebSocket / background worker → VPS/NAS).
   * @param {string} id
   * @param {number} percent  - 0 a 100
   * @param {string} [message] - Mensagem opcional de status
   */
  function phase2Progress(id, percent, message) {
    if (!items[id]) return;
    // Persiste no localStorage na primeira vez que entra na Fase 2
    // (arquivo já está no servidor; worker continua mesmo após navegação)
    if (items[id].phase !== 2) {
      items[id].phase = 2;
      _persistItem(id);
    }
    var e = _els(id);
    if (!e.bar) return;

    var pct = Math.min(100, Math.max(0, percent || 0));

    // Fase 2 com 0%: mostra indeterminate (na fila)
    if (pct === 0) {
      e.bar.classList.add("indeterminate");
      if (e.item) e.item.classList.add("is-queued");
      if (e.phase)
        e.phase.textContent =
          message || "Na fila — aguardando transferência...";
      if (e.size) e.size.textContent = "";
    } else {
      e.bar.classList.remove("indeterminate");
      if (e.item) e.item.classList.remove("is-queued");
      e.bar.style.width = pct + "%";
      var label =
        message ||
        "Fase 2: Transferindo para servidor remoto — " + pct.toFixed(0) + "%";
      if (e.phase) e.phase.textContent = label;
      if (e.size) e.size.textContent = pct.toFixed(0) + "%";
    }
  }

  /**
   * Marca o upload como concluído. Remove o item após 4s.
   * @param {string} id
   */
  function complete(id) {
    var item = items[id];
    if (!item || item.done) return;
    item.done = true;
    activeCount = Math.max(0, activeCount - 1);
    if (item._staleTimer) clearTimeout(item._staleTimer);
    _unpersistItem(id);

    var e = _els(id);
    if (e.item) {
      e.item.classList.remove("is-queued");
      e.item.classList.add("is-done");
      if (e.bar) {
        e.bar.classList.remove("indeterminate");
      }
      if (e.phase) e.phase.textContent = "Concluído ✓";
      if (e.icon)
        e.icon.className = "fa-solid fa-circle-check upload-badge-item-icon";
      if (e.size) e.size.textContent = "";
    }
    _updateCount();
    setTimeout(function () {
      dismiss(id);
    }, 4000);
  }

  /**
   * Marca o upload com erro. Persiste até dismiss manual.
   * @param {string} id
   * @param {string} [msg] - Mensagem de erro
   */
  function error(id, msg) {
    var item = items[id];
    if (!item) return;
    if (item.error) return;
    item.error = true;
    activeCount = Math.max(0, activeCount - 1);
    if (item._staleTimer) clearTimeout(item._staleTimer);
    _unpersistItem(id);

    var e = _els(id);
    if (e.item) {
      e.item.classList.remove("is-queued");
      e.item.classList.add("is-error");
      if (e.bar) {
        e.bar.classList.remove("indeterminate");
      }
      if (e.phase) e.phase.textContent = msg || "Erro no envio";
      if (e.icon)
        e.icon.className =
          "fa-solid fa-circle-exclamation upload-badge-item-icon";
      if (e.size) e.size.textContent = "";
    }
    _updateCount();
  }

  /**
   * Remove o item da barra imediatamente (aborta XHR se ativo).
   * @param {string} id
   */
  function dismiss(id) {
    var item = items[id];
    if (!item) return;

    if (item._staleTimer) clearTimeout(item._staleTimer);
    _unpersistItem(id);

    // Aborta XHR se ainda em Fase 1
    if (item.xhr && !item.done && !item.error) {
      try {
        item.xhr.abort();
      } catch (e) {}
    }

    // Anima saída
    var e = _els(id);
    if (e.item) {
      var h = e.item.offsetHeight;
      e.item.style.maxHeight = h + "px";
      // Force reflow then apply removing class
      e.item.offsetHeight; // eslint-disable-line no-unused-expressions
      e.item.classList.add("removing");
      setTimeout(function () {
        try {
          e.item.remove();
        } catch (_) {}
      }, 380);
    }

    if (!item.done && !item.error) {
      activeCount = Math.max(0, activeCount - 1);
    }
    delete items[id];
    _updateCount();
    setTimeout(_hideIfEmpty, 420);
  }

  // ── beforeunload: avisa se Fase 1 estiver ativa ────────────
  // (Fase 2 é segura para navegar — worker continua no servidor)

  window.addEventListener("beforeunload", function (e) {
    var hasActivePhase1 = Object.keys(items).some(function (id) {
      var item = items[id];
      return item && !item.done && !item.error && (item.phase === 0 || item.phase === 1);
    });
    if (hasActivePhase1) {
      e.preventDefault();
      e.returnValue = "";
    }
  });

  // ── Rehydration: restaura uploads Fase 2 ao carregar nova página ──

  function _rehydrate() {
    var state = _loadState();
    if (!state.length) return;
    state.forEach(function (s) {
      if (!s.id || !s.name) return;
      // Pula se já foi notificado como concluído
      try { if (localStorage.getItem("improov_upload_notified_" + s.id)) { _unpersistItem(s.id); return; } } catch (e) {}
      // Cria o item na barra com estado de espera
      add(s.id, s.name, s.totalBytes || 0, null);
      var e2 = _els(s.id);
      if (e2.bar) e2.bar.classList.add("indeterminate");
      if (e2.phase) e2.phase.textContent = "Verificando status do upload...";
      if (e2.item) e2.item.classList.add("is-queued");
      if (items[s.id]) {
        items[s.id].phase = 2; // já está na fase 2
        items[s.id]._isRehydrated = true;
        // Remove se nenhuma atualização via WebSocket chegar em 15s (estado obsoleto)
        items[s.id]._staleTimer = setTimeout(function () {
          var item = items[s.id];
          if (item && item._isRehydrated && !item.done && !item.error) dismiss(s.id);
        }, STALE_REHYDRATE_MS);
      }
    });
  }

  // ── WebSocket event listener ────────────────────────────────
  // Escuta eventos despachados pelo upload-ws.js (improov:uploadProgress)

  window.addEventListener("improov:uploadProgress", function (ev) {
    try {
      var payload = ev && ev.detail;
      if (!payload || !payload.id) return;
      var id = String(payload.id);

      var status = payload.status ? payload.status.toLowerCase() : "";
      var isDone = status === "done" || payload.progress >= 100;

      // Auto-cria item se não estiver rastreado nesta página (usuário navegou durante Fase 2)
      if (!items[id]) {
        if (isDone) { _unpersistItem(id); return; } // já concluiu, sem necessidade de mostrar
        var stored = _loadState().find(function (s) { return s.id === id; });
        var name = (stored && stored.name) || id;
        var totalBytes = (stored && stored.totalBytes) || 0;
        add(id, name, totalBytes, null);
        if (items[id]) { items[id].phase = 2; items[id]._isRehydrated = true; }
      }

      // Cancela timer de obsolescência — recebemos atualização real
      if (items[id] && items[id]._staleTimer) {
        clearTimeout(items[id]._staleTimer);
        items[id]._staleTimer = null;
        items[id]._isRehydrated = false;
      }

      var isQueued = status === "queued" || status === "enqueued";
      var isProcessing = status === "processing";

      if (isDone) { complete(id); return; }
      if (isQueued) { phase2Progress(id, 0, "Na fila — aguardando transferência..."); return; }
      if (isProcessing || payload.progress > 0) {
        var pct = typeof payload.progress === "number" ? payload.progress : 0;
        var msg = payload.message ? "Fase 2: " + payload.message : null;
        phase2Progress(id, pct, msg);
        return;
      }
      phase2Progress(id, payload.progress || 0, payload.message || null);
    } catch (_) {}
  });

  // ── Init ────────────────────────────────────────────────────

  function _init() {
    _bar();
    _rehydrate();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", _init);
  } else {
    _init();
  }

  // ── Expose ──────────────────────────────────────────────────
  window.UploadBadge = {
    add: add,
    phase1Progress: phase1Progress,
    phase2Progress: phase2Progress,
    complete: complete,
    error: error,
    dismiss: dismiss,
  };
})();
