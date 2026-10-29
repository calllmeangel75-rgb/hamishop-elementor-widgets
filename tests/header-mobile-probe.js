// Mobile header rhythm: both headers must distribute their items evenly and
// centre them on one line, and the logo must keep the shine effect.
const { chromium } = require('playwright');

const BASE = process.argv[2] || 'http://localhost/sks';
const PAGE = BASE + '/?p=13369'; // Elementor "هدر" template: the store header
let failures = 0;

const check = (label, ok, detail) => {
  if (!ok) { failures++; }
  console.log((ok ? 'ok   ' : 'FAIL ') + label + (detail ? ' — ' + detail : ''));
};

/* Widest spread between neighbouring gaps, in css px. */
const spread = (gaps) => (gaps.length ? Math.max.apply(null, gaps) - Math.min.apply(null, gaps) : -1);

async function inspect(page, selector) {
  return page.evaluate((sel) => {
    const row = document.querySelector(sel);
    if (!row) { return null; }
    const items = [...row.querySelectorAll(':scope > *, :scope > * > .hsw-hd__iconbtn, :scope > * > .hsw-hd__account, :scope > * > .hsw-hd__cart, :scope > * > .hsw-hd__action--minimal')]
      .filter((el) => {
        const r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0 && getComputedStyle(el).display !== 'contents';
      })
      .map((el) => {
        const r = el.getBoundingClientRect();
        return { c: String(el.className || el.tagName).split(' ')[0], x: r.x, w: r.width, cy: r.y + r.height / 2 };
      })
      .sort((a, b) => a.x - b.x);
    const gaps = items.slice(1).map((it, i) => Math.round(it.x - (items[i].x + items[i].w)));
    const centres = items.map((it) => Math.round(it.cy));
    return {
      justify: getComputedStyle(row).justifyContent,
      count: items.length,
      gaps,
      verticalSpread: centres.length ? Math.max.apply(null, centres) - Math.min.apply(null, centres) : -1,
      left: Math.round(items[0].x),
      right: Math.round(390 - (items[items.length - 1].x + items[items.length - 1].w)),
    };
  }, selector);
}

(async () => {
  const browser = await chromium.launch();

  for (const width of [390, 360]) {
    const ctx = await browser.newContext({ viewport: { width: width, height: 844 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();
    const errors = [];
    const missing = [];
    page.on('console', (m) => { if (m.type() === 'error') { errors.push(m.text()); } });
    page.on('pageerror', (e) => errors.push(e.message));
    page.on('response', (r) => { if (r.status() >= 400) { missing.push(r.status() + ' ' + r.url()); } });

    await page.goto(PAGE, { waitUntil: 'load' });
    await page.waitForTimeout(900);

    /* ---- store header ---- */
    const store = await inspect(page, '.hsw-hd--store .hsw-hd__maininner');
    check(width + ': store header row found', !!store);
    if (store) {
      check(width + ': store row distributes evenly', /space-evenly|space-between/.test(store.justify), store.justify);
      check(width + ': store gaps are equal', spread(store.gaps) <= 2, JSON.stringify(store.gaps));
      check(width + ': store items share one baseline', store.verticalSpread <= 1, String(store.verticalSpread));
      check(width + ': store row does not overflow', store.left >= 0 && store.right >= 0, JSON.stringify({ left: store.left, right: store.right }));
    }

    /* ---- minimal pill, built from the same tokens ---- */
    const pill = await page.evaluate(() => {
      const root = document.querySelector('.hsw-hd');
      if (!root) { return null; }
      const clone = root.cloneNode(false);
      clone.className = root.className.replace('hsw-hd--store', 'hsw-hd--minimal').replace('is-fixed', '');
      clone.removeAttribute('style');
      clone.style.position = 'static';
      clone.id = 'qa-pill';
      clone.innerHTML =
        '<div class="hsw-hd__minwrap"><div class="hsw-hd__pill">' +
        '<button class="hsw-hd__burger" type="button">' + ((root.querySelector('.hsw-hd__burger') || {}).innerHTML || '') + '</button>' +
        '<div class="hsw-hd__brandwrap"><a class="hsw-hd__brand" href="#">' + ((root.querySelector('.hsw-hd__logomark') || {}).outerHTML || '') + '</a></div>' +
        '<div class="hsw-hd__minactions">' +
        '<button class="hsw-hd__iconbtn" type="button">*</button>' +
        ((root.querySelector('.hsw-hd__cart') || {}).outerHTML || '') +
        '</div></div></div>';
      document.body.appendChild(clone);
      return true;
    });
    const pillInfo = pill ? await inspect(page, '#qa-pill .hsw-hd__pill') : null;
    check(width + ': pill row found', !!pillInfo);
    if (pillInfo) {
      check(width + ': pill row distributes evenly', /space-evenly|space-between/.test(pillInfo.justify), pillInfo.justify);
      check(width + ': pill gaps are equal', spread(pillInfo.gaps) <= 2, JSON.stringify(pillInfo.gaps));
      check(width + ': pill items share one baseline', pillInfo.verticalSpread <= 1, String(pillInfo.verticalSpread));
    }

    /* ---- logo shine ---- */
    const shine = await page.evaluate(() => {
      const mark = document.querySelector('.hsw-hd__logomark');
      if (!mark) { return null; }
      const after = getComputedStyle(mark, '::after');
      return { cls: /--shine/.test(mark.className), anim: after.animationName, bg: after.backgroundImage.slice(0, 40) };
    });
    check(width + ': logo carries the shine class', !!shine && shine.cls, JSON.stringify(shine));
    check(width + ': shine sweep animates', !!shine && shine.anim && shine.anim !== 'none', shine ? shine.anim : '');

    /* Only our own assets matter here: the template page also pulls theme and
       Elementor files whose 404s predate this probe. */
    const ours = missing.filter((m) => /hamishop-elementor-widgets/.test(m));
    check(width + ': our assets load', ours.length === 0, ours.slice(0, 2).join(' | '));
    check(width + ': no script errors', errors.filter((e) => !/Failed to load resource/.test(e)).length === 0,
      errors.filter((e) => !/Failed to load resource/.test(e)).slice(0, 2).join(' | '));
    if (missing.length) { console.log('     (page 4xx, not ours: ' + missing.slice(0, 3).join(' | ') + ')'); }
    await ctx.close();
  }

  await browser.close();
  console.log(failures ? 'RESULT: ' + failures + ' problem(s)' : 'RESULT: clean');
  process.exit(failures ? 1 : 0);
})();
