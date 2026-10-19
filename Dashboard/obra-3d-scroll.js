export class Obra3DScroll {
  constructor({ hero, slot, layer, viewer }) {
    Object.assign(this, { hero, slot, layer, viewer });
    this.scroller = hero.closest(".container");
    this.toolbar = layer.querySelector(".obra-3d-toolbar");
    this.returnButton = layer.querySelector(".obra-3d-return");
    this.abort = new AbortController();
    this.cameraChanged = () => {
      clearTimeout(this.cameraTimer);
      this.cameraTimer = setTimeout(() => {
        if (!this.timeline || this.timeline.progress() < 0.001) this.build();
      }, 250);
    };
    viewer.controls.addEventListener("change", this.cameraChanged);
    this.returnButton.addEventListener("click", () => this.returnToHero(), {
      signal: this.abort.signal,
    });
    this.scroller.addEventListener("wheel", () => this.returnTween?.kill(), {
      passive: true,
      signal: this.abort.signal,
    });
    this.scroller.addEventListener(
      "touchstart",
      () => this.returnTween?.kill(),
      { passive: true, signal: this.abort.signal },
    );
    window.gsap.registerPlugin(window.ScrollTrigger);
    this.media = window.gsap.matchMedia();
    this.media.add(
      {
        all: "all",
        desktop: "(min-width: 768px)",
        reduce: "(prefers-reduced-motion: reduce)",
      },
      (context) => {
        this.reduced = context.conditions.reduce;
        this.desktop = context.conditions.desktop;
        this.build();
        return () => {
          this.returnTween?.kill();
          this.reset();
        };
      },
    );
    this.observer = new ResizeObserver(() => {
      if (
        this.width === this.slot.clientWidth &&
        this.height === this.slot.clientHeight &&
        this.scrollerWidth === this.scroller.clientWidth
      )
        return;
      this.build();
    });
    this.observer.observe(slot);
    this.observer.observe(this.scroller);
  }

  reset(keepFloating = false) {
    this.timeline?.scrollTrigger?.kill();
    this.timeline?.kill();
    this.timeline = null;
    if (!keepFloating) {
      this.slot.append(this.layer);
      Object.assign(this.layer.style, {
        position: "absolute",
        width: "100%",
        height: "100%",
      });
    }
    window.gsap.set(this.layer, { clearProps: "transform,visibility,opacity" });
    window.gsap.set(this.toolbar, { clearProps: "opacity,visibility" });
    this.layer.classList.remove("is-minimap");
    this.layer.style.pointerEvents = "";
    this.returnButton.hidden = true;
    this.toolbar.inert = false;
    this.viewer.setMinimized(false);
  }

  build() {
    this.reset(this.desktop);
    this.width = this.slot.clientWidth;
    this.height = this.slot.clientHeight;
    this.scrollerWidth = this.scroller.clientWidth;
    if (!this.desktop || !this.width || !this.height) {
      this.viewer.resize();
      this.viewer.invalidate();
      return;
    }
    Object.assign(this.layer.style, {
      width: `${this.width}px`,
      height: `${this.height}px`,
    });
    this.viewer.resize();
    const rect = this.slot.getBoundingClientRect();
    const container = this.scroller.getBoundingClientRect();
    const header = this.scroller
      .querySelector(":scope > header")
      .getBoundingClientRect();
    const startX = rect.left;
    const startY = rect.top + this.scroller.scrollTop;
    const subject = this.viewer.screenBounds() || {
      left: this.width * 0.3,
      right: this.width * 0.7,
      top: 0,
      bottom: this.height,
    };
    const subjectWidth = Math.max(1, subject.right - subject.left);
    const subjectHeight = Math.max(1, subject.bottom - subject.top);
    const miniSize = this.scroller.clientWidth > 1100 ? 160 : 120;
    const scale = Math.min(
      (miniSize - 24) / Math.max(subjectWidth, subjectHeight),
      0.45,
    );
    const side = miniSize / scale;
    const left = (subject.left + subject.right - side) / 2;
    const top = (subject.top + subject.bottom - side) / 2;
    const endX =
      container.left + this.scroller.clientWidth - 12 - (left + side) * scale;
    const endY = header.bottom + 12 - top * scale;
    this.layer.style.setProperty(
      "--obra3d-crop",
      `inset(${top}px ${this.width - left - side}px ${this.height - top - side}px ${left}px round ${10 / scale}px)`,
    );
    this.layer.style.setProperty("--obra3d-frame-border", `${1 / scale}px`);
    this.layer.style.setProperty("--obra3d-frame-radius", `${10 / scale}px`);
    const frame = this.layer.querySelector(".obra-3d-mini-frame");
    for (const element of [frame, this.returnButton])
      Object.assign(element.style, {
        left: `${left}px`,
        top: `${top}px`,
        width: `${side}px`,
        height: `${side}px`,
      });
    if (this.layer.parentNode !== document.body)
      document.body.append(this.layer);
    Object.assign(this.layer.style, {
      position: "fixed",
      width: `${this.width}px`,
      height: `${this.height}px`,
    });
    window.gsap.set(this.layer, { x: startX, y: startY, scale: 1 });
    this.timeline = window.gsap.timeline({
      defaults: { ease: "none" },
      onUpdate: () => this.syncState(),
      scrollTrigger: {
        id: "obra-3d-hero",
        scroller: this.scroller,
        trigger: this.hero,
        start: 0,
        end: () =>
          Math.max(
            240,
            this.hero.offsetTop + this.hero.offsetHeight - header.height - 80,
          ),
        scrub: this.reduced ? true : 0.25,
        invalidateOnRefresh: false,
      },
    });
    this.timeline
      .to(this.layer, { x: endX, y: endY, scale, duration: 1 }, 0)
      .to(this.toolbar, { autoAlpha: 0, duration: 0.3 }, 0);
    this.timeline.scrollTrigger.refresh();
    this.timeline.progress(this.timeline.scrollTrigger.progress);
    this.syncState();
    // Reparenting pode invalidar o drawing buffer de um canvas WebGL sob demanda.
    this.viewer.invalidate();
  }

  syncState() {
    const progress = this.timeline?.progress() || 0;
    const minimized = progress >= 0.985;
    const transitioning = progress > 0.02;
    this.layer.classList.toggle("is-minimap", minimized);
    this.layer.style.pointerEvents = transitioning && !minimized ? "none" : "";
    this.layer.dataset.view = minimized
      ? "minimap"
      : transitioning
        ? "transition"
        : "hero";
    // A toolbar deixa de receber foco assim que começa a desaparecer.
    this.toolbar.inert = transitioning;
    this.viewer.setMinimized(transitioning);
    this.returnButton.hidden = !minimized || !this.loaded;
  }

  ready() {
    this.loaded = true;
    this.build();
    this.syncState();
    const host = this.layer.querySelector(".obra-3d-canvas-wrap");
    this.entrance = window.gsap.fromTo(
      host,
      { opacity: 0, scale: this.reduced ? 1 : 0.97 },
      {
        opacity: 1,
        scale: 1,
        duration: this.reduced ? 0 : 0.6,
        ease: "power2.out",
        clearProps: "transform,opacity",
      },
    );
  }

  returnToHero() {
    this.returnTween?.kill();
    this.returnTween = window.gsap.to(this.scroller, {
      scrollTop: 0,
      duration: this.reduced ? 0 : 0.85,
      ease: "power2.inOut",
      overwrite: "auto",
      onComplete: () =>
        this.viewer.renderer.domElement.focus({ preventScroll: true }),
    });
  }

  destroy() {
    clearTimeout(this.cameraTimer);
    this.returnTween?.kill();
    this.viewer.controls.removeEventListener("change", this.cameraChanged);
    this.abort.abort();
    this.observer.disconnect();
    this.entrance?.kill();
    this.media.revert();
    this.reset();
  }
}
