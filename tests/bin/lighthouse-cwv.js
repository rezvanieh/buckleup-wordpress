#!/usr/bin/env node
/**
 * Lean Lighthouse CWV check for sign-off (PLAN.md Phase 6 — modest, not over-engineered).
 *
 *   node bin/lighthouse-cwv.js                 # candidate, mobile, home + a location + a blog post
 *   node bin/lighthouse-cwv.js --target live   # same pages on the live site (for comparison)
 *
 * Runs Lighthouse (mobile emulation) headless via the Playwright-installed Chromium, reports
 * LCP / CLS / TBT (INP proxy) / total transfer + the perf score per page, and PASS/FAIL vs a
 * sane budget. Writes results/lighthouse-<target>.json.
 *
 * Budget (mobile): LCP < 2500ms, CLS < 0.1, TBT < 300ms, perf score >= 0.80.
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('@playwright/test');
const { baseUrlFor } = require('../lib/sites');

const ROOT = path.resolve(__dirname, '..');
const TARGET = process.argv.includes('--target') ? process.argv[process.argv.indexOf('--target') + 1] : 'candidate';
const BASE = baseUrlFor(TARGET);
const PAGES = [
  { key: 'home', path: '/' },
  { key: 'loc-port-moody', path: '/locations/port-moody' },
  { key: 'blog-post', path: '/blog/winter-driving-bc-essential-safety-tips' },
];
const BUDGET = { lcp: 2500, cls: 0.1, tbt: 300, score: 0.80 };

async function main() {
  const { default: lighthouse } = await import('lighthouse');
  // Launch Chromium with a fixed remote-debugging port for Lighthouse to attach to.
  const browser = await chromium.launch({ args: ['--remote-debugging-port=9222'] });
  const port = 9222;
  const out = { target: TARGET, base: BASE, ranAt: new Date().toISOString(), budget: BUDGET, pages: [] };

  for (const p of PAGES) {
    const result = await lighthouse(BASE + p.path, {
      port, output: 'json', logLevel: 'error',
      onlyCategories: ['performance'],
      formFactor: 'mobile',
      screenEmulation: { mobile: true, width: 375, height: 812, deviceScaleFactor: 2, disabled: false },
    });
    const a = result.lhr.audits;
    const m = {
      key: p.key, path: p.path,
      score: result.lhr.categories.performance.score,
      lcp: Math.round(a['largest-contentful-paint'].numericValue),
      cls: +a['cumulative-layout-shift'].numericValue.toFixed(4),
      tbt: Math.round(a['total-blocking-time'].numericValue),
      fcp: Math.round(a['first-contentful-paint'].numericValue),
      si: Math.round(a['speed-index'].numericValue),
      transferKb: Math.round((a['total-byte-weight'].numericValue || 0) / 1024),
    };
    m.pass = m.lcp < BUDGET.lcp && m.cls < BUDGET.cls && m.tbt < BUDGET.tbt && m.score >= BUDGET.score;
    out.pages.push(m);
    console.log(
      `${p.key.padEnd(15)} score=${(m.score * 100).toFixed(0).padStart(3)} ` +
      `LCP=${String(m.lcp).padStart(5)}ms CLS=${String(m.cls).padStart(6)} TBT=${String(m.tbt).padStart(4)}ms ` +
      `transfer=${m.transferKb}KB  → ${m.pass ? 'PASS' : 'OVER BUDGET'}`
    );
  }

  await browser.close();
  fs.mkdirSync(path.join(ROOT, 'results'), { recursive: true });
  fs.writeFileSync(path.join(ROOT, 'results', `lighthouse-${TARGET}.json`), JSON.stringify(out, null, 2));
  console.log(`\nbudget: LCP<${BUDGET.lcp}ms CLS<${BUDGET.cls} TBT<${BUDGET.tbt}ms score>=${BUDGET.score} → results/lighthouse-${TARGET}.json`);
  process.exit(out.pages.every((p) => p.pass) ? 0 : 1);
}
main().catch((e) => { console.error(e); process.exit(1); });
