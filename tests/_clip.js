const { chromium } = require('playwright');
(async () => {
  const tag = process.argv[2], base = process.argv[3];
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 2 });
  await p.goto(base + '/?cb=' + Date.now(), { waitUntil: 'networkidle', timeout: 90000 });
  await p.waitForTimeout(1500);
  // measure the hero grid + its two columns precisely
  const m = await p.evaluate(() => {
    const grid = [...document.querySelectorAll('section [class*="grid-cols"], section .grid')].find(g => g.querySelector('img') && g.getBoundingClientRect().top < 700);
    if (!grid) return {err:'no grid'};
    const gr = grid.getBoundingClientRect();
    const cols = [...grid.children].map(c => { const r = c.getBoundingClientRect(); return {w:Math.round(r.width), h:Math.round(r.height), top:Math.round(r.top), left:Math.round(r.left)}; });
    return { gridTop: Math.round(gr.top), gridLeft: Math.round(gr.left), gridW: Math.round(gr.width), align: getComputedStyle(grid).alignItems, cols };
  });
  console.log(tag, JSON.stringify(m));
  // clip the hero region
  await p.screenshot({ path: `/tmp/clip_${tag}.png`, clip: { x: 720, y: 140, width: 720, height: 640 } });
  await b.close();
})().catch(e=>{console.error('ERR',e.message);process.exit(1);});
