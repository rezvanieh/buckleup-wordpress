// Canonical "load a page into a stable, comparable state" routine, shared by baseline
// capture and the visual spec so the live snapshot and the candidate screenshot are
// produced identically. Steps:
//   1. freeze motion + set test flag (before nav)
//   2. force the requested theme (before nav, so no flash) + emulate OS scheme
//   3. navigate, wait for network to settle and fonts to finish loading
//   4. assert the theme actually resolved
//   5. scroll the full height to trigger any lazy-loaded/in-view content, then return to top
//   6. give late layout (web fonts, images) a brief settle
//
// Returns the resolved theme. Throws on theme mismatch so a bad screenshot can't pass.

const { applyFreeze } = require('./freeze');
const { preparePage, assertTheme } = require('./theme');

async function loadStable(page, url, theme) {
  await applyFreeze(page);
  await preparePage(page, theme);

  await page.goto(url, { waitUntil: 'domcontentloaded' });
  // Settle network; the live Next.js site streams RSC and may never reach a true idle, so
  // cap the wait and fall through rather than hang the whole capture.
  await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
  // Wait for web fonts but never block forever (document.fonts.ready can stay pending).
  await page.evaluate(() => {
    if (!document.fonts) return Promise.resolve();
    return Promise.race([
      document.fonts.ready,
      new Promise((res) => setTimeout(res, 4000)),
    ]);
  }).catch(() => {});

  await assertTheme(page, theme);

  // Trigger lazy/in-view content, then settle at top for full-page capture.
  await autoScroll(page);
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(400);
  return theme;
}

async function autoScroll(page) {
  await page.evaluate(async () => {
    await new Promise((resolve) => {
      let y = 0;
      const step = 600;
      let ticks = 0;
      const MAX_TICKS = 400; // hard cap (~20s @50ms) so a growing/animated page can't loop forever
      const timer = setInterval(() => {
        const max = document.body.scrollHeight;
        window.scrollTo(0, y);
        y += step;
        ticks += 1;
        if (y >= max || ticks >= MAX_TICKS) {
          clearInterval(timer);
          resolve();
        }
      }, 50);
    });
  });
  // Allow any newly-revealed images to decode, but never block on a stalled request.
  await page.evaluate(() => {
    const imgs = Array.from(document.images).filter((i) => !i.complete);
    const settle = Promise.all(
      imgs.map((i) => new Promise((res) => { i.onload = i.onerror = res; }))
    );
    return Promise.race([settle, new Promise((res) => setTimeout(res, 6000))]);
  }).catch(() => {});
}

module.exports = { loadStable, autoScroll };
