(() => {
  const make = (tag, text, cls) => {
    const n = document.createElement(tag);
    if (text != null) n.textContent = text;
    if (cls) n.className = cls;
    return n;
  };
  const button = (label, callback) => {
    const b = make("button", label, "mp-icon-button");
    b.type = "button";
    b.setAttribute("aria-label", label);
    b.title = label;
    b.addEventListener("click", callback);
    return b;
  };
  const dateTime = (value) => {
    const m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec(
      value || "",
    );
    return m
      ? `${m[3]}/${m[2]}/${m[1]}${m[4] ? " " + m[4] + ":" + m[5] : ""}`
      : "Sem registro disponível";
  };
  const accordion = (title, cls = "") => {
    const section = make("details", null, `mp-op-section ${cls}`);
    section.append(make("summary", title));
    return section;
  };
  class AmbienteOperacionalPanel {
    constructor(host, load, onClose) {
      this.load = load;
      this.onClose = onClose;
      this.mode = "compacto";
      this.token = 0;
      this.panel = make("section", null, "mp-operational-panel");
      this.panel.hidden = true;
      this.panel.tabIndex = -1;
      this.panel.setAttribute("aria-label", "Inspeção operacional do ambiente");
      this.panel.setAttribute("role", "region");
      this.header = make("header", null, "mp-op-header");
      const titles = make("div");
      this.title = make("h2");
      this.subtitle = make("p");
      titles.append(this.title, this.subtitle);
      titles.className = "mp-op-header-context";
      this.headerImage = make("div", null, "mp-op-header-image");
      this.headerImage.hidden = true;
      this.min = button("Minimizar painel", () => {
        this.previousMode = this.mode;
        this.setMode("minimizado");
      });
      this.max = button("Maximizar painel", () =>
        this.setMode(this.mode === "completo" ? "compacto" : "completo"),
      );
      this.restore = button("Restaurar painel", () =>
        this.setMode(this.previousMode || "compacto"),
      );
      this.closeButton = button("Fechar painel", () => this.close(true));
      this.min.textContent = "−";
      this.max.textContent = "⛶";
      this.restore.textContent = "↑";
      this.closeButton.textContent = "×";
      const controls = make("div", null, "mp-op-actions");
      controls.append(this.min, this.max, this.restore, this.closeButton);
      this.header.append(titles, this.headerImage, controls);
      this.body = make("div", null, "mp-op-body");
      this.panel.append(this.header, this.body);
      host.append(this.panel);
      this.panel.addEventListener("pointerdown", (e) => {
        if (!e.target.closest(".it-actions"))
          this.panel.querySelectorAll(".it-actions[open]").forEach((menu) => {
            menu.open = false;
          });
      });
      this.setMode("compacto");
      window.addEventListener("focus", () => {
        if (
          !this.panel.hidden &&
          !document.querySelector(".mp-task-dialog[open]") &&
          this.contexts &&
          Date.now() - (this.loadedAt || 0) > 30000
        )
          this.refresh();
      });
      document.addEventListener("keydown", (e) => {
        if (
          e.key !== "Escape" ||
          this.panel.hidden ||
          document.querySelector("dialog[open]") ||
          e.defaultPrevented
        )
          return;
        if (!this.panel.contains(document.activeElement)) return;
        e.preventDefault();
        this.close(true);
      });
    }
    setMode(mode) {
      const before = this.panel.getBoundingClientRect(),
        scroll = this.body.scrollTop;
      this.modeScroll ||= {};
      this.modeScroll[this.mode] = scroll;
      this.motion?.cancel();
      this.contentMotion?.cancel();
      this.mode = mode;
      this.panel.dataset.mode = mode;
      this.placeImageInfo();
      this.min.hidden = mode === "minimizado";
      this.restore.hidden = mode !== "minimizado";
      this.max.setAttribute(
        "aria-label",
        mode === "completo" ? "Restaurar tamanho normal" : "Maximizar painel",
      );
      this.max.title = this.max.getAttribute("aria-label");
      this.body.scrollTop = this.modeScroll[mode] ?? scroll;
      if (
        !this.panel.hidden &&
        before.width &&
        !window.matchMedia("(prefers-reduced-motion: reduce)").matches
      ) {
        const after = this.panel.getBoundingClientRect();
        this.motion = this.panel.animate(
          [
            {
              transform: `translate(${before.left - after.left}px, ${before.top - after.top}px) scale(${before.width / after.width}, ${before.height / after.height})`,
            },
            { transform: "none" },
          ],
          { duration: 320, easing: "cubic-bezier(.22,1,.36,1)" },
        );
        this.contentMotion = this.body.animate(
          [
            { opacity: 0.5, transform: "translateY(4px)" },
            { opacity: 1, transform: "none" },
          ],
          { duration: 200, delay: 100, fill: "backwards", easing: "ease-out" },
        );
      }
    }
    placeImageInfo() {
      if (!this.imageInfo) return;
      const full = this.mode === "completo";
      this.headerImage.hidden = !full;
      if (full) this.headerImage.append(this.imageInfo);
      else this.imageHero.insertBefore(this.imageInfo, this.imageTimeline);
    }
    close(restoreFocus = false) {
      ++this.token;
      this.panel.hidden = true;
      this.contexts = null;
      this.headerImage.replaceChildren();
      this.headerImage.hidden = true;
      this.imageInfo = null;
      this.onClose?.();
      if (restoreFocus) {
        const target = this.returnFocus?.isConnected
          ? this.returnFocus
          : document.getElementById("plantaViewport");
        target?.focus({ preventScroll: true });
      }
    }
    open(environment, images, floorName, actions) {
      this.returnFocus = document.activeElement;
      this.environment = environment;
      this.images = images;
      this.actions = actions;
      this.floorName = floorName || "Pavimento";
      this.selected = images.length === 1 ? images[0].id : null;
      this.headerImage.replaceChildren();
      this.headerImage.hidden = true;
      this.imageInfo = null;
      this.contexts = null;
      this.panel.hidden = false;
      this.panel.style.setProperty(
        "--environment-color",
        window.plantaEnvironmentColor?.(environment.id).color ||
          "var(--spatial-accent)",
      );
      this.title.textContent = environment.nome;
      this.subtitle.textContent = `${this.floorName} · ${images.length} ${images.length === 1 ? "imagem" : "imagens"}`;
      this.panel.focus({ preventScroll: true });
      this.refresh();
    }
    async refresh() {
      const token = ++this.token;
      this.panel.setAttribute("aria-busy", "true");
      if (!this.images.length) {
        this.contexts = new Map();
        this.panel.setAttribute("aria-busy", "false");
        this.render();
        return;
      }
      if (!this.contexts) {
        this.body.replaceChildren();
        const loading = make("div", null, "mp-op-loading");
        loading.setAttribute("role", "status");
        const orb = make("canvas");
        orb.dataset.thinkingOrb = "";
        orb.dataset.orbState = "composing";
        orb.dataset.orbSize = "48";
        orb.setAttribute("aria-label", "Buscando situação operacional");
        loading.append(orb, make("p", "Buscando situação operacional"));
        this.body.append(loading);
        window.thinkingOrbs?.(loading);
      }
      try {
        const result = await this.load(this.images.map((i) => i.id));
        if (token !== this.token || this.panel.hidden) return;
        this.contexts = new Map(result.contextos.map((c) => [c.imagem.id, c]));
        this.loadedAt = Date.now();
        this.render();
      } catch (error) {
        if (token !== this.token || this.panel.hidden) return;
        const message = make("p", error.message, "mp-op-error");
        message.setAttribute("role", "alert");
        if (!this.contexts) this.body.replaceChildren(message);
        else this.body.prepend(message);
        const retry = button("Tentar novamente", () => this.refresh());
        retry.textContent = "Tentar novamente";
        this.body.append(retry);
      } finally {
        if (token === this.token) this.panel.setAttribute("aria-busy", "false");
      }
    }
    thumbnail(image, parent, enlarge = false) {
      const empty = () => {
        parent.classList.add("has-no-preview");
        const box = make("div", null, "mp-op-no-preview");
        box.append(
          make("span", "▧"),
          make("strong", "Prévia indisponível"),
          make("small", "Esta imagem ainda não possui prévia disponível."),
        );
        return box;
      };
      if (!image.preview_url) {
        parent.append(empty());
        return;
      }
      const img = make("img");
      img.src = image.preview_url;
      img.alt = image.imagem_nome;
      img.loading = "lazy";
      img.addEventListener(
        "error",
        () => {
          if (img.parentElement?.tagName === "BUTTON")
            img.parentElement.replaceWith(empty());
          else img.replaceWith(empty());
        },
        { once: true },
      );
      if (enlarge) {
        const open = button("Ampliar prévia da imagem", () =>
          this.openPreview(image),
        );
        open.className = "mp-op-preview-open";
        open.textContent = "";
        open.append(img, make("span", "⛶", "mp-op-preview-expand"));
        parent.append(open);
      } else parent.append(img);
    }
    openPreview(image) {
      const dialog = make("dialog", null, "mp-op-preview-dialog");
      const close = button("Fechar prévia", () => dialog.close());
      close.textContent = "×";
      const header = make("header");
      header.append(make("strong", image.imagem_nome), close);
      const img = make("img");
      img.src = image.preview_url;
      img.alt = image.imagem_nome;
      dialog.append(header, img);
      document.body.append(dialog);
      dialog.addEventListener("close", () => dialog.remove(), { once: true });
      dialog.showModal();
    }
    render() {
      if (!this.contexts) return;
      this.headerImage.replaceChildren();
      this.headerImage.hidden = true;
      this.imageInfo = null;
      this.body.replaceChildren();
      const ui = window.FlowImagemTimeline;
      if (this.images.length > 1 && this.selected != null) {
        const toolbar = make("div", null, "mp-op-toolbar");
        const back = button("Todas as imagens do ambiente", () => {
          this.selected = null;
          this.render();
        });
        back.textContent = "← Todas as imagens";
        toolbar.append(back);
        this.body.append(toolbar);
      }
      if (this.selected == null) {
        this.body.append(make("h3", "Imagens do ambiente"));
        if (!this.images.length)
          this.body.append(
            make(
              "p",
              "Nenhuma imagem vinculada. Use as ações do ambiente para relacionar imagens.",
            ),
          );
        const list = make("div", null, "mp-op-image-list");
        for (const image of this.images) {
          const context = this.contexts.get(image.id);
          const card = make("button", null, "mp-op-image-card");
          card.type = "button";
          card.setAttribute("aria-label", `Abrir ${image.imagem_nome}`);
          const thumb = make("div", null, "mp-op-card-thumb");
          this.thumbnail(image, thumb);
          const info = make("div", null, "mp-op-card-info");
          info.append(make("strong", image.imagem_nome));
          if (context) {
            info.append(
              make("span", ui.statusLabel(context.imagem)),
              ui.summary(context),
            );
          } else info.append(make("p", "Contexto indisponível"));
          card.append(thumb, info);
          card.addEventListener("click", () => {
            this.selected = image.id;
            this.render();
          });
          list.append(card);
        }
        this.body.append(list);
        const refresh = button("Atualizar informações", () => this.refresh());
        refresh.textContent = "↻ Atualizar";
        this.body.append(refresh);
      } else {
        const image = this.images.find((i) => i.id === this.selected),
          context = this.contexts.get(this.selected);
        if (!image || !context) {
          this.body.append(make("p", "Contexto operacional indisponível."));
          return;
        }
        const hero = make("section", null, "mp-op-image-hero");
        const preview = make("div", null, "mp-op-preview");
        this.thumbnail(image, preview, true);
        const info = make("div", null, "mp-op-image-info");
        if (this.images.length > 1)
          info.append(
            make(
              "small",
              `Imagem ${this.images.findIndex((i) => i.id === image.id) + 1} de ${this.images.length}`,
              "mp-op-image-index",
            ),
          );
        info.append(make("h3", image.imagem_nome));
        const official = make(
          "p",
          ui.statusLabel(context.imagem),
          "mp-op-official-status si-" +
            (context.imagem.status_nome || "").toLowerCase(),
        );
        info.append(official);
        const updated = make("div", null, "mp-op-updated");
        const actualUpdate =
          context.imagem.atualizado_em || image.preview_atualizado_em;
        updated.append(
          make(
            "span",
            actualUpdate
              ? `Última atualização ${dateTime(actualUpdate)}`
              : "Atualização sem data registrada",
          ),
        );
        const refresh = button("Atualizar informações", () => this.refresh());
        refresh.textContent = "↻";
        updated.append(refresh);
        info.append(updated);
        this.imageHero = hero;
        this.imageInfo = info;
        this.imageTimeline = ui.overview(context);
        hero.append(preview, info, this.imageTimeline);
        this.body.append(hero);
        this.placeImageInfo();
        this.body.append(
          ui.render(
            context,
            this.mode === "completo" ? "completo" : "compacto",
          ),
        );
        const details = accordion("Detalhes da imagem", "mp-op-image-details");
        const values = [
          ["Etapa", ui.statusLabel(context.imagem)],
          ["Ambiente", this.environment.nome],
          ["Pavimento", this.floorName],
          ["Quantidade de imagens", this.images.length],
          ["Última atualização", dateTime(actualUpdate)],
        ];
        const dl = make("dl");
        values.forEach(([k, v]) => dl.append(make("dt", k), make("dd", v)));
        details.append(dl);
        this.body.append(details);
      }
      if (this.actions) {
        const edit = accordion("Ações do ambiente", "mp-op-edit");
        edit.append(this.actions);
        this.body.append(edit);
      }
      const history = accordion("Histórico e comentários", "mp-op-history");
      history.append(
        make(
          "p",
          "Consulte o histórico e os comentários no FlowReview da função.",
        ),
      );
      const contexts =
        this.selected == null
          ? [...this.contexts.values()]
          : [this.contexts.get(this.selected)];
      for (const context of contexts)
        for (const stage of context?.etapas || []) {
          const link = ui.reviewLink(stage, context);
          link.textContent = stage.funcao_nome;
          history.append(link);
        }
      this.body.append(history);
    }
  }
  window.AmbienteOperacionalPanel = AmbienteOperacionalPanel;
})();
