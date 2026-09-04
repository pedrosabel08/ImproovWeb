(() => {
  const app = document.getElementById("almaApp");
  if (!app) return;

  const imageId = Number(app.dataset.imageId || 0);
  const state = {
    payload: null,
    library: null,
    view: "resumo",
    editing: false,
    references: [],
    sireResults: [],
    activeDimension: null,
    permissions: {
      "alma.editar": app.dataset.canEdit === "1",
      "alma.ativar": app.dataset.canActivate === "1",
    },
  };

  const api = async (action, options = {}) => {
    const method = options.method || "GET";
    const params = new URLSearchParams({ action, ...(options.params || {}) });
    const response = await fetch(`api.php?${params}`, {
      method,
      headers:
        method === "POST"
          ? { "Content-Type": "application/json", Accept: "application/json" }
          : { Accept: "application/json" },
      body:
        method === "POST"
          ? JSON.stringify({ action, ...(options.body || {}) })
          : undefined,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.success === false)
      throw new Error(data.message || "Não foi possível concluir a operação.");
    return data;
  };

  const esc = (value) =>
    String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");

  const attr = esc;
  const formatDate = (value) => {
    if (!value) return "—";
    const date = new Date(String(value).replace(" ", "T"));
    return Number.isNaN(date.getTime())
      ? String(value)
      : new Intl.DateTimeFormat("pt-BR", {
          dateStyle: "short",
          timeStyle: "short",
        }).format(date);
  };
  const toast = (message, error = false) => {
    const el = document.getElementById("almaToast");
    el.textContent = message;
    el.classList.toggle("error", error);
    el.hidden = false;
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => {
      el.hidden = true;
    }, 4200);
  };
  const busy = (button, yes, label = "Processando...") => {
    if (!button) return;
    if (yes) {
      button.dataset.original = button.innerHTML;
      button.disabled = true;
      button.textContent = label;
    } else {
      button.disabled = false;
      button.innerHTML = button.dataset.original || button.innerHTML;
    }
  };

  const rootPillars = () => state.library?.pilares || [];
  const dimensions = () => state.library?.dimensoes || [];
  const dimension = (code) => dimensions().find((item) => item.codigo === code);
  const selections = () => state.payload?.revisao?.selecoes || [];
  const selectionsForPillar = (code) =>
    selections().filter((item) => item.pilar_codigo === code);
  const selectionForDimension = (code) =>
    selections().find((item) => item.dimensao_codigo === code);
  const referenceForPillar = (code) =>
    state.references.find(
      (item) => dimension(item.dimensao_codigo)?.pilar_codigo === code,
    );
  const can = (capability) => Boolean(state.permissions?.[capability]);

  async function load(revisionId = null) {
    if (!imageId) {
      app.innerHTML = `<section class="alma-card alma-empty"><div class="alma-empty-mark">!</div><h1>Imagem não informada</h1><p>Abra o ALMA a partir de uma imagem ou tarefa do Flow.</p><a class="alma-btn" href="../inicio.php">Voltar ao Flow</a></section>`;
      return;
    }
    const payload = await api("direcao", {
      params: {
        imagem_id: imageId,
        ...(revisionId ? { revisao_id: revisionId } : {}),
      },
    });
    state.payload = payload;
    state.permissions = payload.permissions || state.permissions;
    state.references = (payload.revisao?.referencias || []).map((item) => ({
      ...item,
    }));
    const versionId = payload.revisao?.biblioteca_versao_id;
    const libraryResult = await api("biblioteca", {
      params: versionId ? { versao_id: versionId } : {},
    });
    state.library = libraryResult.biblioteca;
    render();
  }

  function pageHeader() {
    const { imagem, direcao, revisao, revisoes = [] } = state.payload;
    const status = revisao?.estado || "SEM DIREÇÃO";
    const revisionOptions = revisoes
      .map(
        (item) =>
          `<option value="${item.id}" ${revisao?.id === item.id ? "selected" : ""}>Revisão ${item.numero} · ${esc(item.estado)}</option>`,
      )
      .join("");
    return `
      <div class="alma-topbar">
        <div>
          <a class="alma-back" href="../inicio.php"><i class="ri-arrow-left-line"></i> Voltar ao Flow</a>
          <div class="alma-breadcrumb">${esc(imagem.obra_nomenclatura || imagem.nome_obra)} / ${esc(imagem.imagem_nome)} / ${esc(imagem.status_imagem || "")}</div>
          <div class="alma-title-row"><h1>Direção Visual da Imagem</h1><span class="alma-badge is-${status.toLowerCase()}">${esc(status)}</span></div>
          <p class="alma-byline">${revisao ? `Definida por ${esc(revisao.criador)} · ${formatDate(revisao.criada_em)} · revisão ${revisao.numero} · Biblioteca ALMA v${esc(revisao.biblioteca_codigo)}` : "Nenhuma direção definida para esta imagem."}</p>
        </div>
        <div class="alma-actions">
          ${revisoes.length > 1 ? `<label class="alma-field"><span class="alma-kicker">Revisões</span><select id="almaRevisionSelect">${revisionOptions}</select></label>` : ""}
          ${direcao && can("alma.editar") && !state.editing ? `<button class="alma-btn" id="almaEdit"><i class="ri-edit-line"></i> Editar direção</button>` : ""}
          ${revisao?.estado === "RASCUNHO" && can("alma.ativar") && !state.editing ? `<button class="alma-btn primary" id="almaActivate"><i class="ri-checkbox-circle-line"></i> Ativar revisão</button>` : ""}
        </div>
      </div>`;
  }

  function tabs() {
    if (!state.payload.direcao) return "";
    return `<nav class="alma-tabs" aria-label="Seções do ALMA">
      <button class="alma-tab ${state.view === "resumo" ? "active" : ""}" data-view="resumo"><i class="ri-layout-grid-line"></i> Resumo</button>
      <button class="alma-tab ${state.view === "pilares" ? "active" : ""}" data-view="pilares"><i class="ri-compass-3-line"></i> Pilares</button>
      <button class="alma-tab ${state.view === "referencias" ? "active" : ""}" data-view="referencias"><i class="ri-image-line"></i> Referências</button>
      <button class="alma-tab ${state.view === "historico" ? "active" : ""}" data-view="historico"><i class="ri-history-line"></i> Histórico</button>
    </nav>`;
  }

  function emptyState() {
    return `<section class="alma-card alma-empty">
      <div class="alma-empty-mark"><i class="ri-compass-3-line"></i></div>
      <div class="alma-kicker">Direção Visual (ALMA)</div>
      <h1>Nenhuma direção definida</h1>
      <p>A imagem ainda não possui uma Direção Visual. Ao definir o ALMA, todas as tarefas ligadas a esta mesma imagem consultarão a revisão ativa.</p>
      ${can("alma.editar") ? `<button class="alma-btn primary" id="almaCreate"><i class="ri-add-line"></i> Definir ALMA</button>` : ""}
    </section>`;
  }

  function pillarSummary(pillar) {
    const selected = selectionsForPillar(pillar.pilar_codigo);
    const labels = selected
      .map(
        (item) =>
          item.item_titulo || item.resumo_contextual || item.aplicacao_imagem,
      )
      .filter(Boolean);
    return labels.join(" · ") || "Direção não preenchida";
  }

  function overview() {
    const { imagem, revisao } = state.payload;
    const bg = imagem.preview_url
      ? `style="background-image:url('${attr(imagem.preview_url)}')"`
      : "";
    return `
      <section class="alma-card alma-hero" ${bg}>
        <div class="alma-hero-content">
          <div class="alma-kicker">Intenção / história da imagem</div>
          <h2>${esc(revisao.sintese_narrativa || "Síntese narrativa ainda não preenchida.")}</h2>
          <p>${esc(revisao.intencao_geral || "Intenção geral ainda não preenchida.")}</p>
        </div>
      </section>
      <section class="alma-section">
        <div class="alma-section-head"><h2>A jornada da imagem</h2><p>Uma intenção, sete dimensões, uma história.</p></div>
        <div class="alma-card alma-journey">${rootPillars()
          .map(
            (pillar) =>
              `<div class="alma-journey-step"><b>${String(pillar.ordem_jornada).padStart(2, "0")}</b><strong>${esc(pillar.etapa_nome)}</strong><span>${esc(pillar.pilar_nome)}</span></div>`,
          )
          .join("")}</div>
      </section>
      <section class="alma-section">
        <div class="alma-section-head"><h2>Direção resumida</h2><p>Clique em um pilar para aprofundar.</p></div>
        <div class="alma-pillars">${rootPillars()
          .map((pillar) => {
            const ref = referenceForPillar(pillar.pilar_codigo);
            return `<article class="alma-card alma-pillar-card" data-pillar="${attr(pillar.pilar_codigo)}" tabindex="0">
            <div><span class="number">${String(pillar.ordem_jornada).padStart(2, "0")}</span><span class="stage">${esc(pillar.etapa_nome)}</span></div>
            <h3>${esc(pillar.pilar_nome)}</h3><strong>${esc(pillarSummary(pillar))}</strong>
            <p>${esc(selectionsForPillar(pillar.pilar_codigo)[0]?.item_resumo || selectionsForPillar(pillar.pilar_codigo)[0]?.aplicacao_imagem || "")}</p>
            <div class="alma-pillar-thumb" ${ref?.thumbnail_url ? `style="background-image:url('${attr(ref.thumbnail_url)}')"` : ""}></div>
          </article>`;
          })
          .join("")}</div>
      </section>`;
  }

  function pillarsView() {
    return `<section class="alma-section"><div class="alma-section-head"><h2>Sete pilares</h2><p>Identidade → resumo → princípio → diretriz → referências.</p></div>
      <div class="alma-pillars">${rootPillars()
        .map(
          (pillar) =>
            `<article class="alma-card alma-pillar-card" data-pillar="${attr(pillar.pilar_codigo)}" tabindex="0"><div><span class="number">${String(pillar.ordem_jornada).padStart(2, "0")}</span><span class="stage">${esc(pillar.etapa_nome)}</span></div><h3>${esc(pillar.pilar_nome)}</h3><strong>${esc(pillarSummary(pillar))}</strong><p>Abrir detalhamento completo</p><div class="alma-pillar-thumb"></div></article>`,
        )
        .join("")}</div></section>`;
  }

  function referencesView() {
    const refs = state.payload.revisao?.referencias || [];
    if (!refs.length)
      return `<section class="alma-card alma-empty"><div class="alma-empty-mark"><i class="ri-image-line"></i></div><h1>Sem referências vinculadas</h1><p>As referências SIRE são adicionadas e interpretadas durante a edição da direção.</p></section>`;
    return `<section class="alma-section"><div class="alma-section-head"><h2>Referências SIRE interpretadas</h2><p>Referências apoiam a direção; não a substituem.</p></div><div class="alma-card alma-detail-section">${refs.map((ref) => `<article class="alma-reference-view"><img src="${attr(ref.thumbnail_url)}" alt=""><div><strong>${esc(ref.titulo_exibicao)}</strong><p><b>${esc(ref.dimensao_nome)} — representa:</b> ${esc(ref.representa)}</p><p><b>Aplicar:</b> ${esc(ref.aplicar)}</p>${ref.nao_copiar ? `<p><b>Não copiar:</b> ${esc(ref.nao_copiar)}</p>` : ""}</div></article>`).join("")}</div></section>`;
  }

  const eventLabels = {
    DIRECAO_CRIADA: "criou a Direção Visual",
    REVISAO_CRIADA: "criou uma revisão",
    INTENCAO_ALTERADA: "alterou a intenção geral",
    SINTESE_ALTERADA: "alterou a síntese narrativa",
    SELECOES_E_CONTEXTO_ALTERADOS: "alterou pilares ou contexto",
    REFERENCIA_VINCULADA: "vinculou uma referência SIRE",
    REFERENCIA_DESVINCULADA: "desvinculou uma referência SIRE",
    INTERPRETACAO_REFERENCIA_ALTERADA:
      "alterou a interpretação de uma referência",
    REVISAO_ATIVADA: "ativou uma revisão",
    REVISAO_SUBSTITUIDA: "substituiu uma revisão anterior",
  };
  const compactHistoryValue = (value) => {
    if (value === null || value === undefined || value === "") return "vazio";
    if (!Array.isArray(value) && typeof value !== "object")
      return String(value);
    if (Array.isArray(value)) {
      return (
        value
          .map((item) => {
            if (item.dimensao)
              return `${item.dimensao}: ${item.item_titulo || item.resumo_contextual || item.aplicacao_imagem || "contexto"}`;
            if (item.referencia_titulo)
              return `${item.dimensao}: ${item.referencia_titulo} — ${item.representa || "interpretação"}`;
            return JSON.stringify(item);
          })
          .join("; ") || "vazio"
      );
    }
    if (value.dimensao && value.referencia_titulo)
      return `${value.dimensao}: ${value.referencia_titulo} — ${value.representa || "interpretação"}`;
    if (value.dimensao)
      return `${value.dimensao}: ${value.item_titulo || value.resumo_contextual || value.aplicacao_imagem || "contexto"}`;
    if (Object.hasOwn(value, "revisao_ativa_id"))
      return `revisão ${value.revisao_ativa_id || "nenhuma"}`;
    if (Object.hasOwn(value, "estado")) return value.estado;
    return JSON.stringify(value);
  };
  const historyDetail = (event) => {
    if (event.antes === null && event.depois === null)
      return esc(event.entidade_tipo);
    const before = compactHistoryValue(event.antes);
    const after = compactHistoryValue(event.depois);
    return `<span class="alma-event-change"><b>Antes:</b> ${esc(before)} <i class="ri-arrow-right-line"></i> <b>Depois:</b> ${esc(after)}</span>`;
  };
  async function historyView() {
    app.querySelector("#almaContent").innerHTML =
      `<div class="alma-loading"><span></span><p>Carregando histórico...</p></div>`;
    try {
      const data = await api("historico", { params: { imagem_id: imageId } });
      const events = data.eventos || [];
      app.querySelector("#almaContent").innerHTML = events.length
        ? `<section class="alma-history">${events.map((event) => `<article class="alma-card alma-event"><time>${formatDate(event.criado_em)}</time><div><strong>${esc(event.ator)} ${esc(eventLabels[event.acao] || event.acao.toLowerCase().replaceAll("_", " "))}${event.revisao_numero ? ` · revisão ${event.revisao_numero}` : ""}</strong><p>${historyDetail(event)}</p></div></article>`).join("")}</section>`
        : `<section class="alma-card alma-empty"><h1>Histórico vazio</h1></section>`;
    } catch (error) {
      toast(error.message, true);
    }
  }

  function officialItem(itemId) {
    for (const dim of dimensions()) {
      const item = (dim.itens || []).find(
        (candidate) => candidate.id === Number(itemId),
      );
      if (item) return item;
    }
    return null;
  }

  function openPillar(code) {
    const pillar = rootPillars().find((item) => item.pilar_codigo === code);
    if (!pillar) return;
    const selected = selectionsForPillar(code);
    const references =
      state.payload.revisao?.referencias?.filter(
        (item) => dimension(item.dimensao_codigo)?.pilar_codigo === code,
      ) || [];
    const blocks = selected
      .map((selection) => {
        const item = officialItem(selection.item_biblioteca_id) || selection;
        const visibleSections = (item.secoes || []).filter(
          (section) => section.codigo !== "fonte_oficial",
        );
        return `<div class="alma-detail-grid">
        <div>
          ${item.resumo || item.item_resumo ? `<section class="alma-card alma-detail-section"><h3>Resumo</h3><p>${esc(item.resumo || item.item_resumo)}</p></section>` : ""}
          ${item.principio_fundamental ? `<section class="alma-card alma-detail-section"><h3>Princípio fundamental</h3><p>${esc(item.principio_fundamental)}</p></section>` : ""}
          ${item.diretriz_completa ? `<section class="alma-card alma-detail-section"><h3>Diretriz completa</h3><p>${esc(item.diretriz_completa)}</p></section>` : ""}
          ${visibleSections.map((section) => `<section class="alma-card alma-detail-section"><h3>${esc(section.titulo)}</h3>${section.conteudo ? `<p>${esc(section.conteudo)}</p>` : ""}${section.entradas?.length ? `<ul class="alma-list">${section.entradas.map((entry) => `<li>${esc(entry.texto)}</li>`).join("")}</ul>` : ""}</section>`).join("")}
        </div>
        <aside>
          <section class="alma-card alma-detail-section alma-context"><h3>Aplicação nesta imagem</h3><p>${esc(selection.aplicacao_imagem || "Sem aplicação contextual registrada.")}</p>${selection.justificativa ? `<h3>Justificativa</h3><p>${esc(selection.justificativa)}</p>` : ""}${selection.observacao_operacional ? `<h3>Observação operacional</h3><p>${esc(selection.observacao_operacional)}</p>` : ""}</section>
          ${references
            .filter((ref) => ref.dimensao_codigo === selection.dimensao_codigo)
            .map(
              (ref) =>
                `<article class="alma-reference-view"><img src="${attr(ref.thumbnail_url)}" alt=""><div><strong>${esc(ref.titulo_exibicao)}</strong><p><b>Representa:</b> ${esc(ref.representa)}</p><p><b>Aplicar:</b> ${esc(ref.aplicar)}</p>${ref.nao_copiar ? `<p><b>Não copiar:</b> ${esc(ref.nao_copiar)}</p>` : ""}</div></article>`,
            )
            .join("")}
        </aside>
      </div>`;
      })
      .join("");
    openDialog(
      `<article class="alma-detail"><header class="alma-detail-head"><div class="alma-kicker">${String(pillar.ordem_jornada).padStart(2, "0")} · ${esc(pillar.etapa_nome)}</div><h2 id="almaDialogTitle">${esc(pillar.pilar_nome)}</h2><p>${esc(pillarSummary(pillar))}</p></header>${blocks || `<div class="alma-empty"><p>Este pilar ainda não foi contextualizado.</p></div>`}</article>`,
    );
  }

  function openDialog(html) {
    const dialog = document.getElementById("almaDialog");
    document.getElementById("almaDialogBody").innerHTML = html;
    dialog.hidden = false;
    document.body.style.overflow = "hidden";
  }
  function closeDialog() {
    document.getElementById("almaDialog").hidden = true;
    document.body.style.overflow = "";
  }

  function selectionFields(dim) {
    const selected = selectionForDimension(dim.codigo) || {};
    const options = (dim.itens || []).filter(
      (item) => item.ativo || item.id === selected.item_biblioteca_id,
    );
    const itemSelect = dim.exige_item_biblioteca
      ? `<div class="alma-field"><label>Item oficial da Biblioteca</label><select data-field="item_biblioteca_id"><option value="">Selecione...</option>${options.map((item) => `<option value="${item.id}" ${Number(selected.item_biblioteca_id) === item.id ? "selected" : ""}>${esc(item.titulo)}</option>`).join("")}</select></div>`
      : `<div class="alma-field"><label>Síntese desta dimensão</label><input data-field="resumo_contextual" maxlength="255" value="${attr(selected.resumo_contextual || "")}" placeholder="Contextualize sem criar um novo conceito oficial"></div>`;
    return `<div class="alma-dimension-block" data-dimension="${attr(dim.codigo)}"><div class="alma-dimension-title"><h4>${esc(dim.nome)}</h4><button type="button" class="alma-btn" data-add-reference="${attr(dim.codigo)}"><i class="ri-image-add-line"></i> Referência SIRE</button></div>${itemSelect}
      <div class="alma-form-grid">
        <div class="alma-field"><label>Aplicação nesta imagem</label><textarea data-field="aplicacao_imagem" placeholder="Como esta escolha deve aparecer nesta imagem?">${esc(selected.aplicacao_imagem || "")}</textarea></div>
        <div class="alma-field"><label>Justificativa</label><textarea data-field="justificativa" placeholder="Por que esta escolha é relevante?">${esc(selected.justificativa || "")}</textarea></div>
      </div>
      <div class="alma-field"><label>Observação operacional</label><textarea data-field="observacao_operacional" placeholder="Orientação útil para a produção, sem duplicar a tarefa.">${esc(selected.observacao_operacional || "")}</textarea></div>
      <div class="alma-reference-list">${referenceEditors(dim.codigo)}</div>
    </div>`;
  }

  function referenceEditors(code) {
    return state.references
      .filter((ref) => ref.dimensao_codigo === code)
      .map(
        (ref, index) =>
          `<article class="alma-reference-editor" data-reference-id="${ref.sire_referencia_id}" data-dimension-code="${attr(code)}"><img src="${attr(ref.thumbnail_url)}" alt=""><div><div class="alma-dimension-title"><strong>${esc(ref.titulo_exibicao || `Referência #${ref.sire_referencia_id}`)}</strong><button type="button" class="alma-btn danger" data-remove-reference="${ref.sire_referencia_id}" data-ref-index="${index}">Remover</button></div><div class="alma-reference-fields"><div class="alma-field"><label>O que representa *</label><textarea data-ref-field="representa">${esc(ref.representa || "")}</textarea></div><div class="alma-field"><label>Como aplicar *</label><textarea data-ref-field="aplicar">${esc(ref.aplicar || "")}</textarea></div><div class="alma-field"><label>Por que é relevante</label><textarea data-ref-field="relevancia">${esc(ref.relevancia || "")}</textarea></div><div class="alma-field"><label>Não copiar</label><textarea data-ref-field="nao_copiar">${esc(ref.nao_copiar || "")}</textarea></div><div class="alma-field wide"><label>Observação</label><input data-ref-field="observacao" value="${attr(ref.observacao || "")}"></div></div></div></article>`,
      )
      .join("");
  }

  function editView() {
    const revision = state.payload.revisao;
    return `<form class="alma-form" id="almaEditForm">
      <section class="alma-card alma-edit-pillar" open><div class="alma-form-grid"><div class="alma-field"><label>Intenção geral da imagem</label><textarea id="almaIntention" required>${esc(revision.intencao_geral || "")}</textarea></div><div class="alma-field"><label>Síntese narrativa da direção</label><textarea id="almaNarrative" required>${esc(revision.sintese_narrativa || "")}</textarea></div></div></section>
      ${rootPillars()
        .map((pillar) => {
          const dims = pillar.filhas?.length
            ? pillar.filhas
            : [dimension(pillar.codigo) || pillar];
          return `<details class="alma-card alma-edit-pillar" open><summary><div><span>${String(pillar.ordem_jornada).padStart(2, "0")} · ${esc(pillar.etapa_nome)}</span><br>${esc(pillar.pilar_nome)}</div><i class="ri-arrow-down-s-line"></i></summary><div class="alma-edit-body">${dims.map(selectionFields).join("")}</div></details>`;
        })
        .join("")}
      <div class="alma-actions"><button type="button" class="alma-btn" id="almaCancelEdit">Cancelar</button><button type="submit" class="alma-btn primary" id="almaSave"><i class="ri-save-line"></i> Salvar rascunho</button></div>
    </form>`;
  }

  function collectForm() {
    const items = [];
    document.querySelectorAll("[data-dimension]").forEach((block, order) => {
      const record = {
        dimensao_codigo: block.dataset.dimension,
        principal: true,
        ordem: order,
      };
      block.querySelectorAll("[data-field]").forEach((field) => {
        record[field.dataset.field] = field.value.trim();
      });
      items.push(record);
    });
    const refs = [];
    document.querySelectorAll(".alma-reference-editor").forEach((block) => {
      const record = {
        dimensao_codigo: block.dataset.dimensionCode,
        sire_referencia_id: Number(block.dataset.referenceId),
      };
      block.querySelectorAll("[data-ref-field]").forEach((field) => {
        record[field.dataset.refField] = field.value.trim();
      });
      refs.push(record);
    });
    return {
      revisao_id: state.payload.revisao.id,
      lock_version: state.payload.revisao.lock_version,
      intencao_geral: document.getElementById("almaIntention").value.trim(),
      sintese_narrativa: document.getElementById("almaNarrative").value.trim(),
      selecoes: items,
      referencias: refs,
    };
  }

  function syncDraftForm() {
    if (!state.editing || !document.getElementById("almaEditForm")) return;
    const draft = collectForm();
    state.payload.revisao.intencao_geral = draft.intencao_geral;
    state.payload.revisao.sintese_narrativa = draft.sintese_narrativa;
    state.payload.revisao.selecoes = draft.selecoes.map((selection) => {
      const dim = dimension(selection.dimensao_codigo) || {};
      const item = officialItem(selection.item_biblioteca_id) || {};
      return {
        ...selection,
        dimensao_nome: dim.nome,
        pilar_codigo: dim.pilar_codigo,
        pilar_nome: dim.pilar_nome,
        etapa_codigo: dim.etapa_codigo,
        etapa_nome: dim.etapa_nome,
        item_titulo: item.titulo,
        item_resumo: item.resumo,
      };
    });
    state.references = draft.referencias.map((reference) => ({
      ...(state.references.find(
        (candidate) =>
          Number(candidate.sire_referencia_id) ===
            reference.sire_referencia_id &&
          candidate.dimensao_codigo === reference.dimensao_codigo,
      ) || {}),
      ...reference,
    }));
  }

  async function startEditing(button = null) {
    try {
      busy(button, true, "Preparando...");
      if (
        !state.payload.revisao ||
        state.payload.revisao.estado !== "RASCUNHO"
      ) {
        const data = await api("criar_revisao", {
          method: "POST",
          body: {
            imagem_id: imageId,
            revisao_origem_id: state.payload.revisao?.id || null,
          },
        });
        state.payload = data;
        state.references = (data.revisao?.referencias || []).map((item) => ({
          ...item,
        }));
        const libraryResult = await api("biblioteca", {
          params: { versao_id: data.revisao.biblioteca_versao_id },
        });
        state.library = libraryResult.biblioteca;
      }
      state.editing = true;
      render();
    } catch (error) {
      toast(error.message, true);
    } finally {
      busy(button, false);
    }
  }

  async function openSirePicker(code) {
    syncDraftForm();
    state.activeDimension = code;
    openDialog(
      `<div class="alma-sire-search"><div class="alma-kicker">SIRE</div><h2 id="almaDialogTitle">Escolher referência</h2><div class="alma-field"><label>Buscar na biblioteca visual</label><input id="almaSireQuery" placeholder="Título, obra ou arquivo"></div><div id="almaSireResults" class="alma-sire-grid"><div class="alma-loading"><span></span></div></div></div>`,
    );
    const run = async () => {
      const target = document.getElementById("almaSireResults");
      try {
        const result = await api("sire_busca", {
          params: { q: document.getElementById("almaSireQuery")?.value || "" },
        });
        state.sireResults = result.referencias || [];
        target.innerHTML = result.referencias.length
          ? result.referencias
              .map(
                (ref) =>
                  `<button type="button" class="alma-card alma-sire-card" data-choose-sire="${ref.id}"><img src="${attr(ref.thumbnail_url)}" alt=""><span>${esc(ref.titulo_exibicao)}</span></button>`,
              )
              .join("")
          : `<p>Nenhuma referência encontrada.</p>`;
      } catch (error) {
        target.innerHTML = `<p>${esc(error.message)}</p>`;
      }
    };
    await run();
    let timer;
    document.getElementById("almaSireQuery")?.addEventListener("input", () => {
      clearTimeout(timer);
      timer = setTimeout(run, 280);
    });
  }

  function addSireReference(ref) {
    const exists = state.references.some(
      (item) =>
        Number(item.sire_referencia_id) === Number(ref.id) &&
        item.dimensao_codigo === state.activeDimension,
    );
    if (!exists)
      state.references.push({
        sire_referencia_id: ref.id,
        dimensao_codigo: state.activeDimension,
        titulo_exibicao: ref.titulo_exibicao,
        thumbnail_url: ref.thumbnail_url,
        representa: "",
        aplicar: "",
        relevancia: "",
        nao_copiar: "",
        observacao: "",
      });
    closeDialog();
    render();
    document
      .querySelector(`[data-dimension="${CSS.escape(state.activeDimension)}"]`)
      ?.scrollIntoView({ behavior: "smooth", block: "center" });
  }

  function render() {
    const { direcao } = state.payload || {};
    app.innerHTML = `<div class="alma-shell">${state.payload ? pageHeader() : ""}${direcao ? tabs() : ""}<div id="almaContent">${!direcao ? emptyState() : state.editing ? editView() : state.view === "referencias" ? referencesView() : state.view === "pilares" ? pillarsView() : overview()}</div></div>`;
    bind();
    if (direcao && !state.editing && state.view === "historico") historyView();
  }

  function bind() {
    document
      .getElementById("almaCreate")
      ?.addEventListener("click", (event) => startEditing(event.currentTarget));
    document
      .getElementById("almaEdit")
      ?.addEventListener("click", (event) => startEditing(event.currentTarget));
    document.getElementById("almaCancelEdit")?.addEventListener("click", () => {
      state.editing = false;
      load(state.payload.revisao.id).catch((error) =>
        toast(error.message, true),
      );
    });
    document
      .getElementById("almaRevisionSelect")
      ?.addEventListener("change", (event) =>
        load(Number(event.target.value)).catch((error) =>
          toast(error.message, true),
        ),
      );
    document.querySelectorAll("[data-view]").forEach((button) =>
      button.addEventListener("click", () => {
        state.view = button.dataset.view;
        state.editing = false;
        render();
      }),
    );
    document.querySelectorAll("[data-pillar]").forEach((card) => {
      card.addEventListener("click", () => openPillar(card.dataset.pillar));
      card.addEventListener("keydown", (event) => {
        if (event.key === "Enter") openPillar(card.dataset.pillar);
      });
    });
    document
      .querySelectorAll("[data-add-reference]")
      .forEach((button) =>
        button.addEventListener("click", () =>
          openSirePicker(button.dataset.addReference),
        ),
      );
    document.querySelectorAll("[data-remove-reference]").forEach((button) =>
      button.addEventListener("click", () => {
        const editor = button.closest(".alma-reference-editor");
        syncDraftForm();
        state.references = state.references.filter(
          (ref) =>
            !(
              Number(ref.sire_referencia_id) ===
                Number(editor.dataset.referenceId) &&
              ref.dimensao_codigo === editor.dataset.dimensionCode
            ),
        );
        render();
      }),
    );
    document
      .getElementById("almaEditForm")
      ?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const button = document.getElementById("almaSave");
        try {
          busy(button, true, "Salvando...");
          const data = await api("salvar_revisao", {
            method: "POST",
            body: collectForm(),
          });
          state.payload = data;
          state.references = (data.revisao?.referencias || []).map((item) => ({
            ...item,
          }));
          state.editing = false;
          toast("Rascunho salvo. A revisão ativa anterior permanece vigente.");
          render();
        } catch (error) {
          toast(error.message, true);
        } finally {
          busy(button, false);
        }
      });
    document
      .getElementById("almaActivate")
      ?.addEventListener("click", async (event) => {
        if (
          !window.confirm(
            "Ativar esta revisão como a Direção Visual vigente da imagem?",
          )
        )
          return;
        const button = event.currentTarget;
        try {
          busy(button, true, "Ativando...");
          const data = await api("ativar_revisao", {
            method: "POST",
            body: { revisao_id: state.payload.revisao.id },
          });
          state.payload = data;
          state.references = (data.revisao?.referencias || []).map((item) => ({
            ...item,
          }));
          toast("Revisão ativada. A produção já consulta esta direção.");
          render();
        } catch (error) {
          toast(error.message, true);
        } finally {
          busy(button, false);
        }
      });
  }

  document
    .querySelector("[data-close-dialog]")
    ?.addEventListener("click", closeDialog);
  document
    .getElementById("almaDialog")
    ?.addEventListener("click", async (event) => {
      if (event.target.id === "almaDialog") closeDialog();
      const button = event.target.closest("[data-choose-sire]");
      if (button) {
        const ref = state.sireResults.find(
          (item) => item.id === Number(button.dataset.chooseSire),
        );
        if (ref) addSireReference(ref);
      }
    });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeDialog();
  });

  load().catch((error) => {
    app.innerHTML = `<section class="alma-card alma-empty"><div class="alma-empty-mark">!</div><h1>Não foi possível abrir o ALMA</h1><p>${esc(error.message)}</p><a class="alma-btn" href="../inicio.php">Voltar ao Flow</a></section>`;
  });
})();
