const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const p = await (await b.newContext({ viewport: { width: 1500, height: 1200 } })).newPage();
  const errs = [];
  p.on('pageerror', e => errs.push(e.message.slice(0, 180)));
  await p.goto('http://localhost/sks/wp-login.php', { waitUntil: 'domcontentloaded' });
  await p.fill('#user_login', 'hsw_admin_view');
  await p.fill('#user_pass', 'Admin!2345');
  await p.click('#wp-submit');
  await p.waitForTimeout(2200);
  await p.goto('http://localhost/sks/wp-admin/admin.php?page=hamishop-wallet', { waitUntil: 'load' });
  await p.waitForTimeout(1200);
  const info = await p.evaluate(() => ({
    pendingRows: document.querySelectorAll('table.widefat tbody tr').length,
    goldenRows: document.querySelectorAll('.hsw-pick').length,
    hasAmount: !!document.querySelector('#hsw-wallet-amount'),
    hasSms: !!document.querySelector('input[name="send_sms"]'),
  }));
  console.log(JSON.stringify(info, null, 2));
  await p.screenshot({ path: 'D:/work-webs/build/hamishop-elementor-widgets/tests/_shots/wallet-admin.png', fullPage: true });
  if (errs.length) console.log('ERRORS', errs);
  await b.close();
})();
