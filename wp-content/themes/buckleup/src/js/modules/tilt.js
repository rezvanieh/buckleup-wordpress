// Hero 3D mouse-tilt — the source Hero.tsx tilts the hero card with framer-motion
// springs:
//   container: perspective 1000px (lg: only)
//   xPct = mouseX/width - 0.5 ; yPct = mouseY/height - 0.5
//   rotateX = map(yPct, [-0.5,0.5] -> [12deg, -12deg])
//   rotateY = map(xPct, [-0.5,0.5] -> [-12deg, 12deg])
//   smoothed with useSpring; resets on mouse leave.
//
// We reproduce the same mapping and add a lightweight spring via lerp on rAF.
// Desktop-only (matches lg:) and disabled under prefers-reduced-motion.

import { prefersReducedMotion } from '../lib/motion-prefs.js';

const MAX_DEG = 12;
const LG_MIN = 1024; // Tailwind lg breakpoint — source gates the tilt at lg:
const SPRING = 0.12; // lerp factor toward target (approximates useSpring)

export function initHeroTilt(root = document) {
  const container = root.querySelector('[data-tilt]');
  if (!container) return;
  const card = container.querySelector('[data-tilt-card]') || container.firstElementChild;
  if (!card) return;

  if (prefersReducedMotion()) return;

  container.style.perspective = '1000px';
  card.style.transformStyle = 'preserve-3d';

  let targetX = 0;
  let targetY = 0;
  let curX = 0;
  let curY = 0;
  let raf = null;
  let active = false;

  const enabled = () => window.matchMedia(`(min-width: ${LG_MIN}px)`).matches;

  const render = () => {
    curX += (targetX - curX) * SPRING;
    curY += (targetY - curY) * SPRING;
    card.style.transform = `rotateX(${curY}deg) rotateY(${curX}deg)`;
    if (Math.abs(targetX - curX) > 0.01 || Math.abs(targetY - curY) > 0.01 || active) {
      raf = requestAnimationFrame(render);
    } else {
      card.style.transform = '';
      raf = null;
    }
  };

  const start = () => {
    if (raf === null) raf = requestAnimationFrame(render);
  };

  container.addEventListener('mousemove', (e) => {
    if (!enabled()) return;
    active = true;
    const rect = container.getBoundingClientRect();
    const xPct = (e.clientX - rect.left) / rect.width - 0.5;
    const yPct = (e.clientY - rect.top) / rect.height - 0.5;
    // rotateX from yPct [-0.5,0.5] -> [12,-12]; rotateY from xPct -> [-12,12]
    targetY = -(yPct * 2) * MAX_DEG; // rotateX value (around X axis)
    targetX = (xPct * 2) * MAX_DEG; // rotateY value (around Y axis)
    start();
  });

  container.addEventListener('mouseleave', () => {
    active = false;
    targetX = 0;
    targetY = 0;
    start();
  });
}
