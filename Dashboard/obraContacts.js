(function () {
  const panel = document.getElementById("obraContactsPanel");
  if (!panel) {
    return;
  }

  const elements = {
    state: document.getElementById("obraContactsState"),
    counter: document.getElementById("obraContactsCounter"),
    list: document.getElementById("obraContactsList"),
    saveSelection: document.getElementById("obraContactsSaveSelection"),
    newState: document.getElementById("obraContactsNewState"),
    add: document.getElementById("obraContactsAdd"),
    cancel: document.getElementById("obraContactsCancel"),
    formEyebrow: document.getElementById("obraContactsFormEyebrow"),
    formTitle: document.getElementById("obraContactsFormTitle"),
    name: document.getElementById("obraContactName"),
    role: document.getElementById("obraContactRole"),
    type: document.getElementById("obraContactType"),
    email: document.getElementById("obraContactEmail"),
    phone: document.getElementById("obraContactPhone"),
    notes: document.getElementById("obraContactNotes"),
  };
  const contactsGrid = panel.querySelector(".obra-contacts-grid");
  const registrationCard = elements.add
    ? elements.add.closest(".obra-contacts-card")
    : null;
  const registrationFields = [
    elements.name,
    elements.role,
    elements.type,
    elements.email,
    elements.phone,
    elements.notes,
  ].filter(Boolean);

  const contactTypeLabels = {
    COMERCIAL: "Comercial",
    APROVACAO: "Aprovacao",
    FINANCEIRO: "Financeiro",
    MARKETING: "Marketing",
    ARQUITETO: "Arquiteto",
    OUTRO: "Outro",
  };

  const state = {
    obraId: "",
    clientName: "",
    architectureReady: true,
    canManageRegistration: false,
    loading: false,
    available: [],
    selectedIds: [],
    form: defaultContact(),
    formMode: "add",
    editingContactId: null,
  };

  function defaultContact() {
    return {
      name: "",
      role: "",
      type: "OUTRO",
      email: "",
      phone: "",
      notes: "",
    };
  }

  function notify(message, type) {
    if (window.Toastify) {
      Toastify({
        text: message,
        duration: 3200,
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
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function resolveObraId() {
    return (
      localStorage.getItem("obraId") ||
      localStorage.getItem("idObra") ||
      new URLSearchParams(window.location.search).get("obra_id") ||
      new URLSearchParams(window.location.search).get("obraId") ||
      ""
    );
  }

  function canManageRegistration() {
    const collaboratorId = Number(localStorage.getItem("idcolaborador") || 0);
    return [1, 9, 21].includes(collaboratorId);
  }

  function syncRegistrationVisibility() {
    if (!registrationCard) {
      return;
    }

    const shouldHide = !state.canManageRegistration;
    registrationCard.hidden = shouldHide;
    registrationCard.style.display = shouldHide ? "none" : "";

    if (contactsGrid) {
      contactsGrid.style.gridTemplateColumns = shouldHide
        ? "minmax(0, 1fr)"
        : "";
    }

    registrationFields.forEach((field) => {
      field.disabled = shouldHide;
    });
  }

  function contactTypeLabel(value) {
    return contactTypeLabels[value] || contactTypeLabels.OUTRO;
  }

  function contactPayload(contact) {
    return {
      name: String(contact.name || "").trim(),
      role: String(contact.role || "").trim(),
      type: String(contact.type || "OUTRO").trim() || "OUTRO",
      email: String(contact.email || "").trim(),
      phone: String(contact.phone || "").trim(),
      notes: String(contact.notes || "").trim(),
    };
  }

  function syncFormFields() {
    elements.name.value = state.form.name;
    elements.role.value = state.form.role;
    elements.type.value = state.form.type;
    elements.email.value = state.form.email;
    elements.phone.value = state.form.phone;
    elements.notes.value = state.form.notes;
  }

  function selectedCount() {
    return state.selectedIds.length;
  }

  function render() {
    elements.counter.textContent = `${selectedCount()} selecionado(s)`;
    elements.saveSelection.disabled = state.loading || !state.architectureReady;
    elements.add.disabled =
      state.loading ||
      !state.architectureReady ||
      !state.canManageRegistration;
    syncRegistrationVisibility();

    if (!state.architectureReady) {
      elements.state.textContent =
        "A nova arquitetura de contatos ainda nao esta disponivel no banco. Execute a migracao SQL desta feature antes de gerenciar vinculos por obra.";
    } else if (state.loading) {
      elements.state.textContent = "Carregando base operacional de contatos...";
    } else if (!state.available.length) {
      elements.state.textContent = state.clientName
        ? `Nenhum contato ativo encontrado para ${state.clientName}.`
        : "Nenhum contato ativo encontrado para o cliente desta obra.";
    } else {
      elements.state.textContent =
        "Lista completa do cliente carregada. Os já vinculados à obra ficam selecionados.";
    }

    elements.newState.textContent = state.architectureReady
      ? "O cadastro permanente ja sai disponivel para esta obra."
      : "Migre o banco para liberar cadastro e vinculo operacional por obra.";

    if (!state.available.length) {
      elements.list.innerHTML =
        '<div class="obra-contact-empty">Nenhum contato disponivel para esta obra.</div>';
      syncFormFields();
      return;
    }

    elements.list.innerHTML = state.available
      .map((contact) => {
        const contactId = Number(contact.contact_id || 0);
        const isSelected = state.selectedIds.includes(contactId);
      const primaryMeta = contact.email || contact.phone || "Sem contato principal";
      const secondaryMeta = [];

      if (contact.email && contact.phone) {
        secondaryMeta.push(contact.phone);
      }
      if (contact.role) {
        secondaryMeta.push(contact.role);
      }

        return `
          <label class="obra-contact-option ${isSelected ? "is-selected" : ""}">
              <span class="obra-contact-checkbox">
                  <input type="checkbox" data-contact-select="1" data-contact-id="${contactId}" ${isSelected ? "checked" : ""}>
              </span>
          <div class="obra-contact-option-core">
            <strong class="obra-contact-option-name">${escapeHtml(contact.name || "Contato sem nome")}</strong>
            <div class="obra-contact-option-meta">
              <span class="obra-contact-option-primary">${escapeHtml(primaryMeta)}</span>
              ${secondaryMeta.length ? `<span class="obra-contact-option-secondary">${escapeHtml(secondaryMeta.join(" • "))}</span>` : ""}
                  </div>
              </div>
          <div class="obra-contact-option-side">
            <span class="obra-contact-pill">${escapeHtml(contactTypeLabel(contact.type || "OUTRO"))}</span>
            ${state.canManageRegistration ? `<button type="button" class="obra-contact-edit-btn" data-contact-edit="${contactId}" title="Editar contato"><i class="fa-solid fa-pencil" aria-hidden="true"></i></button>` : ""}
          </div>
          </label>`;
      })
      .join("");

    if (state.canManageRegistration) {
      elements.list.querySelectorAll("[data-contact-edit]").forEach((btn) => {
        btn.addEventListener("click", (event) => {
          event.stopPropagation();
          event.preventDefault();
          const cId = Number(btn.dataset.contactEdit || 0);
          const found = state.available.find((c) => Number(c.contact_id) === cId);
          if (found) {
            enterEditMode(found);
          }
        });
      });
    }

    syncFormFields();
  }

  async function fetchContacts() {
    if (!state.obraId) {
      return;
    }

    state.loading = true;
    render();

    try {
      const response = await fetch(
        "getClienteContacts.php?obra_id=" + encodeURIComponent(state.obraId),
        {
          credentials: "same-origin",
        },
      );
      const data = await response.json().catch(() => null);

      if (!response.ok || !data || !data.success) {
        throw new Error(
          data && data.message
            ? data.message
            : "Erro ao carregar contatos da obra.",
        );
      }

      state.clientName = data.cliente_nome || "";
      state.architectureReady = Boolean(data.architecture_ready);
      state.available = Array.isArray(data.contacts) ? data.contacts : [];
      state.selectedIds = state.available
        .filter((contact) => Boolean(contact.obra_selected))
        .map((contact) => Number(contact.contact_id))
        .filter((contactId) => contactId > 0);
    } catch (error) {
      console.error(error);
      notify(error.message || "Erro ao carregar contatos da obra.", "error");
    } finally {
      state.loading = false;
      render();
    }
  }

  async function saveSelection() {
    if (!state.obraId || !state.architectureReady) {
      return;
    }

    elements.saveSelection.disabled = true;

    try {
      const response = await fetch("saveObraContacts.php", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          obra_id: Number(state.obraId),
          contact_ids: state.selectedIds.slice(),
        }),
      });
      const data = await response.json().catch(() => null);

      if (!response.ok || !data || !data.success) {
        throw new Error(
          data && data.message
            ? data.message
            : "Erro ao salvar selecao de contatos da obra.",
        );
      }

      notify("Selecao operacional de contatos atualizada.");
      await fetchContacts();
    } catch (error) {
      console.error(error);
      notify(
        error.message || "Erro ao salvar selecao de contatos da obra.",
        "error",
      );
    } finally {
      elements.saveSelection.disabled = false;
    }
  }

  async function saveNewContact() {
    if (!state.obraId || !state.architectureReady) {
      return;
    }

    if (!state.canManageRegistration) {
      notify("Sem permissao para cadastrar contatos nesta obra.", "error");
      return;
    }

    const payload = contactPayload(state.form);
    if (!payload.name) {
      notify("Informe o nome do contato.", "error");
      return;
    }

    elements.add.disabled = true;

    try {
      const response = await fetch("saveClienteContact.php", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          obra_id: Number(state.obraId),
          link_to_obra: true,
          contact: payload,
        }),
      });
      const data = await response.json().catch(() => null);

      if (!response.ok || !data || !data.success) {
        throw new Error(
          data && data.message
            ? data.message
            : "Erro ao cadastrar contato da obra.",
        );
      }

      state.form = defaultContact();
      syncFormFields();
      notify("Contato cadastrado e vinculado a obra.");
      await fetchContacts();
    } catch (error) {
      console.error(error);
      notify(error.message || "Erro ao cadastrar contato da obra.", "error");
    } finally {
      elements.add.disabled = false;
    }
  }

  function updateFormCardHead() {
    const isEditing = state.formMode === "edit";
    if (elements.formEyebrow) {
      elements.formEyebrow.textContent = isEditing ? "Editando contato" : "Cadastro inline";
      elements.formEyebrow.classList.toggle("is-edit", isEditing);
    }
    if (elements.formTitle) {
      if (isEditing) {
        const editing = state.available.find(
          (c) => Number(c.contact_id) === state.editingContactId
        );
        elements.formTitle.textContent = editing ? editing.name : "Editar contato";
      } else {
        elements.formTitle.textContent = "Novo contato do cliente";
      }
    }
    if (elements.add) {
      elements.add.textContent = isEditing ? "Salvar alterações" : "Cadastrar contato";
      elements.add.classList.toggle("is-primary", isEditing);
      elements.add.classList.toggle("is-secondary", !isEditing);
    }
    if (elements.cancel) {
      elements.cancel.hidden = !isEditing;
    }
    if (registrationCard) {
      registrationCard.classList.toggle("obra-contacts-card--edit-mode", isEditing);
    }
  }

  function enterEditMode(contact) {
    state.formMode = "edit";
    state.editingContactId = Number(contact.contact_id || 0);
    state.form = {
      name: String(contact.name || ""),
      role: String(contact.role || ""),
      type: String(contact.type || "OUTRO"),
      email: String(contact.email || ""),
      phone: String(contact.phone || ""),
      notes: String(contact.notes || ""),
    };
    syncFormFields();
    updateFormCardHead();
    if (registrationCard) {
      registrationCard.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }
  }

  function exitEditMode() {
    state.formMode = "add";
    state.editingContactId = null;
    state.form = defaultContact();
    syncFormFields();
    updateFormCardHead();
  }

  async function updateContact() {
    if (!state.editingContactId || !state.architectureReady) {
      return;
    }
    if (!state.canManageRegistration) {
      notify("Sem permissao para editar contatos.", "error");
      return;
    }
    const payload = contactPayload(state.form);
    if (!payload.name) {
      notify("Informe o nome do contato.", "error");
      return;
    }
    elements.add.disabled = true;
    if (elements.cancel) {
      elements.cancel.disabled = true;
    }
    try {
      const response = await fetch("updateClienteContact.php", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          contact_id: state.editingContactId,
          contact: payload,
        }),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok || !data || !data.success) {
        throw new Error(
          data && data.message ? data.message : "Erro ao atualizar contato."
        );
      }
      notify("Contato atualizado com sucesso.");
      exitEditMode();
      await fetchContacts();
    } catch (error) {
      console.error(error);
      notify(error.message || "Erro ao atualizar contato.", "error");
    } finally {
      elements.add.disabled = false;
      if (elements.cancel) {
        elements.cancel.disabled = false;
      }
    }
  }

  state.obraId = resolveObraId();
  state.canManageRegistration = canManageRegistration();

  [
    [elements.name, "name"],
    [elements.role, "role"],
    [elements.email, "email"],
    [elements.phone, "phone"],
    [elements.notes, "notes"],
  ].forEach(([element, field]) => {
    element.addEventListener("input", () => {
      state.form[field] = element.value.trim();
    });
  });

  elements.type.addEventListener("change", () => {
    state.form.type = elements.type.value || "OUTRO";
  });

  elements.list.addEventListener("change", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) {
      return;
    }
    if (target.dataset.contactSelect !== "1") {
      return;
    }

    const contactId = Number(target.dataset.contactId || 0);
    if (!contactId) {
      return;
    }

    if (target.checked) {
      state.selectedIds = Array.from(new Set(state.selectedIds.concat([contactId])));
    } else {
      state.selectedIds = state.selectedIds.filter((value) => value !== contactId);
    }

    render();
  });

  elements.saveSelection.addEventListener("click", saveSelection);
  elements.add.addEventListener("click", () => {
    if (state.formMode === "edit") {
      updateContact();
    } else {
      saveNewContact();
    }
  });
  if (elements.cancel) {
    elements.cancel.addEventListener("click", exitEditMode);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", fetchContacts);
  } else {
    fetchContacts();
  }
})();