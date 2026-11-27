/* Shared presentation for the delivery cards in Entregas and Dashboard/obra. */
(function () {
  'use strict';
  const previousProgress = new Map();
  let tooltip, activeTarget;

  function readyIndicator(progress, compact = false) {
    if (!progress.ready) return '';
    const label = `${progress.ready} ${progress.ready === 1 ? 'pronta' : 'prontas'} para envio`;
    return `<span class="delivery-ready-indicator${compact ? ' is-compact' : ''}" title="${label}" aria-label="${label}"><span class="delivery-package-icon" aria-hidden="true"><i class="fa-solid fa-box"></i><i class="fa-solid fa-check"></i></span><strong>${progress.ready}</strong>${compact ? '' : `<span>${progress.ready === 1 ? 'pronta' : 'prontas'} para envio</span>`}</span>`;
  }

  function progressMarkup(progress) {
    const text = `Entregues: ${progress.delivered}; Prontas para envio: ${progress.ready}; Demais pendências: ${progress.other}`;
    return `<div class="delivery-progress" tabindex="0" role="progressbar" aria-label="Progresso das imagens da entrega" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${progress.pct}" aria-valuetext="${text}" data-delivered="${progress.delivered}" data-ready="${progress.ready}" data-other="${progress.other}"><div class="progress-entrega delivery-progress-track" aria-hidden="true"><span class="delivery-progress-segment is-delivered" style="width:${progress.deliveredPercent}%"></span><span class="delivery-progress-segment is-ready" style="width:${progress.readyPercent}%"></span><span class="delivery-progress-segment is-other" style="width:${progress.otherPercent}%"></span></div></div>`;
  }

  function animateProgress(card, progress, id) {
    const previous = previousProgress.get(String(id));
    if (previous && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
      for (const [type, key] of [['delivered','deliveredPercent'], ['ready','readyPercent'], ['other','otherPercent']]) {
        if (previous[key] === progress[key]) continue;
        card.querySelector(`.delivery-progress-segment.is-${type}`)?.animate(
          [{width:`${previous[key]}%`}, {width:`${progress[key]}%`}],
          {duration:250, easing:'ease-out'},
        );
      }
    }
    previousProgress.set(String(id), progress);
  }

  // Use Flow's floating progress-tooltip presentation (Render/FlowReview),
  // with one instance and the same surface/text/border tokens as its card.
  function hideTooltip() {
    if (!tooltip) return;
    if (tooltip.matches(':popover-open')) tooltip.hidePopover();
    tooltip.hidden = true;
    activeTarget?.removeAttribute('aria-describedby');
    activeTarget = null;
  }
  function positionTooltip() {
    if (!activeTarget?.isConnected) return hideTooltip();
    const rect = activeTarget.getBoundingClientRect();
    const bounds = tooltip.getBoundingClientRect();
    const left = Math.max(8, Math.min(rect.left + rect.width / 2 - bounds.width / 2, innerWidth - bounds.width - 8));
    const top = rect.top >= bounds.height + 12 ? rect.top - bounds.height - 7 : rect.bottom + 7;
    tooltip.style.left = `${Math.round(left)}px`;
    tooltip.style.top = `${Math.round(Math.min(top, innerHeight - bounds.height - 8))}px`;
  }
  function showTooltip(target) {
    if (activeTarget === target) return;
    hideTooltip();
    if (!tooltip) {
      tooltip = document.createElement('div');
      tooltip.id = 'deliveryProgressTooltip';
      tooltip.className = 'deadline-progress-tooltip delivery-progress-tooltip';
      tooltip.setAttribute('role','tooltip');
      tooltip.setAttribute('popover','manual');
    }
    // Inherit the exact active card theme, including the Central's scoped palette.
    (target.closest('.delivery-central') || document.body).append(tooltip);
    tooltip.innerHTML = `<span><i class="is-delivered" aria-hidden="true"></i>Azul — Entregues: ${Number(target.dataset.delivered)}</span><span><i class="is-ready" aria-hidden="true"></i>Verde — Prontas para envio: ${Number(target.dataset.ready)}</span><span><i class="is-other" aria-hidden="true"></i>Cinza — Demais pendências: ${Number(target.dataset.other)}</span>`;
    activeTarget = target;
    target.setAttribute('aria-describedby', tooltip.id);
    tooltip.hidden = false;
    tooltip.showPopover?.();
    positionTooltip();
  }
  document.addEventListener('pointerover', event => {
    const target = event.target.closest?.('.delivery-progress');
    if (target) showTooltip(target);
  });
  document.addEventListener('pointerout', event => {
    const target = event.target.closest?.('.delivery-progress');
    if (target && !target.contains(event.relatedTarget)) hideTooltip();
  });
  document.addEventListener('focusin', event => {
    const target = event.target.closest?.('.delivery-progress');
    if (target) showTooltip(target);
  });
  document.addEventListener('focusout', event => {
    if (event.target.closest?.('.delivery-progress')) hideTooltip();
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') hideTooltip(); });
  document.addEventListener('click', hideTooltip);
  window.addEventListener('scroll', hideTooltip, true);
  window.addEventListener('resize', hideTooltip);
  window.FlowDeliveryCardUI = {readyIndicator, progressMarkup, animateProgress, hideTooltip};
})();
