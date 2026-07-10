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

  const STATUS_META = {
    EM_TRIAGEM: {
      col: "triagem",
      label: "Em triagem",
      emptyTitle: "Nenhum lote em triagem",
      emptyText:
        "Quando um retorno do cliente entrar para análise, ele aparece aqui.",
      icon: "fa-magnifying-glass-chart",
    },
    AGUARDANDO_CLIENTE: {
      col: "aguardando",
      label: "Aguardando cliente",
      emptyTitle: "Nada aguardando cliente",
      emptyText:
        "Lotes com dúvidas ou retorno pendente serão listados nesta coluna.",
      icon: "fa-clock-rotate-left",
    },
    PRONTO_PLANEJAMENTO: {
      col: "planejamento",
      label: "Para planejamento",
      emptyTitle: "Nenhum lote para planejamento",
      emptyText:
        "Triagens concluídas e liberadas para o próximo passo aparecem aqui.",
      icon: "fa-calendar-check",
    },
  };

  const PRIORIDADE_META = {
    BAIXA: { label: "Baixa", cls: "priority-low" },
    NORMAL: { label: "Normal", cls: "priority-normal" },
    ALTA: { label: "Alta", cls: "priority-high" },
    CRITICA: { label: "Crítica", cls: "priority-critical" },
  };

  const NIVEL_LABELS = {
    1: "Muito baixa",
    2: "Baixa",
    3: "Média",
    4: "Alta",
    5: "Muito alta",
  };

  const refs = {
    kpiGrid: document.getElementById("kpiGrid"),
    btnAtualizar: document.getElementById("btnAtualizar"),
    btnBatchMode: document.getElementById("btnBatchMode"),
    // btnNovoLote: document.getElementById("btnNovoLote"),
    btnMaisFiltros: document.getElementById("btnMaisFiltros"),
    filtroBusca: document.getElementById("filtroBusca"),
    filtroObra: document.getElementById("filtroObra"),
    filtroCliente: document.getElementById("filtroCliente"),
    filtroStatus: document.getElementById("filtroStatus"),
    filtroPrioridade: document.getElementById("filtroPrioridade"),
    filtroResponsavel: document.getElementById("filtroResponsavel"),
    filtroPrazo: document.getElementById("filtroPrazo"),
    filtroData: document.getElementById("filtroData"),
    batchActionBar: document.getElementById("batchActionBar"),
    selectedCount: document.getElementById("selectedCount"),
    btnLimparSelecao: document.getElementById("btnLimparSelecao"),
    paModal: document.getElementById("paModal"),
    paModalTitle: document.getElementById("paModalTitle"),
    paModalBadges: document.getElementById("paModalBadges"),
    paModalActions: document.getElementById("paModalActions"),
    paModalBody: document.getElementById("paModalBody"),
    paModalClose: document.getElementById("paModalClose"),
    paFooterClose: document.getElementById("paFooterClose"),
    paPendingCount: document.getElementById("paPendingCount"),
    btnSalvarAlteracoes: document.getElementById("btnSalvarAlteracoes"),
    batchModal: document.getElementById("batchModal"),
    batchForm: document.getElementById("batchForm"),
    batchModalTitle: document.getElementById("batchModalTitle"),
    batchModalBody: document.getElementById("batchModalBody"),
    batchModalClose: document.getElementById("batchModalClose"),
    batchCancel: document.getElementById("batchCancel"),
    conclusaoModal: document.getElementById("conclusaoModal"),
    conclusaoForm: document.getElementById("conclusaoForm"),
    conclusaoTitle: document.getElementById("conclusaoTitle"),
    conclusaoBody: document.getElementById("conclusaoBody"),
    conclusaoModalClose: document.getElementById("conclusaoModalClose"),
    conclusaoCancel: document.getElementById("conclusaoCancel"),
    conclusaoSubmit: document.getElementById("conclusaoSubmit"),
    conclusaoFooterInfo: document.getElementById("conclusaoFooterInfo"),
  };

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
  const imageCounts = {
    triagem: document.getElementById("imagesTriagem"),
    aguardando: document.getElementById("imagesAguardando"),
    planejamento: document.getElementById("imagesPlanejamento"),
  };

  let state = {
    lotes: [],
    obras: [],
    clientes: [],
    responsaveis: [],
    selected: new Set(),
    currentBatchAction: null,
    currentBatchLoteIds: null,
    loteAberto: null,
    itensAbertos: [],
    interacoesCliente: [],
    clientItemSelection: new Set(),
    conclusaoResumo: null,
    restoringDraft: false,
  };

  const FLOWDRIVE_BASE = BASE.replace(/PreAlteracao\/$/, "FlowDrive/");
  const TRIAGEM_CATEGORIAS = [
    [1, "Arquitetônico"],
    [2, "Referências"],
    [3, "Paisagismo"],
    [4, "Luminotécnico"],
    [5, "Estrutural"],
    [6, "Alterações"],
    [7, "Ângulo definido"],
  ];
  const TRIAGEM_TIPOS_ARQUIVO = ["DWG", "PDF", "SKP", "IMG", "IFC", "Outros"];

  async function carregarLotes() {
    renderLoading();
    try {
      const res = await fetch(BASE + "get_pre_alt_entregas.php");
      const json = await res.json();
      if (!json.success)
        throw new Error(json.error || "Erro ao carregar lotes");

      state.lotes = json.lotes || [];
      state.obras = json.obras || [];
      state.clientes = json.clientes || [];
      state.responsaveis = json.responsaveis || [];
      populateFilters();
      renderKpis(json.kpis || {});
      renderLotes();
    } catch (err) {
      Object.values(columns).forEach((col) => {
        col.innerHTML = `<div class="empty-state-card"><i class="fa-solid fa-triangle-exclamation"></i><strong>Erro ao carregar</strong><span>${escHtml(err.message)}</span></div>`;
      });
    }
  }

  function renderLoading() {
    refs.kpiGrid.innerHTML = Array.from({ length: 7 })
      .map(() => '<div class="kpi-card is-loading"></div>')
      .join("");
    Object.values(columns).forEach((col) => {
      col.innerHTML =
        '<div class="column-loading"><i class="fa-solid fa-spinner fa-spin"></i></div>';
    });
  }

  function populateFilters() {
    replaceOptions(
      refs.filtroObra,
      "Todas",
      state.obras,
      "idobra",
      "nomenclatura",
    );
    replaceOptions(
      refs.filtroCliente,
      "Todos",
      state.clientes,
      "idcliente",
      "nome_cliente",
    );
    replaceOptions(
      refs.filtroResponsavel,
      "Todos",
      state.responsaveis,
      "idcolaborador",
      "nome_colaborador",
    );
  }

  function replaceOptions(select, allLabel, rows, valueKey, labelKey) {
    const current = select.value;
    select.innerHTML = `<option value="">${allLabel}</option>`;
    rows.forEach((row) => {
      const option = document.createElement("option");
      option.value = row[valueKey];
      option.textContent = row[labelKey];
      select.appendChild(option);
    });
    select.value = current;
  }

  function getPreAltDraftKey(obraId, cardId) {
    if (!obraId || !cardId) return "";
    return `prealt_draft_${obraId}_${cardId}`;
  }

  function getCurrentPreAltDraftContext() {
    const lote = state.loteAberto || {};
    return {
      obraId: lote.obra_id || lote.idobra || "",
      cardId: lote.lote_id || lote.id || "",
    };
  }

  function readPreAltDraft(obraId, cardId) {
    const draftKey = getPreAltDraftKey(obraId, cardId);
    if (!draftKey) return null;

    let raw = null;
    try {
      raw = localStorage.getItem(draftKey);
    } catch (_) {
      return null;
    }
    if (!raw) return null;

    try {
      const draft = JSON.parse(raw);
      return draft && typeof draft === "object" ? draft : null;
    } catch (_) {
      try {
        localStorage.removeItem(draftKey);
      } catch (__) {}
      return null;
    }
  }

  function hasPreAltDraft(obraId, cardId) {
    return Boolean(readPreAltDraft(obraId, cardId));
  }

  function escapeCssValue(value) {
    if (window.CSS?.escape) return CSS.escape(String(value));
    return String(value).replace(/["\\]/g, "\\$&");
  }

  function atualizarIconeRascunhoPreAlt(obraId, cardId, possuiRascunho) {
    const card = document.querySelector(
      `[data-card-id="${escapeCssValue(cardId)}"][data-obra-id="${escapeCssValue(obraId)}"]`,
    );
    const modalIcon = document.getElementById("paModalDraftIndicator");

    card
      ?.querySelector(".draft-indicator")
      ?.classList.toggle("is-hidden", !possuiRascunho);
    if (modalIcon) modalIcon.classList.toggle("is-hidden", !possuiRascunho);
  }

  function marcarCardsComRascunhoPreAlt() {
    document
      .querySelectorAll("[data-card-id][data-obra-id]")
      .forEach((card) => {
        const obraId = card.dataset.obraId;
        const cardId = card.dataset.cardId;
        const possuiRascunho = hasPreAltDraft(obraId, cardId);
        card
          .querySelector(".draft-indicator")
          ?.classList.toggle("is-hidden", !possuiRascunho);
      });
  }

  function limparRascunhoPreAlt(obraId, cardId) {
    const draftKey = getPreAltDraftKey(obraId, cardId);
    if (!draftKey) return;

    try {
      localStorage.removeItem(draftKey);
    } catch (_) {}
    atualizarIconeRascunhoPreAlt(obraId, cardId, false);
  }

  function salvarRascunhoPreAlt(obraId, cardId) {
    const draftKey = getPreAltDraftKey(obraId, cardId);
    if (!draftKey) return;

    const itens = Array.from(
      refs.paModalBody?.querySelectorAll(".prealt-item") || [],
    );
    const hasChanges = itens.some((item) =>
      item.classList.contains("is-dirty"),
    );

    if (!hasChanges) {
      limparRascunhoPreAlt(obraId, cardId);
      return;
    }

    const draft = {
      obraId,
      cardId,
      updatedAt: new Date().toISOString(),
      itens: {},
    };

    itens.forEach((container) => {
      const imagemId = container.dataset.imagemId || container.dataset.itemId;
      if (!imagemId) return;

      const itemId = Number(container.dataset.itemId);
      const item = state.itensAbertos.find(
        (row) => Number(row.item_id) === itemId,
      ) || { item_id: itemId };
      const payload = getItemFormPayload(container, item);

      draft.itens[imagemId] = {
        imagemId,
        itemId: payload.item_id,
        resultado: payload.resultado,
        tipo: payload.tipo_alteracao,
        qtdComentarios: payload.quantidade_comentarios,
        complexidade: payload.nivel_complexidade
          ? `N${payload.nivel_complexidade}`
          : "",
        retornoCliente: Boolean(payload.necessita_retorno),
        observacao: payload.acao,
      };
    });

    try {
      localStorage.setItem(draftKey, JSON.stringify(draft));
      atualizarIconeRascunhoPreAlt(obraId, cardId, true);
    } catch (_) {
      toast("Não foi possível salvar o rascunho local.", "#F59E0B", 3600);
    }
  }

  function salvarRascunhoAtualPreAlt() {
    if (state.restoringDraft) return;
    const { obraId, cardId } = getCurrentPreAltDraftContext();
    salvarRascunhoPreAlt(obraId, cardId);
  }

  function normalizeDraftResultado(value) {
    const normalized = String(value || "")
      .trim()
      .toUpperCase();
    if (
      normalized === "SEM_ALTERACAO" ||
      normalized === "APROVADA" ||
      normalized === "APROVADO"
    )
      return "SEM_ALTERACAO";
    if (
      normalized === "AGUARDANDO_CLIENTE" ||
      normalized.includes("AGUARDANDO")
    )
      return "AGUARDANDO_CLIENTE";
    return "ALTERACAO";
  }

  function normalizeDraftNivel(value) {
    const match = String(value || "").match(/[1-5]/);
    return match ? Number(match[0]) : null;
  }

  function setItemComplexidade(container, nivel) {
    const btns = container.querySelectorAll(".complexidade-btn");
    if (nivel) {
      container.dataset.nivel = String(nivel);
    } else {
      delete container.dataset.nivel;
    }

    btns.forEach((btn) => {
      const active = Number(btn.dataset.valor) === Number(nivel);
      btn.className = "complexidade-btn";
      if (active) btn.classList.add("active", `active-n${btn.dataset.valor}`);
    });
  }

  function syncItemVisualState(container) {
    const resultadoSelect = container.querySelector(".resultado-select");
    const nivelRow = container.querySelector(".nivel-row");
    const tipoRow = container.querySelector(".tipo-row");
    const retornoCheck = container.querySelector(".necessita-retorno-check");
    const resultadoBadge = container.querySelector("[data-resultado-badge]");
    const resultado = resultadoSelect?.value || "ALTERACAO";
    const isAlteracao = resultado === "ALTERACAO";

    if (nivelRow) nivelRow.style.display = isAlteracao ? "" : "none";
    if (tipoRow) tipoRow.style.display = isAlteracao ? "" : "none";
    if (retornoCheck && resultado === "AGUARDANDO_CLIENTE")
      retornoCheck.checked = true;

    container.dataset.resultado = resultadoFilterValue(resultado);
    if (resultadoBadge) {
      resultadoBadge.className = `badge-resultado ${resultadoBadgeClass(resultado)}`;
      resultadoBadge.textContent = resultadoLabel(resultado);
    }
  }

  function restaurarRascunhoPreAlt(obraId, cardId) {
    const draft = readPreAltDraft(obraId, cardId);
    if (!draft?.itens) return false;

    state.restoringDraft = true;
    try {
      refs.paModalBody.querySelectorAll(".prealt-item").forEach((container) => {
        const imagemId = container.dataset.imagemId || container.dataset.itemId;
        const dados = draft.itens?.[imagemId];
        if (!dados) return;

        const resultadoSelect = container.querySelector(".resultado-select");
        const tipoInput = container.querySelector(".tipo-input");
        const comentariosInput = container.querySelector(".comentarios-input");
        const retornoCheck = container.querySelector(
          ".necessita-retorno-check",
        );
        const observacaoTextarea = container.querySelector(".acao-textarea");

        if (resultadoSelect)
          resultadoSelect.value = normalizeDraftResultado(dados.resultado);
        if (tipoInput) tipoInput.value = dados.tipo || "";
        if (comentariosInput)
          comentariosInput.value = dados.qtdComentarios ?? "";
        if (retornoCheck) retornoCheck.checked = Boolean(dados.retornoCliente);
        if (observacaoTextarea)
          observacaoTextarea.value = dados.observacao || "";

        setItemComplexidade(container, normalizeDraftNivel(dados.complexidade));
        syncItemVisualState(container);
        container._preAltUpdateDirty?.();
      });
    } finally {
      state.restoringDraft = false;
    }

    updateModalDirtyState();
    aplicarFiltrosPreAlt();
    atualizarIconeRascunhoPreAlt(obraId, cardId, true);
    return true;
  }

  function renderKpis(kpis) {
    const total = Number(kpis.total_imagens || 0);
    const progresso = Number(kpis.progresso_geral || 0);
    const items = [
      ["fa-images", total, "Total de imagens", "blue"],
      ["fa-layer-group", kpis.total_lotes || 0, "Total de lotes", "purple"],
      ["fa-comments", kpis.comentarios || 0, "Comentários", "blue"],
      [
        "fa-clock-rotate-left",
        kpis.aguardando_cliente || 0,
        "Aguardando cliente",
        "orange",
      ],
      [
        "fa-calendar-check",
        kpis.em_planejamento || 0,
        "Em planejamento",
        "green",
      ],
      [
        "fa-chart-simple",
        `${progresso}%`,
        "Progresso geral da triagem",
        "purple",
      ],
    ];

    refs.kpiGrid.innerHTML = items
      .map(
        ([icon, value, label, color]) => `
          <article class="kpi-card kpi-${color}">
            <i class="fa-solid ${icon}"></i>
            <strong>${escHtml(value)}</strong>
            <span>${escHtml(label)}</span>
          </article>
        `,
      )
      .join("");
  }

  function getFilteredLotes() {
    const busca = refs.filtroBusca.value.trim().toLowerCase();
    const obra = Number(refs.filtroObra.value || 0);
    const cliente = Number(refs.filtroCliente.value || 0);
    const status = refs.filtroStatus.value;
    const prioridade = refs.filtroPrioridade.value;
    const responsavel = Number(refs.filtroResponsavel.value || 0);
    const prazo = refs.filtroPrazo.value;
    const data = refs.filtroData.value;

    return state.lotes.filter((lote) => {
      const haystack = [
        lote.nomenclatura,
        lote.nome_cliente,
        lote.nome_etapa,
        lote.responsavel_nome,
        STATUS_META[lote.lote_status]?.label,
      ]
        .join(" ")
        .toLowerCase();

      if (busca && !haystack.includes(busca)) return false;
      if (obra && Number(lote.obra_id) !== obra) return false;
      if (cliente && Number(lote.cliente_id) !== cliente) return false;
      if (status && lote.lote_status !== status) return false;
      if (prioridade && lote.prioridade !== prioridade) return false;
      if (responsavel && Number(lote.responsavel_id || 0) !== responsavel)
        return false;
      if (data && dateOnly(lote.lote_resolvido_em) !== data) return false;
      if (prazo && !matchPrazo(lote.prazo_operacional, prazo)) return false;
      return true;
    });
  }

  function matchPrazo(dateStr, filter) {
    if (!dateStr) return filter === "SEM_PRAZO";
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const date = parseDate(dateStr);
    if (!date) return false;
    const diff = Math.round((date - today) / 86400000);
    if (filter === "ATRASADO") return diff < 0;
    if (filter === "HOJE") return diff === 0;
    if (filter === "7_DIAS") return diff >= 0 && diff <= 7;
    return true;
  }

  function renderLotes() {
    const lista = getFilteredLotes();
    const grouped = {
      triagem: lista.filter((l) => l.lote_status === "EM_TRIAGEM"),
      aguardando: lista.filter((l) => l.lote_status === "AGUARDANDO_CLIENTE"),
      planejamento: lista.filter(
        (l) => l.lote_status === "PRONTO_PLANEJAMENTO",
      ),
    };

    Object.entries(grouped).forEach(([key, lotes]) => {
      const totalImages = lotes.reduce(
        (sum, lote) => sum + Number(lote.total_itens || 0),
        0,
      );
      counts[key].textContent =
        `${lotes.length} lote${lotes.length === 1 ? "" : "s"}`;
      imageCounts[key].textContent = plural(totalImages, "imagem", "imagens");
      columns[key].innerHTML = "";

      if (!lotes.length) {
        const statusKey = Object.keys(STATUS_META).find(
          (s) => STATUS_META[s].col === key,
        );
        columns[key].innerHTML = emptyColumn(STATUS_META[statusKey]);
        return;
      }

      lotes.forEach((lote) => columns[key].appendChild(createCard(lote)));
    });
    syncSelectionUi();
    marcarCardsComRascunhoPreAlt();
  }

  function emptyColumn(meta) {
    return `
      <div class="empty-state-card">
        <i class="fa-solid ${meta.icon}"></i>
        <strong>${escHtml(meta.emptyTitle)}</strong>
        <span>${escHtml(meta.emptyText)}</span>
      </div>
    `;
  }

  function createCard(lote) {
    const card = document.createElement("article");
    const priority = PRIORIDADE_META[lote.prioridade] || PRIORIDADE_META.NORMAL;
    const statusCol = STATUS_META[lote.lote_status]?.col || "triagem";
    const loteId = Number(lote.lote_id);
    const obraId = lote.obra_id || "";
    const selected = state.selected.has(loteId);
    const possuiRascunho = hasPreAltDraft(obraId, loteId);

    card.className = `triage-card card-${statusCol} ${selected ? "is-selected" : ""}`;
    card.dataset.loteId = loteId;
    card.dataset.cardId = loteId;
    card.dataset.obraId = obraId;

    const n1 = Number(lote.nivel_1 || 0);
    const n2 = Number(lote.nivel_2 || 0);
    const n3 = Number(lote.nivel_3 || 0);
    const n4 = Number(lote.nivel_4 || 0);
    const n5 = Number(lote.nivel_5 || 0);
    const comments = lote.comentarios_por_imagem || [];
    const planStatus = lote.planejamento_status || "";
    const planBadge = planStatus
      ? `<span class="badge badge-plan-status">${escHtml(planStatus)}</span>`
      : "";
    const ultimaInteracaoTipo = String(
      lote.ultima_interacao_tipo || "",
    ).toUpperCase();
    const ultimaInteracaoLabel =
      ultimaInteracaoTipo === "SOLICITACAO"
        ? "Solicitação ao cliente"
        : ultimaInteracaoTipo === "RETORNO"
          ? `Retorno: ${lote.ultima_interacao_resultado === "APROVADA" ? "aprovada" : "alteração"}`
          : "Nenhuma interação registrada";
    const clientSummary =
      Number(lote.count_aguardando || 0) || ultimaInteracaoTipo
        ? `<div class="card-client-summary">
          <span><i class="fa-solid fa-comments"></i> ${escHtml(ultimaInteracaoLabel)}</span>
          ${lote.ultima_interacao_em ? `<small>${escHtml(formatDateTime(lote.ultima_interacao_em))}</small>` : ""}
          ${Number(lote.count_aguardando || 0) ? `<strong>${plural(lote.count_aguardando, "imagem aguardando", "imagens aguardando")}</strong>` : ""}
          ${Number(lote.count_reanalise_pos_retorno || 0) ? `<em><i class="fa-solid fa-triangle-exclamation"></i> ${plural(lote.count_reanalise_pos_retorno, "reanálise pendente", "reanálises pendentes")}</em>` : ""}
        </div>`
        : "";

    card.innerHTML = `
      <div class="card-topline">
        <label class="select-card" title="Selecionar lote">
          <input type="checkbox" ${selected ? "checked" : ""}>
          <span></span>
        </label>
        <div class="card-title-group">
          <h3>
            <span>${escHtml(lote.nomenclatura || "Obra")}</span>
            <span class="draft-indicator ${possuiRascunho ? "" : "is-hidden"}" title="Existe rascunho salvo localmente">
              <i class="fa-solid fa-file-pen"></i>
            </span>
          </h3>
        </div>
      </div>

      <div class="card-badges">
        <span class="badge badge-stage">${escHtml(lote.nome_etapa || "Etapa")}</span>
        <span class="badge ${priority.cls}">${escHtml(priority.label)}</span>
        <span class="badge badge-date">Resolvido: ${escHtml(formatDate(lote.lote_resolvido_em) || "Sem registro")}</span>
        ${planBadge}
      </div>
      ${clientSummary}

      <div class="metric-grid">
        ${metric("fa-images", lote.total_itens || 0, "imagens")}
        ${metric("fa-layer-group", lote.batch_count || 0, "lotes")}
        ${metric("fa-comments", lote.total_comentarios || 0, "comentários")}
        ${metric("fa-circle-exclamation", lote.comentarios_criticos || 0, "críticos")}
      </div>

      <div class="complexity-row">
        <span class="active-n1">N1 <strong>${n1}</strong></span>
        <span class="active-n2">N2 <strong>${n2}</strong></span>
        <span class="active-n3">N3 <strong>${n3}</strong></span>
        <span class="active-n4">N4 <strong>${n4}</strong></span>
        <span class="active-n5">N5 <strong>${n5}</strong></span>
      </div>

      <div class="progress-block">
        <div>
          <strong>${Number(lote.progresso || 0)}%</strong>
          <span>${plural(lote.classificados || 0, "imagem classificada", "imagens classificadas")} de ${plural(lote.total_itens || 0, "imagem", "imagens")}</span>
        </div>
        <div class="progress-track"><span style="width:${Math.min(100, Number(lote.progresso || 0))}%"></span></div>
      </div>

      <div class="operational-info">
        <span><i class="fa-solid fa-flag-checkered"></i> Resolvido: ${escHtml(formatDateTime(lote.lote_resolvido_em))}</span>
        <span><i class="fa-solid fa-calendar-day"></i> Análise até: ${escHtml(formatDate(lote.prazo_operacional) || "Sem prazo")}</span>
        <span><i class="fa-solid fa-clock"></i> Atualização: ${escHtml(formatDateTime(lote.ultima_atualizacao))}</span>
        <span><i class="fa-solid fa-user"></i> ${escHtml(lote.responsavel_nome || "Sem responsável")}</span>
      </div>

      <div class="card-actions">
        <button type="button" data-card-action="detalhes">Ver detalhes</button>
        <button type="button" data-card-action="triagem">Abrir triagem</button>
        <button type="button" data-card-action="planejar">Planejar</button>
        <button type="button" data-card-action="concluir">Concluir triagem</button>
        <button type="button" data-card-action="review" ${lote.link_review ? "" : "disabled"}>Review Studio</button>
      </div>
    `;

    const selectControl = card.querySelector(".select-card");
    selectControl.addEventListener("pointerdown", (event) => {
      event.stopPropagation();
    });
    selectControl.addEventListener("click", (event) => {
      event.stopPropagation();
    });
    selectControl.addEventListener("mousedown", (event) => {
      event.stopPropagation();
    });
    const selectInput = card.querySelector(".select-card input");
    selectInput.addEventListener("click", (event) => {
      event.stopPropagation();
    });
    selectInput.addEventListener("change", (event) => {
      event.stopPropagation();
      toggleSelection(loteId, event.currentTarget.checked);
    });
    card.querySelectorAll("[data-card-action]").forEach((button) => {
      button.addEventListener("click", (event) => {
        event.stopPropagation();
        handleCardAction(button.dataset.cardAction, lote);
      });
    });
    card.addEventListener("click", (event) => {
      if (event.target.closest(".select-card, .card-actions, .card-menu"))
        return;
      abrirModal(loteId);
    });
    return card;
  }

  function metric(icon, value, label) {
    return `<span><i class="fa-solid ${icon}"></i><strong>${escHtml(value)}</strong>${escHtml(label)}</span>`;
  }

  function commentRow(item) {
    const count = Number(item.comment_count || 0);
    const label =
      count === 0
        ? "sem comentários"
        : `${count} comentário${count === 1 ? "" : "s"}`;
    return `
      <div class="comment-image-row ${Number(item.critical_count || 0) > 0 ? "has-critical" : ""}">
        <span>${escHtml(item.nome || "Imagem")}</span>
        <i></i>
        <strong>${escHtml(label)}</strong>
      </div>
    `;
  }

  function handleCardAction(action, lote) {
    if (action === "planejar") {
      window.location.href = `${BASE}planejamento.php?lote_id=${encodeURIComponent(lote.lote_id)}`;
      return;
    }
    if (action === "review") {
      if (lote.link_review) window.open(lote.link_review, "_blank", "noopener");
      return;
    }
    if (action === "concluir") {
      abrirConclusaoLote(lote.lote_id);
      return;
    }
    abrirModal(lote.lote_id);
  }

  async function abrirModal(loteId) {
    refs.paModal.classList.add("is-open");
    refs.paModal.setAttribute("aria-hidden", "false");
    refs.paModalTitle.textContent = "Carregando";
    refs.paModalBadges.innerHTML = "";
    refs.paModalActions.innerHTML = "";
    state.itensAbertos = [];
    updateModalDirtyState();
    refs.paModalBody.innerHTML =
      '<div class="modal-loading"><i class="fa-solid fa-spinner fa-spin"></i> Carregando lote</div>';

    try {
      const res = await fetch(BASE + `get_pre_alt_lote.php?lote_id=${loteId}`);
      const json = await res.json();
      if (!json.success) throw new Error(json.error || "Erro ao carregar lote");
      state.loteAberto = json.lote;
      state.itensAbertos = json.itens || [];
      state.interacoesCliente = json.interacoes_cliente || [];
      state.clientItemSelection = new Set();
      renderModal(json.lote, json.itens || []);
    } catch (err) {
      refs.paModalBody.innerHTML = `<div class="modal-loading is-error">${escHtml(err.message)}</div>`;
    }
  }

  function renderModal(lote, itens) {
    const resumo = buildResumo(itens);
    refs.paModalTitle.textContent = lote.nomenclatura || "Lote";
    refs.paModalBadges.innerHTML = `
      <span>${escHtml(lote.nome_etapa || "Etapa")}</span>
      <span>${escHtml(STATUS_META[lote.lote_status]?.label || lote.lote_status)}</span>
      <span>Resolvido: ${escHtml(formatDate(lote.lote_resolvido_em) || "Sem registro")}</span>
      <span>Análise até: ${escHtml(formatDate(lote.prazo_operacional) || "Sem prazo")}</span>
      <span>${escHtml(lote.nome_cliente || "Cliente não informado")}</span>
      <span class="draft-indicator modal-draft-indicator ${hasPreAltDraft(lote.obra_id, lote.lote_id) ? "" : "is-hidden"}" id="paModalDraftIndicator" title="Existe rascunho salvo localmente">
        <i class="fa-solid fa-file-pen"></i>
        Rascunho local
      </span>
    `;
    refs.paModalActions.innerHTML = `
      <button type="button" class="modal-review-btn" id="modalPlanejamentoBtn">
        <i class="fa-solid fa-diagram-project"></i> Planejar
      </button>
      <button type="button" class="modal-review-btn" id="modalUploadProjetoBtn">
        <i class="fa-solid fa-upload"></i> Upload projeto
      </button>
      <button type="button" class="modal-review-btn" ${lote.link_review ? "" : "disabled"} id="modalReviewBtn">
        <i class="fa-solid fa-arrow-up-right-from-square"></i> Review Studio
      </button>
    `;
    refs.paModalActions
      .querySelector("#modalUploadProjetoBtn")
      ?.addEventListener("click", () => {
        openTriagemUploadModal("projeto");
      });
    refs.paModalActions
      .querySelector("#modalPlanejamentoBtn")
      ?.addEventListener("click", () => {
        window.location.href = `${BASE}planejamento.php?lote_id=${encodeURIComponent(lote.lote_id)}`;
      });
    refs.paModalActions
      .querySelector("#modalReviewBtn")
      ?.addEventListener("click", () => {
        if (lote.link_review)
          window.open(lote.link_review, "_blank", "noopener");
      });

    refs.paModalBody.innerHTML = `
      <div class="modal-summary">
        ${summaryTile(resumo.total, "Imagens")}
        ${summaryTile(resumo.comentarios, "Comentários")}
        ${summaryTile(resumo.criticos, "Críticos")}
        ${summaryTile(resumo.alteracao, "Com alteração")}
        ${summaryTile(resumo.aguardando, "Aguardando cliente")}
        ${summaryTile(`${resumo.progresso}%`, "Classificação")}
      </div>
      <section class="client-interaction-panel" aria-label="Contato com o cliente">
        <div class="client-interaction-panel-head">
          <div>
            <span>Contato com o cliente</span>
            <strong id="clientInteractionSelectionCount">Nenhuma imagem selecionada</strong>
          </div>
          <button type="button" class="btn-client-link" id="btnSelecionarPendentesCliente">Selecionar pendentes</button>
        </div>
        <div class="client-interaction-actions">
          <button type="button" class="btn-client-action is-request" id="btnSolicitarCliente" disabled>
            <i class="fa-solid fa-paper-plane"></i> Solicitar ao cliente
          </button>
          <button type="button" class="btn-client-action is-return" id="btnRegistrarRetorno" disabled>
            <i class="fa-solid fa-reply"></i> Registrar retorno
          </button>
        </div>
      </section>
      <div class="prealt-filters" aria-label="Filtros das imagens">
        <input type="search" id="filtroImagem" placeholder="Buscar imagem..." autocomplete="off">
        <select id="filtroResultado">
          <option value="">Todos os resultados</option>
          <option value="alteracao">Alteração</option>
          <option value="aguardando_cliente">Aguardando cliente</option>
          <option value="aprovada">Aprovada</option>
        </select>
      </div>
      <div class="modal-items-list"></div>
      <div class="modal-empty-filter" id="prealtFiltroVazio" hidden>
        <strong>Nenhuma imagem encontrada</strong>
        <span>Ajuste a busca ou o resultado selecionado.</span>
      </div>
    `;

    const list = refs.paModalBody.querySelector(".modal-items-list");
    if (!itens.length) {
      list.innerHTML =
        '<div class="empty-state-card"><strong>Nenhuma imagem neste lote</strong><span>O lote não possui itens vinculados.</span></div>';
      return;
    }
    itens.forEach((item) => list.appendChild(createItem(item)));
    wireClientInteractionActions();
    restaurarRascunhoPreAlt(lote.obra_id, lote.lote_id);
    wireModalFilters();
    updateModalDirtyState();
  }

  function isClientPending(item) {
    return (
      item?.resultado === "AGUARDANDO_CLIENTE" ||
      Number(item?.necessita_retorno || 0) === 1
    );
  }

  function getClientInteractionsForItem(itemId) {
    return (state.interacoesCliente || []).filter((interacao) =>
      (interacao.itens || []).some(
        (item) => Number(item.item_id) === Number(itemId),
      ),
    );
  }

  function getClientInteractionItem(interacao, itemId) {
    return (interacao?.itens || []).find(
      (item) => Number(item.item_id) === Number(itemId),
    );
  }

  function renderClientInteractionTimeline(item) {
    const interacoes = getClientInteractionsForItem(item.item_id);
    const hasSolicitacao = interacoes.some(
      (interacao) => interacao.tipo === "SOLICITACAO",
    );
    const legacyPending = isClientPending(item) && !hasSolicitacao;
    const stateBadge =
      item.reanalise_pos_retorno == 1
        ? '<span class="client-item-state is-attention"><i class="fa-solid fa-triangle-exclamation"></i> Reanalisar após retorno do cliente</span>'
        : legacyPending
          ? '<span class="client-item-state is-legacy"><i class="fa-solid fa-circle-info"></i> Solicitação não registrada</span>'
          : "";

    const timeline = interacoes.length
      ? interacoes
          .map((interacao) => {
            const vinculo = getClientInteractionItem(interacao, item.item_id);
            const isReturn = interacao.tipo === "RETORNO";
            const label = isReturn
              ? `Retorno: ${interacao.resultado_retorno === "APROVADA" ? "aprovada" : "alteração"}`
              : "Solicitação ao cliente";
            const snapshot = vinculo?.estado_anterior;
            const snapshotLabel = snapshot
              ? `Antes do retorno: ${snapshot.resultado || "Sem resultado"}${snapshot.nivel_complexidade ? `, N${snapshot.nivel_complexidade}` : ""}`
              : "";
            return `
              <li class="client-timeline-entry ${isReturn ? "is-return" : "is-request"}">
                <strong>${escHtml(label)}</strong>
                <span>${escHtml(formatDateTime(interacao.ocorrido_em))}${interacao.colaborador_nome ? ` · ${escHtml(interacao.colaborador_nome)}` : ""}</span>
                ${interacao.observacao ? `<small>${escHtml(interacao.observacao)}</small>` : ""}
                ${snapshotLabel ? `<small class="client-timeline-snapshot">${escHtml(snapshotLabel)}</small>` : ""}
              </li>
            `;
          })
          .join("")
      : '<li class="client-timeline-entry is-empty">Nenhuma interação registrada.</li>';

    return `
      <section class="client-item-history">
        <div class="client-item-history-head">
          <span>Histórico do cliente</span>
          ${stateBadge}
        </div>
        <ul>${timeline}</ul>
      </section>
    `;
  }

  function syncClientInteractionSelectionUi() {
    const selectedIds = Array.from(state.clientItemSelection || []);
    const selectedItems = state.itensAbertos.filter((item) =>
      state.clientItemSelection.has(Number(item.item_id)),
    );
    const counter = refs.paModalBody?.querySelector(
      "#clientInteractionSelectionCount",
    );
    const requestButton = refs.paModalBody?.querySelector(
      "#btnSolicitarCliente",
    );
    const returnButton = refs.paModalBody?.querySelector(
      "#btnRegistrarRetorno",
    );
    if (counter) {
      counter.textContent = selectedIds.length
        ? plural(
            selectedIds.length,
            "imagem selecionada",
            "imagens selecionadas",
          )
        : "Nenhuma imagem selecionada";
    }
    if (requestButton) requestButton.disabled = selectedIds.length === 0;
    if (returnButton) {
      returnButton.disabled =
        selectedIds.length === 0 || !selectedItems.every(isClientPending);
    }
    refs.paModalBody
      ?.querySelectorAll(".client-item-select")
      .forEach((input) => {
        input.checked = state.clientItemSelection.has(Number(input.value));
      });
  }

  function getDateTimeLocalNow() {
    const now = new Date();
    const timezoneOffset = now.getTimezoneOffset() * 60000;
    return new Date(now.getTime() - timezoneOffset).toISOString().slice(0, 16);
  }

  async function promptClientInteraction(tipo, total) {
    const isReturn = tipo === "RETORNO";
    const result = await Swal.fire({
      title: isReturn ? "Registrar retorno do cliente" : "Solicitar ao cliente",
      html: `
        ${
          isReturn
            ? `
          <select id="swal-client-return-result" class="swal2-input">
            <option value="">Selecione o resultado</option>
            <option value="APROVADA">Aprovada / sem alteração</option>
            <option value="ALTERACAO">Solicitou alteração</option>
          </select>
        `
            : ""
        }
        <label class="swal-client-label" for="swal-client-occurred-at">Data e hora</label>
        <input id="swal-client-occurred-at" class="swal2-input" type="datetime-local" value="${getDateTimeLocalNow()}">
        <textarea id="swal-client-note" class="swal2-textarea" placeholder="Observação opcional para ${total} imagem(ns)"></textarea>
      `,
      showCancelButton: true,
      confirmButtonText: isReturn
        ? "Registrar retorno"
        : "Registrar solicitação",
      cancelButtonText: "Cancelar",
      focusConfirm: false,
      preConfirm: () => {
        const ocorridoEm =
          document.getElementById("swal-client-occurred-at")?.value || "";
        const resultadoRetorno =
          document.getElementById("swal-client-return-result")?.value || "";
        const observacao =
          document.getElementById("swal-client-note")?.value || "";
        if (!ocorridoEm) {
          Swal.showValidationMessage("Informe a data e hora da interação.");
          return false;
        }
        if (isReturn && !resultadoRetorno) {
          Swal.showValidationMessage("Selecione o resultado do retorno.");
          return false;
        }
        return {
          ocorrido_em: ocorridoEm,
          resultado_retorno: resultadoRetorno,
          observacao: observacao.trim(),
        };
      },
    });
    return result.isConfirmed ? result.value : null;
  }

  async function registrarClientInteraction(tipo) {
    const itemIds = Array.from(state.clientItemSelection || []);
    if (!itemIds.length || !state.loteAberto?.lote_id) return;
    const selectedItems = state.itensAbertos.filter((item) =>
      itemIds.includes(Number(item.item_id)),
    );
    if (tipo === "RETORNO" && !selectedItems.every(isClientPending)) {
      toast(
        "Selecione somente imagens aguardando cliente para registrar o retorno.",
        "#F59E0B",
      );
      return;
    }
    const payload = await promptClientInteraction(tipo, itemIds.length);
    if (!payload) return;

    try {
      const res = await fetch(BASE + "registrar_interacao_cliente.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          lote_id: state.loteAberto.lote_id,
          item_ids: itemIds,
          tipo,
          ...payload,
        }),
      });
      const json = await res.json();
      if (!json.success)
        throw new Error(
          json.error || "Nao foi possivel registrar a interação.",
        );
      toast(
        tipo === "RETORNO"
          ? "Retorno do cliente registrado."
          : "Solicitação ao cliente registrada.",
        "#22C55E",
      );
      await abrirModal(state.loteAberto.lote_id);
      await carregarLotes();
    } catch (error) {
      toast("Erro: " + error.message, "#EF4444", 5200);
    }
  }

  function wireClientInteractionActions() {
    const selectPending = refs.paModalBody?.querySelector(
      "#btnSelecionarPendentesCliente",
    );
    selectPending?.addEventListener("click", () => {
      state.itensAbertos.filter(isClientPending).forEach((item) => {
        state.clientItemSelection.add(Number(item.item_id));
      });
      syncClientInteractionSelectionUi();
    });
    refs.paModalBody
      ?.querySelector("#btnSolicitarCliente")
      ?.addEventListener("click", () =>
        registrarClientInteraction("SOLICITACAO"),
      );
    refs.paModalBody
      ?.querySelector("#btnRegistrarRetorno")
      ?.addEventListener("click", () => registrarClientInteraction("RETORNO"));
    syncClientInteractionSelectionUi();
  }

  function createItem(item) {
    const div = document.createElement("article");
    const resultado = item.resultado || "ALTERACAO";
    div.className = "modal-imagem-item prealt-item is-expanded";
    div.dataset.itemId = item.item_id;
    div.dataset.imagemId = item.imagem_id || item.item_id;
    div.dataset.nome = item.nome || "";
    div.dataset.resultado = resultadoFilterValue(resultado);

    div.innerHTML = `
      <header class="modal-item-header">
        <div>
          <strong title="${escHtml(item.nome)}">${escHtml(item.nome)}</strong>
          <span>${Number(item.comment_count || item.quantidade_comentarios || 0)} comentário${Number(item.comment_count || item.quantidade_comentarios || 0) === 1 ? "" : "s"}${Number(item.critical_count || 0) ? `, ${item.critical_count} crítico(s)` : ""}</span>
        </div>
        <label class="client-item-selector" title="Selecionar para contato com o cliente">
          <input type="checkbox" class="client-item-select" value="${item.item_id}">
          <span>Cliente</span>
        </label>
        <button type="button" class="item-upload-btn" data-upload-image title="Enviar arquivos para esta imagem">
          <i class="fa-solid fa-upload"></i>
        </button>
        <span class="badge-resultado ${resultadoBadgeClass(resultado)}" data-resultado-badge>${escHtml(resultadoLabel(resultado))}</span>
        ${item.nivel_complexidade ? `<span class="item-status item-level">${item.nivel_complexidade}</span>` : ""}
      </header>
      <div class="modal-item-body">${createForm(item)}${renderClientInteractionTimeline(item)}</div>
    `;

    wireItem(div, item);
    div.querySelector("[data-upload-image]")?.addEventListener("click", () => {
      openTriagemUploadModal("imagem", item);
    });
    div
      .querySelector(".client-item-select")
      ?.addEventListener("change", (event) => {
        const itemId = Number(event.currentTarget.value);
        if (event.currentTarget.checked) state.clientItemSelection.add(itemId);
        else state.clientItemSelection.delete(itemId);
        syncClientInteractionSelectionUi();
      });
    return div;
  }

  function createForm(item) {
    const resultado = item.resultado || "ALTERACAO";
    const nivel = item.nivel_complexidade || "";
    const tipo = escHtml(item.tipo_alteracao || "");
    const acao = escHtml(item.acao || "");
    const nr = item.necessita_retorno == 1;
    const qtdComentarios = Number(
      item.comment_count || item.quantidade_comentarios || 0,
    );

    return `
      <div class="form-grid">
        <label>
          <span>Resultado</span>
          <select class="resultado-select">
            <option value="ALTERACAO" ${resultado === "ALTERACAO" ? "selected" : ""}>Alteração</option>
            <option value="SEM_ALTERACAO" ${resultado === "SEM_ALTERACAO" ? "selected" : ""}>Aprovada</option>
            <option value="AGUARDANDO_CLIENTE" ${resultado === "AGUARDANDO_CLIENTE" ? "selected" : ""}>Aguardando cliente</option>
          </select>
        </label>
        <label class="tipo-row">
          <span>Tipo</span>
          <input class="tipo-input" type="text" placeholder="Acabamento, composição, projeto" value="${tipo}">
        </label>
        <label>
          <span>Quantidade de comentários</span>
          <input class="comentarios-input" type="number" min="0" step="1" value="${qtdComentarios}">
        </label>
      </div>
      <div class="nivel-row">
        <span>Complexidade</span>
        <div class="complexidade-options">
          ${[1, 2, 3, 4, 5]
            .map(
              (n) =>
                `<button type="button" class="complexidade-btn ${Number(nivel) === n ? `active active-n${n}` : ""}" data-valor="${n}" title="${escHtml(NIVEL_LABELS[n])}">N${n}</button>`,
            )
            .join("")}
        </div>
      </div>
      <label class="necessita-retorno-row">
        <input type="checkbox" class="necessita-retorno-check" ${nr ? "checked" : ""}>
        <span><i class="fa-solid fa-clock-rotate-left"></i> Necessita retorno do cliente</span>
      </label>
      <label class="textarea-row">
        <span>Ação / Observações</span>
        <textarea class="acao-textarea" placeholder="Resumo objetivo para orientar planejamento e execução">${acao}</textarea>
      </label>
    `;
  }

  function wireItem(container, item) {
    const resultadoSelect = container.querySelector(".resultado-select");
    const btns = container.querySelectorAll(".complexidade-btn");

    const syncVisibility = () => {
      syncItemVisualState(container);
    };

    btns.forEach((btn) => {
      btn.addEventListener("click", () => {
        setItemComplexidade(container, Number(btn.dataset.valor));
        updateDirty();
        salvarRascunhoAtualPreAlt();
      });
    });

    if (item.nivel_complexidade)
      container.dataset.nivel = item.nivel_complexidade;
    const updateDirty = () => {
      container.classList.toggle("is-dirty", isItemDirty(container, item));
      updateModalDirtyState();
    };
    container._preAltUpdateDirty = updateDirty;
    const syncResultadoState = () => {
      syncItemVisualState(container);
      aplicarFiltrosPreAlt();
    };

    resultadoSelect.addEventListener("change", () => {
      syncVisibility();
      syncResultadoState();
      updateDirty();
      salvarRascunhoAtualPreAlt();
    });
    container.querySelectorAll("input, select, textarea").forEach((field) => {
      if (
        field === resultadoSelect ||
        field.classList.contains("client-item-select")
      )
        return;
      field.addEventListener("input", () => {
        updateDirty();
        salvarRascunhoAtualPreAlt();
      });
      field.addEventListener("change", () => {
        updateDirty();
        salvarRascunhoAtualPreAlt();
      });
    });
    syncVisibility();
    syncResultadoState();
    container.dataset.initialPayload = JSON.stringify(
      getItemFormPayload(container, item),
    );
    updateDirty();
  }

  function wireModalFilters() {
    const filtroImagem = refs.paModalBody.querySelector("#filtroImagem");
    const filtroResultado = refs.paModalBody.querySelector("#filtroResultado");
    filtroImagem?.addEventListener("input", aplicarFiltrosPreAlt);
    filtroResultado?.addEventListener("change", aplicarFiltrosPreAlt);
    aplicarFiltrosPreAlt();
  }

  function aplicarFiltrosPreAlt() {
    if (!refs.paModalBody) return;
    const filtroImagem = refs.paModalBody.querySelector("#filtroImagem");
    const filtroResultado = refs.paModalBody.querySelector("#filtroResultado");
    const emptyFilter = refs.paModalBody.querySelector("#prealtFiltroVazio");
    const busca = filtroImagem?.value.trim().toLowerCase() || "";
    const resultado = filtroResultado?.value || "";
    let visiveis = 0;

    refs.paModalBody.querySelectorAll(".prealt-item").forEach((item) => {
      const nome = item.dataset.nome?.toLowerCase() || "";
      const itemResultado = item.dataset.resultado || "";
      const passaBusca = !busca || nome.includes(busca);
      const passaResultado = !resultado || itemResultado === resultado;
      const visivel = passaBusca && passaResultado;

      item.style.display = visivel ? "" : "none";
      if (visivel) visiveis += 1;
    });

    if (emptyFilter) emptyFilter.hidden = visiveis > 0;
  }

  function getItemFormPayload(container, item) {
    const resultado = container.querySelector(".resultado-select").value;
    const nivel = parseInt(container.dataset.nivel || "0", 10) || null;
    const quantidadeComentarios = Math.max(
      0,
      parseInt(
        container.querySelector(".comentarios-input")?.value || "0",
        10,
      ) || 0,
    );

    return {
      item_id: item.item_id,
      resultado,
      nivel_complexidade: resultado === "ALTERACAO" ? nivel : null,
      tipo_alteracao:
        resultado === "ALTERACAO"
          ? container.querySelector(".tipo-input")?.value.trim() || ""
          : "",
      acao: container.querySelector(".acao-textarea")?.value.trim() || "",
      necessita_retorno: container.querySelector(".necessita-retorno-check")
        ?.checked
        ? 1
        : 0,
      quantidade_comentarios: quantidadeComentarios,
    };
  }

  function isItemDirty(container, item) {
    return (
      JSON.stringify(getItemFormPayload(container, item)) !==
      container.dataset.initialPayload
    );
  }

  function getDirtyItemPayloads() {
    return Array.from(
      refs.paModalBody.querySelectorAll(".modal-imagem-item.is-dirty"),
    ).map((container) => {
      const itemId = Number(container.dataset.itemId);
      const item = state.itensAbertos.find(
        (row) => Number(row.item_id) === itemId,
      ) || { item_id: itemId };
      return getItemFormPayload(container, item);
    });
  }

  function updateModalDirtyState() {
    const total = refs.paModalBody
      ? refs.paModalBody.querySelectorAll(".modal-imagem-item.is-dirty").length
      : 0;
    if (refs.paPendingCount) {
      refs.paPendingCount.textContent = `${plural(total, "imagem", "imagens")} com alterações pendentes`;
    }
    if (refs.btnSalvarAlteracoes) {
      refs.btnSalvarAlteracoes.disabled = total === 0;
    }
  }

  function normalizeSufixo(value) {
    return String(value || "")
      .trim()
      .toUpperCase()
      .replace(/\s+/g, "_")
      .replace(/[^A-Z0-9_]/g, "");
  }

  function validSufixo(value) {
    if (!value) return true;
    const normalized = normalizeSufixo(value);
    return normalized !== "" && normalized.split("_").length <= 2;
  }

  async function fetchTriagemSufixos(tipoArquivo) {
    if (!tipoArquivo) return [];
    try {
      const response = await fetch(
        `${FLOWDRIVE_BASE}getSufixos.php?tipo_arquivo=${encodeURIComponent(tipoArquivo)}`,
      );
      const json = await response.json();
      return Array.isArray(json) ? json : [];
    } catch (_) {
      return [];
    }
  }

  async function saveTriagemSufixo(tipoArquivo, value) {
    const normalized = normalizeSufixo(value);
    if (!tipoArquivo || !normalized || !validSufixo(normalized)) return;
    await fetch(`${FLOWDRIVE_BASE}getSufixos.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ tipo_arquivo: tipoArquivo, valor: normalized }),
    }).catch(() => {});
  }

  function currentUploadTipos(scope, item) {
    const tiposLote = Array.from(
      new Set(state.itensAbertos.map((row) => row.tipo_imagem).filter(Boolean)),
    );
    if (scope === "imagem") {
      return [item?.tipo_imagem].filter(Boolean).length
        ? [item.tipo_imagem]
        : tiposLote;
    }
    return tiposLote;
  }

  function openTriagemUploadModal(scope, item = null) {
    const lote = state.loteAberto || {};
    if (!lote.obra_id) {
      toast("Lote sem obra vinculada.", "#EF4444");
      return;
    }

    const existing = document.getElementById("triagemUploadModal");
    if (existing) existing.remove();

    const tipos = currentUploadTipos(scope, item);
    const tipoOptions = TRIAGEM_TIPOS_ARQUIVO.map(
      (tipo) => `<option value="${tipo}">${tipo}</option>`,
    ).join("");
    const categoriaOptions = TRIAGEM_CATEGORIAS.map(
      ([id, label]) =>
        `<option value="${id}" ${id === 6 ? "selected" : ""}>${label}</option>`,
    ).join("");
    const title = scope === "imagem" ? "Upload da imagem" : "Upload do projeto";
    const subtitle =
      scope === "imagem"
        ? item?.nome || "Imagem"
        : lote.nomenclatura || "Projeto";
    const modal = document.createElement("div");
    modal.id = "triagemUploadModal";
    modal.className = "triagem-upload-modal is-open";
    modal.innerHTML = `
      <form class="triagem-upload-card" id="triagemUploadForm">
        <header>
          <div>
            <span>${escHtml(title)}</span>
            <strong>${escHtml(subtitle)}</strong>
          </div>
          <button type="button" data-close-upload title="Fechar"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <div class="triagem-upload-body">
          <div class="triagem-upload-grid">
            <label>
              <span>Categoria</span>
              <select name="tipo_categoria">${categoriaOptions}</select>
            </label>
            <label>
              <span>Tipo de arquivo</span>
              <select name="tipo_arquivo" required>
                <option value="">Selecione</option>
                ${tipoOptions}
              </select>
            </label>
            <label>
              <span>Data de recebimento</span>
              <input type="date" name="data_recebido" value="${escHtml(dateOnly(new Date().toISOString()))}" required>
            </label>
          </div>
          <label class="triagem-upload-field">
            <span>Sufixo</span>
            <input name="sufixo" list="triagemSufixosList" placeholder="Selecione ou digite">
            <datalist id="triagemSufixosList"></datalist>
          </label>
          <div class="triagem-upload-types">
            <span>Tipo de imagem</span>
            <strong>${escHtml(tipos.join(", ") || "Não identificado")}</strong>
          </div>
          <label class="triagem-upload-field">
            <span>Observação de triagem</span>
            <textarea name="descricao" rows="3" placeholder="Resumo para identificar o envio"></textarea>
          </label>
          <label class="triagem-upload-files">
            <i class="fa-solid fa-cloud-arrow-up"></i>
            <strong>Selecionar arquivos</strong>
            <span>Envio múltiplo permitido</span>
            <input type="file" name="files" multiple required>
          </label>
          <div class="triagem-upload-file-list" id="triagemUploadFileList"></div>
          <label class="triagem-upload-check">
            <input type="checkbox" name="flag_substituicao" value="1">
            <span>Substituir arquivos existentes do mesmo contexto</span>
          </label>
        </div>
        <footer>
          <button type="button" class="btn" data-close-upload>Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload"></i> Enviar arquivos</button>
        </footer>
      </form>
    `;
    document.body.appendChild(modal);

    const form = modal.querySelector("#triagemUploadForm");
    const tipoSelect = form.querySelector("[name='tipo_arquivo']");
    const fileInput = form.querySelector("[name='files']");
    const suffixList = form.querySelector("#triagemSufixosList");
    const close = () => modal.remove();

    modal
      .querySelectorAll("[data-close-upload]")
      .forEach((btn) => btn.addEventListener("click", close));
    modal.addEventListener("click", (event) => {
      if (event.target === modal) close();
    });

    tipoSelect.addEventListener("change", async () => {
      const sufixos = await fetchTriagemSufixos(tipoSelect.value);
      suffixList.innerHTML = sufixos
        .map((sufixo) => `<option value="${escHtml(sufixo)}"></option>`)
        .join("");
    });

    fileInput.addEventListener("change", () =>
      renderTriagemUploadFiles(form, scope),
    );
    form.addEventListener("submit", (event) =>
      submitTriagemUpload(event, scope, item, tipos, close),
    );
  }

  function renderTriagemUploadFiles(form, scope) {
    const list = form.querySelector("#triagemUploadFileList");
    const files = Array.from(form.querySelector("[name='files']")?.files || []);
    if (!files.length) {
      list.innerHTML = "";
      return;
    }
    if (scope === "imagem" || files.length === 1) {
      list.innerHTML = files
        .map(
          (file) =>
            `<div><span>${escHtml(file.name)}</span><small>${Math.ceil(file.size / 1024)} KB</small></div>`,
        )
        .join("");
      return;
    }
    list.innerHTML = files
      .map(
        (file, index) => `
        <label class="triagem-upload-file-row">
          <span>${escHtml(file.name)}</span>
          <input name="sufixo_por_arquivo_${index}" placeholder="Sufixo deste arquivo">
        </label>`,
      )
      .join("");
  }

  async function submitTriagemUpload(event, scope, item, tipos, close) {
    event.preventDefault();
    const form = event.currentTarget;
    const lote = state.loteAberto || {};
    const files = Array.from(
      form.querySelector("[name='files']")?.files || [],
    ).filter((file) => file.size > 0);
    const tipoArquivo =
      form.querySelector("[name='tipo_arquivo']")?.value || "";
    const sufixo = normalizeSufixo(
      form.querySelector("[name='sufixo']")?.value || "",
    );
    const descricaoLivre =
      form.querySelector("[name='descricao']")?.value.trim() || "";

    if (!tipoArquivo || !files.length || !tipos.length) {
      toast(
        "Informe o tipo de arquivo, o tipo de imagem e selecione ao menos um arquivo.",
        "#F59E0B",
      );
      return;
    }
    if (sufixo && !validSufixo(sufixo)) {
      toast(
        "Sufixo inválido. Use no máximo duas palavras separadas por _.",
        "#F59E0B",
      );
      return;
    }

    const button = form.querySelector("button[type='submit']");
    button.disabled = true;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando';

    try {
      if (sufixo) await saveTriagemSufixo(tipoArquivo, sufixo);
      const fd = new FormData();
      fd.append("obra_id", lote.obra_id);
      fd.append(
        "tipo_categoria",
        form.querySelector("[name='tipo_categoria']")?.value || "6",
      );
      fd.append("tipo_arquivo", tipoArquivo);
      fd.append(
        "data_recebido",
        form.querySelector("[name='data_recebido']")?.value ||
          dateOnly(new Date().toISOString()),
      );
      fd.append(
        "descricao",
        `Triagem${lote.lote_id ? " lote " + lote.lote_id : ""}${descricaoLivre ? " - " + descricaoLivre : ""}`,
      );
      if (form.querySelector("[name='flag_substituicao']")?.checked)
        fd.append("flag_substituicao", "1");
      tipos.forEach((tipo) => fd.append("tipo_imagem[]", tipo));

      if (scope === "imagem") {
        fd.append("refsSkpModo", "porImagem");
        if (sufixo) fd.append("sufixo", sufixo);
        fd.append(
          `observacoes_por_imagem[${item.imagem_id}]`,
          `Triagem${descricaoLivre ? " - " + descricaoLivre : ""}`,
        );
        files.forEach((file) =>
          fd.append(`arquivos_por_imagem[${item.imagem_id}][]`, file),
        );
      } else {
        fd.append("refsSkpModo", "geral");
        for (const [index, file] of files.entries()) {
          fd.append("arquivos[]", file);
          const perFile = normalizeSufixo(
            form.querySelector(`[name='sufixo_por_arquivo_${index}']`)?.value ||
              "",
          );
          if (perFile) await saveTriagemSufixo(tipoArquivo, perFile);
          fd.append("sufixo_por_arquivo[]", perFile || sufixo);
        }
        if (sufixo) fd.append("sufixo", sufixo);
      }

      const response = await fetch(`${FLOWDRIVE_BASE}upload.php`, {
        method: "POST",
        body: fd,
      });
      const json = await response.json();
      const success = Array.isArray(json.success) ? json.success.length : 0;
      const errors = Array.isArray(json.errors)
        ? json.errors.filter(Boolean)
        : [];
      if (!response.ok || (success === 0 && errors.length)) {
        throw new Error(errors[0] || "Upload não concluído.");
      }
      toast(
        `${plural(success, "arquivo enviado", "arquivos enviados")}.`,
        "#22C55E",
      );
      close();
    } catch (err) {
      toast("Erro no upload: " + err.message, "#EF4444", 6000);
      button.disabled = false;
    } finally {
      button.innerHTML = '<i class="fa-solid fa-upload"></i> Enviar arquivos';
    }
  }

  async function salvarAlteracoesModal() {
    const itens = getDirtyItemPayloads();
    if (!itens.length) return;
    const { obraId, cardId } = getCurrentPreAltDraftContext();

    if (
      itens.some(
        (item) => item.resultado === "ALTERACAO" && !item.nivel_complexidade,
      )
    ) {
      toast(
        "Selecione o nível de complexidade das imagens com alteração.",
        "#F59E0B",
      );
      return;
    }

    const btn = refs.btnSalvarAlteracoes;
    btn.disabled = true;
    btn.innerHTML =
      '<i class="fa-solid fa-spinner fa-spin"></i><span>Salvando</span>';

    try {
      const res = await fetch(BASE + "save_pre_analise_batch.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ itens }),
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error || "Erro ao salvar");
      toast(
        json.ready_for_planning
          ? "Lote pronto para planejamento."
          : `${plural(json.updated_items || 0, "imagem atualizada", "imagens atualizadas")}.`,
        "#22C55E",
      );
      limparRascunhoPreAlt(obraId, cardId);
      await abrirModal(state.loteAberto?.lote_id);
      await carregarLotes();
    } catch (err) {
      toast("Erro: " + err.message, "#EF4444");
    } finally {
      btn.innerHTML =
        '<i class="fa-solid fa-floppy-disk"></i><span>Salvar alterações</span>';
      updateModalDirtyState();
    }
  }

  function buildResumo(itens) {
    const resumo = {
      total: itens.length,
      comentarios: 0,
      criticos: 0,
      alteracao: 0,
      aguardando: 0,
      classificados: 0,
      progresso: 0,
    };
    itens.forEach((item) => {
      resumo.comentarios += Number(
        item.comment_count || item.quantidade_comentarios || 0,
      );
      resumo.criticos += Number(item.critical_count || 0);
      if (item.resultado === "ALTERACAO") resumo.alteracao += 1;
      if (
        item.resultado === "AGUARDANDO_CLIENTE" ||
        item.necessita_retorno == 1
      )
        resumo.aguardando += 1;
      if (
        item.resultado &&
        (item.resultado !== "ALTERACAO" || item.nivel_complexidade)
      )
        resumo.classificados += 1;
    });
    resumo.progresso = resumo.total
      ? Math.round((resumo.classificados / resumo.total) * 100)
      : 0;
    return resumo;
  }

  function summaryTile(value, label) {
    return `<div class="summary-tile"><strong>${escHtml(value)}</strong><span>${escHtml(label)}</span></div>`;
  }

  function toggleSelection(loteId, checked) {
    if (checked) state.selected.add(loteId);
    else state.selected.delete(loteId);
    syncSelectionUi();
  }

  function syncSelectionUi() {
    refs.selectedCount.textContent = state.selected.size;
    refs.batchActionBar.classList.toggle("is-visible", state.selected.size > 0);
    document.querySelectorAll(".triage-card").forEach((card) => {
      const id = Number(card.dataset.loteId);
      const checked = state.selected.has(id);
      card.classList.toggle("is-selected", checked);
      const input = card.querySelector(".select-card input");
      if (input) input.checked = checked;
    });
  }

  function clearSelection() {
    state.selected.clear();
    syncSelectionUi();
  }

  function openBatchModal(action, loteIdsOverride = null) {
    const loteIds = loteIdsOverride || Array.from(state.selected);
    if (!loteIds.length) {
      toast("Selecione ao menos um lote.", "#F59E0B");
      return;
    }
    if (action === "concluir") {
      if (loteIds.length !== 1) {
        toast(
          "Selecione um lote por vez para concluir a triagem.",
          "#F59E0B",
          3600,
        );
        return;
      }
      abrirConclusaoLote(loteIds[0]);
      return;
    }
    state.currentBatchAction = action;
    state.currentBatchLoteIds = loteIds;
    refs.batchModal.classList.add("is-open");
    refs.batchModal.setAttribute("aria-hidden", "false");
    refs.batchModalTitle.textContent = batchTitle(action);
    refs.batchModalBody.innerHTML = batchBody(action, loteIds.length);
  }

  function batchTitle(action) {
    return {
      responsavel: "Alterar responsável",
      prazo: "Alterar prazo",
      prioridade: "Alterar prioridade",
      status: "Mover etapa",
      concluir: "Concluir triagem",
    }[action];
  }

  function batchBody(action, totalLotes) {
    if (action === "responsavel") {
      return `
        <label><span>Responsável</span><select name="value" required>
          <option value="">Selecione</option>
          ${state.responsaveis.map((r) => `<option value="${r.idcolaborador}">${escHtml(r.nome_colaborador)}</option>`).join("")}
        </select></label>
      `;
    }
    if (action === "prazo") {
      return '<label><span>Novo prazo</span><input type="date" name="value" required></label>';
    }
    if (action === "prioridade") {
      return `
        <label><span>Prioridade</span><select name="value" required>
          <option value="BAIXA">Baixa</option>
          <option value="NORMAL">Normal</option>
          <option value="ALTA">Alta</option>
          <option value="CRITICA">Crítica</option>
        </select></label>
      `;
    }
    if (action === "status") {
      return `
        <label><span>Etapa</span><select name="value" required>
          <option value="EM_TRIAGEM">Em triagem</option>
          <option value="AGUARDANDO_CLIENTE">Aguardando cliente</option>
          <option value="PRONTO_PLANEJAMENTO">Para planejamento</option>
        </select></label>
      `;
    }
    return `<p class="batch-confirm">Concluir ${totalLotes} lote${totalLotes === 1 ? "" : "s"} e mover para planejamento.</p>`;
  }

  async function submitBatch(event) {
    event.preventDefault();
    const action = state.currentBatchAction;
    const field = refs.batchForm.querySelector("[name='value']");
    const payload = {
      lote_ids: state.currentBatchLoteIds || Array.from(state.selected),
      action,
      value: field ? field.value : null,
      observacao: "Atualização em lote pela tela de Pré-Alteração.",
    };

    try {
      const res = await fetch(BASE + "batch_update_lotes.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error || "Erro na atualização");
      toast(`${json.updated || 0} lote(s) atualizados.`, "#22C55E");
      closeBatchModal();
      clearSelection();
      await carregarLotes();
    } catch (err) {
      toast("Erro: " + err.message, "#EF4444");
    }
  }

  async function abrirConclusaoLote(loteId, dataTriagem = null) {
    closeBatchModal();
    refs.conclusaoModal.classList.add("is-open");
    refs.conclusaoModal.setAttribute("aria-hidden", "false");
    refs.conclusaoTitle.textContent = "Carregando";
    refs.conclusaoFooterInfo.textContent = "Revise os prazos antes de liberar.";
    refs.conclusaoSubmit.disabled = true;
    state.conclusaoResumo = null;
    refs.conclusaoBody.innerHTML =
      '<div class="modal-loading"><i class="fa-solid fa-spinner fa-spin"></i> Carregando resumo</div>';

    try {
      const url = new URL(
        BASE + "get_conclusao_lote.php",
        window.location.origin,
      );
      url.searchParams.set("lote_id", loteId);
      if (dataTriagem) url.searchParams.set("data_triagem", dataTriagem);
      const res = await fetch(url.toString());
      const json = await res.json();
      if (!json.success)
        throw new Error(json.message || "Erro ao carregar resumo");
      state.conclusaoResumo = json;
      renderConclusaoResumo(json);
    } catch (err) {
      refs.conclusaoTitle.textContent = "Resumo indisponível";
      refs.conclusaoBody.innerHTML = `<div class="modal-loading is-error">${escHtml(err.message)}</div>`;
      refs.conclusaoSubmit.disabled = true;
    }
  }

  function renderConclusaoResumo(resumo) {
    const lote = resumo.lote || {};
    const totais = resumo.totais || {};
    const grupoEf = resumo.grupos?.ef || {};
    const grupoAlt = resumo.grupos?.alteracao || {};
    const niveis = totais.niveis || {};
    const canSubmit = Boolean(resumo.eligible);

    refs.conclusaoTitle.textContent = lote.obra_nome || "Resumo do lote";
    refs.conclusaoSubmit.disabled = !canSubmit;
    refs.conclusaoFooterInfo.textContent = canSubmit
      ? `${plural(totais.imagens || 0, "imagem pronta", "imagens prontas")} para liberação.`
      : "Resolva as pendências antes de liberar o lote.";

    const pendenciasHtml = canSubmit
      ? ""
      : `
        <section class="conclusao-pendencias">
          <strong>Conclusão bloqueada</strong>
          ${(resumo.pendencias || []).map((item) => `<span>${escHtml(item)}</span>`).join("")}
        </section>
      `;

    const efHtml =
      Number(grupoEf.total || 0) > 0
        ? entregaResumo("Entrega EF", grupoEf, "prazo_ef")
        : "";
    const altHtml =
      Number(grupoAlt.total || 0) > 0
        ? entregaResumo("Entrega Alteração", grupoAlt, "prazo_alteracao")
        : "";

    refs.conclusaoBody.innerHTML = `
      <section class="conclusao-headline">
        <div>
          <span>Cliente</span>
          <strong>${escHtml(lote.cliente_nome || "Cliente não informado")}</strong>
        </div>
        <div>
          <span>Resolvido pelo cliente</span>
          <strong>${escHtml(formatDate(lote.data_resolvida_cliente) || "Sem registro")}</strong>
        </div>
        <label>
          <span>Data da triagem</span>
          <input type="date" name="data_triagem" value="${escHtml(resumo.data_triagem || dateOnly(new Date().toISOString()))}" required>
        </label>
      </section>

      <section class="conclusao-totals">
        ${summaryTile(totais.imagens || 0, "Total de imagens")}
        ${summaryTile(totais.aprovadas || 0, "Aprovadas")}
        ${summaryTile(totais.alteracoes || 0, "Com alteração")}
        ${summaryTile(`N1 ${niveis[1] || 0}`, "Complexidade")}
        ${summaryTile(`N2 ${niveis[2] || 0}`, "Complexidade")}
        ${summaryTile(`N3 ${niveis[3] || 0}`, "Complexidade")}
        ${summaryTile(`N4 ${niveis[4] || 0}`, "Complexidade")}
        ${summaryTile(`N5 ${niveis[5] || 0}`, "Complexidade")}
      </section>

      ${pendenciasHtml}

      <section class="conclusao-entregas">
        ${efHtml}
        ${altHtml}
      </section>

      <label class="conclusao-observacao">
        <span>Observação</span>
        <textarea name="observacao" rows="3" placeholder="Observação opcional para histórico e entregas"></textarea>
      </label>
    `;

    refs.conclusaoBody
      .querySelector("[name='data_triagem']")
      ?.addEventListener("change", (event) => {
        const loteId = state.conclusaoResumo?.lote?.id;
        if (loteId && event.currentTarget.value) {
          abrirConclusaoLote(loteId, event.currentTarget.value);
        }
      });
  }

  function entregaResumo(titulo, grupo, inputName) {
    const niveis = grupo.niveis || {};
    const nivelHtml =
      inputName === "prazo_alteracao"
        ? `<div class="conclusao-niveis">${[1, 2, 3, 4, 5].map((n) => `<span>N${n} <strong>${niveis[n] || 0}</strong></span>`).join("")}</div>`
        : "";
    return `
      <article class="conclusao-entrega-card">
        <div>
          <span>${escHtml(titulo)}</span>
          <strong>${escHtml(grupo.status_nome || "Etapa")} - ${plural(grupo.total || 0, "imagem", "imagens")}</strong>
        </div>
        ${nivelHtml}
        <label>
          <span>Prazo</span>
          <input type="date" name="${escHtml(inputName)}" value="${escHtml(grupo.prazo || "")}" required>
        </label>
      </article>
    `;
  }

  async function submitConclusao(event) {
    event.preventDefault();
    const resumo = state.conclusaoResumo;
    if (!resumo || !resumo.eligible) {
      toast("Este lote ainda possui pendências.", "#F59E0B");
      return;
    }

    const form = refs.conclusaoForm;
    const payload = {
      lote_id: resumo.lote.id,
      data_triagem:
        form.querySelector("[name='data_triagem']")?.value ||
        resumo.data_triagem,
      prazo_ef: form.querySelector("[name='prazo_ef']")?.value || "",
      prazo_alteracao:
        form.querySelector("[name='prazo_alteracao']")?.value || "",
      observacao: form.querySelector("[name='observacao']")?.value || "",
    };

    refs.conclusaoSubmit.disabled = true;
    refs.conclusaoSubmit.innerHTML =
      '<i class="fa-solid fa-spinner fa-spin"></i> Liberando';
    try {
      const res = await fetch(BASE + "concluir_triagem.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const json = await res.json();
      if (!json.success)
        throw new Error(json.message || "Erro ao concluir triagem");
      toast("Triagem concluída e lote liberado.", "#22C55E");
      closeConclusaoModal();
      closePaModal();
      clearSelection();
      await carregarLotes();
    } catch (err) {
      toast("Erro: " + err.message, "#EF4444", 5200);
      refs.conclusaoSubmit.disabled = false;
    } finally {
      refs.conclusaoSubmit.innerHTML =
        '<i class="fa-solid fa-circle-check"></i> Concluir e liberar lote';
    }
  }

  function closeConclusaoModal() {
    refs.conclusaoModal.classList.remove("is-open");
    refs.conclusaoModal.setAttribute("aria-hidden", "true");
    state.conclusaoResumo = null;
  }

  function closeBatchModal() {
    refs.batchModal.classList.remove("is-open");
    refs.batchModal.setAttribute("aria-hidden", "true");
    state.currentBatchAction = null;
    state.currentBatchLoteIds = null;
  }

  function closePaModal() {
    salvarRascunhoAtualPreAlt();
    refs.paModal.classList.remove("is-open");
    refs.paModal.setAttribute("aria-hidden", "true");
    state.itensAbertos = [];
    state.interacoesCliente = [];
    state.clientItemSelection.clear();
    updateModalDirtyState();
  }

  function resultadoLabel(resultado) {
    if (resultado === "SEM_ALTERACAO") return "Aprovada";
    if (resultado === "AGUARDANDO_CLIENTE") return "Aguardando cliente";
    return "Alteração";
  }

  function resultadoFilterValue(resultado) {
    if (resultado === "SEM_ALTERACAO") return "aprovada";
    if (resultado === "AGUARDANDO_CLIENTE") return "aguardando_cliente";
    return "alteracao";
  }

  function resultadoBadgeClass(resultado) {
    if (resultado === "SEM_ALTERACAO") return "aprovada";
    if (resultado === "AGUARDANDO_CLIENTE") return "aguardando-cliente";
    return "alteracao";
  }

  function formatDate(str) {
    if (!str) return "";
    const [datePart] = String(str).split(" ");
    const [y, m, d] = datePart.split("-");
    return y && m && d ? `${d}/${m}/${y}` : str;
  }

  function formatDateTime(str) {
    if (!str) return "Sem registro";
    const [datePart, timePart = ""] = String(str).split(" ");
    const date = formatDate(datePart);
    const time = timePart.slice(0, 5);
    return time ? `${date} ${time}` : date;
  }

  function parseDate(str) {
    if (!str) return null;
    const [y, m, d] = String(str).split("-");
    if (!y || !m || !d) return null;
    return new Date(Number(y), Number(m) - 1, Number(d));
  }

  function dateOnly(str) {
    return str ? String(str).split(/[ T]/)[0] : "";
  }

  function plural(value, singular, pluralLabel) {
    const total = Number(value || 0);
    return `${total} ${total === 1 ? singular : pluralLabel}`;
  }

  function escHtml(str) {
    return String(str ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function toast(text, background, duration = 2600) {
    Toastify({
      text,
      duration,
      gravity: "top",
      position: "right",
      style: { background },
    }).showToast();
  }

  [
    refs.filtroBusca,
    refs.filtroObra,
    refs.filtroCliente,
    refs.filtroStatus,
    refs.filtroPrioridade,
    refs.filtroResponsavel,
    refs.filtroPrazo,
    refs.filtroData,
  ].forEach((el) => el.addEventListener("input", renderLotes));

  refs.btnAtualizar.addEventListener("click", carregarLotes);
  refs.btnBatchMode.addEventListener("click", () =>
    toast(
      "Selecione os checkboxes dos lotes para atualizar em lote.",
      "#3B82F6",
    ),
  );
  // refs.btnNovoLote.addEventListener("click", () => toast("Novos lotes são criados ao enviar um lote concluído do Review para Pré-Alteração.", "#8B5CF6", 4200));
  // refs.btnMaisFiltros.addEventListener("click", () => {
  //   document.body.classList.toggle("show-advanced-filters");
  // });
  refs.btnLimparSelecao.addEventListener("click", clearSelection);
  refs.batchActionBar.querySelectorAll("[data-batch-action]").forEach((btn) => {
    btn.addEventListener("click", () =>
      openBatchModal(btn.dataset.batchAction),
    );
  });
  refs.paModalClose.addEventListener("click", closePaModal);
  refs.paFooterClose.addEventListener("click", closePaModal);
  refs.btnSalvarAlteracoes.addEventListener("click", salvarAlteracoesModal);
  // refs.paModal.addEventListener("click", (event) => {
  //   if (event.target === refs.paModal) closePaModal();
  // });
  refs.batchModalClose.addEventListener("click", closeBatchModal);
  refs.batchCancel.addEventListener("click", closeBatchModal);
  refs.batchModal.addEventListener("click", (event) => {
    if (event.target === refs.batchModal) closeBatchModal();
  });
  refs.batchForm.addEventListener("submit", submitBatch);
  refs.conclusaoModalClose.addEventListener("click", closeConclusaoModal);
  refs.conclusaoCancel.addEventListener("click", closeConclusaoModal);
  refs.conclusaoModal.addEventListener("click", (event) => {
    if (event.target === refs.conclusaoModal) closeConclusaoModal();
  });
  refs.conclusaoForm.addEventListener("submit", submitConclusao);
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closePaModal();
      closeBatchModal();
      closeConclusaoModal();
    }
  });

  carregarLotes();
})();
