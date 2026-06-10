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

function calcularRitmo(qtd_parcial, meta_individual) {
  const diaAtual = getDiaAtual();
  const diasNoMes = daysInMonth(
    new Date().getFullYear(),
    new Date().getMonth() + 1,
  );

  const taxaAtual = diaAtual > 0 ? qtd_parcial / diaAtual : 0;
  const rateStr = taxaAtual > 0 ? `${taxaAtual.toFixed(1)}/dia` : "";

  if (!meta_individual) {
    return {
      tipo: "none",
      icon: '<i class="fa-solid fa-minus"></i>',
      label: "–",
      rate: "",
    };
  }

  if (qtd_parcial === 0) {
    return {
      tipo: "none",
      icon: '<i class="fa-solid fa-minus"></i>',
      label: "Sem produção",
      rate: rateStr,
    };
  }

  // Evita distorção no começo do mês
  if (diaAtual < 3) {
    return {
      tipo: "none",
      icon: '<i class="fa-solid fa-minus"></i>',
      label: "Aguardando base",
      rate: rateStr,
    };
  }

  const ritmoNecessario = meta_individual / diasNoMes;
  const performance = ritmoNecessario > 0 ? taxaAtual / ritmoNecessario : 0;

  // ======================
  // CLASSIFICAÇÃO
  // ======================
  console.log({
    qtd_parcial,
    meta_individual,
    diaAtual,
    diasNoMes,
    taxaAtual,
    ritmoNecessario,
    performance,
  });

  if (performance >= 1.2) {
    return {
      tipo: "done",
      icon: '<i class="fa-solid fa-arrow-trend-up"></i>',
      label: "Acelerado",
      rate: rateStr,
    };
  }

  if (performance >= 0.9) {
    return {
      tipo: "on",
      icon: '<i class="fa-solid fa-arrow-right"></i>',
      label: "No ritmo",
      rate: rateStr,
    };
  }

  if (performance >= 0.7) {
    return {
      tipo: "warn",
      icon: '<i class="fa-solid fa-arrow-trend-down"></i>',
      label: "Em risco",
      rate: rateStr,
    };
  }

  return {
    tipo: "crit",
    icon: '<i class="fa-solid fa-angles-down"></i>',
    label: "Crítico",
    rate: rateStr,
  };
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

  const ritmo = calcularRitmo(f.qtd_parcial, meta_individual);
  const hue = getAvatarHue(f.nome);
  const initial = f.nome.charAt(0).toUpperCase();
  const imagemAvatar = f.imagem_url
    ? `<div class="gv-avatar" style="background:hsl(${hue},55%,38%)"><img src="https://improov.com.br/flow/ImproovWeb/${f.imagem_url}" alt="${f.nome}" class="gv-avatar-img"></div>`
    : `<div class="gv-avatar" style="background:hsl(${hue},55%,38%)">${initial}</div>`;
  const metaSub = meta_individual !== null ? `de ${meta_individual}` : "";
  const recorde = Number.isFinite(f.recorde_mes) ? f.recorde_mes : 0;

  return `<div class="gv-row" data-colab-id="${f.colaborador_id}">
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
    rows += buildEmployeeRow(f, f.colaborador_id === 0 ? null : (f.meta_individual ?? meta_individual));
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

  // Em TV (≥1800px) só Perspectivas é exibido — KPIs refletem apenas essa seção
  const isTv = document.documentElement.classList.contains("gv-tv-mode");

  const allFuncs = isTv
    ? perspectivas.funcionarios.filter((f) => f.colaborador_id !== 0)
    : [
        ...perspectivas.funcionarios.filter((f) => f.colaborador_id !== 0),
        ...plantas_humanizadas.funcionarios,
      ];

  const totalQtdPersp = perspectivas.funcionarios.reduce(
    (s, f) => s + f.qtd_parcial,
    0,
  );

  const totalMetaPersp = perspectivas.meta_total ?? 0;

  // =========================
  // CONTROLE DE TEMPO
  // =========================

  const hoje = new Date();
  const diaAtual = hoje.getDate();

  const diasNoMes = new Date(
    hoje.getFullYear(),
    hoje.getMonth() + 1,
    0,
  ).getDate();

  // % do tempo já consumido no mês
  const pctTempo = diasNoMes > 0 ? (diaAtual / diasNoMes) * 100 : 0;

  // % real entregue
  const pctProducao =
    totalMetaPersp > 0 ? (totalQtdPersp / totalMetaPersp) * 100 : null;

  // diferença entre produção e tempo
  // positivo = adiantado
  // negativo = atrasado
  const gapRitmo = pctProducao !== null ? pctProducao - pctTempo : null;

  // projeção final mantendo o ritmo atual
  const mediaDia = diaAtual > 0 ? totalQtdPersp / diaAtual : 0;

  const projecaoFinal = mediaDia * diasNoMes;

  const projecaoPct =
    totalMetaPersp > 0 ? (projecaoFinal / totalMetaPersp) * 100 : null;

  // valor exibido no card principal
  const totalPctPersp = pctProducao !== null ? Math.round(pctProducao) : null;

  // =========================
  // STATUS DOS FUNCIONÁRIOS
  // =========================

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

  // =========================
  // STATUS GERAL
  // =========================

  let statusCls, statusTxt, statusSub;

  if (pctProducao === null) {
    statusCls = "no-data";
    statusTxt = "SEM DADOS";
    statusSub = "Aguardando informações";
  } else if (projecaoPct >= 100 && gapRitmo >= 0) {
    statusCls = "on-track";
    statusTxt = "ON TRACK";
    statusSub = "Entrega dentro do ritmo esperado";
  } else if (projecaoPct >= 90) {
    statusCls = "on-track";
    statusTxt = "NO RITMO";
    statusSub = "Pequeno desvio no ritmo";
  } else if (projecaoPct >= 70) {
    statusCls = "at-risk";
    statusTxt = "EM RISCO";
    statusSub = "Meta pode não ser atingida";
  } else {
    statusCls = "behind";
    statusTxt = "ATRASADO";
    statusSub = "Ritmo insuficiente para conclusão";
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
  const metaTotal = isTv
    ? (perspectivas.meta_total ?? 0)
    : (perspectivas.meta_total ?? 0) + (plantas_humanizadas.meta_total ?? 0);

  const statusLabel = isTv ? "Status Perspectivas" : "Status Geral do Mês";
  const finalizationQueueTotal = Number(
    dados.fila_operacional_finalizacao || 0,
  );

  document.getElementById("gvSummaryBar").innerHTML = `
    <div class="gv-kpi gv-kpi--status">
      <div class="gv-kpi-icon ${iconCls}"><i class="fa-solid fa-bullseye-arrow"></i></div>
      <div class="gv-kpi-status-text">
        <span class="gv-kpi-label">${statusLabel}</span>
        <span class="gv-kpi-status-val ${statusCls}">${statusTxt}</span>
        <span class="gv-kpi-status-sub">${statusSub}</span>
      </div>
    </div>
    <div class="gv-kpi gv-kpi--queue">
      <span class="gv-kpi-label">Total de imagens a produzir</span>
      <span class="gv-kpi-value accent">${finalizationQueueTotal}</span>
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
      <span class="gv-kpi-label">Dias Restantes</span>
      <span class="gv-kpi-value">${diasRestantes}</span>
      <span class="gv-kpi-sub">até ${String(ultimoDia).padStart(2, "0")}/${String(now.getMonth() + 1).padStart(2, "0")}</span>
    </div>`;
}

// ── Sistema de Áudio (Web Audio API) ────────────────────────────────────────

const GVAudio = (() => {
  let ctx = null;
  let unlocked = false;

  function getCtx() {
    if (!ctx) ctx = new (window.AudioContext || window.webkitAudioContext)();
    return ctx;
  }

  // Desbloqueia o AudioContext na primeira interação do usuário
  function unlock() {
    if (unlocked) return;
    const ac = getCtx();
    if (ac.state === "suspended") {
      ac.resume().then(() => {
        unlocked = true;
      });
    } else {
      unlocked = true;
    }
  }

  ["click", "keydown", "touchstart", "scroll", "pointerdown"].forEach((evt) => {
    document.addEventListener(evt, unlock, { passive: true });
  });

  function tone(freq, startTime, duration, gain = 0.22, type = "sine") {
    const ac = getCtx();
    const osc = ac.createOscillator();
    const gainNode = ac.createGain();
    osc.connect(gainNode);
    gainNode.connect(ac.destination);
    osc.type = type;
    osc.frequency.setValueAtTime(freq, startTime);
    gainNode.gain.setValueAtTime(0, startTime);
    gainNode.gain.linearRampToValueAtTime(gain, startTime + 0.01);
    gainNode.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
    osc.start(startTime);
    osc.stop(startTime + duration + 0.05);
  }

  // Arpejo celebratório — meta atingida (C5-E5-G5-C6-E6)
  function playMetaReached() {
    if (!unlocked) return;
    const ac = getCtx();
    const t = ac.currentTime;
    [523.25, 659.25, 783.99, 1046.5, 1318.51].forEach((f, i) => {
      tone(f, t + i * 0.11, 0.38, 0.2);
    });
  }

  // Ping duplo curto — novo item produzido
  function playNewItem() {
    if (!unlocked) return;
    const ac = getCtx();
    const t = ac.currentTime;
    tone(880, t, 0.1, 0.16);
    tone(1108.73, t + 0.08, 0.15, 0.11);
  }

  // Dois tons ascendentes — subiu no ranking
  function playRankingUp() {
    if (!unlocked) return;
    const ac = getCtx();
    const t = ac.currentTime;
    tone(659.25, t, 0.15, 0.17, "triangle");
    tone(880, t + 0.13, 0.2, 0.17, "triangle");
  }

  return { playMetaReached, playNewItem, playRankingUp };
})();

// ── Rastreamento de estado para eventos sonoros ───────────────────────────────

let prevState = null;

function buildStateSnapshot(dados) {
  function secSnap(sec) {
    const sorted = [...sec.funcionarios].sort(
      (a, b) => b.qtd_parcial - a.qtd_parcial,
    );
    const map = new Map();
    sorted.forEach((f, idx) => {
      map.set(f.colaborador_id, {
        qtd: f.qtd_parcial,
        rank: idx,
        metaOk: typeof f.pct_meta === "number" && f.pct_meta >= 100,
      });
    });
    return map;
  }
  return {
    persp: secSnap(dados.perspectivas),
    plantas: secSnap(dados.plantas_humanizadas),
    alter: secSnap(dados.alteracoes),
  };
}

function detectSoundEvents(newDados) {
  if (!prevState) return []; // primeira carga — sem som

  const newSnap = buildStateSnapshot(newDados);
  const sections = [
    {
      newMap: newSnap.persp,
      prevMap: prevState.persp,
      bodyId: "bodyPerspectivas",
    },
    {
      newMap: newSnap.plantas,
      prevMap: prevState.plantas,
      bodyId: "bodyPlantas",
    },
    {
      newMap: newSnap.alter,
      prevMap: prevState.alter,
      bodyId: "bodyAlteracoes",
    },
  ];

  const events = []; // { type: 'meta'|'rank'|'item', bodyId, colabId }

  for (const { newMap, prevMap, bodyId } of sections) {
    for (const [colabId, cur] of newMap) {
      const prv = prevMap.get(colabId);
      if (!prv) continue;
      if (cur.metaOk && !prv.metaOk)
        events.push({ type: "meta", bodyId, colabId });
      else if (cur.rank < prv.rank)
        events.push({ type: "rank", bodyId, colabId });
      else if (cur.qtd > prv.qtd)
        events.push({ type: "item", bodyId, colabId });
    }
  }

  // Som: prioridade meta > ranking > item (um som por ciclo)
  const hasMeta = events.some((e) => e.type === "meta");
  const hasRank = events.some((e) => e.type === "rank");
  const hasItem = events.some((e) => e.type === "item");

  if (hasMeta) setTimeout(() => GVAudio.playMetaReached(), 80);
  else if (hasRank) setTimeout(() => GVAudio.playRankingUp(), 80);
  else if (hasItem) setTimeout(() => GVAudio.playNewItem(), 80);

  return events;
}

function applyHighlights(events) {
  const CLASS_MAP = {
    meta: "gv-row--evt-meta",
    rank: "gv-row--evt-rank",
    item: "gv-row--evt-item",
  };
  for (const { type, bodyId, colabId } of events) {
    const container = document.getElementById(bodyId);
    if (!container) continue;
    const row = container.querySelector(`[data-colab-id="${colabId}"]`);
    if (!row) continue;
    // Remove classes anteriores para reiniciar animação se necessário
    row.classList.remove(
      "gv-row--evt-meta",
      "gv-row--evt-rank",
      "gv-row--evt-item",
    );
    void row.offsetWidth; // força reflow para reiniciar animação CSS
    row.classList.add(CLASS_MAP[type]);
    row.addEventListener(
      "animationend",
      () =>
        row.classList.remove(
          "gv-row--evt-meta",
          "gv-row--evt-rank",
          "gv-row--evt-item",
        ),
      { once: true },
    );
  }
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

    const soundEvents = detectSoundEvents(dados);
    prevState = buildStateSnapshot(dados);

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

    if (soundEvents.length) applyHighlights(soundEvents);

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
  setInterval(carregarDados, 30000); // atualiza a cada 30 segundos
});
