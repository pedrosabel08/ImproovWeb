(() => {
  "use strict";

  const clamp = (value, min = 0, max = 1) =>
    Math.min(max, Math.max(min, Number(value) || 0));
  const FALLBACK_PILLARS = [
    { name: "Atmosfera", keyword: "Intenção" },
    { name: "Arquitetura", keyword: "Estrutura" },
    { name: "Materialidade", keyword: "Superfície" },
    { name: "Luz", keyword: "Contraste" },
    { name: "Lifestyle", keyword: "Presença" },
    { name: "Fotografia", keyword: "Enquadramento" },
    { name: "Composição", keyword: "Síntese" },
  ];

  class AlmaBuildLoader {
    constructor(root, content) {
      this.root = root;
      this.content = content;
      this.progress = 0;
      this.startedAt = performance.now();
      this.reducedMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)",
      ).matches;
      this.minDuration = Number(root?.dataset.minDuration || 1450);
      this.sequenceDelay = Number(root?.dataset.sequenceDelay || 200);
      this.pillarDuration = Number(root?.dataset.pillarDuration || 175);
      this.settleDuration = Number(root?.dataset.settleDuration || 100);
      this.revealDuration = Number(root?.dataset.revealDuration || 380);
      this.finishTimer = 0;
      this.cleanupTimer = 0;
      this.autoTimer = 0;
      this.sequenceTimer = 0;
      this.sequenceStarted = false;
      this.imagePreparation = new Map();
      this.autoCeiling = 0;
      this.currentPillar = -1;
      this.completedPillars = 0;
      this.sequenceComplete = false;
      this.resolveSequence = null;
      this.sequencePromise = Promise.resolve();
      this.finishingPromise = null;
      this.pillars = [];
      this.palette = [
        "#b994ff",
        "#78a4ff",
        "#d4ad74",
        "#f0c866",
        "#ec8ca4",
        "#6ed1c8",
        "#9abc83",
      ];
      this.elements = {
        nodes: root?.querySelector("#almaLoaderPillars"),
        lines: root?.querySelector("#almaLoaderConnections"),
        fragments: root?.querySelector("#almaLoaderFragments"),
        status: root?.querySelector("#almaLoaderStatus"),
        percent: root?.querySelector("#almaLoaderPercent"),
      };
    }

    start(fallbackPillars = FALLBACK_PILLARS) {
      if (!this.root) return this;
      this.setState("entering");
      this.root.dataset.sequence = "waiting";
      this.setPillars(
        fallbackPillars.map((pillar, index) => ({
          code: `pillar-${index + 1}`,
          ...pillar,
        })),
      );
      this.setProgress(0.04, "Preparando o espaço visual");
      this.autoCeiling = 0.32;
      this.autoTimer = window.setInterval(() => {
        if (this.progress >= this.autoCeiling) return;
        const distance = this.autoCeiling - this.progress;
        this.setProgress(
          Math.min(
            this.autoCeiling,
            this.progress + Math.max(0.005, distance * 0.15),
          ),
        );
      }, 100);
      requestAnimationFrame(() => this.setState("loading"));
      return this;
    }

    setState(nextState) {
      if (!this.root) return;
      this.root.dataset.state = nextState;
    }

    setPillars(pillars = []) {
      if (!this.root || !pillars.length) return;
      this.pillars = pillars.slice(0, 7).map((pillar, index) => ({
        code: String(pillar.code || pillar.codigo || `pillar-${index + 1}`),
        name: String(
          pillar.name ||
            pillar.pilar_nome ||
            pillar.nome ||
            pillar.etapa_nome ||
            `Pilar ${String(index + 1).padStart(2, "0")}`,
        ),
        keyword: String(pillar.keyword || pillar.item_titulo || "").trim(),
        image: String(pillar.image || pillar.thumbnail_url || "").trim(),
        color: pillar.color || this.palette[index % this.palette.length],
      }));
      this.completedPillars = Math.min(
        this.completedPillars,
        this.pillars.length,
      );
      this.renderComposition();
      this.syncActivation();
    }

    startPillarSequence() {
      if (this.sequenceStarted || !this.pillars.length) return;
      this.sequenceStarted = true;
      this.sequencePromise = new Promise((resolve) => {
        this.resolveSequence = resolve;
      });

      if (this.reducedMotion) {
        this.completedPillars = this.pillars.length;
        this.sequenceComplete = true;
        this.root.dataset.sequence = "complete";
        this.syncActivation();
        this.resolveSequence?.();
        return;
      }

      this.root.dataset.sequence = "idle";
      this.sequenceTimer = window.setTimeout(
        () => this.advancePillarSequence(),
        this.sequenceDelay,
      );
    }

    async advancePillarSequence() {
      if (!this.root || this.root.dataset.state === "done") return;

      if (this.currentPillar >= 0)
        this.completedPillars = Math.max(
          this.completedPillars,
          this.currentPillar + 1,
        );

      if (this.completedPillars >= this.pillars.length) {
        this.currentPillar = -1;
        this.sequenceComplete = true;
        this.root.dataset.sequence = "complete";
        this.setProgress(0.92, "Consolidando a composição final");
        this.syncActivation();
        this.resolveSequence?.();
        return;
      }

      const nextPillar = this.completedPillars;
      await this.preparePillarImage(nextPillar);
      if (!this.root || this.root.dataset.state === "done") return;

      this.currentPillar = nextPillar;
      this.root.dataset.sequence = "feeding";
      const pillar = this.pillars[this.currentPillar];
      const sequenceProgress =
        0.12 + ((this.currentPillar + 1) / this.pillars.length) * 0.7;
      this.setProgress(sequenceProgress, `Estruturando ${pillar.name}`);
      this.syncActivation();
      this.sequenceTimer = window.setTimeout(
        () => void this.advancePillarSequence(),
        this.pillarDuration,
      );
    }

    preparePillarImage(index) {
      const pillar = this.pillars[index];
      if (!pillar?.image) return Promise.resolve();
      if (this.imagePreparation.has(index))
        return this.imagePreparation.get(index);

      const prepared = new Promise((resolve) => {
        const image = new Image();
        const timeout = window.setTimeout(resolve, 1200);
        const complete = () => {
          window.clearTimeout(timeout);
          resolve();
        };
        image.onload = complete;
        image.onerror = complete;
        image.src = pillar.image;
      });
      this.imagePreparation.set(index, prepared);
      return prepared;
    }

    renderComposition() {
      const { nodes, lines, fragments } = this.elements;
      if (!nodes || !lines || !fragments) return;
      nodes.replaceChildren();
      lines.replaceChildren();
      fragments.replaceChildren();

      const count = Math.max(this.pillars.length, 1);
      this.pillars.forEach((pillar, index) => {
        const angle = -Math.PI / 2 + (Math.PI * 2 * index) / count;
        const x = 50 + Math.cos(angle) * 43;
        const y = 50 + Math.sin(angle) * 40;
        const node = document.createElement("div");
        node.className = "alma-build-loader__node";
        node.dataset.loaderIndex = String(index);
        node.style.setProperty("--node-x", `${x}%`);
        node.style.setProperty("--node-y", `${y}%`);
        node.style.setProperty("--node-color", pillar.color);
        node.innerHTML = `<i>${String(index + 1).padStart(2, "0")}</i><span></span>`;
        node.querySelector("span").textContent = pillar.name;
        nodes.appendChild(node);

        const line = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "line",
        );
        line.setAttribute("x1", String(x));
        line.setAttribute("y1", String(y));
        line.setAttribute("x2", "50");
        line.setAttribute("y2", "50");
        line.dataset.loaderIndex = String(index);
        line.style.setProperty("--node-color", pillar.color);
        lines.appendChild(line);

        const fragment = document.createElement("article");
        fragment.className = "alma-build-loader__fragment";
        fragment.dataset.loaderIndex = String(index);
        fragment.dataset.layout = String((index % 7) + 1);
        fragment.style.setProperty("--node-color", pillar.color);
        fragment.dataset.image = pillar.image;
        const fragmentLabel = document.createElement("span");
        fragmentLabel.textContent = pillar.keyword || pillar.name;
        fragment.appendChild(fragmentLabel);
        fragments.appendChild(fragment);
      });
    }

    setProgress(value, message) {
      if (!this.root || this.root.dataset.state === "done") return;
      this.progress = Math.max(this.progress, clamp(value));
      this.autoCeiling = Math.max(
        this.autoCeiling,
        Math.min(0.94, this.progress + 0.12),
      );
      this.root.style.setProperty("--alma-loader-progress", this.progress);
      if (message && this.elements.status)
        this.elements.status.textContent = message;
      if (this.elements.percent)
        this.elements.percent.textContent = `${String(Math.round(this.progress * 100)).padStart(2, "0")}%`;
    }

    syncActivation() {
      if (!this.root || !this.pillars.length) return;
      this.root.querySelectorAll("[data-loader-index]").forEach((element) => {
        const index = Number(element.dataset.loaderIndex);
        const isDone = index < this.completedPillars;
        const isFeeding = index === this.currentPillar;
        if (
          (isDone || isFeeding) &&
          element.classList.contains("alma-build-loader__fragment")
        )
          this.applyFragmentImage(element);
        element.classList.toggle("is-done", isDone);
        element.classList.toggle("is-feeding", isFeeding);
        if (element.classList.contains("alma-build-loader__node")) {
          const marker = element.querySelector("i");
          if (marker)
            marker.textContent = isDone
              ? "✓"
              : String(index + 1).padStart(2, "0");
        }
      });
    }

    applyFragmentImage(fragment) {
      const image = fragment.dataset.image;
      if (!image || fragment.dataset.imageApplied === "true") return;
      fragment.classList.add("has-image");
      fragment.style.backgroundImage = `linear-gradient(180deg, transparent 35%, rgba(3, 8, 13, .88)), url("${image.replaceAll('"', "%22")}")`;
      fragment.dataset.imageApplied = "true";
    }

    finish() {
      if (!this.root || this.root.dataset.state === "done")
        return Promise.resolve();
      if (this.finishingPromise) return this.finishingPromise;
      window.clearInterval(this.autoTimer);
      const elapsed = performance.now() - this.startedAt;
      const minimumDuration = this.reducedMotion ? 120 : this.minDuration;
      const minimumWait = Math.max(0, minimumDuration - elapsed);
      const minimumPromise = new Promise((resolve) => {
        this.finishTimer = window.setTimeout(resolve, minimumWait);
      });

      this.finishingPromise = Promise.all([
        this.sequencePromise,
        minimumPromise,
      ]).then(
        () =>
          new Promise((resolve) => {
            this.setProgress(1, "Direção visual estruturada");
            const settleDuration = this.reducedMotion
              ? 40
              : this.settleDuration;
            window.setTimeout(() => {
              this.setState("revealing");
              this.content?.setAttribute("aria-busy", "false");
              this.content?.classList.add("is-ready");
              const revealDuration = this.reducedMotion
                ? 120
                : this.revealDuration;
              this.cleanupTimer = window.setTimeout(() => {
                this.setState("done");
                this.root.hidden = true;
                this.root.style.pointerEvents = "none";
                resolve();
              }, revealDuration);
            }, settleDuration);
          }),
      );
      return this.finishingPromise;
    }

    fail() {
      if (!this.root) return;
      window.clearTimeout(this.finishTimer);
      window.clearTimeout(this.cleanupTimer);
      window.clearTimeout(this.sequenceTimer);
      window.clearInterval(this.autoTimer);
      this.resolveSequence?.();
      this.content?.setAttribute("aria-busy", "false");
      this.content?.classList.add("is-ready");
      this.setState("done");
      this.root.hidden = true;
      this.root.style.pointerEvents = "none";
    }
  }

  window.AlmaBuildLoader = AlmaBuildLoader;
})();
