document.addEventListener("DOMContentLoaded", () => {
  const navLinks = document.querySelectorAll(".nav a"); // Seleciona todos os links dentro da classe .nav
  const currentPage = window.location.pathname.split("/").pop(); // Obtém o nome do arquivo atual da URL

  navLinks.forEach((link) => {
    const linkHref = link.getAttribute("href"); // Obtém o valor do href de cada link
    if (linkHref === currentPage) {
      link.classList.add("active"); // Adiciona a classe active se o link corresponder à página atual
    }
  });
});

function formatarData(data) {
  const partes = data.split("-");
  const dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
  return dataFormatada;
}

function mostrarImagens() {
  // Mostra as imagens restantes
  document.getElementById("imagens-restantes").style.display = "block";
  // Esconde o botão após clicar
  document.getElementById("mostrar-mais").style.display = "none";
}

const modalColab = document.getElementById("filtro-colab");

var colaboradorId = localStorage.getItem("idcolaborador");

function setTextContent(id, value) {
  const element = document.getElementById(id);
  if (element) {
    element.textContent = value;
  }
}

function formatCurrency(value) {
  const numeric = Number(value || 0);
  if (!Number.isFinite(numeric)) {
    return "R$ 0,00";
  }

  return `R$ ${numeric.toLocaleString("pt-BR", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

function renderTrend(elementId, current, previous) {
  const el = document.getElementById(elementId);
  if (!el) return;
  if (!previous || previous === 0) {
    el.className = "kpi-trend is-flat";
    el.innerHTML = '<i class="fa-solid fa-minus"></i><span>sem histórico</span>';
    return;
  }
  const delta = ((current - previous) / Math.abs(previous)) * 100;
  const isUp = delta > 0.5;
  const isDown = delta < -0.5;
  const sign = isUp ? "+" : "";
  const icon = isUp ? "fa-arrow-trend-up" : isDown ? "fa-arrow-trend-down" : "fa-minus";
  const cls = isUp ? "is-up" : isDown ? "is-down" : "is-flat";
  el.className = `kpi-trend ${cls}`;
  el.innerHTML = `<i class="fa-solid ${icon}"></i><span>${sign}${delta.toFixed(1)}%</span>`;
}

fetch("atualizarValores.php")
  .then((response) => response.json())
  .then((data) => {
    if (data && data.length > 0) {
      const valores = data[0];

      const totalOrcamento      = Number(valores.total_orcamento      || 0);
      const totalProducao       = Number(valores.total_producao        || 0);
      const obrasAtivas         = Number(valores.obras_ativas          || 0);
      const producaoMesAtual    = Number(valores.producao_mes_atual    || 0);
      const producaoMesAnterior = Number(valores.producao_mes_anterior || 0);
      const obrasAtivas3mAgo    = Number(valores.obras_ativas_3m_ago   || 0);

      if (!isNaN(totalOrcamento)) {
        setTextContent("total_orcamentos", formatCurrency(totalOrcamento));
      }

      if (!isNaN(totalProducao)) {
        setTextContent("total_producao", formatCurrency(totalProducao));
      }

      setTextContent("obras_ativas", String(obrasAtivas));

      renderTrend("trend_producao", producaoMesAtual, producaoMesAnterior);
      renderTrend("trend_obras",    obrasAtivas,      obrasAtivas3mAgo);
    } else {
      console.error("Dados não encontrados");
    }
  })
  .catch((error) => {
    console.error("Erro ao buscar dados:", error);
  });


let chartInstance = null;

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function buildChecklistBadge(pendingItems) {
  const pending = parseInt(pendingItems, 10) || 0;
  if (pending <= 0) {
    return "";
  }

  return `<span class="kanban-checklist-badge" title="${pending} item(ns) pendente(s) no checklist">${pending}</span>`;
}

function buildKanbanEmptyState(options) {
  const isOnboarding = options.mode === "onboarding";
  const icon = isOnboarding ? "fa-clipboard-check" : "fa-inbox";
  const title = isOnboarding
    ? "Nenhuma obra pendente"
    : "Nenhuma obra nesta fila";
  const message = isOnboarding
    ? "Tudo em dia!"
    : "Os cards operacionais aparecerão aqui conforme o fluxo avançar.";

  return `
    <div class="kanban-empty ${isOnboarding ? "is-onboarding" : ""}">
      <div class="kanban-empty-icon"><i class="fa-solid ${icon}"></i></div>
      <strong>${title}</strong>
      <span>${message}</span>
    </div>`;
}

function renderKanbanColumn(options) {
  const container = document.getElementById(options.containerId);
  const counter = document.getElementById(options.countId);
  if (!container || !counter) {
    return;
  }

  const items = Array.isArray(options.items) ? options.items : [];
  const cards = [];

  let totalCount = 0;
  if (!items.length) {
    container.innerHTML = buildKanbanEmptyState(options);
    counter.textContent = "0";
    return;
  }

  items.forEach((item) => {
    const obraId = item.idobra;
    const title = escapeHtml(item.nome_obra || "");
    const count = parseInt(item.total_imagens, 10) || 0;
    const totalObra = parseInt(item.total_obra, 10) || count;
    const pendingChecklistItems =
      parseInt(item.pending_checklist_items, 10) || 0;
    const checklistBadge = buildChecklistBadge(pendingChecklistItems);

    let primaryMeta = `Total imagens: ${count}`;
    let secondaryMeta = `Carga da obra: ${totalObra}`;
    let progressText = `${count}/${totalObra}`;
    let progressPct = totalObra > 0 ? Math.round((count / totalObra) * 100) : 0;
    let cardClasses = "kanban-card";

    if (options.mode === "onboarding") {
      const completedItems = Math.max(0, 5 - pendingChecklistItems);
      primaryMeta = `Imagens importadas: ${count}`;
      secondaryMeta = `${pendingChecklistItems} pendência(s) operacional(is)`;
      progressText = `${completedItems}/5`;
      progressPct = Math.round((completedItems / 5) * 100);
      cardClasses += " is-onboarding";
      totalCount += pendingChecklistItems;
    } else {
      totalCount += count;
    }

    cards.push(`
      <div class="${cardClasses}" data-id="${obraId}" id="${options.cardPrefix}-${obraId}">
        <div class="kanban-card-top">
          <span class="priority ${options.priorityClass}">${options.priorityLabel}</span>
          <span class="kanban-progress-text">${progressText}</span>
        </div>
        <div class="kanban-card-middle">
          <div class="kanban-card-main">
            <h5><span class="kanban-card-title">${title}</span>${checklistBadge}</h5>
            <p class="kanban-card-subtitle">${primaryMeta}</p>
            <p class="kanban-card-meta">${secondaryMeta}</p>
          </div>
          <div class="kanban-card-progress">
            <div class="progress-bar"><span class="progress-fill" style="width: ${progressPct}%;"></span></div>
          </div>
        </div>
      </div>`);
  });

  container.innerHTML = cards.join("");
  counter.textContent = String(totalCount);
}

fetch("obras.php")
  .then((res) => res.json())
  .then((data) => {
    renderKanbanColumn({
      containerId: "onboarding-cards",
      countId: "count-onboarding",
      items: data.onboarding || [],
      cardPrefix: "obra-onboarding",
      priorityClass: "onboarding",
      priorityLabel: "Start",
      mode: "onboarding",
    });

    renderKanbanColumn({
      containerId: "hold-cards",
      countId: "count-hold",
      items: data.hold || [],
      cardPrefix: "obra-hold",
      priorityClass: "baixa",
      priorityLabel: "Obra",
      mode: "producao",
    });

    renderKanbanColumn({
      containerId: "andamento-cards",
      countId: "count-andamento",
      items: data.esperando || [],
      cardPrefix: "obra-esperando",
      priorityClass: "media",
      priorityLabel: "Obra",
      mode: "producao",
    });

    renderKanbanColumn({
      containerId: "finalizadas-cards",
      countId: "count-finalizadas",
      items: data.producao || [],
      cardPrefix: "obra-producao",
      priorityClass: "alta",
      priorityLabel: "Obra",
      mode: "producao",
    });
  })
  .catch((err) => {
    console.error("Erro ao carregar obras:", err);
  });

// Ao clicar em um card do kanban, salva o id da obra e vai para obra.php
document.addEventListener("click", (e) => {
  const card = e.target.closest && e.target.closest(".kanban-card");
  if (!card) return;

  const obraId = card.getAttribute("data-id");
  if (!obraId) return;

  localStorage.setItem("obraId", String(obraId));
  window.location.href = "obra.php";
});

function applyStatusImagem(cell, status) {
  switch (status) {
    case "P00":
      cell.style.backgroundColor = "#ffc21c";
      break;
    case "R00":
      cell.style.backgroundColor = "#1cf4ff";
      break;
    case "R01":
      cell.style.backgroundColor = "#ff6200";
      break;
    case "R02":
      cell.style.backgroundColor = "#ff3c00";
      break;
    case "R03":
      cell.style.backgroundColor = "#ff0000";
      break;
    case "EF":
      cell.style.backgroundColor = "#0dff00";
      break;
    case "HOLD":
      cell.style.backgroundColor = "#ff0000";
      break;
    case "TEA":
      cell.style.backgroundColor = "#f7eb07";
      break;
    case "REN":
      cell.style.backgroundColor = "#0c9ef2";
      break;
    case "APR":
      cell.style.backgroundColor = "#0c45f2";
      break;
    case "APP":
      cell.style.backgroundColor = "#7d36f7";
  }
}

document.getElementById("orcamento").addEventListener("click", function () {
  document.getElementById("modalOrcamento").style.display = "flex";
});

document
  .getElementById("formOrcamento")
  .addEventListener("submit", function (e) {
    e.preventDefault();

    const idObra = document.getElementById("idObraOrcamento").value;
    const tipo = document.getElementById("tipo").value;
    const valor = document.getElementById("valor").value;
    const data = document.getElementById("data").value;

    // Enviar os dados para o backend
    fetch("salvarOrcamento.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ idObra, tipo, valor, data }),
    })
      .then((response) => response.json())
      .then((data) => {
        alert("Orçamento salvo com sucesso!");
        document.getElementById("modalOrcamento").style.display = "none"; // Fecha o modal
      })
      .catch((error) => {
        console.error("Erro ao salvar orçamento:", error);
      });
  });

const modalInfos = document.getElementById("modalInfos");
const modalOrcamento = document.getElementById("modalOrcamento");
window.onclick = function (event) {
  if (event.target == modalInfos) {
    modalInfos.style.display = "none";
  }
  if (event.target == modalOrcamento) {
    modalOrcamento.style.display = "none";
  }
  if (event.target == modalColab) {
    modalColab.style.display = "none";
  }
  if (event.target == modalLogs) {
    modalLogs.style.display = "none";
  }
};

window.addEventListener("touchstart", function (event) {
  if (event.target == modalInfos) {
    modalInfos.style.display = "none";
  }
  if (event.target == modalOrcamento) {
    modalOrcamento.style.display = "none";
  }
});

document.addEventListener("DOMContentLoaded", function () {
  const cards = document.querySelectorAll(".stat-card");
  let currentIndex = 0;

  // Exibe o primeiro card
  cards[currentIndex].classList.add("active");

  function nextCard() {
    cards[currentIndex].classList.remove("active");

    currentIndex = (currentIndex + 1) % cards.length;

    cards[currentIndex].classList.add("active");
  }

  setInterval(nextCard, 3000); // 3000 ms = 3 segundos
});

// Obtém o 'obra_id' do localStorage
var obraId = localStorage.getItem("obraId");

if (obraId) {
  abrirModalAcompanhamento(obraId); // Carrega os acompanhamentos automaticamente
} else {
  console.warn("ID da obra não encontrado no localStorage.");
}

// Adiciona o botão de mostrar todos
const btnMostrarAcomps = document.getElementById("btnMostrarAcomps");
const acompanhamentoConteudo = document.getElementById("list_acomp");
// Ao clicar no botão "Mostrar Todos"
btnMostrarAcomps.addEventListener("click", () => {
  acompanhamentoConteudo.classList.toggle("expanded");
  const isExpanded = acompanhamentoConteudo.classList.contains("expanded");
  btnMostrarAcomps.innerHTML = isExpanded
    ? '<i class="fas fa-chevron-up"></i>'
    : '<i class="fas fa-chevron-down"></i>';
});

function abrirModalAcompanhamento(obraId) {
  fetch(`../Obras/getAcompanhamentoEmail.php?idobra=${obraId}`)
    .then((response) => {
      if (!response.ok) {
        throw new Error(`Erro ao carregar dados: ${response.status}`);
      }
      return response.json(); // Converte a resposta para JSON
    })
    .then((acompanhamentos) => {
      // Limpa o conteúdo anterior
      acompanhamentoConteudo.innerHTML = "";

      if (acompanhamentos.length > 0) {
        acompanhamentos.forEach((acomp) => {
          const item = document.createElement("p");
          item.innerHTML = `
                        <div class="acomp-conteudo">
                            <p class="acomp-assunto"><strong>Assunto:</strong> ${acomp.assunto}</p>
                            <p class="acomp-data"><strong>Data:</strong> ${formatarData(acomp.data)}</p>
                        </div>
                    `;
          acompanhamentoConteudo.appendChild(item);
        });
      } else {
        acompanhamentoConteudo.innerHTML =
          "<p>Nenhum acompanhamento encontrado.</p>";
      }
    })
    .catch((error) => {
      console.error("Erro:", error);
    });
}
