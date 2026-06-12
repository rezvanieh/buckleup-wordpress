const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1440, height: 1600 } });
  const pages = { about:'/about/', services:'/services/', contact:'/contact/', instructors:'/instructors/', resources:'/resources/', icbc:'/icbc-road-test-failures/' };
  for (const [name, path] of Object.entries(pages)) {
    try {
      await p.goto('http://localhost:8080'+path+'?cb='+Date.now(), { waitUntil:'networkidle', timeout:60000 });
      await p.waitForTimeout(900);
      await p.screenshot({ path:'/tmp/pg_'+name+'.png' });
      console.log(name,'ok');
    } catch(e){ console.log(name,'ERR',e.message); }
  }
  await b.close();
})().catch(e=>{console.error('ERR',e.message);process.exit(1);});
