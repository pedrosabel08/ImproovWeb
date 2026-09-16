/* global gsap, FlowMotionPresets */
(function (window, document) {
  "use strict";

  var initialized = false;
  var initializedElements =
    typeof WeakSet !== "undefined" ? new WeakSet() : null;
  var presets = window.FlowMotionPresets;

  function reducedMotion() {
    return (
      window.matchMedia &&
      window.matchMedia("(prefers-reduced-motion: reduce)").matches
    );
  }

  function canAnimate() {
    return Boolean(window.gsap && presets && !reducedMotion());
  }

  function asElements(value) {
    if (!value) return [];
    if (value.nodeType === 1) return [value];
    return Array.prototype.slice.call(value).filter(function (item) {
      return item && item.nodeType === 1;
    });
  }

  function unanimated(elements) {
    return elements.filter(function (element) {
      if (!initializedElements)
        return element.dataset.flowMotionInitialized !== "true";
      return !initializedElements.has(element);
    });
  }

  function mark(elements) {
    elements.forEach(function (element) {
      if (initializedElements) initializedElements.add(element);
      element.dataset.flowMotionInitialized = "true";
    });
  }

  function preset(name) {
    return (presets && presets.presets[name]) || presets.presets.subtle;
  }

  function vars(name, overrides) {
    var chosen = preset(name);
    var tokens = presets.tokens;
    var result = {
      duration: tokens.duration[chosen.duration],
      ease: tokens.ease[chosen.ease],
    };
    return Object.assign(result, overrides || {});
  }

  function clear() {
    return "transform,opacity,visibility";
  }

  function fromTo(elements, from, to) {
    if (!canAnimate() || !elements.length) return null;
    return window.gsap.fromTo(
      elements,
      from,
      Object.assign({ clearProps: clear(), overwrite: "auto" }, to),
    );
  }

  function enterPage(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var groups = [
      {
        selector: '[data-motion="header"]',
        preset: "subtle",
        from: { autoAlpha: 0, y: -4 },
      },
      {
        selector: '[data-motion="toolbar"]',
        preset: "subtle",
        from: { autoAlpha: 0, y: -4 },
      },
      {
        selector: '[data-motion="sidebar"]',
        preset: "sidebar",
        from: { autoAlpha: 0, x: -8 },
      },
      {
        selector: '[data-motion="section"]',
        preset: "card",
        from: { autoAlpha: 0, y: 8 },
      },
    ];
    var timeline = canAnimate()
      ? window.gsap.timeline({ defaults: { overwrite: "auto" } })
      : null;

    groups.forEach(function (group, index) {
      var elements = unanimated(
        asElements(scope.querySelectorAll(group.selector)),
      );
      if (!elements.length) return;
      mark(elements);
      if (!timeline) return;
      timeline.fromTo(
        elements,
        group.from,
        vars(group.preset, { autoAlpha: 1, x: 0, y: 0, clearProps: clear() }),
        index === 0 ? 0 : "-=0.17",
      );
    });
    return timeline;
  }

  function enterItems(container, options) {
    options = options || {};
    var root = container && container.querySelectorAll ? container : document;
    var items = options.items
      ? asElements(options.items)
      : asElements(root.querySelectorAll("[data-motion-item]"));
    if (!items.length) return null;
    var initial = Boolean(options.initial);
    var chosen = preset(options.preset || "card");
    var duration = initial
      ? presets.tokens.duration.slow
      : presets.tokens.duration.fast;
    var distance = initial
      ? presets.tokens.distance.md
      : presets.tokens.distance.xs;
    var amount = initial
      ? presets.tokens.stagger.normal
      : presets.tokens.stagger.tight;

    return fromTo(
      items,
      { autoAlpha: 0, y: distance, scale: initial ? 0.985 : 0.99 },
      {
        autoAlpha: 1,
        y: 0,
        scale: 1,
        duration: duration,
        ease: presets.tokens.ease[chosen.ease],
        stagger: { amount: amount, from: "start" },
      },
    );
  }

  function reveal(element, options) {
    options = options || {};
    var elements = asElements(element);
    return fromTo(
      elements,
      { autoAlpha: 0, y: presets.tokens.distance.xs },
      vars(options.preset || "subtle", { autoAlpha: 1, y: 0 }),
    );
  }

  function pop(element, options) {
    options = options || {};
    var elements = asElements(element);
    if (!canAnimate() || !elements.length) return null;
    return window.gsap.fromTo(
      elements,
      { scale: preset(options.preset || "emphasized").scale || 0.985 },
      vars(options.preset || "emphasized", {
        scale: 1,
        clearProps: "transform",
      }),
    );
  }

  function openModal(element, options) {
    options = options || {};
    var elements = asElements(element);
    return fromTo(
      elements,
      { autoAlpha: 0, y: presets.tokens.distance.sm, scale: 0.98 },
      vars(options.preset || "modal", { autoAlpha: 1, y: 0, scale: 1 }),
    );
  }

  function closeModal(element, options) {
    options = options || {};
    var elements = asElements(element);
    if (!canAnimate() || !elements.length) return null;
    return window.gsap.to(
      elements,
      vars(options.preset || "modal", {
        autoAlpha: 0,
        y: 4,
        scale: 0.99,
        clearProps: clear(),
        onComplete: options.onComplete,
      }),
    );
  }

  function init(root) {
    if (!initialized) {
      initialized = true;
      document.documentElement.classList.add("flow-motion-ready");
    }
    return enterPage(root);
  }

  window.FlowMotion = Object.freeze({
    init: init,
    enterPage: enterPage,
    enterItems: enterItems,
    reveal: reveal,
    pop: pop,
    openModal: openModal,
    closeModal: closeModal,
    canAnimate: canAnimate,
  });
})(window, document);
