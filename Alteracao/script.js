let idImagemSelecionada = null;
let sortableInstances = [];
const selectedCards = new Set();
let _filterDebounceTimer = null;
let cardsCompactos = false;

const STATUS_COLUMNS = [
  { label: "Não iniciado", key: "nao-iniciado" },
  { label: "Em andamento", key: "em-andamento" },
  { label: "Em aprovação", key: "em-aprovacao" },
  { label: "Finalizado", key: "finalizado" },
];

function normalizarStatus(status) {
  return (status || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}

function getStatusKey(status) {
  const n = normalizarStatus(status);
  if (n === "nao iniciado") return "nao-iniciado";
  if (n === "em andamento") return "em-andamento";
  if (n === "em aprovacao") return "em-aprovacao";
  if (n === "finalizado") return "finalizado";
  return "nao-iniciado";
}

function getEFKanbanStatusClass(status) {
  const n = normalizarStatus(status);
  if (n === "finalizado") return "ef-ks-done";
  if (n === "em andamento") return "ef-ks-wip";
  if (n === "em aprovacao") return "ef-ks-review";
  return "ef-ks-todo";
}

function getFilters() {
  return {
    status: document.getElementById("filtro-status").value,
    status_imagem: document.getElementById("filtro-status-imagem").value,
    obra_id: document.getElementById("filtro-obra").value,
    colaborador_id: document.getElementById("filtro-colaborador").value,
    busca: document.getElementById("filtro-busca").value.trim(),
  };
}

function montarQueryString(filtros) {
  const params = new URLSearchParams();
  Object.entries(filtros).forEach(([key, value]) => {
    if (value !== "" && value !== null && value !== undefined) {
      params.append(key, value);
    }
  });
  return params.toString();
}

function limparSelecao() {
  selectedCards.clear();
  document
    .querySelectorAll(".imagem-card.selected")
    .forEach((card) => card.classList.remove("selected"));
}

function preencherFiltros(filtros) {
  const selectObra = document.getElementById("filtro-obra");
  const selectColab = document.getElementById("filtro-colaborador");
  const selectStatusImagem = document.getElementById("filtro-status-imagem");

  const obraAtual = selectObra.value;
  const colabAtual = selectColab.value;
  const statusImagemAtual = selectStatusImagem.value;

  selectObra.innerHTML = '<option value="">Todas</option>';
  (filtros?.obras || []).forEach((obra) => {
    const option = document.createElement("option");
    option.value = obra.id;
    option.textContent = obra.nome;
    selectObra.appendChild(option);
  });
  if (obraAtual) selectObra.value = obraAtual;

  selectColab.innerHTML = '<option value="">Todos</option>';
  (filtros?.colaboradores || []).forEach((colab) => {
    const option = document.createElement("option");
    option.value = colab.id;
    option.textContent = colab.nome;
    selectColab.appendChild(option);
  });
  if (colabAtual) selectColab.value = colabAtual;

  selectStatusImagem.innerHTML = '<option value="">Todos</option>';
  (filtros?.status_imagens || []).forEach((status) => {
    const option = document.createElement("option");
    option.value = status.nome;
    option.textContent = status.nome;
    selectStatusImagem.appendChild(option);
  });
  if (statusImagemAtual) selectStatusImagem.value = statusImagemAtual;
}

function agruparPorObra(items) {
  return items.reduce((acc, item) => {
    const key = `${item.obra_id}`;
    if (!acc[key]) acc[key] = { obra_nome: item.obra_nome, items: [] };
    acc[key].items.push(item);
    return acc;
  }, {});
}

function applyStatusImagem(cell, status) {
  const classMap = {
    P00: "si-p00",
    R00: "si-r00",
    R01: "si-r01",
    R02: "si-r02",
    R03: "si-r03",
    R04: "si-r04",
    R05: "si-r05",
    EF: "si-ef",
    HOLD: "si-hold",
    TEA: "si-tea",
    REN: "si-ren",
    APR: "si-apr",
    APP: "si-app",
    RVW: "si-rvw",
    OK: "si-ok",
    "TO-DO": "si-to-do",
    FIN: "si-fin",
    DRV: "si-drv",
    RVW_DONE: "si-rvw-done",
    PRE_ALT: "si-pre-alt",
    READY_FOR_PLANNING: "si-ready-for-planning",
  };
  const cls = classMap[status];
  if (cls) cell.classList.add(cls);
}

function criarCard(item) {
  const card = document.createElement("div");
  card.className = "imagem-card";
  card.dataset.imagemId = item.imagem_id;
  card.dataset.funcaoId = String(item.funcao_id);

  if (!item.colaborador_id) card.classList.add("sem-colaborador");
  if (item.is_ef) card.classList.add("ef-card");
  if (cardsCompactos) card.classList.add("card-compact");

  const efLabelHtml = item.is_ef
    ? `<div class="ef-label"><i class="fa-solid fa-bolt"></i> Render em Alta</div>`
    : "";

  card.innerHTML = `
    ${efLabelHtml}
    <span class="badge">${item.status_nome}</span>
    <div class="card-title">${item.imagem_nome}</div>
    <div class="card-sub"><i class="fa-solid fa-building"></i> ${item.obra_nome}</div>
    <div class="card-footer">
      <div class="card-meta"><i class="fa-solid fa-user"></i> ${item.colaborador_nome || "—"}</div>
      <div class="card-meta"><i class="fa-solid fa-calendar"></i> ${item.prazo || "—"}</div>
    </div>
  `;

  applyStatusImagem(card.querySelector(".badge"), item.status_nome);

  card.addEventListener("click", (event) => {
    const funcaoId = String(item.funcao_id);
    if (event.ctrlKey || event.metaKey) {
      event.preventDefault();
      if (selectedCards.has(funcaoId)) {
        selectedCards.delete(funcaoId);
        card.classList.remove("selected");
      } else {
        selectedCards.add(funcaoId);
        card.classList.add("selected");
      }
      return;
    }
    limparSelecao();
    abrirModal(item.imagem_id);
  });

  return card;
}

function renderEFPanel(items) {
  const efItems = items.filter((i) => i.is_ef);
  const body = document.getElementById("ef-panel-body");
  const countEl = document.getElementById("ef-panel-count");

  if (countEl) countEl.textContent = String(efItems.length);
  if (!body) return;

  body.innerHTML = "";

  if (efItems.length === 0) {
    body.innerHTML = `
      <div class="ef-panel-empty">
        <i class="fa-solid fa-check"></i>
        <span>Nenhum EF</span>
      </div>`;
    return;
  }

  efItems.forEach((item) => {
    const div = document.createElement("div");
    div.className = "ef-item";
    div.dataset.imagemId = item.imagem_id;

    const ksCls = getEFKanbanStatusClass(item.status_funcao);
    const dateHtml = item.prazo
      ? `<div class="ef-item-date"><i class="fa-solid fa-calendar"></i> ${item.prazo}</div>`
      : "";

    div.innerHTML = `
      <div class="ef-item-top">
        <span class="badge si-ef" style="position:static;display:inline-block;">EF</span>
        <span class="ef-item-kanban-status ${ksCls}">${item.status_funcao}</span>
      </div>
      <div class="ef-item-title">${item.imagem_nome}</div>
      <div class="ef-item-meta"><i class="fa-solid fa-building"></i> ${item.obra_nome}</div>
      <div class="ef-item-footer">
        ${dateHtml}
        <div class="card-meta"><i class="fa-solid fa-user"></i> ${item.colaborador_nome || "—"}</div>
      </div>
    `;

    div.addEventListener("click", () => abrirModal(item.imagem_id));
    body.appendChild(div);
  });
}

function renderKanban(items) {
  STATUS_COLUMNS.forEach(({ key }) => {
    const container = document.getElementById(`kanban-${key}`);
    if (container) container.innerHTML = "";
  });

  // Update results count
  const resultsCountEl = document.getElementById("resultsCount");
  if (resultsCountEl) resultsCountEl.textContent = String(items.length);

  // Render EF panel
  renderEFPanel(items);

  const obraFiltrada = document.getElementById("filtro-obra").value !== "";

  STATUS_COLUMNS.forEach(({ label, key }) => {
    const container = document.getElementById(`kanban-${key}`);
    if (!container) return;

    const itensColuna = items.filter(
      (item) => getStatusKey(item.status_funcao) === key,
    );

    const countElement = document.getElementById(`count-${key}`);
    if (countElement) countElement.textContent = String(itensColuna.length);

    const efCountEl = document.getElementById(`ef-count-${key}`);
    if (efCountEl) {
      const efQty = itensColuna.filter((i) => i.is_ef).length;
      const efSpan = efCountEl.querySelector("span");
      if (efSpan) efSpan.textContent = String(efQty);
      efCountEl.style.display = efQty > 0 ? "flex" : "none";
    }

    if (itensColuna.length === 0) {
      const list = document.createElement("div");
      list.className = "cards-list";
      const empty = document.createElement("div");
      empty.className = "empty-state";
      empty.innerHTML =
        '<i class="fa-solid fa-inbox"></i><span>Nenhum item</span>';
      list.appendChild(empty);
      container.appendChild(list);
      return;
    }

    if (obraFiltrada) {
      const grupos = agruparPorObra(itensColuna);
      let cardIndex = 0;
      Object.values(grupos).forEach((grupo) => {
        const groupContainer = document.createElement("div");
        groupContainer.className = "obra-group";
        groupContainer.innerHTML = `<div class="obra-title">${grupo.obra_nome}</div>`;
        const list = document.createElement("div");
        list.className = "cards-list";
        grupo.items.forEach((item) => {
          const card = criarCard(item);
          card.style.animationDelay = `${cardIndex * 40}ms`;
          list.appendChild(card);
          cardIndex++;
        });
        groupContainer.appendChild(list);
        container.appendChild(groupContainer);
      });
      return;
    }

    const list = document.createElement("div");
    list.className = "cards-list";
    itensColuna.forEach((item, i) => {
      const card = criarCard(item);
      card.style.animationDelay = `${i * 40}ms`;
      list.appendChild(card);
    });
    container.appendChild(list);
  });

  inicializarDragAndDrop();
}

function inicializarDragAndDrop() {
  sortableInstances.forEach((instance) => instance.destroy());
  sortableInstances = [];

  document.querySelectorAll(".cards-list").forEach((list) => {
    const instance = new Sortable(list, {
      group: "alteracao-kanban",
      animation: 120,
      ghostClass: "drag-ghost",
      onStart: (evt) => {
        const funcaoId = evt.item?.dataset?.funcaoId;
        if (funcaoId && !selectedCards.has(funcaoId)) {
          limparSelecao();
          selectedCards.add(funcaoId);
          evt.item.classList.add("selected");
        }
      },
      onEnd: (evt) => {
        const coluna = evt.to.closest(".kanban-column");
        const statusDestino = coluna?.dataset?.status;
        if (!statusDestino) {
          recarregarAlteracao();
          return;
        }

        const funcaoId = evt.item?.dataset?.funcaoId;
        const ids =
          selectedCards.size > 0 && funcaoId && selectedCards.has(funcaoId)
            ? Array.from(selectedCards)
            : funcaoId
              ? [funcaoId]
              : [];

        if (ids.length === 0) {
          recarregarAlteracao();
          return;
        }
        atualizarStatusLote(ids, statusDestino);
      },
    });
    sortableInstances.push(instance);
  });
}

function atualizarStatusLote(ids, statusDestino) {
  const atribuirLogado = normalizarStatus(statusDestino) === "em andamento";

  fetch("updateStatusLote.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      funcao_ids: ids,
      status: statusDestino,
      atribuir_logado: atribuirLogado,
    }),
  })
    .then((r) => r.json())
    .then((data) => {
      if (!data.success) {
        Toastify({
          text: data.message || "Erro ao atualizar status.",
          duration: 3200,
          gravity: "top",
          position: "right",
          style: {
            background: "#ef4444",
            borderRadius: "8px",
            fontFamily: '"Inter",sans-serif',
            fontSize: "13px",
          },
        }).showToast();
        recarregarAlteracao();
        return;
      }
      Toastify({
        text: "Status atualizado!",
        duration: 2500,
        gravity: "top",
        position: "right",
        style: {
          background: "#10b981",
          borderRadius: "8px",
          fontFamily: '"Inter",sans-serif',
          fontSize: "13px",
        },
      }).showToast();
      recarregarAlteracao();
    })
    .catch(() => {
      Toastify({
        text: "Erro ao atualizar status.",
        duration: 3200,
        gravity: "top",
        position: "right",
        style: {
          background: "#ef4444",
          borderRadius: "8px",
          fontFamily: '"Inter",sans-serif',
          fontSize: "13px",
        },
      }).showToast();
      recarregarAlteracao();
    });
}

function recarregarAlteracao() {
  limparSelecao();
  const filtros = getFilters();
  const query = montarQueryString(filtros);

  STATUS_COLUMNS.forEach(({ key }) => {
    const container = document.getElementById(`kanban-${key}`);
    if (container) {
      container.innerHTML =
        '<div class="cards-list">' +
        '<div class="skeleton-card"></div>'.repeat(3) +
        "</div>";
    }
  });

  fetch(`getAlteracao.php${query ? `?${query}` : ""}`)
    .then((r) => r.json())
    .then((data) => {
      if (!data.success)
        throw new Error(data.message || "Erro ao carregar dados.");
      preencherFiltros(data.filtros);
      renderKanban(data.items || []);
    })
    .catch((error) => console.error("Erro ao carregar Kanban:", error));
}

// ─── Modal ───
function abrirModal(idimagem) {
  const modal = document.getElementById("myModal");
  if (modal) modal.classList.add("is-open");
  atualizarModal(idimagem);
  idImagemSelecionada = idimagem;
}

function fecharModal() {
  const modal = document.getElementById("myModal");
  if (modal) modal.classList.remove("is-open");
}

function limparCampos() {
  document.getElementById("campoNomeImagem").textContent = "—";
  document.getElementById("status_alteracao").value = "";
  document.getElementById("prazo_alteracao").value = "";
  document.getElementById("obs_alteracao").value = "";
  document.getElementById("opcao_alteracao").value = "";
}

function atualizarModal(idImagem) {
  limparCampos();

  fetch(`../buscaLinhaAJAX.php?ajid=${idImagem}`)
    .then((r) => r.json())
    .then((response) => {
      if (response.funcoes && response.funcoes.length > 0) {
        document.getElementById("campoNomeImagem").textContent =
          response.funcoes[0].imagem_nome;

        response.funcoes.forEach((funcao) => {
          if (funcao.nome_funcao === "Alteração") {
            document.getElementById("opcao_alteracao").value =
              funcao.colaborador_id || "";
            document.getElementById("status_alteracao").value =
              funcao.status || "Não iniciado";
            document.getElementById("prazo_alteracao").value =
              funcao.prazo || "";
            document.getElementById("obs_alteracao").value =
              funcao.observacao || "";
          }
        });
      }
    })
    .catch((error) => console.error("Erro ao buscar dados da linha:", error));
}

document
  .getElementById("salvar_funcoes")
  .addEventListener("click", function () {
    if (!idImagemSelecionada) {
      Toastify({
        text: "Nenhuma imagem selecionada",
        duration: 3000,
        gravity: "top",
        position: "right",
        style: {
          background: "#ef4444",
          borderRadius: "8px",
          fontFamily: '"Inter",sans-serif',
          fontSize: "13px",
        },
      }).showToast();
      return;
    }

    const dados = {
      imagem_id: idImagemSelecionada,
      funcao_id: 6,
      colaborador_id: document.getElementById("opcao_alteracao").value || "",
      status: document.getElementById("status_alteracao").value || "",
      prazo: document.getElementById("prazo_alteracao").value || "",
      observacao: document.getElementById("obs_alteracao").value || "",
    };

    $.ajax({
      type: "POST",
      url: "../insereFuncao.php",
      data: dados,
      success: function () {
        Toastify({
          text: "Dados salvos com sucesso!",
          duration: 3000,
          gravity: "top",
          position: "right",
          style: {
            background: "#10b981",
            borderRadius: "8px",
            fontFamily: '"Inter",sans-serif',
            fontSize: "13px",
          },
        }).showToast();
        fecharModal();
        recarregarAlteracao();
      },
      error: function (jqXHR, textStatus, errorThrown) {
        console.error("Erro ao salvar:", textStatus, errorThrown);
        Toastify({
          text: "Erro ao salvar dados.",
          duration: 3000,
          gravity: "top",
          position: "right",
          style: {
            background: "#ef4444",
            borderRadius: "8px",
            fontFamily: '"Inter",sans-serif',
            fontSize: "13px",
          },
        }).showToast();
      },
    });
  });

// Fechar modal pelos botões e overlay
document.getElementById("closeModal").addEventListener("click", fecharModal);
document.getElementById("closeModalBtn").addEventListener("click", fecharModal);
document.getElementById("myModal").addEventListener("click", function (e) {
  if (e.target === this) fecharModal();
});

// ─── Filtros ───
document
  .getElementById("btn-aplicar-filtros")
  .addEventListener("click", recarregarAlteracao);
document.getElementById("btn-limpar-filtros").addEventListener("click", () => {
  document.getElementById("filtro-status").value = "";
  document.getElementById("filtro-status-imagem").value = "";
  document.getElementById("filtro-obra").value = "";
  document.getElementById("filtro-colaborador").value = "";
  document.getElementById("filtro-busca").value = "";
  recarregarAlteracao();
});

[
  "filtro-status",
  "filtro-status-imagem",
  "filtro-obra",
  "filtro-colaborador",
].forEach((id) => {
  document.getElementById(id).addEventListener("change", recarregarAlteracao);
});

document.getElementById("filtro-busca").addEventListener("input", () => {
  clearTimeout(_filterDebounceTimer);
  _filterDebounceTimer = setTimeout(recarregarAlteracao, 350);
});

// ─── Compact toggle (all cards) ───
const btnToggleCompact = document.getElementById("btn-toggle-compact");
if (btnToggleCompact) {
  btnToggleCompact.addEventListener("click", () => {
    cardsCompactos = !cardsCompactos;
    document.querySelectorAll(".imagem-card").forEach((card) => {
      card.classList.toggle("card-compact", cardsCompactos);
    });
    btnToggleCompact.classList.toggle("ativo", cardsCompactos);
    btnToggleCompact.innerHTML = cardsCompactos
      ? '<i class="fa-solid fa-expand"></i> Expandir'
      : '<i class="fa-solid fa-compress"></i> Compactar';
  });
}

// ESC fecha modal
window.addEventListener("keydown", (e) => {
  if (e.key === "Escape") fecharModal();
});

recarregarAlteracao();
