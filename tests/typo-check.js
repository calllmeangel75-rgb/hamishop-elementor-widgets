const { chromium } = require('playwright');
const FILE = 'file:///D:/work-webs/build/hamishop-elementor-widgets/tests/typo-fixture.html';
const SEL = ['.hsw-bhero__title','.hsw-bhero__sub','.hsw-bhero__stat b','.hsw-bhero__hot a','.hsw-bfeat__title','.hsw-bfeat__ex','.hsw-bfeat__cat','.hsw-bfeat__meta','.hsw-bgrid__title','.hsw-bgrid__ex','.hsw-bgrid__cat','.hsw-bgrid__meta','.hsw-bpop__title','.hsw-bpop__meta','.hsw-btags__title','.hsw-btags__tag','.hsw-bnews__title','.hsw-bnews__sub','.hsw-pp__title','.hsw-pp__meta','.hsw-pp__toctitle','.hsw-ag__title','.hsw-ag__note','.hsw-ag__price'];

async function measure(page) {
  return page.evaluate((sels) => {
    const out = {};
    sels.forEach((s) => {
      const el = document.querySelector(s);
      out[s.replace('.', '')] = el ? getComputedStyle(el).fontSize : null;
    });
    return out;
  }, SEL);
}

(async () => {
  const b = await chromium.launch();

  const d = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  await d.goto(FILE, { waitUntil: 'load' });
  await d.waitForTimeout(400);
  console.log('DESKTOP', JSON.stringify(await measure(d)));

  // prove the control works: set the variables the widgets would write
  await d.evaluate(() => {
    document.querySelector('.hsw-bhero').style.setProperty('--hsw-bhero-title-fs', '40px');
    document.querySelector('.hsw-bfeat').style.setProperty('--hsw-bfeat-title-fs', '28px');
    document.querySelector('.hsw-ag__card').style.setProperty('--hsw-ag-price-fs', '26px');
  });
  await d.waitForTimeout(150);
  const boosted = await measure(d);
  console.log('OVERRIDDEN', JSON.stringify({ heroTitle: boosted.hswbhero__title || boosted['hsw-bhero__title'], agPrice: boosted['hsw-ag__price'] }));

  const m = await (await b.newContext({ viewport: { width: 390, height: 800 } })).newPage();
  await m.goto(FILE, { waitUntil: 'load' });
  await m.waitForTimeout(400);
  console.log('MOBILE', JSON.stringify(await measure(m)));

  await b.close();
})();
