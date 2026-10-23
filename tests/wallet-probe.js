const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const p = await (await b.newContext({ viewport: { width: 1440, height: 1300 } })).newPage();
  const errs = [];
  p.on('pageerror', e => errs.push(e.message.slice(0, 180)));

  await p.goto('http://localhost/sks/wp-login.php', { waitUntil: 'domcontentloaded' });
  await p.fill('#user_login', 'sks');
  await p.fill('#user_pass', 'Test!2345');
  await p.click('#wp-submit');
  await p.waitForTimeout(2200);

  await p.goto('http://localhost/sks/my-account/wallet/', { waitUntil: 'load' });
  await p.waitForTimeout(1200);

  const info = await p.evaluate(() => ({
    balance: document.querySelector('[data-hsw-wallet-balance]')?.textContent.trim(),
    topupForm: !!document.querySelector('[data-hsw-wallet-topup]'),
    convertForm: !!document.querySelector('[data-hsw-wallet-convert]'),
    ledgerRows: document.querySelectorAll('.hsw-ac__ledger tbody tr').length,
    panels: document.querySelectorAll('.hsw-ac__wallet-panel').length,
  }));
  console.log(JSON.stringify(info, null, 2));
  await p.screenshot({ path: 'D:/work-webs/build/hamishop-elementor-widgets/tests/_shots/wallet.png', fullPage: false });

  // submit a top-up request
  if (info.topupForm) {
    await p.fill('#hsw-wallet-amount', '250000');
    await p.fill('#hsw-wallet-note', 'تست مرورگر');
    await p.click('[data-hsw-wallet-topup] button[type="submit"]');
    await p.waitForTimeout(1500);
    const msg = await p.evaluate(() => {
      const m = document.querySelector('[data-hsw-wallet-topup] [data-hsw-wallet-msg]');
      return m ? { text: m.textContent.trim(), hidden: m.hidden } : null;
    });
    console.log('topup msg:', JSON.stringify(msg));
  }
  if (errs.length) console.log('ERRORS', errs);
  await b.close();
})();
