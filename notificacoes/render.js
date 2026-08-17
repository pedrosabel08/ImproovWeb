/* Render único da notificação: usado no início e na prévia administrativa. */
(function (window, document) {
  "use strict";

  function rootUrl(path) {
    if (!path) return "";
    if (/^https?:\/\//i.test(path)) return path;
    if (typeof window.resolveImproovUrl === "function")
      return window.resolveImproovUrl(path);
    if (path.startsWith("/")) return window.location.origin + path;
    var root = (window.PROJECT_ROOT || "/flow/ImproovWeb").replace(/\/+$/, "");
    return window.location.origin + root + "/" + path.replace(/^\/+/, "");
  }

  function sanitize(html) {
    var tags = new Set([
      "P",
      "BR",
      "STRONG",
      "B",
      "EM",
      "I",
      "U",
      "S",
      "UL",
      "OL",
      "LI",
      "A",
      "H1",
      "H2",
      "H3",
      "BLOCKQUOTE",
      "SPAN",
    ]);
    var template = document.createElement("template");
    template.innerHTML = String(html || "");
    function clean(node) {
      Array.prototype.slice.call(node.children).forEach(function (el) {
        if (!tags.has(el.tagName)) {
          el.replaceWith.apply(el, Array.prototype.slice.call(el.childNodes));
          return;
        }
        Array.prototype.slice.call(el.attributes).forEach(function (attr) {
          var name = attr.name.toLowerCase();
          var value = attr.value.trim();
          var allowed =
            (name === "class" &&
              value.split(/\s+/).every(function (c) {
                return /^(ql-align-(center|right|justify)|ql-indent-[1-8])$/.test(
                  c,
                );
              })) ||
            (el.tagName === "LI" &&
              name === "data-list" &&
              ["ordered", "bullet"].indexOf(value) !== -1) ||
            (el.tagName === "A" &&
              name === "href" &&
              /^(https?:\/\/|mailto:|\/)/i.test(value)) ||
            (el.tagName === "A" &&
              name === "target" &&
              ["_blank", "_self"].indexOf(value) !== -1) ||
            (name === "style" &&
              /^(color|background-color)\s*:\s*(#[0-9a-f]{3,8}|rgb\([\d\s,%]+\)|rgba\([\d\s,.%]+\))\s*;?$/i.test(
                value,
              ));
          if (!allowed) el.removeAttribute(attr.name);
        });
        if (el.tagName === "A" && el.target === "_blank")
          el.rel = "noopener noreferrer";
        clean(el);
      });
    }
    clean(template.content);
    return template.innerHTML;
  }

  function isImage(file) {
    return (
      /^image\//i.test(file.mime_type || "") ||
      /\.(png|jpe?g|gif|webp|bmp)(?:$|\?)/i.test(file.url || file.caminho || "")
    );
  }

  function attachments(notification) {
    var files = Array.isArray(notification.anexos)
      ? notification.anexos.slice()
      : [];
    if (notification.arquivo_path)
      files.push({
        nome_original: notification.arquivo_nome || "Arquivo",
        url: notification.arquivo_path,
        mime_type: "",
        legado: true,
      });
    return files;
  }

  function statusRequest(id, action) {
    if (!id || !action) return Promise.resolve();
    return fetch(rootUrl("notificacao_modulo_status.php"), {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body:
        "id=" +
        encodeURIComponent(id) +
        "&action=" +
        encodeURIComponent(action),
    }).catch(function () {});
  }

  function close(modal, options) {
    modal.remove();
    if (typeof options.onClose === "function") options.onClose();
  }

  function open(notification, options) {
    options = options || {};
    var preview = !!options.preview;
    var files = attachments(notification);
    var images = files.filter(isImage);
    var cover = images[0] || null;
    var remaining = files.filter(function (file, index) {
      return file !== cover && (!isImage(file) || index > 0);
    });
    var moduleName = notification.modulo_nome || notification.module_name || "";
    var moduleUrl = notification.modulo_url || notification.module_url || "";
    var moduleIcon =
      notification.modulo_icone || notification.module_icon || "fa-cubes";
    var ctaLabel =
      moduleName && moduleUrl
        ? "Explorar " + moduleName
        : notification.cta_label || "";
    var ctaUrl =
      moduleName && moduleUrl ? moduleUrl : notification.cta_url || "";
    var requiresConfirmation =
      String(notification.exige_confirmacao || "0") !== "0";
    var closable =
      String(
        notification.fechavel === undefined ? "1" : notification.fechavel,
      ) !== "0";
    var modal = document.createElement("div");
    modal.className =
      "flow-notification" + (preview ? " flow-notification--preview" : "");
    modal.setAttribute("role", "dialog");
    modal.setAttribute("aria-modal", "true");
    modal.innerHTML =
      '<div class="flow-notification__backdrop"></div><section class="flow-notification__panel"><button type="button" class="flow-notification__close" aria-label="Fechar">×</button><div class="flow-notification__preview-meta" hidden></div><div class="flow-notification__content"><div class="flow-notification__main' +
      (cover ? " flow-notification__main--with-media" : "") +
      '"><div class="flow-notification__copy"><div class="flow-notification__version"><i class="fa-solid fa-rocket"></i> FLOW <span></span></div><h2></h2><div class="flow-notification__message notification-content"></div><div class="flow-notification__module" hidden></div></div><div class="flow-notification__hero" hidden></div></div><div class="flow-notification__attachments" hidden></div></div><footer class="flow-notification__footer"><label class="flow-notification__dismiss" hidden><input type="checkbox"> Não mostrar novamente</label><div class="flow-notification__footer-actions"><small class="flow-notification__hint" hidden></small><button type="button" class="flow-notification__later">Agora não</button><button type="button" class="flow-notification__confirm" hidden>Confirmar leitura</button><a class="flow-notification__cta" hidden></a></div></footer></section>';
    document.body.appendChild(modal);

    var q = function (selector) {
      return modal.querySelector(selector);
    };
    q(".flow-notification__version span").textContent =
      notification.versao_publicacao ||
      notification.versao ||
      window.APP_VERSION ||
      "dev";
    q("h2").textContent = notification.titulo || "Atualização do Flow";
    var message = String(notification.mensagem || "");
    q(".flow-notification__message").innerHTML = sanitize(
      message.indexOf("<") !== -1 ? message : message.replace(/\r?\n/g, "<br>"),
    );

    if (preview) {
      var previewMeta = q(".flow-notification__preview-meta");
      previewMeta.hidden = false;
      previewMeta.innerHTML =
        "<strong>PRÉVIA — A notificação ainda não foi publicada</strong><span></span>";
      previewMeta.querySelector("span").textContent =
        "Segmentação: " +
        (notification.segmentacao_label ||
          notification.segmentacao_tipo ||
          "Geral") +
        " · Módulo: " +
        (moduleName || "Nenhum") +
        " · Versão: " +
        (notification.versao_publicacao ||
          notification.versao ||
          window.APP_VERSION ||
          "dev") +
        " · Exige confirmação: " +
        (requiresConfirmation ? "Sim" : "Não");
    }

    if (moduleName) {
      var module = q(".flow-notification__module");
      module.hidden = false;
      module.innerHTML =
        '<i class="fa-solid ' +
        String(moduleIcon).replace(/[^a-z0-9-]/gi, "") +
        '"></i><div><small>Módulo relacionado</small><strong></strong></div>';
      module.querySelector("strong").textContent = moduleName;
    }
    if (cover) {
      var hero = q(".flow-notification__hero");
      hero.hidden = false;
      hero.innerHTML = '<img alt="">';
      var image = hero.querySelector("img");
      image.src = rootUrl(cover.url || cover.caminho);
      image.alt = cover.nome_original || "Imagem da notificação";
    }
    if (remaining.length) {
      var attachmentsBox = q(".flow-notification__attachments");
      attachmentsBox.hidden = false;
      var list = document.createElement("ul");
      remaining.forEach(function (file) {
        var item = document.createElement("li");
        var link = document.createElement("a");
        link.href = rootUrl(file.url || file.caminho);
        link.target = "_blank";
        link.rel = "noopener noreferrer";
        link.innerHTML = '<i class="fa-solid fa-paperclip"></i><span></span>';
        link.querySelector("span").textContent = file.nome_original || "Anexo";
        item.appendChild(link);
        list.appendChild(item);
      });
      attachmentsBox.appendChild(list);
    }

    var closeButton = q(".flow-notification__close");
    var later = q(".flow-notification__later");
    if (!closable) {
      closeButton.hidden = true;
      later.hidden = true;
    }
    function dismiss() {
      close(modal, options);
    }
    closeButton.addEventListener("click", dismiss);
    q(".flow-notification__backdrop").addEventListener("click", function () {
      if (closable) dismiss();
    });
    later.addEventListener("click", dismiss);

    var confirm = q(".flow-notification__confirm");
    var hint = q(".flow-notification__hint");
    var confirmationDone = !requiresConfirmation;
    if (requiresConfirmation) {
      confirm.hidden = false;
      hint.hidden = false;
      hint.textContent =
        "Confirme a leitura antes de concluir esta notificação.";
      confirm.addEventListener("click", function () {
        statusRequest(notification.id, "confirmado");
        confirmationDone = true;
        confirm.hidden = true;
        hint.hidden = false;
        hint.textContent = "Leitura confirmada.";
        if (!ctaUrl) dismiss();
      });
    }
    if (notification.fixa && !preview) {
      var dismissControl = q(".flow-notification__dismiss");
      dismissControl.hidden = false;
      dismissControl
        .querySelector("input")
        .addEventListener("change", function (event) {
          if (event.target.checked)
            statusRequest(notification.id, "dispensado");
        });
    }
    if (ctaUrl) {
      var cta = q(".flow-notification__cta");
      cta.hidden = false;
      cta.href = rootUrl(ctaUrl);
      cta.textContent = ctaLabel;
      if (!moduleName) {
        cta.target = "_blank";
        cta.rel = "noopener noreferrer";
      }
      cta.addEventListener("click", function (event) {
        if (preview) {
          event.preventDefault();
          return;
        }
        if (requiresConfirmation && !confirmationDone) {
          event.preventDefault();
          hint.hidden = false;
          hint.textContent = "Confirme a leitura antes de continuar.";
          return;
        }
        statusRequest(
          notification.id,
          requiresConfirmation ? "confirmado" : "visto",
        );
      });
    }
    if (!preview) statusRequest(notification.id, "visto");
    return modal;
  }

  window.FlowNotificationRenderer = { open: open, sanitize: sanitize };
})(window, document);
