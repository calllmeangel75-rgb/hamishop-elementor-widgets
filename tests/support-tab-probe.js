const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const p = await (await b.newContext({ viewport: { width: 1400, height: 1200 } })).newPage();
  await p.goto('http://localhost/sks/wp-login.php', { waitUntil: 'domcontentloaded' });
  await p.fill('#user_login', 'hsw_admin_view');
  await p.fill('#user_pass', 'Admin!2345');
  await p.click('#wp-submit');
  await p.waitForTimeout(2500);
  await p.goto('http://localhost/sks/wp-admin/admin.php?page=hamishop&tab=support', { waitUntil: 'load' });
  await p.waitForTimeout(1500);
  const info = await p.evaluate(() => {
    const codes = [...document.querySelectorAll('code')].map(c => c.textContent.trim()).filter(t => t.includes('ticket-inbound') || t.length > 20);
    return { codes, font: getComputedStyle(document.body).fontFamily };
  });
  console.log(JSON.stringify(info, null, 2));
  await p.screenshot({ path: 'D:/work-webs/build/hamishop-elementor-widgets/tests/_shots/adm-support.png', fullPage: true });
  await b.close();
})();
