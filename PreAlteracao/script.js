// PreAlteracao/script.js
(function () {
  "use strict";

  const BASE = (() => {
    const path = window.location.pathname;
    const idx = path.indexOf("/PreAlteracao");
    return idx !== -1
      ? path.substring(0, idx + 1) + "PreAlteracao/"
      : "/ImproovWeb/PreAlteracao/";
  })();

  const FLOW_REVIEW_BASE = BASE.replace("/PreAlteracao/", "/FlowReview/");

  const STATUS_META = {
    EM_TRIAGEM: {
      col: "triagem",
      label: "Em Triagem",
      empty: "Nenhum lote aguardando triagem.",
    },
    AGUARDANDO_CLIENTE: {
      col: "aguardando",
      label: "Aguardando Cliente",
      empty: "Nenhum lote aguardando retorno do cliente.",
    },
    PRONTO_PLANEJAMENTO: {
      col: "planejamento",
      label: "Para Planejamento",
      empty: "Nenhum lote pronto para planejamento.",
    },
  };

  const NIVEL_LABELS = {
    1: "Muito baixa (ajustes superficiais)",
    2: "Baixa (ajustes de acabamento)",
    3: "Media (revisao de composicao)",
    4: "Alta (revisao estrutural)",
    5: "Muito alta (alteracao de projeto)",
  };

  const filtroObra = document.getElementById("filtroObra");
  const badgeCount = document.getElementById("badgeCount");
  const emptyState = document.getElementById("emptyState");
  const columnsLayout = document.getElementById("columnsLayout");
  const columns = {
    triagem: document.getElementById("colTriagem"),
    aguardando: document.getElementById("colAguardando"),
    planejamento: document.getElementById("colPlanejamento"),
  };
  const counts = {
    triagem: document.getElementById("countTriagem"),
    aguardando: document.getElementById("countAguardando"),
    planejamento: document.getElementById("countPlanejamento"),
  };
  const paModal = document.getElementById("paModal");
  const paModalTitle = document.getElementById("paModalTitle");
  const paModalBadges = document.getElementById("paModalBadges");
  const paModalBody = document.getElementById("paModalBody");
  const paModalClose = document.getElementById("paModalClose");

  let lotesCache = [];
  let loteAberto = null;

  async function carregarLotes() {
    emptyState.style.display = "none";
    columnsLayout.style.display = "grid";
    Object.values(columns).forEach((col) => {
      if (col) col.innerHTML = `<div class="col-empty"><i class="fa-solid fa-spinner fa-spin"></i></div>`;
    });

    try {
      const res = await fetch(BASE + "get_pre_alt_entregas.php");
      const json = await res.json();
      if (!json.success) throw new Error(json.error || "Erro ao carregar lotes");

      lotesCache = json.lotes || [];
      populateObraFilter(json.obras || []);
      renderLotes();
    } catch (err) {
      columns.triagem.innerHTML = `<p style="color:#dc2626;font-size:13px;padding:8px">Erro: ${escHtml(err.message)}</p>`;
      columns.aguardando.innerHTML = "";
      columns.planejamento.innerHTML = "";
    }
  }

  function populateObraFilter(obras) {
    const selected = filtroObra.value;
    while (filtroObra.options.length > 1) filtroObra.remove(1);
    obras.forEach((o) => {
      const opt = document.createElement("option");
      opt.value = o.idobra;
      opt.textContent = o.nomenclatura;
      filtroObra.appendChild(opt);
    });
    if (selected) filtroObra.value = selected;
  }

  function renderLotes() {
    const obraFiltro = parseInt(filtroObra.value, 10) || 0;
    const lista =
      obraFiltro > 0
        ? lotesCache.filter((lote) => lote.obra_id === obraFiltro)
        : lotesCache;

    const totalItens = lista.reduce((sum, lote) => sum + lote.total_itens, 0);
    badgeCount.textContent = `${totalItens} imagens`;

    const grouped = {
      triagem: lista.filter((l) => l.lote_status === "EM_TRIAGEM"),
      aguardando: lista.filter((l) => l.lote_status === "AGUARDANDO_CLIENTE"),
      planejamento: lista.filter((l) => l.lote_status === "PRONTO_PLANEJAMENTO"),
    };

    Object.entries(grouped).forEach(([key, lotes]) => {
      counts[key].textContent = lotes.length;
      columns[key].innerHTML = "";

      if (lotes.length === 0) {
        const statusKey = Object.keys(STATUS_META).find(
          (s) => STATUS_META[s].col === key,
        );
        columns[key].innerHTML = `<div class="col-empty"><span>${STATUS_META[statusKey].empty}</span></div>`;
        return;
      }

      lotes.forEach((lote) => columns[key].appendChild(criarCardLote(lote)));
    });

    if (lista.length === 0) {
      columnsLayout.style.display = "none";
      emptyState.style.display = "flex";
    } else {
      columnsLayout.style.display = "grid";
      emptyState.style.display = "none";
    }
  }

  function criarCardLote(lote) {
    const card = document.createElement("div");
    card.className = `card-prealt card-prealt--${STATUS_META[lote.lote_status]?.col || "triagem"}`;
    card.dataset.loteId = lote.lote_id;

    const nivelHtml = [1, 2, 3, 4, 5]
      .map((nivel) => {
        const count = lote[`nivel_${nivel}`] || 0;
        return count > 0 ? `<span>N${nivel}: ${count}</span>` : "";
      })
      .join("");

    card.innerHTML = `
      <div class="card-prealt-header">
        <h4 class="card-prealt-title">${escHtml(lote.nomenclatura)}</h4>
        <span class="card-prealt-badge">${lote.total_itens}</span>
      </div>
      <div class="card-prealt-meta">
        ${escHtml(lote.nome_etapa)} &nbsp;·&nbsp; Cliente: ${formatarData(lote.data_finalizacao_cliente)}
      </div>
      <div class="card-prealt-stats">
        <span><i class="fa-solid fa-layer-group"></i> ${lote.batch_count} lote(s)</span>
        <span><i class="fa-solid fa-pen-ruler"></i> ${lote.count_alteracao} alteração</span>
        <span><i class="fa-solid fa-check"></i> ${lote.count_sem_alteracao} sem alteração</span>
        ${lote.count_aguardando ? `<span class="stat-analise"><i class="fa-solid fa-clock-rotate-left"></i> ${lote.count_aguardando} retorno</span>` : ""}
      </div>
      <div class="nivel-mini">${nivelHtml || "<span>Sem níveis fechados</span>"}</div>
    `;

    card.addEventListener("click", () => abrirModal(lote.lote_id));
    return card;
  }

  async function abrirModal(loteId) {
    paModal.classList.add("is-open");
    paModalTitle.textContent = "Carregando...";
    paModalBadges.innerHTML = "";
    paModalBody.innerHTML = `<div class="modal-loading"><i class="fa-solid fa-spinner fa-spin"></i> Carregando lote...</div>`;

    try {
      const res = await fetch(BASE + `get_pre_alt_lote.php?lote_id=${loteId}`);
      const json = await res.json();
      if (!json.success) throw new Error(json.error || "Erro ao carregar lote");

      loteAberto = json.lote;
      renderModal(json.lote, json.itens || []);
    } catch (err) {
      paModalBody.innerHTML = `<p style="color:#dc2626;font-size:13px;padding:12px">Erro: ${escHtml(err.message)}</p>`;
    }
  }

  function renderModal(lote, itens) {
    const resumo = buildResumo(itens);
    paModalTitle.textContent = lote.nomenclatura;
    paModalBadges.innerHTML = `
      <span class="modal-meta-badge">${escHtml(lote.nome_etapa)}</span>
      <span class="modal-meta-badge">Cliente: ${formatarData(lote.data_finalizacao_cliente)}</span>
      <span class="modal-meta-badge">${STATUS_META[lote.lote_status]?.label || lote.lote_status}</span>
    `;

    paModalBody.innerHTML = `
      <div class="prealt-summary">
        ${summaryTile(resumo.total, "Imagens")}
        ${summaryTile(resumo.alteracao, "Com alteração")}
        ${summaryTile(resumo.semAlteracao, "Sem alteração")}
        ${summaryTile(resumo.aguardando, "Aguardando cliente")}
        ${[1, 2, 3, 4, 5].map((n) => summaryTile(resumo.niveis[n], `Nível ${n}`)).join("")}
      </div>
      <div class="modal-items-list"></div>
    `;

    const list = paModalBody.querySelector(".modal-items-list");
    itens.forEach((item) => list.appendChild(criarItem(item)));
  }

  function criarItem(item) {
    const div = document.createElement("div");
    div.className = "modal-imagem-item is-expanded";
    div.dataset.itemId = item.item_id;

    const reviewUrl = FLOW_REVIEW_BASE + `?imagem_id=${item.imagem_id}`;
    const resultado = item.resultado || "ALTERACAO";

    div.innerHTML = `
      <div class="modal-item-header">
        <span class="modal-item-nome" title="${escHtml(item.nome)}">${escHtml(item.nome)}</span>
        <span class="badge-substatus badge-pre-alt">${escHtml(resultadoLabel(resultado))}</span>
        ${item.nivel_complexidade ? `<span class="badge-substatus badge-ready">N${item.nivel_complexidade}</span>` : ""}
        <div class="modal-item-actions">
          <a href="${reviewUrl}" target="_blank" class="btn-review-studio">
            <i class="fa-solid fa-comments"></i> Review Studio
          </a>
        </div>
      </div>
      <div class="modal-item-body">
        ${criarForm(item)}
      </div>
    `;

    wireItem(div, item);
    return div;
  }

  function criarForm(item) {
    const resultado = item.resultado || "ALTERACAO";
    const nivel = item.nivel_complexidade || "";
    const tipo = escHtml(item.tipo_alteracao || "");
    const acao = escHtml(item.acao || "");
    const nr = item.necessita_retorno == 1;

    return `
      <div class="form-row">
        <span class="form-label">Resultado</span>
        <select class="resultado-select">
          <option value="ALTERACAO" ${resultado === "ALTERACAO" ? "selected" : ""}>Alteração</option>
          <option value="SEM_ALTERACAO" ${resultado === "SEM_ALTERACAO" ? "selected" : ""}>Sem alteração / aprovada</option>
          <option value="AGUARDANDO_CLIENTE" ${resultado === "AGUARDANDO_CLIENTE" ? "selected" : ""}>Aguardando cliente</option>
        </select>
      </div>
      <div class="form-row nivel-row">
        <span class="form-label">Complexidade</span>
        <div class="complexidade-options">
          ${[1, 2, 3, 4, 5]
            .map(
              (n) =>
                `<button class="complexidade-btn ${Number(nivel) === n ? `active-n${n}` : ""}" data-valor="${n}" title="${escHtml(NIVEL_LABELS[n])}">${n}</button>`,
            )
            .join("")}
        </div>
      </div>
      <div class="form-row tipo-row">
        <span class="form-label">Tipo</span>
        <input class="form-input tipo-input" type="text" placeholder="Ex.: acabamento, composição, projeto, troca de ângulo" value="${tipo}">
      </div>
      <label class="necessita-retorno-row">
        <input type="checkbox" class="necessita-retorno-check" ${nr ? "checked" : ""}>
        <span class="necessita-retorno-label"><i class="fa-solid fa-clock-rotate-left"></i> Necessita retorno do cliente</span>
      </label>
      <div class="form-row">
        <span class="form-label">Ação / Observações</span>
        <textarea class="form-textarea" placeholder="Resumo objetivo para orientar o planejamento e a execução...">${acao}</textarea>
      </div>
      <div class="form-actions">
        <button class="btn-salvar"><i class="fa-solid fa-floppy-disk"></i> Salvar triagem</button>
      </div>
    `;
  }

  function wireItem(container, item) {
    const resultadoSelect = container.querySelector(".resultado-select");
    const nivelRow = container.querySelector(".nivel-row");
    const tipoRow = container.querySelector(".tipo-row");
    const retornoCheck = container.querySelector(".necessita-retorno-check");
    const btns = container.querySelectorAll(".complexidade-btn");

    const syncVisibility = () => {
      const resultado = resultadoSelect.value;
      const isAlteracao = resultado === "ALTERACAO";
      nivelRow.style.display = isAlteracao ? "" : "none";
      tipoRow.style.display = isAlteracao ? "" : "none";
      if (resultado === "AGUARDANDO_CLIENTE") retornoCheck.checked = true;
    };

    btns.forEach((btn) => {
      btn.addEventListener("click", () => {
        btns.forEach((b) => {
          b.className = "complexidade-btn";
        });
        btn.classList.add(`active-n${btn.dataset.valor}`);
        container.dataset.nivel = btn.dataset.valor;
      });
    });

    if (item.nivel_complexidade) {
      container.dataset.nivel = item.nivel_complexidade;
    }

    resultadoSelect.addEventListener("change", syncVisibility);
    syncVisibility();

    container
      .querySelector(".btn-salvar")
      .addEventListener("click", () => salvarItem(container, item));
  }

  async function salvarItem(container, item) {
    const resultado = container.querySelector(".resultado-select").value;
    const nivel = parseInt(container.dataset.nivel || "0", 10) || null;

    if (resultado === "ALTERACAO" && !nivel) {
      toast("Selecione o nível de complexidade.", "#d97706");
      return;
    }

    const payload = {
      item_id: item.item_id,
      resultado,
      nivel_complexidade: resultado === "ALTERACAO" ? nivel : null,
      tipo_alteracao: container.querySelector(".tipo-input")?.value.trim() || "",
      acao: container.querySelector(".form-textarea")?.value.trim() || "",
      necessita_retorno: container.querySelector(".necessita-retorno-check")
        ?.checked
        ? 1
        : 0,
    };

    const btn = container.querySelector(".btn-salvar");
    btn.disabled = true;
    btn.textContent = "Salvando...";

    try {
      const res = await fetch(BASE + "save_pre_analise.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error || "Erro ao salvar");

      toast(
        json.ready_for_planning
          ? "Lote pronto para planejamento."
          : "Triagem salva.",
        "#059669",
      );
      await abrirModal(json.lote_id || loteAberto?.lote_id);
      await carregarLotes();
    } catch (err) {
      toast("Erro: " + err.message, "#dc2626");
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar triagem';
    }
  }

  function buildResumo(itens) {
    const resumo = {
      total: itens.length,
      alteracao: 0,
      semAlteracao: 0,
      aguardando: 0,
      niveis: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 },
    };

    itens.forEach((item) => {
      if (item.resultado === "SEM_ALTERACAO") resumo.semAlteracao += 1;
      if (item.resultado === "AGUARDANDO_CLIENTE" || item.necessita_retorno == 1)
        resumo.aguardando += 1;
      if (item.resultado === "ALTERACAO") resumo.alteracao += 1;
      if (item.nivel_complexidade) {
        resumo.niveis[item.nivel_complexidade] += 1;
      }
    });

    return resumo;
  }

  function summaryTile(value, label) {
    return `<div class="summary-tile"><strong>${value || 0}</strong><span>${escHtml(label)}</span></div>`;
  }

  function resultadoLabel(resultado) {
    if (resultado === "SEM_ALTERACAO") return "Sem alteração";
    if (resultado === "AGUARDANDO_CLIENTE") return "Aguardando cliente";
    return "Alteração";
  }

  function escHtml(str) {
    return String(str ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function formatarData(str) {
    if (!str) return "";
    const [y, m, d] = String(str).split("-");
    return y && m && d ? `${d}/${m}/${y}` : str;
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

  paModalClose.addEventListener("click", () =>
    paModal.classList.remove("is-open"),
  );
  paModal.addEventListener("click", (e) => {
    if (e.target === paModal) paModal.classList.remove("is-open");
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") paModal.classList.remove("is-open");
  });
  filtroObra.addEventListener("change", renderLotes);

  carregarLotes();
})();
