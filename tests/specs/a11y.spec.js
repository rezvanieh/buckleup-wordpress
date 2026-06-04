// Accessibility parity: run axe-core against every public page (candidate) and assert NO
// NEW serious/critical violations versus the live baseline. We snapshot the live site's
// violation fingerprints into results/a11y-live-baseline.json (run with TARGET=live once),
// then on the candidate any serious/critical rule NOT already present on live fails.
//
// Run light + dark at one desktop viewport (axe results are theme-sensitive via contrast).
// Cross-browser is unnecessary for axe (DOM-based); Chromium only.

const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const fs = require('fs');
const path = require('path');
const { baseUrlFor } = require('../lib/sites');
const { applyFreeze } = require('../lib/freeze');
const { preparePage, settleThemeTransition } = require('../lib/theme');

const ROOT = path.resolve(__dirname, '..');
const urls = JSON.parse(fs.readFileSync(path.join(ROOT, 'config', 'urls.json'), 'utf8'));
const TARGET = process.env.TARGET || 'candidate';
const BASE = baseUrlFor(TARGET);
const SERIOUS = new Set(['serious', 'critical']);
const baselinePath = path.join(ROOT, 'results', 'a11y-live-baseline.json');

// Only one viewport for axe; pick by env or default desktop.
const A11Y_VIEWPORT = process.env.A11Y_VIEWPORT || '1440';

function liveFingerprints() {
  if (!fs.existsSync(baselinePath)) return {};
  return JSON.parse(fs.readFileSync(baselinePath, 'utf8'));
}

test.describe('a11y parity (no new serious/critical vs live)', () => {
  const collected = {}; // pageKey::theme -> [ruleIds] (used when TARGET=live to write baseline)

  test.beforeEach(async ({ page }, testInfo) => {
    test.skip(testInfo.project.metadata.viewport !== A11Y_VIEWPORT, `axe runs at ${A11Y_VIEWPORT} only`);
    test.skip(testInfo.project.metadata.browser !== 'chromium', 'axe runs on chromium only');
    await applyFreeze(page);
  });

  for (const p of urls.pages) {
    for (const theme of ['light', 'dark']) {
      test(`${p.key} @ ${theme}`, async ({ page }, testInfo) => {
        await preparePage(page, theme);
        await page.goto(BASE + p.path, { waitUntil: 'domcontentloaded' });
        // Settle past the theme's 150ms base-layer transition before axe samples colors,
        // so dark-mode contrast isn't measured mid-transition (false fail).
        await settleThemeTransition(page);
        // axe-core canvas-detection limitation: in dark mode the page's dark canvas comes
        // from `color-scheme: dark` (a UA-painted canvas axe cannot read), so axe assumes a
        // phantom WHITE background behind transparent elements and reports false color-contrast
        // failures (e.g. the dark-theme link #9e9eff scored against #fff = 2.38, when on the
        // real dark canvas rgb(8,12,22) it's ~18.6:1). Verified via screenshot that dark renders
        // correctly. Set an explicit dark background matching the rendered canvas so axe scores
        // against the TRUE background. Dark-only; light mode's canvas is already explicit white.
        if (theme === 'dark') {
          await page.evaluate(() => { document.documentElement.style.backgroundColor = 'rgb(8, 12, 22)'; });
        }
        const results = await new AxeBuilder({ page })
          .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
          .analyze();
        const serious = results.violations.filter((v) => SERIOUS.has(v.impact));
        const ruleIds = serious.map((v) => v.id).sort();
        const key = `${p.key}::${theme}`;
        collected[key] = ruleIds;

        await testInfo.attach('axe-serious', {
          body: JSON.stringify(serious.map((v) => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, help: v.help })), null, 2),
          contentType: 'application/json',
        });

        if (TARGET === 'live') {
          // Baseline-capture mode: record, never fail.
          return;
        }
        const live = liveFingerprints()[key] || [];
        const liveSet = new Set(live);
        const novel = ruleIds.filter((id) => !liveSet.has(id));
        expect(novel,
          `New serious/critical a11y violations on candidate not present on live for ${key}: ${novel.join(', ')}`
        ).toEqual([]);
      });
    }
  }

  test.afterAll(async () => {
    if (TARGET === 'live' && Object.keys(collected).length) {
      fs.mkdirSync(path.dirname(baselinePath), { recursive: true });
      fs.writeFileSync(baselinePath, JSON.stringify(collected, null, 2));
      // eslint-disable-next-line no-console
      console.log(`[a11y] wrote live baseline fingerprints -> ${baselinePath}`);
    }
  });
});
