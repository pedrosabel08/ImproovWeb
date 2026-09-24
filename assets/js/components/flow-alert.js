(function (window, document) {
  "use strict";

  if (window.FlowAlert) return;

  const ICONS = {
    success: "fa-circle-check",
    warning: "fa-triangle-exclamation",
    error: "fa-circle-xmark",
    info: "fa-circle-info",
  };
  const LABELS = {
    success: "Sucesso",
    warning: "Atenção",
    error: "Erro",
    info: "Informação",
  };
  const active = new Set();
  let modalEntry = null;

  function ensureRegion(mode) {
    const id = `flow-alert-${mode}-region`;
    let region = document.getElementById(id);
    if (region) return region;

    region = document.createElement("div");
    region.id = id;
    region.className = `flow-alert-region flow-alert-region--${mode}`;
    region.setAttribute("aria-live", mode === "toast" ? "polite" : "assertive");
    region.setAttribute("aria-relevant", "additions text");
    document.body.appendChild(region);
    return region;
  }

  function normalizeOptions(options) {
    if (typeof options === "string") return { message: options };
    return options && typeof options === "object" ? options : {};
  }

  function invoke(action, event) {
    if (!action || typeof action.onClick !== "function") return;
    return action.onClick(event);
  }

  function closeEntry(entry) {
    if (!entry || entry.closed) return;
    entry.closed = true;
    window.clearTimeout(entry.timer);
    entry.element.classList.add("is-leaving");
    if (
      entry.mode === "notification" &&
      !entry.region.querySelector(".flow-alert:not(.is-leaving)")
    ) {
      entry.region.classList.add("is-emptying");
    }
    const finish = () => {
      if (entry.finished) return;
      entry.finished = true;
      entry.element.removeEventListener("transitionend", finish);
      if (entry.keyHandler)
        document.removeEventListener("keydown", entry.keyHandler);
      entry.element.remove();
      active.delete(entry);
      if (!entry.region.querySelector(".flow-alert")) {
        entry.region.remove();
      } else {
        entry.region.classList.remove("is-emptying");
      }
      if (modalEntry === entry) modalEntry = null;
      if (typeof entry.onClose === "function") {
        try {
          entry.onClose();
        } catch (error) {
          console.error("FlowAlert onClose callback failed", error);
        }
      }
      if (entry.resolve)
        entry.resolve({ isConfirmed: false, isDismissed: true });
      if (entry.previouslyFocused && entry.previouslyFocused.isConnected) {
        entry.previouslyFocused.focus();
      }
    };
    entry.element.addEventListener("transitionend", finish, { once: true });
    window.setTimeout(finish, 220);
  }

  function addAction(container, action, kind, entry, defaultLabel, result) {
    if (!action && !defaultLabel) return null;
    const config =
      typeof action === "string" ? { label: action } : action || {};
    const button = document.createElement("button");
    button.type = "button";
    button.className = `flow-alert__action flow-alert__action--${kind}`;
    button.textContent = config.label || defaultLabel;
    button.addEventListener("click", async (event) => {
      try {
        const outcome = await invoke(config, event);
        if (outcome === false) return;
        if (entry.resolve) {
          entry.resolve(
            result ||
              (outcome && typeof outcome === "object" ? outcome : null) || {
                isConfirmed: kind === "primary",
                isDismissed: kind !== "primary",
              },
          );
        }
        closeEntry(entry);
      } catch (error) {
        console.error("FlowAlert action failed", error);
      }
    });
    container.appendChild(button);
    return button;
  }

  function show(type, rawOptions) {
    const options = normalizeOptions(rawOptions);
    const mode = ["toast", "notification", "modal"].includes(options.mode)
      ? options.mode
      : type === "warning"
        ? "notification"
        : type === "error"
          ? "modal"
          : "toast";

    if (mode === "modal" && modalEntry) closeEntry(modalEntry);

    const region = ensureRegion(mode);
    const element = document.createElement(
      mode === "modal" ? "section" : "article",
    );
    element.className = `flow-alert flow-alert--${type} flow-alert--${mode}`;
    element.setAttribute("data-flow-alert-type", type);

    const entry = {
      element,
      mode,
      region,
      closed: false,
      timer: 0,
      resolve: null,
      onClose: typeof options.onClose === "function" ? options.onClose : null,
    };
    entry.update = (updates = {}) => {
      if (entry.closed) return;
      if (updates.title !== undefined) {
        const title = element.querySelector(".flow-alert__title");
        if (title) title.textContent = String(updates.title);
      }
      if (updates.message !== undefined) {
        let message = element.querySelector(".flow-alert__message");
        if (!message && updates.message) {
          message = document.createElement("p");
          message.className = "flow-alert__message";
          element.querySelector(".flow-alert__body")?.appendChild(message);
        }
        if (message) message.textContent = String(updates.message);
      }
      if (updates.progress !== undefined && entry.progressFill) {
        const value =
          updates.progress === null
            ? null
            : Math.max(0, Math.min(100, Number(updates.progress) || 0));
        entry.progressFill.style.width = value === null ? "35%" : `${value}%`;
        entry.progressTrack?.classList.toggle(
          "is-indeterminate",
          value === null,
        );
        if (value === null)
          entry.progressTrack?.removeAttribute("aria-valuenow");
        else entry.progressTrack?.setAttribute("aria-valuenow", String(value));
      }
      if (updates.type && ICONS[updates.type]) {
        element.classList.remove(
          "flow-alert--success",
          "flow-alert--warning",
          "flow-alert--error",
          "flow-alert--info",
        );
        element.classList.add(`flow-alert--${updates.type}`);
        const glyph = element.querySelector(".flow-alert__icon i");
        if (glyph) glyph.className = `fa-solid ${ICONS[updates.type]}`;
      }
    };
    entry.close = () => closeEntry(entry);
    active.add(entry);
    if (mode === "modal") modalEntry = entry;

    if (mode === "modal") {
      const backdrop = document.createElement("div");
      backdrop.className = "flow-alert-backdrop";
      backdrop.setAttribute("data-flow-alert-backdrop", "");
      const dialog = document.createElement("div");
      dialog.className = "flow-alert__dialog";
      dialog.setAttribute("role", "alertdialog");
      dialog.setAttribute("aria-modal", "true");
      element.append(backdrop, dialog);
      backdrop.addEventListener("click", () => {
        if (options.dismissible !== false) closeEntry(entry);
      });
    }

    const content =
      mode === "modal" ? element.querySelector(".flow-alert__dialog") : element;
    const icon = document.createElement("span");
    icon.className = "flow-alert__icon";
    icon.setAttribute("aria-hidden", "true");
    const iconGlyph = document.createElement("i");
    iconGlyph.className = `fa-solid ${ICONS[type]}`;
    icon.appendChild(iconGlyph);
    content.appendChild(icon);

    const body = document.createElement("div");
    body.className = "flow-alert__body";
    const heading = document.createElement("h2");
    heading.className = "flow-alert__title";
    heading.textContent = options.title || LABELS[type];
    body.appendChild(heading);
    if (options.message) {
      const message = document.createElement("p");
      message.className = "flow-alert__message";
      message.textContent = String(options.message);
      body.appendChild(message);
    }
    content.appendChild(body);

    const fieldSpecs = options.fields || (options.field ? [options.field] : []);
    if (fieldSpecs.length) {
      const fieldWrap = document.createElement("div");
      fieldWrap.className = "flow-alert__field-wrap";
      const validation = document.createElement("p");
      validation.className = "flow-alert__validation";
      validation.setAttribute("aria-live", "polite");
      validation.hidden = true;

      const fieldRecords = fieldSpecs.map((rawConfig, index) => {
        const fieldConfig =
          typeof rawConfig === "string" ? { type: rawConfig } : rawConfig;
        const group = document.createElement("div");
        group.className = "flow-alert__field-group";
        if (fieldConfig.label) {
          const label = document.createElement("span");
          label.className = "flow-alert__field-label";
          label.textContent = fieldConfig.label;
          group.appendChild(label);
        }
        const type = fieldConfig.type || "text";
        let field;
        if (type === "select") {
          field = document.createElement("select");
          if (fieldConfig.placeholder) {
            const placeholder = document.createElement("option");
            placeholder.value = "";
            placeholder.textContent = fieldConfig.placeholder;
            field.appendChild(placeholder);
          }
          Object.entries(fieldConfig.options || {}).forEach(
            ([value, label]) => {
              const option = document.createElement("option");
              option.value = value;
              option.textContent = label;
              field.appendChild(option);
            },
          );
          if (fieldConfig.value !== undefined) field.value = fieldConfig.value;
        } else if (type === "radio") {
          field = document.createElement("div");
          field.className = "flow-alert__radio-list";
          Object.entries(fieldConfig.options || {}).forEach(
            ([value, label], optionIndex) => {
              const optionLabel = document.createElement("label");
              optionLabel.className = "flow-alert__radio-option";
              const radio = document.createElement("input");
              radio.type = "radio";
              radio.name = `flow-alert-field-${index}`;
              radio.value = value;
              radio.checked = String(fieldConfig.value ?? "") === value;
              optionLabel.append(radio, document.createTextNode(label));
              field.appendChild(optionLabel);
            },
          );
        } else if (type === "checkbox") {
          field = document.createElement("label");
          field.className = "flow-alert__check-option";
          const checkbox = document.createElement("input");
          checkbox.type = "checkbox";
          checkbox.checked = Boolean(fieldConfig.checked);
          checkbox.disabled = Boolean(fieldConfig.disabled);
          checkbox.value = "1";
          field.append(
            checkbox,
            document.createTextNode(
              fieldConfig.optionLabel || fieldConfig.label || "",
            ),
          );
        } else {
          field = document.createElement(
            type === "textarea" ? "textarea" : "input",
          );
          field.className = "flow-alert__field";
          if (field.tagName === "INPUT") field.type = type;
          if (fieldConfig.placeholder)
            field.placeholder = fieldConfig.placeholder;
          if (fieldConfig.value !== undefined) field.value = fieldConfig.value;
          if (fieldConfig.min) field.min = fieldConfig.min;
          if (fieldConfig.maxlength)
            field.maxLength = Number(fieldConfig.maxlength);
          if (fieldConfig.rows) field.rows = Number(fieldConfig.rows);
        }
        if (type !== "radio" && type !== "checkbox")
          field.classList.add("flow-alert__field");
        if (fieldConfig.required && field.required !== undefined)
          field.required = true;
        field.setAttribute(
          "aria-label",
          fieldConfig.label || options.title || "Valor",
        );
        group.appendChild(field);
        fieldWrap.appendChild(group);
        const name = fieldConfig.name || `field${index}`;
        const read = () =>
          type === "radio"
            ? field.querySelector("input:checked")?.value || ""
            : type === "checkbox"
              ? field.querySelector("input")?.checked || false
              : field.value;
        field.addEventListener("input", () => entry.setValidationMessage(""));
        field.addEventListener("change", () => entry.setValidationMessage(""));
        if (
          type !== "select" &&
          type !== "radio" &&
          type !== "checkbox" &&
          type !== "textarea"
        ) {
          field.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
              event.preventDefault();
              content.querySelector(".flow-alert__action--primary")?.click();
            }
          });
        }
        return { name, type, element: field, config: fieldConfig, read };
      });
      fieldRecords.forEach((record) => {
        const dependencyConfig = record.config.dependsOn;
        if (
          !dependencyConfig &&
          typeof record.config.visibleWhen !== "function"
        )
          return;
        const dependency =
          dependencyConfig &&
          fieldRecords.find((item) => item.name === dependencyConfig.name);
        const syncVisibility = () => {
          const values = Object.fromEntries(
            fieldRecords.map((item) => [item.name, item.read()]),
          );
          const visible =
            typeof record.config.visibleWhen === "function"
              ? record.config.visibleWhen(values)
              : dependency?.read() === dependencyConfig.value;
          record.element.parentElement.hidden = !visible;
          if (!visible) {
            if (record.type === "radio") {
              record.element.querySelectorAll("input").forEach((radio) => {
                radio.checked = false;
              });
            } else if (record.element.value !== undefined) {
              record.element.value = "";
            }
          }
        };
        dependency?.element.addEventListener("change", syncVisibility);
        dependency?.element
          .querySelectorAll?.("input")
          .forEach((radio) => radio.addEventListener("change", syncVisibility));
        if (typeof record.config.visibleWhen === "function") {
          fieldRecords.forEach((item) => {
            item.element.addEventListener("change", syncVisibility);
            item.element
              .querySelectorAll?.("input")
              .forEach((radio) =>
                radio.addEventListener("change", syncVisibility),
              );
          });
        }
        syncVisibility();
      });
      fieldWrap.appendChild(validation);
      content.appendChild(fieldWrap);
      entry.fields = fieldRecords;
      entry.field = fieldRecords[0]?.element;
      entry.setValidationMessage = (message) => {
        validation.textContent = message || "";
        validation.hidden = !message;
        fieldRecords[0]?.element.setAttribute(
          "aria-invalid",
          message ? "true" : "false",
        );
      };
      entry.submitField = async () => {
        const values = Object.fromEntries(
          fieldRecords.map((record) => [record.name, record.read()]),
        );
        const validationMessage =
          typeof options.validate === "function"
            ? await options.validate(
                fieldRecords.length === 1 ? fieldRecords[0].read() : values,
                values,
              )
            : "";
        if (validationMessage) {
          entry.setValidationMessage(validationMessage);
          (fieldRecords[0]?.element.matches?.("input,select,textarea")
            ? fieldRecords[0].element
            : fieldRecords[0]?.element.querySelector("input")
          )?.focus();
          return false;
        }
        const submitted =
          typeof options.onSubmit === "function"
            ? await options.onSubmit(
                fieldRecords.length === 1 ? fieldRecords[0].read() : values,
                values,
              )
            : fieldRecords.length === 1
              ? fieldRecords[0].read()
              : values;
        if (submitted === false) return false;
        if (typeof submitted === "string") {
          entry.setValidationMessage(submitted);
          return false;
        }
        return { isConfirmed: true, isDismissed: false, value: submitted };
      };
    }

    if (options.progress !== undefined) {
      const track = document.createElement("div");
      track.className = "flow-alert__progress-track";
      track.setAttribute("role", "progressbar");
      track.setAttribute("aria-label", options.progressLabel || "Progresso");
      track.setAttribute("aria-valuemin", "0");
      track.setAttribute("aria-valuemax", "100");
      const fill = document.createElement("div");
      fill.className = "flow-alert__progress-fill";
      track.appendChild(fill);
      content.appendChild(track);
      entry.progressTrack = track;
      entry.progressFill = fill;
      entry.update({ progress: options.progress });
    }

    if (options.dismissible !== false) {
      const closeButton = document.createElement("button");
      closeButton.type = "button";
      closeButton.className = "flow-alert__close";
      closeButton.setAttribute("aria-label", "Fechar aviso");
      closeButton.innerHTML =
        '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
      closeButton.addEventListener("click", () => closeEntry(entry));
      content.appendChild(closeButton);
    }

    const actions = document.createElement("div");
    actions.className = "flow-alert__actions";
    addAction(actions, options.action, "primary", entry);
    addAction(actions, options.secondaryAction, "secondary", entry);
    if (options.confirm) {
      if (Array.isArray(options.legacyActions)) {
        options.legacyActions.forEach((action) =>
          addAction(
            actions,
            { label: action.label },
            action.kind,
            entry,
            null,
            action.result,
          ),
        );
      } else {
        if (options.showCancel !== false) {
          addAction(
            actions,
            { label: options.cancelText || "Cancelar" },
            "secondary",
            entry,
          );
        }
        addAction(
          actions,
          {
            label: options.confirmText || "Confirmar",
            onClick:
              options.field || options.fields
                ? () => entry.submitField()
                : options.onConfirm,
          },
          "primary",
          entry,
        );
      }
    }
    if (options.loading) {
      const spinner = document.createElement("span");
      spinner.className = "flow-alert__spinner";
      spinner.setAttribute("aria-label", "Carregando");
      actions.appendChild(spinner);
    }
    if (options.cancelAction) {
      addAction(
        actions,
        options.cancelAction,
        "secondary",
        entry,
        options.cancelText || "Cancelar",
      );
    }
    if (actions.childElementCount) content.appendChild(actions);

    region.appendChild(element);
    window.requestAnimationFrame(() => element.classList.add("is-visible"));

    if (mode === "toast" && options.duration !== 0) {
      entry.timer = window.setTimeout(
        () => closeEntry(entry),
        Math.max(1200, Number(options.duration) || 3600),
      );
    } else if (
      (mode === "notification" || mode === "modal") &&
      Number(options.duration) > 0
    ) {
      entry.timer = window.setTimeout(
        () => closeEntry(entry),
        Math.max(1800, Number(options.duration)),
      );
    }

    if (mode === "modal") {
      entry.previouslyFocused = document.activeElement;
      const focusTarget = element.querySelector(
        ".flow-alert__field, .flow-alert__radio-option input, .flow-alert__action, .flow-alert__close",
      );
      window.setTimeout(() => focusTarget?.focus(), 0);
      entry.keyHandler = (event) => {
        if (event.key === "Escape" && options.dismissible !== false)
          closeEntry(entry);
        if (event.key === "Tab") {
          const focusables = Array.from(
            element.querySelectorAll(
              "button:not(:disabled), input:not(:disabled), textarea:not(:disabled), select:not(:disabled)",
            ),
          );
          if (!focusables.length) return;
          const first = focusables[0];
          const last = focusables[focusables.length - 1];
          if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
          } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
          }
        }
      };
      document.addEventListener("keydown", entry.keyHandler);
    }

    return entry;
  }

  function confirm(options) {
    const config = normalizeOptions(options);
    return new Promise((resolve) => {
      const entry = show("warning", {
        ...config,
        mode: "modal",
        confirm: true,
        dismissible: config.dismissible !== false,
      });
      entry.resolve = resolve;
    });
  }

  function input(options) {
    const config = normalizeOptions(options);
    return new Promise((resolve) => {
      const entry = show(
        config.type && ICONS[config.type] ? config.type : "info",
        {
          ...config,
          message: config.message ?? config.text ?? "",
          mode: "modal",
          field: config.field || {
            type: config.inputType || "text",
            label: config.inputLabel,
            placeholder: config.inputPlaceholder,
            value: config.value,
            min: config.min,
            maxlength: config.maxlength,
            rows: config.rows,
            options: config.options,
          },
          confirm: true,
          showCancel: config.showCancel !== false,
          dismissible: config.dismissible !== false,
        },
      );
      entry.resolve = resolve;
    });
  }

  function form(options) {
    const config = normalizeOptions(options);
    return new Promise((resolve) => {
      const entry = show(config.type || "info", {
        ...config,
        mode: "modal",
        confirm: true,
        dismissible: config.dismissible !== false,
      });
      entry.resolve = resolve;
    });
  }

  function choose(options) {
    const config = normalizeOptions(options);
    return new Promise((resolve) => {
      const actions = (config.choices || []).map((choice, index) => ({
        label: choice.label,
        kind: choice.kind || (index === 0 ? "primary" : "secondary"),
        result: {
          isConfirmed: true,
          isDenied: index > 0,
          isDismissed: false,
          value: choice.value,
        },
      }));
      if (config.cancelText)
        actions.push({
          label: config.cancelText,
          kind: "secondary",
          result: { isConfirmed: false, isDismissed: true },
        });
      const entry = show(config.type || "warning", {
        ...config,
        mode: "modal",
        confirm: true,
        legacyActions: actions,
      });
      entry.resolve = resolve;
    });
  }

  function progress(options) {
    const config = normalizeOptions(options);
    const entry = show(config.type || "info", {
      ...config,
      mode: "modal",
      loading: config.loading !== false,
      progress: config.progress ?? 0,
      dismissible: config.dismissible === true,
      cancelAction: config.cancelAction,
      cancelText: config.cancelText,
    });
    const result = new Promise((resolve) => {
      entry.resolve = resolve;
    });
    return {
      update: entry.update,
      close: entry.close,
      element: entry.element,
      result,
    };
  }

  function loading(options) {
    const config = normalizeOptions(options);
    return show("info", {
      ...config,
      mode: config.mode || "modal",
      loading: true,
      dismissible: config.dismissible === true,
    });
  }

  function legacyFire(input, text, icon) {
    let options;
    if (input && typeof input === "object" && !Array.isArray(input)) {
      options = { ...input };
    } else {
      options = { title: input, message: text, icon };
    }

    const inputType = options.input;
    const inputOptions = options.inputOptions;
    const inputConfig = options.inputAttributes || {};
    const simpleInput =
      inputType &&
      !options.html &&
      !options.preConfirm &&
      !options.preDeny &&
      !options.didOpen &&
      !options.willOpen;
    if (simpleInput) {
      const type = [
        "text",
        "number",
        "email",
        "date",
        "datetime-local",
        "time",
        "textarea",
        "select",
        "radio",
      ].includes(inputType)
        ? inputType
        : "text";
      return api.input({
        type:
          options.icon === "warning" ||
          options.icon === "error" ||
          options.icon === "success"
            ? options.icon
            : "info",
        title: options.titleText ?? options.title ?? "",
        message: options.text ?? "",
        field: {
          type,
          label: options.inputLabel,
          placeholder: options.inputPlaceholder,
          value: options.inputValue,
          min: inputConfig.min,
          max: inputConfig.max,
          maxlength: inputConfig.maxlength,
          rows: inputConfig.rows,
          options: inputOptions,
          required: inputConfig.required,
        },
        validate: options.inputValidator,
        confirmText: options.confirmButtonText || "OK",
        cancelText: options.cancelButtonText || "Cancelar",
        showCancel: options.showCancelButton === true,
        dismissible: options.allowOutsideClick !== false,
      });
    }

    if (
      !options.html &&
      typeof options.didOpen === "function" &&
      /showLoading/.test(String(options.didOpen))
    ) {
      const progress = api.progress({
        title: options.titleText ?? options.title ?? "Carregando",
        message: options.text ?? "",
        progress: null,
        dismissible: options.allowOutsideClick !== false,
      });
      return progress.result;
    }

    // Keep SweetAlert for custom HTML forms and lifecycle hooks that still need a deliberate port.
    const requiresSweetAlert = [
      "html",
      "preConfirm",
      "preDeny",
      "didOpen",
      "willOpen",
      "didRender",
      "willClose",
      "didClose",
      "showLoaderOnConfirm",
      "showLoaderOnDeny",
      "footer",
      "imageUrl",
      "progressSteps",
      "validationMessage",
    ].some((key) => options[key] !== undefined);
    if (requiresSweetAlert) return null;

    const type = ["success", "warning", "error", "info", "question"].includes(
      options.icon,
    )
      ? options.icon === "question"
        ? "info"
        : options.icon
      : "info";
    const message = options.text ?? options.message ?? "";
    const mode = options.toast === true ? "toast" : "modal";
    const hasCancel = options.showCancelButton === true;
    const hasDeny = options.showDenyButton === true;
    const showConfirm = options.showConfirmButton !== false;
    const duration = options.timer ?? (mode === "toast" ? 3600 : 0);
    const common = {
      title: options.titleText ?? options.title ?? LABELS[type],
      message,
      mode,
      duration,
      dismissible: options.allowOutsideClick !== false,
    };

    const legacyActions = [];
    if (hasDeny) {
      legacyActions.push({
        label: options.denyButtonText || "Não",
        kind: "secondary",
        result: { isDenied: true, isConfirmed: false, isDismissed: false },
      });
    }
    if (hasCancel) {
      legacyActions.push({
        label: options.cancelButtonText || "Cancelar",
        kind: "secondary",
        result: { isDismissed: true, dismiss: "cancel" },
      });
    }
    if (showConfirm) {
      legacyActions.push({
        label: options.confirmButtonText || "OK",
        kind: "primary",
        result: { isConfirmed: true, isDismissed: false, value: true },
      });
    }

    const promise = new Promise((resolve) => {
      const entry = show(type, {
        ...common,
        confirm: legacyActions.length > 0,
        legacyActions,
      });
      entry.resolve = resolve;
    });
    return promise;
  }

  function installSwalBridge(sweetAlert) {
    if (!sweetAlert || sweetAlert.__flowAlertBridge) return sweetAlert;
    const facade = new Proxy(sweetAlert, {
      get(target, property) {
        if (property === "fire") {
          return function (...args) {
            const replacement = legacyFire(...args);
            return replacement || target.fire.apply(target, args);
          };
        }
        if (property === "mixin") {
          return function (...args) {
            return installSwalBridge(target.mixin.apply(target, args));
          };
        }
        if (property === "close") {
          return function (...args) {
            const result = target.close.apply(target, args);
            api.close();
            return result;
          };
        }
        const value = Reflect.get(target, property, target);
        return typeof value === "function" ? value.bind(target) : value;
      },
      set(target, property, value) {
        return Reflect.set(target, property, value, target);
      },
    });
    Object.defineProperty(facade, "__flowAlertBridge", { value: true });
    return facade;
  }

  function installGlobalBridges() {
    let sweetAlert = window.Swal;
    try {
      Object.defineProperty(window, "Swal", {
        configurable: true,
        enumerable: true,
        get() {
          return sweetAlert;
        },
        set(value) {
          sweetAlert = installSwalBridge(value);
        },
      });
      if (sweetAlert) sweetAlert = installSwalBridge(sweetAlert);
    } catch (_) {
      if (sweetAlert) {
        try {
          window.Swal = installSwalBridge(sweetAlert);
        } catch (_) {
          // Keep legacy SweetAlert operational if the host locks its global.
        }
      }
    }

    if (typeof window.alert === "function" && !window.alert.__flowAlertBridge) {
      const nativeAlert = window.alert.bind(window);
      const flowAlert = function (message) {
        if (!window.FlowAlert) return nativeAlert(message);
        const text = String(message ?? "");
        const normalized = text.toLocaleLowerCase("pt-BR");
        const type = /❌|\berro\b|\bfalha\b|\bfailed\b/.test(normalized)
          ? "error"
          : /⚠️?|\batenção\b|\bpendência\b|\baviso\b/.test(normalized)
            ? "warning"
            : /✅|\bsucesso\b|\bsalvo\b|\bexcluíd[oa]\b|\baprovad[oa]\b|\bregistrad[oa]\b/.test(
                  normalized,
                )
              ? "success"
              : "info";
        show(type, {
          title: LABELS[type],
          message: text,
          mode: "modal",
        });
      };
      Object.defineProperty(flowAlert, "__flowAlertBridge", { value: true });
      try {
        window.alert = flowAlert;
      } catch (_) {
        // Read-only host globals keep their native fallback.
      }
    }
  }

  const api = {
    success: (options) => show("success", normalizeOptions(options)),
    warning: (options) => show("warning", normalizeOptions(options)),
    error: (options) => show("error", normalizeOptions(options)),
    info: (options) => show("info", normalizeOptions(options)),
    confirm,
    loading,
    input,
    form,
    choose,
    progress,
    close() {
      Array.from(active).forEach(closeEntry);
    },
  };

  window.FlowAlert = api;
  installGlobalBridges();
})(window, document);
