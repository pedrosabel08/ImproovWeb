// PreAlteracao/script.js
(function () {
  "use strict";

  const BASE = (function () {
    const path = window.location.pathname;
    const idx = path.indexOf("/PreAlteracao");
    return idx !== -1
      ? path.substring(0, idx + 1) + "PreAlteracao/"
      : "/ImproovWeb/PreAlteracao/";
  })();

  const FLOW_REVIEW_BASE = BASE.replace("/PreAlteracao/", "/FlowReview/");

  // ====== DOM refs ======
  const filtroObra = document.getElementById("filtroObra");
  const badgeCount = document.getElementById("badgeCount");
  const emptyState = document.getElementById("emptyState");
  const columnsLayout = document.getElementById("columnsLayout");
  const colPreAnalise = document.getElementById("colPreAnalise");
  const colPlanejamento = document.getElementById("colPlanejamento");
  const countPreAnalise = document.getElementById("countPreAnalise");
  const countPlanejamento = document.getElementById("countPlanejamento");
  const paModal = document.getElementById("paModal");
  const paModalTitle = document.getElementById("paModalTitle");
  const paModalBadges = document.getElementById("paModalBadges");
  const paModalBody = document.getElementById("paModalBody");
  const paModalClose = document.getElementById("paModalClose");

  let entregasCache = [];

  // ====== Carregar entregas ======
  async function carregarEntregas() {
    emptyState.style.display = "none";
    columnsLayout.style.display = "grid";
    colPreAnalise.innerHTML = `<div class="col-empty"><i class="fa-solid fa-spinner fa-spin"></i></div>`;
    colPlanejamento.innerHTML = "";

    try {
      const res = await fetch(BASE + "get_pre_alt_entregas.php");
      const json = await res.json();
      if (!json.success) throw new Error(json.error || "Erro");

      entregasCache = json.entregas || [];

      // Popula filtro com apenas as obras relevantes
      populateObraFilter(json.obras || []);
      renderEntregas();
    } catch (err) {
      colPreAnalise.innerHTML = `<p style="color:#dc2626;font-size:13px;padding:8px">Erro: ${err.message}</p>`;
    }
  }

  function populateObraFilter(obras) {
    while (filtroObra.options.length > 1) filtroObra.remove(1);
    obras.forEach((o) => {
      const opt = document.createElement("option");
      opt.value = o.idobra;
      opt.textContent = o.nomenclatura;
      filtroObra.appendChild(opt);
    });
  }

  // ====== Renderizar cards de entrega ======
  function renderEntregas() {
    const obraFiltro = parseInt(filtroObra.value, 10) || 0;
    const lista =
      obraFiltro > 0
        ? entregasCache.filter((e) => e.obra_id === obraFiltro)
        : entregasCache;

    const analise = lista.filter((e) => e.count_analise > 0);
    const planning = lista.filter((e) => e.count_planning > 0);
    const totalImgs = lista.reduce(
      (s, e) => s + e.count_analise + e.count_planning,
      0,
    );

    badgeCount.textContent = totalImgs + " imagens";
    countPreAnalise.textContent = analise.length;
    countPlanejamento.textContent = planning.length;

    colPreAnalise.innerHTML = "";
    colPlanejamento.innerHTML = "";

    if (lista.length === 0) {
      columnsLayout.style.display = "none";
      emptyState.style.display = "flex";
      return;
    }

    columnsLayout.style.display = "grid";
    emptyState.style.display = "none";

    if (analise.length === 0) {
      colPreAnalise.innerHTML = `<div class="col-empty"><i class="fa-solid fa-check-circle" style="color:#10b981"></i><span>Nenhuma imagem aguardando análise.</span></div>`;
    } else {
      analise.forEach((e) =>
        colPreAnalise.appendChild(criarCard(e, "analise")),
      );
    }

    if (planning.length === 0) {
      colPlanejamento.innerHTML = `<div class="col-empty"><i class="fa-solid fa-calendar-xmark" style="opacity:.4"></i><span>Nenhuma imagem pronta para planejamento ainda.</span></div>`;
    } else {
      planning.forEach((e) =>
        colPlanejamento.appendChild(criarCard(e, "planning")),
      );
    }
  }

  // ====== Criar card de entrega ======
  function criarCard(entrega, coluna) {
    const card = document.createElement("div");
    card.className = `card-prealt card-prealt--${coluna}`;
    card.dataset.obraId = entrega.obra_id;
    card.dataset.entregaId = entrega.entrega_id;

    const countMain =
      coluna === "analise" ? entrega.count_analise : entrega.count_planning;
    const labelEntrega =
      entrega.entrega_id > 0
        ? `Entrega #${entrega.entrega_id}` +
          (entrega.data_prevista
            ? ` &nbsp;·&nbsp; Prazo: ${formatarData(entrega.data_prevista)}`
            : "")
        : "Imagens avulsas";

    const statAnalise =
      entrega.count_analise > 0
        ? `<span class="stat-analise"><i class="fa-solid fa-magnifying-glass"></i> ${entrega.count_analise} em análise</span>`
        : "";
    const statPlanning =
      entrega.count_planning > 0
        ? `<span class="stat-planning"><i class="fa-solid fa-calendar-check"></i> ${entrega.count_planning} para planejamento</span>`
        : "";

    card.innerHTML = `
      <div class="card-prealt-header">
        <h4 class="card-prealt-title">${escHtml(entrega.nomenclatura)}</h4>
        <span class="card-prealt-badge">${countMain}</span>
      </div>
      <div class="card-prealt-meta">${labelEntrega}</div>
      <div class="card-prealt-stats">${statAnalise}${statPlanning}</div>
    `;

    card.addEventListener("click", () => abrirModal(entrega));
    return card;
  }

  // ====== Modal ======
  async function abrirModal(entrega) {
    const labelEntrega =
      entrega.entrega_id > 0
        ? `Entrega #${entrega.entrega_id}`
        : "Imagens avulsas";

    paModalTitle.textContent = entrega.nomenclatura;
    paModalBadges.innerHTML = `
      <span class="modal-meta-badge">${labelEntrega}</span>
      ${entrega.count_analise > 0 ? `<span class="modal-meta-badge modal-meta-badge--analise"><i class="fa-solid fa-magnifying-glass"></i> ${entrega.count_analise} em análise</span>` : ""}
      ${entrega.count_planning > 0 ? `<span class="modal-meta-badge modal-meta-badge--planning"><i class="fa-solid fa-calendar-check"></i> ${entrega.count_planning} para planejamento</span>` : ""}
    `;
    paModalBody.innerHTML = `<div class="modal-loading"><i class="fa-solid fa-spinner fa-spin"></i> Carregando imagens...</div>`;
    paModal.classList.add("is-open");

    try {
      const url =
        BASE +
        `get_imagens_rvw_done.php?obra_id=${entrega.obra_id}&entrega_id=${entrega.entrega_id}`;
      const res = await fetch(url);
      const json = await res.json();
      if (!json.success) throw new Error(json.error || "Erro");
      renderModalImagens(json.imagens || [], entrega);
    } catch (err) {
      paModalBody.innerHTML = `<p style="color:#dc2626;font-size:13px;padding:12px">Erro: ${err.message}</p>`;
    }
  }

  function renderModalImagens(imagens, entrega) {
    paModalBody.innerHTML = "";
    if (imagens.length === 0) {
      paModalBody.innerHTML = `<div class="modal-empty">Nenhuma imagem encontrada.</div>`;
      return;
    }
    imagens.forEach((img) => {
      const isPlanning = [12, "12"].includes(img.substatus_id);
      paModalBody.appendChild(
        isPlanning
          ? criarItemPlanejamento(img)
          : criarItemAnalise(img, entrega),
      );
    });
  }

  // ====== Item modal: análise (substatus 10/11) ======
  function criarItemAnalise(img, entrega) {
    const div = document.createElement("div");
    div.className = "modal-imagem-item";
    div.dataset.id = img.imagem_id;

    const substatusId = parseInt(img.substatus_id, 10);
    const badgeClass = substatusId === 10 ? "badge-rvw-done" : "badge-pre-alt";
    const badgeLabel = substatusId === 10 ? "RVW_DONE" : "PRE_ALT";
    const retorno =
      img.necessita_retorno == 1
        ? `<span class="badge-retorno"><i class="fa-solid fa-clock-rotate-left"></i> Retorno</span>`
        : "";
    const reviewUrl = FLOW_REVIEW_BASE + `?imagem_id=${img.imagem_id}`;

    div.innerHTML = `
      <div class="modal-item-header">
        <span class="modal-item-nome" title="${escHtml(img.nome)}">${escHtml(img.nome)}</span>
        <span class="badge-substatus ${badgeClass}">${badgeLabel}</span>
        ${retorno}
        <div class="modal-item-actions">
          <a href="${reviewUrl}" target="_blank" class="btn-review-studio">
            <i class="fa-solid fa-comments"></i> Review Studio
          </a>
          <button class="btn-expand" title="Abrir análise">
            <i class="fa-solid fa-chevron-down"></i>
          </button>
        </div>
      </div>
      <div class="modal-item-body">
        ${criarFormAnalise(img)}
      </div>
    `;

    const btnExp = div.querySelector(".btn-expand");
    div.querySelector(".modal-item-header").addEventListener("click", (e) => {
      if (e.target.closest(".btn-review-studio")) return;
      const expanded = div.classList.toggle("is-expanded");
      btnExp.innerHTML = `<i class="fa-solid fa-chevron-${expanded ? "up" : "down"}"></i>`;
    });

    wireComplexidade(div, img);
    const btnSalvar = div.querySelector(".btn-salvar");
    if (btnSalvar)
      btnSalvar.addEventListener("click", () =>
        salvarAnalise(div, img, entrega),
      );

    return div;
  }

  // ====== Item modal: planejamento (substatus 12) ======
  function criarItemPlanejamento(img) {
    const div = document.createElement("div");
    div.className = "modal-imagem-item modal-imagem-item--planning";
    div.dataset.id = img.imagem_id;

    const complexidade = img.complexidade || "";
    const retorno =
      img.necessita_retorno == 1
        ? `<span class="badge-retorno"><i class="fa-solid fa-clock-rotate-left"></i> Retorno</span>`
        : "";
    const reviewUrl = FLOW_REVIEW_BASE + `?imagem_id=${img.imagem_id}`;
    const acaoText = img.acao
      ? escHtml(
          img.acao.length > 120 ? img.acao.substring(0, 120) + "…" : img.acao,
        )
      : "";

    div.innerHTML = `
      <div class="modal-item-header">
        <span class="modal-item-nome" title="${escHtml(img.nome)}">${escHtml(img.nome)}</span>
        <span class="badge-substatus badge-ready">READY</span>
        ${complexidade ? `<span class="badge-substatus badge-pre-alt" style="font-size:10px">${complexidade}</span>` : ""}
        ${retorno}
        <div class="modal-item-actions">
          <a href="${reviewUrl}" target="_blank" class="btn-review-studio">
            <i class="fa-solid fa-comments"></i> Review Studio
          </a>
        </div>
      </div>
      ${acaoText ? `<div class="modal-item-summary">${acaoText}</div>` : ""}
    `;
    return div;
  }

  // ====== Formulário de análise ======
  function criarFormAnalise(img) {
    const c = img.complexidade || "";
    const a = escHtml(img.acao || "");
    const nr = img.necessita_retorno == 1;

    const activeS = c === "S" ? "active-s" : "";
    const activeM = c === "M" ? "active-m" : "";
    const activeC = c === "C" ? "active-c" : "";
    const activeTA = c === "TA" ? "active-ta" : "";

    return `
      <div class="form-row">
        <span class="form-label">Complexidade</span>
        <div class="complexidade-options">
          <button class="complexidade-btn ${activeS}"  data-valor="S"  title="Simples">S</button>
          <button class="complexidade-btn ${activeM}"  data-valor="M"  title="Médio">M</button>
          <button class="complexidade-btn ${activeC}"  data-valor="C"  title="Complexo">C</button>
          <button class="complexidade-btn ${activeTA}" data-valor="TA" title="Troca de Ângulo" style="width:auto;padding:0 10px;font-size:11px">Troca Ângulo</button>
        </div>
      </div>
      <label class="necessita-retorno-row">
        <input type="checkbox" class="necessita-retorno-check" ${nr ? "checked" : ""}>
        <span class="necessita-retorno-label"><i class="fa-solid fa-clock-rotate-left"></i> Necessita retorno do cliente</span>
      </label>
      <div class="form-row">
        <span class="form-label">Ação / Observações</span>
        <textarea class="form-textarea" placeholder="Resumo das alterações necessárias, dúvidas ou observações...">${a}</textarea>
      </div>
      <div class="form-actions">
        <button class="btn-salvar"><i class="fa-solid fa-floppy-disk"></i> Salvar análise</button>
      </div>
    `;
  }

  function wireComplexidade(container, img) {
    const btns = container.querySelectorAll(".complexidade-btn");
    btns.forEach((btn) => {
      btn.addEventListener("click", () => {
        btns.forEach((b) => {
          b.className = "complexidade-btn";
          if (b.dataset.valor === "TA")
            b.style.cssText = "width:auto;padding:0 10px;font-size:11px";
        });
        btn.classList.add(`active-${btn.dataset.valor.toLowerCase()}`);
        container.dataset.complexidade = btn.dataset.valor;
      });
    });
    const active = container.querySelector(
      ".complexidade-btn.active-s,.complexidade-btn.active-m,.complexidade-btn.active-c,.complexidade-btn.active-ta",
    );
    if (active) container.dataset.complexidade = active.dataset.valor;
  }

  async function salvarAnalise(container, img, entrega) {
    const complexidade = container.dataset.complexidade;
    if (!complexidade) {
      toast("Selecione a complexidade.", "#d97706");
      return;
    }

    const acao = container.querySelector(".form-textarea")?.value.trim() || "";
    const necessitaRetorno = container.querySelector(".necessita-retorno-check")
      ?.checked
      ? 1
      : 0;
    const btnSalvar = container.querySelector(".btn-salvar");

    if (btnSalvar) {
      btnSalvar.disabled = true;
      btnSalvar.textContent = "Salvando...";
    }

    try {
      const res = await fetch(BASE + "save_pre_analise.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          imagem_id: img.imagem_id,
          entrega_id: img.entrega_id || 0,
          complexidade,
          acao,
          necessita_retorno: necessitaRetorno,
        }),
      });
      const json = await res.json();
      if (json.success) {
        toast(
          json.ready_for_planning
            ? "🎉 READY_FOR_PLANNING! Pedro foi notificado."
            : "Análise salva!",
          "#059669",
          json.ready_for_planning ? 5000 : 2500,
        );
        // Recarrega modal e cards
        setTimeout(() => abrirModal(entrega), 600);
        setTimeout(() => carregarEntregas(), 900);
      } else {
        toast("Erro: " + (json.error || "desconhecido"), "#dc2626");
      }
    } catch (err) {
      console.error("Erro ao salvar análise:", err);
      toast("Falha ao salvar (ver console).", "#dc2626");
    } finally {
      if (btnSalvar) {
        btnSalvar.disabled = false;
        btnSalvar.innerHTML =
          '<i class="fa-solid fa-floppy-disk"></i> Salvar análise';
      }
    }
  }

  // ====== Utils ======
  function escHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function formatarData(str) {
    if (!str) return "";
    const [y, m, d] = str.split("-");
    return `${d}/${m}/${y}`;
  }

  function toast(text, background, duration = 2500) {
    Toastify({
      text,
      duration,
      gravity: "top",
      position: "right",
      style: { background },
    }).showToast();
  }

  // ====== Fechar modal ======
  paModalClose.addEventListener("click", () =>
    paModal.classList.remove("is-open"),
  );
  paModal.addEventListener("click", (e) => {
    if (e.target === paModal) paModal.classList.remove("is-open");
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") paModal.classList.remove("is-open");
  });

  // ====== Filtro de obra (client-side) ======
  filtroObra.addEventListener("change", () => renderEntregas());

  // ====== Init ======
  carregarEntregas();
})();
