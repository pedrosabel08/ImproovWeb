(function () {
  const btnOpen = document.getElementById("btnAddClienteObra");
  const modal = document.getElementById("modalAddClienteObra");
  if (!btnOpen || !modal) return;

  const elements = {
    modePicker: document.getElementById("onbModePicker"),
    modeButtons: Array.from(modal.querySelectorAll("[data-onboarding-mode]")),
    modalTitle: document.getElementById("onbModalTitle"),
    modalDescription: document.getElementById("onbModalDescription"),
    close: document.getElementById("closeAddClienteObra"),
    clienteSelect: document.getElementById("onbClienteSelect"),
    clienteNomeCompleto: document.getElementById("onbClienteNomeCompleto"),
    clienteSigla: document.getElementById("onbClienteSigla"),
    clienteSiglaBadge: document.getElementById("onbClienteSiglaBadge"),
    projetoInterno: document.getElementById("onbProjetoInterno"),
    projetoSiglaBadge: document.getElementById("onbProjetoSiglaBadge"),
    projetoComercial: document.getElementById("onbProjetoComercial"),
    codigoInterno: document.getElementById("onbCodigoInterno"),
    nomenclaturaBadge: document.getElementById("onbNomenclaturaBadge"),
    observacoes: document.getElementById("onbObservacoes"),
    packageStill: document.getElementById("onbPackageStill"),
    stillQtd: document.getElementById("onbStillQtd"),
    stillPrazo: document.getElementById("onbStillPrazo"),
    stillDiasCorridos: document.getElementById("onbStillDiasCorridos"),
    packageAnimation: document.getElementById("onbPackageAnimation"),
    animationSeconds: document.getElementById("onbAnimationSeconds"),
    animationPrazo: document.getElementById("onbAnimationPrazo"),
    animationDiasCorridos: document.getElementById("onbAnimationDiasCorridos"),
    packageFilm: document.getElementById("onbPackageFilm"),
    filmDuration: document.getElementById("onbFilmDuration"),
    filmPrazo: document.getElementById("onbFilmPrazo"),
    filmDiasCorridos: document.getElementById("onbFilmDiasCorridos"),
    imageFile: document.getElementById("onbImageFile"),
    uploadBox: modal.querySelector(".onb-upload-box"),
    importedFileName: document.getElementById("onbImportedFileName"),
    totalImages: document.getElementById("onbTotalImages"),
    namedImages: document.getElementById("onbNamedImages"),
    duplicateImages: document.getElementById("onbDuplicateImages"),
    errorImages: document.getElementById("onbErrorImages"),
    manualImages: document.getElementById("onbManualImages"),
    addManualImages: document.getElementById("onbAddManualImages"),
    clearImages: document.getElementById("onbClearImages"),
    previewList: document.getElementById("onbImagePreviewList"),
    previewCaption: document.getElementById("onbPreviewCaption"),
    previewTotal: document.getElementById("onbPreviewTotal"),
    selectAllImages: document.getElementById("onbSelectAllImages"),
    contractBatchValue: document.getElementById("onbContractBatchValue"),
    applyContract: document.getElementById("onbApplyContract"),
    replicateGross: document.getElementById("onbReplicateGross"),
    replicateTax: document.getElementById("onbReplicateTax"),
    selectedImageCount: document.getElementById("onbSelectedImageCount"),
    extraProjectField: document.getElementById("onbExtraProjectField"),
    extraProject: document.getElementById("onbExtraProject"),
    stillItemsHeader: document.getElementById("onbStillItemsHeader"),
    stillImagesTitle: document.getElementById("onbStillImagesTitle"),
    stillImageImport: document.getElementById("onbStillImageImport"),
    stillImageManual: document.getElementById("onbStillImageManual"),
    stillImageCommercial: document.getElementById("onbStillImageCommercial"),
    extraPackage: document.getElementById("onbExtraPackage"),
    extraPackageDetails: document.getElementById("onbExtraPackageDetails"),
    extraPackageFields: document.getElementById("onbExtraPackageFields"),
    extraPackageQuantityField: document.getElementById("onbExtraPackageQuantityField"),
    extraPackageQuantity: document.getElementById("onbExtraPackageQuantity"),
    extraPackageDurationField: document.getElementById("onbExtraPackageDurationField"),
    extraPackageDuration: document.getElementById("onbExtraPackageDuration"),
    extraPackageRevenueField: document.getElementById("onbExtraPackageRevenueField"),
    extraPackageRevenue: document.getElementById("onbExtraPackageRevenue"),
    photoServiceValue: document.getElementById("onbPhotoServiceValue"),
    animationRevenue: document.getElementById("onbAnimationRevenue"),
    filmRevenue: document.getElementById("onbFilmRevenue"),
    otherCategory: document.getElementById("onbOtherCategory"),
    otherDescription: document.getElementById("onbOtherDescription"),
    otherQuantity: document.getElementById("onbOtherQuantity"),
    otherUnit: document.getElementById("onbOtherUnit"),
    otherRevenue: document.getElementById("onbOtherRevenue"),
    addOtherItem: document.getElementById("onbAddOtherItem"),
    otherItemsList: document.getElementById("onbOtherItemsList"),
    contactsList: document.getElementById("onbContactsList"),
    contactsState: document.getElementById("onbContactsState"),
    contactsCounter: document.getElementById("onbContactsCounter"),
    draftContactsList: document.getElementById("onbDraftContactsList"),
    contactModeNote: document.getElementById("onbContactModeNote"),
    contactName: document.getElementById("onbContactName"),
    contactRole: document.getElementById("onbContactRole"),
    contactType: document.getElementById("onbContactType"),
    contactEmail: document.getElementById("onbContactEmail"),
    contactPhone: document.getElementById("onbContactPhone"),
    contactNotes: document.getElementById("onbContactNotes"),
    addContact: document.getElementById("onbAddContact"),
    prevStep: document.getElementById("onbPrevStep"),
    nextStep: document.getElementById("onbNextStep"),
    submit: document.getElementById("onbSubmitFlow"),
    cancel: document.getElementById("onbCancelFlow"),
    stepButtons: Array.from(
      document.querySelectorAll("#onbStepper [data-step]"),
    ),
    panels: Array.from(document.querySelectorAll("[data-step-panel]")),
    summaryList: document.getElementById("onbSummaryList"),
    checklistList: document.getElementById("onbChecklistList"),
  };

  const packageCardMap = {
    still: modal.querySelector('[data-package-card="still"]'),
    animation: modal.querySelector('[data-package-card="animation"]'),
    film: modal.querySelector('[data-package-card="film"]'),
  };

  const contactTypeLabels = {
    COMERCIAL: "Comercial",
    APROVACAO: "Aprovacao",
    FINANCEIRO: "Financeiro",
    MARKETING: "Marketing",
    ARQUITETO: "Arquiteto",
    OUTRO: "Outro",
  };

  function defaultContactDraft() {
    return {
      draftId: String(Date.now() + Math.random()),
      name: "",
      role: "",
      email: "",
      phone: "",
      type: "OUTRO",
      notes: "",
    };
  }

  function createContactsState() {
    return {
      available: [],
      selectedIds: [],
      drafts: [],
      loading: false,
      form: defaultContactDraft(),
    };
  }

  function createInitialState() {
    return {
      mode: null,
      step: 1,
      clientId: "",
      clientName: "",
      clientFullName: "",
      clientCode: "",
      clientCodeTouched: false,
      projectInternal: "",
      projectCommercial: "",
      projectInternalTouched: false,
      code: "",
      notes: "",
      packages: {
        still: {
          enabled: false,
          quantity: "",
          deadline_days: "",
          deadline_calendar_days: false,
        },
        animation: {
          enabled: false,
          seconds: "",
          deadline_days: "",
          deadline_calendar_days: false,
        },
        film: {
          enabled: false,
          duration: "",
          deadline_days: "",
          deadline_calendar_days: false,
        },
      },
      images: {
        file_name: "",
        source: "manual",
        entries: [],
        values: {},
        selected: [],
        duplicates: [],
        errors: [],
      },
      otherItems: [],
      extraProjectId: "",
      extraPackages: [],
      extraPackageId: "",
      contacts: createContactsState(),
      unique: {
        loading: false,
        checked: false,
        clienteSiglaExists: false,
        obraSiglaExists: false,
        nomenclaturaExists: false,
      },
      isSubmitting: false,
    };
  }

  let state = createInitialState();
  let uniqueCheckTimer = null;
  let uniqueCheckSeq = 0;
  let allowMotion = !window.matchMedia("(prefers-reduced-motion: reduce)")
    .matches;
  const countTweens = new WeakMap();

  if (window.gsap) {
    const motionQueries = window.gsap.matchMedia();
    motionQueries.add("(prefers-reduced-motion: reduce)", () => {
      allowMotion = false;
    });
    motionQueries.add("(prefers-reduced-motion: no-preference)", () => {
      allowMotion = true;
    });
  }

  function formatMoney(value) {
    return new Intl.NumberFormat("pt-BR", {
      style: "currency",
      currency: "BRL",
    }).format(Number(value) || 0);
  }

  function animateCount(element, target) {
    const next = Math.max(0, Number(target) || 0);
    if (!element) return;
    const previous = Number(element.dataset.countValue || 0);
    if (!window.gsap || !allowMotion || previous === next) {
      element.textContent = String(next);
      element.dataset.countValue = String(next);
      return;
    }
    countTweens.get(element)?.kill();
    const counter = { value: previous };
    const tween = window.gsap.to(counter, {
      value: next,
      duration: 0.42,
      ease: "power1.out",
      onUpdate: () => {
        element.textContent = String(Math.round(counter.value));
      },
      onComplete: () => {
        element.textContent = String(next);
        element.dataset.countValue = String(next);
      },
    });
    countTweens.set(element, tween);
  }

  function animateStepCards() {
    if (!window.gsap || !allowMotion) return;
    const visibleCards = Array.from(
      modal.querySelectorAll(
        ".onb-panel.is-active .onb-card, .onb-sidebar-card",
      ),
    ).filter((card) => card.getClientRects().length);
    if (!visibleCards.length) return;
    window.gsap.from(visibleCards, {
      autoAlpha: 0,
      y: 10,
      duration: 0.32,
      stagger: 0.055,
      ease: "power2.out",
      clearProps: "opacity,visibility,transform",
      overwrite: "auto",
    });
  }

  function notify(message, type) {
    if (window.Toastify) {
      Toastify({
        text: message,
        duration: 3500,
        gravity: "top",
        position: "right",
        style: {
          background:
            type === "error"
              ? "linear-gradient(135deg, #dc2626, #991b1b)"
              : "linear-gradient(135deg, #2563eb, #1d4ed8)",
        },
      }).showToast();
      return;
    }
    alert(message);
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function contactTypeLabel(value) {
    return contactTypeLabels[value] || contactTypeLabels.OUTRO;
  }

  function onlyLetters(value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[^A-Za-z]/g, "")
      .toUpperCase()
      .slice(0, 3);
  }

  function projectCodePart(value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[^A-Za-z0-9]/g, "")
      .toUpperCase()
      .slice(0, 3);
  }

  function inferProjectCode(value) {
    const words = String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toUpperCase()
      .match(/[A-Z0-9]+/g);

    if (!words || !words.length) return "";

    const usefulWords = words.filter(
      (word) => !["DE", "DA", "DO", "DAS", "DOS", "E"].includes(word),
    );
    const sourceWords = usefulWords.length ? usefulWords : words;
    const initials = sourceWords.map((word) => word.charAt(0)).join("");

    return projectCodePart(
      initials.length >= 3 ? initials : sourceWords.join(""),
    );
  }

  function fallbackClientCode(label) {
    return onlyLetters(label);
  }

  function getClientFullName() {
    return state.clientFullName || selectedClientLabel();
  }

  function projectRealName() {
    return [getClientFullName(), state.projectCommercial]
      .map((part) => String(part || "").trim())
      .filter(Boolean)
      .join(" - ");
  }

  function syncNomenclature() {
    const clientCode = onlyLetters(state.clientCode);
    const projectInternal = projectCodePart(state.projectInternal);
    state.clientCode = clientCode;
    state.projectInternal = projectInternal;
    state.code =
      clientCode && projectInternal ? `${clientCode}_${projectInternal}` : "";

    elements.clienteSigla.value = clientCode;
    elements.projetoInterno.value = projectInternal;
    elements.codigoInterno.value = state.code;
  }

  function hasUniqueConflicts() {
    return Boolean(
      state.unique.clienteSiglaExists ||
      state.unique.obraSiglaExists ||
      state.unique.nomenclaturaExists,
    );
  }

  function setBadgeState(element, show) {
    if (!element) return;
    element.hidden = !show;
  }

  function renderUniqueBadges() {
    setBadgeState(elements.clienteSiglaBadge, state.unique.clienteSiglaExists);
    setBadgeState(elements.projetoSiglaBadge, state.unique.obraSiglaExists);
    setBadgeState(elements.nomenclaturaBadge, state.unique.nomenclaturaExists);
  }

  function clearUniqueState() {
    state.unique = {
      loading: false,
      checked: false,
      clienteSiglaExists: false,
      obraSiglaExists: false,
      nomenclaturaExists: false,
    };
    renderUniqueBadges();
  }

  function scheduleUniqueCheck() {
    window.clearTimeout(uniqueCheckTimer);
    state.unique = {
      loading: false,
      checked: false,
      clienteSiglaExists: false,
      obraSiglaExists: false,
      nomenclaturaExists: false,
    };
    renderUniqueBadges();

    if (!state.clientCode && !state.projectInternal && !state.code) {
      clearUniqueState();
      return;
    }

    uniqueCheckTimer = window.setTimeout(checkUniqueSiglas, 260);
  }

  async function checkUniqueSiglas() {
    const params = new URLSearchParams({
      cliente_sigla: state.clientCode || "",
      obra_sigla: state.projectInternal || "",
      nomenclatura: state.code || "",
      cliente_id:
        state.clientId !== "" && state.clientId !== "0" ? state.clientId : "",
    });
    const seq = ++uniqueCheckSeq;
    state.unique.loading = true;

    try {
      const response = await fetch(
        "checkOnboardingSiglas.php?" + params.toString(),
        {
          credentials: "same-origin",
        },
      );
      const data = await response.json().catch(() => null);

      if (seq !== uniqueCheckSeq) return;
      if (!response.ok || !data || !data.success) {
        throw new Error(
          data && data.message ? data.message : "Erro ao validar siglas.",
        );
      }

      state.unique = {
        loading: false,
        checked: true,
        clienteSiglaExists: Boolean(data.cliente_sigla_exists),
        obraSiglaExists: Boolean(data.obra_sigla_exists),
        nomenclaturaExists: Boolean(data.nomenclatura_exists),
      };
      renderUniqueBadges();
      renderSummary();
    } catch (error) {
      console.error(error);
      if (seq === uniqueCheckSeq) {
        state.unique.loading = false;
        state.unique.checked = false;
      }
    }
  }

  function contactPayload(contact) {
    return {
      name: String(contact && contact.name ? contact.name : "").trim(),
      role: String(contact && contact.role ? contact.role : "").trim(),
      email: String(contact && contact.email ? contact.email : "").trim(),
      phone: String(contact && contact.phone ? contact.phone : "").trim(),
      type:
        String(contact && contact.type ? contact.type : "OUTRO").trim() ||
        "OUTRO",
      notes: String(contact && contact.notes ? contact.notes : "").trim(),
    };
  }

  function resetContactsState() {
    state.contacts = createContactsState();
  }

  function selectedContactsCount() {
    return state.contacts.selectedIds.length + state.contacts.drafts.length;
  }

  function syncContactFormFields() {
    const form = state.contacts.form;
    elements.contactName.value = form.name;
    elements.contactRole.value = form.role;
    elements.contactType.value = form.type;
    elements.contactEmail.value = form.email;
    elements.contactPhone.value = form.phone;
    elements.contactNotes.value = form.notes;
  }

  function upsertAvailableContact(contact, shouldSelect) {
    const contactId = Number(contact.contact_id || contact.id || 0);
    if (!contactId) {
      return;
    }

    const normalized = {
      contact_id: contactId,
      name: String(contact.name || "").trim(),
      role: String(contact.role || "").trim(),
      email: String(contact.email || "").trim(),
      phone: String(contact.phone || "").trim(),
      type: String(contact.type || "OUTRO").trim() || "OUTRO",
      notes: String(contact.notes || "").trim(),
      obra_selected: Boolean(contact.obra_selected || shouldSelect),
    };

    const existingIndex = state.contacts.available.findIndex(
      (item) => Number(item.contact_id) === contactId,
    );
    if (existingIndex >= 0) {
      state.contacts.available.splice(existingIndex, 1, normalized);
    } else {
      state.contacts.available.unshift(normalized);
    }

    if (shouldSelect) {
      state.contacts.selectedIds = Array.from(
        new Set(state.contacts.selectedIds.concat([contactId])),
      );
    }
  }

  async function fetchClientContacts() {
    if (state.clientId === "" || state.clientId === "0") {
      renderContacts();
      renderSummary();
      return;
    }

    const requestedClientId = state.clientId;
    state.contacts.loading = true;
    renderContacts();

    try {
      const response = await fetch(
        "getClienteContacts.php?cliente_id=" +
          encodeURIComponent(requestedClientId),
        {
          credentials: "same-origin",
        },
      );
      const data = await response.json().catch(() => null);

      if (!response.ok || !data || !data.success) {
        throw new Error(
          data && data.message
            ? data.message
            : "Erro ao carregar contatos do cliente.",
        );
      }

      if (state.clientId !== requestedClientId) {
        return;
      }

      state.contacts.available = Array.isArray(data.contacts)
        ? data.contacts
        : [];
      state.contacts.selectedIds = state.contacts.available
        .filter((contact) => Boolean(contact.obra_selected))
        .map((contact) => Number(contact.contact_id))
        .filter((contactId) => contactId > 0);
    } catch (error) {
      console.error(error);
      if (state.clientId === requestedClientId) {
        notify(
          error.message || "Erro ao carregar contatos do cliente.",
          "error",
        );
      }
    } finally {
      if (state.clientId === requestedClientId) {
        state.contacts.loading = false;
        renderContacts();
        renderSummary();
      }
    }
  }

  async function saveOrStageContact() {
    const payload = contactPayload(state.contacts.form);
    if (!payload.name) {
      notify("Informe o nome do contato.", "error");
      return;
    }

    if (state.clientId === "") {
      notify("Selecione um cliente antes de adicionar contatos.", "error");
      return;
    }

    if (state.clientId === "0") {
      state.contacts.drafts.unshift({
        draftId: String(Date.now() + Math.random()),
        ...payload,
      });
      state.contacts.form = defaultContactDraft();
      renderContacts();
      renderSummary();
      notify(
        "Contato adicionado ao onboarding e sera criado junto com o cliente.",
      );
      return;
    }

    elements.addContact.disabled = true;

    try {
      const response = await fetch("saveClienteContact.php", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          cliente_id: Number(state.clientId),
          contact: payload,
        }),
      });
      const data = await response.json().catch(() => null);

      if (!response.ok || !data || !data.success) {
        throw new Error(
          data && data.message
            ? data.message
            : "Erro ao salvar contato do cliente.",
        );
      }

      upsertAvailableContact(data.contact || {}, true);
      state.contacts.form = defaultContactDraft();
      renderContacts();
      renderSummary();
      notify("Contato salvo e disponivel para esta obra.");
    } catch (error) {
      console.error(error);
      notify(error.message || "Erro ao salvar contato do cliente.", "error");
    } finally {
      elements.addContact.disabled = false;
    }
  }

  function selectedClientLabel() {
    if (state.clientId !== "" && state.clientId !== "0") {
      const option =
        elements.clienteSelect.options[elements.clienteSelect.selectedIndex];
      const fullName =
        option && option.dataset.nomeCompleto
          ? option.dataset.nomeCompleto.trim()
          : "";
      return (
        fullName || (option ? option.textContent.trim() : "Cliente existente")
      );
    }
    if (state.clientId === "0") {
      return state.clientFullName || state.clientCode || "Novo cliente";
    }
    return "Cliente não selecionado";
  }

  function packageDeadlineLabel(packageItem) {
    const suffix = packageItem.deadline_calendar_days ? "corridos" : "uteis";
    return `${packageItem.deadline_days || 0}d ${suffix}`;
  }

  function selectedPackages() {
    const packages = [];
    if (state.packages.still.enabled) {
      packages.push(
        `STL ${state.packages.still.quantity || 0}img / ${packageDeadlineLabel(state.packages.still)}`,
      );
    }
    if (state.packages.animation.enabled) {
      packages.push(
        `ANI ${state.packages.animation.seconds || 0}s / ${packageDeadlineLabel(state.packages.animation)}`,
      );
    }
    if (state.packages.film.enabled) {
      packages.push(
        `FLM ${state.packages.film.duration || "0s"} / ${packageDeadlineLabel(state.packages.film)}`,
      );
    }
    return packages;
  }

  function computedChecklist() {
    const packageDefined =
      state.packages.still.enabled ||
      state.packages.animation.enabled ||
      state.packages.film.enabled;
    const slaDefined =
      (!state.packages.still.enabled ||
        (state.packages.still.quantity &&
          state.packages.still.deadline_days)) &&
      (!state.packages.animation.enabled ||
        (state.packages.animation.seconds &&
          state.packages.animation.deadline_days)) &&
      (!state.packages.film.enabled ||
        (state.packages.film.duration && state.packages.film.deadline_days)) &&
      packageDefined;
    const imagesImported = state.images.entries.length > 0;

    return [
      { key: "grupo_cliente", label: "Grupo cliente criado", done: false },
      { key: "grupo_interno", label: "Grupo interno criado", done: false },
      {
        key: "imagens_importadas",
        label: "Imagens Still importadas (quando aplicável)",
        done: !state.packages.still.enabled || imagesImported,
      },
      { key: "sla_definido", label: "SLA definido", done: slaDefined },
      {
        key: "pacotes_definidos",
        label: "Pacotes definidos",
        done: packageDefined,
      },
    ];
  }

  function updateClientMode() {
    const isNewClient = state.clientId === "0";
    const hasClient = state.clientId !== "";
    elements.clienteSigla.disabled = !isNewClient;
    elements.clienteNomeCompleto.disabled = !hasClient;
    if (!isNewClient) {
      elements.clienteSigla.setAttribute("readonly", "readonly");
    } else {
      elements.clienteSigla.removeAttribute("readonly");
    }
    elements.clienteNomeCompleto.removeAttribute("readonly");
  }

  function updatePackageCardVisual(packageKey) {
    const card = packageCardMap[packageKey];
    if (!card) return;
    card.classList.toggle("is-enabled", !!state.packages[packageKey].enabled);
  }

  function updateStepUI() {
    elements.stepButtons.forEach((button) => {
      button.hidden =
        state.mode === "extras" && Number(button.dataset.step) !== 3;
      button.classList.toggle(
        "is-active",
        Number(button.dataset.step) === state.step,
      );
    });
    elements.panels.forEach((panel) => {
      panel.classList.toggle(
        "is-active",
        Number(panel.dataset.stepPanel) === state.step,
      );
    });

    elements.extraProjectField.hidden = state.mode !== "extras";
    renderExtraPackageFields();
    renderStillImagesSection();

    elements.prevStep.style.visibility =
      state.step === 1 || state.mode === "extras" ? "hidden" : "visible";
    elements.nextStep.style.display =
      state.mode === "extras" ? "none" : "block";
    elements.nextStep.style.visibility =
      state.step === 4 ? "hidden" : "visible";
    elements.submit.style.display =
      state.step === 4 || state.mode === "extras" ? "block" : "none";
    elements.submit.textContent =
      state.mode === "extras" ? "Adicionar extras" : "Criar projeto";
  }

  function selectedExtraPackage() {
    return state.extraPackages.find((item) => String(item.idobra_pacote) === String(state.extraPackageId)) || null;
  }

  function renderStillImagesSection() {
    const show = state.mode === "extras"
      ? selectedExtraPackage()?.tipo === "STILL"
      : state.mode !== null && state.packages.still.enabled;
    elements.stillImagesTitle.hidden = !show;
    elements.stillItemsHeader.hidden = !show;
    elements.stillImageImport.hidden = !show;
    elements.stillImageManual.hidden = !show;
    elements.stillImageCommercial.hidden = !show;
  }

  function renderExtraPackageFields() {
    const selected = selectedExtraPackage();
    const type = selected ? selected.tipo : "";
    const isAnimation = type === "ANIMACAO";
    const isFilm = type === "FILME";
    elements.extraPackageFields.hidden = state.mode !== "extras" || !selected || type === "STILL";
    elements.extraPackageQuantityField.hidden = !isAnimation;
    elements.extraPackageDurationField.hidden = !isFilm;
    elements.extraPackageRevenueField.hidden = !(isAnimation || isFilm);
    if (state.mode === "extras" && state.extraProjectId && state.extraPackages.length === 0) {
      elements.extraPackageDetails.textContent = "Este projeto não possui pacotes cadastrados.";
    } else if (selected) {
      const detail = selected.tipo === "STILL"
        ? `Pacote Still · ${selected.quantidade || 0} imagem(ns). O valor será informado por imagem.`
        : `${selected.label} · extras serão registrados como novo pacote associado.`;
      elements.extraPackageDetails.textContent = detail;
    }
  }

  async function loadExtraPackages(obraId) {
    state.extraPackages = [];
    state.extraPackageId = "";
    elements.extraPackage.innerHTML = '<option value="">Carregando pacotes...</option>';
    elements.extraPackage.disabled = true;
    renderExtraPackageFields();
    if (!obraId) {
      elements.extraPackage.innerHTML = '<option value="">Selecione primeiro o projeto</option>';
      return;
    }
    try {
      const response = await fetch(`listarPacotesObra.php?obra_id=${encodeURIComponent(obraId)}`, { credentials: "same-origin" });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || "Não foi possível carregar os pacotes.");
      state.extraPackages = Array.isArray(data.packages) ? data.packages : [];
      elements.extraPackage.innerHTML = '<option value="">Selecione um pacote</option>' + state.extraPackages.map((item) => {
        const details = item.tipo === "STILL" ? `${item.quantidade || 0} imagem(ns)` : `${item.segundos || 0}s`;
        return `<option value="${Number(item.idobra_pacote)}">${escapeHtml(item.label)} · ${escapeHtml(details)} · #${Number(item.idobra_pacote)}</option>`;
      }).join("");
      elements.extraPackage.disabled = state.extraPackages.length === 0;
      renderExtraPackageFields();
    } catch (error) {
      elements.extraPackage.innerHTML = '<option value="">Falha ao carregar pacotes</option>';
      elements.extraPackageDetails.textContent = error.message;
    }
  }

  function renderContacts() {
    const selectedCount = selectedContactsCount();
    const usingExistingClient = state.clientId !== "" && state.clientId !== "0";

    elements.contactsCounter.textContent = `${selectedCount} selecionado(s)`;
    elements.contactModeNote.textContent = usingExistingClient
      ? "Salvar novo contato cria um registro permanente em contato_cliente e deixa o contato pronto para selecao nesta obra."
      : "Cliente novo: os contatos adicionados aqui ficam em rascunho e serao criados automaticamente ao finalizar o onboarding.";
    elements.addContact.textContent = usingExistingClient
      ? "Salvar novo contato"
      : "Adicionar ao onboarding";

    if (state.clientId === "") {
      elements.contactsState.textContent =
        "Selecione um cliente para carregar a base de contatos.";
      elements.contactsList.innerHTML =
        '<div class="onb-contact-empty">Nenhum cliente selecionado.</div>';
    } else if (!usingExistingClient) {
      elements.contactsState.textContent =
        "Cliente novo: ainda nao existe base cadastrada. Use o formulario ao lado para montar os contatos desta obra.";
      elements.contactsList.innerHTML =
        '<div class="onb-contact-empty">Nenhum contato permanente disponivel antes da criacao do cliente.</div>';
    } else if (state.contacts.loading) {
      elements.contactsState.textContent =
        "Carregando contatos permanentes do cliente...";
      elements.contactsList.innerHTML =
        '<div class="onb-contact-empty">Buscando contatos do cliente selecionado.</div>';
    } else if (!state.contacts.available.length) {
      elements.contactsState.textContent =
        "Nenhum contato ativo encontrado para este cliente. Cadastre um novo contato ao lado.";
      elements.contactsList.innerHTML =
        '<div class="onb-contact-empty">Sem contatos ativos na base do cliente.</div>';
    } else {
      elements.contactsState.textContent =
        "Selecione quem participa operacionalmente desta obra.";
      elements.contactsList.innerHTML = state.contacts.available
        .map((contact) => {
          const contactId = Number(contact.contact_id || 0);
          const isSelected = state.contacts.selectedIds.includes(contactId);
          const meta = [contact.email, contact.phone].filter(Boolean);
          return `
            <label class="onb-contact-option ${isSelected ? "is-selected" : ""}">
                <span class="onb-contact-select">
                    <input type="checkbox" data-contact-select="1" data-contact-id="${contactId}" ${isSelected ? "checked" : ""}>
                </span>
                <div class="onb-contact-option-main">
                    <div class="onb-contact-option-top">
                        <strong>${escapeHtml(contact.name || "Contato sem nome")}</strong>
                        <div class="onb-contact-option-tags">
                            <span class="onb-contact-pill">${escapeHtml(contactTypeLabel(contact.type || "OUTRO"))}</span>
                            ${contact.role ? `<span class="onb-contact-pill is-muted">${escapeHtml(contact.role)}</span>` : ""}
                        </div>
                    </div>
                    <div class="onb-contact-option-meta">
                        ${meta.length ? meta.map((item) => `<span>${escapeHtml(item)}</span>`).join("") : "<span>Sem e-mail ou telefone cadastrados.</span>"}
                    </div>
                    ${contact.notes ? `<p class="onb-contact-option-note">${escapeHtml(contact.notes)}</p>` : ""}
                </div>
            </label>`;
        })
        .join("");
    }

    elements.draftContactsList.innerHTML = state.contacts.drafts.length
      ? state.contacts.drafts
          .map(
            (contact, index) => `
            <div class="onb-contact-draft" data-draft-contact="${contact.draftId}">
                <div class="onb-contact-draft-main">
                    <div class="onb-contact-option-top">
                        <strong>${escapeHtml(contact.name)}</strong>
                        <div class="onb-contact-option-tags">
                            <span class="onb-contact-pill is-accent">Novo</span>
                            <span class="onb-contact-pill">${escapeHtml(contactTypeLabel(contact.type || "OUTRO"))}</span>
                            ${contact.role ? `<span class="onb-contact-pill is-muted">${escapeHtml(contact.role)}</span>` : ""}
                        </div>
                    </div>
                    <div class="onb-contact-option-meta">
                        ${
                          contact.email || contact.phone
                            ? [contact.email, contact.phone]
                                .filter(Boolean)
                                .map(
                                  (item) => `<span>${escapeHtml(item)}</span>`,
                                )
                                .join("")
                            : "<span>Sem e-mail ou telefone cadastrados.</span>"
                        }
                    </div>
                    ${contact.notes ? `<p class="onb-contact-option-note">${escapeHtml(contact.notes)}</p>` : ""}
                </div>
                <button type="button" class="onb-contact-remove" data-remove-draft="${index}" title="Remover contato em rascunho">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>`,
          )
          .join("")
      : '<div class="onb-contact-empty">Nenhum novo contato adicionado ao onboarding.</div>';

    syncContactFormFields();
  }

  function renderImageState() {
    elements.importedFileName.textContent =
      state.images.file_name || "Nenhum arquivo importado";
    animateCount(elements.totalImages, state.images.entries.length);
    animateCount(elements.namedImages, state.images.entries.length);
    animateCount(elements.duplicateImages, state.images.duplicates.length);
    animateCount(elements.errorImages, state.images.errors.length);
    elements.previewCaption.textContent = state.images.entries.length
      ? `${state.images.entries.length} imagem(ns) · valor bruto e imposto (%) por imagem`
      : "Informe o valor bruto e o imposto (%) de cada imagem.";

    const totals = state.images.entries.reduce(
      (sum, name) => {
        const values = state.images.values[name] || {};
        sum.gross += values.valor === "" ? 0 : Number(values.valor) || 0;
        sum.tax += values.valor === "" || values.imposto === ""
          ? 0
          : ((Number(values.valor) || 0) * (Number(values.imposto) || 0)) / 100;
        return sum;
      },
      { gross: 0, tax: 0 },
    );
    elements.previewTotal.textContent = `${formatMoney(totals.gross)} bruto · ${formatMoney(totals.tax)} imposto`;
    elements.selectedImageCount.textContent = String(
      state.images.selected.length,
    );
    elements.applyContract.disabled = state.images.selected.length === 0;
    elements.selectAllImages.checked =
      state.images.entries.length > 0 &&
      state.images.selected.length === state.images.entries.length;

    elements.previewList.innerHTML = state.images.entries.length
      ? state.images.entries
          .map((name, index) => {
            const values = state.images.values[name] || {};
            const selected = state.images.selected.includes(name);
            const contract = String(values.numero_contrato || "").trim();
            return `
              <li class="onb-image-row ${selected ? "is-selected" : ""}" data-image-row="${index}">
                <input class="onb-image-select" type="checkbox" data-image-select="${index}" aria-label="Selecionar ${escapeHtml(name)}" ${selected ? "checked" : ""}>
                <span class="onb-image-name">${escapeHtml(name)}</span>
                <label class="onb-image-price-label">
                  <span>Valor bruto (R$)</span>
                  <input type="number" min="0" step="0.01" inputmode="decimal" data-image-price="${index}" aria-label="Valor bruto para ${escapeHtml(name)}" value="${escapeHtml(values.valor || "")}" placeholder="0,00">
                </label>
                <label class="onb-image-price-label onb-image-tax-label">
                  <span>Imposto (%)</span>
                  <input type="number" min="0" max="100" step="0.01" inputmode="decimal" data-image-tax="${index}" aria-label="Imposto percentual para ${escapeHtml(name)}" value="${escapeHtml(values.imposto || "")}" placeholder="0,00">
                </label>
                <span class="onb-image-contract" title="${escapeHtml(contract || "Contrato não atribuído")}">${escapeHtml(contract || "Contrato não atribuído")}</span>
              </li>`;
          })
          .join("")
      : '<li class="onb-image-empty">Nenhuma imagem carregada ainda.</li>';
    updateReplicationButtons();
  }

  function renderOtherItems() {
    elements.otherItemsList.innerHTML = state.otherItems.map((item, index) => `<li class="onb-image-row"><span class="onb-image-name">${escapeHtml(item.categoria)} · ${escapeHtml(item.descricao)} (${escapeHtml(item.quantidade)} ${escapeHtml(item.unidade)})</span><span>Valor externo ${formatMoney(Number(item.receita) || 0)}</span><button type="button" class="onb-ghost-btn" data-remove-other-item="${index}">Remover</button></li>`).join("") || '<li class="onb-image-empty">Nenhum material ou serviço adicional.</li>';
  }

  function renderSummary() {
    const packages = selectedPackages();
    const checklist =
      state.mode === "extras"
        ? [
            {
              label: selectedExtraPackage()?.tipo === "STILL"
                ? "Valores comerciais por imagem"
                : "Valor externo do pacote ou material",
              done: selectedExtraPackage()?.tipo === "STILL"
                ? state.images.entries.length > 0 && state.images.entries.every((name) =>
                    String(state.images.values[name]?.valor ?? "").trim() !== "" &&
                    String(state.images.values[name]?.imposto ?? "").trim() !== "")
                : Boolean(selectedExtraPackage()) && (
                    ["ANIMACAO", "FILME"].includes(selectedExtraPackage()?.tipo) &&
                    elements.extraPackageRevenue.value.trim() !== "" &&
                    Number(elements.extraPackageRevenue.value) >= 0 &&
                    state.otherItems.every((item) => String(item.receita ?? "").trim() !== "")
                  ),
            },
          ]
        : computedChecklist();
    const contactsCount = selectedContactsCount();
    const showImageCommercial = state.mode === "extras"
      ? selectedExtraPackage()?.tipo === "STILL"
      : state.packages.still.enabled;
    const commercialTotals = state.images.entries.reduce(
      (sum, name) => {
        const values = state.images.values[name] || {};
        sum.gross += values.valor === "" ? 0 : Number(values.valor) || 0;
        sum.tax += values.valor === "" || values.imposto === ""
          ? 0
          : ((Number(values.valor) || 0) * (Number(values.imposto) || 0)) / 100;
        return sum;
      },
      { gross: 0, tax: 0 },
    );
    const photoServiceValue = elements.photoServiceValue.value;

    if (state.mode === "extras") {
      const option =
        elements.extraProject.options[elements.extraProject.selectedIndex];
      const selectedPackage = selectedExtraPackage();
      const packageDescription = selectedPackage
        ? `${selectedPackage.label} · pacote #${selectedPackage.idobra_pacote}`
        : "Selecione um pacote";
      elements.summaryList.innerHTML = `
        <div class="onb-summary-item"><span>Projeto</span><strong>${escapeHtml(option && option.value ? option.textContent.trim() : "Selecione um projeto")}</strong></div>
        <div class="onb-summary-item"><span>Pacote</span><strong>${escapeHtml(packageDescription)}</strong></div>
        ${showImageCommercial ? `<div class="onb-summary-item"><span>Imagens extras</span><strong>${state.images.entries.length} imagem(ns)</strong></div>` : ""}
        <div class="onb-summary-item"><span>Materiais e serviços</span><strong>${state.otherItems.length} item(ns)</strong></div>
        <div class="onb-summary-item"><span>Valor do pacote extra</span><strong>${elements.extraPackageRevenue.value ? formatMoney(elements.extraPackageRevenue.value) : "Por imagem ou não aplicável"}</strong></div>
        ${showImageCommercial ? `<div class="onb-summary-item"><span>Valor bruto das imagens</span><strong>${formatMoney(commercialTotals.gross)}</strong></div><div class="onb-summary-item"><span>Impostos das imagens</span><strong>${formatMoney(commercialTotals.tax)}</strong></div>` : ""}
        <div class="onb-summary-item"><span>Serviço fotográfico</span><strong>${photoServiceValue ? formatMoney(photoServiceValue) : "Não informado"}</strong></div>`;
      elements.checklistList.innerHTML = checklist
        .map(
          (item) =>
            `<div class="onb-checklist-item"><strong>${escapeHtml(item.label)}</strong><span class="onb-checklist-status ${item.done ? "is-done" : "is-pending"}">${item.done ? "Pronto" : "Pendente"}</span></div>`,
        )
        .join("");
      return;
    }

    elements.summaryList.innerHTML = `
            <div class="onb-summary-item"><span>Cliente</span><strong>${escapeHtml(selectedClientLabel())}</strong></div>
            <div class="onb-summary-item"><span>Projeto interno</span><strong>${escapeHtml(state.projectInternal || "A definir")}</strong></div>
            <div class="onb-summary-item"><span>Projeto comercial</span><strong>${escapeHtml(state.projectCommercial || "A definir")}</strong></div>
            <div class="onb-summary-item"><span>Nomenclatura</span><strong>${escapeHtml(state.code || "A definir")}</strong></div>
              <div class="onb-summary-item"><span>Pacotes</span><strong>${packages.length ? escapeHtml(packages.join(" • ")) : "Nenhum pacote selecionado"}</strong></div>
              <div class="onb-summary-item"><span>Importação</span><strong>${state.images.entries.length} img / ${state.images.duplicates.length} dup / ${state.images.errors.length} err</strong></div>
            ${showImageCommercial ? `<div class="onb-summary-item"><span>Valor bruto das imagens</span><strong>${formatMoney(commercialTotals.gross)}</strong></div><div class="onb-summary-item"><span>Impostos das imagens</span><strong>${formatMoney(commercialTotals.tax)}</strong></div>` : ""}
            <div class="onb-summary-item"><span>Serviço fotográfico</span><strong>${photoServiceValue ? formatMoney(photoServiceValue) : "Não informado"}</strong></div>
            <div class="onb-summary-item"><span>Contatos</span><strong>${contactsCount} contato(s) selecionado(s)</strong></div>
            <div class="onb-summary-item"><span>Status inicial</span><strong>ONBOARDING</strong></div>`;

    elements.checklistList.innerHTML = checklist
      .map(
        (item) => `
            <div class="onb-checklist-item">
                <strong>${escapeHtml(item.label)}</strong>
                <span class="onb-checklist-status ${item.done ? "is-done" : "is-pending"}">${item.done ? "Concluído" : "Pendente"}</span>
            </div>`,
      )
      .join("");
  }

  function renderAll() {
    syncNomenclature();
    updateClientMode();
    updatePackageCardVisual("still");
    updatePackageCardVisual("animation");
    updatePackageCardVisual("film");
    updateStepUI();
    renderUniqueBadges();
    renderContacts();
    renderImageState();
    renderOtherItems();
    renderSummary();
  }

  function resetForm() {
    state = createInitialState();
    elements.clienteSelect.value = "";
    elements.clienteNomeCompleto.value = "";
    elements.clienteNomeCompleto.disabled = true;
    elements.clienteSigla.value = "";
    elements.clienteSigla.disabled = true;
    elements.projetoInterno.value = "";
    elements.projetoComercial.value = "";
    elements.codigoInterno.value = "";
    elements.observacoes.value = "";
    elements.packageStill.checked = false;
    elements.stillQtd.value = "";
    elements.stillPrazo.value = "";
    elements.stillDiasCorridos.checked = false;
    elements.packageAnimation.checked = false;
    elements.animationSeconds.value = "";
    elements.animationPrazo.value = "";
    elements.animationDiasCorridos.checked = false;
    elements.packageFilm.checked = false;
    elements.filmDuration.value = "";
    elements.filmPrazo.value = "";
    elements.filmDiasCorridos.checked = false;
    elements.animationRevenue.value = "";
    elements.filmRevenue.value = "";
    elements.photoServiceValue.value = "";
    elements.otherCategory.value = "";
    elements.otherDescription.value = "";
    elements.otherQuantity.value = "1";
    elements.otherUnit.value = "";
    elements.otherRevenue.value = "";
    elements.imageFile.value = "";
    elements.manualImages.value = "";
    elements.extraProject.value = "";
    elements.extraPackage.innerHTML = '<option value="">Selecione primeiro o projeto</option>';
    elements.extraPackage.disabled = true;
    elements.extraPackageQuantity.value = "";
    elements.extraPackageDuration.value = "";
    elements.extraPackageRevenue.value = "";
    elements.photoServiceValue.value = "";
    elements.contractBatchValue.value = "";
    elements.selectAllImages.checked = false;
    modal.classList.remove("is-extras-mode");
    elements.modalTitle.textContent = "Gerenciar Projeto";
    elements.modalDescription.textContent =
      "Inicie um projeto ou adicione imagens extras a uma obra existente.";
    clearUniqueState();
    renderAll();
  }

  function open() {
    resetForm();
    modal.classList.add("is-mode-picker");
    modal.style.display = "flex";
    document.body.classList.add("onb-modal-open");
    if (window.gsap && allowMotion) {
      window.gsap.from(modal.querySelectorAll(".onb-mode-card"), {
        autoAlpha: 0,
        y: 12,
        duration: 0.34,
        stagger: 0.08,
        ease: "power2.out",
        clearProps: "opacity,visibility,transform",
      });
    }
  }

  function chooseMode(mode) {
    state.mode = mode;
    state.step = mode === "extras" ? 3 : 1;
    modal.classList.remove("is-mode-picker");
    modal.classList.toggle("is-extras-mode", mode === "extras");
    elements.modalTitle.textContent =
      mode === "extras" ? "Adicionar extras ao projeto" : "Novo projeto";
    elements.modalDescription.textContent =
      mode === "extras"
        ? "Selecione o projeto e o pacote. Still é cobrado por imagem; animações e filmes recebem valor externo por pacote extra."
        : "Cadastre o projeto e registre os valores comerciais junto da lista de imagens.";
    renderAll();
    animateStepCards();
  }

  function close() {
    modal.style.display = "none";
    document.body.classList.remove("onb-modal-open");
  }

  function normalizeImportedName(name) {
    return String(name || "")
      .trim()
      .replace(/\s+/g, " ");
  }

  function ingestImageEntries(entries, options) {
    const nextEntries = state.images.entries.slice();
    const seen = new Set(nextEntries.map((item) => item.toLowerCase()));
    const duplicates = state.images.duplicates.slice();
    const errors = state.images.errors.slice();

    entries.forEach((entry, index) => {
      const normalized = normalizeImportedName(entry);
      if (!normalized) {
        errors.push(`Linha ${index + 1}: imagem vazia.`);
        return;
      }
      const key = normalized.toLowerCase();
      if (seen.has(key)) {
        duplicates.push(normalized);
        return;
      }
      seen.add(key);
      nextEntries.push(normalized);
      state.images.values[normalized] = state.images.values[normalized] || {
        valor: "",
        imposto: "",
        numero_contrato: "",
      };
    });

    state.images.entries = nextEntries;
    state.images.duplicates = duplicates;
    state.images.errors = errors;
    state.images.file_name = options.fileName || state.images.file_name;
    state.images.source = options.source || state.images.source;
    renderAll();
  }

  function parseTxt(text) {
    return text
      .split(/\r?\n/)
      .map((line) => line.trim())
      .filter((line) => line && line.charAt(0) !== "#");
  }

  function parseCsvLike(text) {
    return text
      .split(/\r?\n/)
      .map((line) => line.trim())
      .filter(Boolean)
      .map((line, index) => {
        const cells = line
          .split(/[,;\t]/)
          .map((cell) => cell.trim())
          .filter(Boolean);
        if (!cells.length) {
          return "";
        }
        if (index === 0 && /imagem|nome/i.test(cells.join(" "))) {
          return "";
        }
        return cells[0];
      })
      .filter(Boolean);
  }

  function parseSheetRows(rows) {
    return rows
      .map((row, index) => {
        const cells = Array.isArray(row)
          ? row
              .map((cell) => String(cell == null ? "" : cell).trim())
              .filter(Boolean)
          : [String(row == null ? "" : row).trim()].filter(Boolean);
        if (!cells.length) {
          return "";
        }
        if (index === 0 && /imagem|nome/i.test(cells.join(" "))) {
          return "";
        }
        return cells[0];
      })
      .filter(Boolean);
  }

  async function importFile(file) {
    const extension = (file.name.split(".").pop() || "").toLowerCase();
    let entries = [];
    if (extension === "txt") {
      entries = parseTxt(await file.text());
    } else if (extension === "csv") {
      entries = parseCsvLike(await file.text());
    } else if (extension === "xlsx" || extension === "xls") {
      if (!window.XLSX) {
        throw new Error("Biblioteca XLSX indisponível no navegador.");
      }
      const workbook = XLSX.read(await file.arrayBuffer(), { type: "array" });
      const firstSheet = workbook.SheetNames[0];
      const rows = XLSX.utils.sheet_to_json(workbook.Sheets[firstSheet], {
        header: 1,
        blankrows: false,
      });
      entries = parseSheetRows(rows);
    } else {
      throw new Error("Formato não suportado. Use TXT, CSV ou XLSX.");
    }

    ingestImageEntries(entries, {
      fileName: file.name,
      source: extension.toUpperCase(),
    });
  }

  function validateStep(step) {
    if (step === 3) {
      if (state.mode === "extras" && !state.extraProjectId) {
        notify("Selecione o projeto que receberá as imagens extras.", "error");
        return false;
      }
      const extraPackage = selectedExtraPackage();
      if (state.mode === "extras" && !extraPackage) {
        notify("Selecione um pacote cadastrado para o projeto.", "error");
        return false;
      }
      if (state.mode === "extras" && extraPackage && extraPackage.tipo === "ANIMACAO") {
        const seconds = Number(elements.extraPackageQuantity.value);
        const revenue = String(elements.extraPackageRevenue.value || "").trim();
        if (!Number.isInteger(seconds) || seconds <= 0) {
          notify("Informe os segundos de animação que serão adicionados.", "error");
          return false;
        }
        if (!revenue || !Number.isFinite(Number(revenue)) || Number(revenue) < 0) {
          notify("Informe o valor externo cobrado por este extra.", "error");
          return false;
        }
      }
      if (state.mode === "extras" && extraPackage && extraPackage.tipo === "FILME") {
        const duration = String(elements.extraPackageDuration.value || "").trim();
        const revenue = String(elements.extraPackageRevenue.value || "").trim();
        if (!duration) {
          notify("Informe a duração do filme extra.", "error");
          return false;
        }
        if (!revenue || !Number.isFinite(Number(revenue)) || Number(revenue) < 0) {
          notify("Informe o valor externo cobrado por este extra.", "error");
          return false;
        }
      }
      if (state.mode === "extras" && extraPackage && extraPackage.tipo !== "STILL" && state.images.entries.length) {
        notify("Imagens extras só podem ser inseridas pelo pacote Imagens Still.", "error");
        return false;
      }
      if (state.mode !== "extras" && !state.packages.still.enabled && state.images.entries.length) {
        notify("A lista e os valores por imagem só podem ser preenchidos quando o pacote Imagens Still estiver selecionado.", "error");
        return false;
      }
      if (state.mode === "extras" && state.images.entries.length === 0 && state.otherItems.length === 0) {
        if (!extraPackage || extraPackage.tipo === "STILL") {
          notify("Adicione ao menos uma imagem ou material extra.", "error");
          return false;
        }
      }
      if (state.mode === "extras" && extraPackage && extraPackage.tipo === "STILL" && state.images.entries.length === 0) {
        notify("Adicione ao menos uma imagem para o pacote Still.", "error");
        return false;
      }
      const missingPrice = state.images.entries.find((name) => {
        const raw = String(state.images.values[name]?.valor ?? "").trim();
        return raw === "" || !Number.isFinite(Number(raw)) || Number(raw) < 0;
      });
      if (missingPrice) {
        notify(
          `Informe um valor bruto válido para “${missingPrice}”.`,
          "error",
        );
        return false;
      }
      const invalidTax = state.images.entries.find((name) => {
        const raw = String(
          state.images.values[name]?.imposto ?? "",
        ).trim();
        const gross = Number(state.images.values[name]?.valor);
        return (
          raw === "" ||
          !Number.isFinite(Number(raw)) ||
          Number(raw) < 0 ||
          Number(raw) > 100 ||
          !Number.isFinite(gross) ||
          gross < 0
        );
      });
      if (invalidTax) {
        notify(
          `Informe um percentual de imposto entre 0 e 100 para “${invalidTax}”.`,
          "error",
        );
        return false;
      }
      const photoValue = String(elements.photoServiceValue.value || "").trim();
      if (
        photoValue &&
        (!Number.isFinite(Number(photoValue)) || Number(photoValue) < 0)
      ) {
        notify("Informe um valor válido para o serviço fotográfico.", "error");
        return false;
      }
      return true;
    }

    if (step === 1) {
      const usingExistingClient =
        state.clientId !== "" && state.clientId !== "0";
      const usingNewClient = state.clientId === "0";
      if (!usingExistingClient && !usingNewClient) {
        notify("Selecione um cliente ou escolha Novo Cliente.", "error");
        return false;
      }
      if (!state.clientFullName) {
        notify("Informe o nome completo do cliente.", "error");
        return false;
      }
      if (!state.clientCode) {
        notify("Informe a sigla do cliente.", "error");
        return false;
      }
      if (!state.projectInternal || !state.projectCommercial || !state.code) {
        notify(
          "Preencha nome interno do projeto, nome comercial e nomenclatura.",
          "error",
        );
        return false;
      }
      if (state.unique.loading) {
        notify("Aguarde a validacao das siglas.", "error");
        return false;
      }
      if (hasUniqueConflicts()) {
        notify("Altere as siglas marcadas antes de continuar.", "error");
        return false;
      }
    }

    if (step === 2) {
      const checklist = computedChecklist();
      const packagesDefined = checklist.find(
        (item) => item.key === "pacotes_definidos",
      );
      const slaDefined = checklist.find((item) => item.key === "sla_definido");
      if (!packagesDefined || !packagesDefined.done) {
        notify("Selecione ao menos um pacote contratado.", "error");
        return false;
      }
      if (!slaDefined || !slaDefined.done) {
        notify(
          "Preencha as informações e os prazos dos pacotes selecionados.",
          "error",
        );
        return false;
      }
      for (const [enabled, revenue, label] of [
        [state.packages.animation.enabled, elements.animationRevenue.value, "animação"],
        [state.packages.film.enabled, elements.filmRevenue.value, "filme"],
      ]) {
        if (enabled && (!revenue.trim() || !Number.isFinite(Number(revenue)) || Number(revenue) < 0)) {
          notify(`Informe o valor externo cobrado do cliente para o pacote de ${label}.`, "error"); return false;
        }
      }
    }

    return true;
  }

  function confirmNoContacts() {
    if (selectedContactsCount() > 0) {
      return Promise.resolve(true);
    }

    if (window.Swal && typeof window.Swal.fire === "function") {
      return window.Swal.fire({
        title: "Concluir sem contatos?",
        html:
          "Nenhum contato foi cadastrado para este projeto.<br><br>" +
          "Deseja realmente concluir o cadastro sem adicionar contatos?<br><br>" +
          "Você poderá cadastrá-los posteriormente.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Criar Projeto",
        cancelButtonText: "Voltar",
        reverseButtons: true,
      }).then((result) => Boolean(result.isConfirmed));
    }

    return new Promise((resolve) => {
      const overlay = document.createElement("div");
      overlay.className = "onb-confirm-overlay";
      overlay.innerHTML = `
        <div class="onb-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="onbConfirmNoContactsTitle">
          <h3 id="onbConfirmNoContactsTitle">Concluir sem contatos?</h3>
          <p>Nenhum contato foi cadastrado para este projeto.</p>
          <p>Deseja realmente concluir o cadastro sem adicionar contatos?</p>
          <p>Você poderá cadastrá-los posteriormente.</p>
          <div class="onb-confirm-actions">
            <button type="button" class="onb-ghost-btn" data-onb-confirm="back">Voltar</button>
            <button type="button" class="onb-primary-btn" data-onb-confirm="create">Criar Projeto</button>
          </div>
        </div>`;

      function closeConfirm(value) {
        overlay.remove();
        resolve(value);
      }

      overlay.addEventListener("click", (event) => {
        if (event.target === overlay) {
          closeConfirm(false);
          return;
        }
        const action = event.target.closest("[data-onb-confirm]");
        if (!action) return;
        closeConfirm(action.getAttribute("data-onb-confirm") === "create");
      });

      document.body.appendChild(overlay);
    });
  }

  function goToStep(nextStep) {
    const targetStep = Math.max(1, Math.min(4, nextStep));
    if (targetStep > state.step && !validateStep(state.step)) {
      return;
    }
    state.step = targetStep;
    renderAll();
    animateStepCards();
  }

  function buildPayload() {
    return {
      mode: state.mode,
      obra_id: state.mode === "extras" ? Number(state.extraProjectId) : null,
      extra_package: state.mode === "extras" && selectedExtraPackage() ? {
        pacote_id: Number(state.extraPackageId),
        tipo: selectedExtraPackage().tipo,
        segundos: elements.extraPackageQuantity.value.trim(),
        duracao: elements.extraPackageDuration.value.trim(),
        receita: elements.extraPackageRevenue.value.trim(),
      } : null,
      cliente_id:
        state.clientId !== "" && state.clientId !== "0"
          ? Number(state.clientId)
          : null,
      cliente: state.clientId === "0" ? state.clientCode : "",
      cliente_nome_completo: getClientFullName(),
      obra: state.projectInternal,
      obra_nome_completo: state.projectCommercial,
      nome_real: projectRealName(),
      nomenclatura: state.code,
      cliente_nome: selectedClientLabel(),
      observacoes: state.notes,
      packages: state.packages,
      images: state.images.entries.map((name) => ({
        imagem_nome: name,
        valor: state.images.values[name]?.valor ?? "",
        imposto: state.images.values[name]?.imposto ?? "",
        numero_contrato: state.images.values[name]?.numero_contrato ?? "",
        origem: state.mode === "extras" ? "EXTRA" : "ONBOARDING",
      })),
      materiais: state.otherItems.map((item) => ({ ...item, origem: state.mode === "extras" ? "EXTRA" : "ONBOARDING" })),
      package_values: {
        animation: { receita: elements.animationRevenue.value.trim() },
        film: { receita: elements.filmRevenue.value.trim() },
      },
      servico_fotografico_valor: elements.photoServiceValue.value.trim(),
      image_import: {
        file_name: state.images.file_name,
        source: state.images.source,
        total: state.images.entries.length,
        duplicates: state.images.duplicates.length,
        errors: state.images.errors.length,
      },
      selected_contact_ids: state.contacts.selectedIds.slice(),
      new_contacts: state.contacts.drafts.map((contact) => ({
        name: contact.name,
        role: contact.role,
        type: contact.type,
        email: contact.email,
        phone: contact.phone,
        notes: contact.notes,
      })),
    };
  }

  async function submitOnboarding() {
    if (state.mode === "extras") {
      if (!validateStep(3)) return;
    } else {
      if (!validateStep(1) || !validateStep(2) || !validateStep(3)) {
        return;
      }

      if (!(await confirmNoContacts())) {
        return;
      }
    }

    state.isSubmitting = true;
    elements.submit.disabled = true;

    try {
      const response = await fetch(
        state.mode === "extras"
          ? "adicionarExtrasProjeto.php"
          : "iniciarProjeto.php",
        {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(buildPayload()),
        },
      );
      const data = await response.json().catch(() => null);
      if (!response.ok || !data || !data.success) {
        throw new Error(
          data && data.message ? data.message : "Erro ao iniciar projeto.",
        );
      }

      notify(
        state.mode === "extras"
          ? "Extras e valores adicionados. Atualizando dashboard..."
          : "Projeto criado com valores comerciais. Atualizando dashboard...",
      );
      close();
      window.setTimeout(() => window.location.reload(), 900);
    } catch (error) {
      console.error(error);
      notify(error.message || "Erro ao iniciar projeto.", "error");
    } finally {
      state.isSubmitting = false;
      elements.submit.disabled = false;
    }
  }

  btnOpen.addEventListener("click", open);
  elements.modeButtons.forEach((button) => {
    button.addEventListener("click", () =>
      chooseMode(button.dataset.onboardingMode),
    );
  });
  elements.close.addEventListener("click", close);
  elements.cancel.addEventListener("click", close);
  modal.addEventListener("click", (event) => {
    if (event.target === modal) {
      close();
    }
  });

  elements.stepButtons.forEach((button) => {
    button.addEventListener("click", () =>
      goToStep(Number(button.dataset.step)),
    );
  });

  elements.prevStep.addEventListener("click", () => goToStep(state.step - 1));
  elements.nextStep.addEventListener("click", () => goToStep(state.step + 1));
  elements.submit.addEventListener("click", submitOnboarding);

  elements.addOtherItem.addEventListener("click", () => {
    const item = {
      categoria: elements.otherCategory.value.trim(),
      descricao: elements.otherDescription.value.trim(),
      quantidade: elements.otherQuantity.value.trim() || "1",
      unidade: elements.otherUnit.value.trim(),
      receita: elements.otherRevenue.value.trim(),
    };
    if (!item.categoria || !item.descricao || item.receita === "" || !Number.isFinite(Number(item.receita)) || Number(item.receita) < 0) {
      notify("Informe categoria, descrição e valor externo cobrado do cliente.", "error"); return;
    }
    if (!Number.isFinite(Number(item.quantidade)) || Number(item.quantidade) <= 0) { notify("A quantidade deve ser maior que zero.", "error"); return; }
    state.otherItems.push(item);
    elements.otherDescription.value = "";
    elements.otherRevenue.value = "";
    renderAll();
  });
  elements.otherItemsList.addEventListener("click", (event) => {
    const button = event.target.closest("[data-remove-other-item]");
    if (!button) return;
    state.otherItems.splice(Number(button.dataset.removeOtherItem), 1);
    renderAll();
  });
  for (const field of [elements.animationRevenue, elements.filmRevenue, elements.photoServiceValue, elements.otherRevenue]) field.addEventListener("input", renderSummary);

  elements.previewList.addEventListener("input", (event) => {
    const input = event.target.closest("[data-image-price], [data-image-tax]");
    if (!input) return;
    const key = input.hasAttribute("data-image-price") ? "valor" : "imposto";
    const index = Number(input.dataset.imagePrice ?? input.dataset.imageTax);
    const imageName = state.images.entries[index];
    if (!imageName) return;
    state.images.values[imageName] = state.images.values[imageName] || {};
    state.images.values[imageName][key] = input.value;
    const totals = state.images.entries.reduce(
      (sum, name) => {
        const values = state.images.values[name] || {};
        sum.gross += values.valor === "" ? 0 : Number(values.valor) || 0;
        sum.tax += values.valor === "" || values.imposto === ""
          ? 0
          : ((Number(values.valor) || 0) * (Number(values.imposto) || 0)) / 100;
        return sum;
      },
      { gross: 0, tax: 0 },
    );
    elements.previewTotal.textContent = `${formatMoney(totals.gross)} bruto · ${formatMoney(totals.tax)} imposto`;
    updateReplicationButtons();
    renderSummary();
  });

  function updateReplicationButtons() {
    const firstName = state.images.entries[0];
    const firstValues = firstName ? state.images.values[firstName] || {} : {};
    const hasOthers = state.images.entries.length > 1;
    elements.replicateGross.disabled =
      !hasOthers || String(firstValues.valor ?? "").trim() === "";
    elements.replicateTax.disabled =
      !hasOthers || String(firstValues.imposto ?? "").trim() === "";
  }

  function replicateCommercialField(field) {
    const firstName = state.images.entries[0];
    const sourceValue = firstName
      ? String(state.images.values[firstName]?.[field] ?? "").trim()
      : "";
    if (!firstName || !sourceValue || state.images.entries.length < 2) return;
    state.images.entries.slice(1).forEach((name) => {
      state.images.values[name] = state.images.values[name] || {};
      state.images.values[name][field] = sourceValue;
    });
    renderAll();
    notify(field === "valor" ? "Valor bruto replicado para as demais imagens." : "Percentual de imposto replicado para as demais imagens.");
  }

  elements.replicateGross.addEventListener("click", () =>
    replicateCommercialField("valor"),
  );
  elements.replicateTax.addEventListener("click", () =>
    replicateCommercialField("imposto"),
  );

  elements.previewList.addEventListener("change", (event) => {
    const checkbox = event.target.closest("[data-image-select]");
    if (!checkbox) return;
    const imageName =
      state.images.entries[Number(checkbox.dataset.imageSelect)];
    if (!imageName) return;
    if (checkbox.checked) {
      state.images.selected = Array.from(
        new Set(state.images.selected.concat(imageName)),
      );
    } else {
      state.images.selected = state.images.selected.filter(
        (name) => name !== imageName,
      );
    }
    renderImageState();
  });

  elements.selectAllImages.addEventListener("change", () => {
    state.images.selected = elements.selectAllImages.checked
      ? state.images.entries.slice()
      : [];
    renderImageState();
  });

  elements.applyContract.addEventListener("click", () => {
    const contract = elements.contractBatchValue.value.trim();
    if (!contract) {
      notify(
        "Digite o texto do contrato que será aplicado à seleção.",
        "error",
      );
      return;
    }
    if (!state.images.selected.length) {
      notify("Selecione as imagens que pertencem a esse contrato.", "error");
      return;
    }
    state.images.selected.forEach((imageName) => {
      state.images.values[imageName] = state.images.values[imageName] || {};
      state.images.values[imageName].numero_contrato = contract;
    });
    renderImageState();
    renderSummary();
    if (window.gsap && allowMotion) {
      window.gsap.from(
        modal.querySelectorAll(
          ".onb-image-row.is-selected .onb-image-contract",
        ),
        {
          autoAlpha: 0,
          x: -6,
          duration: 0.22,
          stagger: 0.025,
          clearProps: "opacity,visibility,transform",
        },
      );
    }
  });

  elements.extraProject.addEventListener("change", () => {
    state.extraProjectId = elements.extraProject.value || "";
    loadExtraPackages(state.extraProjectId);
    renderSummary();
  });
  elements.extraPackage.addEventListener("change", () => {
    state.extraPackageId = elements.extraPackage.value || "";
    renderExtraPackageFields();
    renderStillImagesSection();
    renderSummary();
  });
  [elements.extraPackageQuantity, elements.extraPackageDuration, elements.extraPackageRevenue].forEach((element) => element.addEventListener("input", renderSummary));
  elements.photoServiceValue.addEventListener("input", renderSummary);

  elements.clienteSelect.addEventListener("change", () => {
    state.clientId = elements.clienteSelect.value || "";
    state.clientCodeTouched = false;
    resetContactsState();
    const option =
      elements.clienteSelect.options[elements.clienteSelect.selectedIndex];

    if (state.clientId !== "" && state.clientId !== "0") {
      state.clientName = option ? option.textContent.trim() : "";
      state.clientFullName =
        option && option.dataset.nomeCompleto
          ? option.dataset.nomeCompleto.trim()
          : "";
      state.clientCode =
        onlyLetters(option ? option.dataset.sigla || "" : "") ||
        fallbackClientCode(state.clientName);
      elements.clienteNomeCompleto.value = state.clientFullName;
      renderAll();
      scheduleUniqueCheck();
      fetchClientContacts();
      return;
    }

    state.clientCode = "";
    state.clientCodeTouched = false;
    state.clientName = "";
    state.clientFullName = "";
    renderAll();
    scheduleUniqueCheck();
  });

  elements.clienteNomeCompleto.addEventListener("input", () => {
    state.clientFullName = elements.clienteNomeCompleto.value.trim();
    if (state.clientId === "0" && !state.clientCodeTouched) {
      state.clientCode = fallbackClientCode(state.clientFullName);
    }
    renderAll();
    scheduleUniqueCheck();
  });

  elements.clienteSigla.addEventListener("input", () => {
    state.clientCode = onlyLetters(elements.clienteSigla.value);
    state.clientCodeTouched = true;
    state.clientName =
      state.clientId === "0" ? state.clientCode : state.clientName;
    renderAll();
    scheduleUniqueCheck();
  });
  elements.projetoInterno.addEventListener("input", () => {
    state.projectInternal = projectCodePart(elements.projetoInterno.value);
    state.projectInternalTouched = true;
    renderAll();
    scheduleUniqueCheck();
  });
  elements.projetoComercial.addEventListener("input", () => {
    state.projectCommercial = elements.projetoComercial.value.trim();
    if (!state.projectInternalTouched) {
      state.projectInternal = inferProjectCode(state.projectCommercial);
      syncNomenclature();
    }
    renderSummary();
    scheduleUniqueCheck();
  });
  elements.observacoes.addEventListener("input", () => {
    state.notes = elements.observacoes.value.trim();
  });

  function bindPackageField(packageKey, fieldKey, element) {
    element.addEventListener("input", () => {
      state.packages[packageKey][fieldKey] = element.value.trim();
      renderSummary();
    });
  }

  function bindPackageToggle(packageKey, element) {
    element.addEventListener("change", () => {
      state.packages[packageKey].enabled = element.checked;
      renderAll();
    });
  }

  function bindPackageCalendarToggle(packageKey, element) {
    element.addEventListener("change", () => {
      state.packages[packageKey].deadline_calendar_days = element.checked;
      renderSummary();
    });
  }

  bindPackageToggle("still", elements.packageStill);
  bindPackageField("still", "quantity", elements.stillQtd);
  bindPackageField("still", "deadline_days", elements.stillPrazo);
  bindPackageCalendarToggle("still", elements.stillDiasCorridos);
  bindPackageToggle("animation", elements.packageAnimation);
  bindPackageField("animation", "seconds", elements.animationSeconds);
  bindPackageField("animation", "deadline_days", elements.animationPrazo);
  bindPackageCalendarToggle("animation", elements.animationDiasCorridos);
  bindPackageToggle("film", elements.packageFilm);
  bindPackageField("film", "duration", elements.filmDuration);
  bindPackageField("film", "deadline_days", elements.filmPrazo);
  bindPackageCalendarToggle("film", elements.filmDiasCorridos);

  elements.imageFile.addEventListener("change", async () => {
    const file = elements.imageFile.files && elements.imageFile.files[0];
    if (!file) return;
    try {
      await importFile(file);
      notify(`Arquivo ${file.name} importado para o onboarding.`);
    } catch (error) {
      console.error(error);
      notify(error.message || "Erro ao processar arquivo.", "error");
    }
  });

  ["dragenter", "dragover"].forEach((type) => {
    elements.uploadBox.addEventListener(type, (event) => {
      event.preventDefault();
      elements.uploadBox.classList.add("is-dragover");
    });
  });
  ["dragleave", "drop"].forEach((type) => {
    elements.uploadBox.addEventListener(type, (event) => {
      event.preventDefault();
      elements.uploadBox.classList.remove("is-dragover");
    });
  });
  elements.uploadBox.addEventListener("drop", async (event) => {
    const file =
      event.dataTransfer &&
      event.dataTransfer.files &&
      event.dataTransfer.files[0];
    if (!file) return;
    try {
      await importFile(file);
      notify(`Arquivo ${file.name} importado para o onboarding.`);
    } catch (error) {
      console.error(error);
      notify(error.message || "Erro ao processar arquivo.", "error");
    }
  });

  elements.addManualImages.addEventListener("click", () => {
    const lines = elements.manualImages.value
      .split(/\r?\n/)
      .map((line) => line.trim())
      .filter(Boolean);
    if (!lines.length) {
      notify("Informe ao menos uma imagem manualmente.", "error");
      return;
    }
    ingestImageEntries(lines, {
      fileName: state.images.file_name || "Lista manual",
      source: state.images.file_name ? "MIXED" : "MANUAL",
    });
    elements.manualImages.value = "";
  });

  elements.clearImages.addEventListener("click", () => {
    state.images = {
      file_name: "",
      source: "manual",
      entries: [],
      values: {},
      selected: [],
      duplicates: [],
      errors: [],
    };
    elements.imageFile.value = "";
    renderAll();
  });

  elements.addContact.addEventListener("click", saveOrStageContact);

  [
    [elements.contactName, "name"],
    [elements.contactRole, "role"],
    [elements.contactEmail, "email"],
    [elements.contactPhone, "phone"],
    [elements.contactNotes, "notes"],
  ].forEach(([element, field]) => {
    element.addEventListener("input", () => {
      state.contacts.form[field] = element.value.trim();
    });
  });

  elements.contactType.addEventListener("change", () => {
    state.contacts.form.type = elements.contactType.value || "OUTRO";
  });

  elements.contactsList.addEventListener("change", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) return;
    if (target.dataset.contactSelect !== "1") return;

    const contactId = Number(target.dataset.contactId || 0);
    if (!contactId) return;

    if (target.checked) {
      state.contacts.selectedIds = Array.from(
        new Set(state.contacts.selectedIds.concat([contactId])),
      );
    } else {
      state.contacts.selectedIds = state.contacts.selectedIds.filter(
        (value) => value !== contactId,
      );
    }

    renderContacts();
    renderSummary();
  });

  elements.draftContactsList.addEventListener("click", (event) => {
    const trigger = event.target.closest("[data-remove-draft]");
    if (!trigger) return;
    const index = Number(trigger.getAttribute("data-remove-draft"));
    if (Number.isNaN(index) || !state.contacts.drafts[index]) return;
    state.contacts.drafts.splice(index, 1);
    renderContacts();
    renderSummary();
  });

  renderAll();
})();
