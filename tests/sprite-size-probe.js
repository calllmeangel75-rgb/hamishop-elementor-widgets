const { chromium } = require('playwright');
const PAGES = [['archive','/shop/'], ['product','/?p=13192'], ['cart','/cart/']];
(async () => {
  const b = await chromium.launch();
  const out = {};
  for (const [name, url] of PAGES) {
    const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
    await p.goto('http://localhost/sks' + url, { waitUntil: 'load' });
    await p.waitForTimeout(800);
    out[name] = await p.evaluate(() => {
      const s = document.querySelector('svg.hsw-sprite');
      if (!s) return { bytes: 0, symbols: 0 };
      const html = s.outerHTML;
      // rough gzip estimate via CompressionStream
      return { bytes: new Blob([html]).size, symbols: s.querySelectorAll('symbol').length };
    });
    await p.close();
  }
  console.log(JSON.stringify(out));
  await b.close();
})();
