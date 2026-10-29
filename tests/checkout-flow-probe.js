// End-to-end purchase flow: cart → checkout → payment → order complete.
// Reports every step, console error, failed request and REST error payload.
const { chromium } = require('playwright');

const BASE = process.argv[2] || 'http://localhost/sks';
const MOBILE = process.env.HSW_MOBILE === '1';
const USER = process.env.HSW_USER || 'sks';
const PASS = process.env.HSW_PASS || 'Test!2345';
const PRODUCT = process.env.HSW_PRODUCT || '13161';

const errors = [];
const rest = [];

function step(msg) {
  console.log('\n== ' + msg);
}

async function login(page) {
  await page.goto(BASE + '/wp-login.php', { waitUntil: 'domcontentloaded' });
  const field = await page.$('#user_login');
  if (!field) {
    return; // already authenticated
  }
  await page.fill('#user_login', USER);
  await page.fill('#user_pass', PASS);
  await page.click('#wp-submit');
  try {
    await page.waitForURL((url) => !/wp-login\.php/.test(url.pathname), { timeout: 25000 });
  } catch (e) {
    console.log('login: no redirect observed (' + e.message.split('\n')[0] + ')');
  }
  await page.waitForLoadState('load').catch(() => {});
}

async function visible(page, selector) {
  for (const el of await page.$$(selector)) {
    if (await el.isVisible()) { return el; }
  }
  return null;
}

async function clickVisible(page, selector, force) {
  const el = await visible(page, selector);
  if (!el) { throw new Error('not visible: ' + selector); }
  await el.click({ force: !!force });
}

async function clearCart(page) {
  const count = () => page.evaluate(() =>
    document.querySelectorAll('.hsw-citems__item, .hsw-citems__mcard, .woocommerce-cart-form__cart-item').length);

  for (let i = 0; i < 12; i++) {
    if (!(await count())) { break; }
    const btn = await visible(page, '.hsw-citems__del, .hsw-citems__mremove, a.remove');
    if (!btn) { break; }
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'load', timeout: 15000 }).catch(() => {}),
      btn.click({ force: true }),
    ]);
    await page.waitForTimeout(700);
  }
  return count();
}

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({
    viewport: MOBILE ? { width: 390, height: 844 } : { width: 1440, height: 1100 },
  });
  const page = await ctx.newPage();

  page.on('console', (m) => { if (m.type() === 'error') { errors.push('console: ' + m.text()); } });
  page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
  page.on('response', async (r) => {
    const url = r.url();
    if (r.status() >= 400) { errors.push('http ' + r.status() + ' ' + url); }
    if (/hsw\/v1\/checkout/.test(url) || /wc-ajax/.test(url)) {
      let body = '';
      try { body = (await r.text()).slice(0, 400); } catch (e) { body = '<no body>'; }
      rest.push({ status: r.status(), url: url.split(BASE)[1], body });
    }
  });

  step('login');
  await login(page);
  console.log('url:', page.url());

  step('empty cart');
  await page.goto(BASE + '/cart/', { waitUntil: 'load' });
  console.log('items left:', await clearCart(page));

  step('add product ' + PRODUCT);
  await page.goto(BASE + '/?add-to-cart=' + PRODUCT + '&quantity=1', { waitUntil: 'load' });
  await page.waitForTimeout(600);
  const badge = await page.evaluate(() => {
    const b = document.querySelector('[data-hsw-cart-count]');
    return b ? b.getAttribute('data-count') : 'n/a';
  });
  console.log('badge:', badge);
  if (badge !== '1') { errors.push('badge after adding one item is ' + badge + ' (expected 1)'); }

  step('checkout');
  await page.goto(BASE + '/checkout/', { waitUntil: 'load' });
  await page.waitForTimeout(1200);

  const cta = await visible(page, '.hsw-cksum__cta');
  if (!cta) {
    errors.push('checkout: .hsw-cksum__cta missing');
  } else {
    const info = await cta.evaluate((el) => ({
      href: el.getAttribute('href'),
      hasAddress: el.getAttribute('data-has-address'),
      payUrl: el.getAttribute('data-hsw-pay-url'),
      text: (el.textContent || '').trim().slice(0, 40),
    }));
    console.log('cta:', JSON.stringify(info));

    if (info.hasAddress === '0') {
      step('fill address');
      await clickVisible(page, '[data-hsw-address-edit]');
      await page.waitForTimeout(400);
      const fill = async (sel, value) => {
        const el = await visible(page, sel);
        if (!el) { return; }
        const tag = await el.evaluate((n) => n.tagName.toLowerCase());
        if (tag === 'select') {
          const values = await el.evaluate((n) => [...n.options].map((o) => o.value).filter(Boolean));
          await el.selectOption(values[0]);
        } else {
          await el.fill(value);
        }
      };
      await fill('#hsw-ckaddr-first_name', 'کاربر');
      await fill('#hsw-ckaddr-last_name', 'تست');
      await fill('#hsw-ckaddr-phone', '09121234567');
      await fill('#hsw-ckaddr-state', '');
      await fill('#hsw-ckaddr-city', 'تهران');
      await fill('#hsw-ckaddr-address_1', 'خیابان تست، پلاک ۱');
      await fill('#hsw-ckaddr-postcode', '1234567890');
      await page.waitForTimeout(300);
      await clickVisible(page, '.hsw-ckaddr__fsubmit');
      await page.waitForTimeout(2000);
      const msg = await page.evaluate(() => {
        const m = document.querySelector('[data-hsw-address-msg]');
        return m && !m.hidden ? m.textContent.trim() : '';
      });
      console.log('address message:', msg || '(none)');
      const again = await visible(page, '.hsw-cksum__cta');
      console.log('cta after address:', await again.evaluate((el) => el.getAttribute('data-has-address')));
    }

    step('checkout → payment');
    try {
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'load', timeout: 25000 }),
        clickVisible(page, '.hsw-cksum__cta'),
      ]);
    } catch (e) {
      errors.push('checkout navigation failed: ' + e.message);
    }
    console.log('url:', page.url());
  }

  step('payment');
  if (!/\/payment\//.test(page.url())) {
    await page.goto(BASE + '/payment/', { waitUntil: 'load' });
    await page.waitForTimeout(1000);
  }

  const methods = await page.evaluate(() =>
    [...document.querySelectorAll('input[name^="hsw_pay_method"], input[name="payment_method"]')]
      .map((r) => ({ value: r.value, checked: r.checked })));
  console.log('methods:', JSON.stringify(methods));

  const termsSel = MOBILE
    ? '.hsw-psum__terms-l.is-m input.hsw-k-terms'
    : '.hsw-psum__terms-l:not(.is-m) input.hsw-k-terms';
  const terms = await visible(page, termsSel);
  if (terms) {
    if (!(await terms.isChecked())) { await terms.check({ force: true }); }
    console.log('terms checked:', await terms.isChecked());
  } else {
    console.log('terms checkbox: none');
  }

  const paySel = MOBILE ? '.hsw-psum__cta--m' : '.hsw-psum__cta:not(.hsw-psum__cta--m)';
  const payBtn = await visible(page, paySel);
  if (!payBtn) {
    errors.push('payment: .hsw-psum__cta missing');
  } else {
    console.log('pay button:', (await payBtn.textContent() || '').trim().slice(0, 40));
    step('pay');
    try {
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'load', timeout: 40000 }),
        payBtn.click(),
      ]);
    } catch (e) {
      errors.push('pay navigation failed: ' + e.message);
    }
    await page.waitForTimeout(1500);
    console.log('final url:', page.url());

    const final = await page.evaluate(() => ({
      title: document.title,
      bodyClass: document.body.className.slice(0, 120),
      orderNumber: (document.querySelector('[class*="cmp__order"] strong, [class*="order-number"]') || {}).textContent || '',
      notices: [...document.querySelectorAll('.woocommerce-error, .woocommerce-message, .woocommerce-info, [role="alert"]')]
        .map((n) => (n.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 140)),
      h1: (document.querySelector('h1, h2') || {}).textContent || '',
    }));
    console.log('final page:', JSON.stringify(final, null, 1));
  }

  console.log('\n== REST calls');
  rest.forEach((r) => console.log(r.status, r.url, '→', r.body));

  console.log('\n== ERRORS');
  console.log(errors.length ? errors.join('\n') : 'none');

  await browser.close();
  process.exit(errors.length ? 2 : 0);
})();
