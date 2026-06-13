const { chromium } = require('playwright');
(async () => {
  const pages = {
    home:'/', about:'/about/', services:'/services/', instructors:'/instructors/',
    contact:'/contact/', resources:'/resources/', icbc:'/resources/icbc-road-test-failures/',
    blog:'/blog/', post:'/blog/class-7l-learners-licence-bc-step-by-step/', login:'/login/'
  };
  const b = await chromium.launch();
  for (const [name, path] of Object.entries(pages)) {
    for (const [vp, w] of [['d',1440],['m',390]]) {
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } });
      const p = await ctx.newPage();
      try {
        await p.goto('http://localhost:8080'+path+'?cb='+Date.now(), { waitUntil:'networkidle', timeout:60000 });
        await p.waitForTimeout(900);
        await p.evaluate(async()=>{ for(let y=0;y<document.body.scrollHeight;y+=700){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,80));} window.scrollTo(0,0); });
        await p.waitForTimeout(400);
        await p.screenshot({ path:`/tmp/sw_${name}_${vp}.png`, fullPage:true });
      } catch(e){ console.log(name,vp,'ERR',e.message); }
      await ctx.close();
    }
    process.stdout.write(name+' ');
  }
  await b.close(); console.log('\nsweep done');
})().catch(e=>{console.error('ERR',e.message);process.exit(1);});
