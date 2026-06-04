// Scroll reveals — the ~48 framer-motion fade-in-up scroll animations from the
// source landing sections, reproduced with Motion One's `inView`.
//
// Source pattern (e.g. Pricing.tsx, Features.tsx, BentoGrid.tsx):
//   initial={{ opacity: 0, y: 20 }}
//   whileInView={{ opacity: 1, y: 0 }}
//   viewport={{ once: true, amount: 0.2 }}
//   transition={{ duration: 0.5, delay: index * 0.05 }}
//
// Markup opts in with data-reveal on the element. A parent with
// data-reveal-stagger auto-assigns index*step delays to its data-reveal children
// (matching the source's per-index stagger). Honor prefers-reduced-motion by
// showing the end state immediately.

import { inView, animate, stagger } from 'motion';
import { prefersReducedMotion } from '../lib/motion-prefs.js';

const DEFAULT_Y = 20;
const DEFAULT_DURATION = 0.5;
const DEFAULT_AMOUNT = 0.2;
const STAGGER_STEP = 0.05; // seconds — source uses index*0.05 (some index*0.1)

function num(el, attr, fallback) {
  const v = el.getAttribute(attr);
  return v === null || v === '' ? fallback : parseFloat(v);
}

export function initReveals(root = document) {
  const reduced = prefersReducedMotion();

  // Assign stagger delays to children of any data-reveal-stagger container.
  root.querySelectorAll('[data-reveal-stagger]').forEach((group) => {
    const step = num(group, 'data-reveal-stagger', STAGGER_STEP) || STAGGER_STEP;
    group.querySelectorAll('[data-reveal]').forEach((child, i) => {
      if (!child.hasAttribute('data-reveal-delay')) {
        child.setAttribute('data-reveal-delay', String(i * step));
      }
    });
  });

  const items = root.querySelectorAll('[data-reveal]');

  if (reduced) {
    // No animation: ensure everything is visible and untransformed.
    items.forEach((el) => {
      el.style.opacity = '1';
      el.style.transform = 'none';
    });
    return;
  }

  items.forEach((el) => {
    const y = num(el, 'data-reveal-y', DEFAULT_Y);
    // Set the initial (pre-reveal) state so there's no flash before inView fires.
    el.style.opacity = '0';
    el.style.transform = `translateY(${y}px)`;
    el.style.willChange = 'opacity, transform';
  });

  items.forEach((el) => {
    const duration = num(el, 'data-reveal-duration', DEFAULT_DURATION);
    const delay = num(el, 'data-reveal-delay', 0);
    const amount = num(el, 'data-reveal-amount', DEFAULT_AMOUNT);

    inView(
      el,
      () => {
        animate(
          el,
          { opacity: [0, 1], transform: ['translateY(' + num(el, 'data-reveal-y', DEFAULT_Y) + 'px)', 'translateY(0px)'] },
          { duration, delay, easing: [0.16, 1, 0.3, 1] }
        ).finished.then(() => {
          el.style.willChange = 'auto';
        });
        // once: true — return nothing so inView stops observing after first entry.
      },
      { amount }
    );
  });
}

// Re-export stagger in case a caller wants Motion One's native stagger directly.
export { stagger };
