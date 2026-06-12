const { chromium } = require('playwright');
(async () => {
  const pages = { home:'/', services:'/services/', contact:'/contact/', about:'/about/' };
  const b = await chromium.launch();
  for (const [name, path] of Object.entries(pages)) {
    for (const [vp, w] of [['d',1440],['m',390]]) {
      const ctx = await b.newContext({ viewport:{width:w,height:900}, reducedMotion:'reduce' });
      const p = await ctx.newPage();
      try {
        await p.goto('https://www.buckleupdriving.ca'+path+'?cb='+Date.now(), { waitUntil:'networkidle', timeout:60000 });
        await p.waitForTimeout(900);
        await p.evaluate(async()=>{ for(let y=0;y<document.body.scrollHeight;y+=600){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,60));} window.scrollTo(0,0); });
        await p.waitForTimeout(400);
        await p.screenshot({ path:`/tmp/prod_${name}_${vp}.png`, fullPage:(vp==='d'?false:false) });
      } catch(e){ console.log(name,vp,'ERR',e.message); }
      await ctx.close();
    }
    process.stdout.write(name+' ');
  }
  await b.close(); console.log('\ndone');
})().catch(e=>{console.error('ERR',e.message);process.exit(1);});
