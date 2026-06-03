// Form-control behavior: Switch toggle + FAQ accordion height animation.
// Both flip the same data-state attributes the markup/CSS expect, and both
// respect prefers-reduced-motion (the accordion falls back to instant open via
// the native <details> toggle).

import { prefersReducedMotion } from '../lib/motion-prefs.js';

/* ------------------------------ Switch ----------------------------------- */
// A [data-switch] button with a [data-slot="switch-thumb"] child. Click flips
// data-state checked/unchecked + aria-checked; the CSS handles the thumb slide.

function initSwitches(root) {
  root.querySelectorAll('[data-switch]').forEach((sw) => {
    const thumb = sw.querySelector('[data-slot="switch-thumb"]');
    sw.addEventListener('click', () => {
      if (sw.hasAttribute('disabled')) return;
      const checked = sw.getAttribute('data-state') === 'checked';
      const next = checked ? 'unchecked' : 'checked';
      sw.setAttribute('data-state', next);
      sw.setAttribute('aria-checked', checked ? 'false' : 'true');
      if (thumb) thumb.setAttribute('data-state', next);
      sw.dispatchEvent(new CustomEvent('switch:change', { detail: { checked: !checked }, bubbles: true }));
    });
  });
}

/* --------------------------- FAQ accordion ------------------------------- */
// Native <details data-faq-item> handles open/close + keyboard for free. We add
// a smooth height animation on the panel and keep data-state in sync (the chevron
// rotation is pure CSS via group-open:). Under reduced motion we do nothing and
// let the browser's instant toggle stand.

function animatePanel(detail, opening) {
  const panel = detail.querySelector('[data-faq-panel]');
  if (!panel) return;

  if (prefersReducedMotion()) {
    detail.setAttribute('data-state', opening ? 'open' : 'closed');
    return;
  }

  const start = panel.getBoundingClientRect().height;
  // Let the DOM reflect the target open state, measure, then animate from->to.
  detail.setAttribute('data-state', opening ? 'open' : 'closed');
  const end = opening ? panel.scrollHeight : 0;

  panel.style.overflow = 'hidden';
  const anim = panel.animate(
    [{ height: `${start}px` }, { height: `${end}px` }],
    { duration: 240, easing: 'cubic-bezier(0.4, 0, 0.2, 1)' }
  );
  anim.onfinish = () => {
    panel.style.overflow = '';
    panel.style.height = '';
  };
}

function initFaq(root) {
  root.querySelectorAll('[data-faq-item]').forEach((detail) => {
    const summary = detail.querySelector('summary');
    if (!summary) return;

    summary.addEventListener('click', (e) => {
      if (prefersReducedMotion()) return; // native toggle is fine
      e.preventDefault();
      const isOpen = detail.hasAttribute('open');
      if (isOpen) {
        // Animate closed, THEN remove the open attribute.
        animatePanelClose(detail);
      } else {
        detail.setAttribute('open', '');
        animatePanel(detail, true);
      }
    });
  });
}

function animatePanelClose(detail) {
  const panel = detail.querySelector('[data-faq-panel]');
  if (!panel) {
    detail.removeAttribute('open');
    return;
  }
  const start = panel.getBoundingClientRect().height;
  panel.style.overflow = 'hidden';
  detail.setAttribute('data-state', 'closed');
  const anim = panel.animate(
    [{ height: `${start}px` }, { height: '0px' }],
    { duration: 220, easing: 'cubic-bezier(0.4, 0, 0.2, 1)' }
  );
  anim.onfinish = () => {
    detail.removeAttribute('open');
    panel.style.overflow = '';
    panel.style.height = '';
  };
}

export function initForms(root = document) {
  initSwitches(root);
  initFaq(root);
}
