/* ============================================================
   Painel de Produção — script.js
   Uso exclusivo: PaginaPrincipal/Overview/index.php
   ============================================================ */

// Só executa o painel de colaborador se não for gestor
if (window.PAINEL && !window.PAINEL.isGestor) {
  document.addEventListener("DOMContentLoaded", initColabDashboard);
}

// ============================================================
// INIT
// ============================================================
function initColabDashboard() {
  const seletor = document.getElementById("mes-seletor");
  if (!seletor) return;

  const { mesAtual, anoAtual } = window.PAINEL;
  loadCollabDashboard(mesAtual, anoAtual);

  seletor.addEventListener("change", () => {
    const [ano, mes] = seletor.value.split("-").map(Number);
    loadCollabDashboard(mes, ano);
  });
}

// ============================================================
// CARREGA TODO O DASHBOARD
// ============================================================
async function loadCollabDashboard(mes, ano) {
  setKpiLoading();

  try {
    const res = await fetch(
      `getDashboardColaborador.php?mes=${mes}&ano=${ano}`,
    );
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();

    if (data.error) throw new Error(data.error);

    renderKpis(data.kpis);
    renderEtapas(data.por_etapa || []);
    renderTarefas(data.tarefas || []);
    populateMesSeletor(
      data.meses_disponiveis || [],
      `${ano}-${String(mes).padStart(2, "0")}`,
    );
    loadFeedbacks();
  } catch (err) {
    console.error("[PainelProdução] Erro ao carregar dashboard:", err);
    showToastError("Erro ao carregar dados do painel.");
  }
}

// ============================================================
// KPIs
// ============================================================
function setKpiLoading() {
  ["kpi-novas", "kpi-valor", "kpi-ajustes"].forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.innerHTML = '<span class="kpi-loading"></span>';
  });
}

function renderKpis(kpis) {
  if (!kpis) return;
  animateCount("kpi-novas", kpis.total_novas ?? 0);
  setKpiValor("kpi-valor", kpis.valor_a_receber ?? 0);
  setKpiAjustes("kpi-ajustes", kpis.media_ajustes ?? 0);
}

function animateCount(id, target) {
  const el = document.getElementById(id);
  if (!el) return;
  const duration = 600;
  const start = performance.now();
  const to = Number(target);
  function step(now) {
    const t = Math.min((now - start) / duration, 1);
    const ease = 1 - Math.pow(1 - t, 3);
    el.textContent = Math.round(to * ease);
    if (t < 1) requestAnimationFrame(step);
    else el.textContent = to;
  }
  requestAnimationFrame(step);
}

function setKpiValor(id, valor) {
  const el = document.getElementById(id);
  if (!el) return;
  const val = parseFloat(valor);
  el.textContent =
    val > 0
      ? "R$ " +
        val.toLocaleString("pt-BR", {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        })
      : "R$ 0,00";
}

function setKpiAjustes(id, media) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = parseFloat(media).toFixed(1).replace(".", ",");
}

// ============================================================
// ETAPAS
// ============================================================
const ETAPA_ICONS = {
  Caderno: "ri-book-2-line",
  Modelagem: "ri-shape-line",
  Composição: "ri-layout-grid-line",
  Finalização: "ri-image-edit-line",
  "Pós-Produção": "ri-magic-line",
  Alteração: "ri-edit-box-line",
  Planta: "ri-map-2-line",
  Filtro: "ri-filter-3-line",
};

function renderEtapas(etapas) {
  const grid = document.getElementById("etapas-grid");
  if (!grid) return;

  if (!etapas.length) {
    grid.innerHTML =
      '<div style="color:var(--muted);font-size:13px;grid-column:1/-1">Sem tarefas neste mês.</div>';
    return;
  }

  grid.innerHTML = etapas
    .map((e) => {
      const iconKey = Object.keys(ETAPA_ICONS).find((k) =>
        e.nome_funcao.includes(k),
      );
      const icon = iconKey ? ETAPA_ICONS[iconKey] : "ri-tools-line";
      const tempo =
        e.tempo_medio_horas != null
          ? formatTempo(parseFloat(e.tempo_medio_horas))
          : "–";

      return `<div class="etapa-card">
            <div class="etapa-nome"><i class="${icon}"></i> ${htmlEscape(e.nome_funcao)}</div>
            <div class="etapa-total">${e.total}</div>
            <div class="etapa-tempo"><i class="ri-time-line"></i> ${tempo} médio</div>
        </div>`;
    })
    .join("");
}

function formatTempo(horas) {
  if (horas === null || isNaN(horas)) return "–";
  if (horas < 1) return `${Math.round(horas * 60)} min`;
  if (horas < 24) return `${horas.toFixed(1).replace(".", ",")} h`;
  return `${(horas / 24).toFixed(1).replace(".", ",")} dias`;
}

// ============================================================
// TAREFAS
// ============================================================
function renderTarefas(tarefas) {
  const tbody = document.getElementById("tasks-body");
  const badge = document.getElementById("tasks-count");
  if (!tbody) return;

  if (badge) badge.textContent = tarefas.length;

  if (!tarefas.length) {
    tbody.innerHTML =
      '<tr><td colspan="7" class="empty-row">Nenhuma tarefa encontrada neste mês.</td></tr>';
    return;
  }

  tbody.innerHTML = tarefas
    .map((t) => {
      const statusClass = getStatusClass(t.status);
      const valorFmt = parseFloat(t.valor || 0).toLocaleString("pt-BR", {
        style: "currency",
        currency: "BRL",
      });
      // Finalização Completa (funcao_id=4) com pago parcial mas sem pago completa = ainda não pago
      const pagoParcialCount = parseInt(t.pago_parcial_count || 0, 10);
      const pagoCompletaCount = parseInt(t.pago_completa_count || 0, 10);
      const isFinalizacaoCompleta =
        parseInt(t.funcao_id, 10) === 4 &&
        pagoParcialCount > 0 &&
        pagoCompletaCount === 0;
      const isPago = t.pagamento == 1 && !isFinalizacaoCompleta;
      const valorClass = isPago ? "valor-pago" : "valor-pendente";

      // Tooltip com data(s) de pagamento
      let pagoTooltip = "";
      if (t.pagamentos_info) {
        pagoTooltip = t.pagamentos_info
          .split(";")
          .map((entry) => {
            const [label, date] = entry.split("|");
            return date ? `${label} pago em ${date}` : label;
          })
          .join("\n");
      }

      // Ícone + badge de pagamento
      let pagoIcon, pagoBadge;
      if (isPago) {
        pagoIcon = `<i class="ri-checkbox-circle-fill icone-pago" title="${htmlEscape(pagoTooltip || "Pago")}"></i>`;
        pagoBadge = '<span class="pago-badge pago-badge-completo">Pago</span>';
      } else if (isFinalizacaoCompleta) {
        pagoIcon = `<i class="ri-time-line icone-pago-parcial" title="Aguardando pagamento final"></i>`;
        pagoBadge =
          '<span class="pago-badge pago-badge-parcial">Pago Parcial</span>';
      } else {
        pagoIcon =
          '<i class="ri-time-line icone-pendente" title="Pendente"></i>';
        pagoBadge = "";
      }

      const qtd = parseInt(t.qtd_ajustes || 0);
      const ajClass =
        qtd === 0 ? "ajuste-zero" : qtd === 1 ? "ajuste-um" : "ajuste-mais";
      const prazoFmt = t.prazo
        ? new Date(t.prazo).toLocaleDateString("pt-BR")
        : "–";

      return `<tr>
            <td>
                <div style="font-weight:600;font-size:13px">${htmlEscape(t.imagem_nome)}</div>
                <div style="font-size:11px;color:var(--muted)">${prazoFmt}</div>
            </td>
            <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${htmlEscape(t.nome_obra)}">
                ${htmlEscape(t.nomenclatura || t.nome_obra)}
            </td>
            <td>${htmlEscape(t.nome_funcao)}</td>
            <td><span class="status-badge ${statusClass}">${htmlEscape(t.status)}</span></td>
            <td class="col-right"><span class="${valorClass}">${valorFmt}</span></td>
            <td class="col-center pago-cell">${pagoIcon}${pagoBadge}</td>
            <td class="col-center"><span class="${ajClass}">${qtd}</span></td>
        </tr>`;
    })
    .join("");
}

function getStatusClass(status) {
  if (!status) return "status-default";
  const s = status.toLowerCase().trim();
  if (s === "finalizado") return "status-finalizado";
  if (s === "em andamento") return "status-andamento";
  if (s === "em aprovação") return "status-aprovacao";
  if (s === "ajuste") return "status-ajuste";
  if (s === "aprovado com ajustes") return "status-aprovado-ajuste";
  if (s === "aprovado") return "status-aprovado";
  return "status-default";
}

// ============================================================
// SELETOR DE MESES
// ============================================================
function populateMesSeletor(meses, valorAtual) {
  const sel = document.getElementById("mes-seletor");
  if (!sel || !meses.length) return;
  if (sel.dataset.populado === "true") {
    sel.value = valorAtual;
    return;
  }

  sel.innerHTML = meses
    .map((m) => {
      const selected = m.valor === valorAtual ? " selected" : "";
      return `<option value="${m.valor}"${selected}>${htmlEscape(m.label)}</option>`;
    })
    .join("");
  sel.dataset.populado = "true";
}

// ============================================================
// FEEDBACKS (status Ajuste com comentário)
// ============================================================
async function loadFeedbacks() {
  const list = document.getElementById("feedback-list");
  const badge = document.getElementById("feedbacks-count");
  if (!list) return;

  try {
    const res = await fetch("getFeedbacks.php");
    const data = await res.json();
    const fbs = data.feedbacks || [];

    if (badge) badge.textContent = fbs.length;

    if (!fbs.length) {
      list.innerHTML =
        '<div class="empty-row" style="padding:16px;color:var(--muted)">Nenhum ajuste pendente.</div>';
      return;
    }

    list.innerHTML = fbs
      .map(
        (f) => `
        <div class="feedback-card">
            <div class="fb-info">
                <div class="fb-obra">${htmlEscape(f.taskTitle || "")}</div>
                <div class="fb-body">${htmlEscape(f.excerpt || "Sem comentário")}</div>
                <span class="fb-etapa">${htmlEscape(f.type || "Ajuste")}</span>
                <div class="fb-date">${f.date ? new Date(f.date).toLocaleDateString("pt-BR") : ""}</div>
            </div>
            <button class="btn-ir" onclick="history.back()">
                <i class="ri-arrow-right-line"></i> Ver
            </button>
        </div>`,
      )
      .join("");
  } catch (err) {
    console.error("[PainelProdução] Erro ao carregar feedbacks:", err);
    if (list)
      list.innerHTML =
        '<div class="empty-row" style="padding:16px;color:var(--muted)">Erro ao carregar.</div>';
  }
}

// ============================================================
// UTILS
// ============================================================
function htmlEscape(str) {
  if (str == null) return "";
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function showToastError(msg) {
  if (typeof Toastify === "function") {
    Toastify({
      text: msg,
      duration: 4000,
      gravity: "bottom",
      position: "right",
      backgroundColor: "var(--danger)",
    }).showToast();
  } else {
    console.error(msg);
  }
}

// ============================================================
// LEGADO — mantido para compatibilidade com gestores se
// window.PAINEL.isGestor === true
// ============================================================
async function carregarMetricas() {
  try {
    const resposta = await fetch("getMetrics.php");
    const data = await resposta.json();

    // --- CARD 1: Função mais finalizada ---
    const card1 = document.getElementById("pct-completed");
    const funcoes = data.funcoes_finalizadas_mes_atual || [];
    let topFuncao = null;
    if (funcoes.length > 0) {
      topFuncao = funcoes[0];
      card1.textContent = `${topFuncao.nome_funcao} (${topFuncao.total_finalizadas})`;
    } else {
      card1.textContent = "--";
    }

    // Container de detalhes (expandir lista)
    let detalhes1 = document.createElement("div");
    detalhes1.style.display = "none";
    detalhes1.style.padding = "4px 8px";
    detalhes1.style.fontSize = "14px";
    card1.parentElement.appendChild(detalhes1);

    card1.style.cursor = "pointer";
    card1.addEventListener("click", () => {
      detalhes1.style.display =
        detalhes1.style.display === "none" ? "block" : "none";
    });
    detalhes1.innerHTML = funcoes
      .map((f) => `${f.nome_funcao}: ${f.total_finalizadas}`)
      .join("<br>");

    // --- CARD 2: Média de tempo da função top ---
    const card2 = document.getElementById("avg-time");
    const tempos = data.tempo_medio_conclusao || [];

    if (tempos.length) {
      // função top = primeira do array
      const top = tempos[0];
      const diasTop = (top.tempo_medio_horas / 24).toFixed(1);
      card2.textContent = `${diasTop} dias`;

      // detalhes de todas as funções
      let detalhes2 = document.createElement("div");
      detalhes2.style.display = "none";
      detalhes2.style.padding = "4px 8px";
      detalhes2.style.fontSize = "14px";

      tempos.forEach((t) => {
        const d = document.createElement("div");
        d.textContent = `${t.nome_funcao}: ${(t.tempo_medio_horas / 24).toFixed(1)} dias`;
        detalhes2.appendChild(d);
      });

      card2.parentElement.appendChild(detalhes2);

      card2.style.cursor = "pointer";
      card2.addEventListener("click", () => {
        detalhes2.style.display =
          detalhes2.style.display === "none" ? "block" : "none";
      });
    } else {
      card2.textContent = "--";
    }

    // --- CARD 3: Taxa de aprovação ---
    const card3 = document.getElementById("approval-rate");
    const taxa = data.taxa_aprovacao || {};
    card3.textContent =
      taxa.pct_aprovadas_de_primeira !== undefined
        ? `${taxa.pct_aprovadas_de_primeira}%`
        : "--";

    let detalhes3 = document.createElement("div");
    detalhes3.style.display = "none";
    detalhes3.style.padding = "4px 8px";
    detalhes3.style.fontSize = "14px";
    card3.parentElement.appendChild(detalhes3);

    card3.style.cursor = "pointer";
    card3.addEventListener("click", () => {
      detalhes3.style.display =
        detalhes3.style.display === "none" ? "block" : "none";
    });

    // detalhamento
    detalhes3.innerHTML = `
            Total com histórico: ${taxa.total_com_historico || 0}<br>
            Aprovadas de primeira: ${taxa.total_aprovadas_de_primeira || 0} (${taxa.pct_aprovadas_de_primeira || 0}%)<br>
            Aprovadas com ajustes: ${taxa.total_aprovadas_com_ajustes_de_primeira || 0} (${taxa.pct_aprovadas_com_ajustes_de_primeira || 0}%)<br>
            Tiveram ajuste: ${taxa.total_que_tiveram_ajuste || 0} (${taxa.pct_que_tiveram_ajuste || 0}%)
        `;

    // --- CARD 4: inventada, aqui total de funções finalizadas ---
    const card4 = document.getElementById("due-today");
    const totalFinalizadas = funcoes.reduce(
      (acc, f) => acc + parseInt(f.total_finalizadas || 0),
      0,
    );
    card4.textContent = totalFinalizadas;
  } catch (erro) {
    console.error("Erro ao carregar métricas:", erro);
  }
}

// --- Função para buscar atualizações do servidor ---
async function fetchUpdates(filter = "all") {
  try {
    const res = await fetch("getAtualizacoes.php");
    const data = await res.json();

    // Atualiza a lista global (se quiser manter fora)
    updates = data.atualizacoes || [];

    // Renderiza
    renderUpdates(filter);
  } catch (err) {
    console.error("Erro ao buscar atualizações:", err);
  }
}

// --- Render updates feed ---
function renderUpdates(filter = "all") {
  const el = document.getElementById("updates-list");
  el.innerHTML = "";

  const list = updates.filter((u) => {
    if (filter === "all") return true;
    if (filter === "my") return u.body.includes("@Pedro") || u.taskId;
    if (filter === "action") return u.actionNeeded;
    return true;
  });

  list.forEach((u) => {
    const node = document.createElement("div");
    node.className = "update card";
    node.setAttribute("data-id", u.id);

    // Ajusta valores de u conforme os campos do PHP
    node.innerHTML = `
            <div class="meta">
                <div class="left">
                    <div class="badge">${u.tipo_evento || "Info"}</div>
                    <div style="display:flex;flex-direction:column">
                        <div class="title">${u.descricao || ""}</div>
                        <div class="tiny">${u.data_evento || ""} • ${u.lido ? "Lido" : "Novo"}</div>
                    </div>
                </div>
                <div class="pill">${u.actionNeeded ? "Ação" : "Info"}</div>
            </div>
            <div class="body">${u.descricao || ""}</div>
            <div class="actions">
                <button class="btn" onclick="openComments('${u.id}')">Comentar</button>
                <button class="btn" onclick="goToTaskForUpdate(${u.taskId || "null"})">Ir para</button>
                <button class="btn" onclick="markRead('${u.id}', this)">${u.lido ? "Marcar não lido" : "Marcar lido"}</button>
            </div>
        `;
    el.appendChild(node);
  });
}

// --- Inicializa ---
let updates = [];

// Chamadas legadas apenas para gestores (elementos só existem na view de gestor)
if (window.PAINEL && window.PAINEL.isGestor) {
  document.addEventListener("DOMContentLoaded", () => {
    carregarMetricas();
    fetchUpdates();
  });
}
// loadFeedbacks é chamada dentro de loadCollabDashboard para colaboradores
