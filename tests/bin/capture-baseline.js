#!/usr/bin/env node
/**
 * Capture a PINNED, date-stamped baseline of a target site.
 *
 *   node bin/capture-baseline.js --target live        # golden master (default cmd)
 *   node bin/capture-baseline.js --target candidate    # the WP build, for spot capture
 *
 * Produces, under baseline/<target>/<YYYY-MM-DD>/ :
 *   screens/<pageKey>--<viewport>--<theme>.png   full-page screenshots (5 vp x 2 themes x N pages)
 *   seo/<pageKey>--<theme>.json                  title/meta/canonical/OG/JSON-LD per page+theme
 *   headers/<pageKey>.json                       raw HTTP response headers + status (theme-agnostic)
 *   sitemap.xml, robots.txt                      verbatim
 *   manifest.json                                index of everything captured + run metadata
 *   latest -> <YYYY-MM-DD>                        symlink to the freshest run
 *
 * The dated folder makes the baseline immutable: a new capture lands in a new dated dir and
 * never overwrites a prior golden master, so a live-site content change can't silently move
 * the goalposts mid-project. `latest` points at the run the diff tools use by default.
 *
 * Runs Chromium headless. Idempotent within a day (re-running the same date overwrites that
 * day's artifacts only).
 */
const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { baseUrlFor } = require('../lib/sites');
const { loadStable } = require('../lib/load');
const { extractSeo } = require('../lib/seo-extract');

const ROOT = path.resolve(__dirname, '..');
const urls = JSON.parse(fs.readFileSync(path.join(ROOT, 'config', 'urls.json'), 'utf8'));
const viewports = JSON.parse(fs.readFileSync(path.join(ROOT, 'config', 'viewports.json'), 'utf8')).viewports;

function arg(name, def) {
  const i = process.argv.indexOf(`--${name}`);
  return i >= 0 ? process.argv[i + 1] : def;
}

const TARGET = arg('target', 'live');
const THEMES = (arg('themes', 'light,dark')).split(',');
const ONLY = arg('only', null); // optional comma list of page keys to limit capture
const DATE = arg('date', new Date().toISOString().slice(0, 10));
const BASE = baseUrlFor(TARGET);

function ensureDir(p) { fs.mkdirSync(p, { recursive: true }); }

async function main() {
  const outDir = path.join(ROOT, 'baseline', TARGET, DATE);
  ensureDir(path.join(outDir, 'screens'));
  ensureDir(path.join(outDir, 'seo'));
  ensureDir(path.join(outDir, 'headers'));

  const pages = urls.pages.filter((p) => !ONLY || ONLY.split(',').includes(p.key));

  console.log(`[baseline] target=${TARGET} base=${BASE} date=${DATE}`);
  console.log(`[baseline] ${pages.length} pages x ${viewports.length} viewports x ${THEMES.length} themes = ` +
    `${pages.length * viewports.length * THEMES.length} screenshots\n`);

  const browser = await chromium.launch();
  const manifest = {
    target: TARGET, baseUrl: BASE, capturedAt: new Date().toISOString(), date: DATE,
    viewports: viewports.map((v) => v.name), themes: THEMES,
    pages: [], errors: [],
  };

  // HTTP headers + sitemap/robots are theme-agnostic: fetch once via a request context.
  const reqCtx = await browser.newContext({ ignoreHTTPSErrors: true });
  for (const p of pages) {
    try {
      const resp = await reqCtx.request.get(BASE + p.path, { maxRedirects: 5 });
      fs.writeFileSync(
        path.join(outDir, 'headers', `${p.key}.json`),
        JSON.stringify({ url: BASE + p.path, status: resp.status(), headers: resp.headers() }, null, 2)
      );
    } catch (e) {
      manifest.errors.push({ page: p.key, stage: 'headers', error: String(e) });
    }
  }
  try {
    const sm = await reqCtx.request.get(BASE + '/sitemap.xml');
    fs.writeFileSync(path.join(outDir, 'sitemap.xml'), await sm.text());
  } catch (e) { manifest.errors.push({ stage: 'sitemap', error: String(e) }); }
  try {
    const rb = await reqCtx.request.get(BASE + '/robots.txt');
    fs.writeFileSync(path.join(outDir, 'robots.txt'), await rb.text());
  } catch (e) { manifest.errors.push({ stage: 'robots', error: String(e) }); }
  await reqCtx.close();

  for (const p of pages) {
    const pageEntry = { key: p.key, path: p.path, shots: [], seo: [] };
    for (const theme of THEMES) {
      // One context per (page,theme) so storage seeding + colorScheme are clean.
      const ctx = await browser.newContext({ ignoreHTTPSErrors: true, deviceScaleFactor: 1 });
      const page = await ctx.newPage();
      try {
        // SEO snapshot is captured once per theme at a desktop viewport.
        await page.setViewportSize({ width: 1440, height: 900 });
        await loadStable(page, BASE + p.path, theme);
        const seo = await extractSeo(page);
        fs.writeFileSync(path.join(outDir, 'seo', `${p.key}--${theme}.json`), JSON.stringify(seo, null, 2));
        pageEntry.seo.push(theme);

        for (const vp of viewports) {
          await page.setViewportSize({ width: vp.width, height: vp.height });
          // Re-settle after resize: re-trigger lazy content at the new width.
          await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
          await page.waitForTimeout(250);
          await page.evaluate(() => window.scrollTo(0, 0));
          await page.waitForTimeout(250);
          const file = `${p.key}--${vp.name}--${theme}.png`;
          await page.screenshot({ path: path.join(outDir, 'screens', file), fullPage: true, animations: 'disabled' });
          pageEntry.shots.push(file);
        }
        console.log(`  [ok] ${p.key} (${theme})  ${viewports.length} shots`);
      } catch (e) {
        console.log(`  [ERR] ${p.key} (${theme}): ${e.message}`);
        manifest.errors.push({ page: p.key, theme, stage: 'capture', error: String(e) });
      } finally {
        await ctx.close();
      }
    }
    manifest.pages.push(pageEntry);
  }

  await browser.close();
  fs.writeFileSync(path.join(outDir, 'manifest.json'), JSON.stringify(manifest, null, 2));

  // Update the `latest` pointer for the diff tools.
  const latest = path.join(ROOT, 'baseline', TARGET, 'latest');
  try { fs.rmSync(latest, { recursive: true, force: true }); } catch (e) {}
  try { fs.symlinkSync(DATE, latest, 'dir'); }
  catch (e) { fs.writeFileSync(path.join(ROOT, 'baseline', TARGET, 'LATEST'), DATE); }

  console.log(`\n[baseline] wrote ${manifest.pages.length} pages -> ${outDir}`);
  if (manifest.errors.length) {
    console.log(`[baseline] ${manifest.errors.length} error(s); see manifest.json`);
    process.exitCode = 1;
  }
}

main().catch((e) => { console.error(e); process.exit(1); });
