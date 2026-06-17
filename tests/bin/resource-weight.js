// Bundle/resource-weight analyzer for the location pages.
// Loads each page, captures every network response, sums transfer bytes by type,
// and flags render-blocking <head> CSS/JS. Mobile emulation.
//   node bin/resource-weight.js [slug ...]
const { chromium } = require('playwright');

const SLUGS = process.argv.slice(2).length ? process.argv.slice(2) : ['coquitlam'];
const BASE = process.env.BASE || 'http://localhost:8080';

(async () => {
  const browser = await chromium.launch();
  for (const slug of SLUGS) {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true });
    const page = await ctx.newPage();
    const byType = {}; const big = []; let total = 0, reqCount = 0;
    page.on('response', async (resp) => {
      try {
        const h = resp.headers();
        const ct = (h['content-type'] || '').split(';')[0];
        let len = parseInt(h['content-length'] || '0', 10);
        if (!len) { try { len = (await resp.body()).length; } catch {} }
        let t = 'other';
        if (/javascript/.test(ct)) t = 'js';
        else if (/css/.test(ct)) t = 'css';
        else if (/image\//.test(ct)) t = 'image';
        else if (/font|woff/.test(ct)) t = 'font';
        else if (/html/.test(ct)) t = 'html';
        byType[t] = (byType[t] || 0) + len; total += len; reqCount++;
        if (len > 40000) big.push({ url: resp.url().replace(BASE, ''), kb: Math.round(len / 1024), t });
      } catch {}
    });
    await page.goto(`${BASE}/locations/${slug}/?cb=${Date.now()}`, { waitUntil: 'networkidle', timeout: 60000 });
    // Render-blocking head resources (no defer/async on script; sync stylesheet links)
    const blocking = await page.evaluate(() => {
      const out = { scripts: [], styles: 0 };
      document.querySelectorAll('head script[src]').forEach(s => { if (!s.defer && !s.async && s.type !== 'module') out.scripts.push(s.src.split('/').pop()); });
      out.styles = document.querySelectorAll('head link[rel="stylesheet"]').length;
      return out;
    });
    const kb = n => Math.round(n / 1024);
    console.log(`\n=== ${slug} ===  total ${kb(total)} KB across ${reqCount} requests`);
    for (const t of ['html', 'css', 'js', 'font', 'image', 'other']) if (byType[t]) console.log(`  ${t.padEnd(6)} ${String(kb(byType[t])).padStart(5)} KB`);
    console.log(`  render-blocking: ${blocking.scripts.length} sync head scripts, ${blocking.styles} stylesheet links`);
    if (blocking.scripts.length) console.log(`    sync scripts: ${blocking.scripts.slice(0, 12).join(', ')}`);
    big.sort((a, b) => b.kb - a.kb);
    console.log(`  heaviest:`); big.slice(0, 10).forEach(b => console.log(`    ${String(b.kb).padStart(4)}KB [${b.t}] ${b.url.slice(0, 70)}`));
    await ctx.close();
  }
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
