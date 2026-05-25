(function () {
  const btnOpen = document.getElementById("btnAddClienteObra");
  const modal = document.getElementById("modalAddClienteObra");
  if (!btnOpen || !modal) return;

  const elements = {
    close: document.getElementById("closeAddClienteObra"),
    clienteSelect: document.getElementById("onbClienteSelect"),
    clienteNovoField: document.getElementById("onbClienteNovoField"),
    clienteNovo: document.getElementById("onbClienteNovo"),
    projetoInterno: document.getElementById("onbProjetoInterno"),
    projetoComercial: document.getElementById("onbProjetoComercial"),
    codigoInterno: document.getElementById("onbCodigoInterno"),
    observacoes: document.getElementById("onbObservacoes"),
    packageStill: document.getElementById("onbPackageStill"),
    stillQtd: document.getElementById("onbStillQtd"),
    stillPrazo: document.getElementById("onbStillPrazo"),
    packageAnimation: document.getElementById("onbPackageAnimation"),
    animationSeconds: document.getElementById("onbAnimationSeconds"),
    animationPrazo: document.getElementById("onbAnimationPrazo"),
    packageFilm: document.getElementById("onbPackageFilm"),
    filmDuration: document.getElementById("onbFilmDuration"),
    filmPrazo: document.getElementById("onbFilmPrazo"),
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
      step: 1,
      clientId: "0",
      clientName: "",
      projectInternal: "",
      projectCommercial: "",
      code: "",
      notes: "",
      packages: {
        still: { enabled: false, quantity: "", deadline_days: "" },
        animation: { enabled: false, seconds: "", deadline_days: "" },
        film: { enabled: false, duration: "", deadline_days: "" },
      },
      images: {
        file_name: "",
        source: "manual",
        entries: [],
        duplicates: [],
        errors: [],
      },
      contacts: createContactsState(),
      isSubmitting: false,
    };
  }

  let state = createInitialState();

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
    if (state.clientId === "0") {
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
    if (state.clientId !== "0") {
      const option =
        elements.clienteSelect.options[elements.clienteSelect.selectedIndex];
      return option ? option.textContent.trim() : "Cliente existente";
    }
    return state.clientName || "Novo cliente";
  }

  function selectedPackages() {
    const packages = [];
    if (state.packages.still.enabled) {
      packages.push(
        `STL ${state.packages.still.quantity || 0}img / ${state.packages.still.deadline_days || 0}d`,
      );
    }
    if (state.packages.animation.enabled) {
      packages.push(
        `ANI ${state.packages.animation.seconds || 0}s / ${state.packages.animation.deadline_days || 0}d`,
      );
    }
    if (state.packages.film.enabled) {
      packages.push(
        `FLM ${state.packages.film.duration || "0s"} / ${state.packages.film.deadline_days || 0}d`,
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
        label: "Imagens importadas",
        done: imagesImported,
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
    elements.clienteNovoField.style.display = isNewClient ? "flex" : "none";
  }

  function updatePackageCardVisual(packageKey) {
    const card = packageCardMap[packageKey];
    if (!card) return;
    card.classList.toggle("is-enabled", !!state.packages[packageKey].enabled);
  }

  function updateStepUI() {
    elements.stepButtons.forEach((button) => {
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

    elements.prevStep.style.visibility =
      state.step === 1 ? "hidden" : "visible";
    elements.nextStep.style.display =
      state.step === 4 ? "block" : "block";
    elements.nextStep.style.visibility =
      state.step === 4 ? "hidden" : "visible";
    elements.submit.style.display = state.step === 4 ? "block" : "none";
  }

  function renderContacts() {
    const selectedCount = selectedContactsCount();
    const usingExistingClient = state.clientId !== "0";

    elements.contactsCounter.textContent = `${selectedCount} selecionado(s)`;
    elements.contactModeNote.textContent = usingExistingClient
      ? "Salvar novo contato cria um registro permanente em contato_cliente e deixa o contato pronto para selecao nesta obra."
      : "Cliente novo: os contatos adicionados aqui ficam em rascunho e serao criados automaticamente ao finalizar o onboarding.";
    elements.addContact.textContent = usingExistingClient
      ? "Salvar novo contato"
      : "Adicionar ao onboarding";

    if (!usingExistingClient) {
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
    elements.totalImages.textContent = String(state.images.entries.length);
    elements.namedImages.textContent = String(state.images.entries.length);
    elements.duplicateImages.textContent = String(
      state.images.duplicates.length,
    );
    elements.errorImages.textContent = String(state.images.errors.length);
    elements.previewCaption.textContent = `${state.images.entries.length} itens prontos para criação`;

    const previewItems = state.images.entries.slice(0, 24);
    elements.previewList.innerHTML = previewItems.length
      ? previewItems.map((name) => `<li>${escapeHtml(name)}</li>`).join("")
      : "<li>Nenhuma imagem carregada ainda.</li>";
  }

  function renderSummary() {
    const packages = selectedPackages();
    const checklist = computedChecklist();
    const contactsCount = selectedContactsCount();

    elements.summaryList.innerHTML = `
            <div class="onb-summary-item"><span>Cliente</span><strong>${escapeHtml(selectedClientLabel())}</strong></div>
            <div class="onb-summary-item"><span>Projeto interno</span><strong>${escapeHtml(state.projectInternal || "A definir")}</strong></div>
            <div class="onb-summary-item"><span>Projeto comercial</span><strong>${escapeHtml(state.projectCommercial || "A definir")}</strong></div>
            <div class="onb-summary-item"><span>Código</span><strong>${escapeHtml(state.code || "A definir")}</strong></div>
              <div class="onb-summary-item"><span>Pacotes</span><strong>${packages.length ? escapeHtml(packages.join(" • ")) : "Nenhum pacote selecionado"}</strong></div>
              <div class="onb-summary-item"><span>Importação</span><strong>${state.images.entries.length} img / ${state.images.duplicates.length} dup / ${state.images.errors.length} err</strong></div>
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
    updateClientMode();
    updatePackageCardVisual("still");
    updatePackageCardVisual("animation");
    updatePackageCardVisual("film");
    updateStepUI();
    renderContacts();
    renderImageState();
    renderSummary();
  }

  function resetForm() {
    state = createInitialState();
    elements.clienteSelect.value = "0";
    elements.clienteNovo.value = "";
    elements.projetoInterno.value = "";
    elements.projetoComercial.value = "";
    elements.codigoInterno.value = "";
    elements.observacoes.value = "";
    elements.packageStill.checked = false;
    elements.stillQtd.value = "";
    elements.stillPrazo.value = "";
    elements.packageAnimation.checked = false;
    elements.animationSeconds.value = "";
    elements.animationPrazo.value = "";
    elements.packageFilm.checked = false;
    elements.filmDuration.value = "";
    elements.filmPrazo.value = "";
    elements.imageFile.value = "";
    elements.manualImages.value = "";
    renderAll();
  }

  function open() {
    resetForm();
    modal.style.display = "flex";
    document.body.classList.add("onb-modal-open");
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
    if (step === 1) {
      const usingExistingClient = state.clientId !== "0";
      if (!usingExistingClient && !state.clientName) {
        notify("Informe o nome do novo cliente.", "error");
        return false;
      }
      if (!state.projectInternal || !state.projectCommercial || !state.code) {
        notify(
          "Preencha projeto interno, projeto comercial e código interno.",
          "error",
        );
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
    }

    if (step === 4) {
      if (selectedContactsCount() === 0) {
        notify("Adicione pelo menos um contato do cliente.", "error");
        return false;
      }
    }

    return true;
  }

  function goToStep(nextStep) {
    const targetStep = Math.max(1, Math.min(4, nextStep));
    if (targetStep > state.step && !validateStep(state.step)) {
      return;
    }
    state.step = targetStep;
    renderAll();
  }

  function buildPayload() {
    return {
      cliente_id: state.clientId !== "0" ? Number(state.clientId) : null,
      cliente: state.clientId === "0" ? state.clientName : "",
      obra: state.projectInternal,
      nome_real: state.projectCommercial,
      nomenclatura: state.code,
      cliente_nome: selectedClientLabel(),
      observacoes: state.notes,
      packages: state.packages,
      images: state.images.entries,
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
    if (!validateStep(1) || !validateStep(2) || !validateStep(4)) {
      return;
    }

    state.isSubmitting = true;
    elements.submit.disabled = true;

    try {
      const response = await fetch("iniciarProjeto.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildPayload()),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok || !data || !data.success) {
        throw new Error(
          data && data.message ? data.message : "Erro ao iniciar projeto.",
        );
      }

      notify(
        "Projeto criado em onboarding com sucesso. Atualizando dashboard...",
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

  elements.clienteSelect.addEventListener("change", () => {
    state.clientId = elements.clienteSelect.value || "0";
    resetContactsState();
    if (state.clientId !== "0") {
      state.clientName = "";
      elements.clienteNovo.value = "";
      renderAll();
      fetchClientContacts();
      return;
    }
    renderAll();
  });

  elements.clienteNovo.addEventListener("input", () => {
    state.clientName = elements.clienteNovo.value.trim();
    renderSummary();
  });
  elements.projetoInterno.addEventListener("input", () => {
    state.projectInternal = elements.projetoInterno.value.trim();
    renderSummary();
  });
  elements.projetoComercial.addEventListener("input", () => {
    state.projectCommercial = elements.projetoComercial.value.trim();
    renderSummary();
  });
  elements.codigoInterno.addEventListener("input", () => {
    state.code = elements.codigoInterno.value.trim().toUpperCase();
    elements.codigoInterno.value = state.code;
    renderSummary();
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

  bindPackageToggle("still", elements.packageStill);
  bindPackageField("still", "quantity", elements.stillQtd);
  bindPackageField("still", "deadline_days", elements.stillPrazo);
  bindPackageToggle("animation", elements.packageAnimation);
  bindPackageField("animation", "seconds", elements.animationSeconds);
  bindPackageField("animation", "deadline_days", elements.animationPrazo);
  bindPackageToggle("film", elements.packageFilm);
  bindPackageField("film", "duration", elements.filmDuration);
  bindPackageField("film", "deadline_days", elements.filmPrazo);

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
