const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const p = await (await b.newContext({ viewport: { width: 1440, height: 1100 } })).newPage();
  const errs = [];
  p.on('pageerror', e => errs.push(e.message.slice(0, 200)));

  await p.goto('http://localhost/sks/shop/', { waitUntil: 'load' });
  await p.waitForTimeout(900);

  const btn = await p.$('[data-hsw-add]');
  await btn.click();

  let modal = null;
  try {
    await p.waitForSelector('.hsw-modal.is-show', { timeout: 12000 });
    modal = await p.evaluate(() => {
      const m = document.querySelector('.hsw-modal');
      return {
        title: m.querySelector('.hsw-modal__title')?.textContent.trim(),
        items: m.querySelectorAll('.woocommerce-mini-cart-item').length,
        total: m.querySelector('.woocommerce-mini-cart__total')?.textContent.replace(/\s+/g, ' ').trim(),
        buttons: [...m.querySelectorAll('.hsw-modal__btn')].map(a => a.textContent.trim()),
        locked: document.body.classList.contains('hsw-scroll-lock'),
        role: m.getAttribute('role'),
        ariaModal: m.getAttribute('aria-modal'),
        toastVisible: !!document.querySelector('.hsw-toast.is-show'),
      };
    });
  } catch (e) {
    modal = 'timeout waiting for modal';
  }
  console.log('modal:', JSON.stringify(modal));

  await p.keyboard.press('Escape');
  let closed = false;
  try {
    await p.waitForSelector('.hsw-modal', { state: 'detached', timeout: 4000 });
    closed = true;
  } catch (e) { closed = false; }
  console.log('ESC closed:', closed, '| scroll unlocked:', await p.evaluate(() => !document.body.classList.contains('hsw-scroll-lock')));

  // savings: force an on-sale product into the cart
  await p.goto('http://localhost/sks/?add-to-cart=13115', { waitUntil: 'load' });
  await p.waitForTimeout(800);
  await p.goto('http://localhost/sks/cart/', { waitUntil: 'load' });
  await p.waitForTimeout(1200);
  console.log('cart:', JSON.stringify(await p.evaluate(() => ({
    items: document.querySelectorAll('.hsw-citems__item').length,
    savings: document.querySelector('.hsw-savings')?.textContent.replace(/\s+/g, ' ').trim() || null,
    count: document.querySelectorAll('.hsw-savings').length,
  }))));

  await p.screenshot({ path: 'D:/work-webs/build/hamishop-elementor-widgets/tests/_shots/cart-savings.png', fullPage: false });
  if (errs.length) console.log('ERRORS', errs);
  await b.close();
})();
