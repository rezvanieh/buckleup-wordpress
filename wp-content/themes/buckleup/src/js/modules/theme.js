// Theme toggle — light / dark / system with no flash-of-wrong-theme.
//
// The blocking inline <head> script (functions.php) resolves and applies the
// stored choice (and html.no-transitions) BEFORE first paint. This module is the
// interactive half: it keeps the choice in localStorage AND a cookie (so a future
// server render could read it), reacts to OS changes when in "system", swaps the
// theme-aware logo, and reflects the active choice on any toggle UI.
//
// Storage key matches the inline script: 'buckleup-theme'.

const STORAGE_KEY = 'buckleup-theme';
const COOKIE = 'buckleup-theme';
const systemQuery = window.matchMedia('(prefers-color-scheme: dark)');

function getStored() {
  try {
    return localStorage.getItem(STORAGE_KEY) || 'system';
  } catch (e) {
    return 'system';
  }
}

function setStored(value) {
  try {
    localStorage.setItem(STORAGE_KEY, value);
  } catch (e) {
    /* private mode — cookie still set below */
  }
  // 1-year cookie so server-side rendering can honor the choice later.
  document.cookie = `${COOKIE}=${value}; path=/; max-age=31536000; samesite=lax`;
}

function resolve(value) {
  if (value === 'dark') return 'dark';
  if (value === 'light') return 'light';
  return systemQuery.matches ? 'dark' : 'light';
}

function apply(value) {
  const resolved = resolve(value);
  const el = document.documentElement;
  el.classList.toggle('dark', resolved === 'dark');
  el.style.colorScheme = resolved;
  swapLogos(resolved);
  reflectControls(value, resolved);
}

function swapLogos(resolved) {
  document.querySelectorAll('[data-logo]').forEach((img) => {
    const src = resolved === 'dark' ? img.getAttribute('data-logo-dark') : img.getAttribute('data-logo-light');
    if (src && img.getAttribute('src') !== src) img.setAttribute('src', src);
  });
}

function reflectControls(value, resolved) {
  // Two-state toggle buttons: update title/aria for the *next* action.
  document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
    const next = resolved === 'dark' ? 'light' : 'dark';
    btn.setAttribute('aria-label', `Switch to ${next} mode`);
    btn.setAttribute('title', `Switch to ${next} mode`);
    btn.setAttribute('data-resolved', resolved);
  });
  // 3-option chooser: mark the selected option.
  document.querySelectorAll('[data-theme-set]').forEach((btn) => {
    const selected = btn.getAttribute('data-theme-set') === value;
    btn.setAttribute('data-state', selected ? 'selected' : 'unselected');
    btn.setAttribute('aria-pressed', selected ? 'true' : 'false');
  });
  // Optional "currently displaying" indicator.
  document.querySelectorAll('[data-theme-current]').forEach((n) => {
    n.textContent = resolved;
  });
}

export function initTheme() {
  apply(getStored());

  // Two-state cycle (the navbar sun/moon button): dark <-> light, mirroring the
  // source's setTheme(theme === 'dark' ? 'light' : 'dark').
  document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const next = resolve(getStored()) === 'dark' ? 'light' : 'dark';
      setStored(next);
      apply(next);
    });
  });

  // Explicit 3-option chooser (light / dark / system).
  document.querySelectorAll('[data-theme-set]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const value = btn.getAttribute('data-theme-set');
      setStored(value);
      apply(value);
    });
  });

  // Follow OS changes while in "system".
  systemQuery.addEventListener('change', () => {
    if (getStored() === 'system') apply('system');
  });
}
