const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const p = await (await b.newContext({ viewport: { width: 1440, height: 1200 } })).newPage();
  await p.goto('http://localhost/sks/wp-login.php', { waitUntil: 'domcontentloaded' });
  await p.fill('#user_login', 'sks');
  await p.fill('#user_pass', 'Test!2345');
  await p.click('#wp-submit');
  await p.waitForTimeout(2200);

  // add something and bump the quantity three times -> "cart updated" notices
  await p.goto('http://localhost/sks/shop/', { waitUntil: 'load' });
  await p.waitForTimeout(700);
  const add = await p.$('[data-hsw-add]');
  if (add) { await add.click(); await p.waitForTimeout(2000); }

  const seen = [];
  for (let i = 0; i < 3; i++) {
    await p.goto('http://localhost/sks/cart/', { waitUntil: 'load' });
    await p.waitForTimeout(700);
    const plus = await p.$('.hsw-c-qty__btn[data-dir="plus"]');
    if (!plus) { break; }
    await Promise.all([p.waitForNavigation({ timeout: 15000 }).catch(() => {}), plus.click()]);
    await p.waitForTimeout(500);
    seen.push(await p.$$eval('.hsw-notices .woocommerce-message', (els) => els.length));
  }

  await p.goto('http://localhost/sks/my-account/edit-address/', { waitUntil: 'load' });
  await p.waitForTimeout(1500);
  const account = await p.evaluate(() => ({
    notices: document.querySelectorAll('.woocommerce-message, .woocommerce-error, .woocommerce-info').length,
    cards: document.querySelectorAll('.hsw-ac__addr-card').length,
  }));

  console.log(JSON.stringify({ noticeCountPerCartLoad: seen, account }, null, 2));
  await p.screenshot({ path: 'D:/work-webs/build/hamishop-elementor-widgets/tests/_shots/addresses-clean.png', fullPage: false });
  await b.close();
})();
