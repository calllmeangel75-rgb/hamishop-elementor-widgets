const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const p = await (await b.newContext({ viewport: { width: 1440, height: 1100 } })).newPage();
  const errs = [];
  p.on('pageerror', (e) => errs.push(e.message.slice(0, 140)));

  await p.goto('http://localhost/sks/wp-login.php', { waitUntil: 'domcontentloaded' });
  await p.fill('#user_login', 'sks');
  await p.fill('#user_pass', 'Test!2345');
  await p.click('#wp-submit');
  await p.waitForTimeout(2200);

  // wallet page
  await p.goto('http://localhost/sks/my-account/wallet/', { waitUntil: 'load' });
  await p.waitForTimeout(1000);
  const wallet = await p.evaluate(() => ({
    balance: document.querySelector('[data-hsw-wallet-balance]')?.textContent.trim(),
    payForm: !!document.querySelector('[data-hsw-wallet-pay]'),
    topupForm: !!document.querySelector('[data-hsw-wallet-topup]'),
    gateways: [...document.querySelectorAll('#hsw-wallet-gateway option')].map((o) => o.textContent.trim()),
    button: document.querySelector('.hsw-ac__wallet-panel button')?.textContent.trim(),
  }));
  console.log('WALLET', JSON.stringify(wallet));

  // add a cheap product and check the wallet gateway on the pay step
  await p.goto('http://localhost/sks/shop/', { waitUntil: 'load' });
  await p.waitForTimeout(700);
  const add = await p.$('[data-hsw-add]');
  if (add) { await add.click(); await p.waitForTimeout(2000); }
  await p.goto('http://localhost/sks/checkout/', { waitUntil: 'load' });
  await p.waitForTimeout(1200);
  const pay = await p.evaluate(() => ({
    methods: [...document.querySelectorAll('[name="payment_method"]')].map((r) => r.value),
    walletNote: document.querySelector('.hsw-wallet-pay-note')?.textContent.trim().slice(0, 80) || null,
    savings: document.querySelector('.hsw-savings')?.textContent.replace(/\s+/g, ' ').trim() || null,
    savingsBeforeTotal: (() => {
      const s = document.querySelector('.hsw-savings');
      const t = document.querySelector('.hsw-cksum__final');
      if (!s || !t) return null;
      return !!(s.compareDocumentPosition(t) & Node.DOCUMENT_POSITION_FOLLOWING);
    })(),
  }));
  console.log('CHECKOUT', JSON.stringify(pay));

  if (errs.length) console.log('ERRORS', errs);
  await b.close();
})();
