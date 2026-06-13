const { chromium } = require('playwright');
(async () => {
  const base = process.argv[2];
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });
  await p.goto(base + '/?cb=' + Date.now(), { waitUntil: 'networkidle', timeout: 90000 });
  await p.waitForTimeout(1500);
  const r = await p.evaluate(() => {
    const out = {};
    // hero grid columns: the hero uses grid lg:grid-cols-[1.2fr_1fr]
    const grid = [...document.querySelectorAll('section .grid, section [class*="grid-cols"]')].find(g => g.querySelector('img') && g.getBoundingClientRect().top < 700);
    if (grid){ const gr = grid.getBoundingClientRect(); out.grid = {w:Math.round(gr.width), cols: getComputedStyle(grid).gridTemplateColumns, children: grid.children.length};
      out.cols = [...grid.children].map(c=>{const r=c.getBoundingClientRect(); return {w:Math.round(r.width),h:Math.round(r.height),top:Math.round(r.top)};}); }
    // the biggest image near top (the car photo / hero card)
    const imgs = [...document.querySelectorAll('img')].filter(i=>i.getBoundingClientRect().top<800 && i.getBoundingClientRect().width>120);
    out.heroImgs = imgs.slice(0,4).map(i=>{const r=i.getBoundingClientRect(); return {w:Math.round(r.width),h:Math.round(r.height),top:Math.round(r.top), natW:i.naturalWidth, natH:i.naturalHeight, src:(i.currentSrc||i.src).split('/').pop()};});
    return out;
  });
  console.log(JSON.stringify(r,null,1));
  await b.close();
})().catch(e=>{console.error('ERR',e.message);process.exit(1);});
