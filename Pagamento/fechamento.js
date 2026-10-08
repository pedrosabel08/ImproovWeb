// A UI formata centavos e solicita atos. Não seleciona direitos, calcula saldo ou soma fechamento.
import { createPdfView } from "./fechamento-pdf.js?v=20261006-continuo";
const $ = (id) => document.getElementById(id);
const esc = (value) =>
  String(value ?? "").replace(
    /[&<>"']/g,
    (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        c
      ],
  );
export const money = (value) => {
  if (value === null || value === undefined) return "Pendente";
  const text = String(value);
  if (!/^-?\d+$/.test(text)) return "Indisponível";
  const negative = text.startsWith("-");
  const digits = text.replace(/^-/, "").padStart(3, "0");
  return (
    (negative ? "-R$ " : "R$ ") +
    digits.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, ".") +
    "," +
    digits.slice(-2)
  );
};
const inputMoney = (value) =>
  value == null
    ? ""
    : String(value).padStart(3, "0").slice(0, -2) +
      "," +
      String(value).padStart(3, "0").slice(-2);
const time = (value) =>
  value
    ? new Date(
        value.includes("T") ? value : value.replace(" ", "T") + "Z",
      ).toLocaleString("pt-BR", { timeZone: "America/Sao_Paulo" })
    : "—";
const names = {
  PRONTO: "Pronto",
  PENDENTE: "Pendente",
  PENDENTE_BONUS: "Pendente de bônus",
  PENDENTE_FIXO: "Pendente de fixo",
  PENDENTE_EXTRAS: "Pendente de extras",
  DIVERGENCIA_FINANCEIRA: "Divergência financeira",
  CONFIRMADO: "Confirmado",
  PREVIEW: "Preview",
  RESERVADA: "Operação incompleta",
  DEVIDO: "Devido",
  QUITADO: "Quitado",
  SEM_VALOR_DEVIDO: "Sem valor devido",
  NAO_ELEGIVEL: "Não elegível",
  PENDENCIA: "Pendência",
  LIQUIDACAO_INDETERMINADA: "Pendente de reconciliação",
  DETERMINADA_POR_EVIDENCIA: "Determinada por evidência",
  NAO_APLICAVEL_VALOR_ZERO: "Não aplicável: valor zero",
  SEM_BONUS: "Sem bônus",
  DEFINIDO: "Definido",
};
const pendingNames = {
  BONUS_PENDENTE: "Defina se haverá bônus nesta competência.",
  FIXO_NAO_DEFINIDO: "O valor fixo ainda não foi definido.",
  FIXO_LIQUIDACAO_INDETERMINADA:
    "A situação do valor fixo precisa ser conferida.",
  PAGAMENTO_SEM_LEDGER:
    "Existe indicação de pagamento sem lançamento compatível.",
  PAGO_ACIMA_DO_DEVIDO: "Os pagamentos registrados superam o valor devido.",
  FIXO_PAGO_ACIMA_DO_DEVIDO: "Os pagamentos comprovados superam o valor fixo.",
  ANIMACAO_LEGADA_AMBIGUA:
    "O pagamento de animação precisa de conferência na origem.",
};
const components = {
  SERVICOS: "Serviços",
  VALOR_FIXO: "Valor fixo",
  ACOMPANHAMENTO_ESPECIAL: "Acompanhamento especial",
  BONUS_EXTRAS: "Extras",
};
const state = {
  financial: null,
  revisions: [],
  documents: [],
  operations: [],
  doc: null,
  viewed: null,
  busy: false,
  sequence: 0,
};
const csrf = document.querySelector('meta[name="pagamento-csrf"]').content;
const storageKey = "pagamento-fechamento-request:" + csrf.slice(0, 16);
let pending = null,
  blobURL = null,
  decision = null;
const busyHome = $("fc-busy").parentNode;
const busyNext = $("fc-busy").nextSibling;
try {
  pending = JSON.parse(sessionStorage.getItem(storageKey) || "null");
} catch {
  /* armazenamento indisponível não cria outra ação */
}
window.thinkingOrbs();
const pdfView = createPdfView(
  $("fc-pdf"),
  $("fc-pdf-previous"),
  $("fc-pdf-next"),
  $("fc-pdf-page"),
  $("fc-pdf-zoom-out"),
  $("fc-pdf-zoom-in"),
  $("fc-pdf-zoom"),
);

const context = () => ({
  colaborador_id: Number($("fc-colaborador").value),
  competencia: $("fc-competencia").value,
});
const validContext = () =>
  Boolean(
    $("fc-colaborador").value &&
    /^20\d\d-(0[1-9]|1[0-2])$/.test($("fc-competencia").value),
  );
const sameContext = (c) =>
  c.colaborador_id === context().colaborador_id &&
  c.competencia === context().competencia;
const message = (text) => {
  $("fc-message").textContent = text;
  $("fc-message").hidden = !text;
};
const savePending = (value) => {
  pending = value;
  try {
    value
      ? sessionStorage.setItem(storageKey, JSON.stringify(value))
      : sessionStorage.removeItem(storageKey);
  } catch {
    /* a chave também permanece na memória */
  }
  updateButtons();
};
const key = () => crypto.randomUUID();
const endpoint = (action) =>
  "api/" +
  (["preparar", "obter", "decidir", "revisoes"].includes(action)
    ? "fechamento/"
    : "documento/") +
  action +
  ".php";
const decisionAuthor = (r) =>
  r
    ? "Decisão de usuário #" +
      r.autor_id +
      " em " +
      time(r.registrado_em) +
      ". " +
      (r.motivo || "")
    : "Nenhuma decisão mensal registrada.";

async function read(action, data) {
  const response = await fetch(
    endpoint(action) + "?" + new URLSearchParams(data),
    { headers: { Accept: "application/json" }, cache: "no-store" },
  );
  const result = await response.json();
  if (!response.ok || !result.success)
    throw Object.assign(
      new Error(
        result.error ||
          result.message ||
          "Não foi possível consultar os dados.",
      ),
      { code: result.code },
    );
  return result.data;
}

function updateButtons() {
  const r = state.financial?.revisao;
  $("fc-busy").hidden = !state.busy;
  $("fc-retry").hidden = !pending;
  $("fc-prepare").disabled = !validContext() || state.busy || Boolean(pending);
  $("fc-prepare").textContent = r
    ? "Preparar nova revisão financeira"
    : "Preparar revisão financeira";
  $("fc-generate").disabled =
    !r?.acoes.gerar_preview ||
    state.busy ||
    Boolean(pending) ||
    state.operations.length > 0;
  $("fc-confirm").disabled =
    !state.viewed?.shown ||
    state.viewed?.estado !== "PREVIEW" ||
    state.busy ||
    Boolean(pending);
  $("fc-colaborador").disabled = state.busy;
  $("fc-competencia").disabled = state.busy;
  document.querySelectorAll("[data-decision]").forEach((button) => {
    button.disabled =
      !r?.acoes.decidir ||
      state.busy ||
      Boolean(pending) ||
      (button.dataset.decision === "LIQUIDACAO" && !r?.acoes.reconciliar_fixo);
  });
  $("fc-decision-form").querySelector("button[type=submit]").disabled =
    state.busy || Boolean(pending);
  $("fc-retry-button").disabled = state.busy;
  document
    .querySelectorAll("[data-doc],[data-view],[data-recover],[data-revision]")
    .forEach((button) => {
      button.disabled = state.busy;
    });
}

function render() {
  const f = state.financial,
    r = f?.revisao;
  $("fc-content").hidden = !r;
  $("fc-title").textContent = r
    ? names[r.situacao] || r.situacao
    : validContext()
      ? "Sem revisão financeira"
      : "Selecione um colaborador";
  $("fc-version").textContent = r
    ? f.nome +
      " · " +
      f.competencia +
      " · Revisão V" +
      r.numero +
      " · " +
      time(r.snapshot_em) +
      (r.version < f.latest_version
        ? " · Histórico: existe V" + f.latest_version
        : "")
    : "Prepare uma revisão para conferir valores e pendências.";
  if (!r) {
    updateButtons();
    return;
  }
  if (
    state.documents.some(
      (d) => d.revision_id === r.id && d.estado === "CONFIRMADO",
    )
  )
    $("fc-title").textContent += " · Documento confirmado";
  $("fc-summary").innerHTML =
    Object.entries(components)
      .map(
        ([code, label]) =>
          "<div><dt>" +
          label +
          "</dt><dd>" +
          esc(money(r.resumo[code])) +
          "</dd></div>",
      )
      .join("") +
    '<div class="fc-total"><dt>Total desta revisão</dt><dd>' +
    (r.total_final_centavos === null
      ? "—"
      : esc(money(r.total_final_centavos))) +
    "</dd></div>";
  $("fc-pendencias").innerHTML = r.pendencias.length
    ? "<p>Este fechamento ainda não pode ser concluído.</p>" +
      r.pendencias
        .map((p) => {
          const label = components[p.componente] || "Serviços";
          const identity = p.identidade?.origem
            ? " Origem: " +
              p.identidade.origem +
              " #" +
              p.identidade.origem_id +
              "."
            : "";
          const values = Object.entries(p.valores || {})
            .filter(([k]) =>
              [
                "base_centavos",
                "pago_centavos",
                "excesso_centavos",
                "utilizado_centavos",
              ].includes(k),
            )
            .map(
              ([k, v]) =>
                ({
                  base_centavos: "Base",
                  pago_centavos: "Pago",
                  excesso_centavos: "Excesso",
                  utilizado_centavos: "Utilizado",
                })[k] +
                ": " +
                money(v),
            )
            .join(" · ");
          return (
            '<article class="fc-item"><h3>' +
            esc(label) +
            "</h3><p>" +
            esc(
              pendingNames[p.codigo] || p.mensagem || "Conferência necessária.",
            ) +
            "</p><p>" +
            esc(values + identity) +
            "</p>" +
            (!p.componente || p.componente === "SERVICOS"
              ? "<p>Confira os lançamentos na origem. Este fluxo não altera o livro financeiro.</p>"
              : "") +
            "</article>"
          );
        })
        .join("")
    : "<p>Sem pendências bloqueantes nesta revisão.</p>";
  $("fc-servicos").innerHTML =
    r.financeiro_servicos.itens_analisados
      .map(
        (i) =>
          "<tr><td>" +
          esc(i.descricao.imagem || i.descricao.funcao || "Serviço") +
          "<small>" +
          esc(i.descricao.funcao || "") +
          "</small><details><summary>Detalhes da origem</summary><p>" +
          esc(
            i.identidade.origem +
              " #" +
              i.identidade.origem_id +
              " · " +
              i.identidade.classe,
          ) +
          "</p>" +
          i.pagamentos
            .map(
              (p) =>
                "<p>Lançamento #" +
                esc(p.idpagamento_item) +
                ": " +
                esc(money(p.valor_centavos)) +
                " · " +
                esc(p.competencia_pagamento) +
                "</p>",
            )
            .join("") +
          "</details></td><td>" +
          esc(money(i.base_centavos)) +
          "</td><td>" +
          esc(money(i.pago_centavos)) +
          "</td><td>" +
          esc(money(i.saldo_centavos)) +
          "</td><td>" +
          esc(names[i.situacao] || i.situacao) +
          "</td></tr>",
      )
      .join("") ||
    '<tr><td colspan="5">Nenhum serviço analisado nesta competência.</td></tr>';
  $("fc-fixo").innerHTML =
    [
      ["Cadastrado", r.fixo.configurado_centavos],
      ["Utilizado", r.fixo.utilizado_centavos],
      ["Pago comprovado", r.fixo.pago_centavos],
      ["Saldo", r.fixo.saldo_centavos],
    ]
      .map(
        ([label, value]) =>
          "<div><dt>" + label + "</dt><dd>" + esc(money(value)) + "</dd></div>",
      )
      .join("") +
    "<div><dt>Liquidação</dt><dd>" +
    esc(names[r.fixo.estado_liquidacao] || r.fixo.estado_liquidacao) +
    "</dd></div>";
  $("fc-fixo-ato").textContent =
    "Valor: " +
    decisionAuthor(r.fixo.ato_valor || r.fixo.override || r.fixo.decisao) +
    " Liquidação: " +
    decisionAuthor(r.fixo.ato_liquidacao);
  $("fc-bonus").innerHTML =
    "<p>" +
    esc(names[r.extras.estado] || r.extras.estado) +
    "</p><p>" +
    esc(decisionAuthor(r.extras.decisao)) +
    "</p>" +
    r.extras.itens
      .map(
        (i) =>
          "<p>" +
          esc(i.categoria) +
          ": " +
          esc(money(i.valor_centavos)) +
          "</p>",
      )
      .join("");
  $("fc-revisoes").innerHTML = state.revisions
    .map(
      (v) =>
        '<button type="button" class="fc-history-item" data-revision="' +
        v.id +
        '" aria-current="' +
        (Number(v.id) === r.id) +
        '">V' +
        esc(v.numero) +
        " · " +
        esc(names[v.estado] || v.estado) +
        "<small>" +
        esc(time(v.criado_em)) +
        "</small></button>",
    )
    .join("");
  $("fc-documentos").innerHTML =
    state.documents
      .map(
        (d) =>
          '<button type="button" class="fc-history-item" data-doc="' +
          d.document_id +
          '" aria-current="' +
          (d.document_id === state.doc?.document_id) +
          '">' +
          (d.estado ? names[d.estado] : "Operação incompleta") +
          " #" +
          d.numero_documento +
          " · V" +
          d.numero_revisao +
          "<small>" +
          esc(time(d.criado_em_utc)) +
          "</small></button>",
      )
      .join("") || "<p>Nenhum documento neste fechamento.</p>";
  $("fc-operacoes").innerHTML =
    state.operations
      .map(
        (op) =>
          '<article class="fc-item"><p>Operação incompleta — recuperação necessária</p><p>' +
          (op.tipo === "GERAR"
            ? "Geração de preview"
            : "Confirmação de documento") +
          " · #" +
          esc(op.id) +
          "</p>" +
          (op.recuperavel
            ? '<button type="button" class="btn btn-secondary" data-recover="' +
              op.id +
              '">Recuperar esta operação</button>'
            : "<p>Visualize o documento novamente.</p>") +
          "</article>",
      )
      .join("") || "<p>Nenhuma operação incompleta deste usuário.</p>";
  renderDocument();
  updateButtons();
}

function renderDocument() {
  const d = state.doc;
  $("fc-document").innerHTML = !d
    ? "<p>Selecione um documento no histórico ou gere um preview da revisão exibida.</p>"
    : "<p>" +
      esc(
        d.estado === "CONFIRMADO"
          ? "Documento confirmado"
          : d.estado === "PREVIEW"
            ? "Preview"
            : "Operação incompleta — recuperação necessária",
      ) +
      " #" +
      d.numero_documento +
      " · Revisão financeira V" +
      d.numero_revisao +
      "</p><p>Total: " +
      esc(money(d.total_centavos)) +
      "</p>" +
      (d.confirmado_em_utc
        ? "<p>Confirmado em " +
          esc(time(d.confirmado_em_utc)) +
          " por usuário #" +
          esc(d.confirmado_por) +
          ".</p>"
        : "") +
      (d.estado
        ? '<button type="button" class="btn btn-secondary" data-view="' +
          d.document_id +
          '">Visualizar PDF</button>'
        : "");
  const v = state.viewed;
  if (v) {
    $("fc-preview-title").textContent =
      (v.estado === "CONFIRMADO" ? "Documento confirmado" : "Preview") +
      " #" +
      v.numero_documento;
    $("fc-preview-info").textContent =
      "Revisão financeira V" +
      v.numero_revisao +
      " · Total " +
      money(v.total_centavos);
    const latest = state.financial?.latest_version || 0;
    $("fc-preview-warning").hidden = latest <= v.numero_revisao;
    $("fc-preview-warning").textContent =
      "Existe uma revisão financeira mais recente (V" +
      latest +
      "). Este preview pertence à V" +
      v.numero_revisao +
      ".";
  }
}

async function histories(c = context()) {
  c = { colaborador_id: c.colaborador_id, competencia: c.competencia };
  const sequence = state.sequence;
  const [revisions, docs] = await Promise.all([
    read("revisoes", c),
    read("listar", c),
  ]);
  if (!sameContext(c) || sequence !== state.sequence) return;
  state.revisions = revisions;
  state.documents = docs.documentos;
  state.operations = docs.operacoes_pendentes;
  if (state.doc)
    state.doc =
      state.documents.find((d) => d.document_id === state.doc.document_id) ||
      state.doc;
  render();
}

async function load(keepRevision = false) {
  if (!validContext()) {
    state.financial = null;
    render();
    return;
  }
  const sequence = ++state.sequence,
    c = context();
  const query =
    keepRevision && state.financial?.revisao
      ? { ...c, revision_id: state.financial.revisao.id }
      : c;
  try {
    const data = await read("obter", query);
    if (sequence !== state.sequence || !sameContext(c)) return;
    state.financial = data;
    await histories(c);
    render();
  } catch (e) {
    if (sequence === state.sequence) {
      message(e.message);
      $("fc-prepare").disabled = true;
    }
  }
}

async function showPDF(blob, d) {
  if (blobURL) URL.revokeObjectURL(blobURL);
  blobURL = URL.createObjectURL(blob);
  state.viewed = { ...d, shown: false };
  $("fc-view-state").textContent = "Abrindo o PDF entregue pelo servidor.";
  $("fc-pdf-download").href = blobURL;
  $("fc-pdf-download").download = "preview-" + d.document_id + ".pdf";
  renderDocument();
  $("fc-preview").showModal();
  try {
    if (
      (await pdfView.open(blob)) &&
      state.viewed?.document_id === d.document_id
    ) {
      state.viewed.shown = true;
      $("fc-view-state").textContent =
        "PDF exibido e visualização registrada para este documento.";
    }
  } catch {
    $("fc-view-state").textContent =
      "Não foi possível exibir o PDF. Feche e abra este documento novamente.";
  }
  updateButtons();
}

async function mutate(action, data, retry = false) {
  if (state.busy || (pending && !retry)) return;
  const request = retry
    ? pending
    : {
        action,
        data: {
          ...context(),
          ...data,
          idempotency_key: data.idempotency_key || key(),
        },
      };
  if (!request) return;
  savePending(request);
  state.busy = true;
  const dialog = $("fc-decision").open
    ? $("fc-decision-form")
    : $("fc-preview").open
      ? $("fc-preview")
      : null;
  if (dialog) dialog.insertBefore($("fc-busy"), dialog.querySelector("footer"));
  const labels = {
    preparar: "Preparando revisão",
    decidir: "Registrando decisão",
    gerar: "Gerando preview",
    visualizar: "Abrindo PDF",
    confirmar: "Confirmando documento",
    recuperar: "Recuperando operação",
  };
  $("fc-busy-label").textContent = labels[request.action];
  $("fc-busy")
    .querySelector("canvas")
    .setAttribute("aria-label", labels[request.action]);
  updateButtons();
  message("");
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 25000);
  try {
    const response = await fetch(endpoint(request.action), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrf,
        Accept:
          request.action === "visualizar"
            ? "application/pdf"
            : "application/json",
      },
      body: JSON.stringify(request.data),
      signal: controller.signal,
    });
    if (request.action === "visualizar" && response.ok) {
      const d =
        state.documents.find(
          (d) => d.document_id === request.data.document_id,
        ) || state.doc;
      if (
        !d ||
        String(d.document_id) !== response.headers.get("X-Documento-Id") ||
        String(d.revision_id) !== response.headers.get("X-Revisao-Id") ||
        d.pdf_hash !== response.headers.get("X-Pdf-Hash") ||
        response.headers.get("X-Visualizado") !== "1" ||
        !response.headers.get("Content-Type")?.startsWith("application/pdf")
      ) {
        throw new Error(
          "A resposta não corresponde ao PDF selecionado. Repita a mesma operação.",
        );
      }
      const blob = await response.blob();
      if (!blob.size)
        throw new Error("O PDF não foi recebido. Repita a mesma operação.");
      savePending(null);
      await showPDF(blob, d);
    } else {
      const result = await response.json();
      if (!response.ok || !result.success) {
        const definitive = [
          "STALE_VERSION",
          "INVALID_PAYLOAD",
          "IDEMPOTENCY_CONFLICT",
          "SCOPE_MISMATCH",
          "FORBIDDEN",
          "FEATURE_DISABLED",
          "INVALID_SESSION",
          "PREVIEW_NOT_VIEWED",
          "DOCUMENT_MISMATCH",
          "NOT_FOUND",
        ];
        if (
          definitive.includes(result.code) ||
          [401, 403, 419, 404, 405].includes(response.status)
        )
          savePending(null);
        if (result.code === "STALE_VERSION") {
          $("fc-decision").close();
          await load(false);
          throw new Error(
            "Este fechamento foi atualizado por outra ação. Recarregamos a versão mais recente; confira os dados antes de continuar.",
          );
        }
        throw new Error(
          result.error || result.message || "Não foi possível concluir a ação.",
        );
      }
      savePending(null);
      if (["preparar", "decidir"].includes(request.action)) {
        state.financial = result.data;
        if ($("fc-decision").open) $("fc-decision").close();
      } else {
        state.doc = result.data;
        if (
          state.viewed?.document_id === result.data.document_id &&
          state.viewed.pdf_hash === result.data.pdf_hash
        )
          state.viewed = { ...state.viewed, ...result.data };
      }
      message(
        request.action === "confirmar"
          ? "Documento confirmado. Foi salvo o mesmo arquivo visualizado."
          : request.action === "recuperar"
            ? "Operação recuperada."
            : "Ação registrada.",
      );
    }
    await histories(request.data);
    render();
  } catch (e) {
    message(
      e.name === "AbortError"
        ? "A resposta demorou além do esperado. A ação pode ter sido concluída. Use a mesma operação para conferir o resultado."
        : e.message,
    );
    if ($("fc-decision").open) $("fc-decision-error").textContent = e.message;
    // A reserva pode já existir mesmo quando a resposta se perdeu.
    try {
      await histories(request.data);
    } catch {
      /* mantém retry visível */
    }
  } finally {
    clearTimeout(timeout);
    state.busy = false;
    busyHome.insertBefore($("fc-busy"), busyNext);
    updateButtons();
  }
}

function addRow(kind, item = {}) {
  const row = document.createElement("div");
  row.className = "fc-row";
  row.dataset.row = kind;
  row.innerHTML =
    kind === "extra"
      ? '<label>Categoria<input name="categoria" required maxlength="160" value="' +
        esc(item.categoria) +
        '"></label>'
      : '<label>Tipo de evidência<select name="tipo"><option value="PAGAMENTO_FIXO">Pagamento de fixo comprovado</option><option value="APURACAO_FIXO_SEM_PAGAMENTO">Apuração explícita sem pagamento</option></select></label><label>Origem verificável<textarea name="origem_verificavel" required maxlength="1000">' +
        esc(item.origem_verificavel) +
        "</textarea></label>";
  row.innerHTML +=
    '<label>Valor (R$)<input name="valor" inputmode="decimal" required placeholder="Ex.: 150,00" value="' +
    esc(inputMoney(item.valor_centavos)) +
    '"></label><label>Referência<input name="referencia" required maxlength="191" value="' +
    esc(item.referencia || key()) +
    '"></label><button type="button" class="btn btn-secondary" data-remove-row>Remover esta linha</button>';
  if (kind === "evidencia" && item.tipo)
    row.querySelector("[name=tipo]").value = item.tipo;
  $("fc-decision-rows").append(row);
}

function openDecision(mode) {
  const r = state.financial?.revisao;
  if (!r?.acoes.decidir || pending || state.busy) return;
  decision = { mode, expected_version: r.version, context: context() };
  $("fc-decision-form").reset();
  $("fc-decision-error").textContent = "";
  const titles = {
    CADASTRO: "Usar valor fixo do cadastro",
    SEM_VALOR_FIXO: "Registrar ausência de valor fixo",
    OVERRIDE: "Substituir valor fixo",
    LIQUIDACAO: "Conferir liquidação do valor fixo",
    SEM_BONUS: "Registrar ausência de bônus",
    DEFINIDO: "Registrar conjunto de bônus",
    PENDENTE: "Reabrir decisão de bônus",
  };
  $("fc-decision-title").textContent = titles[mode];
  $("fc-decision-scope").textContent =
    state.financial.nome +
    " · " +
    state.financial.competencia +
    " · Revisão V" +
    r.numero;
  $("fc-decision-help").textContent =
    mode === "DEFINIDO" || mode === "LIQUIDACAO"
      ? "Este ato substitui o conjunto completo vigente e cria outra revisão. Confira todas as linhas e as evidências antes de registrar."
      : mode === "PENDENTE"
        ? "A revisão ficará pendente até outra decisão explícita de bônus."
        : "A decisão afeta somente este fechamento e cria outra revisão.";
  $("fc-decision-fields").innerHTML =
    mode === "OVERRIDE"
      ? '<label>Novo valor fixo (R$)<input name="valor" inputmode="decimal" required placeholder="Ex.: 1.500,00"></label>'
      : ["DEFINIDO", "LIQUIDACAO"].includes(mode)
        ? (mode === "LIQUIDACAO"
            ? '<label>Estado da liquidação<select name="estado_liquidacao"><option value="EVIDENCIADA">Registrar conjunto completo de evidências</option><option value="INDETERMINADA">Revogar evidências e deixar indeterminado</option></select></label><p>Apuração sem pagamento exige valor explicitamente informado como 0,00 e origem verificável. Ela não pode coexistir com pagamentos.</p>'
            : "") +
          '<div id="fc-decision-rows"></div><button type="button" class="btn btn-secondary" id="fc-add-row">' +
          (mode === "DEFINIDO" ? "Adicionar extra" : "Adicionar evidência") +
          "</button>"
        : "";
  if (mode === "DEFINIDO" || mode === "LIQUIDACAO") {
    const rows =
      mode === "DEFINIDO" ? r.extras.itens : r.fixo.evidencias_liquidacao;
    (rows.length ? rows : [{}]).forEach((row) =>
      addRow(mode === "DEFINIDO" ? "extra" : "evidencia", row),
    );
    $("fc-add-row").onclick = () =>
      addRow(mode === "DEFINIDO" ? "extra" : "evidencia");
    $("fc-decision-fields")
      .querySelector("[name=estado_liquidacao]")
      ?.addEventListener("change", (e) => {
        const revoked = e.target.value === "INDETERMINADA";
        $("fc-decision-rows").hidden = revoked;
        $("fc-add-row").hidden = revoked;
        $("fc-decision-rows")
          .querySelectorAll("input,textarea,select")
          .forEach((input) => (input.disabled = revoked));
      });
  }
  $("fc-decision").showModal();
}

$("fc-decision-form").addEventListener("submit", async (e) => {
  e.preventDefault();
  if (!decision || !sameContext(decision.context)) return;
  const form = e.currentTarget,
    mode = decision.mode,
    input = { motivo: form.elements.motivo.value };
  const type = ["CADASTRO", "SEM_VALOR_FIXO", "OVERRIDE"].includes(mode)
    ? "FIXO"
    : mode === "LIQUIDACAO"
      ? "LIQUIDACAO"
      : "BONUS";
  input.estado =
    mode === "LIQUIDACAO" ? form.elements.estado_liquidacao.value : mode;
  if (mode === "OVERRIDE") input.valor = form.elements.valor.value;
  if (mode === "DEFINIDO" || mode === "LIQUIDACAO") {
    const rows = Array.from(form.querySelectorAll("[data-row]")).map((row) => {
      const read = (name) => row.querySelector('[name="' + name + '"]').value;
      return mode === "DEFINIDO"
        ? {
            categoria: read("categoria"),
            referencia: read("referencia"),
            valor: read("valor"),
          }
        : {
            tipo: read("tipo"),
            referencia: read("referencia"),
            valor: read("valor"),
            origem_verificavel: read("origem_verificavel"),
          };
    });
    input[mode === "DEFINIDO" ? "itens" : "evidencias"] =
      input.estado === "INDETERMINADA" ? [] : rows;
  } else if (type === "BONUS") input.itens = [];
  await mutate("decidir", {
    tipo: type,
    input,
    expected_version: decision.expected_version,
  });
});
$("fc-prepare").onclick = () =>
  mutate("preparar", {
    expected_version: state.financial?.latest_version || 0,
  });
$("fc-generate").onclick = () => {
  const r = state.financial?.revisao;
  if (r?.acoes.gerar_preview)
    mutate("gerar", { fechamento_id: r.fechamento_id, revision_id: r.id });
};
$("fc-confirm").onclick = () => {
  const d = state.viewed;
  if (d?.shown && d.estado === "PREVIEW")
    mutate("confirmar", {
      document_id: d.document_id,
      revision_id: d.revision_id,
      pdf_hash: d.pdf_hash,
    });
};
$("fc-refresh").onclick = () => load(true);
$("fc-retry-button").onclick = async () => {
  if (!pending || state.busy) return;
  $("fc-colaborador").value = String(pending.data.colaborador_id);
  $("fc-competencia").value = pending.data.competencia;
  await load(false);
  await mutate(pending.action, pending.data, true);
};
document.addEventListener("click", async (e) => {
  const button = e.target.closest("button");
  if (!button || button.disabled) return;
  if (button.dataset.close) $(button.dataset.close).close();
  if (button.dataset.decision) openDecision(button.dataset.decision);
  if (button.hasAttribute("data-remove-row"))
    button.closest("[data-row]").remove();
  if (button.dataset.revision) {
    try {
      state.financial = await read("obter", {
        ...context(),
        revision_id: button.dataset.revision,
      });
      render();
    } catch (e) {
      message(e.message);
    }
  }
  if (button.dataset.doc) {
    state.doc = state.documents.find(
      (d) => d.document_id === Number(button.dataset.doc),
    );
    render();
  }
  if (button.dataset.view) {
    state.doc =
      state.documents.find(
        (d) => d.document_id === Number(button.dataset.view),
      ) || state.doc;
    await mutate("visualizar", { document_id: state.doc.document_id });
  }
  if (button.dataset.recover) {
    const op = state.operations.find(
      (op) => Number(op.id) === Number(button.dataset.recover),
    );
    if (op) {
      if (pending && pending.data.idempotency_key !== op.chave) {
        message(
          "Conclua ou confira a requisição pendente antes de recuperar outra operação.",
        );
        return;
      }
      if (pending) savePending(null); // recuperação retoma o mesmo journal/chave, não cria nova operação
      await mutate("recuperar", {
        operation_id: Number(op.id),
        idempotency_key: op.chave,
      });
    }
  }
});
for (const id of ["fc-colaborador", "fc-competencia"])
  $(id).addEventListener("change", () => {
    state.financial = null;
    state.doc = null;
    state.viewed = null;
    state.revisions = [];
    state.documents = [];
    state.operations = [];
    pdfView.close();
    $("fc-pdf-download").removeAttribute("href");
    if (blobURL) {
      URL.revokeObjectURL(blobURL);
      blobURL = null;
    }
    message("");
    render();
    load(false);
  });
window.addEventListener("focus", () => {
  if (!state.busy && validContext()) load(true);
});
updateButtons();
load(false);
