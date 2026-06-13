#!/usr/bin/env node
/**
 * Elementor build QA crawler (render-correctness + integrity, NOT pixel-parity).
 *
 *   node bin/qa-elementor-crawl.js
 *
 * For every marketing page + /blog + a single post + /login on the LOCAL build it:
 *   - loads the page (frozen motion, light theme), records HTTP status + console errors
 *   - extracts SEO surface (title/desc/canonical/OG/twitter/robots/JSON-LD)
 *   - counts <h1> (expect exactly 1) and validates each JSON-LD block parses + lists @types
 *   - collects every in-document link (internal + in-page anchors), de-dupes, and HEAD/GETs
 *     each internal URL to flag 404s / dead anchors
 *   - detects horizontal overflow at 1440 and 390, and broken/zero-dimension images
 *   - screenshots full page at 1440 (desktop) and 390 (mobile)
 *
 * Writes results/qa-elementor.json + screenshots under results/qa-shots/.
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('@playwright/test');
const { baseUrlFor } = require('../lib/sites');
const { applyFreeze } = require('../lib/freeze');
const { preparePage } = require('../lib/theme');
const { extractSeo, flattenJsonLd } = require('../lib/seo-extract');

const ROOT = path.resolve(__dirname, '..');
const BASE = baseUrlFor('candidate');
const SHOTS = path.join(ROOT, 'results', 'qa-shots');
fs.mkdirSync(SHOTS, { recursive: true });

// The pages to crawl (Elementor marketing + theme templates).
const PAGES = [
  { key: 'home', path: '/' },
  { key: 'about', path: '/about/' },
  { key: 'services', path: '/services/' },
  { key: 'instructors', path: '/instructors/' },
  { key: 'contact', path: '/contact/' },
  { key: 'resources', path: '/resources/' },
  { key: 'resources-icbc', path: '/resources/icbc-road-test-failures/' },
  { key: 'loc-coquitlam', path: '/locations/coquitlam/' },
  { key: 'loc-north-vancouver', path: '/locations/north-vancouver/' },
  { key: 'loc-port-coquitlam', path: '/locations/port-coquitlam/' },
  { key: 'loc-port-moody', path: '/locations/port-moody/' },
  { key: 'loc-tri-cities', path: '/locations/tri-cities/' },
  { key: 'blog-index', path: '/blog/' },
  { key: 'blog-post', path: '/blog/winter-driving-bc-essential-safety-tips/' },
  { key: 'login', path: '/login/' },
];

// ----------------------------------------------------------------------------
const report = { base: BASE, ranAt: new Date().toISOString(), pages: [], linkCheck: {} };
const allInternalLinks = new Set(); // absolute URLs to verify once at the end

function sameOrigin(href) {
  try {
    const u = new URL(href, BASE);
    return u.origin === new URL(BASE).origin;
  } catch { return false; }
}

async function crawlPage(browser, p) {
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  const consoleErrors = [];
  const pageErrors = [];
  const failedRequests = [];

  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text().slice(0, 300));
  });
  page.on('pageerror', (err) => pageErrors.push(String(err).slice(0, 300)));
  page.on('requestfailed', (req) => {
    const f = req.failure();
    failedRequests.push(`${req.method()} ${req.url().slice(0, 160)} — ${f ? f.errorText : '?'}`);
  });

  await applyFreeze(page);
  await preparePage(page, 'light');

  const rec = { key: p.key, path: p.path };
  let resp;
  try {
    resp = await page.goto(BASE + p.path, { waitUntil: 'domcontentloaded', timeout: 30000 });
    rec.status = resp ? resp.status() : null;
  } catch (e) {
    rec.status = 'NAV_ERROR';
    rec.navError = String(e).slice(0, 200);
  }
  await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
  // autoscroll to trigger lazy content
  await page.evaluate(async () => {
    await new Promise((res) => {
      let y = 0; const step = 800; let ticks = 0;
      const t = setInterval(() => {
        window.scrollTo(0, y); y += step; ticks++;
        if (y >= document.body.scrollHeight || ticks > 60) { clearInterval(t); res(); }
      }, 30);
    });
  }).catch(() => {});
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(300);

  // --- SEO surface ---
  try { rec.seo = await extractSeo(page); } catch (e) { rec.seoError = String(e).slice(0, 200); }

  // --- DOM integrity probes ---
  const dom = await page.evaluate(() => {
    const h1s = Array.from(document.querySelectorAll('h1')).map((h) => (h.textContent || '').trim().slice(0, 120));
    // heading order: collect levels in document order
    const headingLevels = Array.from(document.querySelectorAll('h1,h2,h3,h4,h5,h6')).map((h) => Number(h.tagName[1]));
    // links
    const links = Array.from(document.querySelectorAll('a[href]')).map((a) => ({
      href: a.getAttribute('href'),
      text: (a.textContent || '').trim().slice(0, 60),
    }));
    // images: broken (naturalWidth 0 after load) or missing alt
    const imgs = Array.from(document.images).map((i) => ({
      src: (i.currentSrc || i.src || '').slice(0, 160),
      broken: i.complete && i.naturalWidth === 0,
      noAlt: !i.hasAttribute('alt'),
      w: i.naturalWidth, h: i.naturalHeight,
    }));
    // landmarks
    const landmarks = {
      header: document.querySelectorAll('header').length,
      nav: document.querySelectorAll('nav').length,
      main: document.querySelectorAll('main, [role=main]').length,
      footer: document.querySelectorAll('footer').length,
    };
    return { h1s, headingLevels, links, imgs, landmarks };
  });
  rec.h1Count = dom.h1s.length;
  rec.h1s = dom.h1s;
  rec.landmarks = dom.landmarks;
  rec.brokenImages = dom.imgs.filter((i) => i.broken).map((i) => i.src);
  rec.imagesNoAlt = dom.imgs.filter((i) => i.noAlt).length;
  rec.imageCount = dom.imgs.length;

  // heading-order skips (e.g. h2 -> h4)
  rec.headingSkips = [];
  for (let i = 1; i < dom.headingLevels.length; i++) {
    if (dom.headingLevels[i] - dom.headingLevels[i - 1] > 1) {
      rec.headingSkips.push(`h${dom.headingLevels[i - 1]}→h${dom.headingLevels[i]}`);
    }
  }

  // collect links for global verification + capture in-page anchors present on THIS page
  const anchorsOnPage = new Set(
    (await page.evaluate(() => Array.from(document.querySelectorAll('[id]')).map((e) => e.id))).filter(Boolean)
  );
  rec.deadInPageAnchors = [];
  rec.linksOnPage = dom.links.length;
  for (const l of dom.links) {
    const href = l.href;
    if (!href) continue;
    if (href.startsWith('#')) {
      const id = href.slice(1);
      if (id && !anchorsOnPage.has(id)) rec.deadInPageAnchors.push(`${href} (text: "${l.text}")`);
      continue;
    }
    if (/^(mailto:|tel:|javascript:|whatsapp:|sms:)/i.test(href)) continue;
    if (href.startsWith('https://wa.me') || href.includes('api.whatsapp.com')) continue;
    if (sameOrigin(href)) {
      const abs = new URL(href, BASE + p.path).toString();
      // strip in-page hash for the existence check but keep note of the target page's anchors separately
      allInternalLinks.add(abs.split('#')[0]);
    }
  }

  // --- horizontal overflow at 1440 then 390 ---
  rec.overflow = {};
  for (const vp of [{ name: 'desktop', w: 1440, h: 1000 }, { name: 'mobile', w: 390, h: 844 }]) {
    await page.setViewportSize({ width: vp.w, height: vp.h });
    await page.waitForTimeout(250);
    const ov = await page.evaluate(() => ({
      scrollW: document.documentElement.scrollWidth,
      clientW: document.documentElement.clientWidth,
      innerW: window.innerWidth,
    }));
    rec.overflow[vp.name] = {
      overflowPx: ov.scrollW - ov.clientW,
      hasOverflow: ov.scrollW > ov.clientW + 2, // 2px tolerance
    };
    // screenshot
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(150);
    const shot = path.join(SHOTS, `${p.key}-${vp.name}.png`);
    try { await page.screenshot({ path: shot, fullPage: true }); } catch (e) { rec.overflow[vp.name].shotError = String(e).slice(0, 120); }
  }

  rec.consoleErrors = consoleErrors;
  rec.pageErrors = pageErrors;
  rec.failedRequests = failedRequests;
  report.pages.push(rec);
  await ctx.close();

  // concise console line
  const flags = [];
  if (rec.status !== 200) flags.push(`HTTP ${rec.status}`);
  if (rec.h1Count !== 1) flags.push(`H1=${rec.h1Count}`);
  if (rec.brokenImages.length) flags.push(`brokenImg=${rec.brokenImages.length}`);
  if (rec.overflow.mobile && rec.overflow.mobile.hasOverflow) flags.push(`mob-overflow=${rec.overflow.mobile.overflowPx}px`);
  if (rec.overflow.desktop && rec.overflow.desktop.hasOverflow) flags.push(`desk-overflow=${rec.overflow.desktop.overflowPx}px`);
  if (rec.deadInPageAnchors.length) flags.push(`deadAnchors=${rec.deadInPageAnchors.length}`);
  if (rec.pageErrors.length) flags.push(`jsErr=${rec.pageErrors.length}`);
  console.log(`${p.key.padEnd(20)} ${String(rec.status).padStart(4)}  H1=${rec.h1Count} links=${rec.linksOnPage} imgs=${rec.imageCount} ${flags.length ? '⚠ ' + flags.join(' ') : 'ok'}`);
}

async function verifyLinks(browser) {
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
  const urls = [...allInternalLinks].sort();
  for (const u of urls) {
    try {
      const r = await ctx.request.get(u, { maxRedirects: 5, timeout: 15000 });
      report.linkCheck[u] = r.status();
    } catch (e) {
      report.linkCheck[u] = 'ERR:' + String(e).slice(0, 80);
    }
  }
  await ctx.close();
}

async function main() {
  console.log(`[qa-crawl] base=${BASE}\n`);
  const browser = await chromium.launch();
  for (const p of PAGES) await crawlPage(browser, p);
  console.log(`\n[qa-crawl] verifying ${allInternalLinks.size} unique internal links...`);
  await verifyLinks(browser);
  await browser.close();

  // summarize broken links
  const broken = Object.entries(report.linkCheck).filter(([, s]) => typeof s === 'number' ? s >= 400 : true);
  report.brokenLinks = broken;
  console.log(`\nbroken/error internal links: ${broken.length}`);
  for (const [u, s] of broken) console.log(`   x ${s}  ${u}`);

  fs.writeFileSync(path.join(ROOT, 'results', 'qa-elementor.json'), JSON.stringify(report, null, 2));
  console.log(`\n[qa-crawl] -> results/qa-elementor.json  (shots in results/qa-shots/)`);
}
main().catch((e) => { console.error(e); process.exit(1); });
