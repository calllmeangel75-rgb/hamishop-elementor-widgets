// Wallet top-up end to end: request → order-pay → gateway → balance + ledger.
const { chromium } = require('playwright');

const BASE = 'http://localhost/sks';
const AMOUNT = process.env.HSW_TOPUP || '100000';

const digits = (s) => String(s || '')
  .replace(/[\u06F0-\u06F9]/g, (d) => String(d.charCodeAt(0) - 0x06F0))
  .replace(/[\u0660-\u0669]/g, (d) => String(d.charCodeAt(0) - 0x0660))
  .replace(/[^\d]/g, '');

async function login(page) {
  await page.goto(BASE + '/wp-login.php', { waitUntil: 'domcontentloaded' });
  if (!(await page.$('#user_login'))) { return; }
  await page.fill('#user_login', 'sks');
  await page.fill('#user_pass', 'Test!2345');
  await page.click('#wp-submit');
  await page.waitForURL((u) => !/wp-login\.php/.test(u.pathname), { timeout: 25000 }).catch(() => {});
}

const readBalance = (page) => page.evaluate(() => {
  const el = document.querySelector('[data-hsw-wallet-balance]');
  return el ? el.innerText : (document.querySelector('.hsw-ac__wallet-main h2') || {}).innerText || '';
});

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 1100 } });
  const page = await ctx.newPage();
  const errors = [];
  page.on('console', (m) => { if (m.type() === 'error') { errors.push('console: ' + m.text()); } });
  page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
  page.on('response', (r) => { if (r.status() >= 400) { errors.push('http ' + r.status() + ' ' + r.url()); } });

  await login(page);

  await page.goto(BASE + '/my-account/wallet/', { waitUntil: 'load' });
  await page.waitForTimeout(800);
  const beforeRaw = await readBalance(page);
  const before = Number(digits(beforeRaw)) || 0;
  console.log('balance before:', digits(beforeRaw));

  const amount = await page.$('form[data-hsw-wallet-pay] [name="amount"]');
  if (!amount) {
    console.log('FAIL: no online top-up form (mode=card?)');
    await browser.close();
    process.exit(2);
  }

  await amount.fill(AMOUNT);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'load', timeout: 30000 }).catch(() => {}),
    page.click('form[data-hsw-wallet-pay] button[type="submit"]'),
  ]);
  await page.waitForTimeout(1200);
  console.log('after submit url:', page.url().split('?')[0]);

  // WooCommerce order-pay screen: pick the available gateway and place the order.
  if (/\/order-pay\//.test(page.url())) {
    const methods = await page.evaluate(() =>
      [...document.querySelectorAll('input[name="payment_method"]')].map((r) => ({ id: r.value, checked: r.checked })));
    console.log('gateways:', JSON.stringify(methods));
    const payBtn = await page.$('#place_order');
    if (payBtn) {
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'load', timeout: 40000 }).catch(() => {}),
        payBtn.click(),
      ]);
      await page.waitForTimeout(1500);
      console.log('after pay url:', page.url().split('?')[0]);
    } else {
      console.log('no #place_order on the pay screen');
    }
  } else {
    const msg = await page.evaluate(() => {
      const m = document.querySelector('[data-hsw-wallet-msg]');
      return m ? m.textContent.trim() : '';
    });
    console.log('top-up message:', msg || '(none)');
  }

  await page.goto(BASE + '/my-account/wallet/', { waitUntil: 'load' });
  await page.waitForTimeout(900);
  const afterRaw = await readBalance(page);
  const after = Number(digits(afterRaw)) || 0;
  console.log('balance after:', after, 'delta:', after - before);

  const ledger = await page.evaluate(() => {
    const rows = [...document.querySelectorAll('.hsw-ac__ledger tbody tr, table.hsw-ac__ledger tr')];
    return rows.slice(0, 3).map((r) => r.innerText.replace(/\s+/g, ' ').trim().slice(0, 80));
  });
  console.log('ledger rows:', JSON.stringify(ledger));

  console.log('errors:', errors.length ? errors : 'none');
  await browser.close();
  process.exit(after - before === Number(AMOUNT) ? 0 : 2);
})();
