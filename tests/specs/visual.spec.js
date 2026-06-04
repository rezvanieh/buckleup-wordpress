// Visual-regression parity: for every page x viewport x theme, capture the CANDIDATE (WP
// build) in the same stabilized state used for the baseline, mask its dynamic regions, and
// pixelmatch it against the pinned LIVE baseline screenshot. Fails when the diff ratio
// exceeds the per-page threshold (config/masks.json).
//
// The Playwright project name encodes the viewport (e.g. "chromium-1100"); we read it from
// testInfo so the matrix of (page x theme) is generated per-project. Browsers default to
// Chromium; set PW_CROSS_BROWSER=1 for WebKit/Firefox too.
//
// Baseline source: baseline/live/latest/screens/<key>--<vp>--<theme>.png (pinned golden
// master). Set UPDATE_BASELINE=1 to (re)write the CANDIDATE shots into a candidate baseline
// dir for inspection instead of failing — it never overwrites the live golden master.

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { baseUrlFor } = require('../lib/sites');
const { loadStable } = require('../lib/load');
const { applyMasks, thresholdForPage } = require('../lib/mask');
const { compareBuffers } = require('../lib/diff');

const ROOT = path.resolve(__dirname, '..');
const urls = JSON.parse(fs.readFileSync(path.join(ROOT, 'config', 'urls.json'), 'utf8'));
const THEMES = (process.env.THEMES || 'light,dark').split(',');
const CANDIDATE = baseUrlFor(process.env.TARGET || 'candidate');

function baselineDir() {
  // Resolve baseline/live/latest (symlink) or fall back to baseline/live/<LATEST file>.
  const live = path.join(ROOT, 'baseline', 'live');
  const latest = path.join(live, 'latest');
  if (fs.existsSync(latest)) return latest;
  const lf = path.join(live, 'LATEST');
  if (fs.existsSync(lf)) return path.join(live, fs.readFileSync(lf, 'utf8').trim());
  throw new Error('No live baseline found. Run `npm run baseline` first.');
}

const resultsDir = path.join(ROOT, 'results', 'visual-diffs');
fs.mkdirSync(resultsDir, { recursive: true });

test.describe('visual parity (candidate vs pinned live baseline)', () => {
  for (const p of urls.pages) {
    for (const theme of THEMES) {
      test(`${p.key} @ ${theme}`, async ({ page }, testInfo) => {
        // Pixel diffs are only meaningful against same-engine baselines; the baseline is
        // captured on Chromium, so skip WebKit/Firefox projects (cross-browser render parity
        // is covered functionally in functional.spec, not pixel-for-pixel).
        test.skip(testInfo.project.metadata.browser !== 'chromium',
          'visual diff is chromium-only (baseline captured on chromium)');
        const vp = testInfo.project.metadata.viewport; // e.g. "1100"
        const baseFile = path.join(baselineDir(), 'screens', `${p.key}--${vp}--${theme}.png`);

        await loadStable(page, CANDIDATE + p.path, theme);
        const masked = await applyMasks(page, p.dynamic);
        const candidateBuf = await page.screenshot({ fullPage: true, animations: 'disabled' });

        if (process.env.UPDATE_BASELINE === '1') {
          const outDir = path.join(ROOT, 'baseline', 'candidate', 'latest', 'screens');
          fs.mkdirSync(outDir, { recursive: true });
          fs.writeFileSync(path.join(outDir, `${p.key}--${vp}--${theme}.png`), candidateBuf);
          test.skip(true, 'UPDATE_BASELINE: candidate shot written, no comparison');
          return;
        }

        expect(fs.existsSync(baseFile), `missing baseline ${path.basename(baseFile)} — run npm run baseline`).toBeTruthy();
        const baselineBuf = fs.readFileSync(baseFile);
        const { pixelmatchThreshold, maxDiffPixelRatio } = thresholdForPage(p.key);
        const res = compareBuffers(baselineBuf, candidateBuf, { pixelmatchThreshold });

        // Attach the diff image + numbers to the report regardless of pass/fail.
        await testInfo.attach('diff', { body: res.diffPng, contentType: 'image/png' });
        await testInfo.attach('candidate', { body: candidateBuf, contentType: 'image/png' });
        testInfo.annotations.push({
          type: 'visual',
          description: `ratio=${(res.ratio * 100).toFixed(3)}% (limit ${(maxDiffPixelRatio * 100).toFixed(2)}%), ` +
            `masked=${masked}, sizeMismatch=${res.sizeMismatch} ` +
            `(base ${res.width}x${res.height} vs cand ${res.candidateWidth}x${res.candidateHeight})`,
        });

        // Always write the diff + candidate when over threshold, so the record exists even
        // for EXPECTED-divergence pages (useful for the eventual re-measure once content lands).
        if (res.ratio > maxDiffPixelRatio) {
          fs.writeFileSync(path.join(resultsDir, `${p.key}--${vp}--${theme}.diff.png`), res.diffPng);
          fs.writeFileSync(path.join(resultsDir, `${p.key}--${vp}--${theme}.candidate.png`), candidateBuf);
        }

        // EXPECTED-divergence pages (services/instructors = real page vs dead live home-mirror;
        // blog-index = 15 vs 5 posts; home/locations = NO-GRADUATES-YET empty state). The live
        // baseline is intentionally NOT the target here, so a high diff is expected — record it
        // and skip the assertion rather than fail. Lead-approved 2026-06-03; reasons in urls.json.
        if (p.expectedDivergence) {
          testInfo.annotations.push({ type: 'expected-divergence', description: p.expectedDivergence });
          test.skip(true, `EXPECTED divergence (${(res.ratio * 100).toFixed(1)}%): ${p.expectedDivergence}`);
          return;
        }

        expect(res.ratio,
          `Visual diff ${(res.ratio * 100).toFixed(3)}% exceeds ${(maxDiffPixelRatio * 100).toFixed(2)}% ` +
          `for ${p.key} @ ${vp}/${theme}. Diff written to results/visual-diffs/.`
        ).toBeLessThanOrEqual(maxDiffPixelRatio);
      });
    }
  }
});
