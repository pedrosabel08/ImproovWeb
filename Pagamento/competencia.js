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
  const csrf = document.querySelector('meta[name="pagamento-csrf"]').content;
  let serial = 0,
    request = null;
  const selector = document.getElementById("colaborador");
  const legacyOptions = Array.from(selector.options, o => ({value:o.value,text:o.text}));
  window.pagamentoAtualizarColaboradoresCompetencia = (people, ref) => {
    const selected = selector.value;
    const previous = selector.selectedOptions[0];
    if (people) {
      if (selector.dataset.competenciaSnapshot === ref) return;
      selector.replaceChildren(new Option("Escolha um colaborador", ""),
        ...[...people].sort((a,b)=>a.nome.localeCompare(b.nome,"pt-BR")).map(p=>new Option(p.nome,String(p.colaborador_id))));
      selector.dataset.competenciaSnapshot = ref;
    } else {
      if (!selector.dataset.competenciaSnapshot) return;
      selector.replaceChildren(...legacyOptions.map(o=>new Option(o.text,o.value)));
      if (selected && !legacyOptions.some(o=>o.value===selected) && previous)
        selector.add(new Option(previous.text,selected));
      delete selector.dataset.competenciaSnapshot;
    }
    selector.value = Array.from(selector.options).some(o=>o.value===selected) ? selected : "";
  };
  async function api(ref, body) {
    const r = await fetch(
      (document.querySelector('meta[name="pagamento-test-competencia"]')?.content || "api/fechamento/competencia.php") +
        (body ? "" : (document.querySelector('meta[name="pagamento-test-competencia"]') ? "&" : "?")+"competencia=" + encodeURIComponent(ref)),
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
      const error = new Error(j.error || "Não foi possível carregar a competência.");
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
      card.className = "overview-card";
      document.getElementById("payment-individual").append(card);
    }
    card.innerHTML =
      '<div role="status"><canvas data-thinking-orb data-orb-state="composing" data-orb-size="48" aria-label="Carregando fechamento"></canvas>Carregando fechamento…</div>';
    window.thinkingOrbs?.(card);
    try {
      const c = await api(ref);
      if (token !== serial) return true;
      window.pagamentoAtualizarColaboradoresCompetencia(c.colaboradores,ref);
      b = selector.value;
      const p = c.colaboradores.find(
        (p) => String(p.colaborador_id) === String(b),
      );
      const close = "fechamento.php?competencia=" + encodeURIComponent(ref);
      if (!p) {
        card.innerHTML =
          "<p>" +
          (!b
            ? "Selecione um colaborador."
            : "Este colaborador não integra o fechamento desta competência.") +
          '</p><a class="btn btn-secondary" href="' +
          close +
          '">Abrir fechamento</a>';
        return true;
      }
      const closed = c.estado === "CONCLUIDO",
        paid = p.pagamento_status === "PAGO";
      const row = (l, v) =>
        "<div><dt>" + escape(l) + "</dt><dd>" + money(v) + "</dd></div>";
      card.innerHTML =
        '<header class="fm-list-heading"><h2>' +
        escape(p.nome) +
        " · " +
        escape(new Date(ref+"-01T12:00:00").toLocaleDateString("pt-BR",{month:"long",year:"numeric"})) +
        '</h2><span class="overview-status-pill ' +
        (paid ? "paid" : "pending") +
        '">' +
        (paid
          ? "Pago"
          : closed
            ? "Pendente de pagamento"
            : "Aguardando fechamento") +
        "</span></header>" +
        "<p>" +
        (closed
          ? "Fechamento concluído · valores oficiais preservados."
          : "Fechamento em andamento · valores parciais para revisão.") +
        "</p><p>Remuneração: " +
        ({FIXO:"Fixo",FIXO_VARIAVEL:"Fixo + variável",VARIAVEL:"Variável"}[p.tipo_remuneracao] || "Não definida") +
        "</p><p>Pagamento previsto: " +
        escape(c.previsto_em.split("-").reverse().join("/")) +
        " · 5º dia útil</p>" +
        '<dl class="fm-breakdown">' +
        row(
          "Fixo",
          (p.resumo.VALOR_FIXO || 0) + (p.resumo.ACOMPANHAMENTO_ESPECIAL || 0),
        ) +
        row("Adendos / tarefas", (p.resumo.SERVICOS || 0) + (p.reconciliacao?.credito_historico_centavos || 0)) +
        row(
          "Extras / bônus",
          (p.resumo.BONUS_EXTRAS || 0) + (p.resumo.BONUS_PRODUTIVIDADE || 0),
        ) +
        row("Descontos", Math.abs(p.resumo.DESCONTO || 0)) +
        row(closed ? "Total fechado" : "Parcial individual", p.total_centavos) +
        (!closed && p.reconciliacao?.pago_centavos ? row("Pagamento histórico a reconciliar", p.reconciliacao.pago_centavos) : "") +
        row("Pago", closed ? p.pago_centavos : null) +
        row("Pendente", p.pendente_centavos) +
        "</dl>" +
        '<a class="btn btn-secondary" href="' +
        close +
        '">Abrir fechamento</a>' +
        (closed && !paid
          ? '<form id="competencia-payment-form"><label>Data real do pagamento<input name="data_pagamento" type="date" required value="' +
            new Date().toLocaleDateString("en-CA") +
            '"></label><label>Observação<input name="observacao" maxlength="255"></label><button class="btn btn-primary" type="submit">Registrar quitação do colaborador</button><p>Esta ação registra o valor pendente e marca todas as funções do fechamento como pagas.</p><p id="competencia-payment-message" role="status"></p></form>'
          : "") +
        (paid
          ? "<p>Pago em " +
            escape(p.pago_em.split("-").reverse().join("/")) +
            "</p>"
          : "");
      if (p.funcoes?.length) {
        const list = document.createElement('section');
        list.className = 'competencia-functions';
        list.innerHTML = '<details><summary>Funções do fechamento (' + p.funcoes.length + ')</summary><p>A situação financeira acompanha a quitação integral do colaborador.</p><ul>' + p.funcoes.map(f => '<li><span>' + escape(f.descricao?.funcao || 'Função #' + f.identidade.origem_id) + (f.descricao?.imagem ? ' · ' + escape(f.descricao.imagem) : '') + '</span><strong>' + (paid ? 'Pago' : closed ? 'Pendente' : 'Aguardando fechamento') + '</strong></li>').join('') + '</ul></details>';
        card.append(list);
      }
      const form = card.querySelector("form");
      if (form)
        form.onsubmit = async (e) => {
          e.preventDefault();
          const button = form.querySelector("button");
          button.disabled = true;
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
            if (token === serial && document.body.dataset.paymentView === "colaborador"
                && selector.value === String(b) && document.getElementById("mes").value === String(mes)
                && document.getElementById("ano").value === String(ano))
              await window.pagamentoCarregarCompetencia(b, mes, ano);
          } catch (err) {
            if (err.status >= 400 && err.status < 500) {
              sessionStorage.removeItem(storage);
              request = null;
            }
            const message = card.querySelector("#competencia-payment-message");
            if (message && token === serial) message.textContent = err.message;
            button.disabled = false;
          }
        };
    } catch (e) {
      if (token === serial)
        card.innerHTML = '<p role="alert">' + escape(e.message) + "</p>";
    }
    return true;
  };
})();
