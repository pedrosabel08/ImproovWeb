/* Liquidação integral por pessoa sobre a revisão oficial, com retry da mesma operação. */
(() => {
  const escape = (v) =>
    String(v ?? "").replace(
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
  const money = (v) =>
    v === null || v === undefined
      ? "—"
      : (Number(v) / 100).toLocaleString("pt-BR", {
          style: "currency",
          currency: "BRL",
        });
  const icon = (name) => `<i class="fa-solid ${name}" aria-hidden="true"></i>`;
  const date = (value) =>
    escape(
      String(value || "")
        .split("-")
        .reverse()
        .join("/"),
    );
  const numberAttributes = (value) =>
    value === null || value === undefined || !Number.isFinite(Number(value))
      ? ""
      : ` data-payment-number="${Number(value)}" data-number-kind="money"`;
  const csrf = document.querySelector('meta[name="pagamento-csrf"]').content;
  let serial = 0,
    request = null;
  const selector = document.getElementById("colaborador");
  const legacyOptions = Array.from(selector.options, (o) => ({
    value: o.value,
    text: o.text,
  }));
  const paymentOption = (person) => {
    const name = String(person.nome || "Colaborador");
    const states = {
      PAGO: { icon: "✅" },
      PENDENTE: {
        icon: "🟠",
      },
      AGUARDANDO_FECHAMENTO: { icon: "🟣" },
    };
    const state =
      states[person.pagamento_status] || states.AGUARDANDO_FECHAMENTO;
    return new Option(`${state.icon} ${name}`, String(person.colaborador_id));
  };
  window.pagamentoAtualizarColaboradoresCompetencia = (people, ref) => {
    const selected = selector.value;
    const previous = selector.selectedOptions[0];
    if (people) {
      selector.replaceChildren(
        new Option("Escolha um colaborador", ""),
        ...[...people]
          .sort((a, b) => a.nome.localeCompare(b.nome, "pt-BR"))
          .map(paymentOption),
      );
      selector.dataset.competenciaSnapshot = ref;
    } else {
      if (!selector.dataset.competenciaSnapshot) return;
      selector.replaceChildren(
        ...legacyOptions.map((o) => new Option(o.text, o.value)),
      );
      if (
        selected &&
        !legacyOptions.some((o) => o.value === selected) &&
        previous
      )
        selector.add(new Option(previous.text, selected));
      delete selector.dataset.competenciaSnapshot;
    }
    selector.value = Array.from(selector.options).some(
      (o) => o.value === selected,
    )
      ? selected
      : "";
  };
  async function api(ref, body) {
    const r = await fetch(
      (document.querySelector('meta[name="pagamento-test-competencia"]')
        ?.content || "api/fechamento/competencia.php") +
        (body
          ? ""
          : (document.querySelector('meta[name="pagamento-test-competencia"]')
              ? "&"
              : "?") +
            "competencia=" +
            encodeURIComponent(ref)),
      body
        ? {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-CSRF-Token": csrf,
            },
            body: JSON.stringify(body),
          }
        : {},
    );
    const j = await r.json();
    if (!r.ok || !j.success) {
      const error = new Error(
        j.error || "Não foi possível carregar a competência.",
      );
      error.status = r.status;
      throw error;
    }
    return j.data;
  }
  window.pagamentoCarregarCompetencia = async (b, mes, ano) => {
    const token = ++serial,
      ref = String(ano) + "-" + String(mes).padStart(2, "0");
    const official = ref >= document.body.dataset.pagamentoInicio;
    document.body.dataset.officialIndividual = String(official);
    if (!official) {
      window.pagamentoAtualizarColaboradoresCompetencia(null);
      return false;
    }
    let card = document.getElementById("competencia-individual");
    if (!card) {
      card = document.createElement("section");
      card.id = "competencia-individual";
      card.className = "competencia-person";
      document.getElementById("payment-individual").append(card);
    }
    window.pagamentoMotion?.stop(card);
    card.innerHTML =
      '<div class="overview-card ci-empty ci-loading" role="status"><canvas data-thinking-orb data-orb-state="composing" data-orb-size="48" aria-label="Carregando fechamento"></canvas><p>Carregando fechamento…</p></div>';
    window.thinkingOrbs?.(card);
    try {
      const c = await api(ref);
      if (token !== serial) return true;
      window.pagamentoAtualizarColaboradoresCompetencia(c.colaboradores, ref);
      b = selector.value;
      const p = c.colaboradores.find(
        (p) => String(p.colaborador_id) === String(b),
      );
      const close = "fechamento.php?competencia=" + encodeURIComponent(ref);
      if (!p) {
        card.innerHTML = `<div class="overview-card ci-empty">${icon("fa-user")}
          <h2>${!b ? "Selecione um colaborador" : "Colaborador fora desta competência"}</h2>
          <p>${!b ? "Escolha uma pessoa no filtro para consultar os valores e o pagamento." : "Este colaborador não integra o fechamento da competência selecionada."}</p>
          <a class="btn btn-secondary" href="${close}">${icon("fa-list-check")} Abrir fechamento</a></div>`;
        return true;
      }
      const closed = c.estado === "CONCLUIDO",
        paid = p.pagamento_status === "PAGO";
      const row = (l, v) =>
        "<div><dt>" +
        escape(l) +
        "</dt><dd" +
        numberAttributes(v) +
        ">" +
        money(v) +
        "</dd></div>";
      const type =
        {
          FIXO: "Fixo",
          FIXO_VARIAVEL: "Fixo + variável",
          VARIAVEL: "Variável",
        }[p.tipo_remuneracao] || "Não definida";
      const names = p.nome.trim().split(/\s+/);
      const initials =
        (names[0]?.[0] || "") +
        (names.length > 1 ? names[names.length - 1][0] : "");
      const status = paid
        ? "Pago"
        : closed
          ? "Pendente de pagamento"
          : "Aguardando fechamento";
      const metric = (
        label,
        value,
        className,
        iconName,
      ) => `<article class="overview-kpi ${className}">
        <span class="overview-kpi-icon">${icon(iconName)}</span><div><h3>${escape(label)}</h3><strong${numberAttributes(value)}>${money(value)}</strong></div></article>`;
      const payment =
        closed && !paid
          ? `<form id="competencia-payment-form"><div class="ci-form-fields">
            <label for="competencia-payment-date"><span>Data real do pagamento</span><input id="competencia-payment-date" name="data_pagamento" type="date" required value="${new Date().toLocaleDateString("en-CA")}"></label>
            <label for="competencia-payment-observation"><span>Observação <small class="ci-optional">(opcional)</small></span><input id="competencia-payment-observation" name="observacao" maxlength="255" placeholder="Informações sobre o pagamento"></label>
          </div><div class="ci-payment-footer"><button class="btn btn-primary" type="submit">${icon("fa-check")} Registrar quitação</button></div>
          <p class="ci-payment-help">${icon("fa-circle-info")}<span>Registra o valor pendente e marca todas as funções do fechamento como pagas.</span></p>
          <p id="competencia-payment-message" role="status" aria-live="polite"></p></form>`
          : `<div class="ci-payment-state ${paid ? "is-paid" : ""}">${icon(paid ? "fa-circle-check" : "fa-lock")}
            <div><strong>${paid ? "Pagamento registrado" : "Aguardando a conclusão do fechamento"}</strong>
            <p>${paid ? "Pago em " + date(p.pago_em) : "A quitação estará disponível depois que todos os adendos forem conferidos e o fechamento for concluído."}</p></div></div>`;
      card.innerHTML =
        `<header class="overview-card ci-person-header"><div class="ci-person-heading">
          <span class="ci-avatar" aria-hidden="true">${escape(initials)}</span><div><h2>${escape(p.nome)}</h2>
          <div class="ci-person-meta"><span class="ci-type">${escape(type)}</span><span>${icon(closed ? "fa-circle-check" : "fa-clock")} Fechamento ${closed ? "concluído" : "em andamento"}</span></div></div></div>
          <div class="ci-forecast">${icon("fa-calendar-days")}<div><span>Pagamento previsto</span><strong>${date(c.previsto_em)} <small>· 5º dia útil</small></strong></div></div>
          <div class="ci-header-actions"><span class="overview-status-pill ${paid ? "paid" : "pending"}">${icon(paid ? "fa-circle-check" : "fa-clock")} ${status}</span>
          <a class="btn btn-secondary" href="${close}">${icon("fa-list-check")} Abrir fechamento</a></div></header>
        <section class="ci-totals" aria-label="Resumo financeiro do colaborador">
          ${metric(closed ? "Total fechado" : "Parcial individual", p.total_centavos, "cost", "fa-wallet")}
          ${metric("Pago", closed ? p.pago_centavos : null, "paid", "fa-circle-check")}
          ${metric("Pendente", p.pendente_centavos, "pending", "fa-clock")}
        </section><div class="ci-detail-grid"><article class="overview-card ci-composition"><header class="ci-section-heading">${icon("fa-coins")}<h3>Composição do pagamento</h3></header><dl class="ci-breakdown">` +
        row(
          "Fixo",
          (p.resumo.VALOR_FIXO || 0) + (p.resumo.ACOMPANHAMENTO_ESPECIAL || 0),
        ) +
        row(
          "Adendos / tarefas",
          (p.resumo.SERVICOS || 0) +
            (p.reconciliacao?.credito_historico_centavos || 0),
        ) +
        row(
          "Extras / bônus",
          (p.resumo.BONUS_EXTRAS || 0) + (p.resumo.BONUS_PRODUTIVIDADE || 0),
        ) +
        row("Descontos", Math.abs(p.resumo.DESCONTO || 0)) +
        (!closed && p.reconciliacao?.pago_centavos
          ? row(
              "Pagamento histórico a reconciliar",
              p.reconciliacao.pago_centavos,
            )
          : "") +
        `</dl></article><article class="overview-card ci-payment"><header class="ci-section-heading">${icon("fa-money-bill-transfer")}<h3>Pagamento do colaborador</h3></header>${payment}</article></div>`;
      if (p.funcoes?.length) {
        const list = document.createElement("section");
        list.className = "overview-card competencia-functions";
        list.innerHTML =
          `<details><summary>${icon("fa-list-check")}<span>Funções do fechamento</span><span class="ci-count">${p.funcoes.length}</span>${icon("fa-chevron-down")}</summary>
          <p class="ci-functions-help">A situação financeira acompanha a quitação integral do colaborador.</p><ul>` +
          p.funcoes
            .map(
              (
                f,
              ) => `<li><div><strong>${escape(f.descricao?.imagem || "Serviço")}</strong><span>${escape(f.descricao?.funcao || "Função #" + f.identidade.origem_id)}</span></div>
            <span class="overview-status-pill ${paid ? "paid" : "pending"}">${paid ? "Pago" : closed ? "Pendente" : "Aguardando fechamento"}</span></li>`,
            )
            .join("") +
          "</ul></details>";
        card.append(list);
      }
      window.pagamentoMotion?.animate(card);
      const form = card.querySelector("form");
      if (form)
        form.onsubmit = async (e) => {
          e.preventDefault();
          const button = form.querySelector("button");
          if (button.disabled) return;
          const originalButton = button.innerHTML;
          button.disabled = true;
          button.setAttribute("aria-busy", "true");
          form.setAttribute("aria-busy", "true");
          button.innerHTML =
            '<canvas data-thinking-orb data-orb-state="working" data-orb-size="24" aria-label="Registrando quitação" aria-hidden="true"></canvas><span>Registrando…</span>';
          const stopOrb = window.thinkingOrbs?.(button);
          const message = card.querySelector("#competencia-payment-message");
          if (message) message.textContent = "";
          const values = new FormData(form);
          const storage =
            "pagamento-liquidacao:" + csrf.slice(0, 16) + ":" + ref + ":" + b;
          try {
            request = JSON.parse(sessionStorage.getItem(storage) || "null") || {
              acao: "pagar",
              competencia: ref,
              colaborador_id: Number(b),
              data_pagamento: values.get("data_pagamento"),
              observacao: values.get("observacao"),
              idempotency_key: crypto.randomUUID(),
            };
            sessionStorage.setItem(storage, JSON.stringify(request));
            await api(ref, request);
            sessionStorage.removeItem(storage);
            request = null;
            window.dispatchEvent(new CustomEvent("pagamento:quitacao"));
            if (
              token === serial &&
              document.body.dataset.paymentView === "colaborador" &&
              selector.value === String(b) &&
              document.getElementById("mes").value === String(mes) &&
              document.getElementById("ano").value === String(ano)
            )
              await window.pagamentoCarregarCompetencia(b, mes, ano);
          } catch (err) {
            if (err.status >= 400 && err.status < 500) {
              sessionStorage.removeItem(storage);
              request = null;
            }
            const message = card.querySelector("#competencia-payment-message");
            if (message && token === serial) message.textContent = err.message;
          } finally {
            stopOrb?.();
            if (button.isConnected) {
              button.innerHTML = originalButton;
              button.disabled = false;
              button.removeAttribute("aria-busy");
              form.removeAttribute("aria-busy");
            }
          }
        };
    } catch (e) {
      if (token === serial)
        card.innerHTML =
          '<p class="overview-notice" role="alert">' +
          escape(e.message) +
          "</p>";
    }
    return true;
  };
})();
