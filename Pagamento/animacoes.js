/* Presentation only: backend values remain authoritative throughout each tween. */
(() => {
  const engine = window.gsap;
  const roots = new Map();
  const numbers = new WeakMap();
  const activeNumbers = new Set();
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  const integer = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 0 });
  const decimal = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });
  const format = (value, kind, suffix = '') => (kind === 'money' ? currency.format(Math.round(value) / 100) : kind === 'percent' ? `${decimal.format(value)}%` : integer.format(Math.round(value))) + suffix;

  function setNumber(element, value, kind = 'count', suffix = '', restart = false) {
    if (!element || !Number.isFinite(value)) return;
    const key = `${kind}:${value}:${suffix}`;
    const previous = numbers.get(element);
    if (!restart && previous?.key === key) {
      // Legacy loading states can reset the text even when the backend total is unchanged.
      if (!activeNumbers.has(previous)) element.textContent = previous.final;
      return;
    }
    previous?.tween?.kill();
    if (previous) activeNumbers.delete(previous);
    const final = format(value, kind, suffix);
    const state = { element, value: restart ? 0 : (previous?.value ?? 0), key, final, tween: null };
    numbers.set(element, state);
    // Assistive technology reads the final figure, not every intermediate animation frame.
    element.setAttribute('role', 'img');
    element.setAttribute('aria-label', final);
    if (!engine || reducedMotion.matches || value === state.value) {
      state.value = value;
      element.textContent = final;
      return;
    }
    activeNumbers.add(state);
    element.textContent = format(state.value, kind, suffix);
    state.tween = engine.to(state, {
      value, duration: .85, ease: 'power2.out', overwrite: 'auto',
      onUpdate: () => { element.textContent = format(state.value, kind, suffix); },
      onComplete: () => { element.textContent = final; activeNumbers.delete(state); },
    });
  }

  function finishNumbers(root) {
    for (const state of activeNumbers) {
      if (root && !root.contains(state.element)) continue;
      state.tween?.progress(1).kill();
      state.element.textContent = state.final;
      activeNumbers.delete(state);
    }
  }

  function stop(root) {
    roots.get(root)?.revert();
    roots.delete(root);
    finishNumbers(root);
  }

  function animate(root) {
    if (!root || !engine) return;
    stop(root);
    const media = engine.matchMedia();
    roots.set(root, media);
    media.add({ motion: '(prefers-reduced-motion: no-preference)', reduce: '(prefers-reduced-motion: reduce)' }, context => {
      if (context.conditions.reduce) return;
      root.querySelectorAll('[data-payment-number]').forEach(element => {
        setNumber(element, Number(element.dataset.paymentNumber), element.dataset.numberKind, element.dataset.numberSuffix || '', true);
      });
      engine.fromTo(root.querySelectorAll('.overview-track > span'), { scaleX: 0, transformOrigin: 'left center' }, {
        scaleX: 1, duration: .9, ease: 'power2.out', stagger: { amount: .18 }, clearProps: 'transform',
      });
      engine.fromTo(root.querySelectorAll('.overview-segment'), { clipPath: 'inset(0 100% 0 0)' }, {
        clipPath: 'inset(0 0% 0 0)', duration: .95, ease: 'power2.out', clearProps: 'clipPath',
      });
      engine.fromTo(root.querySelectorAll('.overview-kpis, .overview-grid'), { autoAlpha: .4 }, {
        autoAlpha: 1, duration: .35, ease: 'power1.out', clearProps: 'opacity,visibility',
      });
      return () => finishNumbers(root);
    }, root);
  }

  reducedMotion.addEventListener('change', () => { if (reducedMotion.matches) finishNumbers(); });
  window.addEventListener('pagehide', () => { for (const root of roots.keys()) stop(root); finishNumbers(); });
  window.pagamentoMotion = { animate, stop, setNumber, format };
})();
