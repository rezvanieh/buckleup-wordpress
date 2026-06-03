// Throwaway probe: confirm theme.js can force light AND dark on a target before a full run.
const { chromium } = require('@playwright/test');
const { baseUrlFor } = require('../lib/sites');
const { preparePage, assertTheme, hasThemeToggle } = require('../lib/theme');
const { applyFreeze } = require('../lib/freeze');

(async () => {
  const target = process.argv.includes('--target') ? process.argv[process.argv.indexOf('--target') + 1] : 'live';
  const base = baseUrlFor(target);
  const browser = await chromium.launch();
  for (const theme of ['light', 'dark']) {
    const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await ctx.newPage();
    await applyFreeze(page);
    await preparePage(page, theme);
    await page.goto(base, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2500); // let hydration + next-themes run
    const cls = await page.evaluate(() => document.documentElement.className);
    const toggle = await hasThemeToggle(page);
    let ok = '?';
    try { await assertTheme(page, theme); ok = 'PASS'; } catch (e) { ok = 'FAIL: ' + e.message; }
    console.log(`theme=${theme}  htmlClass="${cls}"  toggleFound=${toggle}  -> ${ok}`);
    await ctx.close();
  }
  await browser.close();
})();
