const { chromium } = require('playwright');
(async () => {
  const tag = process.argv[2], base = process.argv[3];
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });
  await p.goto(base + '/?cb=' + Date.now(), { waitUntil: 'networkidle', timeout: 90000 });
  await p.waitForTimeout(1500);
  await p.screenshot({ path: `/tmp/hero_${tag}.png` });
  // measure the hero right-card if identifiable
  const m = await p.evaluate(() => {
    const card = document.querySelector('[class*="hero"] img, .container img');
    return null;
  });
  await b.close(); console.log(tag,'done');
})().catch(e=>{console.error('ERR',e.message);process.exit(1);});
