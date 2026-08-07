function formatarDataAtual() {
  const opcoes = { weekday: "long", day: "numeric", month: "long" };
  const dataAtual = new Date();
  return dataAtual.toLocaleDateString("pt-BR", opcoes);
}

const pagamentoCsrfToken =
  document.querySelector('meta[name="pagamento-csrf"]')?.content || "";
const pagamentoFetch = window.fetch.bind(window);
window.fetch = function (input, init = {}) {
  const method = String(init.method || "GET").toUpperCase();
  if (
    pagamentoCsrfToken &&
    ["POST", "PUT", "PATCH", "DELETE"].includes(method)
  ) {
    const headers = new Headers(init.headers || {});
    headers.set("X-CSRF-Token", pagamentoCsrfToken);
    init.headers = headers;
  }
  return pagamentoFetch(input, init).then(async (response) => {
    if (response.ok) return response;

    let payload = {};
    try {
      payload = await response.clone().json();
    } catch (_) {
      // Mantém uma mensagem estável quando o servidor retornar HTML ou vazio.
    }

    const messages = {
      401: "Sua sessão expirou. Atualize a página e entre novamente.",
      403: "Você não tem permissão para executar esta ação.",
      419: "A página ficou desatualizada. Atualize e tente novamente.",
      422: "Os dados enviados são inválidos.",
      500: "O servidor não conseguiu concluir a operação.",
    };
    const error = new Error(
      payload.error ||
        payload.message ||
        messages[response.status] ||
        "Não foi possível concluir a operação.",
    );
    error.status = response.status;
    error.payload = payload;
    throw error;
  });
};

function setPagamentoButtonLoading(
  button,
  loading,
  loadingLabel = "Processando...",
) {
  if (!button) return;
  if (loading) {
    if (!button.dataset.originalHtml)
      button.dataset.originalHtml = button.innerHTML;
    button.disabled = true;
    button.setAttribute("aria-busy", "true");
    button.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${loadingLabel}`;
  } else {
    button.disabled = false;
    button.removeAttribute("aria-busy");
    if (button.dataset.originalHtml) {
      button.innerHTML = button.dataset.originalHtml;
      delete button.dataset.originalHtml;
    }
  }
}

function pagamentoVisibleCheckboxes(selector = ".pagamento-checkbox") {
  return Array.from(document.querySelectorAll(selector)).filter((checkbox) => {
    const row = checkbox.closest("tr");
    return row && row.offsetParent !== null && !checkbox.disabled;
  });
}

function pagamentoSelectedValue(checkbox) {
  const dataValue = checkbox?.getAttribute("data-valor");
  if (
    dataValue !== null &&
    dataValue !== "" &&
    Number.isFinite(Number(dataValue))
  ) {
    return Number(dataValue);
  }
  const valueCell = checkbox?.closest("tr")?.cells?.[3];
  const raw = valueCell?.textContent || "0";
  return (
    Number(
      raw
        .replace(/[^0-9,.-]+/g, "")
        .replace(/\./g, "")
        .replace(",", "."),
    ) || 0
  );
}

function atualizarResumoSelecao() {
  const summary = document.getElementById("selection-summary");
  if (!summary) return;
  const selected = Array.from(
    document.querySelectorAll(".pagamento-checkbox:checked"),
  );
  const total = selected.reduce(
    (sum, checkbox) => sum + pagamentoSelectedValue(checkbox),
    0,
  );
  summary.textContent = `${selected.length} ${selected.length === 1 ? "item selecionado" : "itens selecionados"} · ${total.toLocaleString("pt-BR", { style: "currency", currency: "BRL" })}`;
}

/* Estrutura visual da tela principal. Os elementos com IDs legados são apenas
 * reposicionados para que os endpoints e os fluxos existentes permaneçam iguais. */
document.addEventListener(
  "DOMContentLoaded",
  function prepararPagamentoRedesenhado() {
    const header = document.querySelector(".page-header");
    const filters = document.querySelector(".filters");
    const workspace = document.querySelector(".table-scroll-area");
    const tableUnpaid = document.getElementById("tabela-a-pagar");
    const tablePaid = document.getElementById("tabela-pago");
    const typeFilters = document.querySelector(".tipo-imagem");
    const info = document.getElementById("info-colaborador");
    const resumo = document.getElementById("resumo-pagamentos");
    const oldToolbar = document.querySelector(".action-toolbar");
    const oldConfirm = document.querySelector(".confirm-payment-row");
    const oldTotals = document.querySelector(".totals-bar")?.parentElement;
    if (
      !header ||
      !filters ||
      !workspace ||
      !tableUnpaid ||
      !tablePaid ||
      !typeFilters
    )
      return;

    header.classList.add("payment-header");
    const logo = header.querySelector("#gif");
    const statusButton = header.querySelector("#btn-ver-status-geral");
    header.replaceChildren();
    const heading = document.createElement("div");
    heading.className = "payment-heading";
    if (logo) heading.append(logo);
    heading.insertAdjacentHTML(
      "beforeend",
      '<div><span class="page-kicker">/ Flow</span><h1 class="page-title">Pagamento</h1></div>',
    );
    header.append(heading);
    if (statusButton) {
      statusButton.innerHTML =
        '<i class="fa-regular fa-file-lines"></i> Status geral dos adendos';
      header.append(statusButton);
    }

    filters.classList.add("competencia-bar");
    const resultBadge =
      header.querySelector(".results-badge") || document.createElement("span");
    resultBadge.className = "competencia-count";
    resultBadge.innerHTML =
      '<i class="fa-solid fa-layer-group"></i> <span id="total-imagens">0</span> itens na competência';
    filters.append(resultBadge);

    const statusWidget = document.getElementById("adendo-status-widget");
    const generateAdendo = document.getElementById("generate-adendo");
    const generateLista = document.getElementById("generate-lista");
    const generateExcel = document.getElementById("generate-excel");
    const adendoInfo = document.getElementById("btn-adendo-info");
    const financial = document.createElement("section");
    financial.className = "financial-summary";
    financial.innerHTML =
      '<div class="financial-metrics"><div class="summary-metric"><span class="summary-label">Total da competência</span><strong id="totalValor">R$ 0,00</strong><small><span id="total-itens-resumo">0</span> itens</small></div><div class="summary-metric pending"><span class="summary-label">Pendente</span><strong id="totalValorNaoPago">R$ 0,00</strong><small><span id="total-imagens-nao-pagas">0</span> itens</small></div><div class="summary-metric paid"><span class="summary-label">Pago</span><strong id="totalValorPago">R$ 0,00</strong><small><span id="total-imagens-pagas">0</span> itens</small></div></div>';
    const documentArea = document.createElement("div");
    documentArea.className = "adendo-summary";
    documentArea.innerHTML =
      '<i class="fa-regular fa-file-lines adendo-summary-icon"></i><div class="adendo-copy"></div><div class="adendo-actions"></div>';
    const copy = documentArea.querySelector(".adendo-copy");
    if (statusWidget) {
      statusWidget.style.display = "flex";
      copy.append(statusWidget);
    } else
      copy.innerHTML =
        "<strong>Adendo</strong><span>Selecione uma competência</span>";
    const actions = documentArea.querySelector(".adendo-actions");
    if (adendoInfo) {
      adendoInfo.classList.add("btn", "btn-secondary");
      adendoInfo.innerHTML = '<i class="fa-regular fa-eye"></i> Ver adendo';
      actions.append(adendoInfo);
    }
    const docActions = document.createElement("div");
    docActions.className = "document-actions";
    docActions.innerHTML =
      '<button class="btn btn-secondary" type="button" id="btn-document-actions">Outras ações <i class="fa-solid fa-chevron-down"></i></button><div class="document-actions-menu"></div>';
    const docMenu = docActions.querySelector(".document-actions-menu");
    [generateLista, generateExcel, generateAdendo]
      .filter(Boolean)
      .forEach((button) => {
        button.className = "document-action";
        docMenu.append(button);
      });
    actions.append(docActions);
    financial.append(documentArea);
    filters.insertAdjacentElement("afterend", financial);

    const unpaidWrap = document.createElement("div");
    unpaidWrap.className = "table-wrap";
    unpaidWrap.append(tableUnpaid);
    const paidWrap = document.createElement("div");
    paidWrap.className = "table-wrap";
    paidWrap.append(tablePaid);
    const divergenceTable = document.createElement("table");
    divergenceTable.id = "tabela-divergencias";
    divergenceTable.className = "data-table";
    divergenceTable.innerHTML =
      "<thead><tr><th>Tarefa / imagem</th><th>Função</th><th>Valor atual</th><th>Valor esperado</th><th>Situação</th><th>Ações</th></tr></thead><tbody></tbody>";
    const divergenceWrap = document.createElement("div");
    divergenceWrap.className = "table-wrap";
    divergenceWrap.append(divergenceTable);
    tableUnpaid.querySelector("thead").innerHTML =
      '<tr><th class="col-checkbox"><input type="checkbox" id="selecionar-visiveis" aria-label="Selecionar itens elegíveis visíveis"></th><th>Tarefa / imagem</th><th>Função</th><th>Valor</th><th>Situação financeira</th><th class="col-center">Ações</th></tr>';
    tablePaid.querySelector("thead").innerHTML =
      "<tr><th>Tarefa / imagem</th><th>Função</th><th>Valor pago</th><th>Tipo</th><th>Data do pagamento</th><th>Detalhes</th></tr>";

    const tabs = document.createElement("div");
    tabs.className = "payment-tabs";
    tabs.innerHTML =
      '<button class="payment-tab is-active" type="button" data-payment-tab="a-pagar">A pagar <span id="tab-count-a-pagar">0</span></button><button class="payment-tab" type="button" data-payment-tab="pagos">Pagos <span id="tab-count-pagos">0</span></button><button class="payment-tab" type="button" data-payment-tab="divergencias">Divergências <span class="is-danger" id="tab-count-divergencias">0</span></button>';
    const compact = document.createElement("div");
    compact.className = "compact-filters";
    compact.innerHTML =
      '<label class="search-filter"><i class="fa-solid fa-magnifying-glass"></i><input id="busca-pagamento" type="search" placeholder="Buscar tarefa ou imagem..."></label><div class="function-filter"><button class="compact-filter-button" id="btn-funcoes" type="button">Funções <span id="funcoes-count">(0)</span> <i class="fa-solid fa-chevron-down"></i></button></div><button class="compact-filter-button is-disabled" type="button" title="Aguardando identificação confiável da obra pelo backend">Obra <i class="fa-solid fa-chevron-down"></i></button><label class="toggle-filter"><input id="somente-divergencias" type="checkbox"><span></span> Somente divergências</label><button class="btn btn-secondary" id="limpar-filtros" type="button"><i class="fa-regular fa-trash-can"></i> Limpar filtros</button>';
    compact.querySelector(".function-filter").append(typeFilters);
    typeFilters.id = "funcoes-popover";
    const panels = document.createElement("div");
    panels.className = "payment-panels";
    [
      ["a-pagar", unpaidWrap],
      ["pagos", paidWrap],
      ["divergencias", divergenceWrap],
    ].forEach(([name, wrap], index) => {
      const panel = document.createElement("section");
      panel.className = `table-section payment-tab-panel${index ? "" : " is-active"}`;
      panel.dataset.paymentPanel = name;
      panel.append(wrap);
      panels.append(panel);
    });
    const actionBar = document.createElement("div");
    actionBar.className = "selection-action-bar";
    actionBar.id = "selection-action-bar";
    actionBar.hidden = true;
    const selectAll = document.createElement("button");
    selectAll.id = "marcar-todos";
    selectAll.hidden = true;
    actionBar.append(selectAll);
    const addValue = oldToolbar?.querySelector("#adicionar-valor");
    const clear = oldToolbar?.querySelector("#desmarcar-todos");
    const amount = oldToolbar?.querySelector("#valor");
    const confirm = oldConfirm?.querySelector("#confirmar-pagamento");
    const summary = oldToolbar?.querySelector("#selection-summary");
    actionBar.innerHTML +=
      '<div><strong id="selection-summary">0 itens selecionados · R$ 0,00</strong></div><div class="selection-actions"></div>';
    const selectionActions = actionBar.querySelector(".selection-actions");
    [addValue, clear, confirm]
      .filter(Boolean)
      .forEach((button) => selectionActions.append(button));
    if (addValue)
      addValue.innerHTML = '<i class="fa-solid fa-pen"></i> Adicionar valor';
    if (clear)
      clear.innerHTML = '<i class="fa-solid fa-xmark"></i> Limpar seleção';
    if (confirm) {
      confirm.classList.remove("btn-success");
      confirm.classList.add("btn-primary");
      confirm.innerHTML =
        '<i class="fa-solid fa-credit-card"></i> Confirmar pagamento';
    }
    if (amount) {
      // O campo acompanha a barra de seleção e fica disponível assim que
      // qualquer tarefa for marcada.
      amount.hidden = false;
      actionBar.append(amount);
    }
    if (summary) summary.remove();
    oldToolbar?.remove();
    oldConfirm?.remove();
    oldTotals?.remove();
    workspace.replaceChildren(tabs, compact, panels, actionBar, info, resumo);

    const setTab = (name) => {
      tabs
        .querySelectorAll(".payment-tab")
        .forEach((tab) =>
          tab.classList.toggle("is-active", tab.dataset.paymentTab === name),
        );
      panels
        .querySelectorAll(".payment-tab-panel")
        .forEach((panel) =>
          panel.classList.toggle(
            "is-active",
            panel.dataset.paymentPanel === name,
          ),
        );
      if (name !== "a-pagar") {
        document
          .querySelectorAll(".pagamento-checkbox:checked")
          .forEach((cb) => {
            cb.checked = false;
            cb.closest("tr")?.classList.remove("row-selected");
          });
        atualizarResumoSelecao();
      }
    };
    tabs.addEventListener("click", (event) => {
      const tab = event.target.closest(".payment-tab");
      if (tab) setTab(tab.dataset.paymentTab);
    });
    document
      .getElementById("btn-document-actions")
      ?.addEventListener("click", () => docActions.classList.toggle("is-open"));
    document.addEventListener("click", (event) => {
      if (!docActions.contains(event.target))
        docActions.classList.remove("is-open");
    });
    document
      .getElementById("btn-funcoes")
      ?.addEventListener("click", () =>
        typeFilters.classList.toggle("is-open"),
      );
    document.addEventListener("click", (event) => {
      if (!event.target.closest(".function-filter"))
        typeFilters.classList.remove("is-open");
    });
    document
      .getElementById("adicionar-valor")
      ?.addEventListener("click", () => {
        amount?.focus();
      });
    const search = document.getElementById("busca-pagamento"),
      divergencesOnly = document.getElementById("somente-divergencias");
    const applyVisualFilters = () => {
      const needle = (search?.value || "").trim().toLocaleLowerCase("pt-BR");
      const selectedFunctions = Array.from(
        typeFilters.querySelectorAll("input:checked"),
      ).map((input) => input.name.toLocaleLowerCase("pt-BR"));
      [tableUnpaid, tablePaid, divergenceTable].forEach((table) =>
        table.querySelectorAll("tbody tr").forEach((row) => {
          const functionName = (
            row.dataset.functionName ||
            row.children[2]?.textContent ||
            row.children[1]?.textContent ||
            ""
          ).toLocaleLowerCase("pt-BR");
          const matchesSearch =
            !needle ||
            row.textContent.toLocaleLowerCase("pt-BR").includes(needle);
          const matchesFunction =
            !selectedFunctions.length ||
            selectedFunctions.some((fn) => functionName.includes(fn));
          const matchesDivergence =
            !divergencesOnly?.checked || row.dataset.divergence === "1";
          row.style.display =
            matchesSearch && matchesFunction && matchesDivergence ? "" : "none";
        }),
      );
      const count = selectedFunctions.length;
      document.getElementById("funcoes-count").textContent = `(${count})`;
      contarLinhasTabela();
    };
    search?.addEventListener("input", applyVisualFilters);
    divergencesOnly?.addEventListener("change", applyVisualFilters);
    typeFilters.addEventListener("change", applyVisualFilters);
    document.getElementById("limpar-filtros")?.addEventListener("click", () => {
      if (search) search.value = "";
      if (divergencesOnly) divergencesOnly.checked = false;
      typeFilters
        .querySelectorAll("input")
        .forEach((input) => (input.checked = false));
      applyVisualFilters();
    });
    document
      .getElementById("selecionar-visiveis")
      ?.addEventListener("change", (event) => {
        pagamentoVisibleCheckboxes(
          "#tabela-a-pagar .pagamento-checkbox",
        ).forEach((cb) => {
          cb.checked = event.target.checked;
          cb.closest("tr")?.classList.toggle("row-selected", cb.checked);
        });
        contarLinhasTabela();
      });
    window.pagamentoAplicarFiltrosVisuais = applyVisualFilters;
  },
);

document.addEventListener("DOMContentLoaded", function () {
  // ====== Resumo (dashboard) ======
  const mesResumo = document.getElementById("mes-resumo");
  const anoResumo = document.getElementById("ano-resumo");
  const mesTarefas = document.getElementById("mes");
  const anoTarefas = document.getElementById("ano");
  const btnResumo = document.getElementById("btn-carregar-resumo");
  // statusNovo is defined per item inside carregarResumo's loop
  function setDefaultMesAnoResumo() {
    if (!mesResumo || !anoResumo) return;
    const today = new Date();
    const prev = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    mesResumo.value = (prev.getMonth() + 1).toString();
    anoResumo.value = prev.getFullYear().toString();
    if (mesTarefas && anoTarefas) {
      mesTarefas.value = (prev.getMonth() + 1).toString();
      anoTarefas.value = prev.getFullYear().toString();
    }
  }

  function currencyBRL(n) {
    return (n || 0).toLocaleString("pt-BR", {
      style: "currency",
      currency: "BRL",
    });
  }

  async function carregarResumo() {
    if (!mesResumo || !anoResumo) return;
    const mes = parseInt(mesResumo.value, 10);
    const ano = parseInt(anoResumo.value, 10);
    const tbody = document.querySelector("#tabela-resumo tbody");
    if (tbody) tbody.innerHTML = '<tr><td colspan="7">Carregando...</td></tr>';
    try {
      const res = await fetch(
        `getResumo.php?mes=${encodeURIComponent(mes)}&ano=${encodeURIComponent(ano)}`,
      );
      const json = await res.json();
      if (!tbody) return;
      tbody.innerHTML = "";
      if (!json.items || json.items.length === 0) {
        tbody.innerHTML =
          '<tr><td colspan="7">Sem dados para o período.</td></tr>';
        return;
      }
      json.items.forEach((item) => {
        // determine canonical key and next action
        const normalize = (s) =>
          (s || "").toString().trim().toLowerCase().replace(/\s+/g, "_");
        const key = normalize(item.status);

        let displayLabel = "Desconhecido";
        if (key === "adendo_gerado" || key === "adendo")
          displayLabel = "Adendo gerado";
        else if (key === "pago") displayLabel = "Pago";
        else if (
          key === "aguardando_retorno" ||
          key === "enviado" ||
          key === "confirmando"
        )
          displayLabel = "Aguardando retorno";
        else if (key === "pendente_envio" || key === "pendente")
          displayLabel = "Pendente envio";
        else if (key === "validado" || key === "confirmado")
          displayLabel = "Validado";

        // next action mapping: returns { label, nextStatus, btnClass }
        function nextActionFor(key) {
          switch (key) {
            case "pendente_envio":
            case "pendente":
              return {
                label: "Enviar lista",
                nextStatus: "aguardando_retorno",
                btnClass: "send",
              };
            case "aguardando_retorno":
            case "enviado":
            case "confirmando":
              return {
                label: "Marcar lista respondida",
                nextStatus: "validado",
                btnClass: "validate",
              };
            case "validado":
            case "confirmado":
              return {
                label: "Gerar adendo",
                nextStatus: "adendo_gerado",
                btnClass: "adendo",
              };
            case "adendo_gerado":
            case "adendo":
              return {
                label: "Marcar pago",
                nextStatus: "pago",
                btnClass: "pay",
              };
            case "pago":
              return { label: null, nextStatus: null, btnClass: "pay" };
            default:
              return {
                label: "Enviar lista",
                nextStatus: "aguardando_retorno",
                btnClass: "send",
              };
          }
        }

        const action = nextActionFor(key);

        const tr = document.createElement("tr");
        tr.innerHTML = `
                    <td>${item.nome}</td>
                    <td>${item.mes_ref}</td>
                    <td data-fixo="${item.valor_fixo}">${currencyBRL(item.valor_fixo)}</td>
                    <td data-valor="${item.valor}">${currencyBRL(item.valor)}</td>
                    <td><span class="badge status-${(key || "").toLowerCase()}">${displayLabel}</span></td>
                    <td>${item.ultima_atualizacao ? item.ultima_atualizacao : "-"}</td>
                    <td class="action-group">
                        ${action.nextStatus ? `<button class="action-btn ${action.btnClass} btn-acao" data-colab="${item.colaborador_id}" data-next="${action.nextStatus}">${action.label}</button>` : `<span class="badge status-pago">Pago</span>`}
                        <button class="action-btn small btn-detalhes" data-colab="${item.colaborador_id}">Detalhes</button>
                    </td>
                `;
        tbody.appendChild(tr);
      });

      // Wire actions: primary next-action buttons and detalhes
      tbody.querySelectorAll(".btn-acao").forEach((btn) => {
        btn.addEventListener("click", async () => {
          const colab = parseInt(btn.dataset.colab, 10);
          const next = btn.dataset.next;
          let confirmMsg = `Executar ação "${btn.textContent.trim()}" para este colaborador?`;
          if (next === "pago")
            confirmMsg =
              "Confirmar pagamento para todas as tarefas do mês selecionado deste colaborador?";
          const { isConfirmed: okConfirm } = await Swal.fire({
            title: btn.textContent.trim(),
            text: confirmMsg,
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "Confirmar",
            cancelButtonText: "Cancelar",
            confirmButtonColor: "#4f80e1",
          });
          if (!okConfirm) return;
          await atualizarStatusResumo(colab, next);
        });
      });
      tbody.querySelectorAll(".btn-detalhes").forEach((btn) => {
        btn.addEventListener("click", () =>
          abrirDetalhesColaborador(parseInt(btn.dataset.colab, 10)),
        );
      });
    } catch (e) {
      console.error("Erro ao carregar resumo", e);
      if (tbody)
        tbody.innerHTML = '<tr><td colspan="7">Erro ao carregar</td></tr>';
    }
  }

  async function atualizarStatusResumo(colaboradorId, status) {
    const mes = parseInt(mesResumo.value, 10);
    const ano = parseInt(anoResumo.value, 10);
    try {
      const res = await fetch("updateStatusPagamento.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          colaborador_id: colaboradorId,
          mes,
          ano,
          status,
        }),
      });
      const json = await res.json();
      if (!json.success) {
        await Swal.fire({
          icon: "error",
          title: "Erro",
          text:
            "Falha ao atualizar status: " + (json.error || "erro desconhecido"),
          timer: 3000,
          timerProgressBar: true,
        });
      } else {
        carregarResumo();
      }
    } catch (e) {
      console.error("Erro ao atualizar status", e);
      await Swal.fire({
        icon: "error",
        title: "Erro",
        text: "Erro ao atualizar status",
        timer: 3000,
        timerProgressBar: true,
      });
    }
  }

  function abrirDetalhesColaborador(colaboradorId) {
    const selColab = document.getElementById("colaborador");
    const selMes = document.getElementById("mes");
    const selAno = document.getElementById("ano");
    if (selColab && selMes && selAno) {
      selColab.value = String(colaboradorId);
      selMes.value = mesResumo.value;
      selAno.value = anoResumo.value;
      if (typeof window.carregarDadosColab === "function") {
        window.carregarDadosColab();
        const detalheSec = document.getElementById("table-list");
        if (detalheSec) detalheSec.scrollIntoView({ behavior: "smooth" });
      }
    }
  }

  if (btnResumo) {
    btnResumo.addEventListener("click", carregarResumo);
  }
  if (mesResumo && anoResumo) {
    mesResumo.addEventListener("change", carregarResumo);
    anoResumo.addEventListener("change", carregarResumo);
    setDefaultMesAnoResumo();
    carregarResumo();
  }
  document
    .getElementById("colaborador")
    .addEventListener("change", function () {
      carregarDadosColab();
    });
  document.getElementById("mes").addEventListener("change", carregarDadosColab);
  document.getElementById("ano").addEventListener("change", carregarDadosColab);

  let requisicaoColaboradorAtual = 0;

  function carregarDadosColab() {
    // Ignora respostas de uma seleção anterior caso o usuário alterne
    // rapidamente entre colaboradores.
    const requisicaoAtual = ++requisicaoColaboradorAtual;
    var colaboradorId = document.getElementById("colaborador").value;
    var mesId = document.getElementById("mes").value;
    var anoId = document.getElementById("ano").value;
    const tipoFiltros = Array.from(
      document.querySelectorAll('.tipo-imagem input[type="checkbox"]'),
    );
    const filtrosDeTipoAtivos = new Set(
      tipoFiltros
        .filter((checkbox) => checkbox.checked)
        .map((checkbox) => checkbox.name),
    );

    const confirmarPagamentoButton = document.getElementById(
      "confirmar-pagamento",
    );
    if (confirmarPagamentoButton) confirmarPagamentoButton.disabled = true;

    if (colaboradorId) {
      var url =
        "getColaborador.php?colaborador_id=" +
        encodeURIComponent(colaboradorId);

      if (mesId) {
        url += "&mes_id=" + encodeURIComponent(mesId);
      }
      if (anoId) {
        url += "&ano=" + encodeURIComponent(anoId);
      }

      document.querySelector("#tabela-a-pagar tbody").innerHTML =
        '<tr><td colspan="7" class="col-center"><i class="fa-solid fa-spinner fa-spin"></i> Carregando tarefas...</td></tr>';
      document.querySelector("#tabela-pago tbody").innerHTML = "";

      fetch(url)
        .then((response) => response.json())
        .then((data) => {
          if (requisicaoAtual !== requisicaoColaboradorAtual) return;
          var infoColaborador = document.getElementById("info-colaborador");
          var colaborador = data.dadosColaborador;
          if (colaborador) {
            infoColaborador.innerHTML = `
                            <p id='nomeColaborador'>${colaborador.nome_usuario}</p>
                            <p id='nomeEmpresarial'>${colaborador.nome_empresarial}</p>
                            <p id='cnpjColaborador'>${colaborador.cnpj}</p>
                            <p id='enderecoColaborador'>${colaborador.rua}, ${colaborador.numero}, ${colaborador.bairro}</p>
                            <p id='estadoCivil'>${colaborador.estado_civil}</p>
                            <p id='cpfColaborador'>${colaborador.cpf}</p>
                            <p id='enderecoCNPJ'>${colaborador.rua_cnpj} , ${colaborador.numero_cnpj} , ${colaborador.bairro_cnpj}</p>
                            <p id='cep'>${colaborador.cep}</p>
                            <p id='cepCNPJ'>${colaborador.cep_cnpj}</p>
                        `;
          }

          // Atualiza as duas tabelas
          var tabelaAPagar = document.querySelector("#tabela-a-pagar tbody");
          var tabelaPago = document.querySelector("#tabela-pago tbody");
          tabelaAPagar.innerHTML = "";
          tabelaPago.innerHTML = "";
          let totalValor = 0;

          tipoFiltros.forEach((checkbox) => {
            checkbox.checked = filtrosDeTipoAtivos.has(checkbox.name);
          });

          data.funcoes.forEach(function (item) {
            var row = document.createElement("tr");
            row.setAttribute("data-id", item.identificador);

            var cellNomeImagem = document.createElement("td");
            var cellStatusFuncao = document.createElement("td");
            var cellFuncao = document.createElement("td");
            var cellValor = document.createElement("td");
            var cellCheckbox = document.createElement("td");
            var cellData = document.createElement("td");
            var checkbox = document.createElement("input");

            checkbox.type = "checkbox";
            checkbox.classList.add("pagamento-checkbox");
            checkbox.checked = item.pagamento === 1;
            checkbox.setAttribute("pagamento", item.pagamento);
            checkbox.setAttribute("data-id", item.identificador);
            checkbox.setAttribute("data-origem", item.origem);
            checkbox.setAttribute("funcao", item.funcao_id);
            // include function name so backend can detect 'Finalização parcial'
            checkbox.setAttribute("data-funcao-name", item.nome_funcao || "");
            // comissão do gestor (colab 8 sobre 23/40) e valor a usar no pagamento
            checkbox.setAttribute(
              "data-comissao-gestor",
              item.comissao_gestor ? "1" : "",
            );
            checkbox.setAttribute(
              "data-valor",
              item.valor_exibido != null ? String(item.valor_exibido) : "0",
            );
            // counts to allow 2nd confirmation (pago parcial -> pago completa)
            checkbox.setAttribute(
              "data-pago-parcial-count",
              item.pago_parcial_count != null
                ? String(item.pago_parcial_count)
                : "0",
            );
            checkbox.setAttribute(
              "data-pago-completa-count",
              item.pago_completa_count != null
                ? String(item.pago_completa_count)
                : "0",
            );

            // If this item already has a recorded full payment, lock it to prevent edits and re-registering.
            const pagoCompletaCount = item.pago_completa_count
              ? parseInt(item.pago_completa_count, 10)
              : 0;
            if (pagoCompletaCount > 0) {
              checkbox.disabled = true;
              checkbox.title =
                "Pagamento completo já registrado; não é possível alterar.";
            }

            checkbox.addEventListener("change", function () {
              if (checkbox.checked) {
                row.classList.add("row-selected");
                row.classList.remove("checked");
              } else {
                row.classList.remove("row-selected");
                row.classList.remove("checked");
              }
              // Atualiza contagens quando o usuário altera seleção
              contarLinhasTabela();
            });
            cellCheckbox.appendChild(checkbox);

            // Verificar a origem e preencher os dados de acordo
            if (item.origem === "funcao_imagem") {
              cellNomeImagem.textContent = item.imagem_nome;
              cellFuncao.textContent = item.nome_funcao;
              cellStatusFuncao.textContent = item.status;
              // Mostra valor_exibido (50% para Finalização Parcial/pago-parcial)
              const valorParaMostrar =
                item.valor_exibido != null ? item.valor_exibido : item.valor;
              cellValor.textContent = valorParaMostrar;
              cellData.textContent = item.data_pagamento
                ? item.data_pagamento
                : "";

              // Mostrar indicador: prioriza 'Pago Completa' sobre 'Pago Parcial'
              if (
                item.pago_completa_count &&
                parseInt(item.pago_completa_count, 10) > 0
              ) {
                const badge = document.createElement("span");
                badge.textContent = "Pago Completa";
                badge.style.background = "#ffdf99";
                badge.style.color = "#663c00";
                badge.style.padding = "2px 6px";
                badge.style.borderRadius = "12px";
                badge.style.fontSize = "11px";
                badge.style.marginLeft = "8px";
                badge.title =
                  "Este item já teve pagamento parcial e foi pago por completo";
                cellFuncao.appendChild(badge);
              } else if (
                item.pago_parcial_count &&
                parseInt(item.pago_parcial_count, 10) > 0
              ) {
                const badge = document.createElement("span");
                badge.textContent = "Pago Parcial";
                badge.style.background = "#ffdf99";
                badge.style.color = "#663c00";
                badge.style.padding = "2px 6px";
                badge.style.borderRadius = "12px";
                badge.style.fontSize = "11px";
                badge.style.marginLeft = "8px";
                badge.title =
                  "Este item já foi pago anteriormente como Finalização Parcial";
                cellFuncao.appendChild(badge);
              }

              totalValor += parseFloat(valorParaMostrar) || 0;
            } else if (item.origem === "acompanhamento") {
              cellNomeImagem.textContent = item.imagem_nome;
              cellFuncao.textContent = "Acompanhamento";
              cellStatusFuncao.textContent = "Finalizado";
              cellValor.textContent = item.valor;
              cellData.textContent = item.data_pagamento
                ? item.data_pagamento
                : "";

              totalValor += parseFloat(item.valor) || 0;
            } else if (item.origem === "funcao_animacao") {
              cellNomeImagem.textContent = item.imagem_nome;
              cellFuncao.textContent = item.nome_funcao || "Animação";
              cellStatusFuncao.textContent = item.status;
              cellValor.textContent = item.valor;
              cellData.textContent = item.data_pagamento
                ? item.data_pagamento
                : "";
            }

            row.appendChild(cellNomeImagem);
            row.appendChild(cellStatusFuncao);
            row.appendChild(cellFuncao);
            row.appendChild(cellValor);
            row.appendChild(cellCheckbox);
            row.appendChild(cellData);

            // Roteia para a tabela correta de acordo com status de pagamento
            const normIt = (s) =>
              (s || "")
                .toLowerCase()
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .trim();
            const fnNormIt = normIt(item.nome_funcao || "");
            const pagoParcIt = parseInt(item.pago_parcial_count || 0, 10);
            const pagoCompIt = parseInt(item.pago_completa_count || 0, 10);
            const isFinalizacaoCompleta =
              fnNormIt.includes("finalizacao") && fnNormIt.includes("completa");
            const jaFoiPago =
              item.pagamento == 1 &&
              !(isFinalizacaoCompleta && pagoParcIt > 0 && pagoCompIt === 0);

            // Coluna de Ações — botão de pagamento individual (só para linhas não pagas)
            var cellAcoes = document.createElement("td");
            if (!jaFoiPago) {
              const btnPagar = document.createElement("button");
              btnPagar.textContent = "Pagar";
              btnPagar.className = "btn-pagar-linha";
              btnPagar.addEventListener("click", async function () {
                if (btnPagar.disabled) return;
                const colaboradorId = parseInt(
                  document.getElementById("colaborador").value,
                  10,
                );
                const mes = document.getElementById("mes").value;
                const ano = document.getElementById("ano").value;
                const ids = [
                  {
                    id: parseInt(checkbox.getAttribute("data-id"), 10),
                    origem: checkbox.getAttribute("data-origem"),
                    funcao_id: parseInt(checkbox.getAttribute("funcao"), 10),
                    funcao_name:
                      checkbox.getAttribute("data-funcao-name") || "",
                    comissao_gestor:
                      checkbox.getAttribute("data-comissao-gestor") === "1",
                    valor: parseFloat(
                      checkbox.getAttribute("data-valor") || "0",
                    ),
                  },
                ];
                const confirm = await Swal.fire({
                  title: "Confirmar pagamento",
                  text: "Registrar o pagamento deste item?",
                  icon: "question",
                  showCancelButton: true,
                  confirmButtonText: "Confirmar",
                  cancelButtonText: "Cancelar",
                  confirmButtonColor: "#4f80e1",
                });
                if (!confirm.isConfirmed) return;

                setPagamentoButtonLoading(btnPagar, true, "Pagando...");
                try {
                  const response = await fetch("updatePagamento.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                      ids,
                      colaborador_id: colaboradorId,
                      mes,
                      ano,
                    }),
                  });
                  const resp = await response.json();
                  if (!response.ok || !resp.success) {
                    throw new Error(
                      resp.error || "Erro ao registrar pagamento.",
                    );
                  }

                  const historicoResponse = await fetch("insertHistorico.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                      ids,
                      colaborador_id: colaboradorId,
                      mes,
                      ano,
                    }),
                  });
                  const historico = await historicoResponse.json();
                  await Swal.fire({
                    icon: historico.success ? "success" : "warning",
                    title: historico.success
                      ? "Pagamento registrado"
                      : "Pagamento registrado com alerta",
                    text: historico.success
                      ? "O item foi registrado com sucesso."
                      : "O pagamento foi registrado, mas o histórico legado não pôde ser atualizado.",
                    timer: 3200,
                    timerProgressBar: true,
                  });
                  carregarDadosColab();
                } catch (error) {
                  console.error("Erro ao pagar linha:", error);
                  await Swal.fire({
                    icon: "error",
                    title: "Não foi possível registrar",
                    text:
                      error.message || "Verifique a conexão e tente novamente.",
                    timer: 3500,
                    timerProgressBar: true,
                  });
                } finally {
                  setPagamentoButtonLoading(btnPagar, false);
                }
              });
              cellAcoes.appendChild(btnPagar);
            }
            row.appendChild(cellAcoes);
            (jaFoiPago ? tabelaPago : tabelaAPagar).appendChild(row);

            if (checkbox.checked) {
              row.classList.add("checked");
            }

          });

          contarLinhasTabela();
          // Reaplica somente filtros escolhidos pelo usuário. Antes, as
          // funções eram marcadas automaticamente e acabavam ocultando as
          // linhas de outro colaborador após a troca.
          window.pagamentoAplicarFiltrosVisuais?.();
          if (confirmarPagamentoButton)
            confirmarPagamentoButton.disabled = false;

          // ─── Adendo status widget ────────────────────────────────────────
          const _colabId = parseInt(
            document.getElementById("colaborador").value,
            10,
          );
          const _mesId = parseInt(document.getElementById("mes").value, 10);
          const _anoId = parseInt(document.getElementById("ano").value, 10);
          if (_colabId && _mesId && _anoId) {
            atualizarAdendoStatus(_colabId, _mesId, _anoId);
          }

          // ─── Painel de divergências de valor ────────────────────────────
          const divergentes = (data.funcoes || []).filter(
            (f) => f.tem_divergencia && f.origem === "funcao_imagem",
          );

          let painelDiv = document.getElementById("painel-divergencias");
          if (!painelDiv) {
            painelDiv = document.createElement("div");
            painelDiv.id = "painel-divergencias";
            const tabelaFat = document.getElementById("tabela-a-pagar");
            if (tabelaFat && tabelaFat.parentNode) {
              tabelaFat.parentNode.insertBefore(painelDiv, tabelaFat);
            }
          }

          if (divergentes.length === 0) {
            painelDiv.innerHTML = "";
            painelDiv.style.display = "none";
          } else {
            const linhas = divergentes
              .map(
                (f) => `
              <tr>
                <td>${f.imagem_nome || ""}</td>
                <td>${f.nome_funcao || ""}</td>
                <td class="valor-atual">${currencyBRL(f.valor)}</td>
                <td class="valor-esperado">${currencyBRL(f.valor_esperado)}</td>
                <td><button class="btn-row validate btn-aprovar-valor" data-id="${f.identificador}" title="Aprovar valor atual e ignorar divergência"><i class="fa-solid fa-check"></i> Aprovar</button></td>
              </tr>`,
              )
              .join("");

            painelDiv.style.display = "";
            painelDiv.innerHTML = `
              <div class="divergencia-box">
                <p class="divergencia-titulo">
                  <i class="fa-solid fa-triangle-exclamation"></i>
                  ${divergentes.length} tarefa(s) com valor diferente do esperado
                </p>
                <table class="divergencia-table">
                  <thead>
                    <tr>
                      <th>Imagem</th>
                      <th>Função</th>
                      <th>Valor atual</th>
                      <th>Valor esperado</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>${linhas}</tbody>
                </table>
                <button id="btn-corrigir-valores" class="btn btn-warning">
                  <i class="fa-solid fa-wand-magic-sparkles"></i> Corrigir valores automaticamente
                </button>
              </div>`;

            painelDiv.querySelectorAll(".btn-aprovar-valor").forEach((btn) => {
              btn.addEventListener("click", async () => {
                const id = parseInt(btn.dataset.id, 10);
                btn.disabled = true;
                try {
                  const res = await fetch("aprovarValor.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ id }),
                  });
                  const json = await res.json();
                  if (json.success) {
                    Toastify({
                      text: "Valor aprovado. Divergência ignorada.",
                      duration: 3000,
                      gravity: "top",
                      position: "right",
                      style: {
                        background: "#10b981",
                        borderRadius: "8px",
                        fontFamily: '"Inter", sans-serif',
                        fontSize: "13px",
                      },
                    }).showToast();
                    carregarDadosColab();
                  } else {
                    Toastify({
                      text: "Erro: " + (json.message || "desconhecido"),
                      duration: 4000,
                      gravity: "top",
                      position: "right",
                      style: {
                        background: "#ef4444",
                        borderRadius: "8px",
                        fontFamily: '"Inter", sans-serif',
                        fontSize: "13px",
                      },
                    }).showToast();
                    btn.disabled = false;
                  }
                } catch (e) {
                  Toastify({
                    text: "Erro ao aprovar valor.",
                    duration: 4000,
                    gravity: "top",
                    position: "right",
                    style: {
                      background: "#ef4444",
                      borderRadius: "8px",
                      fontFamily: '"Inter", sans-serif',
                      fontSize: "13px",
                    },
                  }).showToast();
                  btn.disabled = false;
                }
              });
            });

            document
              .getElementById("btn-corrigir-valores")
              .addEventListener("click", async () => {
                const itens = divergentes.map((f) => ({
                  id: f.identificador,
                  valor_novo: f.valor_esperado,
                }));
                if (
                  !(
                    await Swal.fire({
                      title: "Confirmar",
                      text: `Atualizar ${itens.length} tarefa(s) para os valores esperados?`,
                      icon: "question",
                      showCancelButton: true,
                      confirmButtonText: "Atualizar",
                      cancelButtonText: "Cancelar",
                      confirmButtonColor: "#4f80e1",
                    })
                  ).isConfirmed
                )
                  return;
                try {
                  const res = await fetch("corrigirValores.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ itens }),
                  });
                  const json = await res.json();
                  if (json.success) {
                    Toastify({
                      text: `${json.atualizados} tarefa(s) corrigida(s) com sucesso!`,
                      duration: 3500,
                      gravity: "top",
                      position: "right",
                      style: {
                        background: "#10b981",
                        borderRadius: "8px",
                        fontFamily: '"Inter", sans-serif',
                        fontSize: "13px",
                      },
                    }).showToast();
                    carregarDadosColab();
                  } else {
                    Toastify({
                      text: "Erro: " + (json.error || "desconhecido"),
                      duration: 4000,
                      gravity: "top",
                      position: "right",
                      style: {
                        background: "#ef4444",
                        borderRadius: "8px",
                        fontFamily: '"Inter", sans-serif',
                        fontSize: "13px",
                      },
                    }).showToast();
                  }
                } catch (e) {
                  Toastify({
                    text: "Erro ao corrigir valores.",
                    duration: 4000,
                    gravity: "top",
                    position: "right",
                    style: {
                      background: "#ef4444",
                      borderRadius: "8px",
                      fontFamily: '"Inter", sans-serif',
                      fontSize: "13px",
                    },
                  }).showToast();
                }
              });
          }
          // ────────────────────────────────────────────────────────────────
        })
        .catch((error) => {
          if (requisicaoAtual !== requisicaoColaboradorAtual) return;
          console.error("Erro ao carregar dados do colaborador:", error);
          document.querySelector("#tabela-a-pagar tbody").innerHTML =
            '<tr><td colspan="7" class="col-center">Não foi possível carregar as tarefas.</td></tr>';
          if (confirmarPagamentoButton)
            confirmarPagamentoButton.disabled = true;
        });
    } else {
      document.querySelector("#tabela-a-pagar tbody").innerHTML = "";
      document.querySelector("#tabela-pago tbody").innerHTML = "";
      var totalValorLabel = document.getElementById("totalValor");
      totalValorLabel.textContent = "Total: R$ 0,00";
      // Hide adendo widget when no collaborator selected
      const _widget = document.getElementById("adendo-status-widget");
      if (_widget) _widget.style.display = "none";
      if (confirmarPagamentoButton) confirmarPagamentoButton.disabled = true;
      atualizarResumoSelecao();
    }
  }

  // Expor para o dashboard
  window.carregarDadosColab = carregarDadosColab;

  document
    .getElementById("marcar-todos")
    .addEventListener("click", function () {
      pagamentoVisibleCheckboxes().forEach((checkbox) => {
        checkbox.checked = true;
        checkbox.closest("tr")?.classList.add("row-selected");
      });
      contarLinhasTabela();
    });

  document
    .getElementById("desmarcar-todos")
    .addEventListener("click", function () {
      pagamentoVisibleCheckboxes().forEach((checkbox) => {
        checkbox.checked = false;
        checkbox.closest("tr")?.classList.remove("row-selected");
      });
      contarLinhasTabela();
    });

  document
    .getElementById("confirmar-pagamento")
    .addEventListener("click", async function () {
      const button = this;
      if (button.disabled) return;
      var colaboradorId = parseInt(
        document.getElementById("colaborador").value,
        10,
      );
      // Processa somente os itens selecionados e visíveis em "A Pagar".
      var checkboxes = Array.from(
        document.querySelectorAll(
          "#tabela-a-pagar .pagamento-checkbox:checked",
        ),
      ).filter((cb) => !cb.disabled && cb.closest("tr")?.offsetParent !== null);
      var ids = checkboxes.map((cb) => ({
        id: parseInt(cb.getAttribute("data-id"), 10),
        origem: cb.getAttribute("data-origem"),
        funcao_id: parseInt(cb.getAttribute("funcao"), 10),
        funcao_name: cb.getAttribute("data-funcao-name") || "",
        comissao_gestor: cb.getAttribute("data-comissao-gestor") === "1",
        valor: parseFloat(cb.getAttribute("data-valor") || "0"),
      }));

      if (ids.length > 0) {
        const { isConfirmed } = await Swal.fire({
          title: "Confirmar pagamento",
          text: `Confirmar ${ids.length} ${ids.length === 1 ? "item" : "itens"} selecionado(s) deste colaborador?`,
          icon: "question",
          showCancelButton: true,
          confirmButtonText: "Confirmar",
          cancelButtonText: "Cancelar",
          confirmButtonColor: "#4f80e1",
        });
        if (!isConfirmed) return;
        setPagamentoButtonLoading(button, true, "Confirmando...");
        // include selected month/year so backend can group itens into pagamentos (mes_ref)
        const mes = document.getElementById("mes").value;
        const ano = document.getElementById("ano").value;
        try {
          const response = await fetch("updatePagamento.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              ids,
              colaborador_id: colaboradorId,
              mes,
              ano,
            }),
          });
          const data = await response.json();
          if (!response.ok || !data.success) {
            throw new Error(
              data.error || "Não foi possível registrar os pagamentos.",
            );
          }

          const historicoResponse = await fetch("insertHistorico.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              ids,
              colaborador_id: colaboradorId,
              mes,
              ano,
            }),
          });
          const historico = await historicoResponse.json();

          await Swal.fire({
            icon: historico.success ? "success" : "warning",
            title: historico.success
              ? "Pagamento registrado"
              : "Pagamento registrado com alerta",
            text: historico.success
              ? `${ids.length} ${ids.length === 1 ? "item foi registrado" : "itens foram registrados"}.`
              : "O pagamento foi registrado, mas o histórico legado não pôde ser atualizado.",
            timer: 3200,
            timerProgressBar: true,
          });
          carregarDadosColab();
        } catch (error) {
          console.error("Erro ao confirmar pagamentos:", error);
          await Swal.fire({
            icon: "error",
            title: "Não foi possível registrar",
            text: error.message || "Verifique a conexão e tente novamente.",
            timer: 3500,
            timerProgressBar: true,
          });
        } finally {
          setPagamentoButtonLoading(button, false);
        }
      } else {
        Swal.fire({
          icon: "warning",
          title: "Atenção",
          text: "Não há tarefas pendentes para confirmar.",
          timer: 3000,
          timerProgressBar: true,
        });
      }
    });

  // ── "Ver status geral" button ────────────────────────────────────────────
  const btnStatusGeral = document.getElementById("btn-ver-status-geral");
  if (btnStatusGeral) {
    btnStatusGeral.addEventListener("click", abrirModalStatusGeral);
  }

  // ── Modal status geral — close button ───────────────────────────────────
  const btnFecharStatusGeral = document.getElementById(
    "btn-fechar-status-geral",
  );
  if (btnFecharStatusGeral) {
    btnFecharStatusGeral.addEventListener("click", fecharModalStatusGeral);
  }
  const modalStatusGeral = document.getElementById("modalStatusGeral");
  if (modalStatusGeral) {
    modalStatusGeral.addEventListener("click", function (e) {
      if (e.target === modalStatusGeral) fecharModalStatusGeral();
    });
  }

  // ── Info button (adendo popover) ─────────────────────────────────────────
  const btnAdendoInfo = document.getElementById("btn-adendo-info");
  if (btnAdendoInfo) {
    btnAdendoInfo.addEventListener("click", function (e) {
      e.stopPropagation();
      toggleAdendoPopover(btnAdendoInfo);
    });
  }

  // Close popover on outside click
  document.addEventListener("click", function (e) {
    const popover = document.getElementById("popoverAdendoInfo");
    if (popover && popover.style.display !== "none") {
      if (!popover.contains(e.target)) {
        popover.style.display = "none";
      }
    }
  });

  // Popover close button
  const btnFecharPopover = document.getElementById("btn-fechar-popover");
  if (btnFecharPopover) {
    btnFecharPopover.addEventListener("click", function () {
      const popover = document.getElementById("popoverAdendoInfo");
      if (popover) popover.style.display = "none";
    });
  }

  document
    .getElementById("adicionar-valor")
    .addEventListener("click", async function () {
      const button = this;
      if (button.disabled) return;
      // Apenas checkboxes visíveis e marcadas
      var checkboxes = Array.from(
        document.querySelectorAll(".pagamento-checkbox:checked"),
      ).filter((cb) => cb.closest("tr").offsetParent !== null);
      var ids = checkboxes.map((cb) => ({
        id: cb.getAttribute("data-id"),
        origem: cb.getAttribute("data-origem"),
        funcao_id: cb.getAttribute("funcao"),
      }));

      // Aceita o formato usado no campo brasileiro (ex.: 1.234,56) e envia
      // um número normalizado para o endpoint.
      var valorRaw = document.getElementById("valor").value.trim();
      valorRaw = valorRaw.replace(/R\$\s?/gi, "").trim();
      var valor = valorRaw.includes(",")
        ? valorRaw.replace(/\./g, "").replace(",", ".")
        : valorRaw;

      if (ids.length > 0 && valor && Number.isFinite(Number(valor))) {
        setPagamentoButtonLoading(button, true, "Atualizando...");
        try {
          const response = await fetch("updateValor.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ ids, valor }),
          });
          const data = await response.json();
          if (!response.ok || !data.success) {
            throw new Error(data.error || "Erro ao atualizar valores.");
          }
          await Swal.fire({
            icon: "success",
            title: "Valores atualizados",
            text: "Os valores selecionados foram atualizados.",
            timer: 2800,
            timerProgressBar: true,
          });
          document.getElementById("valor").value = "";
          carregarDadosColab();
        } catch (error) {
          console.error("Erro ao adicionar valores:", error);
          await Swal.fire({
            icon: "error",
            title: "Não foi possível atualizar",
            text: error.message || "Verifique a conexão e tente novamente.",
            timer: 3500,
            timerProgressBar: true,
          });
        } finally {
          setPagamentoButtonLoading(button, false);
        }
      } else {
        Swal.fire({
          icon: "warning",
          title: "Atenção",
          text: "Selecione pelo menos uma imagem e insira um valor.",
          timer: 3000,
          timerProgressBar: true,
        });
      }
    });
});

function contarLinhasTabela() {
  const linhas = Array.from(
    document.querySelectorAll(
      "#tabela-a-pagar tbody tr, #tabela-pago tbody tr",
    ),
  );
  let totalImagens = 0;
  let totalValor = 0;
  // Conta imagens visíveis e soma valores
  for (let i = 0; i < linhas.length; i++) {
    const linha = linhas[i];
    if (linha.style.display !== "none") {
      totalImagens++;
      const valorCell = linha.getElementsByTagName("td")[3]; // Supondo que o valor está na quarta coluna (índice 3)
      const raw = valorCell ? valorCell.textContent : "";
      // Normaliza o valor: remove tudo que não seja número, ponto ou vírgula, transforma milhares
      const numero = parseFloat(
        raw
          .replace(/[^0-9,.-]+/g, "")
          .replace(/\./g, "")
          .replace(",", "."),
      );
      totalValor += !isNaN(numero) ? numero : 0; // Soma o valor se for um número
    }
  }

  // Atualiza totais gerais
  const elTotalImagens = document.getElementById("total-imagens");
  const elTotalValor = document.getElementById("totalValor");
  if (elTotalImagens) elTotalImagens.innerText = totalImagens;
  if (elTotalValor)
    elTotalValor.innerText = totalValor.toFixed(2).replace(".", ","); // Atualiza o total

  // --- Totais pagos / não pagos ---
  let totalPagas = 0;
  let totalNaoPagas = 0;
  let valorPagas = 0;
  let valorNaoPagas = 0;

  for (let i = 0; i < linhas.length; i++) {
    const linha = linhas[i];
    if (linha.style.display === "none") continue;
    const checkbox = linha.querySelector(".pagamento-checkbox");
    const valorCell = linha.getElementsByTagName("td")[3];
    const raw = valorCell ? valorCell.textContent : "";
    const numero = parseFloat(
      raw
        .replace(/[^0-9,.-]+/g, "")
        .replace(/\./g, "")
        .replace(",", "."),
    );
    const pagoAttr = checkbox && checkbox.getAttribute("pagamento") === "1";
    // Ajuste: se for 'Finalização Completa' com pagamento parcial (pago_parcial_count>0)
    // e ainda não tiver pago_completa_count, deve ser considerado NÃO PAGO.
    let isPago = !!pagoAttr;
    if (checkbox && pagoAttr) {
      const rawFunc = (
        checkbox.getAttribute("data-funcao-name") || ""
      ).toString();
      const pagoParcialCount =
        parseInt(checkbox.getAttribute("data-pago-parcial-count") || "0", 10) ||
        0;
      const pagoCompletaCount =
        parseInt(
          checkbox.getAttribute("data-pago-completa-count") || "0",
          10,
        ) || 0;
      const fnorm = rawFunc
        .toLowerCase()
        .normalize("NFD")
        .replace(/\p{Diacritic}/gu, "")
        .trim();
      if (
        fnorm.includes("finalizacao") &&
        fnorm.includes("completa") &&
        pagoParcialCount > 0 &&
        pagoCompletaCount === 0
      ) {
        // ainda não foi pago por completo — não conta como pago
        isPago = false;
      }
    }

    if (isPago) {
      totalPagas++;
      valorPagas += !isNaN(numero) ? numero : 0;
    } else {
      totalNaoPagas++;
      valorNaoPagas += !isNaN(numero) ? numero : 0;
    }
  }

  const elTotalPagas = document.getElementById("total-imagens-pagas");
  const elTotalNaoPagas = document.getElementById("total-imagens-nao-pagas");
  const elValorPagas = document.getElementById("totalValorPago");
  const elValorNaoPagas = document.getElementById("totalValorNaoPago");
  if (elTotalPagas) elTotalPagas.innerText = totalPagas;
  if (elTotalNaoPagas) elTotalNaoPagas.innerText = totalNaoPagas;
  if (elValorPagas)
    elValorPagas.innerText = valorPagas.toFixed(2).replace(".", ",");
  if (elValorNaoPagas)
    elValorNaoPagas.innerText = valorNaoPagas.toFixed(2).replace(".", ",");

  // --- Contagem por função (atualiza cada label dentro de .tipo-imagem) ---
  // A marcação usa um único container .tipo-imagem com vários <label class="checkbox-label">;
  // vamos contar as funções nas linhas visíveis e atualizar cada label individualmente.
  const mapaContagem = {};
  for (let i = 0; i < linhas.length; i++) {
    const linha = linhas[i];
    if (linha.style.display === "none") continue; // apenas linhas visíveis
    const funcaoCell = linha.cells[2];
    let funcaoText = funcaoCell
      ? (funcaoCell.textContent || funcaoCell.innerText).trim()
      : "";
    if (!funcaoText) continue;
    funcaoText = funcaoText
      .replace(/Pago\s*Parcial/gi, "")
      .replace(/Pago\s*Completa/gi, "")
      .replace(/\s*-\s*.*/g, "")
      .trim();
    if (!funcaoText) continue;
    mapaContagem[funcaoText] = (mapaContagem[funcaoText] || 0) + 1;
  }

  // Seleciona cada label dentro o container e atualiza seu contador
  const labels = document.querySelectorAll(".tipo-imagem .checkbox-label");
  labels.forEach((label) => {
    const input = label.querySelector('input[type="checkbox"]');
    let nomeFuncao = "";
    if (input && input.name) {
      nomeFuncao = input.name.trim();
    } else {
      // fallback: texto do próprio label (ex.: <span>...)</
      const span = label.querySelector("span");
      nomeFuncao = span
        ? span.textContent.trim()
        : (label.textContent || "").trim();
    }

    // "Planta Humanizada" agrega todas as linhas cujo nome de função contém " ph "
    let count;
    if (nomeFuncao === "Planta Humanizada") {
      count = Object.entries(mapaContagem)
        .filter(([k]) => k.toLowerCase().includes(" ph "))
        .reduce((sum, [, v]) => sum + v, 0);
    } else {
      count = mapaContagem[nomeFuncao] || 0;
    }

    // Atualiza ou cria o span .tipo-count dentro do label
    let spanCount = label.querySelector(".tipo-count");
    // Mostrar o contador apenas quando for maior que 0
    if (count > 0) {
      if (!spanCount) {
        spanCount = document.createElement("span");
        spanCount.className = "tipo-count";
        spanCount.style.marginLeft = "6px";
        spanCount.style.color = "#666";
        label.appendChild(spanCount);
      }
      spanCount.textContent = `(${count})`;
      spanCount.style.display = "";
    } else {
      // Se existir e o count for zero, remove o elemento para não mostrar
      if (spanCount) spanCount.remove();
    }
  });

  atualizarResumoSelecao();
}

function filtrarTabela() {
  // Obter todas as checkboxes marcadas
  const checkboxes = document.querySelectorAll(
    '.tipo-imagem input[type="checkbox"]:checked',
  );
  const normalize = (s) =>
    (s || "")
      .toString()
      .replace(/\s*-\s*.*/g, "")
      .trim()
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "");
  const funcoesSelecionadas = Array.from(checkboxes).map((checkbox) =>
    normalize(checkbox.name),
  );

  ["#tabela-a-pagar tbody", "#tabela-pago tbody"].forEach((sel) => {
    const tbody = document.querySelector(sel);
    if (!tbody) return;
    const linhas = tbody.getElementsByTagName("tr");
    for (let i = 0; i < linhas.length; i++) {
      const linha = linhas[i];
      const funcaoCell = linha.cells[2];
      if (funcaoCell) {
        let funcaoText = (
          funcaoCell.textContent ||
          funcaoCell.innerText ||
          ""
        ).toString();
        // remove rótulos de badge que são anexados ao texto da função
        funcaoText = funcaoText
          .replace(/Pago\s*Parcial/gi, "")
          .replace(/Pago\s*Completa/gi, "")
          .replace(/\s*-\s*.*/g, "")
          .trim();
        const funcaoNorm = normalize(funcaoText);
        // "Planta Humanizada" também cobre funções com " ph " no nome (ex: Finalização PH Completa)
        const matchesPH =
          funcoesSelecionadas.includes("planta humanizada") &&
          funcaoNorm.includes(" ph ");
        if (
          funcoesSelecionadas.length === 0 ||
          funcoesSelecionadas.includes(funcaoNorm) ||
          matchesPH
        ) {
          linha.style.display = "";
        } else {
          linha.style.display = "none";
        }
      }
    }
  });

  contarLinhasTabela();
}

// Função para converter números para texto
document
  .getElementById("generate-adendo")
  .addEventListener("click", async function () {
    const colaboradorId = (() => {
      const el = document.getElementById("colaborador");
      const v = el ? parseInt(el.value, 10) : NaN;
      return Number.isFinite(v) ? v : null;
    })();

    if (!colaboradorId) {
      await Swal.fire({
        icon: "warning",
        title: "Atenção",
        text: "Selecione um colaborador antes de gerar o adendo.",
        timer: 3000,
        timerProgressBar: true,
      });
      return;
    }

    const mesEl = document.getElementById("mes");
    const anoEl = document.getElementById("ano");
    const mes = mesEl ? parseInt(mesEl.value, 10) : NaN;
    const ano = anoEl ? parseInt(anoEl.value, 10) : NaN;

    if (!Number.isFinite(mes) || !Number.isFinite(ano)) {
      await Swal.fire({
        icon: "warning",
        title: "Atenção",
        text: "Selecione mês e ano antes de gerar o adendo.",
        timer: 3000,
        timerProgressBar: true,
      });
      return;
    }

    const { value: valorFixo, isConfirmed: vfConfirmed } = await Swal.fire({
      title: "Valor fixo",
      input: "text",
      inputLabel: "Digite o valor fixo (somente número)",
      inputPlaceholder: "Ex: 1500",
      showCancelButton: true,
      confirmButtonText: "Continuar",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#4f80e1",
      inputValidator: (value) => {
        if (!value || isNaN(value))
          return "Por favor, insira um valor numérico válido.";
      },
    });
    if (!vfConfirmed || !valorFixo) return;

    // Bônus/extras opcionais
    const extras = [];
    const { isConfirmed: querBonus } = await Swal.fire({
      title: "Bônus/Extra",
      text: "Deseja adicionar bônus/extra no adendo?",
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Sim",
      cancelButtonText: "Não",
      confirmButtonColor: "#4f80e1",
    });
    let addBonus = querBonus;
    while (addBonus) {
      const { value: categoria, isConfirmed: catConfirmed } = await Swal.fire({
        title: "Categoria",
        input: "text",
        inputLabel: "Categoria do bônus/extra",
        inputPlaceholder: "Ex: Premiação",
        showCancelButton: true,
        confirmButtonText: "Continuar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#4f80e1",
        inputValidator: (value) => {
          if (!value || !value.trim()) return "Categoria inválida.";
        },
      });
      if (catConfirmed && categoria && categoria.trim()) {
        const { value: valorExtraRaw, isConfirmed: veConfirmed } =
          await Swal.fire({
            title: "Valor do bônus/extra",
            input: "text",
            inputLabel: "Valor (somente número)",
            inputPlaceholder: "Ex: 200",
            showCancelButton: true,
            confirmButtonText: "Adicionar",
            cancelButtonText: "Cancelar",
            confirmButtonColor: "#4f80e1",
            inputValidator: (value) => {
              if (!value || isNaN(parseFloat(value.replace(",", "."))))
                return "Valor inválido.";
            },
          });
        if (veConfirmed && valorExtraRaw) {
          extras.push({
            categoria: categoria.trim(),
            valor: parseFloat(valorExtraRaw.replace(",", ".")),
          });
        }
      }
      const { isConfirmed: maisBonus } = await Swal.fire({
        title: "Mais bônus/extra?",
        text: "Adicionar outro bônus/extra?",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Sim",
        cancelButtonText: "Não",
        confirmButtonColor: "#4f80e1",
      });
      addBonus = maisBonus;
    }

    const btn = this;
    btn.disabled = true;
    try {
      const funcoesSelecionadas = Array.from(
        document.querySelectorAll(
          '.tipo-imagem input[type="checkbox"]:checked',
        ),
      )
        .map((cb) => cb.name)
        .filter(Boolean);
      const itens = Array.from(
        document.querySelectorAll(
          "#tabela-a-pagar tbody tr, #tabela-pago tbody tr",
        ),
      )
        .filter((tr) => tr.offsetParent !== null)
        .map((tr) => {
          const cells = tr.querySelectorAll("td");
          const imagem = cells[0]?.textContent?.trim() || "";
          const checkbox = tr.querySelector(".pagamento-checkbox");
          // Prefer the visible cell text (cleaned from badges) because data attributes may be stale
          let funcaoRaw = cells[2]?.textContent || "";
          // Remove badge texts like 'Pago Parcial' or 'Pago' that are appended visually
          funcaoRaw = funcaoRaw
            .replace(/Pago\s*Parcial/gi, "")
            .replace(/Pago\s*Completa/gi, "")
            .replace(/Pago/gi, "")
            .trim();
          const funcao = (
            funcaoRaw ||
            checkbox?.getAttribute("data-funcao-name") ||
            ""
          ).trim();
          const valorRaw = cells[3]?.textContent?.trim() || "0";
          const valor =
            parseFloat(
              valorRaw
                .replace(/[^0-9,.-]+/g, "")
                .replace(/\./g, "")
                .replace(",", "."),
            ) || 0;
          const dataPagamento = cells[5]?.textContent?.trim() || null;
          const pagoParcialCount = checkbox
            ? parseInt(
                checkbox.getAttribute("data-pago-parcial-count") || "0",
                10,
              )
            : 0;
          const pagoCompletaCount = checkbox
            ? parseInt(
                checkbox.getAttribute("data-pago-completa-count") || "0",
                10,
              )
            : 0;
          return {
            imagem_nome: imagem,
            nome_funcao: funcao,
            valor: valor,
            data_pagamento: dataPagamento,
            pago_parcial_count: pagoParcialCount,
            pago_completa_count: pagoCompletaCount,
          };
        });
      const res = await fetch("gerar_adendo.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          colaborador_id: colaboradorId,
          mes: mes,
          ano: ano,
          valor_fixo: valorFixo,
          funcoes: funcoesSelecionadas,
          extras: extras,
          itens: itens,
        }),
      });
      const data = await res.json();
      if (!res.ok || !data.success) {
        Toastify({
          text: data.message || "Erro ao gerar adendo.",
          duration: 4000,
          gravity: "top",
          position: "right",
          style: {
            background: "#ef4444",
            borderRadius: "8px",
            fontFamily: '"Inter", sans-serif',
            fontSize: "13px",
          },
        }).showToast();
        return;
      }
      if (data.preview_url && data.temp_rel) {
        abrirModalAdendo(data.preview_url, data.temp_rel);
      } else {
        Toastify({
          text: "Adendo gerado com sucesso.",
          duration: 3000,
          gravity: "top",
          position: "right",
          style: {
            background: "#10b981",
            borderRadius: "8px",
            fontFamily: '"Inter", sans-serif',
            fontSize: "13px",
          },
        }).showToast();
      }
    } catch (e) {
      console.error("Erro ao gerar adendo", e);
      Toastify({
        text: "Erro ao gerar adendo.",
        duration: 4000,
        gravity: "top",
        position: "right",
        style: {
          background: "#ef4444",
          borderRadius: "8px",
          fontFamily: '"Inter", sans-serif',
          fontSize: "13px",
        },
      }).showToast();
    } finally {
      btn.disabled = false;
    }
  });

function abrirModalAdendo(previewUrl, tempRel) {
  const modal = document.getElementById("modalAdendo");
  const frame = document.getElementById("adendo-preview-frame");
  if (!modal || !frame) return;

  frame.src = previewUrl;
  modal.classList.add("is-open");

  // Clone confirm button to remove any previously attached listeners
  const btnOld = document.getElementById("btn-confirmar-adendo");
  const btnNew = btnOld.cloneNode(true);
  btnOld.parentNode.replaceChild(btnNew, btnOld);

  btnNew.addEventListener("click", async function () {
    btnNew.disabled = true;
    try {
      const res = await fetch("confirmar_adendo.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ temp_rel: tempRel }),
      });
      const data = await res.json();
      if (!res.ok || !data.success) {
        Toastify({
          text: data.message || "Erro ao confirmar adendo.",
          duration: 4000,
          gravity: "top",
          position: "right",
          style: {
            background: "#ef4444",
            borderRadius: "8px",
            fontFamily: '"Inter", sans-serif',
            fontSize: "13px",
          },
        }).showToast();
        return;
      }
      fecharModalAdendo();
      Toastify({
        text: "Adendo salvo com sucesso!",
        duration: 3500,
        gravity: "top",
        position: "right",
        style: {
          background: "#10b981",
          borderRadius: "8px",
          fontFamily: '"Inter", sans-serif',
          fontSize: "13px",
        },
      }).showToast();
      if (data.download_url) {
        window.open(data.download_url, "_blank");
      }
    } catch (e) {
      console.error("Erro ao confirmar adendo", e);
      Toastify({
        text: "Erro ao confirmar adendo.",
        duration: 4000,
        gravity: "top",
        position: "right",
        style: {
          background: "#ef4444",
          borderRadius: "8px",
          fontFamily: '"Inter", sans-serif',
          fontSize: "13px",
        },
      }).showToast();
    } finally {
      btnNew.disabled = false;
    }
  });
}

function fecharModalAdendo() {
  const modal = document.getElementById("modalAdendo");
  const frame = document.getElementById("adendo-preview-frame");
  if (modal) modal.classList.remove("is-open");
  if (frame) frame.src = "";
}

document
  .getElementById("generate-lista")
  .addEventListener("click", function () {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({
      orientation: "landscape",
    });

    const colaborador =
      document.getElementById("colaborador").options[
        document.getElementById("colaborador").selectedIndex
      ].text;
    const mesNome =
      document.getElementById("mes").options[
        document.getElementById("mes").selectedIndex
      ].text;
    const ano = parseInt(document.getElementById("ano").value, 10);
    let currentY = 20;

    const title = `Relatório completo de ${colaborador}, ${mesNome} de ${ano}`;

    const imgPath = "../assets/logo.jpg";

    fetch(imgPath)
      .then((response) => response.blob())
      .then((blob) => {
        const reader = new FileReader();
        reader.onloadend = function () {
          const imgData = reader.result;
          doc.addImage(imgData, "PNG", 14, currentY, 40, 40);
          currentY += 50;

          doc.setFontSize(16);
          doc.setTextColor(0, 0, 0);
          doc.text(title, 14, currentY);
          currentY += 10;

          // ==== Agrupamento por função ====
          const allTableRows = [
            ...document.querySelectorAll("#tabela-a-pagar tbody tr"),
            ...document.querySelectorAll("#tabela-pago tbody tr"),
          ];
          const tableHeaderThs = document.querySelectorAll(
            "#tabela-a-pagar thead tr th",
          );
          const selectedColumnIndexes = [0, 1, 2]; // colunas que vão para o PDF
          const funcaoColumnIndex = 2; // Ajuste para o índice da coluna "função"
          const dataPagamentoColumnIndex = 5;

          const headers = [];
          const rows = [];
          const agrupamentoFuncoes = {};

          tableHeaderThs.forEach((header, index) => {
            if (selectedColumnIndexes.includes(index)) {
              headers.push(header.innerText);
            }
          });

          allTableRows.forEach((row) => {
            const cells = row.querySelectorAll("td");
            const cell = cells[dataPagamentoColumnIndex];

            // Protege contra cell undefined e normaliza espaços/nbsp
            const rawText =
              cell?.innerText?.replace(/\u00A0/g, " ").trim() ?? "";

            // Converte string vazia para null para sua lógica ficar consistente
            const dataPagamento = rawText === "" ? null : rawText;

            // Detecta se a célula de função contém a marca 'Pago Parcial' (case-insensitive)
            const funcaoTextoRaw = (
              cells[funcaoColumnIndex]?.innerText || ""
            ).trim();
            const funcaoTextoLower = funcaoTextoRaw.toLowerCase();
            const hasPagoParcial =
              funcaoTextoLower.indexOf("pago parcial") !== -1;

            // Observação: usar getComputedStyle caso a visibilidade seja controlada por classe/CSS
            const visible =
              row.style.display !== "none" &&
              getComputedStyle(row).display !== "none";

            // Incluir linhas com data '0000-00-00' OU que tenham a marca 'Pago Parcial'
            if (
              (dataPagamento === "0000-00-00" ||
                dataPagamento === null ||
                hasPagoParcial) &&
              visible
            ) {
              // === Conta por função (removendo o rótulo 'Pago Parcial' para agregação) ===
              const funcao =
                funcaoTextoRaw.replace(/Pago Parcial/gi, "").trim() ||
                "Sem função";
              agrupamentoFuncoes[funcao] =
                (agrupamentoFuncoes[funcao] || 0) + 1;

              // === Monta linhas para o PDF ===
              const rowData = [];
              cells.forEach((cell, index) => {
                if (selectedColumnIndexes.includes(index)) {
                  rowData.push(cell.innerText);
                }
              });
              rows.push(rowData);
            }
          });

          // ==== Adiciona resumo das funções no PDF ====
          doc.setFontSize(12);
          doc.text("Quantidade de tarefas por função:", 14, currentY + 10);

          let yResumo = currentY + 16;
          for (let funcao in agrupamentoFuncoes) {
            doc.text(`${funcao}: ${agrupamentoFuncoes[funcao]}`, 14, yResumo);
            yResumo += 6;
          }

          currentY = yResumo + 10; // avança Y para tabela

          if (rows.length > 0) {
            doc.autoTable({
              head: [headers],
              body: rows,
              startY: currentY,
            });

            doc.save(`Relatório_Completo_${colaborador}_${mesNome}_${ano}.pdf`);
          } else {
            Swal.fire({
              icon: "warning",
              title: "Atenção",
              text: "Nenhum dado disponível para gerar a lista.",
              timer: 3000,
              timerProgressBar: true,
            });
          }
        };
        reader.readAsDataURL(blob);
      })
      .catch((error) => console.error("Erro ao carregar a imagem:", error));
  });

// Função para converter números para texto
function numeroPorExtenso(num) {
  const unidades = [
    "",
    "um",
    "dois",
    "três",
    "quatro",
    "cinco",
    "seis",
    "sete",
    "oito",
    "nove",
    "dez",
    "onze",
    "doze",
    "treze",
    "quatorze",
    "quinze",
    "dezesseis",
    "dezessete",
    "dezoito",
    "dezenove",
  ];
  const dezenas = [
    "",
    "",
    "vinte",
    "trinta",
    "quarenta",
    "cinquenta",
    "sessenta",
    "setenta",
    "oitenta",
    "noventa",
  ];
  const centenas = [
    "",
    "cem",
    "duzentos",
    "trezentos",
    "quatrocentos",
    "quinhentos",
    "seiscentos",
    "setecentos",
    "oitocentos",
    "novecentos",
  ];

  if (num === 0) return "zero";

  let resultado = "";

  // Tratando milhares
  if (num >= 1000) {
    let milhar = Math.floor(num / 1000);
    resultado += milhar === 1 ? "mil " : `${unidades[milhar]} mil `;
    num %= 1000;
  }

  // Tratando centenas
  if (num >= 100) {
    let centena = Math.floor(num / 100);
    resultado += `${centenas[centena]} `;
    num %= 100;
  }

  // Tratando dezenas
  if (num >= 20) {
    let dezena = Math.floor(num / 10);
    resultado += `${dezenas[dezena]} `;
    num %= 10;
  }

  // Tratando unidades
  if (num > 0) {
    if (resultado.trim() !== "") {
      resultado += "e "; // Adiciona "e" se já houver dezenas ou centenas
    }
    resultado += `${unidades[num]} `;
  }

  return resultado.trim(); // Remove espaços em branco no início e no fim
}

function exportToExcel() {
  // Combina as duas tabelas em uma planilha
  const headerRow = document.querySelector("#tabela-a-pagar thead tr");
  const allRows = [
    ...document.querySelectorAll("#tabela-a-pagar tbody tr"),
    ...document.querySelectorAll("#tabela-pago tbody tr"),
  ];

  const wsData = [];
  if (headerRow) {
    wsData.push(Array.from(headerRow.cells).map((th) => th.textContent.trim()));
  }
  allRows.forEach((row) => {
    wsData.push(Array.from(row.cells).map((td) => td.textContent.trim()));
  });

  var ws = XLSX.utils.aoa_to_sheet(wsData);
  var wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Dados");

  // Pega as informações do colaborador, mês e ano
  const colaborador =
    document.getElementById("colaborador").options[
      document.getElementById("colaborador").selectedIndex
    ].text;
  const mes =
    document.getElementById("mes").options[
      document.getElementById("mes").selectedIndex
    ].text;
  const ano = parseInt(document.getElementById("ano").value, 10);

  // Define o nome do arquivo
  const nomeArquivo = `Relatório_${colaborador}_${mes}_${ano}.xlsx`;

  // Gera o arquivo Excel e faz o download com o nome personalizado
  XLSX.writeFile(wb, nomeArquivo);
}

// // ---- UI enhancement: convert status text into styled badges and keep them updated ----
// (function () {
//     const statusClassMap = {
//         'pendente_envio': 'status-pendente_envio',
//         'aguardando_retorno': 'status-aguardando_retorno',
//         'validado': 'status-validado',
//         'adendo_gerado': 'status-adendo_gerado',
//         'pago': 'status-pago'
//     };

//     function normalizeText(t) {
//         return (t || '').toString().trim().toLowerCase().replace(/\s+/g, '_');
//     }

//     function transformCell(td) {
//         if (!td) return;
//         const text = td.textContent.trim();
//         const key = normalizeText(text);
//         if (statusClassMap[key]) {
//             // avoid double-wrapping
//             if (td.querySelector('.status-badge')) return;
//             td.innerHTML = `<span class="status-badge ${statusClassMap[key]}">${text}</span>`;
//         }
//     }

//     function transformAll() {
//         // scan resumo and faturamento tables
//         const tds = Array.from(document.querySelectorAll('#tabela-resumo tbody td, #tabela-faturamento tbody td'));
//         tds.forEach(td => {
//             const txt = td.textContent.trim().toLowerCase();
//             // quick check: if cell text contains one of the known status words
//             if (txt.length === 0) return;
//             const normalized = normalizeText(txt);
//             if (Object.keys(statusClassMap).includes(normalized)) transformCell(td);
//         });
//     }

//     // Observe changes on the two tables and re-run transform when content changes
//     function observeTable(selector) {
//         const el = document.querySelector(selector);
//         if (!el) return;
//         const tbody = el.querySelector('tbody');
//         if (!tbody) return;
//         const mo = new MutationObserver(mutations => {
//             transformAll();
//         });
//         mo.observe(tbody, { childList: true, subtree: true, characterData: true });
//     }

//     document.addEventListener('DOMContentLoaded', function () {
//         // initial transform (in case server rendered statuses exist)
//         setTimeout(transformAll, 200);
//         observeTable('#tabela-resumo');
//         observeTable('#tabela-faturamento');
//     });

//     // also expose for manual invocation after dynamic updates
//     window._transformPagamentoStatusBadges = transformAll;
// })();

// ====================================================================
// ADENDO STATUS — Widget, Popover, Modal Status Geral
// ====================================================================

/**
 * Returns the CSS class + label for a given adendo status string.
 */
function adendoStatusInfo(status) {
  const map = {
    nao_gerado: {
      cls: "estado-nao-gerado",
      icon: "fa-circle-minus",
      label: "Não gerado",
    },
    gerado: { cls: "estado-gerado", icon: "fa-file", label: "Gerado" },
    enviado: {
      cls: "estado-enviado",
      icon: "fa-paper-plane",
      label: "Enviado",
    },
    visualizado: {
      cls: "estado-visualizado",
      icon: "fa-eye",
      label: "Visualizado",
    },
    assinado: {
      cls: "estado-assinado",
      icon: "fa-signature",
      label: "Assinado",
    },
    recusado: {
      cls: "estado-recusado",
      icon: "fa-circle-xmark",
      label: "Recusado",
    },
    expirado: { cls: "estado-expirado", icon: "fa-clock", label: "Expirado" },
  };
  return (
    map[status] || {
      cls: "estado-nao-enviado",
      icon: "fa-circle-minus",
      label: "Não enviado",
    }
  );
}

/**
 * Returns badge CSS class for table display inside status geral modal.
 */
function adendoBadgeClass(status) {
  const map = {
    nao_gerado: "b-nao-gerado",
    gerado: "b-gerado",
    enviado: "b-enviado",
    visualizado: "b-visualizado",
    assinado: "b-assinado",
    recusado: "b-recusado",
    expirado: "b-expirado",
  };
  return map[status] || "b-nao-enviado";
}

/**
 * Formats a datetime string in pt-BR locale.
 */
function fmtDatetime(str) {
  if (!str || str === "0000-00-00 00:00:00" || str === "0000-00-00") return "—";
  const d = new Date(str.replace(" ", "T"));
  if (isNaN(d.getTime())) return str;
  return (
    d.toLocaleDateString("pt-BR") +
    " " +
    d.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })
  );
}

// ── Cached adendo data for the current collaborator/month ─────────────────
let _currentAdendo = null;

/**
 * Fetches adendo status for the selected collaborator/month and updates widget.
 */
async function atualizarAdendoStatus(colaboradorId, mes, ano) {
  const widget = document.getElementById("adendo-status-widget");
  const badge = document.getElementById("adendo-status-badge");
  const dateEl = document.getElementById("adendo-status-date");
  if (!widget || !badge) return;

  try {
    const res = await fetch(
      `get_adendo_status.php?colaborador_id=${encodeURIComponent(colaboradorId)}&mes=${encodeURIComponent(mes)}&ano=${encodeURIComponent(ano)}`,
    );
    const json = await res.json();
    _currentAdendo = json.adendo || null;

    const adendo = json.adendo;
    const info = adendoStatusInfo(adendo ? adendo.status : null);

    // Update badge
    badge.className = `adendo-status-badge ${info.cls}`;
    badge.innerHTML = `<i class="fa-solid ${info.icon}"></i> ${info.label}`;

    // Update date
    if (adendo && adendo.updated_at) {
      dateEl.textContent = fmtDatetime(adendo.updated_at);
    } else {
      dateEl.textContent = "";
    }

    // Show/hide download & reenviar buttons in popover based on state
    const btnDownload = document.getElementById("btn-download-adendo");
    const btnReenviar = document.getElementById("btn-reenviar-adendo");
    if (btnDownload)
      btnDownload.style.display = adendo && adendo.arquivo_nome ? "" : "none";
    if (btnReenviar)
      btnReenviar.style.display =
        adendo && ["enviado", "visualizado", "gerado"].includes(adendo.status)
          ? ""
          : "none";

    widget.style.display = "";
  } catch (e) {
    console.error("Erro ao buscar status adendo:", e);
    widget.style.display = "none";
  }
}

// ── Popover ────────────────────────────────────────────────────────────────

/**
 * Toggles the adendo info popover, loading data if needed.
 */
async function toggleAdendoPopover(anchorEl) {
  const popover = document.getElementById("popoverAdendoInfo");
  if (!popover) return;

  if (popover.style.display !== "none") {
    popover.style.display = "none";
    return;
  }

  // Position popover
  positionPopover(popover, anchorEl);
  popover.style.display = "flex";

  // Load data
  const body = document.getElementById("adendo-popover-body");
  body.innerHTML = '<p class="adendo-popover-loading">Carregando...</p>';

  try {
    const colaboradorId = parseInt(
      document.getElementById("colaborador").value,
      10,
    );
    const mes = parseInt(document.getElementById("mes").value, 10);
    const ano = parseInt(document.getElementById("ano").value, 10);

    const res = await fetch(
      `get_adendo_status.php?colaborador_id=${encodeURIComponent(colaboradorId)}&mes=${encodeURIComponent(mes)}&ano=${encodeURIComponent(ano)}`,
    );
    const json = await res.json();
    const adendo = json.adendo;
    const log = json.log || [];

    body.innerHTML = buildPopoverBody(adendo, log);

    // Wire download button
    const btnDl = document.getElementById("btn-download-adendo");
    if (btnDl && adendo && adendo.arquivo_nome) {
      btnDl.style.display = "";
      btnDl.onclick = function () {
        window.open(
          "../Contratos/download.php?arquivo=" +
            encodeURIComponent(adendo.arquivo_path || adendo.arquivo_nome),
          "_blank",
        );
      };
    }

    // Wire reenviar button (just triggers adendo generation flow)
    const btnRe = document.getElementById("btn-reenviar-adendo");
    if (
      btnRe &&
      adendo &&
      ["enviado", "visualizado", "gerado"].includes(adendo.status)
    ) {
      btnRe.style.display = "";
      btnRe.onclick = function () {
        popover.style.display = "none";
        document.getElementById("generate-adendo").click();
      };
    }
  } catch (e) {
    console.error("Erro ao carregar detalhes do adendo:", e);
    body.innerHTML = '<p class="adendo-popover-loading">Erro ao carregar.</p>';
  }
}

/**
 * Builds the HTML content for the adendo info popover.
 */
function buildPopoverBody(adendo, log) {
  let html = "";

  // // Meta info
  // if (adendo) {
  //   html += `<div class="adendo-doc-meta">`;
  //   if (adendo.arquivo_nome) {
  //     html += `<div class="adendo-doc-meta-row"><span class="adendo-doc-meta-label">Documento</span><span class="adendo-doc-meta-value">${escHtml(adendo.arquivo_nome)}</span></div>`;
  //   }
  //   if (adendo.data_envio) {
  //     html += `<div class="adendo-doc-meta-row"><span class="adendo-doc-meta-label">Enviado em</span><span class="adendo-doc-meta-value">${fmtDatetime(adendo.data_envio)}</span></div>`;
  //   }
  //   if (adendo.assinado_em) {
  //     html += `<div class="adendo-doc-meta-row"><span class="adendo-doc-meta-label">Assinado em</span><span class="adendo-doc-meta-value">${fmtDatetime(adendo.assinado_em)}</span></div>`;
  //   }
  //   html += `</div>`;
  // }

  // Timeline
  html += `<div class="adendo-timeline">`;

  if (log.length === 0 && !adendo) {
    html += `<div class="adendo-timeline-empty">Nenhum adendo gerado para este mês.</div>`;
  } else if (log.length === 0 && adendo) {
    // Synthetic timeline from adendo fields
    const synth = [];
    if (adendo.created_at)
      synth.push({ status: "gerado", ocorrido_em: adendo.created_at });
    if (adendo.data_envio)
      synth.push({ status: "enviado", ocorrido_em: adendo.data_envio });
    if (adendo.assinado_em)
      synth.push({ status: "assinado", ocorrido_em: adendo.assinado_em });

    if (synth.length === 0) {
      html += `<div class="adendo-timeline-empty">Sem histórico disponível.</div>`;
    } else {
      html += synth.map((e) => buildTimelineItem(e)).join("");
    }
  } else {
    html += log.map((e) => buildTimelineItem(e)).join("");
  }

  html += `</div>`;
  return html;
}

function buildTimelineItem(e) {
  const info = adendoStatusInfo(e.status || e.acao);
  const title = e.acao
    ? info.label +
      ' <small style="color:var(--text-muted);font-weight:400;">via ' +
      escHtml(e.acao) +
      "</small>"
    : info.label;
  return `
    <div class="adendo-timeline-item tl-${escHtml(e.status || "")}">
      <div class="adendo-timeline-dot"></div>
      <div class="adendo-timeline-title">${title}</div>
      <div class="adendo-timeline-date">${fmtDatetime(e.ocorrido_em || e.created_at)}</div>
    </div>`;
}

/**
 * Positions the popover near an anchor element, preferring right/left based on viewport space.
 */
function positionPopover(popover, anchor) {
  const rect = anchor.getBoundingClientRect();
  const pw = 320; // popover width
  const vw = window.innerWidth;
  const vh = window.innerHeight;

  let top = rect.bottom + 8;
  let left = rect.left;

  // Prefer left side if not enough room on right
  if (left + pw > vw - 8) left = rect.right - pw;
  if (left < 8) left = 8;

  // Flip above if overflows bottom
  if (top + 420 > vh) top = rect.top - 420 - 8;
  if (top < 8) top = 8;

  popover.style.top = top + "px";
  popover.style.left = left + "px";
}

// ── Modal Status Geral ─────────────────────────────────────────────────────

async function abrirModalStatusGeral() {
  const modal = document.getElementById("modalStatusGeral");
  if (!modal) return;
  modal.classList.add("is-open");

  // Reset
  const summaryEl = document.getElementById("status-geral-summary");
  const tbodyEl = document.getElementById("tbody-status-geral");
  const countEl = document.getElementById("status-geral-count");
  if (summaryEl)
    summaryEl.innerHTML =
      '<p style="color:var(--text-muted);font-size:13px;">Carregando...</p>';
  if (tbodyEl)
    tbodyEl.innerHTML =
      '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Carregando...</td></tr>';

  try {
    const res = await fetch("get_adendo_status.php?mode=geral");
    const json = await res.json();

    if (!json.success) throw new Error(json.message || "Erro desconhecido");

    const { counts, items } = json;
    const total = counts.total || 0;
    const pct = (n) =>
      total > 0
        ? ` <span class="sg-card-pct">${Math.round((n / total) * 100)}%</span>`
        : "";

    // Summary cards
    if (summaryEl) {
      summaryEl.innerHTML = `
        <div class="sg-card c-total">
          <div class="sg-card-label">Total</div>
          <div class="sg-card-value">${total}</div>
        </div>
        <div class="sg-card c-assinado">
          <div class="sg-card-label"><i class="fa-solid fa-signature"></i> Assinados</div>
          <div class="sg-card-value">${counts.assinado}${pct(counts.assinado)}</div>
        </div>
        <div class="sg-card c-visualizado">
          <div class="sg-card-label"><i class="fa-solid fa-eye"></i> Visualizados</div>
          <div class="sg-card-value">${counts.visualizado}${pct(counts.visualizado)}</div>
        </div>
        <div class="sg-card c-enviado">
          <div class="sg-card-label"><i class="fa-solid fa-paper-plane"></i> Enviados</div>
          <div class="sg-card-value">${counts.enviado}${pct(counts.enviado)}</div>
        </div>
        <div class="sg-card c-nao-enviado">
          <div class="sg-card-label"><i class="fa-solid fa-circle-minus"></i> Não enviados</div>
          <div class="sg-card-value">${counts.nao_enviado}${pct(counts.nao_enviado)}</div>
        </div>`;
    }

    // Table
    if (countEl) countEl.textContent = total;

    if (tbodyEl) {
      if (items.length === 0) {
        tbodyEl.innerHTML =
          '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Nenhum adendo encontrado.</td></tr>';
      } else {
        tbodyEl.innerHTML = items
          .map((item) => {
            const info = adendoStatusInfo(item.status);
            const bCls = adendoBadgeClass(item.status);
            const envio = fmtDatetime(item.data_envio);
            const updated = fmtDatetime(item.updated_at);
            return `<tr>
            <td>${escHtml(item.nome_colaborador)}</td>
            <td class="col-center">${escHtml(item.competencia)}</td>
            <td class="col-center"><span class="adendo-badge ${bCls}"><i class="fa-solid ${info.icon}"></i> ${info.label}</span></td>
            <td class="col-center">${envio}</td>
            <td class="col-center">${updated}</td>
            <td class="col-center">
              <button class="btn-row validate btn-sg-detalhes" data-id="${escHtml(item.id)}" title="Ver histórico">
                <i class="fa-solid fa-clock-rotate-left"></i> Detalhes
              </button>
            </td>
          </tr>`;
          })
          .join("");

        // Wire detail buttons
        tbodyEl.querySelectorAll(".btn-sg-detalhes").forEach((btn) => {
          btn.addEventListener("click", () =>
            abrirDetalhesAdendoGeral(parseInt(btn.dataset.id, 10)),
          );
        });
      }
    }
  } catch (e) {
    console.error("Erro ao carregar status geral:", e);
    if (summaryEl)
      summaryEl.innerHTML =
        '<p style="color:#ef4444;font-size:13px;">Erro ao carregar dados.</p>';
    if (tbodyEl)
      tbodyEl.innerHTML =
        '<tr><td colspan="6" style="text-align:center;color:#ef4444;">Erro ao carregar.</td></tr>';
  }
}

function fecharModalStatusGeral() {
  const modal = document.getElementById("modalStatusGeral");
  if (modal) modal.classList.remove("is-open");
}

/**
 * Opens a SweetAlert with the log history for a specific adendo (from status geral modal).
 */
async function abrirDetalhesAdendoGeral(adendoId) {
  // We re-fetch by adendo id directly would require a different endpoint.
  // Instead, show what we have from the already-loaded list if possible.
  // For simplicity, query the single endpoint by searching the item in the list.
  Swal.fire({
    title: "Carregando histórico...",
    didOpen: () => Swal.showLoading(),
    showConfirmButton: false,
  });
  try {
    const res = await fetch(
      `get_adendo_status.php?adendo_id=${encodeURIComponent(adendoId)}&mode=by_id`,
    );
    const json = await res.json();
    const adendo = json.adendo;
    const log = json.log || [];
    Swal.close();
    await Swal.fire({
      title: adendo ? `Adendo — ${escHtml(adendo.competencia)}` : "Histórico",
      html: `<div style="text-align:left;">${buildPopoverBody(adendo, log)}</div>`,
      confirmButtonText: "Fechar",
      confirmButtonColor: "#4f80e1",
      width: 480,
    });
  } catch (e) {
    Swal.fire({
      icon: "error",
      title: "Erro",
      text: "Não foi possível carregar o histórico.",
      timer: 3000,
      timerProgressBar: true,
    });
  }
}

/** Escapes HTML special chars */
function escHtml(str) {
  return String(str ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

/* Adaptador de linhas: a API ainda entrega a mesma estrutura; somente a
 * apresentação das colunas muda conforme a aba ativa. */
document.addEventListener(
  "DOMContentLoaded",
  function ativarTabelaPagamentoRedesenhada() {
    const unpaid = document.getElementById("tabela-a-pagar");
    const paid = document.getElementById("tabela-pago");
    const divergence = document.getElementById("tabela-divergencias");
    if (!unpaid || !paid || !divergence) return;
    const money = (value) =>
      (Number(value) || 0).toLocaleString("pt-BR", {
        style: "currency",
        currency: "BRL",
      });
    const visibleRows = (table) =>
      Array.from(table.querySelectorAll("tbody tr")).filter(
        (row) => row.children.length > 1,
      );
    const paymentState = (checkbox, row) => {
      const value = Number(checkbox?.dataset.valor || 0);
      if (value <= 0) return "Sem valor definido";
      if (row.dataset.divergence === "1") return "Divergência";
      const partial = Number(checkbox?.dataset.pagoParcialCount || 0) > 0;
      return partial ? "Parcial" : "Pendente";
    };
    const statusBadge = (label) =>
      `<span class="payment-state state-${label.toLocaleLowerCase("pt-BR").replace(/[^a-z]/g, "")}">${label}</span>`;
    const transformUnpaid = (row) => {
      if (row.dataset.redesigned === "unpaid" || row.children.length < 7)
        return;
      const cells = Array.from(row.children);
      const [name, , role, value, checkboxCell, , actionCell] = cells;
      const checkbox = checkboxCell.querySelector(".pagamento-checkbox");
      const rawValue =
        Number(
          checkbox?.dataset.valor ||
            value.textContent
              .replace(/[^0-9,.-]+/g, "")
              .replace(/\./g, "")
              .replace(",", "."),
        ) || 0;
      row.dataset.functionName = role.textContent.trim();
      const task = document.createElement("td");
      task.className = "task-cell";
      task.innerHTML = `<strong>${name.textContent.trim()}</strong><small>${row.dataset.obra || ""}</small>`;
      const roleCell = document.createElement("td");
      roleCell.textContent = role.textContent.trim();
      const valueCell = document.createElement("td");
      valueCell.className = "col-numeric";
      valueCell.textContent = money(rawValue);
      const stateCell = document.createElement("td");
      stateCell.className = "financial-state-cell";
      stateCell.innerHTML = statusBadge(paymentState(checkbox, row));
      const newAction = document.createElement("td");
      newAction.className = "col-center row-actions";
      const originalAction = actionCell.querySelector("button");
      // O valor zero não impede a confirmação. A tarefa pode ser paga
      // individualmente ou em lote, mesmo quando ainda não recebeu valor.
      if (originalAction) {
        originalAction.classList.add("row-action-menu");
        originalAction.title = "Pagar item";
        originalAction.innerHTML =
          '<i class="fa-solid fa-ellipsis-vertical"></i>';
        newAction.append(originalAction);
      } else {
        const correction = document.createElement("button");
        correction.type = "button";
        correction.className = "row-action-menu";
        correction.title = "Adicionar valor";
        correction.innerHTML = '<i class="fa-solid fa-ellipsis-vertical"></i>';
        correction.addEventListener("click", () => {
          document.getElementById("adicionar-valor")?.click();
          document.getElementById("valor")?.focus();
        });
        newAction.append(correction);
      }
      row.replaceChildren(
        checkboxCell,
        task,
        roleCell,
        valueCell,
        stateCell,
        newAction,
      );
      row.dataset.redesigned = "unpaid";
    };
    const transformPaid = (row) => {
      if (row.dataset.redesigned === "paid" || row.children.length < 6) return;
      const cells = Array.from(row.children);
      const [name, , role, value, checkboxCell, date] = cells;
      const checkbox = checkboxCell.querySelector(".pagamento-checkbox");
      const rawValue = Number(checkbox?.dataset.valor || 0);
      row.dataset.functionName = role.textContent.trim();
      const task = document.createElement("td");
      task.className = "task-cell";
      task.innerHTML = `<strong>${name.textContent.trim()}</strong><small>${row.dataset.obra || ""}</small>`;
      const roleCell = document.createElement("td");
      roleCell.textContent = role.textContent.trim();
      const amount = document.createElement("td");
      amount.className = "col-numeric";
      amount.textContent = money(rawValue);
      // A API atual não entrega um tipo de pagamento canônico (Integral,
      // Complemento ou Ajuste); não inferir este dado apenas no frontend.
      const type = document.createElement("td");
      type.textContent = "—";
      const dateCell = document.createElement("td");
      dateCell.textContent = date.textContent.trim() || "—";
      const details = document.createElement("td");
      details.innerHTML = '<span class="details-muted">—</span>';
      row.replaceChildren(task, roleCell, amount, type, dateCell, details);
      row.dataset.redesigned = "paid";
    };
    const syncDivergences = () => {
      const legacy = document.getElementById("painel-divergencias");
      if (!legacy) return;
      const legacyRows = Array.from(legacy.querySelectorAll("tbody tr"));
      const tbody = divergence.querySelector("tbody");
      tbody.replaceChildren();
      legacyRows.forEach((legacyRow) => {
        const cells = Array.from(legacyRow.children);
        if (cells.length < 5) return;
        const row = document.createElement("tr");
        row.dataset.divergence = "1";
        row.dataset.functionName = cells[1].textContent.trim();
        const action = cells[4].querySelector("button");
        row.innerHTML = `<td class="task-cell"><strong>${cells[0].textContent.trim()}</strong></td><td>${cells[1].textContent.trim()}</td><td>${cells[2].textContent.trim()}</td><td>${cells[3].textContent.trim()}</td><td>${statusBadge("Divergência")}</td><td></td>`;
        if (action) row.lastElementChild.append(action);
        tbody.append(row);
        visibleRows(unpaid)
          .filter((unpaidRow) =>
            unpaidRow.textContent.includes(cells[0].textContent.trim()),
          )
          .forEach((unpaidRow) => {
            unpaidRow.dataset.divergence = "1";
            const state = unpaidRow.querySelector(".financial-state-cell");
            if (state) state.innerHTML = statusBadge("Divergência");
          });
      });
      legacy.remove();
      atualizarContadoresPagamento();
    };
    const transform = () => {
      visibleRows(unpaid).forEach(transformUnpaid);
      visibleRows(paid).forEach(transformPaid);
      if (
        !document.getElementById("funcoes-popover")?.dataset.initialized &&
        (visibleRows(unpaid).length || visibleRows(paid).length)
      ) {
        document
          .querySelectorAll("#funcoes-popover input")
          .forEach((input) => (input.checked = false));
        document.getElementById("funcoes-popover").dataset.initialized = "1";
      }
      syncDivergences();
      atualizarContadoresPagamento();
      window.pagamentoAplicarFiltrosVisuais?.();
    };
    const observer = new MutationObserver(transform);
    observer.observe(unpaid.querySelector("tbody"), { childList: true });
    observer.observe(paid.querySelector("tbody"), { childList: true });
    const legacyPanelObserver = new MutationObserver(() => {
      if (document.getElementById("painel-divergencias")) transform();
    });
    legacyPanelObserver.observe(document.body, {
      childList: true,
      subtree: true,
    });
    document.addEventListener("change", (event) => {
      if (event.target.matches(".pagamento-checkbox"))
        atualizarContadoresPagamento();
    });
    window.atualizarContadoresPagamento =
      function atualizarContadoresPagamento() {
        const allUnpaid = visibleRows(unpaid),
          allPaid = visibleRows(paid),
          allRows = [...allUnpaid, ...allPaid];
        const total = allRows.reduce((sum, row) => {
          const checkbox = row.querySelector(".pagamento-checkbox");
          const value = checkbox
            ? Number(checkbox.dataset.valor || 0)
            : Number(
                row.children[2]?.textContent
                  .replace(/[^0-9,.-]+/g, "")
                  .replace(/\./g, "")
                  .replace(",", "."),
              );
          return sum + (Number.isFinite(value) ? value : 0);
        }, 0);
        const unpaidTotal = allUnpaid.reduce(
          (sum, row) =>
            sum +
            (Number(
              row.querySelector(".pagamento-checkbox")?.dataset.valor || 0,
            ) || 0),
          0,
        );
        const paidTotal = Math.max(0, total - unpaidTotal);
        const set = (id, content) => {
          const el = document.getElementById(id);
          if (el) el.textContent = content;
        };
        set("total-imagens", allRows.length);
        set("total-itens-resumo", allRows.length);
        set("totalValor", money(total));
        set("total-imagens-nao-pagas", allUnpaid.length);
        set("totalValorNaoPago", money(unpaidTotal));
        set("total-imagens-pagas", allPaid.length);
        set("totalValorPago", money(paidTotal));
        set("tab-count-a-pagar", allUnpaid.length);
        set("tab-count-pagos", allPaid.length);
        set("tab-count-divergencias", visibleRows(divergence).length);
        const selected = Array.from(
          unpaid.querySelectorAll(".pagamento-checkbox:checked"),
        ).filter(
          (cb) => !cb.disabled && cb.closest("tr")?.offsetParent !== null,
        );
        const selectedTotal = selected.reduce(
          (sum, cb) => sum + (Number(cb.dataset.valor || 0) || 0),
          0,
        );
        set(
          "selection-summary",
          `${selected.length} ${selected.length === 1 ? "item selecionado" : "itens selecionados"} · ${money(selectedTotal)}`,
        );
        const bar = document.getElementById("selection-action-bar");
        if (bar) bar.hidden = selected.length === 0;
        const selectAll = document.getElementById("selecionar-visiveis");
        if (selectAll) {
          const eligible = pagamentoVisibleCheckboxes(
            "#tabela-a-pagar .pagamento-checkbox",
          );
          selectAll.checked =
            eligible.length > 0 && eligible.every((cb) => cb.checked);
          selectAll.indeterminate = selected.length > 0 && !selectAll.checked;
        }
      };
    window.contarLinhasTabela = window.atualizarContadoresPagamento;
    window.filtrarTabela = () => window.pagamentoAplicarFiltrosVisuais?.();
    setTimeout(transform, 0);
  },
);
