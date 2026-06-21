// Visual capture of the practice / exam center for review.
// Usage: node bin/shoot-exam.js [nav|hub|category|exam|all]
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = process.env.BASE || 'http://localhost:8080';
const OUT = 'results/exam';
const HUB = '/icbc-class-4-knowledge-test';
const what = process.argv[2] || 'all';

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 });
  const page = await ctx.newPage();
  const go = async (path) => { await page.goto(`${BASE}${path}${path.includes('?') ? '&' : '?'}cb=${Date.now()}`, { waitUntil: 'networkidle', timeout: 60000 }); await page.waitForTimeout(700); };

  if (what === 'nav' || what === 'hub' || what === 'all') {
    await go('/');
    await page.screenshot({ path: `${OUT}/nav-closeup.png`, clip: { x: 700, y: 0, width: 740, height: 96 } });
    console.log('nav-closeup.png');
  }
  if (what === 'hub' || what === 'all') {
    await go(`${HUB}/`);
    await page.screenshot({ path: `${OUT}/hub-top.png`, clip: { x: 0, y: 0, width: 1440, height: 900 } });
    await page.screenshot({ path: `${OUT}/hub-full.png`, fullPage: true });
    console.log('hub-top.png, hub-full.png  h1=', await page.locator('h1').first().innerText().catch(() => '(none)'));
  }
  if (what === 'category' || what === 'all') {
    await go(`${HUB}/air-brakes/`);
    await page.screenshot({ path: `${OUT}/category-top.png`, clip: { x: 0, y: 0, width: 1440, height: 900 } });
    console.log('category-top.png');
  }
  if (what === 'exam' || what === 'all') {
    // Screen 1 — briefing/consent
    await go(`${HUB}/exam/?mode=full`);
    await page.screenshot({ path: `${OUT}/exam-1-briefing.png`, fullPage: true });
    console.log('exam-1-briefing.png');
    // Tick consent + Begin → running
    const consent = page.locator('[data-quiz-consent]').first();
    if (await consent.count()) {
      await consent.check().catch(() => {});
      await page.locator('[data-quiz-begin]').first().click().catch(() => {});
      await page.waitForTimeout(1500);
      await page.screenshot({ path: `${OUT}/exam-2-running.png` });
      console.log('exam-2-running.png');
    }
  }
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
