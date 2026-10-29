// Verifies the stage-2 fixes: drawer stacking, Persian digits in WooCommerce
// markup, and the mobile shortcut bar links.
const { chromium } = require('playwright');

const BASE = process.argv[2] || 'http://localhost/sks';
const FA = /[0-9]/;
let failures = 0;

function check(label, ok, detail) {
  if (!ok) { failures++; }
  console.log((ok ? 'ok   ' : 'FAIL ') + label + (detail ? ' — ' + detail : ''));
}

(async () => {
  const browser = await chromium.launch();

  /* ---------- mobile: drawer layering + shortcut bar ---------- */
  {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const page = await ctx.newPage();
    await page.goto(BASE + '/', { waitUntil: 'load' });
    await page.waitForTimeout(800);

    const bar = await page.evaluate(() =>
      [...document.querySelectorAll('.hsw-sf__item')].map((a) => a.getAttribute('href')));
    check('shortcut bar has real links', bar.length > 0 && bar.every((h) => h && h !== '#'), bar.join(' | '));

    await page.click('[data-hsw-hd-drawer-open]');
    await page.waitForTimeout(600);
    const layering = await page.evaluate(() => {
      const hd = document.querySelector('.hsw-hd');
      const cart = document.querySelector('.hsw-hd__drawercart') || document.querySelector('.hsw-hd__drawer a[href*="cart"]');
      const z = getComputedStyle(hd).zIndex;
      let hit = null;
      if (cart) {
        const r = cart.getBoundingClientRect();
        const el = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
        hit = el ? (el.closest('.hsw-hd__drawer') ? 'drawer' : (el.className || el.tagName)) : 'none';
      }
      return { z, hit, fab: (() => { const f = document.querySelector('.haai-fab'); return f ? getComputedStyle(f).zIndex : null; })() };
    });
    check('header lifted over the chat FAB', Number(layering.z) > Number(layering.fab || 0), JSON.stringify(layering));
    check('drawer CTA receives the tap', layering.hit === 'drawer', String(layering.hit));

    await ctx.close();
  }

  /* ---------- cart drawer digits ---------- */
  {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await page.goto(BASE + '/shop/', { waitUntil: 'load' });
    const add = await page.$('.hsw-ag__cta[data-hsw-add]');
    if (add) {
      await add.click();
      await page.waitForTimeout(2000);
      const digits = await page.evaluate(() => {
        const el = document.querySelector('.hsw-hd__cartsummary span:last-child, .hsw-hd__carttotalrow strong');
        return el ? el.textContent.trim() : '';
      });
      check('cart drawer subtotal is Persian', digits !== '' && !FA.test(digits), digits);
    } else {
      check('cart drawer subtotal is Persian', false, 'no add-to-cart button found');
    }
    await ctx.close();
  }

  /* ---------- related products digits ---------- */
  {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    await page.goto(BASE + '/product/%da%86%d9%87%d8%a7%d8%b1%d9%81%d8%b5%d9%84/', { waitUntil: 'load' });
    await page.waitForTimeout(600);
    const prices = await page.evaluate(() =>
      [...document.querySelectorAll('.hsw-prel__price')].map((e) => e.textContent.replace(/\s+/g, ' ').trim()));
    check('related products prices are Persian',
      prices.length > 0 && prices.every((p) => !FA.test(p)), prices.slice(0, 3).join(' / '));
    await ctx.close();
  }

  await browser.close();
  console.log(failures ? 'RESULT: ' + failures + ' problem(s)' : 'RESULT: clean');
  process.exit(failures ? 1 : 0);
})();
