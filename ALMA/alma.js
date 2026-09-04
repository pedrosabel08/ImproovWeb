(() => {
  const app = document.getElementById("almaApp");
  if (!app) return;

  const state = {
    obraId: Number(app.dataset.obraId || 0),
    imageId: Number(app.dataset.imageId || 0),
    context: null,
    payload: null,
    library: null,
    projectRefs: {},
    imageRefs: {},
    dirtyProject: false,
    dirtyImage: false,
    activePillar: "atmosfera",
    picker: null,
    permissions: { "alma.editar": app.dataset.canEdit === "1" },
  };
  const PROJECT_CODES = ["arquitetura", "materialidade", "lifestyle"];
  const IMAGE_CODES = [
    "atmosfera",
    "luz_momento",
    "luz_linguagem",
    "fotografia_direcao",
    "composicao",
  ];
  const statusMeta = {
    NAO_INICIADO: ["Não iniciado", "empty"],
    PARCIAL: ["Parcial", "partial"],
    COMPLETO: ["Completo", "complete"],
  };
  const esc = (value) =>
    String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  const attr = esc;
  const api = async (action, options = {}) => {
    const method = options.method || "GET";
    const params = new URLSearchParams({ action, ...(options.params || {}) });
    const response = await fetch(`api.php?${params}`, {
      method,
      headers: {
        Accept: "application/json",
        ...(method === "POST" ? { "Content-Type": "application/json" } : {}),
      },
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
  const canEdit = () => Boolean(state.permissions["alma.editar"]);
  const dimension = (code) =>
    (state.library?.dimensoes || []).find((item) => item.codigo === code);
  const currentImage = () =>
    (state.context?.imagens || []).find(
      (image) => Number(image.imagem_id) === Number(state.imageId),
    );
  const selectionFor = (scope, code) => {
    const list =
      scope === "project"
        ? state.context?.projeto?.selecoes || []
        : state.payload?.revisao?.selecoes || [];
    return list.find((item) => item.dimensao_codigo === code) || null;
  };
  const refsFor = (scope, code) =>
    (scope === "project" ? state.projectRefs : state.imageRefs)[code] || [];
  const setSelection = (scope, code, itemId) => {
    let target;
    if (scope === "project") {
      state.context.projeto ||= {
        selecoes: [],
        lock_version: 0,
        biblioteca_versao_id: state.library.versao.id,
      };
      target = state.context.projeto;
    } else {
      state.payload ||= {};
      state.payload.revisao ||= { selecoes: [] };
      target = state.payload.revisao;
    }
    target.selecoes ||= [];
    target.selecoes = target.selecoes.filter(
      (selection) => selection.dimensao_codigo !== code,
    );
    if (!itemId) return;
    const dim = dimension(code),
      item = (dim?.itens || []).find(
        (candidate) => Number(candidate.id) === Number(itemId),
      );
    target.selecoes.push({
      dimensao_codigo: code,
      dimensao_nome: dim?.nome,
      pilar_codigo: dim?.pilar_codigo,
      pilar_nome: dim?.pilar_nome,
      item_biblioteca_id: Number(itemId),
      item_titulo: item?.titulo,
      referencias: refsFor(scope, code),
    });
  };
  const setRefs = (scope, code, refs) => {
    (scope === "project" ? state.projectRefs : state.imageRefs)[code] = refs;
    if (scope === "project") state.dirtyProject = true;
    else state.dirtyImage = true;
  };
  const toast = (message, error = false) => {
    const el = document.getElementById("almaToast");
    el.textContent = message;
    el.classList.toggle("error", error);
    el.hidden = false;
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => (el.hidden = true), 4200);
  };
  const busy = (button, active, label = "Processando...") => {
    if (!button) return;
    if (active) {
      button.dataset.original = button.innerHTML;
      button.disabled = true;
      button.textContent = label;
    } else {
      button.disabled = false;
      button.innerHTML = button.dataset.original || button.innerHTML;
    }
  };
  const openDialog = (html) => {
    document.getElementById("almaDialogBody").innerHTML = html;
    document.getElementById("almaDialog").hidden = false;
    document.body.style.overflow = "hidden";
  };
  const closeDialog = () => {
    document.getElementById("almaDialog").hidden = true;
    document.body.style.overflow = "";
  };
  const notifyHeight = () => {
    if (window.parent === window) return;
    requestAnimationFrame(() =>
      window.parent.postMessage(
        { type: "alma:height", height: document.documentElement.scrollHeight },
        window.location.origin,
      ),
    );
  };

  function hydrateReferences() {
    state.projectRefs = {};
    for (const selection of state.context?.projeto?.selecoes || [])
      state.projectRefs[selection.dimensao_codigo] = (
        selection.referencias || []
      ).map((reference) => ({ ...reference }));
    state.imageRefs = {};
    for (const selection of state.payload?.revisao?.selecoes || [])
      state.imageRefs[selection.dimensao_codigo] = (
        selection.referencias || []
      ).map((reference) => ({ ...reference }));
  }

  async function loadImage(imageId, renderAfter = true) {
    state.imageId = Number(imageId || 0);
    state.payload = state.imageId
      ? await api("direcao", { params: { imagem_id: state.imageId } })
      : null;
    state.permissions = state.payload?.permissions || state.permissions;
    state.imageRefs = {};
    for (const selection of state.payload?.revisao?.selecoes || [])
      state.imageRefs[selection.dimensao_codigo] = (
        selection.referencias || []
      ).map((reference) => ({ ...reference }));
    state.dirtyImage = false;
    if (renderAfter) render();
  }

  async function load() {
    if (!state.obraId && state.imageId) {
      state.payload = await api("direcao", {
        params: { imagem_id: state.imageId },
      });
      state.obraId = Number(state.payload.imagem?.obra_id || 0);
    }
    if (!state.obraId)
      throw new Error("Abra o ALMA a partir de uma obra ou imagem do Flow.");
    state.context = await api("obra_contexto", {
      params: { obra_id: state.obraId },
    });
    state.permissions = state.context.permissions || state.permissions;
    if (
      !state.imageId ||
      !state.context.imagens.some(
        (image) => Number(image.imagem_id) === state.imageId,
      )
    )
      state.imageId = Number(state.context.imagens[0]?.imagem_id || 0);
    if (
      !state.payload ||
      Number(state.payload.imagem?.imagem_id) !== state.imageId
    )
      await loadImage(state.imageId, false);
    const versionId =
      state.context.projeto?.biblioteca_versao_id ||
      state.payload?.revisao?.biblioteca_versao_id;
    state.library = (
      await api("biblioteca", {
        params: versionId ? { versao_id: versionId } : {},
      })
    ).biblioteca;
    hydrateReferences();
    render();
  }

  function referenceCards(scope, code) {
    const refs = refsFor(scope, code);
    if (!refs.length)
      return '<p class="alma-no-references">Nenhuma referência selecionada.</p>';
    return `<div class="alma-selected-references">${refs.map((reference) => `<article class="alma-selected-reference"><img src="${attr(reference.thumbnail_url)}" alt="" loading="lazy"><span>${esc(reference.titulo_exibicao || `Referência #${reference.sire_referencia_id}`)}</span>${canEdit() ? `<button type="button" data-remove-reference="${reference.sire_referencia_id}" data-scope="${scope}" data-code="${code}" aria-label="Remover referência">×</button>` : ""}</article>`).join("")}</div>`;
  }

  function dimensionBlock(scope, code, options = {}) {
    const dim = dimension(code);
    if (!dim) return "";
    const selection = selectionFor(scope, code);
    const selectedId = Number(selection?.item_biblioteca_id || 0);
    const items = (dim.itens || []).filter(
      (item) => item.ativo || Number(item.id) === selectedId,
    );
    return `<article class="alma-dimension-block" data-scope="${scope}" data-dimension="${code}" data-original-item="${selectedId}">
      <div class="alma-dimension-title"><div><span class="alma-kicker">${esc(options.group || dim.pilar_nome)}</span><h4>${esc(dim.nome)}</h4></div>${scope === "image" && selectedId && canEdit() && !state.dirtyImage ? `<button type="button" class="alma-btn compact" data-apply-dimension="${code}"><i class="ri-share-forward-line"></i> Aplicar para outras imagens</button>` : ""}</div>
      <label class="alma-field"><span>Item</span><select data-alma-item ${canEdit() ? "" : "disabled"}><option value="">Selecione...</option>${items.map((item) => `<option value="${item.id}" ${Number(item.id) === selectedId ? "selected" : ""}>${esc(item.titulo)}</option>`).join("")}</select></label>
      <div class="alma-reference-heading"><strong>Referências SIRE</strong><small>Somente as selecionadas para este bloco</small></div>
      ${referenceCards(scope, code)}
      ${canEdit() ? `<button type="button" class="alma-btn reference-add" data-add-reference="${code}" data-scope="${scope}" ${selectedId ? "" : "disabled"}><i class="ri-image-add-line"></i> Adicionar referências</button>` : ""}
    </article>`;
  }

  const pillarIcons = {
    atmosfera: "ri-sparkling-2-line",
    arquitetura: "ri-building-4-line",
    materialidade: "ri-landscape-line",
    luz: "ri-sun-line",
    lifestyle: "ri-heart-3-line",
    fotografia_direcao: "ri-camera-3-line",
    composicao: "ri-layout-4-line",
  };

  function pillarTab(key, label, itemLabel) {
    const active = state.activePillar === key;
    return `<button type="button" class="alma-pillar-tab ${active ? "is-active" : ""}" data-pillar-toggle="${key}" role="tab" aria-selected="${active ? "true" : "false"}"><span class="alma-pillar-icon"><i class="${pillarIcons[key] || "ri-checkbox-blank-circle-line"}"></i></span><span class="alma-pillar-tab-label"><strong>${esc(label)}</strong><span class="alma-pillar-tab-value ${itemLabel === "Não definido" ? "is-empty" : ""}">${esc(itemLabel)}</span></span></button>`;
  }

  function pillarPanel(key, content) {
    return `<section class="alma-pillar-panel" data-pillar-panel="${key}" ${state.activePillar === key ? "" : "hidden"}>${content}</section>`;
  }

  function globalPillarPanel(code) {
    const dim = dimension(code);
    if (!dim) return "";
    const selection = selectionFor("project", code);
    const selectedId = Number(selection?.item_biblioteca_id || 0);
    const items = (dim.itens || []).filter(
      (item) => item.ativo || Number(item.id) === selectedId,
    );
    const itemLabel = selection?.item_titulo || "Não definido";
    return pillarPanel(
      code,
      `<div class="alma-inherited-block"><label class="alma-field"><span>Item</span><select disabled data-inherited-item><option value="">Selecione...</option>${items.map((item) => `<option value="${item.id}" ${Number(item.id) === selectedId ? "selected" : ""}>${esc(item.titulo)}</option>`).join("")}</select></label><div class="alma-reference-heading"><strong>Referências SIRE</strong><small>Somente as selecionadas para este bloco</small></div>${referenceCards("project", code)}</div>`,
    );
  }

  function imagePillarPanel(code) {
    const dim = dimension(code);
    if (!dim) return "";
    const itemLabel =
      selectionFor("image", code)?.item_titulo || "Não definido";
    return pillarPanel(code, dimensionBlock("image", code));
  }

  function lightPillarPanel() {
    const moment =
      selectionFor("image", "luz_momento")?.item_titulo || "Não definido";
    const language =
      selectionFor("image", "luz_linguagem")?.item_titulo || "Não definido";
    const itemLabel =
      moment === "Não definido" && language === "Não definido"
        ? "Não definido"
        : `${moment} · ${language}`;
    return pillarPanel(
      "luz",
      `<div class="alma-light-group"><div class="alma-light-group-head"><span class="alma-kicker">Luz</span><h3>Duas decisões independentes</h3></div>${dimensionBlock("image", "luz_momento", { group: "Luz" })}${dimensionBlock("image", "luz_linguagem", { group: "Luz" })}</div>`,
    );
  }

  function imagePillarSwitcher() {
    const labels = {
      atmosfera: [
        "Atmosfera",
        selectionFor("image", "atmosfera")?.item_titulo || "Não definido",
      ],
      arquitetura: [
        "Arquitetura",
        selectionFor("project", "arquitetura")?.item_titulo || "Não definido",
      ],
      materialidade: [
        "Materialidade",
        selectionFor("project", "materialidade")?.item_titulo || "Não definido",
      ],
      luz: [
        "Luz",
        (() => {
          const moment =
            selectionFor("image", "luz_momento")?.item_titulo || "Não definido";
          const language =
            selectionFor("image", "luz_linguagem")?.item_titulo ||
            "Não definido";
          return moment === "Não definido" && language === "Não definido"
            ? "Não definido"
            : `${moment} · ${language}`;
        })(),
      ],
      lifestyle: [
        "Lifestyle",
        selectionFor("project", "lifestyle")?.item_titulo || "Não definido",
      ],
      fotografia_direcao: [
        "Fotografia",
        selectionFor("image", "fotografia_direcao")?.item_titulo ||
          "Não definido",
      ],
      composicao: [
        "Composição",
        selectionFor("image", "composicao")?.item_titulo || "Não definido",
      ],
    };
    return `<div class="alma-pillar-switcher" aria-label="Pilares da direção visual"><nav class="alma-pillar-tabs" role="tablist">${Object.entries(
      labels,
    )
      .map(([key, values]) => pillarTab(key, values[0], values[1]))
      .join(
        "",
      )}</nav><div class="alma-pillar-detail">${globalPillarPanel("arquitetura")}${globalPillarPanel("materialidade")}${globalPillarPanel("lifestyle")}${imagePillarPanel("atmosfera")}${lightPillarPanel()}${imagePillarPanel("fotografia_direcao")}${imagePillarPanel("composicao")}</div></div>`;
  }

  function projectSection() {
    return `<section class="alma-section alma-project-section"><div class="alma-section-head"><div><span class="alma-kicker">Direção herdada por todas as imagens</span><h2>ALMA do projeto</h2><p>Arquitetura, Materialidade e Lifestyle são definidos uma única vez na obra.</p></div>${canEdit() ? '<button class="alma-btn primary" type="button" id="almaSaveProject"><i class="ri-save-line"></i> Salvar projeto</button>' : ""}</div><div class="alma-global-grid">${PROJECT_CODES.map((code) => dimensionBlock("project", code)).join("")}</div></section>`;
  }

  function imageNavigation() {
    const images = state.context.imagens || [];
    return `<aside class="alma-image-nav"><div class="alma-image-nav-head"><span class="alma-kicker">ALMA das imagens</span><strong>${images.length} imagens elegíveis</strong></div><div class="alma-image-list">${images
      .map((image, index) => {
        const meta = statusMeta[image.alma_status] || statusMeta.NAO_INICIADO;
        return `<button type="button" class="alma-image-option ${Number(image.imagem_id) === state.imageId ? "active" : ""}" data-image-id="${image.imagem_id}"><span class="alma-image-index">${String(index + 1).padStart(2, "0")}</span><span><strong>${esc(image.imagem_nome)}</strong><small>${esc(image.tipo_imagem || "Sem tipo")}</small></span><i class="alma-status-dot is-${meta[1]}" title="${meta[0]}"></i></button>`;
      })
      .join("")}</div></aside>`;
  }

  function imageEditor() {
    const image = currentImage();
    if (!image)
      return '<section class="alma-card alma-empty"><h2>Nenhuma imagem elegível</h2><p>Plantas Humanizadas não participam da configuração ALMA.</p></section>';
    const meta = statusMeta[image.alma_status] || statusMeta.NAO_INICIADO;
    return `<section class="alma-image-editor"><header class="alma-card alma-image-editor-head"><div><span class="alma-kicker">Imagem selecionada</span><h2>${esc(image.imagem_nome)}</h2><p>${esc(image.tipo_imagem || "")}</p></div><span class="alma-status-badge is-${meta[1]}">${meta[0]}</span></header>
      <div class="alma-image-actions">${canEdit() ? '<button type="button" class="alma-btn" id="almaUseBase"><i class="ri-file-copy-line"></i> Usar outra imagem como base</button>' : ""}<button type="button" class="alma-btn" id="almaHistory"><i class="ri-history-line"></i> Histórico</button></div>
      <label class="alma-card alma-field alma-intention"><span>Intenção Geral <small>opcional</small></span><textarea id="almaIntention" ${canEdit() ? "" : "disabled"} placeholder="Uma intenção breve para esta imagem, se for útil.">${esc(state.payload?.revisao?.intencao_geral || "")}</textarea></label>
      ${imagePillarSwitcher()}
      ${canEdit() ? '<div class="alma-sticky-save"><span>Referências e itens são persistidos juntos.</span><button type="button" class="alma-btn primary" id="almaSaveImage"><i class="ri-save-line"></i> Salvar ALMA da imagem</button></div>' : ""}</section>`;
  }

  function render() {
    const obra = state.context.obra;
    app.innerHTML = `<div class="alma-shell"><header class="alma-topbar"><div><a class="alma-back" href="../Dashboard/obra.php"><i class="ri-arrow-left-line"></i> Voltar à obra</a><div class="alma-breadcrumb">${esc(obra.nomenclatura || obra.nome_obra)}</div><div class="alma-title-row"><h1>Direção Visual — ALMA</h1></div><p class="alma-byline">Configuração operacional do projeto e de suas imagens.</p></div></header>${projectSection()}<section class="alma-section"><div class="alma-section-head"><div><span class="alma-kicker">Configuração por imagem</span><h2>ALMA das imagens</h2><p>Atmosfera, Luz, Fotografia e Composição variam por imagem.</p></div></div><div class="alma-workspace">${imageNavigation()}${imageEditor()}</div></section></div>`;
    bind();
    notifyHeight();
  }

  function readBlocks(scope, codes) {
    const selections = [],
      references = [];
    for (const code of codes) {
      const block = app.querySelector(
        `[data-scope="${scope}"][data-dimension="${code}"]`,
      );
      const itemId = Number(
        block?.querySelector("[data-alma-item]")?.value || 0,
      );
      if (!itemId) continue;
      selections.push({ dimensao_codigo: code, item_biblioteca_id: itemId });
      for (const reference of refsFor(scope, code))
        references.push({
          dimensao_codigo: code,
          sire_referencia_id: Number(reference.sire_referencia_id),
        });
    }
    return { selections, references };
  }

  async function saveProject(button) {
    const values = readBlocks("project", PROJECT_CODES);
    try {
      busy(button, true, "Salvando...");
      const result = await api("salvar_projeto", {
        method: "POST",
        body: {
          obra_id: state.obraId,
          biblioteca_versao_id: state.library.versao.id,
          lock_version: state.context.projeto?.lock_version || 0,
          selecoes: values.selections,
          referencias: values.references,
        },
      });
      state.context.projeto = result.projeto;
      state.dirtyProject = false;
      hydrateReferences();
      toast("ALMA do projeto salvo e herdado pelas imagens.");
      render();
    } catch (error) {
      toast(error.message, true);
    } finally {
      busy(button, false);
    }
  }

  async function ensureDraft() {
    if (state.payload?.revisao?.estado === "RASCUNHO") return;
    state.payload = await api("criar_revisao", {
      method: "POST",
      body: {
        imagem_id: state.imageId,
        revisao_origem_id: state.payload?.revisao?.id || null,
      },
    });
  }

  async function saveImage(button) {
    const values = readBlocks("image", IMAGE_CODES);
    try {
      busy(button, true, "Salvando...");
      await ensureDraft();
      state.payload = await api("salvar_revisao", {
        method: "POST",
        body: {
          revisao_id: state.payload.revisao.id,
          lock_version: state.payload.revisao.lock_version,
          intencao_geral: document.getElementById("almaIntention")?.value || "",
          selecoes: values.selections,
          referencias: values.references,
        },
      });
      await refreshContext();
      toast("ALMA da imagem salvo. A direção vigente foi atualizada.");
      render();
    } catch (error) {
      toast(error.message, true);
    } finally {
      busy(button, false);
    }
  }

  async function refreshContext() {
    state.context = await api("obra_contexto", {
      params: { obra_id: state.obraId },
    });
    await loadImage(state.imageId, false);
    hydrateReferences();
    state.dirtyProject = false;
    state.dirtyImage = false;
  }

  function referencePolicy(oldLabel, newLabel) {
    return new Promise((resolve) => {
      openDialog(
        `<section class="alma-confirm"><span class="alma-kicker">Item alterado</span><h2 id="almaDialogTitle">Você alterou o item desta dimensão</h2><p><strong>${esc(oldLabel || "Anterior")}</strong> → <strong>${esc(newLabel || "Novo")}</strong></p><p>As referências podem ser mantidas ou removidas do ALMA. Classificações já existentes no SIRE não serão apagadas.</p><div class="alma-actions"><button class="alma-btn" data-reference-policy="clear">Limpar referências</button><button class="alma-btn primary" data-reference-policy="keep">Manter referências</button></div></section>`,
      );
      document.querySelectorAll("[data-reference-policy]").forEach((button) =>
        button.addEventListener("click", () => {
          closeDialog();
          resolve(button.dataset.referencePolicy);
        }),
      );
    });
  }

  async function itemChanged(select) {
    const block = select.closest("[data-dimension]"),
      scope = block.dataset.scope,
      code = block.dataset.dimension;
    const oldId = Number(block.dataset.originalItem || 0),
      newId = Number(select.value || 0);
    if (oldId !== newId && refsFor(scope, code).length) {
      const oldLabel =
        [...select.options].find((option) => Number(option.value) === oldId)
          ?.textContent || "Item anterior";
      const policy = await referencePolicy(
        oldLabel,
        select.selectedOptions[0]?.textContent || "Sem item",
      );
      if (policy === "clear") setRefs(scope, code, []);
    }
    setSelection(scope, code, newId);
    block.dataset.originalItem = String(newId);
    if (scope === "project") state.dirtyProject = true;
    else state.dirtyImage = true;
    render();
  }

  function pickerCard(reference) {
    const checked = state.picker.selected.has(Number(reference.id));
    return `<button type="button" class="alma-sire-card ${checked ? "selected" : ""}" data-picker-reference="${reference.id}"><span class="alma-sire-check">${checked ? "✓" : ""}</span><img src="${attr(reference.thumbnail_url)}" alt="" loading="lazy"><strong>${esc(reference.titulo_exibicao)}</strong><small>${esc(reference.obra_nomenclatura || reference.ambiente || "SIRE")}</small></button>`;
  }

  async function loadPickerPage() {
    const results = document.getElementById("almaSireResults");
    results.innerHTML =
      '<div class="alma-loading"><span></span><p>Carregando referências...</p></div>';
    try {
      const data = await api("sire_seletor", {
        params: {
          biblioteca_versao_id: state.library.versao.id,
          dimensao_codigo: state.picker.code,
          item_id: state.picker.itemId,
          q: state.picker.query,
          page: state.picker.page,
          golden: state.picker.golden ? 1 : 0,
          selected: [...state.picker.selected].join(","),
        },
      });
      state.picker.hasMore = data.has_more;
      for (const reference of [...data.relacionadas, ...data.outras])
        state.picker.catalog.set(Number(reference.id), reference);
      results.innerHTML = `<section class="alma-picker-section"><h3>Referências relacionadas a ${esc(data.item.titulo)}</h3>${data.relacionadas.length ? `<div class="alma-sire-grid">${data.relacionadas.map(pickerCard).join("")}</div>` : '<p class="alma-no-references">Nenhuma referência relacionada neste resultado.</p>'}</section><section class="alma-picker-section"><h3>Outras referências</h3>${data.outras.length ? `<div class="alma-sire-grid">${data.outras.map(pickerCard).join("")}</div>` : '<p class="alma-no-references">Nenhuma outra referência encontrada.</p>'}</section><div class="alma-pagination"><button class="alma-btn" type="button" data-picker-page="prev" ${state.picker.page === 1 ? "disabled" : ""}>Anterior</button><span>Página ${state.picker.page}</span><button class="alma-btn" type="button" data-picker-page="next" ${state.picker.hasMore ? "" : "disabled"}>Próxima</button></div>`;
      notifyHeight();
    } catch (error) {
      results.innerHTML = `<p class="alma-error">${esc(error.message)}</p>`;
    }
  }

  async function openPicker(scope, code) {
    const block = app.querySelector(
        `[data-scope="${scope}"][data-dimension="${code}"]`,
      ),
      itemId = Number(block?.querySelector("[data-alma-item]")?.value || 0);
    if (!itemId)
      return toast("Selecione o item antes de adicionar referências.", true);
    const selected = new Set(
      refsFor(scope, code).map((reference) =>
        Number(reference.sire_referencia_id),
      ),
    );
    const catalog = new Map(
      refsFor(scope, code).map((reference) => [
        Number(reference.sire_referencia_id),
        { ...reference, id: Number(reference.sire_referencia_id) },
      ]),
    );
    state.picker = {
      scope,
      code,
      itemId,
      selected,
      catalog,
      query: "",
      golden: false,
      page: 1,
      hasMore: false,
    };
    openDialog(
      `<section class="alma-sire-picker"><span class="alma-kicker">Biblioteca visual SIRE</span><h2 id="almaDialogTitle">Adicionar referências</h2><div class="alma-picker-filters"><label class="alma-field"><span>Busca</span><input id="almaSireQuery" type="search" placeholder="Título, obra, ambiente ou arquivo"></label><label class="alma-check"><input id="almaSireGolden" type="checkbox"> Somente Golden Samples</label></div><div id="almaSireResults"></div><div class="alma-dialog-footer"><span id="almaPickerCount">${selected.size} selecionadas</span><button class="alma-btn primary" type="button" id="almaConfirmReferences">Confirmar seleção</button></div></section>`,
    );
    await loadPickerPage();
    let timer;
    document
      .getElementById("almaSireQuery")
      .addEventListener("input", (event) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
          state.picker.query = event.target.value;
          state.picker.page = 1;
          loadPickerPage();
        }, 280);
      });
    document
      .getElementById("almaSireGolden")
      .addEventListener("change", (event) => {
        state.picker.golden = event.target.checked;
        state.picker.page = 1;
        loadPickerPage();
      });
    document
      .getElementById("almaConfirmReferences")
      .addEventListener("click", () => {
        const refs = [...state.picker.selected]
          .map((id) => state.picker.catalog.get(id))
          .filter(Boolean)
          .map((reference) => ({
            ...reference,
            sire_referencia_id: Number(
              reference.sire_referencia_id || reference.id,
            ),
          }));
        setRefs(scope, code, refs);
        closeDialog();
        render();
      });
  }

  async function openHistory() {
    openDialog(
      '<section><span class="alma-kicker">Auditoria</span><h2 id="almaDialogTitle">Histórico da imagem</h2><div id="almaHistoryList" class="alma-history"><div class="alma-loading"><span></span></div></div></section>',
    );
    try {
      const data = await api("historico", {
        params: { imagem_id: state.imageId },
      });
      document.getElementById("almaHistoryList").innerHTML = data.eventos.length
        ? data.eventos
            .map(
              (event) =>
                `<article class="alma-card alma-event"><time>${esc(event.criado_em)}</time><div><strong>${esc(event.ator)} · ${esc(event.acao.replaceAll("_", " ").toLowerCase())}</strong><p>${event.revisao_numero ? `Revisão ${event.revisao_numero}` : "Direção visual"}</p></div></article>`,
            )
            .join("")
        : '<p class="alma-no-references">Nenhuma alteração registrada.</p>';
    } catch (error) {
      toast(error.message, true);
    }
  }

  async function openUseBase() {
    if (state.dirtyImage)
      return toast(
        "Salve ou descarte as alterações da imagem antes de usar uma base.",
        true,
      );
    const options = (state.context.imagens || []).filter(
      (image) => Number(image.imagem_id) !== state.imageId,
    );
    openDialog(
      `<section class="alma-copy-dialog"><span class="alma-kicker">Reaproveitamento consciente</span><h2 id="almaDialogTitle">Usar outra imagem como base</h2><label class="alma-field"><span>Imagem da mesma obra</span><select id="almaBaseImage"><option value="">Selecione...</option>${options.map((image) => `<option value="${image.imagem_id}">${esc(image.imagem_nome)}</option>`).join("")}</select></label><div id="almaBaseBlocks" class="alma-copy-blocks"><p class="alma-no-references">Selecione uma imagem base.</p></div><div class="alma-dialog-footer"><button class="alma-btn primary" id="almaApplyBase" type="button" disabled>Aplicar como base</button></div></section>`,
    );
    document
      .getElementById("almaBaseImage")
      .addEventListener("change", async (event) => {
        const sourceId = Number(event.target.value || 0),
          host = document.getElementById("almaBaseBlocks");
        if (!sourceId) return;
        host.innerHTML = '<div class="alma-loading"><span></span></div>';
        try {
          const source = await api("direcao", {
            params: { imagem_id: sourceId },
          });
          const sourceMap = Object.fromEntries(
            (source.revisao?.selecoes || []).map((selection) => [
              selection.dimensao_codigo,
              selection,
            ]),
          );
          const currentMap = Object.fromEntries(
            (state.payload?.revisao?.selecoes || []).map((selection) => [
              selection.dimensao_codigo,
              selection,
            ]),
          );
          host.innerHTML = IMAGE_CODES.map((code) => {
            const dim = dimension(code),
              base = sourceMap[code],
              current = currentMap[code],
              available = Boolean(base?.item_biblioteca_id);
            const conflict =
              available &&
              current?.item_biblioteca_id &&
              (Number(current.item_biblioteca_id) !==
                Number(base.item_biblioteca_id) ||
                JSON.stringify(
                  (current.referencias || [])
                    .map((ref) => Number(ref.sire_referencia_id))
                    .sort(),
                ) !==
                  JSON.stringify(
                    (base.referencias || [])
                      .map((ref) => Number(ref.sire_referencia_id))
                      .sort(),
                  ));
            return `<article class="alma-copy-row ${available ? "" : "disabled"}"><label><input type="checkbox" data-copy-code="${code}" ${available ? "" : "disabled"}> <strong>${esc(dim?.nome || code)}</strong></label><span>${available ? `${esc(current?.item_titulo || "Sem definição")} ${conflict ? "→" : "="} ${esc(base.item_titulo)}` : "Não definida na imagem base"}</span>${conflict ? `<label class="alma-conflict-confirm"><input type="checkbox" data-confirm-copy="${code}"> Confirmo a substituição</label>` : ""}</article>`;
          }).join("");
          host.dataset.sourceId = String(sourceId);
          document.getElementById("almaApplyBase").disabled = false;
        } catch (error) {
          host.innerHTML = `<p class="alma-error">${esc(error.message)}</p>`;
        }
      });
    document
      .getElementById("almaApplyBase")
      .addEventListener("click", async (event) => {
        const codes = [
          ...document.querySelectorAll("[data-copy-code]:checked"),
        ].map((input) => input.dataset.copyCode);
        if (!codes.length) return toast("Escolha ao menos uma dimensão.", true);
        if (
          codes.some(
            (code) =>
              document.querySelector(`[data-confirm-copy="${code}"]`) &&
              !document.querySelector(`[data-confirm-copy="${code}"]`).checked,
          )
        )
          return toast("Confirme as substituições indicadas.", true);
        try {
          busy(event.currentTarget, true, "Aplicando...");
          await api("usar_imagem_base", {
            method: "POST",
            body: {
              imagem_origem_id: Number(
                document.getElementById("almaBaseBlocks").dataset.sourceId,
              ),
              imagem_destino_id: state.imageId,
              dimensoes: codes,
              confirmar_conflitos: true,
            },
          });
          closeDialog();
          await refreshContext();
          render();
          toast("Dimensões selecionadas copiadas da imagem base.");
        } catch (error) {
          toast(error.message, true);
          busy(event.currentTarget, false);
        }
      });
  }

  async function openApplyDimension(code) {
    const block = app.querySelector(
        `[data-scope="image"][data-dimension="${code}"]`,
      ),
      itemId = Number(block.querySelector("[data-alma-item]").value || 0);
    const item = (dimension(code)?.itens || []).find(
      (candidate) => Number(candidate.id) === itemId,
    );
    if (!item) return toast("Salve a dimensão antes de aplicá-la.", true);
    const sourceRefs = refsFor("image", code)
      .map((ref) => Number(ref.sire_referencia_id))
      .sort();
    const targets = (state.context.imagens || []).filter(
      (image) => Number(image.imagem_id) !== state.imageId,
    );
    openDialog(
      `<section class="alma-copy-dialog"><span class="alma-kicker">Aplicação em lote</span><h2 id="almaDialogTitle">Aplicar “${esc(dimension(code)?.nome)} = ${esc(item.titulo)}”</h2><div class="alma-copy-blocks">${targets
        .map((image) => {
          const current = image.dimensoes?.[code];
          const conflict =
            current &&
            (Number(current.item_id) !== itemId ||
              JSON.stringify([...(current.referencias || [])].sort()) !==
                JSON.stringify(sourceRefs));
          return `<article class="alma-copy-row"><label><input type="checkbox" data-apply-target="${image.imagem_id}"> <strong>${esc(image.imagem_nome)}</strong></label><span>${esc(current?.titulo || "Sem definição")}${conflict ? ` → ${esc(item.titulo)}` : ""}</span>${conflict ? `<label class="alma-conflict-confirm"><input type="checkbox" data-confirm-target="${image.imagem_id}"> Confirmo substituir</label>` : ""}</article>`;
        })
        .join(
          "",
        )}</div><div class="alma-dialog-footer"><button class="alma-btn primary" id="almaConfirmApply" type="button">Aplicar</button></div></section>`,
    );
    document
      .getElementById("almaConfirmApply")
      .addEventListener("click", async (event) => {
        const ids = [
          ...document.querySelectorAll("[data-apply-target]:checked"),
        ].map((input) => Number(input.dataset.applyTarget));
        if (!ids.length) return toast("Selecione ao menos uma imagem.", true);
        if (
          ids.some(
            (id) =>
              document.querySelector(`[data-confirm-target="${id}"]`) &&
              !document.querySelector(`[data-confirm-target="${id}"]`).checked,
          )
        )
          return toast("Confirme cada substituição em conflito.", true);
        try {
          busy(event.currentTarget, true, "Aplicando...");
          await api("aplicar_dimensao", {
            method: "POST",
            body: {
              imagem_origem_id: state.imageId,
              dimensao_codigo: code,
              imagens_destino_ids: ids,
              conflitos_confirmados_ids: ids.filter((id) =>
                document.querySelector(`[data-confirm-target="${id}"]`),
              ),
            },
          });
          closeDialog();
          await refreshContext();
          render();
          toast("Dimensão aplicada às imagens selecionadas.");
        } catch (error) {
          toast(error.message, true);
          busy(event.currentTarget, false);
        }
      });
  }

  function bind() {
    document
      .getElementById("almaSaveProject")
      ?.addEventListener("click", (event) => saveProject(event.currentTarget));
    document
      .getElementById("almaSaveImage")
      ?.addEventListener("click", (event) => saveImage(event.currentTarget));
    document
      .getElementById("almaUseBase")
      ?.addEventListener("click", openUseBase);
    document
      .getElementById("almaHistory")
      ?.addEventListener("click", openHistory);
    document
      .getElementById("almaIntention")
      ?.addEventListener("input", (event) => {
        state.payload.revisao.intencao_geral = event.target.value;
        state.dirtyImage = true;
      });
    document.querySelectorAll("[data-pillar-toggle]").forEach((button) =>
      button.addEventListener("click", () => {
        state.activePillar = button.dataset.pillarToggle;
        document.querySelectorAll("[data-pillar-toggle]").forEach((tab) => {
          const active = tab.dataset.pillarToggle === state.activePillar;
          tab.classList.toggle("is-active", active);
          tab.setAttribute("aria-selected", active ? "true" : "false");
        });
        document.querySelectorAll("[data-pillar-panel]").forEach((panel) => {
          panel.hidden = panel.dataset.pillarPanel !== state.activePillar;
        });
        notifyHeight();
      }),
    );
    document
      .querySelectorAll("[data-alma-item]")
      .forEach((select) =>
        select.addEventListener("change", () => itemChanged(select)),
      );
    document
      .querySelectorAll("[data-add-reference]")
      .forEach((button) =>
        button.addEventListener("click", () =>
          openPicker(button.dataset.scope, button.dataset.addReference),
        ),
      );
    document.querySelectorAll("[data-remove-reference]").forEach((button) =>
      button.addEventListener("click", () => {
        setRefs(
          button.dataset.scope,
          button.dataset.code,
          refsFor(button.dataset.scope, button.dataset.code).filter(
            (reference) =>
              Number(reference.sire_referencia_id) !==
              Number(button.dataset.removeReference),
          ),
        );
        render();
      }),
    );
    document
      .querySelectorAll(".alma-image-option[data-image-id]")
      .forEach((button) =>
        button.addEventListener("click", async () => {
          if (
            state.dirtyImage &&
            !window.confirm(
              "Há alterações não salvas nesta imagem. Deseja trocar mesmo assim?",
            )
          )
            return;
          try {
            await loadImage(Number(button.dataset.imageId));
          } catch (error) {
            toast(error.message, true);
          }
        }),
      );
    document
      .querySelectorAll("[data-apply-dimension]")
      .forEach((button) =>
        button.addEventListener("click", () =>
          openApplyDimension(button.dataset.applyDimension),
        ),
      );
  }

  document
    .querySelector("[data-close-dialog]")
    ?.addEventListener("click", closeDialog);
  document.getElementById("almaDialog")?.addEventListener("click", (event) => {
    if (event.target.id === "almaDialog") closeDialog();
    const card = event.target.closest("[data-picker-reference]");
    if (card && state.picker) {
      const id = Number(card.dataset.pickerReference);
      if (state.picker.selected.has(id)) state.picker.selected.delete(id);
      else state.picker.selected.add(id);
      card.classList.toggle("selected", state.picker.selected.has(id));
      card.querySelector(".alma-sire-check").textContent =
        state.picker.selected.has(id) ? "✓" : "";
      document.getElementById("almaPickerCount").textContent =
        `${state.picker.selected.size} selecionadas`;
    }
    const page = event.target.closest("[data-picker-page]");
    if (page && state.picker) {
      state.picker.page += page.dataset.pickerPage === "next" ? 1 : -1;
      loadPickerPage();
    }
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeDialog();
  });
  new ResizeObserver(notifyHeight).observe(document.body);
  load().catch((error) => {
    app.innerHTML = `<section class="alma-card alma-empty"><div class="alma-empty-mark">!</div><h1>Não foi possível abrir o ALMA</h1><p>${esc(error.message)}</p><a class="alma-btn" href="../inicio.php">Voltar ao Flow</a></section>`;
  });
})();
