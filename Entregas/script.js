// Determine the directory where this script is hosted so we can build
// absolute paths to the PHP endpoints. This allows the same script to be
// included from `/Entregas/...` or from `/Dashboard/...` without broken
// relative fetch calls.
const BASE = (function () {
  try {
    let src = "";
    if (document.currentScript && document.currentScript.src)
      src = document.currentScript.src;
    else {
      const scripts = document.getElementsByTagName("script");
      src =
        (scripts[scripts.length - 1] && scripts[scripts.length - 1].src) || "";
    }
    if (!src) return "./";
    const u = new URL(src, window.location.href);
    u.pathname = u.pathname.replace(/\\/g, "/").replace(/\/[^/]*$/, "/");
    return u.origin + u.pathname;
  } catch (e) {
    return "./";
  }
})();

document.addEventListener("DOMContentLoaded", () => {
  const columns = document.querySelectorAll(".column");
  const modalEntrega = document.getElementById("entregaModal");
  const modalTitle = document.getElementById("modalTitulo");
  const modalEtapa = document.getElementById("modalEtapa");
  const modalPrazo = document.getElementById("modalPrazo");
  const modalProgresso = document.getElementById("modalProgresso");
  const modalImagens = document.getElementById("modalImagens");

  // global store of fetched entregas so we can filter client-side
  let entregasAll = [];

  // Helper: create card element for a entrega
  function createCard(entrega) {
    const card = document.createElement("div");
    card.classList.add("card-entrega");
    card.dataset.id = entrega.id;

    // add status-based class (use nome_etapa as canonical status)
    const statusRaw = String(
      entrega.nome_etapa ||
        entrega.kanban_status ||
        entrega.status ||
        "UNKNOWN",
    );
    const statusCode =
      statusRaw
        .trim()
        .replace(/\s+/g, "-")
        .replace(/[^\w-]/g, "")
        .toUpperCase() || "UNKNOWN";
    card.classList.add("status-" + statusCode);

    // Store extra metadata for context menu
    card.dataset.kanbanStatus = entrega.kanban_status || "";
    card.dataset.arquivada = entrega.arquivada ? "1" : "0";
    card.dataset.canUnarchive = entrega.can_unarchive ? "1" : "0";
    card.dataset.dataPrevista = entrega.data_prevista || "";
    card.dataset.statusId = entrega.status_id || "0";

    const readyCount = parseInt(entrega.ready_count || 0, 10);
    const isConcluida = (entrega.kanban_status || "") === "concluida";

    card.innerHTML = `
                <div class="card-checkbox"></div>
                <div class="card-header">
                    <h4>${entrega.nomenclatura || ""} - ${entrega.nome_etapa || ""}</h4>
                    ${readyCount > 0 ? `<div class="entrega-badge" title="Imagens prontas para entrega">${readyCount}</div>` : ""}
                </div>
                <p><strong>Status:</strong> ${entrega.nome_etapa || entrega.status || entrega.kanban_status || ""}</p>
                <p><strong>Prazo:</strong> ${entrega.data_prevista ? formatarData(entrega.data_prevista) : "-"}</p>
                <div class="progress-entrega">
                    <div class="progress-bar" style="width:${entrega.pct_entregue || 0}%"></div>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <small>${entrega.entregues || 0}/${entrega.total_itens || 0} imagens entregues</small>
                    ${!isConcluida ? `<button class="btn-cronograma-card" title="Gerar cronograma para esta entrega"><i class="fa-solid fa-calendar-days"></i> Cronograma</button>` : ""}
                </div>
            `;

    return card;
  }

  // Render a list of entregas into the columns
  function renderEntregas(list) {
    // clear existing cards
    columns.forEach((col) =>
      col.querySelectorAll(".card-entrega").forEach((card) => card.remove()),
    );

    list.forEach((entrega) => {
      // find column based on dataset statuses
      const col = Array.from(columns).find((c) => {
        const statuses = (c.dataset.status || "")
          .split(",")
          .map((s) => s.trim().toLowerCase());
        // try to match using kanban_status first, then status, then nome_etapa
        const entStatus = String(
          entrega.kanban_status || entrega.status || entrega.nome_etapa || "",
        )
          .trim()
          .toLowerCase();
        return entStatus && statuses.includes(entStatus);
      });

      if (!col) return;
      const card = createCard(entrega);
      col.appendChild(card);
    });

    // Update column count badges
    columns.forEach((col) => {
      const count = col.querySelectorAll(".card-entrega").length;
      const statusKey = (col.dataset.status || "")
        .split(",")[0]
        .trim()
        .toLowerCase();
      const countEl = document.getElementById("count-" + statusKey);
      if (countEl) countEl.textContent = count;
    });
  }

  // Populate filter selects (obra/status) from the fetched entregas
  function populateFiltersFrom(entregas) {
    try {
      const obraSelect = document.getElementById("filterObra");
      const statusSelect = document.getElementById("filterStatus");
      if (!obraSelect || !statusSelect) return;

      // derive unique obras and statuses present in entregas
      const obras = new Map();
      const statuses = new Map();
      entregas.forEach((e) => {
        const obraId = e.obra_id || e.obraId || e.id_obra || null;
        const obraLabel = e.nomenclatura || (obraId ? `Obra ${obraId}` : "");
        if (obraId) obras.set(String(obraId), obraLabel);

        // derive status code from the same fields used in filtering/rendering
        const st = String(e.nome_etapa || "").trim();
        if (st) statuses.set(st, st);
      });

      // clear and fill obra select (keep first option) - sort by label ascending
      const obraDefault = obraSelect.querySelector("option");
      obraSelect.innerHTML = "";
      obraSelect.appendChild(obraDefault.cloneNode(true));
      // convert map to array and sort by label
      const obraArr = Array.from(obras.entries()).sort((a, b) =>
        a[1].localeCompare(b[1], "pt", { sensitivity: "base" }),
      );
      obraArr.forEach(([id, label]) => {
        const opt = document.createElement("option");
        opt.value = id;
        opt.textContent = label;
        obraSelect.appendChild(opt);
      });

      // clear and fill status select - sort alphabetically
      const statusDefault = statusSelect.querySelector("option");
      statusSelect.innerHTML = "";
      statusSelect.appendChild(statusDefault.cloneNode(true));
      const statusArr = Array.from(statuses.values()).sort((a, b) =>
        a.localeCompare(b, "pt", { sensitivity: "base" }),
      );
      statusArr.forEach((label) => {
        const opt = document.createElement("option");
        opt.value = label;
        opt.textContent = label;
        statusSelect.appendChild(opt);
      });
    } catch (err) {
      console.error("Erro ao popular filtros:", err);
    }
  }

  // Apply current filter selections to the global entregasAll and render
  function applyFilters() {
    const obraVal = (document.getElementById("filterObra") || {}).value || "";
    const statusVal =
      (document.getElementById("filterStatus") || {}).value || "";

    const hasFilter = obraVal || statusVal;
    const btnLimpar = document.getElementById("btnLimparFiltros");
    if (btnLimpar) btnLimpar.style.display = hasFilter ? "inline-flex" : "none";

    const filtered = entregasAll.filter((e) => {
      let okObra = true;
      let okStatus = true;

      if (obraVal) {
        const oid = String(e.obra_id || e.obraId || e.id_obra || "");
        okObra = oid === String(obraVal);
      }
      if (statusVal) {
        const st = String(
          e.nome_etapa || e.kanban_status || e.status || "",
        ).trim();
        // compare normalized (case-insensitive)
        okStatus = st.toLowerCase() === String(statusVal).trim().toLowerCase();
      }
      return okObra && okStatus;
    });

    renderEntregas(filtered);
  }

  const btnLimparFiltros = document.getElementById("btnLimparFiltros");
  if (btnLimparFiltros) {
    btnLimparFiltros.addEventListener("click", () => {
      clearFilters();
      btnLimparFiltros.style.display = "none";
    });
  }

  // Clear filters UI and render all
  function clearFilters() {
    const obraSelect = document.getElementById("filterObra");
    const statusSelect = document.getElementById("filterStatus");
    if (obraSelect) obraSelect.value = "";
    if (statusSelect) statusSelect.value = "";
    renderEntregas(entregasAll);
  }

  // Conjuntos de classificação de status/substatus (normalizados em lowercase)
  const STATUS_PENDENTE = new Set(["entrega pendente"]);
  const STATUS_ENTREGUE = new Set([
    "entregue no prazo",
    "entrega antecipada",
    "entregue com atraso",
  ]);
  const SUBSTATUS_PENDENTE = new Set(["rvw", "drv"]);

  // botão de registrar entrega
  const btnRegistrarEntrega = document.createElement("button");
  btnRegistrarEntrega.textContent = "Registrar Entrega";
  btnRegistrarEntrega.classList.add("btn-action", "btn-primary");
  modalEntrega.querySelector(".modal-footer").appendChild(btnRegistrarEntrega);

  let entregaAtualId = null;
  let entregaDados = null; // guarda dados retornados por get_entrega_item.php para uso posterior

  function formatarData(data) {
    const partes = data.split("-");
    const dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
    return dataFormatada;
  }

  // fechar modal: single handler for all buttons with class .fecharModal
  // Instead of closing based only on existence of a modal element,
  // close the closest modal container to the clicked button so other
  // modals are unaffected.
  document.querySelectorAll(".fecharModal").forEach((btn) => {
    btn.addEventListener("click", (e) => {
      // prevent accidental form submission or default button behaviour
      e.preventDefault();

      // try to find the closest modal container for this button
      // (covers the known modal IDs used in this file)
      const modalToClose = btn.closest(
        "#modalSelecionarImagens, #modalAdicionarEntrega, #entregaModal, #modalPrioridade, #cronogramaModal",
      );

      if (modalToClose) {
        modalToClose.classList.remove("is-open");
      } else {
        // fallback: hide any open known modal
        const selecionarModal = document.getElementById(
          "modalSelecionarImagens",
        );
        const addModal = document.getElementById("modalAdicionarEntrega");
        const entregaModal = document.getElementById("entregaModal");

        if (selecionarModal && selecionarModal.classList.contains("is-open"))
          selecionarModal.classList.remove("is-open");
        else if (addModal && addModal.classList.contains("is-open"))
          addModal.classList.remove("is-open");
        else if (entregaModal && entregaModal.classList.contains("is-open"))
          entregaModal.classList.remove("is-open");
      }

      entregaAtualId = null;
      carregarKanban();
      // remover painel lateral se existir
      const mini = document.getElementById("miniImagePanel");
      if (mini) mini.remove();
    });
  });

  // Create and manage mini image info panel
  function showMiniImagePanel(data, imagemId, anchorEl) {
    let panel = document.getElementById("miniImagePanel");
    if (!panel) {
      panel = document.createElement("div");
      panel.id = "miniImagePanel";
      panel.className = "mini-image-panel";
      panel.innerHTML = `
                <div class="mini-header">
                    <strong>Imagem #<span id="miniImgId"></span></strong>
                    <button id="miniCloseBtn" class="fecharMini">×</button>
                </div>
                <div id="miniContent">Carregando...</div>
            `;
      // append hidden first so we can measure and position
      panel.style.visibility = "hidden";
      panel.style.display = "block";
      document.body.appendChild(panel);
      document
        .getElementById("miniCloseBtn")
        .addEventListener("click", () => panel.remove());
    }

    document.getElementById("miniImgId").textContent = imagemId;
    const content = document.getElementById("miniContent");
    if (!data) {
      content.innerHTML = "<p>Sem histórico de função para esta imagem.</p>";
      // position near anchor even when empty
      positionPanelNearAnchor(panel, anchorEl);
      return;
    }

    const funcao = data.nome_funcao || "—";
    const status = data.status || "—";
    const colaborador = data.nome_colaborador || "—";
    const prazo = data.prazo ? formatarData(data.prazo) : "-";

    content.innerHTML = `
            <p><strong>Função:</strong> ${funcao}</p>
            <p><strong>Status:</strong> ${status}</p>
            <p><strong>Colaborador:</strong> ${colaborador}</p>
            <p><strong>Prazo:</strong> ${prazo}</p>
        `;

    // After filling content, position panel near the clicked label
    positionPanelNearAnchor(panel, anchorEl);
  }

  function positionPanelNearAnchor(panel, anchorEl) {
    if (!panel) return;
    // default width (should match CSS) — measure if possible
    const panelWidth = panel.offsetWidth || 300;
    const panelHeight = panel.offsetHeight || 150;

    // If we have an anchor element, position next to it; otherwise keep to right
    if (anchorEl && anchorEl.getBoundingClientRect) {
      const rect = anchorEl.getBoundingClientRect();
      // preferred position: to the right of the anchor
      let left = rect.right + 8;
      // center vertically relative to the anchor row
      let top = rect.top + rect.height / 2 + window.scrollY - panelHeight / 2;

      // if overflowing right edge, place to the left
      if (left + panelWidth > window.innerWidth - 10) {
        left = rect.left + window.scrollX - panelWidth - 8;
      }
      // ensure top is within viewport
      if (top + panelHeight > window.scrollY + window.innerHeight - 10) {
        top = Math.max(
          window.scrollY + 10,
          window.scrollY + window.innerHeight - panelHeight - 10,
        );
      }
      // avoid going above the viewport
      if (top < window.scrollY + 10) {
        top = window.scrollY + 10;
      }

      panel.style.position = "absolute";
      panel.style.left = `${Math.max(10, left)}px`;
      panel.style.top = `${Math.max(10, top)}px`;
      panel.style.visibility = "visible";
    } else {
      // fallback: fixed to right side
      panel.style.position = "fixed";
      panel.style.right = "20px";
      panel.style.top = "20%";
      panel.style.visibility = "visible";
    }
  }

  // --- FUNÇÃO PRINCIPAL PARA CARREGAR O KANBAN ---
  async function carregarKanban() {
    try {
      const modoArquivadas = window._modoArquivadas || false;

      let url;
      if (modoArquivadas) {
        url = BASE + "listar_arquivadas.php";
      } else {
        // If running inside Dashboard we may have an obraId in localStorage.
        const storedObra =
          typeof localStorage !== "undefined"
            ? localStorage.getItem("obraId")
            : null;
        const isEntregasPage = window.location.pathname
          .toLowerCase()
          .includes("/entregas");
        url = BASE + "listar_entregas.php";
        if (storedObra && !isEntregasPage) {
          url += "?obra_id=" + encodeURIComponent(storedObra);
        }
      }

      const res = await fetch(url);
      const entregas = await res.json();

      entregasAll = Array.isArray(entregas) ? entregas : [];

      // Visual indicator for archive mode
      const kanban = document.getElementById("kanban");
      const banner = document.getElementById("arquivadasBanner");
      if (kanban) kanban.classList.toggle("kanban-arquivadas", modoArquivadas);
      if (banner) banner.style.display = modoArquivadas ? "flex" : "none";

      populateFiltersFrom(entregasAll);
      applyFilters(); // re-apply current filter selection after reload
    } catch (err) {
      console.error("Erro ao carregar o Kanban:", err);
    }
  }

  // expose for external callers (e.g. other scripts or handlers)
  try {
    window.carregarKanban = carregarKanban;
  } catch (e) {}

  carregarKanban();

  // wire filter UI events (after initial load will populate options)
  const obraSelectEl = document.getElementById("filterObra");
  const statusSelectEl = document.getElementById("filterStatus");

  if (obraSelectEl)
    obraSelectEl.addEventListener("change", () => applyFilters());
  if (statusSelectEl)
    statusSelectEl.addEventListener("change", () => applyFilters());

  // --- ABRIR MODAL AO CLICAR EM UM CARD ---
  document.addEventListener("click", async (e) => {
    // Interceptar clique no botão de cronograma individual
    if (e.target.closest(".btn-cronograma-card")) {
      e.stopPropagation();
      const card = e.target.closest(".card-entrega");
      if (card) gerarCronogramaParaEntregas([parseInt(card.dataset.id)]);
      return;
    }

    const card = e.target.closest(".card-entrega");
    if (!card) return;

    // Em modo planejamento, seleciona/deseleciona ao invés de abrir modal
    if (window._modoPlanejamento) {
      toggleCardSelection(card);
      return;
    }

    entregaAtualId = card.dataset.id;

    try {
      const res = await fetch(
        BASE + `get_entrega_item.php?id=${entregaAtualId}`,
      );
      const data = await res.json();

      modalTitle.textContent = `${data.nomenclatura || "Entrega"} - ${data.nome_etapa || data.id}`;
      // salvar dados para uso por outros handlers (ex: adicionar imagem por id)
      entregaDados = data;
      modalPrazo.textContent = formatarData(data.data_prevista) || "-";
      // Contabiliza somente itens realmente entregues conforme regras solicitadas
      const finalizedCount = data.itens.filter((i) => {
        const statusStr = (i.status || "").toString().trim();
        const substatus = (i.nome_substatus || "").toString().trim();
        // normalizar para comparação
        const ns = statusStr.toLowerCase();
        const nsub = substatus.toLowerCase();
        const isPendente =
          STATUS_PENDENTE.has(ns) ||
          (SUBSTATUS_PENDENTE.has(nsub) && !STATUS_ENTREGUE.has(ns));
        const isEntregue = STATUS_ENTREGUE.has(ns) && !isPendente;
        return isEntregue;
      }).length;
      modalProgresso.textContent = `${finalizedCount} / ${data.itens.length} finalizadas`;

      modalImagens.innerHTML = "";

      // adiciona checkbox mestre para ações em batch (selecionar todos)
      const masterDiv = document.createElement("div");
      masterDiv.classList.add("modal-imagem-item", "select-all-item");
      masterDiv.innerHTML = `
                <input type="checkbox" id="selectAllImagens">
                <label for="selectAllImagens" class="imagem_nome">Selecionar todos</label>
            `;
      modalImagens.appendChild(masterDiv);

      data.itens.forEach((img) => {
        const div = document.createElement("div");
        div.classList.add("modal-imagem-item");

        const statusStr = (img.status || "").toString().trim();
        const substatusStr = (img.nome_substatus || "").toString().trim();

        const ns = statusStr.toLowerCase();
        const nsub = substatusStr.toLowerCase();

        const isPendente =
          STATUS_PENDENTE.has(ns) ||
          (SUBSTATUS_PENDENTE.has(nsub) && !STATUS_ENTREGUE.has(ns));
        const isEntregue = STATUS_ENTREGUE.has(ns) && !isPendente;
        const isEmAndamento = !isPendente && !isEntregue;

        const checked = isEntregue ? "checked" : "";
        const disabled = isEntregue ? "disabled" : "";

        div.innerHTML = `
                <input type="checkbox" id="img-item-${img.id}" value="${img.id}" ${checked} ${disabled} data-imagem-id="${img.imagem_id}">
                <label class="imagem_nome" data-imagem-id="${img.imagem_id}">${img.nome}</label>
                <span class="entregue">${isEntregue ? "📦 Entregue" : isPendente ? "✅ Pendente" : "⏳ Em andamento"}</span>
            `;
        modalImagens.appendChild(div);
      });

      // Click on image name to open mini info panel (last função / status / colaborador)
      modalImagens.addEventListener("click", async (e) => {
        const label = e.target.closest("label.imagem_nome");
        if (!label) return;
        // get imagem_id from the label's data attribute (set from server data)
        const imagemId =
          label.dataset && label.dataset.imagemId
            ? label.dataset.imagemId
            : null;
        if (!imagemId) return;

        try {
          const resp = await fetch(
            BASE + `get_imagem_funcao.php?imagem_id=${imagemId}`,
          );
          const json = await resp.json();
          if (json && json.success && json.data) {
            showMiniImagePanel(json.data, imagemId, label);
          } else {
            showMiniImagePanel(null, imagemId, label);
          }
        } catch (err) {
          console.error("Erro ao buscar função da imagem:", err);
          showMiniImagePanel(null, imagemId, label);
        }
      });

      // configurar comportamento do checkbox mestre e sincronização
      const master = document.getElementById("selectAllImagens");
      if (master) {
        const selectableSelector = 'input[type="checkbox"]:not([disabled])';
        const getSelectable = () =>
          Array.from(modalImagens.querySelectorAll(selectableSelector)).filter(
            (cb) => cb.id !== "selectAllImagens",
          );

        const updateMasterState = () => {
          const selectable = getSelectable();
          const total = selectable.length;
          const checkedCount = selectable.filter((cb) => cb.checked).length;
          master.checked = total > 0 && checkedCount === total;
          master.indeterminate = checkedCount > 0 && checkedCount < total;
        };

        master.addEventListener("change", () => {
          const selectable = getSelectable();
          selectable.forEach((cb) => (cb.checked = master.checked));
          master.indeterminate = false;
        });

        // adicionar listener para cada checkbox selecionável para manter o mestre em sincronia
        const attachIndividualListeners = () => {
          const selectable = getSelectable();
          selectable.forEach((cb) => {
            cb.removeEventListener("change", updateMasterState);
            cb.addEventListener("change", updateMasterState);
          });
        };

        attachIndividualListeners();
        // inicializar estado do mestre
        updateMasterState();
      }

      modalEntrega.classList.add("is-open");
    } catch (err) {
      console.error("Erro ao carregar detalhes da entrega:", err);
    }
  });

  // --- REMOVER IMAGEM COM CLIQUE DIREITO ---
  modalImagens.addEventListener("contextmenu", async (e) => {
    const item = e.target.closest(".modal-imagem-item");
    if (!item || item.classList.contains("select-all-item")) return;
    if (!entregaAtualId) return;
    e.preventDefault();
    const checkbox = item.querySelector('input[type="checkbox"]');
    if (!checkbox) return;
    const itemId = parseInt(checkbox.value, 10); // este é o id do registro em entregas_itens
    // obter também a imagem_id armazenada nos dados, se disponível
    let imagemId = null;
    if (entregaDados && Array.isArray(entregaDados.itens)) {
      const found = entregaDados.itens.find(
        (it) => parseInt(it.id, 10) === itemId,
      );
      if (found) imagemId = found.imagem_id;
    }
    const nomeLabel = item.querySelector("label.imagem_nome");
    const nomeImagem = nomeLabel
      ? nomeLabel.textContent.trim()
      : "Item " + itemId;
    const confirmar = confirm(
      `Remover a imagem "${nomeImagem}" desta entrega?`,
    );
    if (!confirmar) return;
    try {
      const payload = { entrega_id: entregaAtualId, item_id: itemId };
      const res = await fetch(BASE + "remove_imagem_entrega.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const json = await res.json();
      if (json.success) {
        item.remove();
        if (entregaDados && Array.isArray(entregaDados.itens)) {
          entregaDados.itens = entregaDados.itens.filter(
            (it) => parseInt(it.id, 10) !== itemId,
          );
        }
        const total = modalImagens.querySelectorAll(
          ".modal-imagem-item:not(.select-all-item)",
        ).length;
        const entregues = Array.from(
          modalImagens.querySelectorAll(
            ".modal-imagem-item:not(.select-all-item) input[disabled]",
          ),
        ).length;
        modalProgresso.textContent = `${entregues} / ${total} finalizadas`;
        const master = document.getElementById("selectAllImagens");
        if (master) {
          const selectable = Array.from(
            modalImagens.querySelectorAll(
              'input[type="checkbox"]:not([disabled])',
            ),
          ).filter((cb) => cb.id !== "selectAllImagens");
          const checkedCount = selectable.filter((cb) => cb.checked).length;
          master.checked =
            selectable.length > 0 && checkedCount === selectable.length;
          master.indeterminate =
            checkedCount > 0 && checkedCount < selectable.length;
        }
      } else {
        alert(
          "Não foi possível remover: " + (json.error || "erro desconhecido"),
        );
      }
    } catch (err) {
      console.error("Erro ao remover imagem da entrega:", err);
      alert("Falha ao remover imagem (ver console)");
    }
  });

  // --- REGISTRAR ENTREGA ---
  btnRegistrarEntrega.addEventListener("click", async () => {
    if (!entregaAtualId) return;

    const checkboxes = modalImagens.querySelectorAll(
      'input[type="checkbox"]:checked:not([disabled])',
    );
    if (checkboxes.length === 0) {
      alert("Nenhuma imagem selecionada para entrega.");
      return;
    }

    const imagens = Array.from(checkboxes).map((cb) => cb.value);

    try {
      const res = await fetch(BASE + "registrar_entrega.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          entrega_id: entregaAtualId,
          imagens_entregues: imagens,
        }),
      });
      const json = await res.json();

      if (json.success) {
        alert(`Entrega registrada! Status: ${json.novo_status}`);
        modalEntrega.classList.remove("is-open");
        entregaAtualId = null;
        carregarKanban();
      } else {
        alert("Erro ao registrar entrega: " + (json.error || "desconhecido"));
      }
    } catch (err) {
      console.error("Erro ao registrar entrega:", err);
      alert("Erro ao registrar entrega (ver console)");
    }
  });

  // --- DRAG AND DROP ---
  columns.forEach((col) => {
    col.addEventListener("dragover", (e) => e.preventDefault());
    col.addEventListener("drop", async (e) => {
      e.preventDefault();
      const cardId = e.dataTransfer.getData("text/plain");
      const card = document.querySelector(`.card-entrega[data-id="${cardId}"]`);
      if (!card) return;
      col.appendChild(card);

      const newStatus = col.dataset.status;

      try {
        const res = await fetch(BASE + "update_entrega_status.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ id: cardId, status: newStatus }),
        });
        const result = await res.json();
        if (!result.success) alert("Erro ao atualizar status!");
      } catch (err) {
        console.error("Erro ao mover card:", err);
      }
    });
  });

  // --- Habilitar drag nos cards ---
  document.addEventListener("dragstart", (e) => {
    if (e.target.classList.contains("card-entrega")) {
      e.dataTransfer.setData("text/plain", e.target.dataset.id);
    }
  });
  // --- ADICIONAR IMAGEM: abrir modal de seleção pré-filtrada ---
  const btnAdicionarImagem = document.getElementById("btnAdicionarImagem");
  const modalSelecionar = document.getElementById("modalSelecionarImagens");
  const selecionarContainer = document.getElementById(
    "selecionar_imagens_container",
  );
  const btnAdicionarSelecionadas = document.getElementById(
    "btnAdicionarSelecionadas",
  );

  async function carregarImagensParaSelecao(
    obraId,
    statusId,
    existingIds = [],
    limit = 1000,
  ) {
    if (!obraId || !statusId) {
      selecionarContainer.innerHTML = "<p>Obra ou status inválido.</p>";
      return;
    }
    selecionarContainer.innerHTML = "<p>Carregando imagens...</p>";
    try {
      const res = await fetch(
        BASE + `get_imagens.php?obra_id=${obraId}&status_id=${statusId}`,
      );
      const imgs = await res.json();
      const container = selecionarContainer;
      container.innerHTML = "";

      // Filtrar imagens que já estão na entrega
      const existingSet = new Set(existingIds.map((id) => Number(id)));
      const filtered = imgs.filter((img) => !existingSet.has(Number(img.id)));

      if (!filtered.length) {
        container.innerHTML =
          "<p>Nenhuma imagem disponível para adicionar (todas já presentes ou não existem).</p>";
        return;
      }

      filtered.slice(0, limit).forEach((img) => {
        const div = document.createElement("div");
        div.classList.add("checkbox-item");
        div.innerHTML = `\n                    <input type="checkbox" name="selecionar_imagem_ids[]" value="${img.id}" id="sel-img-${img.id}">\n                    <label for="sel-img-${img.id}"><span>${img.nome}</span></label>\n                `;
        container.appendChild(div);
      });
    } catch (err) {
      console.error("Erro ao carregar imagens para seleção:", err);
      selecionarContainer.innerHTML = "<p>Erro ao carregar imagens.</p>";
    }
  }

  if (btnAdicionarImagem) {
    btnAdicionarImagem.addEventListener("click", async function () {
      if (!entregaAtualId || !entregaDados) {
        alert("Abra primeiro uma entrega clicando no card.");
        return;
      }

      const obraId =
        entregaDados.obra_id ||
        entregaDados.obraId ||
        entregaDados.id_obra ||
        null;
      const statusId =
        entregaDados.status_id ||
        entregaDados.statusId ||
        entregaDados.id_status ||
        null;

      // construir lista de existing ids
      const existingIds = (entregaDados.itens || []).map((it) =>
        Number(it.imagem_id || it.imagemId || it.id),
      );

      // abrir modal e carregar imagens
      if (modalSelecionar) modalSelecionar.classList.add("is-open");
      await carregarImagensParaSelecao(obraId, statusId, existingIds);
    });
  }

  // handler do botão 'Adicionar Selecionadas'
  if (btnAdicionarSelecionadas) {
    btnAdicionarSelecionadas.addEventListener("click", async function () {
      if (!entregaAtualId) {
        alert("Entrega não selecionada.");
        return;
      }
      const checked = Array.from(
        document.querySelectorAll(
          '#selecionar_imagens_container input[type="checkbox"]:checked',
        ),
      );
      if (checked.length === 0) {
        alert("Selecione ao menos uma imagem.");
        return;
      }
      const ids = checked.map((cb) => parseInt(cb.value));
      try {
        const res = await fetch(BASE + "add_imagem_entrega_id.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ entrega_id: entregaAtualId, imagem_ids: ids }),
        });
        const json = await res.json();
        if (json.success) {
          alert(
            "Imagens adicionadas: " +
              (json.added_count || 0) +
              "\nPuladas: " +
              (json.skipped_count || 0),
          );
          if (modalSelecionar) modalSelecionar.classList.remove("is-open");
          // atualizar modal entrega e kanban
          modalEntrega.classList.remove("is-open");
          entregaAtualId = null;
          entregaDados = null;
          carregarKanban();
        } else {
          alert("Erro ao adicionar: " + (json.error || "desconhecido"));
        }
      } catch (err) {
        console.error("Erro ao adicionar imagens selecionadas:", err);
        alert("Erro ao adicionar imagens (ver console)");
      }
    });
  }

  // =====================================================
  // ===== MODO PLANEJAMENTO / CRONOGRAMA =====
  // =====================================================

  window._modoPlanejamento = false;
  const selectedEntregas = new Set();

  const togglePlanejamento = document.getElementById("togglePlanejamento");
  const floatingBar = document.getElementById("floatingBar");
  const floatingCount = document.getElementById("floatingCount");
  const btnGerarCronograma = document.getElementById("btnGerarCronograma");

  if (togglePlanejamento) {
    togglePlanejamento.addEventListener("click", () => {
      window._modoPlanejamento = !window._modoPlanejamento;
      togglePlanejamento.classList.toggle("active", window._modoPlanejamento);
      document
        .querySelector(".container")
        .classList.toggle("modo-planejamento", window._modoPlanejamento);

      if (!window._modoPlanejamento) {
        // Limpar seleção
        selectedEntregas.clear();
        document
          .querySelectorAll(".card-selecionado")
          .forEach((c) => c.classList.remove("card-selecionado"));
        document
          .querySelectorAll(".card-checkbox.checked")
          .forEach((c) => c.classList.remove("checked"));
        updateFloatingBar();
      }
      updateFloatingBar();
    });
  }

  function toggleCardSelection(card) {
    const id = parseInt(card.dataset.id);
    const checkbox = card.querySelector(".card-checkbox");
    if (selectedEntregas.has(id)) {
      selectedEntregas.delete(id);
      card.classList.remove("card-selecionado");
      if (checkbox) checkbox.classList.remove("checked");
    } else {
      selectedEntregas.add(id);
      card.classList.add("card-selecionado");
      if (checkbox) checkbox.classList.add("checked");
    }
    updateFloatingBar();
  }
  // expose for the click handler
  window.toggleCardSelection = toggleCardSelection;

  function updateFloatingBar() {
    if (!floatingBar) return;
    const count = selectedEntregas.size;
    if (window._modoPlanejamento && count > 0) {
      floatingBar.classList.remove("hidden");
      floatingCount.textContent = count;
    } else {
      floatingBar.classList.add("hidden");
    }
  }

  // Gerar cronograma click
  if (btnGerarCronograma) {
    btnGerarCronograma.addEventListener("click", () => {
      const ids = Array.from(selectedEntregas);
      if (ids.length === 0) return;
      if (ids.length === 1) {
        gerarCronogramaParaEntregas(ids);
      } else {
        abrirModalPrioridade(ids);
      }
    });
  }

  // Modal de Prioridade
  function abrirModalPrioridade(ids) {
    const modal = document.getElementById("modalPrioridade");
    const list = document.getElementById("priorityList");
    if (!modal || !list) return;

    // Buscar dados das entregas selecionadas do array entregasAll
    const selecionadas = ids
      .map((id) =>
        entregasAll.find((e) => e.id === id || parseInt(e.id) === id),
      )
      .filter(Boolean);

    // Ordenar: atrasadas primeiro, depois por data_prevista
    selecionadas.sort((a, b) => {
      const aAtrasada = a.kanban_status === "atrasada" ? 0 : 1;
      const bAtrasada = b.kanban_status === "atrasada" ? 0 : 1;
      if (aAtrasada !== bAtrasada) return aAtrasada - bAtrasada;
      return (a.data_prevista || "").localeCompare(b.data_prevista || "");
    });

    list.innerHTML = "";
    selecionadas.forEach((entrega, idx) => {
      const pendentes = (entrega.total_itens || 0) - (entrega.entregues || 0);
      const isAtrasada = entrega.kanban_status === "atrasada";
      const li = document.createElement("li");
      li.classList.add("priority-item");
      li.dataset.id = entrega.id;
      li.innerHTML = `
        <i class="fa-solid fa-grip-vertical drag-handle"></i>
        <span class="priority-number">${idx + 1}</span>
        <div class="priority-info">
          <div class="priority-title">${entrega.nomenclatura || ""} — ${entrega.nome_etapa || ""}</div>
          <div class="priority-meta">
            <span>${pendentes} imagens faltando</span>
            <span>Prazo: ${entrega.data_prevista ? formatarData(entrega.data_prevista) : "—"}</span>
            ${isAtrasada ? '<span class="badge-atraso-sm">⚠️ atrasada</span>' : ""}
          </div>
        </div>
      `;
      list.appendChild(li);
    });

    // Inicializar SortableJS
    if (window.Sortable) {
      new Sortable(list, {
        animation: 150,
        handle: ".drag-handle",
        ghostClass: "sortable-ghost",
        onEnd: () => {
          // Atualizar números
          list.querySelectorAll(".priority-item").forEach((li, i) => {
            li.querySelector(".priority-number").textContent = i + 1;
          });
        },
      });
    }

    modal.classList.add("is-open");
  }

  // Confirmar prioridade
  const btnConfirmarPrioridade = document.getElementById(
    "btnConfirmarPrioridade",
  );
  if (btnConfirmarPrioridade) {
    btnConfirmarPrioridade.addEventListener("click", () => {
      const list = document.getElementById("priorityList");
      const orderedIds = Array.from(
        list.querySelectorAll(".priority-item"),
      ).map((li) => parseInt(li.dataset.id));
      document.getElementById("modalPrioridade").classList.remove("is-open");
      gerarCronogramaParaEntregas(orderedIds);
    });
  }

  // Gerar Cronograma (fetch + render)
  async function gerarCronogramaParaEntregas(orderedIds) {
    const cronogramaModal = document.getElementById("cronogramaModal");
    const cronogramaTabs = document.getElementById("cronogramaTabs");
    const cronogramaContent = document.getElementById("cronogramaContent");
    if (!cronogramaModal) return;

    cronogramaTabs.innerHTML = "";
    cronogramaContent.innerHTML =
      '<p style="color:var(--text-muted);">Gerando cronograma...</p>';
    cronogramaModal.classList.add("is-open");

    try {
      const res = await fetch(BASE + "get_cronograma.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ entrega_ids: orderedIds }),
      });
      const data = await res.json();

      if (data.error) {
        cronogramaContent.innerHTML = `<p style="color:var(--status-reprovado);">${data.error}</p>`;
        return;
      }

      window._cronogramaData = data;
      // Reset to table view on each new cronograma generation
      window._cronogramaView = "table";
      window._cronogramaCurrentEntrega = null;
      const _tog = document.getElementById("cronogramaViewToggle");
      if (_tog) {
        _tog.style.display = "none";
        _tog
          .querySelectorAll(".vt-btn")
          .forEach((b) =>
            b.classList.toggle("active", b.dataset.view === "table"),
          );
      }
      renderCronograma(data);
    } catch (err) {
      console.error("Erro ao gerar cronograma:", err);
      cronogramaContent.innerHTML =
        '<p style="color:var(--status-reprovado);">Erro ao gerar cronograma.</p>';
    }
  }
  window.gerarCronogramaParaEntregas = gerarCronogramaParaEntregas;

  function renderCronograma(data) {
    const cronogramaTabs = document.getElementById("cronogramaTabs");
    const cronogramaContent = document.getElementById("cronogramaContent");
    const viewToggle = document.getElementById("cronogramaViewToggle");

    const entregas = data.entregas || [];
    const colaboradores = data.colaboradores || [];
    window._cronogramaColabs = colaboradores;

    if (entregas.length === 0) {
      cronogramaContent.innerHTML =
        '<p style="color:var(--text-muted);">Nenhuma imagem pendente encontrada nas entregas selecionadas.</p>';
      if (viewToggle) viewToggle.style.display = "none";
      return;
    }

    // Show view toggle and wire buttons
    if (viewToggle) {
      viewToggle.style.display = "flex";
      viewToggle.querySelectorAll(".vt-btn").forEach((btn) => {
        btn.classList.toggle(
          "active",
          btn.dataset.view === (window._cronogramaView || "table"),
        );
        btn.onclick = () => {
          viewToggle
            .querySelectorAll(".vt-btn")
            .forEach((b) => b.classList.remove("active"));
          btn.classList.add("active");
          window._cronogramaView = btn.dataset.view;
          const cur = window._cronogramaCurrentEntrega || entregas[0];
          if (window._cronogramaView === "gantt") renderCronogramaGantt(cur);
          else renderCronogramaTab(cur, colaboradores);
        };
      });
    }

    // Tabs (only if multiple)
    cronogramaTabs.innerHTML = "";
    if (entregas.length > 1) {
      entregas.forEach((ent, idx) => {
        const btn = document.createElement("button");
        btn.classList.add("cronograma-tab");
        if (idx === 0) btn.classList.add("active");
        btn.textContent = `${ent.nomenclatura} — ${ent.nome_etapa}`;
        btn.dataset.index = idx;
        btn.addEventListener("click", () => {
          cronogramaTabs
            .querySelectorAll(".cronograma-tab")
            .forEach((t) => t.classList.remove("active"));
          btn.classList.add("active");
          if (window._cronogramaView === "gantt")
            renderCronogramaGantt(entregas[idx]);
          else renderCronogramaTab(entregas[idx], colaboradores);
        });
        cronogramaTabs.appendChild(btn);
      });
    }

    if (window._cronogramaView === "gantt") renderCronogramaGantt(entregas[0]);
    else renderCronogramaTab(entregas[0], colaboradores);
  }

  function renderCronogramaTab(entrega, colaboradores) {
    window._cronogramaCurrentEntrega = entrega;
    const cronogramaContent = document.getElementById("cronogramaContent");
    const prazoOriginal = entrega.data_prevista
      ? formatarData(entrega.data_prevista)
      : "—";
    const estimativa = entrega.estimativa_conclusao
      ? formatarData(entrega.estimativa_conclusao)
      : "—";

    let html = `<div class="cronograma-summary">
      <div class="summary-item">
        <span class="summary-label">Prazo original</span>
        <span class="summary-value">${prazoOriginal}</span>
      </div>
      <div class="summary-item">
        <span class="summary-label">📅 Estimativa de conclusão</span>
        <span class="summary-value">${estimativa}${entrega.is_atrasado ? '<span class="badge-atraso">⚠️ Atraso</span>' : ""}</span>
      </div>
    </div>`;

    if (entrega.tarefas.length === 0) {
      html +=
        '<p style="color:var(--text-muted);">Nenhuma tarefa pendente para esta entrega.</p>';
      cronogramaContent.innerHTML = html;
      return;
    }

    html += `<div class="cronograma-table-wrapper">
    <table class="cronograma-table">
      <thead>
        <tr>
          <th>Imagem</th>
          <th>Função</th>
          <th>Responsável ✏️</th>
          <th>Início ✏️</th>
          <th>Fim ✏️</th>
        </tr>
      </thead>
      <tbody>`;

    entrega.tarefas.forEach((t, idx) => {
      const criticalClass = t.is_critical ? " is-critical" : "";
      const fallbackClass = t.is_fallback ? " fonte-padrao" : "";
      const colabOptions = colaboradores
        .map(
          (c) =>
            `<option value="${c.id}" ${parseInt(c.id) === t.colaborador_id ? "selected" : ""}>${c.nome}</option>`,
        )
        .join("");

      html += `<tr class="${criticalClass}" data-idx="${idx}">
        <td>${t.imagem_nome}</td>
        <td>${t.funcao_nome}</td>
        <td class="${fallbackClass}">
          <span class="campo-editavel campo-colab" data-idx="${idx}" data-field="colaborador">${t.colaborador_nome}</span>
        </td>
        <td class="${fallbackClass}">
          <span class="campo-editavel campo-data" data-idx="${idx}" data-field="data_inicio">${formatarData(t.data_inicio)}</span>
        </td>
        <td class="${fallbackClass}">
          <span class="campo-editavel campo-data" data-idx="${idx}" data-field="data_fim">${formatarData(t.data_fim)}</span>
        </td>
      </tr>`;
    });

    html += `</tbody></table></div>`;

    // Gargalo
    if (entrega.gargalo) {
      html += `<div class="gargalo-info">
        <i class="fa-solid fa-triangle-exclamation"></i>
        Gargalo: <strong>${entrega.gargalo.colaborador_nome}</strong> — ${entrega.gargalo.quantidade} tarefa(s) na fila
      </div>`;
    }

    cronogramaContent.innerHTML = html;

    // Attach inline edit handlers
    attachInlineEditHandlers(entrega, colaboradores);
  }

  function attachInlineEditHandlers(entrega, colaboradores) {
    const content = document.getElementById("cronogramaContent");

    // ── Editar colaborador ────────────────────────────────────────────────
    content.querySelectorAll(".campo-colab").forEach((span) => {
      span.addEventListener("click", async function () {
        if (this.querySelector("select,span.colab-loading")) return; // already editing

        const idx = parseInt(this.dataset.idx);
        const tarefa = entrega.tarefas[idx];
        const originalText = this.textContent;

        // Show spinner while fetching availability
        const loading = document.createElement("span");
        loading.className = "colab-loading";
        loading.textContent = " ⏳";
        this.appendChild(loading);

        // Fetch availability from GANTT endpoint
        let available = [];
        try {
          const res = await fetch(
            `../GANTT/get_colaboradores_por_funcao.php?funcao_id=${encodeURIComponent(tarefa.funcao_id)}&data_inicio=${encodeURIComponent(tarefa.data_inicio)}&data_fim=${encodeURIComponent(tarefa.data_fim)}&gantt_id=0`,
          );
          if (res.ok) available = await res.json();
        } catch (_) {
          /* fallback: available stays empty, use full colaboradores list */
        }

        // Remove spinner
        loading.remove();
        if (this.querySelector("select")) return; // late guard

        // Build a lookup: colaborador_id → availability info
        const availMap = new Map();
        available.forEach((c) => availMap.set(parseInt(c.idcolaborador), c));

        const select = document.createElement("select");
        select.className = "colab-select";

        // "Sem responsável" option
        const optNone = document.createElement("option");
        optNone.value = "0";
        optNone.textContent = "Sem responsável";
        if (tarefa.colaborador_id === 0) optNone.selected = true;
        select.appendChild(optNone);

        // Add every colaborador (full list as fallback when endpoint returns nothing)
        const listToRender =
          available.length > 0
            ? available.map((c) => ({
                id: parseInt(c.idcolaborador),
                nome: c.nome_colaborador,
                ocupado: !!c.ocupado,
                obras: c.obras_conflitantes || "",
                conflito_inicio: c.data_inicio_conflito || "",
                conflito_fim: c.data_fim_conflito || "",
              }))
            : colaboradores.map((c) => ({
                id: parseInt(c.id),
                nome: c.nome,
                ocupado: false,
                obras: "",
                conflito_inicio: "",
                conflito_fim: "",
              }));

        listToRender.forEach((c) => {
          const opt = document.createElement("option");
          opt.value = c.id;
          if (c.ocupado) {
            opt.textContent = `⚠️ ${c.nome} (${c.obras})`;
            opt.className = "colab-busy";
            if (c.conflito_inicio && c.conflito_fim) {
              opt.title = `Ocupado de ${formatarData(c.conflito_inicio)} a ${formatarData(c.conflito_fim)}`;
            }
          } else {
            opt.textContent = `✅ ${c.nome}`;
          }
          if (c.id === tarefa.colaborador_id) opt.selected = true;
          select.appendChild(opt);
        });

        this.textContent = "";
        this.appendChild(select);
        select.focus();

        const finish = () => {
          const newId = parseInt(select.value);
          const selOpt = select.options[select.selectedIndex];
          // Strip leading emoji from display text
          const rawName = selOpt ? selOpt.textContent : "";
          const newName = rawName.replace(/^[✅⚠️]\s*/, "").split(" (")[0];
          tarefa.colaborador_id = newId;
          tarefa.colaborador_nome = newName || originalText;
          tarefa.is_fallback = newId === 0;
          this.textContent = tarefa.colaborador_nome;
          if (selOpt && selOpt.className === "colab-busy") {
            this.classList.add("colab-warning");
          } else {
            this.classList.remove("colab-warning");
          }
        };
        select.addEventListener("change", finish);
        select.addEventListener("blur", finish);
      });
    });

    // ── Editar datas ──────────────────────────────────────────────────────
    content.querySelectorAll(".campo-data").forEach((span) => {
      span.addEventListener("click", function () {
        if (this.querySelector("input")) return;
        const idx = parseInt(this.dataset.idx);
        const field = this.dataset.field;
        const tarefa = entrega.tarefas[idx];
        const currentVal = tarefa[field]; // yyyy-mm-dd

        const input = document.createElement("input");
        input.type = "date";
        input.value = currentVal || "";
        this.textContent = "";
        this.appendChild(input);
        input.focus();

        const finish = () => {
          const newVal = input.value;
          if (!newVal || newVal === currentVal) {
            this.textContent = formatarData(tarefa[field]);
            return;
          }

          if (field === "data_inicio") {
            // Cascade: shift current + downstream tasks on the same image
            cascadeTaskDateChange(entrega, idx, newVal);
            // Re-render table to reflect all changes
            renderCronogramaTab(
              entrega,
              window._cronogramaColabs || colaboradores,
            );
          } else {
            // data_fim: update only this task
            tarefa[field] = newVal;
            this.textContent = formatarData(newVal);
          }
        };

        input.addEventListener("change", finish);
        input.addEventListener("blur", finish);
      });
    });
  }

  /**
   * Shift the data_inicio of task[idx] to newDateStr and propagate the same
   * delta (in days) to data_fim of that task and to data_inicio + data_fim of
   * every subsequent task that belongs to the same imagem_id AND whose
   * data_inicio is >= the old start date (i.e. it depends on it in the chain).
   */
  function cascadeTaskDateChange(entrega, idx, newDateStr) {
    const tarefa = entrega.tarefas[idx];
    const oldStart = new Date(tarefa.data_inicio + "T00:00:00");
    const newStart = new Date(newDateStr + "T00:00:00");
    const deltaMs = newStart - oldStart;
    if (deltaMs === 0) return;

    const shiftDate = (dateStr) => {
      if (!dateStr) return dateStr;
      const d = new Date(dateStr + "T00:00:00");
      d.setTime(d.getTime() + deltaMs);
      return d.toISOString().slice(0, 10);
    };

    // Shift the edited task
    tarefa.data_inicio = newDateStr;
    tarefa.data_fim = shiftDate(tarefa.data_fim);

    // Shift downstream tasks of the same image
    const imagemId = tarefa.imagem_id;
    for (let i = idx + 1; i < entrega.tarefas.length; i++) {
      const t = entrega.tarefas[i];
      if (t.imagem_id !== imagemId) continue;
      // Only cascade tasks that start on or after the old start date
      if (new Date(t.data_inicio + "T00:00:00") >= oldStart) {
        t.data_inicio = shiftDate(t.data_inicio);
        t.data_fim = shiftDate(t.data_fim);
      }
    }
  }

  // Helper: format date from YYYY-MM-DD to DD/MM
  function formatarDataCurta(str) {
    if (!str) return "—";
    const parts = str.split("-");
    if (parts.length >= 3) return `${parts[2]}/${parts[1]}`;
    return str;
  }

  // Map funcao_id to a color pair used in the Gantt bars
  function getFuncaoColor(funcaoId) {
    const palette = {
      1: { bg: "#fce4ec", text: "#880e4f" }, // Caderno
      2: { bg: "#fff3e0", text: "#bf360c" }, // Modelagem
      3: { bg: "#f9ffc6", text: "#596112" }, // Composição
      4: { bg: "#e8f5e9", text: "#1b5e20" }, // Finalização
      5: { bg: "#e3f2fd", text: "#0d47a1" }, // Pós-produção
      6: { bg: "#fbe9e7", text: "#b71c1c" }, // Alteração
      7: { bg: "#d0edf5", text: "#0004ff" }, // Planta Humanizada
      8: { bg: "#dcffec", text: "#009921" }, // Filtro de assets
    };
    return palette[funcaoId] || { bg: "#e8ecf1", text: "#4b5563" };
  }

  // Render cronograma as a Gantt chart (read-only)
  function renderCronogramaGantt(entrega) {
    window._cronogramaCurrentEntrega = entrega;
    const content = document.getElementById("cronogramaContent");

    const prazoOriginal = entrega.data_prevista
      ? formatarData(entrega.data_prevista)
      : "—";
    const estimativa = entrega.estimativa_conclusao
      ? formatarData(entrega.estimativa_conclusao)
      : "—";

    const summaryHtml = `<div class="cronograma-summary">
      <div class="summary-item">
        <span class="summary-label">Prazo original</span>
        <span class="summary-value">${prazoOriginal}</span>
      </div>
      <div class="summary-item">
        <span class="summary-label">📅 Estimativa de conclusão</span>
        <span class="summary-value">${estimativa}${
          entrega.is_atrasado
            ? '<span class="badge-atraso">⚠️ Atraso</span>'
            : ""
        }</span>
      </div>
    </div>`;

    if (entrega.tarefas.length === 0) {
      content.innerHTML =
        summaryHtml +
        '<p style="color:var(--text-muted);">Nenhuma tarefa pendente para esta entrega.</p>';
      return;
    }

    // Build date range from min data_inicio to max data_fim
    let minDate = null;
    let maxDate = null;
    entrega.tarefas.forEach((t) => {
      const d1 = new Date(t.data_inicio + "T00:00:00");
      const d2 = new Date(t.data_fim + "T00:00:00");
      if (!minDate || d1 < minDate) minDate = d1;
      if (!maxDate || d2 > maxDate) maxDate = d2;
    });

    const dates = [];
    const cur = new Date(minDate);
    while (cur <= maxDate) {
      dates.push(new Date(cur));
      cur.setDate(cur.getDate() + 1);
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // Group tasks by imagem_id preserving insertion order
    const imageMap = new Map();
    entrega.tarefas.forEach((t) => {
      if (!imageMap.has(t.imagem_id)) {
        imageMap.set(t.imagem_id, { nome: t.imagem_nome, tarefas: [] });
      }
      imageMap.get(t.imagem_id).tarefas.push(t);
    });

    // Build month header cells
    const monthCells = [];
    let curMonth = null;
    let monthCount = 0;
    dates.forEach((d, i) => {
      const m = d.toLocaleDateString("pt-BR", {
        month: "short",
        year: "numeric",
      });
      if (m !== curMonth) {
        if (curMonth !== null)
          monthCells.push({ label: curMonth, count: monthCount });
        curMonth = m;
        monthCount = 1;
      } else {
        monthCount++;
      }
      if (i === dates.length - 1)
        monthCells.push({ label: curMonth, count: monthCount });
    });

    const thMeses =
      '<th class="gantt-sticky"></th>' +
      monthCells
        .map(
          (mc) =>
            `<th colspan="${mc.count}" class="gantt-th-month">${mc.label}</th>`,
        )
        .join("");

    const thDias =
      '<th class="gantt-sticky"></th>' +
      dates
        .map((d) => {
          const dow = d.getDay();
          const isWeekend = dow === 0 || dow === 6;
          const isToday = d.getTime() === today.getTime();
          const cls = isToday
            ? "gantt-th-day gantt-today"
            : isWeekend
              ? "gantt-th-day gantt-weekend"
              : "gantt-th-day";
          return `<th class="${cls}">${d.getDate()}</th>`;
        })
        .join("");

    // Build body rows
    let rows = "";
    imageMap.forEach((img) => {
      let cells = `<td class="gantt-sticky gantt-img-label" title="${img.nome}">${img.nome}</td>`;

      dates.forEach((d) => {
        const dow = d.getDay();
        const isToday = d.getTime() === today.getTime();

        let task = null;
        for (const t of img.tarefas) {
          const s = new Date(t.data_inicio + "T00:00:00");
          const e = new Date(t.data_fim + "T00:00:00");
          if (d >= s && d <= e) {
            task = t;
            break;
          }
        }

        if (task) {
          const color = getFuncaoColor(task.funcao_id);
          const bold = task.is_critical ? "font-weight:700;" : "";
          const colabAbrev = (task.colaborador_nome || "—").split(" ")[0];
          cells += `<td style="background:${color.bg};color:${color.text};${bold}font-size:10px;padding:1px 0;" title="${task.funcao_nome} — ${task.colaborador_nome}">${colabAbrev}</td>`;
        } else {
          cells += `<td${isToday ? ' class="gantt-today-cell"' : ""}></td>`;
        }
      });

      rows += `<tr>${cells}</tr>`;
    });

    // Build legend (unique functions)
    const funcoesSeen = new Map();
    entrega.tarefas.forEach((t) => {
      if (!funcoesSeen.has(t.funcao_id))
        funcoesSeen.set(t.funcao_id, t.funcao_nome);
    });
    let legend = '<div class="gantt-legend">';
    funcoesSeen.forEach((nome, id) => {
      const c = getFuncaoColor(id);
      legend += `<span class="gantt-legend-item" style="background:${c.bg};color:${c.text};">${nome}</span>`;
    });
    legend += "</div>";

    // Gargalo
    let gargaloHtml = "";
    if (entrega.gargalo) {
      gargaloHtml = `<div class="gargalo-info">
        <i class="fa-solid fa-triangle-exclamation"></i>
        Gargalo: <strong>${entrega.gargalo.colaborador_nome}</strong> — ${entrega.gargalo.quantidade} tarefa(s) na fila
      </div>`;
    }

    content.innerHTML =
      summaryHtml +
      legend +
      `<div class="gantt-wrapper">
        <table class="gantt-table">
          <thead>
            <tr>${thMeses}</tr>
            <tr>${thDias}</tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>` +
      gargaloHtml;
  }
});

// ─── keep everything below OUTSIDE the DOMContentLoaded closure ─────────────
void 0; // anchor — do not remove

// ──────── re-attach globals below ────────
// (nothing needed: the split below is handled by the original code structure)

document
  .getElementById("adicionar_entrega")
  .addEventListener("click", function () {
    document.getElementById("modalAdicionarEntrega").classList.add("is-open");
  });

document.getElementById("obra_id").addEventListener("change", carregarImagens);
document
  .getElementById("status_id")
  .addEventListener("change", carregarImagens);

function carregarImagens() {
  const obraId = document.getElementById("obra_id").value;
  const statusId = document.getElementById("status_id").value;

  if (!obraId || !statusId) {
    document.getElementById("imagens_container").innerHTML =
      "<p>Selecione uma obra e um status.</p>";
    return;
  }

  fetch(BASE + `get_imagens.php?obra_id=${obraId}&status_id=${statusId}`)
    .then((res) => res.json())
    .then((imagens) => {
      const container = document.getElementById("imagens_container");
      container.innerHTML = "";

      // adicionar checkbox mestre para seleção em lote dentro do container principal
      const masterDiv = document.createElement("div");
      masterDiv.classList.add("checkbox-item", "select-all-item");
      masterDiv.innerHTML = `
                <input type="checkbox" id="selectAllImagens_list">
                <label for="selectAllImagens_list"><strong>Selecionar todos</strong></label>
            `;
      container.appendChild(masterDiv);

      if (!imagens.length) {
        container.innerHTML =
          "<p>Nenhuma imagem encontrada para esses critérios.</p>";
        return;
      }

      imagens.forEach((img) => {
        const div = document.createElement("div");
        div.classList.add("checkbox-item");

        if (img.antecipada) {
          div.classList.add("antecipada");
        }

        div.innerHTML = `
            <input type="checkbox" name="imagem_ids[]" value="${img.id}" class="img-selectable" id="lista-img-${img.id}">
            <label for="lista-img-${img.id}"><span>${img.nome}</span></label>
        `;
        container.appendChild(div);
      });

      // configurar comportamento do checkbox mestre no container
      const master = document.getElementById("selectAllImagens_list");
      if (master) {
        const getSelectable = () =>
          Array.from(
            container.querySelectorAll(
              'input[type="checkbox"].img-selectable:not([disabled])',
            ),
          );

        const updateMasterState = () => {
          const selectable = getSelectable();
          const total = selectable.length;
          const checkedCount = selectable.filter((cb) => cb.checked).length;
          master.checked = total > 0 && checkedCount === total;
          master.indeterminate = checkedCount > 0 && checkedCount < total;
        };

        master.addEventListener("change", () => {
          const selectable = getSelectable();
          selectable.forEach((cb) => (cb.checked = master.checked));
          master.indeterminate = false;
        });

        const attachIndividualListeners = () => {
          const selectable = getSelectable();
          selectable.forEach((cb) => {
            cb.removeEventListener("change", updateMasterState);
            cb.addEventListener("change", updateMasterState);
          });
        };

        attachIndividualListeners();
        updateMasterState();
      }
    })
    .catch((err) => {
      console.error("Erro ao carregar imagens:", err);
    });
}

// enviar form via AJAX
document
  .getElementById("formAdicionarEntrega")
  .addEventListener("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch(BASE + "save_entrega.php", {
      method: "POST",
      body: formData,
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          alert("Entrega adicionada com sucesso!");
          // Aqui você pode atualizar a tabela, fechar modal, etc.
          document.getElementById("formAdicionarEntrega").reset();
          document.getElementById("imagens_container").innerHTML =
            "<p>Selecione uma obra e status.</p>";
        } else {
          alert("Erro: " + data.msg);
        }
      })
      .catch((err) => console.error("Erro:", err));
  });

// ===== Rich context menu for cards (right-click / long-press) =====
(function () {
  const MENU_ID = "cardContextMenu";
  let currentCard = null;

  function getMenu() {
    let m = document.getElementById(MENU_ID);
    if (m) return m;
    m = document.createElement("div");
    m.id = MENU_ID;
    m.className = "card-context-menu";
    m.style.display = "none";
    m.innerHTML = `
      <div class="ctx-header">
        <span class="ctx-title" id="ctxMenuTitle">Ações da entrega</span>
      </div>
      <div class="ctx-body">
        <button class="ctx-item ctx-date"><i class="fa-solid fa-calendar-days"></i> Mudar data prevista</button>
        <button class="ctx-item ctx-status-change"><i class="fa-solid fa-tag"></i> Mudar status</button>
        <button class="ctx-item ctx-archive"><i class="fa-solid fa-box-archive"></i> Arquivar</button>
        <button class="ctx-item ctx-delete"><i class="fa-solid fa-trash"></i> Excluir</button>
      </div>`;
    document.body.appendChild(m);

    // ── Mudar data prevista ────────────────────────────────────────────
    m.querySelector(".ctx-date").addEventListener("click", async () => {
      const card = currentCard;
      hideMenu();
      if (!card) return;
      const entregaId = card.dataset.id;
      const currentDate = card.dataset.dataPrevista || "";
      const { value, isConfirmed } = await Swal.fire({
        title: "Mudar data prevista",
        input: "date",
        inputValue: currentDate,
        showCancelButton: true,
        confirmButtonText: "Salvar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#4f80e1",
        inputValidator: (v) => {
          if (!v) return "Selecione uma data.";
        },
      });
      if (!isConfirmed || !value) return;
      await _callUpdate(
        entregaId,
        { data_prevista: value },
        "Data prevista atualizada!",
      );
    });

    // ── Mudar status ───────────────────────────────────────────────────
    m.querySelector(".ctx-status-change").addEventListener(
      "click",
      async () => {
        const card = currentCard;
        hideMenu();
        if (!card) return;
        const entregaId = card.dataset.id;
        const currentStId = card.dataset.statusId || "0";
        const statuses = window.STATUS_IMAGENS || [];
        if (!statuses.length) {
          Swal.fire({
            icon: "warning",
            title: "Sem opções",
            text: "Lista de status não disponível.",
            timer: 2500,
            timerProgressBar: true,
          });
          return;
        }
        const inputOptions = {};
        statuses.forEach((s) => {
          inputOptions[s.id] = s.nome;
        });
        const { value, isConfirmed } = await Swal.fire({
          title: "Mudar status",
          input: "select",
          inputOptions,
          inputValue: currentStId,
          showCancelButton: true,
          confirmButtonText: "Salvar",
          cancelButtonText: "Cancelar",
          confirmButtonColor: "#4f80e1",
          inputValidator: (v) => {
            if (!v) return "Selecione um status.";
          },
        });
        if (!isConfirmed || !value) return;
        await _callUpdate(
          entregaId,
          { status_id: parseInt(value) },
          "Status atualizado!",
        );
      },
    );

    // ── Arquivar / Desarquivar ─────────────────────────────────────────
    m.querySelector(".ctx-archive").addEventListener("click", async () => {
      const card = currentCard;
      hideMenu();
      if (!card) return;
      const entregaId = card.dataset.id;
      const canUnarchive = card.dataset.canUnarchive === "1";
      const modoArq = window._modoArquivadas || false;
      const isUnarchiving = modoArq && canUnarchive;
      const titleCard =
        card.querySelector(".card-header h4")?.textContent ||
        `Entrega ${entregaId}`;
      const { isConfirmed } = await Swal.fire({
        title: isUnarchiving ? "Desarquivar entrega?" : "Arquivar entrega?",
        text: isUnarchiving
          ? `"${titleCard}" voltará para o kanban ativo.`
          : `"${titleCard}" será movida para o arquivo.`,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: isUnarchiving ? "Desarquivar" : "Arquivar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#4f80e1",
      });
      if (!isConfirmed) return;
      try {
        const res = await fetch(BASE + "archive_entrega.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            entrega_id: entregaId,
            arquivada: isUnarchiving ? 0 : 1,
          }),
        });
        const json = await res.json();
        if (json && json.success) {
          _toast(
            isUnarchiving ? "Entrega desarquivada!" : "Entrega arquivada!",
          );
          if (typeof window.carregarKanban === "function")
            window.carregarKanban();
        } else {
          Swal.fire({
            icon: "error",
            title: "Erro",
            text: json.error || "Falha ao arquivar.",
            timer: 3000,
            timerProgressBar: true,
          });
        }
      } catch (err) {
        console.error(err);
        Swal.fire({
          icon: "error",
          title: "Erro",
          text: "Falha a arquivar (ver console).",
          timer: 3000,
          timerProgressBar: true,
        });
      }
    });

    // ── Excluir ────────────────────────────────────────────────────────
    m.querySelector(".ctx-delete").addEventListener("click", async () => {
      const card = currentCard;
      hideMenu();
      if (!card) return;
      const entregaId = card.dataset.id;
      const titleCard =
        card.querySelector(".card-header h4")?.textContent ||
        `Entrega ${entregaId}`;
      const { isConfirmed } = await Swal.fire({
        title: "Excluir entrega?",
        text: `"${titleCard}" e todos seus itens serão removidos permanentemente.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sim, excluir",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#ef4444",
      });
      if (!isConfirmed) return;
      try {
        const response = await fetch(BASE + "remove_entrega.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ entrega_id: entregaId }),
        });
        const json = await response.json();
        if (json && json.success) {
          const cardEl = document.querySelector(
            `.card-entrega[data-id="${entregaId}"]`,
          );
          if (cardEl) cardEl.remove();
          if (typeof window.carregarKanban === "function")
            window.carregarKanban();
          Swal.fire({
            icon: "success",
            title: "Excluído",
            text: json.message || "Entrega excluída.",
            timer: 2000,
            timerProgressBar: true,
          });
        } else {
          Swal.fire({
            icon: "error",
            title: "Erro",
            text: json.error || "Falha ao excluir.",
            timer: 3000,
            timerProgressBar: true,
          });
        }
      } catch (err) {
        console.error(err);
        Swal.fire({
          icon: "error",
          title: "Erro",
          text: "Falha ao excluir (ver console).",
          timer: 3000,
          timerProgressBar: true,
        });
      }
    });

    return m;
  }

  async function _callUpdate(entregaId, payload, successMsg) {
    try {
      payload.entrega_id = entregaId;
      const res = await fetch(BASE + "update_entrega.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const json = await res.json();
      if (json && json.success) {
        _toast(successMsg);
        if (typeof window.carregarKanban === "function")
          window.carregarKanban();
      } else {
        Swal.fire({
          icon: "error",
          title: "Erro",
          text: json.error || "Falha ao atualizar.",
          timer: 3000,
          timerProgressBar: true,
        });
      }
    } catch (err) {
      console.error(err);
      Swal.fire({
        icon: "error",
        title: "Erro",
        text: "Falha ao atualizar (ver console).",
        timer: 3000,
        timerProgressBar: true,
      });
    }
  }

  function _toast(msg, color) {
    if (typeof Toastify === "undefined") return;
    Toastify({
      text: msg,
      duration: 3000,
      gravity: "top",
      position: "right",
      style: {
        background: color || "#10b981",
        borderRadius: "8px",
        fontFamily: '"Inter", sans-serif',
        fontSize: "13px",
        fontWeight: "500",
      },
    }).showToast();
  }

  function showMenu(card) {
    if (!card) return;
    currentCard = card;
    const menu = getMenu();
    const canUnarchive = card.dataset.canUnarchive === "1";
    const modoArq = window._modoArquivadas || false;
    const archiveBtn = menu.querySelector(".ctx-archive");
    if (archiveBtn) {
      if (modoArq && canUnarchive) {
        archiveBtn.innerHTML =
          '<i class="fa-solid fa-box-open"></i> Desarquivar';
        archiveBtn.style.display = "";
      } else if (modoArq) {
        // inactive-obra entrega — cannot unarchive
        archiveBtn.style.display = "none";
      } else {
        archiveBtn.innerHTML =
          '<i class="fa-solid fa-box-archive"></i> Arquivar';
        archiveBtn.style.display = "";
      }
    }

    // Position menu to the right of the card (flip left if no space)
    const rect = card.getBoundingClientRect();
    const menuW = 240;
    const menuH = 190;
    let left = rect.right + 8 + window.scrollX;
    if (rect.right + menuW + 16 > window.innerWidth) {
      left = rect.left - menuW - 8 + window.scrollX;
    }
    let top = rect.top + window.scrollY;
    if (top + menuH > window.scrollY + window.innerHeight - 10) {
      top = window.scrollY + window.innerHeight - menuH - 10;
    }
    if (top < window.scrollY + 10) top = window.scrollY + 10;

    menu.style.left = Math.max(10, left) + "px";
    menu.style.top = Math.max(10, top) + "px";
    menu.style.display = "block";
  }

  function hideMenu() {
    const m = document.getElementById(MENU_ID);
    if (m) m.style.display = "none";
    currentCard = null;
  }

  // Right-click on any card
  document.addEventListener("contextmenu", function (e) {
    const card = e.target.closest && e.target.closest(".card-entrega");
    if (!card) return;
    e.preventDefault();
    showMenu(card);
  });

  // Close on outside click
  document.addEventListener("click", function (e) {
    const menu = document.getElementById(MENU_ID);
    if (!menu || menu.style.display === "none") return;
    if (menu.contains(e.target)) return;
    if (e.target.closest && e.target.closest(".card-entrega")) return;
    hideMenu();
  });

  // Close on Escape
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") hideMenu();
  });

  // Long-press (touch)
  let touchTimer = null;
  document.addEventListener(
    "touchstart",
    function (e) {
      const card = e.target.closest && e.target.closest(".card-entrega");
      if (!card) return;
      touchTimer = setTimeout(() => {
        card.dataset.suppressClick = "1";
        showMenu(card);
      }, 600);
    },
    { passive: true },
  );

  ["touchend", "touchcancel", "touchmove"].forEach((ev) => {
    document.addEventListener(
      ev,
      function () {
        if (touchTimer) {
          clearTimeout(touchTimer);
          touchTimer = null;
        }
      },
      { passive: true },
    );
  });

  // Intercept suppressed click (after long-press)
  document.addEventListener(
    "click",
    function (e) {
      const card = e.target.closest && e.target.closest(".card-entrega");
      if (card && card.dataset && card.dataset.suppressClick === "1") {
        e.stopImmediatePropagation();
        e.preventDefault();
        card.dataset.suppressClick = "0";
      }
    },
    true,
  );

  // ── "Ver arquivadas" button ──────────────────────────────────────────
  document.addEventListener("DOMContentLoaded", function () {
    const btn = document.getElementById("btnVerArquivadas");
    if (!btn) return;
    btn.addEventListener("click", function () {
      window._modoArquivadas = !window._modoArquivadas;
      btn.classList.toggle("btn-arquivadas-active", window._modoArquivadas);
      btn.innerHTML = window._modoArquivadas
        ? '<i class="fa-solid fa-arrow-left"></i> Voltar às ativas'
        : '<i class="fa-solid fa-box-archive"></i> Ver arquivadas';
      if (typeof window.carregarKanban === "function") window.carregarKanban();
    });
  });
})();

// --- ADICIONAR IMAGEM POR ID (botão no modal de entrega) ---
const btnAdicionarImagem = document.getElementById("btnAdicionarImagem");
if (btnAdicionarImagem) {
  btnAdicionarImagem.addEventListener("click", async function () {
    if (!entregaAtualId || !entregaDados) {
      alert("Abra primeiro uma entrega clicando no card.");
      return;
    }

    // Sugestão: pedir ao usuário uma lista de ids separados por vírgula
    const raw = prompt(
      "Digite o(s) id(s) de imagens (imagens_cliente_obra.idimagens_cliente_obra). Separe por vírgula para múltiplos:",
    );
    if (!raw) return;
    const ids = raw
      .split(",")
      .map((s) => parseInt(s.trim()))
      .filter((n) => !isNaN(n) && n > 0);
    if (ids.length === 0) {
      alert("Nenhum id válido informado.");
      return;
    }

    try {
      const res = await fetch(BASE + "add_imagem_entrega_id.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ entrega_id: entregaAtualId, imagem_ids: ids }),
      });
      const json = await res.json();
      if (json.success) {
        alert("Imagens adicionadas com sucesso: " + (json.added_count || 0));
        // atualizar a view
        document.getElementById("entregaModal").classList.remove("is-open");
        entregaAtualId = null;
        entregaDados = null;
        carregarKanban();
      } else {
        alert("Erro: " + (json.error || "desconhecido"));
      }
    } catch (err) {
      console.error("Erro ao adicionar imagens:", err);
      alert("Erro ao adicionar imagens (ver console)");
    }
  });
}
