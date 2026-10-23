const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const p = await (await b.newContext({ viewport: { width: 1440, height: 1200 } })).newPage();
  const errs = [];
  p.on('pageerror', e => errs.push(e.message.slice(0, 160)));

  await p.goto('http://localhost/sks/shop/', { waitUntil: 'load' });
  await p.waitForTimeout(700);
  const add = await p.$('[data-hsw-add]');
  if (add) { await add.click(); await p.waitForTimeout(2200); }

  await p.goto('http://localhost/sks/checkout/', { waitUntil: 'load' });
  await p.waitForTimeout(1500);

  const info = await p.evaluate(() => ({
    hasForm: !!document.querySelector('[data-hsw-address-form]'),
    fields: [...document.querySelectorAll('[data-hsw-address-form] [name]')].map(e => e.name),
    states: document.querySelectorAll('#hsw-ckaddr-state option').length,
    hint: document.querySelector('.hsw-ckaddr__formhint')?.textContent.trim().slice(0, 60),
  }));
  console.log(JSON.stringify(info, null, 2));

  if (false) {
    await p.fill('#hsw-ckaddr-first_name', 'جواد');
    await p.fill('#hsw-ckaddr-last_name', 'نیک');
    await p.fill('#hsw-ckaddr-phone', '09121234567');
    await p.selectOption('#hsw-ckaddr-state', { index: 1 });
    await p.fill('#hsw-ckaddr-city', 'تهران');
    await p.fill('#hsw-ckaddr-address_1', 'خیابان ولیعصر، پلاک ۱۲');
    await p.fill('#hsw-ckaddr-postcode', '1234567890');
    await Promise.all([
      p.waitForNavigation({ timeout: 25000 }).catch(() => {}),
      p.click('.hsw-ckaddr__fsubmit'),
    ]);
    await p.waitForTimeout(2500);
    const after = await p.evaluate(() => ({
      url: location.href,
      stillForm: !!document.querySelector('[data-hsw-address-form]'),
      line: document.querySelector('.hsw-ckaddr__line')?.textContent.trim().slice(0, 80),
      name: document.querySelector('.hsw-ckaddr__name')?.textContent.trim(),
      msg: document.querySelector('[data-hsw-address-msg]')?.textContent.trim() || null,
    }));
    console.log(JSON.stringify(after, null, 2));
  }
  if (errs.length) console.log('ERRORS', errs);
  await p.screenshot({ path: 'D:/work-webs/build/hamishop-elementor-widgets/tests/_shots/checkout-address.png', fullPage: false });
  await b.close();
})();
