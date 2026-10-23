const { chromium } = require('playwright');
const PAGES = ['/', '/shop/', '/?p=13192', '/cart/', '/checkout/', '/my-account/'];
(async () => {
  const b = await chromium.launch();
  const out = [];
  for (const url of PAGES) {
    const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
    const errs = [];
    p.on('pageerror', (e) => errs.push(e.message.slice(0, 120)));
    await p.goto('http://localhost/sks' + url, { waitUntil: 'load', timeout: 60000 });
    await p.waitForTimeout(900);
    const info = await p.evaluate(() => {
      const sprite = document.querySelector('svg.hsw-sprite');
      const symbols = sprite ? sprite.querySelectorAll('symbol').length : 0;
      const icons = [...document.querySelectorAll('svg.hsw-ic')];
      const zero = icons.filter((el) => {
        const r = el.getBoundingClientRect();
        return r.width === 0 && r.height === 0 && el.offsetParent !== null;
      }).length;
      const missing = icons.filter((el) => {
        const href = el.querySelector('use')?.getAttribute('href') || '';
        return !document.querySelector(href);
      }).length;
      const text = document.body.innerText.match(/\b(add_shopping_cart|check_circle|expand_more|local_shipping)\b/g);
      return { icons: icons.length, symbols, zeroSizedVisible: zero, unresolved: missing, rawNames: text ? text.length : 0 };
    });
    out.push({ url, ...info, errs });
    await p.close();
  }
  console.log(JSON.stringify(out, null, 1));
  await b.close();
})();
