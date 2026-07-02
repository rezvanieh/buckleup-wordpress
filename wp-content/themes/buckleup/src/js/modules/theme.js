// Theme — the site is LIGHT-ONLY.
//
// Dark mode was removed at the client's request (it was broken and unwanted). This
// module now FORCES light on every load and proactively clears any previously-
// stored "dark" preference (localStorage + cookie) so returning visitors who had
// "dark" stored aren't stranded — there is no toggle left to escape it.
//
// The blocking inline <head> script (functions.php) already forces light before
// first paint; this module reinforces it after hydration (light logo swap + storage
// clear) and neutralizes any lingering [data-theme-toggle] / [data-theme-set]
// controls (e.g. the console settings choosers) so they can never re-apply dark.
//
// Storage key/cookie matches the inline script: 'buckleup-theme'.

const STORAGE_KEY = 'buckleup-theme';
const COOKIE = 'buckleup-theme';

function clearStored() {
  try {
    localStorage.removeItem(STORAGE_KEY);
  } catch (e) {
    /* private mode — cookie still cleared below */
  }
  // Expire the cookie so a stale, server-readable "dark" can't resurface.
  document.cookie = `${COOKIE}=; path=/; max-age=0`;
}

function swapLogos() {
  // Always resolve to the light logo.
  document.querySelectorAll('[data-logo]').forEach((img) => {
    const src = img.getAttribute('data-logo-light');
    if (src && img.getAttribute('src') !== src) img.setAttribute('src', src);
  });
}

function forceLight() {
  const el = document.documentElement;
  el.classList.remove('dark');
  el.style.colorScheme = 'light';
  clearStored();
  swapLogos();
  // Reflect "light" on any theme chooser still in the DOM (console settings pages).
  document.querySelectorAll('[data-theme-set]').forEach((btn) => {
    const selected = btn.getAttribute('data-theme-set') === 'light';
    btn.setAttribute('data-state', selected ? 'selected' : 'unselected');
    btn.setAttribute('aria-pressed', selected ? 'true' : 'false');
  });
}

export function initTheme() {
  forceLight();

  // Any lingering theme controls are inert: a click just re-forces light and can
  // never apply .dark. (The navbar + console sun/moon toggles were removed; the
  // console settings [data-theme-set] chooser is handled separately by the lead.)
  document
    .querySelectorAll('[data-theme-toggle], [data-theme-set]')
    .forEach((btn) => btn.addEventListener('click', (e) => {
      if (e && typeof e.preventDefault === 'function') e.preventDefault();
      forceLight();
    }));
}
