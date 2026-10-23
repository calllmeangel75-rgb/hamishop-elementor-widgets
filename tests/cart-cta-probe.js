const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const p = await (await b.newContext({ viewport: { width: 1440, height: 1100 } })).newPage();

  await p.goto('http://localhost/sks/shop/', { waitUntil: 'load' });
  await p.waitForTimeout(700);
  const add = await p.$('[data-hsw-add]');
  if (add) { await add.click(); await p.waitForTimeout(2200); }

  // hammer the cart a few times to build up "cart updated" notices
  for (let i = 0; i < 3; i++) {
    await p.goto('http://localhost/sks/cart/', { waitUntil: 'load' });
    await p.waitForTimeout(600);
    const plus = await p.$('.hsw-c-qty__btn[data-dir="plus"]');
    if (plus) { await Promise.all([p.waitForNavigation({ timeout: 15000 }).catch(() => {}), plus.click()]); }
  }

  await p.goto('http://localhost/sks/cart/', { waitUntil: 'load' });
  await p.waitForTimeout(900);

  const ctaSel = '.hsw-csummary__cta';
  const normal = await p.$eval(ctaSel, (el) => {
    const cs = getComputedStyle(el);
    return { bg: cs.backgroundColor, color: cs.color };
  });
  const box = await p.$eval(ctaSel, (el) => { const r = el.getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; });
  await p.mouse.move(box.x, box.y);
  await p.waitForTimeout(400);
  const hover = await p.$eval(ctaSel, (el) => {
    const cs = getComputedStyle(el);
    return { bg: cs.backgroundColor, color: cs.color };
  });

  const noticesOnCart = await p.$$eval('.hsw-notices .woocommerce-message, .hsw-notices .woocommerce-error', (els) => els.length);

  // now the account addresses page: the queue must be empty by now
  await p.goto('http://localhost/sks/my-account/edit-address/', { waitUntil: 'load' });
  await p.waitForTimeout(1500);
  const account = await p.evaluate(() => ({
    notices: document.querySelectorAll('.woocommerce-message, .woocommerce-error, .woocommerce-info').length,
    hasAddressCards: document.querySelectorAll('.hsw-ac__addr-card').length,
  }));

  console.log(JSON.stringify({ normal, hover, noticesOnCart, account }, null, 2));
  await b.close();
})();
