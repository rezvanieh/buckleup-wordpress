const { chromium } = require('playwright');
(async () => {
  const tag = process.argv[2];       // 'live' or 'local'
  const base = process.argv[3];
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1440, height: 950 } });
  await p.goto(base + '/?cb=' + Date.now(), { waitUntil: 'networkidle', timeout: 90000 });
  await p.waitForTimeout(2000);
  // scroll through to trigger lazy/reveal, then back to top
  await p.evaluate(async () => { for (let y=0; y<document.body.scrollHeight; y+=600){ window.scrollTo(0,y); await new Promise(r=>setTimeout(r,120)); } window.scrollTo(0,0); });
  await p.waitForTimeout(800);
  await p.screenshot({ path: `/tmp/cmp_${tag}_hero.png` });   // hero viewport
  for (const sel of ['#pricing','#testimonials','#faq','#graduates']) {
    const el = await p.$(sel);
    if (el){ await el.scrollIntoViewIfNeeded(); await p.waitForTimeout(500); await el.screenshot({path:`/tmp/cmp_${tag}_${sel.slice(1)}.png`}).catch(()=>{}); }
    else console.log(tag,'no',sel);
  }
  await b.close(); console.log(tag,'done');
})().catch(e=>{console.error('ERR',e.message);process.exit(1);});
