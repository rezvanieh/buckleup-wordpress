// Animation/motion freeze for stable screenshots.
//
// Both sites gate their motion behind prefers-reduced-motion (the live Next app uses
// framer-motion; the WP build uses Motion One + a FLIP module per PLAN.md §3). We:
//   1. Emulate prefers-reduced-motion: reduce  (handled in playwright.config projects).
//   2. Add an init script that, before any app JS runs, injects CSS forcing every
//      transition/animation to a zeroed, completed state and disabling smooth scroll.
//   3. Set a window flag the app can read to skip JS-driven motion (the WP theme should
//      honor window.__BUCKLEUP_TEST__ / a `?freeze=1`-style hook; harmless on the live
//      site, which simply ignores it).
//
// applyFreeze(page) must be called BEFORE page.goto() so the init script registers.

const FREEZE_STYLE = `
*, *::before, *::after {
  animation-duration: 0s !important;
  animation-delay: 0s !important;
  animation-iteration-count: 1 !important;
  transition-duration: 0s !important;
  transition-delay: 0s !important;
  scroll-behavior: auto !important;
  caret-color: transparent !important;
}
html { scroll-behavior: auto !important; }
/* framer-motion / Motion One reveal elements sometimes start at opacity:0 + translateY;
   force them to their settled visible state so masked-off baselines don't capture mid-reveal. */
[style*="opacity: 0"], [style*="opacity:0"] { opacity: 1 !important; }
`;

async function applyFreeze(page) {
  await page.addInitScript(() => {
    // App-readable flag: a freeze-aware build can short-circuit its motion layer.
    window.__BUCKLEUP_TEST__ = true;
    window.__BUCKLEUP_FREEZE_MOTION__ = true;
  });
  await page.addInitScript((css) => {
    const inject = () => {
      const style = document.createElement('style');
      style.setAttribute('data-buckleup-freeze', '');
      style.textContent = css;
      (document.head || document.documentElement).appendChild(style);
    };
    if (document.head) inject();
    else document.addEventListener('DOMContentLoaded', inject);
  }, FREEZE_STYLE);
}

module.exports = { applyFreeze, FREEZE_STYLE };
