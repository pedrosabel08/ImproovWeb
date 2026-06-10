(function () {
  const root = document.querySelector(".op-main");
  const currentMonth = Number(root?.dataset.currentMonth || new Date().getMonth() + 1);
  const currentYear = Number(root?.dataset.currentYear || new Date().getFullYear());
  const monthSelect = document.getElementById("filterMonth");
  const yearInput = document.getElementById("filterYear");
  const functionSelect = document.getElementById("filterFunction");
  const typeSelect = document.getElementById("filterType");
  const form = document.getElementById("opFilters");
  const functionsBody = document.getElementById("functionsBody");
  let trendChart = null;
  let filtersLoaded = false;
  const expandedFunctions = new Set();

  const monthNames = [
    "Janeiro",
    "Fevereiro",
    "Março",
    "Abril",
    "Maio",
    "Junho",
    "Julho",
    "Agosto",
    "Setembro",
    "Outubro",
    "Novembro",
    "Dezembro",
  ];

  function initMonths() {
    monthNames.forEach((name, index) => {
      const option = document.createElement("option");
      option.value = String(index + 1);
      option.textContent = name;
      if (index + 1 === currentMonth) option.selected = true;
      monthSelect.appendChild(option);
    });
    yearInput.value = String(currentYear);
  }

  function formatNumber(value, decimals = 0) {
    return Number(value || 0).toLocaleString("pt-BR", {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    });
  }

  function statusColor(status) {
    if (status === "critico") return "op-number-red";
    if (status === "atencao") return "op-number-orange";
    if (status === "saudavel") return "op-number-green";
    if (status === "excesso") return "op-number-blue";
    return "";
  }

  function iconForStatus(status) {
    if (status === "critico") return "fa-triangle-exclamation";
    if (status === "atencao") return "fa-circle-exclamation";
    if (status === "excesso") return "fa-layer-group";
    if (status === "saudavel") return "fa-circle-check";
    return "fa-clock";
  }

  function fetchData() {
    const params = new URLSearchParams(new FormData(form));
    functionsBody.innerHTML = '<tr><td colspan="9" class="op-empty">Carregando...</td></tr>';

    fetch(`dados.php?${params.toString()}`)
      .then((response) => {
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
      })
      .then((data) => {
        if (data.error) throw new Error(data.error);
        populateFilters(data.options || {});
        renderKpis(data.kpis || {});
        renderTable(data.funcoes || []);
        renderAlerts(data.alertas || []);
        renderRankings(data.rankings || {});
        renderRecommendations(data.alertas || []);
        renderChart(data.tendencia || {});
        renderLastUpdate(data.updated_at);
      })
      .catch((error) => {
        console.error("Dashboard Operacional:", error);
        functionsBody.innerHTML = '<tr><td colspan="9" class="op-empty">Erro ao carregar dados.</td></tr>';
      });
  }

  function populateFilters(options) {
    if (filtersLoaded) return;
    filtersLoaded = true;

    (options.funcoes || []).forEach((item) => {
      const option = document.createElement("option");
      option.value = String(item.id);
      option.textContent = item.nome;
      functionSelect.appendChild(option);
    });

    (options.tipos_imagem || []).forEach((item) => {
      const option = document.createElement("option");
      option.value = item;
      option.textContent = item;
      typeSelect.appendChild(option);
    });
  }

  function renderKpis(kpis) {
    document.getElementById("kpiCritical").textContent = formatNumber(kpis.criticas);
    document.getElementById("kpiAttention").textContent = formatNumber(kpis.atencao);
    document.getElementById("kpiHealthy").textContent = formatNumber(kpis.saudaveis);
    document.getElementById("kpiExcess").textContent = formatNumber(kpis.excesso);
    document.getElementById("kpiQueue").textContent = formatNumber(kpis.total_fila);
    document.getElementById("kpiSupply").textContent =
      kpis.abastecimento_medio === null || kpis.abastecimento_medio === undefined
        ? "-"
        : `${formatNumber(kpis.abastecimento_medio, 0)}%`;
  }

  function renderTable(rows) {
    if (!rows.length) {
      functionsBody.innerHTML = '<tr><td colspan="9" class="op-empty">Nenhuma função encontrada para os filtros.</td></tr>';
      return;
    }

    functionsBody.innerHTML = rows
      .map((row) => {
        const queueTotal = Number(row.fila_total || 0);
        const monthlyGoal = Number(row.meta_mensal || 0);
        const width = monthlyGoal > 0 ? Math.min(100, (queueTotal / monthlyGoal) * 100) : 0;
        const functionId = String(row.id);
        const isExpanded = expandedFunctions.has(functionId);
        const detailId = `op-detail-${functionId}`;
        const counts = row.contagens_status || {};

        return `
          <tr class="op-function-row ${isExpanded ? "is-expanded" : ""}" data-function-id="${escapeHtml(functionId)}" aria-expanded="${isExpanded ? "true" : "false"}" aria-controls="${detailId}">
            <td>
              <span class="op-function">
                <button class="op-row-toggle" type="button" data-action="toggle-details" aria-label="Expandir ${escapeHtml(row.nome)}">
                  <i class="fa-solid ${isExpanded ? "fa-chevron-down" : "fa-chevron-right"}"></i>
                </button>
                <span class="op-function-icon"><i class="fa-solid fa-cube"></i></span>
                ${escapeHtml(row.nome)}
              </span>
            </td>
            <td>${formatNumber(counts.planejada)}</td>
            <td>${formatNumber(counts.p00)}</td>
            <td>${formatNumber(counts.nao_iniciado)}</td>
            <td>
              <span class="op-progress">
                <span>${formatNumber(row.fila_total)}</span>
                <span class="op-bar"><span class="op-bar-fill" style="width:${width}%"></span></span>
              </span>
            </td>
            <td title="Origem: ${row.meta_origem === "metas" ? "tabela metas" : "média diária x 20 dias úteis"}">${formatNumber(row.meta_mensal)}</td>
            <td class="op-production-total">${formatNumber(row.producao_total)}</td>
            <td class="${statusColor(row.status)}" title="Cobertura em dias: ${escapeHtml(row.cobertura_label || "-")}">${escapeHtml(row.abastecimento_label || "-")}</td>
            <td><span class="op-badge ${escapeHtml(row.status)}">${escapeHtml(row.status_label)}</span></td>
          </tr>
          <tr id="${detailId}" class="op-detail-row ${isExpanded ? "is-open" : ""}">
            <td colspan="9">${renderDetailsDrawer(row)}</td>
          </tr>`;
      })
      .join("");
  }

  function renderDetailsDrawer(row) {
    const obras = row?.detalhes?.obras || [];
    const counts = row?.contagens_status || {};
    const productionSummary = `
      <div class="op-production-summary" aria-label="Detalhamento das imagens em produção">
        ${renderProductionStat("Em andamento", counts.em_andamento, "em_andamento")}
        ${renderProductionStat("Em aprovação", counts.em_aprovacao, "em_aprovacao")}
        ${renderProductionStat("Em ajuste", counts.ajuste, "ajuste")}
        ${renderProductionStat("Aprovado c/ ajustes", counts.aprovado_com_ajustes, "aprovado_com_ajustes")}
      </div>`;

    if (!obras.length) {
      return `<div class="op-drawer">${productionSummary}<div class="op-drawer-empty">Nenhuma imagem encontrada para esta função.</div></div>`;
    }

    let counter = 1;
    const groups = obras
      .map((obra) => {
        const items = (obra.itens || [])
          .map((item) => {
            const index = counter++;
            return `
              <li class="op-drawer-item">
                <span class="op-drawer-index">${index}.</span>
                <span class="op-drawer-image">${escapeHtml(item.imagem_nome || "-")}</span>
                <span class="op-drawer-person">${escapeHtml(item.responsavel || "Sem colaborador")}</span>
                <span class="op-drawer-time">${escapeHtml(item.tempo_label || "-")}</span>
                <span class="op-drawer-source status-${escapeHtml(item.status_key || "")}">${escapeHtml(item.status_label || "-")}</span>
              </li>`;
          })
          .join("");

        return `
          <section class="op-drawer-group">
            <h3>${escapeHtml(obra.obra_nome || "Obra")}</h3>
            <ol>${items}</ol>
          </section>`;
      })
      .join("");

    return `<div class="op-drawer">${productionSummary}${groups}</div>`;
  }

  function renderProductionStat(label, value, statusKey) {
    return `
      <div class="op-production-stat status-${escapeHtml(statusKey)}">
        <span>${escapeHtml(label)}</span>
        <strong>${formatNumber(value)}</strong>
      </div>`;
  }

  function renderAlerts(alerts) {
    document.getElementById("alertCount").textContent = formatNumber(alerts.length);
    const list = document.getElementById("alertsList");
    list.innerHTML = alerts
      .map(
        (alert) => `
          <div class="op-alert">
            <span class="op-alert-icon ${escapeHtml(alert.tone)}">
              <i class="fa-solid ${iconForStatus(alert.tone)}"></i>
            </span>
            <div>
              <strong>${escapeHtml(alert.title)}</strong>
              <p>${escapeHtml(alert.body)}</p>
            </div>
          </div>`,
      )
      .join("");
  }

  function renderRankings(rankings) {
    renderRankingList("coverageRanking", rankings.menor_abastecimento || [], (row) => row.abastecimento_label || "-");
    renderRankingList("queueRanking", rankings.maior_fila || [], (row) => `${formatNumber(row.fila_total)} tarefas`);
  }

  function renderRankingList(id, rows, valueFactory) {
    const list = document.getElementById(id);
    if (!rows.length) {
      list.innerHTML = '<div class="op-alert"><p>Nenhum dado disponível.</p></div>';
      return;
    }

    list.innerHTML = rows
      .map(
        (row, index) => `
          <div class="op-rank-item">
            <span class="op-rank-index">${index + 1}</span>
            <div>
              <strong>${escapeHtml(row.nome)}</strong>
              <p>${escapeHtml(valueFactory(row))}</p>
            </div>
          </div>`,
      )
      .join("");
  }

  function renderRecommendations(alerts) {
    const list = document.getElementById("recommendationsList");
    list.innerHTML = alerts
      .slice(0, 4)
      .map(
        (alert) => `
          <div class="op-recommendation">
            <div>
              <strong>${escapeHtml(alert.title)}</strong>
              <p>${escapeHtml(alert.body)}</p>
            </div>
            <span class="op-badge ${escapeHtml(alert.tone)}">${labelForTone(alert.tone)}</span>
          </div>`,
      )
      .join("");
  }

  function renderChart(data) {
    const ctx = document.getElementById("trendChart");
    const porDia = data.por_dia || {};
    const labels = Object.keys(porDia).sort();
    const values = labels.map((day) => Number(porDia[day] || 0));

    if (trendChart) {
      trendChart.destroy();
    }

    trendChart = new Chart(ctx, {
      type: "line",
      data: {
        labels: labels.map((day) => day.slice(8, 10) + "/" + day.slice(5, 7)),
        datasets: [
          {
            label: "Consumo diário",
            data: values,
            borderColor: "#2f87ff",
            backgroundColor: "rgba(47, 135, 255, 0.14)",
            pointBackgroundColor: "#27d6c2",
            pointBorderWidth: 0,
            pointRadius: 3,
            tension: 0.35,
            fill: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: "#0b1727",
            borderColor: "rgba(121, 143, 171, 0.26)",
            borderWidth: 1,
          },
        },
        scales: {
          x: {
            grid: { color: "rgba(121, 143, 171, 0.09)" },
            ticks: { color: "#8ea1ba", maxTicksLimit: 8 },
          },
          y: {
            beginAtZero: true,
            grid: { color: "rgba(121, 143, 171, 0.09)" },
            ticks: { color: "#8ea1ba", precision: 0 },
          },
        },
      },
    });
  }

  function renderLastUpdate(value) {
    const label = document.getElementById("lastUpdate");
    if (!value) {
      label.textContent = "Dados carregados";
      return;
    }
    const date = new Date(value);
    label.textContent = `Dados atualizados às ${date.toLocaleTimeString("pt-BR", {
      hour: "2-digit",
      minute: "2-digit",
    })}`;
  }

  function labelForTone(tone) {
    if (tone === "critico") return "Crítico";
    if (tone === "atencao") return "Atenção";
    if (tone === "excesso") return "Excesso";
    if (tone === "saudavel") return "OK";
    return "Meta";
  }

  function escapeHtml(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    expandedFunctions.clear();
    fetchData();
  });

  functionsBody.addEventListener("click", (event) => {
    const row = event.target.closest(".op-function-row");
    if (!row) return;
    toggleFunctionDetails(row);
  });

  initMonths();
  fetchData();

  function toggleFunctionDetails(row) {
    const functionId = String(row.dataset.functionId || "");
    const detailId = row.getAttribute("aria-controls");
    const detailRow = detailId ? document.getElementById(detailId) : null;
    if (!functionId || !detailRow) return;

    const shouldOpen = !expandedFunctions.has(functionId);
    if (shouldOpen) {
      expandedFunctions.add(functionId);
    } else {
      expandedFunctions.delete(functionId);
    }

    row.classList.toggle("is-expanded", shouldOpen);
    row.setAttribute("aria-expanded", shouldOpen ? "true" : "false");
    detailRow.classList.toggle("is-open", shouldOpen);

    const icon = row.querySelector('[data-action="toggle-details"] i');
    if (icon) {
      icon.classList.toggle("fa-chevron-right", !shouldOpen);
      icon.classList.toggle("fa-chevron-down", shouldOpen);
    }
  }
})();
