(() => {
  "use strict";
  const $ = (id) => document.getElementById(id),
    app = $("mapaPavimento"),
    canEdit = app.dataset.canEdit === "1";
  const embedded = app.dataset.embedded === "1" && window.parent !== window;
  const bridgeId =
    app.dataset.bridge || new URLSearchParams(location.search).get("bridge");
  const mapOrigin = new URL(document.baseURI).origin;
  const notifyParent = (type, extra = {}) => {
    if (embedded)
      window.parent.postMessage(
        {
          type,
          bridge: bridgeId,
          obra_id: state.obraId,
          subtipo_id: state.subtypeId,
          ...extra,
        },
        mapOrigin,
      );
  };
  const viewer = new PlantaViewer($("plantaViewport"), { fitPadding: 40 }),
    editor = new PlantaPolygonEditor(viewer),
    cropEditor = new PlantaPolygonEditor(viewer),
    inspector = new PlantaLayerInspector(viewer);
  const state = {
    obraId: 0,
    subtypeId: 0,
    reference: null,
    environments: [],
    images: [],
    documents: [],
    subtypes: [],
    legados: [],
    selectedId: null,
    editing: null,
    geometry: null,
    selectedImages: new Set(),
    busy: false,
    drawing: false,
    preparing: false,
    saving: false,
    layersOpen: false,
    request: 0,
    previewLegacyId: 0,
  };
  const node = (tag, text, className) => {
    const e = document.createElement(tag);
    if (text !== undefined) e.textContent = text;
    if (className) e.className = className;
    return e;
  };
  const button = (text, handler, className = "mp-button mp-secondary") => {
    const e = node("button", text, className);
    e.type = "button";
    e.addEventListener("click", handler);
    return e;
  };
  function message(text = "") {
    $("pageMessage").textContent = text;
    $("pageMessage").hidden = !text;
  }
  function toast(text) {
    $("toast").textContent = text;
    $("toast").hidden = false;
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => {
      $("toast").hidden = true;
    }, 3500);
  }
  function busy(active, text = "Carregando") {
    state.busy = active;
    $("busyOverlay").hidden = !active;
    $("busyText").textContent = text;
    $("busyOverlay").querySelector("canvas").setAttribute("aria-label", text);
    app.setAttribute("aria-busy", String(active));
    controls();
  }
  function controls() {
    $("workspaceProject").textContent = state.obraId
      ? $("obraSelect").selectedOptions[0]?.textContent || "Mapa arquitetônico"
      : "Mapa arquitetônico";
    $("workspaceContext").textContent = state.subtypeId
      ? "Planta · " +
        ($("pavimentoSelect").selectedOptions[0]?.textContent ||
          "Mapa arquitetônico")
      : "Planta · Ambientes e imagens da obra";
    $("planSubtitle").title = $("planSubtitle").textContent;
    const locked =
      state.busy ||
      state.drawing ||
      state.preparing ||
      state.saving ||
      state.layersOpen;
    const configured =
      !!viewer.page && !!state.reference?.subtipo_id && !state.previewLegacyId;
    $("obraSelect").disabled = embedded || locked;
    $("openModel3d").disabled = locked || !state.obraId;
    $("pavimentoSelect").disabled = locked || !state.obraId;
    $("documentSelect").disabled =
      !canEdit ||
      locked ||
      !!state.previewLegacyId ||
      !state.subtypeId ||
      !state.documents.length;
    $("choosePage").disabled =
      !canEdit ||
      locked ||
      !!state.previewLegacyId ||
      !state.subtypeId ||
      !state.reference?.documento_id;
    if (canEdit) {
      $("pdfInput").disabled = locked || !state.obraId;
      $("uploadLabel").classList.toggle("is-disabled", $("pdfInput").disabled);
      $("uploadLabel").setAttribute(
        "aria-disabled",
        String($("pdfInput").disabled),
      );
      $("addEnvironment").disabled = locked || !configured;
      $("configureLayers").disabled =
        locked || !configured || !viewer.getLayers().groups.length;
      $("defineCrop").disabled = locked || !configured;
    }
    $("inspectLayers").disabled =
      state.busy ||
      state.drawing ||
      state.preparing ||
      state.layersOpen ||
      !viewer.page ||
      !viewer.getLayers().groups.length;
    $("rotatePage").disabled = state.busy || !viewer.page;
    $("inspectLayers").textContent = inspector.active
      ? "Encerrar inspeção"
      : "Inspecionar camada";
    $("inspectLayers").setAttribute("aria-pressed", String(!!inspector.active));
    $("referencePreparation").hidden = !viewer.page;
    $("viewTools").hidden = !viewer.page;
    [
      "saveCrop",
      "cancelCrop",
      "fullPageCrop",
      "cropUndo",
      "cropAddVertex",
      "cropRemoveVertex",
      "cropRedraw",
    ].forEach((id) => {
      $(id).disabled = state.busy || state.saving;
    });
    $("saveCrop").disabled =
      state.busy ||
      state.saving ||
      (cropEditor.points.length > 0 && cropEditor.points.length < 3);
    $("cropAddVertex").disabled =
      state.busy || !cropEditor.closed || cropEditor.points.length >= 200;
    $("cropRemoveVertex").disabled =
      state.busy ||
      cropEditor.selected === null ||
      cropEditor.points.length <= 3;
    $("addVertex").disabled = !editor.closed || editor.points.length >= 200;
    $("removeVertex").disabled =
      editor.selected === null || editor.points.length <= 3;
    document
      .querySelectorAll(
        "#environmentDetails button,#environmentList button,#legacyList button",
      )
      .forEach((e) => {
        e.disabled = locked;
      });
  }
  async function api(action, data = {}, method = "GET") {
    const url = new URL("api.php", document.baseURI);
    url.searchParams.set("action", action);
    const options = { method, headers: { Accept: "application/json" } };
    if (method === "GET")
      Object.entries({ obra_id: state.obraId, ...data }).forEach(([k, v]) =>
        url.searchParams.set(k, v),
      );
    else {
      options.headers["X-CSRF-Token"] = app.dataset.csrf;
      if (data instanceof FormData) options.body = data;
      else {
        options.headers["Content-Type"] = "application/json";
        options.body = JSON.stringify({ obra_id: state.obraId, ...data });
      }
    }
    const response = await fetch(url, options),
      result = await response.json().catch(() => {
        throw new Error("Resposta inválida do servidor. Atualize a página.");
      });
    if (!response.ok || !result.sucesso)
      throw new Error(result.erro || "Não foi possível concluir a operação.");
    return result;
  }
  function pdfUrl(id) {
    const url = new URL("api.php", document.baseURI);
    url.search = new URLSearchParams({
      action: "documento_pdf",
      obra_id: state.obraId,
      documento_id: id,
    });
    return url.href;
  }
  function empty(title, description) {
    $("emptyState").hidden = false;
    $("emptyTitle").textContent = title;
    $("emptyDescription").textContent = description;
  }
  function updateContextUrl() {
    if (embedded) return;
    const url = new URL(location.href);
    [
      ["obra_id", state.obraId],
      ["subtipo_id", state.subtypeId],
      ["legado_id", state.previewLegacyId],
    ].forEach(([k, v]) =>
      v ? url.searchParams.set(k, v) : url.searchParams.delete(k),
    );
    url.searchParams.delete("pavimento_id");
    history.replaceState(null, "", url);
  }
  function resetMap() {
    operationalPanel.close();
    inspector.cancel();
    stopCrop(false);
    cancelDrawing();
    viewer.clear();
    state.reference = null;
    state.environments = [];
    state.images = [];
    state.selectedId = null;
    $("environmentDetails").hidden = true;
    $("environmentCount").hidden = true;
    $("choosePage").textContent = "Selecionar página";
    $("documentSelect").value = "";
    renderEnvironments();
    controls();
  }
  function renderDocuments(selected = state.reference?.documento_id) {
    $("documentSelect").replaceChildren(new Option("Selecionar PDF…", ""));
    state.documents.forEach((d) =>
      $("documentSelect").add(
        new Option(
          d.nome_original +
            " · " +
            d.total_paginas +
            (Number(d.total_paginas) === 1 ? " página" : " páginas") +
            " · " +
            d.paginas_utilizadas +
            (Number(d.paginas_utilizadas) === 1 ? " utilizada" : " utilizadas"),
          d.id,
        ),
      ),
    );
    $("documentSelect").value = selected || "";
  }
  function initializeSubtypes(groups, selected = 0) {
    const select = $("pavimentoSelect"),
      jq = window.jQuery;
    if (jq(select).data("select2")) jq(select).select2("destroy");
    select.replaceChildren(new Option("", ""));
    state.subtypes = [...groups.obra, ...groups.demais];
    [
      ["Obra", groups.obra],
      ["Demais subtipos do Flow", groups.demais],
    ].forEach(([label, items]) => {
      if (!items.length) return;
      const g = node("optgroup");
      g.label = label;
      items.forEach((item) => g.append(new Option(item.nome, item.id)));
      select.append(g);
    });
    const normalize = (v) =>
      String(v)
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .trim()
        .toLocaleLowerCase("pt-BR");
    jq(select).select2({
      tags: canEdit,
      placeholder: "Selecione o pavimento / subtipo",
      allowClear: true,
      width: "100%",
      createTag(params) {
        const term = params.term.trim();
        if (
          !term ||
          term.length > 100 ||
          state.subtypes.some((s) =>
            normalize(s.nome).includes(normalize(term)),
          )
        )
          return null;
        return { id: "new:" + term, text: term, newTag: true };
      },
      templateResult(item) {
        if (!item.newTag) return item.text;
        const e = node("span", "+ Criar novo subtipo: " + item.text);
        return e;
      },
      language: {
        noResults: () =>
          canEdit
            ? "Digite para criar um novo subtipo."
            : "Nenhum subtipo encontrado.",
        removeAllItems: () => "Limpar seleção",
        search: () => "Buscar pavimento / subtipo",
      },
    });
    jq(select)
      .next(".select2-container")
      .find(".select2-selection")
      .attr(
        "aria-labelledby",
        "pavimentoLabel select2-pavimentoSelect-container",
      );
    jq(select)
      .off(".mapa")
      .on("change.mapa", () => {
        const value = String(select.value || "");
        if (!value.startsWith("new:")) selectSubtype();
      })
      .on("select2:select.mapa", async (e) => {
        if (!e.params.data.newTag) return;
        busy(true, "Criando subtipo");
        message();
        try {
          const r = await api(
            "subtipo_salvar",
            { nome: e.params.data.text },
            "POST",
          );
          state.subtypes.push(r.subtipo);
          select.querySelector('option[value^="new:"]')?.remove();
          select.add(new Option(r.subtipo.nome, r.subtipo.id, true, true));
          jq(select).val(String(r.subtipo.id)).trigger("change.select2");
          await selectSubtype();
        } catch (error) {
          message(error.message);
          jq(select).val("").trigger("change.select2");
        } finally {
          busy(false);
        }
      });
    if (selected) {
      select.value = selected;
      jq(select).trigger("change.select2");
    }
  }
  function renderLegacies() {
    $("legacySection").hidden = !state.legados.length;
    const list = $("legacyList");
    list.replaceChildren();
    state.legados.forEach((item) => {
      const row = node("div", undefined, "mp-legacy-row");
      row.append(
        node("strong", item.nome),
        button("Visualizar", () => selectLegacy(Number(item.id))),
        ...(canEdit
          ? [
              button("Associar ao subtipo selecionado", () =>
                associateLegacy(item),
              ),
            ]
          : []),
      );
      list.append(row);
    });
  }
  async function selectObra(initialSubtype = 0, legacyId = 0, oldFloor = 0) {
    const request = ++state.request;
    state.obraId = Number($("obraSelect").value);
    state.subtypeId = 0;
    state.previewLegacyId = 0;
    resetMap();
    message();
    updateContextUrl();
    state.documents = [];
    state.legados = [];
    renderDocuments();
    renderLegacies();
    initializeSubtypes({ obra: [], demais: [] });
    $("planTitle").textContent = "Referência arquitetônica";
    $("planSubtitle").textContent = "Documento e página por subtipo.";
    if (!state.obraId) {
      empty("Comece pela obra", "Selecione a obra e o pavimento / subtipo.");
      controls();
      return;
    }
    busy(true, "Buscando documentos e subtipos");
    try {
      const r = await api("contexto");
      if (request !== state.request) return;
      state.documents = r.documentos;
      state.legados = r.legados;
      renderDocuments();
      renderLegacies();
      if (oldFloor) {
        const m = r.migracoes.find((m) => Number(m.pavimento_id) === oldFloor);
        if (m) {
          initialSubtype = Number(m.subtipo_id);
          legacyId = m.subtipo_id ? 0 : Number(m.id);
        }
      }
      initializeSubtypes(r.subtipos, initialSubtype);
      empty(
        state.documents.length
          ? "Selecione um pavimento / subtipo"
          : "Nenhum documento arquitetônico enviado.",
        state.documents.length
          ? "Escolha o subtipo para abrir ou vincular uma página."
          : "Selecione o subtipo e use + Enviar PDF para enviar o primeiro documento.",
      );
      if (legacyId) await selectLegacy(legacyId);
      else if (initialSubtype) await selectSubtype();
    } catch (error) {
      if (request === state.request) {
        message(error.message);
        notifyParent("mp:error", { message: error.message });
      }
    } finally {
      if (request === state.request) busy(false);
    }
  }
  async function loadMap(data, request) {
    state.reference = data.referencia;
    state.environments = data.ambientes;
    if (state.reference) {
      const imageData = await api("imagens", {
        subtipo_id: Number(state.reference.subtipo_id || 0),
        referencia_id: state.reference.id,
      });
      if (request !== state.request) return;
      state.images = imageData.imagens;
      renderDocuments(state.reference.documento_id);
      $("choosePage").textContent = state.reference.documento_id
        ? "Página " +
          state.reference.pagina +
          " de " +
          state.reference.total_paginas
        : "Selecionar página";
      if (state.reference.documento_id) {
        const transfer =
          state.transfer?.id === Number(state.reference.documento_id)
            ? state.transfer.task
            : null;
        state.transfer = null;
        if (
          !(await viewer.load(
            pdfUrl(state.reference.documento_id),
            state.reference,
            transfer,
          )) ||
          request !== state.request
        )
          return;
        $("emptyState").hidden = true;
        viewer.setEnvironments(state.environments);
        renderPreparation();
        $("planSubtitle").textContent =
          state.reference.nome_original + " · Página " + state.reference.pagina;
      } else
        empty(
          "Este pavimento ainda não possui uma página vinculada.",
          "Selecione um documento arquitetônico.",
        );
    } else
      empty(
        "Este pavimento ainda não possui uma página vinculada.",
        state.documents.length
          ? "Selecione um PDF existente ou envie um novo documento."
          : "Nenhum documento arquitetônico enviado. Use + Enviar PDF.",
      );
    renderEnvironments();
  }
  async function selectSubtype() {
    const request = ++state.request;
    state.subtypeId = Number($("pavimentoSelect").value);
    notifyParent("mp:context-change", {
      subtipo_nome:
        $("pavimentoSelect").selectedOptions[0]?.textContent ||
        "Selecione um pavimento",
    });
    state.previewLegacyId = 0;
    resetMap();
    message();
    updateContextUrl();
    const subtype = state.subtypes.find(
      (s) => Number(s.id) === state.subtypeId,
    );
    $("planTitle").textContent = subtype?.nome || "Referência arquitetônica";
    if (!state.subtypeId) {
      empty(
        "Selecione um pavimento / subtipo",
        "Use os subtipos do Flow ou digite um novo nome no seletor.",
      );
      busy(false);
      return;
    }
    busy(true, "Carregando mapa arquitetônico");
    try {
      const map = await api("mapa", { subtipo_id: state.subtypeId });
      if (request !== state.request) return;
      await loadMap(map, request);
    } catch (error) {
      if (request === state.request) {
        message(error.message);
        viewer.clear();
        notifyParent("mp:error", { message: error.message });
      }
    } finally {
      if (request === state.request) {
        busy(false);
        if (viewer.page) {
          notifyParent("mp:ready", {
            referencia_id: Number(state.reference?.id || 0),
          });
        } else if (embedded) {
          notifyParent("mp:error", {
            message:
              "Este pavimento ainda não possui um Mapa de Pavimento disponível.",
          });
        }
      }
    }
  }
  async function selectLegacy(id) {
    const request = ++state.request;
    state.previewLegacyId = id;
    resetMap();
    updateContextUrl();
    busy(true, "Abrindo configuração anterior");
    message();
    try {
      const map = await api("mapa", { legado_id: id });
      if (request !== state.request) return;
      await loadMap(map, request);
      $("planTitle").textContent =
        "Configuração anterior: " +
        state.legados.find((l) => Number(l.id) === id)?.nome;
    } catch (error) {
      message(error.message);
    } finally {
      if (request === state.request) busy(false);
    }
  }
  async function associateLegacy(item) {
    if (!state.subtypeId)
      return message("Selecione o subtipo que deve receber esta configuração.");
    const subtype = state.subtypes.find(
      (s) => Number(s.id) === state.subtypeId,
    );
    const confirm = await FlowAlert.confirm({
      title: "Associar configuração anterior?",
      message:
        "Associar “" +
        item.nome +
        "” a “" +
        subtype.nome +
        "”, preservando PDF, ambientes e vínculos anteriores?",
      confirmText: "Associar configuração",
    });
    if (!confirm.isConfirmed) return;
    busy(true, "Associando configuração");
    try {
      await api(
        "legado_associar",
        { referencia_id: Number(item.id), subtipo_id: state.subtypeId },
        "POST",
      );
      await selectObra(state.subtypeId);
      toast("Configuração associada.");
    } catch (error) {
      message(error.message);
    } finally {
      busy(false);
    }
  }
  let pageTask = null,
    pageObserver = null;
  const thumbnailTasks = new Set();
  function closePageResources() {
    pageObserver?.disconnect();
    pageObserver = null;
    thumbnailTasks.forEach((task) => task.cancel());
    thumbnailTasks.clear();
    if (pageTask) {
      pageTask.destroy().catch(() => {});
      pageTask = null;
    }
    $("documentSelect").value = state.reference?.documento_id || "";
  }
  async function openPages(documentId) {
    if (!canEdit || !state.subtypeId) return;
    inspector.cancel();
    busy(true, "Carregando páginas");
    message();
    $("pagesError").hidden = true;
    try {
      const data = await api("paginas", { documento_id: documentId });
      const doc = state.documents.find((d) => Number(d.id) === documentId);
      if (Number(data.documento.total_paginas) === 1) {
        await bindPage(documentId, 1, data.usos);
        return;
      }
      closePageResources();
      pageTask = pdfjsLib.getDocument({ url: pdfUrl(documentId) });
      const pdf = await pageTask.promise;
      $("pagesDocumentName").textContent =
        doc.nome_original + " · " + pdf.numPages + " páginas";
      $("pagesList").replaceChildren();
      for (let n = 1; n <= pdf.numPages; n++) {
        const uses = data.usos.filter((u) => Number(u.pagina) === n),
          card = button(
            "",
            () => bindPage(documentId, n, data.usos),
            "mp-page-card",
          );
        card.dataset.page = n;
        card.append(
          node("canvas"),
          node("strong", "Página " + n),
          node(
            "span",
            uses.length
              ? "Usada em: " + uses.map((u) => u.nome).join(", ")
              : "Disponível",
          ),
        );
        if (
          state.reference &&
          Number(state.reference.documento_id) === documentId &&
          Number(state.reference.pagina) === n
        ) {
          card.classList.add("is-current");
          card.setAttribute("aria-current", "true");
        }
        $("pagesList").append(card);
      }
      $("pagesDialog").showModal();
      pageObserver = new IntersectionObserver(
        (entries) =>
          entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            pageObserver.unobserve(entry.target);
            const captured = pageTask,
              card = entry.target;
            (async () => {
              const page = await pdf.getPage(Number(card.dataset.page));
              if (pageTask !== captured) return;
              const base = page.getViewport({ scale: 1 }),
                vp = page.getViewport({
                  scale: Math.min(240 / base.width, 180 / base.height),
                }),
                c = card.querySelector("canvas");
              c.width = Math.ceil(vp.width);
              c.height = Math.ceil(vp.height);
              const task = page.render({
                canvasContext: c.getContext("2d"),
                viewport: vp,
              });
              thumbnailTasks.add(task);
              try {
                await task.promise;
              } finally {
                thumbnailTasks.delete(task);
              }
            })().catch((error) => {
              if (
                error.name !== "RenderingCancelledException" &&
                pageTask === captured
              )
                card.append(
                  node("span", "Prévia indisponível; selecione pelo número."),
                );
            });
          }),
        {
          root: $("pagesDialog").querySelector(".mp-dialog-body"),
          rootMargin: "200px",
        },
      );
      $("pagesList")
        .querySelectorAll("button")
        .forEach((card) => pageObserver.observe(card));
    } catch (error) {
      message(error.message);
      $("pagesError").textContent = error.message;
      $("pagesError").hidden = false;
    } finally {
      busy(false);
      controls();
    }
  }
  async function bindPage(documentId, page, uses) {
    if (state.saving) return;
    const others = uses.filter(
      (u) =>
        Number(u.pagina) === page &&
        (u.subtipo_id === null || Number(u.subtipo_id) !== state.subtypeId),
    );
    let reuse = false;
    if (others.length) {
      $("reuseMessage").textContent =
        "Esta página já está vinculada a " +
        others.map((u) => "“" + u.nome + "”").join(", ") +
        ". Deseja utilizá-la também neste subtipo?";
      const confirmed = await new Promise((resolve) => {
        const dialog = $("reuseDialog");
        dialog.returnValue = "";
        dialog.addEventListener(
          "close",
          () => resolve(dialog.returnValue === "confirm"),
          { once: true },
        );
        dialog.showModal();
      });
      if (!confirmed) return;
      reuse = true;
    }
    state.saving = true;
    controls();
    $("pagesList")
      .querySelectorAll("button")
      .forEach((b) => (b.disabled = true));
    try {
      await api(
        "pagina_vincular",
        {
          subtipo_id: state.subtypeId,
          documento_id: documentId,
          pagina: page,
          versao_config: state.previewLegacyId
            ? 0
            : state.reference?.versao_config || 0,
          confirmar_reuso: reuse,
        },
        "POST",
      );
      if (pageTask) {
        state.transfer = { id: documentId, task: pageTask };
        pageTask = null;
      }
      $("pagesDialog").close();
      await selectObra(state.subtypeId);
      toast("Página vinculada ao subtipo.");
    } catch (error) {
      message(error.message);
      $("pagesError").textContent = error.message;
      $("pagesError").hidden = false;
    } finally {
      state.saving = false;
      $("pagesList")
        .querySelectorAll("button")
        .forEach((b) => (b.disabled = false));
      controls();
    }
  }
  function renderEnvironments() {
    const list = $("environmentList");
    list.replaceChildren();
    if (!state.environments.length)
      list.append(
        node("p", "Os ambientes demarcados aparecerão aqui.", "mp-panel-empty"),
      );
    state.environments.forEach((environment) => {
      const item = button(
        "",
        () => selectEnvironment(environment.id, true),
        "mp-environment-item",
      );
      item.classList.toggle("is-selected", state.selectedId === environment.id);
      item.classList.toggle(
        "is-hovered",
        state.hoveredEnvironmentId === environment.id,
      );
      item.dataset.environmentId = environment.id;
      item.style.setProperty(
        "--environment-color",
        window.plantaEnvironmentColor(environment.id).color,
      );
      item.addEventListener("pointerenter", () =>
        setEnvironmentHover(environment.id),
      );
      item.addEventListener("pointerleave", () => setEnvironmentHover(null));
      item.addEventListener("focus", () => setEnvironmentHover(environment.id));
      item.addEventListener("blur", () => setEnvironmentHover(null));
      item.setAttribute(
        "aria-pressed",
        String(state.selectedId === environment.id),
      );
      const copy = node("span", undefined, "mp-environment-copy");
      copy.append(
        node("strong", environment.nome),
        node("span", imageCount(environment.imagem_ids.length)),
      );
      const dot = node("span", undefined, "mp-environment-dot");
      dot.setAttribute("aria-hidden", "true");
      const arrow = node("span", "›", "mp-environment-arrow");
      arrow.setAttribute("aria-hidden", "true");
      item.append(dot, copy, arrow);
      list.append(item);
    });
    $("environmentCount").textContent = "· " + state.environments.length;
    $("environmentCount").hidden = !state.reference;
  }
  function imageCount(count) {
    return `${count} ${count === 1 ? "imagem" : "imagens"}`;
  }
  const operationalPanel = new window.AmbienteOperacionalPanel(
    app,
    (ids) => api("imagens_contexto_operacional", { imagem_ids: ids.join(",") }),
    () => {
      state.selectedId = null;
      state.hoveredEnvironmentId = null;
      viewer.highlight(null);
      viewer.hoverEnvironment(null);
      renderEnvironments();
    },
  );
  function setEnvironmentHover(id) {
    if (state.drawing || inspector.active) return;
    state.hoveredEnvironmentId = id;
    viewer.hoverEnvironment(id);
    $("environmentList")
      .querySelectorAll("[data-environment-id]")
      .forEach((item) =>
        item.classList.toggle(
          "is-hovered",
          Number(item.dataset.environmentId) === id,
        ),
      );
  }
  function selectEnvironment(id, focusMap = false) {
    if (state.drawing || state.busy || state.preparing || state.layersOpen)
      return;
    const environment = state.environments.find((item) => item.id === id);
    if (!environment) return;
    state.selectedId = id;
    viewer.highlight(id);
    if (focusMap) viewer.focusEnvironment(id);
    renderEnvironments();
    const details = $("environmentDetails");
    details.replaceChildren();
    details.hidden = false;
    details.append(
      node("h3", environment.nome),
      node("p", imageCount(environment.imagem_ids.length)),
    );
    if (canEdit && state.reference?.subtipo_id && !state.previewLegacyId) {
      const actions = node("div", undefined, "mp-detail-actions");
      actions.append(
        button("Editar ambiente", () =>
          openEnvironment(environment, environment.geometria),
        ),
        button("Redesenhar área", () => startDrawing(environment)),
        button("Editar pontos", () => startDrawing(environment, true)),
        button(
          "Excluir",
          () => deleteEnvironment(environment),
          "mp-button mp-secondary mp-danger",
        ),
      );
      details.append(actions);
    }
    const images = state.images.filter((image) =>
      environment.imagem_ids.includes(image.id),
    );
    details.hidden = true;
    operationalPanel.open(
      environment,
      images,
      $("planTitle").textContent,
      details.querySelector(".mp-detail-actions"),
    );
  }
  function startDrawing(environment = null, editPoints = false) {
    inspector.cancel();
    if (
      !canEdit ||
      state.busy ||
      state.drawing ||
      state.saving ||
      state.preparing ||
      state.layersOpen ||
      !viewer.page
    )
      return;
    state.editing = environment;
    state.geometry = null;
    operationalPanel.close();
    state.drawing = true;
    $("drawingTitle").textContent = environment
      ? "Editando área do ambiente"
      : "Criando ambiente";
    $("drawingBar").hidden = false;
    editor.start(editPoints ? environment?.geometria : null);
    controls();
    $("navigationHint").textContent =
      "Clique nos vértices · Feche no primeiro ponto · Esc cancela";
    viewer.container.focus({ preventScroll: true });
    if (matchMedia("(max-width:850px)").matches)
      viewer.container.scrollIntoView({ block: "center", behavior: "auto" });
  }
  function cancelDrawing() {
    state.drawing = false;
    state.editing = null;
    state.geometry = null;
    editor.cancel();
    $("drawingBar").hidden = true;
    $("navigationHint").textContent = "Arraste para mover · Role para ampliar";
    controls();
  }
  function openEnvironment(environment, geometry) {
    if (state.preparing || state.busy || state.saving || state.layersOpen)
      return;
    operationalPanel.close();
    state.editing = environment;
    state.geometry = geometry;
    state.selectedImages = new Set(environment?.imagem_ids || []);
    $("environmentDialogTitle").textContent = environment
      ? "Editar ambiente"
      : "Novo ambiente";
    $("environmentName").value = environment?.nome || "";
    $("environmentError").hidden = true;
    renderImageOptions();
    $("environmentDialog").showModal();
    $("environmentName").focus();
  }
  function renderImageOptions() {
    const select = $("environmentImages"),
      jq = window.jQuery;
    jq(select).off(".mapaImages");
    if (jq(select).data("select2")) jq(select).select2("destroy");
    select.replaceChildren();
    const available = node("optgroup"),
      linked = node("optgroup");
    available.label = "Sem vínculo em outros ambientes";
    linked.label = "Já vinculadas a outros ambientes";
    state.images.forEach((image) => {
      const others = (image.ambientes || []).filter(
        (environment) => environment.id !== state.editing?.id,
      );
      const option = new Option(
        image.imagem_nome,
        image.id,
        false,
        state.selectedImages.has(image.id),
      );
      option.dataset.environments = others
        .map(
          (environment) =>
            environment.nome +
            (environment.pavimento ? " · " + environment.pavimento : ""),
        )
        .join("; ");
      (others.length ? linked : available).append(option);
    });
    [available, linked].forEach((group) => {
      if (group.children.length) select.append(group);
    });
    select.disabled = !state.images.length;
    $("imageOptionsEmpty").hidden = !!state.images.length;
    jq(select)
      .select2({
        width: "100%",
        dropdownParent: jq($("environmentImagesPicker")),
        placeholder: "Buscar e selecionar imagens da obra…",
        closeOnSelect: false,
        templateResult(item) {
          if (!item.id) return item.text;
          const result = node("span", undefined, "mp-image-result");
          result.append(node("span", item.text));
          if (item.element?.dataset.environments)
            result.append(
              node(
                "small",
                "Vinculada a: " + item.element.dataset.environments,
              ),
            );
          return jq(result);
        },
        language: {
          noResults: () => "Nenhuma imagem encontrada.",
          searching: () => "Buscando imagens…",
          removeItem: () => "Remover imagem",
          removeAllItems: () => "Limpar seleção",
          search: () => "Buscar imagens da obra",
        },
      })
      .on("change.mapaImages", () => {
        state.selectedImages = new Set((jq(select).val() || []).map(Number));
        selectedCount();
      });
    jq(select).next(".select2-container").find(".select2-selection").attr({
      "aria-labelledby": "environmentImagesLabel",
      "aria-describedby": "environmentImagesHelp",
      "aria-controls": "select2-environmentImages-results",
    });
    jq(select).next(".select2-container").find(".select2-search__field").attr({
      "aria-label": "Buscar imagens da obra",
      role: "textbox",
      "aria-multiline": "false",
    });
    jq(select)
      .data("select2")
      .on("results:all", () => {
        jq($("environmentImagesPicker"))
          .find(".select2-results__option--group")
          .each(function () {
            jq(this)
              .children("ul")
              .attr({
                role: "group",
                "aria-label": this.getAttribute("aria-label"),
              });
            jq(this).attr("role", "presentation").removeAttr("aria-label");
          });
      });
    selectedCount();
  }
  function selectedCount() {
    const count = state.selectedImages.size;
    $("selectedImageCount").textContent =
      `${count} ${count === 1 ? "selecionada" : "selecionadas"}`;
  }
  async function refreshMap(selectedId) {
    const result = await api("mapa", { subtipo_id: state.subtypeId });
    const configurationChanged =
      result.referencia?.versao_config !== state.reference?.versao_config;
    state.reference = result.referencia;
    state.environments = result.ambientes;
    const imageData = await api("imagens", {
      subtipo_id: state.subtypeId,
      referencia_id: state.reference.id,
    });
    state.images = imageData.imagens;
    if (configurationChanged && viewer.page) {
      viewer.setLayers(state.reference.layers);
      viewer.setCrop(state.reference.area_util);
      await viewer.renderPdf();
    }
    viewer.setEnvironments(state.environments);
    renderEnvironments();
    renderPreparation();
    if (selectedId) selectEnvironment(selectedId);
    else {
      operationalPanel.close();
      state.selectedId = null;
      viewer.highlight(null);
      $("environmentDetails").hidden = true;
      renderEnvironments();
    }
  }
  async function deleteEnvironment(environment) {
    if (
      state.busy ||
      state.drawing ||
      state.preparing ||
      state.saving ||
      state.layersOpen
    )
      return;
    const confirmation = await window.FlowAlert.confirm({
      title: "Excluir ambiente?",
      message: `Excluir “${environment.nome}” e sua demarcação? As imagens da obra serão preservadas.`,
      confirmText: "Excluir ambiente",
    });
    if (!confirmation.isConfirmed) return;
    busy(true, "Excluindo ambiente");
    message();
    try {
      await api(
        "ambiente_excluir",
        {
          id: environment.id,
          subtipo_id: state.subtypeId,
          referencia_id: Number(state.reference.id),
          versao_edicao: environment.versao_edicao,
        },
        "POST",
      );
      await refreshMap();
      toast("Ambiente excluído.");
    } catch (error) {
      message(error.message);
    } finally {
      busy(false);
    }
  }

  function renderPreparation() {
    const layers = viewer.getLayers().groups,
      hidden = layers.filter((l) => !l.visible).length;
    $("layersStatus").textContent = layers.length
      ? layers.length - hidden + "/" + layers.length + " camadas visíveis"
      : "Este PDF não possui layers editáveis.";
    $("cropStatus").textContent = state.reference?.area_util
      ? "Área útil poligonal definida"
      : "Área útil não definida · Página inteira";
    if (canEdit) {
      $("configureLayers").textContent = layers.length
        ? "Camadas " + (layers.length - hidden) + "/" + layers.length
        : "Camadas";
      $("configureLayers").title = $("layersStatus").textContent;
      $("configureLayers").setAttribute(
        "aria-label",
        "Configurar camadas: " + $("layersStatus").textContent,
      );
      $("defineCrop").textContent = state.reference?.area_util
        ? "Área útil ✓"
        : "Área útil";
      $("defineCrop").title = $("cropStatus").textContent;
      $("defineCrop").setAttribute(
        "aria-label",
        state.reference?.area_util ? "Editar área útil" : "Definir área útil",
      );
    } else {
      $("referenceReadout").textContent = $("layersStatus").textContent;
      $("referenceReadout").title = $("cropStatus").textContent;
    }
  }
  function startCrop() {
    if (
      state.busy ||
      state.drawing ||
      state.preparing ||
      !viewer.page ||
      !state.reference?.subtipo_id
    )
      return;
    inspector.cancel();
    state.preparing = true;
    message();
    $("cropError").hidden = true;
    $("cropBar").hidden = false;
    $("navigationHint").textContent =
      "Clique para desenhar · Arraste vértices · Espaço + arraste move a planta";
    const c = viewer.crop || { x: 0, y: 0, width: 1, height: 1 };
    viewer.setCrop(null);
    viewer.setEnvironments(
      state.environments.map((e) => ({
        ...e,
        geometria: {
          ...e.geometria,
          points: e.geometria.points.map(([x, y]) => [
            c.x + x * c.width,
            c.y + y * c.height,
          ]),
        },
      })),
    );
    cropEditor.start(state.reference.area_util, true);
    controls();
    viewer.container.focus({ preventScroll: true });
    if (matchMedia("(max-width:850px)").matches)
      $("cropBar").scrollIntoView({ block: "start" });
  }
  function stopCrop(restore = true) {
    if (!state.preparing) return;
    cropEditor.cancel();
    state.preparing = false;
    $("cropBar").hidden = true;
    $("navigationHint").textContent = "Arraste para mover · Role para ampliar";
    if (restore && viewer.page) {
      viewer.setCrop(state.reference.area_util);
      viewer.setEnvironments(state.environments);
    }
    controls();
  }
  function referencePayload(layers, crop) {
    return {
      subtipo_id: state.subtypeId,
      referencia_id: Number(state.reference.id),
      versao_config: state.reference.versao_config,
      layers,
      area_util: crop,
    };
  }
  cropEditor.addEventListener("change", () => {
    $("cropPointCount").textContent =
      cropEditor.points.length +
      " vértices" +
      (cropEditor.closed
        ? " · Contorno fechado"
        : " · Feche no primeiro ponto");
    controls();
  });
  $("cropUndo").addEventListener("click", () => cropEditor.undo());
  $("cropAddVertex").addEventListener("click", () => cropEditor.addVertex());
  $("cropRemoveVertex").addEventListener("click", () =>
    cropEditor.removeVertex(),
  );
  $("cropRedraw").addEventListener("click", () => cropEditor.clear());
  $("fullPageCrop").addEventListener("click", () => cropEditor.clear());
  $("cancelCrop").addEventListener("click", () => stopCrop());
  $("saveCrop").addEventListener("click", async () => {
    if (!state.preparing || state.busy) return;
    $("cropError").hidden = true;
    busy(true, "Salvando área útil");
    try {
      await api(
        "referencia_configurar",
        referencePayload(
          viewer.getLayers(),
          cropEditor.points.length ? cropEditor.geometry() : null,
        ),
        "POST",
      );
      stopCrop(false);
      await refreshMap();
      toast("Área útil salva.");
    } catch (error) {
      $("cropError").textContent = error.message;
      $("cropError").hidden = false;
      if (!state.preparing) message(error.message);
    } finally {
      busy(false);
    }
  });
  function restoreLayerPreview() {
    if (!state.layerBase || !viewer.page) return;
    viewer.setLayers(state.layerBase);
    viewer.renderPdf().catch((error) => message(error.message));
    renderPreparation();
  }
  function selectedLayers() {
    const layers = structuredClone(state.layerBase);
    const visibility = new Map(
      [...$("layerOptions").querySelectorAll("input")].map((input) => [
        input.value,
        input.checked,
      ]),
    );
    layers.groups.forEach(
      (layer) => (layer.visible = visibility.get(layer.id)),
    );
    return layers;
  }
  function previewSelectedLayers() {
    if (!state.layerBase || !viewer.page || state.saving) return;
    viewer.setLayers(selectedLayers());
    viewer.renderPdf().catch((error) => message(error.message));
    renderPreparation();
  }
  function renderLayerOptions() {
    const list = $("layerOptions");
    list.replaceChildren();
    const names = new Intl.Collator("pt-BR", {
      numeric: true,
      sensitivity: "base",
    });
    [...state.layerBase.groups]
      .sort((a, b) => names.compare(a.name, b.name))
      .forEach((layer) => {
        const label = node("label", undefined, "mp-image-option"),
          input = node("input");
        input.type = "checkbox";
        input.value = layer.id;
        input.checked = layer.visible;
        label.append(input, node("span", layer.name));
        input.addEventListener("change", previewSelectedLayers);
        list.append(label);
      });
  }
  function markLayers(visible) {
    $("layerOptions")
      .querySelectorAll("input")
      .forEach((input) => (input.checked = visible));
    previewSelectedLayers();
  }
  $("allLayers").addEventListener("click", () => markLayers(true));
  $("noLayers").addEventListener("click", () => markLayers(false));
  $("defaultLayers").addEventListener("click", () => markLayers(true));
  $("layersDialog").addEventListener("close", () => {
    restoreLayerPreview();
    state.layersOpen = false;
    state.layerBase = null;
    controls();
  });
  $("layersDialog").addEventListener("cancel", (e) => {
    if (state.saving) e.preventDefault();
  });
  $("layersForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    if (!canEdit || state.saving) return;
    state.saving = true;
    controls();
    $("layersError").hidden = true;
    const layers = selectedLayers();
    $("layersDialog")
      .querySelectorAll("button,input")
      .forEach((i) => (i.disabled = true));
    $("saveLayers").textContent = "Salvando…";
    try {
      await api(
        "referencia_configurar",
        referencePayload(layers, state.reference.area_util),
        "POST",
      );
      state.layerBase = layers;
      await refreshMap();
      $("layersDialog").close();
      toast("Camadas salvas.");
    } catch (error) {
      $("layersError").textContent = error.message;
      $("layersError").hidden = false;
    } finally {
      state.saving = false;
      $("saveLayers").textContent = "Salvar camadas";
      $("layersDialog")
        .querySelectorAll("button,input")
        .forEach((i) => (i.disabled = false));
      controls();
    }
  });
  $("inspectLayers").addEventListener("click", async () => {
    if (inspector.active) {
      inspector.cancel();
      controls();
      return;
    }
    busy(true, "Preparando inspeção de camadas");
    try {
      await inspector.start();
      toast("Passe o mouse sobre a planta para identificar as camadas.");
    } catch (error) {
      message(error.message);
    } finally {
      busy(false);
    }
  });
  inspector.addEventListener("progress", (e) => {
    $("busyText").textContent =
      "Preparando inspeção: " +
      e.detail.done +
      " de " +
      e.detail.total +
      " camadas";
  });
  inspector.addEventListener("inspect", (e) => {
    const tooltip = $("tooltip");
    tooltip.hidden = !e.detail;
    if (!e.detail) return;
    tooltip.replaceChildren(
      node(
        "strong",
        e.detail.names.length
          ? "Elementos nesta posição"
          : "Nenhuma camada identificável nesta posição",
      ),
    );
    e.detail.names.forEach((name) => tooltip.append(node("span", name)));
    tooltip.style.left =
      Math.max(
        8,
        Math.min(innerWidth - tooltip.offsetWidth - 10, e.detail.x + 14),
      ) + "px";
    tooltip.style.top =
      Math.max(
        8,
        Math.min(innerHeight - tooltip.offsetHeight - 10, e.detail.y + 14),
      ) + "px";
  });
  if (canEdit) {
    $("defineCrop").addEventListener("click", startCrop);
    $("configureLayers").addEventListener("click", () => {
      inspector.cancel();
      state.layersOpen = true;
      state.layerBase = viewer.getLayers();
      renderLayerOptions();
      $("layersError").hidden = true;
      $("layersDialog").show();
      controls();
    });
  }

  $("obraSelect").addEventListener("change", () => selectObra());
  $("openModel3d").addEventListener("click", () => {
    const url = new URL(
      "predio-3d/visualizador-glb-navegacao.php",
      location.href,
    );
    url.searchParams.set("obra_id", state.obraId);
    url.searchParams.set("subtipo_id", state.subtypeId);
    if (embedded) notifyParent("mp:return-3d");
    else location.assign(url.href);
  });
  $("documentSelect").addEventListener("change", () => {
    const id = Number($("documentSelect").value);
    if (id) openPages(id);
  });
  $("choosePage").addEventListener("click", () =>
    openPages(Number(state.reference.documento_id)),
  );
  $("pagesDialog").addEventListener("close", closePageResources);
  $("pagesDialog").addEventListener("cancel", (e) => {
    if (state.saving) e.preventDefault();
  });
  $("zoomIn").addEventListener("click", () => viewer.zoom(1.25));
  $("zoomOut").addEventListener("click", () => viewer.zoom(0.8));
  $("fitView").addEventListener("click", () => viewer.fit());
  $("resetView").addEventListener("click", () => viewer.reset());
  $("rotatePage").addEventListener("click", () => viewer.rotate(90));
  viewer.addEventListener("viewchange", (e) => {
    $("zoomValue").textContent = Math.round(e.detail.scale * 100) + "%";
    $("tooltip").hidden = true;
  });
  viewer.addEventListener("viewererror", (e) => message(e.detail.message));
  viewer.addEventListener("environmentselect", (e) => {
    if (!inspector.active) selectEnvironment(e.detail);
  });
  viewer.addEventListener("environmenthover", (e) => {
    if (inspector.active) return;
    setEnvironmentHover(e.detail?.environment.id ?? null);
    const tooltip = $("tooltip");
    tooltip.hidden = !e.detail;
    if (!e.detail) return;
    tooltip.replaceChildren(
      node("strong", e.detail.environment.nome),
      node("span", imageCount(e.detail.environment.imagem_ids.length)),
    );
    tooltip.style.left =
      Math.max(
        8,
        Math.min(innerWidth - tooltip.offsetWidth - 10, e.detail.x + 14),
      ) + "px";
    tooltip.style.top =
      Math.max(
        8,
        Math.min(innerHeight - tooltip.offsetHeight - 10, e.detail.y + 14),
      ) + "px";
  });
  editor.addEventListener("change", (e) => {
    $("drawingHint").textContent = editor.closed
      ? e.detail + " vértices. Arraste pontos; clique numa borda para inserir."
      : e.detail +
        " vértices. Clique na planta para marcar; feche no primeiro ponto ou conclua o contorno.";
    $("finishPolygon").disabled = e.detail < 3;
    $("undoPoint").disabled = e.detail === 0;
    controls();
  });
  editor.addEventListener("complete", (e) => {
    $("drawingBar").hidden = true;
    openEnvironment(state.editing, e.detail);
  });
  $("finishPolygon").addEventListener("click", () => editor.finish());
  $("undoPoint").addEventListener("click", () => editor.undo());
  $("addVertex").addEventListener("click", () => editor.addVertex());
  $("removeVertex").addEventListener("click", () => editor.removeVertex());
  $("cancelDrawing").addEventListener("click", cancelDrawing);
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      if (state.preparing && !state.busy) stopCrop();
      else if (state.layersOpen && !state.saving) $("layersDialog").close();
      else if (state.drawing && !$("environmentDialog").open) cancelDrawing();
      else if (inspector.active) {
        inspector.cancel();
        controls();
      }
    }
    if (
      (e.key === "Delete" || e.key === "Backspace") &&
      e.target === viewer.container &&
      (state.drawing || state.preparing)
    ) {
      e.preventDefault();
      const active = state.preparing ? cropEditor : editor;
      if (active.selected !== null) active.removeVertex();
      else active.undo();
    }
  });
  document.querySelectorAll("[data-close]").forEach((e) =>
    e.addEventListener("click", () => {
      if (!state.saving) $(e.dataset.close).close();
    }),
  );
  $("environmentDialog").addEventListener("cancel", (e) => {
    if (state.saving) e.preventDefault();
  });
  document.addEventListener(
    "keydown",
    (e) => {
      const picker = window.jQuery($("environmentImages"));
      if (
        e.key === "Escape" &&
        $("environmentDialog").open &&
        picker.data("select2")?.isOpen()
      ) {
        e.preventDefault();
      }
    },
    true,
  );
  // Keep the inline results in place until an outside click reaches its target.
  // Closing on mousedown would move the centered dialog before mouseup.
  $("environmentDialog").addEventListener("mousedown", (e) => {
    if (
      !$("environmentImagesPicker").contains(e.target) &&
      window.jQuery($("environmentImages")).data("select2")?.isOpen()
    )
      e.stopPropagation();
  });
  $("environmentDialog").addEventListener("click", (e) => {
    if (!$("environmentImagesPicker").contains(e.target))
      window.jQuery($("environmentImages")).select2("close");
  });
  $("environmentDialog").addEventListener("close", () => {
    window.jQuery($("environmentImages")).select2("close");
    if (!state.saving) cancelDrawing();
  });
  if (canEdit) {
    $("addEnvironment").addEventListener("click", () => {
      inspector.cancel();
      startDrawing();
    });
    $("pdfInput").addEventListener("change", async () => {
      const file = $("pdfInput").files[0];
      if (!file) return;
      message();
      busy(true, "Validando e enviando documento");
      let task;
      try {
        if (file.size > 30 * 1024 * 1024)
          throw new Error("Envie um PDF de até 30 MB.");
        task = pdfjsLib.getDocument({
          data: new Uint8Array(await file.arrayBuffer()),
        });
        await task.promise;
        const form = new FormData();
        form.set("obra_id", state.obraId);
        form.set("pdf", file);
        const r = await api("documento_upload", form, "POST"),
          selected = state.subtypeId;
        await selectObra(selected);
        toast(
          r.duplicado
            ? "Este PDF já foi enviado. O documento existente será reutilizado."
            : "Documento enviado.",
        );
        if (selected) await openPages(Number(r.id));
      } catch (error) {
        message(
          error.name === "PasswordException"
            ? "Envie um PDF sem senha."
            : error.message,
        );
      } finally {
        await task?.destroy().catch(() => {});
        busy(false);
        $("pdfInput").value = "";
      }
    });
    $("environmentForm").addEventListener("submit", async (e) => {
      e.preventDefault();
      if (state.saving) return;
      state.saving = true;
      $("saveEnvironment").disabled = true;
      $("environmentError").hidden = true;
      controls();
      try {
        const r = await api(
          "ambiente_salvar",
          {
            id: state.editing?.id || 0,
            versao_edicao: state.editing?.versao_edicao,
            subtipo_id: state.subtypeId,
            referencia_id: Number(state.reference.id),
            versao_referencia: state.reference.versao_config,
            nome: $("environmentName").value.trim(),
            geometria: state.geometry,
            imagem_ids: [...state.selectedImages],
          },
          "POST",
        );
        $("environmentDialog").close();
        cancelDrawing();
        await refreshMap(r.id);
        toast("Ambiente salvo.");
      } catch (error) {
        $("environmentError").textContent = error.message;
        $("environmentError").hidden = false;
        if (!$("environmentDialog").open) message(error.message);
      } finally {
        state.saving = false;
        $("saveEnvironment").disabled = false;
        controls();
      }
    });
  }
  $("themeToggle").addEventListener("click", () => {
    const dark = document.documentElement.dataset.theme
      ? document.documentElement.dataset.theme === "dark"
      : matchMedia("(prefers-color-scheme:dark)").matches;
    document.documentElement.dataset.theme = dark ? "light" : "dark";
    try {
      localStorage.setItem(
        "flow-mapa-theme",
        document.documentElement.dataset.theme,
      );
    } catch (_) {
      /* private browser */
    }
  });
  try {
    const theme = localStorage.getItem("flow-mapa-theme");
    document.documentElement.dataset.theme =
      theme === "light" ? "light" : "dark";
  } catch (_) {
    document.documentElement.dataset.theme = "dark";
  }

  window.thinkingOrbs($("busyOverlay"));
  if (embedded) {
    document.documentElement.dataset.theme = "dark";
    window.addEventListener("message", (event) => {
      if (
        event.origin !== mapOrigin ||
        event.source !== window.parent ||
        event.data?.bridge !== bridgeId ||
        event.data?.type !== "mp:request-leave"
      )
        return;
      const blocked =
        state.busy ||
        state.drawing ||
        state.preparing ||
        state.saving ||
        state.layersOpen ||
        $("environmentDialog").open ||
        $("pagesDialog").open ||
        $("reuseDialog").open;
      notifyParent(blocked ? "mp:leave-blocked" : "mp:leave-ok", {
        message: blocked
          ? "Conclua ou cancele a edição no mapa antes de voltar ao empreendimento."
          : "",
      });
    });
  }
  const initialObra = Number(app.dataset.initialObra);
  if (
    initialObra &&
    [...$("obraSelect").options].some((o) => Number(o.value) === initialObra)
  ) {
    $("obraSelect").value = initialObra;
    selectObra(
      Number(app.dataset.initialSubtipo),
      Number(app.dataset.initialLegado),
      Number(app.dataset.initialPavimento),
    );
  } else initializeSubtypes({ obra: [], demais: [] });
  import("./map-spatial-controls.js?v=20261016-reference-solar")
    .then(({ MapSpatialControls }) => {
      const spatial = new MapSpatialControls(viewer, () => state.obraId);
      $("obraSelect").addEventListener("change", () => spatial.load());
      spatial.load();
    })
    .catch((error) => console.error("Controles espaciais", error));
  controls();
})();
