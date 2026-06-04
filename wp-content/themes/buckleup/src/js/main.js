// BuckleUp theme JS entry — the vanilla-JS interaction layer that replaces the
// source app's framer-motion / Radix behavior with no behavior change. Every
// module is prefers-reduced-motion aware (see lib/motion-prefs.js) and driven by
// data-* attributes the PHP partials/patterns emit, so markup stays declarative.
//
// Modules:
//   theme.js     — light/dark/system toggle, logo swap, no-flash (paired w/ the
//                  blocking inline <head> script in functions.php)
//   reveal.js    — Motion One inView fade-in-up scroll reveals (+ stagger)
//   magic-move.js— FLIP "magic-move" tab/nav pill indicator
//   navbar.js    — scroll-aware header (data-scrolled) + mobile menu
//   tilt.js      — hero 3D mouse-tilt card (lg only)
//   lightbox.js  — graduates shared-element lightbox
//   overlays.js  — Dialog / DropdownMenu / Select (data-state/data-side)
//   forms.js     — Switch + FAQ accordion

import { initTheme } from './modules/theme.js';
import { initReveals } from './modules/reveal.js';
import { initMagicTabs } from './modules/magic-move.js';
import { initNavbar } from './modules/navbar.js';
import { initHeroTilt } from './modules/tilt.js';
import { initLightbox } from './modules/lightbox.js';
import { initOverlays } from './modules/overlays.js';
import { initForms } from './modules/forms.js';
import { initAuth } from './modules/auth.js';
import { initConsole } from './modules/console.js';

function boot() {
  // Theme first so the resolved class/logo are correct before anything paints
  // interactively (the inline head script already set .dark pre-paint).
  initTheme();
  initNavbar();
  initMagicTabs();
  initHeroTilt();
  initLightbox();
  initOverlays();
  initForms();
  initAuth();
  initConsole();
  initReveals();
}

// Contact form: NO JS by design (v1). The form is a plain server-rendered
// admin-post POST that redirects back to /contact?contact=success|error; the PHP
// reads $_GET['contact'] to show the success/error banner. There was a removed
// AJAX enhancement, but the plugin exposes only the admin-post path (no REST), so
// the native submit is the single, verified data path. Honeypot stays in markup.

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}

// Drop the flash-guard once loaded so subsequent theme switches animate via the
// 150ms global color transition. The inline <head> script adds html.no-transitions
// before first paint; remove it on the next frame after load.
window.addEventListener('load', () => {
  requestAnimationFrame(() => {
    requestAnimationFrame(() => document.documentElement.classList.remove('no-transitions'));
  });
});
