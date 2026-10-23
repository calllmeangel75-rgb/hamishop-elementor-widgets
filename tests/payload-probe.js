const { chromium } = require('playwright');

const PAGES = [
  { name: 'archive', url: 'http://localhost/sks/shop/' },
  { name: 'product', url: 'http://localhost/sks/?p=13192' },
  { name: 'cart',    url: 'http://localhost/sks/cart/' },
];

(async () => {
  const b = await chromium.launch();
  const out = {};

  for (const page of PAGES) {
    const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
    const p = await ctx.newPage();
    await p.goto(page.url, { waitUntil: 'load' });
    await p.waitForTimeout(1500);

    const res = await p.evaluate(() => {
      const rows = performance.getEntriesByType('resource').map((r) => ({
        url: r.name,
        type: r.initiatorType,
        bytes: r.encodedBodySize || r.transferSize || 0,
      }));
      const isHsw = (u) => u.includes('/hamishop-elementor-widgets/');
      const sum = (list) => list.reduce((a, r) => a + r.bytes, 0);
      const css = rows.filter((r) => isHsw(r.url) && r.url.includes('.css'));
      const js = rows.filter((r) => isHsw(r.url) && r.url.includes('.js'));
      const fonts = rows.filter((r) => isHsw(r.url) && /\.(woff2?|ttf|otf)$/.test(r.url));
      const allCss = rows.filter((r) => r.url.includes('.css'));
      const allJs = rows.filter((r) => r.url.includes('.js'));
      return {
        hswCss: { files: css.length, bytes: sum(css), list: css.map((r) => r.url.split('/').pop() + ':' + Math.round(r.bytes / 1024) + 'KB') },
        hswJs: { files: js.length, bytes: sum(js), list: js.map((r) => r.url.split('/').pop() + ':' + Math.round(r.bytes / 1024) + 'KB') },
        hswFonts: { files: fonts.length, bytes: sum(fonts), list: fonts.map((r) => r.url.split('/').pop() + ':' + Math.round(r.bytes / 1024) + 'KB') },
        pageCss: { files: allCss.length, bytes: sum(allCss) },
        pageJs: { files: allJs.length, bytes: sum(allJs) },
        total: sum(rows),
      };
    });
    out[page.name] = res;
    await ctx.close();
  }

  console.log(JSON.stringify(out, null, 1));
  await b.close();
})();
