"use strict";

const CATALOG_API = "catalogo_ajax.php";
const REFERENCE_API = "referencia_ajax.php";
const EVENT_REFS_API = "evento_referencias_ajax.php";
const SIRE_ADMIN_API = "sire_admin_ajax.php";
const PAGE_LIMIT = 48;

let pillars = [];
let currentPage = 1;
let totalRefs = 0;
let currentReference = null;
let searchTimer = null;
let classificationDirty = false;
let lastLightboxTrigger = null;
const goldenPending = new Set();
let adminPillars = [];
let adminSelectedPillarId = null;
let adminSearchTimer = null;
let lastAdminTrigger = null;

const esc = (value) =>
  String(value || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");

function pillarIdentityClass(code) {
  const normalized = String(code || "").toLowerCase();
  return /^[a-z]+$/.test(normalized)
    ? `sire-pillar-identity--${normalized}`
    : "sire-pillar-identity--default";
}

function formatDate(value) {
  if (!value) return "—";
  const date = new Date(String(value).replace(" ", "T"));
  return Number.isNaN(date.getTime())
    ? value
    : date.toLocaleDateString("pt-BR", {
        day: "2-digit",
        month: "short",
        year: "numeric",
      });
}

function originLabel(origin) {
  return (
    { Flow: "Flow", Upload: "Upload", URL: "URL" }[origin] || origin || "Flow"
  );
}

function notify(message, isError = false) {
  Toastify({
    text: message,
    duration: 3600,
    gravity: "top",
    position: "right",
    className: `sire-toast${isError ? " sire-toast--error" : ""}`,
  }).showToast();
}

function apiJson(url, options) {
  return fetch(url, options).then((response) =>
    response.json().then((json) => {
      if (!response.ok || json.success === false)
        throw new Error(
          json.message || "Não foi possível concluir a operação.",
        );
      return json;
    }),
  );
}

function copyToClipboard(text) {
  if (navigator.clipboard?.writeText)
    return navigator.clipboard.writeText(text);

  return new Promise((resolve, reject) => {
    const input = document.createElement("textarea");
    input.value = text;
    input.setAttribute("readonly", "");
    input.style.position = "fixed";
    input.style.opacity = "0";
    document.body.append(input);
    input.select();

    const copied = document.execCommand("copy");
    input.remove();
    copied ? resolve() : reject(new Error("Cópia não suportada."));
  });
}

function select2ValueTemplate(value) {
  if (!value.id) return value.text;
  const description = value.element
    ? $(value.element).attr("data-description")
    : value.descricao;
  const option = document.createElement("span");
  option.className = "sire-select2-option";
  const title = document.createElement("strong");
  title.textContent = value.text;
  option.append(title);
  if (description) {
    const detail = document.createElement("small");
    detail.textContent = description;
    option.append(detail);
  }
  return option;
}

function appendPillarOptions(select, pillar, selected = []) {
  (pillar.valores || []).forEach((value) => {
    const isSelected = selected.includes(String(value.id));
    const option = new Option(value.text, value.id, isSelected, isSelected);
    $(option).attr("data-description", value.descricao || "");
    select.append(option);
  });
}

function buildPillarFilters(selectedByCode = {}) {
  const container = $("#pilarFilters").empty();
  pillars.forEach((pillar) => {
    const id = `filterPilar_${pillar.codigo}`;
    const selected = selectedByCode[pillar.codigo] || [];
    const values = pillar.valores || [];
    container.append(`
      <details class="sire-filter-accordion ${pillarIdentityClass(pillar.codigo)}" data-code="${esc(pillar.codigo)}">
        <summary><span class="sire-pillar-title"><i class="sire-pillar-dot" aria-hidden="true"></i>${esc(pillar.nome)}</span><span class="sire-filter-accordion-count">${values.length}</span></summary>
        <div class="sire-filter-accordion-body">
          <label class="sr-only" for="${id}_search">Buscar em ${esc(pillar.nome)}</label>
          <div class="sire-filter-value-search"><i class="fa-solid fa-magnifying-glass"></i><input id="${id}_search" type="search" data-pillar-search="${esc(pillar.codigo)}" placeholder="Buscar ${esc(pillar.nome.toLowerCase())}"></div>
          <select id="${id}" class="pilar-filter sr-only" data-code="${esc(pillar.codigo)}" multiple aria-hidden="true" tabindex="-1"></select>
          <div class="sire-filter-value-list">${values
            .map((value, index) => {
              const checked = selected.includes(String(value.id))
                ? " checked"
                : "";
              return `<label class="sire-filter-value ${pillarIdentityClass(pillar.codigo)}${index > 5 ? " is-extra" : ""}"><input type="checkbox" value="${value.id}" data-pillar-value="${esc(pillar.codigo)}"${checked}><span>${esc(value.text)}</span><b>${Number(value.referencias) || 0}</b></label>`;
            })
            .join("")}</div>
          ${values.length > 6 ? '<button type="button" class="sire-filter-more">Ver mais</button>' : ""}
        </div>
      </details>`);
    const select = $(`#${id}`);
    appendPillarOptions(select, pillar, selected);
  });
}

function refreshPillarOptions() {
  $(".pilar-filter").each(function () {
    const code = $(this).data("code");
    const selected = $(this).val() || [];
    const pillar = pillars.find((item) => item.codigo === code);
    $(this).empty();
    if (pillar) appendPillarOptions($(this), pillar, selected);
    $(this).trigger("change");
  });
}

function currentPillarSelections() {
  const selected = {};
  $(".pilar-filter").each(function () {
    selected[$(this).data("code")] = $(this).val() || [];
  });
  return selected;
}

function loadPillars(preserveSelections = false) {
  const selected = preserveSelections ? currentPillarSelections() : {};
  return apiJson(`${REFERENCE_API}?action=getPilares`).then((response) => {
    pillars = response.pilares || [];
    buildPillarFilters(selected);
  });
}

function catalogParams() {
  const params = new URLSearchParams({
    action: "getRefs",
    page: String(currentPage),
    per_page: String(PAGE_LIMIT),
  });
  const search = $("#searchInput").val().trim();
  const obra = $("#filterObra").val();
  const ambiente = $("#filterAmbiente").val();
  if (search) params.set("search", search);
  if (obra) params.set("obra_id", obra);
  if (ambiente) params.set("ambiente", ambiente);
  if ($("#filterGolden").is(":checked")) params.set("golden", "1");
  $(".pilar-filter").each(function () {
    const code = $(this).data("code");
    ($(this).val() || []).forEach((valueId) =>
      params.append(`pilar_${code}[]`, valueId),
    );
  });
  return params;
}

function renderCards(refs, append = false) {
  const grid = $("#refGrid");
  if (!append) grid.empty();
  if (!refs.length && !append) {
    grid.html(
      '<div class="empty-state"><i class="fa-solid fa-images"></i><p>Nenhuma referência encontrada</p><span>Tente ajustar os filtros.</span></div>',
    );
    return;
  }

  const html = refs
    .map((reference) => {
      const title =
        reference.imagem_nome_curto ||
        reference.nomenclatura ||
        reference.nome_arquivo ||
        "Referência";
      const work = reference.obra_nomenclatura || "";
      const environment = reference.ambiente || "Referência visual";
      const showWork = work && work.toLowerCase() !== title.toLowerCase();
      const golden = Number(reference.golden_sample) === 1;
      const thumbnail = reference.thumbnail_url || "../assets/logo.jpg";
      const classifications = Array.isArray(reference.classificacoes)
        ? reference.classificacoes
        : [];
      const visibleClassifications = classifications.slice(0, 3);
      const remainingClassifications = Math.max(
        0,
        classifications.length - visibleClassifications.length,
      );
      return `
      <article class="ref-card${golden ? " is-golden" : ""}" data-id="${reference.id}" role="button" tabindex="0" aria-label="Abrir referência ${esc(title)}">
        <div class="card-thumb-wrap">
          <img loading="lazy" decoding="async" src="${esc(thumbnail)}" alt="${esc(title)}" class="loading"
               onload="this.classList.remove('loading')"
               onerror="this.src='../assets/logo.jpg';this.classList.remove('loading')">
          <button type="button" class="card-heart${golden ? " is-golden" : ""}" data-id="${reference.id}" aria-label="${golden ? "Remover dos Golden Samples" : "Marcar como Golden Sample"}" title="${golden ? "Remover dos Golden Samples" : "Marcar como Golden Sample"}"><i class="${golden ? "fa-solid" : "fa-regular"} fa-star"></i></button>
          <span class="card-origin origem-${String(reference.origem).toLowerCase().replace(/\s+/g, "-")}">
            ${esc(originLabel(reference.origem))}
          </span>
          ${Number(reference.anexos_count) > 0 ? `<span class="card-attachments"><i class="fa-solid fa-paperclip"></i>${Number(reference.anexos_count)}</span>` : ""}
          <div class="card-hover-actions" aria-label="Ações da referência">
            <button type="button" class="card-interactive card-copy-link" data-url="${esc(reference.imagem_url)}"><i class="fa-solid fa-link"></i><span>Copiar link</span></button>
            <button type="button" class="card-interactive card-similar" data-id="${reference.id}"><i class="fa-solid fa-wand-magic-sparkles"></i><span>Similares</span></button>
          </div>
        </div>
        <div class="card-body">
          ${showWork ? `<p class="card-eyebrow" title="${esc(work)}">${esc(work)}</p>` : ""}
          <p class="card-title" title="${esc(title)}">${esc(title)}</p>
          <div class="card-footer">
            <div class="card-classifications"><span class="sire-card-badge sire-card-badge--environment">${esc(environment)}</span>${visibleClassifications.map((item) => `<span class="sire-card-badge sire-pillar-badge ${pillarIdentityClass(item.codigo)}">${esc(item.nome)}</span>`).join("")}${remainingClassifications ? `<span class="card-classifications-more">+${remainingClassifications}</span>` : ""}</div>
          </div>
        </div>
      </article>`;
    })
    .join("");

  if (append) {
    grid.append(html);
  } else {
    grid.html(html);
  }
}

function updateResultState(response) {
  totalRefs = Number(response.total || 0);
  $("#resultsCount").text(totalRefs);
  const active =
    catalogParams().toString().includes("search=") ||
    $("#filterObra").val() ||
    $("#filterAmbiente").val() ||
    $("#filterGolden").is(":checked") ||
    $(".pilar-filter")
      .toArray()
      .some((element) => ($(element).val() || []).length);
  $("#resultsBadge").toggleClass("has-filter", Boolean(active));
  $("#filterDot").toggleClass("visible", Boolean(active));
  $("#btnLimpar").toggle(Boolean(active));
  const activeCount =
    [$("#filterObra").val(), $("#filterAmbiente").val()].filter(Boolean)
      .length +
    Number($("#filterGolden").is(":checked")) +
    $(".pilar-filter")
      .toArray()
      .reduce((count, element) => count + ($(element).val() || []).length, 0);
  $("#mobileFilterCount")
    .text(activeCount)
    .prop("hidden", activeCount === 0);
  const stats = response.stats || {};
  $("#contextRefsCount").text(Number(stats.referencias ?? totalRefs));

  const internas = Number(stats.internas || 0);
  const externas = Number(stats.externas || 0);

  $("#contextInteriorsCount").text(internas);
  $("#contextInteriorsLabel").text(internas === 1 ? "interna" : "internas");

  $("#contextExteriorsCount").text(externas);
  $("#contextExteriorsLabel").text(externas === 1 ? "externa" : "externas");

  $("#contextNewCount").text(Number(stats.novas_semana || 0));
  renderActiveFilters();
  const canLoadMore = Number(response.page) < Number(response.total_pages);
  $("#loadMoreWrap").toggle(canLoadMore);
  $("#loadMoreCounter").text(
    canLoadMore
      ? `(${Math.min(currentPage * PAGE_LIMIT, totalRefs)} / ${totalRefs})`
      : "",
  );
}

function renderActiveFilters() {
  const chips = [];
  if ($("#filterGolden").is(":checked"))
    chips.push({ key: "golden", label: "Golden Sample" });
  const obra = $("#filterObra option:selected");
  if (obra.val()) chips.push({ key: "obra", label: obra.text().trim() });
  const ambiente = $("#filterAmbiente option:selected");
  if (ambiente.val())
    chips.push({ key: "ambiente", label: ambiente.text().trim() });
  $(".pilar-filter").each(function () {
    const select = $(this);
    (select.val() || []).forEach((value) => {
      const option = select.find(`option[value="${value}"]`);
      chips.push({
        key: `pillar:${select.data("code")}:${value}`,
        label: option.text(),
        pillarCode: select.data("code"),
      });
    });
  });
  $("#activeFilterChips").html(
    chips
      .map(
        (chip) =>
          `<button type="button" class="sire-filter-chip${chip.pillarCode ? ` sire-pillar-badge ${pillarIdentityClass(chip.pillarCode)}` : ""}" data-filter-key="${esc(chip.key)}">${esc(chip.label)} <i class="fa-solid fa-xmark"></i></button>`,
      )
      .join(""),
  );
  $("#btnContextClear").prop("hidden", chips.length === 0);
}

function clearAllFilters() {
  $("#searchInput, #filterObra, #filterAmbiente").val("");
  $("#filterGolden").prop("checked", false);
  $(".pilar-filter").val(null);
  $(".sire-filter-value input").prop("checked", false);
  currentPage = 1;
  loadReferences();
}

function loadReferences(append = false) {
  const params = catalogParams();
  return apiJson(`${CATALOG_API}?${params.toString()}`)
    .then((response) => {
      if (append) {
        renderCards(response.refs || [], true);
      } else {
        renderCards(response.refs || []);
      }
      updateResultState(response);
    })
    .catch((error) => notify(error.message, true));
}

function selectPillarValues(reference) {
  $(".classification-select").each(function () {
    const code = $(this).data("code");
    const selected = (
      reference.classificacoes && reference.classificacoes[code]
        ? reference.classificacoes[code]
        : []
    ).map((value) => String(value.id));
    const select = $(this);
    const currentValues = reference.classificacoes?.[code] || [];
    currentValues.forEach((value) => {
      if (!select.find(`option[value="${value.id}"]`).length) {
        const option = new Option(value.text, value.id, true, true);
        $(option)
          .attr("data-description", value.descricao || "")
          .attr("data-inactive", "1");
        select.append(option);
      }
    });
    select.val(selected).trigger("change");
  });
}

function initializeClassificationSelect(select, pillar) {
  if (select.hasClass("select2-hidden-accessible")) return;
  select.select2({
    tags: false,
    placeholder: `Selecionar ${pillar.nome}`,
    width: "100%",
    closeOnSelect: false,
    dropdownParent: $("#refLightbox"),
    templateResult: select2ValueTemplate,
  });
  select.on("change.sire-classification", () => {
    classificationDirty = true;
  });
}

function setClassificationPanelOpen(isOpen) {
  const panel = $("#lbClassificationTab");
  const button = $("#btnToggleClassification");
  if (!panel.length || !button.length) return;

  panel.prop("hidden", !isOpen);
  button
    .attr("aria-expanded", String(isOpen))
    .toggleClass("is-active", isOpen)
    .find("span")
    .text(isOpen ? "Ocultar classificação" : "Classificar");
  $("#btnSaveClassification").prop("hidden", !isOpen);

  if (isOpen) {
    window.requestAnimationFrame(() => {
      $(".classification-select.select2-hidden-accessible").trigger(
        "change.select2",
      );
    });
  }
}

function buildClassificationFields() {
  $(".classification-select").each(function () {
    if ($(this).hasClass("select2-hidden-accessible")) {
      $(this).select2("destroy");
      $(this).off("change.sire-classification");
    }
  });
  const fields = $("#classificationFields").empty();
  pillars.forEach((pillar) => {
    const id = `classification_${pillar.codigo}`;
    fields.append(
      `<div class="classification-field ${pillarIdentityClass(pillar.codigo)}"><label class="sire-pillar-title" for="${id}"><i class="sire-pillar-dot" aria-hidden="true"></i>${esc(pillar.nome)}</label><select id="${id}" class="classification-select" data-code="${esc(pillar.codigo)}" data-pillar-id="${pillar.id}" multiple></select></div>`,
    );
    const select = $(`#${id}`);
    appendPillarOptions(select, pillar);
    initializeClassificationSelect(select, pillar);
    /* Valores são criados exclusivamente no modal Administrar SIRE.
    select.on("select2:select", function (event) {
      const value = event.params.data;
      // O Select2 pode descartar propriedades extras do objeto criado; o
      // prefixo torna a identificação do novo valor determinística.
      if (!value.newValue && !String(value.id || "").startsWith("new:")) return;
      const selectElement = $(this);
      apiJson(REFERENCE_API, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "createValue",
          pilar_id: Number(selectElement.data("pillar-id")),
          nome: value.text,
        }),
      })
        .then((response) => {
          const current = (selectElement.val() || []).filter(
            (id) => id !== value.id,
          );
          selectElement
            .find(`option[value="${value.id.replace(/"/g, '\\"')}"]`)
            .remove();
          selectElement.append(
            new Option(response.value.text, response.value.id, true, true),
          );
          selectElement
            .val([...current, String(response.value.id)])
            .trigger("change");
          const pillar = pillars.find(
            (item) => item.id === Number(selectElement.data("pillar-id")),
          );
          if (
            pillar &&
            !pillar.valores.some((item) => item.id === response.value.id)
          )
            pillar.valores.push(response.value);
          refreshPillarOptions();
        })
        .catch((error) => {
          selectElement
            .find(`option[value="${value.id.replace(/"/g, '\\"')}"]`)
            .remove();
          selectElement.trigger("change");
          notify(error.message, true);
        });
    }); */
  });
}

function openLightbox(referenceId) {
  apiJson(
    `${REFERENCE_API}?action=getReferencia&id=${encodeURIComponent(referenceId)}`,
  )
    .then((response) => {
      currentReference = response.referencia;
      const reference = currentReference;
      $("#lb_titulo").text(
        reference.titulo ||
          reference.nomenclatura ||
          reference.nome_arquivo_exibicao ||
          "Referência",
      );
      $("#lb_obra, #lb_obra_det").text(
        reference.obra_nomenclatura || "Biblioteca visual",
      );
      $("#lb_ambiente, #lb_ambiente_det").text(
        reference.ambiente || originLabel(reference.origem),
      );
      $("#lb_estilo").text("Classificada pelos pilares da ALMA");
      $("#lb_nomenclatura").text(
        reference.nomenclatura || reference.titulo || "—",
      );
      $("#lb_arquivo").text(
        reference.nome_arquivo_exibicao || reference.url_externa || "—",
      );
      const modelPath = String(reference.modelo_pasta || "").trim();
      $("#btnCopyModelPath")
        .prop("hidden", !modelPath)
        .attr(
          "title",
          modelPath
            ? `Copiar a pasta de ${reference.modelo_nome_arquivo || "modelo .max"}`
            : "",
        );
      $("#lb_data").text(
        formatDate(reference.criado_em || reference.importado_em),
      );
      $("#lbMainImg").attr("src", reference.imagem_url);
      $("#lbOrigin").text(originLabel(reference.origem));
      $("#lbDescription").val(reference.descricao || "");
      $("#lbBtnGolden").toggleClass(
        "is-golden",
        Number(reference.golden_sample) === 1,
      );
      $("#lbGoldenIcon")
        .toggleClass("fa-solid", Number(reference.golden_sample) === 1)
        .toggleClass("fa-regular", Number(reference.golden_sample) !== 1);
      setClassificationPanelOpen(false);
      selectPillarValues(reference);
      classificationDirty = false;
      $("#refLightbox").addClass("is-open");
      window.setTimeout(() => $("#closeLightbox").trigger("focus"), 0);
    })
    .catch((error) => notify(error.message, true));
}

function closeLightbox() {
  $("#refLightbox").removeClass("is-open");
  setClassificationPanelOpen(false);
  $("#lbMainImg").attr("src", "");
  currentReference = null;
  classificationDirty = false;
  if (lastLightboxTrigger) $(lastLightboxTrigger).trigger("focus");
  lastLightboxTrigger = null;
}

function requestCloseLightbox() {
  if (!$("#refLightbox").hasClass("is-open")) return;
  if (!classificationDirty) {
    closeLightbox();
    return;
  }
  if (typeof Swal === "undefined") {
    notify("Salve ou cancele as alterações antes de fechar.", true);
    return;
  }
  Swal.fire({
    title: "Descartar alterações?",
    text: "A classificação ainda não foi salva.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Descartar",
    cancelButtonText: "Continuar editando",
  }).then((result) => {
    if (result.isConfirmed) closeLightbox();
  });
}

function openFullscreen(src) {
  const fullScreenDiv = $(`
    <div id="fullscreenImgDiv">
      <div id="image_wrapper"><img id="fullscreenImg" src="${esc(src)}" alt="Referência ampliada"></div>
    </div>`);
  $("body").append(fullScreenDiv);

  const $imageWrapper = fullScreenDiv.find("#image_wrapper");
  let zoom = 1;
  let dragging = false;
  let startX = 0;
  let startY = 0;
  let translateX = 0;
  let translateY = 0;
  const applyTransform = () =>
    $imageWrapper.css(
      "transform",
      `scale(${zoom}) translate(${translateX}px, ${translateY}px)`,
    );

  fullScreenDiv.on("wheel", (event) => {
    if (!event.ctrlKey) return;
    event.preventDefault();
    zoom = Math.min(
      5,
      Math.max(0.1, zoom + (event.originalEvent.deltaY < 0 ? 0.1 : -0.1)),
    );
    if (zoom === 0.1) {
      translateX = 0;
      translateY = 0;
    }
    applyTransform();
  });
  $imageWrapper.on("mousedown.fullscreen", (event) => {
    if (event.button !== 0) return;
    dragging = true;
    startX = event.clientX - translateX;
    startY = event.clientY - translateY;
    $imageWrapper.css("cursor", "grabbing");
    event.preventDefault();
  });
  $(document)
    .on("mousemove.fullscreen", (event) => {
      if (!dragging) return;
      translateX = event.clientX - startX;
      translateY = event.clientY - startY;
      applyTransform();
    })
    .on("mouseup.fullscreen", () => {
      dragging = false;
      $imageWrapper.css("cursor", "grab");
    });
  const close = () => {
    $(document).off(".fullscreen");
    fullScreenDiv.remove();
  };
  fullScreenDiv.on("click", (event) => {
    if (event.target === fullScreenDiv[0]) close();
  });
  $(document).on("keydown.fullscreen", (event) => {
    if (event.key === "Escape") close();
  });
  applyTransform();
}

function saveClassification() {
  if (!currentReference) return;
  const classificacoes = {};
  $(".classification-select").each(function () {
    classificacoes[$(this).data("code")] = ($(this).val() || []).map(Number);
  });
  apiJson(REFERENCE_API, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      action: "saveClassificacao",
      referencia_id: currentReference.id,
      descricao: $("#lbDescription").val(),
      classificacoes,
    }),
  })
    .then((response) => {
      currentReference = response.referencia;
      classificationDirty = false;
      notify("Classificação salva.");
      loadReferences();
    })
    .catch((error) => notify(error.message, true));
}

function toggleGolden(referenceId) {
  const id = Number(referenceId);
  if (!id || goldenPending.has(id)) return;

  const card = $(`.ref-card[data-id="${id}"]`);
  const cardButton = card.find(".card-heart");
  const modalButton = $("#lbBtnGolden");
  const wasGolden =
    card.hasClass("is-golden") ||
    (currentReference &&
      Number(currentReference.id) === id &&
      Number(currentReference.golden_sample) === 1);
  const applyGolden = (isGolden) => {
    card.toggleClass("is-golden", isGolden);
    cardButton
      .toggleClass("is-golden", isGolden)
      .attr(
        "title",
        isGolden ? "Remover dos Golden Samples" : "Marcar como Golden Sample",
      )
      .attr(
        "aria-label",
        isGolden ? "Remover dos Golden Samples" : "Marcar como Golden Sample",
      );
    cardButton
      .find("i")
      .toggleClass("fa-solid", isGolden)
      .toggleClass("fa-regular", !isGolden);
    if (currentReference && Number(currentReference.id) === id) {
      currentReference.golden_sample = isGolden ? 1 : 0;
      modalButton.toggleClass("is-golden", isGolden);
      $("#lbGoldenIcon")
        .toggleClass("fa-solid", isGolden)
        .toggleClass("fa-regular", !isGolden);
    }
  };

  goldenPending.add(id);
  cardButton.add(modalButton).addClass("is-loading").prop("disabled", true);
  applyGolden(!wasGolden);

  apiJson("golden_sample_ajax.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ referencia_id: id }),
  })
    .then((response) => {
      applyGolden(Number(response.golden_sample) === 1);
      if ($("#filterGolden").is(":checked")) {
        currentPage = 1;
        loadReferences();
      }
    })
    .catch((error) => {
      applyGolden(wasGolden);
      notify(error.message, true);
    })
    .finally(() => {
      goldenPending.delete(id);
      cardButton
        .add(modalButton)
        .removeClass("is-loading")
        .prop("disabled", false);
    });
}

function adminRequest(action, payload = null, query = null) {
  const params = new URLSearchParams({ action, ...(query || {}) });
  const options = payload
    ? {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action, ...payload }),
      }
    : undefined;
  return fetch(`${SIRE_ADMIN_API}?${params.toString()}`, options).then(
    async (response) => {
      const json = await response.json();
      if (!response.ok || json.success === false) {
        const error = new Error(
          json.message || "Não foi possível concluir a operação.",
        );
        error.payload = json;
        throw error;
      }
      return json;
    },
  );
}

function setAdminOpen(open) {
  const modal = $("#sireAdminModal");
  if (!modal.length) return;
  if (open) {
    modal.addClass("is-open").removeAttr("aria-hidden");
  } else {
    modal.removeClass("is-open").attr("aria-hidden", "true");
  }
  if (!open && lastAdminTrigger) {
    $(lastAdminTrigger).trigger("focus");
    lastAdminTrigger = null;
  }
}

function adminPillar() {
  return adminPillars.find(
    (pillar) => Number(pillar.id) === Number(adminSelectedPillarId),
  );
}

function renderAdminPillars() {
  const nav = $("#adminPillarsNav").empty();
  adminPillars.forEach((pillar) => {
    const selected = Number(pillar.id) === Number(adminSelectedPillarId);
    nav.append(
      `<button type="button" class="sire-admin-pillar ${pillarIdentityClass(pillar.codigo)}${selected ? " is-selected" : ""}" data-pillar-id="${pillar.id}" aria-current="${selected ? "true" : "false"}"><span class="sire-pillar-title"><i class="sire-pillar-dot" aria-hidden="true"></i>${esc(pillar.nome)}</span><b title="${pillar.valores_ativos} ativos de ${pillar.total_valores}">${pillar.total_valores}</b></button>`,
    );
  });
  const pillar = adminPillar();
  $("#adminPillarTitle").html(
    pillar
      ? `<span class="sire-pillar-title ${pillarIdentityClass(pillar.codigo)}"><i class="sire-pillar-dot" aria-hidden="true"></i>${esc(pillar.nome)}</span>`
      : "Pilar",
  );
}

function renderAdminValues(values) {
  const list = $("#adminValuesList").empty();
  const identityClass = pillarIdentityClass(adminPillar()?.codigo);
  $("#adminValuesEmpty").prop("hidden", values.length !== 0);
  values.forEach((value) => {
    const description = value.descricao || "Sem descrição";
    const usage = Number(value.uso) || 0;
    list.append(`<tr class="sire-admin-value ${identityClass}" data-value-id="${value.id}">
      <td><strong class="sire-pillar-title"><i class="sire-pillar-dot" aria-hidden="true"></i>${esc(value.nome)}</strong></td>
      <td><span class="sire-admin-value-description" title="${esc(description)}">${esc(description)}</span></td>
      <td>${value.caracteristicas} ${Number(value.caracteristicas) === 1 ? "característica" : "características"}</td>
      <td><span class="sire-admin-usage">${usage} ${usage === 1 ? "referência" : "referências"}</span></td>
      <td><span class="sire-admin-status ${value.ativo ? "is-active" : "is-inactive"}">${value.ativo ? "Ativo" : "Inativo"}</span></td>
      <td class="sire-admin-row-actions"><button type="button" class="btn-icon-text admin-edit-value" data-value-id="${value.id}"><i class="fa-solid fa-pen"></i><span>Editar</span></button>${usage === 0 ? `<button type="button" class="btn-icon-text is-danger admin-delete-value" data-value-id="${value.id}"><i class="fa-solid fa-trash"></i><span>Excluir</span></button>` : `<button type="button" class="btn-icon-text admin-toggle-value" data-value-id="${value.id}" data-active="${value.ativo ? "0" : "1"}"><i class="fa-solid ${value.ativo ? "fa-ban" : "fa-rotate-left"}"></i><span>${value.ativo ? "Desativar" : "Reativar"}</span></button>`}</td>
    </tr>`);
  });
}

function loadAdminValues() {
  if (!adminSelectedPillarId) return Promise.resolve();
  const search = $("#adminValueSearch").val().trim();
  $("#adminValuesList").html(
    '<tr><td colspan="6" class="sire-admin-loading">Carregando valores...</td></tr>',
  );
  return adminRequest("getValues", null, {
    pilar_id: adminSelectedPillarId,
    search,
  })
    .then((response) => renderAdminValues(response.valores || []))
    .catch((error) => {
      $("#adminValuesList").empty();
      $("#adminValuesEmpty").text(error.message).prop("hidden", false);
    });
}

function loadAdminCatalog() {
  return adminRequest("getCatalog")
    .then((response) => {
      adminPillars = response.pilares || [];
      if (
        !adminPillars.some(
          (pillar) => Number(pillar.id) === Number(adminSelectedPillarId),
        )
      ) {
        adminSelectedPillarId = adminPillars[0] ? adminPillars[0].id : null;
      }
      renderAdminPillars();
      return loadAdminValues();
    })
    .catch((error) => notify(error.message, true));
}

function addAdminFeature(value = "") {
  const index = $("#adminFeaturesList .sire-admin-feature").length + 1;
  $("#adminFeaturesList").append(
    `<div class="sire-admin-feature"><span class="sire-admin-feature-order">${index}</span><label class="sr-only">Característica ${index}</label><input type="text" maxlength="255" value="${esc(value)}" placeholder="Ex.: Baixo ruído visual"><button type="button" class="btn-icon-text is-danger admin-remove-feature" aria-label="Remover característica"><i class="fa-solid fa-xmark"></i></button></div>`,
  );
}

function resetAdminForm(value = null) {
  const pillar = adminPillar();
  const used = Number(value?.uso || 0) > 0;
  $("#adminValueForm").prop("hidden", false);
  $("#adminValuesView").prop("hidden", true);
  $("#adminFormTitle").text(value ? `Editar ${value.nome}` : "Novo valor");
  $("#adminValueId").val(value?.id || "");
  const select = $("#adminValuePillar").empty();
  adminPillars.forEach((item) =>
    select.append(
      new Option(
        item.nome,
        item.id,
        false,
        Number(item.id) === Number(value?.pilar_id || pillar?.id),
      ),
    ),
  );
  select.prop("disabled", used);
  $("#adminPillarChangeHelp").prop("hidden", !used);
  $("#adminValueName").val(value?.nome || "");
  $("#adminValueDescription").val(value?.descricao || "");
  $(
    `input[name="adminValueStatus"][value="${value && !value.ativo ? "0" : "1"}"]`,
  ).prop("checked", true);
  $("#adminFeaturesList").empty();
  (value?.caracteristicas || []).forEach((feature) =>
    addAdminFeature(feature.descricao),
  );
  window.setTimeout(() => $("#adminValueName").trigger("focus"), 0);
}

function closeAdminForm() {
  $("#adminValueForm").prop("hidden", true)[0]?.reset();
  $("#adminValuesView").prop("hidden", false);
}

function editAdminValue(valueId) {
  adminRequest("getValue", null, { id: valueId })
    .then((response) => resetAdminForm(response.valor))
    .catch((error) => notify(error.message, true));
}

function syncLibraryPillars() {
  return loadPillars(true).then(() => {
    if (!$("#refLightbox").hasClass("is-open")) buildClassificationFields();
    currentPage = 1;
    return loadReferences();
  });
}

function confirmAdminAction(options) {
  if (typeof Swal === "undefined") return Promise.resolve(false);
  return Swal.fire(options).then((result) => result.isConfirmed);
}

function normalizeReleasedEventRefs(refs) {
  const releasedStatuses = new Set([
    "liberado",
    "liberada",
    "liberado para sire",
    "liberada para sire",
  ]);
  const seen = new Set();

  return (Array.isArray(refs) ? refs : []).filter((ref) => {
    if (
      !releasedStatuses.has(
        String(ref.status || "")
          .trim()
          .toLowerCase(),
      )
    ) {
      return false;
    }
    const source =
      ref.tipo === "url"
        ? String(ref.url || "")
            .trim()
            .replace(/\/+$/, "")
            .toLowerCase()
        : String(ref.hash_sha1 || ref.caminho || "").trim();
    if (!source) return false;
    const key = `${ref.tipo}:${source}`;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

function renderEventRefs(rawRefs) {
  const refs = normalizeReleasedEventRefs(rawRefs);
  const section = $("#sireEventQueue");
  if (refs.length < 2) {
    section.prop("hidden", true).attr("aria-hidden", "true");
    $("#sireEventRefsList").empty();
    return;
  }

  section.prop("hidden", false).removeAttr("aria-hidden");
  $("#sireEventQueueCount").text(`${refs.length} referências liberadas`);
  $("#sireEventRefsList").html(
    refs
      .map((ref) => {
        const href =
          ref.tipo === "url"
            ? ref.url
            : `../${String(ref.caminho || "").replace(/^\/+/, "")}`;
        const preview =
          ref.tipo === "upload"
            ? `<img loading="lazy" decoding="async" src="../thumb.php?path=${encodeURIComponent(String(ref.caminho || ""))}&w=160&q=75" alt="${esc(ref.nome_original || ref.nome_arquivo || "Referência")}" onerror="this.src='../assets/logo.jpg'">`
            : '<i class="fa-solid fa-link"></i>';
        const title =
          ref.nome_original || ref.nome_arquivo || ref.url || "Referência";
        const eventDate = formatDate(ref.data_evento || ref.criado_em);
        const observation = ref.observacao
          ? `<span title="${esc(ref.observacao)}"><i class="fa-regular fa-comment"></i>${esc(ref.observacao)}</span>`
          : "";
        return `<article class="sire-event-ref-card"><a class="sire-event-ref-preview ${ref.tipo === "upload" ? "is-upload" : "is-url"}" href="${esc(href)}" target="_blank" rel="noopener">${preview}</a><div class="sire-event-ref-body"><div class="sire-event-ref-title-row"><strong>${esc(title)}</strong><span>${esc(ref.origem || "Evento")}</span></div><div class="sire-event-ref-meta"><span><i class="fa-solid fa-building"></i>${esc(ref.obra_nomenclatura || "Obra")}</span><span><i class="fa-regular fa-calendar"></i>${esc(ref.tipo_evento || "Evento")} · ${esc(eventDate)}</span>${ref.participantes ? `<span><i class="fa-solid fa-users"></i>${esc(ref.participantes)}</span>` : ""}${observation}</div></div></article>`;
      })
      .join(""),
  );
}

function loadEventRefs() {
  const refreshButton = $("#btnReloadEventRefs")
    .addClass("is-loading")
    .prop("disabled", true);
  $.getJSON(EVENT_REFS_API)
    .done((response) => {
      if (!response || response.success === false) {
        $("#sireEventQueue").prop("hidden", true).attr("aria-hidden", "true");
        notify(
          response?.error || "Não foi possível carregar a fila de eventos.",
          true,
        );
        return;
      }
      renderEventRefs(response.data || response.referencias || []);
    })
    .fail(() => {
      $("#sireEventQueue").prop("hidden", true).attr("aria-hidden", "true");
      $("#sireEventRefsList").empty();
      notify("Não foi possível carregar as referências de evento.", true);
    })
    .always(() =>
      refreshButton.removeClass("is-loading").prop("disabled", false),
    );
}

function openAddReference() {
  $("#addReferenceModal").addClass("is-open");
}
function closeAddReference() {
  $("#addReferenceModal").removeClass("is-open");
  $("#addReferenceForm")[0].reset();
  $("#addReferenceType").trigger("change");
}

function submitAddReference(form) {
  const data = new FormData(form);
  data.append("action", "addReference");
  apiJson(REFERENCE_API, { method: "POST", body: data })
    .then((response) => {
      closeAddReference();
      notify("Referência adicionada.");
      currentPage = 1;
      loadReferences();
      openLightbox(response.referencia.id);
    })
    .catch((error) => notify(error.message, true));
}

$(function () {
  $("#searchInput").attr(
    "placeholder",
    "Buscar referências, ex.: fachada noturna madeira, quarto minimalista...",
  );
  Promise.all([
    loadPillars(),
    $.Deferred((deferred) => deferred.resolve()).promise(),
  ])
    .then(() => {
      buildClassificationFields();
      loadReferences();
    })
    .catch((error) => notify(error.message, true));
  loadEventRefs();
  $("#btnReloadEventRefs").on("click", loadEventRefs);
  $("#refGrid").on("click", ".ref-card", function (event) {
    if (!$(event.target).closest(".card-interactive, .card-heart").length) {
      lastLightboxTrigger = this;
      openLightbox($(this).data("id"));
    }
  });
  $("#refGrid").on("keydown", ".ref-card", function (event) {
    if (
      (event.key === "Enter" || event.key === " ") &&
      !$(event.target).closest(".card-interactive, .card-heart").length
    ) {
      event.preventDefault();
      lastLightboxTrigger = this;
      openLightbox($(this).data("id"));
    }
  });
  $("#refGrid").on("click", ".card-heart", function (event) {
    event.stopPropagation();
    toggleGolden($(this).data("id"));
  });
  $("#refGrid").on("click", ".card-open-reference", function (event) {
    event.stopPropagation();
    lastLightboxTrigger = $(this).closest(".ref-card")[0];
    openLightbox($(this).data("id"));
  });
  $("#refGrid").on("click", ".card-copy-link", function (event) {
    event.stopPropagation();

    const link = $(this).data("url");

    if (!link) {
      notify("Link da imagem não encontrado.", true);
      return;
    }

    if (navigator.clipboard?.writeText) {
      navigator.clipboard
        .writeText(link)
        .then(() => notify("Link da imagem copiado."))
        .catch(() => notify("Não foi possível copiar o link.", true));
    } else {
      notify("A cópia de link não é suportada neste navegador.", true);
    }
  });
  $("#refGrid").on("click", ".card-similar", function (event) {
    event.stopPropagation();
    notify("Visualização de similares estará disponível em breve.");
  });
  $("#lbBtnGolden").on("click", () => {
    if (currentReference) toggleGolden(currentReference.id);
  });
  $("#btnToggleClassification").on("click", function () {
    setClassificationPanelOpen($(this).attr("aria-expanded") !== "true");
  });
  $("#closeLightbox, #closeLightboxFooter").on("click", requestCloseLightbox);
  $("#refLightbox").on("click", function (event) {
    if (event.target === this) requestCloseLightbox();
  });
  $("#lbMainImg").on("click", function () {
    const src = $(this).attr("src");
    if (src) openFullscreen(src);
  });
  $("#btnSaveClassification").on("click", saveClassification);
  $("#btnCopyModelPath").on("click", () => {
    const modelPath = String(currentReference?.modelo_pasta || "").trim();
    if (!modelPath) {
      notify("Pasta do modelo .max não encontrada para esta imagem.", true);
      return;
    }

    copyToClipboard(modelPath)
      .then(() => notify("Pasta do modelo copiada."))
      .catch(() => notify("Não foi possível copiar a pasta do modelo.", true));
  });
  $("#lbDescription").on("input", () => {
    classificationDirty = true;
  });
  $("#btnVerOriginal").on("click", () => {
    if (currentReference)
      window.open(currentReference.imagem_url, "_blank", "noopener");
  });
  $("#searchInput").on("input", function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      currentPage = 1;
      loadReferences();
    }, 320);
    $("#searchClear").toggleClass("visible", Boolean($(this).val().trim()));
  });
  $("#searchClear").on("click", () =>
    $("#searchInput").val("").trigger("input").focus(),
  );
  $("#filterObra, #filterAmbiente, #filterGolden").on("change", () => {
    currentPage = 1;
    loadReferences();
  });
  $("#btnAplicar").on("click", () => {
    currentPage = 1;
    loadReferences();
    setFilterDrawerOpen(false);
  });
  $("#btnLimpar").on("click", () => {
    clearAllFilters();
  });
  $("#btnContextClear").on("click", clearAllFilters);
  $("#pilarFilters").on("change", "[data-pillar-value]", function () {
    const code = $(this).data("pillar-value");
    const selected = $(`[data-pillar-value="${code}"]:checked`)
      .map(function () {
        return String($(this).val());
      })
      .get();
    $(`.pilar-filter[data-code="${code}"]`).val(selected);
    currentPage = 1;
    loadReferences();
  });
  $("#pilarFilters").on("input", "[data-pillar-search]", function () {
    const query = String($(this).val() || "")
      .trim()
      .toLocaleLowerCase("pt-BR");
    $(this)
      .closest(".sire-filter-accordion-body")
      .find(".sire-filter-value")
      .each(function () {
        $(this).toggleClass(
          "is-search-hidden",
          query && !$(this).text().toLocaleLowerCase("pt-BR").includes(query),
        );
      });
  });
  $("#pilarFilters").on("click", ".sire-filter-more", function () {
    const accordion = $(this).closest(".sire-filter-accordion");
    accordion.toggleClass("is-showing-all");
    $(this).text(
      accordion.hasClass("is-showing-all") ? "Ver menos" : "Ver mais",
    );
  });
  $("#activeFilterChips").on("click", ".sire-filter-chip", function () {
    const key = String($(this).data("filter-key"));
    if (key === "golden") $("#filterGolden").prop("checked", false);
    else if (key === "obra") $("#filterObra").val("");
    else if (key === "ambiente") $("#filterAmbiente").val("");
    else if (key.startsWith("pillar:")) {
      const [, code, value] = key.split(":");
      const select = $(`.pilar-filter[data-code="${code}"]`);
      select.val((select.val() || []).filter((id) => String(id) !== value));
      $(`[data-pillar-value="${code}"][value="${value}"]`).prop(
        "checked",
        false,
      );
    }
    currentPage = 1;
    loadReferences();
  });
  $("#btnLoadMore").on("click", () => {
    currentPage++;
    loadReferences(true);
  });
  $("#btnAddReference").on("click", openAddReference);
  $("#closeAddReference, #cancelAddReference").on("click", closeAddReference);
  $("#addReferenceModal").on("click", function (event) {
    if (event.target === this) closeAddReference();
  });
  $("#addReferenceType")
    .on("change", function () {
      const isUpload = $(this).val() === "Upload";
      $("#addReferenceUploadGroup").prop("hidden", !isUpload);
      $("#addReferenceUrlGroup").prop("hidden", isUpload);
      $("#addReferenceFile").prop("required", isUpload);
      $("#addReferenceUrl").prop("required", !isUpload);
    })
    .trigger("change");
  $("#addReferenceForm").on("submit", function (event) {
    event.preventDefault();
    submitAddReference(this);
  });
  $("#btnManageSire").on("click", function () {
    lastAdminTrigger = this;
    setAdminOpen(true);
    closeAdminForm();
    loadAdminCatalog().then(() =>
      window.setTimeout(() => $("#closeSireAdmin").trigger("focus"), 0),
    );
  });
  $("#closeSireAdmin").on("click", () => setAdminOpen(false));
  $("#sireAdminModal").on("click", function (event) {
    if (event.target === this) setAdminOpen(false);
  });
  $("#adminPillarsNav").on("click", ".sire-admin-pillar", function () {
    adminSelectedPillarId = Number($(this).data("pillar-id"));
    $("#adminValueSearch").val("");
    closeAdminForm();
    renderAdminPillars();
    loadAdminValues();
  });
  $("#adminValueSearch").on("input", () => {
    clearTimeout(adminSearchTimer);
    adminSearchTimer = setTimeout(loadAdminValues, 250);
  });
  $("#btnNewSireValue").on("click", () => resetAdminForm());
  $("#btnCancelSireValue, #btnCancelSireValueFooter").on(
    "click",
    closeAdminForm,
  );
  $("#btnAddSireFeature").on("click", () => addAdminFeature());
  $("#adminFeaturesList").on("click", ".admin-remove-feature", function () {
    $(this).closest(".sire-admin-feature").remove();
    $("#adminFeaturesList .sire-admin-feature-order").each(function (index) {
      $(this).text(index + 1);
    });
  });
  $("#adminValuesList").on("click", ".admin-edit-value", function () {
    editAdminValue(Number($(this).data("value-id")));
  });
  $("#adminValuesList").on("click", ".admin-toggle-value", function () {
    const button = $(this);
    const activate = String(button.data("active")) === "1";
    confirmAdminAction({
      title: activate ? "Reativar valor?" : "Desativar valor?",
      text: activate
        ? "O valor voltará a aparecer nas novas classificações."
        : "O valor permanecerá nas referências antigas, mas não poderá ser escolhido em novas classificações.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: activate ? "Reativar" : "Desativar",
      cancelButtonText: "Cancelar",
    }).then((confirmed) => {
      if (!confirmed) return;
      adminRequest("toggleStatus", {
        id: Number(button.data("value-id")),
        ativo: activate,
      })
        .then(() => Promise.all([loadAdminCatalog(), syncLibraryPillars()]))
        .then(() => notify(activate ? "Valor reativado." : "Valor desativado."))
        .catch((error) => notify(error.message, true));
    });
  });
  $("#adminValuesList").on("click", ".admin-delete-value", function () {
    const valueId = Number($(this).data("value-id"));
    confirmAdminAction({
      title: "Excluir valor definitivamente?",
      text: "Este valor ainda não está associado a nenhuma referência.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Excluir",
      cancelButtonText: "Cancelar",
    }).then((confirmed) => {
      if (!confirmed) return;
      adminRequest("deleteValue", { id: valueId })
        .then(() => Promise.all([loadAdminCatalog(), syncLibraryPillars()]))
        .then(() => notify("Valor excluído."))
        .catch((error) => notify(error.message, true));
    });
  });
  $("#adminValueForm").on("submit", function (event) {
    event.preventDefault();
    if (!this.reportValidity()) return;
    const submit = (confirmSimilar = false) => {
      const payload = {
        id: Number($("#adminValueId").val()) || 0,
        pilar_id: Number($("#adminValuePillar").val()),
        nome: $("#adminValueName").val(),
        descricao: $("#adminValueDescription").val(),
        ativo: $("input[name='adminValueStatus']:checked").val() === "1",
        caracteristicas: $("#adminFeaturesList input")
          .map(function () {
            return $(this).val();
          })
          .get(),
        confirmar_semelhante: confirmSimilar,
      };
      const saveButton = $("#btnSaveSireValue")
        .prop("disabled", true)
        .text("Salvando...");
      adminRequest("saveValue", payload)
        .then(() => Promise.all([loadAdminCatalog(), syncLibraryPillars()]))
        .then(() => {
          closeAdminForm();
          notify("Valor salvo.");
        })
        .catch((error) => {
          if (error.payload?.requires_confirmation) {
            confirmAdminAction({
              title: "Valor semelhante encontrado",
              text: error.message,
              icon: "question",
              showCancelButton: true,
              confirmButtonText: "Salvar mesmo assim",
              cancelButtonText: "Revisar",
            }).then((confirmed) => {
              if (confirmed) submit(true);
            });
            return;
          }
          notify(error.message, true);
        })
        .finally(() => saveButton.prop("disabled", false).text("Salvar valor"));
    };
    submit();
  });
  const setFilterDrawerOpen = (open) => {
    $("#filters").toggleClass("open", open);
    $("#filterBackdrop").prop("hidden", !open).toggleClass("is-visible", open);
    $("#filter-toggle-btn").attr("aria-expanded", String(open));
    $("body").toggleClass("sire-filter-drawer-open", open);
  };
  $("#filter-toggle-btn").on("click", () =>
    setFilterDrawerOpen(!$("#filters").hasClass("open")),
  );
  $("#btnCloseFilters").on("click", () => setFilterDrawerOpen(false));
  $("#filterBackdrop").on("click", () => setFilterDrawerOpen(false));
  $(document).on("keydown", (event) => {
    if (event.key === "Escape") {
      if (
        $(event.target).hasClass("select2-search__field") ||
        $(event.target).closest(".select2-container").length
      )
        return;
      if ($("#filters").hasClass("open")) {
        setFilterDrawerOpen(false);
        return;
      }
      if ($("#sireAdminModal").hasClass("is-open")) {
        setAdminOpen(false);
        return;
      }
      if ($("#refLightbox").hasClass("is-open")) {
        requestCloseLightbox();
        return;
      }
      closeAddReference();
    }
    const activeModal = $("#sireAdminModal").hasClass("is-open")
      ? $("#sireAdminModal")
      : $("#refLightbox").hasClass("is-open")
        ? $("#refLightbox")
        : $();
    if (event.key === "Tab" && activeModal.length) {
      const focusable = activeModal
        .find(
          "button:not([disabled]):visible, [href]:visible, input:visible, select:visible, textarea:visible",
        )
        .toArray();
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      }
      if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }
  });
});
