const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const p = await (await b.newContext({ viewport: { width: 1440, height: 1200 } })).newPage();
  await p.goto('http://localhost/sks/wp-login.php', { waitUntil: 'domcontentloaded' });
  await p.fill('#user_login', 'sks');
  await p.fill('#user_pass', 'Test!2345');
  await p.click('#wp-submit');
  await p.waitForTimeout(2500);
  await p.goto('http://localhost/sks/my-account/edit-address/billing/', { waitUntil: 'load' });
  await p.waitForTimeout(1500);
  const info = await p.evaluate(() => {
    const f = document.querySelector('form.woocommerce-address-fields');
    const fields = [...document.querySelectorAll('.woocommerce-address-fields input, .woocommerce-address-fields select')].slice(0, 6).map((e) => {
      const cs = getComputedStyle(e);
      return { name: e.name || e.id, h: Math.round(e.getBoundingClientRect().height), radius: cs.borderRadius, bg: cs.backgroundColor, border: cs.borderColor };
    });
    return { hasForm: !!f, fields, wrappers: [...document.querySelectorAll('.hsw-ac__wc-wrap')].length };
  });
  console.log(JSON.stringify(info, null, 2));
  await p.screenshot({ path: 'D:/work-webs/build/hamishop-elementor-widgets/tests/_shots/account-address-edit.png', fullPage: true });
  await b.close();
})();
