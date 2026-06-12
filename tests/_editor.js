const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: 1500, height: 900 } });
  const p = await ctx.newPage();
  // login
  await p.goto('http://localhost:8080/wp-login.php', { waitUntil:'networkidle', timeout:60000 });
  await p.fill('#user_login', 'admin');
  await p.fill('#user_pass', 'admin123');
  await p.click('#wp-submit');
  await p.waitForTimeout(2000);
  // open Elementor editor for Home (38)
  await p.goto('http://localhost:8080/wp-admin/post.php?post=38&action=elementor', { waitUntil:'domcontentloaded', timeout:90000 });
  // wait for the Elementor editor panel + preview to appear
  await p.waitForTimeout(12000);
  await p.screenshot({ path:'/tmp/elementor_editor.png' });
  // report what's on screen
  const has = await p.evaluate(() => ({
    panel: !!document.querySelector('#elementor-panel'),
    previewIframe: !!document.querySelector('#elementor-preview-iframe'),
    title: document.title,
  }));
  console.log(JSON.stringify(has));
  await b.close();
})().catch(e=>{console.error('ERR', e.message);process.exit(1);});
