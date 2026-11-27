(function (root) {
  "use strict";
  function formatarTempo(segundos) {
    if (segundos == null || !Number.isFinite(Number(segundos))) return "—";
    segundos = Math.max(0, Number(segundos));
    if (segundos < 60) return Math.round(segundos) + " s";
    var minutos = Math.round(segundos / 60);
    var horas = Math.floor(minutos / 60);
    var restante = minutos % 60;
    return horas
      ? horas + "h" + (restante ? " " + restante + "min" : "")
      : minutos + " min";
  }
  function formatarVariacao(valor) {
    if (valor == null || !Number.isFinite(Number(valor)))
      return { texto: "—", classe: "" };
    valor = Number(valor);
    return {
      texto:
        (valor < 0 ? "↓ " : valor > 0 ? "↑ " : "") +
        Math.abs(valor).toLocaleString("pt-BR", {
          minimumFractionDigits: 1,
          maximumFractionDigits: 1,
        }) +
        "%",
      classe: valor < 0 ? "is-down" : valor > 0 ? "is-up" : "",
    };
  }
  function formatarData(data) {
    var partes = data.split(" ");
    var d = partes[0].split("-");
    return d[2] + "/" + d[1] + "/" + d[0] + " " + partes[1];
  }
  var api = {
    formatarTempo: formatarTempo,
    formatarVariacao: formatarVariacao,
  };
  if (typeof module !== "undefined" && module.exports) module.exports = api;
  if (!root.document) return;
  var doc = root.document;
  var botao = doc.getElementById("card-mediana");
  var painel = doc.getElementById("pos-tempo-popover");
  if (!botao || !painel) return;
  var closeTimer;
  function posicionar() {
    if (painel.hidden) return;
    var rect = botao.getBoundingClientRect();
    var margem = 8;
    painel.style.left =
      Math.max(
        margem,
        Math.min(
          rect.right - painel.offsetWidth,
          root.innerWidth - painel.offsetWidth - margem,
        ),
      ) + "px";
    var abaixo = rect.bottom + margem;
    var altura = painel.offsetHeight;
    painel.style.top =
      (abaixo + altura <= root.innerHeight - margem
        ? abaixo
        : Math.max(margem, rect.top - altura - margem)) + "px";
  }
  function fechar() {
    if (painel.hidden) return;
    botao.setAttribute("aria-expanded", "false");
    painel.classList.remove("is-open");
    clearTimeout(closeTimer);
    closeTimer = setTimeout(
      function () {
        painel.hidden = true;
      },
      root.matchMedia("(prefers-reduced-motion: reduce)").matches ? 0 : 180,
    );
  }
  function abrir() {
    clearTimeout(closeTimer);
    painel.hidden = false;
    botao.setAttribute("aria-expanded", "true");
    posicionar();
    root.requestAnimationFrame(function () {
      painel.classList.add("is-open");
    });
  }
  botao.addEventListener("click", function () {
    botao.getAttribute("aria-expanded") === "true" ? fechar() : abrir();
  });
  doc.addEventListener("click", function (e) {
    if (!botao.contains(e.target) && !painel.contains(e.target)) fechar();
  });
  doc.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && botao.getAttribute("aria-expanded") === "true") {
      fechar();
      botao.focus();
    }
  });
  root.addEventListener("resize", posicionar);
  doc.addEventListener("scroll", posicionar, true);
  api.indisponivel = function () {
    doc.getElementById("pos-ciclos").textContent =
      "Dados indisponíveis no momento.";
    doc.getElementById("pos-revisoes-vazio").textContent =
      "Não foi possível carregar o detalhamento.";
    doc.getElementById("pos-revisoes-vazio").hidden = false;
  };
  api.render = function (dados) {
    var atual = dados.atual,
      anterior = dados.anterior;
    var variacao = formatarVariacao(dados.variacao_percentual);
    var trend = doc.getElementById("pos-variacao");
    var detalheTrend = doc.getElementById("pos-detalhe-variacao");
    doc.getElementById("pos-mediana").textContent = formatarTempo(
      atual.mediana_segundos,
    );
    doc.getElementById("pos-detalhe-atual").textContent = formatarTempo(
      atual.mediana_segundos,
    );
    doc.getElementById("pos-detalhe-anterior").textContent = formatarTempo(
      anterior.mediana_segundos,
    );
    doc.getElementById("pos-ciclos").textContent =
      "Últimos 30 dias · " +
      atual.amostra +
      (atual.amostra === 1 ? " ciclo concluído" : " ciclos concluídos");
    [trend, detalheTrend].forEach(function (el) {
      el.textContent = variacao.texto;
      el.classList.remove("is-up", "is-down");
      if (variacao.classe) el.classList.add(variacao.classe);
    });
    var tooltip =
      "Atual: " +
      formatarTempo(atual.mediana_segundos) +
      " · " +
      atual.amostra +
      " ciclos (" +
      formatarData(atual.inicio) +
      " a " +
      formatarData(atual.fim) +
      ").\nAnterior: " +
      formatarTempo(anterior.mediana_segundos) +
      " · " +
      anterior.amostra +
      " ciclos (" +
      formatarData(anterior.inicio) +
      " até antes de " +
      formatarData(anterior.fim) +
      ").";
    trend.title = tooltip;
    botao.setAttribute(
      "aria-label",
      "Mediana de pós: " +
        formatarTempo(atual.mediana_segundos) +
        ". Variação: " +
        variacao.texto +
        ". Abrir detalhamento. " +
        tooltip,
    );
    var corpo = doc.getElementById("pos-revisoes");
    corpo.replaceChildren();
    doc.getElementById("pos-revisoes-vazio").textContent =
      "Nenhum ciclo concluído neste período.";
    dados.revisoes.forEach(function (item) {
      var tr = doc.createElement("tr");
      [
        item.revisao,
        String(item.amostra),
        formatarTempo(item.segundos),
      ].forEach(function (valor) {
        var td = doc.createElement("td");
        td.textContent = valor;
        tr.appendChild(td);
      });
      corpo.appendChild(tr);
    });
    doc.getElementById("pos-revisoes-vazio").hidden = dados.revisoes.length > 0;
    if (!painel.hidden) posicionar();
  };
  root.PosTempo = api;
})(typeof window !== "undefined" ? window : globalThis);
