/**
 * AdminMetas/js/app.js
 *
 * Módulo de administração de metas por colaborador.
 * - Carrega dados via AJAX
 * - Renderiza accordions por função
 * - Rastreia alterações inline
 * - Envia apenas metas alteradas/novas ao salvar
 */

(function () {
  "use strict";

  const SAVE_BUTTON_IDS = ["btnSalvar", "btnSalvarFooter"];
  const FILTER_SHEET_MAX_WIDTH = 1366;
  const FINALIZACAO_LEVELS = {
    1: { label: "Heartstarter", title: "Nível 1 - Heartstarter" },
    2: { label: "Heartmaker", title: "Nível 2 - Heartmaker" },
    3: { label: "Heartmaster", title: "Nível 3 - Heartmaster" },
  };
  const STATUS_META = {
    "no-meta": {
      label: "Sem meta",
      icon: "fa-minus",
      description: "Nenhuma meta cadastrada para este colaborador.",
    },
    below: {
      label: "Abaixo",
      icon: "fa-arrow-down",
      description: "Parcial abaixo da meta atual.",
    },
    hit: {
      label: "Atingida",
      icon: "fa-check",
      description: "Parcial exatamente na meta atual.",
    },
    over: {
      label: "Superada",
      icon: "fa-arrow-up",
      description: "Parcial acima da meta atual.",
    },
    record: {
      label: "Recorde",
      icon: "fa-trophy",
      description: "Parcial atingiu ou superou o recorde individual.",
    },
  };

  const state = {
    mes: window.APP_MES,
    ano: window.APP_ANO,
    data: null,
    openFuncaoId: null,
    changes: new Map(),
    funcaoFilter: "",
    searchTerm: "",
  };

  function init() {
    bindFilters();
    bindActions();
    bindFilterSheet();
    initTooltip();
    atualizarBotaoSalvar();
    syncSearchClearButton();
    carregarDados();
  }

  function bindFilters() {
    const btnAplicar = document.getElementById("btnAplicar");
    const selMes = document.getElementById("selMes");
    const selAno = document.getElementById("selAno");
    const selFuncao = document.getElementById("selFuncao");
    const searchInput = document.getElementById("searchColaborador");
    const btnLimparBusca = document.getElementById("btnLimparBusca");

    if (btnAplicar) {
      btnAplicar.addEventListener("click", aplicarFiltro);
    }

    [selMes, selAno].forEach((select) => {
      if (!select) return;
      select.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
          aplicarFiltro();
        }
      });
    });

    if (selFuncao) {
      selFuncao.addEventListener("change", () => {
        state.funcaoFilter = selFuncao.value;
        renderFuncoes();
      });
    }

    if (searchInput) {
      searchInput.addEventListener("input", () => {
        state.searchTerm = searchInput.value.trim();
        syncSearchClearButton();
        renderFuncoes();
      });

      searchInput.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && searchInput.value) {
          event.preventDefault();
          clearSearch();
        }
      });
    }

    if (btnLimparBusca) {
      btnLimparBusca.addEventListener("click", clearSearch);
    }
  }

  function bindActions() {
    SAVE_BUTTON_IDS.forEach((id) => {
      const button = document.getElementById(id);
      if (button) {
        button.addEventListener("click", salvarMetas);
      }
    });

    const btnDescartar = document.getElementById("btnDescartar");
    if (btnDescartar) {
      btnDescartar.addEventListener("click", descartarAlteracoes);
    }
  }

  function bindFilterSheet() {
    const btnOpenFilters = document.getElementById("btnOpenFilters");
    const btnCloseFilters = document.getElementById("btnCloseFilters");
    const backdrop = document.getElementById("filtersBackdrop");

    if (btnOpenFilters) {
      btnOpenFilters.addEventListener("click", openFilterSheet);
    }

    if (btnCloseFilters) {
      btnCloseFilters.addEventListener("click", () => closeFilterSheet());
    }

    if (backdrop) {
      backdrop.addEventListener("click", () => closeFilterSheet());
    }

    window.addEventListener("resize", syncFilterSheetViewport);
    document.addEventListener("keydown", onDocumentKeydown);
    syncFilterSheetViewport();
  }

  function onDocumentKeydown(event) {
    if (event.key !== "Escape" || !isFilterSheetOpen()) return;

    const searchInput = document.getElementById("searchColaborador");
    if (
      searchInput &&
      document.activeElement === searchInput &&
      searchInput.value.trim()
    ) {
      return;
    }

    closeFilterSheet();
  }

  function isCompactFiltersViewport() {
    return window.innerWidth <= FILTER_SHEET_MAX_WIDTH;
  }

  function isFilterSheetOpen() {
    return document.body.classList.contains("filters-sheet-open");
  }

  function setFilterSheetOpen(isOpen) {
    const filtersPanel = document.getElementById("filtersPanel");
    const backdrop = document.getElementById("filtersBackdrop");
    const btnOpenFilters = document.getElementById("btnOpenFilters");

    document.body.classList.toggle("filters-sheet-open", isOpen);

    if (filtersPanel) {
      filtersPanel.classList.toggle("is-sheet-open", isOpen);
      filtersPanel.setAttribute(
        "aria-hidden",
        isCompactFiltersViewport() ? String(!isOpen) : "false",
      );
    }

    if (backdrop) {
      backdrop.classList.toggle("is-visible", isOpen);
      backdrop.setAttribute("aria-hidden", String(!isOpen));
    }

    if (btnOpenFilters) {
      btnOpenFilters.setAttribute("aria-expanded", String(isOpen));
    }
  }

  function openFilterSheet() {
    if (!isCompactFiltersViewport()) return;

    setFilterSheetOpen(true);
  }

  function closeFilterSheet(options = {}) {
    const { restoreFocus = true } = options;
    const btnOpenFilters = document.getElementById("btnOpenFilters");

    setFilterSheetOpen(false);

    if (restoreFocus && btnOpenFilters && isCompactFiltersViewport()) {
      btnOpenFilters.focus();
    }
  }

  function syncFilterSheetViewport() {
    if (!isCompactFiltersViewport()) {
      closeFilterSheet({ restoreFocus: false });
      return;
    }

    const filtersPanel = document.getElementById("filtersPanel");
    if (filtersPanel && !isFilterSheetOpen()) {
      filtersPanel.setAttribute("aria-hidden", "true");
    }
  }

  function clearSearch() {
    const input = document.getElementById("searchColaborador");
    if (!input) return;
    input.value = "";
    state.searchTerm = "";
    syncSearchClearButton();
    renderFuncoes();
    input.focus();
  }

  function aplicarFiltro() {
    const mes = parseInt(document.getElementById("selMes").value, 10);
    const ano = parseInt(document.getElementById("selAno").value, 10);

    if (hasPendingChanges()) {
      Swal.fire({
        title: "Alterações pendentes",
        text: "Ao mudar o período, as alterações não salvas serão descartadas. Continuar?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Continuar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#6ea8ff",
        background: "#0f1728",
        color: "#eef2ff",
      }).then((result) => {
        if (result.isConfirmed) {
          mudarPeriodo(mes, ano);
        }
      });
      return;
    }

    mudarPeriodo(mes, ano);
  }

  function mudarPeriodo(mes, ano) {
    closeFilterSheet({ restoreFocus: false });
    state.mes = mes;
    state.ano = ano;
    state.changes.clear();
    state.openFuncaoId = null;
    atualizarBotaoSalvar();
    carregarDados();
  }

  function carregarDados() {
    renderSkeleton();

    fetch(`backend/carregar_dados.php?mes=${state.mes}&ano=${state.ano}`)
      .then((response) => {
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
      })
      .then((data) => {
        if (!data.success) throw new Error(data.error || "Erro desconhecido");
        state.data = data;
        syncFuncaoSelect(data.funcoes);
        renderFuncoes();
      })
      .catch((err) => {
        document.getElementById("listaAcordoes").innerHTML = `
          <div class="alert-box danger">
            <i class="fa-solid fa-circle-xmark"></i>
            <div>Erro ao carregar dados: ${escHtml(err.message)}</div>
          </div>`;
        updateResultsSummary("0");
      });
  }

  function renderSkeleton() {
    const list = document.getElementById("listaAcordoes");
    if (!list) return;

    let html = "";
    for (let index = 0; index < 6; index += 1) {
      html += '<div class="skeleton-card"></div>';
    }

    list.innerHTML = html;
    updateResultsSummary("…");
  }

  function syncFuncaoSelect(funcoes) {
    const select = document.getElementById("selFuncao");
    if (!select) return;

    const hasCurrent = funcoes.some(
      (funcao) => String(funcao.funcao_id) === String(state.funcaoFilter),
    );

    if (!hasCurrent) {
      state.funcaoFilter = "";
    }

    let options = '<option value="">Todas</option>';
    funcoes.forEach((funcao) => {
      options += `<option value="${funcao.funcao_id}">${escHtml(funcao.nome_funcao)}</option>`;
    });

    select.innerHTML = options;
    select.value = state.funcaoFilter;
  }

  function renderFuncoes() {
    const list = document.getElementById("listaAcordoes");
    if (!list) return;

    const funcoes = getVisibleFuncoes();
    const totalVisible = funcoes.reduce(
      (sum, funcao) => sum + funcao.visibleColaboradores.length,
      0,
    );

    updateResultsSummary(totalVisible);

    if (!funcoes.length) {
      state.openFuncaoId = null;
      list.innerHTML = buildEmptyState(
        state.searchTerm || state.funcaoFilter
          ? "Nenhum colaborador encontrado para os filtros atuais."
          : "Nenhuma função disponível para o período selecionado.",
      );
      return;
    }

    list.innerHTML = "";
    const fragment = document.createDocumentFragment();

    funcoes.forEach((funcao) => {
      fragment.appendChild(criarAcordao(funcao));
    });

    list.appendChild(fragment);
    restoreOpenAccordion(funcoes);
  }

  function getVisibleFuncoes() {
    if (!state.data || !Array.isArray(state.data.funcoes)) return [];

    const normalizedSearch = normalizeText(state.searchTerm);

    return state.data.funcoes
      .filter((funcao) => {
        if (!state.funcaoFilter) return true;
        return String(funcao.funcao_id) === String(state.funcaoFilter);
      })
      .map((funcao) => {
        const visibleColaboradores = normalizedSearch
          ? funcao.colaboradores.filter((colaborador) =>
              normalizeText(colaborador.nome).includes(normalizedSearch),
            )
          : funcao.colaboradores.slice();

        return {
          ...funcao,
          visibleColaboradores,
        };
      })
      .filter((funcao) => {
        if (!normalizedSearch) return true;
        return funcao.visibleColaboradores.length > 0;
      });
  }

  function criarAcordao(funcao) {
    const wrapper = document.createElement("section");
    wrapper.className = "accordion-funcao";
    wrapper.dataset.funcaoId = String(funcao.funcao_id);
    wrapper.classList.toggle(
      "has-pending",
      getPendingCountForFuncao(funcao.funcao_id) > 0,
    );

    const header = document.createElement("button");
    header.className = "accordion-header";
    header.type = "button";
    header.setAttribute("aria-expanded", "false");
    header.innerHTML = buildHeaderHTML(funcao);
    header.addEventListener("click", () => toggleAcordao(funcao.funcao_id));

    const body = document.createElement("div");
    body.className = "accordion-body";
    body.id = `body-${funcao.funcao_id}`;
    body.innerHTML = buildBodyHTML(funcao);

    wrapper.appendChild(header);
    wrapper.appendChild(body);

    return wrapper;
  }

  function buildHeaderHTML(funcao) {
    const totalColaboradores = funcao.colaboradores.length;
    const visiveis = funcao.visibleColaboradores.length;
    const countLabel =
      state.searchTerm && visiveis !== totalColaboradores
        ? `${visiveis}/${totalColaboradores} colaboradores`
        : `${totalColaboradores} ${totalColaboradores === 1 ? "colaborador" : "colaboradores"}`;

    const recordeEquipeAtingido =
      funcao.recorde_equipe > 0 &&
      funcao.total_parcial >= funcao.recorde_equipe;

    return `
      <div class="accordion-header-main">
        <div class="header-identity">
          <span class="expand-icon"><i class="fa-solid fa-chevron-right"></i></span>
          <span class="funcao-dot" style="background:${funcao.cor}"></span>
          <div class="funcao-copy">
            <div class="funcao-row">
              <span class="funcao-nome">${escHtml(funcao.nome_funcao)}</span>
              ${recordeEquipeAtingido ? `<span class="mini-flag record-flag"${funcao.recorde_equipe_mes ? ` data-tooltip="Recorde da equipe em ${escHtml(formatPeriodo(funcao.recorde_equipe_mes))}"` : ""}>Recorde</span>` : ""}
            </div>
            <div class="funcao-meta-row">
              <span class="funcao-count">${countLabel}</span>
              <span data-role="funcao-pending">${buildFuncaoPendingHTML(funcao.funcao_id)}</span>
            </div>
          </div>
        </div>
      </div>
      <div class="accordion-header-stats">
        ${buildStatChip("Parcial", funcao.total_parcial)}
        ${buildStatChip("Mês ant.", funcao.total_anterior)}
        ${buildStatChip("Recorde", funcao.recorde_equipe, funcao.recorde_equipe_mes ? `Recorde em ${formatPeriodo(funcao.recorde_equipe_mes)}` : null)}
      </div>
      <span class="accordion-chevron"><i class="fa-solid fa-angle-right"></i></span>`;
  }

  function buildStatChip(label, value, tooltip) {
    const tooltipAttr = tooltip ? ` data-tooltip="${escHtml(tooltip)}"` : "";
    return `
      <div class="stat-chip"${tooltipAttr}>
        <span class="stat-label">${label}</span>
        <strong class="stat-val">${value}</strong>
      </div>`;
  }

  function buildFuncaoPendingHTML(funcaoId) {
    const count = getPendingCountForFuncao(funcaoId);
    if (count <= 0) return "";
    return `<span class="mini-flag changed-flag">${count} ${count === 1 ? "alteração" : "alterações"}</span>`;
  }

  function buildBodyHTML(funcao) {
    if (!funcao.visibleColaboradores.length) {
      return '<div class="empty-state"><i class="fa-solid fa-magnifying-glass"></i><strong>Nenhum colaborador encontrado</strong><span>Tente refinar a busca ou limpar os filtros locais.</span></div>';
    }

    const rows = funcao.visibleColaboradores
      .map((colaborador) => buildRowHTML(funcao, colaborador))
      .join("");

    return `
      <div class="accordion-body-inner">
        <table class="colab-table">
          <thead>
            <tr>
              <th>Colaborador</th>
              <th>Parcial atual</th>
              <th class="col-center">Mês anterior</th>
              <th class="col-center">Recorde</th>
              <th class="col-center">Meta</th>
              <th class="col-center">Meta sugerida</th>
              <th class="col-center">Status</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
        <div class="accordion-body-footer" data-role="funcao-footer">${buildFuncaoFooterHTML(funcao.funcao_id)}</div>
      </div>`;
  }

  function buildFuncaoFooterHTML(funcaoId) {
    const pendingCount = getPendingCountForFuncao(funcaoId);
    if (pendingCount > 0) {
      return `<span><i class="fa-regular fa-pen-to-square"></i> ${pendingCount} ${pendingCount === 1 ? "alteração pendente" : "alterações pendentes"} nesta função</span>`;
    }

    return '<span><i class="fa-regular fa-keyboard"></i> Pressione Enter para avançar ao próximo input da tabela.</span>';
  }

  function buildRowHTML(funcao, colaborador) {
    const funcaoId = funcao.funcao_id;
    const key = buildChangeKey(funcaoId, colaborador.colaborador_id);
    const metaAtual = getCurrentMeta(funcaoId, colaborador);
    const isDirty = state.changes.has(key);
    const nivelFinalizacaoBadge = buildNivelFinalizacaoBadge(
      funcaoId,
      colaborador,
    );
    const status = getStatusMeta(
      colaborador.parcial,
      metaAtual,
      colaborador.recorde,
    );
    const progress = getProgressData(
      colaborador.parcial,
      metaAtual,
      colaborador.recorde,
    );
    const sugestao = getSuggestedMeta(colaborador, metaAtual);

    return `
      <tr class="${isDirty ? "row-dirty" : ""}" data-key="${key}">
        <td class="colab-cell" data-label="Colaborador">
          <div class="colab-identity">
            <span class="avatar-token">${buildAvatarContent(colaborador)}</span>
            <div class="colab-meta">
              <div class="colab-headline">
                <strong class="colab-name">${escHtml(colaborador.nome)}</strong>
                ${nivelFinalizacaoBadge}
              </div>
              <span class="colab-submeta">${buildRecordHint(colaborador.recorde_mes)}</span>
            </div>
          </div>
        </td>
        <td data-role="parcial" data-label="Parcial atual">${buildPartialMetricHTML(colaborador.parcial, progress)}</td>
        <td class="col-center" data-label="Mês anterior">${colaborador.mes_anterior}</td>
        <td class="col-center" data-label="Recorde">${colaborador.recorde > 0 ? `<span data-tooltip="${escHtml(colaborador.recorde_mes ? `Recorde em ${formatPeriodo(colaborador.recorde_mes)}` : "Recorde registrado")}">${colaborador.recorde}</span>` : "—"}</td>
        <td class="col-center meta-cell" data-label="Meta">
          <div class="meta-editor ${isDirty ? "is-dirty" : ""}">
            <input
              type="number"
              class="meta-input ${isDirty ? "dirty" : ""}"
              min="0"
              inputmode="numeric"
              placeholder="—"
              aria-label="Meta de ${escHtml(colaborador.nome)}"
              value="${metaAtual === null ? "" : metaAtual}"
              data-original="${colaborador.meta === null ? "" : colaborador.meta}"
              data-funcao-id="${funcaoId}"
              data-colab-id="${colaborador.colaborador_id}">
            <span class="change-badge" ${isDirty ? "" : "hidden"}>Alterado</span>
          </div>
        </td>
        <td class="col-center suggestion-cell" data-role="suggestion" data-label="Meta sugerida">${buildSuggestionHTML(sugestao)}</td>
        <td class="col-center status-cell" data-role="status" data-label="Status">${buildStatusHTML(status)}</td>
      </tr>`;
  }

  function buildAvatarContent(colaborador) {
    if (colaborador.foto_colaborador) {
      return `<img src="https://improov.com.br/flow/ImproovWeb/${escHtml(colaborador.foto_colaborador)}" alt="${escHtml(colaborador.nome)}" loading="lazy">`;
    }
    return escHtml(getInitials(colaborador.nome));
  }

  function buildRecordHint(recordeMes) {
    if (!recordeMes) return "Sem recorde registrado";
    return `Recorde em ${formatPeriodo(recordeMes)}`;
  }

  function buildPartialMetricHTML(parcial, progress) {
    return `
      <div class="metric-stack">
        <div class="metric-top">
          <strong class="metric-number">${parcial}</strong>
          <span class="metric-label">${progress.label}</span>
        </div>
        <div class="metric-bar">
          <span class="metric-bar-fill metric-${progress.tone}" style="width:${progress.width}%"></span>
        </div>
      </div>`;
  }

  function buildSuggestionHTML(sugestao) {
    if (sugestao === null) {
      return '<span class="suggestion-empty">—</span>';
    }

    return `
      <div class="suggestion-stack" title="Base recente: maior valor entre parcial atual, mês anterior e meta atual.">
        <strong class="suggestion-value">${sugestao}</strong>
        <span class="suggestion-label">Base recente</span>
      </div>`;
  }

  function buildStatusHTML(status) {
    const meta = STATUS_META[status.kind];
    return `
      <span class="status-pill status-${status.kind}" title="${escHtml(meta.description)}">
        <i class="fa-solid ${meta.icon}"></i>
        ${meta.label}
      </span>`;
  }

  function toggleAcordao(funcaoId) {
    const body = document.getElementById(`body-${funcaoId}`);
    const header = document.querySelector(
      `[data-funcao-id="${funcaoId}"] .accordion-header`,
    );

    if (!body || !header) return;

    const isOpen = body.classList.contains("is-open");

    if (state.openFuncaoId !== null && state.openFuncaoId !== funcaoId) {
      fecharAcordaoAtivo();
    }

    if (isOpen) {
      fecharBody(body, header);
      state.openFuncaoId = null;
      return;
    }

    abrirBody(body, header, funcaoId);
  }

  function abrirBody(body, header, funcaoId) {
    body.classList.add("is-open");
    body.style.display = "block";
    header.classList.add("open");
    header.setAttribute("aria-expanded", "true");
    state.openFuncaoId = funcaoId;
    bindBodyInputs(body);
  }

  function fecharBody(body, header) {
    body.classList.remove("is-open");
    body.style.display = "none";
    header.classList.remove("open");
    header.setAttribute("aria-expanded", "false");
  }

  function fecharAcordaoAtivo() {
    if (state.openFuncaoId === null) return;

    const prevBody = document.getElementById(`body-${state.openFuncaoId}`);
    const prevHeader = document.querySelector(
      `[data-funcao-id="${state.openFuncaoId}"] .accordion-header`,
    );

    if (prevBody && prevHeader) {
      fecharBody(prevBody, prevHeader);
    }
  }

  function restoreOpenAccordion(funcoes) {
    const targetExists = funcoes.some(
      (funcao) => funcao.funcao_id === state.openFuncaoId,
    );

    const funcaoParaAbrir = targetExists
      ? state.openFuncaoId
      : state.funcaoFilter && funcoes.length === 1
        ? funcoes[0].funcao_id
        : null;

    if (funcaoParaAbrir === null) {
      state.openFuncaoId = null;
      return;
    }

    const body = document.getElementById(`body-${funcaoParaAbrir}`);
    const header = document.querySelector(
      `[data-funcao-id="${funcaoParaAbrir}"] .accordion-header`,
    );

    if (body && header) {
      abrirBody(body, header, funcaoParaAbrir);
    }
  }

  function bindBodyInputs(bodyEl) {
    bodyEl.querySelectorAll(".meta-input").forEach((input) => {
      if (input.dataset.bound) return;

      input.dataset.bound = "1";
      input.addEventListener("input", () => onMetaChange(input));
      input.addEventListener("focus", () => input.select());
      input.addEventListener("keydown", (event) => onMetaKeydown(event, input));
    });
  }

  function onMetaKeydown(event, input) {
    if (event.key !== "Enter") return;

    event.preventDefault();
    focusNextInput(input);
  }

  function focusNextInput(currentInput) {
    const inputs = Array.from(
      document.querySelectorAll(".accordion-body.is-open .meta-input"),
    ).filter((input) => input.offsetParent !== null);

    const currentIndex = inputs.indexOf(currentInput);
    if (currentIndex < 0 || currentIndex === inputs.length - 1) return;

    inputs[currentIndex + 1].focus();
    inputs[currentIndex + 1].select();
  }

  function onMetaChange(input) {
    const funcaoId = parseInt(input.dataset.funcaoId, 10);
    const colabId = parseInt(input.dataset.colabId, 10);
    const key = buildChangeKey(funcaoId, colabId);
    const original = normalizeMetaValue(input.dataset.original);
    const nextValue = normalizeMetaValue(input.value.trim());

    if (nextValue === original) {
      state.changes.delete(key);
      input.classList.remove("dirty");
    } else {
      state.changes.set(key, {
        funcao_id: funcaoId,
        colaborador_id: colabId,
        meta_tarefas: nextValue,
      });
      input.classList.add("dirty");
    }

    refreshRowState(input);
    syncFuncaoPendingUI(funcaoId);
    atualizarBotaoSalvar();
  }

  function refreshRowState(input) {
    const funcaoId = parseInt(input.dataset.funcaoId, 10);
    const colabId = parseInt(input.dataset.colabId, 10);
    const colaborador = findColaborador(funcaoId, colabId);
    const row = input.closest("tr");

    if (!colaborador || !row) return;

    const key = buildChangeKey(funcaoId, colabId);
    const metaAtual = normalizeMetaValue(input.value.trim());
    const isDirty = state.changes.has(key);
    const status = getStatusMeta(
      colaborador.parcial,
      metaAtual,
      colaborador.recorde,
    );
    const progress = getProgressData(
      colaborador.parcial,
      metaAtual,
      colaborador.recorde,
    );
    const sugestao = getSuggestedMeta(colaborador, metaAtual);

    row.classList.toggle("row-dirty", isDirty);

    const editor = row.querySelector(".meta-editor");
    if (editor) {
      editor.classList.toggle("is-dirty", isDirty);
    }

    const changeBadge = row.querySelector(".change-badge");
    if (changeBadge) {
      changeBadge.hidden = !isDirty;
    }

    const partialCell = row.querySelector('[data-role="parcial"]');
    if (partialCell) {
      partialCell.innerHTML = buildPartialMetricHTML(
        colaborador.parcial,
        progress,
      );
    }

    const suggestionCell = row.querySelector('[data-role="suggestion"]');
    if (suggestionCell) {
      suggestionCell.innerHTML = buildSuggestionHTML(sugestao);
    }

    const statusCell = row.querySelector('[data-role="status"]');
    if (statusCell) {
      statusCell.innerHTML = buildStatusHTML(status);
    }
  }

  function syncFuncaoPendingUI(funcaoId) {
    const wrapper = document.querySelector(`[data-funcao-id="${funcaoId}"]`);
    if (!wrapper) return;

    const pending = getPendingCountForFuncao(funcaoId);
    wrapper.classList.toggle("has-pending", pending > 0);

    const pendingSlot = wrapper.querySelector('[data-role="funcao-pending"]');
    if (pendingSlot) {
      pendingSlot.innerHTML = buildFuncaoPendingHTML(funcaoId);
    }

    const footer = wrapper.querySelector('[data-role="funcao-footer"]');
    if (footer) {
      footer.innerHTML = buildFuncaoFooterHTML(funcaoId);
    }
  }

  function hasPendingChanges() {
    return state.changes.size > 0;
  }

  function atualizarBotaoSalvar() {
    const count = state.changes.size;
    const pendingPanel = document.getElementById("pendingPanel");
    const pendingSummary = document.getElementById("pendingSummaryCount");
    const stickyFooter = document.getElementById("stickyFooter");
    const stickyPendingCount = document.getElementById("stickyPendingCount");
    const resultsBadge = document.getElementById("resultsBadge");

    if (pendingSummary) pendingSummary.textContent = String(count);
    if (stickyPendingCount) stickyPendingCount.textContent = String(count);
    if (pendingPanel) pendingPanel.classList.toggle("has-changes", count > 0);
    if (stickyFooter) stickyFooter.style.display = count > 0 ? "flex" : "none";
    if (resultsBadge) {
      resultsBadge.classList.toggle(
        "is-filtered",
        Boolean(state.searchTerm || state.funcaoFilter),
      );
    }

    SAVE_BUTTON_IDS.forEach((id) => {
      const button = document.getElementById(id);
      if (!button) return;
      const isLoading = button.dataset.loading === "1";
      button.classList.toggle("has-changes", count > 0);
      button.disabled = isLoading;
    });

    ["pendingBadge", "pendingBadgeFooter"].forEach((id) => {
      const badge = document.getElementById(id);
      if (!badge) return;

      if (count > 0) {
        badge.textContent = String(count);
        badge.style.display = "inline-flex";
      } else {
        badge.style.display = "none";
      }
    });

    const btnDescartar = document.getElementById("btnDescartar");
    if (btnDescartar) {
      btnDescartar.disabled = count === 0;
    }
  }

  function setSaveButtonsLoading(isLoading) {
    SAVE_BUTTON_IDS.forEach((id) => {
      const button = document.getElementById(id);
      if (!button) return;

      button.dataset.loading = isLoading ? "1" : "0";
      button.disabled = isLoading;
      button.classList.toggle("is-loading", isLoading);

      const icon = button.querySelector("i");
      if (icon) {
        icon.className = isLoading
          ? "fa-solid fa-spinner fa-spin"
          : "fa-solid fa-floppy-disk";
      }

      const label = button.querySelector(".btn-label");
      if (label) {
        label.textContent = isLoading ? "Salvando..." : "Salvar alterações";
      }
    });
  }

  function salvarMetas() {
    if (!hasPendingChanges()) {
      showToast("Nenhuma alteração pendente.", "info");
      return;
    }

    const metas = [];
    state.changes.forEach((change) => {
      if (change.meta_tarefas === null || change.meta_tarefas < 0) return;
      metas.push({
        colaborador_id: change.colaborador_id,
        funcao_id: change.funcao_id,
        meta_tarefas: change.meta_tarefas,
      });
    });

    if (!metas.length) {
      showToast("Nenhuma meta válida para salvar.", "info");
      return;
    }

    setSaveButtonsLoading(true);

    fetch("backend/salvar_metas.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ mes: state.mes, ano: state.ano, metas }),
    })
      .then((response) => {
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
      })
      .then((data) => {
        if (!data.success) {
          throw new Error(data.error || "Erro ao salvar metas.");
        }

        const parts = [];
        if (data.inserted > 0) {
          parts.push(
            `${data.inserted} ${data.inserted === 1 ? "inserida" : "inseridas"}`,
          );
        }
        if (data.updated > 0) {
          parts.push(
            `${data.updated} ${data.updated === 1 ? "atualizada" : "atualizadas"}`,
          );
        }

        showToast(
          parts.length ? `Metas salvas: ${parts.join(", ")}.` : "Metas salvas.",
          "success",
        );

        state.changes.clear();
        atualizarBotaoSalvar();
        carregarDados();
      })
      .catch((err) => {
        showToast(err.message || "Falha ao salvar metas.", "error");
      })
      .finally(() => {
        setSaveButtonsLoading(false);
        atualizarBotaoSalvar();
      });
  }

  function descartarAlteracoes() {
    if (!hasPendingChanges()) return;

    Swal.fire({
      title: "Descartar alterações?",
      text: "As metas editadas voltarão aos valores carregados deste período.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Descartar",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#fb7185",
      background: "#0f1728",
      color: "#eef2ff",
    }).then((result) => {
      if (!result.isConfirmed) return;

      state.changes.clear();
      renderFuncoes();
      atualizarBotaoSalvar();
      showToast("Alterações descartadas.", "info");
    });
  }

  function updateResultsSummary(value) {
    const countEl = document.getElementById("resultsCount");
    if (countEl) {
      countEl.textContent = String(value);
    }
  }

  function syncSearchClearButton() {
    const btn = document.getElementById("btnLimparBusca");
    const input = document.getElementById("searchColaborador");
    if (!btn || !input) return;
    btn.classList.toggle("is-visible", Boolean(input.value));
  }

  function getPendingCountForFuncao(funcaoId) {
    let count = 0;
    state.changes.forEach((change) => {
      if (change.funcao_id === funcaoId) {
        count += 1;
      }
    });
    return count;
  }

  function getCurrentMeta(funcaoId, colaborador) {
    const key = buildChangeKey(funcaoId, colaborador.colaborador_id);
    if (state.changes.has(key)) {
      return state.changes.get(key).meta_tarefas;
    }
    return colaborador.meta === null ? null : colaborador.meta;
  }

  function getSuggestedMeta(colaborador, metaAtual) {
    const candidates = [
      colaborador.parcial,
      colaborador.mes_anterior,
      metaAtual === null ? 0 : metaAtual,
    ].filter((value) => Number.isFinite(value) && value > 0);

    if (!candidates.length) return null;
    return Math.max(...candidates);
  }

  function buildNivelFinalizacaoBadge(funcaoId, colaborador) {
    if (!isFuncaoFinalizacao(funcaoId)) return "";

    const nivelInfo = getNivelFinalizacaoInfo(colaborador.nivel_finalizacao);
    if (!nivelInfo) return "";

    return `
      <span class="finalizacao-badge level-${nivelInfo.level}" title="${escHtml(nivelInfo.title)}">
        ${nivelInfo.label}
      </span>`;
  }

  function isFuncaoFinalizacao(funcaoId) {
    return [4, 7].includes(Number(funcaoId));
  }

  function getNivelFinalizacaoInfo(nivel) {
    const parsedNivel = Number(nivel);
    const info = FINALIZACAO_LEVELS[parsedNivel];
    if (!info) return null;

    return {
      ...info,
      level: parsedNivel,
    };
  }

  function getStatusMeta(parcial, meta, recorde) {
    if (meta === null || meta <= 0) {
      return { kind: "no-meta" };
    }

    if (recorde > 0 && parcial >= recorde) {
      return { kind: "record" };
    }

    if (parcial > meta) {
      return { kind: "over" };
    }

    if (parcial === meta) {
      return { kind: "hit" };
    }

    return { kind: "below" };
  }

  function getProgressData(parcial, meta, recorde) {
    if (meta === null || meta <= 0) {
      return {
        width: 0,
        label: "Sem meta",
        tone: "no-meta",
      };
    }

    const percentualReal = Math.round((parcial / meta) * 100);
    const status = getStatusMeta(parcial, meta, recorde);

    return {
      width: Math.max(8, Math.min(percentualReal, 100)),
      label: `${percentualReal}%`,
      tone: status.kind,
    };
  }

  function findColaborador(funcaoId, colabId) {
    if (!state.data) return null;

    const funcao = state.data.funcoes.find(
      (item) => item.funcao_id === funcaoId,
    );

    if (!funcao) return null;
    return (
      funcao.colaboradores.find(
        (colaborador) => colaborador.colaborador_id === colabId,
      ) || null
    );
  }

  function buildChangeKey(funcaoId, colabId) {
    return `${funcaoId}:${colabId}`;
  }

  function normalizeMetaValue(rawValue) {
    if (
      rawValue === "" ||
      rawValue === null ||
      typeof rawValue === "undefined"
    ) {
      return null;
    }

    const parsed = parseInt(rawValue, 10);
    return Number.isNaN(parsed) ? null : parsed;
  }

  function normalizeText(text) {
    return String(text || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  }

  function getInitials(nome) {
    const parts = String(nome || "")
      .trim()
      .split(/\s+/)
      .filter(Boolean);

    if (!parts.length) return "--";
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
  }

  function formatPeriodo(periodo) {
    const match = String(periodo || "").match(/^(\d{4})-(\d{2})$/);
    if (!match) return periodo || "—";

    const date = new Date(`${match[1]}-${match[2]}-01T00:00:00`);
    if (Number.isNaN(date.getTime())) return periodo;

    return new Intl.DateTimeFormat("pt-BR", {
      month: "short",
      year: "numeric",
    }).format(date);
  }

  function buildEmptyState(message) {
    return `
      <div class="empty-state">
        <i class="fa-solid fa-layer-group"></i>
        <strong>Nada para mostrar</strong>
        <span>${escHtml(message)}</span>
      </div>`;
  }

  function escHtml(str) {
    const div = document.createElement("div");
    div.textContent = String(str);
    return div.innerHTML;
  }

  function showToast(text, type) {
    const colors = {
      success: "#16a34a",
      error: "#e11d48",
      info: "#2563eb",
    };

    Toastify({
      text,
      duration: 3500,
      gravity: "top",
      position: "right",
      style: {
        background: colors[type] || colors.info,
        borderRadius: "12px",
        fontFamily: '"Inter", sans-serif',
        fontSize: "13px",
        fontWeight: "600",
      },
    }).showToast();
  }

  document.addEventListener("DOMContentLoaded", init);

  // ── Tooltip engine (position:fixed — avoids overflow:hidden clipping) ──────
  function initTooltip() {
    const tip = document.createElement("div");
    tip.className = "app-tooltip";
    tip.setAttribute("aria-hidden", "true");
    document.body.appendChild(tip);

    function show(el) {
      const text = el.dataset.tooltip;
      if (!text) return;

      tip.textContent = text;
      tip.classList.remove("pos-above", "pos-below", "is-visible");
      // Place off-screen to measure dimensions before revealing
      tip.style.top = "-9999px";
      tip.style.left = "0px";

      const tipW = tip.offsetWidth;
      const tipH = tip.offsetHeight;
      const rect = el.getBoundingClientRect();
      const gap = 8;
      const arrowH = 5;

      const spaceAbove = rect.top;
      const placeBelow = spaceAbove < tipH + gap + arrowH + 4;

      let top = placeBelow
        ? rect.bottom + gap + arrowH
        : rect.top - tipH - gap - arrowH;

      // Center horizontally on the element, clamp within viewport
      let left = rect.left + rect.width / 2 - tipW / 2;
      const leftClamped = Math.max(
        8,
        Math.min(left, window.innerWidth - tipW - 8),
      );

      // Adjust arrow position to point at the element center even when clamped
      const arrowLeft = rect.left + rect.width / 2 - leftClamped;
      const arrowLeftPct = Math.max(10, Math.min(arrowLeft, tipW - 10));
      tip.style.setProperty("--arrow-left", `${arrowLeftPct}px`);

      tip.classList.add(placeBelow ? "pos-below" : "pos-above");
      tip.style.top = `${top}px`;
      tip.style.left = `${leftClamped}px`;
      tip.classList.add("is-visible");
    }

    function hide() {
      tip.classList.remove("is-visible");
    }

    document.addEventListener("mouseover", (e) => {
      const el = e.target.closest("[data-tooltip]");
      if (el) show(el);
    });

    document.addEventListener("mouseout", (e) => {
      const el = e.target.closest("[data-tooltip]");
      if (el && !el.contains(e.relatedTarget)) hide();
    });
  }
})();
