// Shared prefers-reduced-motion gate. Every motion module imports `reduced` and
// bails (or applies the end-state instantly) when the user opts out, matching the
// source app's framer-motion behavior under reduced motion.

export const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

export function prefersReducedMotion() {
  return reducedMotionQuery.matches;
}
