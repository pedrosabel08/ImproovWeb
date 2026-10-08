/* Managerial dashboard. All monetary values and classifications come from PHP. */
document.addEventListener("DOMContentLoaded", () => {
  const container = document.querySelector(".container");
  const header = document.querySelector(".payment-header");
  const filters = document.querySelector(".competencia-bar");
  if (!container || !header || !filters) return;
  const mes = document.getElementById("mes");
  const ano = document.getElementById("ano");
  const colaborador = document.getElementById("colaborador");
  const colabFilter = colaborador.closest(".filter-group");
  const individualCount = filters.querySelector(".competencia-count");
  const money = (cents) =>
    (cents / 100).toLocaleString("pt-BR", {
      style: "currency",
      currency: "BRL",
    });
  const escape = (value) =>
    String(value ?? "").replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[c],
    );
  const pct = (part, total) =>
    total > 0 ? Math.max(0, Math.min(100, (part / total) * 100)) : 0;
  const percentage = (value) =>
    `${Number(value).toLocaleString("pt-BR", { maximumFractionDigits: 1 })}%`;
  const empty = (message) => `<p class="overview-empty">${escape(message)}</p>`;
  const icon = (name) => `<i class="fa-solid ${name}" aria-hidden="true"></i>`;
  const number = (value, kind = "count", suffix = "") => {
    const label =
      kind === "money"
        ? money(value)
        : kind === "percent"
          ? percentage(value)
          : Number(value).toLocaleString("pt-BR");
    return `<span class="payment-number" data-payment-number="${Number(value)}" data-number-kind="${kind}" data-number-suffix="${escape(suffix)}" role="img" aria-label="${escape(label + suffix)}">${escape(label + suffix)}</span>`;
  };
  let payload = null;
  let controller;

  const individual = document.createElement("section");
  individual.id = "payment-individual";
  individual.className = "payment-individual";
  individual.setAttribute("aria-label", "Pagamento por colaborador");
  container.append(individual);
  [
    document.querySelector(".financial-summary"),
    document.querySelector(".table-scroll-area"),
  ].forEach((el) => individual.append(el));
  const detailNotice = document.createElement("div");
  detailNotice.className = "overview-notice";
  detailNotice.hidden = true;
  individual.insertBefore(detailNotice, individual.lastElementChild);
  window.addEventListener("pagamento:detalhe", (event) => {
    const r = event.detail;
    detailNotice.hidden = !r?.divergencias_financeiras;
    const financialSummary = individual.querySelector(".financial-summary");
    if (r) window.pagamentoMotion?.animate(financialSummary);
    else window.pagamentoMotion?.stop(financialSummary);
    detailNotice.textContent = r?.divergencias_financeiras
      ? `${r.divergencias_financeiras} item(ns) com inconsistência no livro financeiro. Confira os lançamentos antes de pagar. Os valores exibidos preservam os registros existentes.`
      : "";
  });

  const actions = document.createElement("div");
  actions.className = "payment-header-actions";
  actions.innerHTML =
    '<div class="payment-view-toggle" role="group" aria-label="Visão de pagamentos"><button type="button" data-view="geral" aria-controls="payment-overview">Visão geral</button><button type="button" data-view="colaborador" aria-controls="payment-individual">Por colaborador</button></div>';
  const closingTab = document.getElementById("fechamento-v2-link");
  if (closingTab) {
    closingTab.textContent = "Fechamento";
    actions.querySelector(".payment-view-toggle").append(closingTab);
  }
  const statusButton = document.getElementById("btn-ver-status-geral");
  header.append(actions);
  actions.append(statusButton);
  const subtitle = document.createElement("p");
  subtitle.className = "payment-subtitle";
  subtitle.textContent = "Custos de produção, pagamentos e adendos";
  header.querySelector(".payment-heading > div").append(subtitle);
  const overviewCount = document.createElement("span");
  overviewCount.className = "competencia-count";
  overviewCount.innerHTML = `${icon("fa-layer-group")} <span id="overview-item-count">—</span> itens na competência`;
  filters.append(overviewCount);

  const overview = document.createElement("section");
  overview.id = "payment-overview";
  overview.className = "payment-overview";
  overview.setAttribute("aria-label", "Visão geral de pagamentos");
  overview.innerHTML = `
    <div id="overview-feedback" role="status" aria-live="polite"></div>
    <div class="overview-kpis" id="overview-kpis"></div>
    <div class="overview-grid">
      <article class="overview-card" data-overview-panel="payment"><header>${icon("fa-chart-simple")}<div><h2>Pagamento do mês</h2><p>Distribuição financeira dos itens da competência</p></div></header><div id="overview-month"></div></article>
      <article class="overview-card" data-overview-panel="status"><header>${icon("fa-chart-pie")}<div><h2>Status dos pagamentos</h2><p>Quantidade de itens por situação financeira</p></div></header><div id="overview-status"></div></article>
      <article class="overview-card" data-overview-panel="review"><header>${icon("fa-file-contract")}<div><h2>Status dos adendos</h2><p>Situação documental nesta competência</p></div></header><div id="overview-amendments"></div></article>
      <article class="overview-card overview-functions" data-overview-panel="functions"><header><div class="overview-function-heading">${icon("fa-chart-column")}<div><h2>Custos por função</h2><p>Participação no custo do mês e comparação com o mês anterior</p></div></div><div id="overview-function-highlights" class="overview-function-highlights"></div></header><div id="overview-functions"></div></article>
      <article class="overview-card" data-overview-panel="top"><header>${icon("fa-users")}<div><h2>Top colaboradores do mês</h2><p>Selecione uma pessoa para consultar os detalhes</p></div></header><div id="overview-top"></div></article>
      <article class="overview-card" data-overview-panel="works"><header>${icon("fa-building")}<div><h2>Obras com maior impacto no mês</h2><p>Ranking de obras por custo total e produtividade associada</p></div></header><div id="overview-works"></div></article>
    </div>
    <section class="overview-card overview-operational" aria-labelledby="operational-title">
      <header>${icon("fa-user-group")}<div><h2 id="operational-title">Resumo operacional</h2><p>Consolidado por colaborador nesta competência</p></div><span id="overview-results" class="overview-results"></span></header>
      <div class="overview-local-filters">
        <label class="overview-search">${icon("fa-magnifying-glass")}<span class="sr-only">Buscar colaborador</span><input type="search" id="overview-search" placeholder="Buscar colaborador…"></label>
        <label><span class="sr-only">Filtrar por função</span><select id="overview-role"><option value="">Todas as funções</option></select></label>
        <label><span class="sr-only">Filtrar por obra</span><select id="overview-work"><option value="">Todas as obras</option></select></label>
        <label class="toggle-filter"><input type="checkbox" id="overview-pending"><span></span> Somente pendentes</label>
        <button type="button" class="btn btn-secondary" id="overview-clear">${icon("fa-filter-circle-xmark")} Limpar filtros</button>
      </div>
      <div class="overview-table-wrap"><table class="data-table overview-table"><thead><tr><th>Colaborador</th><th class="col-right">Itens</th><th class="col-right">Total</th><th class="col-right">Pago</th><th class="col-right">Pendente</th><th class="col-right">Adendos</th><th>Situação financeira</th></tr></thead><tbody id="overview-rows"></tbody></table></div>
      <p class="overview-table-note">Os filtros selecionam colaboradores. Cada linha mantém os valores integrais da competência; os indicadores gerais não mudam.</p>
    </section>
    <p class="overview-footnote">Pago inclui a liquidação dos itens selecionados, mesmo em outro mês. Adendos documentam valores e não são somados ao custo.</p>`;
  container.append(overview);
  const search = document.getElementById("overview-search");
  const role = document.getElementById("overview-role");
  const work = document.getElementById("overview-work");
  const pending = document.getElementById("overview-pending");
  const overviewGrid = overview.querySelector(".overview-grid");
  const overviewKpis = document.getElementById("overview-kpis");
  const setHtml = (id, html) => {
    document.getElementById(id).innerHTML = html;
  };

  function persist() {
    const url = new URL(location.href);
    url.searchParams.set("view", document.body.dataset.paymentView);
    url.searchParams.set("mes", mes.value);
    url.searchParams.set("ano", ano.value);
    if (colaborador.value)
      url.searchParams.set("colaborador_id", colaborador.value);
    else url.searchParams.delete("colaborador_id");
    history.replaceState(null, "", url);
  }

  window.addEventListener("pagamento:quitacao", () => {
    if (document.body.dataset.paymentView === "geral") load();
  });

  function setView(view) {
    document.body.dataset.paymentView = view;
    overview.hidden = view !== "geral";
    individual.hidden = view !== "colaborador";
    colabFilter.hidden = view !== "colaborador";
    individualCount.hidden = view !== "colaborador";
    overviewCount.hidden = view !== "geral";
    actions.querySelectorAll("[data-view]").forEach((button) => {
      button.classList.toggle("is-active", button.dataset.view === view);
      button.setAttribute("aria-pressed", String(button.dataset.view === view));
    });
    persist();
    if (view === "geral") {
      overview.scrollTop = 0;
      load();
    } else {
      controller?.abort();
      window.pagamentoMotion?.stop(overview);
      window.carregarDadosColab?.();
    }
  }

  function drillDown(id) {
    if (
      !Array.from(colaborador.options).some(
        (option) => option.value === String(id),
      )
    ) {
      const person = payload?.colaboradores.find(
        (c) => String(c.colaborador_id) === String(id),
      );
      if (person) colaborador.add(new Option(person.nome, String(id)));
    }
    colaborador.value = String(id);
    setView("colaborador");
    colaborador.focus();
  }

  function kpi(
    label,
    value,
    detail,
    tone,
    symbol,
    kind = "money",
    suffix = "",
  ) {
    return `<article class="overview-kpi ${tone}"><span class="overview-kpi-icon">${icon(symbol)}</span><div><h2>${escape(label)}</h2><strong>${number(value, kind, suffix)}</strong><p>${escape(detail)}</p></div></article>`;
  }

  function metricBar(label, count, total, tone) {
    const percent = pct(count, total);
    return `<div class="overview-status-row ${tone}"><span class="overview-status-label"><b class="overview-dot" aria-hidden="true"></b>${escape(label)}</span><strong>${number(count)}</strong><span class="overview-track" aria-hidden="true"><span style="width:${percent}%"></span></span><small>${number(percent, "percent")}</small></div>`;
  }

  function render(data) {
    const r = data.resumo;
    const docs = data.adendos;
    const c = data.fechamento;
    const official = data.fonte_financeira === "FECHAMENTO";
    window.pagamentoAtualizarColaboradoresCompetencia?.(
      official ? data.colaboradores : null,
      data.competencia,
    );
    const closed = c?.estado === "CONCLUIDO";
    const completedOfficial = official && closed;
    overviewGrid.classList.toggle("is-closed", completedOfficial);
    overviewKpis.classList.toggle("is-closed", completedOfficial);
    overview.querySelector('[data-overview-panel="status"]').hidden =
      completedOfficial;
    overview.querySelector('[data-overview-panel="review"]').hidden =
      completedOfficial;
    overview.querySelector('[data-overview-panel="works"]').hidden =
      !completedOfficial;
    const competenceLabel = new Date(
      data.competencia + "-02T12:00:00",
    ).toLocaleDateString("pt-BR", { month: "long", year: "numeric" });
    overview.querySelector(".overview-footnote").textContent = official
      ? "Competência: " +
        competenceLabel +
        " · Pagamento previsto: " +
        c.previsto_em.split("-").reverse().join("/") +
        " (5º dia útil). Valores e gráficos usam o mesmo fechamento."
      : "Pago inclui a liquidação dos itens selecionados, mesmo em outro mês. Adendos documentam valores e não são somados ao custo.";
    overview.querySelector(".overview-functions h2").textContent =
      completedOfficial
        ? "Custos por função"
        : official
          ? "Componentes do fechamento"
          : "Custo por função";
    overview.querySelector(".overview-functions p").textContent =
      completedOfficial
        ? "Participação no custo do mês e comparação com o mês anterior"
        : official
          ? "Composição preservada na revisão oficial"
          : "Valores salvos nos itens, em ordem de custo";
    overview.querySelector('[data-overview-panel="top"] h2').textContent =
      completedOfficial ? "Colaboradores do mês" : "Top colaboradores do mês";
    overview.querySelector('[data-overview-panel="top"] header p').textContent =
      completedOfficial
        ? "Participação no custo do mês e comparação com o mês anterior"
        : "Selecione uma pessoa para consultar os detalhes";
    overviewCount.innerHTML = `${icon("fa-layer-group")} <span id="overview-item-count">${r.itens}</span> ${official ? "colaboradores" : "itens"} na competência`;
    overview
      .querySelector("#overview-month")
      .closest("article")
      .querySelector("h2").textContent = official
      ? "Pagamento da competência"
      : "Pagamento do mês";
    overview
      .querySelector("#overview-month")
      .closest("article")
      .querySelector("header p").textContent = official
      ? "Valores consolidados no fechamento"
      : "Distribuição financeira dos itens da competência";
    overview
      .querySelector("#overview-status")
      .closest("article")
      .querySelector("header p").textContent = official
      ? "Quantidade de colaboradores por situação financeira"
      : "Quantidade de itens por situação financeira";
    overview
      .querySelector("#overview-amendments")
      .closest("article")
      .querySelector("h2").textContent = official
      ? "Revisão do fechamento"
      : "Status dos adendos";
    overview
      .querySelector("#overview-amendments")
      .closest("article")
      .querySelector("header p").textContent = official
      ? "Confirmação documental dos colaboradores aptos"
      : "Situação documental nesta competência";
    overview.querySelector(
      ".overview-table thead th:nth-child(2)",
    ).textContent = official ? "Fechamentos" : "Itens";
    overview.querySelector("#operational-title").textContent = official
      ? "Resumo da competência"
      : "Resumo operacional";
    setHtml(
      "overview-kpis",
      [
        kpi(
          official
            ? closed
              ? "Total fechado"
              : "Parcial consolidado"
            : "Custo total da produção",
          official && !closed ? c.parcial_centavos : r.total,
          official
            ? closed
              ? "Fechamento concluído"
              : `${c.contagens.CONFIRMADO} de ${c.quantidade} revisados`
            : `${r.itens} itens`,
          "cost",
          "fa-layer-group",
        ),
        kpi(
          "Pago",
          r.pago,
          completedOfficial
            ? r.percentual_pago === null
              ? ""
              : `${percentage(r.percentual_pago)} do total`
            : `${r.itens_pagos} ${official ? "colaboradores pagos" : "itens quitados"}${r.percentual_pago === null ? "" : ` · ${percentage(r.percentual_pago)} do valor`}`,
          "paid",
          "fa-circle-check",
        ),
        kpi(
          "Pendente",
          r.pendente,
          completedOfficial
            ? r.percentual_pendente === null
              ? ""
              : `${percentage(r.percentual_pendente)} do total`
            : `${r.itens_pendentes} ${official ? "colaboradores" : "itens"}${r.percentual_pendente === null ? "" : ` · ${percentage(r.percentual_pendente)} do valor`}`,
          "pending",
          "fa-clock",
        ),
        kpi(
          official ? "Pagamentos" : "Divergências",
          official ? c.quantidade_pagos : r.divergencias,
          official
            ? `${c.quantidade_pagos}/${c.quantidade} concluídos`
            : r.divergencias
              ? "Itens que precisam de conferência"
              : "Nenhuma divergência identificada",
          "danger",
          "fa-triangle-exclamation",
          "count",
          official ? " pagos" : " ocorrências",
        ),
        kpi(
          official ? "Revisados" : "Adendos",
          official ? c.contagens.CONFIRMADO : docs.total,
          official
            ? `${c.contagens.CONFIRMADO}/${c.quantidade} PDFs confirmados`
            : `${docs.nao_assinados} não assinados`,
          "documents",
          "fa-file-lines",
          "count",
          " registros",
        ),
      ]
        .filter((_, index) => !completedOfficial || index < 3)
        .join(""),
    );
    setHtml(
      "overview-feedback",
      official
        ? closed && c.situacao === "QUITADO"
          ? ""
          : `<div class="overview-notice">${closed ? "Fechamento concluído · " + (c.situacao === "PARCIALMENTE_PAGO" ? "Pagamento em andamento" : "Pagamento pendente") : "Fechamento em andamento · Pagamento aguardando fechamento"} <a href="fechamento.php?competencia=${escape(data.competencia)}">Abrir fechamento</a></div>`
        : !r.itens
          ? empty("Nenhum item de pagamento encontrado nesta competência.")
          : r.divergencias_financeiras
            ? `<div class="overview-notice">${icon("fa-triangle-exclamation")} ${r.divergencias_financeiras} item(ns) com inconsistência no livro financeiro. ${r.excesso > 0 ? `Há ${escape(money(r.excesso))} pagos acima dos valores salvos. ` : ""}Confira os colaboradores com divergência.</div>`
            : "",
    );
    let distribution = empty(
      r.itens
        ? "A distribuição financeira não está disponível para estes valores. Confira as divergências."
        : "Nenhum pagamento encontrado nesta competência.",
    );
    if (r.grafico_financeiro_disponivel) {
      distribution = `<div class="overview-segment" role="img" aria-label="${percentage(r.percentual_pago)} pago e ${percentage(r.percentual_pendente)} pendente em valores"><span class="paid" style="width:${pct(r.pago, r.total)}%">${r.percentual_pago >= 12 ? percentage(r.percentual_pago) : ""}</span><span class="pending" style="width:${pct(r.pendente, r.total)}%">${r.percentual_pendente >= 12 ? percentage(r.percentual_pendente) : ""}</span></div>`;
    }
    const settlementByDate = new Map();
    if (completedOfficial) {
      data.colaboradores.forEach((member) => {
        const date = member.pago_em ? String(member.pago_em).slice(0, 10) : "";
        if (!date || member.situacao !== "Pago") return;
        const day = settlementByDate.get(date) || {
          date,
          total: 0,
          collaborators: 0,
        };
        day.total += Number(member.pago || 0);
        day.collaborators += 1;
        settlementByDate.set(date, day);
      });
    }
    const settlementDays = [...settlementByDate.values()].sort((a, b) =>
      a.date.localeCompare(b.date),
    );
    const maxSettlementDay = Math.max(
      0,
      ...settlementDays.map((day) => day.total),
    );
    const formatPaymentDate = (value, year = false) => {
      if (!value) return "—";
      const date = new Date(`${value}T12:00:00`);
      return Number.isNaN(date.getTime())
        ? "—"
        : date.toLocaleDateString(
            "pt-BR",
            year ? undefined : { day: "2-digit", month: "2-digit" },
          );
    };
    const lastSettlementDate = settlementDays.at(-1)?.date || "";
    const paymentInsights = completedOfficial
      ? `<div class="overview-payment-insights"><div class="overview-payment-average"><span>Média do fechamento</span><strong>${money(c.quantidade ? r.total / c.quantidade : 0)}</strong><small>por colaborador · ${c.quantidade} no fechamento</small></div><div class="overview-payment-timeline"><header><strong>Pagamentos concluídos por dia</strong><small>${settlementDays.length ? `${formatPaymentDate(settlementDays[0].date)} a ${formatPaymentDate(lastSettlementDate)}` : "Nenhum pagamento concluído"}</small></header>${settlementDays.length ? `<div class="overview-payment-days">${settlementDays.map((day) => `<div class="overview-payment-day"><time datetime="${escape(day.date)}">${formatPaymentDate(day.date)}</time><span class="overview-track" aria-hidden="true"><span style="width:${pct(day.total, maxSettlementDay)}%"></span></span><strong>${money(day.total)}</strong><small>${day.collaborators} ${day.collaborators === 1 ? "colaborador" : "colaboradores"}</small></div>`).join("")}</div>` : '<p class="overview-payment-empty">Os pagamentos aparecerão aqui quando forem registrados.</p>'}${c.situacao === "QUITADO" && lastSettlementDate ? `<p class="overview-payment-complete">${icon("fa-calendar-check")} Quitação completa em <strong>${formatPaymentDate(lastSettlementDate, true)}</strong></p>` : `<p class="overview-payment-pending">${icon("fa-clock")} Faltam <strong>${r.itens_pendentes} ${r.itens_pendentes === 1 ? "colaborador" : "colaboradores"}</strong> · ${money(r.pendente)} pendentes</p>`}${r.pago > 0 && r.pendente > 0 ? '<small class="overview-payment-footnote">Pagamentos parciais estão somados ao total Pago; o gráfico mostra as quitações completas.</small>' : ""}</div></div>`
      : "";
    setHtml(
      "overview-month",
      `${distribution}<div class="overview-month-legend"><div class="paid"><span>${icon("fa-circle")} Pago</span><strong>${number(r.pago, "money")}</strong><small>${r.itens_pagos} ${official ? "colaboradores pagos" : "itens quitados"}</small></div><div class="pending"><span>${icon("fa-circle")} Pendente</span><strong>${number(r.pendente, "money")}</strong><small>${r.itens_pendentes} ${official ? "colaboradores pendentes" : "itens"}</small></div></div>${paymentInsights}`,
    );
    setHtml(
      "overview-status",
      r.itens
        ? `${metricBar("Pagos", r.itens_pagos, r.itens, "paid")}${metricBar("Pendentes", r.itens_pendentes, r.itens, "pending")}${metricBar("Com divergência", r.divergencias, r.itens, "danger")}<p class="overview-small-note">Divergências podem ocorrer em itens pagos ou pendentes.</p>`
        : empty("Nenhum item nesta competência."),
    );
    if (official && !closed) {
      setHtml(
        "overview-month",
        empty(
          "Aguardando conclusão do fechamento. O parcial consolidado ainda não é o valor definitivo a pagar.",
        ),
      );
      const cards = document.querySelectorAll(
        "#overview-kpis .overview-kpi strong",
      );
      if (cards[1]) cards[1].textContent = "—";
      if (cards[2]) cards[2].textContent = "—";
    }
    if (official)
      setHtml(
        "overview-status",
        `${metricBar("Pagos", c.quantidade_pagos, c.quantidade, "paid")}${metricBar("Pendentes", c.quantidade - c.quantidade_pagos, c.quantidade, "pending")}`,
      );
    const states = Object.entries(docs.status).filter(([, count]) => count > 0);
    setHtml(
      "overview-amendments",
      docs.total
        ? states
            .map(([state, count]) =>
              metricBar(
                state === "confirmado"
                  ? "Revisado (PDF confirmado)"
                  : adendoStatusInfo(state).label,
                count,
                docs.total,
                state === "assinado"
                  ? "paid"
                  : ["recusado", "expirado"].includes(state)
                    ? "danger"
                    : state === "nao_gerado"
                      ? "pending"
                      : "documents",
              ),
            )
            .join("")
        : empty("Nenhum adendo registrado nesta competência."),
    );
    const functionCostsTotal = data.funcoes.reduce(
      (total, item) => total + Number(item.total),
      0,
    );
    const functionTasksTotal = data.funcoes.reduce(
      (total, item) => total + Number(item.tarefas || 0),
      0,
    );
    const topThreeFunctions = data.funcoes
      .filter((item) => Number(item.total) > 0)
      .slice(0, 3);
    const topThreeCost = topThreeFunctions.reduce(
      (total, item) => total + Number(item.total),
      0,
    );
    setHtml(
      "overview-function-highlights",
      completedOfficial && functionCostsTotal > 0
        ? `<div class="overview-function-highlight"><span>${icon("fa-trophy")} Top 3 funções = ${percentage((topThreeCost / functionCostsTotal) * 100)} do custo por função</span><small>${money(topThreeCost)} do custo de tarefas no mês</small></div><div class="overview-function-highlight"><span>${icon("fa-calculator")} Ticket médio: ${functionTasksTotal ? money(functionCostsTotal / functionTasksTotal) : "—"}</span><small>por tarefa</small></div>`
        : "",
    );
    setHtml(
      "overview-functions",
      data.funcoes.length
        ? completedOfficial
          ? `<div class="overview-function-table"><div class="overview-function-row overview-function-head"><span>Função</span><span class="overview-function-bar-heading" aria-hidden="true"></span><span>Custo (R$)</span><span>% do total</span><span>vs mês anterior</span></div>${data.funcoes
              .map((item) => {
                const variation = item.variacao_percentual;
                const trend =
                  variation === null || variation === undefined
                    ? item.mes_anterior_centavos === 0 && Number(item.total) > 0
                      ? `<span class="overview-trend trend-up">▲ Novo</span>`
                      : '<span class="overview-trend trend-neutral">—</span>'
                    : variation > 0
                      ? `<span class="overview-trend trend-up">▲ +${percentage(variation)}</span>`
                      : variation < 0
                        ? `<span class="overview-trend trend-down">▼ ${percentage(variation)}</span>`
                        : '<span class="overview-trend trend-neutral">— 0%</span>';
                return `<div class="overview-function-row"><span class="overview-function-name" title="${escape(item.nome)}">${escape(item.nome)}</span><span class="overview-track" aria-hidden="true"><span style="width:${pct(item.total, functionCostsTotal)}%"></span></span><strong>${money(item.total)}</strong><small>${functionCostsTotal > 0 ? percentage((item.total / functionCostsTotal) * 100) : "—"}</small>${trend}</div>`;
              })
              .join(
                "",
              )}</div>${data.comparacao_mes_anterior?.disponivel ? "" : '<p class="overview-small-note">Comparação indisponível: o fechamento do mês anterior ainda não foi concluído.</p>'}`
          : `<div class="overview-rank-list">${data.funcoes.map((item) => `<div class="overview-function-row"><span title="${escape(item.nome)}">${escape(item.nome)}</span><span class="overview-track" aria-hidden="true"><span style="width:${pct(item.total, functionCostsTotal)}%"></span></span><strong>${number(item.total, "money")}</strong><small>${functionCostsTotal > 0 && item.total >= 0 ? number((item.total / functionCostsTotal) * 100, "percent") : "—"}</small></div>`).join("")}</div>`
        : empty(
            official && !closed
              ? "Disponível após a conclusão do fechamento."
              : "Nenhum custo por função nesta competência.",
          ),
    );
    const top =
      official && !closed
        ? []
        : data.colaboradores
            .filter((c) => c.itens > 0)
            .sort((a, b) => b.total - a.total)
            .slice(0, 5);
    const collaboratorRows = completedOfficial
      ? [...data.colaboradores].sort(
          (a, b) =>
            Number(b.total) - Number(a.total) ||
            a.nome.localeCompare(b.nome, "pt-BR"),
        )
      : top;
    const collaboratorCostsTotal = collaboratorRows.reduce(
      (total, c) => total + Number(c.total),
      0,
    );
    const collaboratorTrend = (c) => {
      const variation = c.variacao_percentual;
      if (variation === null || variation === undefined) {
        return c.mes_anterior_centavos === 0 && Number(c.total) > 0
          ? '<span class="overview-trend trend-up">▲ Novo</span>'
          : '<span class="overview-trend trend-neutral">—</span>';
      }
      if (variation > 0)
        return `<span class="overview-trend trend-up">▲ +${percentage(variation)}</span>`;
      if (variation < 0)
        return `<span class="overview-trend trend-down">▼ ${percentage(variation)}</span>`;
      return '<span class="overview-trend trend-neutral">— 0%</span>';
    };
    setHtml(
      "overview-top",
      collaboratorRows.length
        ? completedOfficial
          ? `<div class="overview-collaborator-table"><div class="overview-collaborator-row overview-collaborator-head"><span>Colaborador</span><span aria-hidden="true"></span><span>Custo (R$)</span><span>% do total</span><span>vs mês anterior</span></div>${collaboratorRows.map((c) => `<button type="button" class="overview-collaborator-row" data-colaborador="${c.colaborador_id}"><span class="overview-collaborator-name" title="${escape(c.nome)}">${escape(c.nome)}</span><span class="overview-track" aria-hidden="true"><span style="width:${pct(c.total, collaboratorCostsTotal)}%"></span></span><strong>${money(c.total)}</strong><small>${collaboratorCostsTotal > 0 ? percentage((c.total / collaboratorCostsTotal) * 100) : "—"}</small>${collaboratorTrend(c)}</button>`).join("")}</div>${data.comparacao_mes_anterior?.disponivel ? "" : '<p class="overview-small-note">Comparação indisponível: o fechamento do mês anterior ainda não foi concluído.</p>'}`
          : `<div class="overview-rank-list">${top.map((c, index) => `<button type="button" class="overview-top-row" data-colaborador="${c.colaborador_id}"><span class="overview-rank">${index + 1}</span><span>${escape(c.nome)}</span><span class="overview-track" aria-hidden="true"><span style="width:${pct(c.total, top[0].total)}%"></span></span><strong>${number(c.total, "money")}</strong></button>`).join("")}</div>`
        : empty(
            official && !closed
              ? "Disponível após a conclusão do fechamento."
              : "Nenhum colaborador com itens nesta competência.",
          ),
    );
    const works = completedOfficial
      ? data.obras.filter((obra) => Number(obra.total) > 0)
      : [];
    const costlyWorks = works;
    const worksTotal = works.reduce(
      (total, obra) => total + Number(obra.total),
      0,
    );
    const reconciledWithoutWork = Number(
      data.creditos_reconciliados_sem_obra_centavos || 0,
    );
    const taskCostsWithoutWork = Math.max(
      0,
      Number(data.custos_sem_obra_centavos || 0) - reconciledWithoutWork,
    );
    setHtml(
      "overview-works",
      costlyWorks.length
        ? `<div class="overview-work-table"><div class="overview-work-row overview-work-head"><span>#</span><span>Obra</span><span class="overview-work-bar-heading" aria-hidden="true"></span><span>Custo total (R$)</span><span>% do total</span><span>Tarefas</span><span>Médio/tarefa</span></div>${costlyWorks.map((obra, index) => `<div class="overview-work-row"><span class="overview-rank">${index + 1}</span><span class="overview-work-name" title="${escape(obra.nome)}">${escape(obra.nome)}</span><span class="overview-track" aria-hidden="true"><span style="width:${pct(obra.total, costlyWorks[0].total)}%"></span></span><strong>${money(obra.total)}</strong><small>${worksTotal > 0 ? percentage((obra.total / worksTotal) * 100) : "—"}</small><span>${Number(obra.tarefas || 0).toLocaleString("pt-BR")}</span><span>${obra.tarefas ? money(obra.total / Number(obra.tarefas)) : "—"}</span></div>`).join("")}</div><p class="overview-small-note">Média calculada sobre tarefas com custo reconhecido. ${taskCostsWithoutWork > 0 ? `${money(taskCostsWithoutWork)} em tarefas sem obra` : ""}${taskCostsWithoutWork > 0 && reconciledWithoutWork > 0 ? " · " : ""}${reconciledWithoutWork > 0 ? `${money(reconciledWithoutWork)} em créditos reconciliados sem vínculo individual com uma obra` : ""}${taskCostsWithoutWork > 0 || reconciledWithoutWork > 0 ? " ficam fora do ranking." : " Custos fixos e bônus não são atribuídos a obras."}</p>`
        : empty(
            "Nenhum custo de tarefa ou serviço vinculado a obras nesta competência.",
          ),
    );
    const oldRole = role.value;
    const oldWork = work.value;
    role.innerHTML =
      '<option value="">Todas as funções</option>' +
      data.funcoes
        .map(
          (f) => `<option value="${escape(f.nome)}">${escape(f.nome)}</option>`,
        )
        .join("");
    work.innerHTML =
      '<option value="">Todas as obras</option>' +
      data.obras
        .map(
          (o) =>
            `<option value="${o.id}">${escape(o.nome || `Obra #${o.id}`)}</option>`,
        )
        .join("");
    role.value = oldRole;
    work.value = oldWork;
    renderRows();
    window.pagamentoMotion?.animate(overview);
  }

  function renderRows() {
    if (!payload) return;
    const query = search.value.trim().toLocaleLowerCase("pt-BR");
    const rows = payload.colaboradores.filter(
      (c) =>
        c.nome.toLocaleLowerCase("pt-BR").includes(query) &&
        (!role.value || c.funcoes.includes(role.value)) &&
        (!work.value || c.obras.includes(Number(work.value))) &&
        (!pending.checked || c.itens_pendentes > 0),
    );
    document.getElementById("overview-results").textContent =
      `${rows.length} colaboradores`;
    setHtml(
      "overview-rows",
      rows.length
        ? rows
            .map((c) => {
              const initials = c.nome
                .trim()
                .split(/\s+/)
                .map((s) => s[0])
                .slice(0, 2)
                .join("")
                .toUpperCase();
              const tone = c.divergencias
                ? "danger"
                : c.itens_pendentes
                  ? "pending"
                  : c.itens
                    ? "paid"
                    : "neutral";
              return `<tr><td><button type="button" class="overview-person" data-colaborador="${c.colaborador_id}"><span class="overview-avatar" aria-hidden="true">${escape(initials)}</span><span>${escape(c.nome)}</span>${icon("fa-chevron-right")}</button></td><td class="col-right">${c.itens}</td><td class="col-right">${payload.fechamento?.estado === "EM_ANDAMENTO" ? "—" : money(c.total)}</td><td class="col-right">${payload.fechamento?.estado === "EM_ANDAMENTO" ? "—" : money(c.pago)}</td><td class="col-right">${payload.fechamento?.estado === "EM_ANDAMENTO" ? "—" : money(c.pendente)}</td><td class="col-right">${c.adendos}</td><td><span class="overview-status-pill ${tone}">${icon(c.divergencias ? "fa-triangle-exclamation" : c.itens_pendentes ? "fa-clock" : c.itens ? "fa-circle-check" : "fa-minus")}${escape(c.situacao)}</span></td></tr>`;
            })
            .join("")
        : `<tr><td colspan="7" class="overview-empty">${payload.colaboradores.length ? (pending.checked && !search.value && !role.value && !work.value ? "Nenhum item pendente nesta competência." : "Nenhum colaborador corresponde aos filtros.") : "Nenhum colaborador com registros nesta competência."}</td></tr>`,
    );
  }

  function skeleton() {
    setHtml(
      "overview-feedback",
      '<span class="sr-only">Carregando visão geral…</span>',
    );
    setHtml(
      "overview-kpis",
      Array.from(
        { length: 5 },
        () =>
          '<div class="overview-kpi overview-skeleton"><span></span><span></span><span></span></div>',
      ).join(""),
    );
    ["month", "status", "amendments", "functions", "top"].forEach((id) =>
      setHtml(
        `overview-${id}`,
        '<div class="overview-skeleton overview-card-skeleton"><span></span><span></span><span></span></div>',
      ),
    );
    setHtml(
      "overview-rows",
      '<tr><td colspan="7"><div class="overview-skeleton"><span></span><span></span></div></td></tr>',
    );
    document.getElementById("overview-item-count").textContent = "—";
    document.getElementById("overview-results").textContent = "";
  }

  async function load() {
    controller?.abort();
    window.pagamentoMotion?.stop(overview);
    const request = new AbortController();
    controller = request;
    payload = null;
    overview.setAttribute("aria-busy", "true");
    skeleton();
    const params = new URLSearchParams({ mes: mes.value, ano: ano.value });
    try {
      const response = await fetch(
        `${document.querySelector('meta[name="pagamento-test-overview"]')?.content || "getVisaoGeral.php?"}${params}`,
        {
          signal: request.signal,
        },
      );
      const data = await response.json();
      if (request.signal.aborted) return;
      if (!data.success)
        throw new Error(
          data.error || "Não foi possível carregar a visão geral.",
        );
      payload = data;
      render(data);
    } catch (error) {
      if (request.signal.aborted) return;
      setHtml("overview-kpis", "");
      ["month", "status", "amendments", "functions", "top"].forEach((id) =>
        setHtml(`overview-${id}`, empty("Dados indisponíveis.")),
      );
      setHtml(
        "overview-rows",
        '<tr><td colspan="7" class="overview-empty">Não foi possível consultar os colaboradores.</td></tr>',
      );
      setHtml(
        "overview-feedback",
        `<div class="overview-notice" role="alert">${escape(error.message)} <button class="btn btn-secondary" type="button" id="overview-retry">Tentar novamente</button></div>`,
      );
      document.getElementById("overview-retry").addEventListener("click", load);
    } finally {
      if (controller === request) overview.setAttribute("aria-busy", "false");
    }
  }

  actions
    .querySelectorAll("[data-view]")
    .forEach((button) =>
      button.addEventListener("click", () => setView(button.dataset.view)),
    );
  overview.addEventListener("click", (event) => {
    const button = event.target.closest("[data-colaborador]");
    if (button) drillDown(button.dataset.colaborador);
  });
  search.addEventListener("input", renderRows);
  [role, work, pending].forEach((el) =>
    el.addEventListener("change", renderRows),
  );
  document.getElementById("overview-clear").addEventListener("click", () => {
    search.value = "";
    role.value = "";
    work.value = "";
    pending.checked = false;
    renderRows();
  });
  [mes, ano].forEach((el) =>
    el.addEventListener("change", () => {
      persist();
      if (document.body.dataset.paymentView === "geral") load();
    }),
  );
  colaborador.addEventListener("change", persist);
  const params = new URLSearchParams(location.search);
  if (/^(?:[1-9]|1[0-2])$/.test(params.get("mes") || ""))
    mes.value = params.get("mes");
  const year = params.get("ano");
  if (/^20\d{2}$/.test(year || "")) {
    if (!Array.from(ano.options).some((option) => option.value === year))
      ano.add(new Option(year, year));
    ano.value = year;
  }
  const colabId = params.get("colaborador_id");
  if (
    colabId &&
    Array.from(colaborador.options).some((option) => option.value === colabId)
  )
    colaborador.value = colabId;
  setView(params.get("view") === "colaborador" ? "colaborador" : "geral");
  if (params.get("adendos") === "1") statusButton.click();
});
