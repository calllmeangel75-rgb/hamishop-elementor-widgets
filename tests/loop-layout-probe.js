// Measures the loop cards on the test page at both breakpoints.
// Fails when a price/button pair overlaps or a rail reserves dead height.
const { chromium } = require('playwright');

const URL = process.argv[2] || 'http://localhost/sks/hsw-loop-test/';
const VIEWPORTS = [
  { name: 'mobile', width: 390, height: 844 },
  { name: 'tablet', width: 779, height: 900 },
  { name: 'desktop', width: 1440, height: 900 },
];

(async () => {
  const browser = await chromium.launch();
  let failures = 0;

  for (const vp of VIEWPORTS) {
    const ctx = await browser.newContext({ viewport: { width: vp.width, height: vp.height } });
    const page = await ctx.newPage();
    const errors = [];
    page.on('console', (m) => {
      if (m.type() === 'error') { errors.push(m.text()); }
    });
    page.on('response', (r) => {
      if (r.status() >= 400) { errors.push(r.status() + ' ' + r.url()); }
    });

    await page.goto(URL, { waitUntil: 'load' });
    await page.waitForTimeout(900);

    const report = await page.evaluate(() => {
      const out = [];
      document.querySelectorAll('.hsw-loop').forEach((root, i) => {
        const cards = [...root.querySelectorAll('.hsw-loop__card')];
        let overlap = 0;
        let worst = '';
        cards.forEach((card) => {
          const prices = card.querySelector('.hsw-loop__prices');
          const actions = card.querySelector('.hsw-loop__actions');
          if (!prices || !actions) { return; }
          const p = prices.getBoundingClientRect();
          const a = actions.getBoundingClientRect();
          // RTL: the actions sit on the inline-end, i.e. to the left of prices.
          const dx = Math.min(p.right, a.right) - Math.max(p.left, a.left);
          const dy = Math.min(p.bottom, a.bottom) - Math.max(p.top, a.top);
          // Only an overlap of painted text matters: compare content width too.
          const text = prices.scrollWidth;
          const box = prices.clientWidth;
          const painted = Math.max(0, Math.round(text - box));
          if (dx > 1 && dy > 1) {
            overlap++;
            worst = (card.querySelector('.hsw-loop__name') || {}).textContent || '';
          }
          if (painted > 1) {
            overlap++;
            worst = worst || ((card.querySelector('.hsw-loop__name') || {}).textContent || '');
          }
        });

        const rail = root.querySelector('[data-hsw-rail]');
        const body = root.querySelector('.hsw-loop__body');
        const foot = root.querySelector('.hsw-loop__foot');
        const btn = root.querySelector('.hsw-loop__btn');
        out.push({
          index: i + 1,
          cls: root.className.replace(/\s+/g, ' ').trim().slice(0, 60),
          cards: cards.length,
          overlap,
          worst: (worst || '').trim().slice(0, 30),
          railH: rail ? Math.round(rail.getBoundingClientRect().height) : 0,
          cardH: cards[0] ? Math.round(cards[0].getBoundingClientRect().height) : 0,
          bodyH: body ? Math.round(body.getBoundingClientRect().height) : 0,
          footH: foot ? Math.round(foot.getBoundingClientRect().height) : 0,
          btnH: btn ? Math.round(btn.getBoundingClientRect().height) : 0,
        });
      });
      return out;
    });

    console.log('== ' + vp.name + ' ' + vp.width + 'px');
    report.forEach((r) => {
      const bad = r.overlap > 0 || r.railH - r.cardH > 12;
      if (bad) { failures++; }
      console.log(
        (bad ? 'FAIL' : 'ok  ') +
          ' #' + r.index +
          ' cards=' + r.cards +
          ' overlap=' + r.overlap + (r.worst ? ' («' + r.worst + '»)' : '') +
          ' railH=' + r.railH + ' cardH=' + r.cardH +
          ' footH=' + r.footH + ' btnH=' + r.btnH +
          ' slack=' + (r.railH - r.cardH)
      );
    });
    if (errors.length) {
      failures++;
      console.log('console/network:', errors.slice(0, 5));
    }
    await ctx.close();
  }

  await browser.close();
  console.log(failures ? 'RESULT: ' + failures + ' problem(s)' : 'RESULT: clean');
  process.exit(failures ? 1 : 0);
})();
