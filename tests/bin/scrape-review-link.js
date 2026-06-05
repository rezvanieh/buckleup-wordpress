#!/usr/bin/env node
/**
 * Extract real Google reviews from per-review "share" links.
 *
 * WHY this exists: Google serves a degraded "limited view" of a Maps PLACE page
 * to unauthenticated/automated browsers, so the reviews pane never loads and the
 * place URL can't be scraped. A SINGLE-REVIEW share link
 * (Maps → a review → Share → Copy link → https://maps.app.goo.gl/XXXX) renders
 * the review in FULL view even when not signed in. So the client copies the share
 * link of each review and we extract from those deep-links instead.
 *
 * USAGE (from tests/):
 *   node bin/scrape-review-link.js <share-url> [<share-url> ...]
 * Output: JSON array to stdout AND to tests/results/google-reviews.json, each:
 *   { link, name, rating, when, text, translated }
 *
 * NOTES
 * - We force hl=en after the redirect so we get the VERBATIM English original
 *   (Google otherwise auto-translates to the geo-IP locale; `translated` flags it).
 * - The reviewer name is read as the body line immediately before the "X ago"
 *   timestamp (the panel's name <div> class is unstable); blank lines that hold
 *   zero-width chars are dropped by requiring a real letter/number per line.
 * - Then transcribe the output into scripts/wp/real-testimonials.php (verbatim
 *   text; title-case names for polish) and run the seed/applier — see CLAUDE.md.
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('@playwright/test');

const LINKS = process.argv.slice(2);
if (!LINKS.length) {
  console.error('usage: node bin/scrape-review-link.js <share-url> [<share-url> ...]');
  process.exit(1);
}

const RESULTS = path.join(__dirname, '..', 'results');
fs.mkdirSync(RESULTS, { recursive: true });

const forceEnglish = (url) => url + (url.includes('?') ? '&' : '?') + 'hl=en&gl=US';

async function extractOne(page, share) {
  await page.goto(share, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(3500);
  for (const label of ['Accept all', 'Reject all', 'I agree']) {
    const b = page.getByRole('button', { name: new RegExp('^' + label, 'i') });
    if (await b.count().catch(() => 0)) { try { await b.first().click({ timeout: 2000 }); } catch {} }
  }
  await page.waitForTimeout(1500);

  // Re-navigate to the resolved review URL forced into English.
  const resolved = page.url();
  if (/google\.com\/maps/.test(resolved)) {
    await page.goto(forceEnglish(resolved), { waitUntil: 'domcontentloaded', timeout: 60000 }).catch(() => {});
    await page.waitForTimeout(3500);
  }
  // Expand any truncated text.
  for (const sel of ['button:has-text("More")', 'button.w8nwRe']) {
    const m = page.locator(sel);
    const n = await m.count().catch(() => 0);
    for (let j = 0; j < n; j++) { try { await m.nth(j).click({ timeout: 600 }); } catch {} }
  }
  await page.waitForTimeout(500);

  return await page.evaluate(() => {
    const pick = (sel) => { const e = document.querySelector(sel); return e ? e.textContent.trim() : null; };

    // Rating from an English aria-label ("5 stars" / "Rated 5.0 out of 5").
    let rating = null;
    for (const el of document.querySelectorAll('[aria-label],[role="img"]')) {
      const a = el.getAttribute('aria-label') || '';
      const m = a.match(/([0-5](?:\.\d)?)\s*star/i) || a.match(/rated\s*([0-5](?:\.\d)?)/i);
      if (m) { rating = parseFloat(m[1]); break; }
    }

    let text = (pick('.wiI7pd') || pick('[class*="wiI7pd"]') || '').replace(/\s*\n\s*/g, ' ').trim();

    // Name = the visible line right before the "X ago" timestamp. Keep only lines
    // that contain a real letter/number (drops Google's zero-width "blank" lines).
    const timeRe = /^(a|an|\d+)\s+(second|minute|hour|day|week|month|year)s?\s+ago$/i;
    const lines = document.body.innerText
      .split('\n')
      .map((s) => s.trim())
      .filter((l) => /[\p{L}\p{N}]/u.test(l));
    let name = pick('.d4r55') || pick('[class*="d4r55"]') || null;
    let when = pick('.rsqaWe') || pick('[class*="rsqaWe"]') || null;
    for (let i = 1; i < lines.length; i++) {
      if (timeRe.test(lines[i])) {
        if (!name) name = lines[i - 1];
        if (!when) when = lines[i];
        break;
      }
    }
    return { name, rating, when, text, translated: /Translated by Google|See original/i.test(document.body.innerText) };
  });
}

(async () => {
  const browser = await chromium.launch({
    headless: true,
    args: ['--disable-blink-features=AutomationControlled', '--lang=en-US'],
  });
  const ctx = await browser.newContext({
    locale: 'en-US',
    timezoneId: 'America/Vancouver',
    extraHTTPHeaders: { 'Accept-Language': 'en-US,en;q=0.9' },
    userAgent:
      'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    viewport: { width: 1400, height: 1800 },
  });
  // Mask the obvious automation signals so we don't get the "limited view".
  await ctx.addInitScript(() => {
    Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
    Object.defineProperty(navigator, 'languages', { get: () => ['en-US', 'en'] });
    Object.defineProperty(navigator, 'plugins', { get: () => [1, 2, 3, 4, 5] });
    window.chrome = { runtime: {} };
  });
  await ctx.addCookies([
    { name: 'CONSENT', value: 'YES+', domain: '.google.com', path: '/' },
    { name: 'SOCS', value: 'CAISEwgDEgk0ODE3Nzk3MjQaAmVuIAEaBgiA_LyaBg', domain: '.google.com', path: '/' },
  ]);
  const page = await ctx.newPage();

  const out = [];
  for (const link of LINKS) {
    try {
      const r = await extractOne(page, link);
      out.push({ link, ...r });
      console.error(`✓ ${r.name || '(no name)'} — ${r.rating ?? '?'}★ — ${r.when || ''}`);
    } catch (e) {
      out.push({ link, error: e.message });
      console.error(`✗ ${link}: ${e.message}`);
    }
  }
  fs.writeFileSync(path.join(RESULTS, 'google-reviews.json'), JSON.stringify(out, null, 2));
  console.log(JSON.stringify(out, null, 2));
  await browser.close();
})().catch((e) => { console.error('ERR', e.message); process.exit(1); });
