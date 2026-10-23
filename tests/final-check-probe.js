const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  const errs = [];
  p.on('pageerror', (e) => errs.push(e.message.slice(0, 120)));

  const pages = ['/', '/shop/', '/?p=13192', '/cart/', '/checkout/', '/my-account/'];
  const report = [];
  for (const url of pages) {
    await p.goto('http://localhost/sks' + url, { waitUntil: 'load' });
    await p.waitForTimeout(800);
    report.push(await p.evaluate((u) => {
      const icons = [...document.querySelectorAll('svg.hsw-ic')];
      const missing = icons.filter((el) => {
        const id = (el.querySelector('use')?.getAttribute('href') || '').replace('#', '');
        return id && !document.getElementById(id);
      }).length;
      return { url: u, icons: icons.length, missing, sprite: !!document.querySelector('svg.hsw-sprite') };
    }, url));
  }

  // wishlist toggle swaps to the filled symbol
  await p.goto('http://localhost/sks/shop/', { waitUntil: 'load' });
  await p.waitForTimeout(700);
  const wish = await p.$('[data-hsw-wish]');
  let wishResult = 'no button';
  if (wish) {
    await wish.click();
    await p.waitForTimeout(400);
    wishResult = await p.evaluate(() => {
      const el = document.querySelector('[data-hsw-wish] use');
      return el ? el.getAttribute('href') : 'no use';
    });
  }

  console.log(JSON.stringify({ report, wishAfterClick: wishResult, errs }, null, 1));
  await b.close();
})();
