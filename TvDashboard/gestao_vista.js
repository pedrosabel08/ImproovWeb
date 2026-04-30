/* gestao_vista.js – TV polling + card rendering for Gestão à Vista */

"use strict";

// ── Utilidades de data ────────────────────────────────────────────────────────

function daysInMonth(y, m) {
  return new Date(y, m, 0).getDate();
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
  const diasEl = document.getElementById("gvDiasRestantes");
  const now = new Date();
  periodEl.textContent = `${MONTHS_PT[now.getMonth()]} ${now.getFullYear()}`;
  diasEl.textContent = `${getDiasRestantes()} dias restantes`;

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

// ── Helpers de classificação ──────────────────────────────────────────────────

function getPctClass(pct) {
  if (pct === null) return "none";
  if (pct >= 100) return "done";
  if (pct >= 70) return "on";
  if (pct >= 35) return "atrasado";
  if (pct > 0) return "critico";
  return "none";
}

function getProgressLabel(pct) {
  if (pct === null) return "–";
  if (pct === 0) return "Sem produção";
  if (pct >= 100) return "Acima da meta";
  if (pct >= 70) return "No ritmo";
  return "Abaixo do ritmo";
}

function calcularRitmo(qtd_parcial, meta_individual, pct) {
  const diaAtual = getDiaAtual();
  const taxaAtual = diaAtual > 0 ? qtd_parcial / diaAtual : 0;
  const rateStr = taxaAtual > 0 ? `${taxaAtual.toFixed(1)}/dia` : "";

  if (pct === null || meta_individual === null || meta_individual === 0) {
    return { tipo: "none", icon: "–", label: "–", rate: "" };
  }
  if (pct === 0)
    return { tipo: "none", icon: "–", label: "Sem produção", rate: rateStr };
  if (pct >= 110)
    return { tipo: "done", icon: "↗", label: "Acelerado", rate: rateStr };
  if (pct >= 70)
    return { tipo: "on", icon: "→", label: "No ritmo", rate: rateStr };
  if (pct >= 35)
    return { tipo: "warn", icon: "↘", label: "Atrasado", rate: rateStr };
  return { tipo: "crit", icon: "↘↘", label: "Crítico", rate: rateStr };
}

function getAvatarHue(name) {
  let hash = 0;
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  return Math.abs(hash) % 360;
}

// ── Linha de funcionário (card row) ──────────────────────────────────────────

function buildEmployeeRow(f, meta_individual) {
  const pct = typeof f.pct_meta === "number" ? f.pct_meta : null;
  const pgClass = getPctClass(pct);
  const pgLabel = getProgressLabel(pct);
  const pgWidth = pct !== null ? Math.min(pct, 100) : 0;
  const pctStr = pct !== null ? `${pct}%` : "–";

  const falta =
    meta_individual !== null ? meta_individual - f.qtd_parcial : null;
  let faltaHtml;
  if (falta === null) {
    faltaHtml = `<div class="gv-falta-val none">–</div>`;
  } else if (falta <= 0) {
    faltaHtml = `<div class="gv-falta-val positive">+${Math.abs(falta)}</div>`;
  } else {
    const fc = falta > meta_individual * 0.5 ? "negative" : "warn";
    faltaHtml = `<div class="gv-falta-val ${fc}">${falta}</div>`;
  }

  const ritmo = calcularRitmo(f.qtd_parcial, meta_individual, pct);
  const hue = getAvatarHue(f.nome);
  const initial = f.nome.charAt(0).toUpperCase();
  const imagemAvatar = f.imagem_url
    ? `<div class="gv-avatar" style="background:hsl(${hue},55%,38%)"><img src="${f.imagem_url}" alt="${f.nome}" class="gv-avatar-img"></div>`
    : `<div class="gv-avatar" style="background:hsl(${hue},55%,38%)">${initial}</div>`;
  const metaSub = meta_individual !== null ? `de ${meta_individual}` : "";
  const recorde = Number.isFinite(f.recorde_mes) ? f.recorde_mes : 0;

  return `<div class="gv-row">
    <div class="gv-row-nome">
      ${imagemAvatar}
      <span class="gv-row-name-text">${f.nome}</span>
    </div>
    <div class="gv-row-progresso">
      <div class="gv-prog-pct ${pgClass}">${pctStr}</div>
      <div class="gv-prog-bar">
        <div class="gv-prog-fill ${pgClass}" style="width:${pgWidth}%"></div>
      </div>
      <div class="gv-prog-label">${pgLabel}</div>
    </div>
    <div class="gv-row-qtd">
    <div class="gv-qtd-val">${f.qtd_parcial}</div>
    <div class="gv-qtd-sub">${metaSub}</div>
    </div>
    <div class="gv-row-falta">${faltaHtml}</div>
    <div class="gv-row-recorde">
      <div class="gv-rec-val">${recorde}</div>
    </div>
    <div class="gv-row-ritmo">
      <span class="gv-ritmo-icon gv-ritmo-${ritmo.tipo}">${ritmo.icon}</span>
      <div class="gv-ritmo-info">
        <div class="gv-ritmo-label gv-ritmo-${ritmo.tipo}">${ritmo.label}</div>
        ${ritmo.rate ? `<div class="gv-ritmo-rate">${ritmo.rate}</div>` : ""}
      </div>
    </div>
  </div>`;
}

// ── Footer de seção ───────────────────────────────────────────────────────────

function renderSectionFooter(footId, totalQtd, meta_total) {
  const footEl = document.getElementById(footId);
  const leftEl = footEl.querySelector(".gv-foot-left");
  const rightEl = footEl.querySelector(".gv-foot-right");

  const pct = meta_total > 0 ? Math.round((totalQtd / meta_total) * 100) : null;
  const falta = meta_total !== null ? meta_total - totalQtd : null;

  let faltaVal = "–",
    faltaClass = "none";
  if (falta !== null) {
    if (falta <= 0) {
      faltaVal = `+${Math.abs(falta)}`;
      faltaClass = "green";
    } else {
      faltaVal = `${falta}`;
      faltaClass = falta > meta_total * 0.3 ? "red" : "yellow";
    }
  }
  const pctHtml =
    pct !== null ? `<span class="gv-foot-pct">${pct}% da meta</span>` : "";

  leftEl.innerHTML = `
    <span class="gv-foot-label">Total atual</span>
    <span class="gv-foot-val">${totalQtd}</span>
    ${pctHtml}`;

  rightEl.innerHTML = `
    <span class="gv-foot-label">Falta para meta</span>
    <span class="gv-foot-val ${faltaClass}">${faltaVal}</span>`;
}

// ── Renderização: Perspectivas ────────────────────────────────────────────────

function buildPerspectivas(data) {
  const { funcionarios, meta_total, meta_individual } = data;

  const metaEl = document.getElementById("metaPerspectivas");
  if (metaEl)
    metaEl.innerHTML = `Meta mensal: <strong>${meta_total ?? "–"}</strong>`;

  const outros = funcionarios.find((f) => f.colaborador_id === 0);
  const principais = funcionarios.filter((f) => f.colaborador_id !== 0);
  const sorted = [...principais].sort((a, b) => b.qtd_parcial - a.qtd_parcial);
  const all = outros ? [...sorted, outros] : sorted;

  let totalQtd = 0,
    rows = "";
  for (const f of all) {
    totalQtd += f.qtd_parcial;
    rows += buildEmployeeRow(f, meta_individual);
  }
  document.getElementById("bodyPerspectivas").innerHTML = rows;
  renderSectionFooter("footPerspectivas", totalQtd, meta_total);
}

// ── Renderização: seção genérica (Plantas / Alterações) ──────────────────────

function buildSecao(data, bodyId, footId, metaElId) {
  const { funcionarios, meta_total, meta_individual } = data;

  const metaEl = document.getElementById(metaElId);
  if (metaEl)
    metaEl.innerHTML = `Meta mensal: <strong>${meta_total ?? "–"}</strong>`;

  const sorted = [...funcionarios].sort(
    (a, b) => b.qtd_parcial - a.qtd_parcial,
  );

  let totalQtd = 0,
    rows = "";
  for (const f of sorted) {
    totalQtd += f.qtd_parcial;
    rows += buildEmployeeRow(f, meta_individual);
  }
  document.getElementById(bodyId).innerHTML = rows;
  renderSectionFooter(footId, totalQtd, meta_total);
}

// ── Barra de KPIs (summary bar) ───────────────────────────────────────────────

function buildSummaryBar(dados) {
  const { perspectivas, plantas_humanizadas } = dados;

  const allFuncs = [
    ...perspectivas.funcionarios.filter((f) => f.colaborador_id !== 0),
    ...plantas_humanizadas.funcionarios,
  ];

  const totalQtdPersp = perspectivas.funcionarios.reduce(
    (s, f) => s + f.qtd_parcial,
    0,
  );
  const totalMetaPersp = perspectivas.meta_total ?? 0;
  const totalPctPersp =
    totalMetaPersp > 0
      ? Math.round((totalQtdPersp / totalMetaPersp) * 100)
      : null;

  const dentroMeta = allFuncs.filter(
    (f) => f.pct_meta !== null && f.pct_meta >= 100,
  ).length;
  const emRisco = allFuncs.filter(
    (f) => f.pct_meta !== null && f.pct_meta >= 50 && f.pct_meta < 100,
  ).length;
  const atrasados = allFuncs.filter(
    (f) => f.pct_meta !== null && f.pct_meta < 50,
  ).length;
  const totalFunc = allFuncs.filter((f) => f.pct_meta !== null).length;

  let statusCls, statusTxt, statusSub;
  if (totalPctPersp === null) {
    statusCls = "no-data";
    statusTxt = "SEM DADOS";
    statusSub = "Aguardando informações";
  } else if (totalPctPersp >= 90) {
    statusCls = "on-track";
    statusTxt = "ON TRACK";
    statusSub = "Ritmo dentro do esperado";
  } else if (totalPctPersp >= 70) {
    statusCls = "on-track";
    statusTxt = "NO RITMO";
    statusSub = "Ritmo dentro do esperado";
  } else if (totalPctPersp >= 50) {
    statusCls = "at-risk";
    statusTxt = "EM RISCO";
    statusSub = "Ritmo abaixo do esperado";
  } else {
    statusCls = "behind";
    statusTxt = "ATRASADO";
    statusSub = "Ritmo crítico";
  }
  const iconCls =
    statusCls === "on-track"
      ? "green"
      : statusCls === "at-risk"
        ? "yellow"
        : statusCls === "behind"
          ? "red"
          : "";

  const diasRestantes = getDiasRestantes();
  const now = new Date();
  const ultimoDia = daysInMonth(now.getFullYear(), now.getMonth() + 1);
  const metaTotal =
    (perspectivas.meta_total ?? 0) + (plantas_humanizadas.meta_total ?? 0);

  document.getElementById("gvSummaryBar").innerHTML = `
    <div class="gv-kpi gv-kpi--status">
      <div class="gv-kpi-icon ${iconCls}"><i class="fa-solid fa-bullseye-arrow"></i></div>
      <div class="gv-kpi-status-text">
        <span class="gv-kpi-label">Status Geral do Mês</span>
        <span class="gv-kpi-status-val ${statusCls}">${statusTxt}</span>
        <span class="gv-kpi-status-sub">${statusSub}</span>
      </div>
    </div>
    <div class="gv-kpi">
      <span class="gv-kpi-label">Produção Total</span>
      <span class="gv-kpi-value">${totalQtdPersp}</span>
      <span class="gv-kpi-sub">${totalPctPersp !== null ? totalPctPersp + "% da meta mensal" : "–"}</span>
    </div>
    <div class="gv-kpi">
      <span class="gv-kpi-label">Dentro da Meta</span>
      <span class="gv-kpi-value green">${dentroMeta}</span>
      <span class="gv-kpi-sub">${totalFunc > 0 ? Math.round((dentroMeta / totalFunc) * 100) + "%" : "–"} dos colaboradores</span>
    </div>
    <div class="gv-kpi">
      <span class="gv-kpi-label">Em Risco</span>
      <span class="gv-kpi-value yellow">${emRisco}</span>
      <span class="gv-kpi-sub">${totalFunc > 0 ? Math.round((emRisco / totalFunc) * 100) + "%" : "–"} dos colaboradores</span>
    </div>
    <div class="gv-kpi">
      <span class="gv-kpi-label">Atrasados</span>
      <span class="gv-kpi-value red">${atrasados}</span>
      <span class="gv-kpi-sub">${totalFunc > 0 ? Math.round((atrasados / totalFunc) * 100) + "%" : "–"} dos colaboradores</span>
    </div>
    <div class="gv-kpi">
      <span class="gv-kpi-label">Meta do Mês</span>
      <span class="gv-kpi-value accent">${metaTotal > 0 ? metaTotal : "–"}</span>
      <span class="gv-kpi-sub">Total esperado</span>
    </div>
    <div class="gv-kpi">
      <span class="gv-kpi-label">Dias Restantes</span>
      <span class="gv-kpi-value">${diasRestantes}</span>
      <span class="gv-kpi-sub">até ${String(ultimoDia).padStart(2, "0")}/${String(now.getMonth() + 1).padStart(2, "0")}</span>
    </div>`;
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

    buildSummaryBar(dados);
    buildPerspectivas(dados.perspectivas);
    buildSecao(
      dados.plantas_humanizadas,
      "bodyPlantas",
      "footPlantas",
      "metaPlantas",
    );
    buildSecao(
      dados.alteracoes,
      "bodyAlteracoes",
      "footAlteracoes",
      "metaAlteracoes",
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
  setInterval(carregarDados, 120000); // atualiza a cada 2 minutos
});
