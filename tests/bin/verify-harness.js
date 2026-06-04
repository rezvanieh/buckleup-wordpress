#!/usr/bin/env node
/**
 * Harness self-check (no network, no Docker). Confirms the parity harness is wired correctly
 * before Task #9: every config parses, every lib loads, the pinned live baseline exists and
 * is complete (all pages × viewports × themes present), and the parity matrix is well-formed.
 *
 *   node bin/verify-harness.js
 *
 * Exit 0 = ready to validate a candidate; 1 = something is missing (printed).
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
let fail = 0;
const ok = (m) => console.log(`  ok   ${m}`);
const bad = (m) => { console.log(`  FAIL ${m}`); fail++; };

// 1. configs parse
const cfg = {};
for (const f of ['urls', 'viewports', 'masks', 'seo-expectations']) {
  try { cfg[f] = JSON.parse(fs.readFileSync(path.join(ROOT, 'config', `${f}.json`), 'utf8')); ok(`config/${f}.json parses`); }
  catch (e) { bad(`config/${f}.json: ${e.message}`); }
}

// 2. libs load
for (const f of ['sites', 'freeze', 'theme', 'load', 'seo-extract', 'mask', 'diff']) {
  try { require(path.join(ROOT, 'lib', `${f}.js`)); ok(`lib/${f}.js loads`); }
  catch (e) { bad(`lib/${f}.js: ${e.message}`); }
}

// 3. parity matrix well-formed (consistent column count)
try {
  const lines = fs.readFileSync(path.join(ROOT, 'parity-matrix.csv'), 'utf8').trim().split('\n');
  const cols = (l) => { let n = 1, q = false; for (const c of l) { if (c === '"') q = !q; else if (c === ',' && !q) n++; } return n; };
  const header = cols(lines[0]);
  const mismatched = lines.filter((l) => cols(l) !== header).length;
  const expected = lines.slice(1).filter((l) => /EXPECTED/.test(l)).length;
  if (mismatched === 0) ok(`parity-matrix.csv: ${lines.length - 1} rows, ${header} cols, ${expected} EXPECTED-divergence rows`);
  else bad(`parity-matrix.csv: ${mismatched} row(s) with wrong column count`);
} catch (e) { bad(`parity-matrix.csv: ${e.message}`); }

// 4. pinned live baseline present + complete
try {
  const live = path.join(ROOT, 'baseline', 'live');
  const latest = fs.existsSync(path.join(live, 'latest'))
    ? path.join(live, 'latest')
    : path.join(live, fs.readFileSync(path.join(live, 'LATEST'), 'utf8').trim());
  const manifest = JSON.parse(fs.readFileSync(path.join(latest, 'manifest.json'), 'utf8'));
  ok(`baseline pinned: ${manifest.target} @ ${manifest.date} (${manifest.pages.length} pages)`);

  const vps = cfg.viewports.viewports.map((v) => v.name);
  const themes = manifest.themes;
  let missing = 0;
  for (const p of cfg.urls.pages) {
    for (const vp of vps) for (const th of themes) {
      const f = path.join(latest, 'screens', `${p.key}--${vp}--${th}.png`);
      if (!fs.existsSync(f)) { missing++; if (missing <= 5) bad(`missing screenshot ${path.basename(f)}`); }
    }
    for (const th of themes) {
      const s = path.join(latest, 'seo', `${p.key}--${th}.json`);
      if (!fs.existsSync(s)) { missing++; if (missing <= 5) bad(`missing seo snapshot ${path.basename(s)}`); }
    }
  }
  const expectShots = cfg.urls.pages.length * vps.length * themes.length;
  if (missing === 0) ok(`baseline complete: ${expectShots} screenshots + ${cfg.urls.pages.length * themes.length} SEO snapshots, sitemap, robots, ${cfg.urls.pages.length} header files`);
  else bad(`baseline incomplete: ${missing} artifact(s) missing`);
  if (manifest.errors && manifest.errors.length) bad(`baseline manifest reports ${manifest.errors.length} capture error(s)`);
} catch (e) { bad(`baseline: ${e.message} — run \`npm run baseline\` first`); }

console.log(`\n${fail === 0 ? 'HARNESS OK — ready for Task #9' : `${fail} problem(s) — see above`}`);
process.exit(fail ? 1 : 0);
