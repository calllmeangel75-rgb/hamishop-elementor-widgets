const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const c = await b.newContext({ viewport: { width: 1440, height: 900 } });
  const p = await c.newPage();
  const errs = [];
  p.on('pageerror', e => errs.push(String(e).slice(0, 120)));
  p.on('console', m => { if (m.type() === 'error') errs.push(m.text().slice(0, 120)); });

  await p.goto('http://localhost/sks/hsw-min-test/?v=' + Date.now(), { waitUntil: 'domcontentloaded' });
  await p.waitForTimeout(2000);

  const r = await p.evaluate(() => {
    const cs = s => { const el = document.querySelector(s); return el ? getComputedStyle(el) : null; };
    const hdr = cs('.hsw-hd');
    const card = cs('.hsw-card');
    const add = cs('.hsw-add');
    const ic = document.querySelector('.hsw-ic');
    return {
      headerBg: hdr ? hdr.backgroundColor : null,
      headerPos: hdr ? hdr.position : null,
      cardRadius: card ? card.borderTopLeftRadius : null,
      addBg: add ? add.backgroundColor : null,
      iconFont: ic ? getComputedStyle(ic).fontFamily.slice(0, 26) : null,
      cards: document.querySelectorAll('.hsw-card').length,
      minCss: Array.from(document.styleSheets).filter(s => (s.href || '').includes('.min.css')).length,
    };
  });
  console.log(JSON.stringify(r, null, 1));
  console.log('errors:', errs.length ? errs.slice(0, 3) : 'none');
  await p.screenshot({ path: 'tests/_diag/min-page.png', clip: { x: 0, y: 0, width: 1440, height: 700 } });
  await b.close();
})();
