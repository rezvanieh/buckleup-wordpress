// Quick visual capture of the 5 Elementor location landing pages.
// Usage: node bin/shoot-locations.js
const { chromium } = require('playwright');
const fs = require('fs');

const SLUGS = ['coquitlam', 'north-vancouver', 'port-coquitlam', 'port-moody', 'tri-cities'];
const BASE = process.env.BASE || 'http://localhost:8080';
const OUT = 'results/locations';

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 });
  const page = await ctx.newPage();
  for (const slug of SLUGS) {
    const url = `${BASE}/locations/${slug}/?cb=${Date.now()}`;
    await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
    await page.waitForTimeout(800);
    // Hero viewport shot
    await page.screenshot({ path: `${OUT}/${slug}-hero.png` });
    // Full page shot
    await page.screenshot({ path: `${OUT}/${slug}-full.png`, fullPage: true });
    const h1 = await page.locator('h1').first().innerText().catch(() => '(none)');
    const heroBg = await page.locator('.elementor-element').first().evaluate(el => getComputedStyle(el).backgroundImage).catch(() => '');
    console.log(`${slug}: h1="${h1.replace(/\n/g, ' ')}" heroBg=${heroBg.includes('webp') ? 'WEBP-OK' : heroBg.slice(0, 40)}`);
  }
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
