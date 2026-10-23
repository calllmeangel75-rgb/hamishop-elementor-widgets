const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
  const p = await ctx.newPage();
  const errs = [];
  p.on('pageerror', (e) => errs.push(e.message.slice(0, 120)));

  await p.goto('http://localhost/sks/?add-to-cart=13107', { waitUntil: 'load' });
  await p.waitForTimeout(800);
  await p.goto('http://localhost/sks/cart/', { waitUntil: 'load' });
  await p.waitForTimeout(900);

  const plus = await p.$('.hsw-citems__mqty .is-plus');
  if (!plus) { console.log('no mobile plus'); await b.close(); return; }

  // bump to 8
  for (let i = 0; i < 7; i++) {
    await p.click('.hsw-citems__mqty .is-plus');
    await p.waitForTimeout(700);
  }
  const afterPlus = await p.evaluate(() => ({
    value: document.querySelector('.hsw-citems__mqty .hsw-c-qty__v')?.textContent.trim(),
    minus: !!document.querySelector('.hsw-citems__mqty .is-minus'),
    minusDisabled: document.querySelector('.hsw-citems__mqty .is-minus')?.disabled,
    trash: !!document.querySelector('.hsw-citems__mremove'),
  }));
  console.log('AFTER PLUS x7', JSON.stringify(afterPlus));

  // one minus
  await p.click('.hsw-citems__mqty .is-minus');
  await p.waitForTimeout(900);
  console.log('AFTER MINUS', JSON.stringify(await p.evaluate(() => ({
    value: document.querySelector('.hsw-citems__mqty .hsw-c-qty__v')?.textContent.trim(),
    minusDisabled: document.querySelector('.hsw-citems__mqty .is-minus')?.disabled,
  }))));

  // drop to 1
  for (let i = 0; i < 7; i++) {
    const m = await p.$('.hsw-citems__mqty .is-minus');
    if (!m || await m.isDisabled()) { break; }
    await m.click();
    await p.waitForTimeout(700);
  }
  console.log('AT MIN', JSON.stringify(await p.evaluate(() => ({
    value: document.querySelector('.hsw-citems__mqty .hsw-c-qty__v')?.textContent.trim(),
    minusDisabled: document.querySelector('.hsw-citems__mqty .is-minus')?.disabled,
    trash: !!document.querySelector('.hsw-citems__mremove'),
  }))));

  if (errs.length) console.log('ERRORS', errs);
  await p.screenshot({ path: 'D:/work-webs/build/hamishop-elementor-widgets/tests/_shots/mobile-cart.png', fullPage: false });
  await b.close();
})();
