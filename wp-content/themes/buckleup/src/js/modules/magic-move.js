// FLIP "magic-move" — replaces framer-motion's shared-layout `layoutId` indicator
// (the pill/bubble that glides between the active tab/nav item) with a First/Last/
// Invert/Play transform using the Web Animations API.
//
// Source: custom-tabs.tsx renders two stacked spans (layoutId "bubble" +
// "bubble-bg") inside the active tab; framer-motion animates them to the newly
// active tab with `{ type: "spring", bounce: 0.2, duration: 0.6 }`. Here we keep
// the same two markup spans (data-bubble / data-bubble-bg) and, on tab change,
// move them to the new active button while FLIP-animating from their old box.
//
// Reduced motion: skip the transform, just relocate instantly.

import { prefersReducedMotion } from '../lib/motion-prefs.js';

// Spring-ish easing approximating framer-motion spring(bounce 0.2). A slight
// overshoot cubic-bezier reads close to the original at 600ms.
const SPRING_EASING = 'cubic-bezier(0.34, 1.32, 0.5, 1)';
const SPRING_DURATION = 600;

/**
 * Move a magic indicator element to a new host, FLIP-animating from its current
 * on-screen box. The element is expected to be absolutely positioned filling its
 * host (inset-0), so only translate/scale differences matter.
 */
function flipMove(indicator, fromRect, toHost) {
  toHost.appendChild(indicator);
  if (prefersReducedMotion()) {
    return;
  }
  const toRect = indicator.getBoundingClientRect();
  const dx = fromRect.left - toRect.left;
  const dy = fromRect.top - toRect.top;
  const sx = toRect.width ? fromRect.width / toRect.width : 1;
  const sy = toRect.height ? fromRect.height / toRect.height : 1;

  indicator.animate(
    [
      { transform: `translate(${dx}px, ${dy}px) scale(${sx}, ${sy})` },
      { transform: 'translate(0, 0) scale(1, 1)' },
    ],
    { duration: SPRING_DURATION, easing: SPRING_EASING, fill: 'none' }
  );
}

/**
 * Wire a single CustomTabs group (a [data-tabs] element). Clicking a tab updates
 * data-state/aria-selected and glides the bubble(s) into the new active tab.
 *
 * @param {HTMLElement} group
 * @param {(id:string)=>void} [onChange] optional callback (e.g. to switch panels)
 */
export function initTabsGroup(group, onChange) {
  const buttons = Array.from(group.querySelectorAll('[data-tab]'));
  if (!buttons.length) return;

  group.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-tab]');
    if (!btn || !group.contains(btn) || btn.getAttribute('data-state') === 'active') return;

    const prev = buttons.find((b) => b.getAttribute('data-state') === 'active');
    const bubble = group.querySelector('[data-bubble]');
    const bubbleBg = group.querySelector('[data-bubble-bg]');

    // Capture the indicators' First boxes before they move.
    const fromBubble = bubble ? bubble.getBoundingClientRect() : null;
    const fromBubbleBg = bubbleBg ? bubbleBg.getBoundingClientRect() : null;

    buttons.forEach((b) => {
      const active = b === btn;
      b.setAttribute('data-state', active ? 'active' : 'inactive');
      b.setAttribute('aria-selected', active ? 'true' : 'false');
      // Active label = primary-foreground on the solid primary pill (AA in both
      // themes); inactive = muted with a foreground hover. Mirrors the PHP markup.
      b.classList.toggle('text-primary-foreground', active);
      b.classList.toggle('text-muted-foreground', !active);
      b.classList.toggle('hover:text-foreground', !active);
    });

    if (bubble && fromBubble) flipMove(bubble, fromBubble, btn);
    if (bubbleBg && fromBubbleBg) flipMove(bubbleBg, fromBubbleBg, btn);

    if (typeof onChange === 'function') onChange(btn.getAttribute('data-tab'), btn, prev);
  });
}

/**
 * Initialize all tab groups in `root`. If a group has a [data-tab-panels]
 * sibling/descendant container, panels with data-tab-panel="{id}" are toggled.
 */
export function initMagicTabs(root = document) {
  root.querySelectorAll('[data-tabs]').forEach((group) => {
    const panelHost =
      document.querySelector(`[data-tab-panels="${group.getAttribute('data-tabs')}"]`) || null;

    initTabsGroup(group, (id) => {
      if (!panelHost) return;
      panelHost.querySelectorAll('[data-tab-panel]').forEach((p) => {
        const show = p.getAttribute('data-tab-panel') === id;
        p.hidden = !show;
        p.setAttribute('data-state', show ? 'active' : 'inactive');
      });
    });
  });
}
