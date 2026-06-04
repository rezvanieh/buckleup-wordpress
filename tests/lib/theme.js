// Deterministic theme forcing across both sites.
//
// Live site: next-themes, which persists to localStorage under the key "theme"
//   ("light" | "dark" | "system") and toggles class="dark" on <html>.
// WP build (PLAN.md §3): an inline pre-paint script reading a cookie + localStorage,
//   also resolving "system" and toggling .dark on <html> (same contract, no FOUC).
//
// Strategy: BEFORE navigation we (a) seed localStorage so the app's own pre-paint
// script resolves the theme with no flash, (b) set a matching cookie for the WP path,
// and (c) emulate the OS color scheme so a "system" fallback still lands correctly.
// AFTER load we ASSERT the resolved theme (presence/absence of the .dark class) so a
// silently-failed toggle becomes a test failure rather than a wrong-mode screenshot.
//
// preparePage(page, theme) must run before page.goto().

async function preparePage(page, theme /* 'light' | 'dark' */) {
  const isDark = theme === 'dark';
  await page.addInitScript((t) => {
    try {
      localStorage.setItem('theme', t);                 // next-themes + WP localStorage key
      localStorage.setItem('buckleup-theme', t);         // defensive alt key
    } catch (e) { /* storage may be unavailable on first paint; cookie still covers WP */ }
    document.cookie = `theme=${t}; path=/; max-age=31536000; SameSite=Lax`;
  }, theme);
  // Emulate OS scheme so any "system"-resolving path matches the requested theme.
  await page.emulateMedia({ colorScheme: isDark ? 'dark' : 'light' });
}

// Verify the page actually rendered in the requested theme. Returns the resolved theme.
async function assertTheme(page, theme) {
  const hasDarkClass = await page.evaluate(() =>
    document.documentElement.classList.contains('dark')
  );
  const resolved = hasDarkClass ? 'dark' : 'light';
  if (resolved !== theme) {
    throw new Error(
      `Theme mismatch: requested "${theme}" but <html> resolved to "${resolved}" ` +
      `(html class="${await page.evaluate(() => document.documentElement.className)}")`
    );
  }
  return resolved;
}

// Detect whether a togglable theme toggle control is present (used by functional checks
// in Task #9; not required for baseline capture).
async function hasThemeToggle(page) {
  return page.evaluate(() => {
    const sel = "[data-theme-toggle], [data-theme-set], [aria-label*='theme' i], [aria-label*='dark' i], button:has(svg[class*='sun' i]), button:has(svg[class*='moon' i])";
    return !!document.querySelector(sel);
  });
}

// Wait out the theme's intended 150ms base-layer `transition: background-color` so a
// computed-style read or screenshot never captures the mid-transition "from" color. Because
// we use the production path (storage preset BEFORE nav → the inline no-flash <head> script
// applies .dark pre-paint under an `html.no-transitions` guard), dark renders with no
// transition artifact — but a runtime toggle WOULD animate, so we (a) wait for the
// no-transitions guard to clear, then (b) settle comfortably past 150ms. loadStable already
// settles far longer; this is for paths (e.g. axe) that measure soon after navigation.
async function settleThemeTransition(page) {
  await page.waitForFunction(
    () => !document.documentElement.classList.contains('no-transitions'),
    { timeout: 5000 }
  ).catch(() => {});
  await page.waitForTimeout(250); // > the 150ms base-layer transition
}

module.exports = { preparePage, assertTheme, hasThemeToggle, settleThemeTransition };
