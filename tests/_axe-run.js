// Standalone axe: absolute serious/critical violations on the Elementor build.
const { chromium } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { applyFreeze } = require('./lib/freeze');
const { preparePage, settleThemeTransition } = require('./lib/theme');

const PAGES = [
  { key: 'home', path: '/' },
  { key: 'contact', path: '/contact/' },
  { key: 'blog-post', path: '/blog/winter-driving-bc-essential-safety-tips/' },
];

(async () => {
  const browser = await chromium.launch();
  for (const p of PAGES) {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await applyFreeze(page);
    await preparePage(page, 'light');
    await page.goto('http://localhost:8080' + p.path, { waitUntil: 'domcontentloaded' });
    await settleThemeTransition(page);
    await page.waitForTimeout(800);
    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze();
    const sc = results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
    const mod = results.violations.filter((v) => v.impact === 'moderate' || v.impact === 'minor');
    console.log(`\n===== ${p.key} (${p.path}) =====`);
    console.log(`serious/critical: ${sc.length}   moderate/minor: ${mod.length}`);
    for (const v of sc) {
      console.log(`  [${v.impact}] ${v.id}: ${v.help} (${v.nodes.length} nodes)`);
      v.nodes.slice(0, 3).forEach((n) => console.log(`      → ${n.target.join(' ')}  ${(n.html||'').slice(0,90)}`));
    }
    console.log('  -- moderate/minor ids: ' + mod.map((v) => `${v.id}(${v.nodes.length})`).join(', '));
    await ctx.close();
  }
  await browser.close();
})();
