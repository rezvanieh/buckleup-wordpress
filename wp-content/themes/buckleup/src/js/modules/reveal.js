// Scroll reveals — the ~48 framer-motion fade-in-up scroll animations from the
// source landing sections, reproduced with a native IntersectionObserver +
// CSS transitions (no Motion One dependency).
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

import { prefersReducedMotion } from '../lib/motion-prefs.js';

const DEFAULT_Y = 20;
const DEFAULT_DURATION = 0.5;
const DEFAULT_AMOUNT = 0.2;
const STAGGER_STEP = 0.05; // seconds — source uses index*0.05 (some index*0.1)
// The exact easing the Motion One port used (animate easing:[0.16,1,0.3,1]).
const EASE = 'cubic-bezier(0.16, 1, 0.3, 1)';

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
    // Set the initial (pre-reveal) state so there's no flash before it scrolls in.
    el.style.opacity = '0';
    el.style.transform = `translateY(${y}px)`;
    el.style.willChange = 'opacity, transform';
  });

  items.forEach((el) => {
    const duration = num(el, 'data-reveal-duration', DEFAULT_DURATION);
    const delay = num(el, 'data-reveal-delay', 0);
    const amount = num(el, 'data-reveal-amount', DEFAULT_AMOUNT);
    // Clamp to a valid IntersectionObserver threshold (matches Motion One's
    // numeric-amount → threshold mapping for all real values; 0.2 default).
    const threshold = Math.min(1, Math.max(0, amount));

    // One observer per element (mirrors the old per-element inView): each carries
    // its own threshold/duration/delay. isIntersecting + unobserve replicates
    // Motion One's inView with `once` (return-nothing → stop observing on enter).
    const observer = new IntersectionObserver((entries, obs) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        obs.unobserve(el); // once: true — reveal exactly once, then stop observing.
        // The initial (opacity:0 / translateY) state was already committed and
        // painted before this async callback, so setting the end state here
        // transitions rather than snapping — same as the old animate() call.
        el.style.transition =
          `opacity ${duration}s ${EASE} ${delay}s, transform ${duration}s ${EASE} ${delay}s`;
        el.style.opacity = '1';
        el.style.transform = 'translateY(0px)';
        // After the animation finishes: drop the will-change hint AND the inline
        // transition, so the element falls back to the global 150ms color
        // transition (matches Motion One's WAAPI, which never set el.style.transition).
        window.setTimeout(() => {
          el.style.willChange = 'auto';
          el.style.transition = '';
        }, (delay + duration) * 1000 + 50);
      });
    }, { threshold });

    observer.observe(el);
  });
}
