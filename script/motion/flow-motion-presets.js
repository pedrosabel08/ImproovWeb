/* FlowMotion tokens. Keep page modules on these controlled values. */
(function (window) {
  "use strict";

  window.FlowMotionPresets = Object.freeze({
    tokens: Object.freeze({
      duration: Object.freeze({ fast: 0.2, normal: 0.36, slow: 0.52 }),
      distance: Object.freeze({ xs: 4, sm: 8, md: 12 }),
      stagger: Object.freeze({ tight: 0.12, normal: 0.22, relaxed: 0.32 }),
      ease: Object.freeze({
        enter: "power3.out",
        exit: "power2.in",
        emphasized: "power2.out",
      }),
    }),
    presets: Object.freeze({
      subtle: Object.freeze({
        duration: "fast",
        distance: "xs",
        ease: "enter",
      }),
      card: Object.freeze({
        duration: "normal",
        distance: "sm",
        ease: "enter",
      }),
      sidebar: Object.freeze({
        duration: "normal",
        distance: "sm",
        ease: "enter",
      }),
      modal: Object.freeze({
        duration: "fast",
        distance: "sm",
        ease: "enter",
        scale: 0.98,
      }),
      emphasized: Object.freeze({
        duration: "fast",
        ease: "emphasized",
        scale: 0.985,
      }),
    }),
  });
})(window);
