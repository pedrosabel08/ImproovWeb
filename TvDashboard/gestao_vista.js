/* gestao_vista.js – TV polling + table rendering for Gestão à Vista */

"use strict";

// ── Utilidades de data ────────────────────────────────────────────────────────

function daysInMonth(y, m) {
  return new Date(y, m, 0).getDate(); // m is 1-based, Date(y, m, 0) = last day of month m
}

function getDiaAtual() {
  return new Date().getDate();
}
function getDiasRestantes() {
  const now = new Date();
  return daysInMonth(now.getFullYear(), now.getMonth() + 1) - now.getDate();
}

// ── Relógio e período ─────────────────────────────────────────────────────────

const MONTHS_PT = [
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

function initClock() {
  const clockEl = document.getElementById("gvClock");
  const periodEl = document.getElementById("gvPeriod");
  const now = new Date();
  periodEl.textContent = `${MONTHS_PT[now.getMonth()]} ${now.getFullYear()}`;

  function tick() {
    const d = new Date();
    clockEl.textContent = d.toLocaleTimeString("pt-BR", {
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
    });
  }
  tick();
  setInterval(tick, 1000);
}

// ── Atualizado há X min ───────────────────────────────────────────────────────

let lastUpdateAt = null;

function startUpdatedLabel() {
  const el = document.getElementById("gvUpdated");
  setInterval(() => {
    if (!lastUpdateAt) return;
    const mins = Math.floor((Date.now() - lastUpdateAt) / 60000);
    el.textContent =
      mins === 0 ? "Atualizado agora" : `Atualizado há ${mins} min`;
  }, 30000);
}

// ── Construção de células de meta ─────────────────────────────────────────────

function metaCells(qtdParcial, metaInd, diasRestantes) {
  const r00 = metaInd !== null ? qtdParcial - metaInd : null;

  let tdR00, tdDias;
  if (r00 === null) {
    tdR00 = `<td>–</td>`;
    tdDias = `<td>–</td>`;
  } else if (r00 > 0) {
    tdR00 = `<td class="gv-cell-done">+${r00}</td>`;
    tdDias = `<td class="gv-cell-done">${diasRestantes}</td>`;
  } else {
    tdR00 = `<td>${Math.abs(r00)}</td>`;
    tdDias = `<td>${diasRestantes}</td>`;
  }

  return tdR00 + tdDias;
}

// ── Renderização: Perspectivas ────────────────────────────────────────────────

function buildPerspectivas(data) {
  const { funcionarios, meta_total, meta_individual } = data;
  const diasRestantes = getDiasRestantes();

  let totalRec = 0,
    totalQtd = 0;
  let rows = "";

  const outros = funcionarios.find((f) => f.colaborador_id === 0);
  const principais = funcionarios.filter((f) => f.colaborador_id !== 0);
  const sorted = [...principais].sort((a, b) => b.qtd_parcial - a.qtd_parcial);
  const all = outros ? [...sorted, outros] : sorted;

  for (const f of all) {
    totalRec += f.recorde_mes;
    totalQtd += f.qtd_parcial;
    const bateuRec = f.recorde_mes > 0 && f.qtd_parcial >= f.recorde_mes;
    const pct =
      typeof f.pct_meta === "number"
        ? f.pct_meta
        : f.pct_meta === null
          ? null
          : null;
    let pctBadge = "";
    if (pct === null) {
      pctBadge = '<span class="gv-pct-badge gv-pct-none">—</span>';
    } else if (meta_individual !== null && f.qtd_parcial >= meta_individual) {
      pctBadge = `<span class="gv-pct-badge gv-pct-done">${pct}%</span>`;
    } else if (pct >= 80) {
      pctBadge = `<span class="gv-pct-badge gv-pct-high">${pct}%</span>`;
    } else if (pct >= 50) {
      pctBadge = `<span class="gv-pct-badge gv-pct-mid">${pct}%</span>`;
    } else {
      pctBadge = `<span class="gv-pct-badge gv-pct-low">${pct}%</span>`;
    }

    rows += `<tr class="${bateuRec ? "gv-row-record" : ""}">
      <td>${f.nome}</td>
      <td>${f.recorde_mes}</td>
      <td><span class="gv-qty">${f.qtd_parcial}</span> ${pctBadge}</td>
      ${metaCells(f.qtd_parcial, meta_individual, diasRestantes)}
    </tr>`;
  }

  document.getElementById("bodyPerspectivas").innerHTML = rows;
  const totalPct = (meta_total !== null && meta_total > 0) ? Math.round((totalQtd / meta_total) * 100) : null;
  let totalPctBadge = '';
  if (totalPct === null) {
    totalPctBadge = '<span class="gv-pct-badge gv-pct-none">—</span>';
  } else if (meta_total !== null && totalQtd >= meta_total) {
    totalPctBadge = `<span class="gv-pct-badge gv-pct-done">${totalPct}%</span>`;
  } else if (totalPct >= 80) {
    totalPctBadge = `<span class="gv-pct-badge gv-pct-high">${totalPct}%</span>`;
  } else if (totalPct >= 50) {
    totalPctBadge = `<span class="gv-pct-badge gv-pct-mid">${totalPct}%</span>`;
  } else {
    totalPctBadge = `<span class="gv-pct-badge gv-pct-low">${totalPct}%</span>`;
  }

  document.getElementById("footPerspectivas").innerHTML = `<tr>
    <td class="gv-tfoot-label">Total:</td>
    <td>${totalRec}</td>
    <td>${totalQtd} ${totalPctBadge}</td>
    <td class="gv-tfoot-meta">Meta (mês): ${meta_total ?? "–"}</td>
  </tr>`;
}

// ── Renderização: tabela genérica (Plantas / Alterações) ─────────────────────

function buildSecao(data, bodyId, footId, labelTotalProd, labelMeta) {
  const { funcionarios, meta_total, meta_individual } = data;
  const diasRestantes = getDiasRestantes();

  let totalQtd = 0;
  let rows = "";

  const sorted = [...funcionarios].sort(
    (a, b) => b.qtd_parcial - a.qtd_parcial,
  );
  for (const f of sorted) {
    totalQtd += f.qtd_parcial;
    const bateuRec = f.recorde_mes > 0 && f.qtd_parcial >= f.recorde_mes;
    const pct =
      typeof f.pct_meta === "number"
        ? f.pct_meta
        : f.pct_meta === null
          ? null
          : null;
    let pctBadge = "";
    if (pct === null) {
      pctBadge = '<span class="gv-pct-badge gv-pct-none">—</span>';
    } else if (meta_individual !== null && f.qtd_parcial >= meta_individual) {
      pctBadge = `<span class="gv-pct-badge gv-pct-done">${pct}%</span>`;
    } else if (pct >= 80) {
      pctBadge = `<span class="gv-pct-badge gv-pct-high">${pct}%</span>`;
    } else if (pct >= 50) {
      pctBadge = `<span class="gv-pct-badge gv-pct-mid">${pct}%</span>`;
    } else {
      pctBadge = `<span class="gv-pct-badge gv-pct-low">${pct}%</span>`;
    }

    const qtdDisplay = f.qtd_parcial || "";
    rows += `<tr class="${bateuRec ? "gv-row-record" : ""}">
      <td>${f.nome}</td>
      <td>${f.recorde_mes || ""}</td>
      <td><span class="gv-qty">${qtdDisplay}</span> ${pctBadge}</td>
      ${metaCells(f.qtd_parcial, meta_individual, diasRestantes)}
    </tr>`;
  }

  document.getElementById(bodyId).innerHTML = rows;
  const sectionPct = (meta_total !== null && meta_total > 0) ? Math.round((totalQtd / meta_total) * 100) : null;
  let sectionPctBadge = '';
  if (sectionPct === null) {
    sectionPctBadge = '<span class="gv-pct-badge gv-pct-none">—</span>';
  } else if (meta_total !== null && totalQtd >= meta_total) {
    sectionPctBadge = `<span class="gv-pct-badge gv-pct-done">${sectionPct}%</span>`;
  } else if (sectionPct >= 80) {
    sectionPctBadge = `<span class="gv-pct-badge gv-pct-high">${sectionPct}%</span>`;
  } else if (sectionPct >= 50) {
    sectionPctBadge = `<span class="gv-pct-badge gv-pct-mid">${sectionPct}%</span>`;
  } else {
    sectionPctBadge = `<span class="gv-pct-badge gv-pct-low">${sectionPct}%</span>`;
  }

  document.getElementById(footId).innerHTML = `<tr>
    <td colspan="2" class="gv-tfoot-label">${labelTotalProd}</td>
    <td>${totalQtd} ${sectionPctBadge}</td>
    <td class="gv-tfoot-label">${labelMeta}</td>
    <td class="gv-tfoot-meta">${meta_total ?? "–"}</td>
  </tr>`;
}

// ── Polling ───────────────────────────────────────────────────────────────────

async function carregarDados() {
  const now = new Date();
  const mes = now.getMonth() + 1;
  const ano = now.getFullYear();

  try {
    const resp = await fetch(`buscar_gestao_vista.php?mes=${mes}&ano=${ano}`);
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    const dados = await resp.json();

    buildPerspectivas(dados.perspectivas);
    buildSecao(
      dados.plantas_humanizadas,
      "bodyPlantas",
      "footPlantas",
      "Total de produção atual Improov:",
      "Meta mensal Improov:",
    );
    buildSecao(
      dados.alteracoes,
      "bodyAlteracoes",
      "footAlteracoes",
      "Total de produção atual Improov:",
      "Meta mensal Improov:",
    );

    lastUpdateAt = Date.now();
    document.getElementById("gvUpdated").textContent = "Atualizado agora";
    document.getElementById("gvOffline").hidden = true;
  } catch (err) {
    console.error("Erro ao carregar dados:", err);
    document.getElementById("gvOffline").hidden = false;
  }
}

// ── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener("DOMContentLoaded", () => {
  initClock();
  startUpdatedLabel();
  carregarDados();
  // setInterval(carregarDados, 120000); // atualiza a cada 2 minutos
});
