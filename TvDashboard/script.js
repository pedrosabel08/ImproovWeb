/**
 * TvDashboard/script.js
 * Chart.js + tabela + relógio + auto-refresh a cada 2 minutos.
 */

// ── Constantes ────────────────────────────────────────────────
const REFRESH_INTERVAL_MS = 2 * 60 * 1000; // 2 minutos
const NOMES_MESES = [
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

// Cores por nome de função (fallback para cinza)
const FUNC_COLORS = {
  Caderno: "#38bdf8",
  "Filtro de assets": "#a78bfa",
  Modelagem: "#fb923c",
  Composição: "#34d399",
  "Pré-Finalização": "#fbbf24",
  "Finalização Parcial": "#f87171",
  "Finalização Completa": "#4ade80",
  "Finalização de Planta Humanizada": "#2dd4bf",
  "Pós-produção": "#c084fc",
  Alteração: "#94a3b8",
};

function funcColor(nome) {
  return FUNC_COLORS[nome] || "#6b7280";
}

// ── Estado ────────────────────────────────────────────────────
let tvChart = null;
let lastUpdateAt = null;

// ── Relógio ───────────────────────────────────────────────────
function tickClock() {
  const el = document.getElementById("tvRelogio");
  if (!el) return;
  const now = new Date();
  const hh = String(now.getHours()).padStart(2, "0");
  const mm = String(now.getMinutes()).padStart(2, "0");
  const ss = String(now.getSeconds()).padStart(2, "0");
  el.textContent = `${hh}:${mm}:${ss}`;
}

function updateLastUpdateLabel() {
  const el = document.getElementById("tvLastUpdate");
  if (!el || !lastUpdateAt) return;
  const diffMs = Date.now() - lastUpdateAt;
  const diffMin = Math.floor(diffMs / 60000);
  el.textContent =
    diffMin === 0 ? "Atualizado agora" : `Atualizado há ${diffMin} min`;
}

// ── Período no cabeçalho ──────────────────────────────────────
function setPeriodo(mes, ano) {
  const el = document.getElementById("tvPeriodo");
  if (el) el.textContent = `${NOMES_MESES[mes - 1]} de ${ano}`;
}

// ── Gráfico ───────────────────────────────────────────────────
function buildChart(dados) {
  const labels = dados.map((d) => d.nome_funcao);
  const valores = dados.map((d) => d.quantidade);
  const metas = dados.map((d) => d.meta || 0);
  const cores = dados.map((d) => {
    if (d.atingiu_meta) return funcColor(d.nome_funcao);
    if (d.pct_meta === null) return funcColor(d.nome_funcao);
    return funcColor(d.nome_funcao);
  });
  const bordas = dados.map((d) => (d.bate_recorde ? "#fbbf24" : "transparent"));

  const ctx = document.getElementById("tvChart").getContext("2d");

  if (tvChart) {
    // Atualiza silenciosamente
    tvChart.data.labels = labels;
    tvChart.data.datasets[0].data = valores;
    tvChart.data.datasets[0].backgroundColor = cores.map((c) => c + "cc"); // 80% opacidade
    tvChart.data.datasets[0].borderColor = bordas;
    if (tvChart.data.datasets[1]) {
      tvChart.data.datasets[1].data = metas;
    }
    tvChart.update("active");
    return;
  }

  tvChart = new Chart(ctx, {
    type: "bar",
    data: {
      labels,
      datasets: [
        {
          label: "Produção",
          data: valores,
          backgroundColor: cores.map((c) => c + "cc"),
          borderColor: bordas,
          borderWidth: 2,
          borderRadius: 6,
          borderSkipped: false,
        },
        {
          label: "Meta",
          data: metas,
          type: "bar",
          backgroundColor: "rgba(255,255,255,0.06)",
          borderColor: "rgba(255,255,255,0.25)",
          borderWidth: 1,
          borderRadius: 6,
          borderSkipped: false,
          barPercentage: 0.95,
          categoryPercentage: 0.85,
        },
      ],
    },
    options: {
      indexAxis: "y",
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 600, easing: "easeInOutQuart" },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: "rgba(10,12,16,0.95)",
          titleColor: "#e8eaf0",
          bodyColor: "#9ca3af",
          borderColor: "rgba(255,255,255,0.1)",
          borderWidth: 1,
          padding: 12,
          cornerRadius: 8,
          callbacks: {
            title: (items) => items[0].label,
            label: (item) => {
              const d = dados[item.dataIndex];
              if (item.datasetIndex === 0) {
                const metaTxt = d.meta ? ` — Meta: ${d.meta}` : "";
                const pctTxt = d.pct_meta !== null ? ` (${d.pct_meta}%)` : "";
                return ` Produção: ${d.quantidade}${metaTxt}${pctTxt}`;
              }
              return d.meta ? ` Meta: ${d.meta}` : " Sem meta definida";
            },
          },
        },
      },
      scales: {
        x: {
          beginAtZero: true,
          grid: {
            color: "rgba(255,255,255,0.05)",
            drawBorder: false,
          },
          ticks: {
            color: "#6b7280",
            font: { size: 11 },
          },
        },
        y: {
          grid: { display: false },
          ticks: {
            color: "#c9cdd4",
            font: { size: 13, weight: "600" },
            padding: 8,
          },
        },
      },
    },
  });
}

// ── Tabela ────────────────────────────────────────────────────
function buildTable(dados) {
  const tbody = document.getElementById("tvTableBody");
  if (!tbody) return;
  tbody.innerHTML = "";

  if (!dados || dados.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="5" class="tv-table-loading">Nenhum dado encontrado.</td></tr>';
    return;
  }

  dados.forEach((d) => {
    const tr = document.createElement("tr");
    if (d.atingiu_meta) tr.classList.add("tv-row-done");
    if (d.bate_recorde) tr.classList.add("tv-row-record");

    // Nome + cor
    const tdNome = document.createElement("td");
    const nomeDiv = document.createElement("div");
    nomeDiv.className = "tv-func-name";
    const dot = document.createElement("span");
    dot.className = "tv-func-dot";
    dot.style.background = funcColor(d.nome_funcao);
    const nomeSpan = document.createElement("span");
    nomeSpan.textContent = d.nome_funcao;
    if (d.bate_recorde) {
      const trophy = document.createElement("i");
      trophy.className = "fa-solid fa-trophy tv-record-icon";
      nomeSpan.appendChild(trophy);
    }
    nomeDiv.appendChild(dot);
    nomeDiv.appendChild(nomeSpan);
    tdNome.appendChild(nomeDiv);

    // Quantidade
    const tdQtd = document.createElement("td");
    const qtdSpan = document.createElement("span");
    qtdSpan.className = "tv-qty";
    qtdSpan.textContent = d.quantidade;
    tdQtd.appendChild(qtdSpan);

    // Meta
    const tdMeta = document.createElement("td");
    tdMeta.textContent = d.meta !== null ? d.meta : "—";

    // % da meta
    const tdPct = document.createElement("td");
    const badge = document.createElement("span");
    badge.className = "tv-pct-badge";
    if (d.pct_meta === null) {
      badge.classList.add("tv-pct-none");
      badge.textContent = "—";
    } else if (d.atingiu_meta) {
      badge.classList.add("tv-pct-done");
      badge.textContent = d.pct_meta + "%";
    } else if (d.pct_meta >= 80) {
      badge.classList.add("tv-pct-high");
      badge.textContent = d.pct_meta + "%";
    } else if (d.pct_meta >= 50) {
      badge.classList.add("tv-pct-mid");
      badge.textContent = d.pct_meta + "%";
    } else {
      badge.classList.add("tv-pct-low");
      badge.textContent = d.pct_meta + "%";
    }
    tdPct.appendChild(badge);

    // Variação vs anterior
    const tdAnterior = document.createElement("td");
    const ant = d.mes_anterior || 0;
    const qtd = d.quantidade || 0;
    const delta = qtd - ant;
    tdAnterior.innerHTML =
      ant +
      (delta > 0
        ? ` <span class="tv-delta-up">▲${delta}</span>`
        : delta < 0
          ? ` <span class="tv-delta-down">▼${Math.abs(delta)}</span>`
          : ` <span class="tv-delta-eq">—</span>`);

    tr.appendChild(tdNome);
    tr.appendChild(tdQtd);
    tr.appendChild(tdMeta);
    tr.appendChild(tdPct);
    tr.appendChild(tdAnterior);
    tbody.appendChild(tr);
  });
}

// ── Busca e renderização ──────────────────────────────────────
let mesAtual, anoAtual;

function carregarDados() {
  const offlineEl = document.getElementById("tvOffline");

  fetch(`buscar_tv.php?mes=${mesAtual}&ano=${anoAtual}`)
    .then((res) => {
      if (!res.ok) throw new Error("HTTP " + res.status);
      return res.json();
    })
    .then((dados) => {
      if (offlineEl) offlineEl.style.display = "none";

      const dadosFiltrados = dados.filter((d) => d.nome_funcao !== "Alteração");

      buildChart(dadosFiltrados);
      buildTable(dadosFiltrados);

      lastUpdateAt = Date.now();
      updateLastUpdateLabel();
    })
    .catch((err) => {
      console.error("[TV] Erro ao carregar dados:", err);
      if (offlineEl) offlineEl.style.display = "flex";
    });
}

// ── Inicialização ─────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
  const now = new Date();
  mesAtual = now.getMonth() + 1;
  anoAtual = now.getFullYear();

  setPeriodo(mesAtual, anoAtual);

  // Relógio
  tickClock();
  setInterval(tickClock, 1000);

  // Atualiza label "há X min" a cada 30s
  setInterval(updateLastUpdateLabel, 30000);

  // Carga inicial
  carregarDados();

  // Auto-refresh
  setInterval(carregarDados, REFRESH_INTERVAL_MS);
});
