const { chromium } = require('playwright');
const ORDER = process.argv[2] || '13470';
(async () => {
  const b = await chromium.launch();
  const p = await (await b.newContext({ viewport: { width: 1440, height: 1400 } })).newPage();
  const errs = [];
  p.on('pageerror', e => errs.push(e.message.slice(0, 200)));

  await p.goto('http://localhost/sks/wp-login.php', { waitUntil: 'domcontentloaded' });
  await p.fill('#user_login', 'sks');
  await p.fill('#user_pass', 'Test!2345');
  await p.click('#wp-submit');
  await p.waitForTimeout(2200);

  await p.goto(`http://localhost/sks/my-account/view-order/${ORDER}/`, { waitUntil: 'load' });
  await p.waitForTimeout(1500);

  const info = await p.evaluate(() => {
    const inv = document.querySelector('#hsw-invoice');
    return {
      hasInvoice: !!inv,
      orderNo: document.querySelector('.hsw-inv__no')?.textContent.trim(),
      items: document.querySelectorAll('.hsw-inv__items tbody tr').length,
      total: document.querySelector('.hsw-inv__grand td')?.textContent.trim(),
      printBtn: !!document.querySelector('[data-hsw-print]'),
      printIcon: document.querySelector('.hsw-inv__print .hsw-ic')?.textContent,
      parties: document.querySelectorAll('.hsw-inv__party').length,
      status: document.querySelector('.hsw-inv__status')?.textContent.trim(),
    };
  });
  console.log(JSON.stringify(info, null, 2));
  await p.screenshot({ path: 'D:/work-webs/build/hamishop-elementor-widgets/tests/_shots/invoice.png', fullPage: true });

  // print rendering
  await p.emulateMedia({ media: 'print' });
  await p.waitForTimeout(500);
  await p.screenshot({ path: 'D:/work-webs/build/hamishop-elementor-widgets/tests/_shots/invoice-print.png', fullPage: false });
  if (errs.length) console.log('ERRORS', errs);
  await b.close();
})();
