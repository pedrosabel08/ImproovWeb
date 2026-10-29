import { createPdfView } from "./fechamento-pdf.js?v=20261006-continuo";
const $ = (id) => document.getElementById(id);
const esc = (v) =>
  String(v ?? "").replace(
    /[&<>"']/g,
    (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        c
      ],
  );
const types = {
  FIXO: "Fixo",
  VARIAVEL: "Variável",
  FIXO_VARIAVEL: "Fixo + variável",
};
const statuses = {
  NAO_REVISADO: "Não revisado",
  ATENCAO: "Atenção",
  CONFIRMADO: "Revisado",
};
const serviceStates = {
  RETIRADO: "Retirado do fechamento",
  DEVIDO: "A pagar",
  QUITADO: "Pago",
  SEM_VALOR_DEVIDO: "Sem valor",
  NAO_ELEGIVEL: "Fora da competência",
  PENDENCIA: "Conferir",
};
const competenceMonths = [
  "JANEIRO",
  "FEVEREIRO",
  "MARÇO",
  "ABRIL",
  "MAIO",
  "JUNHO",
  "JULHO",
  "AGOSTO",
  "SETEMBRO",
  "OUTUBRO",
  "NOVEMBRO",
  "DEZEMBRO",
];
function sanitizeFileName(value) {
  const name = String(value ?? "").trim();
  const safe = name
    .replace(/\s+/g, "_")
    .replace(/[\\/:*?"<>|]/g, "-")
    .replace(/-+/g, "-")
    .replace(/^[ -]+|[ -]+$/g, "");
  return safe || "ADENDO";
}
function setDocumentState(text) {
  const label = $("fm-document-state").querySelector("span");
  if (label) label.textContent = text;
}
function documentFileName() {
  const [year, month] = monthly.competencia.split("-");
  const nome = monthly.colaboradores[index]?.nome ?? "COLABORADOR";
  const mesNome = competenceMonths[Number(month) - 1] ?? month;
  return `ADENDO_CONTRATUAL_${sanitizeFileName(nome)}_${mesNome}_${year}.pdf`;
}
export const money = (v) => {
  if (v === null || v === undefined) return "—";
  const s = String(v);
  if (!/^-?\d+$/.test(s)) return "—";
  if (s.startsWith("-")) return "- " + money(s.slice(1));
  const d = s.padStart(3, "0");
  return (
    "R$ " +
    d.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, ".") +
    "," +
    d.slice(-2)
  );
};
const decimal = (v) =>
  String(v).padStart(3, "0").slice(0, -2) +
  "," +
  String(v).padStart(3, "0").slice(-2);
const csrf = document.querySelector('meta[name="pagamento-csrf"]').content;
const journal = "fechamento-mensal:" + csrf.slice(0, 16);
let pending = null,
  busy = false,
  globalBusy = false,
  monthly = null,
  financial = null,
  documentMeta = null,
  viewed = false,
  index = -1,
  blobURL = null;
let queue = [],
  queuePosition = -1;
let serviceDraft = [];
let statsSignature = null;
const icon = (name) =>
  '<i class="fa-solid ' + name + '" aria-hidden="true"></i>';
function paymentNumberAttrs(value, kind) {
  const raw = String(value ?? "");
  const number = /^\d+$/.test(raw) ? Number(raw) : NaN;
  return Number.isSafeInteger(number)
    ? 'data-payment-number="' + number + '" data-number-kind="' + kind + '"'
    : "";
}
function syncCompetence() {
  const [year, month] = ref().split("-");
  if (![...$("fm-select-year").options].some((o) => o.value === year))
    $("fm-select-year").add(new Option(year, year));
  $("fm-select-month").value = month;
  $("fm-select-year").value = year;
  document.querySelectorAll("[data-payment-link]").forEach((link) => {
    link.href =
      "./?" +
      new URLSearchParams({
        view: link.dataset.paymentLink,
        mes: String(Number(month)),
        ano: year,
      });
  });
  $("fm-status-link").href =
    "./?" +
    new URLSearchParams({
      view: "colaborador",
      mes: String(Number(month)),
      ano: year,
      adendos: "1",
    });
}
function placeholder(title, text) {
  $("fm-pdf-placeholder").hidden = false;
  $("fm-pdf-placeholder-title").textContent = title;
  $("fm-pdf-placeholder-text").textContent = text;
  $("fm-pdf-orb").hidden = true;
  $("fm-pdf-placeholder-icon").hidden = false;
  $("fm-pdf-placeholder").setAttribute("aria-busy", "false");
}
function pdfLoading(title, text) {
  $("fm-pdf-placeholder").hidden = false;
  $("fm-pdf-placeholder-title").textContent = title;
  $("fm-pdf-placeholder-text").textContent = text;
  $("fm-pdf-orb").hidden = false;
  $("fm-pdf-placeholder-icon").hidden = true;
  $("fm-pdf-placeholder").setAttribute("aria-busy", "true");
}
try {
  pending = JSON.parse(sessionStorage.getItem(journal) || "null");
} catch {}
const pdf = createPdfView(
  $("fm-pdf"),
  $("fm-pdf-previous"),
  $("fm-pdf-next"),
  $("fm-pdf-page"),
  $("fm-zoom-out"),
  $("fm-zoom-in"),
  $("fm-zoom"),
);
window.thinkingOrbs();
const ref = () => $("fm-month").value;
const context = () => ({
  colaborador_id: monthly.colaboradores[index].colaborador_id,
  competencia: monthly.competencia,
});
const apiRoot = document.querySelector('meta[name="mensal-test-api"]')?.content;
const endpoint = (a) =>
  apiRoot
    ? apiRoot + encodeURIComponent(a)
    : ["concluir", "quitar", "pagar"].includes(a)
      ? "api/fechamento/competencia.php"
      : "api/" +
        ([
          "mensal",
          "iniciar",
          "incluir",
          "obter",
          "preparar",
          "decidir",
        ].includes(a)
          ? "fechamento/"
          : "documento/") +
        a +
        ".php";
const message = (t) => {
  $("fm-message").textContent = t;
  $("fm-message").hidden = !t;
  for (const id of ["fm-services-message", "fm-service-error"]) {
    $(id).textContent = t;
    $(id).hidden = !t;
  }
};
const save = (p) => {
  pending = p;
  try {
    p
      ? sessionStorage.setItem(journal, JSON.stringify(p))
      : sessionStorage.removeItem(journal);
  } catch {}
  update();
};
function update() {
  const locked = busy || !!pending;
  for (const id of [
    "fm-start",
    "fm-month",
    "fm-select-month",
    "fm-select-year",
    "fm-back",
    "fm-skip",
    "fm-recalculate",
    "fm-extra-close",
    "fm-refresh",
    "fm-include",
  ])
    $(id).disabled = locked || serviceDraft.length > 0;
  const closed = monthly?.estado === "CONCLUIDO";
  const includeAvailable =
    !closed &&
    !!monthly?.ciclo_id &&
    monthly.inclusao_disponivel === true &&
    (monthly.participantes_disponiveis || []).length > 0;
  $("fm-include").hidden = !includeAvailable;
  $("fm-roster-note").hidden =
    !monthly?.ciclo_id ||
    closed ||
    monthly.inclusao_disponivel !== false ||
    !(monthly.participantes_disponiveis || []).length;
  document
    .querySelectorAll(
      "[data-service-index],[data-service-edit],[data-service-reset],[data-service-tariff]",
    )
    .forEach((button) => (button.disabled = locked || closed));
  for (const id of ["fm-withdraw-function", "fm-restore-function"])
    $(id).disabled =
      locked || closed || !$("fm-service-function").options.length;
  $("fm-service-submit").disabled = locked || closed;
  $("fm-service-draft-save").disabled = locked || closed;
  $("fm-service-draft-discard").disabled = locked;
  $("fm-service-details").disabled = busy || !financial?.revisao;
  $("fm-start").disabled =
    locked ||
    !!serviceDraft.length ||
    (!monthly?.estado && !monthly?.quantidade);
  if ($("fm-conclude"))
    $("fm-conclude").disabled =
      locked ||
      !!serviceDraft.length ||
      closed ||
      !monthly?.quantidade ||
      monthly.contagens.CONFIRMADO !== monthly.quantidade;
  $("fm-recalculate").disabled ||= closed;
  if ($("fm-discount"))
    $("fm-discount").disabled = locked || closed || !financial?.revisao;
  $("fm-review").disabled =
    locked ||
    !!serviceDraft.length ||
    !monthly?.colaboradores.some(
      (c) => c.preparado && c.status !== "CONFIRMADO",
    );
  $("fm-confirm").disabled =
    locked ||
    !!serviceDraft.length ||
    !viewed ||
    documentMeta?.estado !== "PREVIEW";
  $("fm-extra").disabled =
    locked || !!serviceDraft.length || closed || !financial?.revisao;
  $("fm-discount").disabled ||= !!serviceDraft.length;
  $("fm-extra-form").querySelector("[type=submit]").disabled = locked;
  document
    .querySelectorAll("[data-person],[data-remove-extra]")
    .forEach((b) => (b.disabled = locked || !!serviceDraft.length));
  $("fm-busy").hidden = !busy || !globalBusy;
  $("fm-retry").hidden = !pending;
  $("fm-retry-button").disabled = busy;
  $("fm-services-retry").hidden = !pending;
  $("fm-services-retry").disabled = busy;
  $("fm-person-previous").disabled =
    locked || !!serviceDraft.length || index <= 0;
  $("fm-person-next").disabled =
    locked ||
    !!serviceDraft.length ||
    index < 0 ||
    index >= (monthly?.quantidade || 0) - 1;
  $("fm-step").setAttribute("aria-busy", String(busy));
  $("fm-page-root").setAttribute("aria-busy", String(busy));
  $("fm-confirm").innerHTML =
    busy && $("fm-busy-label").textContent === "Confirmando adendo"
      ? "Confirmando adendo…"
      : "Confirmar e próximo " + icon("fa-arrow-right");
  $("fm-pdf").setAttribute("aria-busy", String(busy && !viewed));
}
async function run(label, fn, { global = true } = {}) {
  if (busy) return;
  busy = true;
  globalBusy = global;
  $("fm-busy-label").textContent = label;
  message("");
  update();
  try {
    await fn();
  } catch (e) {
    const text = e.message || "Não foi possível concluir. Tente novamente.";
    message(text);
    if (index >= 0 && !viewed) {
      placeholder("Não foi possível abrir o adendo", text);
      setDocumentState("Adendo indisponível para conferência.");
    }
  } finally {
    busy = false;
    globalBusy = false;
    update();
  }
}
async function read(a, data) {
  const response = await fetch(
    endpoint(a) +
      (endpoint(a).includes("?") ? "&" : "?") +
      new URLSearchParams(data),
    { cache: "no-store" },
  );
  const result = await response.json();
  if (!response.ok || !result.success)
    throw new Error(result.error || "Não foi possível carregar os dados.");
  return result.data;
}
async function mutate(a, data, repeat = false) {
  if (pending && !repeat)
    throw new Error("Conclua a ação anterior usando Tentar novamente.");
  const request = repeat
    ? pending
    : {
        action: a,
        data: {
          ...data,
          idempotency_key: data.idempotency_key || crypto.randomUUID(),
        },
      };
  save(request);
  const response = await fetch(endpoint(request.action), {
    method: "POST",
    headers: { "Content-Type": "application/json", "X-CSRF-Token": csrf },
    body: JSON.stringify(
      ["concluir", "quitar", "pagar"].includes(request.action)
        ? { ...request.data, acao: request.action }
        : request.data,
    ),
  });
  const result = await response.json();
  if (!response.ok || !result.success) {
    // Falhas transitórias podem ter reservado uma operação: conservar a mesma chave.
    if (response.status < 500) save(null);
    throw new Error(
      result.error || "A ação não foi concluída. Tente novamente.",
    );
  }
  save(null);
  return result.data;
}
function summary() {
  if (!monthly) return;
  const m = monthly,
    c = m.contagens;
  const configuracao = m.pendencias_configuracao || [];
  $("fm-config").hidden = !configuracao.length;
  $("fm-config-title").textContent =
    configuracao.length + " cadastro(s) com participação ainda não definida";
  $("fm-config-list").innerHTML = configuracao
    .map(
      (p) =>
        '<li><a href="../Colaborador/?colaborador_id=' +
        encodeURIComponent(p.colaborador_id) +
        '" target="_blank" rel="noopener">' +
        esc(p.nome) +
        " — revisar cadastro</a></li>",
    )
    .join("");
  const isClosed = m.estado === "CONCLUIDO";
  const disponiveis = m.participantes_disponiveis || [];
  $("fm-include").hidden =
    isClosed || !m.ciclo_id || !m.inclusao_disponivel || !disponiveis.length;
  $("fm-roster-note").hidden =
    isClosed ||
    !m.ciclo_id ||
    m.inclusao_disponivel !== false ||
    !disponiveis.length;
  $("fm-include-person").innerHTML =
    '<option value="">Selecione um colaborador</option>' +
    disponiveis
      .map(
        (p) =>
          '<option value="' +
          esc(p.colaborador_id) +
          '">' +
          esc(p.nome) +
          "</option>",
      )
      .join("");
  $("fm-cycle-state").textContent = isClosed ? "Concluído" : "Em andamento";
  $("fm-conclude").hidden = !m.ciclo_id || isClosed;
  $("fm-cycle-financial").textContent = m.estado
    ? (isClosed
        ? "Total fechado " + money(m.total_fechado_centavos)
        : "Parcial " + money(m.parcial_centavos)) +
      " · Previsto " +
      m.previsto_em.split("-").reverse().join("/") +
      " (5º dia útil)"
    : "";
  $("fm-period").textContent = new Date(
    m.competencia + "-02T12:00:00",
  ).toLocaleDateString("pt-BR", { month: "long", year: "numeric" });
  const stat = (n, label, symbol, tone = "") =>
    '<div class="fm-stat ' +
    tone +
    '"><span class="fm-stat-icon">' +
    icon(symbol) +
    "</span><div><strong " +
    paymentNumberAttrs(n, "count") +
    ">" +
    n +
    "</strong><span>" +
    label +
    "</span></div></div>";
  const nextStatsSignature = [
    m.quantidade,
    c.NAO_REVISADO,
    c.ATENCAO,
    c.CONFIRMADO,
  ].join(":");
  if (nextStatsSignature !== statsSignature) {
    $("fm-stats").innerHTML =
      stat(m.quantidade, "aptos", "fa-users") +
      stat(c.NAO_REVISADO, "não revisados", "fa-file-lines", "neutral") +
      stat(c.ATENCAO, "em atenção", "fa-triangle-exclamation", "attention") +
      stat(c.CONFIRMADO, "revisados", "fa-circle-check", "confirmed");
    statsSignature = nextStatsSignature;
    window.pagamentoMotion?.animate($("fm-stats"));
  }
  $("fm-progress").max = m.quantidade || 1;
  $("fm-progress").value = c.CONFIRMADO;
  $("fm-progress-label").textContent =
    c.CONFIRMADO + " de " + m.quantidade + " revisados";
  const progressPercent = m.quantidade
    ? Math.round((c.CONFIRMADO / m.quantidade) * 100)
    : 0;
  if (window.pagamentoMotion)
    window.pagamentoMotion.setNumber(
      $("fm-progress-percent"),
      progressPercent,
      "percent",
    );
  else $("fm-progress-percent").textContent = progressPercent + "%";
  $("fm-context-count").textContent =
    m.quantidade + " colaboradores na competência";
  const started = m.quantidade > 0 && m.colaboradores.every((c) => c.preparado);
  $("fm-start").hidden = started || isClosed;
  $("fm-started").hidden = !started;
  syncCompetence();
}
function overview(finished = false) {
  $("fm-step").hidden = true;
  $("fm-overview").hidden = false;
  $("fm-context").hidden = false;
  $("fm-page-root")?.classList.remove("fm-reviewing");
  index = -1;
  financial = null;
  viewed = false;
  documentMeta = null;
  if (!monthly) return;
  summary();
  const m = monthly,
    c = m.contagens;
  $("fm-list").innerHTML =
    m.colaboradores
      .map(
        (p, i) =>
          '<tr><td><button class="fm-person" data-person="' +
          i +
          '">' +
          esc(p.nome) +
          "</button><small>" +
          esc(types[p.tipo_remuneracao] || "Não definido") +
          "</small>" +
          p.pendencias.map((t) => "<small>" + esc(t) + "</small>").join("") +
          "</td><td>" +
          esc(types[p.tipo_remuneracao] || "Não definido") +
          "</td>" +
          [
            (p.resumo?.VALOR_FIXO || 0) +
              (p.resumo?.ACOMPANHAMENTO_ESPECIAL || 0),
            (p.resumo?.SERVICOS || 0) +
              (p.reconciliacao?.credito_historico_centavos || 0),
            (p.resumo?.BONUS_EXTRAS || 0) +
              (p.resumo?.BONUS_PRODUTIVIDADE || 0),
            Math.abs(p.resumo?.DESCONTO || 0),
          ]
            .map((v) => "<td>" + money(v) + "</td>")
            .join("") +
          "<td>" +
          "<span " +
          paymentNumberAttrs(p.total_centavos, "money") +
          ">" +
          money(p.total_centavos) +
          "</span>" +
          '</td><td><span class="fm-badge fm-status-' +
          p.status +
          '">' +
          statuses[p.status] +
          "</span></td><td>" +
          (p.pagamento_status === "PAGO"
            ? "Pago"
            : p.pagamento_status === "PENDENTE"
              ? "Pendente"
              : "Aguardando fechamento") +
          "</td></tr>",
      )
      .join("") ||
    '<tr><td colspan="9">Nenhum colaborador ativo está marcado como participante. Revise os cadastros e clique em Atualizar cadastros.</td></tr>';
  window.pagamentoMotion?.animate($("fm-list"));
  $("fm-finish").hidden =
    !finished && !(m.quantidade && c.CONFIRMADO === m.quantidade);
  $("fm-finish").innerHTML =
    "<h2>" +
    (c.CONFIRMADO === m.quantidade
      ? "Fechamento revisado."
      : "Revisão desta sequência concluída.") +
    "</h2><p>" +
    c.CONFIRMADO +
    " confirmados · " +
    c.ATENCAO +
    " com atenção · " +
    c.NAO_REVISADO +
    " não revisados</p>";
  update();
}
async function clearPdf() {
  viewed = false;
  documentMeta = null;
  $("fm-download").hidden = true;
  if (blobURL) URL.revokeObjectURL(blobURL);
  blobURL = null;
  await pdf.close();
  placeholder(
    "Adendo para conferência",
    "Carregando os valores do colaborador.",
  );
  update();
}
function breakdown(r) {
  const row = (label, value, help = "", className = "") =>
    '<div class="' +
    className +
    '"><dt>' +
    esc(label) +
    (help ? "<small>" + esc(help) + "</small>" : "") +
    "</dt><dd " +
    paymentNumberAttrs(value, "money") +
    ">" +
    money(value) +
    "</dd></div>";
  const bonus = r.bonus_produtividade;
  const acompanhamentoFixo =
    r.acompanhamento_especial.regra === "MENSAL_ACOMPANHAMENTO_VALOR_CADASTRO";
  const finalizador = r.financeiro_servicos.itens_analisados.some(
    (item) => Number(item.descricao.funcao_id) === 4,
  );
  $("fm-breakdown").innerHTML =
    row(
      "Serviços / variável",
      r.resumo.SERVICOS,
      r.tipo_remuneracao === "FIXO" ? "Produção apenas para consulta" : "",
    ) +
    (finalizador
      ? row(
          "Bônus produtividade",
          r.resumo.BONUS_PRODUTIVIDADE,
          "Finalização R0: " +
            bonus.quantidade_finalizacao_r0 +
            " imagens · Meta: 20" +
            (bonus.quantidade_bonus
              ? " · +" +
                bonus.quantidade_bonus +
                " " +
                (bonus.quantidade_bonus === 1 ? "imagem" : "imagens") +
                " · " +
                money(bonus.tarifa_centavos)
              : ""),
        )
      : "") +
    (acompanhamentoFixo
      ? ""
      : row(
          "Valor fixo",
          r.tipo_remuneracao === "VARIAVEL" ? null : r.resumo.VALOR_FIXO,
        )) +
    (r.acompanhamento_especial.aplicavel
      ? row("Acompanhamento", r.resumo.ACOMPANHAMENTO_ESPECIAL)
      : "") +
    row("Extra manual", r.resumo.BONUS_EXTRAS) +
    row("Desconto", Math.abs(r.resumo.DESCONTO || 0), r.desconto?.motivo || "");
  $("fm-total-summary").innerHTML = row(
    "Total",
    r.total_final_centavos,
    "",
    "fm-total",
  );
  window.pagamentoMotion?.animate($("fm-breakdown"));
  window.pagamentoMotion?.animate($("fm-total-summary"));
  $("fm-attention").innerHTML = r.pendencias
    .map((p) => '<div class="fm-notice"><p>' + esc(p.mensagem) + "</p></div>")
    .join("");
  renderServices(r);
  $("fm-extras").innerHTML = r.extras.itens
    .map(
      (e, i) =>
        "<p>" +
        esc(e.categoria) +
        " · " +
        money(e.valor_centavos) +
        ' <button type="button" class="btn btn-secondary" data-remove-extra="' +
        i +
        '" aria-label="Remover ' +
        esc(e.categoria) +
        '">Remover</button></p>',
    )
    .join("");
}
let serviceTab = "pending";
function renderServices(r = financial?.revisao) {
  if (!r) return;
  $("fm-service-draft").hidden = !serviceDraft.length;
  $("fm-service-draft-list").innerHTML = serviceDraft
    .map(
      (d, i) =>
        `<li><span>${esc(d.label)}</span> <button type="button" class="btn btn-secondary" data-draft-remove="${i}">Remover</button></li>`,
    )
    .join("");
  const all = r.financeiro_servicos.itens_analisados;
  // Também protege a consulta de revisões antigas; o cálculo novo já exclui a etapa parcial.
  const items = all.filter(
    (s) =>
      s.descricao.funcao !== "Finalização parcial" &&
      (s.elegibilidade.elegivel || s.situacao === "RETIRADO"),
  );
  const hasPayment = (s) =>
    s.situacao === "QUITADO" ||
    s.pagamentos.some((p) => BigInt(p.valor_centavos) > 0n);
  const groups = {
    pending: items.filter(
      (s) => s.situacao !== "QUITADO" && s.situacao !== "RETIRADO",
    ),
    paid: items.filter(hasPayment),
    removed: items.filter((s) => s.situacao === "RETIRADO"),
  };
  for (const [key, list] of Object.entries(groups))
    $("fm-services-" + key + "-count").textContent = list.length;
  document
    .querySelectorAll("[data-service-tab]")
    .forEach((b) =>
      b.setAttribute(
        "aria-pressed",
        String(b.dataset.serviceTab === serviceTab),
      ),
    );
  $("fm-services-scope").textContent =
    `${monthly.colaboradores[index].nome} · ${monthly.competencia}`;
  const fixed = r.tipo_remuneracao === "FIXO";
  $("fm-services-summary").textContent =
    serviceTab === "paid"
      ? "Pagamentos registrados para este colaborador. Tarefas com pagamento parcial também aparecem em Pendentes, com o saldo restante."
      : serviceTab === "removed"
        ? "Tarefas retiradas deste fechamento. Você pode restaurá-las enquanto o fechamento estiver aberto."
        : fixed
          ? "Produção coberta pela remuneração fixa. Estes serviços não acrescentam valor ao fixo."
          : "O saldo da coluna A pagar agora será incluído no fechamento. Tarefas sem valor não acrescentam cobrança.";
  const functions = new Map();
  items.forEach((s) => {
    const id = Number(s.descricao.funcao_id);
    if (id > 0)
      functions.set(id, id === 4 ? "Finalização" : s.descricao.funcao);
  });
  const selected = $("fm-service-function").value;
  $("fm-service-function").innerHTML = [...functions]
    .sort((a, b) => String(a[1]).localeCompare(String(b[1]), "pt-BR"))
    .map(([id, name]) => `<option value="${id}">${esc(name)}</option>`)
    .join("");
  if (functions.has(Number(selected)))
    $("fm-service-function").value = selected;
  $("fm-service-function").closest(".fm-service-batch").hidden =
    monthly.estado === "CONCLUIDO" || serviceTab === "paid";
  const compare = (a, b) =>
    String(a || "").localeCompare(String(b || ""), "pt-BR", {
      numeric: true,
      sensitivity: "base",
    });
  $("fm-services").innerHTML =
    groups[serviceTab]
      .sort(
        (a, b) =>
          compare(a.descricao.obra_nome, b.descricao.obra_nome) ||
          compare(a.descricao.imagem, b.descricao.imagem) ||
          compare(a.descricao.funcao, b.descricao.funcao),
      )
      .map((s) => {
        const n = all.indexOf(s),
          locked = busy || !!pending,
          open = monthly.estado !== "CONCLUIDO";
        const ph = s.planta_humanizada;
        const onlyTariff = s.divergencias.every((p) =>
          ["PLANTA_TARIFA_DIVERGENTE", "PLANTA_TIPO_PENDENTE"].includes(
            p.codigo,
          ),
        );
        const canEdit =
          open &&
          serviceTab !== "paid" &&
          s.situacao !== "RETIRADO" &&
          s.situacao !== "QUITADO" &&
          (s.saldo_centavos !== null || (ph && onlyTariff)) &&
          onlyTariff &&
          !fixed;
        const canRemove =
          open &&
          serviceTab !== "paid" &&
          (s.situacao === "RETIRADO" ||
            (!s.pagamentos.length &&
              s.flag_legada_pagamento !== 1 &&
              s.situacao !== "QUITADO"));
        const btn = (attr, text) =>
          `<button type="button" class="btn btn-secondary" ${attr}="${n}" ${locked ? "disabled" : ""}>${text}</button>`;
        const history = s.pagamentos.length
          ? `<details class="fm-service-history"><summary>Ver lançamentos (${s.pagamentos.length})</summary><ul>${s.pagamentos.map((p) => `<li><strong>${money(p.valor_centavos)}</strong> · ${esc(p.competencia_pagamento || "Competência não informada")}<small>${esc(p.observacao || "Pagamento registrado")}</small></li>`).join("")}</ul></details>`
          : "";
        const note = s.ajuste_valor
          ? `<small>Valor ajustado: ${esc(s.ajuste_valor.motivo)}</small>`
          : s.retirada
            ? `<small>${esc(s.retirada.motivo)}</small>`
            : "";
        const errors = s.divergencias
          .map(
            (p) =>
              `<small class="fm-service-warning">${esc(p.mensagem)}</small>`,
          )
          .join("");
        const tariff = ph
          ? `<div class="fm-plant-tariff"><small>${ph.categoria_confirmada ? "Tipo" : "Tipo sugerido"}: ${esc(ph.categoria_nome || "Selecione o tipo da planta")}</small><small>Tarifa padrão: <strong>${money(ph.esperado_centavos)}</strong></small>${ph.divergente ? `<strong class="fm-service-warning">${ph.historico ? "Divergência no histórico" : "Divergente"}</strong>` : ph.excecao ? "<small>Exceção justificada</small>" : "<small>Conforme a tabela</small>"}</div>`
          : "";
        return `<tr><td data-label="Tarefa"><small>${esc(s.descricao.obra_nome || "")}</small><strong>${esc(s.descricao.imagem || "Serviço")}</strong><small>${esc(s.descricao.funcao)}${s.descricao.tipo_animacao ? " · " + esc(s.descricao.tipo_animacao) : ""}</small>${tariff}${history}</td><td data-label="Valor total">${money(s.base_centavos)}${s.ajuste_valor ? `<small>Original: ${money(s.base_original_centavos)}</small>` : ""}</td><td data-label="Já pago">${money(s.pago_centavos)}</td><td data-label="A pagar agora">${money(fixed || s.situacao === "RETIRADO" ? "0" : s.saldo_centavos)}</td><td data-label="Situação / ações"><span class="fm-badge">${esc(serviceStates[s.situacao] || "Conferir")}</span>${note}${errors}<div class="fm-service-row-actions">${canEdit && ph ? btn("data-service-tariff", "Aplicar tarifa padrão") : ""}${canEdit ? btn("data-service-edit", ph ? "Usar valor excepcional" : "Alterar valor") : ""}${open && s.ajuste_valor ? btn("data-service-reset", "Restaurar valor") : ""}${canRemove ? btn("data-service-index", s.situacao === "RETIRADO" ? "Restaurar tarefa" : "Retirar") : ""}</div></td></tr>`;
      })
      .join("") ||
    '<tr><td colspan="5">Nenhum serviço nesta situação.</td></tr>';
}
$("fm-service-details").onclick = () => {
  serviceTab = "pending";
  if (!pending) message("");
  renderServices();
  $("fm-services-dialog").showModal();
};
$("fm-services-close").onclick = () => $("fm-services-dialog").close();
$("fm-services-dialog").addEventListener("close", () => {
  if (serviceDraft.length)
    message(
      "Há alterações de serviços pendentes. Abra os detalhes para salvar ou descartar.",
    );
});
document.querySelectorAll("[data-service-tab]").forEach(
  (b) =>
    (b.onclick = () => {
      serviceTab = b.dataset.serviceTab;
      renderServices();
    }),
);

async function showDocument(doc) {
  documentMeta = doc;
  viewed = false;
  const response = await fetch(endpoint("visualizar"), {
    method: "POST",
    headers: { "Content-Type": "application/json", "X-CSRF-Token": csrf },
    body: JSON.stringify({
      ...context(),
      document_id: doc.document_id,
      idempotency_key: crypto.randomUUID(),
    }),
  });
  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || "Não foi possível abrir o adendo.");
  }
  if (
    response.headers.get("X-Documento-Id") !== String(doc.document_id) ||
    response.headers.get("X-Revisao-Id") !== String(doc.revision_id) ||
    response.headers.get("X-Pdf-Hash") !== doc.pdf_hash
  )
    throw new Error(
      "O adendo recebido mudou. Atualize os valores para revisar novamente.",
    );
  const blob = await response.blob();
  if (!(await pdf.open(blob)))
    throw new Error("Não foi possível exibir o adendo.");
  blobURL = URL.createObjectURL(blob);
  $("fm-download").href = blobURL;
  $("fm-download").download = documentFileName();
  $("fm-download").hidden = false;
  viewed = true;
  setDocumentState(
    doc.estado === "CONFIRMADO"
      ? "Adendo confirmado."
      : "Confira o adendo antes de confirmar.",
  );
  $("fm-pdf-placeholder").hidden = true;
}
async function loadStep() {
  await clearPdf();
  setDocumentState("Carregando valores…");
  financial = await read("obter", context());
  const r = financial.revisao;
  if (!r || r.monthly_rule_version !== "fechamento_mensal_v1") {
    $("fm-breakdown").innerHTML = "";
    $("fm-total-summary").innerHTML = "";
    $("fm-services").innerHTML = "";
    $("fm-extras").innerHTML = "";
    $("fm-attention").innerHTML =
      '<p class="fm-notice">Inicie o fechamento ou atualize os valores para preparar este colaborador.</p>';
    setDocumentState("Adendo ainda não preparado.");
    placeholder(
      "Adendo ainda não preparado",
      "Inicie o fechamento ou atualize os valores deste colaborador.",
    );
    return;
  }
  $("fm-type").textContent = types[r.tipo_remuneracao] || "Não definido";
  breakdown(r);
  if (!r.total_final_determinado) {
    setDocumentState(
      "Resolva os itens com atenção e atualize os valores para abrir o adendo.",
    );
    placeholder(
      "Há itens para conferir",
      "Resolva as pendências indicadas ao lado e atualize os valores para preparar o PDF.",
    );
    return;
  }
  setDocumentState("Preparando PDF para conferência…");
  pdfLoading(
    "Preparando adendo",
    "Gerando ou carregando o PDF para conferência.",
  );
  const listing = await read("listar", context());
  let doc = listing.documentos.findLast(
    (d) =>
      d.revision_id === r.id &&
      d.estado &&
      (d.estado === "CONFIRMADO" ||
        d.modelo_version === "adendo_revisao_documental_v4"),
  );
  if (!doc) {
    const op = listing.operacoes_pendentes.find((o) => o.tipo === "GERAR");
    doc = op
      ? await mutate("recuperar", {
          ...context(),
          operation_id: Number(op.id),
          idempotency_key: op.chave,
        })
      : await mutate("gerar", {
          ...context(),
          fechamento_id: r.fechamento_id,
          revision_id: r.id,
        });
    if (
      doc.revision_id !== r.id ||
      (doc.estado !== "CONFIRMADO" &&
        doc.modelo_version !== "adendo_revisao_documental_v4")
    ) {
      doc = await mutate("gerar", {
        ...context(),
        fechamento_id: r.fechamento_id,
        revision_id: r.id,
      });
    }
  }
  await showDocument(doc);
}
async function enter(i) {
  globalBusy = false;
  index = i;
  const c = monthly.colaboradores[i];
  financial = null;
  $("fm-breakdown").innerHTML = "";
  $("fm-services").innerHTML = "";
  $("fm-extras").innerHTML = "";
  $("fm-attention").innerHTML = "";
  $("fm-total-summary").innerHTML =
    '<div class="fm-total"><dt>Total</dt><dd>—</dd></div>';
  $("fm-step").hidden = false;
  $("fm-overview").hidden = true;
  $("fm-context").hidden = false;
  document.querySelector(".fm-page").classList.add("fm-reviewing");
  summary();
  $("fm-service-details").open = false;
  $("fm-name").textContent = c.nome;
  $("fm-type").textContent = types[c.tipo_remuneracao] || "Não definido";
  $("fm-edit").href =
    "../Colaborador/?colaborador_id=" + encodeURIComponent(c.colaborador_id);
  $("fm-position").textContent = i + 1 + " de " + monthly.quantidade;
  $("fm-avatar").textContent = c.nome
    .trim()
    .split(/\s+/)
    .map((n) => n[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();
  $("fm-person-status").textContent = statuses[c.status];
  $("fm-person-status").className = "fm-badge fm-status-" + c.status;
  $("fm-step").classList.remove("fm-enter");
  requestAnimationFrame(() => $("fm-step").classList.add("fm-enter"));
  await loadStep();
}
async function next() {
  while (++queuePosition < queue.length) {
    const i = monthly.colaboradores.findIndex(
      (c) => c.colaborador_id === queue[queuePosition],
    );
    if (i >= 0 && monthly.colaboradores[i].status !== "CONFIRMADO") {
      await enter(i);
      return;
    }
  }
  await clearPdf();
  monthly = await read("mensal", { competencia: monthly.competencia });
  overview(true);
}
function newQueue(i = null) {
  queue = monthly.colaboradores
    .filter((c) => c.status !== "CONFIRMADO")
    .map((c) => c.colaborador_id);
  if (i !== null) {
    const id = monthly.colaboradores[i].colaborador_id;
    const remaining = [
      ...monthly.colaboradores.slice(i + 1),
      ...monthly.colaboradores.slice(0, i),
    ].filter((c) => c.status !== "CONFIRMADO");
    queue = [id, ...remaining.map((c) => c.colaborador_id)];
  }
  queuePosition = -1;
}
$("fm-start").onclick = () =>
  run("Preparando colaboradores", async () => {
    monthly = await mutate("iniciar", { competencia: ref() });
    overview();
  });
$("fm-refresh").onclick = () =>
  run("Atualizando cadastros", async () => {
    monthly = await read("mensal", { competencia: ref() });
    overview();
  });
$("fm-include").onclick = () => {
  $("fm-include-person").value = "";
  $("fm-include-dialog").showModal();
};
$("fm-include-close").onclick = () => $("fm-include-dialog").close();
$("fm-include-form").onsubmit = (e) => {
  e.preventDefault();
  const collaborator = Number($("fm-include-person").value);
  if (!Number.isSafeInteger(collaborator) || collaborator < 1) return;
  run("Incluindo colaborador no fechamento", async () => {
    const name =
      $("fm-include-person").selectedOptions[0]?.textContent || "Colaborador";
    monthly = await mutate("incluir", {
      competencia: ref(),
      colaborador_id: collaborator,
    });
    $("fm-include-dialog").close();
    overview();
    message(name + " incluído(a) no fechamento.");
  });
};
$("fm-month").onchange = () =>
  run("Carregando competência", async () => {
    monthly = await read("mensal", { competencia: ref() });
    overview();
  });
for (const id of ["fm-select-month", "fm-select-year"])
  $(id).onchange = () => {
    $("fm-month").value =
      $("fm-select-year").value + "-" + $("fm-select-month").value;
    run("Carregando competência", async () => {
      await clearPdf();
      monthly = await read("mensal", { competencia: ref() });
      overview();
    });
  };
for (const [id, direction] of [
  ["fm-person-previous", -1],
  ["fm-person-next", 1],
])
  $(id).onclick = () =>
    run(
      "Abrindo colaborador",
      async () => {
        const i = index + direction;
        if (i < 0 || i >= monthly.quantidade) return;
        newQueue(i);
        queuePosition = 0;
        await enter(i);
      },
      { global: false },
    );
$("fm-review").onclick = () =>
  run(
    "Abrindo adendo",
    async () => {
      newQueue();
      await next();
    },
    { global: false },
  );
$("fm-list").onclick = (e) => {
  const b = e.target.closest("[data-person]");
  if (b)
    run(
      "Abrindo colaborador",
      async () => {
        const i = Number(b.dataset.person);
        newQueue(i);
        if (monthly.colaboradores[i].status === "CONFIRMADO") await enter(i);
        else await next();
      },
      { global: false },
    );
};
$("fm-skip").onclick = () =>
  run("Abrindo próximo colaborador", next, { global: false });
$("fm-back").onclick = () =>
  run("Carregando fechamento", async () => {
    await clearPdf();
    monthly = await read("mensal", { competencia: monthly.competencia });
    overview();
  });
$("fm-confirm").onclick = () =>
  run("Confirmando adendo", async () => {
    if (!viewed || !documentMeta) return;
    await mutate("confirmar", {
      ...context(),
      document_id: documentMeta.document_id,
      revision_id: documentMeta.revision_id,
      pdf_hash: documentMeta.pdf_hash,
    });
    monthly = await read("mensal", { competencia: monthly.competencia });
    await next();
  });
$("fm-recalculate").onclick = () =>
  run("Atualizando valores", async () => {
    viewed = false;
    await mutate("preparar", {
      ...context(),
      expected_version: financial.latest_version,
    });
    monthly = await read("mensal", { competencia: monthly.competencia });
    await enter(index);
  });
let discountMode = false;
$("fm-discount").onclick = () => {
  discountMode = true;
  $("fm-extra-form").reset();
  $("fm-extra-title").textContent = "Desconto manual";
  $("fm-extra-form").querySelector("[type=submit]").textContent =
    "Salvar desconto";
  $("fm-extra-description").hidden = true;
  $("fm-extra-form").elements.categoria.required = false;
  $("fm-extra-form").elements.valor.value = decimal(
    financial?.revisao?.desconto?.valor_centavos || 0,
  );
  $("fm-extra-scope").textContent =
    monthly.colaboradores[index].nome + " · " + monthly.competencia;
  $("fm-extra-dialog").showModal();
};
$("fm-conclude").onclick = () =>
  run("Concluindo fechamento", async () => {
    await mutate("concluir", { competencia: monthly.competencia });
    monthly = await read("mensal", { competencia: monthly.competencia });
    overview();
  });
$("fm-extra").onclick = () => {
  discountMode = false;
  $("fm-extra-title").textContent = "Extra manual";
  $("fm-extra-form").querySelector("[type=submit]").textContent =
    "Salvar extra";
  $("fm-extra-description").hidden = false;
  $("fm-extra-form").elements.categoria.required = true;
  $("fm-extra-form").reset();
  $("fm-extra-scope").textContent =
    monthly.colaboradores[index].nome + " · " + monthly.competencia;
  $("fm-extra-dialog").showModal();
};
$("fm-extra-close").onclick = () => $("fm-extra-dialog").close();
let serviceDecision = null,
  servicePlant = null;
function serviceDialog(estado, alvo, s = null) {
  if (busy || pending || monthly.estado === "CONCLUIDO") return;
  serviceDecision = { estado, alvo };
  if (alvo === "ITEM") serviceDecision.identidade = s.identidade;
  else serviceDecision.funcao_id = Number($("fm-service-function").value);
  const target =
    alvo === "ITEM"
      ? `${s.descricao.imagem} · ${s.descricao.funcao}`
      : $("fm-service-function").selectedOptions[0]?.textContent;
  $("fm-service-form").reset();
  const edit = estado === "ALTERAR_VALOR",
    reset = estado === "RESTAURAR_VALOR",
    standard = estado === "APLICAR_TARIFA";
  servicePlant = edit || standard ? s?.planta_humanizada : null;
  $("fm-service-plant-label").hidden = !servicePlant;
  $("fm-service-form").elements.categoria_planta.required = !!servicePlant;
  $("fm-service-form").elements.categoria_planta.innerHTML =
    '<option value="">Selecione o tipo da planta</option>' +
    Object.entries(servicePlant?.categorias || {})
      .map(
        ([code, c]) =>
          `<option value="${esc(code)}">${esc(c.nome)} — ${money(c.valor_centavos)}</option>`,
      )
      .join("");
  $("fm-service-form").elements.categoria_planta.value =
    servicePlant?.categoria || servicePlant?.categoria_sugerida || "";
  $("fm-service-reason-label").hidden = standard;
  $("fm-service-form").elements.motivo.required = !standard;
  $("fm-service-standard-note").hidden = !standard;
  $("fm-service-value-label").hidden = !edit;
  $("fm-service-form").elements.valor.required = edit;
  $("fm-service-form").elements.valor.value = s ? decimal(s.base_centavos) : "";
  $("fm-service-paid").hidden = !edit;
  $("fm-service-paid").textContent = s
    ? `Já pago: ${money(s.pago_centavos)}. O novo valor total não pode ser inferior ao já pago.`
    : "";
  $("fm-service-error").hidden = true;
  $("fm-service-title").textContent = standard
    ? "Aplicar tarifa padrão"
    : edit
      ? servicePlant
        ? "Valor excepcional da planta"
        : "Alterar valor da tarefa"
      : reset
        ? "Restaurar valor original"
        : `${estado === "RETIRAR" ? "Retirar" : "Restaurar"} ${alvo === "ITEM" ? "tarefa" : "função"}`;
  $("fm-service-target").textContent =
    `${monthly.colaboradores[index].nome} · ${monthly.competencia} · ${target}`;
  $("fm-service-submit").textContent = "Adicionar alteração";
  $("fm-service-dialog").showModal();
}
$("fm-services").onclick = (e) => {
  const button = e.target.closest(
    "[data-service-index],[data-service-edit],[data-service-reset],[data-service-tariff]",
  );
  if (!button) return;
  const s =
    financial.revisao.financeiro_servicos.itens_analisados[
      Number(
        button.dataset.serviceIndex ??
          button.dataset.serviceEdit ??
          button.dataset.serviceReset ??
          button.dataset.serviceTariff,
      )
    ];
  serviceDialog(
    button.hasAttribute("data-service-tariff")
      ? "APLICAR_TARIFA"
      : button.hasAttribute("data-service-edit")
        ? "ALTERAR_VALOR"
        : button.hasAttribute("data-service-reset")
          ? "RESTAURAR_VALOR"
          : s.situacao === "RETIRADO"
            ? "RESTAURAR"
            : "RETIRAR",
    "ITEM",
    s,
  );
};
$("fm-withdraw-function").onclick = () => serviceDialog("RETIRAR", "FUNCAO");
$("fm-restore-function").onclick = () => serviceDialog("RESTAURAR", "FUNCAO");
$("fm-service-close").onclick = () => $("fm-service-dialog").close();
$("fm-service-form").onsubmit = (e) => {
  e.preventDefault();
  const input = {
    ...serviceDecision,
    motivo: new FormData(e.target).get("motivo"),
  };
  if (serviceDecision.estado === "ALTERAR_VALOR")
    input.valor = new FormData(e.target).get("valor");
  if (servicePlant)
    input.categoria_planta = new FormData(e.target).get("categoria_planta");
  if (serviceDecision.estado === "APLICAR_TARIFA") delete input.motivo;
  if (serviceDraft.length >= 100) {
    message("O limite é de 100 alterações por vez. Salve antes de continuar.");
    return;
  }
  const category = servicePlant?.categorias?.[input.categoria_planta];
  const label = `${$("fm-service-target").textContent} — ${$("fm-service-title").textContent}${category ? `: ${category.nome}` : ""}${input.valor ? ` · R$ ${input.valor}` : category && input.estado === "APLICAR_TARIFA" ? ` · ${money(category.valor_centavos)}` : ""}${input.motivo ? ` · ${input.motivo}` : ""}`;
  serviceDraft.push({ input, label });
  $("fm-service-dialog").close();
  $("fm-services-close").focus({ preventScroll: true });
  $("fm-services-dialog").scrollTop = 0;
  renderServices();
  update();
};
$("fm-service-draft-list").onclick = (e) => {
  const button = e.target.closest("[data-draft-remove]");
  if (!button || busy || pending) return;
  serviceDraft.splice(Number(button.dataset.draftRemove), 1);
  renderServices();
  update();
};
$("fm-service-draft-discard").onclick = () => {
  if (busy || pending) return;
  serviceDraft = [];
  message("");
  renderServices();
  update();
};
$("fm-service-draft-save").onclick = () =>
  run(
    "Salvando alterações",
    async () => {
      if (!serviceDraft.length) return;
      await mutate("decidir", {
        ...context(),
        expected_version: financial.latest_version,
        tipo: "SERVICOS",
        input: { estado: "LOTE", operacoes: serviceDraft.map((d) => d.input) },
      });
      serviceDraft = [];
      monthly = await read("mensal", { competencia: monthly.competencia });
      await enter(index);
      renderServices();
    },
    { global: false },
  );
window.addEventListener("beforeunload", (e) => {
  if (serviceDraft.length) {
    e.preventDefault();
    e.returnValue = "";
  }
});

function extraItems() {
  return financial.revisao.extras.itens.map((e) => ({
    categoria: e.categoria,
    referencia: e.referencia,
    valor: decimal(e.valor_centavos),
  }));
}
async function saveExtras(items, motivo) {
  viewed = false;
  update();
  await mutate("decidir", {
    ...context(),
    expected_version: financial.latest_version,
    tipo: "BONUS",
    input: {
      estado: items.length ? "DEFINIDO" : "SEM_BONUS",
      motivo,
      itens: items,
    },
  });
  $("fm-extra-dialog").close();
  monthly = await read("mensal", { competencia: monthly.competencia });
  await enter(index);
}
$("fm-extra-form").onsubmit = (e) => {
  e.preventDefault();
  run("Salvando extra", async () => {
    const f = new FormData(e.target);
    if (discountMode) {
      await mutate("decidir", {
        ...context(),
        expected_version: financial.latest_version,
        tipo: "DESCONTO",
        input: {
          estado: "DEFINIDO",
          motivo: f.get("motivo"),
          valor: f.get("valor"),
        },
      });
      $("fm-extra-dialog").close();
      monthly = await read("mensal", { competencia: monthly.competencia });
      await enter(index);
      return;
    }
    const items = extraItems();
    items.push({
      categoria: f.get("categoria"),
      valor: f.get("valor"),
      referencia: "extra:" + crypto.randomUUID(),
    });
    await saveExtras(items, f.get("motivo"));
  });
};
$("fm-extras").onclick = (e) => {
  const b = e.target.closest("[data-remove-extra]");
  if (b)
    run("Removendo extra", async () => {
      const items = extraItems();
      items.splice(Number(b.dataset.removeExtra), 1);
      await saveExtras(
        items,
        "Remoção de extra durante revisão do fechamento mensal.",
      );
    });
};
$("fm-retry-button").onclick = () =>
  run("Concluindo ação anterior", async () => {
    const request = pending;
    await mutate(request.action, request.data, true);
    if (request.action === "decidir" && request.data.input?.estado === "LOTE")
      serviceDraft = [];
    $("fm-month").value = request.data.competencia;
    monthly = await read("mensal", { competencia: request.data.competencia });
    if (request.action === "incluir") $("fm-include-dialog").close();
    if (request.action === "confirmar") {
      if (index >= 0) await next();
      else overview();
    } else if (
      index >= 0 &&
      request.data.colaborador_id === context().colaborador_id
    )
      await loadStep();
    else overview();
  });
$("fm-services-retry").onclick = () => $("fm-retry-button").click();
const documentCard = $("fm-document"),
  fullButton = $("fm-fullscreen");
// Em telas menores, o resumo vem antes do PDF e todas as ações ficam depois dele.
const stackedReview = window.matchMedia("(max-width:1179px)");
function placeReviewActions() {
  const footer = document.querySelector(".fm-action-footer"),
    secondary = document.querySelector(".fm-review-layout .fc-actions");
  if (stackedReview.matches) {
    document.querySelector(".fm-financial").append($("fm-total-summary"));
    footer.insertBefore(
      secondary,
      document.querySelector(".fm-review-actions"),
    );
  } else {
    footer.prepend($("fm-total-summary"));
    document.querySelector(".fm-financial-scroll").append(secondary);
  }
}
stackedReview.addEventListener("change", placeReviewActions);
placeReviewActions();
function fullState() {
  const expanded =
    document.fullscreenElement === documentCard ||
    documentCard.classList.contains("is-expanded");
  fullButton.setAttribute("aria-pressed", String(expanded));
  fullButton.setAttribute(
    "aria-label",
    expanded ? "Sair da tela cheia" : "Tela cheia",
  );
  fullButton.title = expanded ? "Sair da tela cheia" : "Tela cheia";
  fullButton.innerHTML = icon(expanded ? "fa-compress" : "fa-expand");
}
fullButton.onclick = async () => {
  if (document.fullscreenElement === documentCard)
    await document.exitFullscreen();
  else if (documentCard.classList.contains("is-expanded"))
    documentCard.classList.remove("is-expanded");
  else {
    try {
      await documentCard.requestFullscreen();
    } catch {
      documentCard.classList.add("is-expanded");
    }
  }
  fullState();
};
document.addEventListener("fullscreenchange", fullState);
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape" && documentCard.classList.contains("is-expanded")) {
    documentCard.classList.remove("is-expanded");
    fullState();
    fullButton.focus();
  }
});
syncCompetence();
run("Carregando fechamento", async () => {
  if (pending) $("fm-month").value = pending.data.competencia;
  monthly = await read("mensal", { competencia: ref() });
  overview();
});
