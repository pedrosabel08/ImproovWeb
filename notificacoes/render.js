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

  function imageAlt(file, index, total) {
    var name = String(file.nome_original || "").trim();
    var position = total > 1 ? " " + (index + 1) + " de " + total : "";
    return name
      ? "Imagem" + position + " da notificação: " + name
      : "Imagem" + position + " da notificação";
  }

  function createIcon(name) {
    var icon = document.createElement("i");
    icon.className = "fa-solid " + name;
    icon.setAttribute("aria-hidden", "true");
    return icon;
  }

  function openImageLightbox(images, startIndex) {
    var index = startIndex;
    var lightbox = document.createElement("div");
    lightbox.className = "flow-notification-lightbox";
    lightbox.setAttribute("role", "dialog");
    lightbox.setAttribute("aria-modal", "true");
    lightbox.setAttribute("aria-label", "Imagem ampliada da notificação");
    lightbox.innerHTML =
      '<div class="flow-notification-lightbox__backdrop"></div><section class="flow-notification-lightbox__panel"><button type="button" class="flow-notification-lightbox__close" aria-label="Fechar imagem ampliada"></button><img class="flow-notification-lightbox__image" alt=""><div class="flow-notification-lightbox__counter" aria-live="polite" hidden></div></section>';
    document.body.appendChild(lightbox);

    var q = function (selector) {
      return lightbox.querySelector(selector);
    };
    var image = q(".flow-notification-lightbox__image");
    var counter = q(".flow-notification-lightbox__counter");
    var closeButton = q(".flow-notification-lightbox__close");
    closeButton.appendChild(createIcon("fa-xmark"));

    function update(nextIndex) {
      index = (nextIndex + images.length) % images.length;
      var file = images[index];
      image.src = rootUrl(file.url || file.caminho);
      image.alt = imageAlt(file, index, images.length);
      if (images.length > 1)
        counter.textContent = index + 1 + " / " + images.length;
    }

    function dismiss() {
      document.removeEventListener("keydown", onKeydown);
      lightbox.remove();
    }

    function onKeydown(event) {
      if (event.key === "Escape") dismiss();
      if (images.length > 1 && event.key === "ArrowLeft") update(index - 1);
      if (images.length > 1 && event.key === "ArrowRight") update(index + 1);
    }

    closeButton.addEventListener("click", dismiss);
    q(".flow-notification-lightbox__backdrop").addEventListener(
      "click",
      dismiss,
    );
    if (images.length > 1) {
      var previous = document.createElement("button");
      previous.type = "button";
      previous.className =
        "flow-notification-lightbox__nav flow-notification-lightbox__nav--previous";
      previous.setAttribute("aria-label", "Imagem anterior");
      previous.appendChild(createIcon("fa-chevron-left"));
      previous.addEventListener("click", function () {
        update(index - 1);
      });
      var next = document.createElement("button");
      next.type = "button";
      next.className =
        "flow-notification-lightbox__nav flow-notification-lightbox__nav--next";
      next.setAttribute("aria-label", "Próxima imagem");
      next.appendChild(createIcon("fa-chevron-right"));
      next.addEventListener("click", function () {
        update(index + 1);
      });
      q(".flow-notification-lightbox__panel").append(previous, next);
      counter.hidden = false;
    }
    document.addEventListener("keydown", onKeydown);
    update(index);
    closeButton.focus();
  }

  function createImageCarousel(images) {
    var index = 0;
    var touchStart = null;
    var carousel = document.createElement("div");
    carousel.className =
      "flow-notification__carousel" +
      (images.length > 1 ? " flow-notification__carousel--multiple" : "");
    carousel.tabIndex = 0;
    carousel.setAttribute("role", "region");
    carousel.setAttribute("aria-label", "Galeria de imagens da notificação");

    var stage = document.createElement("div");
    stage.className = "flow-notification__carousel-stage";
    var image = document.createElement("img");
    image.className = "flow-notification__carousel-image";
    image.loading = "eager";
    image.decoding = "async";
    image.addEventListener("click", function () {
      openImageLightbox(images, index);
    });
    stage.appendChild(image);
    carousel.appendChild(stage);

    var counter;
    var dots;
    function update(nextIndex) {
      index = (nextIndex + images.length) % images.length;
      var file = images[index];
      if (images.length > 1) {
        image.classList.remove("is-changing");
        // Reinicia a animação sem recriar elementos ou listeners.
        void image.offsetWidth;
        image.classList.add("is-changing");
      }
      image.src = rootUrl(file.url || file.caminho);
      image.alt = imageAlt(file, index, images.length);
      if (counter) counter.textContent = index + 1 + " / " + images.length;
      if (dots) {
        Array.prototype.forEach.call(dots.children, function (dot, dotIndex) {
          var active = dotIndex === index;
          dot.classList.toggle("is-active", active);
          dot.setAttribute("aria-current", active ? "true" : "false");
        });
      }
    }

    if (images.length > 1) {
      [
        ["previous", "Imagem anterior", "fa-chevron-left", -1],
        ["next", "Próxima imagem", "fa-chevron-right", 1],
      ].forEach(function (control) {
        var button = document.createElement("button");
        button.type = "button";
        button.className =
          "flow-notification__carousel-nav flow-notification__carousel-nav--" +
          control[0];
        button.setAttribute("aria-label", control[1]);
        button.appendChild(createIcon(control[2]));
        button.addEventListener("click", function () {
          update(index + control[3]);
        });
        stage.appendChild(button);
      });

      counter = document.createElement("div");
      counter.className = "flow-notification__carousel-counter";
      counter.setAttribute("aria-live", "polite");
      stage.appendChild(counter);

      dots = document.createElement("div");
      dots.className = "flow-notification__carousel-dots";
      images.forEach(function (_, dotIndex) {
        var dot = document.createElement("button");
        dot.type = "button";
        dot.className = "flow-notification__carousel-dot";
        dot.setAttribute("aria-label", "Ir para imagem " + (dotIndex + 1));
        dot.addEventListener("click", function () {
          update(dotIndex);
        });
        dots.appendChild(dot);
      });
      carousel.appendChild(dots);
    }

    carousel.addEventListener("keydown", function (event) {
      if (images.length < 2) return;
      if (event.key === "ArrowLeft") {
        event.preventDefault();
        update(index - 1);
      }
      if (event.key === "ArrowRight") {
        event.preventDefault();
        update(index + 1);
      }
    });
    carousel.addEventListener(
      "touchstart",
      function (event) {
        var touch = event.changedTouches[0];
        touchStart = touch ? { x: touch.clientX, y: touch.clientY } : null;
      },
      { passive: true },
    );
    carousel.addEventListener(
      "touchend",
      function (event) {
        if (images.length < 2 || !touchStart) return;
        var touch = event.changedTouches[0];
        if (!touch) return;
        var deltaX = touch.clientX - touchStart.x;
        var deltaY = touch.clientY - touchStart.y;
        touchStart = null;
        if (Math.abs(deltaX) < 48 || Math.abs(deltaX) <= Math.abs(deltaY))
          return;
        update(index + (deltaX < 0 ? 1 : -1));
      },
      { passive: true },
    );
    update(0);
    return carousel;
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
    var remaining = files.filter(function (file) {
      return !isImage(file);
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
      (images.length ? " flow-notification__main--with-media" : "") +
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
    if (images.length) {
      var hero = q(".flow-notification__hero");
      hero.hidden = false;
      hero.appendChild(createImageCarousel(images));
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
